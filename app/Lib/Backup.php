<?php

namespace App\Lib;

use App\Core\App;
use App\Core\Blob;
use App\Core\Schema;
use App\Core\Settings;

/**
 * Sao lưu / phục hồi toàn bộ dữ liệu (kể cả tệp PDF, logo… đang lưu trong CSDL) sang định dạng .tnbak:
 *
 *   "TNBK1\n" + [4 byte độ dài (big-endian)][khối nén DEFLATE] + [4 byte][khối nén] + …
 *
 * Mỗi khối là các dòng JSON: {"meta":…} · {"table":"users","cols":[…],"bin":[…]} · {"r":[giá trị theo cols]} · {"end":true}
 * - Ghi & đọc theo luồng, bộ nhớ dùng ít, không tạo tệp tạm trên hosting.
 * - Không phụ thuộc loại CSDL: sao lưu từ SQLite có thể phục hồi vào MySQL và ngược lại.
 * - Phục hồi chia thành nhiều bước ngắn (mỗi bước vài giây) để không vướng giới hạn thời gian của hosting.
 */
final class Backup
{
    public const MAGIC = "TNBK1\n";
    public const FRAME = 1572864;   // ~1,5 MB văn bản JSON mỗi khối
    public const CHUNK = 524288;    // 512 KB mỗi khúc khi tải lên
    /** Không sao lưu: phiên đăng nhập, vùng đệm phục hồi, lượt đăng nhập (dữ liệu tạm). */
    public const EXCLUDE = ['web_sessions', 'restore_chunks', 'login_attempts'];

    /** Cấu trúc các bảng cần sao lưu: cột, cột nhị phân, khóa chính tự tăng. */
    public static function tables(bool $withLogs = true): array
    {
        $out = [];
        foreach (Schema::tables() as $name => $def) {
            if (in_array($name, self::EXCLUDE, true) || (!$withLogs && in_array($name, ['audit_logs', 'error_logs'], true))) {
                continue;
            }
            $cols = [];
            $bin = [];
            $pk = null;
            foreach ($def as $col => $spec) {
                if ($col[0] === '@') {
                    continue;
                }
                $cols[] = $col;
                $type = explode(':', explode('|', $spec)[0])[0];
                if ($type === 'blob') {
                    $bin[] = $col;
                }
                if ($type === 'id') {
                    $pk = $col;
                }
            }
            $out[$name] = ['cols' => $cols, 'bin' => $bin, 'pk' => $pk];
        }
        return $out;
    }

    public static function manifest(bool $withLogs = true): array
    {
        $db = App::db();
        $counts = [];
        foreach (array_keys(self::tables($withLogs)) as $t) {
            $counts[$t] = (int) $db->value('SELECT COUNT(*) FROM {' . $t . '}');
        }
        return [
            'format' => 1,
            'app' => 'thi-trac-nghiem',
            'version' => TN_VERSION,
            'schema' => Schema::VERSION,
            'driver' => $db->isSqlite() ? 'sqlite' : 'mysql',
            'created_at' => time(),
            'site' => (string) Settings::get('site_name'),
            'org' => (string) Settings::get('org_name'),
            'with_logs' => $withLogs,
            'counts' => $counts,
        ];
    }

    /**
     * Ghi bản sao lưu vào luồng $out (vd. php://output).
     * @param resource $out
     */
    public static function write($out, bool $withLogs = true): array
    {
        $db = App::db();
        $buf = '';
        $stats = ['frames' => 0, 'rows' => 0, 'bytes' => strlen(self::MAGIC)];
        fwrite($out, self::MAGIC);
        $flush = static function (bool $force) use (&$buf, $out, &$stats): void {
            if ($buf === '' || (!$force && strlen($buf) < self::FRAME)) {
                return;
            }
            $z = gzdeflate($buf, 6);
            fwrite($out, pack('N', strlen($z)) . $z);
            $stats['frames']++;
            $stats['bytes'] += 4 + strlen($z);
            $buf = '';
            @flush();
        };
        $line = static function (array $obj) use (&$buf, $flush): void {
            $buf .= json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION) . "\n";
            $flush(false);
        };
        $manifest = self::manifest($withLogs);
        $line(['meta' => $manifest]);
        foreach (self::tables($withLogs) as $t => $info) {
            $line(['table' => $t, 'cols' => $info['cols'], 'bin' => $info['bin']]);
            $colSql = implode(', ', $info['cols']);
            $emit = static function (array $r) use ($info, $line, &$stats): void {
                foreach ($info['bin'] as $b) {
                    if ($r[$b] !== null) {
                        $r[$b] = base64_encode(is_resource($r[$b]) ? (string) stream_get_contents($r[$b]) : (string) $r[$b]);
                    }
                }
                $line(['r' => array_values($r)]);
                $stats['rows']++;
            };
            if ($info['pk']) {
                $last = 0;
                $batch = $info['bin'] ? 6 : 500;
                while (true) {
                    $rows = $db->all('SELECT ' . $colSql . ' FROM {' . $t . '} WHERE ' . $info['pk'] . ' > ? ORDER BY ' . $info['pk'] . ' LIMIT ' . $batch, [$last]);
                    if (!$rows) {
                        break;
                    }
                    foreach ($rows as $r) {
                        $last = (int) $r[$info['pk']];
                        $emit($r);
                    }
                }
            } else {
                foreach ($db->all('SELECT ' . $colSql . ' FROM {' . $t . '}') as $r) {
                    $emit($r);
                }
            }
        }
        $line(['end' => true, 'rows' => $stats['rows']]);
        $flush(true);
        return $stats + ['manifest' => $manifest];
    }

    // ------------------------------------------------------------------ Phục hồi

    /** Đọc $len byte tại vị trí $offset của tệp đã tải lên (lưu từng khúc trong bảng restore_chunks). */
    public static function readBytes(string $token, int $offset, int $len): string
    {
        $db = App::db();
        $out = '';
        while ($len > 0) {
            $seq = intdiv($offset, self::CHUNK);
            $in = $offset % self::CHUNK;
            $data = $db->value('SELECT data FROM {restore_chunks} WHERE token = ? AND seq = ?', [$token, $seq]);
            if (is_resource($data)) {
                $data = stream_get_contents($data);
            }
            if (!is_string($data) || $data === '') {
                break;
            }
            $part = (string) substr($data, $in, $len);
            if ($part === '') {
                break;
            }
            $out .= $part;
            $offset += strlen($part);
            $len -= strlen($part);
        }
        return $out;
    }

    /** Đọc khối nén tại $offset: trả về [văn bản, vị trí khối tiếp theo]. */
    private static function frameAt(string $token, int $offset, int $size): array
    {
        $hdr = self::readBytes($token, $offset, 4);
        if (strlen($hdr) < 4) {
            throw new \RuntimeException('Tệp sao lưu bị thiếu dữ liệu (tải lên chưa trọn vẹn?).');
        }
        $len = (int) unpack('N', $hdr)[1];
        if ($len <= 0 || $offset + 4 + $len > $size || $len > 64 * 1024 * 1024) {
            throw new \RuntimeException('Tệp sao lưu bị hỏng tại vị trí ' . $offset . '.');
        }
        $z = self::readBytes($token, $offset + 4, $len);
        $text = strlen($z) === $len ? @gzinflate($z) : false;
        if ($text === false) {
            throw new \RuntimeException('Không giải nén được dữ liệu sao lưu (tệp hỏng).');
        }
        return [$text, $offset + 4 + $len];
    }

    /** Kiểm tra tệp đã tải lên và đọc thông tin bản sao lưu. */
    public static function inspect(string $token, int $size): array
    {
        if (self::readBytes($token, 0, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new \RuntimeException('Đây không phải tệp sao lưu của hệ thống (.tnbak).');
        }
        [$text] = self::frameAt($token, strlen(self::MAGIC), $size);
        $first = json_decode(strtok($text, "\n"), true);
        if (!is_array($first) || !isset($first['meta']['format'])) {
            throw new \RuntimeException('Không đọc được thông tin bản sao lưu.');
        }
        $m = $first['meta'];
        if ((int) ($m['schema'] ?? 0) > Schema::VERSION) {
            throw new \RuntimeException('Bản sao lưu được tạo từ phiên bản mới hơn (v' . ($m['version'] ?? '?') . '). Hãy cập nhật mã nguồn trước khi phục hồi.');
        }
        return $m;
    }

    /** Xóa dữ liệu hiện có (trừ phiên đăng nhập & vùng đệm phục hồi). */
    public static function clearAll(): void
    {
        $db = App::db();
        $db->transaction(function () use ($db) {
            foreach (array_keys(Schema::tables()) as $t) {
                if (in_array($t, ['web_sessions', 'restore_chunks'], true)) {
                    continue;
                }
                $db->run('DELETE FROM {' . $t . '}');
            }
        });
    }

    /**
     * Chạy một bước phục hồi (tối đa $budget giây). $job được cập nhật tiến độ.
     * $job: token, size, offset, rows, frames, table, cols, bin, keep, maint, cleared, done
     */
    public static function step(array &$job, float $budget = 8.0): void
    {
        $db = App::db();
        $t0 = microtime(true);
        $schema = Schema::tables();
        if (empty($job['cleared'])) {
            self::clearAll();
            Settings::reset();
            Settings::set('maintenance', 1);
            $job['cleared'] = true;
            $job['offset'] = strlen(self::MAGIC);
        }
        while (!$job['done'] && microtime(true) - $t0 < $budget) {
            if ($job['offset'] >= $job['size']) {
                $job['done'] = true;
                break;
            }
            [$text, $next] = self::frameAt($job['token'], (int) $job['offset'], (int) $job['size']);
            $db->begin();
            try {
                foreach (explode("\n", $text) as $ln) {
                    if ($ln === '') {
                        continue;
                    }
                    $o = json_decode($ln, true);
                    if (!is_array($o)) {
                        throw new \RuntimeException('Dòng dữ liệu hỏng trong bản sao lưu.');
                    }
                    if (isset($o['r'])) {
                        if ($job['table'] === null) {
                            continue;
                        }
                        $row = [];
                        foreach ($job['cols'] as $i => $c) {
                            if (!isset($job['keep'][$c])) {
                                continue;
                            }
                            $v = $o['r'][$i] ?? null;
                            if ($v !== null && in_array($c, $job['bin'], true)) {
                                $v = new Blob((string) base64_decode((string) $v));
                            }
                            $row[$c] = $v;
                        }
                        if ($job['table'] === 'settings') {
                            if (($row['name'] ?? '') === 'maintenance') {
                                $job['maint'] = json_decode((string) $row['value'], true);
                                $row['value'] = '1';
                            }
                            $db->upsert('settings', $row, ['name']);
                        } else {
                            $db->insert($job['table'], $row);
                        }
                        $job['rows']++;
                    } elseif (isset($o['table'])) {
                        $t = (string) $o['table'];
                        if (!isset($schema[$t]) || in_array($t, ['web_sessions', 'restore_chunks'], true)) {
                            $job['table'] = null; // bảng không còn dùng ở phiên bản này
                            continue;
                        }
                        $job['table'] = $t;
                        $job['cols'] = (array) $o['cols'];
                        $job['bin'] = (array) ($o['bin'] ?? []);
                        $job['keep'] = array_flip(array_values(array_filter(array_keys($schema[$t]), static fn($c) => $c[0] !== '@')));
                        $job['tables'][] = $t;
                    } elseif (isset($o['end'])) {
                        $job['done'] = true;
                    }
                }
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }
            $job['offset'] = $next;
            $job['frames']++;
        }
        if ($job['done']) {
            self::finish($job);
        }
    }

    /** Hoàn tất: trả lại chế độ bảo trì như trong bản sao lưu, dọn vùng đệm, đăng xuất mọi phiên. */
    private static function finish(array $job): void
    {
        $db = App::db();
        Settings::reset();
        Settings::set('maintenance', (int) ($job['maint'] ?? 0) === 1 ? 1 : 0);
        Settings::set('last_restore_at', time());
        $db->run('DELETE FROM {restore_chunks}');
        $db->run('DELETE FROM {login_attempts}');
        $db->run('DELETE FROM {web_sessions}');
        try {
            $db->insert('audit_logs', [
                'user_id' => null, 'action' => 'backup.restore', 'target_type' => 'system', 'target_id' => null,
                'details' => json_enc(['rows' => $job['rows'], 'frames' => $job['frames'], 'from' => $job['meta']['version'] ?? '?', 'created' => $job['meta']['created_at'] ?? null]),
                'ip' => client_ip(), 'user_agent' => mb_substr(user_agent(), 0, 255), 'created_at' => time(),
            ]);
        } catch (\Throwable $e) {
            // bỏ qua
        }
    }
}

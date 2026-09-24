<?php

namespace App\Core;

/**
 * Kho lưu tệp NGAY TRONG CSDL (đề thi PDF, lời giải, logo, favicon...).
 *
 * Tệp được chia thành các khúc 512 KB (bảng file_chunks) để:
 *  - không tạo file trên hosting (không tăng inode);
 *  - tương thích MySQL có max_allowed_packet nhỏ;
 *  - đọc/ghi theo luồng, không tốn RAM với tệp lớn.
 */
final class FileStore
{
    public const CHUNK = 524288;

    /** Lưu nội dung chuỗi thành tệp mới. */
    public static function putString(string $data, string $name, string $mime, string $purpose, ?int $ownerId = null, array $meta = []): int
    {
        $db = App::db();
        return $db->transaction(function (Database $db) use ($data, $name, $mime, $purpose, $ownerId, $meta) {
            $now = time();
            $size = strlen($data);
            $count = (int) ceil($size / self::CHUNK);
            $id = $db->insert('files', [
                'purpose' => $purpose,
                'name' => mb_substr($name, 0, 255),
                'mime' => $mime,
                'size' => $size,
                'sha256' => hash('sha256', $data),
                'chunk_size' => self::CHUNK,
                'chunk_count' => $count,
                'status' => 'ready',
                'meta' => $meta ? json_enc($meta) : null,
                'owner_id' => $ownerId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            for ($i = 0; $i < $count; $i++) {
                $db->insert('file_chunks', [
                    'file_id' => $id,
                    'seq' => $i,
                    'data' => new Blob(substr($data, $i * self::CHUNK, self::CHUNK)),
                ]);
            }
            return $id;
        });
    }

    /** Lưu tệp tải lên (đọc từ tệp tạm theo từng khúc). */
    public static function putUploaded(string $tmpPath, string $name, string $mime, string $purpose, ?int $ownerId = null, array $meta = []): int
    {
        $fh = fopen($tmpPath, 'rb');
        if (!$fh) {
            throw new \RuntimeException('Không đọc được tệp tải lên.');
        }
        $db = App::db();
        try {
            return $db->transaction(function (Database $db) use ($fh, $name, $mime, $purpose, $ownerId, $meta) {
                $now = time();
                $id = $db->insert('files', [
                    'purpose' => $purpose,
                    'name' => mb_substr($name, 0, 255),
                    'mime' => $mime,
                    'size' => 0,
                    'sha256' => null,
                    'chunk_size' => self::CHUNK,
                    'chunk_count' => 0,
                    'status' => 'ready',
                    'meta' => $meta ? json_enc($meta) : null,
                    'owner_id' => $ownerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $ctx = hash_init('sha256');
                $seq = 0;
                $size = 0;
                while (!feof($fh)) {
                    $buf = self::readExactly($fh, self::CHUNK);
                    if ($buf === '') {
                        break;
                    }
                    hash_update($ctx, $buf);
                    $size += strlen($buf);
                    $db->insert('file_chunks', ['file_id' => $id, 'seq' => $seq++, 'data' => new Blob($buf)]);
                }
                $db->update('files', ['size' => $size, 'chunk_count' => $seq, 'sha256' => hash_final($ctx)], 'id = ?', [$id]);
                return $id;
            });
        } finally {
            fclose($fh);
        }
    }

    private static function readExactly($fh, int $len): string
    {
        $buf = '';
        while (strlen($buf) < $len && !feof($fh)) {
            $part = fread($fh, $len - strlen($buf));
            if ($part === false || $part === '') {
                break;
            }
            $buf .= $part;
        }
        return $buf;
    }

    // ---------------- Tải lên theo khúc (vượt giới hạn upload_max_filesize) ----------------

    public static function beginUpload(string $name, string $mime, int $size, string $purpose, ?int $ownerId): int
    {
        $now = time();
        return App::db()->insert('files', [
            'purpose' => $purpose,
            'name' => mb_substr($name, 0, 255),
            'mime' => $mime,
            'size' => $size,
            'sha256' => null,
            'chunk_size' => self::CHUNK,
            'chunk_count' => (int) ceil($size / self::CHUNK),
            'status' => 'uploading',
            'meta' => null,
            'owner_id' => $ownerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function putChunk(int $fileId, int $seq, string $data): void
    {
        $db = App::db();
        $db->transaction(function (Database $db) use ($fileId, $seq, $data) {
            $db->run('DELETE FROM {file_chunks} WHERE file_id = ? AND seq = ?', [$fileId, $seq]);
            $db->insert('file_chunks', ['file_id' => $fileId, 'seq' => $seq, 'data' => new Blob($data)]);
            $db->update('files', ['updated_at' => time()], 'id = ?', [$fileId]);
        });
    }

    /** Kiểm tra đủ khúc, tính SHA-256, chuyển trạng thái "ready". */
    public static function finishUpload(int $fileId): array
    {
        $db = App::db();
        $f = self::info($fileId);
        if (!$f) {
            throw new \RuntimeException('Không tìm thấy tệp.');
        }
        $count = (int) $f['chunk_count'];
        $have = (int) $db->value('SELECT COUNT(*) FROM {file_chunks} WHERE file_id = ?', [$fileId]);
        if ($have !== $count) {
            throw new \RuntimeException('Tệp tải lên chưa đầy đủ (' . $have . '/' . $count . ' phần).');
        }
        $ctx = hash_init('sha256');
        $size = 0;
        for ($i = 0; $i < $count; $i++) {
            $chunk = (string) $db->value('SELECT data FROM {file_chunks} WHERE file_id = ? AND seq = ?', [$fileId, $i]);
            $size += strlen($chunk);
            hash_update($ctx, $chunk);
        }
        if ($size !== (int) $f['size']) {
            throw new \RuntimeException('Kích thước tệp không khớp, vui lòng tải lại.');
        }
        $db->update('files', ['status' => 'ready', 'sha256' => hash_final($ctx), 'updated_at' => time()], 'id = ?', [$fileId]);
        return self::info($fileId);
    }

    // ---------------- Đọc ----------------

    public static function info(int $id): ?array
    {
        return $id > 0 ? App::db()->one('SELECT * FROM {files} WHERE id = ?', [$id]) : null;
    }

    public static function firstBytes(int $id, int $len = 8): string
    {
        $chunk = App::db()->value('SELECT data FROM {file_chunks} WHERE file_id = ? AND seq = 0', [$id]);
        return substr((string) $chunk, 0, $len);
    }

    public static function contents(int $id): string
    {
        $f = self::info($id);
        if (!$f) {
            return '';
        }
        $out = '';
        for ($i = 0; $i < (int) $f['chunk_count']; $i++) {
            $out .= (string) App::db()->value('SELECT data FROM {file_chunks} WHERE file_id = ? AND seq = ?', [$id, $i]);
        }
        return $out;
    }

    /**
     * Gửi nội dung tệp ra trình duyệt theo từng khúc.
     * $xorKey: nếu có, nội dung được làm rối bằng XOR (chống tải trực tiếp file đề).
     */
    public static function stream(int $id, ?string $xorKey = null): void
    {
        $f = self::info($id);
        if (!$f) {
            return;
        }
        $offset = 0;
        $klen = $xorKey !== null ? strlen($xorKey) : 0;
        for ($i = 0; $i < (int) $f['chunk_count']; $i++) {
            $chunk = (string) App::db()->value('SELECT data FROM {file_chunks} WHERE file_id = ? AND seq = ?', [$id, $i]);
            if ($klen > 0) {
                $n = strlen($chunk);
                $start = $offset % $klen;
                $stream = substr(str_repeat($xorKey, (int) ceil(($n + $start) / $klen) + 1), $start, $n);
                $chunk = $chunk ^ $stream;
            }
            $offset += strlen($chunk);
            echo $chunk;
            if (ob_get_level() === 0) {
                flush();
            }
        }
    }

    public static function delete(?int $id): void
    {
        if (!$id) {
            return;
        }
        $db = App::db();
        $db->run('DELETE FROM {file_chunks} WHERE file_id = ?', [$id]);
        $db->run('DELETE FROM {files} WHERE id = ?', [$id]);
    }

    /** Xóa các lượt tải lên dở dang quá 1 ngày. */
    public static function purgeStale(): void
    {
        $db = App::db();
        $ids = $db->column("SELECT id FROM {files} WHERE status = 'uploading' AND updated_at < ?", [time() - 86400]);
        foreach ($ids as $id) {
            self::delete((int) $id);
        }
    }

    public static function totalSize(): int
    {
        return (int) App::db()->value("SELECT COALESCE(SUM(size), 0) FROM {files} WHERE status = 'ready'");
    }
}

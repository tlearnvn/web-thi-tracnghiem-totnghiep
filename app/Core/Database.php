<?php

namespace App\Core;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Lớp truy cập CSDL dùng chung cho SQLite (1 file duy nhất) và MySQL/MariaDB.
 *
 * - Tên bảng viết trong câu SQL dạng {users} sẽ được thay bằng tên có tiền tố (MySQL).
 * - Mọi mốc thời gian lưu dạng số nguyên (Unix timestamp) nên không phụ thuộc múi giờ máy chủ CSDL.
 */
final class Database
{
    public PDO $pdo;
    public string $driver;
    private string $prefix;
    private int $txDepth = 0;
    private array $tableMap = [];

    private function __construct(PDO $pdo, string $driver, string $prefix)
    {
        $this->pdo = $pdo;
        $this->driver = $driver;
        $this->prefix = $prefix;
        foreach (array_keys(Schema::tables()) as $t) {
            $this->tableMap['{' . $t . '}'] = $prefix . $t;
        }
    }

    /**
     * @param array $cfg ['driver' => 'sqlite', 'path' => ..., 'wal' => true]
     *                   hoặc ['driver' => 'mysql', 'host','port','database','username','password','prefix']
     */
    public static function connect(array $cfg): self
    {
        $driver = strtolower((string) ($cfg['driver'] ?? 'sqlite'));
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
        if ($driver === 'mysql') {
            if (!extension_loaded('pdo_mysql')) {
                throw new RuntimeException('Máy chủ chưa bật PHP extension pdo_mysql.');
            }
            $host = (string) ($cfg['host'] ?? 'localhost');
            $port = (int) ($cfg['port'] ?? 3306);
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . ($cfg['database'] ?? '') . ';charset=utf8mb4';
            if (!empty($cfg['socket'])) {
                $dsn = 'mysql:unix_socket=' . $cfg['socket'] . ';dbname=' . ($cfg['database'] ?? '') . ';charset=utf8mb4';
            }
            $opts[PDO::ATTR_TIMEOUT] = 10;
            $pdo = new PDO($dsn, (string) ($cfg['username'] ?? ''), (string) ($cfg['password'] ?? ''), $opts);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET time_zone = '+07:00'");
            try {
                $pdo->exec("SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'ONLY_FULL_GROUP_BY', ''), 'NO_ZERO_DATE', '')");
            } catch (\Throwable $e) {
                // bỏ qua nếu máy chủ không cho phép
            }
            return new self($pdo, 'mysql', (string) ($cfg['prefix'] ?? ''));
        }

        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('Máy chủ chưa bật PHP extension pdo_sqlite.');
        }
        $path = self::resolveSqlitePath((string) ($cfg['path'] ?? ''));
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $opts[PDO::ATTR_TIMEOUT] = 20;
        $pdo = new PDO('sqlite:' . $path, null, null, $opts);
        $pdo->exec('PRAGMA busy_timeout = 20000');
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('PRAGMA temp_store = MEMORY');
        if (!array_key_exists('wal', $cfg) || $cfg['wal']) {
            try {
                $pdo->exec('PRAGMA journal_mode = WAL');
                $pdo->exec('PRAGMA synchronous = NORMAL');
            } catch (\Throwable $e) {
                $pdo->exec('PRAGMA journal_mode = DELETE');
            }
        } else {
            $pdo->exec('PRAGMA journal_mode = DELETE');
            $pdo->exec('PRAGMA synchronous = FULL');
        }
        return new self($pdo, 'sqlite', '');
    }

    public static function resolveSqlitePath(string $path): string
    {
        if ($path === '') {
            $path = 'storage/database.sqlite';
        }
        $isAbs = $path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
        return $isAbs ? $path : BASE_PATH . '/' . ltrim($path, '/');
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /** Tên bảng thật (có tiền tố). */
    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    public function sql(string $sql): string
    {
        return strtr($sql, $this->tableMap);
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($this->sql($sql));
        $i = 0;
        foreach ($params as $k => $v) {
            $key = is_int($k) ? ++$i : (strpos((string) $k, ':') === 0 ? $k : ':' . $k);
            if ($v instanceof Blob) {
                $stmt->bindValue($key, $v->data, PDO::PARAM_LOB);
            } elseif (is_int($v)) {
                $stmt->bindValue($key, $v, PDO::PARAM_INT);
            } elseif (is_bool($v)) {
                $stmt->bindValue($key, $v ? 1 : 0, PDO::PARAM_INT);
            } elseif ($v === null) {
                $stmt->bindValue($key, null, PDO::PARAM_NULL);
            } elseif (is_float($v)) {
                $stmt->bindValue($key, (string) $v, PDO::PARAM_STR);
            } else {
                $stmt->bindValue($key, (string) $v, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        return $stmt;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = [])
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function column(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Mảng [cột khóa => cột giá trị] hoặc [cột khóa => cả dòng]. */
    public function keyed(string $sql, array $params = [], string $key = 'id', ?string $valueCol = null): array
    {
        $out = [];
        foreach ($this->all($sql, $params) as $row) {
            $out[$row[$key]] = $valueCol === null ? $row : $row[$valueCol];
        }
        return $out;
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . $this->table($table) . ' (' . implode(', ', $cols) . ') VALUES ('
            . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $this->run($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /** Chèn nhiều dòng trong một giao dịch (nhanh hơn nhiều với SQLite). */
    public function insertMany(string $table, array $rows): void
    {
        if (!$rows) {
            return;
        }
        $cols = array_keys(reset($rows));
        $sql = 'INSERT INTO ' . $this->table($table) . ' (' . implode(', ', $cols) . ') VALUES ('
            . implode(', ', array_fill(0, count($cols), '?')) . ')';
        $this->transaction(function () use ($sql, $rows, $cols) {
            foreach ($rows as $r) {
                $vals = [];
                foreach ($cols as $c) {
                    $vals[] = $r[$c] ?? null;
                }
                $this->run($sql, $vals);
            }
        });
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        if (!$data) {
            return 0;
        }
        $set = [];
        $vals = [];
        foreach ($data as $col => $val) {
            $set[] = $col . ' = ?';
            $vals[] = $val;
        }
        $sql = 'UPDATE ' . $this->table($table) . ' SET ' . implode(', ', $set) . ' WHERE ' . $where;
        return $this->run($sql, array_merge($vals, array_values($params)))->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->run('DELETE FROM ' . $this->table($table) . ' WHERE ' . $where, array_values($params))->rowCount();
    }

    /** Thêm hoặc cập nhật theo khóa (không dùng cú pháp riêng của từng CSDL). */
    public function upsert(string $table, array $data, array $keys): void
    {
        $where = [];
        $params = [];
        foreach ($keys as $k) {
            $where[] = $k . ' = ?';
            $params[] = $data[$k];
        }
        $w = implode(' AND ', $where);
        $exists = $this->value('SELECT 1 FROM ' . $this->table($table) . ' WHERE ' . $w, $params);
        if ($exists) {
            $upd = $data;
            foreach ($keys as $k) {
                unset($upd[$k]);
            }
            if ($upd) {
                $this->update($table, $upd, $w, $params);
            }
            return;
        }
        try {
            $this->insert($table, $data);
        } catch (\PDOException $e) {
            // Hai yêu cầu cùng lúc: bản ghi vừa được tạo -> cập nhật
            $upd = $data;
            foreach ($keys as $k) {
                unset($upd[$k]);
            }
            if ($upd) {
                $this->update($table, $upd, $w, $params);
            }
        }
    }

    /** Mệnh đề IN (?, ?, ?) an toàn. */
    public function in(array $values): string
    {
        return $values ? '(' . implode(', ', array_fill(0, count($values), '?')) . ')' : '(NULL)';
    }

    public function begin(): void
    {
        if ($this->txDepth === 0) {
            if ($this->driver === 'sqlite') {
                // BEGIN IMMEDIATE: giữ khóa ghi ngay từ đầu để tránh lỗi "database is locked"
                $this->pdo->exec('BEGIN IMMEDIATE');
            } else {
                $this->pdo->beginTransaction();
            }
        }
        $this->txDepth++;
    }

    public function commit(): void
    {
        if ($this->txDepth <= 0) {
            return;
        }
        $this->txDepth--;
        if ($this->txDepth === 0) {
            if ($this->driver === 'sqlite') {
                $this->pdo->exec('COMMIT');
            } else {
                $this->pdo->commit();
            }
        }
    }

    public function rollBack(): void
    {
        if ($this->txDepth <= 0) {
            return;
        }
        $this->txDepth = 0;
        try {
            if ($this->driver === 'sqlite') {
                $this->pdo->exec('ROLLBACK');
            } else {
                $this->pdo->rollBack();
            }
        } catch (\Throwable $e) {
            // giao dịch đã kết thúc
        }
    }

    public function transaction(callable $fn)
    {
        $this->begin();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function tableExists(string $name): bool
    {
        $t = $this->table($name);
        if ($this->driver === 'sqlite') {
            return (bool) $this->value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?", [$t]);
        }
        return (bool) $this->value('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t]);
    }

    public function columnExists(string $table, string $column): bool
    {
        $t = $this->table($table);
        if ($this->driver === 'sqlite') {
            foreach ($this->all('PRAGMA table_info(' . $t . ')') as $c) {
                if (strcasecmp($c['name'], $column) === 0) {
                    return true;
                }
            }
            return false;
        }
        return (bool) $this->value('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$t, $column]);
    }

    /** Thông tin phiên bản & dung lượng CSDL (trang Thông tin hệ thống). */
    public function info(): array
    {
        $info = ['driver' => $this->driver, 'version' => '', 'size' => null];
        try {
            if ($this->driver === 'sqlite') {
                $info['version'] = 'SQLite ' . $this->value('SELECT sqlite_version()');
                $info['journal'] = strtoupper((string) $this->value('PRAGMA journal_mode'));
                $pages = (int) $this->value('PRAGMA page_count');
                $size = (int) $this->value('PRAGMA page_size');
                $info['size'] = $pages * $size;
            } else {
                $info['version'] = 'MySQL ' . $this->value('SELECT VERSION()');
                $info['size'] = (int) $this->value(
                    'SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?',
                    [str_replace('_', '\\_', $this->prefix) . '%']
                );
                $info['max_packet'] = (int) $this->value('SELECT @@max_allowed_packet');
            }
        } catch (\Throwable $e) {
            // bỏ qua
        }
        return $info;
    }
}

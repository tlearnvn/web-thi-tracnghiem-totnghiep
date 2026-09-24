<?php

namespace App\Core;

/**
 * Định nghĩa cấu trúc bảng một lần, sinh DDL cho cả SQLite và MySQL.
 *
 * Cú pháp cột: "kiểu[:độ dài]|cờ|cờ..."
 *   kiểu: id (khóa tự tăng), int, ts (timestamp 64-bit), bool, float, str, text, mtext, blob
 *   cờ:   null (cho phép NULL), d:<giá trị> (mặc định), pk (khóa chính dạng chuỗi)
 * Khóa đặc biệt: '@index' => [[cột...], ...], '@unique' => [[cột...], ...]
 */
final class Schema
{
    /** Tăng số này và thêm hàm vào migrations() khi thay đổi cấu trúc. */
    public const VERSION = 3;

    public static function tables(): array
    {
        $ts = ['created_at' => 'ts|d:0', 'updated_at' => 'ts|d:0'];
        return [
            'settings' => [
                'name' => 'str:100|pk',
                'value' => 'mtext|null',
                'updated_at' => 'ts|d:0',
            ],
            'web_sessions' => [
                'id' => 'str:128|pk',
                'user_id' => 'int|null',
                'data' => 'mtext|null',
                'ip' => 'str:45|null',
                'user_agent' => 'str:255|null',
                'created_at' => 'ts|d:0',
                'last_activity' => 'ts|d:0',
                '@index' => [['user_id'], ['last_activity']],
            ],
            'roles' => [
                'id' => 'id',
                'code' => 'str:30',
                'name' => 'str:100',
                'kind' => 'str:20|d:staff',
                'description' => 'str:255|null',
                'permissions' => 'text|null',
                'is_system' => 'bool|d:0',
                'sort_order' => 'int|d:0',
            ] + $ts + ['@unique' => [['code']]],
            'users' => [
                'id' => 'id',
                'username' => 'str:64',
                'password_hash' => 'str:255',
                'role' => 'str:30|d:student',
                'full_name' => 'str:150',
                'code' => 'str:50|null',
                'class_id' => 'int|null',
                'gender' => 'str:10|null',
                'birthday' => 'str:10|null',
                'email' => 'str:150|null',
                'phone' => 'str:30|null',
                'subject_id' => 'int|null',
                'status' => 'str:20|d:active',
                'must_change_password' => 'bool|d:0',
                'note' => 'str:255|null',
                'search_text' => 'str:255|null',
                'sort_key' => 'str:191|null',
                'last_login_at' => 'ts|null',
                'last_login_ip' => 'str:45|null',
                'created_by' => 'int|null',
            ] + $ts + [
                '@unique' => [['username']],
                '@index' => [['role'], ['class_id'], ['code'], ['sort_key']],
            ],
            'classes' => [
                'id' => 'id',
                'name' => 'str:50',
                'grade' => 'int|null',
                'school_year' => 'str:20|null',
                'homeroom_teacher_id' => 'int|null',
                'description' => 'str:255|null',
                'status' => 'str:20|d:active',
                'sort_key' => 'str:100|null',
            ] + $ts + ['@index' => [['name'], ['school_year']]],
            'class_teachers' => [
                'id' => 'id',
                'class_id' => 'int',
                'user_id' => 'int',
                'subject_id' => 'int|null',
                'created_at' => 'ts|d:0',
                '@index' => [['class_id'], ['user_id']],
            ],
            'subjects' => [
                'id' => 'id',
                'code' => 'str:30',
                'name' => 'str:100',
                'short_name' => 'str:30|null',
                'duration' => 'int|d:50',
                'structure' => 'text|null',
                'scoring' => 'text|null',
                'color' => 'str:20|null',
                'sort_order' => 'int|d:0',
                'is_active' => 'bool|d:1',
            ] + $ts + ['@unique' => [['code']]],
            'files' => [
                'id' => 'id',
                'purpose' => 'str:30',
                'name' => 'str:255',
                'mime' => 'str:100',
                'size' => 'ts|d:0',
                'sha256' => 'str:64|null',
                'chunk_size' => 'int|d:0',
                'chunk_count' => 'int|d:0',
                'status' => 'str:20|d:ready',
                'meta' => 'text|null',
                'owner_id' => 'int|null',
            ] + $ts + ['@index' => [['purpose'], ['status']]],
            'file_chunks' => [
                'id' => 'id',
                'file_id' => 'int',
                'seq' => 'int',
                'data' => 'blob|null',
                '@unique' => [['file_id', 'seq']],
            ],
            'exams' => [
                'id' => 'id',
                'title' => 'str:255',
                'subject_id' => 'int|null',
                'grade' => 'int|null',
                'description' => 'text|null',
                'duration' => 'int|d:50',
                'structure' => 'text|null',
                'scoring' => 'text|null',
                'is_shared' => 'bool|d:0',
                'status' => 'str:20|d:draft',
                'created_by' => 'int|null',
            ] + $ts + ['@index' => [['subject_id'], ['created_by']]],
            'exam_variants' => [
                'id' => 'id',
                'exam_id' => 'int',
                'code' => 'str:20',
                'pdf_file_id' => 'int|null',
                'solution_file_id' => 'int|null',
                'note' => 'str:255|null',
                'sort_order' => 'int|d:0',
            ] + $ts + ['@unique' => [['exam_id', 'code']]],
            'exam_keys' => [
                'id' => 'id',
                'variant_id' => 'int',
                'part' => 'int',
                'num' => 'int',
                'answer' => 'str:100|null',
                'points' => 'float|null',
                'level' => 'str:30|null',
                'topic' => 'str:150|null',
                'origin' => 'str:20|null',
                'explanation' => 'mtext|null',
                'is_void' => 'bool|d:0',
                'updated_at' => 'ts|d:0',
                '@unique' => [['variant_id', 'part', 'num']],
            ],
            'exam_sessions' => [
                'id' => 'id',
                'exam_id' => 'int',
                'name' => 'str:255',
                'mode' => 'str:20|d:exam',
                'start_at' => 'ts|null',
                'end_at' => 'ts|null',
                'duration' => 'int|null',
                'access_code' => 'str:50|null',
                'variant_mode' => 'str:20|d:random',
                'fixed_variant_id' => 'int|null',
                'max_attempts' => 'int|d:1',
                'late_join' => 'int|d:0',
                'opts' => 'text|null',
                'status' => 'str:20|d:active',
                'paused_at' => 'ts|null',
                'released' => 'bool|d:0',
                'room' => 'str:100|null',
                'created_by' => 'int|null',
            ] + $ts + ['@index' => [['exam_id'], ['start_at'], ['created_by']]],
            'session_targets' => [
                'id' => 'id',
                'session_id' => 'int',
                'class_id' => 'int|null',
                'user_id' => 'int|null',
                '@index' => [['session_id'], ['class_id'], ['user_id']],
            ],
            'session_staff' => [
                'id' => 'id',
                'session_id' => 'int',
                'user_id' => 'int',
                'role' => 'str:20|d:proctor',
                '@index' => [['session_id'], ['user_id']],
            ],
            'attempts' => [
                'id' => 'id',
                'session_id' => 'int',
                'exam_id' => 'int',
                'variant_id' => 'int',
                'user_id' => 'int',
                'attempt_no' => 'int|d:1',
                'status' => 'str:20|d:in_progress',
                'started_at' => 'ts|d:0',
                'duration_sec' => 'int|d:0',
                'extra_sec' => 'int|d:0',
                'paused_at' => 'ts|null',
                'paused_total' => 'int|d:0',
                'hold_sec' => 'int|d:0',
                'deadline_at' => 'ts|d:0',
                'submitted_at' => 'ts|null',
                'submit_reason' => 'str:30|null',
                'last_seen_at' => 'ts|null',
                'last_saved_at' => 'ts|null',
                'answers' => 'mtext|null',
                'flags' => 'text|null',
                'seq' => 'int|d:0',
                'answered' => 'int|d:0',
                'device_token' => 'str:64|null',
                'device_prev' => 'str:64|null',
                'device_info' => 'str:255|null',
                'ip' => 'str:45|null',
                'violations' => 'int|d:0',
                'locked' => 'bool|d:0',
                'score' => 'float|null',
                'score_detail' => 'text|null',
                'essay_scores' => 'text|null',
                'grading_status' => 'str:20|d:auto',
                'graded_by' => 'int|null',
                'graded_at' => 'ts|null',
                'note' => 'str:255|null',
            ] + $ts + [
                '@unique' => [['session_id', 'user_id', 'attempt_no']],
                '@index' => [['user_id'], ['status', 'deadline_at'], ['variant_id'], ['exam_id']],
            ],
            'attempt_events' => [
                'id' => 'id',
                'attempt_id' => 'int',
                'type' => 'str:30',
                'data' => 'text|null',
                'ip' => 'str:45|null',
                'created_at' => 'ts|d:0',
                '@index' => [['attempt_id']],
            ],
            'session_messages' => [
                'id' => 'id',
                'session_id' => 'int',
                'user_id' => 'int|null',
                'message' => 'text|null',
                'level' => 'str:20|d:info',
                'created_by' => 'int|null',
                'created_at' => 'ts|d:0',
                '@index' => [['session_id']],
            ],
            'announcements' => [
                'id' => 'id',
                'title' => 'str:255',
                'body' => 'text|null',
                'audience' => 'str:20|d:all',
                'class_id' => 'int|null',
                'is_pinned' => 'bool|d:0',
                'starts_at' => 'ts|null',
                'ends_at' => 'ts|null',
                'created_by' => 'int|null',
            ] + $ts,
            'audit_logs' => [
                'id' => 'id',
                'user_id' => 'int|null',
                'action' => 'str:60',
                'target_type' => 'str:40|null',
                'target_id' => 'int|null',
                'details' => 'text|null',
                'ip' => 'str:45|null',
                'user_agent' => 'str:255|null',
                'created_at' => 'ts|d:0',
                '@index' => [['created_at'], ['user_id'], ['action']],
            ],
            'error_logs' => [
                'id' => 'id',
                'ref' => 'str:20|null',
                'level' => 'str:20',
                'message' => 'text|null',
                'file' => 'str:255|null',
                'line' => 'int|null',
                'url' => 'str:255|null',
                'user_id' => 'int|null',
                'trace' => 'mtext|null',
                'created_at' => 'ts|d:0',
                '@index' => [['created_at']],
            ],
            // Vùng đệm khi tải tệp sao lưu lên để phục hồi (không nằm trong bản sao lưu)
            'restore_chunks' => [
                'id' => 'id',
                'token' => 'str:64',
                'seq' => 'int',
                'data' => 'blob|null',
                'created_at' => 'ts|d:0',
                '@unique' => [['token', 'seq']],
            ],
            'login_attempts' => [
                'id' => 'id',
                'username' => 'str:64|null',
                'ip' => 'str:45|null',
                'success' => 'bool|d:0',
                'created_at' => 'ts|d:0',
                '@index' => [['username', 'created_at'], ['ip', 'created_at']],
            ],
        ];
    }

    /** Các bước nâng cấp: [phiên bản đích => function(Database $db)] */
    private static function migrations(): array
    {
        return [
            // Mã thiết bị bị thu hồi khi giám thị "mở khóa thiết bị" (máy cũ không được giành lại bài)
            2 => static function (Database $db): void { self::addColumn($db, 'attempts', 'device_prev', 'str:64|null'); },
            // 3: bảng restore_chunks (được tạo tự động ở bước "tạo các bảng còn thiếu")
        ];
    }

    // ------------------------------------------------------------------

    public static function install(Database $db): void
    {
        foreach (self::tables() as $name => $def) {
            if (!$db->tableExists($name)) {
                foreach (self::createStatements($db, $name, $def) as $sql) {
                    $db->pdo->exec($sql);
                }
            }
        }
        Settings::set('schema_version', self::VERSION);
    }

    /** Gọi mỗi request (rẻ): tự nâng cấp CSDL khi phiên bản cấu trúc thay đổi. */
    public static function ensureUpToDate(Database $db): void
    {
        $current = (int) Settings::get('schema_version', 0);
        if ($current >= self::VERSION) {
            return;
        }
        // Tạo các bảng còn thiếu (an toàn khi chạy lại)
        foreach (self::tables() as $name => $def) {
            if (!$db->tableExists($name)) {
                foreach (self::createStatements($db, $name, $def) as $sql) {
                    $db->pdo->exec($sql);
                }
            }
        }
        foreach (self::migrations() as $version => $fn) {
            if ($version > $current && $version <= self::VERSION) {
                $fn($db);
            }
        }
        Settings::set('schema_version', self::VERSION);
    }

    public static function addColumn(Database $db, string $table, string $column, string $spec): void
    {
        if ($db->columnExists($table, $column)) {
            return;
        }
        $db->pdo->exec('ALTER TABLE ' . $db->table($table) . ' ADD COLUMN ' . self::columnSql($db, $column, $spec));
    }

    public static function createStatements(Database $db, string $name, array $def): array
    {
        $table = $db->table($name);
        $cols = [];
        $pk = null;
        foreach ($def as $col => $spec) {
            if ($col[0] === '@') {
                continue;
            }
            if (strpos($spec, '|pk') !== false) {
                $pk = $col;
            }
            $cols[] = '  ' . self::columnSql($db, $col, $spec);
        }
        if ($pk !== null) {
            $cols[] = '  PRIMARY KEY (' . $pk . ')';
        }
        $sql = 'CREATE TABLE ' . $table . " (\n" . implode(",\n", $cols) . "\n)";
        if (!$db->isSqlite()) {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        $out = [$sql];
        foreach (['@unique' => 'UNIQUE INDEX', '@index' => 'INDEX'] as $key => $kw) {
            foreach ($def[$key] ?? [] as $idxCols) {
                $idx = self::indexName($db, $name, $idxCols, $key === '@unique');
                $out[] = 'CREATE ' . $kw . ' ' . $idx . ' ON ' . $table . ' (' . implode(', ', $idxCols) . ')';
            }
        }
        return $out;
    }

    private static function indexName(Database $db, string $table, array $cols, bool $unique): string
    {
        $name = $db->prefix() . $table . '_' . implode('_', $cols) . ($unique ? '_uq' : '_idx');
        if (strlen($name) > 60) {
            $name = substr($name, 0, 48) . '_' . substr(md5($name), 0, 10);
        }
        return $name;
    }

    private static function columnSql(Database $db, string $col, string $spec): string
    {
        $parts = explode('|', $spec);
        $typeDef = array_shift($parts);
        $len = null;
        if (strpos($typeDef, ':') !== false) {
            [$type, $len] = explode(':', $typeDef, 2);
        } else {
            $type = $typeDef;
        }
        $nullable = in_array('null', $parts, true);
        $isPk = in_array('pk', $parts, true);
        $default = null;
        foreach ($parts as $p) {
            if (strncmp($p, 'd:', 2) === 0) {
                $default = substr($p, 2);
            }
        }
        $sqlite = $db->isSqlite();

        if ($type === 'id') {
            return $sqlite ? $col . ' INTEGER PRIMARY KEY AUTOINCREMENT' : $col . ' INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
        }
        switch ($type) {
            case 'int':
                $t = $sqlite ? 'INTEGER' : 'INT';
                break;
            case 'ts':
                $t = $sqlite ? 'INTEGER' : 'BIGINT';
                break;
            case 'bool':
                $t = $sqlite ? 'INTEGER' : 'TINYINT';
                break;
            case 'float':
                $t = $sqlite ? 'REAL' : 'DOUBLE';
                break;
            case 'str':
                $t = 'VARCHAR(' . (int) ($len ?: 255) . ')';
                break;
            case 'text':
                $t = $sqlite ? 'TEXT' : 'MEDIUMTEXT';
                break;
            case 'mtext':
                $t = $sqlite ? 'TEXT' : 'LONGTEXT';
                break;
            case 'blob':
                $t = $sqlite ? 'BLOB' : 'LONGBLOB';
                break;
            default:
                $t = 'TEXT';
        }
        $sql = $col . ' ' . $t;
        if (!$nullable || $isPk) {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }
        // MySQL không cho DEFAULT với TEXT/BLOB
        if ($default !== null && !in_array($type, ['text', 'mtext', 'blob'], true)) {
            if (in_array($type, ['int', 'ts', 'bool', 'float'], true)) {
                $sql .= ' DEFAULT ' . (is_numeric($default) ? $default : '0');
            } else {
                $sql .= " DEFAULT '" . str_replace("'", "''", $default) . "'";
            }
        }
        return $sql;
    }
}

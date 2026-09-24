<?php

namespace App\Core;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Lưu phiên đăng nhập (PHP session) vào CSDL thay vì file:
 *  - không sinh thêm file => không tăng inode trên shared hosting;
 *  - không bị site khác trên cùng máy chủ dọn mất session (lỗi "tự đăng xuất" kinh điển);
 *  - biết được ai đang trực tuyến, có thể buộc đăng xuất.
 */
final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private Database $db;
    private int $lifetime;
    /** @var array<string, array{data:string, last:int}> */
    private array $known = [];

    public function __construct(Database $db, int $lifetime)
    {
        $this->db = $db;
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $row = $this->db->one('SELECT data, last_activity FROM {web_sessions} WHERE id = ?', [$id]);
        if (!$row) {
            return '';
        }
        if ((int) $row['last_activity'] < time() - $this->lifetime) {
            $this->destroy($id);
            return '';
        }
        $this->known[$id] = ['data' => (string) $row['data'], 'last' => (int) $row['last_activity']];
        return (string) $row['data'];
    }

    public function write(string $id, string $data): bool
    {
        $now = time();
        $uid = $this->extractUserId($data);
        if (isset($this->known[$id])) {
            // Không đổi dữ liệu và vừa cập nhật gần đây => bỏ qua để giảm ghi CSDL
            if ($this->known[$id]['data'] === $data && $this->known[$id]['last'] > $now - 60) {
                return true;
            }
            $this->db->run(
                'UPDATE {web_sessions} SET data = ?, user_id = ?, last_activity = ? WHERE id = ?',
                [$data, $uid, $now, $id]
            );
        } else {
            if ($data === '') {
                return true; // phiên rỗng (khách chưa làm gì) – không cần lưu
            }
            try {
                $this->db->insert('web_sessions', [
                    'id' => $id,
                    'user_id' => $uid,
                    'data' => $data,
                    'ip' => client_ip(),
                    'user_agent' => user_agent(),
                    'created_at' => $now,
                    'last_activity' => $now,
                ]);
            } catch (\PDOException $e) {
                $this->db->run(
                    'UPDATE {web_sessions} SET data = ?, user_id = ?, last_activity = ? WHERE id = ?',
                    [$data, $uid, $now, $id]
                );
            }
        }
        $this->known[$id] = ['data' => $data, 'last' => $now];
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->db->run('DELETE FROM {web_sessions} WHERE id = ?', [$id]);
        unset($this->known[$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return self::cleanup($this->db, $this->lifetime);
    }

    public function validateId(string $id): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM {web_sessions} WHERE id = ?', [$id]);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $now = time();
        if (isset($this->known[$id]) && $this->known[$id]['last'] > $now - 60) {
            return true;
        }
        $this->db->run('UPDATE {web_sessions} SET last_activity = ? WHERE id = ?', [$now, $id]);
        $this->known[$id] = ['data' => $data, 'last' => $now];
        return true;
    }

    public static function cleanup(Database $db, int $lifetime): int
    {
        return $db->run('DELETE FROM {web_sessions} WHERE last_activity < ?', [time() - $lifetime])->rowCount();
    }

    private function extractUserId(string $data): ?int
    {
        if ($data !== '' && preg_match('/_uid\|i:(\d+);/', $data, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}

<?php

namespace App\Core;

/**
 * Cấu hình hệ thống dạng khóa – giá trị (lưu JSON trong bảng settings).
 */
final class Settings
{
    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [
            // Nhận diện
            'site_name' => 'Hệ thống thi trắc nghiệm trực tuyến',
            'site_short_name' => 'THI TRẮC NGHIỆM',
            'org_name' => 'Trường THPT',
            'org_parent' => 'Sở Giáo dục và Đào tạo',
            'footer_text' => '© {year} {org}. Hệ thống thi trắc nghiệm theo định dạng đề thi tốt nghiệp THPT từ năm 2025.',
            'logo_file_id' => 0,
            'logo_version' => '1',
            'favicon_file_id' => 0,
            'favicon_version' => '1',
            'primary_color' => '#2563eb',
            'login_title' => 'Chào mừng đến với phòng thi trực tuyến',
            'login_subtitle' => 'Làm bài thi theo định dạng đề thi tốt nghiệp THPT mới: trắc nghiệm nhiều lựa chọn, đúng/sai và trả lời ngắn.',
            'login_notice' => '',
            'default_school_year' => self::guessSchoolYear(),

            // Thi cử
            'exam_device_lock' => 1,
            'exam_auto_reclaim' => 1,
            'exam_require_fullscreen' => 0,
            'exam_max_violations' => 0,
            'exam_violation_action' => 'log',
            'exam_grace_seconds' => 90,
            'exam_autosave_ms' => 1200,
            'exam_heartbeat_seconds' => 20,
            'exam_watermark' => 1,
            'exam_show_score' => 'after_submit',
            'exam_allow_review' => 'after_end',
            'exam_protect_pdf' => 1,

            // Xếp loại
            'grade_excellent' => 8,
            'grade_good' => 6.5,
            'grade_average' => 5,
            'grade_weak' => 3.5,

            // Bảo mật
            'login_max_attempts' => 5,
            'login_lock_minutes' => 5,
            'login_ip_max_attempts' => 60,
            'session_lifetime_hours' => 12,
            'password_min_length' => 6,
            'student_can_change_password' => 1,
            'remember_login' => 0,

            // Khác
            'maintenance' => 0,
            'maintenance_message' => 'Hệ thống đang bảo trì, vui lòng quay lại sau ít phút.',
            'schema_version' => 0,
            'installed_at' => 0,
            'app_secret' => '',
        ];
    }

    public static function guessSchoolYear(): string
    {
        $y = (int) date('Y');
        $m = (int) date('n');
        return $m >= 8 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
    }

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (App::db()->all('SELECT name, value FROM {settings}') as $row) {
                    $v = json_decode((string) $row['value'], true);
                    self::$cache[$row['name']] = ($v === null && $row['value'] !== 'null') ? $row['value'] : $v;
                }
            } catch (\Throwable $e) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::load();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        $d = self::defaults();
        return array_key_exists($key, $d) ? $d[$key] : $default;
    }

    public static function all(): array
    {
        return array_merge(self::defaults(), self::load());
    }

    public static function set(string $key, $value): void
    {
        App::db()->upsert('settings', [
            'name' => $key,
            'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'updated_at' => time(),
        ], ['name']);
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public static function setMany(array $values): void
    {
        App::db()->transaction(function () use ($values) {
            foreach ($values as $k => $v) {
                self::set($k, $v);
            }
        });
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}

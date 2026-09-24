<?php

namespace App\Core;

/**
 * Danh mục quyền và vai trò mặc định.
 * Quản trị viên có thể chỉnh ma trận quyền của từng vai trò (trừ vai trò admin luôn có toàn quyền).
 */
final class Permissions
{
    public const GROUPS = [
        'Tổng quan' => [
            'dashboard.view' => 'Xem trang tổng quan',
        ],
        'Học sinh' => [
            'students.view' => 'Xem học sinh các lớp được phân công',
            'students.view_all' => 'Xem học sinh toàn trường',
            'students.manage' => 'Thêm / sửa / xóa / nhập học sinh từ Excel',
            'students.password' => 'Cấp lại mật khẩu, khóa / mở khóa tài khoản học sinh',
        ],
        'Lớp học' => [
            'classes.view' => 'Xem danh sách lớp',
            'classes.manage' => 'Thêm / sửa / xóa lớp, phân công giáo viên',
        ],
        'Giáo viên & tài khoản' => [
            'teachers.view' => 'Xem danh sách giáo viên',
            'teachers.manage' => 'Thêm / sửa / xóa / nhập giáo viên',
            'users.manage' => 'Quản lý mọi tài khoản (kể cả quản trị)',
            'roles.manage' => 'Chỉnh vai trò và phân quyền',
        ],
        'Đề thi' => [
            'subjects.manage' => 'Quản lý môn thi & định dạng đề',
            'exams.view' => 'Xem đề của mình và đề được chia sẻ',
            'exams.manage' => 'Tạo / sửa đề thi, mã đề, đáp án của mình',
            'exams.manage_all' => 'Quản lý tất cả đề thi',
        ],
        'Ca thi' => [
            'sessions.manage' => 'Tạo / quản lý ca thi của mình',
            'sessions.manage_all' => 'Quản lý tất cả ca thi',
            'sessions.proctor' => 'Giám sát ca thi được phân công (cộng giờ, mở khóa, thu bài)',
        ],
        'Kết quả & thống kê' => [
            'results.view' => 'Xem kết quả ca thi của mình / lớp mình',
            'results.view_all' => 'Xem mọi kết quả',
            'results.grade' => 'Chấm tự luận, chấm lại, cho thi lại',
            'results.export' => 'Xuất Excel kết quả, thống kê',
            'stats.view' => 'Xem thống kê, phân tích câu hỏi',
        ],
        'Hệ thống' => [
            'announcements.manage' => 'Đăng thông báo',
            'settings.manage' => 'Cấu hình hệ thống, giao diện, logo',
            'backup.manage' => 'Sao lưu & phục hồi dữ liệu',
            'logs.view' => 'Xem nhật ký hệ thống',
        ],
        'Cổng học sinh' => [
            'exam.take' => 'Tham gia làm bài thi',
            'practice.use' => 'Luyện tập đề mở',
            'results.own' => 'Xem kết quả của bản thân',
        ],
    ];

    public static function all(): array
    {
        $out = [];
        foreach (self::GROUPS as $perms) {
            $out += $perms;
        }
        return $out;
    }

    public static function defaultRoles(): array
    {
        return [
            'admin' => [
                'name' => 'Quản trị hệ thống',
                'kind' => 'staff',
                'description' => 'Toàn quyền quản trị hệ thống.',
                'permissions' => ['*'],
            ],
            'manager' => [
                'name' => 'Cán bộ quản lý',
                'kind' => 'staff',
                'description' => 'Ban giám hiệu, tổ trưởng: theo dõi toàn trường, xem kết quả, thống kê.',
                'permissions' => [
                    'dashboard.view', 'students.view', 'students.view_all', 'classes.view', 'teachers.view',
                    'exams.view', 'sessions.manage', 'sessions.manage_all', 'sessions.proctor',
                    'results.view', 'results.view_all', 'results.export', 'stats.view', 'announcements.manage', 'logs.view',
                ],
            ],
            'teacher' => [
                'name' => 'Giáo viên',
                'kind' => 'staff',
                'description' => 'Soạn đề, tổ chức ca thi cho lớp mình, chấm và thống kê.',
                'permissions' => [
                    'dashboard.view', 'students.view', 'students.manage', 'students.password', 'classes.view',
                    'exams.view', 'exams.manage', 'sessions.manage', 'sessions.proctor',
                    'results.view', 'results.grade', 'results.export', 'stats.view', 'announcements.manage',
                ],
            ],
            'proctor' => [
                'name' => 'Giám thị',
                'kind' => 'staff',
                'description' => 'Chỉ giám sát các ca thi được phân công.',
                'permissions' => ['dashboard.view', 'sessions.proctor'],
            ],
            'student' => [
                'name' => 'Học sinh',
                'kind' => 'student',
                'description' => 'Làm bài thi, luyện tập và xem kết quả của bản thân.',
                'permissions' => ['exam.take', 'practice.use', 'results.own'],
            ],
        ];
    }
}

<?php

namespace App\Lib;

/**
 * Đọc danh sách học sinh / giáo viên từ Excel (.xlsx, .csv).
 * Nhận dạng tiêu đề cột linh hoạt (có dấu / không dấu, nhiều cách gọi),
 * hỗ trợ tệp xuất từ các phần mềm quản lý trường học (có dòng tiêu đề phụ, tách "Họ đệm" / "Tên").
 */
final class PeopleImporter
{
    private const COMMON = [
        'full_name' => ['hovaten', 'hoten', 'hotenhocsinh', 'hotenhs', 'tenhocsinh', 'hotengiaovien', 'hotengv', 'fullname', 'name', 'hovatenhocsinh', 'hovatengiaovien'],
        'last_name' => ['ho', 'hodem', 'hovatendem', 'hovadem', 'holot', 'hovachulot', 'hovatenlot'],
        'first_name' => ['ten', 'firstname'],
        'birthday' => ['ngaysinh', 'ns', 'dob', 'birthday', 'ngaythangnamsinh', 'sinhngay'],
        'gender' => ['gioitinh', 'gt', 'phai', 'gender', 'namnu'],
        'female' => ['nu'],
        'username' => ['tendangnhap', 'taikhoan', 'username', 'user', 'login', 'tentaikhoan'],
        'password' => ['matkhau', 'password', 'pass', 'mk'],
        'email' => ['email', 'thudientu', 'mail', 'diachiemail'],
        'phone' => ['sodienthoai', 'dienthoai', 'sdt', 'phone', 'mobile', 'didong', 'dt'],
        'note' => ['ghichu', 'note', 'notes'],
    ];

    private const STUDENT = [
        'code' => ['mahs', 'mahocsinh', 'ma', 'sbd', 'sobaodanh', 'mahv', 'mssv', 'maso', 'madinhdanh', 'id', 'mahsinh', 'masohocsinh'],
        'class' => ['lop', 'tenlop', 'class', 'lophoc'],
    ];

    private const TEACHER = [
        'code' => ['magv', 'magiaovien', 'ma', 'macanbo', 'mcb', 'macb', 'id', 'maso'],
        'subject' => ['mon', 'monday', 'bomon', 'monhoc', 'subject', 'mongiangday'],
        'homeroom' => ['lopchunhiem', 'chunhiem', 'gvcn', 'chunhiemlop'],
        'classes' => ['lopday', 'lopgiangday', 'phancong', 'cacloptday', 'cacloday', 'lopphutrach', 'lopphancong'],
        'role' => ['vaitro', 'chucvu', 'role', 'quyen'],
    ];

    public const FIELD_LABELS = [
        'code' => 'Mã', 'full_name' => 'Họ và tên', 'last_name' => 'Họ đệm', 'first_name' => 'Tên', 'birthday' => 'Ngày sinh',
        'gender' => 'Giới tính', 'female' => 'Nữ', 'class' => 'Lớp', 'username' => 'Tên đăng nhập', 'password' => 'Mật khẩu',
        'email' => 'Email', 'phone' => 'Điện thoại', 'note' => 'Ghi chú', 'subject' => 'Môn', 'homeroom' => 'Lớp chủ nhiệm',
        'classes' => 'Lớp giảng dạy', 'role' => 'Vai trò',
    ];

    /**
     * @return array{rows: array, columns: array, sheets: array, errors: array, header_row: int}
     */
    public static function parse(string $data, string $type = 'student', int $sheet = 0): array
    {
        $out = ['rows' => [], 'columns' => [], 'sheets' => [], 'errors' => [], 'header_row' => 0];
        try {
            $book = XlsxReader::readAny($data, $sheet);
        } catch (\Throwable $e) {
            $out['errors'][] = $e->getMessage();
            return $out;
        }
        $out['sheets'] = $book['sheets'];
        $rows = $book['rows'];
        $aliases = self::COMMON + ($type === 'teacher' ? self::TEACHER : self::STUDENT);

        $headerRow = 0;
        $map = [];
        $n = 0;
        foreach ($rows as $rn => $cells) {
            if (++$n > 20) {
                break;
            }
            $m = self::mapHeaders($cells, $aliases);
            if (isset($m['full_name']) || isset($m['first_name'])) {
                $headerRow = $rn;
                $map = $m;
                break;
            }
        }
        if (!$headerRow) {
            $out['errors'][] = 'Không tìm thấy dòng tiêu đề có cột "Họ và tên" (hoặc "Họ đệm" + "Tên"). Hãy dùng tệp mẫu của hệ thống.';
            return $out;
        }
        $out['header_row'] = $headerRow;
        foreach ($map as $field => $ci) {
            $out['columns'][$field] = (string) ($rows[$headerRow][$ci] ?? '');
        }

        foreach ($rows as $rn => $cells) {
            if ($rn <= $headerRow) {
                continue;
            }
            $get = static function (string $f) use ($map, $cells) {
                return isset($map[$f]) ? Text::trimCell($cells[$map[$f]] ?? '') : '';
            };
            $name = $get('full_name');
            if ($name === '' && isset($map['first_name'])) {
                $name = trim($get('last_name') . ' ' . $get('first_name'));
            } elseif (isset($map['last_name'], $map['first_name']) && $get('first_name') !== '' && mb_strpos($name, $get('first_name')) === false) {
                $name = trim($name . ' ' . $get('first_name'));
            }
            $name = Text::normalizeName($name);
            $allEmpty = trim(implode('', array_map('strval', $cells))) === '';
            if ($allEmpty) {
                continue;
            }
            // Bỏ dòng tổng cộng / chữ ký cuối bảng
            if ($name === '' || preg_match('/^(tong|cong|nguoi lap|hieu truong|giao vien chu nhiem)/i', Text::unaccent($name))) {
                if ($name === '') {
                    $out['rows'][] = ['row' => $rn, 'data' => [], 'errors' => ['Thiếu họ tên – bỏ qua dòng này'], 'skip' => true];
                }
                continue;
            }
            $gender = Text::parseGender($get('gender'));
            if ($gender === null && isset($map['female'])) {
                $gender = $get('female') !== '' ? 'Nữ' : 'Nam';
            }
            $birthRaw = isset($map['birthday']) ? ($cells[$map['birthday']] ?? '') : '';
            $birthday = Text::parseDate($birthRaw);
            $d = [
                'full_name' => $name,
                'code' => mb_substr($get('code'), 0, 50),
                'birthday' => $birthday,
                'gender' => $gender,
                'username' => Text::cleanUsername($get('username')),
                'password' => $get('password'),
                'email' => $get('email'),
                'phone' => $get('phone'),
                'note' => mb_substr($get('note'), 0, 255),
            ];
            if ($type === 'teacher') {
                $d['subject'] = $get('subject');
                $d['homeroom'] = $get('homeroom');
                $d['classes'] = $get('classes');
                $d['role'] = $get('role');
            } else {
                $d['class'] = $get('class');
            }
            $errors = [];
            $warn = [];
            if ($birthRaw !== '' && $birthRaw !== null && $birthday === null) {
                $warn[] = 'Ngày sinh "' . $birthRaw . '" không đúng định dạng – bỏ qua';
            }
            if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
                $warn[] = 'Email không hợp lệ – bỏ qua';
                $d['email'] = '';
            }
            $out['rows'][] = ['row' => $rn, 'data' => $d, 'errors' => $errors, 'warnings' => $warn, 'skip' => false];
        }
        if (!$out['rows']) {
            $out['errors'][] = 'Không có dòng dữ liệu nào bên dưới dòng tiêu đề.';
        }
        return $out;
    }

    private static function mapHeaders(array $cells, array $aliases): array
    {
        $map = [];
        foreach ($cells as $ci => $v) {
            $h = Text::normalizeHeader((string) $v);
            if ($h === '' || $h === 'stt' || $h === 'tt') {
                continue;
            }
            foreach ($aliases as $field => $list) {
                if (!isset($map[$field]) && in_array($h, $list, true)) {
                    $map[$field] = $ci;
                    continue 2;
                }
            }
            // So khớp gần đúng
            if (!isset($map['full_name']) && strpos($h, 'hovaten') === 0) {
                $map['full_name'] = $ci;
            } elseif (!isset($map['birthday']) && strpos($h, 'ngaysinh') !== false) {
                $map['birthday'] = $ci;
            } elseif (!isset($map['phone']) && (strpos($h, 'dienthoai') !== false || strpos($h, 'sdt') === 0)) {
                $map['phone'] = $ci;
            } elseif (!isset($map['code']) && (strpos($h, 'mahocsinh') === 0 || strpos($h, 'magiaovien') === 0 || strpos($h, 'madinhdanh') === 0)) {
                $map['code'] = $ci;
            }
        }
        return $map;
    }

    /** Tệp Excel mẫu. */
    public static function templateXlsx(string $type): string
    {
        $x = new XlsxWriter();
        $h = $x->style(['bold' => true, 'fill' => '1D4ED8', 'color' => 'FFFFFF', 'border' => true, 'align' => 'center', 'wrap' => true]);
        $t = $x->style(['border' => true]);
        $txt = $x->style(['border' => true, 'numFmt' => '@']);
        $title = $x->style(['bold' => true, 'size' => 14]);
        $note = $x->style(['italic' => true, 'color' => '64748B']);
        if ($type === 'teacher') {
            $s = $x->sheet('Giáo viên');
            $s->widths([6, 12, 28, 16, 14, 26, 14, 12, 12, 22, 14]);
            $s->row(['DANH SÁCH GIÁO VIÊN'], $title);
            $s->row(['Cột có dấu * là bắt buộc. Bỏ trống Tên đăng nhập / Mật khẩu để hệ thống tự tạo.'], $note);
            $s->row(['STT', 'Mã GV', 'Họ và tên *', 'Tên đăng nhập', 'Mật khẩu', 'Email', 'Điện thoại', 'Môn', 'Lớp chủ nhiệm', 'Lớp giảng dạy', 'Vai trò'], $h, [], 30);
            $s->row([1, 'GV001', 'Nguyễn Thị Minh Hoa', 'hoa.ntm', '', 'hoa@truong.edu.vn', '0912345678', 'Toán', '12A1', '12A1, 12A2, 11A3', 'Giáo viên'], $t, [1 => $txt, 6 => $txt]);
            $s->row([2, 'GV002', 'Trần Văn Bình', '', '', '', '', 'Vật lí', '', '12A1, 12A2', 'Giáo viên'], $t, [1 => $txt, 6 => $txt]);
            $s->row([3, 'GV003', 'Lê Thị Thu', '', '', '', '', '', '', '', 'Giám thị'], $t, [1 => $txt, 6 => $txt]);
            $s->freeze('A4');
        } else {
            $s = $x->sheet('Học sinh');
            $s->widths([6, 14, 28, 13, 10, 10, 16, 14, 24, 14]);
            $s->row(['DANH SÁCH HỌC SINH'], $title);
            $s->row(['Cột có dấu * là bắt buộc. Bỏ trống Tên đăng nhập để dùng Mã HS; bỏ trống Mật khẩu để hệ thống tự tạo. Ngày sinh dạng dd/mm/yyyy.'], $note);
            $s->row(['STT', 'Mã học sinh', 'Họ và tên *', 'Ngày sinh', 'Giới tính', 'Lớp', 'Tên đăng nhập', 'Mật khẩu', 'Email', 'Điện thoại'], $h, [], 30);
            $samples = [['01000001', 'Nguyễn Văn An', '05/09/2008', 'Nam', '12A1'], ['01000002', 'Trần Thị Bích Ngọc', '15/01/2008', 'Nữ', '12A1'], ['01000003', 'Lê Hoàng Minh', '22/11/2008', 'Nam', '12A2']];
            foreach ($samples as $i => $r) {
                $s->row([$i + 1, $r[0], $r[1], $r[2], $r[3], $r[4], '', '', '', ''], $t, [1 => $txt, 3 => $txt, 7 => $txt, 9 => $txt]);
            }
            $s->freeze('A4');
        }
        return $x->build();
    }
}

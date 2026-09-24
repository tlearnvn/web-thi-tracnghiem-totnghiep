<?php

namespace App\Lib;

/**
 * Tiện ích xử lý chuỗi tiếng Việt: bỏ dấu, sắp xếp theo ABC tiếng Việt, sinh tên đăng nhập...
 */
final class Text
{
    /** Nguyên âm theo thứ tự thanh: ngang, huyền, hỏi, ngã, sắc, nặng. */
    private const VOWELS = [
        'a' => ['a', 'à', 'ả', 'ã', 'á', 'ạ'],
        'ă' => ['ă', 'ằ', 'ẳ', 'ẵ', 'ắ', 'ặ'],
        'â' => ['â', 'ầ', 'ẩ', 'ẫ', 'ấ', 'ậ'],
        'e' => ['e', 'è', 'ẻ', 'ẽ', 'é', 'ẹ'],
        'ê' => ['ê', 'ề', 'ể', 'ễ', 'ế', 'ệ'],
        'i' => ['i', 'ì', 'ỉ', 'ĩ', 'í', 'ị'],
        'o' => ['o', 'ò', 'ỏ', 'õ', 'ó', 'ọ'],
        'ô' => ['ô', 'ồ', 'ổ', 'ỗ', 'ố', 'ộ'],
        'ơ' => ['ơ', 'ờ', 'ở', 'ỡ', 'ớ', 'ợ'],
        'u' => ['u', 'ù', 'ủ', 'ũ', 'ú', 'ụ'],
        'ư' => ['ư', 'ừ', 'ử', 'ữ', 'ứ', 'ự'],
        'y' => ['y', 'ỳ', 'ỷ', 'ỹ', 'ý', 'ỵ'],
    ];

    /** Mã sắp xếp chữ cái cơ sở (ă sau a, â sau ă, đ sau d...). */
    private const LETTER_RANK = [
        'a' => 'a0', 'ă' => 'a1', 'â' => 'a2', 'd' => 'd0', 'đ' => 'd1', 'e' => 'e0', 'ê' => 'e1',
        'o' => 'o0', 'ô' => 'o1', 'ơ' => 'o2', 'u' => 'u0', 'ư' => 'u1',
    ];

    private static ?array $toneMap = null;   // ký tự => [cơ sở, thanh]
    private static ?array $plainMap = null;  // ký tự => chữ không dấu

    private static function maps(): void
    {
        if (self::$toneMap !== null) {
            return;
        }
        self::$toneMap = [];
        self::$plainMap = [];
        $plainOf = ['a' => 'a', 'ă' => 'a', 'â' => 'a', 'e' => 'e', 'ê' => 'e', 'i' => 'i', 'o' => 'o', 'ô' => 'o', 'ơ' => 'o', 'u' => 'u', 'ư' => 'u', 'y' => 'y'];
        foreach (self::VOWELS as $base => $list) {
            foreach ($list as $tone => $ch) {
                self::$toneMap[$ch] = [$base, $tone];
                self::$plainMap[$ch] = $plainOf[$base];
                $up = mb_strtoupper($ch);
                self::$plainMap[$up] = strtoupper($plainOf[$base]);
            }
        }
        self::$plainMap['đ'] = 'd';
        self::$plainMap['Đ'] = 'D';
        self::$toneMap['đ'] = ['đ', 0];
    }

    /** Chuẩn hóa Unicode dựng sẵn (NFC) – xử lý chữ gõ kiểu "tổ hợp". */
    public static function nfc(string $s): string
    {
        if ($s !== '' && class_exists('Normalizer') && !\Normalizer::isNormalized($s, \Normalizer::FORM_C)) {
            $n = \Normalizer::normalize($s, \Normalizer::FORM_C);
            if (is_string($n)) {
                return $n;
            }
        }
        return $s;
    }

    public static function unaccent(string $s): string
    {
        self::maps();
        $s = self::nfc($s);
        $s = strtr($s, self::$plainMap);
        // Loại bỏ dấu tổ hợp còn sót (U+0300–U+036F)
        return (string) preg_replace('/[\x{0300}-\x{036F}]/u', '', $s);
    }

    public static function lower(string $s): string
    {
        return mb_strtolower($s, 'UTF-8');
    }

    public static function slug(string $s, string $sep = '-'): string
    {
        $s = strtolower(self::unaccent($s));
        $s = (string) preg_replace('/[^a-z0-9]+/', $sep, $s);
        return trim($s, $sep);
    }

    /** Chuỗi tìm kiếm không dấu, chữ thường. */
    public static function searchText(...$parts): string
    {
        $s = implode(' ', array_filter(array_map('strval', $parts), static fn($x) => $x !== ''));
        $s = strtolower(self::unaccent($s));
        $s = (string) preg_replace('/\s+/', ' ', $s);
        return mb_substr(trim($s), 0, 255);
    }

    public static function normalizeSearch(string $q): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', self::unaccent($q))));
    }

    /** Chuẩn hóa họ tên: bỏ khoảng trắng thừa, viết hoa chữ đầu nếu cả chuỗi đang in hoa/thường. */
    public static function normalizeName(string $name): string
    {
        $name = self::nfc(trim((string) preg_replace('/\s+/u', ' ', $name)));
        if ($name === '') {
            return '';
        }
        if ($name === mb_strtoupper($name) || $name === mb_strtolower($name)) {
            $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8');
        }
        return $name;
    }

    public static function givenName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName));
        return (string) end($parts);
    }

    /** Khóa sắp xếp theo thói quen danh sách lớp Việt Nam: Tên trước, rồi Họ đệm. */
    public static function sortKey(string $fullName): string
    {
        $fullName = trim($fullName);
        $parts = preg_split('/\s+/u', $fullName) ?: [];
        $given = (string) array_pop($parts);
        $rest = implode(' ', $parts);
        $key = self::collate($given) . ' ' . self::collate($rest);
        return mb_substr($key, 0, 191);
    }

    /** Mã so sánh một chuỗi theo bảng chữ cái tiếng Việt (chữ trước, thanh sau). */
    public static function collate(string $s): string
    {
        self::maps();
        $s = self::lower(self::nfc($s));
        $primary = '';
        $tones = '';
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            if (isset(self::$toneMap[$ch])) {
                [$base, $tone] = self::$toneMap[$ch];
                $primary .= self::LETTER_RANK[$base] ?? ($base . '0');
                $tones .= (string) $tone;
            } elseif ($ch === ' ') {
                $primary .= ' ';
            } elseif (preg_match('/[a-z0-9]/', $ch)) {
                $primary .= $ch . '0';
                $tones .= '0';
            } else {
                $primary .= self::unaccent($ch);
            }
        }
        return $primary . '!' . $tones;
    }

    /** "Họ và tên" -> "hovaten" (so khớp tiêu đề cột Excel). */
    public static function normalizeHeader(string $s): string
    {
        $s = strtolower(self::unaccent($s));
        return (string) preg_replace('/[^a-z0-9]+/', '', $s);
    }

    /** Mật khẩu ngẫu nhiên dễ đọc (bỏ các ký tự dễ nhầm 0/O, 1/l/I). */
    public static function randomPassword(int $length = 6, bool $digitsOnly = false): string
    {
        $chars = $digitsOnly ? '23456789' : 'abcdefghjkmnpqrstuvwxyz23456789';
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }

    /** "Nguyễn Văn An" -> "annv" */
    public static function usernameFromName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim(self::unaccent($fullName))) ?: [];
        if (!$parts) {
            return 'user';
        }
        $given = strtolower((string) array_pop($parts));
        $initials = '';
        foreach ($parts as $p) {
            $initials .= strtolower(substr($p, 0, 1));
        }
        $u = (string) preg_replace('/[^a-z0-9]/', '', $given . $initials);
        return $u !== '' ? $u : 'user';
    }

    public static function cleanUsername(string $u): string
    {
        $u = strtolower(self::unaccent(trim($u)));
        return (string) preg_replace('/[^a-z0-9._@-]/', '', $u);
    }

    /**
     * Đọc ngày sinh từ Excel (số serial) hoặc chuỗi dd/mm/yyyy, yyyy-mm-dd, dd-mm-yy...
     * @return string|null "Y-m-d"
     */
    public static function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 1000 && (float) $value < 80000) {
            // Excel serial: 1 = 1900-01-01 (có lỗi năm nhuận 1900)
            $days = (int) floor((float) $value);
            $ts = ($days - 25569) * 86400;
            return gmdate('Y-m-d', $ts);
        }
        $s = trim((string) $value);
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $s, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})$/', $s, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($y < 100) {
                $y += $y > 40 ? 1900 : 2000;
            }
        } elseif (preg_match('/^(\d{4})$/', $s, $m)) {
            return $m[1] . '-01-01';
        } else {
            return null;
        }
        if (!checkdate($mo, $d, $y)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    public static function parseGender($v): ?string
    {
        $s = strtolower(self::unaccent(trim((string) $v)));
        if ($s === '') {
            return null;
        }
        if (in_array($s, ['nam', 'm', 'male', 'trai'], true)) {
            return 'Nam';
        }
        if (in_array($s, ['nu', 'f', 'female', 'gai'], true)) {
            return 'Nữ';
        }
        return null;
    }

    /** Khối lớp từ tên lớp: "12A1" -> 12, "10 Tin" -> 10. */
    public static function gradeFromClassName(string $name): ?int
    {
        if (preg_match('/^\s*(1[0-2]|[6-9])\D?/u', $name, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    public static function trimCell($v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_float($v) && floor($v) === $v && abs($v) < 1e15) {
            return (string) (int) $v;
        }
        return trim((string) preg_replace('/[\x{00A0}\x{200B}\x{FEFF}]/u', ' ', self::nfc((string) $v)));
    }
}

<?php

namespace App\Lib;

/**
 * Chuẩn hóa đáp án (khi nhập từ Excel/JSON/giao diện) và câu trả lời của học sinh.
 */
final class KeyFormat
{
    /** Phần I: "A", "a", "A|B", "AB", "A,B" -> "A" hoặc "AB"; "*" = câu hủy (cho điểm tất cả). */
    public static function p1(?string $v): ?string
    {
        $v = strtoupper(trim(Text::unaccent((string) $v)));
        if ($v === '') {
            return null;
        }
        if ($v === '*') {
            return '*';
        }
        $v = str_replace(['1', '2', '3', '4'], ['A', 'B', 'C', 'D'], $v);
        $letters = array_unique(preg_split('//', (string) preg_replace('/[^ABCD]/', '', $v), -1, PREG_SPLIT_NO_EMPTY));
        if (!$letters) {
            return null;
        }
        sort($letters);
        return implode('', $letters);
    }

    /**
     * Phần II: "ĐSĐĐ", "DSDD", "Đ,S,Đ,Đ", "TFTT", "1011", "đúng sai đúng đúng" -> "DSDD".
     * Dấu "*" ở ý nào = ý đó được tính đúng cho mọi thí sinh.
     */
    public static function p2(?string $v, int $items = 4): ?string
    {
        $raw = trim((string) $v);
        if ($raw === '') {
            return null;
        }
        $s = strtoupper(Text::unaccent($raw));
        $s = str_replace(['DUNG', 'SAI', 'TRUE', 'FALSE'], ['D', 'S', 'D', 'S'], $s);
        $s = (string) preg_replace('/[^DSTF01*]/', '', $s);
        $s = strtr($s, ['T' => 'D', 'F' => 'S', '1' => 'D', '0' => 'S']);
        if (strlen($s) !== $items) {
            return null;
        }
        return $s;
    }

    /**
     * Phần III: "1,5" | "1.5" | "-0,25" | "12" ; nhiều đáp án chấp nhận cách nhau bởi "|" hoặc ";".
     * Trả về dạng dấu phẩy thập phân: "1,5|1,50".
     */
    public static function p3(?string $v): ?string
    {
        $raw = trim((string) $v);
        if ($raw === '') {
            return null;
        }
        if ($raw === '*') {
            return '*';
        }
        $out = [];
        foreach (preg_split('/\s*[|;]\s*/', $raw) as $alt) {
            $alt = str_replace([' ', '−', '–'], ['', '-', '-'], $alt);
            $alt = str_replace('.', ',', $alt);
            if ($alt === '') {
                continue;
            }
            if (!preg_match('/^-?(\d+(,\d*)?|,\d+)$/', $alt)) {
                return null;
            }
            $out[] = $alt;
        }
        return $out ? implode('|', array_unique($out)) : null;
    }

    public static function parseNumber(string $s): ?float
    {
        $s = str_replace(',', '.', trim($s));
        if ($s === '' || $s === '-' || $s === '.' || $s === '-.') {
            return null;
        }
        return is_numeric($s) ? (float) $s : null;
    }

    // ------------------ Câu trả lời của học sinh (dữ liệu từ trình duyệt) ------------------

    /** Làm sạch toàn bộ bài làm theo cấu trúc đề. */
    public static function sanitizeAnswers($answers, array $structure): array
    {
        $a = is_array($answers) ? $answers : [];
        $out = ['p1' => [], 'p2' => [], 'p3' => [], 'e' => []];
        foreach ((array) ($a['p1'] ?? []) as $n => $v) {
            $n = (int) $n;
            $v = strtoupper((string) $v);
            if ($n >= 1 && $n <= $structure['p1'] && in_array($v, ['A', 'B', 'C', 'D'], true)) {
                $out['p1'][(string) $n] = $v;
            }
        }
        foreach ((array) ($a['p2'] ?? []) as $n => $v) {
            $n = (int) $n;
            $v = strtoupper(substr((string) $v, 0, 4));
            if ($n >= 1 && $n <= $structure['p2'] && preg_match('/^[DS_]{1,4}$/', $v) && trim($v, '_') !== '') {
                $out['p2'][(string) $n] = str_pad($v, 4, '_');
            }
        }
        $len = (int) ($structure['p3_len'] ?? 4);
        foreach ((array) ($a['p3'] ?? []) as $n => $v) {
            $n = (int) $n;
            $v = rtrim(substr((string) $v, 0, $len), '_');
            if ($n >= 1 && $n <= $structure['p3'] && $v !== '' && preg_match('/^[-,0-9_]+$/', $v)) {
                $out['p3'][(string) $n] = $v;
            }
        }
        $ne = count($structure['essay'] ?? []);
        foreach ((array) ($a['e'] ?? []) as $n => $v) {
            $n = (int) $n;
            $v = (string) $v;
            if ($n >= 1 && $n <= $ne && trim($v) !== '') {
                $out['e'][(string) $n] = mb_substr($v, 0, 30000);
            }
        }
        return $out;
    }

    public static function countAnswered(array $answers): int
    {
        $n = 0;
        foreach (['p1', 'p2', 'p3', 'e'] as $p) {
            $n += count((array) ($answers[$p] ?? []));
        }
        return $n;
    }

    /**
     * Kiểm tra câu trả lời ngắn theo quy tắc phiếu trả lời:
     * tô từ trái sang phải, không bỏ trống ô ở giữa; dấu "−" chỉ ở cột 1; dấu phẩy chỉ ở cột 2 hoặc 3.
     * @return array{0: bool, 1: string} [hợp lệ, chuỗi đáp án | thông báo lỗi]
     */
    public static function validateP3(string $given, int $len = 4): array
    {
        $g = rtrim($given, '_');
        if ($g === '') {
            return [false, 'Chưa trả lời'];
        }
        if (strpos($g, '_') !== false) {
            return [false, 'Bỏ trống ô ở giữa (phải tô từ trái sang phải)'];
        }
        if (strlen($g) > $len) {
            return [false, 'Quá ' . $len . ' ký tự'];
        }
        $minus = strpos($g, '-');
        if ($minus !== false && ($minus !== 0 || substr_count($g, '-') > 1)) {
            return [false, 'Dấu "−" chỉ được tô ở cột đầu tiên'];
        }
        if (substr_count($g, ',') > 1) {
            return [false, 'Chỉ được tô một dấu phẩy'];
        }
        $comma = strpos($g, ',');
        if ($comma !== false && ($comma < 1 || $comma > $len - 2)) {
            return [false, 'Dấu phẩy chỉ được tô ở cột 2 hoặc cột 3'];
        }
        if (!preg_match('/\d/', $g)) {
            return [false, 'Chưa có chữ số'];
        }
        if ($comma !== false && $comma === strlen($g) - 1) {
            return [false, 'Thiếu chữ số sau dấu phẩy'];
        }
        return [true, $g];
    }
}

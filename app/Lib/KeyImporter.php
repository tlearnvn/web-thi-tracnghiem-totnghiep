<?php

namespace App\Lib;

use App\Core\Database;

/**
 * Nhập đáp án – mã đề – lời giải từ Excel (.xlsx/.csv) hoặc JSON.
 *
 * Tự nhận dạng 3 kiểu bảng Excel thường gặp:
 *  A. Dạng dọc  : mỗi dòng một câu   – cột Mã đề | Phần | Câu | Đáp án | Điểm | Mức độ | Chủ đề | Câu gốc | Lời giải
 *  B. Dạng ngang: mỗi dòng một mã đề – cột Mã đề | I.1 | I.2 | … | II.1 | … | III.1 …
 *  C. Dạng bảng : mỗi cột một mã đề  – cột Câu | 0101 | 0102 | … (kiểu xuất từ phần mềm trộn đề)
 */
final class KeyImporter
{
    private const HEADERS = [
        'code' => ['made', 'madethi', 'made thi', 'ma', 'de', 'madeso', 'variant', 'code', 'mde'],
        'part' => ['phan', 'phanthi', 'part', 'loaicau', 'dang'],
        'num' => ['cau', 'cauhoi', 'socau', 'caus', 'question', 'q', 'caiso'],
        'answer' => ['dapan', 'dapandung', 'da', 'key', 'answer', 'ketqua'],
        'points' => ['diem', 'sodiem', 'points', 'point', 'thangdiem'],
        'level' => ['mucdo', 'mucdonhanthuc', 'level', 'capdo', 'mucdotuduy'],
        'topic' => ['chude', 'noidung', 'chuong', 'topic', 'bai', 'kienthuc', 'donvikienthuc'],
        'origin' => ['caugoc', 'goc', 'origin', 'cauhoigoc', 'magoc'],
        'explanation' => ['loigiai', 'giaithich', 'huongdan', 'huongdangiai', 'loigiaichitiet', 'explanation', 'solution', 'giaichitiet'],
    ];

    /**
     * @return array{variants: array, errors: array, warnings: array, format: string, sheets: array}
     */
    public static function parse(string $data, string $filename, array $structure, ?string $defaultCode = null, int $sheet = 0): array
    {
        $trim = ltrim($data, "\xEF\xBB\xBF \t\r\n");
        $isJson = preg_match('/\.json$/i', $filename) || (isset($trim[0]) && ($trim[0] === '{' || $trim[0] === '['));
        $res = ['variants' => [], 'errors' => [], 'warnings' => [], 'format' => '', 'sheets' => []];
        try {
            if ($isJson) {
                $res = array_merge($res, self::parseJson($trim, $defaultCode));
                $res['format'] = 'JSON';
            } else {
                $book = XlsxReader::readAny($data, $sheet);
                $res['sheets'] = $book['sheets'];
                $res = array_merge($res, self::parseRows($book['rows'], $defaultCode));
            }
        } catch (\Throwable $e) {
            $res['errors'][] = $e->getMessage();
            return $res;
        }
        return self::finalize($res, $structure);
    }

    // ------------------------------------------------------------------ JSON

    private static function parseJson(string $json, ?string $defaultCode): array
    {
        $d = json_decode($json, true);
        if (!is_array($d)) {
            throw new \RuntimeException('Tệp JSON không hợp lệ: ' . json_last_error_msg());
        }
        $list = $d['variants'] ?? $d['ma_de'] ?? $d['made'] ?? null;
        if ($list === null) {
            $list = array_is_list_compat($d) ? $d : [$d];
        }
        $variants = [];
        foreach ($list as $idx => $v) {
            if (!is_array($v)) {
                continue;
            }
            $code = trim((string) ($v['code'] ?? $v['ma_de'] ?? $v['made'] ?? (is_string($idx) ? $idx : ($defaultCode ?? ''))));
            if ($code === '') {
                $code = $defaultCode ?? ('DE' . ($idx + 1));
            }
            $qs = [];
            $partKeys = [1 => ['p1', 'part1', 'phan1', 'I'], 2 => ['p2', 'part2', 'phan2', 'II'], 3 => ['p3', 'part3', 'phan3', 'III'], 4 => ['essay', 'tuluan', 'p4', 'IV']];
            foreach ($partKeys as $part => $names) {
                foreach ($names as $nk) {
                    if (!array_key_exists($nk, $v)) {
                        continue;
                    }
                    $items = $v[$nk];
                    if ($part === 1 && is_string($items) && preg_match('/^[ABCD\s,]+$/i', $items)) {
                        $items = str_split((string) preg_replace('/[^ABCD]/i', '', $items));
                    }
                    foreach ((array) $items as $n => $val) {
                        $num = is_int($n) && array_is_list_compat((array) $items) ? $n + 1 : (int) $n;
                        $q = ['part' => $part, 'num' => $num];
                        if (is_array($val) && !array_is_list_compat($val)) {
                            $q += self::qFromAssoc($val);
                        } else {
                            $q['answer'] = is_array($val) ? self::boolsToP2($val) : (string) $val;
                        }
                        $qs[] = $q;
                    }
                    break;
                }
            }
            foreach ((array) ($v['questions'] ?? $v['cau_hoi'] ?? []) as $q) {
                if (!is_array($q)) {
                    continue;
                }
                $part = self::parsePart((string) ($q['part'] ?? $q['phan'] ?? ''));
                $qq = self::qFromAssoc($q);
                $qq['part'] = $part;
                $qq['num'] = (int) ($q['num'] ?? $q['number'] ?? $q['cau'] ?? 0);
                $qs[] = $qq;
            }
            $exp = $v['explanations'] ?? $v['loi_giai'] ?? null;
            if (is_array($exp)) {
                foreach ($partKeys as $part => $names) {
                    foreach ($names as $nk) {
                        if (isset($exp[$nk]) && is_array($exp[$nk])) {
                            foreach ($exp[$nk] as $n => $text) {
                                $num = is_int($n) && array_is_list_compat($exp[$nk]) ? $n + 1 : (int) $n;
                                $qs[] = ['part' => $part, 'num' => $num, 'explanation' => (string) $text, '_merge' => true];
                            }
                            break;
                        }
                    }
                }
            }
            $variants[] = ['code' => $code, 'questions' => $qs];
        }
        return ['variants' => $variants];
    }

    private static function qFromAssoc(array $q): array
    {
        $ans = $q['answer'] ?? $q['dap_an'] ?? $q['key'] ?? null;
        if (is_array($ans)) {
            $ans = self::boolsToP2($ans);
        }
        return [
            'answer' => $ans === null ? null : (string) $ans,
            'points' => $q['points'] ?? $q['diem'] ?? null,
            'level' => $q['level'] ?? $q['muc_do'] ?? null,
            'topic' => $q['topic'] ?? $q['chu_de'] ?? null,
            'origin' => isset($q['origin']) ? (string) $q['origin'] : (isset($q['cau_goc']) ? (string) $q['cau_goc'] : null),
            'explanation' => $q['explanation'] ?? $q['loi_giai'] ?? $q['giai_thich'] ?? null,
            'is_void' => !empty($q['void']) || !empty($q['huy']) ? 1 : 0,
        ];
    }

    private static function boolsToP2(array $vals): string
    {
        $s = '';
        foreach ($vals as $b) {
            if (is_bool($b)) {
                $s .= $b ? 'D' : 'S';
            } else {
                $s .= (string) $b;
            }
        }
        return $s;
    }

    // ------------------------------------------------------------------ Excel / CSV

    private static function parseRows(array $rows, ?string $defaultCode): array
    {
        if (!$rows) {
            throw new \RuntimeException('Bảng tính trống.');
        }
        // Tìm dòng tiêu đề trong 15 dòng đầu
        $headerRow = null;
        $map = [];
        $scanned = 0;
        foreach ($rows as $rn => $cells) {
            if (++$scanned > 15) {
                break;
            }
            $m = self::mapHeaders($cells);
            if (isset($m['num']) || (isset($m['code']) && count($cells) > 2)) {
                $headerRow = $rn;
                $map = $m;
                break;
            }
        }
        if ($headerRow === null) {
            throw new \RuntimeException('Không tìm thấy dòng tiêu đề (cần có cột "Câu" hoặc "Mã đề"). Hãy dùng tệp mẫu của hệ thống.');
        }
        $headers = $rows[$headerRow];
        $dataRows = array_filter($rows, static fn($k) => $k > $headerRow, ARRAY_FILTER_USE_KEY);

        if (isset($map['num'], $map['answer']) || isset($map['num'], $map['explanation'])) {
            return self::parseLong($dataRows, $map, $defaultCode) + ['format' => 'Excel – mỗi dòng một câu'];
        }
        if (isset($map['code']) && !isset($map['num'])) {
            return self::parseWide($dataRows, $headers, $map) + ['format' => 'Excel – mỗi dòng một mã đề'];
        }
        if (isset($map['num'])) {
            return self::parseTransposed($dataRows, $headers, $map) + ['format' => 'Excel – mỗi cột một mã đề'];
        }
        throw new \RuntimeException('Không nhận dạng được cấu trúc bảng đáp án.');
    }

    private static function mapHeaders(array $cells): array
    {
        $map = [];
        foreach ($cells as $ci => $v) {
            $h = Text::normalizeHeader((string) $v);
            if ($h === '') {
                continue;
            }
            foreach (self::HEADERS as $field => $aliases) {
                if (isset($map[$field])) {
                    continue;
                }
                if (in_array($h, array_map(static fn($a) => str_replace(' ', '', $a), $aliases), true)) {
                    $map[$field] = $ci;
                    continue 2;
                }
            }
            if (!isset($map['explanation']) && (strpos($h, 'loigiai') !== false || strpos($h, 'giaithich') !== false || strpos($h, 'huongdan') !== false)) {
                $map['explanation'] = $ci;
            } elseif (!isset($map['answer']) && strpos($h, 'dapan') === 0) {
                $map['answer'] = $ci;
            } elseif (!isset($map['code']) && strpos($h, 'made') === 0 && strlen($h) <= 8) {
                $map['code'] = $ci;
            }
        }
        return $map;
    }

    private static function parseLong(array $rows, array $map, ?string $defaultCode): array
    {
        $variants = [];
        $warnings = [];
        $p2parts = []; // [code][num] => [a,b,c,d]
        foreach ($rows as $rn => $cells) {
            $numRaw = trim((string) ($cells[$map['num']] ?? ''));
            if ($numRaw === '') {
                continue;
            }
            $code = isset($map['code']) ? trim((string) ($cells[$map['code']] ?? '')) : '';
            if ($code === '') {
                $code = $defaultCode ?? 'CHUNG';
            }
            [$num, $sub] = self::parseNum($numRaw);
            if ($num <= 0) {
                $warnings[] = 'Dòng ' . $rn . ': số câu "' . $numRaw . '" không hợp lệ – bỏ qua.';
                continue;
            }
            $partRaw = isset($map['part']) ? (string) ($cells[$map['part']] ?? '') : '';
            $part = $partRaw !== '' ? self::parsePart($partRaw) : 0;
            $answer = isset($map['answer']) ? trim((string) ($cells[$map['answer']] ?? '')) : '';
            $q = [
                'part' => $part,
                'num' => $num,
                'answer' => $answer !== '' ? $answer : null,
                'points' => isset($map['points']) ? self::num($cells[$map['points']] ?? null) : null,
                'level' => isset($map['level']) ? self::str($cells[$map['level']] ?? null) : null,
                'topic' => isset($map['topic']) ? self::str($cells[$map['topic']] ?? null) : null,
                'origin' => isset($map['origin']) ? self::str($cells[$map['origin']] ?? null) : null,
                'explanation' => isset($map['explanation']) ? self::str($cells[$map['explanation']] ?? null, 60000) : null,
                '_row' => $rn,
            ];
            if ($sub !== null) {
                // Dòng theo từng ý của câu đúng/sai: 1a, 1b, 1c, 1d
                $q['part'] = 2;
                $key = $code . '#' . $num;
                if (!isset($p2parts[$key])) {
                    $p2parts[$key] = ['code' => $code, 'q' => $q, 'subs' => ['_', '_', '_', '_'], 'exp' => []];
                    $p2parts[$key]['q']['answer'] = null;
                }
                $v = KeyFormat::p2(str_repeat((string) $answer, 4));
                $p2parts[$key]['subs'][$sub] = $v !== null ? $v[0] : '_';
                if ($q['explanation']) {
                    $p2parts[$key]['exp'][] = chr(97 + $sub) . ') ' . $q['explanation'];
                }
                continue;
            }
            $variants[$code][] = $q;
        }
        foreach ($p2parts as $p) {
            $q = $p['q'];
            $q['answer'] = implode('', $p['subs']);
            if ($p['exp']) {
                $q['explanation'] = implode("\n", $p['exp']);
            }
            $variants[$p['code']][] = $q;
        }
        $out = [];
        foreach ($variants as $code => $qs) {
            $out[] = ['code' => (string) $code, 'questions' => $qs];
        }
        return ['variants' => $out, 'warnings' => $warnings];
    }

    private static function parseWide(array $rows, array $headers, array $map): array
    {
        $cols = [];
        foreach ($headers as $ci => $h) {
            if ($ci === $map['code']) {
                continue;
            }
            $spec = self::parseQuestionHeader((string) $h);
            if ($spec) {
                $cols[$ci] = $spec;
            }
        }
        if (!$cols) {
            throw new \RuntimeException('Không tìm thấy cột câu hỏi (ví dụ: 1, 2, 3… hoặc I.1, II.1, III.1).');
        }
        $out = [];
        foreach ($rows as $cells) {
            $code = trim((string) ($cells[$map['code']] ?? ''));
            if ($code === '') {
                continue;
            }
            $qs = [];
            foreach ($cols as $ci => [$part, $num, $sub]) {
                $val = trim((string) ($cells[$ci] ?? ''));
                if ($val === '') {
                    continue;
                }
                $qs[] = ['part' => $part, 'num' => $num, 'answer' => $val];
            }
            $out[] = ['code' => $code, 'questions' => $qs];
        }
        return ['variants' => $out];
    }

    private static function parseTransposed(array $rows, array $headers, array $map): array
    {
        $codes = [];
        foreach ($headers as $ci => $h) {
            if ($ci === $map['num'] || (isset($map['part']) && $ci === $map['part'])) {
                continue;
            }
            $h = trim((string) $h);
            if ($h === '') {
                continue;
            }
            if (preg_match('/(\d+[A-Za-z]?)\s*$/u', Text::unaccent($h), $m)) {
                $codes[$ci] = $m[1];
            }
        }
        if (!$codes) {
            throw new \RuntimeException('Không tìm thấy cột mã đề (ví dụ tiêu đề cột: 0101, 0102 hoặc "Mã 101").');
        }
        $variants = [];
        $currentPart = 0;
        foreach ($rows as $cells) {
            $numRaw = trim((string) ($cells[$map['num']] ?? ''));
            // Dòng phân cách "PHẦN II" trong bảng
            $partHint = self::parsePartHeading($numRaw);
            if ($partHint) {
                $currentPart = $partHint;
                continue;
            }
            [$num] = self::parseNum($numRaw);
            if ($num <= 0) {
                continue;
            }
            $part = isset($map['part']) && trim((string) ($cells[$map['part']] ?? '')) !== ''
                ? self::parsePart((string) $cells[$map['part']]) : $currentPart;
            foreach ($codes as $ci => $code) {
                $val = trim((string) ($cells[$ci] ?? ''));
                if ($val !== '') {
                    $variants[$code][] = ['part' => $part, 'num' => $num, 'answer' => $val];
                }
            }
        }
        $out = [];
        foreach ($variants as $code => $qs) {
            $out[] = ['code' => (string) $code, 'questions' => $qs];
        }
        return ['variants' => $out];
    }

    // ------------------------------------------------------------------ Chuẩn hóa & kiểm tra

    private static function finalize(array $res, array $structure): array
    {
        $limits = [1 => $structure['p1'], 2 => $structure['p2'], 3 => $structure['p3'], 4 => count($structure['essay'] ?? [])];
        $names = [1 => 'Phần I', 2 => 'Phần II', 3 => 'Phần III', 4 => 'Tự luận'];
        $variants = [];
        foreach ($res['variants'] as $v) {
            $code = self::cleanCode((string) $v['code']);
            if ($code === '') {
                $res['warnings'][] = 'Bỏ qua một mã đề không có tên.';
                continue;
            }
            $byPart = [1 => [], 2 => [], 3 => [], 4 => []];
            foreach ($v['questions'] as $q) {
                $part = (int) ($q['part'] ?? 0);
                if ($part === 0) {
                    $part = self::inferPart($q['answer'] ?? null);
                }
                if ($part === 0) {
                    if (!empty($q['explanation']) && empty($q['answer'])) {
                        $part = 1;
                    } else {
                        $res['warnings'][] = 'Mã ' . $code . ' câu ' . $q['num'] . ': không xác định được phần (đáp án "' . ($q['answer'] ?? '') . '").';
                        continue;
                    }
                }
                $q['part'] = $part;
                $byPart[$part][] = $q;
            }
            $final = [];
            foreach ($byPart as $part => $qs) {
                if (!$qs) {
                    continue;
                }
                // Đánh số lại khi bảng đánh số liên tục qua các phần (vd Phần II từ câu 13)
                $nums = array_map(static fn($q) => (int) $q['num'], $qs);
                $min = min($nums);
                if ($min > 1 && max($nums) > $limits[$part]) {
                    $sorted = array_values(array_unique($nums));
                    sort($sorted);
                    $re = array_flip($sorted);
                    foreach ($qs as &$q) {
                        $q['num'] = $re[(int) $q['num']] + 1;
                    }
                    unset($q);
                    $res['warnings'][] = 'Mã ' . $code . ': ' . $names[$part] . ' được đánh số lại từ câu ' . $min . '–' . max($nums) . ' thành 1–' . count($sorted) . '.';
                }
                foreach ($qs as $q) {
                    $num = (int) $q['num'];
                    if ($num < 1 || $num > $limits[$part]) {
                        $res['warnings'][] = 'Mã ' . $code . ': ' . $names[$part] . ' câu ' . $num . ' vượt quá số câu của đề (' . $limits[$part] . ') – bỏ qua.';
                        continue;
                    }
                    $answer = $q['answer'] ?? null;
                    $norm = null;
                    if ($answer !== null && $answer !== '') {
                        $norm = $part === 1 ? KeyFormat::p1($answer) : ($part === 2 ? KeyFormat::p2($answer) : ($part === 3 ? KeyFormat::p3($answer) : null));
                        if ($norm === null && $part !== 4) {
                            $res['errors'][] = 'Mã ' . $code . ': ' . $names[$part] . ' câu ' . $num . ' – đáp án "' . $answer . '" không hợp lệ' . ($part === 2 ? ' (cần 4 ký tự Đ/S cho a, b, c, d)' : ($part === 3 ? ' (cần là số, ví dụ -1,5)' : ' (cần A, B, C hoặc D)')) . '.';
                            continue;
                        }
                    }
                    $key = $part . '.' . $num;
                    $row = [
                        'part' => $part,
                        'num' => $num,
                        'answer' => $norm,
                        'points' => self::num($q['points'] ?? null),
                        'level' => self::normLevel($q['level'] ?? null),
                        'topic' => self::str($q['topic'] ?? null, 150),
                        'origin' => self::str($q['origin'] ?? null, 20),
                        'explanation' => self::str($q['explanation'] ?? null, 60000),
                        'is_void' => (int) ($q['is_void'] ?? 0),
                    ];
                    if (isset($final[$key])) {
                        // gộp (vd lời giải khai báo riêng)
                        foreach ($row as $f => $val) {
                            if ($val !== null && $val !== '' && !in_array($f, ['part', 'num'], true)) {
                                $final[$key][$f] = $val;
                            }
                        }
                    } else {
                        $final[$key] = $row;
                    }
                }
            }
            ksort($final, SORT_NATURAL);
            $qs = array_values($final);
            // Cảnh báo thiếu đáp án
            $missing = [];
            foreach ([1, 2, 3] as $part) {
                for ($i = 1; $i <= $limits[$part]; $i++) {
                    if (!isset($final[$part . '.' . $i]) || $final[$part . '.' . $i]['answer'] === null) {
                        $missing[] = ['I', 'II', 'III'][$part - 1] . '.' . $i;
                    }
                }
            }
            if ($missing) {
                $res['warnings'][] = 'Mã ' . $code . ' còn thiếu đáp án: ' . implode(', ', array_slice($missing, 0, 20)) . (count($missing) > 20 ? '… (' . count($missing) . ' câu)' : '') . '.';
            }
            $variants[$code] = ['code' => $code, 'questions' => $qs, 'answered' => count(array_filter($qs, static fn($q) => $q['answer'] !== null)), 'explained' => count(array_filter($qs, static fn($q) => $q['explanation'] !== null))];
        }
        $res['variants'] = array_values($variants);
        if (!$res['variants'] && !$res['errors']) {
            $res['errors'][] = 'Không đọc được đáp án nào từ tệp.';
        }
        return $res;
    }

    public static function cleanCode(string $code): string
    {
        $code = trim(Text::unaccent($code));
        $code = (string) preg_replace('/^(ma\s*de|made|ma|de)\s*[:#.\-]?\s*/i', '', $code);
        return mb_substr((string) preg_replace('/[^A-Za-z0-9_.\-]/', '', $code), 0, 20);
    }

    private static function inferPart(?string $answer): int
    {
        if ($answer === null || trim($answer) === '') {
            return 0;
        }
        $a = strtoupper(trim(Text::unaccent($answer)));
        if (preg_match('/^[ABCD]([|,\/ ]?[ABCD])*$/', $a) && strlen(preg_replace('/[^ABCD]/', '', $a)) < 4) {
            return 1;
        }
        if (KeyFormat::p2($answer) !== null && !preg_match('/^[\d.,\-|;]+$/', trim($answer))) {
            return 2;
        }
        if (KeyFormat::p3($answer) !== null) {
            return 3;
        }
        if (preg_match('/^[ABCD]$/', $a)) {
            return 1;
        }
        return 0;
    }

    public static function parsePart(string $v): int
    {
        $s = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', Text::unaccent($v)));
        $s = (string) preg_replace('/^PHAN/', '', $s);
        $map = ['I' => 1, '1' => 1, 'TN' => 1, 'TRACNGHIEM' => 1, 'MCQ' => 1, 'P1' => 1,
            'II' => 2, '2' => 2, 'DS' => 2, 'DUNGSAI' => 2, 'TF' => 2, 'P2' => 2,
            'III' => 3, '3' => 3, 'TLN' => 3, 'TRALOINGAN' => 3, 'SA' => 3, 'P3' => 3,
            'IV' => 4, '4' => 4, 'TL' => 4, 'TULUAN' => 4, 'ESSAY' => 4, 'P4' => 4];
        return $map[$s] ?? 0;
    }

    private static function parsePartHeading(string $v): int
    {
        $s = strtoupper(trim(Text::unaccent($v)));
        if (preg_match('/^PHAN\s+(I{1,3}|IV|[1-4])\b/', $s, $m)) {
            return self::parsePart($m[1]);
        }
        return 0;
    }

    /** "Câu 1" -> [1, null]; "1a" -> [1, 0]; "C13" -> [13, null] */
    private static function parseNum(string $v): array
    {
        $s = strtolower(trim(Text::unaccent($v)));
        if (preg_match('/(\d+)\s*[\.\)\-]?\s*([a-d])?\)?$/', $s, $m)) {
            return [(int) $m[1], isset($m[2]) && $m[2] !== '' ? ord($m[2]) - 97 : null];
        }
        return [0, null];
    }

    /** Tiêu đề cột dạng ngang: "1", "Câu 1", "I.1", "II.3", "P3.2", "TN1", "DS2", "TLN5" -> [phần, số, ý] */
    private static function parseQuestionHeader(string $h): ?array
    {
        $s = strtolower(trim(Text::unaccent($h)));
        $s = (string) preg_replace('/\s+/', '', $s);
        if (!preg_match('/^(phan)?(iii|ii|iv|i|p[1-4]|tln|tn|ds|tl)?[._\-]?(cau|c)?(\d+)([a-d])?$/', $s, $m)) {
            return null;
        }
        $part = $m[2] !== '' ? self::parsePart($m[2]) : 0;
        return [$part, (int) $m[4], isset($m[5]) && $m[5] !== '' ? ord($m[5]) - 97 : null];
    }

    private static function num($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = str_replace(',', '.', (string) $v);
        return is_numeric($v) ? round((float) $v, 4) : null;
    }

    private static function str($v, int $max = 255): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    public static function normLevel($v): ?string
    {
        $s = self::str($v, 30);
        if ($s === null) {
            return null;
        }
        $u = strtolower(Text::unaccent($s));
        $u = (string) preg_replace('/[^a-z]/', '', $u);
        $map = ['nb' => 'Biết', 'nhanbiet' => 'Biết', 'biet' => 'Biết', 'th' => 'Hiểu', 'thonghieu' => 'Hiểu', 'hieu' => 'Hiểu',
            'vd' => 'Vận dụng', 'vandung' => 'Vận dụng', 'vdc' => 'Vận dụng cao', 'vandungcao' => 'Vận dụng cao'];
        return $map[$u] ?? $s;
    }

    // ------------------------------------------------------------------ Lưu

    /**
     * Ghi đáp án vào CSDL.
     * @param bool $replace true: xóa đáp án cũ của mã đề; false: chỉ cập nhật các câu có trong tệp
     */
    public static function save(Database $db, int $variantId, array $questions, bool $replace = true): int
    {
        return $db->transaction(function (Database $db) use ($variantId, $questions, $replace) {
            if ($replace) {
                $db->run('DELETE FROM {exam_keys} WHERE variant_id = ?', [$variantId]);
            }
            $n = 0;
            $now = time();
            foreach ($questions as $q) {
                $row = [
                    'variant_id' => $variantId,
                    'part' => (int) $q['part'],
                    'num' => (int) $q['num'],
                    'answer' => $q['answer'],
                    'points' => $q['points'],
                    'level' => $q['level'],
                    'topic' => $q['topic'],
                    'origin' => $q['origin'],
                    'explanation' => $q['explanation'],
                    'is_void' => (int) ($q['is_void'] ?? 0),
                    'updated_at' => $now,
                ];
                if ($replace) {
                    $db->insert('exam_keys', $row);
                } else {
                    $existing = $db->one('SELECT id FROM {exam_keys} WHERE variant_id = ? AND part = ? AND num = ?', [$variantId, $row['part'], $row['num']]);
                    if ($existing) {
                        $upd = array_filter($row, static fn($v, $k) => !in_array($k, ['variant_id', 'part', 'num'], true) && $v !== null, ARRAY_FILTER_USE_BOTH);
                        if ($upd) {
                            $db->update('exam_keys', $upd, 'id = ?', [$existing['id']]);
                        }
                    } else {
                        $db->insert('exam_keys', $row);
                    }
                }
                $n++;
            }
            return $n;
        });
    }

    // ------------------------------------------------------------------ Tệp mẫu

    public static function templateXlsx(array $structure, array $codes, string $title = ''): string
    {
        $x = new XlsxWriter();
        $h = $x->style(['bold' => true, 'fill' => '1D4ED8', 'color' => 'FFFFFF', 'border' => true, 'align' => 'center', 'wrap' => true]);
        $t = $x->style(['border' => true]);
        $c = $x->style(['border' => true, 'align' => 'center']);
        $note = $x->style(['italic' => true, 'color' => '64748B']);
        $s = $x->sheet('Đáp án');
        $s->widths([10, 8, 7, 12, 8, 12, 24, 9, 70]);
        $s->row(['Mã đề', 'Phần', 'Câu', 'Đáp án', 'Điểm', 'Mức độ', 'Chủ đề', 'Câu gốc', 'Lời giải (tùy chọn, hỗ trợ công thức $...$)'], $h, [], 30);
        $codes = $codes ?: ['0101'];
        $sampleP1 = ['A', 'B', 'C', 'D'];
        foreach ($codes as $code) {
            for ($i = 1; $i <= $structure['p1']; $i++) {
                $s->row([(string) $code, 'I', $i, '', '', '', '', '', ''], $t, [1 => $c, 2 => $c, 3 => $c]);
            }
            for ($i = 1; $i <= $structure['p2']; $i++) {
                $s->row([(string) $code, 'II', $i, '', '', '', '', '', ''], $t, [1 => $c, 2 => $c, 3 => $c]);
            }
            for ($i = 1; $i <= $structure['p3']; $i++) {
                $s->row([(string) $code, 'III', $i, '', '', '', '', '', ''], $t, [1 => $c, 2 => $c, 3 => $c]);
            }
            foreach (($structure['essay'] ?? []) as $i => $e) {
                $s->row([(string) $code, 'IV', $i + 1, '', $e['points'], '', $e['label'], '', 'Hướng dẫn chấm / đáp án gợi ý…'], $t, [1 => $c, 2 => $c, 3 => $c]);
            }
        }
        $s->freeze('A2');
        $s->autoFilter('A1:I' . $s->rowCount());

        $g = $x->sheet('Hướng dẫn');
        $g->widths([110]);
        $g->row([$title !== '' ? 'MẪU NHẬP ĐÁP ÁN – ' . $title : 'MẪU NHẬP ĐÁP ÁN'], $x->style(['bold' => true, 'size' => 14]));
        foreach ([
            'Mỗi dòng là một câu hỏi của một mã đề. Có thể nhập nhiều mã đề trong cùng một sheet.',
            'Cột "Phần": I (trắc nghiệm A-B-C-D), II (đúng/sai), III (trả lời ngắn), IV (tự luận).',
            'Phần I – Đáp án là A, B, C hoặc D. Nếu chấp nhận nhiều đáp án, ghi "A|C". Ghi "*" để hủy câu (cho điểm tất cả).',
            'Phần II – Ghi 4 ký tự Đ/S tương ứng các ý a), b), c), d). Ví dụ: ĐSĐĐ (hoặc DSDD, 1011, TFTT). Ý nào hủy ghi "*".',
            'Phần II – Cũng có thể nhập mỗi ý một dòng với cột Câu là 1a, 1b, 1c, 1d và Đáp án là Đ hoặc S.',
            'Phần III – Ghi số, dùng dấu phẩy thập phân. Ví dụ: -1,5 ; 12 ; 0,25. Nhiều đáp án chấp nhận: "1,5|1,50".',
            'LƯU Ý: Định dạng ô "Đáp án" và "Mã đề" là Text (@) để Excel không tự đổi "0101" thành 101 hay "1,5" thành ngày tháng.',
            'Cột "Điểm" (tùy chọn): điểm riêng của câu, bỏ trống để dùng cách tính điểm chung của đề.',
            'Cột "Mức độ" (tùy chọn): Biết / Hiểu / Vận dụng (hoặc NB, TH, VD, VDC) – dùng cho thống kê theo mức độ.',
            'Cột "Câu gốc" (tùy chọn): số thứ tự câu trong đề gốc – giúp thống kê một câu hỏi trên nhiều mã đề đã trộn.',
            'Cột "Lời giải" (tùy chọn): giải thích chi tiết hiển thị cho học sinh khi xem lại bài. Hỗ trợ công thức LaTeX đặt trong $...$',
            'Hệ thống cũng nhận: bảng mỗi dòng một mã đề (cột I.1, I.2 … II.1 … III.1), hoặc bảng mỗi cột một mã đề (kiểu phần mềm trộn đề).',
        ] as $line) {
            $g->row([$line], $x->style(['wrap' => true]));
        }
        $g->row(['']);
        $g->row(['Bạn cũng có thể nhập bằng tệp JSON – xem tệp mẫu JSON trên trang nhập đáp án.'], $note);
        return $x->build();
    }

    public static function sampleJson(array $structure, array $codes): string
    {
        $codes = $codes ?: ['0101'];
        $variants = [];
        foreach ($codes as $code) {
            $v = ['code' => (string) $code];
            if ($structure['p1'] > 0) {
                $v['p1'] = array_map(static fn($i) => ['A', 'B', 'C', 'D'][$i % 4], range(0, $structure['p1'] - 1));
            }
            if ($structure['p2'] > 0) {
                $v['p2'] = array_fill(0, $structure['p2'], 'ĐSĐĐ');
            }
            if ($structure['p3'] > 0) {
                $v['p3'] = array_slice(['-1,5', '12', '0,25', '3', '2,5', '100', '7', '0,5'], 0, $structure['p3']) + array_fill(0, $structure['p3'], '1');
            }
            $v['explanations'] = ['p1' => ['1' => 'Ví dụ lời giải câu 1 – hỗ trợ công thức $x^2 + 1 = 0$.']];
            $variants[] = $v;
        }
        return json_encode(['variants' => $variants], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

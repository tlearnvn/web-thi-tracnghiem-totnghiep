<?php

namespace App\Lib;

/**
 * Bộ máy chấm điểm linh hoạt.
 *
 * Các cách tính:
 *  - moet2025 : đúng quy định của Bộ GD&ĐT từ năm 2025 (Phần I 0,25đ/câu; Phần II 0,1–0,25–0,5–1đ;
 *               Phần III 0,5đ (Toán) hoặc 0,25đ (các môn khác)).
 *  - custom   : giáo viên tự đặt điểm từng phần, có thể trừ điểm câu sai, quy đổi về thang điểm bất kỳ.
 *  - ratio    : điểm = (số câu/ý đúng ÷ tổng số câu/ý) × thang điểm.
 */
final class Scoring
{
    public const SCHEMES = [
        'moet2025' => 'Theo quy định của Bộ GD&ĐT (từ năm 2025)',
        'custom' => 'Tùy chỉnh điểm từng phần',
        'ratio' => 'Theo tỉ lệ số câu / ý đúng',
    ];

    public const MOET_P2_TABLE = [0, 0.1, 0.25, 0.5, 1.0];

    public const ROUNDINGS = [
        '0' => 'Không làm tròn',
        '0.01' => 'Làm tròn 2 chữ số thập phân (0,01)',
        '0.05' => 'Làm tròn đến 0,05',
        '0.1' => 'Làm tròn đến 0,1',
        '0.25' => 'Làm tròn đến 0,25',
        '0.5' => 'Làm tròn đến 0,5',
        '1' => 'Làm tròn đến số nguyên',
    ];

    public static function defaults(): array
    {
        return [
            'scheme' => 'moet2025',
            'p1_point' => 0.25,
            'p1_penalty' => 0.0,
            'p2_mode' => 'table',
            'p2_table' => self::MOET_P2_TABLE,
            'p2_item_point' => 0.25,
            'p2_all_point' => 1.0,
            'p3_point' => 0.25,
            'p3_compare' => 'numeric',
            'max_score' => 10.0,
            'scale' => 0,
            'rounding' => 0.01,
            'round_mode' => 'round',
            'min_zero' => 1,
        ];
    }

    public static function normalize($cfg): array
    {
        $cfg = is_array($cfg) ? $cfg : json_dec($cfg, []);
        $d = self::defaults();
        $s = array_merge($d, array_intersect_key($cfg, $d));
        $s['scheme'] = isset(self::SCHEMES[$s['scheme']]) ? $s['scheme'] : 'moet2025';
        $num = static function ($v, float $def, float $min = 0, float $max = 1000): float {
            if (is_string($v)) {
                $v = str_replace(',', '.', $v);
            }
            return is_numeric($v) ? max($min, min($max, (float) $v)) : $def;
        };
        $s['p1_point'] = $num($s['p1_point'], 0.25);
        $s['p1_penalty'] = $num($s['p1_penalty'], 0.0);
        $s['p2_item_point'] = $num($s['p2_item_point'], 0.25);
        $s['p2_all_point'] = $num($s['p2_all_point'], 1.0);
        $s['p3_point'] = $num($s['p3_point'], 0.25);
        $s['max_score'] = $num($s['max_score'], 10.0, 1, 1000);
        $s['rounding'] = $num($s['rounding'], 0.01, 0, 10);
        $s['p2_mode'] = in_array($s['p2_mode'], ['table', 'per_item', 'all'], true) ? $s['p2_mode'] : 'table';
        $s['p3_compare'] = in_array($s['p3_compare'], ['numeric', 'exact'], true) ? $s['p3_compare'] : 'numeric';
        $s['round_mode'] = in_array($s['round_mode'], ['round', 'up', 'down'], true) ? $s['round_mode'] : 'round';
        $s['scale'] = (int) !empty($s['scale']);
        $s['min_zero'] = (int) !empty($s['min_zero']);
        $table = is_array($s['p2_table']) ? array_values($s['p2_table']) : self::MOET_P2_TABLE;
        $t = [];
        for ($i = 0; $i <= 4; $i++) {
            $t[$i] = $num($table[$i] ?? self::MOET_P2_TABLE[$i], self::MOET_P2_TABLE[$i]);
        }
        $t[0] = 0.0;
        $s['p2_table'] = $t;

        if ($s['scheme'] === 'moet2025') {
            $s['p1_point'] = 0.25;
            $s['p1_penalty'] = 0.0;
            $s['p2_mode'] = 'table';
            $s['p2_table'] = self::MOET_P2_TABLE;
        }
        return $s;
    }

    /** Mô tả ngắn cách tính điểm. */
    public static function describe(array $scoring, array $structure): string
    {
        $s = self::normalize($scoring);
        if ($s['scheme'] === 'ratio') {
            return 'Theo tỉ lệ câu/ý đúng · thang ' . fmt_num($s['max_score']);
        }
        $parts = [];
        if ($structure['p1'] > 0) {
            $parts[] = 'Phần I: ' . fmt_num($s['p1_point']) . 'đ/câu' . ($s['p1_penalty'] > 0 ? ' (sai −' . fmt_num($s['p1_penalty']) . ')' : '');
        }
        if ($structure['p2'] > 0) {
            if ($s['p2_mode'] === 'table') {
                $parts[] = 'Phần II: ' . implode(' / ', array_map(static fn($v) => fmt_num($v), array_slice($s['p2_table'], 1))) . 'đ';
            } elseif ($s['p2_mode'] === 'per_item') {
                $parts[] = 'Phần II: ' . fmt_num($s['p2_item_point']) . 'đ/ý';
            } else {
                $parts[] = 'Phần II: ' . fmt_num($s['p2_all_point']) . 'đ khi đúng cả 4 ý';
            }
        }
        if ($structure['p3'] > 0) {
            $parts[] = 'Phần III: ' . fmt_num($s['p3_point']) . 'đ/câu';
        }
        if (!empty($structure['essay'])) {
            $parts[] = 'Tự luận: GV chấm';
        }
        if ($s['scale']) {
            $parts[] = 'quy về thang ' . fmt_num($s['max_score']);
        }
        return implode(' · ', $parts);
    }

    /** Điểm tối đa lý thuyết của một đề. */
    public static function maxScore(array $structure, array $scoring, array $keys = []): float
    {
        $s = self::normalize($scoring);
        if ($s['scheme'] === 'ratio' || $s['scale']) {
            return $s['max_score'];
        }
        $r = self::compute($structure, $s, $keys, [], [], true);
        return $r['raw_max'];
    }

    /**
     * Chấm một bài làm.
     * @param array $keys ['p1.1' => ['answer' => 'A', 'points' => null, 'is_void' => 0], ...]
     * @param array $answers ['p1' => ['1' => 'A'], 'p2' => ['1' => 'DS_D'], 'p3' => ['1' => '-1,5'], 'e' => [...]]
     * @param array $essayScores ['1' => 0.5, ...]
     */
    public static function compute(array $structure, array $scoring, array $keys, array $answers, array $essayScores = [], bool $maxOnly = false): array
    {
        $s = self::normalize($scoring);
        $items = [];
        $len = (int) ($structure['p3_len'] ?? 4);

        // ---- Phần I ----
        $p1 = ['score' => 0.0, 'max' => 0.0, 'correct' => 0, 'wrong' => 0, 'blank' => 0, 'total' => (int) $structure['p1']];
        for ($i = 1; $i <= $structure['p1']; $i++) {
            $k = $keys['p1.' . $i] ?? null;
            $max = ($k && $k['points'] !== null && $k['points'] !== '') ? (float) $k['points'] : $s['p1_point'];
            $keyAns = $k['answer'] ?? null;
            $void = $k && ((int) ($k['is_void'] ?? 0) === 1 || $keyAns === '*');
            $given = strtoupper((string) ($answers['p1'][$i] ?? $answers['p1'][(string) $i] ?? ''));
            $ok = $void || ($given !== '' && $keyAns !== null && $keyAns !== '' && strpos($keyAns, $given) !== false);
            $pts = $ok ? $max : (($given !== '' && $keyAns !== null) ? -$s['p1_penalty'] : 0.0);
            $p1['max'] += $max;
            $p1['score'] += $pts;
            if ($ok) {
                $p1['correct']++;
            } elseif ($given === '') {
                $p1['blank']++;
            } else {
                $p1['wrong']++;
            }
            $items['p1.' . $i] = ['ok' => $ok, 'pts' => $pts, 'max' => $max, 'given' => $given, 'key' => $keyAns, 'void' => $void];
        }

        // ---- Phần II ----
        $p2 = ['score' => 0.0, 'max' => 0.0, 'correct_items' => 0, 'total_items' => 4 * (int) $structure['p2'], 'full' => 0, 'total' => (int) $structure['p2'], 'blank' => 0];
        $baseMax = $s['p2_mode'] === 'table' ? $s['p2_table'][4] : ($s['p2_mode'] === 'per_item' ? 4 * $s['p2_item_point'] : $s['p2_all_point']);
        for ($i = 1; $i <= $structure['p2']; $i++) {
            $k = $keys['p2.' . $i] ?? null;
            $keyAns = $k['answer'] ?? null;
            $void = $k && (int) ($k['is_void'] ?? 0) === 1;
            $factor = ($k && $k['points'] !== null && $k['points'] !== '' && $baseMax > 0) ? ((float) $k['points'] / $baseMax) : 1.0;
            $given = str_pad(strtoupper((string) ($answers['p2'][$i] ?? $answers['p2'][(string) $i] ?? '')), 4, '_');
            $subs = [];
            $cnt = 0;
            for ($j = 0; $j < 4; $j++) {
                $kc = $keyAns !== null && strlen($keyAns) === 4 ? $keyAns[$j] : null;
                $gc = $given[$j];
                $okj = $void || $kc === '*' || ($kc !== null && $gc !== '_' && $gc === $kc);
                $subs[] = $okj;
                if ($okj) {
                    $cnt++;
                }
            }
            if (trim($given, '_') === '') {
                $p2['blank']++;
            }
            if ($s['p2_mode'] === 'table') {
                $pts = $s['p2_table'][$cnt];
            } elseif ($s['p2_mode'] === 'per_item') {
                $pts = $cnt * $s['p2_item_point'];
            } else {
                $pts = $cnt === 4 ? $s['p2_all_point'] : 0.0;
            }
            $pts *= $factor;
            $max = $baseMax * $factor;
            $p2['max'] += $max;
            $p2['score'] += $pts;
            $p2['correct_items'] += $cnt;
            if ($cnt === 4) {
                $p2['full']++;
            }
            $items['p2.' . $i] = ['k' => $cnt, 'subs' => $subs, 'ok' => $cnt === 4, 'pts' => $pts, 'max' => $max, 'given' => $given, 'key' => $keyAns, 'void' => $void];
        }

        // ---- Phần III ----
        $p3 = ['score' => 0.0, 'max' => 0.0, 'correct' => 0, 'wrong' => 0, 'blank' => 0, 'invalid' => 0, 'total' => (int) $structure['p3']];
        for ($i = 1; $i <= $structure['p3']; $i++) {
            $k = $keys['p3.' . $i] ?? null;
            $max = ($k && $k['points'] !== null && $k['points'] !== '') ? (float) $k['points'] : $s['p3_point'];
            $keyAns = $k['answer'] ?? null;
            $void = $k && ((int) ($k['is_void'] ?? 0) === 1 || $keyAns === '*');
            $given = (string) ($answers['p3'][$i] ?? $answers['p3'][(string) $i] ?? '');
            [$valid, $val] = KeyFormat::validateP3($given, $len);
            $ok = $void || ($valid && $keyAns !== null && $keyAns !== '' && self::matchP3($val, $keyAns, $s['p3_compare']));
            $pts = $ok ? $max : 0.0;
            $p3['max'] += $max;
            $p3['score'] += $pts;
            if ($ok) {
                $p3['correct']++;
            } elseif (rtrim($given, '_') === '') {
                $p3['blank']++;
            } else {
                $p3['wrong']++;
                if (!$valid) {
                    $p3['invalid']++;
                }
            }
            $items['p3.' . $i] = ['ok' => $ok, 'pts' => $pts, 'max' => $max, 'given' => rtrim($given, '_'), 'key' => $keyAns, 'void' => $void, 'valid' => $valid, 'note' => $valid ? '' : $val];
        }

        // ---- Tự luận ----
        $es = ['score' => 0.0, 'max' => 0.0, 'graded' => 0, 'total' => count($structure['essay'] ?? [])];
        $pending = false;
        foreach (($structure['essay'] ?? []) as $idx => $e) {
            $n = $idx + 1;
            $k = $keys['e.' . $n] ?? null;
            $max = ($k && $k['points'] !== null && $k['points'] !== '') ? (float) $k['points'] : (float) $e['points'];
            $raw = $essayScores[$n] ?? $essayScores[(string) $n] ?? null;
            $has = $raw !== null && $raw !== '';
            $pts = $has ? max(0.0, min($max, (float) $raw)) : 0.0;
            if ($has) {
                $es['graded']++;
            } else {
                $pending = true;
            }
            $es['max'] += $max;
            $es['score'] += $pts;
            $items['e.' . $n] = ['ok' => $has && $pts >= $max, 'pts' => $pts, 'max' => $max, 'graded' => $has, 'given' => isset($answers['e'][$n]) ? mb_strlen((string) $answers['e'][$n]) : 0];
        }

        $rawMax = $p1['max'] + $p2['max'] + $p3['max'] + $es['max'];
        $raw = $p1['score'] + $p2['score'] + $p3['score'] + $es['score'];
        $unitsTotal = $p1['total'] + $p2['total_items'] + $p3['total'];
        $unitsCorrect = $p1['correct'] + $p2['correct_items'] + $p3['correct'];

        if ($maxOnly) {
            return ['raw_max' => round($rawMax, 4)];
        }

        $total = $raw;
        $max = $rawMax;
        if ($s['scheme'] === 'ratio') {
            $autoMax = max(0.0, $s['max_score'] - $es['max']);
            $ratio = $unitsTotal > 0 ? $unitsCorrect / $unitsTotal : 0;
            $unit = $unitsTotal > 0 ? $autoMax / $unitsTotal : 0;
            $p1['score'] = $p1['correct'] * $unit;
            $p1['max'] = $p1['total'] * $unit;
            $p2['score'] = $p2['correct_items'] * $unit;
            $p2['max'] = $p2['total_items'] * $unit;
            $p3['score'] = $p3['correct'] * $unit;
            $p3['max'] = $p3['total'] * $unit;
            $total = $ratio * $autoMax + $es['score'];
            $max = $s['max_score'];
        } elseif ($s['scale'] && $rawMax > 0) {
            $f = $s['max_score'] / $rawMax;
            foreach ([&$p1, &$p2, &$p3, &$es] as &$part) {
                $part['score'] *= $f;
                $part['max'] *= $f;
            }
            unset($part);
            $total = $raw * $f;
            $max = $s['max_score'];
        }
        if ($s['min_zero']) {
            $total = max(0.0, $total);
        }
        foreach ([&$p1, &$p2, &$p3, &$es] as &$part) {
            $part['score'] = round($part['score'], 4);
            $part['max'] = round($part['max'], 4);
        }
        unset($part);

        return [
            'score' => self::round($total, $s),
            'raw' => round($raw, 4),
            'raw_max' => round($rawMax, 4),
            'max' => round($max, 4),
            'pending' => $pending,
            'parts' => ['p1' => $p1, 'p2' => $p2, 'p3' => $p3, 'essay' => $es],
            'units_correct' => $unitsCorrect,
            'units_total' => $unitsTotal,
            'items' => $items,
        ];
    }

    public static function matchP3(string $given, string $keys, string $mode): bool
    {
        foreach (explode('|', $keys) as $alt) {
            $alt = trim($alt);
            if ($alt === '') {
                continue;
            }
            if ($mode === 'exact') {
                if (str_replace('.', ',', $alt) === str_replace('.', ',', $given)) {
                    return true;
                }
            } else {
                $a = KeyFormat::parseNumber($alt);
                $b = KeyFormat::parseNumber($given);
                if ($a !== null && $b !== null && abs($a - $b) < 1e-9) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function round(float $v, array $s): float
    {
        $step = (float) ($s['rounding'] ?? 0.01);
        if ($step <= 0) {
            return round($v, 4);
        }
        $q = $v / $step;
        switch ($s['round_mode'] ?? 'round') {
            case 'up':
                $q = ceil($q - 1e-9);
                break;
            case 'down':
                $q = floor($q + 1e-9);
                break;
            default:
                $q = round($q + ($q >= 0 ? 1e-9 : -1e-9));
        }
        return round($q * $step, 4);
    }

    /** Rút gọn kết quả để lưu vào attempts.score_detail (không lưu từng câu). */
    public static function summary(array $r): array
    {
        return [
            'score' => $r['score'],
            'max' => $r['max'],
            'raw' => $r['raw'],
            'pending' => $r['pending'],
            'units' => [$r['units_correct'], $r['units_total']],
            'parts' => $r['parts'],
        ];
    }

    /** Xếp loại theo ngưỡng trong cấu hình (thang 10). */
    public static function classify(?float $score, float $max = 10.0): string
    {
        if ($score === null) {
            return '';
        }
        $v = $max > 0 ? $score * 10 / $max : $score;
        if ($v >= (float) setting('grade_excellent', 8)) {
            return 'Giỏi';
        }
        if ($v >= (float) setting('grade_good', 6.5)) {
            return 'Khá';
        }
        if ($v >= (float) setting('grade_average', 5)) {
            return 'Trung bình';
        }
        if ($v >= (float) setting('grade_weak', 3.5)) {
            return 'Yếu';
        }
        return 'Kém';
    }
}

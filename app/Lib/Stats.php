<?php

namespace App\Lib;

use App\Core\App;

/**
 * Thống kê kết quả thi & phân tích câu hỏi (lý thuyết trắc nghiệm cổ điển):
 * độ khó p, độ phân biệt D (nhóm 27% cao – thấp), tương quan điểm – câu r(pb),
 * phân bố phương án, độ tin cậy Cronbach α của đề.
 */
final class Stats
{
    /**
     * Bài làm chính thức của từng học sinh trong một ca thi (khi được làm nhiều lượt thì lấy theo chính sách của ca).
     * @return array<int, array> mỗi phần tử là bài làm + thông tin học sinh
     */
    public static function officialAttempts(array $s, ?int $classId = null): array
    {
        $db = App::db();
        $sql = "SELECT a.*, u.full_name, u.code AS user_code, u.username, u.birthday, u.gender, u.class_id, u.sort_key, c.name AS class_name, c.sort_key AS class_sort, v.code AS variant_code
                FROM {attempts} a
                JOIN {users} u ON u.id = a.user_id
                LEFT JOIN {classes} c ON c.id = u.class_id
                LEFT JOIN {exam_variants} v ON v.id = a.variant_id
                WHERE a.session_id = ? AND a.status NOT IN ('in_progress', 'voided')";
        $params = [(int) $s['id']];
        if ($classId) {
            $sql .= ' AND u.class_id = ?';
            $params[] = $classId;
        }
        $byUser = [];
        foreach ($db->all($sql . ' ORDER BY a.attempt_no', $params) as $a) {
            $byUser[(int) $a['user_id']][] = $a;
        }
        $out = [];
        foreach ($byUser as $list) {
            $off = Sessions::officialAttempt($s, $list);
            if ($off) {
                $off['_count'] = count($list);
                $out[] = $off;
            }
        }
        usort($out, static fn($x, $y) => [(string) $x['class_sort'], (string) $x['class_name'], (string) $x['sort_key']] <=> [(string) $y['class_sort'], (string) $y['class_name'], (string) $y['sort_key']]);
        return $out;
    }

    /** Thống kê mô tả của một dãy số. */
    public static function describe(array $values): array
    {
        $v = array_values(array_map('floatval', array_filter($values, static fn($x) => $x !== null && $x !== '')));
        $n = count($v);
        if ($n === 0) {
            return ['n' => 0, 'mean' => null, 'median' => null, 'sd' => null, 'min' => null, 'max' => null, 'q1' => null, 'q3' => null, 'mode' => null];
        }
        sort($v);
        $mean = array_sum($v) / $n;
        $var = 0.0;
        foreach ($v as $x) {
            $var += ($x - $mean) ** 2;
        }
        $sd = $n > 1 ? sqrt($var / ($n - 1)) : 0.0;
        $counts = [];
        foreach ($v as $x) {
            $k = number_format($x, 2, '.', '');
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }
        arsort($counts);
        return [
            'n' => $n,
            'mean' => $mean,
            'median' => self::quantile($v, 0.5),
            'sd' => $sd,
            'min' => $v[0],
            'max' => $v[$n - 1],
            'q1' => self::quantile($v, 0.25),
            'q3' => self::quantile($v, 0.75),
            'mode' => (float) array_key_first($counts),
        ];
    }

    private static function quantile(array $sorted, float $q): float
    {
        $n = count($sorted);
        if ($n === 1) {
            return $sorted[0];
        }
        $pos = ($n - 1) * $q;
        $lo = (int) floor($pos);
        $hi = (int) ceil($pos);
        return $sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($pos - $lo);
    }

    /** Phổ điểm theo khoảng (mặc định bước 0,5 điểm trên thang 10 – giống phổ điểm thi tốt nghiệp). */
    public static function histogram(array $scores, float $max = 10.0, ?float $step = null): array
    {
        $step = $step ?? ($max <= 10 ? 0.5 : $max / 20);
        $bins = [];
        $nb = (int) ceil($max / $step);
        for ($i = 0; $i <= $nb; $i++) {
            $bins[] = ['x' => round($i * $step, 2), 'n' => 0];
        }
        foreach ($scores as $s) {
            if ($s === null) {
                continue;
            }
            // Làm tròn về mốc gần nhất (giống cách Bộ công bố phổ điểm theo từng mức điểm)
            $i = (int) round(min($max, max(0, (float) $s)) / $step);
            $bins[min($nb, $i)]['n']++;
        }
        return $bins;
    }

    /** Số lượng theo xếp loại (thang 10). */
    public static function classification(array $attempts): array
    {
        $out = ['Giỏi' => 0, 'Khá' => 0, 'Trung bình' => 0, 'Yếu' => 0, 'Kém' => 0];
        foreach ($attempts as $a) {
            if ($a['score'] === null) {
                continue;
            }
            $max = (float) (json_dec($a['score_detail'], [])['max'] ?? 10);
            $c = Scoring::classify((float) $a['score'], $max ?: 10);
            if (isset($out[$c])) {
                $out[$c]++;
            }
        }
        return $out;
    }

    /** Tổng hợp theo lớp. */
    public static function byClass(array $attempts): array
    {
        $g = [];
        foreach ($attempts as $a) {
            $k = (string) ($a['class_name'] ?? '—') ?: '—';
            $g[$k][] = $a;
        }
        $out = [];
        foreach ($g as $name => $list) {
            $scores = array_map(static fn($a) => $a['score'] !== null ? (float) $a['score'] : null, $list);
            $d = self::describe($scores);
            $max = (float) (json_dec($list[0]['score_detail'], [])['max'] ?? 10) ?: 10;
            $pass = count(array_filter($scores, static fn($x) => $x !== null && $x * 10 / $max >= 5));
            $good = count(array_filter($scores, static fn($x) => $x !== null && $x * 10 / $max >= 8));
            $out[] = ['name' => $name, 'n' => $d['n'], 'mean' => $d['mean'], 'sd' => $d['sd'], 'min' => $d['min'], 'max' => $d['max'], 'median' => $d['median'],
                'pass' => $d['n'] ? $pass / $d['n'] : 0, 'good' => $d['n'] ? $good / $d['n'] : 0];
        }
        usort($out, static fn($x, $y) => strnatcasecmp($x['name'], $y['name']));
        return $out;
    }

    /** Tỉ lệ điểm đạt được trung bình của từng phần (I, II, III, tự luận). */
    public static function parts(array $attempts): array
    {
        $sum = [];
        foreach ($attempts as $a) {
            $parts = json_dec($a['score_detail'], [])['parts'] ?? [];
            foreach (['p1', 'p2', 'p3', 'essay'] as $p) {
                if (!empty($parts[$p]['total']) && ($parts[$p]['max'] ?? 0) > 0) {
                    $sum[$p]['score'] = ($sum[$p]['score'] ?? 0) + (float) $parts[$p]['score'];
                    $sum[$p]['max'] = ($sum[$p]['max'] ?? 0) + (float) $parts[$p]['max'];
                    $sum[$p]['n'] = ($sum[$p]['n'] ?? 0) + 1;
                }
            }
        }
        $out = [];
        foreach ($sum as $p => $s) {
            $out[$p] = ['avg' => $s['score'] / $s['n'], 'max' => $s['max'] / $s['n'], 'ratio' => $s['max'] > 0 ? $s['score'] / $s['max'] : 0, 'n' => $s['n']];
        }
        return $out;
    }

    /**
     * Phân tích câu hỏi theo từng mã đề.
     * @return array<int, array{variant: array, n: int, alpha: ?float, items: array}>
     */
    public static function items(array $attempts, array $exam): array
    {
        $structure = $exam['_structure'];
        $scoring = $exam['_scoring'];
        $byVar = [];
        foreach ($attempts as $a) {
            $byVar[(int) $a['variant_id']][] = $a;
        }
        $variants = App::db()->all('SELECT id, code FROM {exam_variants} WHERE exam_id = ? ORDER BY sort_order, code', [(int) $exam['id']]);
        $out = [];
        foreach ($variants as $v) {
            $list = $byVar[(int) $v['id']] ?? [];
            if (!$list) {
                continue;
            }
            $keys = Attempts::keys((int) $v['id']);
            $rows = [];
            foreach ($list as $a) {
                $r = Scoring::compute($structure, $scoring, $keys, json_dec($a['answers'], []), json_dec($a['essay_scores'] ?? null, []));
                $rows[] = ['total' => $a['score'] !== null ? (float) $a['score'] : (float) $r['score'], 'items' => $r['items']];
            }
            usort($rows, static fn($x, $y) => $y['total'] <=> $x['total']);
            $n = count($rows);
            $g = max(1, (int) round($n * 0.27));
            $totals = array_column($rows, 'total');
            $mean = array_sum($totals) / $n;
            $sdT = self::sd($totals);
            $items = [];
            $itemVars = [];
            $matrix = [];
            foreach (ExamFormat::questionIds($structure) as $qid) {
                $part = explode('.', $qid)[0];
                $k = $keys[$qid] ?? null;
                $vals = [];
                $opt = [];
                $subs = [0, 0, 0, 0];
                $full = 0;
                $blank = 0;
                foreach ($rows as $row) {
                    $it = $row['items'][$qid] ?? null;
                    if (!$it) {
                        $vals[] = 0.0;
                        continue;
                    }
                    if ($part === 'p1') {
                        $vals[] = $it['ok'] ? 1.0 : 0.0;
                        $gv = $it['given'] !== '' ? $it['given'] : '–';
                        $opt[$gv] = ($opt[$gv] ?? 0) + 1;
                        if ($it['given'] === '') {
                            $blank++;
                        }
                    } elseif ($part === 'p2') {
                        $vals[] = ($it['k'] ?? 0) / 4;
                        foreach (($it['subs'] ?? []) as $j => $ok) {
                            if ($ok) {
                                $subs[$j]++;
                            }
                        }
                        if (($it['k'] ?? 0) === 4) {
                            $full++;
                        }
                        if (trim((string) $it['given'], '_') === '') {
                            $blank++;
                        }
                    } elseif ($part === 'p3') {
                        $vals[] = $it['ok'] ? 1.0 : 0.0;
                        $gv = (string) $it['given'] !== '' ? (string) $it['given'] : '–';
                        $opt[$gv] = ($opt[$gv] ?? 0) + 1;
                        if ((string) $it['given'] === '') {
                            $blank++;
                        }
                    } else {
                        $vals[] = ($it['max'] ?? 0) > 0 ? (float) $it['pts'] / (float) $it['max'] : 0.0;
                    }
                }
                $p = array_sum($vals) / $n;
                $up = array_sum(array_slice($vals, 0, $g)) / $g;
                $lo = array_sum(array_slice($vals, -$g)) / $g;
                $d = $n >= 4 ? $up - $lo : null;
                $rpb = self::corr($vals, $totals, $sdT);
                $itemVars[] = self::variance($vals);
                $matrix[$qid] = $vals;
                arsort($opt);
                $flags = [];
                if ($n >= 5) {
                    if ($p >= 0.9) {
                        $flags[] = ['Rất dễ', 'info'];
                    } elseif ($p <= 0.2) {
                        $flags[] = ['Rất khó', 'warning'];
                    }
                    if ($d !== null && $d < 0) {
                        $flags[] = ['Phân biệt âm – kiểm tra lại đáp án', 'danger'];
                    } elseif ($d !== null && $d < 0.2 && $p < 0.9) {
                        $flags[] = ['Phân biệt kém', 'warning'];
                    } elseif ($d !== null && $d >= 0.4) {
                        $flags[] = ['Phân biệt tốt', 'success'];
                    }
                    if ($part === 'p1' && $k && strlen((string) $k['answer']) === 1) {
                        $keyN = $opt[$k['answer']] ?? 0;
                        foreach ($opt as $o => $cnt) {
                            if ($o !== $k['answer'] && $o !== '–' && $cnt > $keyN) {
                                $flags[] = ['Phương án ' . $o . ' được chọn nhiều hơn đáp án', 'danger'];
                                break;
                            }
                        }
                    }
                }
                if ($k && (int) $k['is_void'] === 1) {
                    $flags[] = ['Câu đã hủy', 'default'];
                }
                $items[$qid] = [
                    'qid' => $qid,
                    'part' => $part,
                    'label' => ExamFormat::labelOf($qid),
                    'key' => $k['answer'] ?? null,
                    'level' => $k['level'] ?? null,
                    'topic' => $k['topic'] ?? null,
                    'p' => $p,
                    'd' => $d,
                    'rpb' => $rpb,
                    'blank' => $blank,
                    'options' => $part === 'p3' ? array_slice($opt, 0, 6, true) : $opt,
                    'subs' => $part === 'p2' ? array_map(static fn($c) => $c / $n, $subs) : null,
                    'full' => $part === 'p2' ? $full / $n : null,
                    'flags' => $flags,
                ];
            }
            // Độ tin cậy Cronbach α (theo điểm chuẩn hóa từng câu 0…1)
            $kItems = count($itemVars);
            $person = array_fill(0, $n, 0.0);
            foreach ($matrix as $vals) {
                foreach ($vals as $i => $x) {
                    $person[$i] += $x;
                }
            }
            $varP = self::variance($person);
            $alpha = ($n >= 5 && $kItems > 1 && $varP > 0) ? ($kItems / ($kItems - 1)) * (1 - array_sum($itemVars) / $varP) : null;
            $out[] = ['variant' => $v, 'n' => $n, 'mean' => $mean, 'alpha' => $alpha !== null && is_finite($alpha) ? max(-1.0, $alpha) : null, 'items' => $items];
        }
        return $out;
    }

    /** Tổng hợp độ khó theo mức độ nhận thức / chủ đề. */
    public static function byTag(array $items, string $field): array
    {
        $g = [];
        foreach ($items as $it) {
            $tag = trim((string) ($it[$field] ?? ''));
            if ($tag === '') {
                continue;
            }
            $g[$tag][] = $it['p'];
        }
        $out = [];
        foreach ($g as $tag => $ps) {
            $out[] = ['tag' => $tag, 'n' => count($ps), 'p' => array_sum($ps) / count($ps)];
        }
        usort($out, static fn($a, $b) => $b['n'] <=> $a['n']);
        return $out;
    }

    public static function variance(array $v): float
    {
        $n = count($v);
        if ($n < 2) {
            return 0.0;
        }
        $m = array_sum($v) / $n;
        $s = 0.0;
        foreach ($v as $x) {
            $s += ($x - $m) ** 2;
        }
        return $s / ($n - 1);
    }

    public static function sd(array $v): float
    {
        return sqrt(self::variance($v));
    }

    /** Hệ số tương quan Pearson (với điểm câu 0/1 chính là tương quan điểm – câu point-biserial). */
    public static function corr(array $x, array $y, ?float $sdY = null): ?float
    {
        $n = count($x);
        if ($n < 5) {
            return null;
        }
        $sx = self::sd($x);
        $sy = $sdY ?? self::sd($y);
        if ($sx <= 0 || $sy <= 0) {
            return null;
        }
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $c = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $c += ($x[$i] - $mx) * ($y[$i] - $my);
        }
        return ($c / ($n - 1)) / ($sx * $sy);
    }

    /** Nhận xét độ khó theo p. */
    public static function difficultyLabel(float $p): array
    {
        if ($p >= 0.8) {
            return ['Dễ', 'success'];
        }
        if ($p >= 0.6) {
            return ['Trung bình dễ', 'info'];
        }
        if ($p >= 0.4) {
            return ['Trung bình', 'primary'];
        }
        if ($p >= 0.2) {
            return ['Khó', 'warning'];
        }
        return ['Rất khó', 'danger'];
    }

    public static function alphaLabel(?float $a): string
    {
        if ($a === null) {
            return 'Chưa đủ dữ liệu';
        }
        return $a >= 0.9 ? 'Rất cao' : ($a >= 0.8 ? 'Cao' : ($a >= 0.7 ? 'Chấp nhận được' : ($a >= 0.6 ? 'Hơi thấp' : 'Thấp')));
    }
}

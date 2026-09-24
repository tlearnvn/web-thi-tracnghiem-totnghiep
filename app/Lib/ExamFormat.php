<?php

namespace App\Lib;

/**
 * Định dạng đề thi tốt nghiệp THPT từ năm 2025 (Chương trình GDPT 2018).
 *
 *  Phần I   – Trắc nghiệm nhiều phương án lựa chọn (A, B, C, D): 0,25 điểm/câu.
 *  Phần II  – Trắc nghiệm đúng/sai, mỗi câu 4 ý a) b) c) d):
 *             đúng 1 ý 0,1đ; 2 ý 0,25đ; 3 ý 0,5đ; 4 ý 1,0đ.
 *  Phần III – Trắc nghiệm trả lời ngắn (tô tối đa 4 ký tự: dấu "−", dấu phẩy, chữ số):
 *             Toán 0,5đ/câu; Vật lí, Hóa học, Sinh học, Địa lí 0,25đ/câu.
 *  Ngữ văn  – Tự luận (giáo viên chấm).
 */
final class ExamFormat
{
    public const PRESETS = [
        'TOAN' => ['name' => 'Toán', 'short' => 'Toán', 'duration' => 90, 'p1' => 12, 'p2' => 4, 'p3' => 6, 'p3_point' => 0.5, 'color' => '#2563eb'],
        'NGUVAN' => ['name' => 'Ngữ văn', 'short' => 'Văn', 'duration' => 120, 'p1' => 0, 'p2' => 0, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#be123c',
            'essay' => [
                ['label' => 'Câu 1', 'points' => 0.5, 'hint' => 'Đọc hiểu'],
                ['label' => 'Câu 2', 'points' => 0.5, 'hint' => 'Đọc hiểu'],
                ['label' => 'Câu 3', 'points' => 1.0, 'hint' => 'Đọc hiểu'],
                ['label' => 'Câu 4', 'points' => 1.0, 'hint' => 'Đọc hiểu'],
                ['label' => 'Câu 5', 'points' => 1.0, 'hint' => 'Đọc hiểu'],
                ['label' => 'Viết – Câu 1', 'points' => 2.0, 'hint' => 'Đoạn văn nghị luận (khoảng 200 chữ)'],
                ['label' => 'Viết – Câu 2', 'points' => 4.0, 'hint' => 'Bài văn nghị luận (khoảng 600 chữ)'],
            ]],
        'VATLI' => ['name' => 'Vật lí', 'short' => 'Lí', 'duration' => 50, 'p1' => 18, 'p2' => 4, 'p3' => 6, 'p3_point' => 0.25, 'color' => '#7c3aed'],
        'HOAHOC' => ['name' => 'Hóa học', 'short' => 'Hóa', 'duration' => 50, 'p1' => 18, 'p2' => 4, 'p3' => 6, 'p3_point' => 0.25, 'color' => '#0891b2'],
        'SINHHOC' => ['name' => 'Sinh học', 'short' => 'Sinh', 'duration' => 50, 'p1' => 18, 'p2' => 4, 'p3' => 6, 'p3_point' => 0.25, 'color' => '#16a34a'],
        'LICHSU' => ['name' => 'Lịch sử', 'short' => 'Sử', 'duration' => 50, 'p1' => 24, 'p2' => 4, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#b45309'],
        'DIALI' => ['name' => 'Địa lí', 'short' => 'Địa', 'duration' => 50, 'p1' => 18, 'p2' => 4, 'p3' => 6, 'p3_point' => 0.25, 'color' => '#0d9488'],
        'GDKTPL' => ['name' => 'Giáo dục kinh tế và pháp luật', 'short' => 'GDKT&PL', 'duration' => 50, 'p1' => 24, 'p2' => 4, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#9333ea'],
        'TINHOC' => ['name' => 'Tin học', 'short' => 'Tin', 'duration' => 50, 'p1' => 24, 'p2' => 4, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#4f46e5'],
        'CNCN' => ['name' => 'Công nghệ – Công nghiệp', 'short' => 'CN-CN', 'duration' => 50, 'p1' => 24, 'p2' => 4, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#ea580c'],
        'CNNN' => ['name' => 'Công nghệ – Nông nghiệp', 'short' => 'CN-NN', 'duration' => 50, 'p1' => 24, 'p2' => 4, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#65a30d'],
        'TIENGANH' => ['name' => 'Tiếng Anh', 'short' => 'Anh', 'duration' => 50, 'p1' => 40, 'p2' => 0, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#db2777'],
        'TIENGPHAP' => ['name' => 'Tiếng Pháp', 'short' => 'Pháp', 'duration' => 50, 'p1' => 40, 'p2' => 0, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#1d4ed8'],
        'TIENGTRUNG' => ['name' => 'Tiếng Trung Quốc', 'short' => 'Trung', 'duration' => 50, 'p1' => 40, 'p2' => 0, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#dc2626'],
        'TIENGNHAT' => ['name' => 'Tiếng Nhật', 'short' => 'Nhật', 'duration' => 50, 'p1' => 40, 'p2' => 0, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#e11d48'],
        'TIENGHAN' => ['name' => 'Tiếng Hàn', 'short' => 'Hàn', 'duration' => 50, 'p1' => 40, 'p2' => 0, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#0369a1'],
        'TIENGDUC' => ['name' => 'Tiếng Đức', 'short' => 'Đức', 'duration' => 50, 'p1' => 40, 'p2' => 0, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#57534e'],
        'TIENGNGA' => ['name' => 'Tiếng Nga', 'short' => 'Nga', 'duration' => 50, 'p1' => 40, 'p2' => 0, 'p3' => 0, 'p3_point' => 0.25, 'color' => '#475569'],
    ];

    public const MAX_P1 = 120;
    public const MAX_P2 = 30;
    public const MAX_P3 = 30;
    public const MAX_ESSAY = 20;
    public const P2_ITEMS = ['a', 'b', 'c', 'd'];

    public static function structureFromPreset(array $p): array
    {
        return self::normalizeStructure([
            'p1' => $p['p1'] ?? 0,
            'p2' => $p['p2'] ?? 0,
            'p3' => $p['p3'] ?? 0,
            'p3_len' => 4,
            'essay' => $p['essay'] ?? [],
        ]);
    }

    public static function scoringFromPreset(array $p): array
    {
        return Scoring::normalize(['scheme' => 'moet2025', 'p3_point' => $p['p3_point'] ?? 0.25]);
    }

    public static function normalizeStructure($s): array
    {
        $s = is_array($s) ? $s : json_dec($s, []);
        $essay = [];
        foreach (array_slice(is_array($s['essay'] ?? null) ? $s['essay'] : [], 0, self::MAX_ESSAY) as $i => $e) {
            $label = trim((string) ($e['label'] ?? ''));
            $essay[] = [
                'label' => $label !== '' ? mb_substr($label, 0, 60) : 'Câu ' . ($i + 1),
                'points' => max(0, round((float) ($e['points'] ?? 1), 2)),
                'hint' => mb_substr(trim((string) ($e['hint'] ?? '')), 0, 120),
            ];
        }
        return [
            'p1' => max(0, min(self::MAX_P1, (int) ($s['p1'] ?? 0))),
            'p2' => max(0, min(self::MAX_P2, (int) ($s['p2'] ?? 0))),
            'p3' => max(0, min(self::MAX_P3, (int) ($s['p3'] ?? 0))),
            'p3_len' => max(3, min(8, (int) ($s['p3_len'] ?? 4))),
            'essay' => $essay,
        ];
    }

    public static function describe(array $s): string
    {
        $parts = [];
        if ($s['p1'] > 0) {
            $parts[] = $s['p1'] . ' câu TN';
        }
        if ($s['p2'] > 0) {
            $parts[] = $s['p2'] . ' câu Đ/S';
        }
        if ($s['p3'] > 0) {
            $parts[] = $s['p3'] . ' câu TLN';
        }
        if (!empty($s['essay'])) {
            $parts[] = count($s['essay']) . ' câu tự luận';
        }
        return $parts ? implode(' · ', $parts) : 'Chưa có câu hỏi';
    }

    public static function totalQuestions(array $s): int
    {
        return $s['p1'] + $s['p2'] + $s['p3'] + count($s['essay'] ?? []);
    }

    /** Danh sách mã câu: p1.1 … p2.1 … p3.1 … e.1 */
    public static function questionIds(array $s): array
    {
        $ids = [];
        for ($i = 1; $i <= $s['p1']; $i++) {
            $ids[] = 'p1.' . $i;
        }
        for ($i = 1; $i <= $s['p2']; $i++) {
            $ids[] = 'p2.' . $i;
        }
        for ($i = 1; $i <= $s['p3']; $i++) {
            $ids[] = 'p3.' . $i;
        }
        foreach (array_keys($s['essay'] ?? []) as $i) {
            $ids[] = 'e.' . ($i + 1);
        }
        return $ids;
    }

    public static function partName(int $part): string
    {
        return [1 => 'Phần I', 2 => 'Phần II', 3 => 'Phần III', 4 => 'Tự luận'][$part] ?? ('Phần ' . $part);
    }

    public static function partLong(int $part): string
    {
        return [
            1 => 'Trắc nghiệm nhiều phương án lựa chọn',
            2 => 'Trắc nghiệm đúng / sai',
            3 => 'Trắc nghiệm trả lời ngắn',
            4 => 'Tự luận',
        ][$part] ?? '';
    }

    /** "p2.3" -> "Phần II – Câu 3" */
    public static function labelOf(string $qid): string
    {
        [$p, $n] = array_pad(explode('.', $qid), 2, '');
        $map = ['p1' => 'Phần I', 'p2' => 'Phần II', 'p3' => 'Phần III', 'e' => 'Tự luận'];
        return ($map[$p] ?? $p) . ' – Câu ' . $n;
    }

    public static function presetList(): array
    {
        $out = [];
        foreach (self::PRESETS as $code => $p) {
            $out[$code] = $p['name'];
        }
        return $out;
    }
}

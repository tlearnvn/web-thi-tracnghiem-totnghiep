<?php

namespace App\Core;

final class Paginator
{
    public int $page;
    public int $perPage;
    public int $total;
    public int $pages;
    public int $offset;

    public function __construct(int $total, int $perPage = 25, ?int $page = null)
    {
        $this->total = max(0, $total);
        $this->perPage = max(1, min(500, $perPage));
        $this->pages = max(1, (int) ceil($this->total / $this->perPage));
        $p = $page ?? (int) ($_GET['page'] ?? 1);
        $this->page = max(1, min($this->pages, $p));
        $this->offset = ($this->page - 1) * $this->perPage;
    }

    public static function perPageFromRequest(int $default = 25): int
    {
        $pp = (int) ($_GET['per_page'] ?? $default);
        return in_array($pp, [10, 25, 50, 100, 200, 500], true) ? $pp : $default;
    }

    public function sqlLimit(): string
    {
        return ' LIMIT ' . $this->perPage . ' OFFSET ' . $this->offset;
    }

    public function summary(): string
    {
        if ($this->total === 0) {
            return 'Không có dữ liệu';
        }
        $from = $this->offset + 1;
        $to = min($this->total, $this->offset + $this->perPage);
        return 'Hiển thị ' . $from . '–' . $to . ' / ' . number_format($this->total, 0, ',', '.');
    }

    public function links(): string
    {
        $html = '<div class="pager"><span class="pager-info">' . e($this->summary()) . '</span>';
        if ($this->pages > 1) {
            $html .= '<nav class="pager-links" aria-label="Phân trang">';
            $html .= $this->link($this->page - 1, icon('chevron-left'), $this->page <= 1, 'Trang trước');
            $window = [];
            for ($i = 1; $i <= $this->pages; $i++) {
                if ($i === 1 || $i === $this->pages || abs($i - $this->page) <= 2) {
                    $window[] = $i;
                }
            }
            $prev = 0;
            foreach ($window as $i) {
                if ($prev && $i - $prev > 1) {
                    $html .= '<span class="pager-gap">…</span>';
                }
                $html .= $i === $this->page
                    ? '<span class="pager-cur" aria-current="page">' . $i . '</span>'
                    : $this->link($i, (string) $i, false, 'Trang ' . $i);
                $prev = $i;
            }
            $html .= $this->link($this->page + 1, icon('chevron-right'), $this->page >= $this->pages, 'Trang sau');
            $html .= '</nav>';
        }
        return $html . '</div>';
    }

    private function link(int $page, string $label, bool $disabled, string $title): string
    {
        if ($disabled) {
            return '<span class="pager-btn is-disabled">' . $label . '</span>';
        }
        return '<a class="pager-btn" href="' . e(query_with(['page' => $page])) . '" title="' . e($title) . '">' . $label . '</a>';
    }
}

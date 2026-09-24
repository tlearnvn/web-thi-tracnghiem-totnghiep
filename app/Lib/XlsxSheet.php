<?php

namespace App\Lib;

final class XlsxSheet
{
    public string $name;
    private array $rows = [];
    private array $widths = [];
    public ?string $filterRef = null;
    private ?string $freeze = null;
    private array $merges = [];
    public ?array $printTitles = null;
    private bool $landscape = false;
    private int $maxCol = 0;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /** Độ rộng các cột (đơn vị ký tự). */
    public function widths(array $widths): self
    {
        $this->widths = array_values($widths);
        return $this;
    }

    /**
     * Thêm một dòng. Ô có thể là giá trị thường hoặc mảng ['v' => ..., 's' => kiểu, 'f' => công thức].
     * Chuỗi luôn được giữ nguyên dạng văn bản (không mất số 0 đầu của SBD, mã đề).
     */
    public function row(array $cells, ?int $style = null, array $cellStyles = [], ?float $height = null): int
    {
        $this->rows[] = ['cells' => array_values($cells), 'style' => $style, 'styles' => $cellStyles, 'height' => $height];
        $this->maxCol = max($this->maxCol, count($cells));
        return count($this->rows);
    }

    public function blank(): int
    {
        return $this->row([]);
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    /** Cố định vùng: 'A2' = cố định dòng 1; 'C2' = cố định dòng 1 và cột A–B. */
    public function freeze(string $cell): self
    {
        $this->freeze = $cell;
        return $this;
    }

    public function autoFilter(string $ref): self
    {
        $this->filterRef = $ref;
        return $this;
    }

    public function merge(string $ref): self
    {
        $this->merges[] = $ref;
        return $this;
    }

    public function landscape(bool $v = true): self
    {
        $this->landscape = $v;
        return $this;
    }

    public function printTitles(int $fromRow, int $toRow): self
    {
        $this->printTitles = [$fromRow, $toRow];
        return $this;
    }

    public function xml(): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
        $lastRow = max(1, count($this->rows));
        $lastCol = XlsxWriter::col(max(0, $this->maxCol - 1));
        $x .= '<dimension ref="A1:' . $lastCol . $lastRow . '"/>';
        $x .= '<sheetViews><sheetView workbookViewId="0"' . ($this->freeze ? '' : ' tabSelected="0"') . '>';
        if ($this->freeze && preg_match('/^([A-Z]+)(\d+)$/', $this->freeze, $m)) {
            $colIdx = self::colIndex($m[1]);
            $rowIdx = (int) $m[2] - 1;
            $pane = '<pane';
            if ($colIdx > 0) {
                $pane .= ' xSplit="' . $colIdx . '"';
            }
            if ($rowIdx > 0) {
                $pane .= ' ySplit="' . $rowIdx . '"';
            }
            $active = $colIdx > 0 && $rowIdx > 0 ? 'bottomRight' : ($rowIdx > 0 ? 'bottomLeft' : 'topRight');
            $pane .= ' topLeftCell="' . $this->freeze . '" activePane="' . $active . '" state="frozen"/>';
            $x .= $pane . '<selection pane="' . $active . '" activeCell="' . $this->freeze . '" sqref="' . $this->freeze . '"/>';
        }
        $x .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="16"/>';
        if ($this->widths) {
            $x .= '<cols>';
            foreach ($this->widths as $i => $w) {
                $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $w . '" customWidth="1"/>';
            }
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        foreach ($this->rows as $ri => $row) {
            $r = $ri + 1;
            $x .= '<row r="' . $r . '"' . ($row['height'] ? ' ht="' . $row['height'] . '" customHeight="1"' : '') . '>';
            foreach ($row['cells'] as $ci => $cell) {
                $ref = XlsxWriter::col($ci) . $r;
                $style = $row['styles'][$ci] ?? $row['style'];
                $value = $cell;
                $formula = null;
                if (is_array($cell)) {
                    $value = $cell['v'] ?? null;
                    $style = $cell['s'] ?? $style;
                    $formula = $cell['f'] ?? null;
                }
                $s = $style ? ' s="' . (int) $style . '"' : '';
                if ($formula !== null) {
                    $x .= '<c r="' . $ref . '"' . $s . '><f>' . XlsxWriter::x((string) $formula) . '</f></c>';
                } elseif ($value === null || $value === '') {
                    if ($style) {
                        $x .= '<c r="' . $ref . '"' . $s . '/>';
                    }
                } elseif (is_bool($value)) {
                    $x .= '<c r="' . $ref . '"' . $s . ' t="b"><v>' . ($value ? 1 : 0) . '</v></c>';
                } elseif (is_int($value) || is_float($value)) {
                    if (is_float($value) && (is_nan($value) || is_infinite($value))) {
                        $x .= '<c r="' . $ref . '"' . $s . '/>';
                    } else {
                        $x .= '<c r="' . $ref . '"' . $s . '><v>' . (is_float($value) ? self::num($value) : $value) . '</v></c>';
                    }
                } else {
                    $str = (string) $value;
                    $x .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . XlsxWriter::x($str) . '</t></is></c>';
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if ($this->filterRef) {
            $x .= '<autoFilter ref="' . $this->filterRef . '"/>';
        }
        if ($this->merges) {
            $x .= '<mergeCells count="' . count($this->merges) . '">';
            foreach ($this->merges as $m) {
                $x .= '<mergeCell ref="' . $m . '"/>';
            }
            $x .= '</mergeCells>';
        }
        $x .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>';
        $x .= '<pageSetup paperSize="9" orientation="' . ($this->landscape ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/>';
        return $x . '</worksheet>';
    }

    private static function num(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
        return $s === '-0' || $s === '' ? '0' : $s;
    }

    public static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }
}

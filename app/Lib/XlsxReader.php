<?php

namespace App\Lib;

/**
 * Đọc tệp Excel .xlsx (và CSV) bằng PHP thuần.
 * Trả về các dòng dạng [số dòng => [chỉ số cột => giá trị]], ô ngày tháng được đổi sang "Y-m-d".
 */
final class XlsxReader
{
    private ZipReader $zip;
    private array $sheets = [];      // [['name' => ..., 'path' => ...]]
    private array $shared = [];
    private array $dateStyles = [];  // chỉ số style => true nếu là định dạng ngày

    private function __construct(ZipReader $zip)
    {
        $this->zip = $zip;
        $this->loadWorkbook();
        $this->loadSharedStrings();
        $this->loadStyles();
    }

    public static function fromString(string $data): self
    {
        return new self(ZipReader::fromString($data));
    }

    /**
     * Đọc tệp bảng tính bất kỳ (xlsx hoặc csv).
     * @return array{sheets: string[], rows: array}
     */
    public static function readAny(string $data, int $sheet = 0): array
    {
        if (ZipReader::looksLikeZip($data)) {
            $r = self::fromString($data);
            return ['sheets' => $r->sheetNames(), 'rows' => $r->rows($sheet)];
        }
        if (strncmp($data, "\xD0\xCF\x11\xE0", 4) === 0) {
            throw new \RuntimeException('Đây là tệp Excel cũ (.xls). Vui lòng mở bằng Excel và "Lưu thành" định dạng .xlsx rồi tải lên lại.');
        }
        return ['sheets' => ['CSV'], 'rows' => self::readCsv($data)];
    }

    public function sheetNames(): array
    {
        return array_column($this->sheets, 'name');
    }

    private static function xml(string $s): ?\SimpleXMLElement
    {
        $prev = libxml_use_internal_errors(true);
        $x = simplexml_load_string($s, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $x === false ? null : $x;
    }

    private function loadWorkbook(): void
    {
        $wb = $this->zip->get('xl/workbook.xml');
        if ($wb === null) {
            throw new \RuntimeException('Tệp không phải bảng tính Excel (.xlsx) hợp lệ.');
        }
        $rels = [];
        $relXml = $this->zip->get('xl/_rels/workbook.xml.rels');
        if ($relXml !== null && ($rx = self::xml($relXml))) {
            foreach ($rx->Relationship as $r) {
                $target = (string) $r['Target'];
                $target = strpos($target, '/') === 0 ? ltrim($target, '/') : 'xl/' . $target;
                $rels[(string) $r['Id']] = $target;
            }
        }
        $x = self::xml($wb);
        if (!$x) {
            throw new \RuntimeException('Không đọc được cấu trúc bảng tính.');
        }
        $i = 0;
        foreach ($x->sheets->sheet as $s) {
            $i++;
            $rid = '';
            foreach ($s->attributes('r', true) as $k => $v) {
                if ($k === 'id') {
                    $rid = (string) $v;
                }
            }
            $path = $rels[$rid] ?? ('xl/worksheets/sheet' . $i . '.xml');
            $this->sheets[] = ['name' => (string) $s['name'], 'path' => $path];
        }
    }

    private function loadSharedStrings(): void
    {
        $s = $this->zip->get('xl/sharedStrings.xml');
        if ($s === null) {
            return;
        }
        $x = self::xml($s);
        if (!$x) {
            return;
        }
        foreach ($x->si as $si) {
            $this->shared[] = self::richText($si);
        }
    }

    private static function richText(\SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $out = '';
        foreach ($node->r as $r) {
            $out .= (string) $r->t;
        }
        return $out;
    }

    private function loadStyles(): void
    {
        $s = $this->zip->get('xl/styles.xml');
        if ($s === null) {
            return;
        }
        $x = self::xml($s);
        if (!$x) {
            return;
        }
        $custom = [];
        if (isset($x->numFmts)) {
            foreach ($x->numFmts->numFmt as $f) {
                $custom[(int) $f['numFmtId']] = (string) $f['formatCode'];
            }
        }
        if (!isset($x->cellXfs)) {
            return;
        }
        $i = 0;
        foreach ($x->cellXfs->xf as $xf) {
            $id = (int) $xf['numFmtId'];
            $isDate = ($id >= 14 && $id <= 22) || ($id >= 45 && $id <= 47) || ($id >= 27 && $id <= 36) || ($id >= 50 && $id <= 58);
            if (!$isDate && isset($custom[$id])) {
                $code = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', $custom[$id]);
                $isDate = (bool) preg_match('/[dmyhs]/i', (string) $code) && !preg_match('/^[#0.,%E+\-\s]+$/i', (string) $code);
            }
            if ($isDate) {
                $this->dateStyles[$i] = true;
            }
            $i++;
        }
    }

    /** @return array<int, array<int, string|float|int>> */
    public function rows(int $sheetIndex = 0): array
    {
        $sheet = $this->sheets[$sheetIndex] ?? ($this->sheets[0] ?? null);
        if (!$sheet) {
            return [];
        }
        $data = $this->zip->get($sheet['path']);
        if ($data === null) {
            return [];
        }
        $x = self::xml($data);
        if (!$x || !isset($x->sheetData)) {
            return [];
        }
        $rows = [];
        $autoRow = 0;
        foreach ($x->sheetData->row as $row) {
            $rn = isset($row['r']) ? (int) $row['r'] : $autoRow + 1;
            $autoRow = $rn;
            $cells = [];
            $autoCol = -1;
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                if ($ref !== '' && preg_match('/^([A-Z]+)/', $ref, $m)) {
                    $col = XlsxSheet::colIndex($m[1]);
                } else {
                    $col = $autoCol + 1;
                }
                $autoCol = $col;
                $t = (string) $c['t'];
                $v = isset($c->v) ? (string) $c->v : null;
                switch ($t) {
                    case 's':
                        $val = $this->shared[(int) $v] ?? '';
                        break;
                    case 'inlineStr':
                        $val = isset($c->is) ? self::richText($c->is) : '';
                        break;
                    case 'str':
                    case 'e':
                        $val = (string) $v;
                        break;
                    case 'b':
                        $val = $v === '1' ? 'TRUE' : 'FALSE';
                        break;
                    case 'd':
                        $val = substr((string) $v, 0, 10);
                        break;
                    default:
                        if ($v === null || $v === '') {
                            $val = '';
                        } elseif (isset($this->dateStyles[(int) $c['s']]) && is_numeric($v)) {
                            $val = self::serialToDate((float) $v);
                        } elseif (is_numeric($v)) {
                            // Giữ nguyên chuỗi số gốc (tránh 0.30000000000000004)
                            $val = strpos($v, 'E') === false && strpos($v, 'e') === false ? $v : (string) (float) $v;
                        } else {
                            $val = $v;
                        }
                }
                $cells[$col] = is_string($val) ? Text::trimCell($val) : $val;
            }
            if ($cells) {
                ksort($cells);
                $rows[$rn] = $cells;
            }
        }
        return $rows;
    }

    public static function serialToDate(float $serial): string
    {
        $days = (int) floor($serial);
        $secs = (int) round(($serial - $days) * 86400);
        $ts = ($days - 25569) * 86400 + $secs;
        return $secs > 0 ? gmdate('Y-m-d H:i', $ts) : gmdate('Y-m-d', $ts);
    }

    /** Đọc CSV: tự nhận dạng mã hóa (UTF-8/UTF-16/Windows-1258) và dấu phân cách. */
    public static function readCsv(string $data): array
    {
        if (strncmp($data, "\xFF\xFE", 2) === 0) {
            $data = (string) mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16LE');
        } elseif (strncmp($data, "\xFE\xFF", 2) === 0) {
            $data = (string) mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16BE');
        } elseif (strncmp($data, "\xEF\xBB\xBF", 3) === 0) {
            $data = substr($data, 3);
        } elseif (!mb_check_encoding($data, 'UTF-8')) {
            $conv = function_exists('iconv') ? @iconv('CP1258', 'UTF-8//IGNORE', $data) : false;
            $data = $conv !== false ? $conv : (string) mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
        }
        $firstLine = strtok($data, "\n") ?: '';
        $delims = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($delims);
        $delim = (string) array_key_first($delims);
        $rows = [];
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $data);
        rewind($fh);
        $rn = 0;
        while (($line = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            $rn++;
            if ($line === [null]) {
                continue;
            }
            $cells = [];
            foreach ($line as $i => $v) {
                if ($v !== null && trim($v) !== '') {
                    $cells[$i] = Text::trimCell($v);
                }
            }
            if ($cells) {
                $rows[$rn] = $cells;
            }
        }
        fclose($fh);
        return $rows;
    }
}

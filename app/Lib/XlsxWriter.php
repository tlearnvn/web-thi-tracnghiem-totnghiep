<?php

namespace App\Lib;

/**
 * Xuất tệp Excel .xlsx nhiều sheet, có định dạng (tiêu đề màu, viền, số thập phân, cố định dòng tiêu đề,
 * lọc tự động, gộp ô) – viết bằng PHP thuần, không cần thư viện ngoài.
 *
 *   $x = new XlsxWriter();
 *   $s = $x->sheet('Kết quả');
 *   $s->widths([6, 14, 30]);
 *   $s->row(['STT', 'SBD', 'Họ tên'], $x->style(['bold' => true, 'fill' => '1D4ED8', 'color' => 'FFFFFF']));
 *   $x->download('ket-qua.xlsx');
 */
final class XlsxWriter
{
    /** @var XlsxSheet[] */
    private array $sheets = [];
    private array $fonts = [];
    private array $fills = [];
    private array $borders = [];
    private array $numFmts = [];
    private array $xfs = [];
    private array $styleCache = [];
    private string $fontName;
    private int $fontSize;
    public string $title = '';
    public string $author = '';

    public function __construct(string $fontName = 'Arial', int $fontSize = 11)
    {
        $this->fontName = $fontName;
        $this->fontSize = $fontSize;
        $this->fonts[] = $this->fontXml([]);
        $this->fills[] = '<fill><patternFill patternType="none"/></fill>';
        $this->fills[] = '<fill><patternFill patternType="gray125"/></fill>';
        $this->borders[] = '<border><left/><right/><top/><bottom/><diagonal/></border>';
        $this->xfs[] = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
    }

    public function sheet(string $name): XlsxSheet
    {
        $name = self::sheetName($name, count($this->sheets) + 1);
        $existing = array_map(static fn(XlsxSheet $s) => mb_strtolower($s->name), $this->sheets);
        $base = $name;
        $i = 2;
        while (in_array(mb_strtolower($name), $existing, true)) {
            $name = mb_substr($base, 0, 27) . ' (' . $i++ . ')';
        }
        $s = new XlsxSheet($name);
        $this->sheets[] = $s;
        return $s;
    }

    private static function sheetName(string $name, int $n): string
    {
        $name = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name));
        if ($name === '') {
            $name = 'Sheet' . $n;
        }
        return mb_substr($name, 0, 31);
    }

    /**
     * Đăng ký kiểu ô. Khóa hỗ trợ: bold, italic, size, color, fill, border (true|'thin'|'medium'),
     * align (left|center|right), valign (top|center|bottom), wrap, numFmt ('0.00', '0%', '@' ...).
     */
    public function style(array $s): int
    {
        ksort($s);
        $key = json_encode($s);
        if (isset($this->styleCache[$key])) {
            return $this->styleCache[$key];
        }
        $fontXml = $this->fontXml($s);
        $fontId = array_search($fontXml, $this->fonts, true);
        if ($fontId === false) {
            $this->fonts[] = $fontXml;
            $fontId = count($this->fonts) - 1;
        }
        $fillId = 0;
        if (!empty($s['fill'])) {
            $fx = '<fill><patternFill patternType="solid"><fgColor rgb="FF' . strtoupper(ltrim($s['fill'], '#')) . '"/><bgColor indexed="64"/></patternFill></fill>';
            $fillId = array_search($fx, $this->fills, true);
            if ($fillId === false) {
                $this->fills[] = $fx;
                $fillId = count($this->fills) - 1;
            }
        }
        $borderId = 0;
        if (!empty($s['border'])) {
            $w = $s['border'] === true ? 'thin' : (string) $s['border'];
            $c = '<color rgb="FF' . strtoupper(ltrim((string) ($s['borderColor'] ?? 'BFC7D5'), '#')) . '"/>';
            $bx = '<border><left style="' . $w . '">' . $c . '</left><right style="' . $w . '">' . $c . '</right><top style="' . $w . '">' . $c . '</top><bottom style="' . $w . '">' . $c . '</bottom><diagonal/></border>';
            $borderId = array_search($bx, $this->borders, true);
            if ($borderId === false) {
                $this->borders[] = $bx;
                $borderId = count($this->borders) - 1;
            }
        }
        $numFmtId = 0;
        if (!empty($s['numFmt'])) {
            $builtin = ['0' => 1, '0.00' => 2, '#,##0' => 3, '#,##0.00' => 4, '0%' => 9, '0.00%' => 10, '@' => 49];
            if (isset($builtin[$s['numFmt']])) {
                $numFmtId = $builtin[$s['numFmt']];
            } else {
                $idx = array_search($s['numFmt'], $this->numFmts, true);
                if ($idx === false) {
                    $this->numFmts[] = $s['numFmt'];
                    $idx = count($this->numFmts) - 1;
                }
                $numFmtId = 164 + $idx;
            }
        }
        $align = '';
        if (!empty($s['align']) || !empty($s['valign']) || !empty($s['wrap'])) {
            $align = '<alignment'
                . (!empty($s['align']) ? ' horizontal="' . $s['align'] . '"' : '')
                . ' vertical="' . ($s['valign'] ?? 'center') . '"'
                . (!empty($s['wrap']) ? ' wrapText="1"' : '')
                . '/>';
        }
        $xf = '<xf numFmtId="' . $numFmtId . '" fontId="' . $fontId . '" fillId="' . $fillId . '" borderId="' . $borderId . '" xfId="0"'
            . ($numFmtId ? ' applyNumberFormat="1"' : '') . ($fontId ? ' applyFont="1"' : '') . ($fillId ? ' applyFill="1"' : '')
            . ($borderId ? ' applyBorder="1"' : '') . ($align !== '' ? ' applyAlignment="1">' . $align . '</xf>' : '/>');
        $this->xfs[] = $xf;
        $id = count($this->xfs) - 1;
        $this->styleCache[$key] = $id;
        return $id;
    }

    private function fontXml(array $s): string
    {
        return '<font>' . (!empty($s['bold']) ? '<b/>' : '') . (!empty($s['italic']) ? '<i/>' : '')
            . (!empty($s['underline']) ? '<u/>' : '')
            . '<sz val="' . (int) ($s['size'] ?? $this->fontSize) . '"/>'
            . (!empty($s['color']) ? '<color rgb="FF' . strtoupper(ltrim($s['color'], '#')) . '"/>' : '')
            . '<name val="' . htmlspecialchars($this->fontName, ENT_XML1) . '"/><family val="2"/></font>';
    }

    // ------------------------------------------------------------

    public function build(): string
    {
        if (!$this->sheets) {
            $this->sheet('Sheet1');
        }
        $z = new ZipWriter();
        $n = count($this->sheets);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
        for ($i = 1; $i <= $n; $i++) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ct .= '</Types>';
        $z->addFile('[Content_Types].xml', $ct);

        $z->addFile('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>');

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $z->addFile('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::x($this->title) . '</dc:title><dc:creator>' . self::x($this->author) . '</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>');
        $z->addFile('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>TN Exam</Application></Properties>');

        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="28800" windowHeight="15000"/></bookViews><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $defined = '';
        foreach ($this->sheets as $i => $s) {
            $id = $i + 1;
            $wb .= '<sheet name="' . self::x($s->name) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
            $rels .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
            if ($s->filterRef) {
                $defined .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\'' . self::x(str_replace("'", "''", $s->name)) . '\'!' . self::absRef($s->filterRef) . '</definedName>';
            }
            if ($s->printTitles) {
                $defined .= '<definedName name="_xlnm.Print_Titles" localSheetId="' . $i . '">\'' . self::x(str_replace("'", "''", $s->name)) . '\'!$' . $s->printTitles[0] . ':$' . $s->printTitles[1] . '</definedName>';
            }
        }
        $wb .= '</sheets>' . ($defined !== '' ? '<definedNames>' . $defined . '</definedNames>' : '') . '</workbook>';
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $z->addFile('xl/workbook.xml', $wb);
        $z->addFile('xl/_rels/workbook.xml.rels', $rels);
        $z->addFile('xl/styles.xml', $this->stylesXml());
        foreach ($this->sheets as $i => $s) {
            $z->addFile('xl/worksheets/sheet' . ($i + 1) . '.xml', $s->xml());
        }
        return $z->finish();
    }

    private function stylesXml(): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($this->numFmts) {
            $x .= '<numFmts count="' . count($this->numFmts) . '">';
            foreach ($this->numFmts as $i => $f) {
                $x .= '<numFmt numFmtId="' . (164 + $i) . '" formatCode="' . self::x($f) . '"/>';
            }
            $x .= '</numFmts>';
        }
        $x .= '<fonts count="' . count($this->fonts) . '">' . implode('', $this->fonts) . '</fonts>';
        $x .= '<fills count="' . count($this->fills) . '">' . implode('', $this->fills) . '</fills>';
        $x .= '<borders count="' . count($this->borders) . '">' . implode('', $this->borders) . '</borders>';
        $x .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $x .= '<cellXfs count="' . count($this->xfs) . '">' . implode('', $this->xfs) . '</cellXfs>';
        $x .= '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>';
        return $x . '</styleSheet>';
    }

    public function download(string $filename): void
    {
        $data = $this->build();
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        \App\Core\Response::downloadHeaders($filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', strlen($data));
        echo $data;
    }

    public static function x(string $s): string
    {
        $s = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s);
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function col(int $index): string
    {
        $s = '';
        $n = $index + 1;
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = intdiv($n - 1, 26);
        }
        return $s;
    }

    private static function absRef(string $ref): string
    {
        return (string) preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $ref);
    }
}

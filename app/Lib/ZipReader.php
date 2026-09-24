<?php

namespace App\Lib;

/**
 * Đọc / ghi ZIP bằng PHP thuần (chỉ cần zlib) – không phụ thuộc extension zip,
 * không tạo file tạm (phù hợp shared hosting giới hạn inode).
 */
final class ZipReader
{
    private string $data;
    /** @var array<string, array> */
    private array $entries = [];

    private function __construct(string $data)
    {
        $this->data = $data;
        $this->parse();
    }

    public static function fromString(string $data): self
    {
        return new self($data);
    }

    public static function fromFile(string $path): self
    {
        $d = @file_get_contents($path);
        if ($d === false) {
            throw new \RuntimeException('Không đọc được tệp.');
        }
        return new self($d);
    }

    public static function looksLikeZip(string $head): bool
    {
        return strncmp($head, "PK\x03\x04", 4) === 0;
    }

    private function parse(): void
    {
        $len = strlen($this->data);
        if ($len < 22) {
            throw new \RuntimeException('Tệp ZIP không hợp lệ.');
        }
        $searchFrom = max(0, $len - 65557);
        $pos = strrpos(substr($this->data, $searchFrom), "PK\x05\x06");
        if ($pos === false) {
            throw new \RuntimeException('Tệp không phải định dạng ZIP/XLSX hợp lệ.');
        }
        $eocd = unpack('vdisk/vcddisk/vdiskEntries/ventries/Vsize/Voffset/vcommentLen', substr($this->data, $searchFrom + $pos + 4, 18));
        $offset = $eocd['offset'];
        $count = $eocd['entries'];
        for ($i = 0; $i < $count; $i++) {
            if (substr($this->data, $offset, 4) !== "PK\x01\x02") {
                throw new \RuntimeException('Mục lục ZIP bị hỏng.');
            }
            $h = unpack(
                'vmadeBy/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/Vlocal',
                substr($this->data, $offset + 4, 42)
            );
            $name = substr($this->data, $offset + 46, $h['nameLen']);
            $extra = substr($this->data, $offset + 46 + $h['nameLen'], $h['extraLen']);
            if ($h['csize'] === 0xFFFFFFFF || $h['usize'] === 0xFFFFFFFF || $h['local'] === 0xFFFFFFFF) {
                $this->applyZip64($h, $extra);
            }
            $this->entries[str_replace('\\', '/', $name)] = $h;
            $offset += 46 + $h['nameLen'] + $h['extraLen'] + $h['commentLen'];
        }
    }

    private function applyZip64(array &$h, string $extra): void
    {
        $p = 0;
        while ($p + 4 <= strlen($extra)) {
            $f = unpack('vid/vsize', substr($extra, $p, 4));
            if ($f['id'] === 0x0001) {
                $q = $p + 4;
                foreach (['usize', 'csize', 'local'] as $k) {
                    if ($h[$k] === 0xFFFFFFFF) {
                        $h[$k] = unpack('P', substr($extra, $q, 8))[1];
                        $q += 8;
                    }
                }
                return;
            }
            $p += 4 + $f['size'];
        }
    }

    public function names(): array
    {
        return array_keys($this->entries);
    }

    public function has(string $name): bool
    {
        return isset($this->entries[$name]);
    }

    public function get(string $name): ?string
    {
        $h = $this->entries[$name] ?? null;
        if ($h === null) {
            // tìm không phân biệt hoa thường
            foreach ($this->entries as $n => $e) {
                if (strcasecmp($n, $name) === 0) {
                    $h = $e;
                    break;
                }
            }
            if ($h === null) {
                return null;
            }
        }
        $lh = unpack('vnameLen/vextraLen', substr($this->data, $h['local'] + 26, 4));
        $start = $h['local'] + 30 + $lh['nameLen'] + $lh['extraLen'];
        $raw = substr($this->data, $start, $h['csize']);
        if ($h['method'] === 0) {
            return $raw;
        }
        if ($h['method'] === 8) {
            $out = @gzinflate($raw);
            if ($out === false) {
                throw new \RuntimeException('Không giải nén được "' . $name . '".');
            }
            return $out;
        }
        throw new \RuntimeException('Kiểu nén ZIP không được hỗ trợ (' . $h['method'] . ').');
    }
}

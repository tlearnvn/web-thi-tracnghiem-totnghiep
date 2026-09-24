<?php

namespace App\Lib;

/**
 * Ghi tệp ZIP bằng PHP thuần (vào bộ nhớ hoặc thẳng ra luồng php://output).
 */
final class ZipWriter
{
    /** @var resource|null */
    private $stream;
    private string $buffer = '';
    private array $central = [];
    private int $offset = 0;
    private int $dosTime;
    private int $dosDate;

    /** @param resource|null $stream Ghi thẳng ra luồng (vd php://output) thay vì giữ trong bộ nhớ */
    public function __construct($stream = null)
    {
        $this->stream = $stream;
        $t = getdate();
        $this->dosTime = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $this->dosDate = (max(0, $t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
    }

    private function write(string $bytes): void
    {
        if ($this->stream) {
            fwrite($this->stream, $bytes);
        } else {
            $this->buffer .= $bytes;
        }
        $this->offset += strlen($bytes);
    }

    public function addFile(string $name, string $data, bool $compress = true): void
    {
        $crc = crc32($data);
        $method = 0;
        $payload = $data;
        if ($compress && strlen($data) > 64) {
            $z = gzdeflate($data, 6);
            if ($z !== false && strlen($z) < strlen($data)) {
                $payload = $z;
                $method = 8;
            }
        }
        $this->entry($name, $method, $crc, strlen($payload), strlen($data), $payload);
    }

    /**
     * Thêm tệp lớn theo luồng: $producer(callable $emit) gọi $emit($chunk) nhiều lần.
     * Dùng "data descriptor" nên không cần biết trước kích thước.
     */
    public function addStream(string $name, callable $producer, bool $compress = true): void
    {
        $flags = 0x0808; // bit 3: data descriptor, bit 11: tên UTF-8
        $method = $compress ? 8 : 0;
        $localOffset = $this->offset;
        $this->write(pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, $method, $this->dosTime, $this->dosDate, 0, 0, 0, strlen($name), 0) . $name);
        $crcCtx = hash_init('crc32b');
        $usize = 0;
        $csize = 0;
        $deflate = $compress ? deflate_init(ZLIB_ENCODING_RAW, ['level' => 6]) : null;
        $emit = function (string $chunk) use (&$usize, &$csize, $crcCtx, $deflate) {
            if ($chunk === '') {
                return;
            }
            hash_update($crcCtx, $chunk);
            $usize += strlen($chunk);
            $out = $deflate ? deflate_add($deflate, $chunk, ZLIB_NO_FLUSH) : $chunk;
            if ($out !== '' && $out !== false) {
                $csize += strlen($out);
                $this->write($out);
            }
        };
        $producer($emit);
        if ($deflate) {
            $out = deflate_add($deflate, '', ZLIB_FINISH);
            $csize += strlen($out);
            $this->write($out);
        }
        $crc = unpack('N', hash_final($crcCtx, true))[1];
        $this->write(pack('VVVV', 0x08074b50, $crc, $csize, $usize));
        $this->central[] = [$name, $flags, $method, $crc, $csize, $usize, $localOffset];
    }

    private function entry(string $name, int $method, int $crc, int $csize, int $usize, string $payload): void
    {
        $flags = 0x0800;
        $localOffset = $this->offset;
        $this->write(pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, $method, $this->dosTime, $this->dosDate, $crc, $csize, $usize, strlen($name), 0) . $name);
        $this->write($payload);
        $this->central[] = [$name, $flags, $method, $crc, $csize, $usize, $localOffset];
    }

    /** Kết thúc tệp ZIP. Trả về nội dung nếu ghi vào bộ nhớ. */
    public function finish(): string
    {
        $cdStart = $this->offset;
        foreach ($this->central as [$name, $flags, $method, $crc, $csize, $usize, $local]) {
            $this->write(pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, $flags, $method, $this->dosTime, $this->dosDate, $crc, $csize, $usize, strlen($name), 0, 0, 0, 0, 0, $local) . $name);
        }
        $cdSize = $this->offset - $cdStart;
        $n = count($this->central);
        $this->write(pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, $cdSize, $cdStart, 0));
        $out = $this->buffer;
        $this->buffer = '';
        return $out;
    }
}

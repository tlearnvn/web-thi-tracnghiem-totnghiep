<?php

namespace App\Core;

/** Bọc dữ liệu nhị phân để bind dạng BLOB (PDO::PARAM_LOB). */
final class Blob
{
    public string $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }
}

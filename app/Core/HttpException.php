<?php

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public int $status;
    public array $extra;

    public function __construct(int $status, string $message = '', array $extra = [])
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
        $this->status = $status;
        $this->extra = $extra;
    }

    public static function defaultMessage(int $status): string
    {
        switch ($status) {
            case 400:
                return 'Yêu cầu không hợp lệ.';
            case 401:
                return 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại.';
            case 403:
                return 'Bạn không có quyền thực hiện thao tác này.';
            case 404:
                return 'Không tìm thấy trang hoặc dữ liệu được yêu cầu.';
            case 405:
                return 'Phương thức không được hỗ trợ.';
            case 419:
                return 'Phiên làm việc đã hết hạn (mã bảo mật không khớp). Vui lòng tải lại trang.';
            case 423:
                return 'Tài nguyên đang bị khóa.';
            case 503:
                return 'Hệ thống đang bảo trì.';
            default:
                return 'Đã xảy ra lỗi.';
        }
    }
}

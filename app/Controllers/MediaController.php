<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\FileStore;
use App\Core\Settings;

/** Logo, biểu tượng trang (lưu trong CSDL) – truy cập công khai, có cache trình duyệt. */
final class MediaController extends Controller
{
    protected array $public = ['logo', 'favicon'];

    public function logo(): void
    {
        $this->serve((int) Settings::get('logo_file_id', 0));
    }

    public function favicon(): void
    {
        $id = (int) Settings::get('favicon_file_id', 0);
        $this->serve($id ?: (int) Settings::get('logo_file_id', 0));
    }

    private function serve(int $id): void
    {
        \App\Core\Session::release();
        $f = $id ? FileStore::info($id) : null;
        if (!$f || !in_array($f['purpose'], ['logo', 'favicon'], true)) {
            header('Location: ' . base_uri() . 'assets/img/logo.svg');
            return;
        }
        $etag = '"' . substr((string) $f['sha256'], 0, 20) . '"';
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=604800');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            return;
        }
        header('Content-Type: ' . $f['mime']);
        header('Content-Length: ' . (int) $f['size']);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
        FileStore::stream($id);
    }
}

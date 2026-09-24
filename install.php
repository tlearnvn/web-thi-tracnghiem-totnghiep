<?php
/**
 * Trình cài đặt – chạy một lần. Sau khi cài xong, tệp storage/config.php được tạo và trang này tự khóa.
 * Muốn cài lại: xóa storage/config.php (dữ liệu trong CSDL vẫn còn, có thể "kết nối lại").
 */
require __DIR__ . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Schema;
use App\Core\Settings;
use App\Lib\Seeder;
use App\Lib\Text;

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function inst_e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function inst_requirements(): array
{
    $r = [];
    $r[] = ['PHP ' . PHP_VERSION, 'Yêu cầu PHP 8.0 trở lên', PHP_VERSION_ID >= 80000 ? 'ok' : 'bad'];
    $r[] = ['PDO', 'Thư viện kết nối CSDL', extension_loaded('pdo') ? 'ok' : 'bad'];
    $sqlite = extension_loaded('pdo_sqlite');
    $mysql = extension_loaded('pdo_mysql');
    $r[] = ['pdo_sqlite', 'Dùng CSDL SQLite (1 tệp)', $sqlite ? 'ok' : ($mysql ? 'warn' : 'bad')];
    $r[] = ['pdo_mysql', 'Dùng CSDL MySQL / MariaDB', $mysql ? 'ok' : ($sqlite ? 'warn' : 'bad')];
    $r[] = ['mbstring', 'Xử lý chuỗi tiếng Việt', extension_loaded('mbstring') ? 'ok' : 'bad'];
    $r[] = ['zlib', 'Đọc / ghi tệp Excel .xlsx', function_exists('gzinflate') && function_exists('deflate_init') ? 'ok' : 'bad'];
    $r[] = ['SimpleXML', 'Đọc tệp Excel .xlsx', extension_loaded('simplexml') ? 'ok' : 'bad'];
    $r[] = ['intl', 'Chuẩn hóa Unicode (khuyên dùng)', class_exists('Normalizer') ? 'ok' : 'warn'];
    $w = is_writable(STORAGE_PATH);
    $r[] = ['Thư mục storage/', $w ? 'Ghi được' : 'Cần quyền ghi (chmod 755/775)', $w ? 'ok' : 'bad'];
    $r[] = ['max_execution_time', ini_get('max_execution_time') . ' giây', ((int) ini_get('max_execution_time') === 0 || (int) ini_get('max_execution_time') >= 120) ? 'ok' : 'warn'];
    $r[] = ['upload_max_filesize', (string) ini_get('upload_max_filesize') . ' (tệp PDF được tải theo từng phần 512 KB)', 'ok'];
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $r[] = ['HTTPS', $https ? 'Đang dùng kết nối bảo mật' : 'Khuyên dùng HTTPS khi triển khai thật', $https ? 'ok' : 'warn'];
    return $r;
}

function inst_db_config(array $in): array
{
    if (($in['db_driver'] ?? 'sqlite') === 'mysql') {
        return [
            'driver' => 'mysql',
            'host' => trim((string) ($in['mysql_host'] ?? 'localhost')) ?: 'localhost',
            'port' => (int) ($in['mysql_port'] ?? 3306) ?: 3306,
            'database' => trim((string) ($in['mysql_db'] ?? '')),
            'username' => trim((string) ($in['mysql_user'] ?? '')),
            'password' => (string) ($in['mysql_pass'] ?? ''),
            'prefix' => preg_replace('/[^A-Za-z0-9_]/', '', (string) ($in['mysql_prefix'] ?? 'tn_')),
        ];
    }
    $path = trim((string) ($in['sqlite_path'] ?? ''));
    if ($path === '') {
        $path = 'storage/tn_' . bin2hex(random_bytes(6)) . '.sqlite';
    }
    return ['driver' => 'sqlite', 'path' => $path, 'wal' => !empty($in['sqlite_wal'])];
}

function inst_existing(Database $db): bool
{
    try {
        return $db->tableExists('settings') && (bool) $db->value("SELECT 1 FROM {settings} WHERE name = 'installed_at'");
    } catch (\Throwable $e) {
        return false;
    }
}

function inst_json(array $d, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------------------------------------------------------------
$installed = App::loadConfig();
$token = $_COOKIE['tn_install'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    $token = bin2hex(random_bytes(16));
    setcookie('tn_install', $token, ['expires' => time() + 7200, 'path' => base_uri(), 'httponly' => true, 'samesite' => 'Strict']);
}
$errors = [];
$done = null;
$in = $_POST;

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($token, (string) ($in['_token'] ?? ''))) {
        if (($in['action'] ?? '') === 'test') {
            inst_json(['ok' => false, 'error' => 'Phiên cài đặt hết hạn, vui lòng tải lại trang.'], 419);
        }
        $errors[] = 'Phiên cài đặt hết hạn, vui lòng thử lại.';
    } elseif (($in['action'] ?? '') === 'test') {
        try {
            $cfg = inst_db_config($in);
            if ($cfg['driver'] === 'mysql' && $cfg['database'] === '') {
                throw new \RuntimeException('Chưa nhập tên cơ sở dữ liệu.');
            }
            App::setConfig(['db' => $cfg]);
            $db = App::db();
            $info = $db->info();
            $existing = inst_existing($db);
            inst_json(['ok' => true, 'message' => 'Kết nối thành công: ' . $info['version'] . ($existing ? ' – CSDL đã có dữ liệu của hệ thống.' : ''), 'existing' => $existing]);
        } catch (\Throwable $e) {
            inst_json(['ok' => false, 'error' => 'Không kết nối được: ' . $e->getMessage()]);
        }
    } else {
        // ---- Kiểm tra dữ liệu nhập ----
        $siteName = trim((string) ($in['site_name'] ?? ''));
        $orgName = trim((string) ($in['org_name'] ?? ''));
        $adminName = Text::normalizeName((string) ($in['admin_name'] ?? ''));
        $adminUser = Text::cleanUsername((string) ($in['admin_username'] ?? ''));
        $pw = (string) ($in['admin_password'] ?? '');
        $useExisting = !empty($in['use_existing']);
        foreach (inst_requirements() as $req) {
            if ($req[2] === 'bad' && !in_array($req[0], ['pdo_sqlite', 'pdo_mysql'], true)) {
                $errors[] = 'Máy chủ chưa đáp ứng yêu cầu: ' . $req[0] . ' (' . $req[1] . ').';
            }
        }
        $cfg = inst_db_config($in);
        if ($cfg['driver'] === 'mysql' && $cfg['database'] === '') {
            $errors[] = 'Vui lòng nhập tên cơ sở dữ liệu MySQL.';
        }
        if (!$useExisting) {
            if ($siteName === '') {
                $errors[] = 'Vui lòng nhập tên trang web.';
            }
            if ($adminName === '') {
                $errors[] = 'Vui lòng nhập họ tên quản trị viên.';
            }
            if (strlen($adminUser) < 3) {
                $errors[] = 'Tên đăng nhập quản trị cần ít nhất 3 ký tự (chữ không dấu, số, dấu . _ -).';
            }
            if (strlen($pw) < 8) {
                $errors[] = 'Mật khẩu quản trị cần ít nhất 8 ký tự.';
            } elseif ($pw !== (string) ($in['admin_password2'] ?? '')) {
                $errors[] = 'Mật khẩu nhập lại không khớp.';
            }
        }
        if (!$errors) {
            try {
                App::setConfig(['db' => $cfg]);
                $db = App::db();
                $existing = inst_existing($db);
                if ($existing && !$useExisting) {
                    throw new \RuntimeException('CSDL này đã có dữ liệu của hệ thống. Hãy chọn "Dùng lại dữ liệu có sẵn", hoặc đổi tiền tố bảng / tên tệp SQLite khác.');
                }
                if (!$existing && $useExisting) {
                    throw new \RuntimeException('Không tìm thấy dữ liệu cũ trong CSDL này để dùng lại.');
                }
                @set_time_limit(300);
                $demo = [];
                if (!$useExisting) {
                    Schema::install($db);
                    Settings::reset();
                    Seeder::roles($db);
                    Seeder::subjects($db);
                    $secret = bin2hex(random_bytes(32));
                    Settings::setMany([
                        'site_name' => $siteName,
                        'site_short_name' => mb_strtoupper(trim((string) ($in['site_short_name'] ?? '')) ?: 'Thi trắc nghiệm'),
                        'org_name' => $orgName !== '' ? $orgName : 'Trường THPT',
                        'org_parent' => trim((string) ($in['org_parent'] ?? '')),
                        'installed_at' => time(),
                        'app_secret' => $secret,
                        'default_school_year' => Settings::guessSchoolYear(),
                    ]);
                    $adminId = Seeder::createUser($db, [
                        'username' => $adminUser, 'password' => $pw, 'role' => 'admin', 'full_name' => $adminName,
                        'email' => trim((string) ($in['admin_email'] ?? '')) ?: null, 'code' => 'ADMIN',
                    ]);
                    if (!empty($in['demo'])) {
                        $demo = Seeder::demo($db, $adminId);
                    }
                }
                $key = bin2hex(random_bytes(32));
                $config = [
                    'installed' => true,
                    'installed_at' => date('Y-m-d H:i:s'),
                    'app_key' => $key,
                    'db' => $cfg,
                    'debug' => false,
                    'session_name' => 'TNS' . substr(hash('sha256', $key), 0, 8),
                    'trusted_proxy_header' => '',
                    'base_url' => '',
                ];
                $php = "<?php\n// Cấu hình sinh bởi trình cài đặt ngày " . date('d/m/Y H:i') . " (UTC+7). KHÔNG chia sẻ tệp này.\n"
                    . "// Đổi 'debug' => true để xem chi tiết lỗi khi cần khắc phục sự cố.\n"
                    . 'return ' . var_export($config, true) . ";\n";
                if (@file_put_contents(CONFIG_FILE, $php, LOCK_EX) === false) {
                    throw new \RuntimeException('Không ghi được tệp ' . CONFIG_FILE . '. Hãy cấp quyền ghi cho thư mục storage/.');
                }
                @chmod(CONFIG_FILE, 0640);
                if ($cfg['driver'] === 'sqlite') {
                    @chmod(Database::resolveSqlitePath($cfg['path']), 0660);
                }
                $done = ['demo' => $demo, 'admin' => $useExisting ? null : $adminUser, 'driver' => $cfg['driver'], 'existing' => $useExisting];
                setcookie('tn_install', '', ['expires' => time() - 3600, 'path' => base_uri()]);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

$reqs = inst_requirements();
$blocking = array_filter($reqs, static fn($r) => $r[2] === 'bad' && !in_array($r[0], ['pdo_sqlite', 'pdo_mysql'], true));
$noDb = !extension_loaded('pdo_sqlite') && !extension_loaded('pdo_mysql');
$v = static fn(string $k, string $d = '') => inst_e($in[$k] ?? $d);
$driver = $in['db_driver'] ?? (extension_loaded('pdo_sqlite') ? 'sqlite' : 'mysql');
$defaultSqlite = 'storage/tn_' . bin2hex(random_bytes(6)) . '.sqlite';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Cài đặt – Hệ thống thi trắc nghiệm</title>
<link rel="icon" href="assets/img/logo.svg">
<link rel="stylesheet" href="assets/css/fonts.css?v=<?= inst_e(TN_VERSION) ?>">
<link rel="stylesheet" href="assets/css/app.css?v=<?= inst_e(TN_VERSION) ?>">
<script>(function(){try{var t=localStorage.getItem('tn-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<style>
body{background:radial-gradient(1000px 500px at 50% -120px,var(--primary-soft),transparent 70%),var(--bg)}
.db-fields{display:none}.db-fields.show{display:grid;animation:fade-in .2s}
.test-result{font-size:13.5px;margin-top:10px}
</style>
</head>
<body>
<div class="install-wrap">
  <div class="install-head rise">
    <img src="assets/img/logo.svg" alt="">
    <h1 class="mt-2">Cài đặt hệ thống thi trắc nghiệm</h1>
    <p class="text-muted">Định dạng đề thi tốt nghiệp THPT từ năm 2025 · Phiên bản <?= inst_e(TN_VERSION) ?></p>
  </div>

<?php if ($installed): ?>
  <div class="card rise"><div class="card-body text-center">
    <div class="empty-icon"><?= \App\Lib\Icons::svg('shield-check') ?></div>
    <h2>Hệ thống đã được cài đặt</h2>
    <p class="text-muted">Trình cài đặt đã bị khóa để bảo vệ dữ liệu. Muốn cài lại, hãy xóa tệp <code>storage/config.php</code> trên máy chủ.</p>
    <a class="btn btn-primary" href="index.php"><?= \App\Lib\Icons::svg('log-in') ?> Vào trang đăng nhập</a>
  </div></div>

<?php elseif ($done): ?>
  <div class="card rise card-accent"><div class="card-body">
    <div class="row top gap-lg">
      <div class="empty-icon" style="margin:0;background:var(--success-soft);color:var(--success-text)"><?= \App\Lib\Icons::svg('circle-check') ?></div>
      <div class="grow">
        <h2>Cài đặt thành công! 🎉</h2>
        <p class="text-muted">Dữ liệu được lưu trong <?= $done['driver'] === 'sqlite' ? 'tệp SQLite duy nhất' : 'máy chủ MySQL' ?> – kể cả tệp PDF đề thi, logo và phiên đăng nhập, nên không phát sinh thêm tệp (inode) trên hosting.</p>
        <?php if ($done['admin']): ?>
          <div class="alert alert-info mt-2"><?= \App\Lib\Icons::svg('key-round') ?><div>Đăng nhập quản trị bằng tài khoản <strong><?= inst_e($done['admin']) ?></strong> và mật khẩu bạn vừa đặt.</div></div>
        <?php endif; ?>
        <?php if ($done['demo']): ?>
          <div class="alert alert-success mt-2"><?= \App\Lib\Icons::svg('sparkles') ?><div>
            <div class="alert-title">Đã tạo dữ liệu mẫu để dùng thử</div>
            <ul>
              <li>Giáo viên: <code><?= inst_e($done['demo']['teacher']) ?></code></li>
              <li>Giám thị: <code><?= inst_e($done['demo']['proctor']) ?></code></li>
              <li>Học sinh: <code><?= inst_e($done['demo']['student']) ?></code></li>
            </ul>
            <div class="text-sm text-muted mt-1">Có thể xóa toàn bộ dữ liệu mẫu tại <em>Hệ thống → Thông tin hệ thống</em>.</div>
          </div></div>
        <?php endif; ?>
        <div class="alert alert-warning mt-2"><?= \App\Lib\Icons::svg('shield-alert') ?><div>
          Bảo mật: nếu hosting dùng <strong>Nginx</strong> (không đọc .htaccess), hãy chặn truy cập thư mục <code>storage/</code> và <code>app/</code> theo hướng dẫn trong README.
          Nên sao lưu dữ liệu định kỳ tại <em>Hệ thống → Sao lưu</em>.
        </div></div>
        <a class="btn btn-primary btn-lg mt-3" href="index.php?r=login"><?= \App\Lib\Icons::svg('log-in') ?> Đăng nhập ngay</a>
      </div>
    </div>
  </div></div>

<?php else: ?>
  <div class="steps rise rise-1">
    <div class="step active"><span class="step-num">1</span>Kiểm tra máy chủ</div>
    <div class="step"><span class="step-num">2</span>Cơ sở dữ liệu</div>
    <div class="step"><span class="step-num">3</span>Đơn vị & quản trị</div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger mb-3 rise"><?= \App\Lib\Icons::svg('circle-alert') ?><div><div class="alert-title">Chưa cài đặt được</div><ul><?php foreach ($errors as $er): ?><li><?= inst_e($er) ?></li><?php endforeach; ?></ul></div></div>
  <?php endif; ?>

  <form method="post" id="install-form" autocomplete="off">
    <input type="hidden" name="_token" value="<?= inst_e($token) ?>">
    <input type="hidden" name="action" value="install">

    <div class="card rise rise-1">
      <div class="card-head"><h3><?= \App\Lib\Icons::svg('server') ?> 1. Kiểm tra máy chủ</h3><span class="hint">PHP <?= inst_e(PHP_VERSION) ?> · <?= inst_e(PHP_SAPI) ?></span></div>
      <div class="card-body">
        <div class="req-list">
          <?php foreach ($reqs as $r): ?>
            <div class="req-item <?= $r[2] === 'ok' ? '' : $r[2] ?>"><?= \App\Lib\Icons::svg($r[2] === 'ok' ? 'circle-check' : ($r[2] === 'warn' ? 'triangle-alert' : 'circle-x')) ?><div><strong><?= inst_e($r[0]) ?></strong><small><?= inst_e($r[1]) ?></small></div></div>
          <?php endforeach; ?>
        </div>
        <?php if ($blocking || $noDb): ?>
          <div class="alert alert-danger mt-2"><?= \App\Lib\Icons::svg('circle-alert') ?><div>Máy chủ chưa đáp ứng yêu cầu tối thiểu. Hãy bật các extension còn thiếu trong cPanel → <em>Select PHP Version</em> rồi tải lại trang.</div></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mt-3 rise rise-2">
      <div class="card-head"><h3><?= \App\Lib\Icons::svg('database') ?> 2. Cơ sở dữ liệu</h3><span class="hint">Mọi dữ liệu – kể cả tệp PDF đề thi – đều lưu trong CSDL</span></div>
      <div class="card-body">
        <div class="radio-cards">
          <label class="radio-card<?= $driver === 'sqlite' ? ' checked' : '' ?>">
            <input type="radio" name="db_driver" value="sqlite" <?= $driver === 'sqlite' ? 'checked' : '' ?> <?= extension_loaded('pdo_sqlite') ? '' : 'disabled' ?>>
            <span class="rc-icon"><?= \App\Lib\Icons::svg('hard-drive') ?></span>
            <span><span class="rc-title">SQLite – 1 tệp duy nhất</span><span class="rc-desc">Không cần tạo CSDL, sao lưu chỉ bằng 1 tệp. Phù hợp đến khoảng 200–300 học sinh thi cùng lúc.</span></span>
          </label>
          <label class="radio-card<?= $driver === 'mysql' ? ' checked' : '' ?>">
            <input type="radio" name="db_driver" value="mysql" <?= $driver === 'mysql' ? 'checked' : '' ?> <?= extension_loaded('pdo_mysql') ? '' : 'disabled' ?>>
            <span class="rc-icon"><?= \App\Lib\Icons::svg('server') ?></span>
            <span><span class="rc-title">MySQL / MariaDB</span><span class="rc-desc">Dùng CSDL tạo trong cPanel. Khuyên dùng khi có đông học sinh thi đồng thời.</span></span>
          </label>
        </div>

        <div class="form-grid mt-3 db-fields<?= $driver === 'sqlite' ? ' show' : '' ?>" data-db="sqlite">
          <div class="field span-2">
            <label>Đường dẫn tệp SQLite</label>
            <input class="input mono" name="sqlite_path" value="<?= $v('sqlite_path', $defaultSqlite) ?>">
            <div class="help">Tên tệp được tạo ngẫu nhiên để không ai đoán được. Có thể đặt ngoài thư mục web để an toàn hơn, ví dụ <code>/home/tenban/private/thitn.sqlite</code>. Đường dẫn tương đối tính từ thư mục cài đặt.</div>
          </div>
          <label class="check span-2"><input type="checkbox" name="sqlite_wal" value="1" <?= !isset($in['action']) || !empty($in['sqlite_wal']) ? 'checked' : '' ?>><span>Bật chế độ ghi WAL (khuyên dùng – nhiều học sinh lưu bài cùng lúc không bị chờ)<small>Tắt nếu hosting báo lỗi khóa CSDL (một số máy chủ dùng ổ mạng NFS).</small></span></label>
        </div>

        <div class="form-grid cols-3 mt-3 db-fields<?= $driver === 'mysql' ? ' show' : '' ?>" data-db="mysql">
          <div class="field"><label>Máy chủ</label><input class="input" name="mysql_host" value="<?= $v('mysql_host', 'localhost') ?>"></div>
          <div class="field"><label>Cổng</label><input class="input" name="mysql_port" value="<?= $v('mysql_port', '3306') ?>"></div>
          <div class="field"><label>Tiền tố bảng</label><input class="input mono" name="mysql_prefix" value="<?= $v('mysql_prefix', 'tn_') ?>"></div>
          <div class="field"><label>Tên CSDL <span class="req">*</span></label><input class="input" name="mysql_db" value="<?= $v('mysql_db') ?>"></div>
          <div class="field"><label>Tài khoản</label><input class="input" name="mysql_user" value="<?= $v('mysql_user') ?>"></div>
          <div class="field"><label>Mật khẩu</label><input class="input" type="password" name="mysql_pass" value="<?= $v('mysql_pass') ?>"></div>
        </div>

        <div class="row mt-3">
          <button type="button" class="btn" id="test-db"><?= \App\Lib\Icons::svg('activity') ?> Kiểm tra kết nối</button>
          <label class="check" id="existing-box" style="<?= empty($in['use_existing']) ? 'display:none' : '' ?>"><input type="checkbox" name="use_existing" value="1" <?= !empty($in['use_existing']) ? 'checked' : '' ?>><span>Dùng lại dữ liệu có sẵn trong CSDL này (chỉ tạo lại tệp cấu hình)</span></label>
        </div>
        <div class="test-result" id="test-result"></div>
      </div>
    </div>

    <div class="card mt-3 rise rise-3">
      <div class="card-head"><h3><?= \App\Lib\Icons::svg('building-2') ?> 3. Thông tin đơn vị & tài khoản quản trị</h3><span class="hint">Có thể đổi lại trong phần Cài đặt</span></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field span-2"><label>Tên trang web <span class="req">*</span></label><input class="input" name="site_name" value="<?= $v('site_name', 'Hệ thống thi trắc nghiệm trực tuyến') ?>"></div>
          <div class="field"><label>Tên viết tắt (hiển thị trên logo)</label><input class="input" name="site_short_name" value="<?= $v('site_short_name', 'Thi trắc nghiệm') ?>"></div>
          <div class="field"><label>Tên đơn vị (trường)</label><input class="input" name="org_name" value="<?= $v('org_name') ?>" placeholder="Ví dụ: Trường THPT Nguyễn Du"></div>
          <div class="field span-2"><label>Đơn vị chủ quản</label><input class="input" name="org_parent" value="<?= $v('org_parent') ?>" placeholder="Ví dụ: Sở Giáo dục và Đào tạo …"></div>
        </div>
        <div class="divider-text">Tài khoản quản trị</div>
        <div class="form-grid">
          <div class="field"><label>Họ và tên <span class="req">*</span></label><input class="input" name="admin_name" value="<?= $v('admin_name') ?>"></div>
          <div class="field"><label>Email</label><input class="input" type="email" name="admin_email" value="<?= $v('admin_email') ?>"></div>
          <div class="field"><label>Tên đăng nhập <span class="req">*</span></label><input class="input" name="admin_username" value="<?= $v('admin_username', 'admin') ?>" autocomplete="off"></div>
          <div class="field"><label>Mật khẩu (≥ 8 ký tự) <span class="req">*</span></label><input class="input" type="password" name="admin_password" autocomplete="new-password"></div>
          <div class="field"><label>Nhập lại mật khẩu <span class="req">*</span></label><input class="input" type="password" name="admin_password2" autocomplete="new-password"></div>
        </div>
        <label class="check mt-3"><input type="checkbox" name="demo" value="1" <?= !isset($in['action']) || !empty($in['demo']) ? 'checked' : '' ?>><span><strong>Tạo dữ liệu mẫu để dùng thử</strong><small>2 lớp, 20 học sinh, 1 giáo viên, 1 giám thị, 1 đề Toán minh họa có PDF + đáp án + lời giải, 1 ca thi đang mở và 1 ca luyện tập.</small></span></label>
      </div>
      <div class="card-foot">
        <span class="text-muted text-sm grow">Múi giờ hệ thống: Asia/Ho_Chi_Minh (UTC+7)</span>
        <button type="submit" class="btn btn-primary btn-lg" <?= ($blocking || $noDb) ? 'disabled' : '' ?>><?= \App\Lib\Icons::svg('sparkles') ?> Cài đặt hệ thống</button>
      </div>
    </div>
  </form>
<?php endif; ?>
  <p class="text-center text-muted text-sm mt-4">Hệ thống thi trắc nghiệm · v<?= inst_e(TN_VERSION) ?></p>
</div>
<script>
(function () {
  var form = document.getElementById('install-form');
  if (!form) return;
  var radios = form.querySelectorAll('input[name=db_driver]');
  function sync() {
    var val = form.querySelector('input[name=db_driver]:checked');
    val = val ? val.value : 'sqlite';
    form.querySelectorAll('.db-fields').forEach(function (el) { el.classList.toggle('show', el.dataset.db === val); });
    form.querySelectorAll('.radio-card').forEach(function (c) { c.classList.toggle('checked', c.querySelector('input').checked); });
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  sync();
  var btn = document.getElementById('test-db'), out = document.getElementById('test-result');
  btn.addEventListener('click', function () {
    var fd = new FormData(form); fd.set('action', 'test');
    out.innerHTML = '<span class="spinner"></span> Đang kiểm tra…';
    fetch('install.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      out.innerHTML = '<div class="alert ' + (j.ok ? 'alert-success' : 'alert-danger') + '">' + (j.ok ? '✔ ' : '✖ ') + (j.message || j.error) + '</div>';
      document.getElementById('existing-box').style.display = j.existing ? '' : 'none';
    }).catch(function () { out.innerHTML = '<div class="alert alert-danger">Không gửi được yêu cầu.</div>'; });
  });
  form.addEventListener('submit', function () {
    var b = form.querySelector('[type=submit]');
    b.innerHTML = '<span class="spinner"></span> Đang cài đặt…'; b.style.pointerEvents = 'none';
  });
})();
</script>
</body>
</html>

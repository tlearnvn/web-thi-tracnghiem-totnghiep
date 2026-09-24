<?php
use App\Controllers\SettingsController;

$groups = SettingsController::GROUPS;
$field = static function (string $key, array $f, $value): string {
    [$type, $label, $help] = [$f[0], $f[1], $f[2] ?? ''];
    $id = 'st_' . $key;
    $h = '';
    switch ($type) {
        case 'bool':
            return '<div class="switch-row"><div><div class="fw-600">' . e($label) . '</div>' . ($help !== '' ? '<div class="help">' . e($help) . '</div>' : '') . '</div>'
                . '<label class="switch"><input type="checkbox" name="' . e($key) . '" value="1"' . checked((int) $value === 1) . '><span></span></label></div>';
        case 'select':
            $h = '<select class="select" id="' . $id . '" name="' . e($key) . '">';
            foreach ($f['options'] as $k => $v) {
                $h .= '<option value="' . e($k) . '"' . selected((string) $value, (string) $k) . '>' . e($v) . '</option>';
            }
            $h .= '</select>';
            break;
        case 'textarea':
            $h = '<textarea class="textarea" id="' . $id . '" name="' . e($key) . '" rows="3" maxlength="' . (int) ($f['max'] ?? 1000) . '">' . e((string) $value) . '</textarea>';
            break;
        case 'color':
            $sw = '';
            foreach (['#2563eb', '#4f46e5', '#7c3aed', '#0284c7', '#0d9488', '#16a34a', '#ca8a04', '#ea580c', '#dc2626', '#be123c', '#334155'] as $c) {
                $sw .= '<button type="button" class="swatch" style="background:' . $c . '" data-color="' . $c . '" title="' . $c . '"></button>';
            }
            $h = '<div class="color-input"><input type="color" id="' . $id . '" name="' . e($key) . '" value="' . e((string) $value) . '"><input class="input mono" style="width:110px" value="' . e((string) $value) . '" data-hex-for="' . $id . '"></div><div class="swatches mt-1">' . $sw . '</div>';
            break;
        case 'int':
        case 'float':
            $h = '<input class="input" id="' . $id . '" name="' . e($key) . '" inputmode="decimal" style="max-width:180px" value="' . e($type === 'float' ? fmt_num($value, 2) : (string) (int) $value) . '">'
                . (isset($f['min']) ? '<span class="help" style="margin-left:8px">từ ' . e(fmt_num($f['min'])) . ' đến ' . e(fmt_num($f['max'])) . '</span>' : '');
            $h = '<div class="row nowrap" style="gap:6px">' . $h . '</div>';
            break;
        default:
            $h = '<input class="input" id="' . $id . '" name="' . e($key) . '" maxlength="' . (int) ($f['max'] ?? 255) . '" value="' . e((string) $value) . '"' . (!empty($f['required']) ? ' required' : '') . '>';
    }
    return '<div class="field"><label for="' . $id . '">' . e($label) . (!empty($f['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>' . $h . ($help !== '' ? '<div class="help">' . e($help) . '</div>' : '') . '</div>';
};
$media = static function (string $kind, ?array $file, string $url, string $title, string $hint): string {
    return '<div class="media-box" data-kind="' . $kind . '">'
        . '<div class="media-prev"><img src="' . e($url) . '" alt="" data-prev></div>'
        . '<div class="grow"><div class="fw-600">' . e($title) . '</div><div class="help">' . e($hint) . '</div>'
        . '<div class="text-xs text-muted mt-1">' . ($file ? 'Ảnh riêng · ' . (int) (json_dec($file['meta'], [])['w'] ?? 0) . '×' . (int) (json_dec($file['meta'], [])['h'] ?? 0) . ' px · ' . e(fmt_bytes($file['size'])) : 'Đang dùng ảnh mặc định') . '</div>'
        . '<div class="row mt-2" style="gap:8px"><label class="btn btn-sm">' . icon('upload') . ' Chọn ảnh…<input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" hidden data-file></label>'
        . ($file ? '<button type="button" class="btn btn-sm btn-ghost" data-remove>' . icon('rotate-ccw') . ' Dùng mặc định</button>' : '') . '</div></div></div>';
};
?>
<div class="page-head">
  <div><h1>Cài đặt hệ thống</h1><div class="sub">Tên, logo, chân trang, màu sắc, các mặc định khi tạo ca thi, xếp loại, bảo mật. Mọi thay đổi có hiệu lực ngay.</div></div>
  <div class="actions"><span class="version-pill">Phiên bản v<?= e(TN_VERSION) ?></span></div>
</div>
<div data-tab-scope>
  <div class="tabs mb-3" data-tabs data-tabs-hash>
    <?php foreach ($groups as $g => $info): ?><a href="#<?= e($g) ?>" class="tab<?= $g === 'brand' ? ' active' : '' ?>" data-tab="<?= e($g) ?>"><?= icon($info[1]) ?> <?= e($info[0]) ?></a><?php endforeach; ?>
  </div>
  <?php foreach ($groups as $g => $info): ?>
    <div class="tab-panel<?= $g === 'brand' ? ' active' : '' ?>" data-panel="<?= e($g) ?>">
      <div class="grid grid-sidebar">
        <form class="card" method="post" action="<?= e(url('settings/save')) ?>">
          <?= csrf_field() ?><input type="hidden" name="group" value="<?= e($g) ?>">
          <div class="card-head"><h3><?= icon($info[1]) ?> <?= e($info[0]) ?></h3></div>
          <div class="card-body">
            <?php if ($g === 'grade'): ?><div class="form-grid"><?php endif; ?>
            <?php foreach ($fields[$g] as $key => $f) { echo $field($key, $f, $values[$key] ?? ''); } ?>
            <?php if ($g === 'grade'): ?></div><?php endif; ?>
            <?php if ($g === 'brand'): ?>
              <div class="callout text-sm mt-2"><b>Xem trước chân trang:</b><br><?= render_footer_text((string) ($values['footer_text'] ?? '')) ?></div>
            <?php endif; ?>
          </div>
          <div class="card-foot row end"><button class="btn btn-primary" type="submit"><?= icon('save') ?> Lưu thay đổi</button></div>
        </form>
        <div class="stack" style="gap:20px">
          <?php if ($g === 'brand'): ?>
            <div class="card">
              <div class="card-head"><h3><?= icon('image') ?> Logo & biểu tượng</h3></div>
              <div class="card-body stack">
                <?= $media('logo', $logo, logo_url(), 'Logo đơn vị', 'Hiển thị ở menu, trang đăng nhập, phòng thi, phiếu in. Nên dùng ảnh vuông, nền trong suốt.') ?>
                <?= $media('favicon', $favicon, favicon_url(), 'Biểu tượng trang (favicon)', 'Hiện trên tab trình duyệt. Để trống thì dùng logo.') ?>
                <div class="help"><?= icon('info', 'sm') ?> Ảnh được thu nhỏ ngay trên trình duyệt rồi lưu vào CSDL (không tạo tệp trên hosting).</div>
              </div>
            </div>
          <?php elseif ($g === 'exam'): ?>
            <div class="callout text-sm"><b><?= icon('lightbulb', 'sm') ?> Gợi ý cho thi trên máy tính</b><ul style="margin:6px 0 0;padding-left:18px;line-height:1.7"><li>Bật <b>khóa thiết bị</b> và <b>in chìm</b> cho thi chính thức.</li><li>Phòng máy có mạng yếu: tăng <b>thời gian ân hạn</b> lên 120–180 giây.</li><li>Hosting chia sẻ nhiều học sinh cùng lúc: nhịp kiểm tra 20–30 giây, độ trễ lưu 1500 ms.</li></ul></div>
          <?php elseif ($g === 'grade'): ?>
            <div class="callout text-sm">Xếp loại dùng cho bảng điểm, thống kê và báo cáo Excel. Với thang điểm khác 10 (ví dụ thang 100), hệ thống tự quy đổi về thang 10 trước khi xếp loại.</div>
          <?php elseif ($g === 'security'): ?>
            <div class="callout text-sm">Mật khẩu được băm bằng <b>bcrypt</b>. Phiên đăng nhập, nhật ký, tệp đề thi đều lưu trong CSDL. Phòng máy dùng chung một IP công cộng nên tăng giới hạn đăng nhập sai theo IP.</div>
          <?php else: ?>
            <div class="callout text-sm">Khi bảo trì, học sinh và giáo viên thấy thông báo bảo trì; quản trị viên vẫn làm việc bình thường. <b>Không bật khi đang có ca thi.</b></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php \App\Core\View::push('scripts', '<script>
TN.ready(function () {
  var logoUrl = ' . js_json(url('settings/logo')) . ';
  document.querySelectorAll("[data-hex-for]").forEach(function (t) {
    var c = document.getElementById(t.dataset.hexFor);
    c.addEventListener("input", function () { t.value = c.value; });
    t.addEventListener("input", function () { if (/^#[0-9a-f]{6}$/i.test(t.value)) c.value = t.value.toLowerCase(); });
    t.closest(".field").querySelectorAll("[data-color]").forEach(function (b) { b.addEventListener("click", function () { c.value = b.dataset.color; t.value = b.dataset.color; }); });
  });
  function resize(file, max) {
    return new Promise(function (resolve, reject) {
      if (file.size > 8 * 1024 * 1024) { reject(new Error("Ảnh quá lớn (tối đa 8 MB).")); return; }
      var rd = new FileReader();
      rd.onerror = function () { reject(new Error("Không đọc được tệp.")); };
      rd.onload = function () {
        var img = new Image();
        img.onload = function () {
          var w = img.naturalWidth || max, h = img.naturalHeight || max, k = Math.min(1, max / Math.max(w, h));
          var cv = document.createElement("canvas");
          cv.width = Math.max(16, Math.round(w * k)); cv.height = Math.max(16, Math.round(h * k));
          var ctx = cv.getContext("2d");
          ctx.imageSmoothingQuality = "high";
          ctx.drawImage(img, 0, 0, cv.width, cv.height);
          resolve(cv.toDataURL("image/png"));
        };
        img.onerror = function () { reject(new Error("Tệp không phải ảnh hợp lệ.")); };
        img.src = rd.result;
      };
      rd.readAsDataURL(file);
    });
  }
  document.querySelectorAll(".media-box").forEach(function (box) {
    var kind = box.dataset.kind, input = box.querySelector("[data-file]"), prev = box.querySelector("[data-prev]");
    input.addEventListener("change", function () {
      var f = input.files[0]; if (!f) return;
      resize(f, kind === "logo" ? 512 : 192).then(function (data) {
        prev.src = data;
        return TN.api(logoUrl, { data: { kind: kind, data: data } });
      }).then(function (r) { TN.toast(r.message, "success"); setTimeout(function () { location.reload(); }, 800); })
        .catch(function (e) { TN.toast(e.message, "error"); });
    });
    var rm = box.querySelector("[data-remove]");
    if (rm) rm.addEventListener("click", function () {
      TN.confirm({ message: "Bỏ ảnh riêng và dùng ảnh mặc định?" }).then(function (ok) {
        if (!ok) return;
        TN.api(logoUrl, { data: { kind: kind, remove: 1 } }).then(function (r) { TN.toast(r.message, "success"); setTimeout(function () { location.reload(); }, 600); }, function (e) { TN.toast(e.message, "error"); });
      });
    });
  });
});
</script>'); ?>

<?php $loginUrl = absolute_url('login'); ?>
<style>
body{background:#fff}
.slips-bar{position:sticky;top:0;background:var(--surface);border-bottom:1px solid var(--border);padding:12px 20px;display:flex;gap:12px;align-items:center;z-index:5}
.slips{display:grid;grid-template-columns:repeat(2,1fr);gap:0;padding:10mm;max-width:210mm;margin:0 auto}
.slip{border:1px dashed #94a3b8;padding:12px 14px;page-break-inside:avoid;break-inside:avoid;display:flex;gap:12px;align-items:flex-start;min-height:44mm;color:#0f172a;background:#fff}
.slip img{width:40px;height:40px;object-fit:contain}
.slip h4{margin:0;font-size:13px;text-transform:uppercase}
.slip .org{font-size:11px;color:#475569}
.slip .nm{font-size:15px;font-weight:700;margin:6px 0 2px}
.slip .meta{font-size:12px;color:#475569}
.slip .acc{margin-top:8px;display:grid;grid-template-columns:auto 1fr;gap:3px 10px;font-size:13px}
.slip .acc b{font-family:var(--mono);font-size:14px;letter-spacing:.03em}
.slip .url{margin-top:6px;font-size:11px;color:#475569;word-break:break-all}
@media print{.slips-bar{display:none}.slips{padding:0}@page{size:A4;margin:8mm}}
</style>
<div class="slips-bar">
  <strong class="grow">Phiếu tài khoản (<?= $creds ? count($creds['rows']) : 0 ?>)</strong>
  <span class="text-muted text-sm">Mỗi trang A4 in 10 phiếu, cắt theo đường nét đứt.</span>
  <button class="btn btn-primary" onclick="window.print()"><?= icon('printer') ?> In ngay</button>
</div>
<?php if (!$creds): ?>
  <p class="text-center mt-4">Không có dữ liệu. Hãy nhập học sinh hoặc cấp lại mật khẩu trước.</p>
<?php else: ?>
<div class="slips">
  <?php foreach ($creds['rows'] as $r): ?>
    <div class="slip">
      <img src="<?= e(logo_url()) ?>" alt="">
      <div class="grow">
        <h4><?= e(setting('site_short_name')) ?></h4>
        <div class="org"><?= e(setting('org_name')) ?></div>
        <div class="nm"><?= e($r['name']) ?></div>
        <div class="meta"><?= $r['class'] ? 'Lớp ' . e($r['class']) : '' ?><?= $r['code'] ? ' · Mã ' . e($r['code']) : '' ?></div>
        <div class="acc"><span>Tên đăng nhập:</span><b><?= e($r['username']) ?></b><span>Mật khẩu:</span><b><?= e($r['password']) ?></b></div>
        <div class="url">Đăng nhập tại: <?= e($loginUrl) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

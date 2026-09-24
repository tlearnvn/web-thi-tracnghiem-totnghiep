<?php
use App\Core\Logger;

$groups = ['auth' => 'Đăng nhập', 'user' => 'Tài khoản', 'role' => 'Phân quyền', 'class' => 'Lớp', 'exam' => 'Đề thi', 'variant' => 'Mã đề', 'session' => 'Ca thi', 'monitor' => 'Giám sát', 'attempt' => 'Bài làm', 'results' => 'Kết quả', 'settings' => 'Cài đặt', 'backup' => 'Sao lưu', 'system' => 'Hệ thống', 'announcement' => 'Thông báo'];
$tabs = ['audit' => ['Hoạt động', 'activity', $counts['audit']], 'errors' => ['Lỗi hệ thống', 'triangle-alert', $counts['errors']], 'logins' => ['Lịch sử đăng nhập', 'log-in', $counts['logins']]];
?>
<div class="page-head">
  <div><h1>Nhật ký hệ thống</h1><div class="sub">Mọi thao tác quan trọng, lỗi và lượt đăng nhập đều được ghi lại trong CSDL (tự dọn sau 12 tháng) để truy vết khi cần.</div></div>
  <div class="actions">
    <?php if ($tab === 'audit'): ?><a class="btn" href="<?= e(query_with(['r' => 'logs/export', 'page' => null])) ?>"><?= icon('file-spreadsheet') ?> Xuất Excel</a><?php endif; ?>
    <?php if ($tab === 'errors' && $counts['errors']): ?><button class="btn btn-danger-soft" data-post="<?= e(url('logs/clear-errors')) ?>" data-confirm="Xóa toàn bộ nhật ký lỗi?"><?= icon('trash-2') ?> Xóa nhật ký lỗi</button><?php endif; ?>
  </div>
</div>
<div class="mini-stats mb-3">
  <div class="mini-stat"><div class="v"><?= e(fmt_num($counts['audit'], 0)) ?></div><div class="l">Dòng nhật ký hoạt động</div></div>
  <div class="mini-stat"><div class="v <?= $counts['errors24'] ? 'text-danger' : '' ?>"><?= (int) $counts['errors24'] ?></div><div class="l">Lỗi trong 24 giờ qua</div></div>
  <div class="mini-stat"><div class="v <?= $counts['failed24'] > 20 ? 'text-warning' : '' ?>"><?= (int) $counts['failed24'] ?></div><div class="l">Đăng nhập sai trong 24 giờ</div></div>
</div>
<div class="card">
  <div class="tabs" style="padding:0 16px">
    <?php foreach ($tabs as $k => $t): ?><a class="tab<?= $tab === $k ? ' active' : '' ?>" href="<?= e(url('logs', ['tab' => $k])) ?>"><?= icon($t[1]) ?> <?= e($t[0]) ?> <span class="count"><?= e(fmt_num($t[2], 0)) ?></span></a><?php endforeach; ?>
  </div>
  <?php if ($tab === 'audit'): ?>
    <form class="table-toolbar" method="get" action="<?= e(base_uri() . 'index.php') ?>">
      <input type="hidden" name="r" value="logs">
      <div class="input-icon search"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Tìm người dùng, thao tác, IP…" data-autosubmit></div>
      <select class="select" name="group" style="width:auto" data-autosubmit><option value="">Mọi loại thao tác</option><?php foreach ($groups as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($_GET['group'] ?? '', $k) ?>><?= e($v) ?></option><?php endforeach; ?></select>
      <input class="input" type="date" name="from" value="<?= e($_GET['from'] ?? '') ?>" style="width:auto" data-autosubmit title="Từ ngày">
      <input class="input" type="date" name="to" value="<?= e($_GET['to'] ?? '') ?>" style="width:auto" data-autosubmit title="Đến ngày">
    </form>
    <div class="table-wrap"><table class="table table-sm">
      <thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Thao tác</th><th>Đối tượng</th><th>Chi tiết</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r):
          $d = json_dec($r['details'], null);
          $detail = is_array($d) ? implode(' · ', array_map(static fn($k, $v) => $k . ': ' . (is_scalar($v) ? (string) $v : json_enc($v)), array_keys($d), $d)) : (string) $r['details']; ?>
        <tr>
          <td class="nowrap text-sm"><?= e(fmt_dt($r['created_at'], 'H:i:s d/m/Y')) ?></td>
          <td class="text-sm"><?= $r['full_name'] ? e($r['full_name']) . '<div class="text-xs text-muted">' . e($r['username']) . '</div>' : '<span class="text-muted">Hệ thống</span>' ?></td>
          <td><span class="badge"><?= e(Logger::label($r['action'])) ?></span><div class="text-xs text-faint mono"><?= e($r['action']) ?></div></td>
          <td class="text-sm text-muted"><?= e(trim(($r['target_type'] ?? '') . ' #' . ($r['target_id'] ?? ''), ' #')) ?></td>
          <td class="text-xs text-muted" style="max-width:380px;word-break:break-word"><?= e(str_limit($detail, 220)) ?></td>
          <td class="text-xs mono"><?= e((string) $r['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6"><div class="empty" style="padding:30px"><p>Không có dòng nhật ký phù hợp.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table></div>
  <?php elseif ($tab === 'errors'): ?>
    <div class="table-wrap"><table class="table table-sm">
      <thead><tr><th>Thời gian</th><th>Mã lỗi</th><th>Nội dung</th><th>Vị trí</th><th>Người dùng</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="nowrap text-sm"><?= e(fmt_dt($r['created_at'], 'H:i:s d/m/Y')) ?></td>
          <td><span class="badge <?= $r['level'] === 'warning' ? 'badge-warning' : 'badge-danger' ?> mono"><?= e($r['ref'] ?: $r['level']) ?></span></td>
          <td class="text-sm" style="max-width:520px;word-break:break-word"><?= e(str_limit((string) $r['message'], 300)) ?><?php if ($r['trace']): ?><details><summary class="text-xs text-primary" style="cursor:pointer">Chi tiết kỹ thuật</summary><pre class="text-xs" style="white-space:pre-wrap;max-height:260px;overflow:auto;background:var(--surface-2);padding:8px;border-radius:8px"><?= e((string) $r['trace']) ?></pre></details><?php endif; ?><div class="text-xs text-muted mono"><?= e((string) $r['url']) ?></div></td>
          <td class="text-xs mono"><?= e((string) $r['file']) ?>:<?= (int) $r['line'] ?></td>
          <td class="text-sm"><?= e((string) ($r['full_name'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5"><div class="empty" style="padding:30px"><div class="empty-icon"><?= icon('circle-check') ?></div><p>Không có lỗi nào được ghi nhận. 🎉</p></div></td></tr><?php endif; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <form class="table-toolbar" method="get" action="<?= e(base_uri() . 'index.php') ?>">
      <input type="hidden" name="r" value="logs"><input type="hidden" name="tab" value="logins">
      <div class="input-icon search"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Tên đăng nhập hoặc IP…" data-autosubmit></div>
    </form>
    <div class="table-wrap"><table class="table table-sm">
      <thead><tr><th>Thời gian</th><th>Tên đăng nhập</th><th>Kết quả</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr><td class="nowrap text-sm"><?= e(fmt_dt($r['created_at'], 'H:i:s d/m/Y')) ?></td><td class="mono text-sm"><?= e((string) $r['username']) ?></td><td><?= (int) $r['success'] ? badge('Thành công', 'success') : badge('Sai mật khẩu / bị chặn', 'danger') ?></td><td class="mono text-sm"><?= e((string) $r['ip']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4"><div class="empty" style="padding:30px"><p>Chưa có dữ liệu.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  <?= $pager->links() ?>
</div>

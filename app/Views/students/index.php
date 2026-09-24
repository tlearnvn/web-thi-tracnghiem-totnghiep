<?php
use App\Lib\Users;

$q = $_GET['q'] ?? '';
$cid = $_GET['class_id'] ?? '';
?>
<div class="page-head">
  <div>
    <h1>Học sinh</h1>
    <div class="sub"><?= number_format($pager->total, 0, ',', '.') ?> học sinh<?= \App\Core\Scope::classIds() !== null ? ' trong các lớp bạn phụ trách' : '' ?></div>
  </div>
  <div class="actions">
    <a class="btn" href="<?= e(query_with(['r' => 'students/export', 'page' => null])) ?>"><?= icon('file-down') ?> Xuất Excel</a>
    <?php if ($canManage): ?>
      <a class="btn" href="<?= e(url('students/import')) ?>"><?= icon('file-spreadsheet') ?> Nhập từ Excel</a>
      <a class="btn btn-primary" href="<?= e(url('students/create', ['class_id' => $cid])) ?>"><?= icon('user-plus') ?> Thêm học sinh</a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <form class="table-toolbar" method="get" action="<?= e(base_uri() . 'index.php') ?>">
    <input type="hidden" name="r" value="students">
    <div class="input-icon search"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Tìm theo tên, mã HS, tên đăng nhập…" data-autosubmit></div>
    <select class="select" name="class_id" style="width:auto;min-width:150px" data-autosubmit>
      <option value="">Tất cả lớp</option>
      <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($cid, $c['id']) ?>><?= e($c['name']) ?><?= $c['school_year'] ? ' (' . e($c['school_year']) . ')' : '' ?></option><?php endforeach; ?>
      <?php if (\App\Core\Scope::classIds() === null): ?><option value="none"<?= selected($cid, 'none') ?>>— Chưa xếp lớp —</option><?php endif; ?>
    </select>
    <select class="select" name="status" style="width:auto" data-autosubmit>
      <option value="">Mọi trạng thái</option>
      <option value="active"<?= selected($_GET['status'] ?? '', 'active') ?>>Đang hoạt động</option>
      <option value="locked"<?= selected($_GET['status'] ?? '', 'locked') ?>>Đã khóa</option>
    </select>
    <select class="select" name="gender" style="width:auto" data-autosubmit>
      <option value="">Nam & nữ</option>
      <option value="Nam"<?= selected($_GET['gender'] ?? '', 'Nam') ?>>Nam</option>
      <option value="Nữ"<?= selected($_GET['gender'] ?? '', 'Nữ') ?>>Nữ</option>
    </select>
    <select class="select" name="sort" style="width:auto" data-autosubmit>
      <option value="class">Sắp xếp: Lớp → Tên</option>
      <option value="name"<?= selected($_GET['sort'] ?? '', 'name') ?>>Theo tên (ABC)</option>
      <option value="code"<?= selected($_GET['sort'] ?? '', 'code') ?>>Theo mã HS</option>
      <option value="login"<?= selected($_GET['sort'] ?? '', 'login') ?>>Đăng nhập gần đây</option>
    </select>
    <?php if ($q !== '' || $cid !== '' || !empty($_GET['status']) || !empty($_GET['gender'])): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('students')) ?>"><?= icon('x') ?> Xóa lọc</a><?php endif; ?>
  </form>

  <form method="post" action="<?= e(url('students/bulk')) ?>" id="bulk-form">
    <?= csrf_field() ?>
    <?php if ($canManage || $canPassword): ?>
    <div class="bulk-bar" id="bulk-bar">
      <span>Đã chọn <strong data-count>0</strong> học sinh</span>
      <select class="select select-sm" name="action" id="bulk-action" style="width:auto">
        <?php if ($canPassword): ?><option value="password">Cấp lại mật khẩu</option><option value="lock">Khóa tài khoản</option><option value="unlock">Mở khóa tài khoản</option><?php endif; ?>
        <?php if ($canManage): ?><option value="move">Chuyển lớp</option><option value="delete">Xóa học sinh</option><?php endif; ?>
      </select>
      <select class="select select-sm" name="class_id" data-for="move" style="width:auto" hidden>
        <option value="">— Bỏ khỏi lớp —</option>
        <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <select class="select select-sm" name="mode" data-for="password" style="width:auto">
        <?php foreach (Users::PASSWORD_MODES as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <input class="input input-sm" name="fixed" data-for="password-fixed" placeholder="Mật khẩu chung" style="width:150px" hidden>
      <button class="btn btn-sm btn-primary" type="submit" id="bulk-go"><?= icon('check') ?> Thực hiện</button>
    </div>
    <?php endif; ?>

    <?php if (!$rows): ?>
      <div class="empty">
        <div class="empty-icon"><?= icon('graduation-cap') ?></div>
        <h3>Chưa có học sinh</h3>
        <p>Thêm từng học sinh hoặc nhập nhanh cả danh sách lớp từ tệp Excel (hỗ trợ tệp xuất từ phần mềm quản lý trường học).</p>
        <?php if ($canManage): ?><a class="btn btn-primary" href="<?= e(url('students/import')) ?>"><?= icon('file-spreadsheet') ?> Nhập từ Excel</a><?php endif; ?>
      </div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <?php if ($canManage || $canPassword): ?><th class="col-check"><input type="checkbox" data-check-all="#bulk-bar" aria-label="Chọn tất cả"></th><?php endif; ?>
            <th>Học sinh</th><th>Lớp</th><th class="hide-sm">Ngày sinh</th><th class="hide-sm">Tài khoản</th><th class="center">Bài đã làm</th><th class="hide-sm">Đăng nhập gần nhất</th><th class="col-actions"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <?php if ($canManage || $canPassword): ?><td class="col-check"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" data-check></td><?php endif; ?>
            <td>
              <div class="person">
                <span class="avatar sm" style="background:<?= e(color_for($r['username'])) ?>"><?= e(initials($r['full_name'])) ?></span>
                <div><a class="row-link" href="<?= e(url('students/view', ['id' => $r['id']])) ?>"><?= e($r['full_name']) ?></a>
                  <div class="sub"><?= $r['code'] ? 'Mã: ' . e($r['code']) : '' ?><?= $r['gender'] ? ' · ' . e($r['gender']) : '' ?></div></div>
              </div>
            </td>
            <td><?= $r['class_name'] ? badge($r['class_name'], 'primary') : '<span class="text-faint">—</span>' ?></td>
            <td class="hide-sm text-sm"><?= e(fmt_date($r['birthday'])) ?></td>
            <td class="hide-sm"><span class="mono text-sm"><?= e($r['username']) ?></span> <?= $r['status'] === 'active' ? '' : badge('Đã khóa', 'danger', 'lock') ?><?= (int) $r['must_change_password'] ? ' ' . badge('Chờ đổi MK', 'warning') : '' ?></td>
            <td class="center"><?= (int) $r['attempts_done'] ?><?php if ($r['avg_score'] !== null): ?> <span class="text-muted text-sm">· TB <?= e(fmt_num($r['avg_score'], 2)) ?></span><?php endif; ?></td>
            <td class="hide-sm text-sm text-muted"><?= $r['last_login_at'] ? e(fmt_ago($r['last_login_at'])) : 'Chưa đăng nhập' ?></td>
            <td class="col-actions">
              <div class="dropdown">
                <button class="btn btn-ghost btn-icon btn-sm" type="button" data-dropdown aria-label="Thao tác"><?= icon('ellipsis-vertical') ?></button>
                <div class="dropdown-menu">
                  <a href="<?= e(url('students/view', ['id' => $r['id']])) ?>"><?= icon('eye') ?> Xem hồ sơ & kết quả</a>
                  <?php if ($canManage): ?><a href="<?= e(url('students/edit', ['id' => $r['id']])) ?>"><?= icon('square-pen') ?> Sửa thông tin</a><?php endif; ?>
                  <?php if ($canPassword): ?>
                    <button type="button" data-post="<?= e(url('students/password', ['id' => $r['id']])) ?>" data-fields='{"mode":"random6"}' data-confirm="Cấp mật khẩu mới (6 chữ số) cho <?= e($r['full_name']) ?>?"><?= icon('key-round') ?> Cấp lại mật khẩu</button>
                    <button type="button" data-post="<?= e(url('students/bulk')) ?>" data-fields='<?= e(json_encode(['action' => $r['status'] === 'active' ? 'lock' : 'unlock', 'ids[]' => [(int) $r['id']]])) ?>'><?= icon($r['status'] === 'active' ? 'lock' : 'lock-open') ?> <?= $r['status'] === 'active' ? 'Khóa tài khoản' : 'Mở khóa' ?></button>
                  <?php endif; ?>
                  <?php if ($canManage): ?>
                    <hr>
                    <button type="button" class="danger" data-post="<?= e(url('students/delete', ['id' => $r['id']])) ?>" data-confirm="Xóa học sinh <?= e($r['full_name']) ?> và TOÀN BỘ bài làm? Thao tác không thể hoàn tác." data-danger><?= icon('trash-2') ?> Xóa học sinh</button>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </form>
  <?= $pager->links() ?>
</div>

<?php \App\Core\View::push('scripts', <<<'JS'
<script>
TN.ready(function () {
  var sel = document.getElementById('bulk-action');
  if (!sel) return;
  var form = document.getElementById('bulk-form');
  function sync() {
    form.querySelectorAll('[data-for]').forEach(function (el) {
      var f = el.dataset.for;
      el.hidden = !(f === sel.value || (f === 'password-fixed' && sel.value === 'password' && form.querySelector('[name=mode]').value === 'fixed'));
    });
  }
  sel.addEventListener('change', sync);
  form.querySelector('[name=mode]').addEventListener('change', sync);
  sync();
  form.addEventListener('submit', function (e) {
    if (form.dataset.ok) return;
    e.preventDefault();
    var n = TN.checkedIds(form).length;
    var label = sel.options[sel.selectedIndex].text;
    TN.confirm({ message: label + ' cho ' + n + ' học sinh đã chọn?', danger: sel.value === 'delete', ok: label }).then(function (ok) { if (ok) { form.dataset.ok = 1; form.submit(); } });
  });
});
</script>
JS); ?>

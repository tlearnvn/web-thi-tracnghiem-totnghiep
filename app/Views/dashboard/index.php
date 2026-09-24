<?php
use App\Core\Auth;
use App\Core\Logger;
use App\Lib\Sessions;

$u = Auth::user();
use_charts();
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= icon('calendar', 'sm') ?> <?= e(weekday_vi()) ?>, <?= e(date('d/m/Y')) ?></div>
    <h1><?= e(greeting()) ?>, <?= e(\App\Lib\Text::givenName($u['full_name'])) ?> 👋</h1>
    <div class="sub">Tổng quan hoạt động thi cử của <?= e(setting('org_name')) ?>.</div>
  </div>
  <div class="actions">
    <?php if (can('exams.manage', 'exams.manage_all')): ?><a class="btn" href="<?= e(url('exams/create')) ?>"><?= icon('file-plus') ?> Tạo đề thi</a><?php endif; ?>
    <?php if (can('sessions.manage', 'sessions.manage_all')): ?><a class="btn btn-primary" href="<?= e(url('sessions/create')) ?>"><?= icon('calendar-clock') ?> Tạo ca thi</a><?php endif; ?>
  </div>
</div>

<div class="grid grid-4">
  <div class="stat rise"><div class="stat-icon"><?= icon('graduation-cap') ?></div><div><div class="stat-value" data-count-to="<?= (int) $stats['students'] ?>">0</div><div class="stat-label">Học sinh<?= \App\Core\Scope::classIds() !== null ? ' (lớp phụ trách)' : '' ?></div></div></div>
  <div class="stat rise rise-1"><div class="stat-icon purple"><?= icon('school') ?></div><div><div class="stat-value" data-count-to="<?= (int) $stats['classes'] ?>">0</div><div class="stat-label">Lớp học</div></div></div>
  <div class="stat rise rise-2"><div class="stat-icon info"><?= icon('file-text') ?></div><div><div class="stat-value" data-count-to="<?= (int) $stats['exams'] ?>">0</div><div class="stat-label">Đề thi</div></div></div>
  <div class="stat rise rise-3"><div class="stat-icon success"><?= icon('activity') ?></div><div><div class="stat-value" data-count-to="<?= (int) $stats['doingNow'] ?>">0</div><div class="stat-label">Đang làm, có kết nối · <?= (int) $stats['submittedToday'] ?> bài nộp hôm nay</div></div></div>
</div>

<div class="grid grid-sidebar mt-3">
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head">
        <h3><?= icon('circle-play') ?> Ca thi đang diễn ra</h3>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('sessions')) ?>">Tất cả ca thi <?= icon('arrow-right', 'sm') ?></a>
      </div>
      <?php if (!$live): ?>
        <div class="empty" style="padding:30px 20px"><div class="empty-icon"><?= icon('calendar-clock') ?></div><h3>Chưa có ca thi nào đang diễn ra</h3><p>Tạo ca thi mới hoặc xem các ca thi sắp tới bên dưới.</p></div>
      <?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Ca thi</th><th>Tiến độ</th><th class="hide-sm">Kết thúc</th><th class="col-actions"></th></tr></thead>
          <tbody>
          <?php foreach ($live as $s):
              $done = (int) ($s['_stats']['done'] ?? 0);
              $doing = (int) ($s['_stats']['doing'] ?? 0);
              $tg = max(1, (int) $s['_targets']); ?>
            <tr>
              <td>
                <div class="person"><span class="subject-dot" style="background:<?= e($s['subject_color'] ?: '#2563eb') ?>"></span>
                  <div><a class="row-link" href="<?= e(url('sessions/view', ['id' => $s['id']])) ?>"><?= e($s['name']) ?></a>
                    <div class="sub"><?= e($s['subject_name'] ?? '') ?> · <?= Sessions::stateBadge($s) ?><?= $s['mode'] === 'practice' ? ' ' . badge('Luyện tập', 'purple') : '' ?></div></div></div>
              </td>
              <td style="min-width:180px">
                <div class="progress-label"><span><?= $doing ?> đang làm · <?= $done ?> đã nộp</span><span><?= (int) $s['_targets'] ?> HS</span></div>
                <div class="progress"><span style="width:<?= min(100, round(($done + $doing) * 100 / $tg)) ?>%"></span></div>
              </td>
              <td class="hide-sm text-sm"><?= $s['end_at'] ? e(fmt_dt($s['end_at'], 'H:i d/m')) : '<span class="text-muted">Không giới hạn</span>' ?></td>
              <td class="col-actions"><a class="btn btn-sm btn-soft" href="<?= e(url('monitor', ['id' => $s['id']])) ?>"><?= icon('monitor') ?> Giám sát</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-head"><h3><?= icon('chart-column') ?> Điểm trung bình các ca thi gần đây</h3><?php if (can('stats.view')): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('stats')) ?>">Thống kê chi tiết <?= icon('arrow-right', 'sm') ?></a><?php endif; ?></div>
      <div class="card-body">
        <?php if (!$recent): ?>
          <div class="empty" style="padding:24px"><p>Chưa có bài làm nào được chấm.</p></div>
        <?php else: ?>
          <div class="chart-box"><canvas id="chart-recent"></canvas></div>
          <?php \App\Core\View::push('scripts', '<script>TN.ready(function(){TNChart.bar("chart-recent",' . js_json(array_map(static fn($r) => mb_strimwidth($r['name'], 0, 28, '…'), $recent)) . ',[{label:"Điểm TB",data:' . js_json(array_map(static fn($r) => round((float) $r['avg_score'], 2), $recent)) . '},{label:"Cao nhất",data:' . js_json(array_map(static fn($r) => round((float) $r['max_score'], 2), $recent)) . ',color:"#16a34a"}],{max:10});});</script>'); ?>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($activity): ?>
    <div class="card">
      <div class="card-head"><h3><?= icon('history') ?> Hoạt động gần đây</h3><a class="btn btn-sm btn-ghost" href="<?= e(url('logs')) ?>">Nhật ký <?= icon('arrow-right', 'sm') ?></a></div>
      <div class="card-body">
        <div class="timeline">
          <?php foreach ($activity as $a): ?>
            <div class="tl-item <?= strpos($a['action'], 'delete') !== false ? 'danger' : (strpos($a['action'], 'auth.') === 0 ? 'muted' : '') ?>">
              <div class="tl-time"><?= e(fmt_dt($a['created_at'], 'H:i d/m/Y')) ?> · <?= e($a['ip']) ?></div>
              <div class="tl-title"><?= e($a['full_name'] ?? 'Hệ thống') ?> – <?= e(Logger::ACTIONS[$a['action']] ?? $a['action']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('calendar-clock') ?> Sắp diễn ra</h3></div>
      <div class="card-body">
        <?php if (!$upcoming): ?>
          <p class="text-muted mb-0">Không có ca thi nào sắp diễn ra.</p>
        <?php else: ?>
          <ul class="list-plain">
            <?php foreach ($upcoming as $s): ?>
              <li>
                <a class="fw-600" href="<?= e(url('sessions/view', ['id' => $s['id']])) ?>"><?= e($s['name']) ?></a>
                <div class="text-sm text-muted"><?= icon('clock', 'sm') ?> <?= e(fmt_dt($s['start_at'], 'H:i – d/m/Y')) ?> · còn <span class="countdown" data-countdown="<?= (int) $s['start_at'] ?>"></span></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3><?= icon('sparkles') ?> Thao tác nhanh</h3></div>
      <div class="card-body stack-sm">
        <?php if (can('students.manage')): ?><a class="btn btn-block" style="justify-content:flex-start" href="<?= e(url('students/import')) ?>"><?= icon('file-spreadsheet') ?> Nhập học sinh từ Excel</a><?php endif; ?>
        <?php if (can('exams.manage', 'exams.manage_all')): ?><a class="btn btn-block" style="justify-content:flex-start" href="<?= e(url('exams')) ?>"><?= icon('file-up') ?> Tải đề PDF & nhập đáp án</a><?php endif; ?>
        <?php if (can('results.view', 'results.view_all')): ?><a class="btn btn-block" style="justify-content:flex-start" href="<?= e(url('results')) ?>"><?= icon('clipboard-check') ?> Xem kết quả & chấm bài</a><?php endif; ?>
        <?php if (can('settings.manage')): ?><a class="btn btn-block" style="justify-content:flex-start" href="<?= e(url('settings')) ?>"><?= icon('palette') ?> Đổi tên, logo, chân trang</a><?php endif; ?>
      </div>
    </div>

    <?php if ($announcements): ?>
    <div class="card">
      <div class="card-head"><h3><?= icon('megaphone') ?> Thông báo</h3></div>
      <div class="card-body">
        <?php foreach ($announcements as $a): ?>
          <div class="announcement<?= $a['is_pinned'] ? ' pinned' : '' ?>"><h4><?= $a['is_pinned'] ? icon('bookmark', 'sm') : '' ?><?= e($a['title']) ?></h4><p><?= e($a['body']) ?></p><small><?= e(fmt_dt($a['created_at'])) ?></small></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($system): ?>
    <div class="card">
      <div class="card-head"><h3><?= icon('server') ?> Hệ thống</h3><a class="btn btn-sm btn-ghost" href="<?= e(url('system')) ?>">Chi tiết</a></div>
      <div class="card-body">
        <div class="kv-list">
          <div class="kv"><span>CSDL</span><span><?= e($system['db']['version']) ?></span></div>
          <div class="kv"><span>Dung lượng CSDL</span><span><?= e(fmt_bytes($system['db']['size'] ?? 0)) ?></span></div>
          <div class="kv"><span>Tệp lưu trong CSDL</span><span><?= e(fmt_bytes($system['files'])) ?></span></div>
          <div class="kv"><span>Đang trực tuyến</span><span><span class="dot-live"></span> <?= (int) $system['online'] ?> người</span></div>
          <div class="kv"><span>Lỗi 7 ngày qua</span><span class="<?= $system['errors'] ? 'text-danger' : 'text-success' ?>"><?= (int) $system['errors'] ?></span></div>
          <div class="kv"><span>Phiên bản</span><span class="mono">v<?= e(TN_VERSION) ?></span></div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

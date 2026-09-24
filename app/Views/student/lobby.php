<?php
use App\Lib\Attempts;
use App\Lib\ExamFormat;
use App\Lib\Scoring;
use App\Lib\Sessions;

$st = $s['_state'];
$struct = $exam['_structure'];
$parts = Scoring::compute($struct, $exam['_scoring'], [], [])['parts'];
$color = $subject['color'] ?? '#2563eb';
$doing = $s['_doing'];
$max = (int) $s['max_attempts'];
$usedUp = !$doing && $max > 0 && count($s['_done']) >= $max;
$lateUntil = ((int) $s['late_join'] > 0 && $s['start_at']) ? (int) $s['start_at'] + (int) $s['late_join'] * 60 : 0;
// Giờ kết thúc ca chỉ đáng nhắc khi nó cắt ngắn thời gian làm bài của em
$capEnd = $o['time_policy'] === 'cap' && $s['end_at'] && ($duration <= 0 || (int) $s['end_at'] < max(time(), (int) $s['start_at']) + $duration + 60) ? (int) $s['end_at'] : 0;
$resultPolicy = ['best' => 'lần cao điểm nhất', 'latest' => 'lần làm gần nhất', 'first' => 'lần làm đầu tiên'][$o['result_policy']] ?? '';

$rules = [];
$rules[] = ['timer', '', 'Thời gian làm bài: ' . ($duration > 0 ? fmt_duration($duration) : 'không giới hạn'),
    'Đồng hồ tính theo giờ máy chủ (UTC+7) – máy tính của em có sai giờ cũng không ảnh hưởng.' . ($capEnd ? ' Bài thi tự thu chậm nhất lúc ' . fmt_dt($capEnd, 'H:i') . ' (giờ kết thúc ca thi), kể cả khi em vào muộn.' : '')];
$rules[] = ['cloud-upload', 'ok', 'Bài làm được lưu tự động liên tục',
    'Mỗi lần tô đáp án đều được lưu lên máy chủ và lưu dự phòng trên máy. Nếu mất mạng em vẫn làm bài bình thường, khi có mạng lại hệ thống tự đồng bộ.'];
$rules[] = ['laptop', '', 'Máy hỏng, mất điện, lỡ tắt trình duyệt?',
    $o['device_lock'] ? 'Bình tĩnh báo giám thị. Em đăng nhập lại (trên máy khác nếu cần), giám thị bấm "Mở khóa thiết bị" là làm tiếp được – bài đã làm không bị mất.' : 'Em chỉ cần đăng nhập lại và vào lại bài thi để làm tiếp – bài đã làm không bị mất.'];
if ($o['track_focus']) {
    $act = ['log' => 'giám thị sẽ xem xét', 'lock' => 'bài thi bị tạm khóa, chờ giám thị mở', 'submit' => 'hệ thống tự động thu bài'][$o['violation_action']] ?? '';
    $rules[] = ['triangle-alert', 'warn', 'Không rời khỏi màn hình làm bài',
        'Chuyển sang tab/ứng dụng khác, thu nhỏ trình duyệt đều bị ghi nhận.' . ($o['max_violations'] > 0 ? ' Quá ' . $o['max_violations'] . ' lần: ' . $act . '.' : '')];
}
if ($o['require_fullscreen']) {
    $rules[] = ['maximize', 'warn', 'Làm bài ở chế độ toàn màn hình', 'Thoát toàn màn hình được tính là rời khỏi bài thi.'];
}
if ($o['protect_pdf']) {
    $rules[] = ['shield-check', '', 'Đề thi chỉ xem trong phòng thi', 'Không tải về, sao chép hay in đề. Đề có in chìm họ tên và số báo danh của em.'];
}
if ($o['min_submit_minutes'] > 0) {
    $rules[] = ['hourglass', '', 'Nộp bài sớm nhất sau ' . $o['min_submit_minutes'] . ' phút', 'Hãy kiểm tra kỹ bài trước khi nộp.'];
}
if ($max !== 1) {
    $rules[] = ['repeat', '', $max === 0 ? 'Được làm bài nhiều lần' : 'Được làm tối đa ' . $max . ' lần', 'Kết quả chính thức lấy theo ' . $resultPolicy . '.'];
}
$scoreText = ['after_submit' => 'ngay sau khi nộp bài', 'after_end' => 'sau khi ca thi kết thúc', 'manual' => 'khi giáo viên công bố', 'never' => 'không hiển thị cho học sinh'][$o['show_score']] ?? '';
$rules[] = ['award', 'ok', 'Xem điểm: ' . ($s['mode'] === 'practice' ? 'ngay sau khi nộp bài' : $scoreText), 'Bài làm được chấm tự động theo ' . e(Scoring::describe($exam['_scoring'], $struct)) . '.'];
?>
<div class="crumbs mb-2"><a href="<?= e(url('student')) ?>"><?= icon('arrow-left', 'sm') ?> Bài thi của em</a></div>
<div class="page-head">
  <div>
    <div class="eyebrow"><span class="subject-dot" style="background:<?= e($color) ?>"></span> <?= e($subject['name'] ?? 'Bài thi') ?><?= $s['mode'] === 'practice' ? ' · Luyện tập' : '' ?></div>
    <h1><?= e($s['name']) ?></h1>
    <div class="sub"><?= e($exam['title']) ?></div>
  </div>
  <div class="actions"><?= Sessions::stateBadge($s) ?></div>
</div>

<div class="grid grid-sidebar">
  <div class="stack" style="gap:20px">
    <div class="card rise">
      <div class="card-head"><h3><?= icon('file-text') ?> Thông tin bài thi</h3></div>
      <div class="card-body">
        <div class="mini-stats mb-3">
          <div class="mini-stat"><div class="v"><?= $duration > 0 ? (int) round($duration / 60) : '∞' ?></div><div class="l">phút làm bài</div></div>
          <div class="mini-stat"><div class="v"><?= ExamFormat::totalQuestions($struct) ?></div><div class="l">câu hỏi</div></div>
          <div class="mini-stat"><div class="v"><?= e(fmt_num($maxScore)) ?></div><div class="l">điểm tối đa</div></div>
          <div class="mini-stat"><div class="v"><?= $max === 0 ? '∞' : $max ?></div><div class="l">lượt làm bài</div></div>
        </div>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Phần</th><th>Dạng câu hỏi</th><th class="text-center">Số câu</th><th class="text-right">Điểm</th></tr></thead>
          <tbody>
            <?php if ($struct['p1'] > 0): ?><tr><td><b>Phần I</b></td><td>Trắc nghiệm nhiều phương án lựa chọn<div class="text-sm text-muted">Mỗi câu chọn 1 trong 4 phương án A, B, C, D</div></td><td class="text-center"><?= $struct['p1'] ?></td><td class="text-right"><?= e(fmt_num($parts['p1']['max'])) ?></td></tr><?php endif; ?>
            <?php if ($struct['p2'] > 0): ?><tr><td><b>Phần II</b></td><td>Trắc nghiệm đúng / sai<div class="text-sm text-muted">Mỗi câu có 4 ý a), b), c), d) – chọn Đúng hoặc Sai</div></td><td class="text-center"><?= $struct['p2'] ?></td><td class="text-right"><?= e(fmt_num($parts['p2']['max'])) ?></td></tr><?php endif; ?>
            <?php if ($struct['p3'] > 0): ?><tr><td><b>Phần III</b></td><td>Trắc nghiệm trả lời ngắn<div class="text-sm text-muted">Tô/gõ đáp số tối đa <?= (int) $struct['p3_len'] ?> ký tự: dấu "−", chữ số, dấu phẩy</div></td><td class="text-center"><?= $struct['p3'] ?></td><td class="text-right"><?= e(fmt_num($parts['p3']['max'])) ?></td></tr><?php endif; ?>
            <?php if (!empty($struct['essay'])): ?><tr><td><b>Tự luận</b></td><td>Gõ bài làm vào ô trả lời<div class="text-sm text-muted">Giáo viên chấm sau khi thi</div></td><td class="text-center"><?= count($struct['essay']) ?></td><td class="text-right"><?= e(fmt_num($parts['essay']['max'])) ?></td></tr><?php endif; ?>
          </tbody>
        </table></div>
        <dl class="dl mt-3">
          <?php if ($s['start_at']): ?><dt>Bắt đầu</dt><dd><?= e(weekday_vi((int) $s['start_at'])) ?>, <?= e(fmt_dt($s['start_at'], 'H:i – d/m/Y')) ?></dd><?php endif; ?>
          <?php if ($s['end_at']): ?><dt>Kết thúc</dt><dd><?= e(fmt_dt($s['end_at'], 'H:i – d/m/Y')) ?></dd><?php endif; ?>
          <?php if ($lateUntil): ?><dt>Vào phòng muộn nhất</dt><dd><?= e(fmt_dt($lateUntil, 'H:i')) ?> (<?= (int) $s['late_join'] ?> phút sau giờ bắt đầu)</dd><?php endif; ?>
          <?php if ($s['room']): ?><dt>Phòng thi</dt><dd><?= e($s['room']) ?></dd><?php endif; ?>
        </dl>
      </div>
    </div>

    <div class="card rise rise-1">
      <div class="card-head"><h3><?= icon('scroll-text') ?> Quy định phòng thi</h3></div>
      <div class="card-body">
        <ul class="rule-list">
          <?php foreach ($rules as $r): ?>
            <li class="<?= e($r[1]) ?>"><?= icon($r[0]) ?><div><b><?= e($r[2]) ?></b><small><?= $r[3] ?></small></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <?php if ($s['_done']): ?>
      <div class="card rise rise-2">
        <div class="card-head"><h3><?= icon('history') ?> Các lần làm bài</h3></div>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Lần</th><th>Bắt đầu</th><th>Nộp bài</th><th>Trạng thái</th><th class="text-right">Điểm</th><th class="col-actions"></th></tr></thead>
          <tbody>
          <?php foreach (array_reverse($s['_done']) as $a):
              $show = Sessions::canSeeScore($s, $a);
              $mx = (float) (json_dec($a['score_detail'], [])['max'] ?? $maxScore); ?>
            <tr>
              <td>#<?= (int) $a['attempt_no'] ?><?= $s['_official'] && (int) $s['_official']['id'] === (int) $a['id'] && count($s['_done']) > 1 ? ' ' . badge('Tính điểm', 'success') : '' ?></td>
              <td class="text-sm"><?= e(fmt_dt($a['started_at'], 'H:i d/m/Y')) ?></td>
              <td class="text-sm"><?= e(fmt_dt($a['submitted_at'], 'H:i d/m/Y')) ?></td>
              <td><?= Attempts::statusBadge($a) ?></td>
              <td class="text-right"><?= $show && $a['score'] !== null ? '<span class="score-pill ' . score_class($a['score'], $mx ?: 10) . '">' . e(fmt_score($a['score'])) . '</span>' : '<span class="text-muted">–</span>' ?></td>
              <td class="col-actions"><a class="btn btn-sm btn-ghost" href="<?= e(url('student/result', ['aid' => $a['id']])) ?>"><?= icon('chevron-right') ?></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="stack lobby-cta" style="gap:20px">
    <div class="card card-accent rise">
      <div class="card-body">
        <?php if ($doing): ?>
          <div class="eyebrow"><?= icon('circle-play', 'sm') ?> Em đang làm dở</div>
          <h3 class="mb-1">Tiếp tục bài làm</h3>
          <p class="text-muted text-sm">Em đã làm <b><?= (int) $doing['answered'] ?></b> câu. Thời gian làm bài vẫn đang được tính, hãy vào lại ngay.</p>
          <a class="btn btn-primary btn-lg btn-block" href="<?= e(url('exam/room', ['aid' => $doing['id']])) ?>"><?= icon('pencil-line') ?> Tiếp tục làm bài</a>
        <?php elseif ($usedUp): ?>
          <div class="eyebrow"><?= icon('circle-check', 'sm') ?> Đã hoàn thành</div>
          <h3 class="mb-1"><?= $max === 1 ? 'Em đã làm bài thi này' : 'Em đã dùng hết ' . $max . ' lượt làm bài' ?></h3>
          <?php if ($s['_official']): ?><a class="btn btn-primary btn-block mt-2" href="<?= e(url('student/result', ['aid' => $s['_official']['id']])) ?>"><?= icon('clipboard-check') ?> Xem kết quả</a><?php endif; ?>
        <?php elseif ($st === 'upcoming'): ?>
          <div class="eyebrow"><?= icon('hourglass', 'sm') ?> Chưa đến giờ thi</div>
          <div class="text-muted text-sm">Bài thi bắt đầu sau</div>
          <div class="big-time countdown mb-2" data-countdown="<?= (int) $s['start_at'] ?>" data-reload="1" data-done="Đến giờ!">…</div>
          <p class="text-muted text-sm mb-0">Trang sẽ tự tải lại khi đến giờ. Trong lúc chờ, em hãy đọc kỹ quy định phòng thi và kiểm tra máy bên dưới.</p>
        <?php elseif ($st === 'paused'): ?>
          <div class="eyebrow"><?= icon('circle-pause', 'sm') ?> Tạm dừng</div>
          <h3 class="mb-1">Ca thi đang tạm dừng</h3>
          <p class="text-muted text-sm">Vui lòng chờ hướng dẫn của giám thị.</p>
          <a class="btn btn-block" href="<?= e(url('student/lobby', ['sid' => $s['id']])) ?>"><?= icon('refresh-cw') ?> Tải lại</a>
        <?php elseif (in_array($st, ['ended', 'closed'], true)): ?>
          <div class="eyebrow"><?= icon('circle-stop', 'sm') ?> Đã kết thúc</div>
          <h3 class="mb-1">Ca thi đã kết thúc</h3>
          <?php if (!$s['_done']): ?><p class="text-muted text-sm mb-0">Em không có bài làm trong ca thi này.</p><?php endif; ?>
          <?php if ($s['_official']): ?><a class="btn btn-primary btn-block mt-2" href="<?= e(url('student/result', ['aid' => $s['_official']['id']])) ?>"><?= icon('clipboard-check') ?> Xem kết quả</a><?php endif; ?>
        <?php elseif ($lateUntil && time() > $lateUntil): ?>
          <div class="eyebrow"><?= icon('clock-alert', 'sm') ?> Quá giờ vào phòng</div>
          <h3 class="mb-1">Đã hết thời gian vào phòng thi</h3>
          <p class="text-muted text-sm mb-0">Ca thi chỉ cho vào trong <?= (int) $s['late_join'] ?> phút đầu. Em hãy báo giám thị để được hỗ trợ.</p>
        <?php else: ?>
          <div class="eyebrow"><span class="dot-live"></span> Đang mở</div>
          <h3 class="mb-1">Sẵn sàng làm bài?</h3>
          <p class="text-muted text-sm">
            Thời gian làm bài <b><?= $duration > 0 ? e(fmt_duration($duration)) : 'không giới hạn' ?></b><?php if ($capEnd): ?>, nộp chậm nhất lúc <b><?= e(fmt_dt($capEnd, 'H:i')) ?></b><?php endif; ?>.
            Đồng hồ bắt đầu chạy ngay khi em bấm nút.
          </p>
          <form method="post" action="<?= e(url('student/start')) ?>" id="start-form">
            <?= csrf_field() ?>
            <input type="hidden" name="sid" value="<?= (int) $s['id'] ?>">
            <?php if ($s['access_code']): ?>
              <div class="field">
                <label for="access_code">Mã vào phòng thi <span class="text-danger">*</span></label>
                <div class="input-icon"><?= icon('key-round') ?><input class="input input-lg" id="access_code" name="access_code" required autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Giám thị đọc / ghi trên bảng"></div>
              </div>
            <?php endif; ?>
            <label class="check mb-3"><input type="checkbox" name="agree" value="1" required> <span>Em đã đọc và cam kết thực hiện đúng quy định phòng thi.</span></label>
            <button class="btn btn-primary btn-lg btn-block" type="submit" data-busy="Đang chuẩn bị đề…"><?= icon('circle-play') ?> Bắt đầu làm bài</button>
          </form>
          <?php if ($lateUntil): ?><div class="tile-note mt-2"><?= icon('clock') ?> Hết hạn vào phòng sau <span class="countdown" data-countdown="<?= $lateUntil ?>" data-reload="1">…</span></div><?php endif; ?>
          <?php if ($s['end_at'] && (int) $s['end_at'] - time() < 86400): ?><div class="tile-note mt-1"><?= icon('calendar-clock') ?> Ca thi đóng sau <span class="countdown" data-countdown="<?= (int) $s['end_at'] ?>" data-reload="1">…</span></div><?php elseif ($s['end_at']): ?><div class="tile-note mt-1"><?= icon('calendar-clock') ?> Ca thi mở đến <?= e(fmt_dt($s['end_at'], 'H:i d/m/Y')) ?></div><?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card rise rise-1">
      <div class="card-head"><h3><?= icon('scan-line') ?> Kiểm tra máy</h3><button type="button" class="btn btn-sm btn-ghost" id="recheck"><?= icon('refresh-cw') ?></button></div>
      <div class="card-body"><div class="check-list" id="checks"></div></div>
    </div>
  </div>
</div>

<?php \App\Core\View::push('scripts', '<script>window.TN_VIEWER = ' . js_json(asset('js/pdfviewer.js')) . ';</script>'); ?>
<?php \App\Core\View::push('scripts', <<<'JS'
<script>
TN.ready(function () {
  var form = document.getElementById('start-form');
  if (form) form.addEventListener('submit', function () { TN.busy(form.querySelector('[type=submit]'), true); });
  var box = document.getElementById('checks');
  function item(state, text, note) {
    var ic = { ok: 'circle-check', warn: 'triangle-alert', bad: 'circle-x', wait: 'hourglass' }[state];
    return '<div class="check-item ' + state + '">' + TN.icon(ic) + '<span>' + TN.esc(text) + '</span>' + (note ? '<small>' + TN.esc(note) + '</small>' : '') + '</div>';
  }
  function run() {
    var out = [];
    // Tương đương Chrome/Edge 98+, Firefox 94+, Safari 15.4+; sau đó nạp thử bộ hiển thị đề PDF cho chắc
    var modern = !!(window.fetch && window.Promise && window.IntersectionObserver && window.ResizeObserver && 'noModule' in document.createElement('script') &&
      typeof window.structuredClone === 'function' && typeof [].at === 'function' && typeof Object.hasOwn === 'function');
    out.push('<div id="br-check">' + item(modern ? 'wait' : 'bad', 'Trình duyệt hỗ trợ phòng thi', modern ? 'Đang kiểm tra bộ hiển thị đề…' : 'Trình duyệt quá cũ – hãy dùng Chrome/Edge 109 trở lên, Firefox hoặc Safari bản mới') + '</div>');
    var ls = false; try { localStorage.setItem('tn-test', '1'); localStorage.removeItem('tn-test'); ls = true; } catch (e) {}
    out.push(item(ls ? 'ok' : 'warn', 'Lưu dự phòng trên máy', ls ? '' : 'Không dùng được (chế độ ẩn danh?)'));
    out.push(item(navigator.cookieEnabled ? 'ok' : 'bad', 'Cookie', navigator.cookieEnabled ? '' : 'Cần bật cookie'));
    var w = window.innerWidth;
    out.push(item(w >= 1100 ? 'ok' : 'warn', 'Màn hình ' + window.screen.width + '×' + window.screen.height, w >= 1100 ? '' : 'Nhỏ – sẽ hiển thị dạng tab Đề / Phiếu'));
    var fs = !!(document.documentElement.requestFullscreen);
    out.push(item(fs ? 'ok' : 'warn', 'Chế độ toàn màn hình', fs ? '' : 'Không hỗ trợ'));
    var off = Math.round(TN.serverOffset / 1000);
    out.push(item(Math.abs(off) < 120 ? 'ok' : 'warn', 'Đồng hồ máy tính', Math.abs(off) < 120 ? 'Khớp giờ máy chủ' : 'Lệch ' + Math.abs(off) + ' giây – không sao, bài thi dùng giờ máy chủ'));
    out.push('<div id="net-check">' + item('wait', 'Kết nối máy chủ', 'Đang đo…') + '</div>');
    box.innerHTML = out.join('');
    if (modern) {
      var setBr = function (ok) {
        var el = document.getElementById('br-check');
        if (el) el.innerHTML = item(ok ? 'ok' : 'bad', 'Trình duyệt hỗ trợ phòng thi', ok ? '' : 'Không nạp được bộ hiển thị đề PDF – hãy cập nhật trình duyệt (Chrome/Edge 109 trở lên)');
      };
      var imp = null;
      try { imp = new Function('u', 'return import(u)'); } catch (e) { imp = null; }
      if (!imp) setBr(false);
      else if (!window.TN_VIEWER) setBr(true);
      else imp(window.TN_VIEWER).then(function () { setBr(true); }, function () { setBr(false); }); // cũng nạp sẵn vào bộ nhớ đệm cho phòng thi
    }
    var t0 = performance.now(), n = 0, sum = 0;
    function ping() {
      var s = performance.now();
      return fetch(TN.url('auth/csrf'), { cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.text().then(function () { if (!r.ok) throw 0; sum += performance.now() - s; n++; }); });
    }
    ping().then(ping).then(ping).then(function () {
      var ms = Math.round(sum / n);
      document.getElementById('net-check').innerHTML = item(ms < 800 ? 'ok' : 'warn', 'Kết nối máy chủ', ms + ' ms' + (ms < 300 ? ' · tốt' : ms < 800 ? ' · ổn' : ' · chậm'));
    }).catch(function () {
      document.getElementById('net-check').innerHTML = item('bad', 'Kết nối máy chủ', 'Không kết nối được');
    });
  }
  run();
  document.getElementById('recheck').addEventListener('click', run);
});
</script>
JS); ?>

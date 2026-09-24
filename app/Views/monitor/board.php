<?php
/** Màn hình trình chiếu cho phòng thi (máy chiếu / TV): giờ máy chủ, mã vào phòng, thời gian còn lại. */
$site = preg_replace('~index\.php$~', '', absolute_url(''));
?>
<style>
.board { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 28px; padding: 40px 24px; text-align: center; background: radial-gradient(1200px 700px at 50% -20%, var(--primary-soft), transparent 70%), var(--bg); }
.board-org { display: flex; align-items: center; gap: 14px; justify-content: center; color: var(--muted); font-weight: 600; font-size: 18px; }
.board-org img { width: 54px; height: 54px; object-fit: contain; }
.board h1 { font-size: clamp(28px, 4vw, 52px); margin: 0; letter-spacing: -.02em; }
.board .sub { font-size: clamp(16px, 1.8vw, 24px); color: var(--text-2); }
.board-clock { font: 800 clamp(64px, 11vw, 160px)/1 var(--font); letter-spacing: -.01em; font-variant-numeric: tabular-nums; color: var(--text); }
.board-row { display: flex; gap: 22px; flex-wrap: wrap; justify-content: center; }
.board-box { background: var(--surface); border: 1px solid var(--border); border-radius: 26px; padding: 22px 34px; box-shadow: var(--shadow); min-width: 280px; }
.board-box .l { color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; font-size: 14px; }
.board-box .v { font: 800 clamp(34px, 5vw, 64px)/1.15 var(--mono); letter-spacing: .12em; margin-top: 6px; color: var(--primary); }
.board-box .v.small { letter-spacing: 0; font-family: var(--font); font-variant-numeric: tabular-nums; font-size: clamp(24px, 2.8vw, 38px); color: var(--text); }
.board-steps { color: var(--text-2); font-size: clamp(15px, 1.5vw, 20px); line-height: 1.8; max-width: 980px; }
.board-steps code { font-size: .95em; background: var(--surface-3); padding: 2px 10px; border-radius: 8px; }
.board-full { position: fixed; top: 14px; right: 14px; }
</style>
<div class="board">
  <button class="btn btn-ghost board-full" type="button" onclick="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()"><?= icon('maximize') ?> Toàn màn hình</button>
  <div class="board-org"><img src="<?= e(logo_url()) ?>" alt=""><span><?= e(setting('org_name')) ?></span></div>
  <div>
    <h1><?= e($s['name']) ?></h1>
    <div class="sub"><?= e($subject) ?><?= $s['room'] ? ' · ' . e($s['room']) : '' ?> · Thời gian làm bài <?= $duration ? e(fmt_duration($duration)) : 'không giới hạn' ?></div>
  </div>
  <div class="board-clock" id="tn-board-clock">--:--:--</div>
  <div class="board-row">
    <?php if ($s['access_code']): ?>
      <div class="board-box"><div class="l">Mã vào phòng thi</div><div class="v"><?= e($s['access_code']) ?></div></div>
    <?php endif; ?>
    <?php if ($s['end_at']): ?>
      <div class="board-box"><div class="l">Ca thi kết thúc sau</div><div class="v small countdown" data-countdown="<?= (int) $s['end_at'] ?>" data-done="Đã hết giờ">…</div></div>
    <?php elseif ($s['start_at'] && (int) $s['start_at'] > time()): ?>
      <div class="board-box"><div class="l">Bắt đầu sau</div><div class="v small countdown" data-countdown="<?= (int) $s['start_at'] ?>" data-reload="1">…</div></div>
    <?php endif; ?>
  </div>
  <div class="board-steps">
    ① Mở trình duyệt, vào <code><?= e($site) ?></code> &nbsp; ② Đăng nhập bằng tài khoản được cấp &nbsp; ③ Chọn bài thi<?= $s['access_code'] ? ', nhập <b>mã vào phòng</b>' : '' ?> rồi bấm <b>Bắt đầu làm bài</b>.
  </div>
</div>
<?php \App\Core\View::push('scripts', <<<'JS'
<script>
TN.ready(function () {
  var el = document.getElementById('tn-board-clock');
  function tick() { var p = TN.parts(TN.now()); el.textContent = p.hour + ':' + p.minute + ':' + p.second; }
  tick(); setInterval(tick, 500);
  setTimeout(function () { location.reload(); }, 60000);
});
</script>
JS); ?>

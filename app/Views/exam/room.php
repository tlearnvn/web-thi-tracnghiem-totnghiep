<?php
/** Phòng thi (trang đầy đủ, không dùng layout). Biến: $cfg, $exam, $session, $variant, $subject, $title */
$h = $cfg['header'];
?>
<!doctype html>
<html lang="vi">
<head>
<?= \App\Core\View::partial('partials/head', ['title' => $title]) ?>
<link rel="stylesheet" href="<?= asset('css/exam.css') ?>">
</head>
<body class="exam-room">
<div class="xr" id="xr" data-pane="sheet">
  <header class="xr-top">
    <div class="xr-brand">
      <img src="<?= e(logo_url()) ?>" alt="">
      <div class="xr-title">
        <strong><?= e($h['exam']) ?></strong>
        <small><?= e($h['subject']) ?> · Mã đề <b><?= e($h['variant']) ?></b> · <?= e($h['name']) ?> – <?= e($h['code']) ?></small>
      </div>
    </div>
    <div class="xr-mid">
      <div class="prog-chip" title="Số câu đã làm"><div class="prog-ring" id="prog-ring" data-label="0%"></div><span id="prog-text">0 câu</span></div>
      <span class="save-chip" id="save-chip" aria-live="polite"><?= icon('circle-check') ?> Sẵn sàng</span>
      <div class="timer" id="timer" role="timer" aria-live="off">
        <?= icon('timer') ?>
        <div><small id="timer-label">Còn lại</small><div class="timer-val" id="timer-val">--:--</div></div>
      </div>
    </div>
    <div class="xr-right-tools">
      <button type="button" class="icon-btn" id="btn-msg" title="Thông báo từ giám thị" style="position:relative"><?= icon('bell') ?><span class="nav-badge" id="msg-badge" hidden style="position:absolute;top:-2px;right:-4px">0</span></button>
      <button type="button" class="icon-btn hide-sm" id="btn-help" title="Hướng dẫn & phím tắt"><?= icon('keyboard') ?></button>
      <button type="button" class="icon-btn hide-sm" id="btn-full" title="Toàn màn hình"><?= icon('maximize') ?></button>
      <button type="button" class="icon-btn" data-theme-toggle title="Giao diện sáng / tối"><?= icon('sun') ?></button>
      <button type="button" class="btn btn-primary" id="btn-submit"><?= icon('send') ?> Nộp bài</button>
    </div>
  </header>
  <nav class="xr-tabs" aria-label="Chuyển giữa đề thi và phiếu trả lời">
    <button type="button" data-pane="pdf"><?= icon('file-text') ?> Đề thi</button>
    <button type="button" data-pane="sheet" class="active"><?= icon('clipboard-list') ?> Phiếu trả lời</button>
  </nav>
  <div class="xr-body" id="xr-body">
    <div class="xr-left"><div id="pdf" style="height:100%"></div></div>
    <div class="xr-split" tabindex="0" role="separator" aria-orientation="vertical" aria-label="Kéo để đổi độ rộng 2 cột (phím ← →)">
      <div class="xr-split-btns"><button type="button" data-collapse="left" title="Thu gọn đề thi"><?= icon('chevrons-left') ?></button><button type="button" data-collapse="right" title="Thu gọn phiếu trả lời"><?= icon('chevrons-right') ?></button></div>
    </div>
    <div class="xr-right" id="xr-right">
      <div class="xr-nav">
        <div class="seg seg-sm" id="q-filter">
          <button type="button" class="active" data-f="all">Tất cả</button>
          <button type="button" data-f="todo">Chưa làm <b id="cnt-todo"></b></button>
          <button type="button" data-f="flag"><?= icon('flag', 'sm') ?> <b id="cnt-flag"></b></button>
        </div>
        <div class="qchips" id="qchips"></div>
      </div>
      <div class="xr-sheet-wrap">
        <div id="sheet"></div>
        <div class="sheet-end">
          <div><b>Em đã làm xong?</b><div class="text-muted text-sm">Kiểm tra lại các câu chưa làm và câu đã đánh dấu <?= icon('flag', 'sm') ?> trước khi nộp.</div></div>
          <button type="button" class="btn btn-primary" data-submit><?= icon('send') ?> Nộp bài</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="xr-overlay" id="xr-boot">
  <div class="xr-overlay-card">
    <div class="big-ic"><span class="spinner lg"></span></div>
    <h2>Đang chuẩn bị phòng thi…</h2>
    <p id="boot-msg">Đang kết nối máy chủ và tải bài làm của em.</p>
  </div>
</div>
<noscript><div class="xr-overlay"><div class="xr-overlay-card danger"><h2>Cần bật JavaScript</h2><p>Trình duyệt đang tắt JavaScript nên không thể làm bài. Hãy bật lại hoặc dùng trình duyệt khác.</p></div></div></noscript>

<script>window.EXAM_CFG = <?= js_json($cfg) ?>;</script>
<?= \App\Core\View::partial('partials/scripts') ?>
<script src="<?= asset('js/answersheet.js') ?>"></script>
<script src="<?= asset('js/split.js') ?>"></script>
<script src="<?= asset('js/exam.js') ?>"></script>
<script type="module">
import { PdfViewer } from <?= js_json(asset('js/pdfviewer.js')) ?>;
window.PdfViewer = PdfViewer;
window.dispatchEvent(new Event('tn:pdfviewer'));
</script>
</body>
</html>

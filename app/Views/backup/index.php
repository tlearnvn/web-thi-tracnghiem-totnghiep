<?php
$labels = ['users' => 'Tài khoản', 'classes' => 'Lớp', 'exams' => 'Đề thi', 'exam_variants' => 'Mã đề', 'exam_sessions' => 'Ca thi', 'attempts' => 'Bài làm', 'files' => 'Tệp (PDF, logo…)'];
$daysSince = $lastBackup ? (int) floor((time() - $lastBackup) / 86400) : null;
?>
<div class="page-head">
  <div><h1>Sao lưu & phục hồi</h1><div class="sub">Toàn bộ dữ liệu – tài khoản, đề thi PDF, đáp án, bài làm, cài đặt, logo – nằm trong <b><?= $isSqlite ? 'một tệp SQLite' : 'CSDL MySQL' ?></b>. Hãy tải bản sao lưu định kỳ về máy.</div></div>
</div>

<?php if ($daysSince === null || $daysSince >= 7): ?>
  <div class="alert alert-warning mb-3"><?= icon('triangle-alert') ?><div><?= $daysSince === null ? 'Hệ thống <b>chưa từng được sao lưu</b>.' : 'Lần sao lưu gần nhất cách đây <b>' . $daysSince . ' ngày</b>.' ?> Nên sao lưu sau mỗi đợt thi quan trọng.</div></div>
<?php endif; ?>

<div class="grid grid-sidebar">
  <div class="stack" style="gap:20px">
    <div class="card card-accent">
      <div class="card-head"><h3><?= icon('download') ?> Tạo bản sao lưu</h3><?php if ($lastBackup): ?><span class="hint">Gần nhất: <?= e(fmt_dt($lastBackup)) ?></span><?php endif; ?></div>
      <div class="card-body">
        <p class="text-muted">Tệp <code>.tnbak</code> chứa toàn bộ dữ liệu, được nén và tải thẳng về máy (không lưu lại trên hosting). Có thể phục hồi vào hệ thống dùng <b>SQLite hoặc MySQL</b> – dùng cách này để <b>chuyển đổi</b> giữa hai loại CSDL hoặc chuyển sang hosting khác.</p>
        <div class="mini-stats mb-3">
          <?php foreach ($counts as $k => $n): ?><div class="mini-stat"><div class="v"><?= e(fmt_num($n, 0)) ?></div><div class="l"><?= e($labels[$k] ?? $k) ?></div></div><?php endforeach; ?>
          <div class="mini-stat"><div class="v"><?= e(fmt_bytes($info['size'] ?? 0)) ?></div><div class="l">Dung lượng CSDL</div></div>
        </div>
        <form method="get" action="<?= e(base_uri() . 'index.php') ?>" class="row">
          <input type="hidden" name="r" value="backup/download">
          <label class="check"><input type="checkbox" name="logs" value="1" checked> <span>Kèm nhật ký hoạt động & lỗi</span></label>
          <span class="grow"></span>
          <button class="btn btn-primary" type="submit"><?= icon('download') ?> Tải bản sao lưu (.tnbak)</button>
        </form>
      </div>
      <?php if ($isSqlite): ?>
        <div class="card-foot row between">
          <div class="text-sm text-muted"><?= icon('database', 'sm') ?> Hoặc tải nguyên tệp CSDL SQLite (mở được bằng DB Browser for SQLite).</div>
          <a class="btn btn-sm" href="<?= e(url('backup/sqlite')) ?>"><?= icon('hard-drive') ?> Tải tệp .sqlite</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="card" id="restore-card">
      <div class="card-head"><h3><?= icon('upload') ?> Phục hồi từ bản sao lưu</h3><?php if ($lastRestore): ?><span class="hint">Lần phục hồi gần nhất: <?= e(fmt_dt($lastRestore)) ?></span><?php endif; ?></div>
      <div class="card-body">
        <div class="alert alert-danger mb-3"><?= icon('octagon-x') ?><div><b>Phục hồi sẽ XÓA toàn bộ dữ liệu hiện tại</b> và thay bằng dữ liệu trong bản sao lưu. Mọi người đang đăng nhập sẽ bị đăng xuất. Không thực hiện khi đang có ca thi. Nên tải bản sao lưu hiện tại trước khi phục hồi.</div></div>
        <div id="rs-pick">
          <label class="dropzone">
            <input type="file" accept=".tnbak,application/octet-stream" id="rs-file">
            <div class="dz-icon"><?= icon('cloud-upload') ?></div>
            <div class="dz-title">Chọn tệp .tnbak hoặc kéo thả vào đây</div>
            <div class="dz-hint">Tải lên theo từng khúc 512 KB – không vướng giới hạn dung lượng tải lên của hosting.</div>
          </label>
        </div>
        <div id="rs-upload" hidden>
          <div class="progress-label"><span id="rs-up-label">Đang tải lên…</span><span id="rs-up-pct">0%</span></div>
          <div class="progress"><span id="rs-up-bar" style="width:0%"></span></div>
        </div>
        <div id="rs-meta" hidden></div>
        <div id="rs-run" hidden>
          <div class="progress-label"><span id="rs-run-label">Đang phục hồi…</span><span id="rs-run-pct">0%</span></div>
          <div class="progress"><span id="rs-run-bar" style="width:0%"></span></div>
          <p class="text-sm text-muted mt-2"><?= icon('info', 'sm') ?> Không đóng trang này cho đến khi hoàn tất. Hệ thống tạm ở chế độ bảo trì trong lúc phục hồi.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('server') ?> Cơ sở dữ liệu</h3></div>
      <div class="card-body kv-list">
        <div class="kv"><span>Loại</span><span><?= e($info['version'] ?? '') ?></span></div>
        <?php if (!empty($info['journal'])): ?><div class="kv"><span>Chế độ ghi</span><span><?= e($info['journal']) ?></span></div><?php endif; ?>
        <div class="kv"><span>Dung lượng</span><span><?= e(fmt_bytes($info['size'] ?? 0)) ?></span></div>
        <div class="kv"><span>Tệp lưu trong CSDL</span><span><?= e(fmt_bytes($filesSize)) ?></span></div>
        <?php if (!empty($info['max_packet'])): ?><div class="kv"><span>max_allowed_packet</span><span><?= e(fmt_bytes($info['max_packet'])) ?></span></div><?php endif; ?>
      </div>
    </div>
    <div class="callout text-sm">
      <b><?= icon('lightbulb', 'sm') ?> Chuyển từ SQLite sang MySQL (hoặc ngược lại)</b>
      <ol style="margin:6px 0 0;padding-left:18px;line-height:1.7">
        <li>Tải bản sao lưu <code>.tnbak</code> ở hệ thống cũ.</li>
        <li>Cài đặt mới (xóa <code>storage/config.php</code> rồi mở <code>install.php</code>) và chọn loại CSDL mong muốn.</li>
        <li>Đăng nhập tài khoản quản trị mới → <i>Sao lưu & phục hồi</i> → phục hồi tệp vừa tải.</li>
        <li>Đăng nhập lại bằng tài khoản của hệ thống cũ.</li>
      </ol>
    </div>
  </div>
</div>
<?php \App\Core\View::push('scripts', '<script>
TN.ready(function () {
  var U = { init: ' . js_json(url('backup/upload-init')) . ', chunk: ' . js_json(url('backup/upload-chunk')) . ', finish: ' . js_json(url('backup/upload-finish')) . ', start: ' . js_json(url('backup/restore-start')) . ', step: ' . js_json(url('backup/restore-step')) . ', cancel: ' . js_json(url('backup/cancel')) . ' };
  var $ = function (id) { return document.getElementById(id); };
  var labels = ' . js_json(['users' => 'tài khoản', 'classes' => 'lớp', 'exams' => 'đề thi', 'exam_variants' => 'mã đề', 'exam_keys' => 'đáp án', 'exam_sessions' => 'ca thi', 'attempts' => 'bài làm', 'attempt_events' => 'nhật ký bài làm', 'files' => 'tệp', 'file_chunks' => 'khối dữ liệu tệp', 'settings' => 'cài đặt', 'audit_logs' => 'nhật ký hoạt động']) . ';
  var token = null;
  function fmtBytes(n) { return n > 1048576 ? TN.fmtNum(n / 1048576, 1) + " MB" : TN.fmtNum(n / 1024, 0) + " KB"; }
  function reset(msg) {
    token = null; $("rs-pick").hidden = false; $("rs-upload").hidden = true; $("rs-meta").hidden = true; $("rs-run").hidden = true; $("rs-file").value = "";
    if (msg) TN.toast(msg, "error");
  }
  $("rs-file").addEventListener("change", function () {
    var f = this.files[0]; if (!f) return;
    $("rs-pick").hidden = true; $("rs-upload").hidden = false;
    TN.api(U.init, { data: { size: f.size, name: f.name } }).then(function (r) {
      token = r.token; var CH = r.chunk, total = Math.ceil(f.size / CH), i = 0;
      function send(tries) {
        if (i >= total) return Promise.resolve();
        var blob = f.slice(i * CH, Math.min(f.size, (i + 1) * CH));
        return TN.api(U.chunk + "&token=" + token + "&seq=" + i, { raw: blob, timeout: 120000 }).then(function () {
          i++;
          var p = Math.round(i * 100 / total);
          $("rs-up-bar").style.width = p + "%"; $("rs-up-pct").textContent = p + "%";
          $("rs-up-label").textContent = "Đang tải lên " + fmtBytes(Math.min(f.size, i * CH)) + " / " + fmtBytes(f.size);
          return send(0);
        }, function (e) { if (tries < 4 && (e.network || e.status >= 500)) return new Promise(function (res) { setTimeout(res, 1000 * (tries + 1)); }).then(function () { return send(tries + 1); }); throw e; });
      }
      return send(0);
    }).then(function () {
      $("rs-up-label").textContent = "Đang kiểm tra tệp…";
      return TN.api(U.finish, { data: { token: token } });
    }).then(function (r) {
      $("rs-upload").hidden = true;
      var m = r.meta, rows = Object.keys(m.counts || {}).filter(function (k) { return labels[k] && m.counts[k]; }).map(function (k) { return "<div class=\"kv\"><span>" + TN.esc(labels[k]) + "</span><span>" + TN.fmtNum(m.counts[k], 0) + "</span></div>"; }).join("");
      var box = $("rs-meta"); box.hidden = false;
      box.innerHTML = "<div class=\"callout mb-3\"><b>Bản sao lưu hợp lệ</b><div class=\"text-sm text-muted\">" + TN.esc(m.org || "") + " · tạo lúc " + TN.esc(r.created) + " · phiên bản v" + TN.esc(m.version || "?") + " · CSDL " + TN.esc(m.driver || "") + "</div></div>" +
        "<div class=\"kv-list mb-3\">" + rows + "</div>" +
        "<div class=\"field\"><label>Gõ <b>PHỤC HỒI</b> để xác nhận</label><input class=\"input\" id=\"rs-confirm\" autocomplete=\"off\" placeholder=\"PHỤC HỒI\"></div>" +
        "<div class=\"row\"><button class=\"btn btn-danger\" id=\"rs-go\">" + TN.icon("rotate-ccw") + " Xóa dữ liệu hiện tại & phục hồi</button><button class=\"btn btn-ghost\" id=\"rs-cancel\">Hủy</button></div>";
      $("rs-cancel").onclick = function () { TN.api(U.cancel, { data: {} }).then(function () { reset(); }); };
      $("rs-go").onclick = function () {
        var b = this; TN.busy(b, true);
        TN.api(U.start, { data: { token: token, confirm: $("rs-confirm").value } }).then(function () {
          $("rs-meta").hidden = true; $("rs-run").hidden = false; step(0);
        }, function (e) { TN.busy(b, false); TN.toast(e.message, "error"); });
      };
    }).catch(function (e) { reset(e.message); });
  });
  function step(tries) {
    TN.api(U.step, { data: { token: token }, timeout: 90000 }).then(function (r) {
      var p = Math.round((r.progress || 0) * 100);
      $("rs-run-bar").style.width = p + "%"; $("rs-run-pct").textContent = p + "%";
      $("rs-run-label").textContent = r.done ? "Hoàn tất!" : "Đang phục hồi " + (labels[r.table] || r.table || "") + " · " + TN.fmtNum(r.rows, 0) + " bản ghi";
      if (r.done) { TN.toast("Phục hồi thành công. Vui lòng đăng nhập lại.", "success"); setTimeout(function () { location.href = r.redirect; }, 1500); return; }
      step(0);
    }, function (e) {
      if (tries < 5 && (e.network || e.status === 0 || e.status === 502 || e.status === 503 || e.status === 504)) { setTimeout(function () { step(tries + 1); }, 2000 * (tries + 1)); return; }
      $("rs-run-label").textContent = "Lỗi: " + e.message;
      TN.toast(e.message, "error", "Phục hồi thất bại", 0);
    });
  }
});
</script>'); ?>

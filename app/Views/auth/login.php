<?php
$notice = trim((string) setting('login_notice', ''));
?>
<div class="auth">
  <section class="auth-hero">
    <div class="auth-brand rise">
      <img src="<?= e(logo_url()) ?>" alt="Logo">
      <div>
        <strong><?= e(setting('site_short_name')) ?></strong>
        <small><?= e(setting('org_name')) ?><?= setting('org_parent') ? ' · ' . e(setting('org_parent')) : '' ?></small>
      </div>
    </div>

    <div>
      <div class="eyebrow rise rise-1" style="color:rgba(255,255,255,.85)"><?= icon('sparkles', 'sm') ?> Định dạng đề thi tốt nghiệp THPT từ năm 2025</div>
      <h1 class="rise rise-1"><?= e(setting('login_title')) ?></h1>
      <p class="rise rise-2"><?= e(setting('login_subtitle')) ?></p>
      <div class="auth-features rise rise-3">
        <div class="auth-feature"><?= icon('list-checks') ?><strong>Đúng mẫu phiếu của Bộ</strong><span>Phần I, II, III và tự luận</span></div>
        <div class="auth-feature"><?= icon('save') ?><strong>Tự lưu bài liên tục</strong><span>Mất mạng, mất điện vẫn giữ bài</span></div>
        <div class="auth-feature"><?= icon('chart-column') ?><strong>Chấm & thống kê ngay</strong><span>Xuất Excel đầy đủ</span></div>
      </div>
      <div class="auth-sheet rise rise-4" aria-hidden="true">
        <svg width="330" height="118" viewBox="0 0 330 118" fill="none">
          <rect x="1" y="1" width="328" height="116" rx="14" fill="rgba(255,255,255,.1)" stroke="rgba(255,255,255,.25)"/>
          <text x="16" y="24" fill="#fff" font-size="11" font-weight="700" font-family="Be Vietnam Pro, sans-serif" opacity=".9">PHẦN I</text>
          <?php $fill = [1, 3, 0, 2, 1, 3]; for ($r = 0; $r < 3; $r++): for ($c = 0; $c < 4; $c++): $x = 36 + $c * 22; $y = 44 + $r * 24; ?>
            <circle cx="<?= $x ?>" cy="<?= $y ?>" r="7" stroke="#fda4c4" stroke-width="1.6" fill="<?= $fill[$r] === $c ? '#fff' : 'none' ?>"/>
          <?php endfor; endfor; ?>
          <text x="140" y="24" fill="#fff" font-size="11" font-weight="700" font-family="Be Vietnam Pro, sans-serif" opacity=".9">PHẦN II</text>
          <?php $ds = [0, 1, 0]; for ($r = 0; $r < 3; $r++): for ($c = 0; $c < 2; $c++): $x = 156 + $c * 24; $y = 44 + $r * 24; ?>
            <circle cx="<?= $x ?>" cy="<?= $y ?>" r="7" stroke="#fda4c4" stroke-width="1.6" fill="<?= $ds[$r] === $c ? '#fff' : 'none' ?>"/>
          <?php endfor; endfor; ?>
          <text x="226" y="24" fill="#fff" font-size="11" font-weight="700" font-family="Be Vietnam Pro, sans-serif" opacity=".9">PHẦN III</text>
          <rect x="226" y="34" width="86" height="22" rx="6" fill="rgba(255,255,255,.18)"/>
          <text x="240" y="50" fill="#fff" font-size="13" font-weight="800" font-family="Be Vietnam Pro, sans-serif" letter-spacing="6">-1,5</text>
          <?php for ($c = 0; $c < 4; $c++): ?><circle cx="<?= 236 + $c * 22 ?>" cy="76" r="7" stroke="#fda4c4" stroke-width="1.6" fill="<?= $c === 1 ? '#fff' : 'none' ?>"/><circle cx="<?= 236 + $c * 22 ?>" cy="100" r="7" stroke="#fda4c4" stroke-width="1.6" fill="<?= $c === 3 ? '#fff' : 'none' ?>"/><?php endfor; ?>
        </svg>
      </div>
    </div>

    <div class="auth-foot"><?= render_footer_text((string) setting('footer_text', '')) ?></div>
  </section>

  <section class="auth-form-wrap">
    <div class="auth-card rise">
      <h2>Đăng nhập</h2>
      <p class="lead">Nhập tài khoản do nhà trường cấp để vào phòng thi.</p>
      <?php if ($notice !== ''): ?>
        <div class="alert alert-warning mb-3"><?= icon('megaphone') ?><div><?= nl2br(e($notice)) ?></div></div>
      <?php endif; ?>
      <div class="card">
        <form method="post" action="<?= e(url('login')) ?>" class="stack" autocomplete="on">
          <?= csrf_field() ?>
          <div class="field">
            <label for="username">Tên đăng nhập</label>
            <div class="input-icon"><?= icon('user') ?><input class="input input-lg" id="username" name="username" value="<?= e(old('username')) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus placeholder="Mã học sinh hoặc tên tài khoản"></div>
          </div>
          <div class="field">
            <label for="password">Mật khẩu</label>
            <div class="input-icon"><?= icon('lock') ?><input class="input input-lg" id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••"><button type="button" class="toggle-pw" aria-label="Hiện mật khẩu"><?= icon('eye') ?></button></div>
          </div>
          <button class="btn btn-primary btn-lg btn-block mt-1" type="submit"><?= icon('log-in') ?> Đăng nhập</button>
        </form>
        <div class="divider-text">Hỗ trợ</div>
        <p class="text-sm text-muted mb-0"><?= icon('circle-help', 'sm') ?> Quên mật khẩu? Liên hệ giáo viên chủ nhiệm hoặc quản trị viên để được cấp lại.</p>
      </div>
      <p class="text-center text-sm text-muted mt-3">
        <span id="tn-clock" class="clock" style="display:inline-flex"></span>
        <br><span class="version-pill mt-1" style="display:inline-block">Phiên bản <?= e(TN_VERSION) ?></span>
      </p>
    </div>
  </section>
</div>

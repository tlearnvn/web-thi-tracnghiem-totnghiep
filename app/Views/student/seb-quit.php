<div class="bare">
  <div class="bare-card rise">
    <div class="empty-icon" style="width:96px;height:96px;border-radius:30px"><?= icon('door-open', 'ic-lg') ?></div>
    <h2>Thoát Safe Exam Browser</h2>
    <?php if (\App\Lib\Seb::version() !== null): ?>
      <p class="text-muted">Safe Exam Browser sẽ hỏi em xác nhận rồi tự đóng. Nếu cửa sổ vẫn còn, em bấm nút thoát ở góc thanh công cụ của SEB (có thể cần giám thị nhập mật khẩu).</p>
    <?php else: ?>
      <p class="text-muted">Trang này dùng để thoát Safe Exam Browser sau khi làm bài. Em đang dùng trình duyệt thường nên có thể đóng tab này.</p>
    <?php endif; ?>
    <a class="btn" href="<?= e(url('student')) ?>"><?= icon('house') ?> Về trang Bài thi của em</a>
  </div>
</div>

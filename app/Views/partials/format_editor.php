<?php
/**
 * Bộ chỉnh cấu trúc đề & cách tính điểm (dùng cho Môn thi và Đề thi).
 * Biến: $structure, $scoring, $locked (bool – đã có bài làm, cảnh báo khi đổi)
 */
use App\Lib\Scoring;

$st = $structure;
$sc = $scoring;
$essay = $st['essay'] ?: [];
?>
<div class="card" id="format-editor">
  <div class="card-head"><h3><?= icon('layout-grid') ?> Cấu trúc đề</h3><span class="hint" id="fmt-summary"></span></div>
  <div class="card-body">
    <?php if (!empty($locked)): ?>
      <div class="alert alert-warning mb-3"><?= icon('triangle-alert') ?><div>Đề đã có bài làm. Nếu đổi cấu trúc / cách tính điểm, hãy vào <b>Kết quả → Chấm lại</b> để cập nhật điểm.</div></div>
    <?php endif; ?>
    <div class="form-grid cols-4">
      <div class="field"><label>Phần I – số câu (A, B, C, D)</label><input class="input" type="number" name="p1" min="0" max="120" value="<?= (int) $st['p1'] ?>" data-fmt></div>
      <div class="field"><label>Phần II – số câu đúng/sai</label><input class="input" type="number" name="p2" min="0" max="30" value="<?= (int) $st['p2'] ?>" data-fmt><div class="help">Mỗi câu 4 ý a, b, c, d</div></div>
      <div class="field"><label>Phần III – số câu trả lời ngắn</label><input class="input" type="number" name="p3" min="0" max="30" value="<?= (int) $st['p3'] ?>" data-fmt></div>
      <div class="field"><label>Số ô tô ở Phần III</label><select class="select" name="p3_len" data-fmt><?php foreach ([4, 5, 6] as $n): ?><option value="<?= $n ?>"<?= selected($st['p3_len'], $n) ?>><?= $n ?> ký tự<?= $n === 4 ? ' (mẫu của Bộ)' : '' ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="section-title mt-3"><?= icon('pen-line', 'sm') ?> Phần tự luận (giáo viên chấm) <button type="button" class="btn btn-xs btn-soft" id="add-essay"><?= icon('plus') ?> Thêm câu</button></div>
    <div id="essay-list" class="stack-sm">
      <?php foreach ($essay as $e): ?>
        <div class="row nowrap essay-row"><input class="input" name="essay_label[]" value="<?= e($e['label']) ?>" placeholder="Tên câu" style="max-width:180px"><input class="input" name="essay_points[]" value="<?= e(fmt_num($e['points'])) ?>" placeholder="Điểm" style="max-width:90px" data-fmt><input class="input" name="essay_hint[]" value="<?= e($e['hint']) ?>" placeholder="Gợi ý / yêu cầu (tùy chọn)"><button type="button" class="btn btn-icon btn-ghost" data-remove><?= icon('x') ?></button></div>
      <?php endforeach; ?>
    </div>
    <p class="help mt-1" id="essay-empty"<?= $essay ? ' hidden' : '' ?>>Không có câu tự luận. (Môn Ngữ văn: 7 câu – đọc hiểu 4đ, viết 6đ.)</p>
  </div>
</div>

<div class="card mt-3">
  <div class="card-head"><h3><?= icon('calculator') ?> Cách tính điểm</h3><span class="badge badge-primary badge-lg" id="fmt-max">Tối đa: –</span></div>
  <div class="card-body">
    <div class="radio-cards">
      <?php foreach ([
          'moet2025' => ['badge-check', 'Theo quy định của Bộ GD&ĐT', 'Phần I 0,25đ · Phần II 0,1/0,25/0,5/1đ · Phần III theo môn'],
          'custom' => ['sliders-horizontal', 'Tùy chỉnh', 'Tự đặt điểm từng phần, trừ điểm câu sai, quy về thang điểm bất kỳ'],
          'ratio' => ['percent', 'Theo tỉ lệ đúng', 'Điểm = số câu/ý đúng ÷ tổng số × thang điểm'],
      ] as $k => $v): ?>
        <label class="radio-card<?= $sc['scheme'] === $k ? ' checked' : '' ?>"><input type="radio" name="scheme" value="<?= $k ?>"<?= checked($sc['scheme'] === $k) ?> data-fmt><span class="rc-icon"><?= icon($v[0]) ?></span><span><span class="rc-title"><?= e($v[1]) ?></span><span class="rc-desc"><?= e($v[2]) ?></span></span></label>
      <?php endforeach; ?>
    </div>

    <div class="form-grid cols-4 mt-3" data-scheme="moet2025 custom">
      <div class="field" data-scheme="custom"><label>Phần I – điểm/câu đúng</label><input class="input" name="p1_point" value="<?= e(fmt_num($sc['p1_point'])) ?>" data-fmt></div>
      <div class="field" data-scheme="custom"><label>Phần I – trừ điểm câu sai</label><input class="input" name="p1_penalty" value="<?= e(fmt_num($sc['p1_penalty'])) ?>" data-fmt></div>
      <div class="field"><label>Phần III – điểm/câu đúng</label><input class="input" name="p3_point" value="<?= e(fmt_num($sc['p3_point'])) ?>" data-fmt list="p3pts"><datalist id="p3pts"><option value="0,5">Toán</option><option value="0,25">Lí, Hóa, Sinh, Địa</option></datalist><div class="help">Toán 0,5 · các môn khác 0,25</div></div>
      <div class="field" data-scheme="custom"><label>Phần II – cách chấm</label><select class="select" name="p2_mode" data-fmt><option value="table"<?= selected($sc['p2_mode'], 'table') ?>>Theo số ý đúng (bảng)</option><option value="per_item"<?= selected($sc['p2_mode'], 'per_item') ?>>Mỗi ý đúng một số điểm</option><option value="all"<?= selected($sc['p2_mode'], 'all') ?>>Chỉ tính khi đúng cả 4 ý</option></select></div>
    </div>
    <div class="form-grid cols-4 mt-2" data-scheme="custom" data-p2mode="table">
      <?php for ($k = 1; $k <= 4; $k++): ?><div class="field"><label>Đúng <?= $k ?> ý</label><input class="input" name="p2_t<?= $k ?>" value="<?= e(fmt_num($sc['p2_table'][$k])) ?>" data-fmt></div><?php endfor; ?>
    </div>
    <div class="form-grid cols-4 mt-2" data-scheme="custom" data-p2mode="per_item"><div class="field"><label>Điểm mỗi ý đúng</label><input class="input" name="p2_item_point" value="<?= e(fmt_num($sc['p2_item_point'])) ?>" data-fmt></div></div>
    <div class="form-grid cols-4 mt-2" data-scheme="custom" data-p2mode="all"><div class="field"><label>Điểm khi đúng cả 4 ý</label><input class="input" name="p2_all_point" value="<?= e(fmt_num($sc['p2_all_point'])) ?>" data-fmt></div></div>

    <div class="form-grid cols-4 mt-3">
      <div class="field" data-scheme="custom ratio"><label>Thang điểm</label><input class="input" name="max_score" value="<?= e(fmt_num($sc['max_score'])) ?>" data-fmt></div>
      <label class="check" data-scheme="custom" style="align-self:end;padding-bottom:10px"><input type="checkbox" name="scale" value="1"<?= checked($sc['scale']) ?> data-fmt><span>Quy đổi tổng điểm về thang điểm</span></label>
      <div class="field"><label>Làm tròn</label><select class="select" name="rounding" data-fmt><?php foreach (Scoring::ROUNDINGS as $k => $v): ?><option value="<?= e($k) ?>"<?= selected((string) (float) $sc['rounding'], (string) (float) $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Kiểu làm tròn</label><select class="select" name="round_mode"><option value="round"<?= selected($sc['round_mode'], 'round') ?>>Gần nhất</option><option value="up"<?= selected($sc['round_mode'], 'up') ?>>Lên</option><option value="down"<?= selected($sc['round_mode'], 'down') ?>>Xuống</option></select></div>
      <div class="field span-2"><label>So khớp Phần III</label><select class="select" name="p3_compare"><option value="numeric"<?= selected($sc['p3_compare'], 'numeric') ?>>Theo giá trị số (0,50 = 0,5; khuyên dùng)</option><option value="exact"<?= selected($sc['p3_compare'], 'exact') ?>>Khớp chính xác từng ký tự</option></select></div>
      <label class="check span-2" style="align-self:end;padding-bottom:10px"><input type="checkbox" name="min_zero" value="1"<?= checked($sc['min_zero']) ?>><span>Không để tổng điểm âm (khi có trừ điểm)</span></label>
    </div>
  </div>
</div>

<template id="essay-tpl"><div class="row nowrap essay-row"><input class="input" name="essay_label[]" placeholder="Tên câu" style="max-width:180px"><input class="input" name="essay_points[]" value="1" placeholder="Điểm" style="max-width:90px" data-fmt><input class="input" name="essay_hint[]" placeholder="Gợi ý / yêu cầu (tùy chọn)"><button type="button" class="btn btn-icon btn-ghost" data-remove><?= icon('x') ?></button></div></template>
<?php \App\Core\View::push('scripts', <<<'JS'
<script>
TN.ready(function () {
  var box = document.getElementById('format-editor');
  if (!box) return;
  var form = box.closest('form');
  var num = function (n) { var v = form.querySelector('[name="' + n + '"]'); return v ? parseFloat(String(v.value).replace(',', '.')) || 0 : 0; };
  var list = document.getElementById('essay-list'), tpl = document.getElementById('essay-tpl');
  document.getElementById('add-essay').onclick = function () { list.appendChild(tpl.content.cloneNode(true)); calc(); };
  list.addEventListener('click', function (e) { var b = e.target.closest('[data-remove]'); if (b) { b.closest('.essay-row').remove(); calc(); } });
  function calc() {
    var scheme = (form.querySelector('[name=scheme]:checked') || {}).value || 'moet2025';
    form.querySelectorAll('[data-scheme]').forEach(function (el) { el.hidden = el.dataset.scheme.split(' ').indexOf(scheme) < 0; });
    var mode = form.querySelector('[name=p2_mode]').value;
    form.querySelectorAll('[data-p2mode]').forEach(function (el) { el.hidden = scheme !== 'custom' || el.dataset.p2mode !== mode; });
    form.querySelectorAll('.radio-card').forEach(function (c) { var i = c.querySelector('input'); if (i && i.name === 'scheme') c.classList.toggle('checked', i.checked); });
    var p1 = num('p1'), p2 = num('p2'), p3 = num('p3');
    var essay = 0, ne = 0;
    list.querySelectorAll('[name="essay_points[]"]').forEach(function (i) { essay += parseFloat(String(i.value).replace(',', '.')) || 0; ne++; });
    document.getElementById('essay-empty').hidden = ne > 0;
    var max;
    if (scheme === 'ratio') max = num('max_score');
    else {
      var p1p = scheme === 'moet2025' ? 0.25 : num('p1_point');
      var p2max = scheme === 'moet2025' ? 1 : (mode === 'table' ? num('p2_t4') : mode === 'per_item' ? 4 * num('p2_item_point') : num('p2_all_point'));
      max = p1 * p1p + p2 * p2max + p3 * num('p3_point') + essay;
      if (scheme === 'custom' && form.querySelector('[name=scale]').checked) max = num('max_score');
    }
    var el = document.getElementById('fmt-max');
    el.textContent = 'Tối đa: ' + TN.fmtNum(max, 2) + ' điểm';
    el.className = 'badge badge-lg ' + (Math.abs(max - 10) < 1e-6 ? 'badge-success' : 'badge-warning');
    el.title = Math.abs(max - 10) < 1e-6 ? 'Đúng thang 10' : 'Tổng điểm khác 10 – cân nhắc chọn "Tùy chỉnh" + quy đổi về thang 10';
    var parts = [];
    if (p1) parts.push(p1 + ' câu TN'); if (p2) parts.push(p2 + ' câu Đ/S'); if (p3) parts.push(p3 + ' câu TLN'); if (ne) parts.push(ne + ' câu tự luận');
    document.getElementById('fmt-summary').textContent = parts.join(' · ') || 'Chưa có câu hỏi';
  }
  form.addEventListener('input', function (e) { if (e.target.matches('[data-fmt]')) calc(); });
  form.addEventListener('change', function (e) { if (e.target.matches('[data-fmt]')) calc(); });
  window.TNFormat = { calc: calc, apply: function (st, sc) {
    var set = function (n, v) { var el = form.querySelector('[name="' + n + '"]'); if (el) el.value = v; };
    set('p1', st.p1); set('p2', st.p2); set('p3', st.p3); set('p3_len', st.p3_len || 4);
    list.innerHTML = '';
    (st.essay || []).forEach(function (e) { var n = tpl.content.cloneNode(true); var ins = n.querySelectorAll('input'); ins[0].value = e.label; ins[1].value = String(e.points).replace('.', ','); ins[2].value = e.hint || ''; list.appendChild(n); });
    form.querySelectorAll('[name=scheme]').forEach(function (r) { r.checked = r.value === sc.scheme; });
    ['p1_point', 'p1_penalty', 'p2_item_point', 'p2_all_point', 'p3_point', 'max_score'].forEach(function (k) { set(k, String(sc[k]).replace('.', ',')); });
    for (var k = 1; k <= 4; k++) set('p2_t' + k, String(sc.p2_table[k]).replace('.', ','));
    set('p2_mode', sc.p2_mode); set('rounding', String(sc.rounding)); set('round_mode', sc.round_mode); set('p3_compare', sc.p3_compare);
    form.querySelector('[name=scale]').checked = !!sc.scale; form.querySelector('[name=min_zero]').checked = !!sc.min_zero;
    calc();
  } };
  calc();
});
</script>
JS); ?>

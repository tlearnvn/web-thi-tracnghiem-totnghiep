/* =====================================================================
   PHIẾU TRẢ LỜI TRẮC NGHIỆM (mẫu từ năm 2025) – thành phần giao diện dùng chung
   Chế độ: 'exam' (làm bài) | 'review' (xem lại, có đáp án) | 'key' (giáo viên nhập đáp án) | 'print' (in)
   ===================================================================== */
(function (w, d) {
  'use strict';
  var ABCD = ['A', 'B', 'C', 'D'];
  var SUB = ['a', 'b', 'c', 'd'];
  var DIG = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
  function esc(s) { return (w.TN && TN.esc) ? TN.esc(s) : String(s == null ? '' : s); }
  function ic(n, c) { return w.TNIcon ? w.TNIcon(n, c) : ''; }
  function h(tag, cls, html) { var e = d.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function obj(v) { return v && typeof v === 'object' && !Array.isArray(v) ? v : {}; }
  function fmtNum(n) { return (w.TN && TN.fmtNum) ? TN.fmtNum(n, 2) : String(n); }

  /** Kiểm tra câu trả lời ngắn theo quy tắc phiếu (giống máy chủ). */
  function validateP3(g, len) {
    g = String(g || '').replace(/_+$/, '');
    if (!g) return [false, ''];
    if (g.indexOf('_') >= 0) return [false, 'Bỏ trống ô ở giữa – phải tô từ trái sang phải'];
    if (g.length > len) return [false, 'Quá ' + len + ' ký tự'];
    var m = g.indexOf('-');
    if (m > 0 || (g.match(/-/g) || []).length > 1) return [false, 'Dấu "−" chỉ ở cột đầu tiên'];
    if ((g.match(/,/g) || []).length > 1) return [false, 'Chỉ được tô một dấu phẩy'];
    var c = g.indexOf(',');
    if (c >= 0 && (c < 1 || c > len - 2)) return [false, 'Dấu phẩy chỉ ở cột 2 hoặc 3'];
    if (!/\d/.test(g)) return [false, 'Chưa có chữ số'];
    if (c >= 0 && c === g.length - 1) return [false, 'Thiếu chữ số sau dấu phẩy'];
    return [true, g];
  }

  function AnswerSheet(root, o) {
    this.root = typeof root === 'string' ? d.querySelector(root) : root;
    this.o = Object.assign({ mode: 'exam', header: {}, keys: {}, results: null, explanations: {}, flags: [], readOnly: false }, o || {});
    this.s = this.o.structure;
    this.len = this.s.p3_len || 4;
    this.mode = this.o.mode;
    this.readOnly = this.o.readOnly || this.mode === 'review' || this.mode === 'print';
    this.setAnswers(this.o.answers);
    this.flags = (this.o.flags || []).slice();
    this.render();
  }
  var P = AnswerSheet.prototype;

  P.setAnswers = function (a) {
    a = obj(a);
    this.a = { p1: Object.assign({}, obj(a.p1)), p2: Object.assign({}, obj(a.p2)), p3: Object.assign({}, obj(a.p3)), e: Object.assign({}, obj(a.e)) };
    if (this.el) this.refreshAll();
  };
  P.getAnswers = function () { return JSON.parse(JSON.stringify(this.a)); };
  P.getFlags = function () { return this.flags.slice(); };

  P.ids = function () {
    var ids = [], s = this.s, i;
    for (i = 1; i <= s.p1; i++) ids.push('p1.' + i);
    for (i = 1; i <= s.p2; i++) ids.push('p2.' + i);
    for (i = 1; i <= s.p3; i++) ids.push('p3.' + i);
    (s.essay || []).forEach(function (_, j) { ids.push('e.' + (j + 1)); });
    return ids;
  };
  P.value = function (qid) { var p = qid.split('.'); return this.a[p[0]][p[1]]; };
  P.isAnswered = function (qid) {
    var v = this.value(qid);
    if (v == null || v === '') return false;
    if (qid.indexOf('p2.') === 0) return v.replace(/_/g, '') !== '';
    if (qid.indexOf('p3.') === 0) return v.replace(/_/g, '') !== '';
    if (qid.indexOf('e.') === 0) return String(v).trim() !== '';
    return true;
  };
  P.stats = function () {
    var self = this, ids = this.ids(), un = [], partial = [];
    ids.forEach(function (q) {
      if (!self.isAnswered(q)) un.push(q);
      else if (q.indexOf('p2.') === 0 && self.value(q).indexOf('_') >= 0) partial.push(q);
    });
    return { total: ids.length, answered: ids.length - un.length, unanswered: un, partial: partial, flagged: this.flags.slice() };
  };
  P.label = function (qid) {
    var p = qid.split('.');
    var n = { p1: 'Phần I', p2: 'Phần II', p3: 'Phần III', e: 'Tự luận' }[p[0]];
    return n + ' – Câu ' + p[1];
  };
  P.shortLabel = function (qid) {
    var p = qid.split('.');
    return ({ p1: 'I.', p2: 'II.', p3: 'III.', e: 'TL.' }[p[0]]) + p[1];
  };

  // ------------------------------------------------------------------ Dựng giao diện
  P.render = function () {
    var self = this, s = this.s;
    var el = h('div', 'sheet sheet-' + this.mode + (this.readOnly ? ' is-readonly' : ''));
    this.el = el;
    el.appendChild(this.renderHeader());
    if (s.p1 > 0) el.appendChild(this.renderP1());
    if (s.p2 > 0) el.appendChild(this.renderP2());
    if (s.p3 > 0) el.appendChild(this.renderP3());
    if (s.essay && s.essay.length) el.appendChild(this.renderEssay());
    this.root.innerHTML = '';
    this.root.appendChild(el);
    el.addEventListener('click', function (e) { self.onClick(e); });
    el.addEventListener('keydown', function (e) { self.onKey(e); });
    el.addEventListener('input', function (e) { self.onInput(e); });
    this.refreshAll();
  };

  P.renderHeader = function () {
    var H = this.o.header || {};
    var box = h('header', 'sh-head');
    var digits = function (label, value, n) {
      var v = String(value || '').replace(/\s/g, '');
      var html = '<div class="sh-digits"><div class="sh-dl">' + esc(label) + '</div><div class="sh-dbox">';
      for (var i = 0; i < n; i++) html += '<span>' + esc(v[i] || '') + '</span>';
      html += '</div><div class="sh-dgrid" style="grid-template-columns:repeat(' + n + ',1fr)">';
      for (var r = 0; r < 10; r++) for (var c = 0; c < n; c++) html += '<i class="' + (v[c] === String(r) ? 'on' : '') + '">' + r + '</i>';
      return html + '</div></div>';
    };
    var sbd = String(H.code || '').replace(/\D/g, '').slice(-9);
    var code = String(H.variant || '').replace(/\D/g, '').slice(-4);
    box.innerHTML =
      '<div class="sh-title">' + esc(H.title || 'PHIẾU TRẢ LỜI TRẮC NGHIỆM') + '</div>' +
      '<div class="sh-sub"><span>Kỳ thi: <b>' + esc(H.exam || '') + '</b></span></div>' +
      '<div class="sh-sub"><span>Môn thi: <b>' + esc(H.subject || '') + '</b></span><span>Ngày thi: <b>' + esc(H.date || '') + '</b></span></div>' +
      '<div class="sh-grid">' +
      '<div class="sh-info">' +
      '<div class="sh-line"><span>Họ và tên thí sinh:</span><b>' + esc(H.name || '') + '</b></div>' +
      '<div class="sh-line"><span>Ngày sinh:</span><b>' + esc(H.birthday || '') + '</b><span>Lớp:</span><b>' + esc(H.className || '') + '</b></div>' +
      '<div class="sh-line"><span>Ca / phòng thi:</span><b>' + esc(H.room || '') + '</b></div>' +
      '<div class="sh-line"><span>Mã đề thi:</span><b class="mono">' + esc(H.variant || '') + '</b><span>SBD:</span><b class="mono">' + esc(H.code || '') + '</b></div>' +
      '</div>' +
      (sbd ? digits('Số báo danh', sbd, Math.max(6, sbd.length)) : '') +
      (code ? digits('Mã đề thi', code, Math.max(3, code.length)) : '') +
      '</div>';
    return box;
  };

  P.partHead = function (roman, title, note, key) {
    return '<div class="sh-part-head"><div><span class="sh-roman">PHẦN ' + roman + '</span><span class="sh-ptitle">' + esc(title) + '</span></div>' +
      '<span class="sh-pcount" data-count="' + key + '"></span></div>' + (note ? '<div class="sh-note">' + note + '</div>' : '');
  };

  P.flagBtn = function (qid) {
    if (this.mode !== 'exam') return '';
    return '<button type="button" class="sh-flag" data-flag="' + qid + '" title="Đánh dấu xem lại (phím F)" aria-label="Đánh dấu câu ' + qid + '">' + ic('flag') + '</button>';
  };
  P.extraBtn = function (qid) {
    if (this.mode === 'key') return '<button type="button" class="sh-more" data-detail="' + qid + '" title="Điểm, mức độ, lời giải…">' + ic('square-pen') + '</button>';
    if (this.mode === 'review' && (this.o.explanations || {})[qid]) return '<button type="button" class="sh-exp" data-exp="' + qid + '" title="Xem lời giải">' + ic('lightbulb') + '</button>';
    return '';
  };

  P.renderP1 = function () {
    var s = this.s, sec = h('section', 'sh-part', ''), html = this.partHead('I', 'Trắc nghiệm nhiều phương án lựa chọn', this.mode === 'exam' ? 'Mỗi câu chỉ chọn <b>một</b> phương án. Bấm lại để bỏ chọn.' : '', 'p1');
    html += '<div class="p1-grid">';
    for (var b = 0; b < Math.ceil(s.p1 / 10); b++) {
      html += '<div class="p1-block"><div class="p1-row p1-th"><span></span>' + ABCD.map(function (x) { return '<span>' + x + '</span>'; }).join('') + '<span></span></div>';
      for (var i = b * 10 + 1; i <= Math.min(s.p1, b * 10 + 10); i++) {
        var q = 'p1.' + i;
        html += '<div class="p1-row" data-q="' + q + '"><span class="qn">' + i + '</span>' +
          ABCD.map(function (x) { return '<button type="button" class="bub" data-q="' + q + '" data-v="' + x + '" aria-label="Câu ' + i + ' phương án ' + x + '">' + x + '</button>'; }).join('') +
          '<span class="sh-side">' + this.flagBtn(q) + this.extraBtn(q) + '<em class="sh-pts" data-pts="' + q + '"></em></span></div>';
      }
      html += '</div>';
    }
    sec.innerHTML = html + '</div>';
    return sec;
  };

  P.renderP2 = function () {
    var s = this.s, sec = h('section', 'sh-part'), html = this.partHead('II', 'Trắc nghiệm đúng / sai', this.mode === 'exam' ? 'Mỗi ý a), b), c), d) chọn <b>Đúng</b> hoặc <b>Sai</b>.' : '', 'p2');
    html += '<div class="p2-grid">';
    for (var i = 1; i <= s.p2; i++) {
      var q = 'p2.' + i;
      html += '<div class="p2-q" data-q="' + q + '"><div class="p2-head"><span class="qn">Câu ' + i + '</span><span class="sh-side">' + this.flagBtn(q) + this.extraBtn(q) + '<em class="sh-pts" data-pts="' + q + '"></em></span></div>' +
        '<div class="p2-row p2-th"><span></span><span>Đúng</span><span>Sai</span></div>';
      for (var j = 0; j < 4; j++) {
        html += '<div class="p2-row" data-sub="' + j + '"><span class="p2-sub">' + SUB[j] + ')</span>' +
          '<button type="button" class="bub" data-q="' + q + '" data-i="' + j + '" data-v="D" aria-label="Câu ' + i + ' ý ' + SUB[j] + ' Đúng"></button>' +
          '<button type="button" class="bub" data-q="' + q + '" data-i="' + j + '" data-v="S" aria-label="Câu ' + i + ' ý ' + SUB[j] + ' Sai"></button></div>';
      }
      html += '</div>';
    }
    sec.innerHTML = html + '</div>';
    return sec;
  };

  P.renderP3 = function () {
    var s = this.s, len = this.len, sec = h('section', 'sh-part'), self = this;
    var html = this.partHead('III', 'Trắc nghiệm trả lời ngắn', this.mode === 'exam' ? 'Tô từ trái sang phải, bỏ trống các ô bên phải nếu không dùng. Có thể bấm vào ô kết quả để <b>gõ trực tiếp</b> (vd: -1,5).' : (this.mode === 'key' ? 'Nhập đáp án dạng số, dấu phẩy thập phân. Nhiều đáp án chấp nhận: <code>1,5|1,50</code>' : ''), 'p3');
    html += '<div class="p3-grid">';
    for (var i = 1; i <= s.p3; i++) {
      var q = 'p3.' + i;
      html += '<div class="p3-q" data-q="' + q + '"><div class="p3-head"><span class="qn">Câu ' + i + '</span><span class="sh-side">' + this.flagBtn(q) + this.extraBtn(q) + '<em class="sh-pts" data-pts="' + q + '"></em></span></div>';
      if (this.mode === 'key') {
        html += '<input class="p3-key" data-q="' + q + '" placeholder="vd: -1,5" autocomplete="off" spellcheck="false">';
      } else {
        html += '<div class="p3-val" data-q="' + q + '" tabindex="' + (this.readOnly ? -1 : 0) + '" title="' + (this.readOnly ? '' : 'Bấm để gõ đáp án bằng bàn phím') + '">';
        for (var c = 0; c < len; c++) html += '<span data-c="' + c + '"></span>';
        html += '</div><div class="p3-bubbles" style="grid-template-columns:18px repeat(' + len + ',1fr)">';
        html += '<span class="lbl">−</span><button type="button" class="bub sm" data-q="' + q + '" data-c="0" data-v="-"></button>';
        for (c = 1; c < len; c++) html += '<i></i>';
        html += '<span class="lbl">,</span><i></i>';
        for (c = 1; c < len - 1; c++) html += '<button type="button" class="bub sm" data-q="' + q + '" data-c="' + c + '" data-v=","></button>';
        html += '<i></i>';
        DIG.forEach(function (dg) {
          html += '<span class="lbl">' + dg + '</span>';
          for (var c2 = 0; c2 < len; c2++) html += '<button type="button" class="bub sm" data-q="' + q + '" data-c="' + c2 + '" data-v="' + dg + '"></button>';
        });
        html += '</div><div class="p3-msg" data-msg="' + q + '"></div>';
        if (this.mode === 'review') html += '<div class="p3-key-show" data-keyshow="' + q + '"></div>';
      }
      html += '</div>';
    }
    sec.innerHTML = html + '</div>';
    return sec;
  };

  P.renderEssay = function () {
    var s = this.s, sec = h('section', 'sh-part'), self = this;
    var html = this.partHead('IV', 'Tự luận', this.mode === 'exam' ? 'Gõ bài làm vào ô tương ứng. Bài được lưu tự động.' : '', 'e');
    s.essay.forEach(function (e, j) {
      var q = 'e.' + (j + 1);
      html += '<div class="es-q" data-q="' + q + '"><div class="es-head"><span class="qn">' + esc(e.label) + '</span><span class="text-muted">(' + fmtNum(e.points) + ' điểm)</span>' +
        (e.hint ? '<span class="es-hint">' + esc(e.hint) + '</span>' : '') + '<span class="sh-side">' + self.flagBtn(q) + self.extraBtn(q) + '<em class="sh-pts" data-pts="' + q + '"></em></span></div>';
      if (self.mode === 'key') {
        html += '<div class="text-sm text-muted">Tự luận do giáo viên chấm. Bấm biểu tượng bút để nhập hướng dẫn chấm.</div>';
      } else {
        html += '<textarea class="es-text" data-q="' + q + '" rows="' + (e.points >= 2 ? 10 : 5) + '"' + (self.readOnly ? ' readonly' : '') + ' placeholder="Nhập câu trả lời…" spellcheck="false"></textarea><div class="es-count" data-wc="' + q + '"></div>';
      }
      html += '</div>';
    });
    sec.innerHTML = html;
    return sec;
  };

  // ------------------------------------------------------------------ Cập nhật trạng thái hiển thị
  P.refreshAll = function () {
    var self = this;
    this.ids().forEach(function (q) { self.refresh(q); });
    this.refreshCounts();
    if (this.mode === 'review') this.applyResults();
  };

  P.refresh = function (qid) {
    var el = this.el, v = this.value(qid) || '';
    var box = el.querySelector('[data-q="' + qid + '"]:not(button):not(input):not(textarea):not(.p3-val)');
    if (!box) return;
    var part = qid.split('.')[0];
    if (part === 'p1') {
      box.querySelectorAll('.bub').forEach(function (b) { b.classList.toggle('on', v.indexOf(b.dataset.v) >= 0 && v !== ''); });
    } else if (part === 'p2') {
      var s2 = (v + '____').slice(0, 4);
      box.querySelectorAll('.bub').forEach(function (b) { b.classList.toggle('on', s2[+b.dataset.i] === b.dataset.v); });
    } else if (part === 'p3') {
      if (this.mode === 'key') {
        var inp = box.querySelector('.p3-key');
        if (inp && d.activeElement !== inp) inp.value = v;
      } else {
        var cols = this.p3cols(v);
        box.querySelectorAll('.bub').forEach(function (b) { b.classList.toggle('on', cols[+b.dataset.c] === b.dataset.v); });
        box.querySelectorAll('.p3-val span').forEach(function (sp, i) { sp.textContent = cols[i] === '-' ? '−' : (cols[i] || ''); });
        var chk = validateP3(this.p3str(cols), this.len);
        var msg = box.querySelector('[data-msg]');
        if (msg) msg.textContent = (!chk[0] && chk[1]) ? '⚠ ' + chk[1] : '';
        box.classList.toggle('invalid', !chk[0] && !!chk[1]);
      }
    } else if (part === 'e') {
      var ta = box.querySelector('textarea');
      if (ta && d.activeElement !== ta && ta.value !== v) ta.value = v;
      var wc = box.querySelector('[data-wc]');
      if (wc) { var words = String(v).trim() ? String(v).trim().split(/\s+/).length : 0; wc.textContent = words + ' chữ · ' + String(v).length + ' ký tự'; }
    }
    box.classList.toggle('done', this.isAnswered(qid));
    box.classList.toggle('partial', part === 'p2' && this.isAnswered(qid) && v.indexOf('_') >= 0);
    box.classList.toggle('flagged', this.flags.indexOf(qid) >= 0);
    if (this.mode === 'key') {
      var k = (this.o.keys || {})[qid] || {};
      box.classList.toggle('has-exp', !!k.explanation);
      box.classList.toggle('is-void', !!+k.is_void);
    }
  };

  P.refreshCounts = function () {
    var self = this, s = this.s;
    var parts = { p1: s.p1, p2: s.p2, p3: s.p3, e: (s.essay || []).length };
    Object.keys(parts).forEach(function (p) {
      var n = 0;
      for (var i = 1; i <= parts[p]; i++) if (self.isAnswered(p + '.' + i)) n++;
      var el = self.el.querySelector('[data-count="' + p + '"]');
      if (el && self.mode !== 'review') el.textContent = n + '/' + parts[p];
    });
  };

  P.p3cols = function (v) {
    var cols = [];
    v = String(v || '');
    for (var i = 0; i < this.len; i++) cols.push(v[i] && v[i] !== '_' ? v[i] : '');
    return cols;
  };
  P.p3str = function (cols) {
    return cols.map(function (c) { return c || '_'; }).join('').replace(/_+$/, '');
  };

  // ------------------------------------------------------------------ Tương tác
  P.set = function (qid, val) {
    var p = qid.split('.');
    if (val === '' || val == null) delete this.a[p[0]][p[1]]; else this.a[p[0]][p[1]] = val;
    this.refresh(qid);
    this.refreshCounts();
    if (this.o.onChange) this.o.onChange(this.getAnswers(), qid);
  };

  P.onClick = function (e) {
    var t = e.target;
    var fl = t.closest('[data-flag]');
    if (fl) { e.preventDefault(); this.toggleFlag(fl.dataset.flag); return; }
    var dt = t.closest('[data-detail]');
    if (dt && this.o.onDetail) { e.preventDefault(); this.o.onDetail(dt.dataset.detail); return; }
    var ex = t.closest('[data-exp]');
    if (ex) { e.preventDefault(); this.showExplanation(ex.dataset.exp); return; }
    var pv = t.closest('.p3-val');
    if (pv && !this.readOnly) { this.openP3Input(pv.dataset.q); return; }
    var b = t.closest('.bub');
    if (!b || this.readOnly || b.disabled) return;
    var q = b.dataset.q, part = q.split('.')[0], cur = this.value(q) || '';
    if (part === 'p1') {
      this.set(q, cur === b.dataset.v ? '' : b.dataset.v);
    } else if (part === 'p2') {
      var s = (cur + '____').slice(0, 4).split('');
      var i = +b.dataset.i;
      s[i] = s[i] === b.dataset.v ? '_' : b.dataset.v;
      var str = s.join('');
      this.set(q, str.replace(/_/g, '') === '' ? '' : str);
    } else if (part === 'p3') {
      var cols = this.p3cols(cur), c = +b.dataset.c, v = b.dataset.v;
      if (cols[c] === v) cols[c] = '';
      else {
        if (v === ',') cols = cols.map(function (x) { return x === ',' ? '' : x; });
        cols[c] = v;
      }
      this.set(q, this.p3str(cols));
    }
    this.activeRow = b.closest('[data-q]');
  };

  P.onInput = function (e) {
    var t = e.target;
    if (t.classList.contains('es-text')) { this.set(t.dataset.q, t.value); }
    else if (t.classList.contains('p3-key')) { this.set(t.dataset.q, t.value.trim()); }
  };

  P.onKey = function (e) {
    if (this.readOnly) return;
    var t = e.target;
    if (t.tagName === 'TEXTAREA' || t.tagName === 'INPUT') return;
    var row = t.closest('[data-q]');
    if (!row) return;
    var q = row.dataset.q, part = q.split('.')[0], k = e.key.toUpperCase();
    if (k === 'F' && this.mode === 'exam') { e.preventDefault(); this.toggleFlag(q); return; }
    if (part === 'p1') {
      var idx = ['A', 'B', 'C', 'D'].indexOf(k); if (idx < 0) idx = ['1', '2', '3', '4'].indexOf(k);
      if (idx >= 0) { e.preventDefault(); var cur = this.value(q) || ''; this.set(q, cur === ABCD[idx] ? '' : ABCD[idx]); return; }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter') {
        e.preventDefault();
        var n = +q.split('.')[1] + (e.key === 'ArrowUp' ? -1 : 1);
        if (n >= 1 && n <= this.s.p1) this.focusQuestion('p1.' + n);
      }
    }
    if (part === 'p3' && t.classList.contains('p3-val') && (e.key === 'Enter' || /^[\d,.\-]$/.test(e.key))) {
      e.preventDefault(); this.openP3Input(q, /^[\d,.\-]$/.test(e.key) ? e.key : null);
    }
  };

  P.toggleFlag = function (qid) {
    var i = this.flags.indexOf(qid);
    if (i >= 0) this.flags.splice(i, 1); else this.flags.push(qid);
    this.refresh(qid);
    if (this.o.onFlag) this.o.onFlag(this.getFlags(), qid);
  };

  /** Ô nhập bằng bàn phím cho câu trả lời ngắn – tự tô các ô tròn tương ứng. */
  P.openP3Input = function (qid, firstChar) {
    var self = this, box = this.el.querySelector('.p3-q[data-q="' + qid + '"]');
    if (!box || box.querySelector('.p3-input')) return;
    var val = box.querySelector('.p3-val');
    var inp = h('input', 'p3-input');
    inp.maxLength = this.len;
    inp.inputMode = 'decimal';
    inp.autocomplete = 'off';
    inp.value = (firstChar !== null && firstChar !== undefined) ? '' : String(this.value(qid) || '').replace(/_/g, '');
    val.style.display = 'none';
    val.parentNode.insertBefore(inp, val.nextSibling);
    inp.focus();
    if (firstChar) { inp.value = firstChar === '.' ? ',' : firstChar; }
    var apply = function () {
      var v = inp.value.replace(/\./g, ',').replace(/[^\d,\-]/g, '').slice(0, self.len);
      if (inp.value !== v) inp.value = v;
      self.set(qid, v);
    };
    var close = function () { apply(); inp.remove(); val.style.display = ''; };
    inp.addEventListener('input', apply);
    inp.addEventListener('blur', close);
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === 'Escape' || e.key === 'Tab') { e.preventDefault(); inp.blur(); val.focus(); } });
    apply();
  };

  P.focusQuestion = function (qid, flash) {
    var box = this.el.querySelector('[data-q="' + qid + '"]:not(button):not(input):not(textarea):not(.p3-val)');
    if (!box) return;
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    var f = box.querySelector('.bub, textarea, .p3-val, .p3-key');
    if (f && !this.readOnly) setTimeout(function () { f.focus({ preventScroll: true }); }, 250);
    if (flash !== false) { box.classList.remove('pulse'); void box.offsetWidth; box.classList.add('pulse'); }
  };

  // ------------------------------------------------------------------ Xem lại: tô đúng/sai, hiện đáp án
  P.applyResults = function () {
    var r = this.o.results, keys = this.o.keys || {}, el = this.el, self = this;
    if (!r || !r.items) return;
    Object.keys(r.items).forEach(function (q) {
      var it = r.items[q], part = q.split('.')[0];
      var box = el.querySelector('[data-q="' + q + '"]:not(button):not(input):not(textarea):not(.p3-val)');
      if (!box) return;
      var pts = el.querySelector('[data-pts="' + q + '"]');
      if (pts) pts.textContent = fmtNum(it.pts) + 'đ';
      box.classList.toggle('ok', !!it.ok);
      box.classList.toggle('bad', !it.ok);
      var key = it.key || (keys[q] || {}).answer || '';
      if (part === 'p1') {
        box.querySelectorAll('.bub').forEach(function (b) {
          var isKey = key.indexOf(b.dataset.v) >= 0 || key === '*';
          b.classList.toggle('key', isKey);
          if (b.classList.contains('on')) b.classList.add(isKey ? 'good' : 'wrong');
        });
      } else if (part === 'p2') {
        box.querySelectorAll('.p2-row[data-sub]').forEach(function (row) {
          var j = +row.dataset.sub, kc = key[j];
          row.classList.toggle('ok', !!(it.subs && it.subs[j]));
          row.classList.toggle('bad', !(it.subs && it.subs[j]));
          row.querySelectorAll('.bub').forEach(function (b) {
            var isKey = b.dataset.v === kc || kc === '*';
            b.classList.toggle('key', isKey);
            if (b.classList.contains('on')) b.classList.add(isKey ? 'good' : 'wrong');
          });
        });
        if (pts) pts.textContent = (it.k || 0) + '/4 ý · ' + fmtNum(it.pts) + 'đ';
      } else if (part === 'p3') {
        var ks = box.querySelector('[data-keyshow]');
        if (ks) ks.innerHTML = 'Đáp án: <b>' + esc(String(key).replace(/\|/g, ' hoặc ')) + '</b>' + (it.note ? ' <span class="text-danger">· ' + esc(it.note) + '</span>' : '');
        box.querySelectorAll('.bub.on').forEach(function (b) { b.classList.add(it.ok ? 'good' : 'wrong'); });
      } else if (part === 'e') {
        if (pts) pts.textContent = it.graded ? fmtNum(it.pts) + '/' + fmtNum(it.max) + 'đ' : 'Chờ chấm';
        box.classList.toggle('pending', !it.graded);
      }
    });
    ['p1', 'p2', 'p3', 'e'].forEach(function (p) {
      var c = el.querySelector('[data-count="' + p + '"]');
      var part = r.parts && r.parts[p === 'e' ? 'essay' : p];
      if (c && part) c.textContent = fmtNum(part.score) + '/' + fmtNum(part.max) + ' điểm';
    });
  };

  P.showExplanation = function (qid) {
    var text = (this.o.explanations || {})[qid] || '';
    var it = this.o.results && this.o.results.items ? this.o.results.items[qid] : null;
    var key = it ? it.key : ((this.o.keys || {})[qid] || {}).answer;
    var body = '<div class="exp-meta">' + (key ? 'Đáp án đúng: <b>' + esc(AnswerSheet.keyLabel(qid, key)) + '</b>' : '') +
      (it ? ' · Em chọn: <b>' + esc(AnswerSheet.keyLabel(qid, it.given) || '(bỏ trống)') + '</b> ' + (it.ok ? '<span class="badge badge-success">Đúng</span>' : '<span class="badge badge-danger">Chưa đúng</span>') : '') + '</div>' +
      '<div class="md-body exp-body">' + AnswerSheet.md(text) + '</div>';
    var m = w.TN.modal({ title: 'Lời giải – ' + this.label(qid), icon: 'lightbulb', body: body, size: 'lg' });
    AnswerSheet.math(m.body);
  };

  /** Hiển thị đáp án thân thiện: DSDD -> a) Đ b) S c) Đ d) Đ */
  AnswerSheet.keyLabel = function (qid, v) {
    if (v == null || v === '') return '';
    var p = qid.split('.')[0];
    if (p === 'p2') return String(v).split('').map(function (c, i) { return SUB[i] + ') ' + (c === 'D' ? 'Đúng' : c === 'S' ? 'Sai' : c === '*' ? 'hủy' : '–'); }).join('  ');
    if (p === 'p3') return String(v).replace(/_/g, '·').replace(/\|/g, ' hoặc ');
    if (p === 'p1' && String(v).length > 1 && v !== '*') return String(v).split('').join(' hoặc ');
    return String(v);
  };

  /** Markdown tối giản + an toàn (đã thoát HTML). */
  AnswerSheet.md = function (src) {
    var s = esc(src || '');
    var maths = [];
    s = s.replace(/\$\$([\s\S]+?)\$\$|\$([^$\n]+?)\$/g, function (m) { maths.push(m); return '\u0000' + (maths.length - 1) + '\u0000'; });
    s = s.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<i>$2</i>').replace(/`([^`]+)`/g, '<code>$1</code>');
    s = s.replace(/!\[([^\]]*)\]\((https?:\/\/[^)\s]+)\)/g, '<img src="$2" alt="$1">');
    s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    var lines = s.split(/\n/), out = [], inList = false;
    lines.forEach(function (l) {
      var m = l.match(/^\s*[-•+]\s+(.*)$/);
      if (m) { if (!inList) { out.push('<ul>'); inList = true; } out.push('<li>' + m[1] + '</li>'); return; }
      if (inList) { out.push('</ul>'); inList = false; }
      out.push(l === '' ? '<br>' : l + '<br>');
    });
    if (inList) out.push('</ul>');
    s = out.join('').replace(/(<br>)+$/, '');
    s = s.replace(/\u0000(\d+)\u0000/g, function (_, i) { return maths[+i]; });
    return s || '<span class="text-muted">Chưa có lời giải cho câu này.</span>';
  };
  AnswerSheet.math = function (el) {
    if (w.renderMathInElement) {
      try { w.renderMathInElement(el, { delimiters: [{ left: '$$', right: '$$', display: true }, { left: '$', right: '$', display: false }], throwOnError: false }); } catch (e) {}
    }
  };
  AnswerSheet.validateP3 = validateP3;
  w.AnswerSheet = AnswerSheet;
})(window, document);

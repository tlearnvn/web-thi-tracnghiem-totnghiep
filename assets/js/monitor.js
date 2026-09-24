/* =====================================================================
   GIÁM SÁT CA THI THEO THỜI GIAN THỰC (giám thị / giáo viên)
   Cập nhật vài giây một lần; chỉ vẽ lại ô thay đổi để không làm mất menu đang mở.
   ===================================================================== */
(function (w, d) {
  'use strict';
  var C = w.MON_CFG;
  if (!C || !w.TN) return;
  var ic = TN.icon, esc = TN.esc;
  var S = { data: null, rows: {}, since: 0, fetchedAt: 0, fails: 0, timer: 0, filter: '', cls: '', q: '', events: [], busy: false };
  var tbody = d.getElementById('rows');

  var STATUS = {
    none: ['Chưa vào thi', 'default'],
    online: ['Đang làm', 'success'],
    offline: ['Mất kết nối', 'warning'],
    paused: ['Tạm dừng', 'warning'],
    locked: ['Khóa do vi phạm', 'danger'],
    done: ['Đã nộp', 'primary'],
    voided: ['Đã hủy bài', 'default']
  };
  var REASON = { manual: 'tự nộp', timeout: 'hết giờ', violation: 'vi phạm', proctor: 'giám thị thu', session_closed: 'kết thúc ca' };

  function ago(ts) {
    if (!ts) return '';
    var s = Math.max(0, Math.round(serverNow() - ts));
    if (s < 60) return s + ' giây trước';
    if (s < 3600) return Math.floor(s / 60) + ' phút trước';
    return Math.floor(s / 3600) + ' giờ trước';
  }
  function serverNow() { return S.data ? S.data.now + (Date.now() - S.fetchedAt) / 1000 : Date.now() / 1000; }
  function remNow(r) {
    if (r.rem === null || r.rem === undefined) return null;
    if (r.paused || r.locked || S.data.session.paused) return r.rem;
    return Math.max(0, Math.round(r.rem - (Date.now() - S.fetchedAt) / 1000));
  }

  // ------------------------------------------------------------------ Nạp dữ liệu
  function load() {
    clearTimeout(S.timer);
    TN.api(C.dataUrl + '&since=' + S.since, { timeout: 15000 }).then(function (r) {
      S.fails = 0;
      S.data = r;
      S.fetchedAt = Date.now();
      render();
      setLive(true);
      schedule();
    }, function (e) {
      S.fails++;
      setLive(false, e.message);
      schedule();
    });
  }
  function schedule() {
    var iv = d.visibilityState === 'hidden' ? 15000 : 4000;
    if (S.fails) iv = Math.min(30000, 4000 * Math.pow(2, S.fails - 1));
    S.timer = setTimeout(load, iv);
  }
  function setLive(ok, msg) {
    var dot = d.getElementById('live-dot');
    if (dot) dot.className = ok ? 'dot-live' : 'dot-warn';
    var u = d.getElementById('upd-at');
    if (u) u.textContent = ok ? 'cập nhật lúc ' + TN.fmtTime(S.data.now, true) : 'mất kết nối máy chủ – đang thử lại (' + (msg || '') + ')';
  }
  d.addEventListener('visibilitychange', function () { if (d.visibilityState === 'visible') load(); });

  // ------------------------------------------------------------------ Vẽ giao diện
  function render() {
    var r = S.data;
    Object.keys(r.count).forEach(function (k) { var el = d.querySelector('[data-c="' + k + '"]'); if (el) el.textContent = r.count[k]; });
    var st = r.session.state;
    var badge = d.getElementById('state-badge');
    var map = { upcoming: ['Sắp diễn ra', 'info'], running: ['Đang diễn ra', 'success'], paused: ['Tạm dừng', 'warning'], ended: ['Đã kết thúc', 'default'], closed: ['Đã đóng', 'default'] };
    if (badge) badge.innerHTML = '<span class="badge badge-' + map[st][1] + '">' + esc(map[st][0]) + '</span>';
    var end = d.getElementById('end-info');
    if (end) end.innerHTML = r.session.end_at ? (st === 'running' || st === 'paused' ? 'Kết thúc lúc <b>' + TN.fmtDateTime(r.session.end_at) + '</b>' : 'Kết thúc: ' + TN.fmtDateTime(r.session.end_at)) : 'Không giới hạn giờ kết thúc';
    var ctl = d.getElementById('session-ctl');
    if (ctl) {
      var vis = { start_now: st === 'upcoming', pause: st === 'running', resume: st === 'paused', add_time: st === 'running' || st === 'paused', message: st !== 'closed', close: st === 'running' || st === 'paused' };
      ctl.querySelectorAll('[data-ctl]').forEach(function (b) { b.hidden = !vis[b.dataset.ctl]; });
    }
    renderRows();
    renderAlerts();
    renderEvents();
    renderMessages();
  }

  function visible(row) {
    if (S.cls && row.cls !== S.cls) return false;
    if (S.q) {
      var hay = (TN.unaccent ? TN.unaccent(row.name + ' ' + row.code) : (row.name + ' ' + row.code)).toLowerCase();
      if (hay.indexOf(S.q) < 0) return false;
    }
    switch (S.filter) {
      case 'doing': return ['online', 'offline', 'paused', 'locked'].indexOf(row.st) >= 0;
      case 'offline': return row.st === 'offline';
      case 'none': return row.st === 'none' || row.st === 'voided';
      case 'done': return row.st === 'done';
      case 'issue': return row.st === 'offline' || row.st === 'locked' || row.st === 'paused' || row.blocked || row.viol > 0;
      default: return true;
    }
  }

  function cells(r) {
    var total = S.data.total || 1;
    var stInfo = STATUS[r.st] || STATUS.none;
    var sub = '';
    if (r.st === 'online' || r.st === 'offline' || r.st === 'paused' || r.st === 'locked') sub = r.seen ? 'tín hiệu ' + ago(r.seen) : '';
    else if (r.st === 'done') sub = TN.fmtTime(r.sub) + (r.reason ? ' · ' + (REASON[r.reason] || r.reason) : '');
    var dot = r.st === 'online' ? '<span class="dot-live"></span> ' : (r.st === 'offline' ? '<span class="dot-warn"></span> ' : '');
    var pct = r.aid ? Math.round((r.answered || 0) * 100 / total) : 0;
    var rem = remNow(r);
    var remTxt = '';
    if (r.status === 'in_progress') {
      remTxt = rem === null ? '<span class="text-muted">∞</span>' : '<b class="num ' + (rem <= 60 ? 'text-danger' : rem <= 300 ? 'text-warning' : '') + '">' + ((r.paused || r.locked || S.data.session.paused) ? ic('circle-pause', 'sm') + ' ' : '') + TN.fmtDuration(rem) + '</b>' + (r.extraMin ? '<div class="text-xs text-muted">' + (r.extraMin > 0 ? '+' : '') + r.extraMin + ' phút</div>' : '');
    }
    var viol = r.viol ? '<span class="badge ' + (C.maxViol && r.viol >= C.maxViol ? 'badge-danger' : 'badge-warning') + '">' + r.viol + '</span>' : '<span class="text-faint">0</span>';
    var dev = r.aid ? '<div class="text-sm truncate" style="max-width:180px" title="' + esc(r.dev + ' · ' + r.ip) + '">' + esc(r.dev || '—') + '</div><div class="text-xs text-muted">' + esc(r.ip || '') + (r.var ? ' · mã đề ' + esc(r.var) : '') + '</div>' +
      (r.blocked ? '<div class="text-xs text-danger fw-600">' + ic('shield-alert', 'sm') + ' Máy khác đang cố vào bài</div>' : '') +
      (r.free && r.status === 'in_progress' ? '<div class="text-xs text-success">' + ic('lock-open', 'sm') + ' Đã mở khóa, chờ đăng nhập</div>' : '') : '';
    var score = r.st === 'done' ? (r.score !== null ? '<span class="score-pill ' + scoreCls(r.score) + '">' + TN.fmtScore(r.score) + '</span>' + (r.pending ? '<div class="text-xs text-warning">chờ chấm TL</div>' : '') : '–') : '';
    var out = {
      person: '<div class="person"><div style="min-width:0"><div class="fw-600">' + esc(r.name) + (r.extra ? ' <span class="badge" title="Không còn trong danh sách dự thi">ngoài DS</span>' : '') + '</div><div class="sub">' + esc(r.code) + (r.cls ? ' · ' + esc(r.cls) : '') + (r.n > 1 ? ' · lần ' + r.no : '') + '</div></div></div>',
      status: '<span class="badge badge-' + stInfo[1] + '">' + dot + esc(stInfo[0]) + '</span>' + (sub ? '<div class="text-xs text-muted mt-1">' + esc(sub) + '</div>' : ''),
      prog: r.aid ? '<div class="progress-label"><span>' + (r.answered || 0) + '/' + total + ' câu</span><span>' + pct + '%</span></div><div class="progress progress-sm"><span style="width:' + pct + '%"></span></div>' : '',
      rem: remTxt,
      viol: viol,
      dev: dev
    };
    if (C.canResults) out.score = score;
    return out;
  }
  function scoreCls(v) { var x = +v; return x >= 8 ? 'score-hi' : x >= 6.5 ? 'score-mid' : x >= 5 ? 'score-lo' : 'score-fail'; }

  function menu(r) {
    if (!C.canAct || !r.aid) return '';
    var items = [];
    var it = function (act, icon, label, cls) { items.push('<button type="button" class="' + (cls || '') + '" data-act="' + act + '" data-aid="' + r.aid + '">' + ic(icon) + ' ' + esc(label) + '</button>'); };
    if (r.status === 'in_progress') {
      it('add_time', 'timer', 'Cộng / bớt giờ…');
      it('message', 'message-square', 'Nhắn tin riêng…');
      if (r.locked) it('unlock_violation', 'lock-open', 'Mở khóa vi phạm');
      else if (r.paused && r.st === 'paused') it('resume', 'circle-play', 'Cho tiếp tục');
      else it('pause', 'circle-pause', 'Tạm dừng bài này');
      if (C.deviceLock) it('unlock_device', 'laptop', 'Mở khóa thiết bị (đổi máy)');
      items.push('<hr>');
      it('force_submit', 'hourglass', 'Thu bài ngay', 'danger');
      it('void', 'rotate-ccw', 'Hủy bài & cho thi lại từ đầu', 'danger');
    } else if (r.status !== 'voided') {
      it('reopen', 'lock-open', 'Mở lại bài để làm tiếp…');
      it('void', 'rotate-ccw', 'Hủy bài & cho thi lại từ đầu', 'danger');
      if (C.canManage) it('delete', 'trash-2', 'Xóa hẳn bài làm', 'danger');
    }
    if (C.canResults) items.push('<hr><a href="' + esc(C.attemptUrl + '&id=' + r.aid) + '">' + ic('eye') + ' Chi tiết bài làm & nhật ký</a>');
    return '<div class="dropdown"><button type="button" class="btn btn-sm btn-ghost btn-icon" data-dropdown aria-label="Thao tác">' + ic('ellipsis-vertical') + '</button><div class="dropdown-menu">' + items.join('') + '</div></div>';
  }

  function renderRows() {
    var rows = S.data.rows, seen = {};
    if (tbody.querySelector('.empty')) tbody.innerHTML = '';
    var shown = 0;
    rows.forEach(function (r, i) {
      seen[r.uid] = true;
      var tr = S.rows[r.uid];
      var c = cells(r);
      if (!tr) {
        tr = d.createElement('tr');
        tr.dataset.uid = r.uid;
        tr.innerHTML = (C.canAct ? '<td><input type="checkbox" data-chk></td>' : '') +
          '<td data-k="person"></td><td data-k="status"></td><td data-k="prog"></td><td class="text-right nowrap" data-k="rem"></td><td class="text-center" data-k="viol"></td><td class="hide-sm" data-k="dev"></td>' + (C.canResults ? '<td class="text-right" data-k="score"></td>' : '') + '<td class="col-actions" data-k="menu"></td>';
        S.rows[r.uid] = tr;
        tr._c = {};
      }
      if (tbody.children[i] !== tr) tbody.insertBefore(tr, tbody.children[i] || null);
      c.menu = menu(r);
      Object.keys(c).forEach(function (k) {
        if (tr._c[k] === c[k]) return;
        var td = tr.querySelector('[data-k="' + k + '"]');
        if (k === 'menu' && td.querySelector('.dropdown.open')) return; // đang mở menu – để lần sau
        td.innerHTML = c[k];
        tr._c[k] = c[k];
      });
      tr._r = r;
      var vis = visible(r);
      tr.hidden = !vis;
      if (vis) shown++;
      tr.classList.toggle('row-warn', r.st === 'offline' || !!r.blocked);
      tr.classList.toggle('row-danger', r.st === 'locked');
    });
    Object.keys(S.rows).forEach(function (uid) { if (!seen[uid]) { S.rows[uid].remove(); delete S.rows[uid]; } });
    var empty = d.getElementById('rows-empty');
    if (!shown) {
      if (!empty) { empty = d.createElement('tr'); empty.id = 'rows-empty'; empty.innerHTML = '<td colspan="' + (7 + (C.canAct ? 1 : 0) + (C.canResults ? 1 : 0)) + '" class="text-center text-muted" style="padding:28px">Không có học sinh phù hợp bộ lọc.</td>'; }
      tbody.appendChild(empty);
    } else if (empty) empty.remove();
    syncBulk();
  }

  function tickRem() {
    if (!S.data) return;
    Object.keys(S.rows).forEach(function (uid) {
      var tr = S.rows[uid], r = tr._r;
      if (!r || r.status !== 'in_progress') return;
      var c = cells(r);
      ['rem', 'status'].forEach(function (k) {
        if (tr._c[k] !== c[k]) { tr.querySelector('[data-k="' + k + '"]').innerHTML = c[k]; tr._c[k] = c[k]; }
      });
    });
  }
  setInterval(tickRem, 1000);

  function renderAlerts() {
    var box = d.getElementById('alerts');
    if (!box) return;
    var list = [];
    S.data.rows.forEach(function (r) {
      if (r.blocked && !r.free) list.push(['danger', 'shield-alert', '<b>' + esc(r.name) + '</b> (' + esc(r.code) + ') đang cố mở bài thi từ một máy khác. Nếu em ấy vừa đổi máy vì sự cố, hãy mở khóa thiết bị.', C.deviceLock ? ['unlock_device', 'Mở khóa thiết bị', r.aid] : null]);
      else if (r.st === 'locked') list.push(['danger', 'lock', '<b>' + esc(r.name) + '</b> bị khóa bài do rời màn hình ' + r.viol + ' lần.', ['unlock_violation', 'Mở khóa', r.aid]]);
      else if (r.st === 'offline' && r.seen && serverNow() - r.seen > 120) list.push(['warning', 'wifi-off', '<b>' + esc(r.name) + '</b> mất kết nối ' + ago(r.seen) + '. Kiểm tra máy/mạng của học sinh (bài làm vẫn được lưu trên máy).', null]);
    });
    box.innerHTML = list.slice(0, 6).map(function (a) {
      return '<div class="alert alert-' + a[0] + ' mb-2">' + ic(a[1]) + '<div class="grow">' + a[2] + '</div>' + (a[3] && C.canAct ? '<button type="button" class="btn btn-sm" data-act="' + a[3][0] + '" data-aid="' + a[3][2] + '">' + esc(a[3][1]) + '</button>' : '') + '</div>';
    }).join('') + (list.length > 6 ? '<div class="text-sm text-muted mb-2">… và ' + (list.length - 6) + ' trường hợp khác (lọc "Cần xử lý").</div>' : '');
  }

  function renderEvents() {
    var evs = S.data.events || [];
    if (evs.length) {
      S.since = Math.max(S.since, evs[0].id);
      S.events = evs.concat(S.events).slice(0, 150);
    }
    var box = d.getElementById('events');
    if (!box || !S.events.length) return;
    box.innerHTML = S.events.map(function (e) {
      return '<div class="tl-item ' + (e.level === 'warning' ? 'warning' : e.level === 'success' ? 'success' : '') + '"><div class="tl-time">' + TN.fmtTime(e.created_at, true) + '</div>' +
        '<div class="tl-title">' + esc(e.full_name) + ' · <span class="fw-600">' + esc(e.label) + '</span></div>' + (e.detail ? '<div class="tl-data">' + esc(e.detail) + '</div>' : '') + '</div>';
    }).join('');
    var c = d.getElementById('ev-count');
    if (c) c.textContent = S.events.length + ' sự kiện gần nhất';
  }

  function renderMessages() {
    var box = d.getElementById('msgs');
    var m = S.data.messages || [];
    if (!box || !m.length) return;
    box.innerHTML = '<div class="msg-list">' + m.map(function (x) {
      return '<div class="msg-item ' + esc(x.level) + '"><small>' + TN.fmtTime(x.created_at, true) + ' · ' + (x.user_id ? 'gửi ' + esc(x.full_name || '') : 'cả phòng') + '</small>' + esc(x.message) + '</div>';
    }).join('') + '</div>';
  }

  // ------------------------------------------------------------------ Thao tác
  function askMinutes(title, def, allowNeg) {
    return TN.confirm({ title: title, icon: 'timer', message: allowNeg ? 'Nhập số phút (số âm để bớt giờ):' : 'Nhập số phút:', input: { type: 'number', value: String(def), label: 'Số phút' }, ok: 'Xác nhận' })
      .then(function (v) {
        if (v === false || v === null) return null;
        var n = parseInt(v, 10);
        if (!isFinite(n) || n === 0 && !allowNeg) { TN.toast('Số phút không hợp lệ.', 'warning'); return null; }
        return n;
      });
  }
  function askMessage(title) {
    return new Promise(function (resolve) {
      var done = false;
      var m = TN.modal({
        title: title, icon: 'message-square', size: 'sm',
        body: '<div class="field"><label>Nội dung</label><textarea class="textarea" data-msg rows="4" maxlength="500" placeholder="VD: Còn 10 phút, các em kiểm tra lại phiếu trả lời."></textarea></div>' +
          '<div class="field"><label>Mức độ</label><select class="select" data-lv><option value="info">Thông tin</option><option value="warning">Nhắc nhở</option><option value="danger">Cảnh báo</option><option value="success">Tích cực</option></select></div>',
        foot: '<button class="btn btn-ghost" data-close>Hủy</button><button class="btn btn-primary" data-send>' + ic('send') + ' Gửi</button>',
        onClose: function () { if (!done) resolve(null); }
      });
      var ta = m.el.querySelector('[data-msg]');
      setTimeout(function () { ta.focus(); }, 50);
      m.el.querySelector('[data-send]').onclick = function () {
        if (!ta.value.trim()) { ta.focus(); return; }
        done = true;
        var v = { message: ta.value.trim(), level: m.el.querySelector('[data-lv]').value };
        m.close();
        resolve(v);
      };
    });
  }
  var CONFIRM = {
    force_submit: ['Thu bài của học sinh ngay bây giờ?', true],
    void: ['Hủy bài làm này để học sinh thi lại TỪ ĐẦU? Bài cũ vẫn được lưu để đối chiếu nhưng không tính điểm.', true],
    delete: ['Xóa VĨNH VIỄN bài làm (kể cả nhật ký)? Không thể hoàn tác.', true],
    unlock_device: ['Mở khóa thiết bị? Lần đăng nhập tiếp theo (kể cả ở máy khác) sẽ được nhận bài và làm tiếp. Máy cũ sẽ bị chặn.', false],
    pause: ['Tạm dừng bài làm của học sinh? Thời gian được giữ nguyên.', false]
  };
  function act(action, aids) {
    var extra = {};
    var p = Promise.resolve(true);
    if (action === 'add_time') p = askMinutes('Cộng / bớt thời gian làm bài', 5, true).then(function (n) { if (n === null) return false; extra.minutes = n; return true; });
    else if (action === 'reopen') p = askMinutes('Mở lại bài làm – cộng thêm thời gian', 5, false).then(function (n) { if (n === null) return false; extra.minutes = n; return true; });
    else if (action === 'message') p = askMessage(aids.length > 1 ? 'Nhắn tin cho ' + aids.length + ' học sinh' : 'Nhắn tin riêng cho học sinh').then(function (v) { if (!v) return false; Object.assign(extra, v); return true; });
    else if (CONFIRM[action]) p = TN.confirm({ message: CONFIRM[action][0] + (aids.length > 1 ? ' (' + aids.length + ' học sinh)' : ''), danger: CONFIRM[action][1] });
    return p.then(function (ok) {
      if (!ok) return;
      return TN.api(C.actUrl, { data: Object.assign({ action: action, aids: aids }, extra) }).then(function (r) {
        TN.toast(r.message, 'success');
        load();
      }, function (e) { TN.toast(e.message, 'error'); });
    });
  }
  function control(action, btn) {
    var extra = {};
    var p;
    if (action === 'add_time') p = askMinutes('Cộng giờ cho cả phòng thi', 5, false).then(function (n) { if (n === null) return false; extra.minutes = n; return true; });
    else if (action === 'message') p = askMessage('Thông báo tới cả phòng thi').then(function (v) { if (!v) return false; Object.assign(extra, v); return true; });
    else if (btn.dataset.confirm) p = TN.confirm({ message: btn.dataset.confirm, danger: action === 'close' || action === 'pause' });
    else p = Promise.resolve(true);
    p.then(function (ok) {
      if (!ok) return;
      TN.busy(btn, true);
      TN.api(C.ctlUrl, { data: Object.assign({ action: action }, extra) }).then(function (r) {
        TN.busy(btn, false);
        TN.toast(r.message, 'success');
        load();
      }, function (e) { TN.busy(btn, false); TN.toast(e.message, 'error'); });
    });
  }

  d.addEventListener('click', function (e) {
    var a = e.target.closest('[data-act]');
    if (a) { e.preventDefault(); d.querySelectorAll('.dropdown.open').forEach(function (x) { x.classList.remove('open'); }); act(a.dataset.act, [+a.dataset.aid]); return; }
    var b = e.target.closest('[data-bulk]');
    if (b) { e.preventDefault(); var ids = selected(); if (!ids.length) { TN.toast('Chọn học sinh (đã vào thi) trước.', 'warning'); return; } act(b.dataset.bulk, ids); return; }
    var c = e.target.closest('[data-ctl]');
    if (c) { e.preventDefault(); control(c.dataset.ctl, c); }
  });

  // ------------------------------------------------------------------ Chọn nhiều, lọc
  function selected() {
    var ids = [];
    Object.keys(S.rows).forEach(function (uid) {
      var tr = S.rows[uid], cb = tr.querySelector('[data-chk]');
      if (cb && cb.checked && !tr.hidden && tr._r && tr._r.aid) ids.push(tr._r.aid);
    });
    return ids;
  }
  function syncBulk() {
    var bar = d.getElementById('bulk');
    if (!bar) return;
    var n = 0;
    Object.keys(S.rows).forEach(function (uid) { var cb = S.rows[uid].querySelector('[data-chk]'); if (cb && cb.checked && !S.rows[uid].hidden) n++; });
    bar.classList.toggle('show', n > 0);
    bar.querySelector('[data-count]').textContent = n;
  }
  tbody.addEventListener('change', function (e) { if (e.target.matches('[data-chk]')) syncBulk(); });
  var all = d.getElementById('chk-all');
  if (all) all.addEventListener('change', function () {
    Object.keys(S.rows).forEach(function (uid) { var tr = S.rows[uid], cb = tr.querySelector('[data-chk]'); if (cb && !tr.hidden) cb.checked = all.checked; });
    syncBulk();
  });
  var fs = d.getElementById('f-status');
  if (fs) fs.addEventListener('click', function (e) {
    var b = e.target.closest('[data-f]');
    if (!b) return;
    fs.querySelectorAll('[data-f]').forEach(function (x) { x.classList.toggle('active', x === b); });
    S.filter = b.dataset.f;
    if (S.data) renderRows();
  });
  var fc = d.getElementById('f-class');
  if (fc) fc.addEventListener('change', function () { S.cls = fc.value; if (S.data) renderRows(); });
  var fq = d.getElementById('f-q');
  if (fq) fq.addEventListener('input', TN.debounce(function () {
    var v = fq.value.trim().toLowerCase();
    S.q = TN.unaccent ? TN.unaccent(v) : v;
    if (S.data) renderRows();
  }, 200));

  TN.ready(load);
})(window, document);

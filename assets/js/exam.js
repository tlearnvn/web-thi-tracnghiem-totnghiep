/* =====================================================================
   PHÒNG THI – điều khiển toàn bộ quá trình làm bài
   • Máy chủ là nguồn sự thật về thời gian (đồng bộ đồng hồ theo mỗi phản hồi).
   • Mỗi thao tác đều lưu dự phòng vào máy (localStorage) rồi gửi lên máy chủ
     theo số thứ tự tăng dần (seq) → mất mạng, tải lại trang, sập nguồn đều không mất bài.
   • Xử lý sự cố: mất mạng, hết phiên đăng nhập, bài mở ở máy/tab khác,
     tạm dừng / khóa bài, hết giờ khi đang mất mạng, giám sát rời màn hình.
   ===================================================================== */
(function (w, d) {
  'use strict';
  var C = w.EXAM_CFG;
  if (!C || !w.TN) return;
  var $ = function (s) { return d.querySelector(s); };
  var ic = function (n, c) { return TN.icon(n, c); };
  var esc = TN.esc;
  var LS = 'tn_att_' + C.aid;
  var TAB = Math.random().toString(36).slice(2) + Date.now().toString(36);
  var perf = w.performance && performance.now ? function () { return performance.now(); } : function () { return Date.now(); };

  var S = {
    sheet: null, viewer: null, pdfOpened: false,
    seq: 0, ackSeq: 0, dirty: false, inFlight: false, saveTimer: 0, retryDelay: 0,
    clock: null, rtt: 1e9,
    deadline: 0, paused: false, locked: false, pausedRemaining: null, startedAt: 0,
    token: C.deviceToken || null,
    lastMsg: 0, seenMsg: 0, messages: [], unread: 0,
    config: { autosave_ms: 1200, heartbeat: 20, track_focus: 0, require_fullscreen: 0, max_violations: 0, violation_action: 'log', confirm_submit: 1, min_submit_at: 0, protect: 1, grace: 90, duration: 0 },
    online: true, started: false, finishing: false, submitted: false, inactive: false,
    needLogin: false, blocked: false, gone: false,
    warned: {}, violations: 0, events: [], hbTimer: 0, tickTimer: 0, bootDelay: 0, timeUpBusy: false
  };

  // ------------------------------------------------------------------ Đồng hồ máy chủ (đơn điệu, không bị ảnh hưởng khi đổi giờ máy)
  function syncClock(nowSec, p0, p1) {
    var rtt = p1 - p0;
    var server = nowSec * 1000 + 500; // máy chủ trả giây nguyên -> lấy giữa khoảng
    var mid = (p0 + p1) / 2;
    if (!S.clock) { S.clock = { server: server, perf: mid }; S.rtt = rtt; return; }
    var predicted = S.clock.server + (mid - S.clock.perf);
    if (rtt <= S.rtt * 1.6 || Math.abs(predicted - server) > 2500) {
      S.clock = { server: server, perf: mid };
      S.rtt = Math.min(rtt, Math.max(S.rtt, 50));
    }
  }
  function serverNow() {
    if (!S.clock) return Date.now() + (TN.serverOffset || 0);
    return S.clock.server + (perf() - S.clock.perf);
  }
  function remainingSec() {
    if (!S.deadline) return null;
    if (S.paused || S.locked) return S.pausedRemaining;
    return Math.max(0, Math.ceil((S.deadline * 1000 - serverNow()) / 1000));
  }

  // ------------------------------------------------------------------ Gọi API phòng thi
  function call(url, data, opts) {
    opts = opts || {};
    var headers = { 'X-Requested-With': 'tnexam' };
    if (S.token) headers['X-Device-Token'] = S.token;
    var p0 = perf();
    return TN.api(url, { data: data, headers: headers, timeout: opts.timeout || 20000, keepalive: !!opts.keepalive }).then(function (r) {
      if (r.now) syncClock(r.now, p0, perf());
      if (r.device_token) S.token = r.device_token;
      setOnline(true);
      return r;
    }, function (err) {
      var st = err.status;
      if (err.network || st === 0 || st === 502 || st === 503 || st === 504) setOnline(false, st);
      else setOnline(true);
      if (st === 401 || st === 419) needLogin();
      else if (st === 423) deviceLocked(err.message);
      else if (st === 409 && err.data && err.data.code === 'closed') finished(err.data.redirect);
      else if (st === 404) gone(err.message);
      throw err;
    });
  }

  // ------------------------------------------------------------------ Lớp phủ (tạm dừng, khóa, mất kết nối…)
  var overlays = {};
  function overlay(id, o) {
    var el = overlays[id];
    if (!el) { el = d.createElement('div'); el.className = 'xr-overlay'; el.setAttribute('role', 'dialog'); d.body.appendChild(el); overlays[id] = el; }
    el.hidden = false;
    var btns = (o.buttons || []).map(function (b, i) {
      return '<button type="button" class="btn ' + (b.primary ? 'btn-primary btn-lg' : 'btn-lg') + '" data-i="' + i + '">' + (b.icon ? ic(b.icon) : '') + esc(b.label) + '</button>';
    }).join('');
    el.innerHTML = '<div class="xr-overlay-card ' + (o.cls || '') + '"><div class="big-ic">' + (o.spin ? '<span class="spinner lg"></span>' : ic(o.icon || 'info')) + '</div>' +
      '<h2>' + esc(o.title || '') + '</h2>' + (o.html || '<p>' + esc(o.text || '') + '</p>') +
      (btns ? '<div class="btns">' + btns + '</div>' : '') + (o.note ? '<div class="note">' + o.note + '</div>' : '') + '</div>';
    (o.buttons || []).forEach(function (b, i) { var x = el.querySelector('[data-i="' + i + '"]'); if (x) x.onclick = function () { b.fn(x); }; });
    return el;
  }
  function hideOverlay(id) { if (overlays[id]) overlays[id].hidden = true; }
  function hideBoot() { var b = $('#xr-boot'); if (b) b.hidden = true; }
  function bootMsg(t) { var m = $('#boot-msg'); if (m) m.textContent = t; }

  // ------------------------------------------------------------------ Trạng thái kết nối & lưu
  var banner;
  function setOnline(v) {
    if (v === S.online) return;
    S.online = v;
    if (!banner) { banner = d.createElement('div'); banner.className = 'xr-banner offline'; banner.hidden = true; d.body.appendChild(banner); }
    banner.innerHTML = ic('wifi-off') + '<span>Mất kết nối máy chủ. Em cứ tiếp tục làm bài – bài làm đang được lưu trên máy này và sẽ tự gửi khi có mạng lại.</span>';
    banner.hidden = v;
    if (v) {
      TN.toast('Đã kết nối lại máy chủ. Bài làm đang được đồng bộ.', 'success');
      flushEvents();
      if (S.dirty) scheduleSave(100);
    } else {
      chip(S.dirty ? 'offline' : 'offline-idle');
    }
  }

  var chipEl = $('#save-chip');
  function chip(state) {
    if (!chipEl) return;
    var map = {
      saved: ['', 'circle-check', 'Đã lưu ' + TN.fmtTime(Math.floor(serverNow() / 1000), true)],
      saving: ['saving', null, 'Đang lưu…'],
      pending: ['pending', 'pencil', 'Chưa lưu'],
      offline: ['offline', 'wifi-off', 'Mất mạng · đã lưu trên máy'],
      'offline-idle': ['offline', 'wifi-off', 'Mất kết nối'],
      readonly: ['pending', 'lock', 'Đã khóa phiếu']
    };
    var m = map[state] || map.saved;
    chipEl.className = 'save-chip' + (m[0] ? ' ' + m[0] : '');
    chipEl.innerHTML = (m[1] ? ic(m[1]) : '<span class="spinner" style="width:13px;height:13px;border-width:2px"></span>') + ' ' + esc(m[2]);
  }

  // ------------------------------------------------------------------ Bản sao dự phòng trên máy
  function writeBackup(extra) {
    if (!S.sheet) return;
    try {
      localStorage.setItem(LS, JSON.stringify(Object.assign({
        u: C.userId, seq: S.seq, ack: S.ackSeq, answers: S.sheet.getAnswers(), flags: S.sheet.getFlags(),
        deadline: S.deadline, seen: Math.max(S.seenMsg, S.lastMsg), tk: S.token, t: Date.now()
      }, extra || {})));
    } catch (e) { /* bộ nhớ đầy / chế độ ẩn danh */ }
  }
  function readBackup() {
    try { var b = JSON.parse(localStorage.getItem(LS) || 'null'); return b && b.u === C.userId ? b : null; } catch (e) { return null; }
  }
  function clearBackup() { try { localStorage.removeItem(LS); } catch (e) {} }
  function cleanOldBackups() {
    try {
      for (var i = localStorage.length - 1; i >= 0; i--) {
        var k = localStorage.key(i);
        if (k && k.indexOf('tn_att_') === 0 && k !== LS) {
          var b = JSON.parse(localStorage.getItem(k) || 'null');
          if (!b || !b.t || Date.now() - b.t > 7 * 86400000) localStorage.removeItem(k);
        }
      }
    } catch (e) {}
  }

  // ------------------------------------------------------------------ Lưu bài
  function scheduleSave(ms) {
    clearTimeout(S.saveTimer);
    S.saveTimer = setTimeout(doSave, ms == null ? S.config.autosave_ms : ms);
  }
  function payload(extra) {
    return Object.assign({ seq: S.seq, answers: S.sheet.getAnswers(), flags: S.sheet.getFlags(), last_msg: S.lastMsg }, extra || {});
  }
  function doSave() {
    clearTimeout(S.saveTimer);
    if (!S.dirty || S.inFlight || S.inactive || S.submitted || S.finishing || S.blocked || S.needLogin || S.gone) return;
    var seq = S.seq;
    S.inFlight = true;
    chip('saving');
    call(C.urls.save + '&last_msg=' + S.lastMsg, payload()).then(function (r) {
      S.inFlight = false;
      S.retryDelay = 0;
      if (r.saved_seq >= seq) S.ackSeq = Math.max(S.ackSeq, seq);
      if (r.saved_seq > S.seq) { // máy chủ có bản mới hơn (lưu từ tab khác) -> lấy theo máy chủ
        S.seq = r.saved_seq;
        reloadState();
        return;
      }
      S.dirty = S.seq > S.ackSeq;
      applyLive(r);
      writeBackup();
      if (S.dirty) scheduleSave(250); else chip('saved');
    }, function (err) {
      S.inFlight = false;
      if ([401, 404, 409, 419, 423].indexOf(err.status) >= 0) { chip('pending'); return; }
      S.retryDelay = Math.min(15000, S.retryDelay ? S.retryDelay * 2 : 1500);
      chip(S.online ? 'pending' : 'offline');
      if (S.online && err.status >= 400 && err.status < 500 && !S.warnedSave) { S.warnedSave = true; TN.toast(err.message, 'error', 'Chưa lưu được bài'); }
      scheduleSave(S.retryDelay);
    });
  }
  function saveKeepalive() {
    if (!S.sheet || S.submitted || !S.dirty) return;
    try { call(C.urls.save + '&last_msg=' + S.lastMsg, payload(), { keepalive: true }).catch(function () {}); } catch (e) {}
  }

  function onChange() {
    if (S.finishing || S.submitted) return;
    S.seq++;
    S.dirty = true;
    writeBackup();
    chip(S.online ? 'pending' : 'offline');
    scheduleSave();
    refreshProgress();
  }

  // ------------------------------------------------------------------ Áp dụng trạng thái từ máy chủ
  function applyLive(r) {
    if (!r) return;
    if (r.status && r.status !== 'in_progress') { finished(r.redirect || C.urls.result); return; }
    if (typeof r.deadline === 'number') S.deadline = r.deadline;
    var wasHeld = S.paused || S.locked;
    S.paused = !!r.paused;
    S.locked = !!r.locked;
    if (S.paused || S.locked) S.pausedRemaining = r.remaining;
    if (typeof r.violations === 'number') S.violations = r.violations;
    if (r.messages && r.messages.length) onMessages(r.messages);
    renderHold(wasHeld);
    tick();
  }

  function renderHold(wasHeld) {
    var held = S.paused || S.locked;
    if (held) {
      var rem = remainingSec();
      overlay('hold', S.locked ? {
        cls: 'danger', icon: 'lock', title: 'Bài thi đang bị tạm khóa',
        html: '<p>Em đã rời khỏi màn hình làm bài quá số lần cho phép (' + S.violations + ' lần). Hãy giữ nguyên vị trí và báo giám thị để được mở khóa.</p>' +
          (rem !== null ? '<div class="timer-big">' + TN.fmtDuration(rem) + '</div><p class="text-sm">Thời gian làm bài được giữ nguyên trong lúc khóa.</p>' : ''),
        note: 'Mã bài làm: <b>#' + C.aid + '</b>'
      } : {
        cls: 'warning', icon: 'circle-pause', title: 'Giám thị đã tạm dừng bài thi',
        html: '<p>Thời gian làm bài đang được giữ nguyên. Em giữ trật tự và chờ hướng dẫn của giám thị.</p>' +
          (rem !== null ? '<div class="timer-big">' + TN.fmtDuration(rem) + '</div><p class="text-sm">thời gian còn lại</p>' : '')
      });
    } else {
      hideOverlay('hold');
      if (wasHeld) TN.toast('Tiếp tục làm bài. Chúc em làm bài tốt!', 'success', 'Đã tiếp tục');
    }
  }

  // ------------------------------------------------------------------ Tin nhắn của giám thị
  function onMessages(list) {
    var fresh = [];
    list.forEach(function (m) {
      if (S.messages.some(function (x) { return x.id === m.id; })) return;
      S.messages.push(m);
      S.lastMsg = Math.max(S.lastMsg, m.id);
      if (m.id > S.seenMsg) fresh.push(m);
    });
    if (!fresh.length) return;
    S.seenMsg = Math.max(S.seenMsg, S.lastMsg);
    S.unread += fresh.length;
    updateBadge();
    fresh.forEach(function (m) {
      TN.toast(m.message, m.level === 'danger' ? 'error' : (m.level === 'success' ? 'success' : (m.level === 'warning' ? 'warning' : 'info')), 'Thông báo từ giám thị · ' + TN.fmtTime(m.created_at), 15000);
    });
    writeBackup();
  }
  function updateBadge() {
    var b = $('#msg-badge');
    if (!b) return;
    b.hidden = S.unread <= 0;
    b.textContent = S.unread;
  }
  function showMessages() {
    S.unread = 0; updateBadge();
    var html = S.messages.length ? '<div class="msg-list">' + S.messages.slice().reverse().map(function (m) {
      return '<div class="msg-item ' + esc(m.level) + '"><small>' + TN.fmtDateTime(m.created_at, true) + '</small>' + esc(m.message) + '</div>';
    }).join('') + '</div>' : '<div class="empty" style="padding:20px"><div class="empty-icon">' + ic('bell') + '</div><p>Chưa có thông báo nào từ giám thị.</p></div>';
    TN.modal({ title: 'Thông báo từ giám thị', icon: 'bell', body: html, foot: '<button class="btn btn-primary" data-close>Đã hiểu</button>' });
  }

  // ------------------------------------------------------------------ Đồng hồ đếm ngược
  var timerEl = $('#timer'), timerVal = $('#timer-val'), timerLabel = $('#timer-label');
  var THRESH = [[900, 'Còn 15 phút làm bài.', 'info'], [300, 'Còn 5 phút! Kiểm tra lại các câu chưa làm.', 'warning'], [60, 'Còn 1 phút! Bài sẽ tự động nộp khi hết giờ.', 'error']];
  function tick() {
    if (!S.started || !timerVal) return;
    var rem = remainingSec();
    var held = S.paused || S.locked;
    timerEl.classList.toggle('paused', held);
    if (rem === null) {
      var el = Math.max(0, Math.floor(serverNow() / 1000) - S.startedAt);
      timerLabel.textContent = held ? 'Tạm dừng' : 'Đã làm';
      timerVal.textContent = TN.fmtDuration(el);
      timerEl.classList.remove('warn', 'danger');
      return;
    }
    timerLabel.textContent = held ? 'Tạm dừng' : 'Còn lại';
    timerVal.textContent = TN.fmtDuration(rem);
    timerEl.classList.toggle('warn', !held && rem <= 300 && rem > 60);
    timerEl.classList.toggle('danger', !held && rem <= 60);
    if (held || S.finishing) return;
    THRESH.forEach(function (t) {
      if (rem <= t[0] && !S.warned[t[0]]) {
        S.warned[t[0]] = true;
        if (rem > t[0] - 20) TN.toast(t[1], t[2], 'Thời gian', 9000);
      }
    });
    if (rem <= 0) timeUp();
  }
  function timeUp() {
    if (S.finishing || S.timeUpBusy || S.submitted) return;
    S.timeUpBusy = true;
    // Xác nhận lại với máy chủ trước khi thu bài (có thể vừa được cộng giờ)
    call(C.urls.ping + '&last_msg=' + S.lastMsg, { last_msg: S.lastMsg }, { timeout: 8000 }).then(function (r) {
      S.timeUpBusy = false;
      applyLive(r);
      if (!S.submitted && remainingSec() <= 0 && !S.paused && !S.locked) submit(true);
    }, function (err) {
      S.timeUpBusy = false;
      if ([401, 409, 419, 423, 404].indexOf(err.status) < 0) submit(true);
    });
  }

  // ------------------------------------------------------------------ Nộp bài
  function lockSheet() {
    if (!S.sheet) return;
    S.sheet.readOnly = true;
    S.sheet.el.classList.add('is-locked');
  }
  function unlockSheet() {
    if (!S.sheet) return;
    S.sheet.readOnly = false;
    S.sheet.el.classList.remove('is-locked');
  }
  function submit(auto) {
    if (S.submitted) return;
    S.finishing = true;
    clearTimeout(S.saveTimer);
    lockSheet();
    S.seq++;
    writeBackup();
    var body = payload({ auto: auto ? 1 : 0 });
    var tries = 0, retryT = 0;
    overlay('finish', { spin: true, title: auto ? 'Hết giờ làm bài!' : 'Đang nộp bài…', text: auto ? 'Hệ thống đang thu bài của em. Em không cần làm gì thêm.' : 'Đang gửi bài làm lên máy chủ, em đừng tắt máy.' });
    function go() {
      clearTimeout(retryT);
      tries++;
      overlay('finish', { spin: true, title: auto ? 'Hết giờ làm bài!' : 'Đang nộp bài…', text: tries > 1 ? 'Đang thử gửi lại bài làm (lần ' + tries + ')…' : 'Đang gửi bài làm lên máy chủ, em đừng tắt máy.' });
      call(C.urls.submit, body, { timeout: 30000 }).then(function (r) {
        S.submitted = true;
        stopAll();
        // Nếu máy chủ đã thu bài trước đó (hết giờ khi mất mạng) mà máy còn bản chưa gửi -> giữ lại bản dự phòng để giám thị có thể mở lại bài
        if (r.already && S.seq > S.ackSeq + 1) writeBackup({ closed: true }); else clearBackup();
        overlay('finish', { cls: '', icon: 'circle-check', title: 'Đã nộp bài thành công!', text: 'Đang chuyển đến trang kết quả…' });
        setTimeout(function () { location.replace(r.redirect || C.urls.result); }, 700);
      }, function (err) {
        if (S.submitted) return;
        if (err.status === 422) {
          S.finishing = false;
          unlockSheet();
          hideOverlay('finish');
          TN.toast(err.message, 'warning', 'Chưa nộp được bài');
          return;
        }
        if ([401, 419, 423, 409, 404].indexOf(err.status) >= 0) { S.retrySubmit = go; hideOverlay('finish'); return; }
        var wait = Math.min(20, 2 + tries * 2);
        overlay('finish', {
          cls: 'warning', icon: 'wifi-off', title: 'Chưa gửi được bài làm',
          html: '<p>' + (err.network ? 'Máy đang mất kết nối mạng.' : esc(err.message)) + ' Bài làm của em <b>đã được lưu an toàn trên máy này</b> và hệ thống sẽ tự thử lại sau ' + wait + ' giây.</p><p class="text-sm">Không tắt trình duyệt. Nếu mất mạng lâu, hãy báo giám thị.</p>',
          buttons: [{ label: 'Thử lại ngay', icon: 'refresh-cw', primary: true, fn: go }]
        });
        retryT = setTimeout(go, wait * 1000);
      });
    }
    S.retrySubmit = go;
    go();
  }

  function stopAll() {
    clearTimeout(S.saveTimer);
    clearTimeout(S.hbTimer);
    clearInterval(S.tickTimer);
    clearInterval(S.claimTimer);
  }

  function finished(url) {
    if (S.submitted) return;
    S.submitted = true;
    stopAll();
    lockSheet();
    if (S.dirty && S.seq > S.ackSeq) writeBackup({ closed: true }); else clearBackup();
    overlay('finish', { icon: 'circle-check', title: 'Bài thi đã được thu', text: 'Bài làm của em đã được nộp. Đang chuyển đến trang kết quả…' });
    setTimeout(function () { location.replace(url || C.urls.result); }, 1500);
  }

  function gone(msg) {
    if (S.gone) return;
    S.gone = true;
    stopAll();
    hideBoot();
    overlay('gone', { cls: 'danger', icon: 'circle-x', title: 'Không mở được bài làm', text: msg || 'Bài làm không còn tồn tại.', buttons: [{ label: 'Về trang chủ', icon: 'house', primary: true, fn: function () { location.href = C.urls.home; } }] });
  }

  function confirmSubmit() {
    if (!S.started || S.finishing || S.submitted) return;
    if (S.paused || S.locked) { TN.toast('Bài thi đang tạm dừng, chưa thể nộp bài.', 'warning'); return; }
    var minAt = S.config.min_submit_at;
    if (minAt && serverNow() < minAt * 1000) {
      var left = Math.ceil((minAt * 1000 - serverNow()) / 60000);
      TN.toast('Em có thể nộp bài sau ' + left + ' phút nữa. Hãy tranh thủ kiểm tra lại bài.', 'warning', 'Chưa đến giờ nộp bài');
      return;
    }
    var st = S.sheet.stats();
    var invalid = [];
    for (var i = 1; i <= C.structure.p3; i++) {
      var v = S.sheet.value('p3.' + i);
      if (v && v.replace(/_/g, '') && !AnswerSheet.validateP3(v, C.structure.p3_len || 4)[0]) invalid.push('p3.' + i);
    }
    var chips = function (arr) { return arr.map(function (q) { return '<button type="button" class="qchip" data-go="' + q + '">' + esc(S.sheet.shortLabel(q)) + '</button>'; }).join(''); };
    var rem = remainingSec();
    var body = '<div class="submit-sum"><div><b>' + st.answered + '/' + st.total + '</b><span>Đã làm</span></div>' +
      '<div class="' + (st.unanswered.length ? 'warn' : '') + '"><b>' + st.unanswered.length + '</b><span>Chưa làm</span></div>' +
      '<div class="' + (st.flagged.length ? 'warn' : '') + '"><b>' + st.flagged.length + '</b><span>Đánh dấu xem lại</span></div></div>';
    if (st.unanswered.length) body += '<div class="text-sm fw-600">Câu chưa làm <span class="text-muted">(bấm để chuyển đến câu đó)</span></div><div class="submit-list">' + chips(st.unanswered) + '</div>';
    if (st.partial.length) body += '<div class="text-sm fw-600">Câu đúng/sai chưa chọn đủ 4 ý</div><div class="submit-list">' + chips(st.partial) + '</div>';
    if (invalid.length) body += '<div class="text-sm fw-600 text-danger">Câu trả lời ngắn tô chưa đúng quy cách (sẽ không được tính điểm)</div><div class="submit-list">' + chips(invalid) + '</div>';
    if (st.flagged.length) body += '<div class="text-sm fw-600">Câu đã đánh dấu</div><div class="submit-list">' + chips(st.flagged) + '</div>';
    body += '<div class="alert alert-warning mb-0">' + ic('triangle-alert') + '<div>Sau khi nộp, em <b>không thể sửa</b> bài làm nữa.' + (rem !== null ? ' Thời gian còn lại: <b>' + TN.fmtDuration(rem) + '</b>.' : '') + '</div></div>';
    var m = TN.modal({
      title: 'Em chắc chắn muốn nộp bài?', icon: 'send', body: body,
      foot: '<button class="btn btn-ghost" data-close>' + ic('pencil') + ' Làm tiếp</button><button class="btn btn-primary" data-ok>' + ic('send') + ' Nộp bài</button>'
    });
    var ok = m.el.querySelector('[data-ok]');
    if (S.config.confirm_submit) {
      var n = 3, label = ok.innerHTML;
      ok.disabled = true;
      ok.innerHTML = label + ' (' + n + ')';
      var iv = setInterval(function () { n--; if (n <= 0) { clearInterval(iv); ok.disabled = false; ok.innerHTML = label; } else ok.innerHTML = label + ' (' + n + ')'; }, 1000);
    }
    ok.addEventListener('click', function () { m.close(); submit(false); });
    m.el.querySelectorAll('[data-go]').forEach(function (b) { b.addEventListener('click', function () { m.close(); gotoQuestion(b.dataset.go); }); });
  }

  // ------------------------------------------------------------------ Đăng nhập lại tại chỗ (hết phiên)
  function needLogin() {
    if (S.needLogin || S.submitted) return;
    S.needLogin = true;
    hideBoot();
    var m = TN.modal({
      title: 'Đăng nhập lại để làm tiếp', icon: 'log-in', size: 'sm', dismissible: false,
      body: '<p class="text-muted">Phiên đăng nhập đã hết hạn (có thể do mất mạng lâu). <b>Bài làm của em vẫn an toàn</b> – nhập lại mật khẩu để tiếp tục, thời gian vẫn tính bình thường.</p>' +
        '<div class="field"><label>Tài khoản</label><input class="input" value="' + esc(C.username) + '" disabled></div>' +
        '<div class="field"><label>Mật khẩu</label><input class="input input-lg" type="password" data-pw autocomplete="current-password"></div><div class="field-error" data-err></div>',
      foot: '<button class="btn btn-primary btn-block" data-login>' + ic('log-in') + ' Đăng nhập & làm tiếp</button>'
    });
    var pw = m.el.querySelector('[data-pw]'), btn = m.el.querySelector('[data-login]'), errEl = m.el.querySelector('[data-err]');
    function go() {
      if (!pw.value) { pw.focus(); return; }
      TN.busy(btn, true);
      errEl.textContent = '';
      fetch(TN.url('auth/csrf'), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          setCsrf(j.csrf);
          return TN.api(C.urls.login, { data: { username: C.username, password: pw.value, expect_user: C.userId, _token: j.csrf } });
        })
        .then(function (r) {
          setCsrf(r.csrf);
          S.needLogin = false;
          m.close();
          TN.toast('Đã đăng nhập lại. Em tiếp tục làm bài nhé!', 'success');
          resume();
          sendEvent('relogin', '');
        })
        .catch(function (e) { TN.busy(btn, false); errEl.textContent = e.network ? 'Chưa kết nối được máy chủ, thử lại sau giây lát.' : e.message; pw.select(); });
    }
    btn.addEventListener('click', go);
    pw.addEventListener('keydown', function (e) { if (e.key === 'Enter') go(); });
    setTimeout(function () { pw.focus(); }, 60);
  }
  function setCsrf(t) {
    if (!t) return;
    TN.csrf = t;
    var mt = d.querySelector('meta[name="csrf-token"]');
    if (mt) mt.setAttribute('content', t);
  }
  /** Tiếp tục các việc đang dở sau khi hết sự cố (đăng nhập lại / mở khóa thiết bị). */
  function resume() {
    if (!S.started) { boot(); return; }
    if (S.finishing && !S.submitted && S.retrySubmit) { S.retrySubmit(); return; }
    reloadState(true);
  }

  // ------------------------------------------------------------------ Bài đang mở trên máy khác
  function deviceLocked(msg) {
    if (S.submitted) return;
    hideBoot();
    if (!S.blocked) {
      S.blocked = true;
      clearInterval(S.claimTimer);
      S.claimTimer = setInterval(tryClaim, 6000);
    }
    overlay('device', {
      cls: 'danger', icon: 'shield-alert', title: 'Bài thi đang được làm trên máy khác',
      text: msg || 'Bài thi của em đang được mở trên một thiết bị khác.',
      buttons: [{ label: 'Thử lại', icon: 'refresh-cw', primary: true, fn: function (b) { TN.busy(b, true); tryClaim(function () { TN.busy(b, false); }); } }],
      note: 'Đọc cho giám thị: <b>' + esc(C.header.name) + '</b> – SBD <b>' + esc(C.header.code) + '</b> – mã bài <b>#' + C.aid + '</b>. Hệ thống tự thử lại mỗi 6 giây.'
    });
  }
  function tryClaim(done) {
    call(C.urls.claim, {}).then(function () {
      S.blocked = false;
      clearInterval(S.claimTimer);
      hideOverlay('device');
      TN.toast('Đã nhận lại bài thi trên máy này.', 'success');
      resume();
    }, function () {}).then(function () { if (done) done(); });
  }

  // ------------------------------------------------------------------ Nhiều tab cùng mở một bài
  var bc = null;
  try { bc = w.BroadcastChannel ? new BroadcastChannel('tn-exam-' + C.aid) : null; } catch (e) { bc = null; }
  function announce() {
    if (bc) bc.postMessage({ t: 'hello', id: TAB });
    else { try { localStorage.setItem('tn_tab_' + C.aid, TAB + ':' + Date.now()); } catch (e) {} }
  }
  function onOtherTab(id) {
    if (id === TAB || S.inactive || S.submitted || !S.started) return;
    if (S.dirty) doSaveNow();
    S.inactive = true;
    clearTimeout(S.hbTimer);
    overlay('tab', {
      cls: 'warning', icon: 'layers', title: 'Bài thi đã được mở ở tab khác',
      text: 'Để không ghi đè bài làm, tab này đã tạm ngừng. Em chỉ nên làm bài ở một tab/cửa sổ duy nhất.',
      buttons: [{ label: 'Làm bài ở tab này', icon: 'mouse-pointer-click', primary: true, fn: takeOver }]
    });
    if (bc) bc.postMessage({ t: 'busy', id: TAB });
  }
  function doSaveNow() { S.inFlight = false; doSave(); }
  function takeOver() {
    S.inactive = false;
    hideOverlay('tab');
    announce();
    reloadState(true);
    heartbeat();
  }
  if (bc) bc.onmessage = function (e) {
    var m = e.data || {};
    if (m.t === 'hello') onOtherTab(m.id);
    else if (m.t === 'busy' && m.id !== TAB && S.started) sendEvent('multi_tab', 'Mở thêm tab thứ hai');
  };
  else w.addEventListener('storage', function (e) { if (e.key === 'tn_tab_' + C.aid && e.newValue) onOtherTab(e.newValue.split(':')[0]); });

  // ------------------------------------------------------------------ Giám sát rời màn hình, chặn sao chép / in
  var lastLeave = 0, lastCtx = 0;
  function sendEvent(type, detail) {
    var ev = { type: type, detail: String(detail || '').slice(0, 200) };
    if (!S.online) { S.events.push(ev); return Promise.resolve(null); }
    return call(C.urls.event, ev, { keepalive: true, timeout: 10000 }).then(function (r) {
      applyLive(r);
      if (r.counted) onViolation(r);
      return r;
    }, function (err) { if (err.network || !err.status) S.events.push(ev); return null; });
  }
  function flushEvents() {
    if (!S.events.length || !S.online) return;
    var list = S.events.splice(0, S.events.length);
    list.reduce(function (p, ev) { return p.then(function () { return sendEvent(ev.type, ev.detail); }); }, Promise.resolve());
  }
  function leave(detail) {
    if (!S.started || S.finishing || S.submitted || S.inactive || S.paused || S.locked || S.blocked || !S.config.track_focus) return;
    var now = Date.now();
    if (now - lastLeave < 4000 || now - (S.unloadAt || 0) < 10000) return;
    lastLeave = now;
    sendEvent('leave', detail);
  }
  function onViolation(r) {
    if (r.action === 'submitted') return; // applyLive đã chuyển trang
    if (r.action === 'locked') return;    // lớp phủ khóa đã hiện
    var max = r.max || 0;
    overlay('warn', {
      cls: 'warning', icon: 'triangle-alert', title: 'Cảnh báo rời khỏi bài thi',
      html: '<p>Hệ thống ghi nhận em vừa <b>rời khỏi màn hình làm bài</b> (lần thứ <b>' + r.violations + '</b>' + (max ? '/' + max : '') + ').</p>' +
        (max ? '<p class="text-sm">' + (S.config.violation_action === 'submit' ? 'Nếu vượt quá ' + max + ' lần, bài thi sẽ bị <b>tự động thu</b>.' : S.config.violation_action === 'lock' ? 'Nếu vượt quá ' + max + ' lần, bài thi sẽ bị <b>tạm khóa</b> cho đến khi giám thị mở.' : 'Giám thị sẽ xem xét các lần rời màn hình.') + '</p>' : '<p class="text-sm">Mọi lần rời màn hình đều được lưu lại để giám thị xem xét.</p>'),
      buttons: [{ label: 'Em đã hiểu, tiếp tục làm bài', icon: 'check', primary: true, fn: function () { hideOverlay('warn'); } }]
    });
  }
  function inEditable(t) { return t && t.closest && !!t.closest('textarea, input, [contenteditable="true"]'); }

  function setupGuards() {
    // Rời màn hình: chỉ ghi nhận khi trang còn "sống" sau một lúc bị ẩn → tải lại / đóng trang không bị tính nhầm
    var hiddenAt = 0, hiddenT = 0;
    d.addEventListener('visibilitychange', function () {
      if (d.visibilityState === 'hidden') {
        writeBackup();
        saveKeepalive();
        hiddenAt = Date.now();
        clearTimeout(hiddenT);
        hiddenT = setTimeout(function () { hiddenT = 0; if (d.visibilityState === 'hidden') leave('Chuyển tab / thu nhỏ trình duyệt'); }, 1500);
      } else {
        if (hiddenT) { clearTimeout(hiddenT); hiddenT = 0; if (Date.now() - hiddenAt > 400) leave('Chuyển tab / thu nhỏ trình duyệt (' + Math.round((Date.now() - hiddenAt) / 100) / 10 + ' giây)'); }
        if (S.started && !S.submitted && !S.inactive) quickPing();
      }
    });
    var blurT = 0;
    w.addEventListener('blur', function () {
      clearTimeout(blurT);
      blurT = setTimeout(function () { if (!d.hasFocus() && d.visibilityState === 'visible') leave('Chuyển sang cửa sổ / ứng dụng khác'); }, 1500);
    });
    w.addEventListener('focus', function () { clearTimeout(blurT); S.unloadAt = 0; });

    d.addEventListener('keydown', function (e) {
      var k = String(e.key || '').toLowerCase(), mod = e.ctrlKey || e.metaKey;
      if (mod && (k === 'p' || k === 's')) { e.preventDefault(); if (k === 'p') sendEvent('print', 'Ctrl+P'); return; }
      if (mod && k === 'u') { e.preventDefault(); return; }
      if (k === 'f12' || (mod && e.shiftKey && (k === 'i' || k === 'j' || k === 'c'))) { e.preventDefault(); return; }
      if (mod && (k === 'c' || k === 'x' || k === 'a') && !inEditable(e.target)) { e.preventDefault(); return; }
      if (k === 'printscreen') sendEvent('copy', 'Phím PrintScreen');
    }, true);
    d.addEventListener('contextmenu', function (e) {
      if (inEditable(e.target)) return;
      e.preventDefault();
      if (Date.now() - lastCtx > 15000) { lastCtx = Date.now(); sendEvent('contextmenu', ''); }
    });
    d.addEventListener('copy', function (e) { if (!inEditable(e.target)) { e.preventDefault(); } });
    d.addEventListener('cut', function (e) { if (!inEditable(e.target)) e.preventDefault(); });
    d.addEventListener('dragstart', function (e) { if (!inEditable(e.target)) e.preventDefault(); });
    w.addEventListener('beforeprint', function () { sendEvent('print', 'Lệnh in của trình duyệt'); });
    var isEssay = function (t) { return t && t.classList && t.classList.contains('es-text'); };
    d.addEventListener('paste', function (e) {
      if (S.config.track_focus && isEssay(e.target)) { e.preventDefault(); TN.toast('Không được dán nội dung từ bên ngoài vào bài làm.', 'warning'); }
    });
    d.addEventListener('drop', function (e) { if (S.config.track_focus && isEssay(e.target)) e.preventDefault(); });
    d.addEventListener('fullscreenchange', function () {
      if (S.config.require_fullscreen && !d.fullscreenElement && S.started && !S.finishing && !S.submitted) {
        sendEvent('fullscreen_exit', '');
        fsGate();
      }
    });
    w.addEventListener('offline', function () { setOnline(false); S.events.push({ type: 'offline', detail: '' }); });
    w.addEventListener('online', function () { S.events.push({ type: 'online', detail: '' }); quickPing(); });
    w.addEventListener('beforeunload', function (e) {
      S.unloadAt = Date.now();
      if (S.submitted || !S.started) return;
      writeBackup();
      if (S.dirty) { saveKeepalive(); e.preventDefault(); e.returnValue = ''; }
    });
    w.addEventListener('pagehide', function () { if (!S.submitted && S.started) { writeBackup(); saveKeepalive(); } });
  }
  function fsGate() {
    if (d.fullscreenElement || !d.documentElement.requestFullscreen) { hideOverlay('fs'); return; }
    overlay('fs', {
      icon: 'maximize', title: 'Làm bài ở chế độ toàn màn hình',
      text: 'Bài thi này yêu cầu toàn màn hình. Bấm nút bên dưới để tiếp tục. Thoát toàn màn hình sẽ bị ghi nhận là rời khỏi bài thi.',
      buttons: [{ label: 'Vào toàn màn hình', icon: 'maximize', primary: true, fn: function () {
        d.documentElement.requestFullscreen().then(function () { hideOverlay('fs'); }, function () { TN.toast('Trình duyệt không cho phép toàn màn hình. Báo giám thị để được hỗ trợ.', 'error'); });
      } }]
    });
  }

  // ------------------------------------------------------------------ Nhịp tim: cập nhật giờ, tin nhắn, trạng thái tạm dừng
  function heartbeat() {
    clearTimeout(S.hbTimer);
    if (S.submitted || S.gone) return;
    var iv = (S.paused || S.locked) ? 5 : S.config.heartbeat;
    var rem = remainingSec();
    if (rem !== null && rem < 150) iv = Math.min(iv, 8);
    if (!S.online) iv = Math.min(iv, 6);
    S.hbTimer = setTimeout(function () {
      if (S.inactive || S.blocked || S.needLogin || S.finishing) { heartbeat(); return; }
      if (S.dirty && !S.inFlight) { doSave(); heartbeat(); return; }
      call(C.urls.ping + '&last_msg=' + S.lastMsg, { last_msg: S.lastMsg }, { timeout: 12000 }).then(function (r) { applyLive(r); flushEvents(); }, function () {}).then(heartbeat);
    }, iv * 1000);
  }
  function quickPing() {
    if (!S.started || S.submitted || S.inactive) return;
    if (S.dirty) { doSave(); return; }
    call(C.urls.ping + '&last_msg=' + S.lastMsg, { last_msg: S.lastMsg }, { timeout: 10000 }).then(function (r) { applyLive(r); flushEvents(); }, function () {});
  }

  // ------------------------------------------------------------------ Giao diện: phiếu, dãy câu hỏi, tiến độ
  function buildSheet(answers, flags) {
    S.sheet = new AnswerSheet('#sheet', {
      structure: C.structure, mode: 'exam', header: C.header, answers: answers, flags: flags,
      onChange: function (a, q) { onChange(); refreshChip(q); },
      onFlag: function (f, q) { onChange(); refreshChip(q); }
    });
    buildChips();
  }
  function buildChips() {
    var box = $('#qchips');
    if (!box) return;
    box.innerHTML = S.sheet.ids().map(function (q) {
      return '<button type="button" class="qchip" data-q="' + q + '" title="' + esc(S.sheet.label(q)) + '">' + esc(S.sheet.shortLabel(q)) + '</button>';
    }).join('');
    box.addEventListener('click', function (e) { var b = e.target.closest('[data-q]'); if (b) gotoQuestion(b.dataset.q); });
    S.sheet.ids().forEach(refreshChip);
    refreshProgress();
    var f = $('#q-filter');
    if (f) f.addEventListener('click', function (e) {
      var b = e.target.closest('[data-f]');
      if (!b) return;
      f.querySelectorAll('[data-f]').forEach(function (x) { x.classList.toggle('active', x === b); });
      box.classList.toggle('f-todo', b.dataset.f === 'todo');
      box.classList.toggle('f-flag', b.dataset.f === 'flag');
    });
  }
  function refreshChip(q) {
    var b = d.querySelector('#qchips [data-q="' + q + '"]');
    if (!b || !S.sheet) return;
    var v = S.sheet.value(q) || '';
    var done = S.sheet.isAnswered(q);
    b.classList.toggle('done', done);
    b.classList.toggle('partial', done && q.indexOf('p2.') === 0 && v.indexOf('_') >= 0);
    b.classList.toggle('flag', S.sheet.flags.indexOf(q) >= 0);
  }
  function refreshProgress() {
    if (!S.sheet) return;
    var st = S.sheet.stats();
    var pct = st.total ? Math.round(st.answered * 100 / st.total) : 0;
    var ring = $('#prog-ring');
    if (ring) { ring.style.setProperty('--p', pct); ring.setAttribute('data-label', pct + '%'); }
    var t = $('#prog-text');
    if (t) t.textContent = st.answered + '/' + st.total + ' câu';
    var todo = $('#cnt-todo'), fl = $('#cnt-flag');
    if (todo) todo.textContent = st.unanswered.length + st.partial.length;
    if (fl) fl.textContent = st.flagged.length;
  }
  function gotoQuestion(q) {
    var xr = $('#xr');
    if (xr && xr.dataset.pane === 'pdf' && w.innerWidth <= 900) setPane('sheet');
    var body = $('#xr-body');
    if (body && body.classList.contains('collapse-right')) body.classList.remove('collapse-right');
    S.sheet.focusQuestion(q);
    d.querySelectorAll('#qchips .qchip.cur').forEach(function (x) { x.classList.remove('cur'); });
    var b = d.querySelector('#qchips [data-q="' + q + '"]');
    if (b) b.classList.add('cur');
  }
  function setPane(p) {
    var xr = $('#xr');
    xr.dataset.pane = p;
    d.querySelectorAll('.xr-tabs [data-pane]').forEach(function (b) { b.classList.toggle('active', b.dataset.pane === p); });
  }

  function whenViewer(cb) {
    if (w.PdfViewer) { cb(); return; }
    var done = false;
    w.addEventListener('tn:pdfviewer', function () { if (!done) { done = true; cb(); } }, { once: true });
    setTimeout(function () {
      if (done || w.PdfViewer) return;
      var el = $('#pdf');
      if (el) el.innerHTML = '<div class="empty">' + '<div class="empty-icon">' + ic('triangle-alert') + '</div><h3>Không hiển thị được đề thi</h3><p>Trình duyệt quá cũ. Hãy dùng Chrome, Edge hoặc Firefox bản mới, hoặc báo giám thị.</p></div>';
    }, 12000);
  }
  function openPdf(p) {
    whenViewer(function () {
      if (!S.viewer) S.viewer = new w.PdfViewer($('#pdf'), { watermark: C.watermark, protect: true, fullscreenButton: !S.config.require_fullscreen });
      if (!p) {
        S.viewer.empty('Ca thi này dùng <b>đề giấy</b>: em làm bài trên đề được phát và tô đáp án vào phiếu trả lời bên phải.');
        return;
      }
      S.pdfOpened = true;
      S.viewer.open({ url: p.url, key: p.key, headers: { 'X-Requested-With': 'tnexam', 'X-Device-Token': S.token || '' } });
    });
  }

  // ------------------------------------------------------------------ Khởi động
  function start(r) {
    S.config = Object.assign(S.config, r.config || {});
    if (r.device_token) S.token = r.device_token;
    S.startedAt = r.started_at || S.startedAt;
    var b = readBackup();
    var serverSeq = r.seq || 0;
    var answers = r.answers, flags = r.flags || [];
    var useLocal = false;
    if (S.started && S.sheet) {
      useLocal = S.seq > serverSeq; // đã làm tiếp khi mất mạng từ bản dự phòng
    } else if (b && b.seq > serverSeq && b.answers) {
      useLocal = true;
      answers = b.answers;
      flags = b.flags || [];
    }
    if (b && b.seen) S.seenMsg = Math.max(S.seenMsg, b.seen);
    S.seq = Math.max(serverSeq, S.seq, b ? (b.seq || 0) : 0);
    S.ackSeq = Math.max(S.ackSeq, serverSeq);
    S.dirty = useLocal && S.seq > S.ackSeq;
    if (!S.sheet) buildSheet(answers, flags);
    else if (!useLocal) { S.sheet.setAnswers(answers); S.sheet.flags = (flags || []).slice(); S.sheet.refreshAll(); S.sheet.ids().forEach(refreshChip); }
    refreshProgress();
    var first = !S.online2;
    S.online2 = true; // đã nhận trạng thái từ máy chủ ít nhất một lần
    S.started = true;
    applyLive(r);
    if (!S.pdfOpened) openPdf(r.pdf);
    hideBoot();
    if (!S.guards) { S.guards = true; setupGuards(); S.tickTimer = setInterval(tick, 500); }
    if (first) {
      var rem = remainingSec();
      THRESH.forEach(function (t) { if (rem !== null && rem <= t[0]) S.warned[t[0]] = true; });
      heartbeat();
      announce();
      if (S.config.require_fullscreen) fsGate();
      if (useLocal) TN.toast('Đã khôi phục các câu trả lời chưa kịp gửi ở lần trước.', 'info', 'Khôi phục bài làm');
      else if (serverSeq > 0) TN.toast('Em đã vào lại bài thi. Bài làm trước đó được giữ nguyên.', 'success');
    }
    if (S.dirty) { chip('pending'); scheduleSave(150); } else chip('saved');
    writeBackup();
    tick();
  }

  /** Bắt đầu từ bản dự phòng khi không kết nối được máy chủ lúc tải trang. */
  function startOffline(b) {
    S.deadline = b.deadline || 0;
    S.seq = b.seq || 0;
    S.ackSeq = b.ack || 0;
    S.dirty = S.seq > S.ackSeq;
    S.seenMsg = b.seen || 0;
    if (b.tk && !S.token) S.token = b.tk;
    buildSheet(b.answers, b.flags || []);
    S.started = true;
    hideBoot();
    if (!S.guards) { S.guards = true; setupGuards(); S.tickTimer = setInterval(tick, 500); }
    openPdf(null);
    if (S.viewer) S.viewer.empty('Đang chờ kết nối máy chủ để tải đề thi…');
    TN.toast('Chưa kết nối được máy chủ. Em có thể tiếp tục tô phiếu, hệ thống sẽ tự đồng bộ khi có mạng.', 'warning', 'Đang làm bài ngoại tuyến', 12000);
    chip('offline');
    tick();
  }

  function reloadState(force) {
    call(C.urls.state + '&last_msg=' + S.lastMsg).then(function (r) {
      if (force && r.seq >= S.ackSeq && !S.dirty) {
        S.sheet.setAnswers(r.answers);
        S.sheet.flags = (r.flags || []).slice();
        S.sheet.refreshAll();
        S.sheet.ids().forEach(refreshChip);
      }
      start(r);
    }, function () {});
  }

  function boot() {
    if (S.gone) return;
    bootMsg('Đang kết nối máy chủ…');
    call(C.urls.state + '&last_msg=0').then(function (r) {
      S.bootDelay = 0;
      start(r);
    }, function (err) {
      if ([401, 404, 409, 419, 423].indexOf(err.status) >= 0) return; // đã có lớp phủ xử lý riêng
      var b = readBackup();
      if (!S.started && b && b.answers && !b.closed && (err.network || err.status >= 500)) startOffline(b);
      S.bootDelay = Math.min(10000, S.bootDelay ? S.bootDelay * 2 : 1500);
      bootMsg((err.network ? 'Chưa kết nối được máy chủ.' : err.message) + ' Tự thử lại sau ' + Math.round(S.bootDelay / 1000) + ' giây…');
      setTimeout(boot, S.bootDelay);
    });
  }

  TN.ready(function () {
    cleanOldBackups();
    var bk = readBackup();
    if (!S.token && bk && bk.tk) S.token = bk.tk;
    // Bố cục 2 cột kéo thả + tab trên điện thoại
    if (w.TNSplit) TNSplit('#xr-body', { key: 'tn-split-exam', def: 56, min: 22, max: 80 });
    d.querySelectorAll('.xr-tabs [data-pane]').forEach(function (b) { b.addEventListener('click', function () { setPane(b.dataset.pane); }); });
    d.querySelectorAll('#btn-submit, [data-submit]').forEach(function (b) { b.addEventListener('click', confirmSubmit); });
    var bm = $('#btn-msg'); if (bm) bm.addEventListener('click', showMessages);
    var bf = $('#btn-full'); if (bf) bf.addEventListener('click', function () {
      if (d.fullscreenElement) d.exitFullscreen(); else if (d.documentElement.requestFullscreen) d.documentElement.requestFullscreen().catch(function () {});
    });
    var bh = $('#btn-help'); if (bh) bh.addEventListener('click', function () {
      TN.modal({
        title: 'Hướng dẫn làm bài', icon: 'keyboard', size: 'lg',
        body: '<ul class="rule-list">' +
          '<li>' + ic('mouse-pointer-click') + '<div><b>Tô đáp án</b><small>Bấm vào ô tròn để chọn, bấm lần nữa để bỏ chọn. Phần I: phím <kbd>A</kbd> <kbd>B</kbd> <kbd>C</kbd> <kbd>D</kbd> (hoặc <kbd>1</kbd>–<kbd>4</kbd>), <kbd>↑</kbd> <kbd>↓</kbd> để chuyển câu.</small></div></li>' +
          '<li>' + ic('hash') + '<div><b>Phần III – trả lời ngắn</b><small>Tô từ trái sang phải hoặc bấm vào dãy ô kết quả để gõ trực tiếp (ví dụ <code>-1,5</code>). Dấu "−" chỉ ở cột đầu, dấu phẩy ở cột 2 hoặc 3.</small></div></li>' +
          '<li>' + ic('flag') + '<div><b>Đánh dấu xem lại</b><small>Bấm biểu tượng cờ hoặc phím <kbd>F</kbd> ở câu đang chọn. Dùng bộ lọc "Chưa làm" / cờ ở đầu phiếu để rà soát.</small></div></li>' +
          '<li>' + ic('move-horizontal') + '<div><b>Đổi độ rộng 2 cột</b><small>Kéo thanh chia ở giữa (hoặc phím <kbd>←</kbd> <kbd>→</kbd>), bấm đúp để về mặc định. Phóng to đề: <kbd>Ctrl</kbd> + lăn chuột trên đề.</small></div></li>' +
          '<li class="ok">' + ic('cloud-upload') + '<div><b>Lưu tự động</b><small>Mỗi lần tô đều được lưu. Biểu tượng góc trên cho biết trạng thái: "Đã lưu", "Đang lưu", "Mất mạng · đã lưu trên máy".</small></div></li>' +
          '<li class="warn">' + ic('triangle-alert') + '<div><b>Khi có sự cố</b><small>Mất mạng: cứ làm tiếp. Máy treo/mất điện: đăng nhập lại, vào lại bài thi – bài đã làm vẫn còn. Nếu báo "đang làm trên máy khác": báo giám thị mở khóa.</small></div></li>' +
          '</ul>',
        foot: '<button class="btn btn-primary" data-close>Đã hiểu</button>'
      });
    });
    boot();
  });
})(window, document);

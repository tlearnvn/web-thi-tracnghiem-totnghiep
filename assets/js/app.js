/* =====================================================================
   TN – thư viện giao diện dùng chung (không phụ thuộc framework)
   ===================================================================== */
(function (w, d) {
  'use strict';
  var TN = w.TN = w.TN || {};
  var meta = function (n) { var m = d.querySelector('meta[name="' + n + '"]'); return m ? m.getAttribute('content') : ''; };
  TN.csrf = meta('csrf-token');
  TN.base = meta('base-uri') || './';
  var st = parseInt(meta('server-time'), 10);
  TN.serverOffset = st ? st * 1000 - Date.now() : 0;
  TN.now = function () { return Date.now() + TN.serverOffset; };
  TN.icon = function (n, c) { return w.TNIcon ? w.TNIcon(n, c) : ''; };

  // ------------------------------------------------------------ Định dạng (UTC+7)
  var TZ = 'Asia/Ho_Chi_Minh';
  var fmtCache = {};
  function fmt(opts) {
    var k = JSON.stringify(opts);
    if (!fmtCache[k]) {
      try { fmtCache[k] = new Intl.DateTimeFormat('vi-VN', Object.assign({ timeZone: TZ }, opts)); }
      catch (e) { fmtCache[k] = new Intl.DateTimeFormat('vi-VN', opts); }
    }
    return fmtCache[k];
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  TN.parts = function (ms) {
    var o = {};
    fmt({ year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
      .formatToParts(new Date(ms)).forEach(function (p) { o[p.type] = p.value; });
    if (o.hour === '24') o.hour = '00';
    return o;
  };
  TN.fmtDateTime = function (ts, withSec) {
    if (!ts) return '';
    var p = TN.parts(ts * 1000);
    return p.day + '/' + p.month + '/' + p.year + ' ' + p.hour + ':' + p.minute + (withSec ? ':' + p.second : '');
  };
  TN.fmtTime = function (ts, withSec) {
    if (!ts) return '';
    var p = TN.parts(ts * 1000);
    return p.hour + ':' + p.minute + (withSec ? ':' + p.second : '');
  };
  TN.fmtDuration = function (sec) {
    sec = Math.max(0, Math.floor(sec));
    var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
    return (h > 0 ? h + ':' + pad(m) : pad(m)) + ':' + pad(s);
  };
  TN.fmtNum = function (n, dec) {
    if (n === null || n === undefined || n === '') return '';
    dec = dec === undefined ? 2 : dec;
    var s = Number(n).toFixed(dec);
    if (dec > 0) s = s.replace(/\.?0+$/, '');
    var parts = s.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return parts.join(',');
  };
  TN.fmtScore = function (n) { return n === null || n === undefined ? '–' : Number(n).toFixed(2).replace('.', ','); };
  TN.ago = function (ts) {
    if (!ts) return '';
    var dsec = Math.floor(TN.now() / 1000) - ts;
    if (dsec < 10) return 'vừa xong';
    if (dsec < 60) return dsec + ' giây trước';
    if (dsec < 3600) return Math.floor(dsec / 60) + ' phút trước';
    if (dsec < 86400) return Math.floor(dsec / 3600) + ' giờ trước';
    return Math.floor(dsec / 86400) + ' ngày trước';
  };
  TN.esc = function (s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  /** Bỏ dấu tiếng Việt (tìm kiếm không phân biệt dấu). */
  TN.unaccent = function (s) {
    s = String(s === null || s === undefined ? '' : s);
    return (s.normalize ? s.normalize('NFD') : s).replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D');
  };
  TN.url = function (route, params) {
    var q = [];
    if (route) q.push('r=' + encodeURIComponent(route));
    Object.keys(params || {}).forEach(function (k) {
      if (params[k] !== null && params[k] !== undefined && params[k] !== '') q.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
    });
    return TN.base + 'index.php' + (q.length ? '?' + q.join('&') : '');
  };
  TN.debounce = function (fn, ms) {
    var t; return function () { var a = arguments, s = this; clearTimeout(t); t = setTimeout(function () { fn.apply(s, a); }, ms); };
  };

  // ------------------------------------------------------------ Gọi API
  TN.api = function (url, opts) {
    opts = opts || {};
    var headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-Token': TN.csrf };
    var body;
    if (opts.form) { body = opts.form instanceof FormData ? opts.form : new FormData(opts.form); }
    else if (opts.data !== undefined) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(opts.data); }
    else if (opts.raw !== undefined) { headers['Content-Type'] = 'application/octet-stream'; body = opts.raw; }
    Object.assign(headers, opts.headers || {});
    var ctrl = w.AbortController ? new AbortController() : null;
    var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, opts.timeout || 60000) : null;
    return fetch(url, {
      method: opts.method || (body !== undefined ? 'POST' : 'GET'),
      headers: headers, body: body, credentials: 'same-origin', cache: 'no-store',
      signal: ctrl ? ctrl.signal : undefined, keepalive: !!opts.keepalive
    }).then(function (res) {
      if (timer) clearTimeout(timer);
      return res.text().then(function (txt) {
        var json = null;
        try { json = txt ? JSON.parse(txt) : {}; } catch (e) { json = { ok: false, error: 'Máy chủ trả về dữ liệu không hợp lệ (HTTP ' + res.status + ').' }; }
        json = json || {};
        json._status = res.status;
        if (!res.ok || json.ok === false) {
          var err = new Error(json.error || ('Lỗi máy chủ (HTTP ' + res.status + ')'));
          err.status = res.status; err.data = json;
          throw err;
        }
        return json;
      });
    }, function (e) {
      if (timer) clearTimeout(timer);
      var err = new Error(e && e.name === 'AbortError' ? 'Hết thời gian chờ máy chủ phản hồi.' : 'Không kết nối được máy chủ. Kiểm tra mạng và thử lại.');
      err.status = 0; err.network = true;
      throw err;
    });
  };

  // ------------------------------------------------------------ Thông báo nhanh
  var toastBox;
  TN.toast = function (msg, type, title, timeout) {
    if (!toastBox) { toastBox = d.createElement('div'); toastBox.className = 'toasts'; toastBox.setAttribute('aria-live', 'polite'); d.body.appendChild(toastBox); }
    type = type || 'info';
    var icons = { success: 'circle-check', error: 'circle-x', warning: 'triangle-alert', info: 'info' };
    var el = d.createElement('div');
    el.className = 'toast ' + type;
    el.innerHTML = TN.icon(icons[type] || 'info') + '<div class="t-body">' + (title ? '<div class="t-title">' + TN.esc(title) + '</div>' : '') +
      '<div>' + TN.esc(msg) + '</div></div><button class="t-close" aria-label="Đóng">' + TN.icon('x', 'sm') + '</button>';
    toastBox.appendChild(el);
    var close = function () { el.classList.add('out'); setTimeout(function () { el.remove(); }, 260); };
    el.querySelector('.t-close').onclick = close;
    if (timeout !== 0) setTimeout(close, timeout || (type === 'error' ? 7000 : 4200));
    return close;
  };

  // ------------------------------------------------------------ Hộp thoại
  TN.modal = function (o) {
    var bd = d.createElement('div');
    bd.className = 'modal-backdrop';
    bd.innerHTML = '<div class="modal ' + (o.size || '') + '" role="dialog" aria-modal="true">' +
      '<div class="modal-head">' + (o.icon ? '<div class="modal-icon ' + (o.iconClass || '') + '">' + TN.icon(o.icon) + '</div>' : '') +
      '<h3>' + TN.esc(o.title || '') + '</h3><button class="icon-btn" data-close aria-label="Đóng">' + TN.icon('x') + '</button></div>' +
      '<div class="modal-body"></div>' + (o.foot ? '<div class="modal-foot"></div>' : '') + '</div>';
    var body = bd.querySelector('.modal-body');
    if (typeof o.body === 'string') body.innerHTML = o.body; else if (o.body) body.appendChild(o.body);
    var foot = bd.querySelector('.modal-foot');
    if (foot && o.foot) { if (typeof o.foot === 'string') foot.innerHTML = o.foot; else foot.appendChild(o.foot); }
    d.body.appendChild(bd);
    d.body.style.overflow = 'hidden';
    var api = {
      el: bd, body: body, foot: foot,
      close: function () {
        if (!bd.parentNode) return;
        bd.remove();
        if (!d.querySelector('.modal-backdrop')) d.body.style.overflow = '';
        if (o.onClose) o.onClose();
        d.removeEventListener('keydown', onKey);
      }
    };
    function onKey(e) { if (e.key === 'Escape' && o.dismissible !== false) api.close(); }
    d.addEventListener('keydown', onKey);
    bd.addEventListener('mousedown', function (e) { if (e.target === bd && o.dismissible !== false) api.close(); });
    bd.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', api.close); });
    if (o.dismissible === false) bd.querySelectorAll('.modal-head [data-close]').forEach(function (b) { b.remove(); });
    if (o.onOpen) o.onOpen(api);
    var f = bd.querySelector('[autofocus], .modal-body input:not([type=hidden]), .modal-foot .btn-primary');
    if (f) setTimeout(function () { f.focus(); }, 30);
    return api;
  };

  TN.confirm = function (o) {
    if (typeof o === 'string') o = { message: o };
    return new Promise(function (resolve) {
      var done = false;
      var footer = d.createElement('div');
      footer.style.display = 'contents';
      footer.innerHTML = '<button class="btn btn-ghost" data-close>' + TN.esc(o.cancel || 'Hủy') + '</button>' +
        '<button class="btn ' + (o.danger ? 'btn-danger' : 'btn-primary') + '" data-ok>' + TN.esc(o.ok || 'Đồng ý') + '</button>';
      var body = '<div class="text-muted">' + (o.html || TN.esc(o.message || 'Bạn có chắc chắn?')).replace(/\n/g, '<br>') + '</div>';
      if (o.input) {
        body += '<div class="field mt-2">' + (o.input.label ? '<label>' + TN.esc(o.input.label) + '</label>' : '') +
          '<input class="input" data-input type="' + (o.input.type || 'text') + '" value="' + TN.esc(o.input.value || '') + '" placeholder="' + TN.esc(o.input.placeholder || '') + '"></div>';
      }
      var m = TN.modal({
        title: o.title || 'Xác nhận', icon: o.icon || (o.danger ? 'triangle-alert' : 'circle-help'), iconClass: o.danger ? 'danger' : (o.iconClass || ''),
        body: body, foot: footer, size: 'sm', onClose: function () { if (!done) resolve(false); }
      });
      var ok = m.el.querySelector('[data-ok]');
      var inp = m.el.querySelector('[data-input]');
      if (o.delay) {
        var left = o.delay, label = ok.textContent;
        ok.disabled = true; ok.textContent = label + ' (' + left + ')';
        var iv = setInterval(function () { left--; if (left <= 0) { clearInterval(iv); ok.disabled = false; ok.textContent = label; } else ok.textContent = label + ' (' + left + ')'; }, 1000);
      }
      ok.addEventListener('click', function () { done = true; var v = inp ? inp.value : true; m.close(); resolve(v); });
      if (inp) { inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') ok.click(); }); setTimeout(function () { inp.focus(); inp.select(); }, 40); }
    });
  };

  TN.drawer = function (o) {
    var bd = d.createElement('div'); bd.className = 'drawer-backdrop';
    var dr = d.createElement('aside'); dr.className = 'drawer';
    dr.innerHTML = '<div class="drawer-head"><h3>' + TN.esc(o.title || '') + '</h3><button class="icon-btn" data-close>' + TN.icon('x') + '</button></div><div class="drawer-body"></div>';
    var body = dr.querySelector('.drawer-body');
    if (typeof o.body === 'string') body.innerHTML = o.body; else if (o.body) body.appendChild(o.body);
    d.body.appendChild(bd); d.body.appendChild(dr);
    var api = { el: dr, body: body, close: function () { bd.remove(); dr.remove(); if (o.onClose) o.onClose(); } };
    bd.onclick = api.close; dr.querySelector('[data-close]').onclick = api.close;
    return api;
  };

  // ------------------------------------------------------------ Gửi POST từ nút bấm
  TN.post = function (url, fields) {
    var f = d.createElement('form');
    f.method = 'post'; f.action = url; f.style.display = 'none';
    fields = Object.assign({ _token: TN.csrf }, fields || {});
    Object.keys(fields).forEach(function (k) {
      [].concat(fields[k]).forEach(function (v) {
        var i = d.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i);
      });
    });
    d.body.appendChild(f); f.submit();
  };

  TN.busy = function (btn, on) {
    if (!btn) return;
    if (on) { btn.classList.add('loading'); btn.dataset.html = btn.innerHTML; btn.innerHTML = '<span class="spinner"></span>' + (btn.dataset.busy || btn.textContent.trim()); btn.disabled = true; }
    else { btn.classList.remove('loading'); if (btn.dataset.html) btn.innerHTML = btn.dataset.html; btn.disabled = false; }
  };

  // ------------------------------------------------------------ Tải tệp lên theo từng khúc (vượt giới hạn upload của hosting)
  TN.upload = function (file, o) {
    o = o || {};
    var CH = 512 * 1024;
    var total = Math.ceil(file.size / CH) || 1;
    var fileId = null;
    function step(p) { if (o.onProgress) o.onProgress(p); }
    function sendChunk(i, tries) {
      var blob = file.slice(i * CH, Math.min(file.size, (i + 1) * CH));
      return TN.api(TN.url('files/upload-chunk', { id: fileId, seq: i }), { raw: blob, timeout: 120000 }).catch(function (e) {
        if (tries < 4) return new Promise(function (r) { setTimeout(r, 800 * (tries + 1)); }).then(function () { return sendChunk(i, tries + 1); });
        throw e;
      });
    }
    return TN.api(TN.url('files/upload-init'), { data: { name: file.name, size: file.size, mime: file.type || 'application/octet-stream', purpose: o.purpose || 'attachment' } })
      .then(function (r) {
        fileId = r.id;
        var i = 0;
        function next() {
          if (i >= total) return Promise.resolve();
          return sendChunk(i, 0).then(function () { i++; step(i / total); return next(); });
        }
        return next();
      })
      .then(function () { return TN.api(TN.url('files/upload-finish'), { data: Object.assign({ id: fileId }, o.attach || {}) }); });
  };

  // ------------------------------------------------------------ Khởi tạo khi tải trang
  function onReady(fn) { if (d.readyState !== 'loading') fn(); else d.addEventListener('DOMContentLoaded', fn); }
  TN.ready = onReady;

  onReady(function () {
    // Thông báo flash từ máy chủ
    (w.TN_FLASH || []).forEach(function (f) { TN.toast(f.message, f.type === 'danger' ? 'error' : f.type); });

    // Menu bên (điện thoại)
    d.querySelectorAll('[data-toggle-nav]').forEach(function (b) { b.addEventListener('click', function () { d.body.classList.toggle('nav-open'); }); });
    var bd = d.querySelector('.sidebar-backdrop');
    if (bd) bd.addEventListener('click', function () { d.body.classList.remove('nav-open'); });

    // Giao diện sáng / tối
    d.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
      b.addEventListener('click', function () {
        var cur = d.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        d.documentElement.setAttribute('data-theme', cur);
        try { localStorage.setItem('tn-theme', cur); } catch (e) {}
        d.dispatchEvent(new CustomEvent('tn:theme', { detail: cur }));
      });
    });

    // Đồng hồ giờ Việt Nam (theo giờ máy chủ)
    var clock = d.getElementById('tn-clock');
    if (clock) {
      var tick = function () {
        var p = TN.parts(TN.now());
        clock.innerHTML = TN.icon('clock', 'sm') + '<strong>' + p.hour + ':' + p.minute + ':' + p.second + '</strong><span>' + p.day + '/' + p.month + '/' + p.year + '</span>';
      };
      tick(); setInterval(tick, 1000);
    }

    // Menu thả xuống (menu trong bảng cuộn dùng vị trí cố định để không bị cắt)
    function closeMenus(except) {
      d.querySelectorAll('.dropdown.open').forEach(function (dd) { if (dd !== except) dd.classList.remove('open'); });
    }
    d.addEventListener('click', function (e) {
      var t = e.target.closest('[data-dropdown]');
      if (!t && e.target.closest('.dropdown-menu') && !e.target.closest('a,button')) return;
      closeMenus(t ? t.closest('.dropdown') : null);
      if (!t) return;
      e.preventDefault();
      var dd = t.closest('.dropdown');
      var open = !dd.classList.contains('open');
      dd.classList.toggle('open', open);
      var menu = dd.querySelector('.dropdown-menu');
      if (open && menu && (dd.closest('.table-wrap') || dd.hasAttribute('data-fixed'))) {
        menu.style.position = 'fixed'; menu.style.right = 'auto';
        var r = t.getBoundingClientRect(), mh = menu.offsetHeight, mw = menu.offsetWidth;
        var top = r.bottom + 6;
        if (top + mh > w.innerHeight - 8) top = Math.max(8, r.top - mh - 6);
        var left = Math.max(8, Math.min(r.right - mw, w.innerWidth - mw - 8));
        menu.style.top = top + 'px'; menu.style.left = left + 'px';
      }
    });
    w.addEventListener('scroll', function (e) {
      if (e.target && e.target.closest && e.target.closest('.dropdown-menu')) return;
      d.querySelectorAll('.dropdown.open .dropdown-menu').forEach(function (m) { if (m.style.position === 'fixed') m.closest('.dropdown').classList.remove('open'); });
    }, true);
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenus(null); });

    // Tab
    d.querySelectorAll('[data-tabs]').forEach(function (box) {
      var tabs = box.querySelectorAll('.tab[data-tab]');
      var scope = box.closest('[data-tab-scope]') || d;
      function show(name, push) {
        var found = false;
        tabs.forEach(function (t) { var on = t.dataset.tab === name; t.classList.toggle('active', on); if (on) found = true; });
        if (!found) return false;
        scope.querySelectorAll('.tab-panel[data-panel]').forEach(function (p) { p.classList.toggle('active', p.dataset.panel === name); });
        if (push && box.hasAttribute('data-tabs-hash')) history.replaceState(null, '', '#' + name);
        d.dispatchEvent(new CustomEvent('tn:tab', { detail: name }));
        return true;
      }
      tabs.forEach(function (t) { t.addEventListener('click', function (e) { e.preventDefault(); show(t.dataset.tab, true); }); });
      var h = location.hash.replace('#', '');
      if (!(h && show(h, false)) && tabs.length) {
        var act = box.querySelector('.tab.active') || tabs[0];
        show(act.dataset.tab, false);
      }
    });

    // Nút cần xác nhận / gửi POST
    d.addEventListener('click', function (e) {
      var el = e.target.closest('[data-post], [data-confirm]:not(form)');
      if (!el || el.tagName === 'FORM') return;
      if (el.matches('button[type=submit], input[type=submit]') && !el.dataset.post) {
        // nút submit trong form có data-confirm
        e.preventDefault();
        TN.confirm({ message: el.dataset.confirm, danger: el.hasAttribute('data-danger'), ok: el.dataset.ok }).then(function (ok) {
          if (ok) { var f = el.form; if (el.name) { var h = d.createElement('input'); h.type = 'hidden'; h.name = el.name; h.value = el.value; f.appendChild(h); } f.submit(); }
        });
        return;
      }
      if (!el.dataset.post) return;
      e.preventDefault();
      var fields = {};
      try { fields = el.dataset.fields ? JSON.parse(el.dataset.fields) : {}; } catch (x) {}
      var go = function () { TN.post(el.dataset.post, fields); };
      if (el.dataset.confirm) {
        TN.confirm({ message: el.dataset.confirm, title: el.dataset.title, danger: el.hasAttribute('data-danger'), ok: el.dataset.ok }).then(function (ok) { if (ok) go(); });
      } else go();
    });
    d.querySelectorAll('form[data-confirm]').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        if (f.dataset.confirmed) return;
        e.preventDefault();
        TN.confirm({ message: f.dataset.confirm, danger: f.hasAttribute('data-danger') }).then(function (ok) { if (ok) { f.dataset.confirmed = '1'; f.submit(); } });
      });
    });

    // Form AJAX
    d.querySelectorAll('form[data-ajax]').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = f.querySelector('[type=submit]');
        TN.busy(btn, true);
        TN.api(f.action, { form: f }).then(function (r) {
          TN.busy(btn, false);
          if (r.message) TN.toast(r.message, 'success');
          if (r.redirect) location.href = r.redirect; else if (r.reload) location.reload();
          f.dispatchEvent(new CustomEvent('tn:done', { detail: r }));
        }).catch(function (err) { TN.busy(btn, false); TN.toast(err.message, 'error'); });
      });
    });

    // Chọn tất cả trong bảng
    d.querySelectorAll('[data-check-all]').forEach(function (all) {
      var table = all.closest('table') || d;
      var bar = d.querySelector(all.dataset.checkAll || '.bulk-bar');
      var boxes = function () { return table.querySelectorAll('input[data-check]'); };
      var sync = function () {
        var n = 0; boxes().forEach(function (b) { if (b.checked) n++; b.closest('tr') && b.closest('tr').classList.toggle('is-selected', b.checked); });
        all.checked = n > 0 && n === boxes().length; all.indeterminate = n > 0 && n < boxes().length;
        if (bar) { bar.classList.toggle('show', n > 0); var c = bar.querySelector('[data-count]'); if (c) c.textContent = n; }
      };
      all.addEventListener('change', function () { boxes().forEach(function (b) { b.checked = all.checked; }); sync(); });
      table.addEventListener('change', function (e) { if (e.target.matches('input[data-check]')) sync(); });
      sync();
    });
    TN.checkedIds = function (scope) {
      return [].map.call((scope || d).querySelectorAll('input[data-check]:checked'), function (b) { return b.value; });
    };

    // Hiện / ẩn mật khẩu
    d.querySelectorAll('.toggle-pw').forEach(function (b) {
      b.addEventListener('click', function () {
        var i = b.parentNode.querySelector('input');
        i.type = i.type === 'password' ? 'text' : 'password';
        b.innerHTML = TN.icon(i.type === 'password' ? 'eye' : 'eye-off');
      });
    });

    // Ô chọn tệp dạng kéo-thả
    d.querySelectorAll('.dropzone').forEach(function (z) {
      var input = z.querySelector('input[type=file]');
      if (!input) return;
      ['dragenter', 'dragover'].forEach(function (ev) { z.addEventListener(ev, function () { z.classList.add('drag'); }); });
      ['dragleave', 'drop'].forEach(function (ev) { z.addEventListener(ev, function () { z.classList.remove('drag'); }); });
      input.addEventListener('change', function () {
        var info = z.querySelector('.dz-file');
        if (!info) { info = d.createElement('div'); info.className = 'dz-file'; z.appendChild(info); }
        var f = input.files[0];
        info.innerHTML = f ? TN.icon('file-text', 'sm') + TN.esc(f.name) + ' <span class="text-muted">(' + TN.fmtNum(f.size / 1024, 0) + ' KB)</span>' : '';
      });
    });

    // Tự gửi form khi đổi bộ lọc
    d.querySelectorAll('[data-autosubmit]').forEach(function (el) {
      if (el.tagName === 'INPUT' && (el.type === 'search' || el.type === 'text')) {
        el.addEventListener('input', TN.debounce(function () { el.form.submit(); }, 600));
      } else el.addEventListener('change', function () { el.form.submit(); });
    });

    // Sao chép
    d.addEventListener('click', function (e) {
      var el = e.target.closest('[data-copy]');
      if (!el) return;
      e.preventDefault();
      var text = el.dataset.copy;
      var ok = function () { TN.toast('Đã sao chép vào bộ nhớ tạm', 'success'); };
      if (navigator.clipboard && w.isSecureContext) navigator.clipboard.writeText(text).then(ok);
      else { var t = d.createElement('textarea'); t.value = text; d.body.appendChild(t); t.select(); try { d.execCommand('copy'); ok(); } catch (x) {} t.remove(); }
    });

    // Số chạy (ô thống kê)
    d.querySelectorAll('[data-count-to]').forEach(function (el) {
      var target = parseFloat(el.dataset.countTo), dec = parseInt(el.dataset.dec || '0', 10), t0 = null;
      if (!isFinite(target)) return;
      var stepFn = function (ts) {
        if (!t0) t0 = ts;
        var p = Math.min(1, (ts - t0) / 900), e2 = 1 - Math.pow(1 - p, 3);
        el.textContent = TN.fmtNum(target * e2, dec);
        if (p < 1) requestAnimationFrame(stepFn); else el.textContent = TN.fmtNum(target, dec);
      };
      requestAnimationFrame(stepFn);
    });

    // Hiển thị giờ Việt Nam cho các phần tử có data-ts
    d.querySelectorAll('[data-ts]').forEach(function (el) {
      var ts = parseInt(el.dataset.ts, 10);
      if (ts) el.textContent = el.dataset.fmt === 'ago' ? TN.ago(ts) : TN.fmtDateTime(ts);
    });

    // Đếm ngược
    var cds = d.querySelectorAll('[data-countdown]');
    if (cds.length) {
      var tickCd = function () {
        var now = Math.floor(TN.now() / 1000);
        cds.forEach(function (el) {
          var left = parseInt(el.dataset.countdown, 10) - now;
          if (left <= 0) { el.textContent = el.dataset.done || '00:00'; if (el.dataset.reload && !el.dataset.fired) { el.dataset.fired = 1; setTimeout(function () { location.reload(); }, 1200); } return; }
          var dd = Math.floor(left / 86400);
          el.textContent = (dd > 0 ? dd + ' ngày ' : '') + TN.fmtDuration(left % 86400);
        });
      };
      tickCd(); setInterval(tickCd, 1000);
    }
  });
})(window, document);

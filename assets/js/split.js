/* Thanh chia 2 cột kéo thả mượt (pointer events + requestAnimationFrame), nhớ vị trí, thu gọn từng bên */
(function (w, d) {
  'use strict';
  w.TNSplit = function (body, o) {
    body = typeof body === 'string' ? d.querySelector(body) : body;
    if (!body) return null;
    o = Object.assign({ key: 'tn-split', def: 56, min: 20, max: 80 }, o || {});
    var bar = body.querySelector('.xr-split');
    var saved = null;
    try { saved = parseFloat(localStorage.getItem(o.key)); } catch (e) {}
    var cur = saved && isFinite(saved) ? saved : o.def;
    function apply(v, save) {
      cur = Math.max(o.min, Math.min(o.max, v));
      body.style.setProperty('--xr-left', cur.toFixed(2) + '%');
      if (save) { try { localStorage.setItem(o.key, cur.toFixed(1)); } catch (e) {} }
      if (o.onChange) o.onChange(cur);
    }
    apply(cur);
    if (!bar) return null;
    bar.setAttribute('aria-valuenow', Math.round(cur));
    var dragging = false, raf = 0, lastX = 0;
    bar.addEventListener('pointerdown', function (e) {
      if (e.target.closest('button')) return;
      dragging = true;
      body.classList.remove('collapse-left', 'collapse-right');
      bar.setPointerCapture && bar.setPointerCapture(e.pointerId);
      body.classList.add('dragging');
      e.preventDefault();
    });
    bar.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      lastX = e.clientX;
      if (!raf) raf = requestAnimationFrame(function () {
        raf = 0;
        var r = body.getBoundingClientRect();
        apply(((lastX - r.left) / r.width) * 100);
      });
    });
    function end() {
      if (!dragging) return;
      dragging = false;
      body.classList.remove('dragging');
      apply(cur, true);
      bar.setAttribute('aria-valuenow', Math.round(cur));
    }
    bar.addEventListener('pointerup', end);
    bar.addEventListener('pointercancel', end);
    bar.addEventListener('lostpointercapture', end);
    bar.addEventListener('dblclick', function (e) { if (!e.target.closest('button')) apply(o.def, true); });
    bar.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { e.preventDefault(); apply(cur - 2, true); }
      if (e.key === 'ArrowRight') { e.preventDefault(); apply(cur + 2, true); }
      if (e.key === 'Home') { e.preventDefault(); apply(o.def, true); }
    });
    bar.querySelectorAll('[data-collapse]').forEach(function (b) {
      b.addEventListener('click', function () {
        var side = b.dataset.collapse, cls = 'collapse-' + side, other = 'collapse-' + (side === 'left' ? 'right' : 'left');
        if (body.classList.contains(other)) { body.classList.remove(other); return; }
        body.classList.toggle(cls);
      });
    });
    return { set: function (v) { apply(v, true); }, get: function () { return cur; } };
  };
})(window, document);

/* =====================================================================
   Trình xem đề PDF (PDF.js) – hiển thị bằng canvas, không có nút tải/in,
   có hình mờ (watermark) tên thí sinh, tải lười từng trang, tự vừa khung khi kéo cột.
   ===================================================================== */
import * as pdfjsLib from '../vendor/pdfjs/pdf.min.js';

const ASSETS = new URL('../', import.meta.url);
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('vendor/pdfjs/pdf.worker.min.js', ASSETS).href;

const ZOOMS = [0.5, 0.67, 0.75, 0.9, 1, 1.1, 1.25, 1.5, 1.75, 2, 2.5, 3];
const ic = (n, c) => (window.TNIcon ? window.TNIcon(n, c) : '');
const ls = (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } };

function b64ToBytes(b64) {
  const bin = atob(b64);
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out;
}

export class PdfViewer {
  constructor(root, opts = {}) {
    this.root = typeof root === 'string' ? document.querySelector(root) : root;
    this.opts = Object.assign({ watermark: '', protect: true, fullscreenButton: true, invertButton: true, keepPages: 3 }, opts);
    this.pages = [];
    this.scale = 1;
    this.mode = ls('tn-pdf-zoom') || 'fit';
    this.rotation = 0;
    this.doc = null;
    this.current = 1;
    this.renderSeq = 0;
    this.build();
  }

  build() {
    const r = this.root;
    r.classList.add('pdfv');
    r.innerHTML = `
      <div class="pdfv-bar">
        <div class="pdfv-grp">
          <button type="button" class="pdfv-btn" data-act="prev" title="Trang trước">${ic('chevron-left')}</button>
          <span class="pdfv-pageno"><input data-page inputmode="numeric" value="1" aria-label="Trang"><span>/ <b data-total>–</b></span></span>
          <button type="button" class="pdfv-btn" data-act="next" title="Trang sau">${ic('chevron-right')}</button>
        </div>
        <div class="pdfv-grp">
          <button type="button" class="pdfv-btn" data-act="zoomout" title="Thu nhỏ">${ic('zoom-out')}</button>
          <select data-zoom aria-label="Tỉ lệ">
            <option value="fit">Vừa chiều rộng</option><option value="page">Vừa trang</option>
            ${ZOOMS.map((z) => `<option value="${z}">${Math.round(z * 100)}%</option>`).join('')}
          </select>
          <button type="button" class="pdfv-btn" data-act="zoomin" title="Phóng to">${ic('zoom-in')}</button>
        </div>
        <div class="pdfv-grp">
          <button type="button" class="pdfv-btn" data-act="rotate" title="Xoay trang">${ic('rotate-cw')}</button>
          ${this.opts.invertButton ? `<button type="button" class="pdfv-btn" data-act="invert" title="Đọc nền tối (đỡ mỏi mắt)">${ic('moon')}</button>` : ''}
          ${this.opts.fullscreenButton ? `<button type="button" class="pdfv-btn" data-act="full" title="Toàn màn hình">${ic('maximize')}</button>` : ''}
        </div>
      </div>
      <div class="pdfv-scroll" tabindex="0"><div class="pdfv-pages"></div><div class="pdfv-state"></div></div>`;
    this.bar = r.querySelector('.pdfv-bar');
    this.scroller = r.querySelector('.pdfv-scroll');
    this.pagesEl = r.querySelector('.pdfv-pages');
    this.stateEl = r.querySelector('.pdfv-state');
    this.zoomSel = r.querySelector('[data-zoom]');
    this.pageInput = r.querySelector('[data-page]');
    this.totalEl = r.querySelector('[data-total]');
    this.zoomSel.value = this.mode;
    if (!this.zoomSel.value) { this.zoomSel.value = 'fit'; this.mode = 'fit'; }
    if (ls('tn-pdf-invert') === '1') r.classList.add('inverted');

    this.bar.addEventListener('click', (e) => {
      const b = e.target.closest('[data-act]');
      if (!b) return;
      const a = b.dataset.act;
      if (a === 'prev') this.goto(this.current - 1);
      else if (a === 'next') this.goto(this.current + 1);
      else if (a === 'zoomin') this.step(1);
      else if (a === 'zoomout') this.step(-1);
      else if (a === 'rotate') { this.rotation = (this.rotation + 90) % 360; this.relayout(true); }
      else if (a === 'invert') { r.classList.toggle('inverted'); try { localStorage.setItem('tn-pdf-invert', r.classList.contains('inverted') ? '1' : '0'); } catch (x) {} }
      else if (a === 'full') { if (document.fullscreenElement) document.exitFullscreen(); else r.requestFullscreen && r.requestFullscreen(); }
    });
    this.zoomSel.addEventListener('change', () => this.setZoom(this.zoomSel.value));
    this.pageInput.addEventListener('change', () => this.goto(parseInt(this.pageInput.value, 10) || 1));
    this.pageInput.addEventListener('focus', () => this.pageInput.select());

    // Ctrl + lăn chuột = phóng to/thu nhỏ đề (không phóng cả trang web)
    this.scroller.addEventListener('wheel', (e) => {
      if (!e.ctrlKey) return;
      e.preventDefault();
      this.step(e.deltaY < 0 ? 1 : -1);
    }, { passive: false });
    this.scroller.addEventListener('keydown', (e) => {
      if (e.key === 'PageDown' || (e.key === 'ArrowRight' && !e.shiftKey)) { e.preventDefault(); this.goto(this.current + 1); }
      if (e.key === 'PageUp' || (e.key === 'ArrowLeft' && !e.shiftKey)) { e.preventDefault(); this.goto(this.current - 1); }
    });
    let ticking = false;
    this.scroller.addEventListener('scroll', () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => { ticking = false; this.trackCurrent(); });
    });
    if (this.opts.protect) {
      ['contextmenu', 'dragstart', 'selectstart', 'copy'].forEach((ev) => r.addEventListener(ev, (e) => e.preventDefault()));
    }
    // Tự vừa khung khi cột thay đổi kích thước (kéo thanh chia)
    let rt;
    this.ro = new ResizeObserver(() => {
      if (!this.doc) return;
      if (this.mode === 'fit' || this.mode === 'page') {
        this.relayout(false);
        clearTimeout(rt);
        rt = setTimeout(() => this.relayout(true), 220);
      }
    });
    this.ro.observe(this.scroller);
  }

  state(html, cls) {
    this.stateEl.className = 'pdfv-state' + (cls ? ' ' + cls : '');
    this.stateEl.innerHTML = html || '';
    this.stateEl.hidden = !html;
  }

  /** Mở tệp: {url, key (base64 – giải mã XOR), headers} hoặc {data: ArrayBuffer} */
  async open(src) {
    this.state(`<div class="pdfv-load"><span class="spinner lg"></span><div>Đang tải đề thi…</div><div class="progress" style="width:220px"><span data-prog style="width:0%"></span></div></div>`);
    try {
      let bytes;
      if (src.data) {
        bytes = new Uint8Array(src.data);
      } else {
        const res = await fetch(src.url, { headers: src.headers || {}, credentials: 'same-origin' });
        if (!res.ok) {
          let msg = 'Không tải được đề thi (HTTP ' + res.status + ').';
          try { const j = await res.json(); if (j.error) msg = j.error; } catch (x) {}
          const err = new Error(msg); err.status = res.status; throw err;
        }
        const total = +(res.headers.get('X-File-Size') || res.headers.get('Content-Length') || 0);
        const prog = this.stateEl.querySelector('[data-prog]');
        if (res.body && res.body.getReader) {
          const reader = res.body.getReader();
          const chunks = [];
          let got = 0;
          for (;;) {
            const { done, value } = await reader.read();
            if (done) break;
            chunks.push(value);
            got += value.length;
            if (total && prog) prog.style.width = Math.min(100, (got * 100) / total).toFixed(0) + '%';
          }
          bytes = new Uint8Array(got);
          let off = 0;
          for (const c of chunks) { bytes.set(c, off); off += c.length; }
        } else {
          bytes = new Uint8Array(await res.arrayBuffer());
        }
      }
      if (src.key) {
        const k = b64ToBytes(src.key);
        const kl = k.length;
        for (let i = 0; i < bytes.length; i++) bytes[i] ^= k[i % kl];
      }
      if (this.doc) { try { await this.doc.destroy(); } catch (x) {} }
      const task = pdfjsLib.getDocument({
        data: bytes,
        standardFontDataUrl: new URL('vendor/pdfjs/standard_fonts/', ASSETS).href,
        wasmUrl: new URL('vendor/pdfjs/wasm/', ASSETS).href,
        iccUrl: new URL('vendor/pdfjs/iccs/', ASSETS).href,
        isEvalSupported: false,
        enableXfa: false,
      });
      this.doc = await task.promise;
      this.pages = [];
      this.pagesEl.innerHTML = '';
      for (let i = 1; i <= this.doc.numPages; i++) {
        const page = await this.doc.getPage(i);
        const vp = page.getViewport({ scale: 1, rotation: 0 });
        const el = document.createElement('div');
        el.className = 'pdfv-pg';
        el.dataset.n = i;
        el.innerHTML = `<div class="pdfv-pg-no">${i}</div>`;
        this.pagesEl.appendChild(el);
        this.pages.push({ num: i, el, w: vp.width, h: vp.height, rendered: null, task: null, canvas: null });
      }
      this.totalEl.textContent = this.doc.numPages;
      this.state('');
      this.io && this.io.disconnect();
      this.io = new IntersectionObserver((ents) => {
        ents.forEach((en) => { const p = this.pages[+en.target.dataset.n - 1]; p.visible = en.isIntersecting; });
        this.renderVisible();
      }, { root: this.scroller, rootMargin: '600px 0px' });
      this.pages.forEach((p) => this.io.observe(p.el));
      this.relayout(true);
      if (this.opts.onLoad) this.opts.onLoad(this.doc);
    } catch (e) {
      console.error(e);
      const msg = e && e.name === 'PasswordException' ? 'Tệp PDF có mật khẩu, không hiển thị được.' : (e.message || 'Không mở được tệp PDF.');
      this.state(`<div class="pdfv-err">${ic('triangle-alert', 'ic-lg')}<div>${msg}</div><button type="button" class="btn btn-sm" data-retry>${ic('refresh-cw')} Tải lại</button></div>`, 'error');
      const b = this.stateEl.querySelector('[data-retry]');
      if (b) b.onclick = () => this.open(src);
      if (this.opts.onError) this.opts.onError(e);
    }
  }

  empty(message) {
    this.pagesEl.innerHTML = '';
    this.totalEl.textContent = '0';
    this.state(`<div class="pdfv-err">${ic('file-text', 'ic-lg')}<div>${message}</div></div>`);
  }

  computeScale() {
    if (!this.pages.length) return 1;
    const p = this.pages[0];
    const rot = this.rotation % 180 !== 0;
    const pw = rot ? p.h : p.w, ph = rot ? p.w : p.h;
    const avail = Math.max(120, this.scroller.clientWidth - 28);
    if (this.mode === 'fit') return Math.max(0.3, Math.min(4, avail / pw));
    if (this.mode === 'page') return Math.max(0.3, Math.min(4, Math.min(avail / pw, (this.scroller.clientHeight - 24) / ph)));
    return parseFloat(this.mode) || 1;
  }

  /** Cập nhật kích thước khung trang; $render=true thì vẽ lại ở độ phân giải mới. */
  relayout(render) {
    const s = this.computeScale();
    const keepTop = this.scroller.scrollTop / Math.max(1, this.scroller.scrollHeight);
    this.scale = s;
    const rot = this.rotation % 180 !== 0;
    this.pages.forEach((p) => {
      const w = (rot ? p.h : p.w) * s, h = (rot ? p.w : p.h) * s;
      p.el.style.width = Math.floor(w) + 'px';
      p.el.style.height = Math.floor(h) + 'px';
    });
    this.scroller.scrollTop = keepTop * this.scroller.scrollHeight;
    if (render) {
      this.renderSeq++;
      this.renderVisible();
    }
  }

  renderVisible() {
    const vis = this.pages.filter((p) => p.visible).map((p) => p.num);
    if (!vis.length && this.pages.length) vis.push(this.current);
    const lo = Math.min(...vis) - 1, hi = Math.max(...vis) + 1;
    this.pages.forEach((p) => {
      if (p.num >= lo && p.num <= hi) this.renderPage(p);
      else if (p.canvas && (p.num < lo - this.opts.keepPages || p.num > hi + this.opts.keepPages)) this.release(p);
    });
  }

  release(p) {
    if (p.task) { try { p.task.cancel(); } catch (x) {} p.task = null; }
    if (p.canvas) { p.canvas.width = 0; p.canvas.height = 0; p.canvas.remove(); p.canvas = null; }
    p.rendered = null;
  }

  async renderPage(p) {
    const key = this.renderSeq + ':' + this.scale.toFixed(3) + ':' + this.rotation;
    if (p.rendered === key || p.pending === key) return;
    p.pending = key;
    if (p.task) { try { p.task.cancel(); } catch (x) {} p.task = null; }
    try {
      const page = await this.doc.getPage(p.num);
      const vp = page.getViewport({ scale: this.scale, rotation: this.rotation });
      let dpr = Math.min(window.devicePixelRatio || 1, 2);
      const maxPx = 7e6; // giới hạn bộ nhớ cho máy yếu
      if (vp.width * vp.height * dpr * dpr > maxPx) dpr = Math.sqrt(maxPx / (vp.width * vp.height));
      const canvas = document.createElement('canvas');
      canvas.width = Math.floor(vp.width * dpr);
      canvas.height = Math.floor(vp.height * dpr);
      const ctx = canvas.getContext('2d', { alpha: false });
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      const task = page.render({ canvasContext: ctx, viewport: vp, transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null, annotationMode: pdfjsLib.AnnotationMode.DISABLE });
      p.task = task;
      await task.promise;
      p.task = null;
      if (p.pending !== key) return;
      this.watermark(ctx, canvas.width, canvas.height);
      if (p.canvas) { p.canvas.width = 0; p.canvas.remove(); }
      p.canvas = canvas;
      p.el.appendChild(canvas);
      p.rendered = key;
      p.pending = null;
    } catch (e) {
      if (e && e.name === 'RenderingCancelledException') return;
      p.pending = null;
      console.warn('render page', p.num, e);
    }
  }

  watermark(ctx, w, h) {
    const text = this.opts.watermark;
    if (!text) return;
    ctx.save();
    const fs = Math.max(12, Math.round(w / 34));
    ctx.font = `600 ${fs}px "Be Vietnam Pro", Arial, sans-serif`;
    ctx.fillStyle = 'rgba(30, 58, 138, 0.085)';
    ctx.translate(w / 2, h / 2);
    ctx.rotate(-Math.PI / 7);
    const tw = ctx.measureText(text).width + fs * 3;
    const stepY = fs * 5.5;
    const R = Math.sqrt(w * w + h * h) / 2 + tw;
    let row = 0;
    for (let y = -R; y < R; y += stepY) {
      const off = (row++ % 2) * (tw / 2);
      for (let x = -R - off; x < R; x += tw) ctx.fillText(text, x, y);
    }
    ctx.restore();
  }

  trackCurrent() {
    if (!this.pages.length) return;
    const mid = this.scroller.scrollTop + this.scroller.clientHeight / 3;
    let cur = 1;
    for (const p of this.pages) { if (p.el.offsetTop <= mid) cur = p.num; else break; }
    if (cur !== this.current) {
      this.current = cur;
      this.pageInput.value = cur;
      if (this.opts.onPage) this.opts.onPage(cur, this.pages.length);
    }
  }

  goto(n) {
    if (!this.pages.length) return;
    n = Math.max(1, Math.min(this.pages.length, n));
    const p = this.pages[n - 1];
    this.scroller.scrollTo({ top: p.el.offsetTop - 12, behavior: 'smooth' });
    this.current = n;
    this.pageInput.value = n;
  }

  setZoom(mode) {
    this.mode = String(mode);
    this.zoomSel.value = this.mode;
    try { localStorage.setItem('tn-pdf-zoom', this.mode); } catch (x) {}
    this.relayout(true);
  }

  step(dir) {
    const cur = this.scale;
    let next = dir > 0 ? ZOOMS.find((z) => z > cur + 0.01) : [...ZOOMS].reverse().find((z) => z < cur - 0.01);
    if (!next) next = dir > 0 ? ZOOMS[ZOOMS.length - 1] : ZOOMS[0];
    this.setZoom(next);
  }

  destroy() {
    this.ro && this.ro.disconnect();
    this.io && this.io.disconnect();
    this.pages.forEach((p) => this.release(p));
    if (this.doc) this.doc.destroy();
  }
}

window.PdfViewer = PdfViewer;

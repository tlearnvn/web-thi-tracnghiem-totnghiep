/*
 * Bổ sung (polyfill) một số hàm JavaScript mới mà PDF.js cần nhưng trình duyệt cũ chưa có,
 * để đề PDF vẫn hiển thị trên máy phòng thi đời cũ (vd: Chrome/Edge 109 – bản cuối cho Windows 7).
 * Dùng chung cho luồng chính (pdfviewer.js) và worker (pdf.worker.js). Chỉ thêm khi còn thiếu.
 */
(function (g) {
  'use strict';
  var def = function (obj, name, fn) {
    if (obj && typeof obj[name] !== 'function') {
      Object.defineProperty(obj, name, { value: fn, configurable: true, writable: true, enumerable: false });
    }
  };

  // Chrome 119
  def(Promise, 'withResolvers', function () {
    var res, rej;
    var promise = new this(function (a, b) { res = a; rej = b; });
    return { promise: promise, resolve: res, reject: rej };
  });

  // Chrome 114: chép sang ArrayBuffer mới rồi "tách" (detach) vùng nhớ cũ như bản gốc
  var transfer = function (len) {
    var n = len === undefined ? this.byteLength : Math.max(0, Math.floor(Number(len)) || 0);
    var out = new ArrayBuffer(n);
    new Uint8Array(out).set(new Uint8Array(this, 0, Math.min(n, this.byteLength)));
    if (typeof structuredClone === 'function') {
      try { structuredClone(this, { transfer: [this] }); } catch (e) { /* không tách được thì thôi */ }
    }
    return out;
  };
  def(ArrayBuffer.prototype, 'transfer', transfer);
  def(ArrayBuffer.prototype, 'transferToFixedLength', transfer);
})(typeof globalThis !== 'undefined' ? globalThis : self);

export {};

/* Nhập danh sách từ Excel: chọn tệp -> xem trước -> xác nhận ghi (gửi lại tệp, không lưu tệp tạm trên máy chủ) */
(function (w, d) {
  'use strict';
  var STATUS = {
    'new': ['Thêm mới', 'success'], 'update': ['Cập nhật', 'info'], 'skip': ['Bỏ qua', 'default'], 'error': ['Lỗi', 'danger']
  };
  w.TNImport = function (o) {
    var form = d.querySelector(o.form);
    if (!form) return;
    var fileInput = form.querySelector('input[type=file]');
    var out = d.querySelector(o.output);
    var btnPreview = form.querySelector('[data-preview]');
    var sheetSel = form.querySelector('[name=sheet]');
    var lastFile = null;

    function send(commit) {
      var f = fileInput.files[0] || lastFile;
      if (!f) { TN.toast('Vui lòng chọn tệp Excel (.xlsx) hoặc CSV.', 'warning'); return; }
      lastFile = f;
      var fd = new FormData(form);
      fd.set('file', f);
      fd.set('commit', commit ? '1' : '0');
      var btn = commit ? out.querySelector('[data-commit]') : btnPreview;
      TN.busy(btn, true);
      TN.api(o.url, { form: fd, timeout: 300000 }).then(function (r) {
        TN.busy(btn, false);
        render(r);
        if (r.committed) TN.toast('Đã nhập xong: ' + r.result.created + ' thêm mới, ' + r.result.updated + ' cập nhật.', 'success');
      }).catch(function (e) {
        TN.busy(btn, false);
        if (e.data && e.data.sheets && sheetSel) fillSheets(e.data.sheets);
        out.innerHTML = '<div class="alert alert-danger">' + TN.icon('circle-alert') + '<div><div class="alert-title">Không đọc được tệp</div>' + TN.esc(e.message) + '</div></div>';
      });
    }
    function fillSheets(sheets) {
      if (!sheetSel || !sheets || sheets.length < 2) return;
      var cur = sheetSel.value;
      sheetSel.innerHTML = sheets.map(function (s, i) { return '<option value="' + i + '"' + (String(i) === cur ? ' selected' : '') + '>' + TN.esc(s) + '</option>'; }).join('');
      sheetSel.closest('.field').hidden = false;
    }
    function render(r) {
      fillSheets(r.sheets);
      var res = r.result;
      var cols = o.columns;
      var html = '';
      if (r.committed) {
        html += '<div class="alert alert-success mb-3">' + TN.icon('circle-check') + '<div><div class="alert-title">Nhập dữ liệu thành công</div>' +
          'Thêm mới <b>' + res.created + '</b>, cập nhật <b>' + res.updated + '</b>, bỏ qua <b>' + res.skipped + '</b>, lỗi <b>' + res.errors + '</b>.' +
          (r.credentials ? '<div class="row mt-2"><a class="btn btn-primary btn-sm" href="' + r.credentials_url + '">' + TN.icon('key-round') + ' Xem & in ' + r.credentials + ' tài khoản vừa cấp</a></div>' : '') + '</div></div>';
      } else {
        var detected = Object.keys(r.columns || {}).map(function (k) { return '<span class="badge badge-outline">' + TN.esc(r.columns[k]) + ' → ' + TN.esc(o.labels[k] || k) + '</span>'; }).join(' ');
        html += '<div class="card mb-3"><div class="card-body">' +
          '<div class="mini-stats mb-2">' +
          '<div class="mini-stat"><div class="v text-success">' + res.created + '</div><div class="l">Thêm mới</div></div>' +
          '<div class="mini-stat"><div class="v text-info">' + res.updated + '</div><div class="l">Cập nhật</div></div>' +
          '<div class="mini-stat"><div class="v">' + res.skipped + '</div><div class="l">Bỏ qua</div></div>' +
          '<div class="mini-stat"><div class="v text-danger">' + res.errors + '</div><div class="l">Lỗi</div></div></div>' +
          '<div class="text-sm text-muted mb-2">Cột đã nhận dạng: ' + (detected || '—') + '</div>' +
          '<div class="row"><span class="grow text-sm">' + (res.errors ? '⚠ Các dòng lỗi sẽ được bỏ qua. Sửa tệp rồi xem trước lại nếu cần.' : 'Dữ liệu hợp lệ, sẵn sàng nhập.') + '</span>' +
          '<button type="button" class="btn btn-primary" data-commit ' + (res.created + res.updated ? '' : 'disabled') + '>' + TN.icon('upload') + ' Xác nhận nhập ' + (res.created + res.updated) + ' dòng</button></div>' +
          '</div></div>';
      }
      html += '<div class="card"><div class="table-wrap" style="max-height:560px;overflow:auto"><table class="table compact"><thead><tr><th>Dòng</th>' +
        cols.map(function (c) { return '<th>' + TN.esc(c[1]) + '</th>'; }).join('') + '<th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody>';
      r.rows.forEach(function (row) {
        var st = STATUS[row.status] || ['', 'default'];
        html += '<tr><td class="text-muted">' + row.row + '</td>' + cols.map(function (c) {
          var v = row[c[0]];
          if (c[0] === 'birthday' && v) v = v.split('-').reverse().join('/');
          return '<td' + (c[2] ? ' class="' + c[2] + '"' : '') + '>' + TN.esc(v || '') + '</td>';
        }).join('') + '<td><span class="badge badge-' + st[1] + '">' + st[0] + '</span></td><td class="text-sm ' + (row.status === 'error' ? 'text-danger' : 'text-muted') + '">' + TN.esc(row.message || '') + '</td></tr>';
      });
      html += '</tbody></table></div></div>';
      out.innerHTML = html;
      var c = out.querySelector('[data-commit]');
      if (c) c.addEventListener('click', function () {
        TN.confirm({ message: 'Ghi ' + (res.created + res.updated) + ' dòng vào hệ thống?', ok: 'Nhập dữ liệu' }).then(function (ok) { if (ok) send(true); });
      });
      out.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    btnPreview.addEventListener('click', function () { send(false); });
    fileInput.addEventListener('change', function () { lastFile = null; if (fileInput.files[0]) send(false); });
    if (sheetSel) sheetSel.addEventListener('change', function () { send(false); });
  };
})(window, document);

/* =====================================================================
   Tạo bản PDF "Hướng dẫn sử dụng" từ README.md + docs/HUONG-DAN-SU-DUNG.md
   – trang bìa, thông tin tài liệu, mục lục & danh mục hình có số trang, các chương,
     ảnh minh họa, sơ đồ Mermaid (vector), đầu/chân trang, dấu trang (bookmark).

   Cách chạy (không cần cho việc chạy website):
     cd tools/pdf-guide
     npm install            # đặt PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 nếu máy đã có Chromium của Playwright
     npx playwright install chromium   # (chỉ lần đầu, nếu máy chưa có)
     npm run build          # -> docs/HUONG-DAN-SU-DUNG.pdf
     npm run build:share    # -> bản gửi người dùng: không có liên kết / nhắc tới kho mã nguồn trên GitHub
   Phông chữ (OFL) được tải về .cache/fonts ở lần chạy đầu.
   ===================================================================== */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { Marked } from 'marked';
import { chromium } from 'playwright';
import { PDFDocument, PDFName } from 'pdf-lib';
import * as pdfjs from 'pdfjs-dist/legacy/build/pdf.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../..');
// --share: bản để gửi cho người dùng – bỏ mọi liên kết ra ngoài và mọi chỗ nhắc tới kho mã nguồn trên GitHub
const SHARE = process.argv.includes('--share');
const OUT = path.join(ROOT, SHARE ? 'docs/Huong-dan-su-dung-He-thong-thi-trac-nghiem.pdf' : 'docs/HUONG-DAN-SU-DUNG.pdf');
const BUILD = path.join(HERE, SHARE ? 'build/share' : 'build');
const REPO_RE = /github|tlearnvn|web-thi-tracnghiem/i; // không được xuất hiện trong bản gửi
const VERSION = fs.readFileSync(path.join(ROOT, 'VERSION'), 'utf8').trim();
const now = new Date();
const MONTH = `Tháng ${now.getMonth() + 1} năm ${now.getFullYear()}`;
const META = {
  title: 'Hướng dẫn sử dụng – Hệ thống thi trắc nghiệm trực tuyến (định dạng 2025)',
  author: 'Trương Anh Tuấn',
  org: 'Trường THPT chuyên Lương Thế Vinh, TP. Đồng Nai',
  repo: 'https://github.com/tlearnvn/web-thi-tracnghiem-totnghiep',
};
const fileUrl = (p) => pathToFileURL(p).href;
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

// ---------------------------------------------------------------- Phông chữ
// Tệp .ttf tĩnh theo từng độ đậm, lấy qua Google Fonts CSS API (curl nhận về phông đầy đủ, không cắt theo bảng mã).
// Không dùng bản "variable": Chromium in phông variable thành Type3 – chữ kém nét, khó tìm và chép chữ.
const FONTS = [
  { alias: 'BVP', family: 'Be Vietnam Pro', file: 'BeVietnamPro', weights: [400, 500, 600, 700, 800], italic: [400, 700] },
  { alias: 'PFD', family: 'Playfair Display', file: 'PlayfairDisplay', weights: [700, 800] },
  { alias: 'JBM', family: 'JetBrains Mono', file: 'JetBrainsMono', weights: [400, 700] },
  // Mũi tên, ký hiệu toán, chữ Hy Lạp mà Be Vietnam Pro không có
  { alias: 'SYM', family: 'Noto Sans Math', file: 'NotoSansMath', weights: [400], range: 'U+0370-03FF, U+2190-21FF, U+2200-22FF, U+2713' },
  // Emoji đơn sắc dạng vector (theo màu chữ) thay cho emoji màu dạng ảnh
  { alias: 'NEM', family: 'Noto Emoji', file: 'NotoEmoji', weights: [400, 700] },
];
const FONT_DIR = path.join(HERE, '.cache/fonts');
const faces = (f) => [...f.weights.map((w) => ({ w, it: false })), ...(f.italic || []).map((w) => ({ w, it: true }))]
  .map((x) => ({ ...x, file: path.join(FONT_DIR, `${f.file}-${x.w}${x.it ? 'i' : ''}.ttf`) }));
function ensureFonts() {
  fs.mkdirSync(FONT_DIR, { recursive: true });
  for (const f of FONTS) {
    if (faces(f).every((x) => fs.existsSync(x.file) && fs.statSync(x.file).size > 10000)) continue;
    console.log('Tải phông', f.family);
    const q = faces(f).map((x) => `${x.it ? 1 : 0},${x.w}`).sort().join(';');
    const css = execFileSync('curl', ['-sSfL', `https://fonts.googleapis.com/css2?family=${f.family.replace(/ /g, '+')}:ital,wght@${q}`]).toString();
    for (const m of css.matchAll(/font-style:\s*(\w+);\s*font-weight:\s*(\d+);\s*src:\s*url\(([^)]+)\)/g)) {
      const x = faces(f).find((y) => y.w === +m[2] && y.it === (m[1] === 'italic'));
      if (x) execFileSync('curl', ['-sSfL', '-o', x.file, m[3]]);
    }
    const miss = faces(f).filter((x) => !fs.existsSync(x.file));
    if (miss.length) throw new Error('Không tải được phông: ' + miss.map((x) => path.basename(x.file)).join(', '));
  }
}
const fontCss = () => FONTS.flatMap((f) => faces(f).map((x) =>
  `@font-face { font-family: '${f.alias}'; src: url('${fileUrl(x.file)}'); font-weight: ${x.w}; font-style: ${x.it ? 'italic' : 'normal'};${f.range ? ` unicode-range: ${f.range};` : ''} }`)).join('\n');

// ---------------------------------------------------------------- Đọc & cắt Markdown theo "## "
function sections(md) {
  const out = [];
  let cur = { title: null, lines: [] }, fence = false;
  for (const line of md.split('\n')) {
    if (/^\s*```/.test(line)) fence = !fence;
    const m = !fence && /^## (.+)$/.exec(line);
    if (m) { out.push(cur); cur = { title: m[1].trim(), lines: [] }; } else cur.lines.push(line);
  }
  out.push(cur);
  return out.map((s) => ({ title: s.title, body: s.lines.join('\n').trim() }));
}
const plain = (t) => String(t).replace(/^[^\p{L}\p{N}]+/u, '').trim();
// Giống cách GitHub tạo neo cho tiêu đề (để các liên kết #... trong tài liệu vẫn đúng)
const slug = (t) => String(t).toLowerCase().trim().replace(/<[^>]+>/g, '').replace(/[^\p{L}\p{N}\s_-]/gu, '').replace(/\s/g, '-');

// Bỏ ký tự chọn kiểu emoji (U+FE0F): có nó Chromium lấy emoji màu dạng ảnh thay cho phông emoji vector
const readMd = (f) => fs.readFileSync(path.join(ROOT, f), 'utf8').replace(/\uFE0F/g, '');
// Bản gửi: diễn đạt lại các chỗ gắn với GitHub (mỗi mẫu phải khớp – README đổi thì báo lỗi để sửa tại đây)
const SHARE_EDITS = [
  [/\*\*Tải mã nguồn\*\* \(nút \*Code → Download ZIP\*\) và giải nén/, '**Chép mã nguồn** của hệ thống lên hosting và giải nén'],
  [/git clone \S+\ncd \S+\n/, 'cd thu-muc-ma-nguon          # thư mục đã giải nén mã nguồn\n'],
  [/\n- Bản PDF của hướng dẫn \(`docs\/HUONG-DAN-SU-DUNG\.pdf`\)[^\n]*/, ''], // cách dựng tài liệu từ kho mã nguồn
];
const shareEdit = (md) => SHARE_EDITS.reduce((t, [re, to]) => {
  if (!re.test(t)) throw new Error('Bản gửi: README không còn đoạn ' + re);
  return t.replace(re, to);
}, md);
const readme = SHARE ? shareEdit(readMd('README.md')) : readMd('README.md');
const guide = readMd('docs/HUONG-DAN-SU-DUNG.md');
const R = Object.fromEntries(sections(readme).filter((s) => s.title).map((s) => [plain(s.title), s]));
const G = sections(guide).filter((s) => s.title && /^\d+\./.test(s.title));
const need = (k) => { if (!R[k]) throw new Error('README thiếu mục: ' + k); return R[k].body; };

// Đường dẫn ảnh -> file:// tuyệt đối; tài liệu hướng dẫn nằm trong docs/
const absImages = (md, baseDir) => md
  .replace(/(!\[[^\]]*\]\()([^)\s]+)(\))/g, (m, a, src, b) => (/^(https?:|file:)/.test(src) ? m : a + fileUrl(path.resolve(baseDir, src)) + b))
  .replace(/(<img[^>]*\ssrc=")([^"]+)(")/g, (m, a, src, b) => (/^(https?:|file:)/.test(src) ? m : a + fileUrl(path.resolve(baseDir, src)) + b));

// ---------------------------------------------------------------- Dựng nội dung các chương
const ch = [];
ch.push({
  badge: 'GT', word: true, kick: 'Giới thiệu', title: 'Tổng quan hệ thống', id: 'tong-quan', base: ROOT,
  md: `Hệ thống giúp học sinh **làm bài thi trắc nghiệm trên máy tính** theo đúng định dạng đề thi tốt nghiệp THPT từ năm 2025: **bên trái là đề PDF**, **bên phải là phiếu trả lời** giống mẫu của Bộ GD&ĐT. Phần mềm viết bằng PHP thuần, chạy được trên shared hosting, lưu toàn bộ dữ liệu – kể cả tệp đề PDF – vào **một tệp SQLite** hoặc **MySQL** nên không làm tăng số tệp (inode) trên hosting.

<div class="thumbs">
<figure><img src="docs/images/03-tong-quan.jpg" alt="Trang tổng quan"><figcaption>Trang tổng quan của quản trị</figcaption></figure>
<figure><img src="docs/images/06-phong-thi.jpg" alt="Phòng thi"><figcaption>Phòng thi: đề PDF và phiếu trả lời</figcaption></figure>
<figure><img src="docs/images/11-giam-sat.jpg" alt="Giám sát"><figcaption>Giám sát ca thi trực tiếp</figcaption></figure>
<figure><img src="docs/images/17-thong-ke.jpg" alt="Thống kê"><figcaption>Thống kê & phân tích kết quả</figcaption></figure>
</div>

### Tính năng chính

${need('Tính năng')}

### Quy trình tổ chức một kỳ thi

${need('Quy trình tổ chức một kỳ thi')}

### Nên đọc chương nào?

| Bạn là | Nên đọc |
|---|---|
| **Người cài đặt / quản trị hệ thống** | *Cài đặt & triển khai*, Chương 1, 11, 12, 13 và hai phụ lục |
| **Giáo viên** (soạn đề, tổ chức ca thi, chấm) | Chương 2 → 6, Chương 9, 10 |
| **Giám thị** | Chương 6 (điều khiển ca thi), Chương 7 (giám sát & xử lý sự cố) |
| **Học sinh** | Chương 8 (đặc biệt 8.3 – Phần II, 8.4 – Phần III), Chương 13 |
`,
});
ch.push({ badge: 'CĐ', word: true, kick: 'Chuẩn bị', title: 'Cài đặt & triển khai', id: 'cai-dat', base: ROOT, md: need('Cài đặt') });

const extra = {
  // đoạn "Ngoài ra có thể…" sau bảng trong README lặp lại các tùy chọn chương 4 đã nêu
  4: `### Bảng điểm theo môn (quy định 2025)\n\n${need('Cách tính điểm theo quy định 2025').replace(/\n+Ngoài ra có thể:[^\n]*\s*$/, '')}`,
  // câu cuối mục này trong README trùng với lưu ý "nhật ký bài làm" đã có trong chương 7
  7: `### Bảng tra cứu nhanh các tình huống\n\n${need('Xử lý sự cố trong giờ thi').replace(/\n+Mọi thao tác đều được ghi[^\n]*\s*$/, '')}`,
  12: `### Chi tiết kỹ thuật: sao lưu & chuyển đổi CSDL\n\n${need('Sao lưu & chuyển đổi CSDL')}`,
};
for (const s of G) {
  const n = +s.title.match(/^(\d+)\./)[1];
  ch.push({ badge: String(n), kick: 'Chương ' + n, title: s.title.replace(/^\d+\.\s*/, ''), id: slug(s.title), base: path.join(ROOT, 'docs'), num: n,
    md: s.body + (extra[n] ? '\n\n' + extra[n] : ''), faq: n === 13 });
}
// "Bảo mật" (chữ) đặt trước để trang đầu phụ lục không bị trống khi sơ đồ kiến trúc cao gần một trang
ch.push({ badge: 'A', kick: 'Phụ lục A', title: 'Bảo mật, kiến trúc & sơ đồ kỹ thuật', id: 'phu-luc-a', base: ROOT,
  md: `### Bảo mật\n\n${need('Bảo mật')}\n\n### Sơ đồ kiến trúc\n\n${need('Kiến trúc & sơ đồ')}` });
ch.push({ badge: 'B', kick: 'Phụ lục B', title: 'Cấu trúc mã nguồn & phát triển', id: 'phu-luc-b', base: ROOT,
  md: `### Cấu trúc mã nguồn\n\n${need('Cấu trúc mã nguồn')}\n\n### Phát triển\n\n${need('Phát triển')}\n\n### Giấy phép\n\n${need('Giấy phép').replace(/<div align="center">[\s\S]*$/, '').trim()}` });

// ---------------------------------------------------------------- Sơ đồ: điều chỉnh riêng cho trang A4
// Chuỗi bước đơn giản (A --> B --> C ...) -> thẻ bước đánh số, dễ đọc hơn sơ đồ quá dài
function chainSteps(src) {
  if (!/^flowchart\s+(LR|TD|TB)/.test(src.trim())) return null;
  const labels = {}, next = {}, indeg = {};
  for (const m of src.matchAll(/(\w+)\["([^"]*)"\]/g)) labels[m[1]] ??= m[2];
  for (const m of src.matchAll(/(\w+)(?:\["[^"]*"\]|\{"[^"]*"\})?\s*-->\s*(?:\|[^|]*\|\s*)?(\w+)/g)) {
    if (next[m[1]]) return null; // có rẽ nhánh
    next[m[1]] = m[2]; indeg[m[2]] = (indeg[m[2]] || 0) + 1;
  }
  const starts = Object.keys(next).filter((k) => !indeg[k]);
  if (starts.length !== 1 || Object.values(indeg).some((v) => v > 1)) return null;
  const order = [];
  for (let k = starts[0]; k && order.length < 50; k = next[k]) order.push(k);
  if (order.length < 5 || order.some((k) => labels[k] === undefined)) return null;
  return order.map((k) => labels[k].replace(/^\d+\.\s*/, '').replace(/<br\s*\/?>/g, '<br>'));
}
// Sơ đồ rẽ nhiều nhánh / sơ đồ dữ liệu: vẽ theo chiều ngang để chữ to hơn khi thu vào khổ A4
function tweak(src) {
  if (/^erDiagram/.test(src.trim())) return src.replace(/^erDiagram\s*\n/, 'erDiagram\n    direction LR\n');
  const out = {};
  for (const m of src.matchAll(/(\w+)(?:\["[^"]*"\]|\{"[^"]*"\})?\s*-->/g)) out[m[1]] = (out[m[1]] || 0) + 1;
  if (/^flowchart\s+(TD|TB)/.test(src.trim()) && Math.max(0, ...Object.values(out)) >= 5) return src.replace(/^flowchart\s+(TD|TB)/, 'flowchart LR');
  return src;
}

// ---------------------------------------------------------------- Markdown -> HTML
function mdToHtml(md, base) {
  const m = new Marked({ gfm: true });
  m.use({
    renderer: {
      heading({ tokens, depth, text }) {
        const lvl = Math.min(6, depth - 1); // "###" trong tệp nguồn -> h2 trong PDF
        let inner = this.parser.parseInline(tokens);
        const mm = /^(\d+\.\d+)\.\s+/.exec(text);
        if (lvl === 2 && mm) inner = `<span class="num">${mm[1]}</span>` + inner.replace(/^\d+\.\d+\.\s+/, '');
        return `<h${lvl} id="${esc(slug(text))}">${inner}</h${lvl}>\n`;
      },
      code({ text, lang }) {
        if (lang === 'mermaid') {
          const steps = chainSteps(text);
          if (steps) return `<figure class="diagram steps"><ol>${steps.map((t, i) => `<li><span class="no">${i + 1}</span><span class="lb">${t}</span></li>`).join('')}</ol><figcaption></figcaption></figure>\n`;
          return `<figure class="diagram"><div class="mermaid">${esc(tweak(text))}</div><figcaption></figcaption></figure>\n`;
        }
        return `<pre class="code"${lang ? ` data-lang="${esc(lang)}"` : ''}><code>${esc(text)}</code></pre>\n`;
      },
      blockquote({ tokens }) {
        const body = this.parser.parse(tokens);
        const t = body.replace(/<[^>]+>/g, '');
        const kind = /⚠/.test(t) ? 'warn' : /💡/.test(t) ? 'tip' : /🔎/.test(t) ? 'note' : 'info';
        return `<aside class="callout ${kind}">${body.replace(/(<p>)\s*(💡|⚠️|⚠|🔎)\s*/u, '$1')}</aside>\n`;
      },
      link({ href, tokens }) {
        const text = this.parser.parseInline(tokens);
        if (/^(https?:|mailto:)/.test(href)) return SHARE ? text : `<a href="${esc(href)}">${text}</a>`;
        if (href.startsWith('#')) return `<a href="${esc(href)}">${text}</a>`;
        if (/\.(jpe?g|png)$/i.test(href)) return text; // ảnh bấm để phóng to trên GitHub
        const rel = path.relative(ROOT, path.resolve(base, decodeURIComponent(href)));
        if (/HUONG-DAN-SU-DUNG\.md$/.test(rel)) return `${text} (tài liệu này)`;
        return `${text} <span class="file-ref">(<code>${esc(rel)}</code>)</span>`;
      },
      image({ href, text }) {
        return `<img src="${esc(href)}" alt="${esc(text)}">`;
      },
    },
  });
  return m.parse(absImages(md, base));
}

let html = '';
for (const c of ch) {
  c.body = mdToHtml(c.md, c.base);
  const subs = [...c.body.matchAll(/<h2 id="([^"]+)">([\s\S]*?)<\/h2>/g)].map((x) => ({ id: x[1], html: x[2] }));
  c.subs = subs;
  const map = subs.length > 1 ? `<nav class="chap-map"><b>Trong chương</b>${subs.map((s) => `<span>${s.html.replace(/<span class="num">([^<]+)<\/span>/, '<i>$1</i>')}</span>`).join(' ')}</nav>` : '';
  html += `<section class="chapter${c.faq ? ' faq' : ''}" data-kind="${c.num ? 'ch' : 'x'}">
  <header class="chap-head"><div class="chap-badge${c.word ? ' word' : ''}"><span class="l">${c.num ? 'CHƯƠNG' : c.kick.startsWith('Phụ') ? 'PHỤ LỤC' : c.kick.toUpperCase()}</span><span class="n">${esc(c.badge)}</span></div>
  <div><div class="kick">${esc(c.kick)}</div><h1 id="${esc(c.id)}">${esc(c.title)}</h1></div></header>
  ${map}
  ${c.body}
</section>\n`;
}

// ---------------------------------------------------------------- Trang bìa, thông tin, mục lục
const EMBLEM = `<svg class="emblem" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
<defs><linearGradient id="eg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f7e7a8"/><stop offset=".45" stop-color="#d4af37"/><stop offset="1" stop-color="#7a5c12"/></linearGradient>
<linearGradient id="es" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset=".6" stop-color="#eef0f2"/><stop offset="1" stop-color="#c4c8ce"/></linearGradient></defs>
<rect x="8" y="8" width="184" height="184" rx="40" fill="url(#es)" stroke="url(#eg)" stroke-width="6"/>
<rect x="22" y="22" width="156" height="156" rx="30" fill="none" stroke="#e3c766" stroke-width="1.5"/>
${['A', 'B', 'C', 'D'].map((l, j) => `<text x="${66 + j * 30}" y="56" font-family="BVP" font-weight="800" font-size="16" fill="#94701a" text-anchor="middle">${l}</text>`).join('')}
${[1, 2, 3, 4].map((r, i) => `<text x="40" y="${86 + i * 28}" font-family="BVP" font-weight="700" font-size="14" fill="#767c86" text-anchor="middle">${r}</text>` +
  [0, 1, 2, 3].map((j) => { const on = [1, 3, 0, 2][i] === j; return `<circle cx="${66 + j * 30}" cy="${81 + i * 28}" r="10" fill="${on ? 'url(#eg)' : '#ffffff'}" stroke="${on ? '#7a5c12' : '#a9aeb6'}" stroke-width="${on ? 1.5 : 1.8}"/>`; }).join('')).join('')}
</svg>`;

// Dòng chữ vàng kim của tựa bìa: chữ SVG tô gradient (vẫn là chữ thật trong PDF – tìm và chép được)
const GOLD_LINE = (t) => `<svg class="gold-line" width="600" height="43" viewBox="0 0 600 43"><defs><linearGradient id="tg" x1="0" x2="1">
<stop offset="0" stop-color="#7a5c12"/><stop offset=".45" stop-color="#c9a227"/><stop offset="1" stop-color="#8c6a14"/></linearGradient></defs>
<text x="300" y="33" text-anchor="middle" font-family="PFD" font-weight="800" font-size="36" fill="url(#tg)">${esc(t)}</text></svg>`;

const cover = `<section class="cover"><div class="arc"></div><div class="arc2"></div><div class="frame"></div><div class="inner">
<div class="kicker">TÀI LIỆU HƯỚNG DẪN SỬ DỤNG</div>
${EMBLEM}
<div class="ct">Hệ thống thi trắc nghiệm${GOLD_LINE('trực tuyến')}</div>
<div class="cs">Định dạng đề thi tốt nghiệp THPT từ năm 2025</div>
<div class="rule"></div>
<div class="tags"><span>Đề PDF được bảo vệ</span><span>Phiếu trả lời mẫu của Bộ</span><span>Giám sát trực tiếp</span><span>Phân tích câu hỏi</span><span>SQLite / MySQL</span></div>
<div class="shot"><img src="${fileUrl(path.join(ROOT, 'docs/images/06-phong-thi.jpg'))}" alt=""></div>
<div class="author"><div class="lbl">Tác giả</div><div class="name">${esc(META.author)}</div><div class="org">${esc(META.org)}</div>
<div class="meta">PHIÊN BẢN ${esc(VERSION)} · ${esc(MONTH.toUpperCase())}</div></div>
</div></section>`;

const info = `<section class="front">
<h1 class="front-title" id="thong-tin">Thông tin tài liệu</h1><div class="rule"></div>
<div class="info-grid">
<div class="k">Tên tài liệu</div><div>Hướng dẫn sử dụng hệ thống thi trắc nghiệm trực tuyến – định dạng đề thi tốt nghiệp THPT từ năm 2025</div>
<div class="k">Phiên bản phần mềm</div><div>${esc(VERSION)}</div>
<div class="k">Thời gian</div><div>${esc(MONTH)}</div>
<div class="k">Tác giả</div><div><b>${esc(META.author)}</b></div>
<div class="k">Đơn vị</div><div>${esc(META.org)}</div>
<div class="k">Đối tượng</div><div>Quản trị viên, giáo viên, giám thị và học sinh</div>
${SHARE ? '' : `<div class="k">Mã nguồn</div><div><a href="${META.repo}">${esc(META.repo.replace('https://', ''))}</a></div>\n`}<div class="k">Múi giờ</div><div>Mọi thời gian trong hệ thống và tài liệu là <b>giờ Việt Nam (UTC+7)</b></div>
</div>
<div class="sub">Quy ước trình bày</div>
<table><thead><tr><th style="width:38mm">Ký hiệu</th><th>Ý nghĩa</th></tr></thead><tbody>
<tr><td><b>Chữ đậm</b></td><td>Tên nút, mục menu, trang trên giao diện – ví dụ <b>Ca thi → Tạo ca thi</b></td></tr>
<tr><td><em>Chữ nghiêng</em></td><td>Tên tùy chọn, trạng thái hiển thị – ví dụ <em>Đã lưu</em>, <em>Mất mạng · đã lưu trên máy</em></td></tr>
<tr><td><code>Chữ đơn cách</code></td><td>Nội dung cần gõ, giá trị, tên tệp – ví dụ <code>-1,5</code>, <code>storage/config.php</code></td></tr>
<tr><td>→</td><td>Thứ tự thao tác</td></tr>
<tr><td><kbd>Ctrl</kbd> + <kbd>S</kbd></td><td>Phím tắt trên bàn phím</td></tr>
</tbody></table>
<div class="sub">Về tài liệu này</div>
<p>Tài liệu hướng dẫn cài đặt, cấu hình và sử dụng hệ thống thi trắc nghiệm trực tuyến theo định dạng đề thi tốt nghiệp THPT từ năm 2025, gồm: phần <b>Giới thiệu</b> và <b>Cài đặt & triển khai</b>; <b>13 chương</b> hướng dẫn theo từng công việc của quản trị viên, giáo viên, giám thị và học sinh; <b>2 phụ lục</b> kỹ thuật (kiến trúc, sơ đồ dữ liệu, bảo mật, cấu trúc mã nguồn).</p>
<p>Ảnh minh họa được chụp trực tiếp từ phần mềm với <b>dữ liệu mẫu</b> – họ tên học sinh, giáo viên, trường học trong ảnh đều là giả định.</p>
<p class="colophon">${SHARE ? '' : 'Tài liệu được dàn trang tự động từ <code>README.md</code> và <code>docs/HUONG-DAN-SU-DUNG.md</code> bằng công cụ <code>tools/pdf-guide</code> đi kèm mã nguồn. '}Phông chữ: Be Vietnam Pro, Playfair Display, JetBrains Mono, Noto Sans Math, Noto Emoji (giấy phép SIL Open Font License).</p>
<aside class="callout tip"><p><b>Mẹo:</b> khung vàng nhạt là mẹo hoặc lưu ý hữu ích; khung đỏ nhạt là cảnh báo cần đọc kỹ. Bấm vào dòng trong mục lục hoặc bấm dấu trang (bookmark) của trình đọc PDF để đến ngay phần cần xem.</p></aside>
</section>`;

function tocHtml(pages) {
  const pg = (id) => `<span class="pg">${pages[id] || '000'}</span>`;
  let h = `<section class="front"><h1 class="front-title" id="muc-luc">Mục lục</h1><div class="rule"></div><div class="toc">`;
  for (const c of ch) {
    h += `<div class="ch"><span class="lbl">${esc(c.kick)}</span><a href="#${esc(c.id)}"><span class="t">${esc(c.title)}</span><span class="dots"></span>${pg(c.id)}</a></div>`;
    for (const s of c.subs) h += `<div class="it"><a href="#${esc(s.id)}"><span class="t">${s.html.replace(/<span class="num">([^<]+)<\/span>/, '<b style="color:#94701a;margin-right:1.4mm">$1</b>')}</span><span class="dots"></span>${pg(s.id)}</a></div>`;
  }
  h += `<div class="ch"><span class="lbl">Danh mục</span><a href="#danh-muc-hinh"><span class="t">Hình ảnh & sơ đồ</span><span class="dots"></span>${pg('danh-muc-hinh')}</a></div>`;
  return h + '</div></section>';
}
function lofHtml(figs, pages) {
  const list = (arr) => arr.map((f) => `<div class="it"><a href="#${f.id}"><span class="n">${esc(f.label)}</span><span class="t">${esc(f.cap)}</span><span class="dots"></span><span class="pg">${pages[f.id] || '000'}</span></a></div>`).join('');
  return `<section class="front"><h1 class="front-title" id="danh-muc-hinh">Danh mục hình ảnh & sơ đồ</h1><div class="rule"></div>
<div class="sub">Sơ đồ</div><div class="lof">${list(figs.filter((f) => f.kind === 'diagram'))}</div>
<div class="sub">Hình ảnh minh họa</div><div class="lof">${list(figs.filter((f) => f.kind === 'shot'))}</div></section>`;
}

const back = `<section class="cover back"><div class="arc"></div><div class="arc2"></div><div class="frame"></div><div class="inner">
${EMBLEM}<div class="ct">Hệ thống thi trắc nghiệm trực tuyến</div><div class="cs">Định dạng đề thi tốt nghiệp THPT từ năm 2025</div><div class="rule"></div>
<div class="author" style="margin-top:0"><div class="lbl">Tác giả</div><div class="name">${esc(META.author)}</div><div class="org">${esc(META.org)}</div></div>
${SHARE ? '' : `<div class="repo">${esc(META.repo.replace('https://', ''))}</div>`}<div class="meta">PHIÊN BẢN ${esc(VERSION)} · ${esc(MONTH.toUpperCase())}</div></div></section>`;

// ---------------------------------------------------------------- Ghép trang & in
const css = fontCss() + '\n' + fs.readFileSync(path.join(HERE, 'theme.css'), 'utf8');
const page = (toc, lof) => `<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>${esc(META.title)}</title><style>${css}</style></head>
<body>${cover}${info}${toc}${lof}${html}${back}</body></html>`;

// Việc chạy trong trang: đổi <p><img></p> thành hình có chú thích, mở <details>, vẽ sơ đồ Mermaid,
// giữ bảng / đoạn mã ngắn trên cùng một trang
async function prepare(p) {
  await p.evaluate(() => document.fonts.ready);
  await p.addScriptTag({ path: path.join(HERE, 'node_modules/mermaid/dist/mermaid.min.js') });
  return p.evaluate(async () => {
    const figs = [];
    document.querySelectorAll('details').forEach((d) => {
      const s = d.querySelector('summary');
      // <details> chỉ chứa một ảnh (ảnh phụ trên GitHub) -> hình bình thường, không đóng khung
      const imgs = d.querySelectorAll('img');
      if (imgs.length === 1 && d.textContent.replace(s ? s.textContent : '', '').trim() === '') {
        const para = document.createElement('p'); para.appendChild(imgs[0]); d.replaceWith(para);
        return;
      }
      const box = document.createElement('div'); box.className = 'detail-box';
      if (s) { const t = document.createElement('div'); t.className = 'detail-title'; t.innerHTML = s.innerHTML; box.appendChild(t); s.remove(); }
      while (d.firstChild) box.appendChild(d.firstChild);
      d.replaceWith(box);
    });
    document.querySelectorAll('.chapter img').forEach((img) => {
      if (img.closest('figure')) return;
      const p = img.parentElement;
      const fig = document.createElement('figure'); fig.className = 'shot';
      const only = p && p.tagName === 'P' && p.textContent.trim() === '' && p.querySelectorAll('img').length === 1;
      (only ? p : img).replaceWith(fig);
      fig.appendChild(img);
      fig.appendChild(document.createElement('figcaption'));
    });
    let nShot = 0, nDia = 0;
    document.querySelectorAll('.chapter figure').forEach((f) => {
      if (f.closest('.thumbs')) return;
      const cap = f.querySelector('figcaption');
      const isDia = f.classList.contains('diagram');
      let text;
      if (isDia) {
        let h = f.previousElementSibling;
        while (h && !/^H[1-3]$/.test(h.tagName)) h = h.previousElementSibling;
        text = h ? h.textContent.replace(/^\d+(\.\d+)*\s*/, '') : f.closest('.chapter').querySelector('h1').textContent;
      } else text = (f.querySelector('img').getAttribute('alt') || '').trim();
      const label = isDia ? 'Sơ đồ ' + (++nDia) : 'Hình ' + (++nShot);
      f.id = (isDia ? 'so-do-' : 'hinh-') + (isDia ? nDia : nShot);
      cap.innerHTML = '<b>' + label + '.</b> ' + text.replace(/&/g, '&amp;').replace(/</g, '&lt;');
      // Đoạn in nghiêng ngay sau ảnh, mở đầu bằng chính tên ảnh = lời chú thích dài -> gộp vào chú thích
      const nx = f.nextElementSibling;
      if (!isDia && text && nx && nx.tagName === 'P' && nx.firstChild && nx.firstChild.nodeName === 'EM' && nx.textContent.trim().startsWith(text)) {
        cap.innerHTML = '<b>' + label + '.</b> ' + nx.innerHTML;
        cap.classList.add('long');
        nx.remove();
      }
      figs.push({ id: f.id, label, cap: text, kind: isDia ? 'diagram' : 'shot' });
    });
    // Bảng: cột đầu ngắn và ô ngắn không xuống dòng; bảng nhỏ không bị cắt sang trang sau
    document.querySelectorAll('.chapter table, .front table').forEach((t) => {
      const first = [...t.rows].map((r) => (r.cells[0] ? r.cells[0].textContent.trim() : ''));
      if (Math.max(...first.map((x) => x.length)) <= 12) t.classList.add('k1');
      t.querySelectorAll('td, th').forEach((c) => { if (c.textContent.trim().length <= 9) c.classList.add('nw'); });
    });
    // Đoạn mã: ngắn thì không cắt ngang; dòng dài hơn bề rộng khung (tính cả khi nằm trong hộp thông tin)
    // thì thu cỡ chữ – không nhỏ hơn 6,4pt – cho khỏi xuống dòng. JetBrains Mono: mỗi ký tự rộng 0,6em.
    document.querySelectorAll('pre.code').forEach((pre) => {
      const lines = pre.textContent.replace(/\n$/, '').split('\n');
      if (lines.length <= 16) pre.classList.add('keep');
      const L = Math.max(...lines.map((l) => l.length));
      const cs = getComputedStyle(pre);
      const w = pre.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
      if (L * 0.6 * parseFloat(cs.fontSize) > w) pre.style.fontSize = Math.max(6.4 / 0.75, (w / (L * 0.6)) * 0.98).toFixed(2) + 'px';
    });
    window.mermaid.initialize({
      startOnLoad: false, securityLevel: 'loose', theme: 'base', fontFamily: 'BVP, SYM, NEM, sans-serif',
      flowchart: { htmlLabels: true, curve: 'basis', padding: 14 }, sequence: { useMaxWidth: true, actorMargin: 40 },
      themeVariables: {
        fontFamily: 'BVP, SYM, NEM, sans-serif', fontSize: '14px', primaryColor: '#fcf8ec', primaryTextColor: '#23252b', primaryBorderColor: '#c9a227',
        secondaryColor: '#f1f2f4', secondaryTextColor: '#23252b', secondaryBorderColor: '#a9aeb6', tertiaryColor: '#ffffff', tertiaryBorderColor: '#d4af37',
        lineColor: '#767c86', textColor: '#23252b', mainBkg: '#fcf8ec', nodeBorder: '#c9a227', clusterBkg: '#f7f8f9', clusterBorder: '#c4c8ce',
        edgeLabelBackground: '#ffffff', actorBkg: '#fcf8ec', actorBorder: '#c9a227', actorTextColor: '#23252b', signalColor: '#5e646e', signalTextColor: '#23252b',
        labelBoxBkgColor: '#f7efd2', labelBoxBorderColor: '#c9a227', labelTextColor: '#5f4810', noteBkgColor: '#f7efd2', noteBorderColor: '#c9a227', activationBkgColor: '#eef0f2',
        attributeBackgroundColorOdd: '#ffffff', attributeBackgroundColorEven: '#fcf8ec',
      },
    });
    await window.mermaid.run({ nodes: document.querySelectorAll('.mermaid') });
    // Mermaid gắn một khung chú thích ẩn vào cuối <body> -> sinh thêm một trang trắng sau bìa sau
    document.querySelectorAll('body > .mermaidTooltip').forEach((e) => e.remove());
    // Bảng nhỏ (đo sau khi đã có phông) giữ trọn trên một trang
    document.querySelectorAll('.chapter table').forEach((t) => { if (t.getBoundingClientRect().height < 330) t.classList.add('keep'); });
    return figs;
  });
}

// Kích thước (px CSS, 1px = 0,75pt khi in) của từng hình chụp màn hình để tính chuyện thu nhỏ
function measure(p, shrink) {
  return p.evaluate((shrink) => {
    for (const [id, h] of Object.entries(shrink)) { const img = document.querySelector('#' + id + ' img'); if (img) img.style.maxHeight = h + 'px'; }
    const px = (el, k) => parseFloat(getComputedStyle(el)[k]) || 0;
    return [...document.querySelectorAll('.chapter figure.shot')].filter((f) => !f.closest('.thumbs')).map((f) => {
      const img = f.querySelector('img');
      const prev = f.previousElementSibling;
      const head = prev && /^H[2-4]$/.test(prev.tagName)
        ? { keys: [prev.querySelector('.num'), prev.lastChild].filter(Boolean).map((x) => x.textContent.trim().slice(0, 12)).filter(Boolean),
            h: prev.getBoundingClientRect().height + px(prev, 'marginTop') + px(prev, 'marginBottom') }
        : null;
      return { id: f.id, label: f.querySelector('figcaption b').textContent.replace(/\.$/, ''), imgH: img.getBoundingClientRect().height,
        figH: f.getBoundingClientRect().height + px(f, 'marginTop'), head };
    });
  }, shrink);
}

// Chữ trên từng trang (bỏ đầu / chân trang), sắp theo chiều dọc – đơn vị pt tính từ mép trên trang
const MM = 72 / 25.4;
async function textPages(pdfBytes) {
  const doc = await pdfjs.getDocument({ data: new Uint8Array(pdfBytes), useSystemFonts: false, disableFontFace: true, verbosity: 0 }).promise;
  const out = [];
  for (let n = 1; n <= doc.numPages; n++) {
    const pg = await doc.getPage(n);
    const H = pg.view[3];
    const items = (await pg.getTextContent()).items.filter((x) => x.str.trim())
      .map((x) => ({ s: x.str.trim(), top: H - x.transform[5] - x.height * 0.8, bot: H - x.transform[5] + x.height * 0.25 }))
      .filter((x) => x.top > 23 * MM && x.bot < H - 20 * MM)
      .sort((a, b) => a.top - b.top);
    out.push({ n, H, items });
  }
  return out;
}
const figPage = (pages, label) => (pages.find((pg) => pg.items.some((x) => x.s.startsWith(label + '.'))) || {}).n;

// Hình chụp bị đẩy sang đầu trang sau trong khi cuối trang trước còn trống nhiều -> thu nhỏ vừa đủ
// (không dưới 66%) để hình ở lại trang trước, bớt những khoảng trắng lớn
function fitCandidate(pages, dom, tried) {
  for (const f of dom) {
    if (tried.has(f.id)) continue;
    const n = figPage(pages, f.label);
    if (!n || n < 2) continue;
    const pg = pages[n - 1], prev = pages[n - 2];
    if (!pg.items.length || !prev.items.length) continue;
    const line1 = pg.items.filter((x) => x.top < pg.items[0].top + 6); // dòng chữ đầu tiên của trang
    let need = f.figH;
    if (line1.some((x) => x.s.startsWith(f.label + '.'))) { /* hình là phần tử đầu trang */ }
    else if (f.head && line1.some((x) => f.head.keys.some((k) => x.s.startsWith(k)))) need += f.head.h; // tiêu đề đi kèm hình
    else continue;
    const free = (prev.H - 20 * MM - Math.max(...prev.items.map((x) => x.bot)) - 5 * MM) / 0.75;
    const over = need - free;
    const h = f.imgH - over;
    if (process.env.DEBUG) console.log(`  · ${f.label} đầu trang ${n}: cần ${Math.round(need)}px, trống ${Math.round(free)}px → ${Math.round(h / f.imgH * 100)}%`);
    if (over > 0 && free > 60 && h / f.imgH >= 0.66) return { id: f.id, label: f.label, h: Math.floor(h), s: h / f.imgH, page: n };
  }
  return null;
}

// Trang của từng tiêu đề (qua dấu trang của PDF) và của từng hình (tìm chú thích trong nội dung trang)
async function locate(pdfBytes, headingIds, figs) {
  const doc = await pdfjs.getDocument({ data: new Uint8Array(pdfBytes), useSystemFonts: false, disableFontFace: true, verbosity: 0 }).promise;
  const flat = [];
  const walk = async (items) => { for (const it of items || []) { flat.push(it); await walk(it.items); } };
  await walk(await doc.getOutline());
  const pages = {};
  for (let i = 0; i < flat.length && i < headingIds.length; i++) {
    const dest = typeof flat[i].dest === 'string' ? await doc.getDestination(flat[i].dest) : flat[i].dest;
    if (dest) pages[headingIds[i]] = (await doc.getPageIndex(dest[0])) + 1;
  }
  if (flat.length !== headingIds.length) console.warn(`Cảnh báo: ${flat.length} dấu trang / ${headingIds.length} tiêu đề`);
  const first = pages[ch[0].id] || 1;
  for (let n = first; n <= doc.numPages; n++) {
    const tc = await (await doc.getPage(n)).getTextContent();
    const text = tc.items.map((x) => x.str).join(' ').replace(/\s+/g, ' ');
    for (const f of figs) if (!pages[f.id] && text.includes(f.label + '.')) pages[f.id] = n;
  }
  return { pages, numPages: doc.numPages };
}

// Bản gửi: không còn chữ hay liên kết nào dẫn tới kho mã nguồn; không còn liên kết ra ngoài tài liệu
async function checkShare(bytes) {
  const doc = await pdfjs.getDocument({ data: new Uint8Array(bytes), useSystemFonts: false, disableFontFace: true, verbosity: 0 }).promise;
  const hits = [];
  for (let n = 1; n <= doc.numPages; n++) {
    const pg = await doc.getPage(n);
    const items = (await pg.getTextContent()).items.map((x) => x.str);
    if (REPO_RE.test(items.join('')) || REPO_RE.test(items.join(' '))) hits.push(`trang ${n}: chữ`);
    for (const a of await pg.getAnnotations()) if (a.url || a.unsafeUrl) hits.push(`trang ${n}: liên kết ${a.url || a.unsafeUrl}`);
  }
  if (hits.length) throw new Error('Bản gửi chưa sạch:\n  ' + hits.join('\n  '));
}

async function main() {
  ensureFonts();
  fs.mkdirSync(BUILD, { recursive: true });
  const browser = await chromium.launch();
  const p = await browser.newPage({ viewport: { width: 665, height: 1000 } });
  await p.emulateMedia({ media: 'print' });
  const render = async (tocPages, figs, shrink = {}) => {
    fs.writeFileSync(path.join(BUILD, 'guide.html'), page(tocHtml(tocPages), lofHtml(figs, tocPages)));
    await p.goto(fileUrl(path.join(BUILD, 'guide.html')), { waitUntil: 'load' });
    const f = await prepare(p);
    const dom = await measure(p, shrink);
    const ids = await p.evaluate(() => [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].map((h) => h.id));
    // Phần tử tràn ngang sẽ khiến Chromium thu nhỏ cả tài liệu -> báo ngay
    const over = await p.evaluate(() => {
      const W = document.documentElement.clientWidth + 2, out = [];
      document.querySelectorAll('body *').forEach((el) => {
        if (el.closest('.cover')) return;
        const r = el.getBoundingClientRect(), pr = el.parentElement.getBoundingClientRect();
        if (r.right > W && pr.right <= W) out.push(el.tagName + '.' + el.className + ' «' + el.textContent.trim().slice(0, 50) + '»');
      });
      return out;
    });
    if (over.length) console.warn('Cảnh báo – phần tử tràn ngang:\n  ' + over.slice(0, 10).join('\n  '));
    if (SHARE) {
      const leak = await p.evaluate((src) => { const m = new RegExp('.{0,40}(' + src + ').{0,40}', 'i').exec(document.body.innerText); return m && m[0]; }, REPO_RE.source);
      if (leak) throw new Error('Bản gửi còn nhắc tới kho mã nguồn: «' + leak + '»');
    }
    const pdf = await p.pdf({ preferCSSPageSize: true, printBackground: true, outline: true, tagged: true });
    return { pdf, figs: f, ids, dom };
  };
  // Lượt 1: số trang tạm (000) để đo; lượt 1b: danh mục hình có đủ dòng như bản cuối
  const r1 = await render({}, []);
  const figs = r1.figs;
  let cur = await render({}, figs);
  // Thu nhỏ dần từng hình (mỗi lượt một hình, từ đầu tài liệu) rồi dựng lại để xem có vừa trang trước không
  const shrink = {}, tried = new Set();
  let pages = await textPages(cur.pdf);
  for (let i = 0; i < 20; i++) {
    const c = fitCandidate(pages, cur.dom, tried);
    if (!c) break;
    tried.add(c.id);
    const next = await render({}, figs, { ...shrink, [c.id]: c.h });
    const np = await textPages(next.pdf);
    if (figPage(np, c.label) === c.page - 1) {
      shrink[c.id] = c.h; cur = next; pages = np;
      console.log(`  ${c.label}: thu còn ${Math.round(c.s * 100)}% để nằm cuối trang ${c.page - 1}`);
    } else if (process.env.DEBUG) console.log(`  (thử ${c.label} ${Math.round(c.s * 100)}% – vẫn ở trang ${figPage(np, c.label)})`);
  }
  const loc = await locate(cur.pdf, cur.ids, figs);
  const r2 = await render(loc.pages, figs, shrink);
  const chk = await locate(r2.pdf, r2.ids, r2.figs);
  const moved = Object.keys(loc.pages).filter((k) => loc.pages[k] !== chk.pages[k]);
  if (moved.length) console.warn('Cảnh báo: số trang thay đổi ở', moved.slice(0, 8));
  await browser.close();

  fs.writeFileSync(path.join(BUILD, 'raw.pdf'), r2.pdf);
  const out = await PDFDocument.load(r2.pdf);
  out.setTitle(META.title, { showInWindowTitleBar: true });
  out.setAuthor(META.author);
  out.setSubject('Hướng dẫn cài đặt và sử dụng – ' + META.org);
  // pdf-lib nối các từ khóa bằng dấu cách -> tự nối bằng "; " để giữ nguyên từng cụm
  out.setKeywords([['thi trắc nghiệm', 'tốt nghiệp THPT 2025', 'phiếu trả lời', 'hướng dẫn sử dụng', 'Trương Anh Tuấn', 'THPT chuyên Lương Thế Vinh'].join('; ')]);
  out.setCreator('tools/pdf-guide (Chromium)');
  out.setProducer('Hệ thống thi trắc nghiệm ' + VERSION);
  out.setLanguage('vi-VN');
  out.setCreationDate(now);
  out.setModificationDate(now);
  out.catalog.set(PDFName.of('PageMode'), PDFName.of('UseOutlines'));
  const bytes = await out.save();
  if (SHARE) await checkShare(bytes);
  fs.writeFileSync(OUT, bytes);
  console.log(`Đã tạo ${path.relative(ROOT, OUT)} · ${chk.numPages} trang · ${(fs.statSync(OUT).size / 1048576).toFixed(1)} MB · ${r2.figs.length} hình/sơ đồ`);
}
main().catch((e) => { console.error(e); process.exit(1); });

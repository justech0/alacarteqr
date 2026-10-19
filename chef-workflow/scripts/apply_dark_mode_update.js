const fs = require('fs');

const files = ['index.html', 'chef-onizleme.html', 'public_html/index.html'];

files.forEach(filePath => {
  let content = fs.readFileSync(filePath, 'utf8');

  // 1. Clean up old line 11 dark mode snippets if present
  content = content.replace(
    'html[data-theme=dark] .card-photo .photo-fallback{background:radial-gradient(circle at center,#243528 0%,var(--image) 85%);color:var(--green)}html[data-theme=dark] .photo-fallback svg{filter:drop-shadow(0 2px 8px rgba(150,219,160,0.18))}.',
    '.'
  );
  content = content.replace(
    'html[data-theme=dark]{color-scheme:dark;--paper:#161c18;--ink:#f0f3ee;--muted:#b4c1b6;--green:#237e30;--soft:#1a2e1e;--line:#354139;--image:#253027}html[data-theme=dark] .primary-button{background:#237e30;color:#fff}html[data-theme=dark] .primary-button:hover{background:#36a647}html[data-theme=dark] .brand{padding:2px}html[data-theme=dark] .brand img{width:84px;height:84px}',
    ''
  );

  // 2. Replace lines 189-263 (/* ── 7. Dark Mode Polish ── */ block)
  const oldDarkStart = content.indexOf('/* ── 7. Dark Mode Polish ── */');
  if (oldDarkStart !== -1) {
    const oldDarkEnd = content.indexOf('@media (prefers-reduced-motion: reduce)', oldDarkStart);
    if (oldDarkEnd !== -1) {
      const newDarkBlock = `/* ── 7. Dark Mode Architecture ── */
html[data-theme=dark] {
  color-scheme: dark;
  --paper: #0F1210;
  --surface: #171B18;
  --surface-2: #1E231F;
  --dialog: #1B201C;
  --line: #2A312B;
  --ink: #F1F3EE;
  --muted: #9BA59C;
  --green: #5BC46C;
  --green-solid: #1F7A2E;
  --green-solid-hover: #268A36;
  --soft: #16231A;
  --image: #1E231F;
  --danger: #F0645B;
}

html[data-theme=dark] .header {
  background: rgba(15, 18, 16, 0.88);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--line);
}

html[data-theme=dark] .category-nav {
  background: rgba(15, 18, 16, 0.95) !important;
  border-bottom: 1px solid var(--line) !important;
}

html[data-theme=dark] .product-card {
  background: var(--surface);
  border: 1px solid var(--line);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
}

html[data-theme=dark] .product-card:hover {
  background: var(--surface-2);
  border-color: var(--line);
}

html[data-theme=dark] .card-photo {
  background: var(--image);
  border-color: var(--line);
}

html[data-theme=dark] .card-photo .photo-fallback {
  background: radial-gradient(circle at center, var(--soft) 0%, var(--image) 85%);
  color: var(--green);
}

html[data-theme=dark] .card-photo .photo-fallback svg {
  filter: drop-shadow(0 2px 8px rgba(91, 196, 108, 0.2));
}

html[data-theme=dark] .search input,
html[data-theme=dark] .choice {
  background: var(--surface-2);
  border: 1px solid var(--line);
  color: var(--ink);
}

html[data-theme=dark] .search input:focus {
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(91, 196, 108, 0.2);
}

html[data-theme=dark] .choice:focus-within,
html[data-theme=dark] .choice:has(input:checked) {
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(91, 196, 108, 0.2);
}

html[data-theme=dark] .category-button[aria-pressed=false] .category-circle {
  background: var(--surface);
  border: 3px solid var(--paper);
  outline: 1px solid var(--line);
  color: var(--muted);
}

html[data-theme=dark] .category-button[aria-pressed=false] {
  color: var(--muted);
}

html[data-theme=dark] .category-button[aria-pressed=true] .category-circle {
  background: var(--green-solid) !important;
  outline: 2.5px solid var(--green) !important;
  border-color: transparent !important;
  color: #ffffff !important;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4) !important;
}

html[data-theme=dark] .category-button[aria-pressed=true] {
  color: #ffffff !important;
}

html[data-theme=dark] .category-button.is-scroll-active .category-circle {
  outline-color: var(--green);
}

html[data-theme=dark] .dialog {
  background: var(--dialog);
  border: 1px solid var(--line);
  box-shadow: 0 24px 50px rgba(0, 0, 0, 0.7);
  color: var(--ink);
}

html[data-theme=dark] .dialog::backdrop {
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}

html[data-theme=dark] .feedback-fab {
  background: var(--green-solid);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.45);
}

html[data-theme=dark] .feedback-fab:hover {
  background: var(--green-solid-hover);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.55);
}

html[data-theme=dark] .primary-button {
  background: var(--green-solid);
  color: #ffffff;
}

html[data-theme=dark] .primary-button:hover {
  background: var(--green-solid-hover);
}

html[data-theme=dark] .icon-button:hover {
  background: var(--surface-2);
  color: var(--green);
}

html[data-theme=dark] .star-btn {
  color: #3A423B;
}

html[data-theme=dark] .star-btn.is-filled {
  color: var(--star-color);
}

html[data-theme=dark] .star-rating-row {
  --star-color: var(--green);
}

html[data-theme=dark] .star-rating-row[data-level="1"] {
  --star-color: var(--danger);
}

html[data-theme=dark] .choice input:checked + span {
  background: var(--soft);
  color: var(--green);
  border-color: var(--green);
}

html[data-theme=dark] .close-button {
  background: var(--surface-2);
  color: var(--ink);
  border: 1px solid var(--line);
}

html[data-theme=dark] .compact-dialog dl {
  border-color: var(--line);
}

html[data-theme=dark] .compact-dialog dd {
  color: var(--ink);
}

html[data-theme=dark] .campaign {
  background: var(--soft);
  color: var(--ink);
}

html[data-theme=dark] .dialog-symbol {
  background: var(--soft);
  color: var(--green);
}

html[data-theme=dark] .detail-price {
  color: var(--green);
}

html[data-theme=dark] .toast {
  background: var(--surface-2);
  color: var(--ink);
  border: 1px solid var(--line);
}
`;
      content = content.substring(0, oldDarkStart) + newDarkBlock + content.substring(oldDarkEnd);
    }
  }

  // 3. Update LOGO in dark mode: .header-inner .brand -> background: #ffffff !important, border: none, halo box-shadow
  content = content.replace(
    /html\[data-theme=dark\] \.header-inner \.brand\s*\{[^}]*\}/g,
    `html[data-theme=dark] .header-inner .brand {
  background: #ffffff !important;
  border: none !important;
  box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.06), 0 6px 20px rgba(0, 0, 0, 0.5) !important;
}`
  );

  // 4. Update .price in dark mode
  content = content.replace(
    /html\[data-theme=dark\] \.price\s*\{[^}]*\}/g,
    `html[data-theme=dark] .price {
  border-color: var(--line) !important;
  background: transparent !important;
  color: var(--ink) !important;
}`
  );

  // 5. Update targetBg in JS theme ripple: "#121814" -> "#0F1210"
  content = content.replace(
    'let targetBg = nextTheme === "dark" ? "#121814" : "#faf8f5";',
    'let targetBg = nextTheme === "dark" ? "#0F1210" : "#faf8f5";'
  );

  // 6. Update Re() function to update meta theme-color dynamically
  const oldRe = 'function Re(){document.documentElement.dataset.theme=n.theme;';
  const newRe = 'function Re(){document.documentElement.dataset.theme=n.theme;document.querySelector(\'meta[name="theme-color"]\')?.setAttribute("content",n.theme==="dark"?"#0F1210":"#176023");';
  content = content.replace(oldRe, newRe);

  // Validate script syntax
  const scriptMatch = content.match(/<script>([\s\S]*?)<\/script>/);
  if (scriptMatch) {
    try {
      new Function(scriptMatch[1]);
    } catch (err) {
      console.error('Syntax error in ' + filePath + ':', err);
      process.exit(1);
    }
  }

  fs.writeFileSync(filePath, content, 'utf8');
  console.log('Successfully updated ' + filePath);
});

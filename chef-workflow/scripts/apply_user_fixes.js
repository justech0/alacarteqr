const fs = require('fs');

const files = ['index.html', 'chef-onizleme.html', 'public_html/index.html'];

files.forEach(filePath => {
  let content = fs.readFileSync(filePath, 'utf8');

  // 1. Update --header in :root
  content = content.replace('--header:114px', '--header:85px');

  // 2. Remove "Tümü" from qe()
  content = content.replace(
    '[{id:null,name:{tr:l("all"),en:l("all"),ar:l("all")}},...h.categories]',
    'h.categories'
  );
  content = content.replace(
    '[{ id: null, name: { tr: l("all"), en: l("all"), ar: l("all") } }, ...h.categories]',
    'h.categories'
  );

  // 3. Set initial category to "et" in var n
  content = content.replace('category:null,query:""', 'category:"et",query:""');

  // 4. Update touch swipe categories array (remove null)
  content = content.replace(
    'let allCats = [null, ...h.categories.map(c => c.id)];',
    'let allCats = h.categories.map(c => c.id);'
  );

  // 5. Update or append CSS overrides
  const newStyles = `
/* ── SECTION HEADING REMOVED ── */
.section-heading {
  display: none !important;
}

/* ── STICKY CATEGORY NAV (ZERO GAP BENEATH HEADER) ── */
.category-nav {
  position: sticky !important;
  top: 85px !important;
  z-index: 9 !important;
  background: var(--paper) !important;
  border-bottom: 1px solid var(--line) !important;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

/* ── ELEVATED HOVER EFFECT & REMOVED ACTIVE DOT ── */
.category-button {
  transition: color .2s ease, transform .22s cubic-bezier(0.2, 0.8, 0.2, 1) !important;
}
.category-button:hover {
  transform: translateY(-4px) !important;
  color: var(--green) !important;
}
.category-button:hover .category-circle {
  transform: translateY(-5px) scale(1.1) !important;
  outline-color: var(--green) !important;
  box-shadow: 0 8px 20px rgba(23, 96, 35, 0.22) !important;
}
.category-button[aria-pressed=true]:after,
.category-button.is-scroll-active:after {
  display: none !important;
  content: none !important;
}

/* ── COMPACT FOOTER (NO EXCESS SPACE) ── */
footer {
  color: var(--muted);
  font-size: 11px;
  line-height: 1.5 !important;
  padding: 14px 0 16px !important;
  margin-top: 14px !important;
  border-top: 1px solid var(--line);
}
footer p {
  margin: 0 0 6px !important;
}
footer > div {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 6px !important;
}
footer .preview-note {
  margin: 0 !important;
}
`;

  // Place before </style>
  if (content.includes('/* ── SECTION HEADING REMOVED ── */')) {
    const startIdx = content.indexOf('/* ── SECTION HEADING REMOVED ── */');
    const endIdx = content.indexOf('</style>', startIdx);
    content = content.substring(0, startIdx) + newStyles + content.substring(endIdx);
  } else {
    content = content.replace('</style>', newStyles + '\n</style>');
  }

  // Check script syntax
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

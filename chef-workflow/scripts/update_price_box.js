const fs = require('fs');

const files = ['index.html', 'chef-onizleme.html', 'public_html/index.html'];

const newPriceBlock = `/* ── ORIGINAL BOX SHAPE PRICE BADGE (CENTERED) ── */
.card-body {
  padding-top: 12px !important;
  display: flex !important;
  flex-direction: column !important;
  flex: 1 !important;
}
.card-body h3 {
  font-size: 15px !important;
  line-height: 1.35 !important;
  letter-spacing: -0.2px;
  font-weight: 600;
  margin: 0 0 6px !important;
}
.card-body p {
  color: var(--muted);
  font-size: 12px !important;
  line-height: 1.5 !important;
  margin: 0 0 10px !important;
}
.card-bottom {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  text-align: center !important;
  gap: 6px !important;
  margin-top: auto !important;
  width: 100% !important;
  padding-top: 4px !important;
}
.price {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  border: 1px solid var(--line) !important;
  border-radius: 4px !important;
  padding: 5px 12px !important;
  font-size: 15px !important;
  font-weight: 600 !important;
  line-height: 1.15 !important;
  white-space: nowrap !important;
  font-variant-numeric: tabular-nums !important;
  text-align: center !important;
  background: transparent !important;
  color: var(--ink) !important;
  transition: transform .14s ease, border-color .14s ease !important;
}
.price b {
  font-size: 11px !important;
  font-weight: 500 !important;
  margin-inline-start: 3px !important;
}
html[data-theme=dark] .price {
  border-color: var(--line) !important;
  background: transparent !important;
  color: var(--ink) !important;
}
`;

files.forEach(f => {
  let content = fs.readFileSync(f, 'utf8');
  let startIdx = content.indexOf('/* ── REFINED & LIFTED PRICE BADGE ── */');
  if (startIdx === -1) {
    startIdx = content.indexOf('/* ── RESTORED ORIGINAL BOX SHAPE PRICE BADGE ── */');
  }
  if (startIdx === -1) {
    startIdx = content.indexOf('/* ── ORIGINAL BOX SHAPE PRICE BADGE (CENTERED) ── */');
  }
  if (startIdx !== -1) {
    let endIdx = content.indexOf('</style>', startIdx);
    content = content.substring(0, startIdx) + newPriceBlock + content.substring(endIdx);
    fs.writeFileSync(f, content, 'utf8');
    console.log('Successfully updated', f);
  } else {
    console.log('Target block not found in', f);
  }
});

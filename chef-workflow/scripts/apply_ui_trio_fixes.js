const fs = require('fs');
const path = require('path');

const files = [
  path.join(__dirname, '../../index.html'),
  path.join(__dirname, '../../chef-onizleme.html'),
  path.join(__dirname, '../../public_html/index.html')
];

const FLAG_SVGS = {
  tr: '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><defs><clipPath id="flag-tr-clip"><circle cx="12" cy="12" r="12"/></clipPath></defs><g clip-path="url(#flag-tr-clip)"><rect width="24" height="24" fill="#E30A17"/><circle cx="9.5" cy="12" r="5.5" fill="#fff"/><circle cx="11.2" cy="12" r="4.4" fill="#E30A17"/><polygon points="14.8,12 18,13.1 16.8,9.7 16.8,14.3 18,10.9" fill="#fff"/></g></svg>',
  en: '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><defs><clipPath id="flag-en-clip"><circle cx="12" cy="12" r="12"/></clipPath></defs><g clip-path="url(#flag-en-clip)"><path d="M0 0h24v24H0z" fill="#012169"/><path d="M0 0l24 24m0-24L0 24" stroke="#fff" stroke-width="4"/><path d="M0 0l24 24m0-24L0 24" stroke="#C8102E" stroke-width="2"/><path d="M12 0v24M0 12h24" stroke="#fff" stroke-width="6"/><path d="M12 0v24M0 12h24" stroke="#C8102E" stroke-width="3.6"/></g></svg>',
  ar: '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><defs><clipPath id="flag-ar-clip"><circle cx="12" cy="12" r="12"/></clipPath></defs><g clip-path="url(#flag-ar-clip)"><rect width="24" height="24" fill="#006C35"/><path d="M5.5 16h13m0 0l-1.5-1.5m1.5 1.5l-1.5 1.5M18.5 14.5v3" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 8.5h14M6.5 6.5h11M7.5 10.5h9" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-dasharray="1.5 1.5"/></g></svg>'
};

files.forEach(filePath => {
  if (!fs.existsSync(filePath)) {
    console.warn('File not found, skipping:', filePath);
    return;
  }
  let content = fs.readFileSync(filePath, 'utf8');

  // ── FIX 1 & 2 CSS UPDATES ──

  // Add --surface, --surface-2, --dialog to :root if missing
  if (content.includes(':root{color-scheme:light;--paper:#fff;') && !content.includes('--surface-2:#f2f5f2;')) {
    content = content.replace(
      ':root{color-scheme:light;--paper:#fff;',
      ':root{color-scheme:light;--paper:#fff;--surface:#f7f9f7;--surface-2:#f2f5f2;--dialog:#ffffff;'
    );
  }

  // Update base .search rule in line 11
  content = content.replace(
    '.search{height:46px;border:1px solid var(--line);border-radius:6px;display:flex;align-items:center;padding:0 12px;gap:9px;background:var(--paper)}',
    '.search{height:48px;border:1px solid var(--line);border-radius:12px;display:flex;align-items:center;padding:0 14px;gap:10px;background:var(--surface-2)}'
  );

  // Update base .search:focus-within rule in line 11
  content = content.replace(
    '.search:focus-within{outline:2px solid var(--green);outline-offset:2px}',
    '.search:focus-within{border-color:var(--green);box-shadow:0 0 0 3px rgba(91,196,108,.18);outline:none}'
  );

  // Update base .card-body rule in line 11
  content = content.replace(
    '.card-body{padding-top:12px;display:flex;flex-direction:column;flex:1}',
    '.card-body{padding:12px 12px 14px;display:flex;flex-direction:column;flex:1}'
  );

  // Remove .search input rules from html[data-theme=dark]
  content = content.replace(
    /html\[data-theme=dark\] \.search input,\s*html\[data-theme=dark\] \.choice\s*\{[^}]*\}/,
    `html[data-theme=dark] .choice {\n  background: var(--surface-2);\n  border: 1px solid var(--line);\n  color: var(--ink);\n}`
  );

  content = content.replace(
    /html\[data-theme=dark\] \.search input:focus\s*\{[^}]*\}/,
    ''
  );

  // Replace price badge and card body CSS block
  const oldPriceBlockStart = content.indexOf('/* ── ORIGINAL BOX SHAPE PRICE BADGE (CENTERED) ── */');
  if (oldPriceBlockStart !== -1) {
    const oldPriceBlockEnd = content.indexOf('/* ── SECTION HEADING REMOVED ── */', oldPriceBlockStart);
    if (oldPriceBlockEnd !== -1) {
      const newPriceBlock = `/* ── PRODUCT CARD, CARD BODY & PRICE BADGE (REFINED PADDING & SIZING) ── */
.product-card {
  overflow: hidden !important;
  border-radius: 8px !important;
}
.card-body {
  padding: 12px 12px 14px !important;
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
  margin-bottom: 0 !important;
  width: 100% !important;
  padding-top: 4px !important;
}
.price {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  border: 1px solid var(--line) !important;
  border-radius: 6px !important;
  padding: 6px 14px !important;
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
html[data-theme=dark] .card-photo {
  border: none !important;
  border-bottom: 1px solid var(--line) !important;
  border-radius: 0 !important;
}
html[data-theme=dark] .price {
  border-color: var(--line) !important;
  background: transparent !important;
  color: var(--ink) !important;
}
`;
      content = content.substring(0, oldPriceBlockStart) + newPriceBlock + content.substring(oldPriceBlockEnd);
    }
  }

  // Remove existing refined search styles block if present to allow clean re-application
  const existingRefinedStart = content.indexOf('/* ── REFINED SINGLE-BOX SEARCH ── */');
  if (existingRefinedStart !== -1) {
    const existingRefinedEnd = content.indexOf('</style>', existingRefinedStart);
    if (existingRefinedEnd !== -1) {
      content = content.substring(0, existingRefinedStart) + content.substring(existingRefinedEnd);
    }
  }

  // Add refined single-box search & language popover styles right before </style>
  const styleEndIdx = content.indexOf('</style>');
  if (styleEndIdx !== -1) {
    const extraStyles = `
/* ── REFINED SINGLE-BOX SEARCH ── */
.search {
  height: 48px !important;
  border: 1px solid var(--line) !important;
  border-radius: 12px !important;
  display: flex !important;
  align-items: center !important;
  padding: 0 14px !important;
  gap: 10px !important;
  background: var(--surface-2) !important;
  box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03) !important;
  transition: border-color .18s ease, box-shadow .18s ease !important;
}
html[data-theme=dark] .search {
  background: var(--surface-2) !important;
  border-color: var(--line) !important;
  box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.22) !important;
}
.search:focus-within {
  border-color: var(--green) !important;
  box-shadow: 0 0 0 3px rgba(91, 196, 108, 0.18) !important;
  outline: none !important;
}
.search > span {
  color: var(--muted) !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  transition: color .18s ease !important;
}
.search:focus-within > span {
  color: var(--green) !important;
}
.search input {
  background: transparent !important;
  border: 0 !important;
  box-shadow: none !important;
  outline: none !important;
  min-width: 0 !important;
  flex: 1 !important;
  height: 100% !important;
  font-size: 15px !important;
  color: var(--ink) !important;
  padding: 0 !important;
}
.search input:focus,
.search input:focus-visible {
  outline: none !important;
  box-shadow: none !important;
  border: 0 !important;
}
.search input::placeholder {
  color: var(--muted) !important;
  opacity: 0.8 !important;
}
.search .icon-button {
  width: 32px !important;
  height: 32px !important;
  color: var(--muted) !important;
  border-radius: 50% !important;
  padding: 0 !important;
  margin-inline-start: auto !important;
  flex-shrink: 0 !important;
}
.search .icon-button:hover {
  color: var(--ink) !important;
  background: var(--soft) !important;
}

/* ── CUSTOM LANGUAGE POPOVER & FLAG BADGE ── */
.header-left {
  z-index: 12 !important;
}
.language-dropdown {
  position: relative !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  z-index: 12 !important;
}
.lang-btn-content {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
}
.lang-globe-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}
.lang-globe-icon svg {
  width: 19px;
  height: 19px;
}
.lang-current-flag {
  position: absolute;
  bottom: -3px;
  inset-inline-end: -4px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  box-shadow: 0 0 0 1.5px var(--paper);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  transition: transform .18s ease;
}
html[data-theme=dark] .lang-current-flag {
  box-shadow: 0 0 0 1.5px var(--surface);
}
.lang-current-flag svg {
  width: 100%;
  height: 100%;
  display: block;
}
.lang-popover {
  position: absolute;
  top: calc(100% + 8px);
  inset-inline-start: 0;
  min-width: 148px;
  background: var(--dialog, var(--paper));
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.28);
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  z-index: 100;
  opacity: 0;
  transform: translateY(-4px);
  pointer-events: none;
  transition: opacity 140ms ease, transform 140ms ease;
}
html[data-theme=dark] .lang-popover {
  background: var(--dialog);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.65);
}
.lang-popover.is-open {
  opacity: 1;
  transform: translateY(0);
  pointer-events: auto;
}
@media (prefers-reduced-motion: reduce) {
  .lang-popover {
    transition: none !important;
    transform: none !important;
  }
}
.lang-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  background: transparent;
  border: 0;
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 500;
  text-align: start;
  width: 100%;
  cursor: pointer;
  transition: background-color .15s ease, color .15s ease;
  user-select: none;
  outline: none;
}
.lang-option:hover,
.lang-option:focus-visible {
  background: var(--soft);
  color: var(--green);
  outline: 2px solid var(--green);
  outline-offset: -2px;
}
.lang-option[aria-selected="true"] {
  background: var(--soft);
  color: var(--green);
  font-weight: 600;
}
.lang-flag {
  width: 22px;
  height: 22px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.lang-flag svg {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: block;
}
.lang-name {
  flex: 1;
  white-space: nowrap;
}
.lang-check {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  color: var(--green);
  opacity: 0;
}
.lang-option[aria-selected="true"] .lang-check {
  opacity: 1;
}
.lang-check svg {
  width: 14px;
  height: 14px;
}
`;
    content = content.substring(0, styleEndIdx) + extraStyles + content.substring(styleEndIdx);
  }

  // ── UPDATE SEARCH PLACEHOLDER & TRANSLATIONS ──
  // Static HTML
  content = content.replace(
    'aria-label="Menüde ara" placeholder="Menüde ara"',
    'aria-label="Menüde lezzet ara" placeholder="Menüde lezzet ara"'
  );

  // Translations
  content = content.replace('search:"Men\\xFCde ara"', 'search:"Men\\xFCde lezzet ara"');
  content = content.replace('search:"Menüde ara"', 'search:"Menüde lezzet ara"');
  content = content.replace('search:"Search the menu"', 'search:"Search the menu for a treat"');
  content = content.replace(
    'search:"\\u0627\\u0628\\u062D\\u062B \\u0641\\u064A \\u0627\\u0644\\u0642\\u0627\\u0626\\u0645\\u0629"',
    'search:"\\u0627\\u0628\\u062D\\u062B \\u0639\\u0646 \\u0646\\u0643\\u0647\\u062A\\u0643 \\u0641\\u064A \\u0627\\u0644\\u0642\\u0627\\u0626\\u0645\\u0629"'
  );

  // ── UPDATE LANGUAGE SELECTOR IN HTML ──
  const oldLangDropdown = `<div class="language-dropdown">
          <button class="icon-button" id="language-button" type="button" aria-label="Dil seçimi" title="Dil"><span data-icon="globe"></span></button>
          <select id="language" class="language-select-overlay" aria-label="Dil seçin">
            <option value="tr">TR · Türkçe</option>
            <option value="en">EN · English</option>
            <option value="ar">AR · العربية</option>
          </select>
        </div>`;

  const newLangDropdown = `<div class="language-dropdown" id="language-dropdown">
          <button class="icon-button" id="language-button" type="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Dil seçimi" title="Dil">
            <span class="lang-btn-content">
              <span data-icon="globe" class="lang-globe-icon"></span>
              <span class="lang-current-flag" id="lang-current-flag" aria-hidden="true"></span>
            </span>
          </button>
          <div class="lang-popover" id="lang-popover" role="listbox" aria-label="Dil seçin" hidden>
            <button type="button" class="lang-option" role="option" data-lang="tr" aria-selected="true" tabindex="0">
              <span class="lang-flag" aria-hidden="true" data-flag="tr"></span>
              <span class="lang-name">Türkçe</span>
              <span class="lang-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
            </button>
            <button type="button" class="lang-option" role="option" data-lang="en" aria-selected="false" tabindex="-1">
              <span class="lang-flag" aria-hidden="true" data-flag="en"></span>
              <span class="lang-name">English</span>
              <span class="lang-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
            </button>
            <button type="button" class="lang-option" role="option" data-lang="ar" aria-selected="false" tabindex="-1">
              <span class="lang-flag" aria-hidden="true" data-flag="ar"></span>
              <span class="lang-name">العربية</span>
              <span class="lang-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
            </button>
          </div>
          <input type="hidden" id="language" value="tr">
        </div>`;

  if (content.includes(oldLangDropdown)) {
    content = content.replace(oldLangDropdown, newLangDropdown);
  }

  // ── UPDATE JS FOR LANGUAGE POPOVER ──
  const oldLangChangeHandler = 'a("#language").addEventListener("change",e=>{n.lang=e.target.value,ze("chef-language",n.lang),Ke(),F(!0)});';
  const newLangJS = `
// ── LANGUAGE POPOVER LOGIC ──
const FLAG_SVGS = {
  tr: '${FLAG_SVGS.tr.replace(/'/g, "\\'")}',
  en: '${FLAG_SVGS.en.replace(/'/g, "\\'")}',
  ar: '${FLAG_SVGS.ar.replace(/'/g, "\\'")}'
};

function updateLangUI() {
  const currentFlagEl = a("#lang-current-flag");
  if (currentFlagEl) {
    currentFlagEl.innerHTML = FLAG_SVGS[n.lang] || FLAG_SVGS.tr;
  }
  P(".lang-flag[data-flag]").forEach(el => {
    const code = el.dataset.flag;
    if (FLAG_SVGS[code]) el.innerHTML = FLAG_SVGS[code];
  });
  P(".lang-option").forEach(opt => {
    const isSelected = opt.dataset.lang === n.lang;
    opt.setAttribute("aria-selected", String(isSelected));
    opt.setAttribute("tabindex", isSelected ? "0" : "-1");
  });
  const hiddenInput = a("#language");
  if (hiddenInput) hiddenInput.value = n.lang;
}

const langBtn = a("#language-button");
const langPopover = a("#lang-popover");

function openLangMenu() {
  if (!langPopover || !langBtn) return;
  langPopover.hidden = false;
  void langPopover.offsetWidth;
  langPopover.classList.add("is-open");
  langBtn.setAttribute("aria-expanded", "true");
  const selectedOpt = langPopover.querySelector('.lang-option[aria-selected="true"]') || langPopover.querySelector('.lang-option');
  selectedOpt?.focus();
}

function closeLangMenu(restoreFocus = false) {
  if (!langPopover || !langBtn) return;
  langPopover.classList.remove("is-open");
  langPopover.hidden = true;
  langBtn.setAttribute("aria-expanded", "false");
  if (restoreFocus) langBtn.focus();
}

function selectLanguage(lang) {
  if (!lang || lang === n.lang) {
    closeLangMenu(true);
    return;
  }
  n.lang = lang;
  ze("chef-language", n.lang);
  Ke();
  F(!0);
  closeLangMenu(true);
}

if (langBtn && langPopover) {
  langBtn.addEventListener("click", e => {
    e.stopPropagation();
    const isOpen = langBtn.getAttribute("aria-expanded") === "true";
    if (isOpen) {
      closeLangMenu(false);
    } else {
      openLangMenu();
    }
  });

  P(".lang-option").forEach(opt => {
    opt.addEventListener("click", () => {
      selectLanguage(opt.dataset.lang);
    });
  });

  langPopover.addEventListener("keydown", e => {
    const options = Array.from(P(".lang-option"));
    const currentIndex = options.indexOf(document.activeElement);

    if (e.key === "ArrowDown") {
      e.preventDefault();
      const nextIndex = (currentIndex + 1) % options.length;
      options[nextIndex].focus();
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      const prevIndex = (currentIndex - 1 + options.length) % options.length;
      options[prevIndex].focus();
    } else if (e.key === "Escape") {
      e.preventDefault();
      closeLangMenu(true);
    } else if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      if (document.activeElement?.dataset?.lang) {
        selectLanguage(document.activeElement.dataset.lang);
      }
    }
  });

  document.addEventListener("click", e => {
    if (!a("#language-dropdown")?.contains(e.target)) {
      closeLangMenu(false);
    }
  });

  document.addEventListener("keydown", e => {
    if (e.key === "Escape" && langBtn.getAttribute("aria-expanded") === "true") {
      closeLangMenu(true);
    }
  });
}
`;

  if (content.includes(oldLangChangeHandler)) {
    content = content.replace(oldLangChangeHandler, newLangJS);
  }

  // Hook updateLangUI into Ke()
  if (content.includes('function Ke(){') && !content.includes('updateLangUI();')) {
    content = content.replace('function Ke(){', 'function Ke(){updateLangUI();');
  }

  fs.writeFileSync(filePath, content, 'utf8');
  console.log('Successfully updated:', filePath);
});

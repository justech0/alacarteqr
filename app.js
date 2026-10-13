/**
 * Chef À La Carte — Application Logic
 * QR digital menu with search, categories, product details,
 * theme switching, language switching (TR/EN/AR), RTL, campaigns,
 * WiFi info, and feedback.
 */

(function () {
  'use strict';

  // ── State ────────────────────────────────────────────────────────
  const state = {
    lang: 'tr',
    theme: 'light',
    activeCategory: null,
    searchQuery: '',
    lastFocusedElement: null
  };

  const LANG_ORDER = ['tr', 'en', 'ar'];
  const LANG_LABELS = { tr: 'TR', en: 'EN', ar: 'AR' };
  const RTL_LANGS = new Set(['ar']);
  const CURRENCY = '₺';

  // ── DOM References ───────────────────────────────────────────────
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => document.querySelectorAll(sel);

  const dom = {
    header: $('#site-header'),
    logo: $('#header-logo'),
    themeToggle: $('#theme-toggle'),
    langToggle: $('#lang-toggle'),
    langLabel: $('#lang-label'),
    searchInput: $('#search-input'),
    searchClear: $('#search-clear'),
    categoryScroll: $('.category-scroll'),
    menuList: $('#menu-list'),
    emptyState: $('#empty-state'),
    emptyText: $('#empty-text'),
    campaignBanner: $('#campaign-banner'),
    campaignText: $('#campaign-text'),
    campaignClose: $('#campaign-close'),
    // Detail dialog
    detailOverlay: $('#detail-overlay'),
    detailDialog: $('#detail-dialog'),
    detailClose: $('#detail-close'),
    detailTitle: $('#detail-title'),
    detailImageContainer: $('#detail-image-container'),
    detailPrice: $('#detail-price'),
    detailCalories: $('#detail-calories'),
    detailDescription: $('#detail-description'),
    detailIngredients: $('#detail-ingredients'),
    // WiFi dialog
    wifiOverlay: $('#wifi-overlay'),
    wifiClose: $('#wifi-close'),
    wifiBtn: $('#wifi-btn'),
    wifiSsid: $('#wifi-ssid'),
    wifiPass: $('#wifi-pass'),
    // Feedback dialog
    feedbackOverlay: $('#feedback-overlay'),
    feedbackClose: $('#feedback-close'),
    feedbackBtn: $('#feedback-btn'),
    feedbackForm: $('#feedback-form'),
    feedbackTextarea: $('#feedback-textarea')
  };

  // ── Logo Scroll Animation ────────────────────────────────────────
  const SCROLL_ENTER_THRESHOLD = 80;
  const SCROLL_EXIT_THRESHOLD = 40;
  let isCompact = false;
  let logoLeftPos = 0;
  let logoCenterPos = 0;
  let ticking = false;
  let prefersReducedMotion = false;

  function measureLogoPositions() {
    const headerInner = dom.header.querySelector('.header-inner');
    const headerRect = headerInner.getBoundingClientRect();
    const logoRect = dom.logo.getBoundingClientRect();

    // Logo's natural left position (relative to header inner)
    logoLeftPos = 0;

    // Center position = center of header minus half logo width minus current logo left
    const headerCenter = headerRect.width / 2;
    const currentLogoLeft = logoRect.left - headerRect.left;
    const logoWidth = logoRect.width;
    logoCenterPos = headerCenter - currentLogoLeft - (logoWidth / 2);
  }

  function updateLogoPosition(scrollY) {
    if (scrollY > SCROLL_ENTER_THRESHOLD && !isCompact) {
      isCompact = true;
      dom.header.classList.add('is-compact');
      if (!prefersReducedMotion) {
        dom.logo.style.transform = `translateX(${logoCenterPos}px)`;
      } else {
        dom.logo.style.transform = `translateX(${logoCenterPos}px)`;
      }
    } else if (scrollY < SCROLL_EXIT_THRESHOLD && isCompact) {
      isCompact = false;
      dom.header.classList.remove('is-compact');
      dom.logo.style.transform = 'translateX(0)';
    }
  }

  function onScroll() {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(() => {
        updateLogoPosition(window.scrollY);
        ticking = false;
      });
    }
  }

  function onResize() {
    // Temporarily reset transform to measure accurately
    const prevTransform = dom.logo.style.transform;
    dom.logo.style.transform = 'translateX(0)';
    dom.header.classList.remove('is-compact');

    requestAnimationFrame(() => {
      measureLogoPositions();
      if (isCompact) {
        dom.header.classList.add('is-compact');
        dom.logo.style.transform = `translateX(${logoCenterPos}px)`;
      } else {
        dom.logo.style.transform = prevTransform;
      }
    });
  }

  // ── Theme ────────────────────────────────────────────────────────
  function setTheme(theme) {
    state.theme = theme;
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('chef-theme', theme);
  }

  function toggleTheme() {
    setTheme(state.theme === 'light' ? 'dark' : 'light');
  }

  // ── Language ─────────────────────────────────────────────────────
  function setLang(lang) {
    state.lang = lang;
    document.documentElement.lang = lang;
    dom.langLabel.textContent = LANG_LABELS[lang];

    // RTL
    if (RTL_LANGS.has(lang)) {
      document.documentElement.dir = 'rtl';
    } else {
      document.documentElement.dir = 'ltr';
    }

    // Update search placeholder
    const placeholders = {
      tr: 'Menüde ara…',
      en: 'Search menu…',
      ar: '…ابحث في القائمة'
    };
    dom.searchInput.placeholder = placeholders[lang] || placeholders.tr;

    // Update empty state text
    const emptyTexts = {
      tr: 'Sonuç bulunamadı',
      en: 'No results found',
      ar: 'لم يتم العثور على نتائج'
    };
    dom.emptyText.textContent = emptyTexts[lang] || emptyTexts.tr;

    localStorage.setItem('chef-lang', lang);

    renderCategories();
    renderMenu();
    updateCampaign();
  }

  function cycleLang() {
    const idx = LANG_ORDER.indexOf(state.lang);
    const next = LANG_ORDER[(idx + 1) % LANG_ORDER.length];
    setLang(next);
  }

  // ── Categories ───────────────────────────────────────────────────
  function renderCategories() {
    const cats = MENU_DATA.categories;
    dom.categoryScroll.innerHTML = '';

    // "All" button
    const allLabels = { tr: 'Tümü', en: 'All', ar: 'الكل' };
    const allBtn = document.createElement('button');
    allBtn.className = 'category-btn' + (state.activeCategory === null ? ' is-active' : '');
    allBtn.textContent = allLabels[state.lang] || allLabels.tr;
    allBtn.type = 'button';
    allBtn.setAttribute('aria-pressed', state.activeCategory === null);
    allBtn.addEventListener('click', () => {
      state.activeCategory = null;
      renderCategories();
      renderMenu();
    });
    dom.categoryScroll.appendChild(allBtn);

    cats.forEach((cat) => {
      const btn = document.createElement('button');
      btn.className = 'category-btn' + (state.activeCategory === cat.id ? ' is-active' : '');
      btn.textContent = cat.name[state.lang] || cat.name.tr;
      btn.type = 'button';
      btn.setAttribute('aria-pressed', state.activeCategory === cat.id);
      btn.addEventListener('click', () => {
        state.activeCategory = cat.id;
        renderCategories();
        renderMenu();
      });
      dom.categoryScroll.appendChild(btn);
    });
  }

  // ── Menu Rendering ───────────────────────────────────────────────
  function getLocalizedText(obj) {
    if (!obj) return '';
    return obj[state.lang] || obj.tr || '';
  }

  function filterItems() {
    let items = MENU_DATA.items;

    // Category filter
    if (state.activeCategory) {
      items = items.filter(item => item.category === state.activeCategory);
    }

    // Search filter
    if (state.searchQuery) {
      const q = state.searchQuery.toLowerCase();
      items = items.filter(item => {
        const name = getLocalizedText(item.name).toLowerCase();
        const desc = getLocalizedText(item.description).toLowerCase();
        return name.includes(q) || desc.includes(q);
      });
    }

    return items;
  }

  function createItemElement(item) {
    const el = document.createElement('article');
    el.className = 'menu-item';
    el.setAttribute('role', 'button');
    el.setAttribute('tabindex', '0');
    el.setAttribute('aria-label', getLocalizedText(item.name) + ' — ' + item.price + ' ' + CURRENCY);

    // Image placeholder
    const imgEl = document.createElement('div');
    imgEl.className = 'item-image';
    imgEl.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M18 8h1a4 4 0 0 1 0 8h-1"/>
      <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>
      <line x1="6" y1="1" x2="6" y2="4"/>
      <line x1="10" y1="1" x2="10" y2="4"/>
      <line x1="14" y1="1" x2="14" y2="4"/>
    </svg>`;

    // Content
    const contentEl = document.createElement('div');
    contentEl.className = 'item-content';

    const nameEl = document.createElement('h3');
    nameEl.className = 'item-name';
    nameEl.textContent = getLocalizedText(item.name);

    const descEl = document.createElement('p');
    descEl.className = 'item-description';
    descEl.textContent = getLocalizedText(item.description);

    contentEl.appendChild(nameEl);
    contentEl.appendChild(descEl);

    // Calories
    if (item.calories !== null && item.calories !== undefined) {
      const calEl = document.createElement('span');
      calEl.className = 'item-calories';
      const calLabels = { tr: 'kcal', en: 'kcal', ar: 'سعرة' };
      calEl.textContent = item.calories + ' ' + (calLabels[state.lang] || 'kcal');
      contentEl.appendChild(calEl);
    }

    // Price
    const priceEl = document.createElement('span');
    priceEl.className = 'item-price';
    priceEl.innerHTML = item.price + '<span class="item-currency">' + CURRENCY + '</span>';

    el.appendChild(imgEl);
    el.appendChild(contentEl);
    el.appendChild(priceEl);

    // Click handler
    const openDetail = () => openProductDetail(item);
    el.addEventListener('click', openDetail);
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openDetail();
      }
    });

    return el;
  }

  function renderMenu() {
    const items = filterItems();
    dom.menuList.innerHTML = '';

    if (items.length === 0) {
      dom.emptyState.hidden = false;
      return;
    }

    dom.emptyState.hidden = true;

    if (state.activeCategory) {
      // Single category — no group headers
      items.forEach(item => {
        dom.menuList.appendChild(createItemElement(item));
      });
    } else {
      // Group by category
      const groups = new Map();
      items.forEach(item => {
        if (!groups.has(item.category)) {
          groups.set(item.category, []);
        }
        groups.get(item.category).push(item);
      });

      MENU_DATA.categories.forEach(cat => {
        const catItems = groups.get(cat.id);
        if (!catItems || catItems.length === 0) return;

        const groupEl = document.createElement('div');
        groupEl.className = 'category-group';

        const titleEl = document.createElement('h2');
        titleEl.className = 'category-group-title';
        titleEl.textContent = getLocalizedText(cat.name);

        groupEl.appendChild(titleEl);
        catItems.forEach(item => {
          groupEl.appendChild(createItemElement(item));
        });

        dom.menuList.appendChild(groupEl);
      });
    }
  }

  // ── Product Detail Dialog ────────────────────────────────────────
  function openProductDetail(item) {
    state.lastFocusedElement = document.activeElement;

    dom.detailTitle.textContent = getLocalizedText(item.name);
    dom.detailPrice.innerHTML = item.price + ' <span class="item-currency">' + CURRENCY + '</span>';

    // Calories
    if (item.calories !== null && item.calories !== undefined) {
      const calLabels = { tr: 'kcal', en: 'kcal', ar: 'سعرة' };
      dom.detailCalories.textContent = item.calories + ' ' + (calLabels[state.lang] || 'kcal');
      dom.detailCalories.hidden = false;
    } else {
      dom.detailCalories.textContent = '';
      dom.detailCalories.hidden = true;
    }

    // Description
    dom.detailDescription.textContent = getLocalizedText(item.description);

    // Image
    dom.detailImageContainer.innerHTML = '';
    if (item.image) {
      const img = document.createElement('img');
      img.src = item.image;
      img.alt = getLocalizedText(item.name);
      img.loading = 'lazy';
      dom.detailImageContainer.appendChild(img);
    } else {
      dom.detailImageContainer.innerHTML = `<svg class="placeholder-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M18 8h1a4 4 0 0 1 0 8h-1"/>
        <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>
        <line x1="6" y1="1" x2="6" y2="4"/>
        <line x1="10" y1="1" x2="10" y2="4"/>
        <line x1="14" y1="1" x2="14" y2="4"/>
      </svg>`;
    }

    // Ingredients
    const ingredients = item.ingredients ? (item.ingredients[state.lang] || item.ingredients.tr || []) : [];
    if (ingredients.length > 0) {
      const ingTitles = { tr: 'İçindekiler', en: 'Ingredients', ar: 'المكونات' };
      dom.detailIngredients.innerHTML = '';
      const titleEl = document.createElement('p');
      titleEl.className = 'detail-ingredients-title';
      titleEl.textContent = ingTitles[state.lang] || ingTitles.tr;
      dom.detailIngredients.appendChild(titleEl);

      const listEl = document.createElement('div');
      listEl.className = 'ingredients-list';
      ingredients.forEach(ing => {
        const tag = document.createElement('span');
        tag.className = 'ingredient-tag';
        tag.textContent = ing;
        listEl.appendChild(tag);
      });
      dom.detailIngredients.appendChild(listEl);
      dom.detailIngredients.hidden = false;
    } else {
      dom.detailIngredients.hidden = true;
    }

    openDialog(dom.detailOverlay, dom.detailDialog);
    dom.detailClose.focus();
  }

  // ── Generic Dialog Management ────────────────────────────────────
  function openDialog(overlay, dialog) {
    overlay.hidden = false;
    // Force reflow for transition
    void overlay.offsetHeight;
    overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';

    // Trap focus
    overlay._keyHandler = (e) => {
      if (e.key === 'Escape') {
        closeDialog(overlay);
      }
      if (e.key === 'Tab') {
        trapFocus(e, overlay);
      }
    };
    document.addEventListener('keydown', overlay._keyHandler);
  }

  function closeDialog(overlay) {
    overlay.classList.remove('is-open');
    document.body.style.overflow = '';
    document.removeEventListener('keydown', overlay._keyHandler);

    const onEnd = () => {
      overlay.hidden = true;
      overlay.removeEventListener('transitionend', onEnd);
      // Restore focus
      if (state.lastFocusedElement) {
        state.lastFocusedElement.focus();
        state.lastFocusedElement = null;
      }
    };
    overlay.addEventListener('transitionend', onEnd);

    // Fallback if transitionend doesn't fire
    setTimeout(() => {
      if (!overlay.hidden) {
        overlay.hidden = true;
        if (state.lastFocusedElement) {
          state.lastFocusedElement.focus();
          state.lastFocusedElement = null;
        }
      }
    }, 400);
  }

  function trapFocus(e, container) {
    const focusable = container.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (focusable.length === 0) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (e.shiftKey) {
      if (document.activeElement === first) {
        e.preventDefault();
        last.focus();
      }
    } else {
      if (document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  // ── Search ───────────────────────────────────────────────────────
  function onSearchInput() {
    state.searchQuery = dom.searchInput.value.trim();
    dom.searchClear.hidden = state.searchQuery.length === 0;
    renderMenu();
  }

  function clearSearch() {
    dom.searchInput.value = '';
    state.searchQuery = '';
    dom.searchClear.hidden = true;
    renderMenu();
    dom.searchInput.focus();
  }

  // ── Campaign ─────────────────────────────────────────────────────
  function updateCampaign() {
    const text = MENU_DATA.campaign ? (MENU_DATA.campaign[state.lang] || '') : '';
    if (text) {
      dom.campaignText.textContent = text;
      dom.campaignBanner.hidden = false;
    } else {
      dom.campaignBanner.hidden = true;
    }
  }

  // ── Init ─────────────────────────────────────────────────────────
  function init() {
    // Check reduced motion
    const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
    prefersReducedMotion = mq.matches;
    mq.addEventListener('change', (e) => {
      prefersReducedMotion = e.matches;
    });

    // Restore preferences
    const savedTheme = localStorage.getItem('chef-theme');
    if (savedTheme) {
      setTheme(savedTheme);
    } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      setTheme('dark');
    }

    const savedLang = localStorage.getItem('chef-lang');
    if (savedLang && LANG_ORDER.includes(savedLang)) {
      setLang(savedLang);
    } else {
      setLang('tr');
    }

    // Measure logo positions after fonts load
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(() => {
        measureLogoPositions();
        updateLogoPosition(window.scrollY);
      });
    } else {
      setTimeout(() => {
        measureLogoPositions();
        updateLogoPosition(window.scrollY);
      }, 200);
    }

    // Event listeners
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', () => {
      // Debounce resize
      clearTimeout(window._resizeTimer);
      window._resizeTimer = setTimeout(onResize, 150);
    });

    dom.themeToggle.addEventListener('click', toggleTheme);
    dom.langToggle.addEventListener('click', cycleLang);

    dom.searchInput.addEventListener('input', onSearchInput);
    dom.searchClear.addEventListener('click', clearSearch);

    // Detail dialog
    dom.detailClose.addEventListener('click', () => closeDialog(dom.detailOverlay));
    dom.detailOverlay.addEventListener('click', (e) => {
      if (e.target === dom.detailOverlay) {
        closeDialog(dom.detailOverlay);
      }
    });

    // WiFi dialog
    dom.wifiBtn.addEventListener('click', () => {
      state.lastFocusedElement = document.activeElement;
      openDialog(dom.wifiOverlay, dom.wifiOverlay.querySelector('dialog'));
      dom.wifiClose.focus();
    });
    dom.wifiClose.addEventListener('click', () => closeDialog(dom.wifiOverlay));
    dom.wifiOverlay.addEventListener('click', (e) => {
      if (e.target === dom.wifiOverlay) {
        closeDialog(dom.wifiOverlay);
      }
    });

    // Feedback dialog
    dom.feedbackBtn.addEventListener('click', () => {
      state.lastFocusedElement = document.activeElement;
      openDialog(dom.feedbackOverlay, dom.feedbackOverlay.querySelector('dialog'));
      dom.feedbackClose.focus();
    });
    dom.feedbackClose.addEventListener('click', () => closeDialog(dom.feedbackOverlay));
    dom.feedbackOverlay.addEventListener('click', (e) => {
      if (e.target === dom.feedbackOverlay) {
        closeDialog(dom.feedbackOverlay);
      }
    });
    dom.feedbackForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const msg = dom.feedbackTextarea.value.trim();
      if (msg) {
        // In production, this would POST to the server
        dom.feedbackTextarea.value = '';
        closeDialog(dom.feedbackOverlay);
      }
    });

    // Campaign close
    dom.campaignClose.addEventListener('click', () => {
      dom.campaignBanner.hidden = true;
    });

    // WiFi data
    if (MENU_DATA.wifi) {
      dom.wifiSsid.textContent = MENU_DATA.wifi.ssid;
      dom.wifiPass.textContent = MENU_DATA.wifi.password;
    }

    // Initial render
    renderCategories();
    renderMenu();
    updateCampaign();
  }

  // Start
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

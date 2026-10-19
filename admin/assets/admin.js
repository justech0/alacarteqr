(() => {
  const root = document.documentElement;
  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

  // ── Theme ──
  const getTheme = () => {
    try { return localStorage.getItem('chef-admin-theme'); } catch (_) { return null; }
  };
  const setTheme = (theme) => {
    root.classList.toggle('dark', theme === 'dark');
    try { localStorage.setItem('chef-admin-theme', theme); } catch (_) {}
    $$('[data-theme-toggle]').forEach((button) => {
      button.setAttribute('aria-label', theme === 'dark' ? 'Açık temaya geç' : 'Koyu temaya geç');
    });
  };
  setTheme(getTheme() || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-theme-toggle]')) setTheme(root.classList.contains('dark') ? 'light' : 'dark');
  });

  const main = () => $('[data-admin-main]');
  const announce = (message, isError = false) => {
    const status = $('[data-admin-flash]');
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-error', isError);
    status.hidden = false;
  };

  // ── Confirmation dialog (shared by delete forms and unsaved-change guard) ──
  const confirmAction = (name, detail, acceptLabel = 'Onayla') => new Promise((resolve) => {
    const dialog = $('[data-testid="confirm-dialog"]');
    if (!dialog) { resolve(true); return; }
    $('[data-testid="confirm-dialog-name"]', dialog).textContent = name || '';
    $('[data-confirm-detail]', dialog).textContent = detail || '';
    const accept = $('[data-testid="confirm-dialog-accept"]', dialog);
    const cancel = $('[data-testid="confirm-dialog-cancel"]', dialog);
    accept.textContent = acceptLabel;
    const finish = (result) => {
      accept.removeEventListener('click', onAccept);
      cancel.removeEventListener('click', onCancel);
      dialog.removeEventListener('cancel', onCancel);
      if (dialog.open) dialog.close();
      resolve(result);
    };
    const onAccept = () => finish(true);
    const onCancel = (event) => { event?.preventDefault?.(); finish(false); };
    accept.addEventListener('click', onAccept);
    cancel.addEventListener('click', onCancel);
    dialog.addEventListener('cancel', onCancel);
    dialog.showModal();
  });

  // ── Unsaved changes ──
  const dirtyForms = new Set();
  // Fields may sit outside their form and point at it with the form attribute.
  const trackDirty = (event) => {
    const form = event.target.form || event.target.closest?.('form');
    if (form?.hasAttribute('data-warn-unsaved')) dirtyForms.add(form);
  };
  document.addEventListener('input', trackDirty);
  document.addEventListener('change', trackDirty);
  const hasDirty = (except) => [...dirtyForms].some((form) => form !== except && form.isConnected);
  const guardDirty = async (except) => {
    if (!hasDirty(except)) return true;
    const ok = await confirmAction('Kaydedilmemiş değişiklikler var', 'Devam ederseniz yaptığınız değişiklikler kaybolur.', 'Devam et');
    if (ok) dirtyForms.clear();
    return ok;
  };
  window.addEventListener('beforeunload', (event) => {
    if (!hasDirty()) return;
    event.preventDefault();
    event.returnValue = '';
  });

  // ── Automatic translation (Turkish source fields fill *_en and *_ar fields) ──
  const translateForm = async (form, overwrite) => {
    const status = $('[data-translate-status]', form);
    const texts = {};
    ['name', 'description', 'ingredients'].forEach((key) => {
      const source = form.elements[key];
      if (!source || !source.value.trim()) return;
      const needs = ['en', 'ar'].some((target) => {
        const field = form.elements[`${key}_${target}`];
        return field && (overwrite || !field.value.trim());
      });
      if (needs) texts[key] = source.value.trim();
    });
    if (!Object.keys(texts).length) {
      if (overwrite && status) status.textContent = 'Önce Türkçe ad veya açıklama yazın.';
      return;
    }
    if (status) status.textContent = 'Çevriliyor…';
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(new URL('api/translate.php', location.href), {
        method: 'POST',
        credentials: 'same-origin',
        signal: controller.signal,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': form.elements.csrf_token?.value || '' },
        body: JSON.stringify({ texts, targets: ['en', 'ar'] }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.translations) throw new Error(payload.error || 'Çeviri yapılamadı.');
      Object.entries(payload.translations).forEach(([name, value]) => {
        const field = form.elements[name];
        if (field && typeof value === 'string' && (overwrite || !field.value.trim())) field.value = value;
      });
      if (status) status.textContent = 'Çeviriler dolduruldu. Kontrol edip düzeltebilirsiniz.';
      if (form.hasAttribute('data-warn-unsaved')) dirtyForms.add(form);
    } catch (error) {
      if (status) status.textContent = error.name === 'AbortError' ? 'Çeviri servisi yanıt vermedi.' : error.message;
    } finally {
      clearTimeout(timer);
    }
  };
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-translate-now]');
    if (!button?.form) return;
    button.disabled = true;
    await translateForm(button.form, true);
    button.disabled = false;
  });

  // ── Page swap: fetch the next page and replace only the main area ──
  const progress = () => $('[data-admin-progress]');
  const adminBase = location.pathname.replace(/[^/]*$/, '');
  const isSwappable = (url) => url.origin === location.origin
    && url.pathname.startsWith(adminBase)
    && /\.php$/.test(url.pathname)
    && !/\/(logout|private-image)\.php$/.test(url.pathname);

  const downloadResponse = async (response) => {
    const blob = await response.blob();
    const disposition = response.headers.get('content-disposition') || '';
    const name = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)?.[1] || 'indirme';
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = decodeURIComponent(name);
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 4000);
  };

  let swapping = null;
  const swap = async (url, init = {}, options = {}) => {
    const area = main();
    if (!area) { location.href = url; return; }
    swapping?.abort();
    const controller = new AbortController();
    swapping = controller;
    const previousPath = location.pathname;
    const scrollY = window.scrollY;
    const bar = progress();
    const slow = setTimeout(() => { if (bar) bar.hidden = false; area.setAttribute('aria-busy', 'true'); }, 120);
    try {
      const response = await fetch(url, { ...init, credentials: 'same-origin', signal: controller.signal, headers: { 'X-Chef-Swap': '1', ...(init.headers || {}) } });
      const type = response.headers.get('content-type') || '';
      if (!type.includes('text/html')) {
        if (response.ok) await downloadResponse(response);
        else announce('İstek tamamlanamadı. Lütfen tekrar deneyin.', true);
        return;
      }
      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const next = $('[data-admin-main]', doc);
      if (!next) { location.href = response.url; return; }
      $$('dialog[open]').forEach((dialog) => { if (!dialog.matches('[data-image-viewer]')) dialog.close(); });
      area.replaceChildren(...[...next.childNodes].map((node) => document.importNode(node, true)));
      document.title = doc.title;
      const title = $('[data-page-title]');
      const nextTitle = $('[data-page-title]', doc);
      if (title && nextTitle) title.textContent = nextTitle.textContent;
      $$('[data-section]').forEach((link) => {
        const twin = $(`[data-section="${link.dataset.section}"]`, doc);
        if (twin?.hasAttribute('aria-current')) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
      });
      const finalUrl = new URL(response.url);
      if (options.push !== false) {
        if (finalUrl.pathname === previousPath && init.method === 'POST') history.replaceState({}, '', finalUrl);
        else history.pushState({}, '', finalUrl);
      }
      dirtyForms.clear();
      initPage(area);
      const keep = options.keepScroll || finalUrl.pathname === previousPath && init.method === 'POST';
      window.scrollTo({ top: keep ? scrollY : 0, behavior: 'instant' });
      const flash = $('[data-admin-flash]', area);
      if (flash && !flash.hidden && keep && flash.getBoundingClientRect().bottom < 0) {
        flash.classList.add('admin-toast');
      }
    } catch (error) {
      if (error.name !== 'AbortError') location.href = url;
    } finally {
      clearTimeout(slow);
      if (swapping === controller) {
        swapping = null;
        if (bar) bar.hidden = true;
        main()?.removeAttribute('aria-busy');
      }
    }
  };

  document.addEventListener('click', async (event) => {
    if (!main() || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('a[href]');
    if (!link || link.target || link.hasAttribute('download') || link.dataset.noSwap !== undefined) return;
    const url = new URL(link.href, location.href);
    if (link.hasAttribute('data-drawer-close')) {
      event.preventDefault();
      const drawer = link.closest('dialog');
      if (!(await guardDirty())) return;
      drawer?.close();
      if (productState.category) url.searchParams.set('category', productState.category);
      history.replaceState({}, '', url);
      return;
    }
    if (!isSwappable(url)) return;
    if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
    event.preventDefault();
    if (!(await guardDirty())) return;
    swap(url.href, {}, { keepScroll: link.hasAttribute('data-keep-scroll') });
  });

  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement)) return;
    if (!main()) return;
    if (form.dataset.confirmName && form.dataset.confirmed !== 'true') {
      event.preventDefault();
      const ok = await confirmAction(form.dataset.confirmName, form.dataset.confirmDetail);
      if (!ok) return;
      const dependents = form.querySelector('input[name="delete_dependents"]');
      if (dependents && Number(form.dataset.dependentCount || 0) > 0) dependents.value = '1';
      form.dataset.confirmed = 'true';
      form.requestSubmit(event.submitter && form.contains(event.submitter) ? event.submitter : undefined);
      return;
    }
    const action = new URL(form.getAttribute('action') || location.href, location.href);
    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    if (form.dataset.noSwap !== undefined || !isSwappable(action)) {
      form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = true; });
      dirtyForms.delete(form);
      return;
    }
    event.preventDefault();
    if (!(await guardDirty(form))) return;
    if (method === 'POST' && form.hasAttribute('data-auto-translate') && ['create', 'update'].includes(form.elements.action?.value)) {
      form.querySelectorAll('button[type="submit"]').forEach((button) => { button.disabled = true; });
      await translateForm(form, false);
    }
    const data = new FormData(form, event.submitter || undefined);
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
    });
    if (method === 'GET') {
      action.search = new URLSearchParams([...data].filter(([, value]) => typeof value === 'string' && value !== '')).toString();
      swap(action.href, {}, { keepScroll: form.hasAttribute('data-keep-scroll') });
    } else {
      swap(action.href, { method: 'POST', body: data }, { keepScroll: true });
    }
  });

  window.addEventListener('popstate', () => swap(location.href, {}, { push: false }));

  // ── Auto-submit filters ──
  let autoTimer = 0;
  document.addEventListener('change', (event) => {
    const form = event.target.closest?.('form[data-auto-submit]');
    if (form && event.target.matches('select')) form.requestSubmit();
  });
  document.addEventListener('input', (event) => {
    const form = event.target.closest?.('form[data-auto-submit]');
    if (!form || !event.target.matches('input[type="search"], input[name="search"]')) return;
    clearTimeout(autoTimer);
    autoTimer = setTimeout(() => form.requestSubmit(), 450);
  });

  // ── Switches show their state as text ──
  document.addEventListener('change', (event) => {
    const input = event.target.closest?.('[data-switch-label]');
    const text = input?.parentElement?.querySelector('.admin-switch-text');
    if (text) text.textContent = input.checked ? text.dataset.on : text.dataset.off;
  });

  // ── Image viewer: enlarge, download, share ──
  const viewer = $('[data-image-viewer]');
  const shareButton = viewer && $('[data-image-viewer-share]', viewer);
  let viewerSource = '';
  let viewerName = 'gorsel';
  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-image-view]');
    if (!trigger || !viewer || !trigger.dataset.imageView) return;
    viewerSource = trigger.dataset.imageView;
    viewerName = trigger.dataset.imageName || 'gorsel';
    $('[data-image-viewer-img]', viewer).src = viewerSource;
    const download = $('[data-image-viewer-download]', viewer);
    download.href = viewerSource;
    download.download = `${viewerName}.webp`;
    if (shareButton) shareButton.hidden = !(navigator.share && navigator.canShare);
    viewer.showModal();
  });
  $('[data-image-viewer-close]', viewer || document)?.addEventListener('click', () => viewer.close());
  viewer?.addEventListener('click', (event) => { if (event.target === viewer) viewer.close(); });
  shareButton?.addEventListener('click', async () => {
    try {
      const blob = await (await fetch(viewerSource, { credentials: 'same-origin' })).blob();
      const file = new File([blob], `${viewerName}.webp`, { type: blob.type || 'image/webp' });
      if (navigator.canShare({ files: [file] })) await navigator.share({ files: [file], title: 'Geri bildirim görseli' });
    } catch (_) {}
  });

  // ── Feedback detail dialog ──
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-feedback-open]');
    if (button) {
      const dialog = $('[data-feedback-dialog]');
      if (!dialog) return;
      $('[data-feedback-comment]', dialog).textContent = button.dataset.feedbackComment || '';
      const source = button.dataset.feedbackImage || '';
      const imageButton = $('[data-feedback-image-button]', dialog);
      if (imageButton) {
        imageButton.hidden = source === '';
        imageButton.dataset.imageView = source;
      }
      const image = $('[data-feedback-image]', dialog);
      if (image) image.src = source;
      dialog.showModal();
      return;
    }
    if (event.target.closest('[data-feedback-close]')) event.target.closest('dialog')?.close();
  });

  // ── Product page: instant filters, drawer, image preview ──
  const productState = { category: null, query: '' };
  // Same folding as the guest search: case, Turkish dotless i and accents.
  const fold = (value) => String(value || '').toLocaleLowerCase('tr-TR').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ı/g, 'i');
  const applyProductFilter = (scope) => {
    const cards = $$('[data-product-grid] > article', scope);
    if (!cards.length && !$('[data-product-grid]', scope)) return;
    const query = fold(productState.query);
    let visible = 0;
    cards.forEach((card) => {
      const show = (!productState.category || card.dataset.category === productState.category)
        && (!query || fold(card.dataset.search).includes(query));
      card.hidden = !show;
      if (show) visible += 1;
    });
    $$('[data-filter-category]', scope).forEach((chip) => {
      chip.setAttribute('aria-pressed', String((chip.dataset.filterCategory || '') === (productState.category || '')));
    });
    const empty = $('[data-product-empty]', scope);
    if (empty) empty.hidden = visible > 0;
  };
  document.addEventListener('click', (event) => {
    const chip = event.target.closest('[data-filter-category]');
    if (!chip) return;
    productState.category = chip.dataset.filterCategory || '';
    applyProductFilter(document);
    const url = new URL(location.href);
    if (productState.category) url.searchParams.set('category', productState.category);
    else url.searchParams.delete('category');
    history.replaceState({}, '', url);
  });
  document.addEventListener('input', (event) => {
    if (!event.target.matches('[data-product-search]')) return;
    productState.query = event.target.value.trim();
    applyProductFilter(document);
  });
  document.addEventListener('change', (event) => {
    const input = event.target.closest?.('[data-image-input]');
    if (!input) return;
    const preview = $('[data-image-preview]', input.form || document);
    const file = input.files?.[0];
    if (!preview || !file) return;
    if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
    preview.dataset.objectUrl = URL.createObjectURL(file);
    preview.src = preview.dataset.objectUrl;
    preview.hidden = false;
  });

  // ── Reorder lists ──
  const refreshReorder = (list) => {
    $$('[data-reorder-item]', list).forEach((item, index) => {
      const input = item.querySelector('input[name="order[]"]');
      const badge = item.querySelector('[data-order-badge]');
      if (input) input.value = item.dataset.id;
      if (badge) badge.textContent = `#${index + 1}`;
    });
  };
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-reorder-direction]');
    const list = button?.closest('[data-reorder-list]');
    if (!list) return;
    const item = button.closest('[data-reorder-item]');
    const direction = button.dataset.reorderDirection;
    const sibling = direction === 'up' ? item?.previousElementSibling : item?.nextElementSibling;
    if (!item || !sibling) return;
    if (direction === 'up') list.insertBefore(item, sibling);
    else list.insertBefore(sibling, item);
    refreshReorder(list);
    const form = list.closest('form');
    if (form?.hasAttribute('data-warn-unsaved')) dirtyForms.add(form);
    announce(`${item.dataset.itemName} ${direction === 'up' ? 'yukarı' : 'aşağı'} taşındı.`);
    button.focus();
  });

  // ── Per-page setup, run on first load and after every swap ──
  const initPage = (scope) => {
    $$('[data-reorder-list]', scope).forEach(refreshReorder);
    const grid = $('[data-product-grid]', scope);
    if (grid) {
      if (productState.category === null) {
        productState.category = $('[data-filter-category][aria-pressed="true"]', scope)?.dataset.filterCategory || '';
      }
      const search = $('[data-product-search]', scope);
      if (search) search.value = productState.query;
      applyProductFilter(scope);
    }
    const drawer = $('[data-product-drawer][data-open-on-load]', scope);
    if (drawer && !drawer.open) {
      drawer.showModal();
      drawer.addEventListener('cancel', async (event) => {
        event.preventDefault();
        if (!(await guardDirty())) return;
        drawer.close();
        history.replaceState({}, '', new URL(drawer.dataset.closeHref, location.href));
      });
    }
    const flash = $('[data-admin-flash]', scope);
    if (flash && !flash.hidden && !flash.classList.contains('is-error')) {
      setTimeout(() => { flash.hidden = true; }, 5000);
    }
  };

  if (main()) initPage(main());
})();

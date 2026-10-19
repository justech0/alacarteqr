(() => {
  const root = document.documentElement;
  const getTheme = () => {
    try { return localStorage.getItem('chef-admin-theme'); } catch (_) { return null; }
  };
  const setTheme = (theme) => {
    root.classList.toggle('dark', theme === 'dark');
    try { localStorage.setItem('chef-admin-theme', theme); } catch (_) {}
  };

  setTheme(getTheme() || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));

  document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
    setTheme(root.classList.contains('dark') ? 'light' : 'dark');
  });

  const menu = document.querySelector('[data-mobile-menu]');
  document.querySelector('[data-mobile-menu-open]')?.addEventListener('click', () => menu?.showModal());
  document.querySelector('[data-mobile-menu-close]')?.addEventListener('click', () => menu?.close());

  const status = document.querySelector('[role="status"]');
  const announce = (message) => {
    if (!status) return;
    status.textContent = message;
    status.hidden = false;
  };

  const confirmDialog = document.querySelector('[data-testid="confirm-dialog"]');
  const confirmName = confirmDialog?.querySelector('[data-testid="confirm-dialog-name"]');
  let pendingForm = null;

  document.querySelectorAll('form[data-confirm-name]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (form.dataset.confirmed === 'true' || !confirmDialog) return;
      event.preventDefault();
      pendingForm = form;
      if (confirmName) confirmName.textContent = form.dataset.confirmName || '';
      const detail = confirmDialog.querySelector('[data-confirm-detail]');
      if (detail) detail.textContent = form.dataset.confirmDetail || '';
      confirmDialog.showModal();
    });
  });

  confirmDialog?.querySelector('[data-testid="confirm-dialog-accept"]')?.addEventListener('click', () => {
    if (!pendingForm) return;
    const dependents = pendingForm.querySelector('input[name="delete_dependents"]');
    if (dependents && Number(pendingForm.dataset.dependentCount || 0) > 0) dependents.value = '1';
    pendingForm.dataset.confirmed = 'true';
    confirmDialog.close();
    pendingForm.requestSubmit();
  });
  confirmDialog?.querySelector('[data-testid="confirm-dialog-cancel"]')?.addEventListener('click', () => {
    pendingForm = null;
    confirmDialog.close();
  });

  document.querySelectorAll('form[data-mutation-form]').forEach((form) => {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', () => {
      dirty = false;
      form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
      });
    });
    if (form.hasAttribute('data-warn-unsaved')) {
      window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
      });
    }
  });

  document.querySelectorAll('[data-reorder-list]').forEach((list) => {
    const refresh = () => {
      [...list.querySelectorAll('[data-reorder-item]')].forEach((item, index) => {
        const input = item.querySelector('input[name="order[]"]');
        const badge = item.querySelector('[data-order-badge]');
        if (input) input.value = item.dataset.id;
        if (badge) badge.textContent = `#${index + 1}`;
      });
    };
    list.addEventListener('click', (event) => {
      const button = event.target.closest('[data-reorder-direction]');
      if (!button) return;
      const item = button.closest('[data-reorder-item]');
      const direction = button.dataset.reorderDirection;
      const sibling = direction === 'up' ? item?.previousElementSibling : item?.nextElementSibling;
      if (!item || !sibling) return;
      if (direction === 'up') list.insertBefore(item, sibling);
      else list.insertBefore(sibling, item);
      refresh();
      announce(`${item.dataset.itemName} ${direction === 'up' ? 'yukarı' : 'aşağı'} taşındı.`);
      button.focus();
    });
    refresh();
  });

  const feedbackDialog = document.querySelector('[data-feedback-dialog]');
  const feedbackComment = feedbackDialog?.querySelector('[data-feedback-comment]');
  const feedbackImage = feedbackDialog?.querySelector('[data-feedback-image]');
  document.querySelectorAll('[data-feedback-open]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!feedbackDialog) return;
      if (feedbackComment) feedbackComment.textContent = button.dataset.feedbackComment || '';
      if (feedbackImage) {
        const source = button.dataset.feedbackImage || '';
        feedbackImage.hidden = source === '';
        feedbackImage.src = source;
      }
      feedbackDialog.showModal();
    });
  });
  feedbackDialog?.querySelector('[data-feedback-close]')?.addEventListener('click', () => feedbackDialog.close());
})();

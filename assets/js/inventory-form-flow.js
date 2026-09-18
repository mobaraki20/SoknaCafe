(() => {
  'use strict';

  const faDigits = '۰۱۲۳۴۵۶۷۸۹';
  const arDigits = '٠١٢٣٤٥٦٧٨٩';
  const normalizeDigits = (value) => String(value ?? '')
    .replace(/[۰-۹]/g, (d) => String(faDigits.indexOf(d)))
    .replace(/[٠-٩]/g, (d) => String(arDigits.indexOf(d)))
    .replace(/[٬,\s]/g, '')
    .replace(/٫/g, '.');
  const numericValue = (value) => {
    const normalized = normalizeDigits(value);
    if (normalized === '') return null;
    const number = Number(normalized);
    return Number.isFinite(number) ? number : null;
  };
  const formatNumber = (value, maximumFractionDigits = 3) => new Intl.NumberFormat('fa-IR', {
    maximumFractionDigits,
  }).format(value);
  const isVisible = (node) => Boolean(node && !node.hidden && node.getClientRects().length && !node.closest('[hidden]'));
  const choiceTrigger = (select) => select?.nextElementSibling?.classList?.contains('panel-choice-trigger') ? select.nextElementSibling : null;
  const touchContext = () => window.CafeUI?.keyboard?.isTouchContext?.() ?? window.matchMedia('(pointer: coarse)').matches;
  const focusControl = (control, selectText = true) => {
    if (!control || !isVisible(control)) return false;
    const target = control instanceof HTMLSelectElement ? (choiceTrigger(control) || control) : control;
    try { target.focus({ preventScroll: false }); } catch (_) { target.focus?.(); }
    if (selectText && target instanceof HTMLInputElement && ['text','search','tel','url','email','password',''].includes(target.type)) target.select?.();
    else if (selectText && target instanceof HTMLInputElement) target.select?.();
    return true;
  };

  // Item-selection pages are one-question task steps. Selecting a result is the action;
  // there is no redundant Continue tap. The hint in the UI makes the transition explicit.
  document.querySelectorAll('form[data-inventory-item-picker]').forEach((form) => {
    let submitting = false;
    form.addEventListener('panel:choice-change', (event) => {
      const select = event.target;
      if (!(select instanceof HTMLSelectElement) || !select.matches('[data-inventory-item-select]') || !select.value || submitting) return;
      submitting = true;
      requestAnimationFrame(() => form.requestSubmit());
    });
  });

  document.querySelectorAll('form[data-inventory-flow]').forEach((form) => {
    const ordered = () => [...form.querySelectorAll('[data-inventory-step]')].filter(isVisible);
    form.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) return;
      const current = event.target;
      if (!(current instanceof HTMLInputElement) || !current.matches('[data-inventory-step]')) return;
      const fields = ordered();
      const index = fields.indexOf(current);
      if (index < 0) return;
      const next = fields.slice(index + 1).find(isVisible);
      if (!next) return;
      event.preventDefault();
      focusControl(next);
    });
    form.addEventListener('panel:choice-change', (event) => {
      const select = event.target;
      if (!(select instanceof HTMLSelectElement)) return;
      const selector = String(select.dataset.inventoryNextTarget || '').trim();
      if (!selector) return;
      if (CafeUI.keyboard?.isTouchContext?.()) return;
      requestAnimationFrame(() => focusControl(form.querySelector(selector)));
    });
    const autofocus = form.querySelector('[data-inventory-autofocus]');
    const allowInitialAutofocus = window.innerWidth > 760 && !window.matchMedia('(pointer: coarse)').matches;
    if (autofocus && allowInitialAutofocus) requestAnimationFrame(() => focusControl(autofocus));
  });

  const formatBaseQuantity = (quantityBase, baseUnit) => {
    if (!Number.isFinite(quantityBase)) return '';
    if (baseUnit === 'g') return `${formatNumber(quantityBase / 1000)} کیلوگرم`;
    if (baseUnit === 'ml') return `${formatNumber(quantityBase / 1000)} لیتر`;
    return `${formatNumber(quantityBase, 0)} عدد`;
  };

  document.querySelectorAll('form[data-inventory-operation-form]').forEach((form) => {
    const unitSelect = form.querySelector('[data-inventory-purchase-unit]');
    const unitCount = form.querySelector('[data-inventory-unit-count]');
    const unitLabel = form.querySelector('[data-inventory-unit-count-label]');
    const actualGroup = form.querySelector('[data-inventory-actual-group]');
    const actualInput = form.querySelector('[data-inventory-actual-input]');
    const quantityPreview = form.querySelector('[data-inventory-quantity-preview]');
    const stockPreview = form.querySelector('[data-inventory-stock-preview]');
    const costInput = form.querySelector('[data-inventory-cost-input]');
    const baseUnit = String(form.dataset.inventoryBaseUnit || 'count');
    const currentBase = Number(form.dataset.inventoryCurrentBase || 0);
    const direction = Number(form.dataset.inventoryDirection || 1);

    const selected = () => unitSelect?.options?.[unitSelect.selectedIndex] || null;
    const quantityBase = () => {
      const option = selected();
      const count = numericValue(unitCount?.value);
      if (!option || count === null || count < 0) return null;
      const mode = option.dataset.mode || 'base';
      if (mode === 'actual_quantity') {
        const actualMajor = numericValue(actualInput?.value);
        if (actualMajor === null || actualMajor < 0) return null;
        return baseUnit === 'count' ? actualMajor : actualMajor * 1000;
      }
      if (mode === 'fixed') {
        const baseQuantity = Number(option.dataset.baseQuantity || 0);
        return baseQuantity > 0 ? count * baseQuantity : null;
      }
      return baseUnit === 'count' ? count : count * 1000;
    };
    const sync = () => {
      const option = selected();
      if (!option) return;
      const mode = option.dataset.mode || 'base';
      if (actualGroup) actualGroup.hidden = mode !== 'actual_quantity';
      if (actualInput) actualInput.required = mode === 'actual_quantity';
      if (unitLabel) {
        const label = String(option.dataset.unitLabel || option.textContent || '').trim();
        unitLabel.textContent = mode === 'base' ? `مقدار (${String(option.dataset.majorLabel || '')})` : `تعداد «${label}»`;
      }
      const base = quantityBase();
      if (quantityPreview) {
        quantityPreview.hidden = base === null;
        if (base !== null) quantityPreview.innerHTML = `<span>به موجودی اعمال می‌شود</span><strong>${formatBaseQuantity(base * direction, baseUnit)}</strong>`;
      }
      if (stockPreview) {
        stockPreview.hidden = base === null;
        if (base !== null) {
          const after = currentBase + (base * direction);
          stockPreview.classList.toggle('is-negative', after < 0);
          stockPreview.textContent = after < 0
            ? `پس از ثبت، موجودی ${formatBaseQuantity(after, baseUnit)} می‌شود.`
            : `موجودی پس از ثبت: ${formatBaseQuantity(after, baseUnit)}`;
        }
      }
    };
    unitSelect?.addEventListener('change', sync);
    unitCount?.addEventListener('input', sync);
    actualInput?.addEventListener('input', sync);
    costInput?.addEventListener('input', sync);
    sync();
  });

  document.addEventListener('click', (event) => {
    const add = event.target.closest?.('[data-inventory-add-field]');
    if (add) {
      const key = String(add.dataset.inventoryAddField || '');
      const field = document.querySelector(`[data-inventory-optional-field="${CSS.escape(key)}"]`);
      if (!field) return;
      field.hidden = false;
      add.hidden = true;
      const control = field.querySelector('input,textarea,select,button[data-open-jalali]');
      if (!touchContext()) requestAnimationFrame(() => focusControl(control, false));
      return;
    }
    const remove = event.target.closest?.('[data-inventory-remove-field]');
    if (!remove) return;
    const key = String(remove.dataset.inventoryRemoveField || '');
    const field = document.querySelector(`[data-inventory-optional-field="${CSS.escape(key)}"]`);
    if (!field) return;
    field.querySelectorAll('input,textarea,select').forEach((control) => {
      if (control instanceof HTMLSelectElement) control.selectedIndex = 0;
      else control.value = '';
      control.dispatchEvent(new Event('input', { bubbles:true }));
      control.dispatchEvent(new Event('change', { bubbles:true }));
    });
    field.hidden = true;
    document.querySelectorAll(`[data-inventory-add-field="${CSS.escape(key)}"]`).forEach((button) => { button.hidden = false; });
  });

  document.querySelectorAll('form[data-inventory-count-form]').forEach((form) => {
    const quantityInputs = [...form.querySelectorAll('[data-inventory-count-quantity]')];
    const progress = document.querySelector('[data-inventory-live-progress]');
    const meter = document.querySelector('[data-inventory-live-meter]');
    const tools = document.querySelector('[data-inventory-count-tools]');
    const emptyCount = tools?.querySelector('[data-inventory-empty-count]');
    const filterButtons = [...(tools?.querySelectorAll('[data-inventory-count-filter]') || [])];
    const storageKey = `sokna:inventory-count:${form.dataset.inventoryCountSession || 'current'}`;
    let activeFilter = 'all';
    const emptyInputs = () => quantityInputs.filter((input) => String(input.value || '').trim() === '');
    const applyCountFilter = () => {
      form.querySelectorAll('[data-inventory-count-line]').forEach((line) => {
        const input = line.querySelector('[data-inventory-count-quantity]');
        line.hidden = activeFilter === 'empty' && String(input?.value || '').trim() !== '';
      });
      form.querySelectorAll('[data-inventory-count-group]').forEach((group) => {
        group.hidden = !group.querySelector('[data-inventory-count-line]:not([hidden])');
      });
      filterButtons.forEach((button) => {
        const selected = button.dataset.inventoryCountFilter === activeFilter;
        button.classList.toggle('is-active', selected);
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
    };
    const syncProgress = () => {
      const filled = quantityInputs.filter((input) => String(input.value || '').trim() !== '').length;
      if (progress) progress.textContent = `${formatNumber(filled, 0)} از ${formatNumber(quantityInputs.length, 0)} قلم وارد شده`;
      if (meter) meter.style.width = `${quantityInputs.length ? Math.round((filled / quantityInputs.length) * 100) : 0}%`;
      if (emptyCount) emptyCount.textContent = formatNumber(Math.max(0, quantityInputs.length - filled), 0);
      applyCountFilter();
    };
    quantityInputs.forEach((input, index) => {
      input.addEventListener('input', syncProgress);
      input.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        const next = quantityInputs.slice(index + 1).find(isVisible);
        if (!next) return;
        event.preventDefault();
        focusControl(next);
      });
    });
    filterButtons.forEach((button) => button.addEventListener('click', () => {
      activeFilter = button.dataset.inventoryCountFilter === 'empty' ? 'empty' : 'all';
      applyCountFilter();
    }));
    tools?.querySelector('[data-inventory-count-next]')?.addEventListener('click', () => {
      const targets = emptyInputs();
      if (!targets.length) return;
      activeFilter = 'empty';
      applyCountFilter();
      const currentIndex = targets.indexOf(document.activeElement);
      const target = targets[currentIndex >= 0 ? (currentIndex + 1) % targets.length : 0];
      target.closest('[data-inventory-count-line]')?.scrollIntoView({ behavior:'smooth', block:'center' });
      window.setTimeout(() => focusControl(target), 180);
    });
    const savePosition = () => {
      try { sessionStorage.setItem(storageKey, JSON.stringify({ scrollY:window.scrollY, activeName:document.activeElement?.name || '' })); } catch (_) {}
    };
    form.addEventListener('submit', savePosition);
    window.addEventListener('pagehide', savePosition);
    syncProgress();
    let restored = false;
    if (form.dataset.inventoryAutofocus !== '1') {
      try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        if (saved && Number.isFinite(Number(saved.scrollY))) {
          restored = true;
          requestAnimationFrame(() => requestAnimationFrame(() => window.scrollTo({ top:Number(saved.scrollY), behavior:'auto' })));
        }
      } catch (_) {}
    }
    if (!restored && form.dataset.inventoryAutofocus === '1' && !touchContext()) {
      const firstEmpty = quantityInputs.find((input) => String(input.value || '').trim() === '') || quantityInputs[0];
      if (firstEmpty) requestAnimationFrame(() => focusControl(firstEmpty));
    }
  });
})();

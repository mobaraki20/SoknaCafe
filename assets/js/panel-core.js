(() => {
  'use strict';
  document.documentElement.dataset.panelUiReady = '1';
  window.CafeUI = window.CafeUI || {};
  const toast = document.getElementById('panelToast');
  const panelContent = document.getElementById('panelContent');
  const interaction = window.CafeUI.interaction || window.SoknaInteraction;
  const keyboardInteraction = () => interaction?.isKeyboard?.() ?? true;
  const restoreKeyboardFocus = (target) => {
    if (!keyboardInteraction() || !target?.focus) return false;
    if (interaction?.restoreFocus) return interaction.restoreFocus(target);
    target.focus({ preventScroll: true });
    return true;
  };

  // Shared virtual-keyboard contract. HTML inputmode/enterkeyhint describe intent;
  // this owner only adds mobile focus progression/dismissal and never changes validation.
  const touchKeyboardContext = () => {
    const vv = window.visualViewport;
    const viewportCompressed = Boolean(vv && (window.innerHeight - vv.height) > 96);
    const coarsePrimary = window.matchMedia?.('(pointer: coarse)')?.matches === true;
    return viewportCompressed || (coarsePrimary && Number(navigator.maxTouchPoints || 0) > 0);
  };
  const keyboardFieldVisible = (node) => {
    if (!node || node.hidden || node.disabled || node.closest('[hidden]')) return false;
    if (node instanceof HTMLSelectElement && node.nextElementSibling?.classList?.contains('panel-choice-trigger')) return Boolean(node.nextElementSibling.getClientRects().length);
    return Boolean(node.getClientRects().length);
  };
  const keyboardFocusTarget = (control) => {
    if (control instanceof HTMLSelectElement && control.nextElementSibling?.classList?.contains('panel-choice-trigger')) return control.nextElementSibling;
    return control;
  };
  const keyboardFlowControls = (input) => {
    const scope = input?.form || input?.closest?.('[role="dialog"]') || panelContent || document;
    return [...scope.querySelectorAll('input,textarea,select')].filter((control) => {
      if (!keyboardFieldVisible(control) || control === input) return false;
      if (control instanceof HTMLInputElement) {
        if (['hidden','checkbox','radio','file','button','submit','reset','color'].includes(control.type)) return false;
        if (control.readOnly || control.getAttribute('inputmode') === 'none') return false;
      }
      return true;
    });
  };
  const focusNextKeyboardField = (input) => {
    if (!(input instanceof HTMLInputElement) && !(input instanceof HTMLTextAreaElement)) return false;
    const scope = input.form || input.closest?.('[role="dialog"]') || panelContent || document;
    const ordered = [...scope.querySelectorAll('input,textarea,select')].filter(keyboardFieldVisible);
    const currentIndex = ordered.indexOf(input);
    if (currentIndex < 0) return false;
    const allowed = new Set(keyboardFlowControls(input));
    const next = ordered.slice(currentIndex + 1).find(control => allowed.has(control));
    const target = keyboardFocusTarget(next);
    if (!target?.focus) return false;
    try { target.focus({ preventScroll: false }); } catch (_) { target.focus(); }
    if (target instanceof HTMLInputElement && target.type !== 'search') target.select?.();
    return true;
  };
  const dismissVirtualKeyboard = (control) => {
    if (!(control instanceof HTMLElement)) return false;
    if (document.activeElement === control) control.blur();
    return true;
  };
  CafeUI.keyboard = {
    isTouchContext: touchKeyboardContext,
    dismiss: dismissVirtualKeyboard,
    dismissForTouch: (control) => touchKeyboardContext() ? dismissVirtualKeyboard(control) : false,
    focusNext: focusNextKeyboardField
  };
  const keyboardHintEligible = (input) => input instanceof HTMLInputElement
    && !input.disabled && !input.readOnly && input.getAttribute('inputmode') !== 'none'
    && !['hidden','checkbox','radio','file','button','submit','reset','color','range','time','date','datetime-local','month','week'].includes(input.type);
  const applyKeyboardHints = (root = document) => {
    const candidates = [];
    if (root instanceof HTMLInputElement) candidates.push(root);
    root.querySelectorAll?.('input').forEach(input => candidates.push(input));
    candidates.forEach(input => {
      if (!keyboardHintEligible(input) || input.hasAttribute('enterkeyhint')) return;
      if (input.type === 'search' || input.getAttribute('inputmode') === 'search') { input.setAttribute('enterkeyhint', 'search'); return; }
      const form = input.form;
      if (!(form instanceof HTMLFormElement) || String(form.method || 'get').toLowerCase() !== 'post' || form.hasAttribute('data-enter-native')) return;
      const ordered = [...form.querySelectorAll('input,textarea,select')];
      const index = ordered.indexOf(input);
      const hasNext = index >= 0 && ordered.slice(index + 1).some(control => {
        if (control instanceof HTMLTextAreaElement || control instanceof HTMLSelectElement) return !control.disabled && !control.hidden && !control.closest('[hidden]');
        return keyboardHintEligible(control) && control.type !== 'search';
      });
      input.setAttribute('enterkeyhint', hasNext ? 'next' : 'done');
    });
  };
  CafeUI.keyboard.applyHints = applyKeyboardHints;
  applyKeyboardHints(document);
  let generatedFieldId = 0;
  const nextFieldId = () => {
    let id = '';
    do { id = `panelField${++generatedFieldId}`; } while (document.getElementById(id));
    return id;
  };
  const applyProgrammaticLabels = (root = document) => {
    const groups = [];
    if (root instanceof Element && root.matches('.form-group')) groups.push(root);
    root.querySelectorAll?.('.form-group').forEach((group) => groups.push(group));
    groups.forEach((group) => {
      const visibleLabel = [...group.children].find((node) => node.matches?.('label'));
      if (!visibleLabel) return;
      const clone = visibleLabel.cloneNode(true);
      clone.querySelectorAll('input,textarea,select,button').forEach((node) => node.remove());
      const labelText = String(clone.textContent || '').replace(/\s+/g, ' ').trim();
      if (!labelText) return;
      const controls = [...group.querySelectorAll('input:not([type="hidden"]),textarea,select')].filter((control) => control.closest('label') === null);
      if (controls.length === 1) {
        const control = controls[0];
        if (!control.id) control.id = nextFieldId();
        visibleLabel.htmlFor = control.id;
        return;
      }
      controls.forEach((control, index) => {
        if (control.hasAttribute('aria-label') || control.hasAttribute('aria-labelledby')) return;
        control.setAttribute('aria-label', `${labelText}، بخش ${index + 1}`);
      });
    });
  };
  CafeUI.applyProgrammaticLabels = applyProgrammaticLabels;
  applyProgrammaticLabels(document);
  const enhanceFileInputs = (root = document) => {
    const inputs = [];
    if (root instanceof HTMLInputElement && root.type === 'file') inputs.push(root);
    root.querySelectorAll?.('input[type="file"]').forEach((input) => inputs.push(input));
    inputs.forEach((input) => {
      if (input.dataset.fileUiReady === '1') return;
      input.dataset.fileUiReady = '1';
      input.classList.add('panel-file-input');
      const control = document.createElement('span');
      control.className = 'panel-file-control';
      control.innerHTML = '<button type="button" class="btn btn-light">انتخاب فایل</button><span aria-live="polite">فایلی انتخاب نشده</span>';
      input.insertAdjacentElement('afterend', control);
      control.querySelector('button')?.addEventListener('click', () => input.click());
      input.addEventListener('change', () => { control.querySelector('span').textContent = input.files?.[0]?.name || 'فایلی انتخاب نشده'; });
    });
  };
  CafeUI.enhanceFileInputs = enhanceFileInputs;
  enhanceFileInputs(document);
  document.addEventListener('keydown', event => {
    if (event.defaultPrevented || event.key !== 'Enter' || event.isComposing || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) return;
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) return;
    if (!touchKeyboardContext()) return;
    const hint = String(input.getAttribute('enterkeyhint') || '').toLowerCase();
    if (hint === 'next') {
      event.preventDefault();
      if (!focusNextKeyboardField(input)) dismissVirtualKeyboard(input);
      return;
    }
    if (hint === 'done') {
      event.preventDefault();
      dismissVirtualKeyboard(input);
      return;
    }
    if (hint === 'search' && input.hasAttribute('data-keyboard-dismiss-on-enter')) {
      event.preventDefault();
      dismissVirtualKeyboard(input);
    }
  });

  let toastTimer = 0, lastToastKey = '', lastToastAt = 0;
  const syncToastPosition = () => {
    if (!toast || !panelContent || window.matchMedia('(max-width:720px)').matches) return;
    const rect = panelContent.getBoundingClientRect();
    const left = Math.max(16, Math.min(window.innerWidth - 436, Math.round(rect.left + 16)));
    toast.style.setProperty('--panel-toast-left', `${left}px`);
  };
  CafeUI.toast = (message, type = '', options = {}) => {
    if (!toast) return;
    const text = String(message || '').trim();
    if (!text) return;
    const tone = String(type || 'success');
    const key = `${tone}:${text}`;
    const now = Date.now();
    if (key === lastToastKey && now - lastToastAt < Number(options.dedupeWindow || 2200)) return;
    lastToastKey = key; lastToastAt = now;
    syncToastPosition();
    toast.textContent = text;
    toast.className = `panel-toast ${tone}`.trim();
    toast.classList.remove('hidden');
    clearTimeout(toastTimer);
    const fallback = tone === 'warning' ? 6000 : 4000;
    if (!options.sticky) toastTimer = window.setTimeout(() => toast.classList.add('hidden'), Number(options.timeout || fallback));
  };
  CafeUI.hideToast = () => { clearTimeout(toastTimer); toast?.classList.add('hidden'); };

  // Payroll reminder is a fail-silent, count-only enhancement of the existing Core launcher.
  // It never blocks panel rendering and its short browser cache is not a financial source of truth.
  const payrollReminderTtlMs = 90 * 1000;
  let payrollReminderRequest = null;
  const payrollReminderDigits = value => String(value).replace(/\d/g, digit => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
  const payrollReminderBadge = link => link?.querySelector?.('[data-payroll-reminder-count]') || null;
  const hidePayrollReminderBadge = link => {
    const badge = payrollReminderBadge(link);
    if (!badge) return;
    badge.hidden = true;
    badge.textContent = '';
    badge.setAttribute('aria-hidden', 'true');
    badge.removeAttribute('aria-label');
  };
  const renderPayrollReminderBadge = (link, count) => {
    const badge = payrollReminderBadge(link);
    if (!badge) return;
    const valid = Number.isInteger(count) && count > 0;
    if (!valid) { hidePayrollReminderBadge(link); return; }
    const human = payrollReminderDigits(count);
    badge.textContent = human;
    badge.hidden = false;
    badge.setAttribute('aria-hidden', 'false');
    badge.setAttribute('aria-label', `${human} مورد نیازمند پیگیری حقوق`);
  };
  const payrollReminderCacheKey = link => {
    const userId = String(link?.dataset?.payrollReminderUser || '').trim();
    return userId ? `sokna.payroll-reminder-count.v1.${userId}` : '';
  };
  const readPayrollReminderCache = link => {
    const key = payrollReminderCacheKey(link);
    if (!key) return null;
    try {
      const parsed = JSON.parse(sessionStorage.getItem(key) || 'null');
      if (!parsed || Number(parsed.expiresAt || 0) <= Date.now()) { sessionStorage.removeItem(key); return null; }
      if (parsed.state === 'count' && Number.isInteger(parsed.count) && parsed.count >= 0) return { state: 'count', count: parsed.count };
      if (parsed.state === 'unknown') return { state: 'unknown' };
    } catch (_) {}
    return null;
  };
  const writePayrollReminderCache = (link, state, count = null) => {
    const key = payrollReminderCacheKey(link);
    if (!key) return;
    try {
      const payload = { state, expiresAt: Date.now() + payrollReminderTtlMs };
      if (state === 'count') payload.count = count;
      sessionStorage.setItem(key, JSON.stringify(payload));
    } catch (_) {}
  };
  CafeUI.refreshPayrollReminderBadge = async ({ force = false } = {}) => {
    const link = document.querySelector('[data-center-personnel-link]');
    if (!link || link.hidden || link.closest('[hidden]')) { if (link) hidePayrollReminderBadge(link); return null; }
    const url = String(link.dataset.payrollReminderUrl || '').trim();
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const userId = Number(link.dataset.payrollReminderUser || 0);
    if (!url || !csrf || !Number.isInteger(userId) || userId < 1) { hidePayrollReminderBadge(link); return null; }
    if (!force) {
      const cached = readPayrollReminderCache(link);
      if (cached?.state === 'count') { renderPayrollReminderBadge(link, cached.count); return cached.count; }
      if (cached?.state === 'unknown') { hidePayrollReminderBadge(link); return null; }
    }
    if (payrollReminderRequest) return payrollReminderRequest;
    payrollReminderRequest = (async () => {
      try {
        const response = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          cache: 'no-store',
          credentials: 'same-origin',
          body: JSON.stringify({ csrf_token: csrf })
        });
        const data = await response.json().catch(() => null);
        const count = data && data.success === true && Number.isInteger(data.count) && data.count >= 0 ? data.count : null;
        if (!response.ok || count === null) {
          writePayrollReminderCache(link, 'unknown');
          hidePayrollReminderBadge(link);
          return null;
        }
        writePayrollReminderCache(link, 'count', count);
        renderPayrollReminderBadge(link, count);
        return count;
      } catch (_) {
        writePayrollReminderCache(link, 'unknown');
        hidePayrollReminderBadge(link);
        return null;
      } finally {
        payrollReminderRequest = null;
      }
    })();
    return payrollReminderRequest;
  };

  // Personnel visibility mirrors Center entitlement. Staff stays fail-closed:
  // unknown/unsupported/failed refresh never reveals the launcher; only explicit allow does.
  CafeUI.refreshPersonnelEntitlement = async () => {
    const link = document.querySelector('[data-center-personnel-link]');
    if (!link || link.dataset.centerPersonnelRefresh !== '1') return null;
    const url = String(link.dataset.centerPersonnelUrl || '').trim();
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (!url || !csrf) return null;
    link.dataset.centerPersonnelRefresh = '0';
    const syncGroup = () => {
      const group = link.closest('[data-nav-group],[data-center-personnel-group]');
      if (!group) return;
      group.hidden = !group.querySelector('a[href]:not([hidden])');
    };
    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        cache: 'no-store',
        credentials: 'same-origin',
        body: JSON.stringify({ csrf_token: csrf })
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data?.success !== true) return null;
      if (data?.supported !== true) {
        link.hidden = true;
        hidePayrollReminderBadge(link);
        syncGroup();
        return null;
      }
      if (data.allowed === true) {
        link.hidden = false;
        syncGroup();
        void CafeUI.refreshPayrollReminderBadge();
        return true;
      }
      link.hidden = true;
      hidePayrollReminderBadge(link);
      syncGroup();
      return false;
    } catch (_) {
      return null;
    }
  };
  void CafeUI.refreshPersonnelEntitlement();
  void CafeUI.refreshPayrollReminderBadge();
  window.addEventListener('resize', syncToastPosition, { passive: true });
  window.addEventListener('orientationchange', syncToastPosition);

  const stack = [];
  const focusableSelector = 'a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
  const managedInert = new Set();
  const setInert = (node, value) => {
    if (!node) return;
    node.classList.toggle('panel-dialog-inert', value);
    if ('inert' in node) node.inert = value;
    if (value) managedInert.add(node); else managedInert.delete(node);
  };
  const clearInert = () => { [...managedInert].forEach(node => setInert(node, false)); };
  const activeEntry = () => stack.at(-1) || null;
  const visibleFocusable = (modal) => [...modal.querySelectorAll(focusableSelector)].filter(el => !el.hidden && el.getClientRects().length > 0);
  const syncInert = () => {
    clearInert();
    const modal = activeEntry()?.modal || null;
    document.body.classList.toggle('has-panel-dialog', Boolean(modal));
    if (!modal) return;
    const shell = document.querySelector('.panel-shell');
    if (shell && !shell.contains(modal)) { setInert(shell, true); return; }
    const content = document.getElementById('panelContent');
    if (!content) return;
    [...content.children].forEach(child => {
      if (child === modal || child.contains(modal)) return;
      setInert(child, true);
    });
  };
  const removeFromStack = (modal) => {
    const index = stack.findIndex(entry => entry.modal === modal);
    return index >= 0 ? stack.splice(index, 1)[0] : null;
  };



  CafeUI.bindSwipeDismiss = ({ sheet, handle = sheet, onDismiss, canStart, threshold = 72, maxStartY = 140 } = {}) => {
    if (!(sheet instanceof HTMLElement) || !(handle instanceof HTMLElement) || typeof onDismiss !== 'function') return () => {};
    let startX = 0, startY = 0, deltaY = 0, pointerId = null, tracking = false;
    const reset = () => { tracking = false; pointerId = null; deltaY = 0; sheet.style.transition = ''; sheet.style.transform = ''; };
    const down = (event) => {
      if (event.pointerType === 'mouse' || event.button !== 0) return;
      if (typeof canStart === 'function' && !canStart(event)) return;
      const rect = sheet.getBoundingClientRect();
      if (event.clientY - rect.top > maxStartY) return;
      startX = event.clientX; startY = event.clientY; deltaY = 0; pointerId = event.pointerId; tracking = true;
      try { handle.setPointerCapture?.(pointerId); } catch (_) { /* synthetic/unsupported pointer capture: gesture still works */ }
      sheet.style.transition = 'none';
    };
    const move = (event) => {
      if (!tracking || event.pointerId !== pointerId) return;
      const dx = event.clientX - startX, dy = event.clientY - startY;
      if (dy <= 0 || Math.abs(dx) > Math.max(24, Math.abs(dy) * .75)) return;
      deltaY = dy; sheet.style.transform = `translateY(${Math.min(dy, 220)}px)`;
      event.preventDefault();
    };
    const up = async (event) => {
      if (!tracking || event.pointerId !== pointerId) return;
      const shouldDismiss = deltaY >= threshold;
      sheet.style.transition = 'transform .18s ease';
      if (!shouldDismiss) { sheet.style.transform = ''; window.setTimeout(reset, 190); return; }
      const allowed = await onDismiss();
      if (!allowed) { sheet.style.transform = ''; window.setTimeout(reset, 190); return; }
      reset();
    };
    handle.style.touchAction = 'pan-x';
    handle.addEventListener('pointerdown', down);
    handle.addEventListener('pointermove', move, { passive:false });
    handle.addEventListener('pointerup', up);
    handle.addEventListener('pointercancel', reset);
    return () => { handle.removeEventListener('pointerdown', down); handle.removeEventListener('pointermove', move); handle.removeEventListener('pointerup', up); handle.removeEventListener('pointercancel', reset); reset(); };
  };

  CafeUI.dialog = {
    open(modal, trigger = document.activeElement) {
      if (!modal) return false;
      removeFromStack(modal);
      stack.push({ modal, trigger: trigger instanceof HTMLElement ? trigger : null });
      modal.classList.remove('hidden');
      modal.setAttribute('aria-hidden', 'false');
      syncInert();
      requestAnimationFrame(() => {
        const target = modal.querySelector('[autofocus],[data-dialog-primary]') || visibleFocusable(modal)[0] || modal;
        if (!modal.hasAttribute('tabindex')) modal.tabIndex = -1;
        if (keyboardInteraction()) target?.focus?.({ preventScroll: true });
        else if (trigger instanceof HTMLElement) trigger.blur();
      });
      return true;
    },
    close(modal, restoreFocus = true, reason = 'programmatic') {
      if (!modal) return false;
      const entry = removeFromStack(modal);
      modal.classList.add('hidden');
      modal.setAttribute('aria-hidden', 'true');
      modal.removeAttribute('aria-busy');
      modal.querySelectorAll('[aria-busy="true"]').forEach(el => el.removeAttribute('aria-busy'));
      syncInert();
      modal.dispatchEvent(new CustomEvent('cafe:dialog-closed', { detail: { reason } }));
      if (restoreFocus && keyboardInteraction() && entry?.trigger?.isConnected) requestAnimationFrame(() => restoreKeyboardFocus(entry.trigger));
      return true;
    },
    closeTop(restoreFocus = true, reason = 'programmatic') {
      const modal = activeEntry()?.modal;
      if (!modal || modal.getAttribute('aria-busy') === 'true' || modal.dataset.staticDialog === '1') return false;
      return this.close(modal, restoreFocus, reason);
    },
    closeAll() { [...stack].reverse().forEach(entry => this.close(entry.modal, false, 'close-all')); },
    top() { return activeEntry()?.modal || null; },
    isOpen(modal) { return stack.some(entry => entry.modal === modal); }
  };

  document.addEventListener('click', event => {
    const closer = event.target.closest('[data-dialog-close]');
    if (closer) {
      event.preventDefault();
      const modal = closer.closest('[role="dialog"],.success-modal,.panel-confirm-layer');
      if (modal) CafeUI.dialog.close(modal, true, 'control');
      return;
    }
    const backdrop = event.target.closest('[data-dialog-backdrop]');
    if (backdrop && backdrop === event.target) {
      const modal = backdrop.closest('[role="dialog"],.success-modal,.panel-confirm-layer');
      if (modal && modal.dataset.backdropClose !== '0') CafeUI.dialog.close(modal, true, 'backdrop');
    }
  });
  document.addEventListener('keydown', event => {
    const modal = CafeUI.dialog.top();
    if (!modal) return;
    if (event.key === 'Escape') {
      if (modal.getAttribute('aria-busy') !== 'true' && modal.dataset.escapeClose !== '0') {
        event.preventDefault();
        CafeUI.dialog.closeTop(true, 'escape');
      }
      return;
    }
    if (event.key !== 'Tab') return;
    const nodes = visibleFocusable(modal);
    if (!nodes.length) { event.preventDefault(); modal.focus(); return; }
    const first = nodes[0], last = nodes.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  const confirmLayer = document.getElementById('panelConfirmLayer');
  const confirmTitle = document.getElementById('panelConfirmTitle');
  const confirmMessage = document.getElementById('panelConfirmMessage');
  const confirmOk = confirmLayer?.querySelector('[data-panel-confirm-ok]');
  let pendingConfirm = null;
  const resolveConfirm = (value) => {
    if (!pendingConfirm) return;
    const resolve = pendingConfirm;
    pendingConfirm = null;
    CafeUI.dialog.close(confirmLayer, true, value ? 'confirm' : 'cancel');
    resolve(Boolean(value));
  };
  confirmLayer?.addEventListener('cafe:dialog-closed', () => {
    if (!pendingConfirm) return;
    const resolve = pendingConfirm;
    pendingConfirm = null;
    resolve(false);
  });
  confirmOk?.addEventListener('click', () => resolveConfirm(true));
  document.addEventListener('click', event => {
    if (event.target.closest('[data-panel-confirm-cancel]')) resolveConfirm(false);
  });
  CafeUI.confirm = (message, title = 'تأیید', options = {}) => {
    if (!confirmLayer) { CafeUI.toast('پنجره تأیید در دسترس نیست؛ صفحه را تازه کنید.', 'error'); return Promise.resolve(false); }
    if (pendingConfirm) resolveConfirm(false);
    confirmTitle.textContent = String(title || 'تأیید');
    confirmMessage.textContent = String(message || '');
    confirmOk.textContent = String(options.okLabel || 'تأیید');
    confirmOk.className = `btn ${options.danger ? 'btn-danger' : 'btn-primary'}`;
    confirmLayer.dataset.confirmTone = options.danger ? 'danger' : 'normal';
    confirmLayer.querySelector('[data-panel-confirm-icon-info]')?.classList.toggle('hidden', Boolean(options.danger));
    confirmLayer.querySelector('[data-panel-confirm-icon-danger]')?.classList.toggle('hidden', !options.danger);
    const cancel = confirmLayer.querySelector('.panel-confirm-actions [data-panel-confirm-cancel]');
    if (cancel) cancel.textContent = String(options.cancelLabel || 'انصراف');
    const promise = new Promise(resolve => { pendingConfirm = resolve; });
    CafeUI.dialog.open(confirmLayer, options.trigger || document.activeElement);
    if (keyboardInteraction()) requestAnimationFrame(() => confirmOk?.focus({ preventScroll: true }));
    return promise;
  };

  CafeUI.runAction = async ({ button, loadingText = 'در حال انجام…', request, onSuccess, onError, successText = 'ثبت شد' }) => {
    if (!button || button.dataset.busy === '1') return null;
    const original = button.innerHTML;
    button.dataset.busy = '1';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.textContent = loadingText;
    const modal = button.closest('[role="dialog"],.success-modal,.panel-confirm-layer');
    modal?.setAttribute('aria-busy', 'true');
    try {
      const result = await request();
      button.textContent = successText;
      await onSuccess?.(result);
      return result;
    } catch (error) {
      if (button.isConnected) button.innerHTML = original;
      await onError?.(error);
      throw error;
    } finally {
      if (button.isConnected) {
        button.dataset.busy = '0';
        button.disabled = false;
        button.removeAttribute('aria-busy');
        if (button.textContent === successText) window.setTimeout(() => { if (button.isConnected) button.innerHTML = original; }, 850);
      }
      modal?.removeAttribute('aria-busy');
    }
  };

  const faDigitMap = '۰۱۲۳۴۵۶۷۸۹';
  const arDigitMap = '٠١٢٣٤٥٦٧٨٩';
  const toFaDigits = (value) => String(value ?? '')
    .replace(/[0-9]/g, digit => faDigitMap[Number(digit)])
    .replace(/[٠-٩]/g, digit => faDigitMap[arDigitMap.indexOf(digit)]);
  const toEnDigits = (value) => String(value ?? '')
    .replace(/[۰-۹]/g, digit => String(faDigitMap.indexOf(digit)))
    .replace(/[٠-٩]/g, digit => String(arDigitMap.indexOf(digit)));
  const localizeNumberText = (value) => toFaDigits(String(value ?? '').replace(/\./g, '٫').replace(/,/g, '٬'));
  const normalizeNumberText = (value) => toEnDigits(String(value ?? ''))
    .replace(/[٬,\s]/g, '')
    .replace(/٫/g, '.');
  CafeUI.digits = { toFa: toFaDigits, toEn: toEnDigits, localizeNumberText, normalizeNumberText };

  const numericInputSelector = 'input.form-control[inputmode="numeric"],input.form-control[inputmode="decimal"]';
  const moneyInputSelector = 'input.form-control[data-money-input]';
  const isUserNumericInput = (input) => input instanceof HTMLInputElement
    && input.matches(numericInputSelector)
    && !input.matches('[data-latin-digits],.ltr-input');
  const isMoneyInput = (input) => input instanceof HTMLInputElement && input.matches(moneyInputSelector);
  const normalizeMoneyText = (value) => toEnDigits(String(value ?? ''))
    .replace(/تومان/g, '')
    .replace(/[^0-9]/g, '');
  const formatMoneyText = (value) => {
    const digits = normalizeMoneyText(value).replace(/^0+(?=\d)/, '');
    if (digits === '') return '';
    return toFaDigits(digits.replace(/\B(?=(\d{3})+(?!\d))/g, '٬'));
  };
  const restoreMoneyCaret = (input, digitsBefore) => {
    if (input.selectionStart === null) return;
    if (digitsBefore <= 0) { input.setSelectionRange(0, 0); return; }
    let seen = 0; let pos = input.value.length;
    for (let i = 0; i < input.value.length; i++) {
      if (/[۰-۹0-9]/.test(input.value[i])) seen++;
      if (seen >= digitsBefore) { pos = i + 1; break; }
    }
    input.setSelectionRange(pos, pos);
  };
  const localizeNumericInput = (input) => {
    if (!isUserNumericInput(input)) return;
    const next = isMoneyInput(input) ? formatMoneyText(input.value) : localizeNumberText(input.value);
    if (input.value !== next) input.value = next;
    input.setAttribute('dir', 'rtl');
  };
  CafeUI.money = { normalize: normalizeMoneyText, format: formatMoneyText };
  document.querySelectorAll(numericInputSelector).forEach(localizeNumericInput);
  document.addEventListener('input', event => {
    const input = event.target;
    if (!isUserNumericInput(input)) return;
    if (isMoneyInput(input)) {
      const caret = input.selectionStart ?? input.value.length;
      const digitsBefore = normalizeMoneyText(input.value.slice(0, caret)).length;
      localizeNumericInput(input);
      restoreMoneyCaret(input, digitsBefore);
      return;
    }
    const start = input.selectionStart;
    const before = input.value;
    localizeNumericInput(input);
    if (start !== null && before.length === input.value.length) input.setSelectionRange(start, start);
  });
  document.addEventListener('formdata', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const names = [...new Set([...form.querySelectorAll(numericInputSelector)]
      .filter(isUserNumericInput)
      .map(input => input.name)
      .filter(Boolean))];
    names.forEach(name => {
      const controls = [...form.querySelectorAll(numericInputSelector)].filter(input => isUserNumericInput(input) && input.name === name);
      const values = event.formData.getAll(name).map((value, index) => {
        const control = controls[index] || controls[0];
        return control && isMoneyInput(control) ? normalizeMoneyText(value) : normalizeNumberText(value);
      });
      event.formData.delete(name);
      values.forEach(value => event.formData.append(name, value));
    });
  });
  document.addEventListener('click', event => {
    const trigger = event.target.closest?.('[data-fill-money-target]');
    if (!trigger) return;
    const input = document.getElementById(String(trigger.dataset.fillMoneyTarget || ''));
    if (!(input instanceof HTMLInputElement)) return;
    input.value = isMoneyInput(input)
      ? formatMoneyText(String(trigger.dataset.fillMoneyValue || ''))
      : localizeNumberText(String(trigger.dataset.fillMoneyValue || ''));
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.focus();
    input.setSelectionRange?.(input.value.length, input.value.length);
  });
  if (panelContent && 'MutationObserver' in window) {
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
      if (!(node instanceof Element)) return;
      if (isUserNumericInput(node)) localizeNumericInput(node);
      node.querySelectorAll?.(numericInputSelector).forEach(localizeNumericInput);
      applyKeyboardHints(node);
      applyProgrammaticLabels(node);
      enhanceFileInputs(node);
    }))).observe(panelContent, { childList: true, subtree: true });
  }


  CafeUI.normalizeRequestError = (error) => {
    const source = error || {};
    const status = Number(source.httpStatus || source.status || source.response?.status || 0);
    const raw = String(source?.data?.message || source.message || '').trim();
    const network = !status && (source.name === 'TypeError' || /failed to fetch|networkerror|network request failed|load failed/i.test(raw));
    const timeout = source.name === 'AbortError' || /timeout|timed out/i.test(raw);
    return { status, raw, network, timeout };
  };
  CafeUI.requestErrorMessage = (error, fallback = 'درخواست کامل نشد. دوباره تلاش کنید.') => {
    const info = CafeUI.normalizeRequestError(error);
    if (info.timeout) return 'ارتباط بیش از حد طول کشید. دوباره تلاش کنید.';
    if (info.network) return navigator.onLine === false
      ? 'اتصال اینترنت برقرار نیست. پس از برقراری اتصال دوباره تلاش کنید.'
      : 'ارتباط با سامانه برقرار نشد. اتصال را بررسی و دوباره تلاش کنید.';
    if (info.status === 401) return 'نشست شما پایان یافته است. دوباره وارد شوید.';
    if (info.status === 403) return 'برای انجام این عملیات دسترسی لازم را ندارید.';
    if (info.status === 419) return 'نشست صفحه منقضی شده است. صفحه را تازه کنید.';
    if (info.status === 429) return 'درخواست‌ها بیش از حد سریع ارسال شده‌اند. کمی بعد دوباره تلاش کنید.';
    if (info.status >= 500) return 'سامانه موقتاً نتوانست عملیات را کامل کند. دوباره تلاش کنید.';
    if (info.raw && !/failed to fetch|networkerror|network request failed|load failed/i.test(info.raw)) return info.raw;
    return fallback;
  };

  document.querySelectorAll('input[type="text"]:not([dir]),input[type="search"]:not([dir]),textarea:not([dir])').forEach(el => el.setAttribute('dir', 'auto'));
  const rowNavigationInteractive = (target, row) => {
    const interactive = target?.closest?.('a[href],button,input,select,textarea,summary,details,form,[role=\"button\"],[role=\"link\"]');
    return Boolean(interactive && interactive !== row);
  };
  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button > 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const row = event.target.closest?.('[data-row-href]');
    if (!row || rowNavigationInteractive(event.target, row)) return;
    const href = String(row.dataset.rowHref || '').trim();
    if (href) window.location.assign(href);
  });
  document.addEventListener('keydown', event => {
    if (event.defaultPrevented || event.key !== 'Enter' || event.isComposing) return;
    const row = event.target.closest?.('[data-row-href]');
    if (!row || event.target !== row) return;
    event.preventDefault();
    const href = String(row.dataset.rowHref || '').trim();
    if (href) window.location.assign(href);
  });

  const autoSubmitControl = control => {
    if (!(control instanceof Element) || !control.matches('[data-auto-submit]')) return;
    const form = control.closest('form');
    if (form instanceof HTMLFormElement) form.requestSubmit();
  };
  document.addEventListener('change', event => autoSubmitControl(event.target));
  document.addEventListener('panel:choice-change', event => autoSubmitControl(event.target));
  document.addEventListener('panel:choice-change', event => {
    const select = event.target;
    const form = select instanceof HTMLSelectElement ? select.closest('form.report-filter-grid') : null;
    if (!form || form.dataset.reportAutoSubmit === '0') return;
    if (select.name === 'period' && select.value === 'custom') return;
    if (!['period','shift','user'].includes(select.name)) return;
    const exportAction = form.querySelector('button[name="action"][value="export"]');
    if (exportAction) exportAction.disabled = true;
    form.requestSubmit();
  });

  document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-confirm]');
    if (!form || form.dataset.confirmed === '1') return;
    event.preventDefault();
    const accepted = await CafeUI.confirm(form.dataset.confirm, form.dataset.confirmTitle || 'تأیید', {
      okLabel: form.dataset.confirmOk || 'ادامه', danger: form.dataset.confirmDanger === '1', trigger: event.submitter
    });
    if (!accepted) return;
    form.dataset.confirmed = '1';
    form.requestSubmit(event.submitter || undefined);
  });
  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-click-confirm]');
    if (!button || button.dataset.confirmed === '1') return;
    event.preventDefault();
    const accepted = await CafeUI.confirm(button.dataset.clickConfirm, button.dataset.confirmTitle || 'تأیید', {
      okLabel: button.dataset.confirmOk || 'ادامه', danger: button.dataset.confirmDanger === '1', trigger: button
    });
    if (!accepted) return;
    button.dataset.confirmed = '1';
    button.click();
    delete button.dataset.confirmed;
  });
})();

(() => {
  'use strict';

  const SELECTOR = '[data-jalali-date]';
  const enhanced = new WeakMap();
  const faDigits = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
  const enDigits = (value) => String(value || '')
    .replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
    .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
  const pad = (value) => String(value).padStart(2, '0');
  const j2g = (jy, jm, jd) => {
    jy += 1595;
    let days = -355668 + (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + jd;
    days += jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186;
    let gy = 400 * Math.floor(days / 146097); days %= 146097;
    if (days > 36524) { gy += 100 * Math.floor(--days / 36524); days %= 36524; if (days >= 365) days++; }
    gy += 4 * Math.floor(days / 1461); days %= 1461;
    if (days > 365) { gy += Math.floor((days - 1) / 365); days = (days - 1) % 365; }
    let gd = days + 1;
    const leap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0;
    const months = [0,31,leap ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
    let gm = 1; while (gm <= 12 && gd > months[gm]) { gd -= months[gm]; gm++; }
    return [gy, gm, gd];
  };
  const monthNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
  const weekdayNames = ['ش','ی','د','س','چ','پ','ج'];
  const currentJalali = () => {
    try {
      const parts = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { year:'numeric',month:'numeric',day:'numeric',timeZone:'Asia/Tehran' }).formatToParts(new Date());
      const value = Object.fromEntries(parts.map((part) => [part.type, enDigits(part.value)]));
      return [Number(value.year), Number(value.month), Number(value.day)];
    } catch (_) { return [1405, 1, 1]; }
  };
  const parse = (value) => {
    const match = enDigits(value).trim().replace(/[.-]/g, '/').match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
    return match ? [Number(match[1]), Number(match[2]), Number(match[3])] : null;
  };
  const monthDays = (year, month) => {
    if (month <= 6) return 31;
    if (month <= 11) return 30;
    const a = j2g(year, 12, 1), b = j2g(year + 1, 1, 1);
    return Math.round((Date.UTC(b[0], b[1]-1, b[2]) - Date.UTC(a[0], a[1]-1, a[2])) / 86400000);
  };

  const dialog = document.createElement('dialog');
  dialog.className = 'jalali-picker-dialog';
  dialog.setAttribute('aria-label', 'انتخاب تاریخ شمسی');
  dialog.innerHTML = `<form method="dialog"><header><button type="button" data-jalali-next aria-label="ماه بعد">${window.SoknaIcons?.markup('chevron-left') || ''}</button><strong data-jalali-title></strong><button type="button" data-jalali-prev aria-label="ماه قبل">${window.SoknaIcons?.markup('chevron-right') || ''}</button></header><div class="jalali-weekdays">${weekdayNames.map((name) => `<span>${name}</span>`).join('')}</div><div class="jalali-days" data-jalali-days></div><footer><button class="btn btn-primary" type="button" data-jalali-today>امروز</button><button class="btn btn-light" value="cancel">انصراف</button></footer></form>`;
  document.body.appendChild(dialog);

  const embeddedMarkup = () => `<section class="jalali-picker-inline hidden" aria-label="انتخاب تاریخ شمسی"><header><button type="button" data-jalali-next aria-label="ماه بعد">${window.SoknaIcons?.markup('chevron-left') || ''}</button><strong data-jalali-title></strong><button type="button" data-jalali-prev aria-label="ماه قبل">${window.SoknaIcons?.markup('chevron-right') || ''}</button></header><div class="jalali-weekdays">${weekdayNames.map((name) => `<span>${name}</span>`).join('')}</div><div class="jalali-days" data-jalali-days></div><footer><button class="btn btn-primary" type="button" data-jalali-today>امروز</button><button class="btn btn-light" type="button" data-jalali-inline-cancel>بستن</button></footer></section>`;
  const isEmbedded = (input) => Boolean(input?.closest?.('[data-financial-filter-sheet]'));
  const activeRoot = () => activeInline || dialog;

  let activeInput = null;
  let activeInline = null;
  let view = currentJalali();
  const touchPrimary = window.matchMedia?.('(pointer: coarse)') || null;

  const setExpanded = (input, expanded) => {
    if (!input) return;
    input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    if (!input.id) return;
    const trigger = document.querySelector(`[data-open-jalali="${CSS.escape(input.id)}"]`);
    if (trigger) trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  };

  const enhance = (input) => {
    if (!(input instanceof HTMLInputElement) || !input.matches(SELECTOR) || enhanced.has(input)) return;
    let inline = null;
    if (isEmbedded(input)) {
      inline = document.createElement('div');
      inline.innerHTML = embeddedMarkup();
      inline = inline.firstElementChild;
      input.closest('.jalali-date-control')?.insertAdjacentElement('afterend', inline);
    }
    enhanced.set(input, { inline });
    const popupRole = inline ? 'grid' : 'dialog';
    input.setAttribute('aria-haspopup', popupRole);
    input.setAttribute('aria-expanded', 'false');
    const trigger = input.id ? document.querySelector(`[data-open-jalali="${CSS.escape(input.id)}"]`) : null;
    if (trigger) {
      trigger.setAttribute('aria-haspopup', popupRole);
      trigger.setAttribute('aria-expanded', 'false');
    }
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('inputmode', touchPrimary?.matches ? 'none' : 'numeric');
    if (touchPrimary?.matches) input.dataset.jalaliMobilePickerOnly = '1';
  };

  const enhanceWithin = (root = document) => {
    if (root instanceof Element && root.matches?.(SELECTOR)) enhance(root);
    root.querySelectorAll?.(SELECTOR).forEach(enhance);
  };

  const syncInputMode = () => {
    document.querySelectorAll(SELECTOR).forEach((input) => {
      enhance(input);
      if (touchPrimary?.matches) {
        input.setAttribute('inputmode', 'none');
        input.dataset.jalaliMobilePickerOnly = '1';
      } else {
        input.setAttribute('inputmode', 'numeric');
        delete input.dataset.jalaliMobilePickerOnly;
      }
    });
  };

  const render = () => {
    const [year, month] = view;
    const root = activeRoot();
    root.querySelector('[data-jalali-title]').textContent = `${monthNames[month - 1]} ${faDigits(year)}`;
    const [gy, gm, gd] = j2g(year, month, 1);
    const offset = (new Date(Date.UTC(gy, gm - 1, gd)).getUTCDay() + 1) % 7;
    const selected = parse(activeInput?.value);
    const today = currentJalali();
    const days = [];
    for (let index = 0; index < offset; index++) days.push('<span class="is-empty"></span>');
    for (let day = 1; day <= monthDays(year, month); day++) {
      const isSelected = selected && selected[0] === year && selected[1] === month && selected[2] === day;
      const isToday = today[0] === year && today[1] === month && today[2] === day;
      days.push(`<button type="button" data-jalali-day="${day}" class="${isSelected ? 'is-selected' : ''} ${isToday ? 'is-today' : ''}">${faDigits(day)}</button>`);
    }
    while (days.length < 42) days.push('<span class="is-empty" aria-hidden="true"></span>');
    root.querySelector('[data-jalali-days]').innerHTML = days.slice(0, 42).join('');
  };

  const shiftMonth = (delta) => {
    view[1] += delta;
    while (view[1] < 1) { view[1] += 12; view[0]--; }
    while (view[1] > 12) { view[1] -= 12; view[0]++; }
    render();
  };

  const open = (input) => {
    if (!(input instanceof HTMLInputElement) || !input.matches(SELECTOR) || input.disabled) return;
    enhance(input);
    const inline = enhanced.get(input)?.inline || null;
    if (activeInline && activeInline !== inline) { activeInline.classList.add('hidden'); activeInline = null; setExpanded(activeInput, false); }
    if (dialog.open && inline) { if (typeof dialog.close === 'function') dialog.close('context-switch'); else dialog.removeAttribute('open'); }
    if (!inline && dialog.open && activeInput !== input) setExpanded(activeInput, false);
    activeInput = input;
    activeInline = inline;
    view = parse(input.value) || currentJalali();
    render();
    setExpanded(activeInput, true);
    if (inline) { inline.classList.remove('hidden'); inline.scrollIntoView({block:'nearest'}); return; }
    if (dialog.open) return;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  };

  const close = (returnValue = 'cancel') => {
    if (activeInline) {
      const input = activeInput;
      activeInline.classList.add('hidden');
      activeInline = null;
      setExpanded(input, false);
      activeInput = null;
      return;
    }
    if (!dialog.open) return;
    if (typeof dialog.close === 'function') dialog.close(returnValue);
    else dialog.removeAttribute('open');
  };

  const commit = (parts, reason = 'selected') => {
    if (!activeInput) return;
    activeInput.value = faDigits(`${parts[0]}/${pad(parts[1])}/${pad(parts[2])}`);
    activeInput.dispatchEvent(new Event('input', { bubbles:true }));
    activeInput.dispatchEvent(new Event('change', { bubbles:true }));
    close(reason);
  };

  enhanceWithin(document);
  syncInputMode();
  touchPrimary?.addEventListener?.('change', syncInputMode);
  const panel = document.querySelector('.panel-content');
  if (panel) {
    const observer = new MutationObserver((mutations) => mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
      if (node instanceof Element) enhanceWithin(node);
    })));
    observer.observe(panel, { childList:true, subtree:true });
  }
  document.addEventListener('panel:enhance-jalali', (event) => enhanceWithin(event.detail?.root || document));

  // On touch devices stop focus before the browser can raise a native keyboard.
  document.addEventListener('pointerdown', (event) => {
    if (!touchPrimary?.matches) return;
    const trigger = event.target.closest('[data-open-jalali]');
    const input = trigger ? document.getElementById(trigger.dataset.openJalali || '') : event.target.closest(SELECTOR);
    if (input?.matches?.(SELECTOR)) event.preventDefault();
  }, true);
  document.addEventListener('pointerup', (event) => {
    if (!touchPrimary?.matches) return;
    if (event.target.closest('[data-open-jalali], [data-jalali-date]')) event.preventDefault();
  }, true);

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-open-jalali]');
    if (trigger) {
      event.preventDefault();
      const input = document.getElementById(trigger.dataset.openJalali || '');
      if (input?.matches(SELECTOR)) open(input);
      return;
    }
    const input = event.target.closest(SELECTOR);
    if (input) {
      event.preventDefault();
      open(input);
    }
  });

  let swipeSuppressClickUntil = 0;
  const handlePickerAction = (event, root) => {
    const day = event.target.closest('[data-jalali-day]');
    if (day && performance.now() < swipeSuppressClickUntil) return true;
    if (day && activeInput) { commit([view[0], view[1], Number(day.dataset.jalaliDay)], 'selected'); return true; }
    if (event.target.closest('[data-jalali-prev]')) { shiftMonth(-1); return true; }
    if (event.target.closest('[data-jalali-next]')) { shiftMonth(1); return true; }
    if (event.target.closest('[data-jalali-today]')) { commit(currentJalali(), 'today'); return true; }
    if (event.target.closest('[data-jalali-inline-cancel]')) { close('cancel'); return true; }
    return false;
  };

  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) {
      const rect = dialog.getBoundingClientRect();
      const outside = event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom;
      if (outside) close('backdrop');
      return;
    }
    handlePickerAction(event, dialog);
  });

  document.addEventListener('click', (event) => {
    const inline = event.target.closest('.jalali-picker-inline');
    if (!inline || inline !== activeInline) return;
    event.preventDefault();
    handlePickerAction(event, inline);
  });

  // One gesture owner for both the modal picker and dynamically-created inline pickers.
  // Vertical movement remains native scrolling; only a deliberate horizontal swipe changes month.
  let swipeStart = null;
  document.addEventListener('pointerdown', (event) => {
    const grid = event.target.closest?.('[data-jalali-days]');
    const root = grid?.closest?.('.jalali-picker-dialog,.jalali-picker-inline');
    if (!grid || !root || (root !== dialog && root !== activeInline)) return;
    if (event.pointerType === 'mouse' || event.button !== 0) return;
    swipeStart = { x:event.clientX, y:event.clientY, id:event.pointerId, grid };
  });
  document.addEventListener('pointerup', (event) => {
    if (!swipeStart || swipeStart.id !== event.pointerId) { swipeStart = null; return; }
    const dx = event.clientX - swipeStart.x;
    const dy = event.clientY - swipeStart.y;
    swipeStart = null;
    if (Math.abs(dx) < 42 || Math.abs(dx) <= Math.abs(dy) * 1.2) return;
    swipeSuppressClickUntil = performance.now() + 350;
    // Same contract as header arrows: swipe left = next month, swipe right = previous month.
    shiftMonth(dx < 0 ? 1 : -1);
  });
  document.addEventListener('pointercancel', () => { swipeStart = null; });

  dialog.addEventListener('close', () => {
    if (activeInline) return;
    setExpanded(activeInput, false);
    activeInput = null;
  });
})();

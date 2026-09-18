(() => {
  'use strict';

  const SELECTOR = '.panel-content input.form-control[type="time"]';
  const interaction = window.CafeUI?.interaction || window.SoknaInteraction;
  const keyboardInteraction = () => interaction?.isKeyboard?.() ?? true;
  const restoreKeyboardFocus = (target) => {
    if (!keyboardInteraction() || !target?.focus) return false;
    if (interaction?.restoreFocus) return interaction.restoreFocus(target);
    target.focus({ preventScroll: true });
    return true;
  };
  const enhanced = new WeakMap();
  let active = null;

  const faDigits = (value) => String(value ?? '').replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
  const normalize = (value) => {
    const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})/);
    if (!match) return '';
    const hour = Math.max(0, Math.min(23, Number(match[1])));
    const minute = Math.max(0, Math.min(59, Number(match[2])));
    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
  };

  const layer = document.createElement('div');
  layer.className = 'panel-time-layer hidden';
  layer.id = 'panelTimeLayer';
  layer.setAttribute('role', 'dialog');
  layer.setAttribute('aria-modal', 'true');
  layer.setAttribute('aria-hidden', 'true');
  layer.innerHTML = `
    <div class="panel-time-backdrop" data-panel-time-close aria-hidden="true"></div>
    <section class="panel-time-sheet" role="document" aria-labelledby="panelTimeTitle">
      <header class="panel-time-head">
        <div><h2 id="panelTimeTitle">ساعت</h2></div>
        <button class="panel-time-close" type="button" data-panel-time-close aria-label="بستن">${window.SoknaIcons?.markup('close') || ''}</button>
      </header>
      <div class="panel-time-body">
        <div class="panel-time-period" role="radiogroup" aria-label="قبل یا بعد از ظهر">
          <button type="button" data-panel-time-period="am" role="radio" aria-checked="false">قبل از ظهر</button>
          <button type="button" data-panel-time-period="pm" role="radio" aria-checked="false">بعد از ظهر</button>
        </div>
        <section><div class="panel-time-section-title"><strong>ساعت</strong><span data-panel-time-hour-value>۱۲</span></div><div class="panel-time-grid panel-time-hours" role="listbox" aria-label="ساعت"></div></section>
        <section><div class="panel-time-section-title"><strong>دقیقه</strong><span data-panel-time-minute-value>۰۰</span></div><div class="panel-time-grid panel-time-minutes" role="listbox" aria-label="دقیقه"></div></section>
      </div>
      <footer class="panel-time-actions"><button class="btn btn-primary" type="button" data-panel-time-apply>ثبت ساعت</button><button class="btn btn-light" type="button" data-panel-time-close>انصراف</button></footer>
    </section>`;
  document.body.appendChild(layer);

  const sheet = layer.querySelector('.panel-time-sheet');
  const title = layer.querySelector('#panelTimeTitle');
  const hours = layer.querySelector('.panel-time-hours');
  const minutes = layer.querySelector('.panel-time-minutes');
  const hourValue = layer.querySelector('[data-panel-time-hour-value]');
  const minuteValue = layer.querySelector('[data-panel-time-minute-value]');
  const apply = layer.querySelector('[data-panel-time-apply]');
  const periodButtons = [...layer.querySelectorAll('[data-panel-time-period]')];

  const safeLabel = (input) => {
    const aria = String(input.getAttribute('aria-label') || '').trim();
    if (aria) return aria;
    if (input.id) {
      const external = document.querySelector(`label[for="${CSS.escape(input.id)}"]`);
      if (external) return String(external.textContent || '').replace(/\s+/g, ' ').trim();
    }
    const parent = input.closest('label');
    if (parent) {
      const span = parent.querySelector(':scope > span');
      if (span) return String(span.textContent || '').replace(/\s+/g, ' ').trim();
    }
    return 'انتخاب ساعت';
  };

  const minuteStep = (input) => {
    const explicit = Number(input.dataset.minuteStep || 0);
    if ([1, 5, 10, 15, 20, 30].includes(explicit)) return explicit;
    const rawStep = input.getAttribute('step');
    if (rawStep) {
      const seconds = Number(rawStep);
      const derived = Number.isFinite(seconds) && seconds > 0 ? Math.max(1, Math.round(seconds / 60)) : 15;
      if ([1, 5, 10, 15, 20, 30].includes(derived)) return derived;
    }
    console.warn('Sokna time input has no semantic minute step; falling back to 15 minutes.', input.name || input.id || input);
    return 15;
  };

  const toTwelveHour = (hour24) => ({
    period: hour24 >= 12 ? 'pm' : 'am',
    hour12: (hour24 % 12) || 12,
  });
  const toTwentyFourHour = (hour12, period) => {
    const hour = Math.max(1, Math.min(12, Number(hour12) || 12));
    if (period === 'am') return hour === 12 ? 0 : hour;
    return hour === 12 ? 12 : hour + 12;
  };
  const periodLabel = (period) => period === 'pm' ? 'بعد از ظهر' : 'قبل از ظهر';
  const displayValue = (input) => {
    const canonical = normalize(input.value);
    if (!canonical) return 'انتخاب ساعت';
    const [hour24, minute] = canonical.split(':').map(Number);
    const { hour12, period } = toTwelveHour(hour24);
    return `${faDigits(hour12)}:${faDigits(String(minute).padStart(2, '0'))} ${periodLabel(period)}`;
  };

  const syncTrigger = (input) => {
    const state = enhanced.get(input);
    if (!state) return;
    state.trigger.querySelector('[data-panel-time-value]').textContent = displayValue(input);
    state.trigger.disabled = input.disabled;
    state.trigger.classList.toggle('is-empty', normalize(input.value) === '');
    state.trigger.classList.toggle('hidden', input.classList.contains('hidden') || input.hidden);
    state.trigger.setAttribute('aria-invalid', input.getAttribute('aria-invalid') === 'true' ? 'true' : 'false');
  };

  const renderButtons = (container, values, selected, type) => {
    container.replaceChildren();
    values.forEach((value) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'panel-time-option';
      button.dataset[type] = String(value);
      button.setAttribute('role', 'option');
      button.setAttribute('aria-selected', value === selected ? 'true' : 'false');
      button.textContent = type === 'hour' ? faDigits(String(value)) : faDigits(String(value).padStart(2, '0'));
      if (value === selected) button.classList.add('is-selected');
      container.appendChild(button);
    });
  };

  const updateSelection = () => {
    if (!active) return;
    hourValue.textContent = faDigits(String(active.hour12));
    minuteValue.textContent = faDigits(String(active.minute).padStart(2, '0'));
    periodButtons.forEach((button) => {
      const selected = button.dataset.panelTimePeriod === active.period;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-checked', selected ? 'true' : 'false');
    });
    hours.querySelectorAll('.panel-time-option').forEach((button) => {
      const selected = Number(button.dataset.hour) === active.hour12;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-selected', selected ? 'true' : 'false');
    });
    minutes.querySelectorAll('.panel-time-option').forEach((button) => {
      const selected = Number(button.dataset.minute) === active.minute;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-selected', selected ? 'true' : 'false');
    });
  };

  const close = ({ restoreFocus = keyboardInteraction() } = {}) => {
    if (!active) return;
    const trigger = active.trigger;
    active = null;
    sheet.style.transform = '';
    sheet.style.transition = '';
    layer.classList.add('hidden');
    layer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
    trigger.setAttribute('aria-expanded', 'false');
    if (restoreFocus) restoreKeyboardFocus(trigger);
    else interaction?.clearPointerFocus?.(layer);
  };

  const open = (input) => {
    const state = enhanced.get(input);
    if (!state || input.disabled) return;
    if (active && active.input !== input) close({ restoreFocus: false });
    const current = normalize(input.value) || '00:00';
    const [hour24, minute] = current.split(':').map(Number);
    const { hour12, period } = toTwelveHour(hour24);
    const step = minuteStep(input);
    const minuteValues = Array.from({ length: Math.ceil(60 / step) }, (_, index) => index * step).filter((value) => value < 60);
    let snappedMinute = minuteValues.reduce((best, value) => Math.abs(value - minute) < Math.abs(best - minute) ? value : best, minuteValues[0] ?? 0);
    if (step === 1) snappedMinute = minute;
    active = { input, trigger: state.trigger, hour12, period, minute: snappedMinute, step };
    title.textContent = safeLabel(input);
    renderButtons(hours, Array.from({ length: 12 }, (_, index) => index + 1), hour12, 'hour');
    renderButtons(minutes, minuteValues, snappedMinute, 'minute');
    updateSelection();
    layer.classList.remove('hidden');
    layer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
    state.trigger.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(() => {
      hours.querySelector('.is-selected')?.scrollIntoView({ block: 'nearest' });
      minutes.querySelector('.is-selected')?.scrollIntoView({ block: 'nearest' });
      if (keyboardInteraction()) (hours.querySelector('.is-selected') || hours.querySelector('button') || layer.querySelector('.panel-time-close'))?.focus({ preventScroll: true });
      else state.trigger.blur();
    });
  };

  const enhance = (input) => {
    if (!(input instanceof HTMLInputElement) || input.type !== 'time' || enhanced.has(input)) return;
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'form-control panel-time-trigger';
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', 'panelTimeLayer');
    trigger.setAttribute('aria-label', safeLabel(input));
    trigger.innerHTML = '<span data-panel-time-value></span><i aria-hidden="true"></i>';
    input.insertAdjacentElement('afterend', trigger);
    input.classList.add('panel-time-source');
    input.tabIndex = -1;
    input.setAttribute('aria-hidden', 'true');
    enhanced.set(input, { trigger });
    syncTrigger(input);

    trigger.addEventListener('click', () => open(input));
    input.addEventListener('change', () => syncTrigger(input));
    input.addEventListener('input', () => syncTrigger(input));
    input.addEventListener('focus', () => trigger.focus({ preventScroll: true }));
    const observer = new MutationObserver(() => syncTrigger(input));
    observer.observe(input, { attributes: true, attributeFilter: ['disabled', 'hidden', 'class', 'aria-invalid'] });
  };

  const enhanceWithin = (root = document) => {
    if (root instanceof Element && root.matches?.(SELECTOR)) enhance(root);
    root.querySelectorAll?.(SELECTOR).forEach(enhance);
  };

  enhanceWithin(document);
  const panel = document.querySelector('.panel-content');
  if (panel) {
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
        if (node instanceof Element) enhanceWithin(node);
      }));
    });
    observer.observe(panel, { childList: true, subtree: true });
  }
  document.addEventListener('panel:enhance-time', (event) => enhanceWithin(event.detail?.root || document));

  periodButtons.forEach((button) => button.addEventListener('click', () => {
    if (!active) return;
    active.period = button.dataset.panelTimePeriod === 'pm' ? 'pm' : 'am';
    updateSelection();
  }));
  hours.addEventListener('click', (event) => {
    const button = event.target.closest('[data-hour]');
    if (!button || !active) return;
    active.hour12 = Number(button.dataset.hour);
    updateSelection();
  });
  minutes.addEventListener('click', (event) => {
    const button = event.target.closest('[data-minute]');
    if (!button || !active) return;
    active.minute = Number(button.dataset.minute);
    updateSelection();
  });
  apply.addEventListener('click', () => {
    if (!active) return;
    const input = active.input;
    const hour24 = toTwentyFourHour(active.hour12, active.period);
    input.value = `${String(hour24).padStart(2, '0')}:${String(active.minute).padStart(2, '0')}`;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.removeAttribute('aria-invalid');
    syncTrigger(input);
    close();
  });
  layer.querySelectorAll('[data-panel-time-close]').forEach((button) => button.addEventListener('click', () => close()));

  document.addEventListener('keydown', (event) => {
    if (!active) return;
    if (event.key === 'Escape') { event.preventDefault(); close(); return; }
    if (event.key !== 'Tab') return;
    const focusables = [...layer.querySelectorAll('button:not(:disabled)')];
    if (!focusables.length) return;
    const first = focusables[0], last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  document.addEventListener('invalid', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !enhanced.has(input)) return;
    requestAnimationFrame(() => {
      syncTrigger(input);
      const trigger = enhanced.get(input).trigger;
      trigger.setAttribute('aria-invalid', 'true');
      trigger.focus({ preventScroll: false });
    });
  }, true);

})();

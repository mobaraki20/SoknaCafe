(() => {
  'use strict';

  const SELECTOR = '.panel-content select.form-control:not([multiple])';
  const enhanced = new WeakMap();
  let active = null;
  let activeInline = null;
  const interaction = window.CafeUI?.interaction || window.SoknaInteraction;
  const interactionModality = () => interaction?.current?.() || 'keyboard';
  const keyboardInteraction = () => interaction?.isKeyboard?.() ?? true;
  const restoreKeyboardFocus = (target) => {
    if (!keyboardInteraction() || !target?.focus) return false;
    if (interaction?.restoreFocus) return interaction.restoreFocus(target);
    target.focus({ preventScroll: true });
    return true;
  };

  const layer = document.createElement('div');
  layer.className = 'panel-choice-layer hidden';
  layer.id = 'panelChoiceLayer';
  layer.setAttribute('role', 'dialog');
  layer.setAttribute('aria-modal', 'true');
  layer.setAttribute('aria-hidden', 'true');
  layer.innerHTML = `
    <div class="panel-choice-backdrop" data-panel-choice-close aria-hidden="true"></div>
    <section class="panel-choice-sheet" role="document" aria-labelledby="panelChoiceTitle">
      <div class="panel-choice-grip" data-panel-choice-drag aria-hidden="true"></div>
      <header class="panel-choice-head" data-panel-choice-drag>
        <h2 id="panelChoiceTitle">انتخاب کنید</h2>
        <button class="panel-choice-close" type="button" data-panel-choice-close aria-label="بستن">${window.SoknaIcons?.markup('close') || ''}</button>
      </header>
      <div class="panel-choice-search hidden" data-panel-choice-search>
        <div class="panel-choice-search-field">
          <input class="form-control" type="search" autocomplete="off" inputmode="search" enterkeyhint="search" placeholder="جست‌وجو" aria-label="جست‌وجو در گزینه‌ها" aria-controls="panelChoiceList">
          <button class="panel-choice-search-clear hidden" type="button" data-panel-choice-search-clear aria-label="پاک‌کردن جست‌وجو">${window.SoknaIcons?.markup('close') || ''}</button>
        </div>
      </div>
      <div class="panel-choice-list" id="panelChoiceList" role="listbox"></div>
      <div class="panel-choice-empty hidden" data-panel-choice-empty><span data-panel-choice-empty-text>نتیجه‌ای پیدا نشد.</span><button type="button" class="btn btn-sm btn-light" data-panel-choice-empty-clear>پاک‌کردن جست‌وجو</button></div>
    </section>`;
  document.body.appendChild(layer);

  const sheet = layer.querySelector('.panel-choice-sheet');
  const list = layer.querySelector('.panel-choice-list');
  const title = layer.querySelector('#panelChoiceTitle');
  const searchWrap = layer.querySelector('[data-panel-choice-search]');
  const searchInput = searchWrap.querySelector('input');
  const searchClear = layer.querySelector('[data-panel-choice-search-clear]');
  const emptyState = layer.querySelector('[data-panel-choice-empty]');
  const emptyText = layer.querySelector('[data-panel-choice-empty-text]');
  const emptyClear = layer.querySelector('[data-panel-choice-empty-clear]');

  const cleanLabelText = (node) => {
    if (!(node instanceof Element)) return '';
    const clone = node.cloneNode(true);
    clone.querySelectorAll('select,input,textarea,button,option,optgroup').forEach((child) => child.remove());
    return String(clone.textContent || '').replace(/\s+/g, ' ').trim().replace(/[،,:؛]+$/u, '');
  };

  const labelFor = (select) => {
    const aria = String(select.getAttribute('aria-label') || '').trim();
    if (aria) return aria;
    const dataLabel = String(select.dataset.choiceLabel || '').trim();
    if (dataLabel) return dataLabel;
    if (select.id) {
      const external = document.querySelector(`label[for="${CSS.escape(select.id)}"]`);
      const text = cleanLabelText(external);
      if (text) return text;
    }
    const parent = select.closest('label');
    const parentText = cleanLabelText(parent);
    if (parentText) return parentText;
    const groupLabel = select.closest('.form-group')?.querySelector(':scope > label, :scope > span');
    return cleanLabelText(groupLabel) || 'انتخاب کنید';
  };

  const selectedLabel = (select) => {
    const option = select.options[select.selectedIndex];
    return option ? option.textContent.trim() : 'انتخاب کنید';
  };

  const isPlaceholderOption = (option) => option.dataset.choicePlaceholder === 'true' || (option.disabled && option.value === '');
  const visibleOptions = (select) => [...select.options].filter((option) => !option.hidden && !isPlaceholderOption(option));
  const searchEnabled = (select, mode = resolvedMode(select)) => mode === 'browse' && ['1','true','yes'].includes(String(select.dataset.choiceSearch || '').trim().toLowerCase());
  const normalizeSearch = (value) => String(value || '')
    .normalize('NFKC')
    .toLocaleLowerCase('fa-IR')
    .replace(/[يى]/g, 'ی')
    .replace(/ك/g, 'ک')
    .replace(/[ًٌٍَُِّْـ]/g, '')
    .replace(/[\u200c\u200d]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  const resolvedMode = (select) => {
    // Task sheets own their surface. A choice inside them must stay embedded instead of opening a second overlay.
    if (select.closest('[data-financial-filter-sheet]')) return 'embedded';
    const requested = String(select.dataset.choiceMode || '').trim().toLowerCase();
    if (requested === 'embedded') return 'embedded';
    if (requested === 'browse') return 'browse';
    if (requested === 'compact') return 'compact';
    if (requested === 'adaptive') return visibleOptions(select).length <= 5 ? 'compact' : 'browse';
    // Unclassified controls fail toward the safer compact internal picker,
    // never toward a native browser select. New controls should be explicitly classified.
    return 'compact';
  };

  const syncTrigger = (select) => {
    const state = enhanced.get(select);
    if (!state) return;
    state.trigger.querySelector('[data-panel-choice-value]').textContent = selectedLabel(select);
    state.trigger.setAttribute('aria-label', `${labelFor(select)}، ${selectedLabel(select)}`);
    state.trigger.disabled = select.disabled;
    state.trigger.classList.toggle('hidden', select.classList.contains('hidden') || select.hidden);
    state.trigger.setAttribute('aria-invalid', select.getAttribute('aria-invalid') === 'true' ? 'true' : 'false');
    if (state.inline) state.inline.classList.toggle('hidden', state.trigger.classList.contains('hidden'));
  };

  const optionButton = (option, index, groupKey = '0') => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'panel-choice-option';
    button.dataset.optionIndex = String(index);
    button.dataset.choiceGroup = String(groupKey);
    button.dataset.searchText = normalizeSearch(option.textContent);
    button.setAttribute('role', 'option');
    button.setAttribute('aria-selected', option.selected ? 'true' : 'false');
    button.disabled = option.disabled;
    button.innerHTML = `<span></span><i aria-hidden="true">${window.SoknaIcons?.markup('check') || ''}</i>`;
    button.querySelector('span').textContent = option.textContent.trim();
    if (option.selected) button.classList.add('is-selected');
    return button;
  };

  const applySearchFilter = (target, query) => {
    const normalized = normalizeSearch(query);
    const tokens = normalized.split(' ').filter(Boolean);
    let visibleCount = 0;
    target.querySelectorAll('.panel-choice-option').forEach((button) => {
      const haystack = String(button.dataset.searchText || '');
      button.hidden = tokens.length > 0 && !tokens.every((token) => haystack.includes(token));
      if (!button.hidden) visibleCount += 1;
    });
    target.querySelectorAll('.panel-choice-group').forEach((heading) => {
      const key = heading.dataset.choiceGroup;
      heading.hidden = ![...target.querySelectorAll(`.panel-choice-option[data-choice-group="${CSS.escape(key)}"]`)].some((button) => !button.hidden);
    });
    if (target === list) {
      emptyState.classList.toggle('hidden', visibleCount !== 0);
      searchClear.classList.toggle('hidden', normalized === '');
      if (emptyText) emptyText.textContent = normalized === '' ? 'نتیجه‌ای پیدا نشد.' : `برای «${String(query).trim()}» نتیجه‌ای پیدا نشد.`;
      if (emptyClear) emptyClear.hidden = normalized === '';
    }
    return visibleCount;
  };

  const buildOptionsInto = (select, target) => {
    target.replaceChildren();
    let lastGroup = null;
    let groupKey = 0;
    [...select.options].forEach((option, index) => {
      if (option.hidden || isPlaceholderOption(option)) return;
      const group = option.parentElement?.tagName === 'OPTGROUP' ? option.parentElement.label : '';
      if (group && group !== lastGroup) {
        groupKey += 1;
        const heading = document.createElement('div');
        heading.className = 'panel-choice-group';
        heading.dataset.choiceGroup = String(groupKey);
        heading.textContent = group;
        target.appendChild(heading);
        lastGroup = group;
      } else if (!group) {
        groupKey = 0;
        lastGroup = null;
      }
      target.appendChild(optionButton(option, index, groupKey));
    });
    if (target === list && active?.select === select && searchEnabled(select, active.mode)) applySearchFilter(target, searchInput.value);
  };

  const applyOption = (select, index) => {
    const option = select.options[Number(index)];
    if (!option || option.disabled) return false;
    const previousValue = select.value;
    const changed = previousValue !== option.value;
    select.value = option.value;
    if (changed) {
      select.dispatchEvent(new Event('input', { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
      select.dispatchEvent(new CustomEvent('panel:choice-change', {
        bubbles: true,
        detail: {
          select,
          previousValue,
          value: select.value,
          interaction: interactionModality(),
          mode: resolvedMode(select),
        },
      }));
    }
    select.dispatchEvent(new CustomEvent('panel:choice-commit', {
      bubbles: true,
      detail: {
        select,
        previousValue,
        value: select.value,
        changed,
        interaction: interactionModality(),
        mode: resolvedMode(select),
      },
    }));
    syncTrigger(select);
    return true;
  };

  const closeInline = ({ restoreFocus = keyboardInteraction() } = {}) => {
    if (!activeInline) return;
    const { trigger, inline } = activeInline;
    activeInline = null;
    inline.classList.add('hidden');
    inline.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    if (restoreFocus) restoreKeyboardFocus(trigger);
    else if (inline.contains(document.activeElement)) document.activeElement.blur();
  };

  const openInline = (select) => {
    const state = enhanced.get(select);
    if (!state?.inline || select.disabled) return;
    if (activeInline?.select === select) { closeInline(); return; }
    closeInline();
    if (active) close({ restoreFocus: false });
    buildOptionsInto(select, state.inline);
    activeInline = { select, trigger: state.trigger, inline: state.inline };
    state.inline.hidden = false;
    state.inline.classList.remove('hidden');
    state.trigger.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(() => {
      const selected = state.inline.querySelector('.panel-choice-option.is-selected:not(:disabled)');
      const first = state.inline.querySelector('.panel-choice-option:not(:disabled)');
      if (keyboardInteraction()) (selected || first)?.focus({ preventScroll: true });
      else state.trigger.blur();
      selected?.scrollIntoView({ block: 'nearest' });
    });
  };

  const syncVisualViewport = () => {
    if (!active || active.mode !== 'browse' || !matchMedia('(max-width: 720px)').matches) return;
    const vv = window.visualViewport;
    const top = vv ? Number(vv.offsetTop || 0) : 0;
    const left = vv ? Number(vv.offsetLeft || 0) : 0;
    const width = vv ? Number(vv.width || window.innerWidth) : window.innerWidth;
    const height = vv ? Number(vv.height || window.innerHeight) : window.innerHeight;
    layer.style.setProperty('--choice-vv-top', `${Math.round(top)}px`);
    layer.style.setProperty('--choice-vv-left', `${Math.round(left)}px`);
    layer.style.setProperty('--choice-vv-width', `${Math.round(width)}px`);
    layer.style.setProperty('--choice-vv-height', `${Math.round(height)}px`);
  };

  const resetVisualViewport = () => {
    for (const name of ['--choice-vv-top','--choice-vv-left','--choice-vv-width','--choice-vv-height']) layer.style.removeProperty(name);
  };

  const close = ({ restoreFocus = keyboardInteraction() } = {}) => {
    if (!active) return;
    const trigger = active.trigger;
    active = null;
    sheet.style.transform = '';
    sheet.style.transition = '';
    layer.classList.add('hidden');
    layer.classList.remove('mode-compact', 'mode-browse', 'has-search');
    searchWrap.classList.add('hidden');
    searchInput.value = '';
    searchClear.classList.add('hidden');
    emptyState.classList.add('hidden');
    resetVisualViewport();
    layer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
    trigger.setAttribute('aria-expanded', 'false');
    if (restoreFocus) restoreKeyboardFocus(trigger);
    else if (layer.contains(document.activeElement)) document.activeElement.blur();
  };

  const open = (select) => {
    const state = enhanced.get(select);
    if (!state || select.disabled) return;
    // Choice is a top-level task overlay. Close contextual surfaces first so
    // the panel never accumulates nested/competing overlays.
    window.SoknaActionMenu?.close?.(false);
    window.SoknaPanelTools?.close?.(false);
    window.SoknaPanelNavigation?.close?.(false);
    const mode = resolvedMode(select);
    if (mode === 'embedded') { openInline(select); return; }
    closeInline();
    syncTrigger(select);
    if (active && active.select !== select) close({ restoreFocus: false });
    active = { select, trigger: state.trigger, mode };
    const label = labelFor(select);
    const useSearch = searchEnabled(select, mode);
    title.textContent = label;
    searchWrap.classList.toggle('hidden', !useSearch);
    searchInput.value = '';
    searchInput.placeholder = `جست‌وجوی ${label}`;
    searchInput.setAttribute('aria-label', `جست‌وجوی ${label}`);
    buildOptionsInto(select, list);
    layer.classList.remove('hidden', 'mode-compact', 'mode-browse', 'has-search');
    layer.classList.add(`mode-${mode}`);
    if (useSearch) layer.classList.add('has-search');
    syncVisualViewport();
    layer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
    state.trigger.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(() => {
      const selected = list.querySelector('.panel-choice-option.is-selected:not(:disabled):not([hidden])');
      const first = list.querySelector('.panel-choice-option:not(:disabled):not([hidden])');
      const explicitSearchFocus = useSearch && ['1','true','yes'].includes(String(select.dataset.choiceSearchFocus || '').trim().toLowerCase());
      if (keyboardInteraction() || explicitSearchFocus) (useSearch ? searchInput : (selected || first || layer.querySelector('.panel-choice-close')))?.focus({ preventScroll: true });
      else state.trigger.blur();
      selected?.scrollIntoView({ block: 'nearest' });
    });
  };

  const enhance = (select) => {
    if (!(select instanceof HTMLSelectElement) || enhanced.has(select) || select.multiple || select.size > 1) return;
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'form-control panel-choice-trigger';
    trigger.setAttribute('aria-haspopup', resolvedMode(select) === 'embedded' ? 'listbox' : 'dialog');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML = '<span data-panel-choice-value></span><i aria-hidden="true"></i>';
    select.insertAdjacentElement('afterend', trigger);
    let inline = null;
    if (resolvedMode(select) === 'embedded') {
      inline = document.createElement('div');
      inline.className = 'panel-choice-inline hidden';
      inline.hidden = true;
      inline.id = `${select.id || `panelChoice${enhanced.size + 1}`}InlineList`;
      inline.setAttribute('role', 'listbox');
      trigger.insertAdjacentElement('afterend', inline);
      trigger.setAttribute('aria-controls', inline.id);
    } else {
      trigger.setAttribute('aria-controls', 'panelChoiceLayer');
    }
    select.classList.add('panel-choice-source');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    enhanced.set(select, { trigger, inline });
    syncTrigger(select);

    trigger.addEventListener('click', () => open(select));
    select.addEventListener('change', () => syncTrigger(select));
    select.addEventListener('input', () => syncTrigger(select));
    select.addEventListener('focus', () => trigger.focus({ preventScroll: true }));
    select.addEventListener('click', (event) => { event.preventDefault(); open(select); });
    inline?.addEventListener('click', (event) => {
      const button = event.target.closest('.panel-choice-option');
      if (!button || button.disabled) return;
      if (applyOption(select, button.dataset.optionIndex)) closeInline();
    });

    const observer = new MutationObserver(() => {
      syncTrigger(select);
      if (activeInline?.select === select && inline) buildOptionsInto(select, inline);
      if (active?.select === select) buildOptionsInto(select, list);
    });
    observer.observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'hidden', 'class', 'aria-invalid'] });
  };

  const enhanceWithin = (root = document) => {
    if (root instanceof Element && root.matches?.(SELECTOR)) enhance(root);
    root.querySelectorAll?.(SELECTOR).forEach(enhance);
  };

  enhanceWithin(document);
  const panel = document.querySelector('.panel-content');
  if (panel) {
    const rootObserver = new MutationObserver((mutations) => {
      for (const mutation of mutations) mutation.addedNodes.forEach((node) => {
        if (node instanceof Element) enhanceWithin(node);
      });
    });
    rootObserver.observe(panel, { childList: true, subtree: true });
  }
  document.addEventListener('panel:enhance-choice', (event) => enhanceWithin(event.detail?.root || document));

  searchInput.addEventListener('input', () => {
    if (!active || !searchEnabled(active.select, active.mode)) return;
    applySearchFilter(list, searchInput.value);
  });
  searchInput.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || event.isComposing || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) return;
    if (!window.CafeUI?.keyboard?.isTouchContext?.()) return;
    event.preventDefault();
    window.CafeUI.keyboard.dismiss(searchInput);
  });
  searchInput.addEventListener('focus', syncVisualViewport);
  const clearSearch = () => {
    if (!active) return;
    searchInput.value = '';
    applySearchFilter(list, '');
    searchInput.focus({ preventScroll: true });
  };
  searchClear.addEventListener('click', clearSearch);
  emptyClear?.addEventListener('click', clearSearch);
  window.visualViewport?.addEventListener('resize', syncVisualViewport);
  window.visualViewport?.addEventListener('scroll', syncVisualViewport);

  list.addEventListener('click', (event) => {
    const button = event.target.closest('.panel-choice-option');
    if (!button || button.disabled || !active) return;
    if (applyOption(active.select, button.dataset.optionIndex)) close();
  });

  layer.addEventListener('click', (event) => {
    if (event.target.closest('[data-panel-choice-close]')) close();
  });

  document.addEventListener('click', (event) => {
    if (!activeInline) return;
    if (event.target.closest('.panel-choice-inline') || event.target.closest('.panel-choice-trigger') === activeInline.trigger) return;
    closeInline();
  }, true);

  document.addEventListener('keydown', (event) => {
    if (activeInline) {
      if (event.key === 'Escape') { event.preventDefault(); closeInline({ restoreFocus: true }); return; }
      if (event.key === 'Tab') closeInline();
    }
    if (!active) return;
    if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); close({ restoreFocus: true }); return; }
    if (['ArrowDown','ArrowUp','Home','End'].includes(event.key) && !event.target.closest('input,textarea')) {
      const options = [...list.querySelectorAll('.panel-choice-option:not(:disabled):not([hidden])')];
      if (options.length) {
        event.preventDefault(); event.stopImmediatePropagation();
        const current = Math.max(0, options.indexOf(document.activeElement));
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : event.key === 'ArrowDown' ? (current + 1) % options.length : (current - 1 + options.length) % options.length;
        options[next].focus({ preventScroll: true });
      }
      return;
    }
    if (event.key !== 'Tab') return;
    const focusables = [...layer.querySelectorAll('button:not(:disabled),input:not(:disabled)')].filter((node) => !node.closest('.hidden') && !node.hidden);
    if (!focusables.length) return;
    const first = focusables[0], last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); event.stopImmediatePropagation(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); event.stopImmediatePropagation(); first.focus(); }
  }, true);

  document.addEventListener('invalid', (event) => {
    const select = event.target;
    if (!(select instanceof HTMLSelectElement) || !enhanced.has(select)) return;
    requestAnimationFrame(() => {
      syncTrigger(select);
      enhanced.get(select).trigger.setAttribute('aria-invalid', 'true');
      open(select);
    });
  }, true);

  if (window.CafeUI?.bindSwipeDismiss) {
    layer.querySelectorAll('[data-panel-choice-drag]').forEach((handle) => {
      window.CafeUI.bindSwipeDismiss({
        sheet,
        handle,
        threshold: 72,
        canStart: event => Boolean(active && active.mode === 'browse' && matchMedia('(max-width: 720px)').matches && !event.target.closest('button,a,input,textarea,select')),
        onDismiss: async () => { close({ restoreFocus: false }); return true; }
      });
    });
  }

})();

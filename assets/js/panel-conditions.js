(() => {
  'use strict';

  const GROUP_SELECTOR = '[data-panel-condition-source][data-panel-condition-value]';
  const groups = new Set();
  const originalDisabled = new WeakMap();

  const sourceFor = (group) => {
    const sourceId = String(group.dataset.panelConditionSource || '').trim();
    if (!sourceId) return null;
    const source = document.getElementById(sourceId);
    return source instanceof HTMLInputElement || source instanceof HTMLSelectElement || source instanceof HTMLTextAreaElement ? source : null;
  };

  const expectedValues = (group) => String(group.dataset.panelConditionValue || '')
    .split('|').map((value) => value.trim()).filter(Boolean);

  const controlsOf = (group) => [...group.querySelectorAll('input,select,textarea,button')];

  const syncGroup = (group) => {
    const source = sourceFor(group);
    if (!source) return;
    const visible = expectedValues(group).includes(String(source.value));
    group.classList.toggle('hidden', !visible);
    group.setAttribute('aria-hidden', visible ? 'false' : 'true');
    controlsOf(group).forEach((control) => {
      if (!originalDisabled.has(control)) originalDisabled.set(control, control.disabled);
      control.disabled = visible ? Boolean(originalDisabled.get(control)) : true;
    });
  };

  const register = (group) => {
    if (!(group instanceof Element) || groups.has(group)) return;
    if (!sourceFor(group)) return;
    groups.add(group);
    syncGroup(group);
  };

  const enhanceWithin = (root = document) => {
    if (root instanceof Element && root.matches?.(GROUP_SELECTOR)) register(root);
    root.querySelectorAll?.(GROUP_SELECTOR).forEach(register);
  };

  const syncForSource = (source) => {
    if (!(source instanceof Element) || !source.id) return;
    groups.forEach((group) => {
      if (group.dataset.panelConditionSource === source.id) syncGroup(group);
    });
  };

  // panel-choice owns selection. This owner only translates source value into
  // visibility/enabled state for dependent controls. Native input/change remain
  // supported for programmatic/non-choice sources without DOM-specific coupling.
  document.addEventListener('panel:choice-change', (event) => syncForSource(event.target));
  document.addEventListener('input', (event) => syncForSource(event.target));
  document.addEventListener('change', (event) => syncForSource(event.target));
  document.addEventListener('panel:enhance-conditions', (event) => enhanceWithin(event.detail?.root || document));

  enhanceWithin(document);
  const panel = document.querySelector('.panel-content');
  if (panel) {
    const observer = new MutationObserver((mutations) => {
      for (const mutation of mutations) mutation.addedNodes.forEach((node) => {
        if (node instanceof Element) enhanceWithin(node);
      });
    });
    observer.observe(panel, { childList: true, subtree: true });
  }
})();

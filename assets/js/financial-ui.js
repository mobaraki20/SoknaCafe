(() => {
  'use strict';

  const byId = (id) => document.getElementById(id);

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-financial-filter-open]');
    if (!trigger) return;
    const layer = byId(trigger.getAttribute('data-financial-filter-open') || '');
    if (!layer || !window.CafeUI?.dialog) return;
    event.preventDefault();
    CafeUI.dialog.open(layer, trigger);
  });

  document.querySelectorAll('[data-financial-filter-layer]').forEach((layer) => {
    const sheet = layer.querySelector('[data-financial-filter-sheet]');
    const handle = layer.querySelector('[data-financial-filter-handle]');
    if (!sheet || !handle || !window.CafeUI?.bindSwipeDismiss) return;
    window.CafeUI.bindSwipeDismiss({
      sheet,
      handle,
      threshold: 72,
      maxStartY: 116,
      onDismiss: async () => {
        if (!CafeUI.dialog.isOpen(layer)) return false;
        CafeUI.dialog.close(layer, true, 'swipe');
        return true;
      }
    });
  });
})();

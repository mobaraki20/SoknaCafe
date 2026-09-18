(() => {
  'use strict';

  window.CafeUI = window.CafeUI || {};
  if (window.CafeUI.interaction?.ready) return;

  let modality = 'keyboard';
  const root = document.documentElement;
  const set = (value) => {
    modality = value === 'pointer' ? 'pointer' : 'keyboard';
    root.dataset.inputModality = modality;
  };
  const pointer = () => set('pointer');
  const keyboard = (event) => {
    if (event?.metaKey || event?.ctrlKey || event?.altKey) return;
    set('keyboard');
  };

  document.addEventListener('pointerdown', pointer, true);
  document.addEventListener('touchstart', pointer, { capture: true, passive: true });
  document.addEventListener('keydown', keyboard, true);
  set('keyboard');

  const api = {
    ready: true,
    current: () => modality,
    isKeyboard: () => modality === 'keyboard',
    isPointer: () => modality === 'pointer',
    markPointer: pointer,
    markKeyboard: keyboard,
    shouldRestoreFocus: () => modality === 'keyboard',
    focusForKeyboard(target, options = { preventScroll: true }) {
      if (modality !== 'keyboard' || !target?.focus) return false;
      target.focus(options);
      return true;
    },
    restoreFocus(target, options = { preventScroll: true }) {
      if (modality !== 'keyboard' || !target?.isConnected || !target?.focus) return false;
      target.focus(options);
      return true;
    },
    clearPointerFocus(scope = document) {
      if (modality !== 'pointer') return;
      const active = document.activeElement;
      if (active instanceof HTMLElement && active !== document.body && (scope === document || scope.contains?.(active))) active.blur();
    },
  };
  window.CafeUI.interaction = api;
  window.SoknaInteraction = api;
})();

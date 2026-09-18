(() => {
  'use strict';
  const activateMode = (picker, mode) => {
    if (!picker) return;
    picker.querySelectorAll('input[name="image_mode"]').forEach(input => { input.checked = input.value === mode; });
    picker.querySelectorAll('[data-image-panel]').forEach(panel => {
      if (panel.dataset.imagePanel === 'library') return;
      panel.classList.toggle('is-active', panel.dataset.imagePanel === mode);
    });
  };

  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-image-mode-trigger]');
    if (!trigger) return;
    const picker = trigger.closest('[data-image-picker]');
    if (!picker) return;
    const mode = trigger.dataset.imageModeTrigger;
    const radio = picker.querySelector(`input[name="image_mode"][value="${CSS.escape(mode)}"]`);
    if (mode === 'keep') {
      event.preventDefault();
      closeLibrary(picker, false);
      activateMode(picker, 'keep');
      return;
    }
    if (mode === 'upload') {
      event.preventDefault();
      closeLibrary(picker, false);
      activateMode(picker, 'upload');
      const input = picker.querySelector('input[type="file"][name="image"]');
      input?.click();
      return;
    }
    if (mode === 'library') {
      event.preventDefault();
      const panel = picker.querySelector('[data-image-panel="library"]');
      if (!panel) { CafeUI.toast('آلبوم سایت در این فرم در دسترس نیست.', 'error'); return; }
      picker.dataset.previousImageMode = picker.querySelector('input[name="image_mode"]:checked')?.value || 'keep';
      picker.dataset.previousImageLibrary = picker.querySelector('input[name="image_library"]:checked')?.value || '';
      activateMode(picker, 'library');
      picker.classList.add('is-library-open');
      panel.classList.add('is-active');
      panel.dataset.imageReturnId = trigger.id || '';
      document.body.classList.add('image-library-open');
      bindLibrarySwipe(picker, panel);
      if (window.CafeUI?.dialog) CafeUI.dialog.open(panel, trigger);
      else panel.setAttribute('aria-hidden', 'false');
    }
  });

  const bindLibrarySwipe = (picker, panel) => {
    if (!picker || !panel || panel.dataset.swipeDismissBound === '1' || !window.CafeUI?.bindSwipeDismiss) return;
    const surface = panel.querySelector('.image-library-surface');
    const handle = panel.querySelector('.image-library-dialog-head');
    if (!surface || !handle) return;
    panel.dataset.swipeDismissBound = '1';
    window.CafeUI.bindSwipeDismiss({
      sheet: surface,
      handle,
      threshold: 72,
      canStart: event => !event.target.closest('button,a,input,textarea,select'),
      onDismiss: async () => { closeLibrary(picker, false); return true; }
    });
  };

  const closeLibrary = (picker, apply) => {
    const panel = picker?.querySelector('[data-image-panel="library"]');
    if (!picker || !panel) return;
    if (!apply) {
      const previous = picker.dataset.previousImageLibrary || '';
      picker.querySelectorAll('input[name="image_library"]').forEach(input => { input.checked = input.value === previous; });
      const previousMode = picker.dataset.previousImageMode || 'keep';
      activateMode(picker, previousMode);
    }
    picker.classList.remove('is-library-open');
    panel.classList.remove('is-active');
    if (window.CafeUI?.dialog?.isOpen?.(panel)) CafeUI.dialog.close(panel, true, apply ? 'apply' : 'cancel');
    else panel.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('image-library-open');
  };
  document.addEventListener('click', event => {
    const cancel = event.target.closest('[data-image-library-cancel]');
    if (cancel) { event.preventDefault(); closeLibrary(cancel.closest('[data-image-picker]'), false); return; }
    const apply = event.target.closest('[data-image-library-apply]');
    if (apply) {
      event.preventDefault();
      const picker = apply.closest('[data-image-picker]');
      if (!picker?.querySelector('input[name="image_library"]:checked')) { CafeUI.toast('ابتدا یک تصویر را انتخاب کنید.', 'warning'); return; }
      activateMode(picker, 'library');
      closeLibrary(picker, true);
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const picker = document.querySelector('[data-image-picker].is-library-open');
    if (picker) { event.preventDefault(); closeLibrary(picker, false); }
  });
  document.addEventListener('input', event => {
    const search = event.target.closest('[data-image-library-search]');
    if (!search) return;
    const picker = search.closest('[data-image-picker]');
    const query = String(search.value || '').trim().toLocaleLowerCase('fa-IR');
    picker?.querySelectorAll('[data-image-library-card]').forEach(card => card.classList.toggle('hidden', query && !String(card.dataset.imageLibraryCard || '').includes(query)));
  });
  document.addEventListener('change', event => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || input.name !== 'image') return;
    const picker = input.closest('[data-image-picker]');
    if (!picker || !input.files?.length) return;
    const file = input.files[0];
    activateMode(picker, 'upload');
    const preview = picker.querySelector('[data-image-upload-preview]');
    const image = preview?.querySelector('img');
    const name = picker.querySelector('[data-image-upload-name]');
    const meta = picker.querySelector('[data-image-upload-meta]');
    if (name) name.textContent = file.name;
    if (meta) meta.textContent = `${Math.max(1, Math.round(file.size / 1024)).toLocaleString('fa-IR')} کیلوبایت`;
    if (preview && image) {
      const url = URL.createObjectURL(file);
      image.onload = () => URL.revokeObjectURL(url);
      image.src = url;
      preview.classList.remove('hidden');
    }
    const required = input.dataset.requiredAspect || picker.dataset.requiredAspect || '';
    if (!required) return;
    const url = URL.createObjectURL(file), probe = new Image();
    probe.onload = () => {
      URL.revokeObjectURL(url);
      const ratio = probe.naturalWidth / Math.max(1, probe.naturalHeight);
      const valid = required === 'square' ? Math.abs(ratio - 1) < .015 : required === '2:1' ? Math.abs(ratio - 2) < .04 : true;
      if (valid) return;
      input.value = '';
      preview?.classList.add('hidden');
      const keep = picker.querySelector('input[name="image_mode"][value="keep"]'); if (keep) keep.checked = true;
      CafeUI.toast(required === '2:1' ? 'تصویر کمپین باید با نسبت ۲:۱ باشد؛ پیشنهاد ۱۲۰۰×۶۰۰ پیکسل است.' : 'تصویر آیتم باید مربع باشد؛ پیشنهاد ۱۲۰۰×۱۲۰۰ پیکسل است.', 'error');
    };
    probe.onerror = () => URL.revokeObjectURL(url);
    probe.src = url;
  });
  document.addEventListener('click', event => {
    const clear = event.target.closest('[data-image-upload-clear]');
    if (!clear) return;
    const picker = clear.closest('[data-image-picker]');
    const input = picker?.querySelector('input[type="file"][name="image"]'); if (input) input.value = '';
    const preview = picker?.querySelector('[data-image-upload-preview]'); preview?.classList.add('hidden'); preview?.querySelector('img')?.removeAttribute('src');
  });
})();

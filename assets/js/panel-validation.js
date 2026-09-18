(() => {
  'use strict';

  const errorId = (control) => {
    if (!control.id) control.id = `panel-field-${Math.random().toString(36).slice(2, 10)}`;
    return `${control.id}-validation`;
  };
  const cleanLabelText = (node) => {
    if (!node) return '';
    const clone = node.cloneNode(true);
    clone.querySelectorAll?.('select,input,textarea,button,option,optgroup').forEach((child) => child.remove());
    return String(clone.textContent || '').replace(/\s+/g, ' ').trim().replace(/[،,:؛]+$/u, '');
  };
  const labelText = (control) => {
    const aria = String(control.getAttribute('aria-label') || '').trim();
    if (aria) return aria;
    if (control.id) {
      const external = document.querySelector(`label[for="${CSS.escape(control.id)}"]`);
      const text = cleanLabelText(external);
      if (text) return text;
    }
    const ownLabel = control.labels?.[0];
    const ownText = cleanLabelText(ownLabel);
    if (ownText) return ownText;
    const group = control.closest('.form-group');
    if (group) {
      const text = cleanLabelText(group.querySelector(':scope > label, :scope > span'));
      if (text) return text;
    }
    return 'این فیلد';
  };
  const messageFor = (control) => {
    const label = labelText(control);
    const validity = control.validity;
    if (validity.valueMissing) {
      if (control.type === 'checkbox') return `تأیید «${label}» لازم است.`;
      if (control.tagName === 'SELECT') return `برای «${label}» یک گزینه انتخاب کنید.`;
      return `«${label}» را وارد کنید.`;
    }
    if (validity.typeMismatch) {
      if (control.type === 'url') return `آدرس واردشده برای «${label}» معتبر نیست؛ آدرس کامل را با http یا https وارد کنید.`;
      if (control.type === 'email') return `ایمیل واردشده برای «${label}» معتبر نیست.`;
      return `مقدار «${label}» معتبر نیست.`;
    }
    if (validity.tooShort) return `«${label}» باید حداقل ${control.minLength} نویسه باشد.`;
    if (validity.tooLong) return `«${label}» نباید بیشتر از ${control.maxLength} نویسه باشد.`;
    if (validity.rangeUnderflow) return `«${label}» نباید کمتر از ${control.min} باشد.`;
    if (validity.rangeOverflow) return `«${label}» نباید بیشتر از ${control.max} باشد.`;
    if (validity.stepMismatch) return `مقدار «${label}» با فاصله مجاز این فیلد هماهنگ نیست.`;
    if (validity.patternMismatch) return `فرمت واردشده برای «${label}» معتبر نیست.`;
    if (validity.badInput) return `برای «${label}» یک مقدار معتبر وارد کنید.`;
    return control.dataset.validationMessage || `مقدار «${label}» را بررسی کنید.`;
  };
  const clear = (control) => {
    const id = errorId(control);
    const node = document.getElementById(id);
    node?.remove();
    control.removeAttribute('aria-invalid');
    const ids = String(control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean).filter((value) => value !== id);
    if (ids.length) control.setAttribute('aria-describedby', ids.join(' ')); else control.removeAttribute('aria-describedby');
    control.closest('.form-group')?.classList.remove('has-validation-error');
  };
  const show = (control) => {
    clear(control);
    const id = errorId(control);
    const node = document.createElement('small');
    node.id = id;
    node.className = 'panel-field-error';
    node.setAttribute('role', 'alert');
    node.textContent = messageFor(control);
    const group = control.closest('.form-group');
    if (group) group.appendChild(node); else control.insertAdjacentElement('afterend', node);
    control.setAttribute('aria-invalid', 'true');
    const described = String(control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    if (!described.includes(id)) described.push(id);
    control.setAttribute('aria-describedby', described.join(' '));
    group?.classList.add('has-validation-error');
  };

  let focusQueued = false;
  document.addEventListener('invalid', (event) => {
    const control = event.target;
    if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) return;
    event.preventDefault();
    show(control);
    if (!focusQueued) {
      focusQueued = true;
      requestAnimationFrame(() => {
        focusQueued = false;
        const first = document.querySelector('[aria-invalid="true"]');
        first?.focus?.({ preventScroll: false });
      });
    }
  }, true);

  const reconsider = (event) => {
    const control = event.target;
    if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) return;
    if (control.getAttribute('aria-invalid') === 'true' && control.checkValidity()) clear(control);
  };
  document.addEventListener('input', reconsider, true);
  document.addEventListener('change', reconsider, true);
})();

(() => {
  'use strict';
  const forms = [...document.querySelectorAll('[data-reorder-form]')];
  if (!forms.length) return;

  forms.forEach(form => {
    const list = form.querySelector('[data-reorder-list]');
    const output = form.querySelector('[data-reorder-output]');
    if (!list || !output) return;

    let dragging = null;
    const rows = () => [...list.querySelectorAll('[data-reorder-id]')];
    const sync = () => {
      const ids = rows().map(row => { const raw = String(row.dataset.reorderId || ''); return /^\d+$/.test(raw) ? Number(raw) : raw; }).filter(value => value !== '');
      output.value = JSON.stringify(ids);
      form.dispatchEvent(new CustomEvent('reorder:change',{detail:{ids}}));
    };
    const focusHandle = row => row?.querySelector('[data-reorder-handle]')?.focus();
    const move = (row, direction) => {
      if (!row) return;
      if (direction < 0 && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
      if (direction > 0 && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
      sync();
      focusHandle(row);
    };

    list.addEventListener('click', event => {
      const row = event.target.closest('[data-reorder-id]');
      if (event.target.closest('[data-reorder-up]')) move(row, -1);
      if (event.target.closest('[data-reorder-down]')) move(row, 1);
    });

    list.addEventListener('keydown', event => {
      const handle = event.target.closest('[data-reorder-handle]');
      if (!handle || !event.altKey) return;
      const row = handle.closest('[data-reorder-id]');
      if (event.key === 'ArrowUp') { event.preventDefault(); move(row, -1); }
      if (event.key === 'ArrowDown') { event.preventDefault(); move(row, 1); }
    });

    list.addEventListener('dragstart', event => {
      dragging = event.target.closest('[data-reorder-id]');
      if (!dragging) return;
      dragging.classList.add('is-dragging');
      if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragover', event => {
      event.preventDefault();
      const target = event.target.closest('[data-reorder-id]');
      if (!dragging || !target || target === dragging) return;
      const rect = target.getBoundingClientRect();
      list.insertBefore(dragging, event.clientY < rect.top + rect.height / 2 ? target : target.nextElementSibling);
    });
    list.addEventListener('dragend', () => {
      dragging?.classList.remove('is-dragging');
      dragging = null;
      sync();
    });
    form.addEventListener('submit', sync);
    sync();
  });
})();

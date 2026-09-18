(() => {
  'use strict';
  const list = document.querySelector('[data-message-list]');
  if (!list) return;
  const cards = [...list.querySelectorAll('[data-message-card]')];
  const search = document.querySelector('[data-message-search]');
  const empty = document.querySelector('[data-message-empty]');
  let filter = 'all';
  let group = 'all';

  const normalize = value => String(value || '').trim().toLocaleLowerCase('fa-IR').replace(/ي/g,'ی').replace(/ك/g,'ک');
  const apply = () => {
    const query = normalize(search?.value);
    let visible = 0;
    cards.forEach(card => {
      const matchesFilter = filter === 'all' || (filter === 'changed' && card.dataset.changed === '1') || (filter === 'optional' && card.dataset.optional === '1');
      const matchesGroup = group === 'all' || card.dataset.group === group;
      const matchesSearch = !query || normalize(card.dataset.searchText).includes(query) || normalize(card.querySelector('[data-message-input]')?.value).includes(query);
      const show = matchesFilter && matchesGroup && matchesSearch;
      card.hidden = !show;
      if (show) visible++;
    });
    if (empty) empty.hidden = visible !== 0;
  };

  document.querySelectorAll('[data-message-filter]').forEach(button => button.addEventListener('click', () => {
    filter = button.dataset.messageFilter || 'all';
    document.querySelectorAll('[data-message-filter]').forEach(b => { b.classList.toggle('btn-primary', b === button); b.classList.toggle('btn-light', b !== button); });
    apply();
  }));
  document.querySelectorAll('[data-message-group]').forEach(button => button.addEventListener('click', () => {
    group = button.dataset.messageGroup || 'all';
    document.querySelectorAll('[data-message-group]').forEach(b => b.classList.toggle('is-active', b === button));
    apply();
  }));
  search?.addEventListener('input', apply);

  cards.forEach(card => {
    const input = card.querySelector('[data-message-input]');
    const preview = card.querySelector('[data-message-preview]');
    const counter = card.querySelector('[data-message-count]');
    const enabled = card.querySelector('[data-message-enabled]');
    const sync = () => {
      if (preview && input) preview.textContent = input.value || '—';
      if (counter && input) {
        const toFa = v => String(v).replace(/\d/g,d => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
        counter.textContent = `${toFa([...input.value].length)} / ${toFa(input.maxLength || 1000)}`;
      }
      card.classList.toggle('is-disabled', enabled ? !enabled.checked : false);
    };
    input?.addEventListener('input', sync);
    enabled?.addEventListener('change', sync);
    card.querySelectorAll('[data-insert-token]').forEach(button => button.addEventListener('click', () => {
      if (!input) return;
      const token = `{${button.dataset.insertToken}}`;
      const start = input.selectionStart ?? input.value.length;
      const end = input.selectionEnd ?? start;
      input.setRangeText(token,start,end,'end');
      input.focus();
      input.dispatchEvent(new Event('input',{bubbles:true}));
    }));
    sync();
  });
  apply();
})();

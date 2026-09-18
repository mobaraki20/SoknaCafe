(() => {
  'use strict';
  const endpoint = window.SOKNA_ITEMS_ENDPOINT;
  const csrf = window.SOKNA_ITEMS_CSRF;
  const items = window.SOKNA_ITEMS || {};
  const drawer = document.getElementById('itemEditorDrawer');
  const backdrop = document.getElementById('itemEditorBackdrop');
  const form = document.getElementById('itemQuickEditForm');
  let drawerOpener = null;
  let initialDrawerSnapshot = '';
  const coarsePointer = window.matchMedia?.('(pointer: coarse)');
  const categoryMenuIds = window.SOKNA_CATEGORY_MENU_IDS || {};
  const mobileDrawer = () => window.matchMedia?.('(max-width: 760px)').matches || coarsePointer?.matches;

  const faDigits = (value) => String(value).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
  const enDigits = (value) => String(value ?? '').replace(/[۰-۹]/g, d => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g, d => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
  const money = (value) => `${new Intl.NumberFormat('fa-IR').format(Number(value || 0))} تومان`;
  const toast = (message, type = '') => window.CafeUI?.toast?.(message, type);
  const request = async (payload) => {
    const response = await fetch(endpoint, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ ...payload, csrf_token: csrf }),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) throw new Error(data.message || 'این تغییر انجام نشد.');
    return data;
  };

  const updateRow = (item) => {
    if (!item) return;
    items[item.id] = item;
    const row = document.querySelector(`[data-item-row="${item.id}"]`);
    if (!row) return;
    row.querySelector('[data-item-name]').textContent = item.name;
    row.querySelector('[data-item-category]').textContent = item.category_name;
    row.querySelector('[data-item-station]').textContent = item.station_label;
    row.querySelector('[data-item-price]').textContent = money(item.price);
    const featured = row.querySelector('[data-featured-badge]');
    featured?.classList.toggle('hidden', !item.featured);
    const badges = row.querySelector('.item-admin-badges');
    if (badges) {
      badges.querySelectorAll('[data-item-menu-badge],[data-item-menu-empty]').forEach(node => node.remove());
      const names = Array.isArray(item.menu_names) ? item.menu_names : [];
      if (names.length) names.forEach(name => { const badge=document.createElement('span'); badge.className='badge badge-menu'; badge.dataset.itemMenuBadge=''; badge.textContent=name; badges.appendChild(badge); });
      else { const badge=document.createElement('span'); badge.className='badge badge-muted'; badge.dataset.itemMenuEmpty=''; badge.textContent='بدون منو'; badges.appendChild(badge); }
    }
    [['availability', item.available, 'قابل سفارش', 'غیرقابل سفارش'], ['active', item.active, 'منتشرشده', 'پیش‌نویس']].forEach(([action, on, yes, no]) => {
      const button = row.querySelector(`[data-item-action="${action}"]`);
      if (!button) return;
      button.classList.toggle('is-on', Boolean(on));
      button.classList.toggle('is-off', !on);
      button.setAttribute('aria-pressed', on ? 'true' : 'false');
      const label = button.querySelector('b'); if (label) label.textContent = on ? yes : no;
    });
  };

  const quickMenuInputs = () => [...document.querySelectorAll('[data-quick-item-menu]')];
  const syncQuickMenuOptions = (selectedIds = null, categoryId = null) => {
    const category = Number(categoryId ?? (document.getElementById('quickItemCategory')?.value || 0));
    const allowed = new Set((categoryMenuIds[category] || []).map(Number));
    const selected = selectedIds === null ? new Set(quickMenuInputs().filter(input => input.checked).map(input => Number(input.value))) : new Set((selectedIds || []).map(Number));
    quickMenuInputs().forEach(input => {
      const id = Number(input.value); input.disabled = !allowed.has(id); input.checked = allowed.has(id) && selected.has(id);
      input.closest('label')?.classList.toggle('is-disabled', input.disabled);
    });
  };

  const drawerSnapshot = () => JSON.stringify({
    name: document.getElementById('quickItemName')?.value.trim() || '',
    price: window.CafeUI?.money?.normalize?.(document.getElementById('quickItemPrice')?.value || '') || enDigits(document.getElementById('quickItemPrice')?.value || '').replace(/[,٬\s]/g, ''),
    category: document.getElementById('quickItemCategory')?.value || '',
    station: document.getElementById('quickItemStation')?.value || '',
    available: Boolean(document.getElementById('quickItemAvailable')?.checked),
    active: Boolean(document.getElementById('quickItemActive')?.checked),
    staff_only: Boolean(document.getElementById('quickItemStaffOnly')?.checked),
    takeaway_allowed: Boolean(document.getElementById('quickItemTakeaway')?.checked),
    featured: Boolean(document.getElementById('quickItemFeatured')?.checked),
    menu_ids: quickMenuInputs().filter(input => input.checked && !input.disabled).map(input => Number(input.value)).sort((a,b)=>a-b),
  });
  const openDrawer = (id, opener) => {
    const item = items[id];
    if (!item || !drawer || !form) return;
    drawerOpener = opener || null;
    document.getElementById('quickItemId').value = item.id;
    document.getElementById('quickItemName').value = item.name;
    const priceInput = document.getElementById('quickItemPrice');
    priceInput.value = window.CafeUI?.money?.format?.(item.price) || faDigits(item.price);
    const category = document.getElementById('quickItemCategory'); category.value = item.category_id; category.dispatchEvent(new Event('change', { bubbles:true }));
    syncQuickMenuOptions(item.menu_ids || [], item.category_id);
    const station = document.getElementById('quickItemStation'); station.value = item.preparation_station; station.dispatchEvent(new Event('change', { bubbles:true }));
    document.getElementById('quickItemAvailable').checked = Boolean(item.available);
    document.getElementById('quickItemActive').checked = Boolean(item.active);
    document.getElementById('quickItemStaffOnly').checked = Boolean(item.staff_only);
    document.getElementById('quickItemTakeaway').checked = item.takeaway_allowed !== false;
    document.getElementById('quickItemFeatured').checked = Boolean(item.featured);
    const scheduleWrap = document.getElementById('quickItemScheduleWrap');
    const schedule = document.getElementById('quickItemSchedule');
    if (scheduleWrap) scheduleWrap.hidden = !item.has_schedule;
    if (schedule) schedule.textContent = item.has_schedule ? (item.schedule_summary || 'زمان‌بندی‌شده') : '';
    document.getElementById('quickItemFullEdit').href = `item_form.php?id=${item.id}`;
    document.getElementById('itemEditorTitle').textContent = item.name;
    drawer.classList.remove('hidden');
    const modal = Boolean(mobileDrawer());
    drawer.setAttribute('aria-modal', modal ? 'true' : 'false');
    backdrop?.classList.toggle('hidden', !modal);
    document.body.classList.toggle('no-scroll', modal);
    initialDrawerSnapshot = drawerSnapshot();
    requestAnimationFrame(() => { if (!coarsePointer?.matches) document.getElementById('quickItemName')?.focus(); });
  };
  const closeDrawer = () => {
    drawer?.classList.add('hidden'); backdrop?.classList.add('hidden'); drawer?.setAttribute('aria-modal','false'); document.body.classList.remove('no-scroll');
    initialDrawerSnapshot = '';
    drawerOpener?.focus?.(); drawerOpener = null;
  };
  const closeDrawerSafely = async () => {
    if (!initialDrawerSnapshot || drawerSnapshot() === initialDrawerSnapshot) { closeDrawer(); return true; }
    const accepted = await window.CafeUI?.confirm?.('تغییرات ذخیره نشده‌اند. بدون ذخیره خارج شوید؟', 'خروج از ویرایش سریع', { okLabel:'خروج بدون ذخیره', danger:true });
    if (accepted) { closeDrawer(); return true; }
    return false;
  };


  document.getElementById('quickItemCategory')?.addEventListener('change', () => syncQuickMenuOptions());

  document.addEventListener('click', async (event) => {
    const edit = event.target.closest('[data-quick-edit-item]');
    if (edit) { openDrawer(Number(edit.dataset.quickEditItem), edit); return; }
    if (event.target.closest('[data-close-item-editor]') || event.target === backdrop) { await closeDrawerSafely(); return; }
    const toggle = event.target.closest('[data-item-action]');
    if (!toggle) return;
    event.preventDefault();
    const id = Number(toggle.dataset.itemId); const action = toggle.dataset.itemAction;
    const row = toggle.closest('[data-item-row]');
    toggle.disabled = true; row?.classList.add('is-busy');
    try {
      const desiredState = toggle.getAttribute('aria-pressed') === 'true' ? 0 : 1;
      const data = await request({ action, id, desired_state: desiredState });
      updateRow(data.item); toast(data.message);
      if (window.SOKNA_ITEMS_FILTERED) location.reload();
    } catch (error) { toast(window.CafeUI?.requestErrorMessage?.(error) || 'این تغییر انجام نشد.', 'error'); }
    finally { toggle.disabled = false; row?.classList.remove('is-busy'); }
  });
  const swipeHandle = drawer?.querySelector('[data-item-editor-swipe-handle]');
  if (drawer && swipeHandle && window.CafeUI?.bindSwipeDismiss) {
    window.CafeUI.bindSwipeDismiss({ sheet:drawer, handle:swipeHandle, onDismiss:closeDrawerSafely });
  }

  document.addEventListener('keydown', async event => { if (event.key === 'Escape' && drawer && !drawer.classList.contains('hidden')) { event.preventDefault(); await closeDrawerSafely(); } });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submit = form.querySelector('[type="submit"]'); submit.disabled = true;
    const payload = {
      action: 'quick_update', id: Number(document.getElementById('quickItemId').value),
      name: document.getElementById('quickItemName').value.trim(),
      price: window.CafeUI?.money?.normalize?.(document.getElementById('quickItemPrice').value) || enDigits(document.getElementById('quickItemPrice').value).replace(/[,٬\s]/g, ''),
      category_id: Number(document.getElementById('quickItemCategory').value),
      preparation_station: document.getElementById('quickItemStation').value,
      available: document.getElementById('quickItemAvailable').checked,
      active: document.getElementById('quickItemActive').checked,
      staff_only: document.getElementById('quickItemStaffOnly').checked,
      takeaway_allowed: document.getElementById('quickItemTakeaway').checked,
      featured: document.getElementById('quickItemFeatured').checked,
      menu_ids: quickMenuInputs().filter(input => input.checked && !input.disabled).map(input => Number(input.value)),
    };
    try {
      const data = await request(payload); updateRow(data.item); toast(data.message); closeDrawer();
      if (window.SOKNA_ITEMS_FILTERED) location.reload();
    } catch (error) { toast(window.CafeUI?.requestErrorMessage?.(error) || 'این تغییر انجام نشد.', 'error'); }
    finally { submit.disabled = false; }
  });

  document.getElementById('quickItemFullEdit')?.addEventListener('click', async (event) => {
    if (!initialDrawerSnapshot || drawerSnapshot() === initialDrawerSnapshot) return;
    event.preventDefault();
    const href = event.currentTarget.href;
    const accepted = await window.CafeUI?.confirm?.('تغییرات ویرایش سریع ذخیره نشده‌اند. به ویرایش کامل بروید؟', 'ویرایش کامل', { okLabel:'ادامه' });
    if (accepted) window.location.href = href;
  });

  const checkboxes = [...document.querySelectorAll('[data-item-select]')];
  const selectAll = document.getElementById('selectAllItems');
  const selectionBar = document.getElementById('itemSelectionBar');
  const selectedCount = document.getElementById('selectedItemCount');
  const operation = document.getElementById('bulkItemOperation');
  const categoryTarget = document.getElementById('bulkCategoryTarget');
  const stationTarget = document.getElementById('bulkStationTarget');
  const menuTarget = document.getElementById('bulkMenuTarget');
  const selectedIds = () => checkboxes.filter(box => box.checked).map(box => Number(box.value));
  const syncSelection = () => {
    const count = selectedIds().length;
    selectionBar?.classList.toggle('hidden', count === 0);
    if (selectedCount) selectedCount.textContent = faDigits(count);
    if (selectAll) { selectAll.checked = count > 0 && count === checkboxes.length; selectAll.indeterminate = count > 0 && count < checkboxes.length; }
  };
  checkboxes.forEach(box => box.addEventListener('change', syncSelection));
  selectAll?.addEventListener('change', () => { checkboxes.forEach(box => { box.checked = selectAll.checked; }); syncSelection(); });
  document.getElementById('clearItemSelection')?.addEventListener('click', () => { checkboxes.forEach(box => { box.checked = false; }); if (selectAll) selectAll.checked = false; syncSelection(); });
  operation?.addEventListener('change', () => {
    categoryTarget?.classList.toggle('hidden', operation.value !== 'category');
    stationTarget?.classList.toggle('hidden', operation.value !== 'station');
    menuTarget?.classList.toggle('hidden', !['menu_add','menu_remove'].includes(operation.value));
  });
  document.getElementById('applyBulkItemAction')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const ids = selectedIds(); const op = operation?.value || '';
    if (!ids.length || !op) { toast('آیتم‌ها و نوع عملیات را انتخاب کنید.', 'warning'); return; }
    let target = '';
    if (op === 'category') target = categoryTarget?.value || '';
    if (op === 'station') target = stationTarget?.value || '';
    if (['menu_add','menu_remove'].includes(op)) target = menuTarget?.value || '';
    if (['category','station','menu_add','menu_remove'].includes(op) && !target) { toast('مقصد عملیات را انتخاب کنید.', 'warning'); return; }
    const labels = {available_on:'قابل سفارش‌کردن',available_off:'توقف سفارش',active_on:'انتشار',active_off:'بردن به پیش‌نویس',category:'انتقال دسته‌بندی',station:'تغییر محل آماده‌سازی',menu_add:'افزودن به منو',menu_remove:'حذف از منو'};
    const confirmed = await window.CafeUI?.confirm?.(`${labels[op] || 'تغییر'} برای ${faDigits(ids.length)} آیتم انجام شود؟`, 'اعمال تغییر');
    if (!confirmed) return;
    button.disabled = true;
    try { const data = await request({ action:'bulk_update', ids, operation:op, target }); toast(data.message); location.reload(); }
    catch (error) { toast(window.CafeUI?.requestErrorMessage?.(error) || 'این تغییر انجام نشد.', 'error'); button.disabled = false; }
  });

  document.querySelectorAll('#itemFilterForm select').forEach(select => select.addEventListener('change', () => select.form.requestSubmit()));
  // Search is intentionally submitted by Enter/the explicit button. Reloading while a user
  // is composing Persian text interrupts IME input and makes search appear unreliable.
})();

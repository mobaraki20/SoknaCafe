(() => {
  'use strict';

  try { if ('scrollRestoration' in history) history.scrollRestoration = 'manual'; } catch (_) {}

  const messages = window.CAFE_MESSAGES || {};
  const guestInteraction = window.CafeUI?.interaction || window.SoknaInteraction;
  const keyboardGuestInteraction = () => guestInteraction?.isKeyboard?.() ?? true;
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
  const items = new Map((window.CAFE_MENU || []).map((item) => [Number(item.id), item]));
  const cart = new Map();
  let takeawayDraft = null;
  let takeawaySheetOpener = null;
  let editConflict = null;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const table = window.CAFE_TABLE;
  let session = window.CAFE_SESSION;
  let canOrder = Boolean(window.CAFE_CAN_ORDER);
  let orderAcceptance = {...(window.CAFE_ORDER_ACCEPTANCE || {cafe: true, kitchen: true, bar: true})};
  let orderingEnabled = Boolean(orderAcceptance.cafe);
  const orderAcceptanceMessages = window.CAFE_ORDER_ACCEPTANCE_MESSAGES || {};
  let requiresOperatorConfirmation = Boolean(window.CAFE_REQUIRES_OPERATOR_CONFIRMATION);
  let stationStateHash = String(window.CAFE_STATION_STATE_HASH || '');

  const numberFa = (value) => new Intl.NumberFormat('fa-IR').format(Number(value));
  const textFaDigits = (value) => String(value ?? '').replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
  const money = (value) => `${numberFa(value)} ${window.CAFE_CURRENCY}`;
  const normalize = (value) => String(value || '')
    .toLocaleLowerCase('fa')
    .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
    .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
    .replace(/ي/g, 'ی')
    .replace(/ك/g, 'ک')
    .replace(/[\u064B-\u065F]/g, '')
    .replace(/[\u200c\u200f\u202a-\u202e]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
  })[char]);
  const sprite = String(window.SOKNA_ICON_SPRITE || '');
  const icon = (name, className = '') => `<svg class="ui-icon ${esc(className)}" aria-hidden="true" focusable="false"><use href="${esc(sprite)}#icon-${esc(name)}"></use></svg>`;
  const canOrderItem = (item) => Boolean(item) && Number(item.available) === 1 && Number(item.order_available ?? 1) === 1;
  const itemUnavailableLabel = (item) => Number(item?.available) !== 1
    ? msg('item_unavailable', 'فعلاً موجود نیست')
    : (Number(item?.order_available ?? 1) !== 1 ? String(item?.unavailable_message || 'سفارش آنلاین این آیتم فعلاً متوقف است') : '');
  const serviceScopeForItem = (item) => String(item?.preparation_area || (item?.preparation_station === 'kitchen' ? 'kitchen' : 'bar'));
  const fulfillment=window.SoknaFulfillment;
  const clampTakeaway=(line)=>fulfillment.clamp(line);
  const cartTakeawayQuantity=()=>fulfillment.takeawayQuantity(cart.values());
  const cartEligibleQuantity=()=>fulfillment.eligibleQuantity(cart.values());
  const cartIsAllTakeaway=()=>{const q=cartEligibleQuantity(),total=[...cart.values()].reduce((sum,line)=>sum+Number(line.quantity||0),0);return q>0&&q===total&&cartTakeawayQuantity()===q;};
  const orderPayloadLines = () => { const rows=[]; for(const line of cart.values()){clampTakeaway(line);const q=Number(line.quantity||0),t=Number(line.takeaway_quantity||0),d=q-t;const base={id:Number(line.item.id),note:String(line.note||''),unit_price:Number(line.item.price)};if(d>0)rows.push({...base,quantity:d,fulfillment_mode:'dine_in'});if(t>0)rows.push({...base,quantity:t,fulfillment_mode:'takeaway'});} return rows; };
  function applyOrderAcceptance(next) {
    orderAcceptance = {
      cafe: Boolean(next?.cafe),
      kitchen: Boolean(next?.kitchen),
      bar: Boolean(next?.bar),
    };
    orderingEnabled = orderAcceptance.cafe;
    items.forEach((item) => {
      const scope = serviceScopeForItem(item);
      const blocked = !orderAcceptance.cafe ? 'cafe' : (!orderAcceptance[scope] ? scope : '');
      item.order_available = Number(item.available) === 1 && !blocked ? 1 : 0;
      item.blocked_scope = blocked;
      item.unavailable_message = blocked ? String(orderAcceptanceMessages[blocked] || '') : '';
    });
    document.querySelectorAll('[data-menu-item],[data-featured-item]').forEach((card) => {
      const item = items.get(Number(card.dataset.itemId));
      const available = canOrderItem(item);
      const servicePaused = Number(item?.available) === 1 && !available;
      card.classList.toggle('service-unavailable', servicePaused);
      card.classList.toggle('unavailable', Number(item?.available) !== 1);
      card.querySelectorAll('[data-add-item],[data-inc],[data-inline-inc]').forEach((button) => { button.disabled = !available; });
      const badge = card.querySelector('[data-service-unavailable-label]');
      if (badge) badge.classList.toggle('hidden', !(Number(item?.available) === 1 && !available));
    });
    for (const [id, line] of [...cart.entries()]) {
      const item = items.get(id);
      if (item && !line.item?.snapshot_from_order) line.item = item;
    }
    if (currentItemDetailId) renderItemDetailActions();
    render();
  }

  const uuid = () => window.crypto?.randomUUID?.()
    || `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
  const hashEqualsText = (left, right) => String(left || '') === String(right || '');

  const deviceKey = 'cafe-device-token-v1';
  let deviceToken = '';
  try {
    deviceToken = localStorage.getItem(deviceKey) || uuid();
    localStorage.setItem(deviceKey, deviceToken);
  } catch (_) {
    deviceToken = uuid();
  }

  const storageKey = `cafe-table-v2:${table?.token || 'public'}:${deviceToken}`;
  const cartBar = document.getElementById('cartBar');
  const drawer = document.getElementById('cartDrawer');
  const backdrop = document.getElementById('drawerBackdrop');
  const cartItems = document.getElementById('cartItems');
  const cartDrawerContent = document.getElementById('cartDrawerContent');
  const cartScrollCue = document.getElementById('cartScrollCue');
  const cartSummaryCaption = document.getElementById('cartSummaryCaption');
  const cartModeRow = document.getElementById('cartModeRow');
  const orderNoteDisclosure = document.getElementById('orderNoteDisclosure');
  const cartSuggestion = document.getElementById('cartSuggestion');
  const cartStationWarning = document.getElementById('cartStationWarning');
  const cartCount = document.getElementById('cartCount');
  const cartTotal = document.getElementById('cartTotal');
  const cartBarTotal = document.getElementById('cartBarTotal');
  const customerNote = document.getElementById('customerNote');
  const submit = document.getElementById('submitOrder');
  const submitText = document.getElementById('submitOrderText');
  const successModal = document.getElementById('successModal');
  const successOrderCode = document.getElementById('successOrderCode');
  const successProgress = document.getElementById('successProgress');
  const waiterModal = document.getElementById('waiterModal');
  const tableChangeModal = document.getElementById('tableChangeModal');
  const tableChangeBanner = document.getElementById('tableChangeBanner');
  const eventsSheet = document.getElementById('eventsSheet');
  const itemDetailModal = document.getElementById('itemDetailModal');
  const itemDetailMedia = document.getElementById('itemDetailMedia');
  const itemDetailTitle = document.getElementById('itemDetailTitle');
  const itemDetailPrice = document.getElementById('itemDetailPrice');
  const itemDetailTags = document.getElementById('itemDetailTags');
  const itemDetailDescription = document.getElementById('itemDetailDescription');
  const itemDetailActions = document.getElementById('itemDetailActions');
  const itemDetailNote = document.getElementById('itemDetailNote');
  const itemDetailNoteBlock = document.getElementById('itemDetailNoteBlock');
  const itemDetailOptionSlot = document.getElementById('itemDetailOptionSlot');
  const itemDetailSuggestion = document.getElementById('itemDetailSuggestion');
  const itemDetailSuggestionTitle = document.getElementById('itemDetailSuggestionTitle');
  const itemDetailSuggestionMedia = document.getElementById('itemDetailSuggestionMedia');
  const itemDetailSuggestionPrice = document.getElementById('itemDetailSuggestionPrice');
  const itemDetailSuggestionAdd = document.getElementById('itemDetailSuggestionAdd');
  const itemDetailPrimary = document.getElementById('itemDetailPrimary');
  const recentOrderBadge = document.getElementById('recentOrderBadge');
  const cartContext = document.getElementById('cartContext');
  const statusText = document.getElementById('orderStatusText');
  const statusBox = document.getElementById('orderLiveStatus');
  const toast = document.getElementById('guestToast');
  const tableNotice = document.getElementById('tableNotice');
  const recentBar = document.getElementById('recentOrderBar');
  const guestOrdersModal = document.getElementById('guestOrdersModal');
  const guestOrdersList = document.getElementById('guestOrdersList');
  const guestOrdersEmpty = document.getElementById('guestOrdersEmpty');
  const guestConfirmModal = document.getElementById('guestConfirmModal');
  const guestConfirmTitle = document.getElementById('guestConfirmTitle');
  const guestConfirmText = document.getElementById('guestConfirmText');
  const guestConfirmAccept = document.getElementById('guestConfirmAccept');
  const guestConfirmCancel = document.getElementById('guestConfirmCancel');
  const guestTakeawayDisclosure=document.getElementById('guestTakeawayDisclosure');
  const guestTakeawayDisclosureTitle=document.getElementById('guestTakeawayDisclosureTitle');
  const guestTakeawayDisclosureCopy=document.getElementById('guestTakeawayDisclosureCopy');
  const guestTakeawayLayer=document.getElementById('guestTakeawayLayer');
  const guestTakeawaySheet=document.getElementById('guestTakeawaySheet');
  const guestTakeawayList=document.getElementById('guestTakeawayList');
  const guestTakeawayAll=document.getElementById('guestTakeawayAll');
  const guestTakeawayConfirm=document.getElementById('guestTakeawayConfirm');
  const guestTakeawayCancel=document.getElementById('guestTakeawayCancel');
  const guestTakeawayClose=document.getElementById('guestTakeawayClose');
  const guestTakeawayBackdrop=document.getElementById('guestTakeawayBackdrop');
  const guestOrderConflict = document.getElementById('guestOrderConflict');
  const guestOrderConflictTitle = document.getElementById('guestOrderConflictTitle');
  const guestOrderConflictText = document.getElementById('guestOrderConflictText');
  const guestOrderConflictPrimary = document.getElementById('guestOrderConflictPrimary');
  const guestOrderConflictSecondary = document.getElementById('guestOrderConflictSecondary');
  const guestEditExit = document.getElementById('guestEditExit');
  const cartTotalLabel = document.getElementById('cartTotalLabel');
  const cartFulfillmentSummary = document.getElementById('cartFulfillmentSummary');

  let pendingToken = '';
  let pendingSignature = '';
  let submitting = false;
  let trackingTimer = null;
  let currentTracking = null;
  let toastTimer = null;
  let contextTimer = null;
  let waiterTimer = null;
  let lastSuggestionId = 0;
  let lastSuggestionSourceId = 0;
  let lastCartReviewSignature = '';
  let searchMetricTimer = null;
  let lastRecordedSearch = '';
  let waiterState = null;
  let waiterAction = 'create';
  let pendingTableConfirmation = null;
  let currentItemDetailId = 0;
  let itemDetailDraftQuantity = 1;
  let guestOrders = [];
  let editingOrder = null;
  let editingOrderCode = '';
  let editingOrderSignature = '';
  let editingOrderNeedsReconcile = false;
  let guestOrdersTimer = null;

  const focusable = 'button:not([disabled]),a[href],input:not([disabled]),textarea:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';
  document.querySelectorAll('[role="dialog"][aria-hidden="true"]').forEach((layer) => { if ('inert' in layer) layer.inert = true; });

  const layerManager = (() => {
    let current = null;
    let trigger = null;
    let historyActive = false;
    let reusableClosedEntry = false;
    let locked = false;
    const queue = [];
    const isDrawer = (layer) => layer === drawer;
    const backgroundNodes = [...document.querySelectorAll('.guest-header,.guest-main,.guest-notices,.table-change-banner,.recent-order,.guest-action-dock')];
    const setBackgroundInert = (value) => backgroundNodes.forEach((node) => { if ('inert' in node) node.inert = value; });

    function lockPage() {
      if (locked) return;
      // Do not rewrite body position, scrollTop or scroll-behavior. Those
      // techniques caused Chrome Android to paint the document from the top
      // before returning to the selected item. Background gestures are blocked
      // by the layer manager while the document stays at its native position.
      document.documentElement.classList.add('guest-layer-open');
      setBackgroundInert(true);
      locked = true;
    }

    function unlockPage() {
      if (!locked) return;
      document.documentElement.classList.remove('guest-layer-open');
      setBackgroundInert(false);
      locked = false;
    }

    function show(layer) {
      if (!layer) return;
      if (isDrawer(layer)) {
        if ('inert' in layer) layer.inert = false;
        layer.classList.add('open');
        backdrop?.classList.add('open');
        layer.setAttribute('aria-hidden', 'false');
        document.getElementById('openCart')?.setAttribute('aria-expanded', 'true');
        document.body.classList.add('cart-drawer-open');
      } else {
        if ('inert' in layer) layer.inert = false;
        layer.classList.remove('hidden');
      }
      current = layer;
      lockPage();
      const target = layer.querySelector(focusable);
      if (target) setTimeout(() => target.focus({ preventScroll: true }), 0);
    }

    function hide(layer) {
      if (!layer) return;
      if (isDrawer(layer)) {
        layer.classList.remove('open');
        backdrop?.classList.remove('open');
        layer.setAttribute('aria-hidden', 'true');
        document.getElementById('openCart')?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('cart-drawer-open');
      } else {
        layer.classList.add('hidden');
      }
      if ('inert' in layer) layer.inert = true;
    }

    function processQueue() {
      if (current || !queue.length) return;
      const next = queue.shift();
      open(next.layer, next.options);
    }

    function finishClose(layer) {
      if (!layer || layer !== current) return;
      hide(layer);
      current = null;
      unlockPage();
      // Touch close must not scroll a prior card or raise an IME. Keyboard
      // users return to the exact control that opened the layer.
      const focusTarget = trigger;
      trigger = null;
      if (keyboardGuestInteraction() && focusTarget?.isConnected) requestAnimationFrame(() => focusTarget.focus?.({ preventScroll: true }));
      setTimeout(processQueue, 0);
    }

    function open(layer, options = {}) {
      if (!layer || current === layer) return;
      if (current) {
        if (options.queue) {
          if (!queue.some((entry) => entry.layer === layer)) queue.push({ layer, options: { ...options, queue: false } });
          return;
        }
        hide(current);
        current = null;
      } else {
        trigger = document.activeElement;
      }
      if (!historyActive) {
        try {
          const state = { ...(history.state || {}), cafeLayer: true, cafeLayerClosed: false };
          if (reusableClosedEntry) history.replaceState(state, '', location.href);
          else history.pushState(state, '', location.href);
          reusableClosedEntry = false;
          historyActive = true;
        } catch (_) {}
      }
      show(layer);
    }

    function close(layer = current, options = {}) {
      if (!layer || layer !== current) return;
      if (historyActive && options.fromPop !== true) {
        // Explicit close must never traverse browser history: history.back() was
        // the source of the visible top-to-return-position scroll regression.
        // Reuse this entry on the next layer open and transparently skip it when
        // the user later presses the browser Back button.
        try {
          history.replaceState({ ...(history.state || {}), cafeLayer: false, cafeLayerClosed: true }, '', location.href);
          reusableClosedEntry = true;
        } catch (_) {}
        historyActive = false;
      }
      finishClose(layer);
    }

    function currentLayer() { return current; }

    window.addEventListener('popstate', () => {
      if (current) {
        historyActive = false;
        reusableClosedEntry = false;
        finishClose(current);
        return;
      }
      if (reusableClosedEntry) {
        reusableClosedEntry = false;
        setTimeout(() => history.back(), 0);
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && current) {
        if (current === tableChangeModal) return;
        event.preventDefault();
        close();
        return;
      }
      if (event.key !== 'Tab' || !current) return;
      const list = [...current.querySelectorAll(focusable)].filter((element) => element.offsetParent !== null);
      if (!list.length) return;
      const first = list[0];
      const last = list[list.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault(); last.focus({ preventScroll: true });
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first.focus({ preventScroll: true });
      }
    });

    const layerScrollSelector = '.item-detail-scroll,.guest-events-panel,.guest-orders-list,.success-box,.drawer-content,.cart-drawer,.public-table-picker,.guest-takeaway-list';
    const blockBackgroundGesture = (event) => {
      if (!current) return;
      const target = event.target instanceof Element ? event.target : null;
      if (target?.closest(layerScrollSelector)) return;
      event.preventDefault();
    };
    document.addEventListener('wheel', blockBackgroundGesture, { capture: true, passive: false });
    document.addEventListener('touchmove', blockBackgroundGesture, { capture: true, passive: false });

    return { open, close, currentLayer };
  })();

  function bindDismissibleBackdrop(layer) {
    if (!layer) return;
    let startedOnBackdrop = false;
    let activePointerId = null;
    layer.addEventListener('pointerdown', (event) => {
      startedOnBackdrop = event.target === layer;
      activePointerId = startedOnBackdrop ? event.pointerId : null;
      if (!startedOnBackdrop) return;
      event.preventDefault();
      event.stopPropagation();
      try { layer.setPointerCapture(event.pointerId); } catch (_) {}
    }, { capture: true });
    layer.addEventListener('pointerup', (event) => {
      const shouldClose = startedOnBackdrop && activePointerId === event.pointerId && layerManager.currentLayer() === layer;
      if (startedOnBackdrop) {
        event.preventDefault();
        event.stopPropagation();
      }
      startedOnBackdrop = false;
      activePointerId = null;
      if (shouldClose) {
        layerManager.close(layer);
        document.documentElement.dataset.suppressGuestClick = '1';
        setTimeout(() => { delete document.documentElement.dataset.suppressGuestClick; }, 450);
      }
    }, { capture: true });
    layer.addEventListener('pointercancel', () => { startedOnBackdrop = false; activePointerId = null; }, { capture: true });
    layer.addEventListener('click', (event) => {
      if (event.target !== layer) return;
      event.preventDefault();
      event.stopPropagation();
      // Keyboard/synthetic click fallback. Real pointer input closes on pointerup;
      // checking the active layer prevents the follow-up click from closing or
      // activating anything underneath.
      if (layerManager.currentLayer() === layer) {
        layerManager.close(layer);
        document.documentElement.dataset.suppressGuestClick = '1';
        setTimeout(() => { delete document.documentElement.dataset.suppressGuestClick; }, 450);
      }
    }, { capture: true });
  }

  [successModal, guestOrdersModal, waiterModal, itemDetailModal, eventsSheet, guestConfirmModal].forEach(bindDismissibleBackdrop);

  const bindGuestSwipe = window.SoknaGuestSheet?.bindSwipeDismiss;
  if (bindGuestSwipe) {
    bindGuestSwipe({
      layer: drawer, panel: drawer, handle: drawer?.querySelector('.drawer-head'), close: closeDrawer,
      transformFor: (y) => `translate(-50%, ${Math.max(0, y)}px)`,
    });
    bindGuestSwipe({
      layer: itemDetailModal, panel: itemDetailModal?.querySelector('.item-detail-panel'), handle: itemDetailModal?.querySelector('.item-detail-grip'),
      close: () => { currentItemDetailId = 0; itemDetailDraftQuantity = 1; layerManager.close(itemDetailModal); },
    });
    bindGuestSwipe({
      layer: guestOrdersModal, panel: guestOrdersModal?.querySelector('.guest-orders-box'), handle: guestOrdersModal?.querySelector('.guest-orders-head'),
      close: () => layerManager.close(guestOrdersModal),
    });
    bindGuestSwipe({
      layer: eventsSheet, panel: eventsSheet?.querySelector('.guest-events-panel'), handle: eventsSheet?.querySelector('.guest-events-head'),
      close: () => layerManager.close(eventsSheet),
    });
  }

  function openEventsLayer(event) {
    event?.preventDefault();
    event?.stopPropagation();
    if (!eventsSheet) return;
    if (searchZone?.classList.contains('search-engaged')) closeSearch();
    metric('event_open', 0);
    layerManager.open(eventsSheet);
  }

  document.querySelectorAll('[data-open-events],a[href="#events"]').forEach((control) => {
    control.addEventListener('click', openEventsLayer);
  });
  document.querySelectorAll('[data-close-events]').forEach((control) => {
    control.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      layerManager.close(eventsSheet);
    });
  });

  function msg(key, fallback, vars = {}) {
    let text = String(messages[key] ?? fallback ?? key);
    for (const [name, value] of Object.entries(vars)) text = text.replaceAll(`{${name}}`, String(value));
    return text;
  }

  function metric(metricKey, refId = 0, extra = {}) {
    if (!window.CAFE_ANALYTICS_ENABLED || !window.CAFE_METRIC_URL) return;
    fetch(window.CAFE_METRIC_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      keepalive: true,
      body: JSON.stringify({ metric_key: metricKey, ref_id: Number(refId) || 0, ...extra, csrf_token: csrf }),
    }).catch(() => {});
  }

  function showToast(text, type = '') {
    if (!toast) return;
    toast.textContent = text;
    toast.className = `guest-toast ${type}`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.add('hidden'), 4200);
  }

  function saveState() {
    try {
      localStorage.setItem(storageKey, JSON.stringify({
        items: [...cart.values()].map((line) => ({
          id: Number(line.item.id),
          quantity: line.quantity,
          note: line.note || '',
          takeaway_quantity: Number(line.takeaway_quantity || 0),
          unit_price: Number(line.item.price),
        })),
        note: customerNote?.value || '',
        pendingToken,
        pendingSignature,
        editingOrderCode,
        editingOrderSignature,
      }));
    } catch (_) {}
  }

  function restoreState() {
    try {
      const currentRaw = localStorage.getItem(storageKey);
      const saved = JSON.parse(currentRaw || 'null');
      if (!saved) return;
      (saved.items || []).forEach((line) => {
        const item = items.get(Number(line.id));
        if (item && Number(item.available)) {
          cart.set(Number(line.id), {
            item,
            quantity: Math.max(1, Math.min(20, Number(line.quantity) || 1)),
            note: String(line.note || ''),
            takeaway_quantity: Math.max(0, Math.min(Number(line.quantity)||1, Number(line.takeaway_quantity)||0)),
          });
        }
      });
      if (customerNote) customerNote.value = String(saved.note || '');
      pendingToken = String(saved.pendingToken || '');
      pendingSignature = String(saved.pendingSignature || '');
      editingOrderCode = String(saved.editingOrderCode || '');
      editingOrderSignature = String(saved.editingOrderSignature || '');
      editingOrderNeedsReconcile = Boolean(editingOrderCode);
    } catch (_) {}
  }

  function totals() {
    let quantity = 0;
    let total = 0;
    for (const line of cart.values()) {
      quantity += line.quantity;
      total += Number(line.item.price) * line.quantity;
    }
    return { quantity, total };
  }

  function setInlineCounts() {
    document.querySelectorAll('[data-inline-count]').forEach((element) => {
      const id = Number(element.dataset.inlineCount);
      const quantity = cart.get(id)?.quantity || 0;
      element.textContent = new Intl.NumberFormat('fa-IR').format(quantity);
      element.closest('.inline-qty')?.classList.toggle('has-items', quantity > 0);
    });
    document.querySelectorAll('[data-menu-item],[data-featured-item]').forEach((element) => {
      element.classList.toggle('is-selected', Boolean(cart.get(Number(element.dataset.itemId))?.quantity));
    });
  }

  function renderItemDetailActions() {
    if (!itemDetailActions || !currentItemDetailId) return;
    const item = items.get(Number(currentItemDetailId));
    if (!item) return;
    if (!canOrderItem(item)) {
      itemDetailActions.innerHTML = `<div class="item-detail-unavailable">${esc(itemUnavailableLabel(item))}</div>`;
      itemDetailPrimary?.classList.add('hidden');
      return;
    }
    itemDetailPrimary?.classList.remove('hidden');
    itemDetailActions.innerHTML = `<div class="detail-qty-control" aria-label="تعداد ${esc(item.name)}"><button type="button" data-detail-dec aria-label="کم کردن ${esc(item.name)}">${icon('minus')}</button><strong>${new Intl.NumberFormat('fa-IR').format(itemDetailDraftQuantity)}</strong><button type="button" data-detail-inc aria-label="بیشتر کردن ${esc(item.name)}">${icon('plus')}</button></div>`;
    const currentQuantity = cart.get(Number(item.id))?.quantity || 0;
    const actionLabel = itemDetailDraftQuantity === 0
      ? 'حذف از سفارش'
      : currentQuantity > 0 ? 'ثبت تغییرات' : 'افزودن به سفارش';
    const label = itemDetailPrimary?.querySelector('span');
    if (label) label.textContent = actionLabel;
  }

  function renderItemDetailSuggestion(item) {
    if (!itemDetailSuggestion || !itemDetailNoteBlock) return;
    const currentLine = cart.get(Number(item?.id));
    const hasSavedNote = Boolean(String(itemDetailNote?.value || currentLine?.note || '').trim());
    const suggested = items.get(Number(item?.suggested_item_id || 0));
    const suggestionVisible = Boolean(
      !hasSavedNote
      && suggested
      && canOrderItem(suggested)
      && Number(suggested.id) !== Number(item?.id)
      && !cart.has(Number(suggested.id))
    );

    itemDetailSuggestion.classList.toggle('hidden', !suggestionVisible);
    itemDetailNoteBlock.classList.toggle('hidden', suggestionVisible);
    if (itemDetailOptionSlot) itemDetailOptionSlot.classList.toggle('has-suggestion', suggestionVisible);

    if (!suggestionVisible) {
      itemDetailSuggestionAdd?.removeAttribute('data-suggest-add');
      return;
    }
    if (itemDetailSuggestionTitle) itemDetailSuggestionTitle.textContent = suggested.name;
    if (itemDetailSuggestionPrice) itemDetailSuggestionPrice.textContent = money(suggested.price);
    if (itemDetailSuggestionMedia) itemDetailSuggestionMedia.innerHTML = suggested.image
      ? `<img src="${esc(suggested.image)}" alt="">`
      : icon('coffee');
    if (itemDetailSuggestionAdd) {
      itemDetailSuggestionAdd.dataset.suggestAdd = String(suggested.id);
      itemDetailSuggestionAdd.disabled = false;
      itemDetailSuggestionAdd.setAttribute('aria-label', `افزودن ${suggested.name}`);
    }
  }

  function openItemDetail(id, trigger = null) {
    const item = items.get(Number(id));
    if (!item || !itemDetailModal) return;
    currentItemDetailId = Number(item.id);
    itemDetailDraftQuantity = cart.get(Number(item.id))?.quantity || 1;
    if (itemDetailNote) itemDetailNote.value = cart.get(Number(item.id))?.note || '';
    if (itemDetailMedia) itemDetailMedia.innerHTML = item.image
      ? `<img src="${esc(item.image)}" alt="${esc(item.name)}">`
      : icon('coffee');
    if (itemDetailTitle) itemDetailTitle.textContent = item.name || '';
    if (itemDetailPrice) itemDetailPrice.textContent = money(item.price);
    if (itemDetailDescription) {
      const description = String(item.description || '').trim();
      const uniqueDescription = normalize(description) === normalize(item.name) ? '' : description;
      itemDetailDescription.textContent = uniqueDescription;
      itemDetailDescription.classList.toggle('hidden', !uniqueDescription);
    }
    if (itemDetailTags) {
      const detailTags = (item.tags || []).slice(0, 5).map((tag) => `<span class="menu-tag tag-${esc(tag.color_key || 'neutral')}">${esc(`${tag.icon || ''} ${tag.title || ''}`.trim())}</span>`);
      itemDetailTags.innerHTML = detailTags.join('');
      itemDetailTags.classList.toggle('hidden', !detailTags.length);
    }
    renderItemDetailActions();
    renderItemDetailSuggestion(item);
    metric('item_detail_open', Number(item.id));
    layerManager.open(itemDetailModal);
  }

  function cartReviewSignature() {
    return JSON.stringify({
      lines: [...cart.values()].map((line) => [Number(line.item.id), Number(line.quantity || 0), Number(line.takeaway_quantity || 0), String(line.note || '')]),
      note: String(customerNote?.value || ''),
    });
  }

  function resetSuggestion() {
    lastSuggestionId = 0;
    lastSuggestionSourceId = 0;
  }

  function renderSuggestion() {
    if (!cartSuggestion) return;
    const totalUnits = [...cart.values()].reduce((sum, line) => sum + Number(line.quantity || 0), 0);
    const isLargeCart = cart.size >= 4 || totalUnits >= 8;
    const sourceStillPresent = lastSuggestionSourceId > 0 && cart.has(Number(lastSuggestionSourceId));
    const suggested = sourceStillPresent ? items.get(Number(lastSuggestionId)) : null;
    if (!suggested || !Number(suggested.available) || cart.has(Number(suggested.id))) {
      cartSuggestion.classList.add('hidden');
      cartSuggestion.classList.remove('cart-is-large');
      cartSuggestion.innerHTML = '';
      if (!sourceStillPresent) resetSuggestion();
      return;
    }
    cartSuggestion.classList.toggle('cart-is-large', isLargeCart);
    if (isLargeCart) {
      cartSuggestion.innerHTML = `<details class="cart-suggestion-details"><summary><span><small>پیشنهاد ویژه برای شما</small><strong>${esc(suggested.name)}</strong></span><b aria-hidden="true">${icon('chevron-left')}</b></summary><div class="cart-suggestion-body"><span>${money(suggested.price)}</span><button type="button" data-suggest-add="${Number(suggested.id)}">افزودن</button></div></details>`;
    } else {
      cartSuggestion.innerHTML = `<div><small>پیشنهاد ویژه برای شما</small><strong>${esc(suggested.name)}</strong><span>${money(suggested.price)}</span></div><button type="button" data-suggest-add="${Number(suggested.id)}">افزودن</button>`;
    }
    cartSuggestion.classList.remove('hidden');
  }

  function updateCartScrollCue() {
    if (!cartDrawerContent) return;
    const hasMore = cartDrawerContent.scrollHeight - cartDrawerContent.scrollTop - cartDrawerContent.clientHeight > 8;
    cartScrollCue?.classList.toggle('is-visible', hasMore);
    cartDrawerContent.classList.toggle('has-scroll-more', hasMore);
  }

  const guestTakeawaySheetIsOpen=()=>Boolean(guestTakeawayLayer&&!guestTakeawayLayer.classList.contains('hidden'));
  function renderGuestTakeawaySheet(){
    if(!guestTakeawayList||!takeawayDraft)return;
    let eligibleTotal=0,draftTake=0;
    guestTakeawayList.innerHTML=[...cart.values()].map(line=>{
      const id=Number(line.item.id),q=Number(line.quantity||0),eligible=fulfillment.allowed(line.item);
      const take=eligible?Math.max(0,Math.min(q,Number(takeawayDraft.get(id)||0))):0;
      if(eligible){eligibleTotal+=q;draftTake+=take;}
      let control='';
      if(!eligible) control='<span class="guest-takeaway-ineligible">فقط داخل کافه</span>';
      else if(q===1) control=`<button class="guest-takeaway-toggle ${take?'is-on':''}" type="button" data-guest-takeaway-toggle="${id}" aria-pressed="${take?'true':'false'}">${take?`${icon('check')} بیرون‌بر`:'داخل کافه'}</button>`;
      else control=`<div class="guest-takeaway-stepper"><button type="button" data-guest-takeaway-delta="-1" data-id="${id}" aria-label="کم‌کردن تعداد بیرون‌بر"${take<=0?' disabled':''}>${icon('minus')}</button>${fulfillment.ratioHtml(take,q,numberFa)}<button type="button" data-guest-takeaway-delta="1" data-id="${id}" aria-label="افزودن تعداد بیرون‌بر"${take>=q?' disabled':''}>${icon('plus')}</button></div>`;
      return `<article class="guest-takeaway-row"><div><strong>${esc(line.item.name)}</strong><small>${numberFa(q)} عدد</small></div>${control}</article>`;
    }).join('');
    if(guestTakeawayAll){guestTakeawayAll.textContent=eligibleTotal>0&&draftTake===eligibleTotal?'همه داخل کافه':'همه بیرون‌بر';guestTakeawayAll.disabled=eligibleTotal===0;}
  }
  function openGuestTakeawaySheet(trigger=guestTakeawayDisclosure){
    if(!guestTakeawayLayer||cartEligibleQuantity()===0)return;
    takeawayDraft=new Map();for(const line of cart.values()){clampTakeaway(line);takeawayDraft.set(Number(line.item.id),Number(line.takeaway_quantity||0));}
    takeawaySheetOpener=trigger||document.activeElement;
    renderGuestTakeawaySheet();guestTakeawayLayer.classList.remove('hidden');guestTakeawayLayer.setAttribute('aria-hidden','false');
    requestAnimationFrame(()=>guestTakeawaySheet?.querySelector('button:not(:disabled)')?.focus?.({preventScroll:true}));
  }
  function closeGuestTakeawaySheet(apply=false){
    if(!guestTakeawayLayer||!guestTakeawaySheetIsOpen())return;
    if(apply&&takeawayDraft){for(const line of cart.values()){const id=Number(line.item.id);line.takeaway_quantity=fulfillment.allowed(line.item)?Math.max(0,Math.min(Number(line.quantity||0),Number(takeawayDraft.get(id)||0))):0;}pendingToken='';pendingSignature='';}
    takeawayDraft=null;guestTakeawayLayer.classList.add('hidden');guestTakeawayLayer.setAttribute('aria-hidden','true');render();
    const opener=takeawaySheetOpener;takeawaySheetOpener=null;requestAnimationFrame(()=>opener?.focus?.({preventScroll:true}));
  }

  function render() {
    const total = totals();
    const totalTakeaway = cartTakeawayQuantity();
    const eligibleQuantity=cartEligibleQuantity();
    guestTakeawayDisclosure?.classList.toggle('hidden', total.quantity===0 || eligibleQuantity===0);
    if(guestTakeawayDisclosureTitle) guestTakeawayDisclosureTitle.textContent=totalTakeaway>0?'تغییر موارد بیرون‌بر':'بیرون‌بر هم دارید؟';
    if(guestTakeawayDisclosureCopy) guestTakeawayDisclosureCopy.textContent=totalTakeaway>0?'برای مشاهده یا تغییر موارد بیرون‌بر، اینجا بزنید.':'در صورت نیاز، موارد بیرون‌بر را مشخص کنید.';
    if (cartFulfillmentSummary) {
      cartFulfillmentSummary.textContent = totalTakeaway > 0 ? `${numberFa(totalTakeaway)} عدد بیرون‌بر` : '';
      cartFulfillmentSummary.classList.toggle('hidden', totalTakeaway === 0);
    }
    const appendTarget = mutableAppendTarget();
    guestOrderConflict?.classList.toggle('hidden', !editConflict);
    if (editConflict) {
      const changed = editConflict.kind === 'changed' && editConflict.latest?.can_edit;
      if (guestOrderConflictTitle) guestOrderConflictTitle.textContent = changed ? 'سفارش در جای دیگری تغییر کرده است.' : 'این سفارش دیگر قابل ویرایش نیست.';
      if (guestOrderConflictText) guestOrderConflictText.textContent = changed
        ? 'برای جلوگیری از بازنویسی تغییرات جدید، آخرین نسخه را بارگذاری کنید.'
        : 'کافه این سفارش را تأیید کرده یا وضعیت آن تغییر کرده است. نسخه قبلی به سفارش جدید تبدیل نمی‌شود.';
      if (guestOrderConflictPrimary) guestOrderConflictPrimary.textContent = changed ? 'بارگذاری آخرین نسخه' : 'مشاهده سفارش‌ها';
      if (guestOrderConflictSecondary) guestOrderConflictSecondary.textContent = changed ? 'انصراف از ویرایش' : 'شروع سفارش جدید';
    }
    if (cartCount) cartCount.textContent = new Intl.NumberFormat('fa-IR').format(total.quantity);
    if (cartContext) cartContext.textContent = table ? `${textFaDigits(table.name)} • ${new Intl.NumberFormat('fa-IR').format(cart.size)} قلم، ${new Intl.NumberFormat('fa-IR').format(total.quantity)} عدد` : `${new Intl.NumberFormat('fa-IR').format(cart.size)} قلم، ${new Intl.NumberFormat('fa-IR').format(total.quantity)} عدد`;
    if (cartTotal) cartTotal.textContent = money(total.total);
    if (cartBarTotal) cartBarTotal.textContent = money(total.total);
    if (cartTotalLabel) cartTotalLabel.textContent = !editingOrder && appendTarget ? 'جمع موارد جدید' : 'جمع سفارش';
    if (cartSummaryCaption) {
      const modeText = editingOrderCode
        ? `در حال ویرایش ${orderNumberLabel(editingOrder?.order_number || editConflict?.latest?.order_number)}`
        : appendTarget && total.quantity > 0 ? `این موارد به ${orderNumberLabel(appendTarget.order_number)} اضافه می‌شوند` : '';
      cartSummaryCaption.textContent = modeText;
      cartSummaryCaption.classList.toggle('hidden', !modeText);
    }
    guestEditExit?.classList.toggle('hidden', !editingOrderCode);
    cartModeRow?.classList.toggle('hidden', !editingOrderCode && !(appendTarget && total.quantity > 0));
    if (orderNoteDisclosure && String(customerNote?.value || '').trim()) orderNoteDisclosure.open = true;
    cartBar?.classList.toggle('hidden', total.quantity === 0);
    document.body.classList.toggle('has-cart', total.quantity > 0);
    setInlineCounts();
    renderItemDetailActions();
    if (currentItemDetailId) renderItemDetailSuggestion(items.get(Number(currentItemDetailId)));
    updateSubmitMode();
    if (!cartItems) return;

    if (!total.quantity) {
      cartItems.innerHTML = `<div class="empty-cart">${esc(msg('cart_empty', 'هنوز چیزی انتخاب نکردی.'))}</div>`;
      resetSuggestion();
      cartStationWarning?.classList.add('hidden');
      renderSuggestion();
      saveState();
      requestAnimationFrame(updateCartScrollCue);
      return;
    }

    const openNoteIds = new Set([...cartItems.querySelectorAll('.line-note-disclosure[open]')]
      .map((element) => Number(element.closest('[data-line]')?.dataset.line || 0))
      .filter(Boolean));
    const cartLines = [...cart.values()].map((line) => {
      const itemId = Number(line.item.id);
      const lineTotal = Number(line.item.price) * Number(line.quantity);
      const unitCopy = Number(line.quantity) > 1 ? `<bdi>${numberFa(line.quantity)}</bdi><span aria-hidden="true">×</span><bdi>${numberFa(line.item.price)}</bdi>` : '';
      const note = String(line.note || '').trim();
      const noteIsOpen = Boolean(note) && openNoteIds.has(itemId);
      const noteBlock = messages.item_note_placeholder_enabled === false || !note ? '' : `<details class="line-note-disclosure has-note"${noteIsOpen ? ' open' : ''}><summary><span>یادداشت: ${esc(note)}</span><b>ویرایش</b></summary><textarea class="line-note" maxlength="500" data-note="${itemId}" placeholder="${esc(msg('item_note_placeholder', 'مثلاً بدون شکر'))}">${esc(note)}</textarea></details>`;
      clampTakeaway(line);const take=Number(line.takeaway_quantity||0);
      const fulfillmentBadge=take>0?`<span class="cart-line-takeaway-badge">${esc(fulfillment.label(line,numberFa))}</span>`:'';
      return `<div class="cart-line" data-line="${itemId}"><div class="cart-line-main"><div class="cart-line-heading"><h4>${esc(line.item.name)}</h4><div class="cart-line-total">${numberFa(lineTotal)}</div></div><div class="cart-line-lower"><div class="cart-line-meta"><div class="cart-line-price">${unitCopy}</div>${fulfillmentBadge}</div><div class="qty-control" aria-label="تعداد ${esc(line.item.name)}"><button type="button" data-dec="${itemId}" aria-label="کم کردن ${esc(line.item.name)}">${icon('minus')}</button><strong>${numberFa(line.quantity)}</strong><button type="button" data-inc="${itemId}" aria-label="بیشتر کردن ${esc(line.item.name)}">${icon('plus')}</button></div></div>${noteBlock}</div></div>`;
    }).join('');
    cartItems.innerHTML = `<div class="cart-currency-note">مبالغ به تومان</div>${cartLines}`;
    if (cartStationWarning) {
      const delayed = [...cart.values()].filter((line) => Number(line.item.station_busy) === 1).map((line) => line.item.name);
      cartStationWarning.textContent = delayed.length ? msg('station_busy_cart', 'این انتخاب‌ها ممکنه با کمی تأخیر آماده بشن: {items}', { items: delayed.join('، ') }) : '';
      cartStationWarning.classList.toggle('hidden', delayed.length === 0);
    }
    renderSuggestion();
    saveState();
    requestAnimationFrame(updateCartScrollCue);
  }

  function clearPendingTableConfirmation() {
    pendingTableConfirmation = null;
    tableChangeBanner?.classList.add('hidden');
  }

  function setPendingTableConfirmation(config) {
    pendingTableConfirmation = config;
    tableChangeBanner?.classList.remove('hidden');
  }

  function requestTableConfirmation(onConfirm = () => {}) {
    if (!pendingTableConfirmation || !tableChangeModal) {
      onConfirm();
      return;
    }
    const config = pendingTableConfirmation;
    document.getElementById('tableChangeText').textContent = config.text;
    const yes = document.getElementById('confirmTableChange');
    const no = document.getElementById('rejectTableChange');
    yes.textContent = config.yesLabel || 'بله، همین میز';
    no.textContent = config.noLabel || 'QR میز فعلی';

    const cleanup = () => {
      yes.removeEventListener('click', confirm);
      no.removeEventListener('click', reject);
    };
    const confirm = () => {
      cleanup();
      config.onConfirm?.();
      clearPendingTableConfirmation();
      layerManager.close(tableChangeModal);
      setTimeout(onConfirm, 20);
    };
    const reject = () => {
      cleanup();
      clearPendingTableConfirmation();
      layerManager.close(tableChangeModal);
      config.onReject?.();
    };
    yes.addEventListener('click', confirm);
    no.addEventListener('click', reject);
    layerManager.open(tableChangeModal, { queue: true });
  }

  function openDrawer() {
    requestTableConfirmation(() => {
      const signature = cartReviewSignature();
      const changedSinceLastReview = lastCartReviewSignature !== '' && lastCartReviewSignature !== signature;
      if (changedSinceLastReview && cartDrawerContent) cartDrawerContent.scrollTop = 0;
      lastCartReviewSignature = signature;
      layerManager.open(drawer);
      requestAnimationFrame(updateCartScrollCue);
    });
  }

  function closeDrawer() {
    layerManager.close(drawer);
  }

  function changeQty(id, delta) {
    const activeControl = keyboardGuestInteraction()
      ? document.activeElement?.closest?.(`[data-inc="${id}"],[data-dec="${id}"]`)
      : null;
    const focusKind = activeControl?.hasAttribute('data-inc') ? 'inc' : (activeControl?.hasAttribute('data-dec') ? 'dec' : '');
    const existingLine = cart.get(id);
    const item = existingLine?.item || items.get(id);
    if (!item) return;
    // Pausing guest ordering must never trap a guest in an existing draft: decreases
    // remain possible, while every increase still obeys today's ordering state.
    if (delta > 0 && (!canOrder || !canOrderItem(items.get(id) || item))) return;
    if (delta < 0 && !existingLine) return;
    const inheritTakeaway = cartIsAllTakeaway();
    const previousQuantity = existingLine?.quantity || 0;
    const previousTakeaway = Number(existingLine?.takeaway_quantity || 0);
    const wasAllTakeaway = previousQuantity > 0 && previousTakeaway === previousQuantity;
    const line = existingLine || {
      item, quantity: 0, note: '',
      takeaway_quantity: 0,
    };
    line.quantity = Math.max(0, Math.min(20, line.quantity + delta));
    if (delta > 0 && (wasAllTakeaway || (!existingLine && inheritTakeaway))) {
      line.takeaway_quantity = line.quantity;
    } else {
      clampTakeaway(line);
    }
    if (!line.quantity) {
      cart.delete(id);
      if (Number(lastSuggestionSourceId) === Number(id)) resetSuggestion();
    } else cart.set(id, line);
    if (delta > 0) {
      lastSuggestionSourceId = Number(id);
      lastSuggestionId = Number(item.suggested_item_id) || 0;
      if (previousQuantity === 0) metric('item_add', id);
    }
    render();
    if (focusKind) {
      requestAnimationFrame(() => {
        const selector = focusKind === 'inc' ? `[data-inc="${id}"]` : `[data-dec="${id}"]`;
        const sameControl = cartItems?.querySelector(selector);
        const fallback = cartItems?.querySelector('[data-inc],[data-dec]') || document.getElementById('closeCart');
        (sameControl || fallback)?.focus?.({preventScroll:true});
      });
    }
  }

  document.addEventListener('click', (event) => {
    if (document.documentElement.dataset.suppressGuestClick === '1') {
      event.preventDefault();
      event.stopPropagation();
      return;
    }
    const detailIncrease = event.target.closest('[data-detail-inc]');
    if (detailIncrease) { itemDetailDraftQuantity = Math.min(20, itemDetailDraftQuantity + 1); renderItemDetailActions(); return; }
    const detailDecrease = event.target.closest('[data-detail-dec]');
    if (detailDecrease) { itemDetailDraftQuantity = Math.max(0, itemDetailDraftQuantity - 1); renderItemDetailActions(); return; }
    const add = event.target.closest('[data-add-item]');
    if (add) {
      if (!canOrder) {
        showToast(msg('table_inactive', 'این میز هنوز برای سفارش فعال نشده.'), 'warning');
      } else {
        changeQty(Number(add.dataset.addItem), 1);
      }
    }
    const suggestion = event.target.closest('[data-suggest-add]');
    if (suggestion) {
      changeQty(Number(suggestion.dataset.suggestAdd), 1);
      resetSuggestion();
      render();
      const current = items.get(Number(currentItemDetailId));
      if (current) renderItemDetailSuggestion(current);
    }
    const inlineDecrease = event.target.closest('[data-inline-dec]');
    if (inlineDecrease) changeQty(Number(inlineDecrease.dataset.inlineDec), -1);
    const increase = event.target.closest('[data-inc]');
    if (increase) changeQty(Number(increase.dataset.inc), 1);
    const decrease = event.target.closest('[data-dec]');
    if (decrease) changeQty(Number(decrease.dataset.dec), -1);
    const detailOpen = event.target.closest('[data-open-item-detail]');
    if (detailOpen) openItemDetail(Number(detailOpen.dataset.openItemDetail), detailOpen);
    const itemCard = event.target.closest('[data-menu-item],[data-featured-item]');
    if (itemCard && !event.target.closest('button,a,input,textarea,summary,details')) {
      openItemDetail(Number(itemCard.dataset.itemId), itemCard);
    }
    if (event.target.closest('[data-close-item-detail]')) {
      currentItemDetailId = 0;
      itemDetailDraftQuantity = 1;
      layerManager.close(itemDetailModal);
    }
    const aboutLink = event.target.closest('a[href*="about.php"]');
    if (aboutLink && table) {
      try { sessionStorage.setItem(`${storageKey}:return-scroll`, String(window.scrollY || 0)); } catch (_) {}
    }
    const tracked = event.target.closest('[data-metric]');
    if (tracked) metric(tracked.dataset.metric, tracked.dataset.refId || 0);
  });

  document.querySelectorAll('[data-menu-item],[data-featured-item]').forEach((card) => {
    const id = Number(card.dataset.itemId);
    card.removeAttribute('role');
    card.removeAttribute('tabindex');
    card.removeAttribute('aria-label');
    const button = document.createElement('button');
    button.className = 'menu-item-hit';
    button.type = 'button';
    button.dataset.openItemDetail = String(id);
    button.setAttribute('aria-label', `دیدن جزئیات ${items.get(id)?.name || 'آیتم'}`);
    card.prepend(button);
  });

  document.addEventListener('input', (event) => {
    if (!event.target.matches('[data-note]')) return;
    const line = cart.get(Number(event.target.dataset.note));
    if (line) {
      line.note = event.target.value;
      saveState();
    }
  });
  customerNote?.addEventListener('input', saveState);
  itemDetailNote?.addEventListener('input', () => {
    const current = items.get(Number(currentItemDetailId));
    if (current) renderItemDetailSuggestion(current);
  });


  itemDetailPrimary?.addEventListener('click', () => {
    const item = items.get(Number(currentItemDetailId));
    if (!item || !canOrderItem(item)) return;
    if (itemDetailDraftQuantity <= 0) {
      cart.delete(Number(item.id));
      if (Number(lastSuggestionSourceId) === Number(item.id)) resetSuggestion();
    } else {
      const inheritTakeaway = cartIsAllTakeaway();
      const previous = cart.get(Number(item.id));
      const previousQuantity = Number(previous?.quantity || 0);
      const previousTakeaway = Number(previous?.takeaway_quantity || 0);
      const wasAllTakeaway = previousQuantity > 0 && previousTakeaway === previousQuantity;
      cart.set(Number(item.id), {
        item,
        quantity: itemDetailDraftQuantity,
        note: String(itemDetailNote?.value || previous?.note || '').trim(),
        takeaway_quantity: previous
          ? (wasAllTakeaway ? itemDetailDraftQuantity : Math.min(itemDetailDraftQuantity, previousTakeaway))
          : (inheritTakeaway ? itemDetailDraftQuantity : 0),
        });
      lastSuggestionSourceId = Number(item.id);
      lastSuggestionId = Number(item.suggested_item_id) || 0;
      if (!previous) metric('item_add', Number(item.id));
    }
    render();
    currentItemDetailId = 0;
    itemDetailDraftQuantity = 1;
    layerManager.close(itemDetailModal);
  });

  cartDrawerContent?.addEventListener('scroll', updateCartScrollCue, { passive: true });
  window.addEventListener('resize', () => requestAnimationFrame(updateCartScrollCue), { passive: true });
  document.getElementById('openCart')?.addEventListener('click', openDrawer);
  document.getElementById('closeCart')?.addEventListener('click', closeDrawer);
  backdrop?.addEventListener('click', closeDrawer);
  document.getElementById('reviewTableChange')?.addEventListener('click', () => requestTableConfirmation());

  const mutableAppendTarget = () => editingOrderCode ? null : ([...guestOrders].reverse().find((order) => order.can_edit) || null);

  function clearEditingContext({ clearCart = false } = {}) {
    editingOrder = null;
    editingOrderCode = '';
    editingOrderSignature = '';
    editConflict = null;
    editingOrderNeedsReconcile = false;
    if (clearCart) {
      cart.clear();
      if (customerNote) customerNote.value = '';
      takeawayDraft = null;
      resetSuggestion();
      pendingToken = '';
      pendingSignature = '';
    }
  }

  function setEditConflict(kind, latest = null) {
    const orderCode = editingOrderCode || String(editingOrder?.order_code || latest?.order_code || '');
    editConflict = { kind: kind === 'changed' ? 'changed' : 'locked', orderCode, latest: latest || null };
  }

  function reconcileEditingOrder() {
    if (!editingOrderCode) { editingOrderNeedsReconcile = false; return; }
    const latest = guestOrders.find((order) => order.order_code === editingOrderCode) || null;
    if (!latest || !latest.can_edit) {
      setEditConflict('locked', latest);
      editingOrderNeedsReconcile = false;
      return;
    }
    const baseSignature = String(editingOrderSignature || editingOrder?.edit_signature || '');
    if (!baseSignature || !hashEqualsText(baseSignature, String(latest.edit_signature || ''))) {
      setEditConflict('changed', latest);
      editingOrderNeedsReconcile = false;
      return;
    }
    editingOrder = latest;
    editingOrderSignature = baseSignature;
    editConflict = null;
    editingOrderNeedsReconcile = false;
  }

  function updateSubmitMode() {
    if (!submitText) return;
    if (editingOrderNeedsReconcile) {
      submitText.textContent = 'در حال بررسی نسخه سفارش…';
      return;
    }
    if (editConflict) {
      submitText.textContent = 'ابتدا وضعیت سفارش را مشخص کنید';
      return;
    }
    if (editingOrder) {
      submitText.textContent = msg('submit_order_update', 'ذخیره تغییرات {order}', { order: orderNumberLabel(editingOrder.order_number) });
      return;
    }
    const appendTarget = mutableAppendTarget();
    submitText.textContent = appendTarget ? msg('submit_order_add', 'افزودن به {order}', { order: orderNumberLabel(appendTarget.order_number) }) : msg('submit_order', 'ثبت سفارش');
  }

  function renderGuestOrders() {
    if (!guestOrdersList) return;
    const active = guestOrders.filter((order) => order.status !== 'cancelled');
    guestOrdersEmpty?.classList.toggle('hidden', guestOrders.length > 0);
    const lineMarkup = (line) => {
      const quantity = Math.max(0, Number(line.quantity || 0));
      const note = String(line.note || '').trim();
      const takeaway = String(line.fulfillment_mode || '') === 'takeaway' ? `<b class="guest-order-takeaway">${esc(msg('fulfillment_takeaway','بیرون‌بر'))}</b>` : '';
      return `<div class="guest-order-line"><span>${esc(line.name)} <small>× ${numberFa(quantity)}</small>${takeaway}${note ? `<em class="guest-order-line-note">یادداشت: ${esc(note)}</em>` : ''}</span><strong>${numberFa(line.line_total)}</strong></div>`;
    };
    guestOrdersList.innerHTML = guestOrders.map((order) => {
      const isMutable = Boolean(order.can_edit || order.can_cancel);
      const orderItems = Array.isArray(order.items) ? order.items : [];
      const unitCount = orderItems.reduce((sum, line) => sum + Number(line.quantity || 0), 0);
      const previewLines = orderItems.slice(0, 4).map(lineMarkup).join('');
      const remainingLines = orderItems.slice(4);
      const more = remainingLines.length ? `<details class="guest-order-more"><summary>مشاهده ${numberFa(remainingLines.length)} قلم دیگر</summary><div class="guest-order-more-lines">${remainingLines.map(lineMarkup).join('')}</div></details>` : '';
      const statusLabel = orderStatusShortLabel(order.status);
      const statusClass = isMutable ? 'is-pending' : ['accounted','completed'].includes(String(order.status)) ? 'is-confirmed' : String(order.status)==='cancelled' ? 'is-cancelled' : '';
      const actions = (order.can_edit || order.can_cancel) ? `<div class="guest-order-actions">${order.can_edit ? `<button type="button" data-edit-guest-order="${esc(order.order_code)}">ویرایش سفارش</button>` : ''}${order.can_cancel ? `<button type="button" data-cancel-guest-order="${esc(order.order_code)}">لغو سفارش</button>` : ''}</div>` : '';
      return `<article class="guest-order-card ${statusClass}" data-guest-order="${esc(order.order_code)}"><header class="guest-order-card-head"><div><strong>${orderNumberLabel(order.order_number)}</strong><span class="guest-order-status">${esc(statusLabel)}</span></div><small>${numberFa(orderItems.length)} قلم · ${numberFa(unitCount)} عدد</small></header><div class="guest-order-lines">${previewLines || '<div class="guest-order-line"><span>بدون آیتم</span></div>'}${more}</div><footer class="guest-order-card-foot"><strong>${money(order.total_amount)}</strong>${actions}</footer></article>`;
    }).join('');
    if (guestOrders.length) guestOrdersList.insertAdjacentHTML('afterbegin', '<div class="guest-orders-currency">مبالغ به تومان</div>');

    if (guestOrders.length) {
      const latest = guestOrders[guestOrders.length - 1];
      if (recentOrderBadge) recentOrderBadge.textContent = new Intl.NumberFormat('fa-IR').format(active.length || guestOrders.length);
      applyOrderProgress(latest.status);
      recentBar?.classList.remove('hidden');
    } else {
      recentBar?.classList.add('hidden');
    }
    updateSubmitMode();
  }

  async function refreshGuestOrders(options = {}) {
    if (!table || !window.CAFE_GUEST_ORDERS_API_URL) return [];
    try {
      const response = await fetch(window.CAFE_GUEST_ORDERS_API_URL, {
        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, cache: 'no-store',
        body: JSON.stringify({ action: 'list', table_token: table.token, session_token: session?.token || '', device_token: deviceToken, csrf_token: csrf }),
      });
      const data = await response.json().catch(() => ({}));
      window.SoknaPushRuntime?.handleResponse?.(data);
      if (!response.ok || !data.success) throw new Error(data.message || 'سفارش‌ها دریافت نشدند.');
      guestOrders = Array.isArray(data.orders) ? data.orders : [];
      reconcileEditingOrder();
      renderGuestOrders();
      render();
      return guestOrders;
    } catch (error) {
      if (!options.silent) showToast(window.SoknaGuestUI?.requestErrorMessage?.(error, 'سفارش‌ها دریافت نشدند.') || 'سفارش‌ها دریافت نشدند.', 'error');
      return guestOrders;
    }
  }

  function openGuestOrders() {
    if (!guestOrdersModal) return;
    refreshGuestOrders({ silent: true });
    layerManager.open(guestOrdersModal);
  }

  function openGuestConfirmation({ title, text, acceptLabel, cancelLabel = 'انصراف', danger = false, onAccept, onCancel }) {
    if (!guestConfirmModal || !guestConfirmAccept || !guestConfirmCancel) return;
    if (guestConfirmTitle) guestConfirmTitle.textContent = title;
    if (guestConfirmText) guestConfirmText.textContent = text;
    guestConfirmAccept.textContent = acceptLabel;
    guestConfirmCancel.textContent = cancelLabel;
    guestConfirmAccept.classList.toggle('is-danger', danger);
    guestConfirmAccept.disabled = false;
    guestConfirmCancel.disabled = false;
    const cleanup = () => {
      guestConfirmAccept.removeEventListener('click', accept);
      guestConfirmCancel.removeEventListener('click', cancel);
    };
    const cancel = () => {
      cleanup();
      layerManager.close(guestConfirmModal);
      onCancel?.();
    };
    const accept = async () => {
      guestConfirmAccept.disabled = true;
      guestConfirmCancel.disabled = true;
      const completed = await onAccept?.(guestConfirmAccept);
      if (completed === false) {
        guestConfirmAccept.disabled = false;
        guestConfirmCancel.disabled = false;
        return;
      }
      cleanup();
    };
    guestConfirmAccept.addEventListener('click', accept);
    guestConfirmCancel.addEventListener('click', cancel);
    layerManager.open(guestConfirmModal);
  }

  function loadGuestOrderIntoCart(order) {
    cart.clear();
    (order.items || []).forEach((line) => {
      const id = Number(line.id);
      const current = items.get(id);
      // Keep the order snapshot even when today's menu is paused/unavailable. That lets
      // the guest reduce or cancel without pretending the old line is newly orderable.
      const item = {
        ...(current || {}),
        id,
        name: String(line.name || current?.name || ''),
        price: Number(line.unit_price || current?.price || 0),
        snapshot_only: !current || !canOrderItem(current),
        snapshot_from_order: true,
      };
      const quantity = Math.max(1, Math.min(20, Number(line.quantity) || 1));
      const existing = cart.get(id);
      if (existing) {
        existing.quantity = Math.min(20, Number(existing.quantity || 0) + quantity);
        if (String(line.fulfillment_mode || '') === 'takeaway') existing.takeaway_quantity = Math.min(existing.quantity, Number(existing.takeaway_quantity || 0) + quantity);
        const incomingNote = String(line.note || '').trim();
        if (incomingNote && incomingNote !== String(existing.note || '').trim()) existing.note = [existing.note, incomingNote].filter(Boolean).join('؛ ');
        clampTakeaway(existing);
      } else {
        cart.set(id, {
          item, quantity, note: String(line.note || ''),
          takeaway_quantity: String(line.fulfillment_mode || '') === 'takeaway' ? quantity : 0,
            });
      }
    });
    if (customerNote) customerNote.value = String(order.customer_note || '');
    takeawayDraft = null;
    editConflict = null;
    editingOrder = order;
    editingOrderCode = String(order.order_code || '');
    editingOrderSignature = String(order.edit_signature || '');
    editingOrderNeedsReconcile = false;
    pendingToken = '';
    pendingSignature = '';
    render();
    updateSubmitMode();
    layerManager.open(drawer);
    return true;
  }

  function startEditingGuestOrder(code) {
    const order = guestOrders.find((entry) => entry.order_code === code && entry.can_edit);
    if (!order) return;
    if (!cart.size || editingOrderCode === code) {
      loadGuestOrderIntoCart(order);
      return;
    }
    openGuestConfirmation({
      title: `ویرایش ${orderNumberLabel(order.order_number)}`,
      text: 'اقلام فعلی سبد حذف می‌شوند و اقلام این سفارش برای ویرایش جایگزین خواهند شد. ادامه می‌دهید؟',
      acceptLabel: 'ادامه ویرایش',
      cancelLabel: 'انصراف',
      onAccept: () => { layerManager.close(guestConfirmModal); loadGuestOrderIntoCart(order); return true; },
      onCancel: () => layerManager.open(guestOrdersModal),
    });
  }

  function requestExitGuestEdit({ returnToOrders = false } = {}) {
    if (!editingOrderCode) return;
    openGuestConfirmation({
      title: 'خروج از ویرایش سفارش؟',
      text: 'تغییرات ذخیره‌نشده حذف می‌شوند؛ خود سفارش ثبت‌شده دست‌نخورده می‌ماند.',
      acceptLabel: returnToOrders ? 'خروج و مشاهده سفارش‌ها' : 'خروج از ویرایش',
      cancelLabel: 'ادامه ویرایش',
      onCancel: () => layerManager.open(drawer),
      onAccept: () => {
        layerManager.close(guestConfirmModal);
        clearEditingContext({ clearCart: true });
        render();
        saveState();
        if (returnToOrders) openGuestOrders();
        return true;
      },
    });
  }

  function cancelGuestOrder(code) {
    const order = guestOrders.find((entry) => entry.order_code === code && entry.can_cancel);
    if (!order) return;
    openGuestConfirmation({
      title: `لغو ${orderNumberLabel(order.order_number)}`,
      text: 'این سفارش لغو می‌شود و امکان بازگرداندن آن وجود ندارد.',
      acceptLabel: 'لغو سفارش',
      cancelLabel: 'بازگشت',
      danger: true,
      onCancel: () => layerManager.open(guestOrdersModal),
      onAccept: async (button) => {
        button.textContent = 'در حال لغو…';
        try {
          const response = await fetch(window.CAFE_GUEST_ORDERS_API_URL, {
            method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, cache: 'no-store',
            body: JSON.stringify({ action: 'cancel', order_code: code, table_token: table.token, session_token: session?.token || '', device_token: deviceToken, csrf_token: csrf }),
          });
          const data = await response.json().catch(() => ({}));
          window.SoknaPushRuntime?.handleResponse?.(data);
          if (!response.ok || !data.success) throw new Error(data.message || 'لغو سفارش انجام نشد.');
          if (editingOrder?.order_code === code || editingOrderCode === code) clearEditingContext({ clearCart: true });
          layerManager.close(guestConfirmModal);
          showToast('سفارش لغو شد.');
          await refreshGuestOrders({ silent: true });
          layerManager.open(guestOrdersModal);
          return true;
        } catch (error) {
          button.textContent = 'لغو سفارش';
          showToast(window.SoknaGuestUI?.requestErrorMessage?.(error, 'لغو سفارش انجام نشد.') || 'لغو سفارش انجام نشد.', 'error');
          return false;
        }
      },
    });
  }

  function statusMessage(status) {
    const messagesByStatus = {
      pending_approval: ['status_pending_approval', 'سفارش ثبت شد و منتظر تأیید کافه است.'],
      new: ['status_new', 'سفارش ثبت شد و منتظر تأیید کافه است.'],
      accounted: ['status_accounted', 'سفارش تأیید و به حساب میز اضافه شد.'],
      completed: ['status_completed', 'حساب میز تسویه و بسته شد.'],
      cancelled: ['status_cancelled', 'سفارش لغو شد.'],
    };
    const entry = messagesByStatus[status];
    return entry ? msg(entry[0], entry[1]) : 'در حال دریافت وضعیت سفارش…';
  }

  function orderNumberLabel(number) {
    const value = Number(number || 0);
    return value > 0 ? `سفارش ${new Intl.NumberFormat('fa-IR').format(value)}` : 'سفارش ثبت‌شده';
  }

  function orderStatusShortLabel(status) {
    const labels = {
      pending_approval: 'منتظر تأیید کافه',
      new: 'منتظر تأیید کافه',
      accounted: 'تأییدشده',
      completed: 'تسویه‌شده',
      cancelled: 'لغوشده',
    };
    return labels[String(status || '')] || 'در حال بررسی';
  }

  function applyOrderProgress(status) {
    const normalizedStatus = String(status || 'new');
    if (recentBar) recentBar.dataset.status = normalizedStatus;
    if (successProgress) successProgress.dataset.status = normalizedStatus;
    statusBox?.classList.toggle('is-confirmed', ['accounted', 'completed'].includes(normalizedStatus));
    statusBox?.classList.toggle('is-cancelled', normalizedStatus === 'cancelled');
  }

  function showRecent(orderNumber, text, status = 'new') {
    if (!recentBar) return;
    if (recentOrderBadge) recentOrderBadge.textContent = new Intl.NumberFormat('fa-IR').format(Math.max(1, guestOrders.filter((order) => order.status !== 'cancelled').length));
    applyOrderProgress(status);
    recentBar.classList.remove('hidden');
  }

  function stopTracking() {
    clearTimeout(trackingTimer);
    trackingTimer = null;
  }

  function clearClosedSessionState() {
    stopTracking();
    currentTracking = null;
    guestOrders = [];
    editingOrder = null;
    editingOrderCode = '';
    pendingToken = '';
    pendingSignature = '';
    cart.clear();
    if (customerNote) customerNote.value = '';
    recentBar?.classList.add('hidden');
    successModal?.classList.add('hidden');
    try {
      localStorage.removeItem(storageKey);
      localStorage.removeItem(`${storageKey}:last-order`);
      localStorage.removeItem(`${storageKey}:waiter`);
    } catch (_) {}
    waiterState = null;
    render();
  }

  async function trackOrder(orderCode, clientToken, orderNumber = 0) {
    stopTracking();
    currentTracking = { orderCode, clientToken, orderNumber: Number(orderNumber || 0) };
    let failures = 0;
    showRecent(orderNumber, 'داریم بررسی می‌کنیم…', recentBar?.dataset.status || 'new');

    const poll = async () => {
      if (!navigator.onLine) {
        trackingTimer = setTimeout(poll, 12000);
        return;
      }
      try {
        const response = await fetch(window.CAFE_STATUS_API_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          cache: 'no-store',
          body: JSON.stringify({ order_code: orderCode, client_token: clientToken, csrf_token: csrf }),
        });
        const data = await response.json();
        window.SoknaPushRuntime?.handleResponse?.(data);
        if (!response.ok || !data.success) throw new Error();
        failures = 0;
        if (data.expired || data.status === 'session_closed') {
          clearClosedSessionState();
          return;
        }
        const text = statusMessage(data.status);
        if (statusText) statusText.textContent = text;
        showRecent(orderNumber, text, data.status);
        refreshGuestOrders({ silent: true });
        if (!['accounted', 'completed', 'cancelled'].includes(data.status)) {
          const delay = document.hidden ? 15000 : (data.status === 'new' ? 4000 : 7000);
          trackingTimer = setTimeout(poll, delay);
        }
      } catch (_) {
        failures += 1;
        const text = msg('tracking_unavailable', 'فعلاً نمی‌تونیم وضعیت رو تازه کنیم؛ سفارشت قبلاً ارسال شده.');
        if (statusText) statusText.textContent = text;
        showRecent(orderNumber, text, recentBar?.dataset.status || 'new');
        if (failures < 8) trackingTimer = setTimeout(poll, Math.min(30000, 8000 * failures));
      }
    };
    poll();
  }

  document.getElementById('successClose')?.addEventListener('click', () => layerManager.close(successModal));
  document.getElementById('openRecentOrder')?.addEventListener('click', openGuestOrders);
  document.getElementById('guestOrdersClose')?.addEventListener('click', () => layerManager.close(guestOrdersModal));
  document.getElementById('guestOrdersDone')?.addEventListener('click', () => layerManager.close(guestOrdersModal));
  guestOrdersList?.addEventListener('click', (event) => {
    const edit = event.target.closest('[data-edit-guest-order]');
    if (edit) startEditingGuestOrder(edit.dataset.editGuestOrder || '');
    const cancel = event.target.closest('[data-cancel-guest-order]');
    if (cancel) cancelGuestOrder(cancel.dataset.cancelGuestOrder || '');
  });

  function mergeDraftIntoMutableOrder(editableOrder, draftOrder) {
    const merged = new Map();
    const keyOf = (line) => `${Number(line.id)}|${String(line.fulfillment_mode || 'dine_in') === 'takeaway' ? 'takeaway' : 'dine_in'}`;
    (editableOrder.items || []).forEach((line) => merged.set(keyOf(line), {
      id: Number(line.id), quantity: Number(line.quantity || 0), note: String(line.note || ''), unit_price: Number(line.unit_price || 0),
      fulfillment_mode: String(line.fulfillment_mode || '') === 'takeaway' ? 'takeaway' : 'dine_in',
    }));
    draftOrder.items.forEach((line) => {
      const key = keyOf(line);
      const current = merged.get(key);
      if (!current) { merged.set(key, {...line}); return; }
      const next = Number(current.quantity || 0) + Number(line.quantity || 0);
      if (next > 20) throw Object.assign(new Error('تعداد یک آیتم با یک نوع سرو در سفارش در انتظار نمی‌تواند بیشتر از ۲۰ باشد.'), { code: 'invalid_order' });
      current.quantity = next;
      const incomingNote = String(line.note || '').trim();
      if (incomingNote && incomingNote !== String(current.note || '').trim()) current.note = [current.note, incomingNote].filter(Boolean).join('؛ ');
    });
    const existingNote = String(editableOrder.customer_note || '').trim();
    const incomingNote = String(draftOrder.note || '').trim();
    return {
      note: incomingNote && incomingNote !== existingNote ? [existingNote, incomingNote].filter(Boolean).join('؛ ') : existingNote,
      items: [...merged.values()].filter((line) => Number(line.quantity || 0) > 0),
    };
  }


  async function submitOrder(options = {}) {
    const retryCount = Math.max(0, Number(options.retryCount || 0));
    if (submitting || editConflict || editingOrderNeedsReconcile || !cart.size || !table || (!canOrder && !editingOrder)) return;
    if (editingOrderCode && !editingOrder) {
      setEditConflict('locked', guestOrders.find((entry) => entry.order_code === editingOrderCode) || null);
      render();
      return;
    }
    if (pendingTableConfirmation) {
      requestTableConfirmation(() => submitOrder(options));
      return;
    }

    submitting = true;
    submit.disabled = true;
    if (submitText) submitText.textContent = msg('sending_order', 'داریم سفارشت رو می‌فرستیم…');
    else submit.textContent = msg('sending_order', 'داریم سفارشت رو می‌فرستیم…');
    const order = {
      note: customerNote?.value.trim() || '',
      items: orderPayloadLines(),
    };
    const signature = JSON.stringify(order);
    if (!pendingToken || pendingSignature !== signature) {
      pendingToken = uuid();
      pendingSignature = signature;
    }
    saveState();

    let submittedMode = 'create';
    let submittedTarget = null;
    let retrySubmission = false;

    try {
      const explicitOrder = editingOrderCode ? editingOrder : null;
      const appendTarget = explicitOrder ? null : mutableAppendTarget();
      submittedMode = explicitOrder ? 'edit' : appendTarget ? 'append' : 'create';
      submittedTarget = explicitOrder || appendTarget;
      const usesEditableApi = Boolean(submittedTarget && window.CAFE_GUEST_ORDERS_API_URL);
      const endpoint = usesEditableApi ? window.CAFE_GUEST_ORDERS_API_URL : window.CAFE_API_URL;
      const finalOrder = submittedMode === 'edit'
        ? order
        : submittedMode === 'append' ? mergeDraftIntoMutableOrder(submittedTarget, order) : order;
      const expectedSignature = submittedMode === 'edit'
        ? String(editingOrderSignature || submittedTarget?.edit_signature || '')
        : String(submittedTarget?.edit_signature || '');
      const body = usesEditableApi ? {
        action: 'update',
        order_code: submittedTarget.order_code,
        expected_signature: expectedSignature,
        table_token: table.token,
        session_token: session?.token || '',
        device_token: deviceToken,
        customer_note: finalOrder.note,
        items: finalOrder.items,
        csrf_token: csrf,
      } : {
        table_token: table.token,
        session_token: session?.token || '',
        device_token: deviceToken,
        client_token: pendingToken,
        customer_note: order.note,
        items: order.items,
        csrf_token: csrf,
      };
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        cache: 'no-store',
        body: JSON.stringify(body),
      });
      const data = await response.json().catch(() => ({}));
      window.SoknaPushRuntime?.handleResponse?.(data);
      if (!response.ok || !data.success) {
        throw Object.assign(new Error(data.message || msg('order_failed', 'سفارشت ثبت نشد؛ دوباره امتحان کن.')), { code: data.code });
      }

      metric('order_submit', 0);
      const trackingToken = String(data.client_token || submittedTarget?.client_token || pendingToken);
      clearEditingContext();
      pendingToken = '';
      pendingSignature = '';
      cart.clear();
      if (customerNote) customerNote.value = '';
      render();

      const successTitle = document.getElementById('successTitle');
      if (successTitle) successTitle.textContent = msg('order_received_title', 'سفارشت رسید 👌');
      if (successOrderCode) successOrderCode.textContent = orderNumberLabel(data.order_number);
      if (statusText) statusText.textContent = data.duplicate ? msg('duplicate_order', 'این سفارش قبلاً ثبت شده؛ دوباره نفرستادیم.') : statusMessage(data.status || 'new');
      applyOrderProgress(data.status || 'new');
      layerManager.open(successModal);

      try {
        localStorage.setItem(`${storageKey}:last-order`, JSON.stringify({
          orderCode: data.order_code,
          orderNumber: Number(data.order_number || 0),
          clientToken: trackingToken,
          createdAt: Date.now(),
        }));
      } catch (_) {}
      await refreshGuestOrders({ silent: true });
      showRecent(data.order_number, statusMessage(data.status || 'new'), data.status || 'new');
      trackOrder(data.order_code, trackingToken, data.order_number);
    } catch (error) {
      const refreshCodes = ['order_changed','order_not_editable','order_not_found'];
      if (submittedMode === 'edit' && refreshCodes.includes(String(error.code || ''))) {
        await refreshGuestOrders({ silent: true });
        if (!editConflict) {
          const latest = guestOrders.find((entry) => entry.order_code === editingOrderCode) || null;
          setEditConflict(latest?.can_edit ? 'changed' : 'locked', latest);
        }
        render();
      } else if (submittedMode === 'append' && refreshCodes.includes(String(error.code || ''))) {
        await refreshGuestOrders({ silent: true });
        if (retryCount < 1) retrySubmission = true;
        else showToast('وضعیت سفارش هم‌زمان تغییر کرد؛ دوباره ثبت را بزنید.', 'warning');
      } else if (error.code === 'pending_order_exists' && submittedMode === 'create') {
        await refreshGuestOrders({ silent: true });
        if (retryCount < 1 && mutableAppendTarget()) retrySubmission = true;
        else showToast(window.SoknaGuestUI?.requestErrorMessage?.(error, 'سفارش در انتظار پیدا نشد؛ دوباره امتحان کن.') || 'سفارش در انتظار پیدا نشد؛ دوباره امتحان کن.', 'error');
      } else {
        showToast(window.SoknaGuestUI?.requestErrorMessage?.(error, msg('order_failed', 'سفارشت ثبت نشد؛ دوباره امتحان کن.')) || msg('order_failed', 'سفارشت ثبت نشد؛ دوباره امتحان کن.'), 'error');
      }
      if (error.code === 'ordering_paused') {
        orderingEnabled = false;
        canOrder = false;
        setOrderingState();
      }
      if (error.code === 'session_inactive') {
        canOrder = false;
        setOrderingState();
        closeDrawer();
      }
      if (error.code === 'prices_changed' || error.code === 'items_unavailable') {
        saveState();
        setTimeout(() => location.reload(), 1200);
      }
    } finally {
      submitting = false;
      submit.disabled = Boolean(editConflict || editingOrderNeedsReconcile) || (!canOrder && !editingOrder);
      updateSubmitMode();
      if (!submitText && submit) submit.textContent = msg('submit_order', 'فرستادن سفارش');
      saveState();
      if (retrySubmission) setTimeout(() => submitOrder({ retryCount: retryCount + 1 }), 0);
    }
  }

  guestTakeawayDisclosure?.addEventListener('click',(event)=>openGuestTakeawaySheet(event.currentTarget));
  guestTakeawayClose?.addEventListener('click',()=>closeGuestTakeawaySheet(false));
  guestTakeawayCancel?.addEventListener('click',()=>closeGuestTakeawaySheet(false));
  guestTakeawayBackdrop?.addEventListener('click',()=>closeGuestTakeawaySheet(false));
  guestTakeawayConfirm?.addEventListener('click',()=>closeGuestTakeawaySheet(true));
  guestTakeawayAll?.addEventListener('click',()=>{if(!takeawayDraft)return;let eligibleTotal=0,draftTake=0;for(const line of cart.values()){if(!fulfillment.allowed(line.item))continue;const q=Number(line.quantity||0);eligibleTotal+=q;draftTake+=Math.max(0,Math.min(q,Number(takeawayDraft.get(Number(line.item.id))||0)));}const makeAll=!(eligibleTotal>0&&draftTake===eligibleTotal);for(const line of cart.values())if(fulfillment.allowed(line.item))takeawayDraft.set(Number(line.item.id),makeAll?Number(line.quantity||0):0);renderGuestTakeawaySheet();});
  guestTakeawayList?.addEventListener('click',(event)=>{if(!takeawayDraft)return;const delta=event.target.closest('[data-guest-takeaway-delta]');if(delta){const id=Number(delta.dataset.id),line=cart.get(id);if(!line||!fulfillment.allowed(line.item))return;const q=Number(line.quantity||0),next=Math.max(0,Math.min(q,Number(takeawayDraft.get(id)||0)+Number(delta.dataset.guestTakeawayDelta||0)));takeawayDraft.set(id,next);renderGuestTakeawaySheet();return;}const toggle=event.target.closest('[data-guest-takeaway-toggle]');if(toggle){const id=Number(toggle.dataset.guestTakeawayToggle),line=cart.get(id);if(line&&fulfillment.allowed(line.item))takeawayDraft.set(id,Number(takeawayDraft.get(id)||0)>0?0:1);renderGuestTakeawaySheet();}});
  document.addEventListener('keydown',(event)=>{if(!guestTakeawaySheetIsOpen()||!guestTakeawaySheet)return;if(event.key==='Escape'){event.preventDefault();event.stopImmediatePropagation();closeGuestTakeawaySheet(false);return;}if(event.key!=='Tab')return;const focusables=[...guestTakeawaySheet.querySelectorAll('button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex]:not([tabindex="-1"])')].filter(el=>el.offsetParent!==null);if(!focusables.length)return;const first=focusables[0],last=focusables[focusables.length-1],active=document.activeElement;if(!guestTakeawaySheet.contains(active)){event.preventDefault();first.focus({preventScroll:true});return;}if(event.shiftKey&&active===first){event.preventDefault();last.focus({preventScroll:true});}else if(!event.shiftKey&&active===last){event.preventDefault();first.focus({preventScroll:true});}},true);
  window.addEventListener('popstate',(event)=>{if(!guestTakeawaySheetIsOpen())return;try{history.pushState({...(history.state||{}),cafeLayer:true,cafeLayerClosed:false},'',location.href);}catch(_){}closeGuestTakeawaySheet(false);event.stopImmediatePropagation();},true);
  window.SoknaGuestSheet?.bindSwipeDismiss?.({layer:guestTakeawayLayer,panel:guestTakeawaySheet,handle:guestTakeawaySheet?.querySelector('header')||guestTakeawaySheet,close:()=>closeGuestTakeawaySheet(false)});
  guestEditExit?.addEventListener('click', () => requestExitGuestEdit());
  guestOrderConflictPrimary?.addEventListener('click', () => {
    if (!editConflict) return;
    if (editConflict.kind === 'changed' && editConflict.latest?.can_edit) {
      const latest = editConflict.latest;
      editConflict = null;
      loadGuestOrderIntoCart(latest);
      return;
    }
    openGuestOrders();
  });
  guestOrderConflictSecondary?.addEventListener('click', () => requestExitGuestEdit());
  submit?.addEventListener('click', submitOrder);

  function setOrderingState() {
    document.body.classList.toggle('ordering-disabled', !canOrder);
    if (submit) submit.disabled = Boolean(editConflict || editingOrderNeedsReconcile) || (!canOrder && !editingOrder);
    if (!tableNotice) return;
    if (!orderingEnabled) {
      tableNotice.textContent = msg('ordering_pause_cafe', 'پذیرش سفارش آنلاین موقتاً متوقف است. منو و قیمت‌ها همچنان قابل مشاهده‌اند.');
      tableNotice.className = 'guest-notice is-waiting';
    } else if (!canOrder && table && window.CAFE_SESSIONS_ENABLED && !session) {
      tableNotice.textContent = msg('table_inactive', 'این میز هنوز برای سفارش فعال نشده؛ یه لحظه دیگه دوباره امتحان کن.');
      tableNotice.className = 'guest-notice is-waiting';
    } else if (canOrder) {
      tableNotice.textContent = '';
      tableNotice.className = 'guest-notice hidden';
    }
  }

  const search = document.getElementById('menuSearch');
  const searchZone = document.getElementById('guestSearchZone');
  const searchPanel = document.getElementById('searchPanel');
  const searchOpen = document.getElementById('searchOpen');
  const searchClose = document.getElementById('searchClose');
  const searchPresets = document.getElementById('searchPresets');
  const searchResults = document.getElementById('searchResults');
  const searchResultList = document.getElementById('searchResultList');
  const searchResultsCount = document.getElementById('searchResultsCount');
  const searchClear = document.getElementById('searchClear');

  function searchResultCard(item) {
    const quantity = cart.get(Number(item.id))?.quantity || 0;
    const media = item.image
      ? `<img src="${esc(item.image)}" loading="lazy" decoding="async" alt="">`
      : icon('coffee');
    const physicallyAvailable = Number(item.available) === 1;
    const orderAvailable = Boolean(table) && canOrderItem(item);
    const servicePaused = Boolean(table) && physicallyAvailable && !orderAvailable;
    const state = !physicallyAvailable
      ? `<span class="search-result-state">${esc(msg('item_unavailable', 'فعلاً موجود نیست'))}</span>`
      : (servicePaused
        ? `<span class="search-result-state">${esc(itemUnavailableLabel(item))}</span>`
        : (Boolean(table) && Number(item.station_busy) ? `<span class="search-result-state">${esc(msg('station_busy_badge', 'آماده‌سازی با کمی تأخیر'))}</span>` : ''));
    const controls = !orderAvailable
      ? ''
      : `<div class="inline-qty${quantity > 0 ? ' has-items' : ''}" data-inline-qty="${Number(item.id)}"><button type="button" data-inline-dec="${Number(item.id)}" aria-label="کم کردن ${esc(item.name)}">${icon('minus')}</button><span data-inline-count="${Number(item.id)}">${new Intl.NumberFormat('fa-IR').format(quantity)}</span><button class="inline-add" type="button" data-add-item="${Number(item.id)}" aria-label="افزودن ${esc(item.name)}">${icon('plus')}<span class="inline-add-label">افزودن</span></button></div>`;
    return `<article class="search-result-card${physicallyAvailable ? '' : ' unavailable'}${servicePaused ? ' service-unavailable' : ''}${quantity > 0 ? ' is-selected' : ''}" data-menu-item data-item-id="${Number(item.id)}"><button class="menu-item-hit" type="button" data-open-item-detail="${Number(item.id)}" aria-label="دیدن جزئیات ${esc(item.name)}"></button><div class="search-result-media">${media}</div><div class="search-result-copy"><strong>${esc(item.name)}</strong><small>${esc(item.category || item.description || '')}</small><b>${money(item.price)}</b>${state}</div>${controls}</article>`;
  }

  function openSearch() {
    if (!searchPanel) return;
    searchPanel.classList.remove('hidden');
    searchZone?.classList.add('search-engaged');
    document.body.classList.add('search-panel-open');
    searchOpen?.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(() => search?.focus());
  }

  function renderSearch(query) {
    const normalizedQuery = normalize(query);
    const active = normalizedQuery.length > 0;
    document.body.classList.toggle('is-searching', active);
    searchClear?.classList.toggle('hidden', !active);
    searchPresets?.classList.toggle('hidden', active);
    searchResults?.classList.toggle('hidden', !active);
    if (!active || !searchResultList) return;

    const searchTokens = normalizedQuery.split(' ').filter(Boolean);
    const matches = [...items.values()].filter((item) => {
      const haystack = normalize(item.search_text || `${item.name} ${item.category || ''}`);
      return searchTokens.every((token) => haystack.includes(token));
    });
    if (searchResultsCount) searchResultsCount.textContent = matches.length > 30
      ? `۳۰ مورد اول از ${new Intl.NumberFormat('fa-IR').format(matches.length)} نتیجه · عبارت دقیق‌تری وارد کنید`
      : `${new Intl.NumberFormat('fa-IR').format(matches.length)} مورد پیدا شد`;
    if (matches.length) {
      searchResultList.innerHTML = matches.slice(0, 30).map(searchResultCard).join('');
    } else {
      const alternatives = [...items.values()].filter(canOrderItem).slice(0, 3);
      searchResultList.innerHTML = `<div class="search-result-empty">${icon('search')}<strong>${esc(msg('no_results', 'چیزی با این عبارت پیدا نکردیم.'))}</strong></div>${alternatives.map(searchResultCard).join('')}`;
    }
    setInlineCounts();
    clearTimeout(searchMetricTimer);
    const cleanQuery = String(query || '').trim().replace(/\s+/g, ' ').slice(0, 80);
    const recordKey = `${normalize(cleanQuery)}:${matches.length}`;
    if (cleanQuery.length >= 2 && recordKey !== lastRecordedSearch) {
      searchMetricTimer = setTimeout(() => {
        lastRecordedSearch = recordKey;
        metric('search_query', 0, { search_term: cleanQuery, result_count: matches.length });
        if (matches.length === 0) metric('search_no_result', 0);
      }, 900);
    }
  }

  function clearSearch({ focus = true } = {}) {
    if (!search) return;
    search.value = '';
    renderSearch('');
    if (focus) search.focus();
  }

  function closeSearch() {
    clearSearch({ focus: false });
    searchZone?.classList.remove('search-engaged');
    document.body.classList.remove('search-panel-open');
    searchOpen?.setAttribute('aria-expanded', 'false');
    searchOpen?.focus();
  }

  searchOpen?.addEventListener('click', openSearch);
  searchClose?.addEventListener('click', closeSearch);
  search?.addEventListener('focus', openSearch);
  search?.addEventListener('input', () => renderSearch(search.value));
  document.querySelectorAll('[data-search-preset]').forEach((button) => button.addEventListener('click', () => {
    if (!search) return;
    openSearch();
    search.value = button.dataset.searchPreset || '';
    renderSearch(search.value);
    search.focus();
  }));
  searchClear?.addEventListener('click', () => clearSearch());
  document.getElementById('searchBackToMenu')?.addEventListener('click', closeSearch);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && searchZone?.classList.contains('search-engaged') && !layerManager.currentLayer()) closeSearch();
  });

  window.SoknaCategoryNavigation?.install();

  window.SoknaHorizontalRail?.installAll(document);


  const guestHeader = document.getElementById('guestHeader');
  if ('IntersectionObserver' in window && guestHeader) {
    const headerObserver = new IntersectionObserver(([entry]) => {
      document.body.classList.toggle('guest-scrolled', !entry.isIntersecting);
    }, { threshold: 0.02 });
    headerObserver.observe(guestHeader);
  } else {
    const updateScrolledState = () => document.body.classList.toggle('guest-scrolled', window.scrollY > 90);
    window.addEventListener('scroll', updateScrolledState, { passive: true });
    updateScrolledState();
  }

  document.querySelectorAll('[data-event-date]').forEach((element) => {
    const date = new Date(element.dataset.eventDate);
    if (!Number.isNaN(date.getTime())) {
      element.textContent = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        weekday: 'long', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit',
      }).format(date);
    }
  });

  function promptSessionConfirmation(sessionToken, tableName) {
    const key = `cafe-session-confirm:${sessionToken}:${deviceToken}`;
    try {
      if (localStorage.getItem(key) === '1') return;
    } catch (_) {}
    setPendingTableConfirmation({
      text: `برای اینکه سفارش به میز درست برسه، فقط تأیید کن الان روی ${textFaDigits(tableName)} نشستی.`,
      yesLabel: 'بله، همین میز',
      noLabel: 'QR میز فعلی',
      onConfirm: () => {
        try { localStorage.setItem(key, '1'); } catch (_) {}
      },
      onReject: () => {
        canOrder = false;
        setOrderingState();
        showToast('QR میز فعلیت رو اسکن کن تا سفارش به جای درست برسه.', 'warning');
      },
    });
  }

  function scheduleContextRefresh(delay) {
    clearTimeout(contextTimer);
    contextTimer = setTimeout(refreshContext, delay);
  }

  async function refreshContext() {
    if (!table) return;
    if (!navigator.onLine) {
      scheduleContextRefresh(20000);
      return;
    }
    try {
      const response = await fetch(window.CAFE_CONTEXT_API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        cache: 'no-store',
        body: JSON.stringify({ table_token: table.token, device_token: deviceToken, csrf_token: csrf }),
      });
      const data = await response.json();
      window.SoknaPushRuntime?.handleResponse?.(data);
      if (!response.ok || !data.success) throw new Error();
      if (data.session?.token && session?.token && data.session.token !== session.token) {
        location.reload();
        return;
      }
      if (data.session?.token && !session) {
        location.reload();
        return;
      }
      if (data.order_acceptance) applyOrderAcceptance(data.order_acceptance);
      requiresOperatorConfirmation = Boolean(data.requires_operator_confirmation);
      if (data.station_state_hash && stationStateHash && data.station_state_hash !== stationStateHash) {
        saveState();
        location.reload();
        return;
      }
      stationStateHash = String(data.station_state_hash || stationStateHash);
      if (!data.session && session) {
        session = null;
        clearClosedSessionState();
        canOrder = Boolean(data.can_order);
        setOrderingState();
      } else {
        canOrder = Boolean(data.can_order);
        setOrderingState();
      }
      waiterButton?.classList.toggle('hidden', !data.waiter_enabled && !data.active_call);
      if (data.active_call) {
        const owned = Boolean(waiterState?.owned && waiterState?.code === data.active_call.public_code);
        waiterState = {
          code: data.active_call.public_code,
          status: data.active_call.status,
          owned,
          createdAt: waiterState?.createdAt || Date.now(),
        };
        applyWaiterStatus(data.active_call.status, data.active_call.public_code);
      }
      if (data.late_join && data.session?.token) promptSessionConfirmation(data.session.token, data.table?.name || table.name);
    } catch (_) {}
    scheduleContextRefresh(document.hidden ? 30000 : 12000);
  }

  const waiterButton = document.getElementById('waiterButton');
  const waiterButtonText = document.getElementById('waiterButtonText');
  const confirmWaiter = document.getElementById('confirmWaiter');
  const closeWaiter = document.getElementById('closeWaiter');
  const waiterModalText = document.getElementById('waiterModalText');
  const publicWaiterTable = document.getElementById('publicWaiterTable');
  const publicTableGrid = document.getElementById('publicTableGrid');
  const publicTableSelection = document.getElementById('publicTableSelection');
  const publicTableSelectWrap = document.getElementById('publicTableSelectWrap');
  const publicTableQuick = document.getElementById('publicTableQuick');
  const publicTableQuickName = document.getElementById('publicTableQuickName');
  const publicWaiterTables = Array.isArray(window.CAFE_PUBLIC_WAITER_TABLES) ? [...window.CAFE_PUBLIC_WAITER_TABLES] : [];
  const publicSelectionKey = 'cafe-public-waiter-table-v1';
  const waiterStorageKey = table ? `${storageKey}:waiter` : `cafe-public-waiter-v1:${deviceToken}`;

  const publicTableNamePart = (row) => String(row?.name || '').replace(/^میز\s*/u, '').trim();
  const publicTableNumber = (row) => {
    const direct = Number(row?.table_number || 0);
    if (direct > 0) return direct;
    const match = normalize(publicTableNamePart(row)).match(/(?:^|\s)(\d+)(?:\s|$)/);
    return match ? Number(match[1]) : 0;
  };
  const publicTableDisplay = (row) => {
    const number = publicTableNumber(row);
    return number > 0 ? new Intl.NumberFormat('fa-IR', { useGrouping: false }).format(number) : (publicTableNamePart(row) || 'بدون شماره');
  };
  const publicTableSort = (a, b) => {
    const an = publicTableNumber(a), bn = publicTableNumber(b);
    if (an && bn && an !== bn) return an - bn;
    if (an !== bn) return an ? -1 : 1;
    const aso = Number(a?.sort_order || 0), bso = Number(b?.sort_order || 0);
    if (aso !== bso) return aso - bso;
    return String(a?.name || '').localeCompare(String(b?.name || ''), 'fa');
  };
  const selectedPublicTable = () => publicWaiterTables.find((row) => Number(row.id) === Number(publicWaiterTable?.value || 0)) || null;
  function renderPublicWaiterTables() {
    if (!publicTableGrid) return;
    publicWaiterTables.sort(publicTableSort);
    publicTableGrid.innerHTML = publicWaiterTables.map((row) => {
      const selected = Number(row.id) === Number(publicWaiterTable?.value || 0);
      return `<button class="public-table-option${selected ? ' is-selected' : ''}" type="button" role="radio" aria-checked="${selected}" data-public-table="${Number(row.id)}"><small>میز</small><strong>${esc(publicTableDisplay(row))}</strong></button>`;
    }).join('');
  }
  function choosePublicWaiterTable(id, persist = true) {
    const row = publicWaiterTables.find((item) => Number(item.id) === Number(id));
    if (!row || !publicWaiterTable) return;
    publicWaiterTable.value = String(row.id);
    if (publicTableSelection) publicTableSelection.textContent = `میز ${publicTableDisplay(row)}`;
    if (publicTableQuickName) publicTableQuickName.textContent = `میز ${publicTableDisplay(row)}`;
    renderPublicWaiterTables();
    if (confirmWaiter && waiterAction === 'create') confirmWaiter.disabled = false;
    if (persist) {
      try { sessionStorage.setItem(publicSelectionKey, JSON.stringify({ id: row.id, savedAt: Date.now() })); } catch (_) {}
    }
  }
  function restorePublicWaiterTable() {
    if (table) return;
    try {
      const saved = JSON.parse(sessionStorage.getItem(publicSelectionKey) || 'null');
      if (saved && Date.now() - Number(saved.savedAt || 0) < 3 * 3600 * 1000) choosePublicWaiterTable(saved.id, false);
    } catch (_) {}
  }
  function revealSelectedPublicTable() {
    if (!publicTableGrid) return;
    const selected = publicTableGrid.querySelector('.public-table-option.is-selected');
    if (!selected) { publicTableGrid.scrollTop = 0; return; }
    const gridRect = publicTableGrid.getBoundingClientRect();
    const selectedRect = selected.getBoundingClientRect();
    const delta = selectedRect.top - gridRect.top - Math.max(0, (publicTableGrid.clientHeight - selectedRect.height) / 2);
    publicTableGrid.scrollTop = Math.max(0, publicTableGrid.scrollTop + delta);
  }
  publicTableGrid?.addEventListener('click', (event) => {
    const option = event.target.closest('[data-public-table]');
    if (option) choosePublicWaiterTable(option.dataset.publicTable);
  });
  document.getElementById('changePublicTable')?.addEventListener('click', () => {
    publicTableQuick?.classList.add('hidden');
    publicTableSelectWrap?.classList.remove('hidden');
    requestAnimationFrame(revealSelectedPublicTable);
  });
  restorePublicWaiterTable();
  renderPublicWaiterTables();

  function saveWaiter() {
    try {
      if (waiterState) localStorage.setItem(waiterStorageKey, JSON.stringify(waiterState));
      else localStorage.removeItem(waiterStorageKey);
    } catch (_) {}
  }

  function applyWaiterStatus(status, code = '') {
    if (!waiterButton) return;
    if (code) {
      waiterState = {
        ...(waiterState || {}), code, status,
        owned: Boolean(waiterState?.owned),
        createdAt: waiterState?.createdAt || Date.now(),
      };
    }
    if (status === 'new') {
      waiterButton.classList.add('is-active');
      waiterButtonText && (waiterButtonText.textContent = msg('waiter_button_sent', 'گارسون خبر شده'));
    } else if (status === 'accepted') {
      waiterButton.classList.add('is-active', 'is-accepted');
      waiterButtonText && (waiterButtonText.textContent = msg('waiter_button_accepted', 'همکارمون در راهه'));
    } else {
      waiterButton.classList.remove('is-active', 'is-accepted');
      waiterButtonText && (waiterButtonText.textContent = msg('waiter_button', 'فراخوان گارسون'));
      if (['done', 'cancelled', 'none'].includes(status)) waiterState = null;
    }
    saveWaiter();
  }

  function scheduleWaiterPoll(delay) {
    clearTimeout(waiterTimer);
    waiterTimer = setTimeout(pollWaiter, delay);
  }

  function waiterContextPayload(publicTableId = 0) {
    if (table) return { table_token: table.token, session_token: session?.token || '' };
    return { public_table_id: Number(publicTableId || waiterState?.publicTableId || selectedPublicTable()?.id || 0) };
  }

  async function pollWaiter() {
    clearTimeout(waiterTimer);
    if (!waiterState?.code) return;
    if (!table && !Number(waiterState.publicTableId || 0)) return;
    if (!navigator.onLine) {
      scheduleWaiterPoll(15000);
      return;
    }
    try {
      const response = await fetch(window.CAFE_WAITER_API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        cache: 'no-store',
        body: JSON.stringify({
          action: 'status',
          ...waiterContextPayload(waiterState.publicTableId),
          device_token: deviceToken,
          client_token: waiterState.clientToken || '',
          call_code: waiterState.code,
          csrf_token: csrf,
        }),
      });
      const data = await response.json();
      window.SoknaPushRuntime?.handleResponse?.(data);
      if (response.ok && data.success) {
        const previous = waiterState?.status;
        if (typeof data.owned === 'boolean') waiterState.owned = data.owned;
        applyWaiterStatus(data.status, data.call_code || waiterState?.code || '');
        if (data.status === 'done' && previous !== 'done' && messages.waiter_done_enabled !== false) showToast(msg('waiter_done', 'خوشحالیم که کنارت بودیم.'));
        if (data.status === 'cancelled' && previous !== 'cancelled' && messages.waiter_cancelled_enabled !== false) showToast(msg('waiter_cancelled', 'درخواست فراخوان بسته شد.'));
        if (['new', 'accepted'].includes(data.status)) scheduleWaiterPoll(document.hidden ? 15000 : 5000);
      }
    } catch (_) {
      scheduleWaiterPoll(12000);
    }
  }

  function openWaiterModal() {
    if (!waiterModal) return;
    const status = waiterState?.status;
    const selected = selectedPublicTable();
    if (status === 'new') {
      waiterModalText.textContent = msg('waiter_sent', 'درخواستت رسید؛ یکی از همکارامون میاد سر میزت.');
      if (waiterState?.owned) {
        confirmWaiter.textContent = 'لغو درخواست';
        confirmWaiter.classList.remove('hidden');
        confirmWaiter.disabled = false;
        waiterAction = 'cancel';
      } else {
        confirmWaiter.classList.add('hidden');
        waiterAction = 'none';
      }
    } else if (status === 'accepted') {
      waiterModalText.textContent = msg('waiter_accepted', 'دیدیمش؛ تا چند لحظه دیگه میایم پیشت.');
      confirmWaiter.classList.add('hidden');
      waiterAction = 'none';
    } else if (table) {
      waiterModalText.textContent = 'درخواست حضور گارسون برای همین میز فرستاده می‌شود.';
      confirmWaiter.textContent = 'آره، خبرش کن';
      confirmWaiter.classList.remove('hidden');
      confirmWaiter.disabled = false;
      waiterAction = 'create';
    } else {
      waiterModalText.textContent = msg('public_waiter_prompt', 'میزت را انتخاب کن تا گارسون بداند کجا بیاید.');
      confirmWaiter.textContent = msg('waiter_button', 'فراخوان گارسون');
      confirmWaiter.classList.remove('hidden');
      confirmWaiter.disabled = !selected;
      waiterAction = 'create';
      if (selected) {
        publicTableSelectWrap?.classList.add('hidden');
        publicTableQuick?.classList.remove('hidden');
        if (publicTableQuickName) publicTableQuickName.textContent = `میز ${publicTableDisplay(selected)}`;
      } else {
        publicTableQuick?.classList.add('hidden');
        publicTableSelectWrap?.classList.remove('hidden');
      }
    }
    layerManager.open(waiterModal);
  }

  waiterButton?.addEventListener('click', openWaiterModal);
  closeWaiter?.addEventListener('click', () => layerManager.close(waiterModal));
  confirmWaiter?.addEventListener('click', async () => {
    if (waiterAction === 'none') return;
    const selected = selectedPublicTable();
    if (!table && waiterAction === 'create' && !selected) {
      showToast('اول میزت را انتخاب کن.', 'warning');
      return;
    }
    confirmWaiter.disabled = true;
    if (waiterAction === 'cancel') {
      try {
        const response = await fetch(window.CAFE_WAITER_API_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          cache: 'no-store',
          body: JSON.stringify({
            action: 'cancel', ...waiterContextPayload(waiterState?.publicTableId),
            client_token: waiterState?.clientToken || '', call_code: waiterState?.code || '', csrf_token: csrf,
          }),
        });
        const data = await response.json().catch(() => ({}));
        window.SoknaPushRuntime?.handleResponse?.(data);
        if (!response.ok || !data.success || data.status !== 'cancelled') {
          throw new Error(data.message || msg('waiter_connection_error', 'دوباره امتحان کن.'));
        }
        applyWaiterStatus('cancelled');
        layerManager.close(waiterModal);
        showToast(msg('waiter_cancelled', 'درخواست فراخوان بسته شد.'));
      } catch (error) {
        showToast(window.SoknaGuestUI?.requestErrorMessage?.(error, msg('waiter_connection_error', 'دوباره امتحان کن.')) || msg('waiter_connection_error', 'دوباره امتحان کن.'), 'error');
      } finally {
        confirmWaiter.disabled = false;
      }
      return;
    }

    const clientToken = uuid();
    try {
      const publicTableId = Number(selected?.id || 0);
      const response = await fetch(window.CAFE_WAITER_API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        cache: 'no-store',
        body: JSON.stringify({
          action: 'create', ...waiterContextPayload(publicTableId),
          device_token: deviceToken, client_token: clientToken, csrf_token: csrf,
        }),
      });
      const data = await response.json();
      window.SoknaPushRuntime?.handleResponse?.(data);
      if (!response.ok || !data.success) throw new Error(data.message || msg('waiter_connection_error', 'دوباره امتحان کن.'));
      waiterState = {
        code: data.call_code,
        status: data.status,
        owned: Boolean(data.owned),
        clientToken,
        ...(table ? {} : { publicTableId }),
        createdAt: Date.now(),
      };
      applyWaiterStatus(data.status, data.call_code);
      layerManager.close(waiterModal);
      showToast(data.status === 'accepted' ? msg('waiter_accepted', 'همکارمون در راهه.') : msg('waiter_sent', 'درخواستت رسید.'));
      pollWaiter();
    } catch (error) {
      showToast(window.SoknaGuestUI?.requestErrorMessage?.(error) || 'دوباره تلاش کنید.', 'error');
    } finally {
      confirmWaiter.disabled = false;
    }
  });

  function handleTableChange() {
    if (!table) return;
    const key = 'cafe-last-table-v1';
    let previous = null;
    try { previous = JSON.parse(localStorage.getItem(key) || 'null'); } catch (_) {}
    const current = { token: table.token, name: table.name, url: location.href, seenAt: Date.now() };
    if (previous && previous.token !== table.token && Date.now() - Number(previous.seenAt || 0) < 6 * 3600 * 1000) {
      setPendingTableConfirmation({
        text: msg('table_changed_question', 'به نظر میاد میزت عوض شده. الان روی {table} نشستی؟', { table: textFaDigits(table.name) }),
        yesLabel: 'بله، همین میز',
        noLabel: 'QR میز فعلی',
        onConfirm: () => {
          try { localStorage.setItem(key, JSON.stringify(current)); } catch (_) {}
        },
        onReject: () => {
          if (previous.url) location.href = previous.url;
        },
      });
    } else {
      try { localStorage.setItem(key, JSON.stringify(current)); } catch (_) {}
    }
  }

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      if (table) { refreshContext(); refreshGuestOrders({ silent: true }); }
      if (currentTracking) trackOrder(currentTracking.orderCode, currentTracking.clientToken, currentTracking.orderNumber);
      if (waiterState?.code) pollWaiter();
    }
  });
  window.addEventListener('online', () => {
    if (table) { refreshContext(); refreshGuestOrders({ silent: true }); }
    if (waiterState?.code) pollWaiter();
  });

  try {
    const savedWaiter = JSON.parse(localStorage.getItem(waiterStorageKey) || 'null');
    if (savedWaiter && Date.now() - Number(savedWaiter.createdAt || 0) < 3 * 3600 * 1000) {
      waiterState = savedWaiter;
      if (!table && waiterState.publicTableId) choosePublicWaiterTable(waiterState.publicTableId, false);
      applyWaiterStatus(savedWaiter.status, savedWaiter.code);
      pollWaiter();
    } else if (savedWaiter) {
      localStorage.removeItem(waiterStorageKey);
    }
  } catch (_) {}

  metric('menu_view', 0);
  if (!table) return;

  restoreState();
  applyOrderAcceptance(orderAcceptance);
  try {
    const returnScroll = Number(sessionStorage.getItem(`${storageKey}:return-scroll`) || 0);
    if (returnScroll > 0) {
      sessionStorage.removeItem(`${storageKey}:return-scroll`);
      requestAnimationFrame(() => window.scrollTo({ top: returnScroll, behavior: 'auto' }));
    }
  } catch (_) {}
  setOrderingState();
  handleTableChange();
  if (table) { refreshContext(); refreshGuestOrders({ silent: true }); }

  try {
    const lastOrder = JSON.parse(localStorage.getItem(`${storageKey}:last-order`) || 'null');
    if (lastOrder && Date.now() - Number(lastOrder.createdAt || 0) < 3 * 3600 * 1000 && lastOrder.orderCode && lastOrder.clientToken) {
      showRecent(lastOrder.orderNumber, 'داریم بررسی می‌کنیم…');
      trackOrder(lastOrder.orderCode, lastOrder.clientToken, lastOrder.orderNumber);
    } else if (lastOrder) {
      localStorage.removeItem(`${storageKey}:last-order`);
    }
  } catch (_) {}
})();

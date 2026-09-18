(() => {
  'use strict';

  const interactiveSelector = 'button,a,input,textarea,select,summary,details,[contenteditable="true"]';

  function bindSwipeDismiss(options = {}) {
    const layer = options.layer;
    const panel = options.panel;
    const handle = options.handle || panel;
    const close = typeof options.close === 'function' ? options.close : () => {};
    const transformFor = typeof options.transformFor === 'function'
      ? options.transformFor
      : (y) => `translateY(${Math.max(0, y)}px)`;
    if (!layer || !panel || !handle || !window.PointerEvent) return null;

    let pointerId = null;
    let startY = 0;
    let startX = 0;
    let startAt = 0;
    let distance = 0;
    let cancelled = false;
    let resetTimer = 0;

    const resetVisual = () => {
      clearTimeout(resetTimer);
      panel.style.transition = 'transform 180ms ease';
      panel.style.transform = transformFor(0);
      resetTimer = window.setTimeout(() => {
        panel.style.removeProperty('transition');
        panel.style.removeProperty('transform');
      }, 190);
    };

    const releaseCapture = () => {
      if (pointerId === null) return;
      try { handle.releasePointerCapture(pointerId); } catch (_) {}
    };

    const finish = (event, forceCancel = false) => {
      if (pointerId === null || (event && event.pointerId !== pointerId)) return;
      const elapsed = Math.max(1, performance.now() - startAt);
      const velocity = distance / elapsed;
      const threshold = Math.min(112, Math.max(72, panel.getBoundingClientRect().height * .17));
      const shouldClose = !forceCancel && !cancelled && distance > 0
        && (distance >= threshold || (distance >= 30 && velocity >= .62));
      releaseCapture();
      pointerId = null;
      if (shouldClose) {
        panel.style.transition = 'transform 150ms ease';
        panel.style.transform = transformFor(Math.max(distance, Math.min(window.innerHeight * .34, 260)));
        window.setTimeout(() => {
          panel.style.removeProperty('transition');
          panel.style.removeProperty('transform');
          close();
        }, 120);
      } else resetVisual();
    };

    handle.addEventListener('pointerdown', (event) => {
      if (event.pointerType === 'mouse' || event.button !== 0) return;
      const target = event.target instanceof Element ? event.target : null;
      if (target?.closest(interactiveSelector)) return;
      if (layer.classList.contains('hidden') || (layer.hasAttribute('aria-hidden') && layer.getAttribute('aria-hidden') === 'true')) return;
      pointerId = event.pointerId;
      startY = event.clientY;
      startX = event.clientX;
      startAt = performance.now();
      distance = 0;
      cancelled = false;
      clearTimeout(resetTimer);
      panel.style.transition = 'none';
      try { handle.setPointerCapture(pointerId); } catch (_) {}
    });

    handle.addEventListener('pointermove', (event) => {
      if (pointerId === null || event.pointerId !== pointerId) return;
      const dy = event.clientY - startY;
      const dx = Math.abs(event.clientX - startX);
      if (dy < -8 || (dx > Math.abs(dy) + 14 && dx > 24)) {
        cancelled = true;
        return;
      }
      if (dy <= 0) return;
      distance = dy;
      event.preventDefault();
      panel.style.transform = transformFor(distance);
    }, { passive: false });

    handle.addEventListener('pointerup', (event) => finish(event, false));
    handle.addEventListener('pointercancel', (event) => finish(event, true));

    return { reset: resetVisual };
  }

  const normalizeRequestError = (error) => {
    const source = error || {};
    const status = Number(source.httpStatus || source.status || source.response?.status || 0);
    const raw = String(source?.data?.message || source.message || '').trim();
    const network = !status && (source.name === 'TypeError' || /failed to fetch|networkerror|network request failed|load failed/i.test(raw));
    const timeout = source.name === 'AbortError' || /timeout|timed out/i.test(raw);
    return { status, raw, network, timeout };
  };
  const requestErrorMessage = (error, fallback = 'درخواست کامل نشد. دوباره تلاش کنید.') => {
    const info = normalizeRequestError(error);
    if (info.timeout) return 'ارتباط بیش از حد طول کشید. دوباره تلاش کنید.';
    if (info.network) return navigator.onLine === false
      ? 'اتصال اینترنت برقرار نیست. پس از برقراری اتصال دوباره تلاش کنید.'
      : 'ارتباط با سامانه برقرار نشد. اتصال را بررسی و دوباره تلاش کنید.';
    if (info.status === 429) return 'درخواست‌ها بیش از حد سریع ارسال شده‌اند. کمی بعد دوباره تلاش کنید.';
    if (info.status >= 500) return 'سامانه موقتاً نتوانست درخواست را کامل کند. دوباره تلاش کنید.';
    if (info.raw && !/failed to fetch|networkerror|network request failed|load failed/i.test(info.raw)) return info.raw;
    return fallback;
  };
  window.SoknaGuestUI = Object.assign(window.SoknaGuestUI || {}, { normalizeRequestError, requestErrorMessage });

  window.SoknaGuestSheet = { bindSwipeDismiss };
})();

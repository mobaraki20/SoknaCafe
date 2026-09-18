(() => {
  'use strict';

  const installed = new WeakSet();
  const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  function visibleChildren(rail) {
    return [...rail.children].filter((node) => node.getClientRects().length > 0);
  }

  function overflowState(rail) {
    const box = rail.getBoundingClientRect();
    const nodes = visibleChildren(rail).map((node) => node.getBoundingClientRect());
    return {
      left: nodes.some((child) => child.left < box.left - 2),
      right: nodes.some((child) => child.right > box.right + 2),
    };
  }

  function reveal(rail, element, margin = 18) {
    if (!rail || !element || rail.scrollWidth <= rail.clientWidth + 2) return;
    const railBox = rail.getBoundingClientRect();
    const itemBox = element.getBoundingClientRect();
    let delta = 0;
    if (itemBox.left < railBox.left + margin) delta = itemBox.left - (railBox.left + margin);
    else if (itemBox.right > railBox.right - margin) delta = itemBox.right - (railBox.right - margin);
    if (Math.abs(delta) > 2) rail.scrollBy({left: delta, behavior: 'auto'});
  }

  function install(rail) {
    if (!rail || installed.has(rail)) return null;
    installed.add(rail);
    const id = rail.id;
    const shell = rail.closest('.horizontal-rail-shell');
    const buttons = id ? [...document.querySelectorAll(`[data-scroll-rail="${CSS.escape(id)}"]`)] : [];
    const rtl = getComputedStyle(rail).direction === 'rtl';
    let pointerId = null;
    let startX = 0;
    let startScroll = 0;
    let frame = 0;

    const update = () => {
      frame = 0;
      const state = overflowState(rail);
      shell?.classList.toggle('can-scroll-left', state.left);
      shell?.classList.toggle('can-scroll-right', state.right);
      buttons.forEach((button) => {
        const logical = Number(button.dataset.scrollDir || 1);
        const physicalLeft = rtl ? logical > 0 : logical < 0;
        button.disabled = physicalLeft ? !state.left : !state.right;
      });
    };
    const scheduleUpdate = () => {
      if (!frame) frame = requestAnimationFrame(update);
    };
    const move = (logicalDirection) => {
      const physical = Number(logicalDirection || 1) * (rtl ? -1 : 1);
      rail.scrollBy({left: physical * Math.max(240, rail.clientWidth * .72), behavior: reducedMotion() ? 'auto' : 'smooth'});
    };

    buttons.forEach((button) => button.addEventListener('click', () => move(Number(button.dataset.scrollDir || 1))));
    rail.addEventListener('scroll', scheduleUpdate, {passive: true});
    rail.addEventListener('wheel', (event) => {
      // Do not trap normal vertical page scrolling. Only consume a genuinely
      // horizontal gesture or an explicit Shift+wheel gesture.
      const horizontal = Math.abs(event.deltaX) > Math.abs(event.deltaY) + 2;
      const shifted = event.shiftKey && Math.abs(event.deltaY) > 0;
      if (!horizontal && !shifted) return;
      if (rail.scrollWidth <= rail.clientWidth + 2) return;
      const delta = horizontal ? event.deltaX : event.deltaY;
      if (!delta) return;
      event.preventDefault();
      rail.scrollBy({left: delta, behavior: 'auto'});
    }, {passive: false});

    rail.addEventListener('pointerdown', (event) => {
      if (event.pointerType === 'mouse' && event.button !== 0) return;
      if (event.target.closest('a,button,input,select,textarea')) return;
      pointerId = event.pointerId;
      startX = event.clientX;
      startScroll = rail.scrollLeft;
      rail.classList.add('is-dragging');
      rail.setPointerCapture?.(event.pointerId);
    });
    rail.addEventListener('pointermove', (event) => {
      if (pointerId !== event.pointerId) return;
      rail.scrollLeft = startScroll - (event.clientX - startX);
    });
    const stop = (event) => {
      if (pointerId !== event.pointerId) return;
      pointerId = null;
      rail.classList.remove('is-dragging');
      rail.releasePointerCapture?.(event.pointerId);
    };
    rail.addEventListener('pointerup', stop);
    rail.addEventListener('pointercancel', stop);

    if ('ResizeObserver' in window) new ResizeObserver(scheduleUpdate).observe(rail);
    window.addEventListener('resize', scheduleUpdate, {passive: true});
    scheduleUpdate();
    return {refresh: scheduleUpdate, reveal: (element, margin) => reveal(rail, element, margin), move};
  }

  function installAll(root = document) {
    root.querySelectorAll('.horizontal-rail').forEach(install);
  }

  window.SoknaHorizontalRail = {install, installAll, reveal};
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => installAll(), {once: true});
  else installAll();
})();

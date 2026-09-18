(() => {
  'use strict';

  // One owner for contextual action menus across the panel. Desktop menus stay
  // anchored to their trigger and collision-clamped to the visual viewport;
  // touch/mobile uses the shared task-sheet treatment for larger targets, stable
  // backdrop behavior and predictable scroll containment.
  let activeActionMenu = null;
  let activePopover = null;
  let activePopoverHome = null;
  let suppressScrollCloseUntil = 0;
  const mobileSheetMedia = window.matchMedia('(max-width: 640px)');
  const menuBackdrop = document.createElement('button');
  menuBackdrop.type = 'button';
  menuBackdrop.className = 'panel-action-menu-backdrop hidden';
  menuBackdrop.tabIndex = -1;
  menuBackdrop.setAttribute('aria-label', 'بستن گزینه‌ها');
  document.body.appendChild(menuBackdrop);
  const interaction = window.CafeUI?.interaction || window.SoknaInteraction;
  const keyboardInteraction = () => interaction?.isKeyboard?.() ?? true;
  const restoreKeyboardFocus = (target) => {
    if (!keyboardInteraction() || !target?.focus) return false;
    if (interaction?.restoreFocus) return interaction.restoreFocus(target);
    target.focus({ preventScroll: true });
    return true;
  };

  const triggerOf = (menu) => menu?.querySelector(':scope > summary,:scope > [data-action-menu-trigger]');
  const popoverOf = (menu) => activeActionMenu === menu && activePopover
    ? activePopover
    : menu?.querySelector(':scope > .row-action-popover,:scope > [data-action-menu-popover]');

  const portalPopover = (menu) => {
    const popover = menu?.querySelector(':scope > .row-action-popover,:scope > [data-action-menu-popover]');
    if (!popover) return null;
    activePopoverHome = document.createComment('action-menu-popover-home');
    menu.insertBefore(activePopoverHome, popover);
    document.body.appendChild(popover);
    activePopover = popover;
    return popover;
  };

  const restorePopoverHome = () => {
    if (activePopover && activePopoverHome?.parentNode) activePopoverHome.parentNode.insertBefore(activePopover, activePopoverHome.nextSibling);
    activePopoverHome?.remove();
    activePopoverHome = null;
    activePopover = null;
  };

  const viewportBox = () => {
    const vv = window.visualViewport;
    if (vv) {
      return {
        left: Number(vv.offsetLeft || 0),
        top: Number(vv.offsetTop || 0),
        width: Number(vv.width || window.innerWidth),
        height: Number(vv.height || window.innerHeight),
      };
    }
    return { left: 0, top: 0, width: window.innerWidth, height: window.innerHeight };
  };

  function resetPopover(popover) {
    if (!popover) return;
    popover.classList.remove('is-viewport-popover','is-mobile-action-sheet');
    popover.removeAttribute('data-menu-placement');
    for (const prop of ['left','top','right','bottom','max-height']) popover.style.removeProperty(prop);
  }

  function closeActionMenu(restore = false) {
    if (!activeActionMenu) return;
    const previous = activeActionMenu;
    const trigger = triggerOf(previous);
    if (previous.matches('details')) previous.removeAttribute('open');
    else previous.classList.remove('is-open');
    trigger?.setAttribute('aria-expanded', 'false');
    activePopover?.classList.remove('is-open');
    resetPopover(activePopover);
    menuBackdrop.classList.add('hidden');
    document.body.classList.remove('panel-action-menu-open');
    activeActionMenu = null;
    restorePopoverHome();
    if (restore) restoreKeyboardFocus(trigger);
  }

  function position(menu) {
    const trigger = triggerOf(menu);
    const popover = popoverOf(menu);
    if (!trigger || !popover || !menu) return;

    popover.classList.add('is-viewport-popover');
    // Clear stale inline placement before measuring after a resize/orientation change.
    for (const prop of ['left','top','right','bottom','max-height']) popover.style.removeProperty(prop);
    if (mobileSheetMedia.matches) {
      popover.classList.add('is-mobile-action-sheet');
      popover.dataset.menuPlacement = 'sheet';
      return;
    }
    popover.classList.remove('is-mobile-action-sheet');

    const viewport = viewportBox();
    const margin = viewport.width <= 420 ? 8 : 10;
    const gap = 6;
    const boundsLeft = viewport.left + margin;
    const boundsRight = viewport.left + viewport.width - margin;
    const boundsTop = viewport.top + margin;
    const boundsBottom = viewport.top + viewport.height - margin;
    const triggerRect = trigger.getBoundingClientRect();

    // Width is content-led, but never allowed to consume the whole mobile screen.
    const desired = Math.max(210, Math.min(280, popover.scrollWidth || popover.offsetWidth || 228));
    const width = Math.min(desired, Math.max(180, viewport.width - margin * 2));
    const rtl = (document.documentElement.dir || getComputedStyle(document.documentElement).direction) === 'rtl';
    let left = rtl ? triggerRect.right - width : triggerRect.left;
    left = Math.max(boundsLeft, Math.min(left, boundsRight - width));

    // Keep enough room for a useful list even when the visual viewport is small.
    const maxHeight = Math.max(140, Math.min(520, viewport.height - margin * 2));
    popover.style.maxHeight = `${Math.round(maxHeight)}px`;
    // offsetHeight is reliable after the menu has been made visible.
    const height = Math.min(popover.scrollHeight || popover.offsetHeight || 220, maxHeight);
    const belowTop = triggerRect.bottom + gap;
    const aboveTop = triggerRect.top - gap - height;
    let top;
    let placement;
    if (belowTop + height <= boundsBottom) {
      top = belowTop;
      placement = 'below';
    } else if (aboveTop >= boundsTop) {
      top = aboveTop;
      placement = 'above';
    } else {
      const roomBelow = boundsBottom - belowTop;
      const roomAbove = triggerRect.top - gap - boundsTop;
      if (roomBelow >= roomAbove) {
        top = Math.max(boundsTop, belowTop);
        popover.style.maxHeight = `${Math.max(120, Math.floor(roomBelow))}px`;
        placement = 'below-clamped';
      } else {
        const usable = Math.max(120, Math.floor(roomAbove));
        popover.style.maxHeight = `${usable}px`;
        top = Math.max(boundsTop, triggerRect.top - gap - Math.min(height, usable));
        placement = 'above-clamped';
      }
    }

    popover.style.left = `${Math.round(left)}px`;
    popover.style.top = `${Math.round(top)}px`;
    popover.dataset.menuPlacement = placement;
  }

  function syncActionMenuContext(menu) {
    const popover = popoverOf(menu);
    if (!popover) return;
    popover.querySelector(':scope > .row-action-context')?.remove();
    const label = String(menu.dataset.actionMenuLabel || '').trim();
    // Context is useful on small screens where several identical "..." triggers
    // can be visible at once. It identifies the entity inside the task sheet.
    if (!label || viewportBox().width > 720) return;
    const heading = document.createElement('div');
    heading.className = 'row-action-context';
    heading.setAttribute('role', 'presentation');
    heading.textContent = label;
    popover.prepend(heading);
  }

  function openActionMenu(menu) {
    if (!menu) return;
    if (activeActionMenu && activeActionMenu !== menu) closeActionMenu(false);
    window.SoknaPanelTools?.close?.(false);
    window.SoknaPanelNavigation?.close?.(false);
    activeActionMenu = menu;
    const popover = portalPopover(menu);
    if (!popover) { activeActionMenu = null; return; }
    syncActionMenuContext(menu);
    if (mobileSheetMedia.matches) {
      menuBackdrop.classList.remove('hidden');
      document.body.classList.add('panel-action-menu-open');
    }
    suppressScrollCloseUntil = performance.now() + 220;
    if (menu.matches('details')) menu.setAttribute('open', '');
    else menu.classList.add('is-open');
    popover.classList.add('is-open');
    triggerOf(menu)?.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(() => {
      if (activeActionMenu !== menu) return;
      position(menu);
      if (keyboardInteraction()) popoverOf(menu)?.querySelector('a,button:not(:disabled)')?.focus({ preventScroll: true });
      else triggerOf(menu)?.blur();
    });
  }

  menuBackdrop.addEventListener('click', (event) => { event.preventDefault(); closeActionMenu(false); });

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.row-action-menu > summary,[data-action-menu-trigger]');
    if (trigger) {
      event.preventDefault();
      event.stopPropagation();
      const menu = trigger.closest('.row-action-menu,[data-action-menu]');
      if (activeActionMenu === menu) closeActionMenu(true);
      else openActionMenu(menu);
      return;
    }
    if (activeActionMenu && event.target.closest('.row-action-popover,[data-action-menu-popover]')) {
      if (event.target.closest('a,[role="menuitem"],button')) window.setTimeout(() => closeActionMenu(false), 0);
      return;
    }
    if (activeActionMenu) closeActionMenu(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && activeActionMenu) {
      event.preventDefault();
      closeActionMenu(true);
    }
  });

  const reposition = () => { if (activeActionMenu) position(activeActionMenu); };
  const closeOnPrimaryScroll = (event) => {
    if (event?.target instanceof Element && event.target.closest?.('.row-action-popover')) return;
    if (performance.now() < suppressScrollCloseUntil) { reposition(); return; }
    if (activeActionMenu) closeActionMenu(false);
  };
  mobileSheetMedia.addEventListener?.('change', () => { if (activeActionMenu) closeActionMenu(false); });
  window.addEventListener('resize', reposition);
  window.visualViewport?.addEventListener('resize', reposition);
  window.visualViewport?.addEventListener('scroll', reposition);
  window.addEventListener('scroll', closeOnPrimaryScroll, { passive: true });
  document.getElementById('panelContent')?.addEventListener('scroll', closeOnPrimaryScroll, { passive: true });

  window.SoknaActionMenu = { close: closeActionMenu, open: openActionMenu, reposition };
})();

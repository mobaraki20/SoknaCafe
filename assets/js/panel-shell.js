(() => {
  'use strict';

  if (window.SoknaPanelShell?.ready) return;

  const iconSprite = String(window.SOKNA_ICON_SPRITE || '');
  const escapeAttribute = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const iconMarkup = (name, className = '') => {
    const safeName = String(name || 'info').replace(/[^a-z0-9_-]/gi, '') || 'info';
    const safeClass = String(className || '').replace(/[^a-z0-9 _-]/gi, '').trim();
    return `<svg class="ui-icon${safeClass ? ` ${safeClass}` : ''}" aria-hidden="true" focusable="false"><use href="${escapeAttribute(iconSprite)}#icon-${safeName}"></use></svg>`;
  };
  window.SoknaIcons = Object.freeze({ sprite: iconSprite, markup: iconMarkup });

  const interaction = window.CafeUI?.interaction || window.SoknaInteraction;
  const keyboardInteraction = () => interaction?.isKeyboard?.() ?? true;
  const restoreKeyboardFocus = (target) => {
    if (!keyboardInteraction() || !target?.focus) return false;
    if (interaction?.restoreFocus) return interaction.restoreFocus(target);
    target.focus({ preventScroll: true });
    return true;
  };
  const sidebar = document.getElementById('sidebar');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');
  const sidebarToggles = [...document.querySelectorAll('[data-panel-nav-toggle]')];
  const sidebarToggle = sidebarToggles[0] || null;
  const toolsToggle = document.getElementById('panelToolsToggle');
  const toolsPopover = document.getElementById('panelToolsPopover');
  const toolsHome = toolsPopover?.parentNode || null;
  const toolsHomeMarker = toolsPopover ? document.createComment('panel-tools-popover-home') : null;
  if (toolsPopover && toolsHomeMarker) toolsHome.insertBefore(toolsHomeMarker, toolsPopover);
  const mobileSidebar = window.matchMedia('(max-width: 1180px)');
  const sidebarFocusable = 'a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])';
  let sidebarOpener = null;
  let toolsOpener = null;
  let toolsScrollGraceUntil = 0;
  let unbindToolsSwipe = () => {};
  const toolsSheetMedia = window.matchMedia('(max-width: 640px)');
  const toolsBackdrop = document.createElement('button');
  toolsBackdrop.type = 'button';
  toolsBackdrop.className = 'panel-tools-backdrop hidden';
  toolsBackdrop.tabIndex = -1;
  toolsBackdrop.setAttribute('aria-label', 'بستن ابزارها');
  document.body.appendChild(toolsBackdrop);

  const sidebarIsOpen = () => Boolean(sidebar?.classList.contains('open'));
  const toolsIsOpen = () => Boolean(toolsPopover && !toolsPopover.classList.contains('hidden'));
  const restoreToolsHome = () => {
    if (toolsPopover && toolsHomeMarker?.parentNode) toolsHomeMarker.parentNode.insertBefore(toolsPopover, toolsHomeMarker.nextSibling);
  };

  const syncSidebarState = () => {
    const open = sidebarIsOpen() && mobileSidebar.matches;
    sidebarToggles.forEach(toggle => { toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); if (!toggle.hasAttribute('data-app-nav-more')) toggle.setAttribute('aria-label', open ? 'بستن منو' : 'بازکردن منو'); });
    sidebar?.setAttribute('aria-hidden', mobileSidebar.matches && !open ? 'true' : 'false');
    if (sidebar && 'inert' in sidebar) sidebar.inert = mobileSidebar.matches && !open;
    document.body.classList.toggle('panel-sidebar-open', open);
  };

  const closeTools = (restoreFocus = false) => {
    if (!toolsToggle || !toolsPopover || !toolsIsOpen()) return;
    toolsPopover.classList.add('hidden');
    toolsPopover.classList.remove('is-mobile-tools-sheet');
    toolsBackdrop.classList.add('hidden');
    document.body.classList.remove('panel-tools-open');
    toolsPopover.setAttribute('aria-hidden', 'true');
    toolsToggle.setAttribute('aria-expanded', 'false');
    restoreToolsHome();
    const focusTarget = toolsOpener || toolsToggle;
    toolsOpener = null;
    if (restoreFocus && keyboardInteraction()) restoreKeyboardFocus(focusTarget);
  };

  const closeSidebar = (restoreFocus = false) => {
    if (!sidebarIsOpen()) {
      syncSidebarState();
      return;
    }
    const focusTarget = sidebarOpener || sidebarToggle;
    sidebar?.classList.remove('open');
    syncSidebarState();
    sidebarOpener = null;
    if (restoreFocus && keyboardInteraction()) restoreKeyboardFocus(focusTarget);
  };

  const openSidebar = () => {
    if (!sidebar || !mobileSidebar.matches) return;
    closeTools(false);
    window.SoknaActionMenu?.close?.(false);
    sidebarOpener = document.activeElement instanceof HTMLElement ? document.activeElement : sidebarToggle;
    sidebar.classList.add('open');
    syncSidebarState();
    requestAnimationFrame(() => {
      const target = sidebar.querySelector('[aria-current="page"]') || sidebar.querySelector(sidebarFocusable);
      if (keyboardInteraction()) target?.focus?.({ preventScroll: true });
      else sidebarToggle?.blur();
    });
  };

  const openTools = () => {
    if (!toolsToggle || !toolsPopover) return;
    closeSidebar(false);
    window.SoknaActionMenu?.close?.(false);
    toolsOpener = document.activeElement instanceof HTMLElement ? document.activeElement : toolsToggle;
    toolsScrollGraceUntil = performance.now() + 250;
    if (toolsSheetMedia.matches) {
      document.body.appendChild(toolsPopover);
      toolsPopover.classList.add('is-mobile-tools-sheet');
      toolsBackdrop.classList.remove('hidden');
      document.body.classList.add('panel-tools-open');
    }
    toolsPopover.classList.remove('hidden');
    toolsPopover.setAttribute('aria-hidden', 'false');
    toolsToggle.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(() => { if (keyboardInteraction()) toolsPopover.querySelector('[role="menuitem"]:not(.hidden)')?.focus?.({ preventScroll: true }); else toolsToggle?.blur(); });
  };

  const groups = [...document.querySelectorAll('[data-nav-group]')];
  const groupStorageKey = 'sokna.panel.nav-group.v1300';
  const readOpenGroup = () => {
    try { return sessionStorage.getItem(groupStorageKey) || ''; } catch (_) { return ''; }
  };
  const writeOpenGroup = (key) => {
    try {
      if (key) sessionStorage.setItem(groupStorageKey, key);
      else sessionStorage.removeItem(groupStorageKey);
    } catch (_) {}
  };
  const setGroup = (group, open, { persist = true, focus = false } = {}) => {
    if (!group) return;
    const button = group.querySelector('.side-nav-group-toggle');
    const nav = group.querySelector(':scope > .side-nav');
    group.classList.toggle('is-open', open);
    button?.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (nav) nav.hidden = !open;
    if (persist) {
      if (open) writeOpenGroup(group.dataset.navGroup || '');
      else if (readOpenGroup() === group.dataset.navGroup) writeOpenGroup('');
    }
    if (focus) button?.focus?.({ preventScroll: true });
  };
  const restoreGroups = () => {
    const current = groups.find(group => group.classList.contains('is-current'));
    const stored = groups.find(group => group.dataset.navGroup === readOpenGroup());
    const target = current || stored || groups[0] || null;
    groups.forEach(group => setGroup(group, group === target, { persist: false }));
    if (target) writeOpenGroup(target.dataset.navGroup || '');
  };

  restoreGroups();
  groups.forEach(group => group.querySelector('.side-nav-group-toggle')?.addEventListener('click', () => {
    if (group.classList.contains('is-open')) return;
    groups.filter(item => item !== group).forEach(item => setGroup(item, false, { persist: false }));
    setGroup(group, true);
  }));

  sidebarToggles.forEach(toggle => toggle.addEventListener('click', event => {
    event.preventDefault();
    event.stopPropagation();
    sidebarIsOpen() ? closeSidebar(true) : openSidebar();
  }));
  sidebarBackdrop?.addEventListener('click', event => {
    event.preventDefault();
    closeSidebar(true);
  });
  sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
    closeTools(false);
    if (mobileSidebar.matches) closeSidebar(false);
  }));

  toolsBackdrop.addEventListener('click', event => { event.preventDefault(); closeTools(false); });

  const bindToolsSwipe = () => {
    unbindToolsSwipe();
    if (!toolsPopover || !window.CafeUI?.bindSwipeDismiss) return;
    unbindToolsSwipe = window.CafeUI.bindSwipeDismiss({
      sheet: toolsPopover,
      handle: toolsPopover,
      maxStartY: 48,
      canStart: () => toolsSheetMedia.matches && toolsIsOpen() && toolsPopover.classList.contains('is-mobile-tools-sheet'),
      onDismiss: () => {
        if (!toolsSheetMedia.matches || !toolsIsOpen()) return false;
        closeTools(false);
        return true;
      }
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindToolsSwipe, { once: true });
  else bindToolsSwipe();

  toolsToggle?.addEventListener('click', event => {
    event.preventDefault();
    event.stopPropagation();
    toolsIsOpen() ? closeTools(true) : openTools();
  });
  toolsPopover?.addEventListener('click', event => {
    if (event.target.closest('[role="menuitem"]')) window.setTimeout(() => closeTools(false), 0);
  });

  document.addEventListener('click', event => {
    if (sidebarIsOpen() && !event.target.closest('#sidebar') && !event.target.closest('[data-panel-nav-toggle]')) closeSidebar(false);
    if (toolsIsOpen() && !event.target.closest('#panelToolsMenu') && !toolsPopover.contains(event.target)) closeTools(false);
  });

  const closeToolsOnPrimaryScroll = () => {
    if (performance.now() < toolsScrollGraceUntil) return;
    if (toolsIsOpen()) closeTools(false);
  };
  window.addEventListener('scroll', closeToolsOnPrimaryScroll, { passive: true });
  document.getElementById('panelContent')?.addEventListener('scroll', closeToolsOnPrimaryScroll, { passive: true });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      if (sidebarIsOpen() && mobileSidebar.matches) {
        event.preventDefault();
        closeSidebar(true);
        return;
      }
      if (toolsIsOpen()) {
        event.preventDefault();
        closeTools(true);
        return;
      }
    }
    if (event.key !== 'Tab' || !sidebarIsOpen() || !mobileSidebar.matches) return;
    const focusables = [...sidebar.querySelectorAll(sidebarFocusable)].filter(el => !el.hasAttribute('disabled') && el.getClientRects().length);
    if (!focusables.length) {
      event.preventDefault();
      sidebar.focus?.();
      return;
    }
    const first = focusables[0], last = focusables.at(-1);
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    } else if (!sidebar.contains(document.activeElement)) {
      event.preventDefault();
      first.focus();
    }
  });

  const handleSidebarBreakpoint = () => {
    if (!mobileSidebar.matches) sidebar?.classList.remove('open');
    syncSidebarState();
    restoreGroups();
  };
  toolsSheetMedia.addEventListener?.('change', () => { if (toolsIsOpen()) closeTools(false); });
  if (typeof mobileSidebar.addEventListener === 'function') mobileSidebar.addEventListener('change', handleSidebarBreakpoint);
  else mobileSidebar.addListener?.(handleSidebarBreakpoint);

  window.SoknaPanelNavigation = { isOpen: sidebarIsOpen, close: closeSidebar, open: openSidebar, setGroup };
  window.SoknaPanelTools = { isOpen: toolsIsOpen, close: closeTools, open: openTools };
  window.SoknaPanelShell = { ready: true, sidebarIsOpen, toolsIsOpen, closeSidebar, openSidebar, closeTools, openTools };
  document.documentElement.dataset.panelShellReady = '1';
  document.documentElement.dataset.panelNavigationReady = '1';
  syncSidebarState();
})();

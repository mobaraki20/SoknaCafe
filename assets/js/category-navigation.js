(() => {
  'use strict';

  function install(options = {}) {
    const root = options.root || document;
    const sections = [...root.querySelectorAll(options.sectionSelector || '.category-section-v12')];
    const tabs = [...root.querySelectorAll(options.tabSelector || '.category-orbit a')];
    if (!sections.length || !tabs.length) return null;

    const rail = tabs[0].closest('.category-orbit');
    const shell = rail?.closest('.category-rail-shell,.horizontal-rail-shell');
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    const sectionById = new Map(sections.map((section, index) => [section.id, {section, index}]));
    let currentIndex = -1;
    let frame = 0;
    let lockToken = 0;
    let lockedTarget = '';

    const stickyOffset = () => {
      // The guest header scrolls away and is intentionally excluded. Only the
      // actual sticky category shell is an obstruction at the final position.
      const height = shell?.getBoundingClientRect().height || rail?.getBoundingClientRect().height || 56;
      const top = shell ? Number.parseFloat(getComputedStyle(shell).top || '0') || 0 : 0;
      return Math.max(56, Math.min(112, top + height + 8));
    };

    const absoluteTop = (section) => window.scrollY + section.getBoundingClientRect().top;

    const tabForIndex = (index) => {
      if (index < 0) return tabs.find((tab) => tab.getAttribute('href') === '#menuStart') || tabs[0];
      return tabs.find((tab) => tab.getAttribute('href') === `#${sections[index].id}`) || null;
    };

    const revealTab = (tab) => {
      if (!tab || !rail) return;
      if (window.SoknaHorizontalRail?.reveal) window.SoknaHorizontalRail.reveal(rail, tab, 18);
    };

    const activate = (index, reveal = true) => {
      const nextTab = tabForIndex(index);
      if (!nextTab) return;
      const changed = index !== currentIndex || nextTab.getAttribute('aria-current') !== 'true';
      if (!changed) return;
      currentIndex = index;
      tabs.forEach((tab) => {
        const active = tab === nextTab;
        tab.classList.toggle('active', active);
        if (active) tab.setAttribute('aria-current', 'true');
        else tab.removeAttribute('aria-current');
      });
      if (reveal) revealTab(nextTab);
      root.dispatchEvent(new CustomEvent('sokna:category-change', {detail: {index, id: index < 0 ? 'menuStart' : sections[index].id}}));
    };

    const sync = () => {
      frame = 0;
      if (lockedTarget) return;
      const marker = window.scrollY + stickyOffset();
      let next = -1;
      // Read the live layout every sync. Search, table notices, image loading and
      // viewport changes can all shift categories without a navigation event.
      for (let i = 0; i < sections.length; i += 1) {
        if (absoluteTop(sections[i]) <= marker + 14) next = i;
        else break;
      }
      if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 3) next = sections.length - 1;
      activate(next, true);
    };

    const schedule = () => {
      if (frame) return;
      frame = requestAnimationFrame(sync);
    };

    const scrollToTarget = (target, selector) => {
      const token = ++lockToken;
      const entry = target.id === 'menuStart' ? {index: -1} : sectionById.get(target.id);
      const targetIndex = entry?.index ?? -1;
      lockedTarget = target.id;
      activate(targetIndex, true);

      let userInterrupted = false;
      let lastDesired = null;
      let stableFrames = 0;
      let correctionAt = 0;
      const started = performance.now();
      const desiredTop = () => target.id === 'menuStart' ? 0 : Math.max(0, absoluteTop(target) - stickyOffset());
      const interrupt = () => {
        if (token !== lockToken) return;
        userInterrupted = true;
        lockToken += 1;
        lockedTarget = '';
        schedule();
      };
      const interruptOptions = {passive: true, once: true};
      window.addEventListener('wheel', interrupt, interruptOptions);
      window.addEventListener('touchstart', interrupt, interruptOptions);

      const initial = desiredTop();
      lastDesired = initial;
      window.scrollTo({top: initial, behavior: reducedMotion ? 'auto' : 'smooth'});
      try { history.replaceState(history.state, '', selector); } catch (_) {}

      const followLiveTarget = () => {
        if (token !== lockToken || userInterrupted) return;
        const now = performance.now();
        const desired = desiredTop();
        const targetShift = lastDesired === null ? 0 : Math.abs(desired - lastDesired);
        const distance = Math.abs(desired - window.scrollY);

        // content-visibility can replace estimated category heights with real
        // heights while the smooth scroll is already running. Keep the clicked
        // category as the owner and follow the live DOM target until both the
        // destination and the viewport are stable for several frames.
        if (targetShift > 2) stableFrames = 0;
        else if (distance <= 3) stableFrames += 1;
        else stableFrames = 0;

        if (targetShift > 4 && now - correctionAt > 90) {
          correctionAt = now;
          window.scrollTo({top: desired, behavior: reducedMotion ? 'auto' : 'smooth'});
        } else if (now - started > 2200 && distance > 3 && now - correctionAt > 120) {
          // A bounded final correction avoids releasing the scroll spy on the
          // previous section when Chrome has already ended its native animation.
          correctionAt = now;
          window.scrollTo({top: desired, behavior: 'auto'});
        }

        lastDesired = desired;
        if (stableFrames >= 5 || now - started > 3600) {
          if (Math.abs(desiredTop() - window.scrollY) > 3) window.scrollTo({top: desiredTop(), behavior: 'auto'});
          lockedTarget = '';
          activate(targetIndex, true);
          schedule();
          return;
        }
        requestAnimationFrame(followLiveTarget);
      };
      requestAnimationFrame(followLiveTarget);
    };

    tabs.forEach((tab) => tab.addEventListener('click', (event) => {
      const selector = tab.getAttribute('href') || '';
      const target = selector.startsWith('#') ? root.querySelector(selector) : null;
      if (!target) return;
      event.preventDefault();
      scrollToTarget(target, selector);
    }));

    sections.forEach((section) => section.style.setProperty('--category-scroll-offset', `${stickyOffset()}px`));
    const refresh = () => {
      const offset = `${stickyOffset()}px`;
      sections.forEach((section) => section.style.setProperty('--category-scroll-offset', offset));
      schedule();
    };

    window.addEventListener('scroll', schedule, {passive: true});
    window.addEventListener('resize', refresh, {passive: true});
    if ('ResizeObserver' in window) {
      const observer = new ResizeObserver(refresh);
      if (shell) observer.observe(shell);
      observer.observe(document.body);
    }
    window.addEventListener('load', refresh, {once: true});

    const currentTab = tabs.find((tab) => tab.getAttribute('aria-current') === 'true');
    const currentHref = currentTab?.getAttribute('href') || '#menuStart';
    currentIndex = currentHref === '#menuStart' ? -1 : (sectionById.get(currentHref.slice(1))?.index ?? -1);
    refresh();
    return {refresh, activate};
  }

  window.SoknaCategoryNavigation = {install};
})();

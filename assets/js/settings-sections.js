(() => {
  'use strict';
  const nav = document.querySelector('[data-settings-nav]');
  if (!nav) return;

  const groups = {
    settingsBrand: ['settingsIdentity', 'settingsFavicon'],
    settingsGuest: ['settingsTheme', 'settingsPublic', 'settingsGuestFeatures', 'settingsGuestMessages'],
    settingsOperations: ['settingsOperationalPreferences'],
    settingsIntegrations: ['settingsDomain', 'settingsIntegrationLinks'],
    settingsSystem: ['settingsHealth', 'settingsSystemOptions', 'settingsDeviceTools', 'settingsSecurity'],
  };
  const groupIds = Object.keys(groups);
  const sections = [...new Set(Object.values(groups).flat())]
    .map(id => document.getElementById(id))
    .filter(Boolean);
  const containers = new Map(sections.map(section => [section, section.parentElement]));

  function show(groupId, updateHash = true) {
    if (!groupIds.includes(groupId)) groupId = 'settingsBrand';
    const visibleIds = new Set(groups[groupId] || []);
    sections.forEach(section => {
      const active = visibleIds.has(section.id);
      section.hidden = !active;
      section.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    [...new Set(containers.values())].forEach(parent => {
      if (!parent || !parent.matches('.page-grid,.settings-page-grid')) return;
      const visibleChildren = [...parent.children].filter(child => !child.hidden && child.matches('.settings-section'));
      parent.classList.toggle('is-settings-single', visibleChildren.length <= 1);
    });
    nav.querySelectorAll('a').forEach(link => {
      const active = link.hash === `#${groupId}`;
      link.classList.toggle('is-active', active);
      link.setAttribute('aria-current', active ? 'page' : 'false');
    });
    if (updateHash && location.hash !== `#${groupId}`) history.replaceState(null, '', `#${groupId}`);
  }

  nav.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    if (!link) return;
    event.preventDefault();
    show(link.hash.slice(1));
    document.querySelector('.settings-section-index')?.scrollIntoView({ block: 'start', behavior: 'smooth' });
  });
  window.addEventListener('hashchange', () => show(location.hash.slice(1), false));
  show(location.hash.slice(1) || 'settingsBrand', false);
})();

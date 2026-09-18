(() => {
  'use strict';
  const standaloneQuery = window.matchMedia?.('(display-mode: standalone)');
  const isStandalone = () => Boolean(standaloneQuery?.matches || window.navigator.standalone === true);
  const syncStandaloneClass = () => document.documentElement.classList.toggle('pwa-standalone', isStandalone());
  syncStandaloneClass();
  standaloneQuery?.addEventListener?.('change', syncStandaloneClass);

  const networkBanner = document.getElementById('panelNetworkBanner');
  const syncNetworkState = () => {
    const offline = navigator.onLine === false;
    document.documentElement.classList.toggle('is-offline', offline);
    networkBanner?.classList.toggle('hidden', !offline);
  };
  window.addEventListener('online', syncNetworkState);
  window.addEventListener('offline', syncNetworkState);
  syncNetworkState();

  if (window.PWA_ENABLED === false) {
    navigator.serviceWorker?.getRegistrations?.().then((list) => list.forEach((registration) => registration.unregister())).catch(() => {});
    return;
  }

  const installButton = document.getElementById('pwaInstallButton');
  const updateBanner = document.getElementById('pwaUpdateBanner');
  const updateApply = document.getElementById('pwaUpdateApply');
  const updateLater = document.getElementById('pwaUpdateLater');
  let deferredPrompt = null;
  let waitingWorker = null;
  let updateRequested = false;

  const showUpdate = (worker) => {
    if (!worker) return;
    waitingWorker = worker;
    updateBanner?.classList.remove('hidden');
  };
  const hideUpdate = () => updateBanner?.classList.add('hidden');
  const unsafeToReload = () => Boolean(
    document.body.classList.contains('has-panel-dialog')
    || document.querySelector('[role="dialog"]:not(.hidden),[aria-modal="true"]:not(.hidden),[data-dirty="1"],.is-dirty,[aria-busy="true"]')
  );
  const applyUpdate = () => {
    if (!waitingWorker) return;
    if (unsafeToReload()) {
      window.CafeUI?.toast?.('اول عملیات یا فرم باز را تمام کن؛ بعد به‌روزرسانی را بزن.', 'warning');
      return;
    }
    updateRequested = true;
    updateApply && (updateApply.disabled = true);
    waitingWorker.postMessage({ type: 'SKIP_WAITING' });
  };

  if ('serviceWorker' in navigator && window.PWA_SW_URL && window.isSecureContext) {
    navigator.serviceWorker.register(window.PWA_SW_URL, { scope: new URL('.', window.PWA_SW_URL).pathname }).then((registration) => {
      if (registration.waiting) showUpdate(registration.waiting);
      registration.addEventListener('updatefound', () => {
        const worker = registration.installing;
        worker?.addEventListener('statechange', () => {
          if (worker.state === 'installed' && navigator.serviceWorker.controller) showUpdate(registration.waiting || worker);
        });
      });
      window.addEventListener('focus', () => registration.update().catch(() => {}));
    }).catch(() => {});
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (updateRequested) location.reload();
    });
  }

  updateApply?.addEventListener('click', applyUpdate);
  updateLater?.addEventListener('click', hideUpdate);

  if (installButton && !isStandalone()) installButton.classList.remove('hidden');
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    if (installButton) {
      const label = installButton.querySelector('span');
      if (label) label.textContent = 'نصب وب‌اپ';
      installButton.classList.remove('hidden');
    }
  });

  installButton?.addEventListener('click', async () => {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      await deferredPrompt.userChoice;
      deferredPrompt = null;
      return;
    }
    const isiOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const message = !window.isSecureContext
      ? 'نصب وب‌اپ روی آدرس HTTP ممکن نیست. سامانه را با HTTPS باز کنید.'
      : isiOS
        ? 'در Safari گزینه Share و سپس Add to Home Screen را بزنید.'
        : 'از منوی مرورگر گزینه «Install app» یا «Add to Home screen» را انتخاب کنید.';
    if (window.CafeUI?.toast) CafeUI.toast(message, window.isSecureContext ? '' : 'warning');
    else {
      const note=document.createElement('div');note.className='pwa-inline-message';note.setAttribute('role','status');note.textContent=message;document.body.appendChild(note);setTimeout(()=>note.remove(),3500);
    }
  });
  window.addEventListener('appinstalled', () => installButton?.classList.add('hidden'));
})();

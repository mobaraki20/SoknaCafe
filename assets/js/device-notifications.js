(() => {
  'use strict';
  const button = document.getElementById('waiterNotify');
  const testButton = document.getElementById('waiterNotifyTest');
  if (!button || !window.WAITER_PUSH_API) return;
  const buttonLabel = button.querySelector('span') || button.appendChild(document.createElement('span'));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let apiState = null;
  let subscription = null;
  let busy = false;

  const b64ToBytes = value => {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map(char => char.charCodeAt(0)));
  };
  const json = async (url, options = {}) => {
    const response = await fetch(url, { cache: 'no-store', headers: { Accept: 'application/json', ...(options.headers || {}) }, ...options });
    const data = await response.json().catch(() => ({}));
    window.SoknaPushRuntime?.handleResponse?.(data);
    if (!response.ok || data.success === false) { const error=new Error(data.message || 'عملیات اعلان انجام نشد.'); error.data=data; error.httpStatus=response.status; throw error; }
    return data;
  };
  const post = (action, extra = {}) => json(window.WAITER_PUSH_API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ action, csrf_token: csrf, ...extra })
  });
  const label = () => {
    const ua = navigator.userAgent;
    if (/Android/i.test(ua)) return 'گوشی اندرویدی';
    if (/iPhone|iPad/i.test(ua)) return 'آیفون یا آیپد';
    if (/Windows/i.test(ua)) return 'کامپیوتر ویندوز';
    if (/Macintosh/i.test(ua)) return 'کامپیوتر مک';
    return 'دستگاه کارکنان';
  };
  const supportReason = () => {
    if (!window.isSecureContext) return 'اعلان فقط روی HTTPS یا localhost فعال می‌شود.';
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return 'این مرورگر از اعلان پس‌زمینه پشتیبانی نمی‌کند.';
    return '';
  };
  const render = () => {
    const reason = supportReason();
    button.disabled = busy || Boolean(reason);
    button.title = reason;
    if (busy) buttonLabel.textContent = 'در حال بررسی…';
    else if (reason) buttonLabel.textContent = 'اعلان پشتیبانی نمی‌شود';
    else if (subscription) buttonLabel.textContent = 'اعلان دستگاه فعال است';
    else if (Notification.permission === 'denied') buttonLabel.textContent = 'اعلان در مرورگر مسدود است';
    else buttonLabel.textContent = 'فعال‌کردن اعلان دستگاه';
    testButton?.classList.toggle('hidden', !subscription);
    if (testButton) {
      testButton.disabled = busy || !subscription;
      testButton.title = subscription ? 'مسیر واقعی صف و Push همین دستگاه بررسی می‌شود.' : '';
    }
  };
  const registration = async () => {
    if (window.PWA_SW_URL) await navigator.serviceWorker.register(window.PWA_SW_URL, { scope: new URL('.', window.PWA_SW_URL).pathname });
    return navigator.serviceWorker.ready;
  };
  const refresh = async () => {
    render();
    if (supportReason()) return;
    try {
      apiState = await json(window.WAITER_PUSH_API);
      const reg = await registration();
      subscription = await reg.pushManager.getSubscription();
    } catch (error) {
      // Passive state refresh must not look like the user's foreground operation failed.
      // Interactive enable/disable/test actions still surface their own errors below.
      apiState = null;
    } finally { render(); }
  };
  const enable = async () => {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') throw new Error('اجازه اعلان داده نشد. از تنظیمات مرورگر یا گوشی آن را فعال کن.');
    apiState ||= await json(window.WAITER_PUSH_API);
    if (!apiState.public_key) throw new Error('کلید اعلان سامانه آماده نیست.');
    const reg = await registration();
    subscription = await reg.pushManager.getSubscription();
    if (!subscription) subscription = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(apiState.public_key) });
    await post('subscribe', { subscription: subscription.toJSON(), device_label: label() });
    localStorage.setItem('sokna.device.notifications', '1');
    window.CafeUI?.toast?.('اعلان این دستگاه فعال شد.');
  };
  const disable = async () => {
    const ok = await (window.CafeUI?.confirm?.('اعلان‌های پس‌زمینه روی همین دستگاه دیگر نمایش داده نمی‌شوند.','خاموش‌کردن اعلان دستگاه',{ okLabel:'خاموش‌کردن', danger:true, trigger:button }) ?? Promise.resolve(false));
    if (!ok) return;
    const endpoint = subscription?.endpoint || '';
    if (endpoint) await post('unsubscribe', { endpoint });
    await subscription?.unsubscribe?.();
    subscription = null;
    localStorage.setItem('sokna.device.notifications', '0');
    window.CafeUI?.toast?.('اعلان این دستگاه خاموش شد.');
  };
  button.addEventListener('click', async () => {
    if (busy || supportReason()) return;
    busy = true; render();
    try { subscription ? await disable() : await enable(); }
    catch (error) { window.CafeUI?.toast?.(window.CafeUI?.requestErrorMessage?.(error) || 'این تغییر انجام نشد.', 'error'); }
    finally { busy = false; await refresh(); }
  });
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  const testPipeline = async () => {
    const created = await post('test');
    const queueId = Number(created.queue_id || 0);
    const diagnosticKey = String(created.diagnostic_key || '');
    if (!queueId || !diagnosticKey) throw new Error('شناسه تست کامل اعلان دریافت نشد.');
    let last = null;
    for (let attempt = 0; attempt < 15; attempt++) {
      await sleep(attempt === 0 ? 350 : 700);
      last = await post('test_status', { queue_id: queueId, diagnostic_key: diagnosticKey });
      const deliveries = last.deliveries || {};
      if (last.status === 'sent' && Number(deliveries.sent || 0) > 0) return last;
      if (last.status === 'failed' || Number(deliveries.failed || 0) > 0 || Number(deliveries.expired || 0) > 0) {
        const error = new Error('صف اعلان پردازش شد اما ارسال به این دستگاه ناموفق بود. جزئیات فنی در بخش مدیریت اعلان‌ها ثبت شده است.');
        error.data = last;
        throw error;
      }
      if (last.status === 'sent' && Number(deliveries.total || 0) === 0) {
        const error = new Error('صف پردازش شد اما هیچ دستگاه فعالی برای این کاربر پیدا نشد.');
        error.data = last;
        throw error;
      }
    }
    const error = new Error('تست کامل اعلان در صف مانده است؛ اتصال Push و وضعیت صف اعلان را بررسی کن.');
    error.data = last || {};
    throw error;
  };
  testButton?.addEventListener('click', async () => {
    if (busy || !subscription) return;
    busy = true; render();
    try {
      await testPipeline();
      apiState = await json(window.WAITER_PUSH_API);
      let message = 'مسیر کامل اعلان؛ صف، ارسال خودکار و Push این دستگاه سالم است.';
      if (apiState.admin_live_operations === false) message += ' این حساب مدیر است و اعلان عملیات زنده برای مدیر فعلاً خاموش است.';
      window.CafeUI?.toast?.(message, apiState.admin_live_operations === false ? 'warning' : 'success');
    }
    catch (error) { window.CafeUI?.toast?.(window.CafeUI?.requestErrorMessage?.(error) || 'تست کامل اعلان انجام نشد.', 'error'); }
    finally { busy = false; render(); }
  });
  window.SoknaPush = { refresh };
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  window.addEventListener('online', refresh);
  refresh();
})();

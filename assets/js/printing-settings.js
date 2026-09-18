(() => {
  'use strict';
  const dataNode = document.getElementById('printing-printers-data');
  let printersByAgent = {};
  try { printersByAgent = JSON.parse(dataNode?.textContent || '{}') || {}; } catch (_) {}

  const fillPair = (form, role) => {
    const agentSelect = form.querySelector(`[data-print-agent-select="${role}"]`);
    const printerSelect = form.querySelector(`[data-print-printer-select="${role}"]`);
    if (!agentSelect || !printerSelect) return;
    const agentId = String(agentSelect.value || '0');
    const current = String(printerSelect.dataset.currentPrinter || printerSelect.value || '').trim();
    const list = Array.isArray(printersByAgent[agentId]) ? printersByAgent[agentId] : [];
    printerSelect.replaceChildren();
    const first = document.createElement('option');
    first.value = '';
    first.textContent = agentId === '0' ? (role === 'fallback' ? 'بدون پرینتر جایگزین' : 'ابتدا رایانه چاپ را انتخاب کنید') : (list.length ? '— انتخاب پرینتر —' : 'پرینتری از این رایانه گزارش نشده');
    printerSelect.append(first);
    let found = false;
    for (const printer of list) {
      const name = String(printer?.name || '').trim();
      if (!name) continue;
      const option = document.createElement('option');
      option.value = name;
      const meta = [];
      if (printer.default) meta.push('پیش‌فرض');
      if (printer.offline) meta.push('آفلاین');
      option.textContent = meta.length ? `${name} · ${meta.join(' · ')}` : name;
      option.selected = current !== '' && name.toLocaleLowerCase() === current.toLocaleLowerCase();
      found ||= option.selected;
      printerSelect.append(option);
    }
    if (current && !found) {
      const missing = document.createElement('option');
      missing.value = current;
      missing.textContent = `${current} · در فهرست فعلی Windows دیده نشد`;
      missing.selected = true;
      printerSelect.append(missing);
    }
    printerSelect.disabled = agentId === '0';
  };

  document.querySelectorAll('[data-print-destination]').forEach((form) => {
    for (const role of ['primary', 'fallback']) {
      fillPair(form, role);
      const agentSelect = form.querySelector(`[data-print-agent-select="${role}"]`);
      const printerSelect = form.querySelector(`[data-print-printer-select="${role}"]`);
      agentSelect?.addEventListener('change', () => {
        if (printerSelect) printerSelect.dataset.currentPrinter = '';
        fillPair(form, role);
      });
      printerSelect?.addEventListener('change', () => { printerSelect.dataset.currentPrinter = printerSelect.value; });
    }
  });

  document.querySelector('[data-copy-print-token]')?.addEventListener('click', async (event) => {
    const tokenNode = document.getElementById('printAgentToken');
    const token = tokenNode?.textContent?.trim() || '';
    if (!token) return;
    try {
      await navigator.clipboard.writeText(token);
      const button = event.currentTarget;
      const old = button.textContent;
      button.textContent = 'کپی شد';
      setTimeout(() => { button.textContent = old; }, 1600);
    } catch (_) {
      const range = document.createRange();
      range.selectNodeContents(tokenNode);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);
    }
  });

  document.querySelectorAll('[data-print-editor-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('details')?.removeAttribute('open'));
  });
})();


// Read-only operational snapshot. It never replaces forms/focus/scroll or one-time token DOM.
(() => {
  const root=document.querySelector('[data-print-live-status]');if(!root)return;
  const url=root.dataset.snapshotUrl;if(!url)return;
  const fa=v=>String(v??'').replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
  let busy=false;
  const refresh=async()=>{if(busy||document.hidden)return;busy=true;try{const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});const data=await response.json().catch(()=>null);if(!response.ok||!data?.success)return;root.querySelector('[data-print-live-label]')?.replaceChildren(document.createTextNode(data.overall?.label||''));root.querySelector('[data-print-live-agents]')?.replaceChildren(document.createTextNode(`${fa(data.online_agents)}/${fa(data.agent_count)}`));root.querySelector('[data-print-live-destinations]')?.replaceChildren(document.createTextNode(`${fa(data.ready_destinations)}/${fa(data.destination_count)}`));root.querySelector('[data-print-live-problems]')?.replaceChildren(document.createTextNode(fa(data.problem_count)));root.classList.toggle('is-ok',Boolean(data.overall?.healthy));root.classList.toggle('is-warn',!data.overall?.healthy);}catch(_){}finally{busy=false;}};
  window.setInterval(refresh,15000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)void refresh();});
})();


// Mutating route operations must not be double-submitted.
(() => {
  document.querySelectorAll('form[data-print-mutation]').forEach((form) => {
    form.addEventListener('submit', () => {
      if (form.dataset.submitting === '1') return;
      form.dataset.submitting = '1';
      form.querySelectorAll('button[type="submit"],input[type="submit"]').forEach((button) => { button.disabled = true; });
    });
  });
})();

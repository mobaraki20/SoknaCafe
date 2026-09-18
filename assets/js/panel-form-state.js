/* 1.29.2 — lightweight form draft recovery for validation redirects. */
(() => {
  const forms=[...document.querySelectorAll('form[method="post"]')].filter(form=>!form.hasAttribute('data-no-form-draft')&&form.querySelector('input:not([type="hidden"]):not([type="password"]):not([type="file"]),textarea,select'));
  if(!forms.length)return;
  const signature=(form,index)=>{
    const names=[...form.elements].map(el=>el.name).filter(name=>name&&!/csrf|token|password/i.test(name)).sort().join('|');
    return `sokna-form:${location.pathname}:${form.getAttribute('action')||''}:${names}:${index}`;
  };
  const serialize=(form)=>{
    const data={};[...form.elements].forEach(el=>{if(!el.name||el.disabled||/csrf|token|password/i.test(el.name)||['file','submit','button'].includes(el.type))return;if(el.type==='checkbox'||el.type==='radio'){if(el.checked)data[el.name]=el.value||'on';else if(!(el.name in data))data[el.name]=null;}else data[el.name]=el.value;});return data;
  };
  const apply=(form,data)=>{[...form.elements].forEach(el=>{if(!el.name||!(el.name in data)||/csrf|token|password/i.test(el.name)||el.type==='file')return;const value=data[el.name];if(el.type==='checkbox'||el.type==='radio')el.checked=value!==null&&String(el.value||'on')===String(value);else el.value=value??'';el.dispatchEvent(new Event('change',{bubbles:true}));});};
  const hasError=Boolean(document.querySelector('.alert-error'));
  const hasSuccess=Boolean(document.querySelector('.alert-success'));
  forms.forEach((form,index)=>{
    const key=signature(form,index);
    if(hasSuccess)sessionStorage.removeItem(key);
    if(hasError){try{const data=JSON.parse(sessionStorage.getItem(key)||'null');if(data)apply(form,data);}catch(_){}}
    let timer;const save=()=>{clearTimeout(timer);timer=setTimeout(()=>{try{sessionStorage.setItem(key,JSON.stringify(serialize(form)));}catch(_){}},120);};
    form.addEventListener('input',save);form.addEventListener('change',save);form.addEventListener('submit',()=>{try{sessionStorage.setItem(key,JSON.stringify(serialize(form)));}catch(_){}});
  });
  if(hasError)requestAnimationFrame(()=>document.querySelector('form input:not([type="hidden"]):not([type="file"]),form textarea,form select')?.focus?.({preventScroll:false}));
})();

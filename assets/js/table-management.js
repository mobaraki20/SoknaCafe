(() => {
  const form = document.getElementById('bulkTableForm');
  const preview = document.getElementById('bulkTablePreview');
  const submit = document.getElementById('bulkTableSubmit');
  if (!form || !preview || !submit) return;
  const existing = new Set((window.SOKNA_EXISTING_TABLE_NUMBERS || []).map(Number));
  const fa = (value) => new Intl.NumberFormat('fa-IR').format(value);
  const list = (numbers) => numbers.length ? numbers.map(fa).join('، ') : '—';
  const render = () => {
    const from = Number(form.elements.from_number?.value || 0);
    const to = Number(form.elements.to_number?.value || 0);
    if (!Number.isInteger(from) || !Number.isInteger(to) || from < 1 || to < from || (to - from + 1) > 100) {
      preview.innerHTML = '<strong>بازه معتبر وارد کنید.</strong><span>حداکثر ۱۰۰ میز در هر بار ساخته می‌شود.</span>';
      preview.className = 'bulk-table-preview is-warning';
      submit.disabled = true;
      submit.textContent = 'بررسی و ساخت میزها';
      return;
    }
    const requested = Array.from({ length: to - from + 1 }, (_, index) => from + index);
    const present = requested.filter((number) => existing.has(number));
    const missing = requested.filter((number) => !existing.has(number));
    preview.innerHTML = `<div><strong>موجود از قبل</strong><span>${list(present)}</span></div><div><strong>ساخته خواهند شد</strong><span>${list(missing)}</span></div>`;
    preview.className = `bulk-table-preview ${missing.length ? 'is-ready' : 'is-warning'}`;
    submit.disabled = missing.length === 0;
    submit.textContent = missing.length ? `ساخت ${fa(missing.length)} میز جدید` : 'همه میزها موجودند';
  };
  form.addEventListener('input', render);
  render();
})();

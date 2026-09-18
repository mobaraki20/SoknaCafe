<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/includes/panel_layout.php';

require_login();
$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$audienceAccess = [
    'all' => true,
    'admin' => $isAdmin,
    'operator' => user_has_capability('orders_floor', $user) || user_has_capability('cashier_accounts', $user) || user_has_capability('shift_supervision', $user),
    'floor' => user_has_capability('orders_floor', $user),
    'preparation' => user_has_capability('preparation', $user),
    'reports' => $isAdmin,
    'inventory' => $isAdmin || user_has_capability('inventory_view', $user) || user_has_capability('inventory_cost_view', $user) || user_has_capability('inventory_operations', $user) || user_has_capability('inventory_finalize', $user) || user_has_capability('inventory_manage', $user),
];

$help = require __DIR__ . '/includes/help_topics.php';
$topics = is_array($help['topics'] ?? null) ? $help['topics'] : [];
$reviewedForVersion = (string)($help['reviewed_for_version'] ?? '—');
$visibleTopics = array_values(array_filter($topics, static function(array $topic) use ($audienceAccess): bool {
    $modules = array_values(array_filter(array_map('strval', (array)($topic['modules'] ?? []))));
    $singleModule = trim((string)($topic['module'] ?? ''));
    if ($singleModule !== '') $modules[] = $singleModule;
    foreach (array_values(array_unique($modules)) as $module) if (!sokna_module_enabled($module)) return false;
    foreach (array_values(array_unique(array_map('strval', (array)($topic['runtime_modules'] ?? [])))) as $module) {
        if ($module !== '' && !sokna_module_runtime_ready($module)) return false;
    }
    foreach (($topic['audiences'] ?? ['all']) as $audience) if (!empty($audienceAccess[$audience])) return true;
    return false;
}));
$categories = array_values(array_unique(array_column($visibleTopics, 'category')));
$featured = array_values(array_filter($visibleTopics, static fn(array $topic): bool => !empty($topic['featured'])));
panel_header('راهنمای سامانه', 'help');
?>
<section class="help-hero card">
    <div class="help-hero-copy">
        <span class="help-kicker"><?= ui_icon('info') ?> راهنمای عملیاتی نسخه <span dir="ltr"><?= e($reviewedForVersion) ?></span></span>
        <h2>کار را سریع پیدا کنید؛ در خطا بدانید قدم بعدی چیست.</h2>
        <p>نام صفحه، عملیات یا مشکلی مثل «QR»، «فاکتور»، «قطع اینترنت» یا «بازیابی» را جست‌وجو کنید.</p>
    </div>
    <label class="help-search-wrap" for="helpSearch"><?= ui_icon('search') ?><input id="helpSearch" class="form-control" type="search" placeholder="جست‌وجو در راهنمای همین نسخه…" autocomplete="off" enterkeyhint="search"></label>
</section>

<?php if ($featured): ?>
<section class="help-quick-paths" aria-label="مسیرهای سریع">
    <div class="help-section-title"><strong>مسیرهای سریع</strong><span>کارهای پرتکرار و وضعیت‌های حساس</span></div>
    <div class="help-quick-grid">
        <?php foreach(array_slice($featured,0,8) as $topic): ?>
        <a class="help-quick-card" href="?topic=<?= e(rawurlencode((string)$topic['id'])) ?>">
            <span class="help-quick-icon"><?= ui_icon('arrow-left') ?></span><span><small><?= e((string)$topic['category']) ?></small><strong><?= e((string)$topic['title']) ?></strong></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<div class="help-category-tabs horizontal-rail" id="helpCategories" aria-label="دسته‌بندی راهنما">
    <button type="button" class="active" data-help-category="all">همه</button>
    <?php foreach($categories as $category): ?><button type="button" data-help-category="<?= e($category) ?>"><?= e($category) ?></button><?php endforeach; ?>
</div>

<div class="help-results-meta"><strong id="helpResultCount"><?= fa_digits(count($visibleTopics)) ?> مطلب</strong><span>فقط راهنماهای متناسب با دسترسی این حساب نمایش داده می‌شوند.</span></div>

<section class="help-topic-grid" id="helpTopicGrid">
<?php foreach($visibleTopics as $index=>$topic):
    $body = array_values(array_map('strval', (array)($topic['body'] ?? [])));
    $searchText = ($topic['category'] ?? '').' '.($topic['title'] ?? '').' '.($topic['keywords'] ?? '').' '.implode(' ', $body);
?>
<details id="help-<?= e((string)$topic['id']) ?>" class="help-topic card" data-help-topic data-topic="<?= e((string)$topic['id']) ?>" data-category="<?= e((string)$topic['category']) ?>" data-search="<?= e($searchText) ?>" <?= $index < 1 ? 'open' : '' ?>>
    <summary><span class="help-topic-icon"><?= ui_icon('info') ?></span><span><small><?= e((string)$topic['category']) ?></small><strong><?= e((string)$topic['title']) ?></strong></span><?= ui_icon('chevron-down') ?></summary>
    <div class="help-topic-body">
        <?php foreach($body as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?>
        <?php if(!empty($topic['path']) && !empty($topic['action'])): ?><a class="btn btn-light help-topic-action" href="<?= e(asset((string)$topic['path'])) ?>"><?= e((string)$topic['action']) ?></a><?php endif; ?>
    </div>
</details>
<?php endforeach; ?>
</section>
<div class="card empty-state hidden" id="helpEmpty">مطلبی پیدا نشد. چند واژه کوتاه‌تر مثل «میز فاکتور» یا «QR نامعتبر» امتحان کنید.</div>

<script>
(() => {
  const input = document.getElementById('helpSearch');
  const topics = [...document.querySelectorAll('[data-help-topic]')];
  const buttons = [...document.querySelectorAll('[data-help-category]')];
  const count = document.getElementById('helpResultCount');
  const empty = document.getElementById('helpEmpty');
  const params = new URLSearchParams(window.location.search);
  let category = 'all';
  const digits = value => new Intl.NumberFormat('fa-IR').format(value);
  const normalize = value => String(value || '').toLocaleLowerCase('fa')
    .replace(/[يى]/g,'ی').replace(/ك/g,'ک').replace(/[ۀة]/g,'ه')
    .replace(/[٠-٩]/g,d=>'۰۱۲۳۴۵۶۷۸۹'['٠١٢٣٤٥٦٧٨٩'.indexOf(d)])
    .replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)])
    .replace(/[\u064B-\u065F\u0670]/g,'').replace(/[\u200c\u200f\u202a-\u202e]/g,' ')
    .replace(/[^\p{L}\p{N}]+/gu,' ').replace(/\s+/g,' ').trim();
  const tokens = value => normalize(value).split(' ').filter(Boolean);
  function filter() {
    const queryTokens = tokens(input.value);
    let visible = 0;
    topics.forEach(topic => {
      const inCategory = category === 'all' || topic.dataset.category === category;
      const haystack = normalize(topic.dataset.search);
      const matches = queryTokens.length === 0 || queryTokens.every(token => haystack.includes(token));
      const show = inCategory && matches;
      topic.classList.toggle('hidden', !show);
      if (show) visible += 1;
    });
    count.textContent = `${digits(visible)} مطلب`;
    empty.classList.toggle('hidden', visible !== 0);
  }
  input.value = params.get('q') || '';
  input.addEventListener('input', filter);
  buttons.forEach(button => button.addEventListener('click', () => {
    category = button.dataset.helpCategory || 'all';
    buttons.forEach(item => item.classList.toggle('active', item === button));
    filter();
  }));
  filter();
  const requestedTopic = params.get('topic');
  if (requestedTopic) {
    const target = topics.find(topic => topic.dataset.topic === requestedTopic);
    if (target) {
      input.value = ''; category = 'all';
      buttons.forEach(item => item.classList.toggle('active', item.dataset.helpCategory === 'all'));
      filter(); target.open = true;
      requestAnimationFrame(() => target.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start'}));
    }
  }
})();
</script>
<?php panel_footer(); ?>

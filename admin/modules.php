<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$user = current_user();
$actorUserId = (int)($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action !== 'toggle') throw new RuntimeException('عملیات معتبر نیست.');
        $key = trim((string)($_POST['module_key'] ?? ''));
        $desiredRaw = (string)($_POST['desired_enabled'] ?? '');
        $expectedRaw = (string)($_POST['expected_enabled'] ?? '');
        if (!in_array($desiredRaw, ['0','1'], true) || !in_array($expectedRaw, ['0','1'], true)) {
            throw new RuntimeException('وضعیت درخواستی معتبر نیست.');
        }
        // `toggleable` means the module has completed its full navigation/route/guest/background
        // contract. The manager never exposes a switch merely because a module is optional.
        if (!sokna_module_toggleable($key)) throw new RuntimeException('این قابلیت هنوز برای مدیریت مستقل آماده نشده است.');

        $pdo = db();
        $pdo->beginTransaction();
        $changed = sokna_module_set_enabled_locked(
            $pdo,
            $key,
            $desiredRaw === '1',
            $actorUserId,
            $expectedRaw === '1'
        );
        $pdo->commit();
        clear_setting_cache();
        $module = sokna_module($key) ?? [];
        $label = trim((string)($module['label'] ?? 'این قابلیت')) ?: 'این قابلیت';
        flash($changed ? 'success' : 'info', $changed
            ? ($desiredRaw === '1' ? $label . ' فعال شد.' : $label . ' غیرفعال شد.')
            : 'وضعیت این قابلیت تغییری نکرد.');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        flash('error', safe_business_error_message($e, 'تغییر وضعیت قابلیت انجام نشد.'));
    }
    redirect('modules.php');
}

$registry = sokna_module_registry();
$manageableModules = [];
$alwaysOnModules = [];
foreach ($registry as $key => $module) {
    if (sokna_module_toggleable((string)$key)) {
        $manageableModules[(string)$key] = $module;
    } elseif (!empty($module['required'])) {
        $alwaysOnModules[(string)$key] = $module;
    }
}
$enabledManageableCount = 0;
foreach (array_keys($manageableModules) as $key) {
    if (sokna_module_runtime_ready((string)$key)) $enabledManageableCount++;
}

panel_header('امکانات سامانه', 'modules');
?>
<div class="panel-page-flow modules-workspace">
  <div class="toolbar modules-toolbar">
    <div class="panel-copy-stack">
      <strong>امکانات سامانه</strong>
      <small class="muted">قابلیت‌های اختیاری را از یک محل فعال یا غیرفعال کنید؛ فقط بخش‌هایی که خاموش‌شدنشان در کل سامانه کنترل شده است اینجا کلید دارند.</small>
    </div>
  </div>

  <div class="modules-overview" aria-label="خلاصه امکانات قابل مدیریت">
    <div class="modules-overview-metric">
      <span>قابل مدیریت</span>
      <strong><?= e(fa_digits((string)count($manageableModules))) ?></strong>
    </div>
    <div class="modules-overview-metric">
      <span>فعال</span>
      <strong><?= e(fa_digits((string)$enabledManageableCount)) ?></strong>
    </div>
  </div>

  <div class="panel-helper-note modules-permission-note">
    <strong>این صفحه دسترسی کاربران را تغییر نمی‌دهد.</strong>
    <span>فعال بودن یک قابلیت با مجوز اعضای تیم فرق دارد. دسترسی هر شخص از <a href="users.php">اعضای تیم</a> مدیریت می‌شود.</span>
  </div>

  <section class="modules-section" aria-labelledby="manageableModulesTitle">
    <div class="modules-section-head">
      <div class="panel-copy-stack">
        <h2 id="manageableModulesTitle">قابلیت‌های قابل مدیریت</h2>
        <small class="muted">خاموش‌کردن، قابلیت را از کار روزانه کنار می‌گذارد؛ حذف اطلاعات یک عملیات جداگانه است.</small>
      </div>
    </div>

    <div class="modules-control-list">
      <?php foreach ($manageableModules as $key => $module):
        $configured = sokna_module_configured_enabled((string)$key);
        $enabled = sokna_module_enabled((string)$key);
        $ready = sokna_module_runtime_ready((string)$key);
        $unreadyDependencies = sokna_module_unready_dependencies((string)$key);
        $manager = is_array($module['manager'] ?? null) ? $module['manager'] : [];
        $label = trim((string)($module['label'] ?? $key));
        $context = trim((string)($manager['context'] ?? 'اختیاری'));
        $description = trim((string)($manager['description'] ?? 'قابلیت اختیاری سامانه'));
        $note = trim((string)($configured ? ($manager['active_note'] ?? '') : ($manager['disabled_note'] ?? '')));
        if ($unreadyDependencies) {
            $dependencyLabels = array_map(static fn(string $dep): string => (string)(sokna_module($dep)['label'] ?? $dep), $unreadyDependencies);
            if ($configured) {
                $note = 'برای فعال‌شدن در کار روزانه، ابتدا ' . implode(' و ', $dependencyLabels) . ' را فعال و آماده کنید.';
            } else {
                $baseDisabledNote = trim((string)($manager['disabled_note'] ?? ''));
                $note = ($baseDisabledNote !== '' ? $baseDisabledNote . ' ' : '')
                    . 'برای فعال‌کردن، ابتدا ' . implode(' و ', $dependencyLabels) . ' را فعال و آماده کنید.';
            }
        } elseif ($configured && !$ready) {
            $note = trim((string)($manager['waiting_note'] ?? ''));
            if ($note === '') $note = 'قابلیت روشن است اما برای بازگشت به عملیات روزانه هنوز نیاز به آماده‌سازی دارد.';
        }
        $impacts = is_array($manager['impacts'] ?? null) ? $manager['impacts'] : [];
        $links = is_array($manager['links'] ?? null) ? $manager['links'] : [];
        $icon = trim((string)($manager['icon'] ?? 'list')) ?: 'list';
        $configuredDependents = [];
        if ($configured) {
            foreach (sokna_module_dependents((string)$key) as $dependentKey) {
                if (!sokna_module_toggleable($dependentKey) || !sokna_module_configured_enabled($dependentKey)) continue;
                $configuredDependents[] = (string)(sokna_module($dependentKey)['label'] ?? $dependentKey);
            }
        }
        $disableConfirm = $label . ' غیرفعال شود؟ چیزی حذف نمی‌شود.';
        if ($configuredDependents) {
            $disableConfirm .= ' قابلیت وابسته «' . implode('» و «', $configuredDependents) . '» نیز غیرفعال می‌شود.';
        }
        $disableBlockers = $configured ? sokna_module_disable_preflight((string)$key) : [];
      ?>
      <article id="module-<?= e((string)$key) ?>" class="card module-control-card <?= $enabled ? 'is-enabled' : 'is-disabled' ?><?= $configured && !$ready ? ' is-waiting' : '' ?>" data-module-key="<?= e((string)$key) ?>">
        <div class="module-control-head">
          <div class="module-control-identity">
            <span class="module-control-icon" aria-hidden="true"><?= ui_icon($icon) ?></span>
            <div class="panel-copy-stack">
              <div class="module-control-title-row">
                <h3><?= e($label) ?></h3>
                <span class="module-control-context"><?= e($context) ?></span>
              </div>
              <p><?= e($description) ?></p>
            </div>
          </div>
          <?php $statusLabel = !$configured ? 'غیرفعال' : ($ready ? 'فعال' : 'نیازمند آماده‌سازی'); ?>
          <span class="panel-status-badge <?= $ready ? 'is-success' : ($configured ? 'is-warning' : 'is-muted') ?>" aria-label="وضعیت <?= e($label) ?>"><?= e($statusLabel) ?></span>
        </div>

        <div class="module-control-body">
          <?php if ($note !== ''): ?>
            <div class="settings-inline-note module-state-note"><?= e($note) ?></div>
          <?php endif; ?>

          <?php if ($impacts): ?>
            <ul class="module-impact-list" aria-label="اثر تغییر وضعیت">
              <?php foreach ($impacts as $impact): ?>
                <li><?= e((string)$impact) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if ($disableBlockers): ?>
            <div class="settings-inline-note module-disable-blocker" role="status">
              <?php foreach ($disableBlockers as $blocker): ?>
                <div><strong><?= e((string)$blocker['message']) ?></strong><?php if(trim((string)($blocker['href'] ?? '')) !== '' && trim((string)($blocker['label'] ?? '')) !== ''): ?> <a href="<?= e((string)$blocker['href']) ?>"><?= e((string)$blocker['label']) ?></a><?php endif; ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($enabled && $links): ?>
            <div class="module-control-links" aria-label="مدیریت <?= e($label) ?>">
              <?php foreach ($links as $link):
                $href = trim((string)($link['href'] ?? ''));
                $linkLabel = trim((string)($link['label'] ?? ''));
                if ($href === '' || $linkLabel === '') continue;
              ?>
                <a class="btn btn-light" href="<?= e($href) ?>"><?= e($linkLabel) ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="post" class="panel-action-bar module-control-action" <?= $configured ? 'data-confirm="' . e($disableConfirm) . '" data-confirm-title="غیرفعال‌کردن ' . e($label) . '؟" data-confirm-ok="غیرفعال‌کردن"' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="module_key" value="<?= e((string)$key) ?>">
            <input type="hidden" name="expected_enabled" value="<?= $configured ? '1' : '0' ?>">
            <input type="hidden" name="desired_enabled" value="<?= $configured ? '0' : '1' ?>">
            <?php $toggleDisabled = (!$configured && $unreadyDependencies) || ($configured && $disableBlockers); ?>
            <button class="btn <?= $configured ? 'btn-outline' : 'btn-primary' ?>" type="submit" <?= $toggleDisabled ? 'disabled aria-disabled="true" title="ابتدا موارد نیازمند رسیدگی را تعیین تکلیف کنید"' : '' ?>><?= $configured ? 'غیرفعال کردن' : 'فعال کردن' ?></button>
          </form>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card modules-core-card" aria-labelledby="alwaysOnModulesTitle">
    <div class="card-head">
      <div class="panel-copy-stack">
        <h2 id="alwaysOnModulesTitle">بخش‌های همیشه فعال</h2>
        <small class="muted">این بخش‌ها برای عملیات پایه سکنا لازم‌اند و عمداً کلید خاموش/روشن ندارند.</small>
      </div>
      <span class="panel-status-badge is-success">پایه</span>
    </div>
    <div class="card-body">
      <div class="modules-core-list">
        <?php foreach ($alwaysOnModules as $module): ?>
          <span class="modules-core-chip"><?= e((string)($module['label'] ?? '')) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</div>
<?php panel_footer(); ?>

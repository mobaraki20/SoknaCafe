<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$groupLabels = [
    'general'=>'منو و جست‌وجو',
    'cart'=>'سبد و ثبت سفارش',
    'order'=>'وضعیت سفارش',
    'waiter'=>'فراخوان گارسون',
    'operations'=>'سرویس و آماده‌سازی',
    'staff_notifications'=>'اعلان‌های کارکنان',
    'events'=>'رویدادها',
];

function message_ui_modified(string $key, array $definition): bool
{
    return customer_message($key) !== (string)$definition['default']
        || (!empty($definition['optional']) && customer_message_enabled($key) !== true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = isset($_POST['reset_one']) ? 'reset_one' : (string)($_POST['action'] ?? 'save');
    try {
        $pdo = db();
        $actorUserId = (int)(current_user()['id'] ?? 0);
        if ($action === 'reset_all') {
            $changedKeys = [];
            foreach (message_definition_map() as $key => $definition) {
                if (($definition['editable'] ?? true) !== true) continue;
                if (message_ui_modified($key,$definition)) $changedKeys[] = $key;
                $pdo->prepare('DELETE FROM settings WHERE setting_key IN (?,?)')->execute(['message.'.$key,'message_enabled.'.$key]);
            }
            if ($changedKeys) audit_log_write('guest_messages.reset','settings','guest_messages',['changed_count'=>count($changedKeys),'keys'=>$changedKeys],$actorUserId);
            flash('success','متن‌های قابل ویرایش به نسخه استاندارد سکنا برگشتند.');
        } elseif ($action === 'reset_one') {
            $key = trim((string)($_POST['reset_one'] ?? ''));
            $definition = message_definition_map()[$key] ?? null;
            if (!$definition || (($definition['editable'] ?? true) !== true)) throw new RuntimeException('این متن قابل بازنشانی نیست.');
            $wasModified = message_ui_modified($key,$definition);
            $pdo->prepare('DELETE FROM settings WHERE setting_key IN (?,?)')->execute(['message.'.$key,'message_enabled.'.$key]);
            if ($wasModified) audit_log_write('guest_message.reset','settings','guest_messages',['key'=>$key],$actorUserId);
            flash('success','متن «'.(string)$definition['label'].'» به نسخه استاندارد برگشت.');
        } elseif ($action === 'save') {
            $stmt = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            $changedKeys = [];
            foreach (message_definition_map() as $key => $definition) {
                if (($definition['editable'] ?? true) !== true) continue;
                $beforeValue = customer_message($key);
                $beforeEnabled = customer_message_enabled($key);
                $maxLength = max(80,min(1000,(int)($definition['max_length'] ?? 1000)));
                $value = text_substr(trim((string)($_POST['message'][$key] ?? $definition['default'])),0,$maxLength);
                if ($value === '') $value = (string)$definition['default'];
                $allowedTokens = array_values(array_map('strval',(array)($definition['tokens'] ?? [])));
                if (preg_match_all('/\{([A-Za-z0-9_]+)\}/u',$value,$matches)) {
                    $unknown = array_values(array_diff(array_unique($matches[1]),$allowedTokens));
                    if ($unknown) throw new RuntimeException('در متن «'.(string)$definition['label'].'» متغیر ناشناخته وجود دارد: {'.$unknown[0].'}');
                }
                if ($allowedTokens && (($definition['tokens_required'] ?? true) === true)) {
                    foreach ($allowedTokens as $token) {
                        if (!str_contains($value,'{'.$token.'}')) throw new RuntimeException('متغیر {'.$token.'} در متن «'.(string)$definition['label'].'» باید حفظ شود.');
                    }
                }
                $stmt->execute(['message.'.$key,$value]);
                $enabledBool = true;
                if (!empty($definition['optional'])) {
                    $enabledBool = isset($_POST['enabled'][$key]);
                    $stmt->execute(['message_enabled.'.$key,$enabledBool?'1':'0']);
                }
                if ($beforeValue !== $value || $beforeEnabled !== $enabledBool) $changedKeys[] = $key;
            }
            if ($changedKeys) audit_log_write('guest_messages.updated','settings','guest_messages',['changed_count'=>count($changedKeys),'keys'=>$changedKeys],$actorUserId);
            flash('success',$changedKeys ? 'تغییرات متن‌ها ذخیره شد.' : 'تغییری برای ذخیره وجود نداشت.');
        } else {
            throw new RuntimeException('عملیات متن‌ها معتبر نیست.');
        }
    } catch (Throwable $e) {
        flash('error', safe_business_error_message($e,'ذخیره متن‌ها انجام نشد.'));
    }
    redirect('messages.php');
}

$lastChanges = [];
try {
    $rows = db()->query("SELECT a.action,a.details_json,a.created_at,u.display_name actor_name FROM audit_log a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.entity_type='settings' AND a.entity_id='guest_messages' AND a.action IN('guest_messages.updated','guest_messages.reset','guest_message.reset') ORDER BY a.id DESC LIMIT 120")->fetchAll();
    foreach ($rows as $audit) {
        $details = json_decode((string)($audit['details_json'] ?? '{}'),true);
        $keys = [];
        if (is_array($details)) {
            if (!empty($details['key'])) $keys[] = (string)$details['key'];
            foreach ((array)($details['keys'] ?? []) as $key) $keys[] = (string)$key;
        }
        foreach (array_unique($keys) as $key) {
            if (!isset($lastChanges[$key])) $lastChanges[$key] = ['created_at'=>(string)$audit['created_at'],'actor_name'=>(string)($audit['actor_name'] ?: 'مدیر')];
        }
    }
} catch (Throwable $e) {
    error_log('guest messages last changes: '.$e->getMessage());
}

$editableDefinitions = [];
foreach (message_definitions() as $group => $rows) {
    $editableDefinitions[$group] = array_values(array_filter($rows,static fn(array $row): bool => (($row['editable'] ?? true) === true)));
}
$totalEditable = array_sum(array_map('count',$editableDefinitions));
$modifiedCount = 0;
foreach (message_definition_map() as $key=>$definition) if (($definition['editable']??true)===true && message_ui_modified($key,$definition)) $modifiedCount++;

panel_header('متن‌ها و اعلان‌ها','messages');
?>
<div class="panel-page-flow">
<div class="toolbar messages-v2-titlebar"><div class="panel-copy-stack"><strong>متن‌ها و اعلان‌ها</strong><small class="muted">متن‌های مهمان و اعلان کارکنان را از اینجا مدیریت کن. عنوان اقدام‌های عملیاتی ثابت می‌ماند.</small></div><a class="btn btn-light" href="settings.php#settingsGuest">بازگشت به منوی مهمان</a></div>

<section class="card messages-v2-toolbar" data-messages-toolbar>
  <div class="messages-v2-search"><label for="messageSearch">جست‌وجوی متن</label><input id="messageSearch" class="form-control" type="search" inputmode="search" enterkeyhint="search" autocomplete="off" placeholder="مثلاً بیرون‌بر، فراخوان یا ثبت سفارش" data-message-search></div>
  <div class="messages-v2-filters" role="group" aria-label="فیلتر متن‌ها">
    <button class="btn btn-sm btn-primary" type="button" data-message-filter="all">همه · <?= fa_digits($totalEditable) ?></button>
    <button class="btn btn-sm btn-light" type="button" data-message-filter="changed">تغییرکرده · <?= fa_digits($modifiedCount) ?></button>
    <button class="btn btn-sm btn-light" type="button" data-message-filter="optional">اختیاری</button>
  </div>
  <div class="messages-v2-groups" role="group" aria-label="فیلتر دسته متن‌ها"><button type="button" class="is-active" data-message-group="all">همه</button><?php foreach($groupLabels as $group=>$label): if(empty($editableDefinitions[$group]))continue; ?><button type="button" data-message-group="<?= e($group) ?>"><?= e($label) ?></button><?php endforeach; ?></div>
</section>

<div class="panel-helper-note messages-guidance"><strong>متن را ساده نگه دارید.</strong><span>متغیرهای لازم مثل <code>{items}</code> باید در متن بمانند؛ سامانه تغییر نادرست آن‌ها را هنگام ذخیره رد می‌کند.</span></div>

<form method="post" id="messagesForm"><?= csrf_field() ?>
<div class="messages-v2-list" data-message-list>
<?php foreach($editableDefinitions as $group=>$rows): foreach($rows as $row):
    $key=(string)$row['key']; $enabled=customer_message_enabled($key); $value=customer_message($key); $modified=message_ui_modified($key,$row); $maxLength=max(80,min(1000,(int)($row['max_length']??1000))); $last=$lastChanges[$key]??null;
?>
<section class="message-editor message-editor-v2 <?= !$enabled?'is-disabled':'' ?>" data-message-card data-group="<?= e($group) ?>" data-changed="<?= $modified?'1':'0' ?>" data-optional="<?= !empty($row['optional'])?'1':'0' ?>" data-search-text="<?= e(mb_strtolower((string)$row['label'].' '.(string)($row['where']??'').' '.(string)$row['default'])) ?>">
  <div class="message-editor-head">
    <div><label for="msg-<?= e($key) ?>"><?= e((string)$row['label']) ?></label><span class="message-key" dir="ltr"><?= e($key) ?></span></div>
    <div class="message-editor-state"><?php if($modified): ?><span class="badge badge-pending">تغییرکرده</span><?php endif; ?><?php if($row['optional']): ?><label class="switch-label"><input type="checkbox" name="enabled[<?= e($key) ?>]" <?= $enabled?'checked':'' ?> data-message-enabled> نمایش داده شود</label><?php else: ?><span class="badge">ضروری</span><?php endif; ?></div>
  </div>
  <textarea id="msg-<?= e($key) ?>" class="form-control" name="message[<?= e($key) ?>]" maxlength="<?= $maxLength ?>" data-message-input><?= e($value) ?></textarea>
  <div class="message-editor-meta"><span><strong>محل نمایش:</strong> <?= e((string)($row['where']??'—')) ?></span><span data-message-count><?= fa_digits(text_length($value)) ?> / <?= fa_digits($maxLength) ?></span></div>
  <?php if(!empty($row['tokens'])): ?><div class="message-token-row"><span>متغیرها:</span><?php foreach($row['tokens'] as $token): ?><button type="button" class="message-token" data-insert-token="<?= e((string)$token) ?>" aria-label="افزودن متغیر <?= e((string)$token) ?>">{<?= e((string)$token) ?>}</button><?php endforeach; ?></div><?php endif; ?>
  <div class="message-preview" aria-label="پیش‌نمایش متن"><small>پیش‌نمایش</small><p data-message-preview><?= e($value) ?></p></div>
  <div class="message-default"><span><strong>متن استاندارد:</strong> <?= e((string)$row['default']) ?></span><?php if($last): ?><small>آخرین تغییر: <?= e(format_jalali_compact((string)$last['created_at'])) ?> · <?= e((string)$last['actor_name']) ?></small><?php endif; ?></div>
  <div class="message-editor-actions"><?php if($modified): ?><button class="btn btn-sm btn-light" type="submit" name="reset_one" value="<?= e($key) ?>" data-click-confirm="ویرایش این متن کنار گذاشته می‌شود و متن استاندارد سکنا برمی‌گردد." data-confirm-title="بازگردانی این متن؟" data-confirm-ok="بازگردانی">بازگردانی همین متن</button><?php endif; ?></div>
</section>
<?php endforeach; endforeach; ?>
</div>
<div class="empty-state messages-v2-empty" data-message-empty hidden><strong>متنی با این فیلتر پیدا نشد.</strong><span>عبارت جست‌وجو یا فیلتر را تغییر دهید.</span></div>
<details class="messages-reset-control"><summary>بازنشانی همه متن‌ها</summary><div><p>این عملیات فقط متن‌های قابل ویرایش را به نسخه استاندارد سکنا برمی‌گرداند و متن‌های ثابت امنیتی/مالی را تغییر نمی‌دهد.</p><button class="btn btn-danger" name="action" value="reset_all" data-click-confirm="همه ویرایش‌های متن‌های قابل تغییر کنار گذاشته می‌شوند و نسخه استاندارد سکنا برمی‌گردد." data-confirm-title="بازگردانی همه متن‌ها؟" data-confirm-ok="بازگردانی همه" data-confirm-danger="1">بازگردانی همه متن‌ها</button></div></details>
<div class="sticky-save messages-save-bar"><span class="muted"><?= fa_digits($modifiedCount) ?> متن با نسخه استاندارد تفاوت دارد.</span><button class="btn btn-primary" name="action" value="save">ذخیره تغییرات</button></div>
</form>
</div>
<?php panel_footer('<script defer src="'.e(asset('assets/js/messages-admin.js')).'"></script>'); ?>

<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
$soknaRuntimeReady = PHP_VERSION_ID >= 80200 && extension_loaded('pdo_mysql') && extension_loaded('fileinfo') && extension_loaded('openssl') && extension_loaded('sodium') && extension_loaded('mbstring');
header('X-Sokna-Runtime: ' . ($soknaRuntimeReady ? 'php-ready' : 'php-incompatible'));
if (is_logged_in()) redirect(user_home_path());
$next = safe_local_redirect_target($_GET['next'] ?? $_POST['next'] ?? '', '');
$error = '';
$restored = (string)($_GET['restored'] ?? '') === '1';
$users = [];
try {
    $users = db()->query("SELECT id,display_name FROM users WHERE active=1 ORDER BY display_name,id")->fetchAll();
} catch (Throwable) {
    $users = [];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $userId = (int)($_POST['user_id'] ?? 0);
    $password = (string)($_POST['password'] ?? '');
    $rate = auth_login_rate_status($userId);
    if (!$rate['allowed']) {
        $minutes = max(1, (int)ceil(((int)$rate['retry_after']) / 60));
        $error = 'تلاش‌های ناموفق زیادی ثبت شده است؛ حدود ' . fa_digits((string)$minutes) . ' دقیقه دیگر دوباره امتحان کنید.';
    } else {
        $username = '';
        if ($userId > 0) {
            $stmt = db()->prepare('SELECT username FROM users WHERE id=? AND active=1 LIMIT 1');
            $stmt->execute([$userId]);
            $username = (string)($stmt->fetchColumn() ?: '');
        }
        if ($username !== '' && login($username, $password)) {
            auth_login_rate_clear($userId);
            $destination = $next !== '' ? safe_local_redirect_target($next, user_home_path()) : user_home_path();
            redirect($destination);
        }
        auth_login_rate_fail($userId);
        $rate = auth_login_rate_status($userId);
        if (!$rate['allowed']) {
            $minutes = max(1, (int)ceil(((int)$rate['retry_after']) / 60));
            $error = 'تلاش‌های ناموفق زیادی ثبت شده است؛ حدود ' . fa_digits((string)$minutes) . ' دقیقه دیگر دوباره امتحان کنید.';
        } else {
            $error = 'نام انتخاب‌شده یا رمز عبور درست نیست.';
        }
    }
}
$font = ui_font();
$primaryColor = valid_hex_color(setting('primary_color', '#365b4c'), '#365b4c');
$accentColor = valid_hex_color(setting('accent_color', '#b85c38'), '#b85c38');
$backgroundColor = valid_hex_color(setting('background_color', '#f7f3ec'), '#f7f3ec');
$postedUserId = (int)($_POST['user_id'] ?? 0);
$postedUserName = '';
foreach ($users as $candidate) {
    if ((int)$candidate['id'] === $postedUserId) {
        $postedUserName = (string)$candidate['display_name'];
        break;
    }
}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ورود | <?= e(setting('cafe_name','سامانه کافه')) ?></title><?= favicon_head_tags() ?><?= ui_font_head($font) ?><link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/scds-foundation.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/responsive.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/panel.css')) ?>"><style>:root{--font-ui:<?= ui_font_family($font) ?>;--primary:<?= e($primaryColor) ?>;--accent:<?= e($accentColor) ?>;--app-bg:<?= e($backgroundColor) ?>;--on-primary:<?= e(contrast_text_color($primaryColor)) ?>}</style></head>
<body class="login-page sokna-login-v19 font-<?= e($font) ?>">
<main class="login-shell-v19">
    <section class="login-story-v19" aria-label="معرفی سامانه">
        <div class="login-story-brand"><div class="login-story-mark"><?php if(setting('logo_path')): ?><img src="<?= e(asset(setting('logo_path'))) ?>" alt=""><?php else: ?><?= ui_icon('coffee') ?><?php endif; ?></div><div><strong><?= e(setting('cafe_name','سامانه کافه')) ?></strong><small>فضای کاری تیم سکنا</small></div></div>
        <div class="login-story-copy"><span>یک مسیر، از میز تا تحویل</span><h1>عملیات روشن‌تر، سرویس آرام‌تر.</h1><p>سفارش‌ها، میزها و فراخوان‌های مهمان در یک فضای مشترک و هماهنگ با منوی سکنا.</p></div>
        <div class="login-story-points"><span><?= ui_icon('table') ?> وضعیت میزها</span><span><?= ui_icon('operations') ?> جریان سفارش</span><span><?= ui_icon('service') ?> هماهنگی تیم</span></div>
    </section>
    <section class="login-card">
        <div class="login-logo"><img src="<?= e(favicon_url(192)) ?>" alt="نشان سکنا" width="64" height="64"></div>
        <span class="login-kicker">ورود اعضای تیم</span><h1>خوش آمدید</h1><p>حساب خود را انتخاب کنید و وارد فضای کاری شوید.</p>
        <?php if($restored): ?><div class="alert alert-success">بازیابی با موفقیت کامل شد. برای ادامه با یکی از حساب‌های بازیابی‌شده وارد شوید.</div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="login-form" id="loginForm" novalidate><?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
            <?php if($users): ?>
                <label for="loginAccountTrigger">نام و نام خانوادگی</label>
                <button class="login-account-trigger" type="button" id="loginAccountTrigger" aria-haspopup="dialog" aria-controls="loginAccountModal" aria-expanded="false">
                    <span>حساب ورود</span><strong id="loginAccountSelected"><?= $postedUserName !== '' ? e($postedUserName) : 'انتخاب حساب' ?></strong><span aria-hidden="true"><?= ui_icon('chevron-down') ?></span>
                </button>
                <p class="inline-form-error hidden" id="loginAccountError" role="alert">یک حساب را انتخاب کنید.</p>
            <?php else: ?><div class="alert alert-error">کاربر فعالی برای ورود پیدا نشد.</div><?php endif; ?>
            <label for="loginPassword">رمز عبور</label>
            <input class="form-control" id="loginPassword" type="password" name="password" autocomplete="current-password">
            <p class="inline-form-error hidden" id="loginPasswordError" role="alert">رمز عبور را وارد کنید.</p>
            <button class="btn btn-primary btn-block" type="submit" <?= !$users?'disabled':'' ?>>ورود به سامانه</button>

            <?php if($users): ?>
            <div class="login-account-modal hidden" id="loginAccountModal" role="dialog" aria-modal="true" aria-labelledby="loginAccountTitle">
                <div class="login-account-backdrop" data-login-account-close aria-hidden="true"></div>
                <section class="login-account-sheet" role="document">
                    <header class="login-account-head">
                        <div><h2 id="loginAccountTitle">انتخاب حساب</h2><small>نام خود را از فهرست انتخاب کنید.</small></div>
                        <button class="login-account-close" type="button" data-login-account-close aria-label="بستن"><?= ui_icon('close') ?></button>
                    </header>
                    <div class="login-account-list" role="radiogroup" aria-label="حساب‌های فعال">
                    <?php foreach($users as $user): ?>
                        <label class="login-account-option <?= $postedUserId === (int)$user['id'] ? 'is-selected' : '' ?>" data-user-name="<?= e((string)$user['display_name']) ?>">
                            <input type="radio" name="user_id" value="<?= (int)$user['id'] ?>" <?= $postedUserId === (int)$user['id'] ? 'checked' : '' ?>>
                            <span><?= e((string)$user['display_name']) ?></span><i aria-hidden="true"><?= ui_icon('check') ?></i>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </section>
            </div>
            <?php endif; ?>
        </form>
        <small class="login-footnote">سامانه داخلی مجموعه · زمان مرجع Asia/Tehran</small>
    </section>
</main>
<?php if($users): ?>
<script src="<?= e(asset('assets/js/interaction-modality.js')) ?>"></script>
<script>
(() => {
 const modal=document.getElementById('loginAccountModal');
 const trigger=document.getElementById('loginAccountTrigger');
 const selected=document.getElementById('loginAccountSelected');
 const form=document.getElementById('loginForm');
 const password=document.getElementById('loginPassword');
 const accountError=document.getElementById('loginAccountError');
 const passwordError=document.getElementById('loginPasswordError');
 const options=[...modal.querySelectorAll('.login-account-option')];
 const radios=[...modal.querySelectorAll('input[name="user_id"]')];
 const storageKey='sokna.login.lastUserId';
 const setSelected=(radio,close=true)=>{
   if(!radio)return;
   radio.checked=true;
   options.forEach(row=>row.classList.toggle('is-selected',row.contains(radio)));
   const row=radio.closest('.login-account-option');
   selected.textContent=row?.dataset.userName||'انتخاب حساب';
   accountError.classList.add('hidden');
   try{localStorage.setItem(storageKey,radio.value);}catch(_e){}
   if(close)closeModal();
 };
 const interaction=window.CafeUI?.interaction||window.SoknaInteraction;
 const openModal=()=>{modal.classList.remove('hidden');document.body.classList.add('no-scroll');trigger.setAttribute('aria-expanded','true');requestAnimationFrame(()=>{const row=modal.querySelector('input:checked')?.closest('.login-account-option')||options[0];row?.scrollIntoView({block:'nearest'});if(interaction?.isKeyboard?.())row?.querySelector('input')?.focus({preventScroll:true});else trigger.blur();});};
 const closeModal=()=>{modal.classList.add('hidden');document.body.classList.remove('no-scroll');trigger.setAttribute('aria-expanded','false');if(!(interaction?.restoreFocus?.(trigger)))interaction?.clearPointerFocus?.(modal);};
 trigger.addEventListener('click',openModal);
 modal.querySelectorAll('[data-login-account-close]').forEach(button=>button.addEventListener('click',closeModal));
 radios.forEach(radio=>radio.addEventListener('change',()=>setSelected(radio,true)));
 document.addEventListener('keydown',event=>{
   if(modal.classList.contains('hidden'))return;
   if(event.key==='Escape'){event.preventDefault();closeModal();return;}
   if(event.key!=='Tab')return;
   const focusables=[...modal.querySelectorAll('input:not(:disabled),button:not(:disabled)')].filter(el=>el.offsetParent!==null);
   if(!focusables.length)return;
   const first=focusables[0],last=focusables[focusables.length-1];
   if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
   else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
 });
 if(!radios.some(r=>r.checked)){
   try{const last=localStorage.getItem(storageKey);const match=radios.find(r=>r.value===last);if(match)setSelected(match,false);}catch(_e){}
 }
 form.addEventListener('submit',event=>{
   const account=radios.find(r=>r.checked);
   const passwordMissing=password.value==='';
   accountError.classList.toggle('hidden',!!account);
   passwordError.classList.toggle('hidden',!passwordMissing);
   if(!account||passwordMissing){event.preventDefault();if(!account){openModal();}else{password.focus();}}
 });
 password.addEventListener('input',()=>{if(password.value!=='')passwordError.classList.add('hidden');});
})();
</script>
<?php endif; ?>
</body></html>

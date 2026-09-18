<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('inventory');
require_login();
if (!user_can_inventory_manage()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';
$pdo=db(); inventory_seed_if_empty($pdo); $userId=(int)(current_user()['id']??0);
if(inventory_initialized()){flash('warning','موجودی اولیه قبلاً نهایی شده است؛ برای به‌روزرسانی از شمارش دوره‌ای استفاده کن.');redirect('inventory.php?tab=counts');}
$openCount=inventory_open_count_session($pdo);
if($openCount)redirect('inventory_count.php?id='.(int)$openCount['id']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf($_POST['csrf_token']??null);
 try{$pdo->beginTransaction();$id=inventory_count_start($pdo,'موجودی اولیه','opening',$userId);$pdo->commit();redirect('inventory_count.php?id='.$id.'&started=1');}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('inventory opening: '.$e->getMessage());flash('error',safe_business_error_message($e,'شروع موجودی اولیه انجام نشد.'));}
}
panel_header('راه‌اندازی موجودی اولیه','inventory');
?>
<section class="card inventory-review-card"><div class="card-body"><h2>موجودی واقعی امروز را ثبت کن</h2><p>این عملیات فقط یک‌بار برای شروع انبار انجام می‌شود و خرید ساختگی ایجاد نمی‌کند.</p><p class="muted">برای هر کالا مقدار واقعی را وارد می‌کنی. ارزش تقریبی اولیه اختیاری است؛ قیمت نامشخص صفر فرض نمی‌شود. می‌توانی ذخیره کنی و بعداً ادامه بدهی.</p><form method="post" class="form-grid"><?= csrf_field() ?><div class="form-group full actions"><button class="btn btn-primary">شروع موجودی اولیه</button><a class="btn btn-light" href="inventory.php">انصراف</a></div></form></div></section>
<?php panel_footer(); ?>

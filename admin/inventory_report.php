<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
sokna_module_require('reporting');
sokna_module_require_runtime_ready('inventory');
require dirname(__DIR__) . '/includes/panel_layout.php';
require_once dirname(__DIR__) . '/includes/reporting.php';
require_once dirname(__DIR__) . '/includes/xlsx_export.php';

function inventory_report_scalar(PDO $pdo,string $sql,array $params=[]):mixed{$st=$pdo->prepare($sql);$st->execute($params);$v=$st->fetchColumn();return $v===false?null:$v;}
function inventory_report_rows(PDO $pdo,string $sql,array $params=[]):array{$st=$pdo->prepare($sql);$st->execute($params);return $st->fetchAll();}
function inventory_report_pct(float $value):string{return fa_digits(rtrim(rtrim(number_format($value,1,'.',''),'0'),'.')).'٪';}

$pdo=db();
$range=report_range_resolve('30');
$fromBusiness=(string)$range['from'];$toBusiness=(string)$range['to'];
[$physicalStart,$physicalEnd]=business_date_range_bounds($fromBusiness,$toBusiness);
$from=$physicalStart->format('Y-m-d H:i:s');$to=$physicalEnd->format('Y-m-d H:i:s');
$validSettlement=report_valid_settlement_sql('sr');
$saleLineSource=report_settlement_line_source_sql('sale');
$warning=(string)$range['error'];
$netSales=$knownCogs=$coveredCogs=$purchaseSpend=$wasteCost=$coveredRevenue=$itemRevenue=0;
$consumptionRows=$itemRows=$departmentRows=$purchaseDepartmentRows=$countVarianceRows=[];$countSessionCount=$countVarianceCount=0;$consumptionSql=$itemSql=$countVarianceSql='';
try{
    $params=[$fromBusiness,$toBusiness];
    $netSales=(int)(inventory_report_scalar($pdo,"SELECT COALESCE(SUM(sr.total-sr.tax_amount),0) FROM settlement_records sr WHERE $validSettlement AND sr.business_date>=? AND sr.business_date<=?",$params)??0);
    $itemRevenue=(int)(inventory_report_scalar($pdo,"SELECT COALESCE(SUM(sale.net_amount),0) FROM settlement_records sr JOIN $saleLineSource ON sale.settlement_id=sr.id WHERE $validSettlement AND sr.business_date>=? AND sr.business_date<=?",$params)??0);
    $costSql="SELECT m.source_id order_item_id,
        -SUM(COALESCE(m.total_cost_delta,0)+COALESCE((SELECT SUM(r.total_cost_delta) FROM inventory_movements r WHERE r.reversal_of_id=m.id),0)) known_cogs,
        SUM(m.total_cost_delta IS NULL) unknown_components,COUNT(*) component_count
      FROM inventory_movements m
      WHERE m.movement_type='recipe_consumption' AND m.source_type='order_item'
      GROUP BY m.source_id";
    $knownCogs=(int)(inventory_report_scalar($pdo,"SELECT COALESCE(SUM(ROUND(c.known_cogs*sale.quantity/NULLIF(oi.quantity,0))),0) FROM settlement_records sr JOIN $saleLineSource ON sale.settlement_id=sr.id JOIN order_items oi ON oi.id=sale.order_item_id JOIN ($costSql) c ON CAST(c.order_item_id AS UNSIGNED)=oi.id WHERE $validSettlement AND sr.business_date>=? AND sr.business_date<=?",$params)??0);
    $coveredRevenue=(int)(inventory_report_scalar($pdo,"SELECT COALESCE(SUM(CASE WHEN c.component_count>0 AND c.unknown_components=0 THEN sale.net_amount ELSE 0 END),0) FROM settlement_records sr JOIN $saleLineSource ON sale.settlement_id=sr.id JOIN order_items oi ON oi.id=sale.order_item_id LEFT JOIN ($costSql) c ON CAST(c.order_item_id AS UNSIGNED)=oi.id WHERE $validSettlement AND sr.business_date>=? AND sr.business_date<=?",$params)??0);
    $coveredCogs=(int)(inventory_report_scalar($pdo,"SELECT COALESCE(SUM(CASE WHEN c.component_count>0 AND c.unknown_components=0 THEN ROUND(c.known_cogs*sale.quantity/NULLIF(oi.quantity,0)) ELSE 0 END),0) FROM settlement_records sr JOIN $saleLineSource ON sale.settlement_id=sr.id JOIN order_items oi ON oi.id=sale.order_item_id LEFT JOIN ($costSql) c ON CAST(c.order_item_id AS UNSIGNED)=oi.id WHERE $validSettlement AND sr.business_date>=? AND sr.business_date<=?",$params)??0);
    $purchaseReceiptSql="SELECT m.id,m.department,m.occurred_at,CASE WHEN m.total_cost_delta IS NULL AND NOT EXISTS(SELECT 1 FROM inventory_movements ca WHERE ca.correction_of_id=m.id AND ca.movement_type='cost_adjustment') THEN NULL ELSE COALESCE(m.total_cost_delta,0)+COALESCE((SELECT SUM(ca.total_cost_delta) FROM inventory_movements ca WHERE ca.correction_of_id=m.id AND ca.movement_type='cost_adjustment'),0) END corrected_total FROM inventory_movements m WHERE m.movement_type='purchase_receive'";
    $purchaseSpend=(int)(inventory_report_scalar($pdo,"SELECT COALESCE(SUM(corrected_total),0) FROM ($purchaseReceiptSql) p WHERE p.occurred_at>=? AND p.occurred_at<? AND p.corrected_total IS NOT NULL",[$from,$to])??0);
    $purchaseSpend+=(int)(inventory_report_scalar($pdo,"SELECT COALESCE(SUM(total_cost_delta),0) FROM inventory_movements WHERE movement_type='purchase_return' AND occurred_at>=? AND occurred_at<? AND total_cost_delta IS NOT NULL",[$from,$to])??0);
    $wasteCost=abs((int)(inventory_report_scalar($pdo,"SELECT COALESCE(SUM(total_cost_delta),0) FROM inventory_movements WHERE movement_type='waste' AND occurred_at>=? AND occurred_at<? AND total_cost_delta IS NOT NULL",[$from,$to])??0));
    $consumptionSql="SELECT i.name,i.base_unit,COALESCE(m.department,'shared') department,
        -SUM(ROUND((m.quantity_base+COALESCE((SELECT SUM(r.quantity_base) FROM inventory_movements r WHERE r.reversal_of_id=m.id),0))*sale.quantity/NULLIF(oi.quantity,0))) consumed_quantity,
        -SUM(ROUND((COALESCE(m.total_cost_delta,0)+COALESCE((SELECT SUM(r.total_cost_delta) FROM inventory_movements r WHERE r.reversal_of_id=m.id),0))*sale.quantity/NULLIF(oi.quantity,0))) known_cost,
        SUM(m.total_cost_delta IS NULL) unknown_count
      FROM settlement_records sr JOIN $saleLineSource ON sale.settlement_id=sr.id JOIN order_items oi ON oi.id=sale.order_item_id
      JOIN inventory_movements m ON m.source_type='order_item' AND oi.id=CAST(m.source_id AS UNSIGNED) JOIN inventory_items i ON i.id=m.inventory_item_id
      WHERE m.movement_type='recipe_consumption' AND $validSettlement AND sr.business_date>=? AND sr.business_date<=?
      GROUP BY i.id,i.name,i.base_unit,COALESCE(m.department,'shared') HAVING consumed_quantity<>0 ORDER BY known_cost DESC,consumed_quantity DESC";
    $consumptionRows=inventory_report_rows($pdo,$consumptionSql.' LIMIT 30',$params);
    $itemSql="SELECT MAX(sale.item_name) item_name,SUM(sale.quantity) qty,SUM(sale.net_amount) revenue,COALESCE(SUM(ROUND(c.known_cogs*sale.quantity/NULLIF(oi.quantity,0))),0) known_cogs,
        SUM(CASE WHEN c.component_count IS NULL OR c.component_count=0 OR c.unknown_components>0 THEN sale.net_amount ELSE 0 END) uncovered_revenue
      FROM settlement_records sr JOIN $saleLineSource ON sale.settlement_id=sr.id JOIN order_items oi ON oi.id=sale.order_item_id LEFT JOIN ($costSql) c ON CAST(c.order_item_id AS UNSIGNED)=oi.id
      WHERE $validSettlement AND sr.business_date>=? AND sr.business_date<=?
      GROUP BY CASE WHEN sale.item_id IS NULL THEN CONCAT('snapshot:',sale.item_name,'|',sale.unit_price) ELSE CONCAT('id:',sale.item_id) END
      ORDER BY revenue DESC";
    $itemRows=inventory_report_rows($pdo,$itemSql.' LIMIT 20',$params);
    $departmentRows=inventory_report_rows($pdo,"SELECT COALESCE(m.department,'shared') department,-SUM(ROUND((COALESCE(m.total_cost_delta,0)+COALESCE((SELECT SUM(r.total_cost_delta) FROM inventory_movements r WHERE r.reversal_of_id=m.id),0))*sale.quantity/NULLIF(oi.quantity,0))) known_cost FROM settlement_records sr JOIN $saleLineSource ON sale.settlement_id=sr.id JOIN order_items oi ON oi.id=sale.order_item_id JOIN inventory_movements m ON m.movement_type='recipe_consumption' AND m.source_type='order_item' AND oi.id=CAST(m.source_id AS UNSIGNED) WHERE $validSettlement AND sr.business_date>=? AND sr.business_date<=? GROUP BY COALESCE(m.department,'shared') ORDER BY known_cost DESC",$params);
    $purchaseDepartmentRows=inventory_report_rows($pdo,"SELECT department,SUM(total) total FROM (SELECT COALESCE(p.department,'shared') department,p.corrected_total total FROM ($purchaseReceiptSql) p WHERE p.occurred_at>=? AND p.occurred_at<? AND p.corrected_total IS NOT NULL UNION ALL SELECT COALESCE(department,'shared') department,total_cost_delta total FROM inventory_movements WHERE movement_type='purchase_return' AND occurred_at>=? AND occurred_at<? AND total_cost_delta IS NOT NULL) x GROUP BY department ORDER BY total DESC",[$from,$to,$from,$to]);
    $countSessionCount=(int)(inventory_report_scalar($pdo,"SELECT COUNT(*) FROM inventory_count_sessions WHERE session_type='periodic' AND status='finalized' AND finalized_at>=? AND finalized_at<?",[$from,$to])??0);
    $countVarianceCount=(int)(inventory_report_scalar($pdo,"SELECT COUNT(*) FROM inventory_count_lines l JOIN inventory_count_sessions s ON s.id=l.session_id WHERE s.session_type='periodic' AND s.status='finalized' AND s.finalized_at>=? AND s.finalized_at<? AND l.difference_base<>0",[$from,$to])??0);
    $countVarianceSql="SELECT s.title,s.finalized_at,i.name,i.base_unit,l.system_quantity_snapshot,l.actual_quantity,l.difference_base FROM inventory_count_lines l JOIN inventory_count_sessions s ON s.id=l.session_id JOIN inventory_items i ON i.id=l.inventory_item_id WHERE s.session_type='periodic' AND s.status='finalized' AND s.finalized_at>=? AND s.finalized_at<? AND l.difference_base<>0 ORDER BY s.finalized_at DESC,ABS(l.difference_base) DESC,l.id DESC";
    $countVarianceRows=inventory_report_rows($pdo,$countVarianceSql.' LIMIT 20',[$from,$to]);
}catch(Throwable $e){
    if($warning==='')$warning='بخشی از هزینه مواد قابل محاسبه نیست. ثبت قیمت خرید و سابقه موجودی را بررسی کنید.';
    error_log('inventory report: '.$e->getMessage());
}
$coverage=$itemRevenue>0?min(100,max(0,$coveredRevenue*100/$itemRevenue)):0.0;
$coveredNetSales=$itemRevenue>0?(int)round($netSales*($coveredRevenue/$itemRevenue)):0;
$grossProfit=$coveredNetSales-$coveredCogs;
$margin=$coveredNetSales>0?$grossProfit*100/$coveredNetSales:0.0;

if(($_GET['action']??'')==='export'){
    // UI keeps long comparisons compact, but Excel must contain the complete applied range.
    $exportConsumptionRows=$consumptionSql!==''?inventory_report_rows($pdo,$consumptionSql,$params):[];
    $exportItemRows=$itemSql!==''?inventory_report_rows($pdo,$itemSql,$params):[];
    $exportVarianceRows=$countVarianceSql!==''?inventory_report_rows($pdo,$countVarianceSql,[$from,$to]):[];
    $summary=[
        [xlsx_cell('انبار و سود سکنا','title',3)],
        [xlsx_cell('بازه: '.$range['label'],'meta',3)],
        [xlsx_cell('شاخص','header'),xlsx_cell('مقدار','header'),xlsx_cell('توضیح','header')],
        ['فروش پس از تخفیف (پیش از مالیات)',xlsx_cell($netSales,'money'),'کل فروش تسویه‌شده معتبر در بازه'],
        ['فروش دارای هزینه کامل',xlsx_cell($coveredNetSales,'money'),'بخشی از فروش که مواد مصرفی و هزینه آن کامل ثبت شده است'],
        ['هزینه مواد همان فروش',xlsx_cell($coveredCogs,'money'),'فقط برای فروش دارای پوشش کامل'],
        ['سود ناخالص تقریبی همان فروش',xlsx_cell($grossProfit,'money'),'هزینه‌های حقوق، اجاره و سربار در آن نیست'],
        ['حاشیه سود ناخالص',xlsx_cell($margin/100,'percent'),'برای فروش دارای هزینه کامل'],
        ['پوشش محاسبه هزینه',xlsx_cell($coverage/100,'percent'),'درصد درآمد آیتم‌هایی که مواد مصرفی و هزینه آن‌ها کامل ثبت شده است'],
        ['ضایعات ثبت‌شده',xlsx_cell($wasteCost,'money'),'ضایعات قیمت‌دار ثبت‌شده در بازه'],
        ['خرید خالص ثبت‌شده',xlsx_cell($purchaseSpend,'money'),'خریدها پس از اصلاح قیمت و برگشت خرید؛ معادل هزینه مواد فروخته‌شده نیست'],
        ['شمارش دوره‌ای',xlsx_cell($countSessionCount,'integer'),$countVarianceCount.' قلم مغایرت ثبت‌شده در بازه'],
    ];
    $consRows=[[xlsx_cell('مصرف مواد','title',5)],[xlsx_cell('بازه: '.$range['label'],'meta',5)],[xlsx_cell('ماده','header'),xlsx_cell('بخش','header'),xlsx_cell('مصرف','header'),xlsx_cell('هزینه ثبت‌شده','header'),xlsx_cell('وضعیت هزینه','header')]];
    foreach($exportConsumptionRows as $r)$consRows[]=[(string)$r['name'],inventory_department_labels()[(string)$r['department']]??'مشترک',inventory_format_quantity((int)$r['consumed_quantity'],(string)$r['base_unit']),xlsx_cell((int)$r['known_cost'],'money'),(int)$r['unknown_count']>0?'ناقص':'کامل'];
    $menuRows=[[xlsx_cell('سود تقریبی آیتم‌های منو','title',5)],[xlsx_cell('درآمد آیتم پس از تخصیص تخفیف و پیش از مالیات است؛ برای مقایسه آیتم‌ها استفاده شود.','meta',5)],[xlsx_cell('آیتم','header'),xlsx_cell('تعداد','header'),xlsx_cell('درآمد','header'),xlsx_cell('هزینه مواد','header'),xlsx_cell('سود ناخالص','header')]];
    foreach($exportItemRows as $r){$complete=(int)$r['uncovered_revenue']===0;$profit=(int)$r['revenue']-(int)$r['known_cogs'];$menuRows[]=[(string)$r['item_name'],xlsx_cell((int)$r['qty'],'integer'),xlsx_cell((int)$r['revenue'],'money'),$complete?xlsx_cell((int)$r['known_cogs'],'money'):xlsx_cell('ناقص','warning'),$complete?xlsx_cell($profit,'money'):xlsx_cell('—','warning')];}
    $varianceRows=[[xlsx_cell('مغایرت شمارش دوره‌ای','title',6)],[xlsx_cell('بازه: '.$range['label'],'meta',6)],[xlsx_cell('شمارش','header'),xlsx_cell('زمان نهایی‌سازی','header'),xlsx_cell('کالا','header'),xlsx_cell('موجودی سیستم','header'),xlsx_cell('شمارش واقعی','header'),xlsx_cell('مغایرت','header')]];
    foreach($exportVarianceRows as $r)$varianceRows[]=[(string)$r['title'],format_jalali_compact((string)$r['finalized_at']),(string)$r['name'],inventory_format_quantity((int)$r['system_quantity_snapshot'],(string)$r['base_unit']),inventory_format_quantity((int)$r['actual_quantity'],(string)$r['base_unit']),inventory_format_quantity((int)$r['difference_base'],(string)$r['base_unit'])];
    xlsx_download('sokna-inventory-profit.xlsx',[
        ['name'=>'خلاصه','rows'=>$summary,'widths'=>[28,22,55],'freeze_row'=>3],
        ['name'=>'مصرف مواد','rows'=>$consRows,'widths'=>[30,18,20,24,18],'freeze_row'=>3,'auto_filter'=>'A3:E'.max(3,count($consRows))],
        ['name'=>'آیتم‌های منو','rows'=>$menuRows,'widths'=>[30,14,22,22,22],'freeze_row'=>3,'auto_filter'=>'A3:E'.max(3,count($menuRows))],
        ['name'=>'مغایرت شمارش','rows'=>$varianceRows,'widths'=>[28,24,28,20,20,20],'freeze_row'=>3,'auto_filter'=>'A3:F'.max(3,count($varianceRows))],
    ]);
}

panel_header('انبار و سود','inventory_report');
?>
<div class="panel-surface-stack">
<section class="report-shell"><div class="report-shell-head"><div><strong>هزینه و سود ناخالص</strong><small>فروش تسویه‌شده و هزینه مواد مصرفی در همان بازه؛ این گزارش سود خالص کسب‌وکار نیست.</small></div><span class="report-range-summary"><?= e((string)$range['label']) ?></span></div><form method="get" class="report-filter-grid"><?php report_render_range_fields($range,'inventoryReport'); ?><div class="report-filter-actions"><button class="btn btn-primary">نمایش</button><button class="btn btn-light" name="action" value="export">خروجی Excel</button></div></form></section>
<?php if($warning): ?><div class="alert alert-warning"><?= e($warning) ?></div><?php endif; ?>
<div class="metric-grid compact-kpi-grid inventory-report-kpis"><article class="metric-card"><span>فروش پس از تخفیف (پیش از مالیات)</span><strong><?= e(toman_number($netSales)) ?></strong></article><article class="metric-card"><span>فروش دارای هزینه کامل</span><strong><?= e(toman_number($coveredNetSales)) ?></strong><small><?= e(inventory_report_pct($coverage)) ?> پوشش محاسبه هزینه</small></article><article class="metric-card"><span>هزینه مواد همان فروش</span><strong><?= e(toman_number($coveredCogs)) ?></strong></article><article class="metric-card"><span>سود ناخالص تقریبی همان فروش</span><strong><?= e(toman_number($grossProfit)) ?></strong><small><?= e(inventory_report_pct($margin)) ?> حاشیه · بدون حقوق، اجاره و سربار</small></article><article class="metric-card"><span>ضایعات ثبت‌شده</span><strong><?= e(toman_number($wasteCost)) ?></strong></article></div>
<section class="card"><div class="card-head"><div><h2>هزینه مواد به تفکیک بخش</h2><small>بر اساس بخشی که هنگام تأیید سفارش روی مصرف انبار ثبت شده است.</small></div></div><div class="card-body"><div class="inventory-report-departments"><?php foreach($departmentRows as $r): ?><div><span><?= e(inventory_department_labels()[(string)$r['department']]??'مشترک') ?></span><strong><?= e(toman_number((int)$r['known_cost'])) ?></strong></div><?php endforeach; ?><?php if(!$departmentRows): ?><div class="empty-state compact">هنوز مصرف قیمت‌داری برای این بازه ثبت نشده است.</div><?php endif; ?></div></div></section>
<section class="card"><div class="card-head"><div><h2>مصرف مواد</h2><small>مصرف خالص فروش پس از اصلاح‌های آماده‌نشده؛ در صفحه ۳۰ ردیف اول نمایش داده می‌شود و Excel بازه کامل را دارد.</small></div></div><div class="report-data-list" style="--report-metric-count:3"><?php foreach($consumptionRows as $r): ?><article class="report-data-row"><div class="report-data-primary"><strong><?= e((string)$r['name']) ?></strong><small><?= e(inventory_department_labels()[(string)$r['department']]??'مشترک') ?></small></div><div class="report-data-metric"><span>مصرف</span><strong><?= e(inventory_format_quantity((int)$r['consumed_quantity'],(string)$r['base_unit'])) ?></strong></div><div class="report-data-metric"><span>هزینه ثبت‌شده</span><strong><?= e(toman_number((int)$r['known_cost'])) ?></strong></div><div class="report-data-metric"><span>وضعیت هزینه</span><strong class="<?= (int)$r['unknown_count']>0?'report-data-status':'' ?>"><?= (int)$r['unknown_count']>0?'ناقص':'کامل' ?></strong></div></article><?php endforeach; ?><?php if(!$consumptionRows): ?><div class="empty-state">برای فروش‌های این بازه مصرف مواد ثبت نشده است.</div><?php endif; ?></div></section>
<section class="card"><div class="card-head"><div><h2>سود تقریبی آیتم‌های منو</h2><small>درآمد آیتم پس از تخصیص تخفیف و پیش از مالیات است؛ در صفحه ۲۰ آیتم اول و در Excel کل بازه می‌آید.</small></div></div><div class="report-data-list" style="--report-metric-count:4"><?php foreach($itemRows as $r): $complete=(int)$r['uncovered_revenue']===0;$profit=(int)$r['revenue']-(int)$r['known_cogs']; ?><article class="report-data-row"><div class="report-data-primary"><strong><?= e((string)$r['item_name']) ?></strong><small><?= fa_digits((int)$r['qty']) ?> عدد<?= $complete?'':' · هزینه مواد ناقص' ?></small></div><div class="report-data-metric"><span>درآمد</span><strong><?= e(toman_number((int)$r['revenue'])) ?></strong></div><div class="report-data-metric"><span>هزینه مواد</span><strong><?= $complete?e(toman_number((int)$r['known_cogs'])):'ناقص' ?></strong></div><div class="report-data-metric"><span>سود ناخالص</span><strong><?= $complete?e(toman_number($profit)):'—' ?></strong></div><div class="report-data-metric"><span>وضعیت</span><strong class="<?= $complete?'':'report-data-status' ?>"><?= $complete?'قابل محاسبه':'هزینه ناقص' ?></strong></div></article><?php endforeach; ?><?php if(!$itemRows): ?><div class="empty-state">فروش تسویه‌شده‌ای برای این بازه نیست.</div><?php endif; ?></div></section>
<section class="card"><div class="card-head"><div><h2>خرید خالص ثبت‌شده</h2><small>قیمت اصلاح‌شده خریدها و برگشت خرید در بازه؛ با هزینه مواد فروخته‌شده یکی نیست.</small></div><strong><?= e(toman_number($purchaseSpend)) ?></strong></div><div class="card-body"><div class="inventory-report-departments"><?php foreach($purchaseDepartmentRows as $r): ?><div><span><?= e(inventory_department_labels()[(string)$r['department']]??'مشترک') ?></span><strong><?= e(toman_number((int)$r['total'])) ?></strong></div><?php endforeach; ?></div></div></section>
<section class="card"><div class="card-head"><div><h2>مغایرت شمارش دوره‌ای</h2><small><?= fa_digits($countSessionCount) ?> شمارش نهایی · <?= fa_digits($countVarianceCount) ?> قلم مغایرت؛ ۲۰ مورد اخیر در صفحه و کل بازه در Excel.</small></div></div><div class="report-data-list" style="--report-metric-count:3"><?php foreach($countVarianceRows as $r): ?><article class="report-data-row"><div class="report-data-primary"><strong><?= e((string)$r['name']) ?></strong><small><?= e((string)$r['title']) ?> · <?= e(format_jalali_compact((string)$r['finalized_at'])) ?></small></div><div class="report-data-metric"><span>سیستم</span><strong><?= e(inventory_format_quantity((int)$r['system_quantity_snapshot'],(string)$r['base_unit'])) ?></strong></div><div class="report-data-metric"><span>واقعی</span><strong><?= e(inventory_format_quantity((int)$r['actual_quantity'],(string)$r['base_unit'])) ?></strong></div><div class="report-data-metric"><span>مغایرت</span><strong class="report-data-status"><?= e(inventory_format_quantity((int)$r['difference_base'],(string)$r['base_unit'])) ?></strong></div></article><?php endforeach; ?><?php if(!$countVarianceRows): ?><div class="empty-state">در شمارش‌های دوره‌ای این بازه مغایرتی ثبت نشده است.</div><?php endif; ?></div></section>
</div>
<?php panel_footer(); ?>

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
maintenance_guard_json();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(['success'=>false],405);
if (!sokna_module_enabled('reporting')) json_response(['success'=>true]);
if (!setting_bool('analytics_enabled', true)) json_response(['success'=>true]);
$data=request_json();
if (!csrf_valid($data['csrf_token']??null)) json_response(['success'=>false],419);
$key=(string)($data['metric_key']??'');
$ref=max(0,(int)($data['ref_id']??0));
$allowed=['menu_view','item_add','order_submit','event_open','campaign_click','instagram_click','whatsapp_click','accommodation_click','about_open','search_no_result','search_query'];
if (!in_array($key,$allowed,true)) json_response(['success'=>false],422);
if (in_array($key,['event_open','campaign_click'],true) && !sokna_module_enabled('marketing')) json_response(['success'=>true]);
$bucket=date('Y-m-d-H');
$_SESSION['metric_count'][$bucket]=(int)($_SESSION['metric_count'][$bucket]??0)+1;
if ($_SESSION['metric_count'][$bucket]>120) json_response(['success'=>true]);
$metricBusinessDate=business_current_date();
$dedupeKey=$metricBusinessDate.':'.$key.':'.$ref;
if (in_array($key,['menu_view','event_open','about_open'],true)&&isset($_SESSION['metric_seen'][$dedupeKey])) json_response(['success'=>true]);
$_SESSION['metric_seen'][$dedupeKey]=1;
try {
    $pdo=db();
    $stmt=$pdo->prepare('INSERT INTO menu_metrics_daily(metric_date,metric_key,ref_id,metric_value) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE metric_value=metric_value+1');
    $stmt->execute([$metricBusinessDate,$key,$ref]);
    if ($key==='search_query') {
        $term=trim((string)($data['search_term']??''));
        $term=preg_replace('/\s+/u',' ',$term)??'';
        $term=str_replace(['ي','ك'],['ی','ک'],$term);
        $length=text_length($term);
        $looksSensitive=(bool)preg_match('/(?:@|https?:\/\/|www\.|\d{6,})/iu',en_digits($term));
        if ($length>=2 && $length<=80 && !$looksSensitive) {
            $normalized=text_lower($term);
            $resultCount=max(0,min(9999,(int)($data['result_count']??0)));
            $zero=$resultCount===0?1:0;
            $search=$pdo->prepare('INSERT INTO menu_search_terms_daily(metric_date,normalized_term,display_term,search_count,zero_result_count,last_result_count) VALUES(?,?,?,1,?,?) ON DUPLICATE KEY UPDATE display_term=VALUES(display_term),search_count=search_count+1,zero_result_count=zero_result_count+VALUES(zero_result_count),last_result_count=VALUES(last_result_count),updated_at=CURRENT_TIMESTAMP');
            $search->execute([$metricBusinessDate,$normalized,$term,$zero,$resultCount]);
        }
    }
} catch(Throwable $e) { error_log('Metric error: '.$e->getMessage()); }
json_response(['success'=>true]);

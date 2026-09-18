<?php
declare(strict_types=1);
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ui_icon(string $name): string { return '<i>'.$name.'</i>'; }
function fa_digits(string|int $value): string { return strtr((string)$value,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }
require dirname(__DIR__).'/includes/reporting.php';
$range=['period'=>'30','from_j'=>'۱۴۰۵/۰۴/۲۵','to_j'=>'۱۴۰۵/۰۵/۲۳'];
ob_start(); report_render_range_fields($range,'test'); $html=ob_get_clean();
if (substr_count($html,'<option') !== 6) { fwrite(STDERR,"option count\n"); exit(1); }
if (!preg_match('/<option value="30"\s+selected>۳۰ روز<\/option>/', $html)) { fwrite(STDERR,"selected period\n"); exit(2); }
if (strpos($html,'name="from_j"')===false || strpos($html,'name="to_j"')===false) { exit(3); }
echo "REPORT_RENDER_OK\n";

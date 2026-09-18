<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/printing.php';
$prep=print_template_defaults('preparation');
$cust=print_template_defaults('customer');
if (($prep['design']['labels']['ticket_title']??'')!=='فیش آماده‌سازی') throw new RuntimeException('prep title label');
if (($prep['show_prices']??true)!==false || ($cust['show_prices']??false)!==true) throw new RuntimeException('price boundary');
$custom=$cust;$custom['format']='sokna-print-template-v2';$custom['layout_contract']='customer-receipt-v2';$custom['paper_width_mm']=58;$custom['footer']='سپاس';$custom['design']['section_order']=['meta','brand','items','summary','settlement','footer'];
$out=print_template_validate($custom,'customer');
if ($out['format']!=='sokna-print-template-v1' || $out['paper_width_mm']!==58 || $out['footer']!=='سپاس') throw new RuntimeException('v2 normalization');
$evil=$custom;$evil['footer']='<script>alert(1)</script>';
try{print_template_validate($evil,'customer');throw new RuntimeException('unsafe footer accepted');}catch(RuntimeException $e){if(str_contains($e->getMessage(),'unsafe footer accepted'))throw $e;}
$prepTry=$prep;$prepTry['show_prices']=true;$prepOut=print_template_validate($prepTry,'preparation');if($prepOut['show_prices']!==false)throw new RuntimeException('prep price leak');
echo "Print Template v2 runtime PASS\n";

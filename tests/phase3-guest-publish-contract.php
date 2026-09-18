<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$publish=(string)file_get_contents($root.'/public_edge/api/v1/local/guest/publish.php');
$local=(string)file_get_contents($root.'/includes/guest_publish.php');
$admin=(string)file_get_contents($root.'/admin/guest_publish.php');
$schema=(string)file_get_contents($root.'/public_edge/database/schema.sql');
function need3(bool $ok,string $m):void{if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";}
need3(str_contains($schema,'guest_publish_revisions'),'immutable revision store exists');
need3(str_contains($schema,'guest_active_revisions'),'active pointer store exists');
need3(str_contains($schema,'guest_availability_state'),'availability channel is separate');
need3(str_contains($publish,'missing_media'),'publish validates media before pointer swap');
$mediaPos=strpos($publish,'foreach ($manifest');
$pointerPos=strpos($publish,'guest_active_revisions');
need3($mediaPos!==false && $pointerPos!==false && $mediaPos<$pointerPos,'media validation precedes pointer activation');
need3(str_contains($publish,'revision_id_conflict'),'immutable revision id conflict is explicit');
need3(str_contains($local,'menu_catalog_snapshot'),'Local snapshot derives from canonical menu read model');
need3(!str_contains($local,'<html'),'publish payload is data, not frozen Local HTML');
need3(str_contains($admin,'انتشار منوی مهمان'),'publish is an explicit admin action');
need3(!str_contains((string)file_get_contents($root.'/admin/settings.php'),"guest_publish_now("),'Save Local does not silently publish');
echo "Phase 3 guest publish contract PASS\n";

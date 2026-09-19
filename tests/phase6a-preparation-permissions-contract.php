<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$owner=(string)file_get_contents($root.'/includes/preparation_permissions.php');
$boot=(string)file_get_contents($root.'/bootstrap.php');
$feed=(string)file_get_contents($root.'/waiter/api_feed.php');
$action=(string)file_get_contents($root.'/waiter/api_action.php');
$page=(string)file_get_contents($root.'/waiter/index.php');
$js=(string)file_get_contents($root.'/assets/js/waiter.js');
$projection=(string)file_get_contents($root.'/includes/relay_projection.php');
function p6a(bool $ok,string $m):void{if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";}

p6a(str_contains($boot,"includes/preparation_permissions.php"),'Preparation permission owner is loaded centrally');
p6a(str_contains($owner,'function preparation_access_context'),'canonical Preparation access context exists');
p6a(str_contains($owner,'$globalMonitor=$isAdmin||$hasSupervision'),'Supervisor/Admin visibility is explicitly global');
p6a(str_contains($owner,'$actionable=(!$isAdmin&&$hasPreparation)?$assigned:[]'),'Admin/supervision alone never create Preparation action authority');
p6a(str_contains($feed,"require_any_capability(['orders_floor','preparation','shift_supervision'])"),'Supervisor may enter read feed');
p6a(!preg_match('/UPDATE\s+preparation_adjustments/i',$feed),'Preparation feed is side-effect free');
p6a(str_contains($feed,"'visible_preparation_areas'")&&str_contains($feed,"'actionable_preparation_areas'"),'Feed exposes server-authored visibility/action scopes');
p6a(str_contains($feed,"'preparation_visible'")&&str_contains($feed,"'preparation_actionable'"),'Feed does not overload legacy preparation capability flag');
p6a(str_contains($action,'preparation_access_context($user)')&&str_contains($action,"$actionableAreas"),'Mutation route consumes canonical actionable areas');
p6a(!str_contains($action,'user_preparation_areas($userId)'), 'Mutation route does not re-infer Preparation scope independently');
p6a(str_contains($page,'WAITER_ACTIONABLE_AREAS')&&str_contains($page,'WAITER_VISIBLE_AREAS'),'Page receives explicit area scopes');
p6a(str_contains($js,'canActTask')&&str_contains($js,'actionable_preparation_areas'),'Browser gates each Preparation action by actionable area');
p6a(str_contains($projection,"'operations.read','preparation.monitor'"),'Remote supervisor remains Preparation monitor');
p6a(!preg_match("/shift_supervision[^\n]+preparation\.mutate/",$projection),'Remote supervisor does not gain Preparation mutation');
echo "Phase 6A preparation permission split contract PASS\n";

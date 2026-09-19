<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

function p6al(bool $ok,string $message,mixed $context=null):void{
    if(!$ok){
        fwrite(STDERR,"FAIL: $message\n");
        if($context!==null)fwrite(STDERR,json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");
        exit(1);
    }
    echo "PASS: $message\n";
}
$pdo=db();
$pdo->beginTransaction();
try{
    $insert=$pdo->prepare("INSERT INTO users(username,password_hash,display_name,role,active) VALUES(?,?,?,?,1)");
    $cap=$pdo->prepare("INSERT INTO user_capabilities(user_id,capability,enabled) VALUES(?,?,1)");
    $area=$pdo->prepare("INSERT INTO user_preparation_areas(user_id,area_key) VALUES(?,?)");

    $mk=function(string $name,string $role,array $caps,array $areas)use($insert,$cap,$area):array{
        $insert->execute([$name,password_hash('phase6a-pass',PASSWORD_DEFAULT),$name,$role]);
        $id=(int)db()->lastInsertId();
        foreach($caps as $c)$cap->execute([$id,$c]);
        foreach($areas as $a)$area->execute([$id,$a]);
        return ['id'=>$id,'username'=>$name,'display_name'=>$name,'role'=>$role,'active'=>1];
    };

    $prep=$mk('phase6a-prep','operator',['preparation'],['kitchen']);
    $super=$mk('phase6a-super','operator',['shift_supervision'],[]);
    $both=$mk('phase6a-both','operator',['preparation','shift_supervision'],['kitchen']);
    $admin=$mk('phase6a-admin','admin',[],[]);

    $ctx=preparation_access_context($prep);
    p6al($ctx['visible_areas']===['kitchen']&&$ctx['actionable_areas']===['kitchen']&&$ctx['can_mutate']===true,'Preparation-only user sees and acts only assigned area',$ctx);

    $ctx=preparation_access_context($super);
    p6al($ctx['visible_areas']===['kitchen','bar']&&$ctx['actionable_areas']===[]&&$ctx['monitor_only']===true,'Supervisor sees all areas but cannot mutate',$ctx);

    $ctx=preparation_access_context($both);
    p6al($ctx['visible_areas']===['kitchen','bar']&&$ctx['actionable_areas']===['kitchen']&&$ctx['can_mutate']===true,'Supervisor+Preparation sees all but mutates assigned area only',$ctx);

    $ctx=preparation_access_context($admin);
    p6al($ctx['visible_areas']===['kitchen','bar']&&$ctx['actionable_areas']===[]&&$ctx['monitor_only']===true,'Admin role alone is global read-only for Preparation',$ctx);

    p6al(preparation_can_mutate_area('kitchen',$both)===true,'assigned area mutation allowed');
    p6al(preparation_can_mutate_area('bar',$both)===false,'unassigned visible area mutation denied');
    p6al(preparation_can_mutate_area('bogus',$both)===false,'invalid area never normalizes into authorization');

    $pdo->rollBack();
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
}
echo "Phase 6A Preparation local permission matrix PASS\n";

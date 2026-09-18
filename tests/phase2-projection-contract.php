<?php
declare(strict_types=1);
$src=(string)file_get_contents(dirname(__DIR__).'/includes/relay_projection.php');
function c(bool $ok,string $m):void{if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";}
c(str_contains($src,"in_array('preparation',\$local,true)"),'preparation capability is projected explicitly');
c(!preg_match("/shift_supervision[^\n]+preparation\.mutate/",$src),'shift supervision alone does not grant preparation mutation');
c(str_contains($src,"password_hash"),'projection carries verifier hash, not plaintext password');
c(str_contains($src,"user_preparation_areas"),'preparation area projection is included');
echo "Phase 2 projection contract PASS\n";

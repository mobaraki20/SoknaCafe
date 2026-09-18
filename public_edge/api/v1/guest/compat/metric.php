<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require dirname(__DIR__,4).'/guest/compat.php';

if($_SERVER['REQUEST_METHOD']!=='POST')public_json(['success'=>false],405);
$data=public_json_body();public_guest_csrf_or_fail($data);
// Guest analytics is support behavior, never part of ordering correctness.
// Until aggregate telemetry ownership is migrated, Public intentionally no-ops.
public_json(['success'=>true]);

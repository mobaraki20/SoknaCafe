#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(64); }
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/sokna_center_projection.php';

try {
    $result = sokna_center_push_user_projection(db(), 5);
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $e) {
    if (function_exists('sokna_log_event')) sokna_log_event('warning','center.user_projection_failed',['message'=>$e->getMessage()]);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(2);
}

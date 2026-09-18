<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/print_agent_api.php';

$valid = [
    'r-0123456789abcdef0123456789abcdef',
    'r-ABCDEF0123456789ABCDEF0123456789',
];
$invalid = [
    '',
    '0123456789abcdef0123456789abcdef',
    'r-0123456789abcdef0123456789abcde',
    'r-0123456789abcdef0123456789abcdef0',
    'r-0123456789abcdef0123456789abcdeg',
    'receipt-0123456789abcdef0123456789abcdef',
];
foreach ($valid as $receipt) {
    if (!print_agent_api_local_receipt_id_valid($receipt)) throw new RuntimeException('Valid Agent receipt rejected: ' . $receipt);
}
foreach ($invalid as $receipt) {
    if (print_agent_api_local_receipt_id_valid($receipt)) throw new RuntimeException('Invalid Agent receipt accepted: ' . $receipt);
}
$hash = str_repeat('a',64);
if (!print_agent_api_content_sha256_valid($hash)) throw new RuntimeException('Valid sha256 rejected.');
if (print_agent_api_content_sha256_valid('abc')) throw new RuntimeException('Short sha256 accepted.');
echo "Print Agent receipt validator PASS\n";

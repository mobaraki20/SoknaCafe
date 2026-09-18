<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_any_capability(['orders_floor','cashier_accounts','shift_supervision']);
require dirname(__DIR__) . '/includes/panel_layout.php';
require dirname(__DIR__) . '/includes/operator_page.php';
render_operator_page();

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin','operator','waiter','staff']);
require_capability('cashier_accounts');
require dirname(__DIR__) . '/includes/panel_layout.php';
require dirname(__DIR__) . '/includes/invoices_page.php';
render_invoices_page(false);

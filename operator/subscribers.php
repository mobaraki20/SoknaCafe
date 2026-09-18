<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login();
require dirname(__DIR__) . '/includes/subscribers_page.php';
render_subscribers_page();

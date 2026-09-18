<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
logout_user();
redirect(app_base_url() . '/login.php');

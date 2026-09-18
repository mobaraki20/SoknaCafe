<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_login();
redirect(user_home_path());

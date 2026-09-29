<?php
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
logout_user();
redirect('/login.php');

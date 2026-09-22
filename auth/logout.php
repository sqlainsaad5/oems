<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
logout_user();
flash('success', 'You have been signed out.');
redirect('/auth/login.php');

<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
start_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) logout_user();
header('Location: ' . url('admin/login.php'));

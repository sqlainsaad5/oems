<?php
/**
 * OEMS — Login
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
guest_only();

$errors = [];
$login = '';

if (is_post()) {
    verify_csrf();
    $login = trim((string)request('login', ''));
    $password = (string)request('password', '');

    if ($login === '' || $password === '') {
        $errors[] = 'Please enter both username/email and password.';
    } else {
        $result = attempt_login($login, $password);
        if ($result['ok']) {
            flash('success', 'Welcome back, ' . $result['user']['full_name'] . '!');
            redirect(role_home($result['user']['role']));
        }
        $errors[] = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,560;9..144,600&family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <section class="auth-visual" aria-hidden="false">
        <div class="auth-brand">
            <span class="brand-mark">O</span>
            <div>
                <strong>OEMS</strong>
                <small>Online Examination Management</small>
            </div>
        </div>
        <div class="auth-hero">
            <h1>Exams with clarity and quiet confidence.</h1>
            <p>Schedule, invigilate digitally, grade with precision, and publish results — built for institutions that value reliability.</p>
            <ul class="auth-points">
                <li>Role-based portals for admins, teachers, and students</li>
                <li>Timed exams with auto-save and auto-submit</li>
                <li>Instant MCQ scoring and manual descriptive grading</li>
            </ul>
        </div>
        <p style="position:relative;z-index:1;opacity:.65;font-size:.82rem;margin:0">Encrypted passwords · Secure sessions · Responsive UI</p>
    </section>
    <section class="auth-panel">
        <div class="auth-card">
            <h2>Sign in</h2>
            <p class="lead">Use your institutional credentials to continue.</p>

            <?php if ($errors): ?>
                <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('success')): ?>
                <div class="alert alert-success"><?= e($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('error')): ?>
                <div class="alert alert-danger"><?= e($msg) ?></div>
            <?php endif; ?>

            <form method="post" action="" autocomplete="on" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="login">Username or email</label>
                    <input class="form-control" type="text" id="login" name="login" value="<?= e($login) ?>" required autofocus placeholder="admin or student1">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required placeholder="••••••••">
                </div>
                <button class="btn btn-primary btn-block" type="submit">Sign in</button>
            </form>

            <div class="demo-hint">
                <strong style="color:var(--ink)">Demo accounts</strong> (password <code>password123</code>)<br>
                Admin: <code>admin</code> · Teacher: <code>teacher1</code> · Student: <code>student1</code>
            </div>
            <p style="margin:18px 0 0;text-align:center;font-size:.85rem">
                <a href="<?= url('/') ?>">← Back to home</a>
            </p>
        </div>
    </section>
</div>
</body>
</html>

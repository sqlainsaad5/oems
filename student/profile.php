<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_student();
$user = $ctx['user'];
$student = $ctx['student'];

if (is_post()) {
    verify_csrf();
    $fullName = trim((string)request('full_name'));
    $email = trim((string)request('email'));
    $phone = trim((string)request('phone'));
    $address = trim((string)request('address'));
    $password = (string)request('password');
    $current = (string)request('current_password');

    if ($fullName === '' || $email === '') {
        flash('error', 'Name and email are required.');
        redirect('/student/profile.php');
    }

    try {
        db()->beginTransaction();
        db()->prepare('UPDATE users SET full_name=?, email=? WHERE id=?')
            ->execute([$fullName, $email, (int)$user['id']]);
        db()->prepare('UPDATE students SET phone=?, address=? WHERE id=?')
            ->execute([$phone ?: null, $address ?: null, (int)$student['id']]);

        if ($password !== '') {
            if (!password_verify($current, $user['password'])) {
                throw new RuntimeException('Current password is incorrect.');
            }
            if (strlen($password) < 8) {
                throw new RuntimeException('New password must be at least 8 characters.');
            }
            db()->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
        }
        db()->commit();
        flash('success', 'Profile updated.');
        log_activity((int)$user['id'], 'profile_update', 'Student profile updated');
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('error', $e->getMessage() ?: 'Could not update profile.');
    }
    redirect('/student/profile.php');
}

// Refresh
$ctx = require_student();
$user = $ctx['user'];
$student = $ctx['student'];

$pageTitle = 'Profile';
$pageSubtitle = 'Update your account information';
$activeNav = 'profile';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid grid-2">
    <section class="panel">
        <div class="panel-header"><h2>Personal details</h2></div>
        <form method="post" class="panel-body">
            <?= csrf_field() ?>
            <div class="form-group"><label>Full name</label><input class="form-control" name="full_name" required value="<?= e($user['full_name']) ?>"></div>
            <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required value="<?= e($user['email']) ?>"></div>
            <div class="form-group"><label>Phone</label><input class="form-control" name="phone" value="<?= e($student['phone'] ?? '') ?>"></div>
            <div class="form-group"><label>Address</label><textarea class="form-control" name="address"><?= e($student['address'] ?? '') ?></textarea></div>
            <hr style="border:0;border-top:1px solid var(--line);margin:8px 0 18px">
            <h3 style="font-size:1rem">Change password</h3>
            <div class="form-group"><label>Current password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password"></div>
            <div class="form-group"><label>New password</label><input class="form-control" type="password" name="password" autocomplete="new-password"><div class="form-hint">Leave blank to keep your current password.</div></div>
            <button class="btn btn-primary" type="submit">Save changes</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-header"><h2>Academic info</h2></div>
        <div class="panel-body list-feed">
            <div class="feed-item"><div><strong>Username</strong><span><?= e($user['username']) ?></span></div></div>
            <div class="feed-item"><div><strong>Student ID</strong><span><?= e($student['student_id']) ?></span></div></div>
            <div class="feed-item"><div><strong>Course</strong><span><?= e(($student['course_code'] ?? '') . ' — ' . ($student['course_name'] ?? 'N/A')) ?></span></div></div>
            <div class="feed-item"><div><strong>Enrollment year</strong><span><?= e((string)($student['enrollment_year'] ?? '—')) ?></span></div></div>
            <div class="feed-item"><div><strong>Semester</strong><span><?= e((string)$student['semester']) ?></span></div></div>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

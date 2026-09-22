<?php
/**
 * OEMS — Authentication & Authorization
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    start_app_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null && (int)$cached['id'] === (int)$_SESSION['user_id']) {
        return $cached;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        logout_user();
        return null;
    }
    $cached = $user;
    return $user;
}

function check_auth(array $roles = []): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('/auth/login.php');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        flash('error', 'You do not have permission to access that area.');
        redirect(role_home($user['role']));
    }
    return $user;
}

function guest_only(): void
{
    $user = current_user();
    if ($user) {
        redirect(role_home($user['role']));
    }
}

function attempt_login(string $login, string $password): array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['ok' => false, 'error' => 'Invalid username/email or password.'];
    }
    if ($user['status'] !== 'active') {
        return ['ok' => false, 'error' => 'Your account is inactive. Contact the administrator.'];
    }

    start_app_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role'];

    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([(int)$user['id']]);
    log_activity((int)$user['id'], 'login', 'User signed in');

    return ['ok' => true, 'user' => $user];
}

function logout_user(): void
{
    start_app_session();
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        log_activity((int)$uid, 'logout', 'User signed out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function get_teacher_profile(int $userId): ?array
{
    $stmt = db()->prepare('SELECT t.*, u.full_name, u.email, u.username FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_student_profile(int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT s.*, u.full_name, u.email, u.username, c.name AS course_name, c.code AS course_code
         FROM students s
         JOIN users u ON u.id = s.user_id
         LEFT JOIN courses c ON c.id = s.course_id
         WHERE s.user_id = ?'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function require_teacher(): array
{
    $user = check_auth(['teacher']);
    $profile = get_teacher_profile((int)$user['id']);
    if (!$profile) {
        flash('error', 'Teacher profile not found.');
        redirect('/auth/login.php');
    }
    return ['user' => $user, 'teacher' => $profile];
}

function require_student(): array
{
    $user = check_auth(['student']);
    $profile = get_student_profile((int)$user['id']);
    if (!$profile) {
        flash('error', 'Student profile not found.');
        redirect('/auth/login.php');
    }
    return ['user' => $user, 'student' => $profile];
}

function require_admin(): array
{
    return check_auth(['admin']);
}

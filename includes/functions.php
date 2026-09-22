<?php
/**
 * OEMS — Shared Helper Functions
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name(SESSION_NAME);
    session_start([
        'cookie_lifetime' => 0,
        'gc_maxlifetime'  => SESSION_LIFETIME,
    ]);
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    if (str_starts_with($path, 'http')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . url($path));
    }
    exit;
}

function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    if ($path === '/') {
        return rtrim(BASE_URL, '/') . '/';
    }
    return rtrim(BASE_URL, '/') . $path;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function request(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function flash(string $key, ?string $message = null): ?string
{
    start_app_session();
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $val = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $val;
}

function csrf_token(): string
{
    start_app_session();
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    start_app_session();
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals($_SESSION[CSRF_TOKEN_KEY] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid security token. Please refresh and try again.');
    }
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function log_activity(?int $userId, string $action, ?string $details = null): void
{
    try {
        $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)');
        $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable) {
        // Non-fatal
    }
}

function create_notification(int $userId, string $title, string $message, string $type = 'system', ?string $link = null): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)');
    $stmt->execute([$userId, $title, $message, $type, $link]);
}

function notify_role_users(string $role, string $title, string $message, string $type = 'system', ?string $link = null): void
{
    $stmt = db()->prepare('SELECT id FROM users WHERE role = ? AND status = "active"');
    $stmt->execute([$role]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        create_notification((int)$uid, $title, $message, $type, $link);
    }
}

function calculate_grade(float $percentage): string
{
    return match (true) {
        $percentage >= 90 => 'A+',
        $percentage >= 85 => 'A',
        $percentage >= 80 => 'A-',
        $percentage >= 75 => 'B+',
        $percentage >= 70 => 'B',
        $percentage >= 65 => 'B-',
        $percentage >= 60 => 'C+',
        $percentage >= 55 => 'C',
        $percentage >= 50 => 'C-',
        $percentage >= 40 => 'D',
        default => 'F',
    };
}

function format_datetime(?string $dt, string $format = 'M j, Y g:i A'): string
{
    if (!$dt) {
        return '—';
    }
    return date($format, strtotime($dt));
}

function time_remaining_seconds(string $endsAt): int
{
    return max(0, strtotime($endsAt) - time());
}

function exam_window_status(array $exam): string
{
    if (($exam['status'] ?? '') === 'cancelled') {
        return 'cancelled';
    }
    $approval = $exam['approval_status'] ?? 'approved';
    if (in_array($approval, ['draft', 'pending', 'rejected'], true)) {
        return $approval === 'pending' ? 'pending_approval' : $approval;
    }
    $mode = $exam['availability_mode'] ?? 'scheduled';
    if ($mode === 'always') {
        return 'available';
    }
    $now = time();
    $start = strtotime((string)$exam['start_time']);
    $end = strtotime((string)$exam['end_time']);
    if ($now < $start) {
        return 'upcoming';
    }
    if ($now > $end) {
        return 'ended';
    }
    return 'available';
}

function paper_is_student_visible(array $exam): bool
{
    if (($exam['approval_status'] ?? '') !== 'approved') {
        return false;
    }
    return in_array($exam['status'] ?? '', ['scheduled', 'active', 'completed'], true);
}

function fetch_paper_sections(int $examId): array
{
    $st = db()->prepare('SELECT * FROM paper_sections WHERE exam_id=? ORDER BY sort_order, id');
    $st->execute([$examId]);
    return $st->fetchAll();
}

function ensure_default_paper_section(int $examId, string $type = 'mixed'): int
{
    $st = db()->prepare('SELECT id FROM paper_sections WHERE exam_id=? ORDER BY sort_order, id LIMIT 1');
    $st->execute([$examId]);
    $id = $st->fetchColumn();
    if ($id) {
        return (int)$id;
    }
    db()->prepare(
        "INSERT INTO paper_sections (exam_id, title, section_code, section_type, sort_order) VALUES (?, 'Section A', 'A', ?, 1)"
    )->execute([$examId, $type]);
    return (int)db()->lastInsertId();
}

function count_pending_paper_approvals(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM exams WHERE approval_status='pending'")->fetchColumn();
}

function exam_pass_fail(array $examOrResult, float $obtained, float $total, float $percentage): string
{
    $passingMarks = (float)($examOrResult['passing_marks'] ?? 0);
    if ($passingMarks > 0) {
        return $obtained + 0.0001 >= $passingMarks ? 'PASS' : 'FAIL';
    }
    $passPct = (float)get_setting('passing_percentage', '40');
    return $percentage + 0.0001 >= $passPct ? 'PASS' : 'FAIL';
}

function ordinal_rank(int $n): string
{
    $n = max(1, $n);
    $mod100 = $n % 100;
    if ($mod100 >= 11 && $mod100 <= 13) {
        return $n . 'th';
    }
    return match ($n % 10) {
        1 => $n . 'st',
        2 => $n . 'nd',
        3 => $n . 'rd',
        default => $n . 'th',
    };
}

/**
 * Attach rank (1-based) to results already sorted by percentage DESC.
 */
function attach_result_ranks(array $results): array
{
    $rank = 0;
    $prev = null;
    $index = 0;
    foreach ($results as &$r) {
        $index++;
        $pct = (float)$r['percentage'];
        if ($prev === null || $pct < $prev) {
            $rank = $index;
            $prev = $pct;
        }
        $r['rank'] = $rank;
        $r['rank_label'] = ordinal_rank($rank);
        $r['pass_fail'] = exam_pass_fail(
            $r,
            (float)($r['obtained_marks'] ?? 0),
            (float)($r['total_marks'] ?? 0),
            $pct
        );
    }
    unset($r);
    return $results;
}

function paginate(int $total, int $page, int $perPage = 10): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
    ];
}

function validate_required(array $data, array $fields): array
{
    $errors = [];
    foreach ($fields as $field => $label) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $errors[$field] = "$label is required.";
        }
    }
    return $errors;
}

function old(string $key, mixed $default = ''): mixed
{
    start_app_session();
    return $_SESSION['_old'][$key] ?? $default;
}

function store_old(array $data): void
{
    start_app_session();
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    start_app_session();
    unset($_SESSION['_old']);
}

function role_home(string $role): string
{
    return match ($role) {
        'admin' => '/admin/index.php',
        'teacher' => '/teacher/index.php',
        'student' => '/student/index.php',
        default => '/auth/login.php',
    };
}

function unread_notifications_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function get_setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? (string)$val : $default;
}

function backup_database(): ?string
{
    if (!is_dir(BACKUP_PATH)) {
        mkdir(BACKUP_PATH, 0755, true);
    }
    $file = BACKUP_PATH . '/oems_backup_' . date('Ymd_His') . '.sql';
    // Prefer mysqldump when available
    $mysqldump = 'mysqldump';
    $cmd = sprintf(
        '%s --host=%s --port=%s --user=%s %s %s > %s 2>&1',
        escapeshellcmd($mysqldump),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_PORT),
        escapeshellarg(DB_USER),
        DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '',
        escapeshellarg(DB_NAME),
        escapeshellarg($file)
    );
    // Portable PHP dump fallback
    try {
        $tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $out = "-- OEMS Backup " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach ($tables as $table) {
            $create = db()->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $out .= "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n\n";
            $rows = db()->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = array_map(fn($c) => "`$c`", array_keys($row));
                $vals = array_map(function ($v) {
                    if ($v === null) return 'NULL';
                    return db()->quote((string)$v);
                }, array_values($row));
                $out .= 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n";
            }
            $out .= "\n";
        }
        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        file_put_contents($file, $out);
        return $file;
    } catch (Throwable) {
        return null;
    }
}

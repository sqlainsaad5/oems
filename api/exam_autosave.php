<?php
/**
 * API — Exam answer auto-save
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/exam_helpers.php';

header('Content-Type: application/json');

if (!is_post()) {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

start_app_session();
$token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$token || !hash_equals($_SESSION[CSRF_TOKEN_KEY] ?? '', $token)) {
    json_response(['ok' => false, 'error' => 'Invalid token'], 419);
}

$user = current_user();
if (!$user || $user['role'] !== 'student') {
    json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}
$student = get_student_profile((int)$user['id']);
if (!$student) {
    json_response(['ok' => false, 'error' => 'Profile missing'], 400);
}

$examId = (int)($_POST['exam_id'] ?? 0);
// Also accept from form without exam_id field — parse from referer query if needed
if (!$examId && !empty($_SERVER['HTTP_REFERER'])) {
    $qs = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
    parse_str((string)$qs, $q);
    $examId = (int)($q['exam_id'] ?? 0);
}

// Prefer hidden field — add it in attempt form via answers only; get exam from attempt
$sid = (int)$student['id'];
$att = db()->prepare('SELECT * FROM exam_attempts WHERE student_id=? AND status="in_progress" ORDER BY id DESC LIMIT 1');
if ($examId) {
    $att = db()->prepare('SELECT * FROM exam_attempts WHERE exam_id=? AND student_id=? AND status="in_progress"');
    $att->execute([$examId, $sid]);
} else {
    $att->execute([$sid]);
}
$attempt = $att->fetch();
if (!$attempt) {
    json_response(['ok' => false, 'error' => 'No active attempt'], 400);
}
$examId = (int)$attempt['exam_id'];

if (strtotime($attempt['ends_at']) <= time()) {
    json_response(['ok' => false, 'error' => 'Time expired', 'expired' => true], 400);
}

$answers = (array)($_POST['answers'] ?? []);
save_student_answers($examId, $sid, $answers, false);
json_response(['ok' => true, 'saved_at' => date('c'), 'count' => count($answers)]);

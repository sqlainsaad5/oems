<?php
/**
 * Student — Available papers (approved only)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_student();
$user = $ctx['user'];
$student = $ctx['student'];
$sid = (int)$student['id'];

$exams = db()->prepare(
    "SELECT e.*, s.name AS subject_name, s.code AS subject_code,
            m.title AS mega_title, m.code AS mega_code,
            u.full_name AS teacher_name,
            ea.status AS attempt_status, ea.started_at, ea.submitted_at,
            (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id=e.id) AS qcount,
            (SELECT COUNT(*) FROM exam_questions eq JOIN questions q ON q.id=eq.question_id WHERE eq.exam_id=e.id AND q.question_type='mcq') AS mcq_count,
            (SELECT COUNT(*) FROM exam_questions eq JOIN questions q ON q.id=eq.question_id WHERE eq.exam_id=e.id AND q.question_type='descriptive') AS desc_count
     FROM exams e
     JOIN subjects s ON s.id=e.subject_id
     JOIN teachers t ON t.id=e.teacher_id
     JOIN users u ON u.id=t.user_id
     LEFT JOIN mega_exams m ON m.id=e.mega_exam_id
     LEFT JOIN exam_attempts ea ON ea.exam_id=e.id AND ea.student_id=?
     WHERE e.approval_status='approved' AND e.status IN ('scheduled','active','completed')
     ORDER BY e.start_time DESC"
);
$exams->execute([$sid]);
$exams = $exams->fetchAll();

$pageTitle = 'Available Papers';
$pageSubtitle = 'Approved examinations you can attempt';
$activeNav = 'exams';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="paper-grid">
<?php if (!$exams): ?>
    <section class="panel"><div class="empty-state"><h3>No papers available</h3><p>Approved papers from your teachers will appear here.</p></div></section>
<?php endif; ?>
<?php foreach ($exams as $ex):
    $win = exam_window_status($ex);
    $attempt = $ex['attempt_status'] ?? null;
    $done = in_array($attempt, ['submitted','auto_submitted'], true);
    $inProgress = $attempt === 'in_progress';
    $always = ($ex['availability_mode'] ?? '') === 'always';
?>
    <article class="paper-card panel">
        <div class="paper-card-top">
            <?php if ($ex['mega_title']): ?>
                <span class="badge badge-info"><?= e($ex['mega_title']) ?> (<?= e($ex['mega_code']) ?>)</span>
            <?php endif; ?>
            <?php if ($always): ?>
                <span class="badge badge-success">Always Available</span>
            <?php elseif ($win === 'available'): ?>
                <span class="badge badge-success">Open now</span>
            <?php elseif ($win === 'upcoming'): ?>
                <span class="badge badge-warning">Upcoming</span>
            <?php else: ?>
                <span class="badge">Closed</span>
            <?php endif; ?>
        </div>
        <h2 class="paper-card-title"><?= e($ex['title']) ?></h2>
        <p class="paper-card-meta"><?= e($ex['subject_name']) ?></p>
        <div class="paper-card-stats">
            <div><span>Teacher</span><strong><?= e($ex['teacher_name']) ?></strong></div>
            <div><span>Duration</span><strong><?= (int)$ex['duration_minutes'] ?> mins</strong></div>
            <div><span>Total Marks</span><strong><?= e((string)$ex['total_marks']) ?></strong></div>
            <div><span>Questions</span><strong><?= (int)$ex['qcount'] ?></strong></div>
        </div>
        <div class="chip-row" style="margin:10px 0">
            <span class="badge badge-success">MCQs: <?= (int)$ex['mcq_count'] ?></span>
            <span class="badge badge-warning">Descriptive: <?= (int)$ex['desc_count'] ?></span>
            <?php if (!empty($ex['exam_password'])): ?><span class="badge badge-brand">Password protected</span><?php endif; ?>
        </div>
        <div class="paper-card-schedule">
            <?php if ($always): ?>
                <strong>No time restrictions</strong>
                <small>Start anytime, complete within <?= (int)$ex['duration_minutes'] ?> minutes</small>
            <?php else: ?>
                <strong>Exam schedule</strong>
                <small><?= e(format_datetime($ex['start_time'])) ?> → <?= e(format_datetime($ex['end_time'])) ?></small>
            <?php endif; ?>
        </div>
        <div class="paper-card-actions">
            <?php if ($done): ?>
                <span class="badge badge-success">Submitted</span>
                <a class="btn btn-sm btn-secondary" href="<?= url('/student/results.php') ?>">View results</a>
            <?php elseif ($inProgress && $win === 'available'): ?>
                <a class="btn btn-primary" href="<?= url('/student/attempt.php?exam_id='.(int)$ex['id']) ?>">Continue exam</a>
            <?php elseif ($win === 'available'): ?>
                <a class="btn btn-primary" href="<?= url('/student/attempt.php?exam_id='.(int)$ex['id']) ?>">▶ Start Exam</a>
            <?php elseif ($win === 'upcoming'): ?>
                <span class="badge badge-info">Opens <?= e(format_datetime($ex['start_time'], 'M j, g:i A')) ?></span>
            <?php else: ?>
                <span class="badge">Window closed</span>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

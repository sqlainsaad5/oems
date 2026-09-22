<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_teacher();
$user = $ctx['user'];
$teacher = $ctx['teacher'];
$tid = (int)$teacher['id'];

$stats = [];
$st = db()->prepare('SELECT COUNT(*) FROM questions WHERE teacher_id=?');
$st->execute([$tid]);
$stats['questions'] = (int)$st->fetchColumn();
$st = db()->prepare('SELECT COUNT(*) FROM exams WHERE teacher_id=?');
$st->execute([$tid]);
$stats['exams'] = (int)$st->fetchColumn();
$st = db()->prepare(
    "SELECT COUNT(*) FROM student_answers sa
     JOIN questions q ON q.id=sa.question_id
     JOIN exam_attempts ea ON ea.exam_id=sa.exam_id AND ea.student_id=sa.student_id
     WHERE q.teacher_id=? AND q.question_type='descriptive' AND sa.is_graded=0
       AND ea.status IN ('submitted','auto_submitted')"
);
$st->execute([$tid]);
$stats['pending_grade'] = (int)$st->fetchColumn();
$st = db()->prepare('SELECT COUNT(*) FROM results r JOIN exams e ON e.id=r.exam_id WHERE e.teacher_id=? AND r.status="published"');
$st->execute([$tid]);
$stats['published'] = (int)$st->fetchColumn();

$exams = db()->prepare(
    "SELECT e.*, s.name AS subject_name FROM exams e JOIN subjects s ON s.id=e.subject_id
     WHERE e.teacher_id=? ORDER BY e.start_time DESC LIMIT 6"
);
$exams->execute([$tid]);
$exams = $exams->fetchAll();

$pageTitle = 'Teacher Dashboard';
$pageSubtitle = 'Welcome, ' . $teacher['full_name'];
$activeNav = 'dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="portal-hero portal-hero-teacher reveal is-visible" style="--portal-banner: url('<?= asset('images/portal-banner.png') ?>')">
    <div class="portal-hero-content">
        <p class="portal-eyebrow">Teaching workspace</p>
        <h2>Hello, <?= e(explode(' ', $teacher['full_name'])[0]) ?></h2>
        <p>Build question banks, schedule exams, grade descriptive answers, and publish results.</p>
        <div class="portal-hero-actions">
            <a class="btn btn-primary" href="<?= url('/teacher/exams.php?new=1') ?>">Create exam</a>
            <a class="btn btn-secondary" href="<?= url('/teacher/questions.php') ?>">Question bank</a>
        </div>
    </div>
    <div class="portal-hero-visual" aria-hidden="true">
        <div class="portal-orb"></div>
        <div class="portal-orb portal-orb-2"></div>
    </div>
</section>

<div class="quick-actions reveal is-visible">
    <a class="quick-action" href="<?= url('/teacher/questions.php') ?>"><?= oems_icon('help') ?><span>Questions</span></a>
    <a class="quick-action" href="<?= url('/teacher/exams.php') ?>"><?= oems_icon('clipboard') ?><span>Exams</span></a>
    <a class="quick-action" href="<?= url('/teacher/grading.php') ?>"><?= oems_icon('edit') ?><span>Grading<?= $stats['pending_grade'] ? ' · '.$stats['pending_grade'] : '' ?></span></a>
    <a class="quick-action" href="<?= url('/teacher/results.php') ?>"><?= oems_icon('award') ?><span>Results</span></a>
</div>

<div class="grid grid-4 stagger-in" style="margin-bottom:18px">
    <div class="stat-card" style="--i:0">
        <div class="stat-icon"><?= oems_icon('help') ?></div>
        <div class="stat-label">Questions</div>
        <div class="stat-value"><?= $stats['questions'] ?></div>
        <div class="stat-meta">In your bank</div>
    </div>
    <div class="stat-card" style="--i:1">
        <div class="stat-icon"><?= oems_icon('clipboard') ?></div>
        <div class="stat-label">Exams</div>
        <div class="stat-value"><?= $stats['exams'] ?></div>
        <div class="stat-meta">Created by you</div>
    </div>
    <div class="stat-card" style="--i:2">
        <div class="stat-icon"><?= oems_icon('edit') ?></div>
        <div class="stat-label">Pending grading</div>
        <div class="stat-value"><?= $stats['pending_grade'] ?></div>
        <div class="stat-meta"><a href="<?= url('/teacher/grading.php') ?>">Open queue →</a></div>
    </div>
    <div class="stat-card" style="--i:3">
        <div class="stat-icon"><?= oems_icon('award') ?></div>
        <div class="stat-label">Published results</div>
        <div class="stat-value"><?= $stats['published'] ?></div>
        <div class="stat-meta">Visible to students</div>
    </div>
</div>

<section class="panel reveal">
    <div class="panel-header"><h2>Your exams</h2><a class="btn btn-sm btn-primary" href="<?= url('/teacher/exams.php') ?>">Manage</a></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Title</th><th>Subject</th><th>Schedule</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$exams): ?><tr><td colspan="4"><div class="empty-state"><div class="empty-icon"><?= oems_icon('clipboard') ?></div><h3>No exams yet</h3><p>Create your first exam from the Exams page.</p></div></td></tr><?php endif; ?>
            <?php foreach ($exams as $ex): ?>
                <tr>
                    <td><strong><?= e($ex['title']) ?></strong></td>
                    <td><?= e($ex['subject_name']) ?></td>
                    <td><?= e(format_datetime($ex['start_time'])) ?></td>
                    <td><span class="badge badge-brand"><?= e($ex['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_student();
$user = $ctx['user'];
$student = $ctx['student'];
$sid = (int)$student['id'];

$papers = db()->prepare(
    "SELECT e.*, s.name AS subject_name, s.code AS subject_code,
            ea.status AS attempt_status, ea.submitted_at
     FROM exams e
     JOIN subjects s ON s.id=e.subject_id
     LEFT JOIN exam_attempts ea ON ea.exam_id=e.id AND ea.student_id=?
     WHERE e.approval_status='approved' AND e.status IN ('scheduled','active','completed')
     ORDER BY e.start_time ASC"
);
$papers->execute([$sid]);
$papers = $papers->fetchAll();

$counts = [
    'available' => 0,
    'upcoming' => 0,
    'expired' => 0,
    'completed' => 0,
    'published' => 0,
];
foreach ($papers as $ex) {
    $win = exam_window_status($ex);
    $done = in_array($ex['attempt_status'] ?? '', ['submitted', 'auto_submitted'], true);
    if ($done) {
        $counts['completed']++;
    } elseif ($win === 'available') {
        $counts['available']++;
    } elseif ($win === 'upcoming') {
        $counts['upcoming']++;
    } elseif ($win === 'ended') {
        $counts['expired']++;
    }
}
$pub = db()->prepare("SELECT COUNT(*) FROM results WHERE student_id=? AND status='published'");
$pub->execute([$sid]);
$counts['published'] = (int)$pub->fetchColumn();
$totalRelevant = max(1, $counts['available'] + $counts['upcoming'] + $counts['completed'] + $counts['expired']);
$completionRate = (int)round(($counts['completed'] / $totalRelevant) * 100);

$results = db()->prepare(
    "SELECT r.*, e.title FROM results r JOIN exams e ON e.id=r.exam_id
     WHERE r.student_id=? AND r.status='published' ORDER BY r.published_at DESC LIMIT 5"
);
$results->execute([$sid]);
$results = $results->fetchAll();

require_once dirname(__DIR__) . '/includes/gpa_helpers.php';
$gpaSummary = compute_student_gpa($sid);

$pageTitle = 'Dashboard';
$pageSubtitle = 'Welcome back, ' . ($student['full_name'] ?? $user['full_name']);
$activeNav = 'dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="portal-hero portal-hero-student reveal is-visible" style="--portal-banner: url('<?= asset('images/portal-banner.png') ?>')">
    <div class="portal-hero-content">
        <p class="portal-eyebrow">Student workspace</p>
        <h2>Hi, <?= e(explode(' ', $student['full_name'] ?? $user['full_name'])[0]) ?></h2>
        <p><?= $counts['available'] > 0 ? "You have {$counts['available']} paper(s) ready to attempt." : 'Browse available papers and track your results & CGPA.' ?></p>
        <div class="portal-hero-actions">
            <a class="btn btn-primary" href="<?= url('/student/exams.php') ?>">Available Papers</a>
            <a class="btn btn-secondary" href="<?= url('/student/gpa.php') ?>">SGPA / CGPA</a>
        </div>
    </div>
    <div class="portal-hero-visual" aria-hidden="true">
        <div class="portal-orb"></div>
        <div class="portal-orb portal-orb-2"></div>
    </div>
</section>

<div class="grid grid-3 stagger-in" style="margin-bottom:18px">
    <div class="stat-card" style="--i:0"><div class="stat-icon"><?= oems_icon('clipboard') ?></div><div class="stat-label">Available Exams</div><div class="stat-value"><?= $counts['available'] ?></div><div class="stat-meta">Exams you can take now</div></div>
    <div class="stat-card" style="--i:1"><div class="stat-icon"><?= oems_icon('clock') ?></div><div class="stat-label">Upcoming</div><div class="stat-value"><?= $counts['upcoming'] ?></div><div class="stat-meta">Starting soon</div></div>
    <div class="stat-card" style="--i:2"><div class="stat-icon"><?= oems_icon('activity') ?></div><div class="stat-label">Expired / Missed</div><div class="stat-value"><?= $counts['expired'] ?></div><div class="stat-meta">Time window passed</div></div>
    <div class="stat-card" style="--i:3"><div class="stat-icon"><?= oems_icon('check') ?></div><div class="stat-label">Completed</div><div class="stat-value"><?= $counts['completed'] ?></div><div class="stat-meta">Successfully submitted</div></div>
    <div class="stat-card" style="--i:4"><div class="stat-icon"><?= oems_icon('award') ?></div><div class="stat-label">Published Results</div><div class="stat-value"><?= $counts['published'] ?></div><div class="stat-meta">Evaluations completed</div></div>
    <div class="stat-card" style="--i:5"><div class="stat-icon"><?= oems_icon('chart') ?></div><div class="stat-label">CGPA</div><div class="stat-value"><?= e(format_gpa($gpaSummary['cgpa'])) ?></div><div class="stat-meta"><a href="<?= url('/student/gpa.php') ?>">View SGPA / CGPA →</a></div></div>
</div>

<div class="grid grid-2">
    <section class="panel reveal">
        <div class="panel-header"><h2>Available papers</h2><a class="btn btn-sm btn-secondary" href="<?= url('/student/exams.php') ?>">View all</a></div>
        <div class="panel-body list-feed">
            <?php
            $shown = 0;
            foreach ($papers as $ex):
                $win = exam_window_status($ex);
                $done = in_array($ex['attempt_status'] ?? '', ['submitted','auto_submitted'], true);
                if ($done || $win !== 'available') continue;
                $shown++;
            ?>
                <div class="feed-item exam-feed-item">
                    <div>
                        <strong><?= e($ex['title']) ?></strong>
                        <span><?= e($ex['subject_code']) ?> · <?= (int)$ex['duration_minutes'] ?> min</span>
                    </div>
                    <a class="btn btn-sm btn-primary" href="<?= url('/student/attempt.php?exam_id='.(int)$ex['id']) ?>">Start</a>
                </div>
            <?php endforeach; ?>
            <?php if (!$shown): ?><div class="empty-state"><h3>No open papers</h3><p>Check back when a paper is approved and available.</p></div><?php endif; ?>
        </div>
    </section>
    <section class="panel reveal">
        <div class="panel-header"><h2>Recent results</h2><a class="btn btn-sm btn-secondary" href="<?= url('/student/results.php') ?>">All results</a></div>
        <div class="panel-body list-feed">
            <?php if (!$results): ?><div class="empty-state"><p>Published results will appear here.</p></div><?php endif; ?>
            <?php foreach ($results as $r): ?>
                <div class="feed-item" style="justify-content:space-between">
                    <div><strong><?= e($r['title']) ?></strong><span><?= e($r['obtained_marks'].'/'.$r['total_marks']) ?></span></div>
                    <span class="badge badge-accent"><?= e($r['grade']) ?> · <?= e((string)$r['percentage']) ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

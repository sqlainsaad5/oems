<?php
/**
 * Admin Dashboard
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

$stats = [
    'users' => (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'students' => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
    'teachers' => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn(),
    'courses' => (int)db()->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
    'subjects' => (int)db()->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),
    'exams' => (int)db()->query('SELECT COUNT(*) FROM exams')->fetchColumn(),
    'mega' => (int)db()->query('SELECT COUNT(*) FROM mega_exams')->fetchColumn(),
    'pending' => count_pending_paper_approvals(),
    'questions' => (int)db()->query('SELECT COUNT(*) FROM questions')->fetchColumn(),
    'results' => (int)db()->query("SELECT COUNT(*) FROM results WHERE status='published'")->fetchColumn(),
];

$recentLogs = db()->query(
    'SELECT l.*, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT 8'
)->fetchAll();

$upcoming = db()->query(
    "SELECT e.*, s.name AS subject_name FROM exams e JOIN subjects s ON s.id = e.subject_id
     WHERE e.approval_status='approved' AND e.status IN ('scheduled','active')
       AND (e.availability_mode='always' OR e.end_time >= NOW())
     ORDER BY e.start_time ASC LIMIT 5"
)->fetchAll();

$pageTitle = 'Dashboard';
$pageSubtitle = 'Institution overview and recent activity';
$activeNav = 'dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="portal-hero reveal is-visible" style="--portal-banner: url('<?= asset('images/portal-banner.png') ?>')">
    <div class="portal-hero-content">
        <p class="portal-eyebrow">Admin workspace</p>
        <h2>Welcome back, <?= e(explode(' ', $user['full_name'])[0]) ?></h2>
        <p>Monitor users, courses, and live examinations from one calm control center.</p>
        <div class="portal-hero-actions">
            <a class="btn btn-primary" href="<?= url('/admin/users.php?role=student') ?>">Manage students</a>
            <a class="btn btn-secondary" href="<?= url('/admin/reports.php') ?>">View reports</a>
        </div>
    </div>
    <div class="portal-hero-visual" aria-hidden="true">
        <div class="portal-orb"></div>
        <div class="portal-orb portal-orb-2"></div>
    </div>
</section>

<div class="quick-actions reveal is-visible">
    <a class="quick-action" href="<?= url('/admin/mega_exams.php') ?>"><?= oems_icon('clipboard') ?><span>Mega Exams</span></a>
    <a class="quick-action" href="<?= url('/admin/paper_approvals.php') ?>"><?= oems_icon('check') ?><span>Approvals</span></a>
    <a class="quick-action" href="<?= url('/admin/departments.php') ?>"><?= oems_icon('layers') ?><span>Departments</span></a>
    <a class="quick-action" href="<?= url('/admin/users.php?role=student') ?>"><?= oems_icon('users') ?><span>Students</span></a>
    <a class="quick-action" href="<?= url('/admin/users.php?role=teacher') ?>"><?= oems_icon('user') ?><span>Teachers</span></a>
</div>

<div class="grid grid-4 stagger-in" style="margin-bottom:18px">
    <div class="stat-card" style="--i:0">
        <div class="stat-icon"><?= oems_icon('users') ?></div>
        <div class="stat-label">Total Students</div>
        <div class="stat-value" data-count="<?= $stats['students'] ?>"><?= $stats['students'] ?></div>
        <div class="stat-meta"><a href="<?= url('/admin/users.php?role=student') ?>">View students →</a></div>
    </div>
    <div class="stat-card" style="--i:1">
        <div class="stat-icon"><?= oems_icon('users') ?></div>
        <div class="stat-label">Total Teachers</div>
        <div class="stat-value" data-count="<?= $stats['teachers'] ?>"><?= $stats['teachers'] ?></div>
        <div class="stat-meta"><a href="<?= url('/admin/users.php?role=teacher') ?>">View teachers →</a></div>
    </div>
    <div class="stat-card" style="--i:2">
        <div class="stat-icon"><?= oems_icon('check') ?></div>
        <div class="stat-label">Pending Approvals</div>
        <div class="stat-value" data-count="<?= $stats['pending'] ?>"><?= $stats['pending'] ?></div>
        <div class="stat-meta"><a href="<?= url('/admin/paper_approvals.php') ?>">Review papers →</a></div>
    </div>
    <div class="stat-card" style="--i:3">
        <div class="stat-icon"><?= oems_icon('award') ?></div>
        <div class="stat-label">Published Results</div>
        <div class="stat-value" data-count="<?= $stats['results'] ?>"><?= $stats['results'] ?></div>
        <div class="stat-meta"><?= $stats['mega'] ?> mega · <?= $stats['exams'] ?> papers</div>
    </div>
</div>

<div class="grid grid-2">
    <section class="panel reveal">
        <div class="panel-header">
            <h2>Upcoming exams</h2>
            <a class="btn btn-sm btn-secondary" href="<?= url('/admin/exams.php') ?>">View all</a>
        </div>
        <div class="panel-body">
            <?php if (!$upcoming): ?>
                <div class="empty-state"><div class="empty-icon"><?= oems_icon('clipboard') ?></div><h3>No upcoming exams</h3><p>Teachers can schedule exams from their portal.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Exam</th><th>Subject</th><th>Window</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($upcoming as $ex): ?>
                            <tr>
                                <td><strong><?= e($ex['title']) ?></strong></td>
                                <td><?= e($ex['subject_name']) ?></td>
                                <td><?= e(format_datetime($ex['start_time'], 'M j, g:i A')) ?></td>
                                <td><span class="badge badge-brand"><?= e(exam_window_status($ex)) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel reveal">
        <div class="panel-header">
            <h2>System activity</h2>
            <a class="btn btn-sm btn-secondary" href="<?= url('/admin/activity.php') ?>">Full log</a>
        </div>
        <div class="panel-body">
            <div class="list-feed">
                <?php foreach ($recentLogs as $log): ?>
                    <div class="feed-item">
                        <div>
                            <strong><?= e($log['action']) ?></strong>
                            <span><?= e($log['full_name'] ?: 'System') ?> · <?= e(format_datetime($log['created_at'])) ?></span>
                            <?php if ($log['details']): ?><p style="margin:6px 0 0;font-size:.85rem"><?= e($log['details']) ?></p><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

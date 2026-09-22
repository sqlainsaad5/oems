<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_teacher();
$user = $ctx['user'];
$teacher = $ctx['teacher'];
$tid = (int)$teacher['id'];

$perf = db()->prepare(
    "SELECT e.title, COUNT(r.id) AS n, ROUND(AVG(r.percentage),1) AS avg_pct,
            SUM(CASE WHEN r.percentage >= 40 THEN 1 ELSE 0 END) AS passed
     FROM exams e
     LEFT JOIN results r ON r.exam_id=e.id
     WHERE e.teacher_id=?
     GROUP BY e.id ORDER BY e.start_time DESC"
);
$perf->execute([$tid]);
$perf = $perf->fetchAll();

$top = db()->prepare(
    "SELECT u.full_name, st.student_id AS roll, r.percentage, e.title
     FROM results r
     JOIN exams e ON e.id=r.exam_id
     JOIN students st ON st.id=r.student_id
     JOIN users u ON u.id=st.user_id
     WHERE e.teacher_id=? AND r.status='published'
     ORDER BY r.percentage DESC LIMIT 10"
);
$top->execute([$tid]);
$top = $top->fetchAll();

$pageTitle = 'Performance';
$pageSubtitle = 'Student performance analytics';
$activeNav = 'performance';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid grid-2">
    <section class="panel">
        <div class="panel-header"><h2>Per-exam averages</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Exam</th><th>Students</th><th>Avg %</th><th>Passed</th></tr></thead>
                <tbody>
                <?php foreach ($perf as $p): ?>
                    <tr>
                        <td><strong><?= e($p['title']) ?></strong></td>
                        <td><?= (int)$p['n'] ?></td>
                        <td><?= e((string)($p['avg_pct'] ?? '—')) ?></td>
                        <td><?= (int)$p['passed'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><h2>Top performers</h2></div>
        <div class="panel-body list-feed">
            <?php if (!$top): ?><div class="empty-state"><p>Publish results to see rankings.</p></div><?php endif; ?>
            <?php foreach ($top as $t): ?>
                <div class="feed-item" style="justify-content:space-between">
                    <div><strong><?= e($t['full_name']) ?></strong><span><?= e($t['roll']) ?> · <?= e($t['title']) ?></span></div>
                    <span class="badge badge-success"><?= e((string)$t['percentage']) ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
/**
 * Admin — Reports (stats + Excel/PDF export)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

$export = (string)request('export', '');
$examId = (int)request('exam_id', 0);

if ($export && $examId) {
    $exam = db()->prepare(
        'SELECT e.*, s.name AS subject_name FROM exams e JOIN subjects s ON s.id=e.subject_id WHERE e.id=?'
    );
    $exam->execute([$examId]);
    $exam = $exam->fetch();
    if (!$exam) {
        flash('error', 'Exam not found.');
        redirect('/admin/reports.php');
    }
    $rows = db()->prepare(
        "SELECT r.*, u.full_name, st.student_id AS roll
         FROM results r
         JOIN students st ON st.id=r.student_id
         JOIN users u ON u.id=st.user_id
         WHERE r.exam_id=? ORDER BY r.percentage DESC"
    );
    $rows->execute([$examId]);
    $rows = $rows->fetchAll();

    if ($export === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="exam_' . $examId . '_results.xls"');
        echo "Student ID\tName\tObtained\tTotal\tPercentage\tGrade\tStatus\n";
        foreach ($rows as $r) {
            echo implode("\t", [
                $r['roll'], $r['full_name'], $r['obtained_marks'], $r['total_marks'],
                $r['percentage'], $r['grade'], $r['status'],
            ]) . "\n";
        }
        exit;
    }

    if ($export === 'pdf') {
        // Lightweight printable HTML PDF (browser print / Save as PDF)
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html><head><title>Result Report — <?= e($exam['title']) ?></title>
        <style>
            body{font-family:Georgia,serif;padding:32px;color:#0b1220}
            h1{font-size:22px;margin:0 0 6px}
            .meta{color:#64748b;margin-bottom:24px}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #cbd5e1;padding:8px 10px;text-align:left;font-size:13px}
            th{background:#f1f5f9}
            @media print{.no-print{display:none}}
        </style></head><body>
        <button class="no-print" onclick="window.print()">Print / Save PDF</button>
        <h1><?= e($exam['title']) ?></h1>
        <div class="meta"><?= e($exam['subject_name']) ?> · Generated <?= e(date('M j, Y g:i A')) ?></div>
        <table>
            <thead><tr><th>#</th><th>Student ID</th><th>Name</th><th>Score</th><th>%</th><th>Grade</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= e($r['roll']) ?></td>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['obtained_marks'].'/'.$r['total_marks']) ?></td>
                    <td><?= e($r['percentage']) ?></td>
                    <td><?= e($r['grade']) ?></td>
                    <td><?= e($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <script>window.print()</script>
        </body></html>
        <?php
        exit;
    }
}

$exams = db()->query('SELECT id, title FROM exams ORDER BY start_time DESC')->fetchAll();
$perf = db()->query(
    "SELECT e.title, COUNT(r.id) AS attempts,
            ROUND(AVG(r.percentage),1) AS avg_pct,
            ROUND(MAX(r.percentage),1) AS max_pct,
            ROUND(MIN(r.percentage),1) AS min_pct
     FROM exams e
     LEFT JOIN results r ON r.exam_id=e.id
     GROUP BY e.id ORDER BY e.start_time DESC LIMIT 10"
)->fetchAll();
$gradeDist = db()->query(
    "SELECT grade, COUNT(*) AS c FROM results WHERE status='published' AND grade IS NOT NULL GROUP BY grade ORDER BY grade"
)->fetchAll();

$pageTitle = 'Reports';
$pageSubtitle = 'Performance statistics and exports';
$activeNav = 'reports';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="grid grid-2" style="margin-bottom:16px">
    <section class="panel">
        <div class="panel-header"><h2>Export results</h2></div>
        <div class="panel-body">
            <form method="get" class="grid" style="gap:12px">
                <div class="form-group" style="margin:0">
                    <label>Exam</label>
                    <select class="form-select" name="exam_id" required>
                        <option value="">Select exam…</option>
                        <?php foreach ($exams as $ex): ?>
                            <option value="<?= (int)$ex['id'] ?>"><?= e($ex['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cta-row">
                    <button class="btn btn-primary" name="export" value="excel" type="submit">Export Excel</button>
                    <button class="btn btn-secondary" name="export" value="pdf" type="submit">Export PDF</button>
                </div>
            </form>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><h2>Grade distribution</h2></div>
        <div class="panel-body">
            <?php if (!$gradeDist): ?>
                <div class="empty-state"><p>No published results yet.</p></div>
            <?php else: ?>
                <div class="list-feed">
                    <?php foreach ($gradeDist as $g): ?>
                        <div class="feed-item" style="justify-content:space-between;align-items:center">
                            <strong>Grade <?= e($g['grade']) ?></strong>
                            <span class="badge badge-brand"><?= (int)$g['c'] ?> students</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="panel">
    <div class="panel-header"><h2>Exam performance statistics</h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Exam</th><th>Attempts</th><th>Avg %</th><th>Max %</th><th>Min %</th></tr></thead>
            <tbody>
            <?php foreach ($perf as $p): ?>
                <tr>
                    <td><strong><?= e($p['title']) ?></strong></td>
                    <td><?= (int)$p['attempts'] ?></td>
                    <td><?= e((string)($p['avg_pct'] ?? '—')) ?></td>
                    <td><?= e((string)($p['max_pct'] ?? '—')) ?></td>
                    <td><?= e((string)($p['min_pct'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

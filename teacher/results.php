<?php
/**
 * Teacher — Results with Mega Exam filters, rank, PASS/FAIL, publish
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_teacher();
$user = $ctx['user'];
$teacher = $ctx['teacher'];
$tid = (int)$teacher['id'];

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');
    $examId = (int)request('exam_id');
    $own = db()->prepare('SELECT * FROM exams WHERE id=? AND teacher_id=?');
    $own->execute([$examId, $tid]);
    $exam = $own->fetch();
    if (!$exam) {
        flash('error', 'Paper not found.');
        redirect('/teacher/results.php');
    }

    if ($action === 'recalculate') {
        $attempts = db()->prepare("SELECT student_id FROM exam_attempts WHERE exam_id=? AND status IN ('submitted','auto_submitted')");
        $attempts->execute([$examId]);
        foreach ($attempts->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            $sum = db()->prepare('SELECT COALESCE(SUM(marks_obtained),0) FROM student_answers WHERE exam_id=? AND student_id=? AND is_graded=1');
            $sum->execute([$examId, $sid]);
            $obtained = (float)$sum->fetchColumn();
            $total = (float)$exam['total_marks'];
            $pct = $total > 0 ? round(($obtained / $total) * 100, 2) : 0;
            db()->prepare(
                'INSERT INTO results (exam_id, student_id, total_marks, obtained_marks, percentage, grade, status)
                 VALUES (?,?,?,?,?,?, "pending")
                 ON DUPLICATE KEY UPDATE obtained_marks=VALUES(obtained_marks), percentage=VALUES(percentage), grade=VALUES(grade), total_marks=VALUES(total_marks)'
            )->execute([$examId, $sid, $total, $obtained, $pct, calculate_grade($pct)]);
        }
        flash('success', 'Results recalculated from graded answers.');
    }

    if ($action === 'publish') {
        db()->prepare('UPDATE results SET status="published", published_at=NOW() WHERE exam_id=?')->execute([$examId]);
        db()->prepare('UPDATE exams SET results_published=1 WHERE id=?')->execute([$examId]);
        $students = db()->prepare(
            'SELECT st.user_id FROM results r JOIN students st ON st.id=r.student_id WHERE r.exam_id=?'
        );
        $students->execute([$examId]);
        foreach ($students->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            create_notification((int)$uid, 'Results published: ' . $exam['title'], 'Your results are now available.', 'result', '/student/results.php');
        }
        flash('success', 'Results published and students notified.');
        log_activity((int)$user['id'], 'publish_results', "Exam #$examId");
    }
    $q = http_build_query(array_filter([
        'exam_id' => $examId,
        'mega' => (int)request('mega') ?: null,
        'department_id' => (int)request('department_id') ?: null,
    ]));
    redirect('/teacher/results.php?' . $q);
}

$megaId = (int)request('mega', 0);
$departmentId = (int)request('department_id', 0);
$examId = (int)request('exam_id', 0);

$megas = db()->prepare(
    "SELECT DISTINCT m.id, m.title, m.code
     FROM mega_exams m
     JOIN exams e ON e.mega_exam_id=m.id
     WHERE e.teacher_id=?
     ORDER BY m.title"
);
$megas->execute([$tid]);
$megas = $megas->fetchAll();

$departments = db()->query("SELECT id, name, code FROM departments WHERE status='active' ORDER BY name")->fetchAll();

$paperSql = "SELECT e.id, e.title, e.semester, e.total_marks, e.passing_marks, e.mega_exam_id, e.department_id,
                    s.name AS subject_name, s.code AS subject_code, m.title AS mega_title, m.code AS mega_code
             FROM exams e
             JOIN subjects s ON s.id=e.subject_id
             LEFT JOIN mega_exams m ON m.id=e.mega_exam_id
             WHERE e.teacher_id=? AND e.approval_status='approved'";
$paperParams = [$tid];
if ($megaId) {
    $paperSql .= ' AND e.mega_exam_id=?';
    $paperParams[] = $megaId;
}
if ($departmentId) {
    $paperSql .= ' AND e.department_id=?';
    $paperParams[] = $departmentId;
}
$paperSql .= ' ORDER BY e.semester, s.name, e.title';
$pst = db()->prepare($paperSql);
$pst->execute($paperParams);
$papers = $pst->fetchAll();

if (!$examId && $papers) {
    $examId = (int)$papers[0]['id'];
}

$examMeta = null;
$results = [];
$avg = 0.0;
if ($examId) {
    foreach ($papers as $p) {
        if ((int)$p['id'] === $examId) {
            $examMeta = $p;
            break;
        }
    }
    if (!$examMeta) {
        $own = db()->prepare(
            "SELECT e.*, s.name AS subject_name, s.code AS subject_code, m.title AS mega_title, m.code AS mega_code
             FROM exams e JOIN subjects s ON s.id=e.subject_id
             LEFT JOIN mega_exams m ON m.id=e.mega_exam_id
             WHERE e.id=? AND e.teacher_id=?"
        );
        $own->execute([$examId, $tid]);
        $examMeta = $own->fetch() ?: null;
    }
    if ($examMeta) {
        $rs = db()->prepare(
            "SELECT r.*, e.passing_marks, u.full_name, st.student_id AS roll
             FROM results r
             JOIN students st ON st.id=r.student_id
             JOIN users u ON u.id=st.user_id
             JOIN exams e ON e.id=r.exam_id
             WHERE r.exam_id=? AND e.teacher_id=?
             ORDER BY r.percentage DESC, r.obtained_marks DESC, st.student_id ASC"
        );
        $rs->execute([$examId, $tid]);
        $results = attach_result_ranks($rs->fetchAll());
        if ($results) {
            $avg = round(array_sum(array_map(fn($r) => (float)$r['percentage'], $results)) / count($results), 2);
        }
    }
}

$export = (string)request('export', '');
if ($export === 'excel' && $examId && $results) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="results_exam_' . $examId . '.xls"');
    echo "Position\tRoll\tName\tPaper\tMarks\tTotal\t%\tGrade\tResult\tStatus\n";
    foreach ($results as $r) {
        echo "{$r['rank_label']}\t{$r['roll']}\t{$r['full_name']}\t" . ($examMeta['title'] ?? '') . "\t{$r['obtained_marks']}\t{$r['total_marks']}\t{$r['percentage']}\t{$r['grade']}\t{$r['pass_fail']}\t{$r['status']}\n";
    }
    exit;
}

$pageTitle = 'Results';
$pageSubtitle = 'Filter by Mega Exam, view ranks, and publish';
$activeNav = 'results';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel" style="margin-bottom:14px">
    <div class="panel-header"><h2>Filters</h2></div>
    <div class="panel-body">
        <form method="get" class="form-row" style="align-items:end">
            <div class="form-group" style="margin:0">
                <label>Mega Exam</label>
                <select class="form-select" name="mega" onchange="this.form.submit()">
                    <option value="0">All</option>
                    <?php foreach ($megas as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= $megaId===(int)$m['id']?'selected':'' ?>><?= e($m['title'].' ('.$m['code'].')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label>Department</label>
                <select class="form-select" name="department_id" onchange="this.form.submit()">
                    <option value="0">All</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $departmentId===(int)$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label>Paper / Subject</label>
                <select class="form-select" name="exam_id" onchange="this.form.submit()" style="min-width:240px">
                    <?php if (!$papers): ?><option value="0">No papers</option><?php endif; ?>
                    <?php foreach ($papers as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $examId===(int)$p['id']?'selected':'' ?>>
                            <?= e(($p['subject_code'] ?? '') . ' — ' . $p['title'] . ($p['semester'] ? ' (Sem '.$p['semester'].')' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($megaId): ?><input type="hidden" name="mega" value="<?= $megaId ?>"><?php endif; ?>
        </form>
    </div>
</section>

<?php if ($examMeta): ?>
<div class="toolbar">
    <div>
        <strong><?= e($examMeta['title']) ?></strong>
        <?php if (!empty($examMeta['mega_title'])): ?>
            <span class="badge badge-info"><?= e($examMeta['mega_title']) ?> (<?= e($examMeta['mega_code']) ?>)</span>
        <?php endif; ?>
        <span class="badge"><?= count($results) ?> student<?= count($results)===1?'':'s' ?></span>
        <span class="badge badge-brand">Avg: <?= e((string)$avg) ?>%</span>
    </div>
    <div class="toolbar-right">
        <a class="btn btn-secondary" href="?exam_id=<?= $examId ?>&mega=<?= $megaId ?>&department_id=<?= $departmentId ?>&export=excel">Export Excel</a>
        <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="exam_id" value="<?= $examId ?>">
            <input type="hidden" name="mega" value="<?= $megaId ?>">
            <input type="hidden" name="department_id" value="<?= $departmentId ?>">
            <input type="hidden" name="action" value="recalculate">
            <button class="btn btn-secondary" type="submit">Recalculate</button>
        </form>
        <form method="post" style="display:inline" data-confirm="Publish results to students?">
            <?= csrf_field() ?>
            <input type="hidden" name="exam_id" value="<?= $examId ?>">
            <input type="hidden" name="mega" value="<?= $megaId ?>">
            <input type="hidden" name="department_id" value="<?= $departmentId ?>">
            <input type="hidden" name="action" value="publish">
            <button class="btn btn-primary" type="submit">Publish results</button>
        </form>
    </div>
</div>
<?php endif; ?>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Position</th><th>Roll No</th><th>Student Name</th><th>Paper</th><th>Marks</th><th>Total</th><th>Percentage</th><th>Status</th><th>Publish</th></tr></thead>
            <tbody>
            <?php if (!$results): ?><tr><td colspan="9"><div class="empty-state"><h3>No results</h3><p>Submit attempts and grade descriptive answers, then recalculate.</p></div></td></tr><?php endif; ?>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><span class="badge badge-accent"><?= e($r['rank_label']) ?></span></td>
                    <td><?= e($r['roll']) ?></td>
                    <td><strong><?= e($r['full_name']) ?></strong></td>
                    <td><a href="<?= url('/teacher/grading.php?session_id='.(int)$examId.'&student_id='.(int)$r['student_id']) ?>"><?= e($examMeta['title'] ?? 'Paper') ?></a></td>
                    <td><?= e((string)$r['obtained_marks']) ?></td>
                    <td><?= e((string)$r['total_marks']) ?></td>
                    <td style="color:<?= $r['pass_fail']==='PASS' ? 'var(--success)' : 'var(--danger)' ?>;font-weight:600"><?= e((string)$r['percentage']) ?>%</td>
                    <td><span class="badge <?= $r['pass_fail']==='PASS'?'badge-success':'badge-danger' ?>"><?= e($r['pass_fail']) ?></span></td>
                    <td><span class="badge <?= $r['status']==='published'?'badge-success':'badge-warning' ?>"><?= e($r['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

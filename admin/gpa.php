<?php
/**
 * Admin — Student SGPA / CGPA overview
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/gpa_helpers.php';
$user = require_admin();

$studentId = (int)request('student_id', 0);
$students = db()->query(
    "SELECT st.id, st.student_id AS roll, st.semester, u.full_name, c.code AS course_code
     FROM students st
     JOIN users u ON u.id=st.user_id
     LEFT JOIN courses c ON c.id=st.course_id
     ORDER BY u.full_name"
)->fetchAll();

$gpa = null;
$selected = null;
if ($studentId) {
    foreach ($students as $s) {
        if ((int)$s['id'] === $studentId) {
            $selected = $s;
            break;
        }
    }
    if ($selected) {
        $gpa = compute_student_gpa($studentId);
    }
}

$pageTitle = 'SGPA / CGPA';
$pageSubtitle = 'View calculated semester and cumulative GPA for students';
$activeNav = 'gpa';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel" style="margin-bottom:16px">
    <div class="panel-header"><h2>Select student</h2></div>
    <div class="panel-body">
        <form method="get" class="form-row" style="align-items:end">
            <div class="form-group" style="margin:0;flex:1">
                <label>Student</label>
                <select class="form-select" name="student_id" required onchange="this.form.submit()">
                    <option value="">— Choose —</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $studentId===(int)$s['id']?'selected':'' ?>>
                            <?= e($s['full_name'].' ('.$s['roll'].')'.($s['course_code'] ? ' · '.$s['course_code'] : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Calculate</button>
        </form>
    </div>
</section>

<?php if ($selected && $gpa): ?>
<div class="grid grid-3" style="margin-bottom:16px">
    <div class="stat-card">
        <div class="stat-label">Student</div>
        <div class="stat-value" style="font-size:1.2rem"><?= e($selected['full_name']) ?></div>
        <div class="stat-meta"><?= e($selected['roll']) ?> · Sem <?= (int)$selected['semester'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">CGPA</div>
        <div class="stat-value"><?= e(format_gpa($gpa['cgpa'])) ?></div>
        <div class="stat-meta"><?= e((string)$gpa['total_credits']) ?> credits</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Semesters</div>
        <div class="stat-value"><?= count($gpa['semesters']) ?></div>
        <div class="stat-meta"><?= count($gpa['courses']) ?> subjects</div>
    </div>
</div>

<?php if (!$gpa['semesters']): ?>
<section class="panel"><div class="empty-state"><h3>No published results</h3><p>Publish exam results first to calculate GPA.</p></div></section>
<?php else: ?>
    <?php foreach ($gpa['semesters'] as $sem): ?>
    <section class="panel" style="margin-bottom:14px">
        <div class="panel-header">
            <h2>Semester <?= (int)$sem['semester'] ?></h2>
            <span class="badge badge-brand">SGPA <?= e(format_gpa($sem['sgpa'])) ?></span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Code</th><th>Subject</th><th>Grade</th><th>GP</th><th>Credits</th><th>QP</th></tr></thead>
                <tbody>
                <?php foreach ($sem['courses'] as $c): ?>
                    <tr>
                        <td><?= e($c['subject_code']) ?></td>
                        <td><?= e($c['subject_name']) ?></td>
                        <td><span class="badge badge-accent"><?= e($c['grade']) ?></span></td>
                        <td><?= e(number_format($c['grade_points'], 2)) ?></td>
                        <td><?= e(number_format($c['credit_hours'], 1)) ?></td>
                        <td><?= e(number_format($c['quality_points'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

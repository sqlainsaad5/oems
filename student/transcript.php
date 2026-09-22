<?php
/**
 * Student — UC05 Generate Transcript (print / Save as PDF)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/gpa_helpers.php';

$ctx = require_student();
$user = $ctx['user'];
$student = $ctx['student'];
$sid = (int)$student['id'];

$gpa = compute_student_gpa($sid);
$hasResults = count($gpa['courses']) > 0;

// Enrich student profile (department / batch if present)
$extra = db()->prepare(
    "SELECT st.*, u.full_name, u.email, c.name AS course_name, c.code AS course_code,
            d.name AS department_name, b.name AS batch_name
     FROM students st
     JOIN users u ON u.id=st.user_id
     LEFT JOIN courses c ON c.id=st.course_id
     LEFT JOIN departments d ON d.id=st.department_id
     LEFT JOIN batches b ON b.id=st.batch_id
     WHERE st.id=?"
);
$extra->execute([$sid]);
$profile = $extra->fetch() ?: $student;

$institution = get_setting('institution_name', APP_FULL_NAME);
$print = (string)request('print', '') === '1';

if (!$hasResults) {
    flash('error', 'No results available. Transcript can be generated only after published results exist.');
    redirect('/student/results.php');
}

$pageTitle = 'Official Transcript';
$pageSubtitle = 'Generate and download your academic transcript (PDF)';
$activeNav = 'transcript';
$bodyClass = $print ? 'transcript-print' : '';
require dirname(__DIR__) . '/includes/header.php';
?>

<?php if (!$print): ?>
<div class="toolbar no-print">
    <div>
        <p style="margin:0;color:var(--muted);font-size:.92rem">Preview below. Use <strong>Print / Save as PDF</strong> for an official copy.</p>
    </div>
    <div class="toolbar-right">
        <a class="btn btn-secondary" href="<?= url('/student/gpa.php') ?>">SGPA / CGPA</a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><?= oems_icon('clipboard') ?> Print / Save as PDF</button>
    </div>
</div>
<?php endif; ?>

<article class="transcript-sheet" id="transcriptSheet">
    <header class="transcript-head">
        <div>
            <p class="transcript-eyebrow"><?= e(APP_NAME) ?></p>
            <h1><?= e($institution) ?></h1>
            <p class="transcript-sub">Official Academic Transcript</p>
        </div>
        <div class="transcript-meta-box">
            <div><span>Issued</span><strong><?= e(date('M j, Y')) ?></strong></div>
            <div><span>Document</span><strong>TR-<?= e($profile['student_id'] ?? '') ?>-<?= e(date('Ymd')) ?></strong></div>
        </div>
    </header>

    <section class="transcript-student">
        <h2>Student information</h2>
        <div class="transcript-grid">
            <div><span>Full name</span><strong><?= e($profile['full_name'] ?? $user['full_name']) ?></strong></div>
            <div><span>Student / Roll No</span><strong><?= e($profile['student_id'] ?? '') ?></strong></div>
            <div><span>Email</span><strong><?= e($profile['email'] ?? $user['email']) ?></strong></div>
            <div><span>Course</span><strong><?= e(($profile['course_code'] ?? '') ? ($profile['course_code'].' — '.($profile['course_name'] ?? '')) : '—') ?></strong></div>
            <div><span>Department</span><strong><?= e($profile['department_name'] ?? '—') ?></strong></div>
            <div><span>Batch</span><strong><?= e($profile['batch_name'] ?? '—') ?></strong></div>
            <div><span>Current semester</span><strong><?= e((string)($profile['semester'] ?? '—')) ?></strong></div>
            <div><span>CGPA</span><strong><?= e(format_gpa($gpa['cgpa'])) ?> / 4.00</strong></div>
        </div>
    </section>

    <?php foreach ($gpa['semesters'] as $sem): ?>
    <section class="transcript-sem">
        <div class="transcript-sem-head">
            <h2>Semester <?= (int)$sem['semester'] ?></h2>
            <span>SGPA: <strong><?= e(format_gpa($sem['sgpa'])) ?></strong> · Credits: <?= e((string)$sem['credits']) ?></span>
        </div>
        <table class="transcript-table">
            <thead>
            <tr>
                <th>Code</th>
                <th>Subject</th>
                <th>Paper</th>
                <th>%</th>
                <th>Grade</th>
                <th>GP</th>
                <th>Cr</th>
                <th>QP</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($sem['courses'] as $c): ?>
                <tr>
                    <td><?= e($c['subject_code']) ?></td>
                    <td><?= e($c['subject_name']) ?></td>
                    <td><?= e($c['paper_title']) ?></td>
                    <td><?= e(number_format($c['percentage'], 2)) ?></td>
                    <td><?= e($c['grade']) ?></td>
                    <td><?= e(number_format($c['grade_points'], 2)) ?></td>
                    <td><?= e(number_format($c['credit_hours'], 1)) ?></td>
                    <td><?= e(number_format($c['quality_points'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endforeach; ?>

    <section class="transcript-summary">
        <h2>Cumulative record</h2>
        <div class="transcript-grid">
            <div><span>Total credit hours</span><strong><?= e((string)$gpa['total_credits']) ?></strong></div>
            <div><span>Total quality points</span><strong><?= e((string)$gpa['quality_points']) ?></strong></div>
            <div><span>CGPA</span><strong><?= e(format_gpa($gpa['cgpa'])) ?></strong></div>
            <div><span>Subjects counted</span><strong><?= count($gpa['courses']) ?></strong></div>
        </div>
        <p class="transcript-note">
            Grade scale: A+/A=4.0, A-=3.7, B+=3.3, B=3.0, B-=2.7, C+=2.3, C=2.0, C-=1.7, D=1.0, F=0.0.
            Only published examination results are included. Latest paper per subject per semester is used.
        </p>
    </section>

    <footer class="transcript-foot">
        <div>
            <div class="sign-line"></div>
            <span>Controller of Examinations</span>
        </div>
        <div>
            <div class="sign-line"></div>
            <span>Institution seal / stamp</span>
        </div>
        <p class="transcript-generated">Generated by <?= e(APP_NAME) ?> on <?= e(date('Y-m-d H:i')) ?></p>
    </footer>
</article>

<?php if (!$print): ?>
<script>
// Optional: open print dialog when ?print=1 was intended via button only
</script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

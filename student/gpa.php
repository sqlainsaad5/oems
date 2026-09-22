<?php
/**
 * Student — SGPA / CGPA (from published results)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/gpa_helpers.php';

$ctx = require_student();
$user = $ctx['user'];
$student = $ctx['student'];
$sid = (int)$student['id'];

$gpa = compute_student_gpa($sid);

$pageTitle = 'SGPA / CGPA';
$pageSubtitle = 'Semester GPA and cumulative GPA from published results';
$activeNav = 'gpa';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="grid grid-3 stagger-in" style="margin-bottom:18px">
    <div class="stat-card" style="--i:0">
        <div class="stat-icon"><?= oems_icon('award') ?></div>
        <div class="stat-label">CGPA</div>
        <div class="stat-value"><?= e(format_gpa($gpa['cgpa'])) ?></div>
        <div class="stat-meta">Out of 4.00 · <?= e((string)$gpa['total_credits']) ?> credit hours</div>
    </div>
    <div class="stat-card" style="--i:1">
        <div class="stat-icon"><?= oems_icon('chart') ?></div>
        <div class="stat-label">Quality Points</div>
        <div class="stat-value" style="font-size:1.4rem"><?= e((string)$gpa['quality_points']) ?></div>
        <div class="stat-meta">Σ (Grade points × Credits)</div>
    </div>
    <div class="stat-card" style="--i:2">
        <div class="stat-icon"><?= oems_icon('book') ?></div>
        <div class="stat-label">Subjects counted</div>
        <div class="stat-value"><?= count($gpa['courses']) ?></div>
        <div class="stat-meta"><?= count($gpa['semesters']) ?> semester(s)</div>
    </div>
</div>

<section class="panel" style="margin-bottom:16px">
    <div class="panel-header"><h2>How it is calculated</h2></div>
    <div class="panel-body" style="font-size:.92rem;color:var(--muted)">
        <p style="margin:0 0 8px"><strong>SGPA</strong> = Σ(Grade points × Credit hours) ÷ Σ(Credit hours) for one semester.</p>
        <p style="margin:0 0 8px"><strong>CGPA</strong> = same formula across all semesters (published results only).</p>
        <p style="margin:0">Scale: A+/A = 4.0 · A- = 3.7 · B+ = 3.3 · B = 3.0 · … · D = 1.0 · F = 0.0. Latest published paper per subject/semester is used.</p>
    </div>
</section>

<?php if (!$gpa['semesters']): ?>
<section class="panel">
    <div class="empty-state">
        <div class="empty-icon"><?= oems_icon('award') ?></div>
        <h3>No GPA yet</h3>
        <p>When teachers publish your exam results, SGPA and CGPA will appear here.</p>
        <a class="btn btn-primary" href="<?= url('/student/results.php') ?>">My Results</a>
    </div>
</section>
<?php else: ?>
    <div class="toolbar" style="margin-bottom:12px">
        <div></div>
        <a class="btn btn-primary" href="<?= url('/student/transcript.php') ?>">Generate Transcript (PDF)</a>
    </div>
    <?php foreach ($gpa['semesters'] as $sem): ?>
    <section class="panel" style="margin-bottom:16px">
        <div class="panel-header">
            <h2>Semester <?= (int)$sem['semester'] ?></h2>
            <div class="chip-row">
                <span class="badge badge-brand">SGPA: <?= e(format_gpa($sem['sgpa'])) ?></span>
                <span class="badge"><?= e((string)$sem['credits']) ?> credits</span>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Subject</th>
                    <th>Paper</th>
                    <th>%</th>
                    <th>Grade</th>
                    <th>GP</th>
                    <th>Credits</th>
                    <th>QP</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sem['courses'] as $c): ?>
                    <tr>
                        <td><strong><?= e($c['subject_code']) ?></strong></td>
                        <td><?= e($c['subject_name']) ?></td>
                        <td><?= e($c['paper_title']) ?></td>
                        <td><?= e(number_format($c['percentage'], 2)) ?>%</td>
                        <td><span class="badge badge-accent"><?= e($c['grade']) ?></span></td>
                        <td><?= e(number_format($c['grade_points'], 2)) ?></td>
                        <td><?= e(number_format($c['credit_hours'], 1)) ?></td>
                        <td><?= e(number_format($c['quality_points'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" style="text-align:right;font-weight:600">Semester totals</td>
                        <td><strong><?= e((string)$sem['credits']) ?></strong></td>
                        <td><strong><?= e((string)$sem['quality_points']) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
    <?php endforeach; ?>

    <section class="panel">
        <div class="panel-header"><h2>Cumulative summary</h2></div>
        <div class="panel-body paper-card-stats">
            <div><span>Total credits</span><strong><?= e((string)$gpa['total_credits']) ?></strong></div>
            <div><span>Total quality points</span><strong><?= e((string)$gpa['quality_points']) ?></strong></div>
            <div><span>CGPA</span><strong><?= e(format_gpa($gpa['cgpa'])) ?> / 4.00</strong></div>
        </div>
    </section>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

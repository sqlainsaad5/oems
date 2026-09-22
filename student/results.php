<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_student();
$user = $ctx['user'];
$student = $ctx['student'];
$sid = (int)$student['id'];

$results = db()->prepare(
    "SELECT r.*, e.title, e.start_time, e.passing_marks, e.total_marks AS exam_total,
            s.name AS subject_name, s.code AS subject_code,
            m.title AS mega_title, m.code AS mega_code
     FROM results r
     JOIN exams e ON e.id=r.exam_id
     JOIN subjects s ON s.id=e.subject_id
     LEFT JOIN mega_exams m ON m.id=e.mega_exam_id
     WHERE r.student_id=? AND r.status='published'
     ORDER BY r.published_at DESC"
);
$results->execute([$sid]);
$raw = $results->fetchAll();

// Rank among published peers for same exam
$enriched = [];
foreach ($raw as $r) {
    $peers = db()->prepare(
        "SELECT r.student_id, r.percentage, r.obtained_marks, r.total_marks, e.passing_marks
         FROM results r
         JOIN exams e ON e.id=r.exam_id
         WHERE r.exam_id=? AND r.status='published'
         ORDER BY r.percentage DESC, r.obtained_marks DESC"
    );
    $peers->execute([(int)$r['exam_id']]);
    $peerRows = attach_result_ranks($peers->fetchAll());
    $rankLabel = '—';
    foreach ($peerRows as $p) {
        if ((int)$p['student_id'] === $sid) {
            $rankLabel = $p['rank_label'];
            break;
        }
    }
    $r['rank_label'] = $rankLabel;
    $r['pass_fail'] = exam_pass_fail($r, (float)$r['obtained_marks'], (float)$r['total_marks'], (float)$r['percentage']);
    $enriched[] = $r;
}

$pageTitle = 'My Results';
$pageSubtitle = 'Published grades, ranks, and pass/fail';
$activeNav = 'results';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Paper</th><th>Mega Exam</th><th>Subject</th><th>Score</th><th>%</th><th>Position</th><th>Result</th><th>Grade</th></tr></thead>
            <tbody>
            <?php if (!$enriched): ?>
                <tr><td colspan="8"><div class="empty-state"><div class="empty-icon"><?= oems_icon('award') ?></div><h3>No published results</h3><p>When your teachers publish results, they will show up here.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($enriched as $r): ?>
                <tr>
                    <td><strong><?= e($r['title']) ?></strong><br><small><?= e(format_datetime($r['start_time'], 'M j, Y')) ?></small></td>
                    <td><?= e($r['mega_title'] ? $r['mega_title'].' ('.$r['mega_code'].')' : '—') ?></td>
                    <td><?= e($r['subject_code']) ?></td>
                    <td><?= e($r['obtained_marks'].' / '.$r['total_marks']) ?></td>
                    <td>
                        <div style="min-width:120px">
                            <strong><?= e((string)$r['percentage']) ?>%</strong>
                            <div class="progress" style="margin-top:6px"><span style="width:<?= min(100, (float)$r['percentage']) ?>%"></span></div>
                        </div>
                    </td>
                    <td><span class="badge badge-accent"><?= e($r['rank_label']) ?></span></td>
                    <td><span class="badge <?= $r['pass_fail']==='PASS'?'badge-success':'badge-danger' ?>"><?= e($r['pass_fail']) ?></span></td>
                    <td><span class="badge badge-brand"><?= e($r['grade']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

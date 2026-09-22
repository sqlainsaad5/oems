<?php
/**
 * Admin — Paper Approvals
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');
    $id = (int)request('id');
    $paper = db()->prepare('SELECT e.*, s.name AS subject_name FROM exams e JOIN subjects s ON s.id=e.subject_id WHERE e.id=?');
    $paper->execute([$id]);
    $paper = $paper->fetch();
    if (!$paper) {
        flash('error', 'Paper not found.');
        redirect('/admin/paper_approvals.php');
    }

    if ($action === 'approve') {
        db()->prepare(
            "UPDATE exams SET approval_status='approved', approved_by=?, approved_at=NOW(), status=IF(status='draft','scheduled',status), rejection_reason=NULL WHERE id=?"
        )->execute([(int)$user['id'], $id]);
        // notify teacher
        $tUser = db()->prepare('SELECT u.id FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=?');
        $tUser->execute([(int)$paper['teacher_id']]);
        $tid = (int)$tUser->fetchColumn();
        if ($tid) {
            create_notification($tid, 'Paper approved: ' . $paper['title'], 'Your paper is approved and visible to students when available.', 'exam', '/teacher/exams.php');
        }
        notify_role_users('student', 'Paper available: ' . $paper['title'], 'A new approved paper is ready. Check Available Papers.', 'exam', '/student/exams.php');
        flash('success', 'Paper approved.');
        log_activity((int)$user['id'], 'paper_approve', "Exam #$id");
    }
    if ($action === 'reject') {
        $reason = trim((string)request('rejection_reason'));
        db()->prepare(
            "UPDATE exams SET approval_status='rejected', approved_by=?, approved_at=NOW(), rejection_reason=?, status='draft' WHERE id=?"
        )->execute([(int)$user['id'], $reason !== '' ? $reason : 'Rejected by admin', $id]);
        $tUser = db()->prepare('SELECT u.id FROM teachers t JOIN users u ON u.id=t.user_id WHERE t.id=?');
        $tUser->execute([(int)$paper['teacher_id']]);
        $tid = (int)$tUser->fetchColumn();
        if ($tid) {
            create_notification($tid, 'Paper rejected: ' . $paper['title'], $reason !== '' ? $reason : 'Please revise and resubmit.', 'alert', '/teacher/exams.php');
        }
        flash('success', 'Paper rejected.');
        log_activity((int)$user['id'], 'paper_reject', "Exam #$id");
    }
    redirect('/admin/paper_approvals.php' . ((int)request('mega') ? '?mega='.(int)request('mega') : ''));
}

$megaFilter = (int)request('mega', 0);
$megas = db()->query("SELECT id, title, code FROM mega_exams WHERE status='active' ORDER BY title")->fetchAll();

$sql = "SELECT e.*, s.name AS subject_name, s.code AS subject_code,
               m.title AS mega_title, m.code AS mega_code,
               d.name AS department_name, b.name AS batch_name,
               u.full_name AS teacher_name,
               (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id=e.id) AS qcount
        FROM exams e
        JOIN subjects s ON s.id=e.subject_id
        JOIN teachers t ON t.id=e.teacher_id
        JOIN users u ON u.id=t.user_id
        LEFT JOIN mega_exams m ON m.id=e.mega_exam_id
        LEFT JOIN departments d ON d.id=e.department_id
        LEFT JOIN batches b ON b.id=e.batch_id
        WHERE 1=1";
$params = [];
if ($megaFilter) {
    $sql .= ' AND e.mega_exam_id=?';
    $params[] = $megaFilter;
}
$sql .= ' ORDER BY FIELD(e.approval_status,"pending","draft","rejected","approved"), e.updated_at DESC';
$st = db()->prepare($sql);
$st->execute($params);
$papers = $st->fetchAll();
$pending = array_values(array_filter($papers, fn($p) => $p['approval_status'] === 'pending'));
$approved = array_values(array_filter($papers, fn($p) => $p['approval_status'] === 'approved'));

$pageTitle = 'Paper Approvals';
$pageSubtitle = 'Review and approve teacher-submitted papers';
$activeNav = 'approvals';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel" style="margin-bottom:16px">
    <div class="panel-header"><h2>Filter by Mega Exam</h2></div>
    <div class="panel-body chip-row">
        <a class="btn btn-sm <?= !$megaFilter ? 'btn-primary' : 'btn-secondary' ?>" href="<?= url('/admin/paper_approvals.php') ?>">All Papers (<?= count($papers) ?>)</a>
        <?php foreach ($megas as $m):
            $pSt = db()->prepare("SELECT COUNT(*) FROM exams WHERE mega_exam_id=? AND approval_status='pending'");
            $pSt->execute([(int)$m['id']]);
            $pend = (int)$pSt->fetchColumn();
        ?>
            <a class="btn btn-sm <?= $megaFilter===(int)$m['id']?'btn-primary':'btn-secondary' ?>" href="?mega=<?= (int)$m['id'] ?>">
                <?= e($m['title']) ?> (<?= e($m['code']) ?>)<?= $pend ? ' · '.$pend.' pending' : '' ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel" style="margin-bottom:16px">
    <div class="panel-header">
        <h2>Pending Approvals</h2>
        <span class="badge badge-warning"><?= count($pending) ?> pending</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Paper</th><th>Mega Exam</th><th>Department</th><th>Batch</th><th>Sem</th><th>Teacher</th><th>Window</th><th></th></tr></thead>
            <tbody>
            <?php if (!$pending): ?><tr><td colspan="8"><div class="empty-state"><h3>No pending papers</h3></div></td></tr><?php endif; ?>
            <?php foreach ($pending as $p): ?>
                <tr>
                    <td><strong><?= e($p['title']) ?></strong><br><small><?= e($p['paper_code'] ?: $p['subject_code']) ?> · <?= (int)$p['qcount'] ?> Qs</small></td>
                    <td><span class="badge badge-info"><?= e($p['mega_title'] ?: '—') ?></span></td>
                    <td><?= e($p['department_name'] ?: '—') ?></td>
                    <td><?= e($p['batch_name'] ?: '—') ?></td>
                    <td><?= $p['semester'] !== null ? 'Sem '.(int)$p['semester'] : '—' ?></td>
                    <td><?= e($p['teacher_name']) ?></td>
                    <td><?= ($p['availability_mode'] ?? '') === 'always' ? '<span class="badge badge-success">Always Available</span>' : e(format_datetime($p['start_time'], 'M j, g:i A')) ?></td>
                    <td class="actions">
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="mega" value="<?= $megaFilter ?>">
                            <button class="btn btn-sm btn-primary" type="submit">✓ Approve</button>
                        </form>
                        <form method="post" style="display:inline" data-confirm="Reject this paper?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="mega" value="<?= $megaFilter ?>">
                            <input type="hidden" name="rejection_reason" value="Please revise questions and resubmit.">
                            <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Recently Approved Papers</h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Paper</th><th>Mega Exam</th><th>Teacher</th><th>Approved</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($approved, 0, 15) as $p): ?>
                <tr>
                    <td><strong><?= e($p['title']) ?></strong></td>
                    <td><?= e($p['mega_title'] ?: '—') ?></td>
                    <td><?= e($p['teacher_name']) ?></td>
                    <td><?= e(format_datetime($p['approved_at'])) ?></td>
                    <td><span class="badge badge-success">approved</span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$approved): ?><tr><td colspan="5"><div class="empty-state"><h3>No approved papers yet</h3></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

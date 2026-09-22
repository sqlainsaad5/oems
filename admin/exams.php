<?php
/**
 * Admin — Exam monitoring & scheduling overview
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');
    $id = (int)request('id');
    if ($action === 'set_status' && $id) {
        $status = (string)request('status');
        if (in_array($status, ['scheduled','active','completed','cancelled'], true)) {
            db()->prepare('UPDATE exams SET status=? WHERE id=?')->execute([$status, $id]);
            if ($status === 'scheduled') {
                $ex = db()->prepare('SELECT title FROM exams WHERE id=?');
                $ex->execute([$id]);
                $title = (string)$ex->fetchColumn();
                notify_role_users('student', 'Exam scheduled: ' . $title, 'A new exam has been scheduled. Check your exams list for details.', 'exam', '/student/exams.php');
            }
            flash('success', 'Exam status updated.');
            log_activity((int)$user['id'], 'exam_status', "Exam #$id → $status");
        }
    }
    redirect('/admin/exams.php');
}

$exams = db()->query(
    "SELECT e.*, s.name AS subject_name, s.code AS subject_code, u.full_name AS teacher_name,
            m.title AS mega_title, m.code AS mega_code,
            (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id=e.id) AS question_count,
            (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id=e.id) AS attempt_count
     FROM exams e
     JOIN subjects s ON s.id=e.subject_id
     JOIN teachers t ON t.id=e.teacher_id
     JOIN users u ON u.id=t.user_id
     LEFT JOIN mega_exams m ON m.id=e.mega_exam_id
     ORDER BY e.start_time DESC"
)->fetchAll();

$pageTitle = 'All Papers';
$pageSubtitle = 'Monitor papers, approval state, and availability';
$activeNav = 'exams';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="panel">
    <div class="panel-header">
        <h2>All papers</h2>
        <a class="btn btn-sm btn-primary" href="<?= url('/admin/paper_approvals.php') ?>">Paper Approvals</a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>Paper</th><th>Mega Exam</th><th>Subject</th><th>Teacher</th><th>Window</th><th>Qs</th><th>Approval</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$exams): ?>
                <tr><td colspan="9"><div class="empty-state"><h3>No papers yet</h3><p>Teachers create papers from their portal.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($exams as $ex): $win = exam_window_status($ex); ?>
                <tr>
                    <td><strong><?= e($ex['title']) ?></strong><br><small style="color:var(--muted)"><?= (float)$ex['total_marks'] ?> marks · <?= (int)$ex['attempt_count'] ?> attempts</small></td>
                    <td><?= e($ex['mega_title'] ? $ex['mega_title'].' ('.$ex['mega_code'].')' : '—') ?></td>
                    <td><?= e($ex['subject_code']) ?></td>
                    <td><?= e($ex['teacher_name']) ?></td>
                    <td><?= ($ex['availability_mode'] ?? '') === 'always' ? 'Always' : e(format_datetime($ex['start_time'], 'M j, g:i A')) ?></td>
                    <td><?= (int)$ex['question_count'] ?></td>
                    <td><span class="badge <?= match($ex['approval_status'] ?? 'draft') { 'approved'=>'badge-success','pending'=>'badge-warning','rejected'=>'badge-danger', default=>'badge-brand' } ?>"><?= e($ex['approval_status'] ?? 'draft') ?></span></td>
                    <td>
                        <span class="badge badge-brand"><?= e($ex['status']) ?></span>
                        <div><small class="badge"><?= e($win) ?></small></div>
                    </td>
                    <td>
                        <form method="post" class="actions">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="id" value="<?= (int)$ex['id'] ?>">
                            <select class="form-select" name="status" style="width:auto;min-height:34px">
                                <?php foreach (['scheduled','active','completed','cancelled'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $ex['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-secondary" type="submit">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

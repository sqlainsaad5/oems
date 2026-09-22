<?php
/**
 * Admin — Mega Exams
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');
    if ($action === 'save') {
        $id = (int)request('id', 0);
        $title = trim((string)request('title'));
        $code = preg_replace('/[^A-Za-z0-9]/', '', (string)request('code')) ?? '';
        $description = trim((string)request('description'));
        $status = (string)request('status', 'active');
        if ($title === '' || $code === '' || strlen($title) > 30 || strlen($code) > 30) {
            flash('error', 'Title and code are required (max 30 chars; code letters/numbers only).');
        } elseif (!preg_match('/^[A-Za-z0-9 ]+$/', $title)) {
            flash('error', 'Title: only letters, numbers and spaces.');
        } else {
            try {
                if ($id) {
                    db()->prepare('UPDATE mega_exams SET title=?, code=?, description=?, status=? WHERE id=?')
                        ->execute([$title, $code, $description, $status, $id]);
                    flash('success', 'Mega exam updated.');
                } else {
                    db()->prepare('INSERT INTO mega_exams (title, code, description, status, created_by) VALUES (?,?,?,?,?)')
                        ->execute([$title, $code, $description, $status, (int)$user['id']]);
                    flash('success', 'Mega exam created.');
                }
                log_activity((int)$user['id'], 'mega_exam_save', $code);
            } catch (PDOException) {
                flash('error', 'Could not save mega exam (duplicate code?).');
            }
        }
    }
    if ($action === 'delete') {
        $id = (int)request('id');
        db()->prepare('DELETE FROM mega_exams WHERE id=?')->execute([$id]);
        flash('success', 'Mega exam deleted.');
    }
    redirect('/admin/mega_exams.php');
}

$rows = db()->query(
    "SELECT m.*,
            (SELECT COUNT(*) FROM exams e WHERE e.mega_exam_id=m.id) AS paper_count,
            (SELECT COUNT(*) FROM exams e WHERE e.mega_exam_id=m.id AND e.approval_status='pending') AS pending_count
     FROM mega_exams m ORDER BY m.created_at DESC"
)->fetchAll();
$editId = (int)request('edit', 0);
$create = (string)request('new', '') === '1';
$edit = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM mega_exams WHERE id=?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

$pageTitle = 'Mega Exams';
$pageSubtitle = 'Top-level exam series (Mid term, Final, …)';
$activeNav = 'mega_exams';
require dirname(__DIR__) . '/includes/header.php';
$showModal = $edit || $create;
?>
<div class="toolbar">
    <div></div>
    <a class="btn btn-primary" href="?new=1"><?= oems_icon('plus') ?> Add Mega Exam</a>
</div>
<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Title</th><th>Code</th><th>Papers</th><th>Pending</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state"><h3>No mega exams</h3><p>Create Mid term / Final containers for papers.</p></div></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e($r['title']) ?></strong></td>
                    <td><code><?= e($r['code']) ?></code></td>
                    <td><?= (int)$r['paper_count'] ?></td>
                    <td><?= (int)$r['pending_count'] ? '<span class="badge badge-warning">'.(int)$r['pending_count'].'</span>' : '0' ?></td>
                    <td><span class="badge <?= $r['status']==='active'?'badge-success':'badge-warning' ?>"><?= e($r['status']) ?></span></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-secondary" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                        <a class="btn btn-sm btn-accent" href="<?= url('/admin/paper_approvals.php?mega='.(int)$r['id']) ?>">Papers</a>
                        <form method="post" style="display:inline" data-confirm="Delete this mega exam?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<div class="modal-backdrop" id="megaModal" <?= $showModal ? '' : 'hidden' ?>>
    <div class="modal">
        <div class="modal-header"><h2><?= $edit ? 'Edit Mega Exam' : 'Create Mega Exam' ?></h2>
            <a class="icon-btn" href="<?= url('/admin/mega_exams.php') ?>"><?= oems_icon('x') ?></a>
        </div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Exam Title *</label>
                    <input class="form-control" name="title" required maxlength="30" pattern="[A-Za-z0-9 ]+" value="<?= e($edit['title'] ?? '') ?>">
                    <div class="form-hint">Only letters, numbers and spaces. Max 30 characters.</div>
                </div>
                <div class="form-group">
                    <label>Exam Code *</label>
                    <input class="form-control" name="code" required maxlength="30" pattern="[A-Za-z0-9]+" value="<?= e($edit['code'] ?? '') ?>">
                    <div class="form-hint">Only letters and numbers. Max 30 characters.</div>
                </div>
                <div class="form-group"><label>Description</label><textarea class="form-control" name="description"><?= e($edit['description'] ?? '') ?></textarea></div>
                <div class="form-group"><label>Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?= ($edit['status'] ?? '')==='active'?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= ($edit['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-secondary" href="<?= url('/admin/mega_exams.php') ?>">Cancel</a>
                <button class="btn btn-primary" type="submit"><?= $edit ? 'Save' : 'Create Mega Exam' ?></button>
            </div>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
/**
 * Admin — Departments CRUD
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');
    if ($action === 'save') {
        $id = (int)request('id', 0);
        $name = trim((string)request('name'));
        $code = strtoupper(trim((string)request('code')));
        $description = trim((string)request('description'));
        $status = (string)request('status', 'active');
        if ($name === '' || $code === '') {
            flash('error', 'Name and code are required.');
        } else {
            try {
                if ($id) {
                    db()->prepare('UPDATE departments SET name=?, code=?, description=?, status=? WHERE id=?')
                        ->execute([$name, $code, $description, $status, $id]);
                    flash('success', 'Department updated.');
                } else {
                    db()->prepare('INSERT INTO departments (name, code, description, status) VALUES (?,?,?,?)')
                        ->execute([$name, $code, $description, $status]);
                    flash('success', 'Department created.');
                }
                log_activity((int)$user['id'], 'department_save', $code);
            } catch (PDOException) {
                flash('error', 'Could not save department (duplicate code?).');
            }
        }
    }
    if ($action === 'delete') {
        $id = (int)request('id');
        db()->prepare('DELETE FROM departments WHERE id=?')->execute([$id]);
        flash('success', 'Department deleted.');
        log_activity((int)$user['id'], 'department_delete', "Department #$id");
    }
    redirect('/admin/departments.php');
}

$rows = db()->query(
    'SELECT d.*,
            (SELECT COUNT(*) FROM batches b WHERE b.department_id=d.id) AS batch_count,
            (SELECT COUNT(*) FROM students s WHERE s.department_id=d.id) AS student_count
     FROM departments d ORDER BY d.name'
)->fetchAll();
$editId = (int)request('edit', 0);
$edit = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM departments WHERE id=?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

$pageTitle = 'Departments';
$pageSubtitle = 'Academic departments for papers and students';
$activeNav = 'departments';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="toolbar">
    <div></div>
    <button class="btn btn-primary" type="button" data-modal-open="deptModal"><?= oems_icon('plus') ?> Add department</button>
</div>
<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Code</th><th>Name</th><th>Batches</th><th>Students</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6"><div class="empty-state"><h3>No departments</h3></div></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e($r['code']) ?></strong></td>
                    <td><?= e($r['name']) ?></td>
                    <td><?= (int)$r['batch_count'] ?></td>
                    <td><?= (int)$r['student_count'] ?></td>
                    <td><span class="badge <?= $r['status']==='active'?'badge-success':'badge-warning' ?>"><?= e($r['status']) ?></span></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-secondary" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                        <form method="post" style="display:inline" data-confirm="Delete this department?">
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
<div class="modal-backdrop" id="deptModal" <?= $edit ? '' : 'hidden' ?>>
    <div class="modal">
        <div class="modal-header"><h2><?= $edit ? 'Edit department' : 'Add department' ?></h2><button class="icon-btn" type="button" data-modal-close><?= oems_icon('x') ?></button></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label>Name</label><input class="form-control" name="name" required maxlength="150" value="<?= e($edit['name'] ?? '') ?>"></div>
                    <div class="form-group"><label>Code</label><input class="form-control" name="code" required maxlength="30" value="<?= e($edit['code'] ?? '') ?>"></div>
                </div>
                <div class="form-group"><label>Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?= ($edit['status'] ?? '')==='active'?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= ($edit['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group"><label>Description</label><textarea class="form-control" name="description"><?= e($edit['description'] ?? '') ?></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button class="btn btn-primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

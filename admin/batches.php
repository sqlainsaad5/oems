<?php
/**
 * Admin — Batches CRUD
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

$departments = db()->query("SELECT id, name, code FROM departments WHERE status='active' ORDER BY name")->fetchAll();

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');
    if ($action === 'save') {
        $id = (int)request('id', 0);
        $name = trim((string)request('name'));
        $year = (int)request('year', (int)date('Y'));
        $departmentId = (int)request('department_id', 0) ?: null;
        $status = (string)request('status', 'active');
        if ($name === '' || $year < 2000) {
            flash('error', 'Name and a valid year are required.');
        } else {
            try {
                if ($id) {
                    db()->prepare('UPDATE batches SET name=?, year=?, department_id=?, status=? WHERE id=?')
                        ->execute([$name, $year, $departmentId, $status, $id]);
                    flash('success', 'Batch updated.');
                } else {
                    db()->prepare('INSERT INTO batches (name, year, department_id, status) VALUES (?,?,?,?)')
                        ->execute([$name, $year, $departmentId, $status]);
                    flash('success', 'Batch created.');
                }
                log_activity((int)$user['id'], 'batch_save', $name);
            } catch (PDOException) {
                flash('error', 'Could not save batch.');
            }
        }
    }
    if ($action === 'delete') {
        $id = (int)request('id');
        db()->prepare('DELETE FROM batches WHERE id=?')->execute([$id]);
        flash('success', 'Batch deleted.');
    }
    redirect('/admin/batches.php');
}

$rows = db()->query(
    'SELECT b.*, d.name AS department_name,
            (SELECT COUNT(*) FROM students s WHERE s.batch_id=b.id) AS student_count
     FROM batches b
     LEFT JOIN departments d ON d.id=b.department_id
     ORDER BY b.year DESC, b.name'
)->fetchAll();
$editId = (int)request('edit', 0);
$edit = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM batches WHERE id=?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

$pageTitle = 'Batches';
$pageSubtitle = 'Student intake batches linked to departments';
$activeNav = 'batches';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="toolbar">
    <div></div>
    <button class="btn btn-primary" type="button" data-modal-open="batchModal"><?= oems_icon('plus') ?> Add batch</button>
</div>
<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Batch</th><th>Department</th><th>Students</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="5"><div class="empty-state"><h3>No batches</h3></div></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <strong><?= e($r['name']) ?></strong>
                        <div style="color:var(--muted);font-size:.85rem;margin-top:2px">Class of <?= (int)$r['year'] ?></div>
                    </td>
                    <td><?= e($r['department_name'] ?: '—') ?></td>
                    <td><?= (int)$r['student_count'] ?></td>
                    <td><span class="badge <?= $r['status']==='active'?'badge-success':'badge-warning' ?>"><?= e($r['status']) ?></span></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-secondary" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                        <form method="post" style="display:inline" data-confirm="Delete this batch?">
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
<div class="modal-backdrop" id="batchModal" <?= $edit ? '' : 'hidden' ?>>
    <div class="modal">
        <div class="modal-header"><h2><?= $edit ? 'Edit batch' : 'Add batch' ?></h2><button class="icon-btn" type="button" data-modal-close><?= oems_icon('x') ?></button></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label>Batch name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Batch 2024"></div>
                    <div class="form-group"><label>Class of (year)</label><input class="form-control" type="number" name="year" min="2000" max="2100" required value="<?= e((string)($edit['year'] ?? date('Y'))) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Department</label>
                        <select class="form-select" name="department_id">
                            <option value="">— Optional —</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= ((int)($edit['department_id'] ?? 0)===(int)$d['id'])?'selected':'' ?>><?= e($d['code'].' — '.$d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= ($edit['status'] ?? '')==='active'?'selected':'' ?>>Active</option>
                            <option value="inactive" <?= ($edit['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button class="btn btn-primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
/**
 * Admin — Courses CRUD
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
        $duration = max(1, (int)request('duration_years', 4));
        $status = (string)request('status', 'active');
        if ($name === '' || $code === '') {
            flash('error', 'Name and code are required.');
        } else {
            try {
                if ($id) {
                    db()->prepare('UPDATE courses SET name=?, code=?, description=?, duration_years=?, status=? WHERE id=?')
                        ->execute([$name, $code, $description, $duration, $status, $id]);
                    flash('success', 'Course updated.');
                } else {
                    db()->prepare('INSERT INTO courses (name, code, description, duration_years, status) VALUES (?,?,?,?,?)')
                        ->execute([$name, $code, $description, $duration, $status]);
                    flash('success', 'Course created.');
                }
                log_activity((int)$user['id'], 'course_save', $code);
            } catch (PDOException) {
                flash('error', 'Could not save course (duplicate code?).');
            }
        }
    }
    if ($action === 'delete') {
        $id = (int)request('id');
        db()->prepare('DELETE FROM courses WHERE id=?')->execute([$id]);
        flash('success', 'Course deleted.');
        log_activity((int)$user['id'], 'course_delete', "Course #$id");
    }
    redirect('/admin/courses.php');
}

$courses = db()->query(
    'SELECT c.*, (SELECT COUNT(*) FROM subjects s WHERE s.course_id=c.id) AS subject_count,
            (SELECT COUNT(*) FROM students st WHERE st.course_id=c.id) AS student_count
     FROM courses c ORDER BY c.name'
)->fetchAll();
$editId = (int)request('edit', 0);
$edit = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM courses WHERE id=?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

$pageTitle = 'Courses';
$pageSubtitle = 'Academic programs offered by the institution';
$activeNav = 'courses';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="toolbar">
    <div></div>
    <button class="btn btn-primary" type="button" data-modal-open="courseModal"><?= oems_icon('plus') ?> Add course</button>
</div>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Code</th><th>Name</th><th>Duration</th><th>Subjects</th><th>Students</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($courses as $c): ?>
                <tr>
                    <td><strong><?= e($c['code']) ?></strong></td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= (int)$c['duration_years'] ?> yrs</td>
                    <td><?= (int)$c['subject_count'] ?></td>
                    <td><?= (int)$c['student_count'] ?></td>
                    <td><span class="badge <?= $c['status']==='active'?'badge-success':'badge-warning' ?>"><?= e($c['status']) ?></span></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-secondary" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
                        <form method="post" style="display:inline" data-confirm="Delete this course and its subjects?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal-backdrop" id="courseModal" <?= $edit ? '' : 'hidden' ?>>
    <div class="modal">
        <div class="modal-header"><h2><?= $edit ? 'Edit course' : 'Add course' ?></h2><button class="icon-btn" type="button" data-modal-close><?= oems_icon('x') ?></button></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label>Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
                    <div class="form-group"><label>Code</label><input class="form-control" name="code" required value="<?= e($edit['code'] ?? '') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Duration (years)</label><input class="form-control" type="number" min="1" max="8" name="duration_years" value="<?= e((string)($edit['duration_years'] ?? 4)) ?>"></div>
                    <div class="form-group"><label>Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= ($edit['status'] ?? '')==='active'?'selected':'' ?>>Active</option>
                            <option value="inactive" <?= ($edit['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
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

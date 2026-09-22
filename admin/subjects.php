<?php
/**
 * Admin — Subjects CRUD
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');
    if ($action === 'save') {
        $id = (int)request('id', 0);
        $courseId = (int)request('course_id');
        $name = trim((string)request('name'));
        $code = strtoupper(trim((string)request('code')));
        $description = trim((string)request('description'));
        $credits = max(1, (int)request('credit_hours', 3));
        $status = (string)request('status', 'active');
        if (!$courseId || $name === '' || $code === '') {
            flash('error', 'Course, name, and code are required.');
        } else {
            try {
                if ($id) {
                    db()->prepare('UPDATE subjects SET course_id=?, name=?, code=?, description=?, credit_hours=?, status=? WHERE id=?')
                        ->execute([$courseId, $name, $code, $description, $credits, $status, $id]);
                    flash('success', 'Subject updated.');
                } else {
                    db()->prepare('INSERT INTO subjects (course_id, name, code, description, credit_hours, status) VALUES (?,?,?,?,?,?)')
                        ->execute([$courseId, $name, $code, $description, $credits, $status]);
                    flash('success', 'Subject created.');
                }
            } catch (PDOException) {
                flash('error', 'Could not save subject (duplicate code?).');
            }
        }
    }
    if ($action === 'delete') {
        db()->prepare('DELETE FROM subjects WHERE id=?')->execute([(int)request('id')]);
        flash('success', 'Subject deleted.');
    }
    if ($action === 'assign_teacher') {
        $teacherId = (int)request('teacher_id');
        $subjectId = (int)request('subject_id');
        if ($teacherId && $subjectId) {
            db()->prepare('INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES (?,?)')
                ->execute([$teacherId, $subjectId]);
            flash('success', 'Teacher assigned to subject.');
        }
    }
    redirect('/admin/subjects.php');
}

$subjects = db()->query(
    'SELECT s.*, c.name AS course_name, c.code AS course_code
     FROM subjects s JOIN courses c ON c.id = s.course_id ORDER BY c.name, s.name'
)->fetchAll();
$courses = db()->query('SELECT id, name, code FROM courses ORDER BY name')->fetchAll();
$teachers = db()->query(
    'SELECT t.id, u.full_name, t.employee_id FROM teachers t JOIN users u ON u.id=t.user_id ORDER BY u.full_name'
)->fetchAll();
$editId = (int)request('edit', 0);
$edit = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM subjects WHERE id=?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

$pageTitle = 'Subjects';
$pageSubtitle = 'Subjects and teacher assignments';
$activeNav = 'subjects';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="toolbar">
    <button class="btn btn-secondary" type="button" data-modal-open="assignModal">Assign teacher</button>
    <button class="btn btn-primary" type="button" data-modal-open="subjectModal"><?= oems_icon('plus') ?> Add subject</button>
</div>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Code</th><th>Subject</th><th>Course</th><th>Credits</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($subjects as $s): ?>
                <tr>
                    <td><strong><?= e($s['code']) ?></strong></td>
                    <td><?= e($s['name']) ?></td>
                    <td><?= e($s['course_code']) ?></td>
                    <td><?= (int)$s['credit_hours'] ?></td>
                    <td><span class="badge badge-brand"><?= e($s['status']) ?></span></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-secondary" href="?edit=<?= (int)$s['id'] ?>">Edit</a>
                        <form method="post" style="display:inline" data-confirm="Delete subject?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal-backdrop" id="subjectModal" <?= $edit ? '' : 'hidden' ?>>
    <div class="modal">
        <div class="modal-header"><h2><?= $edit ? 'Edit subject' : 'Add subject' ?></h2><button class="icon-btn" type="button" data-modal-close><?= oems_icon('x') ?></button></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-group"><label>Course</label>
                    <select class="form-select" name="course_id" required>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ((int)($edit['course_id'] ?? 0)===(int)$c['id'])?'selected':'' ?>><?= e($c['code'].' — '.$c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Name</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
                    <div class="form-group"><label>Code</label><input class="form-control" name="code" required value="<?= e($edit['code'] ?? '') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Credit hours</label><input class="form-control" type="number" name="credit_hours" min="1" max="6" value="<?= e((string)($edit['credit_hours'] ?? 3)) ?>"></div>
                    <div class="form-group"><label>Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive" <?= ($edit['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Description</label><textarea class="form-control" name="description"><?= e($edit['description'] ?? '') ?></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="assignModal" hidden>
    <div class="modal">
        <div class="modal-header"><h2>Assign teacher</h2><button class="icon-btn" type="button" data-modal-close><?= oems_icon('x') ?></button></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="assign_teacher">
            <div class="modal-body">
                <div class="form-group"><label>Teacher</label>
                    <select class="form-select" name="teacher_id" required>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= e($t['full_name'].' ('.$t['employee_id'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Subject</label>
                    <select class="form-select" name="subject_id" required>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= e($s['code'].' — '.$s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Assign</button></div>
        </form>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

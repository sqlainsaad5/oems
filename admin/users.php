<?php
/**
 * Admin — User Management (CRUD + CSV bulk student upload)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

$errors = [];
$action = request('action', '');

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');

    if ($action === 'create' || $action === 'update') {
        $id = (int)request('id', 0);
        $data = [
            'full_name' => trim((string)request('full_name')),
            'username' => trim((string)request('username')),
            'email' => trim((string)request('email')),
            'role' => (string)request('role'),
            'status' => (string)request('status', 'active'),
            'password' => (string)request('password'),
            'student_id' => trim((string)request('student_id')),
            'course_id' => (int)request('course_id', 0) ?: null,
            'employee_id' => trim((string)request('employee_id')),
            'department' => trim((string)request('department')),
            'phone' => trim((string)request('phone')),
        ];
        $errors = validate_required($data, [
            'full_name' => 'Full name',
            'username' => 'Username',
            'email' => 'Email',
            'role' => 'Role',
        ]);
        if (!in_array($data['role'], ['admin', 'teacher', 'student'], true)) {
            $errors['role'] = 'Invalid role.';
        }
        // New accounts only via Add student / Add teacher (not generic admin).
        if ($action === 'create' && !in_array($data['role'], ['student', 'teacher'], true)) {
            $errors['role'] = 'Use Add student or Add teacher.';
        }
        if ($action === 'create' && $data['password'] === '') {
            $errors['password'] = 'Password is required for new users.';
        }
        if ($data['role'] === 'student' && $data['student_id'] === '') {
            $errors['student_id'] = 'Student ID is required.';
        }
        if ($data['role'] === 'teacher' && $data['employee_id'] === '') {
            $errors['employee_id'] = 'Employee ID is required.';
        }
        if ($data['phone'] !== '' && !is_valid_pk_mobile($data['phone'])) {
            $errors['phone'] = 'Enter a valid Pakistani mobile (e.g. +92 3001234567 or 03001234567).';
        } elseif ($data['phone'] !== '') {
            $data['phone'] = normalize_pk_mobile($data['phone']) ?? $data['phone'];
        }

        if (!$errors) {
            try {
                db()->beginTransaction();
                if ($action === 'create') {
                    $stmt = db()->prepare('INSERT INTO users (username, email, password, full_name, role, status) VALUES (?,?,?,?,?,?)');
                    $stmt->execute([
                        $data['username'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT),
                        $data['full_name'], $data['role'], $data['status'],
                    ]);
                    $uid = (int)db()->lastInsertId();
                    if ($data['role'] === 'student') {
                        db()->prepare('INSERT INTO students (user_id, course_id, student_id, phone) VALUES (?,?,?,?)')
                            ->execute([$uid, $data['course_id'], $data['student_id'], $data['phone'] ?: null]);
                    } elseif ($data['role'] === 'teacher') {
                        db()->prepare('INSERT INTO teachers (user_id, employee_id, department, phone) VALUES (?,?,?,?)')
                            ->execute([$uid, $data['employee_id'], $data['department'] ?: null, $data['phone'] ?: null]);
                    }
                    log_activity((int)$user['id'], 'user_create', "Created {$data['role']} {$data['username']}");
                    flash('success', $data['role'] === 'student' ? 'Student created successfully.' : 'Teacher created successfully.');
                    db()->commit();
                    $backRole = in_array($data['role'], ['student', 'teacher'], true) ? $data['role'] : '';
                    redirect('/admin/users.php' . ($backRole !== '' ? '?role=' . rawurlencode($backRole) : ''));
                } else {
                    $fields = 'username=?, email=?, full_name=?, role=?, status=?';
                    $params = [$data['username'], $data['email'], $data['full_name'], $data['role'], $data['status']];
                    if ($data['password'] !== '') {
                        $fields .= ', password=?';
                        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
                    }
                    $params[] = $id;
                    db()->prepare("UPDATE users SET $fields WHERE id=?")->execute($params);

                    if ($data['role'] === 'student') {
                        $exists = db()->prepare('SELECT id FROM students WHERE user_id=?');
                        $exists->execute([$id]);
                        if ($exists->fetch()) {
                            db()->prepare('UPDATE students SET course_id=?, student_id=?, phone=? WHERE user_id=?')
                                ->execute([$data['course_id'], $data['student_id'], $data['phone'] ?: null, $id]);
                        } else {
                            db()->prepare('INSERT INTO students (user_id, course_id, student_id, phone) VALUES (?,?,?,?)')
                                ->execute([$id, $data['course_id'], $data['student_id'], $data['phone'] ?: null]);
                        }
                    } elseif ($data['role'] === 'teacher') {
                        $exists = db()->prepare('SELECT id FROM teachers WHERE user_id=?');
                        $exists->execute([$id]);
                        if ($exists->fetch()) {
                            db()->prepare('UPDATE teachers SET employee_id=?, department=?, phone=? WHERE user_id=?')
                                ->execute([$data['employee_id'], $data['department'] ?: null, $data['phone'] ?: null, $id]);
                        } else {
                            db()->prepare('INSERT INTO teachers (user_id, employee_id, department, phone) VALUES (?,?,?,?)')
                                ->execute([$id, $data['employee_id'], $data['department'] ?: null, $data['phone'] ?: null]);
                        }
                    }
                    log_activity((int)$user['id'], 'user_update', "Updated user #{$id}");
                    flash('success', 'User updated successfully.');
                    db()->commit();
                    $backRole = in_array($data['role'], ['student', 'teacher'], true) ? $data['role'] : '';
                    redirect('/admin/users.php' . ($backRole !== '' ? '?role=' . rawurlencode($backRole) : ''));
                }
            } catch (PDOException $e) {
                db()->rollBack();
                $errors['form'] = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Username, email, or ID already exists.'
                    : 'Could not save user.';
            }
        }
        store_old($_POST);
    }

    if ($action === 'delete') {
        $id = (int)request('id');
        if ($id === (int)$user['id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            log_activity((int)$user['id'], 'user_delete', "Deleted user #{$id}");
            flash('success', 'User deleted.');
        }
        redirect('/admin/users.php');
    }

    if ($action === 'bulk_csv') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please upload a valid CSV file.');
            redirect('/admin/users.php');
        }
        $fh = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($fh);
        $created = 0;
        $failed = 0;
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 5) { $failed++; continue; }
            [$fullName, $username, $email, $studentId, $courseCode] = array_map('trim', $row);
            $password = $row[5] ?? 'password123';
            try {
                $courseId = null;
                if ($courseCode !== '') {
                    $c = db()->prepare('SELECT id FROM courses WHERE code=?');
                    $c->execute([$courseCode]);
                    $courseId = $c->fetchColumn() ?: null;
                }
                db()->beginTransaction();
                db()->prepare('INSERT INTO users (username, email, password, full_name, role, status) VALUES (?,?,?,?,?,?)')
                    ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, 'student', 'active']);
                $uid = (int)db()->lastInsertId();
                db()->prepare('INSERT INTO students (user_id, course_id, student_id) VALUES (?,?,?)')
                    ->execute([$uid, $courseId, $studentId]);
                db()->commit();
                $created++;
            } catch (Throwable) {
                if (db()->inTransaction()) db()->rollBack();
                $failed++;
            }
        }
        fclose($fh);
        log_activity((int)$user['id'], 'bulk_upload', "CSV upload: {$created} created, {$failed} failed");
        flash('success', "Bulk upload complete: {$created} students created, {$failed} failed.");
        redirect('/admin/users.php');
    }
}

$q = trim((string)request('q', ''));
$roleFilter = (string)request('role', '');
$page = max(1, (int)request('page', 1));
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if (in_array($roleFilter, ['admin','teacher','student'], true)) {
    $where[] = 'u.role = ?';
    $params[] = $roleFilter;
}
$whereSql = implode(' AND ', $where);
$countStmt = db()->prepare("SELECT COUNT(*) FROM users u WHERE $whereSql");
$countStmt->execute($params);
$pager = paginate((int)$countStmt->fetchColumn(), $page, 12);
$listStmt = db()->prepare(
    "SELECT u.* FROM users u WHERE $whereSql ORDER BY u.created_at DESC LIMIT {$pager['per_page']} OFFSET {$pager['offset']}"
);
$listStmt->execute($params);
$users = $listStmt->fetchAll();
$courses = db()->query('SELECT id, name, code FROM courses WHERE status="active" ORDER BY name')->fetchAll();

$editId = (int)request('edit', 0);
$editUser = null;
$editProfile = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([$editId]);
    $editUser = $st->fetch() ?: null;
    if ($editUser) {
        if ($editUser['role'] === 'student') {
            $ps = db()->prepare('SELECT * FROM students WHERE user_id=?');
            $ps->execute([$editId]);
            $editProfile = $ps->fetch() ?: null;
        } elseif ($editUser['role'] === 'teacher') {
            $ps = db()->prepare('SELECT * FROM teachers WHERE user_id=?');
            $ps->execute([$editId]);
            $editProfile = $ps->fetch() ?: null;
        }
    }
}

// Create flow: ?new=student | ?new=teacher (no generic Add user)
$createRole = (string)request('new', '');
if ($errors && !$editUser) {
    $createRole = (string)old('role', $createRole);
}
if (!in_array($createRole, ['student', 'teacher'], true)) {
    $createRole = '';
}
$formRole = $editUser['role'] ?? ($createRole !== '' ? $createRole : (in_array($roleFilter, ['student', 'teacher'], true) ? $roleFilter : 'student'));
$showUserModal = (bool)$editUser || $createRole !== '' || ($errors && !$editUser);
$modalTitle = $editUser
    ? 'Edit user'
    : ($formRole === 'teacher' ? 'Add teacher' : 'Add student');
$saveLabel = $editUser
    ? 'Save user'
    : ($formRole === 'teacher' ? 'Save teacher' : 'Save student');
$listQuery = in_array($roleFilter, ['student', 'teacher'], true) ? ('?role=' . rawurlencode($roleFilter)) : '';
$listUrl = url('/admin/users.php' . $listQuery);

$pageTitle = match ($roleFilter) {
    'student' => 'Students',
    'teacher' => 'Teachers',
    'admin' => 'Administrators',
    default => 'Students & Teachers',
};
$pageSubtitle = match ($roleFilter) {
    'student' => 'Add and manage student accounts',
    'teacher' => 'Add and manage teacher accounts',
    'admin' => 'Manage administrator accounts',
    default => 'Manage students, teachers, and administrators',
};
$activeNav = match ($roleFilter) {
    'teacher' => 'teachers',
    'student' => 'students',
    default => (($editUser['role'] ?? $createRole) === 'teacher' ? 'teachers' : 'students'),
};
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="toolbar">
    <form class="toolbar-left" method="get" style="flex:1;min-width:min(320px,100%)">
        <?php if (in_array($roleFilter, ['student', 'teacher'], true)): ?>
            <input type="hidden" name="role" value="<?= e($roleFilter) ?>">
            <div class="search-inline">
                <input class="form-control search-box" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, username, email…">
                <button class="btn btn-secondary" type="submit">Search</button>
            </div>
        <?php else: ?>
            <input class="form-control search-box" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, username, email…">
            <select class="form-select" name="role" style="width:auto">
                <option value="">All roles</option>
                <?php foreach (['admin','teacher','student'] as $r): ?>
                    <option value="<?= $r ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary" type="submit">Filter</button>
        <?php endif; ?>
    </form>
    <div class="toolbar-right">
        <?php if ($roleFilter === 'student'): ?>
            <button class="btn btn-secondary" type="button" data-modal-open="csvModal">Bulk CSV</button>
            <button class="btn btn-primary" type="button" data-modal-open="userModal" data-create-role="student"><?= oems_icon('plus') ?> Add student</button>
        <?php elseif ($roleFilter === 'teacher'): ?>
            <button class="btn btn-primary" type="button" data-modal-open="userModal" data-create-role="teacher"><?= oems_icon('plus') ?> Add teacher</button>
        <?php else: ?>
            <button class="btn btn-secondary" type="button" data-modal-open="csvModal">Bulk CSV</button>
            <button class="btn btn-secondary" type="button" data-modal-open="userModal" data-create-role="teacher"><?= oems_icon('plus') ?> Add teacher</button>
            <button class="btn btn-primary" type="button" data-modal-open="userModal" data-create-role="student"><?= oems_icon('plus') ?> Add student</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= e($errors['form']) ?></div><?php endif; ?>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="7"><div class="empty-state"><h3>No users found</h3></div></td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= e($u['full_name']) ?></strong></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge badge-brand"><?= e($u['role']) ?></span></td>
                    <td><span class="badge <?= $u['status']==='active'?'badge-success':'badge-warning' ?>"><?= e($u['status']) ?></span></td>
                    <td><?= e(format_datetime($u['last_login'])) ?></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-secondary" href="<?= url('/admin/users.php?edit=' . $u['id']) ?>">Edit</a>
                        <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this user?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager['pages'] > 1): ?>
        <div class="panel-body">
            <div class="pagination">
                <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
                    <a class="<?= $i === $pager['page'] ? 'is-active' : '' ?>" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&role=<?= urlencode($roleFilter) ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<div class="modal-backdrop" id="userModal" <?= $showUserModal ? '' : 'hidden' ?>>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
        <div class="modal-header">
            <h2 id="userModalTitle"><?= e($modalTitle) ?></h2>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= oems_icon('x') ?></button>
        </div>
        <form method="post" id="userForm" action="<?= e($listUrl) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="userFormAction" value="<?= $editUser ? 'update' : 'create' ?>">
            <?php if ($editUser): ?><input type="hidden" name="id" id="userFormId" value="<?= (int)$editUser['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full name</label>
                        <input class="form-control" name="full_name" required value="<?= e($editUser['full_name'] ?? old('full_name')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input class="form-control" name="username" required value="<?= e($editUser['username'] ?? old('username')) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input class="form-control" type="email" name="email" required value="<?= e($editUser['email'] ?? old('email')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Password <span id="passwordHint"><?= $editUser ? '(leave blank to keep)' : '' ?></span></label>
                        <input class="form-control" type="password" name="password" id="userPassword" <?= $editUser ? '' : 'required' ?>>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <?php if ($editUser): ?>
                            <select class="form-select" name="role" id="roleSelect" required>
                                <?php foreach (['admin','teacher','student'] as $r): ?>
                                    <option value="<?= $r ?>" <?= $formRole === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="hidden" name="role" id="roleSelect" value="<?= e($formRole) ?>">
                            <div class="form-control" id="roleBadgeWrap" style="display:flex;align-items:center;background:var(--surface-2, #f3f4f6)">
                                <span class="badge badge-brand" id="roleBadge"><?= e($formRole) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-select" name="status">
                            <?php foreach (['active','inactive','suspended'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($editUser['status'] ?? old('status', 'active')) === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div id="studentFields" <?= $formRole === 'student' ? '' : 'style="display:none"' ?>>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Student ID</label>
                            <input class="form-control" name="student_id" id="fieldStudentId" value="<?= e($editProfile['student_id'] ?? old('student_id')) ?>" <?= !$editUser && $formRole === 'student' ? 'required' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label>Course</label>
                            <select class="form-select" name="course_id">
                                <option value="">— Select —</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= ((int)($editProfile['course_id'] ?? old('course_id', 0)) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['code'] . ' — ' . $c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="teacherFields" <?= $formRole === 'teacher' ? '' : 'style="display:none"' ?>>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Employee ID</label>
                            <input class="form-control" name="employee_id" id="fieldEmployeeId" value="<?= e($editProfile['employee_id'] ?? old('employee_id')) ?>" <?= !$editUser && $formRole === 'teacher' ? 'required' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <input class="form-control" name="department" value="<?= e($editProfile['department'] ?? old('department')) ?>">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input
                        class="form-control"
                        type="tel"
                        name="phone"
                        inputmode="tel"
                        autocomplete="tel"
                        placeholder="+92 3001234567"
                        title="Pakistani mobile: +92 3XXXXXXXXX or 03XXXXXXXXX"
                        value="<?= e($editProfile['phone'] ?? old('phone')) ?>"
                    >
                    <small style="color:var(--muted);display:block;margin-top:6px">Format: +92 3XXXXXXXXX or 03XXXXXXXXX</small>
                    <?php if (!empty($errors['phone'])): ?>
                        <small class="field-error" style="color:var(--danger,#b91c1c);display:block;margin-top:4px"><?= e($errors['phone']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="userSaveBtn"><?= e($saveLabel) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="csvModal" hidden>
    <div class="modal">
        <div class="modal-header">
            <h2>Bulk student upload</h2>
            <button type="button" class="icon-btn" data-modal-close><?= oems_icon('x') ?></button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk_csv">
            <div class="modal-body">
                <p>CSV columns: <code>full_name, username, email, student_id, course_code, password(optional)</code></p>
                <div class="form-group">
                    <label>CSV file</label>
                    <input class="form-control" type="file" name="csv_file" accept=".csv,text/csv" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
  const role = document.getElementById('roleSelect');
  const sf = document.getElementById('studentFields');
  const tf = document.getElementById('teacherFields');
  const title = document.getElementById('userModalTitle');
  const saveBtn = document.getElementById('userSaveBtn');
  const badge = document.getElementById('roleBadge');
  const form = document.getElementById('userForm');
  const actionInput = document.getElementById('userFormAction');
  const password = document.getElementById('userPassword');
  const passwordHint = document.getElementById('passwordHint');
  const studentId = document.getElementById('fieldStudentId');
  const employeeId = document.getElementById('fieldEmployeeId');
  const isEditPage = <?= $editUser ? 'true' : 'false' ?>;
  const listUrl = <?= json_encode($listUrl) ?>;

  function currentRole(){
    if (!role) return '';
    return role.tagName === 'SELECT' ? role.value : (role.value || '');
  }
  function sync(){
    if (!sf || !tf) return;
    const v = currentRole();
    sf.style.display = v === 'student' ? '' : 'none';
    tf.style.display = v === 'teacher' ? '' : 'none';
    if (studentId) studentId.required = v === 'student' && actionInput?.value === 'create';
    if (employeeId) employeeId.required = v === 'teacher' && actionInput?.value === 'create';
  }
  function prepareCreate(createRole){
    if (isEditPage) {
      window.location.href = listUrl + (listUrl.includes('?') ? '&' : '?') + 'new=' + encodeURIComponent(createRole);
      return;
    }
    if (form) form.reset();
    if (actionInput) actionInput.value = 'create';
    const idInput = document.getElementById('userFormId');
    if (idInput) idInput.remove();
    if (role && role.tagName !== 'SELECT') role.value = createRole;
    if (badge) badge.textContent = createRole;
    if (title) title.textContent = createRole === 'teacher' ? 'Add teacher' : 'Add student';
    if (saveBtn) saveBtn.textContent = createRole === 'teacher' ? 'Save teacher' : 'Save student';
    if (password) password.required = true;
    if (passwordHint) passwordHint.textContent = '';
    sync();
  }

  role?.addEventListener('change', sync);
  sync();

  document.querySelectorAll('[data-create-role]').forEach((btn) => {
    btn.addEventListener('click', () => {
      prepareCreate(btn.getAttribute('data-create-role') || 'student');
    });
  });

  // Keep scroll locked while server-rendered modal is open
  const modal = document.getElementById('userModal');
  if (modal && !modal.hasAttribute('hidden')) {
    document.body.style.overflow = 'hidden';
  }
  document.querySelectorAll('#userModal [data-modal-close]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.body.style.overflow = '';
      if (window.location.search.includes('new=') || window.location.search.includes('edit=')) {
        window.history.replaceState({}, '', listUrl);
      }
    });
  });
})();
</script>
<?php
clear_old();
require dirname(__DIR__) . '/includes/footer.php';
?>

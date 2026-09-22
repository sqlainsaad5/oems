<?php
/**
 * Teacher — Question Bank CRUD
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_teacher();
$user = $ctx['user'];
$teacher = $ctx['teacher'];
$tid = (int)$teacher['id'];

$subjectsStmt = db()->prepare(
    'SELECT s.* FROM subjects s
     JOIN teacher_subjects ts ON ts.subject_id=s.id
     WHERE ts.teacher_id=? AND s.status="active" ORDER BY s.name'
);
$subjectsStmt->execute([$tid]);
$subjects = $subjectsStmt->fetchAll();
$subjectIds = array_map(fn($s) => (int)$s['id'], $subjects);

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');
    if ($action === 'save') {
        $id = (int)request('id', 0);
        $subjectId = (int)request('subject_id');
        $text = trim((string)request('question_text'));
        $type = (string)request('question_type', 'mcq');
        $difficulty = (string)request('difficulty', 'medium');
        $marks = (float)request('marks', 1);
        $oa = trim((string)request('option_a'));
        $ob = trim((string)request('option_b'));
        $oc = trim((string)request('option_c'));
        $od = trim((string)request('option_d'));
        $correct = strtoupper(trim((string)request('correct_answer')));
        if (!in_array($subjectId, $subjectIds, true) || $text === '') {
            flash('error', 'Valid subject and question text are required.');
        } elseif ($type === 'mcq' && (!in_array($correct, ['A','B','C','D'], true) || $oa === '' || $ob === '')) {
            flash('error', 'MCQs need options A/B (at least) and a correct answer.');
        } else {
            if ($type === 'descriptive') {
                $oa = $ob = $oc = $od = $correct = null;
            }
            if ($id) {
                $own = db()->prepare('SELECT id FROM questions WHERE id=? AND teacher_id=?');
                $own->execute([$id, $tid]);
                if (!$own->fetch()) {
                    flash('error', 'Question not found.');
                    redirect('/teacher/questions.php');
                }
                db()->prepare(
                    'UPDATE questions SET subject_id=?, question_text=?, question_type=?, difficulty=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, marks=? WHERE id=? AND teacher_id=?'
                )->execute([$subjectId, $text, $type, $difficulty, $oa, $ob, $oc, $od, $correct, $marks, $id, $tid]);
                flash('success', 'Question updated.');
            } else {
                db()->prepare(
                    'INSERT INTO questions (subject_id, teacher_id, question_text, question_type, difficulty, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$subjectId, $tid, $text, $type, $difficulty, $oa, $ob, $oc, $od, $correct, $marks]);
                flash('success', 'Question added to bank.');
            }
        }
    }
    if ($action === 'delete') {
        $id = (int)request('id');
        db()->prepare('DELETE FROM questions WHERE id=? AND teacher_id=?')->execute([$id, $tid]);
        flash('success', 'Question deleted.');
    }
    redirect('/teacher/questions.php');
}

$filterSubject = (int)request('subject_id', 0);
$filterDiff = (string)request('difficulty', '');
$filterType = (string)request('type', '');
$where = ['q.teacher_id=?'];
$params = [$tid];
if ($filterSubject && in_array($filterSubject, $subjectIds, true)) {
    $where[] = 'q.subject_id=?';
    $params[] = $filterSubject;
}
if (in_array($filterDiff, ['easy','medium','hard'], true)) {
    $where[] = 'q.difficulty=?';
    $params[] = $filterDiff;
}
if (in_array($filterType, ['mcq','descriptive'], true)) {
    $where[] = 'q.question_type=?';
    $params[] = $filterType;
}
$sql = 'SELECT q.*, s.name AS subject_name, s.code AS subject_code FROM questions q JOIN subjects s ON s.id=q.subject_id WHERE ' . implode(' AND ', $where) . ' ORDER BY q.created_at DESC';
$st = db()->prepare($sql);
$st->execute($params);
$questions = $st->fetchAll();

$editId = (int)request('edit', 0);
$edit = null;
if ($editId) {
    $es = db()->prepare('SELECT * FROM questions WHERE id=? AND teacher_id=?');
    $es->execute([$editId, $tid]);
    $edit = $es->fetch() ?: null;
}

$pageTitle = 'Question Bank';
$pageSubtitle = 'Create and categorize exam questions';
$activeNav = 'questions';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="toolbar">
    <form class="toolbar-left" method="get">
        <select class="form-select" name="subject_id" style="width:auto">
            <option value="">All subjects</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $filterSubject===(int)$s['id']?'selected':'' ?>><?= e($s['code']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" name="difficulty" style="width:auto">
            <option value="">All levels</option>
            <?php foreach (['easy','medium','hard'] as $d): ?>
                <option value="<?= $d ?>" <?= $filterDiff===$d?'selected':'' ?>><?= ucfirst($d) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" name="type" style="width:auto">
            <option value="">All types</option>
            <option value="mcq" <?= $filterType==='mcq'?'selected':'' ?>>MCQ</option>
            <option value="descriptive" <?= $filterType==='descriptive'?'selected':'' ?>>Descriptive</option>
        </select>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
    <button class="btn btn-primary" type="button" data-modal-open="qModal"><?= oems_icon('plus') ?> Add question</button>
</div>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Question</th><th>Subject</th><th>Type</th><th>Difficulty</th><th>Marks</th><th></th></tr></thead>
            <tbody>
            <?php if (!$questions): ?>
                <tr><td colspan="6"><div class="empty-state"><h3>No questions yet</h3><p>Build your bank before creating exams.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($questions as $q): ?>
                <tr>
                    <td style="max-width:420px"><?= e(mb_strimwidth($q['question_text'], 0, 120, '…')) ?></td>
                    <td><?= e($q['subject_code']) ?></td>
                    <td><span class="badge"><?= e($q['question_type']) ?></span></td>
                    <td><span class="badge badge-<?= $q['difficulty']==='hard'?'danger':($q['difficulty']==='easy'?'success':'warning') ?>"><?= e($q['difficulty']) ?></span></td>
                    <td><?= e((string)$q['marks']) ?></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-secondary" href="?edit=<?= (int)$q['id'] ?>">Edit</a>
                        <form method="post" style="display:inline" data-confirm="Delete this question?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal-backdrop" id="qModal" <?= $edit ? '' : 'hidden' ?>>
    <div class="modal" style="width:min(720px,100%)">
        <div class="modal-header"><h2><?= $edit ? 'Edit question' : 'Add question' ?></h2><button class="icon-btn" type="button" data-modal-close><?= oems_icon('x') ?></button></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label>Subject</label>
                        <select class="form-select" name="subject_id" required>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= ((int)($edit['subject_id'] ?? 0)===(int)$s['id'])?'selected':'' ?>><?= e($s['code'].' — '.$s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Type</label>
                        <select class="form-select" name="question_type" id="qType">
                            <option value="mcq" <?= ($edit['question_type'] ?? '')==='mcq'?'selected':'' ?>>MCQ</option>
                            <option value="descriptive" <?= ($edit['question_type'] ?? '')==='descriptive'?'selected':'' ?>>Descriptive</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Question</label><textarea class="form-control" name="question_text" required><?= e($edit['question_text'] ?? '') ?></textarea></div>
                <div id="mcqFields">
                    <div class="form-row">
                        <div class="form-group"><label>Option A</label><input class="form-control" name="option_a" value="<?= e($edit['option_a'] ?? '') ?>"></div>
                        <div class="form-group"><label>Option B</label><input class="form-control" name="option_b" value="<?= e($edit['option_b'] ?? '') ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Option C</label><input class="form-control" name="option_c" value="<?= e($edit['option_c'] ?? '') ?>"></div>
                        <div class="form-group"><label>Option D</label><input class="form-control" name="option_d" value="<?= e($edit['option_d'] ?? '') ?>"></div>
                    </div>
                    <div class="form-group"><label>Correct answer</label>
                        <select class="form-select" name="correct_answer">
                            <?php foreach (['A','B','C','D'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($edit['correct_answer'] ?? '')===$opt?'selected':'' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Difficulty</label>
                        <select class="form-select" name="difficulty">
                            <?php foreach (['easy','medium','hard'] as $d): ?>
                                <option value="<?= $d ?>" <?= ($edit['difficulty'] ?? 'medium')===$d?'selected':'' ?>><?= ucfirst($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Marks</label><input class="form-control" type="number" step="0.5" min="0.5" name="marks" value="<?= e((string)($edit['marks'] ?? '1')) ?>"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
        </form>
    </div>
</div>
<script>
(function(){
  const t=document.getElementById('qType'), m=document.getElementById('mcqFields');
  function sync(){ m.style.display = t.value==='mcq' ? '' : 'none'; }
  t?.addEventListener('change', sync); sync();
})();
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

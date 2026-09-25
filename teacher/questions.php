<?php
/**
 * Teacher — Question Bank (batch compose + CRUD)
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
$subjectMap = [];
foreach ($subjects as $s) {
    $subjectMap[(int)$s['id']] = $s;
}

function teacher_question_payload(array $item, array $subjectIds): array
{
    $subjectId = (int)($item['subject_id'] ?? 0);
    $text = trim((string)($item['question_text'] ?? ''));
    $type = (string)($item['question_type'] ?? 'mcq');
    $difficulty = (string)($item['difficulty'] ?? 'medium');
    $marks = (float)($item['marks'] ?? 1);
    $oa = trim((string)($item['option_a'] ?? ''));
    $ob = trim((string)($item['option_b'] ?? ''));
    $oc = trim((string)($item['option_c'] ?? ''));
    $od = trim((string)($item['option_d'] ?? ''));
    $correct = strtoupper(trim((string)($item['correct_answer'] ?? '')));

    if (!in_array($type, ['mcq', 'descriptive'], true)) {
        $type = 'mcq';
    }
    if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
        $difficulty = 'medium';
    }
    if ($marks < 0.5) {
        $marks = 1.0;
    }
    if (!in_array($subjectId, $subjectIds, true) || $text === '') {
        return ['ok' => false, 'error' => 'Valid subject and question text are required.'];
    }
    if ($type === 'mcq' && (!in_array($correct, ['A', 'B', 'C', 'D'], true) || $oa === '' || $ob === '')) {
        return ['ok' => false, 'error' => 'MCQs need options A/B (at least) and a correct answer.'];
    }
    if ($type === 'descriptive') {
        $oa = $ob = $oc = $od = $correct = null;
    }
    return [
        'ok' => true,
        'data' => [$subjectId, $text, $type, $difficulty, $oa, $ob, $oc, $od, $correct, $marks],
    ];
}

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');

    if ($action === 'bulk_save') {
        $raw = (string)request('questions_json', '[]');
        $items = json_decode($raw, true);
        if (!is_array($items) || !$items) {
            flash('error', 'Add at least one question to the list before saving.');
            redirect('/teacher/questions.php');
        }
        $saved = 0;
        $failed = 0;
        $ins = db()->prepare(
            'INSERT INTO questions (subject_id, teacher_id, question_text, question_type, difficulty, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        foreach ($items as $item) {
            if (!is_array($item)) {
                $failed++;
                continue;
            }
            $parsed = teacher_question_payload($item, $subjectIds);
            if (!$parsed['ok']) {
                $failed++;
                continue;
            }
            [$subjectId, $text, $type, $difficulty, $oa, $ob, $oc, $od, $correct, $marks] = $parsed['data'];
            $ins->execute([$subjectId, $tid, $text, $type, $difficulty, $oa, $ob, $oc, $od, $correct, $marks]);
            $saved++;
        }
        if ($saved > 0) {
            log_activity((int)$user['id'], 'questions_bulk', "Added {$saved} questions");
            flash('success', $failed
                ? "Saved {$saved} question(s). {$failed} skipped due to invalid data."
                : "Saved {$saved} question(s) to your bank.");
        } else {
            flash('error', 'No valid questions to save. Check subjects, text, and MCQ options.');
        }
        redirect('/teacher/questions.php');
    }

    if ($action === 'save') {
        $id = (int)request('id', 0);
        $parsed = teacher_question_payload([
            'subject_id' => request('subject_id'),
            'question_text' => request('question_text'),
            'question_type' => request('question_type', 'mcq'),
            'difficulty' => request('difficulty', 'medium'),
            'marks' => request('marks', 1),
            'option_a' => request('option_a'),
            'option_b' => request('option_b'),
            'option_c' => request('option_c'),
            'option_d' => request('option_d'),
            'correct_answer' => request('correct_answer'),
        ], $subjectIds);
        if (!$parsed['ok']) {
            flash('error', $parsed['error']);
        } else {
            [$subjectId, $text, $type, $difficulty, $oa, $ob, $oc, $od, $correct, $marks] = $parsed['data'];
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
if (in_array($filterDiff, ['easy', 'medium', 'hard'], true)) {
    $where[] = 'q.difficulty=?';
    $params[] = $filterDiff;
}
if (in_array($filterType, ['mcq', 'descriptive'], true)) {
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
$pageSubtitle = 'Compose several questions, then save them all at once';
$activeNav = 'questions';
require dirname(__DIR__) . '/includes/header.php';

$subjectsJson = array_map(static fn($s) => [
    'id' => (int)$s['id'],
    'code' => $s['code'],
    'name' => $s['name'],
], $subjects);
?>
<div class="toolbar">
    <form class="toolbar-left" method="get">
        <select class="form-select" name="subject_id" style="width:auto">
            <option value="">All subjects</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $filterSubject === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['code']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" name="difficulty" style="width:auto">
            <option value="">All levels</option>
            <?php foreach (['easy', 'medium', 'hard'] as $d): ?>
                <option value="<?= $d ?>" <?= $filterDiff === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" name="type" style="width:auto">
            <option value="">All types</option>
            <option value="mcq" <?= $filterType === 'mcq' ? 'selected' : '' ?>>MCQ</option>
            <option value="descriptive" <?= $filterType === 'descriptive' ? 'selected' : '' ?>>Descriptive</option>
        </select>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
    <button class="btn btn-primary" type="button" id="openComposeBtn"><?= oems_icon('plus') ?> Compose questions</button>
</div>

<?php if (!$subjects): ?>
    <div class="alert alert-warning">No subjects assigned yet. Ask an admin to assign subjects before adding questions.</div>
<?php else: ?>
<section class="panel" id="composePanel" hidden>
    <div class="panel-header">
        <h2>Compose questions</h2>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="badge">Fill → Add to list → Save all</span>
            <button type="button" class="btn btn-sm btn-secondary" id="closeComposeBtn">Hide</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="form-row">
            <div class="form-group"><label>Subject</label>
                <select class="form-select" id="draftSubject" required>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= e($s['code'] . ' — ' . $s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Type</label>
                <select class="form-select" id="draftType">
                    <option value="mcq">MCQ</option>
                    <option value="descriptive">Descriptive</option>
                </select>
            </div>
        </div>
        <div class="form-group"><label>Question</label><textarea class="form-control" id="draftText" rows="3" placeholder="Type the question…"></textarea></div>
        <div id="draftMcqFields">
            <div class="form-row">
                <div class="form-group"><label>Option A</label><input class="form-control" id="draftA"></div>
                <div class="form-group"><label>Option B</label><input class="form-control" id="draftB"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Option C</label><input class="form-control" id="draftC"></div>
                <div class="form-group"><label>Option D</label><input class="form-control" id="draftD"></div>
            </div>
            <div class="form-group"><label>Correct answer</label>
                <select class="form-select" id="draftCorrect">
                    <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
                        <option value="<?= $opt ?>"><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Difficulty</label>
                <select class="form-select" id="draftDifficulty">
                    <?php foreach (['easy', 'medium', 'hard'] as $d): ?>
                        <option value="<?= $d ?>" <?= $d === 'medium' ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Marks</label><input class="form-control" type="number" step="0.5" min="0.5" id="draftMarks" value="1"></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px">
            <button class="btn btn-primary" type="button" id="draftAddBtn"><?= oems_icon('plus') ?> Add to list</button>
            <span style="color:var(--muted);font-size:.9rem">Question goes into the list below — nothing is saved yet.</span>
        </div>
    </div>
</section>

<section class="panel" id="draftListPanel" hidden>
    <div class="panel-header">
        <h2>Questions ready to save</h2>
        <span class="badge badge-brand" id="draftCount">0</span>
    </div>
    <div class="panel-body">
        <div id="draftEmpty" class="empty-state" style="padding:24px">
            <h3>No questions in the list yet</h3>
            <p>Use the compose form above, then click <strong>Add to list</strong>.</p>
        </div>
        <div id="draftList" class="list-feed" style="display:none;gap:10px"></div>
        <form method="post" id="bulkSaveForm" style="margin-top:16px;display:none">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk_save">
            <input type="hidden" name="questions_json" id="questionsJson" value="[]">
            <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                <button class="btn btn-secondary" type="button" id="draftClearBtn">Clear list</button>
                <button class="btn btn-primary" type="submit" id="bulkSaveBtn">Save all to bank</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <h2>Saved question bank</h2>
        <span class="badge"><?= count($questions) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Question</th><th>Subject</th><th>Type</th><th>Difficulty</th><th>Marks</th><th></th></tr></thead>
            <tbody>
            <?php if (!$questions): ?>
                <tr><td colspan="6"><div class="empty-state"><h3>No saved questions yet</h3><p>Compose above and save them to your bank.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($questions as $q): ?>
                <tr>
                    <td style="max-width:420px"><?= e(mb_strimwidth($q['question_text'], 0, 120, '…')) ?></td>
                    <td><?= e($q['subject_code']) ?></td>
                    <td><span class="badge"><?= e($q['question_type']) ?></span></td>
                    <td><span class="badge badge-<?= $q['difficulty'] === 'hard' ? 'danger' : ($q['difficulty'] === 'easy' ? 'success' : 'warning') ?>"><?= e($q['difficulty']) ?></span></td>
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

<?php if ($edit): ?>
<div class="modal-backdrop" id="qModal">
    <div class="modal" style="width:min(720px,100%)">
        <div class="modal-header"><h2>Edit question</h2><a class="icon-btn" href="<?= url('/teacher/questions.php') ?>" aria-label="Close"><?= oems_icon('x') ?></a></div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label>Subject</label>
                        <select class="form-select" name="subject_id" required>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= ((int)$edit['subject_id'] === (int)$s['id']) ? 'selected' : '' ?>><?= e($s['code'] . ' — ' . $s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Type</label>
                        <select class="form-select" name="question_type" id="qType">
                            <option value="mcq" <?= $edit['question_type'] === 'mcq' ? 'selected' : '' ?>>MCQ</option>
                            <option value="descriptive" <?= $edit['question_type'] === 'descriptive' ? 'selected' : '' ?>>Descriptive</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Question</label><textarea class="form-control" name="question_text" required><?= e($edit['question_text']) ?></textarea></div>
                <div id="mcqFields">
                    <div class="form-row">
                        <div class="form-group"><label>Option A</label><input class="form-control" name="option_a" value="<?= e((string)$edit['option_a']) ?>"></div>
                        <div class="form-group"><label>Option B</label><input class="form-control" name="option_b" value="<?= e((string)$edit['option_b']) ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Option C</label><input class="form-control" name="option_c" value="<?= e((string)$edit['option_c']) ?>"></div>
                        <div class="form-group"><label>Option D</label><input class="form-control" name="option_d" value="<?= e((string)$edit['option_d']) ?>"></div>
                    </div>
                    <div class="form-group"><label>Correct answer</label>
                        <select class="form-select" name="correct_answer">
                            <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($edit['correct_answer'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Difficulty</label>
                        <select class="form-select" name="difficulty">
                            <?php foreach (['easy', 'medium', 'hard'] as $d): ?>
                                <option value="<?= $d ?>" <?= ($edit['difficulty'] ?? 'medium') === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Marks</label><input class="form-control" type="number" step="0.5" min="0.5" name="marks" value="<?= e((string)($edit['marks'] ?? '1')) ?>"></div>
                </div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-secondary" href="<?= url('/teacher/questions.php') ?>">Cancel</a>
                <button class="btn btn-primary" type="submit">Update question</button>
            </div>
        </form>
    </div>
</div>
<script>
(function(){
  const t=document.getElementById('qType'), m=document.getElementById('mcqFields');
  function sync(){ if(m&&t) m.style.display = t.value==='mcq' ? '' : 'none'; }
  t?.addEventListener('change', sync); sync();
  document.body.style.overflow = 'hidden';
})();
</script>
<?php endif; ?>

<script>
(function(){
  const subjects = <?= json_encode($subjectsJson, JSON_UNESCAPED_UNICODE) ?>;
  const subjectLookup = Object.fromEntries(subjects.map(s => [String(s.id), s]));
  const draft = [];
  const els = {
    type: document.getElementById('draftType'),
    mcq: document.getElementById('draftMcqFields'),
    subject: document.getElementById('draftSubject'),
    text: document.getElementById('draftText'),
    a: document.getElementById('draftA'),
    b: document.getElementById('draftB'),
    c: document.getElementById('draftC'),
    d: document.getElementById('draftD'),
    correct: document.getElementById('draftCorrect'),
    difficulty: document.getElementById('draftDifficulty'),
    marks: document.getElementById('draftMarks'),
    add: document.getElementById('draftAddBtn'),
    list: document.getElementById('draftList'),
    empty: document.getElementById('draftEmpty'),
    count: document.getElementById('draftCount'),
    form: document.getElementById('bulkSaveForm'),
    json: document.getElementById('questionsJson'),
    clear: document.getElementById('draftClearBtn'),
    compose: document.getElementById('composePanel'),
    draftPanel: document.getElementById('draftListPanel'),
    openBtn: document.getElementById('openComposeBtn'),
    closeBtn: document.getElementById('closeComposeBtn'),
  };
  if (!els.add || !els.compose) return;

  function openCompose(){
    els.compose.hidden = false;
    if (els.draftPanel) els.draftPanel.hidden = false;
    const topbar = document.querySelector('.topbar');
    const offset = (topbar ? topbar.offsetHeight : 72) + 12;
    const y = els.compose.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
    setTimeout(() => els.text?.focus(), 350);
  }
  function closeCompose(){
    if (draft.length && !confirm('Hide compose? Your list will stay until you leave this page.')) return;
    els.compose.hidden = true;
    if (els.draftPanel && !draft.length) els.draftPanel.hidden = true;
  }
  els.openBtn?.addEventListener('click', openCompose);
  els.closeBtn?.addEventListener('click', closeCompose);

  function syncType(){
    els.mcq.style.display = els.type.value === 'mcq' ? '' : 'none';
  }
  function clearCompose(keepMeta){
    els.text.value = '';
    els.a.value = '';
    els.b.value = '';
    els.c.value = '';
    els.d.value = '';
    els.correct.value = 'A';
    if (!keepMeta) {
      els.difficulty.value = 'medium';
      els.marks.value = '1';
    }
    els.text.focus();
  }
  function escapeHtml(str){
    return String(str).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  }
  function render(){
    els.count.textContent = String(draft.length);
    els.json.value = JSON.stringify(draft);
    if (els.draftPanel && draft.length) els.draftPanel.hidden = false;
    if (!draft.length) {
      els.empty.style.display = '';
      els.list.style.display = 'none';
      els.list.innerHTML = '';
      els.form.style.display = 'none';
      return;
    }
    els.empty.style.display = 'none';
    els.list.style.display = 'grid';
    els.form.style.display = '';
    els.list.innerHTML = draft.map((q, i) => {
      const sub = subjectLookup[String(q.subject_id)] || {};
      const opts = q.question_type === 'mcq'
        ? `<div style="margin-top:8px;color:var(--muted);font-size:.88rem;display:grid;gap:2px">
            <span>A. ${escapeHtml(q.option_a || '')}${q.correct_answer==='A'?' ✓':''}</span>
            <span>B. ${escapeHtml(q.option_b || '')}${q.correct_answer==='B'?' ✓':''}</span>
            ${q.option_c ? `<span>C. ${escapeHtml(q.option_c)}${q.correct_answer==='C'?' ✓':''}</span>` : ''}
            ${q.option_d ? `<span>D. ${escapeHtml(q.option_d)}${q.correct_answer==='D'?' ✓':''}</span>` : ''}
          </div>` : '<div style="margin-top:6px;color:var(--muted);font-size:.88rem">Descriptive answer</div>';
      return `<div class="feed-item" style="align-items:flex-start;gap:12px">
        <div style="flex:1;min-width:0">
          <div class="chip-row" style="margin-bottom:6px">
            <span class="badge badge-brand">#${i + 1}</span>
            <span class="badge">${escapeHtml(sub.code || '—')}</span>
            <span class="badge">${escapeHtml(q.question_type)}</span>
            <span class="badge badge-${q.difficulty==='hard'?'danger':(q.difficulty==='easy'?'success':'warning')}">${escapeHtml(q.difficulty)}</span>
            <span class="badge">${escapeHtml(String(q.marks))} marks</span>
          </div>
          <strong style="display:block;line-height:1.45">${escapeHtml(q.question_text)}</strong>
          ${opts}
        </div>
        <button type="button" class="btn btn-sm btn-danger" data-remove="${i}">Remove</button>
      </div>`;
    }).join('');
  }

  els.type.addEventListener('change', syncType);
  syncType();
  els.add.addEventListener('click', () => {
    const item = {
      subject_id: parseInt(els.subject.value, 10) || 0,
      question_type: els.type.value,
      question_text: (els.text.value || '').trim(),
      option_a: (els.a.value || '').trim(),
      option_b: (els.b.value || '').trim(),
      option_c: (els.c.value || '').trim(),
      option_d: (els.d.value || '').trim(),
      correct_answer: els.correct.value,
      difficulty: els.difficulty.value,
      marks: parseFloat(els.marks.value) || 1,
    };
    if (!item.question_text) {
      alert('Please enter the question text.');
      els.text.focus();
      return;
    }
    if (item.question_type === 'mcq' && (!item.option_a || !item.option_b)) {
      alert('MCQ needs at least Option A and Option B.');
      return;
    }
    draft.push(item);
    render();
    clearCompose(true);
  });
  els.list.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-remove]');
    if (!btn) return;
    draft.splice(parseInt(btn.getAttribute('data-remove'), 10), 1);
    render();
  });
  els.clear.addEventListener('click', () => {
    if (!draft.length) return;
    if (!confirm('Clear the entire list?')) return;
    draft.length = 0;
    render();
  });
  els.form.addEventListener('submit', (e) => {
    if (!draft.length) {
      e.preventDefault();
      alert('Add at least one question to the list first.');
      return;
    }
    if (!confirm('Save ' + draft.length + ' question(s) to your bank?')) {
      e.preventDefault();
    }
  });
  render();
})();
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

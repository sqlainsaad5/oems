<?php
/**
 * Teacher — Create / manage papers (Mega Exam + sections + approval)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_teacher();
$user = $ctx['user'];
$teacher = $ctx['teacher'];
$tid = (int)$teacher['id'];

$subjectsStmt = db()->prepare(
    'SELECT s.* FROM subjects s JOIN teacher_subjects ts ON ts.subject_id=s.id WHERE ts.teacher_id=? ORDER BY s.name'
);
$subjectsStmt->execute([$tid]);
$subjects = $subjectsStmt->fetchAll();
$subjectIds = array_map('intval', array_column($subjects, 'id'));

$megas = db()->query("SELECT id, title, code FROM mega_exams WHERE status='active' ORDER BY title")->fetchAll();
$departments = db()->query("SELECT id, name, code FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$batches = db()->query("SELECT id, name, year, department_id FROM batches WHERE status='active' ORDER BY year DESC, name")->fetchAll();

function recalc_exam_marks(int $examId): void
{
    db()->prepare('UPDATE exams SET total_marks=(SELECT COALESCE(SUM(marks),0) FROM exam_questions WHERE exam_id=?) WHERE id=?')
        ->execute([$examId, $examId]);
}

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');

    if ($action === 'save_exam') {
        $id = (int)request('id', 0);
        $subjectId = (int)request('subject_id');
        $megaId = (int)request('mega_exam_id', 0) ?: null;
        $departmentId = (int)request('department_id', 0) ?: null;
        $batchId = (int)request('batch_id', 0) ?: null;
        $semester = (int)request('semester', 0) ?: null;
        $paperCode = trim((string)request('paper_code'));
        $title = trim((string)request('title'));
        $description = trim((string)request('description'));
        $duration = max(5, (int)request('duration_minutes', 60));
        $passing = (float)request('passing_marks', 0);
        $availability = (string)request('availability_mode', 'scheduled');
        if (!in_array($availability, ['scheduled', 'always'], true)) {
            $availability = 'scheduled';
        }
        $start = (string)request('start_time');
        $end = (string)request('end_time');
        if ($availability === 'always') {
            $start = $start !== '' ? $start : date('Y-m-d H:i:s', strtotime('-1 day'));
            $end = $end !== '' ? $end : date('Y-m-d H:i:s', strtotime('+10 years'));
        } else {
            // Duration follows the scheduled window (start → end).
            $startTs = strtotime(str_replace('T', ' ', $start));
            $endTs = strtotime(str_replace('T', ' ', $end));
            if ($startTs && $endTs && $endTs > $startTs) {
                $duration = max(5, (int)round(($endTs - $startTs) / 60));
            }
        }
        $examPassword = trim((string)request('exam_password'));
        $mode = (string)request('selection_mode', 'manual');
        $randomCount = (int)request('random_count', 0) ?: null;
        $instructions = trim((string)request('instructions'));
        $submitForApproval = (string)request('submit_for_approval', '') === '1';
        $sectionTitles = (array)request('section_title', []);
        $sectionCodes = (array)request('section_code', []);
        $sectionTypes = (array)request('section_type', []);
        $sectionQuestions = (array)request('section_questions', []);

        if (!in_array($subjectId, $subjectIds, true) || $title === '' || !$start || !$end) {
            flash('error', 'Subject, title, and schedule are required.');
            redirect('/teacher/exams.php');
        }
        if ($availability === 'scheduled' && strtotime($end) <= strtotime($start)) {
            flash('error', 'End time must be after start time.');
            redirect('/teacher/exams.php');
        }

        try {
            db()->beginTransaction();
            $approvalStatus = 'draft';
            $status = 'draft';

            if ($id) {
                $own = db()->prepare('SELECT * FROM exams WHERE id=? AND teacher_id=?');
                $own->execute([$id, $tid]);
                $existing = $own->fetch();
                if (!$existing) {
                    throw new RuntimeException('Not found');
                }
                if ($existing['approval_status'] === 'approved') {
                    $approvalStatus = 'approved';
                    $status = $existing['status'];
                } elseif ($existing['approval_status'] === 'pending' && !$submitForApproval) {
                    $approvalStatus = 'pending';
                }
                db()->prepare(
                    'UPDATE exams SET subject_id=?, mega_exam_id=?, department_id=?, batch_id=?, semester=?, paper_code=?, title=?, description=?, duration_minutes=?, passing_marks=?, start_time=?, end_time=?, availability_mode=?, exam_password=?, selection_mode=?, random_count=?, instructions=?, status=?, approval_status=? WHERE id=? AND teacher_id=?'
                )->execute([
                    $subjectId, $megaId, $departmentId, $batchId, $semester, $paperCode !== '' ? $paperCode : null,
                    $title, $description, $duration, $passing, $start, $end, $availability,
                    $examPassword !== '' ? $examPassword : null, $mode, $randomCount, $instructions,
                    $status, $approvalStatus, $id, $tid,
                ]);
                $examId = $id;
                db()->prepare('DELETE FROM exam_questions WHERE exam_id=?')->execute([$examId]);
                db()->prepare('DELETE FROM paper_sections WHERE exam_id=?')->execute([$examId]);
            } else {
                db()->prepare(
                    'INSERT INTO exams (teacher_id, subject_id, mega_exam_id, department_id, batch_id, semester, paper_code, title, description, duration_minutes, passing_marks, start_time, end_time, availability_mode, exam_password, selection_mode, random_count, instructions, status, approval_status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $tid, $subjectId, $megaId, $departmentId, $batchId, $semester, $paperCode !== '' ? $paperCode : null,
                    $title, $description, $duration, $passing, $start, $end, $availability,
                    $examPassword !== '' ? $examPassword : null, $mode, $randomCount, $instructions, 'draft', 'draft',
                ]);
                $examId = (int)db()->lastInsertId();
            }

            $insSec = db()->prepare(
                'INSERT INTO paper_sections (exam_id, title, section_code, section_type, sort_order) VALUES (?,?,?,?,?)'
            );
            $insEq = db()->prepare(
                'INSERT INTO exam_questions (exam_id, question_id, section_id, marks, sort_order) VALUES (?,?,?,?,?)'
            );

            $sectionCount = max(count($sectionTitles), 1);
            $anyQuestions = false;
            for ($i = 0; $i < $sectionCount; $i++) {
                $secTitle = trim((string)($sectionTitles[$i] ?? ''));
                if ($secTitle === '') {
                    $secTitle = 'Section ' . chr(65 + $i);
                }
                $secCode = strtoupper(trim((string)($sectionCodes[$i] ?? chr(65 + $i))));
                $secType = (string)($sectionTypes[$i] ?? 'mixed');
                if (!in_array($secType, ['mcq', 'descriptive', 'mixed'], true)) {
                    $secType = 'mixed';
                }
                $insSec->execute([$examId, $secTitle, $secCode !== '' ? $secCode : chr(65 + $i), $secType, $i + 1]);
                $sectionId = (int)db()->lastInsertId();

                $qids = array_map('intval', (array)($sectionQuestions[$i] ?? []));
                if ($mode === 'random' && $i === 0) {
                    $count = max(1, (int)$randomCount);
                    $typeSql = $secType === 'mixed' ? '' : ' AND question_type=?';
                    $rq = db()->prepare(
                        'SELECT id, marks, question_type FROM questions WHERE subject_id=? AND teacher_id=? AND status="active"'
                        . $typeSql . ' ORDER BY RAND() LIMIT ' . $count
                    );
                    $rqArgs = [$subjectId, $tid];
                    if ($secType !== 'mixed') {
                        $rqArgs[] = $secType;
                    }
                    $rq->execute($rqArgs);
                    $order = 1;
                    foreach ($rq->fetchAll() as $q) {
                        $insEq->execute([$examId, $q['id'], $sectionId, $q['marks'], $order++]);
                        $anyQuestions = true;
                    }
                } else {
                    $order = 1;
                    foreach ($qids as $qid) {
                        $qs = db()->prepare('SELECT id, marks, question_type FROM questions WHERE id=? AND teacher_id=? AND subject_id=?');
                        $qs->execute([$qid, $tid, $subjectId]);
                        $q = $qs->fetch();
                        if (!$q) {
                            continue;
                        }
                        if ($secType !== 'mixed' && ($q['question_type'] ?? '') !== $secType) {
                            continue;
                        }
                        $insEq->execute([$examId, $q['id'], $sectionId, $q['marks'], $order++]);
                        $anyQuestions = true;
                    }
                }
            }

            if (!$anyQuestions) {
                ensure_default_paper_section($examId);
            }
            recalc_exam_marks($examId);

            if ($submitForApproval) {
                $c = db()->prepare('SELECT COUNT(*) FROM exam_questions WHERE exam_id=?');
                $c->execute([$examId]);
                $qCount = (int)$c->fetchColumn();
                if ($qCount < 1) {
                    throw new RuntimeException('Add questions before submitting for approval.');
                }
                if (!$megaId) {
                    throw new RuntimeException('Select a Mega Exam before submitting for approval.');
                }
                db()->prepare("UPDATE exams SET approval_status='pending', status='draft' WHERE id=? AND teacher_id=?")
                    ->execute([$examId, $tid]);
                notify_role_users('admin', 'Paper pending approval: ' . $title, 'A teacher submitted a paper for review.', 'alert', '/admin/paper_approvals.php');
            }

            db()->commit();
            flash('success', $submitForApproval ? 'Paper submitted for admin approval.' : ($id ? 'Paper updated.' : 'Paper created as draft.'));
            log_activity((int)$user['id'], 'paper_save', "Exam #$examId");
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not save paper.');
        }
        redirect('/teacher/exams.php');
    }

    if ($action === 'submit_approval') {
        $id = (int)request('id');
        $own = db()->prepare('SELECT * FROM exams WHERE id=? AND teacher_id=?');
        $own->execute([$id, $tid]);
        $paper = $own->fetch();
        if (!$paper) {
            flash('error', 'Paper not found.');
        } else {
            $c = db()->prepare('SELECT COUNT(*) FROM exam_questions WHERE exam_id=?');
            $c->execute([$id]);
            if ((int)$c->fetchColumn() < 1 || empty($paper['mega_exam_id'])) {
                flash('error', 'Paper needs a Mega Exam and at least one question before submission.');
            } else {
                db()->prepare("UPDATE exams SET approval_status='pending' WHERE id=?")->execute([$id]);
                notify_role_users('admin', 'Paper pending approval: ' . $paper['title'], 'A teacher submitted a paper for review.', 'alert', '/admin/paper_approvals.php');
                flash('success', 'Submitted for approval.');
            }
        }
        redirect('/teacher/exams.php');
    }

    if ($action === 'delete') {
        $id = (int)request('id');
        db()->prepare('DELETE FROM exams WHERE id=? AND teacher_id=?')->execute([$id, $tid]);
        flash('success', 'Paper deleted.');
        redirect('/teacher/exams.php');
    }
}

$exams = db()->prepare(
    "SELECT e.*, s.name AS subject_name, s.code AS subject_code, m.title AS mega_title, m.code AS mega_code,
            (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id=e.id) AS qcount,
            (SELECT COUNT(*) FROM paper_sections ps WHERE ps.exam_id=e.id) AS section_count
     FROM exams e
     JOIN subjects s ON s.id=e.subject_id
     LEFT JOIN mega_exams m ON m.id=e.mega_exam_id
     WHERE e.teacher_id=? ORDER BY e.created_at DESC"
);
$exams->execute([$tid]);
$exams = $exams->fetchAll();

$editId = (int)request('edit', 0);
$create = (string)request('new', '') === '1';
$edit = null;
$editSections = [];
$selectedBySection = [];
if ($editId) {
    $es = db()->prepare('SELECT * FROM exams WHERE id=? AND teacher_id=?');
    $es->execute([$editId, $tid]);
    $edit = $es->fetch() ?: null;
    if ($edit) {
        $editSections = fetch_paper_sections($editId);
        foreach ($editSections as $sec) {
            $sq = db()->prepare('SELECT question_id FROM exam_questions WHERE exam_id=? AND section_id=?');
            $sq->execute([$editId, (int)$sec['id']]);
            $selectedBySection[(int)$sec['id']] = array_map('intval', $sq->fetchAll(PDO::FETCH_COLUMN));
        }
    }
}

$bank = [];
if ($subjects) {
    $bankStmt = db()->prepare('SELECT id, subject_id, question_text, question_type, difficulty, marks FROM questions WHERE teacher_id=? AND status="active" ORDER BY subject_id, id');
    $bankStmt->execute([$tid]);
    $bank = $bankStmt->fetchAll();
}

$pageTitle = 'Manage Papers';
$pageSubtitle = 'Create papers under Mega Exams, add sections, then submit for approval';
$activeNav = 'exams';
require dirname(__DIR__) . '/includes/header.php';
$showModal = $edit || $create;
if (!$editSections && $showModal) {
    $editSections = [
        ['id' => 0, 'title' => 'Section A (MCQ)', 'section_code' => 'A', 'section_type' => 'mcq'],
        ['id' => 0, 'title' => 'Section B (Descriptive)', 'section_code' => 'B', 'section_type' => 'descriptive'],
    ];
}
?>
<div class="toolbar">
    <div></div>
    <a class="btn btn-primary" href="?new=1"><?= oems_icon('plus') ?> Create Paper</a>
</div>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Paper</th><th>Mega Exam</th><th>Subject</th><th>Sections</th><th>Qs</th><th>Approval</th><th>Availability</th><th></th></tr></thead>
            <tbody>
            <?php if (!$exams): ?><tr><td colspan="8"><div class="empty-state"><h3>No papers</h3><p>Create a paper, attach sections/questions, then submit for admin approval.</p></div></td></tr><?php endif; ?>
            <?php foreach ($exams as $ex): ?>
                <tr>
                    <td><strong><?= e($ex['title']) ?></strong><?php if ($ex['paper_code']): ?><br><small><?= e($ex['paper_code']) ?></small><?php endif; ?></td>
                    <td><?= e($ex['mega_title'] ? $ex['mega_title'].' ('.$ex['mega_code'].')' : '—') ?></td>
                    <td><?= e($ex['subject_code']) ?></td>
                    <td><?= (int)$ex['section_count'] ?></td>
                    <td><?= (int)$ex['qcount'] ?></td>
                    <td><span class="badge <?= match($ex['approval_status']) { 'approved'=>'badge-success','pending'=>'badge-warning','rejected'=>'badge-danger', default=>'badge-brand' } ?>"><?= e($ex['approval_status']) ?></span></td>
                    <td><?= ($ex['availability_mode'] ?? '') === 'always' ? 'Always' : e(format_datetime($ex['start_time'], 'M j, g:i A')) ?></td>
                    <td class="actions">
                        <?php if ($ex['approval_status'] !== 'approved'): ?>
                            <a class="btn btn-sm btn-secondary" href="?edit=<?= (int)$ex['id'] ?>">Edit</a>
                        <?php endif; ?>
                        <?php if (in_array($ex['approval_status'], ['draft','rejected'], true)): ?>
                            <form method="post" style="display:inline">
                                <?= csrf_field() ?><input type="hidden" name="action" value="submit_approval"><input type="hidden" name="id" value="<?= (int)$ex['id'] ?>">
                                <button class="btn btn-sm btn-primary" type="submit">Submit</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" style="display:inline" data-confirm="Delete paper?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$ex['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal-backdrop" id="examModal" <?= $showModal ? '' : 'hidden' ?>>
    <div class="modal" style="width:min(920px,100%)">
        <div class="modal-header"><h2><?= $edit ? 'Edit paper' : 'Create paper' ?></h2>
            <a class="icon-btn" href="<?= url('/teacher/exams.php') ?>"><?= oems_icon('x') ?></a>
        </div>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_exam">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label>Paper title</label><input class="form-control" name="title" required value="<?= e($edit['title'] ?? '') ?>"></div>
                    <div class="form-group"><label>Paper code</label><input class="form-control" name="paper_code" maxlength="40" value="<?= e($edit['paper_code'] ?? '') ?>" placeholder="Eg1"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Mega Exam</label>
                        <select class="form-select" name="mega_exam_id">
                            <option value="">— Select —</option>
                            <?php foreach ($megas as $m): ?>
                                <option value="<?= (int)$m['id'] ?>" <?= ((int)($edit['mega_exam_id'] ?? 0)===(int)$m['id'])?'selected':'' ?>><?= e($m['title'].' ('.$m['code'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Subject</label>
                        <select class="form-select" name="subject_id" id="examSubject" required>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= ((int)($edit['subject_id'] ?? 0)===(int)$s['id'])?'selected':'' ?>><?= e($s['code'].' — '.$s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Department</label>
                        <select class="form-select" name="department_id">
                            <option value="">—</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['id'] ?>" <?= ((int)($edit['department_id'] ?? 0)===(int)$d['id'])?'selected':'' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Batch</label>
                        <select class="form-select" name="batch_id">
                            <option value="">—</option>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" <?= ((int)($edit['batch_id'] ?? 0)===(int)$b['id'])?'selected':'' ?>><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Semester</label><input class="form-control" type="number" min="1" max="12" name="semester" value="<?= e((string)($edit['semester'] ?? '4')) ?>"></div>
                </div>
                <div class="form-group"><label>Description</label><textarea class="form-control" name="description"><?= e($edit['description'] ?? '') ?></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>Availability</label>
                        <select class="form-select" name="availability_mode" id="availMode">
                            <option value="scheduled" <?= ($edit['availability_mode'] ?? 'scheduled')==='scheduled'?'selected':'' ?>>Scheduled window</option>
                            <option value="always" <?= ($edit['availability_mode'] ?? '')==='always'?'selected':'' ?>>Always available</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Exam password (optional)</label>
                        <div class="password-field">
                            <input class="form-control" type="password" name="exam_password" id="examPassword" value="<?= e($edit['exam_password'] ?? '') ?>" placeholder="Students enter before start" autocomplete="new-password">
                            <button type="button" class="password-toggle" id="examPasswordToggle" aria-label="Show password" title="Show password">
                                <?= oems_icon('eye') ?>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-row" id="scheduleRow">
                    <div class="form-group"><label>Start</label><input class="form-control" type="datetime-local" name="start_time" id="examStart" value="<?= e($edit ? date('Y-m-d\TH:i', strtotime($edit['start_time'])) : '') ?>"></div>
                    <div class="form-group"><label>End</label><input class="form-control" type="datetime-local" name="end_time" id="examEnd" value="<?= e($edit ? date('Y-m-d\TH:i', strtotime($edit['end_time'])) : '') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Duration (minutes)</label>
                        <input class="form-control" type="number" name="duration_minutes" id="examDuration" min="0" value="<?= e((string)($edit['duration_minutes'] ?? '0')) ?>">
                        <small id="durationHint" style="color:var(--muted);display:block;margin-top:6px">Set from Start → End when scheduled.</small>
                    </div>
                    <div class="form-group"><label>Passing marks</label><input class="form-control" type="number" step="0.5" name="passing_marks" value="<?= e((string)($edit['passing_marks'] ?? '0')) ?>"></div>
                    <div class="form-group"><label>Question selection</label>
                        <select class="form-select" name="selection_mode" id="selMode">
                            <option value="manual" <?= ($edit['selection_mode'] ?? '')==='manual'?'selected':'' ?>>Manual per section</option>
                            <option value="random" <?= ($edit['selection_mode'] ?? '')==='random'?'selected':'' ?>>Random (section A)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="randomCountWrap"><label>Random question count</label><input class="form-control" type="number" name="random_count" min="1" value="<?= e((string)($edit['random_count'] ?? '5')) ?>"></div>
                <div class="form-group"><label>Instructions</label><textarea class="form-control" name="instructions"><?= e($edit['instructions'] ?? '') ?></textarea></div>

                <h3 style="margin:18px 0 10px;font-size:1rem">Sections & questions</h3>
                <div id="sectionsWrap">
                    <?php foreach ($editSections as $si => $sec):
                        $secId = (int)($sec['id'] ?? 0);
                        $sel = $selectedBySection[$secId] ?? [];
                    ?>
                    <div class="panel" style="margin-bottom:12px;padding:12px" data-section-block>
                        <div class="form-row">
                            <div class="form-group"><label>Section title</label><input class="form-control" name="section_title[]" value="<?= e($sec['title']) ?>" required></div>
                            <div class="form-group"><label>Code</label><input class="form-control" name="section_code[]" value="<?= e($sec['section_code']) ?>" maxlength="5"></div>
                            <div class="form-group"><label>Type</label>
                                <select class="form-select section-type-select" name="section_type[]">
                                    <?php foreach (['mcq','descriptive','mixed'] as $t): ?>
                                        <option value="<?= $t ?>" <?= ($sec['section_type'] ?? '')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="section-qs list-feed" style="max-height:180px;overflow:auto">
                            <?php foreach ($bank as $q): ?>
                                <label class="feed-item" data-subject="<?= (int)$q['subject_id'] ?>" data-qtype="<?= e($q['question_type']) ?>" style="cursor:pointer">
                                    <input type="checkbox" name="section_questions[<?= (int)$si ?>][]" value="<?= (int)$q['id'] ?>" <?= in_array((int)$q['id'], $sel, true)?'checked':'' ?>>
                                    <div>
                                        <strong><?= e(mb_strimwidth($q['question_text'], 0, 90, '…')) ?></strong>
                                        <span><?= e($q['question_type']) ?> · <?= e((string)$q['marks']) ?> marks</span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer" style="flex-wrap:wrap;gap:8px">
                <a class="btn btn-secondary" href="<?= url('/teacher/exams.php') ?>">Cancel</a>
                <button class="btn btn-secondary" type="submit" name="submit_for_approval" value="0">Save draft</button>
                <button class="btn btn-primary" type="submit" name="submit_for_approval" value="1">Save & submit for approval</button>
            </div>
        </form>
    </div>
</div>
<script>
(function(){
  const mode=document.getElementById('selMode');
  const rand=document.getElementById('randomCountWrap');
  const subj=document.getElementById('examSubject');
  const avail=document.getElementById('availMode');
  const schedule=document.getElementById('scheduleRow');
  const startInput=document.getElementById('examStart');
  const endInput=document.getElementById('examEnd');
  const durationInput=document.getElementById('examDuration');
  const durationHint=document.getElementById('durationHint');
  function syncMode(){ rand.style.display = mode.value==='random' ? '' : 'none'; }
  function syncDurationFromSchedule(){
    if (!startInput || !endInput || !durationInput) return;
    if (avail?.value === 'always') {
      durationInput.readOnly = false;
      if (durationHint) durationHint.textContent = 'Set manually when paper is always available.';
      return;
    }
    durationInput.readOnly = true;
    const s = startInput.value ? new Date(startInput.value) : null;
    const e = endInput.value ? new Date(endInput.value) : null;
    if (s && e && !isNaN(s) && !isNaN(e) && e > s) {
      const mins = Math.max(5, Math.round((e - s) / 60000));
      durationInput.value = String(mins);
      if (durationHint) durationHint.textContent = 'Auto-set from Start → End (' + mins + ' min).';
    } else {
      durationInput.value = '0';
      if (durationHint) durationHint.textContent = 'Pick Start and End — duration fills automatically.';
    }
  }
  function syncAvail(){
    schedule.style.opacity = avail.value==='always' ? '.45' : '1';
    syncDurationFromSchedule();
  }
  function syncSectionQuestions(){
    const sid = subj?.value || '';
    document.querySelectorAll('[data-section-block]').forEach(block=>{
      const secType = block.querySelector('.section-type-select')?.value || 'mixed';
      block.querySelectorAll('.section-qs .feed-item').forEach(el=>{
        const matchSubject = el.dataset.subject === sid;
        const matchType = secType === 'mixed' || el.dataset.qtype === secType;
        const show = matchSubject && matchType;
        el.style.display = show ? '' : 'none';
        if (!show) {
          const cb = el.querySelector('input[type="checkbox"]');
          if (cb) cb.checked = false;
        }
      });
    });
  }
  mode?.addEventListener('change', syncMode);
  avail?.addEventListener('change', syncAvail);
  startInput?.addEventListener('change', syncDurationFromSchedule);
  endInput?.addEventListener('change', syncDurationFromSchedule);
  startInput?.addEventListener('input', syncDurationFromSchedule);
  endInput?.addEventListener('input', syncDurationFromSchedule);
  const pwd=document.getElementById('examPassword');
  const pwdToggle=document.getElementById('examPasswordToggle');
  const eyeSvg = <?= json_encode(oems_icon('eye')) ?>;
  const eyeOffSvg = <?= json_encode(oems_icon('eye-off')) ?>;
  pwdToggle?.addEventListener('click', ()=>{
    if (!pwd) return;
    const show = pwd.type === 'password';
    pwd.type = show ? 'text' : 'password';
    pwdToggle.innerHTML = show ? eyeOffSvg : eyeSvg;
    pwdToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    pwdToggle.setAttribute('title', show ? 'Hide password' : 'Show password');
  });
  subj?.addEventListener('change', syncSectionQuestions);
  document.querySelectorAll('.section-type-select').forEach(sel=>{
    sel.addEventListener('change', syncSectionQuestions);
  });
  syncMode(); syncAvail(); syncSectionQuestions(); syncDurationFromSchedule();
})();
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

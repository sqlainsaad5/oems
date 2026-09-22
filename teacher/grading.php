<?php
/**
 * Teacher — Evaluate Answers (session view + pending descriptive queue)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_teacher();
$user = $ctx['user'];
$teacher = $ctx['teacher'];
$tid = (int)$teacher['id'];

$sessionExamId = (int)request('session_id', 0) ?: (int)request('exam_id', 0);
$sessionStudentId = (int)request('student_id', 0);

if (is_post()) {
    verify_csrf();
    $answerId = (int)request('answer_id');
    $marks = (float)request('marks_obtained');
    $feedback = trim((string)request('feedback'));
    $returnExam = (int)request('return_exam_id', 0);
    $returnStudent = (int)request('return_student_id', 0);

    $row = db()->prepare(
        "SELECT sa.*, q.marks AS max_marks, eq.marks AS exam_marks, q.teacher_id
         FROM student_answers sa
         JOIN questions q ON q.id=sa.question_id
         LEFT JOIN exam_questions eq ON eq.exam_id=sa.exam_id AND eq.question_id=sa.question_id
         WHERE sa.id=? AND q.teacher_id=?"
    );
    $row->execute([$answerId, $tid]);
    $ans = $row->fetch();
    if (!$ans) {
        flash('error', 'Answer not found.');
        redirect('/teacher/grading.php');
    }
    $max = (float)($ans['exam_marks'] ?? $ans['max_marks']);
    $marks = max(0, min($marks, $max));
    db()->prepare(
        'UPDATE student_answers SET marks_obtained=?, is_graded=1, graded_by=?, graded_at=NOW(), feedback=?, is_correct=? WHERE id=?'
    )->execute([$marks, $tid, $feedback ?: null, $marks >= ($max * 0.5) ? 1 : 0, $answerId]);

    $examId = (int)$ans['exam_id'];
    $studentId = (int)$ans['student_id'];
    require_once dirname(__DIR__) . '/includes/exam_helpers.php';
    upsert_result_from_answers($examId, $studentId);

    flash('success', 'Answer graded.');
    if ($returnExam && $returnStudent) {
        redirect('/teacher/grading.php?session_id=' . $returnExam . '&student_id=' . $returnStudent);
    }
    redirect('/teacher/grading.php');
}

// ---- Session / evaluate one student attempt ----
if ($sessionExamId && $sessionStudentId) {
    $examSt = db()->prepare(
        "SELECT e.*, s.name AS subject_name, m.title AS mega_title, m.code AS mega_code,
                d.name AS department_name, b.name AS batch_name
         FROM exams e
         JOIN subjects s ON s.id=e.subject_id
         LEFT JOIN mega_exams m ON m.id=e.mega_exam_id
         LEFT JOIN departments d ON d.id=e.department_id
         LEFT JOIN batches b ON b.id=e.batch_id
         WHERE e.id=? AND e.teacher_id=?"
    );
    $examSt->execute([$sessionExamId, $tid]);
    $exam = $examSt->fetch();
    if (!$exam) {
        flash('error', 'Paper not found.');
        redirect('/teacher/grading.php');
    }

    $stu = db()->prepare(
        "SELECT st.*, u.full_name, ea.status AS attempt_status, ea.submitted_at
         FROM students st
         JOIN users u ON u.id=st.user_id
         LEFT JOIN exam_attempts ea ON ea.exam_id=? AND ea.student_id=st.id
         WHERE st.id=?"
    );
    $stu->execute([$sessionExamId, $sessionStudentId]);
    $student = $stu->fetch();
    if (!$student) {
        flash('error', 'Student not found.');
        redirect('/teacher/grading.php');
    }

    $answers = db()->prepare(
        "SELECT sa.*, q.question_text, q.question_type, q.correct_answer, q.option_a, q.option_b, q.option_c, q.option_d,
                eq.marks AS max_marks, eq.section_id, ps.title AS section_title, ps.section_code, ps.section_type
         FROM exam_questions eq
         JOIN questions q ON q.id=eq.question_id
         LEFT JOIN paper_sections ps ON ps.id=eq.section_id
         LEFT JOIN student_answers sa ON sa.exam_id=eq.exam_id AND sa.question_id=eq.question_id AND sa.student_id=?
         WHERE eq.exam_id=?
         ORDER BY ps.sort_order, eq.sort_order, eq.id"
    );
    $answers->execute([$sessionStudentId, $sessionExamId]);
    $answers = $answers->fetchAll();

    $mcq = array_values(array_filter($answers, fn($a) => ($a['question_type'] ?? '') === 'mcq'));
    $desc = array_values(array_filter($answers, fn($a) => ($a['question_type'] ?? '') === 'descriptive'));

    $obtained = 0.0;
    $gradedCount = 0;
    foreach ($answers as $a) {
        if (!empty($a['is_graded'])) {
            $obtained += (float)$a['marks_obtained'];
            $gradedCount++;
        }
    }
    $total = (float)$exam['total_marks'];
    $pct = $total > 0 ? round(($obtained / $total) * 100, 2) : 0;
    $passFail = exam_pass_fail($exam, $obtained, $total, $pct);

    $res = db()->prepare('SELECT * FROM results WHERE exam_id=? AND student_id=?');
    $res->execute([$sessionExamId, $sessionStudentId]);
    $resultRow = $res->fetch();

    $pageTitle = 'Evaluate Answers';
    $pageSubtitle = $exam['title'] . ' · ' . $student['full_name'];
    $activeNav = 'grading';
    require dirname(__DIR__) . '/includes/header.php';
    ?>
    <div class="toolbar">
        <a class="btn btn-secondary" href="<?= url('/teacher/grading.php') ?>">← Pending queue</a>
        <a class="btn btn-secondary" href="<?= url('/teacher/results.php?exam_id='.$sessionExamId) ?>">Results</a>
    </div>

    <section class="panel" style="margin-bottom:14px">
        <div class="panel-body">
            <div class="chip-row" style="margin-bottom:10px">
                <h2 style="margin:0;flex:1"><?= e($exam['subject_name']) ?> — <?= e($exam['title']) ?></h2>
                <?php if ($exam['mega_title']): ?>
                    <span class="badge badge-danger"><?= e($exam['mega_title']) ?> (<?= e($exam['mega_code']) ?>)</span>
                <?php endif; ?>
                <span class="badge">Total: <?= e((string)$exam['total_marks']) ?> marks</span>
            </div>
            <div class="paper-card-stats">
                <div><span>Department / Batch</span><strong><?= e(($exam['department_name'] ?: '—') . ' / ' . ($exam['batch_name'] ?: '—')) ?></strong></div>
                <div><span>Student</span><strong><?= e($student['full_name']) ?> (<?= e($student['student_id']) ?>)</strong></div>
                <div><span>Semester</span><strong><?= $exam['semester'] !== null ? (int)$exam['semester'] : '—' ?></strong></div>
                <div><span>Submitted</span><strong><?= e(format_datetime($student['submitted_at'])) ?></strong></div>
                <div><span>Attempt</span><strong><?= e($student['attempt_status'] ?: '—') ?></strong></div>
                <div><span>Result row</span><strong><?= e($resultRow['status'] ?? 'pending') ?></strong></div>
            </div>
        </div>
    </section>

    <div class="eval-summary">
        <div>
            <span>Obtained Marks</span>
            <strong><?= e(number_format($obtained, 2)) ?> out of <?= e(number_format($total, 2)) ?></strong>
        </div>
        <div>
            <span>Percentage</span>
            <strong><?= e(number_format($pct, 2)) ?>%</strong>
            <span class="badge <?= $passFail==='PASS'?'badge-success':'badge-danger' ?>"><?= e($passFail) ?></span>
        </div>
        <div>
            <span>Questions</span>
            <strong><?= count($answers) ?> total</strong>
            <small style="opacity:.85"><?= count($mcq) ?> MCQ + <?= count($desc) ?> Descriptive</small>
        </div>
        <div>
            <span>Passing</span>
            <strong><?= e((string)$exam['passing_marks']) ?> marks</strong>
            <small style="opacity:.85"><?= e(get_setting('passing_percentage', '40')) ?>% fallback</small>
        </div>
        <div class="eval-progress">
            <span>Progress: <?= e(number_format($obtained, 2)) ?> / <?= e(number_format($total, 2)) ?></span>
            <div class="progress"><span style="width:<?= min(100, $pct) ?>%;background:<?= $passFail==='PASS'?'#22c55e':'#ef4444' ?>"></span></div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-top:16px">
        <section class="panel">
            <div class="panel-header"><h2>MCQ Section</h2><span class="badge"><?= count($mcq) ?></span></div>
            <div class="panel-body list-feed">
                <?php if (!$mcq): ?><div class="empty-state"><p>No MCQs on this paper.</p></div><?php endif; ?>
                <?php foreach ($mcq as $i => $a): ?>
                    <div class="feed-item" style="flex-direction:column;align-items:stretch;gap:8px">
                        <div class="chip-row">
                            <span class="badge">Q<?= $i + 1 ?></span>
                            <span class="badge <?= !empty($a['is_correct'])?'badge-success':'badge-danger' ?>">
                                <?= !empty($a['is_correct']) ? 'Correct' : 'Incorrect' ?>
                            </span>
                            <span class="badge"><?= e((string)($a['marks_obtained'] ?? 0)) ?> / <?= e((string)$a['max_marks']) ?></span>
                        </div>
                        <strong><?= e($a['question_text']) ?></strong>
                        <span>Answer: <?= e($a['answer_text'] ?: '—') ?> · Key: <?= e($a['correct_answer'] ?: '—') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Descriptive Section</h2><span class="badge badge-warning"><?= count(array_filter($desc, fn($d) => empty($d['is_graded']))) ?> to grade</span></div>
            <div class="panel-body" style="display:grid;gap:14px">
                <?php if (!$desc): ?><div class="empty-state"><p>No descriptive questions.</p></div><?php endif; ?>
                <?php foreach ($desc as $i => $a): ?>
                    <article class="question-card" style="margin:0">
                        <div class="chip-row" style="margin-bottom:8px">
                            <span class="badge">Q<?= $i + 1 ?></span>
                            <span class="badge badge-info">Max <?= e((string)$a['max_marks']) ?></span>
                            <?php if (!empty($a['is_graded'])): ?>
                                <span class="badge badge-success">Graded <?= e((string)$a['marks_obtained']) ?></span>
                            <?php else: ?>
                                <span class="badge badge-warning">Ungraded</span>
                            <?php endif; ?>
                        </div>
                        <h3 style="font-size:1rem"><?= e($a['question_text']) ?></h3>
                        <div style="background:var(--surface-2);border-radius:10px;padding:12px;margin:10px 0;white-space:pre-wrap"><?= e($a['answer_text'] ?: '(No answer)') ?></div>
                        <?php if (!empty($a['id'])): ?>
                        <form method="post" class="form-row" style="align-items:end">
                            <?= csrf_field() ?>
                            <input type="hidden" name="answer_id" value="<?= (int)$a['id'] ?>">
                            <input type="hidden" name="return_exam_id" value="<?= $sessionExamId ?>">
                            <input type="hidden" name="return_student_id" value="<?= $sessionStudentId ?>">
                            <div class="form-group" style="margin:0"><label>Marks</label>
                                <input class="form-control" type="number" step="0.5" min="0" max="<?= e((string)$a['max_marks']) ?>" name="marks_obtained" required value="<?= e((string)($a['marks_obtained'] ?? '')) ?>">
                            </div>
                            <div class="form-group" style="margin:0"><label>Feedback</label>
                                <input class="form-control" name="feedback" value="<?= e($a['feedback'] ?? '') ?>" placeholder="Optional">
                            </div>
                            <button class="btn btn-primary" type="submit">Save grade</button>
                        </form>
                        <?php else: ?>
                            <p class="form-hint">No submitted answer row for this question.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <?php
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// ---- Default: pending descriptive queue + submitted attempts list ----
$pending = db()->prepare(
    "SELECT sa.*, q.question_text, q.marks AS max_marks, e.title AS exam_title, e.id AS exam_id,
            u.full_name AS student_name, st.student_id AS roll, st.id AS student_pk
     FROM student_answers sa
     JOIN questions q ON q.id=sa.question_id
     JOIN exams e ON e.id=sa.exam_id
     JOIN students st ON st.id=sa.student_id
     JOIN users u ON u.id=st.user_id
     JOIN exam_attempts ea ON ea.exam_id=sa.exam_id AND ea.student_id=sa.student_id
     WHERE q.teacher_id=? AND q.question_type='descriptive' AND sa.is_graded=0
       AND ea.status IN ('submitted','auto_submitted')
     ORDER BY sa.submitted_at ASC"
);
$pending->execute([$tid]);
$pending = $pending->fetchAll();

$sessions = db()->prepare(
    "SELECT ea.*, e.title AS exam_title, e.id AS exam_id, u.full_name, st.student_id AS roll, st.id AS student_pk,
            m.title AS mega_title,
            (SELECT COUNT(*) FROM student_answers sa JOIN questions q ON q.id=sa.question_id
              WHERE sa.exam_id=ea.exam_id AND sa.student_id=ea.student_id AND q.question_type='descriptive' AND sa.is_graded=0) AS pending_desc
     FROM exam_attempts ea
     JOIN exams e ON e.id=ea.exam_id
     JOIN students st ON st.id=ea.student_id
     JOIN users u ON u.id=st.user_id
     LEFT JOIN mega_exams m ON m.id=e.mega_exam_id
     WHERE e.teacher_id=? AND ea.status IN ('submitted','auto_submitted')
     ORDER BY ea.submitted_at DESC
     LIMIT 40"
);
$sessions->execute([$tid]);
$sessions = $sessions->fetchAll();

$pageTitle = 'Evaluate Answers';
$pageSubtitle = 'Review submitted papers and grade descriptive answers';
$activeNav = 'grading';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel" style="margin-bottom:16px">
    <div class="panel-header"><h2>Submitted attempts</h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Student</th><th>Paper</th><th>Mega Exam</th><th>Submitted</th><th>Pending desc.</th><th></th></tr></thead>
            <tbody>
            <?php if (!$sessions): ?><tr><td colspan="6"><div class="empty-state"><h3>No submissions yet</h3></div></td></tr><?php endif; ?>
            <?php foreach ($sessions as $s): ?>
                <tr>
                    <td><strong><?= e($s['full_name']) ?></strong><br><small><?= e($s['roll']) ?></small></td>
                    <td><?= e($s['exam_title']) ?></td>
                    <td><?= e($s['mega_title'] ?: '—') ?></td>
                    <td><?= e(format_datetime($s['submitted_at'])) ?></td>
                    <td><?= (int)$s['pending_desc'] ? '<span class="badge badge-warning">'.(int)$s['pending_desc'].'</span>' : '<span class="badge badge-success">0</span>' ?></td>
                    <td><a class="btn btn-sm btn-primary" href="?session_id=<?= (int)$s['exam_id'] ?>&student_id=<?= (int)$s['student_pk'] ?>">Evaluate</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Pending descriptive answers</h2><span class="badge badge-warning"><?= count($pending) ?> awaiting</span></div>
    <div class="panel-body">
        <?php if (!$pending): ?>
            <div class="empty-state"><div class="empty-icon"><?= oems_icon('check') ?></div><h3>All caught up</h3><p>No descriptive answers waiting for review.</p></div>
        <?php else: ?>
            <div class="grid" style="gap:16px">
                <?php foreach ($pending as $p): ?>
                    <article class="question-card">
                        <div class="chip-row" style="margin-bottom:10px">
                            <span class="badge badge-brand"><?= e($p['exam_title']) ?></span>
                            <span class="badge"><?= e($p['student_name']) ?> · <?= e($p['roll']) ?></span>
                            <span class="badge badge-info">Max <?= e((string)$p['max_marks']) ?></span>
                            <a class="btn btn-sm btn-secondary" href="?session_id=<?= (int)$p['exam_id'] ?>&student_id=<?= (int)$p['student_pk'] ?>">Full evaluate</a>
                        </div>
                        <h3><?= e($p['question_text']) ?></h3>
                        <div style="background:var(--surface-2);border-radius:10px;padding:14px;margin-bottom:12px;white-space:pre-wrap"><?= e($p['answer_text'] ?: '(No answer)') ?></div>
                        <form method="post" class="form-row" style="align-items:end">
                            <?= csrf_field() ?>
                            <input type="hidden" name="answer_id" value="<?= (int)$p['id'] ?>">
                            <div class="form-group" style="margin:0"><label>Marks</label><input class="form-control" type="number" step="0.5" min="0" max="<?= e((string)$p['max_marks']) ?>" name="marks_obtained" required></div>
                            <div class="form-group" style="margin:0"><label>Feedback</label><input class="form-control" name="feedback" placeholder="Optional feedback"></div>
                            <button class="btn btn-primary" type="submit">Save grade</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

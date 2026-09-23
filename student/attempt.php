<?php
/**
 * Student — Take paper (password gate, sections, timer, auto-save, final submit)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/exam_helpers.php';
$ctx = require_student();
$user = $ctx['user'];
$student = $ctx['student'];
$sid = (int)$student['id'];
$examId = (int)request('exam_id', 0);
$sectionParam = (int)request('section_id', 0);
$showCompletion = (string)request('show_completion', '') === '1';

$examStmt = db()->prepare(
    "SELECT e.*, s.name AS subject_name, s.code AS subject_code,
            m.title AS mega_title, d.name AS department_name, b.name AS batch_name,
            inst.setting_value AS institution_name
     FROM exams e
     JOIN subjects s ON s.id=e.subject_id
     LEFT JOIN mega_exams m ON m.id=e.mega_exam_id
     LEFT JOIN departments d ON d.id=e.department_id
     LEFT JOIN batches b ON b.id=e.batch_id
     LEFT JOIN system_settings inst ON inst.setting_key='institution_name'
     WHERE e.id=?"
);
$examStmt->execute([$examId]);
$exam = $examStmt->fetch();
if (!$exam || !paper_is_student_visible($exam)) {
    flash('error', 'Paper not available.');
    redirect('/student/exams.php');
}
$win = exam_window_status($exam);
if ($win !== 'available') {
    flash('error', 'This paper is not open right now.');
    redirect('/student/exams.php');
}

$attStmt = db()->prepare('SELECT * FROM exam_attempts WHERE exam_id=? AND student_id=?');
$attStmt->execute([$examId, $sid]);
$attempt = $attStmt->fetch();
$showDoneSummary = (string)request('done', '') === '1';

// After final submit: allow one-time score summary, otherwise bounce to exams list
if ($attempt && in_array($attempt['status'], ['submitted', 'auto_submitted'], true)) {
    if ($showDoneSummary) {
        grade_mcq_answers($examId, $sid);
        upsert_result_from_answers($examId, $sid);
        $summaryAll = db()->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN q.question_type='mcq' THEN eq.marks ELSE 0 END),0) AS mcq_total,
                COALESCE(SUM(CASE WHEN q.question_type='mcq' AND sa.is_graded=1 THEN sa.marks_obtained ELSE 0 END),0) AS mcq_obtained,
                COALESCE(SUM(CASE WHEN q.question_type='mcq' AND sa.is_correct=1 THEN 1 ELSE 0 END),0) AS correct,
                COALESCE(SUM(CASE WHEN q.question_type='mcq' THEN 1 ELSE 0 END),0) AS mcq_q,
                COALESCE(SUM(CASE WHEN q.question_type='descriptive' THEN 1 ELSE 0 END),0) AS desc_q
             FROM exam_questions eq
             JOIN questions q ON q.id=eq.question_id
             LEFT JOIN student_answers sa
               ON sa.exam_id=eq.exam_id AND sa.question_id=eq.question_id AND sa.student_id=?
             WHERE eq.exam_id=?"
        );
        $summaryAll->execute([$sid, $examId]);
        $sum = $summaryAll->fetch() ?: [];
        $mcqCount = (int)($sum['mcq_q'] ?? 0);
        $descCount = (int)($sum['desc_q'] ?? 0);
        $pageTitle = 'Paper submitted';
        $activeNav = 'exams';
        $bodyClass = 'exam-gate';
        require dirname(__DIR__) . '/includes/header.php';
        ?>
        <div class="exam-gate-card panel">
            <div class="exam-gate-header"><h2>Paper submitted successfully</h2></div>
            <div class="exam-gate-body">
                <p>Your answers are locked.</p>
                <?php if ($mcqCount > 0): ?>
                    <p>Here is your objective (MCQ) summary for this paper:</p>
                    <div class="summary-banner">
                        <div><span>MCQ Marks</span><strong><?= e((string)($sum['mcq_obtained'] ?? 0)) ?> / <?= e((string)($sum['mcq_total'] ?? 0)) ?></strong></div>
                        <div><span>Correct</span><strong><?= (int)($sum['correct'] ?? 0) ?></strong></div>
                        <div><span>MCQ Questions</span><strong><?= $mcqCount ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" style="margin-top:12px">
                        This paper has no MCQ questions. No automatic objective score to show.
                    </div>
                <?php endif; ?>
                <?php if ($descCount > 0): ?>
                    <p style="margin-top:14px;color:var(--muted);font-size:.9rem">
                        <?= $descCount ?> descriptive answer(s) will be graded manually by your teacher. Final result appears after publish.
                    </p>
                <?php elseif ($mcqCount > 0): ?>
                    <p style="margin-top:14px;color:var(--muted);font-size:.9rem">Final result appears after your teacher publishes results.</p>
                <?php endif; ?>
                <a class="btn btn-primary" style="width:100%;margin-top:16px;text-align:center" href="<?= url('/student/exams.php') ?>">Back to Available Papers</a>
            </div>
        </div>
        <?php
        require dirname(__DIR__) . '/includes/footer.php';
        exit;
    }
    flash('info', 'You have already submitted this paper.');
    redirect('/student/exams.php');
}

$needsPassword = trim((string)($exam['exam_password'] ?? '')) !== '';
$passwordOk = $attempt && (int)$attempt['password_verified'] === 1;
if (!$needsPassword) {
    $passwordOk = true;
}

// Password gate
if (is_post() && (string)request('action') === 'verify_password') {
    verify_csrf();
    $entered = (string)request('exam_password');
    if (!hash_equals((string)$exam['exam_password'], $entered)) {
        flash('error', 'Incorrect exam password.');
        redirect('/student/attempt.php?exam_id=' . $examId);
    }
    if (!$attempt) {
        $endsAt = date('Y-m-d H:i:s', min(
            time() + ((int)$exam['duration_minutes'] * 60),
            ($exam['availability_mode'] ?? '') === 'always' ? strtotime('+1 year') : strtotime($exam['end_time'])
        ));
        db()->prepare(
            'INSERT INTO exam_attempts (exam_id, student_id, started_at, ends_at, status, password_verified, ip_address) VALUES (?,?,NOW(),?,"in_progress",1,?)'
        )->execute([$examId, $sid, $endsAt, $_SERVER['REMOTE_ADDR'] ?? null]);
        log_activity((int)$user['id'], 'exam_start', "Exam #$examId");
    } else {
        db()->prepare('UPDATE exam_attempts SET password_verified=1 WHERE id=?')->execute([(int)$attempt['id']]);
    }
    redirect('/student/attempt.php?exam_id=' . $examId);
}

if (!$attempt && !$needsPassword) {
    $endsAt = date('Y-m-d H:i:s', min(
        time() + ((int)$exam['duration_minutes'] * 60),
        ($exam['availability_mode'] ?? '') === 'always' ? strtotime('+1 year') : strtotime($exam['end_time'])
    ));
    db()->prepare(
        'INSERT INTO exam_attempts (exam_id, student_id, started_at, ends_at, status, password_verified, ip_address) VALUES (?,?,NOW(),?,"in_progress",1,?)'
    )->execute([$examId, $sid, $endsAt, $_SERVER['REMOTE_ADDR'] ?? null]);
    $attStmt->execute([$examId, $sid]);
    $attempt = $attStmt->fetch();
    log_activity((int)$user['id'], 'exam_start', "Exam #$examId");
    $passwordOk = true;
}

if ($needsPassword && !$passwordOk) {
    $pageTitle = 'Start Exam';
    $pageSubtitle = $exam['title'];
    $activeNav = 'exams';
    $bodyClass = 'exam-gate';
    require dirname(__DIR__) . '/includes/header.php';
    ?>
    <div class="exam-gate-card panel">
        <div class="exam-gate-header">
            <h2><?= e(get_setting('institution_name', APP_FULL_NAME)) ?></h2>
            <p>Online Examination System</p>
        </div>
        <div class="exam-gate-body">
            <div class="paper-card-stats">
                <div><span>Dept</span><strong><?= e($exam['department_name'] ?: '—') ?></strong></div>
                <div><span>Paper</span><strong><?= e($exam['title']) ?></strong></div>
                <div><span>Student</span><strong><?= e($user['full_name']) ?></strong></div>
                <div><span>Mega Exam</span><strong><?= e($exam['mega_title'] ?: '—') ?></strong></div>
                <div><span>Semester</span><strong><?= $exam['semester'] !== null ? (int)$exam['semester'] : '—' ?></strong></div>
                <div><span>Roll No</span><strong><?= e($student['student_id']) ?></strong></div>
                <div><span>Batch</span><strong><?= e($exam['batch_name'] ?: '—') ?></strong></div>
                <div><span>Duration</span><strong><?= (int)$exam['duration_minutes'] ?> minutes</strong></div>
                <div><span>Total Marks</span><strong><?= e((string)$exam['total_marks']) ?></strong></div>
            </div>
            <form method="post" style="margin-top:18px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify_password">
                <div class="form-group">
                    <label>Enter Exam Password</label>
                    <input class="form-control" type="password" name="exam_password" required autofocus>
                </div>
                <button class="btn btn-primary" type="submit" style="width:100%">▶ Start Exam</button>
            </form>
        </div>
    </div>
    <?php
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// Refresh attempt
$attStmt->execute([$examId, $sid]);
$attempt = $attStmt->fetch();
if (!$attempt) {
    flash('error', 'Could not start attempt.');
    redirect('/student/exams.php');
}

$sections = fetch_paper_sections($examId);
if (!$sections) {
    $sections = [['id' => ensure_default_paper_section($examId), 'title' => 'Section A', 'section_code' => 'A', 'section_type' => 'mixed']];
    $sections = fetch_paper_sections($examId);
}

// Section progress map
$progress = [];
$pg = db()->prepare('SELECT section_id, status FROM exam_section_progress WHERE exam_id=? AND student_id=?');
$pg->execute([$examId, $sid]);
foreach ($pg->fetchAll() as $row) {
    $progress[(int)$row['section_id']] = $row['status'];
}

$currentSection = null;
if ($sectionParam) {
    // Locked section: cannot reopen to edit (quiz-app style)
    if (is_section_completed($examId, $sid, $sectionParam)) {
        redirect('/student/attempt.php?exam_id=' . $examId);
    }
    foreach ($sections as $sec) {
        if ((int)$sec['id'] === $sectionParam) {
            $currentSection = $sec;
            break;
        }
    }
}
if (!$currentSection) {
    foreach ($sections as $sec) {
        if (($progress[(int)$sec['id']] ?? 'pending') !== 'completed') {
            $currentSection = $sec;
            break;
        }
    }
}
$allDone = all_sections_completed($examId, $sid);
if (!$currentSection && $allDone) {
    $showCompletion = true;
}

// Auto-submit from timer
if (is_post() && (string)request('auto_submit') === '1') {
    verify_csrf();
    $answers = (array)request('answers', []);
    save_student_answers($examId, $sid, $answers, true);
    grade_mcq_answers($examId, $sid);
    upsert_result_from_answers($examId, $sid);
    db()->prepare('UPDATE exam_attempts SET status="auto_submitted", submitted_at=NOW() WHERE exam_id=? AND student_id=?')
        ->execute([$examId, $sid]);
    redirect('/student/attempt.php?exam_id=' . $examId . '&done=1');
}

// Final submit entire paper
if (is_post() && (string)request('action') === 'final_submit') {
    verify_csrf();
    if (!all_sections_completed($examId, $sid) && fetch_paper_sections($examId)) {
        flash('error', 'Complete all sections before final submit.');
        redirect('/student/attempt.php?exam_id=' . $examId);
    }
    grade_mcq_answers($examId, $sid);
    upsert_result_from_answers($examId, $sid);
    $auto = (string)request('auto_submit', '') === '1';
    $status = $auto ? 'auto_submitted' : 'submitted';
    db()->prepare('UPDATE exam_attempts SET status=?, submitted_at=NOW() WHERE exam_id=? AND student_id=?')
        ->execute([$status, $examId, $sid]);
    log_activity((int)$user['id'], 'exam_submit', "Exam #$examId ($status)");
    redirect('/student/attempt.php?exam_id=' . $examId . '&done=1');
}

// Complete current section (lock — no score reveal)
if (is_post() && (string)request('action') === 'complete_section') {
    verify_csrf();
    $secId = (int)request('section_id');
    if ($secId <= 0) {
        flash('error', 'Invalid section.');
        redirect('/student/attempt.php?exam_id=' . $examId);
    }
    if (is_section_completed($examId, $sid, $secId)) {
        // Already locked — never re-open for edits
        redirect('/student/attempt.php?exam_id=' . $examId . '&show_completion=1&section_id=' . $secId);
    }
    $answers = (array)request('answers', []);
    save_student_answers($examId, $sid, $answers, true);
    // Do NOT grade/reveal here — modern quiz apps show score only after final submit
    mark_section_completed($examId, $sid, $secId);
    db()->prepare('UPDATE exam_attempts SET current_section_id=? WHERE id=?')->execute([$secId, (int)$attempt['id']]);
    redirect('/student/attempt.php?exam_id=' . $examId . '&show_completion=1&section_id=' . $secId);
}

// Save answers while navigating within the ACTIVE section only
if (is_post() && (string)request('action') === 'save_answers') {
    verify_csrf();
    $secId = (int)request('section_id');
    if ($secId && is_section_completed($examId, $sid, $secId)) {
        flash('error', 'This section is locked. You cannot change answers.');
        redirect('/student/attempt.php?exam_id=' . $examId);
    }
    $answers = (array)request('answers', []);
    save_student_answers($examId, $sid, $answers, false);
    $next = (string)request('nav', '');
    $qIndex = (int)request('q_index', 0);
    if ($next === 'next') {
        $qIndex++;
    } elseif ($next === 'prev') {
        $qIndex = max(0, $qIndex - 1);
    }
    redirect('/student/attempt.php?exam_id=' . $examId . '&section_id=' . $secId . '&q=' . $qIndex);
}

// Expired attempt
if (strtotime($attempt['ends_at']) <= time() && $attempt['status'] === 'in_progress') {
    grade_mcq_answers($examId, $sid);
    upsert_result_from_answers($examId, $sid);
    db()->prepare('UPDATE exam_attempts SET status="auto_submitted", submitted_at=NOW() WHERE id=? AND status="in_progress"')
        ->execute([(int)$attempt['id']]);
    flash('info', 'Your exam time had expired and was auto-submitted.');
    redirect('/student/exams.php');
}

// Completion interstitial after section — NO scores (locked only)
if ($showCompletion && $sectionParam) {
    if (!is_section_completed($examId, $sid, $sectionParam)) {
        redirect('/student/attempt.php?exam_id=' . $examId);
    }
    // Refresh progress map
    $progress[(int)$sectionParam] = 'completed';
    $allDone = all_sections_completed($examId, $sid);
    $secTitle = 'Section';
    foreach ($sections as $sec) {
        if ((int)$sec['id'] === $sectionParam) {
            $secTitle = $sec['title'] . ' (' . $sec['section_code'] . ')';
            break;
        }
    }
    $pageTitle = 'Section locked';
    $activeNav = 'exams';
    $bodyClass = 'exam-taking';
    require dirname(__DIR__) . '/includes/header.php';
    ?>
    <div class="exam-gate-card panel">
        <div class="exam-gate-header" style="background:linear-gradient(135deg,#0f766e,#134e4a)">
            <h2>Section completed &amp; locked</h2>
        </div>
        <div class="exam-gate-body">
            <p style="margin-top:0"><strong><?= e($secTitle) ?></strong> is submitted. You cannot go back and change answers in this section.</p>
            <p style="color:var(--muted);font-size:.92rem">Scores are shown only after you final-submit the full paper.</p>
            <?php if ($allDone): ?>
                <form method="post" style="margin-top:20px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="final_submit">
                    <button class="btn btn-primary" type="submit" style="width:100%" onclick="return confirm('Final submit paper? You cannot change any answers after this.')">FINAL SUBMIT PAPER</button>
                </form>
            <?php else: ?>
                <a class="btn btn-primary" style="width:100%;margin-top:20px;text-align:center" href="<?= url('/student/attempt.php?exam_id='.$examId) ?>">Continue to next section →</a>
            <?php endif; ?>
        </div>
    </div>
    <script>
    // Discourage using browser Back to reopen locked section UI
    if (window.history && history.pushState) {
      history.pushState(null, '', location.href);
      window.addEventListener('popstate', function () {
        history.pushState(null, '', location.href);
        window.location.replace(<?= json_encode(url('/student/attempt.php?exam_id=' . $examId)) ?>);
      });
    }
    </script>
    <?php
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

if ($allDone && !$currentSection) {
    $pageTitle = 'Submit paper';
    $activeNav = 'exams';
    $bodyClass = 'exam-taking';
    require dirname(__DIR__) . '/includes/header.php';
    ?>
    <div class="exam-gate-card panel">
        <div class="exam-gate-header"><h2>All sections locked</h2></div>
        <div class="exam-gate-body">
            <p>Every section is complete. You cannot edit previous answers. Submit to finish and see your MCQ score.</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="final_submit">
                <div id="examAttempt" data-exam-id="<?= (int)$examId ?>" data-ends-at="<?= strtotime($attempt['ends_at']) ?>" hidden></div>
                <button class="btn btn-primary" type="submit" style="width:100%" onclick="return confirm('Final submit paper?')">FINAL SUBMIT PAPER</button>
            </form>
        </div>
    </div>
    <?php
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$secId = (int)$currentSection['id'];
$questions = db()->prepare(
    "SELECT q.*, eq.marks AS exam_marks, eq.sort_order
     FROM exam_questions eq
     JOIN questions q ON q.id=eq.question_id
     WHERE eq.exam_id=? AND eq.section_id=?
     ORDER BY eq.sort_order, eq.id"
);
$questions->execute([$examId, $secId]);
$questions = $questions->fetchAll();
if (!$questions) {
    // fallback: all questions if section empty
    $questions = db()->prepare(
        "SELECT q.*, eq.marks AS exam_marks, eq.sort_order
         FROM exam_questions eq JOIN questions q ON q.id=eq.question_id
         WHERE eq.exam_id=? ORDER BY eq.sort_order, eq.id"
    );
    $questions->execute([$examId]);
    $questions = $questions->fetchAll();
}

$saved = [];
$savedStmt = db()->prepare('SELECT question_id, answer_text FROM student_answers WHERE exam_id=? AND student_id=?');
$savedStmt->execute([$examId, $sid]);
foreach ($savedStmt->fetchAll() as $row) {
    $saved[(int)$row['question_id']] = $row['answer_text'];
}

$qIndex = max(0, min((int)request('q', 0), max(0, count($questions) - 1)));
$q = $questions[$qIndex] ?? null;

$pageTitle = $exam['title'];
$pageSubtitle = 'Section ' . ($currentSection['section_code'] ?? '') . ' · ' . (int)$exam['duration_minutes'] . ' min';
$activeNav = 'exams';
$bodyClass = 'exam-taking';
require dirname(__DIR__) . '/includes/header.php';
?>

<div id="examAttempt" data-exam-id="<?= (int)$examId ?>" data-ends-at="<?= strtotime($attempt['ends_at']) ?>" data-lockdown="1">
    <div class="exam-lock-banner" id="examLockBanner">
        <span data-lock-msg>Stay in fullscreen. Leaving the exam tab is monitored.</span>
        <span>Warnings: <strong id="examBlurCount">0</strong></span>
    </div>
    <div class="exam-lock-overlay" id="examLockOverlay">
        <div class="panel">
            <h3 style="margin-top:0">Exam security</h3>
            <p>Please return to fullscreen and keep this tab focused until you submit.</p>
            <button type="button" class="btn btn-primary" id="examResumeFs">Re-enter fullscreen</button>
        </div>
    </div>
    <div class="exam-topbar">
        <div>
            <strong><?= e(get_setting('institution_name', APP_FULL_NAME)) ?></strong>
            <div class="exam-topbar-meta">
                <span>Department: <?= e($exam['department_name'] ?: '—') ?></span>
                <span>Student: <?= e($user['full_name']) ?></span>
                <span>Paper: <?= e($exam['title']) ?></span>
                <span>Roll No: <?= e($student['student_id']) ?></span>
                <span>Semester: <?= $exam['semester'] !== null ? (int)$exam['semester'] : '—' ?></span>
                <span>Duration: <?= (int)$exam['duration_minutes'] ?> minutes</span>
            </div>
            <span class="badge badge-warning">Current Section: <?= e($currentSection['section_code'] . ' (' . $currentSection['section_type'] . ')') ?></span>
        </div>
        <div class="exam-timer compact">
            <div class="time" id="examCountdown">--:--:--</div>
        </div>
    </div>

    <?php if ($q): $qid = (int)$q['id']; ?>
    <form method="post" id="examForm">
        <?= csrf_field() ?>
        <input type="hidden" name="exam_id" value="<?= (int)$examId ?>">
        <input type="hidden" name="section_id" value="<?= $secId ?>">
        <input type="hidden" name="q_index" value="<?= $qIndex ?>">
        <input type="hidden" name="action" id="formAction" value="save_answers">
        <input type="hidden" name="nav" id="formNav" value="">

        <div class="question-status-bar">
            <span>Question: <?= $qIndex + 1 ?>/<?= count($questions) ?></span>
            <span>Section: <?= e($currentSection['section_code']) ?></span>
        </div>

        <article class="question-card" id="q-<?= $qid ?>">
            <div class="chip-row" style="margin-bottom:8px">
                <span class="badge">Question <?= $qIndex + 1 ?> of <?= count($questions) ?></span>
                <span class="badge badge-brand"><?= e($q['question_type']) ?></span>
                <span class="badge"><?= e((string)$q['exam_marks']) ?> marks</span>
            </div>
            <h3><?= e($q['question_text']) ?></h3>
            <?php if ($q['question_type'] === 'mcq'): ?>
                <div class="option-list">
                    <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $opt => $col):
                        if (empty($q[$col])) {
                            continue;
                        }
                        $checked = (($saved[$qid] ?? '') === $opt) ? 'checked' : '';
                    ?>
                        <label class="<?= $checked ? 'is-selected' : '' ?>">
                            <input type="radio" name="answers[<?= $qid ?>]" value="<?= $opt ?>" <?= $checked ?>>
                            <span><?= e($q[$col]) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <textarea class="form-control" name="answers[<?= $qid ?>]" rows="6" placeholder="Write your answer…"><?= e($saved[$qid] ?? '') ?></textarea>
            <?php endif; ?>

            <?php // Keep other answers in form as hidden so auto-save/section complete retain them ?>
            <?php foreach ($questions as $i => $oq):
                if ($i === $qIndex) {
                    continue;
                }
                $oid = (int)$oq['id'];
                if (!isset($saved[$oid])) {
                    continue;
                }
                ?>
                <input type="hidden" name="answers[<?= $oid ?>]" value="<?= e($saved[$oid]) ?>">
            <?php endforeach; ?>

            <div class="question-nav">
                <button class="btn btn-secondary" type="submit" <?= $qIndex <= 0 ? 'disabled' : '' ?> onclick="document.getElementById('formNav').value='prev'">← Previous</button>
                <?php if ($qIndex < count($questions) - 1): ?>
                    <button class="btn btn-primary" type="submit" onclick="document.getElementById('formNav').value='next'">Next →</button>
                <?php else: ?>
                    <button class="btn btn-primary" type="submit" onclick="document.getElementById('formAction').value='complete_section'; return confirm('Complete this section? You cannot change these answers afterward.');">Complete section</button>
                <?php endif; ?>
            </div>
            <p id="saveStatus" style="margin-top:10px;color:var(--muted);font-size:.85rem">Answers auto-save every few seconds</p>
        </article>
    </form>
    <?php else: ?>
        <div class="panel"><div class="empty-state"><h3>No questions in this section</h3>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="complete_section"><input type="hidden" name="section_id" value="<?= $secId ?>">
                <button class="btn btn-primary" type="submit">Skip / complete section</button>
            </form>
        </div></div>
    <?php endif; ?>
</div>
<script>
document.querySelectorAll('.option-list label').forEach(function(label){
  label.addEventListener('click', function(){
    label.parentElement.querySelectorAll('label').forEach(function(l){ l.classList.remove('is-selected'); });
    label.classList.add('is-selected');
  });
});
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

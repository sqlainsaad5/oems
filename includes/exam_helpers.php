<?php
/**
 * Shared exam grading + paper helpers
 */
declare(strict_types=1);

function grade_mcq_answers(int $examId, int $studentId): void
{
    $rows = db()->prepare(
        "SELECT sa.id, sa.answer_text, q.correct_answer, q.question_type, eq.marks
         FROM student_answers sa
         JOIN questions q ON q.id=sa.question_id
         JOIN exam_questions eq ON eq.exam_id=sa.exam_id AND eq.question_id=sa.question_id
         WHERE sa.exam_id=? AND sa.student_id=?"
    );
    $rows->execute([$examId, $studentId]);
    $upd = db()->prepare(
        'UPDATE student_answers SET is_correct=?, marks_obtained=?, is_graded=?, submitted_at=COALESCE(submitted_at, NOW()) WHERE id=?'
    );
    foreach ($rows->fetchAll() as $row) {
        if ($row['question_type'] === 'mcq') {
            $ans = strtoupper(trim((string)$row['answer_text']));
            $correct = strtoupper(trim((string)$row['correct_answer']));
            $ok = $ans !== '' && $ans === $correct;
            $upd->execute([$ok ? 1 : 0, $ok ? (float)$row['marks'] : 0, 1, $row['id']]);
        } else {
            db()->prepare('UPDATE student_answers SET submitted_at=COALESCE(submitted_at, NOW()) WHERE id=?')
                ->execute([$row['id']]);
        }
    }
}

function upsert_result_from_answers(int $examId, int $studentId): void
{
    $exam = db()->prepare('SELECT total_marks, passing_marks FROM exams WHERE id=?');
    $exam->execute([$examId]);
    $examRow = $exam->fetch() ?: ['total_marks' => 0, 'passing_marks' => 0];
    $total = (float)$examRow['total_marks'];
    $sum = db()->prepare('SELECT COALESCE(SUM(marks_obtained),0) FROM student_answers WHERE exam_id=? AND student_id=? AND is_graded=1');
    $sum->execute([$examId, $studentId]);
    $obtained = (float)$sum->fetchColumn();
    $pct = $total > 0 ? round(($obtained / $total) * 100, 2) : 0;
    db()->prepare(
        'INSERT INTO results (exam_id, student_id, total_marks, obtained_marks, percentage, grade, status)
         VALUES (?,?,?,?,?,?,"pending")
         ON DUPLICATE KEY UPDATE obtained_marks=VALUES(obtained_marks), percentage=VALUES(percentage), grade=VALUES(grade), total_marks=VALUES(total_marks)'
    )->execute([$examId, $studentId, $total, $obtained, $pct, calculate_grade($pct)]);
}

function save_student_answers(int $examId, int $studentId, array $answers, bool $final = false): void
{
    $valid = db()->prepare('SELECT question_id FROM exam_questions WHERE exam_id=?');
    $valid->execute([$examId]);
    $allowed = array_map('intval', $valid->fetchAll(PDO::FETCH_COLUMN));
    $stmt = db()->prepare(
        'INSERT INTO student_answers (exam_id, student_id, question_id, answer_text, auto_saved_at, submitted_at)
         VALUES (?,?,?,?,NOW(),?)
         ON DUPLICATE KEY UPDATE answer_text=VALUES(answer_text), auto_saved_at=NOW(), submitted_at=COALESCE(VALUES(submitted_at), submitted_at)'
    );
    foreach ($answers as $qid => $text) {
        $qid = (int)$qid;
        if (!in_array($qid, $allowed, true)) {
            continue;
        }
        $stmt->execute([
            $examId,
            $studentId,
            $qid,
            is_string($text) ? trim($text) : '',
            $final ? date('Y-m-d H:i:s') : null,
        ]);
    }
}

function mcq_section_summary(int $examId, int $studentId, int $sectionId): array
{
    $st = db()->prepare(
        "SELECT COUNT(*) AS total_q,
                SUM(CASE WHEN q.question_type='mcq' THEN 1 ELSE 0 END) AS mcq_q,
                SUM(CASE WHEN q.question_type='mcq' AND sa.is_correct=1 THEN 1 ELSE 0 END) AS correct,
                COALESCE(SUM(CASE WHEN q.question_type='mcq' THEN eq.marks ELSE 0 END),0) AS mcq_total,
                COALESCE(SUM(CASE WHEN q.question_type='mcq' AND sa.is_graded=1 THEN sa.marks_obtained ELSE 0 END),0) AS mcq_obtained
         FROM exam_questions eq
         JOIN questions q ON q.id=eq.question_id
         LEFT JOIN student_answers sa ON sa.exam_id=eq.exam_id AND sa.question_id=eq.question_id AND sa.student_id=?
         WHERE eq.exam_id=? AND eq.section_id=?"
    );
    $st->execute([$studentId, $examId, $sectionId]);
    $row = $st->fetch() ?: [];
    return [
        'total_q' => (int)($row['total_q'] ?? 0),
        'mcq_q' => (int)($row['mcq_q'] ?? 0),
        'correct' => (int)($row['correct'] ?? 0),
        'mcq_total' => (float)($row['mcq_total'] ?? 0),
        'mcq_obtained' => (float)($row['mcq_obtained'] ?? 0),
    ];
}

function mark_section_completed(int $examId, int $studentId, int $sectionId): void
{
    db()->prepare(
        "INSERT INTO exam_section_progress (exam_id, student_id, section_id, status, started_at, completed_at)
         VALUES (?,?,?,'completed',NOW(),NOW())
         ON DUPLICATE KEY UPDATE status='completed', completed_at=NOW()"
    )->execute([$examId, $studentId, $sectionId]);
}

function all_sections_completed(int $examId, int $studentId): bool
{
    $sections = fetch_paper_sections($examId);
    if (!$sections) {
        return true;
    }
    $st = db()->prepare(
        "SELECT COUNT(*) FROM exam_section_progress
         WHERE exam_id=? AND student_id=? AND status='completed'"
    );
    $st->execute([$examId, $studentId]);
    return (int)$st->fetchColumn() >= count($sections);
}

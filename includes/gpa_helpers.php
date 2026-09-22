<?php
/**
 * SGPA / CGPA helpers (from published exam results + subject credit hours)
 */
declare(strict_types=1);

function grade_to_points(string $grade): float
{
    return match (strtoupper(trim($grade))) {
        'A+' => 4.00,
        'A'  => 4.00,
        'A-' => 3.70,
        'B+' => 3.30,
        'B'  => 3.00,
        'B-' => 2.70,
        'C+' => 2.30,
        'C'  => 2.00,
        'C-' => 1.70,
        'D'  => 1.00,
        'F'  => 0.00,
        default => 0.00,
    };
}

/**
 * Build course rows for a student from published results.
 * If multiple papers exist for the same subject+semester, keep the latest published.
 *
 * @return array{courses: list<array>, semesters: array<int,array>, cgpa: float|null, total_credits: float, quality_points: float}
 */
function compute_student_gpa(int $studentId): array
{
    $st = db()->prepare(
        "SELECT r.id AS result_id, r.obtained_marks, r.total_marks, r.percentage, r.grade, r.published_at,
                e.id AS exam_id, e.title AS paper_title, e.semester AS exam_semester,
                s.id AS subject_id, s.name AS subject_name, s.code AS subject_code,
                COALESCE(s.credit_hours, 3) AS credit_hours,
                st.semester AS student_semester
         FROM results r
         JOIN exams e ON e.id = r.exam_id
         JOIN subjects s ON s.id = e.subject_id
         JOIN students st ON st.id = r.student_id
         WHERE r.student_id = ? AND r.status = 'published'
         ORDER BY COALESCE(e.semester, st.semester, 1) ASC, s.code ASC, r.published_at DESC, r.id DESC"
    );
    $st->execute([$studentId]);
    $rows = $st->fetchAll();

    $seen = [];
    $courses = [];
    foreach ($rows as $row) {
        $sem = (int)($row['exam_semester'] ?: $row['student_semester'] ?: 1);
        $subjectId = (int)$row['subject_id'];
        $key = $sem . ':' . $subjectId;
        if (isset($seen[$key])) {
            continue; // already kept latest
        }
        $seen[$key] = true;
        $credits = max(0.5, (float)$row['credit_hours']);
        $grade = (string)($row['grade'] ?: calculate_grade((float)$row['percentage']));
        $gp = grade_to_points($grade);
        $courses[] = [
            'semester' => $sem,
            'subject_id' => $subjectId,
            'subject_code' => $row['subject_code'],
            'subject_name' => $row['subject_name'],
            'paper_title' => $row['paper_title'],
            'exam_id' => (int)$row['exam_id'],
            'percentage' => (float)$row['percentage'],
            'grade' => $grade,
            'grade_points' => $gp,
            'credit_hours' => $credits,
            'quality_points' => round($gp * $credits, 2),
            'published_at' => $row['published_at'],
        ];
    }

    $semesters = [];
    $totalCredits = 0.0;
    $totalQP = 0.0;
    foreach ($courses as $c) {
        $sem = (int)$c['semester'];
        if (!isset($semesters[$sem])) {
            $semesters[$sem] = [
                'semester' => $sem,
                'courses' => [],
                'credits' => 0.0,
                'quality_points' => 0.0,
                'sgpa' => null,
            ];
        }
        $semesters[$sem]['courses'][] = $c;
        $semesters[$sem]['credits'] += $c['credit_hours'];
        $semesters[$sem]['quality_points'] += $c['quality_points'];
        $totalCredits += $c['credit_hours'];
        $totalQP += $c['quality_points'];
    }

    ksort($semesters);
    foreach ($semesters as &$semData) {
        $semData['credits'] = round($semData['credits'], 2);
        $semData['quality_points'] = round($semData['quality_points'], 2);
        $semData['sgpa'] = $semData['credits'] > 0
            ? round($semData['quality_points'] / $semData['credits'], 2)
            : null;
    }
    unset($semData);

    return [
        'courses' => $courses,
        'semesters' => $semesters,
        'cgpa' => $totalCredits > 0 ? round($totalQP / $totalCredits, 2) : null,
        'total_credits' => round($totalCredits, 2),
        'quality_points' => round($totalQP, 2),
    ];
}

function format_gpa(?float $gpa): string
{
    return $gpa === null ? '—' : number_format($gpa, 2);
}

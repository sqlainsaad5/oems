<?php
/**
 * OEMS — Seed Data
 * Default password for all demo users: password123
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

$pdo = db();
$hash = password_hash('password123', PASSWORD_DEFAULT);
$isCli = PHP_SAPI === 'cli';

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ([
        'notifications','results','student_answers','exam_section_progress','exam_attempts','exam_questions',
        'paper_sections','exams','mega_exams','questions','teacher_subjects','students','teachers',
        'subjects','courses','batches','departments','users','activity_logs'
    ] as $t) {
        if ($pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetch()) {
            $pdo->exec("TRUNCATE TABLE `$t`");
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->beginTransaction();

    $pdo->prepare('INSERT INTO users (username, email, password, full_name, role, status) VALUES (?,?,?,?,?,?)')
        ->execute(['admin', 'admin@oems.edu', $hash, 'System Administrator', 'admin', 'active']);

    $pdo->exec("INSERT INTO departments (name, code, description) VALUES
        ('Computer Science', 'CS', 'Department of Computer Science'),
        ('Software Engineering', 'SE', 'Department of Software Engineering'),
        ('Business Administration', 'BA', 'Department of Business Administration')");

    $pdo->exec("INSERT INTO batches (name, year, department_id) VALUES
        ('Batch 2024', 2024, 1),
        ('Batch 2023', 2023, 1),
        ('Batch 2024', 2024, 2)");

    $pdo->exec("INSERT INTO courses (name, code, description, duration_years) VALUES
        ('Bachelor of Computer Science', 'BSCS', 'Four-year undergraduate CS program covering software, systems, and AI.', 4),
        ('Bachelor of Business Administration', 'BBA', 'Undergraduate business program with management focus.', 4),
        ('Bachelor of Software Engineering', 'BSSE', 'Software engineering with emphasis on design and quality.', 4)");

    $pdo->exec("INSERT INTO subjects (course_id, name, code, description, credit_hours) VALUES
        (1, 'Web Development', 'CS301', 'Modern full-stack web technologies.', 3),
        (1, 'Database Systems', 'CS302', 'Relational databases and SQL.', 3),
        (1, 'Data Structures', 'CS201', 'Algorithms and data structures.', 3),
        (1, 'Software Engineering', 'CS401', 'SDLC, UML, and project management.', 3),
        (3, 'Object-Oriented Programming', 'SE201', 'OOP concepts with Java/PHP.', 3),
        (2, 'Principles of Management', 'BA101', 'Foundations of management.', 3)");

    $pdo->prepare(
        'INSERT INTO mega_exams (title, code, description, status, created_by) VALUES (?,?,?,?,?)'
    )->execute(['Mid term', 'mt2025', 'Mid-term examination series 2025', 'active', 1]);
    $pdo->prepare(
        'INSERT INTO mega_exams (title, code, description, status, created_by) VALUES (?,?,?,?,?)'
    )->execute(['Final Exam', 'fn2025', 'Final examination series 2025', 'active', 1]);

    $teachers = [
        ['teacher1', 'sara.khan@oems.edu', 'Dr. Sara Khan', 'EMP-T001', 'Computer Science', 'PhD Computer Science'],
        ['teacher2', 'ali.raza@oems.edu', 'Prof. Ali Raza', 'EMP-T002', 'Software Engineering', 'MS Software Engineering'],
    ];
    $teacherStmt = $pdo->prepare('INSERT INTO users (username, email, password, full_name, role) VALUES (?,?,?,?,?)');
    $tpStmt = $pdo->prepare('INSERT INTO teachers (user_id, employee_id, department, phone, qualification, hire_date) VALUES (?,?,?,?,?,?)');
    foreach ($teachers as $i => $t) {
        $teacherStmt->execute([$t[0], $t[1], $hash, $t[2], 'teacher']);
        $uid = (int)$pdo->lastInsertId();
        $tpStmt->execute([$uid, $t[3], $t[4], '0300' . (1000000 + $i), $t[5], '2022-01-15']);
    }

    $pdo->exec('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (1,1),(1,2),(1,3),(2,4),(2,5)');

    $students = [
        ['student1', 'ahmed@oems.edu', 'Ahmed Hassan', 'STU-2024-001', 1, 1, 1, 2024, 4],
        ['student2', 'fatima@oems.edu', 'Fatima Noor', 'STU-2024-002', 1, 1, 1, 2024, 4],
        ['student3', 'usman@oems.edu', 'Usman Ali', 'STU-2024-003', 1, 1, 2, 2023, 5],
        ['student4', 'aisha@oems.edu', 'Aisha Malik', 'STU-2023-004', 3, 2, 3, 2023, 4],
        ['student5', 'bilal@oems.edu', 'Bilal Ahmed', 'STU-2024-005', 2, 3, null, 2024, 3],
    ];
    $sUser = $pdo->prepare('INSERT INTO users (username, email, password, full_name, role) VALUES (?,?,?,?,?)');
    $sProf = $pdo->prepare(
        'INSERT INTO students (user_id, course_id, department_id, batch_id, student_id, enrollment_year, semester, phone) VALUES (?,?,?,?,?,?,?,?)'
    );
    foreach ($students as $i => $s) {
        $sUser->execute([$s[0], $s[1], $hash, $s[2], 'student']);
        $uid = (int)$pdo->lastInsertId();
        $sProf->execute([$uid, $s[4], $s[5], $s[6], $s[3], $s[7], $s[8], '0311' . (2000000 + $i)]);
    }

    $q = $pdo->prepare('INSERT INTO questions (subject_id, teacher_id, question_text, question_type, difficulty, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $mcqs = [
        [1, 1, 'Which HTML tag is used to define an internal stylesheet?', 'mcq', 'easy', '<style>', '<css>', '<script>', '<link>', 'A', 1],
        [1, 1, 'What does CSS stand for?', 'mcq', 'easy', 'Computer Style Sheets', 'Cascading Style Sheets', 'Creative Style System', 'Colorful Style Sheets', 'B', 1],
        [1, 1, 'Which HTTP method is typically used to submit form data?', 'mcq', 'medium', 'GET', 'POST', 'PUT', 'HEAD', 'B', 2],
        [1, 1, 'In JavaScript, which keyword declares a block-scoped variable?', 'mcq', 'medium', 'var', 'let', 'define', 'static', 'B', 2],
        [1, 1, 'Which status code means “Not Found”?', 'mcq', 'easy', '200', '301', '404', '500', 'C', 1],
        [1, 1, 'What is the purpose of localStorage in browsers?', 'mcq', 'medium', 'Server-side caching', 'Persistent client-side key-value storage', 'Database indexing', 'CSS animation', 'B', 2],
        [1, 1, 'Which SQL clause filters groups?', 'mcq', 'hard', 'WHERE', 'HAVING', 'ORDER BY', 'LIMIT', 'B', 3],
        [2, 1, 'Which normal form removes transitive dependencies?', 'mcq', 'medium', '1NF', '2NF', '3NF', 'BCNF', 'C', 2],
        [2, 1, 'What does ACID stand for in databases?', 'mcq', 'hard', 'Atomicity, Consistency, Isolation, Durability', 'Access, Control, Integrity, Data', 'Array, Column, Index, Database', 'None of the above', 'A', 3],
        [2, 1, 'Which JOIN returns only matching rows from both tables?', 'mcq', 'easy', 'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'FULL JOIN', 'C', 1],
    ];
    foreach ($mcqs as $row) {
        $q->execute($row);
    }
    $q->execute([1, 1, 'Explain the difference between client-side and server-side rendering with examples.', 'descriptive', 'hard', null, null, null, null, null, 5]);
    $q->execute([1, 1, 'Describe how you would design a secure login system for a web application.', 'descriptive', 'medium', null, null, null, null, null, 5]);
    $q->execute([2, 1, 'Write SQL to find students who scored above average in a given exam.', 'descriptive', 'hard', null, null, null, null, null, 5]);

    $start = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $end   = date('Y-m-d H:i:s', strtotime('+7 days'));
    $pdo->prepare(
        'INSERT INTO exams (teacher_id, subject_id, mega_exam_id, department_id, batch_id, semester, paper_code, title, description, duration_minutes, total_marks, passing_marks, start_time, end_time, availability_mode, exam_password, selection_mode, status, approval_status, approved_by, approved_at, instructions)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        1, 1, 1, 1, 1, 4, 'WD1',
        'Web Development',
        'Covers HTML, CSS, JavaScript fundamentals.',
        45, 20, 8,
        $start, $end, 'scheduled', 'exam123', 'manual', 'scheduled', 'approved', 1, date('Y-m-d H:i:s'),
        "1. Do not refresh the page during the exam.\n2. Answers are auto-saved every few seconds.\n3. The exam will auto-submit when time expires."
    ]);

    $pdo->prepare(
        "INSERT INTO paper_sections (exam_id, title, section_code, section_type, sort_order) VALUES
         (1, 'Section A (MCQ)', 'A', 'mcq', 1),
         (1, 'Section B (Descriptive)', 'B', 'descriptive', 2)"
    )->execute();

    $eq = $pdo->prepare('INSERT INTO exam_questions (exam_id, question_id, section_id, marks, sort_order) VALUES (?,?,?,?,?)');
    $order = 1;
    foreach ([1, 2, 3, 4, 5, 6] as $qid) {
        $marks = in_array($qid, [3, 4, 6], true) ? 2 : 1;
        $eq->execute([1, $qid, 1, $marks, $order++]);
    }
    $eq->execute([1, 11, 2, 5, 1]);
    $eq->execute([1, 12, 2, 5, 2]);
    $pdo->exec('UPDATE exams SET total_marks = (SELECT COALESCE(SUM(marks),0) FROM exam_questions WHERE exam_id = 1) WHERE id = 1');

    // Always-available approved paper
    $pdo->prepare(
        'INSERT INTO exams (teacher_id, subject_id, mega_exam_id, department_id, batch_id, semester, paper_code, title, description, duration_minutes, total_marks, passing_marks, start_time, end_time, availability_mode, exam_password, selection_mode, status, approval_status, approved_by, approved_at, instructions)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        1, 2, 1, 1, 1, 4, 'DB1',
        'Database Systems',
        'Short paper on normalization and SQL.',
        20, 10, 4,
        $start, $end, 'always', null, 'manual', 'scheduled', 'approved', 1, date('Y-m-d H:i:s'),
        'Answer all questions carefully.'
    ]);
    $pdo->prepare(
        "INSERT INTO paper_sections (exam_id, title, section_code, section_type, sort_order) VALUES (2, 'Section A', 'A', 'mixed', 1)"
    )->execute();
    foreach ([8, 9, 10] as $i => $qid) {
        $eq->execute([2, $qid, 3, $qid === 9 ? 3 : ($qid === 8 ? 2 : 1), $i + 1]);
    }
    $pdo->exec('UPDATE exams SET total_marks = (SELECT COALESCE(SUM(marks),0) FROM exam_questions WHERE exam_id = 2) WHERE id = 2');

    // Pending approval sample
    $pdo->prepare(
        'INSERT INTO exams (teacher_id, subject_id, mega_exam_id, department_id, batch_id, semester, paper_code, title, description, duration_minutes, total_marks, passing_marks, start_time, end_time, availability_mode, selection_mode, status, approval_status, instructions)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        1, 1, 1, 1, 1, 4, 'WD2',
        'Web Dev Practice',
        'Pending admin approval demo paper.',
        30, 0, 0,
        $start, $end, 'always', 'manual', 'draft', 'pending',
        'Demo pending paper.'
    ]);
    $pdo->prepare(
        "INSERT INTO paper_sections (exam_id, title, section_code, section_type, sort_order) VALUES (3, 'Section A', 'A', 'mcq', 1)"
    )->execute();
    $eq->execute([3, 1, 4, 1, 1]);
    $eq->execute([3, 2, 4, 1, 2]);
    $pdo->exec('UPDATE exams SET total_marks = (SELECT COALESCE(SUM(marks),0) FROM exam_questions WHERE exam_id = 3) WHERE id = 3');

    $n = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)');
    $studentUsers = $pdo->query("SELECT id FROM users WHERE role='student'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($studentUsers as $uid) {
        $n->execute([(int)$uid, 'Available: Web Development (Mid term)', 'An approved paper is available. Open Available Papers to start.', 'exam', '/student/exams.php']);
        $n->execute([(int)$uid, 'Welcome to OEMS', 'Your student account is ready. Update your profile and explore available papers.', 'system', '/student/profile.php']);
    }

    $pdo->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)')
        ->execute([1, 'seed_database', 'Demo data seeded with mega exams and paper approvals', '127.0.0.1']);

    $pdo->commit();
    echo "Seed completed successfully.\n";
    echo "Demo logins (password: password123):\n";
    echo "  admin / admin@oems.edu\n";
    echo "  teacher1 / sara.khan@oems.edu\n";
    echo "  student1 / ahmed@oems.edu\n";
    echo "Exam password for Web Development paper: exam123\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = 'Seed failed: ' . $e->getMessage();
    if ($isCli) {
        fwrite(defined('STDERR') ? STDERR : STDOUT, $message . PHP_EOL);
        exit(1);
    }
    throw new RuntimeException($message, 0, $e);
}

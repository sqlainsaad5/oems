<?php
/**
 * Incremental migration: Mega Exam / Paper Approval / Departments / Batches / Sections
 * Safe to re-run — skips existing columns/tables.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

$pdo = db();
$isCli = PHP_SAPI === 'cli';

function mig_out(string $msg, bool $cli): void
{
    echo ($cli ? $msg . PHP_EOL : '<p>' . htmlspecialchars($msg) . '</p>');
}

function table_exists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

function exec_safe(PDO $pdo, string $sql, string $label, bool $cli): void
{
    try {
        $pdo->exec($sql);
        mig_out("OK: {$label}", $cli);
    } catch (Throwable $e) {
        mig_out("SKIP/WARN {$label}: " . $e->getMessage(), $cli);
    }
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>OEMS Migrate v2</title>
    <style>body{font-family:system-ui;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.5}</style></head><body><h1>OEMS Migration v2</h1>';
}

try {
    if (!table_exists($pdo, 'departments')) {
        exec_safe($pdo, "CREATE TABLE departments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            code VARCHAR(30) NOT NULL UNIQUE,
            description TEXT,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB", 'create departments', $isCli);
    } else {
        mig_out('OK: departments exists', $isCli);
    }

    if (!table_exists($pdo, 'batches')) {
        exec_safe($pdo, "CREATE TABLE batches (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            year YEAR NOT NULL,
            department_id INT UNSIGNED DEFAULT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_batches_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
        ) ENGINE=InnoDB", 'create batches', $isCli);
    } else {
        mig_out('OK: batches exists', $isCli);
    }

    if (!table_exists($pdo, 'mega_exams')) {
        exec_safe($pdo, "CREATE TABLE mega_exams (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            code VARCHAR(40) NOT NULL UNIQUE,
            description TEXT,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_mega_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB", 'create mega_exams', $isCli);
    } else {
        mig_out('OK: mega_exams exists', $isCli);
    }

    if (!column_exists($pdo, 'students', 'department_id')) {
        exec_safe($pdo, 'ALTER TABLE students ADD COLUMN department_id INT UNSIGNED DEFAULT NULL AFTER course_id', 'students.department_id', $isCli);
        exec_safe($pdo, 'ALTER TABLE students ADD CONSTRAINT fk_students_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL', 'fk students.department', $isCli);
    }
    if (!column_exists($pdo, 'students', 'batch_id')) {
        exec_safe($pdo, 'ALTER TABLE students ADD COLUMN batch_id INT UNSIGNED DEFAULT NULL AFTER department_id', 'students.batch_id', $isCli);
        exec_safe($pdo, 'ALTER TABLE students ADD CONSTRAINT fk_students_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL', 'fk students.batch', $isCli);
    }

    $examCols = [
        'mega_exam_id' => "ADD COLUMN mega_exam_id INT UNSIGNED DEFAULT NULL AFTER subject_id",
        'department_id' => "ADD COLUMN department_id INT UNSIGNED DEFAULT NULL AFTER mega_exam_id",
        'batch_id' => "ADD COLUMN batch_id INT UNSIGNED DEFAULT NULL AFTER department_id",
        'semester' => "ADD COLUMN semester TINYINT UNSIGNED DEFAULT NULL AFTER batch_id",
        'paper_code' => "ADD COLUMN paper_code VARCHAR(40) DEFAULT NULL AFTER semester",
        'availability_mode' => "ADD COLUMN availability_mode ENUM('scheduled','always') NOT NULL DEFAULT 'scheduled' AFTER end_time",
        'exam_password' => "ADD COLUMN exam_password VARCHAR(100) DEFAULT NULL AFTER availability_mode",
        'approval_status' => "ADD COLUMN approval_status ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft' AFTER status",
        'approved_by' => "ADD COLUMN approved_by INT UNSIGNED DEFAULT NULL AFTER approval_status",
        'approved_at' => "ADD COLUMN approved_at DATETIME DEFAULT NULL AFTER approved_by",
        'rejection_reason' => "ADD COLUMN rejection_reason TEXT AFTER approved_at",
    ];
    foreach ($examCols as $col => $ddl) {
        if (!column_exists($pdo, 'exams', $col)) {
            exec_safe($pdo, "ALTER TABLE exams {$ddl}", "exams.{$col}", $isCli);
        }
    }

    if (!table_exists($pdo, 'paper_sections')) {
        exec_safe($pdo, "CREATE TABLE paper_sections (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            exam_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) NOT NULL,
            section_code VARCHAR(10) NOT NULL DEFAULT 'A',
            section_type ENUM('mcq','descriptive','mixed') NOT NULL DEFAULT 'mixed',
            instructions TEXT,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sections_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
        ) ENGINE=InnoDB", 'create paper_sections', $isCli);
    }

    if (!column_exists($pdo, 'exam_questions', 'section_id')) {
        exec_safe($pdo, 'ALTER TABLE exam_questions ADD COLUMN section_id INT UNSIGNED DEFAULT NULL AFTER question_id', 'exam_questions.section_id', $isCli);
        exec_safe($pdo, 'ALTER TABLE exam_questions ADD CONSTRAINT fk_eq_section FOREIGN KEY (section_id) REFERENCES paper_sections(id) ON DELETE SET NULL', 'fk eq.section', $isCli);
    }

    if (!column_exists($pdo, 'exam_attempts', 'current_section_id')) {
        exec_safe($pdo, 'ALTER TABLE exam_attempts ADD COLUMN current_section_id INT UNSIGNED DEFAULT NULL AFTER status', 'exam_attempts.current_section_id', $isCli);
    }
    if (!column_exists($pdo, 'exam_attempts', 'password_verified')) {
        exec_safe($pdo, 'ALTER TABLE exam_attempts ADD COLUMN password_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER current_section_id', 'exam_attempts.password_verified', $isCli);
    }

    if (!table_exists($pdo, 'exam_section_progress')) {
        exec_safe($pdo, "CREATE TABLE exam_section_progress (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            exam_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            section_id INT UNSIGNED NOT NULL,
            status ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            UNIQUE KEY uq_section_progress (exam_id, student_id, section_id),
            CONSTRAINT fk_esp_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
            CONSTRAINT fk_esp_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            CONSTRAINT fk_esp_section FOREIGN KEY (section_id) REFERENCES paper_sections(id) ON DELETE CASCADE
        ) ENGINE=InnoDB", 'create exam_section_progress', $isCli);
    }

    // Backfill: scheduled exams without approval become approved so existing demos keep working
    exec_safe(
        $pdo,
        "UPDATE exams SET approval_status='approved', approved_at=COALESCE(approved_at, NOW())
         WHERE approval_status='draft' AND status IN ('scheduled','active','completed')",
        'backfill approved exams',
        $isCli
    );

    // Ensure at least one default section per exam that has questions but no sections
    $exams = $pdo->query(
        "SELECT e.id FROM exams e
         WHERE EXISTS (SELECT 1 FROM exam_questions eq WHERE eq.exam_id=e.id)
           AND NOT EXISTS (SELECT 1 FROM paper_sections ps WHERE ps.exam_id=e.id)"
    )->fetchAll(PDO::FETCH_COLUMN);
    $insSec = $pdo->prepare(
        "INSERT INTO paper_sections (exam_id, title, section_code, section_type, sort_order) VALUES (?, 'Section A', 'A', 'mixed', 1)"
    );
    $updEq = $pdo->prepare('UPDATE exam_questions SET section_id=? WHERE exam_id=? AND section_id IS NULL');
    foreach ($exams as $eid) {
        $insSec->execute([(int)$eid]);
        $sid = (int)$pdo->lastInsertId();
        $updEq->execute([$sid, (int)$eid]);
        mig_out("OK: default section for exam #{$eid}", $isCli);
    }

    mig_out('Migration v2 complete.', $isCli);
} catch (Throwable $e) {
    mig_out('ERROR: ' . $e->getMessage(), $isCli);
    if (!$isCli) {
        echo '</body></html>';
    }
    exit(1);
}

if (!$isCli) {
    echo '<p><a href="../auth/login.php">Login</a></p></body></html>';
}

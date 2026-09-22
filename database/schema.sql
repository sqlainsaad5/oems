-- ============================================================
-- Online Examination Management System (OEMS)
-- Database Schema — MySQL 8.0+
-- ============================================================

CREATE DATABASE IF NOT EXISTS oems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oems;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS student_answers;
DROP TABLE IF EXISTS exam_section_progress;
DROP TABLE IF EXISTS exam_attempts;
DROP TABLE IF EXISTS exam_questions;
DROP TABLE IF EXISTS paper_sections;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS mega_exams;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS teacher_subjects;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS batches;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS system_settings;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Users (authentication + RBAC)
-- ------------------------------------------------------------
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    full_name       VARCHAR(120) NOT NULL,
    role            ENUM('admin','teacher','student') NOT NULL,
    avatar          VARCHAR(255) DEFAULT NULL,
    status          ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    last_login      DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Departments
-- ------------------------------------------------------------
CREATE TABLE departments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    code            VARCHAR(30)  NOT NULL UNIQUE,
    description     TEXT,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Batches (e.g. Batch 2024)
-- ------------------------------------------------------------
CREATE TABLE batches (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    year            YEAR NOT NULL,
    department_id   INT UNSIGNED DEFAULT NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_batches_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_batches_year (year)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Mega Exams (Mid term, Final, etc.)
-- ------------------------------------------------------------
CREATE TABLE mega_exams (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(120) NOT NULL,
    code            VARCHAR(40)  NOT NULL UNIQUE,
    description     TEXT,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mega_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Courses
-- ------------------------------------------------------------
CREATE TABLE courses (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    code            VARCHAR(30)  NOT NULL UNIQUE,
    description     TEXT,
    duration_years  TINYINT UNSIGNED DEFAULT 4,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Subjects
-- ------------------------------------------------------------
CREATE TABLE subjects (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       INT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    code            VARCHAR(30)  NOT NULL,
    description     TEXT,
    credit_hours    TINYINT UNSIGNED DEFAULT 3,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subject_code (code),
    CONSTRAINT fk_subjects_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_subjects_course (course_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Teachers profile
-- ------------------------------------------------------------
CREATE TABLE teachers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    employee_id     VARCHAR(40) NOT NULL UNIQUE,
    department      VARCHAR(120) DEFAULT NULL,
    phone           VARCHAR(30)  DEFAULT NULL,
    qualification   VARCHAR(150) DEFAULT NULL,
    hire_date       DATE DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Students profile
-- ------------------------------------------------------------
CREATE TABLE students (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    course_id       INT UNSIGNED DEFAULT NULL,
    department_id   INT UNSIGNED DEFAULT NULL,
    batch_id        INT UNSIGNED DEFAULT NULL,
    student_id      VARCHAR(40) NOT NULL UNIQUE,
    enrollment_year YEAR DEFAULT NULL,
    semester        TINYINT UNSIGNED DEFAULT 1,
    phone           VARCHAR(30)  DEFAULT NULL,
    address         VARCHAR(255) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_students_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    CONSTRAINT fk_students_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_students_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
    INDEX idx_students_course (course_id),
    INDEX idx_students_department (department_id),
    INDEX idx_students_batch (batch_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Teacher–Subject assignment
-- ------------------------------------------------------------
CREATE TABLE teacher_subjects (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id      INT UNSIGNED NOT NULL,
    subject_id      INT UNSIGNED NOT NULL,
    assigned_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teacher_subject (teacher_id, subject_id),
    CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Question Bank
-- ------------------------------------------------------------
CREATE TABLE questions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id      INT UNSIGNED NOT NULL,
    teacher_id      INT UNSIGNED NOT NULL,
    question_text   TEXT NOT NULL,
    question_type   ENUM('mcq','descriptive') NOT NULL DEFAULT 'mcq',
    difficulty      ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
    option_a        VARCHAR(500) DEFAULT NULL,
    option_b        VARCHAR(500) DEFAULT NULL,
    option_c        VARCHAR(500) DEFAULT NULL,
    option_d        VARCHAR(500) DEFAULT NULL,
    correct_answer  VARCHAR(10)  DEFAULT NULL COMMENT 'A/B/C/D for MCQ',
    marks           DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_questions_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_questions_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_questions_subject (subject_id),
    INDEX idx_questions_difficulty (difficulty),
    INDEX idx_questions_type (question_type)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Exams / Papers (under Mega Exam)
-- ------------------------------------------------------------
CREATE TABLE exams (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id      INT UNSIGNED NOT NULL,
    subject_id      INT UNSIGNED NOT NULL,
    mega_exam_id    INT UNSIGNED DEFAULT NULL,
    department_id   INT UNSIGNED DEFAULT NULL,
    batch_id        INT UNSIGNED DEFAULT NULL,
    semester        TINYINT UNSIGNED DEFAULT NULL,
    paper_code      VARCHAR(40) DEFAULT NULL,
    title           VARCHAR(200) NOT NULL,
    description     TEXT,
    duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
    total_marks     DECIMAL(8,2) NOT NULL DEFAULT 0,
    passing_marks   DECIMAL(8,2) NOT NULL DEFAULT 0,
    start_time      DATETIME NOT NULL,
    end_time        DATETIME NOT NULL,
    availability_mode ENUM('scheduled','always') NOT NULL DEFAULT 'scheduled',
    exam_password   VARCHAR(100) DEFAULT NULL,
    selection_mode  ENUM('manual','random') NOT NULL DEFAULT 'manual',
    random_count    INT UNSIGNED DEFAULT NULL,
    instructions    TEXT,
    status          ENUM('draft','scheduled','active','completed','cancelled') NOT NULL DEFAULT 'draft',
    approval_status ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft',
    approved_by     INT UNSIGNED DEFAULT NULL,
    approved_at     DATETIME DEFAULT NULL,
    rejection_reason TEXT,
    results_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_exams_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    CONSTRAINT fk_exams_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_exams_mega FOREIGN KEY (mega_exam_id) REFERENCES mega_exams(id) ON DELETE SET NULL,
    CONSTRAINT fk_exams_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_exams_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_exams_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_exams_status (status),
    INDEX idx_exams_approval (approval_status),
    INDEX idx_exams_schedule (start_time, end_time)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Paper Sections (Section A MCQ, Section B Descriptive, …)
-- ------------------------------------------------------------
CREATE TABLE paper_sections (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(120) NOT NULL,
    section_code    VARCHAR(10)  NOT NULL DEFAULT 'A',
    section_type    ENUM('mcq','descriptive','mixed') NOT NULL DEFAULT 'mixed',
    instructions    TEXT,
    sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sections_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    INDEX idx_sections_exam (exam_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Exam Questions (pivot)
-- ------------------------------------------------------------
CREATE TABLE exam_questions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT UNSIGNED NOT NULL,
    question_id     INT UNSIGNED NOT NULL,
    section_id      INT UNSIGNED DEFAULT NULL,
    marks           DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_exam_question (exam_id, question_id),
    CONSTRAINT fk_eq_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_eq_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_eq_section FOREIGN KEY (section_id) REFERENCES paper_sections(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Student Answers
-- ------------------------------------------------------------
CREATE TABLE student_answers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    question_id     INT UNSIGNED NOT NULL,
    answer_text     TEXT,
    is_correct      TINYINT(1) DEFAULT NULL,
    marks_obtained  DECIMAL(6,2) DEFAULT NULL,
    is_graded       TINYINT(1) NOT NULL DEFAULT 0,
    graded_by       INT UNSIGNED DEFAULT NULL,
    graded_at       DATETIME DEFAULT NULL,
    feedback        TEXT,
    auto_saved_at   DATETIME DEFAULT NULL,
    submitted_at    DATETIME DEFAULT NULL,
    UNIQUE KEY uq_answer (exam_id, student_id, question_id),
    CONSTRAINT fk_sa_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_grader FOREIGN KEY (graded_by) REFERENCES teachers(id) ON DELETE SET NULL,
    INDEX idx_sa_exam_student (exam_id, student_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Exam Attempts (session tracking)
-- ------------------------------------------------------------
CREATE TABLE exam_attempts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    started_at      DATETIME NOT NULL,
    ends_at         DATETIME NOT NULL,
    submitted_at    DATETIME DEFAULT NULL,
    status          ENUM('in_progress','submitted','auto_submitted','expired') NOT NULL DEFAULT 'in_progress',
    current_section_id INT UNSIGNED DEFAULT NULL,
    password_verified TINYINT(1) NOT NULL DEFAULT 0,
    ip_address      VARCHAR(45) DEFAULT NULL,
    UNIQUE KEY uq_attempt (exam_id, student_id),
    CONSTRAINT fk_ea_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_ea_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_ea_section FOREIGN KEY (current_section_id) REFERENCES paper_sections(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Per-section progress within an attempt
-- ------------------------------------------------------------
CREATE TABLE exam_section_progress (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    section_id      INT UNSIGNED NOT NULL,
    status          ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
    started_at      DATETIME DEFAULT NULL,
    completed_at    DATETIME DEFAULT NULL,
    UNIQUE KEY uq_section_progress (exam_id, student_id, section_id),
    CONSTRAINT fk_esp_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_esp_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_esp_section FOREIGN KEY (section_id) REFERENCES paper_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Results
-- ------------------------------------------------------------
CREATE TABLE results (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    total_marks     DECIMAL(8,2) NOT NULL,
    obtained_marks  DECIMAL(8,2) NOT NULL DEFAULT 0,
    percentage      DECIMAL(5,2) NOT NULL DEFAULT 0,
    grade           VARCHAR(5) DEFAULT NULL,
    status          ENUM('pending','published') NOT NULL DEFAULT 'pending',
    published_at    DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_result (exam_id, student_id),
    CONSTRAINT fk_results_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Notifications
-- ------------------------------------------------------------
CREATE TABLE notifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(200) NOT NULL,
    message         TEXT NOT NULL,
    type            ENUM('exam','result','system','alert') NOT NULL DEFAULT 'system',
    link            VARCHAR(255) DEFAULT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user (user_id, is_read)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Activity Logs (admin monitoring)
-- ------------------------------------------------------------
CREATE TABLE activity_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    action          VARCHAR(100) NOT NULL,
    details         TEXT,
    ip_address      VARCHAR(45) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_logs_created (created_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- System Settings
-- ------------------------------------------------------------
CREATE TABLE system_settings (
    setting_key     VARCHAR(100) PRIMARY KEY,
    setting_value   TEXT,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_key, setting_value) VALUES
('app_name', 'OEMS'),
('app_tagline', 'Online Examination Management System'),
('institution_name', 'OEMS Academy'),
('auto_backup_enabled', '1'),
('default_exam_duration', '60'),
('passing_percentage', '40');

# Online Examination Management System (OEMS)

Production-ready web application for conducting and managing online examinations in educational institutions.

Built per the project SRS + Mega Exam / Paper Approval model — **PHP 8**, **MySQL**, **HTML/CSS/JavaScript** — ready for XAMPP/WAMP deployment.

---

## Quick start

1. Place this folder in your web root, e.g. `C:\xampp\htdocs\oems`
2. Start **Apache** and **MySQL** in XAMPP/WAMP
3. Adjust `BASE_URL` in `config/app.php` if your path differs (default `/oems`)
4. Open `http://localhost/oems/install.php` **or** run:
   ```bash
   php install.php
   ```
5. If upgrading an existing install, also open `http://localhost/oems/database/migrate_v2.php`
6. Sign in at `http://localhost/oems/auth/login.php`

### Demo accounts

| Role    | Username   | Password     |
|---------|------------|--------------|
| Admin   | `admin`    | `password123` |
| Teacher | `teacher1` | `password123` |
| Student | `student1` | `password123` |

Demo paper password (Web Development): `exam123`

---

## Implemented features

| Area | Capability |
|------|------------|
| Auth | Login + RBAC (admin / teacher / student) |
| Org | Departments, batches, courses, subjects |
| Mega Exams | Admin creates Mid term / Final containers |
| Papers | Teacher creates papers with sections + questions |
| Approval | Teacher submits → Admin approve/reject |
| Availability | Scheduled window or Always Available |
| Security | Optional exam password gate |
| Attempt | Section-wise navigation, timer, auto-save, final submit |
| Grading | MCQ auto-grade + descriptive manual grade |
| Reports | PDF/Excel + performance stats |
| Notifications | Upcoming papers & published results |

---

## Key portals

- **Admin:** users, departments, batches, mega exams, paper approvals, reports, audit logs, settings
- **Teacher:** question bank, manage papers (sections), submit for approval, evaluate answers, results
- **Student:** available papers, password gate, section attempt, results, profile

---

## Upgrade note

Fresh install uses the full `database/schema.sql`. Existing databases should run `database/migrate_v2.php` once, then optionally re-seed.

---

## License

Academic / educational project use as defined by the submitting institution.

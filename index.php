<?php
/**
 * OEMS — Public Landing
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
start_app_session();
$user = current_user();
if ($user) {
    redirect(role_home($user['role']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_FULL_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,560;9..144,600&family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="landing" data-base="<?= e(rtrim(BASE_URL, '/')) ?>">
    <div class="landing-bg" aria-hidden="true"></div>

    <nav class="landing-nav">
        <a href="<?= url('/') ?>" class="auth-brand" style="text-decoration:none">
            <span class="brand-mark brand-mark-float">O</span>
            <div>
                <strong>OEMS</strong>
                <small>Examination Platform</small>
            </div>
        </a>
        <div class="landing-nav-links">
            <a href="#features">Features</a>
            <a href="#how">How it works</a>
            <a href="#roles">Roles</a>
            <a class="btn btn-secondary btn-sm" href="<?= url('/auth/login.php') ?>">Sign in</a>
        </div>
    </nav>

    <section class="landing-hero">
        <h1 class="landing-brand-hero"><span>OEMS</span></h1>
        <p class="lede">Secure timed exams, intelligent grading, and clear results — one calm workspace for institutions.</p>
        <div class="cta-row">
            <a class="btn btn-primary" href="<?= url('/auth/login.php') ?>">Enter portal</a>
            <a class="btn btn-secondary" href="#features">Explore the platform</a>
        </div>
        <div class="hero-scroll-hint" aria-hidden="true">
            <span></span>
            Scroll
        </div>
    </section>

    <section class="landing-section" id="features">
        <div class="landing-inner reveal">
            <p class="section-eyebrow">Capabilities</p>
            <h2 class="section-title">Everything exams need — nothing they don’t.</h2>
            <p class="section-lead">From question banks to published results, OEMS keeps every step connected and calm.</p>
            <div class="feature-strip">
                <article class="reveal-child" style="--d:0">
                    <div class="feat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <h3>Question banks</h3>
                    <p>Organize by subject and difficulty. Build exams manually or draw randomly from your bank.</p>
                </article>
                <article class="reveal-child" style="--d:1">
                    <div class="feat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <h3>Live exam sessions</h3>
                    <p>Countdown timers, availability windows, auto-save, and automatic submission when time ends.</p>
                </article>
                <article class="reveal-child" style="--d:2">
                    <div class="feat-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                    <h3>Results & reports</h3>
                    <p>Publish grades, notify students instantly, and export performance to Excel or printable PDF.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="landing-section landing-section-alt" id="how">
        <div class="landing-inner reveal">
            <p class="section-eyebrow">Workflow</p>
            <h2 class="section-title">How OEMS runs an exam</h2>
            <p class="section-lead">A clear path from preparation to results — designed for teachers and students alike.</p>
            <ol class="how-steps">
                <li class="reveal-child" style="--d:0">
                    <span class="step-num">01</span>
                    <div>
                        <h3>Prepare</h3>
                        <p>Teachers build the question bank, set marks, and schedule the exam window.</p>
                    </div>
                </li>
                <li class="reveal-child" style="--d:1">
                    <span class="step-num">02</span>
                    <div>
                        <h3>Conduct</h3>
                        <p>Students attempt online with a live timer. Answers auto-save; time-up auto-submits.</p>
                    </div>
                </li>
                <li class="reveal-child" style="--d:2">
                    <span class="step-num">03</span>
                    <div>
                        <h3>Grade & publish</h3>
                        <p>MCQs score instantly. Descriptive answers are reviewed, then results go live with alerts.</p>
                    </div>
                </li>
            </ol>
        </div>
    </section>

    <section class="landing-section" id="roles">
        <div class="landing-inner reveal">
            <p class="section-eyebrow">Built for everyone</p>
            <h2 class="section-title">Three portals. One system.</h2>
            <p class="section-lead">Each role gets exactly the tools they need — nothing more, nothing confusing.</p>
            <div class="roles-grid">
                <article class="role-card reveal-child" style="--d:0">
                    <h3>Administrator</h3>
                    <p>Manage users, courses, subjects, monitor exams, and generate institutional reports.</p>
                    <ul>
                        <li>User & CSV bulk upload</li>
                        <li>Course / subject control</li>
                        <li>Activity monitoring</li>
                    </ul>
                </article>
                <article class="role-card reveal-child" style="--d:1">
                    <h3>Teacher</h3>
                    <p>Own the academic flow — questions, exams, grading, and publishing results.</p>
                    <ul>
                        <li>Question bank CRUD</li>
                        <li>Manual / random papers</li>
                        <li>Descriptive grading</li>
                    </ul>
                </article>
                <article class="role-card reveal-child" style="--d:2">
                    <h3>Student</h3>
                    <p>A focused exam experience with clear schedules, timers, and published grades.</p>
                    <ul>
                        <li>Available exam list</li>
                        <li>Timed attempts</li>
                        <li>Results & profile</li>
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <section class="landing-section landing-section-alt" id="trust">
        <div class="landing-inner reveal">
            <p class="section-eyebrow">Reliability</p>
            <h2 class="section-title">Designed for exam day pressure.</h2>
            <div class="trust-grid">
                <div class="trust-item reveal-child" style="--d:0">
                    <strong>Encrypted passwords</strong>
                    <span>Secure hashing and role-based access on every request.</span>
                </div>
                <div class="trust-item reveal-child" style="--d:1">
                    <strong>Auto-save answers</strong>
                    <span>Connectivity dips won’t wipe student progress mid-exam.</span>
                </div>
                <div class="trust-item reveal-child" style="--d:2">
                    <strong>Responsive UI</strong>
                    <span>Works cleanly across desktop, tablet, and phone layouts.</span>
                </div>
                <div class="trust-item reveal-child" style="--d:3">
                    <strong>Instant notifications</strong>
                    <span>Students get alerts for upcoming exams and published results.</span>
                </div>
            </div>
        </div>
    </section>

    <section class="landing-cta reveal" id="start">
        <div class="landing-cta-card">
            <h2>Ready to run your next exam?</h2>
            <p>Sign in to the portal and explore admin, teacher, or student workspaces.</p>
            <a class="btn btn-primary" href="<?= url('/auth/login.php') ?>">Open OEMS portal</a>
        </div>
    </section>

    <footer class="landing-footer">
        <div>
            <strong>OEMS</strong>
            <span>Online Examination Management System</span>
        </div>
        <span>&copy; <?= date('Y') ?> · Academic project platform</span>
    </footer>

    <script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>

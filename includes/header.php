<?php
/**
 * OEMS — App Shell Header (sidebar layout)
 * Expected vars: $pageTitle, $pageSubtitle (optional), $activeNav, $user
 */
declare(strict_types=1);

$user = $user ?? current_user();
$role = $user['role'] ?? 'guest';
$unread = $user ? unread_notifications_count((int)$user['id']) : 0;
$pageTitle = $pageTitle ?? APP_NAME;
$pageSubtitle = $pageSubtitle ?? '';
$activeNav = $activeNav ?? '';
$bodyClass = $bodyClass ?? '';

$nav = match ($role) {
    'admin' => [
        ['id' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin/index.php', 'icon' => 'grid'],
        ['id' => 'students', 'label' => 'Students', 'href' => '/admin/users.php?role=student', 'icon' => 'users'],
        ['id' => 'teachers', 'label' => 'Teachers', 'href' => '/admin/users.php?role=teacher', 'icon' => 'user'],
        ['id' => 'departments', 'label' => 'Departments', 'href' => '/admin/departments.php', 'icon' => 'layers'],
        ['id' => 'batches', 'label' => 'Batches', 'href' => '/admin/batches.php', 'icon' => 'users'],
        ['id' => 'courses', 'label' => 'Courses', 'href' => '/admin/courses.php', 'icon' => 'book'],
        ['id' => 'subjects', 'label' => 'Subjects', 'href' => '/admin/subjects.php', 'icon' => 'layers'],
        ['id' => 'mega_exams', 'label' => 'Mega Exams', 'href' => '/admin/mega_exams.php', 'icon' => 'clipboard'],
        ['id' => 'approvals', 'label' => 'Paper Approvals', 'href' => '/admin/paper_approvals.php', 'icon' => 'check'],
        ['id' => 'exams', 'label' => 'All Papers', 'href' => '/admin/exams.php', 'icon' => 'clipboard'],
        ['id' => 'gpa', 'label' => 'SGPA / CGPA', 'href' => '/admin/gpa.php', 'icon' => 'award'],
        ['id' => 'reports', 'label' => 'Reports', 'href' => '/admin/reports.php', 'icon' => 'chart'],
        ['id' => 'notifications', 'label' => 'Notifications', 'href' => '/admin/notifications.php', 'icon' => 'bell'],
        ['id' => 'activity', 'label' => 'Audit Logs', 'href' => '/admin/activity.php', 'icon' => 'activity'],
        ['id' => 'settings', 'label' => 'Settings', 'href' => '/admin/settings.php', 'icon' => 'settings'],
    ],
    'teacher' => [
        ['id' => 'dashboard', 'label' => 'Dashboard', 'href' => '/teacher/index.php', 'icon' => 'grid'],
        ['id' => 'questions', 'label' => 'Question Bank', 'href' => '/teacher/questions.php', 'icon' => 'help'],
        ['id' => 'exams', 'label' => 'Manage Papers', 'href' => '/teacher/exams.php', 'icon' => 'clipboard'],
        ['id' => 'grading', 'label' => 'Evaluate Answers', 'href' => '/teacher/grading.php', 'icon' => 'edit'],
        ['id' => 'results', 'label' => 'Results', 'href' => '/teacher/results.php', 'icon' => 'award'],
        ['id' => 'performance', 'label' => 'Performance', 'href' => '/teacher/performance.php', 'icon' => 'chart'],
        ['id' => 'notifications', 'label' => 'Notifications', 'href' => '/teacher/notifications.php', 'icon' => 'bell'],
    ],
    'student' => [
        ['id' => 'dashboard', 'label' => 'Dashboard', 'href' => '/student/index.php', 'icon' => 'grid'],
        ['id' => 'exams', 'label' => 'Available Papers', 'href' => '/student/exams.php', 'icon' => 'clipboard'],
        ['id' => 'results', 'label' => 'My Results', 'href' => '/student/results.php', 'icon' => 'award'],
        ['id' => 'gpa', 'label' => 'SGPA / CGPA', 'href' => '/student/gpa.php', 'icon' => 'chart'],
        ['id' => 'transcript', 'label' => 'Transcript', 'href' => '/student/transcript.php', 'icon' => 'book'],
        ['id' => 'notifications', 'label' => 'Notifications', 'href' => '/student/notifications.php', 'icon' => 'bell'],
        ['id' => 'profile', 'label' => 'Profile', 'href' => '/student/profile.php', 'icon' => 'user'],
    ],
    default => [],
};

function oems_icon(string $name): string
{
    $icons = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>',
        'chart' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'award' => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu' => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
    ];
    $path = $icons[$name] ?? $icons['grid'];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Online Examination Management System — secure exams, grading, and results.">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,560;9..144,600&family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23134E4A'/%3E%3Ctext x='16' y='22' text-anchor='middle' font-size='14' font-family='Arial' fill='%23F0FDFA' font-weight='700'%3EO%3C/text%3E%3C/svg%3E">
</head>
<body class="app-body <?= e($bodyClass) ?>" data-base="<?= e(rtrim(BASE_URL, '/')) ?>">
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Primary">
        <div class="sidebar-brand">
            <a href="<?= url(role_home($role)) ?>" class="brand-link">
                <span class="brand-mark" aria-hidden="true">O</span>
                <span class="brand-text">
                    <strong>OEMS</strong>
                    <small><?= e(ucfirst($role)) ?> Portal</small>
                </span>
            </a>
            <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close menu"><?= oems_icon('x') ?></button>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($nav as $item): ?>
                <a class="nav-item <?= $activeNav === $item['id'] ? 'is-active' : '' ?>" href="<?= url($item['href']) ?>">
                    <?= oems_icon($item['icon']) ?>
                    <span><?= e($item['label']) ?></span>
                    <?php if ($item['id'] === 'notifications' && $unread > 0): ?>
                        <span class="nav-badge"><?= $unread > 99 ? '99+' : (int)$unread ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar" aria-hidden="true"><?= e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))) ?></div>
                <div class="user-meta">
                    <strong><?= e($user['full_name'] ?? '') ?></strong>
                    <span><?= e($user['email'] ?? '') ?></span>
                </div>
            </div>
            <a class="nav-item nav-logout" href="<?= url('/auth/logout.php') ?>"><?= oems_icon('logout') ?><span>Sign out</span></a>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
    <div class="app-main">
        <header class="topbar">
            <button type="button" class="icon-btn mobile-only" id="sidebarOpen" aria-label="Open menu"><?= oems_icon('menu') ?></button>
            <div class="topbar-title">
                <h1><?= e($pageTitle) ?></h1>
                <?php if ($pageSubtitle): ?>
                    <p><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="topbar-actions">
                <a class="icon-btn" href="<?= url('/' . $role . '/notifications.php') ?>" aria-label="Notifications" title="Notifications">
                    <?= oems_icon('bell') ?>
                    <?php if ($unread > 0): ?><span class="dot-badge"></span><?php endif; ?>
                </a>
            </div>
        </header>
        <main class="page-content">
            <?php
            $success = flash('success');
            $error = flash('error');
            $info = flash('info');
            if ($success || $error || $info):
            ?>
            <div class="toast-stack" role="status">
                <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                <?php if ($info): ?><div class="alert alert-info"><?= e($info) ?></div><?php endif; ?>
            </div>
            <?php endif; ?>

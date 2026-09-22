<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

if (is_post()) {
    verify_csrf();
    if (request('action') === 'broadcast') {
        $title = trim((string)request('title'));
        $message = trim((string)request('message'));
        $role = (string)request('audience', 'student');
        $type = (string)request('type', 'system');
        if ($title && $message) {
            if ($role === 'all') {
                foreach (['admin','teacher','student'] as $r) {
                    notify_role_users($r, $title, $message, $type);
                }
            } else {
                notify_role_users($role, $title, $message, $type);
            }
            flash('success', 'Notification broadcast sent.');
            log_activity((int)$user['id'], 'broadcast', $title);
        } else {
            flash('error', 'Title and message are required.');
        }
    }
    if (request('action') === 'mark_read') {
        db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([(int)$user['id']]);
        flash('success', 'All notifications marked as read.');
    }
    redirect('/admin/notifications.php');
}

$mine = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
$mine->execute([(int)$user['id']]);
$mine = $mine->fetchAll();

$pageTitle = 'Notifications';
$pageSubtitle = 'Broadcast alerts and review your inbox';
$activeNav = 'notifications';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="grid grid-2">
    <section class="panel">
        <div class="panel-header"><h2>Broadcast</h2></div>
        <form method="post" class="panel-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="broadcast">
            <div class="form-group"><label>Title</label><input class="form-control" name="title" required></div>
            <div class="form-group"><label>Message</label><textarea class="form-control" name="message" required></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Audience</label>
                    <select class="form-select" name="audience">
                        <option value="student">Students</option>
                        <option value="teacher">Teachers</option>
                        <option value="admin">Admins</option>
                        <option value="all">Everyone</option>
                    </select>
                </div>
                <div class="form-group"><label>Type</label>
                    <select class="form-select" name="type">
                        <option value="system">System</option>
                        <option value="exam">Exam</option>
                        <option value="result">Result</option>
                        <option value="alert">Alert</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Send notification</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-header">
            <h2>Your inbox</h2>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_read"><button class="btn btn-sm btn-secondary" type="submit">Mark all read</button></form>
        </div>
        <div class="panel-body list-feed">
            <?php if (!$mine): ?><div class="empty-state"><h3>Inbox empty</h3></div><?php endif; ?>
            <?php foreach ($mine as $n): ?>
                <div class="feed-item <?= $n['is_read'] ? '' : 'is-unread' ?>">
                    <div>
                        <strong><?= e($n['title']) ?></strong>
                        <span><?= e(format_datetime($n['created_at'])) ?> · <?= e($n['type']) ?></span>
                        <p style="margin:6px 0 0;font-size:.9rem"><?= e($n['message']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

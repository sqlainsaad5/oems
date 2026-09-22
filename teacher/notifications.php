<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_teacher();
$user = $ctx['user'];

if (is_post() && request('action') === 'mark_read') {
    verify_csrf();
    db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([(int)$user['id']]);
    redirect('/teacher/notifications.php');
}

$mine = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
$mine->execute([(int)$user['id']]);
$mine = $mine->fetchAll();

$pageTitle = 'Notifications';
$activeNav = 'notifications';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <h2>Inbox</h2>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_read"><button class="btn btn-sm btn-secondary">Mark all read</button></form>
    </div>
    <div class="panel-body list-feed">
        <?php if (!$mine): ?><div class="empty-state"><h3>No notifications</h3></div><?php endif; ?>
        <?php foreach ($mine as $n): ?>
            <div class="feed-item <?= $n['is_read']?'':'is-unread' ?>">
                <div>
                    <strong><?= e($n['title']) ?></strong>
                    <span><?= e(format_datetime($n['created_at'])) ?></span>
                    <p style="margin:6px 0 0"><?= e($n['message']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

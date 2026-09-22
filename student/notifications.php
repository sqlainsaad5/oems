<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$ctx = require_student();
$user = $ctx['user'];

if (is_post() && request('action') === 'mark_read') {
    verify_csrf();
    db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([(int)$user['id']]);
    // also mark individually viewed as read
    redirect('/student/notifications.php');
}

db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0')->execute([(int)$user['id']]);

$mine = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 80');
$mine->execute([(int)$user['id']]);
$mine = $mine->fetchAll();

$pageTitle = 'Notifications';
$pageSubtitle = 'Exam reminders and result alerts';
$activeNav = 'notifications';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-body list-feed">
        <?php if (!$mine): ?>
            <div class="empty-state"><div class="empty-icon"><?= oems_icon('bell') ?></div><h3>You're all caught up</h3><p>Exam and result notifications will appear here.</p></div>
        <?php endif; ?>
        <?php foreach ($mine as $n): ?>
            <div class="feed-item">
                <div style="flex:1">
                    <div class="chip-row" style="margin-bottom:6px">
                        <span class="badge badge-<?= $n['type']==='result'?'accent':($n['type']==='exam'?'brand':'info') ?>"><?= e($n['type']) ?></span>
                        <span style="font-size:.8rem;color:var(--muted)"><?= e(format_datetime($n['created_at'])) ?></span>
                    </div>
                    <strong><?= e($n['title']) ?></strong>
                    <p style="margin:6px 0 0"><?= e($n['message']) ?></p>
                    <?php if (!empty($n['link'])): ?>
                        <p style="margin:8px 0 0"><a href="<?= url($n['link']) ?>">Open →</a></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

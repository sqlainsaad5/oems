<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

$logs = db()->query(
    'SELECT l.*, u.full_name, u.username FROM activity_logs l
     LEFT JOIN users u ON u.id=l.user_id
     ORDER BY l.created_at DESC LIMIT 200'
)->fetchAll();

$pageTitle = 'Activity';
$pageSubtitle = 'Monitor system actions and sign-ins';
$activeNav = 'activity';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e(format_datetime($log['created_at'])) ?></td>
                    <td><?= e($log['full_name'] ?: 'System') ?><?php if ($log['username']): ?><br><small><?= e($log['username']) ?></small><?php endif; ?></td>
                    <td><span class="badge badge-info"><?= e($log['action']) ?></span></td>
                    <td><?= e($log['details'] ?? '') ?></td>
                    <td><?= e($log['ip_address'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

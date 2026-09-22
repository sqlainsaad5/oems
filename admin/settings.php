<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
$user = require_admin();

if (is_post()) {
    verify_csrf();
    $action = (string)request('action');
    if ($action === 'save') {
        $keys = ['institution_name','app_tagline','default_exam_duration','passing_percentage','auto_backup_enabled'];
        $stmt = db()->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach ($keys as $k) {
            $stmt->execute([$k, trim((string)request($k, ''))]);
        }
        flash('success', 'Settings saved.');
        log_activity((int)$user['id'], 'settings_update', 'System settings updated');
    }
    if ($action === 'backup') {
        $file = backup_database();
        if ($file) {
            flash('success', 'Backup created: ' . basename($file));
            log_activity((int)$user['id'], 'backup', basename($file));
        } else {
            flash('error', 'Backup failed.');
        }
    }
    redirect('/admin/settings.php');
}

$settings = [];
foreach (db()->query('SELECT setting_key, setting_value FROM system_settings') as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$backups = [];
if (is_dir(BACKUP_PATH)) {
    $backups = array_values(array_filter(scandir(BACKUP_PATH) ?: [], fn($f) => str_ends_with($f, '.sql')));
    rsort($backups);
}

$pageTitle = 'Settings';
$pageSubtitle = 'Institution preferences and backups';
$activeNav = 'settings';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid grid-2">
    <section class="panel">
        <div class="panel-header"><h2>General</h2></div>
        <form method="post" class="panel-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <div class="form-group"><label>Institution name</label><input class="form-control" name="institution_name" value="<?= e($settings['institution_name'] ?? '') ?>"></div>
            <div class="form-group"><label>Tagline</label><input class="form-control" name="app_tagline" value="<?= e($settings['app_tagline'] ?? '') ?>"></div>
            <div class="form-row">
                <div class="form-group"><label>Default exam duration (min)</label><input class="form-control" type="number" name="default_exam_duration" value="<?= e($settings['default_exam_duration'] ?? '60') ?>"></div>
                <div class="form-group"><label>Passing percentage</label><input class="form-control" type="number" name="passing_percentage" value="<?= e($settings['passing_percentage'] ?? '40') ?>"></div>
            </div>
            <div class="form-group"><label>Auto backup enabled</label>
                <select class="form-select" name="auto_backup_enabled">
                    <option value="1" <?= ($settings['auto_backup_enabled'] ?? '1')==='1'?'selected':'' ?>>Yes</option>
                    <option value="0" <?= ($settings['auto_backup_enabled'] ?? '')==='0'?'selected':'' ?>>No</option>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Save settings</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-header"><h2>Database backup</h2></div>
        <div class="panel-body">
            <p>Create an on-demand SQL backup stored under <code>/backups</code>.</p>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="backup"><button class="btn btn-accent" type="submit">Run backup now</button></form>
            <div class="list-feed" style="margin-top:18px">
                <?php if (!$backups): ?><div class="empty-state"><p>No backups yet.</p></div><?php endif; ?>
                <?php foreach (array_slice($backups, 0, 8) as $b): ?>
                    <div class="feed-item"><strong><?= e($b) ?></strong></div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

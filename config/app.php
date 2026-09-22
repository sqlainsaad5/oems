<?php
/**
 * OEMS — Application Configuration
 */
declare(strict_types=1);

// Prevent direct access noise
if (!defined('OEMS_APP')) {
    define('OEMS_APP', true);
}

// Environment
define('APP_NAME', 'OEMS');
define('APP_FULL_NAME', 'Online Examination Management System');
define('APP_VERSION', '1.0.0');
define('APP_ENV', getenv('OEMS_ENV') ?: 'local');
define('APP_DEBUG', APP_ENV !== 'production');

// Paths
define('BASE_PATH', dirname(__DIR__));

/**
 * Resolve public base URL (e.g. /oems) from env or script location.
 */
function oems_detect_base_url(): string
{
    $env = getenv('OEMS_BASE_URL');
    if ($env !== false && $env !== '') {
        return rtrim(str_replace('\\', '/', $env), '/') ?: '';
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    // Climb out of known app subfolders
    $dir = preg_replace('#/(admin|teacher|student|auth|api|assets)(/.*)?$#', '', $dir) ?? $dir;
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }
    return rtrim($dir, '/');
}

define('BASE_URL', oems_detect_base_url());

// Database
define('DB_HOST', getenv('OEMS_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('OEMS_DB_PORT') ?: '3306');
define('DB_NAME', getenv('OEMS_DB_NAME') ?: 'oems');
define('DB_USER', getenv('OEMS_DB_USER') ?: 'root');
define('DB_PASS', getenv('OEMS_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Security
define('SESSION_NAME', 'oems_session');
define('CSRF_TOKEN_KEY', '_csrf_token');
define('PASSWORD_ALGO', PASSWORD_DEFAULT);
define('SESSION_LIFETIME', 7200); // 2 hours

// Uploads
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('BACKUP_PATH', BASE_PATH . '/backups');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB

// Timezone
date_default_timezone_set(getenv('OEMS_TZ') ?: 'Asia/Karachi');

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

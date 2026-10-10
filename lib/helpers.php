<?php
declare(strict_types=1);

/** Load and cache the app config, ensuring storage directories exist. */
function config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require dirname(__DIR__) . '/config.php';
        foreach (['upload_dir', 'data_dir'] as $key) {
            if (!is_dir($cfg[$key])) {
                mkdir($cfg[$key], 0775, true);
            }
        }
    }
    return $cfg;
}

/** Escape a string for HTML output. */
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function format_size(int $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576)    return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)       return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function flash(string $msg, string $type = 'ok'): void {
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function render_flash(): void {
    foreach ($_SESSION['flash'] ?? [] as $f) {
        echo '<div class="flash flash-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
}

/** Debug info: current data store + upload permission status. */
function debug_info(): array {
    $cfg = config();
    $dir = $cfg['upload_dir'];
    $activeStore = store();
    $info = [
        'Data store'          => $activeStore->describe(),
        'Upload dir writable' => is_dir($dir) && is_writable($dir) ? 'yes' : 'NO',
        'file_uploads'        => ini_get('file_uploads') ? 'On' : 'Off',
        'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
        'post_max_size'       => (string)ini_get('post_max_size'),
    ];

    if (!$activeStore instanceof MySqlStore) {
        $info = [
            'Data store'       => $activeStore->describe(),
            'Upload directory' => $dir,
        ] + $info;
        $info['PHP version'] = PHP_VERSION;
    }

    return $info;
}

/** Small button that expands into the debug panel (no JS needed). */
function render_debug_panel(): void {
    echo '<details class="debug"><summary>⚙ debug</summary><dl>';
    foreach (debug_info() as $k => $v) {
        echo '<div class="debug-row"><dt>' . e($k) . '</dt><dd>' . e($v) . '</dd></div>';
    }
    echo '</dl></details>';
}

function page_head(string $title): void {
    $user = current_user();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Popcorn Silo</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
  <a class="brand" href="index.php"><span class="logo">🍿</span> Popcorn Silo</a>
  <nav class="site-nav">
    <?php if ($user): ?>
      <a href="profile.php">My files</a>
      <a href="settings.php">Settings</a>
      <a href="logout.php">Log out</a>
    <?php else: ?>
      <a href="login.php">Log in</a>
      <a href="register.php">Create account</a>
    <?php endif; ?>
  </nav>
</header>
<main class="wrap">
<?php render_flash(); ?>
<?php
}

function page_foot(): void {
    ?>
</main>
<footer class="site-footer">🍿 <a class="site-link" href="https://github.com/pacificpelican/popcorn-silo">Popcorn Silo</a> · buttery fresh file storage · by <a class="site-link" href="https://altaredwood.work">Daniel McKeown</a><br><a class="site-link" href="privacy.php">Privacy policy</a> · <a class="site-link" href="terms.php">Terms of use</a></footer>
</body>
</html>
<?php
}

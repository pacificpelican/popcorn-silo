<?php
require __DIR__ . '/lib/app.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete_account') {
        $cfg = config();
        $id  = (int)$user['id'];

        // Remove the actual blobs from disk, then the records.
        foreach (store()->listFilesByUser($id) as $f) {
            $path = $cfg['upload_dir'] . '/' . $f['stored_name'];
            if (is_file($path)) {
                unlink($path);
            }
        }
        store()->deleteUser($id);

        // Fresh session so the farewell flash survives the logout.
        $_SESSION = [];
        session_regenerate_id(true);
        flash('Account deleted. The silo remembers nothing.');
        header('Location: index.php');
        exit;
    }
    flash('Unknown action.', 'err');
}

page_head('Settings');
?>
<section class="card">
  <h1>Settings</h1>
  <p class="muted">Signed in as <?= e($user['email']) ?></p>
</section>

<section class="card danger-zone">
  <h2>Delete account</h2>
  <p>Gone means gone: your account, every file record, and every uploaded file. No backups, no undo.</p>
  <form method="post" action="settings.php" onsubmit="return confirm('Really delete your account and all your files? This cannot be undone.');">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete_account">
    <button class="btn btn-danger btn-big" type="submit">Delete my account and all my files</button>
  </form>
</section>
<?php page_foot(); ?>

<?php
require __DIR__ . '/lib/app.php';

$user = require_login();
$files = store()->listFilesByUser((int)$user['id']);

page_head('My files');
?>
<section class="card">
  <h1>My files</h1>
  <p class="muted">Signed in as <?= e($user['email']) ?></p>
  <form class="upload-form" method="post" action="action.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="upload">
    <input type="file" name="file" required>
    <button class="btn" type="submit">Upload</button>
    <span class="muted">max <?= e((string)ini_get('upload_max_filesize')) ?></span>
  </form>
</section>

<section class="card files-card">
  <?php if (!$files): ?>
    <p class="muted">No files yet. The silo is empty — sad, but fixable.</p>
  <?php else: ?>
  <table class="files">
    <thead>
      <tr><th>Name</th><th>Size</th><th>Uploaded</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($files as $f): ?>
      <tr>
        <td><a class="file-link" href="file.php?id=<?= (int)$f['id'] ?>"><?= e($f['original_name']) ?></a></td>
        <td><?= e(format_size((int)$f['size'])) ?></td>
        <td><?= e($f['created_at']) ?></td>
        <td>
          <?php if (!empty($f['shared'])): ?>
            <span class="badge badge-shared">shared</span>
          <?php else: ?>
            <span class="badge badge-private">private</span>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a class="btn btn-small" href="download.php?id=<?= (int)$f['id'] ?>">Download</a>
          <form method="post" action="action.php" class="inline-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <input type="text" name="name" value="<?= e($f['original_name']) ?>" required>
            <button class="btn btn-small btn-ghost" type="submit">Rename</button>
          </form>
          <form method="post" action="action.php" class="inline-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="toggle_share">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="btn btn-small" type="submit"><?= !empty($f['shared']) ? 'Make private' : 'Share' ?></button>
          </form>
          <form method="post" action="action.php" class="inline-form" onsubmit="return confirm('Delete this file for good?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="btn btn-small btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<?php render_debug_panel(); ?>
<?php page_foot(); ?>

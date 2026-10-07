<?php
require __DIR__ . '/lib/app.php';

$id = (int)($_GET['id'] ?? 0);
$file = $id > 0 ? store()->getFile($id) : null;
$user = current_user();

if (!$file || !can_view_file($user, $file)) {
    http_response_code(404);
    page_head('Not found');
    ?>
    <section class="card">
      <h1>File not found</h1>
      <p class="muted">This file is private, or it never existed. Spooky either way.</p>
      <p><a href="index.php">Back to the silo</a></p>
    </section>
    <?php
    page_foot();
    exit;
}

$owner = store()->getUser((int)$file['user_id']);
$isOwner = $user !== null && (int)$user['id'] === (int)$file['user_id'];

page_head((string)$file['original_name']);
?>
<section class="card file-card">
  <h1><span class="kernel">🍿</span> <?= e($file['original_name']) ?></h1>
  <dl class="meta">
    <div class="meta-row"><dt>Size</dt><dd><?= e(format_size((int)$file['size'])) ?></dd></div>
    <div class="meta-row"><dt>Uploaded</dt><dd><?= e($file['created_at']) ?></dd></div>
    <div class="meta-row"><dt>By</dt><dd><?= e($owner['email'] ?? 'unknown') ?></dd></div>
    <div class="meta-row"><dt>Status</dt><dd>
      <?php if (!empty($file['shared'])): ?>
        <span class="badge badge-shared">shared</span>
      <?php else: ?>
        <span class="badge badge-private">private</span>
      <?php endif; ?>
    </dd></div>
  </dl>
  <p>
    <a class="btn" href="download.php?id=<?= (int)$file['id'] ?>">Download</a>
    <?php if ($isOwner): ?>
      <a class="btn btn-ghost" href="profile.php">Manage my files</a>
    <?php endif; ?>
  </p>
</section>
<?php page_foot(); ?>

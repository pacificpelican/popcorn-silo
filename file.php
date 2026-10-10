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
  <button class="btn btn-ghost share-button" type="button" id="share-file" aria-describedby="share-status">📤 Share</button>
  <span class="share-status" id="share-status" aria-live="polite"></span>
  <h1><span class="kernel">🍿</span> <?= e($file['original_name']) ?></h1>
  <dl class="meta">
    <div class="meta-row"><dt>Size</dt><dd><?= e(format_size((int)$file['size'])) ?></dd></div>
    <div class="meta-row"><dt>Uploaded</dt><dd><?= e($file['created_at']) ?></dd></div>
    <div class="meta-row"><dt>By</dt><dd><?= e(public_user_label($owner['email'] ?? null)) ?></dd></div>
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
<script>
document.getElementById('share-file').addEventListener('click', async function () {
  const button = this;
  const status = document.getElementById('share-status');
  const shareData = {
    title: <?= json_encode((string)$file['original_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    url: window.location.href
  };

  if (navigator.share) {
    try {
      await navigator.share(shareData);
      status.textContent = 'Shared.';
    } catch (error) {
      if (error.name !== 'AbortError') {
        status.textContent = 'Unable to open the share sheet.';
      }
    }
    return;
  }

  try {
    await navigator.clipboard.writeText(shareData.url);
    button.textContent = '📤 Copied!';
    status.textContent = 'Permalink copied.';
    window.setTimeout(function () {
      button.textContent = '📤 Share';
    }, 2000);
  } catch (error) {
    status.textContent = 'Unable to copy the permalink.';
  }
});
</script>
<?php page_foot(); ?>

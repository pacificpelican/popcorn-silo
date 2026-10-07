<?php
require __DIR__ . '/lib/app.php';

$recent = store()->mostRecentSharedFile();
$user = current_user();

page_head('Home');
?>
<section class="recent-card">
  <h2>Most recent shared file</h2>
  <?php if ($recent): ?>
    <p class="recent-file">
      <span class="kernel">🍿</span>
      <a class="file-link" href="file.php?id=<?= (int)$recent['id'] ?>"><?= e($recent['original_name']) ?></a>
      <span class="muted"><?= e(format_size((int)$recent['size'])) ?> · <?= e($recent['created_at']) ?></span>
    </p>
  <?php else: ?>
    <p class="muted">Nothing shared yet. Pop something in and flip it to shared.</p>
  <?php endif; ?>
</section>

<section class="hero">
  <h1>Your files, popped &amp; stored.</h1>
  <p>Upload stuff, keep it private, share what you feel like. That&rsquo;s the whole deal.</p>
  <?php if ($user): ?>
    <p><a class="btn" href="profile.php">Go to my files</a></p>
  <?php else: ?>
    <p>
      <a class="btn" href="login.php">Log in</a>
      <a class="btn btn-ghost" href="register.php">Create an account</a>
    </p>
  <?php endif; ?>
</section>

<?php render_debug_panel(); ?>
<?php page_foot(); ?>

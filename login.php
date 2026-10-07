<?php
require __DIR__ . '/lib/app.php';

if (current_user()) {
    header('Location: profile.php');
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $user = store()->findUserByEmail($email);
    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        $errors[] = 'Invalid email or password.';
    } else {
        log_in($user);
        header('Location: profile.php');
        exit;
    }
}

page_head('Log in');
?>
<section class="card auth-card">
  <h1>Log in</h1>
  <?php foreach ($errors as $err): ?>
    <div class="flash flash-err"><?= e($err) ?></div>
  <?php endforeach; ?>
  <form method="post" action="login.php">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Email
      <input type="email" name="email" required value="<?= e($email) ?>">
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button class="btn" type="submit">Log in</button>
  </form>
  <p class="muted">No account? <a href="register.php">Create one</a>.</p>
</section>
<?php page_foot(); ?>

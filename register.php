<?php
require __DIR__ . '/lib/app.php';

if (current_user()) {
    header('Location: profile.php');
    exit;
}

$regCfg       = config()['registration'] ?? [];
$regOpen      = (bool)($regCfg['open'] ?? true);
$inviteCodes  = array_values(array_filter(array_map('strval', (array)($regCfg['invite_codes'] ?? []))));
$inviteNeeded = $regOpen && $inviteCodes !== [];

$errors = [];
$email  = '';
$invite = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');
    $invite   = trim((string)($_POST['invite'] ?? ''));

    if (!$regOpen) {
        $errors[] = 'Signups are closed right now.';
    } elseif ($inviteNeeded && !in_array($invite, $inviteCodes, true)) {
        $errors[] = "That invite code isn't valid.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "That email doesn't look right.";
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password needs at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords don't match.";
    }
    if (!$errors && store()->findUserByEmail($email)) {
        $errors[] = 'That email is already registered.';
    }
    if (!$errors) {
        try {
            $user = store()->createUser($email, password_hash($password, PASSWORD_DEFAULT));
        } catch (Throwable $ex) {
            $errors[] = 'That email is already registered.';
        }
    }
    if (!$errors) {
        log_in($user);
        header('Location: profile.php');
        exit;
    }
}

page_head('Create account');
?>
<section class="card auth-card">
  <h1>Create an account</h1>
  <?php foreach ($errors as $err): ?>
    <div class="flash flash-err"><?= e($err) ?></div>
  <?php endforeach; ?>
  <?php if (!$regOpen): ?>
    <p class="muted">Signups are closed right now — the silo is full. Check back later.</p>
  <?php else: ?>
  <form method="post" action="register.php">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Email
      <input type="email" name="email" required value="<?= e($email) ?>">
    </label>
    <label>Password <span class="muted">(8+ characters)</span>
      <input type="password" name="password" required minlength="8">
    </label>
    <label>Confirm password
      <input type="password" name="confirm" required minlength="8">
    </label>
    <?php if ($inviteNeeded): ?>
    <label>Invite code
      <input type="text" name="invite" required value="<?= e($invite) ?>" autocomplete="off">
    </label>
    <?php endif; ?>
    <button class="btn" type="submit">Create account</button>
  </form>
  <p class="muted">Already have one? <a href="login.php">Log in</a>.</p>
  <?php endif; ?>
</section>
<?php page_foot(); ?>

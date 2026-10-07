<?php
declare(strict_types=1);

session_name('popcornsilo');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return store()->getUser((int)$_SESSION['user_id']);
}

/** Fetch the logged-in user or redirect to the login page. */
function require_login(): array {
    $user = current_user();
    if (!$user) {
        flash('Please log in first.', 'err');
        header('Location: login.php');
        exit;
    }
    return $user;
}

function log_in(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        exit('Bad request.');
    }
}

/** Shared files are public; private files are visible only to their owner. */
function can_view_file(?array $user, array $file): bool {
    return !empty($file['shared'])
        || ($user !== null && (int)$file['user_id'] === (int)$user['id']);
}

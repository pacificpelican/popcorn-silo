<?php
require __DIR__ . '/lib/app.php';

$id = (int)($_GET['id'] ?? 0);
$file = $id > 0 ? store()->getFile($id) : null;
$user = current_user();

if (!$file || !can_view_file($user, $file)) {
    http_response_code(404);
    exit('File not found.');
}

$cfg = config();
$path = $cfg['upload_dir'] . '/' . $file['stored_name'];
if (!is_file($path)) {
    http_response_code(410);
    exit('File is missing from storage.');
}

$safeName = str_replace(['"', '\\', "\r", "\n"], '_', (string)$file['original_name']);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;

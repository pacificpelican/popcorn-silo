<?php
require __DIR__ . '/lib/app.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}
csrf_check();

$action = (string)($_POST['action'] ?? '');
$cfg = config();

/** Load a file row owned by the current user, or bail with a 404. */
$ownFile = function (int $id) use ($user): array {
    $file = store()->getFile($id);
    if (!$file || (int)$file['user_id'] !== (int)$user['id']) {
        http_response_code(404);
        exit('File not found.');
    }
    return $file;
};

/** Basic cleanup for user-supplied file names. */
$cleanName = function (string $name): string {
    $name = basename(trim($name));
    return in_array($name, ['', '.', '..'], true) ? '' : $name;
};

switch ($action) {
    case 'upload':
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            flash('Upload failed — file missing or too large.', 'err');
            break;
        }
        $orig = $cleanName((string)$_FILES['file']['name']);
        if ($orig === '') {
            $orig = 'file';
        }
        $stored = bin2hex(random_bytes(16));
        $dest = $cfg['upload_dir'] . '/' . $stored;
        if (!move_uploaded_file((string)$_FILES['file']['tmp_name'], $dest)) {
            flash('Upload failed — could not store the file. Check upload dir permissions.', 'err');
            break;
        }
        $mime = (string)($_FILES['file']['type'] ?? '');
        store()->createFile((int)$user['id'], $orig, $stored, (int)$_FILES['file']['size'], $mime);
        flash('Uploaded "' . $orig . '".');
        break;

    case 'rename':
        $file = $ownFile((int)($_POST['id'] ?? 0));
        $name = $cleanName((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('Name cannot be empty.', 'err');
            break;
        }
        store()->renameFile((int)$file['id'], $name);
        flash('Renamed to "' . $name . '".');
        break;

    case 'toggle_share':
        $file = $ownFile((int)($_POST['id'] ?? 0));
        $nowShared = empty($file['shared']);
        store()->setShared((int)$file['id'], $nowShared);
        flash($nowShared ? 'File is now shared.' : 'File is now private.');
        break;

    case 'delete':
        $file = $ownFile((int)($_POST['id'] ?? 0));
        $path = $cfg['upload_dir'] . '/' . $file['stored_name'];
        if (is_file($path)) {
            unlink($path);
        }
        store()->deleteFile((int)$file['id']);
        flash('Deleted "' . $file['original_name'] . '".');
        break;

    default:
        flash('Unknown action.', 'err');
}

header('Location: profile.php');
exit;

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../security.php';

require_auth(false);                 // session required
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die(false);       // require the hidden form token
}

if (!isset($_SESSION['id'])) {
    header('location: /login');
    exit;
}

if (empty($_FILES['avatar']['name'])) {
    $_SESSION['message'] = 'No file selected.';
    header('location: /profile/' . $_SESSION['guid']);
    exit;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
finfo_close($finfo);

if (!isset($allowed[$mime])) {
    $_SESSION['message'] = 'Only JPG or PNG allowed.';
    header('location: /profile/' . $_SESSION['guid']);
    exit;
}

if ($_FILES['avatar']['size'] > 1 * 1024 * 1024) {           // 1 MB
    $_SESSION['message'] = 'File too large (max 1 MB).';
    header('location: /profile/' . $_SESSION['guid']);
    exit;
}

$ext      = $allowed[$mime] ?? null;
$filename = bin2hex(random_bytes(12)) . ".$ext";              // уникальное имя
$target   = __DIR__ . '/../../public/uploads/avatars/' . $filename;

if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) {
    $_SESSION['message'] = 'Upload failed.';
    header('location: /profile/' . $_SESSION['guid']);
    exit;
}

/*  ————  сохраним в БД  ———— */
$conn = getConnection();
$sql  = "UPDATE USERS SET AVATAR = :avatar WHERE ID_USER = :id";
$stmt = $conn->prepare($sql);
$stmt->bindValue(':avatar', $filename);
$stmt->bindValue(':id', $_SESSION['id'], PDO::PARAM_INT);
$stmt->execute();
$conn = null;

$_SESSION['message'] = 'Avatar updated!';
header('location: /profile/' . $_SESSION['guid']);

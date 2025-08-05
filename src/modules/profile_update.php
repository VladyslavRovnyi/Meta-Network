<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../security.php';

require_auth(false);                 // session required
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die(false);       // require the hidden form token
}

if (!isset($_SESSION['id'])) { header('location: /login'); exit; }

$id        = (int) $_SESSION['id'];
$guid      = $_SESSION['guid'];
$newName   = trim($_POST['username'] ?? '');
$file      = $_FILES['avatar'] ?? null;

/* ----------  валидация имени  ---------- */
if ($newName === '') {
    $_SESSION['message'] = 'Username required.';
    header("location: /profile/$guid");
    exit;
}
if (!preg_match('/^[A-Za-z0-9_]{3,24}$/', $newName)) {
    $_SESSION['message'] = 'Username must be 3-24 chars, [A-Z a-z 0-9 _].';
    header("location: /profile/$guid");
    exit;
}

/* ----------  валидация аватара (если выбран)  ---------- */
$avatarFilename = null;

if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        $_SESSION['message'] = 'Avatar: only JPG or PNG.';
        header("location: /profile/$guid");
        exit;
    }
    if ($file['size'] > 1 * 1024 * 1024) {              // 1 MB
        $_SESSION['message'] = 'Avatar too large (max 1 MB).';
        header("location: /profile/$guid");
        exit;
    }

    $avatarFilename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $target = __DIR__ . '/../../public/uploads/avatars/' . $avatarFilename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $_SESSION['message'] = 'Avatar upload failed.';
        header("location: /profile/$guid");
        exit;
    }
}

/* ----------  запись в БД  ---------- */
$conn = getConnection();
$conn->beginTransaction();

try {
    // 1) меняем username (если новый)
    $sql = "UPDATE USERS SET USERNAME = :name WHERE ID_USER = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':name' => $newName, ':id' => $id]);

    // 2) меняем аватар (если выбран)
    if ($avatarFilename !== null) {
        $sql = "UPDATE USERS SET AVATAR = :av WHERE ID_USER = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':av' => $avatarFilename, ':id' => $id]);
    }

    $conn->commit();

    /* ----------  обновить сессию  ---------- */
    $_SESSION['user'] = $newName;
    $_SESSION['message'] = 'Profile updated!';

} catch (PDOException $e) {
    $conn->rollBack();
    if ($e->errorInfo[1] === 1062) {            // duplicate entry
        $_SESSION['message'] = 'Username already taken.';
    } else {
        $_SESSION['message'] = 'DB error: ' . $e->getMessage();
    }
}

$conn = null;
header("location: /profile/$guid");

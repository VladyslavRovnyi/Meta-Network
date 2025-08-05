<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../autoload.php';

if (!isset($_POST['quote'])) {
    header('Location: /home');
    exit;
}

$quote = trim($_POST['quote']);
$id = (int)($_SESSION['id'] ?? 0);
$date = date('Y-m-d');
$time = date('H:i:s');

if ($id <= 0 || $quote === '') {
    $_SESSION['message'] = 'Empty post or not logged in.';
    header('Location: /home');
    exit;
}

/* ------- optional image ------- */
$imageFilename = null;
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['message'] = 'Upload failed.';
        header('Location: /home');
        exit;
    }

    // Validate type/size using fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowed[$mime])) {
        $_SESSION['message'] = 'Only JPG or PNG allowed.';
        header('Location: /home');
        exit;
    }
    if ($_FILES['photo']['size'] > 3 * 1024 * 1024) { // 3 MB
        $_SESSION['message'] = 'Image too large (max 3 MB).';
        header('Location: /home');
        exit;
    }

    $ext = $allowed[$mime];
    $imageFilename = bin2hex(random_bytes(12)) . '.' . $ext;
    $target = __DIR__ . '/../../public/uploads/posts/' . $imageFilename;

    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
        $_SESSION['message'] = 'Saving image failed.';
        header('Location: /home');
        exit;
    }
}

/* ------- insert into DB ------- */
$conn = getConnection();
try {
    $sql = "INSERT INTO QUOTES (QUOTE, POST_DATE, POST_TIME, ID_USER, IMAGE)
            VALUES (:quote, :postdate, :posttime, :id, :image)";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':quote', $quote);
    $stmt->bindValue(':postdate', $date);
    $stmt->bindValue(':posttime', $time);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':image', $imageFilename); // can be NULL
    $stmt->execute();

    $_SESSION['message'] = 'Posted!';
    header('Location: /home');
} catch (PDOException $e) {
    $_SESSION['message'] = 'DB error: ' . $e->getMessage();
    header('Location: /home');
} finally {
    $conn = null;
}

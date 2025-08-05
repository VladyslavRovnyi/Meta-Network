<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../security.php';

require_auth(false);                 // session required
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die(false);       // require the hidden form token
}

// kill any buffered output so JSON is clean
while (ob_get_level()) { ob_end_clean(); }
ini_set('display_errors', '0');  // avoid notices in JSON
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$fail = function (int $code, string $msg) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
};

if (!isset($_SESSION['id'])) {
    $fail(401, 'Not authenticated');
}

$quoteId = isset($_POST['quote_id']) ? (int)$_POST['quote_id'] : 0;
$body    = isset($_POST['body']) ? trim($_POST['body']) : '';

if ($quoteId <= 0 || $body === '') { $fail(400, 'Empty comment or bad quote id'); }
if (mb_strlen($body) > 300)        { $fail(400, 'Max 300 chars'); }

$conn = getConnection();
try {
    $stmt = $conn->prepare(
        "INSERT INTO COMMENTS (ID_QUOTE, ID_USER, BODY) VALUES (:qid, :uid, :body)"
    );
    $stmt->bindValue(':qid',  $quoteId, PDO::PARAM_INT);
    $stmt->bindValue(':uid',  (int)$_SESSION['id'], PDO::PARAM_INT);
    $stmt->bindValue(':body', $body);
    $stmt->execute();

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    $fail(500, 'DB error');
} finally {
    $conn = null;
}

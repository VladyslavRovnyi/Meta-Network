<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../autoload.php';

// Ensure nothing was already sent
while (ob_get_level()) { ob_end_clean(); }

ini_set('display_errors', '0');
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

if ($quoteId <= 0 || $body === '') {
    $fail(400, 'Empty comment or bad quote id');
}
if (mb_strlen($body) > 300) {
    $fail(400, 'Max 300 chars');
}

$conn = getConnection();

try {
    $sql = "INSERT INTO COMMENTS (ID_QUOTE, ID_USER, BODY)
            VALUES (:qid, :uid, :body)";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':qid',  $quoteId, PDO::PARAM_INT);
    $stmt->bindValue(':uid',  (int)$_SESSION['id'], PDO::PARAM_INT);
    $stmt->bindValue(':body', $body);
    $stmt->execute();

    http_response_code(200);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    $fail(500, 'DB error');
} finally {
    $conn = null;
}

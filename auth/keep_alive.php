<?php
// auth/keep_alive.php
session_start();

if (isset($_SESSION['status']) && $_SESSION['status'] === 'login') {
    $_SESSION['last_activity'] = time();
    echo json_encode(['status' => 'success', 'message' => 'Session refreshed']);
} else {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
}
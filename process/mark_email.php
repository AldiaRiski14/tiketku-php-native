<?php
/**
 * AJAX endpoint untuk update flag email_ticket_sent / email_warning_sent
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);
$type     = $_POST['type'] ?? 'ticket'; // ticket | warning
$success  = ($_POST['success'] ?? '0') === '1';
$msg      = trim($_POST['msg'] ?? '');

if (!$ticketId) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid ticket']);
    exit;
}

// Pastikan tiket milik user
$stmt = $pdo->prepare("SELECT id, user_id, email_ticket_sent, email_warning_sent FROM tickets WHERE id = ? AND user_id = ?");
$stmt->execute([$ticketId, $_SESSION['user_id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    // Admin juga boleh
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'msg' => 'Ticket not found']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, user_id, email_ticket_sent, email_warning_sent FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        echo json_encode(['ok' => false, 'msg' => 'Ticket not found']);
        exit;
    }
}

$userId = $ticket['user_id'];

// Ambil email user
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userEmail = $stmt->fetchColumn();

if ($type === 'ticket' && $success) {
    $pdo->prepare("UPDATE tickets SET email_ticket_sent = 1 WHERE id = ?")->execute([$ticketId]);
    logEmail($userId, $userEmail, 'ticket', 'Tiket Digital - ' . $ticketId, $ticketId, 'sent', $msg);
} elseif ($type === 'warning' && $success) {
    $pdo->prepare("UPDATE tickets SET email_warning_sent = 1 WHERE id = ?")->execute([$ticketId]);
    logEmail($userId, $userEmail, 'warning', 'Peringatan Keberangkatan', $ticketId, 'sent', $msg);
} else {
    logEmail($userId, $userEmail, $type, 'Failed send', $ticketId, 'failed', $msg);
}

echo json_encode(['ok' => true]);
?>

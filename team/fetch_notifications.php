<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'team') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db.php';

// Fetch notifications for the current user
$notifications_query = "SELECT message FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY id DESC LIMIT 5";
$notifications = $conn->query($notifications_query);

$output = '';
if ($notifications->num_rows > 0) {
    $output .= '<ul class="list-group">';
    while ($notification = $notifications->fetch_assoc()) {
        $output .= '<li class="list-group-item">' . htmlspecialchars($notification['message']) . '</li>';
    }
    $output .= '</ul>';
} else {
    $output .= '<p class="text-muted">No notifications.</p>';
}

echo json_encode(['notifications' => $output]);
?>
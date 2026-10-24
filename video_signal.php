<?php
session_start();
header('Content-Type: application/json');
include("include/connection.php");

if (!isset($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_email = $_SESSION['user_email'];
$user_result = mysqli_query($con, "SELECT user_name FROM users WHERE user_email='" . mysqli_real_escape_string($con, $user_email) . "' LIMIT 1");
$current_user_row = mysqli_fetch_assoc($user_result);
$current_user = $current_user_row['user_name'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'incoming') {
    $stmt = mysqli_prepare(
        $con,
        "SELECT id, sender_username, created_at
         FROM video_signals
         WHERE receiver_username = ?
           AND signal_type = 'offer'
           AND created_at >= (NOW() - INTERVAL 45 SECOND)
         ORDER BY id DESC
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "s", $current_user);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $incoming = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    echo json_encode([
        "incoming" => $incoming ? true : false,
        "call" => $incoming ?: null
    ]);
    exit();
}

$peer = $_POST['peer'] ?? $_GET['peer'] ?? '';
$peer = mysqli_real_escape_string($con, $peer);

if ($peer === '') {
    http_response_code(400);
    echo json_encode(["error" => "Missing peer"]);
    exit();
}

$names = [$current_user, $peer];
sort($names, SORT_STRING);
$room_id = sha1($names[0] . '|' . $names[1]);


if ($action === 'cleanup') {
    $stmt = mysqli_prepare(
        $con,
        "DELETE FROM video_signals WHERE room_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "s", $room_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(["success" => true]);
    exit();
}

if ($action === 'send') {
    $type = $_POST['type'] ?? '';
    $data = $_POST['data'] ?? '';

    if ($type === '' || $data === '') {
        http_response_code(400);
        echo json_encode(["error" => "Missing signal type or data"]);
        exit();
    }

    $stmt = mysqli_prepare($con, "INSERT INTO video_signals (room_id, sender_username, receiver_username, signal_type, signal_data, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "sssss", $room_id, $current_user, $peer, $type, $data);
    mysqli_stmt_execute($stmt);

    echo json_encode(["success" => true]);
    exit();
}

if ($action === 'fetch') {
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

    $stmt = mysqli_prepare($con, "SELECT id, sender_username, signal_type, signal_data FROM video_signals WHERE room_id = ? AND receiver_username = ? AND id > ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, "ssi", $room_id, $current_user, $last_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $signals = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $signals[] = $row;
    }

    echo json_encode(["signals" => $signals]);
    exit();
}

http_response_code(400);
echo json_encode(["error" => "Invalid action"]);

<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Handle GET request to fetch messages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 50) : 50;
    $before = isset($_GET['before']) ? intval($_GET['before']) : null;

    try {
        $query = "
            SELECT 
                m.id,
                m.message,
                m.created_at,
                u.username,
                GROUP_CONCAT(DISTINCT b.icon) as badges
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            LEFT JOIN user_badges ub ON u.id = ub.user_id
            LEFT JOIN badge_types b ON ub.badge_type_id = b.id
        ";

        $params = [];
        if ($before) {
            $query .= " WHERE m.id < ?";
            $params[] = $before;
        }

        $query .= "
            GROUP BY m.id, m.message, m.created_at, u.username
            ORDER BY m.created_at DESC
            LIMIT ?
        ";
        $params[] = $limit;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();

        // Format messages
        $formattedMessages = array_map(function($msg) {
            return [
                'id' => $msg['id'],
                'message' => $msg['message'],
                'username' => $msg['username'],
                'badges' => $msg['badges'] ? explode(',', $msg['badges']) : [],
                'createdAt' => $msg['created_at']
            ];
        }, $messages);

        echo json_encode(['messages' => array_reverse($formattedMessages)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch messages']);
    }
    exit;
}

// Handle POST request to send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $message = isset($data['message']) ? trim($data['message']) : '';

    // Validate message
    if (empty($message) || strlen($message) > 500) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid message']);
        exit;
    }

    try {
        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (user_id, message)
            VALUES (?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $message]);
        $messageId = $pdo->lastInsertId();

        // Get user's badges
        $stmt = $pdo->prepare("
            SELECT b.icon 
            FROM user_badges ub
            JOIN badge_types b ON ub.badge_type_id = b.id
            WHERE ub.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $badges = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $messageData = [
            'id' => $messageId,
            'message' => $message,
            'username' => $_SESSION['username'],
            'badges' => $badges,
            'createdAt' => date('Y-m-d H:i:s')
        ];

        // Broadcast message to WebSocket server
        $ws_data = json_encode([
            'type' => 'chat',
            'message' => $messageData
        ]);
        
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $ws_data
        ]]);
        
        @file_get_contents('http://localhost:8080/broadcast', false, $context);

        echo json_encode(['message' => $messageData]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send message']);
    }
    exit;
}

// Handle unsupported methods
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

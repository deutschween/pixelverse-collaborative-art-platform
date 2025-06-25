<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Handle GET request to fetch canvas state
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $startX = isset($_GET['startX']) ? intval($_GET['startX']) : 0;
    $startY = isset($_GET['startY']) ? intval($_GET['startY']) : 0;
    $endX = isset($_GET['endX']) ? intval($_GET['endX']) : 100;
    $endY = isset($_GET['endY']) ? intval($_GET['endY']) : 100;

    try {
        $stmt = $pdo->prepare("
            SELECT x, y, color 
            FROM canvas_pixels 
            WHERE x BETWEEN ? AND ? 
            AND y BETWEEN ? AND ?
        ");
        $stmt->execute([$startX, $endX, $startY, $endY]);
        $pixels = $stmt->fetchAll();

        echo json_encode(['pixels' => $pixels]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch canvas state']);
    }
    exit;
}

// Handle POST request to place pixel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $x = isset($data['x']) ? intval($data['x']) : null;
    $y = isset($data['y']) ? intval($data['y']) : null;
    $color = isset($data['color']) ? $data['color'] : null;

    // Validate input
    if (!is_int($x) || !is_int($y) || !preg_match('/^#[0-9A-F]{6}$/i', $color)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid pixel data']);
        exit;
    }

    // Check cooldown
    $cooldown = checkCooldown($_SESSION['user_id']);
    if ($cooldown > 0) {
        http_response_code(429);
        echo json_encode([
            'error' => 'Cooldown active',
            'remainingTime' => $cooldown
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Place pixel
        $stmt = $pdo->prepare("
            INSERT INTO canvas_pixels (x, y, color, user_id)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            color = VALUES(color),
            user_id = VALUES(user_id),
            placed_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$x, $y, $color, $_SESSION['user_id']]);

        // Update user stats
        $stmt = $pdo->prepare("
            UPDATE user_stats 
            SET blocks_placed = blocks_placed + 1
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);

        // Check for case reward (every 15 blocks)
        $stmt = $pdo->prepare("
            SELECT blocks_placed 
            FROM user_stats 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $stats = $stmt->fetch();

        if ($stats['blocks_placed'] % 15 === 0) {
            // Award a case
            $stmt = $pdo->prepare("
                INSERT INTO cases (user_id, case_type_id)
                SELECT ?, id FROM case_types 
                WHERE rarity = 1 
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id']]);
        }

        $pdo->commit();

        // Get cooldown for next pixel
        $boosts = getActiveBoosts($_SESSION['user_id']);
        $nextCooldown = 5; // Default cooldown

        foreach ($boosts as $boost) {
            if ($boost['boost_type'] === 'instant_place') {
                $nextCooldown = 0;
                break;
            } elseif ($boost['boost_type'] === 'quick_cooldown') {
                $nextCooldown = 2.5;
            }
        }

        // Broadcast pixel update to WebSocket server
        $ws_data = json_encode([
            'type' => 'pixel',
            'x' => $x,
            'y' => $y,
            'color' => $color
        ]);
        
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $ws_data
        ]]);
        
        @file_get_contents('http://localhost:8080/broadcast', false, $context);

        echo json_encode([
            'message' => 'Pixel placed successfully',
            'nextCooldown' => $nextCooldown
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to place pixel']);
    }
    exit;
}

// Handle unsupported methods
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

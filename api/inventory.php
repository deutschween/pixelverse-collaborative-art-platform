<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Handle GET request to fetch inventory
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    try {
        // Get inventory items
        $stmt = $pdo->prepare("
            SELECT i.*, it.name, it.description, it.type, it.duration
            FROM inventory i
            JOIN item_types it ON i.item_type_id = it.id
            WHERE i.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $items = $stmt->fetchAll();

        // Get active boosts
        $stmt = $pdo->prepare("
            SELECT * FROM active_boosts 
            WHERE user_id = ? 
            AND expires_at > NOW()
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $activeBoosts = $stmt->fetchAll();

        echo json_encode([
            'inventory' => $items,
            'activeBoosts' => $activeBoosts
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch inventory']);
    }
    exit;
}

// Handle POST request to use item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $inventoryItemId = isset($data['inventoryItemId']) ? intval($data['inventoryItemId']) : null;

    if (!$inventoryItemId) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid item ID']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Get item details
        $stmt = $pdo->prepare("
            SELECT i.*, it.type, it.duration
            FROM inventory i
            JOIN item_types it ON i.item_type_id = it.id
            WHERE i.id = ? AND i.user_id = ?
        ");
        $stmt->execute([$inventoryItemId, $_SESSION['user_id']]);
        $item = $stmt->fetch();

        if (!$item) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Item not found in inventory']);
            exit;
        }

        // Remove item from inventory
        $stmt = $pdo->prepare("
            DELETE FROM inventory 
            WHERE id = ?
        ");
        $stmt->execute([$inventoryItemId]);

        // Add active boost if it's a boost item
        if ($item['type'] === 'boost') {
            $expiryTime = date('Y-m-d H:i:s', time() + $item['duration']);
            $stmt = $pdo->prepare("
                INSERT INTO active_boosts (user_id, boost_type, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $item['type'], $expiryTime]);
        }

        $pdo->commit();

        echo json_encode([
            'message' => 'Item used successfully',
            'itemType' => $item['type']
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to use item']);
    }
    exit;
}

// Handle unsupported methods
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

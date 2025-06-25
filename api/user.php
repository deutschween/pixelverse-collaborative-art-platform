<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Handle GET request to fetch user data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    try {
        // Get user data including stats and badges
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                us.currency,
                us.blocks_placed,
                GROUP_CONCAT(DISTINCT b.icon) as badges
            FROM users u
            LEFT JOIN user_stats us ON u.id = us.user_id
            LEFT JOIN user_badges ub ON u.id = ub.user_id
            LEFT JOIN badge_types b ON ub.badge_type_id = b.id
            WHERE u.id = ?
            GROUP BY u.id, u.username, u.email, us.currency, us.blocks_placed
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();

        if (!$userData) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        // Get active boosts
        $stmt = $pdo->prepare("
            SELECT boost_type, expires_at 
            FROM active_boosts 
            WHERE user_id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $activeBoosts = $stmt->fetchAll();

        echo json_encode([
            'id' => $userData['id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'currency' => (int)$userData['currency'],
            'blocksPlaced' => (int)$userData['blocks_placed'],
            'badges' => $userData['badges'] ? explode(',', $userData['badges']) : [],
            'activeBoosts' => $activeBoosts
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch user data']);
    }
    exit;
}

// Handle PUT request to update user settings
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $username = isset($data['username']) ? trim($data['username']) : null;
    $currentPassword = isset($data['currentPassword']) ? $data['currentPassword'] : null;
    $newPassword = isset($data['newPassword']) ? $data['newPassword'] : null;

    try {
        $pdo->beginTransaction();

        // If username is being updated
        if ($username) {
            // Check if username is taken
            $stmt = $pdo->prepare("
                SELECT id FROM users 
                WHERE username = ? AND id != ?
            ");
            $stmt->execute([$username, $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'Username already taken']);
                exit;
            }

            // Update username
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ? 
                WHERE id = ?
            ");
            $stmt->execute([$username, $_SESSION['user_id']]);
            $_SESSION['username'] = $username;
        }

        // If password is being updated
        if ($currentPassword && $newPassword) {
            // Verify current password
            $stmt = $pdo->prepare("
                SELECT password 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!verifyPassword($currentPassword, $user['password'])) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'Current password is incorrect']);
                exit;
            }

            // Update password
            $hashedPassword = hashPassword($newPassword);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ? 
                WHERE id = ?
            ");
            $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
        }

        $pdo->commit();
        echo json_encode(['message' => 'Settings updated successfully']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update settings']);
    }
    exit;
}

// Handle unsupported methods
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

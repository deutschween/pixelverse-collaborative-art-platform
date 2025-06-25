<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Handle GET request to fetch cases
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT c.*, ct.name, ct.description, ct.rarity
            FROM cases c
            JOIN case_types ct ON c.case_type_id = ct.id
            WHERE c.user_id = ? AND c.is_opened = 0
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $cases = $stmt->fetchAll();

        echo json_encode(['cases' => $cases]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch cases']);
    }
    exit;
}

// Handle POST request to open case
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $caseId = isset($data['caseId']) ? intval($data['caseId']) : null;

    if (!$caseId) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid case ID']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Get case details
        $stmt = $pdo->prepare("
            SELECT c.*, ct.rarity, ct.min_currency, ct.max_currency
            FROM cases c
            JOIN case_types ct ON c.case_type_id = ct.id
            WHERE c.id = ? AND c.user_id = ? AND c.is_opened = 0
        ");
        $stmt->execute([$caseId, $_SESSION['user_id']]);
        $case = $stmt->fetch();

        if (!$case) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Case not found or already opened']);
            exit;
        }

        // Determine reward
        $random = mt_rand(1, 100);
        $reward = null;

        if ($random > 70) { // 30% chance for badge
            // Get a random badge based on case rarity
            $stmt = $pdo->prepare("
                SELECT * FROM badge_types 
                WHERE rarity <= ? 
                ORDER BY RAND() 
                LIMIT 1
            ");
            $stmt->execute([$case['rarity']]);
            $badge = $stmt->fetch();

            if ($badge) {
                // Add badge to user's collection
                $stmt = $pdo->prepare("
                    INSERT INTO user_badges (user_id, badge_type_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $badge['id']]);
                $reward = ['type' => 'badge', 'badge' => $badge];
            }
        }

        if (!$reward) { // Currency reward if no badge was given
            $currencyAmount = mt_rand($case['min_currency'], $case['max_currency']);

            // Add currency to user's balance
            $stmt = $pdo->prepare("
                UPDATE user_stats 
                SET currency = currency + ?
                WHERE user_id = ?
            ");
            $stmt->execute([$currencyAmount, $_SESSION['user_id']]);
            $reward = ['type' => 'currency', 'amount' => $currencyAmount];
        }

        // Mark case as opened
        $stmt = $pdo->prepare("
            UPDATE cases 
            SET is_opened = 1,
                opened_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$caseId]);

        $pdo->commit();

        echo json_encode([
            'message' => 'Case opened successfully',
            'reward' => $reward
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to open case']);
    }
    exit;
}

// Handle unsupported methods
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

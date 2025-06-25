<?php
// Authentication functions
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendVerificationEmail($email, $code) {
    $to = $email;
    $subject = "Verify your PixelVerse account";
    $message = "
    <html>
    <head>
        <title>Email Verification</title>
    </head>
    <body>
        <h1>Welcome to PixelVerse!</h1>
        <p>Your verification code is: <strong>{$code}</strong></p>
        <p>This code will expire in 15 minutes.</p>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: PixelVerse <noreply@yourdomain.com>" . "\r\n";

    return mail($to, $subject, $message, $headers);
}

// User functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function getUserData($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.*, us.currency, us.blocks_placed 
        FROM users u 
        LEFT JOIN user_stats us ON u.id = us.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Inventory functions
function getUserInventory($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT i.*, it.name, it.description, it.type, it.duration
        FROM inventory i
        JOIN item_types it ON i.item_type_id = it.id
        WHERE i.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getActiveBoosts($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM active_boosts 
        WHERE user_id = ? AND expires_at > NOW()
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// Case functions
function getUserCases($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT c.*, ct.name, ct.description, ct.rarity
        FROM cases c
        JOIN case_types ct ON c.case_type_id = ct.id
        WHERE c.user_id = ? AND c.is_opened = 0
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function awardCase($userId, $rarity = 1) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO cases (user_id, case_type_id)
        SELECT ?, id FROM case_types WHERE rarity = ?
        LIMIT 1
    ");
    return $stmt->execute([$userId, $rarity]);
}

// Canvas functions
function checkCooldown($userId) {
    global $pdo;
    
    // Get active boosts
    $boosts = getActiveBoosts($userId);
    $cooldownTime = 5; // Default 5 seconds
    
    foreach ($boosts as $boost) {
        if ($boost['boost_type'] === 'instant_place') {
            return 0;
        } elseif ($boost['boost_type'] === 'quick_cooldown') {
            $cooldownTime = 2.5; // 50% reduction
        }
    }
    
    // Check last pixel placement
    $stmt = $pdo->prepare("
        SELECT placed_at 
        FROM canvas_pixels 
        WHERE user_id = ? 
        ORDER BY placed_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $lastPixel = $stmt->fetch();
    
    if ($lastPixel) {
        $timeSince = time() - strtotime($lastPixel['placed_at']);
        if ($timeSince < $cooldownTime) {
            return $cooldownTime - $timeSince;
        }
    }
    
    return 0;
}

// Chat functions
function getChatMessages($limit = 50, $before = null) {
    global $pdo;
    
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
    return array_reverse($stmt->fetchAll());
}

// Security functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelVerse - Collaborative Pixel Art</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">PixelVerse</h1>
            <div class="flex items-center gap-4">
                <?php if ($isLoggedIn): ?>
                    <span class="text-gray-600">
                        <i class="fas fa-coins mr-2"></i>
                        <span id="userCurrency">0</span>
                    </span>
                    <a href="inventory.php" class="text-gray-600">
                        <i class="fas fa-box-open mr-2"></i>
                        Inventory
                    </a>
                    <a href="auth/logout.php" class="text-red-600">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Logout
                    </a>
                <?php else: ?>
                    <a href="auth/login.php" class="text-blue-600">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto mt-8 grid grid-cols-12 gap-4">
        <!-- Canvas Area -->
        <div class="col-span-9">
            <div class="bg-white rounded-lg shadow-sm p-4">
                <canvas id="pixelCanvas" width="1000" height="1000" class="border border-gray-200 w-full"></canvas>
                <div class="mt-4 flex items-center gap-4">
                    <input type="color" id="colorPicker" class="w-12 h-12">
                    <div id="cooldownInfo" class="hidden text-red-500">
                        <i class="fas fa-clock mr-2"></i>
                        <span id="cooldownTimer"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-span-3 space-y-4">
            <!-- Cases -->
            <div class="bg-white rounded-lg shadow-sm p-4">
                <h2 class="text-xl font-bold mb-4">
                    <i class="fas fa-box mr-2"></i>
                    Cases
                </h2>
                <div id="casesList" class="space-y-2">
                    <!-- Cases will be loaded here -->
                </div>
            </div>

            <!-- Chat -->
            <div class="bg-white rounded-lg shadow-sm p-4">
                <h2 class="text-xl font-bold mb-4">
                    <i class="fas fa-comments mr-2"></i>
                    Chat
                </h2>
                <div id="chatMessages" class="h-64 overflow-y-auto space-y-2 mb-4">
                    <!-- Chat messages will be loaded here -->
                </div>
                <?php if ($isLoggedIn): ?>
                    <div class="flex gap-2">
                        <input type="text" id="chatInput" class="flex-1 rounded border border-gray-300 px-3 py-2" placeholder="Type a message...">
                        <button id="sendMessage" class="bg-blue-600 text-white px-4 py-2 rounded">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/canvas.js"></script>
    <script src="assets/js/chat.js"></script>
    <script src="assets/js/cases.js"></script>
</body>
</html>

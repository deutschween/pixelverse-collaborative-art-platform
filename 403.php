<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Forbidden - PixelVerse</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="text-center">
        <div class="text-6xl text-gray-400 mb-8">
            <i class="fas fa-lock"></i>
        </div>
        <h1 class="text-4xl font-bold text-gray-800 mb-4">403 - Access Denied</h1>
        <p class="text-gray-600 mb-8">Sorry, you don't have permission to access this area.</p>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="space-x-4">
                <a href="/auth/login.php" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Login
                </a>
                <a href="/auth/register.php" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-user-plus mr-2"></i>
                    Register
                </a>
            </div>
        <?php else: ?>
            <a href="/" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-home mr-2"></i>
                Return Home
            </a>
        <?php endif; ?>
    </div>
</body>
</html>

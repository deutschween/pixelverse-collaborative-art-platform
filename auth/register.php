<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';
$success = '';
$isVerifying = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'];

    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid request';
    } else {
        if (isset($_POST['verification_code'])) {
            // Handle verification
            $email = $_SESSION['pending_verification_email'];
            $code = sanitizeInput($_POST['verification_code']);

            $stmt = $pdo->prepare("
                SELECT * FROM users 
                WHERE email = ? 
                AND verification_code = ? 
                AND verification_expiry > NOW()
                AND is_verified = 0
            ");
            $stmt->execute([$email, $code]);
            $user = $stmt->fetch();

            if ($user) {
                // Mark user as verified
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET is_verified = 1,
                        verification_code = NULL,
                        verification_expiry = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);

                // Initialize user stats
                $stmt = $pdo->prepare("
                    INSERT INTO user_stats (user_id, currency, blocks_placed)
                    VALUES (?, 0, 0)
                ");
                $stmt->execute([$user['id']]);

                $success = 'Email verified successfully! You can now login.';
                unset($_SESSION['pending_verification_email']);
            } else {
                $error = 'Invalid or expired verification code';
                $isVerifying = true;
            }
        } else {
            // Handle registration
            $username = sanitizeInput($_POST['username']);
            $email = sanitizeInput($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            if ($password !== $confirm_password) {
                $error = 'Passwords do not match';
            } else {
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->rowCount() > 0) {
                    $error = 'Username or email already exists';
                } else {
                    // Generate verification code
                    $verificationCode = generateVerificationCode();
                    $verificationExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    $hashedPassword = hashPassword($password);

                    // Create user
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, verification_code, verification_expiry)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([$username, $email, $hashedPassword, $verificationCode, $verificationExpiry])) {
                        // Send verification email
                        if (sendVerificationEmail($email, $verificationCode)) {
                            $_SESSION['pending_verification_email'] = $email;
                            $success = 'Registration successful! Please check your email for verification code.';
                            $isVerifying = true;
                        } else {
                            $error = 'Failed to send verification email';
                        }
                    } else {
                        $error = 'Registration failed';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PixelVerse</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-12">
    <div class="bg-white rounded-lg shadow-sm p-8 w-full max-w-md">
        <h1 class="text-2xl font-bold text-center mb-6">
            <?php echo $isVerifying ? 'Verify Email' : 'Join PixelVerse'; ?>
        </h1>
        
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-500 p-4 rounded-lg mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($isVerifying): ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Verification Code
                    </label>
                    <input 
                        type="text" 
                        name="verification_code" 
                        required 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Enter 6-digit code"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    Verify Email
                </button>

                <p class="text-center text-sm text-gray-600">
                    Didn't receive the code? 
                    <a href="register.php" class="text-blue-600 hover:underline">Try Again</a>
                </p>
            </form>
        <?php else: ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Username
                    </label>
                    <input 
                        type="text" 
                        name="username" 
                        required 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Email
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        required 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Password
                    </label>
                    <input 
                        type="password" 
                        name="password" 
                        required 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Confirm Password
                    </label>
                    <input 
                        type="password" 
                        name="confirm_password" 
                        required 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    Register
                </button>

                <p class="text-center text-sm text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="text-blue-600 hover:underline">Login</a>
                </p>
            </form>
        <?php endif; ?>
    </div>

    <script>
    // Clear form data on page load to prevent resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error - PixelVerse</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="text-center">
        <div class="text-6xl text-gray-400 mb-8">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h1 class="text-4xl font-bold text-gray-800 mb-4">500 - Server Error</h1>
        <p class="text-gray-600 mb-4">Oops! Something went wrong on our end.</p>
        <p class="text-gray-500 mb-8">Our team has been notified and is working on fixing the issue.</p>
        <div class="space-x-4">
            <button onclick="location.reload()" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-redo mr-2"></i>
                Try Again
            </button>
            <a href="/" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                <i class="fas fa-home mr-2"></i>
                Return Home
            </a>
        </div>

        <?php if (isset($_SERVER['HTTP_REFERER'])): ?>
            <div class="mt-8">
                <a href="<?php echo htmlspecialchars($_SERVER['HTTP_REFERER']); ?>" class="text-blue-600 hover:underline">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Go Back
                </a>
            </div>
        <?php endif; ?>

        <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <div class="mt-8 p-4 bg-red-50 rounded-lg text-left max-w-2xl mx-auto">
                <h2 class="font-bold text-red-800 mb-2">Error Details:</h2>
                <pre class="text-red-600 text-sm overflow-x-auto">
                    <?php 
                    if (isset($error)) {
                        echo htmlspecialchars($error);
                    } else {
                        error_log("500 Error: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'Unknown URL'));
                    }
                    ?>
                </pre>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Report error to analytics (if configured)
    if (typeof gtag !== 'undefined') {
        gtag('event', 'error_500', {
            'event_category': 'error',
            'event_label': window.location.pathname
        });
    }
    </script>
</body>
</html>

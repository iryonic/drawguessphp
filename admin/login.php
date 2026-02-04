<?php
require_once 'auth.php';
// If already logged in, go to index
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - DrawGuess</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Fredoka', 'sans-serif'] },
                    colors: { ink: '#1e1e1e', paper: '#f8f9fa' }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white border-4 border-ink shadow-[8px_8px_0px_#1e1e1e] p-8 rounded-xl w-full max-w-sm">
        <h1 class="text-3xl font-black text-center mb-6">🔒 Admin Access</h1>
        
        <?php if(isset($error)): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded-lg border-2 border-red-500 mb-4 text-center font-bold">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="block font-bold text-gray-500 mb-2">Username</label>
                <input type="text" name="username" class="w-full border-2 border-gray-300 rounded-lg p-3 focus:border-ink focus:outline-none focus:shadow-[4px_4px_0px_#1e1e1e] transition-all" autofocus>
            </div>
            <div class="mb-4">
                <label class="block font-bold text-gray-500 mb-2">Password</label>
                <input type="password" name="password" class="w-full border-2 border-gray-300 rounded-lg p-3 focus:border-ink focus:outline-none focus:shadow-[4px_4px_0px_#1e1e1e] transition-all">
            </div>
            <button type="submit" class="w-full bg-black text-white font-bold py-3 rounded-lg border-2 border-black hover:bg-gray-800 active:translate-y-1 transition-transform">
                ENTER PANEL
            </button>
        </form>
        <div class="text-center mt-4">
            <a href="../index.php" class="text-xs font-bold text-gray-400 hover:text-ink">← Back to Game</a>
        </div>
    </div>
</body>
</html>

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
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white border-[3px] border-ink shadow-[12px_12px_0px_#000] p-10 rounded-3xl w-full max-w-sm relative overflow-hidden">
        <!-- Accent -->
        <div class="absolute top-0 left-0 w-full h-2 bg-pop-yellow"></div>
        
        <div class="text-center mb-8">
            <div class="inline-block p-4 bg-yellow-50 rounded-2xl border-2 border-dashed border-yellow-200 mb-4 scale-75 lg:scale-100">
                <span class="text-4xl">⚡</span>
            </div>
            <h1 class="text-3xl font-black text-ink uppercase tracking-tight italic">Panel Login</h1>
            <p class="text-gray-400 font-bold text-[10px] uppercase tracking-widest mt-1">Authorization Required</p>
        </div>
        
        <?php if(isset($error)): ?>
            <div class="bg-red-50 text-red-700 p-3 rounded-xl border-2 border-red-500 mb-6 text-center font-black text-sm">
                💥 <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-6">
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Identity</label>
                <input type="text" name="username" placeholder="Username" class="w-full border-2 border-gray-100 rounded-2xl p-4 text-sm font-bold focus:border-ink focus:outline-none focus:shadow-[4px_4px_0px_#000] transition-all bg-gray-50/50" autofocus>
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Secret</label>
                <input type="password" name="password" placeholder="••••••••" class="w-full border-2 border-gray-100 rounded-2xl p-4 text-sm font-bold focus:border-ink focus:outline-none focus:shadow-[4px_4px_0px_#000] transition-all bg-gray-50/50">
            </div>
            
            <button type="submit" class="w-full bg-ink text-white font-black py-4 rounded-2xl border-2 border-ink hover:bg-gray-800 active:translate-y-1 active:shadow-none transition-all shadow-[6px_6px_0px_#ccc]">
                ACCESS DASHBOARD
            </button>
        </form>

        <div class="text-center mt-8">
            <a href="<?= APP_ROOT ?>" class="inline-flex items-center gap-2 text-[10px] font-black text-gray-300 hover:text-ink uppercase tracking-widest transition-colors">
                <span>←</span> Back to Game
            </a>
        </div>
    </div>
</body>
</html>

<nav class="bg-ink text-white p-4 mb-8">
    <div class="container mx-auto flex flex-col sm:flex-row justify-between items-center bg-ink text-white">
        <div class="flex items-center gap-4 mb-4 sm:mb-0">
            <h1 class="text-2xl font-black text-yellow-300">ADMIN PANEL ⚡</h1>
            <div class="hidden sm:flex gap-2">
                <a href="index.php" class="px-3 py-1 rounded hover:bg-white/20 <?= basename($_SERVER['PHP_SELF'])=='index.php'?'bg-white/20':'' ?>">Dashboard</a>
                <a href="words.php" class="px-3 py-1 rounded hover:bg-white/20 <?= basename($_SERVER['PHP_SELF'])=='words.php'?'bg-white/20':'' ?>">Mange Words</a>
                <a href="avatars.php" class="px-3 py-1 rounded hover:bg-white/20 <?= basename($_SERVER['PHP_SELF'])=='avatars.php'?'bg-white/20':'' ?>">Mange Avatars</a>

            </div>
        </div>
        <div class="flex gap-4 items-center">
            <div class="sm:hidden flex gap-2">
                <a href="index.php" class="text-sm underline">Stats</a>
                <a href="words.php" class="text-sm underline">Words</a>
            </div>
            <a href="auth.php?logout=1" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg font-bold text-xs border-2 border-transparent">LOGOUT</a>
        </div>
    </div>
</nav>

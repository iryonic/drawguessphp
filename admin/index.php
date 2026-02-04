<?php
require_once 'auth.php';
require_once '../api/db.php';
checkAdmin();

// Fetch Stats
$total_players = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM players"))['c'];
$total_rooms = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM rooms"))['c'];
$active_rooms = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM rooms WHERE status='playing'"))['c'];
$total_words = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM words"))['c'];

// Clean up option
if (isset($_POST['cleanup'])) {
    // Delete rooms older than 24 hours
    mysqli_query($conn, "DELETE FROM rooms WHERE created_at < NOW() - INTERVAL 24 HOUR");
    $msg = "Cleanup executed!";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DrawGuess Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Fredoka', 'sans-serif'] },
                    colors: { ink: '#1e1e1e' }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4">
        
        <?php if(isset($msg)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p class="font-bold">Success</p>
                <p><?= $msg ?></p>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-6 rounded-xl border-2 border-indigo-100 shadow-sm text-center">
                <div class="text-3xl font-black text-indigo-500"><?= $total_rooms ?></div>
                <div class="text-xs font-bold text-gray-400 uppercase">Total Rooms</div>
            </div>
            <div class="bg-white p-6 rounded-xl border-2 border-green-100 shadow-sm text-center">
                <div class="text-3xl font-black text-green-500"><?= $active_rooms ?></div>
                <div class="text-xs font-bold text-gray-400 uppercase">Active Games</div>
            </div>
            <div class="bg-white p-6 rounded-xl border-2 border-pink-100 shadow-sm text-center">
                <div class="text-3xl font-black text-pink-500"><?= $total_players ?></div>
                <div class="text-xs font-bold text-gray-400 uppercase">Total Players</div>
            </div>
            <div class="bg-white p-6 rounded-xl border-2 border-yellow-100 shadow-sm text-center">
                <div class="text-3xl font-black text-yellow-500"><?= $total_words ?></div>
                <div class="text-xs font-bold text-gray-400 uppercase">Words DB</div>
            </div>
        </div>

        <div class="bg-white p-8 rounded-xl border-2 border-gray-100 shadow-sm">
             <h2 class="text-xl font-black mb-4">🛠️ Maintenance</h2>
             <form method="POST">
                 <button type="submit" name="cleanup" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded shadow-[2px_2px_0px_rgba(0,0,0,0.2)] active:shadow-none active:translate-y-[2px] transition-all">
                    🧹 Clean Old Rooms (>24h)
                 </button>
                 <p class="text-xs text-gray-400 mt-2">Removes stale rooms created more than 24 hours ago.</p>
             </form>
        </div>

    </div>

</body>
</html>

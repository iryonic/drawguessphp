<?php
/**
 * Admin Dashboard
 * Refactored to use PDO and follow the new security architecture.
 */
require_once 'auth.php';
require_once '../api/db.php';

// Ensure user is authorized
checkAdmin();

// Fetch Platform Stats using PDO
$total_players = DB::fetch("SELECT COUNT(*) as c FROM players")['c'];
$total_rooms = DB::fetch("SELECT COUNT(*) as c FROM rooms")['c'];
$active_rooms = DB::fetch("SELECT COUNT(*) as c FROM rooms WHERE status = ?", ['playing'])['c'];
$total_words = DB::fetch("SELECT COUNT(*) as c FROM words")['c'];

// Handlers with CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        // Corrected interval syntax for standard SQL (or MySQL specific)
        DB::query("DELETE FROM rooms WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $msg = "Cleanup executed successfully!";
    } else {
        $error = "CSRF Security Check Failed.";
    }
}

// Fetch Active Rooms with Player Counts (Ordered by activity)
$rooms = DB::fetchAll("
    SELECT r.*, COUNT(p.id) as player_count 
    FROM rooms r 
    LEFT JOIN players p ON r.id = p.room_id 
    GROUP BY r.id 
    ORDER BY r.created_at DESC 
    LIMIT 10
");

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
                    colors: { ink: '#1e1e1e', pop: { red: '#ff5252', blue: '#4fc3f7', pink: '#ff80ab', yellow: '#ffeb3b', purple: '#b39ddb' } }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen pb-12">
    
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4">
        
        <?php if(isset($msg)): ?>
            <div class="bg-green-100 border-2 border-green-500 text-green-700 p-4 mb-8 rounded-xl font-bold flex items-center gap-3">
                <span class="text-xl">✅</span> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="bg-red-100 border-2 border-red-500 text-red-700 p-4 mb-8 rounded-xl font-bold flex items-center gap-3">
                <span class="text-xl">❌</span> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <h2 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Platform Overview</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
            <div class="bg-white p-6 rounded-2xl border-2 border-ink shadow-[4px_4px_0px_#000]">
                <div class="text-4xl font-black text-ink mb-1"><?= $total_rooms ?></div>
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Rooms</div>
            </div>
            <div class="bg-white p-6 rounded-2xl border-2 border-ink shadow-[4px_4px_0px_#4fc3f7]">
                <div class="text-4xl font-black text-pop-blue mb-1"><?= $active_rooms ?></div>
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">In Progress</div>
            </div>
            <div class="bg-white p-6 rounded-2xl border-2 border-ink shadow-[4px_4px_0_#ff80ab]">
                <div class="text-4xl font-black text-pop-pink mb-1"><?= $total_players ?></div>
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Global Players</div>
            </div>
            <div class="bg-white p-6 rounded-2xl border-2 border-ink shadow-[4px_4px_0_#ffeb3b]">
                <div class="text-4xl font-black text-pop-yellow mb-1"><?= $total_words ?></div>
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Vocabulary</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Active Rooms Table -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl border-2 border-ink overflow-hidden shadow-[8px_8px_0px_rgba(0,0,0,0.05)]">
                    <div class="p-6 border-b-2 border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h2 class="text-xl font-black flex items-center gap-2 text-ink">
                            <span>🎮</span> Recent Activities
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase tracking-widest border-b-2 border-gray-100">
                                <tr>
                                    <th class="px-6 py-4">Room</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4">Players</th>
                                    <th class="px-6 py-4">Progress</th>
                                    <th class="px-6 py-4 text-right">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($rooms as $room): ?>
                                <tr class="hover:bg-blue-50/30 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="font-mono font-bold text-ink bg-gray-100 px-2 py-1 rounded inline-block border border-gray-200"><?= htmlspecialchars($room['room_code']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if($room['status'] === 'playing'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Playing
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500 border border-gray-200 uppercase tracking-tighter">
                                                <?= htmlspecialchars($room['status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold text-ink"><?= $room['player_count'] ?> / 10</td>
                                    <td class="px-6 py-4">
                                        <div class="text-[10px] font-black text-gray-400 uppercase mb-1">Round <?= $room['current_round'] ?> / <?= $room['max_rounds'] ?></div>
                                        <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden border border-gray-200">
                                            <div class="h-full bg-pop-pink" style="width: <?= ($room['current_round'] / $room['max_rounds']) * 100 ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-xs font-bold text-gray-400 uppercase">
                                        <?= date('H:i', strtotime($room['created_at'])) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Maintenance & Sidebar -->
            <div class="space-y-6">
                <!-- Cleanup Card -->
                <div class="bg-white p-8 rounded-2xl border-2 border-ink shadow-[8px_8px_0px_#ff8a80]">
                     <h2 class="text-xl font-black mb-1 flex items-center gap-2">🛠️ System</h2>
                     <p class="text-xs text-gray-400 font-bold uppercase tracking-tight mb-6">Database Management</p>
                     
                     <form method="POST" class="space-y-4">
                         <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                         <div class="p-4 bg-red-50 border-2 border-dashed border-red-100 rounded-xl mb-4">
                             <p class="text-xs font-bold text-red-700 leading-snug">
                                Running cleanup will permanently delete all inactive rooms older than 24 hours.
                             </p>
                         </div>
                         <button type="submit" name="cleanup" class="w-full bg-pop-red hover:bg-red-400 border-2 border-ink text-ink font-black py-4 rounded-xl text-sm shadow-[4px_4px_0px_#000] active:shadow-none active:translate-y-1 transition-all">
                            CLEAN OLD ROOMS
                         </button>
                     </form>
                </div>

                <!-- Database Info -->
                <div class="bg-white p-6 rounded-2xl border-2 border-ink shadow-[8px_8px_0px_#ce93d8]">
                     <h2 class="text-lg font-black mb-4">📖 Word Quick Stats</h2>
                     <div class="space-y-3">
                         <?php
                            $diff_stats = DB::fetchAll("SELECT difficulty, COUNT(*) as c FROM words GROUP BY difficulty");
                            foreach($diff_stats as $ds):
                                $color = $ds['difficulty'] == 'easy' ? 'bg-green-400' : ($ds['difficulty'] == 'medium' ? 'bg-yellow-400' : 'bg-red-400');
                         ?>
                         <div class="flex items-center justify-between p-3 bg-gray-50 border-2 border-gray-100 rounded-xl">
                             <div class="flex items-center gap-2">
                                 <div class="w-2 h-2 rounded-full <?= $color ?>"></div>
                                 <span class="text-xs font-black uppercase text-gray-500 tracking-tight"><?= htmlspecialchars($ds['difficulty']) ?></span>
                             </div>
                             <span class="text-sm font-black text-ink"><?= $ds['c'] ?></span>
                         </div>
                         <?php endforeach; ?>
                     </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

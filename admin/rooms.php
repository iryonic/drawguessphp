<?php
/**
 * Room Management - Admin Module
 * Allows viewing all active rooms and force-closing sessions.
 */
require_once 'auth.php';
require_once '../api/db.php';
checkAdmin();

$msg = null;
$error = null;
$token = getCSRFToken();

// 1. Force Close/Delete Room (Single or Bulk)
if (isset($_GET['delete_room'])) {
    if (!verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        $error = "Security validation failed.";
    } else {
        $room_id = intval($_GET['delete_room']);
        DB::query("DELETE FROM rooms WHERE id = ?", [$room_id]);
        $msg = "Room session #$room_id permanently terminated.";
        header("Location: rooms.php?msg=" . urlencode($msg));
        exit;
    }
}

if (isset($_POST['bulk_terminate']) && !empty($_POST['selected_rooms'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security mismatch.";
    } else {
        $ids = array_map('intval', $_POST['selected_rooms']);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            DB::query("DELETE FROM rooms WHERE id IN ($placeholders)", $ids);
            $msg = "Bulk termination successful for " . count($ids) . " sessions.";
        }
    }
}

// 2. Fetch All Rooms with Player Info
$rooms = DB::fetchAll("
    SELECT r.*, 
    (SELECT COUNT(*) FROM players p WHERE p.room_id = r.id) as player_count,
    (SELECT username FROM players p WHERE p.id = r.host_id LIMIT 1) as host_name
    FROM rooms r
    ORDER BY r.created_at DESC
");

if (isset($_GET['msg'])) $msg = $_GET['msg'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Fredoka', 'sans-serif'] }, colors: { ink: '#1e1e1e', 'pop-red': '#ff5252' } } } }
    </script>
</head>
<body class="bg-gray-50 min-h-screen pb-12 text-ink">
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 max-w-6xl">
        <div class="flex justify-between items-end mb-10">
            <div>
                <h1 class="text-3xl font-black italic">Active Sessions</h1>
                <p class="text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Real-time room monitoring & control</p>
            </div>
            <div class="bg-white border-2 border-ink px-4 py-2 rounded-xl font-black text-sm shadow-[4px_4px_0px_#000]">
                TOTAL: <?= count($rooms) ?>
            </div>
        </div>

        <?php if($msg): ?>
            <div class="bg-green-100 border-2 border-green-500 text-green-700 p-4 mb-8 rounded-2xl font-black flex items-center gap-3">
                ✅ <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="bg-red-100 border-2 border-red-500 text-red-700 p-4 mb-8 rounded-2xl font-black flex items-center gap-3">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="bulk-form">
            <input type="hidden" name="csrf_token" value="<?= $token ?>">
            <div class="bg-white rounded-3xl border-2 border-ink overflow-hidden shadow-[8px_8px_0px_rgba(0,0,0,0.05)]">
                <div class="p-6 border-b-2 border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" id="select-all" class="w-5 h-5 rounded border-2 border-ink accent-ink">
                            <span class="text-xs font-black text-gray-400 uppercase tracking-widest">Select All</span>
                        </label>
                    </div>
                    <button type="submit" name="bulk_terminate" onclick="return confirm('Terminate all selected sessions?')" class="bg-pop-red border-2 border-ink text-ink px-5 py-2.5 rounded-xl font-black text-[10px] shadow-[4px_4px_0px_#000] hover:bg-red-400 active:translate-y-0.5 active:shadow-none transition-all uppercase tracking-widest">
                        Terminate Selected
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-[10px] font-black text-gray-300 uppercase tracking-widest border-b-2 border-gray-100">
                            <tr>
                                <th class="px-8 py-5 w-10"></th>
                                <th class="px-8 py-5">Room Code</th>
                                <th class="px-8 py-5">Host</th>
                                <th class="px-8 py-5">Players</th>
                                <th class="px-8 py-5">Status</th>
                                <th class="px-8 py-5">Round</th>
                                <th class="px-8 py-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($rooms as $room): ?>
                            <tr class="hover:bg-blue-50/20 transition-colors group">
                                <td class="px-8 py-6">
                                    <input type="checkbox" name="selected_rooms[]" value="<?= $room['id'] ?>" class="room-checkbox w-5 h-5 rounded border-2 border-ink accent-ink">
                                </td>
                                <td class="px-8 py-6">
                                    <span class="font-mono font-black text-lg bg-gray-100 px-3 py-1 rounded-lg border-2 border-gray-200 uppercase"><?= htmlspecialchars($room['room_code']) ?></span>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="font-bold text-sm"><?= htmlspecialchars($room['host_name'] ?? 'Disconnected') ?></div>
                                    <div class="text-[10px] text-gray-300 uppercase font-bold tracking-tighter">ID: #<?= $room['host_id'] ?></div>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-2">
                                        <span class="font-black text-sm"><?= $room['player_count'] ?> / 10</span>
                                        <div class="flex -space-x-1">
                                            <?php 
                                                $avatars = DB::fetchAll("SELECT avatar FROM players WHERE room_id = ? LIMIT 3", [$room['id']]);
                                                foreach($avatars as $av) echo "<span class='text-xs filter grayscale-[0.5]'>{$av['avatar']}</span>";
                                            ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <?php if($room['status'] === 'playing'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black bg-green-100 text-green-700 border-2 border-green-200 uppercase tracking-tight">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Ongoing
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black bg-gray-100 text-gray-400 border-2 border-gray-200 uppercase tracking-tight">
                                            <?= htmlspecialchars($room['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="text-[11px] font-black text-gray-400 uppercase tracking-tight mb-1">Rnd <?= $room['current_round'] ?> / <?= $room['max_rounds'] ?></div>
                                    <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden border border-gray-200">
                                        <div class="h-full bg-pink-400" style="width: <?= ($room['current_round'] / ($room['max_rounds'] ?: 1)) * 100 ?>%"></div>
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-right">
                                    <a href="?delete_room=<?= $room['id'] ?>&csrf_token=<?= $token ?>" 
                                       onclick="return confirm('Immediately terminate this session? Users will be disconnected.')"
                                       class="opacity-0 group-hover:opacity-100 transition-opacity bg-pop-red hover:bg-red-400 border-2 border-ink text-ink px-4 py-2 rounded-xl font-black text-[10px] shadow-[3px_3px_0px_#000] active:translate-y-0.5 active:shadow-none transition-all uppercase tracking-widest">
                                        Force Expire
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($rooms)): ?>
                                <tr><td colspan="7" class="py-32 text-center font-bold text-gray-300 italic text-xl">No active game sessions detected.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <script>
        const selectAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.room-checkbox');

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }
    </script>
</body>
</html>

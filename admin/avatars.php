<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'auth.php';
require_once '../api/db.php';
checkAdmin();

// ensure table exists and supports emojis
$tbl_q = "CREATE TABLE IF NOT EXISTS avatars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emoji VARCHAR(10) NOT NULL UNIQUE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin";
mysqli_query($conn, $tbl_q);
mysqli_query($conn, "ALTER TABLE avatars CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_bin");

// --- ACTIONS ---

// Add Avatars (Single or Bulk)
if (isset($_POST['add_avatars'])) {
    $raw = $_POST['emoji_input'];
    // Split string into unicode characters
    $chars = preg_split('//u', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $added = 0;
    
    foreach ($chars as $char) {
        // Filter out spaces, commas, newlines
        if (trim($char) === '' || $char === ',' || $char === "\n" || $char === "\r") continue;
        
        $e = sanitize($conn, $char);
        // Try insert
        if (mysqli_query($conn, "INSERT IGNORE INTO avatars (emoji) VALUES ('$e')")) {
            if (mysqli_affected_rows($conn) > 0) $added++;
        }
    }
    
    $msg = "Added $added avatars!";
    if ($added == 0) $err = "No new avatars added (duplicates or invalid).";
}

// Bulk Delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
    if (!empty($ids)) {
        $ids_str = implode(',', $ids);
        mysqli_query($conn, "DELETE FROM avatars WHERE id IN ($ids_str)");
        $del_count = mysqli_affected_rows($conn);
        header("Location: avatars.php?msg=Deleted $del_count avatars");
        exit;
    }
}

// Restore Defaults
if (isset($_POST['restore_defaults'])) {
    $defaults = ['🐱', '🐶', '🦁', '🦊', '🐸', '🐼', '🐨', '🐷', '🐵', '🦄', '🐙', '👾', '🤖', '👽', '👻', '🤡', '🤠', '🎃', '💀', '💩', '🌞', '🌈', '🍕', '🍔', '🥑', '🌮', '🔥', '✨'];
    $count = 0;
    foreach ($defaults as $av) {
        $av = mysqli_real_escape_string($conn, $av);
        mysqli_query($conn, "INSERT IGNORE INTO avatars (emoji) VALUES ('$av')");
        if (mysqli_affected_rows($conn) > 0) $count++;
    }
    header("Location: avatars.php?msg=Restored $count default avatars");
    exit;
}

// Single Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM avatars WHERE id = $id");
    header("Location: avatars.php");
    exit;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

// Fetch All
$res = mysqli_query($conn, "SELECT * FROM avatars ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Avatars - DrawGuess Admin</title>
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

        <h1 class="text-3xl font-black mb-6">Manage Avatars</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- Add Form -->
            <div class="md:col-span-1">
                <div class="bg-white p-6 rounded-xl border-2 border-gray-100 shadow-sm sticky top-4">
                    <h2 class="text-xl font-black mb-4">Add Avatars</h2>
                    
                    <?php if(isset($msg)): ?>
                        <div class="bg-green-100 text-green-700 p-2 rounded mb-4 text-sm font-bold animate-pulse"><?= $msg ?></div>
                    <?php endif; ?>
                    <?php if(isset($err)): ?>
                        <div class="bg-yellow-100 text-yellow-700 p-2 rounded mb-4 text-sm font-bold"><?= $err ?></div>
                    <?php endif; ?>

                    <form method="POST" class="mb-8">
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Paste Emojis (Bulk supported)</label>
                            <textarea name="emoji_input" rows="3" placeholder="Paste multiple emojis here: 🦄 🐶 🦁" class="w-full border-2 border-gray-200 rounded-lg p-3 text-2xl focus:border-ink outline-none font-bold resize-none" required></textarea>
                            <p class="text-xs text-gray-400 mt-1">Accepts multiple characters at once.</p>
                        </div>
                        <button type="submit" name="add_avatars" class="w-full bg-ink text-white font-bold py-3 rounded-lg shadow-[4px_4px_0px_#ccc] active:shadow-none active:translate-y-1 transition-all mb-4">
                            ADD AVATARS
                        </button>
                    </form>

                    <hr class="border-gray-100 mb-6">

                    <form method="POST">
                        <h3 class="font-bold text-gray-400 text-xs uppercase mb-2">Missing Avatars?</h3>
                        <button type="submit" name="restore_defaults" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold py-2 rounded-lg border-2 border-gray-200 transition-all text-sm mb-2" onclick="return confirm('Load default avatars?')">
                            🔄 LOAD DEFAULT PACK
                        </button>
                    </form>
                </div>
            </div>

            <!-- List -->
            <div class="md:col-span-2">
                <form method="POST" id="bulk_delete_form">
                <div class="bg-white rounded-xl border-2 border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-4 border-b-2 border-gray-100 bg-gray-50 flex flex-wrap justify-between items-center gap-4">
                        <div class="flex items-center gap-4">
                            <h2 class="font-black text-lg">Library <span class="bg-gray-200 text-gray-600 text-xs px-2 py-1 rounded ml-2"><?= mysqli_num_rows($res) ?></span></h2>
                            
                            <label class="flex items-center gap-2 cursor-pointer select-none text-sm font-bold text-gray-500 hover:text-ink">
                                <input type="checkbox" onclick="toggleAll(this)" class="w-4 h-4 accent-ink rounded">
                                Select All
                            </label>
                        </div>

                        <div>
                            <button type="submit" name="bulk_delete" onclick="return confirm('Delete selected avatars?')" class="bg-red-100 text-red-600 hover:bg-red-500 hover:text-white px-4 py-2 rounded-lg font-bold text-sm transition-all border border-red-200">
                                🗑️ Delete Selected
                            </button>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <?php if(mysqli_num_rows($res) == 0): ?>
                            <div class="text-center py-10 text-gray-400">
                                <div class="text-4xl mb-2">🤷</div>
                                <p>No avatars found.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-8 gap-4">
                                <?php while($row = mysqli_fetch_assoc($res)): ?>
                                    <label class="group relative aspect-square bg-gray-50 hover:bg-white border-2 border-gray-100 hover:border-blue-500 rounded-xl flex items-center justify-center text-4xl cursor-pointer transition-all hover:shadow-[3px_3px_0px_#ccc] select-none">
                                        <!-- Checkbox -->
                                        <input type="checkbox" name="selected_ids[]" value="<?= $row['id'] ?>" class="absolute top-2 left-2 w-5 h-5 accent-blue-600 z-10 opacity-50 group-hover:opacity-100 checked:opacity-100 cursor-pointer">
                                        
                                        <?= $row['emoji'] ?>
                                        
                                        <!-- Quick Delete X -->
                                        <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete?')" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs font-bold opacity-0 group-hover:opacity-100 transition shadow hover:bg-red-600 z-20">✕</a>
                                    </label>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                </form>
            </div>

        </div>

    </div>

    <script>
        function toggleAll(source) {
            document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => {
                cb.checked = source.checked;
            });
        }
    </script>
</body>
</html>

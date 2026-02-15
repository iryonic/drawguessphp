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
        header("Location: " . APP_ROOT . "admin/avatars?msg=Deleted $del_count avatars");
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
    header("Location: " . APP_ROOT . "admin/avatars?msg=Restored $count default avatars");
    exit;
}

// Single Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM avatars WHERE id = $id");
    header("Location: " . APP_ROOT . "admin/avatars");
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
<body class="bg-gray-50 min-h-screen pb-12">
    
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4">

        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-black text-ink">Avatar Library</h1>
                <p class="text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Manage player icons and defaults</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Add Form -->
                <div class="bg-white p-6 rounded-2xl border-2 border-ink shadow-[4px_4px_0px_#000]">
                    <h2 class="text-lg font-black mb-4 flex items-center gap-2 font-bold">
                        <span class="bg-blue-100 p-1 rounded-lg">✨</span> Add New
                    </h2>
                    
                    <?php if(isset($msg)): ?>
                        <div class="bg-green-100 text-green-700 p-3 rounded-xl mb-4 text-xs font-bold border-2 border-green-200"><?= $msg ?></div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1 font-bold">Emoji Input</label>
                            <textarea name="emoji_input" rows="3" placeholder="Paste emojis: 🦄 🐶 🦁" class="w-full border-2 border-gray-100 rounded-xl p-4 text-3xl focus:border-ink outline-none font-bold resize-none" required></textarea>
                            <p class="text-[9px] text-gray-400 mt-2 font-bold px-1 italic leading-tight">Separate by spaces or just paste a string of emojis.</p>
                        </div>
                        <button type="submit" name="add_avatars" class="w-full bg-pop-blue hover:bg-blue-300 border-2 border-ink text-white font-black py-3 rounded-xl text-sm shadow-[4px_4px_0px_#000] active:shadow-none active:translate-y-1 transition-all">
                            IMPORT AVATARS
                        </button>
                    </form>

                    <div class="mt-8 pt-6 border-t font-bold border-gray-100">
                        <form method="POST">
                            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 text-center">Missing icons?</h3>
                            <button type="submit" name="restore_defaults" class="w-full bg-gray-50 hover:bg-gray-100 text-gray-400 border-2 border-gray-200 font-black py-2 rounded-xl text-[10px] uppercase tracking-tighter transition-all" onclick="return confirm('Load default avatars?')">
                                🔄 RESTORE DEFAULT PACK
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="lg:col-span-3">
                <form method="POST" id="bulk_delete_form">
                <div class="bg-white rounded-2xl border-2 border-ink overflow-hidden shadow-[8px_8px_0px_rgba(0,0,0,0.05)]">
                    <div class="p-6 border-b-2 border-gray-100 bg-gray-50/50 flex flex-wrap justify-between items-center gap-4">
                        <div class="flex items-center gap-4">
                            <h2 class="font-black text-xl text-ink">Gallery <span class="bg-ink text-white text-[10px] px-2 py-0.5 rounded-full ml-1"><?= mysqli_num_rows($res) ?></span></h2>
                            <label class="flex items-center gap-2 cursor-pointer group select-none">
                                <input type="checkbox" onclick="toggleAll(this)" class="w-4 h-4 rounded border-2 border-ink accent-ink">
                                <span class="text-xs font-black text-gray-400 group-hover:text-ink uppercase tracking-widest">Select All</span>
                            </label>
                        </div>

                        <button type="submit" name="bulk_delete" onclick="return confirm('Delete selected avatars?')" class="bg-pop-red hover:bg-red-400 border-2 border-ink text-ink px-4 py-2 rounded-xl font-black text-xs shadow-[4px_4px_0px_#000] active:shadow-none active:translate-y-1 transition-all">
                            🗑️ Delete Selected
                        </button>
                    </div>
                    
                    <div class="p-8">
                        <?php if(mysqli_num_rows($res) == 0): ?>
                            <div class="text-center py-20 grayscale">
                                <div class="text-6xl mb-4">🤷</div>
                                <h3 class="text-xl font-black text-ink mb-1">No avatars in library</h3>
                                <p class="text-gray-400 text-sm font-bold uppercase tracking-tighter">Add some emojis to get started</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 gap-4">
                                <?php while($row = mysqli_fetch_assoc($res)): ?>
                                    <label class="group relative aspect-square bg-gray-50 hover:bg-white border-2 border-gray-100 hover:border-ink rounded-2xl flex items-center justify-center text-4xl cursor-pointer transition-all hover:scale-105 hover:shadow-[4px_4px_0px_#000] select-none">
                                        <input type="checkbox" name="selected_ids[]" value="<?= $row['id'] ?>" class="absolute top-2 left-2 w-4 h-4 accent-ink z-10 opacity-0 group-hover:opacity-100 checked:opacity-100 transition-opacity">
                                        
                                        <span class="transform group-hover:scale-110 group-hover:-rotate-3 transition-transform"><?= $row['emoji'] ?></span>
                                        
                                        <!-- Delete X -->
                                        <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete?')" class="absolute -top-2 -right-2 w-6 h-6 bg-pop-red text-ink border-2 border-ink rounded-full flex items-center justify-center text-[10px] font-black opacity-0 group-hover:opacity-100 hover:bg-red-400 transition-all shadow-[2px_2px_0px_#000] z-20">✕</a>
                                    </label>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-4 bg-gray-50 border-t-2 border-gray-100 text-center">
                         <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">End of Gallery</span>
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

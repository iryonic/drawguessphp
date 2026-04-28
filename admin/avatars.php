<?php
/**
 * Manage Avatars - Admin Module
 * Fully Migrated to PDO & Secure MVC Architecture.
 */
require_once 'auth.php';
require_once '../api/db.php';
checkAdmin();

$msg = null;
$error = null;
$token = getCSRFToken();

// 1. Add / Import Avatars
if (isset($_POST['add_avatars'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security mismatch.";
    } else {
        $raw = $_POST['emoji_input'] ?? '';
        // Split string into individual unicode characters/emojis
        $chars = preg_split('//u', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $added = 0;
        
        foreach ($chars as $char) {
            $e = trim($char);
            if (empty($e) || $e === ',' || $e === "\n" || $e === "\r") continue;
            
            try {
                DB::query("INSERT INTO avatars (emoji) VALUES (?)", [$e]);
                $added++;
            } catch (Exception $ex) {}
        }
        $msg = "Success: Added $added new avatars!";
    }
}

// 2. Restore Defaults
if (isset($_POST['restore_defaults'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security mismatch.";
    } else {
        $defaults = ['🐱', '🐶', '🦁', '🦊', '🐸', '🐼', '🐨', '🐷', '🐵', '🦄', '🐙', '👾', '🤖', '👽', '👻', '🤡', '🤠', '🎃', '💀', '💩', '🌞', '🌈', '🍕', '🍔', '🥑', '🌮', '🔥', '✨'];
        foreach ($defaults as $av) {
            try { DB::query("INSERT INTO avatars (emoji) VALUES (?)", [$av]); } catch (Exception $e) {}
        }
        $msg = "Default pack successfully integrated.";
    }
}

// 3. Delete Actions
if (isset($_POST['delete_action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security mismatch.";
    } else {
        $ids = isset($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            DB::query("DELETE FROM avatars WHERE id IN ($placeholders)", $ids);
            $msg = "Removed " . count($ids) . " avatars from the library.";
        }
    }
}

$res = DB::fetchAll("SELECT * FROM avatars ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avatar Central - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Fredoka', 'sans-serif'] }, colors: { ink: '#1e1e1e' } } } }
    </script>
</head>
<body class="bg-gray-50 min-h-screen pb-12">
    
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 max-w-5xl">
        <h1 class="text-2xl sm:text-3xl font-black text-ink mb-1">Avatar Library</h1>
        <p class="text-gray-400 font-bold text-[10px] sm:text-xs uppercase tracking-widest mb-6 sm:mb-10">Manage player icons and default packs</p>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 sm:gap-8">
            <!-- Add UI -->
            <div class="lg:col-span-1 space-y-4 sm:space-y-6">
                <div class="bg-white p-5 sm:p-6 rounded-2xl border-2 border-ink shadow-[4px_4px_0px_#000]">
                    <h2 class="text-base sm:text-lg font-black mb-4 sm:mb-6">➕ Import</h2>
                    <?php if($msg): ?>
                        <div class="bg-green-100 text-green-700 p-3 rounded-xl mb-4 text-[10px] sm:text-xs font-bold border-2 border-green-200"><?= $msg ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= $token ?>">
                        <textarea name="emoji_input" rows="3" placeholder="Paste: 🦄 🐶 🦁" class="w-full border-2 border-gray-100 rounded-xl p-3 sm:p-4 text-2xl sm:text-3xl focus:border-ink outline-none font-bold resize-none" required></textarea>
                        <button type="submit" name="add_avatars" class="w-full bg-pop-blue border-2 border-ink text-white font-black py-2.5 sm:py-3 rounded-xl text-xs sm:text-sm shadow-[4px_4px_0px_#000] active:shadow-none active:translate-y-1 transition-all">
                            IMPORT AVATARS
                        </button>
                    </form>

                    <form method="POST" class="mt-8 sm:mt-12 pt-5 sm:pt-6 border-t-2 border-gray-100 border-dashed">
                        <input type="hidden" name="csrf_token" value="<?= $token ?>">
                        <button type="submit" name="restore_defaults" class="w-full bg-gray-50 text-gray-400 border-2 border-gray-200 font-black py-2 rounded-xl text-[9px] sm:text-[10px] uppercase hover:bg-gray-100 transition-all">
                            🔄 Load Starter Pack
                        </button>
                    </form>
                </div>
            </div>

            <!-- View Grid -->
            <div class="lg:col-span-3">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $token = getCSRFToken() ?>">
                    <div class="bg-white rounded-2xl border-2 border-ink overflow-hidden shadow-[8px_8px_0px_rgba(0,0,0,0.05)]">
                        <div class="p-4 sm:p-6 border-b-2 border-gray-100 bg-gray-100/50 flex flex-col sm:flex-row gap-4 justify-between items-center text-center sm:text-left">
                            <h2 class="font-black text-lg sm:text-xl text-ink">Gallery <span class="bg-ink text-white text-[9px] sm:text-[10px] px-2 py-0.5 rounded-full ml-1"><?= count($res) ?></span></h2>
                            <button type="submit" name="delete_action" onclick="return confirm('Remove icons?')" class="w-full sm:w-auto bg-pop-red border-2 border-ink text-ink px-4 py-2 rounded-xl font-black text-[10px] sm:text-xs shadow-[3px_3px_0px_#000] active:shadow-none active:translate-y-1 transition-all uppercase tracking-widest">
                                🗑️ Clear Selected
                            </button>
                        </div>
                        
                        <div class="p-4 sm:p-8 grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-9 gap-2 sm:gap-4 max-h-[500px] sm:max-h-[600px] overflow-y-auto no-scrollbar">
                            <?php foreach($res as $row): ?>
                                <label class="group relative aspect-square bg-gray-50 border-2 border-gray-100 hover:border-ink rounded-lg sm:rounded-xl flex items-center justify-center text-2xl sm:text-4xl cursor-pointer transition-all hover:scale-105 hover:bg-white select-none">
                                    <input type="checkbox" name="selected_ids[]" value="<?= $row['id'] ?>" class="absolute top-1 left-1 sm:top-2 sm:left-2 w-3 h-3 sm:w-4 sm:h-4 accent-ink z-10 opacity-0 group-hover:opacity-100 checked:opacity-100 transition-opacity">
                                    <span class="transform group-hover:scale-110 group-hover:-rotate-3 transition-transform"><?= $row['emoji'] ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

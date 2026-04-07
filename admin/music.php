<?php
/**
 * Manage Music - Admin Module
 * Fully Migrated to PDO & Secure MVC Architecture.
 */
require_once 'auth.php';
require_once '../api/db.php';
checkAdmin();

$msg = "";
$error = "";
$token = getCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security mismatch.";
    } else {
        $enabled = isset($_POST['enabled']) ? '1' : '0';
        $url = trim($_POST['url'] ?? '');
        $volume = floatval($_POST['volume'] ?? 0.3);

        // Handle File Upload
        if (!empty($_FILES['music_file']['name'])) {
            $file = $_FILES['music_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if ($ext !== 'mp3') {
                $error = "Security: Only MP3 files are permitted.";
            } else {
                $target_dir = "../assets/music/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                
                $filename = "lobby_" . time() . ".mp3";
                $target_path = $target_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $url = "assets/music/" . $filename;
                    $msg = "Success: Audio asset uploaded and active.";
                } else {
                    $error = "System: Failed to store audio asset.";
                }
            }
        }

        if (!$error) {
            try {
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([$enabled, 'lobby_music_enabled']);
                $stmt->execute([$url, 'lobby_music_url']);
                $stmt->execute([$volume, 'music_volume']);
                if (!$msg) $msg = "Settings synchronized successfully!";
            } catch (Exception $e) {
                $error = "Database: Synchronization failed.";
            }
        }
    }
}

// Fetch all settings
$res = DB::fetchAll("SELECT * FROM settings");
$settings = [];
foreach($res as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Normalizing defaults just in case
if (!isset($settings['lobby_music_enabled'])) $settings['lobby_music_enabled'] = '0';
if (!isset($settings['lobby_music_url'])) $settings['lobby_music_url'] = '';
if (!isset($settings['music_volume'])) $settings['music_volume'] = '0.3';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audio Console - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Fredoka', 'sans-serif'] },
                    colors: { 
                        ink: '#1e1e1e',
                        'pop-yellow': '#ffeb3b',
                        'pop-blue': '#4fc3f7',
                        'pop-pink': '#ff80ab'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen pb-12">
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 max-w-2xl">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-ink italic">Audio Console</h1>
            <p class="text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Configure atmospheric background music</p>
        </div>

        <div class="bg-white border-[3px] border-ink p-8 rounded-3xl shadow-[10px_10px_0px_#000] relative overflow-hidden">
            <?php if($msg): ?>
                <div class="bg-green-50 text-green-700 p-4 border-2 border-green-500 rounded-2xl mb-8 font-black flex items-center gap-3">
                    ✨ <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="bg-red-50 text-red-700 p-4 border-2 border-red-500 rounded-2xl mb-8 font-black flex items-center gap-3">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                
                <!-- Toggle -->
                <label class="flex items-center justify-between p-5 bg-gray-50 border-2 border-dashed border-gray-200 rounded-2xl hover:border-ink transition-colors cursor-pointer select-none group">
                    <div>
                        <h3 class="font-black text-ink uppercase tracking-tight group-hover:marker-highlight">Auto-Play BGM</h3>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-none mt-1">Lobby & Game Break Atmosphere</p>
                    </div>
                    <div class="relative inline-flex items-center">
                        <input type="checkbox" name="enabled" class="sr-only peer" <?= $settings['lobby_music_enabled'] == '1' ? 'checked' : '' ?>>
                        <div class="w-14 h-8 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-pop-blue border-2 border-ink"></div>
                    </div>
                </label>

                <!-- File Upload -->
                <div class="p-6 border-2 border-ink border-dashed rounded-2xl bg-yellow-50/30 relative">
                    <input type="file" name="music_file" id="music_file" accept=".mp3" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                    <div class="text-center group">
                        <span id="upload-icon" class="text-4xl block mb-2 group-hover:scale-110 transition-transform">🎵</span>
                        <p id="upload-label" class="text-sm font-black text-ink uppercase">Upload MP3 Library</p>
                        <p id="upload-meta" class="text-[9px] text-gray-400 font-bold uppercase mt-1">System Limit: <?= ini_get('upload_max_filesize') ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="h-0.5 bg-gray-100 flex-1"></div>
                    <span class="text-[9px] font-black text-gray-300 uppercase tracking-widest">or stream URL</span>
                    <div class="h-0.5 bg-gray-100 flex-1"></div>
                </div>

                <!-- URL -->
                <input type="text" name="url" value="<?= htmlspecialchars($settings['lobby_music_url']) ?>" class="w-full border-2 border-ink p-4 rounded-2xl font-bold text-sm focus:shadow-[4px_4px_0px_#000] focus:outline-none transition-all" placeholder="https://host.com/music.mp3">

                <!-- Volume HUD -->
                <div class="bg-gray-50 p-6 rounded-2xl border-2 border-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Master Volume</span>
                        <span id="vol-hud" class="bg-pop-pink px-2 py-1 border border-ink rounded font-black text-xs"><?= $settings['music_volume'] * 100 ?>%</span>
                    </div>
                    <input type="range" name="volume" min="0" max="1" step="0.05" value="<?= $settings['music_volume'] ?>" 
                           oninput="document.getElementById('vol-hud').innerText = Math.round(this.value * 100) + '%'"
                           class="w-full accent-ink cursor-pointer">
                </div>

                <button type="submit" class="w-full bg-pop-yellow border-2 border-ink py-5 rounded-2xl font-black text-xl italic shadow-[6px_6px_0px_#000] active:translate-y-1 active:shadow-none transition-all">
                    SAVE CONFIGURATION ⚡
                </button>
            </form>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const label = document.getElementById('upload-label');
            const icon = document.getElementById('upload-icon');
            const meta = document.getElementById('upload-meta');
            
            if (input.files && input.files[0]) {
                const name = input.files[0].name;
                label.innerText = name;
                label.classList.add('text-pop-blue');
                icon.innerText = "📁";
                meta.innerText = "READY TO UPLOAD";
                meta.classList.add('text-green-500');
            } else {
                label.innerText = "Upload MP3 Library";
                label.classList.remove('text-pop-blue');
                icon.innerText = "🎵";
                meta.innerText = "System Limit: <?= ini_get('upload_max_filesize') ?>";
                meta.classList.remove('text-green-500');
            }
        }
    </script>
</body>
</html>

<?php
require_once 'auth.php';
checkAdmin();

$msg = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['enabled']) ? '1' : '0';
    $url = sanitize($conn, $_POST['url']);
    $volume = floatval($_POST['volume']);

    // Handle Upload
    if (!empty($_FILES['music_file']['name'])) {
        $file = $_FILES['music_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'mp3') {
            $error = "Only MP3 files are allowed.";
        } else {
            $target_dir = "../assets/music/";
            $filename = "lobby_" . time() . ".mp3";
            $target_path = $target_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $url = "assets/music/" . $filename;
                $msg = "File uploaded and settings updated!";
            } else {
                $error = "Failed to upload file.";
            }
        }
    }

    if (!$error) {
        mysqli_query($conn, "UPDATE settings SET setting_value = '$enabled' WHERE setting_key = 'lobby_music_enabled'");
        mysqli_query($conn, "UPDATE settings SET setting_value = '$url' WHERE setting_key = 'lobby_music_url'");
        mysqli_query($conn, "UPDATE settings SET setting_value = '$volume' WHERE setting_key = 'music_volume'");
        if (!$msg) $msg = "Settings Updated Successfully!";
    }
}

$res = mysqli_query($conn, "SELECT * FROM settings");
$settings = [];
while($row = mysqli_fetch_assoc($res)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Music - Admin</title>
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
            <h1 class="text-3xl font-black text-ink italic">Audio Control</h1>
            <p class="text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Configure lobby background music & atmosphere</p>
        </div>

        <div class="bg-white border-[3px] border-ink p-8 rounded-3xl shadow-[8px_8px_0px_#000] relative overflow-hidden">
            <!-- Decoration -->
            <div class="absolute top-0 right-0 p-4 opacity-10 pointer-events-none">
                <span class="text-6xl">🎵</span>
            </div>

            <?php if($msg): ?>
                <div class="bg-green-100 text-green-700 p-4 border-2 border-green-500 rounded-2xl mb-8 font-black flex items-center gap-3">
                    <span class="text-xl">✨</span> <?= $msg ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="bg-red-50 text-red-700 p-4 border-2 border-red-500 rounded-2xl mb-8 font-black flex items-center gap-3">
                    <span class="text-xl">⚠️</span> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <!-- Toggle -->
                <label class="flex items-center justify-between p-4 bg-gray-50 border-2 border-dashed border-gray-200 rounded-2xl group hover:border-ink transition-colors cursor-pointer select-none">
                    <div>
                        <h3 class="font-black text-ink uppercase tracking-tight">Enable Lobby Music</h3>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-none mt-1">Plays during assembly & results</p>
                    </div>
                    <div class="relative inline-flex items-center">
                        <input type="checkbox" name="enabled" class="sr-only peer" <?= $settings['lobby_music_enabled'] == '1' ? 'checked' : '' ?>>
                        <div class="w-14 h-8 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-pop-blue border-2 border-ink"></div>
                    </div>
                </label>

                <!-- Upload -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 ml-1">Native Upload (MP3)</label>
                    <div class="bg-yellow-50 p-6 border-2 border-ink border-dashed rounded-2xl relative group hover:bg-yellow-10/50 transition-all">
                        <input type="file" name="music_file" accept=".mp3" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="text-center">
                            <span class="text-4xl group-hover:scale-110 transition-transform inline-block mb-2">📤</span>
                            <p class="text-sm font-black text-ink uppercase tracking-tighter">Click to browse or drop MP3</p>
                            <p class="text-[10px] text-gray-400 font-bold uppercase mt-1 italic">Max size: <?= ini_get('upload_max_filesize') ?></p>
                        </div>
                    </div>
                </div>

                <!-- Separator -->
                <div class="relative flex py-2 items-center">
                    <div class="flex-grow border-t-2 border-gray-100"></div>
                    <span class="flex-shrink mx-4 text-[10px] font-black text-gray-300 uppercase tracking-[0.3em]">OR USE URL</span>
                    <div class="flex-grow border-t-2 border-gray-100"></div>
                </div>

                <!-- URL -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1 italic">External Stream Link</label>
                    <input type="text" name="url" value="<?= htmlspecialchars($settings['lobby_music_url']) ?>" class="w-full border-2 border-ink p-4 rounded-2xl font-bold text-sm focus:outline-none focus:shadow-[4px_4px_0px_#000] transition-all bg-white" placeholder="https://example.com/audio.mp3">
                </div>

                <!-- Volume -->
                <div>
                    <div class="flex justify-between items-end mb-3 ml-1">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none">Global Volume</label>
                        <span id="vol-label" class="text-xs font-black text-ink bg-pop-pink px-2 py-0.5 rounded border border-ink"><?= $settings['music_volume'] * 100 ?>%</span>
                    </div>
                    <div class="bg-gray-50 p-6 rounded-2xl border-2 border-gray-100">
                        <input type="range" name="volume" min="0" max="1" step="0.05" value="<?= $settings['music_volume'] ?>" 
                            oninput="document.getElementById('vol-label').innerText = Math.round(this.value * 100) + '%'"
                            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-ink">
                        <div class="flex justify-between mt-2">
                            <span class="text-[9px] font-black text-gray-300 uppercase">Whisper</span>
                            <span class="text-[9px] font-black text-gray-300 uppercase">Blast</span>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="w-full bg-pop-yellow hover:bg-yellow-300 border-[3px] border-ink py-5 rounded-2xl font-black text-xl italic hover:scale-[1.01] active:translate-y-1 active:shadow-none transition-all shadow-[6px_6px_0px_#000]">
                    APPLY CHANGES ⚡
                </button>
            </form>
        </div>
    </div>
</body>
</html>

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
    <title>Manage Music - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;600;700&display=swap" rel="stylesheet">
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

    <div class="container mx-auto px-4 max-w-2xl">
        <div class="bg-white border-4 border-ink p-8 rounded-2xl shadow-[8px_8px_0px_#1e1e1e]">
            <h2 class="text-3xl font-black mb-6">🎵 Lobby Music Settings</h2>

            <?php if($msg): ?>
                <div class="bg-green-100 text-green-700 p-4 border-2 border-green-500 rounded-xl mb-6 font-bold">
                    <?= $msg ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="bg-red-100 text-red-700 p-4 border-2 border-red-500 rounded-xl mb-6 font-bold">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-6">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="enabled" class="w-6 h-6 border-2 border-ink rounded appearance-none checked:bg-yellow-400" <?= $settings['lobby_music_enabled'] == '1' ? 'checked' : '' ?>>
                        <span class="text-xl font-bold">Enable Background Music</span>
                    </label>
                    <p class="text-gray-400 text-sm mt-1 ml-9 italic">Plays during lobby and wait times.</p>
                </div>

                <div class="mb-6 bg-yellow-50 p-4 border-2 border-dashed border-yellow-200 rounded-xl">
                    <label class="block font-bold text-ink mb-2">Upload New Song (MP3)</label>
                    <input type="file" name="music_file" accept=".mp3" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-2 file:border-ink file:text-sm file:font-bold file:bg-yellow-100 file:text-ink hover:file:bg-yellow-200 transition-all cursor-pointer">
                    <p class="text-[10px] text-gray-400 mt-2 uppercase font-bold">Max size depends on your PHP config</p>
                </div>

                <div class="text-center my-4 font-black text-gray-300">--- OR ---</div>

                <div class="mb-6">
                    <label class="block font-bold text-gray-700 mb-2">MP3 Stream URL</label>
                    <input type="text" name="url" value="<?= htmlspecialchars($settings['lobby_music_url']) ?>" class="w-full border-2 border-ink p-3 rounded-xl focus:outline-none focus:shadow-[4px_4px_0px_#1e1e1e] transition-shadow" placeholder="https://example.com/music.mp3">
                </div>

                <div class="mb-8">
                    <label class="block font-bold text-gray-700 mb-2">Default Volume (0.0 to 1.0)</label>
                    <input type="range" name="volume" min="0" max="1" step="0.1" value="<?= $settings['music_volume'] ?>" class="w-full accent-ink">
                    <div class="flex justify-between text-xs font-bold text-gray-400 mt-1">
                        <span>Min</span>
                        <span>Max</span>
                    </div>
                </div>

                <button type="submit" class="w-full bg-yellow-400 border-4 border-ink py-4 rounded-xl font-black text-xl hover:bg-yellow-300 active:translate-y-1 transition-all shadow-[4px_4px_0px_#1e1e1e]">
                    SAVE SETTINGS
                </button>
            </form>
        </div>
    </div>
</body>
</html>

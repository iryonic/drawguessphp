<?php
require_once '../api/db.php';
require_once 'auth.php'; // Ensure admin is logged in

$admin_id = $_SESSION['admin_id'] ?? 0;
$success = "";
$error = "";

// Fetch current admin
$admin = DB::fetch("SELECT * FROM admins WHERE id = ?", [$admin_id]);

$token = getCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security mismatch. Please try again.";
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_username = trim($_POST['username'] ?? '');
        $new_password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($current_password, $admin['password_hash'] ?? '')) {
            $error = "Current password verification failed.";
        } elseif (empty($new_username)) {
            $error = "Username cannot be empty.";
        } else {

        try {
            // Update Username
            DB::query("UPDATE admins SET username = ? WHERE id = ?", [$new_username, $admin_id]);
            $_SESSION['admin_username'] = $new_username;
            
            // Update Password if provided
            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    $error = "Passwords do not match.";
                } elseif (strlen($new_password) < 6) {
                    $error = "Password must be at least 6 characters.";
                } else {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    DB::query("UPDATE admins SET password_hash = ? WHERE id = ?", [$hash, $admin_id]);
                    $success = "Profile updated successfully (including password).";
                }
            } else {
                $success = "Username updated successfully.";
            }

            if (!$error) {
                // Regenerate session ID and clear the old CSRF token.
                // This prevents "security mismatch" on other admin pages
                // (like music.php) after a password change.
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
            }

            // Refresh local admin data
            $admin = DB::fetch("SELECT * FROM admins WHERE id = ?", [$admin_id]);
            
        } catch (Exception $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Draw & Guess</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Fredoka', sans-serif; background-color: #f7f9fc; color: #1e1e1e; }
        .neo-border { border: 3px solid #1e1e1e; box-shadow: 4px 4px 0px #1e1e1e; }
        .neo-btn { border: 3px solid #1e1e1e; box-shadow: 4px 4px 0px #1e1e1e; transition: all 0.1s; }
        .neo-btn:active { transform: translate(2px, 2px); box-shadow: 2px 2px 0px #1e1e1e; }
        .ink { color: #1e1e1e; }
    </style>
</head>
<body class="p-6">
    <?php include 'nav.php'; ?>

    <div class="container mx-auto max-w-lg">
        <div class="bg-white neo-border p-8 rounded-3xl">
            <h2 class="text-3xl font-black uppercase tracking-tighter mb-8 flex items-center gap-3">
                <span class="bg-pop-purple p-2 rounded-xl text-3xl">👤</span> Manage Profile
            </h2>

            <?php if ($success): ?>
                <div class="bg-pop-green border-2 border-ink px-4 py-3 rounded-xl mb-6 font-bold text-sm">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-pop-red border-2 border-ink px-4 py-3 rounded-xl mb-6 font-bold text-sm">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">

                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Current Password <span class="text-pop-red">*</span></label>
                    <div class="relative">
                        <input type="password" name="current_password" id="current_password" required
                            class="w-full bg-gray-100 border-[3px] border-ink rounded-xl px-4 py-3 pr-12 font-bold focus:outline-none focus:ring-4 focus:ring-pop-purple/20 transition-all"
                            placeholder="Required to save changes">
                        <button type="button" onclick="togglePwd('current_password', 'eye0')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-ink transition-colors">
                            <span id="eye0">👁️</span>
                        </button>
                    </div>
                </div>

                <div class="pt-4 border-t-2 border-ink/5">
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($admin['username'] ?? '') ?>" 
                        class="w-full bg-gray-50 border-[3px] border-ink rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-4 focus:ring-pop-purple/20 transition-all">
                </div>

                <div class="pt-4 border-t-2 border-ink/5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-300 mb-4 italic">Leave blank to keep current password</p>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">New Password</label>
                            <div class="relative">
                                <input type="password" name="password" id="new_password"
                                    class="w-full bg-gray-50 border-[3px] border-ink rounded-xl px-4 py-3 pr-12 font-bold focus:outline-none focus:ring-4 focus:ring-pop-purple/20 transition-all">
                                <button type="button" onclick="togglePwd('new_password', 'eye1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-ink transition-colors">
                                    <span id="eye1">👁️</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Confirm Password</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirm_password"
                                    class="w-full bg-gray-50 border-[3px] border-ink rounded-xl px-4 py-3 pr-12 font-bold focus:outline-none focus:ring-4 focus:ring-pop-purple/20 transition-all">
                                <button type="button" onclick="togglePwd('confirm_password', 'eye2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-ink transition-colors">
                                    <span id="eye2">👁️</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full neo-btn py-4 bg-pop-purple text-white font-black text-lg uppercase tracking-widest rounded-2xl hover:bg-pop-purple/90 transition-colors mt-4">
                    Save Changes 💾
                </button>
            </form>
        </div>

        <div class="mt-8 text-center">
            <p class="text-xs font-black text-gray-400 uppercase tracking-widest">DrawGuess Admin Panel v2.0</p>
        </div>
    </div>

    <script>
        function togglePwd(inputId, eyeId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(eyeId);
            if (input.type === 'password') {
                input.type = 'text';
                eye.innerText = '🙈';
            } else {
                input.type = 'password';
                eye.innerText = '👁️';
            }
        }
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        pop: {
                            yellow: '#ffeb3b',
                            blue: '#4fc3f7',
                            pink: '#ff80ab',
                            purple: '#ce93d8',
                            green: '#b9f6ca',
                            red: '#ff8a80'
                        },
                        ink: '#1e1e1e'
                    }
                }
            }
        }
    </script>
</body>
</html>

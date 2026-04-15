<?php
require_once '../api/db.php';
require_once 'auth.php'; // Ensure admin is logged in

$feedbacks = DB::fetchAll("SELECT * FROM feedbacks ORDER BY created_at DESC");

// Simple metrics
$total = count($feedbacks);
$avg_rating = $total > 0 ? (DB::fetch("SELECT AVG(rating) as avg FROM feedbacks")['avg']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Feedbacks - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Fredoka', sans-serif; background-color: #f7f9fc; color: #1e1e1e; }
        .neo-border { border: 3px solid #1e1e1e; box-shadow: 4px 4px 0px #1e1e1e; }
        .ink { color: #1e1e1e; }
    </style>
</head>
<body class="p-6">
    <?php include 'nav.php'; ?>

    <div class="container mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-end mb-8 gap-4">
            <div>
                <h2 class="text-4xl font-black tracking-tighter uppercase mb-2">User Feedback</h2>
                <div class="flex items-center gap-4">
                    <span class="bg-pop-blue border-2 border-ink px-3 py-1 rounded-full text-xs font-black uppercase">Total: <?= $total ?></span>
                    <span class="bg-pop-yellow border-2 border-ink px-3 py-1 rounded-full text-xs font-black uppercase">Avg Rating: <?= number_format($avg_rating, 1) ?> ★</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if ($total == 0): ?>
                <div class="col-span-full bg-white neo-border p-12 text-center">
                    <div class="text-6xl mb-4">🏜️</div>
                    <p class="font-black text-xl text-gray-400 uppercase tracking-widest">No feedback received yet.</p>
                </div>
            <?php else: ?>
                <?php foreach($feedbacks as $f): 
                    $date = date('M j, Y H:i', strtotime($f['created_at']));
                    $rating_color = $f['rating'] >= 4 ? 'bg-pop-green' : ($f['rating'] >= 3 ? 'bg-pop-yellow' : 'bg-pop-red');
                ?>
                <div class="bg-white neo-border p-6 flex flex-col relative transition-all hover:-translate-y-1 hover:shadow-[6px_6px_0px_#1e1e1e]">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-xl font-black border-2 border-ink">
                                <?= strtoupper(substr($f['username'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-black text-sm uppercase leading-none"><?= htmlspecialchars($f['username']) ?></p>
                                <p class="text-[10px] font-bold text-gray-400 mt-1"><?= $date ?></p>
                            </div>
                        </div>
                        <div class="<?= $rating_color ?> border-2 border-ink px-2 py-0.5 rounded text-[10px] font-black uppercase">
                            <?= $f['rating'] ?> ★
                        </div>
                    </div>
                    
                    <div class="flex-1 bg-gray-50 rounded-xl p-4 border-2 border-ink/5 italic text-sm text-gray-700 leading-relaxed overflow-y-auto max-h-32 no-scrollbar">
                        "<?= htmlspecialchars($f['message']) ?>"
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
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

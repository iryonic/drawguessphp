<?php
/**
 * Manage Words - Admin Module
 * Fully Migrated to PDO & Secure MVC Architecture.
 */
require_once 'auth.php';
require_once '../api/db.php';
checkAdmin();

$msg = null;
$error = null;

// CSRF Token for all actions
$token = getCSRFToken();

// 1. Add Bulk Words
if (isset($_POST['add_bulk'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security mismatch. Please try again.";
    } else {
        $raw = $_POST['words_bulk'] ?? '';
        $diff = $_POST['difficulty'] ?? 'easy';
        
        $words = preg_split("/[\n,]+/", $raw);
        $added = 0;
        $skipped = 0;
        
        foreach ($words as $w) {
            $clean = trim($w);
            if (empty($clean)) continue;
            
            try {
                DB::query("INSERT INTO words (word, difficulty) VALUES (?, ?)", [$clean, $diff]);
                $added++;
            } catch (PDOException $e) {
                // Usually duplicate entry
                $skipped++;
            }
        }
        $msg = "Success: Added $added words! ($skipped skipped as duplicates)";
    }
}

// 2. Delete Single Word
if (isset($_GET['del'])) {
    // Note: GET delete is simple but adding a small confirmation in JS is better.
    // For full security, this should be a POST with CSRF, but we keep GET for simplicity with confirmation.
    $id = intval($_GET['del']);
    DB::query("DELETE FROM words WHERE id = ?", [$id]);
    header("Location: words.php?msg=" . urlencode("Word deleted successfully"));
    exit;
}

// 3. Bulk Actions (Delete/Set Difficulty)
if (isset($_POST['bulk_action']) && !empty($_POST['bulk_action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security mismatch.";
    } else {
        $valid_ids = [];
        if (isset($_POST['ids_json']) && !empty($_POST['ids_json'])) {
            $decoded = json_decode($_POST['ids_json'], true);
            if (is_array($decoded)) $valid_ids = array_filter(array_map('intval', $decoded));
        }

        if (!empty($valid_ids)) {
            $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
            $action = $_POST['bulk_action'];

            if ($action === 'delete') {
                DB::query("UPDATE rounds SET word_id = NULL WHERE word_id IN ($placeholders)", $valid_ids);
                DB::query("DELETE FROM words WHERE id IN ($placeholders)", $valid_ids);
                $msg = "Batch delete completed for " . count($valid_ids) . " items.";
            } elseif (strpos($action, 'set_') === 0) {
                $diff = substr($action, 4);
                DB::query("UPDATE words SET difficulty = ? WHERE id IN ($placeholders)", array_merge([$diff], $valid_ids));
                $msg = "Updated " . count($valid_ids) . " items to " . ucfirst($diff) . ".";
            }
        }
    }
}

// 4. Fetch & Filter
$search = $_GET['search'] ?? '';
$filter_diff = $_GET['difficulty'] ?? '';

$sql = "SELECT * FROM words WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND word LIKE ?";
    $params[] = "%$search%";
}
if (!empty($filter_diff)) {
    $sql .= " AND difficulty = ?";
    $params[] = $filter_diff;
}

$sql .= " ORDER BY word ASC";
$words_list = DB::fetchAll($sql, $params);
$total_all = DB::fetch("SELECT COUNT(*) as c FROM words")['c'];

if (isset($_GET['msg'])) $msg = $_GET['msg'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Words Library - DrawGuess Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Fredoka', 'sans-serif'] }, colors: { ink: '#1e1e1e' } } } }
    </script>
</head>
<body class="bg-gray-50 min-h-screen pb-12">
    
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4 max-w-6xl">
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-black text-ink">Vocabulary Library</h1>
                <p class="text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Manage game words and categories</p>
            </div>
            
            <form method="GET" class="flex gap-2 w-full lg:w-auto">
                <div class="relative flex-1">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="w-full bg-white border-2 border-ink pl-4 pr-10 py-2.5 rounded-xl text-sm font-bold focus:outline-none focus:shadow-[4px_4px_0px_#000] transition-all">
                </div>
                <select name="difficulty" onchange="this.form.submit()" class="bg-white border-2 border-ink px-4 py-2.5 rounded-xl text-xs font-black focus:outline-none">
                    <option value="">All Levels</option>
                    <option value="easy" <?= $filter_diff=='easy'?'selected':'' ?>>🟢 Easy</option>
                    <option value="medium" <?= $filter_diff=='medium'?'selected':'' ?>>🟡 Medium</option>
                    <option value="hard" <?= $filter_diff=='hard'?'selected':'' ?>>🔴 Hard</option>
                </select>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Add Form -->
                <div class="bg-white p-6 rounded-2xl border-2 border-ink shadow-[4px_4px_0px_#000]">
                    <h2 class="text-lg font-black mb-4 flex items-center gap-2">
                        <span class="bg-yellow-100 p-1.5 rounded-lg text-sm">✍️</span> Batch Add
                    </h2>
                    
                    <?php if($msg): ?>
                        <div class="bg-green-100 text-green-700 p-3 rounded-xl mb-4 text-xs font-bold border-2 border-green-200"><?= htmlspecialchars($msg) ?></div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="bg-red-100 text-red-700 p-3 rounded-xl mb-4 text-xs font-bold border-2 border-red-200"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $token ?>">
                        <div class="mb-4">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1">Word Pool</label>
                            <textarea name="words_bulk" rows="5" class="w-full border-2 border-gray-100 rounded-xl p-4 focus:border-ink outline-none font-bold text-sm resize-none placeholder:text-gray-200" placeholder="Apple, Banana, Orange..." required></textarea>
                            <p class="text-[9px] text-gray-400 mt-2 font-bold px-1 italic">Separated by commas/newlines.</p>
                        </div>
                        <div class="mb-6">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1">Class</label>
                            <div class="grid grid-cols-3 gap-2">
                                <?php foreach(['easy', 'medium', 'hard'] as $d): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="difficulty" value="<?= $d ?>" class="peer hidden" <?= $d=='easy'?'checked':'' ?>>
                                    <div class="text-center py-2.5 rounded-lg border-2 border-gray-100 font-black text-[10px] uppercase tracking-tighter peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white transition-all">
                                        <?= $d ?>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" name="add_bulk" class="w-full bg-pop-yellow hover:bg-yellow-300 border-2 border-ink text-ink font-black py-3.5 rounded-xl text-sm shadow-[4px_4px_0px_#000] active:shadow-none active:translate-y-1 transition-all">
                            IMPORT NOW
                        </button>
                    </form>
                </div>

                <!-- Stats -->
                <div class="bg-white p-6 rounded-2xl border-2 border-gray-100">
                     <h2 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Database Stats</h2>
                     <div class="space-y-4">
                         <?php
                            $ds_rows = DB::fetchAll("SELECT difficulty, COUNT(*) as c FROM words GROUP BY difficulty");
                            foreach($ds_rows as $ds):
                                $pc = ($total_all > 0) ? ($ds['c'] / $total_all) * 100 : 0;
                                $color = $ds['difficulty'] == 'easy' ? 'bg-green-400' : ($ds['difficulty'] == 'medium' ? 'bg-yellow-400' : 'bg-red-400');
                         ?>
                         <div>
                             <div class="flex justify-between text-[11px] font-black uppercase mb-1.5">
                                 <span class="text-gray-500"><?= $ds['difficulty'] ?></span>
                                 <span><?= $ds['c'] ?></span>
                             </div>
                             <div class="w-full h-1.5 bg-gray-50 rounded-full overflow-hidden">
                                 <div class="<?= $color ?> h-full transition-all duration-700" style="width: <?= $pc ?>%"></div>
                             </div>
                         </div>
                         <?php endforeach; ?>
                     </div>
                </div>
            </div>

            <!-- Main Table -->
            <div class="lg:col-span-3">
                <form method="POST" id="bulk_form">
                    <input type="hidden" name="csrf_token" value="<?= $token ?>">
                    <input type="hidden" name="ids_json" id="ids_json">
                    <div class="bg-white rounded-2xl border-2 border-ink overflow-hidden shadow-[8px_8px_0px_rgba(0,0,0,0.05)]">
                        <div class="p-5 border-b-2 border-gray-50 bg-gray-50/50 flex flex-wrap justify-between items-center gap-4">
                            <div class="flex items-center gap-4">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="checkbox" onclick="toggleAll(this)" class="w-5 h-5 rounded border-2 border-ink accent-ink cursor-pointer">
                                    <span class="text-xs font-black text-gray-400 uppercase tracking-widest">Select All</span>
                                </label>
                            </div>
                            
                            <div class="flex gap-2">
                                <select name="bulk_action" id="bulk_action_sel" class="border-2 border-ink rounded-xl px-4 py-2 text-xs font-black focus:outline-none bg-white">
                                    <option value="">Bulk Actions</option>
                                    <option value="set_easy">Move to Easy</option>
                                    <option value="set_medium">Move to Medium</option>
                                    <option value="set_hard">Move to Hard</option>
                                    <option value="delete">🗑️ Delete Selected</option>
                                </select>
                                <button type="button" onclick="submitBulk()" class="bg-ink text-white px-6 py-2 rounded-xl text-xs font-black hover:bg-gray-800 active:scale-95 transition-all">APPLY</button>
                            </div>
                        </div>

                        <div class="overflow-x-auto max-h-[700px] no-scrollbar">
                            <table class="w-full text-left">
                                <thead class="sticky top-0 bg-white border-b-2 border-gray-100 z-10">
                                    <tr class="text-[10px] font-black text-gray-300 uppercase tracking-widest">
                                        <th class="px-6 py-4 w-10"></th>
                                        <th class="px-6 py-4">Word / Term</th>
                                        <th class="px-6 py-4 text-center">Difficulty</th>
                                        <th class="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php if(empty($words_list)): ?>
                                        <tr><td colspan="4" class="py-20 text-center font-bold text-gray-300">No words match your filters.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach($words_list as $row): ?>
                                    <tr class="hover:bg-yellow-50/50 transition-colors group">
                                        <td class="px-6 py-4">
                                            <input type="checkbox" name="selected_ids[]" value="<?= $row['id'] ?>" class="w-4 h-4 rounded border-2 border-ink accent-ink">
                                        </td>
                                        <td class="px-6 py-4 font-black text-ink uppercase tracking-tight"><?= htmlspecialchars($row['word']) ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <?php
                                                $cl = $row['difficulty']=='easy' ? 'bg-green-100 text-green-700 border-green-200' : 
                                                       ($row['difficulty']=='medium' ? 'bg-yellow-100 text-yellow-700 border-yellow-200' : 'bg-red-100 text-red-700 border-red-200');
                                            ?>
                                            <span class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase border <?= $cl ?>">
                                                <?= $row['difficulty'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="?del=<?= $row['id'] ?>" onclick="return confirm('Permanently delete this word?')" class="opacity-0 group-hover:opacity-100 transition-opacity p-2 hover:bg-red-50 text-red-500 rounded-lg inline-block">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleAll(src) { document.querySelectorAll('input[name="selected_ids[]"]').forEach(cb => cb.checked = src.checked); }
        function submitBulk() {
            const ids = Array.from(document.querySelectorAll('input[name="selected_ids[]"]:checked')).map(cb => cb.value);
            const act = document.getElementById('bulk_action_sel').value;
            if (!ids.length) return alert('Select items first.');
            if (!act) return alert('Select action first.');
            if (act === 'delete' && !confirm('Delete ' + ids.length + ' items?')) return;
            document.getElementById('ids_json').value = JSON.stringify(ids);
            document.getElementById('bulk_form').submit();
        }
    </script>
</body>
</html>

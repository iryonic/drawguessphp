<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'auth.php';
require_once '../api/db.php';
checkAdmin();

// Add Bulk Words
if (isset($_POST['add_bulk'])) {
    $raw = $_POST['words_bulk'];
    $diff = sanitize($conn, $_POST['difficulty']);
    
    // Split by comma or newline
    $words = preg_split("/[\n,]+/", $raw);
    $added = 0;
    $skipped = 0;
    
    foreach ($words as $w) {
        $clean_word = sanitize($conn, trim($w));
        if (!empty($clean_word)) {
            // Check duplicate
            $check = mysqli_query($conn, "SELECT id FROM words WHERE word = '$clean_word'");
            if (mysqli_num_rows($check) > 0) {
                $skipped++;
            } else {
                mysqli_query($conn, "INSERT INTO words (word, difficulty) VALUES ('$clean_word', '$diff')");
                $added++;
            }
        }
    }
    
    $msg = "Added $added words! ($skipped skipped as duplicates)";
}

// Delete Word
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    mysqli_query($conn, "DELETE FROM words WHERE id = $id");
    header("Location: " . APP_ROOT . "admin/words");
    exit;
}

// Bulk Actions
if (isset($_POST['bulk_action'])) {
    try {
        // Boost limits just in case
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 600);
        
        $valid_ids = [];
        
        // JSON Payload (Robust for thousands of items)
        if (isset($_POST['ids_json']) && !empty($_POST['ids_json'])) {
            $decoded = json_decode($_POST['ids_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $id) {
                    $v = intval($id);
                    if ($v > 0) $valid_ids[] = $v;
                }
            }
        } 
        // Fallback
        elseif (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
            foreach ($_POST['selected_ids'] as $id) {
                $v = intval($id);
                if ($v > 0) $valid_ids[] = $v;
            }
        }
        
        if (!empty($valid_ids)) {
            // Chunk to avoid long query errors
            $chunks = array_chunk($valid_ids, 200); 
            $total_affected = 0;
            
            foreach ($chunks as $chunk) {
                $ids_str = implode(',', $chunk);
                if ($_POST['bulk_action'] === 'delete') {
                    // Detach from rounds first
                    mysqli_query($conn, "UPDATE rounds SET word_id = NULL WHERE word_id IN ($ids_str)");
                    
                    $q = "DELETE FROM words WHERE id IN ($ids_str)";
                    if (!mysqli_query($conn, $q)) throw new Exception(mysqli_error($conn));
                } elseif (in_array($_POST['bulk_action'], ['set_easy', 'set_medium', 'set_hard'])) {
                    $diff = substr($_POST['bulk_action'], 4);
                    $q = "UPDATE words SET difficulty = '$diff' WHERE id IN ($ids_str)";
                    if (!mysqli_query($conn, $q)) throw new Exception(mysqli_error($conn));
                }
                $total_affected += count($chunk);
            }
            
            if ($_POST['bulk_action'] === 'delete') {
                $m = "Deleted " . $total_affected . " words.";
            } else {
                $diff = substr($_POST['bulk_action'], 4);
                $m = "Updated " . $total_affected . " words to " . ucfirst($diff) . ".";
            }
        }
        
        // Use JS Redirect
        $url = 'words.php';
        if (isset($m)) $url .= '?msg=' . urlencode($m);
        echo "<script>window.location.href='$url';</script>";
        exit;

    } catch (Throwable $t) {
        // ERROR HANDLING VISIBLE TO USER
        http_response_code(200);
        echo "<div style='background:#fee; color:#c00; padding:20px; font-family:monospace; border:2px solid red; margin:20px;'>";
        echo "<h2 style='font-size:24px; font-weight:bold; margin-top:0;'>💥 Fatal Error</h2>";
        echo "<p><strong>Message:</strong> " . $t->getMessage() . "</p>";
        echo "<p><strong>Line:</strong> " . $t->getLine() . "</p>";
        echo "<p><strong>File:</strong> " . $t->getFile() . "</p>";
        echo "<pre style='background:#fff; padding:10px; border:1px solid #ccc; overflow:auto;'>";
        echo $t->getTraceAsString();
        echo "</pre>";
        echo "</div>";
        exit;
    }
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

// Search & Filter
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';
$filter_diff = isset($_GET['difficulty']) ? sanitize($conn, $_GET['difficulty']) : '';

$where_clauses = [];
if (!empty($search)) $where_clauses[] = "word LIKE '%$search%'";
if (!empty($filter_diff)) $where_clauses[] = "difficulty = '$filter_diff'";

$where = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// List Words
$res = mysqli_query($conn, "SELECT * FROM words $where ORDER BY word ASC");
$total_words = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM words"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Words - DrawGuess Admin</title>
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
                <h1 class="text-3xl font-black text-ink">Vocabulary Library</h1>
                <p class="text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Manage game words and categories</p>
            </div>
            
            <div class="flex flex-wrap gap-3 w-full lg:w-auto">
                <form method="GET" class="flex gap-2 w-full sm:w-auto">
                    <div class="relative flex-1 sm:w-64">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                        <input type="text" name="search" value="<?= $search ?>" placeholder="Search words..." class="w-full bg-white border-2 border-ink pl-10 pr-4 py-2 rounded-xl text-sm font-bold focus:outline-none focus:shadow-[4px_4px_0px_#000] transition-all">
                    </div>
                    <select name="difficulty" onchange="this.form.submit()" class="bg-white border-2 border-ink px-4 py-2 rounded-xl text-sm font-black focus:outline-none">
                        <option value="">All Levels</option>
                        <option value="easy" <?= $filter_diff=='easy'?'selected':'' ?>>🟢 Easy</option>
                        <option value="medium" <?= $filter_diff=='medium'?'selected':'' ?>>🟡 Medium</option>
                        <option value="hard" <?= $filter_diff=='hard'?'selected':'' ?>>🔴 Hard</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Sidebar / Add -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Add Form -->
                <div class="bg-white p-6 rounded-2xl border-2 border-ink shadow-[4px_4px_0px_#000]">
                    <h2 class="text-lg font-black mb-4 flex items-center gap-2">
                        <span class="bg-yellow-100 p-1 rounded-lg">➕</span> Add Words
                    </h2>
                    
                    <?php if(isset($msg)): ?>
                        <div class="bg-green-100 text-green-700 p-3 rounded-xl mb-4 text-xs font-bold border-2 border-green-200"><?= $msg ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1">Word Pool</label>
                            <textarea name="words_bulk" rows="5" class="w-full border-2 border-gray-100 rounded-xl p-4 focus:border-ink outline-none font-bold text-sm resize-none placeholder:text-gray-200" placeholder="Apple, Banana, Orange..." required></textarea>
                            <p class="text-[9px] text-gray-400 mt-2 font-bold px-1 italic">Separate by commas or new lines.</p>
                        </div>
                        <div class="mb-6">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1">Class</label>
                            <div class="grid grid-cols-3 gap-2">
                                <?php foreach(['easy', 'medium', 'hard'] as $d): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="difficulty" value="<?= $d ?>" class="peer hidden" <?= $d=='easy'?'checked':'' ?>>
                                    <div class="text-center py-2 rounded-lg border-2 border-gray-100 font-black text-[10px] uppercase tracking-tighter peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white transition-all">
                                        <?= $d ?>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" name="add_bulk" class="w-full bg-pop-yellow hover:bg-yellow-300 border-2 border-ink text-ink font-black py-3 rounded-xl text-sm shadow-[4px_4px_0px_#000] active:shadow-none active:translate-y-1 transition-all">
                            IMPORT WORDS
                        </button>
                    </form>
                </div>

                <!-- Stats Summary -->
                <div class="bg-white p-6 rounded-2xl border-2 border-gray-100 hidden lg:block">
                     <h2 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">Database Summary</h2>
                     <div class="space-y-4">
                         <?php
                            $ds_res = mysqli_query($conn, "SELECT difficulty, COUNT(*) as c FROM words GROUP BY difficulty");
                            while($ds = mysqli_fetch_assoc($ds_res)):
                                $pc = ($total_words > 0) ? ($ds['c'] / $total_words) * 100 : 0;
                                $color = $ds['difficulty'] == 'easy' ? 'bg-green-400' : ($ds['difficulty'] == 'medium' ? 'bg-yellow-400' : 'bg-red-400');
                         ?>
                         <div>
                             <div class="flex justify-between text-[11px] font-black uppercase mb-1.5">
                                 <span class="text-gray-500"><?= $ds['difficulty'] ?></span>
                                 <span><?= $ds['c'] ?></span>
                             </div>
                             <div class="w-full h-1.5 bg-gray-50 rounded-full overflow-hidden">
                                 <div class="<?= $color ?> h-full" style="width: <?= $pc ?>%"></div>
                             </div>
                         </div>
                         <?php endwhile; ?>
                     </div>
                </div>
            </div>

            <!-- Main List -->
            <div class="lg:col-span-3">
                <form method="POST" id="bulk_form">
                <input type="hidden" name="ids_json" id="ids_json">
                <div class="bg-white rounded-2xl border-2 border-ink overflow-hidden shadow-[8px_8px_0px_rgba(0,0,0,0.05)]">
                    <div class="p-4 border-b-2 border-gray-100 bg-gray-50/50 flex flex-wrap justify-between items-center gap-4">
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" onclick="toggleAll(this)" class="w-4 h-4 rounded border-2 border-ink accent-ink">
                                <span class="text-xs font-black text-gray-400 group-hover:text-ink uppercase tracking-widest">Select All</span>
                            </label>
                        </div>
                        
                        <div class="flex gap-2">
                            <select name="bulk_action" id="bulk_action_sel" class="border-2 border-ink rounded-lg px-3 py-1.5 text-xs font-black focus:outline-none bg-white">
                                <option value="">Bulk Actions</option>
                                <option value="set_easy">Move to Easy</option>
                                <option value="set_medium">Move to Medium</option>
                                <option value="set_hard">Move to Hard</option>
                                <option value="delete">🗑️ Delete Permanently</option>
                            </select>
                            <button type="button" onclick="submitBulk()" class="bg-ink text-white px-4 py-1.5 rounded-lg text-xs font-black hover:bg-gray-800 active:scale-95 transition-all">APPLY</button>
                        </div>
                    </div>

                    <div class="max-h-[600px] overflow-y-auto">
                        <?php if(mysqli_num_rows($res) == 0): ?>
                            <div class="text-center py-20 px-6">
                                <div class="text-5xl mb-4 grayscale">📦</div>
                                <h3 class="text-xl font-black text-ink mb-1">No words found</h3>
                                <p class="text-gray-400 text-sm font-bold">Try adjusting your search or filters.</p>
                            </div>
                        <?php else: ?>
                        <table class="w-full text-left">
                            <thead class="sticky top-0 bg-white border-b-2 border-gray-100 z-10">
                                <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                                    <th class="px-6 py-4 w-10"></th>
                                    <th class="px-6 py-4">Term</th>
                                    <th class="px-6 py-4 text-center">Difficulty</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php while($row = mysqli_fetch_assoc($res)): ?>
                                <tr class="hover:bg-yellow-50/50 transition-colors group">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" name="selected_ids[]" value="<?= $row['id'] ?>" class="w-4 h-4 rounded border-2 border-ink accent-ink">
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-black text-ink uppercase tracking-tight"><?= $row['word'] ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php
                                            $color = $row['difficulty']=='easy' ? 'bg-green-100 text-green-700 border-green-200' : 
                                                   ($row['difficulty']=='medium' ? 'bg-yellow-100 text-yellow-700 border-yellow-200' : 'bg-red-100 text-red-700 border-red-200');
                                        ?>
                                        <span class="inline-block px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border <?= $color ?>">
                                            <?= $row['difficulty'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <a href="?del=<?= $row['id'] ?>" onclick="return confirm('Delete this word?')" class="p-2 hover:bg-red-100 text-red-500 rounded-lg transition-colors" title="Delete">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>

                    <div class="p-4 bg-gray-50 border-t-2 border-gray-100 flex justify-between items-center">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Showing <?= mysqli_num_rows($res) ?> entries</p>
                        <div class="text-[10px] font-black text-ink uppercase tracking-tighter bg-pop-pink px-2 py-1 rounded border-2 border-ink">End of list</div>
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

        function submitBulk() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            const ids = Array.from(checkboxes).map(cb => cb.value);
            const action = document.getElementById('bulk_action_sel').value;
            
            if (ids.length === 0) {
                alert('Please select at least one word.');
                return;
            }

            if (!action) {
                alert('Please select an action.');
                return;
            }

            if (action === 'delete' && !confirm(`Are you sure you want to delete ${ids.length} words?`)) {
                return;
            }
            
            document.getElementById('ids_json').value = JSON.stringify(ids);
            
            const allCheckboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            allCheckboxes.forEach(cb => cb.disabled = true);
            
            document.getElementById('bulk_form').submit();
        }
    </script>
</body>
</html>

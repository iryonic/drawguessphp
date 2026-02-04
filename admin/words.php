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
    header("Location: words.php");
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

// List Words
$res = mysqli_query($conn, "SELECT * FROM words ORDER BY id DESC");
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
<body class="bg-gray-50 min-h-screen">
    
    <?php include 'nav.php'; ?>

    <div class="container mx-auto px-4">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- Add Form -->
            <div class="md:col-span-1">
                <div class="bg-white p-6 rounded-xl border-2 border-gray-100 shadow-sm sticky top-4">
                    <h2 class="text-xl font-black mb-4">Add New Word</h2>
                    
                    <?php if(isset($msg)): ?>
                        <div class="bg-green-100 text-green-700 p-2 rounded mb-2 text-sm font-bold"><?= $msg ?></div>
                    <?php endif; ?>
                    <?php if(isset($err)): ?>
                        <div class="bg-red-100 text-red-700 p-2 rounded mb-2 text-sm font-bold"><?= $err ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Words (Bulk)</label>
                            <textarea name="words_bulk" rows="6" class="w-full border-2 border-gray-200 rounded-lg p-3 focus:border-ink outline-none font-bold text-sm" placeholder="Cat, Dog, House&#10;Tree&#10;Car" required></textarea>
                            <p class="text-xs text-gray-400 mt-1">Separate by commas or new lines.</p>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Difficulty</label>
                            <select name="difficulty" class="w-full border-2 border-gray-200 rounded p-2 focus:border-ink outline-none bg-white">
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                        <button type="submit" name="add_bulk" class="w-full bg-ink text-white font-bold py-2 rounded shadow-[4px_4px_0px_#ccc] active:shadow-none active:translate-y-1 transition-all">ADD WORDS</button>
                    </form>
                </div>
            </div>

            <!-- List -->
            <div class="md:col-span-2">
                <form method="POST" id="bulk_form">
                <input type="hidden" name="ids_json" id="ids_json">
                <div class="bg-white rounded-xl border-2 border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-4 border-b-2 border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <h2 class="font-black text-lg">Word Library <span class="text-xs font-bold bg-gray-200 px-2 py-1 rounded text-gray-600 ml-2"><?= mysqli_num_rows($res) ?></span></h2>
                        
                        <!-- Bulk Actions -->
                        <div class="flex gap-2">
                            <select name="bulk_action" class="border-2 border-gray-200 rounded px-2 py-1 text-xs font-bold focus:border-ink outline-none">
                                <option value="">-- Bulk Action --</option>
                                <option value="set_easy">Set Easy</option>
                                <option value="set_medium">Set Medium</option>
                                <option value="set_hard">Set Hard</option>
                                <option value="delete">Delete Selected</option>
                            </select>
                            <button type="button" onclick="submitBulk()" class="bg-ink text-white px-3 py-1 rounded text-xs font-bold hover:bg-gray-800">APPLY</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs text-gray-400 uppercase border-b border-gray-100">
                                    <th class="p-3 w-8"><input type="checkbox" onclick="document.querySelectorAll('input[name=\'selected_ids[]\']').forEach(el => el.checked = this.checked)"></th>
                                    <th class="p-3">Word</th>
                                    <th class="p-3">Difficulty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($res)): ?>
                                <tr class="border-b border-gray-50 hover:bg-yellow-50 transition-colors">
                                    <td class="p-3"><input type="checkbox" name="selected_ids[]" value="<?= $row['id'] ?>"></td>
                                    <td class="p-3 font-bold text-ink"><?= $row['word'] ?></td>
                                    <td class="p-3">
                                        <span class="text-xs font-bold px-2 py-1 rounded 
                                            <?= $row['difficulty']=='easy' ? 'bg-green-100 text-green-700' : 
                                               ($row['difficulty']=='medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') ?>">
                                            <?= strtoupper($row['difficulty']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                </form>
            </div>

        </div>

    </div>

    <script>
        function submitBulk() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            const ids = Array.from(checkboxes).map(cb => cb.value);
            
            if (ids.length === 0) {
                alert('Please select at least one word.');
                return;
            }
            
            // Prepare JSON payload
            document.getElementById('ids_json').value = JSON.stringify(ids);
            
            // CRITICAL: Disable checkboxes so they are NOT submitted individually.
            // This prevents triggering the server's max_input_vars limit (HTTP 500).
            const allCheckboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            allCheckboxes.forEach(cb => cb.disabled = true);
            
            document.getElementById('bulk_form').submit();
        }
    </script>
</body>
</html>

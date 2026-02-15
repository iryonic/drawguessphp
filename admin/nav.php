<nav class="bg-white border-b-[3px] border-ink mb-10 sticky top-0 z-50">
    <div class="container mx-auto px-4 flex flex-col sm:flex-row justify-between items-center py-4">
        <div class="flex items-center gap-8 w-full sm:w-auto justify-between sm:justify-start">
            <h1 class="text-xl font-black text-ink tracking-tighter flex items-center gap-2">
                <span class="bg-pop-yellow px-2 py-0.5 rounded border-2 border-ink">ADMIN</span> ⚡
            </h1>
            
            <div class="hidden md:flex items-center gap-1">
                <?php 
                $current = basename($_SERVER['PHP_SELF']);
                $links = [
                    'index.php' => ['Dashboard', 'admin'],
                    'words.php' => ['Words', 'admin/words'],
                    'avatars.php' => ['Avatars', 'admin/avatars'],
                    'music.php' => ['Music', 'admin/music'],
                ];
                foreach($links as $file => $meta):
                    $active = ($current == $file);
                ?>
                <a href="<?= APP_ROOT . $meta[1] ?>" class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?= $active ? 'bg-ink text-white' : 'text-gray-400 hover:text-ink hover:bg-gray-100' ?>">
                    <?= $meta[0] ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Mobile Menu Toggle (Simplified for now) -->
            <div class="md:hidden">
                 <select onchange="window.location.href=this.value" class="bg-gray-100 border-2 border-ink text-[10px] font-black uppercase px-2 py-1 rounded-lg">
                     <option value="<?= APP_ROOT ?>admin" <?= $current=='index.php'?'selected':'' ?>>Dashboard</option>
                     <option value="<?= APP_ROOT ?>admin/words" <?= $current=='words.php'?'selected':'' ?>>Words</option>
                     <option value="<?= APP_ROOT ?>admin/avatars" <?= $current=='avatars.php'?'selected':'' ?>>Avatars</option>
                     <option value="<?= APP_ROOT ?>admin/music" <?= $current=='music.php'?'selected':'' ?>>Music</option>
                 </select>
            </div>
        </div>

        <div class="flex items-center gap-4 mt-4 sm:mt-0">
            <div class="h-8 w-[2px] bg-gray-100 hidden sm:block"></div>
            <a href="auth.php?logout=1" class="border-2 border-ink px-4 py-2 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-pop-red active:translate-y-0.5 transition-all shadow-[3px_3px_0px_#000]">
                Sign Out
            </a>
        </div>
    </div>
</nav>

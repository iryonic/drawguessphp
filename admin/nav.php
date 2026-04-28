<nav class="bg-white border-b-[3.5px] border-ink mb-6 sm:mb-10 sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between py-3 sm:py-4">
            <h1 class="text-lg sm:text-xl font-black text-ink tracking-tighter flex items-center gap-2 shrink-0">
                <span class="bg-pop-yellow px-2 py-0.5 rounded border-2 border-ink shadow-[2px_2px_0px_#000]">ADMIN</span>
            </h1>
            
            <div class="flex items-center gap-2 sm:gap-4">
                <a href="<?= APP_ROOT ?>admin/auth.php?logout=1" class="border-2 border-ink px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg sm:rounded-xl font-black text-[9px] sm:text-[10px] uppercase tracking-widest hover:bg-pop-red active:translate-y-0.5 transition-all shadow-[2px_2px_0px_#000] sm:shadow-[3px_3px_0px_#000]">
                    Sign Out
                </a>
            </div>
        </div>

        <!-- Horizontal Scroll Nav for All Screens (Modern) -->
        <div class="flex items-center gap-1 overflow-x-auto pb-3 -mx-2 px-2 no-scrollbar border-t border-gray-50 pt-3">
            <?php 
            $current = basename($_SERVER['PHP_SELF']);
            $links = [
                'index.php' => ['Dashboard', 'admin'],
                'rooms.php' => ['Rooms', 'admin/rooms'],
                'words.php' => ['Words', 'admin/words'],
                'avatars.php' => ['Avatars', 'admin/avatars'],
                'music.php' => ['Music', 'admin/music'],
                'feedbacks.php' => ['Feedbacks', 'admin/feedbacks'],
                'profile.php' => ['Profile', 'admin/profile'],
            ];
            foreach($links as $file => $meta):
                $active = ($current == $file);
            ?>
            <a href="<?= APP_ROOT . $meta[1] ?>" class="whitespace-nowrap px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg sm:rounded-xl text-[9px] sm:text-[10px] font-black uppercase tracking-widest transition-all <?= $active ? 'bg-ink text-white shadow-[2px_2px_0px_#4fc3f7]' : 'text-gray-400 hover:text-ink hover:bg-gray-50' ?>">
                <?= $meta[0] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

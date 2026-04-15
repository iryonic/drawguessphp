<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Draw & Guess - Creative Drawing </title>
    <script src="https://cdn.tailwindcss.com"></script>

    <meta name="description" content="Draw & Guess Royale — the free real-time multiplayer drawing and guessing game. Create a room, invite friends and see who can draw and guess the fastest!">
    <meta name="keywords" content="draw and guess, multiplayer drawing game, online skribbl, free guessing game, drawguess royale, real-time drawing">
    <meta name="author" content="Irfan Manzoor">
    <link rel="shortcut icon" href="<?= $base_path ?>assets/pwa/favicon.png" type="image/png">
    <meta name="theme-color" content="#facc15">
    <meta name="keywords" content="draw and guess, multiplayer drawing game, online skribbl, free guessing game, drawguess royale, real-time drawing , irfan manzoor , irfan , iryonints , whatisitiry, beetle system, beetle game">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="<?= $base_path ?>manifest.json">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🖊️</text></svg>">
    <meta name="theme-color" content="#facc15">
    <script>
        const APP_ROOT = '<?= rtrim($base_path, "/") . "/" ?>';
    </script>
    <script src="<?= $base_path ?>js/pwa.js?v=STABLE_V17"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Fredoka', 'sans-serif'] },
                    colors: {
                        paper: '#fdfbf7', ink: '#1e1e1e',
                        pop: { 
                            yellow: '#ffeb3b', 
                            blue: '#4fc3f7', 
                            pink: '#ff80ab', 
                            purple: '#ce93d8', 
                            green: '#b9f6ca',
                            orange: '#ffb74d'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { 
            background-color: #fdfbf7; 
            background-image: radial-gradient(#e5e7eb 1.5px, transparent 1.5px), radial-gradient(#e5e7eb 1.5px, transparent 1.5px); 
            background-size: 24px 24px; 
            background-position: 0 0, 12px 12px; 
            color: #1e1e1e; 
            overflow-x: hidden; 
        }
        .fun-card { background: white; border: 3.5px solid #1e1e1e; box-shadow: 10px 10px 0px #1e1e1e; border-radius: 1.5rem; transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .fun-card:hover { transform: translateY(-2px); box-shadow: 12px 12px 0px #1e1e1e; }
        
        .fun-input { background: #f8f9fa; border: 3px solid #1e1e1e; border-radius: 1rem; box-shadow: 4px 4px 0px #e5e7eb; transition: all 0.2s; }
        .fun-input:focus { background: #fff; box-shadow: 4px 4px 0px #1e1e1e; transform: translate(-1px, -1px); outline: none; }
        
        .btn-pop { border: 3.5px solid #1e1e1e; box-shadow: 5px 5px 0px #1e1e1e; transition: all 0.1s; }
        .btn-pop:hover { transform: translate(-1px, -1px); box-shadow: 7px 7px 0px #1e1e1e; }
        .btn-pop:active { transform: translate(3px, 3px); box-shadow: 2px 2px 0px #1e1e1e; }
        
        .marker-highlight { position: relative; white-space: nowrap; }
        .marker-highlight::before { content: ''; position: absolute; bottom: 5px; left: -5px; right: -5px; height: 12px; background: #ffeb3b; z-index: -1; transform: rotate(-1.5deg); opacity: 0.8; border-radius: 4px; }
        
        .avatar-tile { border: 3px solid #e5e7eb; min-width: 65px; min-height: 65px; display: flex; align-items: center; justify-content: center; font-size: 2rem; border-radius: 1.25rem; background: #fff; cursor: pointer; transition: all 0.2s; }
        .avatar-tile:hover { transform: scale(1.1); border-color: #1e1e1e; }
        .avatar-tile.selected { border-color: #1e1e1e; background: #c1fbb4ff; box-shadow: 4px 4px 0px #1e1e1e; transform: translate(-2px, -2px); }
        .modal-bg { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(5px); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        @keyframes slideUpFade {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-up { animation: slideUpFade 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 py-12">

    <div class="w-full max-w-xl z-10 flex flex-col items-center gap-6 sm:gap-10">
        
        <!-- Header -->
        <div class="text-center space-y-3 sm:space-y-6">
            <div class="inline-block px-4 py-1.5 rounded-full border-[2.5px] md:border-[3px] border-black bg-pop-orange text-black text-[10px] md:text-xs font-black tracking-widest transform -rotate-1 shadow-[3px_3px_0px_#000] md:shadow-[4px_4px_0px_#000]">
                🖊️ Beetle System
            </div>
            <h1 class="text-3xl sm:text-5xl md:text-7xl font-black leading-tight tracking-tight text-ink drop-shadow-sm">
                Draw. <span class="marker-highlight">Guess.</span> <br/>
                <span class="text-2xl sm:text-4xl md:text-5xl font-bold text-gray-400 mt-1 md:mt-2 block transform rotate-1">& Fun</span>
            </h1>
        </div>

        <!-- Lobby Card -->
        <div class="fun-card w-full p-5 sm:p-8 md:p-10 relative bg-white">
            <div id="error-msg" class="hidden bg-red-50 border-[2.5px] md:border-[3px] border-red-500 text-red-700 p-3 sm:p-4 rounded-xl md:rounded-2xl mb-6 sm:mb-8 text-xs sm:text-sm text-center font-bold">
                🚫 <span id="error-text">Nickname required!</span>
            </div>

            <div id="setup-form" class="space-y-6 sm:space-y-8">
                <!-- Avatar Selector -->
                <div>
                    <div class="flex justify-between items-center px-1 mb-2 sm:mb-3">
                        <label class="text-[10px] sm:text-xs font-black text-gray-400 uppercase tracking-[0.2em]">Select Persona</label>
                        <span class="text-[9px] sm:text-[10px] text-gray-300 italic">Swipe →</span>
                    </div>
                    <div class="flex gap-3 sm:gap-4 overflow-x-auto pb-4 px-1 no-scrollbar" id="avatar-list"></div>
                    <input type="hidden" id="selected-avatar" value="🐱">
                </div>

                <!-- Nickname -->
                <div>
                    <input type="text" id="username" 
                        class="fun-input w-full px-4 sm:px-6 py-3.5 sm:py-5 text-lg sm:text-2xl font-black text-ink text-center placeholder:text-gray-200" 
                        maxlength="15"
                        placeholder="NICKNAME">
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3 sm:space-y-4 pt-1 sm:pt-2">
                    <button onclick="openCreateModal()" 
                        class="w-full btn-pop bg-pop-blue font-black py-4 sm:py-5 rounded-xl sm:rounded-2xl text-lg sm:text-xl uppercase tracking-widest">
                        🚀 Create Room
                    </button>
                    
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                        <input type="text" id="room-code-input" 
                            class="fun-input flex-1 px-4 py-3 sm:py-4 text-center uppercase tracking-[0.3em] font-mono font-black text-base sm:text-xl" 
                            maxlength="10"
                            placeholder="CODE">
                        <button onclick="joinRoom()" 
                            class="btn-pop w-full sm:w-auto px-6 sm:px-10 py-3 sm:py-4 bg-pop-pink font-black rounded-xl sm:rounded-2xl text-lg sm:text-xl">
                            JOIN
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="flex flex-col items-center gap-4 text-ink font-bold opacity-40">
            <div class="flex gap-6 text-sm">
                <a href="<?= $base_path ?>admin" class="hover:underline">Admin</a>
                <span class="opacity-20">•</span>
                <a href="<?= $base_path ?>how-to-play" class="hover:underline">How to Play</a>
                <span class="opacity-20">•</span>
                <button onclick="showPwaPrompt()" class="hover:underline flex items-center gap-1.5 text-pop-blue">
                    <span>📲</span> Install App
                </button>
            </div>
            <p class="text-[9px] tracking-widest font-black uppercase">designed & developed by <a href="https://irfanmanzoor.in">irfan manzoor</a></p>
        </footer>
    </div>

    <!-- Scripts -->
    <script src="<?= $base_path ?>js/sounds.js?v=NUCLEAR_RESET"></script>
    <script src="<?= $base_path ?>js/lobby.js?v=NUCLEAR_RESET"></script>

    <!-- Modals -->
    <?php include __DIR__ . '/modals/create_room.php'; ?>

    <!-- PWA Install Prompt (Hidden by default) -->
    <div id="pwa-install-prompt" class="fixed bottom-6 left-6 right-6 md:left-auto md:right-10 md:w-96 z-[200] hidden">
        <div class="bg-pop-yellow border-[3.5px] border-ink rounded-[1.5rem] p-5 shadow-[10px_10px_0px_#1e1e1e] transform transition-all hover:scale-102 flex flex-col gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white border-[2.5px] border-ink rounded-xl flex items-center justify-center text-3xl shadow-[3px_3px_0px_#000]">
                    🖊️
                </div>
                <div class="flex-1">
                    <h3 class="font-black text-sm uppercase tracking-tight leading-none mb-1">Install DrawGuess</h3>
                    <p class="text-[10px] font-bold text-ink/60 leading-snug">Add to home screen for absolute zero latency & full screen vibes!</p>
                </div>
                <button id="pwa-close" class="text-xl font-black opacity-30 hover:opacity-100">×</button>
            </div>
            <div class="flex gap-3">
                <button id="pwa-install-btn" class="flex-1 bg-ink text-white font-black py-3 rounded-xl text-xs uppercase tracking-widest hover:bg-gray-800 transition-colors shadow-[3px_3px_0px_rgba(255,255,255,0.2)] active:translate-y-0.5">
                    Install Now 🚀
                </button>
            </div>
        </div>
    </div>
</body>
</html>

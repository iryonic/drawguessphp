<?php
// Determine the dynamic base path securely
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$scriptDir = str_replace('\\', '/', $scriptDir); // Normalize Windows paths
$base_path = rtrim($scriptDir, '/') . '/';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Draw & Guess - In Game</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="manifest" href="<?= $base_path ?>manifest.json">
    <meta name="theme-color" content="#facc15">
    <script>
        const APP_ROOT = '<?= $base_path ?>';
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= $base_path ?>sw.js').catch(() => {});
        }
    </script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Fredoka', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        paper: '#f8f9fa',
                        ink: '#1e1e1e',
                        pop: {
                            yellow: '#ffeb3b',
                            blue: '#4fc3f7',
                            pink: '#ff80ab',
                            purple: '#ce93d8',
                            green: '#b9f6ca',
                            red: '#ff8a80'
                        }
                    },
                    animation: {
                        'bounce-slow': 'bounce 3s infinite',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #fdfbf7;
            background-image: 
                radial-gradient(#e5e7eb 1.5px, transparent 1.5px), 
                radial-gradient(#e5e7eb 1.5px, transparent 1.5px);
            background-size: 24px 24px;
            background-position: 0 0, 12px 12px;
            color: #1e1e1e;
            overflow: hidden;
            touch-action: none;
        }

        .neo-border {
            border: 3px solid #1e1e1e;
            box-shadow: 4px 4px 0px #1e1e1e;
        }
        
        .neo-btn {
            border: 3px solid #1e1e1e;
            box-shadow: 4px 4px 0px #1e1e1e;
            transition: all 0.1s;
        }
        .neo-btn:active {
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0px #1e1e1e;
        }
        .neo-btn.selected {
            background: #e0e0e0;
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0px #1e1e1e;
        }

        .chat-pattern {
            background-image: linear-gradient(45deg, #f0f0f0 25%, transparent 25%, transparent 75%, #f0f0f0 75%, #f0f0f0), linear-gradient(45deg, #f0f0f0 25%, transparent 25%, transparent 75%, #f0f0f0 75%, #f0f0f0);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
        }

        /* Tooltip Arrow */
        .tooltip-arrow::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            margin-left: -6px;
            border-width: 6px 6px 0;
            border-color: #1e1e1e transparent transparent transparent;
        }

        /* Brush Preview */
        .color-dot {
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .color-dot:hover {
            transform: scale(1.2);
        }
        .color-dot.active {
            transform: scale(1.1);
            border-color: white;
            box-shadow: 0 0 0 3px #1e1e1e;
        }
    </style>
</head>

<body class="h-screen flex flex-col">

    <!-- Header / Top Bar -->
    <header class="h-12 md:h-16 bg-white border-b-[3px] border-ink flex items-center justify-between px-2 md:px-4 shrink-0 z-20 relative shadow-sm gap-2 transition-all">
        <!-- Decoration -->
        <div class="absolute -bottom-1 left-0 w-full h-1 bg-gray-100 hidden"></div>

        <!-- Left: Logo & Room Code -->
        <div class="flex items-center gap-2 md:gap-4 shrink-0">
            <div class="md:flex flex-col leading-none hidden">
                <span class="font-black text-xl italic tracking-tighter transform -rotate-2 bg-pop-yellow px-1 border-2 border-ink shadow-[2px_2px_0px_#000]">DRAW</span>
                <span class="font-bold text-xs tracking-widest pl-1">GUESS</span>
            </div>
            <!-- Mobile Logo -->
            <div class="md:hidden font-black text-lg italic tracking-tighter bg-pop-yellow px-1 border-2 border-ink shadow-[1px_1px_0px_#000]">DG</div>
            
            <div onclick="navigator.clipboard.writeText(this.innerText)" title="Copy Room Code"
                class="group cursor-pointer flex items-center gap-1.5 bg-gray-100 border-2 border-ink rounded-lg px-2 py-0.5 md:py-1 hover:bg-pop-blue hover:text-black transition">
                <span id="room-code-display" class="font-mono text-xs md:text-lg font-bold">????</span>
                <svg class="w-3 h-3 md:w-4 md:h-4 text-gray-400 group-hover:text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
            </div>
        </div>

        <!-- Center: Word/Status -->
        <div class="absolute left-1/2 -translate-x-1/2 top-1 md:top-3 w-32 md:w-64 text-center z-10 pointer-events-none">
             <div class="bg-white border-2 border-ink rounded-lg md:rounded-xl px-2 py-0.5 md:py-1 shadow-[2px_2px_0px_#000] md:shadow-[3px_3px_0px_#000] relative overflow-hidden pointer-events-auto">
                <div id="word-display" class="font-mono font-bold text-sm md:text-xl truncate leading-tight">WAITING</div>
                <div class="absolute top-0 left-0 w-1 h-full bg-pop-pink"></div>
             </div>
        </div>

        <!-- Right: Timer & Round -->
        <div class="flex items-center gap-1.5 md:gap-4 shrink-0">
            <!-- Round Counter -->
            <div class="flex flex-col items-end leading-none">
                <span class="text-[8px] md:text-[10px] font-bold uppercase text-gray-400">Rnd</span>
                <div class="font-black text-sm md:text-xl">
                    <span id="current-round">1</span><span class="text-gray-300">/</span><span id="max-rounds" class="text-gray-400">3</span>
                </div>
            </div>

            <!-- Timer -->
            <div class="relative w-8 h-8 md:w-12 md:h-12 flex items-center justify-center bg-white border-2 border-ink rounded-full shadow-[2px_2px_0px_#000]">
                <svg class="absolute inset-0 w-full h-full transform -rotate-90 p-0.5" viewBox="0 0 36 36">
                    <path class="text-gray-100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="4" />
                    <path id="timer-progress" class="text-pop-purple transition-all duration-1000 ease-linear" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="4" />
                </svg>
                <span id="timer" class="font-mono font-bold text-xs md:text-lg">60</span>
            </div>

            <!-- Leave Button -->
            <button onclick="leaveRoom()" class="flex items-center justify-center w-8 h-8 md:w-10 md:h-10 bg-red-50 border-2 border-ink rounded-lg shadow-[2px_2px_0px_#000] hover:bg-red-100 transition active:translate-y-0.5 active:shadow-none" title="Leave Room">
                <span class="text-sm md:text-lg">🏃</span>
            </button>
        </div>
    </header>

    <!-- Main Content Area -->
    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col lg:flex-row overflow-hidden relative">

        <!-- Sidebar Left: Players (Desktop) -->
        <aside id="view-rank-desktop" class="hidden lg:flex w-64 bg-white border-r-[3px] border-ink flex-col z-10">
            <div class="p-3 bg-pop-blue border-b-2 border-ink font-bold text-sm flex justify-between">
                <span>🏆 LEADERBOARD</span>
                <span>top</span>
            </div>
            <div id="player-list" class="flex-1 overflow-y-auto p-3 space-y-2 bg-gray-50">
                <!-- Players Injected Here -->
            </div>
        </aside>

        <!-- Main Center: Canvas -->
        <main id="view-draw" class="order-1 w-full lg:order-none lg:flex-1 lg:h-auto flex flex-col bg-gray-100 relative transition-[height] duration-300 ease-in-out h-full lg:h-full">
            
            <!-- Tools (Only Visible when drawing) -->
            <div id="drawing-tools" class="hidden transition-all duration-300 transform translate-y-0 z-10 w-full bg-white border-b-2 border-ink px-2 md:px-4 py-2 flex items-center justify-between shadow-sm gap-2 shrink-0">
                <!-- Colors -->
                <div class="flex gap-1.5 md:gap-2 overflow-x-auto no-scrollbar py-1 flex-1 mask-fade-right">
                    <button class="w-6 h-6 md:w-8 md:h-8 shrink-0 rounded-full bg-black border-2 border-ink color-dot active" onclick="setColor('#000000')"></button>
                    <button class="w-6 h-6 md:w-8 md:h-8 shrink-0 rounded-full bg-red-500 border-2 border-ink color-dot" onclick="setColor('#ef4444')"></button>
                    <button class="w-6 h-6 md:w-8 md:h-8 shrink-0 rounded-full bg-blue-500 border-2 border-ink color-dot" onclick="setColor('#3b82f6')"></button>
                    <button class="w-6 h-6 md:w-8 md:h-8 shrink-0 rounded-full bg-green-500 border-2 border-ink color-dot" onclick="setColor('#22c55e')"></button>
                    <button class="w-6 h-6 md:w-8 md:h-8 shrink-0 rounded-full bg-yellow-400 border-2 border-ink color-dot" onclick="setColor('#facc15')"></button>
                    <button class="w-6 h-6 md:w-8 md:h-8 shrink-0 rounded-full bg-purple-500 border-2 border-ink color-dot" onclick="setColor('#a855f7')"></button>
                    <button class="w-6 h-6 md:w-8 md:h-8 shrink-0 rounded-full bg-amber-600 border-2 border-ink color-dot" onclick="setColor('#d97706')"></button>
                    <button class="w-6 h-6 md:w-8 md:h-8 shrink-0 rounded-md bg-white border-2 border-ink color-dot relative ml-1" title="Eraser" onclick="setColor('#ffffff')">
                        <span class="absolute inset-0 flex items-center justify-center text-[10px] md:text-xs">🧼</span>
                    </button>
                </div>
                <!-- Size & Actions -->
                <div class="flex items-center gap-1 md:gap-3 pl-2 md:pl-4 border-l-2 border-gray-100 shrink-0">
                    <input type="range" min="2" max="40" value="5" id="brush-size" onchange="setSize(this.value)" 
                        class="w-12 md:w-24 h-2 md:h-3 bg-gray-200 rounded-full appearance-none cursor-pointer accent-ink border border-gray-300">
                    <button onclick="undoAction()" class="neo-btn p-1.5 md:p-2 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 text-xs md:text-base" title="Undo Last Stroke">↩️</button>
                    <button onclick="clearCanvasAction()" class="neo-btn p-1.5 md:p-2 rounded-lg bg-red-100 text-red-600 hover:bg-red-200 text-xs md:text-base" title="Clear Canvas">🗑️</button>
                </div>
            </div>

            <!-- Canvas Container -->
            <div id="canvas-container" class="flex-1 relative bg-gray-200/50 flex items-center justify-center overflow-hidden p-2 md:p-4 touch-none min-h-0">
                 <!-- Wrapper for Border & Positioning -->
                 <div class="relative w-full h-full max-w-full max-h-full neo-border bg-white shadow-none isolate">
                     <canvas id="game-canvas" class="block w-full h-full cursor-crosshair touch-none select-none outline-none"></canvas>
                     
                     <!-- Reaction Bar (Floating) -->
                     <div id="reaction-bar" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-30 transition-all duration-300">
                        <button onclick="sendReaction('😂')" class="w-10 h-10 md:w-12 md:h-12 bg-white/90 border-2 border-ink rounded-full shadow-[2px_2px_0px_#000] hover:scale-110 active:scale-95 transition flex items-center justify-center text-xl md:text-2xl backdrop-blur-sm">😂</button>
                        <button onclick="sendReaction('❤️')" class="w-10 h-10 md:w-12 md:h-12 bg-white/90 border-2 border-ink rounded-full shadow-[2px_2px_0px_#000] hover:scale-110 active:scale-95 transition flex items-center justify-center text-xl md:text-2xl backdrop-blur-sm">❤️</button>
                        <button onclick="sendReaction('😮')" class="w-10 h-10 md:w-12 md:h-12 bg-white/90 border-2 border-ink rounded-full shadow-[2px_2px_0px_#000] hover:scale-110 active:scale-95 transition flex items-center justify-center text-xl md:text-2xl backdrop-blur-sm">😮</button>
                        <button onclick="sendReaction('👏')" class="w-10 h-10 md:w-12 md:h-12 bg-white/90 border-2 border-ink rounded-full shadow-[2px_2px_0px_#000] hover:scale-110 active:scale-95 transition flex items-center justify-center text-xl md:text-2xl backdrop-blur-sm">👏</button>
                     </div>

                     <!-- Game Overlay -->
                     <div id="overlay" class="absolute inset-0 z-20 hidden flex flex-col items-center justify-center bg-white/90 backdrop-blur-sm transition-opacity p-4">
                        <div class="neo-border bg-white p-4 md:p-8 rounded-2xl shadow-xl max-w-lg w-full text-center mx-1 md:mx-4 overflow-y-auto max-h-full flex flex-col items-center justify-center min-h-[50%]">
                            <h2 id="overlay-title" class="text-2xl md:text-4xl font-black mb-1 md:mb-2 text-ink">WAITING</h2>
                            <h3 id="overlay-subtitle" class="text-base md:text-xl font-bold text-gray-500 mb-3 md:mb-6 hidden">Starting soon...</h3>
                            <!-- Word Selection -->
                            <div id="word-selection" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3 w-full"></div>
                            <!-- Start Button -->
                            <button id="start-btn" onclick="startGame()" class="hidden neo-btn w-full py-2 md:py-4 px-auto bg-pop-green text-base md:text-xl font-bold rounded-xl hover:bg-green-300 mt-2 md:mt-4 active:scale-95 transition-transform shrink-0">
                                START GAME 🎮
                            </button>
                        </div>
                     </div>

                     <!-- Turn Notification -->
                     <div id="turn-notification" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-30 pointer-events-none opacity-0 transition-opacity duration-500">
                         <div class="bg-pop-yellow border-2 border-ink px-4 md:px-6 py-2 md:py-3 rounded-full shadow-[4px_4px_0px_#000] text-lg md:text-xl font-black transform rotate-2 whitespace-nowrap">
                            ✏️ IT'S YOUR TURN!
                         </div>
                     </div>
                 </div>
            </div>
        </main>

        <!-- Sidebar Right: Chat (Desktop) -->
        <aside id="view-chat" class="hidden lg:flex w-80 bg-white border-l-[3px] border-ink flex-col z-10">
            <div class="p-3 bg-pop-pink border-b-2 border-ink font-bold text-sm flex justify-between items-center">
                <span>💬 CHAT STREAM</span>
                <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
            </div>
            <div id="chat-box" class="flex-1 overflow-y-auto p-4 space-y-3 flex flex-col-reverse chat-pattern"></div>
            <div class="p-3 border-t-2 border-ink bg-white">
                <form onsubmit="sendChat(event)" class="relative">
                    <input type="text" id="chat-input"
                        class="w-full border-2 border-gray-300 rounded-xl pl-4 pr-12 py-3 font-bold focus:border-ink focus:outline-none focus:shadow-[2px_2px_0px_#000] transition"
                        placeholder="Type guess..." autocomplete="off">
                    <button type="submit" class="absolute right-2 top-2 p-1.5 bg-ink text-white rounded-lg hover:bg-gray-800 transition">➤</button>
                </form>
            </div>
        </aside>

        <!-- Mobile Bottom Panel (Chat/Rank) -->
        <div id="mobile-panel" class="order-2 lg:hidden h-0 bg-white border-t-[3px] border-ink flex flex-col transition-[height] duration-300 overflow-hidden relative z-20">
            <!-- Rank View -->
            <div id="view-rank" class="hidden flex-1 flex-col p-4 overflow-hidden h-full">
                 <h2 class="font-black text-xl mb-3 border-b-2 border-ink pb-2 sticky top-0 bg-white z-10">Leaderboard 🏆</h2>
                 <div id="player-list-mobile" class="space-y-3 overflow-y-auto flex-1 min-h-0"></div>
            </div>
            <!-- Chat View -->
            <div id="view-chat-mobile" class="hidden flex-1 flex-col overflow-hidden h-full">
                <div id="chat-box-mobile" class="flex-1 overflow-y-auto p-3 space-y-2 flex flex-col-reverse chat-pattern bg-gray-50 min-h-0"></div>
                <div class="p-2 border-t-2 border-ink bg-white shrink-0 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
                     <form onsubmit="sendChatMobile(event)" class="relative flex gap-2">
                        <input type="text" id="chat-input-mobile"
                            class="flex-1 w-0 border-2 border-gray-300 rounded-lg px-3 py-2 font-bold focus:border-ink focus:outline-none focus:shadow-[2px_2px_0px_#000] text-sm"
                            placeholder="Guess here..." autocomplete="off">
                        <button type="submit" class="bg-pop-blue border-2 border-ink px-4 py-2 rounded-lg font-bold shadow-[2px_2px_0px_#000] active:shadow-none active:translate-y-1 text-sm shrink-0">SEND</button>
                     </form>
                </div>
            </div>
        </div>

        <!-- Mobile Bottom Nav -->
        <nav class="order-3 lg:hidden h-14 bg-white border-t-[3px] border-ink flex items-stretch shrink-0 z-40 relative shadow-[0_-4px_10px_rgba(0,0,0,0.05)]">
            <button onclick="switchTab('rank')" id="tab-rank" class="flex-1 flex flex-col items-center justify-center gap-0.5 text-gray-400 hover:bg-gray-50 transition active:bg-gray-100">
                <span class="text-lg grayscale opacity-50">🏆</span>
                <span class="text-[9px] font-bold uppercase tracking-wider">Rank</span>
            </button>
            <button onclick="switchTab('draw')" id="tab-draw" class="flex-1 flex flex-col items-center justify-center gap-0.5 text-gray-400 bg-gray-50 hover:bg-gray-100 transition active:bg-gray-200 border-x border-gray-200">
                <span class="text-lg grayscale opacity-100 text-ink">✏️</span>
                <span class="text-[9px] font-bold uppercase tracking-wider text-ink">Canvas</span>
            </button>
            <button onclick="switchTab('chat')" id="tab-chat" class="flex-1 flex flex-col items-center justify-center gap-0.5 text-gray-400 hover:bg-gray-50 transition active:bg-gray-100">
                <span class="text-lg grayscale opacity-50">💬</span>
                <span class="text-[9px] font-bold uppercase tracking-wider">Chat</span>
            </button>
        </nav>

    </div>

    <!-- Scripts -->
    <!-- Confetti Library -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <script src="<?= $base_path ?>js/sounds.js"></script>
    <script src="<?= $base_path ?>js/game.js"></script>
    <script>
        // Sync Logic for Mobile Views managed here to keep game.js clean(er)
        window.addEventListener('resize', () => {
             // 1024px is our breakpoint for Desktop layout
             if (window.innerWidth >= 1024) { 
                const drawView = document.getElementById('view-draw');
                drawView.style.height = 'auto'; // Reset height style
                drawView.classList.remove('hidden');
                
                document.getElementById('mobile-panel').style.height = '0';
                
                document.getElementById('view-rank-desktop').classList.remove('hidden');
                document.getElementById('view-chat').classList.remove('hidden');
             }
        });
    </script>
</body>
</html>
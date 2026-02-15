<?php
// Determine the dynamic base path securely
$scriptName = $_SERVER['SCRIPT_NAME'];
$base_path = rtrim(dirname($scriptName), '/\\') . '/';
if ($base_path == '/' || $base_path == '\\') $base_path = '/';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Draw & Guess - In Game</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="<?= $base_path ?>favicon.ico">
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
            overscroll-behavior: none;
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
            height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        :root {
            --header-h: 3.5rem;
            --nav-h: 4.5rem;
            --mobile-panel-h: 0px;
            --pop-yellow: #ffeb3b;
            --pop-blue: #4fc3f7;
            --pop-pink: #ff80ab;
            --ink: #1e1e1e;
        }

        @media (min-width: 768px) {
            :root {
                --header-h: 4.5rem;
            }
        }

        .neo-border {
            border: 3px solid var(--ink);
            box-shadow: 4px 4px 0px var(--ink);
        }
        
        .neo-btn {
            border: 3px solid var(--ink);
            box-shadow: 4px 4px 0px var(--ink);
            transition: all 0.15s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .neo-btn:active {
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0px var(--ink);
        }

        .chat-pattern {
            background-image: radial-gradient(var(--ink) 0.5px, transparent 0.5px);
            background-size: 15px 15px;
            background-color: #f9fafb;
        }

        /* App-like Tabs */
        .tab-btn {
            position: relative;
            transition: all 0.3s ease;
        }
        .tab-btn.active {
            color: var(--ink) !important;
        }
        .tab-btn.active .tab-icon {
            transform: translateY(-8px) scale(1.2);
            filter: grayscale(0) !important;
            opacity: 1 !important;
        }
        .tab-btn.active .tab-label {
            opacity: 1;
            transform: translateY(-2px);
        }
        .tab-indicator {
            position: absolute;
            bottom: 0.5rem;
            width: 4px;
            height: 4px;
            border-radius: 99px;
            background: var(--ink);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            opacity: 0;
        }
        .tab-btn.active .tab-indicator {
            opacity: 1;
            width: 20px;
        }

        /* Brush Preview */
        .color-dot {
            transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .color-dot:active {
            transform: scale(0.9);
        }
        .color-dot.active {
            transform: scale(1.2);
            box-shadow: 0 0 0 3px var(--pop-yellow), 0 0 0 6px var(--ink);
        }

        @keyframes slide-up {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        .animate-slide-up {
            animation: slide-up 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
    </style>
</head>

<body class="fixed inset-0 w-full h-[100dvh] flex flex-col supports-[height:100svh]:h-[100svh]">

    <!-- Header / Top Bar -->
    <header class="h-[var(--header-h)] pt-[env(safe-area-inset-top)] bg-white/95 backdrop-blur-md border-b-[3px] border-ink flex items-center justify-between px-2 md:px-4 shrink-0 z-50 relative gap-1 md:gap-2 transition-all">
        <!-- Decoration Gradient -->
        <div class="absolute inset-0 bg-gradient-to-b from-transparent to-black/5 pointer-events-none opacity-20"></div>

        <!-- Left: Logo & Room Code -->
        <div class="flex items-center gap-1.5 md:gap-4 shrink-0">
            <div class="md:flex flex-col leading-none hidden">
                <h1 class="font-black text-xl italic tracking-tighter transform -rotate-2 bg-pop-yellow px-1 border-2 border-ink shadow-[2px_2px_0px_#000]">DRAWGUESS</h1>
            </div>
            <!-- Mobile Logo -->
            <div class="md:hidden font-black text-lg italic tracking-tighter bg-pop-yellow px-1 border-2 border-ink shadow-[1px_1px_0px_#000]">DG</div>
            
            <button onclick="navigator.clipboard.writeText(document.getElementById('room-code-display').innerText); this.classList.add('bg-green-100')" 
                class="group flex items-center gap-1 bg-gray-50 border-2 border-ink rounded-lg px-2 py-0.5 md:py-1 hover:bg-pop-blue transition active:scale-95">
                <span id="room-code-display" class="font-mono text-[10px] md:text-lg font-bold">????</span>
                <svg class="w-2.5 h-2.5 md:w-4 md:h-4 text-gray-400 group-hover:text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
            </button>
        </div>

        <!-- Center: Word/Status -->
        <div class="absolute left-1/2 -translate-x-1/2 top-1/2 -translate-y-1/2 w-32 xs:w-40 md:w-80 text-center z-10 pointer-events-none">
             <div class="bg-white border-2 border-ink rounded-lg md:rounded-2xl px-2 py-1 md:py-2 shadow-[2px_2px_0px_#000] md:shadow-[4px_4px_0px_#000] relative overflow-hidden pointer-events-auto">
                <div id="word-display" class="font-mono font-black text-xs xs:text-sm md:text-2xl truncate leading-none uppercase tracking-widest">WAITING</div>
                <div class="absolute top-0 left-0 w-1.5 h-full bg-pop-pink"></div>
             </div>
        </div>

        <!-- Right: Timer & Round -->
        <div class="flex items-center gap-1.5 md:gap-4 shrink-0">
            <!-- Round Counter -->
            <div class="flex-col items-end leading-none hidden sm:flex">
                <span class="text-[8px] md:text-[10px] font-bold uppercase text-gray-400">Rnd</span>
                <div class="font-black text-sm md:text-xl">
                    <span id="current-round">1</span><span class="text-gray-300">/</span><span id="max-rounds" class="text-gray-400">3</span>
                </div>
            </div>

            <!-- Timer -->
            <div class="relative w-8 h-8 md:w-12 md:h-12 flex items-center justify-center bg-white border-2 border-ink rounded-full shadow-[2px_2px_0px_#000] shrink-0">
                <svg class="absolute inset-0 w-full h-full transform -rotate-90 p-0.5" viewBox="0 0 36 36">
                    <path class="text-gray-100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="4" />
                    <path id="timer-progress" class="text-pop-purple transition-all duration-1000 ease-linear" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="4" />
                </svg>
                <span id="timer" class="font-mono font-bold text-xs md:text-lg">60</span>
            </div>

            <!-- Leave Button -->
            <button onclick="leaveRoom()" class="flex items-center justify-center w-8 h-8 md:w-10 md:h-10 bg-red-50 border-2 border-ink rounded-lg shadow-[2px_2px_0px_#000] hover:bg-red-100 transition active:translate-y-0.5 active:shadow-none shrink-0" title="Leave">
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
        <main id="view-draw" class="order-1 flex-1 flex flex-col bg-gray-100 relative transition-all duration-300 ease-in-out sm:rounded-none rounded-b-[2rem] overflow-hidden">
            
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
                    <!-- Brush Preview -->
                    <div class="hidden sm:flex w-8 md:w-10 h-8 md:h-10 items-center justify-center bg-gray-50 border border-gray-200 rounded-lg shrink-0 overflow-hidden">
                        <div id="brush-preview" class="rounded-full bg-black" style="width: 5px; height: 5px;"></div>
                    </div>
                    <input type="range" min="2" max="40" value="5" id="brush-size" oninput="setSize(this.value)" 
                        class="w-10 md:w-24 h-2 md:h-3 bg-gray-200 rounded-full appearance-none cursor-pointer accent-ink border border-gray-300">
                    <div class="flex gap-1">
                        <button onclick="undoAction()" class="neo-btn p-1.5 md:p-2 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 text-xs md:text-base active:scale-90" title="Undo">↩️</button>
                        <button onclick="clearCanvasAction()" class="neo-btn p-1.5 md:p-2 rounded-lg bg-red-100 text-red-600 hover:bg-red-200 text-xs md:text-base active:scale-90" title="Clear">🗑️</button>
                    </div>
                </div>
            </div>

            <!-- Canvas Container -->
            <div id="canvas-container" class="flex-1 relative bg-gray-200/50 flex items-center justify-center overflow-hidden p-2 md:p-4 touch-none min-h-0">
                 <!-- Wrapper for Border & Positioning -->
                 <div class="relative w-full h-full max-w-full max-h-full neo-border bg-white shadow-none isolate">
                     <canvas id="game-canvas" oncontextmenu="return false;" class="block w-full h-full cursor-crosshair touch-none select-none outline-none"></canvas>
                     
                      <!-- Reaction Bar (Floating) -->
                      <div id="reaction-bar" class="absolute bottom-2 md:bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5 md:gap-2 z-30 transition-all duration-300">
                         <button onclick="sendReaction('😂')" class="w-9 h-9 md:w-12 md:h-12 bg-white/90 border-2 border-ink rounded-full shadow-[2px_2px_0px_#000] hover:scale-110 active:scale-95 transition flex items-center justify-center text-lg md:text-2xl backdrop-blur-sm">😂</button>
                         <button onclick="sendReaction('❤️')" class="w-9 h-9 md:w-12 md:h-12 bg-white/90 border-2 border-ink rounded-full shadow-[2px_2px_0px_#000] hover:scale-110 active:scale-95 transition flex items-center justify-center text-lg md:text-2xl backdrop-blur-sm">❤️</button>
                         <button onclick="sendReaction('😮')" class="w-9 h-9 md:w-12 md:h-12 bg-white/90 border-2 border-ink rounded-full shadow-[2px_2px_0px_#000] hover:scale-110 active:scale-95 transition flex items-center justify-center text-lg md:text-2xl backdrop-blur-sm">😮</button>
                         <button onclick="sendReaction('👏')" class="w-9 h-9 md:w-12 md:h-12 bg-white/90 border-2 border-ink rounded-full shadow-[2px_2px_0px_#000] hover:scale-110 active:scale-95 transition flex items-center justify-center text-lg md:text-2xl backdrop-blur-sm">👏</button>
                      </div>

                      <!-- Mobile Toasts Container (Floating Chat) -->
                      <div id="canvas-toasts" class="absolute top-4 left-4 right-4 z-40 pointer-events-none flex flex-col items-start gap-2"></div>

                      <!-- Game Overlay -->
                      <div id="overlay" class="absolute inset-0 z-50 hidden flex flex-col items-center justify-center bg-white/95 backdrop-blur-md transition-opacity p-2 sm:p-4">
                         <div class="neo-border bg-white p-5 md:p-10 rounded-[2rem] shadow-2xl max-w-lg w-[95%] text-center flex flex-col items-center justify-center animate-in zoom-in duration-300">
                             <h2 id="overlay-title" class="text-2xl md:text-5xl font-black mb-1 md:mb-2 text-ink uppercase tracking-tight">WAITING</h2>
                             <h3 id="overlay-subtitle" class="text-xs md:text-lg font-bold text-gray-500 mb-4 md:mb-6 hidden">Starting soon...</h3>
                             <!-- Word Selection -->
                             <div id="word-selection" class="hidden flex flex-col gap-3 w-full mt-2 overflow-y-auto max-h-[50vh]"></div>
                             <!-- Start Button -->
                             <button id="start-btn" onclick="startGame()" class="hidden neo-btn w-full py-4 px-8 bg-pop-green text-xl font-black rounded-2xl hover:bg-green-300 mt-4 active:scale-95 transition-all">
                                 START GAME 🎮
                             </button>
                         </div>
                      </div>

                      <!-- Turn Notification -->
                      <div id="turn-notification" class="absolute inset-0 z-[60] flex items-center justify-center pointer-events-none opacity-0 transition-opacity duration-500">
                          <div class="bg-pop-yellow border-[3px] border-ink px-8 py-4 rounded-3xl shadow-[8px_8px_0px_#000] text-2xl md:text-4xl font-black transform rotate-2 animate-bounce">
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
        <div id="mobile-panel" class="order-2 lg:hidden bg-white border-t-[3px] border-ink flex flex-col transition-all duration-500 overflow-hidden relative z-20 shrink-0 h-[var(--mobile-panel-h)] shadow-[0_-10px_40px_rgba(0,0,0,0.1)]">
            <!-- Sheet Handle Decoration -->
            <div class="w-full flex justify-center py-2 shrink-0">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full border border-gray-100"></div>
            </div>

            <!-- Rank View -->
            <div id="view-rank" class="hidden flex-1 flex-col px-4 pb-4 overflow-hidden h-full">
                 <h2 class="font-black text-xl mb-3 border-b-2 border-ink pb-2 sticky top-0 bg-white z-10 flex items-center justify-between">
                    <span class="flex items-center gap-2">🏆 LEADERBOARD</span>
                    <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Live</span>
                 </h2>
                 <div id="player-list-mobile" class="space-y-3 overflow-y-auto flex-1 min-h-0 py-1"></div>
            </div>
            <!-- Chat View -->
            <div id="view-chat-mobile" class="hidden flex flex-col h-full overflow-hidden absolute inset-0 bg-white z-0">
                <!-- Chat Messages Area -->
                <div id="chat-box-mobile" class="flex-1 overflow-y-auto p-3 space-y-2 flex flex-col-reverse chat-pattern bg-gray-50 overscroll-contain"></div>
                
                <!-- Fixed Input Area -->
                <div class="p-4 border-t-2 border-ink bg-white shrink-0 z-10 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.05)]">
                     <form onsubmit="sendChatMobile(event)" class="relative flex gap-2">
                        <input type="text" id="chat-input-mobile"
                            class="flex-1 w-0 border-2 border-gray-200 rounded-2xl px-4 py-3 font-bold focus:border-ink focus:outline-none focus:ring-4 focus:ring-pop-blue/20 text-base"
                            placeholder="Type your guess..." autocomplete="off">
                        <button type="submit" class="bg-pop-blue border-2 border-ink px-6 py-3 rounded-2xl font-black shadow-[4px_4px_0px_#000] active:shadow-none active:translate-y-1 transition-all text-sm shrink-0 uppercase tracking-tight">SEND</button>
                     </form>
                </div>
            </div>
        </div>

        <!-- Mobile Bottom Nav -->
        <nav class="order-3 lg:hidden h-[var(--nav-h)] pb-[env(safe-area-inset-bottom)] bg-white border-t-[3px] border-ink flex items-stretch shrink-0 z-40 relative shadow-[0_-8px_20px_rgba(0,0,0,0.1)]">
            <button onclick="switchTab('rank')" id="tab-rank" class="tab-btn flex-1 flex flex-col items-center justify-center gap-1 text-gray-400">
                <span class="tab-icon text-2xl grayscale opacity-50 transition-all duration-300">🏆</span>
                <span class="tab-label text-[10px] font-black uppercase tracking-widest opacity-60 transition-all duration-300">Players</span>
                <div class="tab-indicator"></div>
            </button>
            <button onclick="switchTab('draw')" id="tab-draw" class="tab-btn flex-1 flex flex-col items-center justify-center gap-1 text-gray-400">
                <span class="tab-icon text-2xl grayscale opacity-50 transition-all duration-300">✏️</span>
                <span class="tab-label text-[10px] font-black uppercase tracking-widest opacity-60 transition-all duration-300">Canvas</span>
                <div class="tab-indicator"></div>
            </button>
            <button onclick="switchTab('chat')" id="tab-chat" class="tab-btn flex-1 flex flex-col items-center justify-center gap-1 text-gray-400">
                <span class="tab-icon text-2xl grayscale opacity-50 transition-all duration-300">💬</span>
                <span class="tab-label text-[10px] font-black uppercase tracking-widest opacity-60 transition-all duration-300">Chat</span>
                <div class="tab-indicator"></div>
            </button>
        </nav>

    </div>

    <!-- Scripts -->
    <!-- Confetti Library -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <link rel="icon" type="image/x-icon" href="<?= $base_path ?>favicon.ico">
    <script src="<?= $base_path ?>js/sounds.js?v=<?= time() ?>_fix"></script>
    <script src="<?= $base_path ?>js/game.js?v=<?= time() ?>"></script>
    <script>
        // Adjust layout on view resize
        window.addEventListener('resize', () => {
             if (window.innerWidth >= 1024) { 
                document.documentElement.style.setProperty('--mobile-panel-h', '0px');
             } else {
                // If switching back to mobile and we were on rank/chat, restore height
                if (window.currentTab && window.currentTab !== 'draw') {
                    document.documentElement.style.setProperty('--mobile-panel-h', '50svh');
                }
             }
        });
    </script>
</body>
</html>
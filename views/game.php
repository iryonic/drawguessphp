<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <link rel="shortcut icon" href="<?= $base_path ?>assets/pwa/favicon.png" type="image/png">
    <title>Draw & Guess - Play Time!</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="manifest" href="<?= $base_path ?>manifest.json">
    <meta name="theme-color" content="#ffeb3b">
    <script>
        const APP_ROOT = '<?= $base_path ?>';
    </script>
    <script src="<?= $base_path ?>js/pwa.js?v=STABLE_V17"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Fredoka', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        paper: '#fdfbf7',
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
        body { background-color: #fdfbf7; background-image: radial-gradient(#f1f3f5 1.2px, transparent 1.2px), radial-gradient(#f1f3f5 1.2px, transparent 1.2px); background-size: 24px 24px; background-position: 0 0, 12px 12px; color: #1e1e1e; overflow: hidden; overscroll-behavior: none; height: 100dvh; display: flex; flex-direction: column; font-feature-settings: 'ss01', 'cv01', 'cv11'; }
        :root { --header-h: 3.5rem; --nav-h: 3.8rem; --mobile-panel-h: 40dvh; --ink: #1e1e1e; }
        @media (min-width: 1024px) { :root { --header-h: 4.8rem; --mobile-panel-h: 0px; } }
        
        .neo-border { border: 3px solid var(--ink); box-shadow: 4px 4px 0px var(--ink); }
        .neo-btn { border: 2.5px solid var(--ink); box-shadow: 3px 3px 0px var(--ink); transition: all 0.15s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .neo-btn:active { transform: translate(2px, 2px); box-shadow: 0px 0px 0px var(--ink); }
        
        .chat-pattern { background-image: radial-gradient(rgba(30,30,30,0.05) 0.5px, transparent 0.5px); background-size: 12px 12px; background-color: #fcfcfc; }
        
        .tab-btn { position: relative; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .tab-btn.active .tab-icon { transform: translateY(-10px) scale(1.35); filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); opacity: 1 !important; color: var(--ink); }
        .tab-btn.active .tab-label { opacity: 1; transform: translateY(-4px) scale(1.05); font-weight: 800; color: var(--ink); }
        .tab-indicator { position: absolute; bottom: 0.6rem; width: 6px; height: 6px; border-radius: 99px; background: var(--ink); transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); opacity: 0; }
        .tab-btn.active .tab-indicator { opacity: 1; width: 28px; }
        
        .color-dot { transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1); border-width: 2.5px !important; }
        .color-dot:hover { transform: scale(1.15) rotate(5deg); z-index: 10; }
        .color-dot.active { transform: scale(1.1) !important; box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--ink) !important; z-index: 20; }
        
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .glass-header { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        
        /* Smooth Scale for Canvas */
        #canvas-container { perspective: 1000px; }
        .canvas-wrapper { transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1); }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0px); }
        }
        .float-anim { animation: float 3s ease-in-out infinite; }
    </style>
</head>
<body class="fixed inset-0 w-full h-[100dvh] flex flex-col">

    <!-- Header / Top Bar -->
    <header class="h-[var(--header-h)] glass-header border-b-[3px] border-ink flex items-center justify-between px-3 md:px-8 shrink-0 z-50 relative gap-2">
        <!-- Logo & Room Code -->
        <div class="flex items-center gap-2 sm:gap-4 shrink-0">
            <h1 class="hidden lg:block font-black text-2xl italic tracking-tighter transform -rotate-1 bg-pop-yellow px-3 py-1 border-[3px] border-ink shadow-[4px_4px_0px_#000] float-anim">DRAWGUESS</h1>
            <div onclick="navigator.clipboard.writeText('<?= $room_code ?>')" class="flex items-center gap-2 md:gap-3 bg-white border-[2.5px] border-ink rounded-xl px-2.5 md:px-4 py-1.5 md:py-2 cursor-pointer hover:bg-pop-blue transition-all active:translate-y-1 active:shadow-none shadow-[3px_3px_0px_#000] group">
                <span id="room-code-display" class="font-mono font-black text-sm md:text-xl tracking-widest uppercase"><?= $room_code ?></span>
            </div>
        </div>

        <!-- Word/Status (Floating HUD) -->
        <div id="floating-word-hud" class="absolute left-1/2 -translate-x-1/2 top-full -mt-2 md:mt-0 md:top-1/2 md:-translate-y-1/2 flex flex-col items-center pointer-events-none z-[60]">
             <div class="bg-white border-[2.5px] border-ink rounded-xl md:rounded-2xl px-4 md:px-8 py-1.5 md:py-2.5 shadow-[4px_4px_0px_#000] pointer-events-auto min-w-[120px] md:min-w-[200px] text-center transition-all duration-300 transform hover:scale-105">
                <div class="flex items-center justify-center gap-2 sm:gap-3 overflow-hidden">
                    <div id="word-display" class="font-mono font-black text-sm sm:text-lg md:text-2xl leading-none uppercase tracking-[0.2em] md:tracking-[0.3em] text-ink whitespace-nowrap overflow-hidden">WAITING</div>
                    <div id="word-len" class="bg-ink text-white text-[10px] sm:text-xs font-black px-1.5 py-0.5 rounded-md hidden"></div>
                </div>
             </div>
        </div>

        <!-- Timer & Round -->
        <div class="flex items-center gap-2 md:gap-5 shrink-0">
            <!-- Round Counter -->
            <div class="hidden sm:flex flex-col items-end justify-center leading-none mr-1 h-full">
                <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Round</span>
                <div class="font-black text-sm md:text-2xl">
                    <span id="current-round">1</span><span class="text-gray-300 px-0.5">/</span><span id="max-rounds">3</span>
                </div>
            </div>

            <!-- Timer (Premium Circle) -->
            <div class="relative w-10 h-10 md:w-16 md:h-16 flex items-center justify-center bg-white border-[3px] border-ink rounded-full shadow-[3px_3px_0px_#000]">
                <svg class="absolute inset-0 w-full h-full transform -rotate-90 p-1" viewBox="0 0 36 36">
                    <path id="timer-progress" class="text-pop-pink transition-all duration-1000 ease-linear" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" />
                </svg>
                <span id="timer" class="font-mono font-black text-base md:text-2xl pt-0.5">--</span>
            </div>

            <!-- Header Actions -->
            <div class="flex gap-2 ml-1">
                <button onclick="toggleMusicUI()" class="neo-btn w-9 h-9 md:w-12 md:h-12 rounded-xl bg-white flex items-center justify-center text-lg md:text-xl">
                    <span id="music-icon">🔊</span>
                </button>
                <button onclick="leaveRoom()" class="neo-btn w-9 h-9 md:w-12 md:h-12 rounded-xl bg-red-50 flex items-center justify-center text-lg md:text-xl">
                    <span>🏃</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col lg:flex-row overflow-hidden relative">
        
        <!-- Sidebar Left: Players -->
        <aside id="view-rank-desktop" class="hidden lg:flex w-64 bg-white border-r-[3.5px] border-ink flex-col z-10">
            <div class="p-3 bg-pop-blue border-b-2 border-ink font-black text-sm uppercase tracking-widest">🏆 Leaderboard</div>
            <div id="player-list" class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50 no-scrollbar"></div>
        </aside>

        <!-- Main Center: Canvas -->
        <main id="view-draw" class="flex-1 flex flex-col bg-paper relative overflow-hidden">
            
            <!-- Drawing Toolbar (Ultra-Compact & Discrete) -->
            <div id="drawing-tools" class="hidden absolute top-2 left-1/2 -translate-x-1/2 z-[60] bg-white/90 backdrop-blur-md neo-border rounded-xl md:rounded-full p-1 md:px-4 md:py-1.5 flex items-center gap-2 md:gap-4 shadow-lg transition-all duration-300 max-w-[96vw] md:max-w-none border-b-2">
                
                <!-- Colors (Horizontal Scroll on Mobile) -->
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <div class="flex overflow-x-auto no-scrollbar gap-1.5 md:gap-2 px-1 py-0.5 max-w-[180px] sm:max-w-none">
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-black border border-ink color-dot active" onclick="setColor('#000000')" title="Black"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-slate-500 border border-ink color-dot" onclick="setColor('#64748b')" title="Gray"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-red-500 border border-ink color-dot" onclick="setColor('#ef4444')" title="Red"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-orange-500 border border-ink color-dot" onclick="setColor('#f97316')" title="Orange"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-yellow-400 border border-ink color-dot" onclick="setColor('#facc15')" title="Yellow"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-lime-500 border border-ink color-dot" onclick="setColor('#84cc16')" title="Lime"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-green-500 border border-ink color-dot" onclick="setColor('#22c55e')" title="Green"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-cyan-500 border border-ink color-dot" onclick="setColor('#06b6d4')" title="Cyan"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-blue-500 border border-ink color-dot" onclick="setColor('#3b82f6')" title="Blue"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-indigo-500 border border-ink color-dot" onclick="setColor('#6366f1')" title="Indigo"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-purple-500 border border-ink color-dot" onclick="setColor('#a855f7')" title="Purple"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-pink-500 border border-ink color-dot" onclick="setColor('#ec4899')" title="Pink"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-amber-900 border border-ink color-dot" onclick="setColor('#78350f')" title="Brown"></button>
                        <button class="w-5 h-5 md:w-8 md:h-8 shrink-0 rounded-full bg-white border border-ink color-dot" onclick="setColor('#ffffff')" title="White"></button>
                    </div>
                </div>
                
                <div class="w-px h-6 bg-ink/10"></div>

                <!-- Brush & Actions Group -->
                <div class="flex items-center gap-2 md:gap-4 shrink-0">
                    <!-- Brush size is a bit smaller now -->
                    <div class="flex items-center gap-2 bg-gray-50 px-2 py-1 rounded-lg">
                        <div id="brush-preview" class="w-3 h-3 md:w-5 md:h-5 rounded-full bg-ink shrink-0 border border-white"></div>
                        <input type="range" min="1" max="40" value="5" id="brush-size" oninput="setSize(this.value)" class="w-14 md:w-24 h-1 bg-gray-300 rounded-lg appearance-none accent-ink">
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-1 shrink-0">
                        <button onclick="setTool('pen')" data-tool="pen" class="tool-btn p-1 rounded-lg hover:bg-gray-100 text-base md:text-xl ring-2 ring-ink bg-pop-yellow" title="Pen">✏️</button>
                        <button onclick="setTool('fill')" data-tool="fill" class="tool-btn p-1 rounded-lg hover:bg-yellow-50 text-base md:text-xl" title="Fill Bucket">🪣</button>
                        <div class="w-px h-5 bg-ink/10 self-center mx-0.5"></div>
                        <button onclick="undoAction()" class="p-1 rounded-lg hover:bg-gray-100 text-base md:text-xl" title="Undo">↩️</button>
                        <button onclick="clearCanvasAction()" class="p-1 rounded-lg hover:bg-red-50 text-base md:text-xl" title="Clear">🗑️</button>
                    </div>
                </div>
            </div>

            <!-- Canvas Container (50/50 Split for Mobile) -->
            <div id="canvas-container" class="flex-1 lg:flex-none relative p-[0.2rem] md:p-8 flex items-center justify-center bg-paper min-h-[45dvh] lg:min-h-0">
                <div class="canvas-wrapper relative w-full h-full max-w-6xl aspect-[16/9] md:aspect-[4/3] md:neo-border bg-white overflow-hidden isolate shadow-lg md:shadow-none">
                    <canvas id="game-canvas" class="w-full h-full cursor-crosshair touch-none select-none"></canvas>
                    
                    <!-- Reactions -->
                    <div id="reaction-bar" class="absolute bottom-[10px] left-1/2 -translate-x-1/2 flex gap-2 z-30">
                        <button onclick="sendReaction('😂')" class="w-11 h-11 bg-white border-[2.5px] border-ink rounded-2xl shadow-[3px_3px_0px_#000] active:translate-y-1 active:shadow-none transition flex items-center justify-center text-2xl">😂</button>
                        <button onclick="sendReaction('❤️')" class="w-11 h-11 bg-white border-[2.5px] border-ink rounded-2xl shadow-[3px_3px_0px_#000] active:translate-y-1 active:shadow-none transition flex items-center justify-center text-2xl">❤️</button>
                        <button onclick="sendReaction('😮')" class="w-11 h-11 bg-white border-[2.5px] border-ink rounded-2xl shadow-[3px_3px_0px_#000] active:translate-y-1 active:shadow-none transition flex items-center justify-center text-2xl">😮</button>
                        <button onclick="sendReaction('👏')" class="w-11 h-11 bg-white border-[2.5px] border-ink rounded-2xl shadow-[3px_3px_0px_#000] active:translate-y-1 active:shadow-none transition flex items-center justify-center text-2xl">👏</button>
                    </div>

                    <!-- Overlays (Better Sizing) -->
                    <div id="overlay" class="absolute inset-0 z-[100] hidden bg-white/95 backdrop-blur-sm flex flex-col items-center justify-center p-4 text-center transition-all duration-500">
                         <div class="neo-border bg-white p-6 md:p-12 rounded-[1.5rem] md:rounded-[2.5rem] max-w-[calc(100%-2rem)] md:max-w-lg w-full shadow-lg">
                            <h2 id="overlay-title" class="text-2xl md:text-4xl lg:text-6xl font-black text-ink uppercase tracking-tight mb-2">IT'S YOUR TURN!</h2>
                            <p id="overlay-subtitle" class="text-[10px] md:text-base font-bold text-gray-400 uppercase tracking-widest mb-4 md:mb-8">Ready to draw?</p>
                            
                            <div id="word-selection" class="hidden flex-col gap-2 md:gap-3 max-h-[40vh] overflow-y-auto no-scrollbar py-2"></div>
                            <button id="start-btn" onclick="startGame()" class="hidden neo-btn w-full py-3 md:py-5 rounded-xl md:rounded-2xl bg-pop-green text-sm md:text-xl font-black uppercase tracking-widest block mx-auto">START GAME 🎮</button>
                         </div>
                    </div>

                    <div id="turn-notification" class="absolute inset-0 z-[60] flex items-center justify-center pointer-events-none opacity-0 transition-all duration-700">
                        <div class="px-10 py-5 rounded-[2rem] bg-pop-yellow border-[3.5px] border-ink font-black text-3xl md:text-5xl shadow-[8px_8px_0px_#000] animate-bounce">
                            YOUR TURN! ✏️
                        </div>
                    </div>

                    <div id="canvas-toasts" class="absolute top-4 left-4 right-4 z-40 pointer-events-none flex flex-col items-start gap-2"></div>

                    <!-- Game Over / Results Screen -->
                    <div id="results-screen" class="absolute inset-0 z-[110] hidden bg-pop-blue flex flex-col items-center justify-center p-4 text-center overflow-hidden">
                        <div class="relative w-full max-w-2xl">
                            <h2 class="text-4xl md:text-7xl font-black text-white uppercase tracking-tighter mb-8 drop-shadow-[6px_6px_0px_#000]">GAME OVER!</h2>
                            
                            <!-- Podium Container -->
                            <div id="podium" class="flex items-end justify-center gap-2 md:gap-4 mb-12 min-h-[300px]">
                                <!-- 2nd Place -->
                                <div class="flex flex-col items-center group">
                                    <div id="winner-2-avatar" class="text-4xl md:text-6xl mb-2 animate-bounce transition-all duration-500 delay-100">🥈</div>
                                    <div class="bg-white border-[4px] border-ink p-2 md:p-4 rounded-t-2xl w-24 md:w-36 h-32 md:h-48 flex flex-col items-center justify-end shadow-[6px_6px_0px_#000]">
                                        <div id="winner-2-name" class="font-black text-[10px] md:text-sm uppercase truncate w-full mb-1">---</div>
                                        <div id="winner-2-score" class="font-black text-sm md:text-xl text-pop-pink">0</div>
                                        <div class="mt-2 font-black text-2xl md:text-4xl text-gray-200">2</div>
                                    </div>
                                </div>
                                <!-- 1st Place -->
                                <div class="flex flex-col items-center group z-10 -translate-y-4">
                                    <div id="winner-1-avatar" class="text-6xl md:text-8xl mb-2 animate-bounce transition-all duration-500">🥇</div>
                                    <div class="bg-pop-yellow border-[4px] border-ink p-2 md:p-4 rounded-t-2xl w-28 md:w-44 h-44 md:h-64 flex flex-col items-center justify-end shadow-[8px_8px_0px_#000]">
                                        <div id="winner-1-name" class="font-black text-xs md:text-lg uppercase truncate w-full mb-1 text-center">---</div>
                                        <div id="winner-1-score" class="font-black text-lg md:text-2xl text-pop-pink">0</div>
                                        <div class="mt-2 font-black text-4xl md:text-6xl text-white/50">1</div>
                                    </div>
                                </div>
                                <!-- 3rd Place -->
                                <div class="flex flex-col items-center group">
                                    <div id="winner-3-avatar" class="text-3xl md:text-5xl mb-2 animate-bounce transition-all duration-500 delay-200">🥉</div>
                                    <div class="bg-white border-[4px] border-ink p-2 md:p-4 rounded-t-2xl w-20 md:w-32 h-24 md:h-36 flex flex-col items-center justify-end shadow-[4px_4px_0px_#000]">
                                        <div id="winner-3-name" class="font-black text-[10px] md:text-xs uppercase truncate w-full mb-1">---</div>
                                        <div id="winner-3-score" class="font-black text-xs md:text-lg text-pop-pink">0</div>
                                        <div class="mt-2 font-black text-xl md:text-3xl text-gray-100">3</div>
                                    </div>
                                </div>
                            </div>

                            <button onclick="window.location.href='index.php'" class="neo-btn bg-white px-8 py-4 rounded-2xl font-black uppercase tracking-widest text-lg hover:bg-pop-yellow transition-all active:translate-y-1">Return to Lobby 🏠</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Sidebar Right: Chat -->
        <aside id="view-chat" class="hidden lg:flex w-80 bg-white border-l-[3.5px] border-ink flex-col">
            <div class="p-3 bg-pop-pink border-b-2 border-ink font-black text-sm uppercase tracking-widest">💬 Chat Stream</div>
            <div id="chat-box" class="flex-1 overflow-y-auto p-4 space-y-3 flex flex-col-reverse chat-pattern no-scrollbar"></div>
            <div class="p-4 border-t-2 border-ink">
                <form onsubmit="sendChat(event)" class="relative">
                    <input type="text" id="chat-input" class="w-full border-[3px] border-ink rounded-xl px-4 py-3.5 font-bold focus:outline-none" placeholder="Type here..." autocomplete="off">
                    <button type="submit" class="absolute right-2 top-2 p-1.5 bg-ink text-white rounded-lg">➤</button>
                </form>
            </div>
        </aside>

        <!-- Mobile Bottom Section (Persistent Split) -->
        <div id="mobile-panel" class="lg:hidden bg-white border-t-[3px] border-ink flex flex-col transition-all duration-300 h-[var(--mobile-panel-h)] overflow-hidden relative z-20">
         
            
            <div id="view-rank" class="flex flex-1 p-4 overflow-hidden flex-col">
                 <h2 class="font-black text-lg mb-3 border-b-2 border-ink pb-1 uppercase tracking-wide">🏆 Leaderboard</h2>
                 <div id="player-list-mobile" class="space-y-3 overflow-y-auto flex-1 no-scrollbar pb-2"></div>
            </div>
            <div id="view-chat-mobile" class="hidden flex-1 flex-col overflow-hidden">
                <div id="chat-box-mobile" class="flex-1 overflow-y-auto p-4 space-y-3 flex flex-col-reverse chat-pattern no-scrollbar"></div>
                <div class="p-3 border-t-2 border-ink bg-white">
                     <form onsubmit="sendChatMobile(event)" class="flex gap-2">
                        <input type="text" id="chat-input-mobile" class="flex-1 border-[2.5px] border-ink rounded-xl px-4 py-2 font-bold outline-none text-sm" placeholder="Guess word...">
                        <button type="submit" class="bg-pop-pink border-[2.5px] border-ink px-4 py-2 rounded-xl font-black text-xs shadow-[2px_2px_0px_#000]">SEND</button>
                     </form>
                </div>
            </div>
        </div>

        <nav class="lg:hidden h-[var(--nav-h)] bg-white border-t-[3px] border-ink flex items-stretch z-50 relative shrink-0">
            <button onclick="switchTab('rank')" id="tab-rank" class="tab-btn flex-1 flex flex-col items-center justify-center text-gray-400">
                <span class="tab-icon text-xl grayscale opacity-30 transition-all duration-300">🏆</span>
                <span class="tab-label text-[9px] font-black uppercase tracking-[0.1em] mt-1 opacity-50">Hall</span>
                <div class="tab-indicator"></div>
            </button>
            <!-- Canvas tab now acts as a 'Minimize/Expand' toggle or focus indicator -->
            <button onclick="switchTab('draw')" id="tab-draw" class="tab-btn flex-1 flex flex-col items-center justify-center text-gray-400">
                <span class="tab-icon text-xl grayscale opacity-30 transition-all duration-300">✏️</span>
                <span class="tab-label text-[9px] font-black uppercase tracking-[0.1em] mt-1 opacity-50">Focus</span>
                <div class="tab-indicator"></div>
            </button>
            <button onclick="switchTab('chat')" id="tab-chat" class="tab-btn flex-1 flex flex-col items-center justify-center text-gray-400">
                <span class="tab-icon text-xl grayscale opacity-30 transition-all duration-300">💬</span>
                <span class="tab-label text-[9px] font-black uppercase tracking-[0.1em] mt-1 opacity-50">Comms</span>
                <div class="tab-indicator"></div>
            </button>
        </nav>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <!-- PWA Install Prompt -->
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

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    <script src="<?= $base_path ?>js/sounds.js?v=NUCLEAR_RESET"></script>
    <script src="<?= $base_path ?>js/game.js?v=NUCLEAR_RESET"></script>
</body>
</html>
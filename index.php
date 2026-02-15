<?php
// Determine the dynamic base path securely
$scriptName = $_SERVER['SCRIPT_NAME'];
$base_path = rtrim(str_replace('\\', '/', dirname($scriptName)), '/') . '/';
if ($base_path == '/' || $base_path == '\\') $base_path = '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" type="image/x-icon" href="<?= $base_path ?>favicon.ico">
    <title>Draw & Guess - Creative Drawing Party!</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="<?= $base_path ?>manifest.json">
    <meta name="theme-color" content="#facc15">
    <script>
        const APP_ROOT = '<?= $base_path ?>';
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register(APP_ROOT + 'sw.js').catch(() => {});
        }
    </script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Fredoka', 'sans-serif'],
                    },
                    colors: {
                        paper: '#f8f9fa',
                        ink: '#1e1e1e',
                        pop: {
                            yellow: '#ffeb3b',
                            blue: '#4fc3f7',
                            pink: '#ff80ab',
                            purple: '#ce93d8',
                            green: '#b9f6ca'
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
            overflow-x: hidden;
        }

        .fun-card {
            background: white;
            border: 3px solid #1e1e1e;
            box-shadow: 8px 8px 0px #1e1e1e;
            border-radius: 1.5rem;
            transition: all 0.2s ease;
        }

        .fun-card:hover {
            transform: translateY(-2px);
            box-shadow: 10px 10px 0px #1e1e1e;
        }

        .fun-input {
            background: #f8f9fa;
            border: 3px solid #1e1e1e;
            border-radius: 1rem;
            box-shadow: 4px 4px 0px #e5e7eb;
            transition: all 0.2s;
        }
        .fun-input:focus {
            background: #fff;
            box-shadow: 4px 4px 0px #1e1e1e;
            transform: translate(-1px, -1px);
        }

        .btn-pop {
            border: 3px solid #1e1e1e;
            box-shadow: 4px 4px 0px #1e1e1e;
            transition: all 0.1s;
        }
        .btn-pop:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0px #1e1e1e;
        }
        .btn-pop:active {
            transform: translate(0px, 0px);
            box-shadow: 2px 2px 0px #1e1e1e;
        }

        .marker-highlight {
            position: relative;
            white-space: nowrap;
        }
        .marker-highlight::before {
            content: '';
            position: absolute;
            bottom: 5px;
            left: -5px;
            right: -5px;
            height: 12px;
            background: #ffeb3b;
            z-index: -1;
            transform: rotate(-2deg);
            opacity: 0.7;
            border-radius: 4px;
        }

        /* Avatar Selection */
        .avatar-tile {
            border: 3px solid transparent;
            min-width: 60px;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            border-radius: 1rem;
            background: #fff;
            border: 3px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.2s;
        }
        .avatar-tile.selected {
            border-color: #1e1e1e;
            background: #b9f6ca;
            box-shadow: 4px 4px 0px #1e1e1e;
            transform: translate(-2px, -2px);
        }

        /* Modal Overrides */
        .modal-bg {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 py-8">

    <!-- Decorative Doodles (Absolute) -->
    <div class="fixed top-10 left-10 text-6xl opacity-20 rotate-[-15deg] pointer-events-none select-none hidden lg:block">🎨</div>
    <div class="fixed bottom-10 right-10 text-6xl opacity-20 rotate-[15deg] pointer-events-none select-none hidden lg:block">✏️</div>
    <div class="fixed top-20 right-20 w-16 h-16 rounded-full border-[3px] border-dashed border-gray-400 opacity-20 pointer-events-none hidden lg:block"></div>

    <!-- Main Container: Centered Single Column -->
    <div class="w-full max-w-lg z-10 flex flex-col items-center gap-8">
        
        <!-- Branding Header -->
        <div class="text-center space-y-4">
            <div class="inline-block px-4 py-1 rounded-full border-2 border-black bg-pop-yellow text-black text-xs font-bold tracking-wide transform -rotate-2 shadow-[2px_2px_0px_#000]">
                🚀 OPEN BETA
            </div>
            
            <h1 class="text-4xl sm:text-5xl md:text-7xl font-black leading-tight tracking-tight text-ink drop-shadow-sm">
                Draw. <span class="marker-highlight">Guess.</span> <br/>
                <span class="text-2xl sm:text-4xl md:text-5xl font-bold text-gray-500 mt-2 block">Party!</span>
            </h1>
            
            <p class="text-lg text-gray-600 font-medium max-w-xs mx-auto leading-snug">
                The silliest multiplayer drawing game.
            </p>
        </div>

        <!-- Game Card -->
        <div class="fun-card w-full p-6 md:p-8 relative bg-white">
            <!-- Tape Decoration -->
            <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-24 h-5 bg-yellow-200/80 rotate-[-2deg] border-l-2 border-r-2 border-white/50"></div>

            <div id="error-msg" class="hidden bg-red-100 border-2 border-red-500 text-red-700 p-3 rounded-xl mb-6 text-sm text-center font-bold flex items-center justify-center gap-2">
                🚫 <span id="error-text">Error text</span>
            </div>

            <div id="setup-form" class="space-y-5">
                
                <!-- Avatar Selection -->
                <div>
                    <div class="flex justify-between items-center mb-2 px-1">
                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wide">Select Persona</label>
                         <span class="text-xs font-bold text-pop-purple cursor-help" title="More coming soon!">?</span>
                    </div>
                    <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-hide justify-start md:justify-center" id="avatar-list">
                        <!-- Injected -->
                    </div>
                    <input type="hidden" id="selected-avatar" value="🐱">
                </div>

                <!-- Username -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2 pl-1">Your Nickname</label>
                    <input type="text" id="username" 
                        class="fun-input w-full px-5 py-3.5 text-lg font-bold text-gray-900 placeholder-gray-400 outline-none text-center" 
                        placeholder="e.g. DoodleMaster">
                </div>

                <!-- Actions -->
                <div class="space-y-4 pt-2">
                    <button onclick="openCreateModal()" 
                        class="w-full btn-pop bg-pop-blue hover:bg-sky-300 text-black font-bold py-4 px-6 rounded-2xl text-lg flex items-center justify-center gap-2 group">
                        <span class="group-hover:rotate-12 transition">🖊️</span> Create Private Room
                    </button>
                    
                    <div class="relative flex py-1 items-center justify-center text-gray-400 text-xs font-bold uppercase tracking-widest">
                        <span>— OR JOIN ROOM —</span>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                         <input type="text" id="room-code-input" 
                            class="fun-input flex-1 px-5 py-3 text-center uppercase tracking-[0.2em] font-mono font-bold text-lg" 
                            placeholder="CODE">
                        <button onclick="joinRoom()" 
                            class="btn-pop w-full sm:w-auto px-8 py-3 bg-pop-pink hover:bg-pink-300 text-black font-bold rounded-2xl">
                            ENTER
                        </button>
                    </div>
                </div>

            </div>
        </div>
        
        <!-- Footer Info -->
         <div class="flex items-center justify-center gap-1 text-xs font-bold text-gray-400 grayscale opacity-80 hover:grayscale-0 hover:opacity-100 transition duration-500">
            <div class="flex items-center gap-1">
              Version 1.0  Developed by 
            </div>
            <div class="flex items-center gap-1">
                <a href="https://irfanmanzoor.in" class="text-gray-900"><span>Irfan Manzoor</span> </a>
            </div>
         </div>

    </div>

    <!-- Create Room Modal -->
    <div id="create-modal" class="fixed inset-0 z-50 flex items-center justify-center modal-bg hidden opacity-0 transition-opacity duration-300 p-4">
        <div class="fun-card max-w-xs w-full p-6 md:p-8 transform scale-95 transition-transform duration-300" id="create-modal-content">
            
            <div class="flex justify-between items-center mb-6 border-b-2 border-gray-100 pb-4">
                <h2 class="text-2xl font-black text-black">Room Setup</h2>
                <div class="text-2xl cursor-pointer hover:scale-110 transition" onclick="closeCreateModal()">✕</div>
            </div>

            <div class="space-y-6">
                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-2 text-center">Total Rounds</label>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                        <button onclick="setRounds(2)" class="modal-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="2">2</button>
                        <button onclick="setRounds(3)" class="modal-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none selected text-xs sm:text-sm" data-val="3">3</button>
                        <button onclick="setRounds(4)" class="modal-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="4">4</button>
                        <button onclick="setRounds(5)" class="modal-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="5">5</button>
                        <button onclick="setRounds(6)" class="modal-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="6">6</button>
                        <button onclick="setRounds(7)" class="modal-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="7">7</button>
                        <button onclick="setRounds(8)" class="modal-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="8">8</button>
                        <button onclick="setRounds(10)" class="modal-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="10">10</button>
                    </div>
                    <input type="hidden" id="crs-rounds" value="3">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2 text-center">Seconds per Turn</label>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                        <button onclick="setTime(30)" class="modal-time-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="30">30s</button>
                        <button onclick="setTime(45)" class="modal-time-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="45">45s</button>
                        <button onclick="setTime(60)" class="modal-time-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none selected text-xs sm:text-sm" data-val="60">60s</button>
                        <button onclick="setTime(75)" class="modal-time-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="75">75s</button>
                        <button onclick="setTime(90)" class="modal-time-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="90">90s</button>
                        <button onclick="setTime(100)" class="modal-time-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="100">100s</button>
                        <button onclick="setTime(120)" class="modal-time-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="120">120s</button>
                        <button onclick="setTime(180)" class="modal-time-opt btn-pop py-2 rounded-xl bg-white text-gray-500 font-bold border-2 border-gray-200 shadow-none hover:shadow-none text-xs sm:text-sm" data-val="180">180s</button>
                    </div>
                    <input type="hidden" id="crs-time" value="60">
                </div>
            </div>

            <div class="mt-8">
                <button onclick="confirmCreateRoom()" class="w-full btn-pop py-4 rounded-xl bg-pop-green text-black font-bold text-lg shadow-[4px_4px_0px_#000]">Start Game 🚀</button>
            </div>
        </div>
    </div>

    <script src="<?= $base_path ?>js/sounds.js?v=<?= time() ?>"></script>
    <script src="<?= $base_path ?>js/lobby.js?v=<?= time() ?>"></script>
    <script>
        // --- Modal & UI Logic ---
        const modal = document.getElementById('create-modal');
        const modalContent = document.getElementById('create-modal-content');

        // Dynamic Classes for Active States
        const activeClass = "bg-pop-purple text-black border-black shadow-[4px_4px_0px_#000] transform -translate-y-1";
        const inactiveClass = "bg-white text-gray-500 border-gray-200 shadow-none";

        // Initial render
        updateOptionStyles();

        function openCreateModal() {
            const username = document.getElementById('username').value.trim();
            if(!username) { 
                const err = document.getElementById('error-msg');
                document.getElementById('error-text').innerText = "Oops! Choose a nickname first.";
                err.classList.remove('hidden');
                setTimeout(() => err.classList.add('hidden'), 3000);
                document.getElementById('username').focus();
                return; 
            }
            try { sfx.play('pop'); } catch(e){}
            modal.classList.remove('hidden');
            void modal.offsetWidth; 
            modal.classList.remove('opacity-0');
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
        }

        function closeCreateModal() {
            modal.classList.add('opacity-0');
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }

        function setRounds(v) {
            try { sfx.play('pop'); } catch(e){}
            document.getElementById('crs-rounds').value = v;
            updateOptionStyles();
        }
        
        function setTime(v) {
            try { sfx.play('pop'); } catch(e){}
            document.getElementById('crs-time').value = v;
            updateOptionStyles();
        }

        function updateOptionStyles() {
            const rounds = document.getElementById('crs-rounds').value;
            const time = document.getElementById('crs-time').value;

            // Rounds
            document.querySelectorAll('.modal-opt').forEach(btn => {
                btn.className = `modal-opt btn-pop py-3 rounded-xl font-bold border-2 transition ${inactiveClass}`;
                if(btn.dataset.val == rounds) {
                    btn.className = `modal-opt btn-pop py-3 rounded-xl font-bold border-2 transition ${activeClass}`;
                }
            });

            // Time
            document.querySelectorAll('.modal-time-opt').forEach(btn => {
                btn.className = `modal-time-opt btn-pop py-3 rounded-xl font-bold border-2 transition ${inactiveClass}`;
                if(btn.dataset.val == time) {
                    btn.className = `modal-time-opt btn-pop py-3 rounded-xl font-bold border-2 transition ${activeClass.replace('purple', 'blue')}`;
                }
            });
        }
    </script>
</body>
</html>
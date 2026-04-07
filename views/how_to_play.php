<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>How to Play - Draw & Guess</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&display=swap" rel="stylesheet">
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
        }
        .fun-card { background: white; border: 3.5px solid #1e1e1e; box-shadow: 8px 8px 0px #1e1e1e; border-radius: 1.5rem; }
        .marker-highlight { position: relative; white-space: nowrap; font-weight: 900; }
        .marker-highlight::before { content: ''; position: absolute; bottom: 4px; left: -2px; right: -2px; height: 10px; background: #ffeb3b; z-index: -1; transform: rotate(-1deg); opacity: 0.8; border-radius: 2px; }
        .step-num { width: 32px; height: 32px; background: #1e1e1e; color: white; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-weight: 900; transform: rotate(-5deg); }
        .btn-back { border: 3px solid #1e1e1e; box-shadow: 4px 4px 0px #1e1e1e; transition: all 0.1s; }
        .btn-back:active { transform: translate(2px, 2px); box-shadow: 2px 2px 0px #1e1e1e; }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8 flex flex-col items-center">

    <div class="w-full max-w-2xl space-y-8">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <a href="<?= $base_path ?>" class="btn-back px-4 py-2 bg-pop-blue rounded-xl font-black text-sm uppercase tracking-widest">
                ← Back
            </a>
            <div class="inline-block px-4 py-1 rounded-full border-2 border-black bg-pop-orange text-black text-[10px] font-black tracking-widest transform -rotate-1">
                RULEBOOK v1
            </div>
        </div>

        <div class="text-center">
            <h1 class="text-4xl md:text-6xl font-black tracking-tight leading-none mb-2">
                How to <span class="marker-highlight">Play</span>
            </h1>
            <p class="text-gray-400 font-bold uppercase tracking-widest text-xs">Master the Beetle system</p>
        </div>

        <!-- Rules Grid -->
        <div class="space-y-6">
            <!-- Step 1 -->
            <div class="fun-card p-6 flex gap-6 items-start relative overflow-hidden">
                <div class="step-num shrink-0">1</div>
                <div class="space-y-2">
                    <h3 class="text-xl font-black uppercase tracking-tight">Join a Room</h3>
                    <p class="text-gray-600 font-medium leading-relaxed">Enter your nickname, choose a cool avatar, and join a room with a code or create your own <span class="font-bold text-pop-blue">private party</span>.</p>
                </div>
                <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 rotate-12 select-none pointer-events-none">📱</div>
            </div>

            <!-- Step 2 -->
            <div class="fun-card p-6 flex gap-6 items-start relative overflow-hidden bg-pop-yellow/5">
                <div class="step-num shrink-0 bg-pop-yellow !text-ink">2</div>
                <div class="space-y-2">
                    <h3 class="text-xl font-black uppercase tracking-tight">The Drawer</h3>
                    <p class="text-gray-600 font-medium leading-relaxed">When it's your turn, choose a word and draw it on the canvas. Use <span class="font-bold">different colors</span> and sizes to make it clear!</p>
                </div>
                <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 -rotate-12 select-none pointer-events-none">🎨</div>
            </div>

            <!-- Step 3 -->
            <div class="fun-card p-6 flex gap-6 items-start relative overflow-hidden">
                <div class="step-num shrink-0 bg-pop-pink">3</div>
                <div class="space-y-2">
                    <h3 class="text-xl font-black uppercase tracking-tight">The Guessers</h3>
                    <p class="text-gray-600 font-medium leading-relaxed">Everyone else watches the live drawing. Type your guess in the chat as fast as you can. <span class="marker-highlight">Speed counts!</span></p>
                </div>
                <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 rotate-45 select-none pointer-events-none">💬</div>
            </div>

            <!-- Step 4 -->
            <div class="fun-card p-6 flex gap-6 items-start relative overflow-hidden bg-pop-green/5">
                <div class="step-num shrink-0 bg-pop-green !text-ink">4</div>
                <div class="space-y-2">
                    <h3 class="text-xl font-black uppercase tracking-tight">Collect Points</h3>
                    <p class="text-gray-600 font-medium leading-relaxed">The faster you guess correctly, the more points you earn. The drawer also gets points when people guess their masterpiece!</p>
                </div>
                <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 -rotate-6 select-none pointer-events-none">👑</div>
            </div>
        </div>

        <!-- Pro-Tips Section -->
        <div class="fun-card p-8 bg-ink text-white">
            <h3 class="text-2xl font-black uppercase tracking-widest mb-6 text-pop-yellow">✨ Pro-Tips</h3>
            <ul class="space-y-4">
                <li class="flex items-center gap-4">
                    <span class="text-pop-blue text-xl">💎</span>
                    <span class="font-bold text-gray-300">Use UNDO (↩️) to fix mistakes quickly.</span>
                </li>
                <li class="flex items-center gap-4">
                    <span class="text-pop-pink text-xl">💡</span>
                    <span class="font-bold text-gray-300">Check the word length hints in the top bar.</span>
                </li>
                <li class="flex items-center gap-4">
                    <span class="text-pop-green text-xl">⚡</span>
                    <span class="font-bold text-gray-300">Send reactions (❤️, 😂) to cheer on the drawer!</span>
                </li>
            </ul>
        </div>

        <div class="text-center py-6">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400">designed with love by <span class="text-ink">irfan manzoor</span></p>
        </div>
    </div>

</body>
</html>

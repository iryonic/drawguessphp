<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php
    $seo_title       = 'How to Play — Complete Game Guide';
    $seo_description = 'Learn everything about Draw & Guess Royale — scoring system, drawing tools, round mechanics, pro tips, and FAQ. Become the ultimate drawer and guesser!';
    $seo_keywords    = 'how to play draw and guess, drawing game guide, drawguess rules, scoring system, drawing tips';
    $seo_canonical   = 'https://drawguess.irfanmanzoor.in/how-to-play';
    include __DIR__ . '/partials/seo_head.php';
    ?>
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
        .fun-card { background: white; border: 3.5px solid #1e1e1e; box-shadow: 8px 8px 0px #1e1e1e; border-radius: 1.5rem; transition: transform 0.2s, box-shadow 0.2s; }
        .fun-card:hover { transform: translate(-2px, -2px); box-shadow: 10px 10px 0px #1e1e1e; }
        .fun-card-flat { background: white; border: 3px solid #1e1e1e; border-radius: 1.2rem; }
        .marker-highlight { position: relative; white-space: nowrap; font-weight: 900; }
        .marker-highlight::before { content: ''; position: absolute; bottom: 4px; left: -2px; right: -2px; height: 10px; background: #ffeb3b; z-index: -1; transform: rotate(-1deg); opacity: 0.9; border-radius: 2px; }
        .step-num { width: 38px; height: 38px; min-width: 38px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-weight: 900; font-size: 1.1rem; border: 2.5px solid #1e1e1e; box-shadow: 3px 3px 0 #1e1e1e; transform: rotate(-5deg); }
        .btn-back { border: 3px solid #1e1e1e; box-shadow: 4px 4px 0px #1e1e1e; transition: all 0.1s; }
        .btn-back:active { transform: translate(2px, 2px); box-shadow: 2px 2px 0px #1e1e1e; }
        .tool-chip { display: inline-flex; align-items: center; gap: 6px; background: #f8f8f8; border: 2px solid #1e1e1e; border-radius: 8px; padding: 4px 10px; font-weight: 700; font-size: 0.78rem; box-shadow: 2px 2px 0 #1e1e1e; }
        .score-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1.5px dashed #e5e7eb; }
        .score-row:last-child { border-bottom: none; }
        .badge { display: inline-flex; align-items: center; gap: 4px; border: 2px solid #1e1e1e; border-radius: 9999px; padding: 3px 10px; font-weight: 800; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .faq-item summary { cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-item[open] summary::after { transform: rotate(180deg); }
        .faq-item summary::after { content: '▾'; transition: transform 0.2s; font-size: 1.2rem; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
        .float { animation: float 3s ease-in-out infinite; }
        @keyframes slideIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        .slide-in { animation: slideIn 0.5s ease forwards; }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8 flex flex-col items-center pb-16">

    <div class="w-full max-w-3xl space-y-8">

        <!-- Header -->
        <div class="flex items-center justify-between pt-2">
            <a href="<?= $base_path ?>" class="btn-back px-4 py-2 bg-pop-blue rounded-xl font-black text-sm uppercase tracking-widest">
                ← Back to Lobby
            </a>
            <div class="flex items-center gap-2">
                <div class="badge bg-pop-orange">RULEBOOK</div>
            </div>
        </div>

        <!-- Hero -->
        <div class="text-center space-y-3 py-4">
            <div class="text-6xl float">🎨</div>
            <h1 class="text-4xl md:text-6xl font-black tracking-tight leading-none">
                How to <span class="marker-highlight">Play</span>
            </h1>
            <p class="text-gray-400 font-bold uppercase tracking-widest text-xs max-w-sm mx-auto">
                Everything you need to draw, guess, and dominate the leaderboard
            </p>
        </div>

        <!-- Quick Overview -->
        <div class="fun-card p-6 bg-pop-yellow/20">
            <h2 class="text-xl font-black uppercase tracking-tight mb-3 flex items-center gap-2">🎯 Quick Overview</h2>
            <p class="text-gray-700 font-semibold leading-relaxed">
                <strong>Draw & Guess Royale</strong> is a real-time multiplayer game where players take turns drawing a secret word while others race to guess it in the chat. The faster you guess — or the better you draw — the more points you rack up. After all rounds are done, the player with the most points wins!
            </p>
        </div>

        <!-- Step by Step -->
        <div>
            <h2 class="text-2xl font-black uppercase tracking-tight mb-4 flex items-center gap-2">📋 Step-by-Step Game Flow</h2>
            <div class="space-y-4">

                <!-- Step 1 -->
                <div class="fun-card p-5 flex gap-5 items-start relative overflow-hidden slide-in">
                    <div class="step-num shrink-0 bg-pop-blue">1</div>
                    <div class="space-y-2 flex-1">
                        <h3 class="text-lg font-black uppercase tracking-tight">Create or Join a Room</h3>
                        <p class="text-gray-600 font-medium leading-relaxed">Enter your nickname and pick an avatar. Either <strong>create a new room</strong> (as the Host) or <strong>enter a room code</strong> shared by a friend to join their game. Rooms support 2–8 players.</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span class="tool-chip">🏠 Create Room</span>
                            <span class="tool-chip">🔑 Join with Code</span>
                            <span class="tool-chip">👤 Pick Avatar</span>
                        </div>
                    </div>
                    <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 rotate-12 select-none pointer-events-none">📱</div>
                </div>

                <!-- Step 2 -->
                <div class="fun-card p-5 flex gap-5 items-start relative overflow-hidden bg-pop-yellow/5 slide-in">
                    <div class="step-num shrink-0 bg-pop-yellow !text-ink">2</div>
                    <div class="space-y-2 flex-1">
                        <h3 class="text-lg font-black uppercase tracking-tight">Host Starts the Game</h3>
                        <p class="text-gray-600 font-medium leading-relaxed">Only the <strong>room host</strong> can start the game. Configure the number of rounds (1–10) and time per round (30–120 seconds) before starting. Once started, turns rotate automatically among all players.</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span class="tool-chip">🔢 1–10 Rounds</span>
                            <span class="tool-chip">⏱ 30–120s per round</span>
                            <span class="tool-chip">🔄 Auto Rotation</span>
                        </div>
                    </div>
                    <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 -rotate-12 select-none pointer-events-none">🎮</div>
                </div>

                <!-- Step 3 -->
                <div class="fun-card p-5 flex gap-5 items-start relative overflow-hidden slide-in">
                    <div class="step-num shrink-0 bg-pop-pink">3</div>
                    <div class="space-y-2 flex-1">
                        <h3 class="text-lg font-black uppercase tracking-tight">The Drawer Picks a Word</h3>
                        <p class="text-gray-600 font-medium leading-relaxed">When it's your turn, you'll be shown <strong>3 secret words</strong> with difficulty ratings (Easy / Medium / Hard). You have a few seconds to pick one — the other players only see that you're choosing, not what you picked!</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span class="badge bg-pop-green">Easy — Common words</span>
                            <span class="badge bg-pop-yellow">Medium — Trickier</span>
                            <span class="badge bg-pop-pink">Hard — Challenging</span>
                        </div>
                    </div>
                    <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 rotate-45 select-none pointer-events-none">🤫</div>
                </div>

                <!-- Step 4 -->
                <div class="fun-card p-5 flex gap-5 items-start relative overflow-hidden bg-pop-blue/5 slide-in">
                    <div class="step-num shrink-0 bg-pop-blue">4</div>
                    <div class="space-y-2 flex-1">
                        <h3 class="text-lg font-black uppercase tracking-tight">Draw Your Masterpiece</h3>
                        <p class="text-gray-600 font-medium leading-relaxed">Use the drawing toolbar to sketch your word on the canvas. Your drawing is streamed <strong>live</strong> to all other players in real-time. You cannot type hints — only draw!</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span class="tool-chip">🖌 10+ Colors</span>
                            <span class="tool-chip">📏 Brush sizes</span>
                            <span class="tool-chip">↩️ Undo strokes</span>
                            <span class="tool-chip">🗑 Clear canvas</span>
                        </div>
                    </div>
                    <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 -rotate-6 select-none pointer-events-none">🎨</div>
                </div>

                <!-- Step 5 -->
                <div class="fun-card p-5 flex gap-5 items-start relative overflow-hidden slide-in">
                    <div class="step-num shrink-0 bg-pop-green !text-ink">5</div>
                    <div class="space-y-2 flex-1">
                        <h3 class="text-lg font-black uppercase tracking-tight">Guessers Race to Answer</h3>
                        <p class="text-gray-600 font-medium leading-relaxed">While watching the drawing, type your guess in the chat box. The system automatically checks if your answer is correct — <span class="marker-highlight">case insensitive</span>. Once you guess correctly, you can still chat but can no longer reveal the word.</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span class="tool-chip">⌨️ Type in chat</span>
                            <span class="tool-chip">🟢 Auto-detected</span>
                            <span class="tool-chip">🎉 Confetti on correct!</span>
                        </div>
                    </div>
                    <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 rotate-45 select-none pointer-events-none">💬</div>
                </div>

                <!-- Step 6 -->
                <div class="fun-card p-5 flex gap-5 items-start relative overflow-hidden bg-pop-green/5 slide-in">
                    <div class="step-num shrink-0 bg-pop-orange">6</div>
                    <div class="space-y-2 flex-1">
                        <h3 class="text-lg font-black uppercase tracking-tight">Round Ends & Points Are Awarded</h3>
                        <p class="text-gray-600 font-medium leading-relaxed">The round ends when the timer hits zero or all players guess correctly. Points are awarded and the leaderboard updates instantly. Then the next player becomes the drawer!</p>
                    </div>
                    <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 -rotate-6 select-none pointer-events-none">🏆</div>
                </div>

            </div>
        </div>

        <!-- Scoring System -->
        <div class="fun-card p-6">
            <h2 class="text-2xl font-black uppercase tracking-tight mb-4 flex items-center gap-2">⭐ Scoring System</h2>
            <div class="space-y-1">
                <div class="score-row">
                    <div class="flex items-center gap-3"><span class="text-2xl">🥇</span><div><div class="font-black">First Correct Guess</div><div class="text-xs text-gray-400 font-bold">Guessing before anyone else</div></div></div>
                    <div class="badge bg-pop-yellow text-lg font-black">+300 pts</div>
                </div>
                <div class="score-row">
                    <div class="flex items-center gap-3"><span class="text-2xl">🥈</span><div><div class="font-black">Subsequent Correct Guesses</div><div class="text-xs text-gray-400 font-bold">Guessing after others</div></div></div>
                    <div class="badge bg-gray-100 text-lg font-black">+200 pts</div>
                </div>
                <div class="score-row">
                    <div class="flex items-center gap-3"><span class="text-2xl">⚡</span><div><div class="font-black">Speed Bonus</div><div class="text-xs text-gray-400 font-bold">Guessing in first 10 seconds</div></div></div>
                    <div class="badge bg-pop-blue text-lg font-black">+50 pts</div>
                </div>
                <div class="score-row">
                    <div class="flex items-center gap-3"><span class="text-2xl">🎨</span><div><div class="font-black">Drawer Bonus</div><div class="text-xs text-gray-400 font-bold">Per player who guesses your word</div></div></div>
                    <div class="badge bg-pop-pink text-lg font-black">+50 pts each</div>
                </div>
                <div class="score-row">
                    <div class="flex items-center gap-3"><span class="text-2xl">😔</span><div><div class="font-black">Nobody Guesses</div><div class="text-xs text-gray-400 font-bold">Round times out with 0 correct</div></div></div>
                    <div class="badge bg-red-100 text-red-600 text-lg font-black">+0 pts</div>
                </div>
            </div>
        </div>

        <!-- Drawing Tools -->
        <div class="fun-card p-6 bg-ink text-white">
            <h2 class="text-2xl font-black uppercase tracking-tight mb-4 text-pop-yellow flex items-center gap-2">🖌️ Drawing Tools Guide</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">🎨</span>
                    <div>
                        <div class="font-black text-white">Color Palette</div>
                        <div class="text-gray-300 font-medium text-sm">10+ colors including black, gray, red, pink, purple, blue, green, yellow, orange, and white (for erasing areas).</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-2xl">📏</span>
                    <div>
                        <div class="font-black text-white">Brush Sizes</div>
                        <div class="text-gray-300 font-medium text-sm">Choose from Small (2px), Medium (8px), Large (20px), and Extra Large (40px) brush sizes.</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-2xl">↩️</span>
                    <div>
                        <div class="font-black text-white">Undo</div>
                        <div class="text-gray-300 font-medium text-sm">Remove your last stroke with the Undo button. Syncs to all viewers instantly!</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-2xl">🗑️</span>
                    <div>
                        <div class="font-black text-white">Clear Canvas</div>
                        <div class="text-gray-300 font-medium text-sm">Wipe the entire canvas clean and start fresh. Use carefully — this affects all players!</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-2xl">⬜</span>
                    <div>
                        <div class="font-black text-white">Eraser</div>
                        <div class="text-gray-300 font-medium text-sm">Select the white color to use it as an eraser. Great for touching up specific areas.</div>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <span class="text-2xl">🤌</span>
                    <div>
                        <div class="font-black text-white">Touch Support</div>
                        <div class="text-gray-300 font-medium text-sm">Full touch and stylus support on mobile. Draw with your finger just as smoothly as a mouse!</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reactions -->
        <div class="fun-card p-6 bg-pop-pink/10">
            <h2 class="text-2xl font-black uppercase tracking-tight mb-3 flex items-center gap-2">😂 Reactions & Emotes</h2>
            <p class="text-gray-600 font-medium leading-relaxed mb-4">While watching someone draw, you can send live reactions that appear floating over the canvas for everyone to see! These are just for fun and don't affect scoring.</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="fun-card-flat p-3 text-center">
                    <div class="text-3xl mb-1">😂</div>
                    <div class="font-black text-xs uppercase">LOL</div>
                </div>
                <div class="fun-card-flat p-3 text-center">
                    <div class="text-3xl mb-1">❤️</div>
                    <div class="font-black text-xs uppercase">Love it</div>
                </div>
                <div class="fun-card-flat p-3 text-center">
                    <div class="text-3xl mb-1">😮</div>
                    <div class="font-black text-xs uppercase">Wow</div>
                </div>
                <div class="fun-card-flat p-3 text-center">
                    <div class="text-3xl mb-1">👏</div>
                    <div class="font-black text-xs uppercase">Clap</div>
                </div>
            </div>
        </div>

        <!-- Pro Tips -->
        <div class="fun-card p-6 bg-ink text-white">
            <h2 class="text-2xl font-black uppercase tracking-widest mb-5 text-pop-yellow flex items-center gap-2">✨ Pro Tips</h2>
            <ul class="space-y-4">
                <li class="flex items-start gap-4">
                    <span class="text-pop-yellow text-xl mt-0.5">💡</span>
                    <span class="font-bold text-gray-300"><span class="text-white font-black">Start with the outline.</span> Draw the basic shape first, then add details. Guessers recognize shapes more than details.</span>
                </li>
                <li class="flex items-start gap-4">
                    <span class="text-pop-blue text-xl mt-0.5">⚡</span>
                    <span class="font-bold text-gray-300"><span class="text-white font-black">Guess continuously.</span> Keep typing multiple guesses — there's no penalty for wrong ones. Try synonyms!</span>
                </li>
                <li class="flex items-start gap-4">
                    <span class="text-pop-green text-xl mt-0.5">🔢</span>
                    <span class="font-bold text-gray-300"><span class="text-white font-black">Count the dashes.</span> The word display in the top bar shows you how many letters are in the word — use it!</span>
                </li>
                <li class="flex items-start gap-4">
                    <span class="text-pop-pink text-xl mt-0.5">🎨</span>
                    <span class="font-bold text-gray-300"><span class="text-white font-black">Use color as a clue.</span> Drawing the sun in yellow or blood in red gives instant context without "cheating".</span>
                </li>
                <li class="flex items-start gap-4">
                    <span class="text-pop-orange text-xl mt-0.5">🏃</span>
                    <span class="font-bold text-gray-300"><span class="text-white font-black">Hard words = more risk but same reward.</span> Nobody is forced to guess a hard word — but the drawer still earns bonus points per guesser regardless of difficulty.</span>
                </li>
                <li class="flex items-start gap-4">
                    <span class="text-pop-yellow text-xl mt-0.5">📱</span>
                    <span class="font-bold text-gray-300"><span class="text-white font-black">On mobile?</span> Tap the pull handle at the bottom to collapse the leaderboard and give yourself more canvas space to draw!</span>
                </li>
            </ul>
        </div>

        <!-- FAQ -->
        <div class="fun-card p-6">
            <h2 class="text-2xl font-black uppercase tracking-tight mb-4 flex items-center gap-2">❓ FAQ</h2>
            <div class="space-y-3">
                <details class="faq-item fun-card-flat p-4">
                    <summary class="font-black text-sm uppercase tracking-wide">What happens if the drawer leaves mid-round?</summary>
                    <p class="mt-3 text-gray-600 font-medium text-sm leading-relaxed">The round will continue until the timer runs out. The next player in the rotation will become the drawer for the next round. The room host can also restart if needed.</p>
                </details>
                <details class="faq-item fun-card-flat p-4">
                    <summary class="font-black text-sm uppercase tracking-wide">Can I type partial words to guess?</summary>
                    <p class="mt-3 text-gray-600 font-medium text-sm leading-relaxed">The system looks for an exact match (ignoring capital letters). So "BICYCLE" and "bicycle" both work, but "bicy" or "bikes" will not be counted as correct.</p>
                </details>
                <details class="faq-item fun-card-flat p-4">
                    <summary class="font-black text-sm uppercase tracking-wide">What if I refresh the page during a game?</summary>
                    <p class="mt-3 text-gray-600 font-medium text-sm leading-relaxed">Your session is saved! Refreshing will bring you right back into the same room and round, with all your score and progress intact.</p>
                </details>
                <details class="faq-item fun-card-flat p-4">
                    <summary class="font-black text-sm uppercase tracking-wide">Can the drawer see the chat?</summary>
                    <p class="mt-3 text-gray-600 font-medium text-sm leading-relaxed">Yes, the drawer can see all chat messages while drawing. However, correct guesses are shown as "🎉 [Player] discovered the word!" rather than revealing the actual guess text — so there's no unfair help.</p>
                </details>
                <details class="faq-item fun-card-flat p-4">
                    <summary class="font-black text-sm uppercase tracking-wide">Is there a minimum number of players?</summary>
                    <p class="mt-3 text-gray-600 font-medium text-sm leading-relaxed">You can technically play with just 2 people — one draws, one guesses. But the game is much more fun with 4 or more players! Rooms support up to 8 players.</p>
                </details>
                <details class="faq-item fun-card-flat p-4">
                    <summary class="font-black text-sm uppercase tracking-wide">How do I install the app on my phone?</summary>
                    <p class="mt-3 text-gray-600 font-medium text-sm leading-relaxed">On Android/Chrome: A "Install App" prompt will appear at the bottom of the home page. On iOS/Safari: Tap the Share icon → "Add to Home Screen". Once installed, the game runs fullscreen with no browser bars!</p>
                </details>
            </div>
        </div>

        <!-- CTA -->
        <div class="text-center space-y-4 py-4">
            <div class="text-5xl float">🚀</div>
            <h2 class="text-3xl font-black">Ready to Play?</h2>
            <a href="<?= $base_path ?>" class="inline-block px-8 py-4 bg-pop-yellow border-[3px] border-ink rounded-2xl font-black text-lg uppercase tracking-widest shadow-[6px_6px_0_#1e1e1e] hover:shadow-[8px_8px_0_#1e1e1e] hover:-translate-x-1 hover:-translate-y-1 transition-all">
                Let's Draw! 🎨
            </a>
        </div>

        <div class="text-center py-4">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-400">designed with love by <span class="text-ink">irfan manzoor</span></p>
        </div>
    </div>

</body>
</html>

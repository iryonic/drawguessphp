<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Draw & Guess</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Fredoka', sans-serif; background-color: #fdfbf7; background-image: radial-gradient(#f1f3f5 1.2px, transparent 1.2px), radial-gradient(#f1f3f5 1.2px, transparent 1.2px); background-size: 24px 24px; background-position: 0 0, 12px 12px; }
        .neo-border { border: 3px solid #1e1e1e; box-shadow: 6px 6px 0px #1e1e1e; }
        .neo-btn { border: 3px solid #1e1e1e; box-shadow: 4px 4px 0px #1e1e1e; transition: all 0.1s; }
        .neo-btn:active { transform: translate(2px, 2px); box-shadow: 2px 2px 0px #1e1e1e; }
        .rating-star { cursor: pointer; font-size: 2rem; transition: transform 0.2s; }
        .rating-star:hover { transform: scale(1.2); }
        .rating-star.active { color: #facc15; text-shadow: 0 0 10px rgba(250, 204, 21, 0.5); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">

    <div class="max-w-md w-full bg-white neo-border p-8 rounded-3xl relative overflow-hidden">
        <!-- Decorative elements -->
        <div class="absolute -top-10 -right-10 w-32 h-32 bg-yellow-400 rounded-full opacity-20"></div>
        <div class="absolute -bottom-10 -left-10 w-24 h-24 bg-blue-400 rounded-full opacity-20"></div>

        <div class="text-center mb-8 relative">
            <h1 class="text-4xl font-black text-slate-800 tracking-tight mb-2">FEEDBACK</h1>
            <p class="text-slate-500 font-medium italic">Help us make DrawGuess even better! 🖊️</p>
        </div>

        <form id="feedback-form" class="space-y-6 relative">
            <!-- Username -->
            <div>
                <label class="block text-sm font-black uppercase tracking-widest text-slate-400 mb-2">Your Name</label>
                <input type="text" id="username" class="w-full bg-slate-50 border-[3px] border-slate-800 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-4 focus:ring-yellow-400/20 transition-all" placeholder="Enter your name...">
            </div>

            <!-- Rating -->
            <div>
                <label class="block text-sm font-black uppercase tracking-widest text-slate-400 mb-2 text-center">Your Rating</label>
                <div class="flex justify-center gap-2 mb-2" id="star-container">
                    <span class="rating-star" data-value="1">★</span>
                    <span class="rating-star" data-value="2">★</span>
                    <span class="rating-star" data-value="3">★</span>
                    <span class="rating-star" data-value="4">★</span>
                    <span class="rating-star" data-value="5">★</span>
                </div>
                <input type="hidden" id="rating" value="0">
            </div>

            <!-- Message -->
            <div>
                <label class="block text-sm font-black uppercase tracking-widest text-slate-400 mb-2">Message</label>
                <textarea id="message" rows="4" class="w-full bg-slate-50 border-[3px] border-slate-800 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-4 focus:ring-yellow-400/20 transition-all resize-none" placeholder="What's on your mind? Suggestions, bugs, or love..."></textarea>
            </div>

            <button type="submit" class="w-full neo-btn py-4 bg-yellow-400 text-slate-900 font-black text-xl uppercase tracking-widest rounded-2xl hover:bg-yellow-300">
                Send Feedback 🚀
            </button>
        </form>

        <div id="success-msg" class="hidden text-center py-10">
            <div class="text-6xl mb-4">🎉</div>
            <h2 class="text-3xl font-black text-slate-800 mb-2">THANK YOU!</h2>
            <p class="text-slate-500 font-medium mb-6">Your feedback has been beamed to our servers!</p>
            <button onclick="window.location.href='home'" class="neo-btn px-8 py-3 bg-slate-800 text-white font-black rounded-xl">Back to Game</button>
        </div>
    </div>

    <script>
        const stars = document.querySelectorAll('.rating-star');
        const ratingInput = document.getElementById('rating');
        const form = document.getElementById('feedback-form');
        const successMsg = document.getElementById('success-msg');

        stars.forEach(star => {
            star.onclick = () => {
                const val = parseInt(star.dataset.value);
                ratingInput.value = val;
                stars.forEach((s, i) => {
                    if (i < val) s.classList.add('active');
                    else s.classList.remove('active');
                });
            };
        });

        form.onsubmit = async (e) => {
            e.preventDefault();
            
            const rating = parseInt(ratingInput.value);
            if (rating === 0) {
                alert('Please select a rating! ★');
                return;
            }

            const btn = form.querySelector('button');
            btn.disabled = true;
            btn.innerText = 'SENDING...';

            const player = JSON.parse(localStorage.getItem('dg_player') || '{}');
            const data = new URLSearchParams({
                token: player.token || '',
                username: document.getElementById('username').value,
                message: document.getElementById('message').value,
                rating: ratingInput.value
            });

            try {
                const res = await fetch('api/feedback.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: data
                }).then(r => r.json());

                if (res.success) {
                    form.classList.add('hidden');
                    successMsg.classList.remove('hidden');
                } else {
                    alert(res.error || 'Something went wrong');
                    btn.disabled = false;
                    btn.innerText = 'Send Feedback 🚀';
                }
            } catch (err) {
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerText = 'Send Feedback 🚀';
            }
        };
    </script>
</body>
</html>

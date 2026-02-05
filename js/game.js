const player = JSON.parse(localStorage.getItem('dg_player') || '{}');
if (!player.token) window.location.href = 'index.php';

const roomCodeDisplay = document.getElementById('room-code-display');
const playerList = document.getElementById('player-list');
const canvas = document.getElementById('game-canvas');
const ctx = canvas.getContext('2d');
const chatBox = document.getElementById('chat-box');
const overlay = document.getElementById('overlay');
const overlayTitle = document.getElementById('overlay-title'); // Added
const overlaySubtitle = document.getElementById('overlay-subtitle'); // Added
const wordSelect = document.getElementById('word-selection'); // Added
const startBtn = document.getElementById('start-btn'); // Added
const wordDisplay = document.getElementById('word-display');
const timerEl = document.getElementById('timer');
const timerProgress = document.getElementById('timer-progress');
const turnNotif = document.getElementById('turn-notification');

// Mobile Tabs
const tabBtns = {
    draw: document.getElementById('tab-draw'),
    chat: document.getElementById('tab-chat'),
    rank: document.getElementById('tab-rank')
};
const views = {
    draw: document.getElementById('view-draw'),
    chat: document.getElementById('view-chat'),
    rank: document.getElementById('view-rank')
};

// Game State
let gameState = {
    status: 'lobby',
    roundId: 0,
    lastStrokeId: 0, // Reset on load to fetch full drawing
    lastMsgId: 0,    // Reset on load to fetch full chat
    isDrawer: false,
    color: '#000000',
    size: 5,
    myTurn: false,
    endTime: 0,
    totalTime: 60
};

// Update persist (noop now, kept for compatibility if called elsewhere)
function updatePersist() {
    // sessionStorage.setItem('dg_lastStrokeId', gameState.lastStrokeId);
    // sessionStorage.setItem('dg_lastMsgId', gameState.lastMsgId);
}

// Canvas State
let painting = false;
let pointsBuffer = [];
let lastPos = { x: 0, y: 0 };
let strokeHistory = [];

// Local Timer
let timerInterval = null;

// Init
if (roomCodeDisplay) roomCodeDisplay.innerText = player.room_code || '????';
resizeCanvas();

// Default to Chat on Mobile
if (window.innerWidth < 1024) {
    switchTab('chat');
}

// Window Resize Handling
window.addEventListener('resize', resizeCanvas);

function resizeCanvas() {
    const rect = canvas.getBoundingClientRect();
    // Round to avoid sub-pixel rendering issues which can cause drift
    canvas.width = Math.floor(rect.width);
    canvas.height = Math.floor(rect.height);
}

// ... (lines 80-211 skipped) ...


// Listeners
if (canvas) {
    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('mouseleave', endDraw);

    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        const touch = e.touches[0];
        const mouseEvent = new MouseEvent("mousedown", { clientX: touch.clientX, clientY: touch.clientY });
        canvas.dispatchEvent(mouseEvent);
    }, { passive: false });

    canvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        const touch = e.touches[0];
        const mouseEvent = new MouseEvent("mousemove", { clientX: touch.clientX, clientY: touch.clientY });
        canvas.dispatchEvent(mouseEvent);
    }, { passive: false });

    canvas.addEventListener('touchend', (e) => {
        e.preventDefault();
        const mouseEvent = new MouseEvent("mouseup", {});
        canvas.dispatchEvent(mouseEvent);
    });
}

// Polling
setTimeout(syncState, 100); // Run immediately
setInterval(syncState, 2000);
setInterval(syncDraw, 200);
setInterval(syncChat, 1000);
setInterval(sendStrokes, 300);

if (timerInterval) clearInterval(timerInterval);
timerInterval = setInterval(updateLocalTimer, 1000);

// --- Functions --- //

// Mobile Tab Switching
function switchTab(tab) {
    const drawView = document.getElementById('view-draw');
    const mobilePanel = document.getElementById('mobile-panel');
    const mobileChat = document.getElementById('view-chat-mobile');
    const mobileRank = document.getElementById('view-rank');

    // Reset Buttons
    Object.values(tabBtns).forEach(btn => {
        if (btn) {
            btn.classList.add('text-gray-400');
            btn.classList.remove('text-ink', 'bg-gray-100');
            const icon = btn.querySelector('span');
            if (icon) {
                icon.classList.add('grayscale', 'opacity-50');
                icon.classList.remove('opacity-100');
            }
        }
    });

    // Active Button
    if (tabBtns[tab]) {
        tabBtns[tab].classList.remove('text-gray-400');
        tabBtns[tab].classList.add('text-ink', 'bg-gray-100');
        const icon = tabBtns[tab].querySelector('span');
        if (icon) {
            icon.classList.remove('grayscale', 'opacity-50');
            icon.classList.add('opacity-100');
        }
    }

    // Logic for Views (Split Screen)
    if (window.innerWidth < 1024) {
        if (tab === 'draw') {
            // Full Screen Canvas mode
            drawView.classList.remove('h-[50vh]');
            drawView.classList.add('h-full', 'flex-1');

            mobilePanel.style.height = '0';
        } else {
            // Split Screen mode
            drawView.classList.remove('h-full', 'flex-1');
            drawView.classList.add('h-[50vh]'); // Shrink Canvas to 50%

            mobilePanel.classList.remove('h-0');
            mobilePanel.classList.add('flex-1');

            // Toggle Content
            mobileChat.classList.add('hidden');
            mobileRank.classList.add('hidden');

            if (tab === 'chat') mobileChat.classList.remove('hidden');
            if (tab === 'rank') mobileRank.classList.remove('hidden');
        }
        resizeCanvas();
    }
}

function updateLocalTimer() {
    // If not in a state with a timer, clear timer visual
    const statesWithTimer = ['drawing', 'choosing', 'countdown', 'ended', 'game_over'];
    if (!statesWithTimer.includes(gameState.status)) {
        if (timerEl) timerEl.innerText = "--";
        if (timerProgress) timerProgress.style.strokeDashoffset = 0;
        return;
    }

    const now = Date.now() / 1000;
    // Use Math.ceil so 59.9s shows as 60, and 0.1s shows as 1. 0 is 0.
    let left = gameState.endTime > 0 ? Math.max(0, Math.ceil(gameState.endTime - now)) : 0;

    if (timerEl) {
        timerEl.innerText = left;
        // Pulse red and play tick when low time
        if (left <= 10 && left > 0) {
            timerEl.classList.add('text-red-600', 'animate-pulse');
            // Ambient Tension: Play tick sound during drawing phase
            if (gameState.status === 'drawing') {
                try { sfx.play('tick'); } catch (e) { }
            }
        } else {
            timerEl.classList.remove('text-red-600', 'animate-pulse');
        }
    }

    if (timerProgress) {
        let total = gameState.totalTime || 60;
        if (gameState.status === 'choosing') total = 7;
        if (gameState.status === 'countdown') total = 3;
        if (gameState.status === 'ended') total = 10;
        if (gameState.status === 'game_over') total = 15;

        // If time is up, offset is 100 (empty). If full, 0.
        // pct = percentage remaining. 1.0 = full. 0.0 = empty.
        const pct = Math.min(1, Math.max(0, left / total));
        // StrokeDashArray is 100. Offset 0 = Full. Offset 100 = Empty.
        const offset = 100 - (pct * 100);
        timerProgress.style.strokeDashoffset = offset;
    }
}

function getNormalizedPos(evt) {
    const rect = canvas.getBoundingClientRect();
    // Use clientX/Y which are relative to the viewport, same as rect.
    // Use rect.width/height (displayed size) for normalization.
    return {
        x: (evt.clientX - rect.left) / rect.width,
        y: (evt.clientY - rect.top) / rect.height
    };
}

function startDraw(e) {
    if (!gameState.myTurn) return;
    painting = true;
    lastPos = getNormalizedPos(e);
    pointsBuffer = [lastPos];
    drawDot(lastPos.x, lastPos.y, gameState.color, gameState.size);
}

function drawDot(nx, ny, color, size) {
    const x = nx * canvas.width;
    const y = ny * canvas.height;
    ctx.beginPath();
    ctx.arc(x, y, size / 2, 0, Math.PI * 2);
    ctx.fillStyle = color;
    ctx.fill();
    strokeHistory.push({ type: 'dot', x: nx, y: ny, color, size });
}

function drawLine(nx1, ny1, nx2, ny2, color, size) {
    const x1 = nx1 * canvas.width;
    const y1 = ny1 * canvas.height;
    const x2 = nx2 * canvas.width;
    const y2 = ny2 * canvas.height;

    ctx.lineWidth = size;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = color;

    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.stroke();

    strokeHistory.push({ type: 'line', x1: nx1, y1: ny1, x2: nx2, y2: ny2, color, size });
}

function draw(e) {
    if (!painting || !gameState.myTurn) return;
    const pos = getNormalizedPos(e);
    drawLine(lastPos.x, lastPos.y, pos.x, pos.y, gameState.color, gameState.size);
    lastPos = pos;
    pointsBuffer.push(pos);
}

function endDraw() {
    if (!painting) return;
    painting = false;
    sendStrokes();
}

async function sendStrokes() {
    if (pointsBuffer.length === 0) return;

    // Prevention of gaps: If existing buffer only has the carry-over point, don't resend
    if (painting && pointsBuffer.length === 1) return;

    const pointsToSend = pointsBuffer;
    const data = {
        token: player.token,
        action: 'draw',
        color: gameState.color,
        size: gameState.size,
        points: JSON.stringify(pointsToSend)
    };

    // Critical Fix: Retain last point to bridge the gap to the next batch
    if (painting && pointsBuffer.length > 0) {
        const last = pointsBuffer[pointsBuffer.length - 1];
        pointsBuffer = [last];
    } else {
        pointsBuffer = [];
    }

    await fetch('api/draw_sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data)
    });
}


async function syncState() {
    try {
        const response = await fetch('api/game_state.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ token: player.token, action: 'sync' })
        });
        const res = await response.json();

        // Handle Session Error
        if (res.error === 'Invalid Session') {
            alert('Session expired. Redirecting to home.');
            window.location.href = 'index.php';
            return;
        }

        if (res.data && res.data.room) {
            if (roomCodeDisplay) roomCodeDisplay.textContent = res.data.room.room_code || '????';
        }

        if (!res.data) {
            console.warn("Sync: No data received", res);
            return;
        }
        const data = res.data;

        // Round Change / Data Sync
        if (data.round.id != gameState.roundId) {
            if (data.round.id > 0 && data.round.id > gameState.roundId) {
                gameState.lastStrokeId = 0;
                strokeHistory = [];
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                updatePersist();
                if (gameState.roundId !== 0) {
                    try { sfx.play('start'); } catch (e) { }
                }
            }
        }

        gameState.roundId = data.round.id;
        const oldStatus = gameState.status;
        gameState.status = data.round.status || 'lobby';

        if (oldStatus === 'choosing' && gameState.status !== 'choosing') {
            if (wordSelect) wordSelect.innerHTML = '';
        }

        // Round End Sound
        if (oldStatus === 'drawing' && gameState.status === 'ended') {
            try { sfx.play('ding'); } catch (e) { }
        }

        if (document.getElementById('game-status-text'))
            document.getElementById('game-status-text').textContent = gameState.status === 'lobby' ? 'LOBBY' : gameState.status.toUpperCase();

        if (document.getElementById('current-round')) document.getElementById('current-round').textContent = data.room.current_round;
        if (document.getElementById('max-rounds')) document.getElementById('max-rounds').textContent = data.room.max_rounds;

        if (data.round.time_left !== undefined) {
            const now = Math.floor(Date.now() / 1000);
            gameState.endTime = now + data.round.time_left;
            if (data.room.round_duration) gameState.totalTime = parseInt(data.room.round_duration);
        }

        updatePlayers(data.players, data.round.drawer_id);

        // Turn Logic
        const isMe = data.me == data.round.drawer_id;
        const wasMe = gameState.myTurn;
        gameState.myTurn = (gameState.status === 'drawing' && isMe);

        if (gameState.myTurn && !wasMe && gameState.status === 'drawing') {
            showTurnNotification();
            try { sfx.play('start'); } catch (e) { }
        }

        const tools = document.getElementById('drawing-tools');
        if (gameState.myTurn) {
            if (tools) tools.classList.remove('hidden');
        } else {
            if (tools) tools.classList.add('hidden');
        }

        // Overlays
        const overlayTitle = document.getElementById('overlay-title');
        const overlaySubtitle = document.getElementById('overlay-subtitle');
        const wordSelect = document.getElementById('word-selection');
        const startBtn = document.getElementById('start-btn');

        if (gameState.status === 'lobby') {
            if (overlay) overlay.classList.remove('hidden');
            if (overlayTitle) overlayTitle.textContent = "WAITING FOR PLAYERS";
            if (overlaySubtitle) {
                overlaySubtitle.textContent = "Share the room code: " + (data.room.room_code || '????');
                overlaySubtitle.classList.remove('hidden');
            }
            if (wordSelect) wordSelect.classList.add('hidden');

            // Use improved flags
            const mePlayer = data.players.find(p => p.is_me);
            const isHost = mePlayer ? mePlayer.is_host : false;

            if (startBtn) {
                if (isHost) {
                    startBtn.classList.remove('hidden');
                    startBtn.classList.add('flex'); // Ensure flex display
                } else {
                    startBtn.classList.add('hidden');
                    startBtn.classList.remove('flex');
                }
            }
        } else if (gameState.status === 'choosing') {
            if (overlay) overlay.classList.remove('hidden');
            if (startBtn) startBtn.classList.add('hidden');
            if (isMe) {
                if (overlayTitle) overlayTitle.textContent = "IT'S YOUR TURN!";
                if (overlaySubtitle) {
                    const left = Math.max(0, Math.ceil(gameState.endTime - Date.now() / 1000));
                    overlaySubtitle.innerHTML = `Choose a Word to Draw <br> <span class="text-3xl font-black text-pop-pink animate-pulse">${left}s</span>`;
                    overlaySubtitle.classList.remove('hidden');
                }
                if (wordSelect) {
                    wordSelect.classList.remove('hidden');
                    const options = data.round.options || data.words || [];
                    if (options.length > 0 && wordSelect.children.length === 0) {
                        options.forEach(w => {
                            const btn = document.createElement('button');
                            btn.className = "bg-pop-blue hover:bg-sky-300 border-2 border-ink px-6 py-4 rounded-xl text-black font-bold text-lg hover:scale-105 transition shadow-[4px_4px_0px_#000]";
                            btn.textContent = `${w.word}`;
                            const badge = w.difficulty == 'easy' ? '🟢' : (w.difficulty == 'medium' ? '🟡' : '🔴');
                            btn.innerHTML += ` <span class="text-xs ml-2">${badge}</span>`;
                            btn.onclick = () => selectWord(w.id);
                            wordSelect.appendChild(btn);
                        });
                    }
                }
            } else {
                if (overlayTitle) overlayTitle.textContent = "DRAWER IS CHOOSING";
                if (overlaySubtitle) {
                    overlaySubtitle.textContent = "Get ready to guess!";
                    overlaySubtitle.classList.remove('hidden');
                }
                if (wordSelect) wordSelect.classList.add('hidden');
            }
        } else if (gameState.status === 'countdown') {
            if (overlay) overlay.classList.remove('hidden');
            if (overlayTitle) overlayTitle.textContent = "GET READY!";
            if (overlaySubtitle) {
                const left = data.round.time_left || 0;
                overlaySubtitle.innerHTML = `<div class="text-6xl font-black text-ink my-4 animate-bounce">${left}</div><div class="text-sm font-bold text-gray-500 uppercase">Guess the drawing to win points!</div>`;
                overlaySubtitle.classList.remove('hidden');
            }
            if (wordSelect) wordSelect.classList.add('hidden');
            if (startBtn) startBtn.classList.add('hidden');
            if (wordDisplay) wordDisplay.textContent = ''; // Clear word display during countdown
            ctx.clearRect(0, 0, canvas.width, canvas.height); // Clear

        } else if (gameState.status === 'drawing') {
            if (overlay) overlay.classList.add('hidden');
            if (data.round.word) {
                if (wordDisplay) wordDisplay.textContent = data.round.word;
            } else {
                if (wordDisplay) wordDisplay.textContent = "";
            }

            if (data.round.id != gameState.roundId) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                strokeHistory = [];
                gameState.roundId = data.round.id;
                gameState.lastStrokeId = 0;
            }
        } else if (gameState.status === 'ended') {
            if (overlay) overlay.classList.remove('hidden');
            if (overlayTitle) overlayTitle.textContent = `ROUND OVER!`;

            const nextIn = data.round.time_left || 0;
            if (overlaySubtitle) {
                overlaySubtitle.innerHTML = `The word was: <span class="font-black text-2xl mx-2 text-ink uppercase">${data.round.word}</span><br><div class="text-xs font-bold text-gray-400 mt-4 uppercase tracking-widest">Next turn in ${nextIn}s</div>`;
                overlaySubtitle.classList.remove('hidden');
            }
            if (wordSelect) wordSelect.classList.add('hidden');
            if (startBtn) startBtn.classList.add('hidden');

        } else if (gameState.status === 'finished' || gameState.status === 'game_over') {
            if (overlay) overlay.classList.remove('hidden');

            // Find Winner
            const winner = data.players.length > 0 ? data.players[0] : { username: 'Nobody', avatar: '👻' };

            if (overlayTitle) overlayTitle.textContent = `GAME OVER!`;

            const timeLeft = data.round.time_left || 0;
            if (overlaySubtitle) {
                overlaySubtitle.innerHTML = `
                    <div class="mb-4">
                        <div class="text-6xl mb-2 bounce-slow">${winner.avatar}</div>
                        <div class="text-2xl font-black text-ink uppercase">${winner.username}</div>
                        <div class="text-lg font-mono font-bold text-pop-purple border-2 border-dashed border-pop-purple rounded-lg px-3 py-1 mt-2 inline-block bg-purple-50">${winner.score} PTS</div>
                    </div>
                    ${gameState.status === 'game_over' ? `<div class="text-xs font-extrabold text-gray-500 mt-8 uppercase tracking-widest animate-pulse">Restarting in ${timeLeft}s...</div>` : '<div class="text-xs font-bold text-gray-400 mt-8">Calculating results...</div>'}
                `;
                overlaySubtitle.classList.remove('hidden');
            }

            if (wordSelect) wordSelect.classList.add('hidden');
            if (startBtn) startBtn.classList.add('hidden');

            // Switch to rank tab on mobile automatically
            if (window.innerWidth < 1024) switchTab('rank');

            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    } catch (e) { console.error("SyncState Error:", e); }
}

function showTurnNotification() {
    if (turnNotif) {
        turnNotif.classList.remove('opacity-0');
        setTimeout(() => {
            turnNotif.classList.add('opacity-0');
        }, 3000);
    }
}

function updatePlayers(players, drawerId) {
    const lists = [document.getElementById('player-list'), document.getElementById('player-list-mobile')];

    // Sort players
    players.sort((a, b) => b.score - a.score);

    lists.forEach(list => {
        if (!list) return;
        list.innerHTML = '';
        players.forEach((p, index) => {
            const isDrawer = p.id == drawerId;
            const div = document.createElement('div');
            // Compact Ranking with trophy for #1
            const rank = index === 0 ? '👑' : (index + 1);
            div.className = `p-3 rounded-xl flex items-center gap-3 transition-all border-2 ${isDrawer ? 'border-pop-yellow bg-yellow-50 shadow-sm' : 'border-gray-200 bg-white'}`;
            div.innerHTML = `
                <div class="font-bold text-gray-500 w-5 text-center text-sm">${rank}</div>
                <div class="text-2xl">${p.avatar}</div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm md:text-base flex justify-between items-center truncate">
                        <span class="truncate text-ink">${p.username}</span>
                        ${isDrawer ? '<span class="text-xs ml-1 bg-yellow-300 px-1 rounded border border-black">✏️</span>' : ''}
                    </div>
                    <div class="text-xs text-indigo-500 font-mono font-bold">${p.score} pts</div>
                </div>
            `;
            list.appendChild(div);
        });
    });
}

async function selectWord(wid) {
    try { sfx.play('pop'); } catch (e) { }
    await fetch('api/game_state.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: player.token, action: 'select_word', word_id: wid })
    });
}

async function startGame() {
    try { sfx.play('pop'); } catch (e) { }
    await fetch('api/game_state.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: player.token, action: 'start_game' })
    });
}

// Queue for incoming strokes (smoother rendering)
let strokeQueue = [];

// Start render loop
requestAnimationFrame(renderLoop);

function renderLoop() {
    if (strokeQueue.length > 0) {
        const start = performance.now();
        // Process queue with 10ms frame budget to prevent freeze
        while (strokeQueue.length > 0) {
            if (performance.now() - start > 10) break; // Yield to next frame

            const s = strokeQueue.shift();

            if (s.color === 'CLEAR') {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                strokeHistory = [];
                continue;
            }

            if (s.color === 'UNDO') {
                strokeHistory.pop(); // Remove last known stroke
                // Full Redraw
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                const tempHistory = [...strokeHistory];
                strokeHistory = []; // Reset because drawLine pushes to it
                tempHistory.forEach(oldS => {
                    if (oldS.type === 'dot') drawDot(oldS.x, oldS.y, oldS.color, oldS.size);
                    else drawLine(oldS.x1, oldS.y1, oldS.x2, oldS.y2, oldS.color, oldS.size);
                });
                continue;
            }

            const points = s.points;
            if (!points || points.length < 1) continue;

            // Draw stroke
            ctx.beginPath();
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = s.color;
            ctx.fillStyle = s.color;

            if (points.length === 1) {
                // Dot
                const p = points[0];
                ctx.arc(p.x * canvas.width, p.y * canvas.height, s.size / 2, 0, Math.PI * 2);
                ctx.fill();
            } else {
                // Smooth Line
                ctx.lineWidth = s.size;
                const w = canvas.width;
                const h = canvas.height;

                if (points.length > 2) {
                    // Quadratic Curve Interpolation for smoothness
                    ctx.moveTo(points[0].x * w, points[0].y * h);

                    for (let i = 1; i < points.length - 2; i++) {
                        const xc = (points[i].x + points[i + 1].x) / 2;
                        const yc = (points[i].y + points[i + 1].y) / 2;
                        ctx.quadraticCurveTo(points[i].x * w, points[i].y * h, xc * w, yc * h);
                    }
                    // Curve through the last two points
                    ctx.quadraticCurveTo(
                        points[points.length - 2].x * w,
                        points[points.length - 2].y * h,
                        points[points.length - 1].x * w,
                        points[points.length - 1].y * h
                    );
                } else {
                    // Fallback for short lines
                    ctx.moveTo(points[0].x * w, points[0].y * h);
                    for (let i = 1; i < points.length; i++) {
                        ctx.lineTo(points[i].x * w, points[i].y * h);
                    }
                }
                ctx.stroke();
            }
        }
    }
    requestAnimationFrame(renderLoop);
}

async function syncDraw() {
    // If I'm drawing, skip fetch to avoid latency/double-draw, 
    // UNLESS I just refreshed (ID=0), then I must fetch history.
    if (gameState.myTurn && gameState.status === 'drawing' && gameState.lastStrokeId > 0) return;

    if (gameState.status !== 'drawing' && gameState.status !== 'ended') return;

    try {
        const res = await (await fetch(`api/draw_sync.php?token=${player.token}&action=fetch&last_id=${gameState.lastStrokeId}`)).json();
        if (res.data && res.data.strokes) {
            // Sort just in case DB doesn't ensure order (id usually does)
            res.data.strokes.sort((a, b) => a.id - b.id);

            res.data.strokes.forEach(s => {
                if (s.id > gameState.lastStrokeId) gameState.lastStrokeId = s.id;
                // Add to queue for render loop
                strokeQueue.push(s);
            });
            updatePersist();
        }
    } catch (e) { }
}

async function syncChat() {
    try {
        const res = await (await fetch(`api/chat.php?token=${player.token}&action=fetch&last_id=${gameState.lastMsgId}`)).json();
        if (res.data && res.data.messages) {
            res.data.messages.forEach(m => {
                if (m.id > gameState.lastMsgId) gameState.lastMsgId = m.id;

                const username = m.username || 'System';

                const createMsg = () => {
                    const div = document.createElement('div');
                    div.className = "chat-msg transition-all duration-300";

                    if (m.type === 'guess' || m.is_system) {
                        if (m.type === 'guess') {
                            try {
                                sfx.play('success');
                                confetti({
                                    particleCount: 100,
                                    spread: 70,
                                    origin: { y: 0.6 },
                                    colors: ['#b9f6ca', '#ffeb3b', '#4fc3f7', '#ff80ab']
                                });
                            } catch (e) { }
                            div.className = "text-sm text-center font-black px-4 py-3 rounded-xl border-2 mx-2 my-4 shadow-[4px_4px_0px_#1e1e1e] bg-pop-green text-black border-black transform rotate-1 animate-bounce";
                            div.innerText = `🎉 ${username} guessed the word correctly!`;
                        } else {
                            // System
                            div.className = "text-xs text-center font-bold px-3 py-2 rounded-lg border-2 mx-2 my-2 shadow-[2px_2px_0px_#1e1e1e] bg-pop-yellow text-black border-black";
                            div.innerText = m.message;
                        }
                    } else {
                        try { sfx.play('pop'); } catch (e) { }
                        div.className = "text-sm mb-2 break-words p-2 rounded-lg mx-2 flex flex-col bg-white border-2 border-gray-100";
                        div.innerHTML = `<span class="font-bold text-black text-[10px] uppercase tracking-wide mb-0.5">${username}</span> <span class="text-gray-800">${m.message}</span>`;
                    }
                    return div;
                };

                // Add to standard chat boxes
                const chatBoxDesktop = document.getElementById('chat-box');
                const chatBoxMobile = document.getElementById('chat-box-mobile');
                [chatBoxDesktop, chatBoxMobile].forEach(box => {
                    if (box) box.prepend(createMsg());
                });

                // MOBILE TOASTS: Floating chat over canvas
                if (window.innerWidth < 1024) {
                    const toastContainer = document.getElementById('canvas-toasts');
                    if (toastContainer) {
                        const toast = document.createElement('div');

                        if (m.type === 'guess' || m.type === 'system') {
                            toast.className = "backdrop-blur border-2 border-ink px-4 py-2 rounded-xl shadow-[4px_4px_0px_rgba(0,0,0,1)] text-xs font-black transition-all transform translate-y-2 opacity-0";
                            if (m.type === 'guess') {
                                toast.classList.add('bg-pop-green', 'text-black');
                                toast.innerHTML = `🎉 ${username} guessed correctly!`;
                            } else {
                                toast.classList.add('bg-pop-yellow', 'text-black');
                                toast.innerHTML = `${username} ${m.message}`;
                            }
                        }
                        else {
                            toast.className = "bg-white/90 backdrop-blur border border-gray-200 px-3 py-1.5 rounded-lg shadow-sm text-xs flex gap-1 transition-all transform translate-y-2 opacity-0";
                            toast.innerHTML = `<span class="font-black text-ink uppercase tracking-wide">${username}:</span> <span class="text-gray-800">${m.message}</span>`;
                        }

                        toastContainer.appendChild(toast);

                        // Animate In
                        requestAnimationFrame(() => {
                            toast.classList.remove('translate-y-2', 'opacity-0');
                        });

                        // Remove after 4s
                        setTimeout(() => {
                            toast.classList.add('opacity-0', '-translate-x-4');
                            setTimeout(() => toast.remove(), 300);
                        }, 4000);

                        // Limit to 4 toasts
                        if (toastContainer.children.length > 4) {
                            toastContainer.removeChild(toastContainer.firstChild);
                        }
                    }
                }
            });
            updatePersist();
        }
    } catch (e) { }
}

async function sendChat(e) {
    if (e) e.preventDefault();

    // Restriction: Drawer cannot chat
    if (gameState.myTurn && gameState.status === 'drawing') {
        const input = document.getElementById('chat-input');
        // Visual feedback
        input.classList.add('ring-2', 'ring-red-500');
        setTimeout(() => input.classList.remove('ring-2', 'ring-red-500'), 500);
        return;
    }

    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';

    await submitChat(msg);
}

async function sendChatMobile(e) {
    if (e) e.preventDefault();
    if (gameState.myTurn && gameState.status === 'drawing') return;

    const input = document.getElementById('chat-input-mobile');
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';

    await submitChat(msg);
}

async function submitChat(msg) {
    await fetch('api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: player.token, action: 'send', message: msg })
    });
    syncChat();
}

function setColor(c) {
    try { sfx.play('pop'); } catch (e) { }
    gameState.color = c;

    // Update active state in UI
    document.querySelectorAll('.color-dot').forEach(btn => {
        btn.classList.remove('active');
        // Simple check if color matches (converted to hex or rgb?)
        // For simplicity we just assume click triggers this
    });
    // Visual feedback is handled by onclick logic mostly, but we can enhance later
}
function setSize(s) { gameState.size = s; }
function clearCanvasAction() {
    try { sfx.play('pop'); } catch (e) { }
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    strokeHistory = [];
    fetch('api/draw_sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: player.token, action: 'clear' })
    });
}

function undoAction() {
    try { sfx.play('pop'); } catch (e) { }
    // Local Optimistic Undo
    strokeHistory.pop();
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const tempHistory = [...strokeHistory];
    strokeHistory = [];
    tempHistory.forEach(oldS => {
        if (oldS.type === 'dot') drawDot(oldS.x, oldS.y, oldS.color, oldS.size);
        else drawLine(oldS.x1, oldS.y1, oldS.x2, oldS.y2, oldS.color, oldS.size);
    });

    fetch('api/draw_sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: player.token, action: 'undo' })
    });
}

async function leaveRoom() {
    if (!confirm('Are you sure you want to leave the game?')) return;

    try {
        await fetch('api/rooms.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ token: player.token, action: 'leave' })
        });
    } catch (e) { }

    localStorage.removeItem('dg_player');
    window.location.href = 'index.php';
}

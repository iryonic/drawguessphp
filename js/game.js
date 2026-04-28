// --- Layout Constants & Global State ---
const MOBILE_BREAKPOINT = 1024;
const DEFAULT_MOBILE_PANEL_H = '45vh';
window.currentTab = (window.innerWidth < MOBILE_BREAKPOINT) ? 'chat' : 'draw';
let currentTab = window.currentTab;

// Security Helper
function escapeHTML(str) {
    if (!str) return "";
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

const player = JSON.parse(localStorage.getItem('dg_player') || '{}');

// Recovery: Check URL for room code and compare with session
const urlParams = window.location.pathname.split('/');
const urlRoom = urlParams[urlParams.length - 1];

if (urlRoom && player.room_code !== urlRoom.toUpperCase()) {
    console.warn("Room Mismatch: URL says " + urlRoom + " but session is " + player.room_code);
    // If mismatch, try to reload but don't force redirect yet, syncState will handle 401
}

if (!player.token && !urlRoom) {
    window.location.href = APP_ROOT || './';
}

// Fail-safe for SFX engine (prevents crashes if sounds.js fails)
if (typeof sfx === 'undefined') {
    window.sfx = { 
        play: () => {}, playBGM: () => {}, stopBGM: () => {}, 
        getBGMStatus: () => false, toggleBGM: () => false 
    };
}

// Global scope initialization for HTML event handlers (CRITICAL TOP-LEVEL)
// (Functions will be mapped to window at the bottom of the file)

// UI Element Cache with Lazy Hooks
const getEl = (id) => document.getElementById(id);
const ui = {
    get roomCode() { return getEl('room-code-display'); },
    get players() { return getEl('player-list'); },
    get canvas() { return getEl('game-canvas'); },
    get overlay() { return getEl('overlay'); },
    get overlayTitle() { return getEl('overlay-title'); },
    get overlaySubtitle() { return getEl('overlay-subtitle'); },
    get wordSelect() { return getEl('word-selection'); },
    get startBtn() { return getEl('start-btn'); },
    get wordDisplay() { return getEl('word-display'); },
    get wordLen() { return getEl('word-len'); },
    get timer() { return getEl('timer'); },
    get timerProgress() { return getEl('timer-progress'); },
    get turnNotif() { return getEl('turn-notification'); },
    get floatingHud() { return getEl('floating-word-hud'); },
    get resultsScreen() { return getEl('results-screen'); },
    get podium() { return getEl('podium'); },
    get reactionBar() { return getEl('reaction-bar'); },
    get chatBox() { return getEl('chat-box') || getEl('chat-box-mobile'); },
    get chatInput() { return getEl('chat-input') || getEl('chat-input-mobile'); }
};

const canvas = ui.canvas;
const ctx = canvas ? canvas.getContext('2d') : null;

// Throttling Logic
let isTabActive = true;
document.addEventListener('visibilitychange', () => {
    isTabActive = !document.hidden;
});

function canPoll(type) {
    if (isTabActive) return true;
    if (type === 'state') return Math.random() < 0.2;
    return false;
}

// Mobile Tabs
const tabBtns = {
    draw: getEl('tab-draw'),
    chat: getEl('tab-chat'),
    rank: getEl('tab-rank')
};

// Game State Object
let processedMsgIds = new Set();
let gameState = {
    status: 'lobby',
    roundId: 0,
    lastStrokeId: 0,
    lastMsgId: 0,
    isDrawer: false,
    color: '#000000',
    size: (window.innerWidth < 1024) ? 3 : 5,
    myTurn: false,
    endTime: 0,
    totalTime: 60,
    tool: 'pen'   // 'pen' | 'fill'
};

// Game Persist Logic
function updatePersist() {
    sessionStorage.setItem('dg_lastStrokeId', gameState.lastStrokeId);
    sessionStorage.setItem('dg_lastMsgId', gameState.lastMsgId);
}

// Drawing State
let painting = false;
let pointsBuffer = [];
let lastPos = { x: 0, y: 0 };
let strokeHistory = [];
let lastSendTime = 0;
let timerInterval = null;
let isFirstBatchOfStroke = true;

// --- Initialization ---
if (ui.roomCode) ui.roomCode.innerText = player.room_code || '????';

function resizeCanvas() {
    if (!canvas) return;
    const container = getEl('canvas-container');
    if (!container) return;
    const wrapper = container.querySelector('.isolate') || container;
    
    const dpr = window.devicePixelRatio || 1;
    const w = wrapper.clientWidth;
    const h = wrapper.clientHeight;
    
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    
    if (ctx) {
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
    }

    gameState.lastStrokeId = 0;
    if (typeof syncDraw === 'function') syncDraw();
}

window.addEventListener('resize', resizeCanvas);
if (canvas) {
    setTimeout(resizeCanvas, 300);
    
    // Bind Drawing Events
    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('mouseleave', endDraw);

    canvas.addEventListener('touchstart', (e) => { 
        if (gameState.myTurn) e.preventDefault(); 
        const touch = e.touches[0];
        startDraw({ clientX: touch.clientX, clientY: touch.clientY }); 
    }, { passive: false });

    canvas.addEventListener('touchmove', (e) => { 
        if (gameState.myTurn) e.preventDefault(); 
        const touch = e.touches[0];
        draw({ clientX: touch.clientX, clientY: touch.clientY }); 
    }, { passive: false });

    canvas.addEventListener('touchend', endDraw);
}

// (Polling and engine start moved to bottom)

// --- Functions --- //
updateBrushPreview();

// --- Core Actions & UI Logic ---

// Initial Mobile View
if (window.innerWidth < MOBILE_BREAKPOINT) {
    setTimeout(() => { if (typeof switchTab === 'function') window.switchTab('chat'); }, 100);
}

function updateLocalTimer() {
    // If not in a state with a timer, clear timer visual
    const statesWithTimer = ['drawing', 'choosing', 'countdown', 'ended', 'game_over'];
    if (!statesWithTimer.includes(gameState.status)) {
        if (ui.timer) ui.timer.innerText = "--";
        if (ui.timerProgress) ui.timerProgress.style.strokeDashoffset = 0;
        return;
    }

    const now = Date.now() / 1000;
    let left = gameState.endTime > 0 ? Math.max(0, Math.ceil(gameState.endTime - now)) : 0;

    if (ui.timer) {
        ui.timer.innerText = left;
        if (left <= 10 && left > 0) {
            ui.timer.classList.add('text-red-600', 'animate-pulse');
            if (gameState.status === 'drawing') {
                try { sfx.play('tick'); } catch (e) { }
            }
        } else {
            ui.timer.classList.remove('text-red-600', 'animate-pulse');
        }
    }

    if (ui.timerProgress) {
        let total = gameState.totalTime || 60;
        if (gameState.status === 'choosing') total = 7;
        if (gameState.status === 'countdown') total = 3;
        if (gameState.status === 'ended') total = 10;
        if (gameState.status === 'game_over') total = 15;

        const pct = Math.min(1, Math.max(0, left / total));
        const offset = 100 - (pct * 100);
        ui.timerProgress.style.strokeDashoffset = offset;
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

    // Fill tool: run bucket fill on click, don't start a paint stroke
    if (gameState.tool === 'fill') {
        const pos = getNormalizedPos(e);
        fillAction(pos.x, pos.y);
        return;
    }

    painting = true;
    lastPos = getNormalizedPos(e);
    pointsBuffer = [lastPos];
    isFirstBatchOfStroke = true;
    drawDot(lastPos.x, lastPos.y, gameState.color, gameState.size);
}

function drawDot(nx, ny, color, size) {
    const x = nx * (canvas.width / (window.devicePixelRatio || 1));
    const y = ny * (canvas.height / (window.devicePixelRatio || 1));
    ctx.beginPath();
    ctx.arc(x, y, size / 2, 0, Math.PI * 2);
    ctx.fillStyle = color;
    ctx.fill();
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
}

function drawStrokeBatch(s) {
    if (!s || !s.points || s.points.length === 0) return;
    
    ctx.beginPath();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = s.color;
    ctx.fillStyle = s.color;
    
    const dpr = window.devicePixelRatio || 1;
    const w = canvas.width / dpr;
    const h = canvas.height / dpr;
    const points = s.points;

    if (points.length === 1) {
        ctx.arc(points[0].x * w, points[0].y * h, s.size / 2, 0, Math.PI * 2);
        ctx.fill();
    } else {
        ctx.lineWidth = s.size;
        if (points.length > 2) {
            ctx.moveTo(points[0].x * w, points[0].y * h);
            for (let i = 1; i < points.length - 2; i++) {
                const xc = (points[i].x + points[i + 1].x) / 2;
                const yc = (points[i].y + points[i + 1].y) / 2;
                ctx.quadraticCurveTo(points[i].x * w, points[i].y * h, xc * w, yc * h);
            }
            ctx.quadraticCurveTo(
                points[points.length - 2].x * w,
                points[points.length - 2].y * h,
                points[points.length - 1].x * w,
                points[points.length - 1].y * h
            );
        } else {
            ctx.moveTo(points[0].x * w, points[0].y * h);
            for (let i = 1; i < points.length; i++) {
                ctx.lineTo(points[i].x * w, points[i].y * h);
            }
        }
        ctx.stroke();
    }
}

function draw(e) {
    if (!painting || !gameState.myTurn) return;
    const pos = getNormalizedPos(e);

    // Advanced Local Smoothing (Live Interpolation)
    const factorW = canvas.width / (window.devicePixelRatio || 1);
    const factorH = canvas.height / (window.devicePixelRatio || 1);

    ctx.lineWidth = gameState.size;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = gameState.color;

    // Drawing locally for instant feedback
    ctx.beginPath();
    ctx.moveTo(lastPos.x * factorW, lastPos.y * factorH);
    ctx.lineTo(pos.x * factorW, pos.y * factorH);
    ctx.stroke();

    lastPos = pos;
    pointsBuffer.push(pos);

    // Throttle for server sync
    const now = Date.now();
    if (pointsBuffer.length > 15 || (now - lastSendTime > 200)) {
        sendStrokes();
        lastSendTime = now;
    }
}

function endDraw() {
    if (!painting) return;
    painting = false;
    sendStrokes();
}

async function sendStrokes() {
    if (pointsBuffer.length === 0) return;
    if (painting && pointsBuffer.length === 1) return;

    const pointsToSend = [...pointsBuffer]; // Copy points to prevent reference issues
    const data = {
        token: player.token,
        action: 'draw',
        color: gameState.color,
        size: gameState.size,
        points: JSON.stringify(pointsToSend),
        is_start: isFirstBatchOfStroke ? 'true' : 'false'
    };

    // Save to local history for Undo
    strokeHistory.push({ 
        color: gameState.color, 
        size: gameState.size, 
        points: pointsToSend, 
        is_start: isFirstBatchOfStroke 
    });
    
    isFirstBatchOfStroke = false;
    
    if (painting && pointsBuffer.length > 0) {
        const last = pointsBuffer[pointsBuffer.length - 1];
        pointsBuffer = [last];
    } else {
        pointsBuffer = [];
    }

    const response = await fetch(`${APP_ROOT}api/draw_sync.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data)
    });
    const res = await response.json();
    
    // Update lastStrokeId to prevent syncDraw from fetching our own stroke back
    if (res.success && res.data && res.data.id) {
        const sid = parseInt(res.data.id);
        if (sid > (gameState.lastStrokeId || 0)) {
            gameState.lastStrokeId = sid;
            updatePersist();
        }
    }
}


async function syncState() {
    try {
        const response = await fetch(`${APP_ROOT}api/game_state.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ token: player.token, action: 'sync' })
        });
        const res = await response.json();

        // Handle Session Error with Grace
        if (res.error === 'Invalid Session' || res.error === 'Room not found') {
            console.error("Session Integrity Check Failed:", res.error);
            window.sessionFailCount = (window.sessionFailCount || 0) + 1;
            if (window.sessionFailCount > 3) {
                localStorage.removeItem('dg_player'); 
                window.location.href = APP_ROOT;
            }
            return;
        }
        window.sessionFailCount = 0; 

        if (res.data && res.data.room) {
            if (ui.roomCode) ui.roomCode.textContent = res.data.room.room_code || '????';
        }

        if (!res.data) return;
        const data = res.data;

        // Round Change / Data Sync
        if (data.round.id != gameState.roundId) {
            if (data.round.id > 0 && data.round.id > gameState.roundId) {
                gameState.lastStrokeId = 0;
                gameState.lastMsgId = 0;
                processedMsgIds.clear();
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
            if (ui.wordSelect) ui.wordSelect.innerHTML = '';
        }

        if (oldStatus === 'drawing' && gameState.status === 'ended') {
            try { sfx.play('ding'); } catch (e) { }
        }

        if (data.room.current_round && ui.roundText) ui.roundText.textContent = data.room.current_round;
        if (data.room.max_rounds && ui.maxRounds) ui.maxRounds.textContent = data.room.max_rounds;

        // BGM Logic
        if (gameState.status === 'lobby' || gameState.status === 'ended' || gameState.status === 'game_over' || gameState.status === 'finished') {
            sfx.playBGM();
        } else {
            sfx.stopBGM();
        }

        const musicIcon = getEl('music-icon');
        if (musicIcon) musicIcon.innerText = sfx.getBGMStatus() ? '🔊' : '🔇';

        if (data.round.time_left !== undefined) {
            const now = Math.floor(Date.now() / 1000);
            gameState.endTime = now + data.round.time_left;
            if (data.room.round_duration) gameState.totalTime = parseInt(data.room.round_duration);
        }

        updatePlayers(data.players, data.round.drawer_id);

        const isMe = data.me == data.round.drawer_id;
        const wasMe = gameState.myTurn;
        gameState.myTurn = (gameState.status === 'drawing' && isMe);
        updateBrushPreview();

        if (gameState.myTurn && !wasMe && gameState.status === 'drawing') {
            showTurnNotification();
            try { sfx.play('start'); } catch (e) { }
        }

        const tools = getEl('drawing-tools');
        if (tools) {
            if (gameState.myTurn) tools.classList.remove('hidden');
            else tools.classList.add('hidden');
        }
        
        if (gameState.status === 'drawing' && ui.overlay && !ui.overlay.classList.contains('hidden')) {
            if (ui.floatingHud) ui.floatingHud.style.display = 'flex';
        } else if (gameState.status === 'drawing') {
            if (ui.floatingHud) ui.floatingHud.style.display = 'flex';
        } else {
            if (ui.floatingHud) ui.floatingHud.style.display = 'none';
        }

        // Overlays
        if (gameState.status === 'lobby') {
            if (ui.overlay) ui.overlay.classList.remove('hidden');
            if (ui.overlayTitle) ui.overlayTitle.textContent = "WAITING FOR PLAYERS";
            if (ui.overlaySubtitle) {
                ui.overlaySubtitle.textContent = "Share the room code: " + (data.room.room_code || '????');
                ui.overlaySubtitle.classList.remove('hidden');
            }
            if (ui.wordSelect) ui.wordSelect.classList.add('hidden');

            const mePlayer = data.players.find(p => p.is_me);
            const isHost = mePlayer ? mePlayer.is_host : false;
            if (ui.startBtn) {
                if (isHost) ui.startBtn.classList.remove('hidden');
                else ui.startBtn.classList.add('hidden');
            }
        } else if (gameState.status === 'choosing') {
            if (ui.overlay) ui.overlay.classList.remove('hidden');
            if (ui.startBtn) ui.startBtn.classList.add('hidden');
            if (isMe) {
                if (ui.overlayTitle) ui.overlayTitle.textContent = "IT'S YOUR TURN!";
                if (ui.overlaySubtitle) {
                    const left = Math.max(0, Math.ceil(gameState.endTime - Date.now() / 1000));
                    ui.overlaySubtitle.innerHTML = `Choose a Word <br> <span class="text-3xl font-black text-pop-pink">${left}s</span>`;
                    ui.overlaySubtitle.classList.remove('hidden');
                }
                if (ui.wordSelect) {
                    ui.wordSelect.classList.remove('hidden');
                    const options = data.round.options || data.words || [];
                    if (options.length > 0 && ui.wordSelect.children.length === 0) {
                        options.forEach(w => {
                            const btn = document.createElement('button');
                            btn.className = "w-full neo-btn py-2 px-4 bg-white hover:bg-pop-yellow flex justify-between items-center mb-1";
                            btn.innerHTML = `<span>${escapeHTML(w.word)}</span><span class="text-[8px] border-ink px-1 rounded-full bg-gray-100">${escapeHTML(w.difficulty)}</span>`;
                            btn.onclick = () => selectWord(w.id);
                            ui.wordSelect.appendChild(btn);
                        });
                    }
                }
            } else {
                const drawer = data.players.find(p => p.id == data.round.drawer_id);
                const drawerName = drawer ? drawer.username.toUpperCase() : "DRAWER";
                if (ui.overlayTitle) ui.overlayTitle.textContent = `${drawerName} IS CHOOSING`;
                if (ui.wordSelect) ui.wordSelect.classList.add('hidden');
            }
        } else if (gameState.status === 'countdown') {
            if (ui.overlay) ui.overlay.classList.remove('hidden');
            if (ui.overlayTitle) ui.overlayTitle.textContent = "GET READY!";
            if (ui.wordSelect) ui.wordSelect.classList.add('hidden');
            if (ui.startBtn) ui.startBtn.classList.add('hidden');
            if (ui.wordDisplay) ui.wordDisplay.textContent = data.round.word || ""; 
            if (ui.wordLen) {
                if (data.round.word_len > 0) {
                    ui.wordLen.textContent = data.round.word_len;
                    ui.wordLen.classList.remove('hidden');
                } else {
                    ui.wordLen.classList.add('hidden');
                }
            }
            ctx.clearRect(0, 0, canvas.width, canvas.height); 

        } else if (gameState.status === 'drawing') {
            if (ui.overlay) ui.overlay.classList.add('hidden');
            if (ui.wordDisplay) ui.wordDisplay.textContent = data.round.word || "";
            if (ui.wordLen) {
                if (data.round.word_len > 0) {
                    ui.wordLen.textContent = data.round.word_len;
                    ui.wordLen.classList.remove('hidden');
                } else {
                    ui.wordLen.classList.add('hidden');
                }
            }

            if (data.round.id != gameState.roundId) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                strokeHistory = [];
                gameState.roundId = data.round.id;
                gameState.lastStrokeId = 0;
            }
        } else if (gameState.status === 'ended') {
            if (ui.overlay) ui.overlay.classList.remove('hidden');
            if (ui.overlayTitle) ui.overlayTitle.textContent = `ROUND OVER!`;
            if (ui.wordSelect) ui.wordSelect.classList.add('hidden');
            if (ui.startBtn) ui.startBtn.classList.add('hidden');
            if (ui.wordLen) ui.wordLen.classList.add('hidden');

        } else if (gameState.status === 'finished' || gameState.status === 'game_over') {
            if (ui.wordLen) ui.wordLen.classList.add('hidden');
            if (ui.wordDisplay) ui.wordDisplay.textContent = "GAME OVER";
            showResults(data.players);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    } catch (e) {
        console.error("SyncState Error:", e);
    }
}

function showResults(players) {
    if (!ui.resultsScreen || !ui.resultsScreen.classList.contains('hidden')) return;

    // Sort players by score
    const sorted = [...players].sort((a, b) => b.score - a.score);
    
    // Update Podium
    for (let i = 1; i <= 3; i++) {
        const p = sorted[i - 1];
        const nameEl = document.getElementById(`winner-${i}-name`);
        const scoreEl = document.getElementById(`winner-${i}-score`);
        const avatarEl = document.getElementById(`winner-${i}-avatar`);

        if (p && nameEl && scoreEl) {
            nameEl.textContent = p.username;
            scoreEl.textContent = p.score;
            if (avatarEl && p.avatar) avatarEl.textContent = p.avatar;
        } else if (nameEl) {
            nameEl.textContent = "---";
            if (scoreEl) scoreEl.textContent = "0";
        }
    }

    ui.resultsScreen.classList.remove('hidden');
    
    // Trigger Confetti
    const duration = 5 * 1000;
    const animationEnd = Date.now() + duration;
    const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 1000 };

    function randomInRange(min, max) {
        return Math.random() * (max - min) + min;
    }

    const interval = setInterval(function() {
        const timeLeft = animationEnd - Date.now();

        if (timeLeft <= 0) {
            return clearInterval(interval);
        }

        const particleCount = 50 * (timeLeft / duration);
        confetti({ ...defaults, particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } });
        confetti({ ...defaults, particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } });
    }, 250);

    try { sfx.play('start'); } catch(e) {}
}

function toggleMusicUI() {
    const enabled = sfx.toggleBGM();
    const icon = document.getElementById('music-icon');
    if (icon) {
        icon.innerText = enabled ? '🔊' : '🔇';
    }
}

// Global scope (Moved to top for safety)

function showTurnNotification() {
    if (ui.turnNotif) {
        ui.turnNotif.classList.remove('opacity-0');
        setTimeout(() => {
            if (ui.turnNotif) ui.turnNotif.classList.add('opacity-0');
        }, 3000);
    }
}

function updatePlayers(players, drawerId) {
    const lists = [document.getElementById('player-list'), document.getElementById('player-list-mobile')];

    // Sort players by score descending
    players.sort((a, b) => b.score - a.score);

    lists.forEach(list => {
        if (!list) return;
        list.innerHTML = '';
        players.forEach((p, index) => {
            const isDrawer = p.id == drawerId;
            const div = document.createElement('div');
            const rankEmoji = index === 0 ? '👑' : (index === 1 ? '🥈' : (index === 2 ? '🥉' : ''));
            const isMe = p.is_me;
            const isHost = p.is_host;

            div.className = `group relative p-4 rounded-[1.25rem] md:rounded-2xl flex items-center gap-4 transition-all border-[3px] shadow-[4px_4px_0px_#1e1e1e22] hover:shadow-[6px_6px_0px_#000] 
                ${isDrawer ? 'bg-pop-yellow/10 border-pop-yellow' : 'bg-white border-ink/10 hover:border-ink'} 
                ${isMe ? 'ring-2 ring-pop-blue ring-offset-2' : ''}`;

            div.innerHTML = `
                ${rankEmoji ? `<div class="absolute -top-2 -left-2 text-xl drop-shadow-md z-10">${rankEmoji}</div>` : ''}
                <div class="text-4xl shrink-0 group-hover:scale-110 transition-transform duration-300 transform-gpu">${escapeHTML(p.avatar)}</div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-1">
                        <span class="font-black text-ink truncate text-sm md:text-base">${escapeHTML(p.username)}${isMe ? ' <span class="text-[10px] text-pop-blue">(YOU)</span>' : ''}</span>
                        ${isHost ? '<span class="text-[8px] font-black bg-ink text-white px-1.5 rounded-sm uppercase transform rotate-2">HOST</span>' : ''}
                    </div>
                    <div class="flex items-center gap-2">
                         <div class="h-2 flex-1 bg-gray-100 rounded-full overflow-hidden border border-ink/5">
                             <div class="h-full bg-pop-purple transition-all duration-1000" style="width: ${Math.min(100, (p.score / 1000) * 100)}%"></div>
                         </div>
                         <span class="text-[11px] font-black font-mono text-pop-purple shrink-0 bg-pop-purple/5 px-1.5 rounded-md">${p.score} <span class="text-[8px] opacity-60">PTS</span></span>
                    </div>
                </div>
                ${isDrawer ? '<div class="absolute -right-2 -top-2 rotate-6 bg-ink text-white text-[9px] font-black py-1 px-3 rounded-xl border-2 border-pop-yellow float-anim shadow-sm">DRAWING</div>' : ''}
            `;
            list.appendChild(div);
        });
    });
}

async function selectWord(wid) {
    try { sfx.play('pop'); } catch (e) { }
    await fetch(`${APP_ROOT}api/game_state.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: player.token, action: 'select_word', word_id: wid })
    });
}

async function startGame() {
    try { sfx.play('pop'); } catch (e) { }
    await fetch(`${APP_ROOT}api/game_state.php`, {
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
                if (strokeHistory.length > 0) {
                    let popped;
                    do {
                        popped = strokeHistory.pop();
                    } while (strokeHistory.length > 0 && popped && !popped.is_start);
                }
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                strokeHistory.forEach(batch => drawStrokeBatch(batch));
                continue;
            }

            if (typeof s.color === 'string' && s.color.startsWith('FILL:')) {
                const fillColor = s.color.slice(5);
                const pt = Array.isArray(s.points) && s.points[0] ? s.points[0] : null;
                if (pt) {
                    floodFillCanvas(pt.x, pt.y, fillColor);
                    // Store fill as a special history entry for undo to clear over
                    strokeHistory.push(s);
                }
                continue;
            }

            // Normal Stroke
            drawStrokeBatch(s);
            strokeHistory.push(s);
        }
    }
    requestAnimationFrame(renderLoop);
}

async function syncDraw() {
    // Initial fetch of history when first loading or round changes
    // Initial fetch of history when first loading or round changes
    // Only skip if we are the drawer AND we already have history (prevents duplication)
    // If we are the drawer but history is empty (e.g. refresh), we MUST fetch.
    if (gameState.myTurn && gameState.status === 'drawing' && strokeHistory.length > 0) return;

    if (gameState.status !== 'drawing' && gameState.status !== 'ended') return;

    try {
        const res = await (await fetch(`${APP_ROOT}api/draw_sync.php?token=${player.token}&action=fetch&last_id=${gameState.lastStrokeId}`)).json();
        if (res.data && res.data.strokes) {
            res.data.strokes.sort((a, b) => a.id - b.id);
            res.data.strokes.forEach(s => {
                if (s.id > gameState.lastStrokeId) gameState.lastStrokeId = s.id;
                strokeQueue.push(s);
            });
            updatePersist();
        }
    } catch (e) { }
}

function handleIncomingMessage(m) {
    if (processedMsgIds.has(m.id)) return;
    processedMsgIds.add(m.id);

    if (m.id > gameState.lastMsgId) gameState.lastMsgId = m.id;

    const username = m.username || 'System';

    if (m.type === 'reaction') {
        showReaction(m.message);
        return;
    }

    const createMsg = () => {
        const div = document.createElement('div');
        div.className = "chat-msg transition-all duration-300";

        if (m.type === 'guess' || m.is_system) {
            if (m.type === 'guess') {
                try {
                    sfx.play('achieve');
                    confetti({ particleCount: 60, spread: 50, origin: { y: 0.8 }, colors: ['#b9f6ca', '#facc15'] });
                } catch (e) { }
                div.className = "flex justify-center my-4 px-2 w-full";
                div.innerHTML = `
                    <div class="bg-pop-green border-2 border-ink px-4 py-2 rounded-xl shadow-[4px_4px_0px_#000] text-xs font-black uppercase text-center animate-bounce">
                        🎉 ${escapeHTML(username)} discovered the word!
                    </div>
                `;
            } else {
                div.className = "flex justify-center my-2 px-2 w-full";
                div.innerHTML = `
                    <div class="bg-pop-yellow border-2 border-ink px-4 py-1.5 rounded-full shadow-[2px_2px_0px_#000] text-[10px] font-black uppercase tracking-tight">
                        📢 ${escapeHTML(m.message)}
                    </div>
                `;
            }
        } else {
            const isMe = m.player_id == player.id;
            div.className = `flex flex-col mb-4 max-w-[85%] ${isMe ? 'ml-auto items-end' : 'mr-auto items-start'}`;
            div.innerHTML = `
                <div class="flex items-center gap-1.5 mb-1 px-1">
                    <span class="text-[10px] font-black text-ink/40 uppercase tracking-widest">${escapeHTML(username)}</span>
                </div>
                <div class="group relative px-4 py-2.5 rounded-2xl border-[2.5px] shadow-[3px_3px_0px_#1e1e1e11] font-bold text-sm leading-snug transition-all
                    ${isMe ? 'bg-pop-blue border-ink text-black rounded-tr-none hover:shadow-[4px_4px_0px_#000]' : 'bg-white border-ink text-ink rounded-tl-none hover:shadow-[4px_4px_0px_#000]'}"
                    style="transform: rotate(${isMe ? '1deg' : '-1deg'})">
                    ${escapeHTML(m.message)}
                </div>
            `;
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
    if (window.innerWidth < MOBILE_BREAKPOINT && currentTab !== 'chat') {
        showToast(m);
    }
}

async function syncChat() {
    try {
        const res = await (await fetch(`${APP_ROOT}api/chat.php?token=${player.token}&action=fetch&last_id=${gameState.lastMsgId}`)).json();
        if (res.data && res.data.messages) {
            res.data.messages.forEach(handleIncomingMessage);
            updatePersist();
        }
    } catch (e) { }
}

// Helper Functions
function showToast(m) {
    const toastContainer = document.getElementById('canvas-toasts');
    if (!toastContainer) return;

    const toast = document.createElement('div');
    const username = m.username || 'System';

    if (m.type === 'guess' || m.type === 'system') {
        toast.className = "backdrop-blur border-2 border-ink px-4 py-2 rounded-xl shadow-[4px_4px_0px_rgba(0,0,0,1)] text-xs font-black transition-all transform translate-y-2 opacity-0";
        if (m.type === 'guess') {
            toast.classList.add('bg-pop-green', 'text-black');
            toast.innerHTML = `🎉 ${escapeHTML(username)} guessed correctly!`;
        } else {
            toast.classList.add('bg-pop-yellow', 'text-black');
            toast.innerHTML = `${escapeHTML(username)} ${escapeHTML(m.message)}`;
        }
    }
    else {
        toast.className = "bg-white/95 backdrop-blur border-2 border-ink px-3 py-1.5 rounded-lg shadow-[2px_2px_0px_#000] text-xs flex gap-1 transition-all transform translate-y-2 opacity-0";
        toast.innerHTML = `<span class="font-black text-ink uppercase tracking-wide shrink-0">${escapeHTML(username)}:</span> <span class="text-gray-800 truncate">${escapeHTML(m.message)}</span>`;
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

    // Limit to 3 toasts on canvas
    while (toastContainer.children.length > 3) {
        toastContainer.removeChild(toastContainer.firstChild);
    }
}



function switchTab(tab) {
    if (window.innerWidth >= MOBILE_BREAKPOINT) return;
    currentTab = tab;

    const tabs = {
        rank: document.getElementById('tab-rank'),
        draw: document.getElementById('tab-draw'),
        chat: document.getElementById('tab-chat')
    };

    const views = {
        rank: document.getElementById('view-rank'),
        chat: document.getElementById('view-chat-mobile')
    };

    // 1. Update Tab Buttons
    Object.keys(tabs).forEach(k => {
        const t = tabs[k];
        if (!t) return;
        t.classList.toggle('active', k === tab);
    });

    // 2. Switching Logic for Split-Screen Panel
    // Note: Canvas is now perpetually visible in the upper half.
    // The lower panel toggles between Leaderboard and Chat.
    
    if (tab === 'draw') {
        // 'Focus' mode: We could minimize the panel, but for now we'll just keep it on 'Chat' or 'Rank'
        // or toggle a focused state. Let's keep it simple: focus just means the tabs reflect the state.
    } else {
        // Show active view in the lower panel
        Object.keys(views).forEach(k => {
            const v = views[k];
            if (!v) return;
            if (k === tab) {
                v.classList.remove('hidden');
                v.classList.add('flex');
            } else {
                v.classList.add('hidden');
                v.classList.remove('flex');
            }
        });

        if (tab === 'chat') {
            setTimeout(() => {
                const box = document.getElementById('chat-box-mobile');
                if (box) box.scrollTop = box.scrollHeight;
            }, 100);
        }
    }

    try { sfx.play('pop'); } catch (e) { }
    // Always trigger resize to be safe with layout shifts
    setTimeout(resizeCanvas, 150);
}

window.switchTab = switchTab;

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
    await fetch(`${APP_ROOT}api/chat.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: player.token, action: 'send', message: msg })
    });
    syncChat();
}

window.sendChat = sendChat;
window.sendChatMobile = sendChatMobile;

async function sendReaction(emoji) {
    try { sfx.play('pop'); } catch (e) { }
    showReaction(emoji);

    await fetch(`${APP_ROOT}api/chat.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: player.token, action: 'reaction', emoji: emoji })
    });
}

function showReaction(emoji) {
    const container = document.getElementById('canvas-container');
    if (!container) return;

    const el = document.createElement('div');
    el.className = "absolute pointer-events-none text-4xl z-40 transition-all duration-1000 ease-out";
    el.style.left = (20 + Math.random() * 60) + "%";
    el.style.bottom = "20%";
    el.innerText = emoji;

    container.appendChild(el);

    // Animate
    requestAnimationFrame(() => {
        el.style.transform = `translate(${(Math.random() - 0.5) * 100}px, -200px) scale(1.5)`;
        el.style.opacity = "0";
    });

    setTimeout(() => el.remove(), 1000);
}

function setColor(c) {
    try { sfx.play('pop'); } catch (e) { }
    gameState.color = c;

    // Update active state in UI
    document.querySelectorAll('.color-dot').forEach(btn => {
        btn.classList.remove('active', 'ring-4', 'ring-ink', 'scale-110', 'ring-2');
    });

    // The event target is the most reliable
    if (window.event && window.event.currentTarget) {
        const target = window.event.currentTarget;
        if (target.classList.contains('color-dot')) {
            target.classList.add('active');
        }
    }

    updateBrushPreview();
}

function setSize(s) {
    gameState.size = s;
    const slider = document.getElementById('brush-size');
    if (slider) slider.value = s;
    updateBrushPreview();
}

function setTool(tool) {
    gameState.tool = tool;
    updateBrushPreview(); // This will now handle the cursor update
    
    // Update active state on toolbar buttons
    document.querySelectorAll('.tool-btn').forEach(btn => {
        btn.classList.toggle('ring-2', btn.dataset.tool === tool);
        btn.classList.toggle('ring-ink', btn.dataset.tool === tool);
        btn.classList.toggle('bg-pop-yellow', btn.dataset.tool === tool);
    });
    try { sfx.play('pop'); } catch(e) {}
}

// ---------- Flood Fill (Iterative Scanline) ----------
function hexToRgba(hex) {
    const h = hex.replace('#', '');
    const bigint = parseInt(h.length === 3
        ? h.split('').map(c => c + c).join('')
        : h, 16);
    return [(bigint >> 16) & 255, (bigint >> 8) & 255, bigint & 255, 255];
}

function colorsMatch(data, idx, target, tolerance) {
    return Math.abs(data[idx]     - target[0]) <= tolerance &&
           Math.abs(data[idx + 1] - target[1]) <= tolerance &&
           Math.abs(data[idx + 2] - target[2]) <= tolerance &&
           Math.abs(data[idx + 3] - target[3]) <= tolerance;
}

function floodFillCanvas(startNX, startNY, fillColor) {
    const dpr = window.devicePixelRatio || 1;
    const w = Math.floor(canvas.width);
    const h = Math.floor(canvas.height);

    const sx = Math.round(startNX * (canvas.width / dpr) * dpr);
    const sy = Math.round(startNY * (canvas.height / dpr) * dpr);

    const imageData = ctx.getImageData(0, 0, w, h);
    const data = imageData.data;

    const targetColor = [
        data[(sy * w + sx) * 4],
        data[(sy * w + sx) * 4 + 1],
        data[(sy * w + sx) * 4 + 2],
        data[(sy * w + sx) * 4 + 3]
    ];
    const fill = hexToRgba(fillColor);

    // Don't fill if already the same color
    if (fill[0] === targetColor[0] && fill[1] === targetColor[1] &&
        fill[2] === targetColor[2] && fill[3] === targetColor[3]) return;

    const TOLERANCE = 20;
    const visited = new Uint8Array(w * h);
    const stack = [[sx, sy]];

    while (stack.length > 0) {
        const [cx, cy] = stack.pop();
        if (cx < 0 || cx >= w || cy < 0 || cy >= h) continue;
        const idx = cy * w + cx;
        if (visited[idx]) continue;
        visited[idx] = 1;

        if (!colorsMatch(data, idx * 4, targetColor, TOLERANCE)) continue;

        data[idx * 4]     = fill[0];
        data[idx * 4 + 1] = fill[1];
        data[idx * 4 + 2] = fill[2];
        data[idx * 4 + 3] = fill[3];

        stack.push([cx + 1, cy], [cx - 1, cy], [cx, cy + 1], [cx, cy - 1]);
    }

    ctx.putImageData(imageData, 0, 0);
}

function fillAction(nx, ny) {
    if (!gameState.myTurn) return;
    try { sfx.play('pop'); } catch(e) {}

    // Apply locally
    floodFillCanvas(nx, ny, gameState.color);

    // Sync to server
    fetch(`${APP_ROOT}api/draw_sync.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            token: player.token,
            action: 'fill',
            color: gameState.color,
            x: nx,
            y: ny
        })
    }).then(r => r.json()).then(res => {
        if (res.success && res.data && res.data.id) {
            gameState.lastStrokeId = parseInt(res.data.id);
            updatePersist();
        }
    });
}
// ---------- End Flood Fill ----------

function updateBrushPreview() {
    const preview = document.getElementById('brush-preview');
    if (preview) {
        preview.style.backgroundColor = gameState.color;
        preview.style.width = Math.max(8, gameState.size) + 'px';
        preview.style.height = Math.max(8, gameState.size) + 'px';
    }

    // Dynamic Canvas Cursor
    if (canvas) {
        if (!gameState.myTurn) {
            canvas.style.cursor = 'default';
        } else if (gameState.tool === 'fill') {
            canvas.style.cursor = 'cell';
        } else {
            // Create a colored dot cursor
            // We use a slightly larger cursor than the actual brush for visibility, 
            // but capped at a reasonable size (browsers have limits for custom cursors)
            const size = Math.min(32, Math.max(12, parseInt(gameState.size) + 4));
            const half = size / 2;
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}"><circle cx="${half}" cy="${half}" r="${half - 1.5}" fill="${gameState.color}" stroke="white" stroke-width="1.5"/><circle cx="${half}" cy="${half}" r="${half - 0.5}" fill="none" stroke="black" stroke-width="0.5" opacity="0.3"/></svg>`;
            const cursorUrl = 'data:image/svg+xml;base64,' + btoa(svg);
            canvas.style.cursor = `url(${cursorUrl}) ${half} ${half}, crosshair`;
        }
    }
}
function clearCanvasAction() {
    try { sfx.play('pop'); } catch (e) { }
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    strokeHistory = [];


    // Still notify PHP for DB persistence
    fetch(`${APP_ROOT}api/draw_sync.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: player.token, action: 'clear' })
    }).then(r => r.json()).then(res => {
        if (res.success && res.data && res.data.id) {
            gameState.lastStrokeId = parseInt(res.data.id);
            updatePersist();
        }
    });
}

function undoAction() {
    if (!gameState.myTurn) return;
    try { sfx.play('pop'); } catch (e) { }
    
    if (strokeHistory.length > 0) {
        let popped;
        do {
            popped = strokeHistory.pop();
        } while (strokeHistory.length > 0 && popped && !popped.is_start);
    }

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    strokeHistory.forEach(batch => drawStrokeBatch(batch));

    fetch(`${APP_ROOT}api/draw_sync.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ token: player.token, action: 'undo' })
    }).then(r => r.json()).then(res => {
        if (res.success && res.data && res.data.id) {
            gameState.lastStrokeId = parseInt(res.data.id);
            updatePersist();
        }
    });
}

async function leaveRoom() {
    if (!confirm('Are you sure you want to leave the game?')) return;

    try {
        await fetch(`${APP_ROOT}api/rooms.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ token: player.token, action: 'leave_room' })
        });
    } catch (e) { console.error("Leave error:", e); }

    localStorage.removeItem('dg_player');
    window.location.href = APP_ROOT;
}
window.selectWord = selectWord;
window.startGame = startGame;
window.syncState = syncState;
window.sendReaction = sendReaction;
window.setColor = setColor;
window.setSize = setSize;
window.setTool = setTool;
window.fillAction = fillAction;
window.undoAction = undoAction;
window.clearCanvasAction = clearCanvasAction;
window.toggleMusicUI = toggleMusicUI;
window.leaveRoom = leaveRoom;

// --- Engine Start ---
updateBrushPreview();
setTimeout(() => syncState(), 100);
setInterval(() => canPoll('state') && syncState(), 1000);
setInterval(() => canPoll('draw') && syncDraw(), 250); 
setInterval(() => canPoll('chat') && syncChat(), 1000);
setInterval(() => canPoll('strokes') && sendStrokes(), 1000);
if (timerInterval) clearInterval(timerInterval);
timerInterval = setInterval(() => updateLocalTimer(), 1000);

// End of Game Script

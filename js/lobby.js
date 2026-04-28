const avatarList = document.getElementById('avatar-list');
const avatarInput = document.getElementById('selected-avatar');
let avatars = [];

// Connectivity Tracking
let consecutiveFetchFailures = 0;
function handleFetchResult(success) {
    const banner = document.getElementById('conn-lost-banner');
    if (!banner) return;
    
    if (success) {
        consecutiveFetchFailures = 0;
        banner.classList.add('-translate-y-full');
    } else {
        consecutiveFetchFailures++;
        if (consecutiveFetchFailures >= 2) { 
            banner.classList.remove('-translate-y-full');
        }
    }
}

// Init Avatars
async function loadAvatars() {
    try {
        const res = await (await fetch(APP_ROOT + 'api/avatars.php')).json();
        handleFetchResult(true);
        console.log("Avatars Loaded:", res);

        if (res.success && res.data && Array.isArray(res.data) && res.data.length > 0) {
            avatars = res.data;
        } else {
            // Fallback
            avatars = ['🐱', '🐶', '🦁', '🦊', '🐸', '🐼', '🐨', '🐷'];
        }
        renderAvatars();
    } catch (e) {
        handleFetchResult(false);
        console.error("Failed to load avatars", e);
        avatars = ['🐱', '🐶', '🦁', '🦊', '🐸', '🐼', '🐨', '🐷'];
        renderAvatars();
    }
}

function renderAvatars() {
    avatarList.innerHTML = '';
    avatars.forEach((av, index) => {
        const div = document.createElement('div');
        const baseClass = "avatar-tile flex-shrink-0";

        div.className = baseClass;

        // Auto-select first if none selected or matches
        const currentVal = avatarInput.value;
        if ((index === 0 && (currentVal === '🐱' || currentVal === '')) || currentVal === av) {
            div.classList.add('selected');
            avatarInput.value = av;
        }

        div.innerText = av;

        div.onclick = () => {
            document.querySelectorAll('.avatar-tile').forEach(el => {
                el.classList.remove('selected');
            });
            div.classList.add('selected');
            avatarInput.value = av;
            try { sfx.play('pop'); } catch (e) { }
        };
        avatarList.appendChild(div);
    });
}

loadAvatars();

async function apiRequest(endpoint, data) {
    try {
        const res = await fetch(APP_ROOT + `api/${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data)
        });
        const json = await res.json();
        handleFetchResult(true);
        return json;
    } catch (e) {
        handleFetchResult(false);
        return { success: false, error: 'Network error or invalid JSON' };
    }
}

function showError(msg) {
    const el = document.getElementById('error-msg');
    el.innerText = msg;
    el.classList.remove('hidden');
}

async function confirmCreateRoom() {
    const username = document.getElementById('username').value.trim();
    if (!username) return showError("Please enter a username");

    const avatar = document.getElementById('selected-avatar').value;
    const maxRounds = document.getElementById('crs-rounds').value;
    const duration = document.getElementById('crs-time').value;
    const maxPlayers = document.getElementById('crs-players') ? document.getElementById('crs-players').value : 8;

    const res = await apiRequest('rooms.php', {
        action: 'create',
        username,
        avatar,
        max_rounds: maxRounds,
        round_duration: duration,
        max_players: maxPlayers
    });

    if (res.success) {
        sfx.play('start');
        localStorage.setItem('dg_player', JSON.stringify(res.data));
        window.location.href = APP_ROOT + 'room/' + res.data.room_code;
    } else {
        showError(res.error || 'Failed to create room');
    }
}

// Old function replaced by modal flow
// async function createRoom() { ... }

async function joinRoom() {
    sfx.play('pop');
    const username = document.getElementById('username').value.trim();
    const roomCode = document.getElementById('room-code-input').value.trim().toUpperCase();

    if (!username) return showError("Please enter a username");
    if (!roomCode) return showError("Please enter a room code");

    const avatar = document.getElementById('selected-avatar').value;

    const res = await apiRequest('rooms.php', {
        action: 'join',
        username,
        room_code: roomCode,
        avatar
    });

    if (res.success) {
        sfx.play('start');
        localStorage.setItem('dg_player', JSON.stringify(res.data));
        window.location.href = APP_ROOT + 'room/' + res.data.room_code;
    } else {
        showError(res.error || 'Failed to join room');
    }
}

// End of Lobby Script

// Handle auto-join from URL parameter
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const inviteCode = urlParams.get('join');
    if (inviteCode) {
        const roomInput = document.getElementById('room-code-input');
        if (roomInput) {
            roomInput.value = inviteCode.toUpperCase();
            // Highlight the field to show it's pre-filled
            roomInput.classList.add('bg-pop-blue/10');
            roomInput.classList.add('border-pop-blue');
        }
    }
})();

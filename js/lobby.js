const avatars = ['🐱', '🐶', '🦁', '🦊', '🐸', '🐼', '🐨', '🐷'];
const avatarList = document.getElementById('avatar-list');
const avatarInput = document.getElementById('selected-avatar');

// Init avatars
avatars.forEach(av => {
    const div = document.createElement('div');
    // Creative Fun Theme Classes
    const baseClass = "avatar-tile flex-shrink-0";
    div.className = `${baseClass} ${av === '🐱' ? 'selected' : ''}`;
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

async function apiRequest(endpoint, data) {
    try {
        const res = await fetch(`api/${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data)
        });
        return await res.json();
    } catch (e) {
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

    const res = await apiRequest('rooms.php', {
        action: 'create',
        username,
        avatar,
        max_rounds: maxRounds,
        round_duration: duration
    });

    if (res.success) {
        sfx.play('start');
        localStorage.setItem('dg_player', JSON.stringify(res.data));
        window.location.href = 'game.php';
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
        window.location.href = 'game.php';
    } else {
        showError(res.error || 'Failed to join room');
    }
}

<!-- Create Room Modal - Playful Yellow -->
<div id="create-modal" class="fixed inset-0 z-50 flex items-center justify-center modal-bg hidden opacity-0 transition-opacity duration-300 p-6 backdrop-blur-[4px] bg-black/30">
    <div class="fun-card max-w-sm w-full p-8 md:p-10 transform translate-y-4 scale-95 transition-all duration-300 bg-white" id="create-modal-content">
        
        <div class="flex justify-between items-center mb-8 border-b-4 border-ink pb-6">
            <h2 class="text-3xl font-black text-ink uppercase tracking-tight">Room <span class="marker-highlight">Setup</span></h2>
            <div class="text-2xl cursor-pointer hover:scale-125 hover:rotate-90 transition font-black p-2 bg-gray-100 rounded-xl" onclick="closeCreateModal()">✕</div>
        </div>

        <div class="space-y-8">
            <!-- Round Count -->
            <div class="space-y-4">
                 <label class="block text-xs font-black text-gray-400 uppercase tracking-[0.2em] px-1 text-center">Total Play Rounds</label>
                <div class="grid grid-cols-4 gap-3">
                    <button onclick="setRounds(2)" class="modal-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-sm" data-val="2">2</button>
                    <button onclick="setRounds(3)" class="modal-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-sm" data-val="3">3</button>
                    <button onclick="setRounds(5)" class="modal-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-sm" data-val="5">5</button>
                    <button onclick="setRounds(8)" class="modal-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-sm" data-val="8">8</button>
                </div>
                <input type="hidden" id="crs-rounds" value="3">
            </div>

            <!-- Timer -->
            <div class="space-y-4">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-[0.2em] px-1 text-center">Seconds per Turn</label>
                <div class="grid grid-cols-4 gap-3">
                    <button onclick="setTime(30)" class="modal-time-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-xs" data-val="30">30s</button>
                    <button onclick="setTime(60)" class="modal-time-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-xs" data-val="60">60s</button>
                    <button onclick="setTime(90)" class="modal-time-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-xs" data-val="90">90s</button>
                    <button onclick="setTime(120)" class="modal-time-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-xs" data-val="120">2m</button>
                </div>
                <input type="hidden" id="crs-time" value="60">
            </div>

            <!-- Max Players -->
            <div class="space-y-4">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-[0.2em] px-1 text-center">Max Players</label>
                <div class="grid grid-cols-4 gap-3">
                    <button onclick="setPlayers(4)"  class="modal-players-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-sm" data-val="4">4</button>
                    <button onclick="setPlayers(8)"  class="modal-players-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-sm" data-val="8">8</button>
                    <button onclick="setPlayers(16)" class="modal-players-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-sm" data-val="16">16</button>
                    <button onclick="setPlayers(32)" class="modal-players-opt btn-pop py-3 rounded-2xl bg-white text-ink font-black border-2 border-ink text-sm" data-val="32">32</button>
                </div>
                <!-- Custom Input -->
                <div class="flex items-center gap-3 mt-1">
                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Custom:</span>
                    <input type="number" id="crs-players-custom" min="2" max="50" placeholder="e.g. 20"
                        class="flex-1 border-2 border-ink rounded-xl px-3 py-2 font-black text-sm focus:outline-none focus:shadow-[3px_3px_0_#000] transition-all text-center"
                        oninput="setPlayersCustom(this.value)">
                    <span class="text-[10px] font-black text-gray-300 uppercase">max 50</span>
                </div>
                <input type="hidden" id="crs-players" value="8">
            </div>
        </div>

        <div class="mt-12">
            <button onclick="confirmCreateRoom()" 
                class="w-full btn-pop py-5 rounded-2xl bg-pop-green text-ink font-black text-xl shadow-[8px_8px_0px_#1e1e1e]">
                GO TO LOBBY 🚀
            </button>
        </div>
    </div>
</div>

<script>
    // --- Modal Logic ---
    const modal = document.getElementById('create-modal');
    const modalContent = document.getElementById('create-modal-content');
    const activeClassRounds  = "bg-pop-purple text-ink border-ink shadow-[4px_4px_0px_#1e1e1e] transform -translate-y-1";
    const activeClassTime    = "bg-pop-blue text-ink border-ink shadow-[4px_4px_0px_#1e1e1e] transform -translate-y-1";
    const activeClassPlayers = "bg-pop-pink text-ink border-ink shadow-[4px_4px_0px_#1e1e1e] transform -translate-y-1";
    const inactiveClass      = "bg-white text-gray-400 border-gray-200 shadow-none";

    function openCreateModal() {
        const username = document.getElementById('username').value.trim();
        if(!username) { 
            const err = document.getElementById('error-msg');
            document.getElementById('error-text').innerText = "Wait! Give us a nickname first.";
            err.classList.remove('hidden');
            setTimeout(() => err.classList.add('hidden'), 3500);
            return; 
        }
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modalContent.classList.remove('translate-y-4', 'scale-95');
            modalContent.classList.add('translate-y-0', 'scale-100');
        }, 10);
    }

    function closeCreateModal() {
        modal.classList.add('opacity-0');
        modalContent.classList.add('translate-y-4', 'scale-95');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    function setRounds(v) {
        document.getElementById('crs-rounds').value = v;
        updateOptionStyles();
        try { sfx.play('pop'); } catch(e){}
    }
    
    function setTime(v) {
        document.getElementById('crs-time').value = v;
        updateOptionStyles();
        try { sfx.play('pop'); } catch(e){}
    }

    function setPlayers(v) {
        document.getElementById('crs-players').value = v;
        document.getElementById('crs-players-custom').value = '';
        updateOptionStyles();
        try { sfx.play('pop'); } catch(e){}
    }

    function setPlayersCustom(v) {
        const val = Math.max(2, Math.min(50, parseInt(v) || 8));
        document.getElementById('crs-players').value = val;
        // Deselect preset buttons
        document.querySelectorAll('.modal-players-opt').forEach(btn => btn.className = `modal-players-opt btn-pop py-3 rounded-2xl font-black border-2 text-sm ${inactiveClass}`);
    }

    function updateOptionStyles() {
        const rounds   = document.getElementById('crs-rounds').value;
        const time     = document.getElementById('crs-time').value;
        const players  = document.getElementById('crs-players').value;
        document.querySelectorAll('.modal-opt').forEach(btn =>
            btn.className = `modal-opt btn-pop py-3 rounded-2xl font-black border-2 text-sm ${btn.dataset.val == rounds ? activeClassRounds : inactiveClass}`);
        document.querySelectorAll('.modal-time-opt').forEach(btn =>
            btn.className = `modal-time-opt btn-pop py-3 rounded-2xl font-black border-2 text-xs ${btn.dataset.val == time ? activeClassTime : inactiveClass}`);
        document.querySelectorAll('.modal-players-opt').forEach(btn =>
            btn.className = `modal-players-opt btn-pop py-3 rounded-2xl font-black border-2 text-sm ${btn.dataset.val == players ? activeClassPlayers : inactiveClass}`);
    }
    
    updateOptionStyles();
</script>

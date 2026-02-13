class SoundManager {
    constructor() {
        this.enabled = true;
        this.ctx = null;
        this.unlocked = false;
        this.bgm = null;
        this.bgmEnabled = false;
        this.config = null;

        const unlock = () => {
            this.init();
            if (this.ctx && this.ctx.state === 'running') {
                this.unlocked = true;
                this.loadConfig(); // Fetch music settings on first interaction
                document.removeEventListener('click', unlock);
                document.removeEventListener('touchstart', unlock);
                document.removeEventListener('keydown', unlock);
            }
        };

        document.addEventListener('click', unlock);
        document.addEventListener('touchstart', unlock);
        document.addEventListener('keydown', unlock);
    }

    async loadConfig() {
        try {
            const res = await (await fetch(APP_ROOT + 'api/settings.php')).json();
            if (res.success) {
                this.config = res.settings;
                this.bgmEnabled = this.config.lobby_music_enabled === '1';
            }
        } catch (e) { }
    }

    init() {
        if (this.unlocked) return; // Already unlocked, skip
        if (!this.ctx) {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                this.ctx = new AudioContext();
            } catch (e) { return; }
        }
        if (this.ctx.state === 'suspended') {
            // Only try to resume; don't force it or log errors if it fails due to lack of user gesture
            this.ctx.resume().catch(() => { });
        }
    }

    play(name) {
        if (!this.enabled) return;
        this.init();
        if (this.ctx && this.ctx.state === 'suspended') {
            this.ctx.resume().then(() => this.trigger(name));
        } else {
            this.trigger(name);
        }
    }

    trigger(name) {
        switch (name) {
            case 'pop': this.tone(800, 'sine', 0.1, 0.15); break;
            case 'ding':
                this.tone(1046, 'sine', 0.8, 0.2);
                this.tone(523, 'sine', 0.8, 0.2);
                break;
            case 'tick': this.tone(1200, 'triangle', 0.03, 0.05); break;
            case 'start': this.arpeggio([523, 659, 784, 1046], 0.08); break;
            case 'win': this.arpeggio([523, 659, 784, 1046, 1318, 1568, 2093], 0.1); break;
            case 'success': this.arpeggio([784, 1046, 1318], 0.08); break;
        }
    }

    playBGM() {
        if (!this.bgmEnabled || !this.config) return;
        if (this.bgm) return; // Already playing

        this.bgm = new Audio(this.config.lobby_music_url);
        this.bgm.loop = true;
        this.bgm.volume = parseFloat(this.config.music_volume || 0.3);
        this.bgm.play().catch(e => console.warn("BGM play blocked", e));
    }

    stopBGM() {
        if (this.bgm) {
            this.bgm.pause();
            this.bgm = null;
        }
    }

    tone(freq, type, duration, vol) {
        if (!this.ctx) return;
        try {
            const t = this.ctx.currentTime;
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();
            osc.type = type;
            osc.frequency.setValueAtTime(freq, t);
            gain.gain.setValueAtTime(0, t);
            gain.gain.linearRampToValueAtTime(vol, t + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.001, t + duration);
            osc.connect(gain);
            gain.connect(this.ctx.destination);
            osc.start(t);
            osc.stop(t + duration + 0.1);
        } catch (e) { }
    }

    arpeggio(notes, interval) {
        notes.forEach((n, i) => {
            setTimeout(() => this.tone(n, 'sine', 0.2, 0.08), i * 1000 * interval);
        });
    }
}

const sfx = new SoundManager();

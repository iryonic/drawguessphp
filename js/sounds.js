class SoundManager {
    constructor() {
        this.enabled = true;
        this.ctx = null;
        this.unlocked = false;

        // Try to unlock immediately on any interaction
        const unlock = () => {
            this.init();
            if (this.ctx && this.ctx.state === 'running') {
                this.unlocked = true;
                // Remove listeners once successful
                document.removeEventListener('click', unlock);
                document.removeEventListener('touchstart', unlock);
                document.removeEventListener('keydown', unlock);
            }
        };

        document.addEventListener('click', unlock);
        document.addEventListener('touchstart', unlock);
        document.addEventListener('keydown', unlock);
    }

    init() {
        if (!this.ctx) {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                this.ctx = new AudioContext();
            } catch (e) {
                console.error("Web Audio API not supported", e);
                return;
            }
        }
        if (this.ctx.state === 'suspended') {
            this.ctx.resume().catch(e => console.warn("Audio resume pending user gesture", e));
        }
    }

    play(name) {
        if (!this.enabled) return;
        this.init();

        // If still suspended, we can't hear anything, try to resume again
        if (this.ctx && this.ctx.state === 'suspended') {
            this.ctx.resume().then(() => this.trigger(name));
        } else {
            this.trigger(name);
        }
    }

    trigger(name) {
        switch (name) {
            case 'pop': // Sharp click
                this.tone(800, 'sine', 0.1, 0.15);
                break;
            case 'ding': // Bell
                this.tone(1046, 'sine', 0.8, 0.3); // C6
                this.tone(523, 'sine', 0.8, 0.3);  // C5
                break;
            case 'tick': // Wood blockish
                this.tone(1200, 'triangle', 0.03, 0.1);
                break;
            case 'start': // Ascending
                this.arpeggio([523, 659, 784, 1046], 0.08);
                break;
            case 'win': // Fanfare
                this.arpeggio([523, 659, 784, 1046, 1318, 1568, 2093], 0.1);
                break;
            case 'success': // Recognition/Guess Correct
                this.arpeggio([784, 1046, 1318], 0.08); // G5, C6, E6
                break;
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

            // Envelope to prevent clicking
            gain.gain.setValueAtTime(0, t);
            gain.gain.linearRampToValueAtTime(vol, t + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.001, t + duration);

            osc.connect(gain);
            gain.connect(this.ctx.destination);

            osc.start(t);
            osc.stop(t + duration + 0.1);
        } catch (e) {
            console.error("Audio Error:", e);
        }
    }

    arpeggio(notes, interval) {
        notes.forEach((n, i) => {
            setTimeout(() => this.tone(n, 'square', 0.2, 0.1), i * 1000 * interval);
        });
    }
}

const sfx = new SoundManager();

class SoundManager {
    constructor() {
        this.sounds = {
            pop: new Audio('https://assets.mixkit.co/active_storage/sfx/2571/2571-preview.mp3'),
            ding: new Audio('https://assets.mixkit.co/active_storage/sfx/1435/1435-preview.mp3'), // Correct guess
            tick: new Audio('https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3'), // Timer
            start: new Audio('https://assets.mixkit.co/active_storage/sfx/1435/1435-preview.mp3'), // Game start (placeholder)
            win: new Audio('https://assets.mixkit.co/active_storage/sfx/2019/2019-preview.mp3'),
            draw: new Audio('https://assets.mixkit.co/active_storage/sfx/2571/2571-preview.mp3')
        };
        // Preload
        Object.values(this.sounds).forEach(s => s.load());
        this.enabled = true;
    }

    play(name) {
        if (!this.enabled) return;
        if (this.sounds[name]) {
            this.sounds[name].currentTime = 0;
            this.sounds[name].play().catch(e => console.log('Audio Blocked', e));
        }
    }
}

const sfx = new SoundManager();

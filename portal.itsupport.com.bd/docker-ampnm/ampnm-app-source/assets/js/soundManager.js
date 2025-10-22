const SoundManager = {
    sounds: {},
    isMuted: false,

    init() {
        this.sounds = {
            online: new Audio('assets/sounds/online.mp3'),
            warning: new Audio('assets/sounds/warning.mp3'),
            critical: new Audio('assets/sounds/critical.mp3'),
            offline: new Audio('assets/sounds/offline.mp3')
        };

        Object.values(this.sounds).forEach(sound => {
            sound.load();
        });

        const unlockAudio = () => {
            if (document.body.dataset.audioUnlocked) return;
            
            const promises = Object.values(this.sounds).map(sound => {
                sound.volume = 0;
                return sound.play().catch(e => {});
            });

            Promise.all(promises).then(() => {
                Object.values(this.sounds).forEach(sound => {
                    sound.pause();
                    sound.currentTime = 0;
                    sound.volume = 1;
                });
                document.body.dataset.audioUnlocked = 'true';
                console.log("Audio context unlocked for notifications.");
            });
        };

        document.body.addEventListener('click', unlockAudio, { once: true });
        document.body.addEventListener('keydown', unlockAudio, { once: true });
    },

    play(soundName) {
        if (this.isMuted || !this.sounds[soundName]) {
            return;
        }

        const sound = this.sounds[soundName];
        sound.currentTime = 0;

        const playPromise = sound.play();

        if (playPromise !== undefined) {
            playPromise.catch(error => {
                console.warn(`Sound '${soundName}' could not be played automatically:`, error);
            });
        }
    }
};

SoundManager.init();
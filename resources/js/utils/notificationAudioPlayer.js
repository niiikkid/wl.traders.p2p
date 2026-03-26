let activeAudio = null;
let activeAudioSrc = null;

const resolveAudioSrc = (src) => {
    if (!src) {
        return null;
    }

    try {
        return new URL(src, window.location.origin).toString();
    } catch (error) {
        return src;
    }
};

const clearActiveAudio = (audio) => {
    if (activeAudio !== audio) {
        return;
    }

    activeAudio = null;
    activeAudioSrc = null;
};

export const playNotificationAudio = async (src, {interrupt = true} = {}) => {
    const nextAudioSrc = resolveAudioSrc(src);
    if (!nextAudioSrc) {
        return;
    }

    const isActiveAudioPlaying = activeAudio && !activeAudio.paused && !activeAudio.ended;
    if (isActiveAudioPlaying) {
        if (activeAudioSrc === nextAudioSrc) {
            // Не запускаем повторно один и тот же трек, пока он уже играет.
            return;
        }

        if (!interrupt) {
            // Не прерываем текущее воспроизведение, чтобы избежать наложения.
            return;
        }

        activeAudio.pause();
        activeAudio.currentTime = 0;
    }

    const audio = new Audio(src);
    activeAudio = audio;
    activeAudioSrc = nextAudioSrc;

    audio.addEventListener('ended', () => clearActiveAudio(audio), {once: true});
    audio.addEventListener('error', () => clearActiveAudio(audio), {once: true});

    try {
        await audio.play();
    } catch (error) {
        clearActiveAudio(audio);
    }
};

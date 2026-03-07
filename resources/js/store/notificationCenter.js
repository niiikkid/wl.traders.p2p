import {defineStore} from "pinia";

export const useNotificationCenterStore = defineStore("notificationCenter", {
    state: () => ({
        unreadCount: 0,
        latestNotificationId: null,
        soundEnabled: true,
        soundTrack: "radwimps.mp3",
    }),
    actions: {
        syncFromPageProps(pageProps = {}) {
            const menuUnreadCount = Number(pageProps?.menu?.notificationsUnreadCount ?? 0);
            this.unreadCount = Number.isNaN(menuUnreadCount) ? 0 : menuUnreadCount;

            const soundSettings = pageProps?.notificationsSound ?? {};
            this.soundEnabled = soundSettings.enabled ?? true;
            this.soundTrack = soundSettings.track ?? "radwimps.mp3";
        },
        setUnreadCount(value) {
            const parsedValue = Number(value ?? 0);
            this.unreadCount = Number.isNaN(parsedValue) ? 0 : parsedValue;
        },
        setLatestNotificationId(value) {
            if (value === null || value === undefined || value === "") {
                this.latestNotificationId = null;
                return;
            }

            const parsedValue = Number(value);
            this.latestNotificationId = Number.isNaN(parsedValue) ? null : parsedValue;
        },
        setSoundSettings(enabled, track) {
            this.soundEnabled = !!enabled;
            this.soundTrack = track ?? "radwimps.mp3";
        },
    },
});

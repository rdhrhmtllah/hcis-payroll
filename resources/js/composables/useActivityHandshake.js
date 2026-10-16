import { onMounted } from 'vue';
import axios from 'axios';
import FingerprintJS from '@fingerprintjs/fingerprintjs';

const HANDSHAKE_KEY = '_act_handshake_done';
const GPS_OPTIN_KEY = '_act_gps_optin';

async function getFingerprint() {
    try {
        const fp = await FingerprintJS.load();
        const result = await fp.get();
        return {
            fp: (result?.visitorId || '').slice(0, 64),
            visitorId: (result?.visitorId || '').slice(0, 64),
        };
    } catch {
        return { fp: null, visitorId: null };
    }
}

function getGps() {
    return new Promise((resolve) => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) {
            resolve(null);
            return;
        }
        const optin = localStorage.getItem(GPS_OPTIN_KEY) === '1';
        if (!optin) {
            resolve(null);
            return;
        }
        const timer = setTimeout(() => resolve(null), 4000);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                clearTimeout(timer);
                resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude });
            },
            () => {
                clearTimeout(timer);
                resolve(null);
            },
            { enableHighAccuracy: false, maximumAge: 60000, timeout: 3500 },
        );
    });
}

function buildPayload({ fp, visitorId }, gps) {
    const screen = typeof window !== 'undefined' && window.screen ? window.screen : { width: 0, height: 0 };
    return {
        fp,
        visitor_id: visitorId,
        screen_w: screen.width || null,
        screen_h: screen.height || null,
        timezone: Intl?.DateTimeFormat?.().resolvedOptions?.().timeZone || null,
        language: navigator?.language?.slice(0, 16) || null,
        platform: 'web',
        gps,
    };
}

export function useActivityHandshake({ alwaysRun = false } = {}) {
    onMounted(async () => {
        try {
            if (typeof window === 'undefined') return;
            if (!alwaysRun && sessionStorage.getItem(HANDSHAKE_KEY) === '1') return;

            const ident = await getFingerprint();
            const gps = await getGps();
            const payload = buildPayload(ident, gps);

            await axios.post('/api/v1/activity/handshake', payload, {
                headers: {
                    'X-Activity-Fp': ident.fp || '',
                    'X-Activity-Visitor-Id': ident.visitorId || '',
                    'X-Activity-Platform': 'web',
                },
            });

            sessionStorage.setItem(HANDSHAKE_KEY, '1');
        } catch {
            // silent — handshake bersifat best-effort
        }
    });
}

export function setActivityGpsOptIn(value) {
    try {
        if (value) localStorage.setItem(GPS_OPTIN_KEY, '1');
        else localStorage.removeItem(GPS_OPTIN_KEY);
    } catch {
        // ignore
    }
}

export function getActivityGpsOptIn() {
    try {
        return localStorage.getItem(GPS_OPTIN_KEY) === '1';
    } catch {
        return false;
    }
}

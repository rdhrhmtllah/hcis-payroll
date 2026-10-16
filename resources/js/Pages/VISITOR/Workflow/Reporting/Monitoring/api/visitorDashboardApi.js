import axios from 'axios';

const BASE = '/api/v1/visitor/dashboard';

function hasil(response) {
    return response?.data?.result ?? null;
}

/**
 * Analitik: cache 10 menit berkunci rentang + ranah.
 *
 * `domain` ikut ke server supaya blok milik ranah tetangga tidak dihitung sama
 * sekali — bukan sekadar tidak dirender. Ranahnya juga masuk kunci cache, kalau
 * tidak muatan tamu dan permit saling menimpa.
 */
export async function ambilAnalitik({ dari, sampai, segarkan = false, domain = null, tipe = null } = {}) {
    return hasil(
        await axios.get(`${BASE}/analitik`, {
            params: {
                dari,
                sampai,
                ...(segarkan ? { segarkan: 1 } : {}),
                ...(domain ? { domain } : {}),
                ...(tipe ? { tipe } : {}),
            },
        }),
    );
}

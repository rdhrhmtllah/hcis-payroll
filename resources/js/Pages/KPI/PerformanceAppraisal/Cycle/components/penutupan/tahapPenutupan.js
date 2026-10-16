/**
 * Tahap penutupan siklus (spec 2026-10-02). Urutan dan kodenya sama dengan
 * PaPenutupanSiklusService; dipakai stepper dan halaman Penutupan Siklus.
 */
export const TAHAP_PENUTUPAN = [
    { kode: 'pengisian', nama: 'Pengisian' },
    { kode: 'verifikasi', nama: 'Hitung & Verifikasi' },
    { kode: 'kualitatif', nama: 'Kesimpulan Kualitatif' },
    { kode: 'kurva', nama: 'Kurva Normal' },
    { kode: 'balancing', nama: 'Balancing' },
    { kode: 'terbit', nama: 'Rilis & Terbit' },
];

export const KODE_TAHAP = ['ikhtisar', ...TAHAP_PENUTUPAN.map((t) => t.kode)];

// Alamat lama `?tab=` tetap berlaku: tautan dari halaman lain dan markah orang tidak putus.
const TAB_LAMA = { RINGKASAN: 'ikhtisar', PENGISIAN: 'pengisian', HASIL: 'verifikasi', KERANJANG: 'verifikasi' };

/** @returns {{ tahap: string, sub: 'HASIL'|'KERANJANG' }} */
export function tahapDariUrl(search) {
    try {
        const q = new URLSearchParams(search || '');
        const tahap = String(q.get('tahap') || '').toLowerCase();
        if (KODE_TAHAP.includes(tahap)) return { tahap, sub: String(q.get('sub') || '').toUpperCase() === 'KERANJANG' ? 'KERANJANG' : 'HASIL' };
        const tab = String(q.get('tab') || '').toUpperCase();
        if (TAB_LAMA[tab]) return { tahap: TAB_LAMA[tab], sub: tab === 'KERANJANG' ? 'KERANJANG' : 'HASIL' };
    } catch {
        // alamat rusak → ikhtisar
    }
    return { tahap: 'ikhtisar', sub: 'HASIL' };
}

export const LABEL_KEADAAN = { SELESAI: 'Selesai', BERJALAN: 'Berjalan', MENUNGGU: 'Menunggu', DILEWATI: 'Dilewati' };

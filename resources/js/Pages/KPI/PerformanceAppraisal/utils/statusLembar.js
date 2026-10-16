/**
 * Label dan varian status lembar untuk penilai. Satu tempat, supaya daftar lembar dan
 * pratinjau Kalibrasi Penilai tidak menyebut keadaan yang sama dengan dua nama.
 *
 * `SUDAH_DIKERJAKAN` sama dengan `PaStatusPenugasan::SUDAH_DIKERJAKAN` di server — lembar
 * yang tautannya tidak perlu ditawarkan lagi.
 */
const PETA = {
    BELUM_MULAI: { label: 'Belum dimulai', variant: 'neutral' },
    BERJALAN: { label: 'Sedang diisi', variant: 'info' },
    MENUNGGU_VERIFIKASI: { label: 'Menunggu verifikasi', variant: 'warning' },
    TERKUMPUL: { label: 'Terisi, belum dikirim', variant: 'brand' },
    SELESAI: { label: 'Terkirim', variant: 'success' },
    DIBATALKAN: { label: 'Dibatalkan', variant: 'neutral' },
};

export const SUDAH_DIKERJAKAN = ['SELESAI', 'TERKUMPUL', 'MENUNGGU_VERIFIKASI'];

export function statusLembar(kode) {
    return PETA[kode] ?? { label: kode || '—', variant: 'neutral' };
}

/**
 * Skor untuk dibaca: pemisah desimal koma, paling banyak dua angka di belakangnya.
 * Kosong menjadi '—', BUKAN '0' seperti `formatAngka()`: di matriks kalibrasi, sel tanpa
 * skor dan skor nol adalah dua keadaan yang berbeda.
 */
export function angka(n) {
    if (n === null || n === undefined || n === '' || Number.isNaN(Number(n))) return '—';
    return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

export function tanggal(teks) {
    if (!teks) return '—';
    const d = new Date(String(teks).replace(' ', 'T'));
    return Number.isNaN(d.getTime())
        ? String(teks)
        : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

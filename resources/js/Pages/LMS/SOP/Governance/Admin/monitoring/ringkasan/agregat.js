import axios from 'axios';

/**
 * Penghubung bersama untuk seluruh panel agregat tab Ringkasan.
 *
 * Sengaja bukan mixin: tiap panel memegang keadaannya sendiri secara terang —
 * `memuat`, `galat`, dan datanya — sehingga membaca satu berkas panel cukup
 * untuk tahu apa yang terjadi di layarnya. Yang dibagikan hanya hal yang memang
 * harus sama di semua panel: alamat dasar, bentuk balasan, dan pemformatan.
 */
export const API = '/api/v1/sop/admin/monitoring';

/**
 * Ambil satu agregat.
 *
 * Melempar saat `success` false, BUKAN mengembalikan null diam-diam: panel harus
 * bisa membedakan "server bilang tidak ada data" dari "permintaannya gagal", dan
 * keduanya terlihat sama persis kalau kegagalan dipetakan jadi objek kosong.
 */
export async function ambil(jalur, params = {}) {
    const r = await axios.get(`${API}${jalur}`, { params });
    if (!r?.data?.success) {
        throw new Error(r?.data?.message || 'Gagal memuat data');
    }

    return r.data.result;
}

/** 1234 → "1.234" */
export function angka(n) {
    return new Intl.NumberFormat('id-ID').format(Number(n) || 0);
}

/** Detik → satuan yang terbaca sekilas. */
export function durasi(detik) {
    const d = Number(detik) || 0;
    if (d <= 0) return '—';
    if (d < 60) return `${d} dtk`;
    if (d < 3600) return `${Math.round(d / 60)} mnt`;

    return `${(d / 3600).toFixed(1)} jam`;
}

/** Jumlah hari → "3 bulan lagi" / "12 hari lagi", untuk proyeksi. */
export function lamaHari(hari) {
    const h = Number(hari) || 0;
    if (h <= 0) return '—';
    if (h < 45) return `${h} hari`;
    if (h < 365) return `${Math.round(h / 30)} bulan`;

    return `${(h / 365).toFixed(1)} tahun`;
}

/** ISO date → "28 Nov 2026". */
export function tanggal(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '—';

    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

/**
 * Warna peran untuk persentase penyelesaian.
 *
 * Ambangnya sama di seluruh panel supaya satu unit tak pernah tampil merah di
 * satu kartu dan kuning di kartu sebelahnya pada layar yang sama.
 */
export function sentimenPersen(persen) {
    const p = Number(persen) || 0;
    if (p >= 85) return 'success';
    if (p >= 60) return 'info';
    if (p >= 35) return 'warning';

    return 'danger';
}

/** Warna peran untuk delta: naik itu baik, turun itu buruk, nol itu netral. */
export function sentimenDelta(delta) {
    if (delta === null || delta === undefined) return 'neutral';
    if (delta > 0) return 'success';
    if (delta < 0) return 'danger';

    return 'neutral';
}

/**
 * Menghasilkan label rentang tanggal yang rapi (misal: "01 Jan 2026 – 31 Jan 2026") jika saringan tanggal aktif.
 * Mengembalikan null jika tidak ada saringan tanggal aktif.
 */
export function formatLabelRentang(saringan) {
    if (!saringan) return null;
    const start = saringan.date_start || saringan.dari;
    const end = saringan.date_end || saringan.sampai;
    if (!start || !end) return null;

    const d1 = new Date(start);
    const d2 = new Date(end);
    if (Number.isNaN(d1.getTime()) || Number.isNaN(d2.getTime())) return null;

    const opt = { day: '2-digit', month: 'short', year: 'numeric' };
    const tgl1 = d1.toLocaleDateString('id-ID', opt);
    const tgl2 = d2.toLocaleDateString('id-ID', opt);

    if (tgl1 === tgl2) return tgl1;
    return `${tgl1} – ${tgl2}`;
}

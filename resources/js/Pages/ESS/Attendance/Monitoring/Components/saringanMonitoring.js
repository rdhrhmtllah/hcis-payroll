// Saringan organisasi bersama kedua tab Monitoring Kehadiran. Nilai dicocokkan
// persis di server (SaringanMonitoring::ORGANISASI); `search` ikut dibagi.
export const ORGANISASI = [
    { key: 'divisi', label: 'Divisi', icon: 'diagram-3', placeholder: 'Semua divisi' },
    { key: 'departemen', label: 'Departemen', icon: 'diagram-2', placeholder: 'Semua departemen' },
    { key: 'jabatan', label: 'Jabatan', icon: 'person-badge', placeholder: 'Semua jabatan' },
    { key: 'lokasi', label: 'Lokasi kerja', icon: 'geo-alt', placeholder: 'Semua lokasi' },
    { key: 'shift', label: 'Shift', icon: 'clock', placeholder: 'Semua shift' },
];

export const organisasiKosong = () => ({
    search: '',
    ...Object.fromEntries(ORGANISASI.map((o) => [o.key, []])),
});

/** Departemen yang masuk akal untuk divisi terpilih (semua bila divisi kosong). */
export function departemenTersedia(opsi, divisiTerpilih) {
    const semua = opsi?.departemen || [];
    const cocok = divisiTerpilih?.length ? semua.filter((d) => divisiTerpilih.includes(d.divisi)) : semua;
    return [...new Set(cocok.map((d) => d.nama))];
}

/** Definisi field HcisFilterBar untuk saringan organisasi. */
export function fieldOrganisasi(opsi, organisasi) {
    const daftar = (v) => (v || []).map((x) => ({ value: x, label: x }));
    return ORGANISASI.map((o) => ({
        ...o,
        type: 'select',
        multiple: true,
        col: 3,
        options: daftar(o.key === 'departemen' ? departemenTersedia(opsi, organisasi.divisi) : opsi?.[o.key]),
    }));
}

/**
 * Terapkan satu perubahan saringan organisasi; mengembalikan objek BARU.
 * Departemen yang tak lagi di bawah divisi terpilih dibuang, karena akan
 * menyaring ke hasil kosong tanpa terlihat sebabnya.
 */
export function ubahOrganisasi(opsi, organisasi, key, value) {
    const hasil = { ...organisasi, [key]: Array.isArray(value) ? value : (value ? [value] : []) };
    if (key === 'divisi') {
        const boleh = departemenTersedia(opsi, hasil.divisi);
        hasil.departemen = hasil.departemen.filter((d) => boleh.includes(d));
    }
    return hasil;
}

export function parameterOrganisasi(organisasi) {
    return {
        search: organisasi.search || '',
        ...Object.fromEntries(ORGANISASI.map((o) => [o.key, organisasi[o.key] || []])),
    };
}

export function formatTanggal(v, opsi = { day: '2-digit', month: 'short', year: 'numeric' }) {
    if (!v) return '—';
    const d = new Date(`${String(v).slice(0, 10)}T00:00:00`);
    return Number.isNaN(d.getTime()) ? v : d.toLocaleDateString('id-ID', opsi);
}

export function formatMenit(menit) {
    const n = Number(menit) || 0;
    if (n < 60) return `${n} mnt`;
    const j = Math.floor(n / 60);
    const m = n % 60;
    return m ? `${j} j ${m} mnt` : `${j} jam`;
}

/** Tampilan per kategori kehadiran (kunci = KategoriKehadiran di server). */
export const KATEGORI = {
    belum_absen: { label: 'Belum absen', ikon: 'person-exclamation', sentiment: 'danger', variant: 'danger' },
    terlambat: { label: 'Hadir terlambat', ikon: 'alarm', sentiment: 'warning', variant: 'warning' },
    tepat_waktu: { label: 'Hadir tepat waktu', ikon: 'check2-circle', sentiment: 'success', variant: 'success' },
    belum_jam: { label: 'Belum jam masuk', ikon: 'hourglass-split', sentiment: 'neutral', variant: 'neutral' },
    sakit_izin: { label: 'Sakit / Izin', ikon: 'bandaid', sentiment: 'info', variant: 'info' },
    cuti: { label: 'Cuti', ikon: 'airplane', sentiment: 'brand', variant: 'brand' },
    libur: { label: 'Day off / Libur', ikon: 'moon-stars', sentiment: 'neutral', variant: 'neutral' },
    tanpa_jadwal: { label: 'Jadwal belum ditetapkan', ikon: 'question-circle', sentiment: 'warning', variant: 'warning' },
};

export const kategori = (k) => KATEGORI[k] || { label: k || '—', ikon: 'circle', sentiment: 'neutral', variant: 'neutral' };

export function nomorTransaksi(transaksi) {
    const LABEL = { cuti: 'Cuti', izin: 'Izin', sakit: 'Sakit', lembur: 'Lembur', terlambat: 'Izin terlambat', pulang_cepat: 'Izin pulang cepat' };
    return Object.entries(transaksi || {}).map(([k, no]) => ({ jenis: LABEL[k] || k, no }));
}

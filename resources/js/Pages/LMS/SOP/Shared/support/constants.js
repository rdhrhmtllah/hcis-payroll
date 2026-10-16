/**
 * Konstanta SOP (mirror backend app/Services/SOP/Support/*).
 * Dipakai untuk memetakan enum -> label + warna badge, biar konsisten.
 * Jangan hardcode string status di komponen — impor dari sini.
 */

// Status_Paket (Penjadwalan_Detail) -> label + varian StatusPill
export const STATUS_PAKET = {
    BELUM: { label: 'Belum', variant: 'idle' },
    PROGRESS: { label: 'Progress', variant: 'warn' },
    SELESAI: { label: 'Selesai', variant: 'ok' },
};

// Status item per karyawan (N_SOP_Progress.Status)
export const STATUS_ITEM = {
    BELUM: { label: 'Belum', variant: 'idle', icon: '○' },
    DIBUKA: { label: 'Dibuka', variant: 'warn', icon: '◐' },
    SELESAI: { label: 'Selesai', variant: 'ok', icon: '✓' },
    DIKONFIRMASI: { label: 'Dikonfirmasi', variant: 'ok', icon: '✓' },
    DIGANTIKAN: { label: 'Digantikan', variant: 'idle', icon: '↻' },
};

// Status lifecycle paket di Builder (N_SOP_Master_Paket.Status) -> label Indonesia.
// Catatan: beda dari STATUS_PAKET (status penjadwalan karyawan) di atas.
export const STATUS_PAKET_LABEL = {
    DRAFT: 'Draft',
    PUBLISHED: 'Terbit',
    ARCHIVED: 'Arsip',
    OBSOLETE: 'Usang',
};
export function statusPaketLabel(s) {
    return STATUS_PAKET_LABEL[s] || s || 'Draft';
}

// Prioritas paket
export const PRIORITAS = {
    KRITIS: { label: 'Sangat Penting', variant: 'bad' },
    TINGGI: { label: 'Tinggi', variant: 'warn' },
    NORMAL: { label: 'Normal', variant: 'idle' },
};

// Peristiwa ledger (Riwayat)
// Peristiwa pada riwayat kepatuhan.
// `kalimat` menjawab "ini apa" untuk pembaca yang bukan pembuat fiturnya;
// label sendirian ("Dicabut") tak pernah menyebut APA yang dicabut.
export const PERISTIWA = {
    SELESAI: {
        label: 'Penugasan selesai',
        variant: 'ok',
        kalimat: 'Seluruh item wajib pada paket ini sudah dibaca dan dikonfirmasi.',
    },
    DIBUKA_LAGI: {
        label: 'Dibuka kembali',
        variant: 'upd',
        kalimat: 'Paket yang tadinya selesai diwajibkan lagi karena isinya berubah.',
    },
    DICABUT: {
        label: 'Akses dicabut',
        variant: 'bad',
        kalimat:
            'Penugasan paket ini dikeluarkan dari perhitungan kepatuhan — bukan dianggap selesai, ' +
            'melainkan tidak lagi diwajibkan. Token kuisnya ikut dimatikan.',
    },
    KADALUARSA: {
        label: 'Kedaluwarsa',
        variant: 'warn',
        // Dipertahankan agar baris lama tetap terbaca. Masa berlaku SOP dicabut
        // 15 Agu 2026, jadi peristiwa ini TIDAK BISA lahir lagi.
        kalimat: 'Peristiwa lama dari masa ketika dokumen masih punya masa berlaku.',
    },
};

// Sebab pencabutan yang ditulis mesin — tak pernah ditawarkan di layar.
export const SEBAB_CABUT_SISTEM = {
    ATURAN_OTOMATIS: 'Dicabut mesin aturan penugasan',
};

/*
 * Cermin dari App\Services\LMS\SOP\Support\SopSebabCabut::MANUAL.
 *
 * Menambah sebab WAJIB di dua tempat. Kalau hanya di sini, pilihannya tampil di
 * layar lalu ditolak server; kalau hanya di PHP, sebabnya tak pernah bisa dipilih.
 */
export const SEBAB_CABUT = {
    SALAH_TARGET: 'Salah sasaran — tidak seharusnya ditugaskan',
    PINDAH_UNIT: 'Karyawan pindah unit atau jabatan',
    KELUAR: 'Karyawan keluar atau tidak lagi aktif',
    TIDAK_RELEVAN: 'Dokumen tidak relevan bagi karyawan ini',
    PAKET_DITARIK: 'Paket Dokumen ditarik atau digantikan',
    DUPLIKAT: 'Penugasan ganda',
    LAINNYA: 'Lainnya — dijelaskan di catatan',
};

/** Sebab yang tak menjelaskan apa pun tanpa catatan. */
export const SEBAB_WAJIB_CATATAN = ['LAINNYA'];

/** Label sebab apa pun, termasuk yang ditulis mesin dan yang tak dikenali. */
export function labelSebab(kode) {
    if (!kode) return null;

    return SEBAB_CABUT[kode] || SEBAB_CABUT_SISTEM[kode] || kode;
}

// Tipe item -> ikon Bootstrap Icons (bi) + label
export const TIPE_ITEM = {
    DOKUMEN: { label: 'Dokumen', icon: 'bi-file-earmark-text' },
    FORM: { label: 'Form', icon: 'bi-file-earmark-arrow-down' },
    VIDEO: { label: 'Video', icon: 'bi-play-btn' },
    KUIS: { label: 'Kuis', icon: 'bi-patch-question' },
    TAUTAN: { label: 'Tautan', icon: 'bi-link-45deg' },
    PARAGRAF: { label: 'Paragraf', icon: 'bi-text-paragraph' },
    ABSENSI: { label: 'Absensi', icon: 'bi-camera' },
};

// Tipe item berbasis berkas (mereferensi konten library + punya versi). Mirror SopTipeItem backend.
export const FILE_TIPE_ITEM = ['DOKUMEN', 'FORM', 'VIDEO'];
export function isFileTipe(t) {
    return FILE_TIPE_ITEM.includes(t);
}
// Petunjuk jenis file yang diharapkan per tipe (untuk validasi/hint UI).
// FORM tak pernah ditampilkan di penampil — berkasnya diunduh apa adanya,
// jadi jenisnya bebas (Excel/Word/CSV/PDF/Gambar).
export const TYPE_HINT = { DOKUMEN: 'PDF', VIDEO: 'video', FORM: 'Excel/Word/CSV/PDF/Gambar' };


// Warna varian StatusPill (kelas Bootstrap subtle)
export const PILL_VARIANT = {
    ok: 'bg-success-subtle text-success-emphasis border-success-subtle',
    warn: 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
    bad: 'bg-danger-subtle text-danger-emphasis border-danger-subtle',
    upd: 'bg-info-subtle text-info-emphasis border-info-subtle',
    idle: 'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle',
    acc: 'bg-primary-subtle text-primary-emphasis border-primary-subtle',
};

// Palet aksen kategori — memberi tiap kategori identitas warna+ikon yang
// konsisten dipakai lintas halaman (Kategori, Repository). Deterministik dari nama.
const PALET_AKSEN = [
    { color: '#6366f1', icon: 'bi-folder2' },
    { color: '#0ea5e9', icon: 'bi-journal-text' },
    { color: '#10b981', icon: 'bi-shield-check' },
    { color: '#f59e0b', icon: 'bi-briefcase' },
    { color: '#ec4899', icon: 'bi-people' },
    { color: '#8b5cf6', icon: 'bi-diagram-3' },
    { color: '#14b8a6', icon: 'bi-clipboard-check' },
    { color: '#f97316', icon: 'bi-megaphone' },
];

export function temaAksen(name) {
    const s = String(name || '');
    let h = 0;
    for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
    return PALET_AKSEN[h % PALET_AKSEN.length];
}

// URL seam ke halaman milik LEAD (dipakai Dev A untuk tautan tombol)
export const SOP_URL = {
    repository: () => `/sop/repository`,
    saya: () => `/sop/saya`,
    tim: () => `/sop/tim`,
    detail: (id) => `/sop/${id}`,
    reader: (id, itemId) => `/sop/${id}/baca/${itemId}`, // LEAD
    checkpoint: (id, itemId) => `/sop/${id}/kuis/${itemId}`, // LEAD
};

/**
 * Peran pada blok penanda tangan versi SOP.
 *
 * CERMIN dari App\Services\LMS\SOP\Support\SopPeranPengesah::SEMUA — server
 * menolak nilai di luar daftar ini, jadi keduanya harus diubah bersama.
 * Kalau layar menawarkan pilihan yang tak dikenal server, admin baru tahu
 * setelah menekan simpan.
 *
 * Urut sesuai lazimnya blok tanda tangan dibaca.
 */
export const PERAN_PENGESAH = ['Disiapkan oleh', 'Diperiksa oleh', 'Disetujui oleh', 'Disahkan oleh', 'Mengetahui'];

/*
 * Profil tipe — cermin app/Services/LMS/SOP/Support/SopTipeItem::PROFIL.
 *
 * Nama blok WAJIB identik dengan backend. Kalau keduanya menyimpang, layar dan
 * server tidak lagi sepakat soal field mana yang berlaku — dan itu persis
 * keadaan yang peta ini dibuat untuk mengakhiri: sebelum ada peta, layar
 * memutuskan sendiri, dan 47% versi FORM terlanjur mendapat nomor pengesahan
 * yang tak pernah dibutuhkan.
 *
 * Blok yang tidak terdaftar berarti TIDAK BERLAKU: tidak dirender, dan ditolak
 * server bila tetap dikirim terisi.
 */
export const BLOK = {
    NOMOR_RESMI: 'nomor_resmi',
    PENGESAHAN: 'pengesahan',
    BERKAS: 'berkas',
    UJIAN: 'ujian',
    /* Form kosong untuk diunduh karyawan; berkas utama FORM adalah contoh pengisiannya. */
    LAMPIRAN: 'lampiran',
};

export const PROFIL_TIPE = {
    DOKUMEN: [BLOK.NOMOR_RESMI, BLOK.PENGESAHAN, BLOK.BERKAS],
    FORM: [BLOK.PENGESAHAN, BLOK.BERKAS, BLOK.LAMPIRAN],
    VIDEO: [BLOK.BERKAS],
    KUIS: [BLOK.UJIAN],
};

export function blokBerlaku(tipe, blok) {
    return (PROFIL_TIPE[tipe] ?? []).includes(blok);
}

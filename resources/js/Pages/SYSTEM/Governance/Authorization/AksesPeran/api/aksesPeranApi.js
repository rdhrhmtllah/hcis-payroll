import axios from 'axios';

/** Endpoint layar Akses & Peran. Lingkup modul ditentukan server. */
const dasar = '/api/v1/akses-peran';

// Halaman per modul (/admin/{modul}/akses-peran): setiap permintaan membawa ?konteks=
// sehingga server mengunci data & perubahan ke modul itu.
let konteks = null;
export function aturKonteksModul(kode) {
    konteks = kode || null;
}
const opsi = (params = {}) => ({ params: konteks ? { ...params, konteks } : params });
const ambil = (url, params) => axios.get(`${dasar}${url}`, opsi(params)).then((r) => r.data?.result);
const kirim = (metode, url, isi) => axios[metode](`${dasar}${url}`, isi, opsi()).then((r) => r.data?.result);

export const aksesPeranApi = {
    ringkasan: () => ambil('/ringkasan'),
    peran: () => ambil('/peran'),
    detailPeran: (id) => ambil(`/peran/${id}`),
    /** Isi beberapa peran (halaman × aksi) — ringan, untuk wizard. */
    isiPeran: (ids) => ambil('/peran/isi', { ids }),
    /** Peta balik: halaman → peran yang membukanya, jumlah orang & akses langsung. */
    petaHalaman: (cari = '') => ambil('/halaman', { cari }),
    orangHalaman: (halaman) => ambil('/halaman/orang', { halaman }),
    cariPengguna: (cari) => ambil('/pengguna', { cari }),
    /** Anggota modul (peran/grant), dengan saringan cari/peran/tingkat dan halaman. */
    anggota: (saring = {}) => ambil('/pengguna', { anggota: 1, ...saring }),
    /** Pemilih karyawan bersaring; semua_id=1 → semua id hasil saringan (maks. 200). */
    pilihKaryawan: (saring = {}) => ambil('/pengguna/pilih', saring),
    opsiSaringKaryawan: () => ambil('/pengguna/opsi-saring'),
    detailPengguna: (id) => ambil(`/pengguna/${id}`),
    telusuri: (id, halaman) => ambil(`/pengguna/${id}/telusuri`, { halaman }),
    admin: () => ambil('/admin'),
    /** saring: { aksi, cari, dari, sampai, peran } */
    audit: (halaman = 1, saring = {}) => ambil('/audit', { halaman, ...saring }),
    // Fase 3a — Katalog (System Admin)
    katalog: () => ambil('/katalog'),
    ubahMode: (grup, mode, alasan) => kirim('put', `/katalog/${grup}/mode`, { mode, alasan }),
    selisih: (modul, halaman = 1) => ambil('/selisih', { modul, halaman }),
    bersihkanSelisih: (modul, alasan, sampai) => kirim('post', `/selisih/bersihkan`, { modul, alasan, sampai }),
    sinkron: () => kirim('post', `/sinkron`),
    // Master tingkat peran (DDL-2026-181) — System Admin
    tingkat: () => ambil('/tingkat'),
    ubahKemampuanTingkat: (kode, isi, alasan) => kirim('put', `/tingkat/${kode}`, { ...isi, alasan }),
    ubahTingkatPeran: (idRole, tingkat, alasan) => kirim('put', `/peran/${idRole}/tingkat`, { tingkat, alasan }),
    // Fase 5 — kesiapan pensiun jalur lama per modul (sinkron kering)
    kesiapan: () => ambil('/kesiapan'),
    // Fase 3b — tombol ubah
    tugaskan: (isi) => kirim('post', `/penugasan`, isi),
    /** Satu peran ke banyak karyawan → { berhasil: [...], gagal: [{ id_user, nama, alasan }] } */
    tugaskanMassal: (isi) => kirim('post', `/penugasan/massal`, isi),
    ubahBatas: (id, berlaku_sampai, alasan) => kirim('put', `/penugasan/${id}/batas`, { berlaku_sampai, alasan }),
    cabutPenugasan: (id, alasan) => kirim('post', `/penugasan/${id}/cabut`, { alasan }),
    beriGrant: (isi) => kirim('post', `/grant`, isi),
    cabutGrant: (isi) => kirim('post', `/grant/cabut`, isi),
    ubahHak: (idRole, hak, alasan, versi) => kirim('put', `/peran/${idRole}/hak`, { hak, alasan, versi }),
    pratinjau: (isi) => kirim('post', `/pratinjau`, isi),
    // Template cakupan bernama (DDL-2026-184) → { tersedia, bisa_buat, data }
    templateCakupan: () => ambil('/cakupan-template'),
    simpanTemplateCakupan: (isi, id = null) =>
        id ? kirim('put', `/cakupan-template/${id}`, isi) : kirim('post', '/cakupan-template', isi),
    nonaktifkanTemplateCakupan: (id, alasan) => kirim('post', `/cakupan-template/${id}/nonaktif`, { alasan }),
};

/** Pesan galat API yang bisa langsung ditampilkan (403 aturan delegasi, 422 validasi). */
export function pesanGalat(e, cadangan = 'Gagal menyimpan.') {
    const d = e?.response?.data;
    const pertama = d?.errors ? Object.values(d.errors)[0]?.[0] : null;
    return pertama || d?.message || cadangan;
}

/** Arti mode keputusan per modul (RBAC Fase 3a). */
export const infoMode = {
    LAMA: { label: 'Lama', varian: 'neutral', arti: 'Aturan lama yang memutuskan (kondisi sekarang).' },
    BAYANGAN: {
        label: 'Bayangan',
        varian: 'warning',
        arti: 'Aturan lama tetap memutuskan; aturan baru dihitung dan setiap beda dicatat.',
    },
    BARU: {
        label: 'Baru',
        varian: 'success',
        arti: 'Aturan baru yang memutuskan. Pastikan selisih sudah nol atau disetujui.',
    },
};

/** Label & warna tanggung jawab dan jenis role — satu tempat untuk semua tab. */
export const labelJenis = { ADMIN: 'Admin', AKSES: 'Akses', POOL_APPROVAL: 'Pool Approval' };
export const varianJenis = { ADMIN: 'brand', AKSES: 'neutral', POOL_APPROVAL: 'warning' };
export const labelTanggungJawab = { AKSES: 'Akses', APPROVAL: 'Approval', KEANGGOTAAN: 'Keanggotaan' };

export function teksCakupan(cakupan = []) {
    // Server mengirim label terbaca (nama divisi/karyawan/template); cadangan: kode mentah.
    return (
        cakupan.map((c) => c.label || (c.nilai ? `${c.jenis}: ${c.nilai}` : c.jenis)).join(' · ') || 'Semua karyawan'
    );
}

export function tanggal(nilai) {
    if (!nilai) return '—';
    const d = new Date(String(nilai).replace(' ', 'T'));
    return Number.isNaN(d.getTime())
        ? String(nilai)
        : d.toLocaleString('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          });
}

/** Status kesiapan pensiun jalur lama (RBAC Fase 5). */
export const infoKesiapan = {
    SIAP_BAYANGAN: { label: 'Siap Bayangan', varian: 'info' },
    PERLU_SINKRON: { label: 'Perlu sinkron', varian: 'warning' },
    TINJAU_SELISIH: { label: 'Tinjau selisih', varian: 'warning' },
    SIAP_BARU: { label: 'Siap Baru', varian: 'success' },
    BARU: { label: 'Sudah Baru', varian: 'brand' },
};

/** Tingkat peran (master N_HRIS_Tingkat_Peran): label & warna satu tempat. */
export const infoTingkat = {
    SYSTEM_ADMIN: { label: 'System Admin', varian: 'danger' },
    SUPER_ADMIN: { label: 'Super Admin', varian: 'warning' },
    ADMIN: { label: 'Admin', varian: 'brand' },
    PENGGUNA: { label: 'Pengguna', varian: 'neutral' },
};

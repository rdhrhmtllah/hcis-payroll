import axios from 'axios';

/**
 * Sumber isi palet perintah untuk halaman Monitoring SOP.
 *
 * Berbeda dari SOP Saya yang seluruh datanya sudah di klien, sumber "karyawan"
 * di sini memanggil API — karena daftar karyawan bisa ribuan dan tak mungkin
 * dikirim sekaligus. Itu sebabnya sumbernya ditandai `async: true` (cangkang
 * palet menampilkan status "Mencari…") dan halaman memasang `debounce`.
 */

const API = '/api/v1/sop/admin/monitoring';
const cocok = (q, ...teks) =>
    !q ||
    teks.some((t) =>
        String(t || '')
            .toLowerCase()
            .includes(q),
    );

const PERINTAH = [
    {
        id: 't:ringkasan',
        judul: 'Buka Ringkasan',
        sub: 'Yang perlu ditangani hari ini',
        icon: 'bi-speedometer2',
        aksi: { tab: 'ringkasan' },
    },
    {
        id: 't:karyawan',
        judul: 'Buka Karyawan',
        sub: 'Kepatuhan per orang',
        icon: 'bi-people-fill',
        aksi: { tab: 'karyawan' },
    },
    {
        id: 't:paket',
        judul: 'Buka Paket',
        sub: 'Tingkat penyelesaian per paket',
        icon: 'bi-collection-fill',
        aksi: { tab: 'paket' },
    },
    // Perintah "Buka Dokumen", "Buka Analitik", dan "Lihat dokumen kadaluarsa"
    // dicabut: tab Dokumen disembunyikan sementara dan Analitik sudah dilebur
    // ke Ringkasan. Menyisakannya berarti palet menawarkan jalan buntu.
    {
        id: 'f:terlambat',
        judul: 'Lihat yang terlambat',
        sub: 'Karyawan · saringan terlambat',
        icon: 'bi-exclamation-octagon-fill',
        tone: 'bad',
        aksi: { tab: 'karyawan', filters: { status: 'terlambat' } },
    },
];

const HALAMAN = [
    {
        id: 'n:penjadwalan',
        judul: 'Ke Penjadwalan Dokumen',
        sub: 'Distribusikan paket ke karyawan',
        icon: 'bi-calendar-plus',
        aksi: { url: '/sop/admin/penjadwalan' },
    },
    {
        id: 'n:builder',
        judul: 'Ke Document Builder',
        sub: 'Susun paket & item',
        icon: 'bi-tools',
        aksi: { url: '/sop/admin/builder' },
    },
];

/** @param {() => {paketOptions: Array}} ambil */
export function sumberMonitoring(ambil) {
    return [
        {
            jenis: 'karyawan',
            label: 'Karyawan',
            async: true,
            async cari(q) {
                if (q.length < 2) return [];
                try {
                    const r = await axios.get(`${API}/karyawan`, { params: { q, limit: 6, status: 'all' } });
                    if (!r.data.success) return [];

                    return (r.data.result || []).map((k) => ({
                        id: `k:${k.Kode_Karyawan}`,
                        judul: k.Nama || k.Kode_Karyawan,
                        sub: [k.Kode_Karyawan, k.Departemen, k.Jabatan].filter(Boolean).join(' · '),
                        tag: k.Terlambat > 0 ? `${k.Terlambat} terlambat` : null,
                        tone: k.Terlambat > 0 ? 'bad' : 'ok',
                        icon: 'bi-person-fill',
                        aksi: { url: `/sop/admin/monitoring/karyawan/${k.Token}` },
                    }));
                } catch (e) {
                    return [];
                }
            },
        },
        {
            jenis: 'paket',
            label: 'Paket',
            cari(q) {
                return ambil()
                    .paketOptions.filter((p) => cocok(q, p.Nama_Paket))
                    .slice(0, 5)
                    .map((p) => ({
                        id: `p:${p.Id_SOP_Paket}`,
                        judul: p.Nama_Paket,
                        sub: 'Paket Dokumen · tingkat penyelesaian',
                        icon: 'bi-journal-text',
                        aksi: { tab: 'paket', filters: { paket: p.Id_SOP_Paket } },
                    }));
            },
        },
        {
            jenis: 'perintah',
            label: 'Pindah & saring',
            cari(q) {
                return PERINTAH.filter((x) => cocok(q, x.judul, x.sub));
            },
        },
        {
            jenis: 'halaman',
            label: 'Halaman lain',
            cari(q) {
                return HALAMAN.filter((x) => cocok(q, x.judul, x.sub));
            },
        },
    ];
}

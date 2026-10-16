/**
 * Sumber isi palet perintah untuk halaman SOP Saya.
 *
 * Dipisah dari komponen halaman supaya SopSaya.vue tetap soal tampilan, dan
 * supaya bentuk "sumber" terlihat jelas sebagai kontrak dengan CommandPalette:
 *   { jenis, label, async?, cari(q) -> item[] | Promise<item[]> }
 *
 * Semua sumber di sini SINKRON: tugasSaya() mengirim seluruh tugas karyawan
 * sekaligus, jadi penyaringan terjadi di memori dan hasilnya muncul pada
 * ketikan yang sama — tanpa request, tanpa debounce.
 */

const cocok = (q, ...teks) =>
    !q ||
    teks.some((t) =>
        String(t || '')
            .toLowerCase()
            .includes(q),
    );

/** @param {() => {items:Array, grup:Array, counts:Object, isLeader:boolean}} ambil */
export function sumberSopSaya(ambil) {
    return [
        {
            jenis: 'tugas',
            label: 'Tugas Anda',
            cari(q) {
                const { items } = ambil();

                return items
                    .filter((k) => cocok(q, k.Nama_Paket, k.Kategori))
                    .slice(0, 6)
                    .map((k) => ({
                        id: `s:${k.Id_SOP_Paket}`,
                        judul: k.Nama_Paket,
                        sub: [k.Kategori || 'Umum', k.Total_Item ? `${k.Item_Selesai}/${k.Total_Item} item` : null]
                            .filter(Boolean)
                            .join(' · '),
                        tag: k.Is_Terlambat ? 'Terlambat' : k.Persen >= 100 ? 'Tuntas' : null,
                        tone: k.Is_Terlambat ? 'bad' : k.Persen >= 100 ? 'ok' : '',
                        icon: 'bi-journal-text',
                        aksi: { url: `/sop/${k.Id_SOP_Paket}` },
                    }));
            },
        },
        {
            jenis: 'kategori',
            label: 'Kategori',
            cari(q) {
                const { grup } = ambil();

                return grup
                    .filter((g) => cocok(q, g.nama))
                    .slice(0, 5)
                    .map((g) => ({
                        id: `k:${g.nama}`,
                        judul: g.nama,
                        sub: `${g.total} dokumen · ${g.persen}% tuntas`,
                        tag: g.terlambat ? `${g.terlambat} terlambat` : null,
                        tone: g.terlambat ? 'bad' : g.persen >= 100 ? 'ok' : '',
                        icon: 'bi-folder-fill',
                        aksi: { kategori: g.nama },
                    }));
            },
        },
        {
            jenis: 'saring',
            label: 'Saring',
            cari(q) {
                const { counts } = ambil();
                const opsi = [
                    { k: 'terlambat', label: 'yang terlambat', icon: 'bi-exclamation-octagon-fill', tone: 'bad' },
                    { k: 'perlu_dibaca', label: 'yang perlu dibaca', icon: 'bi-journal-bookmark', tone: '' },
                    { k: 'diperbarui', label: 'yang diperbarui', icon: 'bi-arrow-repeat', tone: '' },
                    { k: 'selesai', label: 'yang sudah selesai', icon: 'bi-check2-circle', tone: 'ok' },
                ];

                return (
                    opsi
                        // Saringan yang hasilnya pasti kosong tak ditawarkan — palet yang
                        // menjanjikan sesuatu lalu memberi layar kosong lebih buruk
                        // daripada palet yang lebih pendek.
                        .filter((x) => (counts[x.k] || 0) > 0)
                        .map((x) => ({
                            id: `f:${x.k}`,
                            judul: `Saring ${x.label}`,
                            sub: `${counts[x.k]} dokumen`,
                            icon: x.icon,
                            tone: x.tone,
                            aksi: { status: x.k },
                        }))
                        .concat([
                            {
                                id: 'f:reset',
                                judul: 'Tampilkan semua tugas',
                                sub: 'Hapus saringan status',
                                icon: 'bi-grid-1x2-fill',
                                aksi: { status: '' },
                            },
                        ])
                        .filter((x) => cocok(q, x.judul, x.sub))
                );
            },
        },
        {
            jenis: 'halaman',
            label: 'Halaman lain',
            cari(q) {
                const { isLeader } = ambil();
                const daftar = [
                    {
                        id: 'n:repo',
                        judul: 'Buka Public Document',
                        sub: 'Rujukan publik, tertata per folder',
                        icon: 'bi-journal-bookmark-fill',
                        aksi: { url: '/sop/repository' },
                    },
                ];
                if (isLeader) {
                    daftar.push({
                        id: 'n:tim',
                        judul: 'Buka Kepatuhan Tim',
                        sub: 'Ringkasan anggota tim Anda',
                        icon: 'bi-people-fill',
                        aksi: { url: '/sop/tim' },
                    });
                }
                daftar.push({
                    id: 'a:kartu',
                    judul: 'Unduh Kartu Kepatuhan',
                    sub: 'Diproses di panel Aktivitas',
                    icon: 'bi-download',
                    aksi: { kartu: true },
                });

                return daftar.filter((x) => cocok(q, x.judul, x.sub));
            },
        },
    ];
}

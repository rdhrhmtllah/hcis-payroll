/**
 * Sumbu pengelompokan & saringan untuk halaman SOP Saya.
 *
 * Modul: LMS · Submodul: SOP · Fitur: Employee/Saya · Lapisan: Support (frontend).
 *
 * KENAPA BERDIRI SENDIRI, BUKAN COMPUTED DI HALAMAN. Aturan di sini punya satu
 * sifat yang membuatnya rawan: sebagian sumbu bernilai JAMAK, sehingga satu
 * tugas boleh muncul di lebih dari satu kelompok. Begitu itu terjadi, jumlah
 * per kelompok tidak lagi menjumlah ke jumlah tugas — dan angka yang tak
 * menjumlah, bila tak dijelaskan, dibaca sebagai angka yang salah.
 *
 * Aturan seperti itu harus bisa diuji langsung, tanpa merender halaman
 * seribu baris beserta seluruh permintaan HTTP-nya.
 *
 * ASAL MULA NILAINYA. Kepemilikan (departemen), klasifikasi (jenis dokumen),
 * dan proses (APQC) melekat pada DOKUMEN, sedangkan satu baris di SOP Saya
 * adalah PAKET. Server mengirimkannya sebagai DAFTAR pada tiap kartu —
 * `Departemen[]`, `Jenis_Dokumen[]`, `Apqc[]` — justru karena satu paket boleh
 * memuat dokumen milik beberapa departemen sekaligus.
 */

/** Nama kelompok untuk tugas yang tak punya nilai pada sumbu tertentu. */
export const TANPA = {
    departemen: 'Tanpa Pemilik',
    jenis: 'Tanpa Jenis',
    apqc: 'Tanpa Proses',
    prioritas: 'Tanpa Prioritas',
};

/**
 * SELURUH sumbu yang dikenal, termasuk yang tidak muncul di rail.
 *
 * Dipisahkan dari `SUMBU_RAIL` di bawah karena keduanya menjawab pertanyaan
 * yang berbeda: yang ini "sumbu apa yang artinya dipahami modul", yang itu
 * "sumbu apa yang layak jadi pengelompokan di layar". APQC berguna sebagai
 * saringan tetapi buruk sebagai rail — satu paket bisa menyentuh banyak proses
 * sekaligus, dan railnya jadi lebih panjang daripada daftar tugasnya.
 *
 * `jamak` menentukan cara berhitung, bukan sekadar keterangan: sumbu jamak
 * menempatkan satu tugas di beberapa kelompok, dan kepala rail wajib
 * menyebutkan selisih antara jumlah tugas dan jumlah penempatan.
 */
export const AKSIS = {
    departemen: {
        key: 'departemen',
        label: 'Departemen',
        ikon: 'diagram-3-fill',
        jamak: true,
        tanpa: TANPA.departemen,
        ambil: (k) => k.Departemen || [],
    },
    jenis: {
        key: 'jenis',
        label: 'Jenis Dokumen',
        ikon: 'journals',
        jamak: true,
        tanpa: TANPA.jenis,
        ambil: (k) => k.Jenis_Dokumen || [],
    },
    apqc: {
        key: 'apqc',
        label: 'Proses APQC',
        ikon: 'diagram-2',
        jamak: true,
        tanpa: TANPA.apqc,
        ambil: (k) => k.Apqc || [],
    },
    prioritas: {
        key: 'prioritas',
        label: 'Prioritas',
        ikon: 'flag-fill',
        jamak: false,
        tanpa: TANPA.prioritas,
        ambil: (k) => (k.Prioritas ? [{ id: k.Prioritas, nama: k.Prioritas }] : []),
    },
};

/** Sumbu yang ditawarkan sebagai pengelompokan rail, berurut tampil. */
export const SUMBU = [AKSIS.departemen, AKSIS.jenis, AKSIS.prioritas];

/**
 * Sumbu untuk PUSTAKA (Public Document).
 *
 * Berbeda dari SUMBU milik Functional Document pada dua titik, dan keduanya
 * disengaja: PRIORITAS dibuang karena ia sifat penugasan, bukan sifat dokumen;
 * APQC ditambahkan karena di rak publik yang dicari orang adalah proses
 * bisnisnya — dan pada tampilan per-dokumen satu baris menyentuh tepat satu
 * proses, sehingga railnya tidak lagi lebih panjang daripada daftarnya.
 */
export const SUMBU_PUSTAKA = [AKSIS.departemen, AKSIS.jenis, AKSIS.apqc];

/**
 * MELEMPAR pada kunci tak dikenal, tidak jatuh ke sumbu pertama.
 *
 * Bentuk lama mengembalikan sumbu pertama sebagai cadangan, dan itu jebakan: salah
 * ketik pada satu kunci membuat modul ini diam-diam mengelompokkan hal yang
 * sama sekali berbeda dari yang diminta, dengan hasil yang tampak masuk akal.
 * Kegagalan yang berisik jauh lebih murah daripada kelompok yang salah.
 */
export function sumbuDari(key) {
    const s = AKSIS[key];
    if (!s) throw new Error(`Sumbu tugas tidak dikenal: ${key}`);

    return s;
}

/**
 * Nilai sumbu sebuah tugas, SELALU minimal satu.
 *
 * Daftar kosong dipulangkan sebagai satu entri ber-id null, bukan sebagai
 * daftar kosong. Ini bukan kerapian: tugas yang tak punya nilai pada sumbu
 * aktif akan LENYAP dari seluruh rail bila dibiarkan kosong — hilang tanpa
 * pesan, dan justru tugas itulah yang paling perlu dilihat (paket yang seluruh
 * isinya kuis dan tautan, atau dokumen yang belum bertuan).
 */
export function nilaiSumbu(item, key) {
    const s = sumbuDari(key);
    const nilai = (s.ambil(item) || []).filter((v) => v && (v.id !== undefined || v.nama));

    if (!nilai.length) return [{ id: null, nama: s.tanpa, kosong: true }];

    return nilai.map((v) => ({
        id: v.id ?? null,
        nama: v.nama || s.tanpa,
        kosong: v.id === null || !v.nama,
        level: v.level ?? null,
        kode: v.kode ?? null,
    }));
}

/**
 * Rakit kelompok rail dari daftar tugas yang sudah tersaring.
 *
 * Mengembalikan `tugas` dan `penempatan` sekaligus, dan keduanya WAJIB dipakai
 * layar. Pada sumbu jamak keduanya berbeda, dan menampilkan salah satunya saja
 * membuat pembaca menyimpulkan yang lain. `tugas` menghitung kartu unik;
 * `penempatan` menghitung berapa kali kartu muncul di seluruh kelompok.
 *
 * @param {Array} items tugas yang sudah lolos saringan
 * @param {string} key kunci sumbu
 * @param {(nama:string)=>{color:string,icon:string}} tema pemberi warna & ikon
 */
export function bangunGrup(items, key, tema) {
    const s = sumbuDari(key);
    const peta = new Map();
    let penempatan = 0;

    (items || []).forEach((k) => {
        nilaiSumbu(k, key).forEach((n) => {
            const nama = n.nama;
            if (!peta.has(nama)) {
                const t = tema ? tema(nama) : { color: '#64748b', icon: 'folder2-open' };
                peta.set(nama, {
                    nama,
                    warna: t.color,
                    ikon: t.icon,
                    kosong: !!n.kosong,
                    items: [],
                    total: 0,
                    selesai: 0,
                    terlambat: 0,
                    item_total: 0,
                    item_selesai: 0,
                });
            }
            const g = peta.get(nama);
            g.items.push(k);
            g.total += 1;
            penempatan += 1;
            if (k.Persen >= 100) g.selesai += 1;
            if (k.Is_Terlambat) g.terlambat += 1;
            g.item_total += k.Total_Item || 0;
            g.item_selesai += k.Item_Selesai || 0;
        });
    });

    const grup = [...peta.values()]
        .map((g) => ({ ...g, persen: g.item_total ? Math.round((g.item_selesai / g.item_total) * 100) : 0 }))
        /* Kelompok "tanpa …" selalu di bawah, berapa pun angkanya: ia bukan
           unit organisasi melainkan pekerjaan yang belum dirapikan, dan
           menaruhnya di tengah daftar membuatnya terbaca seperti departemen. */
        .sort(
            (a, b) =>
                a.kosong - b.kosong ||
                (b.terlambat > 0) - (a.terlambat > 0) ||
                a.persen - b.persen ||
                a.nama.localeCompare(b.nama),
        );

    return { grup, tugas: (items || []).length, penempatan, jamak: s.jamak };
}

/* ══════════════════════════ SARINGAN ══════════════════════════ */

/** Ember tenggat. Nilainya keadaan, bukan tanggal — itu yang ditanyakan orang. */
export const EMBER_TENGGAT = [
    { value: 'lewat', label: 'Sudah lewat' },
    { value: 'minggu', label: 'Dalam 7 hari' },
    { value: 'bulan', label: 'Dalam 30 hari' },
    { value: 'nanti', label: 'Lebih dari 30 hari' },
    { value: 'tanpa', label: 'Tanpa tenggat' },
];

export function emberTenggat(item, sisaHari) {
    if (!item || !item.Tenggat) return 'tanpa';
    if (sisaHari < 0) return 'lewat';
    if (sisaHari <= 7) return 'minggu';
    if (sisaHari <= 30) return 'bulan';
    return 'nanti';
}

export const EMBER_ESTIMASI = [
    { value: 'pendek', label: '≤ 10 menit' },
    { value: 'sedang', label: '≤ 30 menit' },
    { value: 'panjang', label: '> 30 menit' },
];

export function emberEstimasi(item) {
    const m = item && item.Estimasi ? Number(item.Estimasi) : null;
    if (!m) return null;
    if (m <= 10) return 'pendek';
    if (m <= 30) return 'sedang';
    return 'panjang';
}

export const OPSI_SUMBER = [
    { value: 'MANUAL', label: 'Ditugaskan admin' },
    { value: 'AUTO_RULE', label: 'Aturan otomatis' },
    { value: 'MANDIRI', label: 'Daftar sendiri' },
];

/**
 * Daftar pilihan saringan dirakit DARI DATA yang sedang dipegang layar.
 *
 * Bukan dari endpoint master. Alasannya: yang berguna bagi karyawan hanyalah
 * departemen yang benar-benar menyentuh tugasnya. Menawarkan seluruh master
 * membuat dropdown berisi puluhan pilihan yang semuanya menghasilkan nol
 * baris — dan yang memilihnya menyimpulkan tugasnya hilang, bukan bahwa
 * pilihan itu memang tak berlaku baginya.
 *
 * Nilai `null` (tanpa pemilik / tanpa jenis) ikut ditawarkan sebagai pilihan
 * karena ia keadaan yang nyata, bukan ketiadaan data.
 */
export function opsiSumbu(items, key) {
    const peta = new Map();

    (items || []).forEach((k) => {
        nilaiSumbu(k, key).forEach((n) => {
            const kunci = n.id === null ? '__kosong__' : String(n.id);
            if (!peta.has(kunci)) peta.set(kunci, { value: kunci, label: n.nama, level: n.level ?? 99 });
        });
    });

    return [...peta.values()].sort(
        (a, b) =>
            (a.value === '__kosong__') - (b.value === '__kosong__') ||
            a.level - b.level ||
            a.label.localeCompare(b.label),
    );
}

/** Apakah tugas ini punya salah satu nilai terpilih pada sumbu tertentu. */
export function cocokSumbu(item, key, terpilih) {
    if (!terpilih || !terpilih.length) return true;

    return nilaiSumbu(item, key).some((n) => {
        const idStr = n.id === null || n.id === undefined ? '__kosong__' : String(n.id);
        const nameStr = n.nama || (n.id === null ? 'Tanpa Pemilik' : '');
        return terpilih.includes(idStr) || (nameStr && terpilih.includes(nameStr));
    });
}

/**
 * Bentuk pohon folder hirarkis (Departemen -> Jenis Dokumen) dari himpunan item/kartu.
 * Dipakai oleh Functional Document & Public Document (katalog dokumen).
 *
 * @param {Array} items daftar tugas/kartu
 * @returns {Array} array of dept tree nodes
 */
export function bangunPohonFolder(items) {
    const deptMap = {};
    const paketDepts = {};

    (items || []).forEach((it) => {
        const id = it.Id_SOP_Paket || it.Id_SOP_Dokumen || it.id || JSON.stringify(it);
        const depts = (it.Departemen && it.Departemen.length)
            ? it.Departemen
            : (it.Nama_Departemen ? [{ id: it.Departemen_Id, nama: it.Nama_Departemen, kode: it.Kode_Departemen }] : []);
        const jenisList = (it.Jenis_Dokumen && it.Jenis_Dokumen.length)
            ? it.Jenis_Dokumen
            : (it.Nama_Jenis ? [{ id: it.Jenis_Dokumen_Id, nama: it.Nama_Jenis, kode: it.Kode_Jenis_Dokumen, level: it.Level_Hirarki }] : []);

        const normalizedDepts = depts.length ? depts : [{ id: null, nama: 'Tanpa Pemilik', kode: null, kosong: true }];
        const normalizedJenis = jenisList.length ? jenisList : [{ id: null, nama: 'Tanpa Jenis', kode: null, level: 99, kosong: true }];

        normalizedDepts.forEach((d) => {
            const dNama = d.nama || 'Tanpa Pemilik';
            const dId = 'dept_' + (d.id || dNama);
            if (!deptMap[dNama]) {
                deptMap[dNama] = {
                    id: dId,
                    nama: dNama,
                    kode: d.kode || null,
                    kosong: !d.id && dNama === 'Tanpa Pemilik',
                    pakets: {},
                    sub: {},
                };
            }
            deptMap[dNama].pakets[id] = true;
            paketDepts[id] = true;

            normalizedJenis.forEach((j) => {
                const jNama = j.nama || 'Tanpa Jenis';
                const jId = 'jns_' + dNama + '_' + (j.id || jNama);
                if (!deptMap[dNama].sub[jNama]) {
                    deptMap[dNama].sub[jNama] = {
                        id: jId,
                        nama: jNama,
                        kode: j.kode || null,
                        level: j.level !== undefined && j.level !== null ? Number(j.level) : 99,
                        kosong: !j.id && jNama === 'Tanpa Jenis',
                        pakets: {},
                    };
                }
                deptMap[dNama].sub[jNama].pakets[id] = true;
            });
        });
    });

    const tree = [];
    Object.values(deptMap).forEach((d) => {
        const children = Object.values(d.sub).map((s) => ({
            id: s.id,
            nama: s.nama,
            kode: s.kode,
            level: s.level,
            kosong: s.kosong,
            total: Object.keys(s.pakets).length,
        }));
        children.sort((a, b) => (a.level - b.level) || a.nama.localeCompare(b.nama));

        tree.push({
            id: d.id,
            nama: d.nama,
            kode: d.kode,
            kosong: d.kosong,
            total: Object.keys(d.pakets).length,
            children,
        });
    });

    tree.sort((a, b) => {
        if (a.kosong && !b.kosong) return 1;
        if (!a.kosong && b.kosong) return -1;
        return a.nama.localeCompare(b.nama);
    });

    return tree;
}

/**
 * Terapkan seluruh saringan.
 *
 * `sisaHari` disuntikkan, tidak dihitung di sini: perhitungannya sudah ada di
 * halaman dan memakai "hari ini" milik peramban. Dua salinan aturan tanggal
 * akan menyimpang pada tengah malam, pada zona waktu berbeda, atau ketika satu
 * di antaranya diperbaiki dan yang lain tidak.
 */
export function terapkanSaringan(items, f, sisaHari) {
    let arr = (items || []).slice();

    if (f.prioritas) arr = arr.filter((x) => x.Prioritas === f.prioritas);

    if (f.departemen && f.departemen.length) arr = arr.filter((x) => cocokSumbu(x, 'departemen', f.departemen));
    if (f.jenis && f.jenis.length) arr = arr.filter((x) => cocokSumbu(x, 'jenis', f.jenis));
    if (f.apqc && f.apqc.length) arr = arr.filter((x) => cocokSumbu(x, 'apqc', f.apqc));

    if (f.tenggat) arr = arr.filter((x) => emberTenggat(x, sisaHari(x)) === f.tenggat);
    if (f.sumber) arr = arr.filter((x) => (x.Sumber || 'MANUAL') === f.sumber);
    if (f.estimasi) arr = arr.filter((x) => emberEstimasi(x) === f.estimasi);

    // Saklar, bukan pilihan tiga keadaan: "sembunyikan yang perlu dibaca ulang"
    // bukan pertanyaan yang pernah diajukan siapa pun.
    if (f.perlu_ulang) arr = arr.filter((x) => (x.Item_Perlu_Ulang || 0) > 0);

    const kataKunci = f.search || f.q;
    if (kataKunci) {
        const q = String(kataKunci).toLowerCase();
        /* Pencarian ikut menjangkau judul dokumen, nomor SOP, nama paket, departemen, dan jenis. */
        arr = arr.filter((x) => {
            const ladang = [
                x.Judul,
                x.Nomor_SOP,
                x.Nomor_SOP_Resmi,
                x.Nama_Paket,
                x.Kode_Paket,
                ...(x.Departemen || []).map((d) => d.nama),
                ...(x.Jenis_Dokumen || []).map((d) => d.nama),
            ];

            return ladang.some((t) => t && String(t).toLowerCase().includes(q));
        });
    }

    return arr;
}

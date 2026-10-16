/**
 * Aturan kelayakan ujian HC Learn untuk diikat ke satu sel (level × kategori penilai).
 *
 * ── Yang BERUBAH, dan kenapa berkas ini nyaris ditulis ulang ──────────────────
 *
 * Sampai 9 September 2026 HC Learn melempar paket soal yang SUDAH berlabel kompetensi:
 * tiap butir membawa `Kode_Kompetensi`, dan paketnya membawa `Dimensi`. Kelayakan karena itu
 * bisa dijawab dari paketnya sendiri — cocokkan dimensinya, hitung cakupan kompetensinya.
 *
 * Kontraknya sekarang tidak begitu. Yang dilempar adalah ujian berisi ALAT TES berisi BAGIAN,
 * dan bagian-bagian itu TIDAK berlabel apa pun. Kompetensi bukan lagi milik HC Learn — ia
 * dipetakan admin di halaman Ikatan Paket Soal, satu bagian ke tepat satu kompetensi.
 *
 * Akibatnya "cakupan kompetensi" TIDAK BISA dihitung dari ujiannya, dan usaha menghitungnya
 * hanya menghasilkan angka nol untuk semua orang. Yang masih bisa dijawab sebelum pelabelan
 * hanya dua hal, dan keduanya ada di bawah:
 *
 *   1. identitas bagiannya ambigu atau tidak — pasangan (alat tes, bank soal) yang kembar;
 *   2. cacah bagiannya sepadan atau tidak dengan cacah kompetensi wajib level itu.
 *
 * Sisanya — kompetensi mana kurang, mana berlebih — baru punya jawaban SESUDAH admin
 * melabeli, dan itu dijawab `PaBijeksiLabel` di server lewat endpoint pemeriksaan.
 *
 * ── Hubungannya dengan server ────────────────────────────────────────────────
 *
 * Seluruh fungsi di sini MURNI: menerima ujian dan syaratnya, memulangkan jawaban. Tidak
 * mengambil data, tidak menyimpan apa pun.
 *
 * Ini CERMIN dari `PaBijeksiLabel::periksa()`, bukan penggantinya — kode masalahnya sengaja
 * dipakai ulang persis (`BAGIAN_KEMBAR`, `JUMLAH_TAK_SEPADAN`) supaya kalimat yang dibaca
 * admin di layar sama dengan yang dipulangkan server. Layar bisa dilewati; penjaga yang
 * hanya ada di layar bukan penjaga. Kalau keduanya berselisih, yang benar SELALU server.
 */

/**
 * Kompetensi wajib sebagai himpunan kode datar.
 *
 * @param {Array<{dimensi_kode: string, kompetensi: Array<{kode: string}>}>} kompetensiWajib
 * @returns {Set<string>}
 */
export function himpunanKompetensiWajib(kompetensiWajib) {
    return new Set((kompetensiWajib ?? []).flatMap((g) => (g.kompetensi ?? []).map((c) => c.kode)));
}

/**
 * Identitas satu bagian: PASANGAN (alat tes, bank soal).
 *
 * Cermin `PaBagianKatalog::kunci()`. Bukan `id_jenis_soal` saja — satu bank soal boleh
 * muncul di dua alat tes berbeda, dan itu bagian yang berbeda.
 */
export function kunciBagian(bagian) {
    return `${bagian?.id_jenis_indikator ?? ''}|${bagian?.id_jenis_soal ?? ''}`;
}

/**
 * Kunci bagian yang muncul lebih dari sekali.
 *
 * Cermin `PaBagianKatalog::kembar()`. Selama ada yang kembar, dua bagian berbeda menunjuk
 * baris yang sama dan pelabelan tidak bisa dipercaya sama sekali.
 *
 * @returns {string[]}
 */
export function bagianKembar(paket) {
    const cacah = new Map();

    for (const b of paket?.Bagian ?? []) {
        const k = kunciBagian(b);
        cacah.set(k, (cacah.get(k) ?? 0) + 1);
    }

    return [...cacah.entries()].filter(([, n]) => n > 1).map(([k]) => k);
}

/**
 * Bagian dikelompokkan per ALAT TES, urut sesuai kiriman HC Learn.
 *
 * Dikelompokkan karena begitulah bentuknya di sana, dan admin yang melabeli membaca
 * bagian dalam konteks alat tesnya — nama bagian seperti "Analytical Thinking" berulang
 * antar alat tes, dan tanpa pengelompokan keduanya tampak sebagai baris kembar.
 *
 * @returns {Array<{id: string, nama: string, bagian: Array<object>}>}
 */
export function perAlatTes(paket) {
    const peta = new Map();

    for (const b of paket?.Bagian ?? []) {
        const id = String(b?.id_jenis_indikator ?? '');
        const ada = peta.get(id);

        if (ada) {
            ada.bagian.push(b);
            continue;
        }

        peta.set(id, { id, nama: b?.nama_alat_tes || 'Alat tes tanpa nama', bagian: [b] });
    }

    return [...peta.values()];
}

/**
 * Kesepadanan cacah: berapa bagian yang dipunyai ujian, berapa kompetensi yang dituntut level.
 *
 * Karena satu bagian mewakili tepat satu kompetensi, keduanya HARUS sama. Ini satu-satunya
 * perbandingan yang bermakna sebelum pelabelan — dan sengaja tidak disebut "coverage",
 * karena yang dibandingkan bukan lagi kompetensi yang tercakup.
 *
 * @returns {{bagian: number, wajib: number, sepadan: boolean, selisih: number}}
 */
export function kesepadanan(paket, kompetensiWajib) {
    const bagian = (paket?.Bagian ?? []).length;
    const wajib = himpunanKompetensiWajib(kompetensiWajib).size;

    return { bagian, wajib, sepadan: wajib > 0 && bagian === wajib, selisih: bagian - wajib };
}

/**
 * Layak diikat bila bagiannya ada, identitasnya tidak ambigu, dan cacahnya sepadan.
 *
 * Kelengkapan pemetaannya TIDAK diperiksa di sini — pada saat kartu ini dirender, admin
 * belum melabeli apa pun. Yang menjaga kelengkapan adalah gerbang simpan di server.
 */
export function bisaDipilih(paket, kompetensiWajib) {
    return paket?.bagian_tersedia !== false
        && bagianKembar(paket).length === 0
        && kesepadanan(paket, kompetensiWajib).sepadan;
}

/**
 * Alasan yang bisa dibaca admin.
 *
 * Kartu mati tanpa keterangan membuat admin mengira sistemnya rusak, lalu mencoba berulang
 * kali. Urutannya disengaja: sebut penghalang yang paling mendasar lebih dulu, karena
 * memperbaiki yang itu sering membuat sisanya ikut hilang.
 */
export function alasanTakBisa(paket, kompetensiWajib) {
    if (paket?.bagian_tersedia === false) {
        return 'Ujian ini tidak punya bagian yang bisa dilabeli — seluruh alat tesnya bertipe khusus (Kraeplin, PAPI, DISC).';
    }

    const kembar = bagianKembar(paket);

    if (kembar.length) {
        return `${kembar.length} bagian identitasnya kembar. HC Learn tidak memberi id tersendiri per bagian, `
            + 'jadi bagian yang memakai bank soal yang sama di dalam satu alat tes tidak bisa dibedakan. '
            + 'Minta HC Learn memisahkan bank soalnya sebelum ujian ini diikat.';
    }

    const { bagian, wajib, selisih } = kesepadanan(paket, kompetensiWajib);

    if (wajib === 0) {
        return 'Level ini belum memiliki syarat kompetensi di Master Data > Syarat Kompetensi.';
    }

    if (selisih !== 0) {
        return `Ujian ini punya ${bagian} bagian, sedangkan level ini menuntut ${wajib} kompetensi. `
            + 'Karena satu bagian mewakili tepat satu kompetensi, keduanya harus sama.';
    }

    return '';
}

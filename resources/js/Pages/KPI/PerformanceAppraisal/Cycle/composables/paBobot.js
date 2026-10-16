/*
 | Aturan kunci dan pembagian bobot penilai — DIPAKAI BERSAMA oleh dialognya dan Tahap 5.
 |
 | KENAPA DIPISAH
 |
 | Dialog menampilkan angkanya, induknya yang mengirimkannya. Kalau keduanya menghitung sendiri
 | "siapa saja yang punya bobot" dan "apa kuncinya", cukup salah satu bergeser untuk membuat
 | layar menunjukkan satu himpunan sementara yang terkirim himpunan yang lain — dan server akan
 | menolaknya dengan "Seluruh penilai dalam satu kategori harus disebut bobotnya sekaligus",
 | pesan yang benar tetapi menunjuk ke tempat yang salah.
 */

/*
 | Kunci bobot satu penilai.
 |
 | Penilai yang baru dicentang BELUM punya `Id_PA_Assignment` — barisnya baru lahir saat
 | disimpan. Supaya bobotnya tetap bisa disunting sekarang, ia dipegang lewat kunci sementara
 | berdasarkan identitas orangnya, lalu diterjemahkan ke id penugasan yang sebenarnya sesudah
 | penugasannya dibuat.
 */
export function kunciBobot(candidate) {
    return candidate.assignment_id
        ? String(candidate.assignment_id)
        : `${KUNCI_BARU}${candidate.kode_perusahaan}|${candidate.kode_karyawan}`;
}

export const KUNCI_BARU = 'baru:';

/*
 | Penilai yang PUNYA bobot dalam satu kategori: yang sudah terpasang, ditambah yang baru
 | dicentang dan belum tersimpan.
 |
 | Yang baru dicentang ikut dihitung karena bobot kategori dibagi ke seluruh anggotanya nanti.
 | Mengabaikannya membuat total terbaca 100% padahal sebentar lagi dibagi ke lebih banyak orang.
 */
export function penilaiBerbobot(item, terpilih = []) {
    return (item.calon || []).filter(
        (c) => Boolean(c.assignment_id) || terpilih.includes(c.kode_karyawan),
    );
}

/*
 | Membagi 100 rata ke sebuah daftar penilai, hasilnya dijamin berjumlah TEPAT 100.
 |
 | Sisa pembulatan diberikan ke yang terakhir. Membagi 100/3 lalu membulatkan tiap bagian
 | menghasilkan 99,99 — dan server menolaknya, sehingga "bagi rata" justru menghasilkan keadaan
 | yang tidak bisa disimpan.
 */
export function bagiRataBobot(daftar) {
    const hasil = {};

    if (! daftar.length) {
        return hasil;
    }

    const dasar = Math.floor((100 / daftar.length) * 100) / 100;
    let sisa = 100;

    daftar.forEach((c, i) => {
        const nilai = i === daftar.length - 1 ? Math.round(sisa * 100) / 100 : dasar;

        hasil[kunciBobot(c)] = nilai;
        sisa = Math.round((sisa - nilai) * 100) / 100;
    });

    return hasil;
}

/*
 | Menyebar sisa 100 ke penilai yang BELUM disentuh admin.
 |
 | KENAPA BUKAN PLAFON PER KOTAK
 |
 | Kategori selalu berjumlah tepat 100. Kalau tiap kotak diberi plafon "100 dikurangi bobot yang
 | lain", plafonnya sama dengan angka yang sudah tertulis di kotak itu sendiri — sehingga nilainya
 | hanya bisa diturunkan dan tidak pernah bisa dinaikkan. Kelihatan seperti penjagaan, padahal
 | mengunci seluruh penyuntingan.
 |
 | Yang dipikirkan admin adalah "atasan ini 60%", bukan "turunkan dua orang lain dulu supaya ada
 | ruang". Jadi kotak yang ia ketik menerima angkanya, dan sisanya dibagi ke yang belum ia sentuh.
 |
 | Yang SUDAH ia sentuh tidak pernah diusik lagi. Tanpa itu, mengatur orang kedua akan merusak
 | angka orang pertama, dan pekerjaannya berputar tanpa pernah selesai. Konsepnya sama dengan
 | `Bobot_Kustom` di database, hanya dijalankan di layar.
 |
 | Mengembalikan null bila penyebarannya tidak mungkin — semua sudah disentuh, atau yang disentuh
 | sudah melewati 100. Layar lalu menunjukkan selisihnya dan Simpan tertahan; menebak-nebak di
 | keadaan itu berarti menimpa angka yang baru saja sengaja diketik.
 */
export function sebarSisa(daftar, bobot, terkunci) {
    const semua = daftar.map(kunciBobot);
    const bebas = semua.filter((k) => ! terkunci.has(k));

    if (! bebas.length) {
        return null;
    }

    const dipakai = semua
        .filter((k) => terkunci.has(k))
        .reduce((acc, k) => acc + Number(bobot[k] || 0), 0);

    const sisa = Math.round((100 - dipakai) * 100) / 100;

    if (sisa < 0) {
        return null;
    }

    const hasil = {};
    const dasar = Math.floor((sisa / bebas.length) * 100) / 100;
    let tersisa = sisa;

    bebas.forEach((k, i) => {
        const nilai = i === bebas.length - 1 ? Math.round(tersisa * 100) / 100 : dasar;

        hasil[k] = nilai;
        tersisa = Math.round((tersisa - nilai) * 100) / 100;
    });

    return hasil;
}

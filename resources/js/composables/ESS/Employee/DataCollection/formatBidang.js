/**
 * Aturan format isian formulir pengumpulan data — satu tempat, dua pemakai.
 *
 * Wizard memakainya untuk memutuskan boleh maju atau tidak; BidangIsian
 * memakainya untuk membatasi ketikan dan memberi pesan. Sebelumnya keduanya
 * menebak sendiri sifat field dari potongan kata pada labelnya, dan tebakan
 * itu menabrak: label 'Status Per-NIK-ahan' mengandung 'nik', sehingga
 * pertanyaan status pernikahan dituntut '16 digit angka sesuai KTP' — tuntutan
 * yang tidak bisa dipenuhi jawaban apa pun, jadi orangnya berhenti di situ.
 *
 * Karena itu sifat field tidak lagi ditebak: yang berlaku hanya yang tertulis
 * di `config/pengumpulan_data.php` sebagai `format`, `maks_digit`, `min_digit`.
 */

const POLA_EMAIL = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

export const AWALAN_HP = '8';
export const AWALAN_SIMPAN_HP = '62';
export const MAKS_DIGIT_HP = 13;
export const MIN_DIGIT_HP = 9;
export const MAKS_DIGIT_NIK = 16;

// USIA_MIN / USIA_MAKS dihapus 4 Sep 2026. Batas usia 15-70 sudah dicabut dari
// server pada 3 Sep, dan sejak itu kedua konstanta ini tidak dipakai siapa pun
// — tetapi masih terbaca sebagai batas yang berlaku oleh siapa pun yang membuka
// berkas ini. Batas yang kelak dipasang lagi datang lewat skema (`usia_maks`),
// bukan lewat angka yang ditanam di sini.

const dua = (n) => String(n).padStart(2, '0');

/** 'YYYY-MM-DD' dari sebuah Date, tanpa lewat UTC (yang bisa menggeser sehari). */
const keTeksTanggal = (d) => `${d.getFullYear()}-${dua(d.getMonth() + 1)}-${dua(d.getDate())}`;

const geserTahun = (d, tahun) => {
    const hasil = new Date(d.getFullYear() - tahun, d.getMonth(), d.getDate());

    return hasil;
};

const bacaTanggal = (nilai) => {
    const teks = String(nilai ?? '').trim().slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(teks)) return null;

    const [t, b, h] = teks.split('-').map(Number);
    const d = new Date(t, b - 1, h);

    return Number.isNaN(d.getTime()) ? null : d;
};

const tanggalIndonesia = (d) =>
    d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

/**
 * Jendela tanggal lahir yang sah.
 *
 * Tepi atasnya HARI INI, sama dengan satu-satunya pagar yang tersisa di server
 * (`before_or_equal:today`) sejak batas usia 15-70 dilepas pada 3 Sep 2026.
 * Tepi bawahnya 100 tahun — kalender sengaja lebih ketat daripada server, sebab
 * arah yang berbahaya cuma satu: kalender menawarkan tanggal yang kemudian
 * ditolak. Sebaliknya tidak merugikan siapa pun.
 *
 * `usia_maks` masih dibaca kalau skema mengirimnya, supaya batas yang kelak
 * dipasang lagi di server langsung berlaku di sini tanpa mengubah berkas ini.
 *
 * Dipakai dua-duanya — untuk mematikan tanggal di kalender DAN untuk menyusun
 * pesan galat — supaya yang ditawarkan kalender tidak pernah berbeda dari yang
 * diterima server.
 */
export const batasTanggalLahir = (definisi, hariIni = new Date()) => {
    if (definisi?.format !== 'tanggal_lahir') return null;

    const maks = definisi.usia_maks ?? 100;

    return {
        paling_akhir: keTeksTanggal(hariIni),
        paling_awal: keTeksTanggal(geserTahun(hariIni, maks)),
        usia_maks: maks,
    };
};

/**
 * Apakah sebuah pertanyaan muncul, mengingat jawaban yang sudah ada.
 *
 * Syaratnya DIBACA dari skema ('tampil_jika'), tidak ditulis ulang di sini.
 * Server membaca tulisan yang sama untuk memutuskan menuntut atau membuang
 * jawabannya — kalau keduanya menyimpulkan sendiri-sendiri, yang paling
 * mungkin terjadi adalah kotaknya hilang di layar tapi tetap dituntut saat
 * Kirim, dan tidak ada isian yang bisa memuaskannya.
 */
export const bidangTampil = (definisi, jawaban) => {
    const syarat = definisi?.tampil_jika ?? null;

    if (!syarat) return true;

    return (syarat.nilai ?? []).includes(jawaban?.[syarat.field]);
};

/** Kunci-kunci yang benar-benar tampil dari sebuah daftar langkah. */
export const kunciTampil = (daftarKunci, skemaField, jawaban) =>
    (daftarKunci ?? []).filter((kunci) => bidangTampil(skemaField?.[kunci], jawaban));

export const angkaSaja = (nilai) => String(nilai ?? '').replace(/\D/g, '');

/**
 * Angka nomor HP sebagaimana DIKETIK orang: tanpa '+62' dan tanpa '0' di depan.
 *
 * Kotaknya sudah menampilkan '+62' sebagai awalan tetap, jadi inilah satuan
 * yang dipakai `maks_digit`/`min_digit` — bukan panjang nilai tersimpan.
 */
export const angkaDiketikHp = (nilai) => {
    let angka = angkaSaja(nilai);

    if (angka.startsWith('62')) angka = angka.slice(2);
    if (angka.startsWith('0')) angka = angka.slice(1);

    return angka;
};

/**
 * Nilai yang disimpan: berawalan '62', seperti yang dituntut server.
 *
 * '62' dan bukan '0' karena itu bentuk yang dipakai seluruh sistem — Karyawan.HP
 * menyimpannya begitu (958 dari 958 baris), dan pengirim WhatsApp mengubah apa
 * pun jadi '62…' sebelum berangkat. Yang DIKETIK tetap dimulai dari '8';
 * awalannya ditambahkan di sini.
 *
 * Awalannya DIBACA dari skema ('prefix_simpan'), sama seperti sifat field yang
 * lain. AWALAN_SIMPAN_HP hanya cadangan untuk pemanggil yang tidak membawa
 * definisi — kalau ia sampai berselisih dengan config, yang menang tetap
 * config, sebab pola validasi server disusun dari nilai yang sama.
 */
export const nilaiSimpanHp = (nilai, maksDigit = MAKS_DIGIT_HP, awalanSimpan = AWALAN_SIMPAN_HP) => {
    const angka = angkaDiketikHp(nilai).slice(0, maksDigit);

    return angka !== '' ? `${awalanSimpan}${angka}` : '';
};

export const nilaiSimpanNik = (nilai, maksDigit = MAKS_DIGIT_NIK) =>
    angkaSaja(nilai).slice(0, maksDigit);

/**
 * Cacahan sederhana. '0' adalah jawaban yang SAH, bukan isian kosong — jadi
 * yang dikembalikan tetap '0', bukan string kosong.
 */
export const nilaiSimpanAngka = (nilai, maksDigit) => {
    // Nol di depan dibuang: server memakai aturan 'integer' yang menolak '01',
    // dan kalimat galatnya ('harus berupa angka') tidak menunjuk apa yang
    // salah. '0' sendirian tetap nol.
    const angka = angkaSaja(nilai).replace(/^0+(?=\d)/, '');

    return maksDigit ? angka.slice(0, maksDigit) : angka;
};

/**
 * Batas ketikan sebuah field, atau null bila memang tak berbatas.
 * Dipasang sebagai `maxlength` supaya kotaknya BERHENTI menerima angka di
 * batasnya — bukan menerima terus lalu menolak setelah tombol ditekan.
 */
export const batasKetikan = (definisi) => {
    const format = definisi?.format ?? null;

    if (format === 'angka') return definisi?.maks_digit ?? null;

    if (format !== 'nik' && format !== 'hp') return null;

    return definisi?.maks_digit ?? (format === 'nik' ? MAKS_DIGIT_NIK : MAKS_DIGIT_HP);
};

/**
 * Galat yang layak muncul saat itu juga, tanpa menunggu tombol Lanjut.
 */
export const galatSeketika = (definisi, nilai, hariIni = new Date()) => {
    const format = definisi?.format ?? null;

    // 'angka' ikut diperiksa seketika karena kotaknya sudah dibatasi jumlah
    // digitnya: satu-satunya kesalahan yang tersisa adalah nilainya di luar
    // rentang, dan itu sudah bisa dinilai pada angka yang baru saja diketik.
    if (format === 'email' || format === 'tanggal_lahir' || format === 'angka') {
        return galatFormat(definisi, nilai, hariIni);
    }

    if (format === 'hp') {
        const awalan = definisi?.prefix ?? AWALAN_HP;
        const angka = angkaDiketikHp(nilai);

        return angka !== '' && !angka.startsWith(awalan)
            ? `Nomor HP harus diawali ${awalan} setelah +62 (contoh: ${awalan}123456789)`
            : null;
    }

    return null;
};

/**
 * Pesan galat format, atau null bila tidak ada yang salah.
 *
 * Isian kosong BUKAN urusan fungsi ini — "wajib diisi" diperiksa pemanggilnya,
 * supaya kotak yang belum disentuh tidak langsung dimarahi soal format.
 */
export const galatFormat = (definisi, nilai, hariIni = new Date()) => {
    const format = definisi?.format ?? null;
    const teks = String(nilai ?? '').trim();

    if (!format || teks === '') return null;

    if (format === 'tanggal_lahir') {
        const tgl = bacaTanggal(teks);

        if (!tgl) return 'Tanggal lahir belum terisi dengan benar';

        const teksHariIni = keTeksTanggal(hariIni);
        if (teks > teksHariIni) {
            return 'Tanggal lahir tidak boleh di masa depan';
        }

        return null;
    }

    if (format === 'email') {
        return POLA_EMAIL.test(teks) ? null : 'Format email belum benar (contoh: nama@domain.com)';
    }

    if (format === 'angka') {
        const angka = angkaSaja(teks);

        if (angka === '' || angka !== teks) return 'Isi dengan angka saja, tanpa titik atau koma';

        const jumlah = Number(angka);
        const min = definisi.nilai_min ?? 0;
        const maks = definisi.nilai_maks ?? null;

        if (jumlah < min) return `Tidak boleh kurang dari ${min}`;
        if (maks !== null && jumlah > maks) return `Paling banyak ${maks}`;

        return null;
    }

    if (format === 'nik') {
        const maks = definisi.maks_digit ?? MAKS_DIGIT_NIK;

        return angkaSaja(teks).length === maks ? null : `Wajib ${maks} digit angka sesuai KTP`;
    }

    if (format === 'hp') {
        const maks = definisi.maks_digit ?? MAKS_DIGIT_HP;
        const min = definisi.min_digit ?? MIN_DIGIT_HP;
        const awalan = definisi.prefix ?? AWALAN_HP;
        const angka = angkaDiketikHp(teks);

        // Angka PERTAMA diperiksa lebih dulu: itu satu-satunya bagian yang sudah
        // bisa dinilai sejak awal, dan justru itu yang dituntut pola server
        // ('^08…'). Selama browser cuma memeriksa panjang, nomor seperti
        // 021-233… lolos empat langkah lalu ditolak di ujung.
        if (angka !== '' && !angka.startsWith(awalan)) {
            return `Nomor HP harus diawali ${awalan} setelah +62 (contoh: ${awalan}123456789)`;
        }

        if (angka.length < min) return `Nomor HP belum lengkap (minimal ${min} angka setelah +62)`;
        if (angka.length > maks) return `Nomor HP kelebihan angka (maksimal ${maks} angka setelah +62)`;

        return null;
    }

    return null;
};

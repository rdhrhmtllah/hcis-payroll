import { computed, ref } from 'vue';
import * as paApi from '../../api/paApi.js';
import { KUNCI_BARU, bagiRataBobot, kunciBobot, penilaiBerbobot, sebarSisa } from './paBobot.js';

/*
 | Penyuntingan bobot penilai — DIPAKAI BERSAMA oleh Tahap 5 dan Daftar Siklus.
 |
 | KENAPA ADA
 |
 | `PaAssignmentDialog` dibuka dari DUA tempat. Kabel bobotnya semula hanya dipasang di Tahap 5,
 | sehingga dari Daftar Siklus kotak bobotnya tampil dan bisa diketik tetapi tidak tersambung ke
 | apa pun: `@bobot-change` tak ada yang mendengarkan, `:weights` tak diikat, dan Simpan tidak
 | membawa satu angka pun. Admin mengetik 20/80, menekan Simpan, dan angkanya kembali seperti
 | semula — tanpa galat, tanpa pesan, tanpa petunjuk.
 |
 | Menyalin kabelnya ke berkas kedua akan menghasilkan dua salinan yang menyimpang; itu sudah
 | terjadi sekali di modul ini. Jadi seluruh keadaan dan aturannya dipindahkan ke sini, dan kedua
 | layar memanggil yang sama.
 |
 | @param {object} penugasan hasil `usePaAssignment()` — dibutuhkan `kategori`, `pilihan`, `ubahPilihan`, `muat`
 */
export function usePaBobotEditor(penugasan) {
    /* Bobot yang sedang disunting, berkunci `kunciBobot()`. */
    const bobotSunting = ref({});

    /*
     | Kunci yang sudah DISENTUH admin. Yang di sini tidak pernah diusik lagi oleh penyebaran
     | sisa; tanpa itu, mengatur orang kedua akan merusak angka orang pertama dan pekerjaannya
     | berputar tanpa pernah selesai.
     */
    const bobotTerkunci = ref({});

    /*
     | Kategori yang admin minta MENGIKUTI pembagian rata. Dikirim lewat jalur tersendiri supaya
     | penanda `Bobot_Kustom` benar-benar dilepas, bukan diganti angka rata yang tetap bertanda.
     */
    const kategoriRata = ref({});

    /*
     | Pesan bobot punya SALURAN SENDIRI.
     |
     | Sebelumnya galat bobot dititipkan ke saluran galat penugasan, dan layar menampilkannya
     | dengan judul "Penugasan ditolak" — padahal penugasannya tersimpan dan yang ditolak
     | pembagian bobotnya. Judul yang salah mengarahkan admin memperbaiki hal yang tidak rusak.
     */
    const bobotGalat = ref('');
    const bobotKabar = ref('');

    const kategori = computed(() => penugasan.kategori.value || []);

    function cariKategori(kodeKategori) {
        return kategori.value.find((k) => k.kode_kategori === kodeKategori) || null;
    }

    function daftarBerbobot(kodeKategori) {
        const item = cariKategori(kodeKategori);

        return item ? penilaiBerbobot(item, penugasan.pilihan[kodeKategori] || []) : [];
    }

    /** Nilai yang SEDANG BERLAKU di layar untuk satu kategori, berkunci `kunciBobot()`. */
    function nilaiBerlaku(daftar, tambahan = {}) {
        const peta = {};

        for (const c of daftar) {
            const k = kunciBobot(c);
            const sumber = { ...bobotSunting.value, ...tambahan };

            peta[k] = sumber[k] !== undefined ? sumber[k] : (c.bobot ?? 0);
        }

        return peta;
    }

    function himpunanTerkunci(tambahan = {}) {
        const gabung = { ...bobotTerkunci.value, ...tambahan };

        return new Set(Object.keys(gabung).filter((k) => gabung[k]));
    }

    /** Kategori mana yang memiliki satu kunci bobot; null bila tidak ketemu. */
    function kategoriPemilikKunci(kunci) {
        for (const item of kategori.value) {
            if (daftarBerbobot(item.kode_kategori).some((c) => kunciBobot(c) === String(kunci))) {
                return item.kode_kategori;
            }
        }

        return null;
    }

    function lepasTandaRata(kodeKategori) {
        const sisa = { ...kategoriRata.value };

        delete sisa[kodeKategori];
        kategoriRata.value = sisa;
    }

    /* Melepas tanda "sudah disentuh" seluruh penilai satu kategori. Tanpa ini "bagi rata" tidak
       benar-benar merata: angka yang pernah diketik tetap dipertahankan penyebaran berikutnya. */
    function lepasKunciKategori(kodeKategori) {
        const sisa = { ...bobotTerkunci.value };

        for (const c of daftarBerbobot(kodeKategori)) {
            delete sisa[kunciBobot(c)];
        }

        bobotTerkunci.value = sisa;
    }

    function ratakanDiLayar(kodeKategori) {
        const daftar = daftarBerbobot(kodeKategori);

        if (! daftar.length) {
            return;
        }

        bobotSunting.value = { ...bobotSunting.value, ...bagiRataBobot(daftar) };
    }

    function ubahBobot({ kunci, nilai }) {
        const baru = { ...bobotSunting.value, [kunci]: nilai };
        const tandaBaru = { [kunci]: true };
        const kodeKategori = kategoriPemilikKunci(kunci);

        if (kodeKategori !== null) {
            /* Sisanya disebar ke penilai yang BELUM disentuh, supaya kategorinya tetap 100 tanpa
               admin harus menurunkan orang lain lebih dulu. Kalau tak ada lagi yang bisa
               menyerap, `sebarSisa` mengembalikan null dan keadaannya dibiarkan apa adanya —
               selisihnya terlihat di baris total dan Simpan tertahan. Menebak di situ berarti
               menimpa angka yang baru saja sengaja diketik. */
            const daftar = daftarBerbobot(kodeKategori);
            const sebar = sebarSisa(daftar, nilaiBerlaku(daftar, baru), himpunanTerkunci(tandaBaru));

            if (sebar !== null) {
                Object.assign(baru, sebar);
            }

            /* Begitu satu angka disentuh, kategorinya bukan "rata" lagi — ia keputusan. */
            lepasTandaRata(kodeKategori);
        }

        bobotSunting.value = baru;
        bobotTerkunci.value = { ...bobotTerkunci.value, ...tandaBaru };
    }

    /*
     | "Bagi rata" bukan sekadar mengisi angka rata: selama penandanya masih menyala, angka itu
     | tetap diperlakukan sebagai keputusan dan kategorinya berhenti menyesuaikan diri saat
     | penilainya berubah. Karena itu kategorinya dicatat, dan saat disimpan ia dikirim lewat
     | `kembalikanBobotRata` — bukan lewat penyimpanan bobot biasa yang justru menandainya.
     */
    function bagiRataKategori(kodeKategori) {
        kategoriRata.value = { ...kategoriRata.value, [kodeKategori]: true };
        lepasKunciKategori(kodeKategori);
        ratakanDiLayar(kodeKategori);
    }

    /*
     | Mencentang atau membuang centang MENGUBAH susunan penilai, jadi pembagiannya disegarkan
     | seketika. Tanpa ini kotak bobot penilai baru muncul kosong dan totalnya langsung salah —
     | admin harus membetulkan sendiri angka yang seharusnya sudah benar sejak awal.
     */
    function ubahPilihanDanRatakan({ kodeKategori, candidate }) {
        penugasan.ubahPilihan(kodeKategori, candidate);

        const daftar = daftarBerbobot(kodeKategori);
        const sebar = sebarSisa(daftar, nilaiBerlaku(daftar), himpunanTerkunci());

        if (sebar !== null) {
            /* Angka yang sudah diatur admin DIHORMATI; penilai baru menyerap sisanya. */
            bobotSunting.value = { ...bobotSunting.value, ...sebar };

            return;
        }

        /* Tak ada yang bisa menyerap: kembali ke rata penuh dan lepas kuncinya, karena penilai
           baru dengan bobot 0% adalah penilai yang jawabannya tidak ikut dihitung. */
        bagiRataKategori(kodeKategori);
    }

    /*
     | Muatan bobot untuk permintaan Simpan, ber-IDENTITAS orang.
     |
     | Sengaja BUKAN id penugasan: penilai yang baru dicentang belum punya id, dan menunggunya
     | berarti menyegarkan usulan lebih dulu — satu perjalanan mahal ke server hanya untuk
     | menerjemahkan kunci. Dengan identitas, server sendiri yang mencocokkannya sesudah barisnya
     | lahir, di dalam transaksi yang sama.
     |
     | Server menuntut SELURUH penilai satu kategori disebut sekaligus, jadi kategori yang
     | tersentuh dikirim lengkap - yang tidak disunting memakai nilai yang sedang berlaku.
     | Kategori yang diminta rata dilewati: ia dikerjakan lewat `bobot_rata`.
     */
    function muatanSimpan() {
        const mintaRata = Object.keys(kategoriRata.value).filter((k) => kategoriRata.value[k]);
        const bobot = [];

        for (const item of kategori.value) {
            if (mintaRata.includes(item.kode_kategori)) {
                continue;
            }

            const daftar = daftarBerbobot(item.kode_kategori);

            if (! daftar.length) {
                continue;
            }

            const tersentuh = daftar.some(
                (c) => Object.prototype.hasOwnProperty.call(bobotSunting.value, kunciBobot(c)),
            );

            if (! tersentuh) {
                continue;
            }

            for (const c of daftar) {
                const k = kunciBobot(c);

                bobot.push({
                    kode_kategori: item.kode_kategori,
                    kode_perusahaan: c.kode_perusahaan,
                    kode_karyawan: c.kode_karyawan,
                    bobot: Number(bobotSunting.value[k] !== undefined ? bobotSunting.value[k] : (c.bobot ?? 0)),
                });
            }
        }

        return { bobot, bobot_rata: mintaRata };
    }

    /*
     | Membaca hasil Simpan dan mengatakannya di saluran bobot.
     |
     | Keberhasilannya DIKATAKAN: tanpa itu penyimpanan bobot yang berhasil tidak meninggalkan
     | jejak apa pun, dan tidak ada cara membedakannya dari yang gagal diam-diam.
     */
    function terapkanHasil(res) {
        bobotGalat.value = '';
        bobotKabar.value = '';

        if (res?.error) {
            /* Galatnya milik SATU permintaan yang mengerjakan keduanya, jadi penugasannya pun
               tidak tersimpan. Suntingannya sengaja TIDAK dibuang: yang diketik admin masih di
               layar untuk diperbaiki. */
            return;
        }

        const d = res?.data || {};
        const bagian = [];

        if (d.bobot_diterapkan > 0) {
            bagian.push(`Pembagian bobot tersimpan untuk ${d.bobot_diterapkan} penilai.`);
        }

        if ((d.kategori_dikembalikan_rata?.length || 0) > 0) {
            bagian.push('Pembagian bobot dikembalikan ke rata.');
        }

        if ((d.bobot_kustom_digugurkan?.length || 0) > 0) {
            const nama = d.bobot_kustom_digugurkan
                .map((id) => kategori.value.find((k) => k.kategori_id === id)?.nama_kategori || `#${id}`)
                .join(', ');

            bagian.push(`Bobot khusus kategori ${nama} dikembalikan ke pembagian rata karena `
                + 'susunan penilainya berubah.');
        }

        bobotKabar.value = bagian.join(' ');
        kosongkan();
    }

    function kosongkan() {
        bobotSunting.value = {};
        kategoriRata.value = {};
        bobotTerkunci.value = {};
    }

    /* Pesannya dibuang terpisah dari suntingannya: `kosongkan()` dipanggil SESUDAH berhasil
       menyimpan, dan membuang pesan suksesnya di sana berarti tidak pernah terlihat. */
    function kosongkanPesan() {
        bobotGalat.value = '';
        bobotKabar.value = '';
    }

    return {
        bobotSunting,
        bobotTerkunci,
        kategoriRata,
        bobotGalat,
        bobotKabar,
        kosongkanPesan,
        ubahBobot,
        bagiRataKategori,
        ubahPilihanDanRatakan,
        muatanSimpan,
        terapkanHasil,
        kosongkan,
    };
}

export default usePaBobotEditor;

import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import paApi from '../../api/paApi.js';

export const LANGKAH_RINGKASAN = 'RINGKASAN';

/**
 * Keadaan wizard tugas penilaian.
 *
 * LANGKAH AKTIF DATANG DARI SERVER (`langkah_aktif`), tidak ditebak dari kelengkapan isi.
 * Layar hanya memegang dua hal: langkah mana yang sedang DILIHAT, dan apakah yang tampil
 * lembarnya atau pratinjau Kalibrasi Penilai-nya.
 *
 * TRANSISI: bila langkah yang sedang dilihat berubah menjadi TUNTAS setelah dimuat ulang —
 * penilai baru menyelesaikan lembar terakhirnya di HC Learn — tampilan berpindah ke pratinjau
 * langkah itu. Itulah titik Kalibrasi Penilai di antara dua kategori.
 *
 * DIMUAT ULANG SAAT TAB AKTIF KEMBALI: penilai mengisi di tab HC Learn dan lembarnya kembali
 * lewat callback. Tanpa ini, ia kembali ke HCIS dan melihat langkahnya seolah belum tuntas.
 */
/**
 * Pratinjau (Matriks Kalibrasi) hanya untuk kategori yang BOLEH dikalibrasi. Kategori lain
 * tidak punya apa pun untuk diubah di sana, jadi penilai cukup melihat daftar lembarnya.
 */
export function bolehPratinjau(l) {
    return Boolean(l?.boleh_kalibrasi) && l?.status !== 'TERKUNCI';
}

/*
 * PRATINJAU KALIBRASI WAJIB DILIHAT SEKALI. Sejak HC Learn dibuka di tab yang sama, penilai
 * selalu pulang lewat pemuatan baru, dan server langsung menunjuk kategori berikutnya —
 * Matriks Kalibrasi kategori yang baru tuntas terlewati dan penilai tidak pernah tahu ia
 * boleh mengkalibrasi. Penanda per peramban: cukup untuk "sudah pernah melihat", dan bila
 * hilang (peramban lain, data situs dihapus) akibatnya hanya pratinjau tampil sekali lagi.
 */
/* Nama kunci diganti dari `pa-wizard-pratinjau-dilihat`: versi itu ikut menandai pratinjau
   yang dibuka selagi kategorinya BELUM tuntas, sehingga saat lembar terakhir masuk
   pratinjaunya tidak lagi dibuka. Penanda lama sengaja diabaikan. */
const AWALAN_DILIHAT = 'pa-wizard-pratinjau-tuntas-dilihat';

function kunciDilihat(cycleId, kode) {
    return `${AWALAN_DILIHAT}:${cycleId}:${kode}`;
}

function sudahDilihat(cycleId, kode) {
    try {
        return window.localStorage.getItem(kunciDilihat(cycleId, kode)) === '1';
    } catch {
        // Penyimpanan diblokir: anggap sudah, supaya penilai tidak dibelokkan berulang kali.
        return true;
    }
}

function tandaiDilihat(cycleId, kode) {
    try {
        window.localStorage.setItem(kunciDilihat(cycleId, kode), '1');
    } catch {
        // Tidak bisa diingat; pratinjau akan tampil lagi pada pemuatan berikutnya.
    }
}

export function usePaWizardPenilaian({ cycleId = null, muatSaatPasang = true, kategoriAwal = null } = {}) {
    const wizard = ref(null);
    const memuat = ref(false);

    /* Siklus yang sedang dibuka. Kosong pada pemuatan pertama: server memilih yang masih
       menuntut tindakan, lalu pilihan itu dipegang supaya muat ulang tidak melompat siklus. */
    const siklusDipilih = ref(cycleId);
    const galat = ref(null);

    const kodeDilihat = ref(null);
    const tampilan = ref('lembar');

    const lembar = ref(null);
    const pratinjau = ref(null);
    const memuatIsi = ref(false);
    const galatIsi = ref(null);

    const menyimpanId = ref(0);
    const galatSimpan = ref(null);
    const kotor = ref(false);
    const mengirim = ref(false);
    const galatKirim = ref(null);

    const siklus = computed(() => wizard.value?.siklus ?? null);
    const langkah = computed(() => wizard.value?.langkah ?? []);
    const kosong = computed(() => wizard.value !== null && langkah.value.length === 0);
    const indeksDilihat = computed(() => langkah.value.findIndex((l) => l.kode_kategori === kodeDilihat.value));
    const langkahDilihat = computed(() => (indeksDilihat.value < 0 ? null : langkah.value[indeksDilihat.value]));
    const langkahBerikut = computed(() => {
        const berikut = indeksDilihat.value < 0 ? null : langkah.value[indeksDilihat.value + 1];
        return berikut && berikut.status !== 'TERKUNCI' ? berikut : null;
    });
    const langkahSebelum = computed(() => {
        const sebelum = indeksDilihat.value <= 0 ? null : langkah.value[indeksDilihat.value - 1];
        return sebelum && sebelum.status !== 'TERKUNCI' ? sebelum : null;
    });

    function metaGalat(e) {
        return {
            pesan: e?.message || 'Permintaan tidak bisa diselesaikan.',
            status: Number(e?.status || 0),
            kode: e?.error_code || '',
            requestId: e?.request_id || '',
        };
    }

    /* Kategori dari URL (`?kategori=SELF`) dipakai SEKALI, pada pemuatan pertama. Pemuatan
       ulang sesudahnya kembali mengikuti server, supaya transisi ke pratinjau tetap jalan. */
    let kategoriTertunda = kategoriAwal ? String(kategoriAwal).toUpperCase() : null;

    function arahkanKeServer() {
        const diminta = kategoriTertunda && langkah.value.find((l) => l.kode_kategori === kategoriTertunda);
        kategoriTertunda = null;

        if (diminta && diminta.status !== 'TERKUNCI') {
            kodeDilihat.value = diminta.kode_kategori;
            tampilan.value = diminta.status === 'TUNTAS' && bolehPratinjau(diminta) ? 'pratinjau' : 'lembar';
            return;
        }

        /* Kategori yang bisa dikalibrasi dan baru tuntas dibuka di pratinjaunya lebih dulu;
           penilai sendiri yang menekan Lanjut. Tidak setelah semuanya terkirim: tidak ada
           lagi yang bisa dikalibrasi. */
        const idSiklus = siklus.value?.cycle_id ?? cycleId;
        const belumDilihat = !wizard.value?.pengiriman?.sudah_terkirim_semua
            && langkah.value.find((l) => l.status === 'TUNTAS' && bolehPratinjau(l) && !sudahDilihat(idSiklus, l.kode_kategori));

        if (belumDilihat) {
            kodeDilihat.value = belumDilihat.kode_kategori;
            tampilan.value = 'pratinjau';
            return;
        }

        const aktif = wizard.value?.langkah_aktif ?? null;

        if (aktif === LANGKAH_RINGKASAN) {
            kodeDilihat.value = null;
            tampilan.value = 'ringkasan';
            return;
        }

        kodeDilihat.value = aktif;
        tampilan.value = 'lembar';
    }

    /*
     * Nomor urut permintaan isi. Membuka langkah lain sebelum isi langkah sebelumnya tiba
     * tidak boleh membuat jawaban yang LEBIH LAMBAT — milik langkah yang sudah ditinggalkan —
     * menimpa isi yang sedang dilihat.
     */
    let urutIsi = 0;

    /* `senyap`: penyegaran latar (tab kembali terlihat). Isi lama tetap tampil selagi data baru
       diambil, lalu diganti di tempat — tanpa skeleton. Skeleton membuat halaman memendek,
       gulungan melompat ke atas, dan matriks kalibrasi dipasang ulang (tab level & lipatan
       kembali ke awal); bagi penilai itu terasa seperti halaman me-refresh sendiri. */
    async function muatIsi({ senyap = false } = {}) {
        const nomor = ++urutIsi;

        if (tampilan.value === 'ringkasan' || !kodeDilihat.value) {
            lembar.value = null;
            pratinjau.value = null;
            return;
        }

        const adaIsi = tampilan.value === 'pratinjau' ? pratinjau.value !== null : lembar.value !== null;
        memuatIsi.value = !(senyap && adaIsi);
        galatIsi.value = null;
        const id = siklus.value?.cycle_id ?? siklusDipilih.value;
        const jenis = tampilan.value;
        const kode = kodeDilihat.value;

        try {
            const hasil =
                jenis === 'pratinjau'
                    ? await paApi.pratinjauLangkahSaya(kodeDilihat.value, id)
                    : await paApi.lembarLangkahSaya(kodeDilihat.value, id);

            if (nomor !== urutIsi) return;

            if (jenis === 'pratinjau') {
                pratinjau.value = hasil;

                // Hanya pratinjau kategori yang SUDAH tuntas yang dihitung "sudah dilihat".
                const l = langkah.value.find((x) => x.kode_kategori === kode);
                if (l?.status === 'TUNTAS') tandaiDilihat(id, kode);
            } else {
                lembar.value = hasil;
            }
        } catch (e) {
            if (nomor === urutIsi) galatIsi.value = metaGalat(e);
        } finally {
            if (nomor === urutIsi) memuatIsi.value = false;
        }
    }

    async function muat({ pertahankanPosisi = false } = {}) {
        memuat.value = true;
        galat.value = null;

        try {
            const statusSebelum = langkahDilihat.value?.status ?? null;
            wizard.value = await paApi.wizardPenilaianSaya(siklusDipilih.value);
            siklusDipilih.value ??= wizard.value?.siklus?.cycle_id ?? null;

            const posisiHilang = tampilan.value !== 'ringkasan' && !langkahDilihat.value;

            if (!pertahankanPosisi || posisiHilang) {
                arahkanKeServer();
            } else if (
                tampilan.value === 'lembar' &&
                statusSebelum !== 'TUNTAS' &&
                langkahDilihat.value?.status === 'TUNTAS' &&
                bolehPratinjau(langkahDilihat.value)
            ) {
                tampilan.value = 'pratinjau';
            }

            await muatIsi({ senyap: pertahankanPosisi });
        } catch (e) {
            galat.value = metaGalat(e);
        } finally {
            memuat.value = false;
        }
    }

    function buka(kode, jenis = null, { paksa = false } = {}) {
        if (kotor.value && !paksa) return false;

        const l = langkah.value.find((x) => x.kode_kategori === kode);
        if (!l || l.status === 'TERKUNCI') return true;

        kotor.value = false;
        kodeDilihat.value = kode;
        const pratinjauBoleh = bolehPratinjau(l);
        tampilan.value = jenis === 'pratinjau' && !pratinjauBoleh
            ? 'lembar'
            : jenis ?? (l.status === 'TUNTAS' && pratinjauBoleh ? 'pratinjau' : 'lembar');
        muatIsi();

        return true;
    }

    function gantiTampilan(jenis, { paksa = false } = {}) {
        if (kotor.value && !paksa) return false;
        if (tampilan.value === jenis) return true;
        if (jenis === 'pratinjau' && !bolehPratinjau(langkahDilihat.value)) return true;

        kotor.value = false;
        tampilan.value = jenis;
        muatIsi();

        return true;
    }

    function bukaRingkasan({ paksa = false } = {}) {
        if (kotor.value && !paksa) return false;
        const bolehBuka =
            wizard.value?.langkah_aktif === LANGKAH_RINGKASAN ||
            Boolean(wizard.value?.boleh_kirim) ||
            Boolean(wizard.value?.pengiriman?.sudah_terkirim_semua);
        if (!bolehBuka) return true;

        kotor.value = false;
        kodeDilihat.value = null;
        tampilan.value = 'ringkasan';
        lembar.value = null;
        pratinjau.value = null;

        return true;
    }

    /** Pindah siklus. Seperti berpindah langkah: draf kalibrasi yang belum disimpan ditanyakan dulu. */
    function gantiSiklus(id, { paksa = false } = {}) {
        if (kotor.value && !paksa) return false;
        if (!id || Number(id) === Number(siklusDipilih.value)) return true;

        kotor.value = false;
        siklusDipilih.value = Number(id);
        lembar.value = null;
        pratinjau.value = null;
        muat();

        return true;
    }

    function lanjut({ paksa = false } = {}) {
        if (kotor.value && !paksa) return false;
        if (langkahBerikut.value) return buka(langkahBerikut.value.kode_kategori, null, { paksa: true });

        return bukaRingkasan({ paksa: true });
    }

    async function simpanKalibrasi({ assignmentId, perubahan = [], perubahanJawaban = [], alasan }) {
        menyimpanId.value = assignmentId;
        galatSimpan.value = null;

        try {
            const r = await paApi.simpanKalibrasiPenilai({ assignmentId, perubahan, perubahanJawaban, alasan });

            // Ditandai bersih SEBELUM pemanggil menampilkan pesan sukses.
            kotor.value = false;
            pratinjau.value = r?.pratinjau ?? pratinjau.value;

            try {
                wizard.value = await paApi.wizardPenilaianSaya(siklusDipilih.value);
            } catch {
                // Hitungan langkah boleh basi sebentar; kalibrasinya sudah tersimpan.
            }

            return true;
        } catch (e) {
            galatSimpan.value = metaGalat(e);
            return false;
        } finally {
            menyimpanId.value = 0;
        }
    }

    /*
     * Menyimpan kalibrasi BANYAK peserta sekaligus dengan satu alasan. API-nya tetap per
     * penugasan, jadi dijalankan berurutan: yang sudah berhasil tetap tersimpan, dan begitu satu
     * gagal prosesnya berhenti — sisanya tetap draf supaya penilai bisa memperbaikinya.
     * `saatTersimpan(assignmentId)` dipanggil tiap kali satu orang berhasil.
     */
    const progresSimpan = ref(null);

    async function simpanKalibrasiBanyak(daftar, { saatTersimpan = () => {} } = {}) {
        galatSimpan.value = null;
        let berhasil = 0;

        try {
            for (const [i, item] of daftar.entries()) {
                progresSimpan.value = { ke: i + 1, total: daftar.length };
                menyimpanId.value = item.assignmentId;

                try {
                    const r = await paApi.simpanKalibrasiPenilai({
                        assignmentId: item.assignmentId,
                        perubahan: item.perubahan ?? [],
                        perubahanJawaban: item.perubahanJawaban ?? [],
                        alasan: item.alasan,
                    });
                    pratinjau.value = r?.pratinjau ?? pratinjau.value;
                    berhasil++;
                    saatTersimpan(item.assignmentId);
                } catch (e) {
                    const g = metaGalat(e);
                    galatSimpan.value = { ...g, pesan: `Gagal menyimpan ${item.nama ?? `penugasan ${item.assignmentId}`}: ${g.pesan}` };
                    return { berhasil, gagal: item.assignmentId };
                }
            }

            kotor.value = false;
            return { berhasil, gagal: null };
        } finally {
            menyimpanId.value = 0;
            progresSimpan.value = null;
            try {
                wizard.value = await paApi.wizardPenilaianSaya(siklusDipilih.value);
            } catch {
                // Hitungan langkah boleh basi sebentar; kalibrasinya sudah tersimpan.
            }
        }
    }

    async function kirim() {
        if (!siklus.value || mengirim.value) return false;

        mengirim.value = true;
        galatKirim.value = null;

        try {
            await paApi.kirimSemuaPenilaianSaya(siklus.value.cycle_id);
            await muat();
            return true;
        } catch (e) {
            galatKirim.value = metaGalat(e);
            return false;
        } finally {
            mengirim.value = false;
        }
    }

    /*
     * Tidak dimuat ulang selagi ada perubahan belum tersimpan: pratinjau yang ditimpa dari
     * server akan menghapus angka yang sedang diketik penilai tanpa pemberitahuan.
     */
    function saatTerlihat() {
        if (document.visibilityState === 'visible' && !memuat.value && !kotor.value) {
            muat({ pertahankanPosisi: true });
        }
    }

    onMounted(() => {
        document.addEventListener('visibilitychange', saatTerlihat);
        if (muatSaatPasang) muat();
    });

    onBeforeUnmount(() => document.removeEventListener('visibilitychange', saatTerlihat));

    return {
        wizard,
        siklus,
        langkah,
        langkahDilihat,
        langkahBerikut,
        langkahSebelum,
        kodeDilihat,
        tampilan,
        kosong,
        memuat,
        galat,
        lembar,
        pratinjau,
        memuatIsi,
        galatIsi,
        menyimpanId,
        galatSimpan,
        kotor,
        mengirim,
        galatKirim,
        muat,
        muatIsi,
        buka,
        gantiTampilan,
        bukaRingkasan,
        gantiSiklus,
        lanjut,
        simpanKalibrasi,
        simpanKalibrasiBanyak,
        progresSimpan,
        kirim,
    };
}

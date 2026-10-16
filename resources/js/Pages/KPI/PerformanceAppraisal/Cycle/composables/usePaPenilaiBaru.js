import { ref } from 'vue';
import paApi from '../../api/paApi.js';
import { hcisMessage } from '@/components/ui';

/*
 | Mencari dan menambahkan penilai baru — DIPAKAI BERSAMA oleh Tahap 5 dan Daftar Siklus.
 |
 | Alasannya sama dengan `usePaBobotEditor`: `PaAssignmentDialog` dibuka dari dua tempat, dan
 | kabelnya semula hanya ada di satu. Dari Daftar Siklus kotak pencariannya tampil tetapi
 | `@search-rater` dan `@tambah-penilai` tak ada yang mendengarkan — mengetik nama tidak
 | memunculkan siapa pun, dan menekan "tambahkan" tidak melakukan apa pun.
 |
 | @param {object} opsi
 * @param {() => number|undefined} opsi.participantId peserta yang sedang dibuka
 * @param {() => Promise<void>}    opsi.segarkan      memuat ulang usulan (dan daftar luarnya)
 * @param {(pesan: string) => void} opsi.laporkan     menampilkan galat ke admin
 * @param {{ kosongkan: () => void }} [opsi.bobot]    editor bobot, bila layarnya punya
 */
export function usePaPenilaiBaru({ participantId, segarkan, laporkan, bobot = null }) {
    const hasilCariPenilai = ref({});
    const sedangCariPenilai = ref({});

    /* Sedang menambahkan, per kategori. Tanpa ini tombol yang diklik tidak memberi tanda apa pun
       selama permintaannya berjalan — dan pada DB dev satu permintaan bisa lebih dari satu detik,
       cukup lama untuk membuat admin menyimpulkan kliknya tidak masuk lalu menekannya
       berkali-kali. */
    const menambahPenilai = ref({});

    /*
     | Galat penambahan, PER KATEGORI.
     |
     | Sebelumnya diteruskan ke pesan halaman lewat `laporkan()` — dan pesan halaman itu berada
     | di KAKI Langkah 5, jauh di bawah lipatan dan DI BELAKANG modal. Admin menekan "tambahkan"
     | di dalam modal, penolakannya muncul di tempat yang tidak bisa ia lihat, dan yang tampak
     | adalah tombol yang tidak melakukan apa-apa.
     |
     | Pesan penolakan harus mendarat di tempat tindakannya terjadi. Sebabnya sering spesifik dan
     | dapat ditindaklanjuti — "siklus berstatus CLOSED", "sudah tercatat sebagai Atasan",
     | "karyawan sudah tidak aktif" — dan membuangnya ke tempat tak terlihat sama saja dengan
     | tidak punya sebab sama sekali.
     */
    const galatTambah = ref({});

    /* Kabar yang BUKAN galat: penambahan berhasil, dan kalau masa berlaku relasinya dimundurkan
       supaya terlihat oleh siklus ini, itu harus dikatakan — ia mengubah data yang dipakai
       siklus lain juga. */
    const kabarTambah = ref({});

    let jeda = null;

    function cariCalonPenilai({ kodeKategori, kata }) {
        /* Diberi jeda singkat 120ms untuk pengalaman autocomplete instan dan responsif */
        if (jeda) {
            clearTimeout(jeda);
        }

        const query = (kata ?? '').trim();

        if (! query) {
            hasilCariPenilai.value = { ...hasilCariPenilai.value, [kodeKategori]: [] };
            sedangCariPenilai.value = { ...sedangCariPenilai.value, [kodeKategori]: false };

            return;
        }

        sedangCariPenilai.value = { ...sedangCariPenilai.value, [kodeKategori]: true };

        jeda = setTimeout(async () => {
            try {
                /* Endpoint yang tahu PESERTANYA, memfilter relasi organisasi & status aktif */
                const res = await paApi.cariCalonPenilai(participantId(), {
                    kode_kategori: kodeKategori,
                    q: query,
                    limit: 20,
                });

                const d = res?.result ?? res?.data ?? res;

                hasilCariPenilai.value = { ...hasilCariPenilai.value, [kodeKategori]: d?.baris ?? [] };
            } catch {
                hasilCariPenilai.value = { ...hasilCariPenilai.value, [kodeKategori]: [] };
            } finally {
                sedangCariPenilai.value = { ...sedangCariPenilai.value, [kodeKategori]: false };
            }
        }, 120);
    }

    async function tambahPenilaiBaru({ kodeKategori, kodePerusahaan, kodeKaryawan, nama, namaKategori = '' }) {
        const id = participantId();

        if (! id) {
            return;
        }

        menambahPenilai.value = { ...menambahPenilai.value, [kodeKategori]: true };
        galatTambah.value = { ...galatTambah.value, [kodeKategori]: '' };
        kabarTambah.value = { ...kabarTambah.value, [kodeKategori]: '' };

        try {
            const hasil = await paApi.tambahPenilaiDenganRelasi(id, {
                kode_kategori: kodeKategori,
                kode_perusahaan: kodePerusahaan,
                kode_karyawan: kodeKaryawan,
            });

            const pesanDasar = hasil?.relasi_dimundurkan
                ? `${nama} ditambahkan. Masa berlaku relasinya dimundurkan ke `
                  + `${hasil.tanggal_acuan} agar terbaca siklus ini — relasi itu kini juga `
                  + 'terlihat oleh siklus lain yang acuannya lebih awal.'
                : `${nama} ditambahkan sebagai penilai.`;
            /* Sesudah dikunci server langsung menjadwalkannya ke HC Learn — disebut di sini. */
            const pesan = hasil?.jadwal_hclearn?.catatan ? `${pesanDasar} ${hasil.jadwal_hclearn.catatan}` : pesanDasar;

            kabarTambah.value = {
                ...kabarTambah.value,
                [kodeKategori]: pesan,
            };

            const labelKat = namaKategori || (kodeKategori ? `Kategori ${kodeKategori}` : 'Penilai');
            hcisMessage.success(pesan, `Penilai ${labelKat} Ditambahkan`);

            /* Bobot yang sedang disunting DIBUANG: menambah penilai membuat server membagi rata
               ulang kategorinya, jadi angka yang tersimpan di layar sudah tidak berlaku. */
            if (bobot) {
                bobot.kosongkan();
            }

            hasilCariPenilai.value = { ...hasilCariPenilai.value, [kodeKategori]: [] };

            await segarkan();
        } catch (e) {
            /* Pesan servernya diteruskan apa adanya: ia sudah menyebut sebabnya — arah yang
               bertentangan, orang yang sama dengan pesertanya, atau karyawan nonaktif.
               Menggantinya dengan "gagal menambah" membuang satu-satunya keterangan yang
               berguna. */
            const teks = e?.message || `Gagal menambahkan ${nama} sebagai penilai.`;

            /* DI MODAL, tempat tombolnya ditekan. `laporkan()` tetap dipanggil supaya jejaknya
               ada di halaman, tetapi yang menentukan adalah yang terlihat admin sekarang. */
            galatTambah.value = { ...galatTambah.value, [kodeKategori]: teks };
            const labelKat = namaKategori || (kodeKategori ? `Kategori ${kodeKategori}` : 'Penilai');
            hcisMessage.error(teks, `Penilai ${labelKat} Gagal Ditambahkan`);
            laporkan(teks);
        } finally {
            menambahPenilai.value = { ...menambahPenilai.value, [kodeKategori]: false };
        }
    }

    function kosongkanHasil() {
        if (jeda) {
            clearTimeout(jeda);
            jeda = null;
        }

        hasilCariPenilai.value = {};
        sedangCariPenilai.value = {};
        menambahPenilai.value = {};
        galatTambah.value = {};
        kabarTambah.value = {};
    }

    return {
        hasilCariPenilai,
        sedangCariPenilai,
        menambahPenilai,
        galatTambah,
        kabarTambah,
        cariCalonPenilai,
        tambahPenilaiBaru,
        kosongkanHasil,
    };
}

export default usePaPenilaiBaru;

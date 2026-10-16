import { reactive } from 'vue';
import axios from 'axios';

const API = '/api/v1/sop/admin/dokumen';

/**
 * Keadaan alur versi — SATU-SATUNYA pemuat.
 *
 * Langkah yang dibuka datang dari server (`Langkah_Aktif`). Layar tak pernah
 * menghitungnya sendiri, dan itu bukan soal kerapian melainkan koreksi atas
 * bug nyata: versi sebelumnya memilih "langkah pertama yang isinya kurang",
 * dan karena Pengajuan selalu dilaporkan lengkap, layar melompat ke Review
 * setiap kali admin menyimpan. Kerja tersimpan, tapi tak pernah terlihat.
 *
 * `pertahankanDibuka` dipakai sesudah menyimpan TANPA berpindah tahap: yang
 * disegarkan datanya, bukan tempat berdiri pemakainya. Sesudah benar-benar
 * pindah tahap, ia tidak dipakai — perpindahan memang seharusnya memindahkan.
 */
export function createAlur(api = axios) {
    const s = reactive({
        memuat: false,
        galat: '',
        untuk: null,
        langkah: [],
        langkahAktif: 1,
        dibuka: 1,
        tahap: null,
        aksiUtama: null,
        /* Aksi yang memindahkan LAYAR, bukan tahap — dipakai sebelum
           penerbitan, supaya Pemberkasan mengantar ke Tinjauan Akhir alih-alih
           langsung menerbitkan. Null di mana pun selain di sana. */
        aksiAntar: null,
        aksiLain: [],

        /** Langkah yang sedang terbuka, lengkap dengan kelompok & keadaannya. */
        get langkahDibuka() {
            return s.langkah.find((l) => l.Nomor === s.dibuka) || null;
        },

        /** Kelompok field yang harus digambar. Default PENGAJUAN saat belum ada data. */
        get kelompok() {
            return s.langkahDibuka?.Kelompok || 'PENGAJUAN';
        },

        /**
         * @param {object}  opsi
         * @param {boolean} opsi.pertahankanDibuka  tetap di langkah yang sedang dibuka
         * @param {?number} opsi.dibukaAwal         langkah yang diminta pemanggil (dari URL)
         */
        async muat(idVersi, { pertahankanDibuka = false, dibukaAwal = null } = {}) {
            if (!idVersi) return;

            s.memuat = true;
            s.galat = '';
            const sebelumnya = s.dibuka;
            try {
                const { data } = await api.get(`${API}/versi/${idVersi}/langkah`);
                const r = data?.result || {};
                s.langkah = r.Langkah || [];
                s.langkahAktif = r.Langkah_Aktif || 1;
                s.tahap = r.Tahap || null;
                s.aksiUtama = r.Aksi_Utama || null;
                s.aksiAntar = r.Aksi_Antar || null;
                s.aksiLain = r.Aksi_Lain || [];
                s.untuk = idVersi;

                /* Yang dipertahankan hanya langkah yang MASIH boleh dibuka.
                   Sesudah versi mundur tahap, langkah tempat admin berdiri bisa
                   berubah jadi terkunci — memaksanya bertahan di sana berarti
                   menyodorkan formulir yang tak bisa disimpan. */
                const boleh = (n) => n !== null
                    && s.langkah.some((l) => l.Nomor === n && !l.Terkunci);

                /* LANGKAH_AKTIF ADALAH TAHAP, BUKAN TEMPAT BERDIRI.
                   Tinjauan Akhir dicapai lewat Aksi_Antar — perpindahan LAYAR
                   yang sengaja tidak menggeser tahap, sebab menerbitkan tak
                   bisa ditarik dan rekapnya harus dibaca lebih dulu. Akibatnya
                   tahap tetap di Pemberkasan, dan memuat ulang selalu memulangkan
                   admin ke sana meski ia sudah berdiri di rekap.

                   Karena itu tempat berdiri boleh datang dari PEMANGGIL (yang
                   membacanya dari URL). Ia tetap tunduk pada Terkunci: yang
                   diminta hanyalah tempat berdiri, bukan izin. */
                s.dibuka = pertahankanDibuka && boleh(sebelumnya)
                    ? sebelumnya
                    : (boleh(dibukaAwal) ? dibukaAwal : s.langkahAktif);
            } catch (e) {
                s.galat = e?.response?.data?.message || 'Gagal memuat perjalanan versi.';
            } finally {
                s.memuat = false;
            }
        },

        /**
         * Rangka empat langkah untuk versi yang BELUM punya id.
         *
         * Dibaca dari master lewat endpoint template, bukan dihafal layar —
         * empat langkah yang ditulis di dua tempat adalah sumber kebenaran
         * kedua yang seluruh rancangan ini ada untuk menghindarinya.
         */
        async muatTemplate() {
            s.memuat = true;
            s.galat = '';
            try {
                const { data } = await api.get(`${API}/langkah`);
                const r = data?.result || {};
                s.langkah = r.Langkah || [];
                s.langkahAktif = r.Langkah_Aktif || 1;
                s.dibuka = s.langkahAktif;
                s.tahap = null;
                /* Aksinya datang dari server, sama seperti pada versi yang sudah
                   ada. Dulu baris ini memaku "Simpan Draft Versi", dan itu
                   memaksa admin melewati satu klik yang tak dimintanya: satu
                   untuk menyimpan, satu lagi untuk lanjut — padahal ia masuk ke
                   wizard ini memang untuk mendefinisikan versi dan
                   meneruskannya. Label yang dirakit layar juga sumber kebenaran
                   kedua yang seluruh berkas ini ada untuk menghindarinya. */
                s.aksiUtama = r.Aksi_Utama || null;
                s.aksiAntar = r.Aksi_Antar || null;
                s.aksiLain = r.Aksi_Lain || [];
                s.untuk = null;
            } catch (e) {
                s.galat = e?.response?.data?.message || 'Gagal menyiapkan perjalanan versi.';
            } finally {
                s.memuat = false;
            }
        },

        /**
         * Membuka sebuah langkah. Yang terkunci ditolak.
         *
         * Penjaga ini ada DI SINI, bukan hanya sebagai `:disabled` di tombol:
         * aturan yang cuma dipasang di satu dari dua pintu setara adalah bug
         * yang sudah berulang di modul ini.
         */
        buka(nomor) {
            const l = s.langkah.find((x) => x.Nomor === nomor);
            if (!l || l.Terkunci) return false;
            s.dibuka = nomor;

            return true;
        },
    });

    return s;
}

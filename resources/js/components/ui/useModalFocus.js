import { onUnmounted } from 'vue';

/**
 * Fokus untuk lapisan modal (dialog, laci).
 *
 * Dua hal yang sering dianggap sudah beres padahal belum:
 *
 *   1. Fokus dipindahkan MASUK tapi tidak pernah dikembalikan. Menutup dialog
 *      melempar pengguna keyboard ke <body>, dan penelusuran Tab dimulai lagi
 *      dari puncak halaman. Di tabel panjang, baris yang tadi ia buka hilang.
 *
 *   2. Fokus tidak ditahan. Elemen di dialog memang dapat fokus lebih dulu,
 *      tapi Tab terus akan membawanya keluar ke halaman DI BALIK tirai —
 *      tempat yang tidak bisa ia lihat dan tidak seharusnya bisa ia sentuh
 *      selagi dialognya modal. Ia mengisi formulir yang tak terlihat.
 *
 * Keduanya diselesaikan di sini supaya tidak lahir dua salinan yang perlahan
 * berbeda di HcisDialog dan HcisDrawer.
 */

/*
 * Sengaja tidak memakai [tabindex]:not([tabindex="-1"]) yang longgar: elemen
 * ber-tabindex negatif memang dimaksudkan untuk dilewati Tab, dan memasukkannya
 * membuat penelusuran berhenti di tempat yang tak terduga.
 */
const DAPAT_FOKUS = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
    '[contenteditable="true"]',
].join(',');

/*
 * Penyaringnya sengaja hanya membuang yang TERBUKTI tersembunyi, bukan
 * menyimpan yang terbukti terlihat.
 *
 * Bedanya menentukan arah kegagalan. Versi pertama berkas ini memakai
 * offsetWidth/getClientRects — dan di lingkungan yang tidak menghitung tata
 * letak, keduanya nol untuk SEMUA elemen. Hasilnya daftarnya kosong, jebakannya
 * menganggap tidak ada apa pun yang bisa difokus, dan Tab berhenti ditahan
 * sama sekali. Itu kegagalan senyap yang persis membatalkan gunanya berkas ini.
 *
 * Dengan arah terbalik, kesalahan terburuknya cuma menyertakan satu elemen tak
 * terlihat dalam lingkaran Tab — mengganggu, tapi jebakannya tetap menjebak.
 */
function elemenDapatFokus(wadah) {
    if (!wadah) return [];

    return [...wadah.querySelectorAll(DAPAT_FOKUS)].filter((el) => {
        if (el.hidden) return false;
        if (el.closest('[aria-hidden="true"]')) return false;

        const gaya = typeof getComputedStyle === 'function' ? getComputedStyle(el) : null;
        if (gaya && (gaya.display === 'none' || gaya.visibility === 'hidden')) return false;

        return true;
    });
}

export function useModalFocus() {
    let pemicu = null;
    let wadah = null;

    function padaKeydown(e) {
        if (e.key !== 'Tab' || !wadah) return;

        const bisa = elemenDapatFokus(wadah);

        // Tidak ada apa pun yang bisa difokus di dalam: tahan fokus di wadahnya
        // sendiri, jangan biarkan Tab melarikannya ke halaman di belakang.
        if (bisa.length === 0) {
            e.preventDefault();
            wadah.focus();
            return;
        }

        const pertama = bisa[0];
        const terakhir = bisa[bisa.length - 1];
        const aktif = document.activeElement;

        if (e.shiftKey && (aktif === pertama || aktif === wadah)) {
            e.preventDefault();
            terakhir.focus();
            return;
        }

        if (!e.shiftKey && aktif === terakhir) {
            e.preventDefault();
            pertama.focus();
            return;
        }

        // Fokus sedang di luar wadah — bisa terjadi kalau elemen yang
        // memegangnya baru saja terhapus. Tarik kembali ke dalam.
        // Pengecualian: jangan tarik jika fokus berada di lapisan popover/popper/dropdown
        // yang sengaja di-teleport ke <body> (mis. el-select, HcisSelect, el-popper, dsb).
        const isFloatingLayer = aktif && (
            aktif.closest?.('.hcis-select__popover') ||
            aktif.closest?.('.hcis-tree-select__popover') ||
            aktif.closest?.('.el-popper') ||
            aktif.closest?.('.el-select-dropdown') ||
            aktif.closest?.('.el-picker__popper') ||
            aktif.closest?.('.el-dropdown__popper') ||
            aktif.closest?.('.el-autocomplete-suggestion') ||
            aktif.closest?.('[role="listbox"]') ||
            aktif.closest?.('.hcis-drawer')
        );

        if (aktif && !wadah.contains(aktif) && !isFloatingLayer) {
            e.preventDefault();
            pertama.focus();
        }
    }

    /**
     * Dipanggil saat lapisan dibuka.
     * Pemicu diingat SEBELUM fokus dipindahkan — setelahnya sudah terlambat.
     */
    function tangkap() {
        pemicu = typeof document === 'undefined' ? null : document.activeElement;
    }

    /** Dipanggil setelah wadahnya benar-benar ada di DOM (nextTick). */
    function tahan(el) {
        if (!el) return;
        wadah = el;
        el.focus();
        document.addEventListener('keydown', padaKeydown, true);
    }

    /** Dipanggil saat lapisan ditutup atau dilepas. */
    function lepas() {
        if (wadah) {
            document.removeEventListener('keydown', padaKeydown, true);
            wadah = null;
        }

        const sasaran = pemicu;
        pemicu = null;

        // Simpulnya bisa sudah lepas dari dokumen — mis. baris tabel yang
        // memicu dialog lalu terhapus oleh aksi dialog itu sendiri.
        if (sasaran && typeof sasaran.focus === 'function' && sasaran.isConnected) {
            sasaran.focus();
        }
    }

    onUnmounted(lepas);

    return { tangkap, tahan, lepas };
}

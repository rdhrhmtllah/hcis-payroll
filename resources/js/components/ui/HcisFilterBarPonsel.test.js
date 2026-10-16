/*
 * Laci saringan di ponsel.
 *
 * Yang dijaga di sini adalah PENUNDAAN. Di layar sempit, laci menutupi
 * hasilnya — jadi menyaring seketika berarti daftar berubah berkali-kali di
 * balik tirai yang tidak bisa dilihat, dan tiap perubahan memicu penyaringan
 * penuh tanpa satu pun yang berguna bagi pengguna.
 *
 * Konsekuensinya: menutup laci tanpa menekan "Terapkan" harus MEMBATALKAN,
 * bukan menerapkan diam-diam. Itu yang paling mudah salah ditulis.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisFilterBar from './HcisFilterBar.vue';

const FILTERS = [
    { key: 'status', label: 'Status', type: 'select', options: [{ value: 'a', label: 'A' }] },
    { key: 'divisi', label: 'Divisi', type: 'input' },
];

/** Memaksa komponen menganggap dirinya di layar sempit. */
function lebarLayar(px) {
    Object.defineProperty(window, 'innerWidth', { writable: true, configurable: true, value: px });
    window.dispatchEvent(new Event('resize'));
}

let pemasangan = [];
const pasang = (props = {}) => {
    const w = mount(HcisFilterBar, {
        props: { filters: FILTERS, filterValues: { status: '', divisi: '' }, ...props },
        attachTo: document.body,
    });
    pemasangan.push(w);
    return w;
};

beforeEach(() => lebarLayar(420));

afterEach(() => {
    pemasangan.forEach((w) => w.unmount());
    pemasangan = [];
    lebarLayar(1280);
    document.body.innerHTML = '';
});

describe('HcisFilterBar — mode ponsel', () => {
    it('menampilkan tombol apung, bukan baris saringan', async () => {
        const w = pasang({ collapsible: false });
        await w.vm.$nextTick();

        expect(document.querySelector('.hcis-filter-fab'), 'tombol apung tidak muncul').toBeTruthy();
        expect(w.find('.hcis-filter-grid').exists(), 'baris saringan masih memakan layar').toBe(false);
    });

    it('tombol apung membawa jumlah saringan aktif', async () => {
        const w = pasang({ filterValues: { status: 'a', divisi: 'HC' } });
        await w.vm.$nextTick();

        expect(document.querySelector('.hcis-filter-fab__badge')?.textContent.trim()).toBe('2');
    });

    it('mengubah saringan di laci TIDAK langsung memancarkan apa pun', async () => {
        const w = pasang();
        await w.vm.$nextTick();

        w.vm.draf.status = 'a';
        await w.vm.$nextTick();

        expect(
            w.emitted('filter-change'),
            'saringan diterapkan sebelum Terapkan ditekan',
        ).toBeFalsy();
    });

    it('Terapkan memancarkan hanya kunci yang BERUBAH', async () => {
        const w = pasang({ filterValues: { status: '', divisi: 'HC' } });
        await w.vm.$nextTick();

        w.vm.draf = { status: 'a', divisi: 'HC' };   // hanya status yang berubah
        w.vm.terapkanDraf();

        const keluar = w.emitted('filter-change');
        expect(keluar).toHaveLength(1);
        expect(keluar[0][0].key).toBe('status');
        expect(keluar[0][0].value).toBe('a');
    });

    it('menutup laci tanpa Terapkan membatalkan perubahan', async () => {
        const w = pasang({ filterValues: { status: '', divisi: '' } });
        await w.vm.$nextTick();

        w.vm.draf.status = 'a';
        w.vm.batalkanDraf();

        expect(w.emitted('filter-change'), 'perubahan diterapkan diam-diam').toBeFalsy();
        expect(w.vm.draf.status, 'draf tidak dikembalikan ke nilai asli').toBe('');
    });

    it('membuka laci selalu mulai dari nilai yang sedang berlaku', async () => {
        // Kalau draf tidak disegarkan saat dibuka, perubahan yang tadi
        // dibatalkan akan muncul lagi pada pembukaan berikutnya.
        const w = pasang({ filterValues: { status: 'a', divisi: '' } });
        await w.vm.$nextTick();

        w.vm.draf.status = 'zzz';
        w.vm.bukaLaci();

        expect(w.vm.draf.status).toBe('a');
    });

    it('di layar lebar tidak ada tombol apung', async () => {
        lebarLayar(1280);
        const w = pasang({ collapsible: false });
        await w.vm.$nextTick();

        expect(document.querySelector('.hcis-filter-fab')).toBeNull();
        expect(w.find('.hcis-filter-grid').exists()).toBe(true);
    });
});

const CHIPS = [
    { value: 'semua', label: 'Semua' },
    { value: 'aktif', label: 'Aktif', badge: 7 },
];

describe('HcisFilterBar — pil & tampilan ikut ke laci', () => {
    it('pil dan pengalih tampilan hilang dari baris utama di ponsel', async () => {
        const w = pasang({ quickChips: CHIPS, showViewToggle: true });
        await w.vm.$nextTick();

        expect(w.find('.hcis-filter-chips-wrapper').exists(), 'pil masih memakan tinggi layar').toBe(false);
        expect(w.find('.hcis-filter-view-group').exists()).toBe(false);

        // Isi laci baru dirender saat ia dibuka, dan di-teleport ke <body>.
        w.vm.bukaLaci();
        await w.vm.$nextTick();

        expect(document.querySelector('.hcis-filter-sheet__chips'), 'pil tidak muncul di laci').toBeTruthy();
        expect(document.querySelector('.hcis-filter-sheet__tampilan')).toBeTruthy();
    });

    it('laci tetap terbuka meski halaman hanya punya pil', async () => {
        // Tanpa gerbang yang benar, `filters` kosong membuat tombol apungnya
        // tidak dirender sama sekali — dan pilnya hilang total di ponsel.
        const w = pasang({ filters: [], quickChips: CHIPS });
        await w.vm.$nextTick();

        expect(document.querySelector('.hcis-filter-fab')).toBeTruthy();
    });

    it('memilih pil di laci tidak langsung memancarkan apa pun', async () => {
        const w = pasang({ quickChips: CHIPS, showViewToggle: true });
        await w.vm.$nextTick();

        w.vm.drafChip = 'aktif';
        w.vm.drafView = 'grid';
        await w.vm.$nextTick();

        expect(w.emitted('chip-click'), 'pil diterapkan sebelum Terapkan').toBeFalsy();
        expect(w.emitted('update:viewMode'), 'tampilan diganti sebelum Terapkan').toBeFalsy();
    });

    it('Terapkan memancarkan chip-click dengan objek pil, bukan nilainya', async () => {
        const w = pasang({ quickChips: CHIPS, activeChipValue: 'semua' });
        await w.vm.$nextTick();

        w.vm.drafChip = 'aktif';
        w.vm.terapkanDraf();

        // Kontraknya harus sama persis dengan klik pil di layar lebar, supaya
        // pemanggil tidak perlu tahu dari mana pilihannya datang.
        expect(w.emitted('chip-click').at(-1)[0]).toEqual(CHIPS[1]);
    });

    it('Terapkan diam kalau pil dan tampilan tidak berubah', async () => {
        const w = pasang({ quickChips: CHIPS, activeChipValue: 'aktif', showViewToggle: true, viewMode: 'grid' });
        await w.vm.$nextTick();

        w.vm.bukaLaci();
        w.vm.terapkanDraf();

        expect(w.emitted('chip-click')).toBeFalsy();
        expect(w.emitted('update:viewMode')).toBeFalsy();
    });

    it('menutup laci membatalkan pilihan pil dan tampilan', async () => {
        const w = pasang({ quickChips: CHIPS, activeChipValue: 'semua', showViewToggle: true, viewMode: 'table' });
        await w.vm.$nextTick();

        w.vm.drafChip = 'aktif';
        w.vm.drafView = 'grid';
        w.vm.batalkanDraf();

        expect(w.emitted('chip-click')).toBeFalsy();
        expect(w.vm.drafChip).toBe('semua');
        expect(w.vm.drafView).toBe('table');
    });

    it('lencana tombol apung menghitung pil aktif juga', async () => {
        const w = pasang({
            filterValues: { status: 'a', divisi: '' },
            quickChips: CHIPS,
            activeChipValue: 'aktif',
        });
        await w.vm.$nextTick();

        expect(document.querySelector('.hcis-filter-fab__badge')?.textContent.trim()).toBe('2');
    });

    it('slot chips TIDAK dipindah ke laci', async () => {
        /*
         * Isi slot milik pemanggil dan memancarkan langsung ke induknya, jadi
         * ia tidak bisa ditunda. Memindahkannya ke laci membuat "Batal"
         * berbohong — perubahannya sudah terjadi sebelum tombolnya ditekan.
         */
        const w = mount(HcisFilterBar, {
            props: { filters: FILTERS, filterValues: {} },
            slots: { chips: '<div class="pil-buatan-sendiri" />' },
            attachTo: document.body,
        });
        pemasangan.push(w);
        await w.vm.$nextTick();

        expect(w.find('.pil-buatan-sendiri').exists()).toBe(true);
    });
});

describe('HcisFilterBar — layar lebar tidak terpengaruh', () => {
    it('pil dan pengalih tampilan tetap di baris utama', async () => {
        lebarLayar(1280);
        const w = pasang({ collapsible: false, quickChips: CHIPS, showViewToggle: true });
        await w.vm.$nextTick();

        expect(document.querySelector('.hcis-filter-fab')).toBeNull();
        expect(w.find('.hcis-filter-grid').exists()).toBe(true);
        expect(w.find('.hcis-filter-chips-wrapper').exists()).toBe(true);
        expect(w.find('.hcis-filter-view-group').exists()).toBe(true);
    });
});

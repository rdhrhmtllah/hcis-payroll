/*
 * Lima komponen yang sebelumnya tidak pernah di-mount satu kali pun oleh tes
 * mana pun: HcisCollapse, HcisCollapseItem, HcisCol, HcisSelectOption, dan
 * HcisSplitterPane.
 *
 * Empat di antaranya berkomunikasi dengan induknya lewat provide/inject —
 * bentuk yang paling sering diam-diam patah, karena memutus kontraknya tidak
 * menghasilkan galat apa pun: anaknya cuma berhenti tahu keadaan dirinya dan
 * tampil "selalu tertutup" atau "tidak pernah terpilih". Justru itu yang perlu
 * dikunci tes, bukan rupanya.
 */
import { describe, expect, it } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { mount } from '@vue/test-utils';

import HcisCol from './HcisCol.vue';
import HcisCollapse from './HcisCollapse.vue';
import HcisCollapseItem from './HcisCollapseItem.vue';
import HcisSelectOption from './HcisSelectOption.vue';
import HcisSplitterPane from './HcisSplitterPane.vue';

// ─────────────────────────────────────────────────────────────────────
describe('HcisCollapse', () => {
    const pasang = (props = {}) =>
        mount(HcisCollapse, {
            props,
            slots: {
                default: () => [
                    h(HcisCollapseItem, { name: 'a', title: 'A' }),
                    h(HcisCollapseItem, { name: 'b', title: 'B' }),
                    h(HcisCollapseItem, { name: 'c', title: 'C', disabled: true }),
                ],
            },
        });

    const kepala = (w, i) => w.findAll('.hcis-collapse-item__header')[i];

    it('mode ganda: membuka menambah nama, menutup membuangnya', async () => {
        const w = pasang({ modelValue: [] });

        await kepala(w, 0).trigger('click');
        expect(w.emitted('update:modelValue').at(-1)).toEqual([['a']]);

        await kepala(w, 1).trigger('click');
        expect(w.emitted('update:modelValue').at(-1)).toEqual([['a', 'b']]);

        await kepala(w, 0).trigger('click');
        expect(w.emitted('update:modelValue').at(-1)).toEqual([['b']]);
    });

    it('mode akordeon: hanya satu terbuka, dan nilainya skalar bukan larik', async () => {
        // Bentuk nilai berbeda antara dua mode. Kalau akordeon ikut memancarkan
        // larik, v-model pemakainya menerima tipe yang tidak ia harapkan dan
        // baru ketahuan jauh di hilir.
        const w = pasang({ accordion: true, modelValue: '' });

        await kepala(w, 0).trigger('click');
        expect(w.emitted('update:modelValue').at(-1)).toEqual(['a']);

        await kepala(w, 1).trigger('click');
        expect(w.emitted('update:modelValue').at(-1)).toEqual(['b']);

        // Menekan yang sedang terbuka menutupnya, dan kosongnya '' bukan null.
        await kepala(w, 1).trigger('click');
        expect(w.emitted('update:modelValue').at(-1)).toEqual(['']);
    });

    it('item disabled tidak memancarkan apa pun', async () => {
        const w = pasang({ modelValue: [] });

        await kepala(w, 2).trigger('click');
        expect(w.emitted('update:modelValue')).toBeUndefined();
    });

    it('menerima modelValue skalar di mode ganda tanpa memecahnya jadi karakter', async () => {
        // Jebakan klasik: string diperlakukan sebagai iterable lalu jadi
        // ['a','b','c'] alih-alih ['abc'].
        const w = pasang({ modelValue: 'abc' });
        await kepala(w, 0).trigger('click');
        expect(w.emitted('update:modelValue').at(-1)).toEqual([['abc', 'a']]);
    });
});

// ─────────────────────────────────────────────────────────────────────
describe('HcisCollapseItem', () => {
    it('mengikuti keadaan terbuka dari induknya', async () => {
        const w = mount(HcisCollapse, {
            props: { modelValue: ['b'] },
            slots: {
                default: () => [
                    h(HcisCollapseItem, { name: 'a', title: 'A' }),
                    h(HcisCollapseItem, { name: 'b', title: 'B' }),
                ],
            },
        });

        const item = w.findAll('.hcis-collapse-item');
        expect(item[0].classes()).not.toContain('hcis-collapse-item--active');
        expect(item[1].classes()).toContain('hcis-collapse-item--active');
    });

    it('tanpa induk, tidak meledak — hanya diam tertutup', () => {
        // Dipakai sendirian adalah kesalahan pemakaian, tapi ia tidak boleh
        // menjatuhkan seluruh halaman karena inject-nya kosong.
        const w = mount(HcisCollapseItem, { props: { name: 'x', title: 'X' } });
        expect(w.find('.hcis-collapse-item').exists()).toBe(true);
        expect(w.classes()).not.toContain('hcis-collapse-item--active');
    });
});

// ─────────────────────────────────────────────────────────────────────
describe('HcisCol', () => {
    const kelas = (props) => mount(HcisCol, { props }).classes();

    it('menerjemahkan span dan offset jadi kelas grid 24 satuan', () => {
        const c = kelas({ span: 12, offset: 6 });
        expect(c).toContain('hcis-col-12');
        expect(c).toContain('hcis-col-offset-6');
    });

    it('offset 0 tidak menghasilkan kelas — nol berarti tidak ada, bukan ada bernilai nol', () => {
        expect(kelas({ span: 24, offset: 0 })).not.toContain('hcis-col-offset-0');
    });

    it('titik henti bentuk angka dan bentuk objek keduanya berlaku', () => {
        expect(kelas({ span: 24, md: 12 })).toContain('hcis-col-md-12');

        const c = kelas({ span: 24, lg: { span: 8, offset: 2 } });
        expect(c).toContain('hcis-col-lg-8');
        expect(c).toContain('hcis-col-lg-offset-2');
    });

    it('menghormati prop tag', () => {
        expect(mount(HcisCol, { props: { tag: 'section' } }).element.tagName).toBe('SECTION');
    });
});

// ─────────────────────────────────────────────────────────────────────
describe('HcisSelectOption', () => {
    /** Induk palsu: cukup menyediakan kontrak yang di-inject anaknya. */
    const IndukPalsu = (nilai, multiple, jejak) =>
        defineComponent({
            provide() {
                return {
                    hcisSelect: {
                        props: { multiple },
                        modelValue: ref(nilai),
                        selectOption: jejak,
                    },
                };
            },
            render() {
                return h('div', this.$slots.default?.());
            },
        });

    const pasang = (opsi, nilai, multiple, jejak = () => {}) =>
        mount(IndukPalsu(nilai, multiple, jejak), {
            slots: { default: () => h(HcisSelectOption, opsi) },
        });

    it('menandai dirinya terpilih pada pilihan tunggal', () => {
        const w = pasang({ value: 'b', label: 'B' }, 'b', false);
        expect(w.find('.hcis-select-option').classes()).toContain('is-selected');
        expect(w.find('.hcis-select-option').attributes('aria-selected')).toBe('true');
    });

    it('menandai dirinya terpilih pada pilihan ganda', () => {
        const w = pasang({ value: 'b', label: 'B' }, ['a', 'b'], true);
        expect(w.find('.hcis-select-option').classes()).toContain('is-selected');
    });

    /*
     * Uji ini dulu menuntut kebalikannya: bahwa 1 dan '1' HARUS dibedakan.
     * Ia merah sejak baris pertamanya ditulis (4 Sep) karena perbandingan
     * String() sudah sengaja dipasang seminggu sebelumnya (27 Agu, "Harden SOP
     * document governance and reporting") — jadi ia bukan penjaga yang rusak
     * belakangan, melainkan tuntutan yang tak pernah disepakati kodenya.
     *
     * Yang benar adalah perilaku yang ada sekarang. Id dari basis data tiba
     * sebagai angka sementara nilai opsi kerap berupa teks (atau sebaliknya
     * setelah lewat query string); tanpa penyamaan itu, pilihan yang sudah
     * tersimpan tidak tersorot saat form dibuka untuk disunting. Bahaya yang
     * dituduhkan uji lama — dua baris menyala sekaligus — menuntut satu select
     * memuat 1 DAN '1' sekaligus, yang tidak terjadi di mana pun.
     *
     * Yang tetap wajib dijaga ada di uji penolakan di bawahnya: nilai yang
     * memang BERBEDA tidak boleh ikut tersorot.
     */
    it('menyamakan angka dan teks yang isinya sama, karena id DB dan nilai opsi kerap beda tipe', () => {
        const w = pasang({ value: 1 }, '1', false);
        expect(w.find('.hcis-select-option').classes()).toContain('is-selected');
    });

    it('PENOLAKAN — nilai yang berbeda tidak ikut tersorot', () => {
        const w = pasang({ value: 2 }, '1', false);
        expect(w.find('.hcis-select-option').classes()).not.toContain('is-selected');
    });

    it('meneruskan value dan label saat diklik', async () => {
        const dipilih = [];
        const w = pasang({ value: 'b', label: 'B' }, '', false, (x) => dipilih.push(x));

        await w.find('.hcis-select-option').trigger('click');
        expect(dipilih).toHaveLength(1);
        expect(dipilih[0]).toMatchObject({ value: 'b', label: 'B' });
    });

    it('memakai value sebagai label saat label tidak diberikan', async () => {
        const dipilih = [];
        const w = pasang({ value: 'b' }, '', false, (x) => dipilih.push(x));

        await w.find('.hcis-select-option').trigger('click');
        expect(dipilih).toHaveLength(1);
        expect(dipilih[0]).toMatchObject({ value: 'b', label: 'b' });
    });

    it('opsi disabled tidak bisa dipilih', async () => {
        const dipilih = [];
        const w = pasang({ value: 'b', disabled: true }, '', false, (x) => dipilih.push(x));

        await w.find('.hcis-select-option').trigger('click');
        expect(dipilih).toEqual([]);
    });

    it('tanpa induk, klik tidak menjatuhkan halaman', async () => {
        const w = mount(HcisSelectOption, { props: { value: 'x' } });
        await w.find('.hcis-select-option').trigger('click');
        expect(w.find('.hcis-select-option').exists()).toBe(true);
    });
});

// ─────────────────────────────────────────────────────────────────────
describe('HcisSplitterPane', () => {
    const gaya = (props) => mount(HcisSplitterPane, { props }).attributes('style') ?? '';

    it('angka diperlakukan sebagai persen, bukan piksel', () => {
        // Kalau 30 diterjemahkan jadi 30px, panelnya menyempit jadi segaris
        // dan isinya tidak terlihat sama sekali.
        expect(gaya({ size: 30 })).toContain('30%');
    });

    it('string dipakai apa adanya', () => {
        expect(gaya({ size: '24rem' })).toContain('24rem');
    });

    it('tanpa size, panel membagi ruang sisa', () => {
        expect(gaya({})).toContain('flex: 1 1 0%');
    });

    it('collapsed menutup panel sepenuhnya, termasuk padding-nya', () => {
        // Menyetel lebar 0 tanpa membuang padding menyisakan garis tipis yang
        // terlihat seperti cacat gambar.
        const s = gaya({ collapsed: true });
        expect(s).toContain('width: 0px');
        expect(s).toContain('overflow: hidden');
        expect(s).toContain('padding: 0');
    });

    it('collapsed menang atas size', () => {
        const s = gaya({ size: 50, collapsed: true });
        expect(s).toContain('width: 0px');
        expect(s).not.toContain('50%');
    });
});

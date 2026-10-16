import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ElementPlus from 'element-plus';
import FilterPilihSemua from './FilterPilihSemua.vue';

const OPSI = ['Finance', 'HSE', 'IT Support', 'Produksi', 'Supervisor Lapangan'];

const terpasang = [];

/**
 * Di-mount dengan element-plus SUNGGUHAN, bukan stub: yang diuji di sini adalah
 * hasil rangkaian el-select + slot #header + el-checkbox. Stub akan menjadikan
 * uji ini hijau bahkan kalau slot kepalanya tak pernah terpasang.
 */
function pasang(nilaiAwal = []) {
    const wrapper = mount(FilterPilihSemua, {
        props: {
            modelValue: nilaiAwal,
            opsi: OPSI,
            'onUpdate:modelValue': (v) => wrapper.setProps({ modelValue: v }),
        },
        global: { plugins: [ElementPlus] },
        attachTo: document.body,
    });
    terpasang.push(wrapper);

    return wrapper;
}

/** Dropdown-nya di-teleport ke body, jadi dicari lewat document, bukan wrapper. */
async function buka(wrapper) {
    await wrapper.find('.el-select__wrapper').trigger('click');
    await new Promise((r) => setTimeout(r, 0));

    return document.querySelector('.el-select-dropdown__header');
}

afterEach(() => {
    terpasang.splice(0).forEach((w) => w.unmount());
    document.body.innerHTML = '';
});

describe('FilterPilihSemua', () => {
    it('memasang baris pilih-semua di kepala dropdown beserta jumlah opsinya', async () => {
        const wrapper = pasang();
        const kepala = await buka(wrapper);

        expect(kepala).not.toBeNull();
        expect(kepala.textContent).toContain('Pilih semua (5)');
    });

    it('menyapu seluruh opsi saat pilih-semua dicentang', async () => {
        const wrapper = pasang();
        const kepala = await buka(wrapper);

        kepala.querySelector('.el-checkbox').click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual(OPSI);
        expect(wrapper.emitted('change')).toHaveLength(1);
    });

    it('mengosongkan pilihan saat pilih-semua dicentang ulang', async () => {
        const wrapper = pasang([...OPSI]);
        const kepala = await buka(wrapper);

        expect(kepala.querySelector('input').checked).toBe(true);

        kepala.querySelector('.el-checkbox').click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual([]);
    });

    it('berstatus sebagian saat baru sebagian opsi terpilih', async () => {
        const wrapper = pasang(['HSE']);
        const kepala = await buka(wrapper);

        // element-plus menaruh is-indeterminate di __input, bukan di akar.
        expect(kepala.querySelector('.el-checkbox__input').className).toContain('is-indeterminate');
        expect(kepala.querySelector('.el-checkbox').getAttribute('aria-checked')).toBe('mixed');
        expect(kepala.querySelector('input').checked).toBe(false);
    });

    it('hanya menyapu hasil tersaring saat admin sedang mengetik', async () => {
        const wrapper = pasang();
        await buka(wrapper);

        // Meniru element-plus yang meneruskan ketikan ke :filter-method.
        wrapper.vm.saring('su');
        await wrapper.vm.$nextTick();

        const kepala = document.querySelector('.el-select-dropdown__header');
        expect(kepala.textContent).toContain('Pilih semua hasil (2)');

        kepala.querySelector('.el-checkbox').click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual(['IT Support', 'Supervisor Lapangan']);
    });

    it('tidak membuang nilai terpilih yang berada di luar daftar opsi', async () => {
        const wrapper = pasang(['Unit Dihapus']);
        const kepala = await buka(wrapper);

        kepala.querySelector('.el-checkbox').click();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual([...OPSI, 'Unit Dihapus']);
    });

    it('tidak memasang baris pilih-semua saat opsinya kosong', async () => {
        const wrapper = mount(FilterPilihSemua, {
            props: { modelValue: [], opsi: [] },
            global: { plugins: [ElementPlus] },
            attachTo: document.body,
        });
        terpasang.push(wrapper);

        await buka(wrapper);

        expect(document.querySelector('.el-select-dropdown__header')).toBeNull();
    });
});

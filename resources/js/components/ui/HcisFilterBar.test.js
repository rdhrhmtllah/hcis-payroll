import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisFilterBar from './HcisFilterBar.vue';

describe('HcisFilterBar.vue', () => {
    const mockFilters = [
        {
            key: 'status',
            label: 'Status Otorisasi',
            type: 'select',
            options: [
                { label: 'Semua Status', value: '' },
                { label: 'Disetujui', value: 'APPROVED' },
                { label: 'Menunggu', value: 'PENDING' },
            ]
        },
        {
            key: 'department',
            label: 'Divisi',
            type: 'text',
            placeholder: 'Ketik divisi...'
        }
    ];

    it('renders search input and emits update:search on typing', async () => {
        const wrapper = mount(HcisFilterBar, {
            props: {
                search: '',
                searchPlaceholder: 'Cari Karyawan...',
            }
        });

        expect(wrapper.find('input[type="search"]').exists()).toBe(true);

        const searchInput = wrapper.find('input[type="search"]');
        await searchInput.setValue('Ahmad');

        /*
         * Pencarian kini DITUNDA (searchDebounce, baku 300ms). Tanpa jeda, tiap
         * huruf memicu satu penyaringan penuh dan kursor tersendat di daftar
         * besar.
         *
         * Tes ini sengaja menunggu sungguhan, bukan memakai timer palsu:
         * yang perlu dijamin adalah nilainya AKHIRNYA sampai, bukan mekanisme
         * penundaannya.
         */
        expect(wrapper.emitted('update:search'), 'belum boleh terkirim sebelum jeda').toBeFalsy();

        await new Promise((r) => setTimeout(r, 350));

        expect(wrapper.emitted('update:search')).toBeTruthy();
        expect(wrapper.emitted('update:search').at(-1)[0]).toBe('Ahmad');
    });

    it('searchDebounce: 0 mengirim seketika', async () => {
        // Daftar kecil tidak perlu menunggu; jeda di situ cuma terasa lamban.
        const wrapper = mount(HcisFilterBar, {
            props: { search: '', searchDebounce: 0 },
        });

        await wrapper.find('input[type="search"]').setValue('Budi');

        expect(wrapper.emitted('update:search')).toBeTruthy();
        expect(wrapper.emitted('update:search')[0][0]).toBe('Budi');
    });

    it('kotak pencarian tetap responsif selama jeda berjalan', async () => {
        /*
         * Nilai ketikan ditahan lokal. Kalau kotaknya menunggu prop induk,
         * huruf akan terasa "balik" — pengguna mengetik cepat lalu melihat
         * karakter hilang, dan itu terbaca sebagai aplikasi yang rusak.
         */
        const wrapper = mount(HcisFilterBar, { props: { search: '' } });
        const input = wrapper.find('input[type="search"]');

        await input.setValue('Ahm');
        expect(input.element.value).toBe('Ahm');
    });

    it('renders filters grid and emits filter-change on select', async () => {
        const wrapper = mount(HcisFilterBar, {
            props: {
                filters: mockFilters,
                filterValues: { status: '', department: '' },
                collapsible: false,
            }
        });

        /*
         * Tiap saringan kini dirender HcisFilterField, bukan <select> mentah —
         * jadi tipe date, cascader, checkbox, dan kawan-kawan tampil dengan
         * komponen Hcis yang sama seperti di formulir mana pun.
         *
         * Yang diuji adalah SAMBUNGANNYA: satu kolom per saringan, dan nilai
         * yang keluar dari kolom itu diteruskan sebagai filter-change lengkap
         * dengan kunci dan salinan seluruh nilai.
         */
        const kolom = wrapper.findAllComponents({ name: 'HcisFilterField' });
        expect(kolom).toHaveLength(mockFilters.length);
        expect(kolom[0].props('filter').key).toBe('status');

        await kolom[0].vm.$emit('update:modelValue', 'APPROVED');

        const dipancarkan = wrapper.emitted('filter-change');
        expect(dipancarkan).toBeTruthy();
        expect(dipancarkan[0][0].key).toBe('status');
        expect(dipancarkan[0][0].value).toBe('APPROVED');
        // Nilai gabungan ikut dikirim supaya pemanggil tidak perlu merakitnya
        // sendiri — dan tidak perlu menebak nilai saringan lain.
        expect(dipancarkan[0][0].values.status).toBe('APPROVED');
    });

    it('lebar kolom dikirim sebagai variabel, bukan grid-column inline', async () => {
        /*
         * Gaya inline mengalahkan media query. Kalau lebar ditulis langsung
         * sebagai `grid-column`, saringan akan terkunci selebar itu sampai ke
         * layar ponsel dan responsifnya mati diam-diam.
         */
        const wrapper = mount(HcisFilterBar, {
            props: {
                filters: [{ key: 'rentang', label: 'Rentang', type: 'daterange', col: 6 }],
                filterValues: {},
                collapsible: false,
            },
        });

        const gaya = wrapper.find('.hcis-filter-col').attributes('style') ?? '';
        expect(gaya).toContain('--hcis-filter-col');
        expect(gaya, 'grid-column inline akan mengunci lebar di semua layar').not.toContain('grid-column');
    });

    it('emits reset event when reset button is clicked', async () => {
        const wrapper = mount(HcisFilterBar, {
            props: {
                search: 'Kata Kunci',
                filters: mockFilters,
                filterValues: { status: 'APPROVED' },
            }
        });

        const resetBtn = wrapper.find('.hcis-filter-reset-btn');
        expect(resetBtn.exists()).toBe(true);

        await resetBtn.trigger('click');
        expect(wrapper.emitted('reset')).toBeTruthy();
    });
});

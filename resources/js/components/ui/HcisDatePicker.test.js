import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import HcisDatePicker from './HcisDatePicker.vue';

describe('HcisDatePicker.vue', () => {
    it('renders input shell with modelValue date', () => {
        const wrapper = mount(HcisDatePicker, {
            props: { modelValue: '2026-08-12' },
        });

        const input = wrapper.find('.hcis-date-picker__input');
        expect(input.element.value).toBe('2026-08-12');
    });

    it('opens popover dialog when trigger is clicked', async () => {
        const wrapper = mount(HcisDatePicker, {
            props: { modelValue: '2026-08-12', teleportTo: false },
        });

        expect(wrapper.find('.hcis-date-picker__popover').exists()).toBe(false);
        await wrapper.find('.hcis-date-picker__trigger').trigger('click');
        expect(wrapper.find('.hcis-date-picker__popover').exists()).toBe(true);
        expect(wrapper.text()).toContain('Preset Cepat');
        // Nama bulan dan tahun berada di dua elemen terpisah, jadi
        // wrapper.text() menyatukannya tanpa spasi.
        expect(wrapper.text()).toContain('Agustus');
        expect(wrapper.text()).toContain('2026');
    });

    it('emits update:modelValue when a date day is selected and confirmed', async () => {
        const wrapper = mount(HcisDatePicker, {
            props: { modelValue: '2026-08-12', teleportTo: false },
        });

        await wrapper.find('.hcis-date-picker__trigger').trigger('click');
        const dayBtn = wrapper.findAll('.hcis-date-picker__day-cell')[15];
        await dayBtn.trigger('click');

        // Sejak kalendernya ditulis ulang, memilih hari bisa langsung menutup
        // popovernya — tombol Terapkan tidak selalu masih ada. Yang diuji
        // seharusnya HASILNYA (nilai terpancar), bukan urutan tombol yang
        // kebetulan dipakai versi lama.
        const confirmBtn = wrapper.find('.hcis-date-picker__btn-confirm');
        if (confirmBtn.exists()) await confirmBtn.trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    });

    it('menit :59 bisa dipilih agar jam akhir hari 23:59 terjangkau', async () => {
        const wrapper = mount(HcisDatePicker, {
            props: { modelValue: '2026-10-10 23:59:00', type: 'datetime', teleportTo: false },
        });

        await wrapper.find('.hcis-date-picker__trigger').trigger('click');
        const menit = wrapper.findAll('.hcis-time-item').map((b) => b.text());

        expect(menit).toContain('59');
        expect(menit).toContain('55');
        expect(wrapper.findAll('.hcis-time-item.is-selected').map((b) => b.text())).toEqual(['23', '59']);
    });

    // ---------------------------------------------------------------------
    // Mode tanggal + jam di desktop: yang dipilih harus TERLIHAT dipilih.
    //
    // Laporan user 6 Okt 2026: mengeklik tanggal tak mengubah tampilannya.
    // Sel desktop diberi kelas `is-selected` yang tak punya aturan CSS sama
    // sekali — gaya terpilih hanya milik `is-start`/`is-end`, kelas yang
    // dipakai versi ponsel. Kolom jam juga tak bergulir ke jam terpilih,
    // sehingga "08" berada di luar pandangan.
    // ---------------------------------------------------------------------

    it('tanggal yang diklik memakai kelas bergaya terpilih (desktop)', async () => {
        const wrapper = mount(HcisDatePicker, {
            props: { modelValue: '', type: 'datetime', teleportTo: false },
        });

        await wrapper.find('.hcis-date-picker__trigger').trigger('click');
        const hari = wrapper.findAll('.hcis-day-cell').find((b) => b.text() === '20' && !b.classes('is-other-month'));
        await hari.trigger('click');

        expect(hari.classes()).toContain('is-start');
        expect(wrapper.findAll('.hcis-day-cell.is-start')).toHaveLength(1);
    });

    it('jam dan menit terpilih digulir ke dalam pandangan saat dibuka', async () => {
        const wrapper = mount(HcisDatePicker, {
            props: { modelValue: '2026-10-20 08:30:00', type: 'datetime', teleportTo: false },
            attachTo: document.body,
        });

        // jsdom tak menghitung tata letak; letak butir dipalsukan (tinggi 32px).
        const asli = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'offsetTop');
        Object.defineProperty(HTMLElement.prototype, 'offsetTop', {
            configurable: true,
            get() {
                const daftar = [...(this.parentElement?.children || [])];
                return this.classList?.contains('hcis-time-item') ? daftar.indexOf(this) * 32 : 0;
            },
        });

        try {
            await wrapper.find('.hcis-date-picker__trigger').trigger('click');
            await new Promise((r) => setTimeout(r, 0));
            await wrapper.vm.$nextTick();

            const [jam, menit] = wrapper.findAll('.hcis-time-col-list');
            expect(jam.element.scrollTop).toBeGreaterThan(0);
            expect(menit.element.scrollTop).toBeGreaterThan(0);
        } finally {
            if (asli) Object.defineProperty(HTMLElement.prototype, 'offsetTop', asli);
            wrapper.unmount();
        }
    });

    // ---------------------------------------------------------------------
    // Batas tanggal.
    //
    // Menahan di kalender lebih baik daripada memarahi sesudahnya: tanggal
    // yang tidak pernah bisa diterima tidak boleh ditawarkan. Tanpa ini tiap
    // halaman menambal batasnya sendiri — dan tambalan per halaman pasti
    // berbeda dari aturan servernya.
    // ---------------------------------------------------------------------

    const sesudah = (teks) => (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` > teks;

    it('mematikan hari yang ditolak disabledDate', async () => {
        const wrapper = mount(HcisDatePicker, {
            props: { modelValue: '2026-08-12', teleportTo: false, disabledDate: sesudah('2026-08-15') },
        });

        await wrapper.find('.hcis-date-picker__trigger').trigger('click');

        const sel = wrapper.findAll('.hcis-date-picker__day-cell');
        const mati = sel.filter((c) => c.attributes('disabled') !== undefined);

        expect(mati.length).toBeGreaterThan(0);
        expect(mati.map((c) => c.text())).toContain('16');
        expect(mati.map((c) => c.text())).not.toContain('10');
    });

    it('menolak memilih hari yang dimatikan', async () => {
        const wrapper = mount(HcisDatePicker, {
            props: { modelValue: '2026-08-12', teleportTo: false, disabledDate: sesudah('2026-08-15') },
        });

        await wrapper.find('.hcis-date-picker__trigger').trigger('click');

        const target = wrapper.findAll('.hcis-date-picker__day-cell').find((c) => c.text() === '20');
        await target.trigger('click');
        await wrapper.find('.hcis-date-picker__btn-confirm').trigger('click');

        const dipancarkan = wrapper.emitted('update:modelValue');
        expect(dipancarkan?.at(-1)?.[0]).not.toBe('2026-08-20');
    });

    it('tanpa disabledDate tidak ada hari yang mati', async () => {
        const wrapper = mount(HcisDatePicker, {
            props: { modelValue: '2026-08-12', teleportTo: false },
        });

        await wrapper.find('.hcis-date-picker__trigger').trigger('click');

        const mati = wrapper.findAll('.hcis-date-picker__day-cell').filter((c) => c.attributes('disabled') !== undefined);
        expect(mati).toHaveLength(0);
    });

    describe('format baca', () => {
        it("menampilkan 'D MMMM YYYY' tetapi nilai tetap YYYY-MM-DD", async () => {
            const wrapper = mount(HcisDatePicker, {
                props: { modelValue: '2024-12-24', format: 'D MMMM YYYY', teleportTo: false },
            });

            const input = wrapper.find('.hcis-date-picker__input');
            expect(input.element.value).toBe('24 Desember 2024');
            // Bentuk baca tak bisa diurai balik; tanggal dipilih dari kalender.
            expect(input.attributes('readonly')).toBeDefined();

            await wrapper.find('.hcis-date-picker__trigger').trigger('click');
            const hari = wrapper.findAll('.hcis-date-picker__day-cell').find((c) => c.text() === '5' && !c.classes().some((k) => k.includes('other-month')));
            await hari.trigger('click');

            expect(wrapper.emitted('update:modelValue').at(-1)[0]).toBe('2024-12-05');
        });

        it('tanpa format baca tetap menampilkan YYYY-MM-DD seperti sebelumnya', () => {
            const wrapper = mount(HcisDatePicker, { props: { modelValue: '2024-12-24' } });

            expect(wrapper.find('.hcis-date-picker__input').element.value).toBe('2024-12-24');
        });
    });

    /**
     * Di ponsel dialog mengembalikan fokus ke input saat ditutup. Kalau fokus
     * itu membuka kalender lagi, Terapkan dan ✕ tak pernah bisa menutupnya.
     */
    describe('ponsel', () => {
        const lebarAsli = window.innerWidth;

        beforeEach(() => {
            Object.defineProperty(window, 'innerWidth', { configurable: true, value: 390 });
        });

        afterEach(() => {
            Object.defineProperty(window, 'innerWidth', { configurable: true, value: lebarAsli });
            // Dialog di-teleport ke body; sisa uji sebelumnya membuat pencarian
            // tombol menemukan dialog yang salah.
            document.body.innerHTML = '';
        });

        it('fokus yang dikembalikan dialog tidak membuka ulang kalender', async () => {
            const wrapper = mount(HcisDatePicker, { props: { modelValue: '2026-08-12' } });
            await wrapper.vm.$nextTick();

            await wrapper.find('.hcis-date-picker__input').trigger('focus');

            expect(wrapper.findComponent({ name: 'HcisDialog' }).props('modelValue')).toBe(false);
        });

        it('ketukan pada input tetap membuka kalender', async () => {
            const wrapper = mount(HcisDatePicker, { props: { modelValue: '2026-08-12' } });
            await wrapper.vm.$nextTick();

            await wrapper.find('.hcis-date-picker__input').trigger('click');

            expect(wrapper.findComponent({ name: 'HcisDialog' }).props('modelValue')).toBe(true);
        });

        // Dialog di-teleport ke body, jadi isinya dicari lewat document.
        const bukaDiBody = async (props = { modelValue: '2026-08-12' }) => {
            const wrapper = mount(HcisDatePicker, { props, attachTo: document.body });
            await wrapper.vm.$nextTick();
            await wrapper.find('.hcis-date-picker__input').trigger('click');
            await wrapper.vm.$nextTick();

            return wrapper;
        };
        const terbuka = (w) => w.findComponent({ name: 'HcisDialog' }).props('modelValue');
        const hariDiDialog = (angka) =>
            [...document.querySelectorAll('.hcis-mobile-dialog-content .hcis-day-cell:not(.is-other-month)')].find(
                (b) => b.textContent.trim() === String(angka),
            );
        const tombol = (teks) => [...document.querySelectorAll('button')].find((b) => b.textContent.trim() === teks);

        it('ketukan di dalam dialog (navigasi bulan) tidak menutupnya', async () => {
            const wrapper = await bukaDiBody();

            document.querySelector('.hcis-mobile-dialog-content [title="Bulan Berikutnya"]').click();
            await wrapper.vm.$nextTick();

            expect(terbuka(wrapper)).toBe(true);
            wrapper.unmount();
        });

        it('mengetuk tanggal hanya menandai; tidak memancarkan, tidak menutup', async () => {
            const wrapper = await bukaDiBody();

            hariDiDialog(20).click();
            await wrapper.vm.$nextTick();

            expect(terbuka(wrapper)).toBe(true);
            expect(wrapper.emitted('update:modelValue')).toBeFalsy();
            wrapper.unmount();
        });

        it('Terapkan memancarkan tanggal yang ditandai lalu menutup', async () => {
            const wrapper = await bukaDiBody();

            hariDiDialog(20).click();
            await wrapper.vm.$nextTick();
            tombol('Terapkan').click();
            await wrapper.vm.$nextTick();

            expect(wrapper.emitted('update:modelValue').at(-1)[0]).toBe('2026-08-20');
            expect(terbuka(wrapper)).toBe(false);
            wrapper.unmount();
        });

        it('Batal membuang tanda dan tidak terbawa ke pembukaan berikutnya', async () => {
            const wrapper = await bukaDiBody();

            hariDiDialog(20).click();
            await wrapper.vm.$nextTick();
            tombol('Batal').click();
            await wrapper.vm.$nextTick();

            expect(wrapper.emitted('update:modelValue')).toBeFalsy();

            await wrapper.find('.hcis-date-picker__input').trigger('click');
            await wrapper.vm.$nextTick();
            tombol('Terapkan').click();
            await wrapper.vm.$nextTick();

            expect(wrapper.emitted('update:modelValue').at(-1)[0]).toBe('2026-08-12');
            wrapper.unmount();
        });
    });
});

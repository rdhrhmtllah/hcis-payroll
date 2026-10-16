import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { h } from 'vue';
import HcisBadge from './HcisBadge.vue';
import HcisButton from './HcisButton.vue';
import HcisField from './HcisField.vue';

import HcisCard from './HcisCard.vue';

import HcisEmptyState from './HcisEmptyState.vue';
import HcisSkeleton from './HcisSkeleton.vue';
import HcisTable from './HcisTable.vue';
import HcisCheckbox from './HcisCheckbox.vue';
import HcisTabs from './HcisTabs.vue';
import HcisBreadcrumb from './HcisBreadcrumb.vue';
import HcisIcon from './HcisIcon.vue';
import HcisAlert from './HcisAlert.vue';
import HcisAvatar from './HcisAvatar.vue';
import HcisTextarea from './HcisTextarea.vue';
import HcisRadio from './HcisRadio.vue';
import HcisProgress from './HcisProgress.vue';
import HcisSteps from './HcisSteps.vue';
import HcisGerak from './HcisGerak.vue';
import HcisDivider from './HcisDivider.vue';

const Gerak = HcisGerak;

describe('HCIS design system primitives', () => {
    it('renders button variant and blocks interaction while loading', () => {
        const wrapper = mount(HcisButton, {
            props: { label: 'Simpan', variant: 'secondary', loading: true },
        });

        expect(wrapper.classes()).toContain('hcis-btn--secondary');
        expect(wrapper.attributes('disabled')).toBeDefined();
        expect(wrapper.find('.hcis-btn__spinner').exists()).toBe(true);
        expect(wrapper.text()).toContain('Simpan');
    });

    it('supports semantic badge variants', () => {
        const wrapper = mount(HcisBadge, { props: { label: 'Disetujui', variant: 'success' } });

        expect(wrapper.classes()).toContain('hcis-badge--success');
        expect(wrapper.text()).toBe('Disetujui');
    });

    it('merender <a> ketika href diisi', () => {
        const wrapper = mount(HcisButton, { props: { label: 'Buka', href: '/detail' } });

        expect(wrapper.element.tagName).toBe('A');
        expect(wrapper.attributes('href')).toBe('/detail');
        expect(wrapper.attributes('disabled')).toBeUndefined();
    });

    /**
     * Penjaganya masih ada, cara kerjanya yang berubah.
     *
     * Dulu tombol ikon tanpa nama aksesibel MENOLAK dirender. Itu keras:
     * satu prop yang lupa diisi membuat tombolnya lenyap dari layar, dan yang
     * terlihat cuma "tombolnya hilang" tanpa petunjuk apa pun. Sekarang ia
     * tetap dirender tetapi memperingatkan di console (HcisButton.vue:123).
     *
     * Uji ini ikut berubah cara, BUKAN maksud: yang dituntut tetap ada
     * teguran saat nama aksesibelnya hilang. Menghapus ujinya akan membuat
     * pencabutan teguran itu lolos tanpa suara.
     */
    it('menegur tombol ikon yang tidak punya nama aksesibel', () => {
        const teguran = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const wrapper = mount(HcisButton, { props: { iconOnly: true } });

        expect(wrapper.find('button').exists()).toBe(true);
        expect(teguran).toHaveBeenCalledWith(expect.stringContaining('aria-label'));

        teguran.mockRestore();
    });

    it('tidak menegur tombol ikon yang sudah punya aria-label', () => {
        const teguran = vi.spyOn(console, 'warn').mockImplementation(() => {});

        mount(HcisButton, { props: { iconOnly: true }, attrs: { 'aria-label': 'Hapus baris' } });

        expect(teguran).not.toHaveBeenCalled();

        teguran.mockRestore();
    });

    it('merender tombol ikon ketika aria-label disertakan', () => {
        const wrapper = mount(HcisButton, {
            props: { iconOnly: true },
            attrs: { 'aria-label': 'Hapus' },
        });

        expect(wrapper.find('button').exists()).toBe(true);
        expect(wrapper.classes()).toContain('hcis-btn--icon');
    });

    it('mendukung ukuran dan titik pada lencana', () => {
        const wrapper = mount(HcisBadge, {
            props: { label: 'Aktif', variant: 'success', size: 'sm', dot: true },
        });

        expect(wrapper.classes()).toContain('hcis-badge--sm');
        expect(wrapper.find('.hcis-badge__dot').exists()).toBe(true);
        expect(wrapper.text()).toBe('Aktif');
    });

    it('emits the new value from a field', async () => {
        const wrapper = mount(HcisField, {
            props: { id: 'employee-name', label: 'Nama', modelValue: '' },
        });

        await wrapper.find('input').setValue('Ayu');

        expect(wrapper.emitted('update:modelValue')).toEqual([['Ayu']]);
    });

    it('mendukung fitur clearable, password toggle, dan ikon pada HcisField', async () => {
        const wrapper = mount(HcisField, {
            props: {
                id: 'search-field',
                label: 'Cari',
                modelValue: 'Kueri',
                clearable: true,
                leadingIcon: 'bi bi-search',
            },
        });

        expect(wrapper.classes()).toContain('hcis-field--has-leading');
        expect(wrapper.find('.hcis-field__clear-btn').exists()).toBe(true);

        await wrapper.find('.hcis-field__clear-btn').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toEqual([['']]);
        expect(wrapper.emitted('clear')).toBeTruthy();
    });

    it('merender HcisCard dengan varian, loading skeleton, dan menangani event klik interaktif', async () => {
        const wrapper = mount(HcisCard, {
            props: {
                title: 'Kartu Pengajuan',
                description: 'Deskripsi singkat',
                variant: 'glass',
                interactive: true,
                loading: false,
                icon: 'bi bi-card-checklist',
            },
        });

        expect(wrapper.classes()).toContain('hcis-card--variant-glass');
        expect(wrapper.classes()).toContain('hcis-card--interactive');
        expect(wrapper.find('.hcis-card__title').text()).toBe('Kartu Pengajuan');
        expect(wrapper.find('.hcis-card__icon-box').exists()).toBe(true);

        await wrapper.trigger('click');
        expect(wrapper.emitted('click')).toBeTruthy();
    });

    it('merender HcisEmptyState dengan varian, sentiment, ikon, dan slot tindakan', () => {
        const wrapper = mount(HcisEmptyState, {
            props: {
                title: 'Data Tidak Ada',
                description: 'Cari kata kunci lain',
                variant: 'dashed',
                sentiment: 'warning',
                icon: 'bi bi-search',
            },
        });

        expect(wrapper.classes()).toContain('hcis-empty-state--variant-dashed');
        expect(wrapper.classes()).toContain('hcis-empty-state--sentiment-warning');
        expect(wrapper.find('.hcis-empty-state__title').text()).toBe('Data Tidak Ada');
        expect(wrapper.find('.bi-search').exists()).toBe(true);
    });

    it('merender HcisSkeleton dengan preset avatar, card, dan tipe animasi pulse', () => {
        const avatarWrapper = mount(HcisSkeleton, {
            props: { type: 'avatar', animation: 'pulse', width: '4rem', height: '4rem' },
        });
        expect(avatarWrapper.classes()).toContain('hcis-skeleton--circle');
        expect(avatarWrapper.classes()).toContain('hcis-skeleton--pulse');

        const cardWrapper = mount(HcisSkeleton, {
            props: { type: 'card' },
        });
        expect(cardWrapper.classes()).toContain('hcis-skeleton--card');
        expect(cardWrapper.find('.hcis-skeleton__header').exists()).toBe(true);
    });

    it('mendukung fitur pengurutan kolom, seleksi baris, dan opsi striped/bordered pada HcisTable', async () => {
        const columns = [
            { key: 'nama', label: 'Nama', sortable: true },
            { key: 'jabatan', label: 'Jabatan' },
        ];
        const rows = [
            { id: 1, nama: 'Budi', jabatan: 'Dev' },
            { id: 2, nama: 'Ayu', jabatan: 'Lead' },
        ];

        const wrapper = mount(HcisTable, {
            props: {
                columns,
                rows,
                selectable: true,
                striped: true,
                bordered: true,
                sortKey: 'nama',
                sortOrder: 'asc',
            },
        });

        expect(wrapper.find('.hcis-table-container--bordered').exists()).toBe(true);
        expect(wrapper.find('.hcis-table--striped').exists()).toBe(true);

        // Sorting check
        const cells = wrapper.findAll('tbody td');
        expect(cells[1].text()).toBe('Ayu'); // Sorted ASC: Ayu comes first

        // Select row check
        const checkboxes = wrapper.findAllComponents(HcisCheckbox);
        await checkboxes[1].find('input').setValue(true);
        expect(wrapper.emitted('update:selectedKeys')).toBeTruthy();
    });

    it('mendukung varian segmented dan slot panel pada HcisTabs', async () => {
        const tabs = [
            { key: 'harian', label: 'Harian', icon: 'bi bi-calendar' },
            { key: 'bulanan', label: 'Bulanan' },
        ];

        const wrapper = mount(HcisTabs, {
            props: {
                modelValue: 'harian',
                tabs,
                variant: 'segmented',
                block: true,
            },
            slots: {
                'panel-harian': '<div class="test-panel-harian">Konten Harian</div>',
            },
        });

        expect(wrapper.find('.hcis-tabs--variant-segmented').exists()).toBe(true);
        expect(wrapper.find('.hcis-tabs--block').exists()).toBe(true);
        expect(wrapper.find('.test-panel-harian').text()).toBe('Konten Harian');

        // Tab click
        const tabButtons = wrapper.findAll('button[role="tab"]');
        await tabButtons[1].trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['bulanan']);
    });

    it('mendukung opsi pemisah kustom, penyingkatan maxItems, dan varian pills pada HcisBreadcrumb', () => {
        const items = [
            { label: 'Beranda', href: '/', icon: 'bi bi-house' },
            { label: 'Level 1', href: '/l1' },
            { label: 'Level 2', href: '/l2' },
            { label: 'Level 3', href: '/l3' },
            { label: 'Halaman Aktif' },
        ];

        const wrapper = mount(HcisBreadcrumb, {
            props: {
                items,
                separator: 'chevron',
                variant: 'pills',
                maxItems: 3,
            },
        });

        expect(wrapper.find('.hcis-breadcrumb--variant-pills').exists()).toBe(true);
        expect(wrapper.find('.bi-chevron-right').exists()).toBe(true);
        expect(wrapper.text()).toContain('...');
        expect(wrapper.find('.hcis-breadcrumb__current').text()).toBe('Halaman Aktif');
    });

    it('mendukung normalisasi nama, warna, animasi spin, dan kontainer boxed pada HcisIcon', () => {
        const wrapper = mount(HcisIcon, {
            props: {
                name: 'check-circle',
                size: 'xl',
                color: 'success',
                variant: 'boxed',
                spin: true,
                label: 'Status Sukses',
            },
        });

        expect(wrapper.classes()).toContain('bi-check-circle');
        expect(wrapper.classes()).toContain('hcis-icon--xl');
        expect(wrapper.classes()).toContain('hcis-icon--color-success');
        expect(wrapper.classes()).toContain('hcis-icon--variant-boxed');
        expect(wrapper.classes()).toContain('hcis-icon--spin');
        expect(wrapper.attributes('aria-label')).toBe('Status Sukses');
    });

    it('mendukung varian accent, solid, size, dan tombol tutup pada HcisAlert', async () => {
        const wrapper = mount(HcisAlert, {
            props: {
                variant: 'brand',
                appearance: 'accent',
                size: 'sm',
                title: 'Informasi Penting',
                description: 'Deskripsi pesan alert',
                closable: true,
            },
        });

        // Akar komponennya <Transition>; kelasnya ada di div di dalamnya.
        const kotak = wrapper.find('.hcis-alert');
        expect(kotak.classes()).toContain('hcis-alert--brand');
        expect(kotak.classes()).toContain('hcis-alert--appearance-accent');
        expect(kotak.classes()).toContain('hcis-alert--size-sm');
        expect(wrapper.find('.hcis-alert__title').text()).toBe('Informasi Penting');
        expect(wrapper.find('.hcis-alert__desc').text()).toBe('Deskripsi pesan alert');

        // Close button click
        const closeBtn = wrapper.find('.hcis-alert__close');
        await closeBtn.trigger('click');
        expect(wrapper.emitted('close')).toBeTruthy();
    });

    it('mendukung status dot, bentuk kustom, dan kalkulasi inisial pada HcisAvatar', () => {
        const wrapper = mount(HcisAvatar, {
            props: {
                name: 'Ayu Rahmawati',
                size: 'xl',
                shape: 'rounded',
                status: 'online',
            },
        });

        // wrapper.classes() membaca simpul akar, dan akar komponen ini adalah
        // komentar penanda mode — bukan elemennya. Atributnya sendiri tetap
        // diwariskan dengan benar ke elemen tunggal di bawahnya; yang perlu
        // disasar hanyalah elemen itu.
        const avatar = wrapper.find('.hcis-avatar-container');

        expect(avatar.classes()).toContain('hcis-avatar--xl');
        expect(avatar.classes()).toContain('hcis-avatar--shape-rounded');
        expect(wrapper.find('.hcis-avatar__initials').text()).toBe('AR');
        expect(wrapper.find('.hcis-avatar__status--online').exists()).toBe(true);
    });

    it('mendukung clearable, success feedback, dan peringatan batas karakter pada HcisTextarea', async () => {
        const wrapper = mount(HcisTextarea, {
            props: {
                id: 'ta-test',
                label: 'Catatan SOP',
                modelValue: 'Isi catatan pendek',
                maxlength: 20,
                clearable: true,
                success: 'Format valid',
            },
        });

        expect(wrapper.find('textarea').element.value).toBe('Isi catatan pendek');
        expect(wrapper.find('.hcis-field__success').text()).toBe('Format valid');

        const countEl = wrapper.find('.hcis-textarea__count');
        expect(countEl.text()).toBe('18/20');
        expect(countEl.classes()).toContain('is-near-limit');

        // Clear action
        const clearBtn = wrapper.find('.hcis-textarea__clear');
        expect(clearBtn.exists()).toBe(true);
        await clearBtn.trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['']);
        expect(wrapper.emitted('clear')).toBeTruthy();
    });

    it('mendukung varian card, button, size lg, dan sentimen warna success pada HcisCheckbox', () => {
        const wrapper = mount(HcisCheckbox, {
            props: {
                label: 'Modul Produksi',
                description: 'Akses penuh',
                variant: 'card',
                size: 'lg',
                color: 'success',
                modelValue: true,
            },
        });

        expect(wrapper.classes()).toContain('hcis-choice--variant-card');
        expect(wrapper.classes()).toContain('hcis-choice--lg');
        expect(wrapper.classes()).toContain('hcis-choice--color-success');
        expect(wrapper.classes()).toContain('is-checked');
        expect(wrapper.find('.hcis-choice__label').text()).toBe('Modul Produksi');
        expect(wrapper.find('.hcis-choice__hint').text()).toBe('Akses penuh');
    });

    it('mendukung direction horizontal, variant card, dan titik lingkar pada HcisRadio', async () => {
        const wrapper = mount(HcisRadio, {
            props: {
                name: 'test-radio',
                modelValue: 'opsi1',
                direction: 'horizontal',
                variant: 'card',
                options: [
                    { value: 'opsi1', label: 'Opsi 1', hint: 'Keterangan 1' },
                    { value: 'opsi2', label: 'Opsi 2', hint: 'Keterangan 2' },
                ],
            },
        });

        expect(wrapper.classes()).toContain('hcis-radio-group--horizontal');
        const choices = wrapper.findAll('.hcis-choice--radio');
        expect(choices).toHaveLength(2);
        expect(choices[0].classes()).toContain('is-checked');

        const inputs = wrapper.findAll('input[type="radio"]');
        await inputs[1].setValue();
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['opsi2']);
    });

    it('mendukung size xl, insideValue, sentiment info, dan mode indeterminate pada HcisProgress', () => {
        const wrapper = mount(HcisProgress, {
            props: {
                label: 'Progress SOP',
                value: 85,
                size: 'xl',
                insideValue: true,
                sentiment: 'info',
                striped: true,
                animated: true,
            },
        });

        // Sama seperti HcisAvatar: akar komponennya komentar penanda mode.
        const bar = wrapper.find('.hcis-progress');

        expect(bar.classes()).toContain('hcis-progress--xl');
        expect(bar.classes()).toContain('hcis-progress--striped');
        expect(bar.classes()).toContain('hcis-progress--animated');
        expect(wrapper.find('.hcis-progress__fill').classes()).toContain('hcis-progress__fill--info');
        expect(wrapper.find('.hcis-progress__inner-text').text()).toBe('85%');
    });

    it('mendukung clickable, line connector, variant cards, dan custom icon pada HcisSteps', async () => {
        const wrapper = mount(HcisSteps, {
            props: {
                current: 1,
                clickable: true,
                variant: 'cards',
                steps: [
                    { label: 'Langkah 1', icon: 'file-earmark' },
                    { label: 'Langkah 2', caption: 'Sedang Berjalan' },
                    { label: 'Langkah 3' },
                ],
            },
        });

        expect(wrapper.classes()).toContain('hcis-steps-wrapper--variant-cards');
        // Dua perubahan sekaligus pada varian 'cards': itemnya bernama
        // .hcis-steps__card-item, dan 'is-clickable' pada pembungkus
        // digantikan 'is-interactive' PER LANGKAH.
        const items = wrapper.findAll('.hcis-steps__card-item');
        expect(items[0].classes()).toContain('is-interactive');
        expect(items[0].classes()).toContain('is-done');
        expect(items[1].classes()).toContain('is-current');
        expect(items[2].classes()).toContain('is-todo');

        await items[2].trigger('click');
        expect(wrapper.emitted('update:current')[0]).toEqual([2]);
        expect(wrapper.emitted('change')[0]).toEqual([{ step: { label: 'Langkah 3' }, index: 2 }]);
    });

    it('mendukung pembungkus transisi tunggal dan grup pada HcisGerak / Gerak', () => {
        const wrapperSingle = mount(HcisGerak, {
            props: { name: 'slide-up', mode: 'out-in' },
            slots: { default: () => h('div', 'Konten Single') },
        });
        expect(wrapperSingle.html()).toContain('Konten Single');

        const wrapperGroup = mount(Gerak, {
            props: { name: 'slide-up', group: true, tag: 'ul' },
            slots: { default: () => h('li', 'Item 1') },
        });
        expect(wrapperGroup.classes()).toContain('hcis-group');
        expect(wrapperGroup.html()).toContain('Item 1');
    });

    it('mendukung direction, variant, align, dan icon pada HcisDivider', () => {
        const wrapper = mount(HcisDivider, {
            props: {
                label: 'Lampiran',
                icon: 'paperclip',
                direction: 'horizontal',
                variant: 'gradient',
                align: 'left',
                spacing: 'lg',
            },
        });

        expect(wrapper.classes()).toContain('hcis-divider--horizontal');
        expect(wrapper.classes()).toContain('hcis-divider--variant-gradient');
        expect(wrapper.classes()).toContain('hcis-divider--align-left');
        expect(wrapper.classes()).toContain('hcis-divider--lg');
        expect(wrapper.find('.hcis-divider__label').text()).toContain('Lampiran');
        expect(wrapper.findComponent(HcisIcon).exists()).toBe(true);

        const wrapperVertical = mount(HcisDivider, {
            props: { direction: 'vertical', variant: 'dashed' },
        });
        expect(wrapperVertical.classes()).toContain('hcis-divider--vertical');
        expect(wrapperVertical.classes()).toContain('hcis-divider--variant-dashed');
        expect(wrapperVertical.attributes('aria-orientation')).toBe('vertical');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DashboardBlokPermit from './DashboardBlokPermit.vue';
import DashboardBlokTren from './DashboardBlokTren.vue';

const stubs = {
    HcisCard: { template: '<section><slot /></section>' },
    HcisEmptyState: { props: ['title', 'description'], template: '<div data-test="kosong">{{ title }}</div>' },
    HcisSkeleton: true,
    HcisBadge: { template: '<span><slot /></span>' },
};

const stubsTren = {
    ...stubs,
    HcisAlert: { props: ['title', 'description'], template: '<div data-test="penanda">{{ title }} {{ description }}</div>' },
    HcisChart: { props: ['series', 'categories', 'type'], template: '<div data-test="chart" />' },
};

describe('DashboardBlokTren', () => {
    it('memisahkan seri transaksi dan orang', () => {
        const wrapper = mount(DashboardBlokTren, {
            props: {
                tren: [
                    { tanggal: '2026-08-23', transaksi: 2, orang: 6 },
                    { tanggal: '2026-08-24', transaksi: 1, orang: 1 },
                ],
                sebaranJam: [],
                dataTipis: false,
            },
            global: { stubs: stubsTren },
        });

        expect(wrapper.vm.seriTren.map((s) => s.name)).toEqual(['Transaksi', 'Orang']);
        expect(wrapper.vm.seriTren[1].data).toEqual([6, 1]);
    });

    it('memasang penanda ketika data tipis, tanpa menyembunyikan grafiknya', () => {
        const wrapper = mount(DashboardBlokTren, {
            props: { tren: [{ tanggal: '2026-08-24', transaksi: 1, orang: 1 }], sebaranJam: [], dataTipis: true },
            global: { stubs: stubsTren },
        });

        expect(wrapper.find('[data-test="penanda"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-test="chart"]').length).toBe(2);
    });
});

const stubsPermit = {
    ...stubs,
    HcisChart: {
        props: ['series', 'categories', 'type', 'title'],
        template: '<div data-test="chart">{{ title }}</div>',
    },
};

describe('DashboardBlokPermit', () => {
    it('memisahkan tiga seri keputusan permit', () => {
        const wrapper = mount(DashboardBlokPermit, {
            props: {
                kategori: [{ kategori: 'HIJAU', jumlah: 6 }],
                tren: [
                    { tanggal: '2026-08-24', diajukan: 6, disetujui: 4, ditolak: 2 },
                    { tanggal: '2026-08-25', diajukan: 2, disetujui: 0, ditolak: 0 },
                ],
            },
            global: { stubs: stubsPermit },
        });

        expect(wrapper.vm.seriTren.map((s) => s.name)).toEqual(['Diajukan', 'Disetujui', 'Ditolak']);
        expect(wrapper.vm.seriTren[2].data).toEqual([2, 0]);
    });

    it('tidak memampatkan hari bernilai nol', () => {
        // Server mengirim setiap hari pada rentang, termasuk yang kosong.
        // Menyaringnya di sini membuat dua pengajuan berjarak sepekan terbaca
        // seolah terjadi berturut-turut.
        const tren = [
            { tanggal: '2026-08-12', diajukan: 0, disetujui: 0, ditolak: 0 },
            { tanggal: '2026-08-13', diajukan: 1, disetujui: 0, ditolak: 0 },
            { tanggal: '2026-08-14', diajukan: 0, disetujui: 0, ditolak: 0 },
        ];
        const wrapper = mount(DashboardBlokPermit, {
            props: { kategori: [], tren },
            global: { stubs: stubsPermit },
        });

        expect(wrapper.vm.tanggalTren).toHaveLength(3);
        expect(wrapper.vm.seriTren[0].data).toEqual([0, 1, 0]);
    });

    it('merender dua chart, tanpa daftar akan-kadaluarsa', () => {
        const wrapper = mount(DashboardBlokPermit, {
            props: { kategori: [{ kategori: 'HIJAU', jumlah: 6 }], tren: [] },
            global: { stubs: stubsPermit },
        });

        expect(wrapper.findAll('[data-test="chart"]')).toHaveLength(2);
        expect(wrapper.text().toLowerCase()).not.toContain('kadaluarsa');
    });
});

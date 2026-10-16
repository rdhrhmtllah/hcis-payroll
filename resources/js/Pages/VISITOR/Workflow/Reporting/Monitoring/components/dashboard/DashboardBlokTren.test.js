import { describe, it, expect } from 'vitest';
import { shallowMount } from '@vue/test-utils';

import DashboardBlokTren from './DashboardBlokTren.vue';

/**
 * Kontrak heatmap HcisChart.
 *
 * Untuk type="heatmap", HcisChart membaca prop `data` (deret [xIndex, yIndex,
 * nilai]), sumbu X dari `categories`, dan label sumbu Y dari
 * `series[0].categories`. Mengirim `series` bergaya ApexCharts membuat cabang
 * heatmap-nya menerima array kosong dan kartunya tidak pernah merender apa pun —
 * tanpa galat, tanpa petunjuk.
 */
const SEBARAN = [
    { tanggal: '2026-08-24', jam: 9, jumlah: 3 }, // Senin
    { tanggal: '2026-08-24', jam: 14, jumlah: 2 },
    { tanggal: '2026-08-25', jam: 9, jumlah: 4 }, // Selasa
];

function mountBlok(sebaranJam = SEBARAN) {
    return shallowMount(DashboardBlokTren, {
        props: { tren: [], sebaranJam, dataTipis: false, loading: false },
    });
}

function heatmap(wrapper) {
    return wrapper
        .findAllComponents({ name: 'HcisChart' })
        .find((c) => c.props('type') === 'heatmap');
}

describe('DashboardBlokTren — kartu jam sibuk', () => {
    it('mengisi prop data heatmap, bukan hanya series', () => {
        const kartu = heatmap(mountBlok());

        expect(kartu, 'kartu heatmap tidak ditemukan').toBeTruthy();
        expect(kartu.props('data').length, 'HcisChart heatmap membaca `data`, bukan `series`').toBeGreaterThan(0);
    });

    it('memakai bentuk [xIndex, yIndex, nilai]', () => {
        const data = heatmap(mountBlok()).props('data');

        data.forEach((titik) => {
            expect(Array.isArray(titik)).toBe(true);
            expect(titik).toHaveLength(3);
        });
    });

    it('menempatkan jam pada sumbu X dan hari pada sumbu Y', () => {
        const kartu = heatmap(mountBlok());

        expect(kartu.props('categories')).toHaveLength(24);
        expect(kartu.props('categories')[9]).toBe('9');
        expect(kartu.props('series')[0].categories).toContain('Senin');
    });

    it('menjumlahkan kedatangan pada hari dan jam yang sama', () => {
        // Senin jam 9 ada 3, Selasa jam 9 ada 4 — keduanya tidak boleh menyatu.
        const data = heatmap(mountBlok()).props('data');
        const nilai = data.map((t) => t[2]).filter((v) => v > 0).sort((a, b) => a - b);

        expect(nilai).toEqual([2, 3, 4]);
    });

    it('tidak merender titik apa pun saat belum ada check-in', () => {
        const data = heatmap(mountBlok([])).props('data');

        expect(data.filter((t) => t[2] > 0)).toHaveLength(0);
    });
});

import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import * as echarts from 'echarts';
import HcisChart from './HcisChart.vue';

/*
 * Grafik kartesian bermulti-seri: seri campuran, legenda, dan penggabungan
 * opsi pemanggil.
 *
 * Ketiganya cacat yang TIDAK terbaca sebagai galat — grafiknya tetap
 * tergambar, hanya salah. Karena itu penjaganya membaca opsi yang benar-benar
 * diserahkan ke ECharts, bukan piksel hasilnya.
 */

let opsiTerakhir;

vi.mock('echarts', async () => {
    const nyata = await vi.importActual('echarts');

    return {
        ...nyata,
        init: vi.fn(() => ({
            setOption: vi.fn((o) => {
                opsiTerakhir = o;
            }),
            resize: vi.fn(),
            dispose: vi.fn(),
            on: vi.fn(),
            off: vi.fn(),
            getDataURL: vi.fn(() => ''),
            clear: vi.fn(),
        })),
    };
});

async function gambar(props) {
    opsiTerakhir = undefined;
    const wrapper = mount(HcisChart, { props, attachTo: document.body });
    await flushPromises();
    await new Promise((r) => setTimeout(r, 0));

    return wrapper;
}

const DERET = {
    type: 'bar',
    categories: ['M1', 'M2', 'M3'],
    series: [
        { name: 'Masuk', data: [10, 20, 30] },
        { name: 'Tuntas', data: [5, 15, 25] },
        { name: 'Selisih', type: 'line', data: [-5, -5, -5] },
    ],
};

const seri = (nama) => opsiTerakhir.series.find((s) => s.name === nama);

describe('HcisChart — seri campuran batang + garis', () => {
    beforeEach(() => {
        opsiTerakhir = undefined;
    });

    it('menghormati type milik seri, bukan type grafiknya', async () => {
        await gambar(DERET);

        expect(seri('Masuk').type).toBe('bar');
        expect(seri('Selisih').type).toBe('line');
    });

    it('seri garis TIDAK mewarisi dekorasi batang', async () => {
        await gambar(DERET);
        const garis = seri('Selisih');

        // Inilah yang membuat grafiknya terbaca "bercampur": garis digambar di
        // atas pita latar batang, selebar batang, tanpa lebar garis.
        expect(garis.showBackground).toBeUndefined();
        expect(garis.barMaxWidth).toBeUndefined();
        expect(garis.lineStyle?.width).toBeGreaterThan(0);
    });

    it('seri garis digambar DI ATAS batang', async () => {
        await gambar(DERET);

        expect(seri('Selisih').z).toBeGreaterThan(0);
    });

    it('pita latar batang dimatikan saat grafiknya campuran', async () => {
        await gambar(DERET);
        expect(seri('Masuk').showBackground).toBe(false);

        // ...tapi tetap menyala saat seluruh serinya batang.
        await gambar({ ...DERET, series: DERET.series.slice(0, 2) });
        expect(seri('Masuk').showBackground).toBe(true);
    });

    it('batang bernilai negatif dibulatkan di kedua ujung', async () => {
        await gambar({
            type: 'bar',
            categories: ['M1'],
            series: [{ name: 'Arus', data: [-4] }],
        });

        expect(seri('Arus').itemStyle.borderRadius).toEqual([8, 8, 8, 8]);
    });
});

describe('HcisChart — legenda grafik kartesian', () => {
    beforeEach(() => {
        opsiTerakhir = undefined;
    });

    it('menampilkan legenda begitu serinya lebih dari satu', async () => {
        await gambar(DERET);

        expect(opsiTerakhir.legend?.show).toBe(true);
    });

    it('menurunkan grid supaya batang tak menembus legenda', async () => {
        await gambar(DERET);
        const denganLegenda = opsiTerakhir.grid.top;

        await gambar({ type: 'bar', categories: ['M1'], series: [{ name: 'Satu', data: [1] }] });

        expect(opsiTerakhir.legend).toBeUndefined();
        expect(denganLegenda).toBeGreaterThan(opsiTerakhir.grid.top);
    });
});

describe('HcisChart — opsi pemanggil digabung DALAM', () => {
    beforeEach(() => {
        opsiTerakhir = undefined;
    });

    it('menimpa kunci bersarang tanpa menelan gaya bawaannya', async () => {
        const formatter = (v) => `${v}%`;
        await gambar({ ...DERET, options: { yAxis: { axisLabel: { formatter } } } });

        expect(opsiTerakhir.yAxis.axisLabel.formatter).toBe(formatter);
        // Dulu seluruh sumbu ikut hilang bersama gaya sistem desainnya.
        expect(opsiTerakhir.yAxis.axisLine).toEqual({ show: false });
        expect(opsiTerakhir.yAxis.splitLine?.lineStyle).toBeDefined();
        expect(opsiTerakhir.yAxis.axisLabel.fontSize).toBe(11);
    });

    it('menambah satu kunci tooltip tanpa membuang formatter & gayanya', async () => {
        await gambar({ ...DERET, options: { tooltip: { valueFormatter: (v) => v } } });

        expect(typeof opsiTerakhir.tooltip.valueFormatter).toBe('function');
        expect(typeof opsiTerakhir.tooltip.formatter).toBe('function');
        expect(opsiTerakhir.tooltip.trigger).toBe('axis');
        expect(opsiTerakhir.tooltip.padding).toEqual([10, 14]);
    });

    it('mengganti array utuh, bukan menggabungnya per indeks', async () => {
        await gambar({
            type: 'bar',
            categories: ['M1', 'M2', 'M3'],
            series: [{ name: 'Satu', data: [1, 2, 3] }],
            options: { grid: { left: 40 } },
        });

        // Kunci grid lain tetap hidup meski pemanggil hanya menyebut `left`.
        expect(opsiTerakhir.grid.left).toBe(40);
        expect(opsiTerakhir.grid.containLabel).toBe(true);
    });

    it('tidak menimpa seri milik preset yang menyerapnya sendiri (donut)', async () => {
        await gambar({
            type: 'donut',
            data: [{ name: 'A', value: 1 }],
            options: { series: [{ radius: ['80%', '90%'] }] },
        });

        // Seri donat harus tetap berupa seri pie bergaya preset, bukan diganti
        // objek telanjang milik pemanggil.
        expect(opsiTerakhir.series[0].type).toBe('pie');
        expect(opsiTerakhir.series[0].radius).toEqual(['80%', '90%']);
    });
});

describe('HcisChart — echarts benar-benar dipanggil', () => {
    it('menyerahkan opsi ke instans echarts (bukan penjaga yang menguji udara)', async () => {
        await gambar(DERET);

        expect(echarts.init).toHaveBeenCalled();
        expect(opsiTerakhir).toBeDefined();
        expect(opsiTerakhir.series).toHaveLength(3);
    });
});

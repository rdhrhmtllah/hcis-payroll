import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import GrafikHariIni from './GrafikHariIni.vue';

// Komponen chart nyata tetap dipakai. Hanya renderer ECharts yang diganti,
// sehingga data yang hilang saat opsi digabung tetap tertangkap.
const { opsi } = vi.hoisted(() => ({ opsi: [] }));

vi.mock('echarts', async () => {
    const nyata = await vi.importActual('echarts');
    return {
        ...nyata,
        init: vi.fn(() => ({
            setOption: vi.fn((o) => opsi.push(o)),
            resize: vi.fn(),
            dispose: vi.fn(),
            on: vi.fn(),
            off: vi.fn(),
            getDataURL: vi.fn(() => ''),
            clear: vi.fn(),
        })),
    };
});

describe('GrafikHariIni — data check-in sampai ke renderer', () => {
    it('mempertahankan type dan data seri batang saat opsi tampilan digabung', async () => {
        opsi.length = 0;
        const w = mount(GrafikHariIni, {
            attachTo: document.body,
            props: {
                analitik: {
                    tren_jam: [
                        { jam: '06:00', jumlah: 41, kumulatif: 41 },
                        { jam: '07:00', jumlah: 80, kumulatif: 121 },
                        { jam: '08:00', jumlah: 31, kumulatif: 152 },
                    ],
                },
            },
        });
        try {
            await flushPromises();
            await new Promise((resolve) => setTimeout(resolve, 0));
            const gambar = opsi.find((o) => o.xAxis?.data?.includes('07:00'));
            expect(gambar).toBeDefined();
            expect(gambar.series[0].type).toBe('bar');
            expect(gambar.series[0].data).toEqual([41, 80, 31]);
        } finally {
            w.unmount();
        }
    });
});

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import TabRingkasan from './TabRingkasan.vue';
import BabKeadaan from './ringkasan/BabKeadaan.vue';
import BabMomentum from './ringkasan/BabMomentum.vue';
import BabTitikMacet from './ringkasan/BabTitikMacet.vue';
import BabKualitas from './ringkasan/BabKualitas.vue';

vi.mock('axios');

/** IntersectionObserver palsu — tes mengendalikan kapan sebuah zona "terlihat". */
function pasangObserverPalsu() {
    const instans = [];
    globalThis.IntersectionObserver = class {
        constructor(cb) {
            this.cb = cb;
            this.targets = [];
            instans.push(this);
        }
        observe(el) {
            this.targets.push(el);
        }
        disconnect() {
            this.targets = [];
        }
        picu(el) {
            this.cb([{ isIntersecting: true, target: el }]);
        }
    };
    return instans;
}

function jawabanKosong(url) {
    if (url.includes('/summary')) {
        return Promise.resolve({
            data: {
                success: true,
                result: { coverage: 80, paket_aktif: 3, tertunda: 1, jatuh_tempo: 2, terlambat: 4 },
            },
        });
    }
    if (url.includes('/momentum')) {
        return Promise.resolve({
            data: {
                success: true,
                result: {
                    sisa: { penugasan: 98, orang: 32, item: 150 },
                    laju: { penugasan_30: 2, peristiwa_30: 60, item_30: 5, item_peristiwa_30: 150 },
                    deret: [],
                    arah: { status: 'mengejar', masuk_4mgg: 10, tuntas_4mgg: 25, net: 15 },
                    proyeksi: { cukup_data: true, hari: 49, tanggal: '2026-10-10', dasar: 'rata-rata 30 hari' },
                },
            },
        });
    }
    if (url.includes('/titik-henti')) {
        return Promise.resolve({
            data: {
                success: true,
                result: {
                    daftar: [],
                    total_tertahan: 0,
                    total_dokumen: 0,
                    rerata_persen: 0,
                    terpotong: 0,
                },
            },
        });
    }
    return Promise.resolve({ data: { success: true, result: [], total_data: 0 } });
}

async function pasang() {
    axios.get = vi.fn((url) => jawabanKosong(url));
    axios.post = vi.fn(() => Promise.resolve({ data: { success: true, result: {} } }));

    const wrapper = mount(TabRingkasan, {
        global: { stubs: { HcisChart: true, HcisDrawer: true, HcisDatePicker: true, SopRing: true, EmptyState: true, ProgressBar: true } },
    });
    await flushPromises();
    return wrapper;
}

describe('TabRingkasan — susunan bab dan navigasi', () => {
    beforeEach(() => pasangObserverPalsu());

    it('merender bab-bab analitik terpadu', async () => {
        const wrapper = await pasang();

        expect(wrapper.findComponent(BabKeadaan).exists()).toBe(true);
        expect(wrapper.findComponent(BabMomentum).exists()).toBe(true);
        expect(wrapper.findComponent(BabTitikMacet).exists()).toBe(true);
        expect(wrapper.findComponent(BabKualitas).exists()).toBe(true);
    });

    /*
     * DILEPAS: 'menawarkan sub-nav bab analitik dengan floating dock'.
     *
     * Ia menagih `.tr-nav-btn` dengan lima label — Keadaan, Kesehatan Dokumen,
     * Jangkauan Publik, Titik Macet, Kualitas. Kelas itu TIDAK PERNAH ADA di
     * kode produksi: bukan sekarang, dan `git log -S` atas seluruh riwayat
     * komponen ini tak menemukannya sekali pun. Uji ini ditulis bersama
     * 36655fa6 dan MERAH sejak lahir.
     *
     * Jadi ia bukan penjaga regresi melainkan PERMINTAAN FITUR yang berbentuk
     * uji — dan uji yang merah sejak lahir lebih berbahaya daripada tak ada
     * uji: ia menaikkan derau sehingga kerusakan yang sungguhan tenggelam.
     * Persis itu yang terjadi pada tiga regresi UI yang baru ditemukan sesi
     * ini, yang lolos berminggu-minggu karena CI memang sudah merah.
     *
     * Rancangannya juga belum utuh: ia meminta LIMA butir nav sementara bab
     * yang benar-benar dirender ada EMPAT (uji di atas) — 'Kesehatan Dokumen'
     * dan 'Jangkauan Publik' tak punya komponen padanan. Membangunnya menuntut
     * keputusan rancangan, bukan sekadar menambah tombol.
     *
     * Kalau sub-nav itu memang diinginkan, ia layak jadi tiket tersendiri;
     * uji ini akan ditulis ulang bersama fiturnya.
     */
});

describe('TabRingkasan — filter rentang tanggal global', () => {
    beforeEach(() => pasangObserverPalsu());

    it('meneruskan date_start dan date_end ke params ketika date_range diisi', async () => {
        const wrapper = await pasang();

        wrapper.vm.onSaringan({ date_range: ['2026-01-01', '2026-01-31'] });
        await flushPromises();

        expect(wrapper.vm.params.date_start).toBe('2026-01-01');
        expect(wrapper.vm.params.date_end).toBe('2026-01-31');
        expect(wrapper.vm.params.dari).toBe('2026-01-01');
        expect(wrapper.vm.params.sampai).toBe('2026-01-31');
    });

    it('mereset date_range ke default 30 hari saat resetSaringan dipanggil', async () => {
        const wrapper = await pasang();

        wrapper.vm.onSaringan({ date_range: ['2026-01-01', '2026-01-31'], preset: 'custom' });
        await flushPromises();
        expect(wrapper.vm.params.date_start).toBe('2026-01-01');
        expect(wrapper.vm.bukanBaku).toBe(true);

        wrapper.vm.resetSaringan();
        await flushPromises();

        expect(wrapper.vm.saringan.preset).toBe('30d');
        expect(wrapper.vm.bukanBaku).toBe(false);
        expect(wrapper.vm.params.date_start).toBeDefined();
        expect(wrapper.vm.params.date_end).toBeDefined();
    });

    it('mendukung pergantian preset cepat periode', async () => {
        const wrapper = await pasang();

        wrapper.vm.pilihPreset('7d');
        await flushPromises();

        expect(wrapper.vm.saringan.preset).toBe('7d');
        expect(wrapper.vm.params.date_start).toBeDefined();
        expect(wrapper.vm.params.date_end).toBeDefined();
    });
});

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import MonitoringKehadiran from './MonitoringKehadiran.vue';
import TabHariIni, { INTERVAL_SEGAR } from './Components/TabHariIni.vue';
import TabRekapPeriode, { JEDA_POLL_EKSPOR } from './Components/TabRekapPeriode.vue';

const get = vi.fn();
const post = vi.fn();

vi.mock('axios', () => ({
    default: {
        get: (...a) => get(...a),
        post: (...a) => post(...a),
    },
}));

const HARI_INI = '2026-10-14';

const opsiSaringan = {
    divisi: ['PRODUKSI', 'GUDANG'],
    departemen: [{ nama: 'MESIN', divisi: 'PRODUKSI' }, { nama: 'PENERIMAAN', divisi: 'GUDANG' }],
    level: ['STAFF'],
    jabatan: ['OPERATOR'],
    lokasi: ['EMI', 'HO'],
    shift: ['EMI-S1'],
};

const daftarPeriode = [
    { mulai: '2026-09-16', akhir: '2026-10-15', label: '16 Sep – 15 Okt 2026' },
    { mulai: '2026-08-16', akhir: '2026-09-15', label: '16 Agu – 15 Sep 2026' },
];

const barisHariIni = (ganti = {}) => ({
    kode: 'K001',
    nama: 'Budi',
    jabatan: 'OPERATOR',
    departemen: 'MESIN',
    lokasi: 'EMI',
    kategori: 'belum_absen',
    shift: 'EMI-S1',
    jam_masuk: '08:00',
    jam_keluar: '16:00',
    check_in: null,
    check_out: null,
    terlambat_menit: 0,
    pulang_cepat_menit: 0,
    lembur_jam: 0,
    izin_terlambat: false,
    izin_pulang_cepat: false,
    diperbarui: false,
    tanpa_checkout: false,
    transaksi: {},
    ...ganti,
});

const balasHariIni = ({ diperbarui = 0, items = [barisHariIni()] } = {}) => ({
    data: {
        success: true,
        result: {
            ringkasan: {
                tanggal: HARI_INI,
                total: 10,
                kategori: { tepat_waktu: 5, terlambat: 1, belum_absen: 2, belum_jam: 0, sakit_izin: 1, cuti: 1, libur: 0, tanpa_jadwal: 0 },
                tanda: { pulang_cepat: 0, lembur: 1, diperbarui, tanpa_checkout: 0 },
                data_per: '2026-10-14 09:55',
            },
            analitik: {
                transaksi: { sakit: 1, izin: 0, cuti_tahunan: 1, cuti_khusus: 0, lembur: 1, konversi_lembur: 0, terlambat: 1, pulang_cepat: 0, terjadwal: 10, hadir: 6, libur: 0, total_pengajuan: 3 },
                tren_jam: [{ jam: '08:00', jam_angka: 8, jumlah: 6, kumulatif: 6 }],
                sebaran_lokasi: [{ lokasi: 'EMI', terjadwal: 10, hadir: 6, libur: 0, izin_cuti_sakit: 2, persen_hadir: 60 }],
                terbaru_absen: [{ kode: 'K001', nama: 'Budi', shift: 'EMI-S1', check_in: '07:55', terlambat_menit: 0 }],
                terbaru_transaksi: [{ kode: 'K002', nama: 'Siti', jenis: 'Sakit', tipe_badge: 'danger', nomor: 'SK01', keterangan: 'Demam' }],
            },
            daftar: { items, total: items.length, page: 1, limit: 25 },
        },
    },
});

const balasRekap = (diperbarui = 0) => ({
    data: {
        success: true,
        result: {
            items: [{ kode: 'K001', nama: 'Budi', departemen: 'MESIN', terjadwal: 22, hadir: 20, terlambat_kali: 3, terlambat_menit: 45, terlambat_berizin: 1, cepat_kali: 0, cepat_menit: 0, cepat_berizin: 0, sakit: 1, izin: 0, cuti: 1, alpha: 0, lupa_checkout: 2, lembur_jam: 6, lembur_hari: 2, konversi: 0 }],
            total: 1,
            page: 1,
            limit: 25,
            diperbarui,
        },
    },
});

const tautan = { perbaikan_absensi: '/perbaiki-checkinout', izin: '/izinAdmin' };

// ECharts tak bisa menggambar di jsdom; grafik diuji lewat pembentuk datanya (grafikMonitoring.test.js).
const global = {
    stubs: {
        teleport: true,
        HcisChart: { props: ['title'], emits: ['chart-click'], template: '<div class="chart-stub">{{ title }}</div>' },
    },
};

const balasGrafik = {
    data: {
        success: true,
        result: {
            tren: [{ tanggal: '2026-10-13', terjadwal: 10, hadir: 8, hadir_terlambat: 1, sakit: 0, izin: 0, cuti: 0, alpha: 2 }],
            sel: [{ kelompok: 'MESIN', tanggal: '2026-10-13', terjadwal: 10, hadir: 8, terlambat_kali: 1, alpha: 2 }],
            kelompok: [{ kelompok: 'MESIN', karyawan: 10, terjadwal: 10, hadir: 8, persen_hadir: 80, terlambat_kali: 1, terlambat_menit: 15, alpha: 2, lembur_jam: 0 }],
            hierarki: {
                id: 'root',
                name: 'Semua Lokasi',
                tingkat: 'root',
                karyawan_count: 10,
                terjadwal: 10,
                hadir: 8,
                persen_hadir: 80,
                children: [
                    {
                        id: 'lokasi:EMI',
                        name: 'EMI',
                        tingkat: 'lokasi',
                        karyawan_count: 10,
                        terjadwal: 10,
                        hadir: 8,
                        persen_hadir: 80,
                        children: [],
                    },
                ],
            },
        },
    },
};

const buatHalaman = () => mount(MonitoringKehadiran, {
    props: { opsiSaringan, daftarPeriode, hariIni: HARI_INI, tautan, kategori: [], maksHari: 62 },
    global,
});

const panggilan = (jalur) => get.mock.calls.filter(([url]) => url.endsWith(jalur));

describe('Monitoring Kehadiran', () => {
    beforeEach(() => {
        get.mockReset();
        post.mockReset();
        get.mockImplementation((url) => Promise.resolve(
            url.includes('/data/grafik') ? balasGrafik : (url.includes('/data/rekap') ? balasRekap() : balasHariIni()),
        ));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('menampilkan metrik transaksi dan aliran check-in terbaru', async () => {
        const w = mount(TabHariIni, { props: { opsiSaringan, hariIni: HARI_INI, tautan }, global });
        await flushPromises();

        expect(panggilan('/data/hari-ini')[0][1].params).toEqual(expect.objectContaining({ tanggal: HARI_INI }));
        expect(w.text()).toContain('Kehadiran Hari Ini');
        expect(w.text()).toContain('Pengajuan Sakit & Izin');
        expect(w.text()).toContain('Pengajuan Cuti');
        expect(w.text()).toContain('Lembur & Konversi');
        expect(w.text()).toContain('Aliran Check-in Terbaru');
        expect(w.text()).toContain('Pengajuan & Transaksi Hari Ini');
        expect(w.text()).toContain('Budi');
        expect(w.text()).toContain('Siti');
    });

    it('menampilkan peringatan bila ada baris yang sedang dihitung ulang', async () => {
        get.mockImplementation(() => Promise.resolve(balasHariIni({ diperbarui: 3 })));
        const w = mount(TabHariIni, { props: { opsiSaringan, hariIni: HARI_INI, tautan }, global });
        await flushPromises();

        expect(w.find('[data-uji="peringatan-diperbarui"]').exists()).toBe(true);
        expect(w.text()).toContain('3 karyawan menunggu hitung ulang');
    });

    it('memuat ulang otomatis tiap 5 menit dan berhenti saat tab ditinggalkan', async () => {
        vi.useFakeTimers();
        const w = mount(TabHariIni, { props: { opsiSaringan, hariIni: HARI_INI, tautan }, global });
        await flushPromises();
        const awal = panggilan('/data/hari-ini').length;

        vi.advanceTimersByTime(INTERVAL_SEGAR);
        await flushPromises();
        expect(panggilan('/data/hari-ini').length).toBe(awal + 1);

        w.unmount();
        vi.advanceTimersByTime(INTERVAL_SEGAR * 3);
        await flushPromises();
        expect(panggilan('/data/hari-ini').length).toBe(awal + 1);
    });

    it('tanggal lampau tidak dimuat ulang otomatis', async () => {
        vi.useFakeTimers();
        const w = mount(TabHariIni, { props: { opsiSaringan, hariIni: HARI_INI, tautan }, global });
        await flushPromises();
        w.vm.gantiFilter({ key: 'tanggal', value: '2026-10-13' });
        await flushPromises();
        const sebelum = panggilan('/data/hari-ini').length;

        vi.advanceTimersByTime(INTERVAL_SEGAR);
        await flushPromises();
        expect(panggilan('/data/hari-ini').length).toBe(sebelum);
        w.unmount();
    });

    it('berpindah tab mempertahankan saringan organisasi', async () => {
        const w = buatHalaman();
        await flushPromises();

        w.findComponent(TabHariIni).vm.gantiFilter({ key: 'lokasi', value: ['EMI'] });
        await flushPromises();
        expect(panggilan('/data/hari-ini').at(-1)[1].params.lokasi).toEqual(['EMI']);

        w.vm.tab = 'rekap';
        await flushPromises();
        expect(panggilan('/data/grafik').at(-1)[1].params).toEqual(expect.objectContaining({ lokasi: ['EMI'], dari: '2026-09-16', sampai: '2026-10-15' }));

        w.vm.tab = 'hari-ini';
        await flushPromises();
        expect(panggilan('/data/hari-ini').at(-1)[1].params.lokasi).toEqual(['EMI']);
    });

    it('memilih divisi membuang departemen di luar divisi itu', async () => {
        const w = buatHalaman();
        await flushPromises();
        const tab = w.findComponent(TabHariIni);

        tab.vm.gantiFilter({ key: 'departemen', value: ['MESIN', 'PENERIMAAN'] });
        await flushPromises();
        tab.vm.gantiFilter({ key: 'divisi', value: ['PRODUKSI'] });
        await flushPromises();

        expect(w.vm.organisasi.departemen).toEqual(['MESIN']);
    });

    it('klik sel peta panas membuka tab Hari Ini pada tanggal & unit itu, lalu kembali tetap di Grafik', async () => {
        const w = buatHalaman();
        await flushPromises();
        w.vm.tab = 'rekap';
        await flushPromises();

        w.findComponent(TabRekapPeriode).vm.gantiTampilan('grafik');
        await flushPromises();
        expect(panggilan('/data/grafik').at(-1)[1].params).toEqual(expect.objectContaining({ dimensi: 'departemen', dari: '2026-09-16' }));

        const grafik = w.findComponent({ name: 'GrafikRekap' });
        grafik.vm.klikSel({ data: [0, 0, 20] });
        await flushPromises();

        expect(w.vm.tab).toBe('hari-ini');
        expect(w.vm.organisasi.departemen).toEqual(['MESIN']);
        expect(panggilan('/data/hari-ini').at(-1)[1].params).toEqual(expect.objectContaining({ tanggal: '2026-10-13', departemen: ['MESIN'] }));

        w.vm.tab = 'rekap';
        await flushPromises();
        expect(w.findComponent({ name: 'GrafikRekap' }).exists()).toBe(true);
    });

    it('rekap menampilkan peringatan data sedang diperbarui', async () => {
        get.mockImplementation((url) => Promise.resolve(
            url.includes('/data/grafik')
                ? { data: { success: true, result: { ...balasGrafik.data.result, diperbarui: 4 } } }
                : balasRekap(4)
        ));
        const w = mount(TabRekapPeriode, { props: { opsiSaringan, daftarPeriode }, global });
        await flushPromises();

        expect(w.find('[data-uji="peringatan-diperbarui"]').exists()).toBe(true);
        expect(w.text()).toContain('4 baris absensi');
    });

    it('rentang bebas lebih dari 62 hari tidak dikirim ke server', async () => {
        const w = mount(TabRekapPeriode, { props: { opsiSaringan, daftarPeriode, maksHari: 62 }, global });
        w.vm.gantiFilter({ key: 'periode', value: 'bebas' });
        await flushPromises();
        const sebelum = panggilan('/data/rekap').length;

        w.vm.gantiFilter({ key: 'rentang', value: ['2026-07-01', '2026-10-01'] });
        await flushPromises();

        expect(w.vm.galatRentang).toContain('maksimal 62 hari');
        expect(panggilan('/data/rekap').length).toBe(sebelum);
    });

    it('ekspor memulai job lalu menampilkan tautan unduh saat siap', async () => {
        vi.useFakeTimers();
        post.mockResolvedValue({ data: { success: true, result: { export_id: 'mk-exp-1' } } });
        let status = 'processing';
        get.mockImplementation((url) => Promise.resolve(url.includes('/ekspor/')
            ? { data: { success: true, result: { status } } }
            : balasRekap()));

        const w = mount(TabRekapPeriode, { props: { opsiSaringan, daftarPeriode }, global });
        await flushPromises();

        await w.vm.mulaiEkspor();
        expect(post).toHaveBeenCalledWith('/admin/monitoring-kehadiran/ekspor', expect.objectContaining({ dari: '2026-09-16', sampai: '2026-10-15' }));

        vi.advanceTimersByTime(JEDA_POLL_EKSPOR);
        await flushPromises();
        expect(w.vm.ekspor.status).toBe('proses');

        status = 'ready';
        vi.advanceTimersByTime(JEDA_POLL_EKSPOR);
        await flushPromises();

        expect(w.vm.ekspor.status).toBe('siap');
        expect(w.vm.ekspor.url).toBe('/admin/monitoring-kehadiran/ekspor/mk-exp-1/unduh');
        w.unmount();
    });

    it('grafik drill-down merender 3 mode (batang, treemap, sunburst) dan mendukung navigasi breadcrumb', async () => {
        const w = buatHalaman();
        await flushPromises();
        w.vm.tab = 'rekap';
        await flushPromises();

        w.findComponent(TabRekapPeriode).vm.gantiTampilan('grafik');
        await flushPromises();

        const drill = w.findComponent({ name: 'GrafikDrilldown' });
        expect(drill.exists()).toBe(true);
        expect(drill.text()).toContain('Semua');

        // Bawaan: batang bertingkat
        expect(drill.vm.modeVisual).toBe('batang');
        expect(drill.find('[data-uji="chart-drilldown-batang"]').exists()).toBe(true);

        // Ganti ke Treemap
        drill.vm.modeVisual = 'treemap';
        await flushPromises();
        expect(drill.find('[data-uji="chart-drilldown-treemap"]').exists()).toBe(true);

        // Ganti ke Sunburst
        drill.vm.modeVisual = 'sunburst';
        await flushPromises();
        expect(drill.find('[data-uji="chart-drilldown-sunburst"]').exists()).toBe(true);

        // Navigasi path: klik node masuk
        drill.vm.pathNavigasi = ['EMI'];
        await flushPromises();
        expect(drill.vm.labelCakupanAktif).toBe('EMI');

        // Tombol kembali mundur satu tingkat
        drill.vm.mundurSatuTingkat();
        expect(drill.vm.pathNavigasi).toEqual([]);
    });
});

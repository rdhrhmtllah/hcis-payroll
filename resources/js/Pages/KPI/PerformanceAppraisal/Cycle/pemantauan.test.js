import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PemantauanPage from './pemantauan.vue';
import { HcisPagination } from '@/components/ui';
import paApi from '../api/paApi.js';

/* Respons monitoring sejak 7 Okt 2026 membawa blok `peserta` (halaman per KARYAWAN, progres dari
   server). Mock lama yang hanya punya `penugasan.baris` dilengkapi di sini dengan bentuk yang sama
   seperti api/fixtures/siklus-monitoring.json. */
function denganPeserta(respons, progres = {}) {
    const baris = respons?.penugasan?.baris || [];
    const per = new Map();
    baris.forEach((r) => {
        if (!per.has(r.Id_PA_Participant)) {
            per.set(r.Id_PA_Participant, {
                Id_PA_Participant: r.Id_PA_Participant, Id_PA_Result: null, Kode_Karyawan: r.Kode_Karyawan ?? null,
                Nama_Karyawan_Saat_Cycle: r.Nama_Karyawan_Saat_Cycle, Nama_Divisi_Saat_Cycle: r.Nama_Divisi_Saat_Cycle ?? null,
                Nama_Level_Saat_Cycle: r.Nama_Level_Saat_Cycle ?? null, ID_Level_Saat_Cycle: null, penilai: [],
            });
        }
        per.get(r.Id_PA_Participant).penilai.push(r);
    });
    const peserta = [...per.values()].map((p) => ({
        ...p,
        progres: progres[p.Id_PA_Participant] || {
            total: p.penilai.length,
            selesai: p.penilai.filter((r) => r.Status_Penugasan === 'SELESAI').length,
            terkumpul: p.penilai.filter((r) => r.Status_Penugasan === 'TERKUMPUL').length,
            berjalan: p.penilai.filter((r) => r.Status_Penugasan === 'BERJALAN').length,
            belum_mulai: p.penilai.filter((r) => r.Status_Penugasan === 'BELUM_MULAI').length,
            terlambat: 0,
        },
    }));
    const pg = respons?.penugasan || {};
    return {
        ...respons,
        peserta: { baris: peserta, halaman: pg.halaman ?? 1, per_halaman: 10, total: pg.total ?? peserta.length, total_halaman: pg.total_halaman ?? 1 },
    };
}

vi.mock('../api/paApi.js', () => ({
    default: {
        daftarSiklus: vi.fn(),
        monitoringSiklus: vi.fn(),
        sapuTertahan: vi.fn(),
        bukaPengisian: vi.fn(),
        tutupPengisian: vi.fn(),
        cetakUlangPintu: vi.fn(),
        jadwalUlangPintu: vi.fn(),
        keranjangSiklus: vi.fn(),
        detailHasil: vi.fn(),
        hitungPeserta: vi.fn(),
        kalibrasi: vi.fn(),
        terbitkanPeserta: vi.fn(),
        hitungSiklus: vi.fn(),
        mulaiHitungLatar: vi.fn(),
        statusHitungLatar: vi.fn(),
        pratinjauHitungSiklus: vi.fn(),
        ringkasanPenerbitan: vi.fn(),
        tutupPenerbitan: vi.fn(),
        terbitkan: vi.fn(),
        batalkanPenugasan: vi.fn(),
        daftarPeserta: vi.fn(),
        penutupanSiklus: vi.fn(),
        kirimanPerluDitinjau: vi.fn(),
        petakanUlangKiriman: vi.fn(),
    },
}));

const uiStubs = {
    HcisHeader: {
        props: ['title', 'description'],
        template: '<header><h1>{{ title }}</h1><p>{{ description }}</p><slot name="badge" /><slot name="actions" /><slot name="console" /><slot name="tabs" /></header>',
    },
    HcisStatGroup: { template: '<div class="stat-group"><slot /></div>' },
    HcisStatCard: {
        props: ['label', 'value'],
        template: '<div class="stat-card"><span>{{ label }}</span><strong>{{ value }}</strong></div>',
    },
    HcisFilterBar: {
        props: ['search', 'quickChips'],
        template: '<div class="filter-bar"><slot /></div>',
    },
    HcisTable: {
        props: ['columns', 'rows'],
        /* Sel `pintu` ikut dirender: tanpa itu tombol cetak ulang tidak pernah ada di DOM,
           dan uji yang menegaskan ketiadaannya akan hijau tanpa menguji apa pun. */
        /* Sel `dinilai` ikut dirender: di sanalah nama peserta menjadi jalan masuk ke
           panel hasilnya, dan uji yang tidak merendernya akan hijau tanpa menyentuhnya. */
        template: '<table class="table-stub"><tbody><tr v-for="r in rows" :key="r.id_key"><td class="sel-dinilai"><slot name="cell-dinilai" :row="r" /></td><td class="sel-pintu"><slot name="cell-pintu" :row="r" /></td></tr></tbody></table>',
    },
    HcisBadge: {
        props: ['label'],
        template: '<span class="badge">{{ label }}</span>',
    },
    HcisStatusBadge: {
        props: ['status', 'label'],
        template: '<span class="status-badge">{{ label }}</span>',
    },
    HcisButton: {
        props: ['label', 'loading', 'disabled'],
        // Isi slot ikut dirender: tombol di halaman ini menulis teksnya sebagai anak, bukan
        // lewat prop `label`, sehingga stub tanpa <slot /> memulangkan tombol tanpa teks.
        template: '<button :disabled="disabled">{{ label }}<slot /></button>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: { template: '<div class="result-state" />' },
    HcisEmptyState: { template: '<div class="empty-state" />' },
    HcisSkeleton: { template: '<div class="skeleton" />' },
    HcisDialog: {
        props: ['modelValue', 'title', 'description'],
        template: '<div v-if="modelValue" class="dialog-stub"><h2>{{ title }}</h2><p>{{ description }}</p><slot /><slot name="footer" /></div>',
    },
    HcisAvatar: { props: ['name'], template: '<span class="avatar" />' },
    PaResultDialog: {
        props: ['modelValue', 'participant', 'canCalculate', 'canCalibrate', 'canPublish'],
        template: '<div v-if="modelValue" class="result-dialog">{{ participant?.Nama_Karyawan_Saat_Cycle }}'
            + '<span class="izin">{{ [canCalculate, canCalibrate, canPublish].join(",") }}</span></div>',
    },
    PaPublicationDialog: {
        props: ['modelValue', 'canPublish'],
        template: '<div v-if="modelValue" class="publication-dialog">{{ canPublish }}</div>',
    },
    PaCycleFillingDialog: {
        props: ['modelValue', 'mode'],
        template: '<div v-if="modelValue" class="cycle-filling-dialog" />',
    },
    HcisTextarea: {
        props: ['modelValue', 'label', 'error', 'disabled'],
        emits: ['update:modelValue'],
        template: '<label class="textarea-stub">{{ label }}<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><span class="textarea-error">{{ error }}</span></label>',
    },
};

describe('PerformanceAppraisalPemantauanPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.pratinjauHitungSiklus.mockResolvedValue({
            total: 140, siap_hitung_pertama: 140, belum_lengkap: 0, terkalibrasi: 0, terbit: 0,
        });
        /* `operasi_boleh` seperti yang dikirim server untuk siklus OPEN (sebagian): tombol cetak
           ulang dan petakan ulang kini ikut gerbang siklus, sama dengan penolakan servernya. */
        paApi.daftarSiklus.mockResolvedValue({
            siklus: [
                { Id_PA_Cycle: 10, Kode_Cycle: 'PA-2026-H1', Nama_Cycle: 'Semester 1 2026', Status_Cycle: 'OPEN', operasi_boleh: ['CETAK_ULANG_PINTU', 'TERIMA_PAYLOAD'] },
            ],
        });
        /* BENTUKNYA MENGIKUTI api/fixtures/siklus-monitoring.json, bukan karangan.
           Versi sebelumnya memakai bentuk lama — `baris` di akar, dengan
           `Nama_Karyawan_Yang_Dinilai`/`Status_Pengisian` — yang tidak pernah lagi
           dikirim server sejak daftar penugasan dipisah dari daftar terlambat dan
           diberi halaman. Mock yang bentuknya sudah tidak ada membuat ujinya
           memeriksa halaman terhadap dunia yang tidak ada: ia bisa hijau sementara
           halamannya kosong di hadapan data sungguhan. */
        paApi.monitoringSiklus.mockResolvedValue(denganPeserta({
            ringkasan: { total: 2, selesai: 1, berjalan: 0, belum_mulai: 1, terlambat: 0, persen: 50 },
            kategori: {
                ATASAN: { kode: 'ATASAN', nama: 'Atasan Langsung', total: 1, selesai: 1, persen: 100 },
                PEER: { kode: 'PEER', nama: 'Rekan Kerja', total: 1, selesai: 0, persen: 0 },
            },
            keranjang: { siap_dihitung: 0, perlu_verifikasi: 0, tertahan: 0, terbit: 0, kiriman_susulan: 0 },
            penugasan: {
                baris: [
                    {
                        Id_PA_Assignment: 9001,
                        Id_PA_Participant: 101,
                        Kode_Karyawan: 'DEMO-001',
                        Nama_Karyawan_Saat_Cycle: 'Budi Santoso',
                        Nama_Divisi_Saat_Cycle: 'PRODUCTION',
                        Nama_Level_Saat_Cycle: 'STAFF',
                        Kode_Karyawan_Penilai: 'DEMO-009',
                        Nama_Penilai_Saat_Cycle: 'Agus Manager',
                        Kode_Kategori: 'ATASAN',
                        Nama_Kategori: 'Atasan Langsung',
                        Status_Penugasan: 'SELESAI',
                        Due_At: '2026-09-30',
                        Started_At: '2026-09-05 09:15:00',
                        Finished_At: '2026-09-05 09:41:00',
                        hari_terlambat: 0,
                    },
                    {
                        Id_PA_Assignment: 9002,
                        Id_PA_Participant: 102,
                        Kode_Karyawan: 'DEMO-002',
                        Nama_Karyawan_Saat_Cycle: 'Dewi Lestari',
                        Nama_Divisi_Saat_Cycle: 'PRODUCTION',
                        Nama_Level_Saat_Cycle: 'STAFF',
                        Kode_Karyawan_Penilai: 'DEMO-010',
                        Nama_Penilai_Saat_Cycle: 'Siti Rekan',
                        Kode_Kategori: 'PEER',
                        Nama_Kategori: 'Rekan Kerja',
                        Status_Penugasan: 'BELUM_MULAI',
                        Due_At: '2026-09-30',
                        Started_At: null,
                        Finished_At: null,
                        hari_terlambat: 0,
                    },
                ],
                halaman: 1,
                per_halaman: 20,
                total: 2,
                total_halaman: 1,
                dipangkas: false,
            },
        }));
    });

    /*
     | TIGA TAB, DAN DATANYA DIMUAT SAAT TABNYA DIBUKA.
     |
     | Daftar peserta satu siklus bisa ratusan baris. Memuatnya bersama halaman berarti admin
     | yang hanya ingin mengejar penilai tetap menunggu data yang tidak ia buka.
     */
    it('memuat daftar peserta hanya ketika tab Hasil dibuka', async () => {
        paApi.daftarPeserta.mockResolvedValue({ peserta: [] });

        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(paApi.daftarPeserta).not.toHaveBeenCalled();

        // Stepper penutupan: tahap Hitung & Verifikasi membuka daftar hasil per peserta.
        await wrapper.find('[data-uji="tahap-verifikasi"]').trigger('click');
        await flushPromises();

        /* LINGKUPNYA ikut ditegaskan, bukan hanya "dipanggil". Tab ini meja tinjau-kalibrasi-
           terbit; menariknya tanpa saringan status berarti daftarnya berisi ratusan peserta
           yang belum terhitung dan tidak bisa ditindak dari sini. Saringannya harus di
           SERVER: menyaring sesudah pemenggalan halaman menghilangkan orang tanpa pesan. */
        expect(paApi.daftarPeserta).toHaveBeenCalledWith(10, expect.objectContaining({
            halaman: 1,
            per_halaman: 20,
            status_hasil: 'DIHITUNG,TERBIT',
        }));
    });

    /* KOREKSI HITUNG & VERIFIKASI (7 Okt 2026): tab Hasil dulu memuat 200 (dipangkas server ke
       100) lalu menyaring/mencari/memotong halaman di layar — peserta ke-101 tak pernah tampil. */
    it('tab Hasil: saringan status dan pencarian dikirim ke server, halaman dari server', async () => {
        paApi.daftarPeserta.mockResolvedValue({ peserta: [{ Id_PA_Participant: 1, Nama_Karyawan_Saat_Cycle: 'Aji', Status_Hasil: 'DIHITUNG' }], total: 135 });
        window.history.replaceState(null, '', '/performance-appraisal/pemantauan?tahap=verifikasi&cycle=10');
        const w = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(w.text()).toContain('135');
        await w.findAll('button.pa-hasil-chip').find((b) => b.text().includes('Menunggu Review')).trigger('click');
        await flushPromises();
        expect(paApi.daftarPeserta).toHaveBeenLastCalledWith('10', expect.objectContaining({ status_hasil: 'DIHITUNG', halaman: 1 }));

        await w.find('input.pa-hasil-search-input').setValue('E15');
        // Pencarian ditunda 300 ms supaya tidak satu permintaan per ketukan.
        await new Promise((r) => setTimeout(r, 350));
        await flushPromises();
        expect(paApi.daftarPeserta).toHaveBeenLastCalledWith('10', expect.objectContaining({ q: 'E15', halaman: 1 }));
        // Tidak ada lagi saringan grade maupun chip kalibrasi di tahap ini.
        expect(w.text()).not.toContain('Hasil Dikalibrasi');
        expect(w.text()).not.toContain('Semua Grade');
        window.history.replaceState(null, '', '/performance-appraisal/pemantauan');
    });

    it('hitung ulang tertahan dicicil dengan penanda posisi sampai habis', async () => {
        paApi.sapuTertahan
            .mockResolvedValueOnce({ diperiksa: 50, jadi_dihitung: 10, tetap_tertahan: 40, gagal: 0, terakhir_id: 77, selesai: false })
            .mockResolvedValueOnce({ diperiksa: 5, jadi_dihitung: 5, tetap_tertahan: 0, gagal: 0, terakhir_id: 99, selesai: true });
        window.history.replaceState(null, '', '/performance-appraisal/pemantauan?tahap=verifikasi&sub=KERANJANG&cycle=10');
        const monitoring = await paApi.monitoringSiklus.mock.results[0]?.value;
        paApi.monitoringSiklus.mockResolvedValue({ ...(monitoring || {}), keranjang: { needs_kpi: 3, needs_rater: 2, tertahan_lain: 1, tertahan: 6, siap_dihitung: 0 } });
        const w = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        // Kendala penilai punya tile sendiri (dulu terhitung di "Kendala Lain" tapi tak pernah tampil).
        expect(w.text()).toContain('Kendala Penilai');
        w.vm.jalankanSapu('TERTAHAN');
        await flushPromises();
        expect(paApi.sapuTertahan.mock.calls.map((c) => c[2])).toEqual([
            { mulaiDariId: null, jenis: 'TERTAHAN' },
            { mulaiDariId: 77, jenis: 'TERTAHAN' },
        ]);
        window.history.replaceState(null, '', '/performance-appraisal/pemantauan');
    });

    /*
     | PRATINJAU DI RINGKASAN MENGANTAR KE TABNYA, LENGKAP DENGAN ANTREANNYA.
     |
     | Keranjang muncul di dua tempat dengan peran berbeda: PRATINJAU di tab Ringkasan yang
     | tugasnya mengantar, dan yang di tab "Antrean Tindak Lanjut" tempat ia benar-benar
     | dikerjakan. Menekan pratinjaunya harus memindahkan admin ke tab itu DENGAN antrean
     | yang ia tunjuk sudah terbuka — bukan sekadar memindahkan tab lalu meninggalkannya
     | memilih ulang hal yang barusan ia tunjuk.
     */
    it('mengantar pratinjau keranjang ke tab Antrean beserta antreannya', async () => {
        paApi.daftarPeserta.mockResolvedValue({ peserta: [] });
        paApi.keranjangSiklus.mockResolvedValue({ baris: [], total: 0 });

        const monitoring = await paApi.monitoringSiklus.mock.results[0]?.value;
        paApi.monitoringSiklus.mockResolvedValue({
            ...(monitoring || {}),
            /* Nama keranjangnya kini per SEBAB, bukan per status: `tertahan` tunggal pecah
               menjadi needs_kpi / needs_rater / tertahan_lain, karena lima dari tujuh sebab
               tertahan adalah soal KPI dan sisanya menuntut perbaikan yang sama sekali lain. */
            keranjang: {
                siap_dihitung: 0,
                needs_kpi: 1,
                needs_rater: 0,
                tertahan_lain: 0,
                needs_review_qualitative: 0,
                reviewing_calibration: 0,
                terbit: 0,
                kiriman_susulan: 0,
            },
        });

        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        const petak = wrapper.findAll('.pa-basket-tile')
            .find((b) => /Kendala Nilai KPI/i.test(b.text()));

        expect(petak).toBeTruthy();

        await petak.trigger('click');
        await flushPromises();

        expect(paApi.keranjangSiklus).toHaveBeenCalled();
        expect(paApi.keranjangSiklus.mock.calls.at(-1)[1]).toBe('needs_kpi');
    });

    /*
     | HASIL PESERTA DIBUKA DI TEMPAT, BUKAN DENGAN PINDAH HALAMAN.
     |
     | Sampai 7 September 2026 satu-satunya jalan dari sini ke hasil seseorang adalah
     | `window.location.href` ke halaman siklus — admin yang sedang menelusuri kehilangan
     | seluruh saringan dan posisinya, lalu harus mencari orang yang sama sekali lagi di
     | daftar yang berbeda, untuk membaca satu alasan tertahan.
     */
    it('membuka panel hasil peserta tanpa meninggalkan halaman', async () => {
        paApi.detailHasil.mockResolvedValue({ peserta: { Id_PA_Participant: 101 } });

        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(wrapper.find('.result-dialog').exists()).toBe(false);

        await wrapper.find('.sel-dinilai .pa-table-person-link').trigger('click');
        await flushPromises();

        expect(paApi.detailHasil).toHaveBeenCalledWith(101);
        expect(wrapper.find('.result-dialog').exists()).toBe(true);
    });

    /*
     | IZIN DIAMBIL DARI SERVER, BUKAN DISUSUN DI LAYAR.
     |
     | Daftar status yang ditulis tangan sudah pernah keliru dalam dua arah sekaligus: ia
     | menyebut `CALCULATED` yang tidak pernah ada, dan melewatkan `OPEN` yang justru
     | diizinkan gerbang. Yang hilang karenanya bukan sekadar tombol, melainkan kemampuan
     | menghitung peserta yang sudah lengkap selagi pengisian masih berjalan.
     */
    it('menampilkan aksi hasil hanya ketika server mengizinkannya', async () => {
        paApi.daftarSiklus.mockResolvedValue({
            siklus: [{
                Id_PA_Cycle: 10,
                Kode_Cycle: 'PA-2026-H1',
                Nama_Cycle: 'Semester 1 2026',
                Status_Cycle: 'OPEN',
                operasi_boleh: ['HITUNG_HASIL', 'TUTUP_PENGISIAN'],
            }],
        });

        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        const teksTombol = wrapper.findAll('button').map((b) => b.text());

        /* HITUNG_HASIL ADA di `operasi_boleh`, tetapi tombol MASSAL tetap tidak boleh muncul
           pada siklus OPEN: izin itu untuk jalur PER ORANG, sedangkan jalur massal menolak
           OPEN dengan alasan yang benar — menghitung ratusan orang yang penilainya masih
           berjalan lalu mengulang seluruhnya adalah pekerjaan yang hasilnya dibuang sendiri.
           Menawarkan tombol yang sudah pasti ditolak sama saja menyuruh admin menekannya
           untuk mengetahui sesuatu yang sudah bisa dikatakan sekarang. */
        expect(teksTombol).not.toContain('Hitung Hasil');

        // TERBITKAN tidak ada di `operasi_boleh`, jadi tombolnya tidak boleh muncul.
        expect(teksTombol).not.toContain('Terbitkan Hasil');
    });

    it('menawarkan hitung ulang semua hanya pada siklus yang sudah ditutup', async () => {
        paApi.daftarSiklus.mockResolvedValue({
            siklus: [{
                Id_PA_Cycle: 10,
                Kode_Cycle: 'PA-2026-H1',
                Nama_Cycle: 'Semester 1 2026',
                Status_Cycle: 'CLOSED',
                operasi_boleh: ['HITUNG_HASIL', 'TERBITKAN'],
            }],
        });

        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(wrapper.findAll('button').map((b) => b.text())).toContain('Hitung Hasil');
    });

    /*
     | HITUNG SEBAGAI TUGAS LATAR, bukan cicilan dari browser.
     |
     | Terukur ±0,5 detik per peserta: 1.600 orang = ±14 menit dengan tab yang wajib tetap
     | terbuka. Kini layar hanya memulai tugas lalu membaca kemajuannya sampai selesai.
     */
    it('memulai hitung di latar lalu membaca kemajuan sampai selesai', async () => {
        vi.useFakeTimers();
        paApi.daftarSiklus.mockResolvedValue({
            siklus: [{
                Id_PA_Cycle: 10,
                Kode_Cycle: 'PA-2026-H1',
                Nama_Cycle: 'Semester 1 2026',
                Status_Cycle: 'CLOSED',
                operasi_boleh: ['HITUNG_HASIL'],
            }],
        });
        paApi.statusHitungLatar
            .mockResolvedValueOnce({ tugas: null }) // saat halaman dibuka: belum ada tugas
            .mockResolvedValueOnce({ tugas: { status: 'processing', ringkasan: { total: 140, sudah: 100 }, dihitung: 98, tertahan: 2, jumlah_galat: 0 } })
            .mockResolvedValueOnce({ tugas: { status: 'ready', ringkasan: { total: 140, sudah: 140 }, dihitung: 138, tertahan: 2, jumlah_galat: 0 } });
        paApi.mulaiHitungLatar.mockResolvedValue({ baru: true, tugas: { status: 'queued', ringkasan: { total: 140, sudah: 0 } } });

        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text() === 'Hitung Hasil').trigger('click');
        await flushPromises();

        /* Tombol toolbar membuka konfirmasi yang menyebut angkanya; ujinya menempuh pintu
           yang sama dengan admin — buka dialog, lalu jalankan. */
        const dialog = wrapper.findComponent({ name: 'PaHitungSiklusDialog' });
        expect(dialog.exists()).toBe(true);
        dialog.vm.$emit('jalankan', 'BELUM');
        await flushPromises();

        expect(paApi.mulaiHitungLatar).toHaveBeenCalledWith(10, { lingkup: 'BELUM' });
        expect(paApi.hitungSiklus).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(3000);
        await flushPromises();
        expect(wrapper.text()).toContain('Menghitung 100/140 peserta');

        const muatSebelum = paApi.monitoringSiklus.mock.calls.length;
        await vi.advanceTimersByTimeAsync(3000);
        await flushPromises();
        expect(wrapper.text()).toContain('Hitung Hasil');
        expect(paApi.monitoringSiklus.mock.calls.length).toBeGreaterThan(muatSebelum); // data dimuat ulang
        vi.useRealTimers();
    });

    it('menyambung tugas hitung yang masih berjalan saat halaman dibuka', async () => {
        paApi.daftarSiklus.mockResolvedValue({
            siklus: [{ Id_PA_Cycle: 10, Kode_Cycle: 'PA-2026-H1', Nama_Cycle: 'Semester 1 2026', Status_Cycle: 'CLOSED', operasi_boleh: ['HITUNG_HASIL'] }],
        });
        paApi.statusHitungLatar.mockResolvedValue({ tugas: { status: 'processing', ringkasan: { total: 900, sudah: 450 }, dihitung: 450, tertahan: 0, jumlah_galat: 0 } });

        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(paApi.statusHitungLatar).toHaveBeenCalledWith(10);
        expect(wrapper.text()).toContain('Menghitung 450/900 peserta');
        wrapper.unmount();
    });

    it('tidak menampilkan aksi hasil ketika siklus tidak mengizinkan apa pun', async () => {
        paApi.daftarSiklus.mockResolvedValue({
            siklus: [{
                Id_PA_Cycle: 10,
                Kode_Cycle: 'PA-2026-H1',
                Nama_Cycle: 'Semester 1 2026',
                Status_Cycle: 'DRAFT',
                operasi_boleh: [],
            }],
        });

        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        const teksTombol = wrapper.findAll('button').map((b) => b.text());

        expect(teksTombol).not.toContain('Hitung Siklus');
        expect(teksTombol).not.toContain('Terbitkan Hasil');
    });

    it('renders header, loads cycle and fetches monitoring data', async () => {
        const wrapper = mount(PemantauanPage, {
            global: { stubs: uiStubs },
        });

        await vi.dynamicImportSettled();

        // Judul halaman kini "Monitoring Cycle" (penutupan siklus menjadi tahapan di dalamnya).
        expect(wrapper.find('h1').text()).toContain('Monitoring Cycle');
        expect(paApi.daftarSiklus).toHaveBeenCalled();
        /* Argumen keduanya lahir bersama tabel penugasan yang berhalaman dan bersaringan.
           Ditegaskan sebagai BENTUK, bukan objek utuh: mengunci seluruh isinya membuat
           uji ini memerah setiap kali satu saringan ditambahkan — padahal yang dijaga
           di sini cuma bahwa halaman mengirim siklus DAN keadaan saringannya, bukan
           siklus saja. */
        expect(paApi.monitoringSiklus).toHaveBeenCalledWith(10, expect.objectContaining({
            halaman: 1,
            per_halaman: expect.any(Number),
        }));

        expect(wrapper.text()).toContain('Budi Santoso');
    });

    it('displays summary and category breakdown properly', async () => {
        const wrapper = mount(PemantauanPage, {
            global: { stubs: uiStubs },
        });

        await vi.dynamicImportSettled();

        expect(wrapper.text()).toContain('Progres Pengisian Siklus');
        expect(wrapper.text()).toContain('Atasan Langsung');
        expect(wrapper.text()).toContain('Rekan Kerja');
    });
    /* ------------------------------------------------------------------ *
     * Akses ulang: cetak ulang (lanjutkan) atau jadwalkan ulang (dari awal) *
     * ------------------------------------------------------------------ */

    const stubPaham = { ...uiStubs, HcisCheckbox: {
        props: ['modelValue', 'label'], emits: ['update:modelValue'],
        template: '<label class="paham-stub"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />{{ label }}</label>',
    } };

    it('satu tombol Akses ulang untuk setiap penugasan yang belum dibatalkan', async () => {
        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await vi.dynamicImportSettled();

        /* Dua baris (SELESAI dan BELUM_MULAI), dua tombol — tanpa "Cetak ulang"/"Jadwalkan
           ulang" terpisah yang membingungkan. */
        const tombol = wrapper.findAll('.sel-pintu button').filter((b) => b.text().includes('Akses ulang'));
        expect(tombol).toHaveLength(2);
        expect(wrapper.text()).not.toContain('Cetak ulang');
        expect(wrapper.text()).not.toContain('Jadwalkan ulang');
    });

    it('menolak alasan yang terlalu pendek tanpa memanggil server', async () => {
        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });

        await vi.dynamicImportSettled();
        await wrapper.find('[data-uji="akses-ulang-9002"]').trigger('click');

        expect(wrapper.find('.dialog-stub').exists()).toBe(true);

        await wrapper.find('.textarea-stub textarea').setValue('oke');
        await wrapper.find('[data-uji="akses-ulang-kirim"]').trigger('click');

        expect(paApi.cetakUlangPintu).not.toHaveBeenCalled();
        expect(wrapper.find('.textarea-error').text()).not.toBe('');
    });

    /* Yang SUDAH selesai hanya bisa diulang dari awal — dengan konfirmasi penghapusan jawaban. */
    it('penugasan yang sudah selesai: hanya ulangi dari awal, menuntut konfirmasi', async () => {
        paApi.jadwalUlangPintu.mockResolvedValue({ assignment_id: 9001, jawaban_dihapus: true, catatan: 'Jadwal baru dikirim.' });
        const wrapper = mount(PemantauanPage, { global: { stubs: stubPaham } });
        await vi.dynamicImportSettled();
        await wrapper.find('[data-uji="akses-ulang-9001"]').trigger('click');

        const dialog = wrapper.find('[data-uji="dialog-akses-ulang"]');
        expect(dialog.text()).toContain('DIHAPUS permanen');
        expect(dialog.find('[data-uji="akses-ulang-lanjut"]').exists()).toBe(false);
        await dialog.find('textarea').setValue('Penilai meninggalkan tes setengah jalan.');
        const kirim = () => wrapper.find('[data-uji="akses-ulang-kirim"]');
        expect(kirim().attributes('disabled')).toBeDefined();
        await wrapper.find('.paham-stub input').setValue(true);
        await kirim().trigger('click');
        await vi.dynamicImportSettled();

        expect(paApi.jadwalUlangPintu).toHaveBeenCalledWith(9001, 'Penilai meninggalkan tes setengah jalan.');
        expect(paApi.cetakUlangPintu).not.toHaveBeenCalled();
    });

    it('mengirim penugasan dan alasannya, lalu memuat ulang', async () => {
        paApi.cetakUlangPintu.mockResolvedValue({ token_baru: 'SIM-PA-BARU-1' });

        const wrapper = mount(PemantauanPage, { global: { stubs: uiStubs } });

        await vi.dynamicImportSettled();
        await wrapper.find('[data-uji="akses-ulang-9002"]').trigger('click');

        const alasan = 'Paket soal disalin ulang karena ikatan menunjuk butir yang dicabut.';

        await wrapper.find('.textarea-stub textarea').setValue(alasan);
        await wrapper.find('[data-uji="akses-ulang-kirim"]').trigger('click');
        await vi.dynamicImportSettled();

        // 9002 adalah baris BELUM_MULAI. Kalau yang terkirim 9001, tombolnya menunjuk
        // baris yang salah dan admin mencetak ulang pintu orang lain.
        expect(paApi.cetakUlangPintu).toHaveBeenCalledWith(9002, alasan);

        // Tabelnya harus dimuat ulang: pintu lama berhenti berlaku, dan layar yang tidak
        // menyegarkan diri menyisakan keadaan yang sudah tidak benar.
        expect(paApi.monitoringSiklus).toHaveBeenCalledTimes(2);
    });


    /*
     | PAGINASI TAB PENGISIAN (7 Okt 2026). Satu klik halaman memicu DUA request monitoring
     | (handler terpasang di `current-change` DAN `update:current-page`) plus muat ulang alur,
     | dan respons yang tiba belakangan menimpa halaman yang sedang dipilih — isi tabel
     | berganti-ganti. Satu klik = satu request, dan hanya respons terbaru yang dipakai.
     */
    describe('paginasi pengisian', () => {
        const halamanPalsu = (halaman) => denganPeserta({
            ringkasan: {}, kategori: {}, keranjang: {},
            penugasan: {
                baris: Array.from({ length: 20 }, (_, i) => ({
                    Id_PA_Assignment: (halaman - 1) * 20 + i + 1,
                    Id_PA_Participant: (halaman - 1) * 20 + i + 1,
                    Nama_Karyawan_Saat_Cycle: `Halaman${halaman}-${i}`,
                })),
                halaman, per_halaman: 20, total: 60, total_halaman: 3,
            },
        });
        const pasangDiPengisian = async () => {
            window.history.replaceState(null, '', '/performance-appraisal/pemantauan?tahap=pengisian&cycle=10');
            paApi.penutupanSiklus.mockResolvedValue({ tahap: [] });
            paApi.monitoringSiklus.mockImplementation((id, q) => Promise.resolve(halamanPalsu(q.halaman)));
            const w = mount(PemantauanPage, { global: { stubs: uiStubs } });
            await flushPromises();
            return w;
        };
        const klikHalaman = (w, n) => w.findComponent(HcisPagination).findAll('button').find((b) => b.text().trim() === String(n)).trigger('click');

        it('satu klik halaman = satu request monitoring, tanpa memuat ulang alur penutupan', async () => {
            const w = await pasangDiPengisian();
            const mon = paApi.monitoringSiklus.mock.calls.length;
            const alur = paApi.penutupanSiklus.mock.calls.length;

            await klikHalaman(w, 2);
            await flushPromises();

            expect(paApi.monitoringSiklus.mock.calls.slice(mon).map((c) => c[1].halaman)).toEqual([2]);
            expect(paApi.penutupanSiklus.mock.calls.length).toBe(alur);
        });

        it('respons halaman lama yang tiba belakangan tidak menimpa halaman yang dipilih', async () => {
            const w = await pasangDiPengisian();
            let lepasHalaman2;
            paApi.monitoringSiklus.mockImplementation((id, q) => (q.halaman === 2
                ? new Promise((r) => { lepasHalaman2 = () => r(halamanPalsu(2)); })
                : Promise.resolve(halamanPalsu(q.halaman))));

            await klikHalaman(w, 2);
            await klikHalaman(w, 3);
            await flushPromises();
            lepasHalaman2();
            await flushPromises();

            expect(w.text()).toContain('Halaman3-0');
            expect(w.text()).not.toContain('Halaman2-0');
        });
    });

    /*
     | KOREKSI TAHAP PENGISIAN (7 Okt 2026). Halaman dulu dipotong per baris penugasan lalu
     | dikelompokkan per karyawan di layar, sehingga orang dengan 5 dari 6 penilai selesai bisa
     | terbaca "0/1". Progres kini dari server atas semua penilainya.
     */
    describe('koreksi pengisian', () => {
        const pasang = async (respons) => {
            window.history.replaceState(null, '', '/performance-appraisal/pemantauan?tahap=pengisian&cycle=10');
            paApi.penutupanSiklus.mockResolvedValue({ tahap: [] });
            paApi.monitoringSiklus.mockResolvedValue(respons);
            const w = mount(PemantauanPage, { global: { stubs: uiStubs } });
            await flushPromises();
            return w;
        };
        const satuBelum = {
            ringkasan: { total: 6, selesai: 5, berjalan: 0, belum_mulai: 1, terkumpul: 0, terlambat: 0, persen: 83 },
            kategori: {}, keranjang: {},
            penugasan: { baris: [{
                Id_PA_Assignment: 1, Id_PA_Participant: 7, Kode_Karyawan: 'E15', Nama_Karyawan_Saat_Cycle: 'Dimas',
                Kode_Karyawan_Penilai: 'X1', Kode_Perusahaan_Penilai: '001', Nama_Penilai_Saat_Cycle: 'Rekan', Kode_Kategori: 'PEER',
                Nama_Kategori: 'Rekan', Status_Penugasan: 'BELUM_MULAI', Due_At: '2026-10-31', hari_terlambat: 0,
            }], halaman: 1, per_halaman: 10, total: 1, total_halaman: 1 },
        };

        it('progres karyawan dari server, bukan dari penilai yang kebetulan tampil', async () => {
            const w = await pasang(denganPeserta(satuBelum, { 7: { total: 6, selesai: 5, terkumpul: 0, berjalan: 0, belum_mulai: 1, terlambat: 0 } }));
            expect(w.text()).toContain('5/6');
            expect(w.text()).not.toContain('0/1');
        });

        it('kartu Belum Selesai menyaring belum mulai + sedang mengisi sekaligus', async () => {
            const w = await pasang(denganPeserta(satuBelum));
            const kartu = w.findAll('[role="button"], button').find((b) => b.attributes('title') === 'Klik untuk menyaring rater yang belum selesai');
            await kartu.trigger('click');
            await flushPromises();
            expect(paApi.monitoringSiklus).toHaveBeenLastCalledWith('10', expect.objectContaining({ status: 'BELUM_SELESAI', halaman: 1 }));
        });

        it('lepas paksa tersedia di tampilan tabel untuk lembar terisi yang belum dikirim', async () => {
            const terkumpul = structuredClone(satuBelum);
            terkumpul.penugasan.baris[0].Status_Penugasan = 'TERKUMPUL';
            paApi.daftarSiklus.mockResolvedValue({ siklus: [
                { Id_PA_Cycle: 10, Kode_Cycle: 'PA-2026-H1', Nama_Cycle: 'Semester 1 2026', Status_Cycle: 'OPEN', operasi_boleh: ['HITUNG_HASIL'] },
            ] });
            const w = await pasang(denganPeserta(terkumpul));
            // Baris penilai ada di baris anak; buka dulu baris karyawannya.
            await w.findAll('tr').find((t) => t.text().includes('Dimas')).trigger('click');
            await flushPromises();
            expect(w.find('[data-uji="lepas-paksa-tabel"]').exists()).toBe(true);
        });
    });

    it('antrean: respons kategori lama yang tiba belakangan tidak menimpa kategori yang dipilih', async () => {
        window.history.replaceState(null, '', '/performance-appraisal/pemantauan?tahap=verifikasi&sub=KERANJANG&cycle=10');
        paApi.penutupanSiklus.mockResolvedValue({ tahap: [] });
        paApi.daftarPeserta.mockResolvedValue({ peserta: [] });
        const monitoring = await paApi.monitoringSiklus.mock.results[0]?.value;
        paApi.monitoringSiklus.mockResolvedValue({
            ...(monitoring || {}),
            keranjang: { siap_dihitung: 0, needs_kpi: 1, needs_rater: 0, tertahan_lain: 1, needs_review_qualitative: 0, terbit: 0, kiriman_susulan: 0 },
        });
        const isi = (nama) => ({ baris: [{ Id_PA_Participant: nama.length, Nama_Karyawan_Saat_Cycle: nama, Kode_Karyawan: nama }], total: 1, halaman: 1, per_halaman: 25, total_halaman: 1 });
        let lepasKpi;
        paApi.keranjangSiklus.mockImplementation((id, jenis) => (jenis === 'needs_kpi'
            ? new Promise((r) => { lepasKpi = () => r(isi('OrangKPI')); })
            : Promise.resolve(isi('OrangLain'))));

        const w = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();
        const ubin = (re) => w.findAll('.pa-compact-tile').find((t) => re.test(t.text()));

        await ubin(/Kendala Nilai KPI/i).trigger('click');
        await ubin(/Kendala Lain/i).trigger('click');
        await flushPromises();
        lepasKpi();
        await flushPromises();

        expect(w.text()).toContain('OrangLain');
        expect(w.text()).not.toContain('OrangKPI');
    });

    /* KOREKSI 8 Okt 2026 */

    it('aksi per peserta memuat ulang baris tabel Hasil, bukan hanya monitoring', async () => {
        paApi.daftarPeserta.mockResolvedValue({ peserta: [{ Id_PA_Participant: 101, Nama_Karyawan_Saat_Cycle: 'Budi Santoso', Status_Hasil: 'DIHITUNG' }], total: 1 });
        paApi.detailHasil.mockResolvedValue({ peserta: { Id_PA_Participant: 101 } });
        paApi.terbitkanPeserta.mockResolvedValue({});
        window.history.replaceState(null, '', '/performance-appraisal/pemantauan?tahap=verifikasi&cycle=10');
        const w = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();
        const sebelum = paApi.daftarPeserta.mock.calls.length;
        expect(sebelum).toBeGreaterThan(0);

        await w.find('.sel-dinilai .pa-table-person-link').trigger('click');
        await flushPromises();
        expect(w.find('.result-dialog').exists()).toBe(true);
        w.findComponent(uiStubs.PaResultDialog).vm.$emit('publish');
        await flushPromises();

        expect(paApi.terbitkanPeserta).toHaveBeenCalledWith(101);
        expect(paApi.daftarPeserta.mock.calls.length).toBe(sebelum + 1);
        window.history.replaceState(null, '', '/performance-appraisal/pemantauan');
    });

    it('antrean: pencarian dikirim ke server sebagai q, dan total tertahan dihitung per orang', async () => {
        window.history.replaceState(null, '', '/performance-appraisal/pemantauan?tahap=verifikasi&sub=KERANJANG&cycle=10');
        paApi.penutupanSiklus.mockResolvedValue({ tahap: [] });
        paApi.daftarPeserta.mockResolvedValue({ peserta: [] });
        const monitoring = await paApi.monitoringSiklus.mock.results[0]?.value;
        // Tiga orang yang ketiganya tertahan karena KPI DAN penilai: kartu boleh tumpang tindih.
        paApi.monitoringSiklus.mockResolvedValue({
            ...(monitoring || {}),
            keranjang: { siap_dihitung: 0, tertahan: 3, needs_kpi: 3, needs_rater: 3, tertahan_lain: 0, needs_review_qualitative: 0, terbit: 0, kiriman_susulan: 0 },
        });
        paApi.keranjangSiklus.mockResolvedValue({
            baris: [{ Id_PA_Participant: 1, Nama_Karyawan_Saat_Cycle: 'OrangKPI', Kode_Karyawan: 'E01' }],
            total: 30, halaman: 1, per_halaman: 25, total_halaman: 2,
        });

        const w = mount(PemantauanPage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(w.find('.pa-queue-subtab-badge').text()).toBe('3');
        expect(w.find('.pa-queue-strip-note').text()).toContain('lebih dari satu sebab');

        await w.findAll('.pa-compact-tile').find((t) => /Kendala Nilai KPI/i.test(t.text())).trigger('click');
        await flushPromises();
        await w.find('input.pa-queue-search-input').setValue('E15');
        await new Promise((r) => setTimeout(r, 350));
        await flushPromises();

        expect(paApi.keranjangSiklus).toHaveBeenLastCalledWith(expect.anything(), 'needs_kpi', expect.objectContaining({ q: 'E15', halaman: 1 }));
        window.history.replaceState(null, '', '/performance-appraisal/pemantauan');
    });

    /*
     | KOREKSI IKHTISAR & PENGISIAN (8 Okt 2026). Angka kartu harus berjumlah sama dengan
     | totalnya, siklus bawaan harus yang sedang OPEN, dan tombol hanya tampil bila server
     | akan menerimanya — gerbang status siklus DAN izin halaman fasenya.
     */
    describe('koreksi ikhtisar & pengisian', () => {
        const ringkasan = {
            total: 10, selesai: 4, berjalan: 1, belum_mulai: 2, terkumpul: 1, menunggu_verifikasi: 2,
            terlambat: 0, persen: 40, karyawan_dinilai: 190,
        };
        const pasang = async ({ siklus, respons, akses, url = '/performance-appraisal/pemantauan?tahap=pengisian&cycle=10' } = {}) => {
            window.history.replaceState(null, '', url);
            paApi.penutupanSiklus.mockResolvedValue({ tahap: [] });
            if (siklus) paApi.daftarSiklus.mockResolvedValue({ siklus });
            if (respons) paApi.monitoringSiklus.mockResolvedValue(respons);
            const w = mount(PemantauanPage, { props: akses ? { akses } : {}, global: { stubs: uiStubs } });
            await flushPromises();
            return w;
        };
        const respons = (baris = [], extra = {}) => denganPeserta({
            ringkasan, kategori: {}, keranjang: {},
            penugasan: { baris, halaman: 1, per_halaman: 10, total: baris.length, total_halaman: 1 },
            ...extra,
        });
        const barisTerkumpul = {
            Id_PA_Assignment: 1, Id_PA_Participant: 7, Kode_Karyawan: 'E15', Nama_Karyawan_Saat_Cycle: 'Dimas',
            Kode_Karyawan_Penilai: 'X1', Kode_Perusahaan_Penilai: '001', Nama_Penilai_Saat_Cycle: 'Rekan', Kode_Kategori: 'PEER',
            Nama_Kategori: 'Rekan', Status_Penugasan: 'TERKUMPUL', Due_At: '2026-10-31', hari_terlambat: 0,
        };
        const barisBelum = { ...barisTerkumpul, Id_PA_Assignment: 2, Id_PA_Participant: 8, Nama_Karyawan_Saat_Cycle: 'Sari', Status_Penugasan: 'BELUM_MULAI' };
        afterEach(() => window.history.replaceState(null, '', '/performance-appraisal/pemantauan'));

        it('Belum Selesai = total − selesai, rinciannya memuat menunggu verifikasi, dan ada chip-nya', async () => {
            const w = await pasang({ respons: respons() });
            const kartu = w.findAll('[role="button"]').find((b) => b.attributes('title') === 'Klik untuk menyaring rater yang belum selesai');

            // 10 − 4 = 6, sama dengan "60% sisa" di sebelahnya.
            expect(kartu.text()).toContain('6');
            expect(kartu.text()).toContain('60% sisa');
            expect(w.find('[data-uji="rincian-belum-selesai"]').text()).toContain('2 menunggu verifikasi HC Learn');

            const chips = w.findAllComponents(uiStubs.HcisFilterBar).map((f) => f.props('quickChips')).find((c) => Array.isArray(c) && c.length);
            const chip = chips.find((c) => c.value === 'MENUNGGU_VERIFIKASI');
            expect(chip.badge).toBe(2);
            // Chip status (selain "Semua" dan "Terlambat") berjumlah sama dengan total.
            expect(chips.filter((c) => !['ALL', 'TERLAMBAT'].includes(c.value)).reduce((n, c) => n + Number(c.badge), 0)).toBe(10);
        });

        it('jumlah karyawan dinilai dari server, bukan baris halaman yang terbuka', async () => {
            const w = await pasang({ respons: respons([barisBelum]) });
            expect(w.find('[data-uji="karyawan-dinilai"]').text()).toContain('190');
        });

        it('tanpa ?cycle= memilih siklus OPEN lebih dulu walau ada LOCKED yang lebih baru', async () => {
            await pasang({
                url: '/performance-appraisal/pemantauan',
                siklus: [
                    { Id_PA_Cycle: 11092, Tahun: 2026, Status_Cycle: 'LOCKED', Periode_Dinilai_Sampai: '2026-12-31' },
                    { Id_PA_Cycle: 11091, Tahun: 2026, Status_Cycle: 'CLOSED', Periode_Dinilai_Sampai: '2026-12-31' },
                    { Id_PA_Cycle: 11089, Tahun: 2025, Status_Cycle: 'OPEN', Periode_Dinilai_Sampai: '2025-12-31' },
                ],
                respons: respons(),
            });
            expect(String(paApi.monitoringSiklus.mock.calls[0][0])).toBe('11089');
        });

        it('tanpa OPEN, CLOSED dengan periode berakhir paling akhir menang atas Id terbesar', async () => {
            await pasang({
                url: '/performance-appraisal/pemantauan',
                siklus: [
                    { Id_PA_Cycle: 30, Tahun: 2026, Status_Cycle: 'CLOSED', Periode_Dinilai_Sampai: '2026-03-31' },
                    { Id_PA_Cycle: 20, Tahun: 2026, Status_Cycle: 'CLOSED', Periode_Dinilai_Sampai: '2026-06-30' },
                    { Id_PA_Cycle: 40, Tahun: 2026, Status_Cycle: 'PUBLISHED', Periode_Dinilai_Sampai: '2026-12-31' },
                ],
                respons: respons(),
            });
            expect(String(paApi.monitoringSiklus.mock.calls[0][0])).toBe('20');
        });

        it('Buka Pengisian dipadamkan beserta sebabnya bila siklus lain masih OPEN', async () => {
            const w = await pasang({
                url: '/performance-appraisal/pemantauan?tahap=pengisian&cycle=11092',
                siklus: [
                    { Id_PA_Cycle: 11092, Kode_Cycle: 'PA-2026', Status_Cycle: 'LOCKED' },
                    { Id_PA_Cycle: 11089, Kode_Cycle: 'PA-2025', Status_Cycle: 'OPEN' },
                ],
                respons: respons(),
            });
            expect(w.find('[data-uji="buka-pengisian"]').attributes('disabled')).toBeDefined();
            expect(w.find('[data-uji="buka-pengisian-terhalang"]').text()).toContain('PA-2025');
        });

        it('cetak ulang tidak ditawarkan pada siklus CLOSED (gerbang CETAK_ULANG_PINTU tertutup)', async () => {
            const w = await pasang({
                siklus: [{ Id_PA_Cycle: 10, Status_Cycle: 'CLOSED', operasi_boleh: ['HITUNG_HASIL', 'TERIMA_PAYLOAD'] }],
                respons: respons([barisBelum]),
            });
            expect(w.findAll('.sel-pintu button').filter((b) => b.text().includes('Cetak ulang'))).toHaveLength(0);
        });

        it('petakan ulang hilang pada siklus PUBLISHED, diganti sebabnya', async () => {
            paApi.kirimanPerluDitinjau.mockResolvedValue({ baris: [{ payload_id: 5, nama_penilai: 'A', nama_peserta: 'B', tak_terpetakan: ['X'], butir_hilang: [] }] });
            const w = await pasang({
                siklus: [{ Id_PA_Cycle: 10, Status_Cycle: 'PUBLISHED', operasi_boleh: [] }],
                respons: respons(),
            });
            expect(w.text()).not.toContain('Petakan Ulang Semua');
            expect(w.find('[data-uji="petakan-ulang-terkunci"]').text()).toContain('OPEN atau CLOSED');
        });

        it('tanpa izin penyiapan, petakan ulang tidak ditawarkan walau siklusnya OPEN', async () => {
            paApi.kirimanPerluDitinjau.mockResolvedValue({ baris: [{ payload_id: 5, nama_penilai: 'A', nama_peserta: 'B', tak_terpetakan: ['X'], butir_hilang: [] }] });
            const w = await pasang({ respons: respons(), akses: { penyiapan: false, pemantauan: true, hasil: true } });
            expect(w.text()).not.toContain('Petakan Ulang Semua');
            expect(w.find('[data-uji="petakan-ulang-terkunci"]').text()).toContain('izin penyiapan');
        });

        it('tanpa izin fase Hasil, lepas paksa tidak ditawarkan walau HITUNG_HASIL terbuka', async () => {
            const siklus = [{ Id_PA_Cycle: 10, Status_Cycle: 'OPEN', operasi_boleh: ['HITUNG_HASIL'] }];
            const w = await pasang({ siklus, respons: respons([barisTerkumpul]), akses: { penyiapan: true, pemantauan: true, hasil: false } });
            await w.findAll('tr').find((t) => t.text().includes('Dimas')).trigger('click');
            await flushPromises();
            expect(w.find('[data-uji="lepas-paksa-tabel"]').exists()).toBe(false);

            const boleh = await pasang({ siklus, respons: respons([barisTerkumpul]), akses: { penyiapan: true, pemantauan: true, hasil: true } });
            await boleh.findAll('tr').find((t) => t.text().includes('Dimas')).trigger('click');
            await flushPromises();
            expect(boleh.find('[data-uji="lepas-paksa-tabel"]').exists()).toBe(true);
        });

        it('baris "(Tanpa divisi)" di peta divisi tidak mengosongkan saringan diam-diam', async () => {
            const w = await pasang({
                url: '/performance-appraisal/pemantauan?cycle=10',
                respons: respons([], { peta_panas: { status: ['BELUM_MULAI'], baris: [
                    { id_divisi: 111, divisi: 'IT', sel: [2], total: 2, terlambat: 1 },
                    { id_divisi: null, divisi: '(Tanpa divisi)', sel: [1], total: 1, terlambat: 0 },
                ] } }),
            });
            const panggilan = paApi.monitoringSiklus.mock.calls.length;
            w.vm.onDivisionChartClick({ dataIndex: 1 });
            await flushPromises();
            expect(paApi.monitoringSiklus.mock.calls.length).toBe(panggilan);

            w.vm.onDivisionChartClick({ dataIndex: 0 });
            await flushPromises();
            expect(paApi.monitoringSiklus).toHaveBeenLastCalledWith('10', expect.objectContaining({ id_divisi: '111' }));
        });
    });
});

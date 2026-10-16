import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RiwayatPage from './riwayat.vue';
import paApi from '../api/paApi.js';

vi.mock('../api/paApi.js', () => ({
    default: {
        riwayatAdmin: vi.fn(),
        detailHasil: vi.fn(),
        auditSiklus: vi.fn(),
    },
}));

const uiStubs = {
    HcisHeader: {
        props: ['title', 'description'],
        template: '<header><h1>{{ title }}</h1><p>{{ description }}</p><slot name="badge" /><slot name="actions" /><slot name="tabs" /><slot name="console" /></header>',
    },
    HcisStatGroup: {
        props: ['stats'],
        template: '<div class="stat-group"><div v-for="s in (stats || [])" :key="s.id" class="stat-item">{{ s.label }} {{ s.value }}</div><slot /></div>',
    },
    HcisStatCard: {
        props: ['label', 'value'],
        template: '<div class="stat-card"><span>{{ label }}</span><strong>{{ value }}</strong></div>',
    },
    HcisChart: {
        props: ['title', 'series', 'categories', 'data'],
        template: '<div class="hcis-chart-stub">{{ title }}</div>',
    },
    HcisCard: {
        props: ['title'],
        template: '<div class="hcis-card-stub"><slot name="header" /><slot />{{ title }}<slot name="footer" /></div>',
    },
    HcisBadge: {
        props: ['label'],
        template: '<span class="hcis-badge-stub">{{ label }}</span>',
    },
    HcisAvatar: {
        props: ['name'],
        template: '<div class="hcis-avatar-stub">{{ name }}</div>',
    },
    HcisPagination: {
        props: ['total', 'currentPage'],
        template: '<div class="hcis-pagination-stub" />',
    },
    HcisFilterBar: {
        props: ['search', 'quickChips'],
        template: '<div class="filter-bar"><slot /></div>',
    },
    HcisTable: {
        props: ['columns', 'rows'],
        template: '<table class="table-stub"><tbody><tr v-for="r in rows" :key="r.id_unique"><td>{{ r.Nama_Karyawan_Saat_Cycle }}</td></tr></tbody></table>',
    },
    HcisStatusBadge: {
        props: ['status', 'label'],
        template: '<span class="status-badge">{{ label }}</span>',
    },
    HcisButton: {
        props: ['label', 'loading', 'disabled'],
        template: '<button :disabled="disabled">{{ label }}</button>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: { template: '<div class="result-state" />' },
    /* Judul dan keterangannya ikut dirender: stub yang membuang keduanya membuat uji tidak
       bisa melihat apa yang sebenarnya dikatakan halaman kepada pemakainya. */
    HcisEmptyState: {
        props: ['title', 'description'],
        template: '<div class="empty-state">{{ title }} {{ description }}</div>',
    },
    HcisSkeleton: { template: '<div class="skeleton" />' },
    /* Lini masa audit kini digambar HcisTimeline. Stub ini menggambar judul, pelaku, waktu,
       dan meta tiap kejadian beserta slot isinya, supaya uji tetap membaca apa yang dikatakan. */
    HcisTimeline: {
        props: ['items'],
        template: '<div class="timeline-stub"><div v-for="it in (items || [])" :key="it.id" class="pa-audit-item">'
            + '<strong>{{ it.title }}</strong> <span>{{ it.name }}</span> <time>{{ it.time }}</time>'
            + '<span v-for="m in (it.meta || [])" :key="m.label">{{ m.label }}: {{ m.value }}</span>'
            + '<slot name="itemContent" :item="it" /></div></div>',
    },
    PaResultDialog: { template: '<div class="result-dialog-stub" />' },
};

describe('PerformanceAppraisalRiwayatPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.riwayatAdmin.mockResolvedValue({
            total: 1,
            total_halaman: 1,
            halaman: 1,
            baris: [
                {
                    Id_PA_Participant: 501,
                    Id_PA_Cycle: 1,
                    Nama_Karyawan_Saat_Cycle: 'Dian Sastro',
                    Kode_Karyawan: 'EMP099',
                    Nama_Jabatan_Saat_Cycle: 'Senior HR Specialist',
                    Nama_Divisi_Saat_Cycle: 'Human Capital',
                    Nama_Cycle: 'Semester 1 2025',
                    Tahun: 2025,
                    Nilai_KPI: 90.0,
                    Nilai_Kompetensi: 92.0,
                    Final_Score: 90.8,
                    Kode_Grade_Final: 'A',
                    Status_Hasil: 'DITERBITKAN',
                    dikalibrasi: false,
                },
            ],
            tren: [
                { cycle_id: 1, nama_cycle: 'Semester 1 2025', tahun: 2025, rerata: 85.4, jumlah_peserta: 40 },
            ],
            pilihan_saringan: {
                tahun: [2025, 2026],
                siklus: [{ id: 1, kode: 'PA-2025', nama: 'Semester 1 2025' }],
                grade: ['A', 'B+', 'B', 'C', 'D'],
                status_hasil: ['DITERBITKAN', 'DIHITUNG'],
            },
        });
    });

    it('renders header and fetches multi-year appraisal records', async () => {
        const wrapper = mount(RiwayatPage, {
            global: { stubs: uiStubs },
        });

        await vi.dynamicImportSettled();

        expect(wrapper.find('h1').text()).toContain('Riwayat Penilaian & Lini Masa Kinerja');
        expect(paApi.riwayatAdmin).toHaveBeenCalled();

        expect(wrapper.text()).toContain('Dian Sastro');
        expect(wrapper.text()).toContain('Total Rekam Jejak');
        expect(wrapper.text()).toContain('Semester 1 2025');
    });

    /*
     | HALAMAN INI MENYEBUT DIRINYA "AUDIT", DAN SEKARANG MEMANG MEMUATNYA.
     |
     | Jejak tindakan — pembatalan, pengecualian, override, kalibrasi, penerbitan — sudah lama
     | tersimpan dan sudah punya endpoint, tetapi satu-satunya jalan membacanya adalah dialog
     | di halaman daftar siklus. Yang mencari "siapa menggeser grade orang ini" membuka halaman
     | ini lebih dulu dan menemukan segalanya kecuali itu.
     |
     | Yang ditegaskan di sini bentuk PEMETAANNYA, bukan sekadar "ada barisnya": server
     | mengirim `rincian`, `sasaran`, `sebab`, dan `aktor_nama` — nama yang berbeda dari
     | tebakan pertama saya. Pemetaan yang meleset menghasilkan lini masa berisi tanda pisah,
     | dan tanda pisah tidak pernah tampak seperti bug.
     */
    it('memuat jejak audit cycle dan memetakan bentuk kejadian dari server', async () => {
        paApi.auditSiklus.mockResolvedValue({
            siklus: { Id_PA_Cycle: 7, Kode_Cycle: 'PA-2026-01' },
            kejadian: [{
                waktu: '2026-09-07 08:34:09',
                jenis: 'KALIBRASI',
                sasaran: 'Budi Santoso',
                sebab: 'Hasil forum kalibrasi HR',
                rincian: 'Grade B menjadi B_PLUS',
                aktor_nama: 'Aji',
                oleh_sistem: false,
            }],
            jenis_tersedia: ['KALIBRASI', 'SIKLUS_DIKUNCI'],
            cacah: { keputusan_manusia: 1, oleh_sistem: 0 },
            halaman: 1,
            per_halaman: 10,
            total: 1,
            total_halaman: 1,
        });

        const wrapper = mount(RiwayatPage, { global: { stubs: uiStubs } });
        await flushPromises();

        // Tanpa cycle terpilih, lini masa audit tidak punya arti — dan itu dikatakan.
        expect(paApi.auditSiklus).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Pilih satu siklus penilaian terlebih dahulu');

        wrapper.vm.selectedFilterValues.cycle_id = 7;
        await wrapper.vm.muatAudit({ halaman: 1 });
        await flushPromises();

        expect(paApi.auditSiklus).toHaveBeenCalled();
        expect(wrapper.text()).toContain('Kalibrasi grade');
        expect(wrapper.text()).toContain('Grade B menjadi B_PLUS');
        expect(wrapper.text()).toContain('Budi Santoso');
        expect(wrapper.text()).toContain('Aji');
        expect(wrapper.text()).toContain('Hasil forum kalibrasi HR');
    });

    /*
     | SATU BARIS = SATU PERISTIWA, BUKAN SATU ORANG.
     |
     | Pada siklus sungguhan, 99 dari 100 kejadian adalah satu tindakan massal yang sama —
     | "Peserta dikecualikan", oleh sistem, dengan sebab yang sama — dipecah menjadi satu
     | baris per orang. Tiga keputusan manusia yang justru dicari tenggelam di antaranya.
     |
     | Yang ditegaskan di sini: jumlahnya terbaca, dan namanya diwakili beberapa contoh —
     | bukan didaftar seluruhnya, yang hanya memindahkan tumpukan tadi ke dalam baris.
     */
    it('menampilkan tindakan massal sebagai satu peristiwa berjumlah', async () => {
        paApi.auditSiklus.mockResolvedValue({
            siklus: { Id_PA_Cycle: 7 },
            kejadian: [{
                waktu: '2026-09-07 06:42:07',
                waktu_awal: '2026-09-07 06:41:49',
                waktu_akhir: '2026-09-07 06:42:07',
                jumlah: 98,
                jenis: 'PESERTA_DIKECUALIKAN',
                rincian: 'Dikecualikan dari siklus',
                sasaran: null,
                sasaran_contoh: ['FIRMAN ANGGRAINI', 'TONO ANGGRAINI'],
                sasaran_lain: 96,
                sebab: null,
                aktor_nama: null,
                oleh_sistem: true,
            }],
            jenis_tersedia: ['PESERTA_DIKECUALIKAN'],
            cacah: { keputusan_manusia: 0, oleh_sistem: 98 },
            halaman: 1, per_halaman: 10, total: 1, total_halaman: 1,
        });

        const wrapper = mount(RiwayatPage, { global: { stubs: uiStubs } });
        await flushPromises();

        wrapper.vm.selectedFilterValues.cycle_id = 7;
        await wrapper.vm.muatAudit({ halaman: 1 });
        await flushPromises();

        // Satu baris, bukan sembilan puluh delapan.
        expect(wrapper.findAll('.pa-audit-item')).toHaveLength(1);
        expect(wrapper.text()).toContain('98 orang');
        expect(wrapper.text()).toContain('+96 lainnya');

        // Rentang waktunya terbaca: tindakan massal berlangsung beberapa menit.
        expect(wrapper.text()).toContain('2026-09-07 06:41:49');
    });
});

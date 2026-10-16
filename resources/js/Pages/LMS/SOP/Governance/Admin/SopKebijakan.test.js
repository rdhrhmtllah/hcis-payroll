import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import SopKebijakan from './SopKebijakan.vue';
import SopKebijakanStats from './Components/SopKebijakanStats.vue';

vi.mock('axios');

/* schedulers sengaja tidak ada di sini. Sejak halaman dipecah, status scheduler
   pindah ke /api/v1/sop/admin/setup-system/settings dan /summary tak lagi
   membawanya. */
const MOCK_SUMMARY = {
    total_rules: 4,
    active_rules: 2,
    attention_rules: 1,
    active_runs: 0,
};

const MOCK_OPTIONS = {
    packages: [
        { Id_SOP_Paket: 'pkg-1', Nama_Paket: 'SOP Keamanan', Mode_Distribusi: 'PUBLIK' },
        { Id_SOP_Paket: 'pkg-2', Nama_Paket: 'SOP Finance', Mode_Distribusi: 'TERBATAS' },
    ],
    karyawan: [
        { Kode_Karyawan: 'K001', Nama: 'Andi', No_Induk_Karyawan: '240001', Jabatan: 'Engineer', Departemen: 'Dev' },
        { Kode_Karyawan: 'K002', Nama: 'Budi', No_Induk_Karyawan: '240002', Jabatan: 'Accountant', Departemen: 'Accounting' },
    ],
    karyawan_total: 2,
    filters: {
        divisi: ['IT', 'Finance', 'HR'],
        departemen: ['Dev', 'Accounting', 'Talent'],
        level: ['Staff', 'Lead', 'Manager'],
        jabatan: ['Software Engineer', 'Accountant'],
        lokasi: ['Head Office', 'Site A'],
    },
};

/* Sejak DDL-2026-053 aturan tak lagi punya Id_SOP_Paket/Nama_Paket tunggal:
   daftar paketnya datang sebagai array Paket berikut Jumlah_Paket. */
const MOCK_RULES = [
    {
        Id_SOP_Assignment_Rule: 'rule-1',
        Nama_Aturan: 'SOP IT Standard',
        Jumlah_Paket: 1,
        Paket: [{ Id_SOP_Paket: 'pkg-1', Nama_Paket: 'SOP Keamanan', Mode_Distribusi: 'PUBLIK' }],
        Status_Aturan: 'ACTIVE',
        Last_Apply_Status: 'SUCCESS',
        Draft_Berubah: false,
    },
    {
        Id_SOP_Assignment_Rule: 'rule-2',
        Nama_Aturan: 'SOP Accounting Rule',
        Jumlah_Paket: 1,
        Paket: [{ Id_SOP_Paket: 'pkg-2', Nama_Paket: 'SOP Finance', Mode_Distribusi: 'TERBATAS' }],
        Status_Aturan: 'DRAFT',
        Last_Apply_Status: 'NEEDS_ATTENTION',
        Draft_Berubah: true,
        Last_Apply_Error: 'Karyawan tidak ditemukan',
    },
];

/* Riwayat kini berbentuk pembungkus: baris induk plus baris anak per paket,
   berikut angka pemotongan yang WAJIB dinyatakan layar. */
const MOCK_RUNS = {
    rows: [
        {
            Id_SOP_Automation_Run: 'run-1',
            Id_Run_Induk: null,
            Is_Anak: false,
            Tipe_Run: 'RULE_APPLY',
            Trigger_Run: 'ACTIVATION',
            Status_Run: 'SUCCESS',
            Total_Ditambahkan: 12,
            Total_Dicabut: 0,
            Total_Dilewati: 2,
            Nama_Paket: null,
            Nama_Aturan: 'SOP IT Standard',
            Queued_At: '2026-08-20T08:00:00Z',
        },
        {
            Id_SOP_Automation_Run: 'run-1a',
            Id_Run_Induk: 'run-1',
            Is_Anak: true,
            Tipe_Run: 'RULE_APPLY',
            Trigger_Run: 'ACTIVATION',
            Status_Run: 'SUCCESS',
            Total_Ditambahkan: 12,
            Total_Dicabut: 0,
            Total_Dilewati: 0,
            Nama_Paket: 'SOP Keamanan',
            Nama_Aturan: 'SOP IT Standard',
            Queued_At: '2026-08-20T08:00:00Z',
        },
    ],
    ditampilkan: 1,
    total_induk: 7,
};

function mockApi() {
    axios.get = vi.fn((url) => {
        if (url.includes('/summary')) return Promise.resolve({ data: { success: true, result: MOCK_SUMMARY } });
        if (url.includes('/options')) return Promise.resolve({ data: { success: true, result: MOCK_OPTIONS } });
        if (url.includes('/rules/rule-1')) {
            return Promise.resolve({
                data: {
                    success: true,
                    result: {
                        Id_SOP_Assignment_Rule: 'rule-1',
                        Nama_Aturan: 'SOP IT Standard',
                        Jumlah_Paket: 1,
                        Paket: [
                            {
                                Id_SOP_Paket: 'pkg-1',
                                Nama_Paket: 'SOP Keamanan',
                                Mode_Distribusi: 'PUBLIK',
                                Urutan: 1,
                                override: false,
                                schedule: null,
                            },
                        ],
                        Status_Aturan: 'ACTIVE',
                        config: {
                            groups: [{ divisi: ['IT'], dept: [], level: [], jabatan: [], lokasi: [], tipe: 'Semua' }],
                            include_karyawan: ['K001'],
                            exclude_karyawan: [],
                            exclude_groups: [
                                { divisi: [], dept: [], level: ['Manager'], jabatan: [], lokasi: [], tipe: 'Semua' },
                            ],
                            schedule: { deadline_mode: 'NONE', deadline_days: 7, reminder_enabled: false },
                        },
                    },
                },
            });
        }
        if (url.includes('/rules')) return Promise.resolve({ data: { success: true, result: MOCK_RULES } });
        if (url.includes('/runs')) return Promise.resolve({ data: { success: true, result: MOCK_RUNS } });
        /* Sengaja TIDAK ada handler /settings. Kalau halaman ini memanggilnya
           lagi, hasilnya result: [] dan uji penjaga di bawah akan gagal. */
        return Promise.resolve({ data: { success: true, result: [] } });
    });
}

describe('SopKebijakan.vue', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        mockApi();
    });

    it('mounts and renders header, stats, and initial rules tab', async () => {
        const wrapper = mount(SopKebijakan, {
            global: {
                stubs: {
                    HcisHeader: {
                        template: '<header class="hcis-header-stub"><slot name="badge"/><slot name="actions"/><h1>{{ title }}</h1><slot name="tabs"/></header>',
                        props: ['title', 'kicker', 'description', 'icon', 'sentiment', 'size', 'animated'],
                    },
                },
            },
        });

        await flushPromises();

        expect(wrapper.text()).toContain('Otomasi Dokumen');
        expect(wrapper.findComponent(SopKebijakanStats).exists()).toBe(true);
        expect(wrapper.text()).toContain('Daftar Aturan');
        expect(wrapper.text()).toContain('SOP IT Standard');
        expect(wrapper.text()).toContain('SOP Accounting Rule');
    });

    /* Penjaga pemisahan halaman. Kebijakan Global dan Otomasi Terjadwal pindah
       ke SopSetupSystem.vue; kalau salah satunya bocor kembali ke sini, dua
       halaman akan menulis setelan yang sama lewat gerbang yang berbeda. */
    it('no longer exposes the global policy and scheduler tabs', async () => {
        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();

        expect(wrapper.vm.tabItems.map((tab) => tab.key)).toEqual(['rules', 'history']);
        expect(wrapper.vm.settings).toBeUndefined();
        expect(wrapper.vm.automationJobs).toBeUndefined();
        expect(wrapper.vm.saveSettings).toBeUndefined();

        const requestedUrls = axios.get.mock.calls.map(([url]) => url);
        expect(requestedUrls.some((url) => url.includes('/settings'))).toBe(false);
    });

    /* Tombol pemicu tetap ada di halaman ini meski kartu scheduler-nya pindah:
       rutenya bergerbang dua kunci justru supaya admin modul bisa menjalankan
       evaluasi tepat setelah mengubah aturan. */
    it('can still trigger a rule evaluation run from the rules tab', async () => {
        axios.post = vi.fn(() => Promise.resolve({ data: { success: true, result: { run_id: 'run-9' } } }));

        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        await wrapper.vm.runNow('rule-scan');

        expect(axios.post).toHaveBeenCalledWith('/api/v1/sop/admin/kebijakan/runs/rule-scan');
    });

    it('filters rules list with search input', async () => {
        const wrapper = mount(SopKebijakan, {
            global: {
                stubs: {
                    HcisHeader: true,
                },
            },
        });

        await flushPromises();

        expect(wrapper.vm.filteredRules).toHaveLength(2);

        wrapper.vm.searchRule = 'Accounting';
        expect(wrapper.vm.filteredRules).toHaveLength(1);
        expect(wrapper.vm.filteredRules[0].Nama_Aturan).toBe('SOP Accounting Rule');
    });

    it('opens a rule and populates form correctly', async () => {
        const wrapper = mount(SopKebijakan, {
            global: {
                stubs: {
                    HcisHeader: true,
                },
            },
        });

        await flushPromises();

        await wrapper.vm.openRule('rule-1');
        await flushPromises();

        expect(wrapper.vm.selectedId).toBe('rule-1');
        expect(wrapper.vm.form.nama_aturan).toBe('SOP IT Standard');
        expect(wrapper.vm.form.paket).toHaveLength(1);
        expect(wrapper.vm.form.paket[0].Id_SOP_Paket).toBe('pkg-1');
        expect(wrapper.vm.form.include_karyawan).toEqual(['K001']);
    });

    it('switches to history tab and displays execution runs table properly', async () => {
        const wrapper = mount(SopKebijakan, {
            global: {
                stubs: {
                    HcisHeader: true,
                },
            },
        });

        await flushPromises();

        wrapper.vm.activeTab = 'history';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Riwayat Eksekusi Otomasi');
        expect(wrapper.text()).toContain('Aktivasi Aturan');
        expect(wrapper.text()).toContain('SOP Keamanan');
        expect(wrapper.vm.filteredRuns).toHaveLength(2);
    });

    /* Plafon riwayat wajib dinyatakan. Jejak audit yang terpotong terbaca
       persis seperti jejak yang lengkap kalau angkanya disembunyikan. */
    it('menyatakan pemotongan riwayat, bukan memotongnya diam-diam', async () => {
        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        wrapper.vm.activeTab = 'history';
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.runsDitampilkan).toBe(1);
        expect(wrapper.vm.runsTotal).toBe(7);
        expect(wrapper.text()).toContain('Menampilkan 1 eksekusi terakhir dari 7');
    });

    /* Paket yang sudah diklaim aturan LAIN tidak boleh ditawarkan: server
       menolaknya, dan menawarkan lalu menolak membuat admin menebak. */
    it('menyembunyikan paket yang sudah dimiliki aturan lain', async () => {
        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        wrapper.vm.newRule();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.availablePackages).toHaveLength(0);
    });

    it('menyembunyikan paket yang sudah ada di daftar aturan ini', async () => {
        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        await wrapper.vm.openRule('rule-1');
        await flushPromises();

        // pkg-1 sudah di form, pkg-2 milik rule-2 -> tak satu pun tersisa.
        expect(wrapper.vm.availablePackages.map((p) => p.Id_SOP_Paket)).toEqual([]);
    });

    it('mengirim daftar paket berikut urutannya saat menyimpan', async () => {
        axios.post = vi.fn(() => Promise.resolve({ data: { success: true, result: { id: 'rule-9' } } }));

        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        wrapper.vm.newRule();
        wrapper.vm.form.nama_aturan = 'Aturan Finance';
        wrapper.vm.form.paket = [
            { Id_SOP_Paket: 'pkg-2', Nama_Paket: 'SOP Finance', override: false, schedule: null },
            { Id_SOP_Paket: 'pkg-1', Nama_Paket: 'SOP Keamanan', override: false, schedule: null },
        ];

        await wrapper.vm.saveDraft(false);

        const [, payload] = axios.post.mock.calls[0];
        expect(payload.paket).toEqual([
            { id_paket: 'pkg-2', urutan: 1, override: false, schedule: null },
            { id_paket: 'pkg-1', urutan: 2, override: false, schedule: null },
        ]);
        expect(payload.id_paket).toBeUndefined();
    });

    it('menolak menyimpan aturan tanpa satu pun paket', async () => {
        axios.post = vi.fn();

        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        wrapper.vm.newRule();
        wrapper.vm.form.nama_aturan = 'Aturan Tanpa Paket';

        const hasil = await wrapper.vm.saveDraft();

        expect(hasil).toBe(false);
        expect(axios.post).not.toHaveBeenCalled();
    });

    /* resetRuleFilters sempat terdefinisi DUA KALI di objek methods; yang kedua
       diam-diam menang. Uji ini menuntut yang tersisa benar-benar bekerja. */
    it('reset filter aturan mengosongkan pencarian dan status', async () => {
        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        wrapper.vm.searchRule = 'Accounting';
        wrapper.vm.statusFilter = 'DRAFT';

        wrapper.vm.resetRuleFilters();

        expect(wrapper.vm.searchRule).toBe('');
        expect(wrapper.vm.statusFilter).toBe('ALL');
    });

    /* Pengecualian berbasis kriteria harus bolak-balik utuh: dimuat dari server,
       lalu dikirim kembali apa adanya. Kalau ia hilang di salah satu arah,
       admin melihat pengecualiannya di layar tapi server tak pernah menerimanya. */
    it('memuat dan mengirim kembali grup pengecualian', async () => {
        axios.post = vi.fn(() => Promise.resolve({ data: { success: true, result: { id: 'rule-1' } } }));
        axios.put = vi.fn(() => Promise.resolve({ data: { success: true, result: { id: 'rule-1' } } }));

        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        await wrapper.vm.openRule('rule-1');
        await flushPromises();

        expect(wrapper.vm.form.exclude_groups).toHaveLength(1);
        expect(wrapper.vm.form.exclude_groups[0].level).toEqual(['Manager']);

        await wrapper.vm.saveDraft(false);

        const [, payload] = axios.put.mock.calls[0];
        expect(payload.config.exclude_groups).toEqual([
            { divisi: [], dept: [], level: ['Manager'], jabatan: [], lokasi: [], tipe: 'Semua' },
        ]);
    });

    it('aturan baru berangkat tanpa pengecualian sama sekali', async () => {
        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        wrapper.vm.newRule();

        expect(wrapper.vm.form.exclude_groups).toEqual([]);
        expect(wrapper.vm.form.groups).toHaveLength(1);
    });

    /* Pemilih orang menggantikan textarea NIK. Kode yang tersimpan tapi tak lagi
       ada di daftar aktif WAJIB tetap muncul — pengecualian yang hilang dari
       pandangan tapi masih bekerja adalah yang paling sulit dilacak ketika
       seseorang bertanya kenapa ia tak menerima SOP. */
    it('menawarkan seluruh karyawan aktif sebagai pilihan', async () => {
        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();

        const opsi = wrapper.vm.opsiOrang([]);
        expect(opsi).toHaveLength(2);
        expect(opsi[0].label).toContain('Andi');
        expect(opsi[0].label).toContain('240001');
    });

    it('tetap menampilkan kode tersimpan yang sudah tak aktif', async () => {
        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();

        const opsi = wrapper.vm.opsiOrang(['K001', 'K999']);
        const nilai = opsi.map((o) => o.value);

        expect(nilai).toContain('K999');
        expect(opsi.find((o) => o.value === 'K999').label).toContain('tidak aktif');
    });

    it('mengirim pilihan orang sebagai array kode, bukan teks', async () => {
        axios.post = vi.fn(() => Promise.resolve({ data: { success: true, result: { id: 'rule-9' } } }));

        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        wrapper.vm.newRule();
        wrapper.vm.form.nama_aturan = 'Aturan Orang';
        wrapper.vm.form.paket = [{ Id_SOP_Paket: 'pkg-1', override: false, schedule: null }];
        wrapper.vm.form.exclude_karyawan = ['K002'];

        await wrapper.vm.saveDraft(false);

        const [, payload] = axios.post.mock.calls[0];
        expect(payload.config.exclude_karyawan).toEqual(['K002']);
        expect(payload.config.include_karyawan).toEqual([]);
    });

    it('menghitung jumlah orang dari pilihan, bukan dari teks', async () => {
        const wrapper = mount(SopKebijakan, {
            global: { stubs: { HcisHeader: true } },
        });

        await flushPromises();
        wrapper.vm.newRule();
        wrapper.vm.form.include_karyawan = ['K001', 'K002'];

        expect(wrapper.vm.includeCount).toBe(2);
        expect(wrapper.vm.excludeCount).toBe(0);
    });
});

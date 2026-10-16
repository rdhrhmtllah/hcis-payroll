import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CyclePage from './siklus.vue';

const paApiMock = vi.hoisted(() => ({
    daftarSiklus: vi.fn(),
    daftarPeserta: vi.fn(),
    keranjangTertunda: vi.fn(),
    usulanPenilai: vi.fn(),
    tugaskanPenilai: vi.fn(),
    hitungPeserta: vi.fn(),
    siapkanSiklus: vi.fn(),
    kunciSiklus: vi.fn(),
    bukaPengisian: vi.fn(),
    tutupPengisian: vi.fn(),
    hitungSiklus: vi.fn(),
    ringkasanPenerbitan: vi.fn(),
    terbitkan: vi.fn(),
    terbitkanPeserta: vi.fn(),
    tutupPenerbitan: vi.fn(),
    detailSiklus: vi.fn(),
    buatSiklus: vi.fn(),
    perbaruiSiklus: vi.fn(),
    auditSiklus: vi.fn(),
}));

vi.mock('../api/paApi.js', () => ({ default: paApiMock }));

const cycle = {
    Id_PA_Cycle: 12,
    Kode_Cycle: 'PA-2026',
    Nama_Cycle: 'PA Semester 1',
    Tahun: 2026,
    Status_Cycle: 'PREPARED',
    peserta: { diikutkan: 1, dikecualikan: 0, tertunda: 1 },
    operasi_boleh: ['BUAT_PENUGASAN', 'KUNCI_SIKLUS', 'HITUNG_HASIL'],
};

const uiStubs = {
    HcisBadge: { template: '<span><slot /></span>' },
    HcisButton: {
        props: ['label', 'href', 'disabled', 'loading'],
        emits: ['click'],
        template: '<a v-if="href" :href="href">{{ label }}</a><button v-else :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisCard: { template: '<article><slot name="header" /><slot /><slot name="footer" /></article>' },
    HcisDialog: {
        props: ['modelValue'],
        template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>',
    },
    HcisHeader: { template: '<header><slot name="actions" /><slot name="console" /></header>' },
    HcisIcon: { template: '<i />' },
    HcisResultState: { props: ['title', 'description'], template: '<div>{{ title }} {{ description }}</div>' },
    HcisStatCard: { template: '<div />' },
    HcisStatusBadge: { template: '<span />' },
    PaAssignmentDialog: {
        name: 'PaAssignmentDialog',
        props: ['modelValue', 'participant', 'categories', 'loading'],
        template:
            '<div v-if="modelValue" class="assignment-dialog">{{ participant?.Kode_Karyawan }} {{ categories.length }} kategori<button @click="$emit(\'save\')">Simpan penugasan</button></div>',
    },
    PaPendingDrawer: {
        name: 'PaPendingDrawer',
        props: ['modelValue', 'items', 'canAssign'],
        template:
            '<div v-if="modelValue" class="pending-drawer"><button v-if="canAssign && items.length" @click="$emit(\'assign\', items[0])">Kelola penilai</button></div>',
    },
    PaResultDialog: true,
    PaCycleSetupDialog: {
        name: 'PaCycleSetupDialog',
        props: ['modelValue'],
        template: '<div v-if="modelValue" class="cycle-setup-dialog"><button @click="$emit(\'save\', {})">Simpan siklus</button></div>',
    },
    PaCycleAuditDialog: {
        name: 'PaCycleAuditDialog',
        props: ['modelValue', 'events'],
        template: '<div v-if="modelValue" class="cycle-audit-dialog">{{ events.length }} kejadian</div>',
    },
    PaCycleFillingDialog: {
        name: 'PaCycleFillingDialog',
        props: ['modelValue', 'mode'],
        template: '<div v-if="modelValue" class="cycle-filling-dialog"><button @click="$emit(\'confirm\')">{{ mode === \'open\' ? \'Buka pengisian\' : \'Tutup pengisian\' }}</button></div>',
    },
    PaParticipantsDialog: {
        name: 'PaParticipantsDialog',
        props: ['modelValue', 'items', 'loading', 'error', 'canAssign'],
        template: '<div v-if="modelValue" class="participant-dialog">{{ items.length }} peserta {{ error }}</div>',
    },
};

beforeEach(() => {
    vi.clearAllMocks();
    paApiMock.daftarSiklus.mockResolvedValue({ siklus: [cycle] });
    paApiMock.daftarPeserta.mockResolvedValue({
        peserta: [{ Id_PA_Participant: 501 }],
        halaman: 1,
        total: 1,
        total_halaman: 1,
        ringkasan: { DIIKUTKAN: 1, DIKECUALIKAN: 0, TERTUNDA: 0 },
        dipangkas: false,
    });
    paApiMock.keranjangTertunda.mockResolvedValue({
        tertunda: [
            {
                Id_PA_Participant: 701,
                Kode_Karyawan: 'A0701',
                Nama_Karyawan_Saat_Cycle: 'Peserta Tertunda',
            },
        ],
    });
    paApiMock.usulanPenilai.mockResolvedValue({
        usulan: {
            SELF: {
                kode_kategori: 'SELF',
                nama_kategori: 'Penilaian Diri',
                min_rater: 1,
                maks_rater: 1,
                terpasang: 0,
                butuh_manual: false,
                izinkan_manual: false,
                calon: [],
            },
        },
    });
    paApiMock.tugaskanPenilai.mockResolvedValue({ dibuat: 0, kategori_dihitung_ulang: [] });
    paApiMock.bukaPengisian.mockResolvedValue({ cycle_id: 12, status_lama: 'LOCKED', status: 'OPEN', dibuka_ulang: false });
    paApiMock.tutupPengisian.mockResolvedValue({ cycle_id: 12, status: 'CLOSED', penugasan_belum_selesai: 3 });
    paApiMock.ringkasanPenerbitan.mockResolvedValue({ terbit: 0, dihitung: 1, tertahan: 0, belum_dihitung: 0, total: 1 });
    paApiMock.terbitkan.mockResolvedValue({ diterbitkan: 1, sudah_terbit: 0, tertahan: 0, belum_dihitung: 0, total_peserta: 1, siap_ditutup: true });
    paApiMock.tutupPenerbitan.mockResolvedValue({ cycle_id: 12, status: 'PUBLISHED', published_at: '2026-09-01 15:08:57' });
    paApiMock.terbitkanPeserta.mockResolvedValue({ participant_id: 701, result_id: 902, status: 'TERBIT' });
    paApiMock.detailSiklus.mockResolvedValue({
        siklus: cycle,
        operasi_boleh: ['BUAT_PENUGASAN'],
        boleh_ubah_periode: true,
        peserta: { total: 1 },
    });
    paApiMock.buatSiklus.mockResolvedValue({ cycle_id: 1393, kode_cycle: 'PA-2026-H1', status: 'DRAFT' });
    paApiMock.perbaruiSiklus.mockResolvedValue({ cycle_id: 12, periode_berubah: [], perlu_siapkan_ulang: false });
    paApiMock.auditSiklus.mockResolvedValue({
        siklus: cycle,
        kejadian: [{ waktu: '2026-09-01 10:00:00', jenis: 'KALIBRASI', sasaran: 'Peserta' }],
        jenis_tersedia: ['KALIBRASI'],
        cacah: { keputusan_manusia: 1, oleh_sistem: 0 },
        batas: { konfigurasi_tanpa_nilai_lama: false },
        halaman: 1,
        per_halaman: 10,
        total: 1,
        total_halaman: 1,
        dipangkas: false,
    });
});

/*
 | SELEKTORNYA DISELARASKAN, BUKAN PERILAKUNYA.
 |
 | Halaman ini diuji dengan SELURUH dialognya di-stub (lihat `uiStubs` di atas), jadi
 | selektor seperti `.participant-dialog` dan `.assignment-dialog` menunjuk markup
 | STUB — bukan kelas komponen sungguhan, yang bernama `.pa-participants` dan
 | `.pa-assignment`. Keduanya benar di tempatnya masing-masing, dan menukarnya
 | membuat uji mencari sesuatu yang memang tidak pernah dirender di sini.
 |
 | Yang DISELARASKAN hanyalah yang menunjuk markup halaman sungguhan: kelas kartu
 | statistik dan label tombol.
 |
 | Label tombol dan judul blok mengikuti salinan halaman apa adanya — saat ini
 | 'Participants', 'Setup Cycle', 'Audit Log', dan blok 'MODUL WORKFLOW TERKAIT'
 | berisi 'Rater Assignment' serta 'Monitoring Pengisian'. Yang dijaga di sini bukan
 | pilihan katanya, melainkan bahwa jalur menuju halaman Penugasan Penilai yang lama
 | memang sudah tidak ada (DML-2026-100) sementara jalur pemantauan tetap ada.
 */
describe('Performance Appraisal cycle participant lifecycle', () => {
    it('keeps participant API orchestration in the page/composable boundary', async () => {
        const wrapper = mount(CyclePage, { global: { stubs: uiStubs } });
        await flushPromises();

        const pesertaButton = wrapper.findAll('button').find((button) => button.text() === 'Participants');
        expect(pesertaButton).toBeTruthy();

        await pesertaButton.trigger('click');
        await flushPromises();

        expect(paApiMock.daftarPeserta).toHaveBeenCalledWith(12, expect.objectContaining({ halaman: 1, per_halaman: 25 }));
        expect(wrapper.find('.participant-dialog').text()).toContain('1 peserta');
        expect(wrapper.findComponent({ name: 'PaParticipantsDialog' }).props('canAssign')).toBe(true);
    });

    it('mengorkestrasi usulan dan simpan penugasan dari keranjang peserta', async () => {
        const wrapper = mount(CyclePage, { global: { stubs: uiStubs } });
        await flushPromises();

        await wrapper.find('.pa-bento-stat.is-pending').trigger('click');
        await flushPromises();
        await wrapper.find('.pending-drawer button').trigger('click');
        await flushPromises();

        expect(paApiMock.usulanPenilai).toHaveBeenCalledWith(701);
        expect(wrapper.find('.assignment-dialog').text()).toContain('1 kategori');

        await wrapper.find('.assignment-dialog button').trigger('click');
        await flushPromises();

        /* TIDAK ADA PENILAI BARU = TIDAK ADA PERMINTAAN.
        
           Uji ini menekan Simpan tanpa memilih siapa pun, dan dulu itu mengirim daftar
           kosong ke server — yang memvalidasi `penilai` sebagai `required|array|min:1`
           lalu menjawab "The penilai field is required." Pesannya membingungkan karena
           tidak ada yang salah: admin memang hanya menutup dialog.
        
           Sekarang permintaannya tidak dikirim sama sekali. Yang tetap dijaga di sini
           adalah ORKESTRASINYA — usulan diminta, dialog terbuka, keranjang disegarkan —
           sedangkan jalur simpan yang sungguhan (dengan penilai terpilih) punya ujinya
           sendiri di composables/usePaAssignment.test.js. */
        expect(paApiMock.tugaskanPenilai).not.toHaveBeenCalled();
        expect(paApiMock.keranjangTertunda).toHaveBeenCalledTimes(2);
    });

    it('tetap membuka keranjang meski ringkasan siklus menunjukkan nol tertunda', async () => {
        paApiMock.daftarSiklus.mockResolvedValue({
            siklus: [{ ...cycle, peserta: { ...cycle.peserta, tertunda: 0 } }],
        });

        const wrapper = mount(CyclePage, { global: { stubs: uiStubs } });
        await flushPromises();

        const pendingButton = wrapper.find('.pa-bento-stat.is-pending');
        expect(pendingButton.attributes('disabled')).toBeUndefined();

        await pendingButton.trigger('click');
        await flushPromises();

        expect(paApiMock.keranjangTertunda).toHaveBeenCalledWith(12);
        expect(wrapper.findComponent({ name: 'PaPendingDrawer' }).props('items')).toHaveLength(1);
    });

    it('tidak membiarkan respons keranjang lama menimpa siklus yang sedang dibuka', async () => {
        let resolveFirst;
        const firstRequest = new Promise((resolve) => {
            resolveFirst = resolve;
        });
        const latestItems = [{ Id_PA_Participant: 702, Nama_Karyawan_Saat_Cycle: 'Terbaru' }];

        paApiMock.keranjangTertunda
            .mockImplementationOnce(() => firstRequest)
            .mockResolvedValueOnce({ tertunda: latestItems });

        const wrapper = mount(CyclePage, { global: { stubs: uiStubs } });
        await flushPromises();

        const pendingButton = wrapper.find('.pa-bento-stat.is-pending');
        await pendingButton.trigger('click');
        await pendingButton.trigger('click');
        await flushPromises();

        const drawer = wrapper.findComponent({ name: 'PaPendingDrawer' });
        expect(drawer.props('items')).toEqual(latestItems);

        resolveFirst({ tertunda: [{ Id_PA_Participant: 701, Nama_Karyawan_Saat_Cycle: 'Lama' }] });
        await flushPromises();

        expect(drawer.props('items')).toEqual(latestItems);
    });

    it('menampilkan navigasi gateway modular ke halaman-halaman fase siklus', async () => {
        const lockedCycle = { ...cycle, Status_Cycle: 'LOCKED', operasi_boleh: ['BUKA_PENGISIAN'] };
        paApiMock.daftarSiklus.mockResolvedValue({ siklus: [lockedCycle] });

        const wrapper = mount(CyclePage, { global: { stubs: uiStubs } });
        await flushPromises();

        expect(wrapper.text()).toContain('MODUL WORKFLOW TERKAIT');
        /* Sejak dikunci: Revisi Penugasan, bukan Rater Assignment (milik Setup & Assignment). */
        expect(wrapper.text()).toContain('Revisi Penugasan');
        expect(wrapper.text()).not.toContain('Rater Assignment');
        expect(wrapper.text()).toContain('Monitoring Pengisian');
        /* Halaman Penugasan Penilai dibubarkan; pekerjaannya pindah ke Tahap 5 wizard. */
        expect(wrapper.find('a[href="/performance-appraisal/penilai?cycle=12"]').exists()).toBe(false);
        expect(wrapper.find('a[href="/performance-appraisal/pemantauan?cycle=12"]').exists()).toBe(true);
    });

    /* Sesudah dikunci yang dibutuhkan revisi — modal di halaman ini, bukan wizard penyiapan. */
    it('siklus terkunci menawarkan Revisi Penugasan sebagai modal, bukan ke wizard', async () => {
        const lockedCycle = { ...cycle, Status_Cycle: 'LOCKED', operasi_boleh: ['BUKA_PENGISIAN', 'TAMBAH_PENILAI'] };
        paApiMock.daftarSiklus.mockResolvedValue({ siklus: [lockedCycle] });

        const wrapper = mount(CyclePage, {
            global: {
                stubs: {
                    ...uiStubs,
                    PaRevisiPenugasanModal: { props: ['modelValue', 'cycle'], template: '<div v-if="modelValue" class="stub-rpn">{{ cycle?.Id_PA_Cycle }}</div>' },
                },
            },
        });
        await flushPromises();

        const chip = wrapper.findAll('a.pa-lanjut-chip').find((a) => a.text().includes('Revisi Penugasan'));
        expect(chip).toBeTruthy();
        expect(wrapper.text()).not.toContain('Rater Assignment');
        await chip.trigger('click');
        expect(wrapper.find('.stub-rpn').text()).toBe('12');
    });

    it('membuka audit siklus melalui endpoint audit dan mempertahankan kejadian server', async () => {
        const wrapper = mount(CyclePage, { global: { stubs: uiStubs } });
        await flushPromises();

        const auditButton = wrapper.findAll('button').find((button) => button.text() === 'Audit Log');
        expect(auditButton).toBeTruthy();
        await auditButton.trigger('click');
        await flushPromises();

        expect(paApiMock.auditSiklus).toHaveBeenCalledWith(12, expect.objectContaining({ halaman: 1, per_halaman: 10 }));
        expect(wrapper.find('.cycle-audit-dialog').text()).toContain('1 kejadian');
    });

    /*
     | PENGUNCIAN TIDAK LAGI ADA DI HALAMAN INI.
     |
     | Ia menjadi tombol penutup Langkah 5, tempat admin memang sedang meninjau penugasan —
     | keputusan yang sama dengan "bekukan siklusnya". Sebelumnya admin menyelesaikan Langkah 5,
     | dipulangkan ke daftar siklus, lalu harus mencari tombol kedua yang menanyakan hal yang
     | sama. Uji ini menahan tombol itu supaya tidak diam-diam kembali.
     */
    it('tidak lagi menawarkan penguncian dari daftar siklus', async () => {
        const prepCycle = {
            ...cycle,
            operasi_boleh: ['SUNTING_PESERTA', 'KUNCI_SIKLUS'],
        };
        paApiMock.daftarSiklus.mockResolvedValue({ siklus: [prepCycle] });

        const wrapper = mount(CyclePage, { global: { stubs: uiStubs } });
        await flushPromises();

        /* Yang ditegaskan KETIADAAN tombol penguncian, bukan keberadaan tombol lain:
           label di halaman ini sering diperhalus, dan penjaga yang merah karena kata
           berubah melatih orang mengabaikan warna merah. */
        const kunciButton = wrapper.findAll('button')
            .find((button) => /Kunci\s+(Siklus|Cycle)/i.test(button.text()));
        expect(kunciButton).toBeUndefined();

        // Izin servernya TIDAK dicabut — yang berpindah tempatnya, bukan haknya. Karena itu
        // yang diuji keberadaan tombolnya, bukan `operasi_boleh`.
        expect(wrapper.text()).not.toContain('Ya, Kunci Siklus');
    });
});

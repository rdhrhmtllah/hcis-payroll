import { shallowMount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import TamuForm from './TamuForm.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

vi.mock('element-plus', () => ({
    ElMessage: {
        error: vi.fn(),
        success: vi.fn(),
        warning: vi.fn(),
    },
}));

describe('TamuForm design-system composition', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({
            data: {
                success: true,
                result: {
                    plat_codes: ['B'],
                    vehicle_types: [{ Id: 1, Nama_Vehicle_Type: 'Mobil' }],
                    keperluan: [{ Id_Master_Purpose: 2, Nama_Keperluan: 'Meeting' }],
                    waktu_estimasi: ['1 jam'],
                    jenis_identitas: ['KTP'],
                },
            },
        });
    });

    it('uses official HCIS controls for the complete guest form', () => {
        const source = readFileSync(
            path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.vue'),
            'utf8',
        );

        expect(source.match(/<HcisCard\b/g)?.length).toBeGreaterThanOrEqual(4);
        expect(source.match(/<HcisField\b/g)?.length).toBeGreaterThanOrEqual(8);
        expect(source).toContain('<HcisPlateInput');
        expect(source).toContain('<HcisPhoneInput');
        expect(source).toContain('<HcisDatePicker');
        expect(source).not.toContain('<HcisInputNumber');
        expect(source).toContain('jumlahOrang');
        expect(source).toContain('visitor-form-grid--transport');
        expect(source).toContain('<HcisTextarea');
        expect(source).toContain('<HcisDialog');
        expect(source).toContain('<HcisResultState');
        expect(source).toContain('v-model="form.no_plat"');
        expect(source).not.toContain('guest-plat-prefix');
        expect(source).not.toContain('guest-plat-suffix');
        expect(source).not.toMatch(/<el-/);
        expect(source).not.toMatch(/<button\b/);
    });

    it('preserves the guest payload contract and confirmation gate', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rina Pratama',
            no_hp: '08123456789',
            instansi_asal: 'PT Contoh',
            keperluan: 'Lain-lain',
            keperluan_lain: 'Audit lokasi',
            pic_tujuan: 'Budi / HR',
            // DDL-2026-121: tanggal_kunjungan diganti Estimasi Masuk/Keluar.
            estimasi_masuk: '2026-08-24 08:00:00',
            estimasi_keluar: '2026-08-24 17:00:00',
            anggota_lain: [{ nama: 'Bambang' }],
            jenis_kendaraan: 'Mobil',
            no_plat: 'B1234XYZ',
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isFormValid).toBe(true);
        expect(wrapper.vm.buildPayload()).toMatchObject({
            nama_tamu: 'Rina Pratama',
            keperluan: 'Audit lokasi',
            plat_prefix: 'B',
            plat_angka: '1234',
            plat_suffix: 'XYZ',
            no_plat: 'B 1234 XYZ',
            jumlah_tamu: 2,
            waktu_mulai_kunjungan: '2026-08-24 08:00:00',
            waktu_selesai_kunjungan: '2026-08-24 17:00:00',
        });

        wrapper.vm.openConfirmModal();
        expect(wrapper.vm.showConfirmModal).toBe(true);
        expect(wrapper.vm.pendingPayload.pic_tujuan).toBe('Budi / HR');
        expect(wrapper.vm.idempotencyKey).toBeTruthy();
    });

    it('clears form inputs immediately after successful submission for security', async () => {
        axios.post.mockResolvedValueOnce({
            data: {
                success: true,
                result: { no_transaksi: 'VSR-20260824-0099' },
            },
        });

        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Tamu Rahasia',
            no_hp: '8123456789',
            instansi_asal: 'PT Confidential',
            pic_tujuan: 'Direktur',
            keperluan: 'Meeting',
            // Tanpa estimasi form belum sah dan konfirmasinya tidak terbuka.
            estimasi_masuk: '2026-08-24 08:00:00',
            estimasi_keluar: '2026-08-24 17:00:00',
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.openConfirmModal();
        expect(wrapper.vm.showConfirmModal).toBe(true);

        await wrapper.vm.confirmSubmit();

        // Regular tanpa foto: JSON biasa ke prefiks API bawaan.
        const [url, body] = axios.post.mock.calls[0];
        expect(url).toBe('/transaksitamu');
        expect(body).not.toBeInstanceOf(FormData);
        expect(body).not.toHaveProperty('rentang_label');
        expect(wrapper.vm.modalStage).toBe('success');
        // Underlying form should be wiped clean immediately
        expect(wrapper.vm.form.nama_tamu).toBe('');
        expect(wrapper.vm.form.no_hp).toBe('');
        expect(wrapper.vm.form.instansi_asal).toBe('');
    });
});

/**
 * Tipe tamu DITETAPKAN HALAMAN (DDL-2026-121), bukan dipilih pengisi form.
 *
 * Dropdown tipe dihapus: halaman Regular dan B2B memasang TamuForm dengan prop
 * `tipe` dan `apiPrefix`, dan server membaca tipe dari rutenya. Kedua tipe
 * wajib mengisi Estimasi Masuk/Keluar; B2B juga wajib berkendaraan + plat.
 */
describe('TamuForm per tipe (Regular vs B2B)', () => {
    const MASTER = {
        data: {
            success: true,
            result: {
                plat_codes: ['B'],
                vehicle_types: [
                    { Id: 1, Nama_Vehicle_Type: 'Tidak Berkendaraan' },
                    { Id: 2, Nama_Vehicle_Type: 'Mobil' },
                ],
                keperluan: [],
                jenis_identitas: ['KTP'],
            },
        },
    };

    const isianDasar = {
        nama_tamu: 'Rina Pratama',
        no_hp: '08123456789',
        instansi_asal: 'PT Contoh',
        keperluan: 'Meeting',
        pic_tujuan: 'Budi / HR',
        estimasi_masuk: '2026-09-10 08:00:00',
        estimasi_keluar: '2026-09-10 17:00:00',
        anggota_lain: [],
    };

    // Pengaju B2B terdaftar satu unit (matriks departemen, DDL-2026-122).
    const pasangB2b = (b2bUnits = ['LOGISTIC']) =>
        shallowMount(TamuForm, { props: { tipe: 'B2B', apiPrefix: 'transaksi-tamu-b2b', b2bUnits } });

    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue(MASTER);
    });

    it('tidak mengirim tipe_tamu maupun tanggal_kunjungan; mengirim estimasi', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, isianDasar);
        await wrapper.vm.$nextTick();

        const payload = wrapper.vm.buildPayload();

        expect(payload).not.toHaveProperty('tipe_tamu');
        expect(payload).not.toHaveProperty('tanggal_kunjungan');
        expect(payload.waktu_mulai_kunjungan).toBe('2026-09-10 08:00:00');
        expect(payload.waktu_selesai_kunjungan).toBe('2026-09-10 17:00:00');
    });

    it('memuat master dan mengirim ke prefiks API halamannya', async () => {
        axios.post.mockResolvedValueOnce({ data: { success: true, result: { no_transaksi: 'VSR-1' } } });
        const wrapper = pasangB2b();
        await wrapper.vm.$nextTick();

        expect(axios.get).toHaveBeenCalledWith('/transaksi-tamu-b2b-master');

        Object.assign(wrapper.vm.form, isianDasar, { jenis_kendaraan: 'Mobil', no_plat: 'B1234XYZ' });
        await wrapper.vm.$nextTick();
        wrapper.vm.openConfirmModal();
        await wrapper.vm.confirmSubmit();

        const [url, body] = axios.post.mock.calls[0];
        expect(url).toBe('/transaksi-tamu-b2b');
        // Tanpa foto tetap JSON; multipart hanya bila foto dilampirkan.
        expect(body).not.toBeInstanceOf(FormData);
        // Satu unit berhak → terkunci otomatis.
        expect(body.b2b_unit).toBe('LOGISTIC');
    });

    it('B2B wajib plat lengkap sampai kode belakang', async () => {
        const wrapper = pasangB2b();
        Object.assign(wrapper.vm.form, isianDasar, { jenis_kendaraan: 'Mobil', no_plat: 'B 1213' });
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.platLengkap).toBe(false);
        expect(wrapper.vm.isFormValid).toBe(false);

        wrapper.vm.form.no_plat = 'B 1213 QAW';
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.platLengkap).toBe(true);
        expect(wrapper.vm.isFormValid).toBe(true);
    });

    it('B2B tanpa unit berhak diblokir; dua unit wajib memilih', async () => {
        const tanpa = pasangB2b([]);
        expect(tanpa.vm.isSubmitBlocked).toBe(true);

        const dua = pasangB2b(['PROCUREMENT', 'LOGISTIC']);
        Object.assign(dua.vm.form, isianDasar, { jenis_kendaraan: 'Mobil', no_plat: 'B1234XYZ' });
        await dua.vm.$nextTick();
        expect(dua.vm.isFormValid).toBe(false);

        dua.vm.form.b2b_unit = 'PROCUREMENT';
        await dua.vm.$nextTick();
        expect(dua.vm.isFormValid).toBe(true);
        expect(dua.vm.buildPayload().b2b_unit).toBe('PROCUREMENT');
    });

    it('memakai multipart hanya bila foto kendaraan B2B dilampirkan', async () => {
        axios.post.mockResolvedValueOnce({ data: { success: true, result: { no_transaksi: 'VSR-2' } } });
        const wrapper = pasangB2b();
        const foto = new File(['x'], 'mobil.jpg', { type: 'image/jpeg' });
        Object.assign(wrapper.vm.form, isianDasar, {
            jenis_kendaraan: 'Mobil',
            no_plat: 'B1234XYZ',
            foto_kendaraan: [{ name: 'mobil.jpg', raw: foto }],
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.openConfirmModal();
        await wrapper.vm.confirmSubmit();

        const [, body] = axios.post.mock.calls[0];
        expect(body).toBeInstanceOf(FormData);
        expect(body.get('foto_kendaraan')).toBeInstanceOf(File);
        expect(body.get('foto_kendaraan').name).toBe('mobil.jpg');
        expect(body.get('waktu_mulai_kunjungan')).toBe('2026-09-10 08:00:00');
        expect(body.has('rentang_label')).toBe(false);
    });

    it('B2B menyembunyikan pilihan Tidak Berkendara dan tanpa bawaannya', async () => {
        const wrapper = pasangB2b();
        await wrapper.vm.fetchMasterData();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.form.jenis_kendaraan).toBe('');
        expect(wrapper.vm.vehicleOptions.map((o) => o.label)).toEqual(['Mobil']);

        const regular = shallowMount(TamuForm);
        await regular.vm.fetchMasterData();
        expect(regular.vm.form.jenis_kendaraan).toBe('Tidak Berkendara');
        expect(regular.vm.vehicleOptions.map((o) => o.label)).toEqual(['Tidak berkendara', 'Mobil']);
    });

    it('B2B wajib berkendaraan dengan plat wilayah + angka + kode belakang', async () => {
        const wrapper = pasangB2b();
        Object.assign(wrapper.vm.form, isianDasar, { jenis_kendaraan: 'Mobil', no_plat: '' });
        await wrapper.vm.$nextTick();

        const labels = wrapper.vm.completionItems.map((i) => i.label);
        expect(labels).toContain('Estimasi masuk & keluar');
        expect(labels).toContain('Kendaraan & plat nomor');
        expect(wrapper.vm.isFormValid).toBe(false);

        // Wilayah saja belum cukup; sejak 29 Sep 2026 kode belakang juga wajib.
        wrapper.vm.form.no_plat = 'B';
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.isFormValid).toBe(false);

        wrapper.vm.form.no_plat = 'B1234';
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.isFormValid).toBe(false);

        wrapper.vm.form.no_plat = 'B1234XYZ';
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.isFormValid).toBe(true);
    });

    it('Regular tidak menagih kendaraan, tetapi tetap menagih estimasi', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, isianDasar, { estimasi_masuk: '', estimasi_keluar: '' });
        await wrapper.vm.$nextTick();

        const labels = wrapper.vm.completionItems.map((i) => i.label);
        expect(labels).toContain('Estimasi masuk & keluar');
        expect(labels).not.toContain('Kendaraan & plat nomor');
        expect(wrapper.vm.isFormValid).toBe(false);

        Object.assign(wrapper.vm.form, {
            estimasi_masuk: '2026-09-10 08:00:00',
            estimasi_keluar: '2026-09-10 17:00:00',
        });
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.isFormValid).toBe(true);
    });

    it('estimasi keluar wajib setelah estimasi masuk', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, isianDasar, {
            estimasi_masuk: '2026-09-10 17:00:00',
            estimasi_keluar: '2026-09-10 08:00:00',
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.estimasiError).toBeTruthy();
        expect(wrapper.vm.isFormValid).toBe(false);
    });

    it('merender estimasi masuk/keluar sebagai datetime, tanpa dropdown tipe', () => {
        const source = readFileSync(path.resolve(__dirname, 'TamuForm.vue'), 'utf8');

        expect(source).not.toContain('v-model="form.tipe_tamu"');
        expect(source).not.toContain('v-model="form.jam_mulai"');
        expect(source).not.toContain('v-model="form.jam_selesai"');
        expect(source).toContain('v-model="form.estimasi_masuk"');
        expect(source).toContain('v-model="form.estimasi_keluar"');
        expect(source).toContain('type="datetime"');
    });
});

describe('TamuForm daftar anggota rombongan', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({
            data: {
                success: true,
                result: {
                    plat_codes: ['B'],
                    vehicle_types: [],
                    keperluan: [],
                    jenis_identitas: ['KTP'],
                },
            },
        });
    });

    it('mengirim daftar anggota selain tamu utama', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rudi Hartono',
            anggota_lain: [
                { nama: 'Siti Aminah', jenis_identitas: 'KTP', no_identitas: '3201' },
                { nama: 'Budi', jenis_identitas: '', no_identitas: '' },
            ],
        });
        await wrapper.vm.$nextTick();

        const payload = wrapper.vm.buildPayload();

        expect(payload.daftar_anggota).toHaveLength(2);
        expect(payload.daftar_anggota[0].nama).toBe('Siti Aminah');
        expect(payload.daftar_anggota[1].nama).toBe('Budi');
    });

    it('membuang kunci identitas kosong dari payload', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rudi Hartono',
            anggota_lain: [{ nama: 'Budi', jenis_identitas: '  ', no_identitas: '' }],
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.buildPayload().daftar_anggota[0]).toEqual({ nama: 'Budi' });
    });

    it('jumlah orang adalah nilai turunan, bukan isian', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rudi Hartono',
            anggota_lain: [{ nama: 'Siti' }, { nama: 'Budi' }],
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.jumlahOrang).toBe(3);
        expect(wrapper.vm.buildPayload().jumlah_tamu).toBe(3);
    });

    it('tamu tunggal tetap sah dengan daftar kosong', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, { nama_tamu: 'Rudi Hartono', anggota_lain: [] });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.jumlahOrang).toBe(1);
        expect(wrapper.vm.buildPayload().daftar_anggota).toEqual([]);
    });

    it('anggota bernama kosong membuat form belum sah', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rudi Hartono',
            anggota_lain: [{ nama: '   ' }],
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.daftarAnggotaValid).toBe(false);
        expect(wrapper.vm.isFormValid).toBe(false);
    });

    it('membatasi rombongan menjadi 50 orang termasuk tamu utama', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rudi Hartono',
            anggota_lain: Array.from({ length: 49 }, (_, i) => ({ nama: `Anggota ${i + 1}` })),
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.jumlahOrang).toBe(50);
        expect(wrapper.vm.bolehTambahAnggota).toBe(false);
        wrapper.vm.tambahAnggota();
        expect(wrapper.vm.form.anggota_lain).toHaveLength(49);
    });

    it('tidak lagi merender isian jumlah yang bisa diketik', () => {
        const source = readFileSync(path.resolve(__dirname, 'TamuForm.vue'), 'utf8');

        expect(source).not.toContain('v-model="form.jumlah_tamu"');
        expect(source).toContain('jumlahOrang');
        expect(source).toContain('anggota_lain');
    });
});

describe('TamuForm opsi moda transportasi dan deduplikasi tidak berkendara', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('mendeduplikasi opsi Tidak Berkendara ketika master data menyediakan variasi Tidak Berkendaraan', async () => {
        axios.get.mockResolvedValueOnce({
            data: {
                success: true,
                result: {
                    plat_codes: ['B'],
                    vehicle_types: [
                        { Id: 1, Nama_Vehicle_Type: 'Tidak Berkendaraan' },
                        { Id: 2, Nama_Vehicle_Type: 'Mobil' },
                        { Id: 3, Nama_Vehicle_Type: 'Sepeda Motor' },
                    ],
                    keperluan: [],
                    jenis_identitas: ['KTP'],
                },
            },
        });

        const wrapper = shallowMount(TamuForm);
        await wrapper.vm.fetchMasterData();
        await wrapper.vm.$nextTick();

        const options = wrapper.vm.vehicleOptions;
        const nonDrivingOptions = options.filter(
            (o) => o.value.toLowerCase().includes('tidak') || o.label.toLowerCase().includes('tidak'),
        );

        expect(nonDrivingOptions).toHaveLength(1);
        expect(nonDrivingOptions[0].value).toBe('Tidak Berkendara');
        expect(nonDrivingOptions[0].label).toBe('Tidak berkendara');
        expect(nonDrivingOptions[0].icon).toBe('person-walking');
        expect(options.map((o) => o.label)).toEqual(['Tidak berkendara', 'Mobil', 'Sepeda Motor']);
    });

    it('menonaktifkan plat nomor dan menandai isVehicleDisabled untuk semua variasi tidak berkendara', () => {
        const wrapper = shallowMount(TamuForm);

        wrapper.vm.form.jenis_kendaraan = 'Tidak Berkendara';
        expect(wrapper.vm.isVehicleDisabled).toBe(true);

        wrapper.vm.form.jenis_kendaraan = 'Tidak Berkendaraan';
        expect(wrapper.vm.isVehicleDisabled).toBe(true);

        wrapper.vm.form.jenis_kendaraan = 'Tanpa Kendaraan';
        expect(wrapper.vm.isVehicleDisabled).toBe(true);

        wrapper.vm.form.jenis_kendaraan = 'Pejalan Kaki';
        expect(wrapper.vm.isVehicleDisabled).toBe(true);

        wrapper.vm.form.jenis_kendaraan = 'Mobil';
        expect(wrapper.vm.isVehicleDisabled).toBe(false);

        wrapper.vm.form.jenis_kendaraan = 'Sepeda Motor';
        expect(wrapper.vm.isVehicleDisabled).toBe(false);
    });
});


describe('TamuForm — banner blokir menyebut sebab yang benar', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: { success: true, result: {} } });
    });

    const judulBanner = (w) => w.findAll('hcis-alert-stub').map((a) => a.attributes('title'));
    const ALUR_SEHAT = {
        approvalPolicy: { needs_approval: true, allow_submit_without_flow: false },
        approverFlows: [{ step: 1 }],
    };

    it('pengaju B2B tanpa unit TIDAK diberi tahu soal approver bila alurnya sehat', () => {
        const w = shallowMount(TamuForm, { props: { tipe: 'B2B', apiPrefix: 'transaksi-tamu-b2b', b2bUnits: [], ...ALUR_SEHAT } });

        expect(w.vm.isSubmitBlocked).toBe(true);
        expect(judulBanner(w)).toContain('Anda belum terdaftar sebagai pengaju tamu B2B');
        expect(judulBanner(w)).not.toContain('Registrasi belum dapat dikirim');
    });

    it('banner approver tetap muncul bila alur memang tanpa approver', () => {
        const w = shallowMount(TamuForm, {
            props: { approvalPolicy: { needs_approval: true, allow_submit_without_flow: false }, approverFlows: [] },
        });

        expect(judulBanner(w)).toContain('Registrasi belum dapat dikirim');
    });
});

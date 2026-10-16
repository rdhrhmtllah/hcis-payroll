import { shallowMount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import PermitForm from './PermitForm.vue';

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

describe('PermitForm design-system composition', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        const slot = {
            data: {
                success: true,
                result: { slot: [{ id_pos: 7, pos: 'Pos Utama', lokasi: 'Gerbang depan', mulai: '2026-09-21 08:00', selesai: '2026-09-21 08:30', kuota: 2, terisi: 0, sisa: 2 }] },
            },
        };
        const master = {
            data: {
                success: true,
                result: {
                    plat_codes: ['B'],
                    vehicle_types: [{ Id: 1, Nama_Vehicle_Type: 'Mobil' }],
                    models: [{ Id: 11, Id_Master_Tipe: 1, Nama_Model_Kendaraan: 'Avanza' }],
                    colors: [{ Id: 21, Nama_Warna: 'Merah' }],
                    kategoris: [
                        {
                            Id: 31,
                            Nama_Kategori: 'Permit Merah',
                            Deskripsi: 'Akses kendaraan dengan pemeriksaan khusus',
                            Kode_Warna: '#dc2626',
                        },
                        {
                            Id: 32,
                            Nama_Kategori: 'Permit Hijau',
                            Deskripsi: 'Akses kendaraan rutin',
                            Kode_Warna: '#16a34a',
                        },
                    ],
                },
            },
        };
        axios.get.mockImplementation((url) => Promise.resolve(url === '/transaksipermit-slot-inspeksi' ? slot : master));
    });

    it('uses official HCIS controls for the complete permit form', () => {
        const source = readFileSync(
            path.resolve(process.cwd(), 'resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitForm.vue'),
            'utf8',
        );

        expect(source.match(/<HcisCard\b/g)?.length).toBeGreaterThanOrEqual(4);
        expect(source.match(/<HcisField\b/g)?.length).toBeGreaterThanOrEqual(5);
        expect(source).toContain('<HcisPlateInput');
        expect(source).toContain('visitor-vehicle-grid');
        expect(source.match(/<HcisUpload\b/g)).toHaveLength(3);
        expect(source).toContain('<HcisRadio');
        expect(source).toContain('permit-category-picker');
        expect(source).toContain('<HcisCheckbox');
        expect(source).toContain('<HcisDialog');
        expect(source).toContain('<HcisResultState');
        expect(source).toContain('v-model="form.no_plat"');
        expect(source).not.toContain('permit-plat-prefix');
        expect(source).not.toContain('permit-plat-suffix');
        expect(source).not.toMatch(/<el-/);
        expect(source).not.toMatch(/<button\b/);
    });

    it('keeps the required-data gate, confirmation behavior, and success result modal', async () => {
        const wrapper = shallowMount(PermitForm);
        await wrapper.vm.$nextTick();

        wrapper.vm.form.id_vehicle_type = 1;
        await wrapper.vm.$nextTick();
        Object.assign(wrapper.vm.form, {
            no_plat: 'B1234XYZ',
            id_vehicle_model: 11,
            id_vehicle_color: 21,
            tahun_kendaraan: 2026,
            id_kategori_permit: 31,
            inspeksi: { id_pos: 7, pos: 'Pos Utama', lokasi: 'Gerbang depan', mulai: '2026-09-21 08:00', selesai: '2026-09-21 08:30' },
            statement_accepted: true,
        });
        wrapper.vm.files = {
            foto_kendaraan: new File(['vehicle'], 'vehicle.jpg', { type: 'image/jpeg' }),
            foto_stnk: new File(['stnk'], 'stnk.pdf', { type: 'application/pdf' }),
            foto_sim: new File(['sim'], 'sim.jpg', { type: 'image/jpeg' }),
        };
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isFormValid).toBe(true);
        expect(wrapper.vm.computedNoPlat).toBe('B 1234 XYZ');
        expect(wrapper.vm.plateParts).toEqual({ prefix: 'B', angka: '1234', suffix: 'XYZ' });
        wrapper.vm.openConfirmModal();
        expect(wrapper.vm.showConfirmModal).toBe(true);
        expect(wrapper.vm.modalStage).toBe('confirm');
        expect(wrapper.vm.confirmReceiptItems).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ label: 'Nomor Plat Kendaraan', value: 'B 1234 XYZ' }),
                expect.objectContaining({ label: 'Kategori Akses', value: 'Permit Merah' }),
                expect.objectContaining({ label: 'Jadwal Inspeksi', value: expect.stringContaining('08:00–08:30 · Pos Utama (Gerbang depan)') }),
            ]),
        );
        expect(wrapper.vm.idempotencyKey).toBeTruthy();

        // Simulate successful submit
        axios.post.mockResolvedValueOnce({
            data: {
                success: true,
                result: { no_transaksi: 'PMT0826-0001' },
            },
        });

        await wrapper.vm.confirmSubmit();
        expect(wrapper.vm.showConfirmModal).toBe(true);
        expect(wrapper.vm.modalStage).toBe('success');
        expect(wrapper.vm.lastSubmittedResult).toEqual({ no_transaksi: 'PMT0826-0001' });
        expect(wrapper.vm.successReceiptItems).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ label: 'No. Transaksi Permit', value: 'PMT0826-0001' }),
                expect.objectContaining({ label: 'Nomor Plat Kendaraan', value: 'B 1234 XYZ' }),
            ]),
        );
        expect(wrapper.emitted('saved')).toBeFalsy();

        wrapper.vm.goToHistory();
        expect(wrapper.vm.showConfirmModal).toBe(false);
        expect(wrapper.emitted('saved')).toBeTruthy();
    });

    it('maps vehicle swatches and permit categories to meaningful visual semantics', async () => {
        const wrapper = shallowMount(PermitForm);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.getVehicleColorSwatch({ Nama_Warna: 'Merah' })).toBe('#dc2626');
        expect(wrapper.vm.categorySentiment({ Kode_Warna: '#dc2626' })).toBe('danger');
        expect(wrapper.vm.categorySentiment({ Kode_Warna: '#16a34a' })).toBe('success');
        expect(wrapper.vm.categoryOptions).toEqual([
            expect.objectContaining({ value: 31, color: 'danger', icon: 'shield-fill-exclamation' }),
            expect.objectContaining({ value: 32, color: 'success', icon: 'shield-fill-check' }),
        ]);
    });
});

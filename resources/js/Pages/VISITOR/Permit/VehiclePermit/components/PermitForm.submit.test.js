import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';

const get = vi.fn();
const post = vi.fn();

vi.mock('axios', () => ({
    default: { get: (...a) => get(...a), post: (...a) => post(...a) },
}));
vi.mock('element-plus', () => ({ ElMessage: { error: vi.fn(), success: vi.fn() } }));

import PermitForm from './PermitForm.vue';

const SLOT = {
    data: {
        success: true,
        result: { slot: [{ id_pos: 7, pos: 'Pos Utama', lokasi: 'Gerbang depan', mulai: '2026-09-21 08:00', selesai: '2026-09-21 08:30', kuota: 2, terisi: 0, sisa: 2 }] },
    },
};

const MASTER = {
    data: {
        success: true,
        result: {
            plat_codes: ['BA'],
            vehicle_types: [{ Id: 1 }],
            models: [{ Id: 11, Id_Master_Tipe: 1 }],
            colors: [{ Id: 21 }],
            kategoris: [{ Id: 31, Nama_Kategori: 'HIJAU' }],
        },
    },
};

/**
 * Kontrak yang divalidasi VehiclePermitController::store().
 *
 * Kalau daftar ini berubah, sisi server ikut berubah — bukan sebaliknya. Payload
 * pernah ditulis ulang memakai nama KOLOM database (Id_Master_Model,
 * Foto_Kendaraan, …) sehingga setiap pengajuan ditolak, dan gejala pertamanya
 * cuma "Header Idempotency-Key wajib diisi" yang menyesatkan.
 */
const WAJIB_ADA = [
    'plat_prefix',
    'plat_angka',
    'plat_suffix',
    'id_vehicle_type',
    'id_vehicle_model',
    'id_vehicle_color',
    'tahun_kendaraan',
    'id_kategori_permit',
    'foto_kendaraan',
    'foto_stnk',
    'foto_sim',
    'inspeksi_id_pos',
    'inspeksi_mulai',
];

async function kirim(balasan = { data: { success: true, result: { no_transaksi: 'PMT-1' } } }) {
    get.mockImplementation((url) => Promise.resolve(url === '/transaksipermit-slot-inspeksi' ? SLOT : MASTER));
    if (balasan instanceof Error) {
        post.mockRejectedValue(balasan);
    } else {
        post.mockResolvedValue(balasan);
    }

    const wrapper = shallowMount(PermitForm, {
        props: {
            config: { enabled: true },
            approvalPolicy: { needs_approval: true, allow_submit_without_flow: false },
            approverFlows: [{ kode_karyawan: 'A1' }],
            kuota: { terpakai: 0, maks: 2 },
        },
    });
    await flushPromises();

    wrapper.vm.form = {
        ...wrapper.vm.defaultForm(),
        no_plat: 'BA 1234 XYZ',
        id_vehicle_type: 1,
        id_vehicle_model: 11,
        id_vehicle_color: 21,
        id_kategori_permit: 31,
        inspeksi: { id_pos: 7, pos: 'Pos Utama', lokasi: 'Gerbang depan', mulai: '2026-09-21 08:00', selesai: '2026-09-21 08:30' },
        statement_accepted: true,
    };
    wrapper.vm.files = {
        foto_kendaraan: new File(['a'], 'kendaraan.jpg', { type: 'image/jpeg' }),
        foto_stnk: new File(['b'], 'stnk.jpg', { type: 'image/jpeg' }),
        foto_sim: new File(['c'], 'sim.jpg', { type: 'image/jpeg' }),
    };

    await wrapper.vm.confirmSubmit();
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
});

describe('PermitForm — kontrak pengiriman ke store()', () => {
    it('mengirim Idempotency-Key sebagai HEADER, bukan field body', async () => {
        await kirim();

        expect(post).toHaveBeenCalledTimes(1);
        const [, formData, konfigurasi] = post.mock.calls[0];

        const kunci = konfigurasi?.headers?.['Idempotency-Key'];
        expect(kunci, 'store() membaca header, bukan field body').toBeTruthy();
        expect(String(kunci).length).toBeLessThanOrEqual(100);
        expect(formData.has('Idempotency_Key')).toBe(false);
    });

    it('memakai nama field yang divalidasi store(), bukan nama kolom database', async () => {
        await kirim();

        const formData = post.mock.calls[0][1];
        const terkirim = [...formData.keys()];

        expect(terkirim).toEqual(expect.arrayContaining(WAJIB_ADA));
    });

    it('tidak mengirim nama bergaya kolom database', async () => {
        await kirim();

        const terkirim = [...post.mock.calls[0][1].keys()];
        const gayaKolom = terkirim.filter((k) => /^[A-Z]/.test(k));

        expect(gayaKolom, `Nama bergaya kolom ikut terkirim: ${gayaKolom.join(', ')}`).toEqual([]);
    });

    it('memecah plat menjadi tiga ruas seperti yang dibaca server', async () => {
        await kirim();

        const formData = post.mock.calls[0][1];

        // store() merangkai ulang dari tiga ruas ini; mengirim plat utuh saja
        // membuat resolvePlatNomor() gagal dan pengajuan ditolak.
        expect(formData.get('plat_prefix')).toBe('BA');
        expect(formData.get('plat_angka')).toBe('1234');
        expect(formData.get('plat_suffix')).toBe('XYZ');
    });
});

describe('PermitForm — jadwal inspeksi (DDL-2026-123)', () => {
    it('mengirim slot yang dipilih apa adanya, format Y-m-d H:i yang divalidasi server', async () => {
        await kirim();

        const formData = post.mock.calls[0][1];
        expect(formData.get('inspeksi_id_pos')).toBe('7');
        expect(formData.get('inspeksi_mulai')).toBe('2026-09-21 08:00');
    });

    it('menahan pengajuan selama slot inspeksi belum dipilih', async () => {
        get.mockImplementation((url) => Promise.resolve(url === '/transaksipermit-slot-inspeksi' ? SLOT : MASTER));
        const wrapper = shallowMount(PermitForm, { props: { kuota: { terpakai: 0, maks: 2 } } });
        await flushPromises();

        // Label menjadi "Jadwal inspeksi SHE" dan tiap butir membawa stepIndex
        // sejak form menjadi wizard tiga langkah; yang diuji tetap: belum lengkap.
        const butir = wrapper.vm.completionItems.find((x) => x.label === 'Jadwal inspeksi SHE');
        expect(butir).toMatchObject({ label: 'Jadwal inspeksi SHE', complete: false });
        expect(wrapper.vm.isFormValid).toBe(false);
    });

    it('melepas pilihan dan memuat ulang slot ketika server menjawab slot direbut', async () => {
        const galat = Object.assign(new Error('422'), {
            response: { status: 422, data: { success: false, message: 'Slot penuh', error_code: 'VISITOR_PERMIT_SLOT_INVALID' } },
        });
        const wrapper = await kirim(galat);

        expect(wrapper.vm.modalStage).toBe('error');
        expect(wrapper.vm.submitErrorMessage).toBe('Slot penuh');
        expect(wrapper.vm.form.inspeksi).toBeNull();
        expect(get.mock.calls.filter(([url]) => url === '/transaksipermit-slot-inspeksi')).toHaveLength(2);
    });

    it('melepas pilihan yang sudah tidak ada di daftar slot terbaru', async () => {
        get.mockImplementation((url) => Promise.resolve(url === '/transaksipermit-slot-inspeksi' ? SLOT : MASTER));
        const wrapper = shallowMount(PermitForm, { props: { kuota: { terpakai: 0, maks: 2 } } });
        await flushPromises();

        wrapper.vm.form.inspeksi = { id_pos: 7, pos: 'Pos Utama', mulai: '2026-09-21 09:00', selesai: '2026-09-21 09:30' };
        await wrapper.vm.fetchSlotInspeksi();

        expect(wrapper.vm.form.inspeksi).toBeNull();
    });
});

describe('PermitForm — foto diperkecil sebelum dikirim', () => {
    it('berkas hasil kompresi yang dipakai; selama diproses dokumen dianggap belum ada', async () => {
        get.mockImplementation((url) => Promise.resolve(url === '/transaksipermit-slot-inspeksi' ? SLOT : MASTER));
        const wrapper = shallowMount(PermitForm, { props: { kuota: { terpakai: 0, maks: 2 } } });
        await flushPromises();

        const asli = new File(['x'.repeat(10)], 'kamera.jpg', { type: 'image/jpeg' });
        const janji = wrapper.vm.updateUpload('foto_kendaraan', [{ raw: asli }]);
        expect(wrapper.vm.files.foto_kendaraan).toBeNull();
        await janji;
        // jsdom tanpa canvas: kompresGambar aman-gagal ke berkas asli.
        expect(wrapper.vm.files.foto_kendaraan).toBe(asli);
    });

    it('PDF di atas 5 MB ditolak dengan pesan, tidak ikut terkirim', async () => {
        get.mockImplementation((url) => Promise.resolve(url === '/transaksipermit-slot-inspeksi' ? SLOT : MASTER));
        const wrapper = shallowMount(PermitForm, { props: { kuota: { terpakai: 0, maks: 2 } } });
        await flushPromises();

        const pdf = new File(['x'], 'stnk.pdf', { type: 'application/pdf' });
        Object.defineProperty(pdf, 'size', { value: 6 * 1024 * 1024 });
        await wrapper.vm.updateUpload('foto_stnk', [{ raw: pdf }]);

        expect(wrapper.vm.files.foto_stnk).toBeNull();
        expect(wrapper.vm.uploadLists.foto_stnk).toEqual([]);
        const { ElMessage } = await import('element-plus');
        expect(ElMessage.error).toHaveBeenCalledWith(expect.stringContaining('melebihi 5 MB'));
    });
});

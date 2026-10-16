import { shallowMount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import VerifikasiKartuScanner from './VerifikasiKartuScanner.vue';

vi.mock('axios', () => ({ default: { post: vi.fn() } }));
vi.mock('jsqr', () => ({ default: vi.fn() }));

const HASIL_SAH = {
    sah: true,
    judul: 'KARTU SAH',
    status: 'AKTIF',
    plat: 'BG 7898 KAD',
    kategori: 'PERMIT HIJAU',
    pemegang: 'Budi Santoso',
    departemen: 'Teknologi Informasi',
    sejak: '24 Agu 2026',
    sampai: '24 Agu 2027',
    no_transaksi: 'PMT0926-0099',
    dicek: '21 Sep 2026 14:02',
};

afterEach(() => {
    vi.clearAllMocks();
});

describe('VerifikasiKartuScanner', () => {
    it('mengirim token apa adanya ke endpoint internal, bukan menilai sendiri', async () => {
        axios.post.mockResolvedValueOnce({ data: { success: true, result: HASIL_SAH } });
        const wrapper = shallowMount(VerifikasiKartuScanner);

        await wrapper.vm.periksa('  HCIS-PASS-1.8588.abc  ');

        expect(axios.post).toHaveBeenCalledWith('/transaksipermit-verifikasi-kartu', { token: 'HCIS-PASS-1.8588.abc' });
        expect(wrapper.vm.hasil).toEqual(HASIL_SAH);
    });

    it('token kosong tidak menembak server', async () => {
        const wrapper = shallowMount(VerifikasiKartuScanner);

        await wrapper.vm.periksa('   ');

        expect(axios.post).not.toHaveBeenCalled();
    });

    it('kegagalan sambungan tampil sebagai hasil, bukan layar diam', async () => {
        axios.post.mockRejectedValueOnce({ response: { data: { message: 'Sesi berakhir.' } } });
        const wrapper = shallowMount(VerifikasiKartuScanner);

        await wrapper.vm.periksa('HCIS-PASS-1.1.x');

        expect(wrapper.vm.hasil).toMatchObject({ sah: false, judul: 'GAGAL MEMERIKSA', sebab: 'Sesi berakhir.' });
    });

    it('mematikan kamera saat dialog ditutup dan saat komponennya dibuang', async () => {
        // Kamera yang tidak dimatikan meninggalkan lampu kamera menyala —
        // gejala yang tampak seperti aplikasi mengintai penggunanya.
        const stop = vi.fn();
        const wrapper = shallowMount(VerifikasiKartuScanner, { props: { modelValue: true } });
        wrapper.vm.stream = { getTracks: () => [{ stop }] };

        await wrapper.setProps({ modelValue: false });
        expect(stop).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.stream).toBeNull();

        wrapper.vm.stream = { getTracks: () => [{ stop }] };
        wrapper.unmount();
        expect(stop).toHaveBeenCalledTimes(2);
    });

    it('memakai kamera belakang, bukan kamera selfie', () => {
        const sumber = readFileSync(
            path.resolve(process.cwd(), 'resources/js/components/VISITOR/VerifikasiKartuScanner.vue'),
            'utf8',
        );

        expect(sumber).toContain("facingMode: 'environment'");
        // Keputusan sah/tidak sah HANYA milik server.
        expect(sumber).toContain('/transaksipermit-verifikasi-kartu');
        expect(sumber).not.toMatch(/hasil\s*=\s*\{\s*sah:\s*true/);
    });
});

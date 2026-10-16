import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AppPanduanPetunjuk from './AppPanduanPetunjuk.vue';

function penyimpananTiruan() {
    let isi = {};
    return {
        getItem: (k) => (k in isi ? isi[k] : null),
        setItem: (k, v) => {
            isi[k] = String(v);
        },
        removeItem: (k) => {
            delete isi[k];
        },
        clear: () => {
            isi = {};
        },
    };
}

const SATU = [{ id: 9, judul: 'Manual Permit', baru: false, diperbarui: '2026-09-01T00:00:00+00:00', catatan: null }];

/**
 * Petunjuk "ada panduan di halaman ini" di bawah tombol Bantuan:
 * sekali per halaman per pengguna, muncul lagi saat panduannya diperbarui.
 */
describe('AppPanduanPetunjuk', () => {
    let meta;

    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-09-29T01:00:00Z'));
        vi.stubGlobal('localStorage', penyimpananTiruan());
        meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        meta.setAttribute('content', 'sesi-satu');
        document.head.appendChild(meta);
    });

    afterEach(() => {
        meta.remove();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    async function pasang(props = {}) {
        const w = mount(AppPanduanPetunjuk, {
            props: { panduan: SATU, jenisPage: 'vehiclePermitPage', kunciPengguna: 'K001', jedaMs: 1000, tampilMs: 5000, ...props },
        });
        await flushPromises();
        return w;
    }

    async function tunggu(w, ms = 1000) {
        vi.advanceTimersByTime(ms);
        await flushPromises();
        return w.find('[data-testid="panduan-petunjuk"]').exists();
    }

    const loginLagi = () => meta.setAttribute('content', `sesi-${Math.random()}`);

    it('muncul setelah jeda, dengan judul panduan', async () => {
        const w = await pasang();
        expect(w.find('[data-testid="panduan-petunjuk"]').exists()).toBe(false);
        expect(await tunggu(w)).toBe(true);
        expect(w.text()).toContain('Ada panduan untuk halaman ini');
        expect(w.text()).toContain('Ada panduan & bantuan untuk halaman ini');
        expect(w.text()).toContain('Manual Permit');
        expect(w.text()).toContain('Kontak PIC');
    });

    it('belum pernah dibuka: Mengerti menunda sampai login berikutnya', async () => {
        const w = await pasang();
        await tunggu(w);
        await w.find('[data-testid="panduan-petunjuk-mengerti"]').trigger('click');
        expect(w.find('[data-testid="panduan-petunjuk"]').exists()).toBe(false);

        expect(await tunggu(await pasang(), 3000)).toBe(false); // sesi sama
        loginLagi();
        expect(await tunggu(await pasang())).toBe(true);
    });

    it('Buka panduan: membuka panduannya, lalu baru diingatkan lagi 7 hari kemudian', async () => {
        const w = await pasang();
        await tunggu(w);
        await w.find('[data-testid="panduan-petunjuk-buka"]').trigger('click');
        expect(w.emitted('buka-panduan')[0][0]).toMatchObject({ id: 9 });

        loginLagi();
        expect(await tunggu(await pasang(), 3000)).toBe(false);

        vi.setSystemTime(new Date('2026-10-06T02:00:00Z'));
        const nanti = await pasang();
        expect(await tunggu(nanti)).toBe(true);
        expect(nanti.text()).toContain('Masih ingat caranya?');
    });

    it('lebih dari satu panduan: tombol membuka Pusat Bantuan', async () => {
        const w = await pasang({ panduan: [...SATU, { id: 10, judul: 'Manual Lain', baru: false, diperbarui: null }] });
        await tunggu(w);
        expect(w.text()).toContain('2 panduan tersedia');
        await w.find('[data-testid="panduan-petunjuk-buka"]').trigger('click');
        expect(w.emitted('buka-bantuan')).toBeTruthy();
    });

    it('Jangan ingatkan lagi: diam, sampai panduannya diperbarui', async () => {
        const w = await pasang();
        await tunggu(w);
        await w.find('[data-testid="panduan-petunjuk-jangan"]').trigger('click');

        loginLagi();
        vi.setSystemTime(new Date('2027-01-01T00:00:00Z'));
        expect(await tunggu(await pasang(), 3000)).toBe(false);

        const diperbarui = await pasang({
            panduan: [{ ...SATU[0], baru: true, diperbarui: '2026-12-31T00:00:00+00:00', catatan: 'Bab persetujuan ditulis ulang' }],
        });
        expect(await tunggu(diperbarui)).toBe(true);
        expect(diperbarui.text()).toContain('Panduan halaman ini diperbarui');
        expect(diperbarui.text()).toContain('Bab persetujuan ditulis ulang');
    });

    it('diabaikan: hilang sendiri, muncul lagi di kunjungan berikutnya', async () => {
        const w = await pasang();
        await tunggu(w);
        expect(await tunggu(w, 5000)).toBe(false);
        expect(await tunggu(await pasang())).toBe(true);
    });

    it('ditahan (Pusat Bantuan terbuka / tour) tidak muncul; membuka bantuan saat tampil = ditanggapi', async () => {
        const w = await pasang({ ditahan: true });
        expect(await tunggu(w, 3000)).toBe(false);

        await w.setProps({ ditahan: false });
        expect(await tunggu(w)).toBe(true);

        await w.setProps({ ditahan: true });
        expect(w.find('[data-testid="panduan-petunjuk"]').exists()).toBe(false);
        expect(await tunggu(await pasang(), 3000)).toBe(false);
    });

    it('tanpa panduan tidak pernah muncul', async () => {
        expect(await tunggu(await pasang({ panduan: [] }), 3000)).toBe(false);
    });
    it('tautan Bantuan (kontak) setara Mengerti: muncul lagi di login berikutnya, bukan diam 7 hari', async () => {
        const w = await pasang();
        await tunggu(w);
        await w.find('[title="Buka Pusat Bantuan untuk melihat kontak PIC"]').trigger('click');
        expect(w.emitted('buka-bantuan')[0]).toEqual(['contacts']);

        expect(await tunggu(await pasang(), 3000)).toBe(false); // sesi sama
        loginLagi();
        expect(await tunggu(await pasang())).toBe(true); // belum pernah membuka panduannya
    });
});

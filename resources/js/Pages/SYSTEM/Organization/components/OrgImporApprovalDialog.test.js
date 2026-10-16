import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Dialog from './OrgImporApprovalDialog.vue';

const stubs = {
    HcisDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="footer" /></div>' },
    HcisButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>' },
    HcisAlert: { props: ['title'], template: '<div class="alert">{{ title }}<slot /></div>' },
    HcisUpload: { props: ['modelValue'], emits: ['update:modelValue'], template: '<div class="unggah" />' },
};

const balas = (result, ok = true, status = 200) => Promise.resolve({ ok, status, json: () => Promise.resolve(ok ? { result } : result) });
const PRATINJAU = { dijalankan: false, dibaca: 3, diimpor: 2, dibuat: 1, sudah_ada: 1, diganti: 1, dicabut: 2, dilewati: 1, galat: [] };

let fetchMock;
beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
});
afterEach(() => vi.unstubAllGlobals());

async function pasangDenganBerkas() {
    const w = mount(Dialog, { props: { modelValue: true }, global: { stubs } });
    w.findComponent(stubs.HcisUpload).vm.$emit('update:modelValue', [{ raw: new File(['x'], 'usulan.xlsx') }]);
    await flushPromises();
    return w;
}
const tombolSimpan = (w) => w.findAll('button').find((b) => b.text() === 'Simpan ke Relasi Org');
const jalankanDikirim = (n) => fetchMock.mock.calls[n][1].body.get('jalankan');

describe('OrgImporApprovalDialog', () => {
    it('berkas langsung diperiksa tanpa menyimpan, dan galat menahan tombol Simpan', async () => {
        fetchMock.mockReturnValueOnce(balas({ ...PRATINJAU, galat: ['Baris 4 (A2): atasan tidak boleh dirinya sendiri.'] }));
        const w = await pasangDenganBerkas();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe('/system/organisasi/relasi/usulan-approval/impor');
        expect(jalankanDikirim(0)).toBe('0');
        expect(w.find('[data-uji="org-apr-galat"]').text()).toContain('dirinya sendiri');
        expect(tombolSimpan(w).attributes('disabled')).toBeDefined();
    });

    it('pratinjau bersih → Simpan mengirim jalankan=1 lalu memberi tahu halaman', async () => {
        fetchMock.mockReturnValueOnce(balas(PRATINJAU)).mockReturnValueOnce(balas({ ...PRATINJAU, dijalankan: true }));
        const w = await pasangDenganBerkas();

        expect(w.find('[data-uji="org-apr-pratinjau"]').text()).toContain('akan diimpor');
        expect(w.text()).toContain('1 karyawan berganti atasan');
        expect(tombolSimpan(w).attributes('disabled')).toBeUndefined();

        await tombolSimpan(w).trigger('click');
        await flushPromises();
        expect(jalankanDikirim(1)).toBe('1');
        expect(w.emitted('tersimpan')[0][0].dijalankan).toBe(true);
        expect(w.emitted('update:modelValue').at(-1)).toEqual([false]);
    });

    it('galat validasi server (mis. bukan .xlsx) ditampilkan', async () => {
        fetchMock.mockReturnValueOnce(balas({ message: 'x', errors: { berkas: ['Berkas harus .xlsx.'] } }, false, 422));
        const w = await pasangDenganBerkas();
        expect(w.text()).toContain('Berkas harus .xlsx.');
    });
});

import { describe, expect, it, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import SopPrioritas from './SopPrioritas.vue';

vi.mock('axios');

/**
 * Kepala halaman Prioritas Dokumen.
 *
 * BENTUK LAMA MENYUSUN KEPALANYA SENDIRI dari kelas `.shero`, `.shero-aurora`,
 * `.shero-title`, dan seterusnya — yang TAK PERNAH didefinisikan di mana pun
 * dalam basis kode ini. Halamannya karena itu tampil tanpa gaya sama sekali,
 * dan tak seorang pun menyadarinya karena halaman ini belum punya baris menu
 * sampai DML-2026-057; praktis tak ada yang pernah membukanya.
 *
 * Yang dijaga di sini bukan "ada kepala", melainkan kepalanya memakai KOMPONEN
 * sistem desain. Kelas yang ditulis tangan bisa saja benar hari ini dan jadi
 * hantu besok tanpa satu pun galat — persis yang terjadi sebelumnya.
 */
describe('SopPrioritas — kepala halaman', () => {
    function pasang() {
        return shallowMount(SopPrioritas, {
            props: { prioritas: [] },
            global: {
                mocks: { $vnum: (v) => String(v ?? ''), $vlabel: (v) => String(v ?? '') },
                stubs: { Head: true, Link: true },
            },
        });
    }

    it('memakai HcisHeader, bukan kepala yang disusun tangan', () => {
        const w = pasang();

        expect(w.findComponent({ name: 'HcisHeader' }).exists()).toBe(true);
    });

    /*
     * Penjaga arah-MERAH-nya. Kalau seseorang mengembalikan blok `.shero-*`,
     * halamannya akan kembali tampil polos — dan gejala itu tak melempar galat
     * apa pun, jadi hanya penjaga inilah yang bisa menangkapnya.
     */
    it('tak lagi memuat satu pun kelas .shero yang tak berdefinisi', () => {
        const html = pasang().html();

        expect(html).not.toMatch(/class="[^"]*\bshero\b/);
        expect(html).not.toMatch(/shero-(aurora|grid|title|sub|ico|top|id|team|date)/);
    });

    /* Jumlah tingkat datang dari komponen masternya, bukan dari permintaan
       kedua yang bisa menyimpang dari daftar di bawahnya. */
    it('lencana jumlah baru muncul setelah master melaporkan angkanya', () => {
        const w = pasang();

        expect(w.vm.jumlah).toBeNull();

        w.findComponent({ name: 'SopPrioritasMaster' }).vm.$emit('berubah', 3);

        expect(w.vm.jumlah).toBe(3);
    });
});

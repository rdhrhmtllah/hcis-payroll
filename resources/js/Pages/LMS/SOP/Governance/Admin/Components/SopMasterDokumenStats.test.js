import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopMasterDokumenStats from './SopMasterDokumenStats.vue';

describe('SopMasterDokumenStats.vue', () => {
    it('renders master stats correctly', () => {
        const wrapper = mount(SopMasterDokumenStats, {
            props: {
                total: 25,
                ringkasan: {
                    PERHATIAN: 3,
                    SIAP: 8,
                    DIPAKAI: 12,
                    BELUM_LENGKAP: 5,
                },
                loading: false,
            },
        });

        expect(wrapper.text()).toContain('Total Dokumen Master');
        expect(wrapper.text()).toContain('Sudah Terbit');
        expect(wrapper.text()).toContain('Perlu Ditinjau');
        expect(wrapper.text()).toContain('Belum Lengkap');

        /* "Karyawan Terikat" DIHAPUS 31 Agu 2026: angkanya milik dokumen yang
           sedang dipilih, bukan milik katalognya, jadi ia berubah tiap kali
           pilihan berpindah dan menjawab pertanyaan yang tak sedang ditanyakan
           di deretan ringkasan. Ia tetap ada di panel detail dokumennya. */
        expect(wrapper.text()).not.toContain('Karyawan Terikat');
    });

    /** Kartu total membawa admin kembali ke "Semua". */
    it('kartu total membersihkan penyaring', async () => {
        const wrapper = mount(SopMasterDokumenStats, {
            props: {
                total: 25,
                ringkasan: { PERHATIAN: 3, SIAP: 8, DIPAKAI: 12, BELUM_LENGKAP: 5 },
                loading: false,
            },
        });

        await wrapper.findAllComponents({ name: 'HcisStatCard' })[0].trigger('click');

        expect(wrapper.emitted('filter-all')).toBeTruthy();
    });

    /**
     * "Sudah Terbit" dihitung dari TAHAPNYA, bukan dari total dikurangi
     * yang perlu ditinjau.
     *
     * Rumus lama mengasumsikan keduanya saling meniadakan. Sejak "Perlu
     * Ditinjau" berarti masa berlaku, asumsi itu terbalik: yang menagih justru
     * dokumen YANG BERLAKU, jadi setiap pengingat akan mengurangi angka yang
     * seharusnya ia hitung. Dengan data di bawah, rumus lama menjawab 22.
     */
    it('menghitung dokumen berlaku dari tahapnya, bukan dari pengurangan', () => {
        const wrapper = mount(SopMasterDokumenStats, {
            props: {
                total: 25,
                ringkasan: { PERHATIAN: 3, SIAP: 8, DIPAKAI: 12, BELUM_LENGKAP: 5 },
                loading: false,
            },
        });

        /* Dibaca dari kartunya, bukan dari internal komponen: `?? nilaiHarapan`
           pada vm yang tak mengekspos computed akan LULUS tanpa menguji apa
           pun. Kartu kedua adalah "Sudah Terbit". */
        const kartu = wrapper.findAllComponents({ name: 'HcisStatCard' });

        expect(kartu[1].props('label')).toBe('Sudah Terbit');
        expect(kartu[1].props('value')).toBe(20);
    });

    it('emits filter-attention when attention card is clicked', async () => {
        const wrapper = mount(SopMasterDokumenStats, {
            props: {
                total: 10,
                ringkasan: {
                    PERHATIAN: 2,
                    SIAP: 3,
                    DIPAKAI: 5,
                    BELUM_LENGKAP: 4,
                },
                loading: false,
            },
        });

        const cards = wrapper.findAllComponents({ name: 'HcisStatCard' });
        expect(cards.length).toBe(4);

        // Kartu ketiga: Perlu Ditinjau
        await cards[2].trigger('click');
        expect(wrapper.emitted('filter-attention')).toBeTruthy();
    });

    /* Dua kartu daftar kerja, dua penyaring berbeda. Keduanya memancarkan
       peristiwa sendiri; satu peristiwa untuk dua kartu akan membuat kartu
       mana pun yang ditekan membuka daftar yang sama. */
    it('kartu Belum Lengkap menyalakan penyaringnya sendiri', async () => {
        const wrapper = mount(SopMasterDokumenStats, {
            props: {
                total: 10,
                ringkasan: { PERHATIAN: 2, SIAP: 3, DIPAKAI: 5, BELUM_LENGKAP: 4 },
                loading: false,
            },
        });

        const cards = wrapper.findAllComponents({ name: 'HcisStatCard' });
        await cards[3].trigger('click');

        expect(wrapper.emitted('filter-incomplete')).toBeTruthy();
        expect(wrapper.emitted('filter-attention')).toBeFalsy();
    });

    /**
     * KARTU BERNILAI NOL TETAP MENYARING — keputusan user 31 Agu 2026.
     *
     * Menekan kartu berarti "bawa saya ke daftar ini", dan "memang tak ada"
     * adalah jawaban yang sah. Yang harus dijaga bukan kartunya diam,
     * melainkan daftarnya mengatakan sebabnya dengan kalimat yang jelas —
     * itu dijaga di SopMasterDokumen (kosongJudul / kosongKalimat).
     */
    it('kartu bernilai nol tetap menyalakan penyaringnya', async () => {
        const wrapper = mount(SopMasterDokumenStats, {
            props: {
                total: 10,
                ringkasan: { PERHATIAN: 0, SIAP: 3, DIPAKAI: 5, BELUM_LENGKAP: 0 },
                loading: false,
            },
        });

        const cards = wrapper.findAllComponents({ name: 'HcisStatCard' });
        await cards[2].trigger('click');
        await cards[3].trigger('click');

        expect(wrapper.emitted('filter-attention')).toBeTruthy();
        expect(wrapper.emitted('filter-incomplete')).toBeTruthy();
    });

    /* Dan tampak bisa ditekan, berisi atau tidak — penampakan dan perilakunya
       harus sepakat ke arah mana pun keputusannya. */
    it('kedua kartu daftar kerja selalu tampak bisa ditekan', () => {
        const wrapper = mount(SopMasterDokumenStats, {
            props: {
                total: 10,
                ringkasan: { PERHATIAN: 0, SIAP: 3, DIPAKAI: 5, BELUM_LENGKAP: 0 },
                loading: false,
            },
        });

        const cards = wrapper.findAllComponents({ name: 'HcisStatCard' });

        expect(cards[2].classes()).toContain('is-interactive');
        expect(cards[3].classes()).toContain('is-interactive');
    });
});
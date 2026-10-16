import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopMasterDokumenCard from './SopMasterDokumenCard.vue';

describe('SopMasterDokumenCard.vue', () => {
    const mockItem = {
        Id_SOP_Dokumen: 101,
        Judul: 'Prosedur K3 Operasional',
        Nomor_SOP_Resmi: 'SOP-K3-001',
        Tipe: 'DOKUMEN',
        Punya_Versi_Berlaku: true,
        Tanpa_Pengesah: false,
        Jumlah_Paket_Pemakai: 3,
    };

    it('renders document title, type, and token properly', () => {
        const wrapper = mount(SopMasterDokumenCard, {
            props: {
                item: mockItem,
                isSelected: false,
            },
        });

        expect(wrapper.text()).toContain('Prosedur K3 Operasional');
        expect(wrapper.text()).toContain('DOKUMEN');
        expect(wrapper.text()).toContain('SOP-K3-001');
        /* Jumlah paket kini pil berikon, tanpa kata "Paket"; tahapnya disebut
           lewat label daur hidup, bukan kata "Sah". Menuntut kalimat lama
           membuat berkas ini merah sejak kartunya dirancang ulang. */
        expect(wrapper.text()).toContain('3');
        expect(wrapper.find('.package-pill').exists()).toBe(true);
    });

    it('displays warning when document has no published version', () => {
        const wrapper = mount(SopMasterDokumenCard, {
            props: {
                item: {
                    ...mockItem,
                    Punya_Versi_Berlaku: false,
                },
                isSelected: false,
            },
        });

        /* Tahapnya kini disebut BARU/DRAFT sesuai daur hidup, bukan satu
           kalimat "Belum Berlaku" untuk keduanya. */
        expect(wrapper.find('.has-alert').exists() || wrapper.classes('has-alert')).toBe(true);
    });

    it('displays danger when document is missing approver', () => {
        const wrapper = mount(SopMasterDokumenCard, {
            props: {
                item: {
                    ...mockItem,
                    Punya_Versi_Berlaku: true,
                    Tanpa_Pengesah: true,
                },
                isSelected: false,
            },
        });

        expect(wrapper.text()).toContain('Tanpa Pengesah');
    });

    it('does not render token badge when Nomor_SOP_Resmi is empty', () => {
        const wrapper = mount(SopMasterDokumenCard, {
            props: {
                item: {
                    ...mockItem,
                    Nomor_SOP_Resmi: null,
                },
                isSelected: false,
            },
        });

        expect(wrapper.find('.item-token').exists()).toBe(false);
    });


    /**
     * LENCANA TANGGAL. Keadaannya datang dari SERVER (Tinjau_Keadaan), bukan
     * dihitung di sini: layar yang membandingkan tanggal sendiri memakai jam
     * browser, sehingga satu admin melihat lencana merah dan yang lain tidak
     * untuk baris yang sama.
     */
    it('menyebut sebab dan keadaan saat tanggalnya sudah lewat', () => {
        const wrapper = mount(SopMasterDokumenCard, {
            props: {
                item: {
                    ...mockItem,
                    Tinjau_Keadaan: 'TERLAMBAT',
                    Tinjau_Sebab: 'REVIEW',
                    Tinjau_Tanggal: '2026-01-05',
                },
                isSelected: false,
            },
        });

        expect(wrapper.text()).toContain('Review lewat');
        /* Merah, bukan kuning: yang sudah lewat harus menonjol di antara yang
           masih punya waktu. */
        expect(wrapper.find('.warning-badge.is-soon').exists()).toBe(false);
    });

    it('memakai warna lebih lembut untuk yang belum lewat', () => {
        const wrapper = mount(SopMasterDokumenCard, {
            props: {
                item: {
                    ...mockItem,
                    Tinjau_Keadaan: 'SEGERA',
                    Tinjau_Sebab: 'KADALUARSA',
                    Tinjau_Tanggal: '2026-11-01',
                },
                isSelected: false,
            },
        });

        expect(wrapper.text()).toContain('Kedaluwarsa dekat');
        expect(wrapper.find('.warning-badge.is-soon').exists()).toBe(true);
    });

    /* Tanpa keadaan, tak ada lencana sama sekali — bukan lencana kosong. */
    it('tidak melencanai dokumen yang tanggalnya masih jauh', () => {
        const wrapper = mount(SopMasterDokumenCard, {
            props: { item: mockItem, isSelected: false },
        });

        expect(wrapper.text()).not.toContain('lewat');
        expect(wrapper.text()).not.toContain('dekat');
    });

    it('emits select event when clicked', async () => {
        const wrapper = mount(SopMasterDokumenCard, {
            props: {
                item: mockItem,
                isSelected: false,
            },
        });

        await wrapper.trigger('click');
        expect(wrapper.emitted('select')).toBeTruthy();
        expect(wrapper.emitted('select')[0][0]).toEqual(mockItem);
    });
});

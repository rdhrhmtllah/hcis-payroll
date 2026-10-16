import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import UploadKontenModal from './UploadKontenModal.vue';

// Mock modal wrapper
vi.mock('../../../../components/ui/modal.vue', () => ({
    default: {
        name: 'Modal',
        template: '<div v-if="show" class="mock-modal"><slot name="body" /></div>',
        props: ['show', 'title', 'subtitle'],
    },
}));

vi.mock('../../../../../../composables/useMediaUpload.js', () => ({
    useMediaUpload: () => ({
        state: { queue: [] },
        ACCEPT_ATTR: '.pdf,.docx,.xlsx,.pptx,.jpg,.jpeg,.png,.mp4,.mov,.mkv,.webm',
        EXT_DIIZINKAN: ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png', 'mp4', 'mov', 'mkv', 'webm'],
        periksaBerkas: vi.fn((file) => ({ ok: true, tipe: 'document' })),
        enqueue: vi.fn(),
        startQueueUploads: vi.fn(),
    }),
}));

describe('UploadKontenModal.vue', () => {
    /*
     * Daftar format punya DUA jawaban sejak scope dipisah, dan itu memang
     * aturannya: LMS hanya menerima PDF + video (materi training diputar di
     * pemutar), sedangkan SOP tetap menerima dokumen Office. Uji lama menuntut
     * SATU daftar berisi DOCX/XLSX/PPTX tanpa menyebut scope, jadi ia menuduh
     * komponen menghilangkan format padahal yang berubah aturannya.
     *
     * Dipecah dua supaya yang dijaga ATURANNYA: kalau kelak DOCX bocor ke LMS,
     * atau Office hilang dari SOP, salah satu di bawah ini merah.
     */
    it('scope LMS hanya menawarkan PDF dan video — bukan dokumen Office', () => {
        const wrapper = mount(UploadKontenModal, {
            props: { show: true, categoryId: 10, categoryName: 'SOP Operasional' },
        });

        const formatText = wrapper.find('.allowed-format-text').text();
        for (const ada of ['PDF', 'MP4', 'MOV', 'MKV', 'WEBM']) {
            expect(formatText, ada).toContain(ada);
        }
        for (const jangan of ['DOCX', 'XLSX', 'PPTX']) {
            expect(formatText, jangan).not.toContain(jangan);
        }

        expect(wrapper.find('input[type="file"]').attributes('accept')).toBe('.pdf,.mp4,.mov,.mkv,.webm');
    });

    it('scope SOP tetap menawarkan dokumen Office dan gambar', () => {
        const wrapper = mount(UploadKontenModal, {
            props: { show: true, categoryId: 10, scope: 'SOP' },
        });

        const formatText = wrapper.find('.allowed-format-text').text();
        for (const ada of ['PDF', 'DOCX', 'XLSX', 'PPTX', 'JPG', 'PNG', 'MP4', 'MOV', 'MKV', 'WEBM']) {
            expect(formatText, ada).toContain(ada);
        }

        expect(wrapper.find('input[type="file"]').attributes('accept'))
            .toBe('.pdf,.docx,.xlsx,.pptx,.jpg,.jpeg,.png,.mp4,.mov,.mkv,.webm');
    });

    it('memetakan ikon untuk berbagai tipe format berkas yang didukung', () => {
        const wrapper = mount(UploadKontenModal, {
            props: { show: true, categoryId: 10 },
        });

        expect(wrapper.vm.getFileIcon('pedoman.pdf')).toBe('📄');
        expect(wrapper.vm.getFileIcon('prosedur.docx')).toBe('📝');
        expect(wrapper.vm.getFileIcon('data.xlsx')).toBe('📊');
        expect(wrapper.vm.getFileIcon('presentasi.pptx')).toBe('📑');
        expect(wrapper.vm.getFileIcon('foto.png')).toBe('🖼️');
        expect(wrapper.vm.getFileIcon('video.mp4')).toBe('🎬');
    });
});

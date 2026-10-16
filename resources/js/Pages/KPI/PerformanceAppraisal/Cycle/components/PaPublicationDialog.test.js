import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaPublicationDialog from './PaPublicationDialog.vue';

const uiStubs = {
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisButton: {
        props: ['label', 'disabled', 'loading'],
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisDialog: {
        props: ['modelValue'],
        template: '<div v-if="modelValue"><slot /><slot name="footer-start" /><slot name="footer" /></div>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: {
        props: ['title', 'description', 'errorCode', 'requestId'],
        template: '<div class="result-state"><strong>{{ title }}</strong><span>{{ description }}</span><small>{{ errorCode }} {{ requestId }}</small></div>',
    },
    HcisStatCard: {
        props: ['label', 'value'],
        template: '<div class="stat-card">{{ label }} {{ value }}</div>',
    },
};

function mountDialog(props = {}) {
    return mount(PaPublicationDialog, {
        props: { modelValue: true, cycle: { Nama_Cycle: 'PA Semester 1', Kode_Cycle: 'PA-2026' }, ...props },
        global: { stubs: uiStubs },
    });
}

describe('PaPublicationDialog', () => {
    it('menampilkan angka status dan menegaskan hasil yang dilewati server', () => {
        const wrapper = mountDialog({
            summary: { terbit: 2, dihitung: 3, tertahan: 1, belum_dihitung: 4, total: 10 },
            canPublish: true,
            actionResult: {
                diterbitkan: 2,
                sudah_terbit: 0,
                tertahan: 1,
                belum_dihitung: 4,
                total_peserta: 10,
                siap_ditutup: false,
            },
        });

        expect(wrapper.text()).toContain('Total peserta 10');
        expect(wrapper.text()).toContain('Siap diterbitkan 3');
        expect(wrapper.text()).toContain('Tertahan 1');
        expect(wrapper.text()).toContain('Belum dihitung 4');
        expect(wrapper.text()).toContain('Siap ditutup');
        expect(wrapper.text()).toContain('Belum');
        expect(wrapper.text()).toContain('Peserta tertahan dan yang belum dihitung tetap tercatat');
        expect(wrapper.text()).toContain('Terbitkan hasil siap');
        expect(wrapper.text()).toContain('Tutup penerbitan');
    });

    it('mengirim aksi hanya melalui event dan mempertahankan error 422 apa adanya', async () => {
        const wrapper = mountDialog({
            summary: { terbit: 0, dihitung: 0, tertahan: 1, belum_dihitung: 2, total: 3 },
            canPublish: true,
            actionError: 'Penerbitan belum dapat ditutup karena masih ada peserta tertahan.',
            actionErrorStatus: 422,
            actionErrorCode: 'PA_PUBLICATION_INCOMPLETE',
            actionRequestId: 'req-publication-1',
        });

        const publishButton = wrapper.findAll('button').find((button) => button.text() === 'Terbitkan hasil siap');
        const closeButton = wrapper.findAll('button').find((button) => button.text() === 'Tutup penerbitan');
        await publishButton.trigger('click');
        // Terbit kini lewat konfirmasi lebih dulu.
        expect(wrapper.emitted('publish')).toBeFalsy();
        await wrapper.findAll('button').find((button) => button.text() === 'Ya, terbitkan').trigger('click');
        await closeButton.trigger('click');

        expect(wrapper.text()).toContain('Siklus akan dipindahkan ke PUBLISHED');
        const confirmCloseButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Ya, tutup penerbitan');
        await confirmCloseButton.trigger('click');

        expect(wrapper.emitted('publish')).toHaveLength(1);
        expect(wrapper.emitted('close-publication')).toHaveLength(1);
        expect(wrapper.text()).toContain('Penerbitan belum dapat ditutup karena masih ada peserta tertahan.');
        expect(wrapper.text()).toContain('PA_PUBLICATION_INCOMPLETE');
        expect(wrapper.text()).toContain('req-publication-1');
    });

    it('membedakan ringkasan kosong dari error', () => {
        const emptyWrapper = mountDialog({ summary: null, canPublish: false });
        expect(emptyWrapper.text()).toContain('Ringkasan penerbitan belum tersedia');
        expect(emptyWrapper.text()).not.toContain('Ringkasan penerbitan gagal dimuat');

        const errorWrapper = mountDialog({ error: 'Akses publikasi ditolak.', errorStatus: 403 });
        expect(errorWrapper.text()).toContain('Akses penerbitan tidak tersedia');
        expect(errorWrapper.text()).toContain('Akses publikasi ditolak.');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import HcisUpload from './HcisUpload.vue';

describe('HcisUpload owned primitive component', () => {
    it('renders label and dropzone correctly when drag prop is true', () => {
        const wrapper = mount(HcisUpload, {
            props: {
                label: 'Upload Dokumen Lampiran',
                drag: true,
                hint: 'PDF saja max 5MB',
            },
        });
        expect(wrapper.text()).toContain('Upload Dokumen Lampiran');
        expect(wrapper.find('.hcis-upload__dragger').exists()).toBe(true);
        expect(wrapper.text()).toContain('PDF saja max 5MB');
    });

    it('renders initial fileList items correctly', () => {
        const wrapper = mount(HcisUpload, {
            props: {
                fileList: [
                    { name: 'dokumen-test.pdf', size: 1048576, status: 'success' },
                ],
            },
        });
        expect(wrapper.text()).toContain('dokumen-test.pdf');
        expect(wrapper.text()).toContain('1 MB');
        expect(wrapper.find('.hcis-upload__file-card').exists()).toBe(true);
    });

    it('emits remove and update:modelValue when remove button clicked', async () => {
        const wrapper = mount(HcisUpload, {
            props: {
                modelValue: [
                    { id: 'f-1', name: 'berkas-1.pdf', size: 500000, status: 'success' },
                ],
            },
        });

        const removeBtn = wrapper.find('.hcis-upload__file-btn--remove');
        await removeBtn.trigger('click');

        expect(wrapper.emitted('remove')).toBeTruthy();
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([[]]);
    });

    it('applies is-dragover class on dragover event', async () => {
        const wrapper = mount(HcisUpload, {
            props: { drag: true },
        });

        const dragger = wrapper.find('.hcis-upload__dragger');
        await dragger.trigger('dragover');

        expect(dragger.classes()).toContain('is-dragover');
    });

    it('hides dropzone and renders limit banner when limit is reached', () => {
        const wrapper = mount(HcisUpload, {
            props: {
                limit: 1,
                fileList: [
                    { name: 'surat-dokter.pdf', size: 500000, status: 'success' },
                ],
            },
        });

        expect(wrapper.find('.hcis-upload__dragger').exists()).toBe(false);
        expect(wrapper.find('.hcis-upload__limit-banner').exists()).toBe(true);
        expect(wrapper.text()).toContain('Kapasitas Unggah Penuh (1/1)');
    });
    it('tombol Kamera membuka input ber-capture, tombol File membuka pemilih berkas', async () => {
        // Photo picker Android tidak menawarkan kamera; tanpa input capture
        // sendiri, "kamera" hanya membuka galeri.
        const wrapper = mount(HcisUpload, {
            props: { drag: true, camera: true, accept: 'image/jpeg,image/png,.jpg,.jpeg,.png' },
            attachTo: document.body,
        });
        const [inputFile, inputKamera] = wrapper.findAll('input[type="file"]').map((w) => w.element);
        expect(inputKamera.getAttribute('capture')).toBe('environment');
        expect(inputFile.hasAttribute('capture')).toBe(false);

        const klikKamera = vi.spyOn(inputKamera, 'click').mockImplementation(() => {});
        const klikFile = vi.spyOn(inputFile, 'click').mockImplementation(() => {});

        await wrapper.find('.hcis-upload__action-btn--camera').trigger('click');
        expect(klikKamera).toHaveBeenCalledTimes(1);
        expect(klikFile).not.toHaveBeenCalled();

        await wrapper.find('.hcis-upload__action-btn--single:not(.hcis-upload__action-btn--camera)').trigger('click');
        expect(klikFile).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });
});

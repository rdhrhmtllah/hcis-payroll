import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import AppNotificationModal from './AppNotificationModal.vue';

// Modal hanya mengambil daftar saat dibuka; cukup balas kosong supaya render selesai.
beforeEach(() => {
    global.fetch = vi.fn(() =>
        Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ success: true, result: [], total_page: 1 }),
        }),
    );
});

function mountModal() {
    return mount(AppNotificationModal, {
        props: { show: true },
        global: { stubs: { Teleport: true, transition: false } },
    });
}

describe('AppNotificationModal — modul VISITOR', () => {
    it('menyediakan VISITOR sebagai pilihan saringan modul', () => {
        const nilaiOpsi = mountModal()
            .findAll('select option')
            .map((o) => o.attributes('value'));

        expect(nilaiOpsi).toContain('VISITOR');
    });

    it('memberi VISITOR ikon dan nada sendiri, bukan jatuh ke lencana generik', () => {
        const vm = mountModal().vm;

        expect(vm.getModuleIcon('VISITOR')).not.toBe(vm.getModuleIcon('MODUL_TAK_DIKENAL'));
        expect(vm.getModuleTone('VISITOR')).not.toBe(vm.getModuleTone('MODUL_TAK_DIKENAL'));
    });
});

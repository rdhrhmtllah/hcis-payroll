import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import GuestRegistrationPage from './GuestRegistrationPage.vue';

const viewDetail = vi.fn();

const HistoryStub = {
    name: 'TamuHistory',
    template: '<div />',
    methods: {
        viewDetail,
        fetchData() {},
    },
};

function mountPage(search, props = {}) {
    Object.defineProperty(window, 'location', {
        value: { ...window.location, search },
        writable: true,
    });

    return mount(GuestRegistrationPage, {
        props,
        global: {
            stubs: {
                VisitorWorkspace: {
                    template: '<div><slot name="panel-form" /><slot name="panel-history" /></div>',
                },
                TamuForm: true,
                HcisAlert: true,
                TamuHistory: HistoryStub,
            },
        },
    });
}

beforeEach(() => {
    viewDetail.mockClear();
    global.fetch = vi.fn(() =>
        Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, result: {} }) }),
    );
});

// Tautan notifikasi membawa token tersamar, bukan Id berurutan.
const TOKEN = 'eyJpdiI6IlRva2VuVWppQ29udG9oIiwidmFsdWUiOiJ4WXoifQ';

describe('GuestRegistrationPage — tautan detail dari notifikasi', () => {
    it('meneruskan token dari ?detail= apa adanya ke detail transaksi', async () => {
        const wrapper = mountPage(`?detail=${TOKEN}`);
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('history');
        expect(viewDetail).toHaveBeenCalledWith({ Id: TOKEN });
    });

    it('masih menerima Id polos demi notifikasi lama', async () => {
        const wrapper = mountPage('?detail=77');
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('history');
        expect(viewDetail).toHaveBeenCalledWith({ Id: '77' });
    });

    it('tidak menyentuh apa pun saat tautan biasa', async () => {
        const wrapper = mountPage('');
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('form');
        expect(viewDetail).not.toHaveBeenCalled();
    });

    it('mengabaikan detail yang bukan token maupun Id transaksi', async () => {
        const wrapper = mountPage('?detail=-3');
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('form');
        expect(viewDetail).not.toHaveBeenCalled();
    });
});

/**
 * Tipe tamu ditetapkan rute (DDL-2026-121). Halaman Regular dan B2B memakai
 * komponen yang sama; yang membedakannya hanyalah props dari controller.
 */
describe('GuestRegistrationPage — halaman per tipe', () => {
    it('halaman Regular memuat statistik dari prefiks bawaan', async () => {
        const wrapper = mountPage('');
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/transaksitamu-stats');
        expect(wrapper.findComponent({ name: 'TamuForm' }).props('tipe')).toBe('REGULAR');
        expect(wrapper.findComponent({ name: 'TamuForm' }).props('apiPrefix')).toBe('transaksitamu');
    });

    it('halaman B2B memakai prefiks API-nya sendiri dan meneruskan tipe ke form', async () => {
        const aturanForm = { rentang_maks_hari: 30, foto_maks_kb: 5120, foto_mime: ['jpg', 'png'] };
        const wrapper = mountPage('', {
            tipeTamu: 'B2B',
            apiPrefix: 'transaksi-tamu-b2b',
            serverNow: '2026-09-18T08:00',
            aturanForm,
        });
        await flushPromises();

        expect(global.fetch).toHaveBeenCalledWith('/transaksi-tamu-b2b-stats');
        expect(global.fetch).not.toHaveBeenCalledWith('/transaksitamu-stats');

        const form = wrapper.findComponent({ name: 'TamuForm' });
        expect(form.props('tipe')).toBe('B2B');
        expect(form.props('apiPrefix')).toBe('transaksi-tamu-b2b');
        expect(form.props('serverNow')).toBe('2026-09-18T08:00');
        expect(form.props('aturanForm')).toEqual(aturanForm);
    });
});

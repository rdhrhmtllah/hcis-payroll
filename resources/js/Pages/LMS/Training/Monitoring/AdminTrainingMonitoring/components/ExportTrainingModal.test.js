import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import ExportTrainingModal from './ExportTrainingModal.vue';
import FilterPanel from '@/components/legacy/Filter/modernFilter.vue';

vi.mock('axios');

const BASE = '/lms/admin/monitoring';

/**
 * Balasan default tiap endpoint yang disentuh saat modal dibuka. `training`
 * sengaja bisa ditimpa per-test karena di situlah bug-nya berada.
 */
function pasangAxios({ training } = {}) {
    const balasanTraining = training ?? { items: [], total: 0, truncated: false };

    axios.get = vi.fn((url) => {
        if (url === `${BASE}/export/trainings/search`) {
            return Promise.resolve({ data: { success: true, result: balasanTraining } });
        }
        if (url === `${BASE}/filter-options`) {
            return Promise.resolve({
                data: {
                    success: true,
                    result: { lokasi: [], departemen: [], divisi: [], level: [], jabatan: [], kategori: [] },
                },
            });
        }
        if (url === `${BASE}/export/employees`) {
            return Promise.resolve({
                data: { success: true, result: { data: [], pagination: { current_page: 1, total_pages: 1, total: 0 } } },
            });
        }
        return Promise.resolve({ data: { success: true, result: [] } });
    });

    return axios.get;
}

async function bukaModal(opsi = {}) {
    pasangAxios(opsi);

    const wrapper = mount(ExportTrainingModal, {
        props: { show: false },
        global: {
            stubs: {
                // Modal harus tetap merender slot: watcher `show` membaca
                // this.$el.querySelector, yang gagal kalau akar komponen hanya
                // node komentar.
                Modal: { template: '<div><slot /></div>' },
                FilterPanel: true,
            },
        },
    });

    await wrapper.setProps({ show: true });
    await flushPromises();
    return wrapper;
}

/** Ambil konfigurasi filter "Nama Training" apa adanya dari komponen. */
function filterTraining(wrapper) {
    return wrapper.vm.filterConfig.filters.find((f) => f.key === 'nama_training');
}

describe('ExportTrainingModal — filter Nama Training', () => {
    beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true }));
    afterEach(() => vi.useRealTimers());

    it('mengirim kata kunci ke server saat pengguna mengetik', async () => {
        // Regresi: dulu daftar training di-fetch SEKALI tanpa `q`, lalu disaring
        // di browser. Karena server memotong hasilnya, training di luar potongan
        // pertama tidak pernah bisa dipilih walau namanya diketik lengkap.
        const wrapper = await bukaModal({
            training: { items: ['Basic K3L'], total: 71, truncated: true },
        });

        const get = axios.get;
        get.mockClear();

        filterTraining(wrapper).remoteMethod('premix');
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();

        const panggilan = get.mock.calls.filter(([url]) => url === `${BASE}/export/trainings/search`);
        expect(panggilan).toHaveLength(1);
        expect(panggilan[0][1]).toEqual({ params: { q: 'premix' } });
    });

    it('menandai filter sebagai pencarian server, bukan penyaring lokal', async () => {
        const wrapper = await bukaModal();
        const filter = filterTraining(wrapper);

        expect(filter.remote).toBe(true);
        expect(typeof filter.remoteMethod).toBe('function');
        expect(filter.filterable).toBe(true);
    });

    it('melaporkan sisa training yang tidak ikut terkirim', async () => {
        // Pembatasan tidak boleh senyap: pengguna harus tahu daftar yang tampak
        // bukan keseluruhan.
        const wrapper = await bukaModal({
            training: { items: ['Basic K3L', 'Food Safety'], total: 71, truncated: true },
        });

        const opsi = filterTraining(wrapper).options;
        const catatan = opsi[opsi.length - 1];

        expect(catatan.label).toContain('69');
        expect(catatan.label).toContain('ketik untuk mencari');
        expect(catatan.disabled).toBe(true);
    });

    it('tidak menambah catatan sisa saat seluruh training terkirim', async () => {
        const wrapper = await bukaModal({
            training: { items: ['Basic K3L', 'Food Safety'], total: 2, truncated: false },
        });

        expect(filterTraining(wrapper).options.map((o) => o.value)).toEqual(['Basic K3L', 'Food Safety']);
    });

    it('mempertahankan training yang sudah dipilih ketika hasil pencarian berganti', async () => {
        // Pada mode remote, daftar opsi ditimpa tiap pencarian. Tanpa penggabungan
        // ini pilihan lama lenyap dari daftar begitu pengguna mencari kata lain.
        const wrapper = await bukaModal({
            training: { items: ['Training Premix'], total: 71, truncated: true },
        });

        wrapper.vm.filters.nama_training = ['Training Premix'];
        await flushPromises();

        pasangAxios({ training: { items: ['Basic K3L'], total: 71, truncated: true } });
        filterTraining(wrapper).remoteMethod('basic');
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();

        const nilai = filterTraining(wrapper).options.map((o) => o.value);
        expect(nilai).toContain('Basic K3L');
        expect(nilai).toContain('Training Premix');
    });

    it('mengabaikan balasan pencarian yang datang terlambat', async () => {
        // Ketikan cepat menghasilkan beberapa permintaan; yang lambat tidak boleh
        // menimpa hasil ketikan terakhir.
        const wrapper = await bukaModal();

        let selesaikanLama;
        axios.get = vi.fn((url) => {
            if (url !== `${BASE}/export/trainings/search`) {
                return Promise.resolve({ data: { success: true, result: [] } });
            }
            if (!selesaikanLama) {
                return new Promise((resolve) => {
                    selesaikanLama = () =>
                        resolve({ data: { success: true, result: { items: ['LAMA'], total: 1, truncated: false } } });
                });
            }
            return Promise.resolve({ data: { success: true, result: { items: ['BARU'], total: 1, truncated: false } } });
        });

        wrapper.vm.fetchTrainings('a');
        const menunggu = wrapper.vm.fetchTrainings('ab');
        await menunggu;

        selesaikanLama();
        await flushPromises();

        expect(wrapper.vm.trainingOptions).toEqual(['BARU']);
    });
});

describe('modernFilter — dukungan pencarian server', () => {
    it('meneruskan remote dan remoteMethod ke el-select', async () => {
        // Separuh perbaikan ada di komponen filter bersama: tanpa penerusan ini,
        // `remote` di konfigurasi halaman tidak berefek apa pun.
        const remoteMethod = vi.fn();
        const wrapper = mount(FilterPanel, {
            props: {
                config: {
                    filters: [
                        {
                            key: 'nama_training',
                            label: 'Nama Training',
                            type: 'select',
                            filterable: true,
                            multiple: true,
                            remote: true,
                            remoteMethod,
                            options: [{ value: 'A', label: 'A' }],
                        },
                    ],
                },
                filters: { nama_training: [] },
            },
        });
        await flushPromises();

        const select = wrapper.findComponent({ name: 'ElSelect' });
        expect(select.exists()).toBe(true);
        expect(select.props('remote')).toBe(true);
        expect(select.props('remoteMethod')).toBe(remoteMethod);
    });

    it('meneruskan status nonaktif tiap opsi', async () => {
        const wrapper = mount(FilterPanel, {
            props: {
                config: {
                    filters: [
                        {
                            key: 'nama_training',
                            type: 'select',
                            options: [
                                { value: 'A', label: 'A' },
                                { value: 'catatan', label: '+69 training lain', disabled: true },
                            ],
                        },
                    ],
                },
                filters: { nama_training: [] },
            },
        });
        await flushPromises();

        const opsi = wrapper.findAllComponents({ name: 'ElOption' });
        expect(opsi).toHaveLength(2);
        expect(opsi[0].props('disabled')).toBe(false);
        expect(opsi[1].props('disabled')).toBe(true);
    });
});

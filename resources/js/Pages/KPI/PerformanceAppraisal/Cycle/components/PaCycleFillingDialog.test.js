import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaCycleFillingDialog from './PaCycleFillingDialog.vue';

const uiStubs = {
    HcisBadge: { props: ['label'], template: '<span class="badge-stub">{{ label }}</span>' },
    HcisButton: {
        /* `href` IKUT DIDEKLARASIKAN: tombol yang sebenarnya tautan dirender sebagai <a> di
           komponen aslinya, dan stub yang membuang href membuat penjaga tautan di bawah
           mengukur stub-nya sendiri, bukan komponennya. */
        props: ['label', 'disabled', 'href'],
        emits: ['click'],
        template: '<button :disabled="disabled" :data-href="href" @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisDialog: {
        props: ['modelValue'],
        template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: {
        props: ['title', 'description', 'errorCode', 'requestId'],
        template: '<div class="result-state">{{ title }} {{ description }} {{ errorCode }} {{ requestId }}</div>',
    },
    PaLiquidProgressDialog: {
        props: ['modelValue', 'status', 'terjadwal', 'total', 'sisa'],
        template: '<div v-if="modelValue" class="liquid-dialog-stub">{{ status }} {{ terjadwal }}/{{ total }} sisa {{ sisa }}</div>',
    },
};

const cycle = {
    Id_PA_Cycle: 12,
    Nama_Cycle: 'Siklus Contoh',
    Kode_Cycle: 'PA-2026-H1',
    Pengisian_Dari: '2026-07-01',
    Pengisian_Sampai: '2026-07-14',
};

describe('PaCycleFillingDialog', () => {
    /**
     * JALAN KELUAR DARI SISA YANG TAK PERNAH BISA DIJADWALKAN.
     *
     * Sebagian sisa memang tidak bisa dijadwalkan berapa kali pun tombolnya ditekan — HC
     * Learn menolak orangnya, misalnya sudah resign di sisi mereka. Dialog yang hanya
     * menawarkan "Jadwalkan sisanya" membuat admin menekan tombol yang tak pernah bisa
     * menepatinya, tanpa satu pun petunjuk ke mana ia harus pergi; itulah kebuntuan yang
     * dilaporkan pada siklus PA-2026-02.
     *
     * Tautannya menunjuk LANGSUNG ke langkahnya. Tanpa `?langkah=`, wizard membuka langkah
     * mana pun yang terakhir disinggahi, dan admin kembali menebak.
     *
     * Perubahan yang membuat uji ini merah: hapus tombol "Revisi penugasan", atau
     * jadikan alamatnya wizard tanpa penunjuk langkah.
     */
    it('menawarkan jalan ke Langkah 5 saat ada rater yang belum dijadwalkan', () => {
        const wrapper = mount(PaCycleFillingDialog, {
            props: {
                modelValue: true,
                mode: 'open',
                cycle,
                penjadwalan: { total: 1140, terjadwal: 1139, belum: 1 },
            },
            global: { stubs: uiStubs },
        });

        const perbaiki = wrapper.findAll('button').find((b) => b.text().includes('Revisi penugasan'));

        expect(perbaiki).toBeTruthy();
        expect(perbaiki.attributes('data-href')).toBe('/performance-appraisal/siapkan/12?langkah=PENUGASAN');

        /* Sebabnya juga DIKATAKAN, bukan hanya jalannya disediakan: tanpa kalimat itu admin
           menekan "Jadwalkan sisanya" berkali-kali lebih dulu. */
        expect(wrapper.text()).toContain('ditolak HC Learn');
    });

    /** Tidak ada sisa berarti tidak ada yang perlu diperbaiki — tautannya pun tidak muncul. */
    it('tidak menawarkan perbaikan saat semua rater sudah terjadwal', () => {
        const wrapper = mount(PaCycleFillingDialog, {
            props: {
                modelValue: true,
                mode: 'open',
                cycle,
                penjadwalan: { total: 1140, terjadwal: 1140, belum: 0 },
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.findAll('button').find((b) => b.text().includes('Revisi penugasan'))).toBeFalsy();
    });

    /* Di halaman siklus jalannya modal Revisi Penugasan, bukan wizard penyiapan. */
    it('membuka modal revisi penugasan bila pemasangnya menyediakannya', async () => {
        const wrapper = mount(PaCycleFillingDialog, {
            props: { modelValue: true, mode: 'open', cycle, penjadwalan: { total: 10, terjadwal: 9, belum: 1 }, adaRevisiPenugasan: true },
            global: { stubs: uiStubs },
        });

        const revisi = wrapper.find('[data-uji="pengisian-revisi-penugasan"]');
        expect(revisi.attributes('data-href')).toBeUndefined();
        await revisi.trigger('click');
        expect(wrapper.emitted('revisi-penugasan')[0][0]).toEqual(cycle);
    });

    it('menampilkan periode pengisian tanggal mulai dan selesai', () => {
        const wrapper = mount(PaCycleFillingDialog, {
            props: {
                modelValue: true,
                mode: 'open',
                cycle,
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.text()).toContain('01 Jul 2026');
        expect(wrapper.text()).toContain('14 Jul 2026');
        expect(wrapper.text()).toContain('Periode Pengisian');
    });

    it('menampilkan ringkasan periode pengisian pada mode penutupan', () => {
        const wrapper = mount(PaCycleFillingDialog, {
            props: {
                modelValue: true,
                mode: 'close',
                cycle,
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.text()).toContain('Ringkasan Periode Pengisian');
        expect(wrapper.text()).toContain('01 Jul 2026');
        expect(wrapper.text()).toContain('14 Jul 2026');
    });

    it('mempertahankan pesan aturan server beserta metadata error', () => {
        const wrapper = mount(PaCycleFillingDialog, {
            props: {
                modelValue: true,
                mode: 'open',
                cycle,
                actionError: 'Jendela pengisian belum ditetapkan.',
                actionErrorStatus: 422,
                actionErrorCode: 'PA_FILLING_WINDOW_REQUIRED',
                actionRequestId: 'req-1',
            },
            global: { stubs: uiStubs },
        });

        expect(wrapper.text()).toContain('Jendela pengisian belum ditetapkan.');
        expect(wrapper.text()).toContain('PA_FILLING_WINDOW_REQUIRED');
        expect(wrapper.text()).toContain('req-1');
    });

    it('menonaktifkan tombol Buka Pengisian saat masih ada rater yang belum dijadwalkan', () => {
        const wrapper = mount(PaCycleFillingDialog, {
            props: {
                modelValue: true,
                mode: 'open',
                cycle,
                penjadwalan: { total: 3578, terjadwal: 1800, belum: 1778, simulasi: 1800 },
            },
            global: { stubs: uiStubs },
        });

        const bukaBtn = wrapper.findAll('button').find((b) => b.text().includes('Buka pengisian'));
        expect(bukaBtn).toBeTruthy();
        expect(bukaBtn.attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('1.778 rater belum dijadwalkan');
    });

    it('membuka modal konfirmasi penjadwalan saat tombol Jadwalkan sisanya diklik', async () => {
        const wrapper = mount(PaCycleFillingDialog, {
            props: {
                modelValue: true,
                mode: 'open',
                cycle,
                penjadwalan: { total: 3578, terjadwal: 1800, belum: 1778, simulasi: 1800 },
            },
            global: { stubs: uiStubs },
        });

        const jadwalkanBtn = wrapper.findAll('button').find((b) => b.text().includes('Jadwalkan sisanya'));
        expect(jadwalkanBtn).toBeTruthy();
        await jadwalkanBtn.trigger('click');

        expect(wrapper.text()).toContain('Jadwalkan Akses untuk 1.778 Rater');
        expect(wrapper.text()).toContain('Ya, Jadwalkan Sekarang');
    });
});

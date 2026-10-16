import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaAssignmentDialog from './PaAssignmentDialog.vue';

const uiStubs = {
    HcisAlert: {
        props: ['title', 'description', 'variant'],
        template:
            '<div class="alert-stub" :data-variant="variant"><strong>{{ title }}</strong><span>{{ description }}</span><slot /><slot name="actions" /></div>',
    },
    HcisBadge: {
        props: ['label'],
        template: '<span class="badge-stub">{{ label }}</span>',
    },
    HcisButton: {
        props: ['label', 'disabled'],
        template: '<button :disabled="disabled">{{ label }}</button>',
    },
    HcisDialog: {
        props: ['modelValue'],
        template: '<div v-if="modelValue"><slot /><slot name="footer-start" /><slot name="footer" /></div>',
    },
    HcisField: {
        props: ['id', 'modelValue', 'label'],
        emits: ['update:modelValue'],
        template:
            '<label :for="id">{{ label }}<input :id="id" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
    },
    HcisIcon: { template: '<i />' },
    HcisResultState: {
        props: ['title', 'description'],
        template: '<div class="result-state"><strong>{{ title }}</strong><span>{{ description }}</span></div>',
    },
    HcisStatusBadge: {
        props: ['label'],
        template: '<span class="status-badge-stub">{{ label }}</span>',
    },
};

const participant = {
    Id_PA_Participant: 12,
    Nama_Karyawan_Saat_Cycle: 'Ahmad Fauzan',
    Kode_Karyawan: 'A0118',
    Nama_Level_Saat_Cycle: 'STAFF',
};

const categories = [
    {
        kode_kategori: 'ATASAN',
        nama_kategori: 'Atasan Langsung',
        min_rater: 1,
        maks_rater: 2,
        terpasang: 1,
        butuh_manual: false,
        izinkan_manual: true,
        calon: [{ kode_karyawan: 'A0044', nama: 'Dewi Anggraini', sudah_ditugaskan: true }],
    },
    {
        kode_kategori: 'PEER',
        nama_kategori: 'Rekan Kerja',
        min_rater: 2,
        maks_rater: 4,
        terpasang: 0,
        butuh_manual: true,
        izinkan_manual: true,
        calon: [],
    },
];

function mountDialog(props = {}) {
    return mount(PaAssignmentDialog, {
        props: {
            modelValue: true,
            participant,
            categories,
            readiness: {
                siap_dibuka: false,
                siap_dihitung: false,
                kategori: {
                    ATASAN: { cukup_terpasang: true, terpasang: 1, selesai: 1 },
                    PEER: { cukup_terpasang: false, terpasang: 0, selesai: 0 },
                },
            },
            selection: { ATASAN: ['A0044'], PEER: [] },
            manual: { PEER: { kode_perusahaan: '', kode_karyawan: '', alasan_manual: '' } },
            ...props,
        },
        global: { stubs: uiStubs },
    });
}

describe('PaAssignmentDialog', () => {
    /**
     * PENILAI YANG DITOLAK HC LEARN DITANDAI DI TEMPAT PENGGANTINYA DIPILIH.
     *
     * Admin sampai di dialog ini justru karena satu penilai ditolak. Tanpa tanda, ia membuka
     * daftar berisi belasan nama dan harus mengingat sendiri siapa yang tadi disebut
     * bermasalah — lalu mencabut orang yang salah, atau mencentang pengganti sambil
     * meninggalkan yang bermasalah tetap terpasang.
     *
     * Perubahan yang membuat uji ini merah: buang prop `penilaiDitolak`, atau tandai barisnya
     * tanpa menyebut alasannya.
     */
    it('menandai penilai yang ditolak HC Learn beserta alasannya', () => {
        const wrapper = mountDialog({
            penilaiDitolak: {
                A0044: { alasan: 'SUDAH_RESIGN', keterangan: 'Karyawan terdaftar tetapi tidak aktif.' },
            },
        });

        expect(wrapper.text()).toContain('SUDAH_RESIGN');
        expect(wrapper.find('.pa-candidate.is-ditolak').exists()).toBe(true);
    });

    /** Tanpa penolakan, barisnya tetap seperti biasa — tandanya tidak muncul sendiri. */
    it('tidak menandai siapa pun bila tidak ada yang ditolak', () => {
        const wrapper = mountDialog();

        expect(wrapper.find('.pa-candidate.is-ditolak').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('SUDAH_RESIGN');
    });

    it('merender kategori dan meneruskan perubahan pilihan serta manual ke page', async () => {
        const wrapper = mountDialog();

        expect(wrapper.text()).toContain('Atasan Langsung');
        expect(wrapper.text()).toContain('Rekan Kerja');
        expect(wrapper.text()).toContain('1 rater dipilih');
        /* Kesiapan hitung TIDAK boleh muncul di layar penetapan penilai: ia menjawab
           pertanyaan tahap lain, dan di tahap ini selalu berbunyi "belum" karena penilaiannya
           memang belum dikerjakan. Dituntut ABSEN supaya tidak diam-diam kembali. */
        expect(wrapper.text()).not.toContain('siap dihitung');
        expect(wrapper.text()).toContain('Minimum terpasang');

        await wrapper.find('input[type="checkbox"]').setValue(false);
        expect(wrapper.emitted('toggle-candidate')).toEqual([
            [{ kodeKategori: 'ATASAN', candidate: categories[0].calon[0] }],
        ]);

        await wrapper.find('#kode-karyawan-PEER').setValue('A0099');
        expect(wrapper.emitted('manual-change')).toEqual([
            [{ kodeKategori: 'PEER', field: 'kode_karyawan', value: 'A0099' }],
        ]);
    });

    it('membedakan akses penugasan yang tidak tersedia dan error aturan', () => {
        const forbiddenWrapper = mountDialog({ error: 'Akses ditolak.', errorStatus: 403 });
        expect(forbiddenWrapper.text()).toContain('Akses penugasan tidak tersedia');
        expect(forbiddenWrapper.text()).toContain('Akses ditolak.');

        const rejectedWrapper = mountDialog({ error: 'Siklus sudah dikunci.', errorStatus: 422 });
        expect(rejectedWrapper.text()).toContain('Usulan rater tidak dapat dimuat');
        expect(rejectedWrapper.text()).toContain('Siklus sudah dikunci.');
        expect(rejectedWrapper.text()).not.toContain('Pilih penilai untuk setiap kategori');
    });

    it('menampilkan hasil server termasuk nilai di atas 100, status, dan alasan tertahan', () => {
        const wrapper = mountDialog({
            submitSuccess: '2 penugasan tersimpan.',
            result: {
                status: 'TERTAHAN',
                versi: 2,
                nilai_kpi: 104.5,
                nilai_kompetensi: 82.31,
                final_score: 104.5,
                kode_grade: 'A',
                kode_kuadran: 'STRIVING_STAR',
                alasan_tertahan: 'Kelengkapan penilaian belum memenuhi ambang.',
            },
        });

        expect(wrapper.text()).toContain('2 penugasan tersimpan.');
        expect(wrapper.text()).toContain('104,5');
        expect(wrapper.text()).toContain('TERTAHAN');
        expect(wrapper.text()).toContain('Kelengkapan penilaian belum memenuhi ambang.');
    });

    it('membedakan usulan kosong dari error dan menonaktifkan aksi simpan', () => {
        const wrapper = mountDialog({ categories: [], readiness: null });

        expect(wrapper.text()).toContain('Belum ada aturan rater');
        const saveButton = wrapper.findAll('button').find((button) => button.text() === 'Simpan penugasan');
        expect(saveButton.attributes('disabled')).toBeDefined();
    });
});

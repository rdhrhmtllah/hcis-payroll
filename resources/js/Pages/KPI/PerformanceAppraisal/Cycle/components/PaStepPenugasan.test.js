import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PaStepPenugasan from './PaStepPenugasan.vue';

/*
 | LANGKAH 5 ADALAH SATU-SATUNYA TEMPAT SIKLUS DIBEKUKAN.
 |
 | Sebelum 7 September 2026 penguncian hidup di daftar siklus: admin menyelesaikan langkah
 | ini, dipulangkan ke halaman lain, lalu harus mencari tombol kedua yang menanyakan hal yang
 | sama — padahal "penugasan sudah saya tinjau dan saya terima" DAN "bekukan siklusnya" adalah
 | keputusan yang sama.
 |
 | Yang dijaga berkas ini bukan tata letaknya, melainkan tiga sifat yang mudah hilang saat
 | dipindahkan: penguncian menuntut angka pra-kunci sudah terbaca, pencetakan pintu berjalan
 | DICICIL sampai habis, dan siklus yang sudah terkunci tidak dikunci dua kali.
 */

const paApiMock = vi.hoisted(() => ({
    kesehatanPenugasan: vi.fn(),
    progresPenugasan: vi.fn(),
    ringkasanPraKunci: vi.fn(),
    kunciSiklus: vi.fn(),
    jadwalkanPenilaian: vi.fn(),
    penugasanBermasalah: vi.fn(),
    bentukPenugasanMassal: vi.fn(),
    batalkanPenugasan: vi.fn(),
    kesiapanKaryawanHcLearn: vi.fn(),
    usulanPenilai: vi.fn(),
    sinkronkanKaryawanHcLearn: vi.fn(),
    perubahanPeserta: vi.fn(),
    segarkanLevelTertahan: vi.fn(),
    tambahPraKecuali: vi.fn(),
    mulaiPembentukanLatar: vi.fn(),
    statusPembentukanLatar: vi.fn(),
    ulangiPembentukanLatar: vi.fn(),
    hentikanPembentukanLatar: vi.fn(),
    mulaiPenjadwalanLatar: vi.fn(),
    statusPenjadwalanLatar: vi.fn(),
    ulangiPenjadwalanLatar: vi.fn(),
    hapusJadwalDibatalkan: vi.fn(),
    tarikHasilPenjadwalan: vi.fn(),
    cariRevisiPeserta: vi.fn(),
    opsiFilterKaryawanPraKecuali: vi.fn(),
}));

/* Panel Tugas & Aktivitas di topbar: cukup diketahui ia diberi tahu. */
const notifyNewTask = vi.hoisted(() => vi.fn());
vi.mock('@/composables/useActivityCenter', () => ({ useActivityCenter: () => ({ notifyNewTask }) }));

/** Tugas penjadwalan latar, kecuali yang ditimpa. */
function tugas(ganti = {}) {
    return { id: 'pa-jdw-1', status: 'queued', fase: 'kirim', batch: 0, persen: 0, galat: null, ...ganti };
}

/** Tanpa perubahan sejak penugasan dibentuk, kecuali yang ditimpa. */
function perubahan(ganti = {}) {
    return {
        dapat_ditindak: true,
        ada_penugasan: true,
        belum_berpenugasan: 0,
        belum_diterapkan: { karyawan_baru: 0, level_berubah: 0 },
        level_tertahan: [],
        resign: [],
        ...ganti,
    };
}

vi.mock('../../api/paApi.js', () => ({ default: paApiMock }));

const uiStubs = {
    HcisAlert: { template: '<div><slot /></div>' },
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisButton: {
        props: ['label', 'disabled', 'loading'],
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}<slot /></button>',
    },
    HcisCard: { template: '<article><slot /></article>' },
    HcisCollapse: { template: '<div><slot /></div>' },
    HcisCollapseItem: { template: '<div><slot /></div>' },
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    HcisEmptyState: { template: '<div />' },
    HcisField: { template: '<label><slot /></label>' },
    HcisIcon: { template: '<i />' },
    HcisInput: { template: '<input />' },
    HcisProgress: { template: '<div />' },
    HcisResultState: { props: ['title'], template: '<div>{{ title }}</div>' },
    HcisSelect: { template: '<select><slot /></select>' },
    HcisSelectOption: { template: '<option />' },
    HcisSkeleton: { template: '<div />' },
    HcisStatusBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisTextarea: { template: '<textarea />' },
    HcisTooltip: { template: '<div><slot /></div>' },
    PaAssignmentDialog: { template: '<div />' },
    PaLiquidProgressDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
};

/** Ringkasan pra-kunci yang sehat, kecuali yang sengaja ditimpa tiap uji. */
function praKunci(ganti = {}) {
    return {
        status_siklus: 'PREPARED',
        pintu: { diharapkan: 400, tercetak: 0, kurang: 400 },
        peserta: { diikutkan: 120, dikecualikan: 2, dikecualikan_otomatis: 9, tertunda: 0 },
        penugasan: { total: 400, peserta_berpenilai: 120, peserta_tanpa_penilai: 0 },
        penghalang: [],
        boleh_dikunci: true,
        ...ganti,
    };
}

/** Baris penghalang nyata: disalin dari siklus PA-2026-01 pada 10 September 2026. */
function barisResign() {
    return {
        kode_perusahaan: '001',
        kode_karyawan: 'A1',
        nama: 'Frans Bachtiar',
        peran: ['PENILAI'],
        ada: true,
        siap: false,
        alasan: 'SUDAH_RESIGN',
        keterangan: 'Karyawan terdaftar tetapi tidak aktif / sudah resign.',
        dapat_disinkron: false,
        tanggungan: [{
            assignment_id: 4359,
            participant_id: 1221428,
            kode_karyawan: 'V59',
            nama: 'Samuel',
            id_level: 4,
            nama_level: 'Officer',
            divisi: 'Operasional',
            kategori: 'BAWAHAN',
            nama_kategori: 'Bawahan',
            penilai_sekarang: 2,
            min_rater: 2,
            cukup_dibatalkan: false,
        }],
    };
}

/** Kesiapan HC Learn yang sehat seluruhnya, kecuali yang sengaja ditimpa tiap uji. */
function kesiapanSiap(ganti = {}) {
    return {
        status: 'SIAP',
        ringkasan: {
            terlibat: 2, siap: 2, belum_terdaftar: 0, tidak_siap: 0,
            dinilai: 1, penilai_terpasang: 1, penilai_proyeksi: 0,
        },
        baris: [],
        catatan: '2 orang yang terlibat sudah siap dijadwalkan.',
        ...ganti,
    };
}

function pasang(props = {}) {
    return mount(PaStepPenugasan, {
        /* `bolehUbahPenilai` DIPISAH dari `dapatDisunting`, dan bakunya di sini true karena
           itulah keadaan siklus yang belum dikunci. Uji yang menguncinya mengirim false. */
        props: { cycleId: 77, dapatDisunting: true, bolehUbahPenilai: true, ...props },
        global: { stubs: uiStubs },
    });
}

function tombol(wrapper, teks) {
    return wrapper.findAll('button').find((b) => b.text().includes(teks));
}

/* Tombol penguncian dicari lewat kata KUNCI-nya, bukan kalimat penuhnya: "Kunci Siklus &
   Jadwalkan Penilaian" dan "Kunci Cycle & Jadwalkan Penilaian" adalah tombol yang sama. */
function tombolKunci(wrapper) {
    return wrapper.findAll('button').find((b) => /Kunci .*Jadwalkan/.test(b.text()) || /Kunci .*Cetak/.test(b.text()));
}

describe('PaStepPenugasan — penguncian di Langkah 5', () => {
    beforeEach(() => {
        Object.values(paApiMock).forEach((fn) => fn.mockReset());

        paApiMock.kesehatanPenugasan.mockResolvedValue({ baris: [], total_peserta_kurang: 0 });
        paApiMock.progresPenugasan.mockResolvedValue({ selesai: true, total: 0 });
        paApiMock.penugasanBermasalah.mockResolvedValue({ baris: [], total: 0 });
        paApiMock.ringkasanPraKunci.mockResolvedValue(praKunci());
        paApiMock.kunciSiklus.mockResolvedValue({ status: 'LOCKED' });
        paApiMock.jadwalkanPenilaian.mockResolvedValue({
            diminta: 0, gagal: 0, ringkasan: { total: 400, terjadwal: 400, belum: 0 },
        });
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({ result: kesiapanSiap() });
        paApiMock.perubahanPeserta.mockResolvedValue(perubahan());
        paApiMock.statusPenjadwalanLatar.mockResolvedValue({ tugas: null, ringkasan: null });
        paApiMock.statusPembentukanLatar.mockResolvedValue({ tugas: null });
        notifyNewTask.mockReset();
    });

    /**
     * KESIAPAN HC LEARN BERDIRI DI SINI JUGA, bukan hanya di Langkah 4.
     *
     * Di Langkah 4 penilainya masih proyeksi — penugasan belum terbentuk — sehingga "siap" di
     * sana tidak pernah bisa berarti "siap dijadwalkan". Pemeriksaan yang MENGIKAT adalah yang
     * berdiri di sini, sesaat sebelum siklus dikunci dan kirimannya berangkat: `store` menolak
     * 422 atas satu kode yang tak dikenal, dan penolakannya membatalkan SELURUH kiriman.
     *
     * Perubahan yang membuat uji ini merah: buang panelnya dari Langkah 5 dan andalkan yang di
     * Langkah 4 saja.
     */
    it('memeriksa kesiapan HC Learn di langkah ini, dan menyebut orang yang menghalangi', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapanSiap({
                status: 'PERLU_TINDAKAN',
                ringkasan: {
                    terlibat: 2, siap: 1, belum_terdaftar: 0, tidak_siap: 1,
                    dinilai: 1, penilai_terpasang: 1, penilai_proyeksi: 0,
                },
                baris: [{
                    kode_perusahaan: '001',
                    kode_karyawan: 'A1',
                    nama: 'Frans Bachtiar',
                    peran: ['PENILAI'],
                    ada: true,
                    siap: false,
                    alasan: 'SUDAH_RESIGN',
                    keterangan: 'Karyawan terdaftar tetapi tidak aktif / sudah resign.',
                    dapat_disinkron: false,
                }],
                catatan: '1 orang perlu ditinjau sebelum penjadwalan.',
            }),
        });

        const wrapper = pasang();
        await flushPromises();

        expect(paApiMock.kesiapanKaryawanHcLearn).toHaveBeenCalledWith(77);
        expect(wrapper.text()).toContain('Frans Bachtiar');
        expect(wrapper.text()).toContain('SUDAH_RESIGN');

        /* Tombol sinkron TIDAK muncul: mendaftarkan ulang tidak menghidupkan akun nonaktif,
           dan tombol yang menjanjikan sebaliknya ditekan berkali-kali tanpa hasil. */
        expect(tombol(wrapper, 'Sinkronkan ke HC Learn')).toBeFalsy();
    });

    /**
     * PROYEKSI DI LANGKAH 5 ADALAH PERINGATAN, bukan keterangan.
     *
     * Penilai yang belum punya penugasan belum ikut diperiksa sebagai kenyataan. Membiarkan
     * angka itu lewat tanpa suara membuat admin mengunci siklus dengan keyakinan bahwa semua
     * orang sudah dicek, padahal sebagian belum pernah disinggung pemeriksaan ini.
     *
     * Perubahan yang membuat uji ini merah: hapus peringatannya, atau tampilkan tanpa melihat
     * `konteks` sehingga ia juga menyala di Langkah 4 — di sana proyeksi memang keadaan wajar.
     */
    it('memperingatkan ketika sebagian penilai masih proyeksi', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapanSiap({
                ringkasan: {
                    terlibat: 3, siap: 3, belum_terdaftar: 0, tidak_siap: 0,
                    dinilai: 1, penilai_terpasang: 1, penilai_proyeksi: 1,
                },
            }),
        });

        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.text()).toContain('masih proyeksi');
    });

    /**
     * TOMBOL "KELOLA RATER" DI PANEL HC LEARN BENAR-BENAR MEMBUKA DIALOGNYA.
     *
     * Panel yang hanya menyebut nama pesertanya tetap meninggalkan admin mencari orang itu
     * sendiri di tab di bawah. Yang diuji di sini bukan event-nya terpancar — itu sudah dijaga
     * di uji komponennya — melainkan bahwa Langkah 5 MENANGKAPNYA dan membuka dialog untuk
     * peserta yang benar. Penjaga satu arah yang hanya memeriksa sisi pemancar pernah membuat
     * tombol yang rapi tetapi tidak terhubung ke apa pun.
     *
     * Perubahan yang membuat uji ini merah: hapus `@kelola-penilai` dari pemasangan panel.
     */
    it('membuka dialog Kelola Rater untuk peserta yang disebut panel HC Learn', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapanSiap({
                status: 'PERLU_TINDAKAN',
                ringkasan: {
                    terlibat: 2, siap: 1, belum_terdaftar: 0, tidak_siap: 1,
                    dinilai: 1, penilai_terpasang: 1, penilai_proyeksi: 0,
                },
                baris: [{
                    kode_perusahaan: '001',
                    kode_karyawan: 'A1',
                    nama: 'Frans Bachtiar',
                    peran: ['PENILAI'],
                    ada: true,
                    siap: false,
                    alasan: 'SUDAH_RESIGN',
                    keterangan: 'Karyawan terdaftar tetapi tidak aktif / sudah resign.',
                    dapat_disinkron: false,
                    tanggungan: [{
                        assignment_id: 4359,
                        participant_id: 1221428,
                        kode_karyawan: 'V59',
                        nama: 'Samuel',
                        id_level: 4,
                        nama_level: 'Officer',
                        divisi: 'Operasional',
                        kategori: 'BAWAHAN',
                        nama_kategori: 'Bawahan',
                        penilai_sekarang: 2,
                        min_rater: 2,
                        cukup_dibatalkan: false,
                    }],
                }],
            }),
        });

        const wrapper = pasang();
        await flushPromises();

        const kelola = tombol(wrapper, 'Kelola Rater');
        expect(kelola).toBeTruthy();

        await kelola.trigger('click');
        await flushPromises();

        /* Dialognya memuat usulan penilai untuk PESERTA ITU — bukti ia terbuka pada orang yang
           benar, bukan pada peserta kosong. */
        expect(paApiMock.usulanPenilai).toHaveBeenCalledWith(1221428);
    });

    /**
     * SIKLUS TERKUNCI TETAP MENYISAKAN JALAN, dan itu justru yang diuji di sini.
     *
     * Sesudah dikunci, membentuk ulang penugasan massal memang tertutup — tetapi mengganti
     * SATU penilai tidak, sebab `TAMBAH_PENILAI` di server mengizinkan LOCKED. Tanpa
     * pemisahan ini, kesiapan HC Learn menyebut penilai yang sudah resign dan tak ada satu
     * tombol pun yang bisa ditekan untuk membereskannya.
     */
    it('tetap menawarkan Kelola Rater saat siklus terkunci tetapi penilai masih boleh diganti', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapanSiap({ status: 'PERLU_TINDAKAN', baris: [barisResign()] }),
        });

        const terkunci = pasang({ dapatDisunting: false, bolehUbahPenilai: true });
        await flushPromises();

        expect(tombol(terkunci, 'Bentuk Ulang Penugasan')).toBeFalsy();
        expect(tombol(terkunci, 'Kelola Rater')).toBeTruthy();
    });

    /**
     * DUA LAYAR BERSEBELAHAN TIDAK BOLEH SALING MEMBANTAH.
     *
     * Kesehatan penugasan lahir dari data KITA: rater kurang, tanpa aturan, atau nonaktif
     * menurut HCIS. Penilai yang ditolak HC Learn tidak masuk satu pun — A1 masih
     * `Aktif='Y'` di HCIS — sehingga layar ini pernah memasang spanduk hijau "Cycle siap
     * dikunci" dan "Perlu Perhatian 0" tepat di atas panel yang berkata satu penugasan WAJIB
     * DIGANTI. Yang dibaca admin lebih dulu adalah yang hijau, dan ia mengunci.
     *
     * Perubahan yang membuat uji ini merah: kembalikan spanduk dan penghitungnya membaca
     * `siapDikunci`/`totalKurang` dari server saja.
     */
    it('tidak menyatakan siap dikunci selama HC Learn menolak seseorang', async () => {
        paApiMock.ringkasanPraKunci.mockResolvedValue(praKunci({ boleh_dikunci: true }));
        paApiMock.kesehatanPenugasan.mockResolvedValue({
            baris: [], total_peserta_kurang: 0, siap_dikunci: true,
        });
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapanSiap({ status: 'PERLU_TINDAKAN', baris: [barisResign()] }),
        });

        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.text()).not.toContain('Seluruh Peserta Memenuhi Minimum Rater');
        expect(wrapper.text()).toContain('Perlu Penyesuaian');

        /* Pesertanya DISEBUT di daftar perhatian, bukan hanya dihitung: yang harus dibereskan
           adalah penugasannya, dan itu hanya bisa ditemukan lewat nama pesertanya. */
        expect(wrapper.text()).toContain('Samuel');
        expect(wrapper.text()).toContain('Frans Bachtiar');

        /* Dan tombol kuncinya MATI, dengan sebab yang tertulis. */
        const kunci = tombolKunci(wrapper);
        expect(kunci?.attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('ditolak HC Learn');
    });

    /**
     * PEMBENTUKAN MENGUBAH POPULASINYA, jadi hasil pemeriksaan lama jadi basi seketika.
     *
     * Perubahan yang membuat uji ini merah: jangan baca ulang kesiapan sesudah pembentukan
     * massal selesai.
     */
    it('membaca ulang kesiapan sesudah pembentukan penugasan selesai di server', async () => {
        vi.useFakeTimers();
        try {
            paApiMock.mulaiPembentukanLatar.mockResolvedValue({
                baru: true,
                tugas: { id: 'pa-btk-1', status: 'queued', jalur: 'cepat', dibuat: 0 },
            });
            // Peserta baru sesudah penyiapan dijalankan ulang: panel perubahan menawarkan "Lengkapi penugasan".
            paApiMock.perubahanPeserta.mockResolvedValue(perubahan({ belum_berpenugasan: 3 }));

            const wrapper = pasang();
            await flushPromises();
            expect(paApiMock.kesiapanKaryawanHcLearn).toHaveBeenCalledTimes(1);

            /* Lewat TOMBOLNYA, bukan memanggil fungsi di dalam instansnya: yang dijaga adalah
               perjalanan yang benar-benar dilalui admin. */
            const ulang = tombol(wrapper, 'Lengkapi penugasan');
            expect(ulang).toBeTruthy();
            await ulang.trigger('click');
            await flushPromises();

            /* Server yang membentuk — halaman tidak lagi memanggil potongan demi potongan. */
            expect(paApiMock.mulaiPembentukanLatar).toHaveBeenCalledWith(77);
            expect(paApiMock.bentukPenugasanMassal).not.toHaveBeenCalled();
            expect(notifyNewTask).toHaveBeenCalled();
            expect(paApiMock.kesiapanKaryawanHcLearn).toHaveBeenCalledTimes(1);

            /* Selama berjalan, tombol Lengkapi di daftar tugas TIDAK ditawarkan lagi (UAT 13 Okt:
               admin menekannya dua kali). */
            expect(tombol(wrapper, 'Sedang dibentuk')?.attributes('disabled')).toBeDefined();
            const praKunciSebelum = paApiMock.ringkasanPraKunci.mock.calls.length;

            paApiMock.statusPembentukanLatar.mockResolvedValue({
                tugas: { id: 'pa-btk-1', status: 'ready', jalur: 'lengkapi', dibuat: 3, ringkasan: { total: 3, sudah: 3, belum: 0, selesai: true } },
            });
            await vi.advanceTimersByTimeAsync(3000);
            await flushPromises();

            expect(paApiMock.kesiapanKaryawanHcLearn).toHaveBeenCalledTimes(2);
            // Ringkasan pra-kunci ikut dibaca ulang — dulu tertinggal bila langkah sebelumnya gagal.
            expect(paApiMock.ringkasanPraKunci.mock.calls.length).toBeGreaterThan(praKunciSebelum);
            wrapper.unmount();
        } finally {
            vi.useRealTimers();
        }
    });

    /* UAT 13 Okt: tugas pembentukan lama yang selesai (190/190) tidak boleh menimpa progres
       terbaru (190/191 sesudah satu peserta diikutkan kembali). */
    it('tugas pembentukan lama yang sudah selesai tidak menimpa progres terbaru', async () => {
        paApiMock.progresPenugasan.mockResolvedValue({ selesai: false, total: 191, sudah: 190, belum: 1 });
        paApiMock.statusPembentukanLatar.mockResolvedValue({
            tugas: { id: 'pa-btk-lama', status: 'ready', dibuat: 1138, ringkasan: { total: 190, sudah: 190, belum: 0, selesai: true } },
        });

        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.find('.pa-generate-cockpit-card').exists()).toBe(true);
        expect(wrapper.text()).toContain('Lengkapi Penugasan untuk 1 Peserta');
        expect(wrapper.find('.pa-kunci-panel').exists()).toBe(false);
        wrapper.unmount();
    });

    /* Kegagalan tugas (13 Okt: database penuh) tampil di bagian pembentukan sendiri, bukan teks
       abu di footer; tombolnya mengulang tugas yang sama. */
    it('menampilkan galat tugas pembentukan di bagiannya dan mengulang tugas yang sama', async () => {
        paApiMock.progresPenugasan.mockResolvedValue({ selesai: false, total: 190, sudah: 17, belum: 173 });
        paApiMock.statusPembentukanLatar.mockResolvedValue({
            tugas: { id: 'pa-btk-1', status: 'failed', galat: 'Database penuh — hubungi admin server.', dibuat: 17 },
        });
        paApiMock.ulangiPembentukanLatar.mockResolvedValue({ tugas: { id: 'pa-btk-1', status: 'queued', dibuat: 17 } });

        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.find('[data-uji="galat-pembentukan"]').exists()).toBe(true);

        await wrapper.find('.pa-hero-action-btn').trigger('click');
        await flushPromises();

        expect(paApiMock.ulangiPembentukanLatar).toHaveBeenCalledWith(77);
        expect(paApiMock.mulaiPembentukanLatar).not.toHaveBeenCalled();
        expect(wrapper.find('[data-uji="galat-pembentukan"]').exists()).toBe(false);
        expect(wrapper.find('[data-uji="info-pembentukan-latar"]').exists()).toBe(true);

        paApiMock.hentikanPembentukanLatar.mockResolvedValue({ tugas: { id: 'pa-btk-1', status: 'failed', galat: 'Dihentikan admin.' } });
        await wrapper.find('.pa-hero-pause-btn').trigger('click');
        await flushPromises();
        expect(paApiMock.hentikanPembentukanLatar).toHaveBeenCalledWith(77);
        wrapper.unmount();
    });

    /*
     | KESIAPAN HC LEARN BARU DIPERIKSA SESUDAH PEMBENTUKAN SELESAI (UAT 13 Okt).
     |
     | Sebelumnya panel ini tampil sejak awal dan meminta admin "jalankan pembentukan lebih
     | dulu" — di bawah bagian yang justru untuk itu. Sekarang ia bagian dari bagian 4, dan
     | sebelum pembentukan selesai yang tampil hanya satu kalimat penunjuk.
     |
     | Perubahan yang membuat uji ini merah: lepaskan `v-if="isGenerationComplete"` dari panelnya.
     */
    it('tidak memeriksa HC Learn sebelum penugasan selesai dibentuk', async () => {
        paApiMock.progresPenugasan.mockResolvedValue({ selesai: false, total: 120, sudah: 40, belum: 80 });

        const wrapper = pasang();
        await flushPromises();

        expect(paApiMock.kesiapanKaryawanHcLearn).not.toHaveBeenCalled();
        expect(wrapper.find('[data-uji="hcl-tunggu-pembentukan"]').exists()).toBe(true);
        expect(wrapper.find('.pa-kunci-panel').exists()).toBe(false);
    });

    /* Label "sedang membentuk" hanya untuk proses yang memang berjalan. Sebelumnya siklus yang
       belum pernah dibentuk tampil "Sedang Membentuk… 0 dari 190" di samping tombol "Mulai". */
    it('tidak berkata "sedang membentuk" bila pembentukan belum dimulai atau baru sebagian', async () => {
        paApiMock.progresPenugasan.mockResolvedValue({ selesai: false, total: 190, sudah: 0, belum: 190 });
        let wrapper = pasang();
        await flushPromises();
        expect(wrapper.text()).toContain('Penugasan Belum Dibentuk');
        expect(wrapper.text()).not.toContain('Sedang Membentuk');
        wrapper.unmount();

        paApiMock.progresPenugasan.mockResolvedValue({ selesai: false, total: 190, sudah: 40, belum: 150 });
        wrapper = pasang();
        await flushPromises();
        expect(wrapper.text()).toContain('Sebagian Terbentuk');
        expect(wrapper.text()).toContain('Lengkapi Penugasan untuk 150 Peserta');
        wrapper.unmount();
    });

    it('menampilkan angka pra-kunci sebelum tombolnya ditekan', async () => {
        const wrapper = pasang();
        await flushPromises();

        // Inilah yang membuat dialog konfirmasinya bisa dicabut: angkanya sudah di depan mata.
        // Kelasnya, bukan kalimatnya: label panel ini sering diperhalus, dan penjaga yang
        // merah karena kata berubah melatih orang mengabaikan warna merah.
        expect(wrapper.find('.pa-kunci-panel').exists()).toBe(true);
        expect(wrapper.text()).toContain('120');
        expect(tombolKunci(wrapper)).toBeTruthy();
    });

    /*
     | PENGIRIMANNYA MILIK SERVER, bukan halaman ini.
     |
     | Sampai Oktober 2026 halaman ini yang mengunci lalu memanggil penjadwalan 200 per putaran
     | sampai habis — untuk 10.000+ penugasan admin harus menjaga tab terbuka belasan menit, dan
     | menutupnya menghentikan pengiriman di tengah. Sekarang satu panggilan memulai tugas latar
     | (server yang mengunci dan mengirim), lalu halaman hanya membaca keadaannya.
     |
     | Perubahan yang membuat uji ini merah: kembalikan perulangan `jadwalkanPenilaian` ke
     | halaman, atau nyatakan selesai sebelum server melaporkan `ready`.
     */
    it('memulai tugas latar sekali, tidak mengirim batch dari browser, selesai saat server ready', async () => {
        vi.useFakeTimers();
        try {
            paApiMock.mulaiPenjadwalanLatar.mockResolvedValue({
                baru: true,
                tugas: tugas(),
                ringkasan: { total: 400, terjadwal: 0, antre: 0, gagal: 0, belum: 400 },
            });
            paApiMock.ringkasanPraKunci
                .mockResolvedValueOnce(praKunci())
                .mockResolvedValue(praKunci({
                    status_siklus: 'LOCKED',
                    pintu: { diharapkan: 400, tercetak: 400, kurang: 0, antre: 0 },
                }));

            const wrapper = pasang();
            await flushPromises();

            await tombolKunci(wrapper).trigger('click');
            await flushPromises();
            await tombol(wrapper, 'Ya, Kunci').trigger('click');
            await flushPromises();

            expect(paApiMock.mulaiPenjadwalanLatar).toHaveBeenCalledTimes(1);
            expect(paApiMock.mulaiPenjadwalanLatar).toHaveBeenCalledWith(77);
            expect(paApiMock.kunciSiklus).not.toHaveBeenCalled();
            expect(paApiMock.jadwalkanPenilaian).not.toHaveBeenCalled();
            expect(notifyNewTask).toHaveBeenCalled();

            /* Masih berjalan: belum boleh berkata selesai. */
            paApiMock.statusPenjadwalanLatar.mockResolvedValue({
                tugas: tugas({ status: 'processing', fase: 'tunggu', batch: 1 }),
                ringkasan: { total: 400, terjadwal: 0, antre: 400, gagal: 0, belum: 0 },
            });
            await vi.advanceTimersByTimeAsync(4000);
            expect(tombol(wrapper, 'Selesai')).toBeFalsy();
            expect(tombol(wrapper, 'Lihat Progres')).toBeTruthy();

            paApiMock.statusPenjadwalanLatar.mockResolvedValue({
                tugas: tugas({ status: 'ready', fase: 'tunggu', batch: 3, persen: 100 }),
                ringkasan: { total: 400, terjadwal: 400, antre: 0, gagal: 0, belum: 0 },
            });
            await vi.advanceTimersByTimeAsync(4000);
            await flushPromises();

            expect(tombol(wrapper, 'Selesai')).toBeTruthy();
            expect(wrapper.emitted('tersimpan')).toBeTruthy();
        } finally {
            vi.useRealTimers();
        }
    });

    /*
     | Tugas yang masih berjalan — dimulai dari tab lain, atau sebelum halaman ini ditutup —
     | terbaca saat halaman dibuka. Tombolnya hanya membuka progres; tidak memulai tugas kedua.
     */
    it('membaca tugas yang masih berjalan saat dibuka, dan tidak memulai yang kedua', async () => {
        paApiMock.ringkasanPraKunci.mockResolvedValue(praKunci({
            status_siklus: 'LOCKED',
            pintu: { diharapkan: 400, tercetak: 250, kurang: 150 },
        }));
        paApiMock.statusPenjadwalanLatar.mockResolvedValue({
            tugas: tugas({ status: 'processing', batch: 2 }),
            ringkasan: { total: 400, terjadwal: 250, antre: 0, gagal: 0, belum: 150 },
        });

        const wrapper = pasang();
        await flushPromises();

        const lihat = tombol(wrapper, 'Lihat Progres Penjadwalan');
        expect(lihat).toBeTruthy();
        await lihat.trigger('click');
        await flushPromises();

        expect(paApiMock.mulaiPenjadwalanLatar).not.toHaveBeenCalled();
        expect(paApiMock.ulangiPenjadwalanLatar).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    /*
     | Tugas yang GAGAL diulang lewat barisnya sendiri (panel Tugas & Aktivitas memuat satu baris
     | per siklus), bukan dengan memulai tugas baru di sampingnya. Siklus yang sudah LOCKED tidak
     | dikunci ulang dari sini — servernya yang memutuskan.
     */
    it('mengulang tugas yang gagal tanpa mengunci ulang siklus yang sudah terkunci', async () => {
        paApiMock.ringkasanPraKunci.mockResolvedValue(praKunci({
            status_siklus: 'LOCKED',
            pintu: { diharapkan: 400, tercetak: 250, kurang: 150 },
        }));
        paApiMock.statusPenjadwalanLatar.mockResolvedValue({
            tugas: tugas({ status: 'failed', galat: '150 belum terkirim.' }),
            ringkasan: { total: 400, terjadwal: 250, antre: 0, gagal: 0, belum: 150 },
        });
        paApiMock.ulangiPenjadwalanLatar.mockResolvedValue({ tugas: tugas({ status: 'queued' }) });

        const wrapper = pasang();
        await flushPromises();

        const lanjut = tombol(wrapper, 'Lanjutkan');
        expect(lanjut).toBeTruthy();
        expect(wrapper.text()).toContain('150');

        await lanjut.trigger('click');
        await flushPromises();
        await tombol(wrapper, 'Ya, Lanjutkan').trigger('click');
        await flushPromises();

        expect(paApiMock.ulangiPenjadwalanLatar).toHaveBeenCalledWith(77);
        expect(paApiMock.mulaiPenjadwalanLatar).not.toHaveBeenCalled();
        expect(paApiMock.kunciSiklus).not.toHaveBeenCalled();
        expect(paApiMock.jadwalkanPenilaian).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    /* Defect UAT 8 Okt: siklus LOCKED yang seluruh aksesnya siap memuat tombol "Selesai" yang
       MATI sesudah halaman dimuat ulang — siklusnya tidak bisa diselesaikan dari layar ini. */
    it('menghidupkan tombol Selesai pada siklus terkunci yang aksesnya sudah lengkap', async () => {
        paApiMock.ringkasanPraKunci.mockResolvedValue(praKunci({
            status_siklus: 'LOCKED',
            pintu: { diharapkan: 400, tercetak: 400, kurang: 0, antre: 0 },
        }));

        const wrapper = pasang();
        await flushPromises();

        const selesai = tombol(wrapper, 'Selesai');
        expect(selesai).toBeTruthy();
        expect(selesai.attributes('disabled')).toBeUndefined();

        await selesai.trigger('click');
        expect(wrapper.emitted('selesai')).toBeTruthy();
        expect(paApiMock.kunciSiklus).not.toHaveBeenCalled();
    });

    /* Penghalang yang sama dengan yang dijaga server. Layar yang menjanjikan boleh lalu
       ditolak server sama buruknya dengan sebaliknya. */
    it('mematikan tombol saat masih ada penghalang', async () => {
        paApiMock.ringkasanPraKunci.mockResolvedValue(praKunci({
            boleh_dikunci: false,
            penghalang: ['Masih ada 3 peserta berstatus TERTUNDA.'],
            peserta: { diikutkan: 120, dikecualikan: 2, dikecualikan_otomatis: 9, tertunda: 3 },
        }));

        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.text()).toContain('Masih ada 3 peserta berstatus TERTUNDA.');
        expect(tombolKunci(wrapper).attributes('disabled')).toBeDefined();
    });

    /* Ringkasannya gagal dimuat bukan berarti tombolnya boleh ditekan sambil menebak:
       membekukan siklus tidak bisa dibatalkan. */
    it('menahan penguncian ketika ringkasan pra-kunci gagal dimuat', async () => {
        paApiMock.ringkasanPraKunci.mockRejectedValue(new Error('jaringan putus'));

        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.text()).toContain('jaringan putus');
        expect(tombolKunci(wrapper).attributes('disabled')).toBeDefined();
    });
});

describe('PaStepPenugasan — alur bernomor', () => {
    beforeEach(() => {
        Object.values(paApiMock).forEach((fn) => fn.mockReset());
        paApiMock.kesehatanPenugasan.mockResolvedValue({ baris: [], total_peserta_kurang: 0 });
        paApiMock.progresPenugasan.mockResolvedValue({ selesai: true, total: 120, sudah: 120, belum: 0, penugasan: 400 });
        paApiMock.penugasanBermasalah.mockResolvedValue({ baris: [], total: 0 });
        paApiMock.ringkasanPraKunci.mockResolvedValue(praKunci());
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({ result: kesiapanSiap() });
        paApiMock.perubahanPeserta.mockResolvedValue(perubahan());
    });

    /*
     | UAT 13 Okt: satu daftar tugas di atas, rincian dilipat. Tiap masalah disebut SEKALI.
     |
     | Perubahan yang membuat uji ini merah: munculkan lagi kartu angka kesehatan atau panel
     | HC Learn terbuka di halaman, atau sebut penghalang di lebih dari satu tempat.
     */
    it('daftar tugas di atas, rincian dilipat, tanpa tombol "Bentuk Ulang"', async () => {
        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.find('[data-uji="daftar-tugas-judul"]').text()).toBe('Siap dikunci');
        expect(wrapper.findAll('.pa-alur-judul').map((h) => h.text())).toEqual([
            'Pembentukan penugasan',
            'Kunci & jadwalkan ke HC Learn',
        ]);
        expect(wrapper.find('[data-uji="perubahan-kosong"]').exists()).toBe(false);
        expect(wrapper.find('[data-uji="rincian-penugasan-isi"]').attributes('style')).toContain('display: none');
        expect(tombol(wrapper, 'Bentuk Ulang Penugasan')).toBeFalsy();

        await wrapper.find('[data-uji="rincian-penugasan"]').trigger('click');
        expect(wrapper.find('[data-uji="rincian-penugasan-isi"]').attributes('style') || '').not.toContain('display: none');
    });

    /* Jadwal HC Learn milik penilai yang dibatalkan yang belum terhapus: disebut dengan
       sebabnya, dan bisa dicoba lagi dari daftar tugas. */
    it('menyebut jadwal HC Learn yang belum terhapus dan mencoba menghapusnya lagi', async () => {
        paApiMock.statusPenjadwalanLatar.mockResolvedValue({
            tugas: null,
            ringkasan: null,
            jadwal_dibatalkan: { jumlah: 2, gagal: 1, contoh: 'Peserta sudah mengerjakan.' },
        });
        paApiMock.hapusJadwalDibatalkan.mockResolvedValue({ jadwal_dibatalkan: { jumlah: 2, gagal: 1, contoh: null } });
        const wrapper = pasang();
        await flushPromises();

        const baris = wrapper.find('[data-uji="tugas-jadwal-dibatalkan"]');
        expect(baris.text()).toContain('2 jadwal HC Learn milik penilai yang dibatalkan belum terhapus');
        expect(baris.text()).toContain('HC Learn menolak: Peserta sudah mengerjakan.');

        await wrapper.find('[data-uji="tugas-hapus-jadwal"]').trigger('click');
        await flushPromises();
        expect(paApiMock.hapusJadwalDibatalkan).toHaveBeenCalledWith(77);
        wrapper.unmount();
    });

    it('penilai yang ditolak HC Learn disebut SEKALI sebagai Wajib, dengan tombol Ganti penilai', async () => {
        paApiMock.kesiapanKaryawanHcLearn.mockResolvedValue({
            result: kesiapanSiap({ status: 'PERLU_TINDAKAN', baris: [{ ...barisResign(), menghalangi: true }] }),
        });
        const wrapper = pasang();
        await flushPromises();

        const judul = wrapper.find('[data-uji="daftar-tugas-judul"]').text();
        expect(judul).toBe('Belum bisa dikunci — 1 hal wajib dibereskan');
        const baris = wrapper.find('[data-uji="tugas-hcl-A1"]');
        expect(baris.text()).toContain('Frans Bachtiar (A1) tidak bisa dipakai di HC Learn');
        expect(baris.text()).toContain('Masih menilai: Samuel (Bawahan)');
        expect(wrapper.find('.pa-kunci-panel').text()).not.toContain('Frans Bachtiar');
        expect(tombolKunci(wrapper).attributes('disabled')).toBeDefined();
    });

    /* Penilai yang resign sesudah dikunci memegang tugas yang tidak akan pernah diisi. */
    it('menyebut penilai yang sudah tidak aktif di HRIS sesudah dikunci', async () => {
        paApiMock.ringkasanPraKunci.mockResolvedValue(praKunci({ status_siklus: 'LOCKED' }));
        paApiMock.cariRevisiPeserta.mockResolvedValue({ baris: [{ kode_karyawan: 'R1' }, { kode_karyawan: 'R2' }] });
        const wrapper = pasang();
        await flushPromises();

        expect(paApiMock.cariRevisiPeserta).toHaveBeenCalledWith(77, { sumber: 'penilai', status: 'TIDAK_AKTIF' });
        const alert = wrapper.findComponent('[data-uji="penilai-tidak-aktif"]');
        expect(alert.attributes('title')).toBe('2 penilai sudah tidak aktif di HRIS masih punya tugas menilai');

        /* Tombolnya membuka dialog revisi dengan tindakan Lepas penilai, mencari yang tidak aktif. */
        await wrapper.find('[data-uji="buka-lepas-tidak-aktif"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-uji="rvm-tindakan-LEPAS_PENILAI"]').attributes('aria-checked')).toBe('true');
        wrapper.unmount();
    });

    it('perubahan yang belum diterapkan mengarahkan kembali ke Langkah 4', async () => {
        paApiMock.perubahanPeserta.mockResolvedValue(perubahan({ belum_diterapkan: { karyawan_baru: 2, level_berubah: 1 } }));
        const wrapper = pasang();
        await flushPromises();

        expect(wrapper.find('[data-uji="perubahan-belum-diterapkan"]').text()).toContain('2 karyawan baru dan 1 peserta berubah Level PA belum diterapkan');
        await tombol(wrapper, 'Buka Langkah 4').trigger('click');
        expect(wrapper.emitted('kembali')).toBeTruthy();
    });
});

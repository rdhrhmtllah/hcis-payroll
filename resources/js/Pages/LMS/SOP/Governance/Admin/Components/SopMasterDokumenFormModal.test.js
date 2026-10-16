import { describe, expect, it } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { HcisButton } from '@/components/ui';
import SopMasterDokumenFormModal from './SopMasterDokumenFormModal.vue';

/**
 * Pintu KELAHIRAN dokumen SOP: wizard empat langkah di Master Dokumen.
 *
 * Yang dijaga berkas ini: NOMOR RESMI WAJIB UTUH (keputusan produk 10 Sep 2026),
 * dan penolakannya harus punya JALAN KELUAR.
 *
 * Jenis dan APQC adalah segmen pembentuk Nomor_SOP_Resmi, dan
 * `SopNomorDokumen::rakit()` menolak merakit nomor yang ada segmen bolongnya.
 * Dokumen yang lahir tanpa salah satunya tak akan pernah bernomor, dan server
 * tak akan memberi tahu siapa pun — `segarkanNomorResmi()` sengaja membiarkan
 * kolomnya null. Layar adalah satu-satunya tempat pertanyaannya bisa diajukan.
 *
 * Buktinya nyata: dokumen KUIS #30771 lahir tanpa Jenis pada 10 Sep 2026 lewat
 * layar ini, ketika kewajibannya sempat diturunkan dari panjang daftar opsi.
 *
 * Tapi menolak saja tidak cukup. Tombol mati tanpa keterangan adalah jalan
 * buntu bisu — persis yang membuat tipe KUIS tak pernah bisa dibuat siapa pun
 * sebelum ini. Maka setiap penolakan yang sebabnya ada di MASTER, bukan di
 * layar ini, wajib menyebut layar mana yang harus dibereskan.
 */

/* HcisDialog membungkus seluruh isi wizard di slot. Stub bawaan shallowMount
   tidak merender slot, jadi tanpa stub yang merendernya, berkas ini akan
   "hijau" karena tak menemukan apa-apa — bukan karena wizardnya benar. */
const DialogStub = {
    name: 'HcisDialog',
    template: '<div><slot /><slot name="footer" /></div>',
};

/* Sejak refactor UI tiap langkah dibungkus HcisCard. Tanpa stub yang merender
   slotnya, peringatan pemetaan di dalam kartu tak pernah sampai ke DOM uji —
   uji "jalan keluar" merah bukan karena peringatannya hilang dari layar. */
const CardStub = {
    name: 'HcisCard',
    template: '<div><slot /><slot name="actions" /></div>',
};

const JENIS = [{ Id: 'j1', Nama_Jenis: 'Prosedur', Kode_Jenis_Dokumen: 'SOP', Level_Hirarki: 3 }];
const APQC = [{ Id: 'a1', Kode_Proses: '01', Nama_Proses: 'Kembangkan Visi' }];

const ITEM_LENGKAP = {
    Id_SOP_Dokumen: 'hash-dok',
    Judul: 'Prosedur Uji',
    Tipe: 'DOKUMEN',
    Jenis_Dokumen_Id: 'j1',
    APQC_Id: 'a1',
    Entitas_Pemilik_Id: 'e1',
    Departemen_Id: 'd1',
    Entitas_Lingkup_Id: 'e1',
    No_Dokumen: 3,
};

const pasang = (props = {}) =>
    shallowMount(SopMasterDokumenFormModal, {
        props: {
            show: true,
            item: ITEM_LENGKAP,
            tipeOpsi: ['DOKUMEN', 'FORM', 'VIDEO', 'KUIS'],
            jenisOpsi: JENIS,
            apqcOpsi: APQC,
            entitasOpsi: [{ Id: 'e1', Kode_Entitas: 'EHG', Nama_Entitas: 'Holding' }],
            departemenOpsi: [{ Id: 'd1', Kode_Departemen: 'IT', Nama_Departemen: 'IT' }],
            saranNoDokumen: 3,
            segmenKosong: [],
            ...props,
        },
        global: { stubs: { HcisDialog: DialogStub, HcisCard: CardStub } },
    });

/** Tombol wizard dicari lewat LABEL-nya — satu-satunya penanda yang stabil. */
const tombol = (w, label) =>
    w.findAllComponents(HcisButton).find((b) => b.props('label') === label);

const lanjut = async (w, kali) => {
    for (let i = 0; i < kali; i += 1) {
        const b = tombol(w, 'Lanjut');
        expect(b, `tombol "Lanjut" hilang di langkah ke-${i + 1}`).toBeTruthy();
        expect(b.props('disabled'), `tombol "Lanjut" terkunci di langkah ke-${i + 1}`).toBe(false);
        b.vm.$emit('click');
        await w.vm.$nextTick();
    }
};

describe('SopMasterDokumenFormModal — nomor resmi wajib utuh', () => {
    it('identitas lengkap bisa disimpan', async () => {
        const w = pasang();

        await lanjut(w, 3);

        const simpan = tombol(w, 'Simpan Perubahan Identitas');
        expect(simpan, 'tombol simpan tak terpasang di langkah terakhir').toBeTruthy();
        expect(simpan.props('disabled')).toBe(false);

        simpan.vm.$emit('click');
        await w.vm.$nextTick();

        expect(w.emitted('submit')).toBeTruthy();
    });

    it('Jenis belum dipilih menahan langkah pertama', () => {
        const w = pasang({ item: { ...ITEM_LENGKAP, Jenis_Dokumen_Id: '' } });

        expect(tombol(w, 'Lanjut').props('disabled')).toBe(true);
    });

    it('APQC belum dipilih menahan langkah pertama', () => {
        const w = pasang({ item: { ...ITEM_LENGKAP, APQC_Id: '' } });

        expect(tombol(w, 'Lanjut').props('disabled')).toBe(true);
    });

    it('Judul kosong menahan langkah pertama', () => {
        const w = pasang({ item: { ...ITEM_LENGKAP, Judul: '   ' } });

        expect(tombol(w, 'Lanjut').props('disabled')).toBe(true);
    });

    /* Inilah baris yang meloloskan dokumen KUIS #30771. Segmen bolong yang
       lolos Langkah 1 & 2 — entitas atau departemen yang SUDAH DIPILIH tetapi
       kode cetaknya kosong di master — harus tetap tertahan di Langkah 3
       (Penomoran): tombol Lanjut terkunci sehingga tombol simpan tak tercapai. */
    it('segmen nomor yang masih bolong menahan langkah penomoran', async () => {
        const w = pasang({ segmenKosong: ['Departemen pemilik'] });

        await lanjut(w, 2);

        expect(tombol(w, 'Lanjut').props('disabled')).toBe(true);
        expect(tombol(w, 'Simpan Perubahan Identitas'), 'tombol simpan tercapai meski segmen bolong').toBeFalsy();
    });

    it('segmen lengkap tetap bisa sampai ke tombol simpan', async () => {
        const w = pasang();

        await lanjut(w, 3);

        expect(tombol(w, 'Simpan Perubahan Identitas').props('disabled')).toBe(false);
    });

    it('No. Dokumen kosong menahan langkah penomoran', async () => {
        const w = pasang({ item: { ...ITEM_LENGKAP, No_Dokumen: '' }, saranNoDokumen: null });

        await lanjut(w, 2);

        expect(tombol(w, 'Lanjut').props('disabled')).toBe(true);
    });
});

describe('SopMasterDokumenFormModal — penolakan wajib punya jalan keluar', () => {
    const kuisTanpaJenis = () =>
        pasang({
            item: {
                ...ITEM_LENGKAP,
                Judul: 'Kuis Pemahaman K3',
                Tipe: 'KUIS',
                Jenis_Dokumen_Id: '',
            },
            /* Keadaan Web_HRIS pada 10 Sep 2026: KUIS sudah dipetakan ke APQC
               (1 proses) tetapi belum ke satu pun Jenis Dokumen. */
            jenisOpsi: [],
            apqcOpsi: APQC,
        });

    it('tipe tanpa pemetaan Jenis tetap TERTAHAN', () => {
        expect(tombol(kuisTanpaJenis(), 'Lanjut').props('disabled')).toBe(true);
    });

    it('menyebut apa yang kurang DAN layar master mana yang harus dibereskan', () => {
        const w = kuisTanpaJenis();
        const alert = w.find('[data-uji="pemetaan-kurang"]');

        expect(alert.exists(), 'peringatan pemetaan tak dirender').toBe(true);

        const pesan = alert.attributes('description') || '';
        expect(pesan).toContain('KUIS');
        expect(pesan).toContain('Jenis Dokumen');
        expect(pesan).toContain('Master Jenis Dokumen');
        /* APQC-nya SUDAH dipetakan — menyebutnya akan mengirim admin
           membereskan layar yang tak bermasalah. */
        expect(pesan).not.toContain('Master APQC');
    });

    it('menyebut KEDUA layar bila dua-duanya belum dipetakan', () => {
        const w = pasang({
            item: { ...ITEM_LENGKAP, Tipe: 'KUIS', Jenis_Dokumen_Id: '', APQC_Id: '' },
            jenisOpsi: [],
            apqcOpsi: [],
        });

        const pesan = w.find('[data-uji="pemetaan-kurang"]').attributes('description') || '';
        expect(pesan).toContain('Master Jenis Dokumen');
        expect(pesan).toContain('Master APQC');
    });

    /* UJI PENOLAKAN. Peringatan yang selalu menyala tak memberi tahu apa pun,
       dan berkas ini akan tetap hijau kalau `v-if`-nya dihapus. */
    it('TIDAK muncul ketika pemetaan masternya sudah ada', () => {
        expect(pasang().find('[data-uji="pemetaan-kurang"]').exists()).toBe(false);
    });

    it('TIDAK muncul ketika admin sekadar belum memilih', () => {
        const w = pasang({ item: { ...ITEM_LENGKAP, Jenis_Dokumen_Id: '' } });

        expect(w.find('[data-uji="pemetaan-kurang"]').exists()).toBe(false);
    });
});

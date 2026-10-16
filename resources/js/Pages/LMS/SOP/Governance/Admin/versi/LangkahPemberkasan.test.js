import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import LangkahPemberkasan from './LangkahPemberkasan.vue';

/**
 * Auto-isi HARUS tahu diri.
 *
 * Auto-isi yang menimpa ketikan admin adalah persis perilaku yang membuat
 * formulir terasa melawan pemakainya. Uji di berkas ini mengunci batasnya:
 * mengikuti selama belum disentuh, berhenti begitu disentuh, dan bisa diminta
 * mengikuti lagi.
 */
const stub = (nama) => ({ name: nama, template: '<div />' });

const pasang = (nilai = {}, props = {}) => mount(LangkahPemberkasan, {
    props: {
        modelValue: {
            Tanggal_Berlaku: '',
            Tanggal_Kadaluarsa: '',
            Tanggal_Review_Berikutnya: '',
            Id_Master_Konten: null,
            Konten_Nama: '',
            ...nilai,
        },
        masaBerlakuBulan: 24,
        siklusReviewBulan: 12,
        ...props,
    },
    global: {
        stubs: {
            HcisCard: { template: '<div><slot name="actions" /><slot /></div>' },
            HcisBadge: stub('HcisBadge'),
            HcisButton: stub('HcisButton'),
            HcisField: stub('HcisField'),
            HcisUpload: stub('HcisUpload'),
            HcisIcon: stub('HcisIcon'),
            HcisDatePicker: stub('HcisDatePicker'),
            SopKontenTerpakai: stub('SopKontenTerpakai'),
            KartuLampiranForm: {
                name: 'KartuLampiranForm',
                props: ['modelValue', 'terkunci'],
                emits: ['update:modelValue', 'tambah'],
                template: '<div />',
            },
        },
    },
});

const terakhir = (w) => w.emitted('update:modelValue').at(-1)[0];

/* Komponen ini TERKENDALI: modelValue-nya milik induk. Tanpa menulis balik
   nilai yang diemit, uji akan menguji komponen yang setiap perubahannya
   dilupakan — dan itu bukan keadaan yang pernah terjadi di aplikasi. */
const ubahLalu = async (w, kunci, nilai) => {
    await w.vm.ubah(kunci, nilai);
    await w.setProps({ modelValue: terakhir(w) });
};

describe('LangkahPemberkasan — turunan tanggal', () => {
    /**
     * PINDAHAN dari SopPanelProfilTipe.test.js.
     *
     * Berkas itu menguji `pakaiPengesahan`, `pakaiNomorResmi`, dan "tanggal
     * berlaku ada untuk semua tipe" pada SopDokumenVersiModal — komponen yang
     * TIDAK LAGI memiliki satu pun di antaranya. Ketiga aturannya masih
     * berlaku, hanya rumahnya berpindah:
     *
     *   pengesahan   -> SopVersiWizard.test.js (it.each per tipe)
     *   nomor resmi  -> SopMasterDokumenNomor.test.js
     *   tanggal berlaku -> BELUM dijaga di mana pun; itulah yang dipindahkan ke sini.
     *
     * Kenapa aturannya masih benar: `Tanggal_Berlaku` bukan bagian dari peta
     * profil tipe sama sekali. PROFIL di SopTipeItem hanya mengatur empat blok
     * (nomor_resmi, pengesahan, berkas, ujian); tanggal adalah field versi, dan
     * LangkahPemberkasan dirender lewat `v-else` tanpa gerbang tipe. KUIS pun
     * melewatinya — komponennya menyesuaikan diri lewat prop `isKuis`, bukan
     * dengan menghilangkan tanggalnya.
     */
    /* Pemilih tanggal ada TIGA di langkah ini (berlaku, kadaluarsa, review),
       jadi menghitung jumlahnya saja tak cukup: menyembunyikan yang satu tetap
       menyisakan dua, dan penjaganya lolos hijau. Terbukti — sabotase pertama
       memang lolos. Yang dicari pemilih yang BENAR-BENAR terikat nilai
       Tanggal_Berlaku, dikenali lewat nilai penanda yang unik. */
    const pemilihTanggalBerlaku = (w, penanda) => w
        .findAllComponents({ name: 'HcisDatePicker' })
        /* Dibaca dari ATRIBUT, bukan props(): stub bersama di berkas ini tak
           mendeklarasikan prop apa pun, sehingga model-value jatuh ke atribut
           dan props('modelValue') selalu undefined — penjaga yang membacanya
           akan gagal untuk alasan yang salah. */
        .find((d) => d.attributes('model-value') === penanda);

    it('tanggal berlaku tetap ada untuk KUIS, bukan hanya tipe berkas', () => {
        const penanda = '2026-03-15';
        const w = pasang({ Tanggal_Berlaku: penanda }, { isKuis: true });

        expect(
            pemilihTanggalBerlaku(w, penanda),
            'KUIS kehilangan pemilih Tanggal_Berlaku',
        ).toBeTruthy();
    });

    it('tanggal berlaku juga ada untuk tipe berkas biasa', () => {
        /* Sisi pembanding: tanpa ini, uji di atas tetap hijau seandainya
           tanggalnya hilang untuk SEMUA tipe sekaligus. */
        const penanda = '2026-03-15';
        const w = pasang({ Tanggal_Berlaku: penanda }, { isKuis: false });

        expect(pemilihTanggalBerlaku(w, penanda)).toBeTruthy();
    });

    it('mengisi kadaluarsa dan jadwal review saat tanggal berlaku diisi', async () => {
        const w = pasang();
        await w.vm.ubah('Tanggal_Berlaku', '2026-03-15');

        expect(terakhir(w).Tanggal_Kadaluarsa).toBe('2028-03-15');
        expect(terakhir(w).Tanggal_Review_Berikutnya).toBe('2027-03-15');
    });

    it('memakai angka bulan dari master, bukan angka hafalan', async () => {
        const w = pasang({}, { masaBerlakuBulan: 36, siklusReviewBulan: 18 });
        await w.vm.ubah('Tanggal_Berlaku', '2026-03-15');

        expect(terakhir(w).Tanggal_Kadaluarsa).toBe('2029-03-15');
        expect(terakhir(w).Tanggal_Review_Berikutnya).toBe('2027-09-15');
    });

    /**
     * Dijepit di ujung bulan, sama seperti addMonthsNoOverflow di server.
     * Kalau kedua sisi berbeda, tanggal yang ditawarkan di sini tak akan sama
     * dengan yang akhirnya tersimpan.
     */
    it('tidak meluberkan akhir bulan', async () => {
        const w = pasang({}, { masaBerlakuBulan: 1, siklusReviewBulan: 1 });
        await w.vm.ubah('Tanggal_Berlaku', '2026-01-31');

        expect(terakhir(w).Tanggal_Kadaluarsa).toBe('2026-02-28');
    });

    it('berhenti mengikuti setelah admin mengetik kadaluarsanya sendiri', async () => {
        const w = pasang({ Tanggal_Berlaku: '2026-03-15' });
        await ubahLalu(w, 'Tanggal_Kadaluarsa', '2030-01-01');
        await ubahLalu(w, 'Tanggal_Berlaku', '2026-06-01');

        const t = terakhir(w);
        expect(t.Tanggal_Kadaluarsa).toBe('2030-01-01');
        // Jadwal review belum disentuh, jadi ia tetap mengikuti.
        expect(t.Tanggal_Review_Berikutnya).toBe('2027-06-01');
    });

    it('"ikuti baku lagi" mengembalikan turunannya', async () => {
        const w = pasang({ Tanggal_Berlaku: '2026-03-15' });
        await ubahLalu(w, 'Tanggal_Kadaluarsa', '2030-01-01');
        await w.vm.ikutiBakuLagi('Tanggal_Kadaluarsa');

        expect(terakhir(w).Tanggal_Kadaluarsa).toBe('2028-03-15');
    });

    /**
     * Versi tersimpan yang sudah punya kadaluarsa TIDAK ditimpa.
     *
     * Tanggalnya datang dari keputusan yang sudah diambil; mengubah tanggal
     * berlaku tak boleh diam-diam membatalkannya.
     */
    it('menghormati tanggal yang sudah tersimpan pada versi lama', async () => {
        const w = pasang({ Tanggal_Berlaku: '2026-03-15', Tanggal_Kadaluarsa: '2029-12-31' });
        await w.vm.ubah('Tanggal_Berlaku', '2026-06-01');

        expect(terakhir(w).Tanggal_Kadaluarsa).toBe('2029-12-31');
    });

    it('tak mengarang turunan saat tanggal berlaku dikosongkan', async () => {
        const w = pasang({ Tanggal_Berlaku: '2026-03-15' });
        await w.vm.ubah('Tanggal_Berlaku', null);

/* Kosong itu '' , bukan null — dan keduanya sama saja di sini:
           nilaiBerbeda() di server memperlakukan '' dan null sebagai hal yang
           sama, jadi menuntut salah satunya menguji bentuk, bukan makna. */
        expect(terakhir(w).Tanggal_Kadaluarsa).toBeFalsy();
        expect(terakhir(w).Tanggal_Review_Berikutnya).toBeFalsy();
    });
});

describe('LangkahPemberkasan — urutan tanggal', () => {
    it('menyebut kadaluarsa yang mendahului tanggal berlaku', () => {
        const w = pasang({ Tanggal_Berlaku: '2026-03-15', Tanggal_Kadaluarsa: '2026-01-01' });

        expect(w.vm.galatUrutan).toContain('tanggal mulai berlaku');
    });

    it('menyebut jadwal review yang melewati kadaluarsa', () => {
        const w = pasang({
            Tanggal_Berlaku: '2026-03-15',
            Tanggal_Kadaluarsa: '2028-03-15',
            Tanggal_Review_Berikutnya: '2029-01-01',
        });

        expect(w.vm.galatUrutan).toContain('tanggal kedaluwarsa');
    });

    it('diam saat urutannya sah', () => {
        const w = pasang({
            Tanggal_Berlaku: '2026-03-15',
            Tanggal_Review_Berikutnya: '2027-03-15',
            Tanggal_Kadaluarsa: '2028-03-15',
        });

        expect(w.vm.galatUrutan).toBe('');
    });
});

describe('LangkahPemberkasan — form kosong dokumen FORM', () => {
    const kartu = (w) => w.findComponent({ name: 'KartuLampiranForm' });

    it('hanya dirender bila tipenya memakai lampiran', () => {
        expect(kartu(pasang({}, { pakaiLampiran: false })).exists()).toBe(false);
        expect(kartu(pasang({}, { pakaiLampiran: true })).exists()).toBe(true);
    });

    it('meneruskan daftar dan mengikuti izin field Lampiran', () => {
        const daftar = [{ Id_Master_Konten: 'aaa', Judul: 'Form A' }];
        const w = pasang({ Lampiran: daftar }, {
            pakaiLampiran: true,
            bolehUbah: (f) => f !== 'Lampiran',
        });
        expect(kartu(w).props('modelValue')).toEqual(daftar);
        expect(kartu(w).props('terkunci')).toBe(true);
    });

    it('perubahan daftar ditulis ke model dan tambah meminta pemilih berkas', async () => {
        const w = pasang({ Lampiran: [] }, { pakaiLampiran: true });
        await kartu(w).vm.$emit('update:modelValue', [{ Id_Master_Konten: 'bbb' }]);
        expect(terakhir(w).Lampiran).toEqual([{ Id_Master_Konten: 'bbb' }]);

        await kartu(w).vm.$emit('tambah');
        expect(w.emitted('pilih-lampiran')).toHaveLength(1);
    });
});

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PaLabelBagianDialog from '../PaLabelBagianDialog.vue'

/**
 * Bijeksi dijaga SAMBIL diisi, bukan baru divonis saat menyimpan.
 *
 * Admin yang baru tahu labelnya rangkap setelah menekan simpan harus menelusuri 21 baris
 * untuk menemukan yang mana. Kompetensi yang sudah terpakai karena itu hilang dari pilihan
 * berikutnya.
 *
 * Cara membuat berkas ini MERAH: ubah `kunciBagian` menjadi hanya `b.id_jenis_soal`, atau
 * kembalikan seluruh kompetensi dari `pilihanUntuk()`.
 */
const props = {
    idLevel: 5,
    raterCategoryId: null,
    idMasterUjian: 'SIM-X',
    namaUjian: 'Simulasi X',

    /* Dua bagian yang BANK SOALNYA SAMA (77) tetapi alat tesnya berbeda. Inilah bentuk yang
       dokumentasi HC Learn nyatakan mungkin, dan yang menabrak kunci berfield-tunggal. */
    bagian: [
        {
            id_jenis_indikator: '100',
            id_jenis_soal: '77',
            nama_alat_tes: 'TIU',
            nama: 'Verbal',
            urutan_alat_tes: 0,
            urutan_bagian: 0,
        },
        {
            id_jenis_indikator: '200',
            id_jenis_soal: '77',
            nama_alat_tes: 'TKD',
            nama: 'Verbal',
            urutan_alat_tes: 1,
            urutan_bagian: 0,
        },
    ],
    kompetensiWajib: [
        {
            id: 11,
            kode: 'K01',
            nama: 'Analytical Thinking',
            dimensi_kode: 'MANAJERIAL',
            dimensi_nama: 'Kompetensi Manajerial',
        },
        {
            id: 12,
            kode: 'K02',
            nama: 'Problem Solving',
            dimensi_kode: 'MANAJERIAL',
            dimensi_nama: 'Kompetensi Manajerial',
        },
    ],
    labelAwal: {},
}

describe('PaLabelBagianDialog', () => {
    it('membedakan dua bagian yang bank soalnya sama tetapi alat tesnya berbeda', async () => {
        const w = mount(PaLabelBagianDialog, { props })

        w.vm.setLabel('100|77', 11)
        w.vm.setLabel('200|77', 12)
        await w.vm.$nextTick()

        expect(w.vm.label).toEqual({ '100|77': 11, '200|77': 12 })
    })

    it('mematikan tombol simpan selama masih ada bagian tanpa label', () => {
        const w = mount(PaLabelBagianDialog, { props })

        expect(w.get('[data-uji="simpan-label"]').attributes('disabled')).toBeDefined()
    })

    it('menyalakan tombol simpan ketika seluruh bagian berlabel', async () => {
        const w = mount(PaLabelBagianDialog, { props })

        w.vm.setLabel('100|77', 11)
        w.vm.setLabel('200|77', 12)
        await w.vm.$nextTick()

        expect(w.get('[data-uji="simpan-label"]').attributes('disabled')).toBeUndefined()
    })

    it('membuang kompetensi yang sudah terpakai dari pilihan bagian lain', async () => {
        const w = mount(PaLabelBagianDialog, { props })

        w.vm.setLabel('100|77', 11)
        await w.vm.$nextTick()

        expect(w.vm.pilihanUntuk('200|77').map((k) => k.id)).toEqual([12])

        // Bagian yang MEMEGANG label itu tetap melihatnya, kalau tidak pilihannya sendiri
        // menghilang dari daftar dan kotaknya tampak kosong padahal sudah terisi.
        expect(w.vm.pilihanUntuk('100|77').map((k) => k.id)).toEqual([11, 12])
    })

    it('memancarkan label berkunci pasangan alat tes dan bank soal', async () => {
        const w = mount(PaLabelBagianDialog, { props })

        w.vm.setLabel('100|77', 11)
        w.vm.setLabel('200|77', 12)
        await w.vm.$nextTick()
        await w.get('[data-uji="simpan-label"]').trigger('click')

        expect(w.emitted('simpan')[0][0]).toEqual({ '100|77': 11, '200|77': 12 })
    })

    it('mengelompokkan bagian menurut alat tesnya', () => {
        // Dua alat tes yang sama-sama punya bagian bernama "Verbal". Tanpa judul kelompok,
        // admin tidak punya cara membedakan barisnya.
        const w = mount(PaLabelBagianDialog, { props })
        const teks = w.text()

        expect(teks).toContain('TIU')
        expect(teks).toContain('TKD')
    })

    it('menghitung kemajuan pelabelan', async () => {
        const w = mount(PaLabelBagianDialog, { props })

        expect(w.get('[data-uji="hitung-label"]').text()).toContain('0 dari 2')

        w.vm.setLabel('100|77', 11)
        await w.vm.$nextTick()

        expect(w.get('[data-uji="hitung-label"]').text()).toContain('1 dari 2')
    })

    it('mendukung pencocokan otomatis (auto-match) berdasarkan kesesuaian nama/kode', async () => {
        const customProps = {
            ...props,
            bagian: [
                {
                    id_jenis_indikator: '100',
                    id_jenis_soal: '77',
                    nama_alat_tes: 'TIU',
                    nama: 'Analytical Thinking',
                    urutan_alat_tes: 0,
                    urutan_bagian: 0,
                },
                {
                    id_jenis_indikator: '200',
                    id_jenis_soal: '78',
                    nama_alat_tes: 'TKD',
                    nama: 'Problem Solving',
                    urutan_alat_tes: 1,
                    urutan_bagian: 0,
                },
            ],
        }

        const w = mount(PaLabelBagianDialog, { props: customProps })

        expect(w.vm.lengkap).toBe(false)

        w.vm.cocokkanOtomatis()
        await w.vm.$nextTick()

        expect(w.vm.label).toEqual({ '100|77': 11, '200|78': 12 })
        expect(w.vm.lengkap).toBe(true)
    })

    it('dapat mengosongkan seluruh pemetaan dengan resetSemua', async () => {
        const w = mount(PaLabelBagianDialog, {
            props: {
                ...props,
                labelAwal: { '100|77': 11, '200|77': 12 },
            },
        })

        expect(w.vm.lengkap).toBe(true)

        w.vm.resetSemua()
        await w.vm.$nextTick()

        expect(w.vm.label).toEqual({})
        expect(w.vm.lengkap).toBe(false)
    })
})

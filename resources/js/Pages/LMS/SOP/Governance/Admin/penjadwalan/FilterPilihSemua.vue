<template>
    <el-select
        :model-value="modelValue"
        multiple
        filterable
        clearable
        collapse-tags
        collapse-tags-tooltip
        :max-collapse-tags="maxCollapseTags"
        :placeholder="placeholder"
        :disabled="disabled"
        :filter-method="saring"
        style="width: 100%"
        @update:model-value="pilih"
        @visible-change="saatBuka"
    >
        <template v-if="daftar.length" #header>
            <el-checkbox
                class="fps-semua"
                :model-value="semuaTerpilih"
                :indeterminate="sebagianTerpilih"
                @change="togglePilihSemua"
            >
                {{ labelSemua }}
            </el-checkbox>
        </template>
        <el-option v-for="o in tampil" :key="o" :label="o" :value="o" />
    </el-select>
</template>

<script>
/**
 * Saringan multi-pilih dengan baris "Pilih semua" di kepala dropdown.
 *
 * Dua hal yang membuatnya tidak sesepele kelihatannya:
 *
 * 1. Saat admin mengetik, "Pilih semua" HARUS berarti "semua hasil yang sedang
 *    terlihat", bukan seluruh daftar — kalau tidak, mengetik "supervisor" lalu
 *    menekan pilih-semua diam-diam menarik 33 jabatan. Karena itu penyaringan
 *    diambil alih lewat :filter-method dan daftar tampilnya dihitung sendiri.
 * 2. Nilai yang sudah terpilih tapi tidak ada di daftar opsi (opsi basi dari
 *    data organisasi yang berubah) tidak boleh ikut terbuang saat pilih-semua
 *    dinyalakan/dimatikan; membuangnya akan mengubah target tanpa diminta.
 */
export default {
    name: 'FilterPilihSemua',
    props: {
        modelValue: { type: Array, default: () => [] },
        opsi: { type: Array, default: () => [] },
        placeholder: { type: String, default: '' },
        maxCollapseTags: { type: Number, default: 2 },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update:modelValue', 'change'],
    data() {
        return { kueri: '' };
    },
    computed: {
        daftar() {
            return this.opsi || [];
        },
        tampil() {
            const q = this.kueri.trim().toLowerCase();
            if (!q) return this.daftar;

            return this.daftar.filter((o) => String(o).toLowerCase().includes(q));
        },
        terpilih() {
            return new Set(this.modelValue || []);
        },
        jumlahTampilTerpilih() {
            return this.tampil.filter((o) => this.terpilih.has(o)).length;
        },
        semuaTerpilih() {
            return this.tampil.length > 0 && this.jumlahTampilTerpilih === this.tampil.length;
        },
        sebagianTerpilih() {
            return this.jumlahTampilTerpilih > 0 && !this.semuaTerpilih;
        },
        labelSemua() {
            const n = this.tampil.length;

            return this.kueri.trim() ? `Pilih semua hasil (${n})` : `Pilih semua (${n})`;
        },
    },
    methods: {
        /** :filter-method mematikan penyaringan bawaan — daftar tampil jadi urusan kita. */
        saring(kueri) {
            this.kueri = kueri || '';
        },
        saatBuka(terbuka) {
            if (!terbuka) this.kueri = '';
        },
        pilih(nilai) {
            this.$emit('update:modelValue', nilai);
            this.$emit('change', nilai);
        },
        togglePilihSemua(centang) {
            const sekarang = this.modelValue || [];
            const dalamDaftar = new Set(this.daftar);
            // Nilai di luar daftar opsi dipertahankan apa adanya (lihat catatan kelas).
            const luar = sekarang.filter((o) => !dalamDaftar.has(o));

            let hasil;
            if (centang) {
                const gabung = new Set([...sekarang, ...this.tampil]);
                // Diurutkan ulang mengikuti urutan opsi agar tag tidak melompat-lompat.
                hasil = [...this.daftar.filter((o) => gabung.has(o)), ...luar];
            } else {
                const buang = new Set(this.tampil);
                hasil = sekarang.filter((o) => !buang.has(o));
            }

            this.pilih(hasil);
        },
    },
};
</script>

<style scoped>
.fps-semua {
    width: 100%;
    height: auto;
    padding: 0.1rem 0;
    font-weight: 600;
}
</style>

<template>
    <div class="pkd">
        <div class="pkd-kartu">
            <button
                v-for="b in baris"
                :key="b.Kelompok"
                type="button"
                class="pkd-item"
                :class="[`is-${b.Kelompok.toLowerCase()}`, { on: dipilih === b.Kelompok }]"
                :title="b.Keterangan"
                @click="pilih(b.Kelompok)"
            >
                <span class="pkd-nama">{{ b.Nama }}</span>
                <span class="pkd-angka">{{ angka(b.Jumlah) }}</span>
                <span class="pkd-persen">{{ b.Persen }}%</span>
                <span class="pkd-ket">{{ b.Keterangan }}</span>
            </button>
        </div>

        <!-- Batang proporsi. Ketiganya di SATU batang, bukan tiga batang
             terpisah: yang ingin dibaca adalah perbandingannya, dan tiga batang
             yang masing-masing penuh 100% justru menyembunyikan perbandingan
             itu. -->
        <div class="pkd-bar" :aria-label="`Komposisi ${angka(total)} dokumen`">
            <span
                v-for="b in baris"
                :key="b.Kelompok"
                class="pkd-seg"
                :class="`is-${b.Kelompok.toLowerCase()}`"
                :style="{ width: `${lebar(b)}%` }"
                :title="`${b.Nama}: ${angka(b.Jumlah)} (${b.Persen}%)`"
            ></span>
        </div>

        <p class="pkd-total">
            Total <strong>{{ angka(total) }}</strong> dokumen aktif.
            Ketiga kelompok saling lepas dan menjumlah tepat ke total ini.
        </p>
    </div>
</template>

<script>
import { angka } from '../agregat.js';

/**
 * Komposisi tiga kelompok dokumen.
 *
 * Kelompoknya datang dari server sudah bernama dan berketerangan — layar tak
 * menyimpan daftar kelompok sendiri. Menambah kelompok keempat suatu hari
 * cukup mengubah service; panel ini ikut tanpa disunting.
 */
export default {
    name: 'PanelKomposisiDokumen',
    props: {
        komposisi: { type: Object, default: () => ({ Total: 0, Baris: [] }) },
        dipilih: { type: String, default: null },
    },
    emits: ['pilih'],
    computed: {
        baris() {
            return this.komposisi?.Baris || [];
        },
        total() {
            return this.komposisi?.Total || 0;
        },
    },
    methods: {
        angka,
        /* Lebar dihitung dari JUMLAH, bukan dari Persen yang sudah dibulatkan
           server: tiga persen yang masing-masing dibulatkan bisa berjumlah
           99,9% atau 100,1% dan menyisakan celah tipis di ujung batang. */
        lebar(b) {
            return this.total > 0 ? (b.Jumlah * 100) / this.total : 0;
        },
        pilih(k) {
            this.$emit('pilih', this.dipilih === k ? null : k);
        },
    },
};
</script>

<style scoped>
.pkd {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pkd-kartu {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
    gap: 0.75rem;
}

.pkd-item {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.125rem 0.5rem;
    padding: 0.875rem 1rem;
    text-align: left;
    border: 1px solid var(--hcis-border);
    border-left: 4px solid var(--pkd-c, var(--hcis-border-strong));
    border-radius: var(--hcis-radius-lg);
    background: var(--hcis-surface);
    transition: border-color 0.16s ease, box-shadow 0.16s ease;
}

.pkd-item:hover,
.pkd-item.on {
    border-color: var(--pkd-c);
    box-shadow: var(--hcis-shadow-card);
}

.pkd-item.is-fungsional { --pkd-c: var(--hcis-brand); }
.pkd-item.is-publik { --pkd-c: var(--hcis-success-500); }
.pkd-item.is-belum_beredar { --pkd-c: var(--hcis-amber-500); }

.pkd-nama {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--hcis-text-strong);
}

.pkd-angka {
    grid-row: 1 / span 2;
    grid-column: 2;
    align-self: center;
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1;
    color: var(--pkd-c);
}

.pkd-persen {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-text-muted);
}

.pkd-ket {
    grid-column: 1 / -1;
    margin-top: 0.375rem;
    font-size: 0.72rem;
    line-height: 1.35;
    color: var(--hcis-text-muted);
}

.pkd-bar {
    display: flex;
    height: 0.6rem;
    border-radius: var(--hcis-radius-pill);
    overflow: hidden;
    background: var(--hcis-surface-soft);
}

.pkd-seg { transition: width 0.3s ease; }
.pkd-seg.is-fungsional { background: var(--hcis-brand); }
.pkd-seg.is-publik { background: var(--hcis-success-500); }
.pkd-seg.is-belum_beredar { background: var(--hcis-amber-500); }

.pkd-total {
    margin: 0;
    font-size: 0.75rem;
    color: var(--hcis-text-muted);
}
</style>

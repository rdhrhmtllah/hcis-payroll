<template>
    <span v-if="adaKontak" class="kontak-bantuan-tombol-wrap">
        <button
            type="button"
            class="kontak-bantuan-tombol"
            :class="{ 'is-ringkas': ringkas }"
            data-testid="kontak-bantuan-tombol"
            :aria-label="`${judul}. ${subjudul}`"
            @click="terbuka = true"
        >
            <HcisIcon name="headset" size="sm" class="kontak-bantuan-tombol__ikon" aria-hidden="true" />
            <span class="kontak-bantuan-tombol__teks">
                <span class="kontak-bantuan-tombol__judul">{{ judul }}</span>
                <span v-if="!ringkas" class="kontak-bantuan-tombol__subjudul">{{ subjudul }}</span>
            </span>
        </button>

        <HcisDialog
            v-model="terbuka"
            :title="judulDialog"
            :description="deskripsiDialog"
            icon="headset"
            sentiment="info"
            size="md"
        >
            <KontakBantuanDaftar :kontak="kontak" />
            <template #footer>
                <HcisButton variant="secondary" size="md" label="Tutup" @click="terbuka = false" />
            </template>
        </HcisDialog>
    </span>
</template>

<script>
import { HcisButton, HcisDialog, HcisIcon } from '@/components/ui';
import KontakBantuanDaftar from './KontakBantuanDaftar.vue';

/**
 * Tombol "Butuh bantuan?" + subjudul; diklik membuka dialog daftar kontak resmi.
 *
 * Sengaja berupa TOMBOL (bukan kartu besar) supaya bisa ditaruh di mana saja —
 * kepala halaman, footer dialog, samping tombol lain — tanpa memakan tempat.
 * Tidak dirender sama sekali bila belum ada kontak yang bisa dihubungi.
 */
export default {
    name: 'KontakBantuanTombol',
    components: { HcisButton, HcisDialog, HcisIcon, KontakBantuanDaftar },
    props: {
        kontak: { type: Array, default: () => [] },
        judul: { type: String, default: 'Butuh bantuan?' },
        subjudul: { type: String, default: 'Hubungi kontak resmi' },
        judulDialog: { type: String, default: 'Kontak bantuan' },
        deskripsiDialog: { type: String, default: 'Pilih salah satu kontak di bawah untuk bertanya.' },
        // Hanya judul, tanpa subjudul — untuk tempat sempit.
        ringkas: { type: Boolean, default: false },
    },
    data() {
        return { terbuka: false };
    },
    computed: {
        adaKontak() {
            return (this.kontak || []).some((k) => k && k.nama && (k.tautan_wa || k.tautan_email));
        },
    },
};
</script>

<style scoped>
/* Selaras dengan .hcis-btn--secondary ukuran kecil di design-system.css:
   tinggi, sudut, warna, dan bayangan sama dengan tombol lain di kepala halaman. */
.kontak-bantuan-tombol-wrap {
    display: inline-flex;
}

.kontak-bantuan-tombol {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    height: 2.25rem;
    padding: 0 0.8rem 0 0.65rem;
    border-radius: 9px;
    border: 1px solid #cbd5e1;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.06), inset 0 1px 0 0 #ffffff;
    color: var(--hcis-slate-800, #1e293b);
    font: inherit;
    text-align: left;
    white-space: nowrap;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.kontak-bantuan-tombol:hover {
    border-color: #94a3b8;
    background: #f8fafc;
}

.kontak-bantuan-tombol:focus-visible {
    outline: 2px solid var(--hcis-brand-500, #6366f1);
    outline-offset: 2px;
}

.kontak-bantuan-tombol__ikon {
    color: var(--hcis-info-600, #0284c7);
    flex-shrink: 0;
}

.kontak-bantuan-tombol__teks {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.1rem;
    line-height: 1.05;
    min-width: 0;
}

.kontak-bantuan-tombol__judul {
    font-size: 0.78rem;
    font-weight: 650;
}

.kontak-bantuan-tombol__subjudul {
    font-size: 0.66rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #64748b);
}

.kontak-bantuan-tombol.is-ringkas {
    height: 2rem;
    padding: 0 0.75rem 0 0.6rem;
}

/* Layar HP: otomatis ringkas (judul saja) supaya tidak mendesak tombol lain
   di kepala halaman maupun footer dialog. Subjudul tetap terbaca oleh
   pembaca layar lewat aria-label. */
@media (max-width: 640px) {
    .kontak-bantuan-tombol {
        height: 2rem;
        padding: 0 0.7rem 0 0.55rem;
        flex-shrink: 0;
    }
    .kontak-bantuan-tombol__subjudul {
        display: none;
    }
}
</style>

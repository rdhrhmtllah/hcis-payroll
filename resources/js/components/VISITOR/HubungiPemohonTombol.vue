<template>
    <span v-if="daftar.length" class="hubungi-pemohon">
        <HcisButton
            :variant="variant"
            :size="size"
            leading-icon="whatsapp"
            :label="label"
            class="hubungi-pemohon__tombol"
            data-testid="hubungi-pemohon"
            :aria-label="`${label}: ${daftar.map((k) => k.nama).join(', ')}`"
            @click.stop="terbuka = true"
        />

        <HcisDialog
            v-model="terbuka"
            :title="judul"
            :description="deskripsi"
            icon="telephone-outbound"
            sentiment="info"
            size="md"
        >
            <KontakBantuanDaftar :kontak="daftar" />
            <p class="hubungi-pemohon__catatan">
                Pesan WhatsApp sudah terisi otomatis (nama, kendaraan, pos, dan jam jadwal); periksa lalu kirim.
            </p>
            <template #footer>
                <HcisButton variant="secondary" size="md" label="Tutup" @click="terbuka = false" />
            </template>
        </HcisDialog>
    </span>
</template>

<script>
import { HcisButton, HcisDialog } from '@/components/ui';
import KontakBantuanDaftar from '@/components/SYSTEM/KontakBantuan/KontakBantuanDaftar.vue';

/**
 * Tombol "Hubungi" untuk admin SHE: menghubungi pemohon (atau tamu dan
 * PIC-nya) yang terlambat/tidak datang ke jadwal inspeksi.
 *
 * Kontak disusun server (KontakPemohonInspeksiService) dengan bentuk yang sama
 * seperti kontak bantuan, jadi kartunya memakai KontakBantuanDaftar. Tidak
 * dirender sama sekali bila tidak ada nomor yang bisa dihubungi.
 */
export default {
    name: 'HubungiPemohonTombol',
    components: { HcisButton, HcisDialog, KontakBantuanDaftar },
    props: {
        kontak: { type: Array, default: () => [] },
        label: { type: String, default: 'Hubungi' },
        judul: { type: String, default: 'Hubungi pemohon' },
        deskripsi: {
            type: String,
            default: 'Untuk pemohon yang terlambat atau belum datang ke jadwal inspeksi.',
        },
        variant: { type: String, default: 'secondary' },
        size: { type: String, default: 'sm' },
    },
    data() {
        return { terbuka: false };
    },
    computed: {
        daftar() {
            return (this.kontak || []).filter((k) => k && k.nama && (k.tautan_wa || k.tautan_telepon));
        },
    },
};
</script>

<style scoped>
.hubungi-pemohon {
    display: inline-flex;
}

/* Tombol mengikuti lebar pembungkusnya: kelas tata letak induk (mis. tombol
   aksi selebar layar di HP) jatuh ke <span> ini, bukan ke tombolnya. */
.hubungi-pemohon__tombol {
    width: 100%;
}

.hubungi-pemohon__catatan {
    margin: 0.75rem 0 0;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
}
</style>

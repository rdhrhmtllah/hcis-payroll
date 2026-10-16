<template>
    <div class="sop-master-dokumen-stats mb-3">
        <HcisStatGroup :cols="4" gap="sm">
            <!-- 1. Total Dokumen Master -->
            <HcisStatCard
                layout="compact"
                label="Total Dokumen Master"
                :value="total"
                suffix="Dokumen"
                caption="Seluruh master dokumen terdaftar"
                icon="journal-bookmark-fill"
                sentiment="brand"
                appearance="crystal"
                pattern="grid"
                interactive
                :loading="loading"
                @click="emit('filter-all')"
            />

            <!-- 2. Sudah Terbit.
                 NAMANYA DIGANTI DARI "Dokumen Berlaku" (31 Agu 2026). Kartu ini
                 menghitung dokumen yang PUNYA VERSI TERBIT — dokumen yang masa
                 berlakunya sudah lewat pun tetap masuk hitungan. Bersebelahan
                 dengan "Perlu Ditinjau" yang justru bicara soal masa berlaku,
                 kata "berlaku" dipakai untuk dua arti sekaligus, dan yang satu
                 membuat yang lain terbaca salah. "Terbit" adalah peristiwa yang
                 tak bisa dibaca dua cara. -->
            <HcisStatCard
                layout="compact"
                label="Sudah Terbit"
                :value="validCount"
                suffix="Dokumen"
                :caption="`${validPercentage}% dari katalog`"
                icon="patch-check-fill"
                sentiment="success"
                appearance="crystal"
                pattern="dots"
                :loading="loading"
            />

            <!-- 3. Perlu Ditinjau -->
            <HcisStatCard
                layout="compact"
                label="Perlu Ditinjau"
                :value="ringkasan.PERHATIAN || 0"
                suffix="Dokumen"
                caption="Masa berlaku / jadwal review menagih"
                icon="exclamation-triangle-fill"
                sentiment="warning"
                appearance="crystal"
                pattern="stripes"
                interactive
                :loading="loading"
                @click="emit('filter-attention')"
            />

            <!-- 4. Belum Lengkap -->
            <!-- SENTIMENNYA NETRAL, dan itu keputusan bukan kelalaian. Kartu ini
                 menghitung pekerjaan yang menggantung tanpa tenggat — tumpukan
                 draft memang keadaan normal, sebab mendeklarasikan SOP adalah
                 langkah PERTAMA alur. Mewarnainya seperti "Perlu Ditinjau" akan
                 membuat dua peringatan bersaing, dan yang benar-benar menagih
                 tak lagi menonjol. -->
            <HcisStatCard
                layout="compact"
                label="Belum Lengkap"
                :value="ringkasan.BELUM_LENGKAP || 0"
                suffix="Dokumen"
                caption="Belum punya versi berlaku"
                icon="hourglass-split"
                sentiment="neutral"
                appearance="crystal"
                pattern="grid"
                interactive
                :loading="loading"
                @click="emit('filter-incomplete')"
            />

            <!-- "Karyawan Terikat" DIHAPUS 31 Agu 2026 (keputusan user).
                 Angkanya milik dokumen yang sedang dipilih, bukan milik
                 katalognya — ia berubah setiap kali admin memindahkan pilihan,
                 sehingga berdiri di deretan ringkasan katalog ia menjawab
                 pertanyaan yang tak sedang ditanyakan. Angka itu tetap ada di
                 panel detail dokumennya, tempat ia memang berarti. -->
        </HcisStatGroup>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisStatGroup, HcisStatCard } from '@/components/ui';

defineOptions({ name: 'SopMasterDokumenStats' });

const props = defineProps({
    total: {
        type: Number,
        default: 0,
    },
    ringkasan: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['filter-all', 'filter-attention', 'filter-incomplete']);

/* SUDAH TERBIT dihitung dari tahapnya, bukan dari "total dikurangi yang perlu
   perhatian". Pengurangan itu mengasumsikan keduanya saling meniadakan, padahal
   dokumen yang sudah terbit justru YANG BISA menagih tanggal — sejak "Perlu
   Ditinjau" berarti masa berlaku, rumus lama akan mengurangi dokumen yang
   justru sedang dihitungnya. SIAP dan DIPAKAI adalah dua tahap yang sama-sama
   berarti "punya versi terbit"; bersama "Belum Lengkap" keduanya menutup
   seluruh katalog. */
const validCount = computed(() =>
    (props.ringkasan?.SIAP || 0) + (props.ringkasan?.DIPAKAI || 0));

const validPercentage = computed(() => {
    if (!props.total || props.total <= 0) return 0;
    return Math.round((validCount.value / props.total) * 100);
});

/* KARTU SELALU MENYARING, TERMASUK SAAT ANGKANYA NOL — keputusan user
   31 Agu 2026.

   Percobaan sebelumnya mematikan kartu bernilai nol supaya penampakan dan
   perilakunya sepakat. Yang diminta justru sebaliknya: menekan kartu berarti
   "bawa saya ke daftar ini", dan jawaban "memang tak ada" adalah jawaban yang
   sah — asalkan daftarnya mengatakannya dengan kalimat yang jelas, bukan
   dengan diam. Kalimat itu ada di keadaan kosong daftarnya, yang menyebut
   penyaring mana yang sedang menyala. */
</script>

<style scoped>
.sop-master-dokumen-stats {
    width: 100%;
}
</style>

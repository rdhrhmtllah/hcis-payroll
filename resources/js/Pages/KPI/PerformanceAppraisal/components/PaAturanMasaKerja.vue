<template>
    <!--
     | Baris ringkas, sebentuk dengan baris "Urutan Wizard & Kalibrasi Penilai": ikon, judul
     | dengan nilainya, satu kalimat, dan satu tombol. Isian dan penjelasannya ada di dialog —
     | angka ini jarang diubah, jadi tidak perlu memakan tempat di layar setiap saat.
     -->
    <div class="pa-masa-kerja" data-uji="aturan-masa-kerja">
        <div class="pa-masa-kerja__ikon">
            <HcisIcon name="calendar-check" size="xs" />
        </div>

        <div class="pa-masa-kerja__teks">
            <div class="pa-masa-kerja__judul">
                <span>Minimal Masa Kerja Peserta</span>
                <HcisBadge
                    :label="nilai === null ? 'Tidak berlaku' : `${nilai} bulan`"
                    variant="neutral"
                    appearance="subtle"
                    size="2xs"
                    data-uji="masa-kerja-nilai"
                />
            </div>
            <p class="pa-masa-kerja__ringkas">{{ ringkasan }}</p>
            <p v-if="!bolehUbah && alasanTerkunci && nilai !== null" class="pa-masa-kerja__ringkas" data-uji="masa-kerja-terkunci">
                <HcisIcon name="lock" size="2xs" /> {{ alasanTerkunci }}
            </p>
        </div>

        <HcisButton
            v-if="bolehUbah"
            label="Ubah"
            leading-icon="sliders"
            variant="secondary"
            size="xs"
            data-uji="masa-kerja-ubah"
            @click="buka"
        />

        <HcisDialog
            v-model="dialog"
            title="Minimal Masa Kerja Peserta"
            :kicker="kicker"
            icon="calendar-check"
            sentiment="brand"
            size="sm"
        >
            <div class="pa-masa-kerja-dialog">
                <div class="pa-masa-kerja-dialog__isian">
                    <label class="pa-masa-kerja-dialog__label" for="pa-masa-kerja-input">
                        Masa kerja minimal pada akhir periode penilaian
                    </label>
                    <HcisInputNumber
                        id="pa-masa-kerja-input"
                        v-model="draf"
                        :min="0"
                        :max="maks"
                        suffix="bulan"
                        :disabled="menyimpan"
                        data-uji="masa-kerja-input"
                    />
                </div>

                <HcisAlert variant="info" appearance="subtle" icon="info-circle-fill">
                    <ul class="pa-masa-kerja-dialog__aturan">
                        <li>Dihitung dari tanggal masuk sampai akhir periode yang dinilai.</li>
                        <li>Yang bergabung setelah periode berakhir selalu dikecualikan, juga bila batasnya 0.</li>
                        <li>Alasannya tercatat; admin tetap bisa mengikutkan orang tertentu secara manual.</li>
                        <li v-if="keterangan" data-uji="masa-kerja-keterangan">{{ keterangan }}</li>
                    </ul>
                </HcisAlert>
            </div>

            <template #footer>
                <div class="pa-masa-kerja-dialog__aksi">
                    <HcisButton label="Batal" variant="secondary" size="sm" :disabled="menyimpan" @click="dialog = false" />
                    <HcisButton
                        label="Simpan"
                        variant="primary"
                        size="sm"
                        :disabled="!berubah"
                        :loading="menyimpan"
                        data-uji="masa-kerja-simpan"
                        @click="simpan"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisAlert, HcisBadge, HcisButton, HcisDialog, HcisIcon, HcisInputNumber } from '@/components/ui';

/*
 | Satu tampilan untuk dua tempat: master (nilai awal siklus baru) dan Langkah 4 siklus
 | (salinan siklus itu). Penyimpanannya diserahkan ke induk karena alamat dan akibatnya
 | berbeda. Dialog menutup sendiri begitu nilai dari induk sama dengan yang diisi — tanda
 | simpannya berhasil; bila gagal, dialog tetap terbuka dengan isiannya.
 */
defineOptions({ name: 'PaAturanMasaKerja' });

const props = defineProps({
    /** null = siklus lama yang dibuat sebelum aturan ini ada. */
    nilai: { type: Number, default: null },
    maks: { type: Number, default: 24 },
    bolehUbah: { type: Boolean, default: false },
    menyimpan: { type: Boolean, default: false },
    keterangan: { type: String, default: '' },
    alasanTerkunci: { type: String, default: '' },
    kicker: { type: String, default: 'PERFORMANCE APPRAISAL · KELAYAKAN PESERTA' },
});

const emit = defineEmits(['simpan']);

const dialog = ref(false);
const draf = ref(props.nilai);

const ringkasan = computed(() => {
    if (props.nilai === null) {
        return props.bolehUbah
            ? 'Belum berlaku di siklus ini. Klik Ubah untuk memberlakukannya.'
            : props.alasanTerkunci || 'Aturan masa kerja tidak berlaku di siklus ini.';
    }
    if (props.nilai === 0) {
        return 'Hanya karyawan yang bergabung setelah periode berakhir yang dikecualikan otomatis.';
    }
    return `Karyawan dengan masa kerja di bawah ${props.nilai} bulan pada akhir periode dikecualikan otomatis.`;
});

const berubah = computed(() => draf.value !== null && draf.value !== undefined && Number(draf.value) !== props.nilai);

watch(() => props.nilai, (v) => {
    if (dialog.value && v === Number(draf.value)) dialog.value = false;
    draf.value = v;
});

function buka() {
    draf.value = props.nilai;
    dialog.value = true;
}

function simpan() {
    if (!berubah.value) return;
    emit('simpan', Number(draf.value));
}
</script>

<style scoped>
.pa-masa-kerja {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.875rem 1.15rem;
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-xl);
    background: var(--hcis-surface);
}

.pa-masa-kerja__ikon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border-radius: var(--hcis-radius-md);
    background: var(--hcis-surface-soft);
    color: var(--hcis-text-muted);
}

.pa-masa-kerja__teks {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-masa-kerja__judul {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    font-size: 0.925rem;
    font-weight: 700;
    color: var(--hcis-text-strong);
}

.pa-masa-kerja__ringkas {
    margin: 0;
    font-size: 0.75rem;
    color: var(--hcis-text-muted);
}

.pa-masa-kerja-dialog {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-masa-kerja-dialog__isian {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pa-masa-kerja-dialog__isian :deep(.hcis-input-number) {
    width: 11rem;
}

.pa-masa-kerja-dialog__label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--hcis-text-strong);
}

.pa-masa-kerja-dialog__aturan {
    margin: 0;
    padding-left: 1.1rem;
    line-height: 1.6;
}

.pa-masa-kerja-dialog__aksi {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}
</style>

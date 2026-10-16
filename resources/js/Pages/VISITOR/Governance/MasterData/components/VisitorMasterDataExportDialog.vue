<!--
    Pratinjau export master data Visitor (dry run) sebelum berkas dirakit.

    Angka di sini datang dari server lewat jalur yang sama dengan berkasnya,
    bukan dari tabel yang sedang tampil di layar: tabel hanya memuat satu
    halaman dan satu master, sehingga jumlah yang dihitung di layar selalu
    lebih kecil dari isi berkas dan membuat pratinjaunya berbohong.
-->
<template>
    <HcisDialog
        :model-value="modelValue"
        title="Unduh Master Data Permit"
        description="Periksa lembar dan jumlah barisnya dulu; berkas baru dirakit setelah Anda menekan Unduh. Berkas ini sekaligus templat import."
        kicker="Dry run"
        icon="file-earmark-spreadsheet-fill"
        sentiment="purple"
        variant="glass"
        size="lg"
        sticky-footer
        :close-on-overlay="!mengunduh"
        :close-on-esc="!mengunduh"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="visitor-master-export">
            <HcisAlert
                v-if="galat"
                variant="danger"
                :title="galat.message"
                :description="galat.requestId ? `Kode: ${galat.errorCode} · Request ID: ${galat.requestId}` : galat.errorCode"
            />

            <div v-else-if="memuat" class="visitor-master-export__memuat">
                <HcisSkeleton v-for="baris in 4" :key="baris" height="42px" />
            </div>

            <template v-else-if="pratinjau">
                <div class="visitor-master-export__ringkas">
                    <div>
                        <p class="visitor-master-export__label">Nama berkas</p>
                        <p class="visitor-master-export__nilai">{{ pratinjau.nama_berkas }}</p>
                    </div>
                    <div>
                        <p class="visitor-master-export__label">Total baris</p>
                        <p class="visitor-master-export__nilai">
                            {{ pratinjau.total_baris }} baris · {{ pratinjau.sheets.length }} lembar
                        </p>
                    </div>
                </div>

                <table class="visitor-master-export__tabel">
                    <thead>
                        <tr>
                            <th scope="col">Lembar</th>
                            <th scope="col">Kolom</th>
                            <th scope="col" class="visitor-master-export__angka">Baris</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lembar in pratinjau.sheets" :key="lembar.master">
                            <td>
                                <span class="visitor-master-export__sheet">{{ lembar.sheet }}</span>
                                <HcisBadge v-if="lembar.tersaring" variant="warning" size="xs">
                                    Mengikuti saringan layar
                                </HcisBadge>
                            </td>
                            <td class="visitor-master-export__kolom">{{ lembar.kolom.join(' · ') }}</td>
                            <td class="visitor-master-export__angka">
                                {{ lembar.baris }}
                                <span v-if="lembar.tersaring" class="visitor-master-export__dari">
                                    dari {{ lembar.total }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="visitor-master-export__catatan">
                    Kolom nomor urut tidak ikut diekspor. Baris nonaktif tetap ditulis dan ditandai pada kolom Status.
                </p>
            </template>
        </div>

        <template #footer>
            <HcisButton variant="ghost" :disabled="mengunduh" @click="tutup">Batal</HcisButton>
            <HcisButton
                variant="primary"
                icon="download"
                :loading="mengunduh"
                :disabled="!bolehUnduh"
                @click="unduh"
            >
                Unduh Excel
            </HcisButton>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisAlert, HcisBadge, HcisButton, HcisDialog, HcisSkeleton, hcisMessage } from '@/components/ui';
import { simpanBerkasUnduhan, visitorMasterDataApi } from '../api/visitorMasterDataApi';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    baseUrl: { type: String, required: true },
    domain: { type: String, required: true },
    /** Tab yang sedang terbuka; hanya lembar ini yang mengikuti saringan layar. */
    masterAktif: { type: String, default: '' },
    /** Saringan layar apa adanya; page/limit dibuang karena export bukan halaman. */
    filters: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const memuat = ref(false);
const mengunduh = ref(false);
const pratinjau = ref(null);
const galat = ref(null);
let urutanPermintaan = 0;

const bolehUnduh = computed(() => !!pratinjau.value && pratinjau.value.total_baris > 0);

const parameter = computed(() => {
    const { page, limit, ...saringan } = props.filters || {};

    return { ...saringan, master: props.masterAktif };
});

watch(() => props.modelValue, (terbuka) => {
    if (terbuka) muatPratinjau();
}, { immediate: true });

async function muatPratinjau() {
    const urutan = ++urutanPermintaan;
    memuat.value = true;
    galat.value = null;
    pratinjau.value = null;

    try {
        const response = await visitorMasterDataApi.exportPreview(props.baseUrl, props.domain, parameter.value);
        if (urutan !== urutanPermintaan) return;
        pratinjau.value = response.data;
    } catch (error) {
        if (urutan !== urutanPermintaan) return;
        galat.value = error;
    } finally {
        if (urutan === urutanPermintaan) memuat.value = false;
    }
}

async function unduh() {
    if (mengunduh.value || !bolehUnduh.value) return;
    mengunduh.value = true;

    try {
        const berkas = await visitorMasterDataApi.exportDownload(props.baseUrl, props.domain, parameter.value);
        simpanBerkasUnduhan(berkas, pratinjau.value?.nama_berkas);
        hcisMessage.success('Berkas export sedang diunduh.');
        emit('update:modelValue', false);
    } catch (error) {
        hcisMessage.error(error.message);
    } finally {
        mengunduh.value = false;
    }
}

function tutup() {
    if (mengunduh.value) return;
    emit('update:modelValue', false);
}
</script>

<style scoped>
.visitor-master-export {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}

.visitor-master-export__memuat {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2, 0.5rem);
}

.visitor-master-export__ringkas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--hcis-space-3, 0.75rem);
    padding: var(--hcis-space-3, 0.75rem);
    border: 1px solid var(--hcis-border-subtle, rgb(226 232 240));
    border-radius: var(--hcis-radius-lg, 0.75rem);
    background: var(--hcis-surface-muted, rgb(248 250 252));
}

.visitor-master-export__label {
    margin: 0;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, rgb(100 116 139));
}

.visitor-master-export__nilai {
    margin: 0.125rem 0 0;
    font-weight: 600;
    color: var(--hcis-text-strong, rgb(15 23 42));
}

.visitor-master-export__tabel {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.visitor-master-export__tabel th,
.visitor-master-export__tabel td {
    padding: 0.5rem 0.625rem;
    text-align: left;
    border-bottom: 1px solid var(--hcis-border-subtle, rgb(226 232 240));
    vertical-align: top;
}

.visitor-master-export__tabel th {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-text-muted, rgb(100 116 139));
}

.visitor-master-export__sheet {
    display: inline-block;
    margin-right: 0.5rem;
    font-weight: 600;
    color: var(--hcis-text-strong, rgb(15 23 42));
}

.visitor-master-export__kolom {
    color: var(--hcis-text-muted, rgb(100 116 139));
}

.visitor-master-export__angka {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.visitor-master-export__dari {
    display: block;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, rgb(100 116 139));
}

.visitor-master-export__catatan {
    margin: 0;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, rgb(100 116 139));
}
</style>

<template>
    <HcisDialog
        :model-value="modelValue"
        title="Penerbitan hasil"
        kicker="ADMINISTRATOR PA · PUBLIKASI"
        :description="cycle ? `${cycle.Nama_Cycle} · ${cycle.Kode_Cycle}` : ''"
        icon="send-check-fill"
        icon-variant="glass"
        sentiment="success"
        size="lg"
        variant="glass"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="pa-publication">
            <HcisResultState
                v-if="error"
                :status="errorStatus === 403 ? 'warning' : 'danger'"
                variant="flat"
                layout="inline"
                :title="errorStatus === 403 ? 'Akses penerbitan tidak tersedia' : 'Ringkasan penerbitan gagal dimuat'"
                :description="error"
                :show-retry="errorStatus !== 403"
                :error-code="errorCode"
                :request-id="requestId"
                @retry="$emit('retry')"
            />
            <div v-else-if="loading" class="pa-publication__loading" aria-live="polite">
                <HcisIcon name="arrow-repeat" size="lg" animation="spin" color="success" />
                <span>Memuat kesiapan penerbitan...</span>
            </div>
            <template v-else-if="summary">
                <section class="pa-publication__summary" aria-label="Ringkasan penerbitan">
                    <HcisStatCard
                        label="Total peserta"
                        :value="formatAngka(summary.total)"
                        caption="Cakupan siklus"
                        icon="people-fill"
                        sentiment="brand"
                    />
                    <HcisStatCard
                        label="Siap diterbitkan"
                        :value="formatAngka(summary.dihitung)"
                        caption="Hasil berstatus DIHITUNG"
                        icon="send-check-fill"
                        sentiment="success"
                    />
                    <HcisStatCard
                        label="Sudah terbit"
                        :value="formatAngka(summary.terbit)"
                        caption="Sudah terlihat karyawan"
                        icon="check2-circle"
                        sentiment="purple"
                    />
                    <HcisStatCard
                        label="Tertahan"
                        :value="formatAngka(summary.tertahan)"
                        caption="Tidak ikut diterbitkan"
                        icon="hourglass-split"
                        sentiment="warning"
                    />
                    <HcisStatCard
                        label="Belum dihitung"
                        :value="formatAngka(summary.belum_dihitung)"
                        caption="Masih menunggu hasil"
                        icon="clock-history"
                        sentiment="neutral"
                    />
                </section>

                <section class="pa-publication__decision" :class="{ 'is-blocked': !bolehTerbit }" aria-labelledby="publication-decision-title" data-uji="keputusan-server">
                    <div class="pa-publication__decision-icon"><HcisIcon :name="bolehTerbit ? 'shield-check' : 'shield-lock'" size="md" /></div>
                    <div>
                        <span class="pa-eyebrow">KEPUTUSAN SERVER</span>
                        <!-- Dulu kalimat ini selalu "siap diterbitkan", juga ketika server pasti menolak. -->
                        <h3 id="publication-decision-title">{{ bolehTerbit ? 'Hasil siap dapat diterbitkan sekarang' : 'Belum bisa diterbitkan' }}</h3>
                        <p v-if="bolehTerbit">
                            Penerbitan massal hanya memproses hasil berstatus DIHITUNG. Peserta tertahan dan yang belum
                            dihitung tetap tercatat sebagai pekerjaan lanjutan.
                        </p>
                        <p v-else>{{ summary.syarat?.alasan }}</p>
                    </div>
                </section>

                <p v-if="summary.kualitatif_belum?.belum > 0" class="pa-publication__pengingat" data-uji="pengingat-kualitatif">
                    <HcisIcon name="chat-quote-fill" size="2xs" />
                    {{ formatAngka(summary.kualitatif_belum.belum) }} peserta siap terbit belum punya kesimpulan kualitatif
                    ({{ summary.kualitatif_belum.contoh.join(', ') }}{{ summary.kualitatif_belum.belum > summary.kualitatif_belum.contoh.length ? ', …' : '' }}).
                    Sesudah terbit, kesimpulannya tidak bisa ditulis lagi — tulis dulu di tahap Kesimpulan Kualitatif bila perlu.
                </p>

                <HcisResultState
                    v-if="actionError"
                    :status="actionErrorStatus === 403 ? 'warning' : 'danger'"
                    variant="flat"
                    layout="inline"
                    :title="actionErrorStatus === 403 ? 'Akses aksi penerbitan tidak tersedia' : 'Penerbitan ditolak'"
                    :description="actionError"
                    :show-retry="false"
                    :error-code="actionErrorCode"
                    :request-id="actionRequestId"
                />
                <HcisResultState
                    v-else-if="actionSuccess"
                    status="success"
                    variant="flat"
                    layout="inline"
                    title="Aksi penerbitan tersimpan"
                    :description="actionSuccess"
                    :show-retry="false"
                />

                <section
                    v-if="actionResult?.total_peserta !== undefined"
                    class="pa-publication__receipt"
                    aria-label="Hasil penerbitan massal"
                >
                    <div class="pa-publication__receipt-head">
                        <div>
                            <span class="pa-eyebrow">REKAP AKSI</span>
                            <h3>Yang terjadi pada permintaan terakhir</h3>
                        </div>
                        <HcisBadge label="Angka dari server" variant="success" appearance="subtle" size="sm" />
                    </div>
                    <div class="pa-publication__receipt-grid">
                        <div><span>Diterbitkan</span><strong>{{ formatAngka(actionResult.diterbitkan) }}</strong></div>
                        <div><span>Sudah terbit</span><strong>{{ formatAngka(actionResult.sudah_terbit) }}</strong></div>
                        <div><span>Tertahan</span><strong>{{ formatAngka(actionResult.tertahan) }}</strong></div>
                        <div><span>Belum dihitung</span><strong>{{ formatAngka(actionResult.belum_dihitung) }}</strong></div>
                        <div><span>Siap ditutup</span><strong>{{ actionResult.siap_ditutup ? 'Ya' : 'Belum' }}</strong></div>
                    </div>
                </section>

                <section
                    v-else-if="actionResult?.status"
                    class="pa-publication__receipt"
                    aria-label="Hasil penutupan penerbitan"
                >
                    <div class="pa-publication__receipt-head">
                        <div>
                            <span class="pa-eyebrow">STATUS SIKLUS</span>
                            <h3>Penerbitan administratif selesai</h3>
                        </div>
                        <HcisBadge :label="actionResult.status" variant="success" appearance="subtle" size="sm" />
                    </div>
                    <p class="pa-publication__published-at">
                        Diterbitkan pada {{ actionResult.published_at || 'waktu yang dikirim server' }}.
                    </p>
                </section>
            </template>
            <HcisResultState
                v-else
                status="info"
                variant="flat"
                layout="compact"
                title="Ringkasan penerbitan belum tersedia"
                description="Muat ulang untuk mengambil cacah hasil terbaru dari server."
                :show-retry="false"
            />
        </div>

        <template #footer-start>
            <span class="pa-dialog-hint">
                <HcisIcon name="database-check" size="2xs" /> Angka dan keputusan akhir berasal dari server
            </span>
        </template>
        <template #footer>
            <div class="pa-publication__actions">
                <HcisButton
                    v-if="canPublish"
                    label="Terbitkan hasil siap"
                    variant="success"
                    size="sm"
                    leading-icon="send-check-fill"
                    :loading="acting && actionName === 'terbitkan'"
                    :disabled="acting || !bolehTerbit"
                    data-uji="buka-konfirmasi-terbit"
                    @click="dialogKonfirmasiTerbit = true"
                />
                <HcisButton
                    v-if="canPublish"
                    label="Tutup penerbitan"
                    variant="warning"
                    size="sm"
                    leading-icon="lock-fill"
                    :loading="acting && actionName === 'tutup'"
                    :disabled="acting || !bolehTerbit"
                    @click="dialogKonfirmasiTutup = true"
                />
                <HcisButton variant="primary" label="Tutup" size="sm" :disabled="acting" @click="$emit('update:modelValue', false)" />
            </div>
        </template>
    </HcisDialog>

    <!-- Konfirmasi TERBIT. Dulu tombolnya langsung menerbitkan: karyawan menerima notifikasi
         seketika dan penerbitan tidak bisa ditarik kembali. -->
    <HcisDialog
        v-model="dialogKonfirmasiTerbit"
        title="Terbitkan hasil ke karyawan?"
        kicker="TIDAK BISA DITARIK KEMBALI"
        icon="send-check-fill"
        sentiment="success"
        variant="glass"
        size="sm"
    >
        <div class="pa-publication__confirm" data-uji="konfirmasi-terbit">
            <div class="pa-publication__confirm-icon"><HcisIcon name="send-check-fill" size="md" /></div>
            <div>
                <h3>{{ formatAngka(summary.dihitung) }} hasil siap akan diterbitkan</h3>
                <p>
                    Karyawan langsung bisa melihat hasilnya dan menerima notifikasi. Yang tertahan
                    ({{ formatAngka(summary.tertahan) }}) dan belum dihitung ({{ formatAngka(summary.belum_dihitung) }}) tetap menunggu.
                </p>
                <p v-if="summary.kualitatif_belum?.belum > 0" class="pa-publication__pengingat">
                    {{ formatAngka(summary.kualitatif_belum.belum) }} peserta belum punya kesimpulan kualitatif; sesudah terbit kesimpulannya tidak bisa ditulis lagi.
                </p>
            </div>
        </div>
        <template #footer
            ><HcisButton variant="secondary" label="Batal" size="sm" :disabled="acting" @click="dialogKonfirmasiTerbit = false" /><HcisButton
                variant="success"
                label="Ya, terbitkan"
                size="sm"
                leading-icon="send-check-fill"
                :loading="acting && actionName === 'terbitkan'"
                :disabled="acting"
                data-uji="ya-terbit"
                @click="dialogKonfirmasiTerbit = false; $emit('publish')"
        /></template>
    </HcisDialog>

    <HcisDialog
        v-model="dialogKonfirmasiTutup"
        title="Tutup penerbitan siklus?"
        kicker="TINDAKAN SATU ARAH"
        icon="lock-fill"
        sentiment="warning"
        variant="glass"
        size="sm"
    >
        <div class="pa-publication__confirm">
            <div class="pa-publication__confirm-icon"><HcisIcon name="exclamation-triangle-fill" size="md" /></div>
            <div>
                <h3>Siklus akan dipindahkan ke PUBLISHED</h3>
                <p>
                    Setelah ditutup, server tidak mengizinkan perhitungan ulang, penerbitan susulan, atau sesi Balancing baru.
                    Pastikan pekerjaan yang belum selesai memang sudah dipahami risikonya.
                </p>
            </div>
        </div>
        <HcisResultState
            v-if="actionError"
            :status="actionErrorStatus === 403 ? 'warning' : 'danger'"
            variant="flat"
            layout="inline"
            :title="actionErrorStatus === 403 ? 'Akses penutupan tidak tersedia' : 'Penutupan penerbitan ditolak'"
            :description="actionError"
            :show-retry="false"
            :error-code="actionErrorCode"
            :request-id="actionRequestId"
        />
        <template #footer
            ><HcisButton variant="secondary" label="Batal" size="sm" :disabled="acting" @click="dialogKonfirmasiTutup = false" /><HcisButton
                variant="warning"
                label="Ya, tutup penerbitan"
                size="sm"
                leading-icon="lock-fill"
                :loading="acting"
                :disabled="acting"
                @click="$emit('close-publication')"
        /></template>
    </HcisDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisBadge, HcisButton, HcisDialog, HcisIcon, HcisResultState, HcisStatCard } from '@/components/ui';

defineOptions({ name: 'PaPublicationDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    cycle: { type: Object, default: null },
    summary: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
    acting: { type: Boolean, default: false },
    actionName: { type: String, default: '' },
    actionResult: { type: Object, default: null },
    actionError: { type: String, default: '' },
    actionErrorStatus: { type: Number, default: 0 },
    actionErrorCode: { type: String, default: '' },
    actionRequestId: { type: String, default: '' },
    actionSuccess: { type: String, default: '' },
    canPublish: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'retry', 'publish', 'close-publication']);

// Ringkasan lama tanpa `syarat` dianggap boleh; server tetap memeriksa saat aksi dijalankan.
const bolehTerbit = computed(() => props.summary?.syarat?.boleh_terbit !== false);

const dialogKonfirmasiTutup = ref(false);
const dialogKonfirmasiTerbit = ref(false);

watch(
    () => props.actionSuccess,
    (success, previous) => {
        if (success && success !== previous) dialogKonfirmasiTutup.value = false;
    },
    { immediate: true },
);

function formatAngka(value) {
    return Number(value || 0).toLocaleString('id-ID');
}
</script>

<style scoped>
.pa-publication {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}
.pa-publication__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    min-height: 14rem;
    color: var(--hcis-text-muted, #64748b);
}
.pa-publication__summary {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.65rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}
.pa-publication__decision {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(16, 185, 129, 0.24);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.88), rgba(255, 255, 255, 0.78));
}
.pa-publication__decision-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    color: #047857;
    border-radius: 0.7rem;
    background: rgba(16, 185, 129, 0.13);
}
.pa-publication__confirm {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}
.pa-publication__confirm-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    color: #b45309;
    border-radius: 0.7rem;
    background: rgba(245, 158, 11, 0.15);
}
.pa-publication__confirm h3 {
    margin: 0.15rem 0 0.2rem;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.95rem;
}
.pa-publication__confirm p {
    margin: 0;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.75rem;
    line-height: 1.45;
}
.pa-publication__decision h3,
.pa-publication__receipt h3 {
    margin: 0.2rem 0 0.18rem;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.95rem;
}
.pa-publication__decision p,
.pa-publication__published-at {
    margin: 0;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.74rem;
    line-height: 1.45;
}
.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.64rem;
    letter-spacing: 0.1em;
    font-weight: 800;
}
.pa-publication__receipt {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(99, 102, 241, 0.16);
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(238, 242, 255, 0.52);
}
.pa-publication__receipt-head,
.pa-publication__actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}
.pa-publication__receipt-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.55rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}
.pa-publication__receipt-grid div {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    padding: 0.65rem 0.7rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(255, 255, 255, 0.7);
}
.pa-publication__receipt-grid span {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.68rem;
}
.pa-publication__receipt-grid strong {
    color: #3730a3;
    font-size: 1rem;
    font-variant-numeric: tabular-nums;
}
.pa-dialog-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.72rem;
}
@media (max-width: 1000px) {
    .pa-publication__summary {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 640px) {
    .pa-publication {
        gap: 0.75rem;
    }
    .pa-publication__summary,
    .pa-publication__receipt-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
    }
    .pa-publication__summary :deep(.hcis-stat-card) {
        padding: 0.6rem 0.7rem !important;
        border-radius: 10px !important;
    }
    .pa-publication__summary :deep(.hcis-stat-card__value) {
        font-size: 1.2rem !important;
    }
    .pa-publication__summary :deep(.hcis-stat-card__caption) {
        display: none !important;
    }
    .pa-publication__decision {
        padding: 0.65rem 0.75rem;
        gap: 0.55rem;
        border-radius: 10px;
    }
    .pa-publication__decision h3 {
        font-size: 0.85rem;
    }
    .pa-publication__decision p {
        font-size: 0.72rem;
    }
    .pa-publication__receipt {
        padding: 0.65rem 0.75rem;
        border-radius: 10px;
    }
    .pa-publication__receipt-head,
    .pa-publication__actions {
        align-items: stretch;
        flex-direction: column;
        gap: 0.5rem;
    }
    .pa-publication__actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
}
.pa-publication__decision.is-blocked { border-color: #fde68a; background: #fffbeb; }
.pa-publication__pengingat { display: flex; align-items: flex-start; gap: 0.4rem; margin: 0; padding: 0.55rem 0.75rem; border-radius: 10px; background: #faf5ff; font-size: 0.8rem; line-height: 1.45; color: #6b21a8; }
</style>

<template>
    <HcisDialog
        :model-value="true"
        size="md"
        :sentiment="nada.sentiment"
        variant="glass"
        pattern="none"
        glow
        :glow-sentiment="nada.sentiment"
        custom-class="pd-modal"
        icon="bi bi-megaphone-fill"
        kicker="Pengumuman HCIS"
        :show-close="true"
        :close-on-esc="true"
        :close-on-overlay="true"
        @close="$emit('tunda')"
    >
        <!-- Modal Dialog Header Title & Badge -->
        <template #title>
            <span class="pd-modal__dialog-title">Pembaruan Data Karyawan</span>
        </template>

        <template #badge>
            <HcisBadge
                :label="nada.sentiment === 'warning' ? 'Tenggat Menipis' : 'Wajib Diisi'"
                :variant="nada.sentiment === 'warning' ? 'warning' : 'brand'"
                dot
                size="xs"
            />
        </template>

        <div class="pd-modal__body-wrap">
            <!-- Hero Result State -->
            <HcisResultState
                :status="nada.sentiment"
                variant="transparent"
                size="md"
                :title="judul"
                :description="teks"
                animated
                loop-animation
                class="pd-modal__hero"
            />

            <!-- 3 Highlights Cards (Neat & Clean Grid) -->
            <div class="pd-modal__features">
                <div class="pd-modal__feature-card">
                    <div class="pd-modal__feature-icon">
                        <HcisIcon name="bi bi-stopwatch-fill" size="xs" />
                    </div>
                    <div class="pd-modal__feature-text">
                        <span class="pd-modal__feature-title">~2 Menit</span>
                        <span class="pd-modal__feature-desc">Cepat & Ringkas</span>
                    </div>
                </div>

                <div class="pd-modal__feature-card">
                    <div class="pd-modal__feature-icon">
                        <HcisIcon name="bi bi-shield-lock-fill" size="xs" />
                    </div>
                    <div class="pd-modal__feature-text">
                        <span class="pd-modal__feature-title">Tervalidasi</span>
                        <span class="pd-modal__feature-desc">Kerahasiaan HR</span>
                    </div>
                </div>

                <div class="pd-modal__feature-card">
                    <div class="pd-modal__feature-icon">
                        <HcisIcon name="bi bi-check-circle-fill" size="xs" />
                    </div>
                    <div class="pd-modal__feature-text">
                        <span class="pd-modal__feature-title">Sekali Saja</span>
                        <span class="pd-modal__feature-desc">Selesai Permanen</span>
                    </div>
                </div>
            </div>

            <!-- Segmented Quota Meter Card -->
            <div class="pd-modal__quota-card" :class="`is-${nada.sentiment}`">
                <div class="pd-modal__quota-top">
                    <div class="pd-modal__quota-left">
                        <HcisIcon name="bi bi-hourglass-split" size="xs" class="pd-modal__quota-icon" />
                        <span class="pd-modal__quota-title">Jatah Penundaan</span>
                    </div>
                    <span class="pd-modal__quota-pill" :class="`is-${nada.sentiment}`">
                        {{ kalimatJatah }}
                    </span>
                </div>

                <div
                    class="pd-modal__meter-track"
                    role="progressbar"
                    :aria-valuenow="status.sisa_tunda"
                    :aria-valuemax="maksPenundaan"
                >
                    <div
                        v-for="i in maksPenundaan"
                        :key="i"
                        class="pd-modal__meter-segment"
                        :class="{
                            'is-terpakai': i <= status.jumlah_tunda,
                            'is-tersisa': i > status.jumlah_tunda,
                        }"
                    />
                </div>

                <p class="pd-modal__quota-desc">
                    {{
                        status.sisa_tunda <= 1
                            ? '⚠️ Kesempatan menunda hampir habis. Lengkapi sekarang agar akses sistem tetap lancar.'
                            : 'Anda masih dapat menunda hingga jatah habis bila sedang mendesak.'
                    }}
                </p>
            </div>

            <!-- Footer Action Buttons (Neat & Balanced) -->
            <div class="pd-modal__actions">
                <HcisButton
                    variant="secondary"
                    :label="pengumuman.label_tombol_tunda"
                    leading-icon="bi bi-clock-history"
                    size="md"
                    :disabled="sedangMembuka"
                    class="pd-modal__btn-tunda"
                    data-uji="tunda"
                    @click="$emit('tunda')"
                />

                <HcisButton
                    variant="primary"
                    :label="sedangMembuka ? 'Membuka Halaman…' : pengumuman.label_tombol_isi"
                    :trailing-icon="sedangMembuka ? undefined : 'bi bi-arrow-right'"
                    :loading="sedangMembuka"
                    :disabled="sedangMembuka"
                    size="md"
                    glow
                    class="pd-modal__btn-isi"
                    data-uji="isi"
                    @click="handleIsi"
                />
            </div>
        </div>
    </HcisDialog>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { HcisBadge, HcisButton, HcisDialog, HcisIcon, HcisResultState } from '@/components/ui';
import { tahanTourOtomatis } from '@/composables/tourRegistry.js';

/**
 * Ajakan melengkapi data — untuk orang yang MASIH bisa menundanya.
 *
 * Menggunakan Header Indigo pada HcisDialog lengkap dengan kicker, judul,
 * badge dan tombol close, dipadukan dengan HcisResultState di body modal,
 * kartu fitur 3-kolom, indikator meter jatah, dan aksi yang seimbang.
 */
const props = defineProps({
    pengumuman: { type: Object, required: true },
    status: { type: Object, required: true },
    maksPenundaan: { type: Number, default: 3 },
});

const emit = defineEmits(['tunda', 'isi', 'tampil']);

const sedangMembuka = ref(false);

const handleIsi = () => {
    sedangMembuka.value = true;
    emit('isi');
};

// Selama modal ini terbuka, tour pertama-kali halaman di belakangnya menunggu;
// kalau tidak, tirai tour menumpuk di atas modal.
let lepasTour = null;

onMounted(() => {
    lepasTour = tahanTourOtomatis();
    emit('tampil');
});

onBeforeUnmount(() => {
    lepasTour?.();
});

/**
 * Sentimen nada modal:
 * - Awal: info
 * - Saat jatah tunda terpakai: warning
 */
const nada = computed(() =>
    props.status.jumlah_tunda > 0
        ? {
            sentiment: 'warning',
            judul: 'Tinggal kamu yang belum',
            teks: 'Dua menit yang sama masih menunggu. Sesudah ini kesempatan menundanya makin sedikit.',
        }
        : { sentiment: 'info', judul: null, teks: null },
);

const judul = computed(() => nada.value.judul ?? props.pengumuman.judul);
const teks = computed(() => nada.value.teks ?? props.pengumuman.teks);

const kalimatJatah = computed(() =>
    props.status.sisa_tunda === props.maksPenundaan
        ? `bisa ditunda ${props.status.sisa_tunda} kali`
        : `sisa ${props.status.sisa_tunda} penundaan`,
);
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   HCIS DIALOG - INDIGO HEADER OVERRIDE
   ═══════════════════════════════════════════════════════════════════════════ */
:deep(.hcis-dialog__header) {
    background: linear-gradient(135deg, var(--hcis-indigo-950) 0%, var(--hcis-indigo-900) 40%, var(--hcis-indigo-800) 75%, var(--hcis-indigo-700) 100%) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.95rem 1.25rem 0.85rem 1.25rem !important;
    color: var(--hcis-white) !important;
    position: relative;
    overflow: hidden;
}

:deep(.hcis-dialog__kicker) {
    color: var(--hcis-indigo-200) !important;
    font-size: 0.6875rem !important;
    font-weight: 750 !important;
    letter-spacing: 0.06em !important;
    text-transform: uppercase !important;
}

:deep(.hcis-dialog__title) {
    color: var(--hcis-white) !important;
    font-size: 1rem !important;
    font-weight: 750 !important;
    letter-spacing: -0.01em !important;
}

:deep(.hcis-dialog__icon) {
    background: rgba(255, 255, 255, 0.15) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    color: var(--hcis-white) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
    width: 2.25rem !important;
    height: 2.25rem !important;
    border-radius: 10px !important;
}

:deep(.hcis-dialog__close) {
    background: rgba(255, 255, 255, 0.12) !important;
    border: 1px solid rgba(255, 255, 255, 0.22) !important;
    color: var(--hcis-indigo-100) !important;
    transition: all 140ms ease;
}

:deep(.hcis-dialog__close:hover) {
    background: rgba(255, 255, 255, 0.25) !important;
    color: var(--hcis-white) !important;
    border-color: rgba(255, 255, 255, 0.4) !important;
}

/* ═══════════════════════════════════════════════════════════════════════════
   MODAL BODY LAYOUT
   ═══════════════════════════════════════════════════════════════════════════ */
.pd-modal__body-wrap {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
    padding: var(--hcis-space-4) var(--hcis-space-5) var(--hcis-space-5);
    background: var(--hcis-surface, var(--hcis-white));
}

.pd-modal__hero {
    padding: 0.5rem 0 0 !important;
}

:deep(.hcis-result-state__icon-container) {
    margin-bottom: 0.875rem !important;
}

:deep(.hcis-result-state__title) {
    font-size: 1.1875rem !important;
    font-weight: 800 !important;
    letter-spacing: -0.015em !important;
    color: var(--hcis-text-strong, var(--hcis-slate-900)) !important;
    line-height: 1.3 !important;
    margin-bottom: 0.35rem !important;
}

:deep(.hcis-result-state__description) {
    font-size: 0.84rem !important;
    font-weight: 500 !important;
    color: var(--hcis-text-muted, var(--hcis-slate-500)) !important;
    line-height: 1.45 !important;
}

/* ═══════════════════════════════════════════════════════════════════════════
   3 HIGHLIGHT FEATURE CARDS
   ═══════════════════════════════════════════════════════════════════════════ */
.pd-modal__features {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--hcis-space-2-5);
    width: 100%;
}

.pd-modal__feature-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: var(--hcis-space-2-5) var(--hcis-space-2);
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-md, 10px);
    transition: transform var(--hcis-duration-fast) var(--hcis-ease), border-color var(--hcis-duration-fast) var(--hcis-ease), box-shadow var(--hcis-duration-fast) var(--hcis-ease);
}

.pd-modal__feature-card:hover {
    border-color: rgb(var(--hcis-brand-500-rgb) / 0.35);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    transform: translateY(-1px);
}

.pd-modal__feature-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.875rem;
    height: 1.875rem;
    border-radius: var(--hcis-radius-sm, 7px);
    background: rgb(var(--hcis-brand-500-rgb) / 0.12);
    color: var(--hcis-brand-600);
    margin-bottom: var(--hcis-space-1);
}

.pd-modal__feature-text {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.pd-modal__feature-title {
    font-size: 0.8125rem;
    font-weight: 800;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
    line-height: 1.2;
}

.pd-modal__feature-desc {
    font-size: 0.6875rem;
    font-weight: 550;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    line-height: 1.25;
}

/* ═══════════════════════════════════════════════════════════════════════════
   SEGMENTED QUOTA METER
   ═══════════════════════════════════════════════════════════════════════════ */
.pd-modal__quota-card {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2);
    padding: var(--hcis-space-3) var(--hcis-space-3-5);
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-surface-soft, var(--hcis-slate-50));
    border: 1px solid var(--hcis-border-medium, var(--hcis-slate-300));
}

.pd-modal__quota-card.is-warning {
    background: linear-gradient(180deg, var(--hcis-amber-50) 0%, var(--hcis-amber-100) 100%);
    border-color: var(--hcis-amber-300);
}

.pd-modal__quota-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.pd-modal__quota-left {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-1-5);
}

.pd-modal__quota-icon {
    color: var(--hcis-text-muted, var(--hcis-slate-500));
}

.pd-modal__quota-title {
    font-size: 0.78125rem;
    font-weight: 750;
    color: var(--hcis-text-strong, var(--hcis-slate-900));
}

.pd-modal__quota-pill {
    font-size: 0.71875rem;
    font-weight: 750;
    padding: 0.15rem 0.55rem;
    border-radius: var(--hcis-radius-full);
    background: rgb(var(--hcis-brand-500-rgb) / 0.12);
    color: var(--hcis-brand-700, var(--hcis-indigo-700));
}

.pd-modal__quota-pill.is-warning {
    background: rgb(var(--hcis-warning-500-rgb) / 0.16);
    color: var(--hcis-amber-700);
}

.pd-modal__meter-track {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-1-5);
    width: 100%;
}

.pd-modal__meter-segment {
    flex: 1;
    height: 6px;
    border-radius: var(--hcis-radius-full);
    transition: all var(--hcis-duration-normal) var(--hcis-ease);
}

.pd-modal__meter-segment.is-tersisa {
    background: linear-gradient(90deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    box-shadow: 0 0 6px rgba(99, 102, 241, 0.4);
}

.pd-modal__meter-segment.is-terpakai {
    background: var(--hcis-amber-500);
    box-shadow: 0 0 6px rgba(245, 158, 11, 0.45);
}

.pd-modal__quota-desc {
    margin: 0;
    font-size: 0.71875rem;
    font-weight: 500;
    color: var(--hcis-text-muted, var(--hcis-slate-500));
    line-height: 1.35;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ACTIONS BUTTONS
   ═══════════════════════════════════════════════════════════════════════════ */
.pd-modal__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: var(--hcis-space-3);
    width: 100%;
    margin-top: var(--hcis-space-1);
}

.pd-modal__btn-tunda {
    flex: 1;
    justify-content: center;
    font-weight: 650;
}

.pd-modal__btn-isi {
    flex: 1.4;
    justify-content: center;
    font-weight: 750;
}

@media (max-width: 480px) {
    .pd-modal__features {
        grid-template-columns: 1fr;
    }

    .pd-modal__feature-card {
        flex-direction: row;
        gap: var(--hcis-space-2-5);
        text-align: left;
    }

    .pd-modal__feature-icon {
        margin-bottom: 0;
    }

    .pd-modal__actions {
        flex-direction: column-reverse;
        gap: var(--hcis-space-2);
    }

    .pd-modal__btn-tunda,
    .pd-modal__btn-isi {
        width: 100%;
    }
}
</style>

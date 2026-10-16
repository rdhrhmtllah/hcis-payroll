<template>
    <Teleport :to="teleportTo" :disabled="!teleportTo">
        <div class="rs-bilah">
            <div class="rs-bilah-format-wrap">
                <span class="rs-bilah-format-label">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Format:
                </span>
                <HcisSelect
                    :model-value="format"
                    :clearable="false"
                    class="rs-bilah-format"
                    @update:model-value="(v) => $emit('update:format', v)"
                >
                    <HcisSelectOption value="xlsx" label="Excel (.xlsx)" />
                    <HcisSelectOption value="csv" label="CSV (.csv)" />
                    <HcisSelectOption value="pdf" label="PDF (.pdf)" />
                </HcisSelect>
            </div>

            <span class="rs-bilah-isi"></span>

            <!--
                Sebab ditulis DI SEBELAH tombol, bukan disembunyikan di tooltip.
                Tombol mati tanpa alasan adalah jalan buntu, bukan pencegahan:
                orang tidak tahu apa yang harus diperbaiki untuk menghidupkannya.
            -->
            <div v-if="sebabMati" class="rs-bilah-sebab" :title="sebabMati">
                <i class="bi bi-info-circle-fill me-1"></i>
                <span>{{ sebabMati }}</span>
            </div>
            <div v-else-if="valid" class="rs-bilah-siap">
                <i class="bi bi-check-circle-fill me-1"></i>
                <span>Definisi siap diekspor</span>
            </div>

            <div class="rs-bilah-actions">
                <HcisButton
                    v-if="templateAktif"
                    variant="ghost"
                    size="sm"
                    icon="bi-x-lg"
                    label="Tutup template"
                    title="Tutup template dan kosongkan builder"
                    @click="$emit('tutup-template')"
                />

                <HcisButton
                    variant="secondary"
                    size="sm"
                    :icon="templateAktif ? 'bi-arrow-repeat' : 'bi-bookmark'"
                    :label="templateAktif ? 'Update template' : 'Simpan sebagai…'"
                    :disabled="!valid || sibuk"
                    @click="$emit('simpan')"
                />

                <!-- Tetap hidup meski pratinjau terlalu berat: kehilangan pratinjau
                     bukan alasan mencabut export. -->
                <HcisButton
                    variant="primary"
                    size="sm"
                    icon="bi-play-fill"
                    label="Jalankan export"
                    :loading="sibuk"
                    :disabled="!valid || sibuk"
                    @click="$emit('jalankan')"
                />
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisSelect from '@/components/ui/HcisSelect.vue';
import HcisSelectOption from '@/components/ui/HcisSelectOption.vue';

defineProps({
    format: { type: String, default: 'xlsx' },
    valid: { type: Boolean, default: false },
    sibuk: { type: Boolean, default: false },
    sebabMati: { type: String, default: '' },
    templateAktif: { type: Object, default: null },
    teleportTo: { type: [String, Object, Boolean], default: 'body' },
});

defineEmits(['update:format', 'simpan', 'jalankan', 'tutup-template']);
</script>

<style scoped>
/*
    Bilah Aksi Mengapung Tetap (Fixed Floating Action Dock).
    Diposisikan persis di tengah area kerja halaman (.shell-main)
    dengan mengimbangi offset sidebar (4.5rem/72px).
*/
.rs-bilah {
    position: fixed !important;
    bottom: 1.25rem !important;
    left: calc(50% + 2.25rem) !important;
    transform: translateX(-50%) !important;
    width: min(calc(94vw - 4.5rem), 1100px) !important;
    z-index: 1000 !important;
    margin: 0 !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.65rem 1.25rem;
    border: 1px solid rgba(99, 102, 241, 0.22);
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-radius: var(--hcis-radius-pill, 9999px);
    box-shadow: 0 20px 45px -8px rgba(15, 23, 42, 0.22), 0 8px 16px -4px rgba(99, 102, 241, 0.16), 0 0 0 1px rgba(99, 102, 241, 0.18) !important;
    animation: rs-bilah-float-in 220ms ease;
}

@keyframes rs-bilah-float-in {
    from {
        opacity: 0;
        transform: translate(-50%, 20px);
    }
    to {
        opacity: 1;
        transform: translate(-50%, 0);
    }
}

.rs-bilah-format-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.rs-bilah-format-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #1e293b);
    display: flex;
    align-items: center;
}

.rs-bilah-format {
    width: 8.5rem;
}

.rs-bilah-isi {
    flex: 1 1 auto;
}

.rs-bilah-sebab {
    font-size: 0.76rem;
    font-weight: 600;
    color: #b45309;
    background: #fffbeb;
    border: 1px solid #fde68a;
    padding: 0.25rem 0.75rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    display: inline-flex;
    align-items: center;
    max-width: 420px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rs-bilah-siap {
    font-size: 0.76rem;
    font-weight: 600;
    color: #15803d;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    padding: 0.25rem 0.75rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    display: inline-flex;
    align-items: center;
}

.rs-bilah-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@media (max-width: 991.98px) {
    .rs-bilah {
        left: 50% !important;
        width: min(94vw, 1100px) !important;
        bottom: 0.75rem !important;
    }
}

@media (max-width: 768px) {
    .rs-bilah {
        left: 50% !important;
        width: calc(100% - 1.5rem) !important;
        border-radius: var(--hcis-radius-lg, 12px) !important;
        padding: 0.6rem 0.85rem !important;
    }
}
</style>

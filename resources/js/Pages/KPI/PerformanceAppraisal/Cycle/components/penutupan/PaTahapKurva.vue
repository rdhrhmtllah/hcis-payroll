<template>
    <div class="pa-tkv">
        <!-- Banner Metode Pita (Jika siklus bukan kurva) -->
        <div v-if="metode !== 'KURVA'" class="pa-tkv__pita-alert" role="alert">
            <div class="pa-tkv__pita-icon">
                <HcisIcon name="rulers" size="sm" />
            </div>
            <div class="pa-tkv__pita-content">
                <strong>Siklus Ini Menggunakan Metode Pita Nilai Tetap</strong>
                <p>Grade karyawan ditentukan langsung berdasarkan rentang nilai absolut, bukan dari distribusi sebaran statistik kurva normal. Tahap penguncian kurva ini dapat dilewati.</p>
            </div>
        </div>

        <!-- Guidance Accordion Card -->
        <div class="pa-tkv__guidance-card">
            <div
                class="pa-tkv__guidance-header"
                role="button"
                tabindex="0"
                @click="bukaPanduan = !bukaPanduan"
                @keydown.enter="bukaPanduan = !bukaPanduan"
            >
                <div class="pa-tkv__guidance-header-left">
                    <div class="pa-tkv__guidance-icon-box">
                        <HcisIcon name="lightbulb-fill" size="xs" />
                    </div>
                    <div>
                        <span class="pa-tkv__guidance-title">Panduan &amp; Aturan Penguncian Kurva Grade</span>
                        <p class="pa-tkv__guidance-sub">Pahami bagaimana nilai rata-rata (&mu;) dan simpangan baku (&sigma;) membentuk grade karyawan</p>
                    </div>
                </div>
                <div class="pa-tkv__guidance-toggle">
                    <span>{{ bukaPanduan ? 'Sembunyikan' : 'Pelajari Aturan Kurva' }}</span>
                    <HcisIcon :name="bukaPanduan ? 'chevron-up' : 'chevron-down'" size="2xs" />
                </div>
            </div>

            <div v-show="bukaPanduan" class="pa-tkv__guidance-body">
                <div class="pa-tkv__steps-grid">
                    <div class="pa-tkv__step-item">
                        <span class="pa-tkv__step-badge">1</span>
                        <div class="pa-tkv__step-text">
                            <strong>Distribusi Statistik per Grup Level</strong>
                            <p>Grade ditentukan dari posisi nilai akhir terhadap rata-rata (&mu;) dan simpangan baku (&sigma;) masing-masing grup level jabatan.</p>
                        </div>
                    </div>
                    <div class="pa-tkv__step-item">
                        <span class="pa-tkv__step-badge">2</span>
                        <div class="pa-tkv__step-text">
                            <strong>Sifat Grade Sementara</strong>
                            <p>Sebelum dikunci, grade masih bersifat sementara dan dinamis bergeser jika terdapat penugasan rater yang baru masuk atau dihitung ulang.</p>
                        </div>
                    </div>
                    <div class="pa-tkv__step-item">
                        <span class="pa-tkv__step-badge">3</span>
                        <div class="pa-tkv__step-text">
                            <strong>Penguncian Membekukan Batas</strong>
                            <p>Mengunci kurva akan membekukan batas grade (&mu; &amp; &sigma;) secara permanen. Tahap Balancing dan Penerbitan Hasil hanya dapat dijalankan setelah kurva terkunci.</p>
                        </div>
                    </div>
                    <div class="pa-tkv__step-item">
                        <span class="pa-tkv__step-badge">4</span>
                        <div class="pa-tkv__step-text">
                            <strong>Fleksibilitas Pembukaan Kunci</strong>
                            <p>Kunci kurva dapat dibuka kembali dengan menyertakan alasan resmi selama belum ada hasil penilaian yang diterbitkan ke publik.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <PaKurvaPanel :cycle-id="cycleId" @berubah="$emit('berubah')" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { HcisIcon } from '@/components/ui';
import PaKurvaPanel from '../../../Hasil/components/PaKurvaPanel.vue';

defineProps({
    cycleId: { type: Number, required: true },
    metode: { type: String, default: 'KURVA' },
});
defineEmits(['berubah']);

const bukaPanduan = ref(false);
</script>

<style scoped>
.pa-tkv {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-tkv__pita-alert {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.75rem 0.95rem;
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(239, 246, 255, 0.85), #ffffff);
    color: #1e3a8a;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-tkv__pita-icon {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(59, 130, 246, 0.12);
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-tkv__pita-content {
    min-width: 0;
    flex: 1;
}

.pa-tkv__pita-content strong {
    font-size: 0.80rem;
    color: #1e293b;
}

.pa-tkv__pita-content p {
    margin: 0.15rem 0 0;
    font-size: 0.71rem;
    color: #475569;
    line-height: 1.4;
}

.pa-tkv__guidance-card {
    border: 1px solid rgba(199, 210, 254, 0.7);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, #f8faff, #ffffff);
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.04);
    overflow: hidden;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-tkv__guidance-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.65rem 0.95rem;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
    -webkit-tap-highlight-color: transparent;
}

.pa-tkv__guidance-header:hover {
    background: rgba(238, 242, 255, 0.5);
}

.pa-tkv__guidance-header-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
    flex: 1;
}

.pa-tkv__guidance-icon-box {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-tkv__guidance-title {
    font-size: 0.78rem;
    font-weight: 750;
    color: #312e81;
}

.pa-tkv__guidance-sub {
    margin: 0.1rem 0 0;
    font-size: 0.70rem;
    color: #64748b;
}

.pa-tkv__guidance-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.70rem;
    font-weight: 600;
    color: #4f46e5;
    flex-shrink: 0;
}

.pa-tkv__guidance-body {
    padding: 0 0.95rem 0.85rem 0.95rem;
    border-top: 1px dashed rgba(199, 210, 254, 0.7);
}

.pa-tkv__steps-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
    margin-top: 0.65rem;
}

.pa-tkv__step-item {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    padding: 0.6rem 0.75rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-tkv__step-badge {
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    background: #e0e7ff;
    color: #4338ca;
    font-size: 0.66rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-tkv__step-text strong {
    display: block;
    font-size: 0.75rem;
    color: #0f172a;
    font-weight: 700;
}

.pa-tkv__step-text p {
    margin: 0.15rem 0 0;
    font-size: 0.69rem;
    color: #64748b;
    line-height: 1.38;
}

@media (max-width: 768px) {
    .pa-tkv__steps-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .pa-tkv {
        gap: 0.65rem;
    }
    .pa-tkv__guidance-card {
        border-radius: var(--hcis-radius-md, 10px);
    }
    .pa-tkv__guidance-header {
        padding: 0.55rem 0.75rem;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.45rem;
    }
    .pa-tkv__guidance-header-left {
        gap: 0.5rem;
        min-width: 0;
        flex: 1;
    }
    .pa-tkv__guidance-icon-box {
        width: 1.65rem;
        height: 1.65rem;
        border-radius: 6px;
    }
    .pa-tkv__guidance-title {
        font-size: 0.74rem;
    }
    .pa-tkv__guidance-sub {
        display: none;
    }
    .pa-tkv__guidance-toggle {
        font-size: 0.66rem;
        align-self: center;
    }
    .pa-tkv__guidance-body {
        padding: 0 0.75rem 0.65rem 0.75rem;
    }
    .pa-tkv__steps-grid {
        grid-template-columns: 1fr;
        gap: 0.4rem;
        margin-top: 0.5rem;
    }
    .pa-tkv__step-item {
        padding: 0.45rem 0.55rem;
        gap: 0.45rem;
        border-radius: 6px;
    }
    .pa-tkv__step-badge {
        width: 1.15rem;
        height: 1.15rem;
        font-size: 0.62rem;
    }
    .pa-tkv__step-text strong {
        font-size: 0.72rem;
    }
    .pa-tkv__step-text p {
        font-size: 0.66rem;
        line-height: 1.3;
    }
    .pa-tkv__pita-alert {
        padding: 0.6rem 0.75rem;
        border-radius: var(--hcis-radius-md, 10px);
        gap: 0.5rem;
    }
    .pa-tkv__pita-icon {
        width: 1.65rem;
        height: 1.65rem;
        border-radius: 6px;
    }
    .pa-tkv__pita-content strong {
        font-size: 0.76rem;
    }
    .pa-tkv__pita-content p {
        font-size: 0.68rem;
        line-height: 1.35;
    }
}
</style>

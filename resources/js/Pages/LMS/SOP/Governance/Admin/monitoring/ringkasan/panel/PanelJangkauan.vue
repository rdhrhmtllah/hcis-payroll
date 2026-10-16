<template>
    <div class="pjk">
        <!-- ══ READERSHIP EXECUTIVE METER ══ -->
        <div class="pjk-hero">
            <div class="pjk-meter-box">
                <span class="pjk-angka">{{ jangkauan.Persen }}%</span>
                <span class="pjk-badge-reach">Minat Pembaca</span>
            </div>

            <!-- Mini Metric Pods -->
            <div class="pjk-pods">
                <div class="pjk-pod">
                    <div class="pjk-pod-icon text-teal-600">
                        <HcisIcon name="people-fill" size="xs" />
                    </div>
                    <div class="pjk-pod-data">
                        <span class="pjk-pod-num">{{ angka(jangkauan.Karyawan_Aktif) }}</span>
                        <span class="pjk-pod-lbl">Populasi Karyawan</span>
                    </div>
                </div>

                <div class="pjk-pod">
                    <div class="pjk-pod-icon text-emerald-600">
                        <HcisIcon name="book-half" size="xs" />
                    </div>
                    <div class="pjk-pod-data">
                        <span class="pjk-pod-num text-emerald-600">{{ angka(jangkauan.Membuka) }}</span>
                        <span class="pjk-pod-lbl">Pernah Membaca</span>
                    </div>
                </div>

                <div class="pjk-pod">
                    <div class="pjk-pod-icon text-amber-600">
                        <HcisIcon name="door-open-fill" size="xs" />
                    </div>
                    <div class="pjk-pod-data">
                        <span class="pjk-pod-num text-amber-600">{{ angka(mengetuk) }}</span>
                        <span class="pjk-pod-lbl">Mengetuk Akses</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ PROGRESS BAR ══ -->
        <div class="pjk-bar-container">
            <div class="pjk-bar">
                <span class="pjk-isi" :style="{ width: `${lebar}%` }"></span>
            </div>
            <div class="pjk-bar-labels">
                <span>0%</span>
                <span>{{ jangkauan.Persen }}% Jangkauan Aktual ({{ angka(jangkauan.Membuka) }}/{{ angka(jangkauan.Karyawan_Aktif) }} Karyawan)</span>
                <span>100%</span>
            </div>
        </div>
    </div>
</template>

<script>
import { HcisIcon } from '@/components/ui';
import { angka } from '../agregat.js';

export default {
    name: 'PanelJangkauan',
    components: { HcisIcon },
    props: {
        jangkauan: {
            type: Object,
            default: () => ({
                Karyawan_Aktif: 0,
                Tersentuh: 0,
                Membuka: 0,
                Persen: 0,
                Dokumen_Publik: 0,
            }),
        },
    },
    computed: {
        lebar() {
            return Math.min(100, Math.max(0, Number(this.jangkauan.Persen) || 0));
        },
        mengetuk() {
            return Math.max(0, (this.jangkauan.Tersentuh || 0) - (this.jangkauan.Membuka || 0));
        },
    },
    methods: { angka },
};
</script>

<style scoped>
.pjk {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

/* ══ HERO METER ══ */
.pjk-hero {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(240, 253, 250, 0.75) 0%, rgba(255, 255, 255, 0.95) 100%);
    border: 1px solid rgba(153, 246, 228, 0.8);
    box-shadow: 0 2px 8px -2px rgba(13, 148, 136, 0.08);
}

.pjk-meter-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 6.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-white);
    border: 1px solid rgba(153, 246, 228, 0.9);
    box-shadow: 0 2px 8px -2px rgba(13, 148, 136, 0.12);
}

.pjk-angka {
    font-size: 2.15rem;
    font-weight: 800;
    line-height: 1;
    color: #0d9488;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
}

.pjk-badge-reach {
    font-size: 0.65rem;
    font-weight: 750;
    color: #0f766e;
    margin-top: 0.2rem;
    text-align: center;
}

/* ══ MINI PODS ══ */
.pjk-pods {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.65rem;
    flex: 1;
}

.pjk-pod {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.5rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.85);
}

.pjk-pod-icon {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(241, 245, 249, 0.9);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pjk-pod-data {
    display: flex;
    flex-direction: column;
}

.pjk-pod-num {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.pjk-pod-lbl {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

/* ══ PROGRESS BAR ══ */
.pjk-bar-container {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.pjk-bar {
    height: 0.65rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.8);
    overflow: hidden;
}

.pjk-isi {
    display: block;
    height: 100%;
    background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
    border-radius: var(--hcis-radius-pill, 9999px);
    transition: width 0.4s ease;
}

.pjk-bar-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    font-variant-numeric: tabular-nums;
}

@media (max-width: 767.98px) {
    .pjk-hero {
        flex-direction: column;
        align-items: stretch;
    }
    .pjk-pods {
        grid-template-columns: 1fr;
    }
}
</style>

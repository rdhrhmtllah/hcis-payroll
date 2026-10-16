<template>
    <div class="row d-flex justify-content-center mb-3 fade-in align-items-center">
        <div class="col-md-12">
            <div class="leave-info-panel fade-in">
                <div class="container-fluid px-0">
                    <div class="row g-2 g-md-3 justify-content-center">
                        <div
                            v-for="(stat, idx) in stats"
                            :key="idx"
                            class="quick-stat__kolom"
                            :class="[stat.class, stat.size]"
                        >
                            <HcisStatCard
                                :label="stat.title"
                                :value="displayValue(stat)"
                                :sentiment="sentimentOf(stat)"
                            >
                                <!--
                                    Ikon dilewatkan lewat slot, bukan lewat prop `icon`.
                                    Prop itu diteruskan ke <HcisIcon :name="..." /> yang
                                    mengharap nama ikon, sedangkan pemanggil komponen ini
                                    mengirim kelas Bootstrap utuh seperti 'bi bi-activity'.
                                -->
                                <template v-if="stat.icon" #icon>
                                    <i :class="cleanIcon(stat.icon)"></i>
                                </template>
                            </HcisStatCard>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
/*
 * Adapter tipis di atas HcisStatCard.
 *
 * API publiknya sengaja dipertahankan persis: satu prop `stats` berisi array
 * objek dengan bentuk { title, value, unit, icon, bgIcon, class, size }. Tiga
 * puluh pemanggil memakai bentuk itu dan tidak satu pun perlu diubah.
 *
 * Yang berpindah hanyalah isinya: kartu, warna, dan jaraknya kini berasal dari
 * design system, bukan dari CSS lokal dengan warna keras.
 */
import { HcisStatCard } from '@/components/ui';

// bgIcon memakai kosakata Bootstrap ('bg-success-light'); sentiment memakai
// kosakata design system. Tabel ini yang menjembatani keduanya.
const SENTIMENT = {
    primary: 'primary',
    brand: 'primary',
    purple: 'purple',
    violet: 'purple',
    blue: 'primary',
    info: 'info',
    cyan: 'teal',
    teal: 'teal',
    success: 'success',
    good: 'success',
    green: 'success',
    warning: 'warning',
    orange: 'warning',
    amber: 'warning',
    danger: 'danger',
    bad: 'danger',
    red: 'danger',
};

export default {
    components: { HcisStatCard },
    props: {
        stats: { type: Array, required: true },
    },
    methods: {
        sentimentOf(stat) {
            if (stat.sentiment) return stat.sentiment;
            if (stat.color && SENTIMENT[String(stat.color).toLowerCase()]) {
                return SENTIMENT[String(stat.color).toLowerCase()];
            }
            const bg = String(stat.bgIcon || '').toLowerCase();
            const key = Object.keys(SENTIMENT).find((k) => bg.includes(k));

            return key ? SENTIMENT[key] : 'primary';
        },

        cleanIcon(icon) {
            if (!icon) return '';
            return String(icon)
                .replace(/\btext-white\b/g, '')
                .replace(/\btext-light\b/g, '')
                .trim();
        },

        displayValue(stat) {
            // Nilai dan satuan digabung karena HcisStatCard menampilkan satu
            // nilai. Keduanya tetap terbaca; yang hilang hanya gaya terpisah
            // untuk satuannya.
            return stat.unit ? `${stat.value} ${stat.unit}` : stat.value;
        },
    },
};
</script>

<style scoped>
/*
 * Kolom kartu jadi container: lebar kartu, bukan lebar layar, yang menentukan
 * tata letaknya. Tiga kartu `col-4` di ponsel hanya ~115 px — ikon di samping
 * label terdorong keluar dan terpotong oleh overflow kartu.
 */
.quick-stat__kolom {
    container-type: inline-size;
}

@container (max-width: 170px) {
    .quick-stat__kolom :deep(.hcis-stat) {
        padding: 0.8rem 0.75rem;
    }

    /* Ikon naik ke atas label, bukan berdesakan di sampingnya. */
    .quick-stat__kolom :deep(.hcis-stat__header) {
        flex-direction: column-reverse;
        align-items: flex-start;
        gap: 0.4rem;
    }

    .quick-stat__kolom :deep(.hcis-stat__icon-box) {
        width: 1.75rem;
        height: 1.75rem;
        font-size: 0.85rem;
    }

    .quick-stat__kolom :deep(.hcis-stat__label) {
        font-size: 0.68rem;
        letter-spacing: 0.02em;
        line-height: 1.25;
    }

    .quick-stat__kolom :deep(.hcis-stat__body) {
        margin-top: 0.4rem;
    }

    .quick-stat__kolom :deep(.hcis-stat__value) {
        font-size: 1.4rem;
    }
}
</style>

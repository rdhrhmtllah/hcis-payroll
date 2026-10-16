<template>
    <HcisCard
        variant="glass"
        :sentiment="vonis.sentiment"
        size="md"
        icon="diagram-3-fill"
        title="Konsentrasi Tunggakan"
        :description="vonis.kalimat"
        :loading="memuat && !data.daftar.length"
    >
        <template #actions>
            <HcisBadge
                v-if="!memuat"
                :label="vonis.lencana"
                :variant="vonis.sentiment"
                :icon="vonis.icon"
                appearance="subtle"
                size="md"
            />
        </template>

        <HcisEmptyState
            v-if="!memuat && !data.total_tunggakan"
            title="Tidak ada tunggakan"
            description="Seluruh penugasan aktif pada saringan ini sudah tuntas."
            icon="check-circle"
            sentiment="success"
            variant="dashed"
        />

        <template v-else>
            <HcisStatGroup :stats="kpi" :cols="4" gap="sm" appearance="crystal" />

            <div class="pk-daftar">
                <div class="pk-judul">
                    <HcisIcon name="person-exclamation" size="xs" />
                    Pemikul terbesar
                </div>

                <button
                    v-for="o in data.daftar"
                    :key="o.kode"
                    type="button"
                    class="pk-baris"
                    :title="`Buka rekam kepatuhan ${o.nama}`"
                    @click="$emit('buka-drawer', { kode: o.kode, token: o.token, nama: o.nama })"
                >
                    <HcisAvatar :name="o.nama" size="sm" />

                    <span class="pk-info">
                        <span class="pk-nama">{{ o.nama }}</span>
                        <span class="pk-meta">{{ o.sub_divisi || o.divisi || '—' }} · {{ o.jabatan || '—' }}</span>
                    </span>

                    <span class="pk-porsi">
                        <span class="pk-porsi-bar" :style="{ width: lebar(o) }"></span>
                    </span>

                    <span class="pk-angka">
                        <strong>{{ angka(o.tunggakan) }}</strong>
                        <small>tertunggak</small>
                    </span>

                    <HcisBadge
                        v-if="o.terlambat"
                        :label="`${o.terlambat} telat`"
                        variant="danger"
                        appearance="subtle"
                        size="sm"
                    />
                    <span v-else class="pk-kosong">—</span>
                </button>
            </div>
        </template>

        <template #footer>
            <p class="pk-kaki">
                <HcisIcon name="info-circle" size="xs" />
                {{ kalimatPareto }}
                <template v-if="data.terpotong">
                    <strong>{{ angka(data.terpotong) }} karyawan lain</strong> juga punya tunggakan tapi tak
                    ditampilkan di daftar ini.
                </template>
            </p>
        </template>
    </HcisCard>
</template>

<script>
import { HcisCard, HcisBadge, HcisAvatar, HcisEmptyState, HcisIcon, HcisStatGroup } from '@/components/ui';
import { ambil, angka } from '../agregat.js';

/**
 * Apakah tunggakan MENYEBAR atau TERPUSAT?
 *
 * Pertanyaan ini menentukan tindakannya, dan tak satu pun panel lain
 * menjawabnya. "102 penugasan tertunggak" yang dipikul 90 orang menuntut
 * kampanye; angka yang sama yang dipikul 7 orang menuntut tujuh percakapan.
 * Kampanye untuk masalah terpusat membuang waktu 83 orang yang tidak bersalah;
 * percakapan untuk masalah menyebar tak akan pernah selesai.
 */
export default {
    name: 'PanelKonsentrasi',
    components: { HcisCard, HcisBadge, HcisAvatar, HcisEmptyState, HcisIcon, HcisStatGroup },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    emits: ['buka-drawer'],
    data() {
        return {
            data: {
                total_tunggakan: 0,
                total_orang: 0,
                rerata: 0,
                terbanyak: 0,
                pareto: {},
                daftar: [],
                terpotong: 0,
            },
            memuat: true,
        };
    },
    computed: {
        vonis() {
            if (this.memuat) {
                return { sentiment: 'neutral', icon: 'hourglass-split', lencana: 'Menghitung…', kalimat: '' };
            }
            if (!this.data.total_tunggakan) {
                return { sentiment: 'success', icon: 'check-circle', lencana: 'Bersih', kalimat: 'Tak ada tunggakan pada saringan ini.' };
            }

            const p = this.data.pareto || {};
            if (p.terpusat) {
                return {
                    sentiment: 'warning',
                    icon: 'bullseye',
                    lencana: 'Terpusat',
                    kalimat: `Setengah tunggakan hanya dipikul ${angka(p.orang_50)} dari ${angka(this.data.total_orang)} karyawan. Percakapan langsung akan jauh lebih cepat daripada kampanye massal.`,
                };
            }

            return {
                sentiment: 'info',
                icon: 'people',
                lencana: 'Menyebar',
                kalimat: `Tunggakan tersebar rata: ${angka(this.data.total_orang)} karyawan memikul rata-rata ${this.data.rerata} penugasan. Ini masalah proses, bukan masalah beberapa orang.`,
            };
        },
        kpi() {
            const p = this.data.pareto || {};

            return [
                {
                    key: 'total',
                    label: 'Total Tunggakan',
                    value: angka(this.data.total_tunggakan),
                    caption: 'penugasan belum tuntas',
                    sentiment: 'warning',
                    icon: 'inbox-fill',
                },
                {
                    key: 'orang',
                    label: 'Dipikul Oleh',
                    value: angka(this.data.total_orang),
                    caption: `rata-rata ${this.data.rerata} per orang`,
                    sentiment: 'info',
                    icon: 'people-fill',
                },
                {
                    key: 'p50',
                    label: 'Separuh Beban',
                    value: p.orang_50 ? angka(p.orang_50) : '—',
                    caption: 'orang yang memikul 50% tunggakan',
                    sentiment: p.terpusat ? 'danger' : 'neutral',
                    icon: 'pie-chart-fill',
                },
                {
                    key: 'p20',
                    label: '20% Teratas',
                    value: `${p.porsi_20persen_teratas ?? 0}%`,
                    caption: 'porsi tunggakan di tangan mereka',
                    sentiment: (p.porsi_20persen_teratas ?? 0) >= 50 ? 'danger' : 'neutral',
                    icon: 'graph-up',
                },
            ];
        },
        kalimatPareto() {
            const p = this.data.pareto || {};
            if (!p.orang_80) return 'Sebaran tunggakan belum bisa disimpulkan dari data sekecil ini.';

            return `${angka(p.orang_80)} karyawan memikul 80% seluruh tunggakan. Pemikul terbanyak memegang ${angka(this.data.terbanyak)} penugasan sekaligus.`;
        },
    },
    mounted() {
        this.muat();
    },
    watch: {
        saringan: {
            deep: true,
            handler() {
                this.muat();
            },
        },
    },
    methods: {
        angka,
        /** Bar relatif terhadap pemikul TERBESAR, bukan terhadap total — porsi
            per orang terlalu kecil untuk terlihat pada populasi besar. */
        lebar(o) {
            const maks = Number(this.data.terbanyak) || 1;

            return `${Math.max(6, Math.round((Number(o.tunggakan) / maks) * 100))}%`;
        },
        async muat() {
            this.memuat = true;
            try {
                const res = await ambil('/analitik/konsentrasi', { limit: 10, ...this.saringan });
                if (Array.isArray(res)) {
                    this.data = { total_tunggakan: 0, total_orang: res.length, rerata: 0, terbanyak: 0, pareto: {}, daftar: res, terpotong: 0 };
                } else if (res && typeof res === 'object') {
                    this.data = {
                        total_tunggakan: res.total_tunggakan || 0,
                        total_orang: res.total_orang || 0,
                        rerata: res.rerata || 0,
                        terbanyak: res.terbanyak || 0,
                        pareto: res.pareto || {},
                        daftar: Array.isArray(res.daftar) ? res.daftar : [],
                        terpotong: res.terpotong || 0,
                    };
                }
            } catch (e) {
                this.data = { total_tunggakan: 0, total_orang: 0, rerata: 0, terbanyak: 0, pareto: {}, daftar: [], terpotong: 0 };
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
.pk-daftar {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-1);
    margin-top: var(--hcis-space-4);
}

.pk-judul {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    font-size: var(--hcis-font-size-2xs);
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hcis-text-subtle);
    margin-bottom: var(--hcis-space-1);
}

.pk-baris {
    display: grid;
    grid-template-columns: auto minmax(0, 1.5fr) minmax(4rem, 1fr) 5rem 5rem;
    align-items: center;
    gap: var(--hcis-space-3);
    width: 100%;
    text-align: left;
    padding: var(--hcis-space-2);
    border: 1px solid transparent;
    border-radius: var(--hcis-radius-md);
    background: transparent;
    cursor: pointer;
    transition: background var(--hcis-duration-fast) var(--hcis-ease),
        border-color var(--hcis-duration-fast) var(--hcis-ease);
}
.pk-baris:hover {
    background: var(--hcis-surface-soft);
    border-color: var(--hcis-border-subtle);
}

.pk-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.pk-nama {
    font-size: var(--hcis-font-size-sm);
    font-weight: 700;
    color: var(--hcis-text-strong);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pk-meta {
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-text-subtle);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pk-porsi {
    display: block;
    height: 0.4rem;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-surface-soft);
    overflow: hidden;
}
.pk-porsi-bar {
    display: block;
    height: 100%;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-warning-500);
}

.pk-angka {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    line-height: var(--hcis-line-tight);
}
.pk-angka strong {
    font-size: var(--hcis-font-size-md);
    font-weight: 800;
    color: var(--hcis-text-strong);
}
.pk-angka small {
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-text-subtle);
}
.pk-kosong {
    color: var(--hcis-text-subtle);
    font-size: var(--hcis-font-size-xs);
    text-align: right;
}

.pk-kaki {
    display: flex;
    align-items: flex-start;
    gap: var(--hcis-space-2);
    margin: 0;
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text-subtle);
    line-height: var(--hcis-line-relaxed);
}

@media (max-width: 640px) {
    .pk-baris {
        grid-template-columns: auto minmax(0, 1fr) 4.5rem;
    }
    .pk-porsi {
        display: none;
    }
}
</style>

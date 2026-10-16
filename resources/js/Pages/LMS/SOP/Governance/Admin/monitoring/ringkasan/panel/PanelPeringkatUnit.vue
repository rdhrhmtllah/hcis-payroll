<template>
    <HcisCard
        variant="glass"
        sentiment="brand"
        size="md"
        icon="trophy-fill"
        pattern="crosshair"
        :title="judul"
        :description="keterangan"
        :loading="memuat && !data.unit.length"
    >
        <template #actions>
            <div class="pu-dimensi" role="tablist" aria-label="Dimensi peringkat">
                <button
                    v-for="d in DIMENSI"
                    :key="d.key"
                    type="button"
                    role="tab"
                    class="pu-dim-btn"
                    :class="{ on: dimensi === d.key }"
                    :aria-selected="dimensi === d.key"
                    @click="pilih(d.key)"
                >
                    {{ d.label }}
                </button>
            </div>
        </template>

        <HcisEmptyState
            v-if="!memuat && !data.unit.length"
            title="Belum ada unit untuk diperingkat"
            description="Tak ada penugasan aktif yang bisa dikelompokkan pada dimensi ini — atau saringan menyisakan nol baris."
            icon="bar-chart"
            variant="dashed"
            sentiment="neutral"
            size="sm"
        />

        <div v-else class="pu-daftar">
            <button
                v-for="(u, i) in data.unit"
                :key="u.unit"
                type="button"
                class="pu-baris"
                :title="`Saring seluruh halaman ke ${u.unit}`"
                @click="$emit('saring', { kunci: u.saringan, nilai: u.unit })"
            >
                <span class="pu-rank" :class="peringkatKelas(i)">{{ i + 1 }}</span>

                <span class="pu-info">
                    <span class="pu-nama">{{ u.unit }}</span>
                    <span class="pu-meta">
                        {{ angka(u.orang) }} orang · {{ angka(u.selesai) }}/{{ angka(u.total) }} tuntas
                        <template v-if="u.terlambat">
                            · <span class="pu-telat">{{ angka(u.terlambat) }} terlambat</span>
                        </template>
                    </span>
                </span>

                <span class="pu-bar">
                    <HcisProgress
                        :value="u.persen"
                        :sentiment="sentimenPersen(u.persen)"
                        size="sm"
                        :show-value="false"
                    />
                </span>

                <span class="pu-persen">{{ u.persen }}%</span>

                <span class="pu-delta">
                    <HcisBadge
                        v-if="u.delta !== null"
                        :label="`${u.delta > 0 ? '+' : ''}${u.delta}`"
                        :variant="sentimenDelta(u.delta)"
                        :icon="u.delta > 0 ? 'arrow-up' : u.delta < 0 ? 'arrow-down' : 'dash'"
                        appearance="subtle"
                        size="sm"
                    />
                    <!-- Unit tanpa delta -->
                    <HcisTooltip v-else content="Belum ada perubahan data di unit ini">
                        <span class="pu-delta-kosong">—</span>
                    </HcisTooltip>
                </span>
            </button>
        </div>

        <template v-if="data.terpotong" #footer>
            <p class="pu-kaki">
                <HcisIcon name="info-circle" size="xs" class="text-brand-600 me-1" />
                <span>Menampilkan {{ angka(batas) }} unit teratas (dari total {{ angka(data.unit.length + data.terpotong) }} unit).</span>
            </p>
        </template>
    </HcisCard>
</template>

<script>
import { HcisCard, HcisBadge, HcisProgress, HcisEmptyState, HcisTooltip, HcisIcon } from '@/components/ui';
import { ambil, angka, formatLabelRentang, tanggal, sentimenPersen, sentimenDelta } from '../agregat.js';

/**
 * Dimensi peringkat.
 *
 * 'dept' DITAMPILKAN sebagai "Departemen", bukan "Sub Divisi", meski kolom yang
 * dibacanya memang `View_Divisi_Sub_Divisi.nama_sub_divisi`. Nama kolom adalah
 * urusan basis data; yang dilihat orang harus satu istilah saja — dan kartu
 * Heatmap tepat di bawahnya sudah menyebut sumbu yang SAMA "Departemen".
 * Dua nama untuk satu hal di satu halaman membuat pembacanya menduga keduanya
 * mengukur unit yang berbeda.
 *
 * Kuncinya ('dept') sengaja tak ikut berubah: ia nama parameter API.
 */
const DIMENSI = [
    { key: 'divisi', label: 'Divisi', en: 'Division' },
    { key: 'dept', label: 'Departemen', en: 'Department' },
    { key: 'lokasi', label: 'Lokasi', en: 'Location' },
    { key: 'jabatan', label: 'Jabatan', en: 'Position' },
    { key: 'level', label: 'Level', en: 'Level' },
];

/**
 * Peringkat Kepatuhan Unit.
 */
export default {
    name: 'PanelPeringkatUnit',
    components: { HcisCard, HcisBadge, HcisProgress, HcisEmptyState, HcisTooltip, HcisIcon },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    emits: ['saring'],
    data() {
        return {
            DIMENSI,
            dimensi: 'divisi',
            batas: 12,
            data: { unit: [], label: 'Divisi', rerata: 0, terpotong: 0, basis_ledger: null },
            memuat: true,
        };
    },
    computed: {
        /**
         * Judul mengikuti dimensi yang sedang dipilih.
         *
         * Bahasa Inggris mengikuti judul kartu lain di dashboard; label tombol
         * dimensinya sengaja tetap Indonesia karena ia kendali, bukan judul —
         * pembagian yang sama dipakai strip KPI (judul Inggris, keterangan
         * Indonesia).
         */
        judul() {
            const d = DIMENSI.find((x) => x.key === this.dimensi) || DIMENSI[0];

            return `Most Frequent Document Viewer (by ${d.en})`;
        },
        keterangan() {
            if (this.memuat) return 'Menghitung…';

            /* URUTANNYA DISEBUT DI SINI, dan itu bukan basa-basi: daftar ini
               menaik — peringkat 1 adalah unit dengan penyelesaian TERENDAH,
               kebalikan dari yang biasanya diduga pembaca sebuah papan
               peringkat. Selama judulnya tak menyebut arah, kalimat inilah
               satu-satunya yang menahannya dari salah baca. */
            return `Diurutkan dari unit dengan tingkat penyelesaian terendah (Rata-rata ${this.data.rerata}%).`;
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
        tanggal,
        sentimenPersen,
        sentimenDelta,
        pilih(key) {
            if (this.dimensi === key) return;
            this.dimensi = key;
            this.muat();
        },
        /** Tiga terbawah ditandai */
        peringkatKelas(i) {
            return i < 3 ? 'sorot' : '';
        },
        async muat() {
            this.memuat = true;
            try {
                const res = await ambil('/analitik/peringkat-unit', {
                    dimensi: this.dimensi,
                    limit: this.batas,
                    ...this.saringan,
                });
                if (Array.isArray(res)) {
                    this.data = { unit: res, label: 'Divisi', rerata: 0, terpotong: 0, basis_ledger: null };
                } else if (res && typeof res === 'object') {
                    this.data = {
                        unit: Array.isArray(res.unit) ? res.unit : [],
                        label: res.label || 'Divisi',
                        rerata: res.rerata || 0,
                        terpotong: res.terpotong || 0,
                        basis_ledger: res.basis_ledger || null,
                    };
                }
            } catch (e) {
                this.data = { unit: [], label: 'Divisi', rerata: 0, terpotong: 0, basis_ledger: null };
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
.pu-dimensi {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
    background: rgba(241, 245, 249, 0.75);
    padding: 0.2rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pu-dim-btn {
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.16s ease;
    white-space: nowrap;
}

.pu-dim-btn:hover {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    background: var(--hcis-white);
}

.pu-dim-btn.on {
    background: linear-gradient(135deg, var(--hcis-indigo-600), var(--hcis-indigo-700));
    color: var(--hcis-white) !important;
    font-weight: 800;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35);
}

.pu-daftar {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pu-baris {
    display: grid;
    grid-template-columns: 2rem minmax(0, 1.6fr) minmax(6rem, 1fr) 3.5rem 3.5rem;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    text-align: left;
    padding: 0.5rem 0.75rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(255, 255, 255, 0.8);
    cursor: pointer;
    transition: all 0.16s ease;
}

.pu-baris:hover {
    background: var(--hcis-white);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 2px 8px -2px rgba(99, 102, 241, 0.12);
    transform: translateY(-1px);
}

.pu-rank {
    display: grid;
    place-items: center;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: 0.75rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.pu-rank.sorot {
    background: linear-gradient(135deg, var(--hcis-red-100) 0%, var(--hcis-red-200) 100%);
    color: var(--hcis-red-700);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.pu-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pu-nama {
    font-size: 0.78125rem;
    font-weight: 750;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pu-meta {
    font-size: 0.7rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-variant-numeric: tabular-nums;
}

.pu-telat {
    color: var(--hcis-red-700);
    font-weight: 750;
}

.pu-persen {
    font-size: 0.8125rem;
    font-weight: 800;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.pu-delta {
    display: flex;
    justify-content: flex-end;
}

.pu-delta-kosong {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    font-size: 0.75rem;
}

.pu-kaki {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0;
    font-size: 0.735rem;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}

@media (max-width: 767.98px) {
    .pu-baris {
        grid-template-columns: 1.85rem minmax(0, 1fr) 3.5rem;
    }
    .pu-bar,
    .pu-delta {
        display: none;
    }
}
</style>

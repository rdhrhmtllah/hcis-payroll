<template>
    <HcisCard
        variant="glass"
        sentiment="warning"
        size="md"
        icon="signpost-split-fill"
        title="Titik Henti Dokumen"
        :description="keterangan"
        :loading="memuat && !daftar.length"
    >
        <template #actions>
            <HcisBadge
                v-if="!memuat && data.total_tertahan"
                :label="`${angka(data.total_tertahan)} pembacaan tertahan`"
                variant="warning"
                icon="pause-circle"
                appearance="subtle"
                size="md"
            />
        </template>

        <HcisEmptyState
            v-if="!memuat && !daftar.length"
            title="Tak ada dokumen yang ditinggalkan"
            description="Setiap dokumen yang dibuka pada saringan ini juga dikonfirmasi."
            icon="check-circle"
            sentiment="success"
            variant="dashed"
        />

        <div v-else class="th-daftar">
            <div
                v-for="d in daftar"
                :key="d.id_item"
                class="th-baris"
                :class="{ lama: d.umur_terlama >= 30 }"
            >
                <span class="th-ico" :class="`p-${(d.prioritas || 'NORMAL').toLowerCase()}`">
                    <HcisIcon :name="ikonTipe(d.tipe)" size="sm" />
                </span>

                <span class="th-info">
                    <span class="th-judul">{{ d.judul || 'Tanpa judul' }}</span>
                    <span class="th-meta">
                        {{ d.paket }}
                        <template v-if="d.nomor"> · {{ d.nomor }}</template>
                    </span>
                </span>

                <HcisBadge
                    :label="d.prioritas || 'NORMAL'"
                    :variant="warnaPrioritas(d.prioritas)"
                    appearance="subtle"
                    size="sm"
                />

                <span class="th-angka">
                    <strong>{{ angka(d.tertahan) }}</strong>
                    <small>orang berhenti</small>
                </span>

                <span class="th-umur" :class="{ bahaya: d.umur_terlama >= 30 }">
                    <strong>{{ angka(d.umur_terlama) }} hari</strong>
                    <small>terlama menggantung</small>
                </span>
            </div>
        </div>

        <template #footer>
            <p class="th-kaki">
                <HcisIcon name="info-circle" size="xs" />
                {{ kalimatKaki }}
                <template v-if="data.terpotong">
                    <strong>{{ angka(data.terpotong) }} dokumen lain</strong> juga punya pembacaan tertahan tapi
                    tidak ditampilkan di sini.
                </template>
            </p>
        </template>
    </HcisCard>
</template>

<script>
import { HcisCard, HcisBadge, HcisEmptyState, HcisIcon } from '@/components/ui';
import { ambil, angka } from '../agregat.js';

const IKON_TIPE = {
    PARAGRAF: 'file-text',
    PDF: 'file-earmark-pdf',
    VIDEO: 'play-btn',
    TAUTAN: 'link-45deg',
    QUIZ: 'patch-question',
};

/**
 * Di mana orang berhenti membaca.
 *
 * Panel "Dibuka tapi Belum Selesai" yang lama berdaftar ORANG. Panel ini
 * berdaftar DOKUMEN, dan itulah seluruh perbedaannya: sepuluh orang yang
 * berhenti di dokumen yang sama bukan sepuluh masalah kepatuhan — itu satu
 * dokumen yang bermasalah. Yang pertama menghasilkan sepuluh pengingat; yang
 * kedua menghasilkan satu revisi.
 */
export default {
    name: 'PanelTitikHenti',
    components: { HcisCard, HcisBadge, HcisEmptyState, HcisIcon },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            data: { daftar: [], total_tertahan: 0, total_dokumen: 0, rerata_persen: 0, terpotong: 0 },
            memuat: true,
        };
    },
    computed: {
        daftar() {
            return Array.isArray(this.data?.daftar) ? this.data.daftar : (Array.isArray(this.data) ? this.data : []);
        },
        keterangan() {
            if (this.memuat) return 'Menghitung…';
            if (!this.daftar.length) return 'Tidak ada dokumen yang dibuka lalu ditinggalkan.';

            return `${angka(this.data.total_dokumen || this.daftar.length)} dokumen dibuka lalu ditinggalkan, diurutkan dari yang paling sering ditinggalkan.`;
        },
        /**
         * Kedalaman baca hanya diklaim kalau memang terekam. Menampilkan "0%
         * rata-rata" pada dokumen yang tak pernah melaporkan persen membuat
         * pembaca menyimpulkan orang berhenti di baris pertama — padahal yang
         * terjadi adalah datanya tak pernah dikirim.
         */
        kalimatKaki() {
            if (this.data.rerata_persen > 0) {
                return `Rata-rata orang berhenti di sekitar ${this.data.rerata_persen}% isi dokumen.`;
            }

            return 'Kedalaman baca belum terekam untuk dokumen-dokumen ini, jadi yang bisa dipastikan hanya bahwa pembacaannya dibuka lalu tak pernah dikonfirmasi.';
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
        ikonTipe(t) {
            return IKON_TIPE[t] || 'file-earmark';
        },
        warnaPrioritas(p) {
            if (p === 'KRITIS') return 'danger';
            if (p === 'TINGGI') return 'warning';

            return 'neutral';
        },
        async muat() {
            this.memuat = true;
            try {
                const res = await ambil('/analitik/titik-henti', { limit: 10, ...this.saringan });
                if (Array.isArray(res)) {
                    this.data = { daftar: res, total_tertahan: 0, total_dokumen: res.length, rerata_persen: 0, terpotong: 0 };
                } else if (res && typeof res === 'object') {
                    this.data = {
                        daftar: Array.isArray(res.daftar) ? res.daftar : [],
                        total_tertahan: res.total_tertahan || 0,
                        total_dokumen: res.total_dokumen || 0,
                        rerata_persen: res.rerata_persen || 0,
                        terpotong: res.terpotong || 0,
                    };
                }
            } catch (e) {
                this.data = { daftar: [], total_tertahan: 0, total_dokumen: 0, rerata_persen: 0, terpotong: 0 };
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
.th-daftar {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-1);
}

.th-baris {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto 6rem 7rem;
    align-items: center;
    gap: var(--hcis-space-3);
    padding: var(--hcis-space-2) var(--hcis-space-2-5);
    border: 1px solid transparent;
    border-radius: var(--hcis-radius-md);
    transition: background var(--hcis-duration-fast) var(--hcis-ease);
}
.th-baris:hover {
    background: var(--hcis-surface-soft);
    border-color: var(--hcis-border-subtle);
}
.th-baris.lama {
    background: var(--hcis-warning-50);
}

.th-ico {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-md);
    background: var(--hcis-surface-soft);
    color: var(--hcis-text-muted);
}
.th-ico.p-kritis {
    background: var(--hcis-danger-50);
    color: var(--hcis-danger-700);
}
.th-ico.p-tinggi {
    background: var(--hcis-warning-50);
    color: var(--hcis-warning-700);
}

.th-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.th-judul {
    font-size: var(--hcis-font-size-sm);
    font-weight: 700;
    color: var(--hcis-text-strong);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.th-meta {
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-text-subtle);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.th-angka,
.th-umur {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    line-height: var(--hcis-line-tight);
}
.th-angka strong,
.th-umur strong {
    font-size: var(--hcis-font-size-md);
    font-weight: 800;
    color: var(--hcis-text-strong);
}
.th-angka small,
.th-umur small {
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-text-subtle);
    white-space: nowrap;
}
.th-umur.bahaya strong {
    color: var(--hcis-danger-600);
}

.th-kaki {
    display: flex;
    align-items: flex-start;
    gap: var(--hcis-space-2);
    margin: 0;
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text-subtle);
    line-height: var(--hcis-line-relaxed);
}

@media (max-width: 720px) {
    .th-baris {
        grid-template-columns: auto minmax(0, 1fr) 5.5rem;
    }
    .th-baris > :nth-child(3),
    .th-umur {
        display: none;
    }
}
</style>

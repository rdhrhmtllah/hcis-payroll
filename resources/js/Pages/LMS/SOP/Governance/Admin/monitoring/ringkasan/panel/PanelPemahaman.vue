<template>
    <div class="pm-stack">
        <div class="row g-3">
            <!-- 1. Kualitas baca -->
            <div class="col-12">
                <HcisChart
                    type="bar"
                    :data="bacaData"
                    :categories="bacaKategori"
                    :height="260"
                    title="Berapa Lama Benar-benar Dibaca"
                    kicker="Kualitas Baca"
                    :description="bacaDescription"
                    icon="stopwatch"
                    sentiment="brand"
                    variant="glass"
                    :loading="memuat"
                    :metric-value="durasi(data.baca.rerata_detik)"
                    metric-label="Rata-rata waktu baca"
                    :insight="bacaInsight"
                    empty-text="Belum ada durasi baca yang terekam"
                    allow-export
                    allow-table-toggle
                />
            </div>

        </div>
    </div>
</template>

<script>
import { HcisChart } from '@/components/ui';
import { ambil, angka, durasi, formatLabelRentang, sentimenPersen } from '../agregat.js';
import { dataBaca, kategoriBaca } from '../grafikSeri.js';

const KOSONG = {
    baca: { total: 0, rerata_detik: 0, sekilas: 0, persen_sekilas: 0, kelompok: [], paket: [] },
    versi: { total: 0, terserap: 0, tertinggal: 0, persen_terserap: 0, daftar: [] },
};

/**
 * Kualitas baca — berapa lama dokumen benar-benar dibaca.
 *
 * Kartu "Pemahaman lewat Kuis" DIBUANG dari dashboard 2026-08-27 atas
 * permintaan pemilik produk. Payload `kuis` dari /analitik/pemahaman sengaja
 * TIDAK ikut dibuang: laporan ekspor ringkasan masih memuat bagian "Evaluasi
 * Pemahaman Kuis", dan memutus payload-nya akan mengosongkan laporan yang
 * sudah dipakai orang tanpa ada yang memintanya.
 */
export default {
    name: 'PanelPemahaman',
    components: { HcisChart },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            data: JSON.parse(JSON.stringify(KOSONG)),
            memuat: true,
        };
    },
    computed: {
        labelRentang() {
            return formatLabelRentang(this.saringan);
        },
        bacaDescription() {
            if (this.labelRentang) {
                return `Waktu aktif di halaman dokumen pada rentang: ${this.labelRentang}`;
            }

            return 'Waktu aktif di halaman dokumen — bukan selisih dibuka sampai dikonfirmasi.';
        },
        /* Dibentuk grafikSeri.js — dipakai bersama perakit laporan PDF. */
        bacaKategori() {
            return kategoriBaca(this.data);
        },
        bacaData() {
            return dataBaca(this.data);
        },
        bacaInsight() {
            const b = this.data.baca;
            if (!b.total) return '';
            if (this.labelRentang) {
                return `Rata-rata ${durasi(b.rerata_detik)} per dokumen pada rentang ${this.labelRentang} (${angka(b.total)} pembacaan).`;
            }
            if (b.persen_sekilas >= 15) {
                return `${b.persen_sekilas}% pembacaan selesai dalam kurang dari satu menit — terlalu cepat untuk dokumen sepanjang ini.`;
            }
            const paling = (b.paket || [])[0];
            if (paling && paling.rasio < 60) {
                return `Paket "${paling.paket}" dibaca hanya ${paling.rasio}% dari estimasi waktunya. Estimasinya terlalu tinggi, atau isinya dilewati.`;
            }

            return `Rata-rata ${durasi(b.rerata_detik)} per dokumen, dari ${angka(b.total)} pembacaan yang durasinya terekam.`;
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
        durasi,
        async muat() {
            this.memuat = true;
            try {
                const r = await ambil('/analitik/pemahaman', { ...this.saringan });
                if (r && typeof r === 'object' && !Array.isArray(r)) {
                    this.data = {
                        baca: { ...KOSONG.baca, ...(r.baca || {}) },
                        versi: { ...KOSONG.versi, ...(r.versi || {}) },
                    };
                } else {
                    this.data = JSON.parse(JSON.stringify(KOSONG));
                }
            } catch (e) {
                this.data = JSON.parse(JSON.stringify(KOSONG));
            } finally {
                this.memuat = false;
            }
        },
    },
};
</script>

<style scoped>
.pm-stack {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}

.pm-tertinggal {
    margin-top: var(--hcis-space-4);
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-1);
}

.pm-sub-judul {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2);
    font-size: var(--hcis-font-size-2xs);
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--hcis-text-subtle);
}

.pm-baris {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--hcis-space-3);
    padding: var(--hcis-space-2);
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-border-subtle);
    background: var(--hcis-surface);
}

.pm-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.pm-nama {
    font-size: var(--hcis-font-size-sm);
    font-weight: 700;
    color: var(--hcis-text-strong);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pm-meta {
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-text-subtle);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pm-angka {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    line-height: var(--hcis-line-tight);
    flex-shrink: 0;
}
.pm-angka strong {
    font-size: var(--hcis-font-size-md);
    font-weight: 800;
    color: var(--hcis-danger-600);
}
.pm-angka small {
    font-size: var(--hcis-font-size-2xs);
    color: var(--hcis-text-subtle);
    white-space: nowrap;
}

.pm-kaki {
    display: flex;
    align-items: flex-start;
    gap: var(--hcis-space-2);
    margin: 0;
    font-size: var(--hcis-font-size-xs);
    color: var(--hcis-text-subtle);
    line-height: var(--hcis-line-relaxed);
}
</style>

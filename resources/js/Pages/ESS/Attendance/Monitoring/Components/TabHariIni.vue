<template>
    <div class="mk-tab">
        <HcisFilterBar
            :search="organisasi.search"
            search-placeholder="Cari nama atau kode karyawan dalam cakupan analisis…"
            :filters="saringanBar"
            :filter-values="nilaiSaringan"
            :collapsible="true"
            sentiment="brand"
            variant="flat"
            show-active-tags
            @update:search="$emit('update:organisasi', { ...organisasi, search: $event || '' })"
            @filter-change="gantiFilter"
            @reset="resetFilter"
        >
            <template #actions>
                <HcisButton
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-clockwise"
                    label="Segarkan"
                    :loading="memuat"
                    @click="muat"
                />
            </template>
        </HcisFilterBar>

        <p class="mk-muted mk-kesegaran">
            <template v-if="ringkasan.data_per">Data per {{ formatWaktu(ringkasan.data_per) }}</template>
            <template v-else-if="sudahDimuat">Belum ada rekaman absensi terhitung</template>
            <template v-if="tanggal === hariIni"> · pembaruan otomatis 5 menit</template>
        </p>

        <!-- 1. Cockpit Kartu Metrik Transaksi & Kehadiran (HcisStatGroup resmi) -->
        <HcisStatGroup :cols="4" gap="sm" appearance="crystal">
            <HcisStatCard
                label="Kehadiran Hari Ini"
                :value="statHadir.nilai"
                :suffix="statHadir.satuan"
                icon="check2-circle"
                sentiment="success"
                :caption="statHadir.caption"
                :loading="memuat && !sudahDimuat"
            />
            <HcisStatCard
                label="Pengajuan Sakit & Izin"
                :value="statIzinSakit.nilai"
                suffix="pengajuan"
                icon="bandaid"
                sentiment="info"
                :caption="statIzinSakit.caption"
                :loading="memuat && !sudahDimuat"
            />
            <HcisStatCard
                label="Pengajuan Cuti"
                :value="statCuti.nilai"
                suffix="karyawan"
                icon="airplane"
                sentiment="brand"
                :caption="statCuti.caption"
                :loading="memuat && !sudahDimuat"
            />
            <HcisStatCard
                label="Lembur & Konversi"
                :value="statLembur.nilai"
                suffix="pengajuan"
                icon="clock-history"
                sentiment="warning"
                :caption="statLembur.caption"
                :loading="memuat && !sudahDimuat"
            />
        </HcisStatGroup>

        <!-- Peringatan Data Sedang Dihitung Ulang -->
        <HcisAlert
            v-if="ringkasan.tanda && ringkasan.tanda.diperbarui > 0"
            variant="info"
            appearance="soft"
            size="sm"
            icon="arrow-repeat"
            title="Sebagian data sedang dihitung ulang"
            :description="`${ringkasan.tanda.diperbarui} karyawan menunggu hitung ulang absensi; status mereka dapat berubah setelah proses selesai.`"
            data-uji="peringatan-diperbarui"
        />

        <!-- 2. Analitik Visual Utama: Tren Jam ke Jam, Donut Komposisi, & Sebaran Lokasi -->
        <GrafikHariIni
            :ringkasan="ringkasan"
            :analitik="analitik"
            :memuat="memuat && !sudahDimuat"
        />

        <!-- 3. Feed Aktivitas Real-Time Hari Ini (Non-Table Live Stream) -->
        <div class="mk-feed-grid">
            <!-- Kolom 1: Aliran Check-in Terbaru -->
            <HcisCard
                title="Aliran Check-in Terbaru"
                description="Karyawan yang baru saja melakukan tap masuk hari ini"
                icon="person-check"
                sentiment="brand"
                variant="glass"
                divider
            >
                <div v-if="terbaruAbsen.length > 0" class="mk-stream-list">
                    <div
                        v-for="item in terbaruAbsen"
                        :key="item.kode"
                        class="mk-stream-item"
                    >
                        <div class="mk-stream-item__avatar">
                            <HcisIcon name="person" size="xs" />
                        </div>
                        <div class="mk-stream-item__content min-w-0">
                            <div class="d-flex justify-content-between align-items-center gap-1">
                                <span class="fw-bold text-strong text-truncate" style="font-size: 0.84rem;">{{ item.nama }}</span>
                                <span class="font-tabular fw-bold text-primary" style="font-size: 0.82rem;">{{ item.check_in }} WIB</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-1 mt-1 text-muted" style="font-size: 0.72rem;">
                                <span class="text-truncate">{{ item.departemen || item.lokasi || item.kode }} · Shift {{ item.shift }}</span>
                                <HcisBadge
                                    v-if="item.terlambat_menit > 0"
                                    :label="`Telat ${formatMenit(item.terlambat_menit)}`"
                                    variant="warning"
                                    size="2xs"
                                />
                                <span v-else class="text-success fw-semibold">Tepat waktu</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-4 text-muted" style="font-size: 0.8rem;">
                    <HcisIcon name="clock" size="md" class="mb-2 d-block mx-auto opacity-50" />
                    Belum ada rekaman tap masuk hari ini
                </div>
            </HcisCard>

            <!-- Kolom 2: Pengajuan & Transaksi Hari Ini -->
            <HcisCard
                title="Pengajuan & Transaksi Hari Ini"
                description="Daftar transaksi cuti, izin, sakit, lembur, dan konversi aktif"
                icon="file-earmark-text"
                sentiment="purple"
                variant="glass"
                divider
            >
                <div v-if="terbaruTransaksi.length > 0" class="mk-stream-list">
                    <div
                        v-for="(tx, idx) in terbaruTransaksi"
                        :key="idx"
                        class="mk-stream-item"
                    >
                        <div class="mk-stream-item__icon-badge">
                            <HcisBadge
                                :label="tx.jenis"
                                :variant="tx.tipe_badge || 'brand'"
                                size="xs"
                                appearance="subtle"
                            />
                        </div>
                        <div class="mk-stream-item__content min-w-0">
                            <div class="d-flex justify-content-between align-items-center gap-1">
                                <span class="fw-bold text-strong text-truncate" style="font-size: 0.84rem;">{{ tx.nama }}</span>
                                <span class="mk-muted font-tabular" style="font-size: 0.7rem;">{{ tx.nomor }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-1 mt-1 text-muted" style="font-size: 0.72rem;">
                                <span class="text-truncate">{{ tx.departemen || tx.lokasi || tx.kode }}</span>
                                <span class="text-truncate text-strong fst-italic">{{ tx.keterangan }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-4 text-muted" style="font-size: 0.8rem;">
                    <HcisIcon name="clipboard2-check" size="md" class="mb-2 d-block mx-auto opacity-50" />
                    Tidak ada pengajuan izin, sakit, cuti, atau lembur hari ini
                </div>
            </HcisCard>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisFilterBar,
    HcisIcon,
    HcisStatCard,
    HcisStatGroup,
    hcisMessage,
} from '@/components/ui';
import {
    ORGANISASI,
    fieldOrganisasi,
    formatMenit,
    formatTanggal,
    organisasiKosong,
    parameterOrganisasi,
    ubahOrganisasi,
} from './saringanMonitoring.js';
import GrafikHariIni from './GrafikHariIni.vue';

const BASE = '/admin/monitoring-kehadiran';
export const INTERVAL_SEGAR = 5 * 60 * 1000;

export default {
    name: 'TabHariIni',
    components: {
        GrafikHariIni,
        HcisAlert,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisFilterBar,
        HcisIcon,
        HcisStatCard,
        HcisStatGroup,
    },
    props: {
        opsiSaringan: { type: Object, default: () => ({}) },
        organisasi: { type: Object, default: () => organisasiKosong() },
        hariIni: { type: String, required: true },
        tanggalAwal: { type: String, default: '' },
        tautan: { type: Object, default: () => ({}) },
        kategori: { type: Array, default: () => [] },
    },
    emits: ['update:organisasi'],
    data() {
        return {
            tanggal: this.tanggalAwal || this.hariIni,
            memuat: false,
            sudahDimuat: false,
            ringkasan: {
                kategori: {},
                tanda: { pulang_cepat: 0, lembur: 0, diperbarui: 0, tanpa_checkout: 0 },
                data_per: null,
            },
            analitik: {
                transaksi: {},
                tren_jam: [],
                sebaran_lokasi: [],
                terbaru_absen: [],
                terbaru_transaksi: [],
            },
            pewaktu: null,
            permintaan: 0,
        };
    },
    computed: {
        tx() {
            return this.analitik?.transaksi || {};
        },
        kat() {
            return this.ringkasan?.kategori || {};
        },
        statHadir() {
            const hadir = this.tx.hadir ?? (this.kat.tepat_waktu || 0) + (this.kat.terlambat || 0);
            const terjadwal = this.tx.terjadwal ?? (this.ringkasan?.total || 0);
            const persen = terjadwal > 0 ? ((hadir / terjadwal) * 100).toFixed(1) : '0';
            const telat = this.tx.terlambat ?? (this.kat.terlambat || 0);
            return {
                nilai: `${persen}%`,
                satuan: `(${hadir} org)`,
                caption: `${hadir} dari ${terjadwal} terjadwal · ${telat} terlambat`,
            };
        },
        statIzinSakit() {
            const sakit = this.tx.sakit ?? 0;
            const izin = this.tx.izin ?? 0;
            return {
                nilai: `${sakit + izin}`,
                caption: `${sakit} sakit · ${izin} izin dinas / pribadi`,
            };
        },
        statCuti() {
            const tahunan = this.tx.cuti_tahunan ?? 0;
            const khusus = this.tx.cuti_khusus ?? 0;
            return {
                nilai: `${tahunan + khusus}`,
                caption: `${tahunan} cuti tahunan · ${khusus} cuti khusus`,
            };
        },
        statLembur() {
            const lembur = this.tx.lembur ?? 0;
            const konversi = this.tx.konversi_lembur ?? 0;
            return {
                nilai: `${lembur}`,
                caption: `${lembur} lembur kerja · ${konversi} dikonversi`,
            };
        },
        terbaruAbsen() {
            return this.analitik?.terbaru_absen || [];
        },
        terbaruTransaksi() {
            return this.analitik?.terbaru_transaksi || [];
        },
        saringanBar() {
            return [
                { key: 'tanggal', label: 'Tanggal', type: 'date', icon: 'calendar-event', col: 3, clearable: false },
                ...fieldOrganisasi(this.opsiSaringan, this.organisasi),
            ];
        },
        nilaiSaringan() {
            const nilai = { tanggal: this.tanggal };
            ORGANISASI.forEach((o) => { nilai[o.key] = this.organisasi[o.key]; });
            return nilai;
        },
    },
    watch: {
        organisasi: {
            deep: true,
            handler() {
                this.muat();
            },
        },
    },
    mounted() {
        this.muat();
        this.pewaktu = setInterval(() => {
            if (this.tanggal === this.hariIni && document.visibilityState !== 'hidden') this.muat();
        }, INTERVAL_SEGAR);
    },
    beforeUnmount() {
        clearInterval(this.pewaktu);
        this.pewaktu = null;
    },
    methods: {
        formatMenit,
        formatTanggal,
        formatWaktu(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));
            return Number.isNaN(d.getTime()) ? v : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        },
        parameter() {
            return {
                tanggal: this.tanggal,
                ...parameterOrganisasi(this.organisasi),
            };
        },
        async muat() {
            const nomor = ++this.permintaan;
            this.memuat = true;
            try {
                const { data } = await axios.get(`${BASE}/data/hari-ini`, { params: this.parameter() });
                if (nomor !== this.permintaan || !data?.success) return;
                this.ringkasan = data.result.ringkasan || {};
                this.analitik = data.result.analitik || {};
                this.sudahDimuat = true;
            } catch (e) {
                if (nomor === this.permintaan) hcisMessage.error(e.response?.data?.message || 'Data kehadiran gagal dimuat.');
            } finally {
                if (nomor === this.permintaan) this.memuat = false;
            }
        },
        gantiFilter({ key, value }) {
            if (key === 'tanggal') {
                this.tanggal = value || this.hariIni;
                this.muat();
                return;
            }
            this.$emit('update:organisasi', ubahOrganisasi(this.opsiSaringan, this.organisasi, key, value));
        },
        resetFilter() {
            this.tanggal = this.hariIni;
            this.$emit('update:organisasi', organisasiKosong());
        },
    },
};
</script>

<style scoped>
.mk-tab {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}

.mk-kesegaran {
    margin: 0;
}

.mk-feed-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--hcis-space-4, 1rem);
}

.mk-stream-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.mk-stream-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    background: var(--hcis-surface-ground, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-md, 0.5rem);
    transition: border-color 0.15s ease;
}

.mk-stream-item:hover {
    border-color: var(--hcis-brand-500, #4f46e5);
}

.mk-stream-item__avatar {
    width: 32px;
    height: 32px;
    border-radius: 9999px;
    background: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-600, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.mk-stream-item__icon-badge {
    flex-shrink: 0;
}

.mk-stream-item__content {
    flex: 1;
}

@media (max-width: 992px) {
    .mk-feed-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>

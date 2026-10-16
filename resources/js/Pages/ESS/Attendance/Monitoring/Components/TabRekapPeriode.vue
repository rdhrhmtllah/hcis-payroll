<template>
    <div class="mk-tab">
        <HcisAlert
            v-if="diperbarui > 0"
            variant="info"
            appearance="soft"
            size="sm"
            icon="arrow-repeat"
            title="Sebagian data sedang diperbarui"
            :description="`${diperbarui} baris absensi pada periode ini menunggu hitung ulang; angka bisa berubah setelah proses selesai.`"
            data-uji="peringatan-diperbarui"
        />

        <HcisCard
            :title="judulKartu"
            :description="`Periode ${labelPeriode}`"
            icon="calendar3"
            sentiment="brand"
            variant="glass"
            divider
        >
            <template #actions>
                <HcisButton
                    variant="secondary"
                    size="sm"
                    leading-icon="file-earmark-excel"
                    :label="labelEkspor"
                    :loading="ekspor.status === 'memulai' || ekspor.status === 'proses'"
                    :disabled="!rentangValid"
                    data-uji="tombol-ekspor"
                    @click="mulaiEkspor"
                />
                <HcisButton
                    v-if="ekspor.status === 'siap' && ekspor.url"
                    :href="ekspor.url"
                    variant="success"
                    size="sm"
                    leading-icon="download"
                    label="Unduh"
                    data-uji="tautan-unduh"
                />
            </template>

            <HcisFilterBar
                :search="organisasi.search"
                search-placeholder="Cari nama atau kode karyawan…"
                :filters="saringanBar"
                :filter-values="nilaiSaringan"
                :collapsible="true"
                :results-count="tampilan === 'karyawan' ? total : (tampilan === 'grafik' ? undefined : kelompok.length)"
                sentiment="brand"
                variant="flat"
                show-active-tags
                class="mb-3"
                @update:search="gantiCari"
                @filter-change="gantiFilter"
                @reset="resetFilter"
            />

            <HcisAlert
                v-if="galatRentang"
                variant="warning"
                appearance="soft"
                size="sm"
                icon="calendar-x"
                :description="galatRentang"
                class="mb-3"
            />

            <HcisTabs
                :model-value="tampilan"
                :tabs="TAB_TAMPILAN"
                variant="enclosed"
                size="sm"
                aria-label="Tampilan rekap"
                @update:model-value="gantiTampilan"
            >
                <template #[`panel-${tampilan}`]>
            <GrafikRekap
                v-if="tampilan === 'grafik' && rentangValid"
                :parameter="parameterGrafik"
                :label-periode="labelPeriode"
                @buka-hari="$emit('buka-hari', $event)"
                @buka-rincian="bukaRincian"
                @update:diperbarui="diperbarui = $event"
            />

            <HcisTable
                v-else-if="tampilan === 'karyawan'"
                :columns="KOLOM_KARYAWAN"
                :rows="baris"
                row-key="kode"
                :loading="memuat"
                sentiment="brand"
                dense
                interactive
                remote-sort
                :sort-key="urut"
                :sort-order="arah"
                empty-title="Tidak ada data kehadiran"
                empty-description="Tidak ada baris absensi pada periode dan saringan ini."
                @row-click="bukaRincian($event?.row ?? $event)"
                @sort="gantiUrutan"
            >
                <template #cell-nama="{ row }">
                    <div class="mk-karyawan">
                        <span class="mk-karyawan__nama">{{ row.nama }}</span>
                        <span class="mk-muted">{{ row.kode }}<template v-if="row.departemen"> · {{ row.departemen }}</template></span>
                    </div>
                </template>
                <template #cell-hadir="{ row }">
                    <span class="font-tabular">{{ row.hadir }}<span class="mk-muted">/{{ row.terjadwal }}</span></span>
                </template>
                <template #cell-terlambat_kali="{ row }">
                    <div class="mk-tumpuk font-tabular">
                        <span :class="{ 'mk-angka--perhatian': row.terlambat_kali > 0 }">{{ row.terlambat_kali }}×</span>
                        <span v-if="row.terlambat_kali" class="mk-muted">{{ formatMenit(row.terlambat_menit) }}<template v-if="row.terlambat_berizin"> · {{ row.terlambat_berizin }} berizin</template></span>
                    </div>
                </template>
                <template #cell-cepat_kali="{ row }">
                    <div class="mk-tumpuk font-tabular">
                        <span :class="{ 'mk-angka--perhatian': row.cepat_kali > 0 }">{{ row.cepat_kali }}×</span>
                        <span v-if="row.cepat_kali" class="mk-muted">{{ formatMenit(row.cepat_menit) }}<template v-if="row.cepat_berizin"> · {{ row.cepat_berizin }} berizin</template></span>
                    </div>
                </template>
                <template #cell-ketidakhadiran="{ row }">
                    <span class="mk-muted font-tabular">S {{ row.sakit }} · I {{ row.izin }} · C {{ row.cuti }}</span>
                </template>
                <template #cell-alpha="{ row }">
                    <span class="font-tabular" :class="{ 'mk-angka--bahaya': row.alpha > 0 }">{{ row.alpha }}</span>
                </template>
                <template #cell-lupa_checkout="{ row }">
                    <span class="font-tabular" :class="{ 'mk-angka--perhatian': row.lupa_checkout > 0 }">{{ row.lupa_checkout }}</span>
                </template>
                <template #cell-lembur_jam="{ row }">
                    <div class="mk-tumpuk font-tabular">
                        <span>{{ row.lembur_jam }} jam</span>
                        <span v-if="row.lembur_hari || row.konversi" class="mk-muted">{{ row.lembur_hari }} hari<template v-if="row.konversi"> · {{ row.konversi }} konversi</template></span>
                    </div>
                </template>
                <template #footer>
                    <HcisPagination
                        v-if="total > limit"
                        :model-value="page"
                        :page-size="limit"
                        :total="total"
                        @update:model-value="gantiHalaman"
                    />
                </template>
            </HcisTable>

            <HcisTable
                v-else-if="tampilan !== 'grafik'"
                :columns="KOLOM_KELOMPOK"
                :rows="kelompok"
                row-key="kelompok"
                :loading="memuat"
                sentiment="brand"
                dense
                empty-title="Tidak ada data kehadiran"
                empty-description="Tidak ada baris absensi pada periode dan saringan ini."
            >
                <template #cell-persen_hadir="{ row }">
                    <span class="font-tabular">{{ row.persen_hadir === null ? '—' : `${row.persen_hadir.toLocaleString('id-ID')}%` }}</span>
                </template>
                <template #cell-terlambat_menit="{ row }">
                    <span class="font-tabular">{{ formatMenit(row.terlambat_menit) }}</span>
                </template>
                <template #cell-lembur_jam="{ row }">
                    <span class="font-tabular">{{ row.lembur_jam }} jam</span>
                </template>
            </HcisTable>
                </template>
            </HcisTabs>
        </HcisCard>

        <HcisDrawer
            v-model="drawerTerbuka"
            :title="rincian ? rincian.karyawan.nama : 'Rincian Harian'"
            :description="rincian ? `${rincian.karyawan.kode} · ${labelPeriode}` : ''"
            icon="calendar-week"
            size="40rem"
        >
            <HcisSkeleton v-if="memuatRincian" height="14rem" rounded="md" />
            <HcisTable
                v-else-if="rincian"
                :columns="KOLOM_RINCIAN"
                :rows="rincian.hari"
                row-key="tanggal"
                sentiment="brand"
                dense
                empty-title="Tidak ada baris"
            >
                <template #cell-tanggal="{ row }">
                    <span class="font-tabular">{{ formatTanggal(row.tanggal, { weekday: 'short', day: '2-digit', month: 'short' }) }}</span>
                </template>
                <template #cell-shift="{ row }">
                    <div class="mk-tumpuk">
                        <span>{{ row.shift || '—' }}</span>
                        <span v-if="row.jam_masuk" class="mk-muted font-tabular">{{ row.jam_masuk }}–{{ row.jam_keluar || '?' }}</span>
                    </div>
                </template>
                <template #cell-absen="{ row }">
                    <span class="font-tabular">{{ row.check_in || '—' }} – {{ row.check_out || '—' }}</span>
                </template>
                <template #cell-status="{ row }">
                    <div class="mk-lencana">
                        <HcisBadge :label="infoKategori(row.kategori).label" :variant="infoKategori(row.kategori).variant" size="xs" appearance="subtle" dot />
                        <HcisBadge v-if="row.terlambat_menit > 0" :label="`Telat ${formatMenit(row.terlambat_menit)}`" variant="warning" size="xs" appearance="outline" />
                        <HcisBadge v-if="row.pulang_cepat_menit > 0" :label="`Pulang cepat ${formatMenit(row.pulang_cepat_menit)}`" variant="warning" size="xs" appearance="outline" />
                        <HcisBadge v-if="row.alpha" label="Alpha" variant="danger" size="xs" appearance="outline" />
                        <HcisBadge v-if="row.tanpa_checkout" label="Tanpa check-out" variant="warning" size="xs" appearance="outline" />
                        <HcisBadge v-if="row.lembur_jam > 0" :label="`Lembur ${row.lembur_jam} jam${row.konversi ? ' · konversi' : ''}`" variant="purple" size="xs" appearance="outline" />
                        <HcisBadge v-if="row.diperbarui" label="Dihitung ulang" variant="info" size="xs" appearance="outline" icon="arrow-repeat" />
                    </div>
                    <div v-if="nomorTransaksi(row.transaksi).length" class="mk-muted">
                        {{ nomorTransaksi(row.transaksi).map((t) => `${t.jenis} ${t.no}`).join(' · ') }}
                    </div>
                </template>
            </HcisTable>
        </HcisDrawer>
    </div>
</template>

<script>
import axios from 'axios';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDrawer,
    HcisFilterBar,
    HcisTabs,
    HcisPagination,
    HcisSkeleton,
    HcisTable,
    hcisMessage,
} from '@/components/ui';
import {
    ORGANISASI,
    fieldOrganisasi,
    formatMenit,
    formatTanggal,
    kategori,
    nomorTransaksi,
    organisasiKosong,
    parameterOrganisasi,
    ubahOrganisasi,
} from './saringanMonitoring.js';
import GrafikRekap from './GrafikRekap.vue';

const BASE = '/admin/monitoring-kehadiran';

export const JEDA_POLL_EKSPOR = 2000;

const RENTANG_BEBAS = 'bebas';

const LABEL_DIMENSI = { divisi: 'Divisi', departemen: 'Departemen', lokasi: 'Lokasi' };

const TAB_TAMPILAN = [
    { key: 'grafik', label: 'Dashboard Ringkasan', icon: 'speedometer2' },
    { key: 'karyawan', label: 'Tabel Karyawan', icon: 'person-lines-fill' },
    { key: 'departemen', label: 'Tabel Departemen', icon: 'diagram-2' },
    { key: 'divisi', label: 'Tabel Divisi', icon: 'diagram-3' },
    { key: 'lokasi', label: 'Tabel Lokasi', icon: 'geo-alt' },
];

const KOLOM_KARYAWAN = [
    { key: 'nama', label: 'Karyawan', minWidth: '210px', sortable: true },
    { key: 'hadir', label: 'Hadir / Terjadwal', width: '120px', align: 'right', sortable: true },
    { key: 'terlambat_kali', label: 'Terlambat', width: '130px', align: 'right', sortable: true },
    { key: 'cepat_kali', label: 'Pulang Cepat', width: '130px', align: 'right', sortable: true },
    { key: 'ketidakhadiran', label: 'Sakit · Izin · Cuti', width: '140px', align: 'right' },
    { key: 'alpha', label: 'Alpha', width: '80px', align: 'right', sortable: true },
    { key: 'lupa_checkout', label: 'Tanpa Check-out', width: '120px', align: 'right', sortable: true },
    { key: 'lembur_jam', label: 'Lembur', width: '130px', align: 'right', sortable: true },
];

const KOLOM_KELOMPOK = [
    { key: 'kelompok', label: 'Kelompok', minWidth: '220px' },
    { key: 'karyawan', label: 'Karyawan', width: '100px', align: 'right' },
    { key: 'persen_hadir', label: '% Hadir', width: '100px', align: 'right' },
    { key: 'terlambat_menit', label: 'Total Terlambat', width: '140px', align: 'right' },
    { key: 'alpha', label: 'Total Alpha', width: '110px', align: 'right' },
    { key: 'lembur_jam', label: 'Total Lembur', width: '120px', align: 'right' },
];

const KOLOM_RINCIAN = [
    { key: 'tanggal', label: 'Tanggal', width: '110px' },
    { key: 'shift', label: 'Shift', width: '120px' },
    { key: 'absen', label: 'Masuk – Pulang', width: '120px' },
    { key: 'status', label: 'Status', minWidth: '200px' },
];

export default {
    name: 'TabRekapPeriode',
    components: { GrafikRekap, HcisAlert, HcisBadge, HcisButton, HcisCard, HcisDrawer, HcisFilterBar, HcisPagination, HcisSkeleton, HcisTable, HcisTabs },
    props: {
        opsiSaringan: { type: Object, default: () => ({}) },
        organisasi: { type: Object, default: () => organisasiKosong() },
        daftarPeriode: { type: Array, default: () => [] },
        maksHari: { type: Number, default: 62 },
        /** Tampilan pembuka; induk mengingatnya supaya kembali dari drill-down tak mereset. */
        tampilanAwal: { type: String, default: 'grafik' },
    },
    emits: ['update:organisasi', 'buka-hari', 'update:tampilan'],
    data() {
        const awal = this.daftarPeriode[0] || { mulai: '', akhir: '', label: '' };
        return {
            KOLOM_KARYAWAN,
            KOLOM_KELOMPOK,
            KOLOM_RINCIAN,
            TAB_TAMPILAN,
            LABEL_DIMENSI,
            periode: awal.mulai,
            rentangBebas: [awal.mulai, awal.akhir],
            tampilan: TAB_TAMPILAN.some((t) => t.key === this.tampilanAwal) ? this.tampilanAwal : 'grafik',
            memuat: false,
            baris: [],
            kelompok: [],
            total: 0,
            diperbarui: 0,
            page: 1,
            limit: 25,
            urut: 'terlambat_kali',
            arah: 'desc',
            drawerTerbuka: false,
            memuatRincian: false,
            rincian: null,
            ekspor: { status: '', id: '', url: '' },
            pewaktuEkspor: null,
            permintaan: 0,
        };
    },
    computed: {
        rentang() {
            if (this.periode === RENTANG_BEBAS) {
                const [dari, sampai] = this.rentangBebas || [];
                return { dari: dari || '', sampai: sampai || '' };
            }
            const p = this.daftarPeriode.find((x) => x.mulai === this.periode);
            return p ? { dari: p.mulai, sampai: p.akhir } : { dari: '', sampai: '' };
        },
        /** Dicek juga di server (422); di sini supaya tidak menembak permintaan yang pasti ditolak. */
        galatRentang() {
            const { dari, sampai } = this.rentang;
            if (!dari || !sampai) return 'Pilih tanggal awal dan akhir.';
            if (dari > sampai) return 'Tanggal awal tidak boleh setelah tanggal akhir.';
            const hari = Math.round((new Date(`${sampai}T00:00:00`) - new Date(`${dari}T00:00:00`)) / 86400000) + 1;
            return hari > this.maksHari ? `Rentang tanggal maksimal ${this.maksHari} hari (dipilih ${hari} hari).` : '';
        },
        rentangValid() {
            return this.galatRentang === '';
        },
        labelPeriode() {
            if (this.periode !== RENTANG_BEBAS) return this.daftarPeriode.find((x) => x.mulai === this.periode)?.label || '';
            const { dari, sampai } = this.rentang;
            return dari && sampai ? `${formatTanggal(dari)} – ${formatTanggal(sampai)}` : 'rentang bebas';
        },
        judulKartu() {
            if (this.tampilan === 'karyawan') return 'Rekap per Karyawan';
            if (this.tampilan === 'grafik') return 'Grafik Kehadiran';
            return `Rekap per ${LABEL_DIMENSI[this.tampilan]}`;
        },
        parameterGrafik() {
            return { ...this.rentang, ...parameterOrganisasi(this.organisasi) };
        },
        labelEkspor() {
            if (this.ekspor.status === 'proses' || this.ekspor.status === 'memulai') return 'Menyiapkan Excel…';
            return 'Ekspor Excel';
        },
        saringanBar() {
            const field = [
                {
                    key: 'periode',
                    label: 'Periode cut-off',
                    type: 'select',
                    icon: 'calendar3',
                    col: 3,
                    clearable: false,
                    options: [
                        ...this.daftarPeriode.map((p) => ({ value: p.mulai, label: p.label })),
                        { value: RENTANG_BEBAS, label: 'Rentang tanggal bebas…' },
                    ],
                },
            ];
            if (this.periode === RENTANG_BEBAS) {
                field.push({ key: 'rentang', label: `Rentang (maks ${this.maksHari} hari)`, type: 'daterange', icon: 'calendar-range', col: 3, clearable: false });
            }
            return [...field, ...fieldOrganisasi(this.opsiSaringan, this.organisasi)];
        },
        nilaiSaringan() {
            const nilai = { periode: this.periode, rentang: this.rentangBebas };
            ORGANISASI.forEach((o) => { nilai[o.key] = this.organisasi[o.key]; });
            return nilai;
        },
    },
    watch: {
        organisasi: {
            deep: true,
            handler() {
                this.page = 1;
                this.muat();
            },
        },
    },
    mounted() {
        this.muat();
    },
    beforeUnmount() {
        clearTimeout(this.pewaktuEkspor);
        this.pewaktuEkspor = null;
    },
    methods: {
        infoKategori: kategori,
        formatMenit,
        formatTanggal,
        nomorTransaksi,
        parameter() {
            return { ...this.rentang, ...parameterOrganisasi(this.organisasi) };
        },
        async muat() {
            // Tampilan grafik memuat datanya sendiri (GrafikRekap) dari parameter yang sama.
            if (!this.rentangValid || this.tampilan === 'grafik') return;
            const nomor = ++this.permintaan;
            this.memuat = true;
            try {
                if (this.tampilan === 'karyawan') {
                    const { data } = await axios.get(`${BASE}/data/rekap`, {
                        params: { ...this.parameter(), page: this.page, limit: this.limit, urut: this.urut, arah: this.arah },
                    });
                    if (nomor !== this.permintaan || !data?.success) return;
                    this.baris = data.result.items || [];
                    this.total = data.result.total || 0;
                    this.diperbarui = data.result.diperbarui || 0;
                } else {
                    const { data } = await axios.get(`${BASE}/data/rekap-kelompok`, {
                        params: { ...this.parameter(), dimensi: this.tampilan },
                    });
                    if (nomor !== this.permintaan || !data?.success) return;
                    this.kelompok = data.result.items || [];
                }
            } catch (e) {
                if (nomor === this.permintaan) hcisMessage.error(e.response?.data?.message || 'Rekap periode gagal dimuat.');
            } finally {
                if (nomor === this.permintaan) this.memuat = false;
            }
        },
        gantiTampilan(v) {
            this.tampilan = v;
            this.$emit('update:tampilan', v);
            this.muat();
        },
        gantiCari(v) {
            this.$emit('update:organisasi', { ...this.organisasi, search: v || '' });
        },
        gantiFilter({ key, value }) {
            if (key === 'periode') {
                this.periode = value || this.daftarPeriode[0]?.mulai || '';
            } else if (key === 'rentang') {
                this.rentangBebas = Array.isArray(value) ? value : ['', ''];
            } else {
                this.$emit('update:organisasi', ubahOrganisasi(this.opsiSaringan, this.organisasi, key, value));
                return;
            }
            this.page = 1;
            this.ekspor = { status: '', id: '', url: '' };
            this.muat();
        },
        gantiUrutan({ key, order }) {
            this.urut = key === 'ketidakhadiran' ? 'alpha' : key;
            this.arah = order === 'asc' ? 'asc' : 'desc';
            this.muat();
        },
        gantiHalaman(p) {
            this.page = p;
            this.muat();
        },
        resetFilter() {
            this.periode = this.daftarPeriode[0]?.mulai || '';
            this.page = 1;
            // Objek baru selalu memicu watcher organisasi, yang memuat ulang.
            this.$emit('update:organisasi', organisasiKosong());
        },
        async bukaRincian(row) {
            if (!row?.kode) return;
            this.drawerTerbuka = true;
            this.memuatRincian = true;
            this.rincian = null;
            try {
                const { data } = await axios.get(`${BASE}/data/rincian/${encodeURIComponent(row.kode)}`, { params: this.rentang });
                this.rincian = data?.success ? data.result : null;
            } catch (e) {
                hcisMessage.error(e.response?.data?.message || 'Rincian harian gagal dimuat.');
                this.drawerTerbuka = false;
            } finally {
                this.memuatRincian = false;
            }
        },
        async mulaiEkspor() {
            if (!this.rentangValid) return;
            clearTimeout(this.pewaktuEkspor);
            this.ekspor = { status: 'memulai', id: '', url: '' };
            try {
                const { data } = await axios.post(`${BASE}/ekspor`, { ...this.parameter(), urut: this.urut, arah: this.arah });
                this.ekspor = { status: 'proses', id: data.result.export_id, url: '' };
                this.pantauEkspor();
            } catch (e) {
                this.ekspor = { status: '', id: '', url: '' };
                hcisMessage.error(e.response?.data?.message || 'Ekspor gagal dimulai.');
            }
        },
        pantauEkspor() {
            this.pewaktuEkspor = setTimeout(async () => {
                const id = this.ekspor.id;
                if (!id) return;
                try {
                    const { data } = await axios.get(`${BASE}/ekspor/${encodeURIComponent(id)}`);
                    if (id !== this.ekspor.id) return;
                    const st = data?.result?.status;
                    if (st === 'ready') {
                        this.ekspor = { status: 'siap', id, url: `${BASE}/ekspor/${encodeURIComponent(id)}/unduh` };
                        hcisMessage.success('Berkas Excel siap diunduh.');
                    } else if (st === 'failed' || st === 'expired') {
                        this.ekspor = { status: '', id: '', url: '' };
                        hcisMessage.error(data?.result?.error_message || 'Ekspor gagal diproses.');
                    } else {
                        this.pantauEkspor();
                    }
                } catch (e) {
                    this.ekspor = { status: '', id: '', url: '' };
                    hcisMessage.error(e.response?.data?.message || 'Status ekspor gagal dibaca.');
                }
            }, JEDA_POLL_EKSPOR);
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

.mk-karyawan,
.mk-tumpuk {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.mk-karyawan__nama {
    font-weight: 650;
    color: var(--hcis-text-strong);
}

.mk-muted {
    font-size: 0.75rem;
    color: var(--hcis-text-muted);
}

.mk-lencana {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.mk-angka--perhatian {
    font-weight: 650;
    color: var(--hcis-warning-600, var(--hcis-text-strong));
}

.mk-angka--bahaya {
    font-weight: 650;
    color: var(--hcis-danger-600, var(--hcis-text-strong));
}
</style>

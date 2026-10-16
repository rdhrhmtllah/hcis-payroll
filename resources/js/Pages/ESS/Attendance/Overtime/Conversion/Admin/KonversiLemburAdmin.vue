<template>
    <div class="kla-page">
        <HcisHeader
            title="Konversi Lembur"
            kicker="Administrator HCIS · Pantau Transaksi"
            subtitle="Seluruh pengajuan konversi lembur karyawan — pantau statusnya, periksa jejak persetujuan, dan batalkan bila perlu."
            icon="arrow-left-right"
            sentiment="brand"
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
        </HcisHeader>

        <!-- Ringkasan: kartu bisa diklik untuk langsung menyaring -->
        <HcisStatGroup :cols="4" gap="sm" appearance="crystal" class="kla-stats">
            <HcisStatCard
                v-for="kartu in kartuRingkasan"
                :key="kartu.status"
                :label="kartu.label"
                :value="kartu.nilai"
                :suffix="kartu.satuan"
                :icon="kartu.ikon"
                :sentiment="kartu.sentiment"
                :caption="kartu.keterangan"
                :loading="memuat && !sudahDimuat"
                interactive
                @click="pilihStatus(kartu.status)"
            />
        </HcisStatGroup>

        <HcisCard
            title="Daftar Pengajuan"
            description="Klik baris untuk melihat detail dan jejak persetujuan."
            icon="list-check"
            sentiment="brand"
            variant="glass"
            divider
        >
            <HcisFilterBar
                :search="saring.search"
                search-placeholder="Cari nama karyawan, kode, atau nomor transaksi…"
                :filters="saringanBar"
                :filter-values="nilaiSaringan"
                :quick-chips="chipStatus"
                :active-chip-value="saring.status"
                :results-count="total"
                sentiment="brand"
                variant="flat"
                show-active-tags
                class="mb-3"
                @update:search="gantiCari"
                @filter-change="gantiFilter"
                @chip-click="pilihStatus($event?.value ?? '')"
                @reset="resetFilter"
            />

            <HcisTable
                :columns="KOLOM"
                :rows="baris"
                row-key="no_transaksi"
                :loading="memuat"
                sentiment="brand"
                dense
                interactive
                remote-sort
                :sort-key="saring.urut === 'tanggal_lembur' ? 'tanggal' : (saring.urut === 'durasi' ? 'durasi' : 'diajukan')"
                :sort-order="saring.arah"
                :empty-title="saring.search ? 'Tidak ada yang cocok' : 'Belum ada pengajuan pada saringan ini'"
                empty-description="Ubah kata kunci, status, atau rentang tanggal lembur."
                @row-click="bukaDetail($event?.row ?? $event)"
                @sort="gantiUrutan"
            >
                <template #cell-karyawan="{ row }">
                    <div class="kla-karyawan">
                        <span class="kla-karyawan__nama">{{ row.nama }}</span>
                        <span class="kla-karyawan__meta">{{ row.kode_karyawan }}<template v-if="row.jabatan"> · {{ row.jabatan }}</template></span>
                        <span v-if="row.departemen || row.lokasi" class="kla-karyawan__meta">{{ [row.departemen, row.lokasi].filter(Boolean).join(' · ') }}</span>
                    </div>
                </template>

                <template #cell-tanggal="{ row }">
                    <div class="kla-tanggal">
                        <span class="font-tabular">{{ formatTanggal(row.tanggal_lembur) }}</span>
                        <span v-if="row.jam_masuk" class="kla-muted font-tabular">{{ row.jam_masuk }}–{{ row.jam_keluar }}</span>
                    </div>
                </template>

                <template #cell-durasi="{ row }">
                    <span class="font-tabular fw-semibold">{{ formatJam(row.durasi_jam) }}</span>
                </template>

                <template #cell-jenis="{ row }">
                    <div class="kla-jenis">
                        <HcisBadge
                            :label="jenis(row.jenis_konversi).label"
                            :icon="jenis(row.jenis_konversi).ikon"
                            :variant="jenis(row.jenis_konversi).variant"
                            size="xs"
                            appearance="subtle"
                        />
                        <span class="kla-muted">{{ ringkasJenis(row) }}</span>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <HcisBadge
                        :label="status(row.status).label"
                        :variant="status(row.status).variant"
                        size="xs"
                        appearance="subtle"
                        dot
                    />
                </template>

                <template #cell-diajukan="{ row }">
                    <span class="kla-muted font-tabular">{{ formatWaktu(row.diajukan_pada) }}</span>
                </template>

                <template #cell-aksi="{ row }">
                    <div class="kla-aksi">
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            leading-icon="eye"
                            title="Lihat detail"
                            @click.stop="bukaDetail(row)"
                        />
                        <HcisButton
                            v-if="row.boleh_batal"
                            variant="danger-quiet"
                            size="xs"
                            leading-icon="x-circle"
                            title="Batalkan pengajuan"
                            @click.stop="mintaBatal(row)"
                        />
                    </div>
                </template>

                <template #footer>
                    <HcisPagination
                        v-if="total > saring.limit"
                        :model-value="saring.page"
                        :page-size="saring.limit"
                        :total="total"
                        @update:model-value="gantiHalaman"
                    />
                </template>
            </HcisTable>
        </HcisCard>

        <!-- Detail -->
        <HcisDrawer
            v-model="drawerTerbuka"
            :title="detail ? detail.nama : 'Detail Pengajuan'"
            :description="detail ? `${detail.no_transaksi} · diajukan ${formatWaktu(detail.diajukan_pada)}` : ''"
            icon="arrow-left-right"
            size="34rem"
        >
            <HcisSkeleton v-if="memuatDetail" height="14rem" rounded="md" />

            <div v-else-if="detail" class="kla-detail">
                <div class="kla-detail__status">
                    <HcisBadge
                        :label="status(detail.status).label"
                        :variant="status(detail.status).variant"
                        size="sm"
                        appearance="subtle"
                        dot
                    />
                    <HcisBadge
                        :label="jenis(detail.jenis_konversi).label"
                        :icon="jenis(detail.jenis_konversi).ikon"
                        :variant="jenis(detail.jenis_konversi).variant"
                        size="sm"
                        appearance="subtle"
                    />
                </div>

                <HcisAlert
                    v-if="detail.catatan_keputusan"
                    :variant="detail.status === 'DIBATALKAN' ? 'neutral' : 'danger'"
                    appearance="soft"
                    size="sm"
                    :icon="detail.status === 'DIBATALKAN' ? 'slash-circle' : 'x-octagon'"
                    :title="detail.status === 'DIBATALKAN' ? 'Dibatalkan' : 'Ditolak'"
                    :description="`${detail.catatan_keputusan}${detail.diubah_oleh ? ` — oleh ${detail.diubah_oleh}, ${formatWaktu(detail.diubah_pada)}` : ''}`"
                />

                <dl class="kla-info">
                    <div><dt>Karyawan</dt><dd>{{ detail.nama }} <span class="kla-muted">({{ detail.kode_karyawan }})</span></dd></div>
                    <div><dt>Divisi</dt><dd>{{ detail.divisi || '—' }}</dd></div>
                    <div><dt>Departemen</dt><dd>{{ detail.departemen || '—' }}</dd></div>
                    <div><dt>Level · Jabatan</dt><dd>{{ [detail.level, detail.jabatan].filter(Boolean).join(' · ') || '—' }}</dd></div>
                    <div><dt>Lokasi kerja</dt><dd>{{ detail.lokasi || '—' }}</dd></div>
                    <div><dt>Tanggal lembur</dt><dd class="font-tabular">{{ formatTanggal(detail.tanggal_lembur) }}</dd></div>
                    <div><dt>Jam dipilih</dt><dd class="font-tabular">{{ detail.jam_masuk || '—' }} – {{ detail.jam_keluar || '—' }}</dd></div>
                    <div><dt>Durasi</dt><dd class="font-tabular">{{ formatJam(detail.durasi_jam) }}</dd></div>
                    <div><dt>Hasil konversi</dt><dd>{{ ringkasJenis(detail) }}</dd></div>
                </dl>

                <div v-if="detail.catatan_karyawan" class="kla-blok">
                    <span class="kla-blok__judul">Catatan karyawan</span>
                    <p class="kla-blok__isi">{{ detail.catatan_karyawan }}</p>
                </div>

                <HcisButton
                    v-if="detail.lampiran_url"
                    variant="secondary"
                    size="sm"
                    leading-icon="paperclip"
                    label="Buka lampiran"
                    @click="bukaLampiran(detail.lampiran_url)"
                />

                <HcisTimeline
                    v-if="langkahTimeline.length"
                    title="Jejak persetujuan"
                    :items="langkahTimeline"
                    variant="flat"
                    sentiment="brand"
                />
                <p v-else class="kla-muted">Pengajuan ini tidak membutuhkan persetujuan.</p>
            </div>

            <template v-if="detail && detail.boleh_batal" #footer>
                <HcisButton
                    variant="danger"
                    size="sm"
                    leading-icon="x-circle"
                    label="Batalkan Pengajuan"
                    class="w-100 justify-content-center"
                    @click="mintaBatal(detail)"
                />
            </template>
        </HcisDrawer>

        <!-- Konfirmasi batal: dampaknya disebutkan sebelum admin menekan tombol -->
        <HcisDialog
            v-model="dialogBatal"
            title="Batalkan pengajuan konversi lembur?"
            kicker="Administrator HCIS · Pembatalan"
            sentiment="danger"
            icon="exclamation-triangle-fill"
            size="md"
            sticky-footer
            :close-on-overlay="!membatalkan"
            :close-on-esc="!membatalkan"
        >
            <div v-if="target" class="kla-batal">
                <p class="kla-batal__subjek">
                    <strong>{{ target.nama }}</strong> · {{ target.no_transaksi }} ·
                    lembur {{ formatTanggal(target.tanggal_lembur) }} ({{ formatJam(target.durasi_jam) }})
                </p>

                <div class="kla-blok">
                    <span class="kla-blok__judul">Yang akan terjadi</span>
                    <ul class="kla-dampak">
                        <li v-for="(d, i) in dampakBatal" :key="i">{{ d }}</li>
                    </ul>
                </div>

                <HcisAlert
                    v-if="saldoCuti"
                    variant="warning"
                    appearance="soft"
                    size="sm"
                    icon="wallet2"
                    title="Saldo cuti ikut dikoreksi"
                    :description="ringkasSaldo"
                />

                <HcisTextarea
                    id="kla-alasan"
                    v-model="alasan"
                    label="Alasan pembatalan"
                    placeholder="Contoh: salah pilih jenis konversi, lembur tidak disetujui atasan secara lisan…"
                    :maxlength="180"
                    :rows="3"
                    :error="galatAlasan"
                    hint="Tampil di riwayat pengajuan karyawan."
                />
            </div>

            <template #footer>
                <div class="kla-dialog-aksi">
                    <HcisButton label="Kembali" variant="secondary" size="sm" :disabled="membatalkan" @click="dialogBatal = false" />
                    <HcisButton
                        label="Ya, Batalkan"
                        variant="danger"
                        size="sm"
                        leading-icon="x-circle"
                        :loading="membatalkan"
                        :disabled="alasan.trim().length < 5"
                        @click="konfirmasiBatal"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import axios from 'axios';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisDrawer,
    HcisFilterBar,
    HcisHeader,
    HcisPagination,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisTable,
    HcisTextarea,
    HcisTimeline,
    hcisMessage,
} from '@/components/ui';

const BASE = '/admin/konversi-lembur';

const STATUS = {
    MENUNGGU: { label: 'Menunggu', variant: 'warning' },
    DISETUJUI: { label: 'Disetujui', variant: 'info' },
    DITERAPKAN: { label: 'Diterapkan', variant: 'success' },
    DITOLAK: { label: 'Ditolak', variant: 'danger' },
    DIBATALKAN: { label: 'Dibatalkan', variant: 'neutral' },
};

const JENIS = {
    DAY_OFF: { label: 'Libur Pengganti', ikon: 'calendar-check', variant: 'brand' },
    MATERIAL: { label: 'Uang Lembur', ikon: 'cash-coin', variant: 'success' },
    TO_CUTI: { label: 'Saldo Cuti', ikon: 'airplane', variant: 'purple' },
};

const KOLOM = [
    { key: 'karyawan', label: 'Karyawan', minWidth: '210px' },
    { key: 'tanggal', label: 'Tanggal Lembur', width: '150px', sortable: true },
    { key: 'durasi', label: 'Durasi', width: '90px', align: 'right', sortable: true },
    { key: 'jenis', label: 'Konversi', minWidth: '190px' },
    { key: 'status', label: 'Status', width: '120px' },
    { key: 'diajukan', label: 'Diajukan', width: '140px', sortable: true },
    { key: 'aksi', label: '', width: '90px', align: 'right' },
];

// Saringan organisasi multi-pilih; nilai dicocokkan persis di server.
const ORGANISASI = [
    { key: 'divisi', label: 'Divisi', icon: 'diagram-3', placeholder: 'Semua divisi' },
    { key: 'departemen', label: 'Departemen', icon: 'diagram-2', placeholder: 'Semua departemen' },
    { key: 'level', label: 'Level', icon: 'bar-chart-steps', placeholder: 'Semua level' },
    { key: 'jabatan', label: 'Jabatan', icon: 'person-badge', placeholder: 'Semua jabatan' },
    { key: 'lokasi', label: 'Lokasi kerja', icon: 'geo-alt', placeholder: 'Semua lokasi' },
];

const KOSONG_ORGANISASI = () => Object.fromEntries(ORGANISASI.map((o) => [o.key, []]));

const URUT_KOLOM = { tanggal: 'tanggal_lembur', durasi: 'durasi', diajukan: 'dibuat' };

export default {
    name: 'KonversiLemburAdmin',
    props: {
        opsiSaringan: { type: Object, default: () => ({}) },
    },
    components: {
        HcisAlert,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisDialog,
        HcisDrawer,
        HcisFilterBar,
        HcisHeader,
        HcisPagination,
        HcisSkeleton,
        HcisStatCard,
        HcisStatGroup,
        HcisTable,
        HcisTextarea,
        HcisTimeline,
    },
    data() {
        return {
            KOLOM,
            memuat: false,
            sudahDimuat: false,
            baris: [],
            total: 0,
            stats: {},
            saring: { search: '', status: '', jenis: '', tanggal: ['', ''], ...KOSONG_ORGANISASI(), page: 1, limit: 15, urut: 'dibuat', arah: 'desc' },
            drawerTerbuka: false,
            memuatDetail: false,
            detail: null,
            dialogBatal: false,
            target: null,
            alasan: '',
            galatAlasan: '',
            membatalkan: false,
        };
    },
    computed: {
        /** Departemen yang masuk akal untuk divisi terpilih (semua bila divisi kosong). */
        departemenTersedia() {
            const semua = this.opsiSaringan?.departemen || [];
            const divisi = this.saring.divisi;
            const cocok = divisi.length ? semua.filter((d) => divisi.includes(d.divisi)) : semua;
            return [...new Set(cocok.map((d) => d.nama))];
        },
        saringanBar() {
            const opsi = (daftar) => (daftar || []).map((v) => ({ value: v, label: v }));
            return [
                {
                    key: 'jenis',
                    label: 'Jenis konversi',
                    type: 'select',
                    icon: 'arrow-left-right',
                    placeholder: 'Semua jenis',
                    col: 3,
                    options: Object.entries(JENIS).map(([value, j]) => ({ value, label: j.label })),
                },
                { key: 'tanggal', label: 'Tanggal lembur', type: 'daterange', icon: 'calendar-range', col: 3 },
                ...ORGANISASI.map((o) => ({
                    ...o,
                    type: 'select',
                    multiple: true,
                    col: 3,
                    options: opsi(o.key === 'departemen' ? this.departemenTersedia : this.opsiSaringan?.[o.key]),
                })),
            ];
        },
        nilaiSaringan() {
            const nilai = { jenis: this.saring.jenis, tanggal: this.saring.tanggal };
            ORGANISASI.forEach((o) => { nilai[o.key] = this.saring[o.key]; });
            return nilai;
        },
        kartuRingkasan() {
            const s = this.stats;
            return [
                { status: 'MENUNGGU', label: 'Menunggu persetujuan', nilai: s.menunggu ?? 0, satuan: 'Pengajuan', ikon: 'hourglass-split', sentiment: 'warning', keterangan: 'Belum diputus atasan' },
                { status: 'DITERAPKAN', label: 'Sudah diterapkan', nilai: s.diterapkan ?? 0, satuan: 'Pengajuan', ikon: 'check2-circle', sentiment: 'success', keterangan: `${this.formatJam(s.jam_diterapkan ?? 0)} lembur terkonversi` },
                { status: 'DITOLAK', label: 'Ditolak', nilai: s.ditolak ?? 0, satuan: 'Pengajuan', ikon: 'x-octagon', sentiment: 'danger', keterangan: 'Diputus tidak disetujui' },
                { status: 'DIBATALKAN', label: 'Dibatalkan', nilai: s.dibatalkan ?? 0, satuan: 'Pengajuan', ikon: 'slash-circle', sentiment: 'neutral', keterangan: 'Oleh karyawan atau admin' },
            ];
        },
        chipStatus() {
            const s = this.stats;
            return [
                { label: `Semua (${s.total ?? 0})`, value: '' },
                ...Object.entries(STATUS).map(([value, st]) => ({ label: `${st.label} (${s[value.toLowerCase()] ?? 0})`, value })),
            ];
        },
        langkahTimeline() {
            const LANGKAH = {
                approved: { label: 'Disetujui', sentiment: 'success' },
                rejected: { label: 'Ditolak', sentiment: 'danger' },
                pending: { label: 'Menunggu keputusan', sentiment: 'warning' },
                upcoming: { label: 'Belum sampai giliran', sentiment: 'neutral' },
            };
            return (this.detail?.approval_steps || []).map((l, i) => {
                const st = LANGKAH[String(l.status || '').toLowerCase()] || LANGKAH.upcoming;
                return {
                    id: i,
                    title: l.actor || l.step,
                    description: [l.step, l.note].filter(Boolean).join(' · '),
                    time: l.approved_at || '',
                    status: st.label,
                    sentiment: st.sentiment,
                };
            });
        },
        saldoCuti() {
            const d = this.target?.dampak_saldo_cuti;
            if (!d || d.already) return null;
            const totalNilai = (d.hutang_dipulihkan || 0) + (d.jatah_ditarik || 0) + (d.hutang_baru || 0);
            return totalNilai > 0 ? d : null;
        },
        ringkasSaldo() {
            const d = this.saldoCuti;
            if (!d) return '';
            const bagian = [];
            if (d.jatah_ditarik) bagian.push(`${d.jatah_ditarik} hari jatah Cuti Tahunan ditarik kembali`);
            if (d.hutang_dipulihkan) bagian.push(`${d.hutang_dipulihkan} hari hutang cuti yang sempat dilunasi dikembalikan`);
            if (d.hutang_baru) bagian.push(`${d.hutang_baru} hari sudah terpakai dan menjadi hutang cuti`);
            return `${bagian.join('; ')}.`;
        },
        dampakBatal() {
            const t = this.target;
            if (!t) return [];
            const hasil = ['Status pengajuan berubah menjadi Dibatalkan dan tidak bisa diaktifkan kembali.'];
            if (t.status === 'MENUNGGU' || t.status === 'DISETUJUI') {
                hasil.push('Persetujuan yang masih menunggu ditutup; atasan tidak perlu memutusnya lagi.');
            }
            if (t.status === 'DITERAPKAN') {
                if (t.jenis_konversi === 'DAY_OFF') hasil.push(`Hari libur pengganti${t.tanggal_off ? ` (${this.formatTanggal(t.tanggal_off)})` : ''} dicabut; hari itu kembali menjadi hari kerja.`);
                if (t.jenis_konversi === 'MATERIAL') hasil.push('Lembur tidak lagi dihitung sebagai uang lembur pada pemrosesan absensi berikutnya.');
                if (t.jenis_konversi === 'TO_CUTI') hasil.push('Saldo cuti yang dikreditkan dari lembur ini dikoreksi (rinciannya di bawah).');
                hasil.push('Absensi karyawan dihitung ulang otomatis.');
            }
            hasil.push('Karyawan melihat status Dibatalkan beserta alasannya di riwayat pengajuannya (tanpa notifikasi WhatsApp).');
            return hasil;
        },
    },
    mounted() {
        this.muat();
    },
    methods: {
        status(s) {
            return STATUS[s] || { label: s || '—', variant: 'neutral' };
        },
        jenis(j) {
            return JENIS[j] || JENIS.DAY_OFF;
        },
        ringkasJenis(row) {
            if (row.jenis_konversi === 'DAY_OFF') return row.tanggal_off ? `Libur ${this.formatTanggal(row.tanggal_off)}` : 'Tanggal libur belum ditentukan';
            if (row.jenis_konversi === 'TO_CUTI') return row.hari_dikonversi ? `${row.hari_dikonversi} hari saldo cuti` : 'Menjadi saldo cuti';
            return 'Dibayar sebagai uang lembur';
        },
        formatTanggal(v) {
            if (!v) return '—';
            const d = new Date(`${String(v).slice(0, 10)}T00:00:00`);
            return Number.isNaN(d.getTime()) ? v : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        formatWaktu(v) {
            if (!v) return '—';
            const d = new Date(String(v).replace(' ', 'T'));
            return Number.isNaN(d.getTime()) ? v : d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        formatJam(v) {
            const n = Number(v) || 0;
            return `${n.toLocaleString('id-ID', { maximumFractionDigits: 2 })} jam`;
        },
        parameter() {
            const [dari, sampai] = this.saring.tanggal || ['', ''];
            return {
                search: this.saring.search,
                status: this.saring.status,
                jenis: this.saring.jenis,
                tanggal_dari: dari || '',
                tanggal_sampai: sampai || '',
                ...Object.fromEntries(ORGANISASI.map((o) => [o.key, this.saring[o.key]])),
                page: this.saring.page,
                limit: this.saring.limit,
                urut: this.saring.urut,
                arah: this.saring.arah,
            };
        },
        async muat() {
            this.memuat = true;
            try {
                const { data } = await axios.get(`${BASE}/data`, { params: this.parameter() });
                if (data?.success) {
                    this.baris = data.result.list.items || [];
                    this.total = data.result.list.total || 0;
                    this.stats = data.result.stats || {};
                    this.sudahDimuat = true;
                }
            } catch (e) {
                hcisMessage.error(e.response?.data?.message || 'Daftar konversi lembur gagal dimuat.');
            } finally {
                this.memuat = false;
            }
        },
        pilihStatus(s) {
            this.saring.status = this.saring.status === s && s !== '' ? '' : s;
            this.saring.page = 1;
            this.muat();
        },
        gantiCari(v) {
            this.saring.search = v || '';
            this.saring.page = 1;
            this.muat();
        },
        gantiFilter({ key, value }) {
            if (key === 'tanggal') this.saring.tanggal = Array.isArray(value) ? value : ['', ''];
            else if (ORGANISASI.some((o) => o.key === key)) this.saring[key] = Array.isArray(value) ? value : (value ? [value] : []);
            else this.saring[key] = value ?? '';
            if (key === 'divisi') {
                // Departemen yang tak lagi di bawah divisi terpilih akan menyaring ke hasil kosong.
                const boleh = this.departemenTersedia;
                this.saring.departemen = this.saring.departemen.filter((d) => boleh.includes(d));
            }
            this.saring.page = 1;
            this.muat();
        },
        gantiUrutan({ key, order }) {
            this.saring.urut = URUT_KOLOM[key] || 'dibuat';
            this.saring.arah = order === 'asc' ? 'asc' : 'desc';
            this.muat();
        },
        gantiHalaman(p) {
            this.saring.page = p;
            this.muat();
        },
        resetFilter() {
            this.saring = { ...this.saring, search: '', status: '', jenis: '', tanggal: ['', ''], ...KOSONG_ORGANISASI(), page: 1 };
            this.muat();
        },
        async ambilDetail(no) {
            const { data } = await axios.get(`${BASE}/detail/${encodeURIComponent(no)}`);
            return data?.success ? data.result : null;
        },
        async bukaDetail(row) {
            if (!row?.no_transaksi) return;
            this.drawerTerbuka = true;
            this.memuatDetail = true;
            this.detail = null;
            try {
                this.detail = await this.ambilDetail(row.no_transaksi);
            } catch (e) {
                hcisMessage.error(e.response?.data?.message || 'Detail pengajuan gagal dimuat.');
                this.drawerTerbuka = false;
            } finally {
                this.memuatDetail = false;
            }
        },
        async mintaBatal(row) {
            try {
                // Detail dimuat ulang: pratinjau saldo cuti harus berdasarkan saldo TERKINI.
                this.target = await this.ambilDetail(row.no_transaksi);
            } catch (e) {
                hcisMessage.error(e.response?.data?.message || 'Data pengajuan gagal dimuat.');
                return;
            }
            if (!this.target?.boleh_batal) {
                hcisMessage.warning('Pengajuan ini sudah tidak bisa dibatalkan.');
                this.muat();
                return;
            }
            this.alasan = '';
            this.galatAlasan = '';
            this.dialogBatal = true;
        },
        async konfirmasiBatal() {
            if (this.alasan.trim().length < 5) {
                this.galatAlasan = 'Alasan minimal 5 karakter.';
                return;
            }
            this.membatalkan = true;
            try {
                const { data } = await axios.post(`${BASE}/batal/${encodeURIComponent(this.target.no_transaksi)}`, { alasan: this.alasan.trim() });
                hcisMessage.success(data?.message || 'Pengajuan dibatalkan.');
                this.dialogBatal = false;
                this.drawerTerbuka = false;
                this.muat();
            } catch (e) {
                const pesan = e.response?.data?.message || 'Pembatalan gagal diproses.';
                if (e.response?.status === 422) this.galatAlasan = pesan;
                else hcisMessage.error(pesan);
                if (e.response?.status === 409) {
                    this.dialogBatal = false;
                    this.muat();
                }
            } finally {
                this.membatalkan = false;
            }
        },
        bukaLampiran(url) {
            window.open(url, '_blank', 'noopener');
        },
    },
};
</script>

<style scoped>
.kla-page {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}

.kla-karyawan,
.kla-tanggal,
.kla-jenis {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.kla-karyawan__nama {
    font-weight: 650;
    color: var(--hcis-text-strong);
}

.kla-karyawan__meta,
.kla-muted {
    font-size: 0.75rem;
    color: var(--hcis-text-muted);
}

.kla-aksi,
.kla-dialog-aksi {
    display: flex;
    justify-content: flex-end;
    gap: 0.35rem;
    width: 100%;
}

.kla-detail {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kla-detail__status {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.kla-info {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem 1rem;
    margin: 0;
}

.kla-info dt {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-text-muted);
}

.kla-info dd {
    margin: 0.1rem 0 0;
    font-size: 0.85rem;
    color: var(--hcis-text-strong);
}

.kla-blok__judul {
    display: block;
    margin-bottom: 0.3rem;
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-text-strong);
}

.kla-blok__isi {
    margin: 0;
    font-size: 0.85rem;
    color: var(--hcis-text-muted);
    white-space: pre-line;
}

.kla-batal {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.kla-batal__subjek {
    margin: 0;
    font-size: 0.85rem;
    color: var(--hcis-text-muted);
}

.kla-dampak {
    margin: 0;
    padding-left: 1.1rem;
    font-size: 0.82rem;
    color: var(--hcis-text-strong);
}

.kla-dampak li + li {
    margin-top: 0.25rem;
}

@media (max-width: 640px) {
    .kla-info {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>

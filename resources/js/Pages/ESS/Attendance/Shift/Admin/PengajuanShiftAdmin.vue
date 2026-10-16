<template>
    <div class="ssa-page">
        <HcisHeader
            title="Pengajuan Shift"
            kicker="Administrator HCIS · Pantau Perubahan Jadwal"
            subtitle="Ganti shift, tukar hari, dan status hari — dari karyawan, leader, maupun admin. Pantau statusnya, periksa tiap tanggal, dan batalkan yang belum diterapkan."
            icon="calendar2-week"
            sentiment="brand"
        >
            <template #actions>
                <HcisButton
                    variant="secondary"
                    size="sm"
                    leading-icon="calendar-range"
                    label="Buka Shift Admin"
                    @click="bukaShiftAdmin"
                />
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
        <HcisStatGroup :cols="5" gap="sm" appearance="crystal">
            <HcisStatCard
                v-for="kartu in kartuRingkasan"
                :key="kartu.status"
                :label="kartu.label"
                :value="kartu.nilai"
                suffix="Pengajuan"
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
            description="Klik baris untuk melihat tiap tanggal dan jejak persetujuan."
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
                row-key="kunci"
                :loading="memuat"
                sentiment="brand"
                dense
                interactive
                remote-sort
                :sort-key="saring.urut === 'tanggal' ? 'tanggal' : 'diajukan'"
                :sort-order="saring.arah"
                :empty-title="saring.search ? 'Tidak ada yang cocok' : 'Belum ada pengajuan pada saringan ini'"
                empty-description="Ubah kata kunci, status, atau rentang tanggal."
                @row-click="bukaDetail($event?.row ?? $event)"
                @sort="gantiUrutan"
            >
                <template #cell-karyawan="{ row }">
                    <div class="ssa-tumpuk">
                        <span class="ssa-nama">{{ row.nama }}</span>
                        <span class="ssa-muted">{{ row.kode_karyawan }}<template v-if="row.jabatan"> · {{ row.jabatan }}</template></span>
                        <span v-if="row.departemen || row.lokasi" class="ssa-muted">{{ [row.departemen, row.lokasi].filter(Boolean).join(' · ') }}</span>
                    </div>
                </template>

                <template #cell-jenis="{ row }">
                    <div class="ssa-tumpuk">
                        <HcisBadge
                            :label="jenis(row.jenis).label"
                            :icon="jenis(row.jenis).ikon"
                            :variant="jenis(row.jenis).variant"
                            size="xs"
                            appearance="subtle"
                        />
                        <span class="ssa-muted">{{ labelPengaju(row) }}</span>
                    </div>
                </template>

                <template #cell-tanggal="{ row }">
                    <div class="ssa-tumpuk">
                        <span class="font-tabular">{{ ringkasTanggal(row) }}</span>
                        <span v-if="row.jenis !== 'TUKAR_HARI'" class="ssa-muted">
                            {{ row.jumlah_tanggal }} tanggal<template v-if="row.target"> · {{ labelTarget(row) }}</template>
                        </span>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <div class="ssa-tumpuk">
                        <HcisBadge
                            :label="status(row.status).label"
                            :variant="status(row.status).variant"
                            size="xs"
                            appearance="subtle"
                            dot
                        />
                        <span v-if="row.jumlah_tanggal > 1 && row.status !== 'DIBATALKAN'" class="ssa-muted font-tabular">
                            {{ row.jumlah_diterapkan }}/{{ row.jumlah_tanggal }} diterapkan
                        </span>
                    </div>
                </template>

                <template #cell-diajukan="{ row }">
                    <span class="ssa-muted font-tabular">{{ formatWaktu(row.diajukan_pada) }}</span>
                </template>

                <template #cell-aksi="{ row }">
                    <div class="ssa-aksi">
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
                            title="Batalkan yang belum diterapkan"
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
            icon="calendar2-week"
            size="36rem"
        >
            <HcisSkeleton v-if="memuatDetail" height="14rem" rounded="md" />

            <div v-else-if="detail" class="ssa-detail">
                <div class="ssa-baris-lencana">
                    <HcisBadge :label="status(detail.status).label" :variant="status(detail.status).variant" size="sm" appearance="subtle" dot />
                    <HcisBadge :label="jenis(detail.jenis).label" :icon="jenis(detail.jenis).ikon" :variant="jenis(detail.jenis).variant" size="sm" appearance="subtle" />
                    <HcisBadge v-if="detail.target" :label="labelTarget(detail)" variant="neutral" size="sm" appearance="outline" />
                </div>

                <HcisAlert
                    v-if="detail.catatan_batal_admin"
                    variant="neutral"
                    appearance="soft"
                    size="sm"
                    icon="slash-circle"
                    :title="detail.status === 'DIBATALKAN' ? 'Dibatalkan admin' : 'Sebagian tanggal dibatalkan admin'"
                    :description="detail.catatan_batal_admin.replace(PENANDA_BATAL, '')"
                />
                <HcisAlert
                    v-else-if="detail.catatan_tolak"
                    variant="danger"
                    appearance="soft"
                    size="sm"
                    icon="x-octagon"
                    title="Catatan penolakan"
                    :description="detail.catatan_tolak"
                />

                <dl class="ssa-info">
                    <div><dt>Karyawan</dt><dd>{{ detail.nama }} <span class="ssa-muted">({{ detail.kode_karyawan }})</span></dd></div>
                    <div><dt>Divisi</dt><dd>{{ detail.divisi || '—' }}</dd></div>
                    <div><dt>Departemen</dt><dd>{{ detail.departemen || '—' }}</dd></div>
                    <div><dt>Level · Jabatan</dt><dd>{{ [detail.level, detail.jabatan].filter(Boolean).join(' · ') || '—' }}</dd></div>
                    <div><dt>Lokasi kerja</dt><dd>{{ detail.lokasi || '—' }}</dd></div>
                    <div><dt>Diajukan oleh</dt><dd>{{ labelPengaju(detail) }}</dd></div>
                    <div><dt>Terakhir diubah</dt><dd class="font-tabular">{{ formatWaktu(detail.diubah_pada) }}</dd></div>
                </dl>

                <div v-if="detail.alasan" class="ssa-blok">
                    <span class="ssa-blok__judul">Alasan pengajuan</span>
                    <p class="ssa-blok__isi">{{ detail.alasan }}</p>
                </div>

                <div class="ssa-blok">
                    <span class="ssa-blok__judul">{{ detail.jenis === 'TUKAR_HARI' ? 'Hari yang ditukar' : 'Tanggal yang diajukan' }}</span>
                    <ul class="ssa-tanggal">
                        <li v-for="(t, i) in detail.tanggal || []" :key="i" class="ssa-tanggal__baris">
                            <div class="ssa-tumpuk">
                                <template v-if="detail.jenis === 'TUKAR_HARI'">
                                    <span class="font-tabular">Masuk {{ formatTanggal(t.tanggal_bekerja) }}<template v-if="t.shift_bekerja"> ({{ t.shift_bekerja }})</template></span>
                                    <span class="ssa-muted font-tabular">Libur {{ formatTanggal(t.tanggal_off) }}</span>
                                </template>
                                <template v-else-if="detail.jenis === 'STATUS_HARI'">
                                    <span class="font-tabular">{{ formatTanggal(t.tanggal) }}</span>
                                    <span class="ssa-muted">Menjadi <strong>{{ labelStatusHari(t.status_hari) }}</strong></span>
                                </template>
                                <template v-else>
                                    <span class="font-tabular">{{ formatTanggal(t.tanggal) }}</span>
                                    <span class="ssa-muted">{{ t.shift_lama || '—' }} → <strong>{{ t.shift_baru || '—' }}</strong></span>
                                </template>
                                <span v-if="t.catatan" class="ssa-muted">{{ t.catatan }}</span>
                            </div>
                            <div class="ssa-tanggal__aksi">
                                <HcisBadge :label="statusTanggal(t.status).label" :variant="statusTanggal(t.status).variant" size="xs" appearance="subtle" dot />
                                <template v-if="detail.jenis === 'TUKAR_HARI'">
                                    <HcisButton variant="ghost" size="xs" leading-icon="clock-history" label="Masuk" title="Riwayat jadwal hari masuk" @click="bukaJejak(t.tanggal_bekerja)" />
                                    <HcisButton variant="ghost" size="xs" leading-icon="clock-history" label="Libur" title="Riwayat jadwal hari libur" @click="bukaJejak(t.tanggal_off)" />
                                </template>
                                <HcisButton v-else variant="ghost" size="xs" leading-icon="clock-history" title="Lihat riwayat jadwal tanggal ini" @click="bukaJejak(t.tanggal)" />
                            </div>
                        </li>
                    </ul>
                </div>

                <HcisTimeline
                    v-if="langkahTimeline.length"
                    title="Jejak persetujuan"
                    :items="langkahTimeline"
                    variant="flat"
                    sentiment="brand"
                />
                <p v-else class="ssa-muted">Pengajuan ini tidak membutuhkan persetujuan.</p>
            </div>

            <template v-if="detail && (detail.boleh_batal || detail.jumlah_diterapkan > 0)" #footer>
                <div class="ssa-kaki">
                    <HcisButton
                        v-if="detail.jumlah_diterapkan > 0"
                        variant="secondary"
                        size="sm"
                        leading-icon="calendar-range"
                        label="Ubah jadwal di Shift Admin"
                        @click="bukaShiftAdmin"
                    />
                    <HcisButton
                        v-if="detail.boleh_batal"
                        variant="danger"
                        size="sm"
                        leading-icon="x-circle"
                        :label="detail.jumlah_diterapkan > 0 ? 'Batalkan yang belum diterapkan' : 'Batalkan Pengajuan'"
                        @click="mintaBatal(detail)"
                    />
                </div>
            </template>
        </HcisDrawer>

        <!-- Riwayat jadwal untuk satu tanggal karyawan di laci detail -->
        <JejakJadwalHariDrawer
            v-model="jejakHari.buka"
            endpoint="/admin/pengajuan-shift/jejak-hari"
            :kode="jejakHari.kode"
            :tanggal="jejakHari.tanggal"
        />

        <!-- Konfirmasi batal: dampaknya disebutkan sebelum admin menekan tombol -->
        <HcisDialog
            v-model="dialogBatal"
            title="Batalkan pengajuan ini?"
            kicker="Administrator HCIS · Pembatalan"
            sentiment="danger"
            icon="exclamation-triangle-fill"
            size="md"
            sticky-footer
            :close-on-overlay="!membatalkan"
            :close-on-esc="!membatalkan"
        >
            <div v-if="target" class="ssa-batal">
                <p class="ssa-batal__subjek">
                    <strong>{{ target.nama }}</strong> · {{ target.no_transaksi }} · {{ jenis(target.jenis).label }} · {{ ringkasTanggal(target) }}
                </p>

                <div class="ssa-blok">
                    <span class="ssa-blok__judul">Yang akan terjadi</span>
                    <ul class="ssa-dampak">
                        <li v-for="(d, i) in dampakBatal" :key="i">{{ d }}</li>
                    </ul>
                </div>

                <HcisTextarea
                    id="ssa-alasan"
                    v-model="alasan"
                    label="Alasan pembatalan"
                    placeholder="Contoh: jadwal sudah diatur ulang, pengajuan ganda…"
                    :maxlength="180"
                    :rows="3"
                    :error="galatAlasan"
:hint="target.sumber === 'KARYAWAN' ? 'Tampil di riwayat Self Shift karyawan.' : 'Tampil di riwayat pengajuan leader.'"
                />
            </div>

            <template #footer>
                <div class="ssa-aksi">
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
import JejakJadwalHariDrawer from '@/Pages/ESS/Attendance/Shift/Schedule/Components/JejakJadwalHariDrawer.vue';

const BASE = '/admin/pengajuan-shift';
const URL_SHIFT_ADMIN = '/leader-shift/admin';
const PENANDA_BATAL = 'Dibatalkan admin: ';

const STATUS = {
    MENUNGGU: { label: 'Menunggu', variant: 'warning' },
    SEBAGIAN: { label: 'Sebagian Diterapkan', variant: 'info' },
    DITERAPKAN: { label: 'Diterapkan', variant: 'success' },
    DITOLAK: { label: 'Ditolak', variant: 'danger' },
    DIBATALKAN: { label: 'Dibatalkan', variant: 'neutral' },
};

const STATUS_TANGGAL = {
    MENUNGGU: { label: 'Menunggu', variant: 'warning' },
    DITERAPKAN: { label: 'Diterapkan', variant: 'success' },
    DITOLAK: { label: 'Ditolak', variant: 'danger' },
    DIBATALKAN: { label: 'Dibatalkan', variant: 'neutral' },
};

const JENIS = {
    GANTI_SHIFT: { label: 'Ganti Shift', ikon: 'arrow-repeat', variant: 'brand' },
    STATUS_HARI: { label: 'Status Hari', ikon: 'calendar-x', variant: 'teal' },
    TUKAR_HARI: { label: 'Tukar Hari', ikon: 'arrow-left-right', variant: 'purple' },
};

const SUMBER = {
    KARYAWAN: 'Karyawan sendiri',
    LEADER: 'Leader',
    ADMIN: 'Admin',
};

// Nilai Status_Hari yang dipakai leader; selain ini ditampilkan apa adanya.
const STATUS_HARI = { OFF: 'Libur', WORK: 'Masuk', CAMPURAN: 'Campuran' };

const KOLOM = [
    { key: 'karyawan', label: 'Karyawan', minWidth: '220px' },
    { key: 'jenis', label: 'Jenis', width: '140px' },
    { key: 'tanggal', label: 'Tanggal', minWidth: '190px', sortable: true },
    { key: 'status', label: 'Status', width: '170px' },
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

const URUT_KOLOM = { tanggal: 'tanggal', diajukan: 'dibuat' };

const LANGKAH = {
    approved: { label: 'Disetujui', sentiment: 'success' },
    rejected: { label: 'Ditolak', sentiment: 'danger' },
    pending: { label: 'Menunggu keputusan', sentiment: 'warning' },
    closed: { label: 'Ditutup', sentiment: 'neutral' },
    upcoming: { label: 'Belum sampai giliran', sentiment: 'neutral' },
};

export default {
    name: 'PengajuanShiftAdmin',
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
        JejakJadwalHariDrawer,
    },
    props: {
        opsiSaringan: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            KOLOM,
            PENANDA_BATAL,
            memuat: false,
            sudahDimuat: false,
            baris: [],
            total: 0,
            stats: {},
            saring: { search: '', status: '', sumber: '', jenis: '', tanggal: ['', ''], ...KOSONG_ORGANISASI(), page: 1, limit: 15, urut: 'dibuat', arah: 'desc' },
            drawerTerbuka: false,
            memuatDetail: false,
            detail: null,
            dialogBatal: false,
            target: null,
            alasan: '',
            galatAlasan: '',
            membatalkan: false,
            jejakHari: { buka: false, kode: '', tanggal: '' },
        };
    },
    computed: {
        kartuRingkasan() {
            const s = this.stats;
            return [
                { status: 'MENUNGGU', label: 'Menunggu persetujuan', nilai: s.menunggu ?? 0, ikon: 'hourglass-split', sentiment: 'warning', keterangan: 'Belum ada tanggal yang berlaku' },
                { status: 'SEBAGIAN', label: 'Sebagian diterapkan', nilai: s.sebagian ?? 0, ikon: 'circle-half', sentiment: 'info', keterangan: 'Sisa tanggal masih menunggu' },
                { status: 'DITERAPKAN', label: 'Sudah diterapkan', nilai: s.diterapkan ?? 0, ikon: 'check2-circle', sentiment: 'success', keterangan: 'Sudah masuk jadwal' },
                { status: 'DITOLAK', label: 'Ditolak', nilai: s.ditolak ?? 0, ikon: 'x-octagon', sentiment: 'danger', keterangan: 'Diputus tidak disetujui' },
                { status: 'DIBATALKAN', label: 'Dibatalkan', nilai: s.dibatalkan ?? 0, ikon: 'slash-circle', sentiment: 'neutral', keterangan: 'Oleh pengaju atau admin' },
            ];
        },
        chipStatus() {
            const s = this.stats;
            return [
                { label: `Semua (${s.total ?? 0})`, value: '' },
                ...Object.entries(STATUS).map(([value, st]) => ({ label: `${st.label} (${s[value.toLowerCase()] ?? 0})`, value })),
            ];
        },
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
                    key: 'sumber',
                    label: 'Diajukan oleh',
                    type: 'select',
                    icon: 'person-up',
                    placeholder: 'Semua pengaju',
                    col: 3,
                    options: Object.entries(SUMBER).map(([value, label]) => ({ value, label })),
                },
                {
                    key: 'jenis',
                    label: 'Jenis pengajuan',
                    type: 'select',
                    icon: 'arrow-repeat',
                    placeholder: 'Semua jenis',
                    col: 3,
                    options: Object.entries(JENIS).map(([value, j]) => ({ value, label: j.label })),
                },
                { key: 'tanggal', label: 'Tanggal shift', type: 'daterange', icon: 'calendar-range', col: 3 },
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
            const nilai = { sumber: this.saring.sumber, jenis: this.saring.jenis, tanggal: this.saring.tanggal };
            ORGANISASI.forEach((o) => {
                nilai[o.key] = this.saring[o.key];
            });
            return nilai;
        },
        langkahTimeline() {
            return (this.detail?.approval_steps || []).map((l, i) => {
                const st = LANGKAH[l.status] || LANGKAH.upcoming;
                return {
                    id: i,
                    title: l.nama,
                    description: [`Langkah ${l.urutan}`, l.catatan].filter(Boolean).join(' · '),
                    time: l.waktu ? this.formatWaktu(l.waktu) : '',
                    status: st.label,
                    sentiment: st.sentiment,
                };
            });
        },
        dampakBatal() {
            const t = this.target;
            if (!t) return [];
            const tertunda = t.jumlah_tertunda ?? 0;
            const terap = t.jumlah_diterapkan ?? 0;
            const hasil = [];
            if (t.jenis === 'TUKAR_HARI') {
                hasil.push('Tukar hari yang belum diterapkan dibatalkan; hari masuk dan hari libur kembali seperti jadwal semula.');
            } else if (t.jenis === 'STATUS_HARI') {
                hasil.push(`${tertunda} tanggal yang belum diterapkan dibatalkan; status hari pada tanggal itu tetap seperti jadwal saat ini.`);
            } else {
                hasil.push(`${tertunda} tanggal yang belum diterapkan dibatalkan; shift pada tanggal itu tetap seperti jadwal saat ini.`);
            }
            if (t.sumber !== 'KARYAWAN') {
                hasil.push(`Hanya untuk ${t.nama}. Anggota lain dalam pengajuan leader yang sama tidak terpengaruh.`);
            }
            if (terap > 0) {
                hasil.push(`${terap} tanggal yang sudah diterapkan tetap berlaku. Bila perlu diubah, lakukan lewat Shift Admin.`);
            }
            hasil.push('Persetujuan yang masih menunggu ditutup bila tak ada lagi tanggal tertunda dalam pengajuan ini.');
            hasil.push('Jadwal dan absensi tidak berubah karena tidak ada shift yang dicabut.');
            hasil.push(t.sumber === 'KARYAWAN'
                ? 'Karyawan melihat status Dibatalkan Admin beserta alasannya di riwayat Self Shift (tanpa notifikasi WhatsApp).'
                : 'Alasannya tampil di riwayat leader yang mengajukan (tanpa notifikasi WhatsApp).');
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
        statusTanggal(s) {
            return STATUS_TANGGAL[s] || { label: s || '—', variant: 'neutral' };
        },
        jenis(j) {
            return JENIS[j] || JENIS.GANTI_SHIFT;
        },
        labelTarget(row) {
            if (row.jenis === 'STATUS_HARI') return row.target === 'PERMANEN' ? 'permanen' : 'sementara';
            return row.target === 'PERMANEN' ? 'Shift permanen' : 'Shift sementara';
        },
        labelPengaju(row) {
            const sumber = SUMBER[row.sumber] || row.sumber || '—';
            return row.sumber === 'KARYAWAN' || !row.pengaju ? sumber : `${sumber} · ${row.pengaju}`;
        },
        labelStatusHari(v) {
            return STATUS_HARI[v] || v || '—';
        },
        ringkasTanggal(row) {
            if (row.jenis === 'TUKAR_HARI') {
                return `Masuk ${this.formatTanggal(row.tanggal_bekerja)} · Libur ${this.formatTanggal(row.tanggal_off)}`;
            }
            if (!row.tanggal_awal) return '—';
            const rentang = row.tanggal_awal === row.tanggal_akhir
                ? this.formatTanggal(row.tanggal_awal)
                : `${this.formatTanggal(row.tanggal_awal)} – ${this.formatTanggal(row.tanggal_akhir)}`;
            return row.jenis === 'STATUS_HARI' ? `${rentang} · ${this.labelStatusHari(row.status_hari)}` : rentang;
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
        parameter() {
            const [dari, sampai] = this.saring.tanggal || ['', ''];
            return {
                search: this.saring.search,
                status: this.saring.status,
                sumber: this.saring.sumber,
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
                hcisMessage.error(e.response?.data?.message || 'Daftar pengajuan shift gagal dimuat.');
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
            this.saring = { ...this.saring, search: '', status: '', sumber: '', jenis: '', tanggal: ['', ''], ...KOSONG_ORGANISASI(), page: 1 };
            this.muat();
        },
        async ambilDetail(row) {
            const { data } = await axios.get(`${BASE}/detail/${encodeURIComponent(row.no_transaksi)}/${row.id_detail}`);
            return data?.success ? data.result : null;
        },
        async bukaDetail(row) {
            if (!row?.no_transaksi) return;
            this.drawerTerbuka = true;
            this.memuatDetail = true;
            this.detail = null;
            try {
                this.detail = await this.ambilDetail(row);
            } catch (e) {
                hcisMessage.error(e.response?.data?.message || 'Detail pengajuan gagal dimuat.');
                this.drawerTerbuka = false;
            } finally {
                this.memuatDetail = false;
            }
        },
        async mintaBatal(row) {
            try {
                // Dimuat ulang: jumlah tanggal tertunda harus berdasarkan keadaan TERKINI.
                this.target = await this.ambilDetail(row);
            } catch (e) {
                hcisMessage.error(e.response?.data?.message || 'Data pengajuan gagal dimuat.');
                return;
            }
            if (!this.target?.boleh_batal) {
                hcisMessage.warning('Pengajuan ini sudah tidak punya tanggal yang bisa dibatalkan.');
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
                const { data } = await axios.post(`${BASE}/batal/${encodeURIComponent(this.target.no_transaksi)}/${this.target.id_detail}`, { alasan: this.alasan.trim() });
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
        bukaJejak(tanggal) {
            if (!this.detail?.kode_karyawan || !tanggal) return;
            this.jejakHari = { buka: true, kode: this.detail.kode_karyawan, tanggal: String(tanggal).slice(0, 10) };
        },
        bukaShiftAdmin() {
            window.location.assign(URL_SHIFT_ADMIN);
        },
    },
};
</script>

<style scoped>
.ssa-page {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}

.ssa-tumpuk {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.15rem;
    min-width: 0;
}

.ssa-nama {
    font-weight: 650;
    color: var(--hcis-text-strong);
}

.ssa-muted {
    font-size: 0.75rem;
    color: var(--hcis-text-muted);
}

.ssa-aksi,
.ssa-kaki {
    display: flex;
    justify-content: flex-end;
    gap: 0.35rem;
    width: 100%;
}

.ssa-kaki {
    flex-wrap: wrap;
}

.ssa-detail {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.ssa-baris-lencana {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.ssa-info {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem 1rem;
    margin: 0;
}

.ssa-info dt {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-text-muted);
}

.ssa-info dd {
    margin: 0.1rem 0 0;
    font-size: 0.85rem;
    color: var(--hcis-text-strong);
}

.ssa-blok__judul {
    display: block;
    margin-bottom: 0.3rem;
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-text-strong);
}

.ssa-blok__isi {
    margin: 0;
    font-size: 0.85rem;
    color: var(--hcis-text-muted);
    white-space: pre-line;
}

.ssa-tanggal {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.ssa-tanggal__baris {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.55rem 0.7rem;
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-md, 0.5rem);
    font-size: 0.85rem;
    color: var(--hcis-text-strong);
}

.ssa-tanggal__aksi {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
}

.ssa-batal {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.ssa-batal__subjek {
    margin: 0;
    font-size: 0.85rem;
    color: var(--hcis-text-muted);
}

.ssa-dampak {
    margin: 0;
    padding-left: 1.1rem;
    font-size: 0.82rem;
    color: var(--hcis-text-strong);
}

.ssa-dampak li + li {
    margin-top: 0.25rem;
}

@media (max-width: 640px) {
    .ssa-info {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>

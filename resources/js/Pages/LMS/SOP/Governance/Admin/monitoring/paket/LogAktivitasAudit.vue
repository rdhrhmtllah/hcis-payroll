<template>
    <div class="mk-log-flow">
        <!-- ══ 1. CARD BUKTI PRESENSI & VERIFIKASI KEHADIRAN ══ -->
        <HcisCard
            variant="glass"
            :sentiment="absensi ? 'success' : (wajibAbsensi ? 'warning' : 'neutral')"
            size="sm"
            icon="camera-video-fill"
            title="Bukti Presensi & Verifikasi Kehadiran"
            description="Rekaman otentikasi check-in kamera live, koordinat GPS, dan jejak perangkat karyawan"
            :badge="absensi ? 'Terverifikasi' : (wajibAbsensi ? 'Wajib Check-in' : 'Bebas Presensi')"
            :badge-variant="absensi ? 'success' : (wajibAbsensi ? 'warning' : 'neutral')"
        >
            <!-- Kasus 1: Presensi Tercatat & Terverifikasi -->
            <div v-if="absensi" class="mk-absensi-box">
                <div class="row g-3 align-items-center">
                    <!-- Foto Bukti Selfie Presensi -->
                    <div class="col-12 col-md-auto text-center">
                        <div class="mk-absensi-foto-wrap" @click="bukaFotoModal">
                            <img
                                v-if="absensi.Foto_Url"
                                :src="absensi.Foto_Url"
                                alt="Foto Presensi"
                                class="mk-absensi-foto"
                            />
                            <div v-else class="mk-absensi-no-foto">
                                <i class="bi bi-person-bounding-box fs-1 text-muted"></i>
                                <span class="small text-muted mt-1">Tanpa Foto</span>
                            </div>
                            <div v-if="absensi.Foto_Url" class="mk-absensi-foto-overlay">
                                <i class="bi bi-arrows-fullscreen text-white"></i>
                            </div>
                        </div>
                        <HcisButton
                            v-if="absensi.Foto_Url"
                            label="Lihat Foto"
                            variant="link"
                            size="xs"
                            leading-icon="zoom-in"
                            class="mt-1"
                            @click="bukaFotoModal"
                        />
                    </div>

                    <!-- Rincian Metadata Check-in -->
                    <div class="col-12 col-md">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2.5">
                            <HcisBadge variant="success" size="sm" icon="patch-check-fill">
                                Check-in Terverifikasi
                            </HcisBadge>
                            <HcisBadge
                                v-if="absensi.Lokasi_Meragukan"
                                variant="warning"
                                size="sm"
                                icon="exclamation-triangle-fill"
                            >
                                Akurasi GPS Rendah
                            </HcisBadge>
                            <HcisBadge
                                v-else-if="absensi.Akurasi_Meter !== null"
                                variant="info"
                                size="sm"
                                icon="crosshair"
                            >
                                Akurasi ±{{ Math.round(absensi.Akurasi_Meter) }}m
                            </HcisBadge>
                        </div>

                        <div class="row g-2 mk-absensi-grid">
                            <!-- Waktu Checktime -->
                            <div class="col-12 col-sm-6">
                                <div class="mk-abs-item">
                                    <span class="mk-abs-lab"><i class="bi bi-clock-history me-1 text-primary"></i>Waktu Presensi</span>
                                    <span class="mk-abs-val tnum">{{ fmt(absensi.Checktime) }}</span>
                                </div>
                            </div>

                            <!-- Koordinat GPS -->
                            <div class="col-12 col-sm-6">
                                <div class="mk-abs-item">
                                    <span class="mk-abs-lab"><i class="bi bi-geo-alt-fill me-1 text-danger"></i>Koordinat Lokasi</span>
                                    <div class="d-flex align-items-center justify-content-between gap-1">
                                        <span class="mk-abs-val tnum text-truncate" :title="`${absensi.Latitude}, ${absensi.Longitude}`">
                                            {{ absensi.Latitude && absensi.Longitude ? `${absensi.Latitude}, ${absensi.Longitude}` : '—' }}
                                        </span>
                                        <HcisButton
                                            v-if="absensi.Latitude && absensi.Longitude"
                                            label="Peta"
                                            variant="link"
                                            size="xs"
                                            trailing-icon="box-arrow-up-right"
                                            :href="`https://www.google.com/maps?q=${absensi.Latitude},${absensi.Longitude}`"
                                            target="_blank"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Alamat IP -->
                            <div class="col-12 col-sm-6">
                                <div class="mk-abs-item">
                                    <span class="mk-abs-lab"><i class="bi bi-hdd-network me-1 text-secondary"></i>IP Gateway</span>
                                    <span class="mk-abs-val tnum">{{ absensi.IP_Address || '—' }}</span>
                                </div>
                            </div>

                            <!-- Perangkat / User Agent -->
                            <div class="col-12 col-sm-6">
                                <div class="mk-abs-item">
                                    <span class="mk-abs-lab"><i class="bi bi-laptop me-1 text-secondary"></i>Perangkat Karyawan</span>
                                    <span class="mk-abs-val text-truncate" :title="absensi.User_Agent || '—'">
                                        {{ ringkasUA(absensi.User_Agent) || '—' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kasus 2: Wajib Presensi tapi Belum Check-in -->
            <HcisContextBanner
                v-else-if="wajibAbsensi"
                variant="warning"
                icon="exclamation-octagon-fill"
                title="Karyawan Belum Melakukan Presensi Kehadiran"
                description="Paket Dokumen ini mewajibkan presensi foto wajah & lokasi GPS sebelum materi prosedur dapat diakses oleh karyawan."
            />

            <!-- Kasus 3: Paket Bebas Presensi -->
            <HcisContextBanner
                v-else
                variant="neutral"
                icon="shield-slash"
                title="Paket Bebas Presensi Kehadiran"
                description="Paket Dokumen ini diatur tanpa gerbang presensi. Karyawan dapat langsung membaca dan mengonfirmasi seluruh materi prosedur."
            />
        </HcisCard>

        <!-- ══ 2. ALUR MASTER TIMELINE KRONOLOGIS (HcisTimeline) ══ -->
        <HcisTimeline
            :items="timelineNodes"
            :active="activeTimelineIndex"
            card
            variant="glass"
            sentiment="purple"
            title="Log Kronologi Aktivitas & Jejak Pembacaan"
            description="Rekam jejak stempel waktu interaksi karyawan dari penugasan hingga tuntas"
            kicker="JEJAK AUDIT REAL-TIME"
            icon="clock-history"
            :badge="`${timelineNodes.length} Tahap Aktivitas`"
            badge-variant="brand"
        >
            <!-- Slot marker: Icon jewel bulatan timeline -->
            <template #marker="{ item }">
                <i class="bi" :class="item.markerIcon || 'bi-circle'" style="font-size: 0.95rem; line-height: 1;"></i>
            </template>

            <!-- Slot avatar: Icon tipe media / icon stage -->
            <template #avatar="{ item }">
                <ItemTypeIcon v-if="item.tipeItem" :tipe="item.tipeItem" />
                <div v-else-if="item.avatarType" class="sop-tipe" :class="`type-ico-${item.avatarType}`">
                    <i class="bi" :class="item.avatarIcon"></i>
                </div>
                <div v-else class="sop-tipe type-ico-PARAGRAF">
                    <i class="bi" :class="item.avatarIcon || 'bi-circle'"></i>
                </div>
            </template>

            <!-- Slot itemContent: Detail isi tiap node timeline -->
            <template #itemContent="{ item }">
                <!-- Tahap 1: Penugasan -->
                <div v-if="item.type === 'ASSIGN'" class="small text-muted">
                    Dokumen ditugaskan kepada karyawan
                    <span v-if="pen.Tenggat"> dengan tenggat penyelesaian hingga <strong>{{ fmtTgl(pen.Tenggat) }}</strong></span>
                    <span v-else> dengan status berlaku tanpa batas tenggat</span>.
                </div>

                <!-- Tahap 2: Presensi -->
                <div v-else-if="item.type === 'ABSENSI'">
                    <div v-if="absensi" class="small text-muted d-flex align-items-center gap-3 flex-wrap">
                        <span><i class="bi bi-hdd-network me-1 text-secondary"></i>IP: <strong>{{ absensi.IP_Address || '—' }}</strong></span>
                        <span><i class="bi bi-laptop me-1 text-secondary"></i>Perangkat: <strong>{{ ringkasUA(absensi.User_Agent) || '—' }}</strong></span>
                        <span v-if="absensi.Latitude && absensi.Longitude">
                            <i class="bi bi-geo-alt me-1 text-danger"></i>GPS: <strong>{{ absensi.Latitude }}, {{ absensi.Longitude }}</strong>
                        </span>
                        <HcisButton
                            v-if="absensi.Latitude && absensi.Longitude"
                            label="Peta"
                            variant="link"
                            size="xs"
                            trailing-icon="box-arrow-up-right"
                            :href="`https://www.google.com/maps?q=${absensi.Latitude},${absensi.Longitude}`"
                            target="_blank"
                            class="p-0 ms-1"
                        />
                    </div>
                    <div v-else-if="wajibAbsensi" class="small text-warning-emphasis">
                        Karyawan belum melakukan check-in foto selfie & GPS.
                    </div>
                    <div v-else class="small text-muted">
                        Paket ini tidak mewajibkan presensi kehadiran.
                    </div>
                </div>

                <!-- Tahap 3..N: Item Prosedur -->
                <div v-else-if="item.type === 'ITEM'">
                    <!-- Badge Metadata Item -->
                    <div class="d-flex align-items-center gap-1.5 flex-wrap mb-2">
                        <HcisBadge variant="neutral" size="xs">
                            #{{ item.indexNomor }}
                        </HcisBadge>
                        <HcisBadge :variant="item.rawItem.Wajib ? 'warning' : 'neutral'" size="xs">
                            {{ item.rawItem.Wajib ? 'Wajib' : 'Opsional' }}
                        </HcisBadge>
                        <HcisBadge v-if="item.rawItem.Nomor_SOP_Resmi" variant="neutral" size="xs" icon="file-earmark-text">
                            {{ item.rawItem.Nomor_SOP_Resmi }}
                        </HcisBadge>
                        <HcisBadge v-if="item.rawItem.Versi !== null && item.rawItem.Versi !== undefined" variant="brand" size="xs">
                            v{{ String(item.rawItem.Versi).padStart(2, '0') }}
                        </HcisBadge>
                    </div>

                    <!-- Matrix Stempel Aktivitas Riil Item -->
                    <div class="row g-2.5 mt-2">
                        <!-- Pertama Dibuka -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="mk-stamp-tile" :class="{ 'is-empty': !item.rawItem.Waktu_Buka }">
                                <div class="mk-stamp-tile-head">
                                    <i class="bi bi-box-arrow-in-right text-info"></i>
                                    <span>Pertama Dibuka</span>
                                </div>
                                <div class="mk-stamp-tile-val tnum">
                                    {{ item.rawItem.Waktu_Buka ? fmt(item.rawItem.Waktu_Buka) : 'Belum dibuka' }}
                                </div>
                            </div>
                        </div>

                        <!-- Durasi Interaksi -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="mk-stamp-tile" :class="{ 'is-empty': !item.rawItem.Durasi_Detik }">
                                <div class="mk-stamp-tile-head">
                                    <i class="bi bi-stopwatch text-primary"></i>
                                    <span>Durasi Interaksi</span>
                                </div>
                                <div class="mk-stamp-tile-val tnum">
                                    {{ item.rawItem.Durasi_Detik ? durasi(item.rawItem.Durasi_Detik) : '0 dtk' }}
                                </div>
                            </div>
                        </div>

                        <!-- Waktu Konfirmasi -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="mk-stamp-tile" :class="{ 'is-empty': !item.rawItem.Waktu_Konfirmasi }">
                                <div class="mk-stamp-tile-head">
                                    <i class="bi bi-check2-circle text-success"></i>
                                    <span>Waktu Konfirmasi</span>
                                </div>
                                <div class="mk-stamp-tile-val tnum">
                                    {{ item.rawItem.Waktu_Konfirmasi ? fmt(item.rawItem.Waktu_Konfirmasi) : 'Belum konfirmasi' }}
                                </div>
                            </div>
                        </div>

                        <!-- Jejak IP / Gateway -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="mk-stamp-tile" :class="{ 'is-empty': !item.rawItem.Konfirmasi_IP }">
                                <div class="mk-stamp-tile-head">
                                    <i class="bi bi-hdd-network text-secondary"></i>
                                    <span>Jejak IP / Gateway</span>
                                </div>
                                <div class="mk-stamp-tile-val tnum text-truncate" :title="item.rawItem.Konfirmasi_IP || '—'">
                                    {{ item.rawItem.Konfirmasi_IP || '—' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Khusus Kuis / Ujian -->
                    <div
                        v-if="item.rawItem.Tipe_Item === 'KUIS' || item.rawItem.Skor_Quiz !== null || item.rawItem.Jumlah_Percobaan > 0"
                        class="mk-quiz-strip mt-3"
                    >
                        <div class="d-flex align-items-center gap-2.5 min-w-0">
                            <div class="mk-quiz-ico">
                                <i class="bi bi-patch-question-fill"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="mk-quiz-label">Hasil Ujian &amp; Pemahaman</div>
                                <div class="mk-quiz-metrics">
                                    <span>
                                        Skor: <strong :class="item.rawItem.Status_Lulus === 'Y' ? 'text-success' : 'text-danger'">{{ item.rawItem.Skor_Quiz !== null ? item.rawItem.Skor_Quiz : '—' }}</strong><span v-if="item.rawItem.Nilai_Minimum !== null" class="text-muted"> / {{ item.rawItem.Nilai_Minimum }} (Min)</span>
                                    </span>
                                    <span class="text-muted">·</span>
                                    <span>
                                        Percobaan: <strong>{{ item.rawItem.Jumlah_Percobaan || 1 }}</strong><span v-if="item.rawItem.Maks_Percobaan" class="text-muted"> / {{ item.rawItem.Maks_Percobaan }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <HcisBadge
                            :variant="item.rawItem.Status_Lulus === 'Y' ? 'success' : 'danger'"
                            size="sm"
                            :icon="item.rawItem.Status_Lulus === 'Y' ? 'check-circle-fill' : 'x-circle-fill'"
                        >
                            {{ item.rawItem.Status_Lulus === 'Y' ? 'Lulus Ujian' : 'Belum Lulus' }}
                        </HcisBadge>
                    </div>
                </div>

                <!-- Tahap Final: Selesai -->
                <div v-else-if="item.type === 'FINISH'" class="small text-muted">
                    <template v-if="persen === 100">
                        Seluruh prosedur wajib telah dituntaskan dan terekam ke dalam audit kepatuhan resmi.
                    </template>
                    <template v-else>
                        Masih terdapat {{ totalItem - (pr.item_selesai || 0) }} prosedur yang belum diselesaikan karyawan.
                    </template>
                </div>
            </template>
        </HcisTimeline>

        <!-- ══ 3. CARD REKAP EVALUASI KUIS (HcisTable) ══ -->
        <HcisCard
            v-if="hasQuizzes"
            variant="glass"
            sentiment="purple"
            size="sm"
            icon="card-checklist"
            title="Rekap Hasil Ujian & Evaluasi Pemahaman"
            description="Daftar hasil penilaian kuis pemahaman dokumen yang telah dikerjakan karyawan"
            :badge="`${quizItems.length} Kuis`"
            badge-variant="brand"
        >
            <HcisTable
                :columns="kolomKuis"
                :rows="quizItems"
                variant="flat"
                size="sm"
                hoverable
            >
                <!-- Kolom 1: No -->
                <template #cell-no="{ index }">
                    <span class="fw-bold text-muted">{{ index + 1 }}</span>
                </template>

                <!-- Kolom 2: Judul Kuis -->
                <template #cell-judul="{ row }">
                    <div class="fw-bold text-dark">{{ row.Judul_Item }}</div>
                    <div v-if="row.Nomor_SOP_Resmi" class="small text-muted mt-0.5">
                        <i class="bi bi-file-earmark-text me-1 text-primary"></i>{{ row.Nomor_SOP_Resmi }}
                    </div>
                </template>

                <!-- Kolom 3: Skor Akhir -->
                <template #cell-skor="{ row }">
                    <span class="tnum fw-bold fs-6" :class="row.Status_Lulus === 'Y' ? 'text-success' : 'text-danger'">
                        {{ row.Skor_Quiz !== null ? row.Skor_Quiz : '—' }}
                    </span>
                </template>

                <!-- Kolom 4: Passing Grade -->
                <template #cell-passing="{ row }">
                    <span class="tnum text-muted fw-semibold">
                        {{ row.Nilai_Minimum !== null ? row.Nilai_Minimum : '—' }}
                    </span>
                </template>

                <!-- Kolom 5: Percobaan -->
                <template #cell-percobaan="{ row }">
                    <span class="tnum">
                        {{ row.Jumlah_Percobaan || 1 }} <span v-if="row.Maks_Percobaan" class="text-muted">/ {{ row.Maks_Percobaan }}</span>
                    </span>
                </template>

                <!-- Kolom 6: Status -->
                <template #cell-status="{ row }">
                    <HcisBadge
                        :variant="row.Status_Lulus === 'Y' ? 'success' : 'danger'"
                        size="sm"
                        :icon="row.Status_Lulus === 'Y' ? 'check-circle-fill' : 'x-circle-fill'"
                    >
                        {{ row.Status_Lulus === 'Y' ? 'LULUS' : 'BELUM LULUS' }}
                    </HcisBadge>
                </template>
            </HcisTable>
        </HcisCard>

        <!-- ══ PRATINJAU FOTO PRESENSI (HcisFileViewer) ══ -->
        <HcisFileViewer
            v-model="modalFoto"
            :file="fotoViewerFile"
            :title="fotoViewerTitle"
            :allow-download="true"
            :allow-print="true"
            :allow-external-link="true"
        />
    </div>
</template>

<script>
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisContextBanner,
    HcisFileViewer,
    HcisTable,
    HcisTimeline,
} from '@/components/ui';
import StatusPill from '../../../../Shared/components/ui/StatusPill.vue';
import ItemTypeIcon from '../../../../Shared/components/ui/ItemTypeIcon.vue';
import { STATUS_ITEM } from '../../../../Shared/support/constants.js';

export default {
    name: 'LogAktivitasAudit',
    components: {
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisContextBanner,
        HcisFileViewer,
        HcisTable,
        HcisTimeline,
        StatusPill,
        ItemTypeIcon,
    },
    props: {
        detail: { type: Object, default: () => ({}) },
        kode: { type: String, required: true },
        idPaket: { type: String, required: true },
    },
    data() {
        return {
            modalFoto: false,
        };
    },
    computed: {
        d() {
            return this.detail || {};
        },
        paket() {
            return this.d.paket || {};
        },
        pen() {
            return this.d.penugasan || {};
        },
        pr() {
            return this.d.progress || {};
        },
        absensi() {
            return this.d.absensi || null;
        },
        wajibAbsensi() {
            return this.paket.Flag_Absensi === 'Y';
        },
        absensiTerverifikasi() {
            return !!this.absensi;
        },
        persen() {
            return this.pr.persen || 0;
        },
        totalItem() {
            return (this.d.items || []).length;
        },
        totalItemWajib() {
            return (this.d.items || []).filter((it) => !!it.Wajib || it.Flag_Wajib === 'Y').length;
        },
        daftarItemAktivitas() {
            return this.d.items || [];
        },
        quizItems() {
            return (this.d.items || []).filter(
                (it) => it.Tipe_Item === 'KUIS' || it.Skor_Quiz !== null || it.Jumlah_Percobaan > 0,
            );
        },
        hasQuizzes() {
            return this.quizItems.length > 0;
        },
        totalWaktuBaca() {
            const items = this.d.items || [];
            const totalDetik = items.reduce((n, it) => n + (Number(it.Durasi_Detik) || 0), 0);
            return this.durasi(totalDetik);
        },
        statusTenggatCaption() {
            if (this.pen.Is_Terlambat) {
                return `Lewat ${Math.abs(this.pen.Hari_Tersisa || 0)} hari dari tenggat`;
            }
            if (this.pen.Tenggat) {
                return `Tenggat: ${this.pen.Tenggat}`;
            }
            return 'Berlaku tanpa batas tenggat';
        },
        timelineNodes() {
            const nodes = [];

            // 1. Node Penugasan
            nodes.push({
                id: 'assign',
                type: 'ASSIGN',
                title: '1. Penugasan Dokumen Diterbitkan',
                status: this.pen.Sumber ? `Sumber: ${this.pen.Sumber}` : 'Penugasan Resmi',
                sentiment: 'brand',
                avatarType: 'PARAGRAF',
                avatarIcon: 'bi-send-fill',
                markerIcon: 'bi-send-fill',
                time: this.fmt(this.pen.Tanggal_Assign),
            });

            // 2. Node Presensi
            nodes.push({
                id: 'absensi',
                type: 'ABSENSI',
                title: '2. Presensi Kehadiran & Foto Check-In',
                status: this.absensi ? 'Terverifikasi' : (this.wajibAbsensi ? 'Menunggu Check-In' : 'Tidak Diwajibkan'),
                sentiment: this.absensi ? 'success' : (this.wajibAbsensi ? 'warning' : 'neutral'),
                avatarType: 'ABSENSI',
                avatarIcon: 'bi-camera-fill',
                markerIcon: this.absensi ? 'bi-camera-fill' : (this.wajibAbsensi ? 'bi-exclamation-lg' : 'bi-shield-slash'),
                time: this.absensi?.Checktime ? this.fmt(this.absensi.Checktime) : null,
            });

            // 3..N. Node Items
            this.daftarItemAktivitas.forEach((it, idx) => {
                const isDone = it.Status === 'SELESAI' || it.Status === 'DIKONFIRMASI';
                const isProgress = it.Status === 'PROGRESS' || it.Status === 'DIBUKA';

                nodes.push({
                    id: `item-${it.Id_SOP_Paket_Item}`,
                    type: 'ITEM',
                    indexNomor: idx + 3,
                    title: it.Judul_Item,
                    status: this.statusLabel(it.Status),
                    sentiment: isDone ? 'success' : (isProgress ? 'brand' : 'neutral'),
                    tipeItem: it.Tipe_Item,
                    markerIcon: isDone ? 'bi-check-lg' : (isProgress ? 'bi-eye-fill' : 'bi-circle'),
                    time: it.Waktu_Konfirmasi ? this.fmt(it.Waktu_Konfirmasi) : (it.Waktu_Buka ? this.fmt(it.Waktu_Buka) : null),
                    rawItem: it,
                });
            });

            // Final Node: Selesai
            nodes.push({
                id: 'finish',
                type: 'FINISH',
                title: this.persen === 100 ? 'Dokumen Tuntas Dikonfirmasi' : 'Penyelesaian Dokumen Sedang Berjalan',
                status: this.persen === 100 ? '100% Kepatuhan' : `${this.persen}% Selesai`,
                sentiment: this.persen === 100 ? 'success' : 'warning',
                avatarType: this.persen === 100 ? 'DOKUMEN' : 'KUIS',
                avatarIcon: this.persen === 100 ? 'bi-trophy-fill' : 'bi-hourglass-split',
                markerIcon: this.persen === 100 ? 'bi-trophy-fill' : 'bi-hourglass-split',
                time: this.pen.Tanggal_Selesai ? this.fmt(this.pen.Tanggal_Selesai) : null,
            });

            return nodes;
        },
        activeTimelineIndex() {
            if (this.persen === 100) {
                return this.timelineNodes.length - 1;
            }
            const idx = this.timelineNodes.findIndex((n) => n.sentiment === 'warning' || n.sentiment === 'neutral' || n.sentiment === 'brand');
            return idx !== -1 ? idx : 0;
        },
        kolomKuis() {
            return [
                { key: 'no', label: 'No', width: '60px', align: 'center' },
                { key: 'judul', label: 'Judul Kuis / Ujian', align: 'left' },
                { key: 'skor', label: 'Skor Akhir', width: '130px', align: 'center' },
                { key: 'passing', label: 'Passing Grade', width: '130px', align: 'center' },
                { key: 'percobaan', label: 'Percobaan', width: '120px', align: 'center' },
                { key: 'status', label: 'Status', width: '140px', align: 'center' },
            ];
        },
        fotoViewerFile() {
            if (!this.absensi?.Foto_Url) return null;
            return {
                url: this.absensi.Foto_Url,
                name: `Foto Presensi - ${this.d.karyawan?.Nama || 'Karyawan'}.jpg`,
                title: `Foto Presensi Kehadiran (${this.fmt(this.absensi?.Checktime)})`,
                type: 'image',
                allowDownload: true,
                allowPrint: true,
                allowExternalLink: true,
            };
        },
        fotoViewerTitle() {
            return `Foto Presensi Kehadiran - ${this.d.karyawan?.Nama || 'Karyawan'}`;
        },
    },
    methods: {
        bukaFotoModal() {
            if (this.absensi?.Foto_Url) {
                this.modalFoto = true;
            }
        },
        durasi(detik) {
            if (!detik || detik <= 0) return '0 dtk';
            const m = Math.floor(detik / 60);
            const s = detik % 60;
            if (m === 0) return `${s} dtk`;
            if (s === 0) return `${m} mnt`;
            return `${m} mnt ${s} dtk`;
        },
        fmt(d) {
            if (!d) return '—';
            const x = new Date(d);
            if (isNaN(x)) return d;
            return x.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        fmtTgl(d) {
            if (!d) return '—';
            const x = new Date(d);
            if (isNaN(x)) return d;
            return x.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        fmtJam(d) {
            if (!d) return '—';
            const x = new Date(d);
            if (isNaN(x)) return String(d).slice(11, 16) || d;
            return x.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        },
        ringkasUA(ua) {
            if (!ua) return '';
            let os = 'Perangkat';
            if (/windows/i.test(ua)) os = 'Windows';
            else if (/android/i.test(ua)) os = 'Android';
            else if (/iphone|ipad/i.test(ua)) os = 'iOS';
            else if (/macintosh|mac os/i.test(ua)) os = 'macOS';
            else if (/linux/i.test(ua)) os = 'Linux';

            let browser = 'Browser';
            if (/edg\//i.test(ua)) browser = 'Edge';
            else if (/chrome|crios/i.test(ua)) browser = 'Chrome';
            else if (/firefox|fxios/i.test(ua)) browser = 'Firefox';
            else if (/safari/i.test(ua)) browser = 'Safari';

            return `${browser} · ${os}`;
        },
        statusLabel(status) {
            return STATUS_ITEM[status]?.label || status || 'Belum Dibuka';
        },
        statusVariant(status) {
            return STATUS_ITEM[status]?.variant || 'neutral';
        },
    },
};
</script>

<style scoped>
.mk-log-flow {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.min-w-0 {
    min-width: 0;
}

.tnum {
    font-variant-numeric: tabular-nums;
}

/* ══ Absensi Box ══ */
.mk-absensi-box {
    padding: 0.5rem 0.25rem;
}

.mk-absensi-foto-wrap {
    width: 100px;
    height: 100px;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
    border: 2px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
    transition: transform 150ms ease, box-shadow 150ms ease;
}

.mk-absensi-foto-wrap:hover {
    transform: scale(1.03);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
}

.mk-absensi-foto {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mk-absensi-no-foto {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.mk-absensi-foto-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 150ms ease;
}

.mk-absensi-foto-wrap:hover .mk-absensi-foto-overlay {
    opacity: 1;
}

.mk-abs-item {
    display: flex;
    flex-direction: column;
    padding: 0.55rem 0.75rem;
    border-radius: 8px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}

.mk-abs-lab {
    font-size: 0.66rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
}

.mk-abs-val {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    margin-top: 0.15rem;
}

/* ══ Timeline Custom Elements ══ */
:deep(.hcis-timeline-avatar) {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    width: auto !important;
    height: auto !important;
    border-radius: 0 !important;
    padding: 0 !important;
}

:deep(.sop-tipe) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    font-size: 1.1rem;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

:deep(.sop-tipe.type-ico-PARAGRAF) {
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-500);
}
:deep(.sop-tipe.type-ico-DOKUMEN) {
    background: rgba(16, 185, 129, 0.14);
    color: var(--hcis-emerald-600);
}
:deep(.sop-tipe.type-ico-KUIS) {
    background: rgba(245, 158, 11, 0.14);
    color: var(--hcis-amber-600);
}
:deep(.sop-tipe.type-ico-FORM) {
    background: rgba(6, 182, 212, 0.14);
    color: var(--hcis-cyan-600);
}
:deep(.sop-tipe.type-ico-VIDEO) {
    background: rgba(139, 92, 246, 0.14);
    color: var(--hcis-violet-600);
}
:deep(.sop-tipe.type-ico-TAUTAN) {
    background: rgba(236, 72, 153, 0.14);
    color: #db2777;
}
:deep(.sop-tipe.type-ico-ABSENSI) {
    background: rgba(59, 130, 246, 0.14);
    color: var(--hcis-blue-600);
}

/* ══ Stamp Tile (4-Column Metadata Matrix) ══ */
.mk-stamp-tile {
    display: flex;
    flex-direction: column;
    padding: 0.65rem 0.85rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
    height: 100%;
    transition: all 150ms ease;
}

.mk-stamp-tile:hover {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
}

.mk-stamp-tile.is-empty {
    background: #fcfcfd;
    border-color: var(--hcis-slate-100);
}

.mk-stamp-tile-head {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 0.25rem;
}

.mk-stamp-tile-head i {
    font-size: 0.85rem;
}

.mk-stamp-tile-val {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    line-height: 1.3;
}

.mk-stamp-tile.is-empty .mk-stamp-tile-val {
    color: var(--hcis-slate-400);
    font-weight: 500;
}

/* ══ Quiz Strip ══ */
.mk-quiz-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-left: 4px solid #8b5cf6;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.mk-quiz-ico {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: var(--hcis-violet-50);
    color: var(--hcis-violet-600);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.mk-quiz-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.mk-quiz-metrics {
    font-size: 0.84rem;
    color: var(--hcis-slate-800);
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-top: 0.1rem;
}

/* ══ Quiz Table ══ */
.mk-quiz-table th {
    font-size: 0.74rem;
    font-weight: 800;
    color: var(--hcis-slate-600);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.75rem 1rem;
}

.mk-quiz-table td {
    padding: 0.85rem 1rem;
    font-size: 0.85rem;
}
</style>

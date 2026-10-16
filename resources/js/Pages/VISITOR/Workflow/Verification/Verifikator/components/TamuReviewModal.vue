<template>
    <div>
        <HcisDualPaneDialog
            :model-value="show"
            :title="modalTitle"
            :kicker="modalKicker"
            icon="person-vcard-fill"
            sentiment="brand"
            variant="default"
            pattern="none"
            size="xl"
            left-width="390px"
            sticky-footer
            :loading="detailLoading"
            loading-text="Mengambil rincian data tamu..."
            @update:model-value="(val) => { if (!val) $emit('close'); }"
            @close="$emit('close')"
        >
            <!-- ================================================================= -->
            <!-- LEFT PANE: Overview Hero Card, PIC Info & Approval Timeline       -->
            <!-- ================================================================= -->
            <template #left>
                <div v-if="tamu">
                    <!-- 1. Hero Tamu Identity Card -->
                    <div class="tamu-hero-card">
                        <!-- 1. Top Bar: Category Pill & Status Badge -->
                        <div class="tamu-hero-topbar">
                            <span class="tamu-hero-category-pill">
                                <span class="tamu-hero-category-dot" />
                                <span class="tamu-hero-category-text">
                                    {{
                                        tamu.Tipe_Tamu === 'B2B'
                                            ? `Tamu B2B ${tamu.B2B_Unit === 'PROCUREMENT' ? 'Procurement' : tamu.B2B_Unit === 'LOGISTIC' ? 'Logistic' : ''}`.trim()
                                            : 'Tamu Regular'
                                    }}
                                </span>
                            </span>

                            <HcisBadge
                                :label="getStatusBadgeLabel(tamu.Status_Kunjungan, tamu)"
                                :variant="getStatusBadgeVariant(tamu.Status_Kunjungan, tamu)"
                                :icon="getStatusBadgeIcon(tamu.Status_Kunjungan, tamu)"
                                :pulse="tamu.Status_Kunjungan === 'CHECKED_IN' || isItemPendingApproval(tamu)"
                                size="sm"
                                appearance="subtle"
                                class="flex-shrink-0"
                            />
                        </div>

                        <!-- 2. Guest Identity Section: Avatar + Name + Organization / Party -->
                        <div class="tamu-hero-profile">
                            <div class="tamu-hero-avatar-box shadow-sm">
                                <HcisAvatar
                                    :name="tamu.Nama_Tamu"
                                    size="lg"
                                    shape="circle"
                                    variant="brand"
                                />
                            </div>
                            <div class="tamu-hero-profile-info">
                                <h3 class="tamu-hero-name" :title="tamu.Nama_Tamu">
                                    {{ tamu.Nama_Tamu || '-' }}
                                </h3>
                                <div class="tamu-hero-profile-meta">
                                    <span class="tamu-hero-instansi">
                                        <HcisIcon name="buildings" size="2xs" class="text-slate-400 flex-shrink-0" />
                                        <span>{{ tamu.Instansi_Asal || tamu.Instansi_Atau_Asal || 'Kunjungan Umum / Pribadi' }}</span>
                                    </span>
                                    <span v-if="tamu.Jumlah_Tamu > 1" class="tamu-hero-party-count">
                                        <HcisIcon name="people-fill" size="2xs" class="text-slate-400 flex-shrink-0" />
                                        <span>Total {{ tamu.Jumlah_Tamu }} Orang</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Details Container: Registration Number & Schedule Grid -->
                        <div class="tamu-hero-details-container">
                            <!-- Transaction Number Bar -->
                            <div class="tamu-hero-trx-card">
                                <div class="tamu-hero-trx-info">
                                    <span class="tamu-hero-card-kicker">
                                        <HcisIcon name="receipt" size="2xs" class="text-slate-400" />
                                        <span>Nomor Registrasi Tamu</span>
                                    </span>
                                    <div class="tamu-hero-trx-number font-tabular font-mono">
                                        {{ tamu.No_Transaksi || '-' }}
                                    </div>
                                </div>
                                <button
                                    v-if="tamu.No_Transaksi"
                                    type="button"
                                    class="tamu-copy-btn"
                                    title="Salin nomor transaksi"
                                    aria-label="Salin nomor transaksi"
                                    @click="copyToClipboard(tamu.No_Transaksi)"
                                >
                                    <HcisIcon name="copy" size="xs" />
                                </button>
                            </div>

                            <!-- Schedule 2-Column Grid -->
                            <div class="tamu-hero-subpod-grid">
                                <div class="tamu-hero-subpod">
                                    <div class="tamu-hero-card-kicker">
                                        <HcisIcon name="box-arrow-in-right" size="2xs" class="text-primary flex-shrink-0" />
                                        <span>Waktu Masuk</span>
                                    </div>
                                    <div class="tamu-hero-subpod-val font-tabular">
                                        <span v-if="tamu.Waktu_Aktual_Masuk" class="text-slate-900 fw-bold">
                                            {{ formatTimeOnly(tamu.Waktu_Aktual_Masuk) }}
                                        </span>
                                        <span v-else class="text-slate-400 fw-medium text-xs">
                                            Belum Masuk
                                        </span>
                                    </div>
                                </div>
                                <div class="tamu-hero-subpod">
                                    <div class="tamu-hero-card-kicker">
                                        <HcisIcon name="box-arrow-right" size="2xs" class="text-rose-500 flex-shrink-0" />
                                        <span>Waktu Keluar</span>
                                    </div>
                                    <div class="tamu-hero-subpod-val font-tabular">
                                        <span v-if="tamu.Waktu_Aktual_Keluar" class="text-slate-900 fw-bold">
                                            {{ formatTimeOnly(tamu.Waktu_Aktual_Keluar) }}
                                        </span>
                                        <span v-else class="text-slate-400 fw-medium text-xs">
                                            Belum Keluar
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Bottom Meta Row: Kunjungan Date & Submission Date -->
                        <div class="tamu-hero-meta-bar text-xs">
                            <div class="tamu-hero-date">
                                <HcisIcon name="calendar3" size="xs" class="text-slate-400" />
                                <span>Estimasi: <strong>{{ labelJendela(tamu) }}</strong></span>
                            </div>
                            <div v-if="tamu.Created_At" class="tamu-hero-date">
                                <HcisIcon name="clock-history" size="xs" class="text-slate-400" />
                                <span>Diajukan: <strong>{{ formatDate(tamu.Created_At) }}</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. PIC Information Card -->
                    <HcisCard
                        title="PIC & Penanggung Jawab"
                        icon="person-badge-fill"
                        sentiment="brand"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <div class="tamu-pic-card">
                            <div class="tamu-pic-header">
                                <div class="tamu-pic-avatar">
                                    {{ getInitials(tamu.Nama_PIC || tamu.PIC_Tujuan) }}
                                </div>
                                <div class="tamu-pic-identity flex-grow-1 min-w-0">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span
                                            class="tamu-pic-name text-truncate"
                                            :title="tamu.Nama_PIC || tamu.PIC_Tujuan"
                                        >
                                            {{ tamu.Nama_PIC || tamu.PIC_Tujuan || '-' }}
                                        </span>
                                        <span v-if="tamu.Kode_Karyawan_PIC" class="tamu-pic-nik-tag font-tabular flex-shrink-0">
                                            Kode: {{ tamu.Kode_Karyawan_PIC }}
                                        </span>
                                    </div>
                                    <div class="tamu-pic-role text-xs text-truncate">
                                        Penanggung Jawab Kunjungan
                                    </div>
                                </div>
                            </div>

                            <div v-if="tamu.Divisi_PIC" class="tamu-pic-dept-pod">
                                <div class="tamu-pic-dept-icon">
                                    <HcisIcon name="building" size="xs" />
                                </div>
                                <div class="tamu-pic-dept-info">
                                    <span class="tamu-pic-dept-label">Divisi / Unit Kerja</span>
                                    <span class="tamu-pic-dept-val">{{ tamu.Divisi_PIC }}</span>
                                </div>
                            </div>
                        </div>
                    </HcisCard>

                    <!-- 3. Approval Workflow Section -->
                    <HcisCard
                        title="Alur Persetujuan"
                        description="Tahapan verifikasi otoritas dan catatan persetujuan."
                        icon="diagram-3-fill"
                        sentiment="brand"
                        variant="default"
                        size="sm"
                        divider
                    >
                        <div v-if="approvalTimelineItems && approvalTimelineItems.length" class="permit-approval-timeline-wrapper">
                            <HcisTimeline
                                :items="approvalTimelineItems"
                                direction="vertical"
                                variant="compact"
                                sentiment="brand"
                                :card="false"
                            />
                        </div>
                        <div v-else-if="tamu.Tipe_Tamu === 'B2B' || tamu.Status_Permit_Coklat" class="tamu-auto-approved-box p-3 rounded-3 border text-center" :class="isItemPendingApproval(tamu) ? 'bg-amber-50/60 border-amber-200' : 'bg-slate-50 border-slate-200'">
                            <div v-if="isItemPendingApproval(tamu)">
                                <HcisIcon name="hourglass-split" size="lg" class="text-amber-500 mb-2" />
                                <div class="fw-bold text-slate-800 text-sm">Menunggu Persetujuan Permit</div>
                                <div class="text-xs text-slate-600 mt-1">
                                    Izin akses gerbang (Permit Coklat) sedang diproses sebelum tamu dapat memasuki area kerja.
                                </div>
                            </div>
                            <div v-else-if="tamu.Status_Permit_Coklat === 'DITOLAK' || tamu.Status_Approval === 'REJECTED'">
                                <HcisIcon name="slash-circle" size="lg" class="text-danger mb-2" />
                                <div class="fw-bold text-slate-800 text-sm">Permit Akses Ditolak</div>
                                <div class="text-xs text-slate-600 mt-1">
                                    Pengajuan izin akses gerbang ditolak oleh verifikator.
                                </div>
                            </div>
                            <div v-else>
                                <HcisIcon name="check-circle-fill" size="lg" class="text-success mb-2" />
                                <div class="fw-bold text-slate-800 text-sm">Izin Akses Disetujui</div>
                                <div class="text-xs text-slate-600 mt-1">
                                    Izin akses gerbang (Permit Coklat) telah disetujui dan diverifikasi.
                                </div>
                            </div>
                            <div class="mt-3 pt-2 border-top border-slate-200/60 d-flex justify-content-center">
                                <HcisButton
                                    size="xs"
                                    variant="secondary"
                                    leading-icon="shield-lock-fill"
                                    v-if="bolehBukaPermit"
                                    label="Lihat Detail Permit"
                                    @click="openPermitModal(tamu)"
                                />
                            </div>
                            <div v-if="tamu.Created_At" class="mt-2 text-xs font-tabular text-slate-600">
                                Dibuat pada: <strong>{{ formatFullDateTime(tamu.Created_At) }}</strong>
                            </div>
                        </div>
                        <div v-else class="tamu-auto-approved-box p-3 rounded-3 bg-slate-50 border border-slate-200 text-center">
                            <HcisIcon name="check-circle-fill" size="lg" class="text-primary mb-2" />
                            <div class="fw-bold text-slate-800 text-sm">Otorisasi Langsung</div>
                            <div class="text-xs text-slate-600 mt-1">
                                Kunjungan ini tidak memerlukan persetujuan berjenjang dan langsung dijadwalkan.
                            </div>
                            <div v-if="tamu.Created_At" class="mt-2 text-xs font-tabular text-slate-600">
                                Dibuat pada: <strong>{{ formatFullDateTime(tamu.Created_At) }}</strong>
                            </div>
                        </div>
                    </HcisCard>
                </div>
            </template>

            <!-- ================================================================= -->
            <!-- RIGHT PANE: Visit Specs, Guest Identity, and Log Notes            -->
            <!-- ================================================================= -->
            <template #right>
                <div v-if="tamu">
                    <!-- Cancellation / Rejection Alert if canceled or rejected -->
                    <HcisAlert
                        v-if="['REJECTED', 'WITHDRAWN', 'REVOKED', 'CANCELLED'].includes(tamu.Status_Kunjungan) && (tamu.Alasan_Tolak || tamu.Alasan_Pembatalan || getRejectionComment())"
                        variant="danger"
                        appearance="soft"
                        title="Alasan Pembatalan / Penolakan Kunjungan"
                        :description="tamu.Alasan_Tolak || tamu.Alasan_Pembatalan || getRejectionComment()"
                        icon="exclamation-octagon-fill"
                        class="mb-4"
                    />

                    <!-- Permit Akses Gerbang (Permit Coklat) Banner -->
                    <HcisAlert
                        v-if="(tamu.Tipe_Tamu === 'B2B' || tamu.Status_Permit_Coklat) && permitBannerInfo"
                        :variant="permitBannerInfo.variant"
                        appearance="soft"
                        size="sm"
                        :title="permitBannerInfo.title"
                        :description="permitBannerInfo.desc"
                        :badge="permitBannerInfo.statusLabel"
                        :icon="permitBannerInfo.icon"
                        class="mb-4"
                    >
                        <template v-if="bolehBukaPermit" #actions>
                            <HcisButton
                                size="xs"
                                variant="outline"
                                leading-icon="shield-lock-fill"
                                label="Lihat Detail Permit"
                                @click="openPermitModal(tamu)"
                            />
                        </template>
                    </HcisAlert>

                    <!-- Banner Pengajuan Sendiri (Four-Eyes Principle Governance) -->
                    <HcisAlert
                        v-if="isOwnTamu && isPending"
                        variant="warning"
                        appearance="soft"
                        icon="shield-lock-fill"
                        title="Persetujuan Memerlukan Verifikator Lain"
                        class="mb-4 permit-own-alert"
                    >
                        <div class="permit-own-alert__content">
                            <p class="permit-own-alert__text">
                                Pengajuan kunjungan ini terdaftar dengan akun Anda sebagai PIC. Sesuai prinsip <strong>Four-Eyes Principle (Pemisahan Tugas)</strong>, Anda tidak dapat memutus atau menyetujui pengajuan milik sendiri.
                            </p>
                            <div class="permit-own-alert__actions mt-2">
                                <HcisButton
                                    label="Pelajari Kebijakan"
                                    size="xs"
                                    variant="secondary"
                                    leading-icon="info-circle"
                                    @click="dialogMilikSendiri = true"
                                />
                            </div>
                        </div>
                    </HcisAlert>

                    <!-- 1. Visit Specifications Grid Card -->
                    <HcisCard
                        title="Informasi Kunjungan"
                        description="Rincian identitas tamu, instansi asal, kontak, dan jadwal kunjungan."
                        icon="person-vcard-fill"
                        sentiment="brand"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <!-- Visitor Profile Header Strip -->
                        <div class="tamu-visitor-strip mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                <div class="d-flex align-items-center gap-2.5 min-w-0">
                                    <div class="tamu-visitor-avatar">
                                        <HcisIcon name="person-fill" size="sm" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="tamu-visitor-name text-truncate">
                                            {{ tamu.Nama_Tamu || '-' }}
                                        </div>
                                        <div class="tamu-visitor-instansi text-truncate">
                                            <HcisIcon name="buildings" size="2xs" class="text-slate-400" />
                                            <span>{{ tamu.Instansi_Asal || 'Umum / Personal' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                                    <HcisBadge
                                        :label="tamu.Tipe_Tamu === 'B2B' ? 'Tamu Mitra Kerja' : 'Tamu Regular'"
                                        :variant="tamu.Tipe_Tamu === 'B2B' ? 'brand' : 'neutral'"
                                        size="xs"
                                        appearance="subtle"
                                    />
                                    <span v-if="tamu.Jumlah_Tamu > 1" class="tamu-mini-pill">
                                        {{ tamu.Jumlah_Tamu }} Orang
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 4-Tile Detailed Spec Grid -->
                        <div class="tamu-specs-grid">
                            <div class="tamu-spec-tile">
                                <div class="tamu-spec-tile__header">
                                    <HcisIcon name="person-badge" size="2xs" class="tamu-spec-tile__icon" />
                                    <span class="tamu-spec-tile__label">Identitas Resmi (PIC)</span>
                                </div>
                                <div class="tamu-spec-tile__val d-flex align-items-center justify-content-between gap-1 font-tabular">
                                    <div class="d-flex align-items-center gap-1.5 min-w-0">
                                        <span class="tamu-id-type-badge font-mono fw-bold">
                                            {{ tamu.Jenis_Identitas || 'KTP' }}
                                        </span>
                                        <span class="font-mono fw-bold text-slate-900 text-truncate">
                                            {{ tamu.No_Identitas || '-' }}
                                        </span>
                                    </div>
                                    <button
                                        v-if="tamu.No_Identitas"
                                        type="button"
                                        class="tamu-tile-copy-btn"
                                        title="Salin nomor identitas"
                                    aria-label="Salin nomor identitas"
                                        @click="copyText(tamu.No_Identitas, 'Nomor identitas')"
                                    >
                                        <HcisIcon name="copy" size="2xs" />
                                    </button>
                                </div>
                            </div>

                            <div class="tamu-spec-tile">
                                <div class="tamu-spec-tile__header">
                                    <HcisIcon name="telephone-fill" size="2xs" class="tamu-spec-tile__icon" />
                                    <span class="tamu-spec-tile__label">Nomor Kontak / WhatsApp</span>
                                </div>
                                <div class="tamu-spec-tile__val d-flex align-items-center justify-content-between gap-1 font-tabular">
                                    <a
                                        v-if="tamu.No_HP"
                                        :href="`tel:${tamu.No_HP}`"
                                        class="text-primary text-decoration-none fw-semibold"
                                    >
                                        {{ tamu.No_HP }}
                                    </a>
                                    <span v-else class="text-muted">-</span>
                                    <button
                                        v-if="tamu.No_HP"
                                        type="button"
                                        class="tamu-tile-copy-btn"
                                        title="Salin nomor telepon"
                                    aria-label="Salin nomor telepon"
                                        @click="copyText(tamu.No_HP, 'Nomor telepon')"
                                    >
                                        <HcisIcon name="copy" size="2xs" />
                                    </button>
                                </div>
                            </div>

                            <div class="tamu-spec-tile">
                                <div class="tamu-spec-tile__header">
                                    <HcisIcon name="calendar-event-fill" size="2xs" class="tamu-spec-tile__icon" />
                                    <span class="tamu-spec-tile__label">Kategori Kunjungan</span>
                                </div>
                                <div class="tamu-spec-tile__val font-tabular text-slate-800 fw-semibold">
                                    {{ tamu.Tamu_Kategori === 'MITRA' ? 'Mitra (lebih dari 24 jam)' : 'Visitor (maks. 24 jam)' }}
                                </div>
                            </div>

                            <div class="tamu-spec-tile">
                                <div class="tamu-spec-tile__header">
                                    <HcisIcon name="clock-fill" size="2xs" class="tamu-spec-tile__icon" />
                                    <span class="tamu-spec-tile__label">Jendela Waktu Kedatangan</span>
                                </div>
                                <div class="tamu-spec-tile__val font-tabular">
                                    <template v-if="jendelaKunjunganDetail(tamu)">
                                        <!-- Same day visit -->
                                        <div v-if="jendelaKunjunganDetail(tamu).isSameDay" class="tamu-jendela-compact">
                                            <div class="fw-bold text-slate-900">{{ jendelaKunjunganDetail(tamu).dateStr }}</div>
                                            <div class="text-xs text-slate-600 font-medium mt-0.5">
                                                {{ jendelaKunjunganDetail(tamu).timeRangeStr }}
                                            </div>
                                        </div>
                                        <!-- Multi-day visit -->
                                        <div v-else class="tamu-jendela-timeline">
                                            <div class="tamu-jendela-row">
                                                <span class="tamu-jendela-dot is-in" title="Estimasi Masuk" />
                                                <div class="tamu-jendela-text">
                                                    <span>{{ jendelaKunjunganDetail(tamu).startDateStr }}</span>
                                                    <span class="text-slate-500 ms-1">{{ jendelaKunjunganDetail(tamu).startTimeStr }}</span>
                                                </div>
                                            </div>
                                            <div class="tamu-jendela-connector" />
                                            <div class="tamu-jendela-row">
                                                <span class="tamu-jendela-dot is-out" title="Estimasi Keluar" />
                                                <div class="tamu-jendela-text">
                                                    <span>{{ jendelaKunjunganDetail(tamu).endDateStr }}</span>
                                                    <span class="text-slate-500 ms-1">{{ jendelaKunjunganDetail(tamu).endTimeStr }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <span v-else class="text-slate-700">
                                        {{ labelJendela(tamu) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Keperluan / Catatan Kunjungan Callout -->
                        <div v-if="tamu.Keperluan || tamu.Keterangan" class="tamu-reason-callout mt-3">
                            <div class="tamu-reason-callout__header">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="tamu-reason-callout__icon">
                                        <HcisIcon name="chat-quote-fill" size="xs" />
                                    </span>
                                    <span class="tamu-reason-callout__title">Keperluan & Catatan Kunjungan</span>
                                </div>
                                <span class="tamu-reason-callout__tag">Catatan Tamu</span>
                            </div>
                            <div class="tamu-reason-callout__body mt-1">
                                <p class="tamu-reason-callout__text mb-0">
                                    “{{ tamu.Keperluan || tamu.Keterangan }}”
                                </p>
                            </div>
                        </div>

                        <!-- Keterangan Tambahan jika ada -->
                        <div v-if="tamu.Keterangan_Tambahan" class="tamu-note-callout mt-3">
                            <div class="d-flex align-items-center gap-1.5 mb-1 text-slate-700 fw-bold text-xs">
                                <HcisIcon name="info-circle" size="xs" class="text-slate-400" />
                                <span>Keterangan Tambahan</span>
                            </div>
                            <p class="text-xs text-slate-600 mb-0">
                                {{ tamu.Keterangan_Tambahan }}
                            </p>
                        </div>
                    </HcisCard>

                    <!-- 2. Transport & Party Specifications Card -->
                    <HcisCard
                        title="Transportasi & Rombongan"
                        description="Informasi sarana kendaraan dan daftar anggota rombongan tamu."
                        icon="people"
                        sentiment="brand"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <!-- 1. Moda Kedatangan & Kendaraan -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="tamu-spec-tile__label">Moda Kedatangan</span>
                                <span v-if="tamu.No_Plat_Kendaraan" class="text-2xs text-muted">Area Parkir Tamu</span>
                            </div>

                            <div v-if="tamu.No_Plat_Kendaraan" class="tamu-transport-card">
                                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="tamu-transport-icon-box">
                                            <HcisIcon :name="getVehicleIcon(tamu.Jenis_Kendaraan)" size="md" />
                                        </div>
                                        <div>
                                            <div class="fw-bold text-slate-900 text-sm">
                                                {{ tamu.Jenis_Kendaraan || 'Kendaraan Bermotor' }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                Tercatat parkir di area fasilitas perusahaan
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tamu-plate-badge tamu-plate-badge--lg">
                                        {{ tamu.No_Plat_Kendaraan }}
                                    </div>
                                </div>
                                <!-- Foto kendaraan dari pengaju B2B: rujukan inspeksi, bukan dokumen permit. -->
                                <div
                                    v-if="tamu.Foto_Kendaraan_Url"
                                    class="tamu-foto-kendaraan-wrap mt-3"
                                    role="button"
                                    tabindex="0"
                                    title="Klik untuk melihat dan memperbesar foto kendaraan"
                                    @click="bukaFotoKendaraan(tamu)"
                                >
                                    <img
                                        :src="tamu.Foto_Kendaraan_Url"
                                        alt="Foto kendaraan tamu"
                                        class="tamu-foto-kendaraan"
                                        loading="lazy"
                                    />
                                    <div class="tamu-foto-overlay">
                                        <HcisIcon name="zoom-in" size="sm" />
                                        <span>Klik untuk memperbesar</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="tamu-transport-empty">
                                <HcisIcon name="person-walking" size="md" class="text-slate-400 mb-1" />
                                <div class="fw-semibold text-slate-700 text-xs">Tanpa Kendaraan Pribadi</div>
                                <div class="text-2xs text-slate-400">Tamu berkunjung sebagai pejalan kaki, taksi/ojek online, atau transportasi umum.</div>
                            </div>
                        </div>

                        <!-- 2. Rombongan & Anggota Tamu -->
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="tamu-spec-tile__label">Daftar Anggota Rombongan</span>
                                <span class="tamu-mini-pill">
                                    Total: {{ tamu.Jumlah_Tamu || 1 }} Orang
                                </span>
                            </div>

                            <div v-if="bacaDaftarAnggota(tamu.Daftar_Anggota_Json).length" class="tamu-party-list">
                                <div
                                    v-for="(a, i) in bacaDaftarAnggota(tamu.Daftar_Anggota_Json)"
                                    :key="i"
                                    class="tamu-party-row"
                                >
                                    <div class="d-flex align-items-center gap-2.5 min-w-0">
                                        <span class="tamu-party-index">#{{ i + 1 }}</span>
                                        <div class="min-w-0">
                                            <div class="fw-bold text-slate-900 text-xs text-truncate">
                                                {{ a.nama }}
                                            </div>
                                            <div v-if="a.no_identitas" class="d-flex align-items-center gap-1.5 mt-0.5">
                                                <span class="tamu-id-type-badge font-mono">
                                                    {{ a.jenis_identitas || 'ID' }}
                                                </span>
                                                <span class="font-mono text-xs text-slate-600">
                                                    {{ a.no_identitas }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        v-if="a.no_identitas"
                                        type="button"
                                        class="tamu-tile-copy-btn"
                                        title="Salin nomor identitas"
                                    aria-label="Salin nomor identitas"
                                        @click="copyText(a.no_identitas, 'Nomor identitas anggota')"
                                    >
                                        <HcisIcon name="copy" size="2xs" />
                                    </button>
                                </div>
                            </div>
                            <div v-else class="tamu-transport-empty p-3">
                                <span v-if="(tamu.Jumlah_Tamu || 1) <= 1" class="text-xs text-slate-600">
                                    Kunjungan tunggal (1 orang tanpa rombongan tambahan)
                                </span>
                                <span v-else class="text-xs text-slate-600">
                                    {{ tamu.Jumlah_Tamu || 1 }} orang (daftar nama tidak dikumpulkan)
                                </span>
                            </div>
                        </div>
                    </HcisCard>

                    <!-- 3. Log Kehadiran Real-time (if checked-in or checked-out) -->
                    <HcisCard
                        v-if="tamu.Waktu_Aktual_Masuk || tamu.Waktu_Aktual_Keluar"
                        title="Log Kehadiran di Area Kerja"
                        description="Catatan waktu fisik saat tamu masuk dan keluar dari area perusahaan."
                        icon="clock-history"
                        sentiment="teal"
                        variant="default"
                        size="sm"
                        divider
                    >
                        <div class="tamu-presence-grid">
                            <div class="tamu-presence-card">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="tamu-presence-icon text-primary">
                                        <HcisIcon name="box-arrow-in-right" size="md" />
                                    </div>
                                    <div>
                                        <span class="tamu-presence-label">Waktu Check-In Masuk</span>
                                        <div class="tamu-presence-val font-tabular">
                                            {{ formatFullDateTime(tamu.Waktu_Aktual_Masuk) || 'Belum Masuk' }}
                                        </div>
                                        <div
                                            v-if="sumberMasuk"
                                            class="tamu-presence-sumber"
                                            :class="`tamu-presence-sumber--${sumberMasuk.nada}`"
                                        >
                                            <HcisIcon :name="sumberMasuk.ikon" size="2xs" />
                                            <span>{{ sumberMasuk.label }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tamu-presence-card">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="tamu-presence-icon text-rose-600">
                                        <HcisIcon name="box-arrow-right" size="md" />
                                    </div>
                                    <div>
                                        <span class="tamu-presence-label">Waktu Check-Out Keluar</span>
                                        <div class="tamu-presence-val font-tabular">
                                            {{ formatFullDateTime(tamu.Waktu_Aktual_Keluar) || 'Belum Keluar' }}
                                        </div>
                                        <div
                                            v-if="sumberKeluar"
                                            class="tamu-presence-sumber"
                                            :class="`tamu-presence-sumber--${sumberKeluar.nada}`"
                                        >
                                            <HcisIcon :name="sumberKeluar.ikon" size="2xs" />
                                            <span>{{ sumberKeluar.label }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </HcisCard>
                </div>
            </template>

            <!-- ================================================================= -->
            <!-- FOOTER ACTIONS                                                    -->
            <!-- ================================================================= -->
            <template #footer>
                <div class="tamu-modal-footer-wrapper">
                    <div
                        v-if="isOwnTamu && isPending"
                        class="permit-footer-own-notice"
                        role="button"
                        tabindex="0"
                        title="Klik untuk melihat kebijakan Four-Eyes Principle"
                        @click="dialogMilikSendiri = true"
                        @keydown.enter="dialogMilikSendiri = true"
                    >
                        <div class="permit-footer-own-notice__icon">
                            <HcisIcon name="shield-lock-fill" size="xs" />
                        </div>
                        <div class="permit-footer-own-notice__text">
                            <strong>Pengajuan Sendiri</strong>
                            <span>Persetujuan butuh verifikator lain. <u>Info</u></span>
                        </div>
                    </div>
                    <div class="tamu-modal-footer-actions">
                        <HcisButton
                            v-if="isPending"
                            label="Tolak"
                            leading-icon="x-circle"
                            variant="danger-quiet"
                            size="md"
                            class="tamu-footer-btn tamu-footer-btn--reject"
                            :disabled="submitLoading"
                            @click="rejectAlert"
                        />
                        <HcisButton
                            v-if="isPending"
                            :label="submitLoading ? 'Menyimpan...' : (tamu && tamu.Tipe_Tamu === 'B2B' ? 'Setujui & Terbitkan Izin Masuk' : 'Setujui Kunjungan')"
                            leading-icon="check-circle-fill"
                            variant="success"
                            size="md"
                            class="tamu-footer-btn tamu-footer-btn--approve"
                            :loading="submitLoading"
                            :disabled="submitLoading"
                            :title="isOwnTamu ? 'Anda tidak dapat menyetujui pengajuan Anda sendiri. Minta verifikator lain untuk memutusnya.' : ''"
                            @click="approveAlert"
                        />
                        <HcisButton
                            v-if="!isPending"
                            label="Tutup"
                            variant="ghost"
                            size="md"
                            class="tamu-footer-btn"
                            @click="$emit('close')"
                        />
                    </div>
                </div>
            </template>
        </HcisDualPaneDialog>

        <!-- Permit Detail Dialog -->
        <PermitReviewModal
            v-if="showPermitModal"
            :show="showPermitModal"
            :permit-id="selectedPermitId"
            :detail-endpoint="readOnly ? '/laporanvisitor-detail-permit' : '/verifikator-permit'"
            :read-only="readOnly"
            @close="closePermitModal"
        />

        <!-- HCIS File Viewer (Vehicle Photo / Documents) -->
        <HcisFileViewer
            v-model="showFileViewer"
            :files="viewerFiles"
            :initial-index="viewerInitialIndex"
            :allow-download="true"
            :allow-print="true"
            :allow-external-link="true"
        />

        <!-- ================================================================= -->
        <!-- DIALOG LARANGAN PERSETUJUAN PENGAJUAN SENDIRI (FOUR-EYES PRINCIPLE) -->
        <!-- ================================================================= -->
        <HcisDialog
            v-if="dialogMilikSendiri"
            v-model="dialogMilikSendiri"
            title="Persetujuan Tidak Dapat Diproses"
            kicker="Visitor · Kebijakan Persetujuan & Tata Kelola"
            sentiment="warning"
            icon="shield-lock"
            icon-variant="boxed"
            size="md"
            header-divider
            sticky-footer
            @close="dialogMilikSendiri = false"
        >
            <template #badge>
                <HcisBadge label="Pengajuan Sendiri" variant="warning" appearance="subtle" dot size="sm" />
            </template>

            <div class="permit-own-dialog-content">
                <!-- 1. Ticket Informasi PIC vs Akun Pengguna -->
                <div class="permit-own-ticket">
                    <div class="permit-own-ticket__icon">
                        <HcisIcon name="person-badge-fill" size="md" />
                    </div>
                    <div class="permit-own-ticket__body">
                        <span class="permit-own-ticket__label">PIC Karyawan Terdaftar</span>
                        <div class="permit-own-ticket__name">
                            {{ tamu?.Nama_PIC || tamu?.Nama_Karyawan_PIC || 'Akun Anda' }}
                        </div>
                        <div class="permit-own-ticket__meta">
                            <span v-if="tamu?.Kode_Karyawan_PIC" class="permit-own-ticket__id font-tabular">
                                ID: {{ tamu.Kode_Karyawan_PIC }}
                            </span>
                            <span v-if="tamu?.No_Transaksi" class="permit-own-ticket__ref font-tabular">
                                · No. {{ tamu.No_Transaksi }}
                            </span>
                        </div>
                        <div class="permit-own-ticket__match-badge">
                            <HcisIcon name="person-x-fill" size="2xs" />
                            <span>Teridentifikasi sebagai akun Anda sendiri (PIC Kunjungan)</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Penjelasan Kebijakan (Four-Eyes Principle) -->
                <div class="permit-own-policy">
                    <div class="permit-own-policy__header">
                        <div class="permit-own-policy__icon">
                            <HcisIcon name="shield-check" size="xs" />
                        </div>
                        <strong>Prinsip Pemisahan Tugas (Four-Eyes Principle)</strong>
                    </div>
                    <p class="permit-own-policy__desc">
                        Sesuai standar tata kelola dan kepatuhan sistem, karyawan yang bertindak sebagai PIC penanggung jawab kunjungan dilarang menyetujui (<em>self-approve</em>) pengajuan miliknya sendiri demi menjaga integritas, transparansi otorisasi, dan akuntabilitas organisasi.
                    </p>
                </div>

                <!-- 3. Tindakan Solutif Selanjutnya -->
                <div class="permit-own-steps">
                    <div class="permit-own-steps__title">
                        <HcisIcon name="signpost-split-fill" size="xs" />
                        <span>Langkah yang Perlu Dilakukan:</span>
                    </div>
                    <ol class="permit-own-steps__list">
                        <li>
                            <strong>Alihkan ke Verifikator Lain:</strong>
                            <span>Minta rekan verifikator atau penilai otorisasi lain untuk memeriksa dan memutus pengajuan kunjungan ini.</span>
                        </li>
                        <li>
                            <strong>Verifikasi Independen:</strong>
                            <span>Pemeriksaan identitas dan keperluan kunjungan akan diverifikasi secara objektif oleh rekan verifikator.</span>
                        </li>
                    </ol>
                    <div class="permit-own-steps__note">
                        <HcisIcon name="info-circle" size="2xs" />
                        <span>Anda tetap dapat melihat detail kunjungan, anggota rombongan, dan memantau status persetujuannya.</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="permit-own-actions">
                    <HcisButton
                        label="Saya Mengerti"
                        variant="primary"
                        sentiment="warning"
                        leading-icon="check-circle-fill"
                        size="md"
                        class="permit-own-actions__btn"
                        @click="dialogMilikSendiri = false"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import axios from 'axios';
import { defineAsyncComponent } from 'vue';
import { ElMessage } from 'element-plus';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisDualPaneDialog,
    HcisFileViewer,
    HcisIcon,
    HcisTimeline,
    hcisDialog,
} from '@/components/ui';
import { bacaDaftarAnggota } from '@/Pages/VISITOR/Guest/Visit/daftarAnggota';
import { salinTeks } from '@/utils/clipboard';

export default {
    name: 'TamuReviewModal',
    components: {
        HcisAlert,
        HcisAvatar,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisDialog,
        HcisDualPaneDialog,
        HcisFileViewer,
        HcisIcon,
        HcisTimeline,
        PermitReviewModal: defineAsyncComponent(() => import('./PermitReviewModal.vue')),
    },
    props: {
        // Tombol "Lihat Detail Permit" memanggil endpoint yang dijaga kunci
        // halaman permit; pemegang kunci tamu saja akan menerima 403.
        bolehBukaPermit: { type: Boolean, default: true },
        show: { type: Boolean, default: false },
        tamuId: { type: [Number, String], default: null },
        idTamu: { type: [Number, String], default: null },
        detailEndpoint: { type: String, default: null },
        // Prefiks aksi approve/reject: meja Regular dan B2B punya rute sendiri.
        actionEndpoint: { type: String, default: '/verifikator-tamu' },
        readOnly: { type: Boolean, default: false },
    },
    emits: ['close', 'approved', 'rejected'],
    data() {
        return {
            detailLoading: false,
            submitLoading: false,
            tamu: null,
            approvals: [],
            errorMsg: null,
            showPermitModal: false,
            selectedPermitId: null,

            // File Viewer (Vehicle Photo)
            showFileViewer: false,
            viewerFiles: [],
            viewerInitialIndex: 0,

            // Dialog Larangan Persetujuan Pengajuan Sendiri (Four-Eyes Principle)
            isOwnTamu: false,
            dialogMilikSendiri: false,
        };
    },
    computed: {
        /**
         * Asal-usul jam masuk/keluar, dibaca dari Sumber_Masuk/Sumber_Keluar
         * yang dihitung VisitorKehadiranQueryService.
         *
         * TAPPING ikut ditampilkan, bukan hanya yang menyimpang. Penanda yang
         * cuma muncul saat ada masalah membuat ketiadaannya ambigu: pembaca
         * tidak bisa membedakan "kartunya terbaca" dari "kolomnya belum
         * terisi" - dan kunjungan lama sebelum palang menulis penanda ini
         * jatuh ke kategori kedua.
         */
        sumberMasuk() {
            return this.jelaskanSumber(this.tamu?.Sumber_Masuk, this.tamu?.Bypass_Masuk_Oleh);
        },
        sumberKeluar() {
            return this.jelaskanSumber(this.tamu?.Sumber_Keluar, this.tamu?.Bypass_Keluar_Oleh);
        },
        effectiveId() {
            return this.tamuId || this.idTamu || null;
        },
        isPending() {
            return !this.readOnly && this.tamu?.Status_Kunjungan === 'PENDING_APPROVAL';
        },
        modalTitle() {
            if (!this.tamu) return 'Detail Kunjungan Tamu';
            return this.isPending
                ? `Review Kunjungan — ${this.tamu.Nama_Tamu || ''}`
                : `Detail Kunjungan — ${this.tamu.Nama_Tamu || ''}`;
        },
        modalKicker() {
            return this.readOnly ? 'Visitor · Monitoring & Laporan' : 'Visitor · Verification Desk';
        },
        permitBannerInfo() {
            if (!this.tamu) return null;
            const isMitra = this.tamu.Tipe_Tamu === 'B2B';
            const statusPermit = String(this.tamu.Status_Permit_Coklat || '').toUpperCase();
            const statusKunjungan = String(this.tamu.Status_Kunjungan || '').toUpperCase();
            const statusApproval = String(this.tamu.Status_Approval || '').toUpperCase();

            // 1. Ditolak (Permit Ditolak / Approval Ditolak / Kunjungan Ditolak)
            if (
                statusPermit === 'DITOLAK' ||
                statusPermit === 'REJECTED' ||
                statusApproval === 'REJECTED' ||
                statusKunjungan === 'DITOLAK'
            ) {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: isMitra
                        ? 'Izin akses gerbang tidak disetujui untuk kunjungan mitra kerja ini.'
                        : 'Izin akses gerbang tidak disetujui.',
                    statusLabel: 'Ditolak',
                    variant: 'danger',
                    icon: 'shield-x',
                };
            }

            // 2. Dibatalkan (Permit Dibatalkan / Kunjungan Dibatalkan)
            if (
                statusPermit === 'DIBATALKAN' ||
                statusPermit === 'CANCELED' ||
                ['WITHDRAWN', 'REVOKED'].includes(statusKunjungan)
            ) {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: 'Izin akses gerbang dibatalkan.',
                    statusLabel: 'Dibatalkan',
                    variant: 'neutral',
                    icon: 'shield-slash',
                };
            }

            // 3. Sedang Aktif di Area (Checked-In)
            if (statusKunjungan === 'CHECKED_IN') {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: 'Tamu mitra kerja telah check-in di pos gerbang dan berada di dalam area.',
                    statusLabel: 'Sedang Aktif',
                    variant: 'brand',
                    icon: 'shield-check',
                };
            }

            // 4. Kunjungan Selesai (Checked-Out)
            if (statusKunjungan === 'CHECKED_OUT') {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: 'Kunjungan telah selesai dan akses gerbang telah ditutup.',
                    statusLabel: 'Selesai',
                    variant: 'success',
                    icon: 'shield-check',
                };
            }

            // 5. Permit Kadaluarsa (Expired)
            if (statusPermit === 'EXPIRED' || statusKunjungan === 'CANCELLED') {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: 'Masa berlaku izin akses gerbang telah habis.',
                    statusLabel: 'Kadaluarsa',
                    variant: 'neutral',
                    icon: 'clock-history',
                };
            }

            // 6. Menunggu Verifikasi / Pending (Jika permit atau kunjungan masih pending / belum disetujui)
            const isPending =
                this.isPending ||
                statusKunjungan === 'PENDING_APPROVAL' ||
                statusApproval === 'PENDING' ||
                statusApproval === 'MENUNGGU_APPROVAL' ||
                statusPermit === 'MENUNGGU_APPROVAL' ||
                statusPermit === 'PENDING' ||
                statusPermit === '' ||
                statusPermit === 'TANPA_AKSES';

            if (isPending) {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: isMitra
                        ? 'Kunjungan mitra kerja memerlukan verifikasi PIC sebelum izin akses gerbang diterbitkan.'
                        : 'Otorisasi akses gerbang memerlukan verifikasi sebelum kunjungan.',
                    statusLabel: 'Menunggu Verifikasi',
                    variant: 'warning',
                    icon: 'clock-history',
                };
            }

            // 7. Sudah Disetujui / Terbit (Hanya jika permit terkonfirmasi AKTIF/APPROVED dan tidak pending)
            if (
                statusPermit === 'AKTIF' ||
                statusPermit === 'APPROVED' ||
                statusPermit === 'DISETUJUI' ||
                statusPermit === 'SUDAH_DIBUAT' ||
                (statusKunjungan === 'SCHEDULED' && statusApproval === 'APPROVED')
            ) {
                return {
                    title: 'Izin Akses Gerbang (Permit Coklat)',
                    desc: 'Izin akses gerbang telah disetujui dan siap diverifikasi di pos gerbang.',
                    statusLabel: 'Disetujui',
                    variant: 'success',
                    icon: 'shield-check',
                };
            }

            // Fallback: Default Menunggu Verifikasi
            return {
                title: 'Izin Akses Gerbang (Permit Coklat)',
                desc: isMitra
                    ? 'Kunjungan mitra kerja memerlukan verifikasi PIC sebelum izin akses gerbang diterbitkan.'
                    : 'Otorisasi akses gerbang memerlukan verifikasi sebelum kunjungan.',
                statusLabel: 'Menunggu Verifikasi',
                variant: 'warning',
                icon: 'clock-history',
            };
        },
        approvalTimelineItems() {
            if (!this.approvals || !this.approvals.length) return [];
            return this.approvals.map((step, idx) => {
                let status = 'Menunggu';
                let sentiment = 'warning';
                let icon = 'hourglass-split';
                const isExpired = step.Expired_At && new Date(step.Expired_At) < new Date();

                if (step.Flag_Approval === 'Y') {
                    status = 'Disetujui';
                    sentiment = 'success';
                    icon = 'check-circle-fill';
                } else if (step.Flag_Approval === 'T') {
                    status = 'Ditolak';
                    sentiment = 'danger';
                    icon = 'x-circle-fill';
                } else if (isExpired) {
                    status = 'Kadaluarsa';
                    sentiment = 'danger';
                    icon = 'clock-history';
                }

                const approvalDate = step.Tanggal_Approval || step.Updated_At;
                const formattedTime = (step.Flag_Approval || isExpired) && approvalDate
                    ? this.formatFullDateTime(approvalDate)
                    : null;

                const approverName = step.Nama_Approved_By
                    || step.Nama_Approver
                    || step.Nama_Approver_Karyawan
                    || step.Nama_Role_Functional
                    || step.Approver_Karyawan_Kode
                    || 'Approver Otoritas';

                return {
                    id: step.Id_Approval_Request || idx,
                    title: step.Nama_Approval || `Tahap Approval ${step.Request_Order_Flow || idx + 1}`,
                    name: approverName,
                    status,
                    sentiment,
                    icon,
                    time: formattedTime,
                    description: step.Catatan && step.Catatan.trim() ? step.Catatan.trim() : null,
                    active: !step.Flag_Approval && !isExpired,
                };
            });
        },
    },
    watch: {
        show: {
            handler(val) {
                if (val && this.effectiveId) this.loadDetail();
                if (!val) this.resetState();
            },
            immediate: true,
        },
        effectiveId(val) {
            if (val && this.show) this.loadDetail();
        },
    },
    methods: {
        jelaskanSumber(sumber, oleh) {
            if (sumber === 'BYPASS') {
                return {
                    nada: 'peringatan',
                    ikon: 'shield-exclamation',
                    // Nama petugasnya ikut di label, bukan disembunyikan di
                    // tooltip: bypass adalah satu-satunya jalur yang menaruh
                    // manusia sebagai penjamin identitas tamu, dan yang dicari
                    // pembacanya ketika ada yang perlu ditanyakan.
                    label: oleh ? `Bypass palang oleh ${oleh}` : 'Bypass palang (petugas tidak tercatat)',
                };
            }

            if (sumber === 'MANUAL') {
                return { nada: 'netral', ikon: 'pencil-square', label: 'Dicatat manual di HCIS' };
            }

            if (sumber === 'TAPPING') {
                return { nada: 'baik', ikon: 'credit-card-2-front', label: 'Tapping kartu di palang' };
            }

            return null;
        },
        bacaDaftarAnggota,
        async copyToClipboard(text) {
            await this.salin(text, 'No. Transaksi');
        },

        async copyText(text, label = 'Teks') {
            await this.salin(text, label);
        },

        /**
         * Satu pintu menyalin, dan kegagalannya TIDAK BOLEH diam.
         *
         * Bentuk lamanya memanggil `.then()` pada hasil rantai opsional; di
         * konteks tak aman (http) hasilnya `undefined`, jadi yang terjadi bukan
         * "tidak tersalin" melainkan TypeError yang jatuh ke konsol. Bagi
         * pemakainya tombolnya sekadar mati tanpa sebab.
         */
        async salin(text, label = 'Teks') {
            const isi = String(text ?? '').trim();
            if (isi === '') return;

            if (await salinTeks(isi)) {
                ElMessage.success({ message: `${label} "${isi}" disalin.`, duration: 2000 });

                return;
            }

            ElMessage.warning({
                message: `${label} gagal disalin otomatis. Salin manual: ${isi}`,
                duration: 4000,
            });
        },

        getVehicleIcon(jenis) {
            const j = String(jenis || '').toLowerCase();
            if (j.includes('motor') || j.includes('roda 2') || j.includes('sepeda')) return 'bicycle';
            if (j.includes('truk') || j.includes('truck')) return 'truck';
            if (j.includes('bus')) return 'bus-front';
            return 'car-front-fill';
        },

        labelPermitCoklat(status) {
            switch (status) {
                case 'MENUNGGU_APPROVAL':
                    return 'Permit Menunggu Approval';
                case 'DITOLAK':
                    return 'Permit Ditolak';
                case 'TANPA_AKSES':
                    return 'Permit Belum Terbit';
                case 'AKTIF':
                    return 'Permit Berlaku';
                case 'EXPIRED':
                    return 'Permit Habis';
                case 'DIBATALKAN':
                    return 'Permit Dicabut';
                default:
                    return status || '';
            }
        },

        /**
         * Rincian jendela kedatangan & kepulangan yang terstruktur rapi untuk UI card & timeline.
         */
        jendelaKunjunganDetail(row) {
            if (!row || !row.Waktu_Mulai_Kunjungan || !row.Waktu_Selesai_Kunjungan) return null;
            try {
                const cleanStart = typeof row.Waktu_Mulai_Kunjungan === 'string' ? row.Waktu_Mulai_Kunjungan.replace(' ', 'T') : row.Waktu_Mulai_Kunjungan;
                const cleanEnd = typeof row.Waktu_Selesai_Kunjungan === 'string' ? row.Waktu_Selesai_Kunjungan.replace(' ', 'T') : row.Waktu_Selesai_Kunjungan;
                const dStart = new Date(cleanStart);
                const dEnd = new Date(cleanEnd);

                if (Number.isNaN(dStart.getTime()) || Number.isNaN(dEnd.getTime())) return null;

                const NAMA_BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                const startD = String(dStart.getDate()).padStart(2, '0');
                const startM = NAMA_BULAN[dStart.getMonth()] || String(dStart.getMonth() + 1).padStart(2, '0');
                const startY = dStart.getFullYear();
                const startH = String(dStart.getHours()).padStart(2, '0');
                const startMin = String(dStart.getMinutes()).padStart(2, '0');

                const endD = String(dEnd.getDate()).padStart(2, '0');
                const endM = NAMA_BULAN[dEnd.getMonth()] || String(dEnd.getMonth() + 1).padStart(2, '0');
                const endY = dEnd.getFullYear();
                const endH = String(dEnd.getHours()).padStart(2, '0');
                const endMin = String(dEnd.getMinutes()).padStart(2, '0');

                const isSameDay = startD === endD && startM === endM && startY === endY;

                return {
                    isSameDay,
                    dateStr: `${startD} ${startM} ${startY}`,
                    timeRangeStr: `${startH}:${startMin} – ${endH}:${endMin} WIB`,
                    startDateStr: `${startD} ${startM} ${startY}`,
                    startTimeStr: `${startH}:${startMin} WIB`,
                    endDateStr: `${endD} ${endM} ${endY}`,
                    endTimeStr: `${endH}:${endMin} WIB`,
                };
            } catch {
                return null;
            }
        },

        /**
         * Jendela jam kunjungan, dengan cadangan ke label estimasi lama.
         */
        labelJendela(row) {
            if (!row) return '-';

            const detail = this.jendelaKunjunganDetail(row);
            if (detail) {
                if (detail.isSameDay) {
                    return `${detail.dateStr} (${detail.timeRangeStr})`;
                }
                return `${detail.startDateStr} ${detail.startTimeStr} s/d ${detail.endDateStr} ${detail.endTimeStr}`;
            }

            return row.Waktu_Estimasi_Kunjungan || '-';
        },

        async loadDetail() {
            if (!this.effectiveId) return;
            this.detailLoading = true;
            this.errorMsg = null;
            try {
                const endpoint = this.detailEndpoint
                    ? `${this.detailEndpoint}/${encodeURIComponent(this.effectiveId)}`
                    : `/verifikator-tamu/${encodeURIComponent(this.effectiveId)}`;
                const { data: json } = await axios.get(endpoint);
                if (json.success) {
                    const res = json.result || {};
                    if (res.tamu) {
                        this.tamu = res.tamu;
                        this.approvals = res.approvals || [];
                        this.isOwnTamu = Boolean(res.is_own_tamu || res.tamu?.is_own_tamu);
                    } else {
                        this.tamu = res;
                        this.approvals = res.Approvals || res.approvals || [];
                        this.isOwnTamu = Boolean(res.is_own_tamu);
                    }
                } else {
                    this.errorMsg = json.message || 'Gagal memuat data tamu.';
                }
            } catch (e) {
                this.errorMsg = 'Terjadi kesalahan koneksi: ' + (e.response?.data?.message || e.message);
                console.error('loadDetail tamu error', e);
            } finally {
                this.detailLoading = false;
            }
        },

        async approveAlert() {
            if (this.isOwnTamu) {
                this.dialogMilikSendiri = true;
                return;
            }

            const b2b = this.tamu?.Tipe_Tamu === 'B2B';
            const confirmed = await hcisDialog.confirm({
                title: 'Setujui kunjungan tamu?',
                description: b2b
                    ? 'Tamu B2B akan diajukan Permit COKLAT selama rentang Estimasi Masuk–Keluar kunjungannya.'
                    : 'Status kunjungan akan disetujui dan dijadwalkan sesuai tanggal yang diajukan.',
                kicker: 'Visitor · Verification Desk',
                icon: 'check-circle-fill',
                sentiment: 'success',
                confirmText: b2b ? 'Setujui & Terbitkan Izin Masuk' : 'Setujui Kunjungan',
            });
            if (!confirmed) return;

            this.submitAction('approve');
        },

        async rejectAlert() {
            const alasan = await hcisDialog.prompt({
                title: 'Tolak kunjungan tamu',
                description: 'Masukkan alasan penolakan untuk dicatat pada riwayat sistem.',
                placeholder: 'Tuliskan alasan penolakan...',
                inputType: 'textarea',
                kicker: 'Visitor · Verification Desk',
                icon: 'x-circle-fill',
                sentiment: 'danger',
                confirmText: 'Tolak Kunjungan',
                confirmVariant: 'danger',
            });
            if (!alasan || !alasan.trim()) return;
            this.submitAction('reject', alasan.trim());
        },

        async submitAction(action, alasan = null) {
            this.submitLoading = true;
            try {
                const url = `${this.actionEndpoint}/${this.tamu.No_Transaksi}/${action}`;
                const body = action === 'reject' ? { alasan } : {};
                const { data: json } = await axios.post(url, body);

                if (json.success) {
                    this.$emit(action === 'approve' ? 'approved' : 'rejected');
                } else {
                    await hcisDialog.alert({
                        title: 'Aksi tidak dapat diproses',
                        description: json.message || 'Terjadi kesalahan.',
                        sentiment: 'danger',
                        icon: 'exclamation-triangle',
                    });
                }
            } catch (e) {
                const errCode = e.response?.data?.error_code;
                const msg = e.response?.data?.message || e.message || 'Gagal mengirim permintaan.';

                if (errCode === 'VISITOR_APPROVAL_CONFLICT' || String(msg).toLowerCase().includes('sendiri')) {
                    this.dialogMilikSendiri = true;
                    return;
                }

                await hcisDialog.alert({
                    title: 'Aksi tidak dapat diproses',
                    description: msg,
                    sentiment: 'danger',
                    icon: 'exclamation-triangle',
                });
                console.error(e);
            } finally {
                this.submitLoading = false;
            }
        },

        resetState() {
            this.tamu = null;
            this.approvals = [];
            this.submitLoading = false;
            this.errorMsg = null;
            this.showPermitModal = false;
            this.selectedPermitId = null;
            this.isOwnTamu = false;
            this.dialogMilikSendiri = false;
        },

        openPermitModal(item) {
            const target = item || this.tamu;
            if (!target) return;
            const permitId = target.Id_Transaksi_Permit || target.No_Transaksi_Permit || target.Id_Permit || target.No_Permit || target.No_Transaksi;
            this.selectedPermitId = permitId;
            this.showPermitModal = true;
        },

        closePermitModal() {
            this.showPermitModal = false;
            this.selectedPermitId = null;
        },

        getRejectionComment() {
            if (!this.approvals || !this.approvals.length) return '';
            const rejected = this.approvals.find((a) => a.Flag_Approval === 'T' && a.Catatan);
            return rejected ? rejected.Catatan : '';
        },

        getInitials(name) {
            if (!name) return 'T';
            const parts = name.trim().split(/\s+/);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return name.slice(0, 2).toUpperCase();
        },

        formatDate(val) {
            if (!val) return '-';
            try {
                return new Date(val).toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                });
            } catch (e) {
                return val;
            }
        },

        formatFullDateTime(dateStr) {
            if (!dateStr) return '-';
            try {
                const d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                const datePart = d.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                });
                const timePart = d.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).replace('.', ':');
                return `${datePart}, ${timePart} WIB`;
            } catch (e) {
                return dateStr;
            }
        },

        formatTimeOnly(dateStr) {
            if (!dateStr) return '';
            try {
                const d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                return d.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).replace('.', ':') + ' WIB';
            } catch (e) {
                return dateStr;
            }
        },

        isItemPendingApproval(item) {
            if (!item) return false;
            const status = String(item.Status_Kunjungan || '').toUpperCase();
            if (['CHECKED_IN', 'CHECKED_OUT', 'KELUAR_TAK_TERCATAT', 'REJECTED', 'WITHDRAWN', 'REVOKED', 'CANCELLED'].includes(status)) {
                return false;
            }
            if (status === 'PENDING_APPROVAL' || String(item.Status_Approval || '').toUpperCase() === 'PENDING') {
                return true;
            }
            if (item.Tipe_Tamu === 'B2B' || item.Status_Permit_Coklat) {
                const permitStatus = String(item.Status_Permit_Coklat || '').toUpperCase();
                if (['MENUNGGU_APPROVAL', 'TANPA_AKSES', 'PENDING', ''].includes(permitStatus)) {
                    return true;
                }
            }
            return false;
        },

        getStatusBadgeLabel(s, item = null) {
            const target = item || this.tamu;
            if (target && this.isItemPendingApproval(target)) {
                return target.Tipe_Tamu === 'B2B' ? 'Menunggu Approval Permit' : 'Menunggu Approval';
            }
            return (
                {
                    PENDING_APPROVAL: 'Menunggu Approval',
                    SCHEDULED: 'Scheduled',
                    CHECKED_IN: 'Check-In',
                    CHECKED_OUT: 'Check-Out',
                    KELUAR_TAK_TERCATAT: 'Keluar Tak Tercatat',
                    CANCELLED: 'Cancelled',
                    REJECTED: 'Ditolak',
                    WITHDRAWN: 'Ditarik Pemohon',
                    REVOKED: 'Dicabut',
                }[s] || s || 'Menunggu'
            );
        },

        getStatusBadgeVariant(s, item = null) {
            const target = item || this.tamu;
            if (target && this.isItemPendingApproval(target)) {
                return 'warning';
            }
            return (
                {
                    PENDING_APPROVAL: 'warning',
                    SCHEDULED: 'info',
                    CHECKED_IN: 'brand',
                    CHECKED_OUT: 'neutral',
                    KELUAR_TAK_TERCATAT: 'warning',
                    CANCELLED: 'danger',
                    REJECTED: 'danger',
                    WITHDRAWN: 'neutral',
                    REVOKED: 'danger',
                }[s] || 'neutral'
            );
        },

        getStatusBadgeIcon(s, item = null) {
            const target = item || this.tamu;
            if (target && this.isItemPendingApproval(target)) {
                return 'hourglass-split';
            }
            return (
                {
                    PENDING_APPROVAL: 'hourglass-split',
                    SCHEDULED: 'calendar-check',
                    CHECKED_IN: 'box-arrow-in-right',
                    CHECKED_OUT: 'box-arrow-right',
                    KELUAR_TAK_TERCATAT: 'question-circle',
                    CANCELLED: 'clock-history',
                    REJECTED: 'x-circle-fill',
                    WITHDRAWN: 'arrow-counterclockwise',
                    REVOKED: 'slash-circle',
                }[s] || 'dash-circle'
            );
        },

        bukaFotoKendaraan(data) {
            if (!data || !data.Foto_Kendaraan_Url) return;

            const plat = (data.No_Plat_Kendaraan || 'Kendaraan Tamu').replace(/\s+/g, '');
            const jenis = data.Jenis_Kendaraan || 'Kendaraan';
            const url = data.Foto_Kendaraan_Url;
            const cleanPath = String(url).split('?')[0].split('#')[0];
            const rawExt = cleanPath.includes('.') ? cleanPath.split('.').pop().toLowerCase() : 'jpg';
            const validExts = ['jpg', 'jpeg', 'png', 'webp', 'jfif', 'pjpeg', 'svg', 'bmp', 'avif'];
            const ext = validExts.includes(rawExt) ? rawExt : 'jpg';

            this.viewerFiles = [{
                url: url,
                name: `foto-kendaraan-${plat}.${ext}`,
                title: `Foto Kendaraan - ${data.No_Plat_Kendaraan || plat} (${jenis})`,
                type: `image/${ext === 'jpg' ? 'jpeg' : ext}`,
                ext: ext,
                allowDownload: true,
                allowPrint: true,
            }];
            this.viewerInitialIndex = 0;
            this.showFileViewer = true;
        },
    },
};
</script>

<style scoped>
.tamu-foto-kendaraan-wrap {
    position: relative;
    border-radius: var(--hcis-radius-md, 0.5rem);
    overflow: hidden;
    cursor: pointer;
    border: 1px solid var(--hcis-border-subtle, rgba(15, 23, 42, 0.08));
    background: var(--hcis-slate-900, #0f172a);
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.tamu-foto-kendaraan-wrap:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px -3px rgba(15, 23, 42, 0.15);
}

.tamu-foto-kendaraan {
    width: 100%;
    max-height: 240px;
    object-fit: cover;
    display: block;
    border-radius: var(--hcis-radius-md, 0.5rem);
    transition: transform 0.3s ease, filter 0.3s ease;
}

.tamu-foto-kendaraan-wrap:hover .tamu-foto-kendaraan {
    transform: scale(1.02);
    filter: brightness(0.92);
}

.tamu-foto-overlay {
    position: absolute;
    bottom: 0.65rem;
    right: 0.65rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: var(--hcis-radius-full, 9999px);
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 600;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    transition: background 0.2s ease;
}

.tamu-foto-kendaraan-wrap:hover .tamu-foto-overlay {
    background: rgba(79, 70, 229, 0.9);
}

/* =========================================================================
   HERO IDENTITY CARD (LEFT PANE)
   ========================================================================= */
.tamu-hero-card {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.04) 0%, var(--hcis-white, #ffffff) 45%, var(--hcis-slate-50, #f8fafc) 100%);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-2xl, 16px);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.03);
    position: relative;
    overflow: hidden;
    color: var(--hcis-slate-800, #1e293b);
    padding: 1.125rem;
    margin-bottom: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.tamu-hero-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--hcis-brand-500, #6366f1), var(--hcis-brand-400, #818cf8));
}

.tamu-hero-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.tamu-hero-category-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.2rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.6875rem;
    font-weight: 750;
    letter-spacing: 0.03em;
    background-color: var(--hcis-brand-50, #eef2ff);
    color: var(--hcis-brand-700, #4338ca);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
}

.tamu-hero-category-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 0 6px var(--hcis-brand-400, #818cf8);
    flex-shrink: 0;
}

.tamu-hero-category-text {
    font-weight: 750;
    white-space: nowrap;
}

.tamu-hero-profile {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
}

.tamu-hero-avatar-box {
    flex-shrink: 0;
    border-radius: 50%;
    border: 2px solid var(--hcis-white, #ffffff);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}

.tamu-hero-profile-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    flex: 1;
}

.tamu-hero-name {
    color: var(--hcis-slate-900, #0f172a);
    font-weight: 750;
    font-size: 1.0625rem;
    line-height: 1.3;
    margin: 0;
    word-break: break-word;
}

.tamu-hero-profile-meta {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--hcis-slate-600, #475569);
}

.tamu-hero-instansi,
.tamu-hero-party-count {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

/* Middle Specs Container */
.tamu-hero-details-container {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.tamu-hero-trx-card {
    background: var(--hcis-white, #ffffff);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.75rem 0.875rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.tamu-hero-trx-info {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.tamu-hero-card-kicker {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-slate-500, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 700;
    font-size: 0.625rem;
    line-height: 1.2;
    white-space: nowrap;
}

.tamu-hero-trx-number {
    font-size: 1.125rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.2;
}

.tamu-hero-subpod-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.625rem;
}

.tamu-hero-subpod {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.625rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
}

.tamu-hero-subpod-val {
    font-size: 0.8125rem;
    line-height: 1.25;
}

.tamu-hero-meta-bar {
    border-top: 1px solid var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-500, #64748b);
    padding-top: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.625rem;
    flex-wrap: wrap;
}

.tamu-hero-date {
    color: var(--hcis-slate-500, #64748b);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.tamu-copy-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    padding: 0;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    background: var(--hcis-slate-50, #f8fafc);
    color: var(--hcis-slate-500, #64748b);
    border-radius: var(--hcis-radius-md, 8px);
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.tamu-copy-btn:hover {
    color: var(--hcis-brand-600, #4f46e5);
    background: var(--hcis-brand-50, #eef2ff);
    border-color: var(--hcis-brand-300, #a5b4fc);
}

/* =========================================================================
   PIC CARD
   ========================================================================= */
.tamu-pic-card {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
}

.tamu-pic-header {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3, 0.75rem);
}

.tamu-pic-avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-brand-500, var(--hcis-indigo-500)) 0%, var(--hcis-brand-700, var(--hcis-indigo-700)) 100%);
    color: var(--hcis-white);
    font-weight: 700;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.tamu-pic-identity {
    min-width: 0;
    flex: 1 1 0%;
}

.tamu-pic-name {
    font-weight: 700;
    font-size: 0.9375rem;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.3;
}

.tamu-pic-nik-tag {
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.tamu-pic-role {
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: 0.75rem;
    font-weight: 500;
    margin-top: 0.15rem;
    line-height: 1.3;
}

.tamu-pic-dept-pod {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-md, 8px);
    padding: var(--hcis-space-2, 0.5rem) var(--hcis-space-3, 0.75rem);
}

.tamu-pic-dept-icon {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
}

.tamu-pic-dept-info {
    display: flex;
    flex-direction: column;
}

.tamu-pic-dept-label {
    font-size: 0.65rem;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    text-transform: uppercase;
}

.tamu-pic-dept-val {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}



/* =========================================================================
   VISITOR STRIP & PROFILE HEADER
   ========================================================================= */
.tamu-visitor-strip {
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.625rem 0.875rem;
}

.tamu-visitor-avatar {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-slate-700, #334155) 0%, var(--hcis-slate-900, #0f172a) 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tamu-visitor-name {
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.25;
}

.tamu-visitor-instansi {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--hcis-slate-500, #64748b);
    font-weight: 500;
    margin-top: 0.15rem;
}

.tamu-mini-pill {
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    background: var(--hcis-slate-100, #f1f5f9);
    color: var(--hcis-slate-700, #334155);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 9999px;
}

.tamu-id-type-badge {
    font-size: 0.625rem;
    font-weight: 700;
    padding: 0.1rem 0.35rem;
    background: var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-700, #334155);
    border-radius: 4px;
    letter-spacing: 0.03em;
}

.tamu-tile-copy-btn {
    background: transparent;
    border: none;
    padding: 0.2rem 0.35rem;
    border-radius: 4px;
    color: var(--hcis-slate-400, #94a3b8);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.tamu-tile-copy-btn:hover {
    background: var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-700, #334155);
}

/* =========================================================================
   SPECS GRID (RIGHT PANE)
   ========================================================================= */
.tamu-specs-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--hcis-space-3, 0.75rem);
}

.tamu-spec-tile--full {
    grid-column: span 2;
}

@media (max-width: 640px) {
    .tamu-specs-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .tamu-spec-tile {
        padding: 0.5rem 0.625rem;
    }

    .tamu-spec-tile--full {
        grid-column: span 1 !important;
    }
}

.tamu-spec-tile {
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 10px);
    padding: var(--hcis-space-3, 0.75rem);
    min-width: 0;
    overflow: hidden;
}

.tamu-spec-tile__header {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.35rem;
}

.tamu-spec-tile__icon {
    color: var(--hcis-slate-400, #94a3b8);
    flex-shrink: 0;
}

.tamu-spec-tile__label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 650;
    color: var(--hcis-slate-500, #64748b);
    display: block;
}

.tamu-spec-tile__val {
    font-size: 0.84rem;
}

.tamu-jendela-compact {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.tamu-jendela-timeline {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    position: relative;
    padding-left: 0.1rem;
    min-width: 0;
}

.tamu-jendela-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    line-height: 1.25;
    min-width: 0;
}

.tamu-jendela-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.tamu-jendela-dot.is-in {
    background-color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 0 0 2px var(--hcis-brand-100, #e0e7ff);
}

.tamu-jendela-dot.is-out {
    background-color: var(--hcis-rose-500, #f43f5e);
    box-shadow: 0 0 0 2px var(--hcis-rose-100, #ffe4e6);
}

.tamu-jendela-connector {
    width: 1.5px;
    height: 6px;
    background-color: var(--hcis-slate-300, #cbd5e1);
    margin-left: 2.25px;
}

.tamu-jendela-text {
    font-weight: 700;
    color: var(--hcis-slate-900, #0f172a);
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    min-width: 0;
}

.tamu-plate-badge {
    background: var(--hcis-slate-900, #0f172a);
    color: var(--hcis-white, #ffffff);
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 700;
    font-size: 0.75rem;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    letter-spacing: 0.05em;
}

.tamu-plate-badge--lg {
    font-size: 0.875rem;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* =========================================================================
   CALLOUTS & NOTES
   ========================================================================= */
.tamu-reason-callout {
    background: var(--hcis-brand-50, #eef2ff);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: var(--hcis-space-3, 0.75rem);
}

.tamu-reason-callout__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.tamu-reason-callout__icon {
    color: var(--hcis-brand-600, #4f46e5);
}

.tamu-reason-callout__title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-brand-900, #312e81);
}

.tamu-reason-callout__tag {
    font-size: 0.65rem;
    color: var(--hcis-brand-600, #4f46e5);
    background: rgba(255, 255, 255, 0.7);
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.tamu-reason-callout__text {
    font-size: 0.8rem;
    color: var(--hcis-slate-700, #334155);
    font-style: italic;
    line-height: 1.4;
}

.tamu-note-callout {
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px dashed var(--hcis-slate-300, #cbd5e1);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.625rem 0.75rem;
}

/* =========================================================================
   TRANSPORT & PARTY
   ========================================================================= */
.tamu-transport-card {
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.75rem;
}

.tamu-transport-icon-box {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: var(--hcis-brand-50, #eef2ff);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    color: var(--hcis-brand-600, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tamu-transport-empty {
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px dashed var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.875rem 1rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.tamu-party-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.tamu-party-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.5rem 0.75rem;
}

.tamu-party-index {
    font-size: 0.6875rem;
    font-weight: 700;
    font-family: var(--hcis-font-mono, monospace);
    color: var(--hcis-slate-500, #64748b);
    background: var(--hcis-slate-200, #e2e8f0);
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* =========================================================================
   PRESENCE LOG
   ========================================================================= */
.tamu-presence-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--hcis-space-3, 0.75rem);
}

@media (max-width: 768px) {
    .tamu-presence-grid {
        grid-template-columns: 1fr;
    }
}

.tamu-presence-card {
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-lg, 10px);
    padding: var(--hcis-space-3, 0.75rem);
}

.tamu-presence-icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tamu-presence-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    font-weight: 650;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    display: block;
}

.tamu-presence-val {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

/* Asal-usul jam kehadiran: tapping / bypass satpam / catatan manual. */
.tamu-presence-sumber {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.3rem;
    padding: 0.1rem 0.4rem;
    border-radius: var(--hcis-radius-sm, 6px);
    font-size: 0.68rem;
    font-weight: 600;
    line-height: 1.4;
}

/* Ramp teal dipakai lewat --hcis-brand-*, yang sengaja digeser satu tingkat
   karena teal-600 hanya 3,29:1 terhadap putih (lihat design-system.css). */
.tamu-presence-sumber--baik {
    color: var(--hcis-brand-600, #0f766e);
    background: var(--hcis-brand-50, #f0fdfa);
}

.tamu-presence-sumber--peringatan {
    color: var(--hcis-amber-700, #b45309);
    background: var(--hcis-amber-50, #fffbeb);
}

.tamu-presence-sumber--netral {
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-slate-100, #f1f5f9);
}

/* =========================================================================
   APPROVAL TIMELINE IN DUAL-PANE SIDEBAR
   ========================================================================= */
.permit-approval-timeline-wrapper :deep(.hcis-timeline-card__head) {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.35rem;
}

.permit-approval-timeline-wrapper :deep(.hcis-timeline-card__actor) {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    min-width: 0;
    width: 100%;
}

.permit-approval-timeline-wrapper :deep(.hcis-timeline-actor-info) {
    min-width: 0;
    flex: 1;
}

.permit-approval-timeline-wrapper :deep(.hcis-timeline-time-badge) {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    margin-left: 2.75rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    white-space: nowrap;
}

/* =========================================================================
   DIALOG LARANGAN PERSETUJUAN PENGAJUAN SENDIRI (FOUR-EYES PRINCIPLE)
   ========================================================================= */
.permit-own-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.permit-own-ticket {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.5) 0%, rgba(255, 251, 235, 0.95) 100%);
    border: 1.5px solid #fde68a;
    box-shadow: 0 2px 6px rgba(217, 119, 6, 0.06);
}

.permit-own-ticket__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 8px;
    background: #ffffff;
    color: #d97706;
    border: 1px solid #fde68a;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(217, 119, 6, 0.1);
}

.permit-own-ticket__body {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    flex: 1 1 auto;
}

.permit-own-ticket__label {
    font-size: 0.65625rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #92400e;
}

.permit-own-ticket__name {
    font-size: 0.9375rem;
    font-weight: 800;
    color: #78350f;
    line-height: 1.25;
}

.permit-own-ticket__meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: #92400e;
    flex-wrap: wrap;
}

.permit-own-ticket__match-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    font-weight: 650;
    color: #b45309;
    background: rgba(254, 243, 199, 0.8);
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    border: 1px solid #fde68a;
    width: fit-content;
    margin-top: 0.15rem;
}

.permit-own-policy {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.75rem 0.85rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.permit-own-policy__header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.permit-own-policy__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 4px;
    background: #e0e7ff;
    color: #4338ca;
    flex-shrink: 0;
}

.permit-own-policy__header strong {
    font-size: 0.78125rem;
    font-weight: 750;
    color: #1e293b;
}

.permit-own-policy__desc {
    font-size: 0.71875rem;
    color: #475569;
    line-height: 1.4;
    margin: 0;
}

.permit-own-steps {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.75rem 0.85rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
}

.permit-own-steps__title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 750;
    color: #92400e;
}

.permit-own-steps__list {
    margin: 0;
    padding-left: 1.15rem;
    font-size: 0.71875rem;
    color: #78350f;
    line-height: 1.4;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.permit-own-steps__list li strong {
    color: #92400e;
}

.permit-own-steps__note {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    color: #b45309;
    margin-top: 0.2rem;
    padding-top: 0.35rem;
    border-top: 1px dashed #fde68a;
}

.permit-own-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
}

.permit-own-actions__btn {
    min-width: 140px;
}

@media (max-width: 576px) {
    .permit-own-ticket {
        padding: 0.75rem 0.85rem;
        gap: 0.6rem;
    }
    .permit-own-ticket__icon {
        width: 2.2rem;
        height: 2.2rem;
    }
    .permit-own-ticket__name {
        font-size: 0.875rem;
    }
    .permit-own-actions__btn {
        width: 100% !important;
        justify-content: center !important;
    }
}

/* =========================================================================
   FOOTER ACTIONS & OWN NOTICE STYLING
   ========================================================================= */
.tamu-modal-footer-wrapper {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
    gap: 0.75rem;
}

.permit-footer-own-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.65rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
    min-width: 0;
    margin-right: auto;
}

.permit-footer-own-notice:hover {
    background: #fef3c7;
    border-color: #f59e0b;
}

.permit-footer-own-notice__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #d97706;
    flex-shrink: 0;
}

.permit-footer-own-notice__text {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.71875rem;
    color: #92400e;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.permit-footer-own-notice__text strong {
    font-weight: 750;
    color: #78350f;
}

.permit-footer-own-notice__text u {
    font-weight: 650;
    color: #b45309;
    text-decoration: underline;
}

.permit-own-alert {
    border-radius: 10px;
}

.permit-own-alert__text {
    font-size: 0.78125rem;
    line-height: 1.4;
    color: #92400e;
    margin: 0;
}

.tamu-modal-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

@media (max-width: 991px) {
    :deep(.hcis-dialog__description) {
        display: none !important;
    }

    .tamu-modal-footer-wrapper {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .permit-footer-own-notice {
        width: 100%;
        justify-content: center;
        padding: 0.45rem 0.75rem;
        margin-right: 0;
    }

    .permit-footer-own-notice__text {
        white-space: normal;
        text-align: center;
        flex-wrap: wrap;
        justify-content: center;
    }

    .tamu-modal-footer-actions {
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
    }

    .tamu-modal-footer-actions .tamu-footer-btn,
    .tamu-modal-footer-actions :deep(.hcis-button),
    .tamu-modal-footer-actions :deep(button) {
        flex: 1 1 0% !important;
        width: 100% !important;
        justify-content: center !important;
    }
}

:deep(.hcis-dialog__title) {
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    min-width: 0 !important;
}

:deep(.hcis-dialog__title-row) {
    flex-wrap: nowrap !important;
    min-width: 0 !important;
}
</style>

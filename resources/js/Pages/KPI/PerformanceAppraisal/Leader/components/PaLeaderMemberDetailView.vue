<template>
    <div class="pa-member-detail-view" aria-label="Hasil penilaian anggota tim">
        <!-- 1. Top Navigation & Action Strip (Sticky & Compact on Mobile) -->
        <div class="pa-member-nav-strip">
            <!-- Desktop Layout (Single-row executive cockpit) -->
            <div class="pa-member-nav-strip__desktop">
                <div class="pa-member-nav-strip__left">
                    <HcisButton
                        v-if="showBackAction"
                        label="Kembali ke Daftar Tim"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-left"
                        aria-label="Kembali ke daftar anggota tim"
                        @click="$emit('back')"
                    />
                    <span v-if="showBackAction" class="pa-member-nav-strip__divider" aria-hidden="true" />
                    <div class="pa-member-nav-strip__title-group">
                        <span class="pa-eyebrow">DETAIL EVALUASI</span>
                        <h2 class="pa-member-nav-strip__name">{{ detail?.nama || 'Anggota Tim' }}</h2>
                    </div>
                </div>

                <div class="pa-member-nav-strip__right">
                    <!-- Quick Member Picker Modal Trigger Button (Scalable for many members) -->
                    <HcisButton
                        v-if="activeTeamMembers && activeTeamMembers.length > 1"
                        :label="`Daftar Tim (${activeTeamMembers.length})`"
                        variant="secondary"
                        size="sm"
                        leading-icon="people-fill"
                        trailing-icon="chevron-down"
                        aria-label="Buka daftar pilihan anggota tim"
                        title="Buka daftar seluruh anggota tim untuk memilih cepat"
                        @click="bukaModalPilihAnggota"
                    />

                    <!-- Sequential Member Navigation (Fast Previous/Next Switcher) -->
                    <div v-if="activeTeamMembers && activeTeamMembers.length > 1" class="pa-member-stepper-pill">
                        <button
                            type="button"
                            class="pa-member-stepper-btn"
                            :disabled="!prevMember"
                            :title="prevMember ? `Buka detail ${prevMember.nama}` : 'Sudah di anggota pertama'"
                            aria-label="Lihat anggota sebelumnya"
                            @click="prevMember && $emit('select-member', prevMember.participant_id)"
                        >
                            <HcisIcon name="chevron-left" size="xs" />
                        </button>
                        <div class="pa-member-stepper-content" :title="`Anggota ke-${currentMemberIndex + 1} dari ${activeTeamMembers.length}`">
                            <span class="pa-member-stepper-current">{{ currentMemberIndex + 1 }}</span>
                            <span class="pa-member-stepper-slash">/</span>
                            <span class="pa-member-stepper-total">{{ activeTeamMembers.length }}</span>
                            <span class="pa-member-stepper-unit">Anggota</span>
                        </div>
                        <button
                            type="button"
                            class="pa-member-stepper-btn"
                            :disabled="!nextMember"
                            :title="nextMember ? `Buka detail ${nextMember.nama}` : 'Sudah di anggota terakhir'"
                            aria-label="Lihat anggota berikutnya"
                            @click="nextMember && $emit('select-member', nextMember.participant_id)"
                        >
                            <HcisIcon name="chevron-right" size="xs" />
                        </button>
                    </div>

                    <HcisButton
                        label="Segarkan"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="loading"
                        aria-label="Segarkan detail anggota"
                        title="Segarkan data detail"
                        @click="$emit('retry')"
                    />
                </div>
            </div>

            <!-- Mobile Layout (Dedicated ergonomic mobile cockpit) -->
            <div class="pa-member-nav-strip__mobile">
                <!-- Top Actions Bar -->
                <div class="pa-member-nav-strip__mobile-top">
                    <HcisButton
                        v-if="showBackAction"
                        label="Kembali"
                        variant="glass"
                        size="xs"
                        leading-icon="arrow-left"
                        aria-label="Kembali ke daftar anggota tim"
                        @click="$emit('back')"
                    />

                    <!-- Center Stepper Controls -->
                    <div v-if="activeTeamMembers && activeTeamMembers.length > 1" class="pa-member-stepper-pill is-mobile">
                        <button
                            type="button"
                            class="pa-member-stepper-btn"
                            :disabled="!prevMember"
                            :title="prevMember ? `Buka detail ${prevMember.nama}` : 'Sudah di anggota pertama'"
                            aria-label="Lihat anggota sebelumnya"
                            @click="prevMember && $emit('select-member', prevMember.participant_id)"
                        >
                            <HcisIcon name="chevron-left" size="xs" />
                        </button>
                        <div class="pa-member-stepper-content">
                            <span class="pa-member-stepper-current">{{ currentMemberIndex + 1 }}</span>
                            <span class="pa-member-stepper-slash">/</span>
                            <span class="pa-member-stepper-total">{{ activeTeamMembers.length }}</span>
                        </div>
                        <button
                            type="button"
                            class="pa-member-stepper-btn"
                            :disabled="!nextMember"
                            :title="nextMember ? `Buka detail ${nextMember.nama}` : 'Sudah di anggota terakhir'"
                            aria-label="Lihat anggota berikutnya"
                            @click="nextMember && $emit('select-member', nextMember.participant_id)"
                        >
                            <HcisIcon name="chevron-right" size="xs" />
                        </button>
                    </div>

                    <div class="pa-member-nav-mobile-actions">
                        <HcisButton
                            v-if="activeTeamMembers && activeTeamMembers.length > 1"
                            variant="glass"
                            size="xs"
                            leading-icon="people-fill"
                            :icon-only="true"
                            aria-label="Buka daftar anggota tim"
                            :title="`Buka daftar ${activeTeamMembers.length} anggota tim`"
                            @click="bukaModalPilihAnggota"
                        />
                        <HcisButton
                            variant="glass"
                            size="xs"
                            leading-icon="arrow-clockwise"
                            :icon-only="true"
                            :loading="loading"
                            aria-label="Segarkan detail anggota"
                            title="Segarkan data detail"
                            @click="$emit('retry')"
                        />
                    </div>
                </div>

                <!-- Member Context Row (Uncramped, Interactive Picker Trigger) -->
                <div
                    class="pa-member-nav-strip__mobile-member"
                    :class="{ 'is-interactive': activeTeamMembers && activeTeamMembers.length > 1 }"
                    :role="activeTeamMembers && activeTeamMembers.length > 1 ? 'button' : undefined"
                    :tabindex="activeTeamMembers && activeTeamMembers.length > 1 ? 0 : undefined"
                    :title="activeTeamMembers && activeTeamMembers.length > 1 ? 'Ketuk untuk memilih anggota tim lain' : undefined"
                    @click="activeTeamMembers && activeTeamMembers.length > 1 && bukaModalPilihAnggota()"
                    @keydown.enter.prevent="activeTeamMembers && activeTeamMembers.length > 1 && bukaModalPilihAnggota()"
                    @keydown.space.prevent="activeTeamMembers && activeTeamMembers.length > 1 && bukaModalPilihAnggota()"
                >
                    <div class="pa-member-nav-avatar" aria-hidden="true">
                        {{ inisial(detail?.nama) }}
                    </div>
                    <div class="pa-member-nav-info">
                        <div class="pa-member-nav-eyebrow-row">
                            <span class="pa-eyebrow">DETAIL EVALUASI</span>
                            <span v-if="currentMemberIndex >= 0 && activeTeamMembers.length > 1" class="pa-member-nav-pos-pill">
                                {{ currentMemberIndex + 1 }} dari {{ activeTeamMembers.length }}
                                <HcisIcon name="chevron-expand" size="2xs" />
                            </span>
                        </div>
                        <h2 class="pa-member-nav-strip__name">{{ detail?.nama || 'Anggota Tim' }}</h2>
                    </div>
                    <HcisBadge
                        v-if="detail && (detail.terbit || detail.dirilis_ke_leader) && detail.kode_grade"
                        :label="`Grade ${displayGrade(detail.kode_grade)}`"
                        :variant="getGradeSentiment(detail.kode_grade)"
                        appearance="subtle"
                        size="xs"
                        class="pa-member-nav-grade-badge"
                    />
                </div>
            </div>
        </div>

        <!-- Error State -->
        <HcisResultState
            v-if="error"
            :status="errorStatus === 403 ? 'warning' : 'danger'"
            variant="glass"
            layout="compact"
            :title="errorStatus === 403 ? 'Detail anggota tidak dapat diakses' : 'Detail anggota gagal dimuat'"
            :description="error"
            :error-code="errorCode"
            :request-id="requestId"
            :show-retry="errorStatus !== 403"
            primary-text="Coba lagi"
            primary-icon="arrow-clockwise"
            @primary-click="$emit('retry')"
        />

        <!-- Non-destructive Floating Sync Badge -->
        <transition name="hcis-loading-pop">
            <div v-if="loading && detail" class="pa-member-syncing-badge" aria-live="polite">
                <div class="pa-member-spinner-box" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="pa-member-spinner-svg">
                        <circle cx="12" cy="12" r="9" fill="none" stroke-width="2.5" class="pa-member-spinner-track" />
                        <circle cx="12" cy="12" r="9" fill="none" stroke-width="2.5" class="pa-member-spinner-head" />
                    </svg>
                </div>
                <span class="pa-member-syncing-label">Memuat detail anggota tim…</span>
            </div>
        </transition>

        <!-- Loading Skeleton Suite (Initial Member Load) -->
        <div v-if="loading && !detail" class="pa-member-detail-skeleton" aria-live="polite">
            <!-- Profile Banner Skeleton -->
            <div class="pa-skeleton-profile-banner">
                <div class="pa-skeleton-avatar pa-shimmer" />
                <div class="pa-skeleton-profile-content">
                    <div class="pa-skeleton-line is-eyebrow pa-shimmer" />
                    <div class="pa-skeleton-line is-name pa-shimmer" />
                    <div class="pa-skeleton-meta-chips">
                        <div class="pa-skeleton-chip pa-shimmer" />
                        <div class="pa-skeleton-chip pa-shimmer" />
                        <div class="pa-skeleton-chip pa-shimmer" />
                    </div>
                </div>
            </div>

            <!-- Hero Cockpit Card Skeleton -->
            <HcisCard variant="glass" sentiment="teal" pattern="rings" :size="isMobile ? 'sm' : 'md'" class="pa-skeleton-hero-card">
                <div class="pa-skeleton-hero-cockpit">
                    <div class="pa-skeleton-pillar is-grade">
                        <div class="pa-skeleton-circle pa-shimmer" />
                        <div class="pa-skeleton-line is-sub pa-shimmer" />
                    </div>
                    <div class="pa-skeleton-pillar is-score">
                        <div class="pa-skeleton-line is-score-val pa-shimmer" />
                        <div class="pa-skeleton-line is-bar pa-shimmer" />
                    </div>
                    <div class="pa-skeleton-pillar is-comp">
                        <div class="pa-skeleton-line is-comp-item pa-shimmer" />
                        <div class="pa-skeleton-line is-bar pa-shimmer" />
                        <div class="pa-skeleton-line is-comp-item pa-shimmer" />
                        <div class="pa-skeleton-line is-bar pa-shimmer" />
                    </div>
                </div>
                <div class="pa-skeleton-meta-grid">
                    <div v-for="i in 4" :key="i" class="pa-skeleton-meta-box pa-shimmer" />
                </div>
            </HcisCard>

            <!-- Dual Chart / Qualitative Skeleton Cards -->
            <div class="pa-skeleton-dual-grid">
                <HcisCard variant="glass" sentiment="purple" pattern="rings" :size="isMobile ? 'sm' : 'md'" class="pa-skeleton-chart-card">
                    <div class="pa-skeleton-radar-box pa-shimmer" />
                </HcisCard>
                <div class="pa-skeleton-qualitative-col">
                    <HcisCard variant="glass" sentiment="teal" pattern="rings" :size="isMobile ? 'sm' : 'md'" class="pa-skeleton-qual-card">
                        <div class="pa-skeleton-line is-quote pa-shimmer" />
                        <div class="pa-skeleton-line is-quote-sub pa-shimmer" />
                    </HcisCard>
                    <HcisCard variant="glass" sentiment="purple" pattern="rings" :size="isMobile ? 'sm' : 'md'" class="pa-skeleton-qual-card">
                        <div class="pa-skeleton-line is-quote pa-shimmer" />
                        <div class="pa-skeleton-line is-quote-sub pa-shimmer" />
                    </HcisCard>
                </div>
            </div>
        </div>

        <!-- Detail Loaded -->
        <div v-else-if="detail" class="pa-member-detail-body" :class="{ 'is-loading-dimmed': loading }">
            <!-- 3. Member Profile Banner Card -->
            <div class="pa-member-profile-banner">
                <div class="pa-member-profile-banner__main">
                    <div class="pa-member-profile-banner__avatar" aria-hidden="true">
                        {{ inisial(detail.nama) }}
                    </div>
                    <div class="pa-member-profile-banner__copy">
                        <div class="pa-member-profile-banner__eyebrow-row">
                            <span class="pa-eyebrow">ANGGOTA TIM</span>
                            <HcisBadge
                                :label="statusLabel(detail.status_hasil)"
                                :status="detail.status_hasil"
                                :variant="statusVariant(detail.status_hasil)"
                                appearance="glass"
                                size="xs"
                                dot
                            />
                            <HcisBadge
                                v-if="detail.terbit && detail.dikalibrasi === true"
                                label="Dikalibrasi"
                                variant="purple"
                                appearance="glass"
                                size="xs"
                                dot
                            />
                        </div>
                        <h1 class="pa-member-profile-banner__name">{{ detail.nama || 'Nama belum tersedia' }}</h1>
                        <div class="pa-member-profile-banner__meta">
                            <div class="pa-profile-meta-chip">
                                <HcisIcon name="person-badge" size="2xs" class="pa-profile-meta-chip__icon" />
                                <span class="pa-profile-meta-chip__label">Kode:</span>
                                <strong class="pa-profile-meta-chip__value">{{ detail.kode_karyawan || '—' }}</strong>
                            </div>
                            <div class="pa-profile-meta-chip">
                                <HcisIcon name="briefcase" size="2xs" class="pa-profile-meta-chip__icon" />
                                <span class="pa-profile-meta-chip__label">Jabatan:</span>
                                <strong class="pa-profile-meta-chip__value">{{ detail.nama_jabatan || '—' }}</strong>
                            </div>
                            <div v-if="detail.status_peserta" class="pa-profile-meta-chip">
                                <HcisIcon name="check-circle" size="2xs" class="pa-profile-meta-chip__icon" />
                                <span class="pa-profile-meta-chip__label">Status:</span>
                                <strong class="pa-profile-meta-chip__value">{{ statusPesertaLabel(detail.status_peserta) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leader Assessment Quick Action Button (If form link available) -->
                <div v-if="detail.penugasan_leader?.url_ujian" class="pa-member-profile-banner__actions">
                    <a
                        :href="detail.penugasan_leader.url_ujian"
                        class="pa-leader-form-cta-btn"
                        :class="{ 'is-completed': detail.penugasan_leader.sudah_dinilai }"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <HcisIcon :name="detail.penugasan_leader.sudah_dinilai ? 'clipboard-check-fill' : 'pencil-square'" size="xs" />
                        <span>{{ detail.penugasan_leader.sudah_dinilai ? 'Buka Lembar Penilaian Saya' : 'Mulai Nilai Anggota' }}</span>
                        <HcisIcon name="box-arrow-up-right" size="2xs" />
                    </a>
                </div>
            </div>

            <!-- 4. Leader Assessment Highlight Box (If Available) -->
            <div v-if="detail.penugasan_leader" class="pa-leader-assessment-strip">
                <div class="pa-leader-assessment-strip__icon">
                    <HcisIcon :name="detail.penugasan_leader.sudah_dinilai ? 'check2-circle' : 'pencil'" size="sm" />
                </div>
                <div class="pa-leader-assessment-strip__body">
                    <div class="pa-leader-assessment-strip__title-row">
                        <strong>Penilaian Anda Sebagai Atasan (Leader)</strong>
                        <HcisBadge
                            :label="detail.penugasan_leader.sudah_dinilai ? 'Selesai Disubmit' : (detail.penugasan_leader.status_penugasan || 'Belum Diisi')"
                            :variant="detail.penugasan_leader.sudah_dinilai ? 'success' : 'warning'"
                            size="2xs"
                            appearance="solid"
                            dot
                        />
                    </div>
                    <p class="pa-leader-assessment-strip__desc">
                        {{ detail.penugasan_leader.sudah_dinilai
                            ? `Formulir penilaian kinerja untuk ${detail.nama} telah berhasil Anda selesaikan dan tercatat di sistem.`
                            : `Anda memiliki penugasan aktif untuk menilai kompetensi ${detail.nama}.`
                        }}
                    </p>
                </div>
                <a
                    v-if="detail.penugasan_leader.url_ujian"
                    :href="detail.penugasan_leader.url_ujian"
                    class="pa-leader-assessment-strip__action"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <HcisIcon name="eye-fill" size="2xs" />
                    <span>Lihat Lembar Penilaian</span>
                </a>
            </div>

            <!-- 5. Not Published State / Calibration Stage Notice -->
            <HcisAlert
                v-if="!detail.terbit && detail.dirilis_ke_leader"
                variant="info"
                icon="people-fill"
                title="Dirilis untuk Anda, belum untuk karyawannya"
                description="Angka ini dibuka lebih dulu kepada atasan. Karyawan yang bersangkutan belum bisa melihatnya di PA Saya."
                data-uji="hanya-leader-detail"
                class="mb-3"
            />

            <!-- State Kosong Saat Hasil Belum Terbit -->
            <div v-if="!detail.terbit && !detail.dirilis_ke_leader" class="pa-member-unpublished-state">
                <HcisEmptyState
                    title="Hasil Penilaian Belum Diterbitkan"
                    description="Hasil evaluasi kinerja resmi, skor akhir, dan kuadran talenta untuk anggota tim ini belum dipublikasikan oleh komite evaluasi."
                    icon="hourglass-split"
                    variant="glass"
                    sentiment="neutral"
                    size="md"
                >
                    <template v-if="detail.alasan_tertahan" #actions>
                        <HcisAlert
                            variant="warning"
                            appearance="subtle"
                            icon="exclamation-triangle-fill"
                            size="xs"
                            :pattern="false"
                            title="Status Tertahan"
                        >
                            {{ detail.alasan_tertahan }}
                        </HcisAlert>
                    </template>
                </HcisEmptyState>
            </div>

            <!-- 6. Official Published Evaluation Cockpit (Executive Compact Experience) -->
            <template v-else>
                <!-- Hero Cockpit Card (Consolidated single source of metrics - Zero Duplicate Clutter) -->
                <HcisCard
                    title="Hasil Penilaian Kinerja Resmi"
                    :description="`${detail.nama_cycle || detail.kode_cycle || 'Cycle PA'} · Diterbitkan: ${formatTanggalWaktu(detail.published_at)}`"
                    icon="award-fill"
                    sentiment="teal"
                    variant="glass"
                    pattern="rings"
                    :size="isMobile ? 'sm' : 'md'"
                    class="pa-member-hero-card"
                >
                    <template #actions>
                        <div class="pa-hero-header-actions">
                            <HcisStatusBadge
                                label="Diterbitkan Resmi"
                                variant="success"
                                dot
                            />
                            <HcisBadge
                                v-if="detail.dikalibrasi === true"
                                label="Dikalibrasi"
                                variant="purple"
                                appearance="glass"
                                size="xs"
                                dot
                            />
                        </div>
                    </template>

                    <!-- 3-Pillar Executive Showcase Cockpit -->
                    <div class="pa-hero-cockpit">
                        <!-- Pillar 1: GRADE FINAL SPOTLIGHT -->
                        <div class="pa-hero-pillar pa-hero-pillar--grade" :class="gradeThemeClass">
                            <div class="pa-grade-medallion">
                                <div class="pa-grade-medallion__ring">
                                    <span class="pa-grade-medallion__letter">{{ displayGrade(detail.kode_grade) }}</span>
                                </div>
                                <div class="pa-grade-medallion__meta">
                                    <div class="pa-grade-medallion__eyebrow-row">
                                        <span class="pa-eyebrow">GRADE FINAL RESMI</span>
                                        <span v-if="detail.dikalibrasi" class="pa-grade-calibrated-tag" title="Hasil disepakati melalui forum kalibrasi manajemen">
                                            <HcisIcon name="shield-check" size="2xs" />
                                            Forum Kalibrasi
                                        </span>
                                    </div>
                                    <strong class="pa-grade-medallion__predicate">
                                        {{ gradePredicate(detail.kode_grade, detail.nama_grade) }}
                                    </strong>
                                    <small class="pa-grade-medallion__sub">
                                        Peringkat pencapaian kinerja resmi anggota tim
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Pillar 2: FINAL SCORE & PROGRESS GAUGE -->
                        <div class="pa-hero-pillar pa-hero-pillar--score">
                            <div class="pa-hero-pillar__head">
                                <span class="pa-eyebrow">FINAL SCORE</span>
                            </div>
                            <div class="pa-hero-score-number-row">
                                <strong class="pa-hero-score-value">{{ formatScore(detail.nilai_akhir) }}</strong>
                                <span class="pa-hero-score-unit">poin</span>
                            </div>
                            <div class="pa-hero-score-progress">
                                <div
                                    class="pa-hero-score-progress__bar"
                                    :style="{
                                        width: `${Math.min(100, Math.max(0, Number(detail.nilai_akhir) || 0))}%`,
                                        background: getScoreGradient(detail.nilai_akhir)
                                    }"
                                />
                            </div>
                            <small class="pa-hero-score-caption">
                                {{ detail.bobot_kpi != null && detail.bobot_kompetensi != null
                                    ? `KPI × ${formatScore(detail.bobot_kpi)}% + Kompetensi × ${formatScore(detail.bobot_kompetensi)}%`
                                    : 'Akumulasi terbobot KPI & Kompetensi 360°' }}
                            </small>
                        </div>

                        <!-- Pillar 3: KOMPOSISI PILAR (KPI VS KOMPETENSI 360°) -->
                        <div class="pa-hero-pillar pa-hero-pillar--composition">
                            <div class="pa-hero-pillar__head">
                                <span class="pa-eyebrow">KOMPOSISI NILAI AKHIR</span>
                                <span class="pa-composition-tag">2 Pilar Evaluasi</span>
                            </div>

                            <div class="pa-composition-list">
                                <!-- Pilar 1: Nilai KPI -->
                                <div class="pa-composition-item">
                                    <div class="pa-composition-item__row">
                                        <div class="pa-composition-item__left">
                                            <HcisIcon name="graph-up-arrow" size="2xs" class="text-brand" />
                                            <span class="pa-composition-item__label">Kinerja (KPI)</span>
                                        </div>
                                        <div class="pa-composition-item__right">
                                            <strong class="pa-composition-item__score">{{ formatScore(detail.nilai_kpi) }}</strong>
                                            <span v-if="detail.bobot_kpi != null" class="pa-composition-item__scale">bobot {{ formatScore(detail.bobot_kpi) }}%</span>
                                        </div>
                                    </div>
                                    <div class="pa-composition-track" aria-hidden="true">
                                        <div
                                            class="pa-composition-bar is-kpi"
                                            :style="{ width: `${Math.min(100, Math.max(0, Number(detail.nilai_kpi) || 0))}%` }"
                                        />
                                    </div>
                                </div>

                                <!-- Pilar 2: Nilai Kompetensi -->
                                <div class="pa-composition-item">
                                    <div class="pa-composition-item__row">
                                        <div class="pa-composition-item__left">
                                            <HcisIcon name="stars" size="2xs" class="text-purple" />
                                            <span class="pa-composition-item__label">Kompetensi 360°</span>
                                        </div>
                                        <div class="pa-composition-item__right">
                                            <strong class="pa-composition-item__score">{{ formatScore(detail.nilai_kompetensi) }}</strong>
                                            <span v-if="detail.bobot_kompetensi != null" class="pa-composition-item__scale">bobot {{ formatScore(detail.bobot_kompetensi) }}%</span>
                                        </div>
                                    </div>
                                    <div class="pa-composition-track" aria-hidden="true">
                                        <div
                                            class="pa-composition-bar is-competency"
                                            :style="{ width: `${Math.min(100, Math.max(0, Number(detail.nilai_kompetensi) || 0))}%` }"
                                        />
                                    </div>
                                </div>
                            </div>

                            <small class="pa-composition-caption">
                                Target operasional kerja &amp; evaluasi perilaku 360°
                            </small>
                        </div>
                    </div>

                    <!-- Meta Grid: Cycle Info & Level (Matching PA Saya) -->
                    <div class="pa-member-hero-meta" aria-label="Informasi Periode dan Jabatan">
                        <div class="pa-hero-meta-item">
                            <span class="pa-hero-meta-item__label">Cycle</span>
                            <strong class="pa-hero-meta-item__value" :title="detail.nama_cycle || detail.kode_cycle || '—'">
                                {{ detail.nama_cycle || detail.kode_cycle || '—' }}
                            </strong>
                        </div>
                        <div class="pa-hero-meta-item">
                            <span class="pa-hero-meta-item__label">Kode Cycle</span>
                            <strong class="pa-hero-meta-item__value" :title="detail.kode_cycle || '—'">
                                {{ detail.kode_cycle || '—' }}
                            </strong>
                        </div>
                        <div class="pa-hero-meta-item">
                            <span class="pa-hero-meta-item__label">Tahun</span>
                            <strong class="pa-hero-meta-item__value">
                                {{ detail.tahun || '—' }}
                            </strong>
                        </div>
                        <div class="pa-hero-meta-item">
                            <span class="pa-hero-meta-item__label">Level Jabatan</span>
                            <strong class="pa-hero-meta-item__value" :title="detail.nama_level || detail.nama_jabatan || '—'">
                                {{ detail.nama_level || detail.nama_jabatan || '—' }}
                            </strong>
                        </div>
                    </div>
                </HcisCard>

                <!-- 7. HAV TALENT QUADRANT SHOWCASE CARD (9-BOX) (Leader Exclusive) -->
                <HcisCard
                    v-if="memberKuadran"
                    variant="glass"
                    sentiment="purple"
                    pattern="dots"
                    :size="isMobile ? 'sm' : 'md'"
                    class="pa-member-hav-card"
                >
                    <div class="pa-member-hav-banner">
                        <div class="pa-member-hav-banner__main">
                            <div class="pa-member-hav-icon-box" :style="memberKuadran.Warna ? { backgroundColor: memberKuadran.Warna + '18', color: memberKuadran.Warna } : undefined">
                                <HcisIcon name="grid-3x3-gap-fill" size="md" />
                            </div>
                            <div class="pa-member-hav-banner__info">
                                <div class="pa-member-hav-eyebrow-row">
                                    <span class="pa-eyebrow">PEMETAAN BAKAT HAV (9-BOX)</span>
                                    <HcisBadge
                                        :label="`Box ${memberKuadran.Nomor_Box ?? '—'}`"
                                        variant="purple"
                                        appearance="solid"
                                        size="xs"
                                    />
                                </div>
                                <h3 class="pa-member-hav-title">
                                    {{ memberKuadran.Nama_Kuadran || detail.kode_kuadran || 'Pemetaan Bakat HAV' }}
                                </h3>
                                <p class="pa-member-hav-desc">
                                    {{ memberKuadran.Keterangan || 'Pemetaan posisi talenta resmi berdasarkan kombinasi capaian kinerja KPI dan kompetensi 360°.' }}
                                </p>
                            </div>
                        </div>

                        <div class="pa-member-hav-banner__cta">
                            <div class="pa-member-hav-meta-pill">
                                <HcisIcon name="geo-alt-fill" size="2xs" />
                                <span>{{ getQuadrantPosition(memberKuadran.Nomor_Box) }}</span>
                            </div>
                            <HcisButton
                                label="Buka Matriks 9-Box HAV"
                                variant="primary"
                                :size="isMobile ? 'xs' : 'sm'"
                                leading-icon="zoom-in"
                                class="pa-hav-open-btn"
                                @click="showHavModal = true"
                            />
                        </div>
                    </div>
                </HcisCard>

                <!-- 8. Dual Visual Section: Radar Kompetensi 360° & Kekuatan (Strength) / Area Pengembangan (Matching PA Saya) -->
                <div class="pa-member-charts-section mb-3">
                    <div
                        class="pa-result-charts-grid"
                        :class="{ 'is-single-col': !radarKompetensi.length }"
                    >
                        <!-- Left: Radar Chart -->
                        <HcisChart
                            v-if="radarKompetensi.length"
                            type="radar"
                            title="Radar Kompetensi 360°"
                            :description="isMobile ? '' : (radarSeries.length > 1
                                ? 'Capaian anggota dibandingkan ambang (KKM) tiap kompetensi'
                                : 'Peta keseimbangan profil kompetensi lintas dimensi')"
                            icon="pentagon-fill"
                            sentiment="purple"
                            variant="glass"
                            pattern="rings"
                            :height="radarResultHeight"
                            :categories="radarCategories"
                            :series="radarSeries"
                            :metric-value="isMobile ? '' : `${radarKompetensi.length} Dimensi`"
                            :metric-label="isMobile ? '' : 'Cakupan Penilaian'"
                            allow-export
                        />

                        <!-- Right: Kekuatan (Strength) & Area Pengembangan (Development Area) Column -->
                        <div class="pa-qualitative-column">
                            <!-- Card 1: Kekuatan (Strength) -->
                            <HcisCard
                                :title="strengthData.nama || 'Kekuatan Utama'"
                                :description="getCatatanDescription(strengthData.nama)"
                                :icon="getCatatanIcon(strengthData.nama)"
                                sentiment="teal"
                                variant="glass"
                                pattern="rings"
                                :size="isMobile ? 'sm' : 'md'"
                                class="pa-qualitative-card"
                            >
                                <div v-if="strengthData.kesimpulan" class="pa-qualitative-card__body">
                                    <div class="pa-qualitative-card__item">
                                        <span class="pa-qualitative-card__quote-mark" aria-hidden="true">“</span>
                                        <div
                                            class="pa-qualitative-card__text"
                                            v-html="htmlAman(strengthData.kesimpulan)"
                                        />
                                    </div>
                                </div>
                                <div v-else class="pa-qualitative-card__empty">
                                    <div class="pa-qualitative-card__empty-avatar is-strength">
                                        <HcisIcon name="hand-thumbs-up" class="pa-qualitative-card__empty-icon" />
                                    </div>
                                    <div class="pa-qualitative-card__empty-content">
                                        <span class="pa-qualitative-card__empty-title">Belum Ada Catatan Keunggulan</span>
                                        <p class="pa-qualitative-card__empty-desc">
                                            Poin keunggulan dan kekuatan utama belum dicatatkan untuk periode evaluasi ini.
                                        </p>
                                    </div>
                                    <span class="pa-qualitative-card__empty-badge">
                                        <HcisIcon name="dash-circle" size="2xs" />
                                        <span>Belum Ditetapkan</span>
                                    </span>
                                </div>
                            </HcisCard>

                            <!-- Card 2: Area Pengembangan (Development Area) -->
                            <HcisCard
                                :title="devAreaData.nama || 'Area Pengembangan'"
                                :description="getCatatanDescription(devAreaData.nama)"
                                :icon="getCatatanIcon(devAreaData.nama)"
                                sentiment="purple"
                                variant="glass"
                                pattern="rings"
                                :size="isMobile ? 'sm' : 'md'"
                                class="pa-qualitative-card"
                            >
                                <div v-if="devAreaData.kesimpulan" class="pa-qualitative-card__body">
                                    <div class="pa-qualitative-card__item">
                                        <span class="pa-qualitative-card__quote-mark" aria-hidden="true">“</span>
                                        <div
                                            class="pa-qualitative-card__text"
                                            v-html="htmlAman(devAreaData.kesimpulan)"
                                        />
                                    </div>
                                </div>
                                <div v-else class="pa-qualitative-card__empty">
                                    <div class="pa-qualitative-card__empty-avatar is-dev">
                                        <HcisIcon name="lightbulb" class="pa-qualitative-card__empty-icon" />
                                    </div>
                                    <div class="pa-qualitative-card__empty-content">
                                        <span class="pa-qualitative-card__empty-title">Belum Ada Rekomendasi Pengembangan</span>
                                        <p class="pa-qualitative-card__empty-desc">
                                            Rekomendasi area pengembangan kompetensi belum dicatatkan untuk periode ini.
                                        </p>
                                    </div>
                                    <span class="pa-qualitative-card__empty-badge">
                                        <HcisIcon name="dash-circle" size="2xs" />
                                        <span>Belum Ditetapkan</span>
                                    </span>
                                </div>
                            </HcisCard>
                        </div>
                    </div>
                </div>

                <!-- 9. Catatan Kualitatif Lainnya (Jika Ada Dimensi Tambahan di Luar Strength & Dev Area) -->
                <div v-if="otherCatatanList.length" class="pa-member-qualitative-grid mb-3">
                    <HcisCard
                        v-for="grup in otherCatatanList"
                        :key="`catatan-${grup.kode}`"
                        :title="grup.nama"
                        :description="getCatatanDescription(grup.nama)"
                        :icon="getCatatanIcon(grup.nama)"
                        :sentiment="getCatatanSentiment(grup.nama)"
                        variant="glass"
                        pattern="rings"
                        :size="isMobile ? 'sm' : 'md'"
                        class="pa-qualitative-card"
                    >
                        <div class="pa-qualitative-card__body">
                            <div class="pa-qualitative-callout pa-qualitative-callout--brand">
                                <div class="pa-qualitative-callout__head">
                                    <HcisIcon name="journal-text" size="2xs" />
                                    <span>Catatan Resmi:</span>
                                </div>
                                <div
                                    class="pa-qualitative-callout__text"
                                    v-html="htmlAman(grup.kesimpulan)"
                                />
                            </div>
                        </div>
                    </HcisCard>
                </div>

                <!-- 10. Profil Rincian Agregat Kompetensi (Interactive Cards with Modal Dialog) -->
                <HcisCard
                    v-if="detail.kompetensi?.length"
                    title="Profil Agregat Kompetensi"
                    description="Rincian capaian nilai dan definisi standar perilaku untuk masing-masing kompetensi lintas penilai. Klik kartu untuk membuka modal rincian & expected behaviour."
                    icon="diagram-3-fill"
                    sentiment="purple"
                    variant="glass"
                    pattern="dots"
                    :size="isMobile ? 'sm' : 'md'"
                    class="pa-member-competency-card"
                >
                    <div class="pa-competency-grid">
                        <div
                            v-for="item in detail.kompetensi"
                            :key="`${item.kode_kompetensi || item.Kode_Competency}-${item.kode_type || item.Kode_Type}`"
                            class="pa-competency-item"
                            role="button"
                            tabindex="0"
                            :title="`Buka detail kompetensi ${item.nama_kompetensi || item.Nama_Competency}`"
                            @click="bukaDetailKompetensi(item)"
                            @keydown.enter.prevent="bukaDetailKompetensi(item)"
                            @keydown.space.prevent="bukaDetailKompetensi(item)"
                        >
                            <div class="pa-competency-item__header">
                                <div class="pa-competency-item__title-group">
                                    <span class="pa-competency-item__badge">
                                        {{ item.nama_type || item.kode_type || item.Nama_Type || 'Kompetensi' }}
                                    </span>
                                    <h4 class="pa-competency-item__title">
                                        {{ item.nama_kompetensi || item.Kode_Competency || item.kode_kompetensi || '—' }}
                                    </h4>
                                </div>

                                <div class="pa-competency-item__score-group">
                                    <div class="pa-competency-item__score-display">
                                        <span class="pa-competency-item__score-number">{{ formatScore(item.nilai ?? item.Nilai) }}</span>
                                        <span class="pa-competency-item__score-scale">/ 100</span>
                                    </div>
                                    <span
                                        v-if="item.nilai_ambang || item.Nilai_Ambang"
                                        class="pa-competency-item__kkm-pill"
                                        :class="{
                                            'is-pass': Number(item.nilai ?? item.Nilai) >= Number(item.nilai_ambang ?? item.Nilai_Ambang),
                                            'is-under': Number(item.nilai ?? item.Nilai) < Number(item.nilai_ambang ?? item.Nilai_Ambang)
                                        }"
                                    >
                                        Ambang: {{ formatScore(item.nilai_ambang ?? item.Nilai_Ambang) }}
                                    </span>
                                </div>
                            </div>

                            <div class="pa-competency-item__progress-track" aria-hidden="true">
                                <div
                                    class="pa-competency-item__progress-bar"
                                    :style="{
                                        width: `${Math.min(100, Math.max(0, Number(item.nilai ?? item.Nilai) || 0))}%`,
                                        background: getCompetencyBarGradient(item.nilai ?? item.Nilai, item.nilai_ambang ?? item.Nilai_Ambang)
                                    }"
                                />
                            </div>

                            <!-- Teaser Spoiler Box -->
                            <div class="pa-competency-item__teaser">
                                <p class="pa-competency-item__teaser-text">
                                    {{ dapatkanDeskripsiKompetensi(item) }}
                                </p>
                            </div>

                            <!-- Teaser Footer Action -->
                            <div class="pa-competency-item__footer">
                                <span class="pa-competency-teaser-badge">
                                    <HcisIcon name="person-check" size="2xs" />
                                    <span>{{ dapatkanPerilakuKompetensi(item).length }} Standar Perilaku</span>
                                </span>
                                <span class="pa-competency-teaser-action">
                                    <span>Lihat Detail</span>
                                    <HcisIcon name="arrow-right" size="2xs" />
                                </span>
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <!-- Modal Dialog Detail Kompetensi & Expected Behaviour (Interactive) -->
                <HcisDialog
                    v-model="dialogDetailKompetensi"
                    :title="selectedKompetensi?.nama_kompetensi || selectedKompetensi?.Nama_Competency || selectedKompetensi?.kode_kompetensi || 'Detail Kompetensi'"
                    kicker="PROFIL AGREGAT KOMPETENSI"
                    :description="selectedKompetensi?.nama_type || selectedKompetensi?.Nama_Type || selectedKompetensi?.kode_type || 'Standar Definisi & Expected Behaviour'"
                    size="md"
                    variant="glass"
                    sentiment="purple"
                    pattern="dots"
                >
                    <div v-if="selectedKompetensi" class="pa-comp-dialog-content">
                        <!-- Score & Ambang Card Banner -->
                        <div class="pa-comp-dialog-banner">
                            <div class="pa-comp-dialog-score-row">
                                <div class="pa-comp-dialog-score-col">
                                    <span class="pa-comp-dialog-lbl">CAPAIAN NILAI</span>
                                    <div class="pa-comp-dialog-val-wrap">
                                        <strong class="pa-comp-dialog-score">{{ formatScore(selectedKompetensi.nilai ?? selectedKompetensi.Nilai) }}</strong>
                                        <span class="pa-comp-dialog-scale">/ 100</span>
                                    </div>
                                </div>

                                <div class="pa-comp-dialog-status-col">
                                    <span
                                        v-if="selectedKompetensi.nilai_ambang || selectedKompetensi.Nilai_Ambang"
                                        class="pa-competency-item__kkm-pill"
                                        :class="{
                                            'is-pass': Number(selectedKompetensi.nilai ?? selectedKompetensi.Nilai) >= Number(selectedKompetensi.nilai_ambang ?? selectedKompetensi.Nilai_Ambang),
                                            'is-under': Number(selectedKompetensi.nilai ?? selectedKompetensi.Nilai) < Number(selectedKompetensi.nilai_ambang ?? selectedKompetensi.Nilai_Ambang)
                                        }"
                                    >
                                        Ambang Standar: {{ formatScore(selectedKompetensi.nilai_ambang ?? selectedKompetensi.Nilai_Ambang) }}
                                    </span>
                                    <!-- Status hanya bila HC menetapkan ambang; tanpa patokan karangan. -->
                                    <span
                                        v-if="(selectedKompetensi.nilai_ambang ?? selectedKompetensi.Nilai_Ambang) != null"
                                        class="pa-comp-dialog-status-badge"
                                        :class="Number(selectedKompetensi.nilai ?? selectedKompetensi.Nilai) >= Number(selectedKompetensi.nilai_ambang ?? selectedKompetensi.Nilai_Ambang) ? 'is-pass' : 'is-under'"
                                        data-uji="status-ambang"
                                    >
                                        {{ Number(selectedKompetensi.nilai ?? selectedKompetensi.Nilai) >= Number(selectedKompetensi.nilai_ambang ?? selectedKompetensi.Nilai_Ambang) ? 'Memenuhi Standar' : 'Perlu Peningkatan' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="pa-competency-item__progress-track" aria-hidden="true">
                                <div
                                    class="pa-competency-item__progress-bar"
                                    :style="{
                                        width: `${Math.min(100, Math.max(0, Number(selectedKompetensi.nilai ?? selectedKompetensi.Nilai) || 0))}%`,
                                        background: getCompetencyBarGradient(selectedKompetensi.nilai ?? selectedKompetensi.Nilai, selectedKompetensi.nilai_ambang ?? selectedKompetensi.Nilai_Ambang)
                                    }"
                                />
                            </div>
                        </div>

                        <!-- 1. Definisi Lengkap Kompetensi -->
                        <div class="pa-comp-dialog-section">
                            <div class="pa-competency-box-header">
                                <span class="pa-competency-box-header__left">
                                    <HcisIcon name="info-circle" size="2xs" class="pa-competency-box-header__icon text-brand" />
                                    <span class="pa-competency-box-header__label">Definisi Kompetensi</span>
                                </span>
                            </div>
                            <div class="pa-comp-dialog-desc-card">
                                <p class="pa-comp-dialog-desc-text">
                                    {{ dapatkanDeskripsiKompetensi(selectedKompetensi) }}
                                </p>
                            </div>
                        </div>

                        <!-- 2. Expected Behaviour (Standar Perilaku yang Diharapkan) -->
                        <div class="pa-comp-dialog-section">
                            <div class="pa-competency-box-header is-teal">
                                <span class="pa-competency-box-header__left">
                                    <HcisIcon name="person-check" size="2xs" class="pa-competency-box-header__icon text-teal" />
                                    <span class="pa-competency-box-header__label text-teal">Expected Behaviour</span>
                                <span v-if="labelTingkatDituntut(selectedKompetensi)" class="pa-tingkat-dituntut" data-uji="tingkat-dituntut" title="Tingkat yang dituntut untuk level jabatan ini (Matriks Kompetensi)">{{ labelTingkatDituntut(selectedKompetensi) }}</span>
                                </span>
                                <span class="pa-competency-box-header__badge">
                                    {{ dapatkanPerilakuKompetensi(selectedKompetensi).length }} Butir Standar
                                </span>
                            </div>

                            <div class="pa-comp-dialog-behaviour-card">
                                <PaDaftarPerilaku
                                    :teks="dapatkanTeksPerilaku(selectedKompetensi)"
                                    judul=""
                                    class="pa-comp-dialog-perilaku-list"
                                />
                            </div>
                        </div>
                    </div>

                    <template #footer>
                        <HcisButton
                            label="Tutup"
                            variant="secondary"
                            size="sm"
                            @click="dialogDetailKompetensi = false"
                        />
                    </template>
                </HcisDialog>

                <!--
                 | Keterlihatan per kategori penilai 360° dan kartu kebijakan keterlihatan SENGAJA tidak
                 | ditampilkan ke leader (keputusan user 14 Okt 2026). Server masih mengirim `kategori`
                 | dan `batas`; jangan dipasang kembali tanpa keputusan baru.
                 -->
            </template>
        </div>

        <!-- HAV 9-BOX COMPOSITION DIALOG MODAL -->
        <HcisDialog
            v-if="memberKuadran"
            v-model="showHavModal"
            title="Matriks 9-Box HAV Map (Human Asset Value)"
            description="Visualisasi pemetaan talenta 3×3 resmi berdasarkan perpaduan Sumbu Kinerja (KPI) dan Sumbu Potensi (Kompetensi 360°)"
            icon="grid-3x3-gap-fill"
            sentiment="purple"
            variant="glass"
            pattern="dots"
            size="xl"
            sticky-footer
        >
            <div class="pa-hav-modal-content">
                <!-- Top Section: Placement Result Banner -->
                <div class="pa-hav-coordinates-card">
                    <div class="pa-hav-coord-item pa-hav-coord-item--result">
                        <div class="pa-hav-coord-item__result-badge">
                            <HcisIcon name="geo-alt-fill" size="xs" />
                            <span>Posisi Kuadran Anggota Tim</span>
                        </div>
                        <strong class="pa-hav-coord-item__result-name">
                            Box {{ memberKuadran.Nomor_Box }}: {{ memberKuadran.Nama_Kuadran || memberKuadran.Kode_Kuadran }}
                        </strong>
                        <small class="pa-hav-coord-item__result-sub">
                            {{ getQuadrantPosition(memberKuadran.Nomor_Box) }}
                        </small>
                    </div>
                </div>

                <!-- 3x3 HAV Grid Framework -->
                <div class="pa-hav-framework-wrap">
                    <!-- Column Axis Labels (X-Axis: Kinerja / KPI) -->
                    <div class="pa-hav-axis-cols">
                        <div class="pa-axis-col-corner">
                            <span class="pa-axis-corner-y">Kompetensi (Y) &darr;</span>
                            <span class="pa-axis-corner-x">Kinerja (X) &rarr;</span>
                        </div>
                        <div class="pa-axis-col-head">
                            <span class="pa-axis-level-label">Kinerja Rendah</span>
                            <small>Skor KPI &lt; 75</small>
                        </div>
                        <div class="pa-axis-col-head">
                            <span class="pa-axis-level-label">Kinerja Sedang</span>
                            <small>Skor KPI 75 – 90</small>
                        </div>
                        <div class="pa-axis-col-head">
                            <span class="pa-axis-level-label">Kinerja Tinggi</span>
                            <small>Skor KPI &ge; 90</small>
                        </div>
                    </div>

                    <!-- 3x3 Grid with Y-Axis Indicators -->
                    <div class="pa-hav-grid-with-y">
                        <!-- Y-Axis Row Labels -->
                        <div class="pa-hav-axis-rows">
                            <div class="pa-axis-row-head">
                                <span class="pa-axis-row-badge is-high">TINGGI</span>
                                <strong>Komp. Tinggi</strong>
                                <small>&ge; 85</small>
                            </div>
                            <div class="pa-axis-row-head">
                                <span class="pa-axis-row-badge is-med">SEDANG</span>
                                <strong>Komp. Sedang</strong>
                                <small>70 – 84</small>
                            </div>
                            <div class="pa-axis-row-head">
                                <span class="pa-axis-row-badge is-low">RENDAH</span>
                                <strong>Komp. Rendah</strong>
                                <small>&lt; 70</small>
                            </div>
                        </div>

                        <!-- 9 Quadrant Cards Grid (7, 3, 1, 8, 4, 2, 9, 6, 5) -->
                        <div class="pa-hav-matrix-grid" role="grid" aria-label="Sembilan kotak HAV Map">
                            <div
                                v-for="item in OFFICIAL_HAV_QUADRANTS"
                                :key="item.box"
                                class="pa-hav-quadrant-card"
                                :class="{
                                    'is-user-box': isMemberBox(item.box),
                                    [`pa-hav-quadrant-card--${item.sentiment}`]: true,
                                }"
                                :style="{ '--pa-hav-color': item.color }"
                                role="gridcell"
                            >
                                <div class="pa-hav-quadrant-card__header">
                                    <div class="pa-hav-quadrant-badges">
                                        <HcisBadge
                                            :label="`BOX ${item.box}`"
                                            :variant="item.sentiment"
                                            appearance="solid"
                                            size="2xs"
                                        />
                                        <span class="pa-hav-quadrant-axes">
                                            {{ item.komp }} Komp · {{ item.perf }} Perf
                                        </span>
                                    </div>

                                    <span v-if="isMemberBox(item.box)" class="pa-hav-user-current-pill">
                                        <span class="pa-hav-cell__pulse-dot" />
                                        Posisi Anggota
                                    </span>
                                </div>

                                <div class="pa-hav-quadrant-card__body">
                                    <div class="pa-hav-quadrant-title-row">
                                        <HcisIcon :name="item.icon" size="xs" class="pa-hav-box-icon" />
                                        <strong class="pa-hav-quadrant-name">
                                            {{ isMemberBox(item.box) && memberKuadran?.Nama_Kuadran ? memberKuadran.Nama_Kuadran : item.nama }}
                                        </strong>
                                    </div>

                                    <p class="pa-hav-quadrant-desc">
                                        {{ isMemberBox(item.box) && memberKuadran?.Keterangan ? memberKuadran.Keterangan : item.desc }}
                                    </p>
                                </div>

                                <div class="pa-hav-quadrant-card__footer">
                                    <div class="pa-hav-guideline-badge">
                                        <HcisIcon name="lightbulb-fill" size="2xs" />
                                        <span>Rekomendasi Pembinaan:</span>
                                    </div>
                                    <p class="pa-hav-guideline-text">{{ item.guideline }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Position Explanation & Action Guidance -->
                <div v-if="currentMemberBoxData" class="pa-hav-position-insight-card">
                    <div class="pa-hav-insight-header">
                        <div class="pa-hav-insight-icon-box">
                            <HcisIcon name="person-workspace" size="sm" />
                        </div>
                        <div class="pa-hav-insight-title-group">
                            <h4 class="pa-hav-insight-title">
                                Arahan Pembinaan untuk Box {{ memberKuadran.Nomor_Box }} ({{ memberKuadran.Nama_Kuadran || currentMemberBoxData.nama }})
                            </h4>
                            <p class="pa-hav-insight-subtitle">
                                Panduan bagi Leader untuk memfasilitasi peningkatan kinerja dan pengembangan talenta anggota
                            </p>
                        </div>
                    </div>

                    <div class="pa-hav-insight-grid">
                        <div class="pa-hav-insight-box">
                            <div class="pa-hav-insight-box__head">
                                <HcisIcon name="graph-up" size="xs" class="text-success" />
                                <strong>Kategori Kinerja</strong>
                            </div>
                            <p>
                                Tingkat capaian kinerja anggota berada pada zona <strong>{{ currentMemberBoxData.perf }}</strong>.
                            </p>
                        </div>

                        <div class="pa-hav-insight-box">
                            <div class="pa-hav-insight-box__head">
                                <HcisIcon name="stars" size="xs" class="text-purple" />
                                <strong>Kategori Potensi Kompetensi</strong>
                            </div>
                            <p>
                                Tingkat penguasaan kompetensi perilaku berada pada zona <strong>{{ currentMemberBoxData.komp }}</strong>.
                            </p>
                        </div>

                        <div class="pa-hav-insight-box pa-hav-insight-box--action">
                            <div class="pa-hav-insight-box__head">
                                <HcisIcon name="rocket-takeoff-fill" size="xs" class="text-teal" />
                                <strong>Rekomendasi Intervensi Leader</strong>
                            </div>
                            <p>
                                {{ currentMemberBoxData.guideline }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <HcisButton
                    variant="primary"
                    label="Mengerti &amp; Tutup"
                    size="sm"
                    leading-icon="check2"
                    @click="showHavModal = false"
                />
            </template>
        </HcisDialog>

        <!-- 11. Modal Dialog: Searchable Team Member Switcher (Scalable for 2, 10, 50+ members) -->
        <HcisDialog
            :model-value="dialogPilihAnggota"
            title="Pilih Anggota Tim"
            kicker="NAVIGASI EVALUASI"
            :description="`Pilih dari ${activeTeamMembers.length} anggota tim aktif untuk menampilkan detail evaluasi kinerja &amp; 360°.`"
            icon="people-fill"
            sentiment="teal"
            variant="glass"
            size="md"
            sticky-footer
            @update:model-value="dialogPilihAnggota = $event"
        >
            <div class="pa-member-picker-modal">
                <!-- Search & Filter Bar -->
                <div class="pa-picker-search-bar">
                    <div class="pa-picker-search-input-wrap">
                        <HcisIcon name="search" size="xs" class="pa-picker-search-icon" />
                        <input
                            v-model="searchQueryModal"
                            type="search"
                            placeholder="Cari nama, kode, jabatan, atau grade…"
                            class="pa-picker-search-input"
                            aria-label="Cari anggota tim"
                        />
                        <button
                            v-if="searchQueryModal"
                            type="button"
                            class="pa-picker-search-clear"
                            aria-label="Hapus kata kunci pencarian"
                            @click="searchQueryModal = ''"
                        >
                            <HcisIcon name="x-circle-fill" size="xs" />
                        </button>
                    </div>

                    <!-- Filter Status Segment Tabs -->
                    <div class="pa-picker-filter-tabs" role="tablist" aria-label="Filter status publikasi">
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="filterStatusModal === 'ALL'"
                            class="pa-picker-tab-btn"
                            :class="{ 'is-active': filterStatusModal === 'ALL' }"
                            @click="filterStatusModal = 'ALL'"
                        >
                            <span>Semua</span>
                            <span class="pa-picker-tab-count">{{ activeTeamMembers.length }}</span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="filterStatusModal === 'TERBIT'"
                            class="pa-picker-tab-btn"
                            :class="{ 'is-active': filterStatusModal === 'TERBIT' }"
                            @click="filterStatusModal = 'TERBIT'"
                        >
                            <span>Sudah Terbit</span>
                            <span class="pa-picker-tab-count is-terbit">{{ countTerbit }}</span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="filterStatusModal === 'BELUM_TERBIT'"
                            class="pa-picker-tab-btn"
                            :class="{ 'is-active': filterStatusModal === 'BELUM_TERBIT' }"
                            @click="filterStatusModal = 'BELUM_TERBIT'"
                        >
                            <span>Belum Terbit</span>
                            <span class="pa-picker-tab-count is-belum">{{ countBelumTerbit }}</span>
                        </button>
                    </div>
                </div>

                <!-- Member Items List -->
                <div v-if="membersForModal.length" class="pa-picker-list" role="listbox" aria-label="Daftar anggota tim">
                    <div
                        v-for="m in membersForModal"
                        :key="m.participant_id"
                        role="option"
                        :aria-selected="isMemberActive(m)"
                        tabindex="0"
                        class="pa-picker-item"
                        :class="{ 'is-selected': isMemberActive(m) }"
                        @click="pilihMemberDariModal(m)"
                        @keydown.enter.prevent="pilihMemberDariModal(m)"
                        @keydown.space.prevent="pilihMemberDariModal(m)"
                    >
                        <div class="pa-picker-item__left">
                            <span class="pa-picker-item__avatar" :class="{ 'is-active': isMemberActive(m) }" aria-hidden="true">
                                {{ inisial(m.nama) }}
                            </span>
                            <div class="pa-picker-item__info">
                                <div class="pa-picker-item__name-row">
                                    <strong class="pa-picker-item__name">{{ m.nama }}</strong>
                                    <span v-if="isMemberActive(m)" class="pa-picker-active-badge">
                                        <HcisIcon name="check-circle-fill" size="2xs" />
                                        <span>Aktif</span>
                                    </span>
                                </div>
                                <div class="pa-picker-item__meta">
                                    <span>Kode: {{ m.kode_karyawan }}</span>
                                    <span>·</span>
                                    <span>{{ m.nama_jabatan || 'Staff' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="pa-picker-item__right">
                            <HcisBadge
                                v-if="(m.terbit || m.dirilis_ke_leader) && m.kode_grade"
                                :label="`Grade ${displayGrade(m.kode_grade)}`"
                                :variant="getGradeSentiment(m.kode_grade)"
                                appearance="subtle"
                                size="xs"
                            />
                            <HcisBadge
                                v-else-if="!m.terbit && !m.dirilis_ke_leader"
                                label="Belum terbit"
                                variant="neutral"
                                appearance="subtle"
                                size="xs"
                            />
                            <HcisIcon name="chevron-right" size="xs" class="pa-picker-item__arrow" />
                        </div>
                    </div>
                </div>

                <!-- Empty Search State -->
                <div v-else class="pa-picker-empty-state">
                    <HcisIcon name="search" size="md" color="muted" />
                    <p class="pa-picker-empty-text">Tidak ada anggota yang sesuai dengan pencarian <strong>"{{ searchQueryModal }}"</strong></p>
                    <HcisButton
                        v-if="searchQueryModal || filterStatusModal !== 'ALL'"
                        label="Reset Pencarian &amp; Filter"
                        variant="glass"
                        size="xs"
                        leading-icon="arrow-clockwise"
                        @click="searchQueryModal = ''; filterStatusModal = 'ALL'"
                    />
                </div>
            </div>

            <template #footer>
                <div class="pa-picker-footer-strip">
                    <span class="pa-picker-footer-count">
                        Menampilkan {{ membersForModal.length }} dari {{ activeTeamMembers.length }} anggota
                    </span>
                    <HcisButton
                        variant="secondary"
                        label="Tutup"
                        size="sm"
                        @click="dialogPilihAnggota = false"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PaDaftarPerilaku from '../../components/PaDaftarPerilaku.vue';
import { dapatkanDeskripsiKompetensi, dapatkanPerilakuKompetensi, dapatkanTeksPerilaku, labelTingkatDituntut } from '../../utils/kompetensiStandar.js';
import { htmlAman } from '../../utils/htmlAman.js';

import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisDialog,
    HcisEmptyState,
    HcisIcon,
    HcisResultState,
    HcisStatusBadge,
} from '@/components/ui';

defineOptions({ name: 'PaLeaderMemberDetailView' });

const props = defineProps({
    detail: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
    teamMembers: { type: Array, default: () => [] },
    showBackAction: { type: Boolean, default: true },
});

const emit = defineEmits(['back', 'select-member', 'retry']);

const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth <= 768 : false);
let mediaQuery = null;

function updateIsMobile(e) {
    isMobile.value = e ? e.matches : (typeof window !== 'undefined' && window.innerWidth <= 768);
}

onMounted(() => {
    if (typeof window !== 'undefined' && window.matchMedia) {
        mediaQuery = window.matchMedia('(max-width: 768px)');
        isMobile.value = mediaQuery.matches;
        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', updateIsMobile);
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(updateIsMobile);
        }
    }
});

onBeforeUnmount(() => {
    if (mediaQuery) {
        if (typeof mediaQuery.removeEventListener === 'function') {
            mediaQuery.removeEventListener('change', updateIsMobile);
        } else if (typeof mediaQuery.removeListener === 'function') {
            mediaQuery.removeListener(updateIsMobile);
        }
    }
});

const dialogPilihAnggota = ref(false);
const searchQueryModal = ref('');
const filterStatusModal = ref('ALL');

function bukaModalPilihAnggota() {
    searchQueryModal.value = '';
    filterStatusModal.value = 'ALL';
    dialogPilihAnggota.value = true;
}

function pilihMemberDariModal(m) {
    dialogPilihAnggota.value = false;
    emit('select-member', m.participant_id);
}

const showHavModal = ref(false);
const dialogDetailKompetensi = ref(false);
const selectedKompetensi = ref(null);

function bukaDetailKompetensi(item) {
    selectedKompetensi.value = item;
    dialogDetailKompetensi.value = true;
}

const OFFICIAL_HAV_QUADRANTS = [
    {
        box: 7,
        kode: 'RAW_DIAMOND',
        nama: 'Raw Diamond',
        perf: 'Rendah',
        komp: 'Tinggi',
        sentiment: 'brand',
        color: '#6366f1',
        icon: 'gem',
        desc: 'Potensi kompetensi tinggi namun hasil kinerja belum optimal tercapai.',
        guideline: 'Evaluasi kesesuaian tugas (role-fit), atasi hambatan kerja, dan berikan mentoring.',
    },
    {
        box: 3,
        kode: 'FUTURE_STAR_B',
        nama: 'Future Star',
        perf: 'Sedang',
        komp: 'Tinggi',
        sentiment: 'brand',
        color: '#4f46e5',
        icon: 'award-fill',
        desc: 'Kompetensi sangat tinggi dengan capaian kinerja memenuhi target.',
        guideline: 'Tingkatkan akuntabilitas target output dan bimbingan optimasi eksekusi kerja.',
    },
    {
        box: 1,
        kode: 'STRIVING_STAR',
        nama: 'Striving Star',
        perf: 'Tinggi',
        komp: 'Tinggi',
        sentiment: 'success',
        color: '#10b981',
        icon: 'star-fill',
        desc: 'Kinerja dan kompetensi sama-sama istimewa di atas standar.',
        guideline: 'Kandidat promosi cepat, suksesi kepemimpinan, dan prioritas retensi talenta.',
    },
    {
        box: 8,
        kode: 'PROBLEM_EMPLOYEE',
        nama: 'Problem Employee',
        perf: 'Rendah',
        komp: 'Sedang',
        sentiment: 'warning',
        color: '#ea580c',
        icon: 'exclamation-triangle-fill',
        desc: 'Kinerja belum memenuhi target walau potensi kompetensi memadai.',
        guideline: 'Performance Improvement Plan (PIP) terstruktur dan konseling berkala.',
    },
    {
        box: 4,
        kode: 'POTENTIAL_CANDIDATE',
        nama: 'Potential Candidate',
        perf: 'Sedang',
        komp: 'Sedang',
        sentiment: 'teal',
        color: '#0d9488',
        icon: 'person-check-fill',
        desc: 'Performa dan kompetensi stabil memenuhi standar ekspektasi jabatan.',
        guideline: 'Pelatihan spesialisasi fungsi, perluasan tanggung jawab, dan penguatan skill.',
    },
    {
        box: 2,
        kode: 'FUTURE_STAR_A',
        nama: 'Future Star',
        perf: 'Tinggi',
        komp: 'Sedang',
        sentiment: 'purple',
        color: '#8b5cf6',
        icon: 'lightning-charge-fill',
        desc: 'Kinerja melampaui target dengan potensi kompetensi berkembang solid.',
        guideline: 'Pengembangan kepemimpinan terarah, coaching manajerial, dan penugasan strategis.',
    },
    {
        box: 9,
        kode: 'DEADWOOD',
        nama: 'Deadwood',
        perf: 'Rendah',
        komp: 'Rendah',
        sentiment: 'danger',
        color: '#dc2626',
        icon: 'x-octagon-fill',
        desc: 'Pencapaian kinerja dan kompetensi berada di bawah standar minimum.',
        guideline: 'Evaluasi kesesuaian posisi, program pembinaan intensif, atau peninjauan peran.',
    },
    {
        box: 6,
        kode: 'CONTRIBUTOR',
        nama: 'Contributor',
        perf: 'Sedang',
        komp: 'Rendah',
        sentiment: 'info',
        color: '#0284c7',
        icon: 'hand-thumbs-up-fill',
        desc: 'Performa operasional memenuhi target dengan kompetensi perlu pengembangan.',
        guideline: 'Pendampingan on-the-job, review SOP berkala, dan penajaman soft skills.',
    },
    {
        box: 5,
        kode: 'TOP_PERFORMER',
        nama: 'Top Performer',
        perf: 'Tinggi',
        komp: 'Rendah',
        sentiment: 'warning',
        color: '#f59e0b',
        icon: 'trophy-fill',
        desc: 'Pencapaian output target sangat tinggi, namun kompetensi perlu penguatan.',
        guideline: 'Pertahankan delivery hasil sembari intervensi pelatihan kompetensi perilaku.',
    },
];

const memberKuadran = computed(() => {
    if (!props.detail?.kode_kuadran) return null;
    const raw = String(props.detail.kode_kuadran).trim().toUpperCase();
    const match = OFFICIAL_HAV_QUADRANTS.find(
        (q) => q.kode === raw || q.nama.toUpperCase() === raw || String(q.box) === raw,
    );

    if (match) {
        return {
            Nomor_Box: match.box,
            Kode_Kuadran: match.kode,
            Nama_Kuadran: match.nama,
            Warna: match.color,
            Keterangan: match.desc,
            Guideline: match.guideline,
        };
    }

    return {
        Nomor_Box: null,
        Kode_Kuadran: props.detail.kode_kuadran,
        Nama_Kuadran: props.detail.kode_kuadran.replace(/_/g, ' '),
    };
});

const currentMemberBoxData = computed(() => {
    const boxNum = Number(memberKuadran.value?.Nomor_Box);
    if (!boxNum) return null;
    return OFFICIAL_HAV_QUADRANTS.find((item) => item.box === boxNum) || null;
});

const activeTeamMembers = computed(() => {
    return (props.teamMembers || []).filter((m) => m.status_peserta !== 'DIKECUALIKAN');
});

const countTerbit = computed(() => {
    return activeTeamMembers.value.filter((m) => m.terbit || m.dirilis_ke_leader).length;
});

const countBelumTerbit = computed(() => {
    return activeTeamMembers.value.filter((m) => !m.terbit && !m.dirilis_ke_leader).length;
});

const membersForModal = computed(() => {
    let list = activeTeamMembers.value;
    if (filterStatusModal.value === 'TERBIT') {
        list = list.filter((m) => m.terbit || m.dirilis_ke_leader);
    } else if (filterStatusModal.value === 'BELUM_TERBIT') {
        list = list.filter((m) => !m.terbit && !m.dirilis_ke_leader);
    }
    const q = searchQueryModal.value.trim().toLowerCase();
    if (!q) return list;
    return list.filter((m) => {
        const nama = String(m.nama || '').toLowerCase();
        const kode = String(m.kode_karyawan || '').toLowerCase();
        const jabatan = String(m.nama_jabatan || '').toLowerCase();
        const grade = String(m.kode_grade || '').toLowerCase();
        return nama.includes(q) || kode.includes(q) || jabatan.includes(q) || grade.includes(q);
    });
});

const currentMemberIndex = computed(() => {
    if (!props.detail || !activeTeamMembers.value.length) return -1;
    return activeTeamMembers.value.findIndex(
        (m) => Number(m.participant_id) === Number(props.detail.participant_id),
    );
});

const prevMember = computed(() => {
    if (currentMemberIndex.value > 0) {
        return activeTeamMembers.value[currentMemberIndex.value - 1];
    }
    return null;
});

const nextMember = computed(() => {
    if (
        currentMemberIndex.value >= 0 &&
        currentMemberIndex.value < activeTeamMembers.value.length - 1
    ) {
        return activeTeamMembers.value[currentMemberIndex.value + 1];
    }
    return null;
});

function isMemberBox(boxNumber) {
    return Number(memberKuadran.value?.Nomor_Box) === Number(boxNumber);
}

function isMemberActive(member) {
    return Number(props.detail?.participant_id) === Number(member.participant_id);
}

const radarKompetensi = computed(() => {
    const list = props.detail?.kompetensi;
    if (!Array.isArray(list) || !list.length) return [];
    const tersaring = list.filter((c) => {
        const flag = c.flag_radar ?? c.Flag_Radar ?? 'Y';
        return flag !== 'T' && flag !== 't' && flag !== false && flag !== 0;
    });
    return tersaring.length > 0 ? tersaring : list;
});

const radarCategories = computed(() => {
    return radarKompetensi.value.map(
        (c) => c.nama_kompetensi || c.Nama_Competency || c.kode_kompetensi || 'Kompetensi',
    );
});

const radarNilai = computed(() => {
    return radarKompetensi.value.map((c) => {
        const n = Number(c.nilai ?? c.Nilai);
        return Number.isFinite(n) ? n : 0;
    });
});

const radarSeries = computed(() => {
    if (!radarKompetensi.value.length) return [];

    const series = [
        {
            name: 'Skor Kompetensi',
            value: radarNilai.value,
            data: radarNilai.value,
        },
    ];

    const ambang = radarKompetensi.value.map((c) => {
        const a = c.nilai_ambang ?? c.Nilai_Ambang;
        return a === null || a === undefined ? null : Number(a);
    });

    if (ambang.some((v) => v !== null && Number.isFinite(v))) {
        series.push({
            name: 'Ambang (KKM)',
            value: ambang,
            data: ambang,
        });
    }

    return series;
});

const radarResultHeight = computed(() => {
    return isMobile.value ? 340 : 380;
});

function isStrengthDimension(item) {
    const code = String(item?.Kode_Type || item?.kode_type || item?.kode || item?.Kode_Competency || item?.kode_kompetensi || '').toUpperCase();
    const name = String(item?.Nama_Type || item?.nama_type || item?.nama || item?.Nama_Competency || item?.nama_kompetensi || '').toLowerCase();
    return code === 'QL01' || code === 'STRENGTH' || /strength|kekuatan|keunggulan/i.test(name) || /strength|kekuatan|keunggulan/i.test(code);
}

function isDevAreaDimension(item) {
    const code = String(item?.Kode_Type || item?.kode_type || item?.kode || item?.Kode_Competency || item?.kode_kompetensi || '').toUpperCase();
    const name = String(item?.Nama_Type || item?.nama_type || item?.nama || item?.Nama_Competency || item?.nama_kompetensi || '').toLowerCase();
    return code === 'QL02' || code === 'DEVELOPMENT_AREA' || /development|pengembangan|improvement/i.test(name) || /development|pengembangan|improvement/i.test(code);
}

const rawCatatanList = computed(() => {
    return Array.isArray(props.detail?.catatan)
        ? props.detail.catatan
        : (Array.isArray(props.detail?.jawaban_naratif) ? props.detail.jawaban_naratif : []);
});

const strengthData = computed(() => {
    const matching = rawCatatanList.value.filter(isStrengthDimension);
    const firstName = matching.find((n) => n.Nama_Type || n.nama_type || n.Nama_Competency || n.nama_kompetensi)?.Nama_Type
        || matching.find((n) => n.Nama_Type || n.nama_type || n.Nama_Competency || n.nama_kompetensi)?.nama_type
        || matching.find((n) => n.Nama_Type || n.nama_type || n.Nama_Competency || n.nama_kompetensi)?.Nama_Competency
        || matching.find((n) => n.Nama_Type || n.nama_type || n.Nama_Competency || n.nama_kompetensi)?.nama_kompetensi
        || 'Kekuatan Utama';

    const conclusionItem = matching.find((n) => {
        if (n.dari_penilai === true) return false;
        const isi = (n.Isi || n.isi || n.Jawaban_Teks || n.jawaban_teks || n.Jawaban || n.jawaban || n.Kesimpulan || n.kesimpulan || n.Catatan || n.catatan || '').trim();
        return Boolean(isi);
    });

    const kesimpulan = conclusionItem
        ? (conclusionItem.Isi || conclusionItem.isi || conclusionItem.Jawaban_Teks || conclusionItem.jawaban_teks || conclusionItem.Jawaban || conclusionItem.jawaban || conclusionItem.Kesimpulan || conclusionItem.kesimpulan || conclusionItem.Catatan || conclusionItem.catatan || '').trim()
        : '';

    return {
        kode: matching[0]?.Kode_Type || matching[0]?.kode_type || 'QL01',
        nama: firstName,
        kesimpulan,
    };
});

const devAreaData = computed(() => {
    const matching = rawCatatanList.value.filter(isDevAreaDimension);
    const firstName = matching.find((n) => n.Nama_Type || n.nama_type || n.Nama_Competency || n.nama_kompetensi)?.Nama_Type
        || matching.find((n) => n.Nama_Type || n.nama_type || n.Nama_Competency || n.nama_kompetensi)?.nama_type
        || matching.find((n) => n.Nama_Type || n.nama_type || n.Nama_Competency || n.nama_kompetensi)?.Nama_Competency
        || matching.find((n) => n.Nama_Type || n.nama_type || n.Nama_Competency || n.nama_kompetensi)?.nama_kompetensi
        || 'Area Pengembangan';

    const conclusionItem = matching.find((n) => {
        if (n.dari_penilai === true) return false;
        const isi = (n.Isi || n.isi || n.Jawaban_Teks || n.jawaban_teks || n.Jawaban || n.jawaban || n.Kesimpulan || n.kesimpulan || n.Catatan || n.catatan || '').trim();
        return Boolean(isi);
    });

    const kesimpulan = conclusionItem
        ? (conclusionItem.Isi || conclusionItem.isi || conclusionItem.Jawaban_Teks || conclusionItem.jawaban_teks || conclusionItem.Jawaban || conclusionItem.jawaban || conclusionItem.Kesimpulan || conclusionItem.kesimpulan || conclusionItem.Catatan || conclusionItem.catatan || '').trim()
        : '';

    return {
        kode: matching[0]?.Kode_Type || matching[0]?.kode_type || 'QL02',
        nama: firstName,
        kesimpulan,
    };
});

const otherCatatanList = computed(() => {
    const grup = new Map();
    rawCatatanList.value.forEach((note) => {
        if (note.dari_penilai === true) return;
        if (isStrengthDimension(note) || isDevAreaDimension(note)) return;
        const kode = note.Kode_Type || note.kode_type || note.Kode_Competency || note.kode_kompetensi || 'LAINNYA';

        if (!grup.has(kode)) {
            grup.set(kode, {
                kode,
                nama: note.Nama_Type || note.nama_type || note.Nama_Competency || note.nama_kompetensi || kode,
                kesimpulan: '',
            });
        }

        const isi = (note.Isi || note.isi || note.Jawaban_Teks || note.jawaban_teks || note.Jawaban || note.jawaban || note.Kesimpulan || note.kesimpulan || note.Catatan || note.catatan || '').trim();
        if (!isi) return;

        if (!grup.get(kode).kesimpulan) {
            grup.get(kode).kesimpulan = isi;
        }
    });

    return Array.from(grup.values()).filter((g) => Boolean(g.kesimpulan));
});

const gradeThemeClass = computed(() => {
    const g = String(props.detail?.kode_grade || '').toUpperCase();
    if (g.startsWith('A')) return 'is-grade-a';
    if (g.startsWith('B')) return 'is-grade-b';
    if (g.startsWith('C')) return 'is-grade-c';
    if (g.startsWith('D') || g.startsWith('E')) return 'is-grade-d';
    return 'is-grade-neutral';
});

function inisial(nama) {
    if (!nama) return 'PA';
    const bagian = String(nama).trim().split(/\s+/).filter(Boolean);
    if (!bagian.length) return 'PA';
    if (bagian.length === 1) return bagian[0].slice(0, 2).toUpperCase();
    return (bagian[0][0] + bagian[1][0]).toUpperCase();
}

function angka(val) {
    const n = Number(val);
    return Number.isFinite(n) ? n : 0;
}

function formatScore(value) {
    if (value === null || value === undefined || value === '') return '—';
    const parsed = Number(value);
    return Number.isFinite(parsed)
        ? parsed.toLocaleString('id-ID', { maximumFractionDigits: 2 })
        : String(value);
}

function formatTanggalWaktu(value) {
    if (!value) return '—';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

function displayGrade(g) {
    if (!g || g === '—') return '—';
    return String(g)
        .replace(/_PLUS/gi, '+')
        .replace(/_MINUS/gi, '-')
        .replace(/\b([A-E])\s*PLUS\b/gi, '$1+')
        .replace(/\b([A-E])\s*MINUS\b/gi, '$1-')
        .trim();
}

/* Nama grade dari server: pita kurva beku (atau pita siklus) tempat grade itu lahir. */
function gradePredicate(g, nama) {
    if (!g) return 'Belum Ditetapkan';
    return nama || `Grade ${displayGrade(g)}`;
}

function getGradeSentiment(g) {
    const s = String(g || '').toUpperCase();
    if (s.startsWith('A')) return 'success';
    if (s.startsWith('B')) return 'brand';
    if (s.startsWith('C')) return 'warning';
    if (s.startsWith('D') || s.startsWith('E')) return 'danger';
    return 'neutral';
}

function getScoreGradient(score) {
    const n = Number(score) || 0;
    if (n >= 90) return 'linear-gradient(90deg, #10b981 0%, #059669 100%)';
    if (n >= 75) return 'linear-gradient(90deg, #6366f1 0%, #4f46e5 100%)';
    if (n >= 60) return 'linear-gradient(90deg, #f59e0b 0%, #d97706 100%)';
    return 'linear-gradient(90deg, #f43f5e 0%, #e11d48 100%)';
}

function getCompetencyBarGradient(nilai, ambang) {
    const val = Number(nilai) || 0;
    // Tanpa ambang: satu warna netral, bukan hijau/kuning yang menyiratkan lulus/tidak.
    if (ambang === null || ambang === undefined || ambang === '') return 'linear-gradient(90deg, #6366f1 0%, #4f46e5 100%)';
    const kkm = Number(ambang);
    if (val >= kkm) return 'linear-gradient(90deg, #10b981 0%, #059669 100%)';
    if (val >= 60) return 'linear-gradient(90deg, #6366f1 0%, #4f46e5 100%)';
    return 'linear-gradient(90deg, #f59e0b 0%, #d97706 100%)';
}

function getQuadrantPosition(num) {
    const n = Number(num);
    if (n === 1 || n === 2 || n === 3) return 'Kategori High Potential / Star';
    if (n === 4 || n === 5 || n === 6) return 'Kategori Solid Performer / Contributor';
    if (n === 7 || n === 8 || n === 9) return 'Kategori Perlu Pembinaan / Eksplorasi';
    return 'Matriks Kinerja & Potensi';
}

function getCatatanIcon(nama) {
    const s = String(nama || '').toLowerCase();
    if (/strength|kekuatan|keunggulan/i.test(s)) return 'hand-thumbs-up-fill';
    if (/development|pengembangan|improvement/i.test(s)) return 'lightbulb-fill';
    return 'journal-text';
}

function getCatatanSentiment(nama) {
    const s = String(nama || '').toLowerCase();
    if (/strength|kekuatan|keunggulan/i.test(s)) return 'teal';
    if (/development|pengembangan|improvement/i.test(s)) return 'purple';
    return 'brand';
}

function getCatatanDescription(nama) {
    const s = String(nama || '').toLowerCase();
    if (/strength|kekuatan|keunggulan/i.test(s)) {
        return 'Poin keunggulan dan kontribusi positif utama yang terbukti menonjol.';
    }
    if (/development|pengembangan|improvement/i.test(s)) {
        return 'Rekomendasi area pengembangan kompetensi untuk peningkatan kinerja ke depan.';
    }
    return 'Kesimpulan dan catatan evaluasi resmi dari tim penilai.';
}

function statusLabel(status) {
    const map = {
        BELUM_DIHITUNG: 'Belum Dihitung',
        DIHITUNG: 'Dihitung',
        TERTAHAN: 'Tertahan',
        TERBIT: 'Diterbitkan Resmi',
    };
    return map[status] || status || 'Belum Dihitung';
}

function statusVariant(status) {
    const map = {
        BELUM_DIHITUNG: 'neutral',
        DIHITUNG: 'brand',
        TERTAHAN: 'warning',
        TERBIT: 'success',
    };
    return map[status] || 'neutral';
}

function statusPesertaLabel(status) {
    const map = {
        AKTIF: 'Peserta Aktif',
        DIIKUTKAN: 'Diikutkan',
        MUTASI: 'Mutasi Kerja',
        PROMOSI: 'Promosi',
        NONAKTIF: 'Nonaktif',
        DIKECUALIKAN: 'Dikecualikan',
    };
    return map[status] || status;
}
</script>

<style scoped>
.pa-member-detail-view,
.pa-member-detail-view :deep(.hcis-card) {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

/* 1. Top Navigation & Action Strip */
.pa-member-nav-strip {
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    margin-bottom: 0.85rem;
    padding: 0.75rem 1.15rem;
}

.pa-member-nav-strip__desktop {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
}

.pa-member-nav-strip__mobile {
    display: none;
}

.pa-member-nav-strip__left,
.pa-member-nav-strip__right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.pa-member-nav-strip__divider {
    width: 1px;
    height: 20px;
    background: rgba(203, 213, 225, 0.8);
    flex-shrink: 0;
}

.pa-member-nav-strip__title-group {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    min-width: 0;
}

.pa-member-nav-strip__name {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--hcis-color-text-main, #0f172a);
    letter-spacing: -0.01em;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Stepper Pill Navigator */
.pa-member-stepper-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: rgba(241, 245, 249, 0.9);
    padding: 0.2rem 0.35rem;
    border-radius: 999px;
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.03);
}

.pa-member-stepper-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    border: 1px solid rgba(203, 213, 225, 0.8);
    background: #ffffff;
    color: var(--hcis-color-text-main, #1e293b);
    cursor: pointer;
    transition: all 140ms ease;
    padding: 0;
}

.pa-member-stepper-btn:hover:not(:disabled) {
    background: var(--hcis-color-teal, #0d9488);
    color: #ffffff;
    border-color: var(--hcis-color-teal, #0d9488);
    transform: scale(1.05);
}

.pa-member-stepper-btn:active:not(:disabled) {
    transform: scale(0.95);
}

.pa-member-stepper-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    background: transparent;
    border-color: transparent;
}

.pa-member-stepper-content {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0 0.4rem;
    font-size: 0.74rem;
    font-weight: 750;
    color: var(--hcis-color-text-muted, #475569);
    font-variant-numeric: tabular-nums;
    user-select: none;
}

.pa-member-stepper-current {
    color: var(--hcis-color-teal, #0d9488);
    font-weight: 850;
}

.pa-member-stepper-slash {
    color: rgba(148, 163, 184, 0.8);
    font-weight: 500;
}

.pa-member-stepper-total {
    color: var(--hcis-color-text-main, #0f172a);
    font-weight: 750;
}

.pa-member-stepper-unit {
    font-size: 0.68rem;
    color: var(--hcis-color-text-muted, #64748b);
    font-weight: 600;
    margin-left: 0.15rem;
}

/* 2. Team Member Searchable Picker Dialog */
.pa-member-picker-modal {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    max-height: 65vh;
}

.pa-picker-search-bar {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    position: sticky;
    top: 0;
    z-index: 5;
    background: rgba(255, 255, 255, 0.98);
    padding-bottom: 0.25rem;
}

.pa-picker-search-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.pa-picker-search-icon {
    position: absolute;
    left: 0.85rem;
    color: var(--hcis-color-text-muted, #64748b);
    pointer-events: none;
}

.pa-picker-search-input {
    width: 100%;
    padding: 0.6rem 2.2rem 0.6rem 2.3rem;
    font-size: 0.85rem;
    border-radius: var(--hcis-radius-md, 10px);
    border: 1.5px solid rgba(203, 213, 225, 0.9);
    background: #ffffff;
    color: var(--hcis-color-text-main, #0f172a);
    outline: none;
    transition: all 160ms ease;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-picker-search-input:focus {
    border-color: var(--hcis-color-teal, #0d9488);
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
}

.pa-picker-search-clear {
    position: absolute;
    right: 0.75rem;
    background: transparent;
    border: none;
    color: var(--hcis-color-text-muted, #94a3b8);
    cursor: pointer;
    padding: 0.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    transition: color 140ms ease;
}

.pa-picker-search-clear:hover {
    color: var(--hcis-color-text-main, #334155);
}

.pa-picker-filter-tabs {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    scrollbar-width: none;
}

.pa-picker-filter-tabs::-webkit-scrollbar {
    display: none;
}

.pa-picker-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.28rem 0.65rem;
    border-radius: 999px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: rgba(248, 250, 252, 0.95);
    color: var(--hcis-color-text-muted, #64748b);
    font-size: 0.74rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 140ms ease;
    white-space: nowrap;
}

.pa-picker-tab-btn:hover {
    background: #ffffff;
    color: var(--hcis-color-text-main, #0f172a);
    border-color: rgba(203, 213, 225, 0.95);
}

.pa-picker-tab-btn.is-active {
    background: rgba(13, 148, 136, 0.1);
    color: var(--hcis-color-teal, #0d9488);
    border-color: rgba(13, 148, 136, 0.35);
}

.pa-picker-tab-count {
    font-size: 0.68rem;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.8);
    color: var(--hcis-color-text-main, #334155);
}

.pa-picker-tab-btn.is-active .pa-picker-tab-count {
    background: var(--hcis-color-teal, #0d9488);
    color: #ffffff;
}

/* Member List */
.pa-picker-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    overflow-y: auto;
    max-height: 380px;
    padding-right: 0.2rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(203, 213, 225, 0.8) transparent;
}

.pa-picker-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 0.7rem 0.9rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1.5px solid rgba(226, 232, 240, 0.9);
    cursor: pointer;
    transition: all 140ms ease;
    user-select: none;
}

.pa-picker-item:hover {
    background: rgba(248, 250, 252, 0.95);
    border-color: rgba(13, 148, 136, 0.35);
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
}

.pa-picker-item.is-selected {
    background: linear-gradient(135deg, rgba(240, 253, 250, 0.95) 0%, rgba(204, 251, 241, 0.45) 100%);
    border-color: var(--hcis-color-teal, #0d9488);
    box-shadow: 0 3px 12px rgba(13, 148, 136, 0.12), 0 0 0 1px var(--hcis-color-teal, #0d9488);
}

.pa-picker-item__left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1;
}

.pa-picker-item__avatar {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    background: rgba(13, 148, 136, 0.1);
    color: var(--hcis-color-teal, #0d9488);
    font-size: 0.8rem;
    font-weight: 850;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid rgba(13, 148, 136, 0.2);
}

.pa-picker-item__avatar.is-active {
    background: var(--hcis-color-teal, #0d9488);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(13, 148, 136, 0.3);
}

.pa-picker-item__info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
    flex: 1;
}

.pa-picker-item__name-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-picker-item__name {
    font-size: 0.86rem;
    font-weight: 800;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1.25;
}

.pa-picker-active-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.6rem;
    font-weight: 750;
    color: var(--hcis-color-teal, #0d9488);
    background: rgba(13, 148, 136, 0.12);
    padding: 0.06rem 0.35rem;
    border-radius: 999px;
}

.pa-picker-item__meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.25;
}

.pa-picker-item__right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-shrink: 0;
}

.pa-picker-item__arrow {
    color: var(--hcis-color-text-muted, #94a3b8);
    transition: transform 140ms ease;
}

.pa-picker-item:hover .pa-picker-item__arrow {
    color: var(--hcis-color-teal, #0d9488);
    transform: translateX(2px);
}

/* Empty State in Picker */
.pa-picker-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 0.65rem;
    padding: 2.5rem 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(248, 250, 252, 0.8);
    border: 1px dashed rgba(203, 213, 225, 0.8);
}

.pa-picker-empty-text {
    margin: 0;
    font-size: 0.82rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-picker-footer-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 1rem;
}

.pa-picker-footer-count {
    font-size: 0.76rem;
    color: var(--hcis-color-text-muted, #64748b);
    font-weight: 600;
}

.pa-member-nav-mobile-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-member-nav-strip__mobile-member.is-interactive {
    cursor: pointer;
    transition: all 140ms ease;
}

.pa-member-nav-strip__mobile-member.is-interactive:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.pa-member-nav-strip__mobile-member.is-interactive:active {
    transform: scale(0.99);
}

/* 2. Syncing Badge Floating Pill */
.pa-member-syncing-badge {
    position: sticky;
    top: 0.75rem;
    z-index: 30;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    margin: 0 auto 0.75rem auto;
    padding: 0.4rem 0.95rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.18);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 650;
    pointer-events: none;
    align-self: center;
}

.pa-member-spinner-box {
    width: 14px;
    height: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-member-spinner-svg {
    width: 100%;
    height: 100%;
    animation: hcis-spin 0.85s linear infinite;
}

.pa-member-spinner-track {
    stroke: rgba(255, 255, 255, 0.25);
}

.pa-member-spinner-head {
    stroke: #2dd4bf;
    stroke-dasharray: 45;
    stroke-dashoffset: 15;
    stroke-linecap: round;
}

.hcis-loading-pop-enter-active,
.hcis-loading-pop-leave-active {
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.hcis-loading-pop-enter-from,
.hcis-loading-pop-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.95);
}

/* Skeleton Suite Styles */
.pa-member-detail-skeleton {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    animation: pa-fade-in 0.3s ease-out;
}

.pa-skeleton-profile-banner {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.15rem 1.35rem;
    border-radius: var(--hcis-radius-xl, 14px);
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-skeleton-avatar {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    flex-shrink: 0;
}

.pa-skeleton-profile-content {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    flex: 1;
}

.pa-skeleton-line {
    height: 12px;
    border-radius: 4px;
}

.pa-skeleton-line.is-eyebrow {
    width: 90px;
    height: 10px;
}

.pa-skeleton-line.is-name {
    width: 220px;
    height: 20px;
}

.pa-skeleton-meta-chips {
    display: flex;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-top: 0.15rem;
}

.pa-skeleton-chip {
    width: 105px;
    height: 24px;
    border-radius: 6px;
}

.pa-skeleton-hero-card {
    margin-bottom: 0;
}

.pa-skeleton-hero-cockpit {
    display: grid;
    grid-template-columns: 1.15fr 1fr 1.35fr;
    gap: 0.85rem;
    margin-bottom: 0.85rem;
}

.pa-skeleton-pillar {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 1.15rem;
    border-radius: 12px;
    background: rgba(248, 250, 252, 0.7);
    border: 1px solid rgba(226, 232, 240, 0.8);
    min-height: 115px;
    justify-content: center;
}

.pa-skeleton-circle {
    width: 52px;
    height: 52px;
    border-radius: 999px;
}

.pa-skeleton-line.is-score-val {
    width: 80px;
    height: 32px;
}

.pa-skeleton-bar {
    width: 100%;
    height: 6px;
    border-radius: 999px;
}

.pa-skeleton-line.is-comp-item {
    width: 60%;
    height: 10px;
}

.pa-skeleton-line.is-sub {
    width: 70%;
    height: 12px;
}

.pa-skeleton-meta-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.85rem;
    padding-top: 0.85rem;
    border-top: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-skeleton-meta-box {
    height: 36px;
    border-radius: 6px;
}

.pa-skeleton-dual-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.pa-skeleton-radar-box {
    height: 260px;
    border-radius: 12px;
}

.pa-skeleton-qualitative-col {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-skeleton-line.is-quote {
    width: 85%;
    height: 14px;
    margin-bottom: 0.4rem;
}

.pa-skeleton-line.is-quote-sub {
    width: 60%;
    height: 12px;
}

.pa-shimmer {
    background: linear-gradient(
        90deg,
        rgba(226, 232, 240, 0.6) 0%,
        rgba(241, 245, 249, 0.95) 50%,
        rgba(226, 232, 240, 0.6) 100%
    );
    background-size: 200% 100%;
    animation: hcis-shimmer 1.5s infinite ease-in-out;
}

@keyframes hcis-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

.is-loading-dimmed {
    opacity: 0.55;
    pointer-events: none;
    transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

/* 3. Member Profile Banner */
.pa-member-profile-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.15rem;
    padding: 1rem 1.35rem;
    border-radius: var(--hcis-radius-xl, 14px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04);
    margin-bottom: 1rem;
}

.pa-member-profile-banner__main {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
}

.pa-member-profile-banner__avatar {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--hcis-color-teal, #0d9488) 0%, #0f766e 100%);
    color: #ffffff;
    font-size: 1.2rem;
    font-weight: 850;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 6px 16px -2px rgba(13, 148, 136, 0.35);
}

.pa-member-profile-banner__copy {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-member-profile-banner__eyebrow-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-member-profile-banner__name {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 850;
    color: var(--hcis-color-text-main, #0f172a);
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.pa-member-profile-banner__meta {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-top: 0.2rem;
}

.pa-profile-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.22rem 0.55rem;
    border-radius: 6px;
    background: rgba(248, 250, 252, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.95);
    font-size: 0.74rem;
    line-height: 1.25;
}

.pa-profile-meta-chip__icon {
    color: var(--hcis-color-text-muted, #64748b);
    flex-shrink: 0;
}

.pa-profile-meta-chip__label {
    color: var(--hcis-color-text-muted, #64748b);
    font-weight: 500;
}

.pa-profile-meta-chip__value {
    color: var(--hcis-color-text-main, #0f172a);
    font-weight: 750;
}

.pa-leader-form-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1rem;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 750;
    color: #ffffff;
    background: linear-gradient(135deg, var(--hcis-color-teal, #0d9488) 0%, #0f766e 100%);
    box-shadow: 0 3px 12px rgba(13, 148, 136, 0.3);
    text-decoration: none;
    white-space: nowrap;
    transition: all 160ms ease;
}

.pa-leader-form-cta-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 16px rgba(13, 148, 136, 0.4);
    color: #ffffff;
}

.pa-leader-form-cta-btn.is-completed {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 3px 12px rgba(16, 185, 129, 0.3);
}

/* 4. Leader Assessment Strip */
.pa-leader-assessment-strip {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(240, 253, 250, 0.85);
    border: 1px solid rgba(204, 251, 241, 0.95);
    margin-bottom: 1rem;
}

.pa-leader-assessment-strip__icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: rgba(13, 148, 136, 0.15);
    color: var(--hcis-color-teal, #0d9488);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-leader-assessment-strip__body {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
    flex: 1;
}

.pa-leader-assessment-strip__title-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-leader-assessment-strip__title-row strong {
    font-size: 0.84rem;
    font-weight: 750;
    color: #0f766e;
}

.pa-leader-assessment-strip__desc {
    margin: 0;
    font-size: 0.74rem;
    color: var(--hcis-color-text-muted, #475569);
    line-height: 1.35;
}

.pa-leader-assessment-strip__action {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 750;
    color: #0f766e;
    text-decoration: none;
    white-space: nowrap;
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid rgba(204, 251, 241, 0.9);
    transition: all 150ms ease;
}

.pa-leader-assessment-strip__action:hover {
    background: #f0fdfa;
    border-color: var(--hcis-color-teal, #0d9488);
}

/* 6. Hero Card & Cockpit (PA Saya Compact Layout) */
.pa-member-hero-card {
    margin-bottom: 1rem;
}

.pa-hero-header-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-hero-cockpit {
    display: grid;
    grid-template-columns: 1.3fr 1fr 1.15fr;
    gap: 1rem;
    padding: 0.25rem 0 1rem;
    border-bottom: 1px solid var(--hcis-color-border, #e2e8f0);
}

.pa-hero-pillar {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.95rem 1.1rem;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.85);
    backdrop-filter: blur(12px);
    box-shadow: 0 2px 12px -2px rgba(15, 23, 42, 0.03);
    position: relative;
    overflow: hidden;
    transition: transform 160ms ease, box-shadow 160ms ease;
}

.pa-hero-pillar:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px -3px rgba(15, 23, 42, 0.06);
}

.pa-hero-pillar__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

/* Grade Medallion Pillar */
.pa-hero-pillar--grade {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
}

.pa-hero-pillar--grade.is-grade-a {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.14) 100%);
    border-color: rgba(16, 185, 129, 0.35);
    box-shadow: 0 6px 18px -3px rgba(16, 185, 129, 0.14), 0 0 0 1px rgba(16, 185, 129, 0.18) inset;
}

.pa-hero-pillar--grade.is-grade-b {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.07) 0%, rgba(99, 102, 241, 0.14) 100%);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 6px 18px -3px rgba(79, 70, 229, 0.12), 0 0 0 1px rgba(99, 102, 241, 0.18) inset;
}

.pa-hero-pillar--grade.is-grade-c {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.07) 0%, rgba(217, 119, 6, 0.14) 100%);
    border-color: rgba(245, 158, 11, 0.35);
    box-shadow: 0 6px 18px -3px rgba(245, 158, 11, 0.12), 0 0 0 1px rgba(245, 158, 11, 0.18) inset;
}

.pa-hero-pillar--grade.is-grade-d {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.07) 0%, rgba(220, 38, 38, 0.14) 100%);
    border-color: rgba(239, 68, 68, 0.35);
    box-shadow: 0 6px 18px -3px rgba(239, 68, 68, 0.12), 0 0 0 1px rgba(239, 68, 68, 0.18) inset;
}

.pa-grade-medallion {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.pa-grade-medallion__ring {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 2rem;
    line-height: 1;
    flex-shrink: 0;
    box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.1), inset 0 1px 1px rgba(255, 255, 255, 0.85);
}

.is-grade-a .pa-grade-medallion__ring {
    background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    color: #ffffff;
    box-shadow: 0 6px 16px -2px rgba(16, 185, 129, 0.4), inset 0 2px 2px rgba(255, 255, 255, 0.5);
}

.is-grade-b .pa-grade-medallion__ring {
    background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
    color: #ffffff;
    box-shadow: 0 6px 16px -2px rgba(99, 102, 241, 0.4), inset 0 2px 2px rgba(255, 255, 255, 0.5);
}

.is-grade-c .pa-grade-medallion__ring {
    background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);
    color: #ffffff;
    box-shadow: 0 6px 16px -2px rgba(245, 158, 11, 0.4), inset 0 2px 2px rgba(255, 255, 255, 0.5);
}

.is-grade-d .pa-grade-medallion__ring {
    background: linear-gradient(135deg, #f43f5e 0%, #be123c 100%);
    color: #ffffff;
    box-shadow: 0 6px 16px -2px rgba(244, 63, 94, 0.4), inset 0 2px 2px rgba(255, 255, 255, 0.5);
}

.is-grade-neutral .pa-grade-medallion__ring {
    background: linear-gradient(135deg, #64748b 0%, #334155 100%);
    color: #ffffff;
}

.pa-grade-medallion__meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-grade-medallion__eyebrow-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-grade-calibrated-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 700;
    background: rgba(124, 58, 237, 0.14);
    color: #7c3aed;
}

.pa-grade-medallion__predicate {
    font-size: 1.05rem;
    font-weight: 850;
    color: var(--hcis-color-text-main, #0f172a);
    letter-spacing: -0.01em;
    line-height: 1.2;
}

.pa-grade-medallion__sub {
    font-size: 0.72rem;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.3;
}

/* Score Pillar */
.pa-hero-pillar--score {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.85) 100%);
}

.pa-hero-score-scale {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hero-score-number-row {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
    margin: 0.1rem 0 0.35rem;
}

.pa-hero-score-value {
    font-size: 2.1rem;
    line-height: 1;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-hero-score-unit {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hero-score-progress {
    height: 5px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.8);
    overflow: hidden;
    margin-bottom: 0.35rem;
}

.pa-hero-score-progress__bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-hero-score-caption {
    font-size: 0.7rem;
    color: var(--hcis-color-text-muted, #64748b);
}

/* Pillar 3: Composition (KPI vs Kompetensi 360°) */
.pa-hero-pillar--composition {
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-composition-tag {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-surface-soft, #f1f5f9);
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-composition-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin: 0.25rem 0 0.35rem;
}

.pa-composition-item {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-composition-item__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.45rem;
}

.pa-composition-item__left {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.pa-composition-item__label {
    font-size: 0.76rem;
    font-weight: 650;
    color: var(--hcis-slate-700, #334155);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-composition-item__right {
    display: flex;
    align-items: baseline;
    gap: 0.2rem;
    flex-shrink: 0;
}

.pa-composition-item__score {
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
}

.pa-composition-item__scale {
    font-size: 0.65rem;
    color: var(--hcis-slate-400, #94a3b8);
}

.pa-composition-track {
    width: 100%;
    height: 5px;
    background: var(--hcis-surface-soft, #f1f5f9);
    border-radius: 999px;
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-composition-bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}

.pa-composition-bar.is-kpi {
    background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
}

.pa-composition-bar.is-competency {
    background: linear-gradient(90deg, #8b5cf6 0%, #7c3aed 100%);
}

.pa-composition-caption {
    font-size: 0.68rem;
    color: var(--hcis-slate-500, #64748b);
    line-height: 1.3;
    margin-top: auto;
}

.pa-eyebrow {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-member-hero-meta {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
    padding-top: 0.95rem;
    margin-top: 0.85rem;
    border-top: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-hero-meta-item {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-hero-meta-item__label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hero-meta-item__value {
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 7. HAV Talent Quadrant Card (Leader Exclusive) */
.pa-member-hav-card {
    margin-bottom: 1rem;
}

.pa-member-hav-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-member-hav-banner__main {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
    flex: 1;
}

.pa-member-hav-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(124, 58, 237, 0.12);
    color: var(--hcis-color-purple, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-member-hav-banner__info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-member-hav-eyebrow-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-member-hav-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 850;
    color: var(--hcis-color-text-main, #0f172a);
    letter-spacing: -0.01em;
    line-height: 1.25;
}

.pa-member-hav-desc {
    margin: 0;
    font-size: 0.76rem;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.35;
}

.pa-member-hav-banner__cta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.45rem;
    flex-shrink: 0;
}

.pa-member-hav-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: #6366f1;
    background: rgba(99, 102, 241, 0.08);
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    border: 1px solid rgba(99, 102, 241, 0.18);
}

/* 8. Dual Visual Section: Radar + Qualitative Column (Strength & Dev Area) */
.pa-member-charts-section {
    margin-bottom: 1.25rem;
}

.pa-result-charts-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    align-items: stretch;
}

.pa-result-charts-grid.is-single-col {
    grid-template-columns: 1fr;
}

.pa-result-charts-grid > :deep(.hcis-card) {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.pa-qualitative-column {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    height: 100%;
}

.pa-result-charts-grid.is-single-col .pa-qualitative-column {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

.pa-qualitative-column > .pa-qualitative-card {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    margin-bottom: 0;
}

.pa-qualitative-column > .pa-qualitative-card :deep(.hcis-card__body) {
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* 9. Qualitative Cards Styling (Sleek Modern Callout Boxes) */
.pa-member-qualitative-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1rem;
    align-items: stretch;
}

.pa-qualitative-card {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.pa-qualitative-card__body {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    height: 100%;
}

.pa-qualitative-card__item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.95rem 1.15rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-left: 4px solid var(--hcis-color-teal, #0d9488);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
    line-height: 1.6;
    flex: 1;
}

:deep(.hcis-card--sentiment-purple) .pa-qualitative-card__item,
:deep(.hcis-card--purple) .pa-qualitative-card__item {
    border-left-color: var(--hcis-color-purple, #7c3aed);
    background: rgba(245, 243, 255, 0.6);
}

:deep(.hcis-card--sentiment-teal) .pa-qualitative-card__item,
:deep(.hcis-card--teal) .pa-qualitative-card__item {
    border-left-color: var(--hcis-color-teal, #0d9488);
    background: rgba(240, 253, 250, 0.6);
}

.pa-qualitative-card__quote-mark {
    font-size: 1.85rem;
    line-height: 0.8;
    font-family: Georgia, serif;
    color: var(--hcis-color-text-muted, #94a3b8);
    opacity: 0.55;
    user-select: none;
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.pa-qualitative-card__text {
    margin: 0;
    font-size: 0.86rem;
    font-weight: 500;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1.6;
    flex: 1;
    white-space: pre-line;
    word-break: break-word;
}

.pa-qualitative-card__text :deep(p) {
    margin: 0 0 0.45rem 0;
}

.pa-qualitative-card__text :deep(p:last-child) {
    margin-bottom: 0;
}

.pa-qualitative-card__text :deep(ul),
.pa-qualitative-card__text :deep(ol) {
    margin: 0.3rem 0 0.5rem 1.25rem;
    padding: 0;
}

.pa-qualitative-card__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 0.5rem;
    padding: 1.15rem 1rem;
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.85) 0%, rgba(241, 245, 249, 0.45) 100%);
    border: 1px dashed var(--hcis-border-subtle, #cbd5e1);
    flex: 1;
    min-height: 110px;
    box-sizing: border-box;
}

.pa-qualitative-card__empty-avatar {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    color: var(--hcis-slate-400, #94a3b8);
    flex-shrink: 0;
}

.pa-qualitative-card__empty-avatar.is-strength {
    color: var(--hcis-color-teal, #0d9488);
    background: rgba(240, 253, 250, 0.85);
    border-color: rgba(204, 251, 241, 0.9);
}

.pa-qualitative-card__empty-avatar.is-dev {
    color: var(--hcis-color-purple, #7c3aed);
    background: rgba(245, 243, 255, 0.85);
    border-color: rgba(237, 233, 254, 0.9);
}

.pa-qualitative-card__empty-icon {
    font-size: 1rem;
}

.pa-qualitative-card__empty-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    max-width: 340px;
}

.pa-qualitative-card__empty-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-slate-700, #334155);
}

.pa-qualitative-card__empty-desc {
    margin: 0;
    font-size: 0.72rem;
    line-height: 1.4;
    color: var(--hcis-slate-500, #64748b);
}

.pa-qualitative-card__empty-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 600;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: var(--hcis-slate-500, #64748b);
}

/* 10. Competency Card Grid */
.pa-member-competency-card {
    margin-top: 0.25rem;
    margin-bottom: 1rem;
}

.pa-competency-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 0.95rem;
}

.pa-competency-item {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.95rem 1.1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.03);
    cursor: pointer;
    transition: all 160ms ease;
    user-select: none;
}

.pa-competency-item:hover {
    background: rgba(255, 255, 255, 0.98);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 6px 16px -3px rgba(15, 23, 42, 0.07);
    transform: translateY(-1px);
}

.pa-competency-item__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.85rem;
}

.pa-competency-item__title-group {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
    min-width: 0;
    flex: 1;
}

.pa-competency-item__badge {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-color-brand-600, #4f46e5);
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.16);
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    line-height: 1.2;
}

.pa-competency-item__title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1.3;
    letter-spacing: -0.01em;
}

.pa-competency-item__score-group {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.2rem;
    flex-shrink: 0;
}

.pa-competency-item__score-display {
    display: flex;
    align-items: baseline;
    gap: 0.15rem;
}

.pa-competency-item__score-number {
    font-size: 1.25rem;
    font-weight: 850;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1;
    letter-spacing: -0.02em;
}

.pa-competency-item__score-scale {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-color-text-muted, #94a3b8);
}

.pa-competency-item__kkm-pill {
    font-size: 0.65rem;
    font-weight: 650;
    padding: 0.1rem 0.4rem;
    border-radius: 6px;
    background: rgba(241, 245, 249, 0.9);
    color: var(--hcis-color-text-muted, #64748b);
    border: 1px solid rgba(226, 232, 240, 0.9);
    line-height: 1.2;
}

.pa-competency-item__kkm-pill.is-pass {
    color: #059669;
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.25);
}

.pa-competency-item__kkm-pill.is-under {
    color: #d97706;
    background: rgba(245, 158, 11, 0.08);
    border-color: rgba(245, 158, 11, 0.25);
}

.pa-competency-item__progress-track {
    width: 100%;
    height: 5px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.85);
    overflow: hidden;
    margin: 0.1rem 0;
}

.pa-competency-item__progress-bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-competency-item__teaser {
    display: flex;
    flex-direction: column;
    padding: 0.5rem 0.65rem;
    border-radius: 6px;
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-competency-item__teaser-text {
    margin: 0;
    font-size: 0.75rem;
    color: var(--hcis-color-text-muted, #475569);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pa-competency-item__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.4rem;
    border-top: 1px solid rgba(226, 232, 240, 0.75);
}

.pa-competency-teaser-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.65rem;
    font-weight: 600;
    color: #0f766e;
    background: rgba(240, 253, 250, 0.9);
    border: 1px solid rgba(204, 251, 241, 0.9);
    padding: 0.12rem 0.4rem;
    border-radius: 6px;
}

.pa-competency-teaser-action {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-color-brand-600, #4f46e5);
    transition: all 150ms ease;
}

.pa-competency-item:hover .pa-competency-teaser-action {
    gap: 0.35rem;
    color: #4338ca;
}

/* Competency Detail Dialog Styling */
.pa-comp-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-comp-dialog-banner {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(248, 250, 252, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.95);
}

.pa-comp-dialog-score-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.pa-comp-dialog-score-col {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-comp-dialog-lbl {
    font-size: 0.62rem;
    font-weight: 750;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-comp-dialog-val-wrap {
    display: flex;
    align-items: baseline;
    gap: 0.2rem;
}

.pa-comp-dialog-score {
    font-size: 1.5rem;
    font-weight: 850;
    line-height: 1;
    color: var(--hcis-color-brand-600, #4f46e5);
    font-variant-numeric: tabular-nums;
}

.pa-comp-dialog-scale {
    font-size: 0.75rem;
    color: var(--hcis-color-text-muted, #94a3b8);
    font-weight: 600;
}

.pa-comp-dialog-status-col {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.3rem;
}

.pa-comp-dialog-status-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
}

.pa-comp-dialog-status-badge.is-pass {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-comp-dialog-status-badge.is-under {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.pa-comp-dialog-section {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.pa-competency-box-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    margin-bottom: 0.1rem;
}

.pa-competency-box-header__left {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-competency-box-header__icon {
    flex-shrink: 0;
}

.pa-competency-box-header__label {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-competency-box-header.is-teal .pa-competency-box-header__label {
    color: #0f766e;
}

.pa-competency-box-header__badge {
    font-size: 0.6rem;
    font-weight: 700;
    padding: 0.06rem 0.35rem;
    border-radius: 999px;
    background: rgba(13, 148, 136, 0.12);
    color: #0f766e;
    line-height: 1.2;
}

.pa-comp-dialog-desc-card {
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-comp-dialog-desc-text {
    margin: 0;
    font-size: 0.82rem;
    line-height: 1.5;
    color: var(--hcis-color-text-main, #334155);
}

.pa-comp-dialog-behaviour-card {
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(240, 253, 250, 0.75);
    border: 1px solid rgba(204, 251, 241, 0.95);
}

.pa-comp-dialog-perilaku-list {
    margin: 0;
    padding: 0;
}

/* 11. 360 Category Visibility */
.pa-category-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 0.75rem;
}

.pa-category-item {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.75rem 0.9rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-category-item.is-hidden {
    background: rgba(254, 242, 242, 0.6);
    border-color: rgba(254, 202, 202, 0.8);
}

.pa-category-item__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-category-item__code {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 750;
    color: #6366f1;
    margin-right: 0.3rem;
}

.pa-category-item__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.72rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-category-item__meta strong {
    font-size: 0.88rem;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-category-item__reason {
    margin: 0;
    font-size: 0.7rem;
    color: #b91c1c;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* 12. Visibility Policy */
.pa-card-header-flex {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.pa-card-header-flex h3 {
    margin: 0.1rem 0 0;
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-policy-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 0.75rem;
}

.pa-policy-grid > div {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.55rem 0.75rem;
    border-radius: 6px;
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-policy-grid span {
    font-size: 0.65rem;
    color: var(--hcis-color-text-muted, #64748b);
    text-transform: uppercase;
    font-weight: 650;
}

.pa-policy-grid strong {
    font-size: 0.82rem;
    color: var(--hcis-color-text-main, #0f172a);
}

/* 13. HAV 9-Box Modal Content */
.pa-hav-modal-content {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.pa-hav-coordinates-card {
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.95) 0%, rgba(245, 243, 255, 0.95) 100%);
    border: 1px solid rgba(199, 210, 254, 0.9);
    padding: 0.85rem 1.1rem;
}

.pa-hav-coord-item--result {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-hav-coord-item__result-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.65rem;
    font-weight: 750;
    color: var(--hcis-color-brand-600, #4f46e5);
    text-transform: uppercase;
}

.pa-hav-coord-item__result-name {
    font-size: 1.15rem;
    font-weight: 850;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-hav-coord-item__result-sub {
    font-size: 0.75rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hav-framework-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 0.4rem;
}

.pa-hav-axis-cols {
    display: grid;
    grid-template-columns: 90px repeat(3, 1fr);
    gap: 0.55rem;
    min-width: 480px;
}

.pa-axis-col-corner {
    display: flex;
    flex-direction: column;
    justify-content: center;
    font-size: 0.62rem;
    font-weight: 750;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.3;
}

.pa-axis-col-head {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 0.35rem 0.45rem;
    background: rgba(241, 245, 249, 0.85);
    border-radius: 6px;
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-axis-level-label {
    font-size: 0.72rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-axis-col-head small {
    font-size: 0.62rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hav-grid-with-y {
    display: grid;
    grid-template-columns: 90px 1fr;
    gap: 0.55rem;
    min-width: 480px;
}

.pa-hav-axis-rows {
    display: grid;
    grid-template-rows: repeat(3, 1fr);
    gap: 0.55rem;
}

.pa-axis-row-head {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    gap: 0.1rem;
    padding: 0.45rem;
    background: rgba(241, 245, 249, 0.85);
    border-radius: 6px;
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-axis-row-badge {
    font-size: 0.55rem;
    font-weight: 800;
    padding: 0.05rem 0.3rem;
    border-radius: 999px;
}

.pa-axis-row-badge.is-high {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
}

.pa-axis-row-badge.is-med {
    background: rgba(99, 102, 241, 0.15);
    color: #4f46e5;
}

.pa-axis-row-badge.is-low {
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
}

.pa-axis-row-head strong {
    font-size: 0.68rem;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-axis-row-head small {
    font-size: 0.58rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hav-matrix-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-template-rows: repeat(3, 1fr);
    gap: 0.55rem;
}

.pa-hav-quadrant-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.35rem;
    padding: 0.65rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    border: 1.5px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 160ms ease;
}

.pa-hav-quadrant-card.is-user-box {
    border-color: var(--pa-hav-color, #7c3aed);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(245, 243, 255, 0.85) 100%);
    box-shadow: 0 3px 14px rgba(124, 58, 237, 0.2), 0 0 0 1.5px var(--pa-hav-color, #7c3aed);
    transform: translateY(-1px);
}

.pa-hav-quadrant-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.3rem;
}

.pa-hav-quadrant-badges {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.pa-hav-quadrant-axes {
    font-size: 0.58rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hav-user-current-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.58rem;
    font-weight: 750;
    color: #7c3aed;
    background: rgba(124, 58, 237, 0.12);
    padding: 0.08rem 0.35rem;
    border-radius: 999px;
}

.pa-hav-cell__pulse-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #7c3aed;
    box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.3);
}

.pa-hav-quadrant-title-row {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-hav-box-icon {
    color: var(--pa-hav-color, #64748b);
    flex-shrink: 0;
}

.pa-hav-quadrant-name {
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1.25;
}

.pa-hav-quadrant-desc {
    margin: 0;
    font-size: 0.65rem;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pa-hav-quadrant-card__footer {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding-top: 0.3rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
}

.pa-hav-guideline-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.58rem;
    font-weight: 700;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hav-guideline-text {
    margin: 0;
    font-size: 0.62rem;
    color: var(--hcis-color-text-main, #334155);
    line-height: 1.25;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* HAV Insight Guidance Card */
.pa-hav-position-insight-card {
    padding: 1rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(248, 250, 252, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.95);
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-hav-insight-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-hav-insight-icon-box {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(13, 148, 136, 0.12);
    color: var(--hcis-color-teal, #0d9488);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-hav-insight-title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-hav-insight-subtitle {
    margin: 0;
    font-size: 0.72rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hav-insight-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.75rem;
}

.pa-hav-insight-box {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0.75rem 0.85rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-hav-insight-box__head {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-hav-insight-box p {
    margin: 0;
    font-size: 0.74rem;
    color: var(--hcis-color-text-muted, #475569);
    line-height: 1.4;
}

.pa-hav-insight-box--action {
    background: rgba(240, 253, 250, 0.6);
    border-color: rgba(204, 251, 241, 0.95);
}

/* ═══════════════════════════════════════════════════════════════════
   RESPONSIVE DESIGN (Mobile <= 768px & Tablet 769px-1024px)
   ═══════════════════════════════════════════════════════════════════ */
@media (max-width: 768px) {
    .pa-member-nav-strip {
        position: sticky;
        top: 0.5rem;
        z-index: 25;
        padding: 0.6rem 0.85rem;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(16px);
        margin-bottom: 0.75rem;
    }

    .pa-member-nav-strip__desktop {
        display: none;
    }

    .pa-member-nav-strip__mobile {
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
        width: 100%;
    }

    .pa-member-nav-strip__mobile-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        width: 100%;
    }

    .pa-member-stepper-pill.is-mobile {
        padding: 0.15rem 0.3rem;
    }

    .pa-member-stepper-pill.is-mobile .pa-member-stepper-btn {
        width: 30px;
        height: 30px;
    }

    .pa-member-stepper-pill.is-mobile .pa-member-stepper-content {
        font-size: 0.78rem;
        padding: 0 0.35rem;
    }

    .pa-member-nav-strip__mobile-member {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding-top: 0.5rem;
        border-top: 1px dashed rgba(226, 232, 240, 0.95);
        width: 100%;
    }

    .pa-member-nav-avatar {
        width: 34px;
        height: 34px;
        border-radius: 999px;
        background: linear-gradient(135deg, rgba(13, 148, 136, 0.15) 0%, rgba(13, 148, 136, 0.28) 100%);
        color: var(--hcis-color-teal, #0d9488);
        font-size: 0.78rem;
        font-weight: 850;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 1px solid rgba(13, 148, 136, 0.25);
    }

    .pa-member-nav-info {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        min-width: 0;
        flex: 1;
    }

    .pa-member-nav-eyebrow-row {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .pa-member-nav-pos-pill {
        font-size: 0.62rem;
        font-weight: 750;
        color: var(--hcis-color-teal, #0d9488);
        background: rgba(13, 148, 136, 0.08);
        padding: 0.05rem 0.35rem;
        border-radius: 999px;
    }

    .pa-member-nav-strip__name {
        font-size: 0.96rem;
        max-width: 100%;
    }

    .pa-member-nav-grade-badge {
        flex-shrink: 0;
    }

    .pa-member-profile-banner {
        flex-direction: column;
        align-items: stretch;
        gap: 0.85rem;
        padding: 0.85rem 1rem;
    }

    .pa-member-profile-banner__main {
        align-items: flex-start;
        gap: 0.85rem;
    }

    .pa-member-profile-banner__avatar {
        width: 44px;
        height: 44px;
        font-size: 1.1rem;
        border-radius: 12px;
    }

    .pa-member-profile-banner__name {
        font-size: 1.15rem;
    }

    .pa-member-profile-banner__meta {
        gap: 0.35rem;
        margin-top: 0.25rem;
    }

    .pa-profile-meta-chip {
        font-size: 0.7rem;
        padding: 0.18rem 0.45rem;
    }

    .pa-member-profile-banner__actions {
        width: 100%;
    }

    .pa-leader-form-cta-btn {
        width: 100%;
        justify-content: center;
        padding: 0.65rem 0.85rem;
    }

    .pa-leader-assessment-strip {
        flex-direction: column;
        align-items: flex-start;
        padding: 0.75rem 0.9rem;
    }

    .pa-leader-assessment-strip__action {
        width: 100%;
        justify-content: center;
    }

    .pa-member-hero-card {
        margin-bottom: 0.75rem;
    }

    .pa-member-hero-card :deep(.hcis-card__body) {
        padding: 0.65rem 0.75rem !important;
    }

    .pa-hero-cockpit {
        grid-template-columns: 1fr 1fr;
        gap: 0.45rem;
        padding: 0.1rem 0 0.5rem;
    }

    .pa-hero-pillar {
        padding: 0.55rem 0.65rem;
        border-radius: 8px;
    }

    .pa-hero-pillar--grade {
        grid-column: 1;
    }

    .pa-hero-pillar--score {
        grid-column: 2;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .pa-hero-pillar--composition {
        grid-column: 1 / -1;
        padding: 0.55rem 0.65rem;
    }

    .pa-grade-medallion {
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .pa-grade-medallion__ring {
        width: 36px;
        height: 36px;
        font-size: 1.25rem;
        border-radius: 8px;
        flex-shrink: 0;
    }

    .pa-grade-medallion__meta {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        min-width: 0;
    }

    .pa-grade-medallion__eyebrow-row {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        flex-wrap: nowrap;
    }

    .pa-grade-medallion__eyebrow-row .pa-eyebrow {
        font-size: 0.56rem;
        letter-spacing: 0.03em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pa-grade-calibrated-tag {
        font-size: 0.52rem;
        padding: 0.05rem 0.25rem;
    }

    .pa-grade-medallion__predicate {
        font-size: 0.76rem;
        font-weight: 800;
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pa-grade-medallion__sub {
        display: none;
    }

    .pa-hero-pillar--score .pa-hero-pillar__head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
    }

    .pa-hero-pillar--score .pa-eyebrow {
        font-size: 0.56rem;
    }

    .pa-hero-score-scale {
        font-size: 0.58rem;
    }

    .pa-hero-score-value {
        font-size: 1.35rem;
        line-height: 1;
    }

    .pa-hero-score-unit {
        font-size: 0.65rem;
    }

    .pa-hero-score-number-row {
        margin: 0.05rem 0 0.15rem;
        gap: 0.2rem;
    }

    .pa-hero-score-progress {
        height: 4px;
        margin-bottom: 0;
    }

    .pa-hero-score-caption {
        display: none;
    }

    .pa-hero-pillar--composition .pa-hero-pillar__head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.25rem;
    }

    .pa-hero-pillar--composition .pa-eyebrow {
        font-size: 0.56rem;
    }

    .pa-composition-tag {
        font-size: 0.56rem;
        padding: 0.05rem 0.35rem;
    }

    /* Stack composition vertically so labels and scores have full width without clipping */
    .pa-composition-list {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        margin: 0;
    }

    .pa-composition-item {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .pa-composition-item__row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pa-composition-item__left {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        min-width: 0;
    }

    .pa-composition-item__label {
        font-size: 0.72rem;
        font-weight: 650;
        color: var(--hcis-slate-700, #334155);
        white-space: nowrap;
    }

    .pa-composition-item__right {
        display: flex;
        align-items: baseline;
        gap: 0.15rem;
        flex-shrink: 0;
    }

    .pa-composition-item__score {
        font-size: 0.78rem;
        font-weight: 800;
    }

    .pa-composition-item__scale {
        font-size: 0.6rem;
    }

    .pa-composition-track {
        height: 4px;
    }

    .pa-composition-caption {
        display: none;
    }

    /* 4. Symmetrical 2x2 Meta Grid */
    .pa-member-hero-meta {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.35rem !important;
        padding-top: 0.45rem !important;
        margin-top: 0.45rem !important;
        border-top: 1px solid rgba(226, 232, 240, 0.75) !important;
    }

    .pa-hero-meta-item {
        background: rgba(248, 250, 252, 0.85);
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 6px;
        padding: 0.3rem 0.45rem;
        gap: 0.08rem;
        min-width: 0;
    }

    .pa-hero-meta-item__label {
        font-size: 0.58rem !important;
        font-weight: 700;
        line-height: 1.1;
    }

    .pa-hero-meta-item__value {
        font-size: 0.74rem !important;
        font-weight: 750;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pa-member-hav-banner {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.85rem;
    }

    .pa-member-hav-banner__cta {
        width: 100%;
        align-items: stretch;
    }

    .pa-hav-open-btn {
        width: 100%;
        justify-content: center;
    }

    /* 5. Expanded Radar Chart Canvas on Mobile (Hide subtitle & metric badge) */
    .pa-member-charts-section .hcis-chart-wrapper .hcis-card__description,
    .pa-member-charts-section .hcis-chart__top-banner {
        display: none !important;
    }

    .pa-result-charts-grid {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }

    .pa-qualitative-column {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }

    .pa-qualitative-card__item {
        padding: 0.75rem 0.85rem;
        gap: 0.55rem;
        border-radius: 8px;
    }

    .pa-qualitative-card__quote-mark {
        font-size: 1.5rem;
    }

    .pa-qualitative-card__text {
        font-size: 0.82rem;
        line-height: 1.5;
    }

    .pa-qualitative-card__empty {
        padding: 0.95rem 0.85rem;
        min-height: 95px;
    }

    .pa-competency-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .pa-competency-item {
        padding: 0.85rem 0.95rem;
    }

    .pa-competency-item__header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
    }

    /* Skeleton Mobile Styles */
    .pa-skeleton-profile-banner {
        padding: 0.85rem 1rem;
        gap: 0.75rem;
    }

    .pa-skeleton-avatar {
        width: 44px;
        height: 44px;
    }

    .pa-skeleton-hero-cockpit {
        grid-template-columns: 1fr 1fr;
    }

    .pa-skeleton-pillar.is-comp {
        grid-column: span 2;
    }

    .pa-skeleton-meta-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem;
    }

    .pa-skeleton-dual-grid {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    .pa-hero-cockpit {
        grid-template-columns: repeat(2, 1fr);
    }

    .pa-hero-pillar--grade {
        grid-column: span 2;
    }

    .pa-result-charts-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .pa-qualitative-column {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.85rem;
    }
}
.pa-tingkat-dituntut { margin-left: 0.4rem; padding: 0.05rem 0.45rem; border-radius: 999px; background: #ede9fe; color: #5b21b6; font-size: 0.68rem; font-weight: 700; white-space: nowrap; }
</style>

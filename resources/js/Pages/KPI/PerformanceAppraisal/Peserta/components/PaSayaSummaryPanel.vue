<template>
    <section class="pa-saya-summary-panel" aria-label="Ringkasan penilaian saya">
        <!-- Error State -->
        <HcisResultState
            v-if="error"
            :status="errorStatus === 403 ? 'warning' : 'danger'"
            variant="glass"
            layout="compact"
            :title="errorStatus === 403 ? 'Ringkasan tidak tersedia' : 'Ringkasan gagal dimuat'"
            :description="error"
            :error-code="errorCode"
            :request-id="requestId"
            primary-text="Coba lagi"
            primary-icon="arrow-clockwise"
            @primary-click="$emit('retry')"
        />

        <!-- Loading Skeleton -->
        <div v-else-if="loading && !data" class="pa-summary-loading-grid">
            <div class="pa-summary-loading-row-4">
                <HcisSkeleton height="11rem" rounded="xl" />
                <HcisSkeleton height="11rem" rounded="xl" />
                <HcisSkeleton height="11rem" rounded="xl" />
            </div>
            <div class="pa-summary-loading-row">
                <HcisSkeleton height="18rem" rounded="xl" />
                <HcisSkeleton height="18rem" rounded="xl" />
            </div>
            <HcisSkeleton height="20rem" rounded="xl" />
        </div>

        <!-- Global Empty State (No Data at all) -->
        <HcisEmptyState
            v-else-if="!data"
            variant="card"
            sentiment="neutral"
            size="md"
            icon="clipboard2-check"
            title="Belum Ada Data Evaluasi"
            description="Ringkasan evaluasi akan tampil setelah Anda terdaftar sebagai peserta atau penilai dalam cycle Performance Appraisal aktif."
            class="pa-summary-empty-card"
        >
            <template #actions>
                <div class="pa-summary-empty-actions">
                    <HcisButton
                        label="Periksa Hasil Saya"
                        variant="secondary"
                        size="sm"
                        leading-icon="award"
                        @click="$emit('switch-tab', 'hasil')"
                    />
                    <HcisButton
                        label="Lihat Riwayat Cycle"
                        variant="ghost"
                        size="sm"
                        leading-icon="clock-history"
                        @click="$emit('switch-tab', 'riwayat')"
                    />
                </div>
            </template>
        </HcisEmptyState>

        <!-- Main Dynamic Dashboard -->
        <div v-else class="pa-summary-dashboard">
            <!-- 1. HIGH-IMPACT EXECUTIVE HIGHLIGHT CARDS (4 BENTO CARDS) -->
            <div class="pa-summary-kpi-grid">
                <!-- Card 1: Hasil Resmi & Grade Akhir -->
                <div class="pa-summary-kpi-card is-result" @click="$emit('switch-tab', 'hasil')">
                    <div class="pa-summary-kpi-card__head">
                        <div class="pa-summary-kpi-card__badge-wrap">
                            <div class="pa-summary-kpi-card__icon is-success">
                                <HcisIcon name="award-fill" size="2xs" />
                            </div>
                            <span class="pa-summary-kpi-card__kicker">HASIL RESMI</span>
                        </div>
                        <span class="pa-summary-kpi-card__arrow" title="Buka Hasil">
                            <HcisIcon name="arrow-up-right" size="2xs" />
                        </span>
                    </div>

                    <div class="pa-summary-kpi-card__body">
                        <div class="pa-result-hero-pill">
                            <div class="pa-result-grade-medallion" :class="`is-grade-${String(userGrade || 'none').toLowerCase().replace('+', '-plus').replace('_plus', '-plus')}`">
                                <span>{{ displayGrade(userGrade) }}</span>
                            </div>
                            <div class="pa-result-grade-meta">
                                <strong class="pa-result-score-val">{{ formatSkor(userFinalScore) }}</strong>
                                <span class="pa-result-predicate">{{ getGradeLabel(userGrade, userGradeName) }}</span>
                            </div>
                        </div>

                        <div class="pa-summary-kpi-card__sub">
                            <span v-if="userFinalScore && hasHistory && historyDeltaDir !== 'none'" class="pa-trend-badge" :class="`is-${historyDeltaDir}`">
                                <HcisIcon :name="historyDeltaDir === 'up' ? 'arrow-up-right' : (historyDeltaDir === 'down' ? 'arrow-down-right' : 'dash')" size="2xs" />
                                <span>{{ historyDeltaText }} vs lalu</span>
                            </span>
                            <span v-else-if="userFinalScore" class="pa-text-subtle">
                                Skor terverifikasi resmi
                            </span>
                            <span v-else class="pa-text-subtle">
                                Menunggu penerbitan hasil
                            </span>
                        </div>
                    </div>

                    <div class="pa-summary-kpi-card__foot">
                        <span>Lihat Rincian Hasil</span>
                        <HcisIcon name="chevron-right" size="2xs" />
                    </div>
                </div>

                <!-- Card 3: Komposisi Skor & Bobot (KPI vs Kompetensi) -->
                <div class="pa-summary-kpi-card is-composition" @click="$emit('switch-tab', 'hasil')">
                    <div class="pa-summary-kpi-card__head">
                        <div class="pa-summary-kpi-card__badge-wrap">
                            <div class="pa-summary-kpi-card__icon is-brand">
                                <HcisIcon name="sliders" size="2xs" />
                            </div>
                            <span class="pa-summary-kpi-card__kicker">KOMPOSISI BOBOT</span>
                        </div>
                        <span class="pa-summary-kpi-card__arrow" title="Buka Bobot">
                            <HcisIcon name="arrow-up-right" size="2xs" />
                        </span>
                    </div>

                    <div class="pa-summary-kpi-card__body">
                        <div class="pa-composition-dual-row">
                            <div class="pa-comp-col">
                                <span class="pa-comp-lbl">Capaian KPI</span>
                                <strong class="pa-comp-val text-brand">{{ formatSkor(userKpiScore) }}</strong>
                                <small class="pa-comp-sub">{{ labelBobot(userKpiWeight) }}</small>
                            </div>
                            <div class="pa-comp-divider" />
                            <div class="pa-comp-col">
                                <span class="pa-comp-lbl">Kompetensi 360°</span>
                                <strong class="pa-comp-val text-purple">{{ formatSkor(userCompScore) }}</strong>
                                <small class="pa-comp-sub">{{ labelBobot(userCompWeight) }}</small>
                            </div>
                        </div>

                        <!-- Proporsi BOBOT yang dipakai menghitung, bukan perbandingan skor. -->
                        <div v-if="userKpiWeight !== null && userCompWeight !== null" class="pa-comp-balance-bar" data-uji="bar-bobot">
                            <div
                                class="pa-comp-balance-fill is-kpi"
                                :style="{ width: `${persenBobot(userKpiWeight)}%` }"
                                :title="`Bobot KPI ${labelAngka(userKpiWeight)}%`"
                            />
                            <div
                                class="pa-comp-balance-fill is-comp"
                                :style="{ width: `${persenBobot(userCompWeight)}%` }"
                                :title="`Bobot Kompetensi ${labelAngka(userCompWeight)}%`"
                            />
                        </div>
                    </div>

                    <div class="pa-summary-kpi-card__foot">
                        <span>Periksa Rincian Bobot</span>
                        <HcisIcon name="chevron-right" size="2xs" />
                    </div>
                </div>

                <!-- Card 4: Tanggung Jawab Kuesioner Penilai -->
                <div class="pa-summary-kpi-card is-workload" @click="$emit('switch-tab', 'assignment')">
                    <div class="pa-summary-kpi-card__head">
                        <div class="pa-summary-kpi-card__badge-wrap">
                            <div class="pa-summary-kpi-card__icon is-teal">
                                <HcisIcon name="clipboard-check-fill" size="2xs" />
                            </div>
                            <span class="pa-summary-kpi-card__kicker">TUGAS PENILAI</span>
                        </div>
                        <span class="pa-summary-kpi-card__arrow" title="Buka Tugas Penilai">
                            <HcisIcon name="arrow-up-right" size="2xs" />
                        </span>
                    </div>

                    <div class="pa-summary-kpi-card__body">
                        <div class="pa-workload-stat-row">
                            <div class="pa-workload-rate">
                                <strong>{{ angka(data.total_assignment) > 0 ? `${angka(data.progres_persen)}%` : '—' }}</strong>
                                <span>Terisi</span>
                            </div>
                            <div class="pa-workload-counts">
                                <strong>{{ angka(data.sudah_terkirim) }} / {{ angka(data.total_assignment) }}</strong>
                                <span>Terkirim</span>
                            </div>
                        </div>

                        <div class="pa-workload-progress-bar">
                            <div
                                class="pa-workload-progress-fill"
                                :style="{ width: `${angka(data.total_assignment) > 0 ? Math.min(100, Math.max(0, angka(data.progres_persen))) : 0}%` }"
                            />
                        </div>

                        <div class="pa-workload-status-bar">
                            <span v-if="angka(data.belum_dikirim) > 0" class="pa-workload-alert is-warning" data-uji="belum-dikirim">
                                <HcisIcon name="send-exclamation" size="2xs" />
                                <span>{{ angka(data.belum_dikirim) }} lembar terisi belum dikirim</span>
                            </span>
                            <span v-if="angka(data.terlambat) > 0" class="pa-workload-alert is-danger">
                                <HcisIcon name="exclamation-circle-fill" size="2xs" />
                                <span>{{ angka(data.terlambat) }} tugas terlambat</span>
                            </span>
                            <span v-if="angka(data.deadline_dekat) > 0" class="pa-workload-alert is-warning">
                                <HcisIcon name="clock-fill" size="2xs" />
                                <span>{{ angka(data.deadline_dekat) }} mendekati tenggat</span>
                            </span>
                            <span v-if="!angka(data.terlambat) && !angka(data.deadline_dekat) && !angka(data.belum_dikirim)" class="pa-workload-alert is-success">
                                <HcisIcon name="check-circle-fill" size="2xs" />
                                <span>{{ angka(data.total_assignment) > 0 ? 'Semua jadwal aman & terkendali' : 'Tidak ada tugas di cycle yang sedang dibuka' }}</span>
                            </span>
                        </div>

                        <!-- 6. Tugas di cycle yang tidak sedang dibuka: disebut, bukan disembunyikan. -->
                        <p v-if="diLuarJendela" class="pa-workload-luar-jendela" data-uji="di-luar-jendela">
                            <HcisIcon name="info-circle" size="2xs" />
                            <span>{{ diLuarJendela }}</span>
                        </p>
                    </div>

                    <div class="pa-summary-kpi-card__foot">
                        <span>Kelola Tugas Penilaian</span>
                        <HcisIcon name="chevron-right" size="2xs" />
                    </div>
                </div>
            </div>

            <!-- 3. DEEP ANALYTICS: 360° RATER MATRIX & COMPETENCY HIGHLIGHTS (2-COL) -->
            <div class="pa-summary-middle-grid">
                <!-- Left: 360-Degree Rater Category Progress & Donut Breakdown -->
                <HcisCard
                    title="Progres Kuesioner Penilai (360°)"
                    kicker="DISTRIBUSI RATER"
                    description="Keterisian kuesioner evaluasi berdasarkan relasi penilai dan kategori peran"
                    icon="people-fill"
                    sentiment="purple"
                    variant="glass"
                    pattern="rings"
                    class="pa-summary-rater-card"
                >
                    <template #actions>
                        <HcisButton
                            v-if="angka(data.total_assignment) > 0"
                            label="Buka Tugas"
                            variant="glass"
                            size="xs"
                            trailing-icon="arrow-right"
                            @click="$emit('switch-tab', 'assignment')"
                        />
                        <HcisBadge
                            v-else
                            label="Tuntas"
                            variant="success"
                            appearance="glass"
                            size="xs"
                            dot
                        />
                    </template>

                    <div class="pa-rater-compact-wrap">
                        <!-- Left: Compact Donut Visual Section -->
                        <div class="pa-rater-donut-side">
                            <HcisChart
                                type="donut"
                                :card="false"
                                :height="donutChartHeight"
                                :data="chartDonutData"
                                :metric-value="angka(data.total_assignment) > 0 ? `${angka(data.progres_persen)}%` : '—'"
                                :options="{ legend: { show: false } }"
                            />
                            <div class="pa-rater-donut-caption">
                                <HcisBadge
                                    :label="angka(data.total_assignment) > 0 ? `${angka(data.sudah_selesai)}/${angka(data.total_assignment)} Terisi` : 'Tidak Ada Tugas Aktif'"
                                    :variant="angka(data.total_assignment) === 0 || angka(data.progres_persen) === 100 ? 'success' : 'purple'"
                                    appearance="glass"
                                    size="xs"
                                    dot
                                />
                            </div>
                        </div>

                        <!-- Right: Compact Category Progress List -->
                        <div v-if="kategoriList.length" class="pa-category-compact-list">
                            <div
                                v-for="k in kategoriList"
                                :key="k.kode_kategori"
                                class="pa-category-compact-item"
                                :class="{ 'is-tuntas': k.tuntas, 'is-urgent': angka(k.terlambat) > 0 || angka(k.deadline_dekat) > 0 }"
                                @click="$emit('switch-tab', 'assignment')"
                            >
                                <div class="pa-category-compact-head">
                                    <div class="pa-category-compact-title">
                                        <div
                                            class="pa-category-compact-icon"
                                            :style="{
                                                color: getCategoryColor(k.kode_kategori),
                                                backgroundColor: `${getCategoryColor(k.kode_kategori)}15`,
                                            }"
                                        >
                                            <HcisIcon :name="getCategoryIcon(k.kode_kategori)" size="2xs" />
                                        </div>
                                        <span class="pa-category-compact-name">{{ formatCategoryLabel(k.nama_kategori, k.kode_kategori) }}</span>
                                    </div>
                                    <div class="pa-category-compact-meta">
                                        <span class="pa-category-compact-count">{{ angka(k.selesai) }}/{{ angka(k.total) }}</span>
                                        <HcisBadge
                                            :label="`${angka(k.persen)}%`"
                                            :variant="k.tuntas ? 'success' : (angka(k.terlambat) > 0 ? 'danger' : 'purple')"
                                            appearance="glass"
                                            size="xs"
                                            :glow="k.tuntas"
                                            :dot="k.tuntas"
                                        />
                                    </div>
                                </div>

                                <div class="pa-category-compact-track">
                                    <div
                                        class="pa-category-compact-fill"
                                        :style="{
                                            width: `${Math.min(100, Math.max(0, angka(k.persen)))}%`,
                                            backgroundColor: getCategoryColor(k.kode_kategori),
                                        }"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Fallback: Standard Guidance Principles -->
                        <div v-else class="pa-summary-guide-compact-list">
                            <div class="pa-guide-compact-item">
                                <div class="pa-guide-compact-icon is-brand">
                                    <HcisIcon name="eye-fill" size="2xs" />
                                </div>
                                <div class="pa-guide-compact-text">
                                    <strong>Objektivitas 360°</strong>
                                    <span>Penilaian berbasis bukti perilaku nyata.</span>
                                </div>
                            </div>

                            <div class="pa-guide-compact-item">
                                <div class="pa-guide-compact-icon is-purple">
                                    <HcisIcon name="shield-lock-fill" size="2xs" />
                                </div>
                                <div class="pa-guide-compact-text">
                                    <strong>Kerahasiaan Penilai</strong>
                                    <span>Skor diagregasikan tanpa identitas individual.</span>
                                </div>
                            </div>

                            <div class="pa-guide-compact-item">
                                <div class="pa-guide-compact-icon is-teal">
                                    <HcisIcon name="chat-square-quote-fill" size="2xs" />
                                </div>
                                <div class="pa-guide-compact-text">
                                    <strong>Umpan Balik Konstruktif</strong>
                                    <span>Fokus pada akselerasi kapabilitas kerja.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </HcisCard>

                <!-- Right: Competency Highlights & Development Priorities Card -->
                <HcisCard
                    title="Sorotan Kompetensi & Rekam Jejak"
                    kicker="ANALISIS KOMPETENSI"
                    description="Identifikasi kekuatan utama personal dan prioritas pengembangan kompetensi"
                    icon="bullseye"
                    sentiment="teal"
                    variant="glass"
                    pattern="dots"
                    class="pa-summary-insights-card"
                >
                    <template #actions>
                        <HcisButton
                            label="Lihat Rincian"
                            variant="glass"
                            size="xs"
                            trailing-icon="arrow-right"
                            @click="$emit('switch-tab', 'hasil')"
                        />
                    </template>

                    <div class="pa-insights-body">
                        <!-- Top Strengths & Growth Areas Grid -->
                        <div class="pa-insights-dual-grid">
                            <!-- Strengths -->
                            <div class="pa-insights-box is-strength">
                                <div class="pa-insights-box__head">
                                    <div class="pa-insights-box__icon is-success">
                                        <HcisIcon name="star-fill" size="2xs" />
                                    </div>
                                    <strong>Kompetensi Unggulan</strong>
                                    <span class="pa-insights-box__tag">Skor Tertinggi</span>
                                </div>

                                <div v-if="topStrengths.length" class="pa-insights-items-list">
                                    <div
                                        v-for="s in topStrengths"
                                        :key="s.Kode_Competency || s.Nama_Competency"
                                        class="pa-insights-item"
                                    >
                                        <div class="pa-insights-item__top">
                                            <span class="pa-insights-item__name" :title="s.Nama_Competency">{{ s.Nama_Competency }}</span>
                                            <strong class="pa-insights-item__val text-success">{{ formatSkor(s.Nilai) }}</strong>
                                        </div>
                                        <div class="pa-insights-item__bar-wrap">
                                            <div
                                                class="pa-insights-item__bar is-success"
                                                :style="{ width: `${Math.min(100, Math.max(0, (Number(s.Nilai) / 100) * 100))}%` }"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="pa-insights-empty-sub">
                                    <span>Data kompetensi akan tampil setelah hasil penilaian diterbitkan.</span>
                                </div>
                            </div>

                            <!-- Growth Areas -->
                            <div class="pa-insights-box is-growth">
                                <div class="pa-insights-box__head">
                                    <div class="pa-insights-box__icon is-amber">
                                        <HcisIcon name="lightning-charge-fill" size="2xs" />
                                    </div>
                                    <strong>Fokus Pengembangan</strong>
                                    <span class="pa-insights-box__tag">Peluang Tumbuh</span>
                                </div>

                                <div v-if="growthPriorities.length" class="pa-insights-items-list">
                                    <div
                                        v-for="g in growthPriorities"
                                        :key="g.Kode_Competency || g.Nama_Competency"
                                        class="pa-insights-item"
                                    >
                                        <div class="pa-insights-item__top">
                                            <span class="pa-insights-item__name" :title="g.Nama_Competency">{{ g.Nama_Competency }}</span>
                                            <strong class="pa-insights-item__val text-amber">{{ formatSkor(g.Nilai) }}</strong>
                                        </div>
                                        <div class="pa-insights-item__bar-wrap">
                                            <div
                                                class="pa-insights-item__bar is-amber"
                                                :style="{ width: `${Math.min(100, Math.max(0, (Number(g.Nilai) / 100) * 100))}%` }"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="pa-insights-empty-sub">
                                    <span>Area prioritas pengembangan akan teridentifikasi dari hasil kalibrasi.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Multi-Cycle Historical Performance Strip -->
                        <div v-if="hasHistory" class="pa-personal-stats-footer">
                            <div class="pa-personal-stats-history-row">
                                <div class="pa-history-stat-mini">
                                    <span class="pa-history-mini-lbl">Rata-rata Historis</span>
                                    <strong class="pa-history-mini-val text-teal">{{ formatSkor(historyRataRata) }}</strong>
                                </div>
                                <div class="pa-history-stat-mini">
                                    <span class="pa-history-mini-lbl">Nilai Terbaik</span>
                                    <strong class="pa-history-mini-val text-success">{{ formatSkor(historyTerbaik) }}</strong>
                                </div>
                                <div class="pa-history-stat-mini">
                                    <span class="pa-history-mini-lbl">Tren Skor Terakhir</span>
                                    <strong
                                        class="pa-history-mini-val"
                                        :class="historyDeltaDir === 'up' ? 'text-success' : (historyDeltaDir === 'down' ? 'text-danger' : 'text-slate')"
                                    >
                                        <HcisIcon
                                            v-if="historyDeltaDir === 'up'"
                                            name="arrow-up-right"
                                            size="2xs"
                                        />
                                        <HcisIcon
                                            v-else-if="historyDeltaDir === 'down'"
                                            name="arrow-down-right"
                                            size="2xs"
                                        />
                                        {{ historyDeltaText }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </HcisCard>
            </div>

            <!-- 4. COMPETENCY RADAR DEEP-DIVE (When Competencies are Available) -->
            <div v-if="radarKompetensi.length > 0" class="pa-summary-radar-section">
                <HcisChart
                    type="radar"
                    title="Pemetaan Dimensi Kompetensi 360°"
                    kicker="RADAR EVALUASI · TALENT PROFILE"
                    :description="radarDeskripsi"
                    icon="bullseye"
                    sentiment="teal"
                    variant="glass"
                    pattern="grid"
                    :height="radarChartHeight"
                    :categories="radarCategories"
                    :data="radarData"
                    :series="radarSeries"
                    allow-export
                />
            </div>

            <!-- 5. MULTI-CYCLE PERFORMANCE TRAJECTORY & SUMMARY (Replacing static guidance) -->
            <HcisCard
                title="Rekam Jejak & Lintasan Performa Lintas Cycle"
                kicker="HISTORIS · MULTI-CYCLE TRAJECTORY"
                description="Ringkasan pencapaian skor resmi, capaian KPI vs kompetensi 360°, dan grade akhir lintas periode evaluasi"
                icon="clock-history"
                sentiment="teal"
                variant="glass"
                pattern="grid"
                class="pa-summary-trajectory-card"
            >
                <template #actions>
                    <HcisButton
                        label="Buka Seluruh Riwayat"
                        variant="glass"
                        size="xs"
                        trailing-icon="arrow-right"
                        @click="$emit('switch-tab', 'riwayat')"
                    />
                </template>

                <div v-if="historyPeriods.length > 0" class="pa-mini-history-wrap">
                    <!-- Top Metrics Overview Strip -->
                    <div class="pa-mini-history-kpis">
                        <div class="pa-mini-kpi-item">
                            <span class="pa-mini-kpi-lbl">Rata-rata Skor</span>
                            <strong class="pa-mini-kpi-val text-teal">{{ formatSkor(historyRataRata) }}</strong>
                            <small class="pa-mini-kpi-sub">Dari {{ historyPeriods.length }} cycle terbit</small>
                        </div>
                        <div class="pa-mini-kpi-divider" />
                        <div class="pa-mini-kpi-item">
                            <span class="pa-mini-kpi-lbl">Skor Tertinggi</span>
                            <strong class="pa-mini-kpi-val text-success">{{ formatSkor(historyTerbaik) }}</strong>
                            <small class="pa-mini-kpi-sub">{{ bestHistoryCycleTitle }}</small>
                        </div>
                        <div class="pa-mini-kpi-divider" />
                        <div class="pa-mini-kpi-item">
                            <span class="pa-mini-kpi-lbl">Tren Terakhir</span>
                            <strong
                                class="pa-mini-kpi-val"
                                :class="historyDeltaDir === 'up' ? 'text-success' : (historyDeltaDir === 'down' ? 'text-danger' : 'text-slate')"
                            >
                                <HcisIcon
                                    v-if="historyDeltaDir === 'up'"
                                    name="arrow-up-right"
                                    size="2xs"
                                />
                                <HcisIcon
                                    v-else-if="historyDeltaDir === 'down'"
                                    name="arrow-down-right"
                                    size="2xs"
                                />
                                {{ historyDeltaText }}
                            </strong>
                            <small class="pa-mini-kpi-sub">{{ historyDeltaDir === 'up' ? 'Meningkat' : (historyDeltaDir === 'down' ? 'Menurun' : 'Stabil') }} vs lalu</small>
                        </div>
                        <div class="pa-mini-kpi-divider" />
                        <div class="pa-mini-kpi-item">
                            <span class="pa-mini-kpi-lbl">Total Siklus</span>
                            <strong class="pa-mini-kpi-val text-purple">{{ historyPeriods.length }} Periode</strong>
                            <small class="pa-mini-kpi-sub">Terarsip resmi</small>
                        </div>
                    </div>

                    <!-- Mini Trajectory Cycle Rows -->
                    <div class="pa-mini-history-table">
                        <div class="pa-mini-table-head">
                            <span class="col-cycle">Cycle &amp; Periode</span>
                            <span class="col-role">Jabatan &amp; Level</span>
                            <span class="col-kpi">Capaian KPI</span>
                            <span class="col-comp">Kompetensi 360°</span>
                            <span class="col-final">Skor Akhir</span>
                            <span class="col-grade">Grade</span>
                            <span class="col-action">Aksi</span>
                        </div>

                        <div
                            v-for="(p, idx) in recentHistoryList"
                            :key="p.Id_PA_Cycle || idx"
                            class="pa-mini-table-row"
                            :class="{ 'is-latest': idx === 0 }"
                            @click="$emit('switch-tab', 'riwayat')"
                        >
                            <!-- Col 1: Cycle -->
                            <div class="col-cycle">
                                <div class="pa-cycle-tag-group">
                                    <span class="pa-cycle-year-badge">{{ p.Tahun || '—' }}</span>
                                    <div class="pa-cycle-info">
                                        <strong class="pa-cycle-title" :title="p.Nama_Cycle || p.Kode_Cycle">
                                            {{ p.Nama_Cycle || p.Kode_Cycle || 'Cycle PA' }}
                                        </strong>
                                        <small v-if="idx === 0" class="pa-latest-indicator">Cycle Terbaru</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Col 2: Role -->
                            <div class="col-role">
                                <span class="pa-role-name">{{ p.Nama_Jabatan_Saat_Cycle || '—' }}</span>
                                <small v-if="p.Nama_Level_Saat_Cycle" class="pa-level-name">{{ p.Nama_Level_Saat_Cycle }}</small>
                            </div>

                            <!-- Col 3: KPI -->
                            <div class="col-kpi">
                                <div class="pa-score-inline">
                                    <strong class="text-brand">{{ formatSkor(p.Nilai_KPI) }}</strong>
                                    <div class="pa-mini-bar-track">
                                        <div
                                            class="pa-mini-bar-fill is-brand"
                                            :style="{ width: `${Math.min(100, Math.max(0, Number(p.Nilai_KPI) || 0))}%` }"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Col 4: Comp -->
                            <div class="col-comp">
                                <div class="pa-score-inline">
                                    <strong class="text-purple">{{ formatSkor(p.Nilai_Kompetensi) }}</strong>
                                    <div class="pa-mini-bar-track">
                                        <div
                                            class="pa-mini-bar-fill is-purple"
                                            :style="{ width: `${Math.min(100, Math.max(0, Number(p.Nilai_Kompetensi) || 0))}%` }"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Col 5: Final Score -->
                            <div class="col-final">
                                <strong class="pa-final-score-val">{{ formatSkor(p.Final_Score) }}</strong>
                            </div>

                            <!-- Col 6: Grade -->
                            <div class="col-grade">
                                <span
                                    class="pa-grade-mini-badge"
                                    :class="`is-grade-${String(p.Kode_Grade_Final || 'none').toLowerCase().replace('+', '-plus').replace('_plus', '-plus')}`"
                                >
                                    {{ displayGrade(p.Kode_Grade_Final) }}
                                </span>
                            </div>

                            <!-- Col 7: Action -->
                            <div class="col-action">
                                <button
                                    type="button"
                                    class="pa-mini-action-btn"
                                    title="Lihat Detail Riwayat"
                                    @click.stop="$emit('switch-tab', 'riwayat')"
                                >
                                    <span>Detail</span>
                                    <HcisIcon name="chevron-right" size="2xs" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State for History -->
                <div v-else class="pa-mini-history-empty">
                    <div class="pa-mini-empty-icon">
                        <HcisIcon name="clock-history" size="sm" />
                    </div>
                    <div class="pa-mini-empty-text">
                        <strong>Belum Ada Rekam Jejak Multi-Cycle</strong>
                        <p>Riwayat performa lintas periode akan terarsip secara otomatis setelah hasil evaluasi siklus aktif diterbitkan oleh komite evaluasi.</p>
                    </div>
                    <HcisButton
                        label="Buka Tugas Penilaian"
                        variant="glass"
                        size="xs"
                        leading-icon="list-check"
                        @click="$emit('switch-tab', 'assignment')"
                    />
                </div>
            </HcisCard>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisEmptyState,
    HcisIcon,
    HcisProgress,
    HcisResultState,
    HcisSkeleton,
    HcisStatusBadge,
} from '@/components/ui';

defineOptions({ name: 'PaSayaSummaryPanel' });

const props = defineProps({
    data: { type: Object, default: null },
    progress: { type: Object, default: null },
    result: { type: Object, default: null },
    history: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
});

defineEmits(['retry', 'switch-tab']);

/* Server mengirim riwayat dari yang TERLAMA (tren dibaca kiri ke kanan). Ringkasan butuh
   yang terbaru lebih dulu; membaca `periode[0]` sebagai terbaru menampilkan cycle tertua. */
const historyPeriods = computed(() => {
    return Array.isArray(props.history?.periode) ? [...props.history.periode].reverse() : [];
});

const terbaru = computed(() => props.result?.hasil ?? historyPeriods.value[0] ?? null);

const userKpiScore = computed(() => terbaru.value?.Nilai_KPI ?? null);
const userCompScore = computed(() => terbaru.value?.Nilai_Kompetensi ?? null);
const userFinalScore = computed(() => terbaru.value?.Final_Score ?? null);
const userGrade = computed(() => terbaru.value?.Kode_Grade_Final ?? null);
const userGradeName = computed(() => terbaru.value?.Nama_Grade_Final ?? null);

/* Bobot yang SUNGGUH dipakai menghitung hasil itu (per level/siklus), bukan 60/40 tetap. */
const userKpiWeight = computed(() => nilaiAtauNull(terbaru.value?.Bobot_KPI_Terpakai));
const userCompWeight = computed(() => nilaiAtauNull(terbaru.value?.Bobot_Kompetensi_Terpakai));

function nilaiAtauNull(v) {
    if (v === null || v === undefined || v === '') return null;
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
}

function labelAngka(n) {
    return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function labelBobot(n) {
    return n === null ? 'Bobot —' : `Bobot ${labelAngka(n)}%`;
}

function persenBobot(n) {
    const total = (userKpiWeight.value ?? 0) + (userCompWeight.value ?? 0);
    return total > 0 ? Math.min(100, Math.max(0, (n / total) * 100)) : 0;
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

/* Nama grade datang dari server: pita kurva beku (atau pita siklus) tempat grade TERBIT itu
   lahir. Layar tidak mengarang artinya — kurva tiap siklus bisa menamai pitanya berbeda. */
function getGradeLabel(grade, nama) {
    if (!grade) return 'Belum Diterbitkan';
    return nama || `Grade ${displayGrade(grade)}`;
}

const hasHistory = computed(() => {
    return Boolean(
        props.history &&
        (props.history.rata_rata != null ||
            props.history.terbaik != null ||
            props.history.perubahan_terakhir != null ||
            (Array.isArray(props.history.periode) && props.history.periode.length > 0))
    );
});

const historyRataRata = computed(() => {
    return props.history?.rata_rata ?? null;
});

const historyTerbaik = computed(() => {
    return props.history?.terbaik ?? null;
});

const historyDelta = computed(() => {
    return props.history?.perubahan_terakhir ?? null;
});

const historyDeltaDir = computed(() => {
    if (historyDelta.value === null || historyDelta.value === undefined || historyDelta.value === '') return 'none';
    const num = Number(historyDelta.value);
    if (!Number.isFinite(num) || num === 0) return 'neutral';
    return num > 0 ? 'up' : 'down';
});

const historyDeltaText = computed(() => {
    if (historyDelta.value === null || historyDelta.value === undefined || historyDelta.value === '') return 'Tetap';
    const num = Number(historyDelta.value);
    if (!Number.isFinite(num)) return String(historyDelta.value);
    const prefix = num > 0 ? '+' : '';
    return `${prefix}${num.toFixed(2)} pts`;
});

const recentHistoryList = computed(() => {
    return historyPeriods.value.slice(0, 5);
});

const bestHistoryCycleTitle = computed(() => {
    if (!historyPeriods.value.length) return '—';
    const bestScore = Number(props.history?.terbaik);
    const found = historyPeriods.value.find((p) => Number(p.Final_Score) === bestScore);
    return found?.Tahun ? `Tahun ${found.Tahun}` : (found?.Nama_Cycle || 'Cycle Terbaik');
});

const kategoriList = computed(() => {
    return Array.isArray(props.progress?.kategori) ? props.progress.kategori : [];
});

/* Hanya STATUS, dan tiap lembar tepat di satu irisan. Terlambat dan mendekati tenggat adalah
   sifat lembar yang belum diisi, bukan status sendiri — menambahkannya sebagai irisan
   menghitung lembar yang sama dua kali. Keduanya tetap tampil sebagai peringatan di kartu. */
const chartDonutData = computed(() => {
    if (!props.data) return [];
    const items = [];
    const terkirim = angka(props.data.sudah_terkirim);
    const belumDikirim = angka(props.data.belum_dikirim);
    const belum = angka(props.data.belum_dimulai);
    const sedang = angka(props.data.sedang_dikerjakan);

    if (terkirim > 0) items.push({ name: 'Terkirim', value: terkirim, itemStyle: { color: '#10b981' } });
    if (belumDikirim > 0) items.push({ name: 'Terisi, Belum Dikirim', value: belumDikirim, itemStyle: { color: '#f59e0b' } });
    if (sedang > 0) items.push({ name: 'Sedang Dikerjakan', value: sedang, itemStyle: { color: '#8b5cf6' } });
    if (belum > 0) items.push({ name: 'Belum Dimulai', value: belum, itemStyle: { color: '#cbd5e1' } });

    return items.length > 0
        ? items
        : [{ name: 'Tidak Ada Tugas Aktif', value: 1, itemStyle: { color: '#10b981' } }];
});

/* Competency Highlights & Top Strengths / Growth Areas */
const kompetensiList = computed(() => {
    return Array.isArray(props.result?.kompetensi) ? props.result.kompetensi : [];
});

const topStrengths = computed(() => {
    const list = [...kompetensiList.value];
    if (!list.length) return [];
    return list
        .filter((k) => (k.Nilai ?? k.nilai ?? k.score ?? k.Skor) != null)
        .sort((a, b) => {
            const valA = Number(a.Nilai ?? a.nilai ?? a.score ?? a.Skor ?? 0);
            const valB = Number(b.Nilai ?? b.nilai ?? b.score ?? b.Skor ?? 0);
            return valB - valA;
        })
        .slice(0, 2);
});

const growthPriorities = computed(() => {
    const list = [...kompetensiList.value];
    if (list.length <= 2) return [];
    return list
        .filter((k) => (k.Nilai ?? k.nilai ?? k.score ?? k.Skor) != null)
        .sort((a, b) => {
            const valA = Number(a.Nilai ?? a.nilai ?? a.score ?? a.Skor ?? 0);
            const valB = Number(b.Nilai ?? b.nilai ?? b.score ?? b.Skor ?? 0);
            return valA - valB;
        })
        .slice(0, 2);
});

/* Competency Radar Data */
const radarKompetensi = computed(() => {
    const list = kompetensiList.value;
    if (!list.length) return [];
    const tersaring = list.filter((k) => {
        const flag = k.Flag_Radar ?? k.flag_radar ?? 'Y';
        return flag !== 'T' && flag !== 't' && flag !== false && flag !== 0;
    });
    return tersaring.length > 0 ? tersaring : list;
});

const radarCategories = computed(() => {
    if (!radarKompetensi.value.length) return [];
    return radarKompetensi.value.map((k) => ({
        name: k.Nama_Competency || k.Nama_Kompetensi || k.Kode_Competency || k.nama || 'Kompetensi',
    }));
});

const radarData = computed(() => {
    if (!radarKompetensi.value.length) return [];
    return radarKompetensi.value.map((k) => Number(angka(k.Nilai ?? k.nilai ?? k.score ?? k.Skor).toFixed(1)));
});

const radarAmbang = computed(() =>
    radarKompetensi.value.map((k) => {
        const a = k.Nilai_Ambang ?? k.nilai_ambang ?? k.ambang;
        return a === null || a === undefined ? null : Number(a);
    }),
);

const radarChartHeight = computed(() => {
    return typeof window !== 'undefined' && window.innerWidth <= 768 ? 240 : 340;
});

const donutChartHeight = computed(() => {
    return typeof window !== 'undefined' && window.innerWidth <= 768 ? 120 : 155;
});

const radarSeries = computed(() => {
    if (!radarData.value.length) return [];

    const series = [
        {
            name: 'Skor Kompetensi Saya',
            value: radarData.value,
            data: radarData.value,
        },
    ];

    if (radarAmbang.value.some((v) => v !== null && Number.isFinite(v))) {
        series.push({
            name: 'Ambang (KKM)',
            value: radarAmbang.value,
            data: radarAmbang.value,
        });
    }

    return series;
});

const radarDeskripsi = computed(() =>
    radarSeries.value.length > 1
        ? 'Jaring luar adalah capaian Anda, jaring pembanding adalah ambang (KKM) yang ditetapkan untuk tiap kompetensi'
        : 'Distribusi penguasaan kompetensi personal Anda pada dimensi Manajerial, Core Values, dan Perilaku Kerja',
);

const SEBAB = {
    CLOSED: 'cycle sudah ditutup',
    LOCKED: 'cycle belum dibuka',
    PREPARED: 'cycle masih disiapkan',
    DRAFT: 'cycle masih draf',
};

const diLuarJendela = computed(() => {
    const d = props.data?.di_luar_jendela;

    if (!d || !d.total) {
        return null;
    }

    const bagian = Object.entries(d.per_status || {})
        .filter(([, n]) => n > 0)
        .map(([status, n]) => `${angka(n)} karena ${SEBAB[status] || `cyclenya berstatus ${status}`}`);

    return `${angka(d.total)} tugas lain tidak ditampilkan di sini — ${bagian.join(', ')}.`;
});

function formatCategoryLabel(name, code) {
    const c = String(code || '').toUpperCase();
    if (c.includes('PEER') || c.includes('REKAN') || String(name).includes('Rekan Sejawat')) {
        return 'Penilaian Peer';
    }
    if (c.includes('SELF') || c.includes('DIRI')) return 'Penilaian Diri';
    if (c.includes('ATASAN') || c.includes('SUPERIOR')) return 'Penilaian Atasan';
    if (c.includes('BAWAHAN') || c.includes('SUBORDINATE')) return 'Penilaian Bawahan';
    return name || 'Kategori Penilaian';
}

function getCategoryIcon(code) {
    const c = String(code || '').toUpperCase();
    if (c.includes('ATASAN') || c.includes('SUPERIOR')) return 'person-workspace';
    if (c.includes('PEER') || c.includes('REKAN')) return 'people-fill';
    if (c.includes('BAWAHAN') || c.includes('SUBORDINATE')) return 'person-check';
    if (c.includes('SELF') || c.includes('DIRI')) return 'person-badge';
    return 'list-check';
}

function getCategoryColor(code) {
    const c = String(code || '').toUpperCase();
    if (c.includes('ATASAN') || c.includes('SUPERIOR')) return '#4f46e5';
    if (c.includes('PEER') || c.includes('REKAN')) return '#7c3aed';
    if (c.includes('BAWAHAN') || c.includes('SUBORDINATE')) return '#0d9488';
    if (c.includes('SELF') || c.includes('DIRI')) return '#0284c7';
    return '#6366f1';
}

function formatSkor(val) {
    if (val === null || val === undefined || val === '') return '—';
    const num = Number(val);
    return Number.isFinite(num) ? num.toFixed(2) : String(val);
}

function angka(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}
</script>

<style scoped>
.pa-saya-summary-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    min-width: 0;
    max-width: 100%;
}

.pa-summary-empty-card {
    padding: 3rem 1.5rem;
}

.pa-summary-empty-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}

.pa-summary-loading-grid {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.pa-summary-loading-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.pa-summary-loading-row-4 {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

.pa-summary-dashboard {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* 1. Primary KPI Highlight Grid (3 Bento Cards) */
.pa-summary-kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

.pa-summary-kpi-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 1.15rem 1.25rem;
    min-height: 11.25rem;
    border-radius: var(--hcis-radius-xl, 16px);
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.02);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.pa-summary-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
    background: rgba(255, 255, 255, 0.96);
}

.pa-summary-kpi-card.is-result:hover { border-color: rgba(16, 185, 129, 0.45); }

.pa-summary-kpi-card.is-composition:hover { border-color: rgba(79, 70, 229, 0.45); }
.pa-summary-kpi-card.is-workload:hover { border-color: rgba(13, 148, 136, 0.45); }

.pa-summary-kpi-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    height: 1.85rem;
}

.pa-summary-kpi-card__badge-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-summary-kpi-card__icon {
    display: grid;
    place-items: center;
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 7px;
    flex-shrink: 0;
}

.pa-summary-kpi-card__icon.is-success {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-summary-kpi-card__icon.is-amber {
    background: rgba(217, 119, 6, 0.12);
    color: #d97706;
}

.pa-summary-kpi-card__icon.is-brand {
    background: rgba(79, 70, 229, 0.12);
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-summary-kpi-card__icon.is-teal {
    background: rgba(13, 148, 136, 0.12);
    color: #0d9488;
}

.pa-summary-kpi-card__kicker {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
}

.pa-summary-kpi-card__arrow {
    display: grid;
    place-items: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 6px;
    color: var(--hcis-text-muted, #94a3b8);
    transition: all 0.2s ease;
}

.pa-summary-kpi-card:hover .pa-summary-kpi-card__arrow {
    transform: translate(2px, -2px);
    color: var(--hcis-text-strong, #0f172a);
    background: rgba(241, 245, 249, 0.8);
}

.pa-summary-kpi-card__body {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.45rem;
    min-height: 4.85rem;
    padding: 0.35rem 0;
}

.pa-summary-kpi-card__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.65rem;
    border-top: 1px solid rgba(226, 232, 240, 0.85);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-brand-600, #4f46e5);
    height: 1.75rem;
    transition: color 0.15s ease;
}

.pa-summary-kpi-card:hover .pa-summary-kpi-card__foot {
    color: var(--hcis-brand-700, #4338ca);
}

/* Card 1 Details: Result */
.pa-result-hero-pill {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-result-grade-medallion {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
    font-size: 1.125rem;
    font-weight: 700;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
    flex-shrink: 0;
}

.pa-result-grade-medallion.is-grade-none {
    background: #cbd5e1;
    color: #475569;
    box-shadow: none;
}

.pa-result-grade-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-result-score-val {
    font-size: 1.375rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-result-predicate {
    font-size: 0.75rem;
    font-weight: 600;
    color: #059669;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-trend-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.pa-trend-badge.is-up { color: #059669; }
.pa-trend-badge.is-down { color: #dc2626; }
.pa-trend-badge.is-neutral { color: #64748b; }

.pa-text-subtle {
    font-size: 0.75rem;
    color: #64748b;
}

/* Card 3 Details: Composition */
.pa-composition-dual-row {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 0.5rem;
    align-items: center;
}

.pa-comp-col {
    display: flex;
    flex-direction: column;
    gap: 0.08rem;
}

.pa-comp-lbl {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #64748b);
}

.pa-comp-val {
    font-size: 1.125rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}

.pa-comp-sub {
    font-size: 0.6875rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-comp-divider {
    width: 1px;
    height: 2rem;
    background: rgba(226, 232, 240, 0.9);
}

.pa-comp-balance-bar {
    display: flex;
    width: 100%;
    height: 6px;
    border-radius: 999px;
    overflow: hidden;
    background: #e2e8f0;
    gap: 2px;
}

.pa-comp-balance-fill.is-kpi {
    background: var(--hcis-brand-600, #4f46e5);
}

.pa-comp-balance-fill.is-comp {
    background: #7c3aed;
}

/* Card 4 Details: Workload */
.pa-workload-stat-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-workload-rate strong {
    font-size: 1.375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-workload-rate span {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #64748b);
    margin-left: 0.25rem;
}

.pa-workload-counts {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.pa-workload-counts strong {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-workload-counts span {
    font-size: 0.6875rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #64748b);
}

.pa-workload-progress-bar {
    width: 100%;
    height: 6px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.8);
    overflow: hidden;
    margin-top: 0.35rem;
}

.pa-workload-progress-fill {
    height: 100%;
    border-radius: 999px;
    background: #0d9488;
    transition: width 350ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-workload-luar-jendela {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin: 0.5rem 0 0;
    color: var(--hcis-text-muted, #475569);
    font-size: 0.75rem;
    line-height: 1.45;
}

.pa-workload-status-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem 0.75rem;
}

.pa-workload-alert {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.pa-workload-alert.is-success { color: #059669; }
.pa-workload-alert.is-warning { color: #b45309; }
.pa-workload-alert.is-danger { color: #dc2626; }

/* 3. Middle Grid (Rater Matrix & Competency Insights) */
.pa-summary-middle-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.25rem;
    align-items: stretch;
}

.pa-summary-rater-card,
.pa-summary-insights-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-rater-compact-wrap {
    display: grid;
    grid-template-columns: minmax(140px, 180px) 1fr;
    gap: 1.25rem;
    align-items: center;
    height: 100%;
}

.pa-rater-donut-side {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    width: 100%;
    min-width: 140px;
    position: relative;
}

.pa-rater-donut-side :deep(.hcis-chart-wrapper),
.pa-rater-donut-side :deep(.hcis-chart__canvas-container) {
    width: 100%;
}

.pa-rater-donut-caption {
    display: flex;
    justify-content: center;
    width: 100%;
}

/* Category Compact List */
.pa-category-compact-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    justify-content: center;
}

.pa-category-compact-item {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0.55rem 0.75rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-category-compact-item:hover {
    background: rgba(255, 255, 255, 0.96);
    border-color: rgba(124, 58, 237, 0.35);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.08);
}

.pa-category-compact-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.pa-category-compact-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.pa-category-compact-icon {
    display: grid;
    place-items: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 6px;
    flex-shrink: 0;
}

.pa-category-compact-name {
    font-size: 0.8125rem;
    color: var(--hcis-text-strong, #0f172a);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-category-compact-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}

.pa-category-compact-count {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    font-variant-numeric: tabular-nums;
}

.pa-category-compact-track {
    width: 100%;
    height: 4px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.8);
    overflow: hidden;
}

.pa-category-compact-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 350ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* Compact Guidelines Fallback */
.pa-summary-guide-compact-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    justify-content: center;
}

.pa-guide-compact-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.65rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-guide-compact-icon {
    display: grid;
    place-items: center;
    width: 1.6rem;
    height: 1.6rem;
    border-radius: 7px;
    flex-shrink: 0;
}

.pa-guide-compact-icon.is-brand {
    background: rgba(79, 70, 229, 0.12);
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-guide-compact-icon.is-purple {
    background: rgba(124, 58, 237, 0.12);
    color: #7c3aed;
}

.pa-guide-compact-icon.is-teal {
    background: rgba(13, 148, 136, 0.12);
    color: #0d9488;
}

.pa-guide-compact-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-guide-compact-text strong {
    font-size: 0.8125rem;
    color: var(--hcis-text-strong, #0f172a);
    font-weight: 600;
}

.pa-guide-compact-text span {
    font-size: 0.75rem;
    line-height: 1.4;
    color: var(--hcis-text-muted, #64748b);
}

/* Competency Insights & Strengths/Growth */
.pa-insights-body {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.85rem;
    height: 100%;
}

.pa-insights-dual-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.pa-insights-box {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.85rem 0.95rem;
    min-height: 7.25rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-insights-box__head {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-insights-box__head strong {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    flex: 1;
}

.pa-insights-box__icon {
    display: grid;
    place-items: center;
    width: 1.45rem;
    height: 1.45rem;
    border-radius: 6px;
    flex-shrink: 0;
}

.pa-insights-box__icon.is-success {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-insights-box__icon.is-amber {
    background: rgba(217, 119, 6, 0.12);
    color: #d97706;
}

.pa-insights-box__tag {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-text-muted, #64748b);
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    background: rgba(241, 245, 249, 0.9);
}

.pa-insights-items-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-insights-item {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-insights-item__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.pa-insights-item__name {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #334155);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-insights-item__val {
    font-size: 0.75rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pa-insights-item__bar-wrap {
    width: 100%;
    height: 4px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-insights-item__bar {
    height: 100%;
    border-radius: 999px;
}

.pa-insights-item__bar.is-success { background: #10b981; }
.pa-insights-item__bar.is-amber { background: #f59e0b; }

.pa-insights-empty-sub {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #94a3b8);
    font-style: italic;
    line-height: 1.4;
    padding: 0.4rem 0;
}

/* History Performance Strip */
.pa-personal-stats-footer {
    padding: 0.6rem 0.85rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-personal-stats-history-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
    align-items: center;
}

.pa-history-stat-mini {
    display: flex;
    flex-direction: column;
    gap: 0.08rem;
}

.pa-history-mini-lbl {
    font-size: 0.6875rem;
    font-weight: 500;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
}

.pa-history-mini-val {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.875rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.text-success { color: #059669; }
.text-brand { color: #4f46e5; }
.text-purple { color: #7c3aed; }
.text-amber { color: #d97706; }
.text-teal { color: #0d9488; }
.text-slate { color: #64748b; }
.text-danger { color: #dc2626; }

/* 4. Radar Section */
.pa-summary-radar-section {
    width: 100%;
}

/* 5. Trajectory & Multi-Cycle History Section */
.pa-summary-trajectory-card {
    width: 100%;
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-mini-history-wrap {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.pa-mini-history-kpis {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    align-items: center;
    padding: 1rem 1.25rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    gap: 1rem;
}

.pa-mini-kpi-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-mini-kpi-lbl {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
}

.pa-mini-kpi-val {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.2;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-variant-numeric: tabular-nums;
}

.pa-mini-kpi-sub {
    font-size: 0.75rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-mini-kpi-divider {
    display: none;
}

/* Mini Trajectory Table */
.pa-mini-history-table {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

.pa-mini-table-head {
    display: grid;
    grid-template-columns: 2.2fr 1.8fr 1.4fr 1.4fr 1fr 0.8fr 0.8fr;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-mini-table-row {
    display: grid;
    grid-template-columns: 2.2fr 1.8fr 1.4fr 1.4fr 1fr 0.8fr 0.8fr;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(226, 232, 240, 0.85);
    transition: all 150ms cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.pa-mini-table-row:hover {
    background: rgba(255, 255, 255, 0.98);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    border-color: rgba(79, 70, 229, 0.35);
}

.pa-mini-table-row.is-latest {
    background: rgba(240, 253, 250, 0.75);
    border-color: rgba(13, 148, 136, 0.3);
}

.pa-mini-table-row.is-latest:hover {
    background: rgba(240, 253, 250, 0.95);
    border-color: rgba(13, 148, 136, 0.5);
}

.pa-cycle-tag-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.pa-cycle-year-badge {
    display: grid;
    place-items: center;
    padding: 0.2rem 0.45rem;
    border-radius: 6px;
    background: rgba(79, 70, 229, 0.1);
    color: var(--hcis-brand-700, #4338ca);
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
}

.pa-cycle-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-cycle-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-latest-indicator {
    font-size: 0.6875rem;
    font-weight: 600;
    color: #0d9488;
}

.col-role {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-role-name {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-level-name {
    font-size: 0.6875rem;
    color: #64748b;
}

.pa-score-inline {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-score-inline strong {
    font-size: 0.8125rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pa-mini-bar-track {
    width: 100%;
    height: 4px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.8);
    overflow: hidden;
}

.pa-mini-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}

.pa-mini-bar-fill.is-brand {
    background: linear-gradient(90deg, #6366f1, #4f46e5);
}

.pa-mini-bar-fill.is-purple {
    background: linear-gradient(90deg, #a855f7, #7c3aed);
}

.pa-final-score-val {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-grade-mini-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.1rem;
    height: 1.6rem;
    padding: 0 0.45rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1;
    background: #e2e8f0;
    color: #334155;
}

.pa-grade-mini-badge.is-grade-a-plus,
.pa-grade-mini-badge.is-grade-a {
    background: rgba(16, 185, 129, 0.15);
    color: #047857;
    border: 1px solid rgba(16, 185, 129, 0.35);
}

.pa-grade-mini-badge.is-grade-b-plus,
.pa-grade-mini-badge.is-grade-b {
    background: rgba(79, 70, 229, 0.12);
    color: #4338ca;
    border: 1px solid rgba(79, 70, 229, 0.3);
}

.pa-grade-mini-badge.is-grade-b-minus {
    background: rgba(14, 165, 233, 0.12);
    color: #0369a1;
    border: 1px solid rgba(14, 165, 233, 0.3);
}

.pa-grade-mini-badge.is-grade-c,
.pa-grade-mini-badge.is-grade-c-plus {
    background: rgba(245, 158, 11, 0.15);
    color: #b45309;
    border: 1px solid rgba(245, 158, 11, 0.35);
}

.pa-grade-mini-badge.is-grade-d,
.pa-grade-mini-badge.is-grade-e {
    background: rgba(239, 68, 68, 0.12);
    color: #b91c1c;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.pa-mini-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.55rem;
    border-radius: 6px;
    border: 1px solid rgba(203, 213, 225, 0.8);
    background: rgba(255, 255, 255, 0.9);
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-brand-600, #4f46e5);
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-mini-action-btn:hover {
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    border-color: var(--hcis-brand-600, #4f46e5);
}

/* Empty Mini History */
.pa-mini-history-empty {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.6);
    border: 1px dashed rgba(203, 213, 225, 0.9);
}

.pa-mini-empty-icon {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 10px;
    background: rgba(13, 148, 136, 0.12);
    color: #0d9488;
    flex-shrink: 0;
}

.pa-mini-empty-text {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    min-width: 0;
}

.pa-mini-empty-text strong {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-mini-empty-text p {
    margin: 0;
    font-size: 0.75rem;
    line-height: 1.45;
    color: var(--hcis-text-muted, #64748b);
}

@media (max-width: 1100px) {
    .pa-summary-middle-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1024px) {
    .pa-mini-history-kpis {
        grid-template-columns: repeat(2, 1fr);
    }

    .pa-mini-table-head {
        display: none;
    }

    .pa-mini-table-row {
        grid-template-columns: 1fr 1fr;
        gap: 0.65rem;
    }

    .col-cycle {
        grid-column: 1 / -1;
    }

    .col-action {
        grid-column: 1 / -1;
        justify-content: flex-end;
    }
}

@media (max-width: 900px) {
    .pa-summary-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .pa-summary-dashboard {
        gap: 0.65rem;
    }

    /* 1. Primary Bento KPI Grid */
    .pa-summary-kpi-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
    }

    .pa-summary-kpi-card {
        min-height: auto !important;
        padding: 0.65rem 0.75rem !important;
        border-radius: var(--hcis-radius-md, 10px) !important;
        justify-content: flex-start !important;
        gap: 0.35rem !important;
        background: rgba(255, 255, 255, 0.95) !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05) !important;
    }

    /* Card 1 (Hasil) spans 2 columns as a compact horizontal banner */
    .pa-summary-kpi-card.is-result {
        grid-column: 1 / -1;
    }

    .pa-summary-kpi-card.is-result .pa-summary-kpi-card__body {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .pa-summary-kpi-card__head {
        height: auto !important;
        min-height: 1.25rem !important;
    }

    .pa-summary-kpi-card__badge-wrap {
        gap: 0.35rem !important;
    }

    .pa-summary-kpi-card__icon {
        width: 1.35rem !important;
        height: 1.35rem !important;
        font-size: 0.7rem !important;
        border-radius: 5px !important;
    }

    .pa-summary-kpi-card__kicker {
        font-size: 0.625rem !important;
        letter-spacing: 0.03em !important;
        font-weight: 750 !important;
    }

    .pa-summary-kpi-card__arrow {
        display: none !important;
    }

    .pa-summary-kpi-card__body {
        min-height: auto !important;
        padding: 0 !important;
        gap: 0.25rem !important;
    }

    /* Subtitles, footers, redundant text hidden on mobile for clean & compact UI */
    .pa-summary-kpi-card__foot,
    .pa-comp-sub,
    .pa-mini-kpi-sub,
    .pa-text-subtle {
        display: none !important;
    }

    /* Card 1 Details: Result */
    .pa-result-hero-pill {
        gap: 0.5rem;
        align-items: center;
    }

    .pa-result-grade-medallion {
        width: 2.15rem !important;
        height: 2.15rem !important;
        font-size: 0.95rem !important;
        border-radius: 7px !important;
        flex-shrink: 0;
    }

    .pa-result-score-val {
        font-size: 1.15rem !important;
        font-weight: 750;
        line-height: 1.1;
    }

    .pa-result-predicate {
        font-size: 0.7rem !important;
        line-height: 1.1;
    }

    .pa-summary-kpi-card__sub {
        display: flex !important;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.65rem !important;
        margin-top: 0 !important;
    }

    .pa-trend-badge {
        font-size: 0.625rem !important;
        padding: 0.1rem 0.35rem !important;
        border-radius: 4px !important;
    }

    /* Card 2 Details: Composition */
    .pa-composition-dual-row {
        gap: 0.25rem;
        align-items: center;
    }

    .pa-comp-col {
        min-width: 0;
        flex: 1 1 0;
    }

    .pa-comp-lbl {
        font-size: 0.625rem !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #64748b;
        margin-bottom: 0.1rem;
    }

    .pa-comp-val {
        font-size: 0.95rem !important;
        font-weight: 750;
        line-height: 1.1;
    }

    .pa-comp-divider {
        height: 1.5rem !important;
        margin: 0 0.15rem !important;
    }

    .pa-comp-balance-bar {
        height: 4px !important;
        margin-top: 0.25rem !important;
        border-radius: 2px !important;
    }

    /* Card 3 Details: Workload */
    .pa-workload-stat-row {
        gap: 0.25rem;
        align-items: baseline;
    }

    .pa-workload-rate {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        min-width: 0;
    }

    .pa-workload-rate strong {
        font-size: 1rem !important;
        font-weight: 750;
        line-height: 1.1;
    }

    .pa-workload-rate span {
        display: block !important;
        font-size: 0.6rem !important;
        color: #64748b !important;
        font-weight: 500;
    }

    .pa-workload-counts {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        min-width: 0;
    }

    .pa-workload-counts strong {
        font-size: 0.85rem !important;
        font-weight: 750;
        line-height: 1.1;
    }

    .pa-workload-counts span {
        display: block !important;
        font-size: 0.6rem !important;
        color: #64748b !important;
        font-weight: 500;
    }

    .pa-workload-progress-bar {
        height: 4px !important;
        margin-top: 0.25rem !important;
        border-radius: 2px !important;
    }

    .pa-workload-status-bar {
        display: flex !important;
        margin-top: 0.25rem !important;
    }

    .pa-workload-alert {
        font-size: 0.625rem !important;
        gap: 0.25rem !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pa-workload-alert.is-success {
        display: none !important;
    }

    /* Hide long descriptions on all cards */
    :deep(.hcis-card__description),
    :deep(.hcis-card p.hcis-card__description) {
        display: none !important;
    }

    :deep(.hcis-card__header) {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
        padding: 0.65rem 0.75rem !important;
    }

    :deep(.hcis-card__body) {
        padding: 0.65rem 0.75rem !important;
    }

    :deep(.hcis-card__header-main) {
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
    }

    :deep(.hcis-card__icon-box) {
        width: 1.85rem !important;
        height: 1.85rem !important;
        min-width: 1.85rem !important;
        min-height: 1.85rem !important;
        font-size: 0.85rem !important;
        border-radius: 7px !important;
    }

    :deep(.hcis-card__title) {
        font-size: 0.84rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        line-height: 1.25 !important;
    }

    /* 2. Middle Grid: Rater Progress & Competency Highlights */
    .pa-summary-middle-grid {
        gap: 0.65rem !important;
    }

    .pa-summary-rater-card,
    .pa-summary-insights-card {
        height: auto !important;
    }

    .pa-rater-compact-wrap {
        grid-template-columns: 1fr !important;
        gap: 0.5rem !important;
        height: auto !important;
    }

    .pa-rater-donut-side {
        max-width: 155px !important;
        margin: 0 auto !important;
        gap: 0.2rem !important;
    }

    /* Fallback guidance principles hidden on mobile per user instruction */
    .pa-summary-guide-compact-list {
        display: none !important;
    }

    .pa-category-compact-list {
        gap: 0.35rem !important;
    }

    .pa-category-compact-item {
        padding: 0.45rem 0.6rem !important;
        gap: 0.2rem !important;
        border-radius: 8px !important;
    }

    .pa-category-compact-icon {
        width: 1.35rem !important;
        height: 1.35rem !important;
    }

    .pa-category-compact-name {
        font-size: 0.78rem !important;
    }

    .pa-category-compact-count {
        font-size: 0.72rem !important;
    }

    .pa-category-compact-track {
        height: 3.5px !important;
    }

    /* Competency Highlights */
    .pa-insights-dual-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .pa-insights-box {
        min-height: auto !important;
        padding: 0.55rem 0.65rem !important;
        border-radius: var(--hcis-radius-md, 10px) !important;
        gap: 0.3rem !important;
    }

    .pa-insights-box__head {
        gap: 0.35rem !important;
    }

    .pa-insights-box__head strong {
        font-size: 0.76rem !important;
    }

    .pa-insights-box__icon {
        width: 1.25rem !important;
        height: 1.25rem !important;
        font-size: 0.7rem !important;
    }

    .pa-insights-box__tag {
        display: none !important;
    }

    .pa-insights-items-list {
        gap: 0.35rem !important;
    }

    .pa-insights-item__name {
        font-size: 0.72rem !important;
    }

    .pa-insights-item__val {
        font-size: 0.72rem !important;
    }

    .pa-insights-item__bar-wrap {
        height: 3.5px !important;
    }

    /* Historical Stats Footer Strip */
    .pa-personal-stats-footer {
        padding: 0.45rem 0.6rem !important;
        border-radius: 8px !important;
        margin-top: 0.25rem !important;
    }

    .pa-personal-stats-history-row {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        text-align: center;
        gap: 0.25rem;
    }

    .pa-history-stat-mini {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.1rem;
    }

    .pa-history-mini-lbl {
        font-size: 0.625rem !important;
    }

    .pa-history-mini-val {
        font-size: 0.875rem !important;
    }

    /* 3. Radar Chart Section */
    .pa-summary-radar-section {
        margin-bottom: 0.65rem !important;
    }

    /* 4. Multi-Cycle Performance Trajectory */
    .pa-summary-trajectory-card {
        margin-bottom: 0.65rem !important;
    }

    .pa-mini-history-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.35rem !important;
        margin-bottom: 0.5rem !important;
    }

    .pa-mini-kpi-divider {
        display: none;
    }

    .pa-mini-kpi-item {
        padding: 0.35rem 0.45rem !important;
        background: rgba(248, 250, 252, 0.85);
        border-radius: 6px !important;
        border: 1px solid rgba(226, 232, 240, 0.6);
        text-align: center;
    }

    .pa-mini-kpi-lbl {
        font-size: 0.625rem !important;
    }

    .pa-mini-kpi-val {
        font-size: 0.95rem !important;
    }

    .pa-mini-history-empty {
        flex-direction: column;
        text-align: center;
        padding: 1.25rem 0.85rem;
    }

    .pa-mini-table-head {
        display: none;
    }

    .pa-mini-table-row {
        display: grid !important;
        grid-template-columns: 1fr auto !important;
        grid-template-areas:
            "cycle grade"
            "role role"
            "kpi comp"
            "final action" !important;
        gap: 0.35rem !important;
        padding: 0.65rem 0.75rem !important;
        border-radius: var(--hcis-radius-md, 10px) !important;
    }

    .pa-cycle-tag-group {
        gap: 0.35rem !important;
    }

    .pa-cycle-year-badge {
        font-size: 0.625rem !important;
        padding: 0.1rem 0.35rem !important;
    }

    .pa-cycle-title {
        font-size: 0.8125rem !important;
    }

    .pa-latest-indicator {
        font-size: 0.6rem !important;
    }

    .col-cycle {
        grid-area: cycle !important;
        min-width: 0;
    }

    .col-grade {
        grid-area: grade !important;
        justify-self: end;
    }

    .pa-grade-mini-badge {
        font-size: 0.6875rem !important;
        padding: 0.1rem 0.4rem !important;
    }

    .col-role {
        grid-area: role !important;
        min-width: 0;
    }

    .pa-role-name {
        font-size: 0.75rem !important;
    }

    .pa-level-name {
        font-size: 0.68rem !important;
    }

    .col-kpi {
        grid-area: kpi !important;
        padding: 0.3rem 0.45rem !important;
        background: rgba(248, 250, 252, 0.85) !important;
        border-radius: 6px !important;
        border: 1px solid rgba(226, 232, 240, 0.7) !important;
    }

    .col-kpi::before {
        content: "KPI";
        display: block;
        font-size: 0.6rem;
        font-weight: 750;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 0.15rem;
    }

    .col-comp {
        grid-area: comp !important;
        padding: 0.3rem 0.45rem !important;
        background: rgba(248, 250, 252, 0.85) !important;
        border-radius: 6px !important;
        border: 1px solid rgba(226, 232, 240, 0.7) !important;
    }

    .col-comp::before {
        content: "Kompetensi 360°";
        display: block;
        font-size: 0.6rem;
        font-weight: 750;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 0.15rem;
    }

    .col-final {
        grid-area: final !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.25rem !important;
    }

    .col-final::before {
        content: "Skor:";
        font-size: 0.72rem;
        color: var(--hcis-text-muted, #64748b);
        font-weight: 600;
    }

    .pa-final-score-val {
        font-size: 0.95rem !important;
    }

    .col-action {
        grid-area: action !important;
        justify-self: end !important;
    }

    .pa-mini-action-btn {
        font-size: 0.6875rem !important;
        padding: 0.2rem 0.45rem !important;
    }

    .pa-summary-loading-row,
    .pa-summary-loading-row-4 {
        grid-template-columns: 1fr;
    }
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
</style>

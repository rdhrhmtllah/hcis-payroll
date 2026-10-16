<template>
    <section class="pa-saya-result-panel" aria-label="Hasil penilaian saya">
        <HcisResultState
            v-if="error"
            :status="errorStatus === 403 ? 'warning' : 'danger'"
            variant="glass"
            layout="compact"
            :title="errorStatus === 403 ? 'Hasil tidak tersedia' : 'Hasil gagal dimuat'"
            :description="error"
            :error-code="errorCode"
            :request-id="requestId"
            primary-text="Coba lagi"
            primary-icon="arrow-clockwise"
            @primary-click="$emit('retry')"
        />

        <HcisCard
            v-else-if="loading && !data"
            title="Hasil Penilaian Kinerja"
            description="Memuat hasil evaluasi kinerja yang sudah diterbitkan."
            icon="award"
            sentiment="success"
            variant="glass"
            loading
        />

        <HcisEmptyState
            v-else-if="!data"
            variant="card"
            sentiment="neutral"
            size="md"
            icon="award"
            title="Belum Ada Hasil Penilaian"
            description="Hasil evaluasi kinerja akan tampil setelah diterbitkan secara resmi oleh administrator cycle."
        >
            <template #actions>
                <HcisButton
                    label="Segarkan Data"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-clockwise"
                    :loading="loading"
                    @click="$emit('retry')"
                />
            </template>
        </HcisEmptyState>

        <template v-else-if="!data.tersedia">
            <HcisEmptyState
                variant="card"
                sentiment="info"
                size="md"
                icon="hourglass-split"
                title="Hasil Belum Diterbitkan"
                :description="data.pesan || 'Hasil evaluasi kinerja Anda untuk cycle ini belum diterbitkan oleh panitia/komite evaluasi. Hasil akhir beserta rincian capaian nilai akan muncul di sini setelah proses kalibrasi rampung.'"
            >
                <template #actions>
                    <HcisButton
                        label="Segarkan Status"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="loading"
                        @click="$emit('retry')"
                    />
                </template>
            </HcisEmptyState>
            <p v-if="loading" class="pa-saya-result__syncing" aria-live="polite">Memperbarui status hasil…</p>
        </template>

        <template v-else>
            <div v-if="loading" class="pa-saya-result__syncing" aria-live="polite">Menyinkronkan hasil terbaru…</div>

            <!-- 1. MULTI-CYCLE SELECTOR (If User Has Multiple Official Published Cycles) -->
            <div v-if="cycleOptions.length > 1" class="pa-result-cycle-selector-card">
                <div class="pa-result-cycle-selector-header">
                    <div class="pa-result-cycle-selector-title-group">
                        <div class="pa-result-cycle-selector-icon-box">
                            <HcisIcon name="clock-history" size="sm" />
                        </div>
                        <div class="pa-result-cycle-selector-title-copy">
                            <h3 class="pa-result-cycle-selector-title">Pilih Periode Hasil Penilaian</h3>
                            <p class="pa-result-cycle-selector-subtitle">
                                Beralih antar cycle evaluasi kinerja resmi yang telah diterbitkan
                            </p>
                        </div>
                    </div>
                    <span class="pa-result-cycle-selector-hint">
                        {{ cycleOptions.length }} periode tersimpan
                    </span>
                </div>

                <!-- Smooth Horizontal Scroll Ribbon Track -->
                <div class="pa-result-cycle-track" role="tablist" aria-label="Daftar periode penilaian">
                    <button
                        v-for="c in cycleOptions"
                        :key="c.id"
                        type="button"
                        role="tab"
                        :aria-selected="isCycleActive(c)"
                        class="pa-result-cycle-pill"
                        :class="{ 'is-active': isCycleActive(c) }"
                        @click="$emit('cycle-change', c.id)"
                    >
                        <div class="pa-result-cycle-pill__top">
                            <span class="pa-result-cycle-pill__indicator" aria-hidden="true">
                                <HcisIcon v-if="isCycleActive(c)" name="check2" size="2xs" />
                            </span>
                            <span class="pa-result-cycle-pill__year">{{ c.tahun }}</span>
                            <HcisBadge
                                v-if="c.grade"
                                :label="`Grade ${displayGrade(c.grade)}`"
                                :variant="getGradeSentiment(c.grade)"
                                appearance="subtle"
                                size="2xs"
                                class="pa-result-cycle-pill__badge"
                            />
                        </div>

                        <div class="pa-result-cycle-pill__bottom">
                            <span class="pa-result-cycle-pill__name" :title="c.nama">{{ c.nama }}</span>
                            <span v-if="c.score" class="pa-result-cycle-pill__score">
                                Skor: <strong>{{ formatScore(c.score) }}</strong>
                            </span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- 2. OFFICIAL PUBLISHED HERO CARD -->
            <HcisCard
                class="pa-saya-result__hero"
                title="Hasil Penilaian Kinerja Resmi"
                :description="`${data.hasil.Nama_Cycle || data.hasil.Kode_Cycle || 'Cycle PA'} · Diterbitkan: ${formatTanggalWaktu(data.hasil.Published_At)}`"
                icon="award-fill"
                sentiment="success"
                variant="glass"
                pattern="rings"
            >
                <template #actions>
                    <div class="pa-hero-header-actions">
                        <HcisStatusBadge
                            label="Diterbitkan Resmi"
                            variant="success"
                            dot
                        />
                        <HcisBadge
                            v-if="data.hasil.dikalibrasi === true"
                            label="Dikalibrasi"
                            variant="purple"
                            appearance="glass"
                            size="xs"
                            dot
                        />
                    </div>
                </template>

                <!-- Executive 3-Pillar Showcase Cockpit -->
                <div class="pa-saya-result__hero-cockpit">
                    <!-- Pillar 1: GRADE FINAL SPOTLIGHT (Centerpiece) -->
                    <div class="pa-hero-pillar pa-hero-pillar--grade" :class="gradeThemeClass">
                        <div class="pa-grade-medallion">
                            <div class="pa-grade-medallion__ring">
                                <span class="pa-grade-medallion__letter">{{ displayGrade(data.hasil.Kode_Grade_Final) }}</span>
                            </div>
                            <div class="pa-grade-medallion__meta">
                                <div class="pa-grade-medallion__eyebrow-row">
                                    <span class="pa-eyebrow">GRADE FINAL RESMI</span>
                                    <span v-if="data.hasil.dikalibrasi" class="pa-grade-calibrated-tag" title="Hasil disepakati melalui forum kalibrasi manajemen">
                                        <HcisIcon name="shield-check" size="2xs" />
                                        Forum Kalibrasi
                                    </span>
                                </div>
                                <strong class="pa-grade-medallion__predicate">
                                    {{ gradePredicate(data.hasil.Kode_Grade_Final, data.hasil.Nama_Grade_Final) }}
                                </strong>
                                <small class="pa-grade-medallion__sub">
                                    Peringkat pencapaian kinerja resmi Anda
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
                            <strong class="pa-hero-score-value">{{ formatScore(data.hasil.Final_Score) }}</strong>
                            <span class="pa-hero-score-unit">poin</span>
                        </div>
                        <div class="pa-hero-score-progress">
                            <div
                                class="pa-hero-score-progress__bar"
                                :style="{
                                    width: `${Math.min(100, Math.max(0, Number(data.hasil.Final_Score) || 0))}%`,
                                    background: getScoreGradient(data.hasil.Final_Score)
                                }"
                            />
                        </div>
                        <small class="pa-hero-score-caption">
                            {{ bobotKpi !== null && bobotKompetensi !== null
                                ? `KPI × ${formatScore(bobotKpi)}% + Kompetensi × ${formatScore(bobotKompetensi)}%`
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
                                    <div class="d-flex align-items-center gap-1.5 min-w-0">
                                        <HcisIcon name="graph-up-arrow" size="xs" class="text-slate-600" />
                                        <span class="pa-composition-item__label text-truncate">Kinerja (KPI)</span>
                                    </div>
                                    <div class="d-flex align-items-baseline gap-1">
                                        <strong class="pa-composition-item__score">{{ formatScore(data.hasil?.Nilai_KPI) }}</strong>
                                        <span v-if="bobotKpi !== null" class="pa-composition-item__scale">bobot {{ formatScore(bobotKpi) }}%</span>
                                    </div>
                                </div>
                                <div class="pa-composition-track" aria-hidden="true">
                                    <div
                                        class="pa-composition-bar is-kpi"
                                        :style="{ width: `${Math.min(100, Math.max(0, Number(data.hasil?.Nilai_KPI) || 0))}%` }"
                                    />
                                </div>
                            </div>

                            <!-- Pilar 2: Nilai Kompetensi -->
                            <div class="pa-composition-item">
                                <div class="pa-composition-item__row">
                                    <div class="d-flex align-items-center gap-1.5 min-w-0">
                                        <HcisIcon name="stars" size="xs" class="text-slate-600" />
                                        <span class="pa-composition-item__label text-truncate">Kompetensi 360°</span>
                                    </div>
                                    <div class="d-flex align-items-baseline gap-1">
                                        <strong class="pa-composition-item__score">{{ formatScore(data.hasil?.Nilai_Kompetensi) }}</strong>
                                        <span v-if="bobotKompetensi !== null" class="pa-composition-item__scale">bobot {{ formatScore(bobotKompetensi) }}%</span>
                                    </div>
                                </div>
                                <div class="pa-composition-track" aria-hidden="true">
                                    <div
                                        class="pa-composition-bar is-competency"
                                        :style="{ width: `${Math.min(100, Math.max(0, Number(data.hasil?.Nilai_Kompetensi) || 0))}%` }"
                                    />
                                </div>
                            </div>
                        </div>

                        <small class="pa-composition-caption">
                            Perpaduan target kerja operasional &amp; evaluasi perilaku 360°
                        </small>
                    </div>
                </div>

                <!-- Meta Row: Cycle Info -->
                <div class="pa-saya-result__meta">
                    <div><span>Cycle</span><strong>{{ data.hasil.Nama_Cycle || '—' }}</strong></div>
                    <div><span>Kode Cycle</span><strong>{{ data.hasil.Kode_Cycle || '—' }}</strong></div>
                    <div><span>Tahun</span><strong>{{ data.hasil.Tahun || '—' }}</strong></div>
                    <div><span>Level Jabatan</span><strong>{{ data.hasil.Nama_Level_Saat_Cycle || '—' }}</strong></div>
                </div>
            </HcisCard>

            <!-- 3. THREE EXECUTIVE STAT CARDS -->
            <div class="pa-saya-result__score-grid" aria-label="Rincian nilai">
                <HcisStatCard
                    label="Nilai KPI (Target Kerja)"
                    :value="formatScore(data.hasil.Nilai_KPI)"
                    caption="Pencapaian target operasional kerja"
                    icon="graph-up-arrow"
                    sentiment="brand"
                    pattern="grid"
                />
                <HcisStatCard
                    label="Nilai Kompetensi 360°"
                    :value="formatScore(data.hasil.Nilai_Kompetensi)"
                    caption="Evaluasi perilaku lintas penilai"
                    icon="stars"
                    sentiment="purple"
                    pattern="dots"
                />
                <HcisStatCard
                    label="Grade &amp; Predikat Resmi"
                    :value="`Grade ${displayGrade(data.hasil.Kode_Grade_Final)}`"
                    :caption="gradePredicate(data.hasil.Kode_Grade_Final, data.hasil.Nama_Grade_Final)"
                    icon="award-fill"
                    :sentiment="getGradeSentiment(data.hasil.Kode_Grade_Final)"
                    pattern="rings"
                />
            </div>

            <!-- 4. RADAR KOMPETENSI 360 & KEKUATAN / AREA PENGEMBANGAN -->
            <div class="pa-saya-result__charts mb-3">
                <div
                    v-if="radarKompetensi.length || adaKolomKualitatif"
                    class="pa-result-charts-grid"
                    :class="{ 'is-single-col': !radarKompetensi.length || !adaKolomKualitatif }"
                >
                    <HcisChart
                        v-if="radarKompetensi.length"
                        type="radar"
                        title="Radar Kompetensi 360°"
                        :description="radarSeries.length > 1
                            ? 'Capaian Anda dibandingkan ambang (KKM) tiap kompetensi'
                            : 'Peta keseimbangan profil kompetensi lintas dimensi'"
                        icon="pentagon-fill"
                        sentiment="purple"
                        variant="glass"
                        pattern="rings"
                        :height="radarResultHeight"
                        :categories="radarCategories"
                        :series="radarSeries"
                        :metric-value="`${radarKompetensi.length} Dimensi`"
                        metric-label="Cakupan Penilaian"
                        allow-export
                    />

                    <!-- KOLOM KEKUATAN & AREA PENGEMBANGAN -->
                    <div v-if="adaKolomKualitatif" class="pa-qualitative-column">
                        <!-- Card 1: Kekuatan (Strength) — hanya bila pertanyaannya ada di hasil ini -->
                        <HcisCard
                            v-if="strengthData.ada"
                            :title="strengthData.nama || 'Kekuatan Utama'"
                            :description="getCatatanDescription(strengthData.nama)"
                            :icon="getCatatanIcon(strengthData.nama)"
                            sentiment="teal"
                            variant="glass"
                            pattern="rings"
                            class="pa-qualitative-card"
                            :class="{ 'pa-saya-result__note-group': Boolean(strengthData.kesimpulan) }"
                        >
                            <div v-if="strengthData.kesimpulan" class="pa-qualitative-card__body">
                                <div class="pa-qualitative-card__item">
                                    <span class="pa-qualitative-card__quote-mark" aria-hidden="true">“</span>
                                    <div
                                        class="pa-saya-result__note-line pa-saya-result__note-line--kesimpulan pa-qualitative-card__text"
                                        v-html="htmlAman(strengthData.kesimpulan)"
                                    />
                                </div>
                            </div>
                            <div v-else class="pa-qualitative-card__empty">
                                <div class="pa-qualitative-card__empty-avatar is-strength">
                                    <HcisIcon name="hand-thumbs-up" class="pa-qualitative-card__empty-icon" />
                                </div>
                                <div class="pa-qualitative-card__empty-content">
                                    <span class="pa-qualitative-card__empty-title">Belum Ada Kesimpulan Resmi</span>
                                    <p class="pa-qualitative-card__empty-desc">
                                        Poin keunggulan dan kekuatan utama belum dicatatkan untuk periode evaluasi ini.
                                    </p>
                                </div>
                                <span class="pa-qualitative-card__empty-badge">
                                    <HcisIcon name="dash-circle" size="xs" />
                                    <span>Belum Ditetapkan</span>
                                </span>
                            </div>
                        </HcisCard>

                        <!-- Card 2: Area Pengembangan (Development Area) -->
                        <HcisCard
                            v-if="devAreaData.ada"
                            :title="devAreaData.nama || 'Area Pengembangan'"
                            :description="getCatatanDescription(devAreaData.nama)"
                            :icon="getCatatanIcon(devAreaData.nama)"
                            sentiment="purple"
                            variant="glass"
                            pattern="rings"
                            class="pa-qualitative-card"
                            :class="{ 'pa-saya-result__note-group': Boolean(devAreaData.kesimpulan) }"
                        >
                            <div v-if="devAreaData.kesimpulan" class="pa-qualitative-card__body">
                                <div class="pa-qualitative-card__item">
                                    <span class="pa-qualitative-card__quote-mark" aria-hidden="true">“</span>
                                    <div
                                        class="pa-saya-result__note-line pa-saya-result__note-line--kesimpulan pa-qualitative-card__text"
                                        v-html="htmlAman(devAreaData.kesimpulan)"
                                    />
                                </div>
                            </div>
                            <div v-else class="pa-qualitative-card__empty">
                                <div class="pa-qualitative-card__empty-avatar is-dev">
                                    <HcisIcon name="lightbulb" class="pa-qualitative-card__empty-icon" />
                                </div>
                                <div class="pa-qualitative-card__empty-content">
                                    <span class="pa-qualitative-card__empty-title">Belum Ada Rekomendasi Resmi</span>
                                    <p class="pa-qualitative-card__empty-desc">
                                        Rekomendasi area pengembangan kompetensi belum dicatatkan untuk periode ini.
                                    </p>
                                </div>
                                <span class="pa-qualitative-card__empty-badge">
                                    <HcisIcon name="dash-circle" size="xs" />
                                    <span>Belum Ditetapkan</span>
                                </span>
                            </div>
                        </HcisCard>
                    </div>
                </div>
            </div>

            <!-- 5. CATATAN KUALITATIF LAINNYA (JIKA ADA DIMENSI TAMBAHAN DI LUAR STRENGTH & DEV AREA) -->
            <div v-if="otherCatatanList.length" class="pa-saya-result__qualitative-grid mb-3">
                <HcisCard
                    v-for="grup in otherCatatanList"
                    :key="`catatan-${grup.kode}`"
                    :title="grup.nama"
                    :description="getCatatanDescription(grup.nama)"
                    :icon="getCatatanIcon(grup.nama)"
                    :sentiment="getCatatanSentiment(grup.nama)"
                    variant="glass"
                    pattern="rings"
                    class="pa-saya-result__note-group pa-qualitative-card"
                >
                    <div class="pa-qualitative-card__body">
                        <div class="pa-qualitative-card__item">
                            <span class="pa-qualitative-card__quote-mark" aria-hidden="true">“</span>
                            <div
                                class="pa-saya-result__note-line pa-saya-result__note-line--kesimpulan pa-qualitative-card__text"
                                v-html="htmlAman(grup.kesimpulan)"
                            />
                        </div>
                    </div>
                </HcisCard>
            </div>

            <!-- 6. PROFIL RINCIAN KOMPETENSI (DITARUH DI BAWAH DENGAN PENJELASAN) -->
            <HcisCard
                v-if="data.kompetensi?.length"
                title="Profil Agregat Kompetensi"
                description="Rincian capaian nilai dan definisi standar perilaku untuk masing-masing kompetensi lintas penilai."
                icon="diagram-3-fill"
                sentiment="purple"
                variant="glass"
                pattern="dots"
                class="pa-saya-result__competency-card"
            >
                <div class="pa-competency-grid">
                    <div
                        v-for="item in data.kompetensi"
                        :key="`${item.Kode_Competency}-${item.Kode_Type}`"
                        class="pa-competency-item"
                        role="button"
                        tabindex="0"
                        @click="bukaDetailKompetensi(item)"
                        @keydown.enter.prevent="bukaDetailKompetensi(item)"
                        @keydown.space.prevent="bukaDetailKompetensi(item)"
                    >
                        <div class="pa-competency-item__header">
                            <div class="pa-competency-item__title-group">
                                <span class="pa-competency-item__badge">
                                    {{ item.Nama_Type || item.Kode_Type || 'Kompetensi' }}
                                </span>
                                <h4 class="pa-competency-item__title">
                                    {{ item.Nama_Competency || item.Kode_Competency || '—' }}
                                </h4>
                            </div>

                            <div class="pa-competency-item__score-group">
                                <div class="pa-competency-item__score-display">
                                    <span class="pa-competency-item__score-number">{{ formatScore(item.Nilai) }}</span>
                                    <span class="pa-competency-item__score-scale">/ 100</span>
                                </div>
                                <span
                                    v-if="item.Nilai_Ambang"
                                    class="pa-competency-item__kkm-pill"
                                    :class="{
                                        'is-pass': Number(item.Nilai) >= Number(item.Nilai_Ambang),
                                        'is-under': Number(item.Nilai) < Number(item.Nilai_Ambang)
                                    }"
                                >
                                    Ambang: {{ formatScore(item.Nilai_Ambang) }}
                                </span>
                            </div>
                        </div>

                        <div class="pa-competency-item__progress-track" aria-hidden="true">
                            <div
                                class="pa-competency-item__progress-bar"
                                :style="{
                                    width: `${Math.min(100, Math.max(0, Number(item.Nilai) || 0))}%`,
                                    background: getCompetencyBarGradient(item.Nilai, item.Nilai_Ambang)
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

            <!-- Modal Dialog Detail Kompetensi & Expected Behaviour -->
            <HcisDialog
                v-model="dialogDetailKompetensi"
                :title="selectedKompetensi?.Nama_Competency || selectedKompetensi?.Kode_Competency || 'Detail Kompetensi'"
                kicker="PROFIL AGREGAT KOMPETENSI"
                :description="selectedKompetensi?.Nama_Type || selectedKompetensi?.Kode_Type || 'Standar Definisi & Expected Behaviour'"
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
                                    <strong class="pa-comp-dialog-score">{{ formatScore(selectedKompetensi.Nilai) }}</strong>
                                    <span class="pa-comp-dialog-scale">/ 100</span>
                                </div>
                            </div>

                            <div class="pa-comp-dialog-status-col">
                                <span
                                    v-if="selectedKompetensi.Nilai_Ambang"
                                    class="pa-competency-item__kkm-pill"
                                    :class="{
                                        'is-pass': Number(selectedKompetensi.Nilai) >= Number(selectedKompetensi.Nilai_Ambang),
                                        'is-under': Number(selectedKompetensi.Nilai) < Number(selectedKompetensi.Nilai_Ambang)
                                    }"
                                >
                                    Ambang Standar: {{ formatScore(selectedKompetensi.Nilai_Ambang) }}
                                </span>
                                <span
                                    v-if="punyaAmbang(selectedKompetensi)"
                                    class="pa-comp-dialog-status-badge"
                                    :class="Number(selectedKompetensi.Nilai) >= Number(selectedKompetensi.Nilai_Ambang) ? 'is-pass' : 'is-under'"
                                    data-uji="status-ambang"
                                >
                                    {{ Number(selectedKompetensi.Nilai) >= Number(selectedKompetensi.Nilai_Ambang) ? 'Memenuhi Standar' : 'Perlu Peningkatan' }}
                                </span>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="pa-competency-item__progress-track" aria-hidden="true">
                            <div
                                class="pa-competency-item__progress-bar"
                                :style="{
                                    width: `${Math.min(100, Math.max(0, Number(selectedKompetensi.Nilai) || 0))}%`,
                                    background: getCompetencyBarGradient(selectedKompetensi.Nilai, selectedKompetensi.Nilai_Ambang)
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
        </template>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import PaDaftarPerilaku from '../../components/PaDaftarPerilaku.vue';
import { dapatkanDeskripsiKompetensi, dapatkanPerilakuKompetensi, dapatkanTeksPerilaku, labelTingkatDituntut } from '../../utils/kompetensiStandar.js';

import { htmlAman } from '../../utils/htmlAman.js';
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisDialog,
    HcisEmptyState,
    HcisIcon,
    HcisResultState,
    HcisStatCard,
    HcisStatusBadge,
} from '@/components/ui';

defineOptions({ name: 'PaSayaResultPanel' });

const props = defineProps({
    data: { type: Object, default: null },
    history: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
});

defineEmits(['retry', 'cycle-change']);

/* Bobot yang SUNGGUH dipakai menghitung hasil ini (berbeda per level dan siklus). */
function angkaAtauNull(v) {
    if (v === null || v === undefined || v === '') return null;
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
}
const bobotKpi = computed(() => angkaAtauNull(props.data?.hasil?.Bobot_KPI_Terpakai));
const bobotKompetensi = computed(() => angkaAtauNull(props.data?.hasil?.Bobot_Kompetensi_Terpakai));

/* Ambang hanya bila HC menetapkannya. Tanpa ambang tidak ada "memenuhi" atau "perlu
   peningkatan" — patokan karangan layar menilai orang dengan standar yang tidak pernah ada. */
function punyaAmbang(item) {
    return angkaAtauNull(item?.Nilai_Ambang) !== null;
}

const dialogDetailKompetensi = ref(false);
const selectedKompetensi = ref(null);

function bukaDetailKompetensi(item) {
    selectedKompetensi.value = item;
    dialogDetailKompetensi.value = true;
}

const cycleOptions = computed(() => {
    const list = Array.isArray(props.history?.periode) ? props.history.periode : [];
    return list.map((p) => ({
        id: p.Id_PA_Cycle,
        kode: p.Kode_Cycle,
        nama: p.Nama_Cycle || p.Kode_Cycle || `Cycle ${p.Tahun}`,
        tahun: p.Tahun,
        grade: p.Kode_Grade_Final,
        score: p.Final_Score,
    }));
});

function isCycleActive(c) {
    if (!props.data?.hasil) return false;
    const h = props.data.hasil;
    if (h.Id_PA_Cycle && Number(h.Id_PA_Cycle) === Number(c.id)) return true;
    if (h.PA_Cycle_Id && Number(h.PA_Cycle_Id) === Number(c.id)) return true;
    if (h.Kode_Cycle && h.Kode_Cycle === c.kode) return true;
    return false;
}

const chartCompetencyCategories = computed(() => {
    return (props.data?.kompetensi || []).map((c) => c.Nama_Competency || c.Kode_Competency || 'Kompetensi');
});

const chartCompetencyData = computed(() => {
    return (props.data?.kompetensi || []).map((c) => {
        const n = Number(c.Nilai);
        return Number.isFinite(n) ? n : 0;
    });
});

/* Radar memakai daftar tersaring sendiri, terpisah dari batang di sebelahnya.

   Keduanya sengaja TIDAK berbagi sumbu: batang adalah rincian, dan rincian harus memuat
   seluruh kompetensi yang dinilai; radar adalah ringkasan bentuk, dan bentuk itu rusak
   kalau setiap kompetensi dipaksa jadi sumbu. `Flag_Radar` hanya berlaku untuk yang kedua.
   Baris tanpa flag dianggap 'Y' supaya respons lama tetap menggambar radar seperti dulu. */
const radarKompetensi = computed(() => {
    const list = props.data?.kompetensi;
    if (!Array.isArray(list) || !list.length) return [];
    const tersaring = list.filter((c) => {
        const flag = c.Flag_Radar ?? c.flag_radar ?? 'Y';
        return flag !== 'T' && flag !== 't' && flag !== false && flag !== 0;
    });
    return tersaring.length > 0 ? tersaring : list;
});

const radarCategories = computed(() =>
    radarKompetensi.value.map((c) => c.Nama_Competency || c.Nama_Kompetensi || c.Kode_Competency || c.nama || 'Kompetensi'),
);

const radarNilai = computed(() =>
    radarKompetensi.value.map((c) => {
        const n = Number(c.Nilai ?? c.nilai ?? c.score ?? c.Skor);
        return Number.isFinite(n) ? n : 0;
    }),
);

const radarSeries = computed(() => {
    if (!radarKompetensi.value.length) return [];

    const series = [
        {
            name: 'Skor Kompetensi',
            value: radarNilai.value,
            data: radarNilai.value,
        },
    ];

    /* null, bukan 0, untuk ambang yang belum ditetapkan — jaring KKM putus di sumbu itu
       alih-alih penyok ke pusat dan terbaca sebagai standar yang sangat rendah. */
    const ambang = radarKompetensi.value.map((c) => {
        const a = c.Nilai_Ambang ?? c.nilai_ambang ?? c.ambang;
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
    return typeof window !== 'undefined' && window.innerWidth <= 768 ? 240 : 400;
});

function isStrengthDimension(item) {
    const code = String(item?.Kode_Type || item?.kode || item?.Kode_Competency || '').toUpperCase();
    const name = String(item?.Nama_Type || item?.nama || item?.Nama_Competency || '').toLowerCase();
    return code === 'QL01' || code === 'STRENGTH' || /strength|kekuatan|keunggulan/i.test(name) || /strength|kekuatan|keunggulan/i.test(code);
}

function isDevAreaDimension(item) {
    const code = String(item?.Kode_Type || item?.kode || item?.Kode_Competency || '').toUpperCase();
    const name = String(item?.Nama_Type || item?.nama || item?.Nama_Competency || '').toLowerCase();
    return code === 'QL02' || code === 'DEVELOPMENT_AREA' || /development|pengembangan|improvement/i.test(name) || /development|pengembangan|improvement/i.test(code);
}

/*
 | Catatan dikelompokkan per pertanyaan kualitatif (hanya kesimpulan resmi, masukan penilai diabaikan).
 */
const catatanPerDimensi = computed(() => {
    const grup = new Map();
    const rawList = Array.isArray(props.data?.catatan)
        ? props.data.catatan
        : (Array.isArray(props.data?.jawaban_naratif) ? props.data.jawaban_naratif : []);

    rawList.forEach((note) => {
        // Masukan mentah dari penilai tidak ditampilkan
        if (note.dari_penilai === true) return;

        const kode = note.Kode_Type || note.Kode_Competency || 'LAINNYA';

        if (!grup.has(kode)) {
            grup.set(kode, {
                kode,
                nama: note.Nama_Type || note.Nama_Competency || kode,
                kesimpulan: '',
            });
        }

        const isi = (note.Isi || note.Jawaban_Teks || note.Jawaban || '').trim();
        if (!isi) return;

        // Ambil satu kesimpulan saja per dimensi
        if (!grup.get(kode).kesimpulan) {
            grup.get(kode).kesimpulan = isi;
        }
    });

    return Array.from(grup.values()).filter((g) => Boolean(g.kesimpulan));
});

const strengthData = computed(() => {
    const rawList = Array.isArray(props.data?.catatan)
        ? props.data.catatan
        : (Array.isArray(props.data?.jawaban_naratif) ? props.data.jawaban_naratif : []);

    const matching = rawList.filter(isStrengthDimension);
    const firstName = matching.find((n) => n.Nama_Type || n.Nama_Competency)?.Nama_Type
        || matching.find((n) => n.Nama_Type || n.Nama_Competency)?.Nama_Competency
        || 'Kekuatan Utama';

    // Cari kesimpulan resmi (bukan masukan mentah dari penilai)
    const conclusionItem = matching.find((n) => {
        if (n.dari_penilai === true) return false;
        const isi = (n.Isi || n.Jawaban_Teks || n.Jawaban || '').trim();
        return Boolean(isi);
    });

    const kesimpulan = conclusionItem
        ? (conclusionItem.Isi || conclusionItem.Jawaban_Teks || conclusionItem.Jawaban || '').trim()
        : '';

    return {
        kode: matching[0]?.Kode_Type || 'QL01',
        nama: firstName,
        kesimpulan,
        ada: matching.length > 0,
    };
});

const devAreaData = computed(() => {
    const rawList = Array.isArray(props.data?.catatan)
        ? props.data.catatan
        : (Array.isArray(props.data?.jawaban_naratif) ? props.data.jawaban_naratif : []);

    const matching = rawList.filter(isDevAreaDimension);
    const firstName = matching.find((n) => n.Nama_Type || n.Nama_Competency)?.Nama_Type
        || matching.find((n) => n.Nama_Type || n.Nama_Competency)?.Nama_Competency
        || 'Area Pengembangan';

    // Cari kesimpulan resmi (bukan masukan mentah dari penilai)
    const conclusionItem = matching.find((n) => {
        if (n.dari_penilai === true) return false;
        const isi = (n.Isi || n.Jawaban_Teks || n.Jawaban || '').trim();
        return Boolean(isi);
    });

    const kesimpulan = conclusionItem
        ? (conclusionItem.Isi || conclusionItem.Jawaban_Teks || conclusionItem.Jawaban || '').trim()
        : '';

    return {
        kode: matching[0]?.Kode_Type || 'QL02',
        nama: firstName,
        kesimpulan,
        ada: matching.length > 0,
    };
});

const adaKolomKualitatif = computed(() => strengthData.value.ada || devAreaData.value.ada);

const otherCatatanList = computed(() => {
    return catatanPerDimensi.value.filter((g) => !isStrengthDimension(g) && !isDevAreaDimension(g));
});

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

/* Nama grade dari server: pita kurva beku (atau pita siklus) tempat grade terbit itu lahir.
   Arti grade bisa berbeda antar siklus; layar tidak mengarangnya. */
function gradePredicate(g, nama) {
    if (!g) return 'Belum Ditetapkan';
    return nama || `Grade ${displayGrade(g)}`;
}

const gradeThemeClass = computed(() => {
    const g = String(props.data?.hasil?.Kode_Grade_Final || '').toUpperCase();
    if (g.startsWith('A')) return 'is-grade-a';
    if (g.startsWith('B')) return 'is-grade-b';
    if (g.startsWith('C')) return 'is-grade-c';
    if (g.startsWith('D') || g.startsWith('E')) return 'is-grade-d';
    return 'is-grade-neutral';
});

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

function getCompetencyBarGradient(nilai, ambang) {
    const val = Number(nilai) || 0;
    // Tanpa ambang: satu warna netral, bukan hijau/kuning yang menyiratkan lulus/tidak.
    if (ambang === null || ambang === undefined || ambang === '') return 'linear-gradient(90deg, #6366f1 0%, #4f46e5 100%)';
    const kkm = Number(ambang);
    if (val >= kkm) return 'linear-gradient(90deg, #10b981 0%, #059669 100%)';
    if (val >= 60) return 'linear-gradient(90deg, #6366f1 0%, #4f46e5 100%)';
    return 'linear-gradient(90deg, #f59e0b 0%, #d97706 100%)';
}
</script>

<style scoped>
.pa-saya-result-panel,
.pa-saya-result-panel :deep(.hcis-card) {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-saya-result__syncing {
    margin: 0 0 0.75rem;
    font-size: 0.75rem;
    color: var(--hcis-color-text-muted, #64748b);
}

/* 1. Multi-Cycle Selector Card */
.pa-result-cycle-selector-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1rem 1.25rem 0.85rem;
    border-radius: var(--hcis-radius-lg, 14px);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(14px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
    margin-bottom: 1.25rem;
}

.pa-result-cycle-selector-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.pa-result-cycle-selector-title-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-result-cycle-selector-icon-box {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: rgba(99, 102, 241, 0.1);
    color: var(--hcis-color-brand-600, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-result-cycle-selector-title-copy {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-result-cycle-selector-title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    letter-spacing: -0.01em;
}

.pa-result-cycle-selector-subtitle {
    margin: 0;
    font-size: 0.72rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-result-cycle-selector-hint {
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-color-brand-600, #4f46e5);
    background: rgba(99, 102, 241, 0.08);
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    border: 1px solid rgba(99, 102, 241, 0.18);
    white-space: nowrap;
    flex-shrink: 0;
}

/* Horizontal Track Ribbon with smooth scrolling */
.pa-result-cycle-track {
    display: flex;
    align-items: stretch;
    gap: 0.75rem;
    overflow-x: auto;
    padding: 0.2rem 0.15rem 0.5rem;
    scroll-snap-type: x mandatory;
    scrollbar-width: thin;
    scrollbar-color: rgba(203, 213, 225, 0.8) transparent;
}

.pa-result-cycle-track::-webkit-scrollbar {
    height: 5px;
}

.pa-result-cycle-track::-webkit-scrollbar-track {
    background: transparent;
}

.pa-result-cycle-track::-webkit-scrollbar-thumb {
    background-color: rgba(203, 213, 225, 0.8);
    border-radius: 999px;
}

.pa-result-cycle-track::-webkit-scrollbar-thumb:hover {
    background-color: rgba(148, 163, 184, 0.9);
}

/* Cycle Pill Card */
.pa-result-cycle-pill {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.55rem;
    min-width: 220px;
    max-width: 280px;
    flex: 1 0 auto;
    padding: 0.75rem 0.95rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(248, 250, 252, 0.9);
    border: 1.5px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.03);
    cursor: pointer;
    text-align: left;
    scroll-snap-align: start;
    transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    user-select: none;
}

.pa-result-cycle-pill:hover {
    background: #ffffff;
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.07);
    transform: translateY(-2px);
}

.pa-result-cycle-pill.is-active {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.98) 0%, rgba(245, 243, 255, 0.98) 100%);
    border-color: var(--hcis-color-brand-600, #4f46e5);
    box-shadow: 0 4px 16px rgba(79, 70, 229, 0.16), 0 0 0 1px var(--hcis-color-brand-600, #4f46e5);
    transform: translateY(-1px);
}

.pa-result-cycle-pill__top {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-result-cycle-pill__indicator {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 1.5px solid rgba(203, 213, 225, 0.9);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #ffffff;
    color: #ffffff;
    transition: all 160ms ease;
}

.pa-result-cycle-pill.is-active .pa-result-cycle-pill__indicator {
    background: var(--hcis-color-brand-600, #4f46e5);
    border-color: var(--hcis-color-brand-600, #4f46e5);
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35);
}

.pa-result-cycle-pill__year {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-result-cycle-pill.is-active .pa-result-cycle-pill__year {
    color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-result-cycle-pill__badge {
    margin-left: auto;
}

.pa-result-cycle-pill__bottom {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-result-cycle-pill__name {
    font-size: 0.84rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-result-cycle-pill.is-active .pa-result-cycle-pill__name {
    color: var(--hcis-color-brand-600, #4f46e5);
}

.pa-result-cycle-pill__score {
    font-size: 0.72rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-result-cycle-pill__score strong {
    color: var(--hcis-color-text-main, #0f172a);
    font-weight: 750;
}

/* 2. Hero Card & Cockpit Pillars */
.pa-saya-result__hero {
    margin-bottom: 1.25rem;
}

.pa-hero-header-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-saya-result__hero-cockpit {
    display: grid;
    grid-template-columns: 1.35fr 1fr 1fr;
    gap: 1.15rem;
    padding: 0.5rem 0 1.25rem;
    border-bottom: 1px solid var(--hcis-color-border, #e2e8f0);
}

@media (max-width: 991px) {
    .pa-saya-result__hero-cockpit {
        grid-template-columns: 1fr;
    }
}

.pa-hero-pillar {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 1.1rem 1.25rem;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.85);
    backdrop-filter: blur(12px);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04);
    position: relative;
    overflow: hidden;
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.pa-hero-pillar:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08);
}

.pa-hero-pillar__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.35rem;
}

/* Pillar 1: Grade Medallion Themes */
.pa-hero-pillar--grade {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
}

.pa-hero-pillar--grade.is-grade-a {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.14) 100%);
    border-color: rgba(16, 185, 129, 0.35);
    box-shadow: 0 8px 24px -4px rgba(16, 185, 129, 0.16), 0 0 0 1px rgba(16, 185, 129, 0.18) inset;
}

.pa-hero-pillar--grade.is-grade-b {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.07) 0%, rgba(99, 102, 241, 0.14) 100%);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 8px 24px -4px rgba(79, 70, 229, 0.14), 0 0 0 1px rgba(99, 102, 241, 0.18) inset;
}

.pa-hero-pillar--grade.is-grade-c {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.07) 0%, rgba(217, 119, 6, 0.14) 100%);
    border-color: rgba(245, 158, 11, 0.35);
    box-shadow: 0 8px 24px -4px rgba(245, 158, 11, 0.14), 0 0 0 1px rgba(245, 158, 11, 0.18) inset;
}

.pa-hero-pillar--grade.is-grade-d {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.07) 0%, rgba(220, 38, 38, 0.14) 100%);
    border-color: rgba(239, 68, 68, 0.35);
    box-shadow: 0 8px 24px -4px rgba(239, 68, 68, 0.14), 0 0 0 1px rgba(239, 68, 68, 0.18) inset;
}

.pa-grade-medallion {
    display: flex;
    align-items: center;
    gap: 1.15rem;
}

.pa-grade-medallion__ring {
    width: 66px;
    height: 66px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 2.25rem;
    line-height: 1;
    flex-shrink: 0;
    box-shadow: 0 8px 20px -2px rgba(0, 0, 0, 0.12), inset 0 1px 1px rgba(255, 255, 255, 0.85);
    transition: transform 180ms ease;
}

.is-grade-a .pa-grade-medallion__ring {
    background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    color: #ffffff;
    box-shadow: 0 8px 20px -2px rgba(16, 185, 129, 0.4), inset 0 2px 2px rgba(255, 255, 255, 0.5);
}

.is-grade-b .pa-grade-medallion__ring {
    background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
    color: #ffffff;
    box-shadow: 0 8px 20px -2px rgba(99, 102, 241, 0.4), inset 0 2px 2px rgba(255, 255, 255, 0.5);
}

.is-grade-c .pa-grade-medallion__ring {
    background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);
    color: #ffffff;
    box-shadow: 0 8px 20px -2px rgba(245, 158, 11, 0.4), inset 0 2px 2px rgba(255, 255, 255, 0.5);
}

.is-grade-d .pa-grade-medallion__ring {
    background: linear-gradient(135deg, #f43f5e 0%, #be123c 100%);
    color: #ffffff;
    box-shadow: 0 8px 20px -2px rgba(244, 63, 94, 0.4), inset 0 2px 2px rgba(255, 255, 255, 0.5);
}

.is-grade-neutral .pa-grade-medallion__ring {
    background: linear-gradient(135deg, #64748b 0%, #334155 100%);
    color: #ffffff;
}

.pa-grade-medallion__meta {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-grade-medallion__eyebrow-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-grade-calibrated-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    background: rgba(124, 58, 237, 0.14);
    color: #7c3aed;
}

.pa-grade-medallion__predicate {
    font-size: 1.15rem;
    font-weight: 850;
    color: var(--hcis-color-text-main, #0f172a);
    letter-spacing: -0.01em;
    line-height: 1.25;
}

.pa-grade-medallion__sub {
    font-size: 0.74rem;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.35;
}

/* Pillar 2: Score Pillar */
.pa-hero-pillar--score {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.85) 100%);
}

.pa-hero-score-scale {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hero-score-number-row {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    margin: 0.15rem 0 0.45rem;
}

.pa-hero-score-value {
    font-size: 2.35rem;
    line-height: 1;
    font-weight: 900;
    letter-spacing: -0.02em;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-hero-score-unit {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-hero-score-progress {
    height: 6px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.8);
    overflow: hidden;
    margin-bottom: 0.4rem;
}

.pa-hero-score-progress__bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-hero-score-caption {
    font-size: 0.74rem;
    color: var(--hcis-color-text-muted, #64748b);
}

/* Pillar 3: Composition (KPI vs Kompetensi 360°) */
.pa-hero-pillar--composition {
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-composition-tag {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-surface-soft, #f1f5f9);
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-composition-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    margin: 0.4rem 0 0.5rem;
}

.pa-composition-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-composition-item__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-composition-item__label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-700, #334155);
}

.pa-composition-item__score {
    font-size: 0.875rem;
    font-weight: 750;
    color: var(--hcis-slate-900, #0f172a);
}

.pa-composition-item__scale {
    font-size: 0.6875rem;
    color: var(--hcis-slate-400, #94a3b8);
}

.pa-composition-track {
    width: 100%;
    height: 6px;
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
    font-size: 0.7rem;
    color: var(--hcis-slate-500, #64748b);
    line-height: 1.35;
    margin-top: auto;
}

.pa-eyebrow {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-saya-result__meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
    padding-top: 1.15rem;
}

.pa-saya-result__meta > div {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-saya-result__meta span {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-saya-result__meta strong {
    font-size: 0.95rem;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-saya-result__score-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
    margin-bottom: 1.25rem;
}

/* 4. Dual Charts Grid */
.pa-saya-result__charts {
    margin-bottom: 1.5rem;
}

.pa-result-charts-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.35rem;
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
    gap: 1.15rem;
    height: 100%;
}

.pa-result-charts-grid.is-single-col .pa-qualitative-column {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
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

@media (max-width: 991px) {
    .pa-result-charts-grid {
        grid-template-columns: 1fr;
    }
}

/* 6. Competency Card (Full Width with Descriptions) */
.pa-saya-result__competency-card {
    margin-top: 0.25rem;
    margin-bottom: 1.25rem;
}

.pa-competency-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 1.15rem;
}

.pa-competency-item {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1.1rem 1.25rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 2px 8px -1px rgba(15, 23, 42, 0.03);
    transition: all 180ms ease;
}

.pa-competency-item:hover {
    background: rgba(255, 255, 255, 0.98);
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 8px 20px -3px rgba(15, 23, 42, 0.08);
    transform: translateY(-2px);
}

.pa-competency-item__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.pa-competency-item__title-group {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.35rem;
    min-width: 0;
    flex: 1;
}

.pa-competency-item__badge {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-color-brand-600, #4f46e5);
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.16);
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    line-height: 1.2;
}

.pa-competency-item__title {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1.35;
    letter-spacing: -0.01em;
}

.pa-competency-item__score-group {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
    flex-shrink: 0;
}

.pa-competency-item__score-display {
    display: flex;
    align-items: baseline;
    gap: 0.2rem;
}

.pa-competency-item__score-number {
    font-size: 1.35rem;
    font-weight: 850;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1;
    letter-spacing: -0.02em;
}

.pa-competency-item__score-scale {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-color-text-muted, #94a3b8);
}

.pa-competency-item__kkm-pill {
    font-size: 0.68rem;
    font-weight: 650;
    padding: 0.15rem 0.45rem;
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
    height: 6px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.85);
    overflow: hidden;
    margin: 0.15rem 0;
}

.pa-competency-item__progress-bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-competency-item__teaser {
    display: flex;
    flex-direction: column;
    padding: 0.55rem 0.7rem;
    border-radius: 8px;
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.pa-competency-item__teaser-text {
    margin: 0;
    font-size: 0.78125rem;
    color: var(--hcis-color-text-muted, #475569);
    line-height: 1.45;
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
    padding-top: 0.45rem;
    border-top: 1px solid rgba(226, 232, 240, 0.75);
}

.pa-competency-teaser-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: #0f766e;
    background: rgba(240, 253, 250, 0.9);
    border: 1px solid rgba(204, 251, 241, 0.9);
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
}

.pa-competency-teaser-action {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-color-brand-600, #4f46e5);
    transition: all 150ms ease;
}

.pa-competency-item:hover .pa-competency-teaser-action {
    gap: 0.4rem;
    color: #4338ca;
}

/* Competency Dialog Styling */
.pa-comp-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-comp-dialog-banner {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.95rem 1.1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(248, 250, 252, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.95);
}

.pa-comp-dialog-score-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-comp-dialog-score-col {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-comp-dialog-lbl {
    font-size: 0.65rem;
    font-weight: 750;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-comp-dialog-val-wrap {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.pa-comp-dialog-score {
    font-size: 1.65rem;
    font-weight: 850;
    line-height: 1;
    color: var(--hcis-color-brand-600, #4f46e5);
    font-variant-numeric: tabular-nums;
}

.pa-comp-dialog-scale {
    font-size: 0.8rem;
    color: var(--hcis-color-text-muted, #94a3b8);
    font-weight: 600;
}

.pa-comp-dialog-status-col {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.35rem;
}

.pa-comp-dialog-status-badge {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
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
    gap: 0.35rem;
}

.pa-competency-box-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    margin-bottom: 0.15rem;
}

.pa-competency-box-header__left {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-competency-box-header__icon {
    flex-shrink: 0;
}

.pa-competency-box-header__label {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-competency-box-header.is-teal .pa-competency-box-header__label {
    color: #0f766e;
}

.pa-competency-box-header__badge {
    font-size: 0.625rem;
    font-weight: 700;
    padding: 0.08rem 0.4rem;
    border-radius: 999px;
    background: rgba(13, 148, 136, 0.12);
    color: #0f766e;
    line-height: 1.2;
}

.pa-comp-dialog-desc-card {
    padding: 0.75rem 0.95rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.pa-comp-dialog-desc-text {
    margin: 0;
    font-size: 0.85rem;
    line-height: 1.55;
    color: var(--hcis-color-text-main, #334155);
}

.pa-comp-dialog-behaviour-card {
    padding: 0.75rem 0.95rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(240, 253, 250, 0.75);
    border: 1px solid rgba(204, 251, 241, 0.95);
}

.pa-comp-dialog-perilaku-list {
    margin: 0;
    padding: 0;
}

/* 6. Standalone Qualitative Dimension Cards (Full Col-12 Row) */
.pa-saya-result__qualitative-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 1.25rem;
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
    gap: 0.85rem;
    height: 100%;
}

.pa-qualitative-card__item {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1rem 1.25rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-left: 4.5px solid var(--hcis-color-teal, #0d9488);
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    line-height: 1.6;
}

:deep(.hcis-card--sentiment-purple) .pa-qualitative-card__item,
:deep(.hcis-card--purple) .pa-qualitative-card__item {
    border-left-color: var(--hcis-color-purple, #7c3aed);
    background: rgba(245, 243, 255, 0.55);
}

:deep(.hcis-card--sentiment-teal) .pa-qualitative-card__item,
:deep(.hcis-card--teal) .pa-qualitative-card__item {
    border-left-color: var(--hcis-color-teal, #0d9488);
    background: rgba(240, 253, 250, 0.55);
}

.pa-qualitative-card__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 0.65rem;
    padding: 1.35rem 1.25rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.85) 0%, rgba(241, 245, 249, 0.45) 100%);
    border: 1px dashed var(--hcis-border-subtle, #cbd5e1);
    flex: 1;
    min-height: 140px;
    box-sizing: border-box;
}

.pa-qualitative-card__empty-avatar {
    width: 38px;
    height: 38px;
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
    font-size: 1.1rem;
}

.pa-qualitative-card__empty-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    max-width: 380px;
}

.pa-qualitative-card__empty-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-slate-700, #334155);
    letter-spacing: -0.01em;
}

.pa-qualitative-card__empty-desc {
    margin: 0;
    font-size: 0.775rem;
    line-height: 1.45;
    color: var(--hcis-slate-500, #64748b);
}

.pa-qualitative-card__empty-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 600;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: var(--hcis-slate-500, #64748b);
    margin-top: 0.2rem;
}

.pa-qualitative-card__quote-mark {
    font-size: 2rem;
    line-height: 0.8;
    font-family: Georgia, serif;
    color: var(--hcis-color-text-muted, #94a3b8);
    opacity: 0.5;
    user-select: none;
    flex-shrink: 0;
    margin-top: 0.15rem;
}

.pa-qualitative-card__text {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--hcis-color-text-main, #0f172a);
    line-height: 1.65;
    flex: 1;
}

.pa-qualitative-card__text :deep(p) {
    margin: 0 0 0.6rem 0;
}

.pa-qualitative-card__text :deep(p:last-child) {
    margin-bottom: 0;
}

.pa-qualitative-card__text :deep(ul),
.pa-qualitative-card__text :deep(ol) {
    margin: 0.4rem 0 0.6rem 1.35rem;
    padding: 0;
}

.pa-qualitative-card__text :deep(li) {
    margin-bottom: 0.25rem;
}

.pa-qualitative-card__text :deep(blockquote) {
    margin: 0.6rem 0;
    padding: 0.4rem 0.85rem;
    border-left: 3.5px solid var(--hcis-color-brand-500, #6366f1);
    background: rgba(99, 102, 241, 0.05);
    border-radius: 0 var(--hcis-radius-sm, 6px) var(--hcis-radius-sm, 6px) 0;
    color: var(--hcis-color-text-subtle, #475569);
    font-style: italic;
}

.pa-qualitative-card__text :deep(strong) {
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-eyebrow {
    color: var(--hcis-color-brand-600, #4f46e5);
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

@media (max-width: 1024px) {
    .pa-result-charts-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    /* Subtitles, hints & redundant captions hidden on mobile per user instruction */
    .pa-grade-medallion__sub,
    .pa-hero-score-caption,
    .pa-composition-caption,
    .hcis-stat__caption,
    .pa-result-cycle-selector-subtitle,
    .pa-result-cycle-selector-hint,
    .pa-qualitative-card__empty-desc,
    :deep(.hcis-card__description),
    :deep(.hcis-card p.hcis-card__description) {
        display: none !important;
    }

    :deep(.hcis-card__header) {
        padding: 0.65rem 0.75rem !important;
        gap: 0.5rem !important;
    }

    :deep(.hcis-card__body) {
        padding: 0.65rem 0.75rem !important;
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

    /* 1. Compact Cycle Selector Card */
    .pa-result-cycle-selector-card {
        padding: 0.55rem 0.7rem 0.45rem !important;
        gap: 0.45rem !important;
        margin-bottom: 0.65rem !important;
        border-radius: var(--hcis-radius-md, 10px) !important;
    }

    .pa-result-cycle-selector-header {
        gap: 0.5rem !important;
    }

    .pa-result-cycle-selector-icon-box {
        width: 24px !important;
        height: 24px !important;
        border-radius: 6px !important;
    }

    .pa-result-cycle-selector-title {
        font-size: 0.8rem !important;
    }

    .pa-result-cycle-track {
        gap: 0.45rem !important;
        padding: 0.1rem 0.1rem 0.3rem !important;
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }

    .pa-result-cycle-track::-webkit-scrollbar {
        display: none;
    }

    .pa-result-cycle-pill {
        min-width: 140px !important;
        max-width: 175px !important;
        padding: 0.4rem 0.55rem !important;
        gap: 0.3rem !important;
        border-radius: 8px !important;
    }

    .pa-result-cycle-pill__top {
        gap: 0.3rem !important;
    }

    .pa-result-cycle-pill__indicator {
        width: 14px !important;
        height: 14px !important;
    }

    .pa-result-cycle-pill__year {
        font-size: 0.68rem !important;
    }

    .pa-result-cycle-pill__badge {
        font-size: 0.6rem !important;
        padding: 0.05rem 0.35rem !important;
    }

    .pa-result-cycle-pill__bottom {
        gap: 0.1rem !important;
    }

    .pa-result-cycle-pill__name {
        font-size: 0.75rem !important;
    }

    .pa-result-cycle-pill__score {
        font-size: 0.68rem !important;
    }

    /* 2. Hero Card Cockpit: 2-Column Split (Grade + Score) + Full-Width Composition */
    .pa-saya-result__hero {
        margin-bottom: 0.65rem !important;
    }

    .pa-saya-result__hero-cockpit {
        grid-template-columns: 1fr 1fr !important;
        gap: 0.5rem !important;
        padding: 0.2rem 0 0.55rem !important;
    }

    .pa-hero-pillar {
        padding: 0.55rem 0.65rem !important;
        border-radius: var(--hcis-radius-md, 10px) !important;
    }

    /* Pillar 1: Grade Medallion */
    .pa-grade-medallion {
        gap: 0.45rem !important;
    }

    .pa-grade-medallion__ring {
        width: 38px !important;
        height: 38px !important;
        border-radius: 9px !important;
        font-size: 1.25rem !important;
    }

    .pa-grade-medallion__eyebrow-row {
        gap: 0.25rem !important;
    }

    .pa-grade-medallion__predicate {
        font-size: 0.85rem !important;
        line-height: 1.2 !important;
    }

    .pa-grade-calibrated-tag {
        font-size: 0.56rem !important;
        padding: 0.05rem 0.3rem !important;
    }

    /* Pillar 2: Final Score */
    .pa-hero-pillar--score .pa-hero-pillar__head {
        margin-bottom: 0.15rem;
    }

    .pa-hero-score-scale {
        font-size: 0.6rem;
    }

    .pa-hero-score-number-row {
        margin: 0.1rem 0 0.25rem;
        gap: 0.2rem;
    }

    .pa-hero-score-value {
        font-size: 1.35rem !important;
    }

    .pa-hero-score-unit {
        font-size: 0.68rem !important;
    }

    .pa-hero-score-progress {
        height: 4px;
        margin-bottom: 0;
    }

    /* Pillar 3: Composition spans full width across both columns */
    .pa-hero-pillar--composition {
        grid-column: 1 / -1 !important;
        padding: 0.55rem 0.75rem !important;
    }

    .pa-hero-pillar--composition .pa-hero-pillar__head {
        margin-bottom: 0.25rem;
    }

    .pa-composition-tag {
        font-size: 0.6rem !important;
        padding: 0.1rem 0.4rem !important;
    }

    .pa-composition-list {
        gap: 0.35rem !important;
        margin: 0.2rem 0 0 !important;
    }

    .pa-composition-item {
        gap: 0.15rem !important;
    }

    .pa-composition-item__label {
        font-size: 0.72rem !important;
    }

    .pa-composition-item__score {
        font-size: 0.8125rem !important;
    }

    .pa-composition-item__scale {
        font-size: 0.625rem !important;
    }

    .pa-composition-track {
        height: 4px !important;
    }

    /* 3. Hide redundant Stat Cards on mobile (already summarized in Hero Cockpit) */
    .pa-saya-result__score-grid {
        display: none !important;
    }

    /* 4. Compact 2x2 Meta Chips */
    .pa-saya-result__meta {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.35rem !important;
        padding-top: 0.45rem !important;
    }

    .pa-saya-result__meta > div {
        background: rgba(248, 250, 252, 0.75);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 6px;
        padding: 0.35rem 0.5rem;
        gap: 0.1rem;
    }

    .pa-saya-result__meta span {
        font-size: 0.6rem !important;
        line-height: 1.1;
    }

    .pa-saya-result__meta strong {
        font-size: 0.75rem !important;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* 5. Compact Charts & Qualitative Feedback */
    .pa-saya-result__charts {
        margin-bottom: 0.65rem !important;
    }

    .pa-result-charts-grid {
        grid-template-columns: 1fr !important;
        gap: 0.65rem !important;
    }

    .pa-qualitative-column {
        gap: 0.5rem !important;
    }

    .pa-qualitative-column > .pa-qualitative-card {
        margin-bottom: 0 !important;
    }

    .pa-qualitative-card__item {
        padding: 0.5rem 0.65rem !important;
        gap: 0.45rem !important;
        border-radius: 6px !important;
    }

    .pa-qualitative-card__quote-mark {
        font-size: 1.35rem !important;
        margin-top: 0 !important;
    }

    .pa-qualitative-card__text {
        font-size: 0.8125rem !important;
        line-height: 1.45 !important;
    }

    .pa-qualitative-card__empty {
        padding: 0.65rem 0.75rem !important;
        min-height: 0 !important;
        gap: 0.35rem !important;
        border-radius: 8px !important;
    }

    .pa-qualitative-card__empty-avatar {
        width: 28px !important;
        height: 28px !important;
    }

    .pa-qualitative-card__empty-icon {
        font-size: 0.85rem !important;
    }

    .pa-qualitative-card__empty-title {
        font-size: 0.78125rem !important;
    }

    .pa-qualitative-card__empty-badge {
        font-size: 0.625rem !important;
        padding: 0.1rem 0.35rem !important;
        margin-top: 0 !important;
    }

    .pa-saya-result__qualitative-grid {
        grid-template-columns: 1fr !important;
        gap: 0.5rem !important;
        margin-bottom: 0.65rem !important;
    }

    /* 6. Compact Profil Agregat Kompetensi */
    .pa-saya-result__competency-card {
        margin-top: 0 !important;
        margin-bottom: 0.65rem !important;
    }

    .pa-competency-grid {
        grid-template-columns: 1fr !important;
        gap: 0.5rem !important;
    }

    .pa-competency-item {
        padding: 0.6rem 0.75rem !important;
        border-radius: var(--hcis-radius-md, 10px) !important;
        gap: 0.35rem !important;
    }

    .pa-competency-item__header {
        gap: 0.5rem !important;
    }

    .pa-competency-item__title-group {
        gap: 0.2rem !important;
    }

    .pa-competency-item__badge {
        font-size: 0.625rem !important;
        padding: 0.1rem 0.4rem !important;
    }

    .pa-competency-item__title {
        font-size: 0.875rem !important;
        line-height: 1.25 !important;
    }

    .pa-competency-item__score-number {
        font-size: 1.15rem !important;
    }

    .pa-competency-item__score-scale {
        font-size: 0.68rem !important;
    }

    .pa-competency-item__kkm-pill {
        font-size: 0.625rem !important;
        padding: 0.1rem 0.35rem !important;
    }

    .pa-competency-item__progress-track {
        height: 4px !important;
        margin: 0.05rem 0 !important;
    }

    .pa-competency-item__teaser {
        padding: 0.4rem 0.55rem !important;
        border-radius: 6px !important;
    }

    .pa-competency-item__teaser-text {
        font-size: 0.75rem !important;
        line-height: 1.35 !important;
    }

    .pa-competency-item__footer {
        padding-top: 0.35rem !important;
    }

    .pa-competency-teaser-badge {
        font-size: 0.625rem !important;
        padding: 0.1rem 0.35rem !important;
    }

    .pa-competency-teaser-action {
        font-size: 0.7rem !important;
    }

    /* 7. Dialog / Modal Detail Kompetensi */
    .pa-comp-dialog-score {
        font-size: 1.35rem !important;
    }

    .pa-comp-dialog-banner {
        padding: 0.75rem 0.85rem !important;
    }

    .pa-comp-dialog-desc-card,
    .pa-comp-dialog-behaviour-card {
        padding: 0.65rem 0.75rem !important;
    }

    .pa-comp-dialog-desc-text {
        font-size: 0.78125rem !important;
    }

    .pa-comp-dialog-perilaku-list :deep(li) {
        font-size: 0.75rem !important;
        line-height: 1.4 !important;
        padding-left: 0.85rem !important;
    }

    .pa-saya-result__hero-copy {
        flex-direction: column;
        align-items: flex-start;
    }

    .pa-saya-result__quadrant {
        width: 100%;
        text-align: left;
    }
}

.pa-axis-col-corner {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.65rem;
    font-weight: 750;
    color: #6366f1;
    padding: 0.35rem 0.5rem;
}

.pa-axis-corner-y {
    color: #7c3aed;
}

.pa-axis-corner-x {
    color: #059669;
}

.pa-axis-col-head {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    text-align: center;
}

.pa-axis-level-label {
    font-size: 0.76rem;
    font-weight: 750;
    color: #1e1b4b;
}

.pa-axis-col-head small {
    font-size: 0.64rem;
    color: #64748b;
    font-weight: 600;
}

.pa-axis-row-head {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    padding: 0.65rem 0.4rem;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    text-align: center;
}

.pa-axis-row-head strong {
    font-size: 0.74rem;
    color: #1e1b4b;
    line-height: 1.25;
}

.pa-axis-row-head small {
    font-size: 0.64rem;
    color: #64748b;
    font-weight: 600;
}

.pa-axis-row-badge {
    font-size: 0.62rem;
    font-weight: 800;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    letter-spacing: 0.04em;
}

.pa-axis-row-badge.is-high {
    background: #e0e7ff;
    color: #3730a3;
}

.pa-axis-row-badge.is-med {
    background: #f1f5f9;
    color: #475569;
}

.pa-axis-row-badge.is-low {
    background: #fee2e2;
    color: #991b1b;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.8); }
}

.pa-tingkat-dituntut { margin-left: 0.4rem; padding: 0.05rem 0.45rem; border-radius: 999px; background: #ede9fe; color: #5b21b6; font-size: 0.68rem; font-weight: 700; white-space: nowrap; }
</style>

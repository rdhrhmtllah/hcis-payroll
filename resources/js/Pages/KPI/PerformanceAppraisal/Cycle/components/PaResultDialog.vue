<template>
    <HcisDialog
        :model-value="modelValue"
        :title="
            detail?.peserta?.Nama_Karyawan_Saat_Cycle || participant?.Nama_Karyawan_Saat_Cycle || 'Detail hasil peserta'
        "
        kicker="HASIL PERFORMANCE APPRAISAL"
        :description="
            detail?.peserta ? `${detail.peserta.Kode_Karyawan} · ${detail.peserta.Nama_Level_Saat_Cycle || ''}` : ''
        "
        icon="bar-chart-line"
        sentiment="success"
        variant="glass"
        size="full"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="pa-result">
            <!-- Error State -->
            <HcisResultState
                v-if="error"
                :status="errorStatus === 403 ? 'warning' : 'danger'"
                variant="flat"
                layout="inline"
                :title="errorStatus === 403 ? 'Akses hasil tidak tersedia' : 'Detail hasil gagal dimuat'"
                :description="error"
                :show-retry="errorStatus !== 403"
                :error-code="errorCode"
                :request-id="requestId"
                @retry="muatDetail"
            />

            <!-- Loading State -->
            <div v-else-if="loading" class="pa-result__loading">
                <HcisIcon name="arrow-repeat" size="lg" animation="spin" color="success" />
                <span>Memuat detail hasil...</span>
            </div>

            <!-- Action Error State -->
            <HcisResultState
                v-else-if="actionError && !dialogPembatalan"
                :status="actionErrorStatus === 403 ? 'warning' : 'danger'"
                variant="flat"
                layout="inline"
                :title="actionErrorStatus === 403 ? 'Akses aksi hasil tidak tersedia' : 'Aksi hasil ditolak'"
                :description="actionError"
                :show-retry="false"
                :error-code="actionErrorCode"
                :request-id="actionRequestId"
            />

            <!-- Publish Error State -->
            <HcisResultState
                v-else-if="publishError && !dialogPembatalan && !dialogPenerbitan"
                :status="publishErrorStatus === 403 ? 'warning' : 'danger'"
                variant="flat"
                layout="inline"
                :title="publishErrorStatus === 403 ? 'Akses penerbitan tidak tersedia' : 'Penerbitan hasil ditolak'"
                :description="publishError"
                :show-retry="false"
                :error-code="publishErrorCode"
                :request-id="publishRequestId"
            />

            <template v-else-if="detail?.peserta">
                <!-- Action / Publish Success Alerts -->
                <HcisResultState
                    v-if="actionSuccess"
                    status="success"
                    variant="flat"
                    layout="inline"
                    title="Aksi hasil tersimpan"
                    :description="actionSuccess"
                    :show-retry="false"
                />
                <HcisResultState
                    v-if="publishSuccess"
                    status="success"
                    variant="flat"
                    layout="inline"
                    title="Hasil peserta diterbitkan"
                    :description="publishSuccess"
                    :show-retry="false"
                />

                <!-- Hero Cockpit Header Profile -->
                <div class="pa-result-hero">
                    <div class="pa-result-hero__profile">
                        <HcisAvatar
                            :name="detail.peserta.Nama_Karyawan_Saat_Cycle"
                            :size="isMobile ? 'md' : 'lg'"
                            variant="gradient"
                            shape="circle"
                        />
                        <div class="pa-result-hero__meta">
                            <div class="pa-result-hero__name-row">
                                <h3 class="pa-result-hero__name">{{ detail.peserta.Nama_Karyawan_Saat_Cycle }}</h3>
                                <span class="pa-result-hero__code">{{ detail.peserta.Kode_Karyawan }}</span>
                                <span v-if="detail.peserta.Nama_Level_Saat_Cycle" class="pa-result-hero__level-badge">
                                    {{ detail.peserta.Nama_Level_Saat_Cycle }}
                                </span>
                                <HcisBadge
                                    v-if="isPublished"
                                    label="Diterbitkan"
                                    variant="success"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                                <HcisBadge
                                    v-else-if="isTertahan"
                                    label="Tertahan"
                                    variant="warning"
                                    appearance="subtle"
                                    size="xs"
                                    dot
                                />
                            </div>
                            <div class="pa-result-hero__sub">
                                <span v-if="detail.kuadran?.Nama_Kuadran" class="pa-result-hero__kuadran-tag">
                                    <HcisIcon name="grid-3x3-gap-fill" size="2xs" />
                                    {{ detail.kuadran.Nama_Kuadran }}
                                </span>
                                <span class="pa-hero-sep">&bull;</span>
                                <span class="pa-result-hero__calc-tag">
                                    <HcisIcon name="clock-history" size="2xs" />
                                    Versi {{ detail.peserta.Calculation_Version || 1 }}
                                </span>
                                <template v-if="detail.peserta.Flag_Dikalibrasi === 'Y'">
                                    <span class="pa-hero-sep">&bull;</span>
                                    <HcisBadge label="Dikalibrasi" variant="warning" appearance="subtle" size="xs" dot />
                                </template>
                                <template v-if="sudahDirilisKeLeader">
                                    <span class="pa-hero-sep">&bull;</span>
                                    <HcisBadge label="Rilis Leader" variant="brand" appearance="subtle" size="xs" dot />
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Hero Score Card / Status Highlight -->
                    <div v-if="detail.peserta.Final_Score !== null && detail.peserta.Final_Score !== undefined" class="pa-result-hero__score-widget">
                        <div class="pa-hero-score-block">
                            <span class="pa-hero-score-label">Final Score</span>
                            <strong class="pa-hero-score-val">{{ formatScore(detail.peserta.Final_Score) }}</strong>
                        </div>
                        <div v-if="!modeVerifikasi && gradeTampil" class="pa-hero-score-divider" />
                        <div v-if="!modeVerifikasi && gradeTampil" class="pa-hero-grade-block">
                            <span class="pa-hero-score-label">Grade Final</span>
                            <div
                                class="pa-hero-grade-badge"
                                :class="`is-${getGradeVariant(gradeTampil)}`"
                            >
                                {{ displayGrade(gradeTampil) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Held Banner Alert -->
                <div v-if="detail.peserta.Alasan_Tertahan" class="pa-held-banner">
                    <HcisIcon name="hourglass-split" size="md" animation="flip" />
                    <div>
                        <strong>Hasil tertahan</strong>
                        <span class="pa-held-banner__reason" :title="detail.peserta.Alasan_Tertahan">
                            {{ detail.peserta.Alasan_Tertahan }}
                        </span>
                    </div>
                </div>

                <!-- Recalculation Alert -->
                <div v-if="hasilHitung" class="pa-recalculation">
                    <HcisIcon
                        :name="hasilHitung.status === 'TERTAHAN' ? 'hourglass-split' : 'check-circle-fill'"
                        size="sm"
                        :color="hasilHitung.status === 'TERTAHAN' ? 'warning' : 'success'"
                    />
                    <span>
                        {{ hasilHitung.status === 'TERTAHAN' ? 'Perhitungan selesai, hasil tertahan' : 'Perhitungan peserta berhasil' }}
                        · {{ hasilHitung.status }} · versi {{ hasilHitung.versi }} · final score
                        <strong>{{ formatScore(hasilHitung.final_score) }}</strong>
                    </span>
                </div>

                <!-- Recalculation Inline Held Alert -->
                <div v-if="hasilHitung?.alasan_tertahan" class="pa-held-inline" role="status">
                    <HcisIcon name="info-circle" size="xs" color="warning" />
                    <span>{{ hasilHitung.alasan_tertahan }}</span>
                </div>

                <!-- Tab Navigation Switcher -->
                <HcisTabs
                    v-model="activeTab"
                    :tabs="daftarTabDialog"
                    variant="pills"
                    size="sm"
                    class="pa-result__tabs"
                />

                <!-- ==================== SECTION 1: RINGKASAN & SKOR ==================== -->
                <div v-show="activeTab === 'ringkasan' || activeTab === 'semua'" class="pa-section-pane">
                    <!-- 4-Stat Cockpit Cards -->
                    <HcisStatGroup
                        :stats="ringkasanStats"
                        :cols="isMobile ? 2 : 4"
                        gap="xs"
                        :layout="isMobile ? 'dense' : 'compact'"
                        appearance="crystal"
                    />

                    <!-- Pre-Cap Callout Note -->
                    <div
                        v-if="
                            detail.peserta.Nilai_KPI_Sebelum_Cap !== null &&
                            detail.peserta.Nilai_KPI_Sebelum_Cap !== undefined
                        "
                        class="pa-pre-cap-note"
                    >
                        <HcisIcon name="info-circle-fill" size="xs" color="brand" />
                        <span>
                            Nilai KPI sebelum cap: <strong>{{ formatScore(detail.peserta.Nilai_KPI_Sebelum_Cap) }}</strong>.
                            Angka ini dipertahankan server untuk menjelaskan capaian sebelum pembatasan akhir.
                        </span>
                    </div>

                    <!-- Dual Grid: Radar Chart & Kategori Readiness -->
                    <div class="pa-radar-category-grid">
                        <HcisChart
                            v-if="radarKompetensi.length"
                            type="radar"
                            title="Radar Kompetensi 360°"
                            kicker="RADAR EVALUASI · TALENT PROFILE"
                            :description="radarDeskripsi"
                            icon="pentagon-fill"
                            sentiment="purple"
                            variant="glass"
                            pattern="rings"
                            :height="isMobile ? 320 : 380"
                            :categories="radarCategories"
                            :data="radarData"
                            :series="radarSeries"
                            :metric-value="`${radarKompetensi.length} Dimensi`"
                            metric-label="Cakupan Penilaian"
                            allow-export
                        />
                        <HcisCard
                            v-else
                            title="Radar Kompetensi 360°"
                            kicker="RADAR EVALUASI · TALENT PROFILE"
                            description="Analisis visual sebaran pencapaian kompetensi agregat lintas kategori."
                            icon="pentagon-fill"
                            variant="glass"
                            sentiment="purple"
                        >
                            <HcisEmptyState
                                title="Belum ada data kompetensi"
                                description="Data agregat kompetensi belum tersedia untuk peserta ini."
                                icon="bar-chart-line"
                                size="sm"
                                variant="flat"
                                sentiment="neutral"
                            />
                        </HcisCard>

                        <HcisCard
                            title="Penyelesaian per kategori"
                            kicker="PROGRES PENILAIAN · MULTI-RATER"
                            description="Nilai dan status pengisian kuesioner per kategori penilai."
                            icon="layers"
                            variant="glass"
                            sentiment="purple"
                        >
                            <div v-if="detail.kategori?.length" class="pa-category-table">
                                <!-- Aggregate Participation Summary Bar -->
                                <div v-if="totalKategoriRater.expected > 0" class="pa-cat-summary-strip">
                                    <div class="pa-cat-summary-strip__left">
                                        <div class="pa-cat-summary-strip__icon" :class="{ 'is-complete': totalKategoriRater.isComplete }">
                                            <HcisIcon :name="totalKategoriRater.isComplete ? 'check-circle-fill' : 'pie-chart-fill'" size="xs" />
                                        </div>
                                        <div class="pa-cat-summary-strip__info">
                                            <span class="pa-cat-summary-strip__label">Total Partisipasi Penilai</span>
                                            <strong class="pa-cat-summary-strip__val">
                                                {{ totalKategoriRater.filled }} dari {{ totalKategoriRater.expected }} Rater
                                            </strong>
                                        </div>
                                    </div>
                                    <div class="pa-cat-summary-strip__right">
                                        <div class="pa-cat-summary-strip__bar-wrap">
                                            <div class="pa-cat-summary-strip__bar">
                                                <div
                                                    class="pa-cat-summary-strip__bar-fill"
                                                    :class="{ 'is-complete': totalKategoriRater.isComplete }"
                                                    :style="{ width: `${totalKategoriRater.pct}%` }"
                                                />
                                            </div>
                                            <span class="pa-cat-summary-strip__pct">{{ totalKategoriRater.pct }}%</span>
                                        </div>
                                        <HcisBadge
                                            v-if="totalKategoriRater.isComplete"
                                            label="Semua Rater Selesai"
                                            variant="success"
                                            appearance="subtle"
                                            size="2xs"
                                        />
                                    </div>
                                </div>

                                <!-- Category Item Cards -->
                                <div
                                    v-for="category in detail.kategori"
                                    :key="category.Kode_Kategori"
                                    class="pa-category-row"
                                    :class="`pa-category-row--${getCategorySentiment(category.Kode_Kategori)}`"
                                >
                                    <div class="pa-category-row__main">
                                        <div class="pa-cat-jewel" :class="`is-${getCategorySentiment(category.Kode_Kategori)}`">
                                            <HcisIcon :name="getCategoryIcon(category.Kode_Kategori)" size="sm" />
                                        </div>
                                        <div class="pa-category-row__left">
                                            <div class="pa-category-row__title">
                                                <strong>{{ category.Nama_Kategori || category.Kode_Kategori }}</strong>
                                                <span class="pa-cat-code-pill">{{ category.Kode_Kategori }}</span>
                                                <HcisBadge
                                                    v-if="(category.Jumlah_Rater_Mengisi ?? 0) >= (category.Jumlah_Rater_Diharapkan ?? 0) && (category.Jumlah_Rater_Diharapkan ?? 0) > 0"
                                                    label="Lengkap"
                                                    variant="success"
                                                    appearance="subtle"
                                                    size="2xs"
                                                />
                                                <HcisBadge
                                                    v-else-if="(category.Jumlah_Rater_Mengisi ?? 0) > 0"
                                                    label="Sebagian"
                                                    variant="warning"
                                                    appearance="subtle"
                                                    size="2xs"
                                                />
                                                <HcisBadge
                                                    v-else
                                                    label="Belum Ada"
                                                    variant="neutral"
                                                    appearance="subtle"
                                                    size="2xs"
                                                />
                                            </div>
                                            <!-- Completion meter & count -->
                                            <div class="pa-category-row__meter-wrap">
                                                <div class="pa-category-row__meter">
                                                    <div
                                                        class="pa-category-row__meter-fill"
                                                        :class="[
                                                            `is-${getCategorySentiment(category.Kode_Kategori)}`,
                                                            { 'is-complete': (category.Jumlah_Rater_Mengisi ?? 0) >= (category.Jumlah_Rater_Diharapkan ?? 0) && (category.Jumlah_Rater_Diharapkan ?? 0) > 0 }
                                                        ]"
                                                        :style="{ width: `${getCategoryProgressPercent(category)}%` }"
                                                    />
                                                </div>
                                                <div class="pa-category-row__progress-row">
                                                    <span class="pa-category-row__count">
                                                        {{ category.Jumlah_Rater_Mengisi ?? 0 }}/{{ category.Jumlah_Rater_Diharapkan ?? 0 }} rater mengisi
                                                    </span>
                                                    <span class="pa-category-row__pct">
                                                        ({{ getCategoryProgressPercent(category) }}%)
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pa-category-row__right">
                                        <div class="pa-cat-score-meta">
                                            <span class="pa-cat-score-label">Skor Rata-rata</span>
                                            <strong class="pa-cat-score-val">{{ formatScore(category.Nilai) }}</strong>
                                        </div>
                                        <HcisBadge
                                            v-if="category.Bobot_Terpakai !== null && category.Bobot_Terpakai !== undefined"
                                            :label="`${formatScore(category.Bobot_Terpakai)}% bobot`"
                                            variant="neutral"
                                            appearance="subtle"
                                            size="xs"
                                        />
                                    </div>
                                </div>
                            </div>
                            <HcisEmptyState
                                v-else
                                title="Belum ada kategori"
                                description="Belum ada jejak kategori penilai untuk peserta ini."
                                icon="layers"
                                size="sm"
                                variant="flat"
                                sentiment="neutral"
                            />
                        </HcisCard>
                    </div>

                    <!-- Dedicated Card: Bobot yang Berlaku (Full Width Grid Standalone) -->
                    <div v-if="rincianBobotBerlaku" class="pa-bobot-container">
                        <HcisCard
                            title="Bobot yang berlaku"
                            kicker="DISTRIBUSI KOMPETENSI · FORMULA AKHIR"
                            description="Bagian tiap penilai dari nilai kompetensi, sesuai perhitungan terakhir."
                            icon="pie-chart-fill"
                            variant="glass"
                            sentiment="purple"
                            class="pa-bobot-card"
                        >
                            <template #badge>
                                <HcisBadge
                                    v-if="rincianBobotBerlaku.bergeser"
                                    label="Penyesuaian Bobot"
                                    variant="warning"
                                    size="2xs"
                                    dot
                                />
                                <HcisBadge
                                    v-else-if="rincianBobotBerlaku.adaYangBerlaku"
                                    label="Sesuai Rencana"
                                    variant="success"
                                    size="2xs"
                                    dot
                                />
                            </template>

                            <div class="pa-bobot-berlaku" aria-label="Bobot yang berlaku">
                                <!-- Info Alert Saat Ada Kategori yang Gugur/Tidak Dihitung -->
                                <HcisAlert
                                    v-if="rincianBobotBerlaku.bergeser"
                                    variant="warning"
                                    appearance="subtle"
                                    size="sm"
                                    icon="info-circle-fill"
                                    class="mb-3"
                                >
                                    <p class="pa-bobot-berlaku__catatan mb-0">
                                        Bobot berbeda dari rencana: kategori yang tidak memenuhi minimal pengisi tidak dihitung, dan porsinya dibagikan secara proporsional ke kategori lain.
                                    </p>
                                </HcisAlert>

                                <HcisAlert
                                    v-else-if="!rincianBobotBerlaku.adaYangBerlaku"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="sm"
                                    icon="hourglass-split"
                                    class="mb-3"
                                >
                                    <p class="pa-bobot-berlaku__catatan mb-0">
                                        Belum ada bobot yang berlaku. Hasil peserta ini belum terhitung.
                                    </p>
                                </HcisAlert>

                                <!-- Tabel Distribusi Bobot -->
                                <div class="pa-bobot-berlaku__tabel-container">
                                    <table class="pa-bobot-berlaku__tabel">
                                        <thead>
                                            <tr>
                                                <th scope="col">Kategori / Penilai</th>
                                                <th scope="col" class="is-angka">Rencana</th>
                                                <th scope="col" class="is-angka">Berlaku</th>
                                            </tr>
                                        </thead>
                                        <tbody v-for="baris in rincianBobotBerlaku.baris" :key="`bobot-${baris.kode}`">
                                            <!-- Baris Header Kategori -->
                                            <tr class="pa-bobot-berlaku__kategori" :class="{ 'is-excluded': !baris.dihitung }">
                                                <th scope="row">
                                                    <div class="pa-bobot-cat-head">
                                                        <span class="pa-bobot-cat-nama">{{ baris.nama }}</span>
                                                        <span v-if="!baris.dihitung" class="pa-bobot-cat-badge">
                                                            <HcisIcon name="slash-circle" size="2xs" />
                                                            {{ baris.alasanTidakDiikutkan ? `sengaja tidak diikutkan · ${baris.alasanTidakDiikutkan}` : 'tidak dihitung' }}<template v-if="baris.minimal"> · {{ baris.mengisi }} dari minimal {{ baris.minimal }} mengisi</template>
                                                        </span>
                                                        <span v-else class="pa-bobot-cat-valid">
                                                            <HcisIcon name="check-circle-fill" size="2xs" />
                                                            {{ baris.mengisi }} rater mengisi
                                                        </span>
                                                    </div>
                                                </th>
                                                <td class="is-angka">
                                                    <span class="pa-bobot-pct-rencana">
                                                        {{ baris.rencana === null ? '—' : formatPersen(baris.rencana) }}
                                                    </span>
                                                </td>
                                                <td class="is-angka">
                                                    <strong class="pa-bobot-pct-berlaku" :class="{ 'is-zero': !baris.dihitung }">
                                                        {{ baris.dihitung ? formatPersen(baris.berlaku) : '0%' }}
                                                    </strong>
                                                </td>
                                            </tr>

                                            <!-- Baris Rater / Penilai Individual -->
                                            <tr v-for="orang in baris.penilai" :key="`bobot-p-${orang.id}`" class="pa-bobot-berlaku__penilai">
                                                <th scope="row">
                                                    <div class="pa-bobot-penilai-cell">
                                                        <span class="pa-bobot-penilai-dot" />
                                                        <span class="pa-bobot-penilai-nama">{{ orang.nama }}</span>
                                                        <span v-if="orang.sebab" class="pa-bobot-berlaku__sebab">{{ orang.sebab }}</span>
                                                    </div>
                                                </th>
                                                <td class="is-angka" />
                                                <td class="is-angka">
                                                    <span class="pa-bobot-penilai-pct">
                                                        {{ orang.bagian === null ? '—' : formatPersen(orang.bagian) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th scope="row">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <strong>Total Bobot</strong>
                                                        <HcisBadge
                                                            :label="formatPersen(rincianBobotBerlaku.totalBerlaku) === '100%' ? '100% Sempurna' : 'Perlu Selaras'"
                                                            :variant="formatPersen(rincianBobotBerlaku.totalBerlaku) === '100%' ? 'success' : 'warning'"
                                                            appearance="subtle"
                                                            size="2xs"
                                                        />
                                                    </div>
                                                </th>
                                                <td class="is-angka">
                                                    <strong class="text-slate-600">{{ formatPersen(rincianBobotBerlaku.totalRencana) }}</strong>
                                                </td>
                                                <td class="is-angka">
                                                    <strong class="text-purple-700 font-extrabold">{{ formatPersen(rincianBobotBerlaku.totalBerlaku) }}</strong>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </HcisCard>
                    </div>

                    <!-- Wawasan 360 Insights (Full Width Container) -->
                    <div class="pa-wawasan-container">
                        <PaWawasanHasil :detail="detail" :mode-verifikasi="modeVerifikasi" />
                    </div>

                    <!-- Calibration Outdated Warning -->
                    <HcisAlert
                        v-if="detail.kalibrasi_perlu_ditinjau"
                        variant="warning"
                        title="Kalibrasi dibuat sebelum perhitungan terakhir"
                        description="Grade final hasil kalibrasi dipertahankan, tetapi nilai yang mendasarinya sudah dihitung ulang sesudahnya. Tinjau ulang keputusannya bila perlu."
                        class="pa-kalibrasi-basi"
                    />
                </div>

                <!-- ==================== SECTION 2: PENUGASAN PENILAI & KALIBRASI ==================== -->
                <div v-show="activeTab === 'penugasan' || activeTab === 'semua'" class="pa-section-pane">
                    <!-- Segmented Sub-Tab Switcher for Penugasan & Kalibrasi -->
                    <div class="pa-penugasan-subtabs-wrap">
                        <HcisTabs
                            v-model="penugasanSubTab"
                            :tabs="penugasanSubTabs"
                            variant="pills"
                            size="sm"
                            class="pa-penugasan-subtabs"
                        />
                    </div>

                    <!-- SUBTAB 1: DAFTAR PENUGASAN & KATEGORI -->
                    <div v-show="penugasanSubTab === 'daftar' || activeTab === 'semua'" class="pa-penugasan-subpane">
                        <HcisCard
                            title="Penugasan penilai"
                            kicker="DISTRIBUSI EVALUATOR · MULTI-RATER"
                            description="Daftar penilai yang ditugaskan per kategori, status pengisian, serta kontrol pembatalan penugasan."
                            icon="people-fill"
                            variant="glass"
                            sentiment="brand"
                        >
                            <div v-if="penilaiPerKategori.length" class="pa-assignment-table">
                                <!-- Overview Cockpit / Participation Header -->
                                <div class="pa-assign-cockpit">
                                    <div class="pa-assign-stat-card is-active">
                                        <div class="pa-assign-stat-icon is-active">
                                            <HcisIcon name="people-fill" size="xs" />
                                        </div>
                                        <div class="pa-assign-stat-info">
                                            <span class="pa-assign-stat-lbl">Penilai Aktif</span>
                                            <strong class="pa-assign-stat-num">{{ penilaiStatistik.totalAktif }} Orang</strong>
                                            <span class="pa-assign-stat-sub">Distribusi multi-rater</span>
                                        </div>
                                    </div>

                                    <div class="pa-assign-stat-card is-progress">
                                        <div class="pa-assign-stat-icon is-progress">
                                            <HcisIcon :name="penilaiStatistik.semuaSelesai ? 'check-circle-fill' : 'hourglass-split'" size="xs" />
                                        </div>
                                        <div class="pa-assign-stat-info">
                                            <span class="pa-assign-stat-lbl">Progres Pengisian</span>
                                            <div class="pa-assign-progress-val">
                                                <strong class="pa-assign-stat-num">{{ penilaiStatistik.totalSelesai }}/{{ penilaiStatistik.totalAktif }} Selesai</strong>
                                                <span class="pa-assign-stat-pct">({{ penilaiStatistik.persenSelesai }}%)</span>
                                            </div>
                                            <span class="pa-assign-stat-sub">Kuesioner evaluasi</span>
                                        </div>
                                    </div>

                                    <div
                                        class="pa-assign-stat-card"
                                        :class="penilaiStatistik.totalTerlambat > 0 ? 'is-danger' : penilaiStatistik.totalKategoriKritis > 0 ? 'is-warning' : 'is-success'"
                                    >
                                        <div
                                            class="pa-assign-stat-icon"
                                            :class="penilaiStatistik.totalTerlambat > 0 ? 'is-danger' : penilaiStatistik.totalKategoriKritis > 0 ? 'is-warning' : 'is-success'"
                                        >
                                            <HcisIcon
                                                :name="penilaiStatistik.totalTerlambat > 0 ? 'exclamation-circle-fill' : penilaiStatistik.totalKategoriKritis > 0 ? 'exclamation-triangle-fill' : 'shield-check'"
                                                size="xs"
                                            />
                                        </div>
                                        <div class="pa-assign-stat-info">
                                            <span class="pa-assign-stat-lbl">Status Penugasan</span>
                                            <strong class="pa-assign-stat-num">
                                                <template v-if="penilaiStatistik.totalTerlambat > 0">
                                                    {{ penilaiStatistik.totalTerlambat }} Terlambat
                                                </template>
                                                <template v-else-if="penilaiStatistik.totalKategoriKritis > 0">
                                                    {{ penilaiStatistik.totalKategoriKritis }} Kategori Kritis
                                                </template>
                                                <template v-else-if="penilaiStatistik.semuaSelesai">
                                                    Semua Selesai
                                                </template>
                                                <template v-else>
                                                    Sesuai Jadwal
                                                </template>
                                            </strong>
                                            <span class="pa-assign-stat-sub">Kepatuhan tenggat</span>
                                        </div>
                                    </div>

                                    <!-- Stat Card 4: Kalibrasi Penilai -->
                                    <div
                                        class="pa-assign-stat-card is-interactive"
                                        :class="totalKalibrasiPenilai > 0 ? 'is-purple' : ''"
                                        @click="penugasanSubTab = 'kalibrasi'"
                                    >
                                        <div class="pa-assign-stat-icon is-purple">
                                            <HcisIcon name="pencil-square" size="xs" />
                                        </div>
                                        <div class="pa-assign-stat-info">
                                            <span class="pa-assign-stat-lbl">Kalibrasi Penilai</span>
                                            <strong class="pa-assign-stat-num">{{ totalKalibrasiPenilai }} Revisi</strong>
                                            <span class="pa-assign-stat-sub">Klik lihat detail</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Category Sections -->
                                <section
                                    v-for="kategori in penilaiPerKategori"
                                    :key="`kategori-${kategori.kode}`"
                                    class="pa-rater-kategori-card"
                                    :class="`is-${getCategorySentiment(kategori.kode)}`"
                                >
                                    <header class="pa-rater-kategori__kepala">
                                        <div class="pa-rater-kategori__title-wrap">
                                            <div class="pa-cat-jewel" :class="`is-${getCategorySentiment(kategori.kode)}`">
                                                <HcisIcon :name="getCategoryIcon(kategori.kode)" size="2xs" />
                                            </div>
                                            <div class="pa-rater-kategori__name-block">
                                                <div class="pa-rater-kategori__name-line">
                                                    <strong>{{ kategori.nama }}</strong>
                                                    <span class="pa-cat-code-pill">{{ kategori.kode }}</span>
                                                </div>
                                                <span class="pa-rater-kategori__porsi">
                                                    {{
                                                        kategori.bobotKategori === null
                                                            ? 'porsi kategori tidak diketahui'
                                                            : `${formatPersen(kategori.bobotKategori)} dari nilai kompetensi`
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="pa-rater-kategori__ambang-wrap">
                                            <span
                                                class="pa-rater-kategori__ambang"
                                                :class="{ 'pa-rater-kategori__ambang--kurang': kategori.dibawahAmbang }"
                                            >
                                                <HcisIcon :name="kategori.dibawahAmbang ? 'exclamation-circle-fill' : 'person-check'" size="2xs" />
                                                {{ kategori.aktif }} penilai<template v-if="kategori.minSelesai">
                                                    · minimal {{ kategori.minSelesai }} menyelesaikan
                                                </template>
                                            </span>
                                        </div>
                                    </header>

                                    <div v-if="kategori.dibawahAmbang" class="pa-rater-kategori__peringatan">
                                        <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                                        <span>Di bawah ambang. Kategori ini akan dikeluarkan dari perhitungan dan porsinya dibagikan ke kategori lain.</span>
                                    </div>

                                    <div class="pa-rater-kategori__list">
                                        <div
                                            v-for="assignment in kategori.penilai"
                                            :key="assignment.Id_PA_Assignment"
                                            class="pa-assignment-row"
                                            :class="{ 'pa-assignment-row--cancelled': assignment.Status_Penugasan === 'DIBATALKAN' }"
                                        >
                                            <div
                                                class="pa-assignment-avatar"
                                                :class="{
                                                    'is-completed': assignment.Status_Penugasan === 'SELESAI',
                                                    'is-cancelled': assignment.Status_Penugasan === 'DIBATALKAN'
                                                }"
                                            >
                                                <HcisIcon
                                                    :name="assignment.Status_Penugasan === 'SELESAI' ? 'check-circle-fill' : assignment.Status_Penugasan === 'DIBATALKAN' ? 'x-circle-fill' : 'person-fill'"
                                                    size="xs"
                                                    :color="assignment.Status_Penugasan === 'SELESAI' ? 'success' : assignment.Status_Penugasan === 'DIBATALKAN' ? 'danger' : 'brand'"
                                                />
                                            </div>

                                            <div class="pa-assignment-row__info">
                                                <div class="pa-assignment-row__name-line">
                                                    <strong class="pa-assignment-row__name">
                                                        {{ assignment.Nama_Penilai_Saat_Cycle || assignment.Kode_Karyawan_Penilai }}
                                                    </strong>
                                                    <span v-if="assignment.Kode_Karyawan_Penilai" class="pa-assignment-nik-mono">
                                                        {{ assignment.Kode_Karyawan_Penilai }}
                                                    </span>
                                                    <!-- Kalibrasi Badge for Rater -->
                                                    <button
                                                        v-if="raterKalibrasiCount(assignment.Id_PA_Assignment) > 0"
                                                        type="button"
                                                        class="pa-rater-kalibrasi-pill"
                                                        title="Lihat riwayat kalibrasi & revisi rater ini"
                                                        @click.stop="lihatKalibrasiRater(assignment.Id_PA_Assignment)"
                                                    >
                                                        <HcisIcon name="pencil-square" size="2xs" />
                                                        <span>Direvisi ({{ raterKalibrasiCount(assignment.Id_PA_Assignment) }}×)</span>
                                                    </button>
                                                </div>
                                                <div class="pa-assignment-row__sub">
                                                    <span class="pa-weight-tag">{{ formatPersen(assignment.Bobot_Dalam_Kategori) }} dlm kategori</span>
                                                    <span>&bull;</span>
                                                    <span>Sumber: {{ assignment.Sumber_Penugasan || 'Sistem' }}</span>
                                                    <template v-if="assignment.Bobot_Kustom === 'Y'">
                                                        <span>&bull;</span>
                                                        <HcisBadge label="Bobot Kustom" variant="purple" appearance="subtle" size="2xs" />
                                                    </template>
                                                </div>
                                            </div>

                                            <div class="pa-assignment-row__status">
                                                <HcisStatusBadge
                                                    :status="assignment.Status_Penugasan"
                                                    :label="assignment.Status_Penugasan"
                                                    size="xs"
                                                />
                                            </div>

                                            <div class="pa-assignment-row__meta">
                                                <span v-if="assignment.Due_At" class="pa-due-text">
                                                    <HcisIcon name="calendar-event" size="2xs" />
                                                    Tenggat {{ assignment.Due_At }}
                                                </span>
                                                <HcisBadge
                                                    v-if="assignment.terlambat"
                                                    :label="`${assignment.hari_terlambat || 1} hari terlambat`"
                                                    variant="danger"
                                                    appearance="subtle"
                                                    size="xs"
                                                    dot
                                                />
                                            </div>

                                            <div class="pa-assignment-row__action">
                                                <HcisButton
                                                    v-if="canAssign && assignment.Status_Penugasan !== 'DIBATALKAN'"
                                                    label="Batalkan"
                                                    variant="danger"
                                                    appearance="subtle"
                                                    size="xs"
                                                    leading-icon="x-circle"
                                                    @click="bukaPembatalan(assignment)"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                            <HcisEmptyState
                                v-else
                                title="Belum ada penugasan"
                                description="Peserta ini belum memiliki penilai yang ditugaskan."
                                icon="people"
                                size="sm"
                                variant="flat"
                                sentiment="neutral"
                            />
                        </HcisCard>
                    </div>

                    <!-- SUBTAB 2: RIWAYAT KALIBRASI PENILAI -->
                    <div v-show="penugasanSubTab === 'kalibrasi' || activeTab === 'semua'" class="pa-penugasan-subpane">
                        <HcisCard
                            title="Riwayat Kalibrasi Penilai"
                            kicker="AUDIT LOG · REVISI MANDIRI RATER"
                            description="Catatan penyesuaian skor kompetensi dan jawaban esai kualitatif yang dilakukan oleh penilai sendiri sebelum menyelesaikan penilaian."
                            icon="pencil-square"
                            variant="glass"
                            sentiment="purple"
                        >
                            <PaRiwayatKalibrasiPenilai
                                :riwayat="detail.kalibrasi_penilai || []"
                                :filter-assignment-id="selectedKalibrasiAssignmentId"
                                :show-empty="true"
                            />
                        </HcisCard>
                    </div>
                </div>

                <!-- ==================== SECTION 3: QUALITATIVE FEEDBACK & ADMIN NOTES (UNIFIED) ==================== -->
                <div v-show="activeTab === 'naratif' || activeTab === 'catatan' || activeTab === 'semua'" class="pa-section-pane">
                    <!-- 1. Executive Qualitative Cockpit Banner -->
                    <div class="pa-naratif-cockpit">
                        <div class="pa-naratif-stat-card pa-naratif-stat-card--primary">
                            <div class="pa-naratif-stat-icon is-purple">
                                <HcisIcon name="chat-square-quote-fill" size="sm" />
                            </div>
                            <div class="pa-naratif-stat-info">
                                <span class="pa-naratif-stat-label">Partisipasi Kuesioner</span>
                                <div class="pa-naratif-stat-val-row">
                                    <strong class="pa-naratif-stat-num">{{ naratifTerjawab }} dari {{ naratifDiminta }} terjawab</strong>
                                    <span class="pa-naratif-stat-pct">({{ tingkatPartisipasiNaratif }}%)</span>
                                </div>
                                <div class="pa-naratif-stat-badge-row">
                                    <HcisBadge
                                        :label="tingkatPartisipasiNaratif >= 100 ? 'Semua Terjawab' : tingkatPartisipasiNaratif > 0 ? 'Sebagian Terjawab' : 'Belum Terjawab'"
                                        :variant="tingkatPartisipasiNaratif >= 100 ? 'success' : tingkatPartisipasiNaratif > 0 ? 'warning' : 'neutral'"
                                        appearance="subtle"
                                        size="2xs"
                                        dot
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="pa-naratif-stat-card">
                            <div class="pa-naratif-stat-icon is-teal">
                                <HcisIcon name="chat-dots-fill" size="sm" />
                            </div>
                            <div class="pa-naratif-stat-info">
                                <span class="pa-naratif-stat-label">Total Masukan Rater</span>
                                <div class="pa-naratif-stat-val-row">
                                    <strong class="pa-naratif-stat-num">{{ totalJawabanNaratif }}</strong>
                                    <span class="pa-naratif-stat-unit">Teks Masukan</span>
                                </div>
                                <span class="pa-naratif-stat-sub">Dari seluruh evaluator penilai</span>
                            </div>
                        </div>

                        <div class="pa-naratif-stat-card">
                            <div class="pa-naratif-stat-icon is-brand">
                                <HcisIcon name="journal-bookmark-fill" size="sm" />
                            </div>
                            <div class="pa-naratif-stat-info">
                                <span class="pa-naratif-stat-label">Dimensi Kesimpulan</span>
                                <div class="pa-naratif-stat-val-row">
                                    <strong class="pa-naratif-stat-num">{{ kategoriCatatan.length }}</strong>
                                    <span class="pa-naratif-stat-unit">Dimensi Catatan</span>
                                </div>
                                <span class="pa-naratif-stat-sub">Catatan resmi report akhir</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Workflow Explanation Guide Banner -->
                    <div class="pa-naratif-guide-banner">
                        <div class="pa-naratif-guide-icon">
                            <HcisIcon name="info-circle-fill" size="sm" color="brand" />
                        </div>
                        <div class="pa-naratif-guide-text">
                            <strong>Alur Penyusunan Ringkasan Catatan:</strong>
                            <span>
                                Tinjau seluruh <strong>jawaban & observasi penilai</strong> (Atasan, Peer, Bawahan, Self) pada panel kiri, lalu tuliskan <strong>ringkasan kesimpulan evaluasi resmi</strong> pada panel kanan untuk tiap dimensi. Klik tombol <code>+ Kutip ke Ringkasan</code> pada jawaban rater untuk menyalin poin penting secara instan.
                            </span>
                        </div>
                    </div>

                    <!-- Sub-Tab Navigation Bar: Segmented Pill Tabs per Dimensi -->
                    <div v-if="daftarDimensiKualitatif.length > 1" class="pa-dimensi-pills-bar">
                        <div class="pa-dimensi-pills-scroll">
                            <!-- Option: Semua Dimensi -->
                            <button
                                type="button"
                                class="pa-dimensi-pill"
                                :class="{ 'is-active': selectedDimensiKode === 'ALL' }"
                                @click="selectedDimensiKode = 'ALL'"
                            >
                                <HcisIcon name="grid-fill" size="2xs" />
                                <span class="pa-dimensi-pill__name">Semua Dimensi</span>
                                <span class="pa-dimensi-pill__badge">{{ daftarDimensiKualitatif.length }}</span>
                            </button>

                            <!-- Options: Per Dimension -->
                            <button
                                v-for="dim in daftarDimensiKualitatif"
                                :key="`dim-tab-${dim.kode}`"
                                type="button"
                                class="pa-dimensi-pill"
                                :class="[
                                    `is-${dim.sentiment}`,
                                    { 'is-active': selectedDimensiKode === dim.kode },
                                    { 'is-filled': dim.isFilled }
                                ]"
                                @click="selectedDimensiKode = dim.kode"
                            >
                                <span
                                    class="pa-dimensi-pill__status-dot"
                                    :class="{ 'is-filled': dim.isFilled }"
                                    :title="dim.isFilled ? 'Sudah ada ringkasan admin' : 'Belum ada ringkasan admin'"
                                />
                                <HcisIcon :name="dim.icon" size="2xs" />
                                <span class="pa-dimensi-pill__name">{{ dim.nama }}</span>
                                <span v-if="dim.raterTerjawab > 0" class="pa-dimensi-pill__count" title="Jumlah masukan penilai">
                                    {{ dim.raterTerjawab }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- 3. Dual-Pane Split Workspace: Rater Feedback Explorer (Left) & Admin Notes Studio (Right) -->
                    <div
                        v-if="naratifPerPertanyaan.length || kategoriCatatan.length"
                        class="pa-kualitatif-workspace"
                        :class="{ 'pa-kualitatif-workspace--single': !kategoriCatatan.length }"
                    >
                        <!-- Left Column: Qualitative Questions & Rater Feedback Transcripts -->
                        <div class="pa-kualitatif-workspace__left">
                            <HcisCard
                                variant="glass"
                                sentiment="purple"
                                class="pa-kualitatif-card"
                            >
                                <template #header>
                                    <div class="pa-kualitatif-card-header">
                                        <!-- Top Row: Icon, Title, and Collapse All Button -->
                                        <div class="pa-kualitatif-card-header__top">
                                            <div class="pa-kualitatif-card-header__title-group">
                                                <div class="pa-kualitatif-card-header__icon">
                                                    <HcisIcon name="chat-left-quote-fill" size="sm" />
                                                </div>
                                                <div class="pa-kualitatif-card-header__text">
                                                    <span class="pa-kualitatif-card-header__kicker">TRANSKRIP MULTI-RATER · BAHAN RUJUKAN</span>
                                                    <h3 class="pa-kualitatif-card-header__title">1. Masukan &amp; Jawaban Penilai</h3>
                                                </div>
                                            </div>

                                            <div v-if="filteredNaratifPerPertanyaan.length" class="pa-kualitatif-card-header__actions">
                                                <HcisButton
                                                    :label="semuaNaratifTerbuka ? 'Tutup Semua' : 'Buka Semua'"
                                                    :leading-icon="semuaNaratifTerbuka ? 'chevron-bar-contract' : 'chevron-bar-expand'"
                                                    variant="secondary"
                                                    appearance="subtle"
                                                    size="xs"
                                                    @click="toggleSemuaNaratif"
                                                />
                                            </div>
                                        </div>

                                        <!-- Description -->
                                        <p class="pa-kualitatif-card-header__desc">
                                            Seluruh jawaban terbuka dan observasi dari Atasan, Rekan Kerja (Peer), Bawahan, dan Diri Sendiri sebagai bahan perumusan ringkasan.
                                        </p>

                                        <!-- Bottom Row: Filter Category Chips -->
                                        <div v-if="filteredNaratifPerPertanyaan.length && naratifCategories.length > 1" class="pa-kualitatif-card-header__chips-bar">
                                            <span class="pa-kualitatif-chips-label">Filter Penilai:</span>
                                            <div class="pa-naratif-filter-chips">
                                                <button
                                                    type="button"
                                                    class="pa-naratif-chip"
                                                    :class="{ 'is-active': selectedNaratifCategory === 'ALL' }"
                                                    @click="selectedNaratifCategory = 'ALL'"
                                                >
                                                    Semua ({{ totalJawabanNaratif }})
                                                </button>
                                                <button
                                                    v-for="cat in naratifCategories"
                                                    :key="cat.kode"
                                                    type="button"
                                                    class="pa-naratif-chip"
                                                    :class="{ 'is-active': selectedNaratifCategory === cat.kode }"
                                                    @click="selectedNaratifCategory = cat.kode"
                                                >
                                                    {{ cat.nama }} ({{ cat.jumlah }})
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div v-if="filteredNaratifPerPertanyaan.length" class="pa-naratif-competencies-list">
                                    <template
                                        v-for="grup in filteredNaratifPerPertanyaan"
                                        :key="`naratif-grup-${grup.kode}`"
                                    >
                                        <div
                                            v-show="isGrupMatchDimensi(grup.kode, grup.nama, selectedDimensiKode)"
                                            class="pa-naratif-group-card"
                                            :class="{ 'is-expanded': grupTerbuka[grup.kode] !== false }"
                                        >
                                            <!-- Accordion Header -->
                                            <div
                                                class="pa-naratif-group-header"
                                                role="button"
                                                tabindex="0"
                                                :aria-expanded="grupTerbuka[grup.kode] !== false"
                                                @click="toggleGrupNaratif(grup.kode)"
                                                @keydown.enter="toggleGrupNaratif(grup.kode)"
                                            >
                                                <div class="pa-naratif-group-header-left">
                                                    <div class="pa-naratif-group-icon">
                                                        <HcisIcon name="journal-text" size="xs" />
                                                    </div>
                                                    <div class="pa-naratif-group-title-info">
                                                        <strong class="pa-naratif-group-title">{{ grup.nama }}</strong>
                                                        <span class="pa-naratif-group-sub">{{ grup.soal.length }} butir pertanyaan kualitatif</span>
                                                    </div>
                                                </div>

                                                <div class="pa-naratif-group-header-right">
                                                    <HcisBadge
                                                        :label="`${grup.terjawab} / ${grup.diminta} Rater`"
                                                        :variant="grup.terjawab >= grup.diminta && grup.diminta > 0 ? 'success' : grup.terjawab > 0 ? 'warning' : 'neutral'"
                                                        appearance="subtle"
                                                        size="xs"
                                                        dot
                                                    />
                                                    <div
                                                        class="pa-naratif-chevron-btn"
                                                        :class="{ 'is-open': grupTerbuka[grup.kode] !== false }"
                                                        aria-hidden="true"
                                                    >
                                                        <HcisIcon name="chevron-down" size="xs" />
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Accordion Body -->
                                            <div v-show="grupTerbuka[grup.kode] !== false" class="pa-naratif-group-body">
                                                <div
                                                    v-for="soal in grup.soal"
                                                    :key="`naratif-soal-${soal.kode}`"
                                                    class="pa-naratif-question-block"
                                                >
                                                    <!-- Question Prompt Header -->
                                                    <div class="pa-question-prompt-header">
                                                        <div class="pa-question-prompt-left">
                                                            <span class="pa-question-cat-badge">
                                                                <HcisIcon name="tag-fill" size="2xs" />
                                                                {{ soal.kategori }}
                                                            </span>
                                                            <code class="pa-question-code">{{ soal.kode }}</code>
                                                        </div>
                                                        <span class="pa-question-stat">
                                                            <strong>{{ soal.terjawab }}</strong> dari <strong>{{ soal.diminta }}</strong> rater mengisi
                                                        </span>
                                                    </div>

                                                    <!-- Answers List -->
                                                    <div v-if="soal.jawaban.length" class="pa-answers-bubble-grid">
                                                        <div
                                                            v-for="(j, idx) in soal.jawaban"
                                                            :key="`naratif-${soal.kode}-${idx}`"
                                                            class="pa-answer-bubble-card"
                                                        >
                                                            <div class="pa-answer-bubble-header">
                                                                <div class="pa-rater-author-info">
                                                                    <div class="pa-rater-avatar-chip">
                                                                        <span>{{ inisialKaryawan(j.Nama_Penilai_Saat_Cycle || 'P') }}</span>
                                                                    </div>
                                                                    <div class="pa-rater-name-meta">
                                                                        <strong class="pa-rater-name">{{ j.Nama_Penilai_Saat_Cycle || 'Penilai' }}</strong>
                                                                        <span class="pa-rater-category-pill" :class="`is-${getCategorySentiment(j.Kode_Kategori)}`">
                                                                            {{ j.Nama_Kategori || j.Kode_Kategori || 'Evaluator' }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="pa-answer-header-actions">
                                                                    <HcisBadge
                                                                        label="Terisi"
                                                                        variant="success"
                                                                        appearance="subtle"
                                                                        size="2xs"
                                                                        dot
                                                                    />
                                                                    <button
                                                                        v-if="kategoriCatatan.length"
                                                                        type="button"
                                                                        class="pa-quote-insert-btn"
                                                                        title="Kutip masukan penilai ini ke kotak ringkasan admin di sebelah kanan"
                                                                        @click="kutipJawaban(j, grup.kode, grup.nama)"
                                                                    >
                                                                        <HcisIcon name="quote" size="2xs" />
                                                                        <span>+ Kutip ke Ringkasan</span>
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <div class="pa-answer-bubble-quote">
                                                                <HcisIcon name="quote" size="sm" class="pa-answer-quote-icon" />
                                                                <p v-if="j.Pertanyaan" class="pa-answer-question">{{ j.Pertanyaan }}</p>
                                                                <p class="pa-answer-text">{{ j.Jawaban_Teks }}</p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Empty / Incomplete State for this question -->
                                                    <div v-else class="pa-question-empty-notice">
                                                        <HcisIcon name="hourglass-split" size="xs" color="muted" />
                                                        <span>Belum dijawab oleh penilai.</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Notice when selected dimension has no rater questions in left pane -->
                                    <div
                                        v-if="selectedDimensiKode !== 'ALL' && !hasMatchingGrupForDimensi(selectedDimensiKode)"
                                        class="pa-dimensi-no-questions-notice"
                                    >
                                        <div class="pa-dimensi-no-questions-icon">
                                            <HcisIcon name="info-circle-fill" size="xs" color="brand" />
                                        </div>
                                        <div class="pa-dimensi-no-questions-text">
                                            <strong>Tidak ada butir pertanyaan rater khusus untuk dimensi ini.</strong>
                                            <span>Anda dapat langsung merumuskan ringkasan kesimpulan evaluasi pada editor di panel kanan atau beralih ke dimensi lain.</span>
                                        </div>
                                    </div>
                                </div>

                                <HcisEmptyState
                                    v-else
                                    title="Belum Ada Masukan Penilai"
                                    description="Belum ada rekaman jawaban kuesioner kualitatif dari penilai untuk peserta ini."
                                    icon="chat-square-dots"
                                    size="sm"
                                    variant="flat"
                                    sentiment="neutral"
                                />
                            </HcisCard>
                        </div>

                        <!-- Right Column: Admin Qualitative Evaluation Notes Studio -->
                        <div v-if="kategoriCatatan.length" class="pa-kualitatif-workspace__right pa-notes--admin">
                            <HcisCard
                                title="2. Input Ringkasan & Catatan Admin"
                                kicker="EXECUTIVE SUMMARY · REPORT AKHIR"
                                description="Tuliskan ulasan kesimpulan resmi per dimensi hasil rangkuman dari masukan penilai di sebelah kiri."
                                icon="pencil-square"
                                variant="glass"
                                sentiment="brand"
                                class="pa-notes-studio-card"
                            >
                                <!-- Success Alert Feedback -->
                                <HcisAlert
                                    v-if="suksesSimpanCatatan"
                                    variant="success"
                                    appearance="subtle"
                                    closable
                                    @close="suksesSimpanCatatan = ''"
                                >
                                    <template #title>Tersimpan ke Report Peserta</template>
                                    {{ suksesSimpanCatatan }}
                                </HcisAlert>

                                <!-- Grid of Rich Text Editor Cards per Dimension -->
                                <div class="pa-notes__form-grid" :class="{ 'pa-notes__form-grid--single': selectedDimensiKode !== 'ALL' }">
                                    <div
                                        v-for="(kategori, idx) in kategoriCatatan"
                                        v-show="selectedDimensiKode === 'ALL' || selectedDimensiKode === kategori.Kode_Competency"
                                        :key="`catatan-${kategori.Kode_Competency}`"
                                        class="pa-notes__dimension-card"
                                        :class="`is-${getKategoriCatatanSentiment(kategori.Nama_Competency)}`"
                                    >
                                        <div class="pa-notes__dimension-header">
                                            <div class="pa-notes__dimension-title-group">
                                                <div class="pa-notes__dimension-icon-badge">
                                                    <HcisIcon :name="getKategoriCatatanIcon(kategori.Nama_Competency)" size="xs" />
                                                </div>
                                                <div class="pa-notes__dimension-meta">
                                                    <div class="pa-notes__dimension-name-row">
                                                        <h5 class="pa-notes__dimension-name">{{ kategori.Nama_Competency }}</h5>
                                                        <code class="pa-notes__dimension-code">{{ kategori.Kode_Competency }}</code>
                                                        <HcisBadge
                                                            v-if="Boolean((catatanAdmin[kategori.Kode_Competency] || '').trim())"
                                                            label="Terisi"
                                                            variant="success"
                                                            appearance="subtle"
                                                            size="2xs"
                                                            dot
                                                        />
                                                        <HcisBadge
                                                            v-else
                                                            label="Belum Diisi"
                                                            variant="neutral"
                                                            appearance="subtle"
                                                            size="2xs"
                                                        />
                                                    </div>
                                                    <span class="pa-notes__dimension-guide">
                                                        {{ getKategoriCatatanGuideline(kategori.Nama_Competency) }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="pa-notes__dimension-actions">
                                                <HcisButton
                                                    label="Template Poin"
                                                    variant="secondary"
                                                    appearance="subtle"
                                                    size="2xs"
                                                    leading-icon="list-ul"
                                                    :disabled="menyimpanCatatan"
                                                    @click="insertTemplatePoin(kategori.Kode_Competency)"
                                                />
                                            </div>
                                        </div>

                                        <div class="pa-notes__dimension-body">
                                            <HcisRichText
                                                :id="`pa-catatan-${kategori.Kode_Competency}`"
                                                v-model="catatanAdmin[kategori.Kode_Competency]"
                                                :placeholder="`Tuliskan kesimpulan evaluasi untuk ${kategori.Nama_Competency} di sini…`"
                                                :disabled="menyimpanCatatan"
                                                min-height="130px"
                                                max-height="300px"
                                            />
                                        </div>

                                        <!-- Dimension Card Footer Navigation / Progression -->
                                        <div class="pa-notes__dimension-card-footer">
                                            <div class="pa-notes__dimension-footer-info">
                                                <span class="pa-notes__dimension-step-tag">
                                                    Dimensi {{ idx + 1 }} dari {{ kategoriCatatan.length }}
                                                </span>
                                            </div>
                                            <div class="pa-notes__dimension-footer-nav">
                                                <HcisButton
                                                    v-if="hasNextDimensi(kategori.Kode_Competency) && selectedDimensiKode !== 'ALL'"
                                                    :label="`Lanjut ke ${getNextDimensiNama(kategori.Kode_Competency)}`"
                                                    trailing-icon="arrow-right"
                                                    variant="secondary"
                                                    appearance="subtle"
                                                    size="xs"
                                                    @click="keDimensiBerikutnya(kategori.Kode_Competency)"
                                                />
                                                <HcisButton
                                                    v-else-if="selectedDimensiKode !== 'ALL' && idx === kategoriCatatan.length - 1 && daftarDimensiKualitatif.length > 1"
                                                    label="Lihat Semua Dimensi"
                                                    leading-icon="grid-fill"
                                                    variant="secondary"
                                                    appearance="subtle"
                                                    size="xs"
                                                    @click="selectedDimensiKode = 'ALL'"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="galatCatatan" class="pa-notes__galat" role="alert">
                                    <HcisIcon name="exclamation-triangle-fill" size="xs" />
                                    <span>{{ galatCatatan }}</span>
                                </p>

                                <!-- Action Bar Footer -->
                                <div class="pa-notes__action-bar">
                                    <div class="pa-notes__action-hint">
                                        <HcisIcon name="info-circle" size="2xs" />
                                        <span>Format HTML dan teks disimpan otomatis ke database report.</span>
                                    </div>
                                    <HcisButton
                                        variant="primary"
                                        size="sm"
                                        leading-icon="check2-circle"
                                        :loading="menyimpanCatatan"
                                        @click="simpanCatatanAdmin"
                                    >
                                        Simpan ke Report Peserta
                                    </HcisButton>
                                </div>
                            </HcisCard>
                        </div>
                    </div>

                    <!-- 3. Global Empty State if completely empty -->
                    <HcisEmptyState
                        v-else
                        title="Belum Ada Qualitative Questions & Catatan"
                        description="Belum ada rekaman kuesioner kualitatif ataupun dimensi catatan evaluasi untuk peserta ini."
                        icon="chat-square-text"
                        size="md"
                        variant="flat"
                        sentiment="neutral"
                    />
                </div>

                <!-- ==================== RIWAYAT PERHITUNGAN (DDL-2026-168) ==================== -->
                <div v-show="activeTab === 'riwayat'" class="pa-section-pane">
                    <PaRiwayatPerhitungan
                        ref="riwayatRef"
                        :participant-id="props.participant?.Id_PA_Participant ?? props.participant?.id ?? null"
                        :aktif="activeTab === 'riwayat'"
                        :mode-verifikasi="modeVerifikasi"
                    />
                </div>

                <!-- ==================== SECTION 4: BALANCING (spec 2026-09-30) ==================== -->
                <div v-show="activeTab === 'balancing' || activeTab === 'semua'" class="pa-section-pane">
                    <!-- Loading State -->
                    <div v-if="balancing.memuat" class="pa-bal-state-box">
                        <HcisIcon name="arrow-repeat" size="sm" animation="spin" color="brand" />
                        <span>Memuat riwayat balancing forum komite…</span>
                    </div>

                    <!-- Error State -->
                    <HcisResultState
                        v-else-if="balancing.galat"
                        status="danger"
                        variant="flat"
                        layout="inline"
                        title="Gagal Memuat Riwayat Balancing"
                        :description="balancing.galat"
                        :show-retry="true"
                        @retry="bukaTabBalancing"
                    />

                    <!-- Empty State -->
                    <HcisEmptyState
                        v-else-if="!balancing.sesi.length && !balancing.jejak.length"
                        title="Belum Pernah Masuk Sesi Balancing"
                        description="Peserta ini belum memiliki riwayat sesi balancing atau penyesuaian skor oleh forum komite penilai."
                        icon="sliders"
                        size="md"
                        variant="flat"
                        sentiment="neutral"
                    />

                    <!-- Content Layout -->
                    <div v-else class="pa-bal-layout">
                        <!-- 1. Overview Cockpit -->
                        <div class="pa-bal-cockpit">
                            <div class="pa-bal-stat-card pa-bal-stat-card--primary">
                                <div class="pa-bal-stat-icon is-brand">
                                    <HcisIcon name="sliders" size="xs" />
                                </div>
                                <div class="pa-bal-stat-info">
                                    <span class="pa-bal-stat-label">Total Sesi Balancing</span>
                                    <strong class="pa-bal-stat-num">{{ balancing.sesi.length }} Sesi</strong>
                                    <span class="pa-bal-stat-sub">Riwayat komite penilai</span>
                                </div>
                            </div>
                            <div class="pa-bal-stat-card">
                                <div class="pa-bal-stat-icon is-purple">
                                    <HcisIcon name="arrow-repeat" size="xs" />
                                </div>
                                <div class="pa-bal-stat-info">
                                    <span class="pa-bal-stat-label">Jejak Hitung Ulang</span>
                                    <strong class="pa-bal-stat-num">{{ balancing.jejak.length }}× Dihitung Ulang</strong>
                                    <span class="pa-bal-stat-sub">Penyesuaian grade & score</span>
                                </div>
                            </div>
                            <div class="pa-bal-stat-card">
                                <div class="pa-bal-stat-icon is-teal">
                                    <HcisIcon name="check-circle-fill" size="xs" />
                                </div>
                                <div class="pa-bal-stat-info">
                                    <span class="pa-bal-stat-label">Status Sesi Terkini</span>
                                    <strong class="pa-bal-stat-num">{{ balancing.sesi[0]?.status || 'Selesai' }}</strong>
                                    <span class="pa-bal-stat-sub">{{ balancing.sesi[0]?.hitung_ulang_at ? 'Sudah dihitung ulang' : 'Sesi terdaftar' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Section A: Sesi Balancing & Penyesuaian Kompetensi -->
                        <HcisCard
                            title="Sesi Balancing & Penyesuaian Skor"
                            kicker="FORUM KOMITE · PENILAIAN"
                            description="Daftar sesi balancing yang diikuti peserta beserta rincian kompetensi yang disesuaikan oleh tim komite penilai."
                            icon="people-fill"
                            variant="glass"
                            sentiment="brand"
                        >
                            <div v-if="balancing.sesi.length" class="pa-bal-sesi-list">
                                <div v-for="s in balancing.sesi" :key="s.id" class="pa-bal-sesi-card">
                                    <div class="pa-bal-sesi-header">
                                        <div class="pa-bal-sesi-title-group">
                                            <div class="pa-bal-sesi-icon">
                                                <HcisIcon name="sliders" size="xs" />
                                            </div>
                                            <div class="pa-bal-sesi-title-meta">
                                                <strong class="pa-bal-sesi-name">{{ s.nama }}</strong>
                                                <span v-if="s.hitung_ulang_at" class="pa-bal-sesi-date">
                                                    <HcisIcon name="clock-history" size="2xs" /> Dihitung ulang {{ formatWaktuBalancing(s.hitung_ulang_at) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="pa-bal-sesi-badges">
                                            <HcisBadge
                                                :label="s.status"
                                                :variant="s.status === 'FINAL' ? 'success' : s.status === 'DRAFT' ? 'warning' : s.status === 'DIBATALKAN' ? 'danger' : 'neutral'"
                                                appearance="subtle"
                                                size="xs"
                                            />
                                            <HcisBadge
                                                v-if="s.status_tinjau"
                                                :label="s.status_tinjau"
                                                variant="purple"
                                                appearance="subtle"
                                                size="xs"
                                            />
                                        </div>
                                    </div>
                                    <div class="pa-bal-sesi-body">
                                        <div v-if="kompetensiBerubah(s).length" class="pa-bal-komp-list">
                                            <div v-for="k in kompetensiBerubah(s)" :key="k.nama" class="pa-bal-komp-item">
                                                <div class="pa-bal-komp-top">
                                                    <div class="pa-bal-komp-title">
                                                        <HcisIcon name="award" size="2xs" />
                                                        <strong>{{ k.nama }}</strong>
                                                    </div>
                                                    <div class="pa-bal-komp-scores">
                                                        <span class="pa-bal-score-old">{{ formatScore(k.sebelum) }}</span>
                                                        <HcisIcon name="arrow-right" size="2xs" class="pa-bal-arrow" />
                                                        <strong class="pa-bal-score-new">{{ formatScore(k.sesudah) }}</strong>
                                                        <span
                                                            class="pa-bal-delta-tag"
                                                            :class="Number(k.sesudah) >= Number(k.sebelum) ? 'is-up' : 'is-down'"
                                                        >
                                                            {{ Number(k.sesudah) >= Number(k.sebelum) ? '+' : '' }}{{ formatScore(Number(k.sesudah) - Number(k.sebelum)) }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div v-if="k.alasan" class="pa-bal-komp-reason">
                                                    <HcisIcon name="chat-quote-fill" size="2xs" />
                                                    <span><strong>Alasan Penyesuaian:</strong> {{ k.alasan }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="pa-bal-komp-empty">
                                            <HcisIcon name="info-circle" size="xs" />
                                            <span>Tanpa perubahan skor kompetensi pada sesi ini (skor dipertahankan).</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <HcisEmptyState
                                v-else
                                title="Belum Ada Sesi Balancing"
                                description="Belum ada catatan sesi balancing untuk peserta ini."
                                icon="journal-x"
                                size="sm"
                                variant="flat"
                                sentiment="neutral"
                            />
                        </HcisCard>

                        <!-- 3. Section B: Jejak Hitung Ulang -->
                        <HcisCard
                            v-if="balancing.jejak.length"
                            title="Jejak Hitung Ulang Hasil"
                            kicker="AUDIT TRAIL · SKOR & GRADE"
                            description="Catatan riwayat perubahan Final Score, Grade, dan Kuadran dampak dari eksekusi balancing."
                            icon="clock-history"
                            variant="glass"
                            sentiment="purple"
                        >
                            <div class="pa-bal-jejak-list">
                                <div v-for="j in balancing.jejak" :key="j.Nomor_Hitung_Ulang" class="pa-bal-jejak-item">
                                    <div class="pa-bal-jejak-badge">
                                        <span>#{{ j.Nomor_Hitung_Ulang }}</span>
                                    </div>
                                    <div class="pa-bal-jejak-content">
                                        <div class="pa-bal-jejak-row">
                                            <div class="pa-bal-jejak-metric">
                                                <span class="pa-bal-jejak-lbl">Final Score:</span>
                                                <span class="pa-bal-jejak-before">{{ formatScore(j.Final_Score_Sebelum) }}</span>
                                                <HcisIcon name="arrow-right" size="2xs" class="pa-bal-jejak-arrow" />
                                                <strong class="pa-bal-jejak-after">{{ formatScore(j.Final_Score_Sesudah) }}</strong>
                                            </div>
                                            <div class="pa-bal-jejak-metric">
                                                <span class="pa-bal-jejak-lbl">Grade:</span>
                                                <span class="pa-bal-jejak-before">{{ displayGrade(j.Kode_Grade_Sebelum) }}</span>
                                                <HcisIcon name="arrow-right" size="2xs" class="pa-bal-jejak-arrow" />
                                                <strong
                                                    class="pa-bal-jejak-after pa-bal-grade-val"
                                                    :class="`grade-${getGradeVariant(j.Kode_Grade_Sesudah)}`"
                                                >
                                                    {{ displayGrade(j.Kode_Grade_Sesudah) }}
                                                </strong>
                                            </div>
                                        </div>
                                        <div class="pa-bal-jejak-sub">
                                            <span v-if="j.Sebab" class="pa-bal-jejak-sebab">
                                                <HcisIcon name="info-circle" size="2xs" /> {{ j.Sebab }}
                                            </span>
                                            <span v-if="j.Created_At" class="pa-bal-jejak-time">
                                                <HcisIcon name="clock-history" size="2xs" /> {{ formatWaktuBalancing(j.Created_At) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </HcisCard>
                    </div>
                </div>
            </template>

            <!-- Empty Detail State -->
            <HcisResultState
                v-else
                status="info"
                variant="flat"
                layout="compact"
                title="Detail hasil belum tersedia"
                description="Server belum mengembalikan detail hasil untuk peserta ini."
                :show-retry="false"
            />
        </div>

        <template #footer-start>
            <span class="pa-dialog-hint">
                <HcisIcon name="clock-history" size="2xs" />
                Versi perhitungan {{ detail?.peserta?.Calculation_Version || '—' }}
            </span>
            <span v-if="canCalculate && alasanTakBisaHitung" class="pa-dialog-hint" data-uji="alasan-tak-hitung">
                <HcisIcon name="lock-fill" size="2xs" />
                {{ alasanTakBisaHitung }}
            </span>
        </template>

        <template #footer>
            <HcisButton
                v-if="canShowHitung"
                :label="labelHitung"
                variant="success"
                size="sm"
                leading-icon="calculator-fill"
                :loading="recalculating"
                @click="hitungPeserta"
            />
            <HcisButton
                v-if="canShowRilisLeader && !modeVerifikasi"
                :label="sudahDirilisKeLeader ? 'Cabut rilis leader' : 'Rilis ke leader'"
                :variant="sudahDirilisKeLeader ? 'danger' : 'secondary'"
                size="sm"
                :leading-icon="sudahDirilisKeLeader ? 'eye-slash' : 'people-fill'"
                data-uji="rilis-leader"
                @click="sudahDirilisKeLeader ? (dialogCabutLeader = true) : (dialogRilisLeader = true)"
            />
            <HcisButton
                v-if="canShowTerbitkan && !modeVerifikasi"
                :label="labelTerbitkan"
                variant="success"
                size="sm"
                leading-icon="send-check-fill"
                @click="dialogPenerbitan = true"
            />
            <HcisButton
                variant="primary"
                label="Tutup"
                size="sm"
                @click="$emit('update:modelValue', false)"
            />
        </template>
    </HcisDialog>

    <!-- Subdialog 1: Batalkan Penugasan Penilai -->
    <HcisDialog
        v-model="dialogPembatalan"
        title="Batalkan penugasan"
        kicker="AUDIT PENUGASAN"
        icon="x-circle"
        sentiment="danger"
        variant="glass"
        size="sm"
    >
        <p class="pa-modal-copy">
            Berikan alasan pembatalan untuk
            <strong>{{ assignmentBatal?.Nama_Penilai_Saat_Cycle || assignmentBatal?.Kode_Karyawan_Penilai }}</strong>
            pada kategori
            <strong>{{ assignmentBatal?.Nama_Kategori || assignmentBatal?.Kode_Kategori }}</strong>.
        </p>

        <div v-if="dampakPembatalan" class="pa-batal-dampak">
            <div class="pa-batal-dampak__kepala">
                <HcisIcon name="diagram-2" size="2xs" />
                <strong>Bobot sesudah pembatalan</strong>
                <span>{{ dampakPembatalan.tersisa.length }} penilai tersisa</span>
            </div>

            <p v-if="dampakPembatalan.kosong" class="pa-batal-dampak__tahan">
                Ini penilai terakhir pada kategori tersebut. Membatalkannya membuat kategori ini
                dikeluarkan dari perhitungan dan porsinya berpindah ke kategori lain.
            </p>

            <p v-else-if="dampakPembatalan.melanggarAmbang" class="pa-batal-dampak__tahan">
                Kategori ini menuntut minimal
                {{ dampakPembatalan.kategori.minSelesai }} penilai; setelah pembatalan tersisa
                {{ dampakPembatalan.tersisa.length }}. Kategorinya akan dikeluarkan dari
                perhitungan dan porsinya berpindah ke kategori lain.
            </p>

            <p v-else-if="dampakPembatalan.adaKustom" class="pa-batal-dampak__catatan">
                Kategori ini memakai bobot yang diatur admin, jadi sisanya tidak selalu dibagi
                rata. Server akan menghitung ulang dan melaporkan bila bobot khususnya harus
                dilepas.
            </p>

            <ul v-else class="pa-batal-dampak__daftar">
                <li v-for="p in dampakPembatalan.baris" :key="`dampak-${p.Id_PA_Assignment}`">
                    <span>{{ p.Nama_Penilai_Saat_Cycle || p.Kode_Karyawan_Penilai }}</span>
                    <strong>
                        {{ formatPersen(p.Bobot_Dalam_Kategori) }} &rarr;
                        {{ formatPersen(p.bobotBaru) }}
                    </strong>
                </li>
            </ul>

            <p v-if="pembatalanDitahan" class="pa-batal-dampak__jalan">
                Tambahkan penilai penggantinya lebih dulu, lalu batalkan yang ini.
            </p>
        </div>

        <HcisTextarea
            id="alasan-batal-pa"
            v-model="alasanBatal"
            label="Alasan pembatalan"
            placeholder="Contoh: salah pilih penilai"
            :maxlength="500"
            show-count
            required
        />
        <HcisResultState
            v-if="actionError"
            :status="actionErrorStatus === 403 ? 'warning' : 'danger'"
            variant="flat"
            layout="inline"
            :title="actionErrorStatus === 403 ? 'Akses pembatalan tidak tersedia' : 'Pembatalan ditolak'"
            :description="actionError"
            :show-retry="false"
            :error-code="actionErrorCode"
            :request-id="actionRequestId"
        />
        <template #footer>
            <HcisButton variant="secondary" label="Batal" size="sm" @click="dialogPembatalan = false" />
            <HcisButton
                variant="danger"
                label="Batalkan penugasan"
                size="sm"
                leading-icon="x-circle"
                :loading="acting"
                :disabled="!alasanBatal.trim() || pembatalanDitahan"
                @click="batalkan"
            />
        </template>
    </HcisDialog>


    <!-- Subdialog 3b: Rilis ke leader (pintu yang BERBEDA dari penerbitan ke karyawan) -->
    <HcisDialog
        v-model="dialogRilisLeader"
        title="Rilis hasil ke leader?"
        kicker="AKSES LEADER"
        icon="people-fill"
        sentiment="brand"
        variant="glass"
        size="sm"
    >
        <p class="pa-modal-copy">
            Atasan orang ini akan melihat nilai akhir, grade, dan kuadrannya di halaman PA Tim.
            <strong>Karyawannya sendiri belum melihat apa pun</strong> sampai hasilnya benar-benar
            diterbitkan.
        </p>
        <template #footer>
            <HcisButton variant="secondary" label="Batal" size="sm" @click="dialogRilisLeader = false" />
            <HcisButton
                variant="primary"
                label="Ya, rilis ke leader"
                size="sm"
                leading-icon="people-fill"
                data-uji="konfirmasi-rilis-leader"
                @click="rilisKeLeader"
            />
        </template>
    </HcisDialog>

    <!-- Subdialog 3c: Cabut rilis leader — wajib bersebab -->
    <HcisDialog
        v-model="dialogCabutLeader"
        title="Cabut akses leader?"
        kicker="AKSES LEADER"
        icon="eye-slash"
        sentiment="danger"
        variant="glass"
        size="sm"
    >
        <p class="pa-modal-copy">
            Atasan tidak lagi melihat angka orang ini di PA Tim. Sebabnya disimpan bersama nama
            Anda, karena menarik kembali akses yang sudah diberikan akan ditanyakan orang kelak.
        </p>
        <HcisTextarea
            id="pa-alasan-cabut-leader"
            v-model="alasanCabutLeader"
            label="Alasan pencabutan"
            placeholder="Contoh: grade masih ditinjau ulang forum"
            :maxlength="500"
            show-count
            required
            data-uji="alasan-cabut-leader"
        />
        <HcisAlert v-if="galatCabutLeader" variant="danger" :title="galatCabutLeader" />
        <template #footer>
            <HcisButton variant="secondary" label="Batal" size="sm" @click="dialogCabutLeader = false" />
            <HcisButton
                variant="danger"
                label="Ya, cabut akses"
                size="sm"
                leading-icon="eye-slash"
                data-uji="konfirmasi-cabut-leader"
                @click="cabutRilisLeader"
            />
        </template>
    </HcisDialog>

    <!-- Subdialog 3: Konfirmasi Penerbitan Hasil -->
    <HcisDialog
        v-model="dialogPenerbitan"
        title="Terbitkan hasil peserta?"
        kicker="PUBLIKASI HASIL"
        icon="send-check-fill"
        sentiment="success"
        variant="glass"
        size="sm"
    >
        <div class="pa-publish-confirm">
            <div class="pa-publish-confirm__identity">
                <div class="pa-publish-confirm__avatar">
                    <HcisIcon name="person-fill" size="sm" />
                </div>
                <div>
                    <strong>{{ detail?.peserta?.Nama_Karyawan_Saat_Cycle || participant?.Nama_Karyawan_Saat_Cycle }}</strong>
                    <span>{{ detail?.peserta?.Kode_Karyawan || participant?.Kode_Karyawan }}</span>
                </div>
            </div>
            <p class="pa-modal-copy">
                Hasil ini akan terlihat oleh karyawan setelah diterbitkan. Pastikan detail terakhir sudah diperiksa.
                Server akan menolak penerbitan bila hasil masih tertahan.
            </p>
            <div class="pa-publish-confirm__score">
                <span>Final score</span>
                <strong>{{ formatScore(detail?.peserta?.Final_Score) }}</strong>
                <span>Grade {{ displayGrade(detail?.peserta?.Kode_Grade_Final || detail?.peserta?.Kode_Grade_Sistem) }}</span>
            </div>
        </div>
        <HcisResultState
            v-if="publishError"
            :status="publishErrorStatus === 403 ? 'warning' : 'danger'"
            variant="flat"
            layout="inline"
            :title="publishErrorStatus === 403 ? 'Akses penerbitan tidak tersedia' : 'Penerbitan hasil ditolak'"
            :description="publishError"
            :show-retry="false"
            :error-code="publishErrorCode"
            :request-id="publishRequestId"
        />
        <template #footer>
            <HcisButton
                variant="secondary"
                label="Batal"
                size="sm"
                :disabled="publishing"
                @click="dialogPenerbitan = false"
            />
            <HcisButton
                variant="success"
                label="Ya, terbitkan hasil"
                size="sm"
                leading-icon="send-check-fill"
                :loading="publishing"
                :disabled="publishing"
                @click="terbitkanPeserta"
            />
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisChart,
    HcisDialog,
    HcisEmptyState,
    HcisIcon,
    HcisResultState,
    HcisRichText,
    HcisStatCard,
    HcisStatGroup,
    HcisStatusBadge,
    HcisTabs,
    HcisTextarea,
} from '@/components/ui';
import paApi from '../../api/paApi.js';
import PaRiwayatKalibrasiPenilai from './PaRiwayatKalibrasiPenilai.vue';
import PaWawasanHasil from './PaWawasanHasil.vue';
import PaRiwayatPerhitungan from './PaRiwayatPerhitungan.vue';

defineOptions({ name: 'PaResultDialog' });

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    participant: { type: Object, default: null },
    result: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errorStatus: { type: Number, default: 0 },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
    calculation: { type: Object, default: null },
    recalculating: { type: Boolean, default: false },
    acting: { type: Boolean, default: false },
    actionError: { type: String, default: '' },
    actionErrorStatus: { type: Number, default: 0 },
    actionErrorCode: { type: String, default: '' },
    actionRequestId: { type: String, default: '' },
    actionSuccess: { type: String, default: '' },
    publishing: { type: Boolean, default: false },
    publishError: { type: String, default: '' },
    publishErrorStatus: { type: Number, default: 0 },
    publishErrorCode: { type: String, default: '' },
    publishRequestId: { type: String, default: '' },
    publishSuccess: { type: String, default: '' },
    canAssign: { type: Boolean, default: false },
    canCalibrate: { type: Boolean, default: false },
    canCalculate: { type: Boolean, default: false },
    canPublish: { type: Boolean, default: false },
    /* Dibuka dari tahap Hitung & Verifikasi: grade belum ada (kurva belum dikunci), dan rilis
       ke leader / terbit adalah tahap lain — keduanya disembunyikan di sini. */
    modeVerifikasi: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:modelValue',
    'retry',
    'cancel-assignment',
    'calculate',
    'calibrate',
    'publish',
    'release-leader',
    'revoke-leader',
]);

const activeTab = ref('ringkasan');
const isMobile = ref(false);
let mediaQuery = null;

function handleMediaChange(e) {
    isMobile.value = e.matches;
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        mediaQuery = window.matchMedia('(max-width: 768px)');
        isMobile.value = mediaQuery.matches;
        mediaQuery.addEventListener('change', handleMediaChange);
    }
});

onUnmounted(() => {
    if (mediaQuery) {
        mediaQuery.removeEventListener('change', handleMediaChange);
    }
});

function inisialKaryawan(nama) {
    if (!nama) return 'PA';
    const bagian = nama.trim().split(/\s+/);
    if (bagian.length === 1) return bagian[0].slice(0, 2).toUpperCase();
    return (bagian[0][0] + bagian[1][0]).toUpperCase();
}

function displayGrade(kode) {
    if (!kode || kode === '—' || kode === '(Tanpa grade)') return '—';
    return String(kode)
        .replace(/_PLUS/gi, '+')
        .replace(/_MINUS/gi, '-')
        .replace(/\b([A-E])\s*PLUS\b/gi, '$1+')
        .replace(/\b([A-E])\s*MINUS\b/gi, '$1-')
        .trim();
}

function getGradeVariant(grade) {
    if (!grade) return 'neutral';
    const g = displayGrade(grade).toUpperCase();
    if (g.startsWith('A')) return 'success';
    if (g.startsWith('B')) return 'brand';
    if (g.startsWith('C')) return 'warning';
    if (g.startsWith('D') || g.startsWith('E')) return 'danger';
    return 'neutral';
}

const totalPenilaiAktif = computed(() => {
    return (props.result?.penilai || []).filter((p) => p.Status_Penugasan !== 'DIBATALKAN').length;
});

const catatanPerJenis = computed(() => {
    const grup = new Map();

    (props.result?.catatan || []).forEach((c) => {
        const kode = c.Kode_Type || 'LAINNYA';

        if (!grup.has(kode)) {
            grup.set(kode, { kode, nama: c.Nama_Type || kode, isi: [] });
        }

        grup.get(kode).isi.push(c);
    });

    grup.forEach((g) => {
        g.isi.sort((a, b) => Number(a.dari_penilai) - Number(b.dari_penilai));
    });

    return Array.from(grup.values());
});

const naratifPerPertanyaan = computed(() => {
    const jawaban = props.result?.jawaban_naratif || [];
    const pertanyaan = props.result?.pertanyaan_kualitatif || [];

    const perSoal = new Map();

    jawaban.forEach((j) => {
        const kode = j.Kode_Soal_Sumber || '';
        if (!perSoal.has(kode)) perSoal.set(kode, []);
        perSoal.get(kode).push(j);
    });

    if (!pertanyaan.length) {
        const grup = new Map();

        jawaban.forEach((j) => {
            const matchCat = (kategoriCatatan.value || []).find(
                (k) =>
                    (j.Kode_Competency && k.Kode_Competency === j.Kode_Competency) ||
                    k.Nama_Competency?.trim().toLowerCase() === j.Nama_Competency?.trim().toLowerCase(),
            );

            const kode = j.Kode_Competency || matchCat?.Kode_Competency || j.Nama_Competency || j.Kode_Soal_Sumber || 'Lainnya';
            const nama = matchCat?.Nama_Competency || j.Nama_Competency || j.Kode_Soal_Sumber || 'Lainnya';

            if (!grup.has(kode)) {
                grup.set(kode, { kode, nama, diminta: 0, terjawab: 0, soal: [] });
            }

            const g = grup.get(kode);
            g.diminta += 1;
            g.terjawab += 1;

            let soal = g.soal.find((x) => x.kode === (j.Kode_Soal_Sumber || nama));

            if (!soal) {
                soal = {
                    kode: j.Kode_Soal_Sumber || nama,
                    kategori: j.Nama_Kategori || j.Kode_Kategori || '',
                    diminta: 0,
                    terjawab: 0,
                    jawaban: [],
                };
                g.soal.push(soal);
            }

            soal.diminta += 1;
            soal.terjawab += 1;
            soal.jawaban.push(j);
        });

        return Array.from(grup.values());
    }

    const grup = new Map();

    pertanyaan.forEach((p) => {
        const kode = p.Kode_Competency || 'LAINNYA';

        if (!grup.has(kode)) {
            grup.set(kode, {
                kode,
                nama: p.Nama_Competency || kode,
                diminta: 0,
                terjawab: 0,
                soal: [],
            });
        }

        const g = grup.get(kode);
        g.diminta += Number(p.diminta) || 0;
        g.terjawab += Number(p.terjawab) || 0;

        g.soal.push({
            kode: p.Kode_Soal,
            kategori: p.Nama_Kategori || p.Kode_Kategori || 'Semua penilai',
            diminta: Number(p.diminta) || 0,
            terjawab: Number(p.terjawab) || 0,
            jawaban: perSoal.get(p.Kode_Soal) || [],
        });
    });

    return Array.from(grup.values());
});

const naratifDiminta = computed(() =>
    naratifPerPertanyaan.value.reduce((jumlah, g) => jumlah + g.diminta, 0),
);

const naratifTerjawab = computed(() =>
    naratifPerPertanyaan.value.reduce((jumlah, g) => jumlah + g.terjawab, 0),
);

const selectedNaratifCategory = ref('ALL');

const totalPertanyaanKualitatif = computed(() =>
    naratifPerPertanyaan.value.reduce((acc, g) => acc + g.soal.length, 0),
);

const totalJawabanNaratif = computed(() => props.result?.jawaban_naratif?.length || 0);

const tingkatPartisipasiNaratif = computed(() => {
    if (!naratifDiminta.value) return 100;
    return Math.round((naratifTerjawab.value / naratifDiminta.value) * 100);
});

const naratifCategories = computed(() => {
    const list = props.result?.jawaban_naratif || [];
    const counts = new Map();

    list.forEach((j) => {
        const kode = j.Kode_Kategori || 'OTHER';
        const nama = j.Nama_Kategori || kode;
        if (!counts.has(kode)) counts.set(kode, { kode, nama, jumlah: 0 });
        counts.get(kode).jumlah += 1;
    });

    return Array.from(counts.values());
});

const filteredNaratifPerPertanyaan = computed(() => {
    if (selectedNaratifCategory.value === 'ALL') {
        return naratifPerPertanyaan.value;
    }

    return naratifPerPertanyaan.value
        .map((grup) => {
            const filteredSoal = grup.soal
                .map((s) => ({
                    ...s,
                    jawaban: s.jawaban.filter(
                        (j) => j.Kode_Kategori === selectedNaratifCategory.value,
                    ),
                }))
                .filter(
                    (s) =>
                        s.jawaban.length > 0 ||
                        s.kategori?.toUpperCase().includes(selectedNaratifCategory.value),
                );

            return {
                ...grup,
                soal: filteredSoal,
            };
        })
        .filter((grup) => grup.soal.length > 0);
});

const grupTerbuka = reactive({});

const semuaNaratifTerbuka = computed(() => {
    return naratifPerPertanyaan.value.every((g) => grupTerbuka[g.kode] !== false);
});

function toggleGrupNaratif(kode) {
    grupTerbuka[kode] = grupTerbuka[kode] === false;
}

function toggleSemuaNaratif() {
    const targetState = !semuaNaratifTerbuka.value;
    naratifPerPertanyaan.value.forEach((g) => {
        grupTerbuka[g.kode] = targetState;
    });
}

function getCategorySentiment(catCode) {
    if (!catCode) return 'neutral';
    const c = String(catCode).toUpperCase();
    if (c.includes('ATASAN')) return 'brand';
    if (c.includes('PEER') || c.includes('REKAN')) return 'purple';
    if (c.includes('BAWAHAN') || c.includes('SUBORDINATE')) return 'teal';
    if (c.includes('SELF') || c.includes('DIRI')) return 'warning';
    return 'neutral';
}

function getCategoryIcon(catCode) {
    if (!catCode) return 'layers';
    const c = String(catCode).toUpperCase();
    if (c.includes('ATASAN')) return 'person-workspace';
    if (c.includes('PEER') || c.includes('REKAN')) return 'people-fill';
    if (c.includes('BAWAHAN') || c.includes('SUBORDINATE')) return 'diagram-2-fill';
    if (c.includes('SELF') || c.includes('DIRI')) return 'person-check-fill';
    return 'layers';
}

function getCategoryProgressPercent(cat) {
    if (!cat) return 0;
    const expected = Number(cat.Jumlah_Rater_Diharapkan) || 0;
    const filled = Number(cat.Jumlah_Rater_Mengisi) || 0;
    if (expected <= 0) return filled > 0 ? 100 : 0;
    return Math.min(100, Math.round((filled / expected) * 100));
}

const totalKategoriRater = computed(() => {
    const list = props.result?.kategori || [];
    if (!list.length) return { filled: 0, expected: 0, pct: 0, isComplete: false };
    const filled = list.reduce((acc, c) => acc + (Number(c.Jumlah_Rater_Mengisi) || 0), 0);
    const expected = list.reduce((acc, c) => acc + (Number(c.Jumlah_Rater_Diharapkan) || 0), 0);
    const pct = expected > 0 ? Math.min(100, Math.round((filled / expected) * 100)) : 100;
    return {
        filled,
        expected,
        pct,
        isComplete: expected > 0 && filled >= expected,
    };
});

/*
 * Bobot yang berlaku per kategori dan per penilai, mengikuti PaResultCalculationService:
 * kategori memakai `Bobot_Terpakai` hasil perhitungan terakhir (sudah diredistribusi bila
 * ada kategori yang gugur), dan di dalam kategori bobot penilai dinormalkan ulang hanya di
 * antara yang sudah mengisi. Jadi setelah penilai dilepas atau ditambah, angka di sini
 * adalah bagian yang benar-benar dipakai, bukan rencana.
 */
const rincianBobotBerlaku = computed(() => {
    const kategori = props.result?.kategori || [];
    if (!kategori.length) return null;

    const aturan = props.result?.aturan_penilai || {};
    const penilai = props.result?.penilai || [];
    const angka = (v) => (v === null || v === undefined || v === '' ? null : Number(v));

    /* Rencana = bobot aturan LEVEL. `aturan_penilai` sudah memuat override, jadi pada
       peserta yang kategorinya ditandai tidak diikutkan angkanya sudah hasil bagi ulang. */
    const bobotLevel = Object.fromEntries(
        (props.result?.bobot_penilai?.bobot_level || []).map((b) => [b.kode_kategori, b.bobot_persen]),
    );
    const tidakDiikutkan = props.result?.bobot_penilai?.tidak_diikutkan || [];

    const baris = kategori.map((k) => {
        const kode = k.Kode_Kategori;
        const a = aturan[kode] || {};
        const rencana = angka(bobotLevel[kode] ?? a.bobot_persen);
        const berlaku = angka(k.Bobot_Terpakai);
        const dihitung = berlaku !== null;
        const anggota = penilai.filter((p) => p.Kode_Kategori === kode);
        const terhitung = dihitung ? anggota.filter((p) => p.Status_Penugasan === 'SELESAI') : [];
        const totalBobot = terhitung.reduce((acc, p) => acc + (Number(p.Bobot_Dalam_Kategori) || 0), 0);

        return {
            kode,
            nama: k.Nama_Kategori || kode,
            rencana,
            berlaku,
            dihitung,
            mengisi: Number(k.Jumlah_Rater_Mengisi) || 0,
            minimal: a.min_rater_selesai ?? a.min_rater ?? null,
            penilai: anggota.map((p) => {
                const ikut = terhitung.includes(p);
                let bagian = null;

                if (ikut) {
                    bagian = totalBobot > 0
                        ? (berlaku * (Number(p.Bobot_Dalam_Kategori) || 0)) / totalBobot
                        : berlaku / terhitung.length;
                }

                let sebab = null;
                if (!ikut) {
                    if (p.Status_Penugasan === 'DIBATALKAN') sebab = 'dilepas';
                    else if (!dihitung) sebab = 'kategori tidak dihitung';
                    else sebab = 'belum mengisi';
                }

                return { id: p.Id_PA_Assignment, nama: p.Nama_Penilai_Saat_Cycle || 'Penilai', bagian, sebab };
            }),
        };
    });

    // Kategori yang sengaja tidak diikutkan tidak punya baris hasil; ditampilkan dengan alasannya.
    tidakDiikutkan
        .filter((t) => !baris.some((b) => b.kode === t.kode_kategori))
        .forEach((t) => baris.push({
            kode: t.kode_kategori,
            nama: t.nama_kategori || t.kode_kategori,
            rencana: angka(bobotLevel[t.kode_kategori]),
            berlaku: null,
            dihitung: false,
            mengisi: 0,
            minimal: null,
            alasanTidakDiikutkan: t.alasan || '-',
            penilai: [],
        }));

    const totalRencana = baris.reduce((acc, b) => acc + (b.rencana ?? 0), 0);
    const totalBerlaku = baris.reduce((acc, b) => acc + (b.berlaku ?? 0), 0);
    const adaYangBerlaku = baris.some((b) => b.dihitung);
    const bergeser = adaYangBerlaku && baris.some((b) => b.rencana !== null && Math.abs((b.berlaku ?? 0) - b.rencana) > 0.005);

    return { baris, totalRencana, totalBerlaku, adaYangBerlaku, bergeser };
});

const kategoriCatatan = computed(() => props.result?.kategori_catatan || []);

const selectedDimensiKode = ref('ALL');

const daftarDimensiKualitatif = computed(() => {
    const list = [];
    const seenKodes = new Set();
    const seenNames = new Set();

    (kategoriCatatan.value || []).forEach((k) => {
        const kode = k.Kode_Competency;
        const nama = k.Nama_Competency || kode;
        const normNama = nama.trim().toLowerCase();

        if (!seenKodes.has(kode) && !seenNames.has(normNama)) {
            seenKodes.add(kode);
            seenNames.add(normNama);

            const matchingGrup = naratifPerPertanyaan.value.find(
                (g) => g.kode === kode || g.nama?.trim().toLowerCase() === normNama,
            );
            const raterTerjawab = matchingGrup ? matchingGrup.terjawab : 0;
            const raterDiminta = matchingGrup ? matchingGrup.diminta : 0;
            const isiAdmin = (catatanAdmin[kode] || '').trim();

            list.push({
                kode,
                nama,
                icon: getKategoriCatatanIcon(nama),
                sentiment: getKategoriCatatanSentiment(nama),
                guideline: getKategoriCatatanGuideline(nama),
                isFilled: Boolean(isiAdmin),
                raterTerjawab,
                raterDiminta,
            });
        }
    });

    (naratifPerPertanyaan.value || []).forEach((g) => {
        const kode = g.kode;
        const nama = g.nama || g.kode;
        const normNama = nama.trim().toLowerCase();

        if (!seenKodes.has(kode) && !seenNames.has(normNama)) {
            seenKodes.add(kode);
            seenNames.add(normNama);

            const isiAdmin = (catatanAdmin[kode] || '').trim();
            list.push({
                kode,
                nama,
                icon: getKategoriCatatanIcon(nama),
                sentiment: getKategoriCatatanSentiment(nama),
                guideline: getKategoriCatatanGuideline(nama),
                isFilled: Boolean(isiAdmin),
                raterTerjawab: g.terjawab,
                raterDiminta: g.diminta,
            });
        } else {
            const existing = list.find((item) => item.kode === kode || item.nama.trim().toLowerCase() === normNama);
            if (existing && existing.raterTerjawab === 0 && g.terjawab > 0) {
                existing.raterTerjawab = g.terjawab;
                existing.raterDiminta = g.diminta;
            }
        }
    });

    return list;
});

function isGrupMatchDimensi(grupKode, grupNama, dimensiKode) {
    if (!dimensiKode || dimensiKode === 'ALL') return true;
    if (grupKode === dimensiKode) return true;
    const targetDimensi = daftarDimensiKualitatif.value.find((d) => d.kode === dimensiKode);
    if (!targetDimensi) return false;

    const gn = String(grupNama || '').trim().toLowerCase();
    const dn = String(targetDimensi.nama || '').trim().toLowerCase();
    if (gn === dn) return true;

    if (grupKode === targetDimensi.kode) return true;

    if ((gn.includes('strength') || gn.includes('kekuatan')) && (dn.includes('strength') || dn.includes('kekuatan'))) return true;
    if ((gn.includes('development') || gn.includes('pengembangan')) && (dn.includes('development') || dn.includes('pengembangan'))) return true;
    if (gn.includes('inisiatif') && dn.includes('inisiatif')) return true;
    if ((gn.includes('rekomendasi') || gn.includes('recommendation')) && (dn.includes('rekomendasi') || dn.includes('recommendation'))) return true;

    return false;
}

function hasMatchingGrupForDimensi(dimensiKode) {
    if (dimensiKode === 'ALL') return true;
    return filteredNaratifPerPertanyaan.value.some((g) => isGrupMatchDimensi(g.kode, g.nama, dimensiKode));
}

function getDimensiIndex(kode) {
    return daftarDimensiKualitatif.value.findIndex((d) => d.kode === kode);
}

function hasNextDimensi(kode) {
    const idx = getDimensiIndex(kode);
    return idx >= 0 && idx < daftarDimensiKualitatif.value.length - 1;
}

function getNextDimensiNama(kode) {
    const idx = getDimensiIndex(kode);
    if (idx >= 0 && idx < daftarDimensiKualitatif.value.length - 1) {
        return daftarDimensiKualitatif.value[idx + 1].nama;
    }
    return '';
}

function keDimensiBerikutnya(kode) {
    const idx = getDimensiIndex(kode);
    if (idx >= 0 && idx < daftarDimensiKualitatif.value.length - 1) {
        selectedDimensiKode.value = daftarDimensiKualitatif.value[idx + 1].kode;
    } else {
        selectedDimensiKode.value = 'ALL';
    }
}

const catatanAdmin = reactive({});
const menyimpanCatatan = ref(false);
const galatCatatan = ref('');
const suksesSimpanCatatan = ref('');

function getKategoriCatatanIcon(nama) {
    const n = String(nama || '').toLowerCase();
    if (n.includes('kekuatan') || n.includes('strength')) return 'lightning-charge-fill';
    if (n.includes('pengembangan') || n.includes('development') || n.includes('area')) return 'graph-up-arrow';
    if (n.includes('inisiatif') || n.includes('initiative')) return 'lightbulb-fill';
    if (n.includes('rekomendasi') || n.includes('recommendation') || n.includes('saran')) return 'compass-fill';
    return 'card-heading';
}

function getKategoriCatatanSentiment(nama) {
    const n = String(nama || '').toLowerCase();
    if (n.includes('kekuatan') || n.includes('strength')) return 'success';
    if (n.includes('pengembangan') || n.includes('development') || n.includes('area')) return 'warning';
    if (n.includes('inisiatif') || n.includes('initiative')) return 'purple';
    if (n.includes('rekomendasi') || n.includes('recommendation') || n.includes('saran')) return 'brand';
    return 'neutral';
}

function getKategoriCatatanGuideline(nama) {
    const n = String(nama || '').toLowerCase();
    if (n.includes('kekuatan') || n.includes('strength')) {
        return 'Tuliskan poin keunggulan, pencapaian istimewa, dan perilaku positif yang patut dipertahankan.';
    }
    if (n.includes('pengembangan') || n.includes('development') || n.includes('area')) {
        return 'Tuliskan aspek kompetensi atau kebiasaan kerja yang perlu ditingkatkan dengan rencana aksi konkret.';
    }
    if (n.includes('inisiatif') || n.includes('initiative')) {
        return 'Tuliskan kontribusi inisiatif atau proyek bernilai tambah yang dicapai karyawan selama periode penilaian.';
    }
    if (n.includes('rekomendasi') || n.includes('recommendation') || n.includes('saran')) {
        return 'Tuliskan rekomendasi penugasan, pelatihan lanjutan, atau aspirasi jalur pengembangan karir.';
    }
    return 'Tuliskan catatan dan kesimpulan resmi dari evaluator untuk dimensi evaluasi ini.';
}

function insertTemplatePoin(kode) {
    const current = (catatanAdmin[kode] || '').trim();
    const template = '<ul><li>Poin utama 1: </li><li>Poin utama 2: </li></ul>';
    if (!current) {
        catatanAdmin[kode] = template;
    } else {
        catatanAdmin[kode] = `${current}<p><br></p>${template}`;
    }
}

function kutipJawaban(j, groupKode, groupNama) {
    if (!kategoriCatatan.value.length) return;

    let target = null;
    if (selectedDimensiKode.value !== 'ALL') {
        target = kategoriCatatan.value.find((k) => k.Kode_Competency === selectedDimensiKode.value);
    }

    if (!target) {
        target = kategoriCatatan.value.find(
            (k) =>
                (j.Kode_Competency && k.Kode_Competency === j.Kode_Competency) ||
                k.Kode_Competency === groupKode ||
                k.Nama_Competency?.trim().toLowerCase() === (groupNama || j.Nama_Competency)?.trim().toLowerCase(),
        );
    }

    if (!target) {
        const n = (groupNama || j.Nama_Competency || '').toLowerCase();
        target = kategoriCatatan.value.find((k) => {
            const kn = (k.Nama_Competency || '').toLowerCase();
            return (
                (n.includes('strength') && kn.includes('strength')) ||
                (n.includes('kekuatan') && kn.includes('kekuatan')) ||
                (n.includes('development') && kn.includes('development')) ||
                (n.includes('pengembangan') && kn.includes('pengembangan')) ||
                (n.includes('inisiatif') && kn.includes('inisiatif')) ||
                (n.includes('rekomendasi') && kn.includes('rekomendasi'))
            );
        });
    }

    if (!target) {
        target = kategoriCatatan.value[0];
    }

    const targetKode = target.Kode_Competency;
    const author = j.Nama_Penilai_Saat_Cycle || 'Penilai';
    const role = j.Nama_Kategori || j.Kode_Kategori || 'Evaluator';
    const text = j.Jawaban_Teks || '';

    const quoteHtml = `<blockquote><strong>Kutipan ${author} (${role}):</strong> <em>&ldquo;${text}&rdquo;</em></blockquote>`;
    const current = (catatanAdmin[targetKode] || '').trim();

    if (!current) {
        catatanAdmin[targetKode] = quoteHtml;
    } else {
        catatanAdmin[targetKode] = `${current}<p><br></p>${quoteHtml}`;
    }
}

/* Riwayat Balancing dimuat saat tabnya dibuka pertama kali, bukan bersama detail: API-nya
   milik fase Hasil, dan pemegang fase lain yang membuka dialog ini tidak perlu menerima 403. */
const riwayatRef = ref(null);
/* Hitung ulang dari dialog ini melahirkan versi baru; riwayat yang sudah dimuat ikut disegarkan. */
watch(
    () => props.result?.peserta?.Calculation_Version,
    (baru, lama) => {
        if (lama !== undefined && baru !== lama) riwayatRef.value?.segarkan();
    },
);

const balancing = reactive({ dimuat: false, memuat: false, galat: '', sesi: [], jejak: [], pid: null });

function kompetensiBerubah(s) {
    return (s.kompetensi || []).filter((k) => Math.abs(Number(k.sesudah) - Number(k.sebelum)) >= 0.005);
}

function formatWaktuBalancing(v) {
    if (!v) return '—';
    try {
        return new Date(String(v).replace(' ', 'T')).toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return String(v);
    }
}

async function bukaTabBalancing() {
    activeTab.value = 'balancing';
    const pid = props.participant?.Id_PA_Participant ?? props.participant?.id;

    if (!pid || (balancing.dimuat && balancing.pid === pid)) return;

    balancing.memuat = true;
    balancing.galat = '';

    try {
        const r = await paApi.riwayatBalancing(pid);
        balancing.sesi = r?.sesi || [];
        balancing.jejak = r?.jejak || [];
        balancing.dimuat = true;
        balancing.pid = pid;
    } catch (e) {
        balancing.galat = e?.status === 403
            ? 'Riwayat Balancing hanya untuk pemegang fase Hasil.'
            : (e?.message || 'Gagal memuat riwayat Balancing.');
    } finally {
        balancing.memuat = false;
    }
}

/* Dialog tidak dilepas saat peserta berganti; riwayat peserta sebelumnya tidak boleh ikut tampil. */
watch(
    () => props.participant?.Id_PA_Participant ?? props.participant?.id,
    (baru, lama) => {
        if (baru === lama) return;
        Object.assign(balancing, { dimuat: false, galat: '', sesi: [], jejak: [], pid: null });
        if (activeTab.value === 'balancing') bukaTabBalancing();
    },
);

watch(
    () => props.result,
    (baru) => {
        galatCatatan.value = '';
        suksesSimpanCatatan.value = '';

        Object.keys(catatanAdmin).forEach((kode) => delete catatanAdmin[kode]);

        const catatan = baru?.catatan || [];

        (baru?.kategori_catatan || []).forEach((kategori) => {
            const kode = kategori.Kode_Competency;

            const adminNotes = catatan
                .filter((c) => c.dari_penilai === false && c.Kode_Type === kode)
                .map((c) => c.Isi);

            if (adminNotes.length > 0) {
                catatanAdmin[kode] = adminNotes.join('\n');
            } else {
                catatanAdmin[kode] = '';
            }
        });
    },
    { immediate: true },
);

async function simpanCatatanAdmin() {
    const participantId = props.participant?.Id_PA_Participant ?? props.participant?.id;

    if (!participantId || menyimpanCatatan.value) return;

    menyimpanCatatan.value = true;
    galatCatatan.value = '';
    suksesSimpanCatatan.value = '';

    try {
        const muatan = {};

        kategoriCatatan.value.forEach((kategori) => {
            const kode = kategori.Kode_Competency;
            const isi = (catatanAdmin[kode] || '').trim();
            muatan[kode] = isi ? [isi] : [''];
        });

        await paApi.simpanCatatanHasil(participantId, muatan);
        suksesSimpanCatatan.value = 'Catatan evaluasi resmi berhasil disimpan ke report peserta.';
        // Riwayat Perhitungan ikut memuat versi catatan yang baru disimpan.
        riwayatRef.value?.segarkan();
        emit('retry');
    } catch (e) {
        galatCatatan.value = e?.response?.data?.message || e?.message || 'Catatan gagal disimpan.';
    } finally {
        menyimpanCatatan.value = false;
    }
}

const detail = computed(() => props.result);

/* Grade resmi lebih dulu; grade sementara (siklus KURVA belum dikunci) hanya pengisi. */
const gradeTampil = computed(
    () =>
        detail.value?.peserta?.Kode_Grade_Final ||
        detail.value?.peserta?.Kode_Grade_Sistem ||
        detail.value?.peserta?.Grade_Sementara ||
        null,
);
const loading = computed(() => props.loading);
const error = computed(() => props.error);
const errorStatus = computed(() => props.errorStatus);
const errorCode = computed(() => props.errorCode);
const requestId = computed(() => props.requestId);
const hasilHitung = computed(() => props.calculation);
const recalculating = computed(() => props.recalculating);
const acting = computed(() => props.acting);
const actionError = computed(() => props.actionError);
const actionErrorStatus = computed(() => props.actionErrorStatus);
const actionErrorCode = computed(() => props.actionErrorCode);
const actionRequestId = computed(() => props.actionRequestId);
const actionSuccess = computed(() => props.actionSuccess);
const publishing = computed(() => props.publishing);
const publishError = computed(() => props.publishError);
const publishErrorStatus = computed(() => props.publishErrorStatus);
const publishErrorCode = computed(() => props.publishErrorCode);
const publishRequestId = computed(() => props.publishRequestId);
const publishSuccess = computed(() => props.publishSuccess);

const dialogPembatalan = ref(false);
const dialogPenerbitan = ref(false);
const dialogRilisLeader = ref(false);
const dialogCabutLeader = ref(false);
const alasanCabutLeader = ref('');
const galatCabutLeader = ref('');
const assignmentBatal = ref(null);
const alasanBatal = ref('');

/* Competency Radar Computeds (Aligned with PA Saya) */

const radarKompetensi = computed(() => {
    const list = detail.value?.kompetensi || [];
    if (!list.length) return [];

    const agregatList = list.filter(
        (item) =>
            item.agregat === true ||
            !item.Kode_Kategori ||
            item.Kode_Kategori === 'AGREGAT',
    );
    const sourceList = agregatList.length > 0 ? agregatList : list;

    const tersaring = sourceList.filter((k) => {
        const flag = k.Flag_Radar ?? k.flag_radar ?? 'Y';
        return (
            flag !== 'T' &&
            flag !== 't' &&
            flag !== false &&
            flag !== 0 &&
            k.Nilai !== null &&
            k.Nilai !== undefined
        );
    });

    return tersaring.length > 0
        ? tersaring
        : sourceList.filter((k) => k.Nilai !== null && k.Nilai !== undefined);
});

const radarCategories = computed(() => {
    if (!radarKompetensi.value.length) return [];
    return radarKompetensi.value.map((k) => ({
        name: k.Nama_Competency || k.Nama_Kompetensi || k.Kode_Competency || 'Kompetensi',
    }));
});

const radarData = computed(() => {
    if (!radarKompetensi.value.length) return [];
    return radarKompetensi.value.map((k) => Number((Number(k.Nilai) || 0).toFixed(1)));
});

const radarAmbang = computed(() =>
    radarKompetensi.value.map((k) => {
        const a = k.Nilai_Ambang ?? k.nilai_ambang ?? k.ambang;
        return a === null || a === undefined ? null : Number(a);
    }),
);

const radarSeries = computed(() => {
    if (!radarData.value.length) return [];

    const series = [
        {
            name: 'Nilai Agregat',
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
        ? 'Jaring luar adalah capaian agregat, jaring pembanding adalah ambang (KKM) tiap kompetensi'
        : 'Peta visual keseimbangan profil capaian kompetensi lintas dimensi',
);

/* Kalibrasi Forum dilepas dari layar ini (B7, spec 2026-09-30 Balancing): grade kini hanya
   bergeser lewat sesi Balancing dan kurva. */

function muatDetail() {
    emit('retry');
}

function formatScore(value) {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

const daftarTabDialog = computed(() => {
    const list = [
        { key: 'ringkasan', label: 'Ringkasan & Skor', icon: 'speedometer2' },
        {
            key: 'penugasan',
            label: 'Penugasan Penilai',
            icon: 'people-fill',
            badge: totalPenilaiAktif.value > 0 ? totalPenilaiAktif.value : null,
            badgeVariant: 'neutral',
        },
        {
            key: 'naratif',
            label: 'Feedback & Catatan Kualitatif',
            icon: 'chat-left-quote-fill',
            badge: naratifDiminta.value > 0 ? `${naratifTerjawab.value}/${naratifDiminta.value}` : null,
            badgeVariant: 'purple',
        },
    ];

    list.push({ key: 'riwayat', label: 'Riwayat Perhitungan', icon: 'clock-history' });
    list.push({ key: 'balancing', label: 'Balancing', icon: 'sliders' });
    list.push({ key: 'semua', label: 'Semua Bagian', icon: 'grid-fill' });

    return list;
});

const ringkasanStats = computed(() => {
    const p = detail.value?.peserta || {};
    const stats = [
        {
            id: 'nilai-kpi',
            label: 'Nilai KPI',
            value: formatScore(p.Nilai_KPI),
            caption: p.Nilai_KPI_Sebelum_Cap != null && Number(p.Nilai_KPI_Sebelum_Cap) !== Number(p.Nilai_KPI)
                ? `Sebelum cap: ${formatScore(p.Nilai_KPI_Sebelum_Cap)}`
                : 'Nilai sebelum pembobotan akhir',
            icon: 'speedometer2',
            sentiment: 'brand',
        },
        {
            id: 'nilai-kompetensi',
            label: 'Nilai Kompetensi',
            value: formatScore(p.Nilai_Kompetensi),
            caption: 'Agregat lintas kategori',
            icon: 'diagram-3',
            sentiment: 'purple',
        },
        {
            id: 'final-score',
            label: 'Final Score',
            value: formatScore(p.Final_Score),
            caption: 'Nilai hasil mesin',
            icon: 'award-fill',
            sentiment: 'success',
        },
    ];

    if (!props.modeVerifikasi) {
        const gradeText = displayGrade(gradeTampil.value);
        const gradeCaption = p.Kode_Grade_Sistem && p.Kode_Grade_Final && p.Kode_Grade_Sistem !== p.Kode_Grade_Final
            ? `Sistem: ${displayGrade(p.Kode_Grade_Sistem)} → Final: ${displayGrade(p.Kode_Grade_Final)}`
            : p.Kode_Grade_Sistem
                ? `Grade sistem: ${displayGrade(p.Kode_Grade_Sistem)}`
                : (p.Grade_Sementara ? 'Grade sementara · menunggu kunci kurva' : 'Menunggu kunci kurva');

        stats.push({
            id: 'grade-final',
            label: p.Flag_Dikalibrasi === 'Y' ? 'Grade (Dikalibrasi)' : 'Grade Final',
            value: gradeText,
            caption: gradeCaption,
            icon: 'trophy-fill',
            sentiment: getGradeVariant(gradeTampil.value),
        });
    } else {
        stats.push({
            id: 'status-verifikasi',
            label: 'Status Grade',
            value: 'Menunggu Kurva',
            caption: 'Tahap verifikasi kalkulasi',
            icon: 'hourglass-split',
            sentiment: 'warning',
        });
    }

    return stats;
});

watch(activeTab, (tab) => {
    if (tab === 'balancing') {
        bukaTabBalancing();
    }
});

const penilaiPerKategori = computed(() => {
    const aturan = props.result?.aturan_penilai || {};
    const grup = new Map();

    (props.result?.penilai || []).forEach((p) => {
        const kode = p.Kode_Kategori || 'LAINNYA';

        if (!grup.has(kode)) {
            const a = aturan[kode] || {};

            grup.set(kode, {
                kode,
                nama: p.Nama_Kategori || kode,
                bobotKategori: a.bobot_persen ?? null,
                minSelesai: a.min_rater_selesai ?? a.min_rater ?? null,
                penilai: [],
            });
        }

        grup.get(kode).penilai.push(p);
    });

    return Array.from(grup.values()).map((g) => {
        const aktif = g.penilai.filter((p) => p.Status_Penugasan !== 'DIBATALKAN').length;

        return { ...g, aktif, dibawahAmbang: g.minSelesai !== null && aktif < g.minSelesai };
    });
});

function bagiRata(jumlah) {
    if (jumlah < 1) return [];

    const masing = Math.round((100 / jumlah) * 100) / 100;
    const pertama = Math.round((100 - masing * (jumlah - 1)) * 100) / 100;

    return [pertama, ...Array.from({ length: jumlah - 1 }, () => masing)];
}

function formatPersen(nilai) {
    const angka = Number(nilai);

    if (!Number.isFinite(angka)) return '—';

    return `${angka.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}

const dampakPembatalan = computed(() => {
    const target = assignmentBatal.value;

    if (!target) return null;

    const kategori = penilaiPerKategori.value.find((k) => k.kode === target.Kode_Kategori);

    if (!kategori) return null;

    const tersisa = kategori.penilai
        .filter((p) => p.Status_Penugasan !== 'DIBATALKAN')
        .filter((p) => p.Id_PA_Assignment !== target.Id_PA_Assignment);

    const adaKustom = tersisa.some((p) => p.Bobot_Kustom === 'Y');
    const bobotBaru = adaKustom ? [] : bagiRata(tersisa.length);

    return {
        kategori,
        tersisa,
        adaKustom,
        baris: [...tersisa]
            .sort((a, b) => Number(a.Id_PA_Assignment) - Number(b.Id_PA_Assignment))
            .map((p, i) => ({ ...p, bobotBaru: bobotBaru[i] ?? null })),
        melanggarAmbang: kategori.minSelesai !== null && tersisa.length < kategori.minSelesai,
        kosong: tersisa.length === 0,
    };
});

const pembatalanDitahan = computed(() => {
    const d = dampakPembatalan.value;

    return Boolean(d && (d.kosong || d.melanggarAmbang));
});

function bukaPembatalan(assignment) {
    assignmentBatal.value = assignment;
    alasanBatal.value = '';
    dialogPembatalan.value = true;
}

function batalkan() {
    if (!assignmentBatal.value || !alasanBatal.value.trim()) return;

    if (pembatalanDitahan.value) return;
    emit('cancel-assignment', { assignment: assignmentBatal.value, alasan: alasanBatal.value.trim() });
}

const raterList = computed(() => detail.value?.penilai || []);

const activeRaters = computed(() => {
    return raterList.value.filter((r) => r.Status_Penugasan !== 'DIBATALKAN');
});

const penilaiStatistik = computed(() => {
    const list = activeRaters.value;
    const totalAktif = list.length;
    const totalSelesai = list.filter((r) => r.Status_Penugasan === 'SELESAI').length;
    const totalTerlambat = list.filter((r) => r.terlambat).length;
    const totalKategoriKritis = penilaiPerKategori.value.filter((k) => k.dibawahAmbang).length;
    const persenSelesai = totalAktif > 0 ? Math.round((totalSelesai / totalAktif) * 100) : 0;
    return {
        totalAktif,
        totalSelesai,
        totalTerlambat,
        totalKategoriKritis,
        persenSelesai,
        semuaSelesai: totalAktif > 0 && totalSelesai >= totalAktif,
    };
});

const penugasanSubTab = ref('daftar');
const selectedKalibrasiAssignmentId = ref(null);

const kalibrasiPenilaiList = computed(() => props.result?.kalibrasi_penilai || []);
const totalKalibrasiPenilai = computed(() => kalibrasiPenilaiList.value.length);

const kalibrasiCountPerAssignment = computed(() => {
    const map = new Map();
    kalibrasiPenilaiList.value.forEach((k) => {
        const id = Number(k.assignment_id);
        if (id) {
            map.set(id, (map.get(id) || 0) + 1);
        }
    });
    return map;
});

function raterKalibrasiCount(assignmentId) {
    return kalibrasiCountPerAssignment.value.get(Number(assignmentId)) || 0;
}

function lihatKalibrasiRater(assignmentId) {
    selectedKalibrasiAssignmentId.value = assignmentId;
    penugasanSubTab.value = 'kalibrasi';
}

const penugasanSubTabs = computed(() => [
    {
        key: 'daftar',
        label: 'Daftar Penugasan & Kategori',
        icon: 'people-fill',
        badge: penilaiStatistik.value.totalAktif > 0 ? `${penilaiStatistik.value.totalSelesai}/${penilaiStatistik.value.totalAktif}` : null,
        badgeVariant: penilaiStatistik.value.semuaSelesai ? 'success' : 'neutral',
    },
    {
        key: 'kalibrasi',
        label: 'Riwayat Kalibrasi Penilai',
        icon: 'pencil-square',
        badge: totalKalibrasiPenilai.value > 0 ? totalKalibrasiPenilai.value : null,
        badgeVariant: totalKalibrasiPenilai.value > 0 ? 'purple' : 'neutral',
    },
]);

const unfinishedRaters = computed(() => {
    return activeRaters.value.filter((r) => r.Status_Penugasan !== 'SELESAI');
});

const hasFinishedRaters = computed(() => {
    return activeRaters.value.some((r) => r.Status_Penugasan === 'SELESAI');
});

const isSiapHitungPertama = computed(() => {
    return activeRaters.value.length > 0 && unfinishedRaters.value.length === 0 && hasFinishedRaters.value;
});

const hasCalculatedScore = computed(() => {
    const p = detail.value?.peserta;
    if (!p) return false;
    const scoreValid = p.Final_Score !== null && p.Final_Score !== undefined && p.Final_Score !== '';
    const statusTerhitung = ['DIHITUNG', 'DIKALIBRASI', 'DITERBITKAN', 'TERBIT'].includes(p.Status_Hasil);
    return Boolean(scoreValid || statusTerhitung);
});

const isPublished = computed(() => {
    const p = detail.value?.peserta;
    if (!p) return false;
    return p.Status_Hasil === 'TERBIT' || p.Status_Hasil === 'DITERBITKAN' || Boolean(p.Published_At);
});

const isTertahan = computed(() => {
    const p = detail.value?.peserta;
    if (!p) return false;
    return p.Status_Hasil === 'TERTAHAN';
});

/* Kenapa hitung ulang DITUTUP, atau null bila boleh. Cermin penjaga server
   (PaResultCalculationService): hasil TERBIT atau yang sudah dirilis ke leader tidak
   dihitung ulang karena angkanya sudah dibaca orang. Dulu tombolnya tetap tampil dan
   selalu berakhir galat; kini tombolnya disembunyikan dan sebabnya yang ditampilkan. */
const alasanTakBisaHitung = computed(() => {
    const p = detail.value?.peserta;
    if (!p) return null;
    if (p.Status_Hasil === 'TERBIT') {
        return 'Hasil sudah terbit, jadi tidak dihitung ulang. Pakai kalibrasi bila nilainya perlu digeser.';
    }
    if (p.Published_Leader_At) {
        return 'Hasil sudah dirilis ke leader. Cabut rilis leader lebih dulu untuk menghitung ulang.';
    }
    return null;
});

const canShowHitung = computed(() => {
    if (!props.canCalculate) return false;
    const peserta = detail.value?.peserta;
    if (!peserta) return false;
    if (peserta.Status_Peserta === 'DIKECUALIKAN') return false;
    if (alasanTakBisaHitung.value) return false;

    // Sudah terhitung -> boleh Hitung Ulang
    if (hasCalculatedScore.value) return true;
    // Sedang tertahan -> boleh Hitung Ulang Evaluasi setelah perbaikan
    if (isTertahan.value) return true;
    // Belum pernah dihitung -> hanya muncul jika seluruh penilai aktif sudah selesai mengisi
    return isSiapHitungPertama.value;
});

const labelHitung = computed(() => {
    if (isTertahan.value) return 'Hitung Ulang Evaluasi';
    if (hasCalculatedScore.value) return 'Hitung Ulang';
    return 'Hitung Peserta';
});

const canShowTerbitkan = computed(() => {
    if (!props.canPublish) return false;
    const peserta = detail.value?.peserta;
    if (!peserta || !peserta.Id_PA_Result) return false;
    /* Hanya DIHITUNG — satu-satunya status yang diterima PaResultPublishService::terbitkanPeserta.
       TERBIT ditolak ("sudah terbit"), TERTAHAN dan BELUM_DIHITUNG tidak berangka. "Terbitkan
       Ulang" tetap muncul untuk hasil DIHITUNG yang penerbitannya pernah dibatalkan. */
    return peserta.Status_Hasil === 'DIHITUNG';
});

/* Rilis ke leader memakai izin yang sama dengan penerbitan di layar ini: keduanya keputusan
   admin siklus. Yang membedakan hanyalah SIAPA yang jadi bisa melihat. */
const sudahDirilisKeLeader = computed(() => Boolean(detail.value?.peserta?.Published_Leader_At));

const canShowRilisLeader = computed(() => {
    if (!props.canPublish) return false;
    const peserta = detail.value?.peserta;
    if (!peserta || !peserta.Id_PA_Result) return false;
    if (!hasCalculatedScore.value || isTertahan.value) return false;
    // Sudah terbit: leader melihatnya lewat pintu itu, jadi tombol ini tidak ada gunanya.
    if (isPublished.value) return false;
    return true;
});

const labelTerbitkan = computed(() => {
    return isPublished.value ? 'Terbitkan Ulang' : 'Terbitkan Hasil';
});

function hitungPeserta() {
    if (!props.participant) return;
    emit('calculate');
}

function rilisKeLeader() {
    dialogRilisLeader.value = false;
    emit('release-leader');
}

function cabutRilisLeader() {
    const alasan = alasanCabutLeader.value.trim();

    if (alasan.length < 5) {
        galatCabutLeader.value = 'Alasan wajib diisi, minimal 5 karakter.';
        return;
    }

    galatCabutLeader.value = '';
    dialogCabutLeader.value = false;
    emit('revoke-leader', alasan);
    alasanCabutLeader.value = '';
}

function terbitkanPeserta() {
    if (!props.participant || !props.result?.peserta?.Id_PA_Result || publishing.value) return;
    emit('publish');
}

watch(
    () => props.actionSuccess,
    (success, previous) => {
        if (success && success !== previous) {
            dialogPembatalan.value = false;
            assignmentBatal.value = null;
            alasanBatal.value = '';
        }
    },
    { immediate: true },
);

watch(
    () => props.publishSuccess,
    (success, previous) => {
        if (success && success !== previous) dialogPenerbitan.value = false;
    },
    { immediate: true },
);
</script>

<style scoped>
.pa-result {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    min-width: 0;
}

/* Hero Cockpit Profile Card */
.pa-result-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 1rem 1.25rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.85));
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    flex-wrap: wrap;
}

.pa-result-hero__profile {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
    flex: 1 1 auto;
}

.pa-result-hero__meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.pa-result-hero__name-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-result-hero__name {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.pa-result-hero__code {
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.72rem;
    font-weight: 750;
    color: #4338ca;
    background: #eef2ff;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    border: 1px solid #e0e7ff;
}

.pa-result-hero__level-badge {
    font-size: 0.72rem;
    font-weight: 700;
    color: #334155;
    background: #ffffff;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    border: 1px solid #cbd5e1;
}

.pa-result-hero__sub {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
    flex-wrap: wrap;
}

.pa-result-hero__kuadran-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 650;
    color: #4338ca;
}

.pa-result-hero__calc-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 600;
}

.pa-hero-sep {
    opacity: 0.4;
}

.pa-result-hero__score-widget {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin-left: auto;
    background: #ffffff;
    padding: 0.5rem 0.85rem;
    border-radius: var(--hcis-radius-md, 10px);
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    flex-shrink: 0;
}

.pa-hero-score-block,
.pa-hero-grade-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.1rem;
}

.pa-hero-score-divider {
    width: 1px;
    height: 1.8rem;
    background: #e2e8f0;
}

.pa-hero-score-label {
    font-size: 0.62rem;
    font-weight: 750;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--hcis-text-muted, #64748b);
}

.pa-hero-score-val {
    font-size: 1.25rem;
    font-weight: 850;
    color: #047857;
    font-variant-numeric: tabular-nums;
}

.pa-hero-grade-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2.2rem;
    height: 1.85rem;
    padding: 0 0.55rem;
    font-size: 1.15rem;
    font-weight: 850;
    border-radius: 6px;
    transition: all 120ms ease;
}

.pa-hero-grade-badge.is-success {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.pa-hero-grade-badge.is-brand {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.pa-hero-grade-badge.is-warning {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.pa-hero-grade-badge.is-danger {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.pa-hero-grade-badge.is-neutral {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

/* Tabs Switcher */
.pa-result__tabs {
    margin-bottom: 0.25rem;
}

/* Section Container Pane */
.pa-section-pane {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.text-brand {
    color: #4f46e5;
}

.text-purple {
    color: #9333ea;
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.64rem;
    letter-spacing: 0.1em;
    font-weight: 800;
}

.pa-pre-cap-note,
.pa-held-inline {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    padding: 0.65rem 0.8rem;
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.76rem;
    line-height: 1.45;
}

.pa-pre-cap-note {
    color: #3730a3;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(238, 242, 255, 0.68);
}

.pa-pre-cap-note strong {
    color: #312e81;
    font-variant-numeric: tabular-nums;
}

.pa-held-inline {
    color: #92400e;
    border: 1px solid rgba(245, 158, 11, 0.25);
    background: rgba(255, 251, 235, 0.72);
}

.pa-held-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    color: #92400e;
    border: 1px solid rgba(245, 158, 11, 0.35);
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(255, 251, 235, 0.95), rgba(255, 255, 255, 0.8));
}

.pa-held-banner div {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-held-banner strong {
    font-size: 0.82rem;
}

.pa-held-banner span {
    font-size: 0.76rem;
    line-height: 1.45;
}

.pa-held-banner__reason {
    overflow-wrap: anywhere;
}

.pa-recalculation {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.7rem 0.85rem;
    color: #047857;
    border: 1px solid rgba(16, 185, 129, 0.26);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(236, 253, 245, 0.76);
    font-size: 0.76rem;
}

.pa-radar-category-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
    gap: 1rem;
    align-items: stretch;
}

.pa-radar-category-grid > * {
    min-width: 0;
}

.pa-wawasan-container {
    width: 100%;
}

/* Category Table & Rows */
.pa-category-table {
    display: grid;
    gap: 0.7rem;
}

/* Category Summary Strip */
.pa-cat-summary-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.85));
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.pa-cat-summary-strip__left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 0;
}

.pa-cat-summary-strip__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    flex-shrink: 0;
}

.pa-cat-summary-strip__icon.is-complete {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-cat-summary-strip__info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-cat-summary-strip__label {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-text-muted, #64748b);
}

.pa-cat-summary-strip__val {
    font-size: 0.82rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-cat-summary-strip__right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-shrink: 0;
}

.pa-cat-summary-strip__bar-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 90px;
}

.pa-cat-summary-strip__bar {
    flex: 1;
    height: 6px;
    background: #e2e8f0;
    border-radius: 9999px;
    overflow: hidden;
    min-width: 55px;
}

.pa-cat-summary-strip__bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    border-radius: 9999px;
    transition: width 0.3s ease;
}

.pa-cat-summary-strip__bar-fill.is-complete {
    background: linear-gradient(90deg, #10b981, #059669);
}

.pa-cat-summary-strip__pct {
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.72rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

/* Category Item Cards */
.pa-category-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
}

.pa-category-row:hover {
    border-color: #cbd5e1;
    background: #fafafa;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    transform: translateY(-1px);
}

.pa-category-row--brand:hover {
    border-color: rgba(99, 102, 241, 0.4);
}

.pa-category-row--purple:hover {
    border-color: rgba(168, 85, 247, 0.4);
}

.pa-category-row--teal:hover {
    border-color: rgba(20, 184, 166, 0.4);
}

.pa-category-row--warning:hover {
    border-color: rgba(245, 158, 11, 0.4);
}

.pa-category-row__main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1;
}

/* Category Jewel */
.pa-cat-jewel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--hcis-radius-md, 8px);
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.pa-category-row:hover .pa-cat-jewel {
    transform: scale(1.05);
}

.pa-cat-jewel.is-brand {
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.pa-cat-jewel.is-purple {
    background: rgba(168, 85, 247, 0.1);
    color: #9333ea;
    border: 1px solid rgba(168, 85, 247, 0.2);
}

.pa-cat-jewel.is-teal {
    background: rgba(20, 184, 166, 0.1);
    color: #0d9488;
    border: 1px solid rgba(20, 184, 166, 0.2);
}

.pa-cat-jewel.is-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.pa-cat-jewel.is-neutral {
    background: rgba(100, 116, 139, 0.1);
    color: #475569;
    border: 1px solid rgba(100, 116, 139, 0.2);
}

.pa-category-row__left {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
    flex: 1;
}

.pa-category-row__title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.pa-category-row__title strong {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.84rem;
    font-weight: 750;
    letter-spacing: -0.01em;
}

.pa-cat-code-pill {
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.65rem;
    font-weight: 750;
    color: #475569;
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    letter-spacing: 0.03em;
}

.pa-category-row__meter-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    max-width: 260px;
}

.pa-category-row__meter {
    height: 4px;
    background: #f1f5f9;
    border-radius: 9999px;
    overflow: hidden;
    width: 100%;
}

.pa-category-row__meter-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.3s ease;
}

.pa-category-row__meter-fill.is-brand {
    background: linear-gradient(90deg, #6366f1, #4f46e5);
}

.pa-category-row__meter-fill.is-purple {
    background: linear-gradient(90deg, #a855f7, #9333ea);
}

.pa-category-row__meter-fill.is-teal {
    background: linear-gradient(90deg, #14b8a6, #0d9488);
}

.pa-category-row__meter-fill.is-warning {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.pa-category-row__meter-fill.is-neutral {
    background: linear-gradient(90deg, #94a3b8, #64748b);
}

.pa-category-row__meter-fill.is-complete {
    background: linear-gradient(90deg, #10b981, #059669) !important;
}

.pa-category-row__progress-row {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-category-row__count {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.72rem;
    font-weight: 500;
}

.pa-category-row__pct {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.72rem;
    font-weight: 700;
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
}

.pa-bobot-container {
    margin-top: 1.25rem;
}

.pa-bobot-card {
    border-radius: var(--hcis-radius-lg, 12px);
    overflow: hidden;
}

.pa-bobot-berlaku {
    background: #f8fafc;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-md, 10px);
    padding: 1rem;
}

.pa-bobot-berlaku__catatan {
    color: var(--hcis-text-muted, #475569);
    font-size: 0.82rem;
    line-height: 1.5;
}

.pa-bobot-berlaku__tabel-container {
    width: 100%;
    overflow-x: auto;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
}

.pa-bobot-berlaku__tabel {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.pa-bobot-berlaku__tabel th,
.pa-bobot-berlaku__tabel td {
    padding: 0.625rem 0.85rem;
    text-align: left;
    font-weight: 400;
    border-bottom: 1px solid rgba(241, 245, 249, 0.9);
}

.pa-bobot-berlaku__tabel thead th {
    background: #f8fafc;
    color: var(--hcis-text-muted, #475569);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid rgba(226, 232, 240, 0.95);
}

.pa-bobot-berlaku__tabel .is-angka {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.pa-bobot-berlaku__kategori {
    background: #ffffff;
    transition: background-color 0.15s ease;
}

.pa-bobot-berlaku__kategori:hover {
    background: #f8fafc;
}

.pa-bobot-berlaku__kategori.is-excluded {
    background: rgba(254, 243, 199, 0.22);
}

.pa-bobot-berlaku__kategori th {
    font-weight: 600;
    color: var(--hcis-text-main, #0f172a);
}

.pa-bobot-cat-head {
    display: inline-flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.pa-bobot-cat-nama {
    font-weight: 700;
    font-size: 0.85rem;
    color: #1e293b;
}

.pa-bobot-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.pa-bobot-cat-valid {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    font-weight: 500;
    color: #059669;
}

.pa-bobot-pct-rencana {
    font-size: 0.82rem;
    font-weight: 500;
    color: #64748b;
}

.pa-bobot-pct-berlaku {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
}

.pa-bobot-pct-berlaku.is-zero {
    color: #94a3b8;
    text-decoration: line-through;
}

.pa-bobot-berlaku__penilai {
    background: #fafafa;
}

.pa-bobot-berlaku__penilai:hover {
    background: #f1f5f9;
}

.pa-bobot-penilai-cell {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding-left: 1.15rem;
}

.pa-bobot-penilai-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #94a3b8;
    flex-shrink: 0;
}

.pa-bobot-penilai-nama {
    font-size: 0.78rem;
    font-weight: 500;
    color: #334155;
}

.pa-bobot-berlaku__sebab {
    font-size: 0.7rem;
    font-weight: 500;
    color: #b45309;
    background: #fffbeb;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    border: 1px solid rgba(245, 158, 11, 0.2);
    margin-left: 0.25rem;
}

.pa-bobot-penilai-pct {
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
}

.pa-bobot-berlaku__tabel tfoot {
    background: #f8fafc;
    border-top: 2px solid rgba(203, 213, 225, 0.8);
}

.pa-bobot-berlaku__tabel tfoot th,
.pa-bobot-berlaku__tabel tfoot td {
    padding: 0.75rem 0.85rem;
    border-bottom: 0;
    font-size: 0.85rem;
}

.pa-category-row__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.25rem;
    flex-shrink: 0;
}

.pa-cat-score-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.pa-cat-score-label {
    font-size: 0.62rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-text-muted, #64748b);
}

.pa-cat-score-val {
    color: #312e81;
    font-size: 1.15rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    line-height: 1.15;
}

/* Kalibrasi Stale Alert */
.pa-kalibrasi-basi {
    margin-top: 0.25rem;
}

/* Penugasan Penilai Section */
.pa-assignment-table {
    display: grid;
    gap: 1.15rem;
}

/* Penugasan Subtabs */
.pa-penugasan-subtabs-wrap {
    margin-bottom: 0.85rem;
}

.pa-penugasan-subpane {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Overview Cockpit */
.pa-assign-cockpit {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
}

.pa-assign-stat-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.pa-assign-stat-card.is-interactive {
    cursor: pointer;
}

.pa-assign-stat-card.is-interactive:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
    border-color: #cbd5e1;
}

.pa-assign-stat-card.is-active {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.7), rgba(248, 250, 252, 0.95));
    border-color: rgba(99, 102, 241, 0.2);
}

.pa-assign-stat-card.is-progress {
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.7), rgba(248, 250, 252, 0.95));
    border-color: rgba(16, 185, 129, 0.2);
}

.pa-assign-stat-card.is-danger {
    background: linear-gradient(135deg, rgba(254, 242, 242, 0.7), rgba(248, 250, 252, 0.95));
    border-color: rgba(239, 68, 68, 0.2);
}

.pa-assign-stat-card.is-warning {
    background: linear-gradient(135deg, rgba(254, 252, 232, 0.7), rgba(248, 250, 252, 0.95));
    border-color: rgba(245, 158, 11, 0.2);
}

.pa-assign-stat-card.is-success {
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.7), rgba(248, 250, 252, 0.95));
    border-color: rgba(16, 185, 129, 0.2);
}

.pa-assign-stat-card.is-purple {
    background: linear-gradient(135deg, rgba(245, 243, 255, 0.8), rgba(248, 250, 252, 0.95));
    border-color: rgba(139, 92, 246, 0.25);
    border-left: 3px solid #8b5cf6;
}

.pa-assign-stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: var(--hcis-radius-sm, 6px);
    flex-shrink: 0;
}

.pa-assign-stat-icon.is-active {
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
}

.pa-assign-stat-icon.is-purple {
    background: rgba(139, 92, 246, 0.12);
    color: #7c3aed;
}

.pa-assign-stat-sub {
    font-size: 0.65rem;
    color: var(--hcis-text-muted, #94a3b8);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-assign-stat-icon.is-progress {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-assign-stat-icon.is-danger {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
}

.pa-assign-stat-icon.is-warning {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.pa-assign-stat-icon.is-success {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.pa-assign-stat-info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-assign-stat-lbl {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-text-muted, #64748b);
}

.pa-assign-stat-num {
    font-size: 0.92rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.pa-assign-progress-val {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
}

.pa-assign-stat-pct {
    font-size: 0.72rem;
    font-weight: 700;
    color: #059669;
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
}

/* Category Group Container */
.pa-rater-kategori-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-lg, 10px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    transition: all 140ms ease;
}

.pa-rater-kategori-card.is-brand {
    border-left: 3.5px solid #6366f1;
}

.pa-rater-kategori-card.is-purple {
    border-left: 3.5px solid #a855f7;
}

.pa-rater-kategori-card.is-teal {
    border-left: 3.5px solid #14b8a6;
}

.pa-rater-kategori-card.is-warning {
    border-left: 3.5px solid #f59e0b;
}

.pa-rater-kategori__kepala {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.6rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #f1f5f9);
}

.pa-rater-kategori__title-wrap {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.pa-rater-kategori__name-block {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-rater-kategori__name-line {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-rater-kategori__name-line strong {
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-rater-kategori__porsi {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 500;
}

.pa-rater-kategori__ambang-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-rater-kategori__ambang {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
    background: #f1f5f9;
    font-size: 0.72rem;
    font-weight: 650;
    color: #475569;
    font-variant-numeric: tabular-nums;
}

.pa-rater-kategori__ambang--kurang {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fee2e2;
    font-weight: 750;
}

.pa-rater-kategori__peringatan {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 0.75rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: #fef2f2;
    border: 1px solid #fecaca;
    font-size: 0.76rem;
    line-height: 1.45;
    color: #b91c1c;
}

.pa-rater-kategori__list {
    display: grid;
    gap: 0.55rem;
}

/* Individual Assignment Row */
.pa-assignment-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    transition: all 140ms ease;
}

.pa-assignment-row:hover {
    border-color: #cbd5e1;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.pa-assignment-row--cancelled {
    opacity: 0.65;
    background: #f1f5f9;
}

.pa-assignment-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.08);
    flex-shrink: 0;
}

.pa-assignment-avatar.is-completed {
    background: rgba(16, 185, 129, 0.12);
}

.pa-assignment-avatar.is-cancelled {
    background: rgba(239, 68, 68, 0.1);
}

.pa-assignment-row__info {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    flex: 1.2;
}

.pa-assignment-row__name-line {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-assignment-row__name {
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.82rem;
    font-weight: 750;
}

.pa-assignment-nik-mono {
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.65rem;
    font-weight: 700;
    color: #475569;
    background: #ffffff;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.pa-rater-kalibrasi-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 600;
    color: #7c3aed;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.25);
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-rater-kalibrasi-pill:hover {
    background: #7c3aed;
    color: #ffffff;
    border-color: #7c3aed;
}

.pa-assignment-row__sub {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.72rem;
    flex-wrap: wrap;
}

.pa-weight-tag {
    font-weight: 700;
    color: #4338ca;
}

.pa-assignment-row__status {
    flex-shrink: 0;
}

.pa-assignment-row__meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem 0.5rem;
    min-width: 0;
}

.pa-due-text {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-assignment-row__action {
    flex-shrink: 0;
    margin-left: auto;
}

/* ==========================================================================
   QUALITATIVE NARRATIVE & FEEDBACK SYSTEM (SECTION 3) - UNIFIED WORKSPACE
   ========================================================================== */
.pa-naratif-cockpit {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin-bottom: 0.85rem;
}

.pa-naratif-guide-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0.95rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.7), rgba(245, 243, 255, 0.6));
    border: 1px solid rgba(199, 210, 254, 0.9);
    margin-bottom: 0.85rem;
}

.pa-naratif-guide-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 6px;
    background: rgba(79, 70, 229, 0.1);
    color: #4f46e5;
    flex-shrink: 0;
    margin-top: 0.05rem;
}

.pa-naratif-guide-text {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.76rem;
    line-height: 1.5;
    color: var(--hcis-text-strong, #1e293b);
}

.pa-naratif-guide-text strong {
    color: #4338ca;
    font-weight: 750;
}

.pa-naratif-guide-text code {
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.7rem;
    font-weight: 700;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

/* Sub-Tab Navigation Bar: Segmented Dimension Pills */
.pa-dimensi-pills-bar {
    margin-bottom: 0.85rem;
    padding: 0.35rem 0.45rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #f1f5f9;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-dimensi-pills-scroll {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow-x: auto;
    scrollbar-width: thin;
}

.pa-dimensi-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid transparent;
    background: transparent;
    color: var(--hcis-text-muted, #475569);
    font-family: inherit;
    font-size: 0.76rem;
    font-weight: 650;
    cursor: pointer;
    white-space: nowrap;
    transition: all 150ms cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
}

.pa-dimensi-pill:hover {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
}

.pa-dimensi-pill.is-active {
    background: #ffffff;
    color: #4338ca;
    border-color: #c7d2fe;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.12);
    font-weight: 750;
}

.pa-dimensi-pill.is-active.is-success {
    color: #047857;
    border-color: #a7f3d0;
}

.pa-dimensi-pill.is-active.is-warning {
    color: #b45309;
    border-color: #fde68a;
}

.pa-dimensi-pill.is-active.is-purple {
    color: #6d28d9;
    border-color: #ddd6fe;
}

.pa-dimensi-pill.is-active.is-brand {
    color: #4338ca;
    border-color: #c7d2fe;
}

.pa-dimensi-pill__status-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 999px;
    background: #cbd5e1;
    flex-shrink: 0;
    transition: all 150ms ease;
}

.pa-dimensi-pill__status-dot.is-filled {
    background: #10b981;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
}

.pa-dimensi-pill__name {
    font-size: 0.76rem;
}

.pa-dimensi-pill__badge {
    font-size: 0.65rem;
    font-weight: 750;
    padding: 0.05rem 0.35rem;
    background: #e2e8f0;
    color: #475569;
    border-radius: 999px;
}

.pa-dimensi-pill__count {
    font-size: 0.65rem;
    font-weight: 750;
    padding: 0.05rem 0.35rem;
    background: #e0e7ff;
    color: #4338ca;
    border-radius: 999px;
}

.pa-dimensi-pill.is-active .pa-dimensi-pill__count {
    background: #4f46e5;
    color: #ffffff;
}

.pa-dimensi-no-questions-notice {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 1rem 1.1rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    margin-top: 0.5rem;
}

.pa-dimensi-no-questions-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    background: #eef2ff;
    color: #4f46e5;
    flex-shrink: 0;
}

.pa-dimensi-no-questions-text {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.76rem;
    line-height: 1.5;
    color: var(--hcis-text-strong, #334155);
}

.pa-dimensi-no-questions-text strong {
    color: var(--hcis-text-strong, #0f172a);
    font-weight: 700;
}

.pa-kualitatif-workspace {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    gap: 1.1rem;
    align-items: start;
}

.pa-kualitatif-workspace--single {
    grid-template-columns: 1fr;
}

.pa-kualitatif-workspace__left {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-width: 0;
}

.pa-kualitatif-workspace__right {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-width: 0;
    position: sticky;
    top: 0.75rem;
}

.pa-kualitatif-workspace__right .pa-notes__form-grid {
    grid-template-columns: 1fr;
}

.pa-kualitatif-workspace--single .pa-notes__form-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.pa-naratif-stat-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 140ms ease;
}

.pa-naratif-stat-card:hover {
    border-color: rgba(199, 210, 254, 0.9);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.06);
}

.pa-naratif-stat-card--primary {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.6), #ffffff);
    border-color: rgba(199, 210, 254, 0.95);
}

.pa-naratif-stat-icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--hcis-radius-md, 8px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-naratif-stat-icon.is-purple {
    background: rgba(147, 51, 234, 0.1);
    color: #7e22ce;
}

.pa-naratif-stat-icon.is-brand {
    background: rgba(79, 70, 229, 0.1);
    color: #4f46e5;
}

.pa-naratif-stat-icon.is-teal {
    background: rgba(13, 148, 136, 0.1);
    color: #0f766e;
}

.pa-naratif-stat-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-naratif-stat-label {
    font-size: 0.68rem;
    font-weight: 750;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-naratif-stat-val-row {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    margin-top: 0.1rem;
}

.pa-naratif-stat-num {
    font-size: 1.15rem;
    font-weight: 850;
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-naratif-stat-pct {
    font-size: 0.78rem;
    font-weight: 750;
    color: #4338ca;
}

.pa-naratif-stat-unit {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
}

.pa-naratif-stat-sub {
    font-size: 0.68rem;
    color: #94a3b8;
    margin-top: 0.15rem;
}

.pa-naratif-stat-badge-row {
    margin-top: 0.2rem;
}

/* 2. Qualitative Multi-Rater Section */
.pa-kualitatif-card-header {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    width: 100%;
}

.pa-kualitatif-card-header__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.85rem;
    width: 100%;
}

.pa-kualitatif-card-header__title-group {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    flex: 1;
    min-width: 0;
}

.pa-kualitatif-card-header__icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    background: linear-gradient(135deg, rgba(147, 51, 234, 0.12), rgba(126, 34, 206, 0.2));
    color: #7e22ce;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-kualitatif-card-header__text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-kualitatif-card-header__kicker {
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #7e22ce;
    text-transform: uppercase;
}

.pa-kualitatif-card-header__title {
    margin: 0;
    font-size: 0.98rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.3;
}

.pa-kualitatif-card-header__actions {
    flex-shrink: 0;
}

.pa-kualitatif-card-header__desc {
    margin: 0;
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.45;
}

.pa-kualitatif-card-header__chips-bar {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-top: 0.45rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
    flex-wrap: wrap;
}

.pa-kualitatif-chips-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-naratif-card-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-naratif-filter-chips {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-naratif-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    background: #f1f5f9;
    border: 1px solid rgba(203, 213, 225, 0.8);
    font: inherit;
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #475569);
    cursor: pointer;
    transition: all 130ms ease;
}

.pa-naratif-chip:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.pa-naratif-chip.is-active {
    background: #7e22ce;
    border-color: #7e22ce;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(126, 34, 206, 0.25);
}

.pa-naratif-competencies-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-naratif-group-card {
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    overflow: hidden;
    transition: border-color 140ms ease;
}

.pa-naratif-group-card.is-expanded {
    border-color: rgba(199, 210, 254, 0.95);
}

.pa-naratif-group-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid transparent;
    cursor: pointer;
    user-select: none;
    transition: all 140ms ease;
}

.pa-naratif-group-card.is-expanded .pa-naratif-group-header {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.6), rgba(248, 250, 252, 0.9));
    border-bottom-color: rgba(226, 232, 240, 0.9);
}

.pa-naratif-group-header:hover {
    background: #f1f5f9;
}

.pa-naratif-group-header-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-naratif-group-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 6px;
    background: rgba(147, 51, 234, 0.1);
    color: #7e22ce;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-naratif-group-title-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-naratif-group-title {
    font-size: 0.88rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-naratif-group-sub {
    font-size: 0.7rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-naratif-group-header-right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-naratif-chevron-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    color: #64748b;
    transition: transform 200ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-naratif-chevron-btn.is-open {
    transform: rotate(180deg);
}

.pa-naratif-group-body {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1rem;
    background: #ffffff;
}

.pa-naratif-question-block {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(248, 250, 252, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-question-prompt-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding-bottom: 0.45rem;
    border-bottom: 1px dashed rgba(203, 213, 225, 0.8);
}

.pa-question-prompt-left {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-question-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #4338ca;
    background: #eef2ff;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    border: 1px solid #e0e7ff;
}

.pa-question-code {
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.68rem;
    color: #64748b;
    background: #ffffff;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.pa-question-stat {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-question-stat strong {
    color: var(--hcis-text-strong, #0f172a);
    font-variant-numeric: tabular-nums;
}

.pa-answers-bubble-grid {
    display: grid;
    gap: 0.65rem;
}

.pa-answer-bubble-card {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.75rem 0.85rem;
    border-radius: var(--hcis-radius-sm, 8px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02);
}

.pa-answer-bubble-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-answer-header-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-quote-insert-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(199, 210, 254, 0.85);
    color: #4f46e5;
    font-family: inherit;
    font-size: 0.68rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 140ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-quote-insert-btn:hover {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.28);
}

.pa-quote-insert-btn:active {
    transform: translateY(0);
}

.pa-rater-author-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-rater-avatar-chip {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #7c3aed, #9333ea);
    color: #ffffff;
    font-size: 0.65rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-rater-name-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-rater-name {
    font-size: 0.8rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-rater-category-pill {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.08rem 0.4rem;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.pa-rater-category-pill.is-brand { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.pa-rater-category-pill.is-purple { background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; }
.pa-rater-category-pill.is-teal { background: #f0fdfa; color: #0d9488; border: 1px solid #99f6e4; }
.pa-rater-category-pill.is-warning { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.pa-rater-category-pill.is-neutral { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

.pa-answer-bubble-quote {
    position: relative;
    padding-left: 1.4rem;
}

.pa-answer-quote-icon {
    position: absolute;
    left: 0;
    top: 0.1rem;
    color: #cbd5e1;
}

.pa-answer-question {
    margin: 0 0 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    overflow-wrap: anywhere;
}

.pa-answer-text {
    margin: 0;
    font-size: 0.82rem;
    line-height: 1.6;
    color: var(--hcis-text-strong, #1e293b);
    /* Jawaban esai berformat (daftar, paragraf) datang sebagai teks berbaris dari server. */
    white-space: pre-line;
}

.pa-question-empty-notice {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.6rem 0.75rem;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px dashed rgba(203, 213, 225, 0.8);
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
    font-style: italic;
}

@media (max-width: 900px) {
    .pa-naratif-cockpit {
        grid-template-columns: 1fr;
    }
    .pa-catatan-report-grid {
        grid-template-columns: 1fr;
    }
}

/* Admin Notes Form & Rich Text Cards */
.pa-notes-block {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-notes-hero-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.7), rgba(245, 243, 255, 0.5));
    border: 1px solid rgba(199, 210, 254, 0.8);
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.04);
}

.pa-notes-hero-left {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}

.pa-notes-hero-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 10px;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
}

.pa-notes-hero-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-notes-hero-title-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-notes-hero-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-notes-hero-badge {
    font-size: 0.65rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.12rem 0.5rem;
    border-radius: 999px;
}

.pa-notes-hero-desc {
    margin: 0;
    font-size: 0.78rem;
    line-height: 1.5;
    color: var(--hcis-text-muted, #475569);
}

.pa-notes__form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.pa-notes__dimension-card {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem 1.1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 180ms ease;
}

.pa-notes__dimension-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.pa-notes__dimension-card.is-success {
    border-top: 3px solid #10b981;
}

.pa-notes__dimension-card.is-warning {
    border-top: 3px solid #f59e0b;
}

.pa-notes__dimension-card.is-purple {
    border-top: 3px solid #8b5cf6;
}

.pa-notes__dimension-card.is-brand {
    border-top: 3px solid #6366f1;
}

.pa-notes__dimension-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-notes__dimension-title-group {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    min-width: 0;
}

.pa-notes__dimension-icon-badge {
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.pa-notes__dimension-card.is-success .pa-notes__dimension-icon-badge {
    background: #ecfdf5;
    color: #059669;
}

.pa-notes__dimension-card.is-warning .pa-notes__dimension-icon-badge {
    background: #fffbeb;
    color: #d97706;
}

.pa-notes__dimension-card.is-purple .pa-notes__dimension-icon-badge {
    background: #f5f3ff;
    color: #7c3aed;
}

.pa-notes__dimension-card.is-brand .pa-notes__dimension-icon-badge {
    background: #eef2ff;
    color: #4f46e5;
}

.pa-notes__dimension-card.is-neutral .pa-notes__dimension-icon-badge {
    background: #f1f5f9;
    color: #475569;
}

.pa-notes__dimension-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-notes__dimension-name-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-notes__dimension-name {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-notes__dimension-code {
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
    font-size: 0.68rem;
    font-weight: 750;
    color: #4338ca;
    background: #eef2ff;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    border: 1px solid #e0e7ff;
}

.pa-notes__dimension-guide {
    font-size: 0.73rem;
    line-height: 1.4;
    color: var(--hcis-text-muted, #64748b);
}

.pa-notes__dimension-actions {
    margin-left: auto;
    flex-shrink: 0;
}

.pa-notes__dimension-body {
    width: 100%;
}

.pa-notes__form-grid--single {
    grid-template-columns: 1fr !important;
}

.pa-notes__dimension-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 0.65rem;
    margin-top: 0.25rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.9);
    flex-wrap: wrap;
}

.pa-notes__dimension-footer-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-notes__dimension-step-tag {
    font-size: 0.72rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
}

.pa-notes__dimension-footer-nav {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
}

.pa-notes__galat {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-text-danger, #b91c1c);
    padding: 0.4rem 0.65rem;
    background: #fef2f2;
    border-radius: 6px;
    border: 1px solid #fecaca;
}

.pa-notes__action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: var(--hcis-surface-subtle, #f8fafc);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    flex-wrap: wrap;
}

.pa-notes__action-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
}

/* Dialog Subcomponents & Feedback */
.pa-result__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    min-height: 16rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-dialog-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.74rem;
    font-weight: 550;
}

.pa-modal-copy {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.8125rem;
    line-height: 1.5;
}

.pa-batal-dampak {
    display: grid;
    gap: 0.45rem;
    margin: 0.6rem 0;
    padding: 0.75rem 0.85rem;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    border-radius: var(--hcis-radius-sm, 8px);
    background: var(--hcis-surface-subtle, #f8fafc);
}

.pa-batal-dampak__kepala {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    font-weight: 700;
}

.pa-batal-dampak__kepala span {
    margin-left: auto;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    font-weight: 500;
}

.pa-batal-dampak__daftar {
    display: grid;
    gap: 0.25rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-batal-dampak__daftar li {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.8rem;
}

.pa-batal-dampak__daftar strong {
    font-variant-numeric: tabular-nums;
}

.pa-batal-dampak__tahan {
    margin: 0;
    font-size: 0.8rem;
    line-height: 1.5;
    color: var(--hcis-danger-700, #b91c1c);
}

.pa-batal-dampak__catatan {
    margin: 0;
    font-size: 0.8rem;
    line-height: 1.5;
    color: var(--hcis-warning-700, #a16207);
}

.pa-batal-dampak__jalan {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 650;
    line-height: 1.5;
    color: #1e293b;
}

.pa-publish-confirm {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.pa-publish-confirm__identity {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
    max-width: 100%;
}

.pa-publish-confirm__avatar {
    display: grid;
    flex: 0 0 auto;
    width: 2.4rem;
    height: 2.4rem;
    place-items: center;
    color: #047857;
    border-radius: 0.75rem;
    background: rgba(16, 185, 129, 0.13);
}

.pa-publish-confirm__identity div:last-child {
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 0;
}

.pa-publish-confirm__identity strong {
    overflow: hidden;
    color: var(--hcis-text-strong, #0f172a);
    font-size: 0.84rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-publish-confirm__identity span {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
}

.pa-publish-confirm__score {
    display: grid;
    grid-template-columns: 1fr auto auto;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    padding: 0.75rem 0.85rem;
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: var(--hcis-radius-md, 8px);
    background: rgba(236, 253, 245, 0.7);
}

.pa-publish-confirm__score span {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.7rem;
}

.pa-publish-confirm__score strong {
    color: #047857;
    font-size: 1.1rem;
    font-variant-numeric: tabular-nums;
}

.pa-calibration-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem;
}

/* HcisField membawa labelnya sendiri; HcisSelect tidak, jadi labelnya dipasang di sini.
   Tanpa ini kedua pilihan berdiri tanpa keterangan dan hanya bisa dibedakan lewat isi
   dropdownnya — yang justru baru terlihat setelah dibuka. */
.pa-calibration-field {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}

.pa-calibration-field__label {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

@media (max-width: 640px) {
    .pa-calibration-fields {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1100px) {
    .pa-kualitatif-workspace {
        grid-template-columns: 1fr;
    }
    .pa-kualitatif-workspace__right {
        position: static;
    }
    .pa-kualitatif-workspace__right .pa-notes__form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .pa-radar-category-grid {
        grid-template-columns: 1fr;
    }
    .pa-notes__form {
        grid-template-columns: 1fr;
    }
    .pa-notes__form-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .pa-kualitatif-workspace__right .pa-notes__form-grid {
        grid-template-columns: 1fr;
    }
    .pa-notes__dimension-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    .pa-notes__dimension-actions {
        margin-left: 0;
        width: 100%;
    }
    .pa-notes__dimension-actions :deep(.hcis-btn) {
        width: 100%;
    }
    .pa-result-hero {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 0.85rem;
    }
    .pa-result-hero__name {
        font-size: 0.95rem;
    }
    .pa-result-hero__score-widget {
        width: 100%;
        justify-content: space-around;
        padding: 0.45rem 0.65rem;
        margin-left: 0;
    }
    .pa-hero-score-val {
        font-size: 1.1rem;
    }
    .pa-hero-grade-badge {
        min-width: 1.8rem;
        height: 1.6rem;
        font-size: 0.92rem;
        padding: 0 0.35rem;
    }
    .pa-hero-score-label {
        font-size: 0.56rem;
    }
    .pa-radar-category-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    .pa-category-row {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
        padding: 0.75rem 0.85rem;
    }
    .pa-category-row__main {
        width: 100%;
    }
    .pa-category-row__left {
        width: 100%;
    }
    .pa-category-row__meter-wrap {
        max-width: 100%;
    }
    .pa-category-row__right {
        width: 100%;
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        border-top: 1px dashed rgba(226, 232, 240, 0.8);
        padding-top: 0.5rem;
    }
    .pa-cat-score-meta {
        align-items: flex-start;
    }
    .pa-cat-summary-strip {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    .pa-cat-summary-strip__right {
        justify-content: space-between;
        width: 100%;
    }
    .pa-cat-summary-strip__bar-wrap {
        flex: 1;
    }
    .pa-assign-cockpit {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    .pa-rater-kategori-card {
        padding: 0.75rem;
    }
    .pa-rater-kategori__kepala {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .pa-assignment-row {
        align-items: flex-start;
        flex-wrap: wrap;
        padding: 0.65rem 0.75rem;
        gap: 0.45rem;
    }
    .pa-assignment-row__action {
        width: 100%;
        margin-left: 0;
    }
    .pa-assignment-row__action :deep(.hcis-btn) {
        width: 100%;
    }
    .pa-naratif-cockpit {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    .pa-naratif-stat-card {
        padding: 0.65rem 0.75rem;
        gap: 0.65rem;
    }
    .pa-naratif-stat-icon {
        width: 2rem;
        height: 2rem;
    }
    .pa-naratif-stat-num {
        font-size: 1.05rem;
    }
    .pa-notes__action-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 0.6rem;
    }
    .pa-notes__action-bar :deep(.hcis-btn) {
        width: 100%;
    }
    .pa-calibration-fields {
        grid-template-columns: 1fr;
    }
    .pa-bal-cockpit {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    .pa-bal-stat-card {
        padding: 0.65rem 0.75rem;
        gap: 0.65rem;
    }
    .pa-bal-stat-icon {
        width: 2rem;
        height: 2rem;
    }
    .pa-bal-stat-num {
        font-size: 1.05rem;
    }
    .pa-bal-sesi-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    .pa-bal-komp-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.35rem;
    }
    .pa-bal-jejak-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.35rem;
    }
}

/* Balancing Tab Executive Layout */
.pa-bal-state-box {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    padding: 2.5rem 1.5rem;
    font-size: 0.88rem;
    color: var(--hcis-text-muted, #64748b);
    border-radius: var(--hcis-radius-lg, 12px);
    background: rgba(248, 250, 252, 0.7);
    border: 1px dashed var(--hcis-border-subtle, #e2e8f0);
}

.pa-bal-layout {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.pa-bal-cockpit {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.75rem;
}

.pa-bal-stat-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: var(--hcis-surface, #ffffff);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.pa-bal-stat-card--primary {
    border-left: 3px solid var(--hcis-brand, #3b82f6);
    background: linear-gradient(135deg, rgba(239, 246, 255, 0.5), #ffffff);
}

.pa-bal-stat-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: var(--hcis-radius-sm, 8px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-bal-stat-icon.is-brand {
    background: rgba(59, 130, 246, 0.1);
    color: var(--hcis-brand, #3b82f6);
}

.pa-bal-stat-icon.is-purple {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
}

.pa-bal-stat-icon.is-teal {
    background: rgba(20, 184, 166, 0.1);
    color: #0d9488;
}

.pa-bal-stat-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-bal-stat-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-bal-stat-num {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--hcis-text-main, #0f172a);
    line-height: 1.25;
    margin: 0.1rem 0;
}

.pa-bal-stat-sub {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #94a3b8);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Sesi Balancing Card & List */
.pa-bal-sesi-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-bal-sesi-card {
    border-radius: var(--hcis-radius-md, 10px);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    background: var(--hcis-surface, #ffffff);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    overflow: hidden;
}

.pa-bal-sesi-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1.1rem;
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.9), rgba(241, 245, 249, 0.6));
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-bal-sesi-title-group {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-bal-sesi-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 6px;
    background: rgba(59, 130, 246, 0.12);
    color: var(--hcis-brand, #3b82f6);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-bal-sesi-title-meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}

.pa-bal-sesi-name {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--hcis-text-main, #0f172a);
}

.pa-bal-sesi-date {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-bal-sesi-badges {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-bal-sesi-body {
    padding: 0.85rem 1.1rem;
}

.pa-bal-komp-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-bal-komp-item {
    padding: 0.75rem 0.9rem;
    border-radius: var(--hcis-radius-sm, 8px);
    background: rgba(248, 250, 252, 0.75);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-bal-komp-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-bal-komp-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.84rem;
    color: var(--hcis-text-main, #1e293b);
}

.pa-bal-komp-scores {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-bal-score-old {
    font-size: 0.82rem;
    color: var(--hcis-text-muted, #94a3b8);
    text-decoration: line-through;
    font-family: monospace;
}

.pa-bal-arrow {
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-bal-score-new {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--hcis-text-main, #0f172a);
    font-family: monospace;
}

.pa-bal-delta-tag {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    font-family: monospace;
}

.pa-bal-delta-tag.is-up {
    background: rgba(34, 197, 94, 0.12);
    color: #15803d;
    border: 1px solid rgba(34, 197, 94, 0.25);
}

.pa-bal-delta-tag.is-down {
    background: rgba(239, 68, 68, 0.12);
    color: #b91c1c;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.pa-bal-komp-reason {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    font-size: 0.78rem;
    line-height: 1.45;
    color: var(--hcis-text-main, #334155);
    background: rgba(255, 255, 255, 0.85);
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    border-left: 3px solid #8b5cf6;
}

.pa-bal-komp-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--hcis-text-muted, #64748b);
    padding: 0.5rem 0.25rem;
}

/* Jejak Hitung Ulang (Audit Trail) */
.pa-bal-jejak-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.pa-bal-jejak-item {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-sm, 8px);
    background: var(--hcis-surface, #ffffff);
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.pa-bal-jejak-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 50%;
    background: rgba(139, 92, 246, 0.1);
    color: #7c3aed;
    font-weight: 700;
    font-size: 0.78rem;
    flex-shrink: 0;
}

.pa-bal-jejak-content {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
    min-width: 0;
}

.pa-bal-jejak-row {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.pa-bal-jejak-metric {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
}

.pa-bal-jejak-lbl {
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-jejak-before {
    color: var(--hcis-text-muted, #94a3b8);
    text-decoration: line-through;
    font-family: monospace;
}

.pa-bal-jejak-arrow {
    color: var(--hcis-text-muted, #94a3b8);
}

.pa-bal-jejak-after {
    font-weight: 700;
    color: var(--hcis-text-main, #0f172a);
    font-family: monospace;
}

.pa-bal-grade-val.grade-success {
    color: #15803d;
}

.pa-bal-grade-val.grade-brand {
    color: #2563eb;
}

.pa-bal-grade-val.grade-warning {
    color: #d97706;
}

.pa-bal-grade-val.grade-danger {
    color: #dc2626;
}

.pa-bal-jejak-sub {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
    flex-wrap: wrap;
}

.pa-bal-jejak-sebab {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-bal-jejak-time {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--hcis-text-muted, #94a3b8);
}
</style>

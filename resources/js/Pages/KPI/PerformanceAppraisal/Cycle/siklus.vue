<template>
    <main class="pa-page">
        <!-- Unified HCIS Header with Sub-Navigation Console -->
        <HcisHeader
            title="Assessment Cycle"
            :kicker="isMobile ? '' : 'PERFORMANCE APPRAISAL · CYCLE CONTROL TOWER'"
            :subtitle="isMobile ? '' : 'Pusat kendali assessment cycle penilaian kinerja 360 multi-rater. Kelola cycle dari fase setup, rater assignment, pengisian, hingga kalkulasi dan publish hasil.'"
            icon="clipboard2-data-fill"
            sentiment="brand"
            variant="glass"
            :size="isMobile ? 'sm' : 'md'"
            animated
            :loading="memuat"
            class="mb-3 mb-md-4"
        >
            <template #badge>
                <HcisBadge
                    v-if="siklusAktif.length > 0"
                    :label="`${siklusAktif.length} Active Cycles`"
                    variant="brand"
                    appearance="glass"
                    size="sm"
                    radar
                    dot
                />
                <HcisBadge
                    v-else-if="siklus.length > 0"
                    :label="`${formatAngka(siklus.length)} Total Cycles`"
                    variant="neutral"
                    appearance="subtle"
                    size="sm"
                    dot
                />
            </template>

            <template #actions>
                <div class="pa-header-actions">
                    <HcisButton
                        :label="isMobile ? '+ Cycle' : 'Buat Cycle Baru'"
                        variant="primary"
                        size="sm"
                        leading-icon="calendar2-plus-fill"
                        @click="bukaBuatSiklus"
                    />
                    <HcisButton
                        href="/performance-appraisal/master/syarat-kompetensi"
                        label="Master Data"
                        :icon-only="isMobile"
                        variant="secondary"
                        size="sm"
                        leading-icon="sliders"
                        title="Master Data"
                    />
                    <HcisButton
                        label="Segarkan"
                        :icon-only="isMobile"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        title="Segarkan data"
                        @click="muat"
                    />
                </div>
            </template>

            <template #console>
                <nav class="pa-admin-switcher" aria-label="Navigasi Performance Appraisal">
                    <a href="/performance-appraisal" class="pa-admin-switcher__item active pa-admin-switcher__item--active">
                        <HcisIcon name="grid-fill" size="2xs" />
                        <span>Assessment Cycle</span>
                    </a>
                    <a href="/performance-appraisal/pemantauan" class="pa-admin-switcher__item">
                        <HcisIcon name="hourglass-split" size="2xs" />
                        <span>Monitoring &amp; Verifikasi</span>
                    </a>
                    <a href="/performance-appraisal/hav-map" class="pa-admin-switcher__item">
                        <HcisIcon name="grid-3x3" size="2xs" />
                        <span>9-Box HAV Map</span>
                    </a>
                    <a href="/performance-appraisal/distribusi" class="pa-admin-switcher__item">
                        <HcisIcon name="bar-chart-fill" size="2xs" />
                        <span>Distribusi Performance</span>
                    </a>
                    <a href="/performance-appraisal/riwayat" class="pa-admin-switcher__item">
                        <HcisIcon name="clock-history" size="2xs" />
                        <span>Riwayat Penilaian</span>
                    </a>
                    <a href="/performance-appraisal/dashboard" class="pa-admin-switcher__item">
                        <HcisIcon name="speedometer2" size="2xs" />
                        <span>Dashboard PA</span>
                    </a>
                    <a href="/performance-appraisal/master/syarat-kompetensi" class="pa-admin-switcher__item">
                        <HcisIcon name="diagram-3-fill" size="2xs" />
                        <span>Master Data</span>
                    </a>
                </nav>
            </template>
        </HcisHeader>

        <!-- Error State -->
        <HcisResultState
            v-if="errorMemuat && !memuat && siklus.length === 0"
            status="danger"
            variant="glass"
            layout="compact"
            title="Assessment cycle tidak dapat dimuat"
            :description="errorMemuat"
            primary-text="Coba Lagi"
            primary-icon="arrow-clockwise"
            @primary-click="muat"
        />

        <!-- Loading Skeleton -->
        <div v-else-if="memuat" class="pa-cycle-skeleton">
            <div class="pa-skeleton-cockpit">
                <HcisSkeleton v-for="n in 4" :key="`skel-st-${n}`" :height="isMobile ? '3.8rem' : '5.5rem'" rounded="md" />
            </div>
            <div class="pa-skel-filterbar">
                <HcisSkeleton width="100%" height="3.25rem" rounded="md" />
            </div>
            <div class="pa-cycle-grid mt-2">
                <HcisCard v-for="item in 3" :key="item" loading variant="glass" sentiment="brand" class="pa-cycle-card" />
            </div>
        </div>

        <!-- Master DB Empty State -->
        <div v-else-if="siklus.length === 0" class="pa-empty-wrapper">
            <HcisEmptyState
                variant="card"
                size="lg"
                sentiment="brand"
                icon="calendar2-plus"
                title="Belum Ada Assessment Cycle"
                description="Buat assessment cycle baru untuk memulai konfigurasi pembobotan pilar, bank soal, dan rater assignment 360."
            >
                <template #actions>
                    <HcisButton
                        label="Buat Cycle Baru"
                        variant="primary"
                        size="sm"
                        leading-icon="calendar2-plus-fill"
                        @click="bukaBuatSiklus"
                    />
                </template>
            </HcisEmptyState>
        </div>

        <!-- Loaded Content -->
        <template v-else>
            <!-- EXECUTIVE TL;DR COCKPIT (4 CRYSTAL STAT CARDS - 2x2 ULTRA-COMPACT ON MOBILE) -->
            <div class="pa-cockpit-section" aria-label="Ringkasan Assessment Cycle">
                <HcisStatGroup
                    :stats="cycleStats"
                    :cols="isMobile ? 2 : 4"
                    gap="xs"
                    :layout="isMobile ? 'dense' : 'compact'"
                    appearance="crystal"
                    class="pa-stat-group"
                />
            </div>

            <!-- Standard Design System Filter Bar -->
            <HcisFilterBar
                :search="kataKunciCari"
                search-placeholder="Cari nama atau kode cycle..."
                :quick-chips="cycleQuickChips"
                :active-chip-value="filterTab"
                :filters="daftarFilterCycle"
                :filter-values="filterValuesCycle"
                :collapsible="true"
                :results-count="siklusTersaring.length"
                variant="card"
                sentiment="brand"
                glow
                class="pa-filter-bar-wrap"
                @update:search="kataKunciCari = $event"
                @chip-click="ubahChipFilter"
                @filter-change="onFilterChangeCycle"
                @reset="resetFilter"
            />

            <!-- Contextual Filter Empty State -->
            <div v-if="siklusTersaring.length === 0" class="pa-empty-wrapper">
                <HcisEmptyState
                    variant="card"
                    size="md"
                    :sentiment="emptyStateInfo.sentiment"
                    :icon="emptyStateInfo.icon"
                    :title="emptyStateInfo.title"
                    :description="emptyStateInfo.description"
                >
                    <template #actions>
                        <HcisButton
                            :label="emptyStateInfo.buttonLabel"
                            variant="secondary"
                            size="sm"
                            :leading-icon="emptyStateInfo.buttonIcon"
                            @click="emptyStateInfo.action"
                        />
                    </template>
                </HcisEmptyState>
            </div>

            <!-- Cycle Cards Grid -->
            <div v-else class="pa-cycle-grid">
                <HcisCard
                    v-for="row in siklusTersaring"
                    :key="row.Id_PA_Cycle"
                    variant="glass"
                    sentiment="brand"
                    class="pa-cycle-card"
                >
                    <!-- Card Topline / Header -->
                    <template #header>
                        <div class="pa-cycle-card-header">
                            <div class="pa-cycle-card-identity">
                                <div class="pa-cycle-card-avatar" :class="`is-${(row.Status_Cycle || '').toLowerCase()}`">
                                    <HcisIcon :name="getCycleIcon(row.Status_Cycle)" size="xs" />
                                </div>
                                <div class="pa-cycle-card-info">
                                    <div class="pa-cycle-card-meta">
                                        <span class="pa-cycle-code-pill">{{ row.Kode_Cycle }}</span>
                                        <span class="pa-cycle-card-dot">•</span>
                                        <span class="pa-cycle-year">Tahun {{ row.Tahun }}</span>
                                    </div>
                                    <h3 class="pa-cycle-card-name" :title="row.Nama_Cycle">{{ row.Nama_Cycle }}</h3>
                                </div>
                            </div>
                            <HcisStatusBadge :status="row.Status_Cycle" :label="row.Status_Cycle" size="sm" dot class="pa-cycle-card-badge" />
                        </div>
                    </template>

                    <!-- Card Body Content Wrapper with Bento Grid -->
                    <div class="pa-cycle-card-content">
                        <!-- Bento Dual Metric Strip -->
                        <div class="pa-cycle-card-bento">
                            <!-- Bento Pod 1: Periode Asesmen -->
                            <div class="pa-cycle-bento-box is-period">
                                <div class="pa-cycle-bento-label">
                                    <HcisIcon name="calendar3" size="2xs" />
                                    <span>PERIODE ASESMEN</span>
                                </div>
                                <div class="pa-cycle-bento-period-val">
                                    <strong :class="{ 'is-empty': !row.Periode_Dinilai_Dari }">
                                        {{ formatPeriode(row.Periode_Dinilai_Dari, row.Periode_Dinilai_Sampai) }}
                                    </strong>
                                </div>
                            </div>

                            <!-- Bento Pod 2: Peserta 360° -->
                            <div class="pa-cycle-bento-box is-participants">
                                <div class="pa-cycle-bento-label">
                                    <HcisIcon name="people-fill" size="2xs" />
                                    <span>PESERTA (360°)</span>
                                </div>
                                <div class="pa-cycle-bento-stats-row">
                                    <span class="pa-bento-stat is-included" title="Peserta Diikutkan">
                                        <strong class="text-indigo-600">{{ formatAngka(row.peserta?.diikutkan) }}</strong>
                                        <small>Ikut</small>
                                    </span>
                                    <span class="pa-bento-stat is-excluded" title="Peserta Dikecualikan">
                                        <strong class="text-slate-600">{{ formatAngka(row.peserta?.dikecualikan) }}</strong>
                                        <small>Kecualikan</small>
                                    </span>
                                    <button
                                        type="button"
                                        class="pa-bento-stat is-pending"
                                        :class="{ 'has-pending': (row.peserta?.tertunda || 0) > 0 }"
                                        :title="`Lihat ${row.peserta?.tertunda || 0} peserta tertunda`"
                                        @click="bukaTertunda(row)"
                                    >
                                        <strong :class="(row.peserta?.tertunda || 0) > 0 ? 'text-amber-600' : 'text-slate-500'">{{ formatAngka(row.peserta?.tertunda) }}</strong>
                                        <small>Tertunda</small>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Lifecycle Stepper Mini Pipeline -->
                        <div class="pa-lifecycle-pipeline" aria-label="Tahapan Cycle">
                            <!-- Desktop Stepper Pipeline -->
                            <div class="pa-lifecycle-pipeline__desktop">
                                <div
                                    v-for="(st, sIdx) in lifecycleStages"
                                    :key="st.key"
                                    class="pa-lifecycle-step"
                                    :class="{
                                        'is-past': isStagePast(row.Status_Cycle, st.key),
                                        'is-current': isStageCurrent(row.Status_Cycle, st.key),
                                        'is-future': isStageFuture(row.Status_Cycle, st.key),
                                    }"
                                    :title="st.tooltip"
                                >
                                    <div class="pa-stage-dot">
                                        <HcisIcon
                                            v-if="isStagePast(row.Status_Cycle, st.key)"
                                            name="check-lg"
                                            size="2xs"
                                        />
                                        <span v-else>{{ sIdx + 1 }}</span>
                                    </div>
                                    <span class="pa-stage-label">{{ st.label }}</span>
                                </div>
                            </div>

                            <!-- Mobile Compact Micro Pipeline -->
                            <div class="pa-lifecycle-pipeline__mobile">
                                <div class="pa-mobile-pipeline-track">
                                    <div
                                        v-for="(st, sIdx) in lifecycleStages"
                                        :key="`m-${st.key}`"
                                        class="pa-mobile-pipeline-step"
                                        :class="{
                                            'is-past': isStagePast(row.Status_Cycle, st.key),
                                            'is-current': isStageCurrent(row.Status_Cycle, st.key),
                                            'is-future': isStageFuture(row.Status_Cycle, st.key),
                                        }"
                                    >
                                        <div class="pa-mobile-stage-dot">
                                            <HcisIcon
                                                v-if="isStagePast(row.Status_Cycle, st.key)"
                                                name="check-lg"
                                                size="2xs"
                                            />
                                            <span v-else>{{ sIdx + 1 }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="pa-mobile-pipeline-status">
                                    <span class="pa-mobile-pipeline-phase">Tahap {{ getCurrentStageInfo(row.Status_Cycle).index }}/6</span>
                                    <span class="pa-mobile-pipeline-label">{{ getCurrentStageInfo(row.Status_Cycle).label }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Recommended Action CTA Banner (Full-Width Button for Zero Truncation) -->
                        <div class="pa-cta-banner" :class="`pa-cta-banner--${getActionSuggestion(row).btnVariant || 'primary'}`">
                            <div class="pa-cta-banner__info">
                                <span class="pa-cta-kicker">LANGKAH REKOMENDASI:</span>
                                <span class="pa-cta-title">{{ getActionSuggestion(row).text }}</span>
                            </div>
                            <div class="pa-cta-btn-wrap">
                                <HcisButton
                                    :label="getActionSuggestion(row).btnLabel"
                                    :variant="getActionSuggestion(row).btnVariant"
                                    size="sm"
                                    :leading-icon="getActionSuggestion(row).btnIcon"
                                    class="pa-cta-action-btn"
                                    @click="handleRecommendedAction(row)"
                                />
                            </div>
                        </div>

                        <!-- Next Phase Navigation Chips -->
                        <div class="pa-cycle-lanjut">
                            <span class="pa-eyebrow">MODUL WORKFLOW TERKAIT:</span>
                            <div class="pa-cycle-lanjut__list">
                                <a
                                    v-for="tujuan in lanjutan(row)"
                                    :key="tujuan.kode"
                                    class="pa-lanjut-chip"
                                    :class="{ 'pa-lanjut-chip--pasif': !tujuan.aktif }"
                                    :href="tujuan.alamat"
                                    :title="tujuan.keterangan"
                                    @click="klikLanjutan($event, tujuan, row)"
                                >
                                    <i :class="`bi bi-${tujuan.ikon}`" aria-hidden="true"></i>
                                    <span>{{ tujuan.nama }}</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Clean & Symmetrical Footer Actions -->
                    <template #footer>
                        <div class="pa-cycle-card-footer">
                            <!-- Optional Prominent Lifecycle Action (Hidden if already displayed as primary CTA above) -->
                            <HcisButton
                                v-if="boleh(row, 'BUKA_PENGISIAN') && getActionSuggestion(row).action !== 'buka-pengisian'"
                                label="Buka Pengisian"
                                variant="success"
                                size="sm"
                                leading-icon="unlock-fill"
                                class="pa-footer-action-item"
                                @click="bukaDialogPengisian(row, 'open')"
                            />

                            <HcisButton
                                v-if="boleh(row, 'TUTUP_PENGISIAN') && getActionSuggestion(row).action !== 'tutup-pengisian'"
                                label="Tutup Pengisian"
                                variant="warning"
                                size="sm"
                                leading-icon="lock-fill"
                                class="pa-footer-action-item"
                                @click="bukaDialogPengisian(row, 'close')"
                            />

                            <!-- Equal Sized Utility Tools Group -->
                            <div class="pa-footer-tools-group">
                                <HcisButton
                                    :label="isMobile ? 'Peserta' : 'Participants'"
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="people-fill"
                                    class="pa-footer-action-item"
                                    title="Lihat &amp; Kelola Daftar Peserta"
                                    @click="bukaPeserta(row)"
                                />
                                <HcisButton
                                    :label="isMobile ? 'Setup' : 'Setup Cycle'"
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="pencil-square"
                                    class="pa-footer-action-item"
                                    title="Ubah Konfigurasi Cycle"
                                    @click="bukaUbahSiklus(row)"
                                />
                                <HcisButton
                                    :label="isMobile ? 'Audit' : 'Audit Log'"
                                    variant="ghost"
                                    size="sm"
                                    leading-icon="journal-text"
                                    class="pa-footer-action-item"
                                    title="Riwayat Audit Cycle"
                                    @click="bukaAudit(row)"
                                />
                            </div>
                        </div>
                    </template>
                </HcisCard>
            </div>
        </template>

        <!-- Drawers & Dialogs -->
        <PaPendingDrawer
            v-model="drawerTertunda"
            :cycle="cycleTertunda"
            :items="tertunda"
            :loading="memuatTertunda"
            :error="errorTertunda"
            :error-status="errorStatusTertunda"
            :error-code="errorCodeTertunda"
            :request-id="requestIdTertunda"
            :can-assign="cycleTertunda ? boleh(cycleTertunda, 'BUAT_PENUGASAN') : false"
            @retry="muatTertunda"
            @assign="bukaPenugasan"
        />

        <PaAssignmentDialog
            v-model="dialogPenugasan"
            :participant="participantAktif"
            :categories="kategoriPenugasan"
            :readiness="kesiapanPenugasan"
            :selection="pilihanPenugasan"
            :weights="bobotSuntingPenugasan"
            :locked="bobotTerkunciPenugasan"
            :search-results="hasilCariPenugasan"
            :searching="sedangCariPenugasan"
            :adding="menambahPenilaiPenugasan"
            :add-error="galatTambahPenugasan"
            :add-info="kabarTambahPenugasan"
            :bobot-error="bobotGalatPenugasan"
            :bobot-kabar="bobotKabarPenugasan"
            :manual="manualPenugasan"
            :loading="memuatPenugasan"
            :error="errorPenugasan"
            :error-status="errorStatusPenugasan"
            :error-code="errorCodePenugasan"
            :request-id="requestIdPenugasan"
            :saving="menyimpanPenugasan"
            :calculating="menghitungPenugasan"
            :submit-error="submitErrorPenugasan"
            :submit-error-status="submitErrorStatusPenugasan"
            :submit-error-code="submitErrorCodePenugasan"
            :submit-request-id="submitRequestIdPenugasan"
            :submit-success="submitSuccessPenugasan"
            :result="hasilPesertaPenugasan"
            :can-calculate="cyclePenugasan ? boleh(cyclePenugasan, 'HITUNG_HASIL') : false"
            @retry="muatUsulanPenugasanAktif"
            @toggle-candidate="togglePenugasan"
            @bobot-change="bobotPenugasan.ubahBobot"
            @bagi-rata="bobotPenugasan.bagiRataKategori"
            @search-rater="penilaiBaruPenugasan.cariCalonPenilai"
            @tambah-penilai="penilaiBaruPenugasan.tambahPenilaiBaru"
            @manual-change="ubahManualPenugasan"
            @save="simpanPenugasan"
            @calculate="hitungPenugasan"
        />

        <PaRevisiPenugasanModal
            v-model="dialogRevisiPenugasan"
            :cycle="cycleRevisiPenugasan"
            :boleh-revisi="cycleRevisiPenugasan ? boleh(cycleRevisiPenugasan, 'TAMBAH_PENILAI') : false"
            @kelola-rater="bukaPeserta"
            @berubah="muat"
        />

        <PaParticipantsDialog
            v-model="dialogPeserta"
            :cycle="cyclePeserta"
            :items="peserta"
            :loading="memuatPeserta"
            :error="errorPeserta"
            :error-status="errorStatusPeserta"
            :error-code="errorCodePeserta"
            :request-id="requestIdPeserta"
            :halaman="paginationPeserta.halaman"
            :total="paginationPeserta.total"
            :total-halaman="paginationPeserta.totalHalaman"
            :summary="paginationPeserta.ringkasan"
            :truncated="paginationPeserta.dipangkas"
            :opsi="opsiPeserta"
            :can-assign="cyclePeserta ? (boleh(cyclePeserta, 'BUAT_PENUGASAN') || boleh(cyclePeserta, 'TAMBAH_PENILAI')) : false"
            @load="muatPeserta"
            @detail="bukaDetail"
            @assign="bukaPenugasan"
        />

        <PaResultDialog
            v-model="dialogDetail"
            :participant="participantDetail"
            :result="detailHasil"
            :loading="memuatDetailHasil"
            :error="errorDetailHasil"
            :error-status="errorStatusDetailHasil"
            :error-code="errorCodeDetailHasil"
            :request-id="requestIdDetailHasil"
            :calculation="hasilHitungDetail"
            :recalculating="menghitungDetail"
            :acting="memprosesAksiHasil"
            :action-error="errorAksiHasil"
            :action-error-status="errorStatusAksiHasil"
            :action-error-code="errorCodeAksiHasil"
            :action-request-id="requestIdAksiHasil"
            :action-success="suksesAksiHasil"
            :publishing="memprosesPenerbitan && namaAksiPenerbitan === 'terbitkan-peserta'"
            :publish-error="errorAksiPenerbitan"
            :publish-error-status="errorStatusAksiPenerbitan"
            :publish-error-code="errorCodeAksiPenerbitan"
            :publish-request-id="requestIdAksiPenerbitan"
            :publish-success="suksesAksiPenerbitan"
            :can-assign="cyclePeserta ? boleh(cyclePeserta, 'BUAT_PENUGASAN') : false"
            :can-calibrate="cyclePeserta ? boleh(cyclePeserta, 'KALIBRASI') : false"
            :can-calculate="cyclePeserta ? boleh(cyclePeserta, 'HITUNG_HASIL') : false"
            :can-publish="cyclePeserta ? boleh(cyclePeserta, 'TERBITKAN') : false"
            @retry="muatDetailHasilAktif"
            @cancel-assignment="batalkanPenugasanHasilAktif"
            @calculate="hitungPesertaHasilAktif"
            @calibrate="kalibrasiHasilAktif"
            @publish="terbitkanPesertaAktif"
            @release-leader="rilisLeaderAktif"
            @revoke-leader="cabutRilisLeaderAktif"
        />

        <PaCycleSetupDialog
            :model-value="dialogSetupSiklus"
            :mode="modeSetupSiklus"
            :cycle="cycleSetupSiklus"
            :detail="detailSetupSiklus"
            :form="formSetupSiklus"
            :detail-loading="memuatDetailSetup"
            :detail-error="errorDetailSetup"
            :detail-error-status="statusErrorDetailSetup"
            :detail-error-code="kodeErrorDetailSetup"
            :detail-request-id="requestIdDetailSetup"
            :saving="menyimpanSetup"
            :save-error="errorSimpanSetup"
            :save-error-status="statusErrorSimpanSetup"
            :save-error-code="kodeErrorSimpanSetup"
            :save-request-id="requestIdSimpanSetup"
            :save-success="suksesSimpanSetup"
            :save-result="hasilSimpanSetup"
            :validation-error="errorValidasiSetup"
            @update:model-value="ubahDialogSetup"
            @save="simpanSiklus"
        />

        <PaCycleAuditDialog
            :model-value="dialogAudit"
            :cycle="cycleAudit"
            :events="kejadianAudit"
            :types="jenisAudit"
            :counts="cacahAudit"
            :bounds="batasAudit"
            :filters="filterAudit"
            :pagination="paginationAudit"
            :loading="memuatAudit"
            :error="errorAudit"
            :error-status="statusErrorAudit"
            :error-code="kodeErrorAudit"
            :request-id="requestIdAudit"
            @update:model-value="ubahDialogAudit"
            @filter="saringAudit"
            @reset-filter="resetSaringanAudit"
            @retry="muatAuditAktif"
            @page-change="gantiHalamanAudit"
            @size-change="gantiUkuranAudit"
        />

        <!--
            Buka/tutup pengisian DI SINI, bukan dengan berpindah ke halaman pemantauan.

            Ia satu keputusan bertombol tunggal, sama seperti mengunci — memindahkan admin ke
            layar lain untuk menekannya memaksa ia mencari lagi baris yang tadi sudah ada di
            depannya. Komponen dialognya sudah ada sejak lama tetapi tidak pernah dipasang di
            mana pun; yang hilang cuma sambungannya.
        -->
        <PaCycleFillingDialog
            v-model="dialogPengisian"
            :mode="modePengisian"
            :cycle="cyclePengisian"
            :siklus-terbuka-lain="siklusTerbukaLain"
            :acting="sedangPengisian"
            :action-result="hasilPengisian"
            :action-error="galatPengisian"
            :action-error-status="galatPengisianStatus"
            :penjadwalan="penjadwalanPengisian"
            :menjadwalkan="sedangMenjadwalkan"
            @confirm="konfirmasiPengisian"
            @jadwalkan="jadwalkanPenilaian"
            @update:penjadwalan="penjadwalanPengisian = $event"
            @jadwal-selesai="onJadwalSelesai"
            @tarik-hasil="tarikHasilPenjadwalan"
            ada-revisi-penugasan
            @revisi-penugasan="(c) => { dialogPengisian = false; bukaRevisiPenugasan(c); }"
        />

        <!-- Notification Toast -->
        <div
            v-if="notifikasi.message"
            class="pa-notification"
            :class="`pa-notification--${notifikasi.type}`"
            role="status"
        >
            <HcisIcon
                :name="notifikasi.type === 'error' ? 'exclamation-triangle-fill' : 'check-circle-fill'"
                size="sm"
            />
            <div class="pa-notification__content">
                <span>{{ notifikasi.message }}</span>
                <small v-if="notifikasi.errorCode || notifikasi.requestId">
                    <span v-if="notifikasi.errorCode">Kode: {{ notifikasi.errorCode }}</span>
                    <span v-if="notifikasi.requestId">Request ID: {{ notifikasi.requestId }}</span>
                </small>
            </div>
            <button type="button" aria-label="Tutup pemberitahuan" @click="bersihkanNotifikasi">
                <HcisIcon name="x-lg" size="2xs" />
            </button>
        </div>
    </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import paApi from '../api/paApi.js';

/* DIIMPOR, bukan disalin. Halaman ini memanggilnya di lima tempat tanpa pernah punya
   badannya, dan akibatnya seluruh Daftar Siklus gagal dirender. Lihat utils/formatAngka.js. */
import { formatAngka } from '../utils/formatAngka.js';
import { cycleDariUrl } from '../utils/cycleDariUrl.js';

const isMobile = ref(false);
function cekUkuranLayar() {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth <= 768;
    }
}
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisEmptyState,
    HcisField,
    HcisFilterBar,
    HcisHeader,
    HcisIcon,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisStatusBadge,
    hcisMessage,
} from '@/components/ui';
import PaAssignmentDialog from './components/PaAssignmentDialog.vue';
import PaPendingDrawer from './components/PaPendingDrawer.vue';
import PaParticipantsDialog from './components/PaParticipantsDialog.vue';
import PaResultDialog from './components/PaResultDialog.vue';
import PaCycleSetupDialog from './components/PaCycleSetupDialog.vue';
import PaCycleAuditDialog from './components/PaCycleAuditDialog.vue';
import { usePaAssignment } from './composables/usePaAssignment.js';
import PaCycleFillingDialog from './components/PaCycleFillingDialog.vue';
import PaRevisiPenugasanModal from './components/PaRevisiPenugasanModal.vue';
import { usePaBobotEditor } from './composables/usePaBobotEditor.js';
import { usePaPenilaiBaru } from './composables/usePaPenilaiBaru.js';
import { usePaCycleSetup } from './composables/usePaCycleSetup.js';
import { usePaCycleAudit } from './composables/usePaCycleAudit.js';
import { usePaParticipants } from './composables/usePaParticipants.js';
import { rilisLeaderPesertaBerkonfirmasi, usePaPublication } from './composables/usePaPublication.js';
import { usePaResult } from './composables/usePaResult.js';

defineOptions({ name: 'PerformanceAppraisalCyclePage' });

const siklus = ref([]);
const memuat = ref(false);
const errorMemuat = ref('');
const sibuk = ref('');
const terakhirDiperbarui = ref('');

const filterTab = ref('semua'); // 'semua' | 'aktif' | 'draf' | 'selesai'
const tahunTerpilih = ref('');
const saringanStatus = ref('');
const kataKunciCari = ref('');

const drawerTertunda = ref(false);
const cycleTertunda = ref(null);
const memuatTertunda = ref(false);
const errorTertunda = ref('');
const errorStatusTertunda = ref(0);
const errorCodeTertunda = ref('');
const requestIdTertunda = ref('');
const tertunda = ref([]);
let urutanPermintaanTertunda = 0;
const dialogPenugasan = ref(false);
const participantAktif = ref(null);
const dialogPeserta = ref(false);
const cyclePeserta = ref(null);
const participantDetail = ref(null);
const dialogDetail = ref(false);

const {
    peserta,
    memuat: memuatPeserta,
    error: errorPeserta,
    errorStatus: errorStatusPeserta,
    errorCode: errorCodePeserta,
    requestId: requestIdPeserta,
    pagination: paginationPeserta,
    opsi: opsiPeserta,
    muat: muatDaftarPeserta,
    reset: resetPeserta,
} = usePaParticipants();

const penugasanState = usePaAssignment();

/*
 | Penyuntingan bobot memakai composable yang SAMA dengan Tahap 5.
 |
 | Sebelum ini dialognya dibuka dari sini tanpa `:weights`, tanpa `@bobot-change`, dan tanpa
 | `@bagi-rata`: kotak bobotnya tampil dan bisa diketik, tetapi tidak tersambung ke apa pun.
 | Admin mengetik 20/80, menekan Simpan, dan angkanya kembali seperti semula - tanpa galat,
 | tanpa pesan, tanpa petunjuk bahwa layar ini memang tidak pernah bisa menyimpannya.
 |
 | Menyalin kabelnya ke sini akan melahirkan salinan kedua yang menyimpang, dan itu sudah pernah
 | terjadi di modul ini. Jadi keduanya memanggil satu sumber.
 */
const bobotPenugasan = usePaBobotEditor(penugasanState);

/* Pencarian dan penambahan penilai, composable yang sama dengan Tahap 5. Tanpa ini kotak
   pencariannya tampil tetapi mengetik nama tidak memunculkan siapa pun, dan "tambahkan" tidak
   melakukan apa pun - cacat yang sama persis, hanya di layar yang lain. */
const penilaiBaruPenugasan = usePaPenilaiBaru({
    participantId: () => participantAktif.value?.Id_PA_Participant,
    segarkan: async () => {
        await muatUsulanPenugasan(participantAktif.value?.Id_PA_Participant);
    },
    laporkan: (teks) => { submitErrorPenugasan.value = teks; },
    bobot: bobotPenugasan,
});

/*
 | Ref-nya DIDESTRUKTURISASI ke tingkat atas, tidak diakses sebagai `objek.ref` di templat.
 |
 | Vue hanya membuka ref yang menjadi binding tingkat atas `<script setup>`. Ref yang bersarang
 | di dalam objek biasa TIDAK dibuka, sehingga `:weights="bobotPenugasan.bobotSunting"` mengirim
 | objek Ref-nya, bukan isinya — dan `props.weights[kunci]` selamanya undefined tanpa satu pun
 | galat. Menambahkan `.value` di templat juga bekerja, tetapi ia mudah terlupa pada binding
 | berikutnya; bentuk ini membuat kelalaian itu tidak mungkin.
 */
const { bobotSunting: bobotSuntingPenugasan, bobotTerkunci: bobotTerkunciPenugasan,
    bobotGalat: bobotGalatPenugasan, bobotKabar: bobotKabarPenugasan } = bobotPenugasan;

const { hasilCariPenilai: hasilCariPenugasan, sedangCariPenilai: sedangCariPenugasan,
    menambahPenilai: menambahPenilaiPenugasan, galatTambah: galatTambahPenugasan,
    kabarTambah: kabarTambahPenugasan } = penilaiBaruPenugasan;

const {
    kategori: kategoriPenugasan,
    kesiapan: kesiapanPenugasan,
    pilihan: pilihanPenugasan,
    manual: manualPenugasan,
    memuat: memuatPenugasan,
    error: errorPenugasan,
    errorStatus: errorStatusPenugasan,
    errorCode: errorCodePenugasan,
    requestId: requestIdPenugasan,
    menyimpan: menyimpanPenugasan,
    menghitung: menghitungPenugasan,
    submitError: submitErrorPenugasan,
    submitErrorStatus: submitErrorStatusPenugasan,
    submitErrorCode: submitErrorCodePenugasan,
    submitRequestId: submitRequestIdPenugasan,
    submitSuccess: submitSuccessPenugasan,
    hasilPeserta: hasilPesertaPenugasan,
    muat: muatUsulanPenugasan,
    reset: resetPenugasan,
    ubahPilihan: ubahPilihanPenugasan,
    ubahManual: ubahManualPenugasanState,
    simpan: simpanPenugasanApi,
    hitung: hitungPenugasanApi,
} = penugasanState;

const {
    detail: detailHasil,
    loading: memuatDetailHasil,
    error: errorDetailHasil,
    errorStatus: errorStatusDetailHasil,
    errorCode: errorCodeDetailHasil,
    requestId: requestIdDetailHasil,
    hasilHitung: hasilHitungDetail,
    recalculating: menghitungDetail,
    acting: memprosesAksiHasil,
    actionError: errorAksiHasil,
    actionErrorStatus: errorStatusAksiHasil,
    actionErrorCode: errorCodeAksiHasil,
    actionRequestId: requestIdAksiHasil,
    actionSuccess: suksesAksiHasil,
    muat: muatDetailHasil,
    reset: resetDetailHasil,
    batalkan: batalkanPenugasanHasil,
    hitung: hitungPesertaHasil,
    kalibrasi: kalibrasiHasil,
} = usePaResult();

const {
    ringkasan: ringkasanPenerbitan,
    loading: memuatPenerbitan,
    error: errorPenerbitan,
    errorStatus: errorStatusPenerbitan,
    errorCode: errorCodePenerbitan,
    requestId: requestIdPenerbitan,
    acting: memprosesPenerbitan,
    actionName: namaAksiPenerbitan,
    actionResult: hasilAksiPenerbitan,
    actionError: errorAksiPenerbitan,
    actionErrorStatus: errorStatusAksiPenerbitan,
    actionErrorCode: errorCodeAksiPenerbitan,
    actionRequestId: requestIdAksiPenerbitan,
    actionSuccess: suksesAksiPenerbitan,
    muat: muatPenerbitan,
    reset: resetPenerbitan,
    terbitkan: terbitkanSiklus,
    terbitkanPeserta: terbitkanSatuPeserta,
    tutup: tutupPenerbitan,
} = usePaPublication();

const {
    terbuka: dialogSetupSiklus,
    mode: modeSetupSiklus,
    cycle: cycleSetupSiklus,
    detail: detailSetupSiklus,
    form: formSetupSiklus,
    detailLoading: memuatDetailSetup,
    detailError: errorDetailSetup,
    detailErrorStatus: statusErrorDetailSetup,
    detailErrorCode: kodeErrorDetailSetup,
    detailRequestId: requestIdDetailSetup,
    saving: menyimpanSetup,
    saveError: errorSimpanSetup,
    saveErrorStatus: statusErrorSimpanSetup,
    saveErrorCode: kodeErrorSimpanSetup,
    saveRequestId: requestIdSimpanSetup,
    saveSuccess: suksesSimpanSetup,
    saveResult: hasilSimpanSetup,
    validationError: errorValidasiSetup,
    bukaBuat: bukaBuatSiklusApi,
    bukaUbah: bukaUbahSiklusApi,
    tutup: tutupSetupSiklus,
    simpan: simpanSetupSiklus,
} = usePaCycleSetup();

const {
    terbuka: dialogAudit,
    cycle: cycleAudit,
    kejadian: kejadianAudit,
    jenisTersedia: jenisAudit,
    cacah: cacahAudit,
    batas: batasAudit,
    filters: filterAudit,
    pagination: paginationAudit,
    loading: memuatAudit,
    error: errorAudit,
    errorStatus: statusErrorAudit,
    errorCode: kodeErrorAudit,
    requestId: requestIdAudit,
    buka: bukaAuditApi,
    muat: muatAuditApi,
    saring: saringAuditApi,
    gantiHalaman: gantiHalamanAuditApi,
    gantiUkuranHalaman: gantiUkuranAuditApi,
    reset: resetAudit,
} = usePaCycleAudit();

/* PENGUNCIAN TIDAK LAGI DI HALAMAN INI.
   Ia menjadi tombol penutup Langkah 5 pada wizard penyiapan — tempat admin memang sedang
   meninjau penugasan, yaitu keputusan yang sama dengan "bekukan siklusnya". Dua tombol
   untuk satu keputusan hanya membuat admin mencari yang kedua di halaman lain. */

/* Buka/tutup pengisian: dialog yang sama melayani keduanya, dibedakan `modePengisian`. */
const dialogPengisian = ref(false);
const modePengisian = ref('open');
const cyclePengisian = ref(null);

/*
 | Siklus LAIN yang sedang terbuka — dihitung dari daftar yang SUDAH dimuat.
 |
 | Tidak lewat endpoint baru: daftar siklus sudah membawa `Status_Cycle` setiap baris, dan
 | menanyakannya lagi ke server berarti satu perjalanan tambahan untuk data yang sudah ada di
 | tangan.
 |
 | Layar TIDAK menjadi penentu. Aturannya ditegakkan `bukaPengisian()` di server dengan
 | penguncian; yang di sini hanya memadamkan tombolnya lebih awal. Kalau keduanya sempat
 | berselisih — daftar yang basi, misalnya — server yang menang, dan itu arah yang benar.
 */
const siklusTerbukaLain = computed(() => {
    const id = cyclePengisian.value?.Id_PA_Cycle;
    const lain = (siklus.value || []).find(
        (s) => s.Status_Cycle === 'OPEN' && String(s.Id_PA_Cycle) !== String(id),
    );

    return lain ? { id: lain.Id_PA_Cycle, kode: lain.Kode_Cycle, nama: lain.Nama_Cycle } : null;
});
const sedangPengisian = ref(false);
const hasilPengisian = ref(null);
const galatPengisian = ref('');
const galatPengisianStatus = ref(0);

/* Pintu penilai: berapa yang sudah punya token HC Learn. null selama belum dimuat. */
const penjadwalanPengisian = ref(null);
const sedangMenjadwalkan = ref(false);
const cyclePenugasan = ref(null);
const notifikasi = reactive({ type: 'success', message: '', errorCode: '', requestId: '' });

const lifecycleStages = [
    { key: 'DRAFT', label: 'Setup', tooltip: 'Setup bobot pilar, pemetaan paket soal, dan aturan penilai.' },
    { key: 'PREPARED', label: 'Assignment', tooltip: 'Setup peserta dan penyusunan rater assignment 360.' },
    { key: 'LOCKED', label: 'Locked', tooltip: 'Cycle dibekukan dan siap dibuka untuk pengisian form.' },
    { key: 'OPEN', label: 'Open Filling', tooltip: 'Periode pengisian asesmen sedang dibuka.' },
    { key: 'CLOSED', label: 'Calculate', tooltip: 'Pengisian ditutup, skor dihitung dan dikalibrasi.' },
    { key: 'PUBLISHED', label: 'Published', tooltip: 'Hasil resmi diterbitkan dan masuk ke HAV Map.' },
];

const STAGE_ORDER = ['DRAFT', 'PREPARED', 'LOCKED', 'OPEN', 'CLOSED', 'CALCULATED', 'PUBLISHED'];

function isStageCurrent(cycleStatus, stageKey) {
    const s = String(cycleStatus || 'DRAFT').toUpperCase();
    if (stageKey === 'CLOSED' && s === 'CALCULATED') return true;
    return s === stageKey;
}

function isStagePast(cycleStatus, stageKey) {
    const s = String(cycleStatus || 'DRAFT').toUpperCase();
    const currentIdx = STAGE_ORDER.indexOf(s);
    const stageIdx = STAGE_ORDER.indexOf(stageKey);
    return currentIdx > stageIdx;
}

function isStageFuture(cycleStatus, stageKey) {
    const s = String(cycleStatus || 'DRAFT').toUpperCase();
    const currentIdx = STAGE_ORDER.indexOf(s);
    const stageIdx = STAGE_ORDER.indexOf(stageKey);
    return currentIdx < stageIdx;
}

function getCurrentStageInfo(cycleStatus) {
    const s = String(cycleStatus || 'DRAFT').toUpperCase();
    const stageMap = {
        DRAFT: { index: 1, label: 'Setup Konfigurasi', short: 'Setup' },
        PREPARED: { index: 2, label: 'Rater Assignment', short: 'Assignment' },
        LOCKED: { index: 3, label: 'Cycle Terkunci', short: 'Locked' },
        OPEN: { index: 4, label: 'Pengisian Aktif', short: 'Open Filling' },
        CLOSED: { index: 5, label: 'Kalkulasi Skor', short: 'Calculate' },
        CALCULATED: { index: 5, label: 'Kalibrasi & Review', short: 'Calibrate' },
        PUBLISHED: { index: 6, label: 'Hasil Diterbitkan', short: 'Published' },
    };
    return stageMap[s] || { index: 1, label: 'Setup Konfigurasi', short: 'Setup' };
}

function getCycleIcon(status) {
    const s = String(status || 'DRAFT').toUpperCase();
    if (s === 'OPEN') return 'unlock-fill';
    if (s === 'CLOSED' || s === 'CALCULATED') return 'calculator-fill';
    if (s === 'PUBLISHED') return 'award-fill';
    if (s === 'LOCKED') return 'lock-fill';
    if (s === 'PREPARED') return 'people-fill';
    return 'calendar2-range-fill';
}

function getActionSuggestion(row) {
    const s = String(row.Status_Cycle || 'DRAFT').toUpperCase();
    const setup = row.setup;

    if (setup && !setup.lengkap && (s === 'DRAFT' || s === 'PREPARED')) {
        const sisa = Math.max(0, (setup.total ?? 4) - (setup.selesai ?? 0));

        return {
            text: `Setup cycle belum lengkap — ${sisa} dari ${setup.total ?? 4} langkah belum selesai.`,
            btnLabel: 'Lanjutkan Setup →',
            btnVariant: 'primary',
            btnIcon: 'play-fill',
            action: 'siapkan',
        };
    }

    if (s === 'PREPARED' && setup?.lengkap && boleh(row, 'KUNCI_SIKLUS')) {
        return {
            text: 'Seluruh setup cycle sudah lengkap. Tinjau rater assignment di Langkah 5, lalu kunci cycle.',
            btnLabel: 'Ke Langkah 5 →',
            btnVariant: 'warning',
            btnIcon: 'person-check',
            action: 'siapkan',
        };
    }

    if (s === 'DRAFT') {
        return {
            text: 'Selesaikan 4 langkah setup pada wizard penyiapan cycle.',
            btnLabel: 'Setup Cycle →',
            btnVariant: 'primary',
            btnIcon: 'play-fill',
            action: 'siapkan',
        };
    }
    if (s === 'PREPARED') {
        return {
            text: 'Tinjau penugasan rater di Langkah 5; penguncian cycle dilakukan dari sana.',
            btnLabel: 'Rater Assignment →',
            btnVariant: 'primary',
            btnIcon: 'people-fill',
            action: 'penilai',
        };
    }
    if (s === 'LOCKED') {
        return {
            text: 'Cycle sudah dikunci. Tinggal membuka jendela pengisian untuk para rater.',
            btnLabel: 'Buka Pengisian →',
            btnVariant: 'success',
            btnIcon: 'unlock-fill',
            action: 'buka-pengisian',
        };
    }
    if (s === 'OPEN') {
        return {
            text: 'Pengisian form sedang dibuka. Pantau partisipasi rater.',
            btnLabel: 'Monitoring Pengisian →',
            btnVariant: 'primary',
            btnIcon: 'speedometer2',
            action: 'pemantauan',
        };
    }
    if (s === 'CLOSED') {
        return {
            text: 'Pengisian telah ditutup. Lakukan kalkulasi skor hasil evaluasi.',
            btnLabel: 'Hitung Hasil →',
            btnVariant: 'primary',
            btnIcon: 'calculator-fill',
            action: 'hasil',
        };
    }
    if (s === 'CALCULATED') {
        return {
            text: 'Skor telah dihitung. Lakukan kalibrasi grade dan publish hasil.',
            btnLabel: 'Kalibrasi & Publish →',
            btnVariant: 'primary',
            btnIcon: 'sliders',
            action: 'kalibrasi',
        };
    }
    if (s === 'PUBLISHED') {
        return {
            text: 'Cycle telah resmi terbit. Lihat sebaran talenta di HAV Map.',
            btnLabel: 'Buka HAV Map →',
            btnVariant: 'secondary',
            btnIcon: 'grid-1x2-fill',
            action: 'havmap',
        };
    }

    return {
        text: 'Kelola data cycle.',
        btnLabel: 'Lihat Cycle',
        btnVariant: 'secondary',
        btnIcon: 'arrow-right',
        action: 'siapkan',
    };
}

function handleRecommendedAction(row) {
    const sug = getActionSuggestion(row);
    if (sug.action === 'buka-pengisian') {
        bukaDialogPengisian(row, 'open');
    } else if (sug.action === 'tutup-pengisian') {
        bukaDialogPengisian(row, 'close');
    } else if (sug.action === 'siapkan') {
        bukaPenyiapan(row);
    } else if (sug.action === 'penilai') {
        /* Ke wizard penyiapan; penugasan penilai kini Tahap 5 di sana. */
        window.location.href = `/performance-appraisal/siapkan/${row.Id_PA_Cycle}`;
    } else if (sug.action === 'pemantauan') {
        window.location.href = `/performance-appraisal/pemantauan?cycle=${row.Id_PA_Cycle}`;
    } else if (sug.action === 'hasil' || sug.action === 'kalibrasi') {
        window.location.href = `/performance-appraisal/pemantauan?cycle=${row.Id_PA_Cycle}&tab=HASIL`;
    } else if (sug.action === 'havmap') {
        window.location.href = `/performance-appraisal/hav-map?cycle=${row.Id_PA_Cycle}`;
    }
}

function bukaPenyiapan(row) {
    window.location.href = `/performance-appraisal/siapkan/${row.Id_PA_Cycle}`;
}

function klikLanjutan(event, tujuan, row) {
    if (!tujuan.aktif) {
        event.preventDefault();
        return;
    }
    if (tujuan.aksi) {
        event.preventDefault();
        tujuan.aksi(row);
    }
}

/* Revisi penugasan sesudah dikunci — modal, bukan wizard penyiapan. */
const dialogRevisiPenugasan = ref(false);
const cycleRevisiPenugasan = ref(null);

function bukaRevisiPenugasan(row) {
    cycleRevisiPenugasan.value = row;
    dialogRevisiPenugasan.value = true;
}

function lanjutan(row) {
    const id = row.Id_PA_Cycle;
    const status = row.Status_Cycle;
    const sesudah = (...daftar) => daftar.includes(status);

    return [
        /* Sesudah dikunci yang dibutuhkan REVISI, bukan wizard penyiapan: modal di halaman ini. */
        /* Rater Assignment milik tahap Setup & Assignment (wizard). Sejak dikunci yang tersisa
           REVISI — modal di halaman ini; sesudah pengisian ditutup modalnya hanya-baca. */
        sesudah('LOCKED', 'OPEN', 'CLOSED', 'CALCULATED', 'PUBLISHED') ? {
            kode: 'PENILAI',
            nama: 'Revisi Penugasan',
            ikon: 'person-gear',
            alamat: '#',
            aksi: () => bukaRevisiPenugasan(row),
            aktif: true,
            keterangan: boleh(row, 'TAMBAH_PENILAI')
                ? 'Revisi peserta dan penilai sesudah siklus dikunci, tanpa kembali ke wizard penyiapan.'
                : 'Pengisian sudah ditutup — keadaan penugasan bisa dilihat, revisi tidak lagi bisa dijalankan.',
        } : {
            kode: 'PENILAI',
            nama: 'Rater Assignment',
            ikon: 'diagram-3',
            alamat: `/performance-appraisal/siapkan/${id}`,
            aktif: sesudah('PREPARED'),
            keterangan: 'Menugaskan siapa menilai siapa. Aktif setelah peserta disiapkan.',
        },
        {
            kode: 'PENGISIAN',
            nama: 'Monitoring Pengisian',
            ikon: 'speedometer2',
            alamat: `/performance-appraisal/pemantauan?cycle=${id}`,
            aktif: sesudah('LOCKED', 'OPEN', 'CLOSED', 'CALCULATED', 'PUBLISHED'),
            keterangan: 'Membuka dan menutup pengisian, memantau rater belum mengisi.',
        },
        {
            kode: 'HASIL',
            nama: 'Hasil & Kalibrasi',
            ikon: 'calculator-fill',
            alamat: `/performance-appraisal/pemantauan?cycle=${id}&tab=HASIL`,
            aktif: sesudah('CLOSED', 'CALCULATED', 'PUBLISHED'),
            keterangan: 'Meninjau hasil, kalibrasi grade, dan menerbitkan nilai.',
        },
        {
            kode: 'HAVMAP',
            nama: '9-Box HAV Map',
            ikon: 'grid-3x3-gap-fill',
            alamat: `/performance-appraisal/hav-map?cycle=${id}`,
            aktif: sesudah('CALCULATED', 'PUBLISHED', 'CLOSED'),
            keterangan: 'Sebaran sembilan kotak talenta.',
        },
    ];
}

const totalPeserta = computed(() =>
    siklus.value.reduce((total, row) => total + Number(row.peserta?.diikutkan || 0), 0),
);

const totalTertunda = computed(() =>
    siklus.value.reduce((total, row) => total + Number(row.peserta?.tertunda || 0), 0),
);

const siklusAktif = computed(() =>
    siklus.value.filter((row) => ['DRAFT', 'PREPARED', 'LOCKED', 'OPEN', 'CLOSED', 'CALCULATED'].includes(row.Status_Cycle)),
);

const siklusDraf = computed(() =>
    siklus.value.filter((row) => ['DRAFT', 'PREPARED'].includes(row.Status_Cycle)),
);

const siklusTerbit = computed(() =>
    siklus.value.filter((row) => ['PUBLISHED'].includes(row.Status_Cycle)),
);

const cycleStats = computed(() => [
    {
        id: 'total-cycle',
        label: isMobile.value ? 'Total Siklus' : 'Total Assessment Cycle',
        value: formatAngka(siklus.value.length),
        suffix: 'siklus',
        sentiment: 'brand',
        icon: 'calendar3',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Terdaftar di sistem' : 'Seluruh siklus terdaftar di sistem',
        pattern: 'grid',
    },
    {
        id: 'total-peserta',
        label: isMobile.value ? 'Peserta' : 'Total Peserta Dinilai',
        value: formatAngka(totalPeserta.value),
        suffix: 'orang',
        sentiment: 'success',
        icon: 'people-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Karyawan aktif' : 'Total karyawan aktif dinilai',
        pattern: 'dots',
    },
    {
        id: 'perlu-atensi',
        label: isMobile.value ? 'Perlu Atensi' : 'Peserta Tertunda / Isu',
        value: formatAngka(totalTertunda.value),
        suffix: totalTertunda.value > 0 ? 'tertunda' : 'aman',
        sentiment: totalTertunda.value > 0 ? 'warning' : 'neutral',
        icon: totalTertunda.value > 0 ? 'exclamation-triangle-fill' : 'patch-check-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value
            ? (totalTertunda.value > 0 ? 'Belum ada penilai' : 'Semua terisi')
            : (totalTertunda.value > 0 ? `${formatAngka(totalTertunda.value)} belum memiliki penilai` : 'Semua peserta memiliki penilai'),
        pattern: 'rings',
    },
    {
        id: 'siklus-aktif',
        label: isMobile.value ? 'Siklus Aktif' : 'Siklus Berjalan',
        value: formatAngka(siklusAktif.value.length),
        suffix: 'aktif',
        sentiment: 'info',
        icon: 'lightning-charge-fill',
        appearance: 'crystal',
        layout: isMobile.value ? 'dense' : 'compact',
        caption: isMobile.value ? 'Draft / Open / Hitung' : 'Fase Draft, Open, atau Perhitungan',
        pattern: 'stripes',
    },
]);

const cycleQuickChips = computed(() => {
    return [
        {
            id: 'semua',
            value: 'semua',
            label: 'Semua Cycle',
            badge: siklus.value.length,
            icon: 'layers-fill',
        },
        {
            id: 'aktif',
            value: 'aktif',
            label: 'Sedang Berjalan',
            badge: siklusAktif.value.length,
            icon: 'lightning-charge-fill',
            color: 'brand',
        },
        {
            id: 'draf',
            value: 'draf',
            label: 'Penyiapan & Draf',
            badge: siklusDraf.value.length,
            icon: 'pencil-square',
            color: 'warning',
        },
        {
            id: 'selesai',
            value: 'selesai',
            label: 'Selesai / Terbit',
            badge: siklusTerbit.value.length,
            icon: 'check-circle-fill',
            color: 'success',
        },
    ];
});

const tahunPilihan = computed(() => {
    const setTh = new Set(siklus.value.map((s) => s.Tahun).filter(Boolean));
    return [...setTh].sort((a, b) => b - a);
});

const daftarFilterCycle = computed(() => {
    const filters = [];

    if (tahunPilihan.value.length > 0) {
        filters.push({
            key: 'tahun',
            label: 'Tahun Assessment',
            type: 'select',
            icon: 'calendar-range',
            placeholder: 'Semua Tahun',
            clearable: true,
            options: [
                { value: '', label: 'Semua Tahun' },
                ...tahunPilihan.value.map((th) => ({
                    value: String(th),
                    label: `Tahun ${th}`,
                })),
            ],
            col: 6,
        });
    }

    filters.push({
        key: 'status',
        label: 'Status Lifecycle',
        type: 'select',
        icon: 'shield-check',
        placeholder: 'Semua Status',
        clearable: true,
        options: [
            { value: '', label: 'Semua Status' },
            { value: 'DRAFT', label: 'DRAFT' },
            { value: 'PREPARED', label: 'PREPARED' },
            { value: 'LOCKED', label: 'LOCKED' },
            { value: 'OPEN', label: 'OPEN' },
            { value: 'CLOSED', label: 'CLOSED' },
            { value: 'CALCULATED', label: 'CALCULATED' },
            { value: 'PUBLISHED', label: 'PUBLISHED' },
        ],
        col: 6,
    });

    return filters;
});

const filterValuesCycle = computed(() => ({
    tahun: tahunTerpilih.value,
    status: saringanStatus.value,
}));

function onFilterChangeCycle({ key, value }) {
    if (key === 'tahun') {
        tahunTerpilih.value = value || '';
    } else if (key === 'status') {
        saringanStatus.value = value || '';
    }
}

const siklusTersaring = computed(() => {
    let list = siklus.value;

    if (filterTab.value === 'aktif') {
        list = list.filter((r) => ['DRAFT', 'PREPARED', 'LOCKED', 'OPEN', 'CLOSED', 'CALCULATED'].includes(r.Status_Cycle));
    } else if (filterTab.value === 'draf') {
        list = list.filter((r) => ['DRAFT', 'PREPARED'].includes(r.Status_Cycle));
    } else if (filterTab.value === 'selesai') {
        list = list.filter((r) => ['PUBLISHED'].includes(r.Status_Cycle));
    }

    if (saringanStatus.value) {
        list = list.filter((r) => r.Status_Cycle === saringanStatus.value);
    }

    if (tahunTerpilih.value) {
        list = list.filter((r) => String(r.Tahun) === String(tahunTerpilih.value));
    }

    const q = kataKunciCari.value.trim().toLowerCase();
    if (q) {
        list = list.filter((r) =>
            String(r.Nama_Cycle || '').toLowerCase().includes(q) ||
            String(r.Kode_Cycle || '').toLowerCase().includes(q) ||
            String(r.Tahun || '').includes(q)
        );
    }

    return list;
});

function ubahChipFilter(chip) {
    filterTab.value = chip?.value || chip;
}

function resetFilter() {
    filterTab.value = 'semua';
    kataKunciCari.value = '';
    tahunTerpilih.value = '';
    saringanStatus.value = '';
}

const emptyStateInfo = computed(() => {
    if (kataKunciCari.value.trim()) {
        return {
            title: `Pencarian "${kataKunciCari.value}" Tidak Ditemukan`,
            description: 'Tidak ada siklus penilaian yang sesuai dengan kata kunci pencarian.',
            icon: 'search',
            sentiment: 'neutral',
            buttonLabel: 'Reset Pencarian',
            buttonIcon: 'x-circle',
            action: () => {
                kataKunciCari.value = '';
            },
        };
    }

    if (tahunTerpilih.value) {
        return {
            title: `Tidak Ada Siklus di Tahun ${tahunTerpilih.value}`,
            description: 'Tidak ada siklus penilaian yang tercatat pada tahun yang dipilih.',
            icon: 'calendar-x',
            sentiment: 'neutral',
            buttonLabel: 'Tampilkan Semua Tahun',
            buttonIcon: 'calendar3',
            action: () => {
                tahunTerpilih.value = '';
            },
        };
    }

    if (filterTab.value === 'aktif' && siklusAktif.value.length === 0) {
        return {
            title: 'Tidak Ada Siklus yang Sedang Berjalan',
            description: 'Saat ini tidak ada siklus penilaian dalam tahap Draft, Penyiapan, Pengisian, atau Perhitungan.',
            icon: 'lightning',
            sentiment: 'neutral',
            buttonLabel: 'Lihat Semua Siklus',
            buttonIcon: 'layers-fill',
            action: () => {
                filterTab.value = 'semua';
            },
        };
    }

    if (filterTab.value === 'draf' && siklusDraf.value.length === 0) {
        return {
            title: 'Tidak Ada Siklus dalam Tahap Draf',
            description: 'Seluruh siklus telah melewati tahap penyiapan awal.',
            icon: 'pencil-square',
            sentiment: 'neutral',
            buttonLabel: 'Lihat Semua Siklus',
            buttonIcon: 'layers-fill',
            action: () => {
                filterTab.value = 'semua';
            },
        };
    }

    if (filterTab.value === 'selesai' && siklusTerbit.value.length === 0) {
        return {
            title: 'Belum Ada Siklus yang Diterbitkan',
            description: 'Siklus yang telah selesai dan diterbitkan hasilnya akan muncul di sini.',
            icon: 'check-circle',
            sentiment: 'neutral',
            buttonLabel: 'Lihat Semua Siklus',
            buttonIcon: 'layers-fill',
            action: () => {
                filterTab.value = 'semua';
            },
        };
    }

    return {
        title: 'Tidak Ada Siklus yang Sesuai',
        description: 'Tidak ditemukan siklus penilaian yang cocok dengan kriteria filter saat ini.',
        icon: 'funnel',
        sentiment: 'neutral',
        buttonLabel: 'Reset Filter',
        buttonIcon: 'arrow-counterclockwise',
        action: resetFilter,
    };
});

const boleh = (row, operation) => (row?.operasi_boleh || []).includes(operation);
const pesanGalat = (error) => error?.message || 'Permintaan tidak bisa diselesaikan.';

function bukaBuatSiklus() {
    bukaBuatSiklusApi();
}

function bukaUbahSiklus(row) {
    void bukaUbahSiklusApi(row);
}

function ubahDialogSetup(value) {
    if (value) return;
    tutupSetupSiklus();
}

function bukaAudit(row) {
    void bukaAuditApi(row);
}

function ubahDialogAudit(value) {
    if (value) return;
    resetAudit();
}

function saringAudit(patch) {
    if (!cycleAudit.value?.Id_PA_Cycle) return;
    void saringAuditApi(cycleAudit.value.Id_PA_Cycle, patch);
}

function resetSaringanAudit() {
    if (!cycleAudit.value?.Id_PA_Cycle) return;
    void saringAuditApi(cycleAudit.value.Id_PA_Cycle, {
        jenis: '',
        participant_id: '',
        q: '',
        hanya_keputusan_manusia: false,
        halaman: 1,
        per_halaman: 10,
    });
}

function muatAuditAktif() {
    return cycleAudit.value?.Id_PA_Cycle ? muatAuditApi(cycleAudit.value.Id_PA_Cycle) : Promise.resolve({ skipped: true });
}

function gantiHalamanAudit(page) {
    if (!cycleAudit.value?.Id_PA_Cycle) return;
    void gantiHalamanAuditApi(cycleAudit.value.Id_PA_Cycle, page);
}

function gantiUkuranAudit(size) {
    if (!cycleAudit.value?.Id_PA_Cycle) return;
    void gantiUkuranHalamanAuditApi(cycleAudit.value.Id_PA_Cycle, size);
}

async function simpanSiklus(form) {
    const mode = modeSetupSiklus.value;
    const response = await simpanSetupSiklus(form);
    if (!response?.data) return response;

    if (mode === 'create') {
        tampilkanNotifikasi('success', suksesSimpanSetup.value, null);
        tutupSetupSiklus();
        await muat();
    } else {
        await muat();
    }

    return response;
}

const NAMA_BULAN = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const NAMA_BULAN_PENDEK = [
    'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
    'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
];

function parseTanggalYmd(val) {
    if (!val) return null;
    const str = String(val).trim().slice(0, 10);
    const match = str.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;
    return {
        tahun: parseInt(match[1], 10),
        bulan: parseInt(match[2], 10) - 1,
        hari: parseInt(match[3], 10),
    };
}

function formatPeriode(dari, sampai) {
    if (!dari && !sampai) return 'Belum Ditetapkan';
    const dDari = parseTanggalYmd(dari);
    const dSampai = parseTanggalYmd(sampai);

    if (dDari && dSampai) {
        if (dDari.tahun === dSampai.tahun) {
            if (dDari.bulan === dSampai.bulan && dDari.hari === dSampai.hari) {
                return `${dDari.hari} ${NAMA_BULAN[dDari.bulan]} ${dDari.tahun}`;
            }
            if (dDari.bulan === dSampai.bulan) {
                return `${dDari.hari} – ${dSampai.hari} ${NAMA_BULAN[dDari.bulan]} ${dDari.tahun}`;
            }
            return `${dDari.hari} ${NAMA_BULAN[dDari.bulan]} – ${dSampai.hari} ${NAMA_BULAN[dSampai.bulan]} ${dDari.tahun}`;
        }
        return `${dDari.hari} ${NAMA_BULAN[dDari.bulan]} ${dDari.tahun} – ${dSampai.hari} ${NAMA_BULAN[dSampai.bulan]} ${dSampai.tahun}`;
    }

    if (dDari) {
        return `Mulai ${dDari.hari} ${NAMA_BULAN[dDari.bulan]} ${dDari.tahun}`;
    }

    if (dSampai) {
        return `s.d. ${dSampai.hari} ${NAMA_BULAN[dSampai.bulan]} ${dSampai.tahun}`;
    }

    return `${dari || '—'} – ${sampai || '—'}`;
}

function tanggal(value) {
    if (!value) return '';
    const d = parseTanggalYmd(value);
    if (!d) return String(value).slice(0, 10);
    return `${d.hari} ${NAMA_BULAN[d.bulan]} ${d.tahun}`;
}

function tampilkanNotifikasi(type, message, exception = null) {
    notifikasi.type = type;
    notifikasi.message = message;
    notifikasi.errorCode = exception?.error_code || exception?.response?.data?.error_code || '';
    notifikasi.requestId = exception?.request_id || exception?.response?.data?.request_id || '';
}

function bersihkanNotifikasi() {
    notifikasi.message = '';
    notifikasi.errorCode = '';
    notifikasi.requestId = '';
}

async function muat() {
    memuat.value = true;
    errorMemuat.value = '';
    try {
        const data = await paApi.daftarSiklus();
        siklus.value = data?.siklus || [];
        terakhirDiperbarui.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    } catch (error) {
        errorMemuat.value = pesanGalat(error);
    } finally {
        memuat.value = false;
    }
}

async function bukaDialogPengisian(row, mode) {
    cyclePengisian.value = row;
    modePengisian.value = mode;
    hasilPengisian.value = null;
    galatPengisian.value = '';
    galatPengisianStatus.value = 0;
    penjadwalanPengisian.value = null;
    dialogPengisian.value = true;

    if (mode !== 'open') {
        return;
    }

    try {
        penjadwalanPengisian.value = await paApi.ringkasanPenjadwalan(row.Id_PA_Cycle);
    } catch (error) {
        /* Gagal memuat angkanya TIDAK menghalangi pembukaan: membuka pengisian tetap sah,
           hanya keterangannya yang hilang. Yang tidak boleh adalah diam — panelnya
           disembunyikan dan galatnya disebut. */
        galatPengisian.value = pesanGalat(error);
    }
}

async function konfirmasiPengisian(payload = {}) {
    if (!cyclePengisian.value || sedangPengisian.value) return;
    sedangPengisian.value = true;
    galatPengisian.value = '';
    galatPengisianStatus.value = 0;
    try {
        const cycleKode = cyclePengisian.value?.Kode_Cycle || 'Cycle';
        if (modePengisian.value === 'open') {
            const res = await paApi.bukaPengisian(cyclePengisian.value.Id_PA_Cycle, payload);
            hasilPengisian.value = res;
            if (res?.dibuka_ulang) {
                hcisMessage.warning(`Pengisian cycle ${cycleKode} dibuka ulang. Hasil kalkulasi sebelumnya perlu dihitung ulang.`);
                tampilkanNotifikasi('warning', `Pengisian cycle ${cycleKode} dibuka ulang. Hasil perhitungan sebelumnya perlu dihitung ulang.`);
            } else {
                hcisMessage.success(`Pengisian cycle ${cycleKode} berhasil dibuka (Status: OPEN).`);
                tampilkanNotifikasi('success', `Pengisian cycle ${cycleKode} berhasil dibuka.`);
            }
        } else {
            const res = await paApi.tutupPengisian(cyclePengisian.value.Id_PA_Cycle);
            hasilPengisian.value = res;
            hcisMessage.success(`Pengisian cycle ${cycleKode} berhasil ditutup (Status: CLOSED).`);
            tampilkanNotifikasi('success', `Pengisian cycle ${cycleKode} berhasil ditutup.`);
        }
        dialogPengisian.value = false;
        await muat();
    } catch (error) {
        galatPengisian.value = pesanGalat(error);
        galatPengisianStatus.value = Number(error?.status || error?.response?.status || 0);
    } finally {
        sedangPengisian.value = false;
    }
}

async function tarikHasilPenjadwalan(muatan) {
    const selesai = muatan?.selesai;
    const id = cyclePengisian.value?.Id_PA_Cycle;

    if (!id) {
        selesai?.();

        return;
    }

    try {
        const hasil = await paApi.tarikHasilPenjadwalan(id);

        if (hasil?.ringkasan) {
            penjadwalanPengisian.value = hasil.ringkasan;
        }

        /* Angkanya DISEBUTKAN, termasuk yang masih antre. "Berhasil" tanpa angka membuat
           admin mengira semuanya sudah selesai lalu menutup layar. */
        hcisMessage.success(
            `${hasil?.terisi ?? 0} akses terisi`
            + ((hasil?.masih_antre ?? 0) > 0 ? `, ${hasil.masih_antre} masih digarap HC Learn` : '')
            + ((hasil?.gagal ?? 0) > 0 ? `, ${hasil.gagal} ditolak` : '') + '.',
        );
    } catch (e) {
        hcisMessage.error(e?.message || 'Gagal menarik hasil penjadwalan.');
    } finally {
        selesai?.();
    }
}

async function jadwalkanPenilaian() {
    if (!cyclePengisian.value || sedangMenjadwalkan.value) return;
    sedangMenjadwalkan.value = true;
    try {
        const hasil = await paApi.jadwalkanPenilaian(cyclePengisian.value.Id_PA_Cycle, 200);
        if (hasil?.ringkasan) {
            penjadwalanPengisian.value = hasil.ringkasan;
        } else {
            penjadwalanPengisian.value = await paApi.ringkasanPenjadwalan(cyclePengisian.value.Id_PA_Cycle);
        }
    } catch (error) {
        tampilkanNotifikasi('error', pesanGalat(error), error);
    } finally {
        sedangMenjadwalkan.value = false;
    }
}

function onJadwalSelesai(data) {
    if (data) {
        penjadwalanPengisian.value = data;
    }
    void muat();
}

async function bukaTertunda(row) {
    cycleTertunda.value = row;
    drawerTertunda.value = true;
    await muatTertunda();
}

async function muatTertunda() {
    if (!cycleTertunda.value) return;
    const cycleId = cycleTertunda.value.Id_PA_Cycle;
    const nomorPermintaan = ++urutanPermintaanTertunda;
    memuatTertunda.value = true;
    errorTertunda.value = '';
    errorStatusTertunda.value = 0;
    errorCodeTertunda.value = '';
    requestIdTertunda.value = '';
    tertunda.value = [];
    try {
        const data = await paApi.keranjangTertunda(cycleId);
        if (nomorPermintaan !== urutanPermintaanTertunda || cycleId !== cycleTertunda.value?.Id_PA_Cycle) return;
        tertunda.value = data?.tertunda || [];
    } catch (error) {
        if (nomorPermintaan !== urutanPermintaanTertunda || cycleId !== cycleTertunda.value?.Id_PA_Cycle) return;
        errorTertunda.value = pesanGalat(error);
        errorStatusTertunda.value = Number(error?.status || error?.response?.status || 0);
        errorCodeTertunda.value = error?.error_code || error?.response?.data?.error_code || '';
        requestIdTertunda.value = error?.request_id || error?.response?.data?.request_id || '';
    } finally {
        if (nomorPermintaan === urutanPermintaanTertunda) memuatTertunda.value = false;
    }
}

function bukaPenugasan(participant) {
    participantAktif.value = participant;
    cyclePenugasan.value = cycleTertunda.value || cyclePeserta.value || null;
    dialogPenugasan.value = true;
    resetPenugasan();
    void muatUsulanPenugasan(participant?.Id_PA_Participant);
}

function muatUsulanPenugasanAktif() {
    return muatUsulanPenugasan(participantAktif.value?.Id_PA_Participant);
}

function togglePenugasan({ kodeKategori, candidate }) {
    /* Lewat editor bobot, bukan langsung ke `ubahPilihanPenugasan`: mengubah susunan penilai
       harus ikut menyegarkan pembagian bobotnya, kalau tidak kotak penilai baru muncul kosong
       dan totalnya langsung salah. Editor itu yang memanggil `ubahPilihan` di dalamnya. */
    bobotPenugasan.ubahPilihanDanRatakan({ kodeKategori, candidate });
}

function ubahManualPenugasan({ kodeKategori, field, value }) {
    ubahManualPenugasanState(kodeKategori, field, value);
}

async function simpanPenugasan() {
    const participantId = participantAktif.value?.Id_PA_Participant;
    /* SATU permintaan untuk penilai DAN bobotnya, lalu SATU penyegaran. */
    const result = await simpanPenugasanApi(participantId, bobotPenugasan.muatanSimpan());

    bobotPenugasan.terapkanHasil(result);

    if (result?.error || result?.skipped) {
        return result;
    }

    await muatUsulanPenugasan(participantId);

    if (result?.data && cycleTertunda.value) void muatTertunda();

    return result;
}

function hitungPenugasan() {
    return hitungPenugasanApi(participantAktif.value?.Id_PA_Participant);
}

function bukaPeserta(row) {
    cyclePeserta.value = row;
    dialogPeserta.value = true;
    resetPeserta();
    void muatPeserta({ halaman: 1, per_halaman: 25, status_peserta: 'DIIKUTKAN', dengan_opsi: 1 });
}

async function muatPeserta(saringan = {}) {
    if (!cyclePeserta.value) return;
    return muatDaftarPeserta(cyclePeserta.value.Id_PA_Cycle, saringan);
}

/*
 | MEMBUKA DETAIL HASIL SATU PESERTA DARI ALAMAT — `?cycle=<id>&peserta=<id>`.
 |
 | Keranjang keputusan di halaman pemantauan tahu persis siapa yang menunggu diverifikasi,
 | tetapi antarmuka verifikasinya — `PaResultDialog`, lengkap dengan Hitung Ulang, Kalibrasi,
 | dan Terbitkan — hidup di halaman ini. Tanpa jalan pintas, admin yang melihat "3 perlu
 | verifikasi" harus pindah halaman lalu mencari tiga orang itu di antara 859.
 |
 | Dialognya TIDAK digandakan ke halaman pemantauan. Ia mengikat dua puluh lebih prop dan lima
 | penangan aksi; menyalinnya berarti dua pintu yang harus dijaga sama persis, dan modul ini
 | sudah beberapa kali membayar mahal untuk pola itu.
 */
async function bukaPesertaDariUrl() {
    let diminta = '';

    try {
        diminta = new URLSearchParams(window.location.search).get('peserta') || '';
    } catch {
        return;
    }

    if (! diminta) {
        return;
    }

    const row = siklus.value.find((c) => String(c.Id_PA_Cycle) === String(cycleDariUrl(siklus.value)));

    if (! row) {
        return;
    }

    /* Daftar pesertanya dibuka lebih dulu supaya dialog hasil punya konteks siklusnya —
       `cyclePeserta` yang menentukan tombol mana yang menyala lewat `boleh(...)`. */
    cyclePeserta.value = row;
    resetPeserta();
    /* Disaring ke ORANG ITU SAJA, bukan memuat satu halaman lalu berharap ia ada di dalamnya.
       Peserta di halaman kelima akan membuat dialognya tidak terbuka tanpa satu pun pesan. */
    await muatDaftarPeserta(row.Id_PA_Cycle, {
        halaman: 1,
        per_halaman: 25,
        participant_id: diminta,
    });

    const orang = (peserta.value || []).find(
        (p) => String(p.Id_PA_Participant) === String(diminta),
    );

    if (orang) {
        bukaDetail(orang);
    }
}

function bukaDetail(participant) {
    participantDetail.value = participant;
    dialogDetail.value = true;
    resetDetailHasil();
    void muatDetailHasil(participant?.Id_PA_Participant);
}

function muatDetailHasilAktif() {
    return muatDetailHasil(participantDetail.value?.Id_PA_Participant);
}

function batalkanPenugasanHasilAktif({ assignment, alasan }) {
    return batalkanPenugasanHasil(
        participantDetail.value?.Id_PA_Participant,
        assignment?.Id_PA_Assignment,
        alasan,
    );
}

function hitungPesertaHasilAktif() {
    return hitungPesertaHasil(participantDetail.value?.Id_PA_Participant);
}

async function kalibrasiHasilAktif({ resultId, payload }) {
    return kalibrasiHasil(participantDetail.value?.Id_PA_Participant, resultId, payload);
}

async function terbitkanPesertaAktif() {
    const response = await terbitkanSatuPeserta(participantDetail.value?.Id_PA_Participant);

    if (response?.data) {
        await muatDetailHasilAktif();
        tampilkanNotifikasi('success', suksesAksiPenerbitan.value);
    }

    return response;
}

/* Rilis/cabut rilis ke leader per orang. Tombolnya tampil di dialog hasil sejak lama, tetapi
   halaman ini tidak pernah menangkap kejadiannya sehingga klik tidak melakukan apa pun. */
async function rilisLeaderAktif() {
    const pid = participantDetail.value?.Id_PA_Participant;
    if (!pid) return;
    try {
        if ((await rilisLeaderPesertaBerkonfirmasi(paApi, pid))?.dibatalkan) return;
        await muatDetailHasilAktif();
        hcisMessage.success('Hasil dirilis ke leader.', 'Rilis ke leader');
    } catch (e) {
        hcisMessage.error(e?.message || 'Rilis hasil ke leader gagal.', 'Rilis gagal');
    }
}

async function cabutRilisLeaderAktif(alasan) {
    const pid = participantDetail.value?.Id_PA_Participant;
    if (!pid) return;
    try {
        await paApi.cabutRilisLeader(pid, alasan);
        await muatDetailHasilAktif();
        hcisMessage.success('Rilis ke leader dicabut.', 'Rilis dicabut');
    } catch (e) {
        hcisMessage.error(e?.message || 'Pencabutan rilis leader gagal.', 'Pencabutan gagal');
    }
}

onMounted(async () => {
    cekUkuranLayar();
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', cekUkuranLayar, { passive: true });
    }
    await muat();
    await bukaPesertaDariUrl();
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', cekUkuranLayar);
    }
});
</script>

<style scoped>
.pa-page {
    --pa-ink: var(--hcis-text-strong, #0f172a);
    --pa-muted: var(--hcis-text-muted, #64748b);
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    padding-bottom: 5.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    width: 100%;
}

.pa-header-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

/* Master Switcher in Header Console */
.pa-admin-switcher {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: nowrap;
    overflow-x: auto;
    padding: 0.25rem 0.35rem;
    background: rgba(241, 245, 249, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-lg, 12px);
    margin-top: 0.5rem;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}

.pa-admin-switcher::-webkit-scrollbar {
    display: none;
}

.pa-admin-switcher__item {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.76rem;
    font-weight: 500;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.pa-admin-switcher__item:hover {
    color: #4338ca;
    background: rgba(255, 255, 255, 0.8);
}

.pa-admin-switcher__item--active {
    color: #4338ca;
    background: #ffffff;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(99, 102, 241, 0.05);
}

.pa-cycle-skeleton {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pa-cockpit-section {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
}

.pa-stat-group {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
    width: 100%;
}

.pa-skeleton-cockpit {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
    width: 100%;
}

.pa-skel-filterbar {
    width: 100%;
}

/* Filter Bar Wrap & Controls */
.pa-empty-wrapper {
    width: 100%;
}

/* Cycle Cards Grid */
.pa-cycle-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 1.25rem;
}

.pa-cycle-card {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    border: 1px solid rgb(var(--hcis-slate-200-rgb) / 0.85);
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1),
                box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1),
                border-color 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.pa-cycle-card:hover {
    transform: translateY(-2px);
    border-color: rgb(var(--hcis-brand-500-rgb) / 0.35);
    box-shadow: 0 10px 28px -4px rgba(15, 23, 42, 0.1), 0 2px 8px -2px rgba(15, 23, 42, 0.05);
}

.pa-cycle-card :deep(.hcis-card__header) {
    padding: 1.15rem 1.25rem 0.75rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-cycle-card :deep(.hcis-card__body) {
    padding: 0.75rem 1.25rem;
}

.pa-cycle-card :deep(.hcis-card__footer) {
    padding: 0.75rem 1.25rem;
    background: #f8fafc;
    border-top: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-cycle-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.65rem;
    width: 100%;
}

.pa-cycle-card-identity {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    min-width: 0;
    flex: 1;
}

.pa-cycle-card-avatar {
    display: grid;
    place-items: center;
    flex: 0 0 2.25rem;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(79, 70, 229, 0.08) 100%);
    color: var(--hcis-brand-700, #4338ca);
    border: 1px solid rgba(99, 102, 241, 0.25);
    font-size: 0.85rem;
    font-weight: 800;
    flex-shrink: 0;
    margin-top: 0.1rem;
    transition: all 0.2s ease;
}

.pa-cycle-card-avatar.is-draft {
    background: linear-gradient(135deg, rgba(148, 163, 184, 0.15) 0%, rgba(100, 116, 139, 0.08) 100%);
    color: #475569;
    border-color: rgba(148, 163, 184, 0.3);
}

.pa-cycle-card-avatar.is-open {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.18) 0%, rgba(79, 70, 229, 0.1) 100%);
    color: #4338ca;
    border-color: rgba(99, 102, 241, 0.35);
}

.pa-cycle-card-avatar.is-calc {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.18) 0%, rgba(217, 119, 6, 0.1) 100%);
    color: #b45309;
    border-color: rgba(245, 158, 11, 0.35);
}

.pa-cycle-card-avatar.is-done {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.18) 0%, rgba(5, 150, 105, 0.1) 100%);
    color: #047857;
    border-color: rgba(16, 185, 129, 0.35);
}

.pa-cycle-card-avatar.is-locked {
    background: linear-gradient(135deg, rgba(107, 114, 128, 0.15) 0%, rgba(75, 85, 99, 0.08) 100%);
    color: #374151;
    border-color: rgba(156, 163, 175, 0.3);
}

.pa-cycle-card-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    flex: 1;
}

.pa-cycle-card-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.pa-cycle-code-pill {
    font-size: 0.68rem;
    font-weight: 750;
    color: #4f46e5;
    background: #eef2ff;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
    flex-shrink: 0;
}

.pa-cycle-card-dot {
    color: var(--hcis-slate-300, #cbd5e1);
    font-size: 0.65rem;
    flex-shrink: 0;
}

.pa-cycle-year {
    font-size: 0.7rem;
    font-weight: 650;
    color: #64748b;
    flex-shrink: 0;
}

.pa-cycle-card-name {
    margin: 0;
    color: var(--hcis-slate-800, #1e293b);
    font-size: 0.94rem;
    font-weight: 800;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}

.pa-cycle-card-badge {
    flex-shrink: 0;
    margin-top: 0.1rem;
}

/* Card Body Content Layout */
.pa-cycle-card-content {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
}

/* Bento Dual Metric Strip inside Card Body */
.pa-cycle-card-bento {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
    width: 100%;
}

.pa-cycle-bento-box {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.55rem 0.65rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #f8fafc;
    border: 1px solid rgba(226, 232, 240, 0.9);
    min-height: 4.4rem;
    min-width: 0;
    transition: all 150ms ease;
}

.pa-cycle-bento-box:hover {
    border-color: #cbd5e1;
    background: #f1f5f9;
}

.pa-cycle-bento-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--hcis-slate-500, #64748b);
    font-size: 0.62rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    min-width: 0;
}

.pa-cycle-bento-label :deep(.hcis-icon) {
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-cycle-bento-period-val {
    display: flex;
    align-items: center;
    margin-top: 0.25rem;
    min-width: 0;
    flex: 1;
}

.pa-cycle-bento-period-val strong {
    color: var(--hcis-slate-800, #1e293b);
    font-size: 0.76rem;
    font-weight: 700;
    line-height: 1.35;
    letter-spacing: -0.01em;
    word-break: break-word;
}

.pa-cycle-bento-period-val strong.is-empty {
    color: var(--hcis-slate-400, #94a3b8);
    font-weight: 500;
    font-style: italic;
}

.pa-cycle-bento-stats-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.3rem;
    margin-top: 0.25rem;
    width: 100%;
}

.pa-bento-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.15rem;
    padding: 0.25rem 0.15rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    min-width: 0;
    text-align: center;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    transition: all 150ms ease;
}

.pa-bento-stat strong {
    font-size: 0.82rem;
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.01em;
}

.pa-bento-stat small {
    font-size: 0.58rem;
    font-weight: 650;
    line-height: 1;
    color: var(--hcis-slate-500, #64748b);
    text-transform: capitalize;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.pa-bento-stat.is-included {
    background: #f8faff;
    border-color: #e0e7ff;
}

.pa-bento-stat.is-included small {
    color: #6366f1;
}

.pa-bento-stat.is-excluded {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.pa-bento-stat.is-excluded small {
    color: #94a3b8;
}

.pa-bento-stat.is-pending {
    background: #f8fafc;
    border-color: #e2e8f0;
    cursor: pointer;
    font: inherit;
    transition: all 150ms ease;
}

.pa-bento-stat.is-pending small {
    color: #94a3b8;
}

.pa-bento-stat.is-pending:hover {
    border-color: #cbd5e1;
    background: #f1f5f9;
    transform: translateY(-1px);
}

.pa-bento-stat.is-pending.has-pending {
    background: #fffbeb;
    border-color: #fde68a;
    box-shadow: 0 1px 3px rgba(245, 158, 11, 0.12);
}

.pa-bento-stat.is-pending.has-pending small {
    color: #b45309;
}

.pa-bento-stat.is-pending.has-pending:hover {
    border-color: #f59e0b;
    background: #fef3c7;
    box-shadow: 0 2px 5px rgba(245, 158, 11, 0.2);
}

/* Lifecycle Stepper Mini Pipeline */
.pa-lifecycle-pipeline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.55rem 0.65rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.85);
    width: 100%;
    box-sizing: border-box;
}

.pa-lifecycle-pipeline__desktop {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.25rem;
    width: 100%;
}

.pa-lifecycle-pipeline__mobile {
    display: none;
}

.pa-lifecycle-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    flex: 1;
    position: relative;
    min-width: 0;
}

.pa-lifecycle-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 7px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: #e2e8f0;
    z-index: 1;
}

.pa-lifecycle-step.is-past:not(:last-child)::after {
    background: #10b981;
}

.pa-stage-dot {
    width: 15px;
    height: 15px;
    border-radius: 9999px;
    background: #e2e8f0;
    color: #64748b;
    font-size: 0.56rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 2;
    transition: all 0.2s ease;
}

.pa-lifecycle-step.is-past .pa-stage-dot {
    background: #10b981;
    color: #ffffff;
}

.pa-lifecycle-step.is-current .pa-stage-dot {
    background: #4f46e5;
    color: #ffffff;
    box-shadow: 0 0 0 2.5px rgba(99, 102, 241, 0.25);
}

.pa-stage-label {
    font-size: 0.6rem;
    font-weight: 700;
    color: #64748b;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    white-space: nowrap;
}

.pa-lifecycle-step.is-current .pa-stage-label {
    color: #4f46e5;
    font-weight: 800;
}

/* Mobile Micro Pipeline Track */
.pa-mobile-pipeline-track {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.2rem;
    position: relative;
    width: 100%;
}

.pa-mobile-pipeline-step {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1;
    position: relative;
}

.pa-mobile-pipeline-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 100%;
    height: 2px;
    background: #e2e8f0;
    transform: translateY(-50%);
    z-index: 1;
}

.pa-mobile-pipeline-step.is-past:not(:last-child)::after {
    background: #10b981;
}

.pa-mobile-stage-dot {
    width: 15px;
    height: 15px;
    border-radius: 9999px;
    background: #e2e8f0;
    color: #64748b;
    font-size: 0.54rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 2;
    transition: all 0.2s ease;
}

.pa-mobile-pipeline-step.is-past .pa-mobile-stage-dot {
    background: #10b981;
    color: #ffffff;
}

.pa-mobile-pipeline-step.is-current .pa-mobile-stage-dot {
    background: #4f46e5;
    color: #ffffff;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25);
    transform: scale(1.1);
}

.pa-mobile-pipeline-status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.15rem;
    font-size: 0.68rem;
    width: 100%;
}

.pa-mobile-pipeline-phase {
    font-weight: 750;
    color: #4f46e5;
    font-size: 0.62rem;
    background: #eef2ff;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.pa-mobile-pipeline-label {
    font-weight: 700;
    color: var(--hcis-slate-800, #1e293b);
    font-size: 0.72rem;
}

/* CTA Banner */
.pa-cta-banner {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 0.75rem 0.95rem;
    border-radius: 10px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    transition: all 0.2s ease;
}

.pa-cta-banner--success {
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.95), rgba(240, 253, 244, 0.85));
    border-color: rgba(16, 185, 129, 0.3);
}

.pa-cta-banner--success .pa-cta-kicker {
    color: #059669;
}

.pa-cta-banner--success .pa-cta-title {
    color: #064e3b;
}

.pa-cta-banner--primary {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.95), rgba(245, 243, 255, 0.85));
    border-color: rgba(99, 102, 241, 0.25);
}

.pa-cta-banner--primary .pa-cta-kicker {
    color: #4f46e5;
}

.pa-cta-banner--primary .pa-cta-title {
    color: #1e1b4b;
}

.pa-cta-banner--warning {
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.9), rgba(255, 251, 235, 0.85));
    border-color: rgba(245, 158, 11, 0.3);
}

.pa-cta-banner--warning .pa-cta-kicker {
    color: #b45309;
}

.pa-cta-banner--warning .pa-cta-title {
    color: #78350f;
}

.pa-cta-banner--secondary {
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.85));
    border-color: rgba(203, 213, 225, 0.6);
}

.pa-cta-banner--secondary .pa-cta-kicker {
    color: #475569;
}

.pa-cta-banner--secondary .pa-cta-title {
    color: #0f172a;
}

.pa-cta-banner__info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-cta-kicker {
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #4338ca;
}

.pa-cta-title {
    font-size: 0.78rem;
    font-weight: 650;
    color: #1e1b4b;
    line-height: 1.4;
}

.pa-cta-btn-wrap {
    width: 100%;
}

.pa-cta-action-btn {
    width: 100%;
    justify-content: center;
}

.pa-cta-action-btn :deep(.hcis-btn) {
    width: 100%;
    justify-content: center;
    min-height: 2.25rem;
}

/* Lanjut Ke Chips */
.pa-cycle-lanjut {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    margin-top: 0.1rem;
}

.pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.62rem;
    letter-spacing: 0.07em;
    font-weight: 800;
    text-transform: uppercase;
}

.pa-cycle-lanjut__list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.45rem;
    width: 100%;
}

.pa-lanjut-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.42rem 0.65rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    color: #1e293b;
    font-size: 0.72rem;
    font-weight: 650;
    text-decoration: none;
    line-height: 1.25;
    min-height: 2.2rem;
    width: 100%;
    box-sizing: border-box;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-lanjut-chip i {
    font-size: 0.85rem;
    color: var(--hcis-brand-600, #4f46e5);
    flex-shrink: 0;
    transition: transform 160ms ease, color 160ms ease;
}

.pa-lanjut-chip span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}

.pa-lanjut-chip:hover:not(.pa-lanjut-chip--pasif) {
    background: #f5f3ff;
    color: #4338ca;
    border-color: #a5b4fc;
    box-shadow: 0 3px 6px -1px rgba(79, 70, 229, 0.12), 0 1px 3px -1px rgba(79, 70, 229, 0.08);
    transform: translateY(-1px);
}

.pa-lanjut-chip:hover:not(.pa-lanjut-chip--pasif) i {
    transform: scale(1.12);
    color: #4338ca;
}

.pa-lanjut-chip:active:not(.pa-lanjut-chip--pasif) {
    transform: translateY(0);
}

.pa-lanjut-chip--pasif {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    color: #94a3b8;
    box-shadow: none;
    cursor: not-allowed;
}

.pa-lanjut-chip--pasif i {
    color: #cbd5e1;
}

/* Card Footer Actions Layout */
.pa-cycle-card-footer {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    width: 100%;
}

.pa-footer-tools-group {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.45rem;
    width: 100%;
}

.pa-footer-action-item {
    width: 100%;
    min-width: 0;
}

.pa-footer-action-item :deep(.hcis-btn) {
    width: 100%;
    justify-content: center;
    padding: 0.45rem 0.4rem;
    font-size: 0.74rem;
    font-weight: 650;
    border-radius: 6px;
    white-space: nowrap;
}

.pa-notification {
    position: fixed;
    z-index: 50;
    right: 1.5rem;
    bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    max-width: min(32rem, calc(100vw - 2rem));
    padding: 0.85rem 1.15rem;
    border: 1px solid;
    border-radius: var(--hcis-radius-lg, 12px);
    box-shadow: var(--hcis-shadow-lg, 0 10px 15px -3px rgb(0 0 0 / 0.08));
    backdrop-filter: blur(12px);
    font-size: 0.82rem;
}

.pa-notification--success {
    color: #047857;
    border-color: rgba(16, 185, 129, 0.3);
    background: rgba(236, 253, 245, 0.94);
}

.pa-notification--error {
    color: #b91c1c;
    border-color: rgba(239, 68, 68, 0.3);
    background: rgba(254, 242, 242, 0.94);
}

.pa-notification__content {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-notification button {
    background: none;
    border: none;
    cursor: pointer;
    color: inherit;
    padding: 0.1rem;
    display: inline-flex;
}

@media (min-width: 769px) and (max-width: 1100px) {
    .pa-cycle-grid {
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    }
    .pa-stat-group,
    .pa-skeleton-cockpit {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.5rem !important;
    }
}

@media (max-width: 768px) {
    .pa-page {
        padding-bottom: 5.5rem;
        gap: 0.85rem;
    }
    .pa-stat-group,
    .pa-skeleton-cockpit {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.5rem !important;
    }
    .pa-cycle-grid {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }
    .pa-cycle-card :deep(.hcis-card__header),
    .pa-cycle-card :deep(.hcis-card__body),
    .pa-cycle-card :deep(.hcis-card__footer) {
        padding: 0.7rem 0.85rem;
    }
    .pa-footer-tools-group {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.35rem;
    }
    .pa-footer-action-item :deep(.hcis-btn) {
        padding: 0.4rem 0.3rem;
        font-size: 0.72rem;
    }
    .pa-notification {
        left: 1rem;
        right: 1rem;
        bottom: 1rem;
        max-width: none;
    }
}

@media (max-width: 640px) {
    .pa-cockpit-section {
        gap: 0.4rem;
    }

    .pa-stat-group {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.4rem !important;
        margin-bottom: 0.25rem !important;
    }

    .pa-stat-group :deep(.hcis-stat),
    .pa-stat-group .hcis-stat {
        min-height: unset !important;
        height: auto !important;
        padding: 0.45rem 0.55rem !important;
        border-radius: var(--hcis-radius-sm, 8px) !important;
    }

    .pa-stat-group :deep(.hcis-stat__compact-wrap) {
        gap: 0.15rem !important;
    }

    .pa-stat-group :deep(.hcis-stat__value) {
        font-size: 1.15rem !important;
        line-height: 1.15 !important;
        font-weight: 800 !important;
    }

    .pa-stat-group :deep(.hcis-stat__label) {
        font-size: 0.64rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.02em !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-stat-group :deep(.hcis-stat__caption) {
        font-size: 0.58rem !important;
        margin-top: 0.05rem !important;
        color: var(--hcis-slate-500, #64748b) !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .pa-stat-group :deep(.hcis-stat__icon-box) {
        width: 1.35rem !important;
        height: 1.35rem !important;
        border-radius: 5px !important;
    }

    .pa-skeleton-cockpit {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.4rem !important;
    }

    .pa-cycle-card-content {
        gap: 0.6rem !important;
    }

    .pa-cycle-card-bento {
        gap: 0.4rem !important;
    }

    .pa-cycle-bento-box {
        padding: 0.45rem 0.5rem !important;
        min-height: 4.2rem !important;
    }

    .pa-cycle-bento-stats-row {
        gap: 0.25rem !important;
    }

    .pa-bento-stat {
        padding: 0.2rem 0.1rem !important;
    }

    .pa-bento-stat strong {
        font-size: 0.78rem !important;
    }

    .pa-bento-stat small {
        font-size: 0.55rem !important;
    }

    .pa-lifecycle-pipeline {
        padding: 0.45rem 0.55rem !important;
    }

    .pa-lifecycle-pipeline__desktop {
        display: none;
    }

    .pa-lifecycle-pipeline__mobile {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        width: 100%;
    }

    .pa-cta-banner {
        padding: 0.6rem 0.75rem !important;
        gap: 0.45rem !important;
    }

    .pa-cycle-lanjut__list {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 0.35rem !important;
        overflow-x: visible !important;
        padding-bottom: 0 !important;
    }

    .pa-lanjut-chip {
        font-size: 0.68rem !important;
        padding: 0.35rem 0.45rem !important;
        min-height: 2.1rem !important;
        border-radius: 6px !important;
    }

    .pa-lanjut-chip i {
        font-size: 0.78rem !important;
    }

    .pa-footer-tools-group {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 0.3rem !important;
    }

    .pa-footer-action-item :deep(.hcis-btn) {
        padding: 0.4rem 0.25rem !important;
        font-size: 0.72rem !important;
        min-height: 2.15rem !important;
    }
}

@media (max-width: 480px) {
    .pa-cycle-card :deep(.hcis-card__header),
    .pa-cycle-card :deep(.hcis-card__body),
    .pa-cycle-card :deep(.hcis-card__footer) {
        padding: 0.6rem 0.75rem !important;
    }

    .pa-cycle-card-bento {
        gap: 0.35rem !important;
    }

    .pa-cycle-bento-box {
        padding: 0.4rem 0.45rem !important;
        min-height: 4rem !important;
    }

    .pa-cycle-bento-stats-row {
        gap: 0.2rem !important;
    }

    .pa-bento-stat {
        padding: 0.18rem 0.08rem !important;
    }

    .pa-bento-stat strong {
        font-size: 0.75rem !important;
    }

    .pa-bento-stat small {
        font-size: 0.52rem !important;
    }

    .pa-cycle-lanjut__list {
        gap: 0.3rem !important;
    }

    .pa-lanjut-chip {
        padding: 0.3rem 0.4rem !important;
        font-size: 0.65rem !important;
        min-height: 2rem !important;
    }

    .pa-lanjut-chip i {
        font-size: 0.74rem !important;
    }

    .pa-footer-tools-group {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 0.25rem !important;
    }

    .pa-footer-action-item :deep(.hcis-btn) {
        padding: 0.38rem 0.18rem !important;
        font-size: 0.7rem !important;
    }
}

@media (max-width: 360px) {
    .pa-cycle-card-bento {
        grid-template-columns: 1fr !important;
    }
}
</style>

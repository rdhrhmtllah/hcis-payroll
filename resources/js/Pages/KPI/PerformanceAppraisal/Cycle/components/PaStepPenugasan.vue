<template>
    <div class="pa-step-root">
        <div class="pa-step-container">
            <!-- Step Header Banner -->
            <PaStepHeader
                kicker="STEP 5 OF 5 · PENUGASAN PENILAI"
                title="Penugasan Penilai"
                description="Bereskan daftar tugas di bawah — yang berlabel Wajib menahan penguncian — lalu kunci dan jadwalkan akses penilai ke HC Learn."
                icon="person-check"
            >
                <template #actions>
                    <!--
                     | SATU BENTUK DI SEMUA LANGKAH: "Lihat Master ...".
                     |
                     | Disembunyikan bila admin belum berhak: halaman relasi milik modul
                     | SYSTEM, dan tombol yang menjanjikan 403 lebih buruk daripada tombol
                     | yang tidak ada.
                     -->
                    <HcisButton
                        v-if="bisaKelolaRelasi"
                        href="/system/organisasi/relasi"
                        label="Lihat Master Relasi Organisasi"
                        variant="secondary"
                        size="sm"
                        leading-icon="database"
                    />

                    <HcisButton
                        label="Muat Ulang"
                        variant="ghost"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        @click="muat"
                    />
                </template>
            </PaStepHeader>

            <!--
             | DAFTAR TUGAS — satu-satunya tempat masalah disebut (UAT 13 Okt). Tampil begitu
             | penugasan pernah dibentuk; sebelum itu yang perlu dilakukan hanya satu: membentuknya.
             -->
            <PaDaftarTugasPenugasan
                v-if="tampilDaftarTugas"
                :wajib="tugasWajib"
                :saran="tugasSaran"
                :tambahan="jumlahPerubahan"
                :terkunci="sudahTerkunci"
                class="pa-alur-bagian"
            >
                <PaPerubahanPeserta
                    ref="panelPerubahan"
                    :cycle-id="cycleId"
                    langkah="PENUGASAN"
                    :dapat-disunting="dapatDisunting"
                    :boleh-kecualikan="dapatDisunting || bolehUbahPenilai"
                    polos
                    :sedang-membentuk="sedangBentukMassal"
                    @lengkapi="bentukPenugasanMassal"
                    @ke-langkah-4="$emit('kembali')"
                    @berubah="muat"
                    @jumlah="(v) => (jumlahPerubahan = v)"
                    @revisi="(k) => { targetKaryawanRevisi = k; dialogRevisi = true; }"
                />
            </PaDaftarTugasPenugasan>

            <!-- REVISI SESUDAH DIKUNCI — satu pintu untuk perubahan per orang. -->
            <div v-if="sudahTerkunci" class="pa-revisi-bar" data-uji="revisi-bar">
                <div class="pa-revisi-bar__info">
                    <div class="pa-revisi-bar__icon">
                        <HcisIcon name="person-gear" size="sm" color="brand" />
                    </div>
                    <div class="pa-revisi-bar__text">
                        <strong>Panduan Perubahan Peserta Pasca-Kunci</strong>
                        <span>Ada perubahan peserta sesudah dikunci — Level PA dikoreksi, perlu diikutkan kembali, atau karyawan baru?</span>
                    </div>
                </div>
                <HcisButton
                    label="Revisi peserta"
                    variant="secondary"
                    size="sm"
                    leading-icon="person-gear"
                    data-uji="buka-revisi"
                    @click="targetKaryawanRevisi = null; dialogRevisi = true"
                />
            </div>
            <!--
             | PENILAI YANG SUDAH TIDAK AKTIF DI HRIS masih memegang tugas menilai yang tidak akan
             | pernah diisi — pesertanya diam-diam kekurangan penilai sampai hasil dihitung.
             -->
            <HcisAlert
                v-if="sudahTerkunci && penilaiTidakAktif > 0"
                variant="warning"
                appearance="subtle"
                size="sm"
                :title="`${penilaiTidakAktif} penilai sudah tidak aktif di HRIS masih punya tugas menilai`"
                description="Tugas mereka tidak akan pernah diisi. Lepas mereka sebagai penilai supaya pengganti dibentuk dari relasi organisasi."
                icon="exclamation-triangle-fill"
                data-uji="penilai-tidak-aktif"
            >
                <HcisButton label="Lepas & ganti penilai" variant="secondary" size="xs" data-uji="buka-lepas-tidak-aktif" @click="bukaLepasTidakAktif" />
            </HcisAlert>
            <!-- Satu orang atau banyak sekaligus (centang, pilih semua hasil filter). -->
            <PaRevisiPesertaMassalDialog
                v-model="dialogRevisi"
                :cycle-id="cycleId"
                :karyawan-awal="targetKaryawanRevisi"
                @selesai="sesudahRevisi"
            />

            <!--
             | TERKUNCI UNTUK MEMBENTUK ULANG, TIDAK UNTUK MENGGANTI SATU PENILAI.
             |
             | Perbedaan itu yang membuat siklus terkunci berhenti jadi jalan buntu: kesiapan
             | HC Learn bisa menyebut penilai yang sudah resign kapan saja, termasuk sesudah
             | siklus dikunci, dan penggantiannya memang masih diizinkan server.
             -->
            <HcisAlert
                v-if="!dapatDisunting"
                variant="warning"
                appearance="subtle"
                :title="alasanTerkunci || 'Langkah ini sedang terkunci untuk penyuntingan.'"
                icon="lock-fill"
            >
                <template v-if="bolehUbahPenilai">
                    Membentuk ulang penugasan massal memang sudah tidak bisa, tetapi
                    <strong>mengganti atau mencabut satu penilai masih boleh</strong> — pakai
                    <em>Kelola Rater</em> untuk membereskan penilai yang ditolak HC Learn.
                </template>
            </HcisAlert>

            <!-- Error State -->
            <HcisResultState
                v-else-if="error && !memuat"
                status="danger"
                layout="compact"
                title="Kesehatan penugasan tidak dapat dimuat"
                :description="error"
                primary-text="Coba Lagi"
                primary-icon="arrow-clockwise"
                @primary-click="muat"
            />

            <!-- Loading Skeleton State -->
            <div v-else-if="memuat" class="pa-pen-skeleton-card">
                <div class="pa-skeleton-hero-header">
                    <HcisSkeleton width="180px" height="1.6rem" rounded="full" />
                    <HcisSkeleton width="340px" height="2rem" rounded="md" />
                    <HcisSkeleton width="520px" height="1.1rem" rounded="md" />
                </div>
                <div class="pa-skeleton-hero-center">
                    <div class="pa-skeleton-radial-placeholder">
                        <HcisSkeleton width="130px" height="130px" rounded="full" />
                    </div>
                </div>
                <div class="pa-skeleton-hero-footer">
                    <HcisSkeleton v-for="n in 4" :key="`skel-pill-${n}`" height="2.25rem" rounded="full" />
                </div>
            </div>

            <!-- Main Content Area -->
            <div v-else class="pa-pen-content">
                <h3 class="pa-alur-judul">Pembentukan penugasan</h3>
                <!-- HERO COCKPIT: Tampil saat pembentukan penugasan belum selesai / sedang berjalan / belum dijalankan -->
                <div v-if="!isGenerationComplete" class="pa-generate-cockpit-card">
                    <div class="pa-generate-cockpit-header">
                        <div class="pa-generate-badge-group">
                            <HcisStatusBadge
                                :label="statusPembentukanLabel"
                                :variant="statusPembentukanVariant"
                                dot
                            />
                            <span v-if="progres && progres.total > 0" class="pa-generate-context-text">
                                {{ formatAngka(progres.sudah) }} dari {{ formatAngka(progres.total) }} Peserta Diproses
                            </span>
                        </div>
                        <h3 class="pa-generate-cockpit-title">
                            {{ judulPembentukanHero }}
                        </h3>
                        <p class="pa-generate-cockpit-desc">
                            {{ deskripsiPembentukanHero }}
                        </p>
                    </div>

                    <div class="pa-generate-hero-center">
                        <!-- Radial / Orbital Progress Ring Wrapper -->
                        <div
                            class="pa-radial-progress-wrapper"
                            :class="{
                                'is-active': sedangBentukMassal,
                                'is-paused': progres && progres.sudah > 0 && !progres.selesai && !sedangBentukMassal,
                            }"
                        >
                            <svg class="pa-radial-svg" viewBox="0 0 200 200">
                                <defs>
                                    <linearGradient id="paProgressGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#4f46e5" />
                                        <stop offset="50%" stop-color="#7c3aed" />
                                        <stop offset="100%" stop-color="#06b6d4" />
                                    </linearGradient>
                                </defs>
                                <circle
                                    class="pa-radial-track"
                                    cx="100"
                                    cy="100"
                                    :r="radius"
                                    stroke-width="12"
                                />
                                <circle
                                    class="pa-radial-fill"
                                    cx="100"
                                    cy="100"
                                    :r="radius"
                                    stroke-width="12"
                                    stroke="url(#paProgressGrad)"
                                    :stroke-dasharray="circumference"
                                    :stroke-dashoffset="dashOffset"
                                />
                            </svg>

                            <!-- Center Action Button -->
                            <div class="pa-radial-center-action">
                                <button
                                    v-if="!sedangBentukMassal"
                                    type="button"
                                    class="pa-hero-action-btn"
                                    :class="{ 'is-resume': progres && progres.sudah > 0 && !progres.selesai }"
                                    :disabled="!dapatDisunting || (progres && progres.total === 0)"
                                    @click="bentukPenugasanMassal"
                                >
                                    <div class="pa-hero-btn-icon">
                                        <HcisIcon
                                            :name="progres && progres.sudah > 0 ? 'play-fill' : 'magic'"
                                            size="md"
                                        />
                                    </div>
                                    <div class="pa-hero-btn-text">
                                        <strong>{{ labelTombolHero }}</strong>
                                        <small>{{ subTombolHero }}</small>
                                    </div>
                                    <div v-if="progres && progres.total > 0" class="pa-hero-btn-percent">
                                        {{ persenProgres }}%
                                    </div>
                                </button>

                                <!-- Active Live Processing State -->
                                <div v-else class="pa-hero-running-card">
                                    <div class="pa-hero-running-spinner">
                                        <HcisIcon name="arrow-repeat" size="sm" animation="spin" color="brand" />
                                    </div>
                                    <div class="pa-hero-running-stats">
                                        <strong class="pa-hero-running-val">{{ persenProgres }}%</strong>
                                        <span>{{ formatAngka(bentukMassalDibuat) }} dibuat</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="pa-hero-pause-btn"
                                        @click="hentikanPembentukan"
                                    >
                                        <HcisIcon name="pause-fill" size="2xs" />
                                        <span>Hentikan</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Stat Pills in Hero Card -->
                    <div class="pa-generate-cockpit-footer">
                        <div class="pa-generate-stats-row">
                            <div class="pa-gen-stat-pill">
                                <HcisIcon name="people-fill" size="2xs" color="brand" />
                                <span>Target Peserta: <strong>{{ formatAngka(progres?.total || totalPesertaSiklus) }}</strong></span>
                            </div>
                            <div class="pa-gen-stat-pill">
                                <HcisIcon name="check2-circle" size="2xs" color="success" />
                                <span>Sudah Berpenugasan: <strong>{{ formatAngka(progres?.sudah || 0) }}</strong></span>
                            </div>
                            <div class="pa-gen-stat-pill">
                                <HcisIcon name="hourglass-split" size="2xs" color="warning" />
                                <span>Belum Berpenugasan: <strong>{{ formatAngka(progres?.belum || 0) }}</strong></span>
                            </div>
                            <div class="pa-gen-stat-pill">
                                <HcisIcon name="link-45deg" size="2xs" color="purple" />
                                <span>Penugasan Terbentuk: <strong>{{ formatAngka(progres?.penugasan || 0) }}</strong></span>
                            </div>
                        </div>

                        <!-- Kegagalan TUGAS (mis. database penuh) tampil di sini, di bagian yang gagal — bukan teks abu di footer. -->
                        <HcisAlert
                            v-if="galatBentuk"
                            variant="danger"
                            appearance="subtle"
                            size="sm"
                            title="Pembentukan terhenti"
                            :description="galatBentuk"
                            icon="exclamation-triangle-fill"
                            data-uji="galat-pembentukan"
                        />
                        <p v-else-if="tugasBentukBerjalan" class="pa-generate-latar-notice" data-uji="info-pembentukan-latar">
                            Berjalan di server — halaman ini boleh ditutup; pantau di Tugas &amp; Aktivitas.
                        </p>

                        <p v-if="bentukMassalGagal.length" class="pa-generate-error-notice">
                            <HcisIcon name="exclamation-triangle-fill" size="xs" color="danger" />
                            <span>{{ bentukMassalGagal.length }} peserta gagal: {{ bentukMassalGagal[0] }}</span>
                        </p>
                    </div>
                </div>

                <!-- FULL DASHBOARD: Terbuka saat pembentukan sudah selesai (100%) -->
                <template v-else>
                    <!-- Top Completion Banner with Re-sync Trigger -->
                    <div class="pa-generate-complete-strip">
                        <div class="pa-gen-complete-info">
                            <HcisIcon name="check-circle-fill" size="sm" color="success" />
                            <div>
                                <strong>Pembentukan Penugasan Multi-Rater Selesai</strong>
                                <span>{{ formatAngka(progres?.penugasan || totalRelasiEvaluasi) }} penugasan aktif terbentuk untuk {{ formatAngka(progres?.total || totalPesertaSiklus) }} peserta.</span>
                            </div>
                        </div>
                    </div>

                    <!-- RINCIAN — angka kesehatan, matriks per level, dan daftar peserta. Dilipat:
                         masalahnya sudah disebut di daftar tugas; ini untuk memeriksa. -->
                    <button
                        type="button"
                        class="pa-rincian-toggle"
                        data-uji="rincian-penugasan"
                        :aria-expanded="rincianTerbuka"
                        @click="rincianTerbuka = !rincianTerbuka"
                    >
                        <HcisIcon :name="rincianTerbuka ? 'chevron-down' : 'chevron-right'" size="2xs" />
                        <span>Rincian penugasan per level &amp; peserta</span>
                        <small v-if="totalPerluPerhatian > 0">{{ formatAngka(totalPerluPerhatian) }} peserta perlu perhatian</small>
                    </button>
                    <div v-show="rincianTerbuka" class="pa-rincian-isi" data-uji="rincian-penugasan-isi">

                    <!-- Executive Cockpit: 4 Stat Cards Grid -->
                    <div class="pa-cockpit-grid">
                        <!-- Stat 1: Kesiapan Siklus -->
                        <div
                            class="pa-cockpit-card"
                            :class="siapDikunciPenuh ? 'is-ready' : 'is-warning'"
                        >
                            <div class="pa-cockpit-icon-wrap">
                                <HcisIcon
                                    :name="siapDikunciPenuh ? 'check-circle-fill' : 'exclamation-circle-fill'"
                                    size="sm"
                                />
                            </div>
                            <div class="pa-cockpit-body">
                                <span class="pa-cockpit-label">Locking Readiness</span>
                                <div class="pa-cockpit-val-line">
                                    <span class="pa-cockpit-status-text">
                                        {{ siapDikunciPenuh ? 'Siap Dikunci' : 'Perlu Penyesuaian' }}
                                    </span>
                                </div>
                                <span class="pa-cockpit-sub">
                                    <template v-if="siapDikunciPenuh">Seluruh rater lengkap, aktif & dikenal HC Learn</template>
                                    <template v-else-if="penghalangHcLearn.length">
                                        {{ penghalangHcLearn.length }} orang ditolak HC Learn
                                    </template>
                                    <template v-else>{{ totalKurang + totalNonaktif }} isu rater terdeteksi</template>
                                </span>
                            </div>
                        </div>

                        <!-- Stat 2: Rater Nonaktif / Resign -->
                        <div
                            class="pa-cockpit-card"
                            :class="totalNonaktif > 0 ? 'is-danger' : 'is-neutral'"
                        >
                            <div class="pa-cockpit-icon-wrap">
                                <HcisIcon name="person-x-fill" size="sm" />
                            </div>
                            <div class="pa-cockpit-body">
                                <span class="pa-cockpit-label">Inactive / Resigned Raters</span>
                                <div class="pa-cockpit-val-line">
                                    <strong class="pa-cockpit-val">{{ formatAngka(totalNonaktif) }}</strong>
                                    <span class="pa-cockpit-unit">Penugasan</span>
                                </div>
                                <span class="pa-cockpit-sub">
                                    {{ totalNonaktif === 0 ? 'Semua rater berstatus aktif' : 'Wajib diganti karyawan aktif' }}
                                </span>
                            </div>
                        </div>

                        <!-- Stat 3: Peserta Kurang Rater -->
                        <div
                            class="pa-cockpit-card"
                            :class="totalKurang > 0 ? 'is-warning' : 'is-neutral'"
                        >
                            <div class="pa-cockpit-icon-wrap">
                                <HcisIcon name="people-fill" size="sm" />
                            </div>
                            <div class="pa-cockpit-body">
                                <span class="pa-cockpit-label">Under-Quota Participants</span>
                                <div class="pa-cockpit-val-line">
                                    <strong class="pa-cockpit-val">{{ formatAngka(totalKurang) }}</strong>
                                    <span class="pa-cockpit-unit">Peserta</span>
                                </div>
                                <span class="pa-cockpit-sub">
                                    {{ totalKurang === 0 ? 'Semua kuota rater terpenuhi' : 'Di bawah kuota minimum aturan' }}
                                </span>
                            </div>
                        </div>

                        <!-- Stat 4: Peserta Tanpa Salinan Aturan -->
                        <div
                            class="pa-cockpit-card"
                            :class="pesertaTanpaAturan > 0 ? 'is-danger' : 'is-neutral'"
                        >
                            <div class="pa-cockpit-icon-wrap">
                                <HcisIcon name="diagram-3-fill" size="sm" />
                            </div>
                            <div class="pa-cockpit-body">
                                <span class="pa-cockpit-label">Participants without Rules</span>
                                <div class="pa-cockpit-val-line">
                                    <strong class="pa-cockpit-val">{{ formatAngka(pesertaTanpaAturan) }}</strong>
                                    <span class="pa-cockpit-unit">Peserta</span>
                                </div>
                                <span class="pa-cockpit-sub">
                                    {{ pesertaTanpaAturan === 0 ? '100% peserta terikat aturan' : 'Perlu sinkronisasi Langkah 3' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Global Health Completeness Progress Bar -->
                    <div class="pa-health-bar-container">
                        <div class="pa-health-bar-header">
                            <div class="pa-health-bar-title">
                                <HcisIcon name="shield-check" size="2xs" color="brand" />
                                <span>Tingkat Kelengkapan Penugasan Cycle</span>
                            </div>
                            <div class="pa-health-bar-stats">
                                <strong>{{ formatAngka(totalPesertaCukupRelasi) }}</strong> / {{ formatAngka(totalRelasiEvaluasi) }} relasi evaluasi lengkap
                                <span class="pa-health-percent-badge">{{ persenKelengkapan }}%</span>
                            </div>
                        </div>
                        <div class="pa-health-track">
                            <div
                                class="pa-health-fill"
                                :class="persenKelengkapan >= 100 ? 'is-complete' : 'is-partial'"
                                :style="{ width: `${persenKelengkapan}%` }"
                            />
                        </div>
                    </div>

                    <!-- Alert Banners -->
                    <div class="pa-alerts-section">
                        <!--
                         | HIJAU HANYA BILA KEDUA SISI SETUJU.
                         |
                         | Spanduk ini dulu lahir dari data KITA saja, dan berdiri di atas
                         | tombol kunci sambil berkata "siap dikunci" pada siklus yang tepat di
                         | bawahnya dinyatakan tertahan satu penilai oleh HC Learn.
                         -->
                        <HcisAlert
                            v-if="siapDikunciPenuh"
                            variant="success"
                            appearance="glass"
                            title="Seluruh Peserta Memenuhi Minimum Rater & Rater Aktif"
                            icon="check-circle-fill"
                        >
                            Cycle ini telah memenuhi semua kuota rater 360 dan tidak memiliki rater yang nonaktif. Cycle siap dikunci untuk memulai masa penilaian.
                        </HcisAlert>

                        <HcisAlert
                            v-if="pesertaTanpaAturan > 0"
                            variant="danger"
                            appearance="glass"
                            :title="`${formatAngka(pesertaTanpaAturan)} peserta tidak memiliki salinan aturan rater`"
                            icon="exclamation-octagon-fill"
                        >
                            Peserta tersebut tidak dapat dinilai atau dihitung. Periksa konfigurasi di <strong>Langkah 3 (Rater Rules)</strong> untuk level yang bersangkutan.
                        </HcisAlert>

                        <HcisAlert
                            v-if="totalNonaktif > 0"
                            variant="danger"
                            appearance="glass"
                            :title="`${formatAngka(totalNonaktif)} penugasan diberikan ke rater yang sudah TIDAK AKTIF (Resign)`"
                            icon="person-x-fill"
                        >
                            Penugasan ini tidak akan pernah diisi karena akun karyawan sudah nonaktif. <strong>Ganti rater</strong> melalui tombol <em>"Kelola Rater"</em> agar bobot kategori tidak terbuang.
                        </HcisAlert>

                        <HcisAlert
                            v-if="totalKurang > 0"
                            variant="warning"
                            appearance="glass"
                            :title="`${formatAngka(totalKurang)} peserta belum memenuhi kuota minimum rater`"
                            icon="exclamation-triangle-fill"
                        >
                            <template v-if="totalSlotKurang > totalKurang">
                                Ada <strong>{{ formatAngka(totalSlotKurang) }}</strong> slot rater yang
                                belum terisi pada {{ formatAngka(totalKurang) }} peserta itu — satu peserta
                                bisa kurang di beberapa kategori sekaligus.
                            </template>

                            Skor kompetensi mereka akan dihitung dari jumlah rater yang lebih sedikit dari kuota aturan.
                            Anda dapat melengkapi rater tambahan pada tab <em>Peserta &amp; Penugasan</em> di bawah.
                        </HcisAlert>
                    </div>

                    <!-- Segmented Tabs Navigation -->
                    <div class="pa-tab-segmented-bar">
                    <div class="pa-tab-buttons-group">
                        <button
                            type="button"
                            class="pa-seg-btn"
                            :class="{ 'is-active': tabAktif === 'matriks' }"
                            @click="tabAktif = 'matriks'"
                        >
                            <HcisIcon name="diagram-3" size="2xs" />
                            <span>Matriks Kesehatan Level & Kategori</span>
                            <span class="pa-seg-badge">{{ baris.length }}</span>
                        </button>

                        <button
                            type="button"
                            class="pa-seg-btn"
                            :class="{ 'is-active': tabAktif === 'peserta' }"
                            @click="tabAktif = 'peserta'"
                        >
                            <HcisIcon name="people" size="2xs" />
                            <span>Peserta &amp; Penugasan</span>
                            <!--
                                Lencananya jumlah yang PERLU PERHATIAN, bukan jumlah baris yang
                                sedang tampil. Kalau ia mengikuti lingkup, ia akan berubah jadi
                                "859" saat admin melihat semua orang — dan angka mendesak yang
                                berubah arti begitu lingkupnya diganti tidak bisa dipercaya.
                            -->
                            <span
                                class="pa-seg-badge"
                                :class="{ 'is-danger': totalPerluPerhatian > 0 }"
                            >
                                {{ formatAngka(totalPerluPerhatian) }}
                            </span>
                        </button>
                    </div>

                    <!--
                        Dulu di sini ada tombol keluar ke halaman Penugasan Penilai. Halaman itu
                        dibubarkan: pekerjaannya pindah ke tab "Peserta" di bawah, tempat tiap
                        baris punya tombol "Kelola Penilai" sendiri.
                    -->
                    <div class="pa-tab-actions">
                        <HcisButton
                            label="Ke daftar peserta"
                            variant="glass"
                            size="sm"
                            leading-icon="people"
                            @click="tabAktif = 'peserta'"
                        />
                    </div>
                </div>

                <!-- TAB 1: MATRIKS KESEHATAN PER LEVEL & KATEGORI (ACCORDION PER LEVEL) -->
                <div v-show="tabAktif === 'matriks'" class="pa-matriks-panel">
                    <!-- Matrix Toolbar Filter & Accordion Controls -->
                    <div class="pa-matriks-toolbar">
                        <div class="pa-matriks-filter-group">
                            <div class="pa-filter-col">
                                <label class="pa-label-xs">Filter Level</label>
                                <HcisSelect
                                    v-model="filterMatriksLevel"
                                    placeholder="Semua Level"
                                    size="sm"
                                    clearable
                                >
                                    <HcisSelectOption value="" label="Semua Level" />
                                    <HcisSelectOption
                                        v-for="lvl in daftarLevelUnik"
                                        :key="lvl.id_level"
                                        :value="String(lvl.id_level)"
                                        :label="lvl.nama_level"
                                    />
                                </HcisSelect>
                            </div>

                            <div class="pa-filter-col">
                                <label class="pa-label-xs">Filter Status Kesehatan</label>
                                <HcisSelect
                                    v-model="filterMatriksStatus"
                                    placeholder="Semua Status"
                                    size="sm"
                                    clearable
                                >
                                    <HcisSelectOption value="" label="Semua Status" />
                                    <HcisSelectOption value="kurang" label="Hanya Level dengan Kekurangan" />
                                    <HcisSelectOption value="nonaktif" label="Hanya Level dengan Rater Nonaktif" />
                                    <HcisSelectOption value="cukup" label="Hanya Level yang 100% Lengkap" />
                                </HcisSelect>
                            </div>
                        </div>

                        <div class="pa-matriks-actions-right">
                            <div class="pa-expand-toggle-btns">
                                <button
                                    type="button"
                                    class="pa-toggle-exp-btn"
                                    @click="bukaSemuaLevel"
                                >
                                    <HcisIcon name="arrows-expand" size="2xs" /> Buka Semua
                                </button>
                                <button
                                    type="button"
                                    class="pa-toggle-exp-btn"
                                    @click="tutupSemuaLevel"
                                >
                                    <HcisIcon name="arrows-collapse" size="2xs" /> Tutup Semua
                                </button>
                            </div>
                            <span class="pa-matriks-counter">
                                <strong>{{ matriksPerLevel.length }}</strong> Level Jabatan ({{ levelTerbuka.length }} terbuka)
                            </span>
                        </div>
                    </div>

                    <!-- Accordion List per Level -->
                    <div v-if="!matriksPerLevel.length" class="pa-matriks-empty">
                        <HcisEmptyState
                            title="Tidak Ada Level yang Cocok"
                            description="Ubah filter pencarian level atau status di atas untuk menampilkan data."
                            icon="diagram-3"
                        />
                    </div>

                    <HcisCollapse v-else v-model="levelTerbuka" class="pa-level-collapse">
                        <HcisCollapseItem
                            v-for="lvl in matriksPerLevel"
                            :key="lvl.id_level"
                            :name="String(lvl.id_level)"
                            class="pa-level-collapse-item"
                        >
                            <!-- Accordion Header Title -->
                            <template #title>
                                <div class="pa-lvl-header-title-wrap">
                                    <div class="pa-lvl-header-main-row">
                                        <strong class="pa-lvl-name">{{ lvl.nama_level }}</strong>
                                        <span class="pa-lvl-id-badge">ID: {{ lvl.id_level }}</span>
                                        <span class="pa-lvl-peserta-badge">
                                            <HcisIcon name="people-fill" size="2xs" />
                                            {{ formatAngka(lvl.total_peserta) }} Peserta Dinilai
                                        </span>
                                        <span class="pa-lvl-cat-count">
                                            {{ lvl.kategori_list.length }} Kategori Rater
                                        </span>
                                    </div>
                                    <div class="pa-lvl-header-sub-row">
                                        <span class="pa-lvl-progress-summary">
                                            {{ formatAngka(lvl.total_cukup) }} / {{ formatAngka(lvl.total_peserta * lvl.kategori_list.length) }} relasi rater terpenuhi
                                        </span>
                                        <div class="pa-lvl-mini-track">
                                            <div
                                                class="pa-lvl-mini-fill"
                                                :class="lvl.total_kurang === 0 ? 'is-complete' : 'is-warning'"
                                                :style="{ width: `${hitungPersen(lvl.total_cukup, lvl.total_peserta * lvl.kategori_list.length)}%` }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Accordion Header Badge -->
                            <template #badge>
                                <div class="pa-lvl-header-badge-wrap" @click.stop>
                                    <HcisBadge
                                        v-if="lvl.total_nonaktif > 0"
                                        :label="`${formatAngka(lvl.total_nonaktif)} Nonaktif`"
                                        variant="danger"
                                        appearance="glass"
                                        size="xs"
                                        dot
                                    />
                                    <HcisBadge
                                        v-if="lvl.total_kurang > 0"
                                        :label="`${formatAngka(lvl.total_kurang)} Kurang`"
                                        variant="warning"
                                        appearance="glass"
                                        size="xs"
                                        dot
                                    />
                                    <HcisBadge
                                        v-if="lvl.total_kurang === 0 && lvl.total_nonaktif === 0"
                                        label="100% Lengkap"
                                        variant="success"
                                        appearance="subtle"
                                        size="xs"
                                        dot
                                    />
                                    <button
                                        type="button"
                                        class="pa-lvl-jump-btn"
                                        title="Buka diagnostik peserta level ini"
                                        @click="lihatPesertaLevel(lvl.id_level)"
                                    >
                                        <span>Lihat Peserta</span>
                                        <HcisIcon name="arrow-right" size="2xs" />
                                    </button>
                                </div>
                            </template>

                            <!-- Accordion Body: Category Table for This Level -->
                            <div class="pa-lvl-body-table-wrap">
                                <table class="pa-lvl-table">
                                    <thead>
                                        <tr>
                                            <th scope="col" style="min-width: 180px;">Kategori Rater &amp; Bobot</th>
                                            <th scope="col" class="text-center" style="width: 110px;">Min Rater</th>
                                            <th scope="col" style="min-width: 180px;">Kemajuan Pemenuhan</th>
                                            <th scope="col" class="text-center" style="width: 130px;">Kekurangan</th>
                                            <th scope="col" class="text-center" style="width: 130px;">Rater Nonaktif</th>
                                            <th scope="col" class="text-center" style="width: 110px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(b, j) in lvl.kategori_list"
                                            :key="j"
                                            class="pa-lvl-row"
                                            :class="{
                                                'is-row-kurang': b.peserta_kurang > 0,
                                                'is-row-nonaktif': b.penilai_nonaktif > 0,
                                            }"
                                        >
                                            <!-- Kategori & Bobot -->
                                            <td>
                                                <div class="pa-kategori-cell">
                                                    <strong class="pa-kat-title">{{ formatKategori(b.nama_kategori, b.kode_kategori) }}</strong>
                                                    <span class="pa-bobot-pill">Bobot {{ b.bobot_persen }}%</span>
                                                </div>
                                            </td>

                                             <!-- Syarat Minimum -->
                                            <td class="text-center">
                                                <div class="pa-syarat-cell">
                                                    <span class="pa-min-rater">Min: <strong>{{ b.min_rater }}</strong></span>
                                                    <small v-if="b.min_selesai" class="pa-min-selesai">
                                                        (Selesai: {{ b.min_selesai }})
                                                    </small>
                                                </div>
                                            </td>

                                            <!-- Kemajuan Pemenuhan -->
                                            <td>
                                                <div class="pa-progress-cell">
                                                    <div class="pa-progress-label-line">
                                                        <span>{{ formatAngka(b.peserta_cukup) }} / {{ formatAngka(b.peserta) }} Cukup</span>
                                                        <strong>{{ hitungPersen(b.peserta_cukup, b.peserta) }}%</strong>
                                                    </div>
                                                    <div class="pa-cell-track">
                                                        <div
                                                            class="pa-cell-fill"
                                                            :class="b.peserta_kurang === 0 ? 'is-complete' : 'is-warning'"
                                                            :style="{ width: `${hitungPersen(b.peserta_cukup, b.peserta)}%` }"
                                                        />
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Kekurangan -->
                                            <td class="text-center">
                                                <span
                                                    v-if="b.peserta_kurang > 0"
                                                    class="pa-status-chip is-kurang"
                                                    title="Peserta yang belum memenuhi kuota minimum rater"
                                                >
                                                    <HcisIcon name="exclamation-circle" size="2xs" />
                                                    {{ formatAngka(b.peserta_kurang) }} Kurang
                                                </span>
                                                <span v-else class="pa-status-chip is-cukup">
                                                    <HcisIcon name="check2" size="2xs" />
                                                    Lengkap
                                                </span>
                                            </td>

                                            <!-- Rater Nonaktif -->
                                            <td class="text-center">
                                                <span
                                                    v-if="b.penilai_nonaktif > 0"
                                                    class="pa-status-chip is-nonaktif"
                                                    title="Rater yang sudah berstatus resign / tidak aktif"
                                                >
                                                    <HcisIcon name="person-x" size="2xs" />
                                                    {{ formatAngka(b.penilai_nonaktif) }} Nonaktif
                                                </span>
                                                <span v-else class="pa-status-chip is-nol">—</span>
                                            </td>

                                            <!-- Aksi -->
                                            <td class="text-center">
                                                <button
                                                    type="button"
                                                    class="pa-lihat-peserta-btn"
                                                    title="Lihat peserta level ini"
                                                    @click="lihatPesertaLevel(b.id_level)"
                                                >
                                                    <span>Lihat Orang</span>
                                                    <HcisIcon name="arrow-right" size="2xs" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </HcisCollapseItem>
                    </HcisCollapse>
                </div>

                <!-- TAB 2: DIAGNOSTIK PESERTA BERMASALAH -->
                <div v-show="tabAktif === 'peserta'" class="pa-peserta-panel">
                    <!-- Peserta Toolbar -->
                    <div class="pa-peserta-toolbar">
                        <div class="pa-peserta-search-box">
                            <HcisField
                                v-model="cari"
                                size="sm"
                                placeholder="Ketik nama karyawan atau kode / NIK..."
                                leading-icon="search"
                                clearable
                                @keydown.enter="muatOrang(1)"
                            />
                            <HcisButton
                                label="Cari"
                                variant="secondary"
                                size="sm"
                                leading-icon="search"
                                :loading="memuatOrang"
                                @click="muatOrang(1)"
                            />
                        </div>

                        <div class="pa-peserta-filters">
                            <HcisSelect
                                v-model="filterLevelPeserta"
                                placeholder="Semua Level"
                                size="sm"
                                clearable
                                @change="muatOrang(1)"
                            >
                                <HcisSelectOption value="" label="Semua Level" />
                                <HcisSelectOption
                                    v-for="lvl in daftarLevelUnik"
                                    :key="lvl.id_level"
                                    :value="String(lvl.id_level)"
                                    :label="lvl.nama_level"
                                />
                            </HcisSelect>

                            <HcisSelect
                                v-model="perHalaman"
                                size="sm"
                                @change="muatOrang(1)"
                            >
                                <HcisSelectOption :value="10" label="10 per hal" />
                                <HcisSelectOption :value="25" label="25 per hal" />
                                <HcisSelectOption :value="50" label="50 per hal" />
                                <HcisSelectOption :value="100" label="100 per hal" />
                            </HcisSelect>
                        </div>
                    </div>

                    <!--
                        Lingkup daftar: SELURUH peserta, atau hanya yang perlu perhatian.

                        Dulu tab ini mengunci `hanya_bermasalah`, sehingga admin tidak punya jalan
                        menyunting penilai orang yang penugasannya sudah lengkap — padahal
                        "sudah memenuhi minimum" tidak sama dengan "sudah benar". Mengganti
                        penilai yang keliru adalah pekerjaan yang sah, dan sebelumnya mustahil
                        dari layar mana pun.

                        Digabung dalam satu tab, bukan dipecah dua: daftarnya, pencariannya,
                        saringan levelnya, paginasinya, dan tombol "Kelola Penilai"-nya sama
                        persis. Dua tab berarti dua salinan yang akan menyimpang.
                    -->
                    <div class="pa-lingkup-bar" role="group" aria-label="Lingkup daftar peserta">
                        <button
                            type="button"
                            class="pa-lingkup-btn"
                            :class="{ 'is-active': lingkupPeserta === 'semua' }"
                            @click="ubahLingkup('semua')"
                        >
                            <HcisIcon name="people-fill" size="2xs" />
                            Semua Peserta
                            <span class="pa-lingkup-cacah">{{ formatAngka(totalPesertaSiklus) }}</span>
                        </button>

                        <button
                            type="button"
                            class="pa-lingkup-btn"
                            :class="{ 'is-active': lingkupPeserta === 'bermasalah', 'is-danger': totalPerluPerhatian > 0 }"
                            @click="ubahLingkup('bermasalah')"
                        >
                            <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                            Perlu Perhatian
                            <span class="pa-lingkup-cacah">{{ formatAngka(totalPerluPerhatian) }}</span>
                        </button>
                    </div>

                    <!--
                     | DITOLAK HC LEARN MASUK KE SINI JUGA, dan ini bukan hiasan.
                     |
                     | Hitungan "Perlu Perhatian" lahir dari data KITA: rater kurang, tanpa
                     | aturan, atau nonaktif menurut HCIS. A1 tidak masuk satu pun — di HCIS ia
                     | masih `Aktif='Y'` — sehingga layar ini melapor NOL sambil panel di
                     | atasnya berkata satu penugasan WAJIB DIGANTI. Yang dilihat admin lebih
                     | dulu adalah nol itu.
                     |
                     | Barisnya berdiri terpisah, bukan disisipkan ke daftar dari server:
                     | daftar itu berhalaman dan disaring server, jadi menyisipkan ke dalamnya
                     | membuat orang yang sama hilang begitu admin pindah halaman.
                     -->
                    <div v-if="pesertaTerdampakHcLearn.length" class="pa-hcl-terdampak">
                        <div class="pa-hcl-terdampak__head">
                            <HcisIcon name="exclamation-octagon-fill" size="xs" color="danger" />
                            <span>
                                <strong>{{ pesertaTerdampakHcLearn.length }} peserta</strong>
                                penilainya ditolak HC Learn — penugasan ini tidak akan pernah bisa diisi.
                            </span>
                        </div>

                        <div
                            v-for="t in pesertaTerdampakHcLearn"
                            :key="`hcl-${t.participant_id}`"
                            class="pa-hcl-terdampak__row"
                        >
                            <div class="pa-person-cell">
                                <strong>{{ t.nama }} <span class="pa-hcl-kode">{{ t.kode_karyawan }}</span></strong>
                                <small>
                                    Penilai <strong>{{ t.penilai_nama }} ({{ t.penilai_kode }})</strong>
                                    &mdash; {{ t.alasan }}, kategori {{ t.nama_kategori || t.kategori }}
                                </small>
                            </div>

                            <div class="pa-hcl-terdampak__aksi">
                                <span class="pa-hcl-terdampak__saran">
                                    <template v-if="!t.cukup_dibatalkan">
                                        Ganti penilainya — tinggal {{ t.penilai_sekarang - 1 }} dari minimal
                                        {{ t.min_rater }} bila hanya dicabut.
                                    </template>
                                    <template v-else>
                                        Boleh dicabut saja — sisa {{ t.penilai_sekarang - 1 }} masih memenuhi
                                        minimal {{ t.min_rater }}.
                                    </template>
                                    Bila orangnya memang tidak lagi dinilai, kecualikan pesertanya di Langkah 4.
                                </span>

                                <HcisButton
                                    label="Kelola Rater"
                                    variant="primary"
                                    size="xs"
                                    leading-icon="pencil-square"
                                    :disabled="!bolehUbahPenilai"
                                    @click="bukaDialogPenilai(t)"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Info Note -->
                    <div class="pa-peserta-note">
                        <HcisIcon name="info-circle" size="2xs" />
                        <span v-if="lingkupPeserta === 'bermasalah'">
                            Menampilkan hanya peserta yang raternya kurang, tanpa aturan, atau
                            raternya sudah nonaktif. Klik <em>"Kelola Rater"</em> untuk memperbaikinya.
                        </span>
                        <span v-else>
                            Menampilkan <strong>seluruh</strong> peserta cycle ini, termasuk yang
                            penugasannya sudah lengkap. Klik <em>"Kelola Rater"</em> untuk mengganti
                            atau menambah rater — Anda tidak terikat usulan sistem.
                        </span>
                    </div>

                    <!-- Loading Peserta State -->
                    <div v-if="memuatOrang" class="pa-peserta-loading-list">
                        <HcisSkeleton v-for="n in 3" :key="`skel-org-${n}`" height="4.5rem" rounded="md" />
                    </div>

                    <!-- Empty Peserta State -->
                    <div v-else-if="!orang.length" class="pa-peserta-empty-state">
                        <HcisEmptyState
                            :title="lingkupPeserta === 'bermasalah'
                                ? 'Tidak ada peserta yang perlu perhatian'
                                : 'Tidak ada peserta yang cocok'"
                            :description="lingkupPeserta === 'bermasalah'
                                ? 'Seluruh peserta yang cocok dengan pencarian dan saringan ini sudah punya rater yang lengkap dan valid.'
                                : 'Tidak ada peserta yang cocok dengan pencarian atau saringan level ini. Kosongkan saringannya untuk melihat semuanya.'"
                            icon="check2-circle"
                        />
                    </div>

                    <!-- Participants List Cards -->
                    <div v-else class="pa-peserta-cards-list">
                        <div
                            v-for="o in orang"
                            :key="o.participant_id"
                            class="pa-peserta-card"
                            :class="{ 'has-danger': o.masalah.some(m => m.jenis === 'PENILAI_NONAKTIF' || m.jenis === 'TANPA_ATURAN') }"
                        >
                            <div class="pa-peserta-card-header">
                                <div class="pa-peserta-identitas">
                                    <div class="pa-peserta-avatar">
                                        {{ getInitials(o.nama) }}
                                    </div>
                                    <div class="pa-peserta-details">
                                        <div class="pa-peserta-name-line">
                                            <strong class="pa-peserta-nama">{{ o.nama }}</strong>
                                            <span class="pa-code-pill">{{ o.kode_karyawan }}</span>
                                            <span class="pa-level-pill">{{ o.nama_level }}</span>
                                        </div>
                                        <div class="pa-peserta-org-line">
                                            <span>{{ o.divisi || 'Divisi —' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="pa-peserta-actions">
                                    <HcisButton
                                        label="Kelola Rater"
                                        variant="primary"
                                        size="xs"
                                        leading-icon="pencil-square"
                                        @click="bukaDialogPenilai(o)"
                                    />
                                </div>
                            </div>

                            <!-- Issue Badges Section -->
                            <div v-if="o.masalah && o.masalah.length" class="pa-peserta-issues">
                                <span class="pa-issues-label">Isu Terdeteksi:</span>
                                <div class="pa-issues-list">
                                    <div
                                        v-for="(m, j) in o.masalah"
                                        :key="j"
                                        class="pa-issue-item"
                                        :class="getIssueClass(m.jenis)"
                                    >
                                        <HcisIcon :name="getIssueIcon(m.jenis)" size="2xs" />
                                        <span>{{ formatMasalahText(m.pesan) }}</span>
                                        <HcisButton
                                            v-if="m.jenis === 'KURANG' && m.kategori_id && dapatDisunting"
                                            label="Tandai tidak diikutkan"
                                            variant="secondary"
                                            appearance="subtle"
                                            size="xs"
                                            :data-uji="`tandai-tidak-diikutkan-${o.participant_id}-${m.kategori_id}`"
                                            @click="bukaTandaiTidakDiikutkan(o, m)"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Assigned Raters Strip -->
                            <div class="pa-peserta-raters-section">
                                <span class="pa-raters-label">Rater Terpasang ({{ o.penilai.length }}):</span>
                                <div v-if="o.penilai && o.penilai.length" class="pa-raters-chips-wrap">
                                    <div
                                        v-for="(r, j) in o.penilai"
                                        :key="j"
                                        class="pa-rater-chip"
                                        :class="{ 'is-nonaktif': !r.aktif }"
                                        :title="r.aktif ? `Status: ${r.status || 'Aktif'}` : 'Rater ini sudah TIDAK AKTIF (Resign) sebagai karyawan!'"
                                    >
                                        <span class="pa-rater-kat-tag">{{ formatKategori(r.kategori) }}</span>
                                        <span class="pa-rater-name">{{ r.nama }}</span>
                                        <span v-if="!r.aktif" class="pa-rater-dead-tag">
                                            <HcisIcon name="person-x-fill" size="2xs" />
                                            Nonaktif
                                        </span>
                                    </div>
                                </div>
                                <div v-else class="pa-raters-none">
                                    <HcisIcon name="exclamation-triangle" size="2xs" />
                                    <span>Belum ada rater yang terpasang sama sekali.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Controls -->
                    <div v-if="totalOrang > perHalaman" class="pa-pagination-bar">
                        <div class="pa-pagination-info">
                            Menampilkan <strong>{{ (halaman - 1) * perHalaman + 1 }}</strong>–<strong>{{ Math.min(halaman * perHalaman, totalOrang) }}</strong> dari <strong>{{ formatAngka(totalOrang) }}</strong> peserta bermasalah
                        </div>

                        <div class="pa-pagination-controls">
                            <HcisButton
                                label="Pertama"
                                variant="ghost"
                                size="xs"
                                :disabled="halaman <= 1"
                                @click="muatOrang(1)"
                            />
                            <HcisButton
                                label="Sebelumnya"
                                variant="secondary"
                                size="xs"
                                leading-icon="chevron-left"
                                :disabled="halaman <= 1"
                                @click="muatOrang(halaman - 1)"
                            />
                            <span class="pa-pagination-page-indicator">
                                Hal <strong>{{ halaman }}</strong> / {{ Math.ceil(totalOrang / perHalaman) }}
                            </span>
                            <HcisButton
                                label="Selanjutnya"
                                variant="secondary"
                                size="xs"
                                trailing-icon="chevron-right"
                                :disabled="halaman >= Math.ceil(totalOrang / perHalaman)"
                                @click="muatOrang(halaman + 1)"
                            />
                            <HcisButton
                                label="Terakhir"
                                variant="ghost"
                                size="xs"
                                :disabled="halaman >= Math.ceil(totalOrang / perHalaman)"
                                @click="muatOrang(Math.ceil(totalOrang / perHalaman))"
                            />
                        </div>
                    </div>
                </div>
                    </div>
                </template>
            </div>

            <h3 class="pa-alur-judul">Kunci &amp; jadwalkan ke HC Learn</h3>

            <!--
             | KESIAPAN DI HC LEARN — PEMERIKSAAN PERTAMA BAGIAN 4, DAN BARU SESUDAH PENUGASAN DIBENTUK.
             |
             | Panel yang sama berdiri di Langkah 4, tetapi di sana penilainya masih PROYEKSI dari
             | relasi organisasi. Di sini yang diperiksa adalah orang yang SUNGGUH akan dikirimi
             | pintu pengisian — pemeriksaan terakhir sebelum siklus dikunci. `store` menolak 422
             | bila satu kode saja tak dikenal, dan penolakannya membatalkan SELURUH kiriman.
             |
             | Sebelum pembentukan selesai panel ini dulu tetap tampil (UAT 13 Okt) dan meminta
             | admin "jalankan pembentukan lebih dulu" — di bawah bagian yang justru untuk itu.
             | Syaratnya pembentukan SELESAI, bukan "semua peserta berpenugasan": peserta tanpa
             | relasi sama sekali tidak pernah berpenugasan, dan syarat itu akan membiarkan siklus
             | dikunci tanpa pemeriksaan HC Learn.
             |
             | Tetap DI LUAR rantai `v-if` pemuatan: di dalamnya ia ikut dibongkar-pasang tiap kali
             | langkah ini memuat ulang — satu perjalanan tambahan ke HC Learn setiap kali.
             -->
            <button
                v-if="isGenerationComplete"
                type="button"
                class="pa-rincian-toggle"
                data-uji="rincian-hclearn"
                :aria-expanded="rincianHcLearnTerbuka"
                @click="rincianHcLearnTerbuka = !rincianHcLearnTerbuka"
            >
                <HcisIcon :name="rincianHcLearnTerbuka ? 'chevron-down' : 'chevron-right'" size="2xs" />
                <span>Rincian kesiapan data karyawan di HC Learn</span>
                <small v-if="kesiapanHcLearn">{{ ringkasKesiapanHcLearn }}</small>
            </button>
            <PaKesiapanHcLearn
                v-if="isGenerationComplete"
                v-show="rincianHcLearnTerbuka"
                ref="panelHcLearn"
                konteks="PENUGASAN"
                :cycle-id="cycleId"
                :dapat-disunting="bolehUbahPenilai"
                @pesan="terimaPesanHcLearn"
                @kelola-penilai="bukaDialogPenilai"
                @kesiapan="terimaKesiapanHcLearn"
            />
            <p v-else-if="progres" class="pa-alur-tunggu" data-uji="hcl-tunggu-pembentukan">
                Pemeriksaan kesiapan HC Learn dan penguncian muncul setelah penugasan selesai dibentuk di bagian 2.
            </p>

            <!--
             | RINGKASAN PRA-KUNCI, DI HALAMAN — BUKAN DI DIALOG.
             |
             | Angka-angka ini dulu hanya muncul sesudah admin menekan "Kunci Siklus" di
             | halaman lain, sebagai dialog konfirmasi. Artinya keputusan diambil lebih dulu,
             | angkanya dibaca belakangan, dan yang tersisa hanya membenarkan diri sendiri.
             |
             | Di sini ia terbaca SEBELUM tombolnya ditekan. Itu pula yang membuat dialog
             | konfirmasinya bisa dicabut tanpa kehilangan penjagaannya: satu tekan, tetapi
             | ditekan sambil melihat.
             -->
            <div v-if="isGenerationComplete && praKunci" class="pa-kunci-panel">
                <!-- Ringkas: masalahnya sudah di daftar tugas; di sini hanya apa yang akan dibekukan. -->
                <p class="pa-kunci-ringkas" data-uji="kunci-ringkas">
                    <HcisIcon name="shield-lock-fill" size="xs" color="brand" />
                    <span>
                        Yang dikunci: <strong>{{ formatAngka(praKunci.peserta?.diikutkan) }} peserta</strong>
                        · <strong>{{ formatAngka(praKunci.penugasan?.total) }} penugasan</strong>
                        · {{ formatAngka(praKunci.peserta?.dikecualikan) }} dikecualikan.
                        Sesudah dikunci, peserta dan aturan tidak bisa diubah; penilai masih bisa diganti.
                    </span>
                </p>

                <!-- Active Printing Status -->
                <div v-if="sedangMengunci" class="pa-kunci-alert is-info" role="status" aria-live="polite">
                    <HcisIcon name="arrow-repeat" size="xs" animation="spin" color="brand" />
                    <div class="pa-kunci-alert-body">
                        <span>Menjadwalkan akses rater…</span>
                        <strong v-if="progresPintu">
                            {{ formatAngka(progresPintu.terjadwal) }} dari {{ formatAngka(progresPintu.total) }} selesai
                        </strong>
                    </div>
                </div>

                <!-- Unfinished Doors Warning -->
                <div v-else-if="sisaPintu > 0 && sudahTerkunci" class="pa-kunci-alert is-warning">
                    <HcisIcon name="exclamation-circle-fill" size="xs" color="warning" />
                    <span>
                        Cycle sudah terkunci, tetapi <strong>{{ formatAngka(sisaPintu) }} akses rater</strong> belum selesai dibuat. Lanjutkan dari tombol yang sama — penguncian cycle tidak diulang.
                    </span>
                </div>
            </div>

            <!--
             | STATUS HC LEARN DI HALAMAN, bukan hanya di dialog. Sesudah jadwal terkirim,
             | pekerjaannya ada di HC Learn; admin yang menutup dialog harus tetap bisa melihat
             | bahwa sistem sedang MENUNGGU jawaban, bukan macet dan bukan selesai.
             -->
            <div
                v-if="sudahTerkunci && (pintuAntre > 0 || sisaPintu > 0)"
                class="pa-hcl-status"
                :class="pintuAntre > 0 ? 'is-menunggu' : 'is-belum'"
                role="status"
                aria-live="polite"
            >
                <div class="pa-hcl-status__icon" aria-hidden="true">
                    <HcisIcon
                        :name="pintuAntre > 0 ? 'arrow-repeat' : 'send-exclamation'"
                        :animation="pintuAntre > 0 ? 'spin' : null"
                        size="sm"
                    />
                </div>
                <div class="pa-hcl-status__body">
                    <strong v-if="pintuAntre > 0">Jadwal terkirim — menunggu jawaban HC Learn</strong>
                    <strong v-else>Sebagian jadwal belum terkirim ke HC Learn</strong>
                    <span>
                        Akses siap <b>{{ formatAngka(praKunci?.pintu?.tercetak) }}</b>
                        dari {{ formatAngka(praKunci?.pintu?.diharapkan) }}
                        <template v-if="pintuAntre > 0"> · <b>{{ formatAngka(pintuAntre) }}</b> sedang dibuatkan di HC Learn</template>
                        <template v-if="belumDikirim > 0"> · <b>{{ formatAngka(belumDikirim) }}</b> belum dikirim</template>
                    </span>
                    <small v-if="pintuAntre > 0">
                        <template v-if="tugasLatarBerjalan">Dicek otomatis oleh server tiap {{ JEDA_CEK_SERVER }} detik — pantau juga di Tugas &amp; Aktivitas</template>
                        <template v-else>Dicek otomatis tiap {{ INTERVAL_CEK_HCLEARN / 1000 }} detik</template><template v-if="terakhirDicekHcLearn"> · terakhir {{ terakhirDicekHcLearn }}</template>.
                        Jangan buka jendela pengisian sebelum semua akses siap.
                    </small>
                    <small v-if="galatTarikHcLearn" class="pa-hcl-status__galat">{{ galatTarikHcLearn }}</small>
                    <div class="pa-hcl-status__bar" aria-hidden="true">
                        <span class="is-siap" :style="{ width: `${persenPintu.siap}%` }"></span>
                        <span class="is-antre" :style="{ width: `${persenPintu.antre}%` }"></span>
                    </div>
                </div>
                <div class="pa-hcl-status__aksi">
                    <HcisButton
                        v-if="pintuAntre > 0"
                        label="Cek Sekarang"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-repeat"
                        :loading="menarikHasil"
                        @click="tarikHasilPenjadwalan()"
                    />
                    <HcisButton
                        label="Lihat Progres"
                        variant="ghost"
                        size="sm"
                        leading-icon="window"
                        @click="bukaProgresHcLearn"
                    />
                </div>
            </div>

            <!-- Footer Action Bar -->
            <div class="pa-step-footer">
                <div class="pa-footer-status">
                    <span v-if="galatKunci" class="pa-footer-msg pa-footer-msg--galat" role="alert">
                        {{ galatKunci }}
                    </span>

                    <!--
                     | SEBAB TOMBOL MATI DISEBUT DI SEBELAHNYA. Tombol yang mati tanpa
                     | keterangan adalah cara paling melelahkan menyampaikan aturan: admin
                     | menekannya berkali-kali lalu menyimpulkan layarnya rusak.
                     -->
                    <span
                        v-else-if="!bolehKunci && !sudahTerkunci && isGenerationComplete"
                        class="pa-footer-msg pa-footer-msg--galat"
                        role="alert"
                    >
                        Bereskan dulu yang berlabel Wajib di daftar tugas atas.
                    </span>

                    <span v-else-if="pesan" class="pa-footer-msg">{{ pesan }}</span>
                </div>

                <div class="pa-footer-actions">
                    <HcisButton
                        label="Kembali ke Langkah 4: Participants"
                        variant="ghost"
                        size="sm"
                        leading-icon="arrow-left"
                        :disabled="sedangMengunci"
                        @click="$emit('kembali')"
                    />

                    <!--
                     | SATU TOMBOL UNTUK SATU KEPUTUSAN.
                     -->
                    <template v-if="isGenerationComplete">
                        <HcisButton
                            v-if="!selesaiTerkunci"
                            :label="labelKunci"
                            variant="primary"
                            size="sm"
                            trailing-icon="lock-fill"
                            :loading="sedangMengunci"
                            :disabled="!bolehKunci"
                            @click="kunciSiklus"
                        />
                        <HcisButton
                            v-else
                            label="Selesai — Kembali ke Assessment Cycle"
                            variant="primary"
                            size="sm"
                            trailing-icon="check-lg"
                            @click="$emit('selesai')"
                        />
                    </template>
                    <template v-else>
                        <HcisButton
                            label="Selesaikan Pembentukan Penugasan Dahulu"
                            variant="secondary"
                            size="sm"
                            disabled
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>

        <!--
            Penyunting rater per peserta
        -->
        <PaAssignmentDialog
                :penilai-ditolak="petaPenilaiDitolak"
            v-if="pesertaDialog"
            v-model="dialogPenilaiTerbuka"
            :participant="pesertaDialog"
            :categories="kategoriPenilai"
            :selection="penugasan.pilihan"
            :manual="penugasan.manual"
            :readiness="penugasan.kesiapan.value"
            :loading="Boolean(penugasan.memuat.value)"
            :saving="Boolean(penugasan.menyimpan.value)"
            :calculating="Boolean(penugasan.menghitung.value)"
            :error="penugasan.error.value || ''"
            :error-status="Number(penugasan.errorStatus.value || 0)"
            :error-code="penugasan.errorCode.value || ''"
            :request-id="penugasan.requestId.value || ''"
            :submit-error="penugasan.submitError.value || ''"
            :submit-error-status="Number(penugasan.submitErrorStatus.value || 0)"
            :submit-error-code="penugasan.submitErrorCode.value || ''"
            :submit-request-id="penugasan.submitRequestId.value || ''"
            :submit-success="penugasan.submitSuccess.value || ''"
            :result="penugasan.hasilPeserta.value"
            :proposed-count="Number(penugasan.jumlahUsulanBelumSimpan?.value || 0)"
            :weights="bobotSunting"
            :search-results="hasilCariPenilai"
            :searching="sedangCariPenilai"
            :adding="menambahPenilai"
            :add-error="galatTambahPenilai"
            :add-info="kabarTambahPenilai"
            :bobot-error="bobotGalat"
            :bobot-kabar="bobotKabar"
            :locked="bobotTerkunci"
            :revoking-id="idSedangCabut"
            @retry="muatUsulanPeserta"
            @toggle-candidate="bobot.ubahPilihanDanRatakan"
            @manual-change="({ kodeKategori, field, value }) => penugasan.ubahManual(kodeKategori, field, value)"
            @save="simpanPenugasanPeserta"
            @calculate="hitungHasilPeserta"
            @bobot-change="bobot.ubahBobot"
            @bagi-rata="bobot.bagiRataKategori"
            @cancel-assignment="cabutPenilai"
            @search-rater="penilaiBaru.cariCalonPenilai"
            @tambah-penilai="penilaiBaru.tambahPenilaiBaru"
        />

        <!-- TANDAI KATEGORI SENGAJA TIDAK DIIKUTKAN (13 Okt 2026) -->
        <HcisDialog
            v-model="dialogTandaTerbuka"
            title="Tandai kategori tidak diikutkan"
            icon="slash-circle"
            size="md"
            sticky-footer
        >
            <div v-if="targetTanda" class="pa-tanda-body">
                <p class="pa-tanda-kalimat">
                    <strong>{{ targetTanda.nama }}</strong> · {{ targetTanda.namaKategori }}:
                    {{ targetTanda.terpasang }} dari minimal {{ targetTanda.minRater }} penilai.
                </p>
                <p class="pa-tanda-akibat">
                    Kategori ini tidak dihitung untuk orang ini saja. Bobotnya dibagi proporsional ke
                    kategori lain, dan penilai yang tersisa di kategori ini dibatalkan. Bisa dibatalkan
                    selama cycle belum dikunci.
                </p>
                <HcisAlert v-if="galatTanda" variant="danger" size="sm" :title="galatTanda" closable @close="galatTanda = ''" />
                <HcisTextarea
                    v-model="alasanTanda"
                    label="Alasan"
                    required
                    :rows="3"
                    :maxlength="400"
                    placeholder="Contoh: tidak ada rekan selevel di unitnya"
                    :disabled="sedangTanda"
                />
            </div>
            <template #footer>
                <HcisButton variant="secondary" label="Batal" size="sm" :disabled="sedangTanda" @click="dialogTandaTerbuka = false" />
                <HcisButton
                    variant="primary"
                    label="Tandai tidak diikutkan"
                    size="sm"
                    :loading="sedangTanda"
                    :disabled="!alasanTanda.trim() || sedangTanda"
                    @click="simpanTandaTidakDiikutkan"
                />
            </template>
        </HcisDialog>

        <HcisDialog
            v-model="dialogDaftarTandaTerbuka"
            title="Kategori yang sengaja tidak diikutkan"
            icon="slash-circle"
            size="md"
        >
            <ul v-if="tidakDiikutkan.length" class="pa-tanda-daftar">
                <li v-for="t in tidakDiikutkan" :key="`${t.participant_id}-${t.kategori_id}`" class="pa-tanda-baris">
                    <div class="pa-tanda-baris__isi">
                        <strong>{{ t.nama }}</strong>
                        <span class="pa-tanda-baris__meta">{{ t.kode_karyawan }} · {{ t.nama_level }} · {{ t.nama_kategori }}</span>
                        <span class="pa-tanda-baris__alasan">{{ t.alasan }}</span>
                    </div>
                    <HcisButton
                        v-if="dapatDisunting"
                        label="Batalkan tanda"
                        variant="secondary"
                        appearance="subtle"
                        size="xs"
                        :loading="sedangBatalTanda === `${t.participant_id}-${t.kategori_id}`"
                        :data-uji="`batalkan-tanda-${t.participant_id}-${t.kategori_id}`"
                        @click="batalkanTandaTidakDiikutkan(t)"
                    />
                </li>
            </ul>
            <p v-else class="pa-tanda-akibat">Tidak ada kategori yang ditandai.</p>
        </HcisDialog>

        <!-- MODAL KONFIRMASI CABUT PENUGASAN RATER -->
        <HcisDialog
            v-model="dialogCabutTerbuka"
            title="Cabut Penugasan Rater"
            kicker="KONFIRMASI PENCABUTAN"
            sentiment="danger"
            icon="person-x-fill"
            size="md"
            variant="glass"
            sticky-footer
        >
            <div v-if="targetCabut" class="pa-cabut-modal-body">
                <!-- Target Candidate Profile -->
                <div class="pa-cabut-target-card">
                    <div class="pa-cabut-avatar" aria-hidden="true">
                        {{ getInitials(targetCabut.nama) }}
                    </div>
                    <div class="pa-cabut-info">
                        <strong class="pa-cabut-name">{{ targetCabut.nama }}</strong>
                        <div class="pa-cabut-pills">
                            <span v-if="targetCabut.kodeKaryawan" class="pa-cabut-nik">{{ targetCabut.kodeKaryawan }}</span>
                            <span class="pa-cabut-cat-badge">{{ targetCabut.namaKategori || targetCabut.kodeKategori }}</span>
                        </div>
                    </div>
                </div>

                <!-- Warning Notice -->
                <HcisAlert
                    variant="warning"
                    appearance="glass"
                    size="sm"
                    icon="exclamation-triangle-fill"
                >
                    Mencabut rater akan membatalkan baris penugasan ini dan memicu pembagian rata ulang bobot kategori.
                </HcisAlert>

                <HcisAlert
                    v-if="cabutDiBawahMinimum"
                    variant="danger"
                    appearance="glass"
                    size="sm"
                    icon="person-exclamation"
                    title="Rater akan di bawah minimum"
                >
                    Sesudah dicabut, kategori {{ targetCabut.namaKategori || targetCabut.kodeKategori }} tinggal
                    <strong>{{ Math.max(targetCabut.terpasang - 1, 0) }}</strong> terpasang dari minimum
                    <strong>{{ targetCabut.minRater }}</strong>. Centang pengganti lalu tekan Simpan Penugasan,
                    atau cycle tidak bisa dikunci.
                </HcisAlert>

                <!-- Error Notice if any -->
                <HcisAlert
                    v-if="galatCabut"
                    variant="danger"
                    appearance="glass"
                    size="sm"
                    :title="galatCabut"
                    closable
                    @close="galatCabut = ''"
                />

                <!-- Reason Input -->
                <div class="pa-cabut-field-wrap">
                    <label class="pa-cabut-label" for="alasan-cabut-penilai">
                        <span>Alasan Pencabutan <strong class="text-danger">*</strong></span>
                        <span class="pa-cabut-label-hint">Wajib dicatat untuk audit log sistem</span>
                    </label>
                    <textarea
                        id="alasan-cabut-penilai"
                        v-model="alasanCabut"
                        class="pa-cabut-textarea"
                        rows="3"
                        placeholder="Contoh: Karyawan telah mutasi ke divisi lain / tidak lagi relevan..."
                        :disabled="sedangCabut"
                        required
                    ></textarea>
                </div>
            </div>

            <template #footer>
                <HcisButton
                    variant="secondary"
                    label="Batal"
                    size="sm"
                    :disabled="sedangCabut"
                    @click="dialogCabutTerbuka = false"
                />
                <HcisButton
                    variant="danger"
                    label="Ya, Cabut Rater"
                    size="sm"
                    leading-icon="trash3-fill"
                    :loading="sedangCabut"
                    :disabled="!alasanCabut || !alasanCabut.trim() || sedangCabut"
                    @click="eksekusiCabutPenilai"
                />
            </template>
        </HcisDialog>

        <!-- MODAL KONFIRMASI KUNCI CYCLE & JADWALKAN PENILAIAN -->
        <HcisDialog
            v-model="dialogKonfirmasiKunciTerbuka"
            :title="sudahTerkunci ? 'Konfirmasi Lanjutkan Penjadwalan Rater' : 'Konfirmasi Kunci Cycle & Jadwalkan Penilaian'"
            kicker="PERFORMANCE APPRAISAL · LANGKAH 5"
            :sentiment="sudahTerkunci ? 'brand' : 'warning'"
            :icon="sudahTerkunci ? 'arrow-clockwise' : 'shield-lock-fill'"
            size="md"
            variant="glass"
            sticky-footer
        >
            <div v-if="praKunci" class="pa-konfirmasi-kunci-modal">
                <div class="pa-konfirmasi-kunci-hero">
                    <div class="pa-konfirmasi-icon-wrap" :class="{ 'is-locked': sudahTerkunci }">
                        <HcisIcon :name="sudahTerkunci ? 'ticket-perforated-fill' : 'lock-fill'" size="lg" />
                    </div>
                    <div class="pa-konfirmasi-hero-text">
                        <h4>{{ sudahTerkunci ? 'Lanjutkan Penerbitan Akses Rater' : 'Kunci Cycle & Jadwalkan Penilaian 360' }}</h4>
                        <p v-if="!sudahTerkunci">
                            Tindakan ini akan <strong>mengunci status cycle</strong> dan secara otomatis menerbitkan token akses evaluasi bagi seluruh rater yang telah ditetapkan.
                        </p>
                        <p v-else>
                            Cycle sudah terkunci. Sistem akan melanjutkan proses penerbitan token akses untuk sisa penugasan rater.
                        </p>
                    </div>
                </div>

                <!-- 4 Quick Stats Grid -->
                <div class="pa-konfirmasi-stats-grid">
                    <div class="pa-konfirmasi-stat-card">
                        <span class="pa-stat-title">Peserta Diikutkan</span>
                        <strong class="pa-stat-num">{{ formatAngka(praKunci.peserta?.diikutkan ?? 0) }}</strong>
                    </div>
                    <div class="pa-konfirmasi-stat-card">
                        <span class="pa-stat-title">Total Penugasan</span>
                        <strong class="pa-stat-num">{{ formatAngka(praKunci.penugasan?.total ?? 0) }}</strong>
                    </div>
                    <div class="pa-konfirmasi-stat-card">
                        <span class="pa-stat-title">Akses Perlu Dibuat</span>
                        <strong class="pa-stat-num text-brand">{{ formatAngka(sisaPintu > 0 ? sisaPintu : (praKunci.penugasan?.total ?? 0)) }}</strong>
                    </div>
                    <div class="pa-konfirmasi-stat-card">
                        <span class="pa-stat-title">Peserta Tanpa Rater</span>
                        <strong class="pa-stat-num" :class="{ 'text-danger': (praKunci.penugasan?.peserta_tanpa_penilai ?? 0) > 0 }">
                            {{ formatAngka(praKunci.penugasan?.peserta_tanpa_penilai ?? 0) }}
                        </strong>
                    </div>
                </div>

                <HcisAlert
                    variant="info"
                    appearance="glass"
                    size="sm"
                    title="Berjalan di latar"
                    description="Pengiriman ke HC Learn dikerjakan server batch demi batch. Halaman ini boleh ditutup — progresnya terpantau di panel Tugas & Aktivitas."
                    icon="cloud-arrow-up-fill"
                    data-uji="info-latar"
                />

                <!-- Warning Notice if Peserta Tanpa Rater -->
                <HcisAlert
                    v-if="(praKunci.penugasan?.peserta_tanpa_penilai ?? 0) > 0"
                    variant="warning"
                    appearance="glass"
                    size="sm"
                    title="Perhatian: Peserta Tanpa Rater"
                    :description="`Terdapat ${formatAngka(praKunci.penugasan.peserta_tanpa_penilai)} peserta yang belum memiliki rater. Setelah cycle dikunci, rater tidak dapat ditambahkan lagi ke peserta tersebut.`"
                    icon="exclamation-triangle-fill"
                />

            </div>

            <template #footer>
                <HcisButton
                    variant="secondary"
                    label="Batal"
                    size="sm"
                    @click="dialogKonfirmasiKunciTerbuka = false"
                />
                <HcisButton
                    :variant="sudahTerkunci ? 'primary' : 'warning'"
                    :label="sudahTerkunci ? 'Ya, Lanjutkan Penjadwalan' : 'Ya, Kunci & Jadwalkan Sekarang'"
                    :leading-icon="sudahTerkunci ? 'play-fill' : 'lock-fill'"
                    size="sm"
                    @click="eksekusiKunciDanJadwalkan"
                />
            </template>
        </HcisDialog>

        <!-- MODAL LIQUID PROGRESS MONITOR -->
        <PaLiquidProgressDialog
            v-model="dialogProgresLiquidTerbuka"
            :status="statusProgresLiquid"
            :phase="faseProgresLiquid"
            :terjadwal="angkaProgresTerjadwal"
            :total="angkaProgresTotal"
            :sisa="angkaProgresSisa"
            :antre="angkaProgresAntre"
            :menarik="menarikHasil"
            :terakhir-dicek="terakhirDicekHcLearn"
            :interval-cek-detik="tugasLatarBerjalan ? JEDA_CEK_SERVER : INTERVAL_CEK_HCLEARN / 1000"
            :latar="!!tugasLatar"
            :ukuran-batch="450"
            :batch-index="batchPutaranAktif"
            :error-message="galatKunci"
            @retry="eksekusiKunciDanJadwalkan"
            @tarik-hasil="tarikHasilPenjadwalan"
            @finish="$emit('selesai')"
        />

</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePaBobotEditor } from '../composables/usePaBobotEditor.js';
import { usePaPenilaiBaru } from '../composables/usePaPenilaiBaru.js';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisCollapse,
    HcisCollapseItem,
    HcisDialog,
    HcisEmptyState,
    HcisField,
    HcisIcon,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisStatusBadge,
    HcisTextarea,
    hcisMessage,
} from '@/components/ui';
import paApi from '../../api/paApi.js';
import { usePaAssignment } from '../composables/usePaAssignment.js';
import PaAssignmentDialog from './PaAssignmentDialog.vue';
import PaPerubahanPeserta from './PaPerubahanPeserta.vue';
import PaDaftarTugasPenugasan from './PaDaftarTugasPenugasan.vue';
import PaRevisiPesertaMassalDialog from './PaRevisiPesertaMassalDialog.vue';
import PaKesiapanHcLearn from './PaKesiapanHcLearn.vue';
import PaLiquidProgressDialog from './PaLiquidProgressDialog.vue';
import PaStepHeader from './PaStepHeader.vue';
import { useActivityCenter } from '@/composables/useActivityCenter';

defineOptions({ name: 'PaStepPenugasan' });

const props = defineProps({
    cycleId: { type: [Number, String], required: true },
    dapatDisunting: { type: Boolean, default: false },

    /* Mengganti SATU penilai tetap boleh sesudah siklus dikunci — `TAMBAH_PENILAI` dan
       `BATALKAN_PENUGASAN` di server memang mengizinkan LOCKED dan OPEN. Dipisahkan dari
       `dapatDisunting` yang menjawab pertanyaan lain: bolehkah penugasan DIBENTUK ULANG
       massal. Menyamakan keduanya membuat siklus terkunci jadi jalan buntu. */
    bolehUbahPenilai: { type: Boolean, default: false },
    alasanTerkunci: { type: String, default: '' },

    /* Halaman relasi milik modul SYSTEM; admin PA belum tentu memegangnya. Diteruskan
       halaman induk, bukan ditanyakan sendiri: satu pertanyaan hak akses per halaman. */
    bisaKelolaRelasi: { type: Boolean, default: false },
});

const emit = defineEmits(['tersimpan', 'kembali', 'selesai', 'kesiapan']);

const baris = ref([]);
const totalKurang = ref(0);
/* Jumlah slot penilai yang belum terisi. Bukan jumlah orang: satu peserta bisa kurang di
   empat kategori sekaligus. Dipakai untuk menakar volume pekerjaan. */
const totalSlotKurang = ref(0);

/* Jumlah peserta siklus ini, diambil dari baris matriks — bukan dari daftar yang sedang tampil.
   Kalau diambil dari daftar, angkanya ikut menyusut saat admin mencari, dan "Semua Peserta (3)"
   akan terbaca seolah siklusnya berisi tiga orang. */
const totalPesertaSiklus = computed(() => {
    const perLevel = new Map();

    for (const b of baris.value) {
        perLevel.set(b.id_level, Number(b.peserta ?? 0));
    }

    return [...perLevel.values()].reduce((a, b) => a + b, 0);
});

/* Penyuntingan penilai per peserta, tanpa meninggalkan halaman ini.
   `usePaAssignment` dipakai ulang dari halaman Penugasan Penilai yang dibubarkan — satu
   sumber perilaku, bukan dua yang harus dijaga sinkron. */
const penugasan = usePaAssignment(paApi);
const pesertaDialog = ref(null);
const dialogPenilaiTerbuka = ref(false);

/*
 | Pemantauan kemajuan pembentukan.
 */
const progres = ref(null);

const radius = 84;
const circumference = 2 * Math.PI * radius; // 527.787

const persenProgres = computed(() => {
    const p = progres.value;

    if (! p || ! p.total) {
        return 0;
    }

    return Math.min(100, Math.round((p.sudah / p.total) * 100));
});

const dashOffset = computed(() => {
    const p = Math.min(100, Math.max(0, persenProgres.value));
    return circumference - (p / 100) * circumference;
});

const isGenerationComplete = computed(() => {
    if (!progres.value) {
        return false;
    }
    if (progres.value.selesai) {
        return true;
    }
    if (progres.value.total > 0 && progres.value.belum === 0) {
        return true;
    }
    return false;
});

const statusPembentukanLabel = computed(() => {
    if (sedangBentukMassal.value) {
        return 'Sedang Membentuk Penugasan Multi-Rater…';
    }
    if (progres.value && progres.value.sudah > 0 && !progres.value.selesai) {
        return 'Sebagian Terbentuk (Perlu Dilanjutkan)';
    }
    if (isGenerationComplete.value) {
        return 'Pembentukan Penugasan Selesai (100%)';
    }
    return 'Penugasan Belum Dibentuk';
});

const statusPembentukanVariant = computed(() => {
    if (sedangBentukMassal.value) {
        return 'brand';
    }
    if (progres.value && progres.value.sudah > 0 && !progres.value.selesai) {
        return 'warning';
    }
    if (isGenerationComplete.value) {
        return 'success';
    }
    return 'neutral';
});

const judulPembentukanHero = computed(() => {
    if (sedangBentukMassal.value) {
        return 'Membentuk Penugasan Rater 360 Otomatis…';
    }
    if (progres.value && progres.value.sudah > 0 && !progres.value.selesai) {
        return `Lengkapi Penugasan untuk ${formatAngka(progres.value?.belum || 0)} Peserta`;
    }
    return 'Bentuk Penugasan Rater 360 (Multi-Rater)';
});

const deskripsiPembentukanHero = computed(() => {
    if (sedangBentukMassal.value) {
        return 'Sistem sedang membentuk relasi rater (Atasan, Peer, Subordinat, Self) secara bertahap berdasarkan Master Relasi Organisasi & Rater Rules.';
    }
    if (progres.value && progres.value.sudah > 0 && !progres.value.selesai) {
        return `${formatAngka(progres.value?.sudah || 0)} dari ${formatAngka(progres.value?.total || 0)} peserta sudah berpenugasan. Sisa ${formatAngka(progres.value?.belum || 0)} peserta — peserta baru, atau pembentukan yang sempat terhenti — dibentuk tanpa menyentuh penugasan yang sudah ada.`;
    }
    return 'Sistem akan membentuk penugasan multi-rater secara otomatis untuk seluruh peserta yang diikutsertakan pada cycle ini sesuai aturan 360 Matrix.';
});

const labelTombolHero = computed(() => {
    if (progres.value && progres.value.sudah > 0 && !progres.value.selesai) {
        return 'Lengkapi Penugasan';
    }
    return 'Mulai Bentuk Penugasan';
});

const subTombolHero = computed(() => {
    if (progres.value && progres.value.sudah > 0 && !progres.value.selesai) {
        return `Sisa ${formatAngka(progres.value?.belum || 0)} Peserta`;
    }
    return `Untuk ${formatAngka(progres.value?.total || totalPesertaSiklus.value || 0)} Peserta`;
});

/* Belum selesai = masih ada yang belum berpenugasan. Dipakai untuk MEMANTAU kemajuan saja.
   |
   | Sampai 13 Okt ia juga menentukan label: siklus yang pembentukannya belum pernah dimulai
   | tampil "Sedang Membentuk Penugasan… 0 dari 190" di samping tombol "Mulai Bentuk Penugasan",
   | dan "Sebagian terbentuk" tidak pernah muncul karena cabang ini selalu menang lebih dulu.
   | Label "sedang membentuk" kini hanya mengikuti `sedangBentukMassal` — proses yang memang
   | sedang berjalan dari layar ini. */
const pembentukanBerjalan = computed(
    () => Boolean(progres.value && progres.value.total > 0 && ! progres.value.selesai),
);

/* Pembentukan penugasan: tugas latar di server; layar hanya membaca keadaannya. */
const sedangBentukMassal = ref(false);
const bentukMassalDibuat = ref(0);
const bentukMassalGagal = ref([]);

const bobot = usePaBobotEditor(penugasan);
const { bobotSunting, bobotTerkunci, bobotGalat, bobotKabar } = bobot;

/* Pencarian dan penambahan penilai juga dipakai bersama Daftar Siklus, dengan alasan yang
   sama seperti bobot di atas: dialognya satu, layarnya dua. */
const penilaiBaru = usePaPenilaiBaru({
    participantId: () => pesertaDialog.value?.Id_PA_Participant,
    segarkan: async () => {
        await muatUsulanPeserta();
        await muat();
    },
    laporkan: (teks) => { pesan.value = teks; },
    bobot,
});

const { hasilCariPenilai, sedangCariPenilai, menambahPenilai, galatTambah: galatTambahPenilai,
    kabarTambah: kabarTambahPenilai } = penilaiBaru;

const kategoriPenilai = computed(() => Object.values(penugasan.usulan?.value || {}));

/* Nol penugasan terpasang di SELURUH baris. Dibedakan dari "kurang" biasa karena sebabnya
   berbeda dan tindakannya berbeda: yang ini bukan kekurangan sebagian, melainkan langkahnya
   belum dijalankan sama sekali. */
const belumAdaPenugasanSamaSekali = computed(
    () => baris.value.length > 0 && baris.value.every((b) => Number(b.penugasan_terpasang ?? 0) === 0),
);
const totalNonaktif = ref(0);
const pesertaTanpaAturan = ref(0);
const siapDikunci = ref(false);

const orang = ref([]);
const totalOrang = ref(0);
const halaman = ref(1);
const perHalaman = ref(25);
const cari = ref('');
const filterLevelPeserta = ref('');

const filterMatriksLevel = ref('');
const filterMatriksStatus = ref('');
const tabAktif = ref('matriks');

/* Lingkup daftar peserta. Bakunya SELURUH peserta.

   Dipilih baku, bukan mengikuti keadaan data: lingkup yang berubah-ubah sendiri tergantung ada
   tidaknya masalah membuat admin menemukan layar yang berbeda tiap kali membukanya, tanpa tahu
   kenapa. Yang mendesak tidak hilang karenanya — jumlahnya selalu tampil merah di sebelahnya. */
const lingkupPeserta = ref('semua');
const levelTerbuka = ref([]);

const memuat = ref(true);
const memuatOrang = ref(false);
const error = ref('');
const pesan = ref('');

/* Panel kesiapan HC Learn memuat dirinya sendiri; acuannya disimpan supaya pembentukan
   penugasan bisa menyuruhnya membaca ulang — populasi penilainya baru saja berubah. */
const panelHcLearn = ref(null);
const panelPerubahan = ref(null);

function terimaPesanHcLearn({ teks }) {
    pesan.value = teks;
}

/* KESIAPAN HC LEARN IKUT MENENTUKAN "SIAP DIKUNCI".
 |
 | Sebelum ini layar ini menjawab dari data KITA saja: seluruh peserta memenuhi minimum rater
 | dan tidak ada rater nonaktif -> spanduk hijau "Cycle siap dikunci", "Perlu Perhatian 0".
 | Sementara panel tepat di bawahnya berkata satu penilai ditolak HC Learn dan penugasannya
 | WAJIB DIGANTI. Dua layar bersebelahan yang saling membantah, dan yang hijau berada di atas
 | tombol kunci — admin mengunci siklus yang sebenarnya belum beres, lalu mentok.
 |
 | Nonaktif MENURUT KITA dan ditolak MENURUT MEREKA adalah dua hal berbeda: A1 masih
 | `Aktif='Y'` di HCIS, jadi tidak ada satu pun hitungan lokal yang bisa menangkapnya. */
const kesiapanHcLearn = ref(null);

function terimaKesiapanHcLearn(data) {
    kesiapanHcLearn.value = data;
}

/* Panelnya hanya hidup sesudah pembentukan selesai. Bila ia dilepas (pembentukan dilanjutkan
   lagi), hasil pemeriksaan lamanya ikut dilupakan — jangan biarkan ia bicara atas populasi
   yang sedang berubah. */
watch(isGenerationComplete, (selesai) => {
    if (!selesai) kesiapanHcLearn.value = null;
});

/* MENGHALANGI, bukan sekadar tidak siap. Penilai yang penugasannya sudah dicabut tetap
   muncul di daftar — aturan masih memilihnya sebagai calon selama ia aktif di HCIS — tetapi
   penjadwalan tidak akan pernah mengirimkan namanya. Menghitungnya sebagai penghalang membuat
   perbaikan yang sudah dikerjakan admin tidak pernah menyelesaikan apa pun; layarnya tertahan
   pada nama yang panelnya sendiri sebut "tidak ada yang perlu dibereskan". */
const penghalangHcLearn = computed(
    () => (kesiapanHcLearn.value?.baris ?? []).filter((b) => b.menghalangi ?? !b.siap),
);

/* Peserta yang penilainya ditolak — inilah yang harus masuk "Perlu Perhatian". Dikunci id
   peserta supaya satu peserta yang kehilangan dua penilai tetap terhitung sekali. */
const pesertaTerdampakHcLearn = computed(() => {
    const peta = new Map();

    for (const b of penghalangHcLearn.value) {
        for (const t of (b.tanggungan ?? [])) {
            peta.set(t.participant_id, {
                ...t,
                penilai_kode: b.kode_karyawan,
                penilai_nama: b.nama,
                alasan: b.alasan,
                keterangan: b.keterangan,
            });
        }
    }

    return [...peta.values()];
});

/* Peta kode penilai yang ditolak -> sebabnya, dipakai menandai barisnya di dialog Kelola
   Rater. Peta, bukan daftar: dialognya menanyakan "orang ini bermasalah?" per baris, dan
   pencarian linear pada tiap baris kandidat adalah pekerjaan yang tidak perlu ada. */
const petaPenilaiDitolak = computed(() => {
    const peta = {};

    for (const b of penghalangHcLearn.value) {
        peta[b.kode_karyawan] = { alasan: b.alasan, keterangan: b.keterangan };
    }

    return peta;
});

/* SATU-SATUNYA jawaban "siap dikunci" yang dipakai layar ini. Yang dari server tetap
   dihormati; yang ditambahkan hanya syarat yang server itu memang tidak tahu. */
/* Yang dari server DITAMBAH yang hanya diketahui HC Learn. Peserta yang sudah terhitung
   bermasalah di sisi kita tidak dihitung dua kali — tetapi kita tidak punya daftar id-nya di
   layar ini, jadi yang dijumlahkan adalah peserta terdampak yang penilainya ditolak; angkanya
   bisa sedikit berlebih bila orang yang sama juga kurang rater, dan berlebih ke arah "periksa
   lagi" jauh lebih aman daripada nol yang menenangkan. */
const totalPerluPerhatian = computed(
    () => totalKurang.value + pesertaTerdampakHcLearn.value.length,
);

/* Penghalang kunci dari server DITAMBAH penilai yang ditolak HC Learn — yang kedua hanya
   diketahui panel kesiapan, dan server baru menolaknya saat tombol kunci ditekan. */
const semuaPenghalangKunci = computed(() => [
    ...(praKunci.value?.penghalang ?? []),
    ...penghalangHcLearn.value.map((b) => {
        const tanggungan = (b.tanggungan ?? []).map((t) => `${t.nama ?? t.participant_id}${(t.nama_kategori || t.kategori) ? ` (${t.nama_kategori || t.kategori})` : ''}`);
        return `${b.nama} (${b.kode_karyawan}) ditolak HC Learn — ${b.alasan ?? 'tidak bisa dipakai'}.`
            + (tanggungan.length ? ` Ganti penilai ini untuk: ${tanggungan.join(', ')}.` : '');
    }),
]);

/*
 | DAFTAR TUGAS LANGKAH 5 (UAT 13 Okt) — tiap masalah SEKALI, satu kalimat, satu tombol.
 |
 | Wajib = yang menahan tombol kunci, dari sumber yang SAMA dengan `bolehKunci`: penghalang
 | server (`praKunci.penghalang`) dan penilai yang ditolak HC Learn. Peserta tanpa penugasan
 | ikut lewat panel perubahan (labelnya Wajib di sana). Sisanya Disarankan: boleh dikunci,
 | tetapi akibatnya disebut.
 */
const rincianTerbuka = ref(false);

/* Revisi peserta sesudah dikunci: sesudah tersimpan, angka langkah ini dibaca ulang dan
   panel Tugas & Aktivitas diberi tahu (penjadwalannya berjalan di latar). */
const dialogRevisi = ref(false);
const targetKaryawanRevisi = ref(null);

async function sesudahRevisi() {
    targetKaryawanRevisi.value = null;
    useActivityCenter().notifyNewTask();
    await Promise.allSettled([muat(), muatPraKunci(), muatPenilaiTidakAktif()]);
    bacaTugasLatar();
    pantauTugas();
}
const rincianHcLearnTerbuka = ref(false);
const jumlahPerubahan = ref({ total: 0, wajib: 0 });

const tampilDaftarTugas = computed(
    () => sudahTerkunci.value || isGenerationComplete.value || Number(progres.value?.sudah ?? 0) > 0,
);

const ringkasKesiapanHcLearn = computed(() => (penghalangHcLearn.value.length
    ? `${penghalangHcLearn.value.length} orang ditolak HC Learn`
    : 'semua orang dikenal HC Learn'));

const tugasWajib = computed(() => {
    if (sudahTerkunci.value || !isGenerationComplete.value) return [];

    const daftar = (praKunci.value?.penghalang ?? []).map((teks, i) => (
        teks === praKunci.value?.penghalang_kekurangan
            ? {
                kunci: 'kurang-minimal',
                teks,
                rincian: 'Per peserta: Kelola Rater untuk menambah penilai, atau "Tandai tidak diikutkan" bila memang disengaja.',
                aksi: [{ label: 'Lihat peserta', ikon: 'people', uji: 'tugas-kurang-minimal', klik: lihatPesertaBermasalah }],
            }
            : teks === praKunci.value?.penghalang_nonaktif
                ? {
                    kunci: 'penilai-nonaktif',
                    teks,
                    rincian: 'Buka peserta yang ditandai "Nonaktif", lalu Kelola Rater untuk menggantinya.',
                    aksi: [{ label: 'Lihat peserta', ikon: 'people', uji: 'tugas-penilai-nonaktif', klik: lihatPesertaBermasalah }],
                }
                : { kunci: `server-${i}`, teks }
    ));

    for (const b of penghalangHcLearn.value) {
        const tanggungan = b.tanggungan ?? [];
        daftar.push({
            kunci: `hcl-${b.kode_karyawan}`,
            teks: `${b.nama} (${b.kode_karyawan}) tidak bisa dipakai di HC Learn — ${b.keterangan || b.alasan || 'ditolak'}`,
            rincian: tanggungan.length
                ? `Masih menilai: ${tanggungan.map((t) => `${t.nama} (${t.nama_kategori || t.kategori})`).join(', ')}. Ganti penilainya.`
                : 'Orang ini peserta yang dinilai: kecualikan di Langkah 4, atau minta HC Learn mengaktifkannya.',
            aksi: tanggungan.map((t) => ({
                label: tanggungan.length > 1 ? `Ganti penilai ${t.nama}` : 'Ganti penilai',
                ikon: 'people',
                uji: `tugas-ganti-${t.assignment_id}`,
                klik: () => bukaDialogPenilai(t),
            })),
        });
    }

    if (!daftar.length && praKunci.value && praKunci.value.boleh_dikunci === false) {
        daftar.push({ kunci: 'server', teks: 'Server menahan penguncian. Muat ulang langkah ini untuk melihat sebabnya.' });
    }

    return daftar;
});

/*
 | KATEGORI SENGAJA TIDAK DIIKUTKAN (UAT 13 Okt 2026). Kekurangan penilai menahan penguncian;
 | selain menambah penilai, admin boleh memutuskan kategori itu tidak dihitung bagi satu
 | orang — beralasan, tercatat, dan bisa dibatalkan selama cycle belum dikunci.
 */
const tidakDiikutkan = ref([]);
const dialogTandaTerbuka = ref(false);
const dialogDaftarTandaTerbuka = ref(false);
const targetTanda = ref(null);
const alasanTanda = ref('');
const galatTanda = ref('');
const sedangTanda = ref(false);
const sedangBatalTanda = ref('');

function bukaTandaiTidakDiikutkan(peserta, masalah) {
    targetTanda.value = {
        participantId: peserta.participant_id,
        nama: peserta.nama,
        kategoriId: masalah.kategori_id,
        namaKategori: masalah.nama_kategori || masalah.kode_kategori,
        terpasang: masalah.terpasang ?? 0,
        minRater: masalah.min_rater ?? 0,
    };
    alasanTanda.value = '';
    galatTanda.value = '';
    dialogTandaTerbuka.value = true;
}

async function segarkanSesudahTanda() {
    await Promise.allSettled([muat(), muatPraKunci(), muatOrang(halaman.value)]);
}

async function simpanTandaTidakDiikutkan() {
    const t = targetTanda.value;
    if (!t || !alasanTanda.value.trim()) return;

    sedangTanda.value = true;
    galatTanda.value = '';
    try {
        await paApi.tandaiKategoriTidakDiikutkan(t.participantId, t.kategoriId, alasanTanda.value.trim());
        dialogTandaTerbuka.value = false;
        hcisMessage.success(`${t.namaKategori} tidak diikutkan untuk ${t.nama}.`);
        await segarkanSesudahTanda();
    } catch (e) {
        galatTanda.value = e?.response?.data?.message || e?.message || 'Tanda tidak tersimpan.';
    } finally {
        sedangTanda.value = false;
    }
}

async function batalkanTandaTidakDiikutkan(t) {
    sedangBatalTanda.value = `${t.participant_id}-${t.kategori_id}`;
    try {
        await paApi.batalkanKategoriTidakDiikutkan(t.participant_id, t.kategori_id);
        hcisMessage.success(`${t.nama_kategori} kembali diikutkan untuk ${t.nama}.`);
        await segarkanSesudahTanda();
        if (!tidakDiikutkan.value.length) dialogDaftarTandaTerbuka.value = false;
    } catch (e) {
        hcisMessage.error(e?.response?.data?.message || e?.message || 'Tanda tidak bisa dibatalkan.');
    } finally {
        sedangBatalTanda.value = '';
    }
}

function lihatPesertaBermasalah() {
    rincianTerbuka.value = true;
    tabAktif.value = 'peserta';
    lingkupPeserta.value = 'bermasalah';
    muatOrang(1);
    nextTick(() => document.querySelector('.pa-peserta-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
}

/* Jadwal HC Learn milik penugasan yang dibatalkan yang masih hidup — penilainya masih bisa
   membuka tautan lama dan mengisi sia-sia. Biasanya terhapus sendiri di antrean; yang tersisa
   (HC Learn menolak, atau koneksi gagal) disebut di sini dengan tombol untuk mencoba lagi. */
const jadwalDibatalkan = ref(null);
const sedangHapusJadwal = ref(false);

/* Dibaca sendiri, ringan — dipanggil sesudah pembatalan. Penghapusannya berjalan di antrean,
   jadi pembacaan kedua beberapa detik kemudian menangkap hasilnya. */
async function muatJadwalDibatalkan() {
    try {
        const d = await paApi.statusPenjadwalanLatar(props.cycleId);
        jadwalDibatalkan.value = d?.jadwal_dibatalkan ?? null;
    } catch {
        /* Tidak menjatuhkan langkah ini. */
    }
}

async function hapusJadwalDibatalkan() {
    sedangHapusJadwal.value = true;
    try {
        await paApi.hapusJadwalDibatalkan(props.cycleId);
        hcisMessage.success('Penghapusan jadwal HC Learn dijalankan di latar.');
        setTimeout(muatJadwalDibatalkan, 5000);
    } catch (e) {
        hcisMessage.error(e?.message || 'Jadwal HC Learn tidak bisa dihapus.');
    } finally {
        sedangHapusJadwal.value = false;
    }
}

const tugasSaran = computed(() => {
    const sisaJadwal = Number(jadwalDibatalkan.value?.jumlah ?? 0);
    const tugasJadwal = sisaJadwal > 0
        ? [{
            kunci: 'jadwal-dibatalkan',
            teks: `${formatAngka(sisaJadwal)} jadwal HC Learn milik penilai yang dibatalkan belum terhapus`,
            rincian: jadwalDibatalkan.value?.contoh
                ? `HC Learn menolak: ${jadwalDibatalkan.value.contoh}`
                : 'Sedang dihapus otomatis di latar. Sampai terhapus, penilainya masih bisa membuka tautan lama (isiannya ditolak HCIS).',
            aksi: [{ label: sedangHapusJadwal.value ? 'Menghapus…' : 'Hapus sekarang', ikon: 'trash', uji: 'tugas-hapus-jadwal', klik: hapusJadwalDibatalkan }],
        }]
        : [];

    if (!isGenerationComplete.value) return tugasJadwal;

    const lihat = [{ label: 'Lihat peserta', ikon: 'people', uji: 'tugas-lihat-peserta', klik: lihatPesertaBermasalah }];
    const daftar = [];
    const tanpaRater = Number(praKunci.value?.penugasan?.peserta_tanpa_penilai ?? 0);

    if (tanpaRater > 0) {
        daftar.push({
            kunci: 'tanpa-rater',
            teks: `${formatAngka(tanpaRater)} peserta belum punya satu pun penilai`,
            rincian: 'Sesudah dikunci mereka tidak bisa lagi diberi penilai dan tidak akan punya nilai.',
            aksi: lihat,
        });
    }
    // Penilai nonaktif kini MENAHAN penguncian (Wajib, lewat penghalang server).
    /* Kekurangan penilai kini MENAHAN penguncian (masuk Wajib lewat penghalang server).
       Yang tersisa di sini hanya keputusan yang sudah diambil, supaya tetap terlihat. */
    if (tidakDiikutkan.value.length > 0) {
        daftar.push({
            kunci: 'tidak-diikutkan',
            teks: `${formatAngka(tidakDiikutkan.value.length)} kategori sengaja tidak diikutkan`,
            rincian: 'Bobotnya dibagi ke kategori lain untuk orang itu saja.',
            aksi: [{ label: 'Lihat', ikon: 'list-ul', uji: 'tugas-tidak-diikutkan', klik: () => { dialogDaftarTandaTerbuka.value = true; } }],
        });
    }
    if (pesertaTanpaAturan.value > 0) {
        daftar.push({
            kunci: 'tanpa-aturan',
            teks: `${formatAngka(pesertaTanpaAturan.value)} peserta tidak terikat aturan penilai`,
            rincian: 'Lengkapi aturan penilai levelnya di Langkah 3, lalu jalankan ulang penyiapan.',
            aksi: lihat,
        });
    }

    return [...tugasJadwal, ...daftar];
});

const siapDikunciPenuh = computed(
    () => siapDikunci.value && penghalangHcLearn.value.length === 0,
);

// Dilaporkan ke halaman induk supaya kartu langkah 5 tidak berbunyi "Completed" selama belum siap dikunci.
watch(
    () => (isGenerationComplete.value ? siapDikunciPenuh.value : null),
    (v) => emit('kesiapan', v),
    { immediate: true },
);

async function bukaDialogPenilai(baris) {
    /* Bentuk barisnya sudah cocok dengan yang diminta dialog: `pesertaBermasalah()` memulangkan
       Id_PA_Participant, Kode_Perusahaan, Kode_Karyawan, dan namanya. Tidak ada pemetaan yang
       perlu ditulis, dan tidak ada permintaan tambahan untuk mencari orang yang sudah ada di
       tangan. */
    /* Kuncinya DIPETAKAN, tidak diasumsikan sama.

       `pesertaBermasalah()` memulangkan huruf kecil bergaris bawah (`participant_id`, `nama`,
       `nama_level`), sedangkan dialog membaca nama kolom database (`Id_PA_Participant`,
       `Nama_Karyawan_Saat_Cycle`). Percobaan pertama saya menebak `id_peserta` — dialognya akan
       terbuka dengan peserta tanpa id, memuat usulan untuk `undefined`, dan gagal dengan pesan
       yang tidak menyebut sebabnya. */
    pesertaDialog.value = {
        ...baris,
        Id_PA_Participant: baris.participant_id,
        Kode_Karyawan: baris.kode_karyawan,
        Nama_Karyawan_Saat_Cycle: baris.nama,
        ID_Level_Saat_Cycle: baris.id_level,
        Nama_Level_Saat_Cycle: baris.nama_level,
        Nama_Divisi_Saat_Cycle: baris.divisi,
    };
    dialogPenilaiTerbuka.value = true;
    bobot.kosongkan();

    /* Termasuk membatalkan pencarian yang masih menunggu jedanya: hasil untuk peserta
       SEBELUMNYA yang datang belakangan akan terpasang pada peserta yang baru dibuka. */
    penilaiBaru.kosongkanHasil();
    bobot.kosongkanPesan();

    await muatUsulanPeserta();
}

async function muatUsulanPeserta() {
    const id = pesertaDialog.value?.Id_PA_Participant;

    if (! id) {
        return;
    }

    await penugasan.muat(id);
}

/* Id-nya diambil dari keadaan, BUKAN dari payload event.

   `PaAssignmentDialog` memancarkan `save` dan `calculate` TANPA argumen. Halaman Penugasan
   Penilai yang dibubarkan menuliskan penangannya sebagai `simpanPenugasan({ participantId })` —
   mendestrukturisasi `undefined`, yang melempar TypeError sebelum satu baris pun tersimpan.
   Artinya tombol Simpan di dialog itu tidak pernah bisa bekerja dari sana.

   Cacat itu tidak diwarisi: yang dipakai `pesertaDialog`, satu-satunya sumber yang memang tahu
   peserta mana yang sedang dibuka. */
/*
 | Membentuk penugasan untuk seluruh peserta, POTONGAN demi potongan.
 |
 | Setiap panggilan mengerjakan 10 peserta lalu memulangkan berapa yang masih tanpa penugasan.
 | Diulang sampai habis, atau sampai admin menghentikannya — dan berhenti di tengah TIDAK
 | merusak apa pun: yang sudah terbentuk tetap sah, dan menjalankannya lagi melanjutkan dari
 | tempatnya berhenti karena peserta yang sudah cukup dilewati.
 */
async function muatProgres() {
    if (! props.cycleId) {
        return null;
    }

    try {
        progres.value = await paApi.progresPenugasan(props.cycleId);

        return progres.value;
    } catch {
        /* Gagal membaca progres TIDAK menghentikan pemantauan sekali gagal: jaringan yang
           tersendat sesaat bukan alasan membiarkan admin menatap angka yang beku. */
        return null;
    }
}

/*
 | PEMBENTUKAN SEBAGAI TUGAS LATAR.
 |
 | Sampai Oktober 2026 halaman inilah yang memanggil pembentukan 100 peserta per putaran
 | sampai habis — 15-20 menit dengan tab terbuka untuk 10.000 peserta, dan galatnya hanya
 | teks abu di footer (13 Okt: "berhenti di 9%" karena database penuh, tanpa satu pun tanda
 | kegagalan). Sekarang halaman hanya MEMULAI tugas di server lalu MEMBACA keadaannya;
 | tugasnya tampil di panel Tugas & Aktivitas dan halaman boleh ditutup.
 */
const tugasBentuk = ref(null);
const galatBentuk = ref('');
let pemicuPantauBentuk = null;

const tugasBentukBerjalan = computed(() => ['queued', 'processing'].includes(tugasBentuk.value?.status));

function terapkanTugasBentuk(t) {
    tugasBentuk.value = t || null;
    sedangBentukMassal.value = tugasBentukBerjalan.value;
    bentukMassalDibuat.value = Number(t?.dibuat || 0);
    bentukMassalGagal.value = t?.contoh_galat || [];
    galatBentuk.value = t?.status === 'failed' ? (t.galat || 'Pembentukan terhenti.') : '';
}

/* Sesudah pembentukan berakhir, semua yang bergantung pada populasi penilai dibaca ulang —
   sama dengan yang dulu dilakukan perulangan di akhir. */
async function setelahPembentukan() {
    /* SEJAJAR dan saling lepas. Dulu berantai: satu yang gagal menghentikan sisanya, dan
       ringkasan pra-kunci tertinggal ("1 peserta belum punya penilai" padahal sudah). Selama
       dibaca ulang, layar tetap berstatus "sedang membentuk" supaya tombol Lengkapi lama
       tidak ditekan lagi (UAT 13 Okt). */
    sedangBentukMassal.value = true;
    try {
        await Promise.allSettled([muatProgres(), muatUsulanPeserta(), muat(), muatPraKunci()]);
        panelHcLearn.value?.muat();
    } finally {
        sedangBentukMassal.value = tugasBentukBerjalan.value;
    }
}

async function bacaTugasBentuk({ awal = false } = {}) {
    try {
        const data = await paApi.statusPembentukanLatar(props.cycleId);
        const t = data?.tugas || null;
        const tadinyaBerjalan = tugasBentukBerjalan.value;

        const berjalan = ['queued', 'processing'].includes(t?.status);

        /* Saat halaman dibuka, tugas yang SUDAH BERAKHIR tidak boleh bicara atas keadaan
           sekarang. Kegagalan lama hanya ditampilkan bila masih ada yang belum terbentuk. */
        if (awal && t && !berjalan) {
            if (t.status === 'failed' && !progres.value?.selesai) {
                terapkanTugasBentuk(t);
            } else {
                tugasBentuk.value = t;
            }
            return;
        }

        terapkanTugasBentuk(t);

        /* Angka tugas hanya dipakai SELAMA ia berjalan. Ringkasan tugas yang sudah selesai
           adalah potret saat itu — UAT 13 Okt: tugas lama "190/190 selesai" menimpa progres
           terbaru 190/191 sesudah Iqbal diikutkan kembali, sehingga bagian Pembentukan
           tersembunyi dan penguncian terbuka untuk peserta tanpa penugasan. */
        if (berjalan && t?.ringkasan) progres.value = { ...(progres.value || {}), ...t.ringkasan };

        if (tadinyaBerjalan && !tugasBentukBerjalan.value) {
            hentikanPantauBentuk();
            await setelahPembentukan();
            if (t?.status === 'ready') {
                hcisMessage.success('Pembentukan penugasan selesai.', 'Penugasan Terbentuk');
            }
        }
    } catch {
        /* Galat jaringan sesaat: pembacaan berikutnya yang menjawab. */
    }
}

function pantauTugasBentuk() {
    hentikanPantauBentuk();
    if (tugasBentukBerjalan.value) {
        pemicuPantauBentuk = setInterval(bacaTugasBentuk, 3000);
    }
}

function hentikanPantauBentuk() {
    clearInterval(pemicuPantauBentuk);
    pemicuPantauBentuk = null;
}

onBeforeUnmount(hentikanPantauBentuk);

async function bentukPenugasanMassal() {
    if (! props.cycleId || tugasBentukBerjalan.value) {
        return;
    }

    sedangBentukMassal.value = true;
    galatBentuk.value = '';
    pesan.value = '';

    try {
        /* Tugas yang gagal DIULANG (barisnya sama di panel), selain itu dimulai. */
        const data = tugasBentuk.value?.status === 'failed'
            ? await paApi.ulangiPembentukanLatar(props.cycleId)
            : await paApi.mulaiPembentukanLatar(props.cycleId);

        terapkanTugasBentuk(data?.tugas);
        useActivityCenter().notifyNewTask();
        pantauTugasBentuk();
    } catch (e) {
        sedangBentukMassal.value = false;
        galatBentuk.value = e?.message || 'Pembentukan penugasan gagal dimulai.';
    }
}

async function hentikanPembentukan() {
    try {
        const data = await paApi.hentikanPembentukanLatar(props.cycleId);
        terapkanTugasBentuk(data?.tugas);
        hentikanPantauBentuk();
        await setelahPembentukan();
    } catch (e) {
        galatBentuk.value = e?.message || 'Pembentukan tidak bisa dihentikan.';
    }
}

async function simpanPenugasanPeserta() {
    const participantId = pesertaDialog.value?.Id_PA_Participant;

    if (! participantId) {
        return;
    }

    /* SATU permintaan untuk penilai DAN bobotnya, lalu SATU penyegaran. Sebelumnya keduanya
       dikirim terpisah dengan penyegaran di antaranya untuk menerjemahkan id penugasan — dua
       penyegaran untuk satu kali Simpan, dan sebuah jeda di mana penugasannya sudah tersimpan
       sementara bobotnya belum. */
    const res = await penugasan.simpan(participantId, bobot.muatanSimpan());

    bobot.terapkanHasil(res);

    if (res?.error || res?.skipped) {
        return;
    }

    /* Angka kesehatan DISEGARKAN dari server sesudah menyimpan, bukan ditebak di layar.
       Menyesuaikannya sendiri berarti dua sumber untuk satu angka, dan yang di layar akan
       menyimpang dari yang di database tanpa ada yang tahu kapan. */
    await muatUsulanPeserta();
    await muat();

    /* Penilainya baru saja berubah, jadi daftar "siapa yang tergantung pada orang yang
       menghalangi" di panel HC Learn sudah basi. TANPA ditunggu: simpannya sudah berhasil. */
    panelHcLearn.value?.muat();

    hcisMessage.success('Penugasan rater dan pembagian bobot peserta berhasil disimpan.', 'Penugasan Tersimpan');
}

const dialogCabutTerbuka = ref(false);
const targetCabut = ref(null);
const alasanCabut = ref('');
const sedangCabut = ref(false);
const idSedangCabut = ref(null);
const galatCabut = ref('');

/* Diukur dari `terpasang` di server, bukan dari centang: centang usulan belum berlaku apa pun. */
const cabutDiBawahMinimum = computed(() => {
    const t = targetCabut.value;
    if (! t || ! t.minRater) return false;
    return (Number(t.terpasang || 0) - 1) < Number(t.minRater);
});

function cabutPenilai(payload) {
    targetCabut.value = payload;
    alasanCabut.value = '';
    galatCabut.value = '';
    dialogCabutTerbuka.value = true;
}

async function eksekusiCabutPenilai() {
    if (! targetCabut.value?.assignmentId) {
        return;
    }

    const alasan = alasanCabut.value?.trim();
    if (! alasan) {
        galatCabut.value = 'Alasan pencabutan wajib diisi.';
        return;
    }

    sedangCabut.value = true;
    idSedangCabut.value = targetCabut.value.assignmentId;
    galatCabut.value = '';

    const namaTarget = targetCabut.value.nama;
    const diBawahMinimum = cabutDiBawahMinimum.value;
    const namaKategori = targetCabut.value.namaKategori || targetCabut.value.kodeKategori;

    try {
        await paApi.batalkanPenugasan(targetCabut.value.assignmentId, alasan);

        /* Bobot yang sedang disunting DIBUANG: mencabut satu penilai membuat server membagi
           rata ulang kategorinya, jadi angka yang tersimpan di layar sudah tidak berlaku.

           Konfirmasinya baru ditutup SESUDAH daftar rater dimuat ulang: menutupnya lebih dulu
           membuat admin menatap centang lama tanpa tanda apa pun bahwa layar sedang diperbarui. */
        bobot.kosongkan();
        await muatUsulanPeserta();
        dialogCabutTerbuka.value = false;
        targetCabut.value = null;
        alasanCabut.value = '';
        await muat();
        panelHcLearn.value?.muat();
        if (diBawahMinimum) {
            hcisMessage.warning(
                `Penugasan ${namaTarget} dicabut. Kategori ${namaKategori} kini di bawah minimum — centang pengganti lalu Simpan Penugasan.`,
                'Rater Kurang',
            );
        } else {
            hcisMessage.success(`Penugasan ${namaTarget} berhasil dicabut.`, 'Rater Dicabut');
        }
    } catch (e) {
        galatCabut.value = e?.message || 'Gagal mencabut rater.';
    } finally {
        sedangCabut.value = false;
        idSedangCabut.value = null;
    }
}

async function hitungHasilPeserta() {
    const participantId = pesertaDialog.value?.Id_PA_Participant;

    if (! participantId) {
        return;
    }

    await penugasan.hitung(participantId);
}

function formatAngka(n) {
    return Number(n || 0).toLocaleString('id-ID');
}

function formatKategori(nama, kode = '') {
    const raw = String(nama || kode || '').trim();
    if (/rekan\s*sejawat|sejawat|peer/i.test(raw)) {
        return 'Peer';
    }
    return raw || 'Rater';
}

function formatMasalahText(teks) {
    if (!teks) return '';
    return String(teks)
        .replace(/Rekan Sejawat/gi, 'Peer')
        .replace(/Sejawat/gi, 'Peer');
}

function hitungPersen(cukup, total) {
    const tot = Number(total || 0);
    if (tot <= 0) return 0;
    return Math.min(100, Math.round((Number(cukup || 0) / tot) * 100));
}

function getInitials(name) {
    if (!name) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
}

function getIssueClass(jenis) {
    if (jenis === 'PENILAI_NONAKTIF' || jenis === 'TANPA_ATURAN') {
        return 'is-danger-issue';
    }
    return 'is-warning-issue';
}

function getIssueIcon(jenis) {
    if (jenis === 'PENILAI_NONAKTIF') return 'person-x-fill';
    if (jenis === 'TANPA_ATURAN') return 'diagram-3-fill';
    return 'exclamation-triangle-fill';
}

const totalRelasiEvaluasi = computed(() => {
    return baris.value.reduce((acc, b) => acc + (Number(b.peserta) || 0), 0);
});

const totalPesertaCukupRelasi = computed(() => {
    return baris.value.reduce((acc, b) => acc + (Number(b.peserta_cukup) || 0), 0);
});

const persenKelengkapan = computed(() => {
    if (!totalRelasiEvaluasi.value) return 100;
    return Math.min(100, Math.round((totalPesertaCukupRelasi.value / totalRelasiEvaluasi.value) * 100));
});

const daftarLevelUnik = computed(() => {
    const map = new Map();
    for (const b of baris.value) {
        if (b.id_level && !map.has(b.id_level)) {
            map.set(b.id_level, {
                id_level: b.id_level,
                nama_level: b.nama_level,
            });
        }
    }
    return Array.from(map.values());
});

const matriksPerLevel = computed(() => {
    const map = new Map();

    for (const b of baris.value) {
        if (!map.has(b.id_level)) {
            map.set(b.id_level, {
                id_level: b.id_level,
                nama_level: b.nama_level || `Level ${b.id_level}`,
                total_peserta: Number(b.peserta) || 0,
                kategori_list: [],
                total_kurang: 0,
                total_nonaktif: 0,
                total_cukup: 0,
            });
        }
        const lvl = map.get(b.id_level);
        lvl.kategori_list.push(b);
        lvl.total_kurang += Number(b.peserta_kurang) || 0;
        lvl.total_nonaktif += Number(b.penilai_nonaktif) || 0;
        lvl.total_cukup += Number(b.peserta_cukup) || 0;
    }

    return Array.from(map.values()).filter((lvl) => {
        if (filterMatriksLevel.value && String(lvl.id_level) !== String(filterMatriksLevel.value)) {
            return false;
        }
        if (filterMatriksStatus.value === 'kurang' && lvl.total_kurang <= 0) {
            return false;
        }
        if (filterMatriksStatus.value === 'nonaktif' && lvl.total_nonaktif <= 0) {
            return false;
        }
        if (filterMatriksStatus.value === 'cukup' && (lvl.total_kurang > 0 || lvl.total_nonaktif > 0)) {
            return false;
        }
        return true;
    });
});

function bukaSemuaLevel() {
    levelTerbuka.value = matriksPerLevel.value.map((l) => String(l.id_level));
}

function tutupSemuaLevel() {
    levelTerbuka.value = [];
}

function lihatPesertaLevel(idLevel) {
    filterLevelPeserta.value = String(idLevel);
    tabAktif.value = 'peserta';
    muatOrang(1);
}

async function muat() {
    memuat.value = true;
    error.value = '';

    try {
        const [response, progresData] = await Promise.all([
            paApi.kesehatanPenugasan(props.cycleId),
            paApi.progresPenugasan(props.cycleId).catch(() => null),
        ]);
        const d = response?.result ?? response?.data ?? response;

        baris.value = d?.baris ?? [];
        totalKurang.value = d?.total_peserta_kurang ?? 0;

        /* Slot dibaca TERPISAH dari orang. Sebelum ini keduanya satu angka, dan yang
           ditampilkan sebagai "peserta" sebenarnya jumlah slot (level x kategori) — pada 859
           peserta angkanya keluar 2.648, tiga kali populasinya. */
        totalSlotKurang.value = d?.total_slot_kurang ?? 0;
        totalNonaktif.value = d?.total_penilai_nonaktif ?? 0;
        pesertaTanpaAturan.value = d?.peserta_tanpa_aturan ?? 0;
        tidakDiikutkan.value = d?.tidak_diikutkan ?? [];
        siapDikunci.value = Boolean(d?.siap_dikunci);

        if (progresData) {
            progres.value = progresData;
        }
    } catch (e) {
        error.value = e?.message || 'Gagal memuat kesehatan penugasan.';
    } finally {
        memuat.value = false;
    }

    // Panel perubahan ikut dibaca ulang: pembentukan, pengecualian, dan penggantian penilai mengubah isinya.
    panelPerubahan.value?.muat();
    // Pembatalan menghapus jadwal HC Learn di antrean — dibaca sekarang dan sesaat lagi.
    muatJadwalDibatalkan();
    setTimeout(muatJadwalDibatalkan, 6000);
    await muatOrang(1);
}

function ubahLingkup(nilai) {
    if (lingkupPeserta.value === nilai) {
        return;
    }

    lingkupPeserta.value = nilai;

    /* Kembali ke halaman 1: berpindah lingkup mengubah himpunannya, dan bertahan di halaman 7
       dari himpunan yang mungkin hanya punya satu halaman akan menampilkan daftar kosong yang
       terbaca seperti "tidak ada siapa-siapa". */
    return muatOrang(1);
}

async function muatOrang(ke) {
    memuatOrang.value = true;

    try {
        const response = await paApi.penugasanBermasalah(props.cycleId, {
            halaman: ke,
            per_halaman: perHalaman.value,
            cari: cari.value.trim(),
            id_level: filterLevelPeserta.value || undefined,
            /* Mengikuti lingkup, bukan dikunci. Dikirim hanya bila memang menyaring: parameter
               `0` tetap terbaca "ada" oleh sebagian pembaca query string. */
            hanya_bermasalah: lingkupPeserta.value === 'bermasalah' ? 1 : undefined,
        });
        const d = response?.result ?? response?.data ?? response;

        orang.value = d?.baris ?? [];
        totalOrang.value = d?.total ?? 0;
        halaman.value = d?.halaman ?? ke;
    } catch (e) {
        pesan.value = e?.message || 'Gagal memuat daftar peserta.';
    } finally {
        memuatOrang.value = false;
    }
}

/* ------------------------------------------------------------------ *
 * Penguncian, dikerjakan DI SINI                                      *
 * ------------------------------------------------------------------ *
 * Penguncian bukan satu tindakan melainkan dua: membekukan aturan siklus, lalu mencetak
 * pintu HC Learn bagi ribuan penugasan. Yang kedua memakan belasan detik dan bisa berhenti
 * di tengah — dan pemulihannya menekan tombol yang SAMA sekali lagi, karena `kunci()` di
 * server menjawab siklus yang sudah LOCKED dengan keberhasilan yang jujur alih-alih galat.
 */
const praKunci = ref(null);
const sedangMengunci = ref(false);
const galatKunci = ref('');
const selesaiTerkunci = ref(false);

const sudahTerkunci = computed(() => praKunci.value?.status_siklus === 'LOCKED');

/* Penilai yang resign sesudah dikunci — hanya dihitung; daftarnya ada di dialog revisi. */
const penilaiTidakAktif = ref(0);

async function muatPenilaiTidakAktif() {
    if (!sudahTerkunci.value) {
        penilaiTidakAktif.value = 0;
        return;
    }
    try {
        const r = await paApi.cariRevisiPeserta(props.cycleId, { sumber: 'penilai', status: 'TIDAK_AKTIF' });
        penilaiTidakAktif.value = (r?.baris ?? []).length;
    } catch {
        /* Peringatan saja — langkah ini tetap bekerja tanpanya. */
    }
}

watch(sudahTerkunci, muatPenilaiTidakAktif, { immediate: true });

function bukaLepasTidakAktif() {
    targetKaryawanRevisi.value = { tindakan: 'LEPAS_PENILAI', status: 'TIDAK_AKTIF' };
    dialogRevisi.value = true;
}
const sisaPintu = computed(() => Number(praKunci.value?.pintu?.kurang ?? 0));

/* Yang SUDAH DIMINTA tetapi belum bertautan. Dipisahkan dari `sisaPintu` supaya layar bisa
   membedakan "belum diminta" dari "sudah diminta, tautannya menyusul" — dua keadaan yang
   menuntut kalimat dan tombol berbeda. */
const pintuAntre = computed(() => Number(praKunci.value?.pintu?.antre ?? 0));

/* Belum dikirim ke mana pun — berbeda dari antre yang tinggal ditunggu. */
const belumDikirim = computed(() => Math.max(0, sisaPintu.value - pintuAntre.value));

const persenPintu = computed(() => {
    const total = Number(praKunci.value?.pintu?.diharapkan ?? 0);
    if (total <= 0) return { siap: 0, antre: 0 };
    return {
        siap: Math.min(100, (Number(praKunci.value?.pintu?.tercetak ?? 0) / total) * 100),
        antre: Math.min(100, (pintuAntre.value / total) * 100),
    };
});

/* Kalimat tombolnya mengikuti keadaan, bukan sebaliknya. Tiga keadaan yang berbeda
   sebabnya berhak atas tiga kalimat yang berbeda. */
const labelKunci = computed(() => {
    if (tugasLatarBerjalan.value) return 'Lihat Progres Penjadwalan';
    /* Semua sudah dikirim dan tinggal ditunggu: menekan "Lanjutkan Penjadwalan" di sini hanya
       membuka kembali dialog progres, bukan mengirim ulang. */
    if (sudahTerkunci.value && sisaPintu.value > 0 && belumDikirim.value === 0) return 'Lihat Status HC Learn';
    if (sudahTerkunci.value && sisaPintu.value > 0) return 'Lanjutkan Penjadwalan Penilaian';
    if (sudahTerkunci.value) return 'Selesai — Kembali ke Assessment Cycle';

    return 'Kunci Cycle & Jadwalkan Penilaian';
});

const bolehKunci = computed(() => {
    if (sedangMengunci.value) return false;

    // Ringkasannya belum terbaca: menekan tombol berarti membekukan tanpa melihat apa pun.
    if (!praKunci.value) return false;

    /* Sudah terkunci: tombolnya SELALU hidup. `kunciSiklus()` memilih sendiri tindakannya —
       lanjutkan penjadwalan, lihat status HC Learn, atau "Selesai — Kembali ke Assessment
       Cycle" bila semua akses siap. Dulu baris ini mematikan tombol tepat pada keadaan
       terakhir itu, sehingga siklus yang sudah lengkap tidak bisa diselesaikan sesudah
       halamannya dimuat ulang (defect UAT 8 Okt). */
    if (sudahTerkunci.value) return true;

    /* DITAHAN selama HC Learn menolak seseorang. Server pun menolaknya di `kunci()`, tetapi
       tombol yang tetap menyala mengundang admin menekannya dan menerima galat yang bisa
       dicegah — dan sebelum penjaga itu ada, penekanan yang sama membekukan siklus ke keadaan
       yang tidak bisa dibereskan lagi lewat layar mana pun. */
    if (penghalangHcLearn.value.length > 0) return false;

    return praKunci.value.boleh_dikunci !== false;
});

async function muatPraKunci() {
    try {
        praKunci.value = await paApi.ringkasanPraKunci(props.cycleId);
    } catch (e) {
        // Panelnya boleh gagal tanpa merusak sisa layar, tetapi tombolnya ikut mati:
        // membekukan siklus tanpa satu angka pun di depan mata bukan keputusan, itu tebakan.
        praKunci.value = null;
        galatKunci.value = e?.response?.data?.message || e?.message || 'Ringkasan pra-kunci gagal dimuat.';
    }
}

/* Kemajuan penjadwalan penilaian, diperbarui tiap putaran */
const progresPintu = ref(null);

/* Dialog State: Konfirmasi & Liquid Progress */
const dialogKonfirmasiKunciTerbuka = ref(false);
const dialogProgresLiquidTerbuka = ref(false);
const statusProgresLiquid = ref('running'); // 'running' | 'success' | 'error'
const faseProgresLiquid = ref('jadwal'); // 'kunci' | 'jadwal' | 'selesai'
const batchPutaranAktif = ref(1);

const angkaProgresTerjadwal = computed(() => {
    if (progresPintu.value?.terjadwal !== undefined) {
        return Number(progresPintu.value.terjadwal);
    }
    return Number(praKunci.value?.pintu?.tercetak ?? 0);
});

const angkaProgresTotal = computed(() => {
    if (progresPintu.value?.total !== undefined) {
        return Number(progresPintu.value.total);
    }
    return Number(praKunci.value?.pintu?.diharapkan ?? praKunci.value?.penugasan?.total ?? 0);
});

const angkaProgresSisa = computed(() => {
    if (progresPintu.value?.belum !== undefined) {
        return Number(progresPintu.value.belum);
    }
    return belumDikirim.value;
});

const angkaProgresAntre = computed(() => {
    if (progresPintu.value?.antre !== undefined) {
        return Number(progresPintu.value.antre);
    }
    return pintuAntre.value;
});

function kunciSiklus() {
    if (sedangMengunci.value) return;

    /* Tugasnya masih berjalan di server: tombol hanya membuka kembali progresnya. */
    if (tugasLatarBerjalan.value) {
        dialogProgresLiquidTerbuka.value = true;
        return;
    }

    if (sudahTerkunci.value && sisaPintu.value === 0) {
        emit('selesai');
        return;
    }

    if (sudahTerkunci.value && belumDikirim.value === 0 && pintuAntre.value > 0) {
        bukaProgresHcLearn();
        return;
    }

    dialogKonfirmasiKunciTerbuka.value = true;
}

function bukaProgresHcLearn() {
    progresPintu.value = null;
    faseProgresLiquid.value = 'jadwal';
    if (pintuAntre.value > 0) {
        statusProgresLiquid.value = 'menunggu';
    } else if (sisaPintu.value === 0) {
        statusProgresLiquid.value = 'success';
    } else {
        statusProgresLiquid.value = 'error';
    }
    dialogProgresLiquidTerbuka.value = true;
}

/*
 | KUNCI & JADWALKAN SEBAGAI TUGAS LATAR.
 |
 | Sampai Oktober 2026 halaman inilah yang mengirim batch demi batch ke HC Learn: 200 penugasan
 | per putaran, sampai habis. Untuk siklus produksi (10.000+) admin harus menjaga tab tetap
 | terbuka belasan menit, dan menutupnya menghentikan pengiriman di tengah tanpa jejak.
 |
 | Sekarang halaman hanya MEMULAI tugas di server, lalu MEMBACA keadaannya. Pengiriman dan
 | penantian HC Learn dikerjakan job berantai; tugasnya tampil di panel Tugas & Aktivitas, dan
 | dialog ini boleh ditutup kapan saja.
 */
const tugasLatar = ref(null);
const INTERVAL_PANTAU_TUGAS = 4000;
/* Jeda tarik hasil oleh job di server — `PenjadwalanHcLearnPaJob::JEDA_TUNGGU`. */
const JEDA_CEK_SERVER = 30;
let pemicuPantauTugas = null;

const tugasLatarBerjalan = computed(() => ['queued', 'processing'].includes(tugasLatar.value?.status));

function terapkanTugas(data) {
    const t = data?.tugas || null;
    const sebelumnya = tugasLatar.value?.status;
    tugasLatar.value = t;
    if (data?.ringkasan) progresPintu.value = data.ringkasan;
    if (!t) return;

    batchPutaranAktif.value = Math.max(1, t.batch || 1);

    if (t.status === 'queued' || t.status === 'processing') {
        galatKunci.value = '';
        faseProgresLiquid.value = 'jadwal';
        statusProgresLiquid.value = t.fase === 'tunggu' ? 'menunggu' : 'running';
    } else if (t.status === 'ready') {
        faseProgresLiquid.value = 'selesai';
        statusProgresLiquid.value = 'success';
        /* Hanya bila BARU SAJA selesai. Tugas lama yang selesai tidak berarti siklusnya masih
           lengkap — penilai bisa ditambahkan sesudahnya; itu dibaca dari angka pra-kunci. */
        if (sebelumnya && sebelumnya !== 'ready') {
            selesaiTerkunci.value = true;
            hcisMessage.success('Seluruh akses rater sudah siap dari HC Learn.');
        }
    } else if (t.status === 'failed') {
        statusProgresLiquid.value = 'error';
        galatKunci.value = t.galat || 'Penjadwalan terhenti.';
    }
}

async function bacaTugasLatar({ awal = false } = {}) {
    try {
        const data = await paApi.statusPenjadwalanLatar(props.cycleId);
        jadwalDibatalkan.value = data?.jadwal_dibatalkan ?? null;
        const tadinyaBerjalan = tugasLatarBerjalan.value;
        const t = data?.tugas;

        /* Saat halaman dibuka, tugas yang SUDAH berakhir hanya dibawa bila masih relevan:
           kegagalan lama tak perlu dipajang lagi kalau semua akses sekarang sudah siap. */
        if (awal && t && !['queued', 'processing'].includes(t.status)
            && !(t.status === 'failed' && sisaPintu.value > 0)) {
            tugasLatar.value = t;
            return;
        }

        terapkanTugas(data);

        /* Baru saja berakhir: angka pra-kunci dibaca ulang dari server, dan induknya diberi tahu. */
        if (tadinyaBerjalan && !tugasLatarBerjalan.value) {
            await muatPraKunci();
            emit('tersimpan');
        }
    } catch {
        /* Galat jaringan sesaat: pembacaan berikutnya yang menjawab. */
    }
    if (!tugasLatarBerjalan.value) hentikanPantauTugas();
}

function pantauTugas() {
    hentikanPantauTugas();
    if (tugasLatarBerjalan.value) {
        pemicuPantauTugas = setInterval(bacaTugasLatar, INTERVAL_PANTAU_TUGAS);
    }
}

function hentikanPantauTugas() {
    clearInterval(pemicuPantauTugas);
    pemicuPantauTugas = null;
}

onBeforeUnmount(hentikanPantauTugas);

async function eksekusiKunciDanJadwalkan() {
    if (sedangMengunci.value) return;

    dialogKonfirmasiKunciTerbuka.value = false;
    dialogProgresLiquidTerbuka.value = true;
    sedangMengunci.value = true;
    galatKunci.value = '';
    statusProgresLiquid.value = 'running';
    faseProgresLiquid.value = sudahTerkunci.value ? 'jadwal' : 'kunci';
    batchPutaranAktif.value = 1;

    try {
        /* Tugas yang gagal DIULANG (barisnya sama di panel), selain itu dimulai. Keduanya aman
           diulang: server melewati penugasan yang sudah berpintu atau sedang antre, dan siklus
           yang tugasnya masih berjalan mendapat tugas itu kembali, bukan tugas kedua. */
        const data = tugasLatar.value?.status === 'failed'
            ? await paApi.ulangiPenjadwalanLatar(props.cycleId)
            : await paApi.mulaiPenjadwalanLatar(props.cycleId);

        terapkanTugas(data);
        /* Panel Tugas & Aktivitas di topbar langsung menampilkan barisnya. */
        useActivityCenter().notifyNewTask();
        await muatPraKunci();
        emit('tersimpan');
        pantauTugas();
    } catch (e) {
        galatKunci.value = e?.response?.data?.message || e?.message || 'Penguncian atau penjadwalan gagal.';
        statusProgresLiquid.value = 'error';
        await muatPraKunci();
    } finally {
        sedangMengunci.value = false;
    }
}

/**
 * Menarik hasil penjadwalan dari HC Learn, lalu membaca ulang angkanya.
 *
 * `store` menjawab 202 dan menggarap antreannya belakangan; tautan penilai baru ada sesudah
 * hasilnya ditarik. Selama tidak ada tombol untuk itu di sini, satu-satunya jalan keluar dari
 * keadaan "sudah dititipkan" adalah menutup layar — dan yang paling mungkin disimpulkan admin
 * adalah pekerjaannya sudah selesai.
 */
const INTERVAL_CEK_HCLEARN = 20000;
const menarikHasil = ref(false);
const terakhirDicekHcLearn = ref('');
const galatTarikHcLearn = ref('');
let pemicuCekHcLearn = null;

/* `diam`: dipanggil pengecek otomatis. Galat jaringan sesaat dicatat di panel, tidak
   membalik dialog ke "Terhenti" — HC Learn yang lambat menjawab bukan kegagalan. */
async function tarikHasilPenjadwalan({ diam = false } = {}) {
    if (menarikHasil.value) return;

    menarikHasil.value = true;
    if (! diam) galatKunci.value = '';

    try {
        await paApi.tarikHasilPenjadwalan(props.cycleId);
        galatTarikHcLearn.value = '';
        await muatPraKunci();

        /* Angka dialog diambil dari ringkasan pra-kunci yang baru, bukan sisa putaran kirim. */
        progresPintu.value = null;

        if (pintuAntre.value > 0) {
            /* Masih ada yang digarap: TETAP menunggu, jangan diakui selesai. */
            statusProgresLiquid.value = 'menunggu';

            return;
        }

        if (sisaPintu.value === 0) {
            selesaiTerkunci.value = true;
            statusProgresLiquid.value = 'success';
            faseProgresLiquid.value = 'selesai';
            hcisMessage.success('Seluruh akses rater sudah siap dari HC Learn.');

            return;
        }

        statusProgresLiquid.value = 'error';
        galatKunci.value = `${sisaPintu.value} penugasan masih belum punya akses.`;
    } catch (e) {
        const pesanGalat = e?.response?.data?.message || e?.message || 'Gagal menarik hasil dari HC Learn.';
        galatTarikHcLearn.value = `Cek terakhir gagal: ${pesanGalat}`;

        if (! diam) {
            statusProgresLiquid.value = 'error';
            galatKunci.value = pesanGalat;
        }
    } finally {
        menarikHasil.value = false;
        terakhirDicekHcLearn.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
}

/* Cek otomatis selama ada yang antre — admin tidak perlu menekan tombol berulang. */
watch(
    /* Bukan selama tugas latar berjalan: servernya sendiri yang menarik hasil. */
    () => sudahTerkunci.value && pintuAntre.value > 0 && ! sedangMengunci.value && ! tugasLatarBerjalan.value,
    (aktif) => {
        clearInterval(pemicuCekHcLearn);
        pemicuCekHcLearn = null;
        if (aktif) {
            pemicuCekHcLearn = setInterval(() => tarikHasilPenjadwalan({ diam: true }), INTERVAL_CEK_HCLEARN);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => clearInterval(pemicuCekHcLearn));

onMounted(async () => {
    try {
        await Promise.all([
            muat(),
            muatPraKunci(),
        ]);
    } catch (e) {
        error.value = e?.message || 'Gagal memuat data penugasan.';
    }

    /* Tugas latar yang dimulai dari sesi lain — atau sebelum halaman ini ditutup — tetap
       terbaca di sini, dan dipantau selama masih berjalan. */
    await bacaTugasBentuk({ awal: true });
    pantauTugasBentuk();
    await bacaTugasLatar({ awal: true });
    pantauTugas();
});
</script>

<style scoped>
.pa-step-root {
    width: 100%;
}

.pa-step-container {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Skeleton Loading */
.pa-pen-skeleton-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1.5rem;
    padding: 2.75rem 2rem;
    border-radius: var(--hcis-radius-2xl, 20px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(245, 243, 255, 0.7));
    border: 1px solid rgba(199, 210, 254, 0.7);
    box-shadow: 0 12px 32px -6px rgba(79, 70, 229, 0.08), 0 4px 12px rgba(15, 23, 42, 0.02);
    position: relative;
    overflow: hidden;
}

.pa-skeleton-hero-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    max-width: 580px;
}

.pa-skeleton-hero-center {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 0;
}

.pa-skeleton-radial-placeholder {
    width: 190px;
    height: 190px;
    border-radius: 50%;
    border: 12px solid rgba(226, 232, 240, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(248, 250, 252, 0.7);
}

.pa-skeleton-hero-footer {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    width: 100%;
    max-width: 860px;
}

/* Main Content Area */
.pa-pen-content {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ==========================================================================
   Hero Generation Cockpit (Interactive Action CTA with Surrounding Progress)
   ========================================================================== */
.pa-generate-cockpit-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1.6rem;
    padding: 2.5rem 2rem;
    border-radius: var(--hcis-radius-2xl, 20px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.94));
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05), 0 2px 8px rgba(0, 0, 0, 0.02);
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(12px);
}

.pa-generate-cockpit-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 8%;
    right: 8%;
    height: 3px;
    background: linear-gradient(90deg, transparent, #6366f1, #06b6d4, transparent);
    border-radius: 9999px;
}

.pa-generate-cockpit-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    max-width: 680px;
}

.pa-generate-badge-group {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.35rem 0.85rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 9999px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.pa-generate-context-text {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #334155);
    font-variant-numeric: tabular-nums;
}

.pa-generate-cockpit-title {
    margin: 0.35rem 0 0;
    font-size: clamp(1.2rem, 2.5vw, 1.55rem);
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.02em;
}

.pa-generate-cockpit-desc {
    margin: 0;
    font-size: 0.86rem;
    line-height: 1.55;
    color: var(--hcis-text-muted, #475569);
    max-width: 620px;
}

/* Hero Center with Radial Progress Ring */
.pa-generate-hero-center {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0;
}

.pa-radial-progress-wrapper {
    position: relative;
    width: 220px;
    height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-radial-svg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
    pointer-events: none;
    filter: drop-shadow(0 4px 14px rgba(99, 102, 241, 0.22));
}

.pa-radial-track {
    fill: none;
    stroke: #e2e8f0;
}

.pa-radial-fill {
    fill: none;
    stroke-linecap: round;
    transition: stroke-dashoffset 400ms cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes paPulseRing {
    0% { filter: drop-shadow(0 0 0 rgba(99, 102, 241, 0.4)); }
    50% { filter: drop-shadow(0 0 16px rgba(99, 102, 241, 0.7)); }
    100% { filter: drop-shadow(0 0 0 rgba(99, 102, 241, 0.4)); }
}

.pa-radial-progress-wrapper.is-active .pa-radial-fill {
    animation: paPulseRing 2s infinite ease-in-out;
}

.pa-radial-center-action {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Big Hero Button */
.pa-hero-action-btn {
    position: relative;
    width: 148px;
    height: 148px;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.95);
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 55%, #4338ca 100%);
    color: #ffffff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.28rem;
    cursor: pointer;
    box-shadow: 0 12px 28px -4px rgba(79, 70, 229, 0.42), inset 0 2px 5px rgba(255, 255, 255, 0.35);
    transition: all 250ms cubic-bezier(0.4, 0, 0.2, 1);
    padding: 0.75rem;
    text-align: center;
    user-select: none;
}

.pa-hero-action-btn:hover:not(:disabled) {
    transform: scale(1.05);
    background: linear-gradient(135deg, #4338ca 0%, #4f46e5 55%, #3730a3 100%);
    box-shadow: 0 16px 36px -4px rgba(79, 70, 229, 0.55), inset 0 2px 6px rgba(255, 255, 255, 0.45);
}

.pa-hero-action-btn:active:not(:disabled) {
    transform: scale(0.97);
}

.pa-hero-action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    filter: grayscale(0.5);
}

.pa-hero-action-btn.is-resume {
    background: linear-gradient(135deg, #0284c7 0%, #06b6d4 55%, #0369a1 100%);
    box-shadow: 0 12px 28px -4px rgba(6, 182, 212, 0.42), inset 0 2px 5px rgba(255, 255, 255, 0.35);
}

.pa-hero-action-btn.is-resume:hover:not(:disabled) {
    background: linear-gradient(135deg, #0369a1 0%, #0284c7 55%, #075985 100%);
    box-shadow: 0 16px 36px -4px rgba(6, 182, 212, 0.55);
}

.pa-hero-btn-icon {
    font-size: 1.45rem;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-hero-btn-text strong {
    display: block;
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.01em;
}

.pa-hero-btn-text small {
    display: block;
    font-size: 0.64rem;
    opacity: 0.9;
    margin-top: 0.15rem;
}

.pa-hero-btn-percent {
    position: absolute;
    top: -6px;
    right: -6px;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    background: #10b981;
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 850;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
    font-family: var(--hcis-font-mono, monospace);
}

/* Live Running State inside Ring */
.pa-hero-running-card {
    width: 148px;
    height: 148px;
    border-radius: 50%;
    background: #ffffff;
    border: 3px solid rgba(99, 102, 241, 0.25);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.2rem;
    box-shadow: 0 10px 25px -4px rgba(79, 70, 229, 0.15);
    padding: 0.5rem;
    text-align: center;
}

.pa-hero-running-val {
    font-size: 1.35rem;
    font-weight: 850;
    color: #4f46e5;
    font-family: var(--hcis-font-mono, monospace);
    line-height: 1;
}

.pa-hero-running-stats span {
    display: block;
    font-size: 0.62rem;
    color: #64748b;
    margin-top: 0.08rem;
    white-space: nowrap;
}

.pa-hero-pause-btn {
    margin-top: 0.2rem;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    border: 1px solid #fed7aa;
    background: #fff7ed;
    color: #c2410c;
    font-size: 0.64rem;
    font-weight: 750;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    transition: all 150ms ease;
}

.pa-hero-pause-btn:hover {
    background: #ffedd5;
    border-color: #fdba74;
}

/* Hero Footer Stats */
.pa-generate-cockpit-footer {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
}

.pa-generate-stats-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
}

.pa-gen-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.85rem;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    font-size: 0.74rem;
    color: var(--hcis-text-muted, #64748b);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 150ms ease;
}

.pa-gen-stat-pill:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.pa-gen-stat-pill strong {
    color: var(--hcis-text-strong, #0f172a);
    font-weight: 750;
    font-variant-numeric: tabular-nums;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-generate-error-notice {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    background: rgba(254, 242, 242, 0.95);
    border: 1px solid rgba(252, 165, 165, 0.6);
    color: #b91c1c;
    font-size: 0.74rem;
    font-weight: 650;
}

/* Top Complete Strip when Generation is 100% Done */
.pa-generate-complete-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 0.95rem 1.35rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.95), rgba(240, 253, 250, 0.85));
    border: 1px solid rgba(52, 211, 153, 0.45);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.08);
}

.pa-gen-complete-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-gen-complete-info strong {
    display: block;
    font-size: 0.88rem;
    color: #065f46;
    font-weight: 750;
}

.pa-gen-complete-info span {
    display: block;
    font-size: 0.75rem;
    color: #047857;
    margin-top: 0.1rem;
}

/* Cockpit Grid */
.pa-cockpit-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
}

.pa-cockpit-card {
    display: flex;
    align-items: flex-start;
    gap: 0.95rem;
    padding: 1.15rem 1.25rem;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-cockpit-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -3px rgba(15, 23, 42, 0.06);
}

.pa-cockpit-icon-wrap {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-cockpit-card.is-ready {
    background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
    border-color: #bbf7d0;
}
.pa-cockpit-card.is-ready .pa-cockpit-icon-wrap {
    background: #dcfce7;
    color: #15803d;
}

.pa-cockpit-card.is-warning {
    background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
    border-color: #fde68a;
}
.pa-cockpit-card.is-warning .pa-cockpit-icon-wrap {
    background: #fef3c7;
    color: #b45309;
}

.pa-cockpit-card.is-danger {
    background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
    border-color: #fecaca;
}
.pa-cockpit-card.is-danger .pa-cockpit-icon-wrap {
    background: #fee2e2;
    color: #b91c1c;
}

.pa-cockpit-card.is-neutral {
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border-color: #e2e8f0;
}
.pa-cockpit-card.is-neutral .pa-cockpit-icon-wrap {
    background: #f1f5f9;
    color: #64748b;
}

.pa-cockpit-body {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    flex: 1;
}

.pa-cockpit-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.pa-cockpit-val-line {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
}

.pa-cockpit-val {
    font-size: 1.45rem;
    font-weight: 800;
    color: #0f172a;
    font-family: var(--hcis-font-mono, monospace);
    letter-spacing: -0.02em;
    line-height: 1.1;
}

.pa-cockpit-status-text {
    font-size: 1.1rem;
    font-weight: 750;
    color: #0f172a;
    line-height: 1.2;
}

.pa-cockpit-unit {
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 600;
}

.pa-cockpit-sub {
    font-size: 0.75rem;
    color: #475569;
    line-height: 1.35;
    word-break: break-word;
}

/* Health Completeness Bar */
.pa-health-bar-container {
    padding: 1rem 1.25rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.pa-health-bar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-health-bar-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
}

.pa-health-bar-stats {
    font-size: 0.82rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-health-percent-badge {
    font-size: 0.74rem;
    font-weight: 750;
    color: #4f46e5;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-health-track {
    width: 100%;
    height: 8px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.pa-health-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s ease;
}

.pa-health-fill.is-complete {
    background: linear-gradient(90deg, #10b981, #059669);
}

.pa-health-fill.is-partial {
    background: linear-gradient(90deg, #6366f1, #4f46e5);
}

/* Alerts Section */
.pa-alerts-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Segmented Tabs Navigation */
.pa-tab-segmented-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.pa-tab-buttons-group {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.pa-seg-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.95rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
    background: transparent;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.18s ease;
    user-select: none;
}

.pa-seg-btn:hover {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.75);
}

.pa-seg-btn.is-active {
    color: #4f46e5;
    background: #ffffff;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(79, 70, 229, 0.04);
}

.pa-seg-badge {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 750;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: #e2e8f0;
    color: #475569;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-seg-btn.is-active .pa-seg-badge {
    background: #e0e7ff;
    color: #4338ca;
}

.pa-seg-badge.is-danger {
    background: #fee2e2;
    color: #b91c1c;
}

.pa-tab-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Matrix Table Panel */
.pa-matriks-panel {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-matriks-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 0.85rem 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.pa-matriks-filter-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-filter-col {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 170px;
}

.pa-label-xs {
    font-size: 0.72rem;
    font-weight: 650;
    color: #475569;
}

.pa-matriks-counter {
    font-size: 0.78rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}

.pa-matriks-actions-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-expand-toggle-btns {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-toggle-exp-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    font-size: 0.74rem;
    font-weight: 650;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-toggle-exp-btn:hover {
    color: #0f172a;
    background: #e2e8f0;
    border-color: #94a3b8;
}

.pa-matriks-empty {
    padding: 2.5rem 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

/* Accordion per Level */
.pa-level-collapse {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-lvl-header-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    width: 100%;
}

.pa-lvl-header-main-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.pa-lvl-name {
    font-size: 0.98rem;
    font-weight: 750;
    color: #0f172a;
}

.pa-lvl-id-badge {
    font-size: 0.7rem;
    font-weight: 600;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-lvl-peserta-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.74rem;
    font-weight: 650;
    color: #0284c7;
    background: #e0f2fe;
    padding: 0.12rem 0.55rem;
    border-radius: 999px;
}

.pa-lvl-cat-count {
    font-size: 0.74rem;
    color: #64748b;
    font-weight: 500;
}

.pa-lvl-header-sub-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    font-size: 0.76rem;
    color: #64748b;
}

.pa-lvl-mini-track {
    width: 120px;
    height: 6px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.pa-lvl-mini-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}

.pa-lvl-mini-fill.is-complete {
    background: #10b981;
}

.pa-lvl-mini-fill.is-warning {
    background: #f59e0b;
}

.pa-lvl-header-badge-wrap {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.pa-lvl-jump-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 650;
    color: #4f46e5;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-lvl-jump-btn:hover {
    background: #e0e7ff;
    border-color: #a5b4fc;
}

/* Level Body Table */
.pa-lvl-body-table-wrap {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow-x: auto;
    margin-top: 0.35rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.pa-lvl-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
    text-align: left;
}

.pa-lvl-table thead th {
    padding: 0.65rem 0.85rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 700;
    color: #475569;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.pa-lvl-row td {
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}

.pa-lvl-row:last-child td {
    border-bottom: none;
}

.pa-lvl-row.is-row-kurang {
    background: rgba(245, 158, 11, 0.04);
}

.pa-lvl-row.is-row-nonaktif {
    background: rgba(239, 68, 68, 0.04);
}

.pa-kategori-cell {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.pa-kat-title {
    color: #1e293b;
    font-weight: 700;
}

.pa-bobot-pill {
    font-size: 0.7rem;
    font-weight: 650;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-syarat-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.15rem;
}

.pa-min-rater {
    font-size: 0.8rem;
    color: #334155;
}

.pa-min-rater strong {
    color: #0f172a;
    font-weight: 750;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-min-selesai {
    font-size: 0.68rem;
    color: #64748b;
}

.pa-progress-cell {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 160px;
}

.pa-progress-label-line {
    display: flex;
    justify-content: space-between;
    font-size: 0.73rem;
    color: #475569;
}

.pa-cell-track {
    width: 100%;
    height: 6px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.pa-cell-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}

.pa-cell-fill.is-complete {
    background: #10b981;
}

.pa-cell-fill.is-warning {
    background: #f59e0b;
}

.pa-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.73rem;
    font-weight: 650;
    padding: 0.22rem 0.6rem;
    border-radius: 999px;
}

.pa-status-chip.is-kurang {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}

.pa-status-chip.is-nonaktif {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.pa-status-chip.is-cukup {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.pa-status-chip.is-nol {
    color: #94a3b8;
}

.pa-lihat-peserta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 650;
    color: #4f46e5;
    background: #f5f3ff;
    border: 1px solid #ddd6fe;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-lihat-peserta-btn:hover {
    background: #ede9fe;
    border-color: #c4b5fd;
}

/* Tanda kategori tidak diikutkan — polos, tanpa aksen */
.pa-tanda-body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-tanda-kalimat {
    margin: 0;
    font-size: 0.875rem;
}

.pa-tanda-akibat {
    margin: 0;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.8rem;
}

.pa-tanda-daftar {
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-tanda-baris {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0;
    border-bottom: 1px solid var(--hcis-border, #e2e8f0);
}

.pa-tanda-baris:last-child {
    border-bottom: 0;
}

.pa-tanda-baris__isi {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    font-size: 0.85rem;
}

.pa-tanda-baris__meta,
.pa-tanda-baris__alasan {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.78rem;
}

/* Peserta Bermasalah Panel */
.pa-peserta-panel {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-peserta-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    flex-wrap: wrap;
    padding: 0.85rem 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.pa-peserta-search-box {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex: 1;
    min-width: 260px;
    max-width: 500px;
}

.pa-peserta-filters {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-lingkup-bar {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

.pa-lingkup-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.85rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font: inherit;
    font-size: 0.8rem;
    font-weight: 650;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-lingkup-btn:hover {
    border-color: #94a3b8;
    background: #f8fafc;
}

.pa-lingkup-btn.is-active {
    border-color: #4f46e5;
    background: #eef2ff;
    color: #4338ca;
    box-shadow: 0 1px 2px rgba(79, 70, 229, 0.08);
}

.pa-lingkup-btn.is-danger .pa-lingkup-cacah {
    background: #fee2e2;
    color: #b91c1c;
}

.pa-lingkup-btn.is-active.is-danger {
    border-color: #ef4444;
    background: #fef2f2;
    color: #b91c1c;
}

.pa-lingkup-cacah {
    padding: 0.08rem 0.45rem;
    border-radius: 999px;
    background: #f1f5f9;
    font-size: 0.72rem;
    font-weight: 750;
    font-variant-numeric: tabular-nums;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-lingkup-btn.is-active .pa-lingkup-cacah {
    background: #ffffff;
}

.pa-hcl-terdampak {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    margin: 0.75rem 0;
    padding: 0.95rem 1.15rem;
    border-radius: var(--hcis-radius-md, 12px);
    border: 1px solid #fecaca;
    background: #fff5f5;
    box-shadow: 0 1px 3px rgba(239, 68, 68, 0.05);
}

.pa-hcl-terdampak__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.86rem;
    color: #991b1b;
}

.pa-hcl-terdampak__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 0.65rem;
    border-top: 1px solid #fee2e2;
}

.pa-hcl-terdampak__aksi {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
}

.pa-hcl-terdampak__saran {
    font-size: 0.78rem;
    color: #475569;
    max-width: 42rem;
    line-height: 1.4;
}

.pa-hcl-kode {
    font-weight: 600;
    color: #64748b;
    font-family: var(--hcis-font-mono, monospace);
    font-size: 0.8rem;
}

.pa-peserta-note {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78rem;
    color: #64748b;
    background: #f8fafc;
    padding: 0.55rem 0.85rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.pa-peserta-loading-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-peserta-empty-state {
    padding: 2.5rem 1rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.pa-peserta-cards-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-peserta-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.05rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-peserta-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.pa-peserta-card.has-danger {
    border-left: 4px solid #ef4444;
}

.pa-peserta-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-peserta-identitas {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.pa-peserta-avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    color: #4338ca;
    font-size: 0.85rem;
    font-weight: 750;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 4px rgba(79, 70, 229, 0.1);
}

.pa-peserta-details {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-peserta-name-line {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-peserta-nama {
    font-size: 0.95rem;
    font-weight: 750;
    color: #0f172a;
}

.pa-code-pill {
    font-size: 0.72rem;
    font-weight: 650;
    color: #475569;
    background: #f1f5f9;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-level-pill {
    font-size: 0.72rem;
    font-weight: 650;
    color: #0284c7;
    background: #e0f2fe;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
}

.pa-peserta-org-line {
    font-size: 0.76rem;
    color: #64748b;
}

/* Issues Section */
.pa-peserta-issues {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    flex-wrap: wrap;
    padding: 0.55rem 0.75rem;
    background: #fffbeb;
    border: 1px solid #fef3c7;
    border-radius: 8px;
}

.pa-issues-label {
    font-size: 0.73rem;
    font-weight: 750;
    color: #92400e;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-issues-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.pa-issue-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 650;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
}

.pa-issue-item.is-warning-issue {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}

.pa-issue-item.is-danger-issue {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

/* Raters Strip */
.pa-peserta-raters-section {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.pa-raters-label {
    font-size: 0.74rem;
    font-weight: 650;
    color: #64748b;
}

.pa-raters-chips-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.pa-rater-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.74rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #334155;
    transition: all 120ms ease;
}

.pa-rater-chip:hover {
    border-color: #cbd5e1;
    background: #ffffff;
}

.pa-rater-kat-tag {
    font-weight: 700;
    color: #4f46e5;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-rater-name {
    color: #1e293b;
    font-weight: 600;
}

.pa-rater-chip.is-nonaktif {
    border-color: #fca5a5;
    background: #fef2f2;
    color: #b91c1c;
}

.pa-rater-chip.is-nonaktif .pa-rater-name {
    text-decoration: line-through;
    color: #991b1b;
}

.pa-rater-dead-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.66rem;
    font-weight: 750;
    background: #fee2e2;
    color: #dc2626;
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
}

.pa-raters-none {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: #d97706;
}

/* Pagination Bar */
.pa-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.15rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    flex-wrap: wrap;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.pa-pagination-info {
    font-size: 0.78rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}

.pa-pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.pa-pagination-page-indicator {
    font-size: 0.76rem;
    color: #475569;
    margin: 0 0.35rem;
    font-variant-numeric: tabular-nums;
}

/* ==========================================================================
   Pre-Lock Verification Card (pa-kunci-panel)
   ========================================================================== */
.pa-kunci-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    margin-top: 1.25rem;
    padding: 1.5rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02);
    position: relative;
}

.pa-kunci-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding-bottom: 0.95rem;
    border-bottom: 1px solid #f1f5f9;
}

.pa-kunci-header-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.pa-kunci-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    color: var(--hcis-brand, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.8), 0 2px 4px rgba(79, 70, 229, 0.08);
    flex-shrink: 0;
}

.pa-kunci-title-group {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.pa-kunci-title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.01em;
}

.pa-kunci-subtitle {
    margin: 0;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-kunci-header-right {
    display: flex;
    align-items: center;
}

/* Stat Grid */
.pa-kunci-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1rem;
}

.pa-kunci-stat-card {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 1.1rem 1.25rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-kunci-stat-card:hover {
    background: #ffffff;
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.05);
}

.pa-kunci-stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-kunci-stat-label {
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-text-muted, #64748b);
}

.pa-kunci-stat-badge {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-kunci-stat-badge.is-brand {
    background: #eef2ff;
    color: #4f46e5;
}

.pa-kunci-stat-badge.is-muted {
    background: #f1f5f9;
    color: #64748b;
}

.pa-kunci-stat-badge.is-purple {
    background: #f5f3ff;
    color: #7c3aed;
}

.pa-kunci-stat-badge.is-success {
    background: #ecfdf5;
    color: #059669;
}

.pa-kunci-stat-badge.is-warning {
    background: #fffbeb;
    color: #d97706;
}

.pa-kunci-stat-value {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-kunci-stat-sub {
    font-size: 0.74rem;
    color: var(--hcis-text-subtle, #94a3b8);
}

/* Stat Card Alert Variants */
.pa-kunci-stat-card.is-alert-warning {
    background: #fffdf5;
    border-color: #fef08a;
}

.pa-kunci-stat-card.is-alert-warning .pa-kunci-stat-value {
    color: #b45309;
}

.pa-kunci-stat-card.is-alert-success {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.pa-kunci-stat-card.is-alert-success .pa-kunci-stat-value {
    color: #15803d;
}

/* Alerts and Callouts */
.pa-kunci-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.95rem 1.15rem;
    border-radius: 10px;
    font-size: 0.84rem;
    line-height: 1.45;
}

.pa-kunci-alert.is-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

.pa-kunci-alert.is-danger {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.pa-kunci-alert.is-info {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
}

.pa-kunci-alert-body {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex: 1;
}

.pa-kunci-halang {
    margin: 0;
    padding-left: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-footer-msg--galat {
    color: var(--hcis-danger-700, #b91c1c);
    font-weight: 600;
}

.pa-hcl-status {
    display: flex;
    align-items: flex-start;
    gap: 0.95rem;
    margin: 1rem 0 0.5rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    border: 1px solid rgba(2, 132, 199, 0.25);
    background: linear-gradient(135deg, rgba(2, 132, 199, 0.08), rgba(2, 132, 199, 0.02));
    box-shadow: 0 1px 3px rgba(2, 132, 199, 0.04);
}

.pa-hcl-status.is-belum {
    border-color: rgba(245, 158, 11, 0.35);
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.02));
}

.pa-hcl-status__icon {
    flex: 0 0 auto;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: var(--hcis-brand, #0284c7);
    box-shadow: 0 2px 4px rgba(2, 132, 199, 0.2);
}

.pa-hcl-status.is-belum .pa-hcl-status__icon {
    background: var(--hcis-warning, #f59e0b);
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
}

.pa-hcl-status__body {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.82rem;
    color: var(--hcis-text-secondary, #475569);
}

.pa-hcl-status__body strong {
    font-size: 0.9rem;
    color: var(--hcis-text-primary, #0f172a);
}

.pa-hcl-status__body small {
    color: var(--hcis-text-muted, #64748b);
}

.pa-hcl-status__galat {
    color: var(--hcis-danger, #dc2626) !important;
}

.pa-hcl-status__bar {
    display: flex;
    height: 7px;
    margin-top: 0.4rem;
    border-radius: 999px;
    overflow: hidden;
    background: rgba(148, 163, 184, 0.2);
}

.pa-hcl-status__bar .is-siap {
    background: var(--hcis-success, #10b981);
    transition: width 0.5s ease;
}

.pa-hcl-status__bar .is-antre {
    background: repeating-linear-gradient(45deg, rgba(2, 132, 199, 0.6) 0 6px, rgba(2, 132, 199, 0.3) 6px 12px);
    transition: width 0.5s ease;
}

.pa-hcl-status__aksi {
    flex: 0 0 auto;
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.pa-step-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.35rem;
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    flex-wrap: wrap;
    margin-top: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.pa-footer-status {
    flex: 1;
    min-width: 220px;
}

.pa-footer-msg {
    font-size: 0.82rem;
    color: #64748b;
    line-height: 1.4;
}

.pa-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

/* CABUT PENILAI CONFIRMATION DIALOG */
.pa-cabut-modal-body {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-cabut-target-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #fef2f2;
    border: 1px solid #fecaca;
}

.pa-cabut-avatar {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-cabut-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-cabut-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: #991b1b;
}

.pa-cabut-pills {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.pa-cabut-nik {
    font-size: 0.7rem;
    font-weight: 600;
    color: #7f1d1d;
    background: #fee2e2;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
}

.pa-cabut-cat-badge {
    font-size: 0.7rem;
    font-weight: 600;
    color: #475569;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 0.05rem 0.4rem;
    border-radius: 4px;
}

.pa-cabut-field-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-cabut-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.78rem;
    font-weight: 700;
    color: #334155;
}

.pa-cabut-label-hint {
    font-size: 0.7rem;
    font-weight: normal;
    color: #64748b;
}

.pa-cabut-textarea {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font: inherit;
    font-size: 0.82rem;
    color: #0f172a;
    background: #ffffff;
    resize: vertical;
    transition: all 0.15s ease;
}

.pa-cabut-textarea:focus {
    border-color: #ef4444;
    outline: none;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15);
}

/* Modal Konfirmasi Kunci & Jadwalkan */
.pa-konfirmasi-kunci-modal {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0.25rem 0;
}

.pa-konfirmasi-kunci-hero {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: rgba(148, 163, 184, 0.05);
    border: 1px solid rgba(148, 163, 184, 0.12);
    border-radius: 0.75rem;
}

.pa-konfirmasi-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 0.625rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(245, 158, 11, 0.12);
    color: var(--hcis-warning, #f59e0b);
    border: 1px solid rgba(245, 158, 11, 0.25);
    flex-shrink: 0;
}

.pa-konfirmasi-icon-wrap.is-locked {
    background: rgba(2, 132, 199, 0.12);
    color: var(--hcis-brand, #0284c7);
    border-color: rgba(2, 132, 199, 0.25);
}

.pa-konfirmasi-hero-text h4 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    font-weight: 700;
    color: var(--hcis-text-primary, #0f172a);
}

.pa-konfirmasi-hero-text p {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--hcis-text-secondary, #64748b);
    line-height: 1.45;
}

.pa-konfirmasi-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

@media (min-width: 640px) {
    .pa-konfirmasi-stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.pa-konfirmasi-stat-card {
    display: flex;
    flex-direction: column;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(148, 163, 184, 0.14);
    border-radius: 0.625rem;
    text-align: center;
    gap: 0.25rem;
}

.pa-stat-title {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-text-secondary, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-stat-num {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--hcis-text-primary, #0f172a);
    font-variant-numeric: tabular-nums;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-konfirmasi-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0.875rem;
    background: rgba(2, 132, 199, 0.06);
    border: 1px solid rgba(2, 132, 199, 0.15);
    border-radius: 0.5rem;
    font-size: 0.775rem;
    color: var(--hcis-text-secondary, #64748b);
    line-height: 1.4;
}

/* Judul alur bernomor (1 → 4): penanda urutan baca */
.pa-alur-bagian {
    margin-bottom: 1.25rem;
}

.pa-alur-judul {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin: 1.75rem 0 0.85rem;
    font-size: 1.05rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.01em;
}

.pa-perubahan > .pa-alur-judul,
.pa-pen-content > .pa-alur-judul:first-child {
    margin-top: 0;
}

.pa-generate-latar-notice {
    margin: 0.5rem 0 0;
    color: #64748b;
    font-size: 0.8125rem;
    text-align: center;
}

.pa-rincian-toggle {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 0.75rem;
    width: 100%;
    padding: 0.85rem 1.15rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    color: #0f172a;
    font-size: 0.9rem;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.pa-rincian-toggle:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.pa-rincian-toggle[aria-expanded="true"] {
    border-color: #c7d2fe;
    background: #f8faff;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.05);
}

.pa-rincian-toggle small {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
}

.pa-rincian-isi {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 0.5rem;
}

.pa-kunci-ringkas {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin: 0;
    color: #334155;
    font-size: 0.875rem;
    line-height: 1.55;
}

.pa-kunci-ringkas span strong {
    color: #1e1b4b;
    font-weight: 750;
}

.pa-revisi-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem 1.25rem;
    padding: 1rem 1.25rem;
    border: 1px solid #c7d2fe;
    border-left: 5px solid #4f46e5;
    border-radius: 12px;
    background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%);
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.05);
}

.pa-revisi-bar__info {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    flex: 1 1 20rem;
    min-width: 0;
}

.pa-revisi-bar__icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #e0e7ff;
    color: #4338ca;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-revisi-bar__text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-revisi-bar__text strong {
    font-size: 0.92rem;
    color: #1e1b4b;
    font-weight: 750;
}

.pa-revisi-bar__text span {
    font-size: 0.8125rem;
    color: #475569;
    line-height: 1.4;
}

.pa-alur-tunggu {
    margin: 0;
    padding: 0.875rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.8125rem;
}

.pa-alur-no {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 8px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(79, 70, 229, 0.08));
    border: 1px solid rgba(99, 102, 241, 0.25);
    font-size: 0.78rem;
    font-weight: 800;
    color: #4f46e5;
    font-family: var(--hcis-font-mono, monospace);
    box-shadow: 0 1px 2px rgba(79, 70, 229, 0.06);
}

/* Responsive Breakpoints */
@media (max-width: 768px) {
    .pa-cockpit-grid {
        grid-template-columns: 1fr;
    }
    .pa-tab-segmented-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-tab-buttons-group {
        width: 100%;
        display: flex;
    }
    .pa-seg-btn {
        flex: 1;
        justify-content: center;
        padding: 0.45rem 0.6rem;
        font-size: 0.76rem;
    }
    .pa-tab-actions {
        width: 100%;
        justify-content: flex-end;
    }
    .pa-matriks-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-matriks-filter-group {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }
    .pa-filter-col {
        width: 100%;
    }
    .pa-matriks-actions-right {
        width: 100%;
        justify-content: space-between;
    }
    .pa-peserta-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-peserta-search-box {
        max-width: none;
        width: 100%;
    }
    .pa-peserta-filters {
        width: 100%;
        justify-content: space-between;
    }
    .pa-kunci-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .pa-step-footer {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-footer-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }
}

@media (max-width: 640px) {
    .pa-generate-cockpit-card {
        padding: 1.75rem 1rem;
        gap: 1.25rem;
    }

    .pa-generate-stats-row {
        flex-direction: column;
        width: 100%;
    }

    .pa-gen-stat-pill {
        width: 100%;
        justify-content: space-between;
    }

    .pa-generate-complete-strip {
        flex-direction: column;
        align-items: flex-start;
    }

    .pa-hcl-status {
        flex-wrap: wrap;
    }

    .pa-hcl-status__aksi {
        width: 100%;
        justify-content: flex-end;
    }

    .pa-kunci-grid {
        grid-template-columns: 1fr;
    }
}
</style>

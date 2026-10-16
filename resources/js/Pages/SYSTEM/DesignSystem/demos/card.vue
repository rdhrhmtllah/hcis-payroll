<template>
    <div v-if="section === 'anatomy'" class="d-flex flex-column gap-4">
        <!-- Interactive Live Studio Card Configurator -->
        <div class="ds-studio-panel">
            <div class="ds-studio-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="ds-studio-badge">Live Studio</span>
                    <h3 class="ds-studio-title">HcisCard Interactive Configurator</h3>
                </div>
                <!-- Stage Background Switcher -->
                <div class="ds-stage-switcher">
                    <button
                        type="button"
                        class="ds-stage-btn"
                        :class="{ 'is-active': studioStageBg === 'light' }"
                        @click="studioStageBg = 'light'"
                    >
                        Light
                    </button>
                    <button
                        type="button"
                        class="ds-stage-btn"
                        :class="{ 'is-active': studioStageBg === 'dark' }"
                        @click="studioStageBg = 'dark'"
                    >
                        Dark 🌑
                    </button>
                    <button
                        type="button"
                        class="ds-stage-btn"
                        :class="{ 'is-active': studioStageBg === 'mesh' }"
                        @click="studioStageBg = 'mesh'"
                    >
                        Mesh 🔮
                    </button>
                </div>
            </div>

            <div class="ds-studio-body">
                <!-- Preview Stage Canvas -->
                <div
                    class="ds-preview-stage"
                    :class="[
                        `ds-stage-bg--${studioStageBg}`,
                        { 'ds-stage-dark-mode': studioStageBg === 'dark' }
                    ]"
                >
                    <div style="width: 100%; max-width: 560px;">
                        <HcisCard
                            :title="studioTitle"
                            :description="studioDescription"
                            :icon="studioIcon"
                            :icon-animation="studioIconAnimation"
                            :badge="studioBadge"
                            :badge-variant="studioBadgeVariant"
                            :badge-radar="studioBadgeRadar"
                            :badge-glow="studioBadgeGlow"
                            :badge-dot="studioBadgeDot"
                            :sentiment="studioSentiment"
                            :variant="studioVariant"
                            :pattern="studioPattern"
                            :size="studioSize"
                            :divider="studioDivider"
                            :interactive="studioInteractive"
                            :loading="studioLoading"
                            @click="handleCardClick"
                        >
                            <template v-if="studioShowActions" #actions>
                                <HcisButton label="Rincian" variant="secondary" size="xs" leading-icon="eye" />
                            </template>

                            <div class="d-flex flex-column gap-3">
                                <p class="ds-body mb-0">
                                    {{ studioBodyText }}
                                </p>

                                <!-- Removable Tag Chips Composition -->
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <HcisBadge label="Divisi HR" variant="primary" size="xs" dot />
                                    <HcisBadge label="Triwulan III" variant="neutral" size="xs" />
                                    <HcisBadge label="Prioritas Tinggi" variant="danger" size="xs" removable />
                                </div>

                                <div v-if="studioShowMetricRow" class="d-flex align-items-center gap-3 pt-2 border-top">
                                    <div class="d-flex flex-column">
                                        <span class="ds-micro-label">Durasi Izin</span>
                                        <span class="ds-micro-value">4 Hari Kerja</span>
                                    </div>
                                    <div class="vr my-1 opacity-20" />
                                    <div class="d-flex flex-column">
                                        <span class="ds-micro-label">Sisa Saldo</span>
                                        <span class="ds-micro-value text-emerald-600">8 Hari Tersedia</span>
                                    </div>
                                </div>
                            </div>

                            <template v-if="studioShowFooter" #footer>
                                <div class="d-flex align-items-center justify-content-between w-100 gap-2">
                                    <span class="text-xs text-muted">ID Dok: <code>REQ-2026-0819</code></span>
                                    <div class="d-flex align-items-center gap-1">
                                        <HcisButton label="Tolak" variant="ghost" size="xs" />
                                        <HcisButton label="Setujui Pengajuan" variant="primary" size="xs" leading-icon="check2-circle" />
                                    </div>
                                </div>
                            </template>
                        </HcisCard>
                    </div>
                </div>

                <!-- Studio Controls Grid -->
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Pola Latar (Pattern)</label>
                        <select v-model="studioPattern" class="form-select form-select-sm">
                            <option value="none">Polos (Tanpa Pola)</option>
                            <option value="dots">Titik-Titik (Dots Matrix)</option>
                            <option value="grid">Garis Grid (Blueprint Grid)</option>
                            <option value="crosshair">Crosshair Plus (+ Matrix)</option>
                            <option value="stripes">Arsir Diagonal (Stripes /)</option>
                            <option value="rings">Orbit Rings (Radar ◯)</option>
                            <option value="hex">Honeycomb (Hexagon ⬡)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Sentimen Warna</label>
                        <select v-model="studioSentiment" class="form-select form-select-sm">
                            <option value="brand">Primary / Brand (Sapphire)</option>
                            <option value="purple">Purple / Violet (Amethyst)</option>
                            <option value="teal">Teal / Cyan (Mint)</option>
                            <option value="success">Success / Good (Emerald)</option>
                            <option value="warning">Warning (Amber)</option>
                            <option value="danger">Danger / Bad (Crimson)</option>
                            <option value="info">Info (Sky)</option>
                            <option value="neutral">Neutral (Slate)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Varian Permukaan</label>
                        <select v-model="studioVariant" class="form-select form-select-sm">
                            <option value="glass">Glass Frosted (Signature)</option>
                            <option value="default">Default Clean</option>
                            <option value="raised">Raised Elevated</option>
                            <option value="flat">Flat Muted</option>
                            <option value="outlined">Outlined Border</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Animasi Ikon (Icon Animation)</label>
                        <select v-model="studioIconAnimation" class="form-select form-select-sm">
                            <option value="">Tanpa Animasi</option>
                            <option value="float">Float (Mengambang Halus)</option>
                            <option value="spin">Spin (Berputar Kontinu)</option>
                            <option value="gear">Gear (Gigi Berputar)</option>
                            <option value="pulse">Pulse (Denyut Jantung)</option>
                            <option value="ring">Ring (Lonceng Bergetar)</option>
                            <option value="sparkle">Sparkle (Kilauan Bintang)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="ds-control-label">Judul Kartu (Title)</label>
                        <input v-model="studioTitle" type="text" class="form-control form-control-sm" />
                    </div>

                    <div class="col-12 col-md-5">
                        <label class="ds-control-label">Deskripsi (Description)</label>
                        <input v-model="studioDescription" type="text" class="form-control form-control-sm" />
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Ikon Wadah Permata</label>
                        <select v-model="studioIcon" class="form-select form-select-sm">
                            <option value="stars">stars (Signature)</option>
                            <option value="calendar2-check">calendar2-check (Cuti)</option>
                            <option value="patch-question">patch-question (Kuis)</option>
                            <option value="journal-bookmark">journal-bookmark (Dokumen)</option>
                            <option value="shield-lock">shield-lock (Keamanan)</option>
                            <option value="diagram-3-fill">diagram-3-fill (Alur)</option>
                            <option value="award-fill">award-fill (Sertifikasi)</option>
                            <option value="gear-wide-connected">gear-wide-connected (Proses)</option>
                            <option value="bell-fill">bell-fill (Notifikasi)</option>
                            <option value="">Tanpa Ikon</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Teks Lencana (Badge)</label>
                        <input v-model="studioBadge" type="text" class="form-control form-control-sm" />
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="ds-control-label">Varian Lencana</label>
                        <select v-model="studioBadgeVariant" class="form-select form-select-sm">
                            <option value="">Otomatis Sesuai Sentimen</option>
                            <option value="primary">Primary</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="danger">Danger</option>
                            <option value="purple">Purple</option>
                            <option value="teal">Teal</option>
                            <option value="neutral">Neutral</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 d-flex align-items-center flex-wrap gap-3 pt-3">
                        <div class="form-check form-switch mb-0">
                            <input id="swBadgeRadar" v-model="studioBadgeRadar" type="checkbox" class="form-check-input" />
                            <label for="swBadgeRadar" class="form-check-label text-xs font-semibold">Radar Pulse Badge</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input id="swBadgeGlow" v-model="studioBadgeGlow" type="checkbox" class="form-check-input" />
                            <label for="swBadgeGlow" class="form-check-label text-xs font-semibold">Glow Bloom Badge</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input id="swBadgeDot" v-model="studioBadgeDot" type="checkbox" class="form-check-input" />
                            <label for="swBadgeDot" class="form-check-label text-xs font-semibold">Dot Bead Badge</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input id="swDivider" v-model="studioDivider" type="checkbox" class="form-check-input" />
                            <label for="swDivider" class="form-check-label text-xs font-semibold">Divider</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input id="swInteractive" v-model="studioInteractive" type="checkbox" class="form-check-input" />
                            <label for="swInteractive" class="form-check-label text-xs font-semibold">Hover Lift</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input id="swLoading" v-model="studioLoading" type="checkbox" class="form-check-input" />
                            <label for="swLoading" class="form-check-label text-xs font-semibold">Loading</label>
                        </div>
                    </div>
                </div>

                <!-- Reactive Code Generator -->
                <div class="ds-code-generator">
                    <div class="ds-code-header">
                        <span class="ds-code-lang">Vue 3 SFC</span>
                        <button type="button" class="ds-copy-btn" @click="copyCode">
                            <HcisIcon :name="copied ? 'check2' : 'clipboard'" size="xs" />
                            <span>{{ copied ? 'Tersalin!' : 'Salin Kode' }}</span>
                        </button>
                    </div>
                    <pre class="ds-code-block"><code>{{ generatedCode }}</code></pre>
                </div>
            </div>
        </div>
    </div>

    <div v-else-if="section === 'variants'" class="ds-card-demo-wrap">
        <!-- 6 Signature Background Patterns Showcase with Rich Components -->
        <div class="ds-demo-group">
            <span class="ds-group-label">1. Koleksi 6 Pola Latar Belakang & Komposisi Animasi Komponen Signature:</span>
            <div class="hcis-grid hcis-grid--2">
                <!-- 1. Dots Matrix -->
                <HcisCard
                    title="Profil & SOP Pegawai (Dots Matrix)"
                    description="Pola titik-titik mikro dengan animasi ikon mengambang & lencana radar"
                    sentiment="brand"
                    pattern="dots"
                    icon="person-badge"
                    icon-animation="float"
                    badge="Terverifikasi"
                    badge-radar
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Menampilkan rincian kompetensi dan kepatuhan pegawai terhadap SOP internal.</p>
                        <div class="d-flex align-items-center flex-wrap gap-1 pt-1">
                            <HcisBadge label="Full Time" variant="primary" size="xs" dot />
                            <HcisBadge label="HQ Jakarta" variant="neutral" size="xs" />
                            <HcisBadge label="Grade 4A" variant="purple" size="xs" />
                        </div>
                    </div>
                    <template #footer>
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <span class="text-xs text-muted">ID: <code>EMP-9402</code></span>
                            <HcisButton label="Buka Profil" variant="secondary" size="xs" leading-icon="arrow-up-right" />
                        </div>
                    </template>
                </HcisCard>

                <!-- 2. Blueprint Grid -->
                <HcisCard
                    title="Otorisasi Mutasi Jabatan (Blueprint Grid)"
                    description="Garis grid presisi 24px dengan ikon roda gigi bergerak & lencana glow"
                    sentiment="purple"
                    pattern="grid"
                    icon="gear-wide-connected"
                    icon-animation="gear"
                    badge="Menunggu Persetujuan"
                    badge-glow
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Pengalihan divisi dari Finance Accounting ke Strategic Planning Operation.</p>
                        <div class="d-flex align-items-center gap-3 pt-1">
                            <div class="d-flex flex-column">
                                <span class="ds-micro-label">Tanggal Efektif</span>
                                <span class="ds-micro-value">01 Sept 2026</span>
                            </div>
                            <div class="vr my-1 opacity-20" />
                            <div class="d-flex flex-column">
                                <span class="ds-micro-label">Level Approval</span>
                                <span class="ds-micro-value text-indigo-600">Direktur HR</span>
                            </div>
                        </div>
                    </div>
                    <template #footer>
                        <div class="d-flex align-items-center justify-content-end w-100 gap-1.5">
                            <HcisButton label="Tinjau Berkas" variant="ghost" size="xs" />
                            <HcisButton label="Otorisasi" variant="primary" size="xs" leading-icon="check-circle-fill" />
                        </div>
                    </template>
                </HcisCard>

                <!-- 3. Crosshair Plus Matrix -->
                <HcisCard
                    title="Matriks Otorisasi Persetujuan (Crosshair Plus)"
                    description="Tanda silang mikro (+) dengan denyut ikon alur & lencana dot"
                    sentiment="teal"
                    pattern="crosshair"
                    icon="diagram-3-fill"
                    icon-animation="pulse"
                    badge="Alur 3 Tingkat"
                    badge-dot
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Hierarki persetujuan bertingkat untuk modul pengajuan cuti dan reimbursement.</p>
                        <div class="d-flex align-items-center flex-wrap gap-1">
                            <HcisBadge label="Supervisor" variant="teal" size="xs" />
                            <HcisIcon name="arrow-right-short" size="xs" class="text-muted" />
                            <HcisBadge label="Manager" variant="teal" size="xs" />
                            <HcisIcon name="arrow-right-short" size="xs" class="text-muted" />
                            <HcisBadge label="HR Head" variant="teal" size="xs" />
                        </div>
                    </div>
                    <template #footer>
                        <HcisButton label="Ubah Matriks" variant="secondary" size="xs" block leading-icon="sliders" />
                    </template>
                </HcisCard>

                <!-- 4. Diagonal Engineering Stripes -->
                <HcisCard
                    title="Zona Dokumen Lampiran (Diagonal Stripes)"
                    description="Garis arsir 45° dengan ikon berdering dinamis & lencana peringatan"
                    sentiment="warning"
                    pattern="stripes"
                    icon="bell-fill"
                    icon-animation="ring"
                    badge="3 Berkas Diperlukan"
                    badge-radar
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Unggah salinan surat keterangan dokter dan resep obat yang telah ditandatangani.</p>
                        <div class="p-2 rounded-2 bg-amber-50 border border-amber-200 text-xs text-amber-900 d-flex align-items-center gap-2">
                            <HcisIcon name="info-circle-fill" size="xs" class="text-amber-600" />
                            <span>Maksimal 10 MB per berkas (format PDF/JPG).</span>
                        </div>
                    </div>
                    <template #footer>
                        <HcisButton label="Unggah Lampiran" variant="primary" size="xs" block leading-icon="cloud-arrow-up-fill" />
                    </template>
                </HcisCard>

                <!-- 5. Concentric Orbit Rings -->
                <HcisCard
                    title="Piagam Sertifikasi Kompetensi (Orbit Rings)"
                    description="Cincin radar 3D dengan ikon berkilau & lencana berona zamrud"
                    sentiment="success"
                    pattern="rings"
                    icon="award-fill"
                    icon-animation="sparkle"
                    badge="Lulus Predikat A"
                    badge-glow
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Sertifikasi Manajemen Talenta Strategis Tingkat Lanjut diterbitkan oleh HCIS Academy.</p>
                        <div class="d-flex align-items-center justify-content-between pt-1">
                            <div class="d-flex flex-column">
                                <span class="ds-micro-label">Skor Kelulusan</span>
                                <span class="ds-micro-value text-emerald-700">98 / 100</span>
                            </div>
                            <div class="d-flex flex-column text-end">
                                <span class="ds-micro-label">Validitas</span>
                                <span class="ds-micro-value">Agustus 2029</span>
                            </div>
                        </div>
                    </div>
                    <template #footer>
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <HcisButton label="Unduh PDF" variant="ghost" size="xs" leading-icon="download" />
                            <HcisButton label="Lihat Sertifikat" variant="secondary" size="xs" leading-icon="box-arrow-up-right" />
                        </div>
                    </template>
                </HcisCard>

                <!-- 6. Isometric Honeycomb Hex -->
                <HcisCard
                    title="Log Audit Keamanan Sistem (Honeycomb Hex)"
                    description="Sarang lebah heksagonal 3D dengan ikon berputar & lencana darurat"
                    sentiment="danger"
                    pattern="hex"
                    icon="shield-lock-fill"
                    icon-animation="spin"
                    badge="Audit Real-Time"
                    badge-radar
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Pemantauan otomatis anomali login ganda dan percobaan akses unauthorized.</p>
                        <div class="p-2 rounded-2 bg-rose-50 border border-rose-200 text-xs text-rose-900 d-flex align-items-center justify-content-between">
                            <span class="font-medium text-slate-700">Status Gateway</span>
                            <span class="font-bold text-emerald-600 d-flex align-items-center gap-1.5">
                                <span class="hcis-radar-dot" style="width: 6px; height: 6px; background: #10b981;" />
                                Aman (0 Ancaman)
                            </span>
                        </div>
                    </div>
                    <template #footer>
                        <HcisButton label="Lihat Riwayat Log" variant="secondary" size="xs" block leading-icon="list-check" />
                    </template>
                </HcisCard>
            </div>
        </div>

        <!-- Group 2: Glass Frosted Surface with Urgency Sentiments -->
        <div class="ds-demo-group" style="margin-top: 2rem;">
            <span class="ds-group-label">2. Varian Sentimen Warna Kristal (Glass Frosted Sentiments):</span>
            <div class="hcis-grid hcis-grid--2">
                <!-- Brand (Default) -->
                <HcisCard
                    title="Modul Manajemen Talenta (Brand)"
                    description="Pendar nila signature HCIS untuk modul utama & ringkasan umum."
                    sentiment="brand"
                    pattern="dots"
                    icon="stars"
                    icon-animation="sparkle"
                    badge="Signature Brand"
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Pusat integrasi pemetaan suksesi, matrix 9-box talenta, dan ringkasan eksekutif.</p>
                        <div class="d-flex align-items-center gap-2 pt-1">
                            <HcisBadge label="482 Karyawan Terpetakan" variant="primary" size="xs" dot />
                            <HcisBadge label="Update Harian" variant="neutral" size="xs" />
                        </div>
                    </div>
                    <template #footer>
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <span class="text-xs text-muted">Akurasi AI: <strong>99.4%</strong></span>
                            <HcisButton label="Akses Modul" variant="primary" size="xs" leading-icon="box-arrow-in-right" />
                        </div>
                    </template>
                </HcisCard>

                <!-- Purple / Violet -->
                <HcisCard
                    title="Kuis Evaluasi Pemahaman (Purple / Violet)"
                    description="Sesi uji pemahaman SOP operasional dan kepatuhan karyawan."
                    sentiment="purple"
                    pattern="grid"
                    icon="patch-question"
                    icon-animation="pulse"
                    badge="Kuis SOP"
                    badge-dot
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Evaluasi pemahaman regulasi K3 dan standar operasional penanganan berkas.</p>
                        <div class="d-flex align-items-center justify-content-between pt-1">
                            <div class="d-flex flex-column">
                                <span class="ds-micro-label">Durasi Ujian</span>
                                <span class="ds-micro-value">20 Menit</span>
                            </div>
                            <div class="d-flex flex-column text-end">
                                <span class="ds-micro-label">Passing Grade</span>
                                <span class="ds-micro-value text-indigo-600">80 Poin</span>
                            </div>
                        </div>
                    </div>
                    <template #footer>
                        <HcisButton label="Mulai Kuis" variant="secondary" size="xs" block leading-icon="play-fill" />
                    </template>
                </HcisCard>

                <!-- Teal / Cyan -->
                <HcisCard
                    title="Pustaka Kebijakan & Legalitas (Teal / Cyan)"
                    description="Koleksi dokumen acuan resmi, lampiran kerja, dan regulasi internal."
                    sentiment="teal"
                    pattern="crosshair"
                    icon="journal-bookmark"
                    icon-animation="float"
                    badge="Referensi Kerja"
                    badge-glow
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Arsip pedoman tata kelola perusahaan, pakta integritas, dan dokumen legal ketenagakerjaan.</p>
                        <div class="d-flex align-items-center gap-1 pt-1">
                            <HcisBadge label="PDF" variant="teal" size="xs" />
                            <HcisBadge label="Revisi 2026" variant="neutral" size="xs" />
                        </div>
                    </div>
                    <template #footer>
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <span class="text-xs text-muted">14 Dokumen Aktif</span>
                            <HcisButton label="Buka Pustaka" variant="secondary" size="xs" leading-icon="folder2-open" />
                        </div>
                    </template>
                </HcisCard>

                <!-- Success -->
                <HcisCard
                    title="Klaim Pengobatan Rawat Jalan (Success)"
                    description="Status klaim telah terverifikasi dan disetujui HR Finance."
                    sentiment="success"
                    pattern="rings"
                    icon="check-circle"
                    badge="Disetujui"
                    badge-radar
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Klaim kuitansi klinik Pratama telah lolos verifikasi dan dijadwalkan transfer payroll.</p>
                        <div class="d-flex align-items-center justify-content-between pt-1">
                            <span class="ds-micro-label">Nominal Penggantian</span>
                            <span class="ds-micro-value text-emerald-700">Rp 1.450.000</span>
                        </div>
                    </div>
                    <template #footer>
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <span class="text-xs text-muted">No. Resi: <code>CLM-8821</code></span>
                            <HcisButton label="Rincian Bukti" variant="ghost" size="xs" />
                        </div>
                    </template>
                </HcisCard>

                <!-- Warning -->
                <HcisCard
                    title="Pembaruan Sertifikasi Profesi (Warning)"
                    description="Masa berlaku sertifikasi akan habis dalam 14 hari ke depan."
                    sentiment="warning"
                    pattern="stripes"
                    icon="exclamation-triangle"
                    badge="Mendekati Tenggat"
                    badge-dot
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Segera daftarkan perpanjangan lisensi profesi BNSP Anda sebelum masa aktif berakhir.</p>
                        <div class="p-2 rounded-2 bg-amber-50 border border-amber-200 text-xs text-amber-900 d-flex align-items-center gap-2">
                            <HcisIcon name="clock-history" size="xs" class="text-amber-700" />
                            <span>Tenggat batas akhir: <strong>02 September 2026</strong></span>
                        </div>
                    </div>
                    <template #footer>
                        <HcisButton label="Perpanjang Lisensi" variant="primary" size="xs" block leading-icon="arrow-repeat" />
                    </template>
                </HcisCard>

                <!-- Danger -->
                <HcisCard
                    title="Presensi Tidak Valid / Anomali (Danger)"
                    description="Ditemukan selisih data sidik jari dan log gerbang masuk kantor."
                    sentiment="danger"
                    pattern="hex"
                    icon="shield-x"
                    badge="Perlu Tindakan"
                    badge-radar
                    divider
                    interactive
                >
                    <div class="d-flex flex-column gap-2">
                        <p class="ds-body mb-0">Log mesin presensi menunjukkan jam masuk tidak terdeteksi pada shift pagi.</p>
                        <div class="p-2 rounded-2 bg-rose-50 border border-rose-200 text-xs text-rose-900 d-flex align-items-center justify-content-between">
                            <span class="font-medium text-slate-700">Status Koreksi</span>
                            <span class="font-bold text-rose-700">Belum Dikoreksi</span>
                        </div>
                    </div>
                    <template #footer>
                        <div class="d-flex align-items-center justify-content-end w-100 gap-1.5">
                            <HcisButton label="Abaikan" variant="ghost" size="xs" />
                            <HcisButton label="Koreksi Presensi" variant="secondary" size="xs" leading-icon="pencil-square" />
                        </div>
                    </template>
                </HcisCard>
            </div>
        </div>

        <!-- Group 3: Surface & Structural Variants -->
        <div class="ds-demo-group" style="margin-top: 2rem;">
            <span class="ds-group-label">3. Varian Struktur Permukaan Lainnya:</span>
            <div class="hcis-grid hcis-grid--2">
                <HcisCard
                    title="Varian Raised (Melayang)"
                    description="Elevasi multi-layer dengan bayangan lembut dan tegas."
                    variant="raised"
                    icon="layers"
                    badge="Elevasi"
                    badge-variant="brand"
                    divider
                    interactive
                >
                    <p class="ds-body">Permukaan elevasi tegas multi-layer untuk widget penting yang butuh sorotan visual tinggi.</p>
                    <template #footer>
                        <HcisButton label="Telusuri Fitur" variant="secondary" size="xs" block />
                    </template>
                </HcisCard>

                <HcisCard
                    title="Varian Default (Bersih Minimal)"
                    description="Permukaan putih bersih dengan border halus terukur."
                    variant="default"
                    icon="shield-check"
                    badge="Standar"
                    badge-variant="brand"
                    divider
                    interactive
                >
                    <p class="ds-body">Desain visual bersih dengan sudut halus dan bayangan mikro terukur untuk formulir umum.</p>
                    <template #footer>
                        <HcisButton label="Rincian" variant="ghost" size="xs" block />
                    </template>
                </HcisCard>

                <HcisCard
                    title="Varian Flat (Rata)"
                    description="Tanpa bayangan, latar velvet netral."
                    variant="flat"
                    icon="app"
                >
                    <p class="ds-body">Cocok untuk grup formulir bertingkat atau pengelompokan data sekunder di dalam panel.</p>
                </HcisCard>

                <HcisCard
                    title="Varian Outlined (Bingkai)"
                    description="Border tegas dengan latar putih bersih tanpa bayangan."
                    variant="outlined"
                    icon="square"
                >
                    <p class="ds-body">Cocok untuk kartu item dalam daftar berulang, feed aktivitas, atau tabel grid ringkas.</p>
                </HcisCard>
            </div>
        </div>
    </div>

    <div v-else-if="section === 'states'" class="hcis-grid hcis-grid--2">
        <HcisCard title="Memuat (Loading State)" description="Status memuat data asynchronous otomatis" loading icon="arrow-repeat" icon-animation="spin">
            <p class="ds-body">Isi kartu akan digantikan skeleton.</p>
        </HcisCard>
        <HcisCard title="Interaktif + Pembatas (Divider)" description="Garis pemisah crystal etched header dan footer" divider interactive icon="cursor" badge="Klik Saya" badge-variant="brand" badge-radar>
            <p class="ds-body">Hover micro-lift -3px dengan pendar border nila yang halus.</p>
            <template #footer>
                <HcisButton label="Pelajari Lebih Lanjut" variant="secondary" size="sm" block leading-icon="arrow-right" />
            </template>
        </HcisCard>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisBadge, HcisButton, HcisCard, HcisIcon } from '@/components/ui';

defineProps({ section: { type: String, required: true } });

// Interactive Live Studio State
const studioStageBg = ref('light');
const studioPattern = ref('dots');
const studioSentiment = ref('brand');
const studioVariant = ref('glass');
const studioSize = ref('md');
const studioTitle = ref('Pengajuan Cuti Tahunan Pegawai');
const studioDescription = ref('Diajukan 12 Agustus 2026 · Menunggu persetujuan atasan langsung');
const studioIcon = ref('calendar2-check');
const studioIconAnimation = ref('float');
const studioBadge = ref('Menunggu Review');
const studioBadgeVariant = ref('');
const studioBadgeRadar = ref(true);
const studioBadgeGlow = ref(false);
const studioBadgeDot = ref(false);
const studioBodyText = ref('Empat hari kerja, 18–21 Agustus 2026. Alasan: Keperluan keluarga di luar kota.');
const studioShowActions = ref(true);
const studioShowMetricRow = ref(true);
const studioShowFooter = ref(true);
const studioDivider = ref(true);
const studioInteractive = ref(true);
const studioLoading = ref(false);
const copied = ref(false);

function handleCardClick() {
    // Interactive card feedback
}

const generatedCode = computed(() => {
    let code = `<HcisCard\n    title="${studioTitle.value}"`;

    if (studioDescription.value) code += `\n    description="${studioDescription.value}"`;
    if (studioIcon.value) code += `\n    icon="${studioIcon.value}"`;
    if (studioIconAnimation.value) code += `\n    icon-animation="${studioIconAnimation.value}"`;
    if (studioSentiment.value !== 'brand') code += `\n    sentiment="${studioSentiment.value}"`;
    if (studioVariant.value !== 'glass') code += `\n    variant="${studioVariant.value}"`;
    if (studioPattern.value !== 'none') code += `\n    pattern="${studioPattern.value}"`;
    if (studioSize.value !== 'md') code += `\n    size="${studioSize.value}"`;
    if (studioBadge.value) {
        code += `\n    badge="${studioBadge.value}"`;
        if (studioBadgeVariant.value) code += `\n    badge-variant="${studioBadgeVariant.value}"`;
        if (studioBadgeRadar.value) code += `\n    badge-radar`;
        if (studioBadgeGlow.value) code += `\n    badge-glow`;
        if (studioBadgeDot.value) code += `\n    badge-dot`;
    }
    if (studioDivider.value) code += `\n    divider`;
    if (studioInteractive.value) code += `\n    interactive`;
    if (studioLoading.value) code += `\n    loading`;

    code += `\n>`;
    code += `\n    <p class="ds-body">${studioBodyText.value}</p>`;
    code += `\n</HcisCard>`;

    return code;
});

function copyCode() {
    navigator.clipboard.writeText(generatedCode.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}
</script>

<style scoped>
.ds-card-demo-wrap { display: flex; flex-direction: column; gap: 1.25rem; }
.ds-demo-group { display: flex; flex-direction: column; gap: 0.75rem; }
.ds-group-label { font-size: 0.8125rem; font-weight: 700; color: var(--hcis-slate-800, var(--hcis-slate-800)); letter-spacing: -0.01em; }
.ds-body { margin: 0; font-size: 0.8125rem; font-weight: 400; line-height: 1.5; color: var(--hcis-slate-600, var(--hcis-slate-600)); }
.ds-micro-label { font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--hcis-slate-400, var(--hcis-slate-400)); line-height: 1.1; }
.ds-micro-value { font-size: 0.875rem; font-weight: 700; color: var(--hcis-slate-800, var(--hcis-slate-800)); font-variant-numeric: tabular-nums; line-height: 1.25; }
code { background: var(--hcis-slate-100, var(--hcis-slate-100)); border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200)); border-radius: 4px; padding: 1px 5px; font-family: ui-monospace, Consolas, monospace; font-size: 0.75rem; color: var(--hcis-slate-700, var(--hcis-slate-700)); font-weight: 600; }

/* Studio Specific Classes */
.ds-studio-panel {
    border-radius: var(--hcis-radius-xl, 16px);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    background: var(--hcis-surface, var(--hcis-white));
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}

.ds-studio-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.35rem;
    border-bottom: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    background: linear-gradient(135deg, var(--hcis-surface, var(--hcis-white)) 0%, var(--hcis-surface-soft, var(--hcis-slate-50)) 100%);
    flex-wrap: wrap;
    gap: 0.75rem;
}

.ds-studio-badge {
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-800) 100%);
    color: var(--hcis-white);
    font-size: 0.68rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 6px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.ds-studio-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

.ds-stage-switcher {
    display: flex;
    align-items: center;
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    padding: 3px;
    border-radius: 8px;
    gap: 2px;
}

.ds-stage-btn {
    border: none;
    background: transparent;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.ds-stage-btn.is-active {
    background: var(--hcis-white);
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.ds-studio-body {
    padding: 1.35rem;
    display: flex;
    flex-direction: column;
    gap: 1.35rem;
}

.ds-preview-stage {
    min-height: 280px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1.5rem;
    transition: background 300ms ease;
}

.ds-stage-bg--light {
    background: radial-gradient(circle at center, var(--hcis-slate-50) 0%, var(--hcis-slate-200) 100%);
    border: 1px dashed var(--hcis-slate-300);
}

.ds-stage-bg--dark {
    background: radial-gradient(circle at center, var(--hcis-slate-900) 0%, #020617 100%);
    border: 1px dashed var(--hcis-slate-700);
}

.ds-stage-bg--mesh {
    background:
        radial-gradient(ellipse at top left, rgba(99, 102, 241, 0.22) 0%, transparent 50%),
        radial-gradient(ellipse at bottom right, rgba(236, 72, 153, 0.18) 0%, transparent 50%),
        var(--hcis-slate-50);
    border: 1px dashed var(--hcis-slate-300);
}

.ds-control-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    margin-bottom: 4px;
}

.ds-code-generator {
    background: var(--hcis-slate-900);
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--hcis-slate-800);
}

.ds-code-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    background: var(--hcis-slate-800);
    border-bottom: 1px solid var(--hcis-slate-700);
}

.ds-code-lang {
    font-size: 0.68rem;
    font-weight: 750;
    color: var(--hcis-slate-400);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.ds-copy-btn {
    border: none;
    background: rgba(255, 255, 255, 0.1);
    color: var(--hcis-slate-50);
    font-size: 0.72rem;
    font-weight: 650;
    padding: 3px 10px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition-property: var(--hcis-transition-props);
    transition-duration: 180ms;
    transition-timing-function: ease;
}

.ds-copy-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.ds-code-block {
    margin: 0;
    padding: 1rem 1.25rem;
    color: var(--hcis-sky-400);
    font-family: ui-monospace, 'Cascadia Code', 'Source Code Pro', Menlo, Consolas, monospace;
    font-size: 0.78rem;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
}

@media (max-width: 640px) {
    .ds-preview-stage {
        padding: 1rem 0.5rem;
        min-height: auto;
    }
    .ds-studio-header {
        padding: 0.75rem 1rem;
    }
    .ds-studio-body {
        padding: 1rem;
        gap: 1rem;
    }
}
</style>

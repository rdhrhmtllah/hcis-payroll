<template>
    <el-drawer
        v-model="buka"
        direction="rtl"
        size="min(1080px, 100%)"
        :with-header="false"
        :close-on-click-modal="false"
        class="wz-drawer"
        body-class="wz-drawer-body"
        @closed="reset"
    >
        <div class="sop-scope wz-wrap">
            <!-- Kepala Header Console -->
            <div class="wz-head">
                <div class="d-flex align-items-center gap-3">
                    <div class="wz-head-ico">
                        <i class="bi bi-calendar-plus-fill"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-0.5">
                            <span class="sop-eyebrow">Distribusi Baru</span>
                            <span class="wz-step-badge">Langkah {{ step }} dari 4</span>
                        </div>
                        <h6 class="sop-h mb-0">Jadwalkan Dokumen</h6>
                    </div>
                </div>
                <button class="wz-x" title="Tutup (Esc)" @click="buka = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Stepper Progress Bar (Desktop) -->
            <div class="wz-steps-container d-none d-md-block">
                <div class="wz-steps">
                    <div class="wz-steps-progress" :style="{ width: ((step - 1) / 3) * 100 + '%' }"></div>
                    <div
                        v-for="s in steps"
                        :key="s.n"
                        class="wz-step"
                        :class="{ active: step === s.n, done: step > s.n, disabled: !canGoStepDirect(s.n) }"
                        @click="goStep(s.n)"
                    >
                        <div class="wz-dot">
                            <i v-if="step > s.n" class="bi bi-check-lg"></i>
                            <i v-else class="bi" :class="s.icon"></i>
                        </div>
                        <span class="wz-label">{{ s.label }}</span>
                    </div>
                </div>
            </div>

            <!-- Sleek Compact Stepper Bar (Mobile) -->
            <div class="wz-mobile-step-bar d-block d-md-none">
                <div class="d-flex align-items-center justify-content-between mb-1.5">
                    <div class="d-flex align-items-center gap-2">
                        <span class="wz-m-step-ico"><i class="bi" :class="currentStepObj.icon"></i></span>
                        <span class="fw-bold text-dark fs-7">Langkah {{ step }}: {{ currentStepObj.label }}</span>
                    </div>
                    <span class="wz-m-step-count">{{ step }}/4</span>
                </div>
                <div class="wz-m-progress-track">
                    <div class="wz-m-progress-fill" :style="{ width: (step / 4) * 100 + '%' }"></div>
                </div>
            </div>

            <!-- Badan Content -->
            <div class="wz-body">
                <transition name="sop-fade" mode="out-in">
                    <!-- ── 1 · Pilih Paket Dokumen ── -->
                    <div v-if="step === 1" key="step-1" class="sop-fade-up">
                        <div class="mb-3">
                            <HcisField
                                id="wz-cari-paket"
                                v-model="cariPaket"
                                type="search"
                                clearable
                                placeholder="Cari nama paket dokumen atau kata kunci…"
                                size="md"
                            >
                                <template #prefix>
                                    <HcisIcon name="search" size="xs" class="text-muted" />
                                </template>
                            </HcisField>
                        </div>

                        <HcisEmptyState
                            v-if="!paketTersaring.length"
                            title="Tidak Ada Paket yang Cocok"
                            description="Tidak ada paket berstatus Terbit (Published) yang sesuai dengan kata kunci pencarian."
                            icon="journal-x"
                            variant="dashed"
                            sentiment="neutral"
                            class="my-4"
                        >
                            <template #actions>
                                <HcisButton
                                    v-if="cariPaket"
                                    variant="secondary"
                                    size="sm"
                                    leading-icon="arrow-counterclockwise"
                                    label="Reset Pencarian"
                                    @click="cariPaket = ''"
                                />
                            </template>
                        </HcisEmptyState>
                        <div v-else class="wz-paket-grid">
                            <!-- Status draft tidak dimuat pada daftar awal agar paket tampil cepat. -->
                            <button
                                v-for="p in paketTampil"
                                :key="p.Id_SOP_Paket"
                                type="button"
                                class="wz-paket"
                                :class="{ on: form.id_paket === p.Id_SOP_Paket }"
                                @click="pilihPaket(p)"
                            >
                                <span
                                    class="wz-paket-ico"
                                    :style="{
                                        background: theme(p.Nama_Paket).color + '1a',
                                        color: theme(p.Nama_Paket).color,
                                    }"
                                >
                                    <i class="bi" :class="theme(p.Nama_Paket).icon"></i>
                                </span>
                                <span class="wz-paket-info">
                                    <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                        <span class="wz-paket-nama text-truncate">{{ p.Nama_Paket }}</span>
                                        <span class="wz-version-tag">v{{ p.Versi_Terakhir || 1 }}.0</span>
                                    </div>
                                    <div v-if="p.Kode_Paket" class="wz-kode-sub font-mono mb-1 text-muted" style="font-size: 0.72rem;">
                                        {{ p.Kode_Paket }}
                                    </div>
                                    <span class="wz-paket-meta">
                                        <span class="wz-dist" :class="modeDistribusi(p).toLowerCase()">
                                            <i
                                                class="bi"
                                                :class="modeDistribusi(p) === 'PUBLIK' ? 'bi-globe2' : 'bi-lock-fill'"
                                            ></i>
                                            {{ modeDistribusi(p) }}
                                        </span>
                                        <span
                                            v-if="p.Prioritas"
                                            class="wz-meta-prio"
                                            :class="p.Prioritas.toLowerCase()"
                                        >
                                            {{ p.Prioritas }}
                                        </span>
                                        <span v-if="p.Total_Dokumen || p.Total_Kuis" class="wz-comp-tag">
                                            <i class="bi bi-file-earmark-text me-0.5"></i>
                                            {{ p.Total_Dokumen || 0 }} Dok<template v-if="p.Total_Kuis"> · {{ p.Total_Kuis }} Kuis</template>
                                        </span>
                                        <span v-if="p.Estimasi" class="wz-time-tag">
                                            <i class="bi bi-clock me-0.5"></i>~{{ p.Estimasi }}m
                                        </span>
                                    </span>
                                </span>
                                <span class="wz-select-check">
                                    <i
                                        class="bi"
                                        :class="
                                            form.id_paket === p.Id_SOP_Paket
                                                ? 'bi-check-circle-fill'
                                                : 'bi-chevron-right'
                                        "
                                    ></i>
                                </span>
                            </button>
                        </div>

                        <!--
                        Kartu dibatasi supaya DOM tidak meledak saat master paket
                        besar. Datanya tetap utuh di memori, jadi pencarian di atas
                        tetap menjangkau paket yang belum dirender.
                        -->
                        <div v-if="paketTersaring.length > batasRenderPaket" class="wz-render-note">
                            <i class="bi bi-info-circle-fill"></i>
                            <span>
                                Menampilkan <strong>{{ paketTampil.length }}</strong> dari
                                <strong>{{ paketTersaring.length }}</strong> paket. Persempit lewat pencarian di atas
                                untuk menemukan paket lainnya.
                            </span>
                        </div>

                        <div v-if="paketTerpotong" class="wz-server-note">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>
                                Daftar paket dipotong di server karena melewati batas aman. Sebagian paket tidak ikut
                                termuat — hubungi pengelola dokumen bila paket yang dicari tidak ditemukan.
                            </span>
                        </div>
                    </div>

                    <!-- ── 2 · Pilih Target Karyawan ── -->
                    <div v-else-if="step === 2" key="step-2" class="sop-fade-up">
                        <!--
                        Peringatan draft tidak memakai data daftar awal. Detail paket terpilih
                        diperiksa lewat preflight sebelum penjadwalan dikirim.
                        -->

                        <!-- Alert: Paket Dokumen Publik -->
                        <div v-if="paketPublik" class="wz-alert-banner info mb-3">
                            <div class="wz-ab-ico blue">
                                <i class="bi bi-globe2"></i>
                            </div>
                            <div class="wz-ab-content flex-grow-1">
                                <h6 class="wz-ab-title text-sky-900 mb-1">Paket Dokumen ini bersifat Publik</h6>
                                <p class="wz-ab-desc text-sky-800 mb-0">
                                    Seluruh karyawan pada dasarnya sudah dapat mengakses dokumen ini. Penjadwalan ini menetapkan status kewajiban tugas (mandatory) untuk karyawan yang dipilih.
                                </p>
                            </div>
                        </div>

                        <!-- Mode tabs selector -->
                        <div class="wz-modes mb-3">
                            <button
                                v-for="m in modes"
                                :key="m.k"
                                type="button"
                                class="wz-mode"
                                :class="{ on: mode === m.k }"
                                @click="gantiMode(m.k)"
                            >
                                <div class="d-flex align-items-center justify-content-between w-100 mb-1">
                                    <i class="bi" :class="m.icon"></i>
                                    <HcisBadge
                                        v-if="m.k === 'orang' && form.kodes.length > 0"
                                        variant="brand"
                                        size="xs"
                                    >
                                        {{ form.kodes.length }} orang
                                    </HcisBadge>
                                    <HcisBadge
                                        v-else-if="m.k === 'unit' && ringkasUnit.total > 0"
                                        variant="brand"
                                        size="xs"
                                    >
                                        {{ ringkasUnit.total }} orang
                                    </HcisBadge>
                                </div>
                                <span class="wz-mode-t">{{ m.label }}</span>
                                <span class="wz-mode-d">{{ m.desc }}</span>
                            </button>
                        </div>

                        <TargetPilihOrang
                            v-if="mode === 'orang'"
                            v-model="form.kodes"
                            :id-paket="form.id_paket"
                            :opsi="opsiFilter"
                        />
                        <TargetPilihUnit
                            v-else
                            v-model="form.unit"
                            :id-paket="form.id_paket"
                            :opsi="opsiFilter"
                            @ringkas="ringkasUnit = $event"
                        />
                    </div>

                    <!-- ── 3 · Pengaturan Jadwal & Tenggat ── -->
                    <div v-else-if="step === 3" key="step-3" class="sop-fade-up">
                        <div class="wz-jadwal d-flex flex-column gap-3">
                            <!-- Card 1: Identitas Batch & Keterangan -->
                            <div class="wz-card-box">
                                <div class="wz-card-header mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="wz-card-ico bg-primary-soft text-primary"
                                            ><i class="bi bi-tag-fill"></i
                                        ></span>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0 fs-7">Identitas Penjadwalan / Batch</h6>
                                            <small class="text-muted"
                                                >Beri nama kelompok tugas untuk mempermudah pemantauan</small
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="sop-label mb-1"
                                        >Label Penjadwalan / Batch <span class="text-danger">*</span></label
                                    >
                                    <input
                                        v-model="form.nama_batch"
                                        type="text"
                                        class="sop-field"
                                        maxlength="150"
                                        :placeholder="saranLabel"
                                    />
                                    <div class="wz-saran-wrap mt-2">
                                        <span class="wz-saran-label"
                                            ><i class="bi bi-magic text-warning me-1"></i> Saran Cepat:</span
                                        >
                                        <div class="wz-saran">
                                            <button
                                                v-for="s in saranCepat"
                                                :key="s"
                                                type="button"
                                                class="wz-chip-btn"
                                                :class="{ active: form.nama_batch === s }"
                                                @click="form.nama_batch = s"
                                            >
                                                <i class="bi bi-tag me-1"></i>{{ s }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="sop-label mb-1"
                                        >Keterangan / Catatan Tambahan <span class="wz-opt">(opsional)</span></label
                                    >
                                    <textarea
                                        v-model="form.keterangan"
                                        class="sop-field"
                                        rows="2"
                                        maxlength="500"
                                        placeholder="Alasan / instruksi spesifik mengapa distribusi ini ditugaskan..."
                                    ></textarea>
                                </div>
                            </div>

                            <!-- Card 2: Batas Waktu Penyelesaian (Tenggat) -->
                            <div class="wz-card-box">
                                <div class="wz-card-header mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="wz-card-ico bg-amber-soft text-amber"
                                            ><i class="bi bi-calendar-range-fill"></i
                                        ></span>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0 fs-7">
                                                Ketentuan Tenggat Waktu (Deadline)
                                            </h6>
                                            <small class="text-muted"
                                                >Tentukan batas akhir penyelesaian tugas bagi karyawan</small
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="wz-tenggat-cards grid-2 gap-2 mb-3">
                                    <button
                                        type="button"
                                        class="wz-tenggat-card"
                                        :class="{ active: form.tipe_tenggat === 'SAMPAI_DICABUT' }"
                                        @click="form.tipe_tenggat = 'SAMPAI_DICABUT'"
                                    >
                                        <div class="wtc-ico"><i class="bi bi-infinity"></i></div>
                                        <div class="wtc-info">
                                            <strong>Tanpa Tenggat</strong>
                                            <small>Tugas berlaku seterusnya sampai dikonfigurasi ulang.</small>
                                        </div>
                                        <i
                                            class="bi wtc-check"
                                            :class="
                                                form.tipe_tenggat === 'SAMPAI_DICABUT'
                                                    ? 'bi-check-circle-fill text-primary'
                                                    : 'bi-circle text-muted'
                                            "
                                        ></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="wz-tenggat-card"
                                        :class="{ active: form.tipe_tenggat === 'DEADLINE' }"
                                        @click="form.tipe_tenggat = 'DEADLINE'"
                                    >
                                        <div class="wtc-ico amber"><i class="bi bi-calendar-event-fill"></i></div>
                                        <div class="wtc-info">
                                            <strong>Tentukan Deadline</strong>
                                            <small>Wajib diselesaikan sebelum tanggal yang ditentukan.</small>
                                        </div>
                                        <i
                                            class="bi wtc-check"
                                            :class="
                                                form.tipe_tenggat === 'DEADLINE'
                                                    ? 'bi-check-circle-fill text-amber'
                                                    : 'bi-circle text-muted'
                                            "
                                        ></i>
                                    </button>
                                </div>

                                <div v-if="form.tipe_tenggat === 'DEADLINE'" class="wz-deadline-box p-3 rounded-3 mb-2">
                                    <label class="sop-label mb-2">Pilih Tanggal Deadline</label>
                                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                        <input
                                            v-model="form.tanggal_deadline"
                                            type="date"
                                            class="sop-field fw-bold"
                                            :min="hariIni"
                                            style="max-width: 220px"
                                        />
                                        <span class="text-muted small fw-semibold">Preset Cepat:</span>
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            <button
                                                type="button"
                                                class="preset-chip"
                                                :class="{ active: form.tanggal_deadline === getDeadlineDateString(3) }"
                                                @click="setDeadlineDays(3)"
                                            >
                                                +3 Hari
                                            </button>
                                            <button
                                                type="button"
                                                class="preset-chip"
                                                :class="{ active: form.tanggal_deadline === getDeadlineDateString(7) }"
                                                @click="setDeadlineDays(7)"
                                            >
                                                +7 Hari
                                            </button>
                                            <button
                                                type="button"
                                                class="preset-chip"
                                                :class="{ active: form.tanggal_deadline === getDeadlineDateString(14) }"
                                                @click="setDeadlineDays(14)"
                                            >
                                                +14 Hari
                                            </button>
                                            <button
                                                type="button"
                                                class="preset-chip"
                                                :class="{ active: form.tanggal_deadline === getDeadlineDateString(30) }"
                                                @click="setDeadlineDays(30)"
                                            >
                                                +30 Hari
                                            </button>
                                            <button type="button" class="preset-chip" @click="setDeadlineEndOfMonth()">
                                                Akhir Bulan
                                            </button>
                                        </div>
                                    </div>
                                    <p v-if="deadlineLampau" class="wz-warn mb-0">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Tanggal tenggat tidak boleh
                                        berada di masa lalu.
                                    </p>
                                </div>
                                <p v-else class="wz-hint mb-0">
                                    <i class="bi bi-info-circle me-1 text-primary"></i> Karyawan dapat mempelajari dokumen
                                    kapan saja tanpa perhitungan keterlambatan.
                                </p>
                            </div>

                            <!-- Card 3: Pengingat Otomatis (Reminder) -->
                            <div class="wz-card-box">
                                <div class="wz-card-header mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="wz-card-ico bg-purple-soft text-purple"
                                            ><i class="bi bi-bell-fill"></i
                                        ></span>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0 fs-7">Pengingat Otomatis (Reminder)</h6>
                                            <small class="text-muted"
                                                >Notifikasi berkala via sistem untuk karyawan yang belum selesai</small
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div class="wz-reminder-box p-3 rounded-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark">Kirim Pengingat Otomatis</span>
                                            <small class="text-muted fw-normal"
                                                >Kirim notifikasi berkala ke karyawan yang belum menyelesaikan tugas.</small
                                            >
                                        </div>
                                        <HcisSwitch v-model="form.flag_reminder" sentiment="purple" size="md" />
                                    </div>
                                    <div v-if="form.flag_reminder" class="wz-irama mt-3 pt-3 border-top">
                                        <div class="d-flex align-items-center gap-2">
                                            <label class="sop-label mb-0">Frekuensi kirim ulang:</label>
                                            <div class="d-flex align-items-center gap-1">
                                                <input
                                                    v-model.number="form.irama_reminder_hari"
                                                    type="number"
                                                    min="1"
                                                    max="365"
                                                    class="sop-field text-center fw-bold"
                                                    style="width: 80px"
                                                />
                                                <span class="fw-semibold text-secondary">hari sekali</span>
                                            </div>
                                        </div>
                                        <p class="wz-hint mt-2 mb-0">
                                            <i class="bi bi-bell-fill me-1 text-primary"></i> Notifikasi otomatis
                                            diproses setiap hari oleh jadwal cron sistem.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── 4 · Ringkasan & Tinjauan (Review) ── -->
                    <div v-else key="step-4" class="sop-fade-up">
                        <div v-if="memuatPreflight" class="wz-pf-load p-5 text-center">
                            <span
                                class="spinner-border spinner-border-sm text-primary mb-2"
                                role="status"
                                style="width: 2.2rem; height: 2.2rem"
                            ></span>
                            <div class="fw-bold text-dark fs-6 mt-2">Memeriksa Kelayakan & Menghitung Target...</div>
                            <span class="text-muted small">Memverifikasi koneksi HC Learn dan data karyawan</span>
                        </div>
                        <template v-else-if="pf">
                            <!-- Preflight Alert 1: Draft Revisi Belum Dipublikasi -->
                            <div v-if="pf.paket.ada_draft_perubahan" class="wz-alert-banner warn mb-3">
                                <div class="wz-ab-ico amber">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                                <div class="wz-ab-content flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                        <h6 class="wz-ab-title text-amber-900 mb-0">Draft Revisi Belum Dipublikasi</h6>
                                        <HcisBadge variant="warning" size="xs">
                                            Versi Aktif: v{{ paketTerpilih?.Versi_Terakhir || 1 }}
                                        </HcisBadge>
                                    </div>
                                    <p class="wz-ab-desc text-amber-800 mb-0">
                                        Paket ini sedang memiliki draft revisi di Builder. Penjadwalan akan tetap menggunakan versi publik aktif saat ini.
                                    </p>
                                </div>
                            </div>

                            <!-- Preflight Alert 2: Penjadwalan Ditolak / Tidak Tertaut -->
                            <div
                                v-if="pf.identitas_kuis.status === 'DITOLAK' || pf.identitas_kuis.status === 'TIDAK_TERTAUT'"
                                class="wz-alert-banner bad mb-3"
                            >
                                <div class="wz-ab-ico red">
                                    <i class="bi bi-shield-exclamation"></i>
                                </div>
                                <div class="wz-ab-content flex-grow-1">
                                    <h6 class="wz-ab-title text-danger mb-1">Penjadwalan Tidak Dapat Dilanjutkan</h6>
                                    <p class="wz-ab-desc text-secondary mb-0">{{ pf.identitas_kuis.pesan }}</p>
                                </div>
                            </div>

                            <!-- Preflight Alert 3: Koneksi HC Learn Terverifikasi -->
                            <div v-else-if="pf.paket.ada_kuis" class="wz-alert-banner ok mb-3">
                                <div class="wz-ab-ico green">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div class="wz-ab-content flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                        <h6 class="wz-ab-title text-emerald mb-0">Koneksi HC Learn Terverifikasi</h6>
                                        <HcisBadge variant="success" size="xs">Terhubung</HcisBadge>
                                    </div>
                                    <p class="wz-ab-desc text-emerald-800 mb-0">
                                        Paket mengandung item kuis dan integrasi akun HC Learn dalam keadaan aktif.
                                    </p>
                                </div>
                            </div>

                            <div class="wz-review">
                                <!-- Hero Target Banner -->
                                <div class="wz-rv-hero mb-3">
                                    <div class="wz-rv-hero-content">
                                        <span class="wz-rv-badge"
                                            ><i class="bi bi-people-fill me-1"></i> Target Terhitung</span
                                        >
                                        <div class="wz-rv-big tnum">{{ pf.target.baru }}</div>
                                        <div class="wz-rv-lab">Karyawan akan menerima penugasan baru</div>
                                        <div v-if="pf.target.sudah_assign" class="wz-rv-sub mt-2">
                                            <span class="wz-rv-sub-chip">
                                                <i class="bi bi-person-check-fill me-1 text-amber"></i>
                                                {{ pf.target.sudah_assign }} karyawan dilewati (sudah ditugaskan)
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Structured Summary Grid Card -->
                                <div class="wz-card-box mb-3">
                                    <div
                                        class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom"
                                    >
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="wz-card-ico bg-indigo-soft text-indigo"
                                                ><i class="bi bi-clipboard-data-fill"></i
                                            ></span>
                                            <h6 class="fw-bold text-dark mb-0 fs-7">Rincian Ringkasan Penjadwalan</h6>
                                        </div>
                                        <span
                                            class="badge bg-indigo-light text-indigo border border-indigo-subtle rounded-pill px-2.5 py-1 fw-bold fs-8"
                                            >Verified</span
                                        >
                                    </div>

                                    <div class="wz-rv-grid">
                                        <div class="wz-rv-item">
                                            <span class="wz-rv-icon"><i class="bi bi-journal-text"></i></span>
                                            <div class="wz-rv-meta">
                                                <span class="wz-rv-key">Paket Dokumen</span>
                                                <span class="wz-rv-val text-primary fw-bold">{{ pf.paket.nama }}</span>
                                            </div>
                                        </div>

                                        <div class="wz-rv-item">
                                            <span class="wz-rv-icon"><i class="bi bi-globe2"></i></span>
                                            <div class="wz-rv-meta">
                                                <span class="wz-rv-key">Tipe Distribusi</span>
                                                <span class="wz-rv-val">
                                                    <span
                                                        class="wz-dist"
                                                        :class="modeDistribusi(pf.paket.distribusi).toLowerCase()"
                                                    >
                                                        <i
                                                            class="bi"
                                                            :class="
                                                                modeDistribusi(pf.paket.distribusi) === 'PUBLIK'
                                                                    ? 'bi-globe2'
                                                                    : 'bi-lock-fill'
                                                            "
                                                        ></i>
                                                        {{ modeDistribusi(pf.paket.distribusi) }}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="wz-rv-item">
                                            <span class="wz-rv-icon"><i class="bi bi-bookmark-star"></i></span>
                                            <div class="wz-rv-meta">
                                                <span class="wz-rv-key">Prioritas</span>
                                                <span class="wz-rv-val">
                                                    <span
                                                        class="wz-meta-prio"
                                                        :class="
                                                            pf.paket.prioritas ? pf.paket.prioritas.toLowerCase() : ''
                                                        "
                                                        >{{ pf.paket.prioritas }}</span
                                                    >
                                                </span>
                                            </div>
                                        </div>

                                        <div class="wz-rv-item">
                                            <span class="wz-rv-icon"><i class="bi bi-diagram-2"></i></span>
                                            <div class="wz-rv-meta">
                                                <span class="wz-rv-key">Konten & Kuis</span>
                                                <span class="wz-rv-val fw-semibold">
                                                    {{ pf.paket.total_item }} item ({{ pf.paket.total_wajib }} wajib)
                                                    <span
                                                        v-if="pf.paket.ada_kuis"
                                                        class="badge bg-success-light text-success ms-1"
                                                        ><i class="bi bi-patch-check-fill me-0.5"></i> Kuis</span
                                                    >
                                                </span>
                                            </div>
                                        </div>

                                        <div class="wz-rv-item">
                                            <span class="wz-rv-icon"><i class="bi bi-clock-history"></i></span>
                                            <div class="wz-rv-meta">
                                                <span class="wz-rv-key">Estimasi Durasi</span>
                                                <span class="wz-rv-val fw-semibold">{{
                                                    pf.paket.estimasi_menit ? pf.paket.estimasi_menit + ' menit' : '—'
                                                }}</span>
                                            </div>
                                        </div>

                                        <div class="wz-rv-item">
                                            <span class="wz-rv-icon"><i class="bi bi-tag"></i></span>
                                            <div class="wz-rv-meta">
                                                <span class="wz-rv-key">Label Batch</span>
                                                <span class="wz-rv-val fw-bold text-dark">{{
                                                    form.nama_batch || saranLabel
                                                }}</span>
                                            </div>
                                        </div>

                                        <div class="wz-rv-item">
                                            <span class="wz-rv-icon"><i class="bi bi-person-lines-fill"></i></span>
                                            <div class="wz-rv-meta">
                                                <span class="wz-rv-key">Metode Pemilihan</span>
                                                <span class="wz-rv-val fw-semibold">{{ modeLabel }}</span>
                                            </div>
                                        </div>

                                        <div class="wz-rv-item">
                                            <span class="wz-rv-icon"><i class="bi bi-calendar-event"></i></span>
                                            <div class="wz-rv-meta">
                                                <span class="wz-rv-key">Batas Waktu (Deadline)</span>
                                                <span class="wz-rv-val">
                                                    <span
                                                        v-if="form.tipe_tenggat === 'DEADLINE'"
                                                        class="badge bg-amber-light text-amber border border-amber-subtle font-mono fw-bold"
                                                    >
                                                        <i class="bi bi-calendar-check me-1"></i
                                                        >{{ form.tanggal_deadline }}
                                                    </span>
                                                    <span v-else class="badge bg-secondary-light text-secondary"
                                                        >Tanpa Tenggat</span
                                                    >
                                                </span>
                                            </div>
                                        </div>

                                        <div class="wz-rv-item">
                                            <span class="wz-rv-icon"><i class="bi bi-bell"></i></span>
                                            <div class="wz-rv-meta">
                                                <span class="wz-rv-key">Pengingat Berkala</span>
                                                <span class="wz-rv-val">
                                                    <span
                                                        v-if="form.flag_reminder"
                                                        class="badge bg-primary-light text-primary border border-primary-subtle"
                                                    >
                                                        <i class="bi bi-arrow-repeat me-1"></i>Setiap
                                                        {{ form.irama_reminder_hari }} hari
                                                    </span>
                                                    <span v-else class="badge bg-light text-muted">Nonaktif</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div v-if="form.keterangan" class="wz-rv-item wz-rv-item--full">
                                            <span class="wz-rv-icon"><i class="bi bi-chat-left-text"></i></span>
                                            <div class="wz-rv-meta">
                                                <span class="wz-rv-key">Keterangan / Catatan</span>
                                                <span class="wz-rv-val text-dark fw-semibold">"{{ form.keterangan }}"</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-for="(p, i) in pf.tenggat.peringatan" :key="i" class="wz-alert-banner warn mb-2">
                                    <div class="wz-ab-ico amber">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                    </div>
                                    <div class="wz-ab-content flex-grow-1">
                                        <p class="wz-ab-desc text-amber-900 mb-0">{{ p }}</p>
                                    </div>
                                </div>

                                <div v-if="pf.mode_eksekusi.async" class="wz-alert-banner info mb-3">
                                    <div class="wz-ab-ico purple">
                                        <i class="bi bi-lightning-charge-fill"></i>
                                    </div>
                                    <div class="wz-ab-content flex-grow-1">
                                        <h6 class="wz-ab-title text-purple-900 mb-1">Diproses Asinkron di Latar Belakang</h6>
                                        <p class="wz-ab-desc text-purple-800 mb-0">
                                            Penjadwalan mencakup lebih dari {{ pf.mode_eksekusi.ambang }} karyawan.
                                            Tugas akan diproses di background tanpa menghentikan layar Anda.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </transition>
            </div>

            <!-- Kaki Navigation Footer -->
            <div class="wz-foot">
                <HcisButton
                    v-if="step > 1"
                    variant="secondary"
                    size="sm"
                    leading-icon="arrow-left"
                    label="Kembali"
                    :disabled="mengirim"
                    @click="step--"
                />
                <div v-else></div>

                <div class="wz-foot-info">
                    <span v-if="infoKaki" class="badge bg-purple-subtle text-purple border border-purple-subtle px-3 py-1.5 rounded-pill small fw-semibold">
                        {{ infoKaki }}
                    </span>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        v-if="step < 4"
                        variant="primary"
                        sentiment="purple"
                        size="sm"
                        trailing-icon="arrow-right"
                        label="Lanjut"
                        :disabled="!bisaLanjut"
                        @click="lanjut"
                    />
                    <HcisButton
                        v-else
                        variant="primary"
                        sentiment="purple"
                        size="sm"
                        leading-icon="send-fill"
                        :label="mengirim ? 'Menjadwalkan...' : 'Jadwalkan Sekarang'"
                        :loading="mengirim"
                        :disabled="mengirim || !bisaKirim"
                        class="pulse-glow"
                        @click="kirim"
                    />
                </div>
            </div>
        </div>
    </el-drawer>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import TargetPilihOrang from './TargetPilihOrang.vue';
import TargetPilihUnit from './TargetPilihUnit.vue';
import HcisButton from '../../../../../../components/ui/HcisButton.vue';
import HcisBadge from '../../../../../../components/ui/HcisBadge.vue';
import HcisField from '../../../../../../components/ui/HcisField.vue';
import HcisCard from '../../../../../../components/ui/HcisCard.vue';
import HcisContextBanner from '../../../../../../components/ui/HcisContextBanner.vue';
import HcisAlert from '../../../../../../components/ui/HcisAlert.vue';
import HcisEmptyState from '../../../../../../components/ui/HcisEmptyState.vue';
import HcisSwitch from '../../../../../../components/ui/HcisSwitch.vue';
import HcisInputNumber from '../../../../../../components/ui/HcisInputNumber.vue';
import HcisProgress from '../../../../../../components/ui/HcisProgress.vue';
import HcisIcon from '../../../../../../components/ui/HcisIcon.vue';
import { useActivityCenter } from '../../../../../../composables/useActivityCenter.js';
import { temaAksen } from '../../../Shared/support/constants.js';

const API = '/api/v1/sop/admin/penjadwalan';

export default {
    name: 'WizardPenjadwalan',
    components: {
        TargetPilihOrang,
        TargetPilihUnit,
        HcisButton,
        HcisBadge,
        HcisField,
        HcisCard,
        HcisContextBanner,
        HcisAlert,
        HcisEmptyState,
        HcisSwitch,
        HcisInputNumber,
        HcisProgress,
        HcisIcon,
    },
    props: {
        modelValue: { type: Boolean, default: false },
        paketOptions: { type: Array, default: () => [] },
        opsiFilter: { type: Object, default: () => ({}) },
        paketAwal: { type: String, default: '' },
        /** Server memotong daftar paket karena melewati batas aman. */
        paketTerpotong: { type: Boolean, default: false },
    },
    emits: ['update:modelValue', 'selesai'],
    data() {
        return {
            step: 1,
            steps: [
                { n: 1, label: 'Paket', icon: 'bi-journal-text' },
                { n: 2, label: 'Target', icon: 'bi-people' },
                { n: 3, label: 'Jadwal', icon: 'bi-calendar-check' },
                { n: 4, label: 'Review', icon: 'bi-check2-circle' },
            ],
            modes: [
                { k: 'orang', label: 'Pilih Orang', desc: 'Cari & centang satu per satu', icon: 'bi-person-check' },
                { k: 'unit', label: 'Pilih Unit', desc: 'Se-departemen / lokasi sekaligus', icon: 'bi-diagram-3' },
            ],
            mode: 'orang',
            cariPaket: '',
            /** Batas kartu paket yang dirender sekaligus — penjaga DOM, bukan penjaga data. */
            batasRenderPaket: 60,
            form: {
                id_paket: '',
                nama_batch: '',
                keterangan: '',
                kodes: [],
                unit: {},
                tipe_tenggat: 'SAMPAI_DICABUT',
                tanggal_deadline: '',
                flag_reminder: false,
                irama_reminder_hari: 3,
            },
            ringkasUnit: { total: 0, baru: 0, sudah_assign: 0 },
            pf: null,
            memuatPreflight: false,
            mengirim: false,
        };
    },
    computed: {
        currentStepObj() {
            return this.steps.find((s) => s.n === this.step) || this.steps[0];
        },
        buka: {
            get() {
                return this.modelValue;
            },
            set(v) {
                this.$emit('update:modelValue', v);
            },
        },
        hariIni() {
            return new Date().toISOString().slice(0, 10);
        },
        deadlineLampau() {
            return (
                this.form.tipe_tenggat === 'DEADLINE' &&
                this.form.tanggal_deadline &&
                this.form.tanggal_deadline < this.hariIni
            );
        },
        paketTersaring() {
            const q = this.cariPaket.trim().toLowerCase();
            return q
                ? this.paketOptions.filter(
                      (p) =>
                          String(p.Nama_Paket || '').toLowerCase().includes(q) ||
                          String(p.Kode_Paket || '').toLowerCase().includes(q) ||
                          String(p.Deskripsi || '').toLowerCase().includes(q)
                  )
                : this.paketOptions;
        },
        paketTampil() {
            return this.paketTersaring.slice(0, this.batasRenderPaket);
        },
        paketTerpilih() {
            return this.paketOptions.find((p) => p.Id_SOP_Paket === this.form.id_paket) || null;
        },
        paketPublik() {
            return this.paketTerpilih && this.modeDistribusi(this.paketTerpilih) === 'PUBLIK';
        },
        jumlahTarget() {
            return this.mode === 'unit' ? this.ringkasUnit.total : this.form.kodes.length;
        },
        saranLabel() {
            const t = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
            return 'Angkatan ' + t + (this.jumlahTarget ? ' · ' + this.jumlahTarget + ' orang' : '');
        },
        saranCepat() {
            const bulan = new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
            const paket = this.paketOptions.find((p) => p.Id_SOP_Paket === this.form.id_paket);
            const out = ['Distribusi ' + bulan];
            if (paket) out.push(paket.Nama_Paket + ' — ' + bulan);
            const u = this.form.unit || {};
            const unit = [...(u.dept || []), ...(u.divisi || []), ...(u.lokasi || [])];
            if (unit.length) out.push(unit.slice(0, 2).join(' & ') + ' — ' + bulan);
            return out.slice(0, 3);
        },
        modeLabel() {
            const m = this.modes.find((x) => x.k === this.mode);
            return m ? m.label : '—';
        },
        bisaLanjut() {
            if (this.step === 1) return !!this.form.id_paket;
            if (this.step === 2) return this.jumlahTarget > 0;
            if (this.step === 3)
                return this.form.tipe_tenggat !== 'DEADLINE' || (!!this.form.tanggal_deadline && !this.deadlineLampau);
            return true;
        },
        bisaKirim() {
            if (!this.pf) return false;
            if (['DITOLAK', 'TIDAK_TERTAUT'].includes(this.pf.identitas_kuis.status)) return false;
            return this.pf.tenggat.valid && this.pf.target.baru > 0;
        },
        infoKaki() {
            if (this.step === 1 && this.form.id_paket) {
                return this.paketTerpilih ? `Paket: ${this.paketTerpilih.Nama_Paket}` : '';
            }
            if (this.step === 2 && this.jumlahTarget) return `${this.jumlahTarget} karyawan tersaring`;
            if (this.step === 3)
                return this.form.tipe_tenggat === 'DEADLINE'
                    ? `Deadline: ${this.form.tanggal_deadline || 'Belum dipilih'}`
                    : 'Tanpa tenggat';
            if (this.step === 4 && this.pf) return `${this.pf.target.baru} penugasan baru`;
            return '';
        },
    },
    watch: {
        modelValue(v) {
            if (v) {
                this.reset();
                if (this.paketAwal) this.form.id_paket = this.paketAwal;
            }
        },
    },
    methods: {
        theme(name) {
            return temaAksen(name);
        },
        modeDistribusi(paket) {
            const mode = typeof paket === 'string' ? paket : paket?.Mode_Distribusi;
            return mode === 'TERBATAS' ? 'TERBATAS' : 'PUBLIK';
        },
        canGoStepDirect(n) {
            if (n <= this.step) return true;
            if (n === 2 && this.form.id_paket) return true;
            if (n === 3 && this.form.id_paket && this.jumlahTarget) return true;
            if (n === 4 && this.bisaLanjut) return true;
            return false;
        },
        reset() {
            Object.assign(this.$data, this.$options.data.call(this));
        },
        pilihPaket(p) {
            if (this.form.id_paket !== p.Id_SOP_Paket) {
                this.form.kodes = [];
                this.form.unit = {};
                this.ringkasUnit = { total: 0, baru: 0, sudah_assign: 0 };
            }
            this.form.id_paket = p.Id_SOP_Paket;
            this.step = 2;
        },
        gantiMode(k) {
            if (this.mode === k) return;
            this.mode = k;
            this.form.kodes = [];
            this.form.unit = {};
            this.ringkasUnit = { total: 0, baru: 0, sudah_assign: 0 };
        },
        goStep(n) {
            if (n <= this.step) {
                this.step = n;
                return;
            }
            if (n === 2 && this.form.id_paket) this.step = 2;
            else if (n === 3 && this.jumlahTarget) this.step = 3;
            else if (n === 4 && this.bisaLanjut) this.lanjut();
        },
        getDeadlineDateString(days) {
            const d = new Date();
            d.setDate(d.getDate() + days);
            return d.toISOString().slice(0, 10);
        },
        setDeadlineDays(days) {
            this.form.tanggal_deadline = this.getDeadlineDateString(days);
            this.form.tipe_tenggat = 'DEADLINE';
        },
        setDeadlineEndOfMonth() {
            const d = new Date();
            const lastDay = new Date(d.getFullYear(), d.getMonth() + 1, 0);
            this.form.tanggal_deadline = lastDay.toISOString().slice(0, 10);
            this.form.tipe_tenggat = 'DEADLINE';
        },
        lanjut() {
            if (!this.bisaLanjut) return;
            this.step++;
            if (this.step === 4) this.ambilPreflight();
        },
        async ambilPreflight() {
            this.memuatPreflight = true;
            this.pf = null;
            try {
                const params = {
                    paket: this.form.id_paket,
                    tipe_tenggat: this.form.tipe_tenggat,
                    tanggal_deadline: this.form.tipe_tenggat === 'DEADLINE' ? this.form.tanggal_deadline : undefined,
                };
                if (this.mode === 'unit') {
                    const u = this.form.unit || {};
                    Object.assign(params, {
                        tipe: u.tipe || 'Semua',
                        dept: u.dept?.length ? u.dept : undefined,
                        divisi: u.divisi?.length ? u.divisi : undefined,
                        lokasi: u.lokasi?.length ? u.lokasi : undefined,
                        level: u.level?.length ? u.level : undefined,
                        jabatan: u.jabatan?.length ? u.jabatan : undefined,
                    });
                } else {
                    params.kodes = this.form.kodes;
                }
                const r = await axios.get(`${API}/preflight`, { params });
                if (r.data.success) this.pf = r.data.result;
            } catch (e) {
                ElMessage.error('Gagal memeriksa penjadwalan');
            } finally {
                this.memuatPreflight = false;
            }
        },
        async kirim() {
            this.mengirim = true;
            try {
                const payload = {
                    id_paket: this.form.id_paket,
                    nama_batch: this.form.nama_batch || null,
                    keterangan: this.form.keterangan || null,
                    tipe_tenggat: this.form.tipe_tenggat,
                    tanggal_deadline: this.form.tipe_tenggat === 'DEADLINE' ? this.form.tanggal_deadline : null,
                    flag_reminder: this.form.flag_reminder,
                    irama_reminder_hari: this.form.flag_reminder ? this.form.irama_reminder_hari : null,
                };
                if (this.mode === 'unit') payload.target = { mode: 'unit', filter: this.form.unit };
                else payload.kode_karyawan = this.form.kodes;

                const r = await axios.post(`${API}/assign`, payload);
                if (r.data.success) {
                    ElMessage.success(r.data.message);
                    if (r.data.result?.async) useActivityCenter().notifyNewTask();
                    this.buka = false;
                    this.$emit('selesai', r.data.result);
                }
            } catch (e) {
                ElMessage.error(e.response?.data?.message || 'Gagal menjadwalkan');
            } finally {
                this.mengirim = false;
            }
        },
    },
};
</script>

<style scoped>
.wz-wrap {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--surface, var(--hcis-white));
}

/* Header Console */
.wz-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.1rem 1.5rem 0.9rem;
    border-bottom: 1px solid var(--border-soft, #eef2f7);
    background: linear-gradient(180deg, var(--hcis-white) 0%, var(--hcis-slate-50) 100%);
}
.wz-head-ico {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    color: var(--hcis-white);
    display: grid;
    place-items: center;
    font-size: 1.25rem;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
}
.wz-step-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.1);
    color: var(--hcis-indigo-600);
    border: 1px solid rgba(99, 102, 241, 0.2);
}
.wz-x {
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-white);
    color: var(--hcis-slate-500);
    width: 36px;
    height: 36px;
    border-radius: 10px;
    cursor: pointer;
    display: grid;
    place-items: center;
    transition: all 0.2s ease;
}
.wz-x:hover {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-600);
    background: var(--hcis-slate-100);
    transform: rotate(90deg);
}

/* Stepper */
.wz-steps-container {
    padding: 1.1rem 2rem 0.6rem;
    background: var(--hcis-white);
    border-bottom: 1px solid var(--hcis-slate-100);
}
.wz-steps {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
}
.wz-steps::before {
    content: '';
    position: absolute;
    left: 40px;
    right: 40px;
    top: 18px;
    height: 3px;
    background: var(--hcis-slate-200);
    z-index: 0;
    border-radius: 999px;
}
.wz-steps-progress {
    position: absolute;
    left: 40px;
    top: 18px;
    height: 3px;
    background: linear-gradient(90deg, var(--hcis-indigo-500), var(--hcis-emerald-500));
    z-index: 0;
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 999px;
}
.wz-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    z-index: 1;
    cursor: pointer;
    flex: 1;
    user-select: none;
}
.wz-step.disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
.wz-dot {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: var(--hcis-white);
    border: 2px solid var(--border, var(--hcis-slate-300));
    color: var(--hcis-slate-400);
    font-size: 0.95rem;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.wz-step.active .wz-dot {
    border-color: var(--hcis-indigo-500);
    background: var(--hcis-indigo-500);
    color: var(--hcis-white);
    box-shadow: 0 0 0 5px rgba(99, 102, 241, 0.18);
    transform: scale(1.08);
}
.wz-step.done .wz-dot {
    border-color: var(--hcis-emerald-500);
    background: var(--hcis-emerald-500);
    color: var(--hcis-white);
}
.wz-label {
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    transition: color 0.2s ease;
}
.wz-step.active .wz-label {
    color: var(--hcis-indigo-600);
}
.wz-step.done .wz-label {
    color: var(--hcis-emerald-700);
}

/* Body */
.wz-body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 1.25rem 1.5rem;
}

/* Clear Search button */
.btn-clear-search {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--hcis-slate-400);
    cursor: pointer;
    font-size: 1.1rem;
    padding: 0;
    line-height: 1;
    transition: color 0.15s ease;
}
.btn-clear-search:hover {
    color: var(--hcis-rose-600);
}

/* Step 1 Packet Cards */
.wz-paket-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 0.75rem;
}
.wz-paket {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    text-align: left;
    padding: 0.9rem 1rem;
    border-radius: 16px;
    cursor: pointer;
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-white);
    color: var(--hcis-slate-500);
    transition: all 0.2s cubic-bezier(0.22, 1, 0.36, 1);
    position: relative;
    overflow: hidden;
}
.wz-paket:hover {
    border-color: var(--hcis-indigo-400);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(99, 102, 241, 0.12);
}
.wz-paket.on {
    border-color: var(--hcis-indigo-500);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05), rgba(129, 140, 248, 0.02));
    box-shadow:
        0 0 0 3px rgba(99, 102, 241, 0.18),
        0 8px 20px -4px rgba(99, 102, 241, 0.15);
}
.wz-paket-ico {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1.2rem;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}
.wz-paket:hover .wz-paket-ico {
    transform: scale(1.05);
}
.wz-paket-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}
.wz-paket-nama {
    font-weight: 700;
    font-size: 0.92rem;
    color: var(--hcis-slate-800);
}
.wz-paket-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.35rem;
    font-size: 0.72rem;
}
.wz-dist {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.18rem 0.48rem;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 800;
    line-height: 1;
}
.wz-dist.publik {
    color: var(--hcis-sky-600);
    background: var(--hcis-sky-100);
    border-color: var(--hcis-sky-200);
}
.wz-dist.terbatas {
    color: var(--hcis-amber-700);
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-200);
}
.wz-meta-prio {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.12rem 0.4rem;
    border-radius: 6px;
    text-transform: capitalize;
}
.wz-meta-prio.kritis {
    color: var(--hcis-rose-700);
    background: var(--hcis-rose-100);
}
.wz-meta-prio.tinggi {
    color: #c2410c;
    background: var(--hcis-orange-100);
}
.wz-meta-prio.normal {
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
}
.wz-select-check {
    font-size: 1.15rem;
    color: var(--hcis-slate-300);
    transition: color 0.15s ease;
}
.wz-paket.on .wz-select-check {
    color: var(--hcis-indigo-500);
}

.wz-version-tag {
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
    padding: 0.12rem 0.45rem;
    border-radius: 6px;
    border: 1px solid rgba(99, 102, 241, 0.2);
    font-family: monospace, SFMono-Regular, Consolas, sans-serif;
    flex-shrink: 0;
}
.wz-comp-tag {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-sky-700);
    background: var(--hcis-sky-50);
    border: 1px solid var(--hcis-sky-200);
    padding: 0.12rem 0.45rem;
    border-radius: 6px;
}
.wz-time-tag {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    padding: 0.12rem 0.45rem;
    border-radius: 6px;
}
/*
Status draft tidak dirender pada card daftar awal. Style badge dipertahankan
sebagai komentar sampai detail paket dipindahkan penuh ke respons preflight.
.wz-draft-badge {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-100);
    border: 1px solid var(--hcis-amber-200);
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.wz-draft-badge i {
    font-size: 0.55rem;
    color: var(--hcis-amber-600);
    animation: wzPulseDot 1.4s infinite;
}
@keyframes wzPulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.85); }
}
*/

/* Step 2 Modes */
.wz-modes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.65rem;
}
.wz-mode {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 0.8rem 0.95rem;
    border-radius: 14px;
    cursor: pointer;
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-white);
    text-align: left;
    transition: all 0.2s ease;
}
.wz-mode:hover {
    border-color: var(--hcis-indigo-400);
    transform: translateY(-1px);
}
.wz-mode.on {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.05);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
.wz-mode i {
    font-size: 1.25rem;
    color: var(--hcis-indigo-500);
}
.wz-mode-badge {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-100);
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
}
.wz-mode-t {
    font-weight: 800;
    font-size: 0.85rem;
    color: var(--hcis-slate-800);
}
.wz-mode-d {
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    margin-top: 0.1rem;
}

/* Step 3 & Step 4 Card Boxes */
.wz-card-box {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    padding: 1.15rem;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.03);
    transition: all 0.2s ease;
}
.wz-card-ico {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
}
.bg-primary-soft {
    background: rgba(79, 70, 229, 0.1);
}
.bg-amber-soft {
    background: rgba(245, 158, 11, 0.1);
}
.bg-purple-soft {
    background: rgba(168, 85, 247, 0.1);
}
.bg-indigo-soft {
    background: rgba(99, 102, 241, 0.1);
}
.text-amber {
    color: var(--hcis-amber-600);
}
.text-purple {
    color: var(--hcis-purple-600);
}
.text-indigo {
    color: var(--hcis-indigo-600);
}
.bg-indigo-light {
    background: var(--hcis-indigo-100);
}

/* Step 3 Jadwal */
.wz-jadwal {
    width: 100%;
    max-width: 100%;
}
.wz-sep {
    border: 0;
    border-top: 1px solid var(--border-soft, #eef2f7);
    margin: 1.25rem 0;
}
.wz-opt {
    font-weight: 500;
    color: var(--hcis-slate-400);
    text-transform: none;
}
.wz-saran-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.wz-saran-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-500);
}
.wz-saran {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.wz-chip-btn {
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--hcis-indigo-700);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-300);
    padding: 0.3rem 0.7rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.wz-chip-btn:hover,
.wz-chip-btn.active {
    background: var(--hcis-indigo-100);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-900);
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.15);
}

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

/* Tenggat cards */
.wz-tenggat-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.95rem 1rem;
    border-radius: 14px;
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-white);
    cursor: pointer;
    text-align: left;
    transition: all 0.2s ease;
}
.wz-tenggat-card:hover {
    border-color: var(--hcis-indigo-400);
    transform: translateY(-1px);
}
.wz-tenggat-card.active {
    border-color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.05);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
.wtc-ico {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
    display: grid;
    place-items: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.wtc-ico.amber {
    background: rgba(245, 158, 11, 0.12);
    color: var(--hcis-amber-600);
}
.wtc-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}
.wtc-info strong {
    font-size: 0.86rem;
    color: var(--hcis-slate-800);
}
.wtc-info small {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    line-height: 1.25;
    margin-top: 0.15rem;
}
.wtc-check {
    font-size: 1.15rem;
    color: var(--hcis-slate-300);
}
.wz-tenggat-card.active .wtc-check {
    color: var(--hcis-indigo-500);
}

.wz-deadline-box {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}
.preset-chip {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    padding: 0.25rem 0.6rem;
    border-radius: 7px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-chip:hover,
.preset-chip.active {
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-indigo-700);
    background: var(--hcis-indigo-100);
    box-shadow: 0 2px 5px rgba(99, 102, 241, 0.12);
}

.wz-reminder-box {
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}
.wz-hint {
    font-size: 0.75rem;
    color: var(--hcis-slate-500);
}
.wz-warn {
    font-size: 0.76rem;
    color: var(--hcis-rose-700);
    font-weight: 600;
}

.wajib-toggle {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    cursor: pointer;
}
.wajib-toggle input {
    display: none;
}
.wt-box {
    width: 22px;
    height: 22px;
    border-radius: 7px;
    border: 2px solid var(--hcis-slate-300);
    display: grid;
    place-items: center;
    color: transparent;
    transition: all 0.15s ease;
    flex-shrink: 0;
    margin-top: 2px;
}
.wajib-toggle input:checked + .wt-box {
    background: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
    color: var(--hcis-white);
}

/* Step 4 Review */
.wz-review {
    width: 100%;
    max-width: 100%;
}
.wz-rv-hero {
    position: relative;
    padding: 1.75rem 1.5rem;
    border-radius: 20px;
    background: linear-gradient(135deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 50%, var(--hcis-indigo-700) 100%);
    color: var(--hcis-white);
    box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4);
    overflow: hidden;
}
.wz-rv-hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
}
.wz-rv-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(4px);
    color: var(--hcis-white);
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    margin-bottom: 0.4rem;
}
.wz-rv-big {
    font-size: 3.4rem;
    font-weight: 800;
    color: var(--hcis-white);
    line-height: 1;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
}
.wz-rv-lab {
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 700;
    margin-top: 0.3rem;
}
.wz-rv-sub-chip {
    display: inline-flex;
    align-items: center;
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-amber-900);
    background: var(--hcis-amber-100);
    border: 1px solid var(--hcis-amber-200);
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
}

/* Review Summary Grid */
.wz-rv-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 0.75rem;
}
.wz-rv-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.85rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
}
.wz-rv-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    color: var(--hcis-indigo-600);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.wz-rv-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}
.wz-rv-key {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.wz-rv-val {
    font-size: 0.84rem;
    color: var(--hcis-slate-800);
    margin-top: 0.1rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.wz-alert {
    display: flex;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 14px;
    margin-bottom: 0.75rem;
    font-size: 0.82rem;
}
.wz-alert strong {
    display: block;
    font-size: 0.85rem;
}
.wz-alert p {
    margin-top: 0.15rem;
    opacity: 0.9;
}
.wz-alert.bad {
    background: var(--hcis-red-50);
    border: 1px solid var(--hcis-red-200);
    color: var(--hcis-red-800);
}
.wz-alert.warn {
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    color: var(--hcis-amber-800);
}
.wz-alert.ok {
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    color: var(--hcis-emerald-800);
}
.wz-alert.info {
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
    color: var(--hcis-indigo-800);
}

/* Executive Crystal Alert Banners */
.wz-alert-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    padding: 0.95rem 1.15rem;
    border-radius: 14px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}
.wz-alert-banner.warn {
    background: linear-gradient(135deg, #fffdf5 0%, var(--hcis-amber-100) 100%);
    border-color: var(--hcis-amber-200);
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.06);
}
.wz-alert-banner.bad {
    background: linear-gradient(135deg, #fff8f8 0%, var(--hcis-red-100) 100%);
    border-color: var(--hcis-red-200);
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.06);
}
.wz-alert-banner.ok {
    background: linear-gradient(135deg, #f2fcf7 0%, var(--hcis-emerald-100) 100%);
    border-color: var(--hcis-emerald-200);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.06);
}
.wz-alert-banner.info {
    background: linear-gradient(135deg, var(--hcis-sky-50) 0%, var(--hcis-sky-100) 100%);
    border-color: var(--hcis-sky-200);
    box-shadow: 0 2px 8px rgba(14, 165, 233, 0.06);
}
.wz-ab-ico {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
    margin-top: 1px;
}
.wz-ab-ico.amber {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-600);
    border: 1px solid var(--hcis-amber-200);
}
.wz-ab-ico.red {
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
    border: 1px solid var(--hcis-red-200);
}
.wz-ab-ico.green {
    background: var(--hcis-emerald-100);
    color: var(--hcis-emerald-600);
    border: 1px solid var(--hcis-emerald-200);
}
.wz-ab-ico.blue {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-600);
    border: 1px solid var(--hcis-sky-200);
}
.wz-ab-ico.purple {
    background: var(--hcis-purple-100);
    color: var(--hcis-purple-600);
    border: 1px solid #e9d5ff;
}
.wz-ab-title {
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1.35;
}
.wz-ab-desc {
    font-size: 0.8125rem;
    line-height: 1.5;
}
.text-amber-900 {
    color: var(--hcis-amber-900);
}
.text-amber-800 {
    color: var(--hcis-amber-800);
}
.text-emerald {
    color: var(--hcis-emerald-600);
}
.text-emerald-800 {
    color: var(--hcis-emerald-800);
}
.text-sky-900 {
    color: var(--hcis-sky-900);
}
.text-sky-800 {
    color: var(--hcis-sky-700);
}
.text-purple-900 {
    color: #581c87;
}
.text-purple-800 {
    color: var(--hcis-purple-700);
}

.wz-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 3rem 1.5rem;
    color: var(--hcis-slate-400);
    font-size: 0.85rem;
}
.wz-empty i {
    font-size: 2.2rem;
}

/* Catatan di bawah grid paket: batas render (informasi) & potongan server (peringatan). */
.wz-render-note,
.wz-server-note {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    margin-top: 0.85rem;
    padding: 0.65rem 0.85rem;
    border-radius: 12px;
    font-size: 0.78rem;
    line-height: 1.45;
}
.wz-render-note i,
.wz-server-note i {
    flex-shrink: 0;
    margin-top: 0.08rem;
}
.wz-render-note {
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
}
.wz-render-note i {
    color: var(--hcis-slate-500);
}
.wz-server-note {
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-100);
    border: 1px solid var(--hcis-amber-200);
}
.wz-server-note i {
    color: var(--hcis-amber-700);
}

/* Foot navigation */
.wz-foot {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-soft, #eef2f7);
    background: var(--hcis-white);
    box-shadow: 0 -4px 12px rgba(15, 23, 42, 0.03);
}
.wz-foot-info {
    flex: 1;
    text-align: center;
    font-size: 0.82rem;
    color: var(--hcis-slate-600);
    font-weight: 700;
}

:deep(.el-drawer__body) {
    padding: 0 !important;
    overflow: hidden !important;
}

.wz-mobile-step-bar {
    padding: 0.65rem 1.1rem;
    background: var(--hcis-slate-50);
    border-bottom: 1px solid var(--hcis-slate-200);
    flex-shrink: 0;
}
.wz-m-step-ico {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}
.wz-m-step-count {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
}
.wz-m-progress-track {
    width: 100%;
    height: 5px;
    background: var(--hcis-slate-200);
    border-radius: 999px;
    overflow: hidden;
}
.wz-m-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-indigo-600) 0%, var(--hcis-indigo-500) 100%);
    border-radius: 999px;
    transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

/* ==========================================================================
   Mobile Responsive Adjustments for WizardPenjadwalan
   ========================================================================== */
@media (max-width: 767px) {
    .wz-wrap {
        height: 100vh;
        height: 100dvh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: var(--hcis-white);
    }

    .wz-head {
        padding: 0.85rem 1.1rem;
        border-bottom: 1px solid var(--hcis-slate-100);
        flex-shrink: 0;
    }

    .wz-head-ico {
        width: 38px;
        height: 38px;
        font-size: 1.1rem;
        border-radius: 11px;
    }

    .wz-head h6 {
        font-size: 1.05rem !important;
        font-weight: 800 !important;
    }

    /* Scrollable Body Container - Maximum Reading Area */
    .wz-body {
        padding: 1.1rem 1.1rem 1.5rem;
        flex: 1;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Step 1 Package Cards */
    .wz-paket-grid {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .wz-paket {
        padding: 0.95rem 1rem;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    /* Step 2 Mode Cards */
    .wz-modes {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }

    .wz-mode {
        padding: 0.85rem 1rem;
        border-radius: 16px;
    }

    /* Step 3 Grid Layouts */
    .grid-2 {
        grid-template-columns: 1fr !important;
        gap: 0.85rem;
    }

    .wz-tenggat-card {
        padding: 0.85rem 1rem;
        border-radius: 16px;
    }

    .wz-deadline-box,
    .wz-reminder-box {
        padding: 1rem !important;
        border-radius: 16px !important;
    }

    /* Step 4 Review Hero */
    .wz-rv-hero {
        padding: 1.25rem 1rem;
        border-radius: 20px;
    }

    .wz-rv-big {
        font-size: 2.8rem;
    }

    .wz-rv-list > div {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
        padding: 0.65rem 0;
    }

    .wz-rv-list dd {
        text-align: left;
    }

    /* Sticky Mobile Footer Bar */
    .wz-foot {
        position: sticky;
        bottom: 0;
        z-index: 30;
        padding: 0.85rem 1.1rem;
        background: var(--hcis-white);
        border-top: 1px solid var(--hcis-slate-100);
        box-shadow: 0 -8px 24px rgba(15, 23, 42, 0.08);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .wz-foot .stitch-btn {
        height: 48px;
        font-size: 0.92rem;
        font-weight: 700;
        border-radius: 12px;
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .wz-foot-info {
        display: none;
    }
}
</style>

<!-- Unscoped Style for Teleported Element Plus Drawer -->
<style scoped>
.wz-drawer .el-drawer__body,
.wz-drawer-body {
    padding: 0 !important;
    overflow: hidden !important;
}
</style>

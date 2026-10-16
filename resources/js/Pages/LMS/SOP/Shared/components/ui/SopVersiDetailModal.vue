<template>
    <HcisDialog
        :model-value="modelValue"
        :title="judulModal"
        :subtitle="subjudulModal"
        :icon="ikonModal"
        :sentiment="sentimentModal"
        size="xl"
        glow
        pattern="dots"
        sticky-footer
        @update:model-value="$emit('update:modelValue', $event)"
        @close="$emit('update:modelValue', false)"
    >
        <template #header-actions>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <HcisBadge
                    :label="statusLabel"
                    :variant="statusVariant"
                    size="sm"
                    :dot="isActive"
                />
                <span
                    v-if="versiData?.Tahap_Nama"
                    class="sop-detail-tahap-pill"
                    :style="{ '--tc': versiData.Tahap_Warna || '#64748b' }"
                >
                    <i class="bi bi-signpost-split-fill me-1"></i>{{ versiData.Tahap_Nama }}
                </span>
            </div>
        </template>

        <!-- Loading State Saat Fetch Backend -->
        <div v-if="memuatDetail" class="text-center py-5">
            <div class="spinner-border text-primary mb-2" role="status"></div>
            <p class="text-muted small mb-0">Mengambil data lengkap versi dari server…</p>
        </div>

        <div v-else-if="versiData" class="d-flex flex-column gap-3.5 py-1">
            <!-- ══ 0. PERINGATAN DATA TAK LENGKAP ══ -->
            <HcisAlert
                v-if="galatDetail"
                variant="warning"
                appearance="soft"
                icon="exclamation-triangle-fill"
                title="Data lengkap versi gagal dimuat"
                size="sm"
                class="mb-0"
            >
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span>
                        {{ galatDetail }} Yang tampil di bawah hanya ringkasan baris —
                        <strong>pengesah, putaran review, dan berkas belum tentu kosong.</strong>
                    </span>
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        label="Coba Lagi"
                        :loading="memuatDetail"
                        @click="muatDetailVersiLengkap"
                    />
                </div>
            </HcisAlert>

            <!-- ══ 1. BANNER STATUS KONTEKSTUAL ══ -->
            <HcisAlert
                :variant="sentimentModal"
                appearance="soft"
                :icon="ikonModal"
                :title="`Status Versi: ${tahapNamaLengkap}`"
                size="sm"
                glow
                pattern="dots"
                class="mb-0"
            >
                <template v-if="keadaanVersi === 'batal'">
                    Versi ini telah <strong>dibatalkan</strong> pada alur tata kelola dan tersimpan secara permanen sebagai arsip riwayat audit trail (mode baca-saja).
                </template>
                <template v-else-if="keadaanVersi === 'berlaku'">
                    Versi ini adalah <strong>versi operasional yang sedang berlaku</strong> — dokumen menunjuknya sebagai versi aktif, dan inilah yang dibaca karyawan di seluruh paket penugasan.
                </template>
                <template v-else-if="keadaanVersi === 'terbit-lepas'">
                    Versi ini <strong>berstatus terbit tetapi bukan versi aktif dokumen</strong>. Yang menentukan versi mana yang dibaca karyawan adalah penunjuk versi aktif pada dokumen, bukan status ini — periksa kembali dokumennya.
                </template>
                <template v-else-if="keadaanVersi === 'siap'">
                    Versi ini <strong>sudah siap diterbitkan</strong> — seluruh langkah tata kelolanya tuntas dan tinggal menunggu penerbitan. Karyawan belum membacanya.
                </template>
                <template v-else-if="keadaanVersi === 'draft'">
                    Versi ini masih berstatus <strong>draft pengerjaan</strong> pada tahap {{ versiData.Tahap_Nama || 'Pengajuan' }}.
                </template>
                <template v-else>
                    Versi ini adalah <strong>versi arsip terdahulu (obsolete)</strong> yang sudah digantikan oleh versi rilis yang lebih baru.
                </template>
            </HcisAlert>

            <!-- ══ 2. EXECUTIVE TELEMETRY KPI METRICS WIDGET ══ -->
            <HcisStatGroup :cols="4" gap="sm">
                <HcisStatCard
                    layout="compact"
                    label="Nomor Versi"
                    :value="versiLabel"
                    :caption="versiData?.Status ? `Status: ${versiData.Status}` : 'Versi Dokumen'"
                    icon="layers-fill"
                    sentiment="brand"
                />
                <HcisStatCard
                    layout="compact"
                    label="Pengesah & Review"
                    :value="`${daftarPengesah.length} Pejabat`"
                    :caption="`${reviewList.length} Putaran Review`"
                    icon="person-check-fill"
                    sentiment="purple"
                />
                <HcisStatCard
                    layout="compact"
                    label="Lampiran"
                    :value="ringkasanLampiranLabel"
                    :caption="isKuis ? 'Modul Ujian Online' : (berkasKonten?.total_halaman ? `${berkasKonten.total_halaman} Halaman` : (versiData.Punya_Berkas ? 'File PDF Resmi' : 'Tanpa File Terlampir'))"
                    :icon="isKuis ? 'patch-question-fill' : 'file-earmark-pdf-fill'"
                    sentiment="teal"
                />
                <HcisStatCard
                    layout="compact"
                    label="Masa Berlaku"
                    :value="masaBerlaku.nilai"
                    :caption="masaBerlaku.keterangan || (isCancelled ? 'Status Batal' : 'Masa Berlaku Versi')"
                    :icon="masaBerlaku.ikon"
                    :sentiment="statMasaBerlakuSentiment"
                />
            </HcisStatGroup>

            <!-- ══ 1. DOKUMEN INDUK & BERKAS RESMI ══ -->
            <HcisCard
                title="Dokumen Induk & Berkas Prosedur"
                description="Penanda resmi dokumen, berkas lampiran utama, formulir terkait, dan ringkasan perubahan versi."
                icon="folder2-open"
                variant="outlined"
                sentiment="primary"
                size="sm"
            >
                <!-- A. Identitas Dokumen Induk (3-column grid) -->
                <div class="sop-detail-grid">
                    <div v-for="baris in identitasDokumen" :key="baris.label" class="sop-detail-tile">
                        <span class="sop-detail-tile__label">{{ baris.label }}</span>
                        <span class="sop-detail-tile__val text-truncate" :class="baris.kosong ? 'text-muted fw-normal fst-italic' : ''" :title="baris.nilai">
                            {{ baris.nilai }}
                        </span>
                    </div>
                </div>

                <!-- Deskripsi Dokumen (jika ada) -->
                <div v-if="dokumenData.Deskripsi" class="sop-quote-box mt-3">
                    <i class="bi bi-info-circle-fill fs-6 text-primary me-1 flex-shrink-0"></i>
                    <span>{{ dokumenData.Deskripsi }}</span>
                </div>

                <!-- B. Lampiran Berkas Utama / Paket Kuis -->
                <div class="mt-3.5 pt-3.5 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-xs fw-bold text-slate-700 text-uppercase tracking-wider">
                            <i :class="isKuis ? 'bi bi-patch-question-fill text-purple' : 'bi bi-file-earmark-pdf-fill text-primary'" class="me-1"></i>
                            {{ isKuis ? 'Paket Ujian HC Learn' : 'Berkas Lampiran Utama' }}
                        </span>
                        <HcisBadge
                            v-if="berkasKonten?.total_halaman"
                            :label="`${berkasKonten.total_halaman} Halaman`"
                            variant="neutral"
                            size="xs"
                        />
                    </div>

                    <!-- Kuis Exam Banner -->
                    <div v-if="isKuis" class="sop-file-banner p-3 rounded-2 d-flex align-items-center justify-content-between flex-wrap gap-2.5">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="sop-file-icon-box bg-purple-soft text-purple border-0">
                                <i class="bi bi-patch-question-fill fs-4"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-dark text-truncate small" :title="versiData.Nama_Ujian || 'Ujian Tertaut'">
                                    {{ versiData.Nama_Ujian || 'Ujian Tertaut' }}
                                </div>
                                <div class="text-muted text-xs mt-0.5 d-flex align-items-center gap-1.5">
                                    <HcisBadge label="HC Learn Exam" variant="purple" size="2xs" />
                                    <span>Modul kuis evaluasi pemahaman</span>
                                </div>
                            </div>
                        </div>
                        <HcisBadge label="Terintegrasi HC Learn" variant="success" size="xs" icon="check-circle-fill" />
                    </div>

                    <!-- File PDF Banner -->
                    <div v-else-if="versiData.Punya_Berkas || berkasKonten?.url || versiData.Id_Master_Konten" class="sop-file-banner p-3 rounded-2 d-flex align-items-center justify-content-between flex-wrap gap-2.5">
                        <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                            <div class="sop-file-icon-box bg-brand-soft text-primary border-0">
                                <i class="bi bi-file-earmark-pdf-fill fs-4"></i>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-bold text-dark text-truncate small" :title="namaBerkas">{{ namaBerkas }}</div>
                                <div class="text-muted text-xs mt-0.5 d-flex align-items-center gap-1.5 flex-wrap">
                                    <HcisBadge label="PDF / Berkas" variant="brand" size="2xs" />
                                    <span v-if="berkasKonten?.nama_berkas" class="text-secondary text-truncate" :title="berkasKonten.nama_berkas">{{ berkasKonten.nama_berkas }}</span>
                                    <span class="text-secondary d-none d-sm-inline">· Content Repository</span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <HcisButton
                                variant="primary"
                                size="xs"
                                leading-icon="eye"
                                label="Pratinjau Berkas"
                                :loading="memuatBerkas"
                                @click="bukaViewerBerkas"
                            />
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                leading-icon="download"
                                label="Unduh"
                                :loading="memuatUnduh"
                                @click="unduhBerkas"
                            />
                        </div>
                    </div>

                    <!-- Empty File -->
                    <div v-else class="sop-empty-box py-3 text-center">
                        <i class="bi bi-file-earmark-x text-muted fs-4 mb-0.5 d-block"></i>
                        <span class="text-muted small fst-italic">Belum ada berkas lampiran yang dihubungkan pada versi ini.</span>
                    </div>
                </div>

                <!-- C. Formulir Kosong untuk Diunduh (jika ada) -->
                <div v-if="formKosong.length" class="mt-3.5 pt-3.5 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-xs fw-bold text-slate-700 text-uppercase tracking-wider">
                            <i class="bi bi-file-earmark-plus-fill text-success me-1"></i>
                            Form Kosong untuk Diunduh
                        </span>
                        <HcisBadge :label="`${formKosong.length} Formulir`" variant="neutral" size="xs" />
                    </div>
                    <div class="sop-form-kosong-grid">
                        <div
                            v-for="l in formKosong"
                            :key="l.Id_Lampiran"
                            class="sop-form-kosong-item"
                        >
                            <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                                <div class="sop-form-file-icon flex-shrink-0" :class="`is-${(l.Ext || 'file').toLowerCase()}`">
                                    <i class="bi" :class="getFormFileIcon(l.Ext)"></i>
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-semibold text-dark text-truncate text-xs" :title="l.Judul || 'Form kosong'">
                                        {{ l.Judul || 'Form kosong' }}
                                    </div>
                                    <div class="text-muted text-2xs mt-0.5 text-uppercase">
                                        {{ l.Ext || 'File' }}
                                    </div>
                                </div>
                            </div>
                            <HcisButton
                                variant="secondary"
                                size="xs"
                                leading-icon="download"
                                label="Unduh"
                                :loading="mengunduhFormKosong === l.Id_Lampiran"
                                @click="unduhFormKosong(l)"
                            />
                        </div>
                    </div>
                </div>

                <!-- D. Ringkasan Catatan Perubahan Versi -->
                <div class="mt-3.5 pt-3.5 border-top">
                    <div class="d-flex align-items-center gap-1.5 mb-1.5">
                        <span class="text-xs fw-bold text-slate-700 text-uppercase tracking-wider">
                            <i class="bi bi-chat-left-quote-fill text-primary me-1"></i>
                            Catatan Perubahan Versi
                        </span>
                    </div>
                    <div v-if="versiData.Ringkasan_Perubahan" class="sop-quote-box">
                        <i class="bi bi-quote fs-4 text-primary me-1 flex-shrink-0"></i>
                        <span>{{ versiData.Ringkasan_Perubahan }}</span>
                    </div>
                    <div v-else class="text-muted text-xs fst-italic py-1">
                        Tidak ada ringkasan catatan perubahan yang dicantumkan pada versi ini.
                    </div>
                </div>
            </HcisCard>

            <!-- ══ 2. MATRIKS PEJABAT PENGESAH & REVIEW MUTU ══ -->
            <HcisCard
                title="Matriks Pejabat Pengesah Dokumen"
                description="Daftar pejabat struktural penanda tangan pengesahan serta jejak putaran review mutu."
                icon="person-badge-fill"
                variant="outlined"
                sentiment="primary"
                size="sm"
                :badge="`${daftarPengesah.length} Pejabat`"
                badge-variant="neutral"
            >
                <!-- Banner Pengesahan Utama (jika ada data) -->
                <div v-if="versiData.Nomor_Pengesahan || versiData.Tanggal_Pengesahan || versiData.Disahkan_Oleh" class="sop-sah-banner p-3 rounded-2 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 text-xs">
                    <div class="d-flex align-items-center gap-2.5 min-w-0">
                        <div class="sop-sah-icon flex-shrink-0">
                            <i class="bi bi-patch-check-fill text-success fs-5"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate">
                                Pengesahan Utama: {{ versiData.Disahkan_Oleh || 'Tercatat' }}
                            </div>
                            <div v-if="versiData.Jabatan_Pengesah" class="text-muted text-truncate mt-0.5">
                                {{ versiData.Jabatan_Pengesah }}
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap text-muted flex-shrink-0">
                        <span v-if="versiData.Nomor_Pengesahan && versiData.Nomor_Pengesahan !== '—'">
                            No. Pengesahan: <strong class="text-dark font-monospace">{{ versiData.Nomor_Pengesahan }}</strong>
                        </span>
                        <span v-if="versiData.Tanggal_Pengesahan">
                            · Tanggal Sah: <strong class="text-dark">{{ formatTanggal(versiData.Tanggal_Pengesahan) }}</strong>
                        </span>
                    </div>
                </div>

                <!-- Grid Kartu Pejabat Pengesah -->
                <div v-if="pengesahTerurut.length" class="sop-signer-grid">
                    <div
                        v-for="p in pengesahTerurut"
                        :key="p._key"
                        class="sop-signer-card p-3 rounded-2 border bg-white d-flex flex-column gap-2"
                    >
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-1.5 min-w-0">
                                <span class="sop-signer-urut" :title="`Urutan Tanda Tangan: #${p._urut}`">{{ p._urut }}</span>
                                <span class="sop-peran-chip text-truncate" :class="`is-${(p.Peran || '').toLowerCase()}`">
                                    {{ p.Peran || 'Penanda Tangan' }}
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                <HcisBadge
                                    v-if="p.Jenis"
                                    :label="p.Jenis"
                                    variant="neutral"
                                    size="xs"
                                />
                                <HcisBadge
                                    :label="p.Kode_Karyawan ? 'HRIS' : 'Manual'"
                                    :variant="p.Kode_Karyawan ? 'success' : 'neutral'"
                                    size="xs"
                                    :icon="p.Kode_Karyawan ? 'patch-check-fill' : 'pencil'"
                                />
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2.5 min-w-0 pt-1">
                            <div class="sop-signer-avatar flex-shrink-0" :class="`is-${(p.Peran || '').toLowerCase()}`">
                                {{ getInitials(p.Nama) }}
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-bold text-dark text-truncate small" :title="p.Nama || ''">
                                    {{ p.Nama || '—' }}
                                </div>
                                <div class="text-muted text-xs text-truncate mt-0.5" :title="p.Jabatan || ''">
                                    {{ p.Jabatan || 'Tanpa Keterangan Jabatan' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="sop-empty-box py-3.5 text-center">
                    <i class="bi bi-person-x text-muted fs-4 mb-1 d-block"></i>
                    <span class="text-muted small fst-italic">Belum ada pejabat penanda tangan yang didaftarkan pada versi ini.</span>
                </div>

                <!-- Sub-Section: Review Mutu -->
                <div class="mt-3.5 pt-3.5 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-xs fw-bold text-slate-700 text-uppercase tracking-wider">
                            <i class="bi bi-shield-check text-purple me-1"></i>
                            Catatan Putaran Review Mutu
                        </span>
                        <HcisBadge
                            :label="`${reviewList.length} Putaran`"
                            :variant="reviewList.length ? 'purple' : 'neutral'"
                            size="xs"
                        />
                    </div>

                    <!-- Jika ada review -->
                    <div v-if="reviewTampil.length" class="d-flex flex-column gap-2.5">
                        <div
                            v-for="(r, idx) in reviewTampil"
                            :key="r.Id_Review || idx"
                            class="sop-review-card p-3 rounded-2 border bg-white"
                        >
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 pb-1.5 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <HcisBadge :label="`Putaran #${r.Review_Ke || idx + 1}`" variant="purple" size="xs" />
                                    <span class="fw-semibold text-dark small">{{ formatTanggal(r.Tanggal_Review) }}</span>
                                </div>
                                <span v-if="r.Reviewer_Nama" class="text-muted text-xs d-flex align-items-center gap-1">
                                    <i class="bi bi-person-check text-purple"></i>
                                    <span>Reviewer: <strong class="text-dark">{{ r.Reviewer_Nama }}</strong></span>
                                </span>
                            </div>

                            <div v-if="r.Hasil_Review" class="small text-dark lh-base mb-2 sop-review-narasi" v-html="sanitasiSop(r.Hasil_Review)"></div>
                            <div v-else class="text-muted text-xs fst-italic mb-2">Tanpa catatan naratif.</div>

                            <!-- Galeri Lampiran Review -->
                            <div v-if="r.LampiranTampil.length" class="d-flex flex-column gap-1 pt-2 border-top">
                                <span class="text-muted text-2xs fw-semibold">Bukti &amp; Lampiran Review:</span>
                                <div class="d-flex align-items-center gap-2 flex-wrap pt-0.5">
                                    <div
                                        v-for="(img, imgIdx) in r.LampiranTampil"
                                        :key="img.id"
                                        class="sop-review-thumb rounded border overflow-hidden position-relative"
                                        :class="img.url ? 'cursor-pointer' : 'is-mati'"
                                        :title="img.url ? img.nama : `${img.nama} — alamat berkas tak tersedia`"
                                        @click="img.url && bukaViewerReview(r.LampiranTampil, imgIdx)"
                                    >
                                        <img
                                            v-if="img.gambar && img.url"
                                            :src="img.url"
                                            :alt="img.nama"
                                            class="w-100 h-100 object-fit-cover"
                                        />
                                        <div v-else class="sop-review-thumb__ikon">
                                            <i class="bi" :class="`bi-${img.ikon}`"></i>
                                        </div>
                                        <div v-if="img.url" class="sop-thumb-hover d-flex align-items-center justify-content-center">
                                            <i class="bi bi-eye-fill text-white fs-6"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Jika 0 review -->
                    <div v-else class="p-2.5 rounded-2 bg-slate-50 border border-slate-200 d-flex align-items-center justify-content-between gap-2 text-xs text-muted">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle text-muted"></i>
                            <span>Tidak ada catatan putaran review khusus yang direkam untuk versi ini (langsung disahkan).</span>
                        </div>
                        <span class="text-muted fst-italic">0 Putaran</span>
                    </div>
                </div>
            </HcisCard>

            <!-- ══ 3. ALUR TATA KELOLA & LINIMASA SIKLUS VERSI ══ -->
            <HcisCard
                title="Alur Tata Kelola & Linimasa Siklus Versi"
                description="Tahapan wizard alur tata kelola versi serta jejak tanggal siklus hidup dokumen."
                icon="signpost-split-fill"
                variant="outlined"
                sentiment="primary"
                size="sm"
                :badge="alurRingkasLabel"
                badge-variant="neutral"
            >
                <!-- A. Horizontal 5-Step Stepper Track -->
                <div class="sop-stepper-track mb-3.5 pb-3 border-bottom">
                    <div
                        v-for="langkah in alurLangkah"
                        :key="langkah.Nomor"
                        class="sop-stepper-step"
                        :class="`is-${langkah.Keadaan}`"
                    >
                        <div class="sop-stepper-step__marker">
                            <i v-if="langkah.Keadaan === 'selesai'" class="bi bi-check-lg"></i>
                            <span v-else>{{ langkah.Nomor }}</span>
                        </div>
                        <div class="sop-stepper-step__content">
                            <div class="sop-stepper-step__title text-truncate" :title="langkah.Nama">
                                {{ langkah.Nama }}
                            </div>
                            <div class="d-flex align-items-center gap-1 mt-0.5">
                                <span class="sop-stepper-step__badge" :class="`is-${langkah.Keadaan}`">
                                    {{ langkah._keadaanLabel }}
                                </span>
                                <span v-if="langkah.Tanggal" class="sop-stepper-step__date text-truncate">
                                    · {{ formatTanggal(langkah.Tanggal) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- B. Linimasa Siklus 3-Column Milestone Matrix -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-xs fw-bold text-slate-700 text-uppercase tracking-wider">
                        <i class="bi bi-calendar-range-fill text-primary me-1"></i>
                        Linimasa Siklus &amp; Jejak Tanggal
                    </span>
                    <HcisBadge
                        :label="`${jumlahMilestoneTercapai}/${milestones.length} Tercatat`"
                        variant="neutral"
                        size="xs"
                    />
                </div>

                <div class="sop-audit-timeline-grid">
                    <div
                        v-for="m in milestones"
                        :key="m.judul"
                        class="sop-audit-milestone"
                        :class="{ 'is-kosong': !m.tercapai }"
                    >
                        <div class="sop-audit-milestone__dot" :class="`bg-${m.dot}`"></div>
                        <div class="sop-audit-milestone__info min-w-0">
                            <span class="sop-audit-milestone__title text-truncate" :title="m.judul">{{ m.judul }}</span>
                            <span class="sop-audit-milestone__val text-truncate" :class="m.kelasNilai" :title="m.nilai">
                                {{ m.nilai }}
                            </span>
                            <span v-if="m.sub" class="sop-audit-milestone__sub text-truncate" :title="m.sub">{{ m.sub }}</span>
                        </div>
                    </div>
                </div>
            </HcisCard>

        </div>

        <!-- ══ FOOTER ACTIONS ══ -->
        <template #footer>
            <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                <HcisButton
                    v-if="isDraft"
                    variant="primary"
                    size="sm"
                    leading-icon="pencil-square"
                    label="Buka di Wizard Versi"
                    @click="navigasiKeWizard"
                />
                <HcisButton
                    v-else-if="versiData"
                    variant="secondary"
                    size="sm"
                    leading-icon="box-arrow-up-right"
                    label="Buka Wizard Versi (Mode Arsip)"
                    @click="navigasiKeWizard"
                />
                <div v-else></div>

                <HcisButton
                    variant="ghost"
                    size="sm"
                    label="Tutup"
                    @click="$emit('update:modelValue', false)"
                />
            </div>
        </template>

        <!-- ══ PRATINJAU FILE VIEWER MODAL ══ -->
        <HcisFileViewer
            v-if="viewerData"
            v-model="viewerBuka"
            :file="viewerData"
            :files="viewerFiles"
            :initial-index="viewerIndex"
            @close="viewerBuka = false"
        />
    </HcisDialog>
</template>

<script>
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import HcisDialog from '@/components/ui/HcisDialog.vue';
import HcisCard from '@/components/ui/HcisCard.vue';
import HcisAlert from '@/components/ui/HcisAlert.vue';
import HcisBadge from '@/components/ui/HcisBadge.vue';
import HcisButton from '@/components/ui/HcisButton.vue';
import HcisFileViewer from '@/components/ui/HcisFileViewer.vue';
import HcisIcon from '@/components/ui/HcisIcon.vue';
import HcisStatGroup from '@/components/ui/HcisStatGroup.vue';
import HcisStatCard from '@/components/ui/HcisStatCard.vue';
import { hcisMessage } from '@/components/ui';
import { sanitasiSop } from '@/utils/sopHtml';

const API = '/api/v1/sop/admin/dokumen';

export default {
    name: 'SopVersiDetailModal',
    components: {
        HcisDialog,
        HcisCard,
        HcisAlert,
        HcisBadge,
        HcisButton,
        HcisFileViewer,
        HcisIcon,
        HcisStatGroup,
        HcisStatCard,
    },
    props: {
        modelValue: { type: Boolean, default: false },
        versi: { type: Object, default: null },
        dokumen: { type: Object, default: () => ({}) },
        idDokumen: { type: [String, Number], default: null },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            tabAktif: 'ringkasan',
            memuatDetail: false,
            detailBackend: null,
            galatDetail: null,
            memuatBerkas: false,
            mengunduhFormKosong: null,
            memuatUnduh: false,
            viewerBuka: false,
            viewerData: null,
            viewerFiles: [],
            viewerIndex: 0,
        };
    },
    computed: {
        versiData() {
            return this.detailBackend || this.versi?.raw || this.versi || null;
        },
        dokumenData() {
            return this.detailBackend?.Dokumen || this.dokumen || {};
        },
        versiLabel() {
            if (!this.versiData || this.versiData.Versi_Ke === null || this.versiData.Versi_Ke === undefined) return 'Versi —';
            return `v${String(this.versiData.Versi_Ke).padStart(2, '0')}`;
        },
        judulModal() {
            return `Detail Tata Kelola ${this.versiLabel}`;
        },
        subjudulModal() {
            const no = this.dokumenData?.Nomor_SOP_Resmi || this.dokumenData?.No_Dokumen || '';
            const judul = this.dokumenData?.Judul || '';
            return [no, judul].filter(Boolean).join(' · ') || 'Histori Rinci Versi Dokumen';
        },
        /**
         * Dibatalkan datang dari server — jawaban yang sama yang dipakai penjaga
         * di SopDokumenService dan ketiga layar lain.
         *
         * Turunan lama mencari kata "batal" pada nama tahap yang ternyata
         * berbunyi "Cancel", dan memeriksa Status yang bagi versi batal tetap
         * 'DRAFT'. Yang menahannya cuma satu cabang Tahap_Kode; cabang lainnya
         * hanya membuatnya TERLIHAT dijaga berlapis.
         *
         * Tahap_Kode dipertahankan sebagai cadangan: modal ini juga dibuka dari
         * muatan yang belum tentu membawa kolom baru itu.
         */
        isCancelled() {
            const v = this.versiData;
            if (!v) return false;

            return v.Dibatalkan === true || v.Tahap_Kode === 'CANCEL';
        },
        /**
         * Keadaan versi sebagai SATU kata, sumber tunggal untuk label, warna,
         * ikon, dan kalimat banner.
         *
         * Sebelumnya keadaan cuma dikenali dua — PUBLISHED dan DRAFT — sehingga
         * READY, yang berarti "tuntas, tinggal diterbitkan", jatuh ke cabang
         * terakhir dan terbaca sebagai "Versi Arsip (Obsolete)": kebalikan
         * artinya, pada versi yang justru sedang menunggu tindakan admin.
         *
         * "Berlaku" ditentukan PENUNJUK VERSI AKTIF DOKUMEN, bukan status
         * versinya. Status PUBLISHED cuma berarti versi ini pernah sah terbit;
         * yang dibaca karyawan adalah versi yang ditunjuk dokumen. Kalau
         * penunjuknya belum ikut, layar ini harus mengatakannya, bukan
         * menambal dengan menyebut dua versi sama-sama berlaku.
         *
         * Bila Is_Versi_Aktif tak ada — data prop ringkas saat detail gagal
         * dimuat — PUBLISHED dianggap berlaku seperti dulu. Menuduh anomali
         * berdasarkan field yang memang tak dikirim lebih buruk daripada diam.
         */
        keadaanVersi() {
            const v = this.versiData;
            if (!v) return 'obsolete';
            if (this.isCancelled) return 'batal';

            switch (v.Status) {
                case 'DRAFT':
                    return 'draft';
                case 'READY':
                    return 'siap';
                case 'PUBLISHED':
                    return v.Is_Versi_Aktif === false ? 'terbit-lepas' : 'berlaku';
                default:
                    return 'obsolete';
            }
        },
        isActive() {
            return this.keadaanVersi === 'berlaku';
        },
        isDraft() {
            return this.keadaanVersi === 'draft';
        },
        statusLabel() {
            return {
                batal: 'Dibatalkan (Arsip)',
                berlaku: 'Versi Berlaku (Aktif)',
                'terbit-lepas': 'Terbit — Bukan Versi Aktif',
                siap: 'Siap Diterbitkan',
                draft: 'Draft Revisi',
                obsolete: 'Versi Arsip (Obsolete)',
            }[this.keadaanVersi];
        },
        statusVariant() {
            return {
                batal: 'danger',
                berlaku: 'success',
                'terbit-lepas': 'warning',
                siap: 'info',
                draft: 'warning',
                obsolete: 'neutral',
            }[this.keadaanVersi];
        },
        sentimentModal() {
            return {
                batal: 'danger',
                berlaku: 'success',
                'terbit-lepas': 'warning',
                siap: 'info',
                draft: 'warning',
                obsolete: 'brand',
            }[this.keadaanVersi];
        },
        ikonModal() {
            return {
                batal: 'x-circle-fill',
                berlaku: 'patch-check-fill',
                'terbit-lepas': 'exclamation-triangle-fill',
                siap: 'send-check-fill',
                draft: 'pencil-square',
                obsolete: 'clock-history',
            }[this.keadaanVersi];
        },
        tahapNamaLengkap() {
            if (this.isCancelled) return 'Dibatalkan (Arsip)';

            return this.versiData?.Tahap_Nama || {
                berlaku: 'Versi Berlaku',
                'terbit-lepas': 'Terbit Tanpa Penunjuk Aktif',
                siap: 'Siap Diterbitkan',
                draft: 'Draft Pengerjaan',
                obsolete: 'Arsip Riwayat',
            }[this.keadaanVersi];
        },
        daftarPengesah() {
            return this.detailBackend?.Pengesah || this.versiData?.Pengesah || [];
        },
        berkasKonten() {
            return this.detailBackend?.Berkas || null;
        },
        /** Form kosong dokumen FORM, berurutan seperti yang disusun admin. */
        formKosong() {
            const l = this.detailBackend?.Lampiran;
            return Array.isArray(l) ? l : [];
        },
        reviewList() {
            return this.detailBackend?.Review || [];
        },
        /**
         * Putaran review dengan lampiran yang alamatnya benar-benar bisa dibuka.
         *
         * Server mengirim DUA field per lampiran: `Path_Berkas`, jalur objek di
         * GCS, dan `Url`, alamat bertanda tangan berumur 20 menit. Layar ini
         * memakai `Path_Berkas` — jalur seperti `sop/review-lampiran/2026/08/…`
         * yang oleh browser dibaca sebagai alamat relatif terhadap halaman.
         * Akibatnya thumbnail selalu gagal dimuat dan pratinjaunya membuka
         * alamat yang tak pernah ada.
         *
         * `Url` bisa null bila penandatanganan gagal (mis. kredensial GCS
         * bermasalah). Lampiran seperti itu ditampilkan tanpa aksi, bukan
         * sebagai tombol yang pasti gagal.
         */
        reviewTampil() {
            const ikonUntuk = (tipe) => {
                if (tipe.startsWith('image/')) return 'file-earmark-image';
                if (tipe === 'application/pdf') return 'file-earmark-pdf';
                if (tipe.includes('word')) return 'file-earmark-word';
                if (tipe.includes('sheet') || tipe.includes('excel')) return 'file-earmark-spreadsheet';

                return 'file-earmark';
            };

            return this.reviewList.map((r) => ({
                ...r,
                LampiranTampil: (r.Lampiran || []).map((l, i) => {
                    const tipe = l.Tipe_Berkas || '';

                    return {
                        id: l.Id_Lampiran || `${r.Id_Review}-${i}`,
                        url: l.Url || null,
                        nama: l.Nama_Berkas || 'Lampiran review',
                        tipe,
                        gambar: tipe.startsWith('image/'),
                        ikon: ikonUntuk(tipe),
                    };
                }),
            }));
        },
        isKuis() {
            return this.dokumenData?.Tipe === 'KUIS' || this.versiData?.Tipe === 'KUIS';
        },
        namaBerkas() {
            return this.berkasKonten?.judul || this.versiData?.Konten_Nama || 'Berkas Dokumen Resmi (PDF)';
        },
        ringkasanLampiranLabel() {
            if (this.isKuis) return this.versiData?.Nama_Ujian || 'Modul Ujian Kuis';
            if (this.versiData?.Punya_Berkas || this.berkasKonten?.url || this.versiData?.Konten_Nama) return 'PDF Terlampir';
            return 'Tanpa Berkas';
        },
        /**
         * Ubin masa berlaku pada hero.
         *
         * Dulu ubin ini berlabel "Masa Berlaku" tetapi mencetak Tanggal_Berlaku
         * — tanggal MULAI. Admin membacanya sebagai "berlaku sampai", dan versi
         * yang sudah lewat masa berlakunya berbulan-bulan tetap tampak sehat.
         * Yang ditanyakan orang di ubin ini adalah sampai kapan, dan apakah
         * sudah lewat.
         *
         * Keadaannya datang dari server (SopVersiTanggal), bukan dihitung di
         * sini: ambang "sebentar lagi" harus sama dengan yang dipakai laporan
         * Export Studio, dan menyalin angkanya ke layar berarti suatu hari
         * keduanya menjawab berbeda untuk versi yang sama.
         */
        masaBerlaku() {
            const tanggal = this.versiData?.Tanggal_Kadaluarsa;
            const info = this.versiData?.Keadaan_Masa_Berlaku;

            if (this.isCancelled) {
                return {
                    nilai: 'Dibatalkan',
                    keterangan: null,
                    ikon: 'x-circle-fill',
                    kelasIkon: 'bg-light text-muted',
                    kelasTeks: 'text-muted',
                };
            }

            /* Tanpa info server, layar hanya boleh menyebut tanggalnya. */
            if (!info) {
                return {
                    nilai: tanggal ? this.formatTanggal(tanggal) : 'Tanpa masa berlaku',
                    keterangan: null,
                    ikon: 'clock-history',
                    kelasIkon: 'bg-light text-muted',
                    kelasTeks: '',
                };
            }

            const sisa = info.sisa_hari;
            /* Nol hari bukan "sisa 0 hari" — hari ini adalah hari terakhirnya,
               dan kalimat itulah yang membuat admin bertindak hari ini juga. */
            const sisaLabel = sisa === null
                ? null
                : (sisa === 0 ? 'Hari terakhir berlaku' : `Sisa ${sisa} hari`);
            const gaya = {
                Kadaluarsa: {
                    ikon: 'calendar-x-fill',
                    kelasIkon: 'bg-danger-soft text-danger',
                    kelasTeks: 'text-danger',
                    keterangan: sisa === null ? null : `Lewat ${Math.abs(sisa)} hari`,
                },
                Kedaluwarsa: {
                    ikon: 'calendar-x-fill',
                    kelasIkon: 'bg-danger-soft text-danger',
                    kelasTeks: 'text-danger',
                    keterangan: sisa === null ? null : `Lewat ${Math.abs(sisa)} hari`,
                },
                Berlaku: {
                    ikon: 'calendar-check-fill',
                    kelasIkon: 'bg-success-soft text-success',
                    kelasTeks: 'text-success',
                    keterangan: sisaLabel,
                },
                'Tanpa masa berlaku': {
                    ikon: 'infinity',
                    kelasIkon: 'bg-light text-muted',
                    kelasTeks: 'text-muted',
                    keterangan: null,
                },
            }[info.keadaan] || {
                /* Sisanya adalah "Akan kedaluwarsa (<= N hari)". Teksnya membawa
                   ambang dari server, jadi tak boleh dicocokkan huruf per huruf. */
                ikon: 'calendar-event-fill',
                kelasIkon: 'bg-amber-soft text-warning',
                kelasTeks: 'text-warning',
                keterangan: sisaLabel,
            };

            return {
                nilai: tanggal ? this.formatTanggal(tanggal) : info.keadaan,
                ...gaya,
            };
        },
        statMasaBerlakuSentiment() {
            if (this.isCancelled) return 'neutral';
            const info = this.versiData?.Keadaan_Masa_Berlaku;
            if (!info) return 'neutral';
            if (info.keadaan === 'Kadaluarsa' || info.keadaan === 'Kedaluwarsa') return 'danger';
            if (info.keadaan === 'Berlaku') return 'success';
            if (info.keadaan === 'Tanpa masa berlaku') return 'neutral';
            return 'warning';
        },
        /** Baris identitas dokumen induk — kosongnya ditandai, bukan disamarkan. */
        identitasDokumen() {
            const d = this.dokumenData || {};
            /* "Belum diisi" adalah pernyataan tentang dokumennya, dan hanya
               boleh diucapkan kalau datanya memang sudah di tangan. Tanpa
               detail dari server, sel kosong berarti "belum diambil". */
            const labelKosong = this.detailBackend ? 'Belum diisi' : 'Tidak tersedia';

            return [
                ['Nomor Dokumen Resmi', d.Nomor_SOP_Resmi],
                ['Nomor Dokumen', d.No_Dokumen],
                ['Jenis Dokumen', d.Jenis_Dokumen_Nama],
                ['Tipe Materi', d.Tipe],
                ['Departemen Pemilik', d.Departemen_Nama],
                ['Entitas Pemilik', d.Entitas_Nama],
            ].map(([label, nilai]) => ({
                label,
                nilai: nilai || labelKosong,
                kosong: !nilai,
            }));
        },
        /**
         * Pengesah menurut urutan tanda tangan.
         *
         * Server sudah mengurutkannya, tetapi nomornya tak pernah sampai ke
         * layar — padahal blok pengesahan dokumen resmi dibaca berurutan, dan
         * daftar tanpa nomor tak bisa dicocokkan dengan lembar tanda tangannya.
         */
        pengesahTerurut() {
            return [...this.daftarPengesah]
                .sort((a, b) => (a.Urutan ?? 0) - (b.Urutan ?? 0))
                .map((p, i) => ({
                    ...p,
                    _urut: p.Urutan ?? i + 1,
                    _key: p.Id_SOP_Item_Versi_Pengesah ?? `${p.Peran}-${i}`,
                }));
        },
        /**
         * Lima langkah tata kelola beserta keadaannya.
         *
         * Server sudah menghitung semuanya — keadaan, tanggal, ringkasan isi,
         * dan kelengkapan — lalu layar ini membuang seluruhnya. Yang tersisa
         * bagi admin cuma nama tahap di kepala modal, yang tidak menjawab
         * "langkah mana yang menahan versi ini".
         */
        alurLangkah() {
            const gaya = {
                selesai: { ikon: 'check-lg', label: 'Selesai', variant: 'success' },
                berjalan: { ikon: 'play-fill', label: 'Berjalan', variant: 'info' },
                hold: { ikon: 'pause-fill', label: 'Ditahan', variant: 'warning' },
                cancel: { ikon: 'x-lg', label: 'Dibatalkan', variant: 'danger' },
                belum: { ikon: 'dash-lg', label: 'Belum', variant: 'neutral' },
            };

            return (this.detailBackend?.Alur || []).map((l) => {
                const g = gaya[l.Keadaan] || gaya.belum;

                return {
                    ...l,
                    _ikon: g.ikon,
                    _keadaanLabel: g.label,
                    _keadaanVariant: g.variant,
                };
            });
        },
        alurRingkasLabel() {
            const total = this.alurLangkah.length;
            if (!total) return null;
            const selesai = this.alurLangkah.filter((l) => l.Keadaan === 'selesai').length;

            return `${selesai}/${total} langkah`;
        },
        /**
         * Linimasa sebagai data, bukan sembilan blok markup kembar.
         *
         * Milestone yang belum tercapai IKUT DITAMPILKAN — urutan tata kelola
         * baru terbaca kalau yang belum terjadi juga kelihatan — tetapi
         * dibedakan lewat `tercapai`, karena sebelumnya "—" tampil dengan bobot
         * huruf yang sama dengan tanggal sungguhan.
         *
         * Ditarik dan Terakhir Diubah hanya muncul bila ada isinya: keduanya
         * bukan tahap yang ditunggu, jadi barisnya yang kosong cuma menambah
         * dua "—" tanpa memberi tahu apa pun.
         */
        milestones() {
            const v = this.versiData || {};
            const daftar = [
                { judul: 'Dibuat Pada', tanggal: v.Created_At, dot: 'primary', jam: true, sub: this.labelPelaku(v.Dibuat_Oleh_Nama) },
                { judul: 'Pengajuan / Permintaan', tanggal: v.Tanggal_Permintaan, dot: 'brand' },
                { judul: 'Respon Review Mutu', tanggal: v.Tanggal_Respon, dot: 'purple' },
                { judul: 'Persetujuan Tuntas', tanggal: v.Tanggal_Akhir_Approval || v.Tanggal_Pengesahan, dot: 'success' },
                { judul: 'Mulai Berlaku', tanggal: v.Tanggal_Berlaku, dot: 'brand-active', kelasNilai: 'text-primary' },
                { judul: 'Publikasi / Terbit', tanggal: v.Tanggal_Publish, dot: 'success-active', kelasNilai: 'text-success' },
                { judul: 'Peninjauan Berikutnya', tanggal: v.Tanggal_Review_Berikutnya, dot: 'warning', sub: v.Keadaan_Review?.keadaan },
                { judul: 'Kedaluwarsa Dokumen', tanggal: v.Tanggal_Kadaluarsa, dot: 'danger', sub: v.Keadaan_Masa_Berlaku?.keadaan },
                { judul: 'Ditarik / Obsolete', tanggal: v.Tanggal_Obsolete, dot: 'slate', opsional: true },
                { judul: 'Terakhir Diubah', tanggal: v.Updated_At, dot: 'slate', jam: true, opsional: true, sub: this.labelPelaku(v.Diubah_Oleh_Nama) },
            ];

            return daftar
                .filter((m) => !m.opsional || m.tanggal)
                .map((m) => ({
                    ...m,
                    tercapai: Boolean(m.tanggal),
                    nilai: m.tanggal
                        ? (m.jam ? this.formatTanggalJam(m.tanggal) : this.formatTanggal(m.tanggal))
                        : 'Belum tercatat',
                    kelasNilai: m.tanggal ? (m.kelasNilai || '') : 'text-muted fw-normal',
                    sub: m.tanggal ? m.sub : null,
                }));
        },
        jumlahMilestoneTercapai() {
            return this.milestones.filter((m) => m.tercapai).length;
        },
        tabList() {
            return [
                { key: 'ringkasan', id: 'ringkasan', label: 'Ringkasan & Berkas', icon: 'file-earmark-text' },
                {
                    key: 'pengesahan',
                    id: 'pengesahan',
                    label: 'Matriks Pengesahan',
                    icon: 'person-badge',
                    badge: this.daftarPengesah.length ? `${this.daftarPengesah.length}` : null,
                    badgeVariant: 'neutral',
                },
                {
                    key: 'review',
                    id: 'review',
                    label: 'Review Mutu',
                    icon: 'shield-check',
                    badge: this.reviewList.length ? `${this.reviewList.length}` : null,
                    badgeVariant: 'purple',
                },
                {
                    key: 'audit',
                    id: 'audit',
                    label: 'Alur & Linimasa',
                    icon: 'clock-history',
                    badge: this.alurRingkasLabel,
                    badgeVariant: 'neutral',
                },
            ];
        },
    },
    watch: {
        modelValue(buka) {
            if (buka) {
                this.tabAktif = 'ringkasan';
                this.muatDetailVersiLengkap();
            } else {
                this.detailBackend = null;
                this.galatDetail = null;
            }
        },
        versi() {
            if (this.modelValue) {
                this.muatDetailVersiLengkap();
            }
        },
    },
    methods: {
        /*
         * Catatan review ditampilkan lewat v-html. Sampai perubahan ini ia
         * dirender MENTAH di sini, padahal LangkahReview.vue menyanitasi field
         * yang SAMA dengan alasan yang ditulis terang-terangan: isi tersimpan
         * bisa berasal dari jalur lain (seeder, impor, perbaikan lewat SQL).
         */
        sanitasiSop,

        getFormFileIcon(ext) {
            const e = (ext || '').toLowerCase();
            if (e.includes('pdf')) return 'bi-file-earmark-pdf-fill';
            if (e.includes('doc') || e.includes('word')) return 'bi-file-earmark-word-fill';
            if (e.includes('xls') || e.includes('sheet') || e.includes('excel')) return 'bi-file-earmark-excel-fill';
            if (['png', 'jpg', 'jpeg', 'webp', 'gif'].includes(e)) return 'bi-file-earmark-image-fill';
            return 'bi-file-earmark-text-fill';
        },

        formatTanggal(d) {
            if (!d) return '—';
            try {
                const dt = new Date(d);
                if (Number.isNaN(dt.getTime())) return d;
                return dt.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                });
            } catch {
                return d;
            }
        },
        /** Tanggal beserta jam — untuk jejak sistem, yang menitnya ikut berarti. */
        formatTanggalJam(d) {
            if (!d) return '—';
            try {
                const dt = new Date(d);
                if (Number.isNaN(dt.getTime())) return d;

                return `${dt.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                })} · ${dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
            } catch {
                return d;
            }
        },
        /**
         * Nama pelaku pada baris jejak.
         *
         * Dikosongkan bila server tak mengirim namanya. Sebelumnya baris ini
         * mencetak Created_By apa adanya — angka Id_Users — sehingga jejak
         * auditnya berbunyi "Oleh: 4".
         */
        labelPelaku(nama) {
            return nama ? `Oleh: ${nama}` : null;
        },
        getInitials(name) {
            if (!name) return '—';
            return name
                .split(' ')
                .slice(0, 2)
                .map((n) => n[0])
                .join('')
                .toUpperCase();
        },
        async muatDetailVersiLengkap() {
            const raw = this.versi?.raw || this.versi;
            const id = raw?.Id_SOP_Item_Versi;
            if (!id) {
                this.galatDetail = 'Versi ini tidak membawa penanda yang bisa dipakai memuat detailnya.';

                return;
            }

            this.memuatDetail = true;
            this.galatDetail = null;
            try {
                const res = await axios.get(`${API}/versi/${id}/detail`);
                this.detailBackend = res.data?.result || null;
                if (!this.detailBackend) {
                    this.galatDetail = 'Server tidak mengembalikan detail versi.';
                }
            } catch (err) {
                /* Jatuh ke data prop tetap benar sebagai jalan mundur, tetapi
                   HARUS terlihat. Diam di sini membuat pengesah dan review yang
                   gagal diambil tampil sebagai "belum ada" — kalimat yang
                   dibaca admin sebagai keadaan dokumen, bukan sebagai galat. */
                this.detailBackend = null;
                this.galatDetail = err?.response?.data?.message
                    || 'Permintaan ke server gagal.';
            } finally {
                this.memuatDetail = false;
            }
        },
        async bukaViewerBerkas() {
            if (this.berkasKonten && this.berkasKonten.url) {
                this.viewerData = {
                    url: this.berkasKonten.url,
                    name: this.berkasKonten.judul || this.namaBerkas,
                    type: this.berkasKonten.mime || 'application/pdf',
                };
                this.viewerFiles = [this.viewerData];
                this.viewerIndex = 0;
                this.viewerBuka = true;
                return;
            }

            const raw = this.versiData;
            const id = raw?.Id_SOP_Item_Versi;
            if (!id) return;

            this.memuatBerkas = true;
            try {
                const r = await axios.get(`${API}/versi/${id}/konten`);
                const k = r.data?.result;
                if (!k || !k.url) throw new Error('Berkas tidak ditemukan.');

                this.viewerData = {
                    url: k.url,
                    name: k.judul || this.namaBerkas,
                    type: k.mime || 'application/pdf',
                };
                this.viewerFiles = [this.viewerData];
                this.viewerIndex = 0;
                this.viewerBuka = true;
            } catch (e) {
                // error fallback
            } finally {
                this.memuatBerkas = false;
            }
        },
        /* Form kosong diunduh lewat berkas konten admin (signed URL 15 menit,
           sebagai attachment) — sama seperti pratinjau berkas di wizard. */
        async unduhFormKosong(l) {
            this.mengunduhFormKosong = l.Id_Lampiran;
            try {
                const r = await axios.get(`${API}/konten/${l.Id_Master_Konten}/berkas?unduh=1`);
                const url = r.data?.result?.url;
                if (!url) throw new Error('Berkas tidak ditemukan.');
                const a = document.createElement('a');
                a.href = url;
                a.download = l.Judul || 'form-kosong';
                a.target = '_blank';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || e?.message || 'Gagal mengunduh form kosong.');
            } finally {
                this.mengunduhFormKosong = null;
            }
        },
        async unduhBerkas() {
            if (this.berkasKonten && this.berkasKonten.url) {
                const a = document.createElement('a');
                a.href = this.berkasKonten.url;
                a.download = this.berkasKonten.judul || this.namaBerkas || 'dokumen.pdf';
                a.target = '_blank';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                return;
            }

            const raw = this.versiData;
            const id = raw?.Id_SOP_Item_Versi;
            if (!id) return;

            this.memuatUnduh = true;
            try {
                const r = await axios.get(`${API}/versi/${id}/konten?unduh=1`);
                const k = r.data?.result;
                if (!k || !k.url) throw new Error('Berkas tidak ditemukan.');

                const a = document.createElement('a');
                a.href = k.url;
                a.download = k.judul || this.namaBerkas || 'dokumen.pdf';
                a.target = '_blank';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            } catch (e) {
                // error fallback
            } finally {
                this.memuatUnduh = false;
            }
        },
        /**
         * Buka pratinjau lampiran review.
         *
         * Menerima daftar yang SUDAH dinormalkan `reviewTampil`, jadi `url` di
         * sini pasti alamat bertanda tangan — bukan jalur objek GCS. Lampiran
         * tanpa url disaring lebih dulu supaya panah "berikutnya" di viewer tak
         * pernah mendarat di berkas yang tak bisa dimuat; indeks awalnya
         * dihitung ulang terhadap daftar yang sudah disaring itu.
         */
        bukaViewerReview(lampiranList, idx) {
            const bisa = lampiranList.filter((l) => l.url);
            if (!bisa.length) return;

            const target = lampiranList[idx];
            const mulai = Math.max(0, bisa.indexOf(target));

            this.viewerFiles = bisa.map((l) => ({
                url: l.url,
                name: l.nama,
                type: l.tipe || 'image/jpeg',
            }));
            this.viewerIndex = mulai;
            this.viewerData = this.viewerFiles[mulai];
            this.viewerBuka = true;
        },
        navigasiKeWizard() {
            const dokId = this.idDokumen || this.dokumenData?.Id_SOP_Dokumen || this.versiData?.Id_SOP_Dokumen;
            const vId = this.versiData?.Id_SOP_Item_Versi;
            if (dokId && vId) {
                this.$emit('update:modelValue', false);
                router.visit(`/sop/admin/dokumen/${dokId}/versi/${vId}`);
            }
        },
    },
};
</script>

<style scoped>
/* ── Tahap Pill in Header ── */
.sop-detail-tahap-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--tc, #475569);
    background: color-mix(in srgb, var(--tc, #475569) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--tc, #475569) 25%, transparent);
}

/* ── Soft Background Accents ── */
.bg-brand-soft {
    background: #eff6ff;
    color: #2563eb;
}
.bg-success-soft {
    background: #ecfdf5;
    color: #059669;
}
.bg-purple-soft {
    background: #f5f3ff;
    color: #7c3aed;
}
.bg-amber-soft {
    background: #fffbeb;
    color: #d97706;
}
.bg-danger-soft {
    background: #fef2f2;
    color: #dc2626;
}

/* ── Quote Box ── */
.sop-quote-box {
    padding: 0.85rem 1.1rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-left: 3.5px solid var(--hcis-brand-500, #2563eb);
    font-size: 0.84375rem;
    color: #334155;
    line-height: 1.6;
    white-space: pre-line;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}

/* ── Empty Box ── */
.sop-empty-box {
    border-radius: var(--hcis-radius-md, 8px);
    background: #fcfcfd;
    border: 1.5px dashed #cbd5e1;
}

/* ── Identitas Induk 3-Column Grid ── */
.sop-detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}

@media (max-width: 48rem) {
    .sop-detail-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 30rem) {
    .sop-detail-grid {
        grid-template-columns: 1fr;
    }
}

.sop-detail-tile {
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.sop-detail-tile:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.sop-detail-tile__label {
    font-size: 0.6875rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1.2;
}

.sop-detail-tile__val {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.3;
}

/* ── File Banner ── */
.sop-file-banner {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.sop-file-banner:hover {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 3px 8px -1px rgba(15, 23, 42, 0.05);
}

.sop-file-icon-box {
    display: grid;
    place-items: center;
    width: 3.25rem;
    height: 3.25rem;
    flex-shrink: 0;
    border-radius: var(--hcis-radius-md, 8px);
}

/* ── Sah Banner (Pengesahan Utama) ── */
.sop-sah-banner {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
}

.sop-sah-icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    background: #dcfce7;
    display: grid;
    place-items: center;
}

/* ── Signer Row & Pejabat Pengesah ── */
.sop-signer-row {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1);
}

.sop-signer-row:hover {
    border-color: #cbd5e1;
    box-shadow: 0 3px 10px -2px rgba(15, 23, 42, 0.05);
    transform: translateY(-1px);
}

.sop-signer-urut {
    width: 1.625rem;
    height: 1.625rem;
    border-radius: 50%;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #2563eb;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}

.sop-signer-avatar {
    width: 2.375rem;
    height: 2.375rem;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 0.75rem;
    font-weight: 700;
    background: #f1f5f9;
    color: #475569;
    flex-shrink: 0;
    border: 1.5px solid #e2e8f0;
}

.sop-signer-avatar.is-disetujui {
    background: #dcfce7;
    color: #15803d;
    border-color: #bbf7d0;
}

.sop-signer-avatar.is-diperiksa {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #bfdbfe;
}

.sop-signer-avatar.is-disiapkan {
    background: #ede9fe;
    color: #6d28d9;
    border-color: #ddd6fe;
}

.sop-peran-chip {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    background: #f1f5f9;
    color: #475569;
}

.sop-peran-chip.is-disetujui {
    background: #dcfce7;
    color: #15803d;
}

.sop-peran-chip.is-diperiksa {
    background: #eff6ff;
    color: #1d4ed8;
}

.sop-peran-chip.is-disiapkan {
    background: #ede9fe;
    color: #6d28d9;
}

/* ── Review Card & Thumbnails ── */
.sop-review-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1);
}

.sop-review-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 3px 10px -2px rgba(15, 23, 42, 0.05);
}

.sop-review-narasi {
    word-break: break-word;
    overflow-wrap: break-word;
    max-height: 240px;
    overflow-y: auto;
    padding: 0.7rem 0.9rem;
    background: #f8fafc;
    border-radius: 6px;
    border-left: 3.5px solid #8b5cf6;
}

.sop-review-thumb {
    width: 4rem;
    height: 4rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: transform 0.15s ease, border-color 0.15s ease;
}

.sop-review-thumb:hover {
    transform: scale(1.04);
    border-color: #8b5cf6;
}

.sop-review-thumb__ikon {
    display: grid;
    place-items: center;
    width: 100%;
    height: 100%;
    font-size: 1.5rem;
    color: #94a3b8;
}

.sop-review-thumb.is-mati {
    border-style: dashed !important;
    opacity: 0.6;
    cursor: not-allowed;
}

.sop-thumb-hover {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    opacity: 0;
    transition: opacity 0.15s ease;
}

.sop-review-thumb:hover .sop-thumb-hover {
    opacity: 1;
}

/* ── Alur Lima Langkah ── */
.sop-alur-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
}

.sop-alur-item {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.6rem 0;
}

.sop-alur-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 1rem;
    top: 2.35rem;
    bottom: -0.35rem;
    width: 2px;
    background: #e2e8f0;
}

.sop-alur-item.is-selesai:not(:last-child)::before {
    background: #10b981;
}

.sop-alur-marker {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    font-size: 0.8125rem;
    background: #f8fafc;
    color: #94a3b8;
    border: 2px solid #cbd5e1;
    z-index: 1;
    transition: all 0.2s ease;
}

.sop-alur-item.is-selesai .sop-alur-marker {
    background: #ecfdf5;
    color: #059669;
    border-color: #10b981;
}

.sop-alur-item.is-berjalan .sop-alur-marker {
    background: #eff6ff;
    color: #2563eb;
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
}

.sop-alur-item.is-hold .sop-alur-marker {
    background: #fffbeb;
    color: #d97706;
    border-color: #f59e0b;
}

.sop-alur-item.is-cancel .sop-alur-marker {
    background: #fef2f2;
    color: #dc2626;
    border-color: #ef4444;
}

.sop-alur-body {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    padding-top: 0.15rem;
}

.sop-alur-nama {
    font-size: 0.84375rem;
    font-weight: 700;
    color: #0f172a;
}

.sop-alur-item.is-belum .sop-alur-nama {
    color: #94a3b8;
}

.sop-alur-meta {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
    font-size: 0.71875rem;
    color: #64748b;
}

.sop-alur-tanggal {
    color: #94a3b8;
}

/* ── Audit Timeline Grid ── */
.sop-audit-timeline-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

@media (max-width: 40rem) {
    .sop-audit-timeline-grid {
        grid-template-columns: 1fr;
    }
}

.sop-audit-milestone {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0.95rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 8px);
    transition: all 0.15s ease;
    min-width: 0;
}

.sop-audit-milestone:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.04);
}

.sop-audit-milestone__dot {
    width: 0.65rem;
    height: 0.65rem;
    border-radius: 50%;
    margin-top: 0.35rem;
    flex-shrink: 0;
}

.sop-audit-milestone__dot.bg-primary { background: #6366f1; }
.sop-audit-milestone__dot.bg-brand { background: #3b82f6; }
.sop-audit-milestone__dot.bg-purple { background: #8b5cf6; }
.sop-audit-milestone__dot.bg-success { background: #10b981; }
.sop-audit-milestone__dot.bg-brand-active { background: #0284c7; }
.sop-audit-milestone__dot.bg-success-active { background: #059669; }
.sop-audit-milestone__dot.bg-warning { background: #f59e0b; }
.sop-audit-milestone__dot.bg-danger { background: #ef4444; }
.sop-audit-milestone__dot.bg-slate { background: #94a3b8; }

.sop-audit-milestone__info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.sop-audit-milestone__title {
    font-size: 0.6875rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1.2;
}

.sop-audit-milestone__val {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

.sop-audit-milestone__sub {
    font-size: 0.71875rem;
    color: #94a3b8;
}

.sop-audit-milestone.is-kosong {
    background: #fcfcfd;
    border-style: dashed;
    border-color: #e2e8f0;
    box-shadow: none;
}

.sop-audit-milestone.is-kosong .sop-audit-milestone__dot {
    background: #cbd5e1 !important;
}

.sop-audit-milestone.is-kosong .sop-audit-milestone__title {
    color: #94a3b8;
}
</style>

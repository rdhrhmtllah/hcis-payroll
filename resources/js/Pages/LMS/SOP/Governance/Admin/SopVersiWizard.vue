<template>
    <div class="container-fluid px-0 sop-scope sop-master-dokumen sop-versi-wizard">
        <!-- ══ 1. EXECUTIVE LUMINOUS HEADER (HCIS SIGNATURE HEADER) ══ -->
        <HcisHeader
            :title="judulHalaman"
            :kicker="`Tata Kelola · ${nomorDokumenAtau('Dokumen Terkendali')}`"
            description="Kelola siklus hidup versi dokumen: pengajuan rancangan, catatan review mutu berkala, matriks pengesahan pejabat, hingga pemberkasan &amp; penerbitan resmi."
            :icon="idVersiKini ? 'patch-check-fill' : 'journal-plus'"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #breadcrumb>
                <HcisBreadcrumb
                    :items="breadcrumbItems"
                    separator="chevron"
                    size="sm"
                />
            </template>

            <template #badge>
                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                    <HcisBadge
                        :label="labelVersiPill"
                        variant="brand"
                        appearance="glass"
                        size="sm"
                        dot
                    />
                    <HcisBadge
                        v-if="alur.tahap?.Nama_Tahap"
                        :label="alur.tahap.Nama_Tahap"
                        :variant="stageSentiment"
                        appearance="solid"
                        size="sm"
                    />
                    <HcisBadge
                        v-if="kotor"
                        :label="`${jumlahPerubahan} Perubahan Belum Disimpan`"
                        variant="warning"
                        appearance="glass"
                        size="sm"
                        radar
                        dot
                    />
                </div>
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <HcisButton
                        variant="glass"
                        size="md"
                        leading-icon="arrow-clockwise"
                        label="Segarkan"
                        :loading="menyimpan || alur.memuat"
                        title="Segarkan status alur dan data versi"
                        aria-label="Segarkan status alur"
                        @click="segarkanAlur"
                    />

                    <HcisButton
                        variant="glass"
                        size="md"
                        leading-icon="arrow-left"
                        label="Kembali ke Master"
                        title="Kembali ke Master Dokumen"
                        aria-label="Kembali ke Master Dokumen"
                        @click="kembaliKeMaster"
                    />
                </div>
            </template>
        </HcisHeader>

        <!-- ══ 2. DOCUMENT GOVERNANCE HERO PASSPORT CARD (Aligned with Master Dokumen Detail Hero) ══ -->
        <div class="mdx-detail-hero-card mb-4">
            <div class="mdx-detail-hero">
                <!-- Top Metadata & Tags Dock -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2.5">
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <HcisBadge
                            :label="dokumen.Tipe || 'DOKUMEN'"
                            :variant="getTypeVariant(dokumen.Tipe)"
                            appearance="solid"
                            size="sm"
                        />
                        <span v-if="dokumen.Jenis_Nama" class="mdx-hero-tag jenis-tag">
                            <i class="bi bi-diagram-3-fill me-1"></i>{{ dokumen.Jenis_Kode || `L${dokumen.Jenis_Level || '-'}` }} · {{ dokumen.Jenis_Nama }}
                        </span>
                        <span v-else class="mdx-hero-tag neutral-tag">
                            <i class="bi bi-question-circle me-1"></i>Belum Diklasifikasi
                        </span>

                        <span v-if="idVersiKini" class="mdx-hero-tag active-tag">
                            <i class="bi bi-patch-check-fill me-1"></i>v{{ form.Versi_Ke != null ? String(form.Versi_Ke).padStart(2, '0') : '—' }} ({{ alur.tahap?.Nama_Tahap || 'Versi Aktif' }})
                        </span>
                        <span v-else class="mdx-hero-tag warning-tag">
                            <i class="bi bi-plus-circle me-1"></i>v{{ form.Versi_Ke != null ? String(form.Versi_Ke).padStart(2, '0') : 'Baru' }} (Draft Revisi Baru)
                        </span>

                        <span v-if="dokumen.Masa_Berlaku_Bulan" class="mdx-hero-tag neutral-tag" :title="`Masa Berlaku Default: ${dokumen.Masa_Berlaku_Bulan} Bulan`">
                            <i class="bi bi-calendar2-range me-1"></i>Siklus Berlaku: {{ dokumen.Masa_Berlaku_Bulan }} Bln
                        </span>
                        <span v-if="dokumen.Siklus_Review_Bulan" class="mdx-hero-tag neutral-tag" :title="`Siklus Review Default: ${dokumen.Siklus_Review_Bulan} Bulan`">
                            <i class="bi bi-arrow-repeat me-1"></i>Siklus Review: {{ dokumen.Siklus_Review_Bulan }} Bln
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <HcisButton
                            variant="secondary"
                            size="xs"
                            leading-icon="journal-bookmark"
                            label="Lihat Master Dokumen"
                            title="Buka rincian master dokumen"
                            @click="bukaMasterDokumen"
                        />
                    </div>
                </div>

                <!-- Document Title -->
                <h1 class="mdx-hero-title mb-2.5">{{ dokumen.Judul }}</h1>

                <!-- Monospace Crystal Number & Segment Anatomy Bar -->
                <div class="mdx-hero-number-strip mb-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <!-- Number Box + Copy Button -->
                        <div class="d-flex align-items-center gap-2">
                            <!-- NOMOR GABUNGAN TUJUH SEGMEN, bukan satu segmennya.
                                 Kotak ini dulu membaca Nomor_SOP_Resmi lalu jatuh ke
                                 No_Dokumen — dan No_Dokumen hanyalah segmen keenam,
                                 sehingga dokumen bernomor SK/HOL/IA/EHG/02/01/?? tampil
                                 sebagai "1". Urutannya kini sama persis dengan Master
                                 Dokumen: yang utuh, lalu yang berlubang, baru yang tersimpan. -->
                            <div
                                class="mdx-official-number font-monospace"
                                :class="{ 'is-incomplete': !dokumen.Nomor_Lengkap }"
                                :title="judulNomorDokumen"
                            >
                                <i class="bi bi-hash"></i>
                                <span>{{ nomorDokumen }}</span>
                            </div>

                            <button
                                v-if="nomorDokumen !== 'DRAFT-SOP'"
                                type="button"
                                class="btn btn-sm mdx-copy-btn"
                                title="Salin Nomor Dokumen ke Clipboard"
                                aria-label="Salin Nomor Dokumen ke Clipboard"
                                @click="salinNomorDokumen(nomorDokumen)"
                            >
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>

                        <!-- Segment Breakdown Pills -->
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <span v-if="dokumen.Entitas_Pemilik_Kode || dokumen.Entitas_Pemilik_Nama" class="mdx-seg-pill" :title="`Entitas Pemilik: ${dokumen.Entitas_Pemilik_Nama || dokumen.Entitas_Pemilik_Kode}`">
                                <i class="bi bi-building me-1"></i>{{ dokumen.Entitas_Pemilik_Kode || dokumen.Entitas_Pemilik_Nama }}
                            </span>
                            <span v-if="dokumen.Pemilik_Label" class="mdx-seg-pill" :title="`Departemen: ${dokumen.Pemilik_Label}`">
                                <i class="bi bi-diagram-3 me-1"></i>{{ dokumen.Pemilik_Label }}
                            </span>
                            <span v-if="dokumen.APQC_Kode" class="mdx-seg-pill" :title="`APQC: ${dokumen.APQC_Kode} · ${dokumen.APQC_Nama}`">
                                <i class="bi bi-bounding-box me-1"></i>PCF #{{ dokumen.APQC_Kode }}
                            </span>
                            <span v-if="dokumen.Entitas_Lingkup_Kode && dokumen.Entitas_Lingkup_Kode !== dokumen.Entitas_Pemilik_Kode" class="mdx-seg-pill" :title="`Ruang Lingkup: ${dokumen.Entitas_Lingkup_Nama || dokumen.Entitas_Lingkup_Kode}`">
                                <i class="bi bi-geo me-1"></i>Lingkup: {{ dokumen.Entitas_Lingkup_Kode }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 4-Fact Metrics Grid (HcisStatGroup cols=4) -->
                <HcisStatGroup :cols="4" gap="sm" class="mb-1">
                    <HcisStatCard
                        layout="compact"
                        label="Level Hirarki Mutu"
                        :value="dokumen.Jenis_Nama ? `${dokumen.Jenis_Kode ? `${dokumen.Jenis_Kode} · ` : ''}${dokumen.Jenis_Nama}` : 'Belum Diklasifikasi'"
                        caption="Piramid hirarki mutu"
                        icon="diagram-3-fill"
                        sentiment="brand"
                    />
                    <HcisStatCard
                        layout="compact"
                        label="Proses Bisnis APQC"
                        :value="dokumen.APQC_Kode ? `PCF #${dokumen.APQC_Kode} · ${dokumen.APQC_Nama}` : 'Belum Dipetakan'"
                        caption="Klasifikasi APQC PCF v7.3"
                        icon="bounding-box"
                        sentiment="purple"
                    />
                    <HcisStatCard
                        layout="compact"
                        label="Departemen Penanggung Jawab"
                        :value="dokumen.Pemilik_Label || 'Tanpa Departemen'"
                        :caption="`Entitas: ${dokumen.Entitas_Pemilik_Nama || dokumen.Entitas_Pemilik_Kode || '—'}`"
                        icon="diagram-2-fill"
                        sentiment="teal"
                    />
                    <HcisStatCard
                        layout="compact"
                        label="Ruang Lingkup Penerapan"
                        :value="dokumen.Entitas_Lingkup_Nama || dokumen.Entitas_Lingkup_Kode || 'Internal PT'"
                        :caption="`Siklus: ${dokumen.Masa_Berlaku_Bulan || 24} Bln Berlaku / ${dokumen.Siklus_Review_Bulan || 12} Bln Review`"
                        icon="geo-alt-fill"
                        sentiment="amber"
                    />
                </HcisStatGroup>
            </div>
        </div>

        <!-- ══ 3. GLOBAL FLOW ALERTS & WARNINGS ══ -->
        <HcisAlert
            v-if="alur.galat"
            variant="danger"
            appearance="subtle"
            class="mb-3"
            :description="alur.galat"
            closable
            @close="alur.galat = ''"
        />

        <!-- ══ 4. MOBILE VIEW SWITCHER (Screen <= 991px) ══ -->
        <div class="d-lg-none mb-3 mdx-mobile-nav">
            <div class="sop-seg w-100 shadow-sm">
                <button
                    type="button"
                    class="w-50 justify-content-center"
                    :class="{ active: mobileTab === 'ringkasan' }"
                    @click="mobileTab = 'ringkasan'"
                >
                    <i class="bi bi-list-check me-1"></i> Rel &amp; Ringkasan
                </button>
                <button
                    type="button"
                    class="w-50 justify-content-center"
                    :class="{ active: mobileTab === 'langkah' }"
                    @click="mobileTab = 'langkah'"
                >
                    <i class="bi bi-ui-checks-grid me-1"></i> Formulir Langkah
                </button>
            </div>
        </div>

        <!-- ══ 5. HORIZONTAL STEPPER RAIL ══ -->
        <VersiStepper
            :langkah="alur.langkah"
            :dibuka="alur.dibuka"
            :memuat="alur.memuat || memuatInisial"
            class="mb-3"
            @pilih="alur.buka($event)"
        />

        <!-- ══ 6. 2-COLUMN WORKSPACE GRID ══ -->
        <div class="mb-5 mdx-workspace-grid">
            <!-- ── LEFT COLUMN: SIDEBAR RINGKASAN PROGRESS & CHECKLIST ── -->
            <aside class="mdx-sidebar-wrap" :class="{ 'd-none d-lg-block': mobileTab === 'langkah' }">
                <VersiRingkasan
                    :versi-ke="form.Versi_Ke"
                    :langkah="alur.langkah"
                    :tahap="alur.tahap"
                    :baru="!idVersiKini"
                    :dokumen="dokumen"
                    :memuat="alur.memuat || memuatInisial"
                    @pilih="bukaLangkahDanAlih($event)"
                    @riwayat="kembaliKeMaster"
                />
            </aside>

            <!-- ── RIGHT COLUMN: ACTIVE STEP WORKSPACE ── -->
            <main class="mdx-detail-wrap" :class="{ 'd-none d-lg-block': mobileTab === 'ringkasan' }">
                <div class="d-lg-none mb-3">
                    <HcisButton
                        variant="secondary"
                        size="sm"
                        block
                        leading-icon="arrow-left"
                        label="Kembali ke Rel &amp; Ringkasan"
                        @click="mobileTab = 'ringkasan'"
                    />
                </div>

                <!-- Banner Arsip Pembatalan Versi -->
                <HcisAlert
                    v-if="bekuTotal"
                    variant="danger"
                    appearance="soft"
                    :icon="isCancelled ? 'x-circle-fill' : 'archive-fill'"
                    :title="isCancelled
                        ? 'Versi Dokumen Ini Telah Dibatalkan (Arsip Riwayat)'
                        : 'Versi Ini Sudah Digantikan Revisi Baru (Riwayat)'"
                    badge="Arsip / Read-Only"
                    size="sm"
                    glow
                    pattern="dots"
                    class="mb-3"
                >
                    Versi ini telah dibatalkan pada siklus tata kelola dan tersimpan secara permanen sebagai arsip audit trail. Seluruh catatan review, matriks pengesah, dan berkas lampiran tetap dapat ditinjau oleh admin dalam mode baca saja (read-only).
                </HcisAlert>

                <!-- State Memuat / Request: Sembunyikan detail form dan tampilkan skeleton -->
                <div v-if="memuatInisial || alur.memuat" class="d-flex flex-column gap-3" aria-busy="true" aria-live="polite">
                    <div class="p-3 rounded-3 border bg-white d-flex align-items-center gap-3">
                        <HcisSkeleton width="32px" height="32px" rounded="md" />
                        <div class="d-flex flex-column gap-1.5 flex-grow-1">
                            <HcisSkeleton width="40%" height="0.95rem" rounded="sm" />
                            <HcisSkeleton width="70%" height="0.75rem" rounded="sm" />
                        </div>
                    </div>

                    <HcisCard variant="outlined" sentiment="primary" size="sm">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2 flex-grow-1">
                                <HcisSkeleton width="28px" height="28px" rounded="md" />
                                <div class="d-flex flex-column gap-1 flex-grow-1">
                                    <HcisSkeleton width="30%" height="0.95rem" rounded="sm" />
                                    <HcisSkeleton width="50%" height="0.75rem" rounded="sm" />
                                </div>
                            </div>
                            <HcisSkeleton width="75px" height="22px" rounded="md" />
                        </div>

                        <div class="d-flex flex-column gap-3 pt-1">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <HcisSkeleton width="100%" height="75px" rounded="lg" />
                                </div>
                                <div class="col-12 col-md-6">
                                    <HcisSkeleton width="100%" height="75px" rounded="lg" />
                                </div>
                            </div>
                            <HcisSkeleton width="100%" height="80px" rounded="md" />
                        </div>
                    </HcisCard>

                    <HcisCard variant="outlined" sentiment="primary" size="sm">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <HcisSkeleton width="28px" height="28px" rounded="md" />
                            <div class="d-flex flex-column gap-1 flex-grow-1">
                                <HcisSkeleton width="25%" height="0.95rem" rounded="sm" />
                                <HcisSkeleton width="45%" height="0.75rem" rounded="sm" />
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <HcisSkeleton width="100%" height="36px" rounded="md" />
                            </div>
                            <div class="col-12 col-md-6">
                                <HcisSkeleton width="100%" height="36px" rounded="md" />
                            </div>
                        </div>
                    </HcisCard>
                </div>

                <section v-else class="sop-versi-wizard__isi">
                    <LangkahPengajuan
                        v-if="alur.kelompok === 'PENGAJUAN'"
                        v-model="form"
                        :terkunci="langkahTerkunci"
                        :boleh-ubah="bolehUbah"
                        :berubah="berubah"
                        :versi-sebelumnya="versiSebelumnya"
                        :next-versi-ke="nextVersiKe"
                    />
                    <LangkahReview
                        v-else-if="alur.kelompok === 'REVIEW'"
                        v-model:baris="review"
                        :id-versi="idVersiKini"
                        :terkunci="langkahTerkunci"
                        :boleh-ubah="bolehUbah"
                        :memuat="reviewMemuat"
                        :menyimpan="menyimpan"
                        :galat="reviewGalat"
                        :ada-perubahan="reviewKotor"
                        @simpan-sementara="simpanReview()"
                    />
                    <LangkahPengesahan
                        v-else-if="alur.kelompok === 'PENGESAHAN'"
                        v-model="form"
                        :terkunci="langkahTerkunci"
                        :boleh-ubah="bolehUbah"
                        :pakai-pengesahan="pakaiPengesahan"
                        :tipe="dokumen.Tipe"
                        :berubah="berubah"
                    />
                    <LangkahTinjauan
                        v-else-if="alur.kelompok === 'TINJAUAN'"
                        :nilai="form"
                        :review="review"
                        :dokumen="dokumen"
                        :is-kuis="isKuis"
                        :pakai-pengesahan="pakaiPengesahan"
                        :kurang="kurangUtama"
                        :sudah-terbit="sudahTerbit"
                        :versi-sebelumnya="versiSebelumnya"
                        @sunting="alur.buka($event)"
                    />
                    <LangkahPemberkasan
                        v-else
                        v-model="form"
                        :terkunci="langkahTerkunci"
                        :boleh-ubah="bolehUbah"
                        :is-kuis="isKuis"
                        :pakai-lampiran="pakaiLampiran"
                        :id-dokumen="idDokumen"
                        :ujian-list="ujianList"
                        :ujian-loading="ujianLoading"
                        :ujian-error="ujianError"
                        :berubah="berubah"
                        :masa-berlaku-bulan="dokumen.Masa_Berlaku_Bulan || 24"
                        :siklus-review-bulan="dokumen.Siklus_Review_Bulan || 12"
                        @pilih-berkas="bukaPemilih('berkas')"
                        @pilih-lampiran="bukaPemilih('lampiran')"
                    />
                </section>
            </main>
        </div>

        <!-- ══ 7. KAKI AKSI — DUA BARIS, SATU ATURAN PERATAAN TIAP BARIS ══
             Sebelumnya keadaan dan aksi berdesakan di SATU baris ber-space-between.
             Begitu melebar ke tiga kelompok, barisnya membungkus jadi dua potongan
             yang rata ke arah berbeda — dan keterangan "belum lengkap" disembunyikan
             di layar kecil (d-none d-md-inline), justru di layar yang paling butuh
             tahu kenapa tombolnya tak memajukan apa pun.

             Sekarang: baris KEADAAN di atas (muncul hanya bila ada yang perlu
             dikatakan, di semua ukuran layar), baris AKSI di bawah. Tepi kiri kaki
             mewarnai keadaan itu — kuning bila tertahan, hijau bila siap maju. -->
        <footer class="sop-versi-wizard__kaki" :class="kelasKaki">
            <!-- Wadahnya SELALU ada; isinya yang datang dan pergi. Daerah
                 aria-live yang baru lahir bersamaan dengan isinya kerap tak
                 dibacakan pembaca layar — yang diumumkan adalah perubahan DI
                 DALAM daerah yang sudah berdiri. Saat tak ada yang dikatakan ia
                 diruntuhkan lewat kelas, bukan lewat :empty — Vue meninggalkan
                 simpul komentar di sana, dan bergantung pada bagaimana whitespace
                 dipadatkan saat kompilasi adalah taruhan yang tak perlu. -->
            <div
                class="kaki-keadaan"
                :class="{ 'kaki-keadaan--sunyi': bekuTotal || (!kotor && !kurangUtama.length) }"
                aria-live="polite"
            >
                <span v-if="!bekuTotal && kotor" class="kaki-sinyal kaki-sinyal--kotor">
                    <i class="bi bi-record-circle" aria-hidden="true"></i>
                    <span>{{ jumlahPerubahan }} perubahan belum disimpan</span>
                </span>

                <span
                    v-if="!bekuTotal && kurangUtama.length"
                    id="kaki-penghalang"
                    class="kaki-sinyal kaki-sinyal--kurang"
                >
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <span>Belum lengkap: <strong>{{ kurangUtama.join(' · ') }}</strong></span>
                </span>
            </div>

            <div class="kaki-aksi">
                <!-- Aksi mundur & batal berdiri terpisah dari aksi maju, dipisah
                     garis rambut. Membatalkan versi tak boleh sebahu dengan tombol
                     yang paling sering ditekan. -->
                <div v-if="alur.aksiLain.length && !bekuTotal" class="kaki-aksi__kiri">
                    <HcisButton
                        v-for="a in alur.aksiLain"
                        :key="a.Jenis"
                        :variant="a.Jenis === 'BATALKAN' ? 'danger-quiet' : 'ghost'"
                        size="sm"
                        :leading-icon="a.Jenis === 'BATALKAN' ? 'x-circle' : 'arrow-counterclockwise'"
                        :label="a.Label"
                        :disabled="menyimpan || !idVersiKini"
                        @click="jalankanAksi(a)"
                    />
                </div>

                <div class="kaki-aksi__kanan">
                    <template v-if="bekuTotal">
                        <HcisBadge :label="labelBeku" variant="danger" size="sm" :icon="isCancelled ? 'x-circle-fill' : 'archive-fill'" />
                        <!-- Arsip tetap menerima pembetulan TANGGAL (21 Sep 2026),
                             jadi harus ada jalan menyimpannya. Tanpa tombol ini
                             kotak tanggalnya terbuka tapi tak pernah tersimpan. -->
                        <HcisButton
                            v-if="bisaSuntingTanggal"
                            variant="secondary"
                            size="sm"
                            leading-icon="calendar-check"
                            label="Simpan Tanggal"
                            :loading="menyimpan"
                            :disabled="menyimpan || memuatInisial || alur.memuat || !kotor"
                            title="Simpan pembetulan tanggal pada versi arsip"
                            @click="simpan()"
                        />
                        <HcisButton
                            variant="secondary"
                            size="sm"
                            leading-icon="arrow-left"
                            label="Kembali ke Master Dokumen"
                            @click="kembaliKeMaster"
                        />
                    </template>
                    <template v-else>
                        <HcisButton
                            v-if="aksiKini.Jenis !== 'SIMPAN'"
                            variant="secondary"
                            size="sm"
                            leading-icon="check2-circle"
                            label="Simpan Saja"
                            :loading="menyimpan"
                            :disabled="menyimpan || memuatInisial || alur.memuat"
                            title="Simpan perubahan tanpa memindahkan tahap"
                            @click="simpan()"
                        />

                        <!-- Tetap bisa ditekan saat tertahan: penolakan server
                             menyebut sebabnya dengan tepat, sedangkan tombol mati
                             tanpa penjelasan hanya menyisakan tebakan. Sebabnya
                             ditautkan ke baris keadaan supaya pembaca layar ikut
                             mendengarnya. -->
                        <HcisButton
                            :variant="siapMaju ? 'success' : 'primary'"
                            size="sm"
                            :leading-icon="ikonUtama"
                            :label="labelUtama"
                            :loading="menyimpan"
                            :disabled="menyimpan || memuatInisial || alur.memuat"
                            :aria-describedby="kurangUtama.length ? 'kaki-penghalang' : undefined"
                            @click="jalankanUtama"
                        />
                    </template>
                </div>
            </div>
        </footer>

        <!-- ══ 8. DIALOGS & MODALS ══ -->
        <KontenPickerModal
            v-if="!isKuis"
            v-model:visible="pickerOpen"
            :tipe-item="pickerTujuan === 'lampiran' ? 'LAMPIRAN_FORM' : dokumen.Tipe"
            @selected="onKonten"
        />

        <SopTerbitPratinjau
            :versi="pratinjau"
            @close="pratinjau = null"
            @berhasil="setelahTerbit"
        />
    </div>
</template>

<script>
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import {
    HcisHeader,
    HcisBreadcrumb,
    HcisCard,
    HcisButton,
    HcisBadge,
    HcisAlert,
    HcisStatGroup,
    HcisStatCard,
    HcisSkeleton,
    hcisConfirm,
    hcisMessage,
} from '@/components/ui';
import VersiStepper from './versi/VersiStepper.vue';
import VersiRingkasan from './versi/VersiRingkasan.vue';
import { nomorDokumenTampil } from '@/Pages/LMS/SOP/Shared/support/versi.js';
import { BLOK, blokBerlaku } from '@/Pages/LMS/SOP/Shared/support/constants';
import { namaOpsiUjian, petakanOpsiUjian } from '@/Pages/LMS/SOP/Shared/support/ujian';
import LangkahPengajuan from './versi/LangkahPengajuan.vue';
import LangkahReview from './versi/LangkahReview.vue';
import LangkahPengesahan from './versi/LangkahPengesahan.vue';
import LangkahPemberkasan from './versi/LangkahPemberkasan.vue';
import LangkahTinjauan from './versi/LangkahTinjauan.vue';
import SopTerbitPratinjau from '@/Pages/LMS/SOP/Shared/components/ui/SopTerbitPratinjau.vue';
import KontenPickerModal from '@/Pages/LMS/Training/Content/AdminTraining/components/KontenPickerModal.vue';
import { createAlur } from './versi/useVersiAlur.js';
import '../../Shared/support/sopTheme.css';

const API = '/api/v1/sop/admin/dokumen';

export default {
    name: 'SopVersiWizard',
    components: {
        HcisHeader,
        HcisBreadcrumb,
        HcisCard,
        HcisButton,
        HcisBadge,
        HcisAlert,
        HcisStatGroup,
        HcisStatCard,
        HcisSkeleton,
        VersiStepper,
        VersiRingkasan,
        LangkahPengajuan,
        LangkahReview,
        LangkahPengesahan,
        LangkahPemberkasan,
        LangkahTinjauan,
        SopTerbitPratinjau,
        KontenPickerModal,
    },
    props: {
        idDokumen: { type: String, required: true },
        idVersi: { type: String, default: null },
        dokumen: { type: Object, required: true },
        infoVersi: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            memuatInisial: true,
            alur: createAlur(),
            idVersiKini: this.idVersi,
            capVersi: null,
            fieldBolehUbah: null,
            /** Isi versi beku (arsip/riwayat); tanggalnya tetap bisa dibetulkan. */
            bekuIsi: null,
            form: this.kosong(),
            asli: null,
            menyimpan: false,
            pickerOpen: false,
            /* Satu pemilih Library untuk dua tujuan: berkas utama (contoh
               pengisian) atau menambah form kosong. */
            pickerTujuan: 'berkas',
            pratinjau: null,
            ujianList: [],
            ujianLoading: false,
            ujianError: '',
            mobileTab: 'langkah',

            /* CATATAN REVIEW TINGGAL DI SINI, bukan di LangkahReview. */
            review: [],
            reviewAsli: '[]',
            reviewMemuat: false,
            reviewGalat: '',
            reviewUntuk: null,
        };
    },
    computed: {
        isKuis() {
            return this.dokumen?.Tipe === 'KUIS';
        },
        /**
         * Dijawab oleh PETA PROFIL, bukan oleh daftar larangan yang ditulis di sini.
         *
         * Bentuk sebelumnya `!isKuis && Tipe !== 'FORM'` adalah daftar larangan:
         * ia memberi pengesahan kepada SETIAP tipe yang kebetulan tidak disebut
         * — dan VIDEO tidak disebut. Padahal profil resminya (cermin
         * SopTipeItem::PROFIL di server) hanya memberi VIDEO satu blok: berkas.
         * DML-2026-013 bahkan pernah dijalankan khusus untuk MENGHAPUS data
         * pengesahan pada item bertipe FORM, VIDEO, dan KUIS.
         *
         * Daftar izin tak bisa keliru dengan cara yang sama: tipe baru yang
         * belum terdaftar otomatis TIDAK mendapat pengesahan, bukan mendapatkannya.
         */
        /** Form kosong untuk diunduh karyawan — hanya tipe yang profilnya memakainya (FORM). */
        pakaiLampiran() {
            return blokBerlaku(this.dokumen?.Tipe, BLOK.LAMPIRAN);
        },
        pakaiPengesahan() {
            return blokBerlaku(this.dokumen?.Tipe, BLOK.PENGESAHAN);
        },
        versiSebelumnya() {
            return this.infoVersi?.Versi_Sebelumnya || null;
        },
        nextVersiKe() {
            if (this.infoVersi?.Next_Versi_Ke !== undefined && this.infoVersi?.Next_Versi_Ke !== null) {
                return this.infoVersi.Next_Versi_Ke;
            }
            return 0;
        },
        /**
         * SATU JAWABAN, DARI SERVER.
         *
         * Sebelumnya ini rantai tujuh OR yang SELURUH cabangnya meleset —
         * pertahanan berlapis yang tak satu pun lapisnya menyentuh sasaran:
         *
         *   tahap.Kode          → muatan mengirim `Kode_Tahap`, jadi undefined
         *   form.Status         → versi batal tetap berstatus 'DRAFT'
         *   Nama_Tahap ~ 'batal'→ nama tahapnya "Cancel", tak mengandung "batal"
         *   Kelompok langkah    → CANCEL tak punya Langkah, jadi tak pernah
         *                         muncul di daftar langkah sama sekali
         *
         * Hasilnya selalu false, dan wizard versi arsip terbuka penuh untuk
         * disunting. Rantai panjang membuatnya TERLIHAT dijaga berlapis, dan
         * itulah kenapa tak ada yang memeriksanya lagi.
         */
        isCancelled() {
            return this.alur.tahap?.Dibatalkan === true;
        },
        judulHalaman() {
            if (this.bekuTotal) {
                const vke = this.form.Versi_Ke != null ? `v${String(this.form.Versi_Ke).padStart(2, '0')}` : 'Versi';
                const keadaan = this.isCancelled ? 'Dibatalkan / Arsip' : 'Riwayat / Sudah Digantikan';

                return `Wizard Versi & Pengesahan · ${vke} (${keadaan})`;
            }
            if (!this.idVersiKini) {
                const vke = this.form.Versi_Ke != null ? `v${String(this.form.Versi_Ke).padStart(2, '0')}` : 'Baru';
                return `Wizard Versi & Pengesahan · ${vke} (Draft Baru)`;
            }
            const vke = this.form.Versi_Ke != null ? `v${String(this.form.Versi_Ke).padStart(2, '0')}` : 'Versi';
            return `Wizard Versi & Pengesahan · ${vke}`;
        },
        labelVersiPill() {
            if (this.bekuTotal) {
                const label = this.isCancelled ? 'Dibatalkan (Arsip)' : 'Riwayat (Digantikan)';

                return this.form.Versi_Ke != null
                    ? `v${String(this.form.Versi_Ke).padStart(2, '0')} · ${label}` : label;
            }
            if (!this.idVersiKini) {
                return this.form.Versi_Ke != null ? `v${String(this.form.Versi_Ke).padStart(2, '0')} · Draft Baru` : 'Draft Versi Baru';
            }
            return this.form.Versi_Ke != null ? `v${String(this.form.Versi_Ke).padStart(2, '0')}` : 'Draft Versi';
        },
        stageSentiment() {
            if (this.bekuTotal) return 'danger';
            const k = this.alur?.kelompok;
            switch (k) {
                case 'PENGAJUAN': return 'brand';
                case 'REVIEW': return 'purple';
                case 'PENGESAHAN': return 'teal';
                case 'PEMBERKASAN': return 'amber';
                case 'TINJAUAN': return 'success';
                default: return 'neutral';
            }
        },
        breadcrumbItems() {
            return [
                { label: 'Tata Kelola Dokumen', href: '/sop/admin/dokumen' },
                { label: 'Master Dokumen', href: '/sop/admin/dokumen' },
                {
                    label: this.dokumen?.Nomor_SOP_Resmi || this.dokumen?.Judul || 'Dokumen',
                    href: `/sop/admin/dokumen?dok=${this.idDokumen}`,
                },
                {
                    label: this.idVersiKini
                        ? (this.form.Versi_Ke != null ? `Versi ${this.form.Versi_Ke}${this.isCancelled ? ' (Arsip)' : ''}` : 'Sunting Versi')
                        : (this.form.Versi_Ke != null ? `Versi ${this.form.Versi_Ke} (Baru)` : 'Versi Baru'),
                },
            ];
        },
        /**
         * Langkah ini tak menerima pekerjaan lagi.
         *
         * Tetap menyala untuk arsip — yang dibuka pada arsip HANYA tanggal, dan
         * itu diatur per field lewat bolehUbah(), bukan dengan membuka langkahnya.
         */
        langkahTerkunci() {
            return this.alur.langkahDibuka?.Terkunci === true;
        },
        /**
         * Isi versi sudah beku — versi batal atau sudah jadi riwayat.
         *
         * Dibaca dari `Beku_Isi` milik server, bukan disimpulkan dari daftar
         * izin yang kosong: sejak 21 Sep 2026 arsip TETAP menerima pembetulan
         * tanggal, jadi daftarnya tak pernah kosong lagi. Menyimpulkannya dari
         * panjang daftar akan membuat arsip kehilangan spanduknya.
         *
         * `null` berarti muatannya belum tiba; jangan mengunci apa pun sebelum
         * jawabannya ada, sebab mengunci saat ragu membuat wizard yang lambat
         * memuat terlihat rusak.
         */
        terkunciPenuh() {
            return this.bekuIsi === true;
        },
        /**
         * Tak ada lagi yang bisa dikerjakan di sini — versi batal ATAU riwayat.
         *
         * Kaki halaman dulu hanya mengenal `isCancelled`, sehingga versi
         * OBSOLETE tetap menampilkan "Simpan Saja" dan tombol aksi utama.
         * Padahal keduanya sama-sama berakhir: yang satu keputusan membatalkan,
         * yang lain revisi yang sudah digantikan. Menawarkan Simpan pada
         * keduanya berarti menjanjikan pekerjaan yang server tolak di ujung.
         */
        bekuTotal() {
            return this.isCancelled || this.terkunciPenuh;
        },
        /** Kalimat yang menyebut SEBABNYA, bukan sekadar "tak bisa diubah". */
        labelBeku() {
            return this.isCancelled ? 'Dibatalkan — arsip riwayat' : 'Sudah digantikan — riwayat';
        },
        /** Apakah field ini boleh diubah pada keadaan versi sekarang? */
        bolehUbah() {
            const izin = this.fieldBolehUbah;
            const beku = this.bekuIsi === true;

            /* Daftarnya belum tiba: jangan mengunci apa pun — KECUALI pada
               arsip, yang hanya menerima tanggal. Menawarkan seluruh isian pada
               arsip lalu ditolak server adalah kegagalan yang paling
               membingungkan: sudah diketik, baru dibilang tak boleh. */
            return (field) => (Array.isArray(izin) ? izin.includes(field) : !beku);
        },
        /** Arsip yang masih menerima pembetulan tanggal; kaki halaman butuh tahu. */
        bisaSuntingTanggal() {
            return this.bekuIsi === true && Array.isArray(this.fieldBolehUbah) && this.fieldBolehUbah.length > 0;
        },
        aksiUtama() {
            return this.alur.aksiUtama
                || { Jenis: 'SIMPAN', Label: 'Simpan', Ke_Tahap: null, Ke_Langkah: null, Syarat_Kurang: [] };
        },
        aksiKini() {
            const antar = this.alur.aksiAntar;
            if (antar && this.alur.dibuka !== antar.Ke_Langkah) return antar;

            return this.aksiUtama;
        },
        labelUtama() {
            return this.aksiKini.Label;
        },
        ikonUtama() {
            return {
                SIMPAN_LANJUT: 'arrow-right-circle',
                LANJUT_LANGKAH: 'arrow-right-circle',
                TERBITKAN: 'broadcast',
            }[this.aksiKini.Jenis] || (this.idVersiKini ? 'check2-circle' : 'floppy');
        },
        kurangUtama() {
            return this.aksiUtama.Syarat_Kurang || [];
        },
        /* Urutan pilihnya tinggal di support/versi.js, dipakai bersama kartu
           ringkasan di bilah samping. Dua tempat di halaman yang sama pernah
           menyebut nomor berbeda untuk satu dokumen justru karena aturannya
           ditulis dua kali. */
        nomorDokumen() {
            return this.nomorDokumenAtau('DRAFT-SOP');
        },
        judulNomorDokumen() {
            if (this.dokumen?.Nomor_Lengkap) return 'Nomor dokumen resmi';

            const kurang = this.dokumen?.Nomor_Segmen_Kosong || [];
            if (!kurang.length) return 'Nomor dokumen sementara';

            /* Menyebut lubangnya, bukan sekadar menandai kotaknya kuning.
               "Revisi" berlubang karena belum ada versi yang terbit — itu
               keadaan yang wajar, bukan kesalahan pengisian, jadi kalimatnya
               menerangkan bukan menegur. */
            return `Nomor belum utuh — ${kurang.join(', ')} belum terisi. `
                + 'Segmen Revisi terisi sendiri saat versi pertama terbit.';
        },
        /**
         * Warna tepi kiri kaki — satu isyarat untuk satu keadaan, berurutan
         * dari yang paling menentukan. Versi yang sudah jadi arsip tak lagi
         * menawarkan apa pun, jadi keadaannya menang atas sisanya.
         */
        kelasKaki() {
            if (this.bekuTotal) return 'kaki--arsip';
            if (this.kurangUtama.length) return 'kaki--tertahan';
            if (this.siapMaju) return 'kaki--siap';

            return 'kaki--netral';
        },
        sudahTerbit() {
            return this.alur.langkah.some((l) => l.Kelompok === 'TINJAUAN' && l.Lengkap);
        },
        siapMaju() {
            return this.aksiKini.Jenis !== 'SIMPAN' && this.kurangUtama.length === 0;
        },
        jumlahPerubahan() {
            return this.berubah.length + (this.reviewKotor ? 1 : 0);
        },
        reviewKotor() {
            return JSON.stringify(this.muatanReview()) !== this.reviewAsli;
        },
        kotor() {
            return this.berubah.length > 0 || this.reviewKotor;
        },
        berubah() {
            if (!this.asli) return [];
            const awal = JSON.parse(this.asli);

            return Object.keys(this.form).filter((k) => k !== 'Konten_Nama').filter((k) => {
                const a = awal[k];
                const b = this.form[k];
                if (Array.isArray(a) || Array.isArray(b)) {
                    return JSON.stringify(a || []) !== JSON.stringify(b || []);
                }
                return (a ?? '') !== (b ?? '');
            });
        },
    },
    async created() {
        this.memuatInisial = true;
        try {
            const promises = [];
            if (this.isKuis) promises.push(this.muatUjian());

            if (this.idVersiKini) {
                await Promise.all([
                    this.muatVersi(),
                    this.muatReview(this.idVersiKini),
                    /* Langkah dari URL HANYA di pemuatan pertama. Pada pemuatan
                       berikutnya tahapnya baru saja bergeser, dan perpindahan
                       memang seharusnya memindahkan — memaksa kembali ke angka
                       di URL akan menahan admin di langkah yang sudah ia
                       tinggalkan. */
                    this.alur.muat(this.idVersiKini, { dibukaAwal: this.langkahDariUrl() }),
                    ...promises,
                ]);
            } else {
                // Meneruskan versi yang sudah ada sebelumnya
                this.inisialisasiVersiBaru();
                await Promise.all([
                    this.alur.muatTemplate(),
                    ...promises,
                ]);
                this.tandaiBersih();
            }
        } finally {
            this.memuatInisial = false;
        }
    },
    watch: {
        /* Tempat berdiri ditulis ke URL setiap kali bergeser, supaya muat ulang
           mengembalikan admin ke tempat yang sama. Pola & mekanismenya sama
           dengan syncUrl() di SopBuilder: replaceState membawa objek state
           Inertia apa adanya, jadi tak ada permintaan ke server dan tak ada
           entri riwayat baru untuk sekadar berpindah langkah. */
        'alur.dibuka'(nomor) {
            this.tulisLangkahKeUrl(nomor);
        },
    },
    methods: {
        /** Langkah yang diminta URL, atau null bila tak masuk akal. */
        langkahDariUrl() {
            if (typeof window === 'undefined') return null;

            const n = Number(new URLSearchParams(window.location.search).get('langkah'));

            return Number.isInteger(n) && n > 0 ? n : null;
        },
        tulisLangkahKeUrl(nomor) {
            if (typeof window === 'undefined' || !window.history) return;

            const url = new URL(window.location.href);
            if (nomor) url.searchParams.set('langkah', String(nomor));
            else url.searchParams.delete('langkah');

            if (url.toString() === window.location.href) return;

            window.history.replaceState(window.history.state, '', url);
        },
        /**
         * Nomor dokumen, dengan penggantinya bila belum ada apa pun.
         *
         * Tiga tempat di halaman ini menampilkan nomor yang sama — kicker
         * header, kotak nomor di kartu paspor, dan kartu ringkasan di bilah
         * samping — dan ketiganya dulu menulis rantai pilihannya masing-masing.
         * Yang menyimpang di antara tiga salinan adalah yang paling jarang
         * dilihat, dan di sini yang menyimpang adalah nomor dokumen resmi.
         */
        nomorDokumenAtau(pengganti) {
            return nomorDokumenTampil(this.dokumen) || pengganti;
        },
        inisialisasiVersiBaru() {
            const next = this.infoVersi?.Next_Versi_Ke !== undefined && this.infoVersi?.Next_Versi_Ke !== null
                ? this.infoVersi.Next_Versi_Ke
                : 0;

            /* VERSI BARU LAHIR KOSONG. Tak ada satu pun isian yang diwarisi dari
               versi sebelumnya.

               Sampai 30 Agu 2026 formulir ini mengisi dirinya sendiri dari v(n-1):
               matriks pengesah, berkas, dan paket ujian. Maksudnya kenyamanan —
               admin tak perlu memilih ulang hal yang sama tiap revisi — tetapi
               isian itu TERSIMPAN pada penyimpanan pertama, dan sejak saat itu tak
               ada lagi yang membedakannya dari pilihan sadar admin. Dua akibatnya
               nyata:

               1. Gerbang REALISASI_PENGESAH (DML-2026-048) lolos sendiri di tiap
                  revisi sesudah v00 — versi baru tercatat disahkan oleh orang-orang
                  yang belum melihatnya.
               2. Riwayat versi melaporkan "berkas terlampir" pada versi yang
                  berkasnya belum pernah dipilih siapa pun. Laporannya jujur; yang
                  tak jujur adalah data yang ditulis tanpa ada yang memilihnya.

               Yang kedua lebih dari sekadar salah tampil: revisi yang terbit tanpa
               disadari akan membawa BERKAS LAMA di bawah nomor revisi baru, dan
               karyawan membaca dokumen yang sama sambil dihitung sudah membaca yang
               baru.

               Memilih ulang berkas dari Library butuh dua klik. Menemukan versi
               yang diam-diam mengaku punya isi yang tak pernah dipilih butuh
               seseorang yang curiga lebih dulu. */
            this.form = {
                ...this.kosong(),
                Versi_Ke: next,
            };
        },
        getTypeVariant(type) {
            switch (String(type || '').toUpperCase()) {
                case 'DOKUMEN': return 'brand';
                case 'KUIS': return 'purple';
                case 'FORM': return 'teal';
                case 'VIDEO': return 'warning';
                default: return 'neutral';
            }
        },
        salinNomorDokumen(nomor) {
            if (!nomor) return;
            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(nomor);
                hcisMessage.success(`Nomor Dokumen "${nomor}" berhasil disalin ke clipboard.`);
            }
        },
        bukaMasterDokumen() {
            router.visit(`/sop/admin/dokumen?dok=${this.idDokumen}`);
        },
        bukaLangkahDanAlih(nomor) {
            this.alur.buka(nomor);
            this.mobileTab = 'langkah';
        },
        kosong() {
            return {
                Versi_Ke: null,
                Ringkasan_Perubahan: '',
                Tanggal_Permintaan: '',
                Tanggal_Respon: '',
                Tanggal_Akhir_Approval: '',
                Tanggal_Berlaku: '',
                Tanggal_Kadaluarsa: '',
                Tanggal_Review_Berikutnya: '',
                Id_Master_Konten: null,
                Id_Master_Ujian: null,
                Konten_Nama: '',
                Pengesah: [],
                Lampiran: [],
            };
        },
        tandaiBersih() {
            this.asli = JSON.stringify(this.form);
        },
        /**
         * Ambil ulang cap tulis-bersamaan tanpa menyentuh isian formulir.
         *
         * Sengaja BUKAN muatVersi(): memuat ulang seluruh form sesudah simpan
         * akan membuang perubahan yang admin ketik selama permintaan berjalan.
         */
        async muatCapVersi() {
            try {
                const r = await axios.get(`${API}/${this.idDokumen}/versi`, { params: { limit: 50 } });
                const v = (r.data?.result?.rows || []).find((x) => x.Id_SOP_Item_Versi === this.idVersiKini);
                if (v) this.capVersi = v.Cap_Versi || null;
            } catch {
                /* Cap yang gagal disegarkan hanya berarti simpanan berikutnya
                   diminta memuat ulang — mengganggu, tapi tak merusak. */
            }
        },
        async muatVersi() {
            try {
                const r = await axios.get(`${API}/${this.idDokumen}/versi`, { params: { limit: 50 } });
                const daftar = r.data?.result?.rows || [];
                const v = daftar.find((x) => x.Id_SOP_Item_Versi === this.idVersiKini);
                if (!v) {
                    this.alur.galat = 'Versi tidak ditemukan pada dokumen ini.';
                    return;
                }
                /* Cap tulis-bersamaan: waktu baris ini SAAT DIBACA. Dikirim
                   kembali ketika menyimpan supaya server bisa menolak
                   penimpaan diam-diam atas pekerjaan admin lain. Disimpan di
                   luar `form` karena ia bukan isian — kalau ikut form, ia
                   terhitung sebagai "perubahan belum disimpan". */
                this.capVersi = v.Cap_Versi || null;
                /* Izin per field datang dari server. Layar tak menerjemahkan
                   keadaan menjadi aturan sendiri — itulah yang berulang membuat
                   isian ditawarkan lalu ditolak. */
                this.fieldBolehUbah = Array.isArray(v.Field_Boleh_Ubah) ? v.Field_Boleh_Ubah : null;
                this.bekuIsi = typeof v.Beku_Isi === 'boolean' ? v.Beku_Isi : null;

                this.form = {
                    ...this.kosong(),
                    Versi_Ke: v.Versi_Ke ?? null,
                    Ringkasan_Perubahan: v.Ringkasan_Perubahan || '',
                    Tanggal_Permintaan: v.Tanggal_Permintaan || '',
                    Tanggal_Respon: v.Tanggal_Respon || '',
                    Tanggal_Akhir_Approval: v.Tanggal_Akhir_Approval || '',
                    Tanggal_Berlaku: v.Tanggal_Berlaku || '',
                    Tanggal_Kadaluarsa: v.Tanggal_Kadaluarsa || '',
                    Tanggal_Review_Berikutnya: v.Tanggal_Review_Berikutnya || '',
                    Id_Master_Konten: v.Id_Master_Konten || null,
                    Id_Master_Ujian: v.Id_Master_Ujian || null,
                    Konten_Nama: v.Konten_Nama || '',
                    Pengesah: Array.isArray(v.Pengesah) ? JSON.parse(JSON.stringify(v.Pengesah)) : [],
                    Lampiran: (Array.isArray(v.Lampiran) ? v.Lampiran : []).map((l) => ({
                        Id_Master_Konten: l.Id_Master_Konten,
                        Judul: l.Judul || '',
                        Ext: l.Ext || '',
                    })),
                };
                this.tandaiBersih();
            } catch (e) {
                this.alur.galat = e?.response?.data?.message || 'Gagal memuat versi.';
            }
        },
        async muatUjian() {
            this.ujianLoading = true;
            this.ujianError = '';
            try {
                const r = await axios.get(`${API}/options/ujian`);
                /* DIPETAKAN, bukan disimpan mentah. HC Learn mengirim
                   Id_Master_Ujian/Nama_Ujian; komponennya membaca value/label.
                   Menyimpan mentah menghasilkan dropdown berisi opsi kosong
                   yang tak bisa diklik — terlihat seperti data rusak, padahal
                   API-nya membalas 200 dengan isi lengkap. */
                this.ujianList = petakanOpsiUjian(r.data?.result ?? r.data?.data);
            } catch (e) {
                this.ujianList = [];
                /* GALATNYA DITAMPILKAN, bukan ditelan. Sebab paling lazim di
                   sini adalah 403 "akun belum tertaut HC Learn" — kegagalan
                   yang punya jalan keluar, tapi hanya kalau orangnya membaca
                   sebabnya. Ditelan, ia tampil persis seperti daftar kosong. */
                this.ujianError = e?.response?.data?.message || 'Gagal memuat daftar paket ujian HC Learn.';
            } finally {
                this.ujianLoading = false;
            }
        },
        muatan() {
            const p = { ...this.form };
            delete p.Konten_Nama;
            /* Ikut dikirim, bukan bagian form: server membandingkannya dengan
               Updated_At tersimpan sebelum menimpa apa pun. */
            if (this.capVersi) p.Updated_At_Dibaca = this.capVersi;
            if (this.isKuis) {
                p.Nama_Ujian = namaOpsiUjian(this.ujianList, p.Id_Master_Ujian);
            }
            Object.keys(p).forEach((k) => {
                if (p[k] === '') p[k] = null;
            });
            /* Penanda tampilan dibuang di sini, bukan dibiarkan ikut terkirim:
               Dari_Versi dan Aktif menjelaskan ASAL sebuah baris, bukan isinya,
               dan menyimpannya akan membuat versi baru mengaku-ngaku diwarisi
               dari versi yang sudah lama berlalu. */
            p.Pengesah = (p.Pengesah || [])
                .filter((x) => (x.Nama || '').trim() !== '')
                .map(({ Dari_Versi, Aktif, ...sisa }) => sisa);

            /* TIPE YANG TAK MENGENAL PENGESAHAN TIDAK MENGIRIM BLOKNYA.
               Server menolak blok Pengesah TERISI untuk tipe di luar DOKUMEN
               (pastikanFieldSesuaiTipe). Layar memuat bloknya apa adanya dari
               server, jadi satu versi FORM/VIDEO/KUIS lama yang kebetulan
               menyimpan baris pengesah — 47% versi FORM pernah punya nomor
               pengesahan sebelum profil tipe ada — akan MEMANTULKAN barisnya
               kembali dan membuat draftnya mustahil disimpan, tanpa admin
               pernah menyentuh field itu. */
            if (!this.pakaiPengesahan) p.Pengesah = [];

            /* Form kosong: hanya id kontennya yang dikirim, dalam urutan
               tampil. Tipe tanpa blok lampiran tak mengirim kuncinya sama
               sekali — server membaca kunci yang tak dikirim sebagai "jangan
               sentuh", sedangkan larik kosong berarti "lepas semua". */
            if (this.pakaiLampiran) {
                p.Lampiran = (p.Lampiran || []).map((l) => ({ Id_Master_Konten: l.Id_Master_Konten }));
            } else {
                delete p.Lampiran;
            }

            return p;
        },
        async simpan({ diam = false } = {}) {
            if (this.menyimpan) return false;
            this.menyimpan = true;
            try {
                const p = this.muatan();

                if (this.idVersiKini) {
                    await axios.put(`${API}/versi/${this.idVersiKini}`, p);
                    this.tandaiBersih();
                    /* CAP DIPERBARUI SETELAH SIMPAN BERHASIL. Baris di server
                       baru saja bergeser; membiarkan cap lama membuat simpanan
                       KEDUA ditolak sebagai "diubah orang lain" — padahal yang
                       mengubahnya admin ini sendiri. Penjaga yang menuduh
                       pemiliknya sendiri lebih buruk daripada tak ada penjaga. */
                    await this.muatCapVersi();
                    if (this.reviewKotor && !(await this.simpanReview({ diam: true }))) {
                        return false;
                    }
                    await this.alur.muat(this.idVersiKini, { pertahankanDibuka: true });
                    if (!diam) hcisMessage.success('Perubahan versi tersimpan.');

                    return true;
                }

                const r = await axios.post(`${API}/${this.idDokumen}/versi`, p);
                const idBaru = r.data?.result?.Id_SOP_Item_Versi;
                this.tandaiBersih();
                if (!idBaru) {
                    hcisMessage.error('Versi tersimpan tetapi id-nya tak terbaca. Muat ulang halaman.');
                    return false;
                }

                this.idVersiKini = idBaru;
                if (this.reviewKotor) await this.simpanReview({ diam: true });
                router.replace({
                    url: `/sop/admin/dokumen/${this.idDokumen}/versi/${idBaru}`,
                    preserveState: true,
                    preserveScroll: true,
                });
                await this.alur.muat(idBaru);
                if (!diam) hcisMessage.success('Pengajuan versi tersimpan sebagai draft.');

                return true;
            } catch (e) {
                hcisMessage.error(e?.response?.data?.message || 'Gagal menyimpan versi.');
                return false;
            } finally {
                this.menyimpan = false;
            }
        },
        async jalankanUtama() {
            const a = this.aksiKini;

            if (a.Jenis === 'LANJUT_LANGKAH') {
                if (this.kotor && !(await this.simpan({ diam: true }))) return;
                this.alur.buka(a.Ke_Langkah);
                return;
            }

            if (a.Jenis === 'TERBITKAN') {
                if (this.kotor && !(await this.simpan({ diam: true }))) return;
                this.pratinjau = { Id_SOP_Item_Versi: this.idVersiKini, Versi_Ke: this.form.Versi_Ke };
                return;
            }

            if (a.Jenis === 'SIMPAN_LANJUT') {
                if (!(await this.simpan({ diam: true }))) return;
                await this.pindah(a.Ke_Tahap, a.Label);
                return;
            }

            await this.simpan();
        },
        async jalankanAksi(a) {
            if (!a || !this.idVersiKini || this.menyimpan) return;

            const teks = {
                KEMBALIKAN: {
                    title: 'Kembalikan untuk Revisi?',
                    description: 'Versi ini kembali ke tahap sebelumnya supaya bisa diperbaiki. '
                        + 'Seluruh isian yang sudah ada tetap tersimpan.',
                    confirmText: 'Kembalikan',
                    sentiment: 'warning',
                    icon: 'arrow-counterclockwise',
                },
                BATALKAN: {
                    title: 'Batalkan Versi Ini?',
                    description: 'Pembatalan adalah TAHAP AKHIR — versi ini tak bisa dilanjutkan lagi, '
                        + 'dan penggantinya harus dibuat sebagai versi baru. '
                        + 'Catatan yang sudah ada tetap tersimpan sebagai riwayat.',
                    confirmText: 'Ya, Batalkan',
                    sentiment: 'danger',
                    icon: 'x-circle',
                },
            }[a.Jenis];

            if (teks && !(await hcisConfirm({ ...teks, cancelText: 'Batal' }))) return;

            const berhasil = await this.pindah(a.Ke_Tahap, a.Label);

            /* PEMBATALAN ADALAH AKHIR, sama seperti penerbitan — tak ada langkah
               berikutnya yang bisa dikerjakan di wizard ini. Membiarkan admin
               berdiri di formulir versi yang sudah jadi arsip menyodorkan
               isian yang tak lagi menerima apa pun; yang ia butuhkan adalah
               melihat versi itu duduk di riwayat dokumennya. Tujuannya satu
               pintu dengan setelahTerbit(). */
            if (berhasil && a.Jenis === 'BATALKAN') this.kembaliKeDaftarVersi();
        },
        /** @returns {Promise<boolean>} berhasil atau tidak — pemanggil perlu tahu. */
        async pindah(kodeTahap, label) {
            if (!kodeTahap) return false;
            this.menyimpan = true;
            this.alur.galat = '';
            try {
                const r = await axios.put(`${API}/versi/${this.idVersiKini}/tahap`, { Kode_Tahap: kodeTahap });
                await Promise.all([
                    this.alur.muat(this.idVersiKini),
                    this.muatVersi(),
                ]);
                this.reviewUntuk = null;
                await this.muatReview(this.idVersiKini);
                hcisMessage.success(r.data?.message || label || 'Tahap versi berpindah.');

                return true;
            } catch (e) {
                this.alur.galat = e?.response?.data?.message || 'Gagal memindahkan tahap.';
                hcisMessage.error(this.alur.galat);

                return false;
            } finally {
                this.menyimpan = false;
            }
        },
        muatanReview() {
            return this.review.map((r) => ({
                Review_Ke: Number(r.Review_Ke) || null,
                Tanggal_Review: r.Tanggal_Review || null,
                Hasil_Review: r.Hasil_Review || null,
                Lampiran: (r.Lampiran || []).map((l) => ({
                    Berkas: l.Path_Berkas || l.Berkas,
                    Nama_Berkas: l.Nama_Berkas || null,
                    Tipe_Berkas: l.Tipe_Berkas || null,
                    Ukuran_Byte: l.Ukuran_Byte || null,
                })),
            }));
        },
        async muatReview(idVersi) {
            if (!idVersi) {
                this.review = [];
                this.reviewAsli = '[]';
                this.reviewUntuk = null;
                return;
            }
            if (this.reviewUntuk === idVersi) return;

            this.reviewMemuat = true;
            this.reviewGalat = '';
            try {
                const r = await axios.get(`${API}/versi/${idVersi}/review`);
                this.review = (r.data?.result || []).map((x, i) => ({
                    kunci: `ada-${x.Id_Review || i}`,
                    Review_Ke: x.Review_Ke,
                    Tanggal_Review: x.Tanggal_Review || '',
                    Hasil_Review: x.Hasil_Review || '',
                    Lampiran: x.Lampiran || [],
                    galat: '',
                }));
                this.reviewUntuk = idVersi;
                this.reviewAsli = JSON.stringify(this.muatanReview());
            } catch (e) {
                this.review = [];
                this.reviewAsli = '[]';
                this.reviewGalat = e?.response?.data?.message || 'Gagal memuat catatan review.';
            } finally {
                this.reviewMemuat = false;
            }
        },
        async simpanReview({ diam = false } = {}) {
            if (!this.idVersiKini) return false;

            const sebelumnya = this.menyimpan;
            this.menyimpan = true;
            this.reviewGalat = '';
            try {
                const muatan = this.muatanReview();
                const r = await axios.put(`${API}/versi/${this.idVersiKini}/review`, { Review: muatan });
                this.reviewAsli = JSON.stringify(muatan);
                this.reviewUntuk = null;
                await this.muatReview(this.idVersiKini);
                if (!diam) await this.segarkanAlur();
                if (!diam) hcisMessage.success(r.data?.message || 'Catatan review disimpan.');

                return true;
            } catch (e) {
                this.reviewGalat = e?.response?.data?.message || 'Gagal menyimpan catatan review.';
                if (!diam) hcisMessage.error(this.reviewGalat);

                return false;
            } finally {
                this.menyimpan = sebelumnya;
            }
        },
        async segarkanAlur() {
            if (this.idVersiKini) {
                await Promise.all([
                    this.alur.muat(this.idVersiKini, { pertahankanDibuka: true }),
                    this.muatVersi(),
                ]);
            } else {
                await this.alur.muatTemplate();
            }
            hcisMessage.success('Status alur versi disegarkan.');
        },
        bukaPemilih(tujuan) {
            this.pickerTujuan = tujuan;
            this.pickerOpen = true;
        },
        onKonten(k) {
            const id = k?.Id_Master_Konten ?? k?.id ?? null;
            const judul = k?.judul ?? k?.Judul_Konten ?? k?.nama ?? 'Berkas terpilih';

            if (this.pickerTujuan === 'lampiran') {
                const ada = (this.form.Lampiran || []).some((l) => l.Id_Master_Konten === id);
                if (id && !ada) {
                    this.form = {
                        ...this.form,
                        Lampiran: [...(this.form.Lampiran || []), { Id_Master_Konten: id, Judul: judul, Ext: k?.ext || '' }],
                    };
                }
                this.pickerTujuan = 'berkas';
                this.pickerOpen = false;
                return;
            }

            this.form = { ...this.form, Id_Master_Konten: id, Konten_Nama: judul };
            this.pickerOpen = false;
        },
        /* Satu tujuan untuk setiap akhir siklus versi — terbit maupun batal.
           Dua salinan alamat yang sama pasti menyimpang, dan yang menyimpang
           adalah yang jarang ditempuh: pembatalan. */
        kembaliKeDaftarVersi() {
            router.visit(`/sop/admin/dokumen?dok=${this.idDokumen}&tab=versi`);
        },
        async setelahTerbit() {
            this.pratinjau = null;
            this.kembaliKeDaftarVersi();
        },
        async kembaliKeMaster() {
            if (this.kotor && !(await hcisConfirm({
                title: 'Tinggalkan Tanpa Menyimpan?',
                description: 'Ada perubahan yang belum disimpan pada langkah ini. Meninggalkan halaman akan membuang perubahan tersebut.',
                confirmText: 'Tinggalkan',
                cancelText: 'Tetap di Sini',
                sentiment: 'warning',
                icon: 'exclamation-triangle',
            }))) return;

            router.visit(`/sop/admin/dokumen?dok=${this.idDokumen}`);
        },
    },
};
</script>

<style scoped>
.sop-versi-wizard {
    width: 100%;
}

/* ══ Master-Detail Workspace Grid ══ */
.mdx-workspace-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 1.25rem;
    align-items: start;
}

@media (max-width: 991.98px) {
    .mdx-workspace-grid {
        grid-template-columns: 1fr;
    }
}

.mdx-sidebar-wrap {
    position: sticky;
    top: 80px;
    width: 100%;
}

/* ══ Document Hero Passport Card (Aligned with SopMasterDokumen) ══ */
.mdx-detail-hero-card {
    padding: 1.25rem 1.35rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.mdx-detail-hero {
    position: relative;
}

.mdx-hero-tag {
    display: inline-flex;
    align-items: center;
    font-size: 0.72rem;
    font-weight: 700;
    border-radius: var(--hcis-radius-pill, 9999px);
    padding: 0.15rem 0.55rem;
    line-height: 1.3;
}

.jenis-tag {
    color: var(--hcis-indigo-700);
    background: var(--hcis-indigo-100);
    border: 1px solid var(--hcis-indigo-200);
}

.active-tag {
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-100);
    border: 1px solid var(--hcis-emerald-200);
}

.warning-tag {
    color: var(--hcis-amber-700);
    background: var(--hcis-amber-100);
    border: 1px solid var(--hcis-amber-200);
}

.neutral-tag {
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
}

.mdx-hero-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.35;
    letter-spacing: -0.01em;
}

/* Hero Monospace Number Strip */
.mdx-hero-number-strip {
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-100) 100%);
    border: 1px solid var(--hcis-slate-200);
}

.mdx-official-number {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.22rem 0.65rem;
    border-radius: 6px;
    font-size: 0.825rem;
    font-weight: 800;
    color: var(--hcis-white);
    background: var(--hcis-slate-900);
    letter-spacing: 0.02em;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.15);
}

.mdx-official-number.is-incomplete {
    color: var(--hcis-amber-800);
    background: var(--hcis-amber-100);
    border: 1px dashed var(--hcis-amber-600);
    box-shadow: none;
}

.mdx-copy-btn {
    display: inline-grid;
    place-items: center;
    width: 28px;
    height: 28px;
    padding: 0;
    border-radius: 6px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    color: var(--hcis-slate-600);
    transition: all 0.15s ease;
}

.mdx-copy-btn:hover {
    background: #eff6ff;
    border-color: var(--hcis-blue-500);
    color: #1d4ed8;
}

.mdx-seg-pill {
    display: inline-flex;
    align-items: center;
    font-size: 0.7rem;
    font-weight: 650;
    color: var(--hcis-slate-700);
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-300);
    border-radius: 4px;
    padding: 0.12rem 0.45rem;
}

/* ══ Kaki aksi melekat ══
   Satu benda mengambang di atas isi halaman: dua baris di dalam, tepi kiri
   berwarna yang membacakan keadaan langkah. Warna & jarak seluruhnya dari
   token peran — halaman ini sebelumnya memaku hex-nya sendiri, dan nilai
   yang dipaku tak ikut saat temanya bergeser. */
.sop-versi-wizard__kaki {
    --kaki-tepi: var(--hcis-border-medium);

    position: sticky;
    bottom: max(var(--hcis-space-4), env(safe-area-inset-bottom));
    z-index: 50;
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2-5);
    margin-top: var(--hcis-space-6);
    padding: var(--hcis-space-3) var(--hcis-space-4);
    border: 1px solid var(--hcis-border);
    /* Seradius kartu lain di halaman ini (.mdx-detail-hero-card). Radius yang
       lebih besar sempat dicoba supaya kakinya terasa "melayang", tapi tepi
       berwarna 3px yang mengikuti lengkung besar berubah jadi sabit, bukan
       tulang punggung. */
    border-radius: var(--hcis-radius-lg);
    /* Tembus pandang yang SUNGGUHAN. Yang lama menumpuk blur 16px di bawah putih
       0,95 — lapisan komposit yang biayanya nyata dan hasilnya tak terlihat. */
    background: rgb(var(--hcis-surface-rgb) / 0.86);
    backdrop-filter: blur(12px);
    box-shadow:
        inset 3px 0 0 0 var(--kaki-tepi),
        var(--hcis-shadow-lg);
    transition: box-shadow 160ms ease;
}

@supports not (backdrop-filter: blur(1px)) {
    .sop-versi-wizard__kaki {
        background: var(--hcis-surface);
    }
}

.sop-versi-wizard__kaki.kaki--tertahan {
    --kaki-tepi: var(--hcis-warning-500);
}

.sop-versi-wizard__kaki.kaki--siap {
    --kaki-tepi: var(--hcis-success-500);
}

.sop-versi-wizard__kaki.kaki--arsip {
    --kaki-tepi: var(--hcis-danger-500);
}

/* ── Baris keadaan ───────────────────────────────────────────────────────
   Berdiri di ATAS aksinya, bukan berdesakan di sampingnya, dan tampil di
   SEMUA ukuran layar. Alasan sebuah tombol tak memajukan apa pun adalah hal
   pertama yang dicari orang saat tombolnya tak bekerja. */
.kaki-keadaan {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--hcis-space-2) var(--hcis-space-4);
    padding-bottom: var(--hcis-space-2-5);
    border-bottom: 1px solid var(--hcis-border-subtle);
    font-size: 0.775rem;
    line-height: 1.35;
}

/* WAJIB sesudah aturan di atas: spesifisitasnya sama persis (satu kelas), jadi
   yang menang adalah yang belakangan. Ditaruh di depan, baris ini tak pernah
   meruntuhkan apa pun. */
.kaki-keadaan--sunyi {
    display: none;
}

.kaki-sinyal {
    display: inline-flex;
    align-items: baseline;
    gap: var(--hcis-space-1-5);
    color: var(--hcis-text-muted);
    min-width: 0;
}

.kaki-sinyal .bi {
    font-size: 0.7rem;
    line-height: 1;
}

.kaki-sinyal--kotor .bi {
    color: var(--hcis-text-subtle);
}

.kaki-sinyal--kurang {
    color: var(--hcis-warning-700);
}

.kaki-sinyal--kurang .bi {
    color: var(--hcis-warning-500);
}

.kaki-sinyal--kurang strong {
    color: var(--hcis-text-strong);
    font-weight: 600;
}

/* ── Baris aksi ───────────────────────────────────────────────────────────
   Mundur & batal di kiri, maju di kanan, dipisah garis rambut. Saat
   membungkus, keduanya tetap punya satu aturan perataan masing-masing —
   itulah yang dulu bikin kakinya terlihat compang-camping. */
.kaki-aksi {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--hcis-space-2) var(--hcis-space-3);
}

.kaki-aksi__kiri,
.kaki-aksi__kanan {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--hcis-space-2);
}

.kaki-aksi__kanan {
    margin-left: auto;
}

/* Garis rambut hanya lahir bila memang ada yang dipisahkan. */
.kaki-aksi__kiri + .kaki-aksi__kanan::before {
    content: '';
    align-self: stretch;
    width: 1px;
    min-height: 1.5rem;
    margin-right: var(--hcis-space-1);
    background: var(--hcis-border);
}

/* ── Layar sempit ─────────────────────────────────────────────────────────
   Aksi utama melebar penuh; garis pemisah dibuang karena kedua kelompok
   sudah berdiri di barisnya sendiri. */
@media (max-width: 36rem) {
    .sop-versi-wizard__kaki {
        padding: var(--hcis-space-3);
    }

    .kaki-aksi__kiri,
    .kaki-aksi__kanan {
        width: 100%;
        margin-left: 0;
    }

    .kaki-aksi__kiri + .kaki-aksi__kanan::before {
        display: none;
    }

    .kaki-aksi__kanan > :last-child {
        flex: 1 1 auto;
    }
}
</style>

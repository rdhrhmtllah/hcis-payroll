<template>
    <div>
        <HcisDualPaneDialog
            :model-value="show"
            :title="modalTitle"
            :kicker="modalKicker"
            icon="patch-check-fill"
            sentiment="brand"
            variant="default"
            pattern="none"
            size="xl"
            left-width="390px"
            sticky-footer
            :loading="detailLoading"
            loading-text="Mengambil rincian permit..."
            @update:model-value="(val) => { if (!val) $emit('close'); }"
            @close="$emit('close')"
        >
            <!-- ================================================================= -->
            <!-- LEFT PANE: Overview Hero Card, Requester Info & Approval Timeline -->
            <!-- ================================================================= -->
            <template #left>
                <div v-if="permit">
                    <!-- 1. Hero Permit Identity Card -->
                    <div class="permit-hero-card" :style="getHeroCardStyle(permit)">
                        <!-- Header Row: Vehicle Icon + Category/Model Details + Status Badge -->
                        <div class="permit-hero-header">
                            <div class="permit-hero-brand-group">
                                <div class="permit-hero-icon-box shadow-sm" :style="getHeroIconBoxStyle(permit)">
                                    <HcisIcon :name="getVehicleIcon(permit.Nama_Vehicle_Type, permit.Permit_Untuk_Tamu === 'Y')" size="md" />
                                </div>
                                <div class="permit-hero-brand-meta">
                                    <div>
                                        <span class="permit-hero-category-pill" :style="getCategoryBadgeStyle(permit)">
                                            <span class="permit-hero-category-dot" :style="{ backgroundColor: permit.Kode_Warna || '#6366f1' }" />
                                            <span class="permit-hero-category-text text-truncate">{{ permit.Nama_Kategori || 'Permit Akses' }}</span>
                                        </span>
                                    </div>
                                    <div class="permit-hero-vehicle-type text-truncate">
                                        <template v-if="permit.Nama_Vehicle_Type || permit.Nama_Model_Manual">
                                            {{ permit.Nama_Vehicle_Type || 'Kendaraan' }}
                                            <span v-if="permit.Nama_Model_Manual" class="fw-semibold text-slate-700">· {{ permit.Nama_Model_Manual }}</span>
                                        </template>
                                        <template v-else-if="permit.Permit_Untuk_Tamu === 'Y'">
                                            {{ permit.Instansi_Tamu || 'Tamu Kunjungan' }}
                                        </template>
                                        <template v-else>
                                            Kendaraan Operasional
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <HcisBadge
                                :status="permit.Status_Permit"
                                :label="getStatusLabel(permit.Status_Permit)"
                                size="sm"
                                appearance="subtle"
                                class="flex-shrink-0"
                            />
                        </div>

                        <!-- Core Identity & Specs Showcase Box -->
                        <div class="permit-hero-id-box">
                            <!-- Transaction Number Highlight Bar -->
                            <div class="permit-hero-trx-bar">
                                <div class="permit-hero-trx-label-wrap">
                                    <HcisIcon name="receipt-cutoff" size="xs" class="permit-hero-trx-icon" />
                                    <span class="permit-hero-trx-label">Nomor Transaksi</span>
                                </div>
                                <div class="permit-hero-trx-value font-tabular font-mono">
                                    {{ permit.No_Transaksi }}
                                </div>
                            </div>

                            <!-- Primary Specs 3-Column Grid (When specs exist) -->
                            <div v-if="hasVehicleSpecs" class="permit-hero-specs-grid">
                                <div class="permit-hero-spec-cell">
                                    <span class="permit-hero-spec-kicker">Plat Nomor</span>
                                    <div class="permit-hero-spec-val font-tabular font-mono fw-bold text-slate-900 text-truncate">
                                        <span class="permit-hero-plate-badge">{{ permit.No_Plat_Kendaraan || '-' }}</span>
                                    </div>
                                </div>
                                <div class="permit-hero-spec-cell">
                                    <span class="permit-hero-spec-kicker">Tahun</span>
                                    <div class="permit-hero-spec-val font-tabular fw-bold text-slate-800 text-truncate">
                                        {{ permit.Tahun_Kendaraan || '-' }}
                                    </div>
                                </div>
                                <div class="permit-hero-spec-cell">
                                    <span class="permit-hero-spec-kicker">Warna</span>
                                    <div class="permit-hero-spec-val d-flex align-items-center gap-1.5 fw-bold text-slate-800 text-truncate">
                                        <span
                                            v-if="permit.Warna_Manual && permit.Warna_Manual !== '-'"
                                            class="permit-color-circle"
                                            :style="{ backgroundColor: getVehicleColorHex(permit.Warna_Manual) }"
                                        />
                                        <span class="text-truncate">{{ permit.Warna_Manual || '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Contextual Notice Box (When vehicle specs are not recorded yet, e.g. B2B / Coklat) -->
                            <div v-else-if="butuhInspeksi" class="permit-hero-uninspected-box">
                                <div class="permit-hero-uninspected-icon">
                                    <HcisIcon name="clipboard-check" size="xs" />
                                </div>
                                <div class="permit-hero-uninspected-info">
                                    <div class="permit-hero-uninspected-title">Inspeksi Fisik di Pos Jaga</div>
                                    <div class="permit-hero-uninspected-desc">
                                        Data plat, warna, dan tipe kendaraan dicatat verifikator saat pemeriksaan pos.
                                    </div>
                                </div>
                            </div>

                            <div v-else class="permit-hero-uninspected-box permit-hero-uninspected-box--info">
                                <div class="permit-hero-uninspected-icon">
                                    <HcisIcon :name="permit.Permit_Untuk_Tamu === 'Y' ? 'person-vcard' : 'shield-check'" size="xs" />
                                </div>
                                <div class="permit-hero-uninspected-info">
                                    <div class="permit-hero-uninspected-title">
                                        {{ permit.Permit_Untuk_Tamu === 'Y' ? 'Akses Kunjungan Tamu' : 'Izin Akses Khusus' }}
                                    </div>
                                    <div class="permit-hero-uninspected-desc">
                                        {{ permit.Permit_Untuk_Tamu === 'Y' ? 'Izin akses orang / tamu tanpa kendaraan bermotor terdaftar.' : 'Izin operasional dengan otorisasi khusus.' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Meta Row: Submission Date & Guest Reference & Mitra -->
                        <div class="permit-hero-meta-bar">
                            <div class="permit-hero-meta-item">
                                <HcisIcon name="calendar3" size="xs" class="permit-hero-meta-icon" />
                                <span class="permit-hero-meta-label">Diajukan:</span>
                                <span class="permit-hero-meta-value font-tabular">{{ formatDate(permit.Tanggal_Pengajuan) }}</span>
                            </div>
                            <div
                                v-if="permit.No_Transaksi_Tamu"
                                class="permit-hero-meta-item permit-hero-meta-item--ref permit-hero-meta-item--clickable"
                                title="Klik untuk membuka detail kunjungan tamu"
                                role="button"
                                tabindex="0"
                                @click.stop="openTamuDetail(permit.No_Transaksi_Tamu)"
                                @keydown.enter.stop="openTamuDetail(permit.No_Transaksi_Tamu)"
                            >
                                <HcisIcon name="link-45deg" size="xs" class="permit-hero-meta-icon" />
                                <span class="permit-hero-meta-label">Tamu:</span>
                                <span class="permit-hero-meta-value font-tabular font-mono permit-hero-meta-link">
                                    #{{ permit.No_Transaksi_Tamu }}
                                    <HcisIcon name="box-arrow-up-right" size="2xs" class="ms-1 permit-hero-meta-link-icon" />
                                </span>
                            </div>
                            <div v-else-if="permit.Nama_Mitra" class="permit-hero-meta-item permit-hero-meta-item--mitra">
                                <HcisIcon name="plug" size="xs" class="permit-hero-meta-icon" />
                                <span class="permit-hero-meta-label">Mitra:</span>
                                <span class="permit-hero-meta-value">{{ permit.Nama_Mitra }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Pemohon Information Box -->
                    <HcisCard
                        :title="permit.Permit_Untuk_Tamu === 'Y' ? 'Data Tamu & Pemohon' : 'Pemohon & Departemen'"
                        :icon="permit.Permit_Untuk_Tamu === 'Y' ? 'person-vcard-fill' : 'person-badge-fill'"
                        sentiment="brand"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <!-- SHE bisa langsung menghubungi pemohon (atau tamu & PIC-nya)
                             yang terlambat / belum datang ke jadwal inspeksi. -->
                        <template v-if="kontakPemohon.length" #actions>
                            <HubungiPemohonTombol
                                :kontak="kontakPemohon"
                                size="sm"
                                :judul="permit.Permit_Untuk_Tamu === 'Y' ? 'Hubungi tamu' : 'Hubungi pemohon'"
                            />
                        </template>
                        <div class="permit-requester-card">
                            <!-- Avatar & Identity Row -->
                            <div class="permit-requester-header">
                                <div
                                    class="permit-requester-avatar"
                                    :style="getRequesterAvatarStyle(permit)"
                                >
                                    {{ getInitials(permit.Nama_Pemohon_Tampil || permit.Nama_Pemohon) }}
                                </div>
                                <div class="permit-requester-identity">
                                    <div class="d-flex align-items-center gap-2">
                                        <span
                                            class="permit-requester-name text-truncate"
                                            :title="permit.Nama_Pemohon_Tampil || permit.Nama_Pemohon"
                                        >
                                            {{ permit.Nama_Pemohon_Tampil || permit.Nama_Pemohon || '-' }}
                                        </span>
                                        <span
                                            v-if="permit.Permit_Untuk_Tamu !== 'Y' && permit.Kode_Karyawan_Pemohon"
                                            class="permit-requester-nik-tag font-tabular flex-shrink-0"
                                        >
                                            Kode: {{ permit.Kode_Karyawan_Pemohon }}
                                        </span>
                                    </div>
                                    <div class="permit-requester-role text-xs text-truncate">
                                        <template v-if="permit.Permit_Untuk_Tamu === 'Y'">
                                            <span :title="permit.Instansi_Tamu || 'Tamu'">
                                                {{ permit.Instansi_Tamu || 'Tamu' }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span :title="permit.Jabatan || 'Pemohon Kendaraan'">
                                                {{ permit.Jabatan || 'Pemohon Kendaraan' }}
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Details Pods for Tamu (asalPengajuan items) -->
                            <template v-if="permit.Permit_Untuk_Tamu === 'Y'">
                                <div
                                    v-for="(baris, i) in asalPengajuan"
                                    :key="baris.label"
                                    class="permit-requester-dept-pod"
                                    :class="{
                                        'mt-2': i > 0,
                                        'permit-requester-dept-pod--clickable': baris.clickable,
                                    }"
                                    :role="baris.clickable ? 'button' : undefined"
                                    :tabindex="baris.clickable ? 0 : undefined"
                                    :title="baris.clickable ? 'Klik untuk membuka detail kunjungan tamu' : undefined"
                                    @click="baris.clickable ? openTamuDetail(baris.rawNo || permit.No_Transaksi_Tamu) : null"
                                    @keydown.enter="baris.clickable ? openTamuDetail(baris.rawNo || permit.No_Transaksi_Tamu) : null"
                                >
                                    <div class="permit-requester-dept-icon" :class="{ 'permit-requester-dept-icon--clickable': baris.clickable }">
                                        <HcisIcon :name="baris.icon" size="xs" />
                                    </div>
                                    <div class="permit-requester-dept-info flex-grow-1 min-w-0">
                                        <span class="permit-requester-dept-label">{{ baris.label }}</span>
                                        <div class="d-flex align-items-center justify-content-between gap-1.5 min-w-0">
                                            <span
                                                class="permit-requester-dept-val text-truncate"
                                                :class="{ 'font-tabular': baris.tabular, 'permit-requester-dept-val--link': baris.clickable }"
                                            >{{ baris.nilai }}</span>
                                            <span v-if="baris.clickable" class="permit-ref-link-hint flex-shrink-0">
                                                <HcisIcon name="box-arrow-up-right" size="2xs" class="me-1" />
                                                <span>Detail Tamu</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Department Spec Pod for Karyawan (or if Tamu has specific Department) -->
                            <div
                                v-if="permit.Departemen && permit.Departemen !== '-'"
                                class="permit-requester-dept-pod"
                                :class="{ 'mt-2': permit.Permit_Untuk_Tamu === 'Y' && asalPengajuan.length > 0 }"
                            >
                                <div class="permit-requester-dept-icon">
                                    <HcisIcon name="building" size="xs" />
                                </div>
                                <div class="permit-requester-dept-info">
                                    <span class="permit-requester-dept-label">Departemen / Divisi</span>
                                    <span class="permit-requester-dept-val">{{ permit.Departemen }}</span>
                                </div>
                            </div>

                            <!-- Phone Spec Pod for Karyawan -->
                            <div
                                v-if="permit.No_HP_Pemohon && permit.No_HP_Pemohon !== '-' && permit.Permit_Untuk_Tamu !== 'Y'"
                                class="permit-requester-dept-pod mt-2"
                            >
                                <div class="permit-requester-dept-icon">
                                    <HcisIcon name="telephone" size="xs" />
                                </div>
                                <div class="permit-requester-dept-info">
                                    <span class="permit-requester-dept-label">Nomor Kontak / WhatsApp</span>
                                    <span class="permit-requester-dept-val font-tabular">{{ permit.No_HP_Pemohon }}</span>
                                </div>
                            </div>
                        </div>
                    </HcisCard>

                    <!-- 3. Approval Workflow Section -->
                    <HcisCard
                        title="Alur Persetujuan"
                        description="Tahapan verifikasi otoritas dan catatan persetujuan."
                        icon="diagram-3-fill"
                        sentiment="brand"
                        variant="default"
                        size="sm"
                        divider
                    >
                        <div v-if="approvalTimelineItems && approvalTimelineItems.length" class="permit-approval-timeline-wrapper">
                            <HcisTimeline
                                :items="approvalTimelineItems"
                                direction="vertical"
                                variant="compact"
                                sentiment="brand"
                                :card="false"
                            />
                        </div>
                        <div v-else class="permit-auto-approved-box">
                            <div class="permit-auto-approved-badge">
                                <HcisIcon name="check-circle-fill" size="md" />
                            </div>
                            <div class="permit-auto-approved-content">
                                <div class="permit-auto-approved-title">Otorisasi Langsung / Otomatis</div>
                                <div class="permit-auto-approved-desc">
                                    Permit ini tidak memerlukan alur persetujuan berjenjang dan disetujui langsung oleh sistem.
                                </div>
                                <div v-if="permit.Tanggal_Mulai_Berlaku || permit.Tanggal_Pengajuan" class="permit-auto-approved-time font-tabular">
                                    <HcisIcon name="clock-history" size="xs" />
                                    <span>{{ formatFullDateTime(permit.Tanggal_Mulai_Berlaku || permit.Tanggal_Pengajuan) }}</span>
                                </div>
                            </div>
                        </div>
                    </HcisCard>
                </div>
            </template>

            <!-- ================================================================= -->
            <!-- RIGHT PANE: Specs, Duration, Documents, and Physical Checklist    -->
            <!-- ================================================================= -->
            <template #right>
                <div v-if="permit">
                    <!-- 0. Revocation Alert if Revoked -->
                    <HcisAlert
                        v-if="dicabut"
                        variant="danger"
                        appearance="soft"
                        icon="slash-circle-fill"
                        title="Akses permit ini telah dihapus"
                        class="mb-4"
                    >
                        <div class="permit-revoked-details mt-2">
                            <div v-if="permit.Dicabut_Oleh" class="permit-revoked-item">
                                <span class="permit-revoked-label">Dihapus oleh:</span>
                                <span class="permit-revoked-val">{{ permit.Dicabut_Oleh }}</span>
                            </div>
                            <div v-if="permit.Dicabut_Pada" class="permit-revoked-item">
                                <span class="permit-revoked-label">Waktu penghapusan:</span>
                                <span class="permit-revoked-val font-tabular">{{ formatFullDateTime(permit.Dicabut_Pada) }}</span>
                            </div>
                            <div v-if="permit.Alasan_Pencabutan" class="permit-revoked-item">
                                <span class="permit-revoked-label">Alasan:</span>
                                <span class="permit-revoked-val">{{ permit.Alasan_Pencabutan }}</span>
                            </div>
                        </div>
                    </HcisAlert>

                    <!-- 0b. Banner Pengajuan Sendiri (Four-Eyes Principle Governance) -->
                    <HcisAlert
                        v-if="isOwnPermit && isPending"
                        variant="warning"
                        appearance="soft"
                        icon="shield-lock-fill"
                        title="Persetujuan Memerlukan Verifikator SHE Lain"
                        class="mb-4 permit-own-alert"
                    >
                        <div class="permit-own-alert__content">
                            <p class="permit-own-alert__text">
                                Permit ini terdaftar atas nama akun Anda sendiri. Sesuai prinsip <strong>Four-Eyes Principle (Pemisahan Tugas)</strong>, Anda tidak dapat memutus atau menyetujui pengajuan milik sendiri.
                            </p>
                            <div class="permit-own-alert__actions mt-2">
                                <HcisButton
                                    label="Pelajari Kebijakan"
                                    size="xs"
                                    variant="secondary"
                                    leading-icon="info-circle"
                                    @click="dialogMilikSendiri = true"
                                />
                            </div>
                        </div>
                    </HcisAlert>

                    <!-- 1. Vehicle Specifications Grid Card -->
                    <!-- Selalu tampil: tile yang hilang diam-diam membuat verifikator
                         tak bisa membedakan "tidak diisi" dari "tidak ditampilkan". -->
                    <HcisCard
                        title="Data Pengajuan"
                        :description="permitTamu
                            ? 'Jenis izin dan data kendaraan tamu. Data kendaraan dilengkapi verifikator saat pemeriksaan di pos.'
                            : 'Seluruh isian dan pilihan pemohon saat mengajukan permit.'"
                        icon="car-front-fill"
                        sentiment="brand"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <template #actions>
                            <HcisButton
                                v-if="bolehKoreksi"
                                size="sm"
                                variant="outline"
                                leading-icon="pencil-square"
                                label="Koreksi Data"
                                @click="dialogKoreksi = true"
                            />
                        </template>
                        <div class="permit-specs-grid">
                            <!-- Jenis Permit + cakupan aksesnya: yang dicocokkan verifikator saat inspeksi -->
                            <div class="permit-spec-tile permit-spec-tile--full">
                                <span class="permit-spec-tile__label">Jenis Permit</span>
                                <div class="permit-spec-tile__val d-flex align-items-center gap-2 mt-1">
                                    <span class="permit-spec-color-dot" :style="{ backgroundColor: permit.Kode_Warna || '#6366f1' }" />
                                    <span class="fw-bold text-slate-900">{{ isiData(permit.Nama_Kategori) || '-' }}</span>
                                </div>
                                <div class="text-xs text-slate-600 mt-1">
                                    {{ isiData(permit.Deskripsi_Kategori) || 'Belum ada deskripsi akses untuk jenis permit ini di master.' }}
                                </div>
                            </div>

                            <!-- Nomor Plat -->
                            <div class="permit-spec-tile permit-spec-tile--plate">
                                <span class="permit-spec-tile__label">Nomor Plat</span>
                                <div class="permit-spec-tile__val mt-1">
                                    <span v-if="isiData(permit.No_Plat_Kendaraan)" class="permit-spec-plate-pill">
                                        <span class="permit-spec-plate-pill__code">{{ permit.No_Plat_Kendaraan }}</span>
                                    </span>
                                    <span v-else class="text-muted fst-italic text-xs">{{ labelDataKosong }}</span>
                                </div>
                            </div>

                            <!-- Jenis Kendaraan -->
                            <div class="permit-spec-tile">
                                <span class="permit-spec-tile__label">Jenis Kendaraan</span>
                                <div v-if="!isiData(permit.Nama_Vehicle_Type)" class="permit-spec-tile__val text-muted fst-italic text-xs mt-1">
                                    {{ isiData(permit.Jenis_Kendaraan_Tamu) ? `${permit.Jenis_Kendaraan_Tamu} (disebut tamu)` : labelDataKosong }}
                                </div>
                                <div v-else class="permit-spec-tile__val d-flex align-items-center gap-2 mt-1">
                                    <div class="permit-spec-tile__icon-box">
                                        <HcisIcon :name="getVehicleIcon(permit.Nama_Vehicle_Type)" size="xs" />
                                    </div>
                                    <span class="fw-bold text-slate-800">{{ permit.Nama_Vehicle_Type }}</span>
                                </div>
                            </div>

                            <!-- Merek / Model -->
                            <div class="permit-spec-tile">
                                <span class="permit-spec-tile__label">Merek / Model</span>
                                <div v-if="isiData(permit.Nama_Model_Manual || permit.Nama_Model_Kendaraan)" class="permit-spec-tile__val fw-bold text-slate-900 mt-1">
                                    {{ permit.Nama_Model_Manual || permit.Nama_Model_Kendaraan }}
                                </div>
                                <div v-else class="permit-spec-tile__val text-muted fst-italic text-xs mt-1">{{ labelDataKosong }}</div>
                            </div>

                            <!-- Tahun Pembuatan -->
                            <div class="permit-spec-tile">
                                <span class="permit-spec-tile__label">Tahun Pembuatan</span>
                                <div v-if="isiData(permit.Tahun_Kendaraan)" class="permit-spec-tile__val font-tabular fw-semibold text-slate-800 mt-1">
                                    {{ permit.Tahun_Kendaraan }}
                                </div>
                                <div v-else class="permit-spec-tile__val text-muted fst-italic text-xs mt-1">{{ labelDataKosong }}</div>
                            </div>

                            <!-- Warna Kendaraan -->
                            <div class="permit-spec-tile">
                                <span class="permit-spec-tile__label">Warna Kendaraan</span>
                                <div v-if="!isiData(permit.Warna_Manual)" class="permit-spec-tile__val text-muted fst-italic text-xs mt-1">{{ labelDataKosong }}</div>
                                <div v-else class="permit-spec-tile__val d-flex align-items-center gap-2 mt-1">
                                    <span
                                        class="permit-spec-color-dot"
                                        :style="{ backgroundColor: getVehicleColorHex(permit.Warna_Manual) }"
                                        :title="permit.Warna_Manual"
                                    />
                                    <span class="fw-semibold text-slate-800">{{ permit.Warna_Manual }}</span>
                                </div>
                            </div>

                            <!-- Masa Berlaku Permit -->
                            <div v-if="permit.Tanggal_Mulai_Berlaku || permit.Waktu_Mulai_Berlaku || permit.Tanggal_Kadaluarsa" class="permit-spec-tile permit-spec-tile--full">
                                <span class="permit-spec-tile__label">Masa Berlaku Izin</span>
                                <div class="permit-spec-tile__val font-tabular text-xs mt-1 d-flex align-items-center gap-2">
                                    <HcisIcon name="calendar-range" size="xs" class="text-indigo-500 flex-shrink-0" />
                                    <!-- Jendela berjam (permit tamu/mitra) lebih dulu: izin sehari
                                         "29 Sep s/d 29 Sep" tanpa jam tidak memberi tahu kapan ia habis. -->
                                    <span v-if="permit.Waktu_Mulai_Berlaku && permit.Waktu_Berakhir" class="text-slate-800">
                                        <strong>{{ formatWaktuKunjungan(permit.Waktu_Mulai_Berlaku) }}</strong>
                                        <span class="text-slate-400 mx-1">s/d</span>
                                        <strong>{{ formatWaktuKunjungan(permit.Waktu_Berakhir) }}</strong>
                                    </span>
                                    <span v-else-if="permit.Tanggal_Mulai_Berlaku" class="text-slate-800">
                                        <strong>{{ formatDate(permit.Tanggal_Mulai_Berlaku) }}</strong>
                                        <span class="text-slate-400 mx-1">s/d</span>
                                        <strong>{{ formatDate(permit.Tanggal_Kadaluarsa) }}</strong>
                                    </span>
                                    <span v-else class="text-muted fst-italic">
                                        Belum aktif (menunggu approval final)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Alasan / Catatan Pengajuan Callout -->
                        <div class="permit-reason-callout mt-3">
                            <div class="permit-reason-callout__header">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="permit-reason-callout__icon">
                                        <HcisIcon name="chat-quote-fill" size="xs" />
                                    </span>
                                    <span class="permit-reason-callout__title">Alasan / Catatan Pengajuan</span>
                                </div>
                                <span class="permit-reason-callout__tag">Catatan Pemohon</span>
                            </div>
                            <div class="permit-reason-callout__body mt-1">
                                <p v-if="isiData(permit.Keterangan_Pengajuan || permit.Keterangan)" class="permit-reason-callout__text mb-0">
                                    “{{ permit.Keterangan_Pengajuan || permit.Keterangan }}”
                                </p>
                                <p v-else class="text-muted fst-italic text-xs mb-0">
                                    {{ permitTamu ? 'Tidak ada catatan pada izin tamu ini.' : 'Pemohon tidak menulis catatan (kolom ini opsional).' }}
                                </p>
                            </div>
                        </div>

                        <div v-if="riwayatKoreksi.length" class="mt-3">
                            <span class="permit-spec-tile__label">Riwayat Koreksi</span>
                            <PermitKoreksiRiwayat :riwayat="riwayatKoreksi" class="mt-2" />
                        </div>
                    </HcisCard>

                    <!-- Jadwal inspeksi yang DIPILIH PEMOHON saat mengajukan (DDL-2026-123).
                         Tanpa kartu ini verifikator tidak tahu kapan dan di pos mana
                         kendaraannya akan datang. -->
                    <HcisCard
                        title="Jadwal Inspeksi"
                        :description="permitTamu
                            ? 'Izin tamu tidak memakai slot inspeksi; SHE berjaga menyambut tamu sesuai estimasi kunjungan yang diajukan.'
                            : 'Slot pemeriksaan fisik yang dipilih pemohon saat mengajukan.'"
                        icon="calendar-check"
                        sentiment="brand"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4 permit-review-jadwal-card"
                    >
                        <template v-if="jadwalInspeksi" #actions>
                            <div class="permit-jadwal-actions">
                                <HcisBadge :variant="jadwalInspeksiBadge.variant" size="xs" appearance="subtle" dot>
                                    {{ jadwalInspeksiBadge.label }}
                                </HcisBadge>
                                <!-- Pemindahan jadwal satu pintu di Agenda Inspeksi; di sini cukup jalan pintasnya. -->
                                <a
                                    v-if="isPending && !readOnly"
                                    class="permit-jadwal-link"
                                    :href="tautanAgendaInspeksi"
                                >
                                    <HcisIcon name="calendar2-week" size="2xs" />
                                    <span>Atur di Agenda</span>
                                </a>
                            </div>
                        </template>

                        <!-- Modern Ticket Card Layout -->
                        <div
                            v-if="jadwalInspeksiBermasalah && isPending"
                            class="permit-jadwal-alert mt-2 p-2.5 rounded-3 border bg-amber-50 border-amber-200 text-amber-900 text-xs d-flex align-items-start gap-2 mb-3"
                        >
                            <HcisIcon name="exclamation-triangle-fill" size="xs" class="text-amber-600 flex-shrink-0 mt-0.5" />
                            <div>
                                <strong>Pemeriksaan Fisik Belum Selesai:</strong>
                                <span> {{ jadwalInspeksiBermasalah }}</span>
                            </div>
                        </div>

                        <div v-if="jadwalInspeksi" class="permit-inspeksi__ticket">
                            <!-- Row 1: Waktu & Jam Slot -->
                            <div class="permit-inspeksi__ticket-row">
                                <div class="permit-inspeksi__ticket-icon is-calendar">
                                    <HcisIcon name="calendar2-check-fill" size="sm" />
                                </div>
                                <div class="permit-inspeksi__ticket-content">
                                    <span class="permit-inspeksi__ticket-label">Waktu Pemeriksaan Fisik</span>
                                    <div class="permit-inspeksi__datetime-wrap">
                                        <span class="permit-inspeksi__date font-tabular">{{ formatTanggalInspeksi(jadwalInspeksi) }}</span>
                                        <span class="permit-inspeksi__time-badge font-tabular">
                                            <HcisIcon name="clock-fill" size="2xs" />
                                            <span>{{ formatJamInspeksi(jadwalInspeksi) }} WIB</span>
                                        </span>
                                        <span v-if="jadwalInspeksi.shift" class="permit-inspeksi__shift-badge font-tabular">
                                            {{ jadwalInspeksi.shift }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="permit-inspeksi__ticket-divider" />

                            <!-- Row 2: Lokasi & Pos Inspeksi -->
                            <div class="permit-inspeksi__ticket-row">
                                <div class="permit-inspeksi__ticket-icon is-location">
                                    <HcisIcon name="geo-alt-fill" size="sm" />
                                </div>
                                <div class="permit-inspeksi__ticket-content">
                                    <span class="permit-inspeksi__ticket-label">Lokasi Pos Inspeksi</span>
                                    <div class="permit-inspeksi__pos-wrap">
                                        <strong class="permit-inspeksi__pos-name">{{ jadwalInspeksi.pos || '-' }}</strong>
                                        <span v-if="jadwalInspeksi.lokasi" class="permit-inspeksi__pos-tag">· {{ jadwalInspeksi.lokasi }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="permit-inspeksi__ticket-divider" />

                            <!-- Row 3: Riwayat Jadwal & Statistik Kehadiran -->
                            <div class="permit-inspeksi__ticket-row">
                                <div class="permit-inspeksi__ticket-icon is-history">
                                    <HcisIcon name="clock-history" size="sm" />
                                </div>
                                <div class="permit-inspeksi__ticket-content">
                                    <span class="permit-inspeksi__ticket-label">Riwayat Jadwal & Kehadiran</span>
                                    <div class="permit-inspeksi__chips-wrap font-tabular">
                                        <span class="permit-inspeksi__chip">
                                            <HcisIcon name="arrow-repeat" size="2xs" />
                                            Dijadwal ulang pemohon <strong>{{ jadwalInspeksi.jadwal_ulang_terpakai || 0 }}×</strong>
                                        </span>
                                        <span v-if="jadwalInspeksi.dipindah_petugas" class="permit-inspeksi__chip is-warning">
                                            <HcisIcon name="person-gear" size="2xs" />
                                            Dipindah petugas <strong>{{ jadwalInspeksi.dipindah_petugas }}×</strong>
                                        </span>
                                        <span
                                            class="permit-inspeksi__chip"
                                            :class="jadwalInspeksi.tidak_hadir > 0 ? 'is-danger' : 'is-muted'"
                                        >
                                            <HcisIcon name="person-x" size="2xs" />
                                            Tidak hadir <strong>{{ jadwalInspeksi.tidak_hadir || 0 }}×</strong>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Alert Notices if overdue or inspected -->
                            <div v-if="jadwalInspeksi.lewat_jadwal" class="permit-inspeksi__notice is-warning mt-1">
                                <HcisIcon name="exclamation-triangle-fill" size="xs" class="flex-shrink-0" />
                                <div class="permit-inspeksi__notice-text">
                                    <strong>Jadwal Sudah Lewat:</strong> Jadwal sudah lewat tanpa catatan inspeksi petugas SHE.
                                </div>
                            </div>
                            <div v-if="jadwalInspeksi.diinspeksi_oleh" class="permit-inspeksi__notice is-info mt-1">
                                <HcisIcon name="check-circle-fill" size="xs" class="flex-shrink-0" />
                                <div class="permit-inspeksi__notice-text">
                                    Diinspeksi oleh <strong>{{ jadwalInspeksi.diinspeksi_oleh }}</strong><template v-if="jadwalInspeksi.diinspeksi_at"> pada <strong>{{ formatFullDateTime(jadwalInspeksi.diinspeksi_at) }}</strong></template>.
                                </div>
                            </div>
                        </div>

                        <!-- Permit tamu: jam dari pengajuan kunjungannya, agar SHE tahu
                             kapan berjaga di pos untuk menginspeksi kendaraannya. -->
                        <div v-else-if="permitTamu && kunjunganTamu" class="permit-inspeksi__ticket" data-testid="estimasi-kunjungan-tamu">
                            <div class="permit-inspeksi__ticket-row">
                                <div class="permit-inspeksi__ticket-icon is-calendar">
                                    <HcisIcon name="box-arrow-in-right" size="sm" />
                                </div>
                                <div class="permit-inspeksi__ticket-content">
                                    <span class="permit-inspeksi__ticket-label">Estimasi Masuk</span>
                                    <div class="permit-inspeksi__datetime-wrap">
                                        <span class="permit-inspeksi__date font-tabular">{{ formatWaktuKunjungan(kunjunganTamu.estimasi_masuk) }}</span>
                                    </div>
                                    <div v-if="kunjunganTamu.aktual_masuk" class="permit-inspeksi__aktual-tag is-masuk font-tabular">
                                        <HcisIcon name="check-circle-fill" size="2xs" />
                                        <span>Aktual Masuk: {{ formatWaktuKunjungan(kunjunganTamu.aktual_masuk) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="permit-inspeksi__ticket-divider" />

                            <div class="permit-inspeksi__ticket-row">
                                <div class="permit-inspeksi__ticket-icon is-location">
                                    <HcisIcon name="box-arrow-right" size="sm" />
                                </div>
                                <div class="permit-inspeksi__ticket-content">
                                    <span class="permit-inspeksi__ticket-label">Estimasi Keluar</span>
                                    <div class="permit-inspeksi__datetime-wrap">
                                        <span class="permit-inspeksi__date font-tabular">{{ formatWaktuKunjungan(kunjunganTamu.estimasi_keluar) }}</span>
                                    </div>
                                    <div v-if="kunjunganTamu.aktual_keluar" class="permit-inspeksi__aktual-tag is-keluar font-tabular">
                                        <HcisIcon name="arrow-right-circle-fill" size="2xs" />
                                        <span>Aktual Keluar: {{ formatWaktuKunjungan(kunjunganTamu.aktual_keluar) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="permit-inspeksi__empty">
                            <HcisIcon name="calendar-x" size="md" class="text-slate-400 mb-1" />
                            <p class="text-muted fst-italic text-xs mb-0">
                                {{ permitTamu
                                    ? 'Tidak ada slot inspeksi untuk izin tamu.'
                                    : 'Permit ini tidak memiliki jadwal inspeksi — kemungkinan diajukan sebelum fitur jadwal inspeksi berlaku.' }}
                            </p>
                        </div>
                    </HcisCard>

                    <!-- 1b. Hasil inspeksi kendaraan — HANYA untuk izin sehari.
                         Permit karyawan sudah membawa data ini sejak pengajuan;
                         permit COKLAT tidak punya sama sekali, dan justru
                         verifikator yang mencatatnya saat memeriksa kendaraan
                         tamu di pos. Tanpa kartu ini permit tamu terbit ke
                         gerbang dengan merek, tahun, dan warna berisi '-'. -->
                    <HcisCard
                        v-if="butuhInspeksi && isPending"
                        title="Hasil Inspeksi Kendaraan"
                        description="Dicatat verifikator saat memeriksa kendaraan tamu. Wajib dilengkapi sebelum permit disetujui."
                        icon="clipboard-check"
                        sentiment="warning"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <div class="permit-inspeksi-grid">
                            <!-- Daftar modelnya lebih dari seratus baris. Dipilih dari
                                 dropdown datar, verifikator harus menggulir mencari satu
                                 nama di antara motor, mobil, dan truk yang bercampur;
                                 dikelompokkan per jenis kendaraan DAN bisa dicari, ia
                                 cukup mengetik dua huruf. -->
                            <HcisField
                                id="permit-inspeksi-model"
                                label="Merek / Model"
                                required
                                hint="Ketik untuk mencari, atau buka kelompok jenis kendaraannya."
                            >
                                <HcisTreeSelect
                                    :model-value="inspeksi.id_master_model || ''"
                                    :data="pohonModelKendaraan"
                                    placeholder="Pilih merek / model kendaraan..."
                                    :clearable="false"
                                    filterable
                                    @update:model-value="inspeksi.id_master_model = $event || null"
                                />
                            </HcisField>

                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <HcisField id="permit-inspeksi-warna" label="Warna Kendaraan" required>
                                        <HcisSelect
                                            id="permit-inspeksi-warna-select"
                                            :model-value="inspeksi.id_master_warna"
                                            placeholder="Pilih warna kendaraan..."
                                            :clearable="false"
                                            filterable
                                            size="md"
                                            @update:model-value="inspeksi.id_master_warna = $event || null"
                                        >
                                            <HcisSelectOption
                                                v-for="warna in masterColors"
                                                :key="warna.Id_Master_Color"
                                                :value="String(warna.Id_Master_Color)"
                                                :label="warna.Nama_Warna"
                                            />
                                        </HcisSelect>
                                    </HcisField>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <HcisField
                                        id="permit-inspeksi-tahun"
                                        label="Tahun Pembuatan"
                                        required
                                        hint="Pilih tahun pembuatan dari STNK."
                                    >
                                        <HcisSelect
                                            id="permit-inspeksi-tahun-select"
                                            :model-value="inspeksi.tahun_kendaraan ? Number(inspeksi.tahun_kendaraan) : null"
                                            placeholder="Pilih tahun pembuatan..."
                                            :clearable="false"
                                            filterable
                                            leading-icon="calendar-event"
                                            size="md"
                                            @update:model-value="inspeksi.tahun_kendaraan = $event ? Number($event) : null"
                                        >
                                            <HcisSelectOption
                                                v-for="th in daftarTahunKendaraan"
                                                :key="th"
                                                :value="th"
                                                :label="String(th)"
                                            />
                                        </HcisSelect>
                                    </HcisField>
                                </div>
                            </div>
                        </div>
                    </HcisCard>

                    <!-- 2. Rejection Reason Alert if rejected -->
                    <HcisAlert
                        v-if="permit.Status_Permit === 'REJECTED' && (permit.Alasan_Tolak || getRejectionComment())"
                        variant="danger"
                        appearance="soft"
                        title="Alasan Penolakan Pengajuan"
                        :description="permit.Alasan_Tolak || getRejectionComment()"
                        icon="exclamation-octagon-fill"
                        class="mb-4"
                    >
                    </HcisAlert>

                    <!-- 2b. Masa berlaku permit tamu — DITETAPKAN VERIFIKATOR.
                         Permit mitra datang tanpa tanggal sama sekali, dan permit
                         turunan kunjungan hanya membawa tanggal kunjungannya
                         sebagai usulan. Yang tahu sampai kapan tamu ini boleh
                         berada di area adalah orang yang memeriksanya di pos. -->
                    <HcisCard
                        v-if="permitTamu && isPending"
                        title="Masa Berlaku Permit"
                        description="Tentukan sejak kapan dan sampai kapan permit ini berlaku. Wajib diisi sebelum disetujui."
                        icon="calendar-range"
                        sentiment="warning"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <HcisField
                                    id="permit-berlaku-mulai"
                                    label="Mulai Berlaku"
                                    required
                                    hint="Tanggal dan jam tamu mulai diizinkan masuk."
                                >
                                    <HcisDatePicker
                                        v-model="masaBerlaku.mulai"
                                        type="datetime"
                                        placeholder="Pilih tanggal & jam mulai"
                                        format="DD MMM YYYY HH:mm"
                                        :clearable="false"
                                        :show-presets="false"
                                        :disabled-date="tanggalSudahLewat"
                                    />
                                </HcisField>
                            </div>
                            <div class="col-12 col-sm-6">
                                <HcisField
                                    id="permit-berlaku-akhir"
                                    label="Berakhir"
                                    required
                                    hint="Setelah jam ini izinnya otomatis kedaluwarsa."
                                >
                                    <HcisDatePicker
                                        v-model="masaBerlaku.akhir"
                                        type="datetime"
                                        placeholder="Pilih tanggal & jam berakhir"
                                        format="DD MMM YYYY HH:mm"
                                        :clearable="false"
                                        :show-presets="false"
                                        :disabled-date="tanggalSudahLewat"
                                    />
                                </HcisField>
                            </div>
                        </div>

                        <HcisAlert
                            v-if="masalahMasaBerlaku"
                            variant="danger"
                            appearance="soft"
                            size="sm"
                            icon="exclamation-triangle-fill"
                            :description="masalahMasaBerlaku"
                            class="mt-3"
                        />
                        <p v-else-if="ringkasanMasaBerlaku" class="permit-berlaku-ringkas mt-3 mb-0">
                            Berlaku {{ ringkasanMasaBerlaku }}.
                        </p>
                    </HcisCard>

                    <!-- 3. Masa Berlaku Selection (If Final Approval / Validity Required) -->
                    <HcisCard
                        v-if="!permitTamu && ((isPending && isFinalApproval) || isValidityRequired)"
                        title="Masa Berlaku Permit"
                        description="Pilih jangka waktu durasi berlakunya permit kendaraan ini."
                        icon="calendar-check"
                        sentiment="warning"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <div class="permit-duration-box">
                            <HcisField
                                id="permit-duration-select"
                                label="Pilih Durasi Permit"
                                required
                                hint="Durasi mulai dihitung sejak tanggal persetujuan final."
                            >
                                <HcisSelect
                                    id="permit-duration"
                                    :model-value="selectedDurationId"
                                    placeholder="Pilih masa durasi aktif..."
                                    :clearable="false"
                                    :filterable="false"
                                    size="md"
                                    @update:model-value="selectedDurationId = String($event || '')"
                                >
                                    <HcisSelectOption
                                        v-for="duration in durations"
                                        :key="duration.Id_Durasi_Permit"
                                        :value="String(duration.Id_Durasi_Permit)"
                                        :label="duration.Nama_Durasi"
                                    />
                                </HcisSelect>
                            </HcisField>
                        </div>
                    </HcisCard>

                    <!-- 4a. Pengajuan mitra B2B: dokumen kendaraan tidak berlaku.
                         Tanpa panel ini verifikator membuka pengajuan tanpa foto
                         STNK/SIM dan menyimpulkan pemohonnya lalai, lalu
                         menolaknya — kegagalan yang paling mungkin terjadi di
                         hari pertama. -->
                    <HcisCard
                        v-if="dariMitra"
                        title="Pengajuan mitra B2B"
                        icon="plug"
                        sentiment="info"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <div class="permit-mitra-callout mb-3">
                            <HcisIcon name="info-circle-fill" size="xs" class="text-cyan-600 flex-shrink-0 mt-0.5" />
                            <div class="permit-mitra-callout__content">
                                Diajukan lewat API oleh <strong>{{ permit?.Nama_Mitra || 'mitra' }}</strong>;
                                data dan dokumen kendaraan tidak dikirim mitra — verifikator yang mencatatnya saat pemeriksaan.
                            </div>
                        </div>
                        <div class="permit-mitra-grid">
                            <div class="permit-mitra-tile">
                                <span class="permit-mitra-tile__label">Tamu</span>
                                <div class="permit-mitra-tile__val font-tabular fw-bold text-slate-900">{{ permit?.Nama_Tamu || '-' }}</div>
                            </div>
                            <div class="permit-mitra-tile">
                                <span class="permit-mitra-tile__label">Instansi</span>
                                <div class="permit-mitra-tile__val fw-bold text-slate-900">{{ permit?.Instansi_Tamu || '-' }}</div>
                            </div>
                            <div class="permit-mitra-tile">
                                <span class="permit-mitra-tile__label">Jendela berlaku</span>
                                <div class="permit-mitra-tile__val font-tabular text-xs text-slate-800">{{ jendelaPermit }}</div>
                            </div>
                            <div class="permit-mitra-tile">
                                <span class="permit-mitra-tile__label">Referensi mitra</span>
                                <div class="permit-mitra-tile__val font-tabular font-mono text-xs fw-bold text-slate-800">{{ permit?.Referensi_Mitra || '-' }}</div>
                            </div>
                        </div>
                    </HcisCard>

                    <!-- 4. Verification Documents Card -->
                    <HcisCard
                        title="Dokumen & Foto Lampiran"
                        :description="lampiranWajib
                            ? 'Wajib: foto kendaraan, STNK, dan SIM tamu diunggah verifikator saat pemeriksaan di pos. Permit tidak bisa disetujui tanpa ketiganya.'
                            : permitTamu
                                ? 'Berkas kelengkapan kendaraan tamu. Diunggah verifikator saat pemeriksaan di pos, bukan oleh pemohon.'
                                : 'Foto kendaraan, STNK, dan SIM yang diunggah pemohon saat mengajukan. Verifikator boleh mengganti berkas yang tidak terbaca.'"
                        icon="folder-check"
                        :sentiment="hasDocErrors ? 'danger' : 'teal'"
                        variant="default"
                        size="sm"
                        divider
                        class="mb-4"
                    >
                        <template #actions>
                            <HcisBadge
                                v-if="hasDocErrors"
                                variant="danger"
                                size="xs"
                                appearance="solid"
                                dot
                            >
                                {{ countDocErrors }} Berkas Ditolak
                            </HcisBadge>
                            <HcisBadge
                                v-else-if="adaBerkasTertunda"
                                variant="warning"
                                size="xs"
                                appearance="soft"
                                dot
                            >
                                {{ countBerkasTertunda }} Menunggu Disimpan
                            </HcisBadge>
                            <HcisBadge
                                v-else
                                variant="neutral"
                                size="xs"
                                appearance="subtle"
                            >
                                {{ jumlahDokumenTerlampir }}/3 Terlampir
                            </HcisBadge>
                        </template>

                        <!-- Alert jika ada berkas yang ditolak validasi -->
                        <HcisAlert
                            v-if="hasDocErrors"
                            variant="danger"
                            appearance="soft"
                            title="Lampiran Dokumen Memerlukan Perbaikan"
                            :description="`Terdapat ${countDocErrors} berkas lampiran yang ditolak sistem. Silakan periksa pesan galat pada kotak bertanda merah dan pilih berkas pengganti yang valid (JPG, JPEG, PNG, WEBP, PDF - maks. 5 MB).`"
                            icon="exclamation-octagon-fill"
                            class="mb-3"
                        />

                        <!-- Catatan berkas yang menunggu disimpan -->
                        <div v-else-if="adaBerkasTertunda" class="visitor-docs-pending-banner">
                            <div class="visitor-docs-pending-banner__icon">
                                <HcisIcon name="clock-history" size="xs" />
                            </div>
                            <div class="visitor-docs-pending-banner__content">
                                <span class="visitor-docs-pending-banner__title">
                                    {{ countBerkasTertunda }} berkas baru siap disimpan bersama persetujuan.
                                </span>
                                <span class="visitor-docs-pending-banner__subtitle">
                                    Lampiran ditahan sementara dan akan diunggah otomatis saat Anda menekan tombol Setujui.
                                </span>
                            </div>
                        </div>

                        <!-- Slot unggah verifikator menggunakan komponen HcisUpload -->
                        <div v-if="isPending && !readOnly" class="permit-docs-slots">
                            <div class="visitor-docs-list">
                                <div
                                    v-for="slot in slotDokumen"
                                    :key="slot.jenis"
                                    class="visitor-doc-card"
                                    :class="{
                                        'is-error': Boolean(docErrors[slot.jenis]),
                                        'is-staged': Boolean(berkasTertunda[slot.jenis]) && !docErrors[slot.jenis],
                                        'is-existing': Boolean(dokumenTerlampir(slot.jenis)) && !berkasTertunda[slot.jenis] && !docErrors[slot.jenis],
                                        'is-empty': !berkasTertunda[slot.jenis] && !dokumenTerlampir(slot.jenis) && !docErrors[slot.jenis],
                                    }"
                                >
                                    <div class="visitor-doc-card__header">
                                        <div class="visitor-doc-card__title-wrap">
                                            <div
                                                class="visitor-doc-card__icon"
                                                :class="docErrors[slot.jenis] ? 'is-danger' : `is-${getSlotSentiment(slot.jenis)}`"
                                            >
                                                <HcisIcon
                                                    :name="docErrors[slot.jenis] ? 'exclamation-circle-fill' : getSlotIcon(slot.jenis)"
                                                    size="xs"
                                                />
                                            </div>
                                            <div class="visitor-doc-card__meta">
                                                <div class="visitor-doc-card__title" :class="{ 'text-danger': Boolean(docErrors[slot.jenis]) }">
                                                    {{ slot.label }}
                                                </div>
                                                <div class="visitor-doc-card__desc">{{ getSlotSubtext(slot.jenis) }}</div>
                                            </div>
                                        </div>
                                        <div class="visitor-doc-card__badge-wrap">
                                            <HcisBadge
                                                v-if="docErrors[slot.jenis]"
                                                variant="danger"
                                                size="xs"
                                                appearance="solid"
                                                dot
                                            >
                                                Format Ditolak
                                            </HcisBadge>
                                            <HcisBadge
                                                v-else-if="berkasTertunda[slot.jenis]"
                                                variant="warning"
                                                size="xs"
                                                appearance="soft"
                                                dot
                                            >
                                                Siap Disimpan
                                            </HcisBadge>
                                            <template v-else-if="dokumenTerlampir(slot.jenis)">
                                                <HcisBadge variant="success" size="xs" appearance="soft" dot>
                                                    Terlampir
                                                </HcisBadge>
                                                <button
                                                    type="button"
                                                    class="permit-doc-view-pill ms-1"
                                                    :title="`Lihat ${slot.label}`"
                                                    @click.stop="openFileViewer(dokumenSlot(slot.jenis))"
                                                >
                                                    <HcisIcon name="eye-fill" size="2xs" />
                                                    <span>Lihat</span>
                                                </button>
                                            </template>
                                            <HcisBadge
                                                v-else
                                                :variant="lampiranWajib ? 'danger' : 'neutral'"
                                                size="xs"
                                                :appearance="lampiranWajib ? 'soft' : 'subtle'"
                                                :data-testid="lampiranWajib ? 'lampiran-wajib-kosong' : undefined"
                                            >
                                                {{ lampiranWajib ? 'Wajib' : 'Belum Ada' }}
                                            </HcisBadge>
                                        </div>
                                    </div>

                                    <!-- Pesan Galat Spesifik Slot -->
                                    <div v-if="docErrors[slot.jenis]" class="visitor-doc-card__error-banner">
                                        <HcisIcon name="exclamation-triangle-fill" size="xs" class="flex-shrink-0" />
                                        <span class="visitor-doc-card__error-text">{{ docErrors[slot.jenis] }}</span>
                                    </div>

                                    <div class="visitor-doc-card__uploader">
                                        <HcisUpload
                                            :model-value="getUploadFileList(slot.jenis)"
                                            :accept="getSlotAccept(slot.jenis)"
                                            :limit="1"
                                            :max-size="5"
                                            layout="bar"
                                            drag
                                            :icon="docErrors[slot.jenis] ? 'cloud-arrow-up-fill' : getSlotIcon(slot.jenis)"
                                            :sentiment="docErrors[slot.jenis] ? 'danger' : getSlotSentiment(slot.jenis)"
                                            :hint="getSlotHint(slot.jenis)"
                                            variant="glass"
                                            @change="handleUploadChange(slot.jenis, $event)"
                                            @remove="handleUploadRemove(slot.jenis)"
                                            @trigger="pilihBerkas(slot.jenis)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tampilan Dokumen Saat ReadOnly / Sudah Diproses -->
                        <template v-else>
                            <HcisEmptyState
                                v-if="!dokumens || dokumens.length === 0"
                                title="Tidak ada berkas dokumen terlampir"
                                :description="permitTamu
                                    ? 'Izin tamu tidak membawa dokumen dari pemohon, dan verifikator tidak mengunggah berkas saat pemeriksaan.'
                                    : 'Pemohon tidak mengunggah dokumen, dan verifikator tidak menambahkan berkas saat review.'"
                                icon="file-earmark-x"
                                size="sm"
                                variant="dashed"
                                sentiment="neutral"
                                class="my-2"
                            />
                            <div v-else class="permit-docs-list">
                                <div
                                    v-for="(doc, idx) in dokumens"
                                    :key="doc.Id_Permit_Dokumen || idx"
                                    class="permit-doc-item"
                                    @click="openFileViewer(doc)"
                                >
                                    <!-- Left: File Type Icon Box -->
                                    <div class="permit-doc-icon-box" :class="getDocIconBg(doc.Jenis_Dokumen)">
                                        <HcisIcon :name="getDocIcon(doc.Jenis_Dokumen)" size="sm" />
                                    </div>

                                    <!-- Middle: Title, Ext Badge & Filename -->
                                    <div class="permit-doc-info">
                                        <div class="permit-doc-main-row">
                                            <span class="permit-doc-title text-truncate" :title="getDocTitle(doc)">
                                                {{ getDocTitle(doc) }}
                                            </span>
                                            <span class="permit-doc-ext-tag">{{ getDocExtension(doc) }}</span>
                                        </div>
                                        <div class="permit-doc-sub-row text-truncate">
                                            <HcisIcon name="paperclip" size="2xs" class="text-slate-400 flex-shrink-0" />
                                            <span class="text-truncate">{{ getDocFileName(doc) || getDocSubtitle(doc) || 'Dokumen Terlampir' }}</span>
                                        </div>
                                    </div>

                                    <!-- Right: Action Button -->
                                    <div class="permit-doc-action">
                                        <button
                                            type="button"
                                            class="permit-doc-view-pill"
                                            title="Buka Pratinjau Dokumen"
                                            @click.stop="openFileViewer(doc)"
                                        >
                                            <HcisIcon name="eye-fill" size="2xs" />
                                            <span class="d-none d-sm-inline">Lihat</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </HcisCard>

                    <!-- 5. Checklist Pemeriksaan Fisik Kendaraan -->
                    <HcisCard
                        title="Checklist Pemeriksaan Fisik"
                        description="Daftar parameter kelayakan fisik kendaraan sebelum disetujui."
                        icon="list-check"
                        sentiment="brand"
                        variant="default"
                        size="sm"
                        divider
                        class="permit-checklist-card"
                    >
                        <template #badge>
                            <HcisBadge
                                v-if="isPending && checklists && checklists.length"
                                :variant="hasUnansweredRequired ? 'warning' : 'success'"
                                size="xs"
                                :appearance="hasUnansweredRequired ? 'soft' : 'solid'"
                                dot
                            >
                                {{ jumlahChecklistTerjawab }}/{{ checklists.length }} {{ hasUnansweredRequired ? 'dijawab' : 'lengkap' }}
                            </HcisBadge>
                        </template>

                        <template #actions>
                            <!-- Hanya mengisi yang BELUM dijawab: jawaban "Tidak" yang sudah
                                 dipilih verifikator tidak boleh tertimpa satu klik. -->
                            <HcisButton
                                v-if="isPending && !readOnly && adaChecklistKosong"
                                size="sm"
                                variant="ghost"
                                leading-icon="check2-all"
                                label="Tandai semua Ya"
                                class="chk-mark-all-btn"
                                @click="tandaiSemuaYa"
                            />
                        </template>

                        <p v-if="!checklists || !checklists.length" class="text-muted fst-italic text-xs mb-0">
                            Tidak ada pertanyaan pemeriksaan untuk kombinasi jenis permit dan jenis kendaraan ini.
                            Pertanyaan diatur di master checklist oleh admin.
                        </p>
                        <div v-else class="chk-container">
                            <!-- Visual Progress Bar -->
                            <div class="chk-progress-bar-wrap">
                                <div class="chk-progress-track">
                                    <div
                                        class="chk-progress-fill"
                                        :class="{ 'is-complete': !hasUnansweredRequired && isPending }"
                                        :style="{ width: `${(jumlahChecklistTerjawab / checklists.length) * 100}%` }"
                                    />
                                </div>
                                <div class="chk-progress-info">
                                    <span class="chk-progress-counter">
                                        Progres: <strong>{{ jumlahChecklistTerjawab }}</strong> dari <strong>{{ checklists.length }}</strong> parameter
                                    </span>
                                    <span v-if="!hasUnansweredRequired && isPending" class="chk-progress-status text-emerald">
                                        <HcisIcon name="patch-check-fill" size="2xs" /> Semua parameter lengkap
                                    </span>
                                    <span v-else-if="unansweredCount > 0 && isPending" class="chk-progress-status text-amber">
                                        <HcisIcon name="exclamation-circle-fill" size="2xs" /> {{ unansweredCount }} wajib diisi
                                    </span>
                                </div>
                            </div>

                            <!-- Daftar ringan, bukan tumpukan kartu: satu baris per pertanyaan,
                                 penanda warna hanya di tepi kiri supaya mata tertuju ke pertanyaan. -->
                            <ol class="chk-list">
                                <li
                                    v-for="(item, idx) in checklists"
                                    :key="item.question_id || idx"
                                    class="chk-item"
                                    :class="{
                                        'is-ya': item.localJawaban === 'YA',
                                        'is-tidak': item.localJawaban === 'TIDAK',
                                        'is-na': item.localJawaban === 'NA',
                                        'is-kosong': isPending && !item.localJawaban && item.Wajib_Diisi !== 'T',
                                    }"
                                >
                                    <div class="chk-item__row">
                                        <div class="chk-item__question">
                                            <span class="chk-item__num" :title="`Parameter #${idx + 1}`">{{ idx + 1 }}</span>
                                            <span class="chk-item__text">
                                                {{ item.Pertanyaan }}
                                                <span v-if="item.Wajib_Diisi === 'Y'" class="chk-item__wajib" title="Wajib diisi">*</span>
                                            </span>
                                        </div>

                                        <!-- Tiga pilihan tetap: satu ketukan, semuanya terlihat. -->
                                        <div
                                            v-if="isPending"
                                            class="chk-seg"
                                            role="radiogroup"
                                            :aria-label="`Jawaban: ${item.Pertanyaan}`"
                                        >
                                            <label
                                                v-for="opsi in opsiJawabanChecklist"
                                                :key="opsi.value"
                                                class="chk-seg__opt"
                                                :class="[`chk-seg__opt--${opsi.tone}`, { 'is-active': item.localJawaban === opsi.value }]"
                                            >
                                                <input
                                                    type="radio"
                                                    class="chk-seg__input"
                                                    :name="`chk-answer-${item.question_id}`"
                                                    :value="opsi.value"
                                                    :checked="item.localJawaban === opsi.value"
                                                    @change="pilihJawabanChecklist(item, opsi.value)"
                                                />
                                                <HcisIcon :name="opsi.icon" size="2xs" />
                                                <span>{{ opsi.label }}</span>
                                            </label>
                                        </div>
                                        <HcisBadge
                                            v-else
                                            :variant="item.Jawaban === 'YA' ? 'success' : item.Jawaban === 'TIDAK' ? 'danger' : 'neutral'"
                                            size="xs"
                                            appearance="subtle"
                                        >
                                            {{ labelJawabanChecklist(item.Jawaban) }}
                                        </HcisBadge>
                                    </div>

                                    <!-- Catatan dilipat sampai dibutuhkan; "Tidak" membukanya otomatis. -->
                                    <template v-if="isPending">
                                        <div v-if="item.tampilCatatan" class="chk-item__note">
                                            <div class="chk-item__note-header">
                                                <span v-if="item.localJawaban === 'TIDAK'" class="chk-item__note-label text-danger">
                                                    <HcisIcon name="exclamation-circle-fill" size="2xs" /> Alasan Tidak Memenuhi (Wajib):
                                                </span>
                                                <span v-else class="chk-item__note-label text-slate-500">
                                                    <HcisIcon name="pencil-square" size="2xs" /> Catatan Tambahan (Opsional):
                                                </span>
                                                <button
                                                    v-if="item.localJawaban !== 'TIDAK'"
                                                    type="button"
                                                    class="chk-item__note-dismiss"
                                                    title="Tutup catatan"
                                                    @click="item.tampilCatatan = false"
                                                >
                                                    <HcisIcon name="x" size="2xs" />
                                                </button>
                                            </div>
                                            <HcisField
                                                :id="`chk-note-${item.question_id}`"
                                                :model-value="item.localCatatan"
                                                :placeholder="item.localJawaban === 'TIDAK' ? 'Jelaskan apa yang tidak memenuhi standar kelayakan...' : 'Tuliskan catatan verifikator (opsional)...'"
                                                size="sm"
                                                clearable
                                                @update:model-value="item.localCatatan = $event"
                                            />
                                        </div>
                                        <button
                                            v-else
                                            type="button"
                                            class="chk-item__note-toggle"
                                            @click="item.tampilCatatan = true"
                                        >
                                            <HcisIcon name="plus" size="2xs" /> {{ item.localCatatan ? 'Ubah catatan' : 'Tambah catatan' }}
                                        </button>
                                    </template>
                                    <div v-else-if="item.Catatan" class="chk-item__note-readonly">
                                        <HcisIcon name="chat-left-dots-fill" size="2xs" class="me-1.5 text-slate-400 flex-shrink-0" />
                                        <span>{{ item.Catatan }}</span>
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </HcisCard>
                </div>
            </template>

            <!-- ================================================================= -->
            <!-- FOOTER ACTIONS                                                    -->
            <!-- ================================================================= -->
            <template #footer>
                <div class="permit-modal-footer-wrapper">
                    <div
                        v-if="isOwnPermit && isPending"
                        class="permit-footer-own-notice"
                        role="button"
                        tabindex="0"
                        title="Klik untuk melihat kebijakan Four-Eyes Principle"
                        @click="dialogMilikSendiri = true"
                        @keydown.enter="dialogMilikSendiri = true"
                    >
                        <div class="permit-footer-own-notice__icon">
                            <HcisIcon name="shield-lock-fill" size="xs" />
                        </div>
                        <div class="permit-footer-own-notice__text">
                            <strong>Pengajuan Sendiri</strong>
                            <span>Persetujuan butuh verifikator lain. <u>Info</u></span>
                        </div>
                    </div>
                    <div class="permit-modal-footer-actions">
                        <HcisButton
                            v-if="isPending"
                            label="Tolak"
                            leading-icon="x-circle"
                            variant="danger-quiet"
                            size="md"
                            class="permit-footer-btn permit-footer-btn--reject"
                            :disabled="submitLoading"
                            @click="rejectAlert"
                        />
                        <HcisButton
                            v-if="isPending"
                            :label="submitLoading ? 'Menyimpan...' : 'Setujui Permit'"
                            leading-icon="check-circle-fill"
                            variant="success"
                            size="md"
                            class="permit-footer-btn permit-footer-btn--approve"
                            :loading="submitLoading"
                            :disabled="submitLoading || hasUnansweredRequired || inspeksiBelumLengkap || lampiranWajibKurang.length > 0 || masaBerlakuBelumLengkap || Boolean(jadwalInspeksiBermasalah) || (isFinalApproval && !permitTamu && !selectedDurationId)"
                            :title="isOwnPermit ? 'Anda tidak dapat menyetujui pengajuan Anda sendiri. Minta verifikator lain untuk memutusnya.' : judulTombolSetujui"
                            @click="approveAlert"
                        />
                        <HcisButton
                            v-if="isValidityRequired"
                            :label="submitLoading ? 'Menyimpan...' : 'Aktifkan Masa Berlaku'"
                            leading-icon="calendar-check"
                            variant="success"
                            size="md"
                            class="permit-footer-btn permit-footer-btn--activate"
                            :loading="submitLoading"
                            :disabled="submitLoading || !selectedDurationId"
                            @click="activateLegacyPermit"
                        />
                        <HcisButton
                            v-if="bisaCetakKartu"
                            :label="memuatKartu ? 'Menyiapkan...' : 'Cetak Kartu'"
                            variant="secondary"
                            size="md"
                            leading-icon="printer"
                            class="permit-footer-btn permit-footer-btn--print"
                            :loading="memuatKartu"
                            :disabled="memuatKartu"
                            data-testid="permit-cetak-kartu"
                            @click="cetakKartu"
                        />
                        <HcisButton
                            v-if="canRevoke"
                            label="Hapus akses"
                            variant="danger"
                            size="md"
                            leading-icon="slash-circle"
                            class="permit-footer-btn permit-footer-btn--revoke"
                            @click="$emit('minta-revoke', permit)"
                        />
                        <HcisButton
                            v-if="!isPending && !isValidityRequired && !canRevoke"
                            label="Tutup"
                            variant="ghost"
                            size="md"
                            class="permit-footer-btn permit-footer-btn--close"
                            @click="$emit('close')"
                        />
                    </div>
                </div>
            </template>
        </HcisDualPaneDialog>

        <!-- ================================================================= -->
        <!-- HCIS FILE VIEWER LIGHTBOX (MULTI-FILE PREVIEW, ZOOM, ROTATE, NEXT) -->
        <!-- ================================================================= -->
        <HcisFileViewer
            v-if="showFileViewer"
            v-model="showFileViewer"
            :files="viewerFiles"
            :initial-index="viewerInitialIndex"
            :allow-download="true"
            :allow-print="true"
            :allow-external-link="true"
        />

        <!-- ================================================================= -->
        <!-- NESTED VISITOR / TAMU DETAIL MODAL -->
        <!-- ================================================================= -->
        <TamuReviewModal
            v-if="showTamuModal"
            :show="showTamuModal"
            :tamu-id="selectedTamuId"
            detail-endpoint="/laporanvisitor-permit-tamu"
            :read-only="true"
            @close="closeTamuModal"
        />

        <PermitKoreksiDialog
            v-if="permit && bolehKoreksi"
            v-model="dialogKoreksi"
            :permit="permit"
            @tersimpan="loadDetail"
        />

        <!-- ================================================================= -->
        <!-- DIALOG KONFIRMASI PERSETUJUAN AWAL (SEBELUM JADWAL INSPEKSI TIBA) -->
        <!-- ================================================================= -->
        <HcisDialog
            v-if="dialogKonfirmasiAwal"
            v-model="dialogKonfirmasiAwal"
            title="Setujui sebelum jadwal inspeksi?"
            kicker="Visitor · Meja Verifikasi"
            sentiment="warning"
            icon="calendar-check"
            icon-variant="boxed"
            size="md"
            header-divider
            sticky-footer
            @close="tutupKonfirmasiAwal(false)"
        >
            <template #badge>
                <HcisBadge label="Jadwal Belum Tiba" variant="warning" appearance="subtle" dot pulse size="sm" />
            </template>

            <div class="permit-early-dialog-content">
                <!-- 1. Ticket Informasi Jadwal Asli -->
                <div class="permit-early-ticket">
                    <div class="permit-early-ticket__icon">
                        <HcisIcon name="calendar2-event-fill" size="md" />
                    </div>
                    <div class="permit-early-ticket__body">
                        <span class="permit-early-ticket__label">Jadwal Terdaftar Pemohon</span>
                        <div class="permit-early-ticket__time font-tabular">
                            {{ konfirmasiAwalData?.waktu || '-' }}
                        </div>
                        <div class="permit-early-ticket__pos">
                            <HcisIcon name="geo-alt-fill" size="2xs" />
                            <strong>{{ konfirmasiAwalData?.pos || 'Pos SHE' }}</strong>
                            <span class="permit-early-ticket__status-tag">· Hadir Lebih Awal</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Konsekuensi Persetujuan -->
                <div class="permit-early-impacts">
                    <div class="permit-early-impact-item">
                        <div class="permit-early-impact-item__icon is-success">
                            <HcisIcon name="check-circle-fill" size="xs" />
                        </div>
                        <div class="permit-early-impact-item__text">
                            <strong>Inspeksi Fisik Ditandai Selesai</strong>
                            <span>Persetujuan ini mencatat bahwa kendaraan telah selesai diperiksa secara fisik di pos.</span>
                        </div>
                    </div>
                    <div class="permit-early-impact-item">
                        <div class="permit-early-impact-item__icon is-info">
                            <HcisIcon name="arrow-repeat" size="xs" />
                        </div>
                        <div class="permit-early-impact-item__text">
                            <strong>Slot Jadwal Otomatis Dilepas</strong>
                            <span>Slot booking pada jam tersebut akan dibebaskan kembali untuk kuota antrean lainnya.</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Peringatan Checklist Fisik -->
                <div class="permit-early-callout">
                    <HcisIcon name="exclamation-triangle-fill" size="sm" class="flex-shrink-0" />
                    <div class="permit-early-callout__text">
                        <strong>Verifikasi Kelaikan Fisik:</strong>
                        <span>Pastikan checklist fisik dan data kendaraan pemohon sudah diverifikasi dengan benar di pos sebelum menyetujui.</span>
                        <div v-if="adaBerkasTertunda" class="permit-early-callout__sub mt-1">
                            <HcisIcon name="file-earmark-check-fill" size="2xs" />
                            <span>Lampiran berkas/foto baru yang Anda pilih akan ikut disimpan permanen bersama persetujuan ini.</span>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="permit-early-actions">
                    <HcisButton
                        label="Periksa Kembali"
                        variant="secondary"
                        size="sm"
                        @click="tutupKonfirmasiAwal(false)"
                    />
                    <HcisButton
                        label="Ya, Tetap Setujui"
                        variant="primary"
                        sentiment="warning"
                        leading-icon="check-circle-fill"
                        :loading="submitLoading"
                        size="sm"
                        @click="tutupKonfirmasiAwal(true)"
                    />
                </div>
            </template>
        </HcisDialog>

        <!-- ================================================================= -->
        <!-- DIALOG LARANGAN PERSETUJUAN PENGAJUAN SENDIRI (FOUR-EYES PRINCIPLE) -->
        <!-- ================================================================= -->
        <HcisDialog
            v-if="dialogMilikSendiri"
            v-model="dialogMilikSendiri"
            title="Persetujuan Tidak Dapat Diproses"
            kicker="Visitor · Kebijakan Persetujuan & Tata Kelola"
            sentiment="warning"
            icon="shield-lock"
            icon-variant="boxed"
            size="md"
            header-divider
            sticky-footer
            @close="dialogMilikSendiri = false"
        >
            <template #badge>
                <HcisBadge label="Pengajuan Sendiri" variant="warning" appearance="subtle" dot size="sm" />
            </template>

            <div class="permit-own-dialog-content">
                <!-- 1. Ticket Informasi Pemohon vs Akun Pengguna -->
                <div class="permit-own-ticket">
                    <div class="permit-own-ticket__icon">
                        <HcisIcon name="person-badge-fill" size="md" />
                    </div>
                    <div class="permit-own-ticket__body">
                        <span class="permit-own-ticket__label">Pemohon Terdaftar</span>
                        <div class="permit-own-ticket__name">
                            {{ permit?.Nama_Pemohon || permit?.Nama_Karyawan_Pemohon || 'Akun Anda' }}
                        </div>
                        <div class="permit-own-ticket__meta">
                            <span v-if="permit?.Kode_Karyawan_Pemohon" class="permit-own-ticket__id font-tabular">
                                ID: {{ permit.Kode_Karyawan_Pemohon }}
                            </span>
                            <span v-if="permit?.No_Transaksi" class="permit-own-ticket__ref font-tabular">
                                · No. {{ permit.No_Transaksi }}
                            </span>
                        </div>
                        <div class="permit-own-ticket__match-badge">
                            <HcisIcon name="person-x-fill" size="2xs" />
                            <span>Teridentifikasi sebagai akun Anda sendiri</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Penjelasan Kebijakan (Four-Eyes Principle) -->
                <div class="permit-own-policy">
                    <div class="permit-own-policy__header">
                        <div class="permit-own-policy__icon">
                            <HcisIcon name="shield-check" size="xs" />
                        </div>
                        <strong>Prinsip Pemisahan Tugas (Four-Eyes Principle)</strong>
                    </div>
                    <p class="permit-own-policy__desc">
                        Sesuai standar tata kelola dan keselamatan SHE, verifikator tidak diperkenankan memeriksa dan menyetujui (<em>self-approve</em>) pengajuan izin permit milik sendiri demi menjamin independensi, objektivitas pengawasan, dan validitas hasil inspeksi fisik kendaraan.
                    </p>
                </div>

                <!-- 3. Tindakan Solutif Selanjutnya -->
                <div class="permit-own-steps">
                    <div class="permit-own-steps__title">
                        <HcisIcon name="signpost-split-fill" size="xs" />
                        <span>Langkah yang Perlu Dilakukan:</span>
                    </div>
                    <ol class="permit-own-steps__list">
                        <li>
                            <strong>Alihkan ke Verifikator Lain:</strong>
                            <span>Minta rekan verifikator SHE lain untuk membuka antrean verifikasi dan memutus pengajuan permit ini.</span>
                        </li>
                        <li>
                            <strong>Inspeksi Fisik Independen:</strong>
                            <span>Rekan verifikator akan melakukan verifikasi fisik kendaraan pemohon di pos atau klinik inspeksi.</span>
                        </li>
                    </ol>
                    <div class="permit-own-steps__note">
                        <HcisIcon name="info-circle" size="2xs" />
                        <span>Anda tetap dapat melihat berkas dokumen, jadwal inspeksi, dan memantau status persetujuan permit ini.</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="permit-own-actions">
                    <HcisButton
                        label="Saya Mengerti"
                        variant="primary"
                        sentiment="warning"
                        leading-icon="check-circle-fill"
                        size="md"
                        class="permit-own-actions__btn"
                        @click="dialogMilikSendiri = false"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { defineAsyncComponent } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDatePicker,
    HcisDialog,
    HcisDualPaneDialog,
    HcisEmptyState,
    HcisField,
    HcisFileViewer,
    HcisIcon,
    HcisInputNumber,
    HcisSelect,
    HcisSelectOption,
    HcisTimeline,
    HcisTreeSelect,
    HcisUpload,
    hcisDialog,
} from '@/components/ui';
import { daftarTahun, pohonModel } from './masterKendaraan';
import { gabungJawabanLokal } from './checklistLokal';
import PermitKoreksiDialog from './PermitKoreksiDialog.vue';
import PermitKoreksiRiwayat from './PermitKoreksiRiwayat.vue';
import HubungiPemohonTombol from '@/components/VISITOR/HubungiPemohonTombol.vue';

let masterPromise = null;
let masterCache = null;

async function getPermitMasterData() {
    if (masterCache) return masterCache;
    if (!masterPromise) {
        masterPromise = axios.get('/transaksipermit-master').then(({ data }) => {
            if (data?.success && data?.result) {
                masterCache = data.result;
            }
            return masterCache;
        }).catch((err) => {
            masterPromise = null;
            throw err;
        });
    }
    return masterPromise;
}

export default {
    name: 'PermitReviewModal',
    components: {
        HubungiPemohonTombol,
        HcisAlert,
        HcisBadge,
        HcisButton,
        HcisCard,
        HcisDatePicker,
        HcisDialog,
        HcisDualPaneDialog,
        HcisEmptyState,
        HcisField,
        HcisFileViewer,
        HcisIcon,
        HcisInputNumber,
        HcisSelect,
        HcisSelectOption,
        HcisTimeline,
        HcisTreeSelect,
        HcisUpload,
        TamuReviewModal: defineAsyncComponent(() => import('./TamuReviewModal.vue')),
        PermitKoreksiDialog,
        PermitKoreksiRiwayat,
    },
    props: {
        show: { type: Boolean, default: false },
        permitId: { type: [Number, String], default: null },
        idPermit: { type: [Number, String], default: null },
        detailEndpoint: { type: String, default: null },
        // Tombol "Hapus akses" hanya berarti bila induknya menangani
        // `minta-revoke`; tanpa itu tombolnya tampil dan tidak berbuat apa-apa.
        revokeTersedia: { type: Boolean, default: false },
        // Tombol "Cetak Kartu" memanggil rute ADMIN (/laporanvisitor-permit/{id}/kartu-pdf)
        // yang dijaga kunci Monitoring/Verifikator Permit; hanya induk yang
        // pemakainya memegang kunci itu yang menyalakannya.
        cetakTersedia: { type: Boolean, default: false },
        readOnly: { type: Boolean, default: false },
    },
    emits: ['close', 'approved', 'rejected', 'minta-revoke'],
    data() {
        return {
            detailLoading: false,
            submitLoading: false,
            uploadingDoc: false,
            showTamuModal: false,
            selectedTamuId: null,
            activeDocToChange: null,
            permit: null,
            dokumens: [],
            checklists: [],
            approvals: [],
            durations: [],
            selectedDurationId: '',
            masterModels: [],
            masterColors: [],
            masterVehicleTypes: [],
            inspeksi: { id_master_model: null, id_master_warna: null, tahun_kendaraan: null },
            // Jendela berlaku permit tamu, ditetapkan verifikator di meja.
            masaBerlaku: { mulai: '', akhir: '' },
            // Kosakata yang benar-benar tersimpan di basis data. Ejaan lain
            // ('FOTO_STNK') pernah dipakai di validasi server dan membuat setiap
            // penggantian dokumen STNK dibalas 422 tanpa penjelasan.
            slotDokumen: [
                { jenis: 'FOTO_KENDARAAN', label: 'Foto Kendaraan' },
                { jenis: 'STNK', label: 'STNK' },
                { jenis: 'SIM', label: 'SIM' },
            ],
            // Berkas yang sudah dipilih verifikator tetapi BELUM dikirim.
            // Dikirim sekaligus, satu kali, saat menyetujui.
            berkasTertunda: { FOTO_KENDARAAN: null, STNK: null, SIM: null },
            docErrors: {},
            jenisBerkasDipilih: null,
            isFinalApproval: false,
            bolehRevoke: false,
            bolehKoreksi: false,
            riwayatKoreksi: [],
            dialogKoreksi: false,
            jadwalInspeksi: null,
            // Disusun server (KontakPemohonInspeksiService): pemohon, atau tamu + PIC.
            kontakPemohon: [],
            kunjunganTamu: null,
            errorMsg: '',

            // In-app Document Lightbox Viewer
            showFileViewer: false,
            memuatKartu: false,
            viewerFiles: [],
            viewerInitialIndex: 0,

            // Dialog Konfirmasi Persetujuan Sebelum Jadwal Tiba
            dialogKonfirmasiAwal: false,
            konfirmasiAwalResolver: null,
            konfirmasiAwalData: null,

            // Dialog Larangan Persetujuan Pengajuan Sendiri (Four-Eyes Principle)
            isOwnPermit: false,
            dialogMilikSendiri: false,
        };
    },
    computed: {
        effectiveId() {
            return this.permitId || this.idPermit || null;
        },
        /**
         * Pengajuan dari aplikasi mitra B2B.
         *
         * Sumber_Pengajuan, bukan kategori permit: kategori COKLAT juga dipakai
         * permit tamu HCIS yang bukan berasal dari API.
         */
        dariMitra() {
            return this.permit?.Sumber_Pengajuan === 'API' && !this.dariKunjungan;
        },
        /**
         * Permit COKLAT yang lahir dari registrasi tamu.
         *
         * Ditandai No_Transaksi_Tamu, bukan kategori permit: kategori COKLAT
         * juga dipakai permit mitra yang diajukan langsung tanpa kunjungan.
         */
        dariKunjungan() {
            return Boolean(this.permit?.No_Transaksi_Tamu);
        },
        /**
         * Permit tamu — permit mitra maupun permit turunan kunjungan tamu.
         *
         * Keduanya tidak punya dokumen kendaraan dan tidak memakai durasi master
         * bulanan; verifikator yang mencatat kendaraannya dan menetapkan jendela
         * berlakunya sendiri. Memaksanya memilih durasi bulanan untuk keduanya
         * berarti tombol Setujui tidak pernah menyala, padahal server memang
         * mengabaikan durasi itu — kebuntuan tanpa penjelasan.
         *
         * Dulu bernama izinSehari, ketika jendelanya masih dipaksa satu hari.
         */
        permitTamu() {
            return this.dariMitra || this.dariKunjungan;
        },
        /**
         * Ada kendaraan yang bisa diinspeksi?
         *
         * Nilainya datang dari server (Wajib_Inspeksi_Kendaraan), bukan
         * disimpulkan ulang di sini: penjaga yang menolak approval memakai
         * ekspresi yang sama persis, dan dua definisi yang berbeda tipis membuat
         * tombol Setujui menyala sementara servernya menolak — tanpa satu pun
         * pesan yang menjelaskan sebabnya.
         *
         * Tamu pejalan kaki tidak membawa apa pun untuk diperiksa.
         */
        butuhInspeksi() {
            return this.permitTamu && this.permit?.Wajib_Inspeksi_Kendaraan === 'Y';
        },
        inspeksiBelumLengkap() {
            if (!this.butuhInspeksi) return false;

            return (
                !this.inspeksi.id_master_model ||
                !this.inspeksi.id_master_warna ||
                !this.inspeksi.tahun_kendaraan
            );
        },
        tahunMaksimum() {
            return new Date().getFullYear() + 1;
        },
        daftarTahunKendaraan() {
            return daftarTahun(this.tahunMaksimum);
        },
        /**
         * Aturan jendela berlaku, DIBACA ULANG di sini hanya untuk menjelaskan.
         *
         * Penolakannya tetap milik server; yang ada di sini cuma supaya
         * verifikator tahu sebabnya sebelum menekan tombol, bukan sesudah.
         * Pesannya sengaja senada dengan pesan server agar keduanya tidak
         * terbaca sebagai dua penolakan yang berbeda.
         */
        masalahMasaBerlaku() {
            if (!this.permitTamu) return '';

            const mulai = this.masaBerlaku.mulai;
            const akhir = this.masaBerlaku.akhir;
            if (!mulai || !akhir) return '';

            if (new Date(akhir.replace(' ', 'T')) <= new Date(mulai.replace(' ', 'T'))) {
                return 'Waktu berakhir harus setelah waktu mulai.';
            }

            const hariIni = new Date();
            hariIni.setHours(0, 0, 0, 0);
            if (new Date(`${mulai.slice(0, 10)}T00:00:00`) < hariIni) {
                return 'Masa berlaku tidak boleh dimulai pada tanggal yang sudah lewat.';
            }

            return '';
        },
        masaBerlakuBelumLengkap() {
            if (!this.permitTamu) return false;

            return !this.masaBerlaku.mulai || !this.masaBerlaku.akhir || Boolean(this.masalahMasaBerlaku);
        },
        ringkasanMasaBerlaku() {
            const { mulai, akhir } = this.masaBerlaku;
            if (!mulai || !akhir || this.masalahMasaBerlaku) return '';

            // Hari yang TERSENTUH, bukan selisih jam — sama seperti hitungan
            // server. Jendela 22:00 sampai 02:00 menyentuh dua tanggal.
            const awal = new Date(`${mulai.slice(0, 10)}T00:00:00`);
            const akhirTersentuh = new Date(new Date(akhir.replace(' ', 'T')).getTime() - 1000);
            akhirTersentuh.setHours(0, 0, 0, 0);
            const hari = Math.max(1, Math.round((akhirTersentuh - awal) / 86400000) + 1);

            return `${mulai.slice(0, 16)} s/d ${akhir.slice(0, 16)} (${hari} hari)`;
        },
        tanggalSudahLewat() {
            return (tanggal) => {
                const hariIni = new Date();
                hariIni.setHours(0, 0, 0, 0);

                return tanggal < hariIni;
            };
        },
        hasVehicleSpecs() {
            const p = this.permit || {};
            const plat = p.No_Plat_Kendaraan && p.No_Plat_Kendaraan !== '-' ? p.No_Plat_Kendaraan.trim() : '';
            const tipe = p.Nama_Vehicle_Type && p.Nama_Vehicle_Type !== '-' ? p.Nama_Vehicle_Type.trim() : '';
            const model = (p.Nama_Model_Manual || p.Nama_Model_Kendaraan) && (p.Nama_Model_Manual !== '-' && p.Nama_Model_Kendaraan !== '-') ? String(p.Nama_Model_Manual || p.Nama_Model_Kendaraan).trim() : '';
            const tahun = p.Tahun_Kendaraan && p.Tahun_Kendaraan !== '-' ? String(p.Tahun_Kendaraan).trim() : '';
            const warna = p.Warna_Manual && p.Warna_Manual !== '-' ? p.Warna_Manual.trim() : '';
            const jadwal = p.Tanggal_Mulai_Berlaku || p.Waktu_Mulai_Berlaku || p.Keterangan_Pengajuan || p.Keterangan;
            return Boolean(plat || tipe || model || tahun || warna || jadwal);
        },
        /**
         * Jejak asal-usul permit tamu, hanya baris yang benar-benar terisi.
         *
         * Baris kosong berlabel '-' membuat admin menduga datanya hilang,
         * padahal memang tidak pernah dikirim: pengajuan lewat /permit-tamu
         * tidak membawa nomor telepon, dan pengajuan mitra tanpa PIC memang
         * tidak punya karyawan penanggung jawab.
         */
        asalPengajuan() {
            const p = this.permit || {};
            const baris = [];

            if (p.Pemohon_Adalah_Aplikasi === 'Y' && p.Nama_Mitra) {
                baris.push({ label: 'Aplikasi Mitra', nilai: p.Nama_Mitra, icon: 'plug', tabular: false });
            } else if (p.Kode_Karyawan_Pemohon && p.Nama_Pemohon && p.Nama_Pemohon !== (p.Nama_Pemohon_Tampil || p.Nama_Tamu)) {
                baris.push({
                    label: 'Diajukan oleh',
                    nilai: `${p.Nama_Pemohon} (${p.Kode_Karyawan_Pemohon})`,
                    icon: 'person-check',
                    tabular: false,
                });
            }

            if (p.No_Identitas_Tamu) {
                baris.push({
                    label: 'Identitas Tamu',
                    nilai: `${p.Jenis_Identitas_Tamu || 'ID'}: ${p.No_Identitas_Tamu}`,
                    icon: 'card-text',
                    tabular: true,
                });
            }

            if (p.No_HP_Tamu) {
                baris.push({
                    label: 'Kontak Tamu / WA',
                    nilai: p.No_HP_Tamu,
                    icon: 'telephone',
                    tabular: true,
                });
            }

            if (p.Referensi_Mitra) {
                baris.push({
                    label: 'Referensi Mitra',
                    nilai: p.Referensi_Mitra,
                    icon: 'upc-scan',
                    tabular: true,
                });
            }

            if (p.No_Transaksi_Tamu) {
                baris.push({
                    label: 'No. Registrasi Tamu',
                    nilai: `#${p.No_Transaksi_Tamu}`,
                    rawNo: p.No_Transaksi_Tamu,
                    icon: 'journal-check',
                    tabular: true,
                    clickable: true,
                });
            }

            return baris;
        },
        /**
         * Model kendaraan dikelompokkan per jenisnya.
         *
         * Simpul jenisnya sengaja `selectable: false` - "Mobil Penumpang"
         * bukan jawaban yang sah untuk merek/model, dan membiarkannya dipilih
         * menyimpan id kelompok sebagai id model.
         *
         * Jenis yang tidak dikenal TIDAK dibuang melainkan jatuh ke kelompok
         * "Lainnya": daftar jenis dari master disaring per hak akses, sehingga
         * model yang jenisnya tidak lolos saringan itu akan hilang diam-diam
         * dari pilihan verifikator.
         */
        tautanAgendaInspeksi() {
            const tanggal = String(this.jadwalInspeksi?.mulai || '').slice(0, 10);
            const q = new URLSearchParams({ cari: this.permit?.No_Transaksi || '' });
            if (tanggal) q.set('tanggal', tanggal);
            return `/agenda-inspeksi?${q.toString()}`;
        },
        labelDataKosong() {
            return this.permitTamu ? 'Diisi verifikator saat inspeksi' : 'Tidak diisi pemohon';
        },
        jadwalInspeksiBadge() {
            const status = this.jadwalInspeksi?.status;
            if (this.jadwalInspeksi?.gugur) return { variant: 'danger', label: 'Gugur' };
            if (status === 'SELESAI') return { variant: 'success', label: 'Selesai Diinspeksi' };
            if (status === 'TIDAK_HADIR') return { variant: 'danger', label: 'Tidak Hadir' };
            if (status === 'DIBATALKAN') return { variant: 'neutral', label: 'Dibatalkan' };
            // Baris lama yang digantikan booking baru: bukan jadwal yang masih berjalan.
            if (status === 'DIPINDAH') return { variant: 'neutral', label: 'Dipindah Pemohon' };
            if (status === 'DIPINDAH_PETUGAS') return { variant: 'neutral', label: 'Dipindah Petugas' };
            if (status === 'KEDALUWARSA') return { variant: 'warning', label: 'Kedaluwarsa' };
            if (this.jadwalInspeksi?.lewat_jadwal) return { variant: 'warning', label: 'Terlewat' };
            return { variant: 'info', label: 'Terjadwal' };
        },
        /**
         * Jadwal inspeksi yang masih TERJADWAL dan jamnya belum tiba. Permit
         * seperti ini tetap boleh disetujui (pemohon datang lebih awal —
         * permintaan user 29 Sep 2026); konfirmasinya saja yang memperingatkan.
         */
        jadwalInspeksiBermasalah() {
            const j = this.jadwalInspeksi;
            if (!j || this.permitTamu) return null;
            if (j.gugur) {
                return 'Permit gugur karena batas ketidakhadiran terlampaui. Pengajuan tidak dapat disetujui.';
            }
            if (j.status === 'TIDAK_HADIR' || j.wajib_pilih_ulang) {
                return 'Pemohon tercatat tidak hadir pada jadwal inspeksi dan wajib memilih jadwal baru.';
            }
            if (j.status === 'KEDALUWARSA') {
                return 'Jadwal inspeksi telah kedaluwarsa. Menunggu pemohon memilih jadwal baru.';
            }
            if (j.status === 'DIBATALKAN') {
                return 'Jadwal inspeksi telah dibatalkan.';
            }
            if (j.status !== 'SELESAI' && j.mulai) {
                const tm = new Date(String(j.mulai).replace(' ', 'T'));
                if (!Number.isNaN(tm.getTime())) {
                    tm.setHours(23, 59, 59, 999);
                    if (tm.getTime() < Date.now()) {
                        return 'Jadwal inspeksi telah terlewat tanggal tanpa hasil pemeriksaan dari SHE. Menunggu pemohon memilih jadwal baru atau pembaruan status.';
                    }
                }
            }
            return null;
        },
        jadwalInspeksiBelumTiba() {
            const j = this.jadwalInspeksi;
            if (!j || j.status !== 'TERJADWAL' || !j.mulai) return null;
            const mulai = new Date(String(j.mulai).replace(' ', 'T'));
            if (Number.isNaN(mulai.getTime()) || mulai <= new Date()) return null;
            return { waktu: this.formatFullDateTime(String(j.mulai).replace(' ', 'T')), pos: j.pos || '' };
        },
        pohonModelKendaraan() {
            return pohonModel(this.masterModels || [], this.masterVehicleTypes || []);
        },
        hasDocErrors() {
            return Object.values(this.docErrors || {}).some(Boolean);
        },
        /**
         * Permit COKLAT berkendaraan: ketiga foto pemeriksaan WAJIB dan tidak
         * boleh dilewati (permintaan user 29 Sep 2026). Aturan yang sama dijaga
         * server (VisitorApprovalDecisionService::validasiLampiranPermitTamu);
         * syarat kendaraannya memakai butuhInspeksi, sumber yang sama dengan
         * penjaga server. Tamu pejalan kaki tidak membawa apa pun untuk difoto.
         */
        lampiranWajib() {
            return this.butuhInspeksi && this.isPending && !this.readOnly;
        },
        /** Slot wajib yang belum punya berkas tersimpan maupun berkas yang siap diunggah. */
        lampiranWajibKurang() {
            if (!this.lampiranWajib) return [];
            return this.slotDokumen.filter((slot) => !this.berkasTertunda[slot.jenis] && !this.dokumenTerlampir(slot.jenis));
        },
        countDocErrors() {
            return Object.values(this.docErrors || {}).filter(Boolean).length;
        },
        adaBerkasTertunda() {
            return this.slotDokumen.some((slot) => this.berkasTertunda[slot.jenis]);
        },
        countBerkasTertunda() {
            return this.slotDokumen.filter((slot) => this.berkasTertunda[slot.jenis]).length;
        },
        jumlahDokumenTerlampir() {
            return this.slotDokumen.filter((slot) => this.berkasTertunda[slot.jenis] || this.dokumenTerlampir(slot.jenis)).length;
        },
        judulTombolSetujui() {
            if (this.hasDocErrors) return 'Perbaiki berkas lampiran yang ditolak terlebih dahulu';
            if (this.hasUnansweredRequired) return 'Lengkapi checklist wajib terlebih dahulu';
            if (this.inspeksiBelumLengkap) return 'Lengkapi hasil inspeksi kendaraan terlebih dahulu';
            if (this.jadwalInspeksiBermasalah) return this.jadwalInspeksiBermasalah;
            if (this.lampiranWajibKurang.length) {
                return `Unggah foto pemeriksaan: ${this.lampiranWajibKurang.map((s) => s.label).join(', ')}`;
            }

            return '';
        },
        jendelaPermit() {
            const p = this.permit || {};

            if (!p.Waktu_Mulai_Berlaku || !p.Waktu_Berakhir) return '-';

            return `${String(p.Waktu_Mulai_Berlaku).slice(0, 16)} s/d ${String(p.Waktu_Berakhir).slice(0, 16)}`;
        },
        isPending() {
            return !this.readOnly && this.permit?.Status_Permit === 'PENDING';
        },
        isValidityRequired() {
            return !this.readOnly && this.permit?.Status_Permit === 'VALIDITY_REQUIRED';
        },
        dicabut() {
            return this.permit?.Status_Efektif === 'DIBATALKAN' || this.permit?.Status_Permit === 'REVOKED';
        },
        bisaCetakKartu() {
            // Server hanya membuat kartu untuk permit APPROVED (aktif, belum dicabut,
            // belum kedaluwarsa) — syarat yang sama supaya tombol tidak pasti gagal.
            return this.cetakTersedia
                && !this.dicabut
                && this.permit?.Status_Permit === 'APPROVED'
                && Boolean(this.permit?.Id_Transaksi_Permit || this.permit?.Id);
        },
        canRevoke() {
            return this.revokeTersedia && this.bolehRevoke && !this.dicabut && this.permit?.Status_Permit === 'APPROVED';
        },
        modalTitle() {
            if (!this.permit) return 'Detail Permit Kendaraan';
            const nama = this.permit.Nama_Pemohon_Tampil || this.permit.Nama_Pemohon || '';

            return this.isPending ? `Review Permit — ${nama}` : `Detail Permit — ${nama}`;
        },
        modalKicker() {
            return this.readOnly ? 'Visitor · Monitoring & Laporan' : 'Visitor · Verification Desk';
        },
        opsiJawabanChecklist() {
            return [
                { value: 'YA', label: 'Ya', icon: 'check-lg', tone: 'ya' },
                { value: 'TIDAK', label: 'Tidak', icon: 'x-lg', tone: 'tidak' },
                { value: 'NA', label: 'N/A', icon: 'dash-lg', tone: 'na' },
            ];
        },
        jumlahChecklistTerjawab() {
            return (this.checklists || []).filter((c) => c.localJawaban).length;
        },
        adaChecklistKosong() {
            return (this.checklists || []).some((c) => !c.localJawaban);
        },
        hasUnansweredRequired() {
            return this.checklists.some((c) => (c.Wajib_Diisi === 'Y' || !c.Wajib_Diisi) && !c.localJawaban);
        },
        unansweredCount() {
            return this.checklists.filter((c) => (c.Wajib_Diisi === 'Y' || !c.Wajib_Diisi) && !c.localJawaban).length;
        },
        approvalTimelineItems() {
            if (!this.approvals || !this.approvals.length) return [];
            return this.approvals.map((step, idx) => {
                let status = 'Menunggu';
                let sentiment = 'warning';
                let icon = 'hourglass-split';
                const isExpired = step.Expired_At && new Date(step.Expired_At) < new Date();

                if (step.Flag_Approval === 'Y') {
                    status = 'Disetujui';
                    sentiment = 'success';
                    icon = 'check-circle-fill';
                } else if (step.Flag_Approval === 'T') {
                    status = 'Ditolak';
                    sentiment = 'danger';
                    icon = 'x-circle-fill';
                } else if (isExpired) {
                    status = 'Kadaluarsa';
                    sentiment = 'danger';
                    icon = 'clock-history';
                }

                const approvalDate = step.Tanggal_Approval || step.Updated_At;
                const formattedTime = (step.Flag_Approval || isExpired) && approvalDate
                    ? this.formatFullDateTime(approvalDate)
                    : null;

                const approverName = step.Nama_Approved_By
                    || step.Nama_Approver
                    || step.Nama_Approver_Karyawan
                    || step.Nama_Role_Functional
                    || step.Approver_Karyawan_Kode
                    || 'Approver Otoritas';

                return {
                    id: step.Id_Approval_Request || idx,
                    title: step.Nama_Approval || `Tahap Approval ${step.Request_Order_Flow || idx + 1}`,
                    name: approverName,
                    status,
                    sentiment,
                    icon,
                    time: formattedTime,
                    description: step.Catatan && step.Catatan.trim() ? step.Catatan.trim() : null,
                    active: !step.Flag_Approval && !isExpired,
                };
            });
        },
    },
    watch: {
        show: {
            handler(val) {
                if (val && this.effectiveId) this.loadDetail();
                if (!val) this.resetState();
            },
            immediate: true,
        },
        effectiveId(val) {
            if (val && this.show) this.loadDetail();
        },
    },
    methods: {
        async loadDetail() {
            if (!this.effectiveId) return;
            this.detailLoading = true;
            this.errorMsg = '';
            try {
                const endpoint = this.detailEndpoint
                    ? `${this.detailEndpoint}/${encodeURIComponent(this.effectiveId)}`
                    : `/verifikator-permit/${encodeURIComponent(this.effectiveId)}`;
                const { data: json } = await axios.get(endpoint);
                if (json.success) {
                    const noSebelumnya = this.permit?.No_Transaksi;
                    this.permit = json.result.permit || json.result;
                    this.dokumens = json.result.dokumens || [];
                    const checklistServer = (json.result.checklists || []).map((c) => ({
                        ...c,
                        localJawaban: c.Jawaban || '',
                        localCatatan: c.Catatan || '',
                        tampilCatatan: Boolean(c.Catatan) || c.Jawaban === 'TIDAK',
                    }));
                    // Muat ulang untuk permit yang sama (setelah koreksi) mempertahankan isian yang belum disimpan.
                    this.checklists = noSebelumnya && noSebelumnya === this.permit.No_Transaksi
                        ? gabungJawabanLokal(checklistServer, this.checklists)
                        : checklistServer;
                    this.approvals = json.result.approvals || [];
                    this.bolehRevoke = json.result.boleh_revoke === true;
                    // Izin koreksi diputus server (giliran approval + status), bukan dihitung di sini.
                    this.bolehKoreksi = json.result.boleh_koreksi === true && !this.readOnly;
                    this.riwayatKoreksi = json.result.riwayat_koreksi || [];
                    this.jadwalInspeksi = json.result.jadwal_inspeksi || null;
                    this.kontakPemohon = Array.isArray(json.result.kontak_pemohon) ? json.result.kontak_pemohon : [];
                    this.kunjunganTamu = json.result.kunjungan_tamu || null;
                    this.isFinalApproval = Boolean(json.result.is_final_approval);
                    this.isOwnPermit = Boolean(json.result.is_own_permit);

                    // Nilai yang sudah pernah dicatat approver sebelumnya ikut
                    // terisi kembali: approval bertahap membuka modal ini
                    // berkali-kali, dan formulir yang selalu kosong akan
                    // menghapus hasil inspeksi approver pertama.
                    this.inspeksi = {
                        id_master_model: this.permit.Id_Master_Model ? String(this.permit.Id_Master_Model) : null,
                        id_master_warna: this.permit.Id_Master_Warna ? String(this.permit.Id_Master_Warna) : null,
                        tahun_kendaraan: this.permit.Tahun_Kendaraan ?? null,
                    };

                    // Permit turunan kunjungan membawa USULAN jendela dari tanggal
                    // kunjungannya; permit mitra tidak membawa apa pun. Keduanya
                    // tetap bisa diubah — yang menentukan adalah verifikator.
                    this.masaBerlaku = {
                        mulai: this.waktuUntukPemilih(this.permit.Waktu_Mulai_Berlaku),
                        akhir: this.waktuUntukPemilih(this.permit.Waktu_Berakhir),
                    };

                    if (!this.readOnly && this.butuhInspeksi) {
                        await this.loadMasterKendaraan();
                    }

                    if (!this.readOnly && !this.permitTamu && (this.isFinalApproval || this.isValidityRequired)) {
                        await this.loadDurations();
                    }
                } else {
                    this.errorMsg = json.message || 'Gagal memuat data permit.';
                }
            } catch (e) {
                this.errorMsg = e.response?.data?.message || e.message || 'Periksa koneksi lalu coba lagi.';
                console.error('loadDetail error', e);
            } finally {
                this.detailLoading = false;
            }
        },

        async loadMasterKendaraan() {
            try {
                const result = await getPermitMasterData();
                if (!result) return;
                this.masterModels = result.models || [];
                this.masterColors = result.colors || [];
                this.masterVehicleTypes = result.vehicle_types || [];
            } catch (e) {
                // silently fail
            }
        },

        async loadDurations() {
            try {
                const result = await getPermitMasterData();
                this.durations = result ? result.durations || [] : [];
            } catch (e) {
                // silently fail
            }
        },

        /**
         * datetime2 dari SQL Server ('2026-09-16 08:00:00.0000000') ke bentuk
         * yang dimengerti pemilih ('2026-09-16 08:00:00').
         */
        waktuUntukPemilih(nilai) {
            if (!nilai) return '';

            return String(nilai).replace('T', ' ').slice(0, 19);
        },

        async approveAlert() {
            if (this.isOwnPermit) {
                this.dialogMilikSendiri = true;
                return;
            }
            if (this.hasDocErrors) {
                await hcisDialog.alert({
                    title: 'Berkas lampiran belum diperbaiki',
                    description: `Harap ganti ${this.countDocErrors} berkas lampiran yang ditolak sebelum menyetujui permit ini.`,
                    sentiment: 'danger',
                    icon: 'exclamation-octagon-fill',
                });
                return;
            }
            if (this.lampiranWajibKurang.length) {
                await hcisDialog.alert({
                    title: 'Foto pemeriksaan wajib diunggah',
                    description: `Unggah ${this.lampiranWajibKurang.map((s) => s.label).join(', ')} tamu sebelum menyetujui permit ini.`,
                    sentiment: 'warning',
                    icon: 'camera',
                });
                return;
            }
            if (this.hasUnansweredRequired) {
                await hcisDialog.alert({
                    title: 'Checklist belum lengkap',
                    description: `Harap lengkapi ${this.unansweredCount} item checklist pemeriksaan yang wajib diisi terlebih dahulu.`,
                    sentiment: 'warning',
                    icon: 'exclamation-triangle',
                });
                return;
            }
            if (this.inspeksiBelumLengkap) {
                await hcisDialog.alert({
                    title: 'Hasil inspeksi belum lengkap',
                    description:
                        'Isi merek/model, tahun pembuatan, dan warna kendaraan tamu sebelum menyetujui permit ini.',
                    sentiment: 'warning',
                    icon: 'clipboard-check',
                });
                return;
            }
            if (this.masaBerlakuBelumLengkap) {
                await hcisDialog.alert({
                    title: 'Masa berlaku belum ditentukan',
                    description:
                        this.masalahMasaBerlaku ||
                        'Tentukan tanggal dan jam mulai sampai tanggal dan jam berakhir permit ini sebelum menyetujuinya.',
                    sentiment: 'warning',
                    icon: 'calendar-range',
                });
                return;
            }
            if (this.isFinalApproval && !this.permitTamu && !this.selectedDurationId) {
                await hcisDialog.alert({
                    title: 'Masa berlaku belum dipilih',
                    description: 'Pilih masa berlaku permit sebelum approval final.',
                    sentiment: 'warning',
                    icon: 'calendar-event',
                });
                return;
            }
            if (this.jadwalInspeksiBermasalah) {
                await hcisDialog.alert({
                    title: 'Pemeriksaan fisik belum selesai',
                    description: this.jadwalInspeksiBermasalah,
                    sentiment: 'warning',
                    icon: 'calendar-x',
                });
                return;
            }
            const belumTiba = this.jadwalInspeksiBelumTiba;
            if (belumTiba) {
                // Konfirmasi khusus jadwal belum tiba: 'Setujui sebelum jadwal inspeksi?'
                const confirmed = await this.tampilkanKonfirmasiAwal(belumTiba);
                if (!confirmed) return;
                this.submitAction('approve');
                return;
            }

            const confirmed = await hcisDialog.confirm({
                title: 'Setujui permit kendaraan?',
                description: [
                    this.adaBerkasTertunda
                        ? 'Lampiran yang Anda pilih ikut disimpan bersama persetujuan ini.'
                        : null,
                    'Pastikan checklist fisik dan data kendaraan pemohon sudah diverifikasi dengan benar.',
                ].filter(Boolean).join(' '),
                kicker: 'Visitor · Verification Desk',
                icon: 'check-circle-fill',
                sentiment: 'success',
                confirmText: 'Setujui Permit',
                confirmVariant: 'success',
            });
            if (!confirmed) return;
            this.submitAction('approve');
        },

        tampilkanKonfirmasiAwal(belumTiba) {
            return new Promise((resolve) => {
                this.konfirmasiAwalData = {
                    waktu: belumTiba.waktu,
                    pos: belumTiba.pos,
                };
                this.konfirmasiAwalResolver = resolve;
                this.dialogKonfirmasiAwal = true;
            });
        },

        tutupKonfirmasiAwal(hasil = false) {
            this.dialogKonfirmasiAwal = false;
            if (this.konfirmasiAwalResolver) {
                this.konfirmasiAwalResolver(hasil);
                this.konfirmasiAwalResolver = null;
            }
        },

        async rejectAlert() {
            const alasan = await hcisDialog.prompt({
                title: 'Tolak permit kendaraan',
                description: 'Masukkan alasan penolakan untuk dicatat pada riwayat sistem.',
                placeholder: 'Tuliskan alasan penolakan...',
                inputType: 'textarea',
                kicker: 'Visitor · Verification Desk',
                icon: 'x-circle-fill',
                sentiment: 'danger',
                confirmText: 'Tolak Permit',
                confirmVariant: 'danger',
            });
            if (!alasan || !alasan.trim()) return;
            this.submitAction('reject', alasan.trim());
        },

        async submitAction(action, alasan = null) {
            this.submitLoading = true;
            try {
                // Lampiran dikirim LEBIH DULU, sekali jalan, dan kegagalannya
                // membatalkan keputusan. Menyetujui permit sementara bukti
                // pemeriksaannya gagal tersimpan berarti izin terbit tanpa
                // lampiran — dan tidak ada yang menyadarinya sampai seseorang
                // mencarinya. Penolakan tidak butuh lampiran.
                if (action === 'approve' && !(await this.unggahBerkasTertunda())) {
                    return;
                }

                const answers = this.checklists.map((c) => ({
                    question_id: c.question_id,
                    jawaban: c.localJawaban || 'NA',
                    catatan: c.localCatatan || null,
                }));

                const url = `/verifikator-permit/${this.permit.No_Transaksi}/${action}`;
                const body =
                    action === 'reject'
                        ? { alasan }
                        : {
                              answers,
                              id_durasi_permit: this.selectedDurationId || null,
                              ...(this.butuhInspeksi
                                  ? {
                                        id_master_model: this.inspeksi.id_master_model,
                                        id_master_warna: this.inspeksi.id_master_warna,
                                        tahun_kendaraan: this.inspeksi.tahun_kendaraan,
                                    }
                                  : {}),
                              ...(this.permitTamu
                                  ? {
                                        waktu_mulai_berlaku: this.masaBerlaku.mulai || null,
                                        waktu_berakhir: this.masaBerlaku.akhir || null,
                                    }
                                  : {}),
                          };
                const { data: json } = await axios.post(url, body);

                if (json.success) {
                    this.$emit(action === 'approve' ? 'approved' : 'rejected');
                } else {
                    await hcisDialog.alert({
                        title: 'Aksi tidak dapat diproses',
                        description: json.message || 'Terjadi kesalahan.',
                        sentiment: 'danger',
                        icon: 'exclamation-triangle',
                    });
                }
            } catch (e) {
                const errCode = e.response?.data?.error_code;
                const msg = e.response?.data?.message || e.message || 'Gagal mengirim permintaan.';

                if (errCode === 'VISITOR_APPROVAL_CONFLICT' || String(msg).toLowerCase().includes('sendiri')) {
                    this.dialogMilikSendiri = true;
                    return;
                }

                await hcisDialog.alert({
                    title: 'Aksi tidak dapat diproses',
                    description: msg,
                    sentiment: 'danger',
                    icon: 'exclamation-triangle',
                });
                console.error(e);
            } finally {
                this.submitLoading = false;
            }
        },

        async activateLegacyPermit() {
            if (!this.selectedDurationId || this.submitLoading) return;
            const confirmed = await hcisDialog.confirm({
                title: 'Aktifkan masa berlaku permit?',
                description: 'Masa berlaku permit akan dimulai dari hari ini.',
                kicker: 'Visitor · Verification Desk',
                icon: 'calendar-check',
                sentiment: 'success',
                confirmText: 'Aktifkan',
            });
            if (!confirmed) return;

            this.submitLoading = true;
            try {
                const { data: json } = await axios.post(`/verifikator-permit/${this.permit.No_Transaksi}/validity`, {
                    id_durasi_permit: this.selectedDurationId,
                });
                if (!json.success) throw new Error(json.message || 'Gagal menetapkan masa berlaku.');
                this.$emit('approved');
            } catch (e) {
                await hcisDialog.alert({
                    title: 'Masa berlaku gagal ditetapkan',
                    description: e.response?.data?.message || e.message || 'Gagal menetapkan masa berlaku.',
                    sentiment: 'danger',
                    icon: 'exclamation-triangle',
                });
            } finally {
                this.submitLoading = false;
            }
        },

        resetState() {
            this.permit = null;
            this.dokumens = [];
            this.checklists = [];
            this.masterModels = [];
            this.masterColors = [];
            this.masterVehicleTypes = [];
            this.inspeksi = { id_master_model: null, id_master_warna: null, tahun_kendaraan: null };
            this.masaBerlaku = { mulai: '', akhir: '' };
            this.berkasTertunda = { FOTO_KENDARAAN: null, STNK: null, SIM: null };
            this.docErrors = {};
            this.jenisBerkasDipilih = null;
            this.approvals = [];
            this.durations = [];
            this.selectedDurationId = '';
            this.isFinalApproval = false;
            this.bolehRevoke = false;
            this.bolehKoreksi = false;
            this.riwayatKoreksi = [];
            this.dialogKoreksi = false;
            this.jadwalInspeksi = null;
            this.kontakPemohon = [];
            this.kunjunganTamu = null;
            this.submitLoading = false;
            this.errorMsg = '';
            this.showFileViewer = false;
            this.viewerFiles = [];
            this.viewerInitialIndex = 0;
            this.showTamuModal = false;
            this.selectedTamuId = null;
            this.dialogKonfirmasiAwal = false;
            this.konfirmasiAwalResolver = null;
            this.konfirmasiAwalData = null;
            this.isOwnPermit = false;
            this.dialogMilikSendiri = false;
        },

        /**
         * Buka detail tamu DI ATAS modal permit ini.
         *
         * Dulu metode ini melakukan dua hal sekaligus: membuka TamuReviewModal
         * miliknya sendiri, DAN memancarkan 'open-tamu' yang membuat induknya
         * (VerifikatorPermitPanel) membuka TamuReviewModal kedua. Hasilnya dua
         * modal identik bertumpuk, dan menutup yang atas hanya memperlihatkan
         * kembarannya — terbaca seperti modal yang menolak ditutup.
         *
         * Komponen ini sudah memiliki modalnya sendiri (lihat template), dan itu
         * yang benar: ia dipakai di enam tempat, sebagian di antaranya tidak punya
         * induk yang menangani 'open-tamu' sama sekali. Menyerahkan tugasnya ke
         * induk akan membuat tautan ini mati di tempat-tempat tersebut.
         */
        openTamuDetail(noTransaksiTamu) {
            if (!noTransaksiTamu) return;
            this.selectedTamuId = noTransaksiTamu;
            this.showTamuModal = true;
        },

        closeTamuModal() {
            this.showTamuModal = false;
            this.selectedTamuId = null;
        },

        openFileViewer(targetDoc) {
            if (!this.dokumens || !this.dokumens.length) return;

            const targetIdx = this.dokumens.findIndex(
                (d) => d === targetDoc || (targetDoc && d.Id_Permit_Dokumen === targetDoc.Id_Permit_Dokumen)
            );

            this.viewerFiles = this.dokumens.map((d) => {
                const url = this.formatDocUrl(d);
                const realFileName = this.getDocFileName(d);
                const ext = this.getDocExtension(d).toLowerCase();
                const displayName = realFileName || `${this.getDocTitle(d)}.${ext}`;

                return {
                    url,
                    name: displayName.includes('.') ? displayName : `${displayName}.${ext}`,
                    title: this.getDocTitle(d),
                    ext: ext,
                    allowDownload: true,
                };
            });

            this.viewerInitialIndex = targetIdx >= 0 ? targetIdx : 0;
            this.showFileViewer = true;
        },

        async cetakKartu() {
            const id = this.permit?.Id_Transaksi_Permit || this.permit?.Id;
            if (!id || this.memuatKartu) return;

            this.memuatKartu = true;
            try {
                const { data } = await axios.get(`/laporanvisitor-permit/${id}/kartu-pdf`, { responseType: 'blob' });
                const plat = String(this.permit?.No_Plat_Kendaraan || 'permit').replace(/\s+/g, '');

                this.viewerFiles = [{
                    url: URL.createObjectURL(data),
                    name: `kartu-permit-${plat}.pdf`,
                    title: `Kartu Permit ${this.permit?.No_Plat_Kendaraan || ''}`.trim(),
                    ext: 'pdf',
                    allowDownload: true,
                }];
                this.viewerInitialIndex = 0;
                this.showFileViewer = true;
            } catch (e) {
                // Galat dibalas sebagai Blob karena responseType-nya blob.
                let pesan = '';
                const isi = e?.response?.data;
                if (isi instanceof Blob) {
                    try {
                        pesan = JSON.parse(await isi.text())?.message || '';
                    } catch {
                        pesan = '';
                    }
                } else {
                    pesan = isi?.message || '';
                }
                ElMessage.error(pesan || 'Kartu permit gagal dibuat.');
            } finally {
                this.memuatKartu = false;
            }
        },

        formatDocUrl(doc) {
            if (!doc) return '';
            if (typeof doc === 'string') {
                if (doc.startsWith('http://') || doc.startsWith('https://')) return doc;
                const clean = doc.replace(/^\/?(public\/|storage\/)?/, '');
                return `/storage/${clean}`;
            }
            if (doc.File_Url) return doc.File_Url;
            const path = doc.File_Path || doc.Path_File_URL || '';
            if (path.startsWith('http://') || path.startsWith('https://')) return path;
            const clean = path.replace(/^\/?(public\/|storage\/)?/, '');
            return `/storage/${clean}`;
        },

        previewDoc(doc) {
            this.openFileViewer(doc);
        },

        // Enam penolong dokumen di bawah sempat terdefinisi DUA KALI di blok
        // methods yang sama. Kunci terakhir menang, jadi salinan pertama adalah
        // kode mati yang menyesatkan siapa pun yang menyuntingnya. Keduanya
        // identik byte-per-byte saat digabungkan, sehingga perilakunya tidak
        // berubah. Dijaga kunciKembar.ratchet.
        getDocTitle(doc) {
            if (!doc) return 'Dokumen';
            const jenis = (doc.Jenis_Dokumen || '').toUpperCase();
            if (jenis === 'FOTO_KENDARAAN') return 'Foto Kendaraan';
            if (jenis === 'STNK') return 'STNK Kendaraan';
            if (jenis === 'SIM') return 'SIM Pemohon';
            return doc.Jenis_Dokumen || doc.File_Name || 'Berkas Dokumen';
        },

        getDocSubtitle(doc) {
            if (!doc) return '';
            const jenis = (doc.Jenis_Dokumen || '').toUpperCase();
            if (jenis === 'FOTO_KENDARAAN') return 'Tampak fisik kendaraan & plat';
            if (jenis === 'STNK') return 'Surat Tanda Nomor Kendaraan';
            if (jenis === 'SIM') return 'Surat Izin Mengemudi pemohon';
            return 'Dokumen kelengkapan permit';
        },

        getDocFileName(doc) {
            if (!doc) return '';
            const path = doc.Path_File_URL || doc.File_Path || doc.File_Url || '';
            if (path) {
                const base = path.split('?')[0].split('#')[0].split('/').pop();
                if (base && base !== doc.Jenis_Dokumen) {
                    return base;
                }
            }
            if (doc.File_Name && doc.File_Name !== doc.Jenis_Dokumen) {
                return doc.File_Name;
            }
            return '';
        },

        getDocExtension(doc) {
            if (!doc) return 'DOC';
            const path = doc.Path_File_URL || doc.File_Path || doc.File_Url || doc.File_Name || '';
            if (path) {
                const clean = path.split('?')[0].split('#')[0];
                const parts = clean.split('.');
                if (parts.length > 1) {
                    const ext = parts.pop().toUpperCase();
                    if (ext.length <= 5 && !ext.includes('/')) {
                        return ext;
                    }
                }
            }
            const jenis = (doc.Jenis_Dokumen || '').toUpperCase();
            if (jenis === 'FOTO_KENDARAAN') return 'JPG';
            if (jenis === 'STNK') return 'PDF';
            if (jenis === 'SIM') return 'JPG';
            return 'DOC';
        },

        getDocIcon(jenis = '') {
            const j = (jenis || '').toUpperCase();
            if (j.includes('KENDARAAN')) return 'car-front-fill';
            if (j.includes('STNK')) return 'file-earmark-richtext-fill';
            if (j.includes('SIM')) return 'person-vcard-fill';
            return 'file-earmark-text-fill';
        },

        getDocIconBg(jenis = '') {
            const j = (jenis || '').toUpperCase();
            if (j.includes('KENDARAAN')) return 'permit-doc-icon--brand';
            if (j.includes('STNK')) return 'permit-doc-icon--teal';
            if (j.includes('SIM')) return 'permit-doc-icon--purple';
            return 'permit-doc-icon--slate';
        },

        /** Nilai tampil atau '' bila kosong / '-' (penanda kosong dari kueri). */
        isiData(nilai) {
            const teks = String(nilai ?? '').trim();
            return teks === '-' ? '' : teks;
        },

        pilihJawabanChecklist(item, nilai) {
            item.localJawaban = nilai;
            if (nilai === 'TIDAK') item.tampilCatatan = true;
        },

        labelJawabanChecklist(nilai) {
            return { YA: 'Ya', TIDAK: 'Tidak', NA: 'N/A' }[nilai] || 'Belum diisi';
        },

        tandaiSemuaYa() {
            (this.checklists || []).forEach((c) => {
                if (!c.localJawaban) c.localJawaban = 'YA';
            });
        },

        dokumenSlot(jenis) {
            return (this.dokumens || []).find((d) => d.Jenis_Dokumen === jenis) || null;
        },

        dokumenTerlampir(jenis) {
            return (this.dokumens || []).some((d) => d.Jenis_Dokumen === jenis);
        },

        labelSlot(slot) {
            if (this.berkasTertunda[slot.jenis]) return 'Ganti pilihan ' + slot.label;

            return (this.dokumenTerlampir(slot.jenis) ? 'Ganti ' : 'Unggah ') + slot.label;
        },

        iconSlot(jenis) {
            if (this.berkasTertunda[jenis]) return 'check-circle-fill';

            return this.dokumenTerlampir(jenis) ? 'arrow-repeat' : 'cloud-upload';
        },

        kanonikJenis(jenis) {
            const str = String(jenis || '').toUpperCase().trim();
            if (str === 'FOTO_STNK') return 'STNK';
            if (str === 'FOTO_SIM') return 'SIM';
            return str;
        },

        formatDocErrorMessage(slotKey, rawMsg) {
            if (/must be a file of type/i.test(rawMsg)) {
                return 'Format berkas tidak didukung. Harap gunakan berkas berformat JPG, JPEG, PNG, WEBP, atau PDF.';
            }
            if (/greater than|max/i.test(rawMsg)) {
                return 'Ukuran berkas melebihi batas maksimal (maks. 5 MB).';
            }
            return rawMsg || 'Berkas tidak memenuhi syarat validasi.';
        },

        parseDocErrors(errorData) {
            const errors = errorData?.errors || {};
            const newErrors = {};

            // 1. Scan direct error keys from Laravel Validation (e.g. "dokumen.STNK", "dokumen.FOTO_KENDARAAN", "dokumen.SIM")
            Object.keys(errors).forEach((key) => {
                const rawMsg = Array.isArray(errors[key]) ? errors[key][0] : String(errors[key] || '');
                const match = key.match(/^dokumen\.(.+)$/i);
                if (match) {
                    const slotKey = this.kanonikJenis(match[1]);
                    newErrors[slotKey] = this.formatDocErrorMessage(slotKey, rawMsg);
                } else if (key === 'file' && this.activeDocToChange) {
                    const slotKey = this.kanonikJenis(this.activeDocToChange.Jenis_Dokumen);
                    newErrors[slotKey] = this.formatDocErrorMessage(slotKey, rawMsg);
                } else if (key === 'file' && this.jenisBerkasDipilih) {
                    const slotKey = this.kanonikJenis(this.jenisBerkasDipilih);
                    newErrors[slotKey] = this.formatDocErrorMessage(slotKey, rawMsg);
                }
            });

            // 2. Scan main error message if specific keys were not parsed
            const mainMessage = errorData?.message || (typeof errorData === 'string' ? errorData : '');
            if (mainMessage) {
                ['FOTO_KENDARAAN', 'STNK', 'SIM', 'FOTO_STNK', 'FOTO_SIM'].forEach((jenis) => {
                    const regex = new RegExp(`(dokumen\\.${jenis}|\\b${jenis}\\b)`, 'i');
                    if (regex.test(mainMessage)) {
                        const slotKey = this.kanonikJenis(jenis);
                        if (!newErrors[slotKey]) {
                            newErrors[slotKey] = this.formatDocErrorMessage(slotKey, mainMessage);
                        }
                    }
                });
            }

            this.docErrors = { ...this.docErrors, ...newErrors };
        },

        pilihBerkas(jenis) {
            this.jenisBerkasDipilih = this.kanonikJenis(jenis);
            this.activeDocToChange = null;

            if (this.docErrors[this.jenisBerkasDipilih]) {
                const updated = { ...this.docErrors };
                delete updated[this.jenisBerkasDipilih];
                this.docErrors = updated;
            }

            if (this.$refs.changeFileInput) {
                this.$refs.changeFileInput.value = '';
                this.$refs.changeFileInput.click();
            }
        },

        batalkanBerkas(jenis) {
            const kanonik = this.kanonikJenis(jenis);
            this.berkasTertunda = { ...this.berkasTertunda, [kanonik]: null };
            if (this.docErrors[kanonik]) {
                const updated = { ...this.docErrors };
                delete updated[kanonik];
                this.docErrors = updated;
            }
        },

        /**
         * Kirim SEMUA berkas tertunda dalam satu permintaan.
         *
         * Dipanggil tepat sebelum keputusan approval dikirim. Kegagalannya
         * menghentikan approval: menyetujui permit sementara bukti
         * pemeriksaannya gagal tersimpan berarti izin terbit tanpa lampiran,
         * dan tidak ada yang akan menyadarinya sampai seseorang mencarinya.
         */
        async unggahBerkasTertunda() {
            if (!this.adaBerkasTertunda || !this.permit?.No_Transaksi) return true;

            const formData = new FormData();
            formData.append('no_transaksi', this.permit.No_Transaksi);

            this.slotDokumen.forEach((slot) => {
                const berkas = this.berkasTertunda[slot.jenis];
                if (berkas) formData.append(`dokumen[${slot.jenis}]`, berkas);
            });

            this.uploadingDoc = true;
            try {
                const { data: json } = await axios.post('/verifikator-permit-upload-dokumen', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                if (!json.success) {
                    this.parseDocErrors(json);
                    await hcisDialog.alert({
                        title: 'Dokumen gagal disimpan',
                        description: json.message || 'Lampiran tidak tersimpan; permit belum disetujui.',
                        sentiment: 'danger',
                        icon: 'exclamation-triangle',
                    });

                    return false;
                }

                this.docErrors = {};
                this.berkasTertunda = { FOTO_KENDARAAN: null, STNK: null, SIM: null };

                return true;
            } catch (e) {
                this.parseDocErrors(e.response?.data || e);
                await hcisDialog.alert({
                    title: 'Dokumen gagal disimpan',
                    description:
                        e.response?.data?.message || 'Lampiran tidak tersimpan; permit belum disetujui.',
                    sentiment: 'danger',
                    icon: 'exclamation-triangle',
                });

                return false;
            } finally {
                this.uploadingDoc = false;
            }
        },

        triggerChangeFile(doc) {
            this.activeDocToChange = doc;
            const jenis = this.kanonikJenis(doc?.Jenis_Dokumen);
            if (jenis && this.docErrors[jenis]) {
                const updated = { ...this.docErrors };
                delete updated[jenis];
                this.docErrors = updated;
            }
            if (this.$refs.changeFileInput) {
                this.$refs.changeFileInput.value = '';
                this.$refs.changeFileInput.click();
            }
        },

        /**
         * Berkas dipilih - DITAHAN, tidak langsung diunggah.
         *
         * Satu pengecualian: tombol "Ganti" pada dokumen yang sudah terlampir
         * di luar keadaan menunggu review tetap mengirim seketika, karena di
         * sana tidak ada tombol Setujui yang bisa menjadi pemicunya.
         */
        async handleFileChangeSelected(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            const jenis = this.kanonikJenis(this.jenisBerkasDipilih || this.activeDocToChange?.Jenis_Dokumen);
            this.jenisBerkasDipilih = null;

            if (!jenis || !this.permit?.No_Transaksi) return;

            if (this.docErrors[jenis]) {
                const updated = { ...this.docErrors };
                delete updated[jenis];
                this.docErrors = updated;
            }

            if (this.isPending && !this.readOnly) {
                this.berkasTertunda = { ...this.berkasTertunda, [jenis]: file };
                this.activeDocToChange = null;

                return;
            }

            this.uploadingDoc = true;
            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('no_transaksi', this.permit.No_Transaksi);
                formData.append('jenis_dokumen', jenis);

                const { data: json } = await axios.post('/verifikator-permit-upload-dokumen', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                if (json.success) {
                    const updated = { ...this.docErrors };
                    delete updated[jenis];
                    this.docErrors = updated;
                } else {
                    this.parseDocErrors(json);
                }

                await hcisDialog.alert({
                    title: json.success ? 'Dokumen berhasil disimpan' : 'Dokumen gagal disimpan',
                    description: json.success
                        ? 'Lampiran sudah tersimpan pada permit ini.'
                        : json.message || 'Gagal menyimpan dokumen.',
                    sentiment: json.success ? 'success' : 'danger',
                    icon: json.success ? 'check-circle-fill' : 'exclamation-triangle',
                });

                if (json.success) await this.loadDetail();
            } catch (e) {
                this.parseDocErrors(e.response?.data || e);
                await hcisDialog.alert({
                    title: 'Dokumen gagal disimpan',
                    description: e.response?.data?.message || 'Periksa koneksi lalu coba lagi.',
                    sentiment: 'danger',
                    icon: 'exclamation-triangle',
                });
            } finally {
                this.uploadingDoc = false;
                this.activeDocToChange = null;
            }
        },

        handleUploadChange(jenis, fileList) {
            const kanonik = this.kanonikJenis(jenis);
            const item = fileList?.[0];
            const file = item?.raw || item;
            if (file) {
                this.berkasTertunda = { ...this.berkasTertunda, [kanonik]: file };
                if (this.docErrors[kanonik]) {
                    const updated = { ...this.docErrors };
                    delete updated[kanonik];
                    this.docErrors = updated;
                }
            }
        },

        handleUploadRemove(jenis) {
            this.batalkanBerkas(jenis);
        },

        getUploadFileList(jenis) {
            const kanonik = this.kanonikJenis(jenis);
            if (this.berkasTertunda[kanonik]) {
                const file = this.berkasTertunda[kanonik];
                const isError = Boolean(this.docErrors[kanonik]);
                return [
                    {
                        id: `staged-${kanonik}`,
                        name: file.name,
                        size: file.size || 0,
                        status: isError ? 'error' : 'success',
                        percentage: 100,
                        raw: file,
                        url: typeof window !== 'undefined' && file instanceof Blob ? URL.createObjectURL(file) : '',
                    },
                ];
            }

            const existing = (this.dokumens || []).find((d) => this.kanonikJenis(d.Jenis_Dokumen) === kanonik);
            if (existing) {
                const url = this.formatDocUrl(existing);
                const fileName = this.getDocFileName(existing);
                const ext = this.getDocExtension(existing);
                return [
                    {
                        id: existing.Id_Permit_Dokumen || `doc-${kanonik}`,
                        name: fileName || `${this.getDocTitle(existing)}.${ext.toLowerCase()}`,
                        size: existing.Ukuran_File || 0,
                        status: 'success',
                        percentage: 100,
                        url: url,
                        raw: existing,
                    },
                ];
            }

            return [];
        },

        getSlotHint(jenis) {
            return 'JPG, PNG, WEBP, PDF · Maks 5 MB';
        },

        getSlotAccept(jenis) {
            return '.jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf';
        },

        getSlotIcon(jenis) {
            if (jenis === 'FOTO_KENDARAAN') return 'car-front-fill';
            if (jenis === 'STNK') return 'file-earmark-richtext-fill';
            if (jenis === 'SIM') return 'person-vcard-fill';
            return 'cloud-arrow-up-fill';
        },

        getSlotSentiment(jenis) {
            if (jenis === 'FOTO_KENDARAAN') return 'brand';
            if (jenis === 'STNK') return 'teal';
            if (jenis === 'SIM') return 'purple';
            return 'brand';
        },

        getSlotSubtext(jenis) {
            // Siapa pengunggahnya lebih dulu: tanpa itu verifikator tak bisa
            // membedakan berkas pemohon dari slot kosong yang menunggu diisi.
            const asal = this.dokumenTerlampir(jenis)
                ? (this.permitTamu ? 'Diunggah verifikator saat pemeriksaan' : 'Diunggah pemohon saat pengajuan')
                : (this.permitTamu ? 'Belum diunggah — diisi verifikator saat pemeriksaan' : 'Tidak diunggah pemohon');
            if (jenis === 'FOTO_KENDARAAN') return `${asal} · foto fisik kendaraan`;
            if (jenis === 'STNK') return `${asal} · Surat Tanda Nomor Kendaraan`;
            if (jenis === 'SIM') return `${asal} · Surat Izin Mengemudi`;
            return asal;
        },

        handleImgError(event) {
            event.target.style.display = 'none';
        },

        getHeroCardStyle(permit) {
            if (!permit) return {};
            const hex = permit.Kode_Warna || '#6366f1';
            return {
                '--permit-hero-glow': this.hexToRgba(hex, 0.15),
                '--permit-hero-accent': hex,
            };
        },

        getHeroIconBoxStyle(permit) {
            if (!permit) return {};
            const hex = permit.Kode_Warna || '#6366f1';
            return {
                backgroundColor: this.hexToRgba(hex, 0.1),
                color: hex,
                borderColor: this.hexToRgba(hex, 0.28),
            };
        },

        getRequesterAvatarStyle(permit) {
            if (!permit) return {};
            const hex = permit.Kode_Warna || '#6366f1';
            return {
                background: `linear-gradient(135deg, ${hex} 0%, #0f172a 160%)`,
            };
        },

        getCategoryBadgeStyle(permit) {
            if (!permit) return {};
            const hex = permit.Kode_Warna || '#6366f1';
            return {
                backgroundColor: this.hexToRgba(hex, 0.12),
                color: hex,
                borderColor: this.hexToRgba(hex, 0.3),
            };
        },

        getVehicleIcon(type = '', isTamu = false) {
            const t = (type || '').toLowerCase();
            if (t.includes('motor') || t.includes('sepeda')) return 'bicycle';
            if (t.includes('truk') || t.includes('truck')) return 'truck';
            if (t.includes('bus')) return 'bus-front';
            if (t.includes('mobil') || t.includes('car')) return 'car-front-fill';
            if (!type && isTamu) return 'person-vcard';
            return 'car-front-fill';
        },

        getVehicleColorHex(colorName = '') {
            const name = (colorName || '').toLowerCase();
            const colors = {
                hitam: '#0f172a',
                putih: '#f8fafc',
                silver: '#94a3b8',
                abu: '#64748b',
                merah: '#ef4444',
                biru: '#3b82f6',
                hijau: '#10b981',
                kuning: '#f59e0b',
                cokelat: '#78350f',
                emas: '#d97706',
            };
            for (const key in colors) {
                if (name.includes(key)) return colors[key];
            }
            return '#cbd5e1';
        },

        getInitials(name) {
            if (!name) return 'K';
            const parts = name.trim().split(/\s+/);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return name.slice(0, 2).toUpperCase();
        },

        getRejectionComment() {
            if (!this.approvals || !this.approvals.length) return '';
            const rejected = this.approvals.find((a) => a.Flag_Approval === 'T' && a.Catatan);
            return rejected ? rejected.Catatan : '';
        },

        hexToRgba(hex, alpha) {
            if (!hex) return `rgba(100,116,139,${alpha})`;
            let c = hex.replace('#', '');
            if (c.length === 3) {
                c = c.split('').map((char) => char + char).join('');
            }
            const r = parseInt(c.slice(0, 2), 16) || 100;
            const g = parseInt(c.slice(2, 4), 16) || 116;
            const b = parseInt(c.slice(4, 6), 16) || 139;
            return `rgba(${r},${g},${b},${alpha})`;
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            try {
                return new Date(dateStr).toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                });
            } catch (e) {
                return dateStr;
            }
        },

        /** "2026-09-29 13:15" dari server; spasi diganti 'T' agar Safari bisa membacanya. */
        formatWaktuKunjungan(nilai) {
            return nilai ? this.formatFullDateTime(String(nilai).replace(' ', 'T')) : '-';
        },

        formatFullDateTime(dateStr) {
            if (!dateStr) return '-';
            try {
                const d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                const datePart = d.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                });
                const timePart = d.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).replace('.', ':');
                return `${datePart}, ${timePart} WIB`;
            } catch (e) {
                return dateStr;
            }
        },

        formatDateTime(dateStr) {
            return this.formatFullDateTime(dateStr);
        },

        formatTanggalInspeksi(inspeksi) {
            if (!inspeksi?.mulai) return '-';
            try {
                const [tanggal] = String(inspeksi.mulai).split(' ');
                const [y, m, d] = tanggal.split('-').map(Number);
                return new Date(y, m - 1, d).toLocaleDateString('id-ID', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                });
            } catch (e) {
                return String(inspeksi.mulai);
            }
        },

        formatJamInspeksi(inspeksi) {
            if (!inspeksi?.mulai) return '-';
            const jamMulai = String(inspeksi.mulai).slice(11, 16);
            const jamSelesai = inspeksi.selesai ? String(inspeksi.selesai).slice(11, 16) : '';
            return jamSelesai ? `${jamMulai} – ${jamSelesai}` : jamMulai;
        },

        getStatusLabel(s) {
            return (
                {
                    APPROVED: 'Disetujui',
                    REJECTED: 'Ditolak',
                    EXPIRED: 'Expired',
                    VALIDITY_REQUIRED: 'Perlu Set Validitas',
                    REVOKED: 'Dicabut',
                    PENDING: 'Menunggu Approval',
                    NO_FLOW: 'No Flow',
                }[s] || s || 'Menunggu'
            );
        },
    },
};
</script>

<style scoped>
.permit-mitra-note {
    margin: 0 0 0.75rem;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: var(--hcis-text-muted, #64748b);
}

.permit-mitra-list {
    display: grid;
    grid-template-columns: minmax(0, 8rem) minmax(0, 1fr);
    gap: 0.35rem 0.75rem;
    margin: 0;
    font-size: 0.8125rem;
}

.permit-mitra-list dt {
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
}

.permit-mitra-list dd {
    margin: 0;
    color: #0f172a;
    overflow-wrap: anywhere;
}

/* =========================================================================
   HERO IDENTITY CARD (LEFT PANE)
   ========================================================================= */
.permit-hero-card {
    background: var(--hcis-white, #ffffff);
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-xl, 14px);
    padding: var(--hcis-space-4, 1rem);
    margin-bottom: var(--hcis-space-4, 1rem);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    position: relative;
    overflow: hidden;
}

.permit-hero-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--permit-hero-accent, var(--hcis-brand-500, var(--hcis-indigo-500)));
}

.permit-hero-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--hcis-space-2, 0.5rem);
    margin-bottom: 0.75rem;
}

.permit-hero-brand-group {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    min-width: 0;
}

.permit-hero-icon-box {
    width: 2.375rem;
    height: 2.375rem;
    border-radius: 10px;
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
    flex-shrink: 0;
}

.permit-hero-brand-meta {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.permit-hero-category-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 750;
    border: 1px solid;
    line-height: 1.2;
    max-width: 100%;
}

.permit-hero-category-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.permit-hero-vehicle-type {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-slate-600, var(--hcis-slate-600));
}

/* Cohesive Identity & Specs Box */
.permit-hero-id-box {
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 0.75rem;
}

.permit-hero-trx-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: var(--hcis-brand-50, #eef2ff);
    border-bottom: 1px solid var(--hcis-brand-200, #c7d2fe);
}

.permit-hero-trx-label-wrap {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.permit-hero-trx-icon {
    color: var(--hcis-brand-600, #4f46e5);
}

.permit-hero-trx-label {
    font-size: 0.625rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-brand-700, #4338ca);
}

.permit-hero-trx-value {
    font-size: 0.875rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    color: var(--hcis-brand-900, #312e81);
}

.permit-hero-specs-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr 1fr;
    divide-x: 1px solid var(--hcis-slate-200);
}

.permit-hero-spec-cell {
    padding: 0.45rem 0.625rem;
    border-right: 1px solid var(--hcis-slate-200, #e2e8f0);
    min-width: 0;
}

.permit-hero-spec-cell:last-child {
    border-right: none;
}

.permit-hero-spec-kicker {
    display: block;
    font-size: 0.6rem;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400, #94a3b8);
    margin-bottom: 1px;
}

.permit-hero-spec-val {
    font-size: 0.78125rem;
    line-height: 1.25;
}

.permit-color-circle {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
    display: inline-block;
    flex-shrink: 0;
}

.permit-hero-plate-badge {
    background: var(--hcis-slate-900, #0f172a);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 750;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    letter-spacing: 0.04em;
    display: inline-block;
    max-width: 100%;
}

.permit-hero-uninspected-box {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.625rem 0.75rem;
    background: #fffbeb;
    border-top: 1px solid #fef3c7;
    color: #92400e;
}

.permit-hero-uninspected-box--info {
    background: #f0fdf4;
    border-top: 1px solid #dcfce7;
    color: #166534;
}

.permit-hero-uninspected-icon {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 6px;
    background: rgba(217, 119, 6, 0.12);
    color: #d97706;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.permit-hero-uninspected-box--info .permit-hero-uninspected-icon {
    background: rgba(22, 101, 52, 0.12);
    color: #16a34a;
}

.permit-hero-uninspected-info {
    min-width: 0;
}

.permit-hero-uninspected-title {
    font-size: 0.75rem;
    font-weight: 750;
    line-height: 1.25;
}

.permit-hero-uninspected-desc {
    font-size: 0.6875rem;
    line-height: 1.3;
    opacity: 0.85;
    margin-top: 1px;
}

.permit-hero-meta-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.625rem;
    border-top: 1px dashed var(--hcis-slate-200, #e2e8f0);
    flex-wrap: wrap;
}

.permit-hero-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.55rem;
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 6px;
    font-size: 0.6875rem;
    line-height: 1.25;
    min-width: 0;
}

.permit-hero-meta-icon {
    color: var(--hcis-slate-400, #94a3b8);
    flex-shrink: 0;
}

.permit-hero-meta-label {
    color: var(--hcis-slate-500, #64748b);
    font-weight: 500;
}

.permit-hero-meta-value {
    color: var(--hcis-slate-800, #1e293b);
    font-weight: 700;
    white-space: nowrap;
}

.permit-hero-meta-item--ref {
    background: var(--hcis-brand-50, #eef2ff);
    border-color: var(--hcis-brand-200, #c7d2fe);
}

.permit-hero-meta-item--ref .permit-hero-meta-icon {
    color: var(--hcis-brand-600, #4f46e5);
}

.permit-hero-meta-item--ref .permit-hero-meta-label {
    color: var(--hcis-brand-700, #4338ca);
    font-weight: 600;
}

.permit-hero-meta-item--ref .permit-hero-meta-value {
    color: var(--hcis-brand-900, #312e81);
    font-weight: 800;
}

.permit-hero-meta-item--clickable {
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    user-select: none;
}

.permit-hero-meta-item--clickable:hover {
    background: var(--hcis-brand-100, #e0e7ff);
    border-color: var(--hcis-brand-300, #a5b4fc);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(79, 70, 229, 0.12);
}

.permit-hero-meta-item--clickable:active {
    transform: translateY(0);
}

.permit-hero-meta-link {
    display: inline-flex;
    align-items: center;
    color: var(--hcis-brand-700, #4338ca);
}

.permit-hero-meta-link-icon {
    opacity: 0.7;
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.permit-hero-meta-item--clickable:hover .permit-hero-meta-link-icon {
    opacity: 1;
    transform: translate(1px, -1px);
}

.permit-hero-meta-item--mitra {
    background: #f0fdfa;
    border-color: #99f6e4;
}

.permit-hero-meta-item--mitra .permit-hero-meta-icon {
    color: #0d9488;
}

.permit-hero-meta-item--mitra .permit-hero-meta-label {
    color: #0f766e;
    font-weight: 600;
}

.permit-hero-meta-item--mitra .permit-hero-meta-value {
    color: #115e59;
    font-weight: 800;
}

/* =========================================================================
   REQUESTER CARD
   ========================================================================= */
.permit-requester-card {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3, 0.75rem);
}

.permit-requester-header {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3, 0.75rem);
}

.permit-requester-avatar {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: var(--hcis-radius-full, 9999px);
    background: linear-gradient(135deg, var(--hcis-brand-500, var(--hcis-indigo-500)) 0%, var(--hcis-brand-700, var(--hcis-indigo-700)) 100%);
    color: var(--hcis-white);
    font-weight: 700;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.permit-requester-identity {
    min-width: 0;
    flex: 1 1 0%;
}

.permit-requester-name {
    font-weight: 700;
    font-size: 0.9375rem;
    color: var(--hcis-slate-900, var(--hcis-slate-900));
    line-height: 1.3;
}

.permit-requester-nik-tag {
    background: var(--hcis-slate-100, var(--hcis-slate-100));
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.permit-requester-role {
    color: var(--hcis-slate-600, var(--hcis-slate-600));
    font-size: 0.75rem;
    font-weight: 500;
    margin-top: 0.15rem;
    line-height: 1.3;
}

.permit-requester-dept-pod {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-2, 0.5rem);
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    border-radius: var(--hcis-radius-md, 8px);
    padding: var(--hcis-space-2, 0.5rem) var(--hcis-space-3, 0.75rem);
}

.permit-requester-dept-pod--clickable {
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    user-select: none;
}

.permit-requester-dept-pod--clickable:hover {
    background: var(--hcis-brand-50, #eef2ff);
    border-color: var(--hcis-brand-200, #c7d2fe);
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.08);
}

.permit-requester-dept-icon--clickable {
    color: var(--hcis-brand-600, #4f46e5) !important;
}

.permit-requester-dept-val--link {
    color: var(--hcis-brand-700, #4338ca) !important;
    font-weight: 700 !important;
}

.permit-ref-link-hint {
    display: inline-flex;
    align-items: center;
    font-size: 0.6875rem;
    font-weight: 650;
    color: var(--hcis-brand-600, #4f46e5);
    background: #ffffff;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.permit-requester-dept-icon {
    color: var(--hcis-slate-400, var(--hcis-slate-400));
}

.permit-requester-dept-info {
    display: flex;
    flex-direction: column;
}

.permit-requester-dept-label {
    font-size: 0.65rem;
    color: var(--hcis-slate-400, var(--hcis-slate-400));
    text-transform: uppercase;
}

.permit-requester-dept-val {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--hcis-slate-800, var(--hcis-slate-800));
}

/* =========================================================================
   VEHICLE SPECS GRID (EXECUTIVE SUITE)
   ========================================================================= */
.permit-specs-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.625rem;
    width: 100%;
}

.permit-spec-tile {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-lg, 12px);
    padding: 0.625rem 0.875rem;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.02), inset 0 1px 0 rgba(255, 255, 255, 0.8);
    transition: transform 0.15s ease, border-color 0.15s ease;
}

.permit-spec-tile:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

.permit-spec-tile--full {
    grid-column: 1 / -1 !important;
}

.permit-spec-tile__label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500, #64748b);
    display: block;
    margin-bottom: 2px;
}

.permit-spec-tile__val {
    font-size: 0.8125rem;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.35;
    word-break: break-word;
}

.permit-spec-tile__icon-box {
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 6px;
    background: #eef2ff;
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    flex-shrink: 0;
}

.permit-spec-plate-pill {
    display: inline-flex;
    align-items: center;
    background: #0f172a;
    color: #ffffff;
    padding: 0.2rem 0.55rem;
    border-radius: 6px;
    border: 1px solid #334155;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.permit-spec-plate-pill__code {
    font-family: var(--hcis-font-tabular, monospace);
    font-size: 0.8125rem;
    font-weight: 800;
    letter-spacing: 0.06em;
}

.permit-spec-color-dot {
    width: 0.875rem;
    height: 0.875rem;
    border-radius: 50%;
    border: 1.5px solid #cbd5e1;
    display: inline-block;
    flex-shrink: 0;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

@media (max-width: 576px) {
    .permit-specs-grid {
        grid-template-columns: 1fr;
    }
}

/* Reason Callout */
.permit-reason-callout {
    background: var(--hcis-brand-50, var(--hcis-indigo-50));
    border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
    border-radius: var(--hcis-radius-lg, 10px);
    padding: var(--hcis-space-3, 0.75rem);
}

.permit-reason-callout__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.permit-reason-callout__icon {
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
}

.permit-reason-callout__title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--hcis-brand-900, var(--hcis-indigo-900));
}

.permit-reason-callout__tag {
    font-size: 0.65rem;
    color: var(--hcis-brand-600, var(--hcis-indigo-600));
    background: rgba(255, 255, 255, 0.7);
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.permit-reason-callout__text {
    font-size: 0.8rem;
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    font-style: italic;
    line-height: 1.4;
}

/* =========================================================================
   REVOCATION DETAILS ALERT
   ========================================================================= */
.permit-revoked-details {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding-top: 0.25rem;
}

.permit-revoked-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.78125rem;
    color: var(--hcis-slate-800, #1e293b);
}

.permit-revoked-label {
    font-weight: 700;
    min-width: 135px;
    color: var(--hcis-danger-700, #b91c1c);
    flex-shrink: 0;
}

.permit-revoked-val {
    flex: 1;
    min-width: 0;
}

/* =========================================================================
   DURATION BOX (RIGHT PANE)
   ========================================================================= */
.permit-duration-box {
    padding: 0.25rem 0;
}

/* =========================================================================
   MITRA B2B INTEGRATION GRID
   ========================================================================= */
.permit-mitra-callout {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
    padding: 0.625rem 0.75rem;
    background: #ecfeff;
    border: 1px solid #cffafe;
    border-radius: 8px;
}

.permit-mitra-callout__content {
    font-size: 0.78125rem;
    color: #0e7490;
    line-height: 1.4;
}

.permit-mitra-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.625rem;
}

.permit-mitra-tile {
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    min-width: 0;
    transition: all 0.15s ease;
}

.permit-mitra-tile:hover {
    background: #ffffff;
    border-color: var(--hcis-slate-300, #cbd5e1);
}

.permit-mitra-tile__label {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400, #94a3b8);
    display: block;
    margin-bottom: 2px;
}

.permit-mitra-tile__val {
    font-size: 0.8125rem;
    line-height: 1.3;
}

@media (max-width: 640px) {
    .permit-mitra-grid {
        grid-template-columns: 1fr;
    }
}

/* =========================================================================
   DOCUMENTS & ATTACHMENTS
   ========================================================================= */
.permit-requester-nik-tag--aplikasi {
    font-family: inherit;
    letter-spacing: 0;
}

.permit-docs-slots {
    width: 100%;
}

.visitor-docs-list {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    width: 100%;
}

.visitor-doc-card {
    background: var(--hcis-white, #ffffff);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-xl, 14px);
    padding: 0.75rem 0.875rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    transition: border-color 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease, background 0.2s ease;
}

.visitor-doc-card:hover {
    border-color: var(--hcis-brand-300, #a5b4fc);
    box-shadow: 0 4px 12px -2px rgba(99, 102, 241, 0.08);
}

.visitor-doc-card.is-staged {
    border-color: #fcd34d;
    background: linear-gradient(180deg, #fffdfa 0%, #ffffff 100%);
    box-shadow: 0 2px 8px -2px rgba(245, 158, 11, 0.12);
}

.visitor-doc-card.is-existing {
    border-color: #a7f3d0;
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
}

.visitor-doc-card.is-error {
    border-color: #ef4444 !important;
    background: linear-gradient(180deg, #fef2f2 0%, #ffffff 100%) !important;
    box-shadow: 0 0 0 1px #f87171, 0 4px 12px -2px rgba(239, 68, 68, 0.15) !important;
}

.visitor-doc-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.625rem;
}

.visitor-doc-card__title-wrap {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    min-width: 0;
    flex: 1;
}

.visitor-doc-card__icon {
    width: 1.875rem;
    height: 1.875rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.visitor-doc-card__icon.is-brand {
    background: linear-gradient(135deg, #ffffff 0%, #eef2ff 100%);
    color: #4f46e5;
    border: 1px solid #c7d2fe;
}

.visitor-doc-card__icon.is-teal {
    background: linear-gradient(135deg, #ffffff 0%, #f0fdfa 100%);
    color: #0d9488;
    border: 1px solid #99f6e4;
}

.visitor-doc-card__icon.is-purple {
    background: linear-gradient(135deg, #ffffff 0%, #faf5ff 100%);
    color: #9333ea;
    border: 1px solid #e9d5ff;
}

.visitor-doc-card__icon.is-danger {
    background: linear-gradient(135deg, #ffffff 0%, #fee2e2 100%);
    color: #dc2626;
    border: 1px solid #fca5a5;
}

.visitor-doc-card__error-banner {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.65rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: var(--hcis-radius-md, 8px);
    color: #b91c1c;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.35;
    animation: hcis-shake 0.4s ease-in-out;
}

.visitor-doc-card__error-text {
    flex: 1;
    word-break: break-word;
}

@keyframes hcis-shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-4px); }
    40%, 80% { transform: translateX(4px); }
}

.visitor-doc-card__meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.visitor-doc-card__title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.25;
}

.visitor-doc-card__desc {
    font-size: 0.6875rem;
    color: var(--hcis-slate-500, #64748b);
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.visitor-doc-card__badge-wrap {
    flex-shrink: 0;
}

.visitor-doc-card__uploader {
    width: 100%;
}

/* Hide limit banner in compact card mode since file card itself is the file */
.visitor-doc-card :deep(.hcis-upload__limit-banner) {
    display: none !important;
}

.visitor-doc-card :deep(.hcis-upload) {
    gap: 0;
}

.visitor-doc-card :deep(.hcis-upload__list) {
    margin-top: 0;
}

.visitor-doc-card :deep(.hcis-upload__file-card) {
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.5rem 0.75rem;
}

/* =========================================================================
   VERIFICATION DOCUMENTS LIST (READ-ONLY & PREVIEW)
   ========================================================================= */
.permit-docs-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.permit-doc-item {
    background: var(--hcis-white, #ffffff);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 0.55rem 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.permit-doc-item:hover {
    border-color: var(--hcis-brand-400, #818cf8);
    background: var(--hcis-slate-50, #f8fafc);
    box-shadow: 0 2px 8px -2px rgba(99, 102, 241, 0.12);
}

.permit-doc-icon-box {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.permit-doc-icon--brand {
    background: var(--hcis-indigo-50, #eef2ff);
    color: var(--hcis-indigo-600, #4f46e5);
    border: 1px solid var(--hcis-indigo-200, #c7d2fe);
}

.permit-doc-icon--teal {
    background: #f0fdfa;
    color: #0d9488;
    border: 1px solid #99f6e4;
}

.permit-doc-icon--purple {
    background: #faf5ff;
    color: var(--hcis-violet-600, #9333ea);
    border: 1px solid #e9d5ff;
}

.permit-doc-icon--slate {
    background: var(--hcis-slate-50, #f8fafc);
    color: var(--hcis-slate-500, #64748b);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
}

.permit-doc-info {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.permit-doc-main-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.permit-doc-title {
    font-size: 0.8125rem;
    font-weight: 750;
    color: var(--hcis-slate-800, #1e293b);
    line-height: 1.25;
}

.permit-doc-ext-tag {
    font-size: 0.5625rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--hcis-slate-600, #475569);
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    padding: 0.08rem 0.35rem;
    border-radius: 4px;
    letter-spacing: 0.04em;
    flex-shrink: 0;
    line-height: 1.2;
}

.permit-doc-sub-row {
    font-size: 0.6875rem;
    color: var(--hcis-slate-500, #64748b);
    display: flex;
    align-items: center;
    gap: 0.25rem;
    line-height: 1.2;
}

.permit-doc-action {
    flex-shrink: 0;
}

.permit-doc-view-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    height: 1.85rem;
    padding: 0 0.65rem;
    border-radius: 6px;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    background: var(--hcis-slate-50, #f8fafc);
    color: var(--hcis-slate-700, #334155);
    font-family: inherit;
    font-size: 0.6875rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.permit-doc-view-pill:hover {
    background: var(--hcis-indigo-50, #eef2ff);
    color: var(--hcis-indigo-700, #4338ca);
    border-color: var(--hcis-indigo-200, #c7d2fe);
}

/* Staged alert banner */
.visitor-docs-pending-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
    padding: 0.625rem 0.875rem;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 1px solid #fde68a;
    border-radius: var(--hcis-radius-lg, 10px);
    margin-bottom: 0.75rem;
}

.visitor-docs-pending-banner__icon {
    color: #d97706;
    font-size: 0.95rem;
    margin-top: 1px;
    flex-shrink: 0;
}

.visitor-docs-pending-banner__content {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.visitor-docs-pending-banner__title {
    font-size: 0.78125rem;
    font-weight: 700;
    color: #92400e;
    line-height: 1.3;
}

.visitor-docs-pending-banner__subtitle {
    font-size: 0.6875rem;
    color: #b45309;
    line-height: 1.35;
}

/* =========================================================================
   JADWAL INSPEKSI TICKET SECTION (UI/UX UPGRADED)
   ========================================================================= */
.permit-jadwal-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.permit-jadwal-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-brand-700, #4338ca);
    background: var(--hcis-brand-50, #eef2ff);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
    padding: 0.22rem 0.6rem;
    border-radius: 9999px;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.15s ease-in-out;
}

.permit-jadwal-link:hover {
    color: #ffffff;
    background: var(--hcis-brand-600, #4f46e5);
    border-color: var(--hcis-brand-600, #4f46e5);
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
}

.permit-inspeksi__ticket {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.95rem 1.05rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.75) 0%, rgba(248, 250, 252, 0.95) 100%);
    border: 1.5px solid var(--hcis-brand-100, #e0e7ff);
    box-shadow: 0 2px 8px -2px rgba(79, 70, 229, 0.06);
}

.permit-inspeksi__ticket-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.permit-inspeksi__ticket-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 8px;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
}

.permit-inspeksi__ticket-icon.is-calendar {
    background: #ffffff;
    color: var(--hcis-brand-600, #4f46e5);
    border: 1px solid var(--hcis-brand-200, #c7d2fe);
}

.permit-inspeksi__ticket-icon.is-location {
    background: #ffffff;
    color: var(--hcis-emerald-600, #059669);
    border: 1px solid var(--hcis-emerald-200, #a7f3d0);
}

.permit-inspeksi__ticket-icon.is-history {
    background: #ffffff;
    color: var(--hcis-slate-600, #475569);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
}

.permit-inspeksi__ticket-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    flex: 1 1 auto;
}

.permit-inspeksi__ticket-label {
    font-size: 0.65625rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500, #64748b);
    line-height: 1.2;
}

.permit-inspeksi__datetime-wrap {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.permit-inspeksi__date {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.25;
}

.permit-inspeksi__time-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    font-size: 0.71875rem;
    font-weight: 750;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.25);
}

.permit-inspeksi__shift-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-700, #334155);
    font-size: 0.6875rem;
    font-weight: 700;
    white-space: nowrap;
}

.permit-inspeksi__ticket-divider {
    height: 1px;
    background: var(--hcis-brand-100, #e0e7ff);
    margin: 0.1rem 0;
}

.permit-inspeksi__pos-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.permit-inspeksi__pos-name {
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
}

.permit-inspeksi__pos-tag {
    font-size: 0.71875rem;
    color: var(--hcis-slate-600, #475569);
    font-weight: 600;
}

.permit-inspeksi__chips-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.permit-inspeksi__chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 600;
    background: #ffffff;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-700, #334155);
}

.permit-inspeksi__chip strong {
    font-weight: 800;
    color: var(--hcis-slate-900, #0f172a);
}

.permit-inspeksi__chip.is-warning {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}
.permit-inspeksi__chip.is-warning strong {
    color: #78350f;
}

.permit-inspeksi__chip.is-danger {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
}
.permit-inspeksi__chip.is-danger strong {
    color: #7f1d1d;
}

.permit-inspeksi__chip.is-muted {
    opacity: 0.85;
}

.permit-inspeksi__aktual-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.71875rem;
    margin-top: 0.15rem;
}

.permit-inspeksi__aktual-tag.is-masuk {
    color: #059669;
}

.permit-inspeksi__aktual-tag.is-keluar {
    color: #64748b;
}

.permit-inspeksi__notice {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.6rem 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.75rem;
    line-height: 1.45;
}

.permit-inspeksi__notice.is-info {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.permit-inspeksi__notice.is-info .hcis-icon {
    color: #16a34a;
    margin-top: 0.1rem;
}

.permit-inspeksi__notice.is-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

.permit-inspeksi__notice.is-warning .hcis-icon {
    color: #d97706;
    margin-top: 0.1rem;
}

.permit-inspeksi__notice-text {
    flex: 1 1 auto;
    min-width: 0;
}

.permit-inspeksi__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem;
    text-align: center;
}

@media (max-width: 576px) {
    .permit-review-jadwal-card :deep(.hcis-card__description) {
        display: none !important;
    }
    .permit-inspeksi__ticket {
        padding: 0.75rem 0.85rem;
        gap: 0.65rem;
    }
    .permit-inspeksi__ticket-icon {
        width: 2rem;
        height: 2rem;
        font-size: 0.85rem;
    }
    .permit-inspeksi__date {
        font-size: 0.8125rem;
    }
    .permit-inspeksi__time-badge {
        font-size: 0.6875rem;
        padding: 0.12rem 0.4rem;
    }
    .permit-inspeksi__chip {
        font-size: 0.65625rem;
        padding: 0.15rem 0.45rem;
    }
}

/* =========================================================================
   DIALOG KONFIRMASI PERSETUJUAN AWAL JADWAL (UI/UX UPGRADED)
   ========================================================================= */
.permit-early-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.permit-early-ticket {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.45) 0%, rgba(255, 251, 235, 0.9) 100%);
    border: 1.5px solid #fde68a;
    box-shadow: 0 2px 6px rgba(217, 119, 6, 0.05);
}

.permit-early-ticket__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 8px;
    background: #ffffff;
    color: #d97706;
    border: 1px solid #fde68a;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(217, 119, 6, 0.1);
}

.permit-early-ticket__body {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    flex: 1 1 auto;
}

.permit-early-ticket__label {
    font-size: 0.65625rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #92400e;
}

.permit-early-ticket__time {
    font-size: 0.9375rem;
    font-weight: 800;
    color: #78350f;
    line-height: 1.25;
}

.permit-early-ticket__pos {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: #92400e;
    flex-wrap: wrap;
}

.permit-early-ticket__status-tag {
    font-size: 0.6875rem;
    font-weight: 600;
    color: #b45309;
}

.permit-early-impacts {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.permit-early-impact-item {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.6rem 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.permit-early-impact-item__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.permit-early-impact-item__icon.is-success {
    background: #dcfce7;
    color: #16a34a;
}

.permit-early-impact-item__icon.is-info {
    background: #e0e7ff;
    color: #4f46e5;
}

.permit-early-impact-item__text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.permit-early-impact-item__text strong {
    font-size: 0.78125rem;
    font-weight: 750;
    color: var(--hcis-slate-800, #1e293b);
}

.permit-early-impact-item__text span {
    font-size: 0.71875rem;
    color: var(--hcis-slate-600, #475569);
    line-height: 1.35;
}

.permit-early-callout {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding: 0.75rem 0.85rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    color: #92400e;
}

.permit-early-callout .hcis-icon {
    color: #d97706;
    margin-top: 0.15rem;
}

.permit-early-callout__text {
    flex: 1 1 auto;
    font-size: 0.75rem;
    line-height: 1.4;
}

.permit-early-callout__text strong {
    display: block;
    font-weight: 750;
    color: #78350f;
    margin-bottom: 0.15rem;
}

.permit-early-callout__sub {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.71875rem;
    color: #b45309;
}

.permit-early-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    width: 100%;
}

@media (max-width: 576px) {
    .permit-early-ticket {
        padding: 0.75rem 0.85rem;
        gap: 0.6rem;
    }
    .permit-early-ticket__icon {
        width: 2.2rem;
        height: 2.2rem;
    }
    .permit-early-ticket__time {
        font-size: 0.875rem;
    }
    .permit-early-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        width: 100%;
    }
    .permit-early-actions :deep(.hcis-btn) {
        width: 100%;
        justify-content: center;
    }
}

/* =========================================================================
   DIALOG LARANGAN PERSETUJUAN PENGAJUAN SENDIRI (FOUR-EYES PRINCIPLE)
   ========================================================================= */
.permit-own-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.permit-own-ticket {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: linear-gradient(135deg, rgba(254, 243, 199, 0.5) 0%, rgba(255, 251, 235, 0.95) 100%);
    border: 1.5px solid #fde68a;
    box-shadow: 0 2px 6px rgba(217, 119, 6, 0.06);
}

.permit-own-ticket__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 8px;
    background: #ffffff;
    color: #d97706;
    border: 1px solid #fde68a;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(217, 119, 6, 0.1);
}

.permit-own-ticket__body {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    flex: 1 1 auto;
}

.permit-own-ticket__label {
    font-size: 0.65625rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #92400e;
}

.permit-own-ticket__name {
    font-size: 0.9375rem;
    font-weight: 800;
    color: #78350f;
    line-height: 1.25;
}

.permit-own-ticket__meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: #92400e;
    flex-wrap: wrap;
}

.permit-own-ticket__match-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    font-weight: 650;
    color: #b45309;
    background: rgba(254, 243, 199, 0.8);
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    border: 1px solid #fde68a;
    width: fit-content;
    margin-top: 0.15rem;
}

.permit-own-policy {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.75rem 0.85rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.permit-own-policy__header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.permit-own-policy__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 4px;
    background: #e0e7ff;
    color: #4338ca;
    flex-shrink: 0;
}

.permit-own-policy__header strong {
    font-size: 0.78125rem;
    font-weight: 750;
    color: #1e293b;
}

.permit-own-policy__desc {
    font-size: 0.71875rem;
    color: #475569;
    line-height: 1.4;
    margin: 0;
}

.permit-own-steps {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.75rem 0.85rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
}

.permit-own-steps__title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 750;
    color: #92400e;
}

.permit-own-steps__list {
    margin: 0;
    padding-left: 1.15rem;
    font-size: 0.71875rem;
    color: #78350f;
    line-height: 1.4;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.permit-own-steps__list li strong {
    color: #92400e;
}

.permit-own-steps__note {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    color: #b45309;
    margin-top: 0.2rem;
    padding-top: 0.35rem;
    border-top: 1px dashed #fde68a;
}

.permit-own-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
}

.permit-own-actions__btn {
    min-width: 140px;
}

@media (max-width: 576px) {
    .permit-own-ticket {
        padding: 0.75rem 0.85rem;
        gap: 0.6rem;
    }
    .permit-own-ticket__icon {
        width: 2.2rem;
        height: 2.2rem;
    }
    .permit-own-ticket__name {
        font-size: 0.875rem;
    }
    .permit-own-actions__btn {
        width: 100% !important;
        justify-content: center !important;
    }
}

/* =========================================================================
   CHECKLIST PEMERIKSAAN FISIK KENDARAAN (UI/UX UPGRADED)
   ========================================================================= */
.permit-checklist-card {
    transition: all 0.2s ease;
}

@media (max-width: 576px) {
    .permit-checklist-card :deep(.hcis-card__header) {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }
    .permit-checklist-card :deep(.hcis-card__actions) {
        justify-content: flex-end;
        width: 100%;
    }
    .permit-checklist-card :deep(.hcis-card__description) {
        display: none !important;
    }
}

.chk-container {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

/* Progress bar strip */
.chk-progress-bar-wrap {
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.55rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.chk-progress-track {
    width: 100%;
    height: 6px;
    background: var(--hcis-slate-200, #e2e8f0);
    border-radius: 999px;
    overflow: hidden;
}

.chk-progress-fill {
    height: 100%;
    background: var(--hcis-amber-500, #f59e0b);
    border-radius: 999px;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s ease;
}

.chk-progress-fill.is-complete {
    background: var(--hcis-emerald-500, #10b981);
}

.chk-progress-info {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.71875rem;
    line-height: 1.3;
}

.chk-progress-counter {
    color: var(--hcis-slate-600, #475569);
}

.chk-progress-status {
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.text-emerald {
    color: var(--hcis-emerald-600, #059669);
}

.text-amber {
    color: var(--hcis-amber-600, #d97706);
}

.chk-list {
    container-type: inline-size;
    list-style: none;
    margin: 0;
    padding: 0;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 10px);
    overflow: hidden;
    background: var(--hcis-white, #ffffff);
}

.chk-item {
    padding: 0.85rem 0.95rem;
    border-left: 3.5px solid transparent;
    background: var(--hcis-white, #ffffff);
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.chk-item + .chk-item {
    border-top: 1px solid var(--hcis-slate-100, #f1f5f9);
}

.chk-item:hover:not(.is-tidak) {
    background: #f8fafc;
}

.chk-item.is-kosong {
    border-left-color: var(--hcis-warning-400, #fbbf24);
    background: #fffdf5;
}
.chk-item.is-ya {
    border-left-color: var(--hcis-success-500, #10b981);
}
.chk-item.is-tidak {
    border-left-color: var(--hcis-danger-500, #ef4444);
    background: var(--hcis-danger-50, #fef2f2);
}
.chk-item.is-na {
    border-left-color: var(--hcis-slate-300, #cbd5e1);
}

.chk-item__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
}

.chk-item__question {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    min-width: 0;
    flex: 1;
}

.chk-item__num {
    flex-shrink: 0;
    width: 1.35rem;
    height: 1.35rem;
    margin-top: 0.05rem;
    border-radius: 999px;
    background: var(--hcis-slate-100, #f1f5f9);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-600, #475569);
    font-size: 0.6875rem;
    font-weight: 750;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.chk-item.is-ya .chk-item__num {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #059669;
}
.chk-item.is-tidak .chk-item__num {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}
.chk-item.is-na .chk-item__num {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #64748b;
}
.chk-item.is-kosong .chk-item__num {
    background: #fef3c7;
    border-color: #fde68a;
    color: #b45309;
}

.chk-item__text {
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1.45;
    color: var(--hcis-slate-800, #1e293b);
}

.chk-item__wajib {
    color: var(--hcis-danger-500, #ef4444);
    font-weight: 700;
    margin-left: 0.15rem;
}

/* Kontrol segmen: tiga tombol setara dalam satu bingkai */
.chk-seg {
    flex-shrink: 0;
    display: inline-flex;
    padding: 2.5px;
    gap: 3px;
    border: 1.5px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 8px;
    background: var(--hcis-slate-100, #f1f5f9);
}

.chk-seg__opt {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    min-width: 4.5rem;
    height: 2.15rem;
    padding: 0 0.65rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-slate-600, #475569);
    cursor: pointer;
    user-select: none;
    transition: all 0.15s ease;
    -webkit-tap-highlight-color: transparent;
}

.chk-seg__opt:active {
    transform: scale(0.97);
}

.chk-seg__opt--ya:hover:not(.is-active) {
    color: #059669;
    background: #ecfdf5;
}
.chk-seg__opt--tidak:hover:not(.is-active) {
    color: #dc2626;
    background: #fef2f2;
}
.chk-seg__opt--na:hover:not(.is-active) {
    color: #1e293b;
    background: #ffffff;
}

.chk-seg__input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.chk-seg__opt:has(.chk-seg__input:focus-visible) {
    outline: 2px solid var(--hcis-brand-500, #6366f1);
    outline-offset: 1px;
}

.chk-seg__opt.is-active {
    font-weight: 750;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.08);
}
.chk-seg__opt--ya.is-active {
    background: var(--hcis-success-500, #10b981);
    color: #ffffff;
}
.chk-seg__opt--tidak.is-active {
    background: var(--hcis-danger-500, #ef4444);
    color: #ffffff;
}
.chk-seg__opt--na.is-active {
    background: var(--hcis-slate-500, #64748b);
    color: #ffffff;
}

.chk-item__note {
    margin-top: 0.65rem;
    padding-left: 1.95rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.chk-item__note-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.chk-item__note-label {
    font-size: 0.6875rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.chk-item__note-dismiss {
    border: none;
    background: transparent;
    color: var(--hcis-slate-400, #94a3b8);
    cursor: pointer;
    padding: 0.1rem 0.25rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.chk-item__note-dismiss:hover {
    color: var(--hcis-slate-700, #334155);
    background: var(--hcis-slate-100, #f1f5f9);
}

.chk-item__note :deep(*) {
    box-sizing: border-box;
}

.chk-item__note-toggle {
    margin-top: 0.35rem;
    margin-left: 1.95rem;
    padding: 0.15rem 0.45rem;
    border: 1px dashed var(--hcis-slate-300, #cbd5e1);
    border-radius: 5px;
    background: var(--hcis-slate-50, #f8fafc);
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-brand-600, #4f46e5);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    cursor: pointer;
    transition: all 0.15s ease;
}

.chk-item__note-toggle:hover {
    background: #eef2ff;
    border-color: #a5b4fc;
    color: #4338ca;
}

.chk-item__note-readonly {
    margin: 0.45rem 0 0 1.95rem;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    font-size: 0.75rem;
    color: var(--hcis-slate-700, #334155);
    display: flex;
    align-items: flex-start;
    line-height: 1.4;
}

/* Panel sempit (panel kanan modal atau ponsel): pertanyaan di atas,
   tombol selebar baris. */
@container (max-width: 580px) {
    .chk-item__row {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }
    .chk-seg {
        display: flex;
        margin-left: 0;
        width: 100%;
    }
    .chk-seg__opt {
        flex: 1;
        min-width: 0;
        height: 2.35rem;
        font-size: 0.78125rem;
    }
    .chk-item__note {
        margin-top: 0.5rem;
        padding-left: 0;
    }
    .chk-item__note-toggle {
        margin-left: 0;
    }
    .chk-item__note-readonly {
        margin-left: 0;
    }
}

@media (max-width: 640px) {
    .chk-item__row {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
    }
    .chk-seg {
        display: flex;
        margin-left: 0;
        width: 100%;
    }
    .chk-seg__opt {
        flex: 1;
        min-width: 0;
        height: 2.35rem;
        font-size: 0.78125rem;
    }
    .chk-item__note {
        margin-top: 0.5rem;
        padding-left: 0;
    }
    .chk-item__note-toggle {
        margin-left: 0;
    }
    .chk-item__note-readonly {
        margin-left: 0;
    }
}

/* =========================================================================
   APPROVAL TIMELINE IN DUAL-PANE SIDEBAR
   ========================================================================= */
.permit-approval-timeline-wrapper :deep(.hcis-timeline-card__head) {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.35rem;
}

.permit-approval-timeline-wrapper :deep(.hcis-timeline-card__actor) {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    min-width: 0;
    width: 100%;
}

.permit-approval-timeline-wrapper :deep(.hcis-timeline-actor-info) {
    min-width: 0;
    flex: 1;
}

.permit-approval-timeline-wrapper :deep(.hcis-timeline-time-badge) {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    margin-left: 2.75rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
    background: var(--hcis-slate-50, var(--hcis-slate-50));
    border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    white-space: nowrap;
}

.permit-berlaku-ringkas {
    font-size: 0.8125rem;
    color: var(--hcis-text-muted, #64748b);
}

.permit-auto-approved-box {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--hcis-slate-50, #f8fafc);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: 10px;
}

.permit-auto-approved-badge {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: #ecfdf5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid #a7f3d0;
}

.permit-auto-approved-content {
    min-width: 0;
    flex: 1 1 0%;
}

.permit-auto-approved-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-slate-900, #0f172a);
    line-height: 1.25;
}

.permit-auto-approved-desc {
    font-size: 0.71875rem;
    color: var(--hcis-slate-600, #475569);
    line-height: 1.35;
    margin-top: 0.2rem;
}

.permit-auto-approved-time {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-slate-500, #64748b);
    background: #ffffff;
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    margin-top: 0.4rem;
}

.permit-modal-footer-wrapper {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
    gap: 0.75rem;
}

.permit-footer-own-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.65rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
    min-width: 0;
    margin-right: auto;
}

.permit-footer-own-notice:hover {
    background: #fef3c7;
    border-color: #f59e0b;
}

.permit-footer-own-notice__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #d97706;
    flex-shrink: 0;
}

.permit-footer-own-notice__text {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.71875rem;
    color: #92400e;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.permit-footer-own-notice__text strong {
    font-weight: 750;
    color: #78350f;
}

.permit-footer-own-notice__text u {
    font-weight: 650;
    color: #b45309;
    text-decoration: underline;
}

.permit-own-alert {
    border-radius: 10px;
}

.permit-own-alert__text {
    font-size: 0.78125rem;
    line-height: 1.4;
    color: #92400e;
    margin: 0;
}

.permit-modal-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

@media (max-width: 991px) {
    :deep(.hcis-dialog__description) {
        display: none !important;
    }

    .permit-modal-footer-wrapper {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .permit-footer-own-notice {
        width: 100%;
        justify-content: center;
        padding: 0.45rem 0.75rem;
        margin-right: 0;
    }

    .permit-footer-own-notice__text {
        white-space: normal;
        text-align: center;
        flex-wrap: wrap;
        justify-content: center;
    }

    .permit-modal-footer-actions {
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
    }

    .permit-modal-footer-actions .permit-footer-btn,
    .permit-modal-footer-actions :deep(.hcis-button),
    .permit-modal-footer-actions :deep(button) {
        flex: 1 1 0% !important;
        width: 100% !important;
        justify-content: center !important;
    }
}

:deep(.hcis-dialog__title) {
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    min-width: 0 !important;
}

:deep(.hcis-dialog__title-row) {
    flex-wrap: nowrap !important;
    min-width: 0 !important;
}
</style>

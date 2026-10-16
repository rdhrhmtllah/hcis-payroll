<template>
    <main class="pa-bal-board-page">
        <!-- 1. Unified Executive Glass Header (Design System HcisHeader) -->
        <HcisHeader
            :title="sesi ? sesi.nama : 'Sesi Balancing'"
            kicker="PERFORMANCE APPRAISAL · BALANCING"
            :description="sesi ? `PIC ${sesi.pic_nama || '–'} · ${peserta.length} peserta terdaftar dalam sesi penyeimbangan kompetensi ini.` : ''"
            icon="sliders"
            sentiment="brand"
            variant="glass"
            size="md"
            animated
            :loading="memuat"
            class="pa-bal-header"
        >
            <template #badge>
                <div v-if="sesi" class="pa-bal-header-badges">
                    <HcisBadge
                        :label="LABEL_STATUS[sesi.status] || sesi.status"
                        :variant="VARIAN_STATUS[sesi.status]"
                        appearance="glass"
                        size="sm"
                        :dot="sesi.status === 'TERBUKA'"
                        :radar="sesi.status === 'TERBUKA'"
                    />
                    <HcisBadge
                        v-if="kotor"
                        label="Draf Belum Disimpan"
                        variant="warning"
                        appearance="glass"
                        size="sm"
                        dot
                    />
                </div>
            </template>

            <template #actions>
                <div class="pa-bal-board-actions">
                    <HcisButton
                        label="Kembali"
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-left"
                        aria-label="Kembali ke daftar sesi balancing"
                        @click="kembali"
                    />

                    <template v-if="bisaUbah">
                        <HcisButton
                            label="Simulasikan"
                            variant="glass"
                            size="sm"
                            leading-icon="magic"
                            :loading="menyimulasikan"
                            :disabled="adaDiLuarRentang"
                            title="Simulasikan dampak penyesuaian skor kompetensi ke kurva grup"
                            @click="simulasikan"
                        />
                        <HcisButton
                            label="Simpan draft"
                            variant="primary"
                            size="sm"
                            leading-icon="save"
                            :loading="menyimpan"
                            :disabled="adaGalat || !kotor"
                            title="Simpan draf sementara sesi balancing"
                            @click="simpan"
                        />
                        <HcisButton
                            label="Finalkan"
                            variant="success"
                            size="sm"
                            leading-icon="check2-circle"
                            :disabled="adaGalat || kotor || belumDitinjau > 0"
                            title="Kunci sesi secara permanen setelah semua peserta ditinjau"
                            @click="dialogFinal = true"
                        />
                        <HcisButton
                            label="Batalkan sesi"
                            variant="danger"
                            size="sm"
                            leading-icon="x-circle"
                            @click="dialogBatal = true"
                        />
                    </template>
                </div>
            </template>
        </HcisHeader>

        <!-- Loading & Error States -->
        <HcisSkeleton v-if="memuat" :rows="6" />

        <HcisResultState
            v-else-if="galatMuat"
            status="error"
            title="Sesi tidak bisa dimuat"
            :description="galatMuat"
            primary-text="Coba Lagi"
            @primary-click="muat"
        />

        <section v-else-if="sesi" class="pa-bal-board-content" aria-live="polite">
            <!-- Action Alerts & Notifications -->
            <HcisAlert v-if="galatAksi" variant="danger" :title="galatAksi" class="mb-2">
                <HcisButton v-if="versiBasi" label="Muat ulang" size="sm" variant="secondary" @click="muat" />
            </HcisAlert>

            <!-- Locked / Readonly State Banner for Final or Cancelled Sessions -->
            <HcisAlert
                v-if="!bisaUbah"
                :variant="sesi.status === 'FINAL' ? 'success' : 'neutral'"
                :title="sesi.status === 'FINAL' ? 'Sesi Telah Difinalisasi' : 'Sesi Dibatalkan'"
                :description="sesi.status === 'FINAL'
                    ? (sesi.hitung_ulang_at ? `Hasil telah dihitung ulang ke kurva grup (Run #${sesi.nomor_hitung_ulang}). Seluruh isian telah dikunci.` : 'Menunggu hitung ulang di halaman daftar Balancing sebelum dipublish.')
                    : `Sesi ini telah dibatalkan dengan alasan: ${sesi.alasan_batal || 'Tidak disebutkan'}.`"
                class="mb-2"
            />

            <!-- Review Progress Info Alert -->
            <p v-if="bisaUbah && belumDitinjau > 0" class="pa-bal-board__info">
                {{ belumDitinjau }} peserta belum ditinjau. Finalkan aktif setelah semua ditinjau dan draft tersimpan.
            </p>

            <!-- 2. Consolidated Progress Bento Card with Integrated Forum Roster Trigger -->
            <HcisCard
                variant="glass"
                sentiment="brand"
                title="Status Peninjauan Peserta"
                kicker="PROGRES FORUM BALANCING"
                :badge="`${persenTinjau}% Rampung`"
                :badge-variant="persenTinjau >= 100 ? 'success' : 'brand'"
                icon="clipboard2-check-fill"
                class="pa-bal-progress-card"
            >
                <template #actions>
                    <!-- Compact Forum Committee Summary Pill -->
                    <button
                        type="button"
                        class="pa-bal-forum-pill-btn"
                        title="Klik untuk melihat daftar komite forum balancing"
                        @click="dialogForum = true"
                    >
                        <HcisAvatar :name="sesi.pic_nama || 'PIC'" size="xs" shape="circle" />
                        <div class="pa-bal-forum-pill-copy">
                            <span class="pa-bal-forum-pill-pic">PIC: <strong>{{ sesi.pic_nama || '–' }}</strong></span>
                            <span class="pa-bal-forum-pill-badge">
                                <HcisIcon name="people-fill" size="2xs" />
                                {{ sesi.forum.length + 1 }} Komite
                            </span>
                        </div>
                        <HcisIcon name="chevron-right" size="2xs" class="pa-bal-forum-pill-chevron" />
                    </button>
                </template>

                <div class="pa-bal-progress-body">
                    <!-- Design System HcisProgress -->
                    <HcisProgress
                        :percentage="persenTinjau"
                        :sentiment="persenTinjau >= 100 ? 'success' : 'brand'"
                        size="sm"
                        striped
                        animated
                        :show-text="false"
                        class="mb-3"
                    />

                    <!-- Design System HcisStatGroup & HcisStatCard -->
                    <HcisStatGroup :cols="4" gap="sm">
                        <HcisStatCard
                            layout="compact"
                            label="Total"
                            :value="peserta.length"
                            caption="Peserta sesi"
                            icon="people-fill"
                            sentiment="neutral"
                        />
                        <HcisStatCard
                            layout="compact"
                            label="Diselaraskan"
                            :value="jumlahDiubah"
                            caption="Skor disesuaikan"
                            icon="sliders"
                            sentiment="brand"
                        />
                        <HcisStatCard
                            layout="compact"
                            label="Disetujui"
                            :value="jumlahTanpaPerubahan"
                            caption="Tanpa perubahan"
                            icon="check2-circle"
                            sentiment="success"
                        />
                        <HcisStatCard
                            layout="compact"
                            label="Belum Ditinjau"
                            :value="belumDitinjau"
                            :caption="belumDitinjau > 0 ? 'Perlu ditinjau' : 'Semua tuntas'"
                            icon="hourglass-split"
                            :sentiment="belumDitinjau > 0 ? 'warning' : 'neutral'"
                        />
                    </HcisStatGroup>
                </div>
            </HcisCard>

            <!-- 4. Participants Filter Bar (Design System HcisFilterBar) -->
            <HcisFilterBar
                :search="kataKunciPeserta"
                search-placeholder="Cari nama karyawan atau NIK peserta..."
                :quick-chips="pesertaFilterChips"
                :active-chip-value="filterTinjau"
                variant="card"
                sentiment="brand"
                size="sm"
                class="pa-bal-peserta-filterbar"
                @update:search="(v) => kataKunciPeserta = v"
                @chip-click="(c) => filterTinjau = c.value"
                @reset="resetFilterPeserta"
            >
                <template #actions>
                    <HcisButton
                        v-if="bisaUbah && belumDitinjau > 0"
                        label="Tandai Sisa Tanpa Perubahan"
                        variant="glass"
                        size="xs"
                        leading-icon="check-all"
                        title="Tandai seluruh peserta yang belum disentuh sebagai Tanpa Perubahan"
                        @click="tandaiSisaTanpaPerubahan"
                    />
                    <HcisButton
                        :label="semuaTerbuka ? 'Tutup Semua' : 'Buka Semua'"
                        variant="glass"
                        size="xs"
                        :leading-icon="semuaTerbuka ? 'chevron-bar-contract' : 'chevron-bar-expand'"
                        @click="toggleBukaSemua"
                    />
                </template>
            </HcisFilterBar>

            <!-- 5. Main Board (Participants List / Matriks Table) -->
            <div class="pa-bal-board__tata">
                <div class="pa-bal-board__daftar">
                    <HcisEmptyState
                        v-if="!pesertaTersaring.length"
                        variant="card"
                        sentiment="neutral"
                        size="md"
                        title="Tidak ada peserta yang cocok"
                        description="Coba ubah kata kunci pencarian atau filter status peserta di atas."
                        icon="people"
                    >
                        <template #actions>
                            <HcisButton label="Reset Filter" variant="secondary" size="sm" @click="resetFilterPeserta" />
                        </template>
                    </HcisEmptyState>

                    <!-- Participants Container Card (Enclosed Card with Table and Card views) -->
                    <HcisCard
                        v-else
                        variant="glass"
                        sentiment="brand"
                        title="Peserta Sesi Balancing"
                        icon="people-fill"
                        divider
                        class="pa-bal-peserta-card-container"
                    >
                        <template #badge>
                            <HcisBadge
                                :label="`${pesertaTersaring.length} Peserta`"
                                variant="brand"
                                appearance="subtle"
                                size="xs"
                            />
                        </template>

                        <template #description>
                            <span class="pa-bal-header-pills">
                                <button
                                    type="button"
                                    class="pa-bal-pill is-adjusted"
                                    :class="{ 'is-active': filterTinjau === 'DIUBAH' }"
                                    title="Klik untuk filter: Peserta dengan nilai disesuaikan"
                                    @click="filterTinjau = filterTinjau === 'DIUBAH' ? 'SEMUA' : 'DIUBAH'"
                                >
                                    <span class="pa-bal-pill-dot is-adjusted" />
                                    <HcisIcon name="sliders" size="2xs" />
                                    <span class="pa-bal-pill-text"><strong>{{ jumlahDiubah }}</strong> disesuaikan</span>
                                </button>

                                <span class="pa-bal-pill-sep" aria-hidden="true" />

                                <button
                                    type="button"
                                    class="pa-bal-pill is-unchanged"
                                    :class="{ 'is-active': filterTinjau === 'TANPA_PERUBAHAN' }"
                                    title="Klik untuk filter: Peserta disetujui tanpa perubahan"
                                    @click="filterTinjau = filterTinjau === 'TANPA_PERUBAHAN' ? 'SEMUA' : 'TANPA_PERUBAHAN'"
                                >
                                    <span class="pa-bal-pill-dot is-unchanged" />
                                    <HcisIcon name="check2-circle" size="2xs" />
                                    <span class="pa-bal-pill-text"><strong>{{ jumlahTanpaPerubahan }}</strong> tanpa perubahan</span>
                                </button>

                                <template v-if="belumDitinjau > 0">
                                    <span class="pa-bal-pill-sep" aria-hidden="true" />
                                    <button
                                        type="button"
                                        class="pa-bal-pill is-pending"
                                        :class="{ 'is-active': filterTinjau === 'BELUM' }"
                                        title="Klik untuk filter: Peserta belum ditinjau"
                                        @click="filterTinjau = filterTinjau === 'BELUM' ? 'SEMUA' : 'BELUM'"
                                    >
                                        <span class="pa-bal-pill-dot is-pending" />
                                        <HcisIcon name="hourglass-split" size="2xs" />
                                        <span class="pa-bal-pill-text"><strong>{{ belumDitinjau }}</strong> belum ditinjau</span>
                                    </button>
                                </template>
                            </span>
                        </template>

                        <template #actions>
                            <div v-if="bisaUbah" class="pa-bal-undo-group" role="group" aria-label="Urungkan perubahan">
                                <button
                                    type="button"
                                    class="pa-bal-undo-btn"
                                    :disabled="!bisaUndo"
                                    title="Urungkan (Ctrl+Z)"
                                    aria-label="Urungkan"
                                    @click="undo"
                                >
                                    <HcisIcon name="arrow-counterclockwise" size="xs" />
                                    <span>Undo</span>
                                </button>
                                <button
                                    type="button"
                                    class="pa-bal-undo-btn"
                                    :disabled="!bisaRedo"
                                    title="Ulangi (Ctrl+Y / Ctrl+Shift+Z)"
                                    aria-label="Ulangi"
                                    @click="redo"
                                >
                                    <HcisIcon name="arrow-clockwise" size="xs" />
                                    <span>Redo</span>
                                </button>
                            </div>
                            <div class="pa-bal-view-switch-group" role="group" aria-label="Mode Tampilan Peserta">
                                <button
                                    type="button"
                                    class="pa-bal-view-switch-btn"
                                    :class="{ 'is-active': modeTampilanPeserta === 'matriks' }"
                                    title="Tampilan Matriks (lembar rapat)"
                                    @click="modeTampilanPeserta = 'matriks'"
                                >
                                    <HcisIcon name="grid-3x3" size="2xs" />
                                    <span>Matriks</span>
                                </button>
                                <button
                                    type="button"
                                    class="pa-bal-view-switch-btn"
                                    :class="{ 'is-active': modeTampilanPeserta === 'table' }"
                                    title="Tampilan Tabel (Ringkas)"
                                    @click="modeTampilanPeserta = 'table'"
                                >
                                    <HcisIcon name="table" size="2xs" />
                                    <span>Tabel</span>
                                </button>
                                <button
                                    type="button"
                                    class="pa-bal-view-switch-btn"
                                    :class="{ 'is-active': modeTampilanPeserta === 'card' }"
                                    title="Tampilan Kartu (Detail)"
                                    @click="modeTampilanPeserta = 'card'"
                                >
                                    <HcisIcon name="grid-fill" size="2xs" />
                                    <span>Kartu</span>
                                </button>
                            </div>
                        </template>

                        <!-- Mode 0: Matriks — lembar rapat: kolom per orang, baris per parameter -->
                        <PaBalancingMatriks
                            v-if="modeTampilanPeserta === 'matriks'"
                            :peserta="pesertaTersaring"
                            :bisa-ubah="bisaUbah"
                            @ubah="tandaiUbah"
                        />

                        <!-- Mode 1: Table View (Clean, High-density summary) -->
                        <div v-else-if="modeTampilanPeserta === 'table'" class="pa-bal-table-container">
                            <table class="pa-bal-table">
                                <thead>
                                    <tr>
                                        <th scope="col" style="min-width: 200px;">Peserta</th>
                                        <th scope="col" style="width: 140px;">Level & Divisi</th>
                                        <th scope="col" style="text-align: center; width: 85px;">KPI</th>
                                        <th scope="col" style="text-align: center; width: 95px;">Kompetensi</th>
                                        <th scope="col" style="text-align: center; width: 85px;">Final</th>
                                        <th scope="col" style="text-align: center; width: 75px;">Grade</th>
                                        <th scope="col" style="width: 130px;">Status Tinjau</th>
                                        <th scope="col" style="text-align: right; width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="p in pesertaTersaring"
                                        :key="p.participant_id"
                                        class="pa-bal-table-row"
                                        @click="bukaEditPeserta(p.participant_id)"
                                    >
                                        <td>
                                            <div class="pa-bal-table-pic">
                                                <HcisAvatar :name="p.nama" size="xs" shape="rounded" />
                                                <div class="pa-bal-table-title-box">
                                                    <strong class="pa-bal-table-name">{{ p.nama }}</strong>
                                                    <small class="text-muted">{{ p.kode_karyawan }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-muted text-xs">{{ p.level || '–' }} &bull; {{ p.divisi || '–' }}</span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="pa-bal-table-score">{{ formatNilai(p.sebelum?.nilai_kpi) }}</span>
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="pa-bal-table-score-wrap">
                                                <span :class="{ 'pa-bal-score-crossed': adaPerubahan(p) }">
                                                    {{ formatNilai(p.sebelum?.nilai_kompetensi) }}
                                                </span>
                                                <template v-if="adaPerubahan(p)">
                                                    <HcisIcon name="arrow-right" size="2xs" class="text-brand mx-1" />
                                                    <strong class="text-brand">{{ formatNilai(hitungKompSesudah(p)) }}</strong>
                                                    <HcisBadge
                                                        v-if="hitungDelta(p.sebelum?.nilai_kompetensi, hitungKompSesudah(p))"
                                                        :label="hitungDelta(p.sebelum?.nilai_kompetensi, hitungKompSesudah(p))"
                                                        :variant="varianDelta(p.sebelum?.nilai_kompetensi, hitungKompSesudah(p))"
                                                        size="xs"
                                                        appearance="subtle"
                                                        class="ms-1"
                                                    />
                                                </template>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <strong class="pa-bal-table-score-final">{{ formatNilai(p.sebelum?.final) }}</strong>
                                        </td>
                                        <td style="text-align: center;">
                                            <HcisBadge :label="labelGrade(p.sebelum?.grade)" variant="subtle" sentiment="brand" size="xs" />
                                        </td>
                                        <td>
                                            <HcisBadge
                                                :label="LABEL_TINJAU[statusPeserta(p)] || statusPeserta(p)"
                                                :variant="statusPeserta(p) === 'BELUM' ? 'warning' : statusPeserta(p) === 'DIUBAH' ? 'brand' : 'success'"
                                                appearance="subtle"
                                                size="xs"
                                                dot
                                            />
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <div class="pa-bal-table-row-actions">
                                                <HcisButton
                                                    v-if="bisaUbah && !adaPerubahan(p) && p.status_tinjau !== 'TANPA_PERUBAHAN'"
                                                    label="Setujui"
                                                    variant="glass"
                                                    size="xs"
                                                    leading-icon="check-lg"
                                                    title="Tandai tanpa perubahan"
                                                    @click.stop="p.status_tinjau = 'TANPA_PERUBAHAN'; tandaiUbah();"
                                                />
                                                <HcisButton
                                                    label="Sesuaikan"
                                                    variant="ghost"
                                                    size="xs"
                                                    trailing-icon="pencil"
                                                    @click.stop="bukaEditPeserta(p.participant_id)"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mode 2: Card View (Default, with inline accordion) -->
                        <div v-else class="pa-bal-peserta-cards-list">
                            <PaBalancingPesertaKartu
                                v-for="p in pesertaTersaring"
                                :key="p.participant_id"
                                :peserta="p"
                                :bisa-ubah="bisaUbah"
                                :terbuka="apakahTerbuka(p.participant_id)"
                                @toggle="togglePeserta(p.participant_id)"
                                @ubah="tandaiUbah"
                            />
                        </div>
                    </HcisCard>

                    <!-- Catatan Rapat Card (Design System HcisCard & HcisRichText) -->
                    <HcisCard
                        variant="glass"
                        sentiment="neutral"
                        title="Catatan Rapat Komite Balancing"
                        description="Notula & keputusan resmi forum penilai"
                        icon="journal-text"
                        class="pa-bal-board__catatan-card"
                    >
                        <div class="pa-bal-catatan__body">
                            <HcisRichText
                                v-if="bisaUbah"
                                id="pa-bal-catatan"
                                label="Catatan rapat"
                                placeholder="Tuliskan notula, ringkasan argumen kesepakatan komite, atau kebijakan penyesuaian..."
                                :model-value="catatan"
                                @update:model-value="(v) => { catatan = v; tandaiUbah(); }"
                            />
                            <!-- eslint-disable-next-line vue/no-v-html -- disaring server (PaTeksKaya) dan htmlAman. -->
                            <div v-else-if="sesi.catatan_rapat" class="pa-bal-catatan__view" v-html="htmlAman(sesi.catatan_rapat)" />
                            <div v-else class="pa-bal-catatan__kosong">
                                <em>Tidak ada catatan rapat yang disimpan untuk sesi ini.</em>
                            </div>
                        </div>
                    </HcisCard>
                </div>
            </div>
        </section>

        <!-- Finalize Dialog (Design System HcisDialog) -->
        <HcisDialog v-model="dialogFinal" title="Finalkan sesi?" size="sm" sentiment="success">
            <p>
                Isian sesi dikunci dan tidak bisa diubah lagi. Koreksi sesudahnya lewat sesi baru.
                Hasil baru berlaku sesudah hitung ulang Balancing di halaman daftar.
            </p>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="dialogFinal = false" />
                <HcisButton label="Ya, finalkan" variant="success" size="sm" :loading="menyimpan" @click="finalkan" />
            </template>
        </HcisDialog>

        <!-- Cancel Session Dialog (Design System HcisDialog & HcisTextarea) -->
        <HcisDialog v-model="dialogBatal" title="Batalkan sesi?" size="sm" sentiment="danger">
            <p class="text-sm text-muted mb-2">
                Membatalkan sesi akan membuang draf penyesuaian skor dan mengembalikan status kandidat ke siklus awal.
            </p>
            <HcisTextarea
                id="pa-bal-alasan-batal"
                v-model="alasanBatal"
                label="Alasan membatalkan"
                placeholder="Tuliskan alasan pembatalan sesi (minimal 5 karakter)..."
                :rows="3"
                :maxlength="500"
            />
            <template #footer>
                <HcisButton label="Tutup" variant="secondary" size="sm" @click="dialogBatal = false" />
                <HcisButton
                    label="Batalkan sesi"
                    variant="danger"
                    size="sm"
                    :disabled="alasanBatal.trim().length < 5"
                    :loading="menyimpan"
                    @click="batalkan"
                />
            </template>
        </HcisDialog>

        <!-- Forum Committee Roster Dialog (Design System HcisDialog) -->
        <HcisDialog
            v-if="sesi"
            v-model="dialogForum"
            title="Daftar Komite Forum Balancing"
            kicker="TIM EVALUATOR & PIC"
            size="sm"
            sentiment="brand"
        >
            <div class="pa-bal-forum-dialog-list">
                <!-- PIC Item -->
                <div class="pa-bal-forum-dialog-item is-pic">
                    <HcisAvatar :name="sesi.pic_nama || 'PIC'" size="sm" shape="rounded" />
                    <div class="pa-bal-forum-dialog-info">
                        <strong>{{ sesi.pic_nama || 'PIC' }}</strong>
                        <small class="text-brand">Pimpinan / PIC Forum Sesi</small>
                    </div>
                    <HcisBadge label="PIC Forum" variant="brand" size="xs" appearance="subtle" />
                </div>

                <div v-if="sesi.forum.length" class="pa-bal-forum-dialog-divider">
                    <span>Anggota Komite Evaluasi</span>
                </div>

                <!-- Forum Members Items -->
                <div
                    v-for="f in sesi.forum"
                    :key="f.kode_karyawan"
                    class="pa-bal-forum-dialog-item"
                >
                    <HcisAvatar :name="f.nama" size="sm" shape="rounded" />
                    <div class="pa-bal-forum-dialog-info">
                        <strong>{{ f.nama }}</strong>
                        <small class="text-muted">{{ f.peran || 'Anggota Forum' }} &bull; {{ f.kode_karyawan }}</small>
                    </div>
                    <HcisBadge :label="f.peran || 'Anggota'" variant="neutral" size="xs" appearance="subtle" />
                </div>

                <div v-if="!sesi.forum.length" class="text-muted text-xs text-center py-2">
                    <em>Tidak ada anggota forum tambahan terdaftar pada sesi ini.</em>
                </div>
            </div>

            <template #footer>
                <HcisButton label="Tutup" variant="secondary" size="sm" @click="dialogForum = false" />
            </template>
        </HcisDialog>

        <!-- Simulation Modal Dialog -->
        <HcisDialog
            v-model="modalSimulasi"
            title="Simulasi Hasil Balancing"
            kicker="SIMULASI SEMENTARA · MEMORI"
            description="Simulasi dampak penyesuaian skor kompetensi terhadap nilai akhir, grade kurva grup, dan matriks 9-box HAV."
            size="2xl"
            sentiment="brand"
            icon="magic"
            header-divider
            sticky-footer
        >
            <template #badge>
                <HcisBadge
                    v-if="simulasi"
                    :label="simulasi.metode === 'KURVA' ? 'Metode Kurva' : 'Metode Pita'"
                    :variant="simulasi.metode === 'KURVA' ? 'brand' : 'neutral'"
                    size="xs"
                    appearance="subtle"
                />
            </template>

            <PaBalancingSimulasiPanel
                :hasil="simulasi"
                :kuadran="kuadran"
                :memuat="menyimulasikan"
                :basi="simulasiBasi"
                mode-modal
                @tutup="modalSimulasi = false"
                @simulasi-ulang="simulasikan"
            />

            <template #footer>
                <div class="pa-bal-sim-modal-footer">
                    <span class="pa-bal-sim-modal-footer-note">
                        <HcisIcon name="info-circle" size="2xs" />
                        <span>Hasil simulasi dihitung sementara di memori server dan belum disimpan permanen.</span>
                    </span>
                    <div class="pa-bal-sim-modal-footer-actions">
                        <HcisButton
                            v-if="simulasiBasi"
                            label="Simulasi Ulang"
                            variant="warning"
                            size="sm"
                            leading-icon="arrow-clockwise"
                            :loading="menyimulasikan"
                            @click="simulasikan"
                        />
                        <HcisButton
                            label="Tutup"
                            variant="secondary"
                            size="sm"
                            @click="modalSimulasi = false"
                        />
                    </div>
                </div>
            </template>
        </HcisDialog>

        <!-- Floating Glass Action Dock (Sticky Bottom Bar when draft is dirty) -->
        <Transition name="pa-bal-dock-fade">
            <aside v-if="bisaUbah && kotor" class="pa-bal-floating-dock" :class="{ 'is-galat': adaGalat }" aria-label="Aksi Cepat Draf Balancing">
                <div class="pa-bal-dock-content">
                    <div class="pa-bal-dock-status">
                        <span class="pa-bal-dock-pulse" />
                        <div class="pa-bal-dock-texts">
                            <strong>Draf Penyesuaian Belum Disimpan</strong>
                            <small v-if="adaGalat" class="pa-bal-dock-galat"><HcisIcon name="exclamation-triangle-fill" size="2xs" /> Ada nilai di luar 0–100 atau alasan yang belum diisi.</small>
                            <small v-else class="text-muted">Simulasikan dampak penyesuaian atau simpan draf ke server.</small>
                        </div>
                    </div>

                    <div class="pa-bal-dock-actions">
                        <HcisButton
                            label="Simulasikan"
                            variant="secondary"
                            size="sm"
                            leading-icon="magic"
                            :loading="menyimulasikan"
                            :disabled="adaDiLuarRentang"
                            title="Simulasikan dampak penyesuaian skor"
                            @click="simulasikan"
                        />
                        <HcisButton
                            label="Simpan draft"
                            variant="primary"
                            size="sm"
                            leading-icon="save"
                            :loading="menyimpan"
                            :disabled="adaGalat || !kotor"
                            title="Simpan draf sementara sesi balancing"
                            @click="simpan"
                        />
                    </div>
                </div>
            </aside>
        </Transition>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDialog,
    HcisEmptyState,
    HcisFilterBar,
    HcisHeader,
    HcisIcon,
    HcisProgress,
    HcisResultState,
    HcisRichText,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisTextarea,
} from '@/components/ui';
import paApi from '../api/paApi.js';
import { htmlAman } from '../utils/htmlAman.js';
import PaBalancingMatriks from './components/PaBalancingMatriks.vue';
import PaBalancingPesertaKartu from './components/PaBalancingPesertaKartu.vue';
import PaBalancingSimulasiPanel from './components/PaBalancingSimulasiPanel.vue';
import {
    LABEL_STATUS,
    LABEL_TINJAU,
    VARIAN_STATUS,
    adaPerubahan,
    diLuarRentang,
    formatNilai,
    galatPeserta,
    hitungDelta,
    hitungKompSesudah,
    labelGrade,
    payloadSimpan,
    payloadSimulasi,
    potretSuntingan,
    salinPeserta,
    terapkanPotret,
    varianDelta,
} from './utils/balancing.js';

defineOptions({ name: 'PerformanceAppraisalBalancingSesiPage' });

const props = defineProps({ sesiId: { type: Number, required: true } });

const sesi = ref(null);
const peserta = ref([]);
const catatan = ref('');
const kuadran = ref([]);
const memuat = ref(true);
const galatMuat = ref('');
const galatAksi = ref('');
const versiBasi = ref(false);
const menyimpan = ref(false);
const menyimulasikan = ref(false);
const simulasi = ref(null);
const simulasiBasi = ref(false);
const modalSimulasi = ref(false);

// View mode, filter & Accordion states
const modeTampilanPeserta = ref('matriks');
const terbuka = ref(null);
const himpunanTerbuka = ref(new Set());
const modeSemuaTerbuka = ref(false);
const kataKunciPeserta = ref('');
const filterTinjau = ref('SEMUA');

function statusPeserta(p) {
    if (adaPerubahan(p)) return 'DIUBAH';
    if (p.status_tinjau === 'TANPA_PERUBAHAN') return 'TANPA_PERUBAHAN';
    return 'BELUM';
}

function bukaEditPeserta(id) {
    modeTampilanPeserta.value = 'card';
    himpunanTerbuka.value.add(id);
    terbuka.value = id;
}

const kotor = ref(false);
const dialogFinal = ref(false);
const dialogBatal = ref(false);
const dialogForum = ref(false);
const alasanBatal = ref('');

const bisaUbah = computed(() => ['TERBUKA', 'DRAFT'].includes(sesi.value?.status));
const adaGalat = computed(() => peserta.value.some((p) => galatPeserta(p).length > 0));
const adaDiLuarRentang = computed(() => peserta.value.some((p) => (p.kompetensi || []).some(diLuarRentang)));
const belumDitinjau = computed(() => peserta.value.filter((p) => !adaPerubahan(p) && p.status_tinjau !== 'TANPA_PERUBAHAN').length);

const jumlahDiubah = computed(() => peserta.value.filter((p) => adaPerubahan(p)).length);
const jumlahTanpaPerubahan = computed(() => peserta.value.filter((p) => !adaPerubahan(p) && p.status_tinjau === 'TANPA_PERUBAHAN').length);

const persenTinjau = computed(() => {
    if (!peserta.value.length) return 0;
    const sudah = peserta.value.length - belumDitinjau.value;
    return Math.round((sudah / peserta.value.length) * 100);
});

const semuaTerbuka = computed(() => modeSemuaTerbuka.value);

const pesertaFilterChips = computed(() => [
    { id: 'semua', label: 'Semua', value: 'SEMUA', badge: peserta.value.length },
    { id: 'belum', label: 'Belum Ditinjau', value: 'BELUM', badge: belumDitinjau.value, color: belumDitinjau.value > 0 ? 'amber' : 'neutral' },
    { id: 'diubah', label: 'Diubah', value: 'DIUBAH', badge: jumlahDiubah.value, color: 'blue' },
    { id: 'tanpa_perubahan', label: 'Tanpa Perubahan', value: 'TANPA_PERUBAHAN', badge: jumlahTanpaPerubahan.value, color: 'emerald' },
]);

const pesertaTersaring = computed(() => {
    let hasil = peserta.value || [];

    if (filterTinjau.value === 'BELUM') {
        hasil = hasil.filter((p) => !adaPerubahan(p) && p.status_tinjau !== 'TANPA_PERUBAHAN');
    } else if (filterTinjau.value === 'DIUBAH') {
        hasil = hasil.filter((p) => adaPerubahan(p));
    } else if (filterTinjau.value === 'TANPA_PERUBAHAN') {
        hasil = hasil.filter((p) => !adaPerubahan(p) && p.status_tinjau === 'TANPA_PERUBAHAN');
    }

    if (kataKunciPeserta.value.trim()) {
        const cari = kataKunciPeserta.value.toLowerCase().trim();
        hasil = hasil.filter((p) => (p.nama || '').toLowerCase().includes(cari) || (p.kode_karyawan || '').toLowerCase().includes(cari));
    }

    return hasil;
});

function apakahTerbuka(id) {
    if (modeSemuaTerbuka.value) return true;
    if (terbuka.value === id) return true;
    return himpunanTerbuka.value.has(id);
}

function togglePeserta(id) {
    if (modeSemuaTerbuka.value) {
        modeSemuaTerbuka.value = false;
        himpunanTerbuka.value = new Set(peserta.value.map((p) => p.participant_id));
    }

    if (himpunanTerbuka.value.has(id)) {
        himpunanTerbuka.value.delete(id);
        if (terbuka.value === id) terbuka.value = null;
    } else {
        himpunanTerbuka.value.add(id);
        terbuka.value = id;
    }
}

function toggleBukaSemua() {
    if (modeSemuaTerbuka.value) {
        modeSemuaTerbuka.value = false;
        himpunanTerbuka.value.clear();
        terbuka.value = null;
    } else {
        modeSemuaTerbuka.value = true;
        himpunanTerbuka.value = new Set(peserta.value.map((p) => p.participant_id));
    }
}

function resetFilterPeserta() {
    kataKunciPeserta.value = '';
    filterTinjau.value = 'SEMUA';
}

function tandaiSisaTanpaPerubahan() {
    let adaYangDitandai = false;
    for (const p of peserta.value) {
        if (!adaPerubahan(p) && p.status_tinjau !== 'TANPA_PERUBAHAN') {
            p.status_tinjau = 'TANPA_PERUBAHAN';
            adaYangDitandai = true;
        }
    }
    if (adaYangDitandai) {
        tandaiUbah();
    }
}

/* Undo/redo: setiap suntingan peserta menyimpan potret keadaan sebelumnya. Ketikan beruntun
   (mis. alasan) dalam 700 ms digabung jadi satu langkah supaya undo tidak per huruf. */
const tumpukanUndo = ref([]);
const tumpukanRedo = ref([]);
let potretKini = '[]';
let potretTersimpan = '[]';
let waktuUbahTerakhir = 0;
const BATAS_RIWAYAT = 100;
const bisaUndo = computed(() => tumpukanUndo.value.length > 0);
const bisaRedo = computed(() => tumpukanRedo.value.length > 0);

function mulaiRiwayat() {
    potretKini = potretSuntingan(peserta.value);
    potretTersimpan = potretKini;
    tumpukanUndo.value = [];
    tumpukanRedo.value = [];
    waktuUbahTerakhir = 0;
}

function catatRiwayat() {
    const baru = potretSuntingan(peserta.value);
    if (baru === potretKini) return;
    const sekarang = Date.now();
    if (sekarang - waktuUbahTerakhir > 700 || !tumpukanUndo.value.length) {
        tumpukanUndo.value.push(potretKini);
        if (tumpukanUndo.value.length > BATAS_RIWAYAT) tumpukanUndo.value.shift();
    }
    waktuUbahTerakhir = sekarang;
    potretKini = baru;
    tumpukanRedo.value = [];
}

function pulihkan(potret) {
    terapkanPotret(peserta.value, potret);
    potretKini = potret;
    waktuUbahTerakhir = 0;
    kotor.value = potret !== potretTersimpan || catatan.value !== (sesi.value?.catatan_rapat || '');
    if (simulasi.value) simulasiBasi.value = true;
}

function undo() {
    if (!bisaUndo.value) return;
    tumpukanRedo.value.push(potretKini);
    pulihkan(tumpukanUndo.value.pop());
}

function redo() {
    if (!bisaRedo.value) return;
    tumpukanUndo.value.push(potretKini);
    pulihkan(tumpukanRedo.value.pop());
}

// Pintasan papan ketik — tidak dipakai saat kursor di kolom isian, supaya undo bawaan isian tetap jalan.
function pintasanUndo(e) {
    if (!bisaUbah.value || !(e.ctrlKey || e.metaKey)) return;
    const el = e.target;
    if (el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName))) return;
    const tombol = e.key.toLowerCase();
    if (tombol === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
    else if (tombol === 'y' || (tombol === 'z' && e.shiftKey)) { e.preventDefault(); redo(); }
}

function tandaiUbah() {
    kotor.value = true;
    if (simulasi.value) simulasiBasi.value = true;
    catatRiwayat();
}

const PESAN_KELUAR = 'Ada isian yang belum disimpan. Tinggalkan halaman ini dan buang isian itu?';

function bolehKeluar() {
    return !kotor.value || window.confirm(PESAN_KELUAR);
}

function jagaTutupTab(e) {
    if (!kotor.value) return;
    e.preventDefault();
    e.returnValue = PESAN_KELUAR;
}

let lepasJagaNavigasi = () => {};

function terapkan(d) {
    sesi.value = d;
    peserta.value = (d.peserta || []).map(salinPeserta);
    catatan.value = d.catatan_rapat || '';
    kotor.value = false;
    mulaiRiwayat();
}

async function muat() {
    memuat.value = true;
    galatMuat.value = '';
    galatAksi.value = '';
    versiBasi.value = false;
    try {
        terapkan(await paApi.detailSesiBalancing(props.sesiId));
        const hav = await paApi.havMap(sesi.value.cycle_id).catch(() => null);
        kuadran.value = hav?.kuadran || [];
    } catch (e) {
        galatMuat.value = e?.message || 'Gagal memuat sesi.';
    } finally {
        memuat.value = false;
    }
}

function tanganiGalat(e) {
    galatAksi.value = e?.message || 'Permintaan ditolak.';
    versiBasi.value = e?.error_code === 'PA_BAL_VERSI_BERUBAH';
}

async function simpan() {
    menyimpan.value = true;
    galatAksi.value = '';
    try {
        terapkan(await paApi.simpanSesiBalancing(props.sesiId, {
            versi_data: sesi.value.versi_data,
            peserta: payloadSimpan(peserta.value),
            catatan_rapat: catatan.value || null,
        }));
    } catch (e) {
        tanganiGalat(e);
    } finally {
        menyimpan.value = false;
    }
}

async function simulasikan() {
    modalSimulasi.value = true;
    menyimulasikan.value = true;
    galatAksi.value = '';
    try {
        simulasi.value = await paApi.simulasiBalancing(props.sesiId, payloadSimulasi(peserta.value));
        simulasiBasi.value = false;
    } catch (e) {
        tanganiGalat(e);
    } finally {
        menyimulasikan.value = false;
    }
}

async function finalkan() {
    menyimpan.value = true;
    try {
        terapkan(await paApi.finalkanSesiBalancing(props.sesiId, sesi.value.versi_data));
        dialogFinal.value = false;
    } catch (e) {
        dialogFinal.value = false;
        tanganiGalat(e);
    } finally {
        menyimpan.value = false;
    }
}

async function batalkan() {
    menyimpan.value = true;
    try {
        terapkan(await paApi.batalkanSesiBalancing(props.sesiId, alasanBatal.value.trim()));
        dialogBatal.value = false;
    } catch (e) {
        dialogBatal.value = false;
        tanganiGalat(e);
    } finally {
        menyimpan.value = false;
    }
}

function kembali() {
    if (!bolehKeluar()) return;
    kotor.value = false;
    router.visit(`/performance-appraisal/balancing${sesi.value ? `?cycle=${sesi.value.cycle_id}` : ''}`);
}

onMounted(() => {
    muat();
    window.addEventListener('beforeunload', jagaTutupTab);
    window.addEventListener('keydown', pintasanUndo);
    lepasJagaNavigasi = router.on('before', () => bolehKeluar()) || (() => {});
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', jagaTutupTab);
    window.removeEventListener('keydown', pintasanUndo);
    lepasJagaNavigasi();
});
</script>

<style scoped>
.pa-bal-board-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 0;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: hidden;
}

.pa-bal-header {
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-bal-header-badges {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-bal-board-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-bal-board-content {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    width: 100%;
    min-width: 0;
}

.pa-bal-board__info {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #92400e;
    background: rgba(254, 243, 199, 0.7);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(253, 230, 138, 0.85);
    border-radius: var(--hcis-radius-md, 10px);
    padding: 0.65rem 1rem;
    margin: 0;
}

/* Progress Bento Card */
.pa-bal-progress-card {
    border-radius: var(--hcis-radius-xl, 16px);
    overflow: hidden;
}

.pa-bal-progress-body {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Compact Forum Committee Summary Pill */
.pa-bal-forum-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.35rem 0.65rem;
    border-radius: 9999px;
    background: rgba(241, 245, 249, 0.85);
    border: 1px solid rgba(226, 232, 240, 0.9);
    cursor: pointer;
    transition: all 150ms ease;
    text-align: left;
}

.pa-bal-forum-pill-btn:hover {
    background: #ffffff;
    border-color: rgba(79, 70, 229, 0.35);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
}

.pa-bal-forum-pill-copy {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
}

.pa-bal-forum-pill-pic {
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-forum-pill-pic strong {
    font-weight: 700;
}

.pa-bal-forum-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
    background: rgba(79, 70, 229, 0.08);
    color: var(--hcis-brand-600, #4f46e5);
    font-weight: 600;
    font-size: 0.6875rem;
}

.pa-bal-forum-pill-chevron {
    color: #94a3b8;
    transition: transform 150ms ease;
}

.pa-bal-forum-pill-btn:hover .pa-bal-forum-pill-chevron {
    color: var(--hcis-brand-600, #4f46e5);
    transform: translateX(2px);
}

/* Forum Dialog List */
.pa-bal-forum-dialog-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-bal-forum-dialog-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-bal-forum-dialog-item.is-pic {
    background: rgba(238, 242, 255, 0.85);
    border-color: rgba(199, 210, 254, 0.85);
}

.pa-bal-forum-dialog-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.pa-bal-forum-dialog-info strong {
    font-size: 0.8125rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-forum-dialog-info small {
    font-size: 0.6875rem;
}

.pa-bal-forum-dialog-divider {
    display: flex;
    align-items: center;
    margin: 0.35rem 0;
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
}

/* Filter Bar */
.pa-bal-peserta-filterbar {
    border-radius: var(--hcis-radius-xl, 16px);
}

/* Main Layout */
.pa-bal-board__tata {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1.25rem;
}

.pa-bal-board__daftar {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    min-width: 0;
}

/* Participants Container Card (Glass with etched divider & edge-to-edge table) */
.pa-bal-peserta-card-container {
    overflow: hidden;
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-bal-peserta-card-container :deep(.hcis-card__body) {
    padding: 0;
}

/* Header Pills / Status Ribbon */
.pa-bal-header-pills {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
    margin-top: 0.2rem;
}

.pa-bal-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.22rem 0.6rem;
    border-radius: var(--hcis-radius-full, 9999px);
    font-size: 0.725rem;
    font-weight: 500;
    line-height: 1.2;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 180ms cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
}

.pa-bal-pill:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}

.pa-bal-pill-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.pa-bal-pill-text {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

/* Adjusted (Disesuaikan) Theme */
.pa-bal-pill.is-adjusted {
    background: rgba(79, 70, 229, 0.08);
    border-color: rgba(79, 70, 229, 0.22);
    color: var(--hcis-brand-700, #4338ca);
}

.pa-bal-pill.is-adjusted:hover,
.pa-bal-pill.is-adjusted.is-active {
    background: rgba(79, 70, 229, 0.14);
    border-color: rgba(79, 70, 229, 0.38);
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.16);
}

.pa-bal-pill-dot.is-adjusted {
    background: var(--hcis-brand-500, #6366f1);
    box-shadow: 0 0 6px rgba(99, 102, 241, 0.6);
}

.pa-bal-pill.is-adjusted strong {
    color: var(--hcis-brand-800, #3730a3);
    font-weight: 700;
}

/* Unchanged (Tanpa Perubahan) Theme */
.pa-bal-pill.is-unchanged {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.22);
    color: #047857;
}

.pa-bal-pill.is-unchanged:hover,
.pa-bal-pill.is-unchanged.is-active {
    background: rgba(16, 185, 129, 0.14);
    border-color: rgba(16, 185, 129, 0.38);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.16);
}

.pa-bal-pill-dot.is-unchanged {
    background: #10b981;
    box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);
}

.pa-bal-pill.is-unchanged strong {
    color: #065f46;
    font-weight: 700;
}

/* Pending (Belum Ditinjau) Theme */
.pa-bal-pill.is-pending {
    background: rgba(245, 158, 11, 0.08);
    border-color: rgba(245, 158, 11, 0.22);
    color: #b45309;
}

.pa-bal-pill.is-pending:hover,
.pa-bal-pill.is-pending.is-active {
    background: rgba(245, 158, 11, 0.14);
    border-color: rgba(245, 158, 11, 0.38);
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.16);
}

.pa-bal-pill-dot.is-pending {
    background: #f59e0b;
    box-shadow: 0 0 6px rgba(245, 158, 11, 0.6);
}

.pa-bal-pill.is-pending strong {
    color: #92400e;
    font-weight: 700;
}

/* Subtle separator between status pills */
.pa-bal-pill-sep {
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: rgba(148, 163, 184, 0.55);
}

/* View Switcher */
.pa-bal-view-switch-group {
    display: inline-flex;
    align-items: center;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 3px;
    gap: 3px;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
}

.pa-bal-view-switch-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.32rem 0.65rem;
    border: 0;
    background: transparent;
    border-radius: var(--hcis-radius-md, 7px);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-muted, #64748b);
    cursor: pointer;
    transition: all 150ms ease;
    line-height: 1;
}

.pa-bal-view-switch-btn:hover {
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-view-switch-btn.is-active {
    background: #ffffff;
    color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.09), 0 1px 2px rgba(15, 23, 42, 0.05);
    font-weight: 700;
}

/* Participants Table View Styles */
.pa-bal-table-container {
    overflow-x: auto;
    width: 100%;
}

.pa-bal-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
    text-align: left;
}

.pa-bal-table th {
    background: rgba(248, 250, 252, 0.9);
    color: var(--hcis-text-muted, #64748b);
    font-weight: 700;
    font-size: 0.6875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
    white-space: nowrap;
}

.pa-bal-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.9);
    vertical-align: middle;
}

.pa-bal-table-row {
    cursor: pointer;
    transition: background-color 150ms ease;
}

.pa-bal-table-row:hover {
    background-color: rgba(248, 250, 252, 0.8);
}

.pa-bal-table-row:last-child td {
    border-bottom: 0;
}

.pa-bal-table-pic {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-bal-table-title-box {
    display: flex;
    flex-direction: column;
}

.pa-bal-table-name {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-table-score {
    font-size: 0.8125rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-table-score-final {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-bal-peserta-cards-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
}

/* Simulation Dialog Footer */
.pa-bal-sim-modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
}

.pa-bal-sim-modal-footer-note {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-sim-modal-footer-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Catatan Rapat */
.pa-bal-board__catatan-card {
    border-radius: var(--hcis-radius-xl, 16px);
}

.pa-bal-catatan__body {
    min-width: 0;
    margin-top: 0.5rem;
}

.pa-bal-catatan__view {
    font-size: 0.8125rem;
    line-height: 1.6;
    color: var(--hcis-text-strong, #0f172a);
    padding: 0.75rem 1rem;
    background: rgba(248, 250, 252, 0.8);
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(226, 232, 240, 0.85);
}

.pa-bal-catatan__kosong {
    font-size: 0.8125rem;
    color: var(--hcis-text-subtle, #94a3b8);
    padding: 0.85rem;
    text-align: center;
}

/* Table Score & Action Helpers */
.pa-bal-table-score-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    font-size: 0.8125rem;
}

.pa-bal-score-crossed {
    text-decoration: line-through;
    color: #94a3b8;
    font-size: 0.75rem;
}

.pa-bal-table-row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.45rem;
}

/* Floating Glass Action Dock (Sticky Bottom) */
.pa-bal-floating-dock {
    position: fixed;
    bottom: 1.25rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 100;
    width: calc(100% - 2.5rem);
    max-width: 860px;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid #cbd5e1;
    border-radius: var(--hcis-radius-xl, 16px);
    box-shadow: 0 16px 36px -8px rgba(15, 23, 42, 0.18), 0 4px 12px rgba(15, 23, 42, 0.06);
    padding: 0.75rem 1.25rem;
}

.pa-bal-floating-dock.is-galat {
    border-color: #fca5a5;
}

.pa-bal-floating-dock.is-galat .pa-bal-dock-pulse {
    background: #ef4444;
}

.pa-bal-dock-galat {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: #dc2626;
    font-weight: 600;
}

.pa-bal-dock-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-bal-dock-status {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.pa-bal-dock-pulse {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #f59e0b;
    box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
    animation: pa-pulse 1.8s infinite;
    flex-shrink: 0;
}

@keyframes pa-pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
    }
    70% {
        box-shadow: 0 0 0 8px rgba(245, 158, 11, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
    }
}

.pa-bal-dock-texts {
    display: flex;
    flex-direction: column;
}

.pa-bal-dock-texts strong {
    font-size: 0.8125rem;
    color: #0f172a;
}

.pa-bal-dock-texts small {
    font-size: 0.6875rem;
}

.pa-bal-dock-texts small.text-muted {
    color: #64748b !important;
}

.pa-bal-dock-actions {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-left: auto;
}

.pa-bal-dock-fade-enter-active,
.pa-bal-dock-fade-leave-active {
    transition: all 0.25s ease;
}

.pa-bal-dock-fade-enter-from,
.pa-bal-dock-fade-leave-to {
    opacity: 0;
    transform: translate(-50%, 20px);
}



@media (max-width: 768px) {
    .pa-bal-board-page {
        padding: 0;
        gap: 1rem;
    }

    .pa-bal-board-actions {
        width: 100%;
        justify-content: space-between;
    }

    .pa-bal-floating-dock {
        width: calc(100% - 1rem);
        bottom: 0.75rem;
        padding: 0.65rem 0.85rem;
    }

    .pa-bal-dock-content {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .pa-bal-dock-actions {
        width: 100%;
        justify-content: flex-end;
    }
}

/* Undo / redo */
.pa-bal-undo-group { display: inline-flex; align-items: center; gap: 2px; padding: 2px; margin-right: 0.5rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
.pa-bal-undo-btn { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.3rem 0.6rem; border: 0; border-radius: 8px; background: transparent; color: #334155; font-size: 0.76rem; font-weight: 600; cursor: pointer; }
.pa-bal-undo-btn:hover:not(:disabled) { background: #fff; color: #4338ca; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08); }
.pa-bal-undo-btn:disabled { color: #cbd5e1; cursor: not-allowed; }
@media (max-width: 640px) { .pa-bal-undo-btn span { display: none; } }
</style>

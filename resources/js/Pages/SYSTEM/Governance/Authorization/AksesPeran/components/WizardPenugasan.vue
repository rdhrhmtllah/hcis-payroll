<template>
    <div class="ap-wizard-penugasan-root">
        <HcisDialog
            :model-value="modelValue"
            :kicker="headerKicker"
            :title="headerTitle"
            :description="headerDescription"
            :badge="headerBadge"
            :badge-variant="headerBadgeVariant"
            :icon="headerIcon"
            :sentiment="headerSentiment"
            variant="glass"
            size="2xl"
            header-divider
            @update:model-value="$emit('update:modelValue', $event)"
        >
            <!-- ══ HASIL PENUGASAN MASSAL (ada yang dilewati) ══ -->
            <div v-if="hasilMassal" class="ap-wz-step-body d-flex flex-column gap-3">
                <HcisAlert
                    :variant="hasilMassal.berhasil.length ? 'success' : 'warning'"
                    appearance="subtle"
                    :title="`${hasilMassal.berhasil.length} penugasan dibuat, ${hasilMassal.gagal.length} dilewati`"
                    description="Penugasan yang dilewati tidak mengubah apa pun. Periksa alasannya di bawah."
                />
                <HcisTable
                    :columns="[
                        { key: 'nama', label: 'Karyawan dilewati' },
                        { key: 'peran', label: 'Peran' },
                        { key: 'alasan', label: 'Alasan' },
                    ]"
                    :rows="hasilMassal.gagal.map((g) => ({ ...g, kunci: `${g.id_user}-${g.id_role}` }))"
                    row-key="kunci"
                    responsive="cards"
                    size="sm"
                    dense
                >
                    <template #cell-alasan="{ row }"
                        ><span class="small">{{ row.alasan }}</span></template
                    >
                </HcisTable>
            </div>

            <template v-else>
                <!-- ══ OFFICIAL HCIS STEPS COMPONENT (CLEAN DESIGN SYSTEM) ══ -->
                <div class="ap-wz-stepper-wrap mb-4">
                    <HcisSteps
                        :current="langkah"
                        :steps="daftarLangkah"
                        :sentiment="headerSentiment"
                        variant="default"
                        size="sm"
                        clickable
                        @step-click="(idx) => (langkah = idx)"
                    />
                </div>

                <!-- ══ 1. LANGKAH KARYAWAN ══ -->
                <div v-if="currentStepKey === 'karyawan'" class="ap-wz-step-body">
                    <!-- Context Banner saat peran sudah ditentukan dari Tab Peran -->
                    <div
                        v-if="modeAlur === 'role-first' && infoPeranTetap"
                        class="ap-wz-target-hero mb-3"
                        :class="
                            infoPeranTetap.jenis === 'POOL_APPROVAL'
                                ? 'ap-wz-target-hero--warning'
                                : 'ap-wz-target-hero--brand'
                        "
                    >
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div
                                class="ap-wz-target-icon flex-shrink-0"
                                :class="
                                    infoPeranTetap.jenis === 'POOL_APPROVAL'
                                        ? 'ap-wz-target-icon--warning'
                                        : 'ap-wz-target-icon--brand'
                                "
                            >
                                <HcisIcon
                                    :name="infoPeranTetap.jenis === 'POOL_APPROVAL' ? 'diagram-2' : 'shield-check'"
                                    size="md"
                                />
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <span class="ap-kicker">
                                    {{
                                        infoPeranTetap.jenis === 'POOL_APPROVAL'
                                            ? 'ALUR KERJA & APPROVAL POOL'
                                            : 'TARGET PERAN OTORISASI'
                                    }}
                                </span>
                                <h6 class="ap-wz-target-title text-truncate mb-0.5">{{ infoPeranTetap.nama }}</h6>
                                <div class="ap-wz-target-meta">
                                    <span class="font-monospace fw-semibold">{{ infoPeranTetap.kode }}</span>
                                    <span>·</span>
                                    <span>{{ infoPeranTetap.modul || 'Lintas Modul' }}</span>
                                </div>
                            </div>
                        </div>
                        <HcisBadge
                            :label="infoBadgePeran(infoPeranTetap).label"
                            :variant="infoBadgePeran(infoPeranTetap).variant"
                            appearance="subtle"
                            size="sm"
                            class="flex-shrink-0"
                        />
                    </div>

                    <!-- Official HcisFilterBar Component untuk konsistensi pencarian & saringan -->
                    <div class="mb-3">
                        <HcisFilterBar
                            :search="saring.cari"
                            search-placeholder="Cari nama atau NIK karyawan…"
                            :quick-chips="quickStatusChips"
                            :active-chip-value="saring.status"
                            :filters="hcisFilterDefinitions"
                            :filter-values="filterValues"
                            :collapsible="true"
                            variant="flat"
                            size="sm"
                            sentiment="brand"
                            @update:search="
                                (v) => {
                                    saring.cari = v;
                                    jadwalkanMuat();
                                }
                            "
                            @chip-click="onStatusChipClick"
                            @filter-change="onHcisFilterChange"
                            @reset="resetSaring"
                        />
                    </div>

                    <!-- Bilah hasil (sebelum ada yang dipilih) -->
                    <div v-if="!jumlahTerpilih" class="wz-bilah-pilih mb-2">
                        <span class="small text-muted">
                            <strong>{{ daftar.total }}</strong> karyawan sesuai saringan · centang untuk memilih
                        </span>
                        <HcisButton
                            v-if="daftar.total > 0"
                            variant="ghost"
                            size="xs"
                            leading-icon="check2-all"
                            :label="`Pilih semua ${Math.min(daftar.total, MAKS_MASSAL)} hasil`"
                            :loading="memilihSemua"
                            @click="pilihSemuaHasil"
                        />
                    </div>
                    <div v-if="jumlahTerpilih" class="wz-chip-terpilih mb-2">
                        <HcisBadge
                            v-for="k in chipTerpilih"
                            :key="k.id_user"
                            :label="k.nama"
                            variant="brand"
                            appearance="subtle"
                            size="xs"
                            removable
                            @remove="lepas(k.id_user)"
                        />
                        <span v-if="jumlahTerpilih > chipTerpilih.length" class="ap-kecil">
                            +{{ jumlahTerpilih - chipTerpilih.length }} lainnya
                        </span>
                    </div>
                    <HcisAlert
                        v-if="jumlahTerpilih >= MAKS_MASSAL"
                        variant="warning"
                        appearance="subtle"
                        :description="`Maksimal ${MAKS_MASSAL} karyawan per penugasan.`"
                        class="mb-2"
                    />

                    <!-- Tabel karyawan -->
                    <HcisTable
                        :columns="kolomKaryawan"
                        :rows="daftar.data"
                        row-key="id_user"
                        selectable
                        :selected-keys="kunciTerpilih"
                        :row-selectable="(r) => !r.memegang"
                        :loading="mencari"
                        responsive="cards"
                        variant="card"
                        sentiment="brand"
                        size="sm"
                        dense
                        sticky-header
                        class="wz-tabel"
                        empty-icon="person-x"
                        empty-title="Karyawan tidak ditemukan"
                        empty-description="Ubah kata kunci atau saringan."
                        @update:selected-keys="ubahPilihan"
                    >
                        <template #bulk-actions>
                            <HcisButton
                                v-if="!semuaHasilTerpilih"
                                variant="ghost"
                                size="xs"
                                leading-icon="check2-all"
                                :label="`Pilih semua ${Math.min(daftar.total, MAKS_MASSAL)} hasil`"
                                :loading="memilihSemua"
                                @click="pilihSemuaHasil"
                            />
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="x-lg"
                                label="Kosongkan"
                                @click="kosongkanPilihan"
                            />
                        </template>
                        <template #cell-nama="{ row }">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <HcisAvatar :name="row.nama" size="sm" />
                                <div class="min-w-0">
                                    <div class="ap-wz-karyawan-nama text-truncate">{{ row.nama }}</div>
                                    <div class="ap-wz-karyawan-sub text-muted font-monospace">
                                        {{ row.kode_karyawan || '—' }}
                                    </div>
                                </div>
                                <HcisBadge
                                    v-if="row.memegang"
                                    label="Sudah memegang"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="xs"
                                    class="flex-shrink-0"
                                />
                            </div>
                        </template>
                        <template #cell-unit="{ row }">
                            <div class="small text-truncate">{{ row.departemen || '—' }}</div>
                            <div class="ap-kecil text-truncate">{{ row.divisi || '' }}</div>
                        </template>
                        <template #cell-jabatan="{ row }">
                            <div class="small text-truncate">{{ row.jabatan || '—' }}</div>
                            <div class="ap-kecil text-truncate">{{ row.level || '' }}</div>
                        </template>
                        <template #cell-lokasi="{ row }">
                            <span class="small">{{ row.lokasi || '—' }}</span>
                        </template>
                    </HcisTable>
                    <div v-if="daftar.total > daftar.per_halaman" class="d-flex justify-content-end mt-2">
                        <HcisPagination
                            v-model="daftar.halaman"
                            :total="daftar.total"
                            :page-size="daftar.per_halaman"
                            layout="total, prev, pager, next"
                            size="sm"
                            @update:model-value="muat"
                        />
                    </div>
                </div>

                <!-- ══ 2. LANGKAH PERAN ══ -->
                <div v-else-if="currentStepKey === 'peran'" class="ap-wz-step-body">
                    <HcisSkeleton v-if="memuatAkses" :lines="6" />
                    <HcisAlert
                        v-else-if="akses && !akses.bisa_kelola"
                        variant="warning"
                        appearance="subtle"
                        title="Wewenang Tidak Mencukupi"
                        description="Akses karyawan ini hanya dapat diubah oleh tingkat wewenang di atas Anda pada modul ini."
                        class="mb-3"
                    />
                    <template v-else-if="akses">
                        <!-- Context User Banner -->
                        <div class="ap-wz-context-ribbon mb-3">
                            <div class="d-flex align-items-center gap-2.5 min-w-0">
                                <HcisAvatar :name="labelSasaran" size="md" />
                                <div class="min-w-0">
                                    <span class="ap-kicker">KARYAWAN TERPILIH</span>
                                    <div class="ap-wz-target-title text-truncate">{{ labelSasaran }}</div>
                                    <div class="ap-wz-target-meta">{{ subSasaran }}</div>
                                </div>
                            </div>
                            <HcisBadge
                                label="Tersedia untuk Ditugaskan"
                                variant="success"
                                appearance="subtle"
                                size="xs"
                            />
                        </div>

                        <HcisAlert
                            v-if="jumlahSasaran > 1"
                            variant="info"
                            appearance="subtle"
                            description="Karyawan yang sudah memegang peran pilihan, atau di luar wewenang Anda, akan dilewati dan dilaporkan setelah disimpan."
                            class="mb-2"
                        />
                        <!-- Official HcisFilterBar Component untuk pencarian & saringan peran -->
                        <div class="mb-3">
                            <HcisFilterBar
                                :search="cariPeran"
                                search-placeholder="Cari kode atau nama peran…"
                                :quick-chips="quickRoleChips"
                                :active-chip-value="filterKategoriPeran"
                                :filters="roleFilterDefinitions"
                                :filter-values="roleFilterValues"
                                :collapsible="true"
                                variant="flat"
                                size="sm"
                                sentiment="brand"
                                @update:search="(v) => (cariPeran = v)"
                                @chip-click="(c) => (filterKategoriPeran = c.value)"
                                @filter-change="onRoleFilterChange"
                                @reset="resetRoleFilter"
                            />
                        </div>

                        <HcisEmptyState
                            v-if="!kelompok.length"
                            icon="person-badge"
                            title="Tidak Ada Peran Ditemukan"
                            description="Peran tidak ditemukan atau sudah dipegang oleh karyawan terpilih."
                            size="sm"
                        >
                            <template #actions>
                                <HcisButton
                                    v-if="cariPeran || filterKategoriPeran !== 'ALL' || filterModulPeran"
                                    variant="ghost"
                                    size="xs"
                                    label="Reset Saringan Peran"
                                    leading-icon="arrow-counterclockwise"
                                    @click="resetRoleFilter"
                                />
                            </template>
                        </HcisEmptyState>

                        <div v-if="kelompok.length" class="wz-bilah-peran mb-3">
                            <span class="ap-kecil">
                                <template v-if="form.id_roles.length">
                                    <b>{{ form.id_roles.length }} peran dipilih.</b>
                                    Admin dan Pengguna boleh digabung; Super Admin hanya dengan sesama Super Admin.
                                </template>
                                <template v-else>
                                    Centang satu atau beberapa peran. Super Admin tidak digabung dengan peran lain.
                                </template>
                            </span>
                            <HcisButton
                                v-if="form.id_roles.length > (peranTerkunci ? 1 : 0)"
                                variant="ghost"
                                size="xs"
                                label="Kosongkan"
                                @click="form.id_roles = peranTerkunci ? [peranTerkunci] : []"
                            />
                        </div>

                        <!-- Katalog Peran Full-Width (Lega & Nyaman) -->
                        <div v-for="g in kelompok" :key="g.tingkat" class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <HcisBadge
                                        :label="infoTingkat[g.tingkat]?.label || g.tingkat"
                                        :variant="infoTingkat[g.tingkat]?.varian || 'neutral'"
                                        appearance="subtle"
                                        size="sm"
                                    />
                                    <span class="ap-card-subtitle">{{ artiTingkat[g.tingkat] }}</span>
                                </div>
                                <span class="ap-kecil text-muted">{{ g.peran.length }} peran</span>
                            </div>

                            <div class="wz-grid">
                                <HcisCard
                                    v-for="p in g.peran"
                                    :key="p.id"
                                    variant="glass"
                                    size="sm"
                                    interactive
                                    class="ap-role-card-item"
                                    :sentiment="
                                        p.jenis === 'POOL_APPROVAL'
                                            ? 'warning'
                                            : p.tingkat === 'SUPER_ADMIN'
                                              ? 'purple'
                                              : p.tingkat === 'ADMIN'
                                                ? 'brand'
                                                : 'neutral'
                                    "
                                    :class="{ 'is-selected': dipilih(p), 'is-disabled': !bisaDipilih(p) }"
                                    role="checkbox"
                                    :aria-checked="dipilih(p)"
                                    :aria-disabled="!bisaDipilih(p)"
                                    tabindex="0"
                                    @click="togglePeran(p)"
                                    @keydown.enter="togglePeran(p)"
                                >
                                    <div class="d-flex align-items-center gap-2.5 w-100">
                                        <!-- Icon Box Berdasarkan Kategori -->
                                        <div class="ap-role-icon flex-shrink-0" :class="infoBadgePeran(p).iconClass">
                                            <HcisIcon :name="infoBadgePeran(p).iconName" size="sm" />
                                        </div>

                                        <div class="min-w-0 flex-grow-1">
                                            <!-- Baris Atas: Nama Peran & Badge Status -->
                                            <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                                <span class="ap-peran-nama text-truncate" :title="p.nama">{{
                                                    p.nama
                                                }}</span>
                                                <HcisBadge
                                                    :label="infoBadgePeran(p).label"
                                                    :variant="infoBadgePeran(p).variant"
                                                    appearance="subtle"
                                                    size="xs"
                                                    class="flex-shrink-0"
                                                />
                                            </div>

                                            <!-- Baris Tengah: Kode Monospace & Modul -->
                                            <div class="ap-peran-meta-row">
                                                <span class="ap-peran-kode">{{ p.kode }}</span>
                                                <span v-if="p.modul" class="ap-peran-modul text-truncate"
                                                    >· {{ p.modul }}</span
                                                >
                                                <span v-if="p.lama" class="ap-peran-modul">· Peran lama</span>
                                            </div>
                                            <div v-if="!bisaDipilih(p)" class="wz-hint-terpisah">
                                                <HcisIcon name="info-circle" size="xs" />
                                                {{ alasanTakBisa(p) }}
                                            </div>
                                            <button
                                                type="button"
                                                class="wz-intip"
                                                :class="{ 'is-active': intip?.id === p.id }"
                                                :aria-expanded="intip?.id === p.id"
                                                @click.stop="intipPeran(p)"
                                                @keydown.enter.stop
                                            >
                                                <HcisIcon :name="intip?.id === p.id ? 'eye-fill' : 'eye'" size="xs" />
                                                <span>{{
                                                    intip?.id === p.id ? 'Sedang Ditinjau' : 'Lihat Isi Hak'
                                                }}</span>
                                            </button>
                                        </div>

                                        <!-- Selection Radio Dot Indicator -->
                                        <div class="flex-shrink-0 ps-1">
                                            <HcisIcon
                                                v-if="dipilih(p)"
                                                name="check-square-fill"
                                                size="sm"
                                                :color="
                                                    p.jenis === 'POOL_APPROVAL'
                                                        ? 'warning'
                                                        : p.tingkat === 'SUPER_ADMIN'
                                                          ? 'purple'
                                                          : 'brand'
                                                "
                                            />
                                            <span v-else class="ap-wz-radio-dot wz-kotak d-inline-block" />
                                        </div>
                                    </div>
                                </HcisCard>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ══ 3. LANGKAH DETAIL PENUGASAN ══ -->
                <div v-else-if="currentStepKey === 'detail'" class="ap-wz-step-body d-flex flex-column gap-3.5">
                    <!-- Context Combined Header (Karyawan + Peran) -->
                    <div class="ap-wz-context-ribbon">
                        <div class="d-flex align-items-center gap-2.5 min-w-0">
                            <HcisAvatar :name="labelSasaran || 'User'" size="md" />
                            <div class="min-w-0">
                                <span class="ap-kicker">PENUGASAN PERAN</span>
                                <div class="ap-wz-target-title text-truncate">{{ labelSasaran }}</div>
                                <div class="ap-wz-target-meta">
                                    Menjadi:
                                    <strong class="text-primary">{{
                                        daftarPeranTerpilih.length > 1
                                            ? `${daftarPeranTerpilih.length} peran`
                                            : peranTerpilih?.nama
                                    }}</strong>
                                    <span
                                        v-if="daftarPeranTerpilih.length === 1 && peranTerpilih?.kode"
                                        class="font-monospace"
                                    >
                                        ({{ peranTerpilih.kode }})</span
                                    >
                                </div>
                            </div>
                        </div>
                        <HcisBadge
                            :label="infoTingkat[peranTerpilih?.tingkat]?.label || peranTerpilih?.tingkat || 'Peran'"
                            :variant="infoTingkat[peranTerpilih?.tingkat]?.varian || 'brand'"
                            appearance="subtle"
                            size="sm"
                        />
                    </div>

                    <div v-if="daftarPeranTerpilih.length > 1" class="d-flex flex-wrap gap-1.5">
                        <HcisBadge
                            v-for="p in daftarPeranTerpilih"
                            :key="p.id"
                            :label="p.nama"
                            :variant="infoBadgePeran(p).variant"
                            appearance="subtle"
                            size="sm"
                        />
                    </div>

                    <!-- Cakupan Karyawan -->
                    <div class="ap-wz-form-section">
                        <span class="form-label small fw-semibold text-dark mb-1">Cakupan Data Karyawan (Scope)</span>
                        <div class="ap-card-subtitle mb-2">
                            <template v-if="setingkatAdmin">
                                Membatasi karyawan yang boleh dikelola administrator ini.
                            </template>
                            <template v-else>
                                Untuk peran Pengguna, cakupan dicatat tetapi belum membatasi data di halaman modul.
                            </template>
                        </div>
                        <PemilihCakupan
                            :key="`${form.id_roles.join(',')}-${akses?.id_user}`"
                            v-model="form.cakupan"
                            :akses="akses || {}"
                            :peran="daftarPeranTerpilih"
                            @valid="cakupanValid = $event"
                        />
                    </div>

                    <!-- Wewenang admin: otomatis (server memberi tanggung jawab Akses). -->
                    <HcisAlert
                        v-if="setingkatAdmin"
                        variant="info"
                        appearance="subtle"
                        icon="shield-check"
                        description="Administrator ini otomatis berwenang menugaskan peran dan memberi akses kepada karyawan dalam cakupan di atas."
                    />

                    <!-- Masa Berlaku -->
                    <div v-if="adaPeranAkses" class="ap-wz-form-section">
                        <label class="form-label small fw-semibold text-dark mb-1" for="wz-sampai"
                            >Masa Berlaku Penugasan</label
                        >
                        <HcisDatePicker
                            id="wz-sampai"
                            v-model="form.berlaku_sampai"
                            type="date"
                            placeholder="Tanpa batas waktu (permanen)"
                        />
                        <div class="d-flex flex-wrap gap-1.5 mt-2">
                            <HcisButton
                                v-for="p in pintasanWaktu"
                                :key="p.label"
                                variant="secondary"
                                size="xs"
                                :label="p.label"
                                @click="form.berlaku_sampai = p.nilai()"
                            />
                        </div>
                        <div v-if="adaPeranPool" class="ap-card-subtitle mt-2">
                            Batas waktu hanya untuk peran Akses Menu. Peran Pool Approval berlaku tanpa batas.
                        </div>
                    </div>

                    <!-- Alasan Wajib -->
                    <div class="ap-wz-form-section">
                        <HcisTextarea
                            id="wz-alasan"
                            v-model="form.alasan"
                            label="Alasan Penugasan (wajib, tercatat di audit)"
                            placeholder="Contoh: Penugasan koordinator verifikasi per memo internal HRD/2026"
                            :rows="2"
                        />
                    </div>

                    <!-- Yang akan didapat: gabungan isi semua peran terpilih. -->
                    <div class="ap-wz-form-section">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span class="form-label small fw-semibold text-dark mb-0">Yang akan didapat</span>
                            <HcisButton
                                v-if="gabunganIsi.length"
                                variant="ghost"
                                size="xs"
                                :label="lihatDidapat ? 'Sembunyikan' : 'Lihat rincian'"
                                @click="lihatDidapat = !lihatDidapat"
                            />
                        </div>
                        <div class="ap-card-subtitle">{{ teksDidapat }}</div>
                        <!-- Rantai Alur Approval Menu & Matrix di Step 3 -->
                        <div v-if="gabunganJalurApproval.length" class="mt-2.5 pt-2 border-top">
                            <span class="ap-kicker mb-1.5 d-block text-warning-emphasis">
                                ALUR PERSETUJUAN TRANSAKSI:
                            </span>
                            <div class="d-flex flex-column gap-2">
                                <div
                                    v-for="j in gabunganJalurApproval"
                                    :key="j.kode_jalur"
                                    class="p-2.5 rounded-3 bg-white border border-warning-subtle"
                                >
                                    <div class="d-flex align-items-center justify-content-between gap-1 mb-1.5">
                                        <div class="d-flex align-items-center gap-1.5 min-w-0">
                                            <HcisIcon name="diagram-3" size="xs" color="warning" />
                                            <span class="fw-bold small text-dark text-truncate">{{
                                                j.nama_jalur
                                            }}</span>
                                        </div>
                                        <HcisBadge
                                            :label="`Tahap ${j.tahap_ke} dari ${j.total_tahap}`"
                                            variant="warning"
                                            appearance="subtle"
                                            size="xs"
                                        />
                                    </div>
                                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                        <template v-for="(lk, idx) in j.langkah" :key="lk.urutan">
                                            <span
                                                class="ap-step-pill"
                                                :class="{ 'ap-step-pill--target': lk.adalah_peran_ini }"
                                            >
                                                <HcisIcon
                                                    v-if="lk.adalah_peran_ini"
                                                    name="check-circle-fill"
                                                    size="2xs"
                                                />
                                                <span>{{ lk.urutan }}. {{ lk.nama }}</span>
                                            </span>
                                            <HcisIcon
                                                v-if="idx < j.langkah.length - 1"
                                                name="arrow-right"
                                                size="2xs"
                                                class="text-muted opacity-60"
                                            />
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kategori Tamu untuk Peran Visitor Unit -->
                        <div v-if="gabunganAlurApproval.length" class="mt-2.5 pt-2 border-top">
                            <span class="ap-kicker mb-1.5 d-block text-warning-emphasis">
                                KATEGORI TAMU YANG DITANGANI:
                            </span>
                            <div class="d-flex flex-wrap gap-2">
                                <div
                                    v-for="al in gabunganAlurApproval"
                                    :key="al"
                                    class="d-inline-flex align-items-center gap-1.5 p-1 px-2.5 rounded-2 bg-white border border-warning-subtle"
                                >
                                    <HcisIcon name="eye" size="2xs" color="warning" />
                                    <span class="small fw-semibold text-dark">{{ al }}</span>
                                </div>
                            </div>
                        </div>
                        <!-- Kemampuan Fitur Bisnis Terstandarisasi yang didapat -->
                        <div v-if="gabunganKemampuan.length" class="mt-2.5 pt-2 border-top">
                            <span class="ap-kicker mb-1.5 d-block text-brand">
                                KEMAMPUAN FITUR BISNIS YANG DIDAPAT:
                            </span>
                            <div class="d-flex flex-wrap gap-1.5">
                                <HcisBadge
                                    v-for="k in gabunganKemampuan"
                                    :key="k.kode_kemampuan"
                                    :label="`${k.nama_kemampuan} (${k.nama_fitur})`"
                                    variant="brand"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </div>
                        </div>

                        <DaftarIsiPeran v-if="lihatDidapat" class="mt-2" :halaman="gabunganIsi" :baru="halamanBaru" />
                    </div>
                </div>
            </template>

            <template #footer>
                <div class="d-flex justify-content-between align-items-center gap-2 w-100">
                    <HcisButton
                        v-if="langkah > 0 && !hasilMassal"
                        variant="ghost"
                        size="sm"
                        leading-icon="arrow-left"
                        label="Kembali"
                        :disabled="simpan"
                        @click="langkah--"
                    />
                    <span v-else />
                    <div class="d-flex gap-2">
                        <HcisButton
                            v-if="hasilMassal"
                            variant="primary"
                            size="sm"
                            label="Selesai"
                            @click="$emit('update:modelValue', false)"
                        />
                        <HcisButton
                            v-else
                            variant="secondary"
                            size="sm"
                            label="Batal"
                            :disabled="simpan"
                            @click="$emit('update:modelValue', false)"
                        />
                        <HcisButton
                            v-if="currentStepKey === 'karyawan' && !hasilMassal"
                            variant="primary"
                            size="sm"
                            trailing-icon="arrow-right"
                            :label="jumlahTerpilih ? `Lanjut (${jumlahTerpilih} karyawan)` : 'Pilih karyawan dulu'"
                            :loading="memuatAkses"
                            :disabled="!jumlahTerpilih || jumlahTerpilih > MAKS_MASSAL"
                            @click="lanjutDariKaryawan"
                        />
                        <HcisButton
                            v-if="currentStepKey === 'peran'"
                            variant="primary"
                            size="sm"
                            trailing-icon="arrow-right"
                            label="Lanjut ke Detail"
                            :disabled="!form.id_roles.length"
                            @click="langkah++"
                        />
                        <HcisButton
                            v-if="currentStepKey === 'detail' && !hasilMassal"
                            variant="primary"
                            size="sm"
                            :leading-icon="infoPeranTetap?.jenis === 'POOL_APPROVAL' ? 'check-circle' : 'person-check'"
                            :label="labelSimpan"
                            :loading="simpan"
                            :disabled="form.alasan.trim().length < 5 || !cakupanValid"
                            @click="kirim"
                        />
                    </div>
                </div>
            </template>
        </HcisDialog>

        <!-- ══ DRAWER TINGKAT HALAMAN (STANDALONE PAGE-LEVEL DRAWER) ══ -->
        <HcisDrawer
            :model-value="!!intip"
            direction="rtl"
            size="36rem"
            :sentiment="
                peranIntip?.jenis === 'POOL_APPROVAL'
                    ? 'warning'
                    : peranIntip?.tingkat === 'SUPER_ADMIN'
                      ? 'purple'
                      : peranIntip?.tingkat === 'ADMIN'
                        ? 'primary'
                        : 'neutral'
            "
            custom-class="ap-drawer-pratinjau"
            @update:model-value="(v) => !v && (intip = null)"
        >
            <template #header>
                <div class="ap-drawer-custom-header w-100">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-2.5 min-w-0">
                            <div
                                class="ap-drawer-icon-box"
                                :class="`ap-drawer-icon-box--${
                                    peranIntip?.jenis === 'POOL_APPROVAL'
                                        ? 'warning'
                                        : peranIntip?.tingkat === 'SUPER_ADMIN'
                                          ? 'purple'
                                          : peranIntip?.tingkat === 'ADMIN'
                                            ? 'primary'
                                            : 'neutral'
                                }`"
                            >
                                <HcisIcon
                                    :name="
                                        peranIntip?.jenis === 'POOL_APPROVAL'
                                            ? 'diagram-3-fill'
                                            : peranIntip?.tingkat === 'SUPER_ADMIN'
                                              ? 'shield-fill-check'
                                              : peranIntip?.tingkat === 'ADMIN'
                                                ? 'shield-shaded'
                                                : 'person-badge'
                                    "
                                    size="sm"
                                />
                            </div>
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="ap-kicker mb-0">RINCIAN HAK PERAN</span>
                                    <HcisBadge
                                        v-if="peranIntip"
                                        :label="propsPratinjau.badge.label"
                                        :variant="propsPratinjau.badge.variant"
                                        appearance="subtle"
                                        size="xs"
                                    />
                                </div>
                                <h5 class="ap-drawer-title-heading text-truncate mb-0">
                                    {{ peranIntip?.nama || 'Isi Hak Peran' }}
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- Meta Strip Info (Kode, Modul, Keterangan) -->
                    <div v-if="peranIntip" class="ap-drawer-meta-strip mt-2.5">
                        <span class="ap-drawer-meta-code font-monospace">{{ peranIntip.kode }}</span>
                        <span v-if="peranIntip.modul" class="ap-drawer-meta-divider">·</span>
                        <span v-if="peranIntip.modul" class="ap-drawer-meta-item">{{ peranIntip.modul }}</span>
                        <span v-if="peranIntip.lama" class="ap-drawer-meta-divider">·</span>
                        <span v-if="peranIntip.lama" class="ap-drawer-badge-lama">Peran Lama</span>
                    </div>
                </div>
            </template>
            <template #default>
                <PratinjauPeran v-bind="propsPratinjau" tanpa-aksi @pilih="togglePeran(peranIntip)" />
            </template>
            <template #footer>
                <div class="ap-drawer-footer-content w-100">
                    <div class="ap-drawer-footer-status min-w-0">
                        <span
                            v-if="!propsPratinjau.bisa && propsPratinjau.alasan"
                            class="text-danger small font-medium d-flex align-items-center gap-1.5"
                        >
                            <HcisIcon name="exclamation-circle-fill" size="xs" />
                            <span>{{ propsPratinjau.alasan }}</span>
                        </span>
                        <span
                            v-else-if="propsPratinjau.dipilih"
                            class="text-emerald-700 small font-semibold d-flex align-items-center gap-1.5"
                        >
                            <HcisIcon name="check-circle-fill" size="xs" class="text-success" />
                            <span>Peran ini sedang dipilih untuk ditugaskan</span>
                        </span>
                        <span v-else class="text-muted small d-flex align-items-center gap-1.5">
                            <HcisIcon name="info-circle" size="xs" />
                            <span>Klik pilih peran untuk menambahkan ke penugasan</span>
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <HcisButton variant="secondary" size="sm" label="Tutup" @click="intip = null" />
                        <HcisButton
                            v-if="peranIntip"
                            size="sm"
                            :variant="propsPratinjau.dipilih ? 'secondary' : 'primary'"
                            :leading-icon="propsPratinjau.dipilih ? 'x-lg' : 'check-lg'"
                            :label="propsPratinjau.dipilih ? 'Lepas Peran' : 'Pilih Peran Ini'"
                            :disabled="!propsPratinjau.bisa"
                            @click="togglePeran(peranIntip)"
                        />
                    </div>
                </div>
            </template>
        </HcisDrawer>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDatePicker,
    HcisDialog,
    HcisDrawer,
    HcisEmptyState,
    HcisField,
    HcisFilterBar,
    HcisIcon,
    HcisSelect,
    HcisPagination,
    HcisSkeleton,
    HcisSteps,
    HcisTable,
    HcisTabs,
    HcisTextarea,
    hcisMessage,
} from '@/components/ui';
import { aksesPeranApi, infoTingkat, pesanGalat } from '../api/aksesPeranApi.js';
import PemilihCakupan from './PemilihCakupan.vue';
import DaftarIsiPeran from './DaftarIsiPeran.vue';
import PratinjauPeran from './PratinjauPeran.vue';

const tambahHari = (n) => {
    const d = new Date();
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0, 10);
};
const pintasanWaktu = [
    { label: '+30 hari', nilai: () => tambahHari(30) },
    { label: '+90 hari', nilai: () => tambahHari(90) },
    { label: '+1 tahun', nilai: () => tambahHari(365) },
    { label: 'Tanpa batas', nilai: () => '' },
];

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** karyawan sudah dipilih (dari panel Pengguna) */
    idUser: { type: Number, default: null },
    /** hanya peran setingkat admin (tombol "Angkat admin") */
    hanyaAdmin: { type: Boolean, default: false },
    /** peran sudah dipilih (dari "TabPeran" atau "peran tanpa pemegang") */
    idRole: { type: Number, default: null },
    /** objek info peran lengkap (jika ada) */
    peranInfo: { type: Object, default: null },
    /** daftar seluruh peran */
    daftarPeran: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'selesai']);

const artiTingkat = {
    SUPER_ADMIN: 'Kepala Bidang / Dept Head: akses penuh modul & kelola delegasi staf',
    ADMIN: 'PIC Pengelola: staf yang menugaskan peran pengguna dalam cakupannya',
    PENGGUNA: 'Pelaksana & Verifikator: akses menu operasional & alur persetujuan',
};

function infoBadgePeran(p) {
    if (!p) return { label: 'Peran', variant: 'neutral', iconName: 'person-badge', iconClass: 'ap-role-icon--neutral' };
    if (p.jenis === 'POOL_APPROVAL') {
        return {
            label: 'Pool Approval',
            variant: 'warning',
            iconName: 'diagram-2',
            iconClass: 'ap-role-icon--warning',
        };
    }
    if (p.tingkat === 'SUPER_ADMIN') {
        return {
            label: 'Super Admin',
            variant: 'purple',
            iconName: 'shield-lock-fill',
            iconClass: 'ap-role-icon--purple',
        };
    }
    if (p.tingkat === 'ADMIN' || p.jenis === 'ADMIN') {
        return {
            label: 'Admin Modul',
            variant: 'brand',
            iconName: 'shield-fill-check',
            iconClass: 'ap-role-icon--brand',
        };
    }
    return { label: 'Akses Menu', variant: 'neutral', iconName: 'window-stack', iconClass: 'ap-role-icon--neutral' };
}

// Mode alur wizard yang adaptif
const modeAlur = computed(() => {
    if (props.idRole && !props.idUser) return 'role-first';
    if (props.idUser && !props.idRole) return 'user-first';
    return 'full';
});

const infoPeranTetap = computed(() => {
    if (props.peranInfo) return props.peranInfo;
    const targetId = (form.id_roles.length === 1 && form.id_roles[0]) || props.idRole;
    if (targetId && props.daftarPeran?.length) {
        return props.daftarPeran.find((p) => p.id === targetId) || null;
    }
    return null;
});

const headerKicker = computed(() => {
    if (props.hanyaAdmin) return 'HAK AKSES ADMINISTRATOR';
    if (infoPeranTetap.value?.jenis === 'POOL_APPROVAL') return 'ALUR KERJA & APPROVAL POOL';
    if (modeAlur.value === 'role-first') return 'PENUGASAN PERAN TERARAH';
    return 'MANAJEMEN OTORISASI';
});

const headerTitle = computed(() => {
    if (props.hanyaAdmin) return 'Angkat Administrator Modul';
    if (modeAlur.value === 'role-first' && infoPeranTetap.value?.nama) {
        return `Tugaskan Peran: ${infoPeranTetap.value.nama}`;
    }
    return 'Tugaskan Peran Karyawan';
});

const headerDescription = computed(() => {
    if (props.hanyaAdmin) {
        return 'Berikan wewenang pengelolaan administratif modul kepada karyawan terpilih.';
    }
    if (infoPeranTetap.value?.jenis === 'POOL_APPROVAL') {
        return 'Tugaskan satu atau beberapa karyawan aktif ke pool verifikator alur persetujuan ini.';
    }
    if (modeAlur.value === 'role-first' && infoPeranTetap.value) {
        return `Pilih karyawan yang akan memegang wewenang ${infoPeranTetap.value.nama} (${infoPeranTetap.value.kode}).`;
    }
    return 'Pilih karyawan, tentukan peran yang sesuai, dan atur batasan cakupan penugasan.';
});

const headerBadge = computed(() => {
    if (props.hanyaAdmin) return 'Admin Modul';
    if (infoPeranTetap.value?.jenis === 'POOL_APPROVAL') return 'Workflow Pool';
    if (infoPeranTetap.value) return infoBadgePeran(infoPeranTetap.value).label;
    return 'Penugasan';
});

const headerBadgeVariant = computed(() => {
    if (props.hanyaAdmin) return 'purple';
    if (infoPeranTetap.value?.jenis === 'POOL_APPROVAL') return 'warning';
    if (infoPeranTetap.value) return infoBadgePeran(infoPeranTetap.value).variant;
    return 'brand';
});

const headerIcon = computed(() => {
    if (infoPeranTetap.value?.jenis === 'POOL_APPROVAL') return 'diagram-2';
    if (props.hanyaAdmin) return 'shield-plus';
    return 'person-plus';
});

const headerSentiment = computed(() => {
    if (infoPeranTetap.value?.jenis === 'POOL_APPROVAL') return 'warning';
    if (props.hanyaAdmin) return 'purple';
    return 'brand';
});

const daftarLangkah = computed(() => {
    return [
        {
            key: 'karyawan',
            title: '1. Pilih Karyawan',
            subtitle: 'Target penugasan',
            caption: jumlahTerpilih.value ? `${jumlahTerpilih.value} terpilih` : 'Pilih karyawan',
            icon: 'people-fill',
        },
        {
            key: 'peran',
            title: '2. Tentukan Peran',
            subtitle: 'Wewenang & modul',
            caption:
                daftarPeranTerpilih.value.length > 1
                    ? `${daftarPeranTerpilih.value.length} peran`
                    : peranTerpilih.value?.nama || 'Pilih wewenang',
            icon: 'person-badge-fill',
        },
        {
            key: 'detail',
            title: '3. Detail Otorisasi',
            subtitle: 'Cakupan wewenang',
            caption: 'Scope unit & memo',
            icon: 'shield-lock-fill',
        },
    ];
});

const langkah = ref(0);
const currentStepKey = computed(() => daftarLangkah.value[langkah.value]?.key || 'karyawan');

/* ── Pemilih karyawan (banyak sekaligus) ── */
const MAKS_MASSAL = 200;
const saringKosong = () => ({
    cari: '',
    divisi: [],
    departemen: [],
    lokasi: [],
    level: [],
    jabatan: [],
    status_kepegawaian: [],
    status: props.idRole ? 'belum' : '',
});
const saring = reactive(saringKosong());
const opsiSaring = ref(null);
const daftar = reactive({ data: [], total: 0, halaman: 1, per_halaman: 25 });
const mencari = ref(false);
const memilihSemua = ref(false);
const terpilih = reactive(new Map());
const hasilMassal = ref(null);
const saringTerbuka = ref(false);
const jumlahSaringAktif = computed(
    () =>
        ['divisi', 'departemen', 'lokasi', 'level', 'jabatan', 'status_kepegawaian'].filter((k) => saring[k].length)
            .length,
);
let penundaCari = null;
let urutanMuat = 0;

const idRoleSasaran = computed(() => props.idRole || null);
const kunciTerpilih = computed(() => [...terpilih.keys()]);
const jumlahTerpilih = computed(() => terpilih.size);
const chipTerpilih = computed(() => [...terpilih.values()].slice(0, 12));
const semuaHasilTerpilih = computed(
    () => daftar.total > 0 && jumlahTerpilih.value >= Math.min(daftar.total, MAKS_MASSAL),
);
const adaSaringan = computed(() =>
    Object.entries(saring).some(([k, v]) =>
        k === 'status' ? v !== saringKosong().status : Array.isArray(v) ? v.length : v,
    ),
);
const daftarSaring = computed(() => {
    const o = opsiSaring.value || {};
    const departemen = (o.departemen || []).filter((d) => !saring.divisi.length || saring.divisi.includes(d.divisi));
    return [
        { key: 'divisi', label: 'Divisi', opsi: o.divisi || [] },
        { key: 'departemen', label: 'Departemen', opsi: departemen },
        { key: 'jabatan', label: 'Jabatan', opsi: o.jabatan || [] },
        { key: 'level', label: 'Level', opsi: o.level || [] },
        { key: 'lokasi', label: 'Lokasi', opsi: o.lokasi || [] },
        { key: 'status_kepegawaian', label: 'Status Kepegawaian', opsi: o.status_kepegawaian || [] },
    ];
});

const hcisFilterDefinitions = computed(() =>
    daftarSaring.value.map((f) => ({
        key: f.key,
        label: f.label,
        type: 'select',
        options: (f.opsi || []).map((o) => ({ label: o.label, value: o.nilai })),
        placeholder: `Semua ${f.label}`,
    })),
);

const filterValues = computed(() => ({
    divisi: saring.divisi[0] || '',
    departemen: saring.departemen[0] || '',
    jabatan: saring.jabatan[0] || '',
    level: saring.level[0] || '',
    lokasi: saring.lokasi[0] || '',
    status_kepegawaian: saring.status_kepegawaian[0] || '',
}));

function onHcisFilterChange({ key, value }) {
    saring[key] = value ? [value] : [];
    muatUlang();
}

const quickStatusChips = computed(() => {
    if (!idRoleSasaran.value) return [];
    return [
        { id: 'belum', label: 'Belum Memegang Peran', value: 'belum' },
        { id: 'semua', label: 'Semua Karyawan', value: '' },
        { id: 'sudah', label: 'Sudah Memegang', value: 'sudah' },
    ];
});

function onStatusChipClick(chip) {
    saring.status = chip.value;
    muatUlang();
}

const kolomKaryawan = [
    { key: 'nama', label: 'Karyawan', width: '34%' },
    { key: 'unit', label: 'Departemen / Divisi' },
    { key: 'jabatan', label: 'Jabatan / Level' },
    { key: 'lokasi', label: 'Lokasi' },
];

/** Jumlah karyawan sasaran penugasan (panel Pengguna = satu orang tetap). */
const jumlahSasaran = computed(() => (modeAlur.value === 'user-first' ? 1 : jumlahTerpilih.value));
const labelSasaran = computed(() =>
    jumlahSasaran.value > 1
        ? `${jumlahSasaran.value} karyawan`
        : akses.value?.nama || [...terpilih.values()][0]?.nama || '',
);
const subSasaran = computed(() =>
    jumlahSasaran.value > 1
        ? chipTerpilih.value
              .slice(0, 3)
              .map((k) => k.nama)
              .join(', ') + (jumlahSasaran.value > 3 ? ', …' : '')
        : `NIK: ${akses.value?.kode_karyawan || '—'}`,
);
const akses = ref(null);
const memuatAkses = ref(false);
// Isi peran (halaman × aksi) per id, dimuat sesuai kebutuhan.
const isiPeran = reactive(new Map());
const intip = ref(null);
const lihatDidapat = ref(false);
// Halaman yang baru bagi karyawan (hanya bila satu karyawan sasaran).
const halamanBaru = ref(new Set());
const pratinjauSiap = ref(false);
const simpan = ref(false);
const cakupanValid = ref(true);
const form = reactive({ id_roles: [], cakupan: [], berlaku_sampai: '', alasan: '' });
let penunda = null;
let urutan = 0;

// Peran yang sudah dipegang hanya disembunyikan untuk satu karyawan; untuk banyak
// karyawan server melewati (dan melaporkan) yang sudah memegang.
const dipegang = computed(() =>
    jumlahSasaran.value > 1 ? new Set() : new Set((akses.value?.peran || []).map((p) => p.id_role)),
);
const tersedia = computed(() =>
    (akses.value?.peran_tersedia || []).filter(
        (p) => !dipegang.value.has(p.id) && (!props.hanyaAdmin || ['SUPER_ADMIN', 'ADMIN'].includes(p.tingkat)),
    ),
);
const cariPeran = ref('');
const filterKategoriPeran = ref('ALL');
const filterModulPeran = ref('');

const opsiModulPeran = computed(() => {
    const setModul = new Set((tersedia.value || []).map((p) => p.modul).filter(Boolean));
    return [...setModul].map((m) => ({ label: m, value: m }));
});

const quickRoleChips = computed(() => {
    const list = tersedia.value || [];
    const counts = { ALL: list.length, AKSES: 0, POOL: 0, ADMIN: 0, SUPER: 0 };
    list.forEach((p) => {
        if (p.tingkat === 'SUPER_ADMIN') counts.SUPER++;
        else if (p.jenis === 'POOL_APPROVAL') counts.POOL++;
        else if (p.tingkat === 'ADMIN' || p.jenis === 'ADMIN') counts.ADMIN++;
        else counts.AKSES++;
    });

    const chips = [
        { id: 'semua', label: 'Semua Peran', value: 'ALL', badge: counts.ALL },
        { id: 'akses', label: 'Akses Menu', value: 'AKSES', icon: 'window-stack', badge: counts.AKSES },
        { id: 'pool', label: 'Alur Approval', value: 'POOL_APPROVAL', icon: 'diagram-2', badge: counts.POOL },
        { id: 'admin', label: 'Admin Modul', value: 'ADMIN', icon: 'shield-check', badge: counts.ADMIN },
    ];
    if (counts.SUPER > 0) {
        chips.push({
            id: 'super',
            label: 'Super Admin',
            value: 'SUPER_ADMIN',
            icon: 'shield-lock',
            badge: counts.SUPER,
        });
    }
    return chips;
});

const roleFilterDefinitions = computed(() => {
    const defs = [];
    if (opsiModulPeran.value.length > 1) {
        defs.push({
            key: 'modul',
            label: 'Modul Sistem',
            type: 'select',
            options: opsiModulPeran.value,
            placeholder: 'Semua Modul',
        });
    }
    return defs;
});

const roleFilterValues = computed(() => ({
    modul: filterModulPeran.value,
}));

function onRoleFilterChange({ key, value }) {
    if (key === 'modul') filterModulPeran.value = value || '';
}

function resetRoleFilter() {
    cariPeran.value = '';
    filterKategoriPeran.value = 'ALL';
    filterModulPeran.value = '';
}

const filteredTersedia = computed(() => {
    let list = tersedia.value || [];
    if (filterKategoriPeran.value !== 'ALL') {
        if (filterKategoriPeran.value === 'POOL_APPROVAL') {
            list = list.filter((p) => p.jenis === 'POOL_APPROVAL');
        } else if (filterKategoriPeran.value === 'SUPER_ADMIN') {
            list = list.filter((p) => p.tingkat === 'SUPER_ADMIN');
        } else if (filterKategoriPeran.value === 'ADMIN') {
            list = list.filter((p) => p.tingkat === 'ADMIN' || p.jenis === 'ADMIN');
        } else if (filterKategoriPeran.value === 'AKSES') {
            list = list.filter(
                (p) => p.jenis !== 'POOL_APPROVAL' && p.tingkat !== 'SUPER_ADMIN' && p.tingkat !== 'ADMIN',
            );
        }
    }
    if (filterModulPeran.value) {
        list = list.filter((p) => p.modul === filterModulPeran.value);
    }
    const q = cariPeran.value.trim().toLowerCase();
    if (q) {
        list = list.filter(
            (p) =>
                (p.nama && p.nama.toLowerCase().includes(q)) ||
                (p.kode && p.kode.toLowerCase().includes(q)) ||
                (p.modul && p.modul.toLowerCase().includes(q)),
        );
    }
    return list;
});

const kelompok = computed(() => {
    const list = filteredTersedia.value;
    return ['SUPER_ADMIN', 'ADMIN', 'PENGGUNA']
        .map((t) => ({ tingkat: t, peran: list.filter((p) => (p.tingkat || 'PENGGUNA') === t) }))
        .filter((g) => g.peran.length);
});
/* ── Pilihan peran (banyak sekaligus; admin ditugaskan sendiri) ── */
const adalahAdmin = (p) => ['SUPER_ADMIN', 'ADMIN'].includes(p?.tingkat) || p?.jenis === 'ADMIN';
const cariPeranById = (id) =>
    tersedia.value.find((p) => p.id === id) ||
    (props.peranInfo?.id === id ? props.peranInfo : null) ||
    props.daftarPeran.find((p) => p.id === id) ||
    null;
const daftarPeranTerpilih = computed(() => form.id_roles.map(cariPeranById).filter(Boolean));
const peranTerpilih = computed(() => daftarPeranTerpilih.value[0] || infoPeranTetap.value || null);
const peranTerkunci = computed(() => (modeAlur.value === 'role-first' ? props.idRole : null));
const adalahSuper = (p) => p?.tingkat === 'SUPER_ADMIN';
const adaSuperTerpilih = computed(() => daftarPeranTerpilih.value.some(adalahSuper));
// Tanggung jawab diisi bila ada peran setingkat admin; server hanya memakainya untuk peran itu.
const setingkatAdmin = computed(() => daftarPeranTerpilih.value.some(adalahAdmin));
const adaPeranPool = computed(() => daftarPeranTerpilih.value.some((p) => p.jenis === 'POOL_APPROVAL'));
const adaPeranAkses = computed(() => daftarPeranTerpilih.value.some((p) => p.jenis !== 'POOL_APPROVAL'));
const dipilih = (p) => form.id_roles.includes(p.id);
function bisaDipilih(p) {
    if (dipilih(p)) return p.id !== peranTerkunci.value;
    if (props.hanyaAdmin || !form.id_roles.length) return true;
    // Super Admin hanya bergabung dengan sesama Super Admin.
    return adalahSuper(p) === adaSuperTerpilih.value;
}
function alasanTakBisa(p) {
    if (p.id === peranTerkunci.value) return 'Sudah ditentukan dari halaman peran';
    return adalahSuper(p) ? 'Tugaskan terpisah — kosongkan pilihan dulu' : 'Tugaskan terpisah dari Super Admin';
}
function togglePeran(p) {
    if (!bisaDipilih(p)) {
        hcisMessage.info(
            p.id === peranTerkunci.value
                ? 'Peran ini sudah ditentukan dari halaman peran dan tidak bisa dilepas.'
                : 'Super Admin tidak digabung dengan peran Admin atau Pengguna. Klik "Kosongkan" dulu bila ingin menggantinya.',
        );
        return;
    }
    if (dipilih(p)) {
        form.id_roles = form.id_roles.filter((id) => id !== p.id);
        return;
    }
    // Angkat admin: satu pilihan saja (perilaku radio).
    form.id_roles = props.hanyaAdmin ? [p.id] : [...form.id_roles, p.id];
    if (layarLebar.value) intipPeran(p);
}
const labelSimpan = computed(() => {
    const nPeran = daftarPeranTerpilih.value.length;
    const dasar = props.hanyaAdmin ? 'Angkat Admin' : nPeran > 1 ? `Tugaskan ${nPeran} Peran` : 'Tugaskan Peran';
    return dasar + (jumlahSasaran.value > 1 ? ` ke ${jumlahSasaran.value} Karyawan` : '');
});
const wewenangMap = reactive(new Map());
const kemampuanMap = reactive(new Map());
async function muatIsi(ids) {
    const kurang = ids.filter((id) => !isiPeran.has(id));
    if (!kurang.length) return;
    try {
        for (const r of (await aksesPeranApi.isiPeran(kurang)) || []) {
            isiPeran.set(r.id, r.halaman);
            if (r.wewenang_bisnis) wewenangMap.set(r.id, r.wewenang_bisnis);
            if (r.daftar_kemampuan) kemampuanMap.set(r.id, r.daftar_kemampuan);
        }
    } catch {
        kurang.forEach((id) => isiPeran.set(id, []));
    }
}
function intipPeran(p) {
    if (intip.value?.id === p.id) {
        intip.value = null;
        return;
    }
    intip.value = { id: p.id, nama: p.nama };
    muatIsi([p.id]);
}
const peranIntip = computed(() => (intip.value ? cariPeranById(intip.value.id) : null));
const propsPratinjau = computed(() => {
    const p = peranIntip.value;
    if (!p) return { peran: null };
    const info = props.daftarPeran.find((x) => x.id === p.id) || {};
    return {
        peran: p,
        halaman: isiPeran.has(p.id) ? isiPeran.get(p.id) : null,
        wewenangBisnis: wewenangMap.get(p.id) || p.wewenang_bisnis || null,
        daftarKemampuan: kemampuanMap.get(p.id) || p.daftar_kemampuan || [],
        info: { lama: info.lama || p.lama, kembar: info.kembar || [] },
        badge: infoBadgePeran(p),
        dipilih: dipilih(p),
        bisa: bisaDipilih(p),
        alasan: bisaDipilih(p) ? '' : alasanTakBisa(p),
    };
});
const gabunganIsi = computed(() => {
    const per = new Map();
    for (const id of form.id_roles) {
        for (const h of isiPeran.get(id) || []) {
            const k = String(h.jenis_page).toLowerCase();
            const ada = per.get(k);
            per.set(k, ada ? { ...ada, aksi: [...new Set([...ada.aksi, ...h.aksi])] } : { ...h });
        }
    }
    return [...per.values()].sort((a, b) => `${a.header}${a.nama_menu}`.localeCompare(`${b.header}${b.nama_menu}`));
});
const gabunganJalurApproval = computed(() => {
    const list = [];
    for (const id of form.id_roles) {
        const wb = wewenangMap.get(id);
        if (wb?.jalur_approval?.length) list.push(...wb.jalur_approval);
    }
    return list;
});

const gabunganKemampuan = computed(() => {
    const list = [];
    const seen = new Set();
    for (const id of form.id_roles) {
        for (const k of kemampuanMap.get(id) || []) {
            if (!seen.has(k.kode_kemampuan)) {
                seen.add(k.kode_kemampuan);
                list.push(k);
            }
        }
    }
    return list;
});

const gabunganAlurApproval = computed(() => {
    const alur = [];
    for (const id of form.id_roles) {
        const wb = wewenangMap.get(id);
        if (wb?.kategori_tamu?.length)
            alur.push(...wb.kategori_tamu.map((k) => (typeof k === 'object' ? k.label : `Tamu ${k}`)));
    }
    return [...new Set(alur)];
});

const teksDidapat = computed(() => {
    const n = gabunganIsi.value.length;
    const k = daftarPeranTerpilih.value.length;
    if (!form.id_roles.every((id) => isiPeran.has(id))) return 'Menghitung isi peran…';
    if (!n && gabunganAlurApproval.value.length) {
        return `Menjadi anggota alur verifikasi dokumen (${gabunganAlurApproval.value.length} alur persetujuan).`;
    }
    if (!n) return 'Peran terpilih tidak membuka halaman. Pemegang Pool Approval menjadi anggota alur persetujuan.';
    const dasar = `${n} halaman dari ${k} peran`;
    if (jumlahSasaran.value > 1) return `${dasar}. Halaman yang sudah dimiliki tiap karyawan tetap.`;
    return pratinjauSiap.value
        ? `${dasar}, ${halamanBaru.value.size} di antaranya baru bagi karyawan ini.`
        : `${dasar}.`;
});

watch(
    () => props.modelValue,
    async (buka) => {
        urutan++;
        if (!buka) return;
        Object.assign(form, {
            id_roles: props.idRole ? [props.idRole] : [],
            cakupan: [],
            berlaku_sampai: '',
            alasan: '',
        });
        Object.assign(saring, saringKosong());
        cariPeran.value = '';
        filterKategoriPeran.value = 'ALL';
        terpilih.clear();
        hasilMassal.value = null;
        Object.assign(daftar, { data: [], total: 0, halaman: 1 });
        akses.value = null;
        intip.value = null;
        lihatDidapat.value = false;
        halamanBaru.value = new Set();
        langkah.value = 0;
        if (props.idUser) {
            await muatAkses(props.idUser);
            return;
        }
        muatOpsi();
        muat();
    },
);

watch(
    () => form.id_roles.join(','),
    async () => {
        halamanBaru.value = new Set();
        pratinjauSiap.value = false;
        const u = ++urutan;
        const ids = [...form.id_roles];
        if (!ids.length) return;
        muatIsi(ids);
        if (!akses.value || jumlahSasaran.value !== 1) return;
        try {
            // Satu pratinjau per peran; gabungan halaman barunya.
            const hasil = await Promise.all(
                ids.map((id) =>
                    aksesPeranApi.pratinjau({ jenis: 'penugasan', id_user: akses.value.id_user, id_role: id }),
                ),
            );
            if (u !== urutan) return;
            halamanBaru.value = new Set(
                hasil.flatMap((r) => (r?.halaman_baru || []).map((h) => String(h.jenis_page).toLowerCase())),
            );
            pratinjauSiap.value = true;
        } catch {
            /* pratinjau tidak wajib */
        }
    },
);

async function muatOpsi() {
    if (opsiSaring.value) return;
    try {
        opsiSaring.value = await aksesPeranApi.opsiSaringKaryawan();
    } catch {
        /* saringan lanjutan tidak wajib */
    }
}

function paramSaring() {
    const p = { ...saring };
    if (idRoleSasaran.value) p.id_role = idRoleSasaran.value;
    else delete p.status;
    return p;
}

async function muat() {
    const u = ++urutanMuat;
    mencari.value = true;
    try {
        const r = await aksesPeranApi.pilihKaryawan({
            ...paramSaring(),
            halaman: daftar.halaman,
            per_halaman: daftar.per_halaman,
        });
        if (u === urutanMuat) Object.assign(daftar, r);
    } catch (e) {
        if (u === urutanMuat) hcisMessage.error(pesanGalat(e, 'Gagal memuat karyawan.'));
    } finally {
        if (u === urutanMuat) mencari.value = false;
    }
}

function muatUlang() {
    daftar.halaman = 1;
    muat();
}

function jadwalkanMuat() {
    clearTimeout(penundaCari);
    penundaCari = setTimeout(muatUlang, 300);
}

function resetSaring() {
    Object.assign(saring, saringKosong());
    muatUlang();
}

/** HcisTable mengirim kunci terpilih; baris halaman ini disinkronkan, pilihan halaman lain tetap. */
function ubahPilihan(keys) {
    const pilih = new Set(keys);
    for (const r of daftar.data) {
        if (pilih.has(r.id_user)) terpilih.set(r.id_user, r);
        else terpilih.delete(r.id_user);
    }
}

function lepas(id) {
    terpilih.delete(id);
}

function kosongkanPilihan() {
    terpilih.clear();
}

async function pilihSemuaHasil() {
    memilihSemua.value = true;
    try {
        const r = await aksesPeranApi.pilihKaryawan({ ...paramSaring(), semua_id: 1 });
        (r?.karyawan || []).forEach((k) => {
            if (terpilih.size < MAKS_MASSAL && !terpilih.has(k.id_user)) terpilih.set(k.id_user, k);
        });
        if ((r?.karyawan || []).length >= MAKS_MASSAL) {
            hcisMessage.warning(`Dipilih ${MAKS_MASSAL} karyawan pertama (batas satu penugasan).`);
        }
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Gagal memilih semua hasil.'));
    } finally {
        memilihSemua.value = false;
    }
}

async function muatAkses(idUser) {
    memuatAkses.value = true;
    try {
        akses.value = await aksesPeranApi.detailPengguna(idUser);
    } catch (e) {
        hcisMessage.error(pesanGalat(e, 'Gagal memuat akses karyawan.'));
    } finally {
        memuatAkses.value = false;
    }
}

/**
 * Pilihan peran & cakupan bergantung pada wewenang Anda; diambil dari karyawan
 * terpilih pertama yang bisa Anda kelola. Server tetap memeriksa tiap karyawan.
 */
async function lanjutDariKaryawan() {
    akses.value = null;
    for (const id of kunciTerpilih.value.slice(0, 5)) {
        await muatAkses(id);
        if (akses.value?.bisa_kelola) break;
    }
    if (!akses.value?.bisa_kelola) {
        hcisMessage.warning(
            jumlahTerpilih.value > 1
                ? 'Wewenang Anda tidak mencukupi untuk karyawan terpilih. Ubah pilihan karyawan.'
                : 'Wewenang tidak mencukupi untuk mengelola peran karyawan ini.',
        );
        return;
    }
    if (peranTerkunci.value && !form.id_roles.includes(peranTerkunci.value)) form.id_roles = [peranTerkunci.value];
    langkah.value = 1;
}

async function kirim() {
    simpan.value = true;
    try {
        const isi = {
            cakupan: form.cakupan,
            // Server mengabaikan batas waktu untuk peran pool approval.
            berlaku_sampai: adaPeranAkses.value ? form.berlaku_sampai || null : null,
            alasan: form.alasan.trim(),
        };
        if (jumlahSasaran.value > 1 || form.id_roles.length > 1) {
            const idUsers = jumlahSasaran.value > 1 ? kunciTerpilih.value : [akses.value.id_user];
            const r = await aksesPeranApi.tugaskanMassal({ ...isi, id_roles: form.id_roles, id_users: idUsers });
            emit('selesai');
            if (r.gagal.length) {
                // Tetap terbuka: tampilkan siapa yang dilewati beserta alasannya.
                hasilMassal.value = r;
                return;
            }
            hcisMessage.success(`${r.berhasil.length} penugasan dibuat.`);
            emit('update:modelValue', false);
            return;
        }
        await aksesPeranApi.tugaskan({ ...isi, id_role: form.id_roles[0], id_user: akses.value.id_user });
        hcisMessage.success(props.hanyaAdmin ? 'Admin diangkat.' : 'Peran ditugaskan.');
        emit('update:modelValue', false);
        emit('selesai');
    } catch (e) {
        hcisMessage.error(pesanGalat(e));
    } finally {
        simpan.value = false;
    }
}
</script>

<style scoped>
.ap-kicker {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--hcis-brand-600, #0284c7);
    line-height: 1.25;
    display: inline-block;
}

.ap-card-subtitle {
    font-size: 0.75rem;
    font-weight: 400;
    color: var(--hcis-text-secondary, #64748b);
    line-height: 1.45;
}

.ap-kecil {
    font-size: 0.75rem;
    line-height: 1.45;
    color: var(--hcis-text-secondary, #64748b);
}

.ap-wz-step-body {
    min-height: 320px;
}

/* ══ TARGET ROLE HERO (ROLE-FIRST) ══ */
.ap-wz-target-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 0.95rem 1.15rem;
    border-radius: var(--hcis-radius-xl, 12px);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.9);
    background: #ffffff;
    box-shadow:
        0 2px 8px -2px rgba(15, 23, 42, 0.06),
        0 1px 3px 0 rgba(15, 23, 42, 0.04);
}

.ap-wz-target-hero--brand {
    background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 70%);
    border-color: rgba(2, 132, 199, 0.35);
}

.ap-wz-target-hero--warning {
    background: linear-gradient(135deg, #fffbeb 0%, #ffffff 70%);
    border-color: rgba(217, 119, 6, 0.35);
}

.ap-wz-target-icon {
    width: 2.85rem;
    height: 2.85rem;
    border-radius: var(--hcis-radius-lg, 10px);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow:
        0 2px 8px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 #ffffff;
}

.ap-wz-target-icon--brand {
    background: linear-gradient(135deg, #e0f2fe, #bae6fd);
    color: #0284c7;
    border: 1px solid rgba(2, 132, 199, 0.3);
}

.ap-wz-target-icon--warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #b45309;
    border: 1px solid rgba(217, 119, 6, 0.3);
}

.ap-wz-target-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    letter-spacing: -0.015em;
    line-height: 1.35;
}

.ap-wz-target-meta {
    font-size: 0.75rem;
    color: var(--hcis-text-secondary, #64748b);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* ══ CONTEXT RIBBON ══ */
.ap-wz-context-ribbon {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    padding: 0.9rem 1.15rem;
    background: #ffffff;
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.9);
    border-radius: var(--hcis-radius-xl, 12px);
    box-shadow:
        0 2px 8px -2px rgba(15, 23, 42, 0.06),
        0 1px 3px 0 rgba(15, 23, 42, 0.04);
}

/* ══ PEMILIH KARYAWAN (BANYAK) ══ */
.wz-saring {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--hcis-space-2);
}

.wz-saring__cari {
    grid-column: 1 / -1;
}

/* Saringan lanjutan: sebaris di layar lebar, dilipat di HP. */
.wz-saring__lanjut {
    display: contents;
}

.wz-bilah-pilih {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--hcis-space-2);
    padding: 0.65rem 0.95rem;
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.9);
    border-radius: var(--hcis-radius-lg, 10px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.wz-chip-terpilih {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--hcis-space-1);
    max-height: 4.5rem;
    overflow-y: auto;
}

.wz-tabel {
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-lg, 10px);
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.wz-tabel :deep(.hcis-table-wrap) {
    max-height: 24rem;
    overflow-y: auto;
}

.wz-tabel :deep(.hcis-table thead),
.wz-tabel :deep(.hcis-table th) {
    background-color: #f8fafc !important;
    background-image: none !important;
    backdrop-filter: none !important;
    opacity: 1 !important;
    z-index: 10;
}

@media (max-width: 767.98px) {
    .wz-saring {
        grid-template-columns: 1fr;
    }

    .wz-saring__lanjut {
        display: none;
    }

    .wz-saring__lanjut.is-buka {
        display: grid;
        gap: var(--hcis-space-2);
    }

    .wz-tabel {
        max-height: none;
    }
}

.ap-wz-karyawan-nama {
    font-size: 0.875rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.35;
}

.ap-wz-karyawan-sub {
    font-size: 0.75rem;
    line-height: 1.3;
}

/* ══ OFFICIAL HCIS STEPS CONTAINER ══ */
.ap-wz-stepper-wrap {
    padding: 0.85rem 1.25rem;
    background: #ffffff;
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.8);
    border-radius: var(--hcis-radius-xl, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    display: flex;
    justify-content: center;
}

.ap-wz-stepper-wrap :deep(.hcis-steps-wrapper) {
    width: 100%;
    max-width: 680px;
}

.ap-wz-stepper-wrap :deep(.hcis-steps__list--horizontal) {
    justify-content: center;
}

/* ══ ROLE PICKER GRID (STEP 2) ══ */
.wz-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
    gap: 0.75rem;
}

.ap-role-card-item {
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    border-radius: var(--hcis-radius-xl, 12px);
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    background: #ffffff;
}

.ap-role-card-item :deep(.hcis-card__body) {
    padding: 0.85rem 1rem !important;
}

.ap-role-card-item:hover {
    transform: translateY(-2px);
    border-color: var(--hcis-brand-400, #38bdf8);
    box-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.08) !important;
}

.ap-role-card-item.is-selected {
    box-shadow:
        0 0 0 2px var(--hcis-brand-500, #0284c7),
        0 6px 18px -2px rgba(2, 132, 199, 0.25) !important;
    background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%) !important;
}

.ap-role-card-item.hcis-card--sentiment-warning.is-selected {
    box-shadow:
        0 0 0 2px var(--hcis-warning-500, #f59e0b),
        0 6px 18px -2px rgba(245, 158, 11, 0.25) !important;
}

.ap-role-card-item.hcis-card--sentiment-purple.is-selected {
    box-shadow:
        0 0 0 2px #8b5cf6,
        0 6px 18px -2px rgba(139, 92, 246, 0.25) !important;
}

.ap-role-icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: var(--hcis-radius-md, 8px);
    display: flex;
    align-items: center;
    justify-content: center;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
    flex-shrink: 0;
}

.ap-role-card-item:hover .ap-role-icon {
    transform: scale(1.06);
}

.ap-role-icon--neutral {
    background: linear-gradient(135deg, #ffffff, #f1f5f9);
    border: 1px solid rgba(148, 163, 184, 0.4);
    color: #334155;
    box-shadow:
        0 2px 6px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.ap-role-icon--warning {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 1px solid rgba(217, 119, 6, 0.35);
    color: #b45309;
    box-shadow:
        0 2px 8px rgba(217, 119, 6, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.ap-role-icon--brand {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border: 1px solid rgba(2, 132, 199, 0.35);
    color: #0284c7;
    box-shadow:
        0 2px 8px rgba(2, 132, 199, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.ap-role-icon--purple {
    background: linear-gradient(135deg, #faf5ff, #f3e8ff);
    border: 1px solid rgba(124, 58, 237, 0.35);
    color: #7e22ce;
    box-shadow:
        0 2px 8px rgba(124, 58, 237, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.ap-peran-nama {
    font-size: 0.8125rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.35;
    letter-spacing: -0.015em;
}

.ap-peran-meta-row {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    color: var(--hcis-text-secondary, #64748b);
    line-height: 1.25;
}

.ap-peran-kode {
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 600;
}

.ap-peran-modul {
    color: var(--hcis-text-muted, #94a3b8);
}

.ap-wz-radio-dot {
    width: 0.95rem;
    height: 0.95rem;
    border-radius: 50%;
    border: 1.5px solid var(--hcis-slate-300, #cbd5e1);
    background: #ffffff;
    transition: all 0.15s ease;
}

.wz-peran:hover .ap-wz-radio-dot {
    border-color: var(--hcis-brand-400, #38bdf8);
}

/* Pilihan peran banyak: indikator kotak, kartu yang tak bisa digabung diredupkan. */
.wz-kotak {
    border-radius: var(--hcis-radius-xs, 4px);
}
.ap-role-card-item.is-disabled {
    cursor: not-allowed;
}
/* Isi kartu diredupkan, keterangan "tugaskan terpisah" tetap terbaca. */
.ap-role-card-item.is-disabled .ap-role-icon,
.ap-role-card-item.is-disabled .ap-peran-nama,
.ap-role-card-item.is-disabled .ap-peran-meta-row,
.ap-role-card-item.is-disabled :deep(.hcis-badge),
.ap-role-card-item.is-disabled .ap-wz-radio-dot {
    opacity: 0.45;
}
.wz-hint-terpisah {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-1, 0.25rem);
    margin-top: var(--hcis-space-1, 0.25rem);
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--hcis-text-muted);
}
.ap-role-card-item.is-disabled:hover {
    transform: none;
}
.wz-intip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.45rem;
    padding: 0.2rem 0.55rem;
    border-radius: var(--hcis-radius-md, 6px);
    border: 1px solid rgb(var(--hcis-brand-500-rgb, 14 165 233) / 0.25);
    background: rgb(var(--hcis-brand-500-rgb, 14 165 233) / 0.08);
    font-size: 0.6875rem;
    font-weight: 650;
    color: var(--hcis-brand-600, #0284c7);
    cursor: pointer;
    transition: all 0.15s ease;
}
.wz-intip:hover {
    background: rgb(var(--hcis-brand-500-rgb, 14 165 233) / 0.16);
    border-color: var(--hcis-brand-500, #0284c7);
    color: var(--hcis-brand-700, #0369a1);
}
.wz-intip.is-active {
    background: var(--hcis-brand-600, #0284c7);
    color: #ffffff;
    border-color: var(--hcis-brand-600, #0284c7);
}
.ap-drawer-custom-header {
    display: flex;
    flex-direction: column;
}

.ap-drawer-icon-box {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md, 8px);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ap-drawer-icon-box--neutral {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.ap-drawer-icon-box--primary {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.ap-drawer-icon-box--purple {
    background: #faf5ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
}

.ap-drawer-icon-box--warning {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}

.ap-drawer-title-heading {
    font-size: 1rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.3;
    letter-spacing: -0.015em;
}

.ap-drawer-meta-strip {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    background: rgba(248, 250, 252, 0.85);
    border-radius: var(--hcis-radius-md, 6px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    font-size: 0.75rem;
}

.ap-drawer-meta-code {
    font-weight: 650;
    color: var(--hcis-brand-700, #0369a1);
}

.ap-drawer-meta-divider {
    color: #cbd5e1;
}

.ap-drawer-meta-item {
    color: var(--hcis-text-secondary, #475569);
    font-weight: 500;
}

.ap-drawer-badge-lama {
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    background: #fef3c7;
    color: #92400e;
    font-weight: 600;
    font-size: 0.6875rem;
}

.ap-drawer-footer-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.ap-drawer-footer-status {
    flex: 1;
    min-width: 14rem;
}

.ap-step-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.5rem;
    border-radius: var(--hcis-radius-md, 6px);
    background: #f1f5f9;
    color: #475569;
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1.25;
}
.ap-step-pill--target {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
    box-shadow: 0 1px 3px rgba(245, 158, 11, 0.15);
}
.wz-bilah-peran {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--hcis-space-2, 0.5rem);
    flex-wrap: wrap;
}

/* ══ DETAIL STEP SECTIONS (STEP 3) ══ */
.ap-wz-form-section {
    display: flex;
    flex-direction: column;
    padding: 1rem 1.15rem;
    background: #ffffff;
    border: 1px solid rgb(var(--hcis-slate-300-rgb, 203 213 225) / 0.85);
    border-radius: var(--hcis-radius-xl, 12px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.min-w-0 {
    min-width: 0;
}
</style>

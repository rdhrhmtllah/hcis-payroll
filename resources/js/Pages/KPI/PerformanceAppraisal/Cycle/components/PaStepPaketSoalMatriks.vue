<template>
    <div class="pa-step-root">
        <!-- Main Step 2 Container -->
        <div class="pa-step-container">
            <!-- Step Header Banner -->
            <div class="pa-step-header">
                <div class="pa-step-header-identity">
                    <span class="pa-step-kicker">STEP 2 OF 5 · QUESTION PACKAGES &amp; RATER CATEGORIES</span>
                    <h2 class="pa-step-title">
                        <HcisIcon name="ui-checks-grid" size="sm" color="brand" />
                        <span>Pemetaan Paket Soal per Level × Rater</span>
                    </h2>
                    <p class="pa-step-description">
                        Tentukan paket soal evaluasi kinerja dari Bank Soal HC Learn untuk setiap jenjang level jabatan dan kategori rater 360.
                        Sel tanpa paket soal akan menghasilkan formulir kosong saat cycle penilaian dimulai.
                    </p>
                </div>

                <div class="pa-step-header-actions">
                    <!-- Quick Bulk Action: Salin Semua Usulan Master Baku -->
                    <HcisButton
                        v-if="dapatDisunting && totalUsulanTersedia > 0"
                        :label="`Terapkan Semua Usulan Baku (${totalUsulanTersedia} Sel)`"
                        variant="primary"
                        size="sm"
                        leading-icon="sparkles"
                        :loading="menyalinMaster"
                        title="Terapkan seluruh konfigurasi paket baku dari Master Data ke sel yang belum bersoal"
                        @click="salinSemuaDariMaster"
                    />

                    <!-- Fallback auto-picker if catalog exists but master has no recommendation -->
                    <HcisButton
                        v-else-if="dapatDisunting && totalLubang > 0 && katalog.paket?.length"
                        label="Pasang Paket Standar Otomatis"
                        variant="secondary"
                        size="sm"
                        leading-icon="magic"
                        :loading="memasangOtomatis"
                        title="Terapkan paket soal bawaan pertama dari katalog ke semua sel yang masih kosong"
                        @click="pasangOtomatisSemua"
                    />

                    <!-- Satu bentuk di semua langkah: "Lihat Master ...". -->
                    <HcisButton
                        href="/performance-appraisal/master/ikatan-paket-soal"
                        label="Lihat Master Matriks Soal"
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
                </div>
            </div>

            <!-- Lock Alert -->
            <HcisAlert
                v-if="!dapatDisunting"
                variant="warning"
                appearance="subtle"
                :title="alasanTerkunci || 'Langkah ini sedang terkunci untuk penyuntingan.'"
                icon="lock-fill"
            />

            <!-- Error State -->
            <HcisResultState
                v-else-if="error && !memuat"
                status="danger"
                layout="compact"
                title="Matriks paket soal tidak dapat dimuat"
                :description="error"
                primary-text="Coba Lagi"
                primary-icon="arrow-clockwise"
                @primary-click="muat"
            />

            <!-- Loading Skeleton -->
            <div v-else-if="memuat" class="pa-step-skeleton">
                <div class="pa-skel-cockpit">
                    <HcisSkeleton v-for="n in 4" :key="`skel-m-${n}`" height="4.5rem" rounded="md" />
                </div>
                <HcisSkeleton width="100%" height="16rem" rounded="md" class="mt-3" />
            </div>

            <!-- Empty Rules State -->
            <HcisEmptyState
                v-else-if="!level.length"
                title="Belum ada level dinilai untuk cycle ini"
                description="Matriks ini menurunkan barisnya dari aturan rater cycle. Pastikan langkah 1 (Pillar Weights) telah disimpan atau cycle memiliki level aktif."
                icon="diagram-3"
            />

            <!-- Loaded Matrix Content -->
            <div v-else class="pa-matriks-content">
                <!-- 4-Metric Cockpit Summary -->
                <div class="pa-cockpit-grid">
                    <div class="pa-cockpit-stat">
                        <span class="pa-cockpit-kicker">EVALUATED LEVELS</span>
                        <strong>{{ level.length }} Level Jabatan</strong>
                        <small class="text-slate-500">Jumlah jenjang aktif dinilai</small>
                    </div>
                    <div class="pa-cockpit-stat">
                        <span class="pa-cockpit-kicker">RATER CATEGORIES</span>
                        <strong>{{ kategori.length }} Kategori Rater</strong>
                        <small class="text-slate-500">Atasan, Peer, Bawahan, Diri</small>
                    </div>
                    <div class="pa-cockpit-stat" :class="{ 'has-holes': totalLubang > 0, 'is-complete': totalLubang === 0 }">
                        <span class="pa-cockpit-kicker">PACKAGE READINESS</span>
                        <strong v-if="totalLubang === 0" class="text-emerald-600">
                            <HcisIcon name="check-circle-fill" size="xs" /> 100% Terpetakan
                        </strong>
                        <strong v-else class="text-amber-600">
                            <HcisIcon name="exclamation-triangle-fill" size="xs" /> {{ totalLubang }} Sel Belum Bersoal
                        </strong>
                        <small v-if="totalLubang === 0" class="text-emerald-600">Semua formulir rater siap diisi</small>
                        <small v-else class="text-amber-700 font-semibold">{{ totalUsulanTersedia > 0 ? `${totalUsulanTersedia} sel siap diisi usulan baku` : 'Petakan paket soal pada sel di bawah' }}</small>
                    </div>
                    <div class="pa-cockpit-stat" :class="{ 'has-custom': totalDisesuaikan > 0 }">
                        <span class="pa-cockpit-kicker">ALIGNMENT WITH MASTER</span>
                        <strong v-if="totalDisesuaikan === 0" class="text-indigo-600">
                            <HcisIcon name="shield-check" size="xs" /> 100% Sesuai Master
                        </strong>
                        <strong v-else class="text-blue-600">
                            <HcisIcon name="pencil-fill" size="xs" /> {{ totalDisesuaikan }} Sel Disesuaikan
                        </strong>
                        <small>{{ totalDisesuaikan === 0 ? 'Mengikuti susunan master baku' : 'Sengaja disesuaikan untuk cycle ini' }}</small>
                    </div>
                </div>

                <!-- Actionable Notice: Ada Usulan Master yang bisa disalin 1-klik -->
                <div v-if="dapatDisunting && totalUsulanTersedia > 0" class="pa-usulan-banner">
                    <div class="pa-usulan-banner-text">
                        <HcisIcon name="sparkles" size="sm" color="brand" />
                        <div>
                            <strong>Tersedia {{ totalUsulanTersedia }} usulan paket soal baku dari Master Data</strong>
                            <p>Anda dapat menerapkan usulan master pada masing-masing sel, atau salin seluruh usulan sekaligus dalam 1 klik.</p>
                        </div>
                    </div>
                    <HcisButton
                        :label="`Terapkan Semua (${totalUsulanTersedia} Sel)`"
                        variant="primary"
                        size="xs"
                        leading-icon="check-all"
                        :loading="menyalinMaster"
                        @click="salinSemuaDariMaster"
                    />
                </div>

                <!-- Info Notice when there are holes without proposal -->
                <!--
                    Usulan master yang GUGUR. Menunjuk ke Master Data, bukan ke siklus ini:
                    yang perlu dibereskan ada di sana. Ditaruh sebelum peringatan lubang
                    karena ia SEBAB dari sebagian lubangnya.
                -->
                <HcisAlert
                    v-if="usulanGugur.length"
                    variant="warning"
                    appearance="glass"
                    :title="`${usulanGugur.length} usulan master sudah tidak cocok dengan dimensi levelnya`"
                    icon="exclamation-triangle-fill"
                >
                    <p class="pa-gugur-desc">
                        Sel-sel ini harus diisi manual sampai masternya dibereskan di
                        <strong>Master Data &rsaquo; Paket Soal Baku</strong>.
                    </p>
                    <ul class="pa-gugur-list">
                        <li v-for="(g, i) in usulanGugur" :key="i">
                            <strong>{{ g.level }} × {{ g.kategori }}</strong> — {{ g.alasan }}
                        </li>
                    </ul>
                </HcisAlert>

                <HcisAlert
                    v-if="totalLubang > 0 && totalUsulanTersedia === 0"
                    variant="warning"
                    appearance="subtle"
                    :title="`${totalLubang} Kombinasi Level × Rater Belum Memiliki Paket Soal`"
                    icon="exclamation-triangle-fill"
                >
                    Rater pada sel yang belum terpetakan akan membuka formulir penilaian kosong tanpa pertanyaan. Klik sel bertanda kuning di bawah untuk memilih paket dari katalog HC Learn.
                </HcisAlert>

                <!-- Matrix Table Container (Contained & Zero-Overflow) -->
                <div class="pa-matrix-table-wrap-card">
                    <div class="pa-matrix-table-wrap">
                        <table class="pa-matrix-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="pa-matrix-th-level">
                                        <div class="pa-th-label">
                                            <HcisIcon name="layers-fill" size="2xs" />
                                            <span>Level Jabatan Dinilai</span>
                                        </div>
                                    </th>
                                    <th
                                        v-for="k in kategori"
                                        :key="k.kode"
                                        scope="col"
                                        class="pa-matrix-th-rater"
                                    >
                                        <div class="pa-th-label">
                                            <HcisIcon :name="getRaterIcon(k.kode)" size="2xs" />
                                            <span>{{ formatKategori(k.nama, k.kode) }}</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="l in level" :key="l.id_level" class="pa-matrix-row">
                                    <!-- Level Identity Header -->
                                    <th scope="row" class="pa-matrix-td-level">
                                        <div class="pa-level-cell-meta">
                                            <div class="pa-level-name-wrap">
                                                <strong>{{ l.nama_level }}</strong>
                                                <span class="pa-level-id-badge">ID: {{ l.id_level }}</span>
                                            </div>
                                            <!--
                                                `paket_bawaan` adalah paket SELEVEL — baris
                                                ber-rater NULL yang dipakai kategori mana pun
                                                yang tidak punya paketnya sendiri.

                                                Ia OPSIONAL, dan justru tidak dipakai pada
                                                susunan yang diminta HC: soal dibedakan per
                                                kategori rater, jadi tiap sel punya
                                                paketnya sendiri dan bawaan selevel tidak
                                                pernah terpanggil.

                                                Pesan "Belum ada paket bawaan" karena itu
                                                menyesatkan — ia terbaca seperti ada yang
                                                kurang, padahal keadaannya benar. Yang
                                                menentukan lengkap atau tidak adalah `lubang`,
                                                bukan ada-tidaknya bawaan.
                                            -->
                                            <small v-if="l.paket_bawaan" class="pa-level-bawaan">
                                                <HcisIcon name="bookmark-check" size="2xs" />
                                                Bawaan selevel: {{ l.paket_bawaan.kode_paket }}
                                                ({{ l.paket_bawaan.jumlah_butir }} bagian berlabel)
                                            </small>
                                            <small v-else-if="l.lubang === 0" class="pa-level-bawaan">
                                                <HcisIcon name="check-circle-fill" size="2xs" />
                                                Paket diatur per rater
                                            </small>
                                            <small v-else class="pa-level-bawaan is-empty">
                                                {{ l.lubang }} sel rater belum bersoal
                                            </small>
                                        </div>
                                    </th>

                                    <!-- Rater Cells -->
                                    <td
                                        v-for="k in kategori"
                                        :key="k.kode"
                                        class="pa-matrix-td-cell"
                                    >
                                        <!-- Case 1: Category Does NOT evaluate this level -->
                                        <div
                                            v-if="!l.sel[k.kode]?.ikut_menilai"
                                            class="pa-matrix-cell--na"
                                            title="Kategori ini tidak menilai level ini menurut aturan rater cycle."
                                        >
                                            <span>— Tidak Menilai —</span>
                                        </div>

                                        <!-- Case 2: Mapped Package (Filled) -->
                                        <button
                                            v-else-if="l.sel[k.kode]?.paket_id"
                                            type="button"
                                            class="pa-matrix-cell-btn"
                                            :class="{
                                                'is-custom': l.sel[k.kode]?.khusus || l.sel[k.kode]?.sesuai_master === false,
                                                'is-default': !l.sel[k.kode]?.khusus && l.sel[k.kode]?.sesuai_master !== false,
                                            }"
                                            :disabled="!dapatDisunting"
                                            :title="`Klik untuk mengganti paket soal (${l.sel[k.kode].kode_paket})`"
                                            @click="bukaPilih(l, k)"
                                        >
                                            <div class="pa-cell-head">
                                                <span class="pa-cell-code">{{ l.sel[k.kode].kode_paket }}</span>
                                                <HcisBadge
                                                    v-if="l.sel[k.kode].sesuai_master === false"
                                                    label="Disesuaikan"
                                                    variant="info"
                                                    size="2xs"
                                                />
                                                <HcisBadge
                                                    v-else
                                                    :label="l.sel[k.kode].khusus ? 'Khusus' : 'Baku'"
                                                    :variant="l.sel[k.kode].khusus ? 'brand' : 'neutral'"
                                                    size="2xs"
                                                />
                                            </div>
                                            <div class="pa-cell-sub">
                                                <HcisIcon name="card-checklist" size="2xs" />
                                                <span>{{ l.sel[k.kode].jumlah_butir }} bagian berlabel</span>
                                            </div>
                                        </button>

                                        <!-- Case 3: Empty Cell WITH Master Recommendation (Smart Usulan Card) -->
                                        <!--
                                            Usulan master GUGUR: divalidasi ulang setiap kali
                                             matriks dibaca, dan sudah tidak cocok dengan
                                             dimensi kompetensi level ini — entah paketnya
                                             berubah di HC Learn, atau dimensi levelnya
                                             ditambah di Master Data.

                                            Ditampilkan KOSONG, bukan tetap diusulkan:
                                            menampilkannya berarti admin menekan "Pakai
                                            usulan" lalu ditolak, dan menyimpulkan sistemnya
                                            rusak. Sebabnya tetap disebut lewat title supaya
                                            ia tahu MASTERNYA yang perlu dibereskan.
                                        -->
                                        <!-- Tetap BISA DIKLIK: "isi manual" harus benar-benar bisa diisi (UAT 13 Okt). -->
                                        <button
                                            v-else-if="l.sel[k.kode].usulan_master_gugur"
                                            type="button"
                                            class="pa-cell-gugur"
                                            :disabled="!dapatDisunting"
                                            :title="`${l.sel[k.kode].usulan_master_gugur} Klik untuk memilih paket soal secara manual.`"
                                            :data-uji="`sel-gugur-${l.id_level}-${k.kode}`"
                                            @click="bukaPilih(l, k)"
                                        >
                                            <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                                            <span>Isi manual</span>
                                        </button>

                                        <div
                                            v-else-if="l.sel[k.kode].usulan_master"
                                            class="pa-cell-proposal-card"
                                        >
                                            <div
                                                class="pa-proposal-content"
                                                title="Klik untuk memilih paket lain dari katalog"
                                                @click="bukaPilih(l, k)"
                                            >
                                                <div class="pa-proposal-kicker">
                                                    <HcisIcon name="sparkles" size="2xs" color="purple" />
                                                    <span>USULAN MASTER</span>
                                                </div>
                                                <strong class="pa-proposal-code">{{ l.sel[k.kode].usulan_master.kode_paket }}</strong>
                                                <span class="pa-proposal-meta">{{ l.sel[k.kode].usulan_master.jumlah_bagian }} bagian ({{ l.sel[k.kode].usulan_master.jumlah_kompetensi }} kompetensi)</span>
                                            </div>

                                            <button
                                                type="button"
                                                class="pa-proposal-apply-btn"
                                                :disabled="!dapatDisunting || menerimaUsulan === `${l.id_level}|${k.kode}`"
                                                title="Pasang paket usulan baku ini langsung ke sel"
                                                @click.stop="terimaUsulan(l, k)"
                                            >
                                                <HcisIcon
                                                    v-if="menerimaUsulan === `${l.id_level}|${k.kode}`"
                                                    name="arrow-clockwise"
                                                    animation="spin"
                                                    size="2xs"
                                                />
                                                <HcisIcon v-else name="check-lg" size="2xs" />
                                                <span>{{ menerimaUsulan === `${l.id_level}|${k.kode}` ? 'Memasang...' : 'Pakai Usulan' }}</span>
                                            </button>
                                        </div>

                                        <!-- Case 4: Empty Cell WITHOUT Master Recommendation (Hole) -->
                                        <button
                                            v-else
                                            type="button"
                                            class="pa-matrix-cell-btn is-hole"
                                            :disabled="!dapatDisunting"
                                            title="Klik untuk memilih paket soal untuk kombinasi ini"
                                            @click="bukaPilih(l, k)"
                                        >
                                            <div class="pa-cell-hole-state">
                                                <HcisIcon name="plus-circle" size="xs" color="warning" />
                                                <span>Pilih Paket Soal</span>
                                            </div>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer Navigation & Actions -->
            <div class="pa-step-footer">
                <div class="pa-footer-feedback">
                    <span v-if="pesan" class="pa-step-message" :class="{ 'is-error': pesanError }">
                        <HcisIcon :name="pesanError ? 'exclamation-circle-fill' : 'check-circle-fill'" size="2xs" />
                        <span>{{ pesan }}</span>
                    </span>
                    <span v-else class="pa-footer-hint">
                        Pastikan seluruh sel yang aktif telah terpetakan sebelum melangkah ke tahap Aturan Rater (Rater Rules).
                    </span>
                </div>

                <div class="pa-step-nav-buttons">
                    <HcisButton
                        label="Kembali ke Langkah 1: Pillar Weights"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-left"
                        @click="$emit('kembali')"
                    />
                    <HcisButton
                        label="Lanjut ke Langkah 3: Rater Rules"
                        variant="primary"
                        size="sm"
                        trailing-icon="arrow-right"
                        :loading="memprosesLanjut"
                        @click="lanjut"
                    />
                </div>
            </div>
        </div>

        <!-- Dialog: Pilih & Impor Paket Soal dari Katalog (Spacious 2XL) -->
        <HcisDialog
            v-model="dialog"
            :title="judulDialog"
            kicker="KATALOG BANK SOAL · HC LEARN"
            icon="ui-checks"
            sentiment="brand"
            size="2xl"
        >
            <PaPaketSoalPicker
                :paket="katalog.paket"
                :kompetensi-wajib="sasaran.level?.kompetensi_wajib ?? []"
                :nama-sasaran="sasaran.level ? `${sasaran.level.nama_level} × ${sasaran.kategori?.nama || 'Kategori'}` : ''"
                :kode-terpilih="sasaran.level?.sel?.[sasaran.kategori?.kode]?.kode_paket || ''"
                :dipakai-sebaris="dipakaiSebaris"
                :label-bagian="labelBagian"
                :sumber="katalog.sumber"
                :memuat="memuatKatalog"
                :sibuk="mengimporId"
                @pilih="impor"
            />

            <template #footer>
                <div class="pa-dialog-footer-actions">
                    <span class="text-xs text-slate-500">
                        Pilih paket soal yang sesuai dengan kompetensi level jabatan di atas.
                    </span>
                    <HcisButton variant="secondary" label="Tutup" size="sm" @click="dialog = false" />
                </div>
            </template>
        </HcisDialog>
    </div>

    <!--
        Perpindahan DITAHAN, tanpa jalan keluar "lanjut saja".

        Sel yang menilai tetapi belum bersoal membuat penilainya membuka formulir kosong —
        tanpa galat, tanpa pesan — dan itu baru ketahuan setelah pengisian dibuka. Karena itu
        dialognya hanya menawarkan cara menyelesaikannya, bukan cara melewatinya.
    -->
    <HcisDialog
        v-model="dialogTertahan"
        title="Masih ada sel rater yang belum bersoal"
        icon="shield-exclamation"
        sentiment="warning"
        variant="glass"
        size="md"
    >
        <p class="pa-tertahan-desc">
            {{ selTertahan.length }} pasangan level × rater belum punya paket soal. Rater
            pada sel itu akan membuka formulir kosong tanpa satu pun pertanyaan, dan itu baru
            ketahuan setelah pengisian dibuka.
        </p>

        <ul class="pa-tertahan-list">
            <li v-for="(x, i) in selTertahan" :key="i">
                <strong>{{ x.level }} × {{ x.kategori }}</strong>
                <span>{{ x.sebab }}</span>
            </li>
        </ul>

        <p class="pa-tertahan-jalan">
            Selesaikan dengan memilih paketnya langsung pada sel yang bertanda, atau bereskan
            pilihan bakunya di <strong>Master Data &rsaquo; Paket Soal Baku</strong>.
        </p>

        <template #footer>
            <HcisButton
                variant="primary"
                label="Tutup dan lengkapi"
                size="sm"
                @click="dialogTertahan = false"
            />
        </template>
    </HcisDialog>

        <!--
            KONFIRMASI PENGGANTIAN PAKET SEL.

            Layar lama menjanjikan "klik untuk mengganti paket soal" pada sel terisi, tetapi
            server menolaknya dan menyuruh "nonaktifkan yang lama" — tindakan yang tidak ada
            di layar mana pun. Jalannya buntu: sel yang sudah terisi tidak bisa ditukar sama
            sekali.

            Sekarang penggantiannya ada, dan justru karena ada ia harus disetujui: seluruh
            penilai di sel itu berpindah ke soal yang berbeda.
        -->
        <HcisDialog
            :model-value="gantiPaket !== null"
            title="Ganti paket soal sel ini?"
            kicker="LANGKAH 2 · PAKET SOAL"
            icon="arrow-left-right"
            sentiment="warning"
            size="md"
            @update:model-value="gantiPaket = null"
        >
            <div v-if="gantiPaket" class="pa-matriks-ganti">
                <p>
                    <strong>{{ sasaran.level?.nama_level }} &times; {{ sasaran.kategori?.nama || 'Bawaan' }}</strong>
                    sekarang memakai <strong>{{ paketSelSasaran }}</strong>.
                </p>
                <p>
                    Paketnya akan ditukar dengan <strong>{{ gantiPaket.Id_Master_Ujian }}</strong>
                    ({{ gantiPaket.Nama_Ujian }}). Butir paket lama dinonaktifkan — riwayatnya
                    tetap tersimpan, tetapi seluruh penilai di sel ini mengerjakan soal yang baru.
                </p>
                <p class="pa-matriks-ganti-catatan">
                    Penggantian ditolak bila penilai di sel ini sudah dijadwalkan: tautan yang
                    sudah dibagikan menunjuk soal yang lama.
                </p>
            </div>

            <template #footer>
                <div class="pa-dialog-footer-actions">
                    <HcisButton variant="ghost" label="Batal" @click="gantiPaket = null" />
                    <HcisButton
                        variant="primary"
                        leading-icon="arrow-left-right"
                        label="Ganti Paket"
                        :loading="mengimporId === gantiPaket?.Id_Master_Ujian"
                        @click="impor(gantiPaket, true)"
                    />
                </div>
            </template>
        </HcisDialog>

        <!--
            PELABELAN BAGIAN → KOMPETENSI, DI LANGKAH 2.

            Komponennya sama persis dengan yang dipakai halaman master — bukan salinan.
            Dua layar yang menanyakan hal yang sama dengan dua tampilan berbeda akan
            berbeda pula aturannya suatu hari, dan yang berbeda diam-diam adalah pemetaan
            yang menentukan nilai orang mendarat di kompetensi mana.
        -->
        <HcisDialog
            :model-value="pelabelan !== null"
            title="Labeli bagian soal ke kompetensi"
            kicker="LANGKAH 2 · IKATAN PAKET SOAL"
            icon="diagram-3-fill"
            sentiment="brand"
            size="3xl"
            @update:model-value="pelabelan = null"
        >
            <HcisAlert
                v-if="pelabelan?.acuanDari"
                variant="warning"
                title="Isian awal disalin dari sel sebaris"
                class="pa-matriks-label-nota"
            >
                Belum ada pemetaan untuk sel ini, jadi label di bawah disalin dari
                <strong>{{ pelabelan.acuanDari.nama }}</strong> yang memakai ujian dan level
                yang sama. Kompetensi wajib ditentukan per level, jadi pemetaannya semestinya
                identik &mdash; <strong>periksa dulu</strong>, lalu simpan. Yang tersimpan
                menjadi pemetaan milik sel ini sendiri, bukan pinjaman.
            </HcisAlert>

            <HcisAlert
                variant="info"
                title="Pelabelan ini tersimpan sebagai MASTER"
                class="pa-matriks-label-nota"
            >
                Bagian soal tidak membawa kompetensi dari HC Learn, jadi pemetaannya hidup di
                Master Data &mdash; bukan salinan siklus ini. Siklus berikutnya yang memakai
                ujian dan sel yang sama akan memakai pemetaan ini juga.
            </HcisAlert>

            <PaLabelBagianDialog
                v-if="pelabelan"
                :id-level="sasaran.level?.id_level ?? 0"
                :nama-level="sasaran.level?.nama_level || ''"
                :rater-category-id="sasaran.kategori?.id || null"
                :nama-kategori="sasaran.kategori?.nama || 'Bawaan'"
                :id-master-ujian="pelabelan.paket.Id_Master_Ujian"
                :nama-ujian="pelabelan.namaUjian"
                :nama-paket="pelabelan.namaPaket"
                :bagian="pelabelan.bagian"
                :kompetensi-wajib="pelabelan.kompetensiWajib"
                :label-awal="pelabelan.labelAwal"
                :menyimpan="menyimpanLabel"
                @simpan="simpanLabel"
                @batal="pelabelan = null"
            />
        </HcisDialog>
</template>

<script setup>
import { computed, onMounted, reactive, ref, shallowRef } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisDialog,
    HcisEmptyState,
    HcisIcon,
    HcisResultState,
    HcisSkeleton,
} from '@/components/ui';
import PaPaketSoalPicker from '../../components/PaPaketSoalPicker.vue';
import PaLabelBagianDialog from '../../MasterData/components/PaLabelBagianDialog.vue';
import paApi from '../../api/paApi.js';
import { pesanGagalSebagian } from '../../utils/simpanBertahap.js';

defineOptions({ name: 'PaStepPaketSoalMatriks' });

const props = defineProps({
    cycleId: { type: [Number, String], required: true },
    dapatDisunting: { type: Boolean, default: false },
    alasanTerkunci: { type: String, default: '' },
});

const emit = defineEmits(['tersimpan', 'kembali', 'lanjut']);

const kategori = shallowRef([]);
const level = shallowRef([]);
const totalLubang = ref(0);
const totalDisesuaikan = ref(0);

const memuat = ref(false);
const error = ref('');
const pesan = ref('');
const pesanError = ref(false);

const memasangOtomatis = ref(false);
const menyalinMaster = ref(false);
const menerimaUsulan = ref(null);

/**
 * Usulan master yang GUGUR — sudah tidak cocok dengan dimensi kompetensi levelnya.
 *
 * Server memvalidasinya setiap kali matriks dibaca; `usulan_master_gugur` berisi sebabnya
 * dan `usulan_master` dikosongkan. Diratakan jadi satu daftar karena sebabnya hanya terbaca
 * kalau tiap sel ditunjuk satu per satu, dan yang tidak terbaca tidak akan diperbaiki.
 */
const memprosesLanjut = ref(false);
const dialogTertahan = ref(false);

/**
 * Sel yang MENILAI tetapi belum bersoal, beserta sebab kenapa belum bisa diisi otomatis.
 *
 * Dipakai penjaga perpindahan. Sel yang kategorinya tidak menilai TIDAK masuk — ia memang
 * tidak boleh terisi, dan menahan admin karenanya membuat langkah ini tidak akan pernah
 * bisa dilewati.
 */
const selTertahan = computed(() =>
    (level.value ?? []).flatMap((l) =>
        Object.entries(l.sel ?? {})
            .filter(([, sel]) => sel?.ikut_menilai && !sel?.paket_id)
            .map(([kode, sel]) => ({
                level: l.nama_level,
                kategori: kode,
                sebab: sel.usulan_master_gugur
                    ? sel.usulan_master_gugur
                    : (sel.usulan_master
                        ? 'Usulan master tersedia tetapi belum diterapkan.'
                        : 'Master belum punya pilihan baku untuk sel ini.'),
            })),
    ),
);

/**
 * Berpindah ke Langkah 3, dengan dua tahap.
 *
 * PERTAMA menerapkan seluruh usulan master yang masih sahih ke sel yang kosong. Berbeda
 * dengan Langkah 1 dan 3 — di sana yang menggantung adalah angka yang mungkin sedang
 * dicoba-coba admin, jadi menyimpannya otomatis bisa mengunci sesuatu yang tak ia maksudkan.
 * Di sini yang diterapkan adalah rekomendasi master yang sudah TERPAMPANG di selnya, dan
 * menerapkannya tidak menghasilkan pilihan yang belum pernah dilihat admin.
 *
 * KEDUA menahan perpindahan bila masih ada sel yang menilai tetapi belum bersoal. Penilai
 * pada sel itu akan membuka formulir kosong — tanpa galat, tanpa pesan, hanya layar tanpa
 * pertanyaan — dan itu baru ketahuan setelah pengisian dibuka.
 *
 * Tidak ada jalan keluar "lanjut saja". Yang tertahan selalu bisa diselesaikan: pilih
 * paketnya sendiri di sel itu, atau bereskan masternya. Dialognya menyebut yang mana.
 */
async function lanjut() {
    memprosesLanjut.value = true;
    pesan.value = '';

    try {
        // Menerapkan usulan hanya bila ADA yang bisa diterapkan; memanggilnya saat tidak
        // ada apa-apa menghasilkan satu permintaan yang pasti tidak mengubah apa pun.
        const adaUsulan = selTertahan.value.some(
            (x) => x.sebab === 'Usulan master tersedia tetapi belum diterapkan.',
        );

        if (adaUsulan) {
            const response = await paApi.salinPaketSoalDariMaster(props.cycleId);
            const d = response?.result ?? response?.data ?? response;

            pesan.value = d?.dilewati?.length
                ? `${d.disalin} sel diisi dari master, ${d.dilewati.length} dilewati.`
                : `${d?.disalin ?? 0} sel diisi dari master.`;

            await muat();
            emit('tersimpan');
        }

        if (selTertahan.value.length > 0) {
            dialogTertahan.value = true;

            return;
        }

        emit('lanjut');
    } catch (e) {
        pesan.value = e?.message || 'Gagal menerapkan usulan master.';
    } finally {
        memprosesLanjut.value = false;
    }
}

const usulanGugur = computed(() =>
    (level.value ?? []).flatMap((l) =>
        Object.entries(l.sel ?? {})
            .filter(([, sel]) => sel?.usulan_master_gugur)
            .map(([kode, sel]) => ({
                level: l.nama_level,
                kategori: kode,
                alasan: sel.usulan_master_gugur,
            })),
    ),
);
const mengimporId = ref(null);

const dialog = ref(false);
const memuatKatalog = ref(false);
const katalog = reactive({ sumber: '', paket: [] });
const sasaran = reactive({ level: null, kategori: null });

const totalUsulanTersedia = computed(() => {
    let count = 0;
    for (const lv of level.value) {
        for (const kat of kategori.value) {
            const sel = lv.sel?.[kat.kode];
            if (sel?.ikut_menilai && !sel?.paket_id && sel?.usulan_master) {
                count++;
            }
        }
    }
    return count;
});

const judulDialog = computed(() => {
    if (!sasaran.level) return 'Pilih Paket Soal HC Learn';
    return `Petakan Paket Soal: ${sasaran.level.nama_level} × ${sasaran.kategori?.nama || 'Kategori'}`;
});

function formatKategori(nama, kode) {
    const n = String(nama || '');
    const k = String(kode || '').toUpperCase();
    if (k === 'PEER' || n.toLowerCase().includes('sejawat') || n.toLowerCase().includes('rekan')) {
        return 'Peer';
    }
    return n;
}

function getRaterIcon(kode) {
    const k = String(kode || '').toUpperCase();
    if (k.includes('ATASAN')) return 'person-badge-fill';
    if (k.includes('SEJAWAT') || k.includes('PEER')) return 'people-fill';
    if (k.includes('BAWAHAN') || k.includes('SUBORDINATE')) return 'person-lines-fill';
    if (k.includes('DIRI') || k.includes('SELF')) return 'person-fill';
    return 'diagram-3-fill';
}

async function muat() {
    memuat.value = true;
    error.value = '';

    try {
        const response = await paApi.matriksPaketSoal(props.cycleId);
        const data = response?.result ?? response?.data ?? response;

        kategori.value = data?.kategori ?? [];
        level.value = data?.level ?? [];
        totalLubang.value = Number(data?.total_lubang ?? 0);
        totalDisesuaikan.value = Number(data?.total_disesuaikan ?? 0);
    } catch (e) {
        error.value = e?.message || 'Gagal memuat matriks paket soal.';
    } finally {
        memuat.value = false;
    }
}

async function muatKatalogBackground() {
    if (katalog.paket.length) return;
    try {
        const response = await paApi.katalogPaketSoal(props.cycleId);
        const data = response?.result ?? response?.data ?? response;
        katalog.sumber = data?.sumber ?? '';
        katalog.paket = data?.paket ?? [];
    } catch {
        // silent background fetch
    }
}

/* Mapping master untuk usulan sel ini — pratinjau bagian menampilkan kompetensinya. */
const labelBagian = ref(null);

async function muatLabelBagian(usulan) {
    labelBagian.value = null;
    if (!usulan?.id_ikatan) return;
    try {
        const r = await paApi.ambilLabelIkatan(usulan.id_ikatan);
        const baris = r?.result ?? r?.data ?? r ?? [];
        const peta = {};
        for (const d of Array.isArray(baris) ? baris : []) {
            peta[`${d.id_jenis_indikator ?? ''}|${d.id_jenis_soal ?? ''}`] = { kode: d.kode_kompetensi || '', nama: d.nama_kompetensi || '' };
        }
        labelBagian.value = { id_master_ujian: usulan.kode_paket, peta };
    } catch {
        /* Pratinjau saja. */
    }
}

async function bukaPilih(l, k) {
    sasaran.level = l;
    sasaran.kategori = k;
    dialog.value = true;
    muatLabelBagian(l.sel?.[k.kode]?.usulan_master);

    if (katalog.paket.length) return;

    memuatKatalog.value = true;
    try {
        const response = await paApi.katalogPaketSoal(props.cycleId);
        const data = response?.result ?? response?.data ?? response;
        katalog.sumber = data?.sumber ?? '';
        katalog.paket = data?.paket ?? [];
    } catch (e) {
        pesan.value = e?.message || 'Gagal memuat katalog paket soal.';
        pesanError.value = true;
        dialog.value = false;
    } finally {
        memuatKatalog.value = false;
    }
}

/**
 * Ujian yang sudah dipakai sel LAIN pada baris yang sama, beserta nama kategorinya.
 *
 * Dihitung dari matriks yang sudah ada di layar, bukan ditanyakan ke server: jawabannya
 * sudah ada di tangan, dan satu perjalanan lagi untuk hal yang sudah diketahui hanya
 * menambah tunggu saat dialog dibuka.
 */
const dipakaiSebaris = computed(() => {
    if (!sasaran.level) return {};

    const peta = {};

    for (const k of kategori.value) {
        const sel = sasaran.level.sel?.[k.kode];

        if (!sel?.terisi || !sel.kode_paket) continue;
        if (k.kode === sasaran.kategori?.kode) continue;

        peta[sel.kode_paket] = peta[sel.kode_paket]
            ? `${peta[sel.kode_paket]}, ${k.nama}`
            : k.nama;
    }

    return peta;
});

/**
 * Paket yang SEDANG dipakai sel sasaran, atau string kosong bila selnya masih kosong.
 */
const paketSelSasaran = computed(
    () => sasaran.level?.sel?.[sasaran.kategori?.kode]?.kode_paket || '',
);

/* Penggantian ditahan di sini, bukan dikirim diam-diam. Menukar paket soal satu sel
   berarti seluruh penilai di sel itu mengerjakan soal yang berbeda, dan butir lamanya
   ikut dinonaktifkan — satu klik terlalu murah untuk akibat sebesar itu. */
const gantiPaket = ref(null);

function mintaGanti(p) {
    gantiPaket.value = p;
}

/* PELABELAN DI TEMPAT.
   |
   | Sebelum ini, sel yang ujiannya belum dilabeli hanya bisa ditolak: admin diminta
   | berpindah ke Master Data > Ikatan Paket Soal, mencari sel yang sama, melabeli, lalu
   | kembali dan mengulang pilihannya. Empat perpindahan untuk satu keputusan yang sudah
   | ia ambil di layar ini.
   |
   | Yang ditulis tetap MASTER — pelabelan memang hidup di sana, dan tidak ada salinan
   | per-siklus untuknya. Dialognya menyebut itu apa adanya, karena akibatnya nyata:
   | siklus berikutnya yang memakai ujian dan sel yang sama ikut memakainya. */
const pelabelan = ref(null);
const menyimpanLabel = ref(false);

async function bukaPelabelan(p) {
    if (!sasaran.level) return;

    mengimporId.value = p.Id_Master_Ujian;
    pesan.value = '';
    pesanError.value = false;

    try {
        const response = await paApi.bagianPaketSoalSiklus(
            props.cycleId,
            sasaran.level.id_level,
            p.Id_Master_Ujian,
            sasaran.kategori?.id || null,
        );
        const d = response?.result ?? response?.data ?? response;

        pelabelan.value = {
            paket: p,
            bagian: d?.bagian ?? [],
            kompetensiWajib: d?.kompetensi_wajib ?? [],
            labelAwal: d?.label_awal ?? {},
            namaUjian: d?.nama_ujian ?? p.Nama_Ujian,
            namaPaket: d?.nama_paket ?? '',

            /* Null = isian awalnya milik sel ini sendiri. Berisi = SALINAN dari sel
               serumpun, dan itu harus terbaca — bukan muncul begitu saja. */
            acuanDari: d?.label_acuan_dari ?? null,
        };
    } catch (e) {
        pesan.value = e?.message || 'Gagal memuat bagian ujian.';
        pesanError.value = true;
    } finally {
        mengimporId.value = null;
    }
}

/* Melabeli lalu LANGSUNG mengimpor. Dipisah jadi dua tombol, admin yang baru saja
   melabeli tetap harus memilih ulang paketnya — dan itu justru perpindahan yang sedang
   dihapus di sini. */
async function simpanLabel(label) {
    if (!pelabelan.value || !sasaran.level) return;

    menyimpanLabel.value = true;
    pesan.value = '';
    pesanError.value = false;

    try {
        await paApi.labelPaketSoalSiklus(
            props.cycleId,
            sasaran.level.id_level,
            pelabelan.value.paket.Id_Master_Ujian,
            label,
            sasaran.kategori?.id || null,
        );

        const p = pelabelan.value.paket;
        pelabelan.value = null;

        await impor(p);
    } catch (e) {
        pesan.value = e?.message || 'Gagal menyimpan pelabelan.';
        pesanError.value = true;
    } finally {
        menyimpanLabel.value = false;
    }
}

async function impor(p, ganti = false) {
    if (!sasaran.level) return;

    /* Selnya sudah terisi paket LAIN: minta persetujuan dulu. Sama paketnya berarti tidak
       ada yang berubah, dan itu ditolak server dengan sebabnya sendiri. */
    if (!ganti && paketSelSasaran.value && paketSelSasaran.value !== p.Id_Master_Ujian) {
        mintaGanti(p);

        return;
    }

    gantiPaket.value = null;
    mengimporId.value = p.Id_Master_Ujian;
    pesan.value = '';
    pesanError.value = false;

    try {
        await paApi.imporPaketSoal(
            props.cycleId,
            sasaran.level.id_level,
            p.Id_Master_Ujian,
            sasaran.kategori?.id || null,
            ganti,
        );

        pesan.value = `Berhasil mengikat ${p.Id_Master_Ujian} (${p.Nama_Ujian}) ke ${sasaran.level.nama_level} × ${sasaran.kategori?.nama || 'Bawaan'}.`;
        dialog.value = false;

        await muat();
        emit('tersimpan');
    } catch (e) {
        const teks = e?.message || 'Gagal mengimpor paket soal.';

        /* Belum dilabeli BUKAN jalan buntu lagi: dialognya dibuka di sini juga, supaya
           admin yang menemukannya lewat tombol impor tidak perlu tahu bahwa pelabelan
           adalah langkah tersendiri. */
        if (/belum dipetakan ke kompetensi/i.test(teks)) {
            await bukaPelabelan(p);

            return;
        }

        pesan.value = teks;
        pesanError.value = true;
    } finally {
        mengimporId.value = null;
    }
}

async function terimaUsulan(l, k) {
    menerimaUsulan.value = `${l.id_level}|${k.kode}`;
    pesan.value = '';
    pesanError.value = false;

    try {
        const response = await paApi.terimaUsulanPaketSoal(props.cycleId, l.id_level, k.id);
        const d = response?.result ?? response?.data ?? response;

        pesan.value = `Berhasil memasang paket ${d?.kode_paket || l.sel[k.kode]?.usulan_master?.kode_paket} ke ${l.nama_level} × ${k.nama} dari usulan master.`;
        await muat();
        emit('tersimpan');
    } catch (e) {
        pesan.value = e?.message || 'Gagal menerima usulan master.';
        pesanError.value = true;
    } finally {
        menerimaUsulan.value = null;
    }
}

async function salinSemuaDariMaster() {
    menyalinMaster.value = true;
    pesan.value = '';
    pesanError.value = false;

    try {
        const response = await paApi.salinPaketSoalDariMaster(props.cycleId);
        const d = response?.result ?? response?.data ?? response;

        const tersalin = d?.disalin ?? totalUsulanTersedia.value;
        pesan.value = `Berhasil menerapkan ${tersalin} paket soal baku dari Master Data ke cycle ini.`;
        await muat();
        emit('tersimpan');
    } catch (e) {
        pesan.value = e?.message || 'Gagal menyalin paket soal dari master.';
        pesanError.value = true;
    } finally {
        menyalinMaster.value = false;
    }
}

async function pasangOtomatisSemua() {
    if (!katalog.paket.length) {
        await muatKatalogBackground();
    }
    const paketTersedia = katalog.paket.find((p) => p.bagian_tersedia !== false) || katalog.paket[0];
    if (!paketTersedia) {
        pesan.value = 'Tidak ada paket soal yang tersedia di katalog.';
        pesanError.value = true;
        return;
    }

    memasangOtomatis.value = true;
    pesan.value = '';
    pesanError.value = false;

    let suksesCount = 0;
    let sedang = '';
    const totalSel = level.value.reduce((n, lv) => n + kategori.value.filter((k) => lv.sel?.[k.kode]?.ikut_menilai && !lv.sel?.[k.kode]?.paket_id).length, 0);
    try {
        for (const lv of level.value) {
            for (const kat of kategori.value) {
                const sel = lv.sel?.[kat.kode];
                if (sel?.ikut_menilai && !sel?.paket_id) {
                    sedang = `${lv.nama_level || lv.id_level} × ${kat.nama || kat.kode}`;
                    await paApi.imporPaketSoal(
                        props.cycleId,
                        lv.id_level,
                        paketTersedia.Id_Master_Ujian,
                        kat.id || null,
                    );
                    suksesCount++;
                }
            }
        }

        pesan.value = `Berhasil memasang paket standar untuk ${suksesCount} sel level × penilai.`;
        await muat();
        emit('tersimpan');
    } catch (e) {
        pesan.value = pesanGagalSebagian({ tersimpan: suksesCount, total: totalSel, gagalDi: sedang, galat: e, satuan: 'sel' });
        pesanError.value = true;
        if (suksesCount > 0) {
            await muat();
            emit('tersimpan');
        }
    } finally {
        memasangOtomatis.value = false;
    }
}

onMounted(muat);
</script>

<style scoped>
.pa-step-root {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.pa-step-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

/* Step Header */
.pa-step-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.35rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    flex-wrap: wrap;
}

.pa-step-header-identity {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    max-width: 42rem;
}

.pa-step-kicker {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #4f46e5;
}

.pa-step-title {
    margin: 0;
    font-size: 1.12rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-step-description {
    margin: 0;
    font-size: 0.78rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.45;
}

.pa-step-header-actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-step-skeleton {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100%;
}

.pa-skel-cockpit {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}

.pa-matriks-content {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    width: 100%;
    min-width: 0;
}

/* 4-Metric Cockpit Summary */
.pa-cockpit-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
    width: 100%;
    box-sizing: border-box;
}

.pa-cockpit-stat {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    min-width: 0;
}

.pa-cockpit-kicker {
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: #4f46e5;
}

.pa-cockpit-stat strong {
    font-size: 0.95rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-cockpit-stat small {
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-cockpit-stat.is-complete {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.pa-cockpit-stat.has-holes {
    background: #fffbeb;
    border-color: #fde68a;
}

.pa-cockpit-stat.has-custom {
    background: #f8faff;
    border-color: #c7d2fe;
}

/* Actionable Usulan Banner */
.pa-usulan-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1.15rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #faf5ff;
    border: 1px solid #e9d5ff;
    flex-wrap: wrap;
}

.pa-usulan-banner-text {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-usulan-banner-text strong {
    font-size: 0.84rem;
    color: #6b21a8;
    display: block;
}

.pa-usulan-banner-text p {
    margin: 0;
    font-size: 0.72rem;
    color: #7e22ce;
}

/* Matrix Table Card Container */
.pa-matrix-table-wrap-card {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #cbd5e1);
    border-radius: var(--hcis-radius-md, 12px);
    overflow: hidden;
    box-sizing: border-box;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-matrix-table-wrap {
    overflow-x: auto;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
    -webkit-overflow-scrolling: touch;
}

.pa-matrix-table-wrap::-webkit-scrollbar {
    height: 5px;
}

.pa-matrix-table-wrap::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.pa-matrix-table-wrap::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.pa-matrix-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.pa-matrix-table thead th {
    padding: 0.65rem 0.75rem;
    background: #f8fafc;
    border-bottom: 2px solid var(--hcis-border-subtle, #e2e8f0);
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-th-label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-matrix-th-level {
    width: 26%;
}

.pa-matrix-th-rater {
    width: 18.5%;
}

.pa-matrix-row:hover .pa-matrix-td-level {
    background: #f8faff;
}

.pa-matrix-td-level {
    padding: 0.6rem 0.75rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    background: #ffffff;
    vertical-align: middle;
    overflow: hidden;
}

.pa-level-cell-meta {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pa-level-name-wrap {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-level-name-wrap strong {
    font-size: 0.85rem;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-level-id-badge {
    font-size: 0.64rem;
    font-family: ui-monospace, monospace;
    font-weight: 700;
    color: #4f46e5;
    background: #eef2ff;
    padding: 0.06rem 0.3rem;
    border-radius: 4px;
}

.pa-level-bawaan {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.68rem;
    color: var(--hcis-text-muted, #64748b);
    white-space: nowrap;
}

.pa-level-bawaan.is-empty {
    color: #b45309;
}

.pa-matrix-td-cell {
    padding: 0.35rem 0.45rem;
    border-bottom: 1px solid var(--hcis-border-subtle, #e2e8f0);
    vertical-align: middle;
}

/* Cell Buttons */
.pa-matrix-cell-btn {
    width: 100%;
    min-height: 3.2rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.15rem;
    padding: 0.35rem 0.5rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #cbd5e1);
    cursor: pointer;
    text-align: left;
    transition: border-color 100ms ease, background-color 100ms ease;
    box-sizing: border-box;
}

.pa-matrix-cell-btn:hover:not(:disabled) {
    border-color: #4f46e5;
    background: #f8faff;
}

.pa-matrix-cell-btn.is-default {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pa-matrix-cell-btn.is-custom {
    background: #eef2ff;
    border-color: #a5b4fc;
}

.pa-matrix-cell-btn.is-hole {
    background: #fffbeb;
    border: 1.5px dashed #f59e0b;
}

.pa-matrix-cell-btn.is-hole:hover:not(:disabled) {
    background: #fef3c7;
    border-color: #d97706;
}

.pa-matrix-cell--na {
    padding: 0.65rem 0.4rem;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    text-align: center;
    color: #94a3b8;
    font-size: 0.72rem;
    font-style: italic;
}

.pa-cell-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.25rem;
}

.pa-cell-code {
    font-size: 0.72rem;
    font-family: ui-monospace, monospace;
    font-weight: 750;
    color: #1e1b4b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-cell-sub {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.66rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-cell-hole-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    color: #b45309;
    font-size: 0.72rem;
    font-weight: 700;
}

/* Dedicated Usulan Recommendation Card inside Cell */
.pa-cell-proposal-card {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.35rem 0.45rem;
    border-radius: 6px;
    background: #faf5ff;
    border: 1.5px dashed #c084fc;
    box-sizing: border-box;
}

.pa-proposal-content {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    cursor: pointer;
}

.pa-proposal-kicker {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: #7e22ce;
}

.pa-proposal-code {
    font-size: 0.72rem;
    font-family: ui-monospace, monospace;
    color: #581c87;
    font-weight: 750;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-proposal-meta {
    font-size: 0.64rem;
    color: #7e22ce;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pa-proposal-apply-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.2rem 0.45rem;
    border-radius: 4px;
    background: #7e22ce;
    border: 1px solid #6b21a8;
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 120ms ease;
    margin-top: 0.1rem;
}

.pa-proposal-apply-btn:hover:not(:disabled) {
    background: #6b21a8;
}

.pa-proposal-apply-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Step Footer Navigation */
.pa-step-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    flex-wrap: wrap;
}

.pa-footer-feedback {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex: 1;
    min-width: 14rem;
}

.pa-step-message {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #047857;
    font-size: 0.76rem;
    font-weight: 650;
}

.pa-step-message.is-error {
    background: #fef2f2;
    border-color: #fecaca;
    color: #b91c1c;
}

.pa-footer-hint {
    font-size: 0.74rem;
    color: #64748b;
}

.pa-step-nav-buttons {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-dialog-footer-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

@media (max-width: 1024px) {
    .pa-cockpit-grid,
    .pa-skel-cockpit {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .pa-cockpit-grid,
    .pa-skel-cockpit {
        grid-template-columns: 1fr;
    }
    .pa-step-header {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-step-header-actions {
        width: 100%;
    }
    .pa-step-footer {
        flex-direction: column;
        align-items: stretch;
    }
    .pa-step-nav-buttons {
        width: 100%;
        justify-content: space-between;
    }
}
.pa-cell-gugur {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.6rem 0.3rem;
    font-size: 0.7rem;
    color: var(--hcis-warning-strong, #b45309);
    border: 1px dashed var(--hcis-warning, #f59e0b);
    border-radius: var(--hcis-radius-sm, 8px);
    background: color-mix(in srgb, var(--hcis-warning, #f59e0b) 8%, transparent);
}
.pa-cell-gugur {
    width: 100%;
    font-family: inherit;
    cursor: pointer;
}

.pa-cell-gugur:hover:not(:disabled) {
    background: color-mix(in srgb, var(--hcis-warning, #f59e0b) 16%, transparent);
}

.pa-cell-gugur:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.pa-gugur-desc {
    margin: 0.2rem 0 0.35rem;
}

.pa-gugur-list {
    margin: 0;
    padding-left: 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.8rem;
}
.pa-tertahan-desc {
    margin: 0 0 0.6rem;
}

.pa-tertahan-list {
    margin: 0;
    padding-left: 1.1rem;
    max-height: 14rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.83rem;
}

.pa-tertahan-list span {
    display: block;
    font-size: 0.76rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-tertahan-jalan {
    margin: 0.7rem 0 0;
    font-size: 0.83rem;
}
/* Token LAPIS PERAN yang benar-benar terdefinisi. Berkas ini memuat beberapa nama
   karangan (--hcis-jarak-*, --hcis-teks-*, --hcis-warna-teks-*) yang tidak ada di
   design-system.css dan karena itu diam-diam jatuh ke nilai bawaan peramban; blok baru
   tidak ikut menambahnya. */
.pa-matriks-ganti {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-3);
    font-size: var(--hcis-font-size-sm);
    color: var(--hcis-text);
}

.pa-matriks-ganti-catatan {
    color: var(--hcis-text-muted);
}
.pa-matriks-label-nota {
    margin-bottom: var(--hcis-space-4);
}
</style>

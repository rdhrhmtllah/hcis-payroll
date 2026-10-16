<template>
    <div class="pa-step-root">
        <PaStepHeader
            kicker="STEP 4 OF 5 · PARTICIPANT SETUP &amp; SIMULATION"
            title="Peserta &amp; Eksekusi Penyiapan"
            description="Pratinjau siapa yang akan diikutkan, dikecualikan otomatis oleh sistem, atau tertunda. Kelola pengecualian manual dan jalankan penyiapan resmi."
            icon="people-fill"
        >
            <template #actions>
                <HcisButton
                    v-if="bolehKecualikan"
                    label="Kecualikan Karyawan"
                    variant="secondary"
                    size="sm"
                    leading-icon="person-dash"
                    @click="bukaDialogKecuali"
                />
                <!--
                 | SATU BENTUK DI SEMUA LANGKAH: "Lihat Master ...".
                 |
                 | Sebelum 6 September 2026 hanya sebagian langkah punya jalan ke
                 | masternya, dan namanya berbeda-beda. Nama yang berganti tiap langkah
                 | memaksa admin membaca ulang untuk memastikan ia tidak sedang menekan
                 | sesuatu yang MENGUBAH data — padahal semuanya cuma membuka halaman.
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

        <div class="pa-step-body">

            <!--
             | TERKUNCI UNTUK MENYIAPKAN ULANG, TIDAK UNTUK SEGALANYA.
             |
             | Spanduk yang berbunyi "terkunci" tanpa menyebut apa yang MASIH boleh membuat
             | admin berhenti mencari — padahal mengecualikan satu orang tetap diizinkan, dan
             | itulah yang dibutuhkan ketika seseorang ternyata sudah resign.
             -->
            <HcisAlert
                v-if="!dapatDisunting"
                variant="warning"
                appearance="subtle"
                :title="alasanTerkunci || 'Langkah ini sedang terkunci untuk penyuntingan.'"
                icon="lock-fill"
            >
                <template v-if="bolehKecualikan">
                    Menyiapkan ulang peserta memang sudah tidak bisa, tetapi
                    <strong>mengecualikan satu orang masih boleh</strong> — pakai itu bila ada
                    peserta yang ternyata sudah resign atau ditolak HC Learn.
                </template>
            </HcisAlert>

            <!-- Error State -->
            <HcisResultState
                v-else-if="error && !memuat"
                status="danger"
                variant="glass"
                layout="compact"
                title="Pratinjau peserta tidak dapat dimuat"
                :description="error"
                primary-text="Coba Lagi"
                primary-icon="arrow-clockwise"
                @primary-click="muat"
            />

            <!-- Loading Skeleton -->
            <div v-else-if="memuat" class="pa-peserta-skeleton">
                <div class="pa-stat-skel-grid">
                    <HcisSkeleton v-for="n in 4" :key="`skel-stat-${n}`" height="5.5rem" rounded="md" />
                </div>
                <HcisSkeleton width="100%" height="10rem" rounded="lg" class="mt-3" />
            </div>

            <!-- Loaded Content -->
            <div v-else-if="pratinjau" class="pa-peserta-content">
                <!-- Aturan kelayakan siklus ini (DDL-2026-191). Hanya bisa diubah sebelum peserta dibentuk. -->
                <PaAturanMasaKerja
                    v-if="masaKerja"
                    :nilai="masaKerja.min_masa_kerja_bulan"
                    :maks="masaKerja.maks_bulan"
                    :boleh-ubah="masaKerja.boleh_ubah"
                    :menyimpan="menyimpanMasaKerja"
                    :keterangan="keteranganMasaKerja"
                    :alasan-terkunci="masaKerja.alasan_terkunci || ''"
                    @simpan="simpanMasaKerja"
                />

                <!--
                 | ANGKA NYATA ATAU PRATINJAU — DAN LAYAR MENYEBUT YANG MANA.
                 |
                 | Sesudah penyiapan berjalan, pratinjau hanya menghitung orang yang BELUM jadi
                 | peserta; menampilkannya sebagai hasil membuat "Gugur otomatis 0" berdiri di
                 | samping 767 orang yang nyatanya dikecualikan. Sesudah dijalankan, kartunya
                 | membaca peserta yang benar-benar terbentuk.
                 -->
                <HcisStatGroup :cols="4" class="pa-sim-stat-group" data-uji="peserta-angka">
                    <template v-if="sudahDijalankan">
                        <HcisStatCard
                            label="Diikutkan"
                            :value="formatNumber(terbentuk.diikutkan)"
                            caption="Peserta yang dinilai"
                            icon="person-check-fill"
                            sentiment="success"
                        />
                        <HcisStatCard
                            label="Dikecualikan"
                            :value="formatNumber(terbentuk.dikecualikan)"
                            caption="Aturan level, resign, masa kerja, atau keputusan admin"
                            icon="person-dash-fill"
                            sentiment="neutral"
                        />
                        <HcisStatCard
                            label="Dikecualikan Admin"
                            :value="formatNumber(praKecualiAktif)"
                            caption="Bagian dari dikecualikan"
                            icon="person-x-fill"
                            :sentiment="praKecualiAktif > 0 ? 'warning' : 'neutral'"
                        />
                        <HcisStatCard
                            label="Tertunda"
                            :value="formatNumber(terbentuk.tertunda)"
                            caption="Level belum beraturan"
                            icon="hourglass-split"
                            :sentiment="terbentuk.tertunda > 0 ? 'warning' : 'neutral'"
                        />
                    </template>
                    <template v-else>
                        <HcisStatCard
                            label="Akan Diikutkan"
                            :value="formatNumber(pratinjau.cacah?.diikutkan || 0)"
                            caption="Perkiraan peserta yang dinilai"
                            icon="person-check-fill"
                            sentiment="success"
                        />
                        <!--
                         | DUA ANGKA PENGECUALIAN, BUKAN SATU: pengecualian aturan dan pengecualian
                         | yang ditetapkan admin dipisah supaya admin yang mengecualikan dua orang
                         | tidak membaca angka 101.
                         -->
                        <HcisStatCard
                            label="Gugur Otomatis"
                            :value="formatNumber(pratinjau.cacah?.dikecualikan_otomatis || 0)"
                            caption="Aturan level, resign, masa kerja"
                            icon="person-dash-fill"
                            sentiment="neutral"
                        />
                        <HcisStatCard
                            label="Dikecualikan Admin"
                            :value="formatNumber(pratinjau.cacah?.dikecualikan || 0)"
                            caption="Ditetapkan manual"
                            icon="person-x-fill"
                            :sentiment="(pratinjau.cacah?.dikecualikan || 0) > 0 ? 'warning' : 'neutral'"
                        />
                        <HcisStatCard
                            label="Tertunda"
                            :value="formatNumber(pratinjau.cacah?.tertunda || 0)"
                            caption="Level belum beraturan"
                            icon="hourglass-split"
                            :sentiment="(pratinjau.cacah?.tertunda || 0) > 0 ? 'warning' : 'neutral'"
                        />
                    </template>
                </HcisStatGroup>

                <!-- Missing Level Warning Alert -->
                <HcisAlert
                    v-if="pratinjau.level_tanpa_aturan && pratinjau.level_tanpa_aturan.length"
                    variant="warning"
                    appearance="subtle"
                    :title="`${pratinjau.level_tanpa_aturan.length} level karyawan belum memiliki aturan rater`"
                    icon="exclamation-triangle-fill"
                >
                    Karyawan pada level ini akan mendarat di keranjang tertunda. Anda dapat kembali ke <strong>Langkah 3 (Rater Rules)</strong> untuk melengkapi aturan sebelum menjalankan penyiapan.
                </HcisAlert>

                <!--
                 | DUA KEADAAN, DAN LAYAR MENYEBUT YANG MANA.
                 |
                 | Dulu kartu ini selalu bertanya "Siap menjalankan?" — juga pada siklus yang
                 | pesertanya sudah terbentuk — sehingga admin tidak tahu apakah menekannya lagi
                 | berguna. Sesudah dijalankan ia melaporkan kapan, hasilnya, dan apa yang akan
                 | berubah bila dijalankan ulang.
                 -->
                <div v-if="sudahDijalankan" class="pa-penyiapan-status" data-uji="penyiapan-sudah">
                    <div class="pa-penyiapan-status__kepala">
                        <HcisIcon name="check-circle-fill" size="sm" class="pa-penyiapan-status__ikon" />
                        <div>
                            <h3>Penyiapan sudah dijalankan</h3>
                            <p v-if="pratinjau.terakhir_dijalankan" data-uji="penyiapan-terakhir">
                                Terakhir {{ formatWaktu(pratinjau.terakhir_dijalankan.pada) }}<template v-if="pratinjau.terakhir_dijalankan.oleh"> oleh {{ pratinjau.terakhir_dijalankan.oleh }}</template>
                                · {{ formatNumber(terbentuk.diikutkan) }} diikutkan · {{ formatNumber(terbentuk.dikecualikan) }} dikecualikan · {{ formatNumber(terbentuk.tertunda) }} tertunda
                            </p>
                        </div>
                    </div>

                    <div class="pa-penyiapan-status__ulang" data-uji="penyiapan-bila-ulang">
                        <strong>Bila dijalankan ulang</strong>
                        <ul v-if="adaPerubahan">
                            <li v-if="bilaDiulang.peserta_baru">{{ formatNumber(bilaDiulang.peserta_baru) }} karyawan baru dibentuk sebagai peserta</li>
                            <li v-if="bilaDiulang.level_berubah">
                                {{ formatNumber(bilaDiulang.level_berubah) }} peserta berubah Level PA<template v-if="bilaDiulang.level_tertahan">
                                    — {{ formatNumber(bilaDiulang.level_tertahan) }} di antaranya tertahan karena sudah berpenugasan atau punya penyesuaian</template>
                            </li>
                        </ul>
                        <p v-else>Tidak ada perubahan. Menjalankan ulang tidak mengubah apa pun.</p>
                    </div>

                    <div class="pa-penyiapan-status__aksi">
                        <HcisButton
                            label="Jalankan Ulang Penyiapan"
                            :variant="adaPerubahan ? 'primary' : 'secondary'"
                            size="sm"
                            leading-icon="arrow-clockwise"
                            :disabled="!dapatDisunting || !siapDijalankan"
                            :loading="menjalankan"
                            data-uji="jalankan-ulang"
                            @click="dialogJalankan = true"
                        />
                    </div>
                </div>

                <!-- Yang tidak diselesaikan dengan menjalankan ulang: peserta resign, Level PA tertahan. -->
                <PaPerubahanPeserta
                    v-if="sudahDijalankan"
                    :cycle-id="cycleId"
                    langkah="PESERTA"
                    :dapat-disunting="dapatDisunting"
                    :boleh-kecualikan="bolehKecualikan"
                    @berubah="muat"
                    @ke-langkah-5="$emit('lanjut')"
                />

                <div v-else class="pa-launch-cockpit-card">
                    <div class="pa-launch-cockpit-content">
                        <div class="pa-launch-cockpit-text">
                            <div class="pa-cockpit-badge-row">
                                <span class="pa-eyebrow">BELUM DIJALANKAN</span>
                                <HcisBadge
                                    :label="siapDijalankan ? 'SIAP DIEKSEKUSI' : 'MENUNGGU KELENGKAPAN'"
                                    :variant="siapDijalankan ? 'success' : 'warning'"
                                    size="2xs"
                                    dot
                                />
                            </div>
                            <h3>Siap menjalankan penyiapan?</h3>
                            <p>
                                Sistem membentuk daftar peserta resmi berdasarkan <strong>Bobot</strong>, <strong>Paket Soal</strong>, dan <strong>Aturan Penilai</strong>.
                                Penugasan penilai dibentuk sesudahnya di <strong>Langkah 5</strong>.
                            </p>
                            <div v-if="siapDijalankan" class="pa-cockpit-quick-checks">
                                <span class="pa-quick-check-item">
                                    <HcisIcon name="check-circle-fill" size="2xs" color="success" />
                                    <span>Bobot Pilar Siap</span>
                                </span>
                                <span class="pa-quick-check-item">
                                    <HcisIcon name="check-circle-fill" size="2xs" color="success" />
                                    <span>Paket Soal Terpasang</span>
                                </span>
                                <span class="pa-quick-check-item">
                                    <HcisIcon name="check-circle-fill" size="2xs" color="success" />
                                    <span>Aturan Rater Valid</span>
                                </span>
                            </div>
                        </div>

                        <div class="pa-launch-cockpit-action">
                            <HcisButton
                                label="Jalankan Penyiapan"
                                variant="primary"
                                size="lg"
                                leading-icon="play-fill"
                                :disabled="!dapatDisunting || !siapDijalankan"
                                :loading="menjalankan"
                                data-uji="jalankan-penyiapan"
                                class="pa-launch-cta-btn"
                                @click="dialogJalankan = true"
                            />
                            <small v-if="!siapDijalankan && penghalang.length" class="pa-blocked-hint">
                                <HcisIcon name="exclamation-circle-fill" size="2xs" color="warning" />
                                {{ penghalang.length }} hal masih menghalangi penyiapan: {{ penghalang.join(' ') }}
                            </small>
                            <small v-else class="pa-ready-hint">
                                <HcisIcon name="check-circle-fill" size="2xs" color="success" />
                                Seluruh konfigurasi telah lengkap dan siap dieksekusi.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Level Breakdown Simulation Section -->
                <div v-if="daftarLevel.length" class="pa-level-sim-section">
                    <div class="pa-section-head">
                        <div>
                            <span class="pa-eyebrow">DISTRIBUSI POPULASI DINILAI</span>
                            <h4>Rincian Estimasi Peserta per Level Jabatan ({{ totalKaryawanAktif }} Karyawan)</h4>
                        </div>
                    </div>

                    <div class="pa-level-sim-grid">
                        <div
                            v-for="lv in daftarLevel"
                            :key="lv.id_level"
                            class="pa-level-sim-card"
                            :class="{ 'is-incomplete': !lv.punya_paket || !lv.punya_aturan }"
                        >
                            <div class="pa-level-sim-head">
                                <div class="pa-level-sim-title-wrap">
                                    <strong class="pa-level-name">{{ lv.nama_level }}</strong>
                                    <span class="pa-level-sim-tag">ID: {{ lv.id_level }}</span>
                                </div>
                                <HcisBadge
                                    :label="lv.punya_paket && lv.punya_aturan ? 'Siap Diikutkan' : 'Konfigurasi Belum Lengkap'"
                                    :variant="lv.punya_paket && lv.punya_aturan ? 'success' : 'warning'"
                                    size="2xs"
                                    dot
                                />
                            </div>

                            <div class="pa-level-sim-stat">
                                <div class="pa-sim-count-row">
                                    <span class="pa-sim-count">{{ lv.jumlah_karyawan }} Orang</span>
                                    <span class="pa-sim-pct">{{ totalKaryawanAktif ? Math.round((lv.jumlah_karyawan / totalKaryawanAktif) * 100) : 0 }}%</span>
                                </div>
                            </div>

                            <div class="pa-sim-progress">
                                <div
                                    class="pa-sim-fill"
                                    :style="{ width: `${totalKaryawanAktif ? Math.round((lv.jumlah_karyawan / totalKaryawanAktif) * 100) : 0}%` }"
                                />
                            </div>

                            <div class="pa-level-config-flags">
                                <span class="pa-config-flag" :class="lv.punya_paket ? 'is-valid' : 'is-missing'">
                                    <HcisIcon :name="lv.punya_paket ? 'check-circle-fill' : 'exclamation-circle-fill'" size="2xs" />
                                    <span>{{ lv.punya_paket ? 'Paket Soal' : 'Tanpa Paket' }}</span>
                                </span>
                                <span class="pa-config-flag" :class="lv.punya_aturan ? 'is-valid' : 'is-missing'">
                                    <HcisIcon :name="lv.punya_aturan ? 'check-circle-fill' : 'exclamation-circle-fill'" size="2xs" />
                                    <span>{{ lv.punya_aturan ? 'Aturan Rater' : 'Tanpa Aturan' }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pre-Exclusion Section (Pengecualian Manual Admin) -->
                <div class="pa-pra-kecuali-card">
                    <div class="pa-pra-kecuali-head">
                        <div class="pa-pra-kecuali-title-wrap">
                            <span class="pa-eyebrow">DAFTAR PENERAPAN PRA-KECUALI</span>
                            <h4>Karyawan Dikecualikan atas Kebijakan Administrator ({{ praKecuali.length }})</h4>
                        </div>
                        <HcisButton
                            v-if="bolehKecualikan"
                            label="Tambah Pengecualian"
                            variant="secondary"
                            size="xs"
                            leading-icon="plus-lg"
                            @click="bukaDialogKecuali"
                        />
                    </div>

                    <div v-if="!praKecuali.length" class="pa-pra-kecuali-empty">
                        <div class="pa-pra-kecuali-empty-content">
                            <HcisIcon name="info-circle" size="xs" color="neutral" />
                            <span>Belum ada karyawan yang dikecualikan secara manual oleh admin. Filter otomatis sistem (freelance, resign) tetap berlaku.</span>
                        </div>
                        <HcisButton
                            v-if="bolehKecualikan"
                            label="Kecualikan Karyawan"
                            variant="secondary"
                            size="xs"
                            leading-icon="person-dash"
                            @click="bukaDialogKecuali"
                        />
                    </div>

                    <!--
                     | HASIL PENCABUTAN MEMBAWA LANGKAH BERIKUTNYA. Yang sudah terbentuk sebagai peserta
                     | langsung diikutkan kembali — Jalankan Ulang tidak diperlukan untuk mereka, dan
                     | memang tidak mengubah peserta yang sudah ada. Yang dibutuhkan adalah penugasannya
                     | (Langkah 5); Jalankan Ulang hanya untuk orang yang belum pernah menjadi peserta.
                     -->
                    <div v-if="hasilCabut" class="pa-cabut-hasil" data-uji="hasil-cabut">
                        <HcisAlert
                            :variant="hasilCabut.gagal.length ? 'warning' : 'success'"
                            appearance="subtle"
                            size="sm"
                            :title="judulHasilCabut"
                            :icon="hasilCabut.gagal.length ? 'exclamation-triangle-fill' : 'check-circle-fill'"
                        >
                            <p v-if="perluLengkapiPenugasan" class="pa-cabut-hasil__teks">
                                {{ hasilCabut.diikutkan }} orang diikutkan kembali dan belum punya penugasan penilai.
                            </p>
                            <p v-if="perluJalankanUlang" class="pa-cabut-hasil__teks">
                                {{ hasilCabut.belum_peserta }} orang belum pernah menjadi peserta — jalankan ulang penyiapan supaya mereka masuk.
                            </p>
                            <ul v-if="hasilCabut.gagal.length" class="pa-cabut-hasil__gagal">
                                <li v-for="g in hasilCabut.gagal" :key="g.id"><strong>{{ g.nama }}</strong> — {{ g.sebab }}</li>
                            </ul>
                            <div v-if="perluLengkapiPenugasan || perluJalankanUlang" class="pa-cabut-hasil__aksi">
                                <HcisButton
                                    v-if="perluJalankanUlang"
                                    label="Jalankan Ulang Penyiapan"
                                    variant="primary"
                                    size="xs"
                                    leading-icon="arrow-clockwise"
                                    :disabled="!dapatDisunting || !siapDijalankan"
                                    data-uji="cabut-jalankan-ulang"
                                    @click="dialogJalankan = true"
                                />
                                <HcisButton
                                    v-if="perluLengkapiPenugasan"
                                    label="Ke Langkah 5 — Lengkapi Penugasan"
                                    :variant="perluJalankanUlang ? 'secondary' : 'primary'"
                                    size="xs"
                                    trailing-icon="arrow-right"
                                    data-uji="cabut-ke-langkah-5"
                                    @click="$emit('lanjut')"
                                />
                            </div>
                        </HcisAlert>
                    </div>

                    <div v-if="praKecuali.length && dapatDisunting" class="pa-cabut-alat">
                        <HcisCheckbox
                            :model-value="semuaTerpilihCabut"
                            :indeterminate="sebagianTerpilihCabut"
                            label="Pilih semua"
                            size="sm"
                            data-uji="cabut-pilih-semua"
                            @update:model-value="aturPilihSemuaCabut"
                        />
                        <HcisButton
                            :label="`Cabut Pengecualian (${pilihanCabut.length})`"
                            variant="secondary"
                            size="xs"
                            leading-icon="person-check"
                            :disabled="!pilihanCabut.length"
                            data-uji="cabut-terpilih"
                            @click="dialogCabut = true"
                        />
                    </div>

                    <div v-if="praKecuali.length" class="pa-pra-kecuali-table-wrap">
                        <table class="pa-pra-kecuali-table">
                            <thead>
                                <tr>
                                    <th v-if="dapatDisunting" class="pa-cabut-kolom" aria-label="Pilih"></th>
                                    <th>Karyawan</th>
                                    <th>Alasan Pengecualian</th>
                                    <th>Status Keaktifan</th>
                                    <th class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="k in praKecuali" :key="k.id" :class="{ 'is-terpilih': pilihanCabut.includes(k.id) }">
                                    <td v-if="dapatDisunting" class="pa-cabut-kolom">
                                        <HcisCheckbox
                                            v-model="pilihanCabut"
                                            :value="k.id"
                                            size="sm"
                                            :aria-label="`Pilih ${k.nama || k.kode_karyawan}`"
                                        />
                                    </td>
                                    <td>
                                        <div class="pa-person-cell">
                                            <div class="pa-person-avatar">
                                                <HcisIcon name="person-fill" size="2xs" />
                                            </div>
                                            <div class="pa-person-info">
                                                <strong>{{ k.nama || k.kode_karyawan }}</strong>
                                                <small>{{ k.kode_karyawan }} &bull; Perusahaan {{ k.kode_perusahaan || '001' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="pa-reason-text">"{{ k.alasan }}"</span>
                                    </td>
                                    <td>
                                        <HcisBadge
                                            :label="k.dikecualikan_saat_ini ? 'Dikecualikan' : 'Dicabut'"
                                            :variant="k.dikecualikan_saat_ini ? 'warning' : 'neutral'"
                                            size="xs"
                                        />
                                    </td>
                                    <td class="text-right">
                                        <HcisButton
                                            v-if="k.dikecualikan_saat_ini && dapatDisunting"
                                            label="Cabut Pengecualian"
                                            variant="ghost"
                                            size="xs"
                                            leading-icon="trash"
                                            :loading="sibukCabut === k.id"
                                            class="pa-revoke-btn"
                                            @click="cabut(k)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!--
             | KESIAPAN DI HC LEARN — tahap yang berdiri SEBELUM penjadwalan.
             |
             | `store` penjadwalan menolak 422 bila ada SATU kode yang belum dikenal, dan
             | penolakannya membatalkan SELURUH kiriman. Tanpa panel ini kegagalan itu baru
             | terlihat sesudah siklus dikunci, ketika satu-satunya jalan keluarnya jauh lebih
             | mahal. Panel yang sama berdiri lagi di Langkah 5 dari SATU komponen — di sini
             | penilainya masih proyeksi, di sana sudah kenyataan.
             |
             | DI LUAR rantai `v-if` pemuatan langkah, dan itu bukan soal tata letak. Di dalam
             | rantai itu ia ikut dibongkar setiap kali langkahnya memuat ulang, lalu dipasang
             | lagi — satu perjalanan tambahan ke HC Learn pada tiap pemuatan, dan hasil
             | sinkronisasi yang baru saja ditampilkan terhapus tanpa ada yang menutupnya.
             | Ketahuan dari penjaga yang menghitung panggilan: dua yang diharapkan, tiga yang
             | terjadi.
             -->
            <!-- Sesudah penyiapan berjalan, panel yang sama berdiri di Langkah 5 dengan penilai
                 yang sudah nyata — tidak diulang di sini. -->
            <PaKesiapanHcLearn
                v-if="pratinjau && !sudahDijalankan"
                ref="panelHcLearn"
                konteks="PESERTA"
                :cycle-id="cycleId"
                :dapat-disunting="dapatDisunting"
                @pesan="terimaPesanHcLearn"
            />

            <!-- Level PA (DDL-2026-177): peserta yang levelnya berubah tetapi tidak disegarkan otomatis. -->
            <HcisAlert
                v-if="levelTertahan.length"
                variant="warning"
                appearance="subtle"
                icon="person-exclamation"
                class="mb-3"
                data-uji="level-tertahan"
                :title="`${levelTertahan.length} peserta belum ikut Level PA terbaru`"
            >
                <ul class="mb-0 ps-3">
                    <li v-for="t in levelTertahan" :key="t.participant_id">
                        <strong>{{ t.nama }}</strong> ({{ t.kode_karyawan }}) → {{ t.nama_level_seharusnya || 'level kosong' }}: {{ t.sebab }}
                    </li>
                </ul>
            </HcisAlert>

            <!-- Footer Navigation -->
            <div class="pa-step-footer">
                <span v-if="pesan" class="pa-step-message" :class="{ 'is-error': pesanError }">{{ pesan }}</span>
                <div class="pa-step-nav-buttons ms-auto">
                    <HcisButton
                        label="Kembali ke Langkah 3: Rater Rules"
                        variant="secondary"
                        size="sm"
                        leading-icon="arrow-left"
                        @click="$emit('kembali')"
                    />
                    <HcisButton
                        v-if="sudahDijalankan"
                        label="Lanjut ke Langkah 5: Penugasan"
                        variant="primary"
                        size="sm"
                        trailing-icon="arrow-right"
                        data-uji="lanjut-penugasan"
                        @click="$emit('lanjut')"
                    />
                </div>
            </div>
        </div>

        <!-- Dialog: Tambah Pengecualian Manual (Multi-Select Search & Filter) -->
        <!-- KONFIRMASI CABUT BEBERAPA PENGECUALIAN -->
        <HcisDialog
            v-model="dialogCabut"
            title="Cabut Pengecualian"
            kicker="LANGKAH 4 · PESERTA"
            icon="person-check"
            size="sm"
            data-uji="dialog-cabut"
        >
            <p class="pa-cabut-dialog__teks">
                {{ pilihanCabut.length }} orang dicabut dari daftar pengecualian. Yang sudah terbentuk sebagai
                peserta langsung <strong>diikutkan kembali</strong>; penugasan penilainya dilengkapi di Langkah 5.
                Pengecualian otomatis (resign, level tanpa aturan) tidak ikut dikembalikan.
            </p>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="dialogCabut = false" />
                <HcisButton
                    :label="`Ya, Cabut (${pilihanCabut.length})`"
                    variant="primary"
                    size="sm"
                    :loading="sibukCabutBanyak"
                    data-uji="cabut-konfirmasi"
                    @click="cabutTerpilih"
                />
            </template>
        </HcisDialog>

        <HcisDialog
            v-model="dialogKecuali"
            title="Kecualikan Karyawan dari Cycle"
            kicker="KEBIJAKAN ADMINISTRATOR"
            icon="person-dash"
            sentiment="warning"
            size="xl"
        >
            <div class="pa-kecuali-form">
                <!-- Mode Switcher: Pencarian & Filter vs Input Kode Karyawan Banyak -->
                <div class="pa-kecuali-mode-tabs">
                    <button
                        type="button"
                        class="pa-mode-tab"
                        :class="{ active: modePengecualian === 'cari' }"
                        @click="modePengecualian = 'cari'"
                    >
                        <HcisIcon name="search" size="2xs" />
                        <span>Cari & Filter Karyawan (Multi-Select)</span>
                    </button>
                    <button
                        type="button"
                        class="pa-mode-tab"
                        :class="{ active: modePengecualian === 'bulk' }"
                        @click="modePengecualian = 'bulk'"
                    >
                        <HcisIcon name="list-ul" size="2xs" />
                        <span>Input Kode Karyawan Banyak (Bulk)</span>
                    </button>
                </div>

                <!-- Mode 1: Search & Filter Multi-Select -->
                <div v-if="modePengecualian === 'cari'" class="pa-filter-search-container">
                    <!-- 4-Column Organization Multi-Select Filter Bar -->
                    <div class="pa-modal-filters-grid">
                        <div class="pa-filter-item">
                            <label class="pa-filter-label">Divisi (Multi-Pilih)</label>
                            <HcisSelect
                                v-model="filterKecuali.divisi"
                                placeholder="Semua Divisi"
                                size="sm"
                                multiple
                                collapse-tags
                                clearable
                                filterable
                            >
                                <HcisSelectOption
                                    v-for="div in opsiFilter.divisi"
                                    :key="div"
                                    :value="div"
                                    :label="div"
                                />
                            </HcisSelect>
                        </div>

                        <div class="pa-filter-item">
                            <label class="pa-filter-label">Departemen (Multi-Pilih)</label>
                            <HcisSelect
                                v-model="filterKecuali.departemen"
                                placeholder="Semua Departemen"
                                size="sm"
                                multiple
                                collapse-tags
                                clearable
                                filterable
                            >
                                <HcisSelectOption
                                    v-for="dept in opsiFilter.departemen"
                                    :key="dept"
                                    :value="dept"
                                    :label="dept"
                                />
                            </HcisSelect>
                        </div>

                        <div class="pa-filter-item">
                            <label class="pa-filter-label">Level Jabatan (Multi-Pilih)</label>
                            <HcisSelect
                                v-model="filterKecuali.id_level"
                                placeholder="Semua Level"
                                size="sm"
                                multiple
                                collapse-tags
                                clearable
                                filterable
                            >
                                <HcisSelectOption
                                    v-for="lv in opsiFilter.level"
                                    :key="lv.id"
                                    :value="lv.id"
                                    :label="lv.nama"
                                />
                            </HcisSelect>
                        </div>

                        <div class="pa-filter-item">
                            <label class="pa-filter-label">Cabang / Lokasi (Multi-Pilih)</label>
                            <HcisSelect
                                v-model="filterKecuali.cabang"
                                placeholder="Semua Cabang / Lokasi"
                                size="sm"
                                multiple
                                collapse-tags
                                clearable
                                filterable
                            >
                                <HcisSelectOption
                                    v-for="cb in opsiFilter.cabang"
                                    :key="cb"
                                    :value="cb"
                                    :label="cb"
                                />
                            </HcisSelect>
                        </div>
                    </div>

                    <!-- Search Input Bar with Clear & Counter -->
                    <div class="pa-search-action-bar">
                        <div class="pa-search-input-wrap">
                            <HcisField
                                v-model="kataKunciCari"
                                size="sm"
                                placeholder="Ketik nama atau kode karyawan..."
                                leading-icon="search"
                                clearable
                                @input="lakukanPencarianKaryawan"
                            />
                            <HcisIcon v-if="mencariKaryawan" name="arrow-repeat" size="xs" class="pa-spin-icon" />
                        </div>
                        <HcisButton
                            v-if="hasActiveFilter"
                            label="Reset Filter"
                            variant="ghost"
                            size="sm"
                            leading-icon="x-circle"
                            @click="resetFilterKecuali"
                        />
                    </div>

                    <!-- Results Multi-Select List -->
                    <div class="pa-results-box">
                        <div class="pa-results-toolbar">
                            <div class="pa-results-count">
                                <strong>{{ hasilPencarianKaryawan.length }}</strong> karyawan ditemukan
                                <span v-if="jumlahTerpilihDariHasil > 0" class="pa-count-badge">
                                    {{ jumlahTerpilihDariHasil }} dipilih
                                </span>
                            </div>
                            <div class="pa-results-actions">
                                <button
                                    type="button"
                                    class="pa-quick-select-btn"
                                    :disabled="!jumlahDapatDipilih"
                                    @click="semuaHasilTerpilih ? batalkanSemuaHasil() : pilihSemuaHasil()"
                                >
                                    <HcisIcon :name="semuaHasilTerpilih ? 'x-circle' : 'check-all'" size="2xs" />
                                    <span>{{ semuaHasilTerpilih ? `Batalkan Pilihan (${hasilPencarianKaryawan.length} Orang)` : `Pilih Semua (${jumlahDapatDipilih} Orang)` }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="mencariKaryawan" class="pa-results-loading">
                            <HcisIcon name="arrow-repeat" size="sm" class="pa-spin-icon" />
                            <span>Memuat data karyawan...</span>
                        </div>

                        <div v-else-if="!hasilPencarianKaryawan.length" class="pa-results-empty">
                            <span v-if="!kataKunciCari && !hasActiveFilter">
                                Gunakan kata kunci pencarian atau pilih filter divisi/departemen/level/cabang di atas.
                            </span>
                            <span v-else>Tidak ada karyawan yang cocok dengan filter / kata kunci pencarian.</span>
                        </div>

                        <div v-else class="pa-results-list">
                            <div
                                v-for="kar in daftarHasilUrut"
                                :key="kar.kode_perusahaan + '|' + kar.kode_karyawan"
                                class="pa-employee-row"
                                :class="{
                                    'is-selected': isKaryawanTerpilih(kar),
                                    'is-disabled': kar.sudah_dikecualikan,
                                }"
                                @click="!kar.sudah_dikecualikan && togglePilihKaryawan(kar)"
                            >
                                <div class="pa-row-checkbox-col" @click.stop>
                                    <input
                                        type="checkbox"
                                        :checked="isKaryawanTerpilih(kar)"
                                        :disabled="kar.sudah_dikecualikan"
                                        class="pa-row-checkbox"
                                        @change="togglePilihKaryawan(kar)"
                                    />
                                </div>

                                <div class="pa-row-info-col">
                                    <div class="pa-row-primary-line">
                                        <strong class="pa-employee-name">{{ kar.nama }}</strong>
                                        <span class="pa-code-pill">{{ kar.kode_karyawan }}</span>
                                        <span v-if="kar.nama_level" class="pa-level-pill">{{ kar.nama_level }}</span>
                                    </div>
                                    <div class="pa-row-secondary-line">
                                        <span v-if="kar.nama_divisi">{{ kar.nama_divisi }}</span>
                                        <span v-if="kar.nama_divisi && kar.nama_departemen">&rsaquo;</span>
                                        <span v-if="kar.nama_departemen">{{ kar.nama_departemen }}</span>
                                        <span v-if="kar.cabang" class="pa-branch-pill">{{ kar.cabang }}</span>
                                    </div>
                                </div>

                                <div class="pa-row-status-col">
                                    <HcisBadge
                                        v-if="kar.sudah_dikecualikan"
                                        label="Sudah Dikecualikan"
                                        variant="warning"
                                        size="xs"
                                    />
                                    <HcisBadge
                                        v-else-if="isKaryawanTerpilih(kar)"
                                        label="Terpilih"
                                        variant="success"
                                        size="xs"
                                        dot
                                    />
                                    <span v-else class="pa-row-hint-text">Klik untuk pilih</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mode 2: Bulk Kode Karyawan Input -->
                <div v-else class="pa-bulk-container">
                    <div class="pa-bulk-instructions">
                        <strong>Input Kode Karyawan Massal</strong>
                        <p>
                            Tempel atau ketik kode karyawan yang ingin dikecualikan. Pisahkan dengan tanda koma (,), spasi, atau baris baru (Enter).
                        </p>
                    </div>

                    <HcisTextarea
                        v-model="bulkKodeText"
                        size="sm"
                        :rows="5"
                        placeholder="Contoh:&#10;100123&#10;100124&#10;100125"
                    />

                    <div class="pa-bulk-actions">
                        <HcisButton
                            label="Cocokkan & Tambahkan ke Pilihan"
                            variant="primary"
                            size="sm"
                            leading-icon="person-check"
                            :disabled="!bulkKodeText.trim()"
                            :loading="memprosesBulk"
                            @click="prosesBulkKodeKaryawan"
                        />
                    </div>
                </div>

                <!-- Selected Tray (Chip List) -->
                <div v-if="terpilih.length" class="pa-selected-tray">
                    <div class="pa-tray-header">
                        <span class="pa-tray-title">
                            <HcisIcon name="people-fill" size="2xs" color="brand" />
                            <strong>{{ terpilih.length }} Karyawan Dipilih untuk Dikecualikan</strong>
                        </span>
                        <button
                            type="button"
                            class="pa-clear-all-btn"
                            @click="terpilih = []"
                        >
                            Kosongkan Pilihan
                        </button>
                    </div>

                    <div class="pa-tray-chips">
                        <span
                            v-for="(k, i) in terpilih"
                            :key="k.kode_perusahaan + '|' + k.kode_karyawan"
                            class="pa-terpilih-chip"
                        >
                            <span class="pa-chip-code">{{ k.kode_karyawan }}</span>
                            <span class="pa-chip-name">{{ k.nama || k.kode_karyawan }}</span>
                            <button
                                type="button"
                                aria-label="Batalkan pilihan"
                                @click="terpilih.splice(i, 1)"
                            >
                                ×
                            </button>
                        </span>
                    </div>
                </div>

                <!-- Reason Field with Quick Preset Chips -->
                <div class="pa-form-field">
                    <label class="pa-field-label">Alasan Pengecualian (Wajib) <span class="text-danger">*</span></label>
                    <div class="pa-reason-presets">
                        <button
                            v-for="alasanPreset in alasanPresets"
                            :key="alasanPreset"
                            type="button"
                            class="pa-reason-chip"
                            :class="{ 'is-active': formKecuali.alasan === alasanPreset }"
                            @click="formKecuali.alasan = alasanPreset"
                        >
                            {{ alasanPreset }}
                        </button>
                    </div>
                    <HcisTextarea
                        v-model="formKecuali.alasan"
                        size="sm"
                        :rows="2"
                        placeholder="Jelaskan alasan pengecualian untuk kebutuhan jejak audit..."
                    />
                </div>
            </div>

            <template #footer>
                <HcisButton variant="secondary" label="Batal" size="sm" @click="dialogKecuali = false" />
                <HcisButton
                    variant="warning"
                    :label="`Simpan Pengecualian (${terpilih.length} Karyawan)`"
                    size="sm"
                    leading-icon="person-dash"
                    :disabled="!terpilih.length || !formKecuali.alasan.trim()"
                    :loading="menyimpanKecuali"
                    @click="tambahKecuali"
                />
            </template>
        </HcisDialog>

        <!-- Dialog: Konfirmasi & Progress Eksekusi Penyiapan -->
        <HcisDialog
            v-model="dialogJalankan"
            :title="menjalankan ? 'Penyiapan Sedang Berjalan…' : sudahDijalankan ? 'Jalankan Ulang Penyiapan?' : 'Jalankan Penyiapan Peserta?'"
            :kicker="menjalankan ? 'HARAP TUNGGU' : 'EKSEKUSI RESMI SIKLUS'"
            :icon="menjalankan ? 'hourglass-split' : 'play-circle-fill'"
            sentiment="brand"
            variant="glass"
            size="lg"
            :closable="!menjalankan"
        >
            <!-- Tampilan progress saat berjalan -->
            <div v-if="menjalankan" class="pa-exec-progress">
                <!-- Hero Kemajuan Utama -->
                <div class="pa-exec-hero-card">
                    <div class="pa-exec-hero-main">
                        <div class="pa-exec-pulse-icon" aria-hidden="true">
                            <HcisIcon :name="tahapSiapkan.ikon" size="sm" />
                            <span class="pa-exec-pulse-ring" />
                        </div>
                        <div class="pa-exec-hero-text">
                            <div class="pa-exec-stage-pill">
                                <span class="pa-exec-live-dot" />
                                <span>Tahap {{ indeksTahapAktif + 1 }} dari {{ TAHAP_SIAPKAN.length }}</span>
                            </div>
                            <strong class="pa-exec-hero-title">{{ tahapSiapkan.judul }}</strong>
                            <p class="pa-exec-hero-desc">{{ tahapSiapkan.keterangan }}</p>
                        </div>
                    </div>

                    <!-- Progress Bar & Persentase -->
                    <div class="pa-exec-bar-section">
                        <div class="pa-exec-bar-meta">
                            <span class="pa-exec-bar-label">Kemajuan Proses Penyiapan</span>
                            <span class="pa-exec-bar-val">{{ progresSiapkan }}%</span>
                        </div>
                        <div class="pa-exec-progress-track">
                            <div
                                class="pa-exec-progress-bar"
                                role="progressbar"
                                :aria-valuenow="progresSiapkan"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                :style="{ width: `${progresSiapkan}%` }"
                            >
                                <span class="pa-exec-progress-glow" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stepper Breakdown: Transparansi 4 Tahap Pembentukan -->
                <div class="pa-exec-stepper">
                    <div
                        v-for="(t, idx) in TAHAP_SIAPKAN"
                        :key="idx"
                        class="pa-exec-step-item"
                        :class="{
                            'is-done': idx < indeksTahapAktif || progresSiapkan >= 100,
                            'is-active': idx === indeksTahapAktif && progresSiapkan < 100,
                            'is-pending': idx > indeksTahapAktif && progresSiapkan < 100,
                        }"
                    >
                        <div class="pa-exec-step-indicator">
                            <HcisIcon
                                v-if="idx < indeksTahapAktif || progresSiapkan >= 100"
                                name="check-circle-fill"
                                size="xs"
                                color="success"
                            />
                            <span v-else-if="idx === indeksTahapAktif" class="pa-exec-step-spinner" />
                            <span v-else class="pa-exec-step-num">{{ idx + 1 }}</span>
                        </div>
                        <div class="pa-exec-step-content">
                            <span class="pa-exec-step-title">{{ t.judul.replace('…', '') }}</span>
                            <span class="pa-exec-step-hint">{{ t.keterangan }}</span>
                        </div>
                        <span v-if="idx === indeksTahapAktif && progresSiapkan < 100" class="pa-exec-step-badge">
                            Berjalan
                        </span>
                    </div>
                </div>

                <!-- Safety Notice Card -->
                <div class="pa-exec-notice-card">
                    <HcisIcon name="info-circle-fill" size="xs" color="warning" />
                    <p class="pa-exec-notice-text">
                        Proses ini memakan waktu beberapa detik hingga satu menit tergantung jumlah karyawan. <strong>Jangan menutup halaman ini.</strong>
                    </p>
                </div>
            </div>

            <!-- Konfirmasi jalankan ULANG: yang berubah, bukan perkiraan dari nol. -->
            <div v-else-if="sudahDijalankan" class="pa-confirm-body" data-uji="dialog-ulang">
                <!-- Banner Pengantar -->
                <div class="pa-confirm-hero-banner is-repeat">
                    <div class="pa-confirm-hero-icon">
                        <HcisIcon name="arrow-repeat" size="md" color="brand" />
                    </div>
                    <div class="pa-confirm-hero-desc">
                        <h4 class="pa-confirm-hero-title">Sinkronisasi & Perubahan Penyiapan</h4>
                        <p class="pa-confirm-hero-text">
                            Peserta yang sudah terbentuk tidak akan dihapus. Sistem akan memperbarui data karyawan baru dan penyesuaian level PA.
                        </p>
                    </div>
                </div>

                <!-- Grid Kartu Ringkasan Metrik -->
                <div class="pa-confirm-metrics-grid">
                    <div class="pa-metric-card is-new">
                        <div class="pa-metric-card-top">
                            <span class="pa-metric-label">Karyawan Baru</span>
                            <HcisIcon name="person-plus-fill" size="xs" color="success" />
                        </div>
                        <strong class="pa-metric-val">{{ formatNumber(bilaDiulang.peserta_baru) }} <small>orang</small></strong>
                        <span class="pa-metric-sub">Masuk sebagai peserta baru</span>
                    </div>

                    <div class="pa-metric-card is-changed">
                        <div class="pa-metric-card-top">
                            <span class="pa-metric-label">Level PA Berubah</span>
                            <HcisIcon name="layers-fill" size="xs" color="warning" />
                        </div>
                        <strong class="pa-metric-val">{{ formatNumber(bilaDiulang.level_berubah) }} <small>peserta</small></strong>
                        <span class="pa-metric-sub">Akan disegarkan otomatis</span>
                    </div>

                    <div class="pa-metric-card is-held">
                        <div class="pa-metric-card-top">
                            <span class="pa-metric-label">Tertahan</span>
                            <HcisIcon name="shield-lock-fill" size="xs" color="muted" />
                        </div>
                        <strong class="pa-metric-val">{{ formatNumber(bilaDiulang.level_tertahan) }} <small>peserta</small></strong>
                        <span class="pa-metric-sub">Sudah memiliki penugasan</span>
                    </div>
                </div>

                <!-- Petunjuk Keamanan -->
                <HcisAlert
                    variant="info"
                    appearance="subtle"
                    size="sm"
                    icon="info-circle-fill"
                    title="Perlindungan Data Penugasan Aktif"
                    description="Peserta yang sudah memiliki penugasan atau aturan khusus tidak akan dirombak paksa untuk mencegah hilangnya data evaluasi yang sedang berjalan."
                />
            </div>

            <!-- Konfirmasi jalan pertama -->
            <div v-else class="pa-confirm-body">
                <!-- Banner Pengantar -->
                <div class="pa-confirm-hero-banner">
                    <div class="pa-confirm-hero-icon">
                        <HcisIcon name="people-fill" size="md" color="brand" />
                    </div>
                    <div class="pa-confirm-hero-desc">
                        <h4 class="pa-confirm-hero-title">Pembentukan Peserta Siklus</h4>
                        <p class="pa-confirm-hero-text">
                            Sistem akan membekukan daftar peserta berdasarkan kriteria eligibilitas dan aturan pengecualian yang telah ditentukan.
                        </p>
                    </div>
                </div>

                <!-- Grid Kartu Ringkasan Metrik -->
                <div class="pa-confirm-metrics-grid">
                    <div class="pa-metric-card is-included">
                        <div class="pa-metric-card-top">
                            <span class="pa-metric-label">Akan Diikutkan</span>
                            <HcisIcon name="check-circle-fill" size="xs" color="brand" />
                        </div>
                        <strong class="pa-metric-val">{{ formatNumber(pratinjau?.cacah?.diikutkan || 0) }} <small>karyawan</small></strong>
                        <span class="pa-metric-sub">Menerima penugasan evaluasi</span>
                    </div>

                    <div class="pa-metric-card is-excluded">
                        <div class="pa-metric-card-top">
                            <span class="pa-metric-label">Dikecualikan Admin</span>
                            <HcisIcon name="person-x-fill" size="xs" color="warning" />
                        </div>
                        <strong class="pa-metric-val">{{ formatNumber(pratinjau?.cacah?.dikecualikan ?? praKecuali.length) }} <small>karyawan</small></strong>
                        <span class="pa-metric-sub">Ditandai manual oleh admin</span>
                    </div>

                    <div class="pa-metric-card is-auto-drop">
                        <div class="pa-metric-card-top">
                            <span class="pa-metric-label">Gugur Otomatis</span>
                            <HcisIcon name="slash-circle" size="xs" color="muted" />
                        </div>
                        <strong class="pa-metric-val">{{ formatNumber(pratinjau?.cacah?.dikecualikan_otomatis || 0) }} <small>karyawan</small></strong>
                        <span class="pa-metric-sub">Masa kerja / status tidak eligible</span>
                    </div>

                    <div class="pa-metric-card is-status">
                        <div class="pa-metric-card-top">
                            <span class="pa-metric-label">Status Siklus Baru</span>
                            <HcisIcon name="flag-fill" size="xs" color="purple" />
                        </div>
                        <strong class="pa-metric-val text-brand-600">PREPARED</strong>
                        <span class="pa-metric-sub">Siklus siap untuk Langkah 5</span>
                    </div>
                </div>

                <!-- Catatan Transparansi -->
                <HcisAlert
                    variant="neutral"
                    appearance="subtle"
                    size="sm"
                    icon="arrow-right-circle-fill"
                    title="Langkah Berikutnya"
                    description="Setelah eksekusi berhasil, baris peserta akan terbentuk secara permanen. Anda dapat mengatur penugasan penilai dan rater di Langkah 5."
                />
            </div>

            <template #footer>
                <div v-if="!menjalankan" class="pa-confirm-footer">
                    <HcisButton variant="secondary" label="Batal" size="sm" @click="dialogJalankan = false" />
                    <HcisButton
                        variant="primary"
                        :label="sudahDijalankan ? 'Ya, Jalankan Ulang Penyiapan' : 'Ya, Jalankan Penyiapan Sekarang'"
                        size="sm"
                        leading-icon="play-fill"
                        @click="jalankan"
                    />
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import {
    HcisAlert,
    HcisBadge,
    HcisButton,
    HcisCheckbox,
    HcisDialog,
    HcisEmptyState,
    HcisField,
    HcisIcon,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisStatCard,
    HcisStatGroup,
    HcisTextarea,
} from '@/components/ui';
import PaAturanMasaKerja from '../../components/PaAturanMasaKerja.vue';
import PaKesiapanHcLearn from './PaKesiapanHcLearn.vue';
import PaPerubahanPeserta from './PaPerubahanPeserta.vue';
import PaStepHeader from './PaStepHeader.vue';
import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaStepPeserta' });

const props = defineProps({
    cycleId: { type: [Number, String], required: true },
    dapatDisunting: { type: Boolean, default: false },

    /* MENGECUALIKAN SATU ORANG tetap boleh sesudah siklus dikunci — `KECUALIKAN_PESERTA`
       di server memang mengizinkan LOCKED dan OPEN, dan di sanalah kebutuhannya justru
       muncul: seseorang resign, atau HC Learn menolak akunnya. Menyamakannya dengan
       `dapatDisunting`, yang menjawab "boleh menyiapkan ULANG peserta?", membuat siklus
       terkunci jadi jalan buntu. */
    bolehKecualikan: { type: Boolean, default: false },
    alasanTerkunci: { type: String, default: '' },
    siapDijalankan: { type: Boolean, default: false },
    penghalang: { type: Array, default: () => [] },

    /* Halaman relasi milik modul SYSTEM; admin PA belum tentu memegangnya. Diteruskan
       halaman induk, bukan ditanyakan sendiri: satu pertanyaan hak akses per halaman. */
    bisaKelolaRelasi: { type: Boolean, default: false },
});

const emit = defineEmits(['tersimpan', 'kembali', 'selesai', 'lanjut']);

const pratinjau = ref(null);
const praKecuali = ref([]);
const daftarLevel = ref([]);
const memuat = ref(false);
const error = ref('');
const pesan = ref('');
const pesanError = ref(false);
const levelTertahan = ref([]);

/* Keadaan penyiapan dari server: sudah dijalankan atau belum, hasil nyatanya, dan apa yang
   berubah bila dijalankan ulang. Respons lama tanpa medan ini = belum dijalankan. */
const sudahDijalankan = computed(() => Boolean(pratinjau.value?.sudah_dijalankan));
const terbentuk = computed(() => ({ diikutkan: 0, dikecualikan: 0, tertunda: 0, ...(pratinjau.value?.terbentuk || {}) }));
const bilaDiulang = computed(() => ({ peserta_baru: 0, level_berubah: 0, level_tertahan: 0, ...(pratinjau.value?.bila_diulang || {}) }));
const adaPerubahan = computed(() => bilaDiulang.value.peserta_baru > 0 || bilaDiulang.value.level_berubah > 0);
/* Minimal masa kerja siklus. Disimpan lalu pratinjau dimuat ulang, supaya angka
   "Gugur Otomatis" langsung memperlihatkan akibatnya. */
const masaKerja = ref(null);
const menyimpanMasaKerja = ref(false);
const keteranganMasaKerja = computed(() => {
    const m = masaKerja.value;
    if (!m) return '';
    if (m.min_masa_kerja_bulan === null) {
        return m.boleh_ubah
            ? 'Siklus ini dibuat sebelum aturan masa kerja ada, jadi belum berlaku. Isi dan simpan untuk memberlakukannya.'
            : '';
    }
    return m.min_masa_kerja_bulan !== m.nilai_master ? `Nilai master saat ini ${m.nilai_master} bulan.` : '';
});

/* Tidak ditunggu pemuatan utama: pengaturan ini pelengkap, dan pratinjau tidak boleh
   tertahan karenanya. */
async function muatMasaKerja() {
    try {
        const res = await paApi.kelayakanMasaKerjaSiklus(props.cycleId);
        masaKerja.value = res?.result ?? res?.data ?? res;
    } catch {
        masaKerja.value = null;
    }
}

async function simpanMasaKerja(bulan) {
    menyimpanMasaKerja.value = true;
    pesan.value = '';
    try {
        const res = await paApi.simpanKelayakanMasaKerjaSiklus(props.cycleId, bulan);
        masaKerja.value = res?.result ?? res?.data ?? res;
        pesan.value = `Minimal masa kerja disimpan: ${bulan} bulan.`;
        pesanError.value = false;
        await muat();
    } catch (e) {
        pesan.value = e?.message || 'Minimal masa kerja gagal disimpan.';
        pesanError.value = true;
    } finally {
        menyimpanMasaKerja.value = false;
    }
}

const praKecualiAktif = computed(() => praKecuali.value.filter((k) => k.dikecualikan_saat_ini !== false).length);

function formatWaktu(t) {
    const d = new Date(String(t).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(t);
    return d.toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const dialogKecuali = ref(false);
const menyimpanKecuali = ref(false);
const sibukCabut = ref(null);
const modePengecualian = ref('cari');
const kataKunciCari = ref('');
const mencariKaryawan = ref(false);
const hasilPencarianKaryawan = ref([]);

const filterKecuali = reactive({
    divisi: [],
    departemen: [],
    id_level: [],
    cabang: [],
});

const opsiFilter = reactive({
    divisi: [],
    departemen: [],
    level: [],
    cabang: [],
});

const memuatOpsiFilter = ref(false);
const bulkKodeText = ref('');
const memprosesBulk = ref(false);

const hasActiveFilter = computed(() => {
    return Boolean(
        (Array.isArray(filterKecuali.divisi) && filterKecuali.divisi.length > 0) ||
        (Array.isArray(filterKecuali.departemen) && filterKecuali.departemen.length > 0) ||
        (Array.isArray(filterKecuali.id_level) && filterKecuali.id_level.length > 0) ||
        (Array.isArray(filterKecuali.cabang) && filterKecuali.cabang.length > 0) ||
        kataKunciCari.value.trim()
    );
});

const alasanPresets = [
    'Cuti Melahirkan / Panjang',
    'Tugas Luar Negeri / Khusus',
    'Baru Mutasi / Promosi',
    'Dalam Masa Sanksi',
    'Karyawan Percobaan / Magang',
];

const terpilih = ref([]);

const formKecuali = reactive({
    kodePerusahaan: '001',
    alasan: '',
});

const dialogJalankan = ref(false);
const menjalankan = ref(false);
const progresSiapkan = ref(0);

/* Tahapan progress simulasi — naik bertahap selama request berjalan, supaya user
   melihat progress bar bergerak, bukan loncat 0→100. Waktu riil tergantung jumlah
   karyawan; bar ini memperkirakan dan mengejar 95% lalu menunggu request selesai. */
const TAHAP_SIAPKAN = [
    { batas: 15, ikon: 'clipboard-data', judul: 'Menyalin aturan penilai…', keterangan: 'Aturan penilai per level disalin ke siklus ini.' },
    { batas: 45, ikon: 'people-fill', judul: 'Membentuk peserta…', keterangan: 'Setiap karyawan aktif dievaluasi kelayakannya dan dimasukkan ke siklus.' },
    { batas: 75, ikon: 'table', judul: 'Menyiapkan baris hasil…', keterangan: 'Baris hasil dibentuk untuk setiap peserta yang diikutkan.' },
    { batas: 95, ikon: 'check-circle', judul: 'Menyelesaikan…', keterangan: 'Status siklus diperbarui menjadi PREPARED.' },
];
const tahapSiapkan = computed(() => {
    const p = progresSiapkan.value;
    return TAHAP_SIAPKAN.find((t) => p < t.batas) || TAHAP_SIAPKAN[TAHAP_SIAPKAN.length - 1];
});
const indeksTahapAktif = computed(() => {
    const p = progresSiapkan.value;
    const idx = TAHAP_SIAPKAN.findIndex((t) => p < t.batas);
    return idx === -1 ? TAHAP_SIAPKAN.length - 1 : idx;
});

/* ------------------------------------------------------------------ *
 * Kesiapan karyawan di HC Learn                                       *
 * ------------------------------------------------------------------ */

const panelHcLearn = ref(null);

/* Pesan dari panel ditampilkan di tempat pesan langkah ini, bukan di dalam panelnya: satu
   langkah punya satu tempat bicara, dan panel yang berteriak sendiri di tengah halaman
   membuat admin mencari pesan di dua tempat. */
function terimaPesanHcLearn({ teks, error }) {
    pesan.value = teks;
    pesanError.value = Boolean(error);
}

const totalKaryawanAktif = computed(() => {
    return daftarLevel.value.reduce((acc, l) => acc + (Number(l.jumlah_karyawan) || 0), 0);
});

function formatNumber(val) {
    if (val === null || val === undefined || isNaN(Number(val))) return '0';
    return Number(val).toLocaleString('id-ID');
}

async function muatOpsiFilter() {
    if (opsiFilter.divisi.length || opsiFilter.level.length) return;
    memuatOpsiFilter.value = true;
    try {
        const res = await paApi.opsiFilterKaryawanPraKecuali(props.cycleId);
        const data = res?.result ?? res?.data ?? res;
        opsiFilter.divisi = data?.divisi ?? [];
        opsiFilter.departemen = data?.departemen ?? [];
        opsiFilter.level = data?.level ?? [];
        opsiFilter.cabang = data?.cabang ?? [];
    } catch {
        // Abaikan jika gagal
    } finally {
        memuatOpsiFilter.value = false;
    }
}

function resetFilterKecuali() {
    filterKecuali.divisi = [];
    filterKecuali.departemen = [];
    filterKecuali.id_level = [];
    filterKecuali.cabang = [];
    kataKunciCari.value = '';
    lakukanPencarianKaryawan();
}

function bukaDialogKecuali() {
    formKecuali.kodePerusahaan = '001';
    formKecuali.alasan = '';
    kataKunciCari.value = '';
    bulkKodeText.value = '';
    filterKecuali.divisi = [];
    filterKecuali.departemen = [];
    filterKecuali.id_level = [];
    filterKecuali.cabang = [];
    hasilPencarianKaryawan.value = [];
    dialogKecuali.value = true;
    muatOpsiFilter();
    lakukanPencarianKaryawan();
}

let timeoutCari = null;
function lakukanPencarianKaryawan() {
    clearTimeout(timeoutCari);
    timeoutCari = setTimeout(async () => {
        mencariKaryawan.value = true;
        try {
            const response = await paApi.cariKaryawanPraKecuali(props.cycleId, {
                q: kataKunciCari.value.trim(),
                divisi: filterKecuali.divisi,
                departemen: filterKecuali.departemen,
                id_level: filterKecuali.id_level,
                cabang: filterKecuali.cabang,
                limit: 1000,
            });
            const data = response?.result ?? response?.data ?? response;
            hasilPencarianKaryawan.value = data?.baris ?? (Array.isArray(data) ? data : []);
        } catch {
            hasilPencarianKaryawan.value = [];
        } finally {
            mencariKaryawan.value = false;
        }
    }, 150);
}

watch(
    () => [
        filterKecuali.divisi,
        filterKecuali.departemen,
        filterKecuali.id_level,
        filterKecuali.cabang,
    ],
    () => {
        lakukanPencarianKaryawan();
    },
    { deep: true }
);

function isKaryawanTerpilih(kar) {
    return terpilih.value.some(
        (x) => x.kode_perusahaan === kar.kode_perusahaan && x.kode_karyawan === kar.kode_karyawan,
    );
}

function togglePilihKaryawan(kar) {
    if (kar.sudah_dikecualikan) return;
    const idx = terpilih.value.findIndex(
        (x) => x.kode_perusahaan === kar.kode_perusahaan && x.kode_karyawan === kar.kode_karyawan,
    );
    if (idx >= 0) {
        terpilih.value.splice(idx, 1);
    } else {
        terpilih.value.push({
            kode_perusahaan: kar.kode_perusahaan,
            kode_karyawan: kar.kode_karyawan,
            nama: kar.nama,
            nama_level: kar.nama_level,
            nama_divisi: kar.nama_divisi,
            nama_departemen: kar.nama_departemen,
            cabang: kar.cabang,
        });
    }
}

const jumlahDapatDipilih = computed(() => {
    return hasilPencarianKaryawan.value.filter((k) => !k.sudah_dikecualikan).length;
});

const jumlahTerpilihDariHasil = computed(() => {
    return hasilPencarianKaryawan.value.filter((k) => isKaryawanTerpilih(k)).length;
});

const daftarHasilUrut = computed(() => {
    if (!hasilPencarianKaryawan.value.length) return [];

    return [...hasilPencarianKaryawan.value].sort((a, b) => {
        const aSelected = isKaryawanTerpilih(a) ? 1 : 0;
        const bSelected = isKaryawanTerpilih(b) ? 1 : 0;

        // 1. Yang terpilih naik ke paling atas
        if (aSelected !== bSelected) {
            return bSelected - aSelected;
        }

        // 2. Yang sudah dikecualikan ditaruh di paling bawah
        const aExcluded = a.sudah_dikecualikan ? 1 : 0;
        const bExcluded = b.sudah_dikecualikan ? 1 : 0;
        if (aExcluded !== bExcluded) {
            return aExcluded - bExcluded;
        }

        // 3. Urutkan berdasarkan nama abjad
        return (a.nama || '').localeCompare(b.nama || '', 'id');
    });
});

function pilihSemuaHasil() {
    const dapatDipilih = hasilPencarianKaryawan.value.filter((k) => !k.sudah_dikecualikan);
    for (const kar of dapatDipilih) {
        if (!isKaryawanTerpilih(kar)) {
            terpilih.value.push({
                kode_perusahaan: kar.kode_perusahaan,
                kode_karyawan: kar.kode_karyawan,
                nama: kar.nama,
                nama_level: kar.nama_level,
                nama_divisi: kar.nama_divisi,
                nama_departemen: kar.nama_departemen,
                cabang: kar.cabang,
            });
        }
    }
}

function batalkanSemuaHasil() {
    const kodes = new Set(hasilPencarianKaryawan.value.map((k) => `${k.kode_perusahaan}|${k.kode_karyawan}`));
    terpilih.value = terpilih.value.filter((k) => !kodes.has(`${k.kode_perusahaan}|${k.kode_karyawan}`));
}

const semuaHasilTerpilih = computed(() => {
    const dapatDipilih = hasilPencarianKaryawan.value.filter((k) => !k.sudah_dikecualikan);
    if (!dapatDipilih.length) return false;
    return dapatDipilih.every((k) => isKaryawanTerpilih(k));
});

async function prosesBulkKodeKaryawan() {
    const text = bulkKodeText.value.trim();
    if (!text) return;

    const rawCodes = text.split(/[\s,;\n\r\t]+/).map((s) => s.trim()).filter(Boolean);
    const uniqueCodes = Array.from(new Set(rawCodes));
    if (!uniqueCodes.length) return;

    memprosesBulk.value = true;
    try {
        const response = await paApi.cariKaryawanPraKecuali(props.cycleId, {
            kode_list: uniqueCodes,
            limit: 200,
        });
        const data = response?.result ?? response?.data ?? response;
        const baris = data?.baris ?? (Array.isArray(data) ? data : []);

        let ditambahCount = 0;
        for (const kar of baris) {
            if (!kar.sudah_dikecualikan && !isKaryawanTerpilih(kar)) {
                terpilih.value.push({
                    kode_perusahaan: kar.kode_perusahaan,
                    kode_karyawan: kar.kode_karyawan,
                    nama: kar.nama,
                    nama_level: kar.nama_level,
                    nama_divisi: kar.nama_divisi,
                    nama_departemen: kar.nama_departemen,
                    cabang: kar.cabang,
                });
                ditambahCount++;
            }
        }
        bulkKodeText.value = '';
        modePengecualian.value = 'cari';
        pesan.value = `Berhasil memuat ${baris.length} karyawan dari ${uniqueCodes.length} kode (${ditambahCount} ditambahkan ke daftar pilih).`;
        pesanError.value = false;
    } catch (e) {
        pesan.value = e?.message || 'Gagal memproses daftar kode karyawan.';
        pesanError.value = true;
    } finally {
        memprosesBulk.value = false;
    }
}

async function muat() {
    memuat.value = true;
    error.value = '';

    try {
        muatMasaKerja();

        const [praRes, kecualiRes, levelRes] = await Promise.allSettled([
            paApi.pratinjauPenyiapan(props.cycleId),
            paApi.daftarPraKecuali(props.cycleId),
            paApi.levelSiklus(props.cycleId),
        ]);

        if (praRes.status === 'fulfilled') {
            pratinjau.value = praRes.value?.result ?? praRes.value?.data ?? praRes.value;
        }

        if (kecualiRes.status === 'fulfilled') {
            const kData = kecualiRes.value?.result ?? kecualiRes.value?.data ?? kecualiRes.value;
            praKecuali.value = kData?.baris ?? [];
        }

        if (levelRes.status === 'fulfilled') {
            const lData = levelRes.value?.result ?? levelRes.value?.data ?? levelRes.value;
            daftarLevel.value = lData?.level ?? [];
        }
    } catch (e) {
        error.value = e?.message || 'Gagal memuat pratinjau peserta.';
    } finally {
        memuat.value = false;
    }
}

async function tambahKecuali() {
    menyimpanKecuali.value = true;
    pesan.value = '';

    try {
        /* Kode perusahaan diambil dari hasil PENCARIAN, tidak diketik lagi. Ia bagian dari
           pengenal orangnya; meminta admin mengetiknya berarti meminta ia menebak, dan
           tebakan yang salah menghasilkan pengecualian yang tidak pernah cocok. */
        const orang = terpilih.value.map((k) => ({
            kode_perusahaan: k.kode_perusahaan,
            kode_karyawan: k.kode_karyawan,
        }));

        const response = await paApi.tambahPraKecuali(props.cycleId, orang, formKecuali.alasan.trim());
        const data = response?.result ?? response?.data ?? response;

        // Membedakan yang ditambah dari yang memang sudah ada: mengirim ulang orang yang
        // sama bukan galat, tetapi admin perlu tahu berapa yang benar-benar baru.
        pesan.value = `${data?.ditambah ?? 0} ditambahkan, ${data?.sudah_ada ?? 0} memang sudah ada.`
            + (data?.peserta_dikecualikan ? ` ${data.peserta_dikecualikan} peserta yang sudah terbentuk ikut dikecualikan dan penugasannya yang belum diisi dibatalkan.` : '');
        dialogKecuali.value = false;
        terpilih.value = [];
        kataKunciCari.value = '';
        hasilPencarianKaryawan.value = [];
        bulkKodeText.value = '';
        formKecuali.alasan = '';

        await muat();
        emit('tersimpan');
    } catch (e) {
        pesan.value = e?.message || 'Gagal menambahkan pengecualian.';
    } finally {
        menyimpanKecuali.value = false;
    }
}

/* PENCABUTAN — satu baris atau banyak, lewat jalur yang SAMA supaya hasil dan langkah
   berikutnya selalu dilaporkan dengan cara yang sama. */
const pilihanCabut = ref([]);
const dialogCabut = ref(false);
const sibukCabutBanyak = ref(false);
const hasilCabut = ref(null);

const semuaTerpilihCabut = computed(
    () => praKecuali.value.length > 0 && pilihanCabut.value.length === praKecuali.value.length,
);
const sebagianTerpilihCabut = computed(
    () => pilihanCabut.value.length > 0 && pilihanCabut.value.length < praKecuali.value.length,
);

function aturPilihSemuaCabut(pilih) {
    pilihanCabut.value = pilih ? praKecuali.value.map((k) => k.id) : [];
}

const perluLengkapiPenugasan = computed(() => (hasilCabut.value?.diikutkan ?? 0) > 0 && hasilCabut.value?.ada_penugasan);
const perluJalankanUlang = computed(() => (hasilCabut.value?.belum_peserta ?? 0) > 0 && sudahDijalankan.value);

const judulHasilCabut = computed(() => {
    const h = hasilCabut.value;
    if (!h) return '';
    const bagian = [`${h.dicabut} pengecualian dicabut`];
    if (h.diikutkan > 0) bagian.push(`${h.diikutkan} diikutkan kembali`);
    if (h.gagal.length) bagian.push(`${h.gagal.length} gagal`);
    return bagian.join(' · ');
});

async function jalankanCabut(ids) {
    pesan.value = '';
    pesanError.value = false;

    try {
        const data = await paApi.cabutPraKecualiBanyak(props.cycleId, ids);
        hasilCabut.value = { gagal: [], ...data };
        pilihanCabut.value = pilihanCabut.value.filter((id) => !ids.includes(id));
        await muat();
        emit('tersimpan');
    } catch (e) {
        pesan.value = e?.message || 'Gagal mencabut pengecualian.';
        pesanError.value = true;
    }
}

async function cabut(k) {
    sibukCabut.value = k.id;
    try {
        await jalankanCabut([k.id]);
    } finally {
        sibukCabut.value = null;
    }
}

async function cabutTerpilih() {
    sibukCabutBanyak.value = true;
    try {
        await jalankanCabut([...pilihanCabut.value]);
        dialogCabut.value = false;
    } finally {
        sibukCabutBanyak.value = false;
    }
}

async function jalankan() {
    menjalankan.value = true;
    progresSiapkan.value = 5;
    pesan.value = '';
    pesanError.value = false;

    // Timer progress animasi bertahap
    const timerProgress = setInterval(() => {
        if (progresSiapkan.value < 90) {
            progresSiapkan.value += Math.floor(Math.random() * 8) + 3;
        } else if (progresSiapkan.value < 96) {
            progresSiapkan.value += 1;
        }
    }, 400);

    try {
        const hasilSiapkan = await paApi.siapkanSiklus(props.cycleId);
        clearInterval(timerProgress);
        progresSiapkan.value = 100;

        // Beri jeda 400ms di 100% sebelum dialog tutup
        await new Promise((r) => setTimeout(r, 400));

        const diperbarui = Number(hasilSiapkan?.peserta?.level_diperbarui || 0);
        levelTertahan.value = hasilSiapkan?.peserta?.level_tertahan || [];
        pesan.value = 'Penyiapan berhasil dijalankan. Data peserta sudah terbentuk; penugasan penilai dibentuk di Langkah 5.'
            + (diperbarui > 0 ? ` ${diperbarui} peserta disegarkan ke Level PA terbaru.` : '');
        dialogJalankan.value = false;
        await muat();
        emit('tersimpan');
        /* Ada peserta yang Level PA-nya tertahan → TETAP di langkah ini supaya daftarnya terbaca.
           Pindah otomatis ke Langkah 5 membuat peringatan itu tidak pernah terlihat (uji browser 9 Okt). */
        if (!levelTertahan.value.length) emit('selesai', { levelDiperbarui: diperbarui });

        panelHcLearn.value?.muat();
    } catch (e) {
        clearInterval(timerProgress);
        pesan.value = e?.message || 'Gagal mengeksekusi penyiapan cycle.';
        pesanError.value = true;
    } finally {
        menjalankan.value = false;
        progresSiapkan.value = 0;
    }
}

/* Panel HC Learn memuat dirinya sendiri saat dipasang — TERPISAH dari `muat()`, dan sengaja:
   yang satu membaca data kita sendiri, yang satu menyeberang ke HC Learn. Menggabungkannya
   ke dalam `Promise.allSettled` yang sama membuat kerangka layar menunggu jaringan luar
   sebelum apa pun tergambar. */
onMounted(muat);
</script>

<style scoped>
/*
 | Badan langkah tetap berwadah.
 |
 | Sebelum kepala langkah dipisah jadi `PaStepHeader`, seluruh isi langkah ini duduk di
 | dalam satu `HcisCard`. Memindahkan kepalanya keluar ikut membawa wadahnya pergi, dan
 | isinya jatuh langsung ke latar halaman — kelihatan seperti kartu yang hilang, bukan
 | seperti keputusan. Wadahnya dikembalikan di sini, disamakan dengan kartu kepalanya.
 */
.pa-step-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 0.9rem;
    padding: 1.15rem 1.35rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    box-sizing: border-box;
    min-width: 0;
}

.pa-step-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-peserta-skeleton {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-stat-skel-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
}

.pa-peserta-content {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.pa-sim-stat-group {
    width: 100%;
}

/* Launch Hero Cockpit */
.pa-penyiapan-status {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1rem 1.5rem;
    align-items: start;
    padding: 1.35rem 1.6rem;
    border: 1px solid rgba(187, 247, 208, 0.9);
    border-radius: var(--hcis-radius-lg, 14px);
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.5) 0%, rgba(248, 250, 252, 0.9) 100%);
    box-shadow: 0 4px 16px -2px rgba(22, 163, 74, 0.05);
    position: relative;
    overflow: hidden;
}

.pa-penyiapan-status::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #10b981, #06b6d4);
}

.pa-penyiapan-status__kepala {
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
}

.pa-penyiapan-status__ikon {
    color: var(--hcis-color-success-600, #16a34a);
    margin-top: 0.15rem;
    flex-shrink: 0;
    background: #dcfce7;
    padding: 0.45rem;
    border-radius: 50%;
}

.pa-penyiapan-status h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 750;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-penyiapan-status p {
    margin: 0.25rem 0 0;
    font-size: 0.84rem;
    color: var(--hcis-color-text-muted, #64748b);
    line-height: 1.45;
}

.pa-penyiapan-status__ulang {
    grid-column: 1 / 2;
    padding: 0.75rem 0.95rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    font-size: 0.82rem;
    color: #334155;
    margin-top: 0.25rem;
}

.pa-penyiapan-status__ulang strong {
    color: #0f172a;
    font-size: 0.82rem;
    display: block;
    margin-bottom: 0.25rem;
}

.pa-penyiapan-status__ulang ul {
    margin: 0.25rem 0 0;
    padding-left: 1.2rem;
}

.pa-penyiapan-status__ulang li {
    margin-bottom: 0.15rem;
}

.pa-penyiapan-status__aksi {
    grid-column: 2;
    grid-row: 1 / span 2;
    align-self: center;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

@media (max-width: 767px) {
    .pa-penyiapan-status {
        grid-template-columns: 1fr;
        padding: 1.15rem 1.25rem;
    }

    .pa-penyiapan-status__ulang {
        padding-left: 0.75rem;
    }

    .pa-penyiapan-status__aksi {
        grid-column: 1;
        grid-row: auto;
    }

    .pa-penyiapan-status__aksi :deep(.hcis-btn) {
        width: 100%;
    }
}

.pa-launch-cockpit-card {
    padding: 1.35rem 1.6rem;
    border-radius: var(--hcis-radius-lg, 14px);
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95) 0%, rgba(238, 242, 255, 0.6) 100%);
    border: 1px solid rgba(199, 210, 254, 0.85);
    box-shadow: 0 4px 16px -2px rgba(79, 70, 229, 0.06);
    position: relative;
    overflow: hidden;
}

.pa-launch-cockpit-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #4f46e5, #06b6d4, #10b981);
}

.pa-launch-cockpit-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.pa-launch-cockpit-text {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    max-width: 40rem;
}

.pa-cockpit-badge-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-launch-cockpit-text .pa-eyebrow {
    color: var(--hcis-brand-600, #4f46e5);
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}

.pa-launch-cockpit-text h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 750;
    color: #0f172a;
}

.pa-launch-cockpit-text p {
    margin: 0;
    font-size: 0.84rem;
    color: #475569;
    line-height: 1.48;
}

.pa-cockpit-quick-checks {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
}

.pa-quick-check-item {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 650;
    color: #166534;
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
}

.pa-launch-cockpit-action {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.45rem;
}

.pa-launch-cta-btn :deep(.hcis-btn) {
    padding: 0.65rem 1.25rem !important;
    font-size: 0.88rem !important;
    font-weight: 750 !important;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25) !important;
}

.pa-blocked-hint {
    font-size: 0.74rem;
    color: #b45309;
    background: #fffbeb;
    border: 1px solid #fde68a;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    max-width: 22rem;
}

.pa-ready-hint {
    font-size: 0.74rem;
    color: #15803d;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* Level Breakdown Simulation Grid */
.pa-level-sim-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-section-head {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-eyebrow {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.07em;
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-section-head h4 {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-level-sim-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 0.85rem;
}

.pa-level-sim-card {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    padding: 0.95rem 1.05rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 160ms cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-level-sim-card:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px -1px rgba(0, 0, 0, 0.06);
}

.pa-level-sim-card.is-incomplete {
    border-color: #fed7aa;
    background: #fffdfb;
}

.pa-level-sim-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.pa-level-sim-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}

.pa-level-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-level-sim-tag {
    font-size: 0.65rem;
    color: #64748b;
    background: #f1f5f9;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 600;
    flex-shrink: 0;
}

.pa-level-sim-stat {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-sim-count-row {
    display: flex;
    align-items: baseline;
    gap: 0.45rem;
    width: 100%;
    justify-content: space-between;
}

.pa-sim-count {
    font-size: 0.95rem;
    font-weight: 800;
    color: #1e1b4b;
    font-variant-numeric: tabular-nums;
}

.pa-sim-pct {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-brand-600, #4f46e5);
    background: #eef2ff;
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.pa-sim-progress {
    height: 6px;
    background: #f1f5f9;
    border-radius: 9999px;
    overflow: hidden;
}

.pa-sim-fill {
    height: 100%;
    background: linear-gradient(90deg, #4f46e5, #06b6d4);
    border-radius: 9999px;
    transition: width 0.3s ease;
}

.pa-level-config-flags {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding-top: 0.2rem;
    border-top: 1px dashed #f1f5f9;
}

.pa-config-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.64rem;
    font-weight: 650;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
}

.pa-config-flag.is-valid {
    color: #15803d;
    background: #f0fdf4;
}

.pa-config-flag.is-missing {
    color: #b45309;
    background: #fffbeb;
}

/* Pre-Exclusion Section */
.pa-pra-kecuali-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.25rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-pra-kecuali-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.pa-pra-kecuali-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-pra-kecuali-head h4 {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-pra-kecuali-empty {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    font-size: 0.8rem;
    color: #64748b;
    flex-wrap: wrap;
}

.pa-pra-kecuali-empty-content {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-pra-kecuali-table-wrap {
    border-radius: var(--hcis-radius-md, 10px);
    /* Bisa digeser di HP: dengan `hidden`, kolom Status dan tombol Cabut terpotong di 390 px. */
    overflow-x: auto;
    background: #ffffff;
    border: 1px solid var(--hcis-border-subtle, #e2e8f0);
}

.pa-cabut-alat {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 0.75rem;
    margin-bottom: 0.625rem;
}

.pa-cabut-kolom {
    width: 2.5rem;
}

.pa-pra-kecuali-table tr.is-terpilih td {
    background: #f8fafc;
}

.pa-cabut-hasil {
    margin-bottom: 0.75rem;
}

.pa-cabut-hasil__teks {
    margin: 0.25rem 0 0;
}

.pa-cabut-hasil__gagal {
    margin: 0.375rem 0 0;
    padding-left: 1.1rem;
}

.pa-cabut-hasil__aksi {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.625rem;
}

.pa-cabut-dialog__teks {
    margin: 0;
    color: #334155;
    font-size: 0.875rem;
    line-height: 1.55;
}

.pa-pra-kecuali-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.pa-pra-kecuali-table th {
    padding: 0.7rem 0.9rem;
    background: #f8fafc;
    font-weight: 700;
    color: var(--hcis-text-strong, #334155);
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

.pa-pra-kecuali-table td {
    padding: 0.75rem 0.9rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.pa-person-cell {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.pa-person-avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: #eef2ff;
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-person-info {
    display: flex;
    flex-direction: column;
}

.pa-person-info strong {
    font-size: 0.84rem;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-person-info small {
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-reason-text {
    font-style: italic;
    color: #475569;
    background: #f8fafc;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    border-left: 3px solid #f59e0b;
    display: inline-block;
}

.pa-revoke-btn {
    color: #dc2626 !important;
}

.pa-revoke-btn:hover {
    background: #fee2e2 !important;
}

.pa-step-footer {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
    flex-wrap: wrap;
}

.pa-step-nav-buttons {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.pa-step-message {
    font-size: 0.8rem;
    color: var(--hcis-success, #059669);
    font-weight: 550;
}

.pa-step-message.is-error {
    color: var(--hcis-danger, #dc2626);
}

/* Modal Form */
.pa-kecuali-form {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-kecuali-mode-tabs {
    display: flex;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 8px;
    gap: 0.25rem;
}

.pa-mode-tab {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.4rem;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 650;
    color: #64748b;
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-mode-tab.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.pa-form-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-field-label {
    font-size: 0.75rem;
    font-weight: 650;
    color: var(--hcis-text-strong, #0f172a);
}

/* Modal Filter & Multi-Select Architecture */
.pa-filter-search-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-modal-filters-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
    padding: 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.pa-filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-filter-label {
    font-size: 0.68rem;
    font-weight: 650;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.pa-search-action-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-search-input-wrap {
    position: relative;
    flex: 1;
}

.pa-spin-icon {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    animation: spin 1s linear infinite;
    color: #6366f1;
}

/* Results Box & Multi-Select Table/List */
.pa-results-box {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.pa-results-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.pa-results-count {
    font-size: 0.75rem;
    color: #475569;
    display: flex;
    align-items: center;
}

.pa-results-count strong {
    color: #0f172a;
    margin-right: 0.25rem;
}

.pa-count-badge {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 700;
    color: #15803d;
    background: #dcfce7;
    border: 1px solid #86efac;
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    margin-left: 0.4rem;
}

.pa-quick-select-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: #4f46e5;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 6px;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-quick-select-btn:hover:not(:disabled) {
    background: #e0e7ff;
    color: #3730a3;
}

.pa-quick-select-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pa-results-loading,
.pa-results-empty {
    padding: 1.5rem;
    text-align: center;
    font-size: 0.78rem;
    color: #64748b;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.pa-results-list {
    max-height: 18rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.pa-employee-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 120ms ease;
}

.pa-employee-row:last-child {
    border-bottom: 0;
}

.pa-employee-row:hover:not(.is-disabled) {
    background: #f8faff;
}

.pa-employee-row.is-selected {
    background: #eff6ff;
}

.pa-employee-row.is-disabled {
    opacity: 0.55;
    cursor: not-allowed;
    background: #fafafa;
}

.pa-row-checkbox-col {
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-row-checkbox {
    width: 1rem;
    height: 1rem;
    cursor: pointer;
    accent-color: #4f46e5;
}

.pa-row-info-col {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-row-primary-line {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.pa-employee-name {
    font-size: 0.82rem;
    color: #0f172a;
}

.pa-code-pill {
    font-size: 0.68rem;
    font-family: var(--hcis-font-mono, monospace);
    background: #f1f5f9;
    color: #475569;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.pa-level-pill {
    font-size: 0.68rem;
    font-weight: 600;
    background: #ede9fe;
    color: #6b21a8;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
}

.pa-row-secondary-line {
    font-size: 0.72rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.pa-branch-pill {
    font-size: 0.65rem;
    background: #e0f2fe;
    color: #0369a1;
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
}

.pa-row-status-col {
    display: flex;
    align-items: center;
}

.pa-row-hint-text {
    font-size: 0.68rem;
    color: #94a3b8;
}

/* Bulk Kode Karyawan */
.pa-bulk-container {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.pa-bulk-instructions strong {
    font-size: 0.8rem;
    color: #0f172a;
    display: block;
}

.pa-bulk-instructions p {
    font-size: 0.74rem;
    color: #64748b;
    margin: 0.15rem 0 0;
}

.pa-bulk-actions {
    display: flex;
    justify-content: flex-end;
}

/* Selected Tray */
.pa-selected-tray {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.65rem 0.75rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
}

.pa-tray-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pa-tray-title {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    color: #166534;
}

.pa-clear-all-btn {
    border: 0;
    background: transparent;
    font-size: 0.7rem;
    font-weight: 600;
    color: #dc2626;
    cursor: pointer;
    text-decoration: underline;
    padding: 0;
}

.pa-clear-all-btn:hover {
    color: #b91c1c;
}

.pa-tray-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    max-height: 6.5rem;
    overflow-y: auto;
}

.pa-terpilih-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    padding: 0.15rem 0.3rem 0.15rem 0.45rem;
    border-radius: 999px;
    border: 1px solid #86efac;
    background: #ffffff;
    color: #166534;
}

.pa-chip-code {
    font-family: var(--hcis-font-mono, monospace);
    font-weight: 700;
    font-size: 0.66rem;
    color: #15803d;
}

.pa-chip-name {
    font-weight: 600;
}

.pa-terpilih-chip button {
    border: 0;
    background: transparent;
    cursor: pointer;
    font-size: 0.85rem;
    line-height: 1;
    padding: 0 0.15rem;
    color: #dc2626;
    font-weight: bold;
}

.pa-reason-presets {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    margin-bottom: 0.25rem;
}

.pa-reason-chip {
    padding: 0.25rem 0.55rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 150ms ease;
}

.pa-reason-chip:hover {
    border-color: #f59e0b;
    background: #fef3c7;
    color: #78350f;
}

.pa-reason-chip.is-active {
    border-color: #6366f1;
    background: #e0e7ff;
    color: #3730a3;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(99, 102, 241, 0.15);
}

/* Confirm Body */
.pa-exec-progress {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 0.25rem 0;
}

/* Hero Kemajuan */
.pa-exec-hero-card {
    background: linear-gradient(135deg, #f8faff 0%, #eef2ff 100%);
    border: 1px solid #c7d2fe;
    border-radius: 12px;
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.95rem;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.05);
}

.pa-exec-hero-main {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}

.pa-exec-pulse-icon {
    position: relative;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #4f46e5;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
}

.pa-exec-pulse-ring {
    position: absolute;
    inset: -3px;
    border-radius: 13px;
    border: 2px solid #6366f1;
    animation: pa-pulse 1.8s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    pointer-events: none;
}

@keyframes pa-pulse {
    0%, 100% {
        opacity: 0.8;
        transform: scale(1);
    }
    50% {
        opacity: 0;
        transform: scale(1.15);
    }
}

.pa-exec-hero-text {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
    min-width: 0;
}

.pa-exec-stage-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.12rem 0.5rem;
    border-radius: 999px;
    width: fit-content;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-exec-live-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #4f46e5;
    animation: pa-pulse-dot 1.2s infinite ease-in-out;
}

@keyframes pa-pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.8); }
}

.pa-exec-hero-title {
    font-size: 1rem;
    font-weight: 750;
    color: #0f172a;
    line-height: 1.35;
}

.pa-exec-hero-desc {
    margin: 0;
    font-size: 0.8rem;
    color: #475569;
    line-height: 1.45;
}

/* Progress Bar Section */
.pa-exec-bar-section {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding-top: 0.15rem;
}

.pa-exec-bar-meta {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.pa-exec-bar-label {
    font-size: 0.75rem;
    font-weight: 650;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.pa-exec-bar-val {
    font-size: 1.15rem;
    font-weight: 850;
    color: #4338ca;
    font-variant-numeric: tabular-nums;
    font-family: var(--hcis-font-mono, monospace);
}

.pa-exec-progress-track {
    height: 10px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
}

.pa-exec-progress-bar {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #6366f1 0%, #4338ca 100%);
    transition: width 0.35s ease;
    position: relative;
    overflow: hidden;
}

.pa-exec-progress-glow {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.35) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    animation: pa-shimmer 2s infinite linear;
}

@keyframes pa-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Stepper Transparansi */
.pa-exec-stepper {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.4rem 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-exec-step-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.45rem 0.65rem;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.pa-exec-step-item.is-done {
    background: #f8fafc;
}

.pa-exec-step-item.is-done .pa-exec-step-title {
    color: #334155;
    font-weight: 600;
}

.pa-exec-step-item.is-done .pa-exec-step-hint {
    color: #94a3b8;
}

.pa-exec-step-item.is-active {
    background: #eef2ff;
    border: 1px solid #c7d2fe;
}

.pa-exec-step-item.is-active .pa-exec-step-title {
    color: #312e81;
    font-weight: 750;
}

.pa-exec-step-item.is-active .pa-exec-step-hint {
    color: #4338ca;
}

.pa-exec-step-item.is-pending {
    opacity: 0.55;
}

.pa-exec-step-indicator {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-exec-step-num {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-exec-step-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #c7d2fe;
    border-top-color: #4f46e5;
    border-radius: 50%;
    animation: pa-spin 0.8s linear infinite;
}

@keyframes pa-spin {
    to { transform: rotate(360deg); }
}

.pa-exec-step-content {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    flex: 1;
    min-width: 0;
}

.pa-exec-step-title {
    font-size: 0.83rem;
    color: #1e293b;
    line-height: 1.3;
}

.pa-exec-step-hint {
    font-size: 0.73rem;
    color: #64748b;
    line-height: 1.3;
}

.pa-exec-step-badge {
    font-size: 0.68rem;
    font-weight: 700;
    color: #4338ca;
    background: #e0e7ff;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    flex-shrink: 0;
}

/* Notice Card */
.pa-exec-notice-card {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.75rem 0.9rem;
    background: #fffbeb;
    border: 1px solid #fef3c7;
    border-radius: 8px;
}

.pa-exec-notice-text {
    margin: 0;
    font-size: 0.78rem;
    color: #92400e;
    line-height: 1.45;
}

.pa-confirm-body {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-confirm-summary-box {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 0.65rem;
    padding: 0.95rem;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
}

.pa-confirm-stat {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    padding: 0.5rem 0.65rem;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.pa-confirm-stat span {
    font-size: 0.68rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.pa-confirm-stat strong {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
}

.pa-confirm-note {
    margin: 0;
    font-size: 0.8rem;
    color: #475569;
    line-height: 1.45;
}

/* Confirm Hero Banner & Metrics Grid */
.pa-confirm-hero-banner {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.95rem 1.15rem;
    border-radius: var(--hcis-radius-md, 12px);
    background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);
    border: 1px solid #bae6fd;
}

.pa-confirm-hero-banner.is-repeat {
    background: linear-gradient(135deg, #f8faff 0%, #eef2ff 100%);
    border-color: #c7d2fe;
}

.pa-confirm-hero-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.8);
}

.pa-confirm-hero-desc {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pa-confirm-hero-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 750;
    color: #0f172a;
    line-height: 1.3;
}

.pa-confirm-hero-text {
    margin: 0;
    font-size: 0.78rem;
    color: #475569;
    line-height: 1.45;
}

.pa-confirm-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
}

.pa-metric-card {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.85rem 0.95rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    transition: all 150ms ease;
}

.pa-metric-card:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06);
}

.pa-metric-card.is-included {
    border-top: 3px solid #6366f1;
    background: linear-gradient(180deg, #f8faff 0%, #ffffff 60%);
}

.pa-metric-card.is-excluded {
    border-top: 3px solid #f59e0b;
    background: linear-gradient(180deg, #fffdf8 0%, #ffffff 60%);
}

.pa-metric-card.is-auto-drop {
    border-top: 3px solid #94a3b8;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 60%);
}

.pa-metric-card.is-status {
    border-top: 3px solid #8b5cf6;
    background: linear-gradient(180deg, #faf5ff 0%, #ffffff 60%);
}

.pa-metric-card.is-new {
    border-top: 3px solid #10b981;
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 60%);
}

.pa-metric-card.is-changed {
    border-top: 3px solid #f59e0b;
    background: linear-gradient(180deg, #fffdf8 0%, #ffffff 60%);
}

.pa-metric-card.is-held {
    border-top: 3px solid #64748b;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 60%);
}

.pa-metric-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.pa-metric-label {
    font-size: 0.68rem;
    font-weight: 750;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.pa-metric-val {
    font-size: 1.25rem;
    font-weight: 850;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
}

.pa-metric-val small {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
}

.pa-metric-sub {
    font-size: 0.72rem;
    color: #64748b;
    line-height: 1.35;
}

.pa-confirm-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.65rem;
    width: 100%;
}

@media (max-width: 768px) {
    .pa-confirm-metrics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .pa-confirm-hero-banner {
        flex-direction: column;
        align-items: flex-start;
    }
    .pa-confirm-metrics-grid {
        grid-template-columns: 1fr;
    }
    .pa-confirm-footer {
        flex-direction: column-reverse;
        align-items: stretch;
    }
    .pa-confirm-footer :deep(.hcis-btn) {
        width: 100%;
    }
}
</style>

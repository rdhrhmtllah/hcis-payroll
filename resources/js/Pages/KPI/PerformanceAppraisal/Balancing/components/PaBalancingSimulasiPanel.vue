<template>
    <div class="pa-bal-sim-wrap" :class="{ 'is-modal': modeModal, 'pa-bal-sim-card': !modeModal }" aria-live="polite">
        <!-- Header hanya di mode kartu; di dalam HcisDialog judul milik dialog -->
        <header v-if="!modeModal" class="pa-bal-sim__header">
            <div class="pa-bal-sim__pita-wrap">
                <HcisIcon name="magic" size="xs" class="text-brand" />
                <strong class="pa-bal-sim__pita">SIMULASI — belum tersimpan</strong>
            </div>
            <button type="button" class="pa-bal-sim__close-btn" title="Tutup panel simulasi" @click="$emit('tutup')">
                <HcisIcon name="x" size="xs" />
            </button>
        </header>

        <div v-if="basi" class="pa-bal-sim__basi-wrap">
            <p class="pa-bal-sim__basi" role="status">
                <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                Isian sudah berubah sejak simulasi ini. Jalankan simulasi ulang supaya angkanya sesuai.
            </p>
            <HcisButton label="Simulasi Ulang" variant="warning" size="xs" leading-icon="arrow-clockwise" @click="$emit('simulasi-ulang')" />
        </div>

        <HcisSkeleton v-if="memuat" :rows="6" />

        <template v-else-if="hasil">
            <!-- Ringkasan -->
            <div class="pa-bal-sim-ringkas">
                <div class="pa-bal-sim-kartu">
                    <span>Peserta sesi</span>
                    <strong>{{ hasil.peserta?.length || 0 }}</strong>
                    <small>{{ jumlahFinalBerubah }} nilai Final berubah</small>
                </div>
                <div class="pa-bal-sim-kartu is-brand">
                    <span>Rata-rata Final sesi</span>
                    <strong>
                        {{ formatNilai(rataSesi.sebelum) }}
                        <template v-if="rataSesi.sebelum !== rataSesi.sesudah">
                            <HcisIcon name="arrow-right" size="2xs" /> {{ formatNilai(rataSesi.sesudah) }}
                        </template>
                    </strong>
                    <small v-if="rataSesi.sebelum !== rataSesi.sesudah" :class="rataSesi.sesudah > rataSesi.sebelum ? 'is-naik' : 'is-turun'">{{ selisih(rataSesi.sebelum, rataSesi.sesudah) }}</small>
                    <small v-else>Tidak berubah</small>
                </div>
                <div class="pa-bal-sim-kartu is-ungu">
                    <span>Grade bergeser</span>
                    <strong>{{ jumlahGradeBerubah }}</strong>
                    <small>dari {{ hasil.peserta?.length || 0 }} peserta sesi</small>
                </div>
                <div class="pa-bal-sim-kartu is-teal">
                    <span>Pindah kotak HAV</span>
                    <strong>{{ pindahHav.length }}</strong>
                    <small>seluruh siklus</small>
                </div>
                <div class="pa-bal-sim-kartu" :class="{ 'is-warning': hasil.terdampak?.length }">
                    <span>Di luar sesi terdampak</span>
                    <strong>{{ hasil.terdampak?.length || 0 }}</strong>
                    <small>{{ hasil.metode === 'KURVA' ? 'akibat kurva grup bergeser' : 'metode pita: tidak ada' }}</small>
                </div>
            </div>

            <p class="pa-bal-sim__catatan">
                <HcisIcon name="info-circle" size="2xs" />
                <span v-if="hasil.metode === 'KURVA'">Metode <b>Kurva</b>: grade ditentukan posisi nilai terhadap rata-rata (μ) dan simpangan baku (σ) grupnya. Ubah satu orang, μ dan σ grup ikut bergeser, sehingga grade orang lain di grup itu bisa ikut berubah.</span>
                <span v-else>Metode <b>Pita</b>: grade memakai rentang nilai tetap, jadi hanya peserta sesi yang bisa berubah.</span>
            </p>

            <!-- Tab -->
            <div class="pa-bal-sim-tab" role="tablist">
                <button v-for="t in TAB" :key="t.kunci" type="button" role="tab" :aria-selected="tabAktif === t.kunci" :class="{ 'is-aktif': tabAktif === t.kunci }" @click="tabAktif = t.kunci">
                    <HcisIcon :name="t.ikon" size="2xs" />
                    <span>{{ t.label }}</span>
                    <b v-if="t.angka">{{ t.angka }}</b>
                </button>
            </div>

            <!-- ═════ TAB 1 · PERUBAHAN ═════ -->
            <section v-show="tabAktif === 'perubahan'" class="pa-bal-sim-isi">
                <div class="pa-bal-sim-tabel-wrap">
                    <table class="pa-bal-sim-tabel">
                        <thead>
                            <tr>
                                <th>Peserta</th>
                                <th class="is-kanan">Komp</th>
                                <th class="is-kanan">Final</th>
                                <th>Grade</th>
                                <th>Kotak HAV</th>
                                <th>Sebab</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in pesertaUrut" :key="b.participant_id" :class="{ 'is-geser': b.sebelum.grade !== b.sesudah.grade || b.sebelum.kode_kuadran !== b.sesudah.kode_kuadran }">
                                <td>
                                    <div class="pa-bal-sim-orang">
                                        <HcisAvatar :name="b.nama" size="xs" />
                                        <strong>{{ b.nama }}</strong>
                                    </div>
                                </td>
                                <td class="is-kanan">
                                    <span class="pa-bal-sim-trans">
                                        <s v-if="berubahAngka(b.sebelum.nilai_kompetensi, b.sesudah.nilai_kompetensi)">{{ formatNilai(b.sebelum.nilai_kompetensi) }}</s>
                                        <b>{{ formatNilai(b.sesudah.nilai_kompetensi) }}</b>
                                    </span>
                                </td>
                                <td class="is-kanan">
                                    <span class="pa-bal-sim-trans">
                                        <s v-if="berubahAngka(b.sebelum.final, b.sesudah.final)">{{ formatNilai(b.sebelum.final) }}</s>
                                        <b>{{ formatNilai(b.sesudah.final) }}</b>
                                        <span v-if="berubahAngka(b.sebelum.final, b.sesudah.final)" class="pa-bal-sim-chip" :class="b.sesudah.final > b.sebelum.final ? 'is-naik' : 'is-turun'">{{ selisih(b.sebelum.final, b.sesudah.final) }}</span>
                                    </span>
                                </td>
                                <td>
                                    <span class="pa-bal-sim-grade">
                                        <span class="pa-bal-sim-grade__tag">{{ labelGrade(b.sebelum.grade) }}</span>
                                        <template v-if="b.sebelum.grade !== b.sesudah.grade">
                                            <HcisIcon name="arrow-right" size="2xs" />
                                            <span class="pa-bal-sim-grade__tag is-baru">{{ labelGrade(b.sesudah.grade) }}</span>
                                        </template>
                                    </span>
                                </td>
                                <td>
                                    <span class="pa-bal-sim-kotak">
                                        <span class="pa-bal-sim-kotak__no" :class="`is-s-${sentimen(noKotak(b.sebelum.kode_kuadran))}`">{{ noKotak(b.sebelum.kode_kuadran) || '–' }}</span>
                                        <template v-if="b.sebelum.kode_kuadran !== b.sesudah.kode_kuadran">
                                            <HcisIcon name="arrow-right" size="2xs" />
                                            <span class="pa-bal-sim-kotak__no" :class="`is-s-${sentimen(noKotak(b.sesudah.kode_kuadran))}`">{{ noKotak(b.sesudah.kode_kuadran) || '–' }}</span>
                                        </template>
                                        <small>{{ namaKotak(b.sesudah.kode_kuadran) }}</small>
                                    </span>
                                </td>
                                <td>
                                    <HcisBadge v-if="b.sebab" :label="LABEL_SEBAB[b.sebab] || b.sebab" :variant="b.sebab === 'BALANCING' ? 'brand' : b.sebab === 'KURVA' ? 'warning' : 'info'" size="xs" appearance="subtle" />
                                    <span v-else class="pa-bal-sim-redup">Tetap</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="hasil.terdampak?.length" class="pa-bal-sim-luar">
                    <h4><HcisIcon name="exclamation-circle-fill" size="xs" /> Di luar sesi, ikut bergeser ({{ hasil.terdampak.length }})</h4>
                    <p>Mereka tidak dibahas di sesi ini, tetapi grade-nya berubah karena μ dan σ grup kurvanya bergeser.</p>
                    <ul>
                        <li v-for="b in hasil.terdampak" :key="b.participant_id">
                            <strong>{{ b.nama }}</strong>
                            <span class="pa-bal-sim-grade">
                                <span class="pa-bal-sim-grade__tag">{{ labelGrade(b.sebelum.grade) }}</span>
                                <HcisIcon name="arrow-right" size="2xs" />
                                <span class="pa-bal-sim-grade__tag is-baru">{{ labelGrade(b.sesudah.grade) }}</span>
                            </span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- ═════ TAB 2 · HAV MAP ═════ -->
            <section v-show="tabAktif === 'hav'" class="pa-bal-sim-isi">
                <div class="pa-bal-sim-alat">
                    <div class="pa-bal-sim-seg" role="group" aria-label="Tampilkan HAV">
                        <button type="button" :class="{ 'is-aktif': sisiHav === 'sebelum' }" @click="sisiHav = 'sebelum'"><HcisIcon name="clock-history" size="2xs" /> Sebelum</button>
                        <button type="button" :class="{ 'is-aktif': sisiHav === 'sesudah' }" @click="sisiHav = 'sesudah'"><HcisIcon name="magic" size="2xs" /> Sesudah simulasi</button>
                    </div>
                    <label class="pa-bal-sim-centang"><input v-model="hanyaSesi" type="checkbox"> Hanya peserta sesi</label>
                </div>

                <div class="pa-bal-hv">
                    <div class="pa-bal-hv__kolom">
                        <div class="pa-bal-hv__sudut">
                            <span><HcisIcon name="arrow-up" size="2xs" /> Kompetensi</span>
                            <span><HcisIcon name="arrow-right" size="2xs" /> Kinerja</span>
                        </div>
                        <div v-for="k in SUMBU_X" :key="k.nama" class="pa-bal-hv__sumbu-x">
                            <span class="pa-bal-hv__titik" :class="k.kelas" />
                            <strong>{{ k.nama }}</strong>
                            <small>{{ k.ket }}</small>
                        </div>
                    </div>
                    <div class="pa-bal-hv__badan">
                        <div class="pa-bal-hv__baris">
                            <div v-for="k in SUMBU_Y" :key="k.nama" class="pa-bal-hv__sumbu-y" :class="k.kelas">
                                <HcisBadge :label="k.lencana" :variant="k.varian" appearance="solid" size="xs" />
                                <strong>{{ k.nama }}</strong>
                            </div>
                        </div>
                        <div class="pa-bal-hv__grid">
                            <HcisCard
                                v-for="k in kotakHav"
                                :key="k.Nomor_Box"
                                variant="glass"
                                :sentiment="sentimen(k.Nomor_Box)"
                                :pattern="pola(k.Nomor_Box)"
                                :icon="ikon(k.Nomor_Box)"
                                size="sm"
                                class="pa-bal-hv__kotak"
                            >
                                <template #title><span class="pa-bal-hv__judul">{{ k.Nama_Kuadran }}</span></template>
                                <template #badge>
                                    <div class="pa-bal-hv__lencana">
                                        <HcisBadge :label="`BOX ${k.Nomor_Box}`" :variant="sentimen(k.Nomor_Box)" appearance="solid" size="xs" />
                                        <HcisBadge :label="`${k.isi.length} org`" :variant="k.isi.length ? sentimen(k.Nomor_Box) : 'neutral'" :appearance="k.isi.length ? 'glass' : 'subtle'" size="xs" />
                                        <span v-if="sisiHav === 'sesudah' && k.selisih" class="pa-bal-sim-chip" :class="k.selisih > 0 ? 'is-naik' : 'is-turun'">{{ k.selisih > 0 ? `+${k.selisih}` : k.selisih }}</span>
                                    </div>
                                </template>
                                <template #description>
                                    <span class="pa-bal-hv__sumbu">{{ k.Tingkat_Kompetensi }} Komp. · {{ k.Tingkat_Performa }} Perf.</span>
                                </template>
                                <ul v-if="k.isi.length" class="pa-bal-hv__daftar">
                                    <li v-for="o in k.isi" :key="o.participant_id" :class="{ 'is-sesi': o.sesi, 'is-pindah': o.pindah }" :title="o.pindah ? `Pindah dari Box ${o.dari}` : o.nama">
                                        <span class="pa-bal-hv__inisial">{{ inisial(o.nama) }}</span>
                                        <span class="pa-bal-hv__nama">{{ o.nama }}</span>
                                        <span v-if="o.pindah && sisiHav === 'sesudah'" class="pa-bal-hv__dari">dari {{ o.dari || '–' }}</span>
                                        <span v-else-if="o.pindah" class="pa-bal-hv__dari is-ke">ke {{ o.ke || '–' }}</span>
                                        <span v-else-if="o.sesi" class="pa-bal-hv__sesi">Sesi</span>
                                    </li>
                                </ul>
                                <p v-else class="pa-bal-hv__kosong"><HcisIcon name="person-dash" size="2xs" /> 0 peserta</p>
                            </HcisCard>
                        </div>
                    </div>
                </div>
                <p class="pa-bal-sim-legenda">
                    <span><i class="is-sesi" /> Peserta sesi</span>
                    <span><i class="is-pindah" /> Pindah kotak karena simulasi</span>
                </p>
            </section>

            <!-- ═════ TAB 3 · KURVA NORMAL / DISTRIBUSI ═════ -->
            <section v-show="tabAktif === 'kurva'" class="pa-bal-sim-isi">
                <template v-if="hasil.metode === 'KURVA' && grupKurva.length">
                    <!-- Toolbar: Pemilih Grup + Tombol Panduan -->
                    <div class="pa-bal-sim-alat pa-bal-kv-toolbar">
                        <div class="pa-bal-kv-grup-wrap">
                            <span class="pa-bal-kv-grup-label">
                                <HcisIcon name="diagram-2" size="2xs" />
                                <span>Grup Kurva:</span>
                            </span>
                            <div class="pa-bal-sim-grup" role="group" aria-label="Pilih grup kurva">
                                <button
                                    v-for="g in grupKurva"
                                    :key="g.id"
                                    type="button"
                                    :class="{ 'is-aktif': grupAktif?.id === g.id }"
                                    @click="grupAktifId = g.id"
                                >
                                    <span class="pa-bal-sim-grup__nama">{{ g.nama }}</span>
                                    <span class="pa-bal-sim-grup__n">{{ g.sesudah.n }}</span>
                                    <span v-if="g.adaSesi" class="pa-bal-sim-grup__sesi-pill" title="Ada peserta sesi di grup ini">
                                        <span class="pa-bal-sim-grup__titik" />
                                        Sesi
                                    </span>
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="pa-bal-kv-panduan-btn"
                            :class="{ 'is-buka': bukaPanduan }"
                            @click="bukaPanduan = !bukaPanduan"
                        >
                            <HcisIcon name="lightbulb" size="2xs" />
                            <span>{{ bukaPanduan ? 'Tutup Panduan' : 'Panduan Membaca Kurva' }}</span>
                            <HcisIcon name="chevron-down" size="2xs" class="pa-bal-kv-panduan-btn__panah" />
                        </button>
                    </div>

                    <!-- Panduan membaca kurva (collapsible) -->
                    <transition name="pa-bal-slide">
                        <div v-show="bukaPanduan" class="pa-bal-kv-panduan-card">
                            <div class="pa-bal-kv-panduan-card__head">
                                <div class="pa-bal-kv-panduan-card__head-title">
                                    <HcisIcon name="info-circle" size="xs" class="text-indigo-600" />
                                    <strong>Panduan Membaca Kurva Normal (Distribusi Gaussian)</strong>
                                </div>
                                <small>4 prinsip penting dalam pemodelan grade balancing</small>
                            </div>
                            <div class="pa-bal-kv-panduan-grid">
                                <div class="pa-bal-kv-panduan-item">
                                    <div class="pa-bal-kv-panduan-item__badge">1</div>
                                    <div class="pa-bal-kv-panduan-item__content">
                                        <strong>Populasi Grup Menyeluruh</strong>
                                        <p>Kurva dibentuk dari total <b>{{ grupAktif?.sesudah.n }} orang</b> di grup <b>{{ grupAktif?.nama }}</b>. Peserta di luar sesi ini ikut serta membentuk kurva dengan nilai berjalannya.</p>
                                    </div>
                                </div>
                                <div class="pa-bal-kv-panduan-item">
                                    <div class="pa-bal-kv-panduan-item__badge">2</div>
                                    <div class="pa-bal-kv-panduan-item__content">
                                        <strong>Batas Grade Relatif (μ + z · σ)</strong>
                                        <p>Batas grade ditentukan oleh posisi nilai terhadap rata-rata (μ) dan variansi (σ) grup. Angka z adalah pengali baku dari snapshot siklus.</p>
                                    </div>
                                </div>
                                <div class="pa-bal-kv-panduan-item">
                                    <div class="pa-bal-kv-panduan-item__badge">3</div>
                                    <div class="pa-bal-kv-panduan-item__content">
                                        <strong>Efek Pergeseran Batas</strong>
                                        <p>Jika nilai peserta sesi dinaikkan atau diturunkan, μ &amp; σ grup ikut berubah. Rekan se-grup lain bisa mengalami pergeseran grade meski nilainya tidak diubah.</p>
                                    </div>
                                </div>
                                <div class="pa-bal-kv-panduan-item">
                                    <div class="pa-bal-kv-panduan-item__badge">4</div>
                                    <div class="pa-bal-kv-panduan-item__content">
                                        <strong>Karakteristik Kurva Lonceng</strong>
                                        <p>Sebagian besar anggota grup secara alami berkumpul di sekitar nilai rata-rata (Grade B &amp; C+), sementara grade tertinggi dan terendah lebih selektif.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </transition>

                    <div v-if="grupAktif" class="pa-bal-kv">
                        <!-- Stat Grid: 4 Metric Cards -->
                        <div class="pa-bal-kv__stat-grid">
                            <!-- μ Rata-rata -->
                            <div class="pa-bal-kv__stat-card">
                                <div class="pa-bal-kv__stat-icon is-mu">
                                    <HcisIcon name="bullseye" size="sm" />
                                </div>
                                <div class="pa-bal-kv__stat-body">
                                    <span class="pa-bal-kv__stat-label">Rata-rata Nilai (μ)</span>
                                    <div class="pa-bal-kv__stat-val">
                                        <strong>{{ formatNilai(grupAktif.sesudah.rata) }}</strong>
                                        <span
                                            v-if="berubahAngka(grupAktif.sebelum.rata, grupAktif.sesudah.rata)"
                                            class="pa-bal-sim-chip"
                                            :class="grupAktif.sesudah.rata > grupAktif.sebelum.rata ? 'is-naik' : 'is-turun'"
                                        >
                                            {{ selisih(grupAktif.sebelum.rata, grupAktif.sesudah.rata) }}
                                        </span>
                                    </div>
                                    <small class="pa-bal-kv__stat-sub">
                                        Awal: {{ formatNilai(grupAktif.sebelum.rata) }} · Titik tengah distribusi
                                    </small>
                                </div>
                            </div>

                            <!-- σ Simpangan Baku -->
                            <div class="pa-bal-kv__stat-card">
                                <div class="pa-bal-kv__stat-icon is-sd">
                                    <HcisIcon name="sliders" size="sm" />
                                </div>
                                <div class="pa-bal-kv__stat-body">
                                    <span class="pa-bal-kv__stat-label">Simpangan Baku (σ)</span>
                                    <div class="pa-bal-kv__stat-val">
                                        <strong>{{ formatNilai(grupAktif.sesudah.sd) }}</strong>
                                        <span
                                            v-if="berubahAngka(grupAktif.sebelum.sd, grupAktif.sesudah.sd)"
                                            class="pa-bal-sim-chip is-netral"
                                        >
                                            {{ grupAktif.sesudah.sd > grupAktif.sebelum.sd ? 'Lebih menyebar' : 'Lebih rapat' }}
                                        </span>
                                    </div>
                                    <small class="pa-bal-kv__stat-sub">
                                        Awal: {{ formatNilai(grupAktif.sebelum.sd) }} · Lebar variansi nilai
                                    </small>
                                </div>
                            </div>

                            <!-- Total Anggota -->
                            <div class="pa-bal-kv__stat-card">
                                <div class="pa-bal-kv__stat-icon is-n">
                                    <HcisIcon name="people" size="sm" />
                                </div>
                                <div class="pa-bal-kv__stat-body">
                                    <span class="pa-bal-kv__stat-label">Total Anggota Grup</span>
                                    <div class="pa-bal-kv__stat-val">
                                        <strong>{{ grupAktif.sesudah.n }} <span class="pa-bal-kv__stat-unit">orang</span></strong>
                                        <HcisBadge
                                            v-if="jumlahSesiGrup > 0"
                                            :label="`${jumlahSesiGrup} peserta sesi`"
                                            variant="brand"
                                            appearance="subtle"
                                            size="xs"
                                        />
                                    </div>
                                    <small class="pa-bal-kv__stat-sub">
                                        {{ grupAktif.sesudah.n - jumlahSesiGrup }} rekan di luar sesi ikut dihitung
                                    </small>
                                </div>
                            </div>

                            <!-- Level -->
                            <div class="pa-bal-kv__stat-card">
                                <div class="pa-bal-kv__stat-icon is-level">
                                    <HcisIcon name="diagram-3" size="sm" />
                                </div>
                                <div class="pa-bal-kv__stat-body">
                                    <span class="pa-bal-kv__stat-label">Cakupan Job Level</span>
                                    <div class="pa-bal-kv__stat-val">
                                        <div class="pa-bal-kv__level-pills">
                                            <span v-for="lvl in (grupAktif.level.length ? grupAktif.level : ['–'])" :key="lvl" class="pa-bal-kv__level-pill">
                                                {{ lvl }}
                                            </span>
                                        </div>
                                    </div>
                                    <small class="pa-bal-kv__stat-sub">
                                        Level jabatan dalam kurva grup
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Pergeseran Batas Grade (jika ada) -->
                        <div v-if="batasBergeser.length" class="pa-bal-kv-batas-bar">
                            <div class="pa-bal-kv-batas-bar__label">
                                <HcisIcon name="arrow-left-right" size="2xs" />
                                <span>Pergeseran Nilai Batas Grade:</span>
                            </div>
                            <div class="pa-bal-kv-batas-bar__items">
                                <span v-for="b in batasBergeser" :key="b.kode" class="pa-bal-kv-batas-pill">
                                    <span class="pa-bal-kv-batas-pill__kode">{{ labelGrade(b.kode) }}</span>
                                    <span class="pa-bal-kv-batas-pill__nilai">
                                        <s>{{ formatBatas(b.lama) }}</s>
                                        <HcisIcon name="arrow-right" size="2xs" />
                                        <b>{{ formatBatas(b.baru) }}</b>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <!-- SVG Chart with Box Frame -->
                        <div class="pa-bal-kv-chart-box">
                            <div class="pa-bal-kv-chart-header">
                                <div class="pa-bal-kv-chart-title">
                                    <HcisIcon name="graph-up" size="xs" class="text-indigo-600" />
                                    <strong>Distribusi Kurva Normal &amp; Posisi Anggota</strong>
                                </div>
                                <span class="pa-bal-kv-chart-subtitle">
                                    Visualisasi persebaran nilai Final terhadap fungsi densitas probabilitas Gaussian
                                </span>
                            </div>

                            <svg
                                class="pa-bal-kv__grafik"
                                :viewBox="`0 0 ${G.w} ${G.h}`"
                                role="img"
                                :aria-label="`Kurva normal ${grupAktif.nama}`"
                            >
                                <defs>
                                    <linearGradient id="pa-bal-grad-kurva" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" stop-color="#4f46e5" stop-opacity="0.18" />
                                        <stop offset="100%" stop-color="#4f46e5" stop-opacity="0.01" />
                                    </linearGradient>
                                    <!-- Marker panah hijau jika nilai naik -->
                                    <marker id="pa-bal-panah-naik" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                        <path d="M 0 1.5 L 8 5 L 0 8.5 z" fill="#10b981" />
                                    </marker>
                                    <!-- Marker panah merah jika nilai turun -->
                                    <marker id="pa-bal-panah-turun" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                        <path d="M 0 1.5 L 8 5 L 0 8.5 z" fill="#e11d48" />
                                    </marker>
                                </defs>

                                <!-- 1. Pita Zona Grade: Background Tint & Header Pill -->
                                <g v-for="(p, i) in pitaGrafik" :key="p.kode">
                                    <rect
                                        :x="p.x1"
                                        y="0"
                                        :width="p.lebar"
                                        :height="G.dasar"
                                        :fill="warnaGrade(p.kode, i)"
                                        opacity="0.06"
                                    />
                                    <!-- Header Pill Grade di Baris Atas -->
                                    <g v-if="p.lebar > 28">
                                        <rect
                                            :x="p.tengah - 18"
                                            y="8"
                                            width="36"
                                            height="20"
                                            rx="5"
                                            fill="#ffffff"
                                            :stroke="warnaGrade(p.kode, i)"
                                            stroke-width="1.5"
                                        />
                                        <text
                                            :x="p.tengah"
                                            y="22"
                                            text-anchor="middle"
                                            class="pa-bal-kv__label-grade"
                                            :fill="warnaGrade(p.kode, i)"
                                        >
                                            {{ labelGrade(p.kode) }}
                                        </text>
                                    </g>
                                </g>

                                <!-- 2. Garis Batas Grade Vertikal & Angka Cutoff Bawah -->
                                <g v-for="b in batasGradeGrafik" :key="b.kode">
                                    <line
                                        :x1="b.x"
                                        :x2="b.x"
                                        y1="42"
                                        :y2="G.dasar"
                                        stroke="#cbd5e1"
                                        stroke-width="1.2"
                                        stroke-dasharray="3 3"
                                    />
                                    <!-- Angka nilai batas cutoff di dekat dasar -->
                                    <rect
                                        :x="b.x - 17"
                                        :y="G.dasar - 19"
                                        width="34"
                                        height="16"
                                        rx="4"
                                        fill="#ffffff"
                                        stroke="#94a3b8"
                                        stroke-width="1"
                                    />
                                    <text
                                        :x="b.x"
                                        :y="G.dasar - 7"
                                        text-anchor="middle"
                                        class="pa-bal-kv__batas-angka"
                                    >
                                        {{ formatBatas(b.nilai) }}
                                    </text>
                                </g>

                                <!-- 3. Sumbu X, Garis Dasar, & Ticks Nilai -->
                                <line :x1="G.kiri" :x2="G.w - G.kanan" :y1="G.dasar" :y2="G.dasar" stroke="#cbd5e1" stroke-width="1.2" />
                                <g v-for="t in tikX" :key="t">
                                    <line :x1="skalaX(t)" :x2="skalaX(t)" :y1="G.dasar" :y2="G.dasar + 4" stroke="#94a3b8" />
                                    <text :x="skalaX(t)" :y="G.dasar + 16" text-anchor="middle" class="pa-bal-kv__tik">{{ t }}</text>
                                </g>
                                <text :x="(G.w - G.kanan + G.kiri) / 2" :y="G.h - 6" text-anchor="middle" class="pa-bal-kv__sumbu">Nilai Final Peserta →</text>

                                <!-- 4. Kurva Normal: Sebelum (Garis Putus) & Sesudah (Area + Garis Tegas) -->
                                <path :d="jalurKurva(grupAktif.sebelum)" fill="none" stroke="#94a3b8" stroke-width="1.8" stroke-dasharray="5 4" />
                                <path :d="jalurKurva(grupAktif.sesudah, true)" fill="url(#pa-bal-grad-kurva)" stroke="none" />
                                <path :d="jalurKurva(grupAktif.sesudah)" fill="none" stroke="#4f46e5" stroke-width="2.5" />

                                <!-- 5. Indikator Rata-rata (μ): Berada di Puncak Kurva Gaussian -->
                                <!-- μ Sebelum (jika bergeser) -->
                                <g v-if="grupAktif.sebelum.rata !== null && berubahAngka(grupAktif.sebelum.rata, grupAktif.sesudah.rata)">
                                    <line
                                        :x1="skalaX(grupAktif.sebelum.rata)"
                                        :x2="skalaX(grupAktif.sebelum.rata)"
                                        :y1="puncakLamaY"
                                        :y2="G.dasar"
                                        stroke="#94a3b8"
                                        stroke-width="1.2"
                                        stroke-dasharray="3 3"
                                    />
                                    <rect
                                        :x="skalaX(grupAktif.sebelum.rata) - 28"
                                        :y="puncakLamaY - 20"
                                        width="56"
                                        height="16"
                                        rx="8"
                                        fill="#f1f5f9"
                                        stroke="#cbd5e1"
                                        stroke-width="1"
                                    />
                                    <text
                                        :x="skalaX(grupAktif.sebelum.rata)"
                                        :y="puncakLamaY - 8"
                                        text-anchor="middle"
                                        class="pa-bal-kv__mu-lama"
                                    >
                                        μ awal {{ formatNilai(grupAktif.sebelum.rata) }}
                                    </text>
                                </g>

                                <!-- μ Sesudah (Puncak Utama) -->
                                <g v-if="grupAktif.sesudah.rata !== null">
                                    <line
                                        :x1="skalaX(grupAktif.sesudah.rata)"
                                        :x2="skalaX(grupAktif.sesudah.rata)"
                                        :y1="puncakBaruY"
                                        :y2="G.dasar"
                                        stroke="#4f46e5"
                                        stroke-width="1.5"
                                        stroke-dasharray="3 2"
                                    />
                                    <circle
                                        :cx="skalaX(grupAktif.sesudah.rata)"
                                        :cy="puncakBaruY"
                                        r="3.5"
                                        fill="#4f46e5"
                                        stroke="#ffffff"
                                        stroke-width="1.5"
                                    />
                                    <rect
                                        :x="skalaX(grupAktif.sesudah.rata) - 34"
                                        :y="puncakBaruY - 24"
                                        width="68"
                                        height="20"
                                        rx="10"
                                        fill="#4f46e5"
                                    />
                                    <text
                                        :x="skalaX(grupAktif.sesudah.rata)"
                                        :y="puncakBaruY - 10"
                                        text-anchor="middle"
                                        class="pa-bal-kv__mu-baru"
                                    >
                                        μ {{ formatNilai(grupAktif.sesudah.rata) }}
                                    </text>
                                </g>

                                <!-- 6. Titik Anggota Di Luar Sesi (Gray Dots) -->
                                <g v-for="p in pesertaGrafik.nonSesi" :key="`non-${p.participant_id}`">
                                    <circle
                                        v-if="p.gradeBerubah"
                                        :cx="p.x"
                                        :cy="p.y"
                                        r="7.5"
                                        fill="none"
                                        stroke="#f59e0b"
                                        stroke-width="1.8"
                                        stroke-dasharray="2 2"
                                    />
                                    <circle
                                        :cx="p.x"
                                        :cy="p.y"
                                        r="4"
                                        fill="#94a3b8"
                                        stroke="#ffffff"
                                        stroke-width="1.5"
                                    >
                                        <title>{{ p.nama }} (Di Luar Sesi)
Nilai Final: {{ formatNilai(p.sesudah) }}
Grade: {{ labelGrade(p.grade_sebelum) }} → {{ labelGrade(p.grade_sesudah) }}
{{ p.gradeBerubah ? '⚠️ Terdampak pergeseran batas kurva grup' : 'Grade tetap' }}</title>
                                    </circle>
                                </g>

                                <!-- 7. Busur Lintasan Geser (Melengkung Elegan Melompati Titik Lain) -->
                                <g v-for="l in pesertaGrafik.lintasan" :key="`lintasan-${l.id}`">
                                    <!-- Jalur busur lengkung di udara -->
                                    <path
                                        :d="l.d"
                                        fill="none"
                                        :stroke="l.arahNaik ? '#10b981' : '#e11d48'"
                                        stroke-width="1.8"
                                        stroke-dasharray="4 2"
                                        :marker-end="l.arahNaik ? 'url(#pa-bal-panah-naik)' : 'url(#pa-bal-panah-turun)'"
                                    />
                                    <!-- Titik awal sebelum simulasi -->
                                    <circle
                                        :cx="l.x1"
                                        :cy="l.y"
                                        r="4"
                                        fill="#ffffff"
                                        :stroke="l.arahNaik ? '#10b981' : '#e11d48'"
                                        stroke-width="1.5"
                                    />
                                    <!-- Chip delta di puncak lengkungan busur -->
                                    <rect
                                        :x="l.cx - 20"
                                        :y="l.cy - 10"
                                        width="40"
                                        height="18"
                                        rx="9"
                                        :fill="l.arahNaik ? '#10b981' : '#e11d48'"
                                    />
                                    <text
                                        :x="l.cx"
                                        :y="l.cy + 3"
                                        text-anchor="middle"
                                        class="pa-bal-kv__delta-arc"
                                    >
                                        {{ l.deltaStr }}
                                    </text>
                                </g>

                                <!-- 8. Titik & Label Peserta Sesi (Anti-Tabrakan / Multi-Tier) -->
                                <g v-for="p in pesertaGrafik.sesi" :key="`sesi-${p.participant_id}`">
                                    <!-- Garis pin vertikal penunjuk untuk label tier 1 & 2 -->
                                    <line
                                        v-if="p.hasPin"
                                        :x1="p.x"
                                        :x2="p.x"
                                        :y1="p.dotY - 8"
                                        :y2="p.labelY + 8"
                                        stroke="#818cf8"
                                        stroke-width="1"
                                        stroke-dasharray="2 2"
                                    />

                                    <!-- Ring highlight jika grade berubah -->
                                    <circle
                                        v-if="p.gradeBerubah"
                                        :cx="p.x"
                                        :cy="p.dotY"
                                        r="9.5"
                                        fill="none"
                                        stroke="#f59e0b"
                                        stroke-width="2"
                                        stroke-dasharray="2 2"
                                    />

                                    <!-- Titik utama peserta sesi -->
                                    <circle
                                        :cx="p.x"
                                        :cy="p.dotY"
                                        r="5.5"
                                        fill="#4f46e5"
                                        stroke="#ffffff"
                                        stroke-width="2"
                                    >
                                        <title>{{ p.nama }}
Nilai Final: {{ formatNilai(p.sebelum) }} → {{ formatNilai(p.sesudah) }}
Grade: {{ labelGrade(p.grade_sebelum) }} → {{ labelGrade(p.grade_sesudah) }}
Status: Peserta Sesi Balancing
{{ p.gradeBerubah ? '⚠️ Grade mengalami perubahan akibat balancing' : '' }}</title>
                                    </circle>

                                    <!-- Label nama berpelindung pill anti-tabrakan -->
                                    <g>
                                        <rect
                                            :x="p.x - (namaPendek(p.nama).length * 3.5 + 8)"
                                            :y="p.labelY - 11"
                                            :width="namaPendek(p.nama).length * 7 + 16"
                                            height="16"
                                            rx="4"
                                            fill="#ffffff"
                                            stroke="#c7d2fe"
                                            stroke-width="1"
                                            opacity="0.95"
                                        />
                                        <text
                                            :x="p.x"
                                            :y="p.labelY + 1"
                                            text-anchor="middle"
                                            class="pa-bal-kv__nama"
                                        >
                                            {{ namaPendek(p.nama) }}
                                        </text>
                                    </g>
                                </g>
                            </svg>

                            <!-- Modern Legend Bar -->
                            <div class="pa-bal-kv-legenda">
                                <div class="pa-bal-kv-legenda__item">
                                    <span class="pa-bal-kv-legenda__line is-garis-lama" />
                                    <span>Kurva Awal</span>
                                </div>
                                <div class="pa-bal-kv-legenda__item">
                                    <span class="pa-bal-kv-legenda__line is-garis-baru" />
                                    <span>Kurva Sesudah</span>
                                </div>
                                <div class="pa-bal-kv-legenda__item">
                                    <span class="pa-bal-kv-legenda__badge-mu">μ</span>
                                    <span>Rata-rata Grup</span>
                                </div>
                                <div class="pa-bal-kv-legenda__item">
                                    <span class="pa-bal-kv-legenda__dot is-sesi" />
                                    <span>Peserta Sesi</span>
                                </div>
                                <div class="pa-bal-kv-legenda__item">
                                    <span class="pa-bal-kv-legenda__dot is-lain" />
                                    <span>Rekan Se-Grup</span>
                                </div>
                                <div class="pa-bal-kv-legenda__item">
                                    <span class="pa-bal-kv-legenda__dot is-grade-ubah" />
                                    <span>Grade Berubah</span>
                                </div>
                                <div class="pa-bal-kv-legenda__item">
                                    <span class="pa-bal-kv-legenda__arc-sample" />
                                    <span>Busur Pergeseran Nilai</span>
                                </div>
                            </div>

                            <!-- Roster Peserta Sesi di Bawah Grafik -->
                            <div v-if="pesertaGrafik.sesi.length" class="pa-bal-kv-roster">
                                <span class="pa-bal-kv-roster__title">Peserta Sesi:</span>
                                <div class="pa-bal-kv-roster__chips">
                                    <div
                                        v-for="p in pesertaGrafik.sesi"
                                        :key="`roster-${p.participant_id}`"
                                        class="pa-bal-kv-roster__chip"
                                        :class="{ 'is-ubah-grade': p.gradeBerubah }"
                                    >
                                        <span class="pa-bal-kv-roster__nama">{{ p.nama }}</span>
                                        <span class="pa-bal-kv-roster__skor">
                                            <template v-if="p.geser">
                                                <s>{{ formatNilai(p.sebelum) }}</s> → <b>{{ formatNilai(p.sesudah) }}</b>
                                            </template>
                                            <template v-else>
                                                <b>{{ formatNilai(p.sesudah) }}</b>
                                            </template>
                                        </span>
                                        <span class="pa-bal-sim-grade__tag" :class="{ 'is-baru': p.gradeBerubah }">
                                            {{ labelGrade(p.grade_sesudah) }}
                                        </span>
                                        <span v-if="p.gradeBerubah" class="pa-bal-kv-roster__tag-alert">
                                            {{ p.grade_sebelum !== p.grade_sesudah ? `dari ${labelGrade(p.grade_sebelum)}` : '' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dampak Pergeseran Grade di Grup Ini -->
                        <div v-if="gradeBergeserGrup.length" class="pa-bal-kv-perubahan">
                            <div class="pa-bal-kv-perubahan__header">
                                <div class="pa-bal-kv-perubahan__title">
                                    <HcisIcon name="exclamation-circle" size="xs" class="text-amber-600" />
                                    <strong>Peserta yang Mengalami Perubahan Grade</strong>
                                    <HcisBadge :label="`${gradeBergeserGrup.length} orang`" variant="warning" appearance="solid" size="xs" />
                                </div>
                                <span class="pa-bal-kv-perubahan__sub">
                                    Grade bergeser akibat perubahan nilai peserta sesi atau pergerakan batas kurva grup
                                </span>
                            </div>

                            <div class="pa-bal-kv-perubahan__grid">
                                <div
                                    v-for="o in gradeBergeserGrup"
                                    :key="o.participant_id"
                                    class="pa-bal-kv-orang-kartu"
                                    :class="{ 'is-sesi': o.sesi, 'is-luar': !o.sesi }"
                                >
                                    <div class="pa-bal-kv-orang-kartu__kiri">
                                        <HcisAvatar :name="o.nama" size="sm" />
                                        <div class="pa-bal-kv-orang-kartu__bio">
                                            <strong>{{ o.nama }}</strong>
                                            <div class="pa-bal-kv-orang-kartu__meta">
                                                <HcisBadge
                                                    :label="o.sesi ? 'Peserta Sesi' : 'Di Luar Sesi'"
                                                    :variant="o.sesi ? 'brand' : 'neutral'"
                                                    appearance="subtle"
                                                    size="2xs"
                                                />
                                                <span class="pa-bal-kv-orang-kartu__nilai">
                                                    Final:
                                                    <template v-if="berubahAngka(o.sebelum, o.sesudah)">
                                                        <s>{{ formatNilai(o.sebelum) }}</s> → <b>{{ formatNilai(o.sesudah) }}</b>
                                                    </template>
                                                    <template v-else>
                                                        <b>{{ formatNilai(o.sesudah) }}</b> (tetap)
                                                    </template>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pa-bal-kv-orang-kartu__kanan">
                                        <div class="pa-bal-kv-orang-kartu__grade">
                                            <span class="pa-bal-sim-grade__tag">{{ labelGrade(o.grade_sebelum) }}</span>
                                            <HcisIcon name="arrow-right" size="2xs" class="text-slate-400" />
                                            <span class="pa-bal-sim-grade__tag is-baru">{{ labelGrade(o.grade_sesudah) }}</span>
                                        </div>
                                        <small class="pa-bal-kv-orang-kartu__alasan">
                                            {{ o.sesi ? (berubahAngka(o.sebelum, o.sesudah) ? 'Nilai diubah di sesi' : 'Kena geseran batas kurva') : 'Terdampak pergeseran batas kurva grup' }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Matriks Tabel Distribusi Grade -->
                        <div class="pa-bal-kv-tabel-card">
                            <div class="pa-bal-kv-tabel-header">
                                <div>
                                    <h4 class="pa-bal-kv-tabel-title">
                                        <HcisIcon name="table" size="xs" />
                                        <span>Matriks Batas Grade &amp; Sebaran Distribusi</span>
                                    </h4>
                                    <p class="pa-bal-kv-tabel-sub">
                                        Rentang nilai Final dan sebaran anggota grup sebelum dan sesudah simulasi.
                                    </p>
                                </div>
                            </div>

                            <div class="pa-bal-sim-tabel-wrap">
                                <table class="pa-bal-sim-tabel is-ringkas">
                                    <thead>
                                        <tr>
                                            <th>Grade</th>
                                            <th title="Batas bawah grade = μ + z × σ">Batas z-Score</th>
                                            <th title="Rentang nilai Final untuk grade ini setelah simulasi">Rentang Nilai</th>
                                            <th title="Visualisasi proporsi sebaran anggota grup saat ini">Sebaran Aktual</th>
                                            <th class="is-kanan" title="Jumlah orang di grade ini sebelum simulasi">Sebelum</th>
                                            <th class="is-kanan" title="Jumlah orang di grade ini setelah simulasi">Sesudah Simulasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(p, i) in grupAktif.sesudah.pita"
                                            :key="p.kode"
                                            :class="{ 'is-geser': (grupAktif.sesudah.sebaran?.[p.kode] ?? 0) !== (grupAktif.sebelum.sebaran?.[p.kode] ?? 0) }"
                                        >
                                            <td>
                                                <span class="pa-bal-sim-grade__pill" :style="{ '--grade-clr': warnaGrade(p.kode, i) }">
                                                    <span class="pa-bal-sim-grade__dot" />
                                                    <strong>{{ labelGrade(p.kode) }}</strong>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="pa-bal-kv-z">
                                                    {{ p.batas_z === null || p.batas_z === undefined ? 'Terendah (dasar)' : `z ≥ ${p.batas_z >= 0 ? '+' : ''}${formatAngka(p.batas_z)}σ` }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="pa-bal-kv-rentang">
                                                    {{ formatNilai(p.nilai_dari) }} – {{ p.nilai_sampai >= 100 ? '100' : formatNilai(p.nilai_sampai) }}
                                                </span>
                                            </td>
                                            <td>
                                                <!-- Visual Bar Sebaran Aktual -->
                                                <div class="pa-bal-kv-bar-banding" :title="`Sebaran: ${persen(grupAktif.sesudah.sebaran?.[p.kode], grupAktif.sesudah.n)}`">
                                                    <div class="pa-bal-kv-bar-track">
                                                        <span
                                                            class="pa-bal-kv-bar-fill"
                                                            :style="{
                                                                width: `${Math.min(100, Math.round(((grupAktif.sesudah.sebaran?.[p.kode] ?? 0) / (grupAktif.sesudah.n || 1)) * 100))}%`,
                                                                backgroundColor: warnaGrade(p.kode, i),
                                                            }"
                                                        />
                                                    </div>
                                                    <span class="pa-bal-kv-bar-label">
                                                        {{ persen(grupAktif.sesudah.sebaran?.[p.kode], grupAktif.sesudah.n) }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="is-kanan">
                                                <span class="pa-bal-kv-sebelum">
                                                    {{ grupAktif.sebelum.sebaran?.[p.kode] ?? 0 }} org
                                                    <small>({{ persen(grupAktif.sebelum.sebaran?.[p.kode], grupAktif.sebelum.n) }})</small>
                                                </span>
                                            </td>
                                            <td class="is-kanan">
                                                <div class="pa-bal-kv-sesudah-wrap">
                                                    <strong>{{ grupAktif.sesudah.sebaran?.[p.kode] ?? 0 }} org</strong>
                                                    <small>({{ persen(grupAktif.sesudah.sebaran?.[p.kode], grupAktif.sesudah.n) }})</small>
                                                    <span
                                                        v-if="(grupAktif.sesudah.sebaran?.[p.kode] ?? 0) !== (grupAktif.sebelum.sebaran?.[p.kode] ?? 0)"
                                                        class="pa-bal-sim-chip"
                                                        :class="(grupAktif.sesudah.sebaran?.[p.kode] ?? 0) > (grupAktif.sebelum.sebaran?.[p.kode] ?? 0) ? 'is-naik' : 'is-turun'"
                                                    >
                                                        {{ selisihBulat(grupAktif.sebelum.sebaran?.[p.kode] ?? 0, grupAktif.sesudah.sebaran?.[p.kode] ?? 0) }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <p class="pa-bal-kv-kaki">
                                <HcisIcon name="info-circle" size="2xs" />
                                <span>
                                    Jumlah pada kolom Sebelum &amp; Sesudah dihitung dari seluruh {{ grupAktif.sesudah.n }} anggota grup.
                                    Simulasi ini bersifat sementara; grade resmi baru berubah secara permanen setelah sesi difinalkan dan siklus dihitung ulang.
                                </span>
                            </p>
                        </div>
                    </div>
                </template>

                <!-- Metode pita / tanpa grup: sebaran grade seluruh siklus -->
                <template v-else>
                    <div class="pa-bal-kv-pita-box">
                        <div class="pa-bal-sim__catatan">
                            <HcisIcon name="bar-chart" size="2xs" />
                            <span>Sebaran grade seluruh peserta siklus, membandingkan kondisi sebelum vs sesudah simulasi balancing.</span>
                        </div>
                        <div class="pa-bal-dist">
                            <div v-for="(g, i) in sebaranGrade" :key="g.kode" class="pa-bal-dist__baris">
                                <span class="pa-bal-sim-grade__pill" :style="{ '--grade-clr': warnaGrade(g.kode, i) }">
                                    <span class="pa-bal-sim-grade__dot" />
                                    <strong>{{ labelGrade(g.kode) }}</strong>
                                </span>
                                <div class="pa-bal-dist__bar">
                                    <span class="is-lama" :style="{ width: `${lebarBar(g.sebelum)}%` }" :title="`Sebelum: ${g.sebelum}`" />
                                    <span class="is-baru" :style="{ width: `${lebarBar(g.sesudah)}%` }" :title="`Sesudah: ${g.sesudah}`" />
                                </div>
                                <span class="pa-bal-dist__angka">{{ g.sebelum }} → <b>{{ g.sesudah }}</b></span>
                            </div>
                        </div>
                    </div>
                </template>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisAvatar, HcisBadge, HcisButton, HcisCard, HcisIcon, HcisSkeleton } from '@/components/ui';
import { LABEL_SEBAB, berubah, formatNilai, labelGrade } from '../utils/balancing.js';
import { URUTAN_KOTAK_HAV, ikonKotak, polaKotak, sentimenKotak } from '../../utils/havKotak.js';

defineOptions({ name: 'PaBalancingSimulasiPanel' });

const props = defineProps({
    hasil: { type: Object, default: null },
    kuadran: { type: Array, default: () => [] },
    memuat: { type: Boolean, default: false },
    basi: { type: Boolean, default: false },
    modeModal: { type: Boolean, default: false },
});

defineEmits(['tutup', 'simulasi-ulang']);

const tabAktif = ref('perubahan');
const sisiHav = ref('sesudah');
const hanyaSesi = ref(false);
const grupAktifId = ref(null);
const bukaPanduan = ref(false);

const sentimen = sentimenKotak;
const pola = polaKotak;
const ikon = ikonKotak;

const SUMBU_X = [
    { nama: 'Kinerja Rendah', ket: 'Di bawah target', kelas: 'is-rendah' },
    { nama: 'Kinerja Sedang', ket: 'Sesuai ekspektasi', kelas: 'is-sedang' },
    { nama: 'Kinerja Tinggi', ket: 'Melampaui target', kelas: 'is-tinggi' },
];
const SUMBU_Y = [
    { nama: 'Kompetensi Tinggi', lencana: 'TINGGI', varian: 'purple', kelas: 'is-tinggi' },
    { nama: 'Kompetensi Sedang', lencana: 'SEDANG', varian: 'brand', kelas: 'is-sedang' },
    { nama: 'Kompetensi Rendah', lencana: 'RENDAH', varian: 'warning', kelas: 'is-rendah' },
];
const WARNA_GRADE = ['#22c55e', '#84cc16', '#3b82f6', '#a855f7', '#f59e0b', '#ef4444', '#64748b'];

const warnaGrade = (kode, idx = 0) => {
    const k = String(kode || '').toUpperCase();
    if (k.includes('A')) return '#10b981';
    if (k.includes('B_PLUS') || k.includes('B+')) return '#06b6d4';
    if (k.includes('B')) return '#3b82f6';
    if (k.includes('C_PLUS') || k.includes('C+')) return '#8b5cf6';
    if (k.includes('C')) return '#f59e0b';
    if (k.includes('D')) return '#f97316';
    if (k.includes('E')) return '#ef4444';
    return WARNA_GRADE[idx % WARNA_GRADE.length] || '#64748b';
};

const TAB = computed(() => [
    { kunci: 'perubahan', label: 'Perubahan peserta', ikon: 'people', angka: props.hasil?.peserta?.length || 0 },
    { kunci: 'hav', label: 'HAV Map', ikon: 'grid-3x3', angka: pindahHav.value.length || 0 },
    { kunci: 'kurva', label: props.hasil?.metode === 'KURVA' ? 'Kurva normal' : 'Sebaran grade', ikon: 'graph-up', angka: 0 },
]);

/* ── Umum ── */
const berubahAngka = (a, b) => a !== null && b !== null && a !== undefined && b !== undefined && berubah(a, b);
const selisih = (a, b) => {
    const d = Math.round((Number(b) - Number(a)) * 100) / 100;
    return `${d > 0 ? '+' : '−'}${Math.abs(d).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};
const selisihBulat = (a, b) => (b - a > 0 ? `+${b - a}` : `−${a - b}`);
const formatAngka = (v) => (v === null || v === undefined ? '–' : Number(v).toLocaleString('id-ID', { maximumFractionDigits: 2 }));
const persen = (n, total) => (total ? `${Math.round(((n || 0) / total) * 100)}%` : '0%');
const inisial = (nama) => String(nama || '?').split(/\s+/).slice(0, 2).map((x) => x[0]).join('').toUpperCase();
const namaPendek = (nama) => String(nama || '').split(/\s+/)[0];

const pesertaUrut = computed(() => [...(props.hasil?.peserta || [])].sort((a, b) => Number(b.sebelum.grade !== b.sesudah.grade) - Number(a.sebelum.grade !== a.sesudah.grade)));
const jumlahGradeBerubah = computed(() => (props.hasil?.peserta || []).filter((b) => b.sebelum?.grade !== b.sesudah?.grade).length);
const jumlahFinalBerubah = computed(() => (props.hasil?.peserta || []).filter((b) => berubahAngka(b.sebelum?.final, b.sesudah?.final)).length);
const rataSesi = computed(() => {
    const p = (props.hasil?.peserta || []).filter((b) => b.sebelum.final !== null);
    if (!p.length) return { sebelum: null, sesudah: null };
    const rata = (k) => Math.round((p.reduce((s, b) => s + Number(b[k].final || 0), 0) / p.length) * 100) / 100;
    return { sebelum: rata('sebelum'), sesudah: rata('sesudah') };
});

/* ── HAV ── */
const kuadranPerKode = computed(() => new Map(props.kuadran.map((k) => [k.Kode_Kuadran, k])));
const noKotak = (kode) => (kode ? kuadranPerKode.value.get(kode)?.Nomor_Box ?? null : null);
const namaKotak = (kode) => (kode ? kuadranPerKode.value.get(kode)?.Nama_Kuadran || kode : '–');

const semuaHav = computed(() => props.hasil?.semua || []);
const pindahHav = computed(() => semuaHav.value.filter((o) => o.kuadran_sebelum !== o.kuadran_sesudah));

const kotakHav = computed(() => {
    const peta = new Map(props.kuadran.map((k) => [Number(k.Nomor_Box), k]));
    const kunci = sisiHav.value === 'sesudah' ? 'kuadran_sesudah' : 'kuadran_sebelum';
    const sumber = hanyaSesi.value ? semuaHav.value.filter((o) => o.sesi) : semuaHav.value;
    return URUTAN_KOTAK_HAV.map((n) => peta.get(n)).filter(Boolean).map((k) => {
        const isi = sumber.filter((o) => o[kunci] === k.Kode_Kuadran).map((o) => ({
            ...o,
            pindah: o.kuadran_sebelum !== o.kuadran_sesudah,
            dari: noKotak(o.kuadran_sebelum),
            ke: noKotak(o.kuadran_sesudah),
        })).sort((a, b) => Number(b.pindah) - Number(a.pindah) || Number(b.sesi) - Number(a.sesi) || String(a.nama).localeCompare(String(b.nama)));
        const sebelum = sumber.filter((o) => o.kuadran_sebelum === k.Kode_Kuadran).length;
        const sesudah = sumber.filter((o) => o.kuadran_sesudah === k.Kode_Kuadran).length;
        return { ...k, isi, selisih: sesudah - sebelum };
    });
});

/* ── Kurva normal ── */
const grupKurva = computed(() => (props.hasil?.kurva || [])
    .filter((g) => g.anggota?.length)
    .map((g) => ({ ...g, adaSesi: g.anggota.some((o) => o.sesi) })));
const grupAktif = computed(() => grupKurva.value.find((g) => g.id === grupAktifId.value) || grupKurva.value.find((g) => g.adaSesi) || grupKurva.value[0] || null);

const G = { w: 800, h: 340, kiri: 42, kanan: 32, atas: 48, dasar: 275 };

const rentangX = computed(() => {
    const g = grupAktif.value;
    if (!g) return [0, 100];
    const nilai = g.anggota.flatMap((o) => [o.sebelum, o.sesudah]).filter((v) => v !== null).map(Number);
    /* Rata-rata dan sebaran dari grupnya sendiri; tanpa patokan 75/10. Batas atas TIDAK
       dipotong 100: nilai akhir bisa melewatinya, dan titik itu jatuh di luar grafik. */
    const rataNilai = nilai.length ? nilai.reduce((a, b) => a + b, 0) / nilai.length : 0;
    const mu = Number(g.sesudah.rata ?? g.sebelum.rata ?? rataNilai);
    const sd = Math.max(Number(g.sesudah.sd ?? g.sebelum.sd ?? 1), 1);
    const min = Math.max(0, Math.floor(Math.min(mu - 3.2 * sd, ...nilai) / 5) * 5);
    const max = Math.ceil(Math.max(mu + 3.2 * sd, ...nilai) / 5) * 5;
    return [min, max > min ? max : min + 10];
});
const skalaX = (v) => {
    const [a, b] = rentangX.value;
    return G.kiri + ((Number(v) - a) / (b - a)) * (G.w - G.kiri - G.kanan);
};
const tikX = computed(() => {
    const [a, b] = rentangX.value;
    const langkah = b - a > 40 ? 10 : 5;
    const t = [];
    for (let v = Math.ceil(a / langkah) * langkah; v <= b; v += langkah) t.push(v);
    return t;
});

const pdf = (x, mu, sd) => Math.exp(-0.5 * ((x - mu) / sd) ** 2) / (sd * Math.sqrt(2 * Math.PI));
const puncak = computed(() => {
    const g = grupAktif.value;
    const sds = [g?.sebelum?.sd, g?.sesudah?.sd].filter((s) => s && s > 0).map(Number);
    return sds.length ? Math.max(...sds.map((s) => pdf(0, 0, s))) : 1;
});

const puncakBaruY = computed(() => {
    const g = grupAktif.value;
    if (!g?.sesudah?.rata || !g?.sesudah?.sd) return G.dasar - 120;
    const tinggi = G.dasar - G.atas - 45;
    return Math.round(G.dasar - (pdf(Number(g.sesudah.rata), Number(g.sesudah.rata), Number(g.sesudah.sd)) / puncak.value) * tinggi);
});

const puncakLamaY = computed(() => {
    const g = grupAktif.value;
    if (!g?.sebelum?.rata || !g?.sebelum?.sd) return G.dasar - 120;
    const tinggi = G.dasar - G.atas - 45;
    return Math.round(G.dasar - (pdf(Number(g.sebelum.rata), Number(g.sebelum.rata), Number(g.sebelum.sd)) / puncak.value) * tinggi);
});

function jalurKurva(sisi, tutup = false) {
    if (!sisi || sisi.rata === null || !sisi.sd || sisi.sd <= 0) return '';
    const [a, b] = rentangX.value;
    const tinggi = G.dasar - G.atas - 45;
    const titik = [];
    for (let i = 0; i <= 120; i += 1) {
        const x = a + ((b - a) * i) / 120;
        const y = G.dasar - (pdf(x, Number(sisi.rata), Number(sisi.sd)) / puncak.value) * tinggi;
        titik.push(`${skalaX(x).toFixed(1)},${y.toFixed(1)}`);
    }
    const garis = `M ${titik.join(' L ')}`;
    return tutup ? `${garis} L ${skalaX(b).toFixed(1)},${G.dasar} L ${skalaX(a).toFixed(1)},${G.dasar} Z` : garis;
}

const pitaGrafik = computed(() => {
    const [a, b] = rentangX.value;
    return (grupAktif.value?.sesudah?.pita || []).map((p) => {
        const dari = Math.max(a, Math.min(b, Number(p.nilai_dari)));
        const sampai = Math.max(a, Math.min(b, Number(p.nilai_sampai)));
        const x1 = skalaX(dari);
        const x2 = skalaX(sampai);
        return {
            kode: p.kode,
            batas_z: p.batas_z,
            nilai_dari: p.nilai_dari,
            nilai_sampai: p.nilai_sampai,
            x1,
            x2,
            lebar: Math.max(0, x2 - x1),
            tengah: (x1 + x2) / 2,
        };
    });
});

const batasGradeGrafik = computed(() => {
    const pita = grupAktif.value?.sesudah?.pita || [];
    const [a, b] = rentangX.value;
    return pita
        .filter((p) => p.batas_z !== null && p.batas_z !== undefined && Number(p.nilai_dari) >= a && Number(p.nilai_dari) <= b)
        .map((p) => ({
            kode: p.kode,
            nilai: Number(p.nilai_dari),
            x: skalaX(Number(p.nilai_dari)),
            batas_z: p.batas_z,
        }));
});

// Engine anti-tabrakan untuk titik & label peserta, serta busur lintasan pergeseran
const pesertaGrafik = computed(() => {
    const g = grupAktif.value;
    if (!g) return { sesi: [], nonSesi: [], lintasan: [] };

    const nonSesi = [];
    const sesi = [];

    for (const o of g.anggota) {
        if (o.sesudah === null || o.sesudah === undefined) continue;
        if (o.sesi) {
            sesi.push({ ...o });
        } else {
            nonSesi.push({ ...o });
        }
    }

    // Urutkan peserta sesi dari nilai terendah ke tertinggi
    sesi.sort((a, b) => Number(a.sesudah) - Number(b.sesudah));

    const baseDotY = G.dasar - 20;
    const hasilSesi = [];
    let lastX = -999;
    let currentTier = 0;

    for (let i = 0; i < sesi.length; i++) {
        const p = sesi[i];
        const x = skalaX(p.sesudah);
        const dist = x - lastX;

        // Jika jarak nama terlalu rapat (< 44px), naikkan tier label agar teks tidak tabrakan
        if (dist < 44) {
            currentTier = (currentTier + 1) % 3;
        } else {
            currentTier = 0;
        }

        // Offset titik vertikal jika titik sangat berhimpitan (< 14px)
        const dotOffsetY = dist < 14 ? (currentTier % 2 === 1 ? -12 : 0) : 0;
        const dotY = baseDotY + dotOffsetY;

        // Label Y berundak sesuai tier
        const labelY = dotY - 14 - currentTier * 16;
        const hasPin = currentTier > 0;

        hasilSesi.push({
            ...p,
            x,
            dotY,
            labelY,
            tier: currentTier,
            hasPin,
            geser: berubahAngka(p.sebelum, p.sesudah),
            gradeBerubah: p.grade_sebelum !== p.grade_sesudah,
        });

        lastX = x;
    }

    // Busur kurva lengkung untuk peserta yang nilainya bergeser (melompati peserta lain)
    const lintasan = hasilSesi
        .filter((p) => p.geser)
        .map((p) => {
            const x1 = skalaX(p.sebelum);
            const x2 = skalaX(p.sesudah);
            const dist = Math.abs(x2 - x1);
            const tinggiArc = Math.min(55, Math.max(24, dist * 0.22));
            const cx = (x1 + x2) / 2;
            const cy = p.dotY - tinggiArc;
            const delta = Math.round((Number(p.sesudah) - Number(p.sebelum)) * 100) / 100;
            const arahNaik = delta > 0;
            return {
                id: p.participant_id,
                nama: p.nama,
                x1,
                x2,
                y: p.dotY,
                cx,
                cy,
                d: `M ${x1} ${p.dotY} Q ${cx} ${cy} ${x2} ${p.dotY}`,
                delta,
                deltaStr: `${arahNaik ? '+' : ''}${formatNilai(delta)}`,
                arahNaik,
            };
        });

    const hasilNonSesi = nonSesi.map((p) => ({
        ...p,
        x: skalaX(p.sesudah),
        y: baseDotY,
        gradeBerubah: p.grade_sebelum !== p.grade_sesudah,
    }));

    return { sesi: hasilSesi, nonSesi: hasilNonSesi, lintasan };
});

const jumlahSesiGrup = computed(() => (grupAktif.value?.anggota || []).filter((o) => o.sesi).length);
const arah = (a, b) => (Number(b) > Number(a) ? 'naik' : 'turun');
const batasBergeser = computed(() => {
    const g = grupAktif.value;
    if (!g) return [];
    const lama = new Map((g.sebelum.pita || []).map((p) => [p.kode, p.nilai_dari]));
    return (g.sesudah.pita || [])
        .filter((p) => p.batas_z !== null && p.batas_z !== undefined && lama.has(p.kode) && berubahAngka(lama.get(p.kode), p.nilai_dari))
        .map((p) => ({ kode: p.kode, lama: lama.get(p.kode), baru: p.nilai_dari }));
});
// Batas di atas 100 tidak bisa dicapai siapa pun; ditulis begitu supaya angka 113 tidak membingungkan.
const formatBatas = (v) => (Number(v) > 100 ? `${formatNilai(v)} (tak tercapai)` : formatNilai(v));
const ringkasUbah = computed(() => {
    const g = grupAktif.value;
    if (!g) return '';
    const bagian = [];
    bagian.push(berubahAngka(g.sebelum.rata, g.sesudah.rata) ? `μ ${formatNilai(g.sebelum.rata)} → ${formatNilai(g.sesudah.rata)}` : 'μ tetap');
    bagian.push(gradeBergeserGrup.value.length ? `${gradeBergeserGrup.value.length} grade berubah` : 'grade tetap');
    return bagian.join(' · ');
});
const gradeBergeserGrup = computed(() => (grupAktif.value?.anggota || []).filter((o) => o.grade_sebelum !== o.grade_sesudah));

/* ── Sebaran grade (metode pita) ── */
const sebaranGrade = computed(() => {
    const urutan = props.hasil?.grade?.length ? props.hasil.grade.map((g) => g.kode) : [...new Set(semuaHav.value.flatMap((o) => [o.grade_sebelum, o.grade_sesudah]).filter(Boolean))];
    return urutan.map((kode) => ({
        kode,
        sebelum: semuaHav.value.filter((o) => o.grade_sebelum === kode).length,
        sesudah: semuaHav.value.filter((o) => o.grade_sesudah === kode).length,
    }));
});
const maksSebaran = computed(() => Math.max(1, ...sebaranGrade.value.flatMap((g) => [g.sebelum, g.sesudah])));
const lebarBar = (n) => (n / maksSebaran.value) * 100;

watch(() => props.hasil, (baru, lama) => {
    if (baru && !lama) {
        tabAktif.value = 'perubahan';
        grupAktifId.value = null;
        bukaPanduan.value = false;
    }
});
</script>

<style scoped>
.pa-bal-sim-wrap { display: flex; flex-direction: column; gap: 0.9rem; }
.pa-bal-sim-card { padding: 1rem; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; }
.pa-bal-sim__header { display: flex; align-items: center; justify-content: space-between; }
.pa-bal-sim__pita-wrap { display: flex; align-items: center; gap: 0.4rem; }
.pa-bal-sim__pita { font-size: 0.78rem; letter-spacing: 0.04em; color: #4338ca; }
.pa-bal-sim__close-btn { display: inline-grid; place-items: center; width: 28px; height: 28px; border: 0; border-radius: 8px; background: transparent; cursor: pointer; }
.pa-bal-sim__basi-wrap { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.6rem 0.85rem; border: 1px solid #fde68a; border-radius: 12px; background: #fffbeb; }
.pa-bal-sim__basi { display: flex; align-items: center; gap: 0.4rem; margin: 0; font-size: 0.8rem; color: #92400e; }
.pa-bal-sim__catatan { display: flex; align-items: flex-start; gap: 0.45rem; margin: 0; padding: 0.55rem 0.8rem; border-radius: 10px; background: #f8fafc; font-size: 0.78rem; color: #475569; line-height: 1.45; }

/* Ringkasan */
.pa-bal-sim-ringkas { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.6rem; }
.pa-bal-sim-kartu { display: flex; flex-direction: column; gap: 0.1rem; padding: 0.7rem 0.85rem; border: 1px solid #e2e8f0; border-left: 3px solid #cbd5e1; border-radius: 12px; background: #fff; min-width: 0; }
.pa-bal-sim-kartu span { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
.pa-bal-sim-kartu strong { display: flex; align-items: center; gap: 0.3rem; font-size: 1.25rem; color: #0f172a; font-variant-numeric: tabular-nums; }
.pa-bal-sim-kartu small { font-size: 0.72rem; color: #94a3b8; }
.pa-bal-sim-kartu.is-brand { border-left-color: #6366f1; }
.pa-bal-sim-kartu.is-ungu { border-left-color: #a855f7; }
.pa-bal-sim-kartu.is-teal { border-left-color: #14b8a6; }
.pa-bal-sim-kartu.is-warning { border-left-color: #f59e0b; background: #fffbeb; }
small.is-naik { color: #15803d; font-weight: 700; }
small.is-turun { color: #b91c1c; font-weight: 700; }

/* Tab */
.pa-bal-sim-tab { display: flex; gap: 0.3rem; padding: 0.25rem; border-radius: 12px; background: #f1f5f9; width: fit-content; max-width: 100%; overflow-x: auto; }
.pa-bal-sim-tab button { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.9rem; border: 0; border-radius: 9px; background: transparent; color: #475569; font-size: 0.8rem; font-weight: 700; white-space: nowrap; cursor: pointer; }
.pa-bal-sim-tab button b { padding: 0 0.4rem; border-radius: 999px; background: #e2e8f0; font-size: 0.68rem; }
.pa-bal-sim-tab button.is-aktif { background: #fff; color: #4338ca; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1); }
.pa-bal-sim-tab button.is-aktif b { background: #e0e7ff; color: #4338ca; }
.pa-bal-sim-isi { display: flex; flex-direction: column; gap: 0.75rem; }

/* Tabel */
.pa-bal-sim-tabel-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 12px; }
.pa-bal-sim-tabel { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.pa-bal-sim-tabel th { padding: 0.5rem 0.75rem; background: #f8fafc; color: #64748b; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; text-align: left; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
.pa-bal-sim-tabel td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.pa-bal-sim-tabel tr:last-child td { border-bottom: 0; }
.pa-bal-sim-tabel tr.is-geser td { background: #f5f7ff; }
.pa-bal-sim-tabel .is-kanan { text-align: right; }
.pa-bal-sim-tabel.is-ringkas { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
.pa-bal-sim-tabel small { color: #94a3b8; }
.pa-bal-sim-orang { display: flex; align-items: center; gap: 0.5rem; }
.pa-bal-sim-orang strong { font-size: 0.82rem; color: #0f172a; }
.pa-bal-sim-trans { display: inline-flex; align-items: center; gap: 0.35rem; font-variant-numeric: tabular-nums; }
.pa-bal-sim-trans s { color: #94a3b8; }
.pa-bal-sim-chip { display: inline-block; padding: 0 0.4rem; border-radius: 999px; font-size: 0.66rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.pa-bal-sim-chip.is-naik { background: #dcfce7; color: #15803d; }
.pa-bal-sim-chip.is-turun { background: #fee2e2; color: #b91c1c; }
.pa-bal-sim-grade { display: inline-flex; align-items: center; gap: 0.3rem; }
.pa-bal-sim-grade__tag { display: inline-block; min-width: 28px; padding: 0.05rem 0.45rem; border-radius: 6px; background: #f1f5f9; color: #334155; font-size: 0.74rem; font-weight: 800; text-align: center; }
.pa-bal-sim-grade__tag.is-baru { background: #4f46e5; color: #fff; }
.pa-bal-sim-kotak { display: inline-flex; align-items: center; gap: 0.3rem; }
.pa-bal-sim-kotak small { color: #64748b; font-size: 0.72rem; }
.pa-bal-sim-kotak__no { display: inline-grid; place-items: center; width: 22px; height: 22px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; background: #f1f5f9; color: #334155; }
.pa-bal-sim-kotak__no.is-s-success { background: #dcfce7; color: #15803d; }
.pa-bal-sim-kotak__no.is-s-purple { background: #f3e8ff; color: #7e22ce; }
.pa-bal-sim-kotak__no.is-s-brand { background: #e0e7ff; color: #4338ca; }
.pa-bal-sim-kotak__no.is-s-teal { background: #ccfbf1; color: #0f766e; }
.pa-bal-sim-kotak__no.is-s-warning { background: #fef3c7; color: #b45309; }
.pa-bal-sim-kotak__no.is-s-info { background: #e0f2fe; color: #0369a1; }
.pa-bal-sim-kotak__no.is-s-danger { background: #fee2e2; color: #b91c1c; }
.pa-bal-sim-redup { font-size: 0.72rem; color: #94a3b8; }
.pa-bal-sim-luar { padding: 0.8rem 0.9rem; border: 1px solid #fde68a; border-radius: 12px; background: #fffbeb; }
.pa-bal-sim-luar h4 { display: flex; align-items: center; gap: 0.4rem; margin: 0; font-size: 0.85rem; color: #92400e; }
.pa-bal-sim-luar p { margin: 0.25rem 0 0.5rem; font-size: 0.76rem; color: #a16207; }
.pa-bal-sim-luar ul { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.4rem; }
.pa-bal-sim-luar li { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.35rem 0.6rem; border-radius: 8px; background: #fff; font-size: 0.78rem; }

/* Alat */
.pa-bal-sim-alat { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; }
.pa-bal-sim-seg { display: inline-flex; gap: 2px; padding: 2px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
.pa-bal-sim-seg button { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.3rem 0.75rem; border: 0; border-radius: 8px; background: transparent; color: #475569; font-size: 0.76rem; font-weight: 700; cursor: pointer; }
.pa-bal-sim-seg button.is-aktif { background: #fff; color: #4338ca; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.1); }
.pa-bal-sim-centang { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; color: #334155; }

/* HAV map (bergaya halaman HAV Map) */
.pa-bal-hv { display: flex; flex-direction: column; gap: 0.6rem; overflow-x: auto; }
.pa-bal-hv__kolom { display: grid; grid-template-columns: 120px repeat(3, minmax(190px, 1fr)); gap: 0.6rem; }
.pa-bal-hv__sudut { display: flex; flex-direction: column; justify-content: center; gap: 0.2rem; padding: 0.5rem; border: 1px dashed #c7d2fe; border-radius: 10px; font-size: 0.68rem; font-weight: 700; color: #4338ca; }
.pa-bal-hv__sudut span { display: inline-flex; align-items: center; gap: 0.25rem; }
.pa-bal-hv__sumbu-x { display: flex; flex-direction: column; gap: 0.1rem; padding: 0.5rem 0.7rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
.pa-bal-hv__sumbu-x strong { font-size: 0.8rem; color: #0f172a; }
.pa-bal-hv__sumbu-x small { font-size: 0.7rem; color: #64748b; }
.pa-bal-hv__titik { width: 18px; height: 4px; border-radius: 4px; background: #cbd5e1; }
.pa-bal-hv__titik.is-rendah { background: #f59e0b; }
.pa-bal-hv__titik.is-sedang { background: #6366f1; }
.pa-bal-hv__titik.is-tinggi { background: #22c55e; }
.pa-bal-hv__badan { display: grid; grid-template-columns: 120px 1fr; gap: 0.6rem; }
.pa-bal-hv__baris { display: grid; grid-template-rows: repeat(3, minmax(11rem, 1fr)); gap: 0.6rem; }
.pa-bal-hv__sumbu-y { display: flex; flex-direction: column; justify-content: center; gap: 0.35rem; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
.pa-bal-hv__sumbu-y strong { font-size: 0.78rem; color: #0f172a; }
.pa-bal-hv__grid { display: grid; grid-template-columns: repeat(3, minmax(190px, 1fr)); grid-template-rows: repeat(3, minmax(11rem, 1fr)); gap: 0.6rem; }
.pa-bal-hv__kotak { min-width: 0; }
.pa-bal-hv__judul { font-size: 0.82rem; font-weight: 800; }
.pa-bal-hv__lencana { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem; }
.pa-bal-hv__sumbu { font-size: 0.7rem; color: #64748b; }
.pa-bal-hv__daftar { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem; max-height: 9.5rem; overflow-y: auto; }
.pa-bal-hv__daftar li { display: flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.4rem; border-radius: 8px; background: rgba(255, 255, 255, 0.75); border: 1px solid transparent; font-size: 0.74rem; }
.pa-bal-hv__daftar li.is-sesi { border-color: #c7d2fe; background: #eef2ff; }
.pa-bal-hv__daftar li.is-pindah { border-color: #fbbf24; background: #fffbeb; }
.pa-bal-hv__inisial { flex: 0 0 auto; display: inline-grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; background: #e2e8f0; color: #334155; font-size: 0.62rem; font-weight: 800; }
.pa-bal-hv__daftar li.is-sesi .pa-bal-hv__inisial { background: #6366f1; color: #fff; }
.pa-bal-hv__nama { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #1e293b; font-weight: 600; }
.pa-bal-hv__dari { flex: 0 0 auto; padding: 0 0.35rem; border-radius: 999px; background: #fde68a; color: #92400e; font-size: 0.62rem; font-weight: 800; }
.pa-bal-hv__dari.is-ke { background: #e2e8f0; color: #475569; }
.pa-bal-hv__sesi { flex: 0 0 auto; padding: 0 0.35rem; border-radius: 999px; background: #e0e7ff; color: #4338ca; font-size: 0.62rem; font-weight: 800; }
.pa-bal-hv__kosong { display: flex; align-items: center; gap: 0.3rem; margin: 0; font-size: 0.72rem; color: #94a3b8; }

/* Legenda */
.pa-bal-sim-legenda { display: flex; flex-wrap: wrap; gap: 0.9rem; margin: 0; font-size: 0.72rem; color: #64748b; }
.pa-bal-sim-legenda span { display: inline-flex; align-items: center; gap: 0.3rem; }
.pa-bal-sim-legenda i { display: inline-block; width: 12px; height: 12px; border-radius: 4px; }
.pa-bal-sim-legenda i.is-sesi { background: #6366f1; border-radius: 50%; }
.pa-bal-sim-legenda i.is-lain { background: #94a3b8; border-radius: 50%; }
.pa-bal-sim-legenda i.is-pindah { background: #fde68a; border: 1px solid #fbbf24; }
.pa-bal-sim-legenda i.is-grade { background: #fff; border: 2px solid #f59e0b; border-radius: 50%; }
.pa-bal-sim-legenda i.is-garis-lama { height: 0; border-top: 2px dashed #94a3b8; border-radius: 0; width: 18px; }
.pa-bal-sim-legenda i.is-garis-baru { height: 0; border-top: 3px solid #4f46e5; border-radius: 0; width: 18px; }

/* ═════ Kurva Normal & Sebaran ═════ */
.pa-bal-kv { display: flex; flex-direction: column; gap: 0.85rem; }

/* Toolbar & Group Selector */
.pa-bal-kv-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.6rem 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #f8fafc;
}
.pa-bal-kv-grup-wrap {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.pa-bal-kv-grup-label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 750;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.pa-bal-sim-grup {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.pa-bal-sim-grup button {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.8rem;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #ffffff;
    color: #334155;
    font-size: 0.76rem;
    font-weight: 750;
    cursor: pointer;
    transition: all 0.15s ease;
}
.pa-bal-sim-grup button:hover {
    border-color: #94a3b8;
    background: #f1f5f9;
}
.pa-bal-sim-grup button.is-aktif {
    border-color: #4f46e5;
    background: #4f46e5;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.25);
}
.pa-bal-sim-grup__n {
    padding: 0.05rem 0.4rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.68rem;
    font-weight: 750;
}
.pa-bal-sim-grup button.is-aktif .pa-bal-sim-grup__n {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
}
.pa-bal-sim-grup__sesi-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    background: #e0e7ff;
    color: #4338ca;
    font-size: 0.65rem;
    font-weight: 800;
}
.pa-bal-sim-grup button.is-aktif .pa-bal-sim-grup__sesi-pill {
    background: #ffffff;
    color: #4338ca;
}
.pa-bal-sim-grup__titik {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #4f46e5;
}
.pa-bal-kv-panduan-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border: 1px solid #c7d2fe;
    border-radius: 10px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.76rem;
    font-weight: 750;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.pa-bal-kv-panduan-btn:hover {
    background: #e0e7ff;
}
.pa-bal-kv-panduan-btn__panah {
    transition: transform 0.2s ease;
}
.pa-bal-kv-panduan-btn.is-buka .pa-bal-kv-panduan-btn__panah {
    transform: rotate(180deg);
}

/* Panduan Card */
.pa-bal-kv-panduan-card {
    border: 1px solid #c7d2fe;
    border-radius: 14px;
    background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
    padding: 0.85rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.pa-bal-kv-panduan-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    border-bottom: 1px solid #e0e7ff;
    padding-bottom: 0.5rem;
    flex-wrap: wrap;
}
.pa-bal-kv-panduan-card__head-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.82rem;
    font-weight: 800;
    color: #312e81;
}
.pa-bal-kv-panduan-card__head small {
    font-size: 0.72rem;
    color: #6366f1;
}
.pa-bal-kv-panduan-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}
.pa-bal-kv-panduan-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.65rem 0.75rem;
    background: #ffffff;
    border: 1px solid #e0e7ff;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.pa-bal-kv-panduan-item__badge {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #4f46e5;
    color: #ffffff;
    display: inline-grid;
    place-items: center;
    font-size: 0.7rem;
    font-weight: 800;
}
.pa-bal-kv-panduan-item__content strong {
    display: block;
    font-size: 0.76rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 0.2rem;
}
.pa-bal-kv-panduan-item__content p {
    margin: 0;
    font-size: 0.72rem;
    color: #475569;
    line-height: 1.45;
}

/* Stat Grid (4 Metrics) */
.pa-bal-kv__stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.65rem;
}
.pa-bal-kv__stat-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.pa-bal-kv__stat-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-grid;
    place-items: center;
}
.pa-bal-kv__stat-icon.is-mu { background: #e0e7ff; color: #4338ca; }
.pa-bal-kv__stat-icon.is-sd { background: #f3e8ff; color: #7e22ce; }
.pa-bal-kv__stat-icon.is-n { background: #ccfbf1; color: #0f766e; }
.pa-bal-kv__stat-icon.is-level { background: #f1f5f9; color: #475569; }
.pa-bal-kv__stat-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.pa-bal-kv__stat-label {
    font-size: 0.68rem;
    font-weight: 750;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.pa-bal-kv__stat-val {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.pa-bal-kv__stat-val strong {
    font-size: 1.15rem;
    font-weight: 850;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
}
.pa-bal-kv__stat-unit {
    font-size: 0.76rem;
    font-weight: 600;
    color: #64748b;
}
.pa-bal-kv__stat-sub {
    font-size: 0.68rem;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pa-bal-kv__level-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}
.pa-bal-kv__level-pill {
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
    background: #f1f5f9;
    color: #334155;
    font-size: 0.7rem;
    font-weight: 750;
}

/* Pergeseran Batas Grade Bar */
.pa-bal-kv-batas-bar {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    padding: 0.45rem 0.75rem;
    border-radius: 10px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    font-size: 0.74rem;
}
.pa-bal-kv-batas-bar__label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 750;
    color: #92400e;
    white-space: nowrap;
}
.pa-bal-kv-batas-bar__items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.pa-bal-kv-batas-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.1rem 0.45rem;
    background: #ffffff;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    font-size: 0.72rem;
}
.pa-bal-kv-batas-pill__kode {
    font-weight: 800;
    color: #92400e;
}
.pa-bal-kv-batas-pill__nilai {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-variant-numeric: tabular-nums;
    color: #451a03;
}
.pa-bal-kv-batas-pill__nilai s { color: #94a3b8; }

/* SVG Chart Box Frame */
.pa-bal-kv-chart-box {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: #ffffff;
    padding: 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.pa-bal-kv-chart-header {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.pa-bal-kv-chart-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 800;
    color: #0f172a;
}
.pa-bal-kv-chart-subtitle {
    font-size: 0.74rem;
    color: #64748b;
}
.pa-bal-kv__grafik {
    width: 100%;
    height: auto;
    aspect-ratio: 800 / 340;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    background: #ffffff;
}
.pa-bal-kv__label-grade {
    font-size: 11px;
    font-weight: 850;
}
.pa-bal-kv__label-target {
    font-size: 8.5px;
    font-weight: 700;
    opacity: 0.9;
}
.pa-bal-kv__batas-angka {
    font-size: 9px;
    font-weight: 750;
    fill: #475569;
    font-variant-numeric: tabular-nums;
}
.pa-bal-kv__mu-lama {
    font-size: 8.5px;
    font-weight: 750;
    fill: #475569;
    font-variant-numeric: tabular-nums;
}
.pa-bal-kv__mu-baru {
    font-size: 9.5px;
    font-weight: 800;
    fill: #ffffff;
    font-variant-numeric: tabular-nums;
}
.pa-bal-kv__delta-arc {
    font-size: 9px;
    font-weight: 800;
    fill: #ffffff;
    font-variant-numeric: tabular-nums;
}
.pa-bal-kv__tik {
    font-size: 10px;
    fill: #64748b;
    font-variant-numeric: tabular-nums;
}
.pa-bal-kv__sumbu {
    font-size: 10px;
    font-weight: 750;
    fill: #64748b;
    letter-spacing: 0.03em;
}
.pa-bal-kv__nama {
    font-size: 9.5px;
    font-weight: 800;
    fill: #312e81;
}
.pa-bal-kv__nama-halo {
    font-size: 9.5px;
    font-weight: 800;
    fill: none;
    stroke: #ffffff;
    stroke-width: 3.5px;
    stroke-linejoin: round;
}
.pa-bal-kv-legenda {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 1.1rem;
    padding-top: 0.35rem;
    border-top: 1px solid #f1f5f9;
    font-size: 0.72rem;
    color: #475569;
}
.pa-bal-kv-legenda__item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
}
.pa-bal-kv-legenda__line {
    display: inline-block;
    width: 20px;
    height: 0;
}
.pa-bal-kv-legenda__line.is-garis-lama {
    border-top: 2px dashed #94a3b8;
}
.pa-bal-kv-legenda__line.is-garis-baru {
    border-top: 3px solid #4f46e5;
}
.pa-bal-kv-legenda__badge-mu {
    display: inline-grid;
    place-items: center;
    width: 16px;
    height: 16px;
    border-radius: 4px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 0.65rem;
    font-weight: 800;
}
.pa-bal-kv-legenda__dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.pa-bal-kv-legenda__dot.is-sesi {
    background: #4f46e5;
}
.pa-bal-kv-legenda__dot.is-lain {
    background: #94a3b8;
}
.pa-bal-kv-legenda__dot.is-grade-ubah {
    background: #ffffff;
    border: 2px solid #f59e0b;
}
.pa-bal-kv-legenda__arc-sample {
    display: inline-block;
    width: 18px;
    height: 8px;
    border: 1.8px dashed #10b981;
    border-bottom: 0;
    border-radius: 9px 9px 0 0;
}

/* Roster Peserta Sesi */
.pa-bal-kv-roster {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.6rem 0.8rem;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.pa-bal-kv-roster__title {
    font-size: 0.72rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}
.pa-bal-kv-roster__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.pa-bal-kv-roster__chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.25rem 0.6rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    font-size: 0.75rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.pa-bal-kv-roster__chip.is-ubah-grade {
    border-color: #fcd34d;
    background: #fffdf5;
}
.pa-bal-kv-roster__nama {
    font-weight: 750;
    color: #1e293b;
}
.pa-bal-kv-roster__skor {
    font-variant-numeric: tabular-nums;
    color: #475569;
    font-size: 0.72rem;
}
.pa-bal-kv-roster__skor s {
    color: #94a3b8;
}
.pa-bal-kv-roster__skor b {
    color: #0f172a;
}
.pa-bal-kv-roster__tag-alert {
    font-size: 0.68rem;
    font-weight: 700;
    color: #b45309;
}

/* Dampak Pergeseran Grade Grid */
.pa-bal-kv-perubahan {
    border: 1px solid #fed7aa;
    border-radius: 14px;
    background: #fffbf5;
    padding: 0.85rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}
.pa-bal-kv-perubahan__header {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.pa-bal-kv-perubahan__title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.82rem;
    font-weight: 800;
    color: #9a3412;
}
.pa-bal-kv-perubahan__sub {
    font-size: 0.72rem;
    color: #c2410c;
}
.pa-bal-kv-perubahan__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.5rem;
}
.pa-bal-kv-orang-kartu {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.55rem 0.75rem;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #fed7aa;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.pa-bal-kv-orang-kartu.is-sesi {
    border-left: 3px solid #6366f1;
}
.pa-bal-kv-orang-kartu.is-luar {
    border-left: 3px solid #f59e0b;
}
.pa-bal-kv-orang-kartu__kiri {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
}
.pa-bal-kv-orang-kartu__bio {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}
.pa-bal-kv-orang-kartu__bio strong {
    font-size: 0.8rem;
    font-weight: 750;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pa-bal-kv-orang-kartu__meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.pa-bal-kv-orang-kartu__nilai {
    font-size: 0.68rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}
.pa-bal-kv-orang-kartu__nilai s { color: #94a3b8; }
.pa-bal-kv-orang-kartu__kanan {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.2rem;
    flex-shrink: 0;
}
.pa-bal-kv-orang-kartu__grade {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.pa-bal-kv-orang-kartu__alasan {
    font-size: 0.65rem;
    color: #94a3b8;
    text-align: right;
    max-width: 140px;
    line-height: 1.25;
}

/* Distribution Table & Visual Progress Bar */
.pa-bal-kv-tabel-card {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: #ffffff;
    padding: 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.pa-bal-kv-tabel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.pa-bal-kv-tabel-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
    font-size: 0.85rem;
    font-weight: 800;
    color: #0f172a;
}
.pa-bal-kv-tabel-sub {
    margin: 0.2rem 0 0;
    font-size: 0.74rem;
    color: #64748b;
}
.pa-bal-sim-grade__pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 0.76rem;
}
.pa-bal-sim-grade__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: var(--grade-clr, #64748b);
}
.pa-bal-kv-z {
    font-size: 0.74rem;
    color: #475569;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.pa-bal-kv-rentang {
    font-size: 0.76rem;
    font-weight: 600;
    color: #1e293b;
    font-variant-numeric: tabular-nums;
}
.pa-bal-kv-target-persen {
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
    font-variant-numeric: tabular-nums;
}
.pa-bal-kv-bar-banding {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    min-width: 120px;
}
.pa-bal-kv-bar-track {
    position: relative;
    flex: 1;
    height: 10px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: visible;
}
.pa-bal-kv-bar-fill {
    display: block;
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}
.pa-bal-kv-bar-target-marker {
    position: absolute;
    top: -2px;
    width: 2px;
    height: 14px;
    background: #0f172a;
    border-radius: 1px;
    z-index: 2;
    transform: translateX(-50%);
}
.pa-bal-kv-bar-label {
    flex-shrink: 0;
    font-size: 0.72rem;
    font-weight: 750;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    width: 32px;
    text-align: right;
}
.pa-bal-kv-sebelum {
    font-size: 0.76rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}
.pa-bal-kv-sesudah-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    font-variant-numeric: tabular-nums;
}
.pa-bal-kv-sesudah-wrap strong {
    font-size: 0.8rem;
    color: #0f172a;
}
.pa-bal-kv-kaki {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin: 0;
    padding: 0.45rem 0.65rem;
    border-radius: 8px;
    background: #f8fafc;
    font-size: 0.72rem;
    color: #64748b;
    line-height: 1.45;
}
.pa-bal-sim-chip.is-netral {
    background: #f1f5f9;
    color: #475569;
}

/* Fallback Sebaran Pita */
.pa-bal-kv-pita-box {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #ffffff;
}
.pa-bal-dist { display: flex; flex-direction: column; gap: 0.45rem; }
.pa-bal-dist__baris { display: grid; grid-template-columns: 80px 1fr 90px; align-items: center; gap: 0.6rem; }
.pa-bal-dist__bar { display: flex; flex-direction: column; gap: 2px; }
.pa-bal-dist__bar span { display: block; height: 9px; border-radius: 4px; min-width: 2px; }
.pa-bal-dist__bar .is-lama { background: #cbd5e1; }
.pa-bal-dist__bar .is-baru { background: #6366f1; }
.pa-bal-dist__angka { font-size: 0.78rem; color: #475569; text-align: right; font-variant-numeric: tabular-nums; }

@media (max-width: 900px) {
    .pa-bal-sim-ringkas { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pa-bal-kv__stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pa-bal-kv-panduan-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pa-bal-kv-toolbar { flex-direction: column; align-items: stretch; }
}
@media (max-width: 600px) {
    .pa-bal-kv__stat-grid { grid-template-columns: 1fr; }
    .pa-bal-kv-panduan-grid { grid-template-columns: 1fr; }
}
</style>

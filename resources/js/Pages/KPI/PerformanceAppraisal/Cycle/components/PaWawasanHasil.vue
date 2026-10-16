<template>
    <div class="pa-wh" data-uji="wawasan-hasil">
        <!-- 1. Komposisi nilai akhir -->
        <HcisCard
            title="Komposisi Nilai Akhir"
            kicker="FORMULA & PEMBOBOTAN"
            description="Rincian pembentukan Final Score dari nilai berbobot Kinerja (KPI) dan Kompetensi 360°."
            icon="calculator"
            variant="glass"
            sentiment="teal"
            class="pa-wh__kartu"
            data-uji="wawasan-komposisi"
        >
            <div class="pa-wh__komposisi">
                <!-- Dual Pillar Formula Cards -->
                <div class="pa-wh__pillars">
                    <!-- KPI Pillar -->
                    <div class="pa-wh__pillar pa-wh__pillar--kpi">
                        <div class="pa-wh__pillar-head">
                            <div class="pa-wh__pillar-title">
                                <HcisIcon name="speedometer2" size="xs" color="brand" />
                                <strong>Kinerja (KPI)</strong>
                            </div>
                            <span class="pa-wh__pillar-weight pa-wh__pillar-weight--kpi">
                                Bobot {{ angka(p.Bobot_KPI_Terpakai) }}%
                            </span>
                        </div>
                        <div class="pa-wh__pillar-body">
                            <div class="pa-wh__pillar-main">
                                <span class="pa-wh__pillar-val text-brand">{{ angka(p.Nilai_KPI) }}</span>
                                <span class="pa-wh__pillar-calc">× {{ angka(p.Bobot_KPI_Terpakai, 0) }}%</span>
                            </div>
                            <div class="pa-wh__pillar-contrib">
                                <span class="pa-wh__pillar-contrib-lbl">Kontribusi</span>
                                <strong class="pa-wh__pillar-contrib-num text-brand">
                                    {{ angka(sumbangan(p.Nilai_KPI, p.Bobot_KPI_Terpakai)) }}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <!-- Operator Plus Sign -->
                    <div class="pa-wh__plus-sign">
                        <HcisIcon name="plus" size="sm" />
                    </div>

                    <!-- Kompetensi Pillar -->
                    <div class="pa-wh__pillar pa-wh__pillar--kompetensi">
                        <div class="pa-wh__pillar-head">
                            <div class="pa-wh__pillar-title">
                                <HcisIcon name="diagram-3" size="xs" color="purple" />
                                <strong>Kompetensi 360°</strong>
                            </div>
                            <span class="pa-wh__pillar-weight pa-wh__pillar-weight--kompetensi">
                                Bobot {{ angka(p.Bobot_Kompetensi_Terpakai) }}%
                            </span>
                        </div>
                        <div class="pa-wh__pillar-body">
                            <div class="pa-wh__pillar-main">
                                <span class="pa-wh__pillar-val text-purple">{{ angka(p.Nilai_Kompetensi) }}</span>
                                <span class="pa-wh__pillar-calc">× {{ angka(p.Bobot_Kompetensi_Terpakai, 0) }}%</span>
                            </div>
                            <div class="pa-wh__pillar-contrib">
                                <span class="pa-wh__pillar-contrib-lbl">Kontribusi</span>
                                <strong class="pa-wh__pillar-contrib-num text-purple">
                                    {{ angka(sumbangan(p.Nilai_Kompetensi, p.Bobot_Kompetensi_Terpakai)) }}
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Weight Proportion Bar -->
                <div v-if="p.Bobot_KPI_Terpakai != null && p.Bobot_Kompetensi_Terpakai != null" class="pa-wh__proportion">
                    <div class="pa-wh__proportion-bar">
                        <div
                            class="pa-wh__proportion-segment is-kpi"
                            :style="{ width: `${Number(p.Bobot_KPI_Terpakai) || 0}%` }"
                            :title="`KPI: ${angka(p.Bobot_KPI_Terpakai)}%`"
                        >
                            <span>{{ angka(p.Bobot_KPI_Terpakai, 0) }}% KPI (+{{ angka(sumbangan(p.Nilai_KPI, p.Bobot_KPI_Terpakai)) }})</span>
                        </div>
                        <div
                            class="pa-wh__proportion-segment is-kompetensi"
                            :style="{ width: `${Number(p.Bobot_Kompetensi_Terpakai) || 0}%` }"
                            :title="`Kompetensi: ${angka(p.Bobot_Kompetensi_Terpakai)}%`"
                        >
                            <span>{{ angka(p.Bobot_Kompetensi_Terpakai, 0) }}% Komp (+{{ angka(sumbangan(p.Nilai_Kompetensi, p.Bobot_Kompetensi_Terpakai)) }})</span>
                        </div>
                    </div>
                </div>

                <!-- Final Score Result Banner -->
                <div class="pa-wh__final-box" data-uji="komposisi-final">
                    <div class="pa-wh__final-box-left">
                        <div class="pa-wh__final-icon">
                            <HcisIcon name="award-fill" size="md" />
                        </div>
                        <div class="pa-wh__final-meta">
                            <span class="pa-wh__final-label">Final Score Terkalkulasi</span>
                            <span class="pa-wh__final-sum">
                                {{ angka(sumbangan(p.Nilai_KPI, p.Bobot_KPI_Terpakai)) }} (KPI) + {{ angka(sumbangan(p.Nilai_Kompetensi, p.Bobot_Kompetensi_Terpakai)) }} (Kompetensi)
                            </span>
                        </div>
                    </div>
                    <div class="pa-wh__final-box-right">
                        <strong class="pa-wh__final-num">{{ angka(p.Final_Score) }}</strong>
                    </div>
                </div>

                <!-- Contextual Details & Audit List -->
                <div class="pa-wh__audit-chips">
                    <div v-if="p.KPI_Periode_Dari || p.KPI_Periode_Sampai" class="pa-wh__chip">
                        <HcisIcon name="calendar-range" size="2xs" />
                        <span>Periode KPI: <b>{{ tanggal(p.KPI_Periode_Dari) }} – {{ tanggal(p.KPI_Periode_Sampai) }}</b></span>
                    </div>

                    <div class="pa-wh__chip is-muted">
                        <HcisIcon name="clock-history" size="2xs" />
                        <span>Versi {{ p.Calculation_Version || 1 }} · {{ waktu(p.Calculated_At) }}</span>
                    </div>

                    <div
                        v-if="!modeVerifikasi && (p.Kode_Grade_Sistem || p.Kode_Grade_Final)"
                        class="pa-wh__chip is-grade"
                        data-uji="komposisi-grade"
                    >
                        <HcisIcon name="award" size="2xs" />
                        <span>Grade sistem <b>{{ labelGrade(p.Kode_Grade_Sistem) }}</b> → final <b>{{ labelGrade(p.Kode_Grade_Final || p.Kode_Grade_Sistem) }}</b></span>
                        <em v-if="p.Kode_Grade_Final && p.Kode_Grade_Sistem && p.Kode_Grade_Final !== p.Kode_Grade_Sistem" class="pa-wh__ubah">berubah</em>
                    </div>

                    <div
                        v-if="Number(p.Jumlah_KPI_Belum_Disetujui) > 0"
                        class="pa-wh__chip is-warning"
                        data-uji="komposisi-kpi-belum"
                    >
                        <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                        <span>{{ p.Jumlah_KPI_Belum_Disetujui }} KPI belum disetujui pada periode ini</span>
                    </div>

                    <div
                        v-if="kenaCap"
                        class="pa-wh__chip is-info"
                        data-uji="komposisi-cap"
                    >
                        <HcisIcon name="arrow-down-circle" size="2xs" />
                        <span>Nilai KPI dibatasi dari {{ angka(p.Nilai_KPI_Sebelum_Cap) }} menjadi {{ angka(p.Nilai_KPI) }}</span>
                    </div>
                </div>
            </div>
        </HcisCard>

        <!-- 2. Posisi 9-box HAV -->
        <HcisCard
            title="Posisi 9-Box HAV"
            kicker="KINERJA × KOMPETENSI"
            description="Pemetaan talenta 3×3 berdasarkan tingkat Kinerja (KPI) dan Kompetensi."
            icon="grid-3x3-gap-fill"
            variant="glass"
            sentiment="purple"
            class="pa-wh__kartu"
            data-uji="wawasan-hav"
        >
            <div v-if="kuadran" class="pa-wh__hav-wrap">
                <!-- 3x3 Matrix with Column & Row Guides -->
                <div class="pa-wh__hav-matrix">
                    <!-- Column Header (X Axis: Kinerja) -->
                    <div class="pa-wh__hav-col-headers">
                        <span class="pa-wh__hav-axis-tag">Y↓ X→</span>
                        <span class="pa-wh__hav-col-tag">Rendah (KPI)</span>
                        <span class="pa-wh__hav-col-tag">Sedang (KPI)</span>
                        <span class="pa-wh__hav-col-tag">Tinggi (KPI)</span>
                    </div>

                    <!-- Matrix Rows (Y Axis: Kompetensi) -->
                    <div class="pa-wh__hav-rows">
                        <div
                            v-for="baris in BARIS_HAV"
                            :key="baris.label"
                            class="pa-wh__hav-row"
                        >
                            <span class="pa-wh__hav-row-tag">{{ baris.label }}</span>
                            <div class="pa-wh__hav-row-cells">
                                <button
                                    v-for="n in baris.kotakList"
                                    :key="n"
                                    type="button"
                                    class="pa-wh__hav-cell"
                                    :class="{
                                        'is-target': activeBoxNumber === n,
                                        'is-preview': currentInspectedBoxNum === n,
                                        [`is-${KAMUS_HAV[n].sentimen}`]: true,
                                    }"
                                    :title="`Kotak ${n}: ${KAMUS_HAV[n].nama} (${tingkat(KAMUS_HAV[n].komp)} Komp, ${tingkat(KAMUS_HAV[n].perf)} Perf)`"
                                    @mouseenter="hoveredBox = n"
                                    @mouseleave="hoveredBox = null"
                                    @click="clickedBox = (clickedBox === n ? null : n)"
                                >
                                    <div class="pa-wh__hav-cell-top">
                                        <span
                                            class="pa-wh__hav-kotak"
                                            :class="{
                                                'is-aktif': activeBoxNumber === n,
                                                [`is-${KAMUS_HAV[n].sentimen}`]: activeBoxNumber === n,
                                            }"
                                        >{{ n }}</span>
                                        <HcisIcon :name="KAMUS_HAV[n].ikon" size="2xs" />
                                    </div>
                                    <span class="pa-wh__hav-cell-label">{{ KAMUS_HAV[n].namaSingkat }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inspected / Current Quadrant Details -->
                <div class="pa-wh__hav-showcase" :class="`is-${currentInspectedBox.sentimen}`">
                    <div class="pa-wh__hav-showcase-head">
                        <div class="pa-wh__hav-jewel" :class="`is-${currentInspectedBox.sentimen}`">
                            <HcisIcon :name="currentInspectedBox.ikon" size="sm" />
                        </div>
                        <div class="pa-wh__hav-showcase-title">
                            <div class="pa-wh__hav-showcase-meta">
                                <span class="pa-wh__hav-box-badge" :class="`is-${currentInspectedBox.sentimen}`">
                                    Kotak {{ currentInspectedBox.nomor }}
                                </span>
                                <span v-if="activeBoxNumber === currentInspectedBox.nomor" class="pa-wh__hav-target-badge">
                                    Posisi Karyawan
                                </span>
                                <button
                                    v-else
                                    type="button"
                                    class="pa-wh__hav-reset-btn"
                                    @click="resetPreview"
                                >
                                    Kembali ke Posisi
                                </button>
                            </div>
                            <strong class="pa-wh__hav-name" data-uji="hav-nama">
                                {{ activeBoxNumber === currentInspectedBox.nomor ? kuadran.Nama_Kuadran : currentInspectedBox.nama }}
                            </strong>
                        </div>
                    </div>

                    <div class="pa-wh__hav-dimensions">
                        <div class="pa-wh__hav-dim-chip">
                            <HcisIcon name="speedometer2" size="2xs" />
                            <span>Kinerja: <b>{{ tingkat(currentInspectedBox.perf) }}</b></span>
                        </div>
                        <div class="pa-wh__hav-dim-chip">
                            <HcisIcon name="diagram-3" size="2xs" />
                            <span>Kompetensi: <b>{{ tingkat(currentInspectedBox.komp) }}</b></span>
                        </div>
                    </div>

                    <p class="pa-wh__hav-desc">
                        {{ (activeBoxNumber === currentInspectedBox.nomor && kuadran.Deskripsi) ? kuadran.Deskripsi : currentInspectedBox.deskripsi }}
                    </p>
                </div>
            </div>

            <!-- Empty State -->
            <p v-else class="pa-wh__kosong">Kuadran belum terbentuk; muncul setelah hasil dihitung.</p>
        </HcisCard>

        <!-- 3. Posisi di grup kurva -->
        <HcisCard
            v-if="!modeVerifikasi"
            title="Posisi di Grup Kurva"
            kicker="PERINGKAT · SKOR Z · GRADE BERIKUTNYA"
            icon="bar-chart-steps"
            variant="glass"
            sentiment="amber"
            class="pa-wh__kartu"
            data-uji="wawasan-kurva"
        >
            <template v-if="kurva">
                <div class="pa-wh__kurva-atas">
                    <div><small>Peringkat</small><strong data-uji="kurva-peringkat">{{ kurva.peringkat }} <em>dari {{ kurva.n }}</em></strong></div>
                    <div><small>Skor z</small><strong>{{ tanda(kurva.z) }}</strong></div>
                    <div>
                        <small>Grade</small>
                        <strong data-uji="kurva-grade">{{ labelGrade(kurva.grade_final || kurva.grade) }}</strong>
                        <!-- Grade berlaku bisa hasil kalibrasi; letaknya di pita kurva disebut bila berbeda. -->
                        <small
                            v-if="kurva.grade_sistem && kurva.grade_final && kurva.grade_sistem !== kurva.grade_final"
                            class="pa-wh__redup"
                            data-uji="kurva-grade-sistem"
                        >pita kurva: {{ labelGrade(kurva.grade_sistem) }} (dikalibrasi)</small>
                    </div>
                </div>
                <p class="pa-wh__kurva-teks" data-uji="kurva-berikut">
                    <template v-if="kurva.grade_berikut">
                        Kurang <b>{{ angka(kurva.grade_berikut.jarak) }} poin</b> menuju grade {{ labelGrade(kurva.grade_berikut.kode) }} (batas {{ angka(kurva.grade_berikut.batas) }}).
                    </template>
                    <template v-else>Sudah di grade tertinggi grup ini.</template>
                </p>
                <small class="pa-wh__redup">
                    {{ kurva.grup.nama }} · μ {{ angka(kurva.rata) }} · σ {{ angka(kurva.sd) }}<template v-if="kurva.persentil !== null"> · di atas {{ kurva.persentil }}% anggota</template><template v-if="!kurva.terkunci"> · kurva belum dikunci</template>
                </small>
                <p v-for="w in kurva.peringatan || []" :key="w" class="pa-wh__peringatan"><HcisIcon name="exclamation-triangle" size="2xs" /> {{ w }}</p>
            </template>
            <p v-else class="pa-wh__kosong">Siklus ini tidak memakai metode Kurva, atau hasil orang ini belum dihitung.</p>
        </HcisCard>

        <!-- 4. Tren vs siklus sebelumnya -->
        <HcisCard
            title="Tren vs Siklus Sebelumnya"
            :kicker="tren ? (tren.cycle.kode_cycle || 'SIKLUS LALU') : 'RIWAYAT'"
            description="Perbandingan metrik capaian saat ini terhadap siklus evaluasi sebelumnya."
            icon="graph-up-arrow"
            variant="glass"
            sentiment="success"
            class="pa-wh__kartu"
            data-uji="wawasan-tren"
        >
            <div v-if="tren" class="pa-wh__tren">
                <!-- Comparison Benchmark Strip -->
                <div class="pa-wh__tren-strip">
                    <div class="pa-wh__tren-strip-info">
                        <HcisIcon name="clock-history" size="2xs" />
                        <span>Pembanding: <b>{{ tren.cycle.nama_cycle || tren.cycle.kode_cycle }}</b></span>
                    </div>
                    <HcisBadge
                        v-if="arahTrenFinal"
                        :label="arahTrenFinal.label"
                        :variant="arahTrenFinal.variant"
                        :leading-icon="arahTrenFinal.icon"
                        appearance="subtle"
                        size="2xs"
                    />
                </div>

                <!-- Comparison Metrics List -->
                <div class="pa-wh__tren-list">
                    <div
                        v-for="t in barisTren"
                        :key="t.kunci"
                        class="pa-wh__tren-item"
                        :class="`is-${t.sentimen}`"
                        :data-uji="`tren-${t.kunci}`"
                    >
                        <div class="pa-wh__tren-item-head">
                            <div class="pa-wh__tren-item-icon" :class="`is-${t.sentimen}`">
                                <HcisIcon :name="t.ikon" size="2xs" />
                            </div>
                            <strong class="pa-wh__tren-item-label">{{ t.label }}</strong>
                        </div>

                        <div class="pa-wh__tren-item-values">
                            <div class="pa-wh__tren-val-box is-prev">
                                <span class="pa-wh__tren-val-tag">Siklus Lalu</span>
                                <span class="pa-wh__tren-val-num">{{ t.sebelum }}</span>
                            </div>

                            <div class="pa-wh__tren-arrow">
                                <HcisIcon name="arrow-right" size="2xs" />
                            </div>

                            <div class="pa-wh__tren-val-box is-curr">
                                <span class="pa-wh__tren-val-tag">Siklus Ini</span>
                                <strong class="pa-wh__tren-val-num">{{ t.sekarang }}</strong>
                            </div>

                            <!-- Delta Pill -->
                            <div class="pa-wh__tren-delta-wrap">
                                <span
                                    v-if="t.selisih !== null"
                                    class="pa-wh__tren-delta"
                                    :class="t.selisih > 0 ? 'is-naik' : t.selisih < 0 ? 'is-turun' : 'is-tetap'"
                                >
                                    <HcisIcon
                                        :name="t.selisih > 0 ? 'arrow-up-right' : t.selisih < 0 ? 'arrow-down-right' : 'dash'"
                                        size="2xs"
                                    />
                                    <em>{{ tanda(t.selisih) }}</em>
                                </span>
                                <span
                                    v-else
                                    class="pa-wh__tren-delta is-grade"
                                    :class="{ 'is-changed': t.sebelum !== t.sekarang }"
                                >
                                    <em>{{ t.sebelum === t.sekarang ? 'Tetap' : 'Berubah' }}</em>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="pa-wh__tren-empty">
                <div class="pa-wh__tren-empty-icon">
                    <HcisIcon name="clock-history" size="sm" />
                </div>
                <div class="pa-wh__tren-empty-text">
                    <strong>Belum ada hasil siklus sebelumnya</strong>
                    <span>Data tren komparasi akan terbentuk setelah karyawan memiliki riwayat hasil penilaian pada siklus terdahulu.</span>
                </div>
            </div>
        </HcisCard>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { HcisBadge, HcisCard, HcisIcon } from '@/components/ui';
import { URUTAN_KOTAK_HAV, sentimenKotak, ikonKotak } from '../../utils/havKotak.js';

/* Wawasan di tab Ringkasan & Skor dialog hasil admin — bahan memeriksa apakah hasil masuk
   akal sebelum terbit, yang tidak terbaca dari kartu angka maupun radar: cara Final
   terbentuk, posisi 9-box, posisi di grup kurva, dan tren. */
const props = defineProps({
    detail: { type: Object, required: true },
    modeVerifikasi: { type: Boolean, default: false },
});

const p = computed(() => props.detail?.peserta || {});
const kuadran = computed(() => props.detail?.kuadran || null);
const kurva = computed(() => props.detail?.posisi_kurva || null);
const tren = computed(() => props.detail?.tren || null);

/* Interactive 9-Box Matrix Dictionary — TATA LETAK saja (posisi, tingkat, warna, ikon).
   Nama di sini hanya cadangan bila master tidak terkirim; yang ditampilkan adalah nama dari
   master N_PA_Hav_Quadrant (lihat kamusHav). Sampai 8 Okt 2026 nama kerasnya yang tampil dan
   berbeda dari master ("Star" vs "Striving Star", "Under Performer" vs "Deadwood"), sehingga
   kotak yang sama bernama lain di layar ini dibanding HAV Map dan dropdown kalibrasi. */
const KAMUS_HAV_DASAR = {
    1: { nomor: 1, nama: 'Star', namaSingkat: 'Star', komp: 'TINGGI', perf: 'TINGGI', sentimen: 'success', ikon: 'star-fill', deskripsi: 'Performa dan kompetensi sama-sama berada di tingkat tertinggi.' },
    2: { nomor: 2, nama: 'Future Star (High Performer)', namaSingkat: 'Future Star A', komp: 'SEDANG', perf: 'TINGGI', sentimen: 'purple', ikon: 'lightning-charge-fill', deskripsi: 'Performa tinggi didukung kompetensi tingkat sedang.' },
    3: { nomor: 3, nama: 'Future Star (High Potential)', namaSingkat: 'Future Star B', komp: 'TINGGI', perf: 'SEDANG', sentimen: 'brand', ikon: 'award-fill', deskripsi: 'Kompetensi tinggi dengan potensi performa yang terus berkembang.' },
    4: { nomor: 4, nama: 'Potential Candidate', namaSingkat: 'Potential', komp: 'SEDANG', perf: 'SEDANG', sentimen: 'teal', ikon: 'person-check-fill', deskripsi: 'Performa dan kompetensi stabil di tingkat sedang (kontributor inti).' },
    5: { nomor: 5, nama: 'Top Performer', namaSingkat: 'Top Performer', komp: 'RENDAH', perf: 'TINGGI', sentimen: 'warning', ikon: 'trophy-fill', deskripsi: 'Performa tinggi yang fokus pada keunggulan teknis / spesialis.' },
    6: { nomor: 6, nama: 'Contributor', namaSingkat: 'Contributor', komp: 'RENDAH', perf: 'SEDANG', sentimen: 'info', ikon: 'hand-thumbs-up-fill', deskripsi: 'Kontribusi kerja teratur dengan ruang peningkatan kompetensi.' },
    7: { nomor: 7, nama: 'Raw Diamond', namaSingkat: 'Raw Diamond', komp: 'TINGGI', perf: 'RENDAH', sentimen: 'purple', ikon: 'gem', deskripsi: 'Potensi kompetensi tinggi yang membutuhkan dorongan eksekusi.' },
    8: { nomor: 8, nama: 'Problem Employee', namaSingkat: 'Problem Emp.', komp: 'SEDANG', perf: 'RENDAH', sentimen: 'warning', ikon: 'exclamation-triangle-fill', deskripsi: 'Performa rendah dengan kompetensi sedang, memerlukan pendampingan.' },
    9: { nomor: 9, nama: 'Under Performer', namaSingkat: 'Under Perf.', komp: 'RENDAH', perf: 'RENDAH', sentimen: 'danger', ikon: 'x-octagon-fill', deskripsi: 'Performa dan kompetensi rendah, membutuhkan evaluasi dan perbaikan.' },
};

/* Nama per nomor kotak dari master yang dikirim detailHasil (`pilihan_kalibrasi.kuadran`).
   Dua kuadran bisa bernama SAMA (Future Star #2 dan #3), jadi label pendeknya diberi akhiran
   dari kodenya (FUTURE_STAR_A → "A") atau nomor kotaknya supaya sel grid tetap terbedakan. */
const KAMUS_HAV = computed(() => {
    const master = props.detail?.pilihan_kalibrasi?.kuadran || [];
    const cacahNama = {};
    master.forEach((k) => {
        cacahNama[k.Nama_Kuadran] = (cacahNama[k.Nama_Kuadran] || 0) + 1;
    });

    const hasil = {};
    Object.values(KAMUS_HAV_DASAR).forEach((dasar) => {
        const m = master.find((k) => Number(k.Nomor_Box) === dasar.nomor);
        if (!m?.Nama_Kuadran) {
            hasil[dasar.nomor] = dasar;
            return;
        }
        const akhir = String(m.Kode_Kuadran || '').split('_').pop();
        const pembeda = akhir && akhir.length === 1 ? akhir : `#${dasar.nomor}`;
        hasil[dasar.nomor] = {
            ...dasar,
            nama: m.Nama_Kuadran,
            namaSingkat: cacahNama[m.Nama_Kuadran] > 1 ? `${m.Nama_Kuadran} ${pembeda}` : m.Nama_Kuadran,
        };
    });

    return hasil;
});

const BARIS_HAV = [
    { label: 'Tinggi', kotakList: [7, 3, 1] },
    { label: 'Sedang', kotakList: [8, 4, 2] },
    { label: 'Rendah', kotakList: [9, 6, 5] },
];

const hoveredBox = ref(null);
const clickedBox = ref(null);

const activeBoxNumber = computed(() => Number(kuadran.value?.Nomor_Box) || 1);
const currentInspectedBoxNum = computed(() => clickedBox.value || hoveredBox.value || activeBoxNumber.value);
const currentInspectedBox = computed(() => KAMUS_HAV.value[currentInspectedBoxNum.value] || KAMUS_HAV.value[1]);

function resetPreview() {
    clickedBox.value = null;
    hoveredBox.value = null;
}

const tingkatClass = (t) => {
    const k = String(t || '').toUpperCase();
    if (k === 'TINGGI') return 'high';
    if (k === 'SEDANG') return 'med';
    if (k === 'RENDAH') return 'low';
    return 'default';
};

const angka = (v, d = 2) => (v === null || v === undefined || v === '' ? '–' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d }));
const tanda = (v) => (v === null || v === undefined ? '–' : `${Number(v) > 0 ? '+' : Number(v) < 0 ? '−' : '±'}${angka(Math.abs(Number(v)))}`);
const labelGrade = (k) => (k ? String(k).replace(/_PLUS/g, '+').replace(/_MINUS/g, '−') : '–');
const sumbangan = (nilai, bobot) => (nilai === null || nilai === undefined || bobot === null || bobot === undefined ? null : (Number(nilai) * Number(bobot)) / 100);
const tingkat = (t) => ({ TINGGI: 'Tinggi', SEDANG: 'Sedang', RENDAH: 'Rendah' }[String(t || '').toUpperCase()] || t || '–');
const tanggal = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '–');
const waktu = (v) => (v ? new Date(String(v).replace(' ', 'T')).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '–');
const kenaCap = computed(() => p.value.Nilai_KPI_Sebelum_Cap !== null && p.value.Nilai_KPI_Sebelum_Cap !== undefined
    && Number(p.value.Nilai_KPI_Sebelum_Cap) > Number(p.value.Nilai_KPI));

const arahTrenFinal = computed(() => {
    if (!tren.value || p.value.Final_Score == null || tren.value.final == null) return null;
    const diff = Number(p.value.Final_Score) - Number(tren.value.final);
    if (diff > 0) return { status: 'naik', label: 'Performa Meningkat', icon: 'arrow-up-right', variant: 'success' };
    if (diff < 0) return { status: 'turun', label: 'Performa Menurun', icon: 'arrow-down-right', variant: 'danger' };
    return { status: 'tetap', label: 'Performa Stabil', icon: 'dash', variant: 'neutral' };
});

const barisTren = computed(() => {
    const t = tren.value;
    if (!t) return [];
    const selisih = (a, b) => (a === null || a === undefined || b === null || b === undefined ? null : Math.round((Number(a) - Number(b)) * 100) / 100);
    return [
        { kunci: 'final', label: 'Final Score', ikon: 'award-fill', sentimen: 'brand', sebelum: angka(t.final), sekarang: angka(p.value.Final_Score), selisih: selisih(p.value.Final_Score, t.final) },
        ...(props.modeVerifikasi ? [] : [{ kunci: 'grade', label: 'Grade Akhir', ikon: 'award', sentimen: 'purple', sebelum: labelGrade(t.grade), sekarang: labelGrade(p.value.Kode_Grade_Final || p.value.Kode_Grade_Sistem), selisih: null }]),
        { kunci: 'kpi', label: 'Kinerja (KPI)', ikon: 'speedometer2', sentimen: 'teal', sebelum: angka(t.kpi), sekarang: angka(p.value.Nilai_KPI), selisih: selisih(p.value.Nilai_KPI, t.kpi) },
        { kunci: 'kompetensi', label: 'Kompetensi', ikon: 'diagram-3', sentimen: 'purple', sebelum: angka(t.kompetensi), sekarang: angka(p.value.Nilai_Kompetensi), selisih: selisih(p.value.Nilai_Kompetensi, t.kompetensi) },
    ];
});
</script>

<style scoped>
.pa-wh {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.pa-wh__kosong { margin: 0; font-size: 0.8rem; color: #94a3b8; font-style: italic; }
.pa-wh__redup { color: #64748b; font-size: 0.72rem; }

/* 1. Komposisi Nilai Akhir */
.pa-wh__komposisi {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.pa-wh__pillars {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.5rem;
}

.pa-wh__pillar {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.65rem 0.75rem;
    border-radius: var(--hcis-radius-md, 10px);
}

.pa-wh__pillar--kpi {
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.8), rgba(248, 250, 252, 0.95));
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.pa-wh__pillar--kompetensi {
    background: linear-gradient(135deg, rgba(250, 245, 255, 0.8), rgba(248, 250, 252, 0.95));
    border: 1px solid rgba(168, 85, 247, 0.2);
}

.pa-wh__pillar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
}

.pa-wh__pillar-title {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.pa-wh__pillar-title strong {
    font-size: 0.8rem;
    color: var(--hcis-text-strong, #0f172a);
    font-weight: 750;
}

.pa-wh__pillar-weight {
    font-size: 0.65rem;
    font-weight: 750;
    padding: 0.08rem 0.4rem;
    border-radius: 9999px;
}

.pa-wh__pillar-weight--kpi {
    background: #e0e7ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
}

.pa-wh__pillar-weight--kompetensi {
    background: #f3e8ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
}

.pa-wh__pillar-body {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.4rem;
    padding-top: 0.15rem;
}

.pa-wh__pillar-main {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.pa-wh__pillar-val {
    font-size: 1.15rem;
    font-weight: 850;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

.pa-wh__pillar-calc {
    font-size: 0.68rem;
    color: #64748b;
    font-weight: 600;
}

.pa-wh__pillar-contrib {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.pa-wh__pillar-contrib-lbl {
    font-size: 0.58rem;
    text-transform: uppercase;
    font-weight: 700;
    color: #64748b;
}

.pa-wh__pillar-contrib-num {
    font-size: 0.95rem;
    font-weight: 850;
    font-variant-numeric: tabular-nums;
}

.text-brand { color: #4f46e5; }
.text-purple { color: #9333ea; }

.pa-wh__plus-sign {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 9999px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #64748b;
    font-weight: 800;
    flex-shrink: 0;
}

/* Weight Proportion Bar */
.pa-wh__proportion {
    display: flex;
    flex-direction: column;
}

.pa-wh__proportion-bar {
    display: flex;
    height: 1.25rem;
    border-radius: 9999px;
    overflow: hidden;
    background: #f1f5f9;
    border: 1px solid rgba(226, 232, 240, 0.9);
    padding: 2px;
    gap: 2px;
}

.pa-wh__proportion-segment {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    border-radius: 9999px;
    font-size: 0.64rem;
    font-weight: 750;
    color: #ffffff;
    transition: width 200ms ease;
    min-width: 2.2rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 0 0.35rem;
}

.pa-wh__proportion-segment.is-kpi { background: linear-gradient(90deg, #4f46e5, #6366f1); }
.pa-wh__proportion-segment.is-kompetensi { background: linear-gradient(90deg, #9333ea, #a855f7); }

/* Final Score Box */
.pa-wh__final-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: linear-gradient(135deg, rgba(236, 253, 245, 0.95), rgba(240, 253, 250, 0.88));
    border: 1px solid rgba(16, 185, 129, 0.3);
    box-shadow: 0 1px 3px rgba(16, 185, 129, 0.06);
}

.pa-wh__final-box-left {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    min-width: 0;
}

.pa-wh__final-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 9999px;
    background: #d1fae5;
    color: #047857;
    flex-shrink: 0;
}

.pa-wh__final-meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.pa-wh__final-label {
    font-size: 0.7rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #065f46;
}

.pa-wh__final-sum {
    font-size: 0.72rem;
    color: #047857;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

.pa-wh__final-box-right {
    display: flex;
    align-items: baseline;
    flex-shrink: 0;
}

.pa-wh__final-num {
    font-size: 1.35rem;
    font-weight: 850;
    color: #047857;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}

/* Contextual Details Chips */
.pa-wh__audit-chips {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.pa-wh__chip {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.55rem;
    border-radius: 6px;
    font-size: 0.72rem;
    color: #334155;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.pa-wh__chip.is-muted {
    color: #64748b;
    font-size: 0.68rem;
    background: transparent;
    border-color: transparent;
    padding: 0.05rem 0.15rem;
}

.pa-wh__chip.is-warning {
    background: #fffbeb;
    border-color: #fde68a;
    color: #b45309;
    font-weight: 600;
}

.pa-wh__chip.is-info {
    background: #f0f9ff;
    border-color: #bae6fd;
    color: #0369a1;
    font-weight: 600;
}

.pa-wh__chip.is-grade {
    background: #fefce8;
    border-color: #fef08a;
    color: #854d0e;
}

.pa-wh__ubah { padding: 0 0.35rem; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 0.62rem; font-style: normal; font-weight: 700; margin-left: 0.2rem; }

/* 2. 9-Box HAV Matrix & Showcase */
.pa-wh__hav-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-wh__hav-matrix {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.85));
    border: 1px solid #e2e8f0;
    border-radius: var(--hcis-radius-md, 10px);
    padding: 0.45rem 0.5rem;
}

.pa-wh__hav-col-headers {
    display: grid;
    grid-template-columns: 52px repeat(3, minmax(0, 1fr));
    gap: 0.25rem;
    align-items: center;
}

.pa-wh__hav-axis-tag {
    font-size: 0.56rem;
    font-weight: 800;
    color: #64748b;
    padding-left: 0.15rem;
}

.pa-wh__hav-col-tag {
    font-size: 0.58rem;
    font-weight: 750;
    color: #475569;
    text-align: center;
    background: rgba(255, 255, 255, 0.7);
    padding: 0.12rem 0.2rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.pa-wh__hav-rows {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-wh__hav-row {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr);
    gap: 0.25rem;
    align-items: stretch;
}

.pa-wh__hav-row-tag {
    font-size: 0.58rem;
    font-weight: 750;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.pa-wh__hav-row-cells {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.25rem;
}

.pa-wh__hav-cell {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.28rem 0.35rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    font-family: inherit;
    text-align: left;
    cursor: pointer;
    transition: all 130ms ease;
    min-height: 2.6rem;
}

.pa-wh__hav-cell:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

.pa-wh__hav-cell.is-preview {
    border-color: #6366f1 !important;
}

.pa-wh__hav-cell.is-target {
    border-color: #4338ca !important;
    box-shadow: 0 0 0 2px #4f46e5, 0 2px 6px rgba(79, 70, 229, 0.2) !important;
    background: #eef2ff !important;
}

.pa-wh__hav-cell-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.15rem;
}

.pa-wh__hav-kotak {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.1rem;
    height: 1.1rem;
    border-radius: 4px;
    background: #f1f5f9;
    font-size: 0.6rem;
    font-weight: 800;
    color: #475569;
}

.pa-wh__hav-kotak.is-aktif {
    background: #4338ca;
    color: #ffffff !important;
}

.pa-wh__hav-cell-label {
    font-size: 0.62rem;
    font-weight: 750;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.15;
}

/* Showcase details card */
.pa-wh__hav-showcase {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    padding: 0.6rem 0.75rem;
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.pa-wh__hav-showcase-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-wh__hav-jewel {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 9999px;
    background: #f1f5f9;
    color: #475569;
    flex-shrink: 0;
}

.pa-wh__hav-jewel.is-success { background: #d1fae5; color: #047857; }
.pa-wh__hav-jewel.is-purple { background: #f3e8ff; color: #7e22ce; }
.pa-wh__hav-jewel.is-brand { background: #e0e7ff; color: #4338ca; }
.pa-wh__hav-jewel.is-teal { background: #ccfbf1; color: #0f766e; }
.pa-wh__hav-jewel.is-warning { background: #fef3c7; color: #b45309; }
.pa-wh__hav-jewel.is-info { background: #e0f2fe; color: #0369a1; }
.pa-wh__hav-jewel.is-danger { background: #fee2e2; color: #b91c1c; }

.pa-wh__hav-showcase-title {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
    flex: 1 1 auto;
}

.pa-wh__hav-showcase-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.pa-wh__hav-box-badge {
    font-size: 0.58rem;
    font-weight: 800;
    text-transform: uppercase;
    padding: 0.05rem 0.35rem;
    border-radius: 9999px;
    background: #f1f5f9;
    color: #475569;
}

.pa-wh__hav-box-badge.is-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.pa-wh__hav-box-badge.is-purple { background: #faf5ff; color: #6b21a8; border: 1px solid #e9d5ff; }
.pa-wh__hav-box-badge.is-brand { background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe; }
.pa-wh__hav-box-badge.is-teal { background: #f0fdfa; color: #115e59; border: 1px solid #99f6e4; }
.pa-wh__hav-box-badge.is-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.pa-wh__hav-box-badge.is-info { background: #f0f9ff; color: #075985; border: 1px solid #bae6fd; }
.pa-wh__hav-box-badge.is-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

.pa-wh__hav-target-badge {
    font-size: 0.58rem;
    font-weight: 750;
    padding: 0.05rem 0.35rem;
    border-radius: 9999px;
    background: #e0e7ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
}

.pa-wh__hav-reset-btn {
    border: none;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.58rem;
    font-weight: 700;
    padding: 0.06rem 0.35rem;
    border-radius: 9999px;
    cursor: pointer;
}

.pa-wh__hav-name {
    font-size: 0.88rem;
    font-weight: 800;
    color: #0f172a;
}

.pa-wh__hav-dimensions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-wh__hav-dim-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.45rem;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 0.68rem;
    color: #334155;
}

.pa-wh__hav-desc {
    margin: 0;
    font-size: 0.7rem;
    color: #64748b;
    line-height: 1.35;
    padding-top: 0.25rem;
    border-top: 1px dashed #e2e8f0;
}

/* 3. Posisi di grup kurva */
.pa-wh__kurva-atas { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; }
.pa-wh__kurva-atas > div { display: flex; flex-direction: column; gap: 0.1rem; padding: 0.5rem 0.6rem; border-radius: 10px; background: #fffbeb; }
.pa-wh__kurva-atas small { font-size: 0.64rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #92400e; }
.pa-wh__kurva-atas strong { font-size: 1rem; color: #0f172a; font-variant-numeric: tabular-nums; }
.pa-wh__kurva-atas em { font-size: 0.72rem; font-style: normal; font-weight: 600; color: #64748b; }
.pa-wh__kurva-teks { margin: 0.6rem 0 0.3rem; font-size: 0.82rem; color: #334155; }
.pa-wh__peringatan { display: flex; align-items: center; gap: 0.3rem; margin: 0.35rem 0 0; font-size: 0.72rem; color: #b45309; }

/* 4. Tren vs siklus sebelumnya */
.pa-wh__tren {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.pa-wh__tren-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0.65rem;
    border-radius: var(--hcis-radius-sm, 6px);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.pa-wh__tren-strip-info {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    color: #475569;
}

.pa-wh__tren-strip-info b {
    color: #0f172a;
    font-weight: 700;
}

.pa-wh__tren-list {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pa-wh__tren-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    padding: 0.55rem 0.75rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    transition: all 140ms ease;
}

.pa-wh__tren-item:hover {
    border-color: #cbd5e1;
    background: #fafafa;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.04);
}

.pa-wh__tren-item-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 110px;
}

.pa-wh__tren-item-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 6px;
    flex-shrink: 0;
}

.pa-wh__tren-item-icon.is-brand {
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
}

.pa-wh__tren-item-icon.is-purple {
    background: rgba(168, 85, 247, 0.1);
    color: #9333ea;
}

.pa-wh__tren-item-icon.is-teal {
    background: rgba(20, 184, 166, 0.1);
    color: #0d9488;
}

.pa-wh__tren-item-label {
    font-size: 0.78rem;
    font-weight: 750;
    color: var(--hcis-text-strong, #0f172a);
    white-space: nowrap;
}

.pa-wh__tren-item-values {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pa-wh__tren-val-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 48px;
}

.pa-wh__tren-val-tag {
    font-size: 0.55rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
}

.pa-wh__tren-val-num {
    font-size: 0.82rem;
    font-variant-numeric: tabular-nums;
    color: #64748b;
}

.pa-wh__tren-val-box.is-curr .pa-wh__tren-val-num {
    color: #0f172a;
    font-weight: 800;
}

.pa-wh__tren-arrow {
    color: #94a3b8;
    display: flex;
    align-items: center;
}

.pa-wh__tren-delta-wrap {
    min-width: 65px;
    display: flex;
    justify-content: flex-end;
}

.pa-wh__tren-delta {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 750;
    font-variant-numeric: tabular-nums;
    font-family: var(--hcis-font-mono, ui-monospace, monospace);
}

.pa-wh__tren-delta em {
    font-style: normal;
}

.pa-wh__tren-delta.is-naik {
    background: rgba(16, 185, 129, 0.12);
    color: #047857;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

.pa-wh__tren-delta.is-turun {
    background: rgba(239, 68, 68, 0.12);
    color: #b91c1c;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.pa-wh__tren-delta.is-tetap {
    background: rgba(100, 116, 139, 0.1);
    color: #475569;
    border: 1px solid rgba(100, 116, 139, 0.2);
}

.pa-wh__tren-delta.is-grade {
    background: #f1f5f9;
    color: #64748b;
    font-family: inherit;
    font-size: 0.65rem;
}

.pa-wh__tren-delta.is-grade.is-changed {
    background: rgba(168, 85, 247, 0.1);
    color: #7e22ce;
    border: 1px solid rgba(168, 85, 247, 0.25);
}

/* Empty State */
.pa-wh__tren-empty {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem;
    border-radius: var(--hcis-radius-md, 8px);
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
}

.pa-wh__tren-empty-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 50%;
    background: #e2e8f0;
    color: #64748b;
    flex-shrink: 0;
}

.pa-wh__tren-empty-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.pa-wh__tren-empty-text strong {
    font-size: 0.82rem;
    color: #334155;
    font-weight: 700;
}

.pa-wh__tren-empty-text span {
    font-size: 0.72rem;
    color: #64748b;
    line-height: 1.35;
}

@media (max-width: 900px) {
    .pa-wh { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 640px) {
    .pa-wh__pillars {
        grid-template-columns: 1fr;
        gap: 0.45rem;
    }
    .pa-wh__plus-sign {
        margin: 0 auto;
        transform: rotate(90deg);
        width: 1.35rem;
        height: 1.35rem;
    }
    .pa-wh__final-box {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.45rem;
    }
    .pa-wh__final-box-right {
        width: 100%;
        justify-content: flex-end;
        border-top: 1px dashed rgba(16, 185, 129, 0.3);
        padding-top: 0.35rem;
    }
    .pa-wh__hav-col-headers {
        grid-template-columns: 46px repeat(3, minmax(0, 1fr));
        gap: 0.2rem;
    }
    .pa-wh__hav-row {
        grid-template-columns: 46px minmax(0, 1fr);
        gap: 0.2rem;
    }
    .pa-wh__hav-row-cells {
        gap: 0.2rem;
    }
    .pa-wh__hav-cell {
        padding: 0.2rem 0.25rem;
        min-height: 2.4rem;
    }
    .pa-wh__hav-cell-label {
        font-size: 0.56rem;
    }
    .pa-wh__tren-item {
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
        padding: 0.55rem 0.65rem;
    }
    .pa-wh__tren-item-values {
        justify-content: space-between;
        width: 100%;
        border-top: 1px dashed #f1f5f9;
        padding-top: 0.35rem;
    }
}
</style>

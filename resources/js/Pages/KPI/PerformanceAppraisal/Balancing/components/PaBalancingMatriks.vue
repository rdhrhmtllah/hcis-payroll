<template>
    <div ref="akar" class="pa-bal-matriks">
        <!-- ═══════════════════════════════════════════════════════════════
             1. EXECUTIVE MATRIX TOOLBAR & SUMMARY RIBBON
             ═══════════════════════════════════════════════════════════════ -->
        <div class="pa-bal-mx-toolbar">
            <div class="pa-bal-mx-toolbar__left">
                <HcisBadge
                    :label="`${peserta.length} Peserta`"
                    variant="neutral"
                    appearance="glass"
                    size="sm"
                />
                <HcisBadge
                    :label="`${jumlahDiubah} Disesuaikan`"
                    :variant="jumlahDiubah > 0 ? 'brand' : 'neutral'"
                    appearance="glass"
                    size="sm"
                    dot
                />
                <button
                    v-if="kurangAlasan"
                    type="button"
                    class="pa-bal-mx-alasan-shortcut"
                    title="Lompat ke peserta pertama yang belum ada alasannya"
                    @click="keAlasanPertama"
                >
                    <HcisIcon name="exclamation-triangle-fill" size="2xs" />
                    <span><strong>{{ kurangAlasan }}</strong> perlu alasan</span>
                </button>
                <HcisBadge
                    v-if="jumlahBelum"
                    :label="`${jumlahBelum} Belum Ditinjau`"
                    variant="warning"
                    appearance="subtle"
                    size="sm"
                />
            </div>

            <div class="pa-bal-mx-toolbar__right">
                <!-- Visual Indicator Legend -->
                <div class="pa-bal-mx-legend" aria-label="Legenda warna matriks">
                    <span class="pa-bal-mx-legend-item">
                        <i class="legend-swatch is-ubah" /> Nilai Disesuaikan
                    </span>
                    <span class="pa-bal-mx-legend-item pa-bal-mx-legend-heat">
                        <i class="heat-dot is-rendah" />
                        <i class="heat-dot is-tengah" />
                        <i class="heat-dot is-tinggi" />
                        <span>Skala Before</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             2. LEVEL FILTER CONTROLS & SHORTCUT HINTS
             ═══════════════════════════════════════════════════════════════ -->
        <div class="pa-bal-mx-controls-strip">
            <div v-if="semuaGrup.length > 1" class="pa-bal-mx-level-group" role="group" aria-label="Saring level jabatan">
                <button
                    type="button"
                    class="pa-bal-level-btn"
                    :class="{ 'is-active': !levelAktif }"
                    @click="levelAktif = ''"
                >
                    <span>Semua Level</span>
                    <span class="pa-bal-level-cnt">{{ peserta.length }}</span>
                </button>
                <button
                    v-for="g in semuaGrup"
                    :key="g.level"
                    type="button"
                    class="pa-bal-level-btn"
                    :class="{ 'is-active': levelAktif === g.level }"
                    @click="levelAktif = g.level"
                >
                    <span>{{ g.level }}</span>
                    <span class="pa-bal-level-cnt">{{ g.peserta.length }}</span>
                </button>
            </div>

            <div v-if="bisaUbah" class="pa-bal-mx-shortcut-hint">
                <HcisIcon name="info-circle" size="2xs" class="text-brand" />
                <span>Ketik di <strong>After</strong>. <kbd>Enter</kbd> turun &bull; <kbd>Tab</kbd> kanan &bull; kosongkan untuk batal.</span>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             3. HIGH-DENSITY BALANCING MATRIX TABLE (SPREADSHEET COCKPIT)
             ═══════════════════════════════════════════════════════════════ -->
        <div class="pa-bal-mx-scroll">
            <table class="pa-bal-mx-table">
                <thead>
                    <!-- Row 1: Level Groups Header -->
                    <tr class="tr-level">
                        <th class="th-param th-corner" rowspan="3">
                            <div class="pa-bal-corner-card">
                                <div class="pa-bal-corner-icon-circle">
                                    <HcisIcon name="people-fill" size="sm" />
                                </div>
                                <div class="pa-bal-corner-text">
                                    <span class="pa-bal-corner-kicker">Sesi Balancing</span>
                                    <strong class="pa-bal-corner-title">Peserta Evaluasi</strong>
                                </div>
                                <div class="pa-bal-corner-capsule">
                                    <span class="pa-bal-corner-dot" />
                                    <span>{{ pesertaTampil.length }} Peserta Aktif &rarr;</span>
                                </div>
                                <span class="pa-bal-corner-meta">
                                    {{ barisKompetensi.length }} Kompetensi<template v-if="barisKualitatif.length"> &bull; {{ barisKualitatif.length }} Kualitatif</template>
                                </span>
                            </div>
                        </th>
                        <th
                            v-for="g in grup"
                            :key="g.level"
                            :colspan="g.peserta.length * 3"
                            class="th-level-group"
                        >
                            {{ g.level }}
                        </th>
                    </tr>

                    <!-- Row 2: Employee Identity Cards Header (Clean Executive Card Style) -->
                    <tr class="tr-orang">
                        <template v-for="g in grup" :key="`o-${g.level}`">
                            <th
                                v-for="p in g.peserta"
                                :key="p.participant_id"
                                colspan="3"
                                class="th-orang-card"
                                :class="{ 'is-active-col': kolomAktif === p.participant_id }"
                            >
                                <div class="pa-bal-orang-card">
                                    <div class="pa-bal-orang__top">
                                        <div class="pa-bal-orang__id">
                                            <HcisAvatar :name="p.nama" size="xs" shape="circle" />
                                            <div class="pa-bal-orang__text">
                                                <button
                                                    type="button"
                                                    class="pa-bal-orang__name-btn"
                                                    :title="p.nama"
                                                    @click="kolomAktif = p.participant_id"
                                                >
                                                    {{ p.nama }}
                                                </button>
                                                <span class="pa-bal-orang__nik">{{ p.kode_karyawan }}</span>
                                            </div>
                                        </div>
                                        <HcisBadge
                                            :label="LABEL_TINJAU[statusTampil(p)] || statusTampil(p)"
                                            :variant="statusTampil(p) === 'DIUBAH' ? 'brand' : (statusTampil(p) === 'TANPA_PERUBAHAN' ? 'success' : 'neutral')"
                                            appearance="subtle"
                                            size="2xs"
                                            dot
                                        />
                                    </div>

                                    <!-- Final Score & Grade Shift -->
                                    <div class="pa-bal-orang__scores">
                                        <div class="pa-bal-final-score-box">
                                            <span class="pa-bal-score-label">FINAL</span>
                                            <div class="pa-bal-score-nums">
                                                <template v-if="berubah(p.sebelum?.final, hitungFinalSesudah(p))">
                                                    <s class="pa-bal-score-crossed">{{ formatNilai(p.sebelum?.final) }}</s>
                                                    <strong class="pa-bal-score-current">{{ formatNilai(hitungFinalSesudah(p)) }}</strong>
                                                    <span class="pa-bal-delta-chip" :class="naikTurun(p.sebelum?.final, hitungFinalSesudah(p))">
                                                        {{ hitungDelta(p.sebelum?.final, hitungFinalSesudah(p)) }}
                                                    </span>
                                                </template>
                                                <strong v-else class="pa-bal-score-current">{{ formatNilai(p.sebelum?.final) }}</strong>
                                            </div>
                                            <HcisBadge
                                                :label="labelGrade(berubah(p.sebelum?.final, hitungFinalSesudah(p)) ? (p.sesudah?.grade || p.sebelum?.grade) : p.sebelum?.grade)"
                                                variant="brand"
                                                appearance="solid"
                                                size="2xs"
                                            />
                                        </div>

                                        <!-- Pillars Info Breakdown -->
                                        <div class="pa-bal-pillars-row">
                                            <span class="pa-bal-pillar-stat">
                                                <span class="pa-bal-pillar-tag">Komp</span>
                                                <template v-if="berubah(p.sebelum?.nilai_kompetensi, hitungKompSesudah(p))">
                                                    <s class="pa-bal-pillar-crossed">{{ formatNilai(p.sebelum?.nilai_kompetensi) }}</s>
                                                    <b :class="naikTurun(p.sebelum?.nilai_kompetensi, hitungKompSesudah(p))">
                                                        {{ formatNilai(hitungKompSesudah(p)) }}
                                                    </b>
                                                </template>
                                                <b v-else>{{ formatNilai(p.sebelum?.nilai_kompetensi) }}</b>
                                                <small>{{ formatPersen(p.sebelum?.bobot_kompetensi) }}</small>
                                            </span>

                                            <span class="pa-bal-pillar-stat">
                                                <span class="pa-bal-pillar-tag">KPI</span>
                                                <b>{{ formatNilai(p.sebelum?.nilai_kpi) }}</b>
                                                <small>{{ formatPersen(p.sebelum?.bobot_kpi) }}</small>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Alasan per orang: satu alasan untuk seluruh perubahan orang ini -->
                                    <button
                                        v-if="adaPerubahan(p) || p.alasan"
                                        type="button"
                                        class="pa-bal-orang-alasan"
                                        :class="{ 'is-wajib': butuhAlasanPeserta(p), 'has-alasan': Boolean(String(p.alasan || '').trim()) }"
                                        :data-alasan="p.participant_id"
                                        :title="p.alasan ? `Alasan: ${p.alasan}` : 'Tulis alasan perubahan untuk orang ini'"
                                        @click="bukaAlasan($event, p)"
                                    >
                                        <HcisIcon :name="butuhAlasanPeserta(p) ? 'chat-left-dots' : 'chat-left-text-fill'" size="2xs" />
                                        <span>{{ butuhAlasanPeserta(p) ? 'Tulis alasan perubahan' : p.alasan }}</span>
                                    </button>

                                    <!-- Quick Approval Action -->
                                    <button
                                        v-if="bisaUbah && statusTampil(p) === 'BELUM'"
                                        type="button"
                                        class="pa-bal-setujui-btn"
                                        title="Setujui nilai orang ini tanpa perubahan"
                                        @click="setujui(p)"
                                    >
                                        <HcisIcon name="check2" size="2xs" />
                                        <span>Setujui Tanpa Perubahan</span>
                                    </button>
                                </div>
                            </th>
                        </template>
                    </tr>

                    <!-- Row 3: Pillar Groups Header (Pemisah Jelas Pilar Kompetensi vs KPI) -->
                    <tr class="tr-pilar">
                        <template v-for="g in grup" :key="`p-${g.level}`">
                            <template v-for="p in g.peserta" :key="p.participant_id">
                                <th
                                    colspan="2"
                                    class="th-pilar th-pilar-komp"
                                    :class="{ 'is-active-col': kolomAktif === p.participant_id }"
                                    title="Pilar Penilaian Kompetensi"
                                >
                                    <div class="pa-bal-pilar-box">
                                        <span class="pa-bal-pilar-title">Kompetensi</span>
                                        <span
                                            v-if="p.sebelum?.bobot_kompetensi"
                                            class="pa-bal-pilar-weight"
                                            title="Bobot Pilar Kompetensi"
                                        >
                                            {{ formatPersen(p.sebelum?.bobot_kompetensi) }}
                                        </span>
                                    </div>
                                </th>
                                <th
                                    class="th-pilar th-pilar-kpi"
                                    :class="{ 'is-active-col': kolomAktif === p.participant_id }"
                                    title="Pilar Penilaian KPI"
                                >
                                    <div class="pa-bal-pilar-box">
                                        <span class="pa-bal-pilar-title">KPI</span>
                                        <span
                                            v-if="p.sebelum?.bobot_kpi"
                                            class="pa-bal-pilar-weight"
                                            title="Bobot Pilar KPI"
                                        >
                                            {{ formatPersen(p.sebelum?.bobot_kpi) }}
                                        </span>
                                    </div>
                                </th>
                            </template>
                        </template>
                    </tr>

                    <!-- Row 4: Sub-column Labels (Before, After, Skor) -->
                    <tr class="tr-kolom">
                        <th class="th-param th-param-title">
                            <div class="pa-bal-param-corner-label">
                                <HcisIcon name="sliders" size="2xs" />
                                <span>Parameter Penilaian</span>
                            </div>
                        </th>
                        <template v-for="g in grup" :key="`k-${g.level}`">
                            <template v-for="p in g.peserta" :key="p.participant_id">
                                <th
                                    class="th-sub th-before"
                                    :class="{ 'is-active-col': kolomAktif === p.participant_id }"
                                    title="Nilai Kompetensi Sebelum Balancing"
                                >
                                    Before
                                </th>
                                <th
                                    class="th-sub th-after"
                                    :class="{ 'is-active-col': kolomAktif === p.participant_id }"
                                    title="Nilai Kompetensi Sesudah Balancing"
                                >
                                    After
                                </th>
                                <th
                                    class="th-sub th-kpi"
                                    :class="{ 'is-active-col': kolomAktif === p.participant_id }"
                                    title="Skor KPI (Nilai Tetap)"
                                >
                                    Skor
                                </th>
                            </template>
                        </template>
                    </tr>
                </thead>

                <tbody>
                    <!-- SECTION 1: KOMPETENSI -->
                    <tr v-if="barisKompetensi.length" class="tr-section">
                        <th class="th-param th-section-param">
                            <div class="pa-bal-section-tag">
                                <HcisIcon name="sliders" size="2xs" />
                                <span>Kompetensi</span>
                                <span class="pa-bal-section-count">{{ barisKompetensi.length }}</span>
                            </div>
                        </th>
                        <td :colspan="jumlahKolomOrang" class="td-section-span" />
                    </tr>

                    <tr v-for="(r, i) in barisKompetensi" :key="`c-${r.id}`" class="tr-body-row">
                        <th class="th-param th-body-param" scope="row" :title="r.nama">
                            <div class="pa-bal-param-row">
                                <span class="pa-bal-param-indicator" />
                                <span class="pa-bal-param-label">{{ r.nama }}</span>
                            </div>
                        </th>
                        <template v-for="(p, kol) in pesertaTampil" :key="p.participant_id">
                            <template v-if="kompetensiDari(p, r.id)">
                                <!-- Before Value Cell (Refined Heatmap Tint) -->
                                <td
                                    class="td-before"
                                    :style="warnaPanas(kompetensiDari(p, r.id).sebelum)"
                                    :class="{ 'is-active-col': kolomAktif === p.participant_id }"
                                >
                                    {{ formatAngka(kompetensiDari(p, r.id).sebelum) }}
                                </td>

                                <!-- After Value Cell (Editable Input + Diff + Reason Trigger) -->
                                <td
                                    class="td-after"
                                    :class="[kelasAfter(kompetensiDari(p, r.id)), { 'is-active-col': kolomAktif === p.participant_id }]"
                                    :data-sel="kunciSel(p, kompetensiDari(p, r.id))"
                                >
                                    <div class="pa-bal-cell-editor">
                                        <input
                                            v-if="bisaUbah"
                                            type="number"
                                            inputmode="decimal"
                                            min="0"
                                            max="100"
                                            step="any"
                                            class="pa-bal-cell-input"
                                            :data-baris="i"
                                            :data-kolom="kol"
                                            :data-uji="`after-${r.id}`"
                                            :value="nilaiTampilAfter(p, kompetensiDari(p, r.id))"
                                            :aria-label="`After ${r.nama} ${p.nama}`"
                                            @focus="mulaiIsi($event, p, kompetensiDari(p, r.id))"
                                            @blur="selesaiIsi"
                                            @change="ubahNilai(kompetensiDari(p, r.id), $event.target.value)"
                                            @keydown.enter.prevent="pindahBaris($event, i, kol)"
                                        >
                                        <span v-else class="pa-bal-cell-static">
                                            {{ berubah(kompetensiDari(p, r.id).sebelum, kompetensiDari(p, r.id).sesudah) ? formatAngka(kompetensiDari(p, r.id).sesudah) : '–' }}
                                        </span>

                                        <!-- Selisih After − Before (alasan dicatat per orang di kepala kolom) -->
                                        <span
                                            v-if="berubah(kompetensiDari(p, r.id).sebelum, kompetensiDari(p, r.id).sesudah) && !diLuarRentang(kompetensiDari(p, r.id))"
                                            class="pa-bal-delta-btn"
                                            :class="naikTurun(kompetensiDari(p, r.id).sebelum, kompetensiDari(p, r.id).sesudah)"
                                        >
                                            <span class="pa-bal-delta-val">{{ selisihRingkas(kompetensiDari(p, r.id)) }}</span>
                                        </span>
                                    </div>
                                </td>
                            </template>

                            <!-- Not Applicable / Missing Competency -->
                            <td v-else class="td-na" colspan="2" title="Kompetensi ini tidak dinilai untuk karyawan ini">
                                <span class="pa-bal-na-text"><HcisIcon name="slash-circle" size="2xs" /> Tidak dinilai</span>
                            </td>

                            <!-- Merged KPI Cell for this employee -->
                            <td
                                v-if="i === 0"
                                class="td-kpi-merged"
                                :rowspan="barisKompetensi.length"
                                :class="{ 'is-active-col': kolomAktif === p.participant_id }"
                            >
                                <span class="pa-bal-kpi-val">{{ formatAngka(p.sebelum?.nilai_kpi) }}</span>
                            </td>
                        </template>
                    </tr>

                    <!-- SECTION 2: KUALITATIF -->
                    <tr v-if="barisKualitatif.length" class="tr-section">
                        <th class="th-param th-section-param">
                            <div class="pa-bal-section-tag">
                                <HcisIcon name="card-text" size="2xs" />
                                <span>Kualitatif</span>
                                <span class="pa-bal-section-count">{{ barisKualitatif.length }}</span>
                            </div>
                        </th>
                        <td :colspan="jumlahKolomOrang" class="td-section-span" />
                    </tr>

                    <tr v-for="(r, j) in barisKualitatif" :key="`q-${r.id}`" class="tr-body-row tr-kualitatif">
                        <th class="th-param th-body-param" scope="row">{{ r.nama }}</th>
                        <template v-for="p in pesertaTampil" :key="p.participant_id">
                            <template v-if="kualitatifDari(p, r.id)">
                                <!-- Before Qualitative Text -->
                                <td
                                    class="td-teks td-teks-before"
                                    :class="{ 'is-active-col': kolomAktif === p.participant_id }"
                                    :title="teksPolos(kualitatifDari(p, r.id).sebelum) || 'Belum dirangkum admin'"
                                    @click="bukaKualitatif(p, r.id)"
                                >
                                    <div class="pa-bal-teks-cell-wrap">
                                        <p v-if="teksPolos(kualitatifDari(p, r.id).sebelum)" class="pa-bal-teks-snippet">
                                            {{ teksPolos(kualitatifDari(p, r.id).sebelum) }}
                                        </p>
                                        <span v-else class="pa-bal-teks-empty">Draf kosong</span>
                                    </div>
                                </td>

                                <!-- After Qualitative Text + Dialog Trigger -->
                                <td
                                    class="td-teks td-teks-after"
                                    :class="{ 'is-ubah': teksBerubah(kualitatifDari(p, r.id)), 'is-active-col': kolomAktif === p.participant_id }"
                                    :title="teksBerubah(kualitatifDari(p, r.id)) ? teksPolos(kualitatifDari(p, r.id).sesudah) : 'Sama dengan Before'"
                                    @click="bukaKualitatif(p, r.id)"
                                >
                                    <div class="pa-bal-teks-cell-wrap">
                                        <p v-if="teksBerubah(kualitatifDari(p, r.id))" class="pa-bal-teks-snippet is-ubah">
                                            {{ teksPolos(kualitatifDari(p, r.id).sesudah) || '(dikosongkan)' }}
                                        </p>
                                        <span v-else class="pa-bal-teks-empty">Sama Before</span>
                                        <button
                                            type="button"
                                            class="pa-bal-teks-micro-btn"
                                            :aria-label="`Buka ${r.nama} ${p.nama}`"
                                            @click.stop="bukaKualitatif(p, r.id)"
                                        >
                                            <HcisIcon name="box-arrow-up-right" size="2xs" />
                                        </button>
                                    </div>
                                </td>
                            </template>

                            <td v-else class="td-na" colspan="2" title="Karyawan ini tidak memiliki isian kualitatif ini">
                                <span class="pa-bal-na-text"><HcisIcon name="slash-circle" size="2xs" /> Tidak ada isian</span>
                            </td>

                            <td v-if="j === 0" class="td-kpi-merged td-kpi-empty" :rowspan="barisKualitatif.length" />
                        </template>
                    </tr>

                    <tr v-if="!barisKompetensi.length && !barisKualitatif.length">
                        <td :colspan="1 + jumlahKolomOrang" class="td-empty-all">
                            Belum ada parameter penilaian yang terdaftar untuk peserta di sesi ini.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Dialog Edit Kualitatif -->
        <PaBalancingKualitatifDialog
            v-model="modalKualitatif.buka"
            :peserta="modalKualitatif.peserta"
            :competency-id="modalKualitatif.id"
            :bisa-ubah="bisaUbah"
            @ubah="emit('ubah')"
        />

        <!-- ═══════════════════════════════════════════════════════════════
             4. TELEPORTED INLINE REASON POPOVER
             ═══════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div
                v-if="popAlasan"
                ref="kotakPop"
                class="pa-bal-mx-pop"
                :style="{ top: `${popAlasan.y}px`, left: `${popAlasan.x}px` }"
                role="dialog"
                aria-label="Alasan perubahan peserta"
                @keydown.esc="tutupAlasan"
            >
                <div class="pa-bal-mx-pop__header">
                    <div class="pa-bal-mx-pop__title-group">
                        <span class="pa-bal-mx-pop__kicker">Alasan Penyesuaian</span>
                        <strong class="pa-bal-mx-pop__param">{{ popAlasan.p.nama }}</strong>
                    </div>
                    <button type="button" class="pa-bal-mx-pop__close" title="Tutup" @click="tutupAlasan">
                        <HcisIcon name="x-lg" size="2xs" />
                    </button>
                </div>

                <div class="pa-bal-mx-pop__target-strip">
                    <span class="pa-bal-mx-pop__emp">{{ jumlahUbahOrang(popAlasan.p) }} nilai diubah · Final</span>
                    <div class="pa-bal-mx-pop__score-shift">
                        <span>{{ formatNilai(popAlasan.p.sebelum?.final) }}</span>
                        <HcisIcon name="arrow-right" size="2xs" />
                        <strong>{{ formatNilai(hitungFinalSesudah(popAlasan.p)) }}</strong>
                    </div>
                </div>

                <textarea
                    ref="inputPop"
                    rows="3"
                    maxlength="500"
                    class="pa-bal-mx-pop__input"
                    :value="popAlasan.p.alasan || ''"
                    :readonly="!bisaUbah"
                    placeholder="Kenapa nilai orang ini disesuaikan? (kesepakatan forum)"
                    @input="ubahAlasan(popAlasan.p, $event.target.value)"
                    @keydown.enter.exact.prevent="tutupAlasan"
                />

                <div class="pa-bal-mx-pop__footer">
                    <small class="pa-bal-mx-pop__shortcut">Tekan <kbd>Enter</kbd> untuk selesai</small>
                    <HcisButton
                        label="Selesai"
                        variant="primary"
                        size="xs"
                        leading-icon="check-lg"
                        @click="tutupAlasan"
                    />
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { HcisAvatar, HcisBadge, HcisButton, HcisIcon } from '@/components/ui';
import PaBalancingKualitatifDialog from './PaBalancingKualitatifDialog.vue';
import {
    LABEL_TINJAU,
    adaPerubahan,
    berubah,
    butuhAlasanPeserta,
    diLuarRentang,
    formatNilai,
    formatPersen,
    hitungDelta,
    hitungFinalSesudah,
    hitungKompSesudah,
    labelGrade,
} from '../utils/balancing.js';

/* Board bergaya lembar rapat: kolom per orang (dikelompokkan per level), baris per parameter,
   Before/After berdampingan, KPI satu sel menurun. Menyunting objek peserta yang sama dengan
   tampilan kartu, jadi simpan/simulasi tidak perlu tahu tampilan mana yang dipakai. */
const props = defineProps({
    peserta: { type: Array, required: true },
    bisaUbah: { type: Boolean, default: false },
});
const emit = defineEmits(['ubah']);

const akar = ref(null);
const kolomAktif = ref(null);
const levelAktif = ref('');

/* ── Kelompok & kolom ── */
const semuaGrup = computed(() => {
    const peta = new Map();
    for (const p of props.peserta) {
        const level = p.level || 'Tanpa level';
        if (!peta.has(level)) peta.set(level, []);
        peta.get(level).push(p);
    }
    return [...peta].map(([level, peserta]) => ({ level, peserta }));
});

const grup = computed(() => {
    const ada = semuaGrup.value.some((g) => g.level === levelAktif.value);
    return levelAktif.value && ada ? semuaGrup.value.filter((g) => g.level === levelAktif.value) : semuaGrup.value;
});

const pesertaTampil = computed(() => grup.value.flatMap((g) => g.peserta));
const jumlahKolomOrang = computed(() => pesertaTampil.value.length * 3);

function kumpulkanBaris(kunci) {
    const peta = new Map();
    for (const p of pesertaTampil.value) {
        for (const k of p[kunci] || []) {
            if (!peta.has(k.competency_id)) peta.set(k.competency_id, { id: k.competency_id, nama: k.nama });
        }
    }
    return [...peta.values()];
}

const barisKompetensi = computed(() => kumpulkanBaris('kompetensi'));
const barisKualitatif = computed(() => kumpulkanBaris('kualitatif'));

const indeks = computed(() => new Map(props.peserta.map((p) => [p.participant_id, {
    kompetensi: new Map((p.kompetensi || []).map((k) => [k.competency_id, k])),
    kualitatif: new Map((p.kualitatif || []).map((q) => [q.competency_id, q])),
}])));

const kompetensiDari = (p, id) => indeks.value.get(p.participant_id)?.kompetensi.get(id) || null;
const kualitatifDari = (p, id) => indeks.value.get(p.participant_id)?.kualitatif.get(id) || null;

/* ── Ringkasan ── */
const perubahan = computed(() => props.peserta.flatMap((p) => (p.kompetensi || [])
    .filter((k) => !diLuarRentang(k) && berubah(k.sebelum, k.sesudah))
    .map((k) => ({ p, k }))));

const kurangAlasan = computed(() => props.peserta.filter((p) => butuhAlasanPeserta(p)).length);
const jumlahUbahOrang = (p) => (p.kompetensi || []).filter((k) => !diLuarRentang(k) && berubah(k.sebelum, k.sesudah)).length
    + (p.kualitatif || []).filter((q) => (q.sesudah ?? '') !== (q.sebelum ?? '')).length;
const jumlahDiubah = computed(() => props.peserta.filter((p) => adaPerubahan(p)).length);
const jumlahBelum = computed(() => props.peserta.filter((p) => statusTampil(p) === 'BELUM').length);

function statusTampil(p) {
    if (adaPerubahan(p)) return 'DIUBAH';
    return p.status_tinjau === 'DIUBAH' ? 'BELUM' : p.status_tinjau;
}

/* ── Format & warna ── */
function formatAngka(v) {
    if (v === null || v === undefined || v === '') return '–';
    return Number(v).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function naikTurun(a, b) {
    return Number(b) > Number(a) ? 'is-naik' : 'is-turun';
}

function selisihRingkas(k) {
    const d = Math.round((Number(k.sesudah) - Number(k.sebelum)) * 100) / 100;
    return `${d > 0 ? '+' : '−'}${Math.abs(d).toLocaleString('id-ID', { maximumFractionDigits: 2 })}`;
}

// 50 ke bawah merah muda, 75 kuning, 100 hijau muda — pembanding cepat antar orang dalam satu baris.
function warnaPanas(v) {
    if (v === null || v === undefined || v === '') return {};
    const t = Math.max(0, Math.min(1, (Number(v) - 50) / 50));
    return { backgroundColor: `hsl(${Math.round(t * 120)}, 70%, 95%)` };
}

function kelasAfter(k) {
    if (diLuarRentang(k)) return 'is-galat';
    if (!berubah(k.sebelum, k.sesudah)) return '';
    return 'is-ubah';
}

// HTML rangkuman → teks per baris: setiap poin/paragraf satu baris diawali "• ".
function teksPolos(html) {
    const teks = String(html ?? '')
        .replace(/<li[^>]*>/gi, '\n• ')
        .replace(/<br\s*\/?>|<\/(p|li|div)>/gi, '\n')
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/g, ' ').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;|&apos;/g, "'").replace(/&amp;/g, '&');
    return teks.split('\n').map((x) => x.replace(/\s+/g, ' ').trim()).filter((x) => x && x !== '•').join('\n');
}

/* ── Sunting ── */
/* Sel yang sedang diisi dimulai dari angka Before. Disimpan sebagai state, bukan ditulis
   langsung ke DOM: fokus pertama ikut menyorot kolom, render ulangnya menimpa nilai input
   dengan :value — sehingga isian Before hilang pada klik pertama. */
const selFokus = ref(null);

function nilaiTampilAfter(p, k) {
    if (k.sesudah !== '' && k.sesudah !== null && k.sesudah !== undefined && berubah(k.sebelum, k.sesudah)) return k.sesudah;
    return selFokus.value === kunciSel(p, k) ? k.sebelum : '';
}

async function mulaiIsi(e, p, k) {
    const el = e.target;
    kolomAktif.value = p.participant_id;
    selFokus.value = kunciSel(p, k);
    await nextTick();
    if (document.activeElement === el) el.select();
}

function selesaiIsi() {
    // @change sudah jalan sebelum blur; bila tak diubah, sel kembali kosong (= Before).
    selFokus.value = null;
}

function ubahNilai(k, v) {
    k.sesudah = String(v).trim() === '' ? k.sebelum : Number(v);
    emit('ubah');
}

function ubahAlasan(p, v) {
    p.alasan = v;
    emit('ubah');
}

function teksBerubah(q) {
    return (q.sesudah ?? '') !== (q.sebelum ?? '');
}

const modalKualitatif = reactive({ buka: false, peserta: null, id: null });

function bukaKualitatif(p, id) {
    kolomAktif.value = p.participant_id;
    modalKualitatif.peserta = p;
    modalKualitatif.id = id;
    modalKualitatif.buka = true;
}

function setujui(p) {
    p.status_tinjau = 'TANPA_PERUBAHAN';
    emit('ubah');
}

const kunciSel = (p, k) => `${p.participant_id}-${k.competency_id}`;

/* ── Navigasi ala spreadsheet ── */
function pindahBaris(e, baris, kolom) {
    e.target.blur();
    const arah = e.shiftKey ? -1 : 1;
    for (let b = baris + arah; b >= 0 && b < barisKompetensi.value.length; b += arah) {
        const target = akar.value?.querySelector(`input[data-baris="${b}"][data-kolom="${kolom}"]`);
        if (target) {
            target.focus();
            target.select?.();
            return;
        }
    }
}

// Dari bar ringkasan: gulir ke peserta pertama yang belum ada alasannya lalu buka kotak alasannya.
async function keAlasanPertama() {
    const p = props.peserta.find((x) => butuhAlasanPeserta(x));
    if (!p) return;
    if (levelAktif.value && (p.level || 'Tanpa level') !== levelAktif.value) levelAktif.value = '';
    await nextTick();
    const tombol = akar.value?.querySelector(`[data-alasan="${p.participant_id}"]`);
    if (!tombol) return;
    tombol.scrollIntoView({ block: 'center', inline: 'center' });
    await nextTick();
    bukaAlasanDi(tombol, p);
}

/* ── Kotak alasan di sel ── */
const popAlasan = ref(null);
const kotakPop = ref(null);
const inputPop = ref(null);

function bukaAlasan(e, p) {
    bukaAlasanDi(e.currentTarget, p);
}

function posisiDari(el) {
    const r = el.getBoundingClientRect();
    const lebar = 300;

    return {
        x: Math.max(8, Math.min(window.innerWidth - lebar - 8, r.right - lebar)),
        y: Math.min(window.innerHeight - 220, r.bottom + 6),
        terlihat: r.bottom > 0 && r.top < window.innerHeight,
    };
}

async function bukaAlasanDi(el, p) {
    const { x, y } = posisiDari(el);
    popAlasan.value = { p, x, y, dibuka: Date.now() };
    await nextTick();
    inputPop.value?.focus();
}

function tutupAlasan() {
    popAlasan.value = null;
}

function gulirTutup(e) {
    /* Gulir DI DALAM kotak alasan bukan alasan menutupnya (UAT 14 Okt 2026). Pendengar ini
       memakai capture, jadi textarea yang mulai bergulir sesudah ±3 baris ikut tertangkap —
       kotaknya tertutup sendiri di tengah mengetik alasan. */
    if (!popAlasan.value) return;
    if (kotakPop.value && e?.target instanceof Node && kotakPop.value.contains(e.target)) return;

    /* Gulir di luar kotak: kotaknya MENGIKUTI tombolnya, tidak ditutup. Huruf pertama alasan
       menghilangkan penanda "N perlu alasan" di atas matriks; isinya bergeser, browser
       menyesuaikan gulir dokumen sendiri, dan dulu itu menutup kotak tepat di huruf pertama
       (terukur pada layar 1024–1366 px). Ditutup hanya bila tombolnya hilang/keluar layar. */
    const tombol = akar.value?.querySelector(`[data-alasan="${popAlasan.value.p.participant_id}"]`);

    if (!tombol) {
        tutupAlasan();
        return;
    }

    const { x, y, terlihat } = posisiDari(tombol);

    if (!terlihat) {
        tutupAlasan();
        return;
    }

    popAlasan.value = { ...popAlasan.value, x, y };
}

function klikLuar(e) {
    if (!popAlasan.value) return;
    if (kotakPop.value?.contains(e.target) || e.target.closest?.('.pa-bal-orang-alasan')) return;
    tutupAlasan();
}

onMounted(() => {
    document.addEventListener('mousedown', klikLuar);
    window.addEventListener('scroll', gulirTutup, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', klikLuar);
    window.removeEventListener('scroll', gulirTutup, true);
});
</script>

<style scoped>
.pa-bal-matriks {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.125rem 1.25rem;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

/* ═══════════════════════════════════════════════════════════════════════════
   1. TOOLBAR & STATS RIBBON
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-bal-mx-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    flex-wrap: wrap;
    background: rgba(248, 250, 252, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: var(--hcis-radius-xl, 14px);
    padding: 0.55rem 0.85rem;
}

.pa-bal-mx-toolbar__left {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-bal-mx-alasan-shortcut {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: #b45309;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 150ms ease;
    animation: pa-bal-mx-pulse 2s infinite;
}

.pa-bal-mx-alasan-shortcut:hover {
    background: rgba(245, 158, 11, 0.22);
    border-color: rgba(245, 158, 11, 0.5);
    color: #92400e;
}

@keyframes pa-bal-mx-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
    50% { box-shadow: 0 0 0 4px rgba(245, 158, 11, 0); }
}

.pa-bal-mx-toolbar__right {
    display: inline-flex;
    align-items: center;
    gap: 0.85rem;
    flex-wrap: wrap;
}

.pa-bal-mx-legend {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-mx-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    white-space: nowrap;
}

.legend-swatch {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 3px;
}

.legend-swatch.is-ubah {
    background: rgba(79, 70, 229, 0.15);
    border: 1px solid rgba(79, 70, 229, 0.4);
}

.legend-swatch.is-wajib {
    background: rgba(245, 158, 11, 0.18);
    border: 1px solid rgba(245, 158, 11, 0.6);
}

.pa-bal-mx-legend-heat {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.heat-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.heat-dot.is-rendah { background: hsl(0, 70%, 75%); }
.heat-dot.is-tengah { background: hsl(60, 70%, 70%); }
.heat-dot.is-tinggi { background: hsl(120, 60%, 70%); }

/* ═══════════════════════════════════════════════════════════════════════════
   2. LEVEL CONTROLS & SHORTCUT HINTS
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-bal-mx-controls-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pa-bal-mx-level-group {
    display: inline-flex;
    align-items: center;
    background: rgba(241, 245, 249, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-lg, 10px);
    padding: 3px;
    gap: 3px;
}

.pa-bal-level-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border: 0;
    border-radius: var(--hcis-radius-md, 7px);
    background: transparent;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-bal-level-btn:hover {
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-level-btn.is-active {
    background: #ffffff;
    color: var(--hcis-brand-600, #4f46e5);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.pa-bal-level-cnt {
    display: inline-block;
    padding: 0 0.3rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 700;
    background: rgba(226, 232, 240, 0.8);
    color: inherit;
}

.pa-bal-mx-shortcut-hint {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-mx-shortcut-hint kbd {
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-bottom-width: 2px;
    font-size: 0.65rem;
    font-family: inherit;
    color: #334155;
    font-weight: 600;
}

/* ═══════════════════════════════════════════════════════════════════════════
   3. SPREADSHEET TABLE & STICKY STRUCTURE
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-bal-mx-scroll {
    position: relative;
    overflow: auto;
    max-height: 92vh;
    min-height: 550px;
    border: 1px solid #cbd5e1;
    border-radius: var(--hcis-radius-xl, 14px);
    background-color: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.pa-bal-mx-table {
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8125rem;
    min-width: 100%;
    width: max-content;
    background-color: #ffffff;
}

.pa-bal-mx-table th,
.pa-bal-mx-table td {
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.4rem 0.55rem;
    text-align: center;
    white-space: nowrap;
    background-color: #ffffff;
}

/* THEAD MUST BE SOLID AND HIGH Z-INDEX */
.pa-bal-mx-table thead {
    position: sticky;
    top: 0;
    z-index: 30;
    background-color: #ffffff;
}

.pa-bal-mx-table thead th {
    background-color: #ffffff;
    border-bottom: 1px solid #cbd5e1;
}

/* Frozen Left Parameter Column */
.pa-bal-mx-table .th-param {
    position: sticky;
    left: 0;
    z-index: 15;
    width: 250px;
    min-width: 250px;
    max-width: 250px;
    box-sizing: border-box;
    text-align: left;
    background-color: #ffffff !important;
    border-right: 2px solid #cbd5e1;
    box-shadow: 3px 0 6px -2px rgba(15, 23, 42, 0.05);
}

/* Header Parameter Column: sticky on BOTH top and left */
.pa-bal-mx-table thead .th-param {
    position: sticky;
    top: 0;
    left: 0;
    z-index: 45 !important;
    border-right: 2px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
    box-sizing: border-box;
}

/* Corner Header Cell (Spans Rows 1 to 3) */
.th-param.th-corner {
    background-color: #f8fafc !important;
    vertical-align: middle !important;
    text-align: center !important;
    padding: 1rem 0.75rem !important;
    border-right: 2px solid #cbd5e1 !important;
    border-bottom: 1px solid #cbd5e1 !important;
    box-sizing: border-box;
}

.pa-bal-corner-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    width: 100%;
    box-sizing: border-box;
}

.pa-bal-corner-icon-circle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 999px;
    background-color: #eef2ff;
    color: var(--hcis-brand-600, #4f46e5);
    border: 1px solid #e0e7ff;
}

.pa-bal-corner-text {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.15rem;
}

.pa-bal-corner-kicker {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #64748b;
}

.pa-bal-corner-title {
    font-size: 0.875rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}

.pa-bal-corner-capsule {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    background-color: #eef2ff;
    border: 1px solid #e0e7ff;
    color: #4338ca;
    font-size: 0.6875rem;
    font-weight: 700;
    white-space: nowrap;
}

.pa-bal-corner-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background-color: #4f46e5;
}

.pa-bal-corner-meta {
    font-size: 0.65rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}

/* Row 4 Parameter Title Header */
.th-param.th-param-title {
    background-color: #f1f5f9 !important;
    padding: 0.4rem 0.75rem !important;
    border-bottom: 2px solid #cbd5e1 !important;
    border-right: 2px solid #cbd5e1 !important;
    text-align: left !important;
    box-sizing: border-box;
}

.pa-bal-param-corner-label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.6875rem;
    font-weight: 800;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
}

.pa-bal-param-corner-label .hcis-icon {
    color: #4f46e5;
}

/* Row 1: Level Groups */
.tr-level th.th-level-group {
    background-color: #f1f5f9;
    color: #0f172a;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.4rem 0.75rem;
    border-bottom: 1px solid #cbd5e1;
}

/* Row 2: Employee Header Cards */
.tr-orang th.th-orang-card {
    background-color: #ffffff;
    vertical-align: top;
    padding: 0.65rem 0.75rem;
    width: 250px;
    min-width: 250px;
    max-width: 250px;
    box-sizing: border-box;
    border-bottom: 1px solid #e2e8f0;
    border-right: 1px solid #cbd5e1;
    transition: background-color 150ms ease;
}

/* ACTIVE COLUMN MUST BE SOLID OPAQUE COLOR! */
.tr-orang th.th-orang-card.is-active-col {
    background-color: #f5f3ff !important;
}

.pa-bal-orang-card {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    text-align: left;
    background: inherit;
}

.pa-bal-orang__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-bal-orang__id {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.pa-bal-orang__text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pa-bal-orang__name-btn {
    background: none;
    border: none;
    padding: 0;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f172a;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 130px;
    cursor: pointer;
}

.pa-bal-orang__name-btn:hover {
    color: #4338ca;
    text-decoration: underline;
}

.pa-bal-orang__nik {
    font-size: 0.625rem;
    color: #64748b;
    font-weight: 500;
}

.pa-bal-orang__scores {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.35rem 0.5rem;
}

.pa-bal-final-score-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.pa-bal-score-label {
    font-size: 0.625rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.pa-bal-score-nums {
    display: inline-flex;
    align-items: baseline;
    gap: 0.25rem;
}

.pa-bal-score-crossed {
    font-size: 0.72rem;
    color: #94a3b8;
    text-decoration: line-through;
}

.pa-bal-score-current {
    font-size: 0.875rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.pa-bal-pillars-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.6875rem;
    padding-top: 0.15rem;
    border-top: 1px dashed #e2e8f0;
}

.pa-bal-pillar-stat {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    color: #475569;
}

.pa-bal-pillar-tag {
    font-weight: 600;
    color: #64748b;
    font-size: 0.65rem;
}

.pa-bal-pillar-crossed {
    font-size: 0.65rem;
    color: #94a3b8;
    text-decoration: line-through;
}

.pa-bal-pillar-stat b {
    font-weight: 700;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.pa-bal-pillar-stat small {
    color: #94a3b8;
    font-size: 0.625rem;
}

.pa-bal-orang-alasan {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    width: 100%;
    max-width: 240px;
    margin-top: 0.4rem;
    padding: 0.3rem 0.55rem;
    border: 1px solid #c7d2fe;
    border-radius: 8px;
    background: #fff;
    color: #4338ca;
    font-size: 0.7rem;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
}

.pa-bal-orang-alasan span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-bal-orang-alasan:hover {
    background: #eef2ff;
}

.pa-bal-orang-alasan.is-wajib {
    border-color: #fb923c;
    background: #fff7ed;
    color: #c2410c;
    font-weight: 700;
    animation: pa-bal-alasan-denyut 1.8s ease-in-out infinite;
}

.pa-bal-orang-alasan.has-alasan {
    font-style: italic;
    color: #334155;
}

@keyframes pa-bal-alasan-denyut {
    50% { box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.25); }
}

.pa-bal-setujui-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    border: 1px solid #a7f3d0;
    background-color: #ecfdf5;
    color: #065f46;
    font-size: 0.6875rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 120ms ease;
    width: 100%;
    box-sizing: border-box;
}

.pa-bal-setujui-btn:hover {
    background-color: #d1fae5;
    border-color: #6ee7b7;
}

/* Row 3: Pillar Groups Header (Kompetensi vs KPI) */
.tr-pilar th.th-pilar {
    background-color: #f8fafc;
    padding: 0.3rem 0.45rem;
    border-bottom: 1px solid #cbd5e1;
    text-align: center;
    box-sizing: border-box;
}

.tr-pilar th.th-pilar-komp {
    width: 188px;
    min-width: 188px;
    max-width: 188px;
    background-color: #f1f5f9;
    color: #1e293b;
    border-right: 1px solid #cbd5e1;
}

.tr-pilar th.th-pilar-kpi {
    width: 62px;
    min-width: 62px;
    max-width: 62px;
    background-color: #f8fafc;
    color: #475569;
    border-right: 1px solid #cbd5e1;
}

.tr-pilar th.th-pilar.is-active-col {
    background-color: #ede9fe !important;
}

.pa-bal-pilar-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    width: 100%;
}

.pa-bal-pilar-title {
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.pa-bal-pilar-weight {
    display: inline-block;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 700;
    background-color: #e2e8f0;
    color: #475569;
}

.tr-pilar th.th-pilar.is-active-col .pa-bal-pilar-weight {
    background-color: #ddd6fe;
    color: #5b21b6;
}

/* Row 4: Sub-column labels (Before, After, Skor) */
.tr-kolom th.th-sub {
    background-color: #f8fafc;
    color: #64748b;
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.35rem 0.45rem;
    border-bottom: 2px solid #cbd5e1 !important;
    box-shadow: 0 4px 6px -2px rgba(15, 23, 42, 0.08);
}

.tr-kolom th.th-param-title {
    background-color: #f8fafc;
    color: #64748b;
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    border-bottom: 2px solid #cbd5e1 !important;
}

.tr-kolom th.th-after {
    color: #4338ca !important;
    background-color: #eef2ff !important;
    font-weight: 800;
}

.tr-kolom th.is-active-col {
    background-color: #ede9fe !important;
}

.tr-kolom th.th-after.is-active-col {
    background-color: #e0e7ff !important;
}

/* Strict Sub-column dimensions across thead and tbody */
.th-sub.th-before,
.td-before {
    width: 60px;
    min-width: 60px;
    max-width: 60px;
    box-sizing: border-box;
}

.th-sub.th-after,
.td-after {
    width: 128px;
    min-width: 128px;
    max-width: 128px;
    box-sizing: border-box;
    border-right: 1px solid #cbd5e1;
}

.th-sub.th-kpi,
.td-kpi,
.td-kpi-merged {
    width: 62px;
    min-width: 62px;
    max-width: 62px;
    box-sizing: border-box;
    border-right: 1px solid #cbd5e1 !important;
}

/* ═══════════════════════════════════════════════════════════════════════════
   SECTION ROWS & BODY CELLS
   ═══════════════════════════════════════════════════════════════════════════ */
.tr-section th.th-section-param,
.tr-section td.td-section-span {
    background-color: #f1f5f9;
    padding: 0.35rem 0.65rem;
    border-top: 1px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
}

.pa-bal-section-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #0f172a;
}

.pa-bal-section-count {
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    background-color: #cbd5e1;
    color: #334155;
    font-size: 0.625rem;
    font-weight: 700;
}

.tr-body-row:hover th.th-body-param {
    background-color: #f8fafc !important;
}

.pa-bal-param-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-bal-param-indicator {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #6366f1;
    flex-shrink: 0;
}

.tr-body-row th.th-body-param,
.tr-body-row td {
    height: 48px;
    padding: 0.55rem 0.6rem;
    vertical-align: middle;
}

.pa-bal-param-label {
    font-size: 0.84rem;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.35;
}

/* Before Value Cell */
.td-before {
    font-size: 0.875rem;
    font-weight: 700;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    background-color: #ffffff;
    vertical-align: middle;
}

.td-before.is-active-col {
    background-color: #f5f3ff;
}

/* After Value Cell */
.td-after {
    padding: 0.35rem 0.25rem;
    background-color: #ffffff;
    transition: background-color 120ms ease;
    vertical-align: middle;
}

.td-after.is-ubah {
    background-color: #f5f3ff !important;
}

.td-after.is-wajib {
    background-color: #fffbeb !important;
    box-shadow: inset 0 0 0 1px #fcd34d;
}

.td-after.is-galat {
    background-color: #fef2f2 !important;
    box-shadow: inset 0 0 0 1px #fca5a5;
}

.td-after.is-active-col:not(.is-ubah):not(.is-wajib):not(.is-galat) {
    background-color: #faf5ff !important;
}

.pa-bal-cell-editor {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.2rem;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
}

.pa-bal-cell-input {
    flex: 1 1 0;
    width: 100%;
    min-width: 0;
    height: 32px;
    padding: 0 0.25rem;
    border: 1.5px solid #cbd5e1;
    border-radius: 6px;
    background-color: #ffffff;
    text-align: center;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #4338ca;
    font-variant-numeric: tabular-nums;
    -moz-appearance: textfield;
    transition: all 120ms ease;
    box-sizing: border-box;
}

.td-after.is-wajib .pa-bal-cell-input {
    border-color: #f59e0b;
}

.td-after.is-galat .pa-bal-cell-input {
    border-color: #ef4444;
}

.pa-bal-cell-input::-webkit-outer-spin-button,
.pa-bal-cell-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.pa-bal-cell-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    background-color: #ffffff;
}

.pa-bal-cell-static {
    flex: 1 1 auto;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #4338ca;
    line-height: 32px;
}

/* Delta Chips (used in employee header card) */
.pa-bal-delta-chip {
    display: inline-block;
    padding: 0.08rem 0.35rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}

.pa-bal-delta-chip.is-naik {
    background-color: #ecfdf5;
    color: #065f46;
}

.pa-bal-delta-chip.is-turun {
    background-color: #fef2f2;
    color: #991b1b;
}

/* Unified Delta & Reason Capsule Button */
.pa-bal-delta-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.15rem;
    height: 22px;
    padding: 0 0.28rem;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    flex: 0 0 auto;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 120ms ease;
    box-sizing: border-box;
    white-space: nowrap;
    line-height: 1;
}

.pa-bal-delta-btn.is-naik {
    background-color: #ecfdf5;
    color: #065f46;
    border-color: #a7f3d0;
}

.pa-bal-delta-btn.is-naik:hover {
    background-color: #d1fae5;
    border-color: #6ee7b7;
}

.pa-bal-delta-btn.is-turun {
    background-color: #fef2f2;
    color: #991b1b;
    border-color: #fecaca;
}

.pa-bal-delta-btn.is-turun:hover {
    background-color: #fee2e2;
    border-color: #fca5a5;
}

.pa-bal-delta-btn.is-wajib {
    background-color: #fffbeb !important;
    color: #b45309 !important;
    border-color: #f59e0b !important;
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
    animation: pa-bal-mx-denyut 1.5s ease-in-out infinite;
}

.pa-bal-delta-btn.is-wajib:hover {
    background-color: #fef3c7 !important;
}

.pa-bal-delta-warn-icon {
    color: #d97706;
    font-size: 0.625rem;
    flex-shrink: 0;
}

.pa-bal-delta-note-icon {
    color: #4f46e5;
    font-size: 0.6rem;
    flex-shrink: 0;
}

.pa-bal-delta-val {
    font-weight: 700;
    letter-spacing: -0.01em;
}

/* Legacy Reason Button fallback */
.pa-bal-reason-trigger-btn {
    display: inline-grid;
    place-items: center;
    width: 26px;
    height: 26px;
    flex: 0 0 auto;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #4f46e5;
    cursor: pointer;
    transition: all 120ms ease;
}

.pa-bal-reason-trigger-btn:hover {
    background-color: #eef2ff;
}

.pa-bal-reason-trigger-btn.is-wajib {
    color: #d97706;
    animation: pa-bal-mx-denyut 1.5s ease-in-out infinite;
}

@keyframes pa-bal-mx-denyut {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
}

/* Merged KPI Cell */
.td-kpi-merged {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
    background-color: #f8fafc;
    vertical-align: middle;
    padding: 0.75rem 0.5rem;
}

.td-kpi-merged.is-active-col {
    background-color: #f5f3ff;
}

.pa-bal-kpi-val {
    font-variant-numeric: tabular-nums;
}

/* NA State */
.td-na {
    color: #94a3b8;
    background-color: #f8fafc;
}

.pa-bal-na-text {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.6875rem;
    font-weight: 600;
    font-style: italic;
    color: #94a3b8;
}

/* Qualitative Text Cells */
.td-teks {
    white-space: normal;
    text-align: left;
    vertical-align: middle;
    font-size: 0.75rem;
    cursor: pointer;
    background-color: #ffffff;
    padding: 0.35rem 0.45rem;
    transition: background-color 120ms ease;
}

.td-teks:hover {
    background-color: #f8fafc;
}

.td-teks.is-active-col {
    background-color: #f5f3ff;
}

.pa-bal-teks-cell-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.25rem;
    width: 100%;
}

.pa-bal-teks-snippet {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: 0.72rem;
    color: #334155;
    line-height: 1.35;
    text-align: left;
    white-space: normal;
}

.pa-bal-teks-snippet.is-ubah {
    color: #4338ca;
    font-weight: 600;
}

.pa-bal-teks-empty {
    font-size: 0.6875rem;
    color: #94a3b8;
    font-style: italic;
    white-space: nowrap;
}

.pa-bal-teks-micro-btn {
    display: inline-grid;
    place-items: center;
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background-color: #ffffff;
    color: #4338ca;
    cursor: pointer;
    transition: all 0.15s ease;
}

.pa-bal-teks-micro-btn:hover {
    border-color: #6366f1;
    background-color: #eef2ff;
}

.td-empty-all {
    padding: 2.5rem 1rem;
    color: #94a3b8;
    font-style: italic;
    text-align: center;
}

/* ═══════════════════════════════════════════════════════════════════════════
   4. INLINE REASON POPOVER
   ═══════════════════════════════════════════════════════════════════════════ */
.pa-bal-mx-pop {
    position: fixed;
    z-index: 2000;
    width: 300px;
    padding: 0.85rem;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.2), 0 4px 12px rgba(15, 23, 42, 0.08);
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.pa-bal-mx-pop__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-bal-mx-pop__title-group {
    display: flex;
    flex-direction: column;
}

.pa-bal-mx-pop__kicker {
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-mx-pop__param {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-mx-pop__close {
    background: none;
    border: 0;
    padding: 0.2rem;
    color: #94a3b8;
    cursor: pointer;
    border-radius: 4px;
}

.pa-bal-mx-pop__close:hover {
    color: #334155;
}

.pa-bal-mx-pop__target-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: var(--hcis-radius-md, 6px);
    padding: 0.35rem 0.55rem;
    font-size: 0.72rem;
}

.pa-bal-mx-pop__emp {
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
}

.pa-bal-mx-pop__score-shift {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-mx-pop__score-shift strong {
    color: var(--hcis-brand-600, #4f46e5);
}

.pa-bal-mx-pop__input {
    width: 100%;
    padding: 0.45rem 0.65rem;
    font-size: 0.8125rem;
    border-radius: var(--hcis-radius-md, 8px);
    border: 1px solid rgba(203, 213, 225, 0.9);
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    resize: vertical;
    box-sizing: border-box;
}

.pa-bal-mx-pop__input:focus {
    outline: none;
    border-color: var(--hcis-brand-500, #6366f1);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.pa-bal-mx-pop__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.pa-bal-mx-pop__shortcut {
    font-size: 0.6875rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-mx-pop__shortcut kbd {
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    font-size: 0.625rem;
    color: #334155;
}

@media (max-width: 768px) {
    .pa-bal-matriks {
        padding: 0.75rem;
    }

    .pa-bal-mx-table .th-param {
        min-width: 140px;
        max-width: 160px;
    }

    .tr-orang th.th-orang-card {
        min-width: 180px;
    }

    .pa-bal-mx-toolbar__right {
        display: none;
    }
}
</style>

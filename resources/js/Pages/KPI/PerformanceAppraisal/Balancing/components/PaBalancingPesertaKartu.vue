<template>
    <HcisCard
        variant="glass"
        sentiment="neutral"
        class="pa-bal-kartu-wrap"
        :class="{
            'is-terbuka': terbuka,
            'is-galat': galat.length > 0,
            'is-berubah': statusTampil === 'DIUBAH',
            'is-tanpa-perubahan': statusTampil === 'TANPA_PERUBAHAN'
        }"
    >
        <article class="pa-bal-kartu">
            <button
                type="button"
                class="pa-bal-kartu__kepala"
                :aria-expanded="terbuka"
                @click="$emit('toggle')"
            >
                <div class="pa-bal-kartu__avatar-box">
                    <HcisAvatar
                        :name="peserta.nama"
                        size="sm"
                        shape="rounded"
                    />
                </div>

                <div class="pa-bal-kartu__nama">
                    <strong>{{ peserta.nama }}</strong>
                    <small>{{ peserta.kode_karyawan }} &bull; {{ peserta.level || '–' }}</small>
                </div>

                <div class="pa-bal-kartu__angka">
                    <span class="pa-bal-angka-pill">Final <b>{{ formatNilai(peserta.sebelum?.final) }}</b></span>
                    <span class="pa-bal-angka-pill" :class="{ 'is-adjusted': adaPerubahan(peserta) }">
                        Komp
                        <template v-if="!adaPerubahan(peserta)">
                            <b>{{ formatNilai(peserta.sebelum?.nilai_kompetensi) }}</b>
                        </template>
                        <template v-else>
                            <b class="text-brand">{{ formatNilai(kompSesudah) }}</b>
                            <HcisBadge
                                v-if="deltaKomp"
                                :label="deltaKomp"
                                :variant="varianKomp"
                                size="xs"
                                appearance="subtle"
                                class="ms-1"
                            />
                        </template>
                    </span>
                    <span class="pa-bal-angka-pill">KPI <b>{{ formatNilai(peserta.sebelum?.nilai_kpi) }}</b></span>
                    <span class="pa-bal-angka-pill">Grade <b>{{ labelGrade(peserta.sebelum?.grade) }}</b></span>
                </div>

                <div class="pa-bal-kartu__badge-wrap">
                    <HcisBadge
                        :label="LABEL_TINJAU[statusTampil] || statusTampil"
                        :variant="statusTampil === 'BELUM' ? 'warning' : statusTampil === 'DIUBAH' ? 'brand' : 'success'"
                        appearance="subtle"
                        size="xs"
                        dot
                    />
                </div>

                <div class="pa-bal-kartu__panah" :class="{ 'is-terbuka': terbuka }" aria-hidden="true">
                    <HcisIcon :name="terbuka ? 'chevron-up' : 'chevron-down'" size="xs" />
                </div>
            </button>

            <div v-if="terbuka" class="pa-bal-kartu__isi">
                <!-- Card Quick Actions Bar -->
                <div v-if="bisaUbah" class="pa-bal-kartu__aksi-bar">
                    <label v-if="!adaPerubahan(peserta)" class="pa-bal-kartu__tanpa">
                        <input
                            type="checkbox"
                            data-uji="tanpa-perubahan"
                            :checked="peserta.status_tinjau === 'TANPA_PERUBAHAN'"
                            @change="tandaiTanpaPerubahan($event.target.checked)"
                        >
                        <span>Sudah dibahas, tanpa perubahan</span>
                    </label>
                    <div v-else class="pa-bal-kartu__info-ubah">
                        <HcisIcon name="info-circle" size="2xs" />
                        <span>Terdapat penyesuaian skor kompetensi pada peserta ini.</span>
                    </div>

                    <div v-if="adaPerubahan(peserta)" class="pa-bal-kartu__reset-semua">
                        <HcisButton
                            label="Reset ke Skor Awal"
                            variant="secondary"
                            size="xs"
                            leading-icon="arrow-counterclockwise"
                            title="Kembalikan semua skor kompetensi ke Before"
                            @click="resetSemuaKeBefore"
                        />
                    </div>
                </div>

                <!-- Competency Matrix Table -->
                <div class="pa-bal-matriks-wrap">
                    <table class="pa-bal-matriks">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 44%;">Kompetensi</th>
                                <th scope="col" style="width: 12%;">Bobot</th>
                                <th scope="col" style="width: 16%;">Before</th>
                                <th scope="col" style="width: 28%;">After</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="k in peserta.kompetensi"
                                :key="k.competency_id"
                                :class="{ 'is-berubah': berubah(k.sebelum, k.sesudah) }"
                            >
                                <td data-label="Kompetensi">
                                    <div class="pa-bal-komp-cell">
                                        <strong>{{ k.nama }}</strong>
                                        <span v-if="k.tingkat" class="pa-bal-tingkat" data-uji="tingkat-dituntut" title="Tingkat yang dituntut untuk level peserta ini (Matriks Kompetensi)">Level {{ k.tingkat }}<template v-if="k.nama_tingkat"> · {{ k.nama_tingkat }}</template></span>
                                        <small v-if="k.deskripsi">{{ k.deskripsi }}</small>
                                        <PaDaftarPerilaku :teks="k.expected_behaviour" judul="" ringkas />
                                    </div>
                                </td>
                                <td data-label="Bobot">
                                    <HcisBadge :label="persen(k.bobot)" variant="neutral" size="xs" appearance="subtle" />
                                </td>
                                <td data-label="Before">
                                    <span class="pa-bal-skor-sebelum">{{ formatNilai(k.sebelum) }}</span>
                                </td>
                                <td data-label="After">
                                    <div v-if="bisaUbah" class="pa-bal-input-group">
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            class="pa-bal-input"
                                            :class="{ 'is-galat': diLuarRentang(k), 'is-berubah-input': berubah(k.sebelum, k.sesudah) }"
                                            :aria-label="`After ${k.nama}`"
                                            :data-uji="`after-${k.competency_id}`"
                                            :value="k.sesudah"
                                            @input="ubahNilai(k, $event.target.value)"
                                        >
                                        <HcisBadge
                                            v-if="hitungDelta(k.sebelum, k.sesudah)"
                                            :label="hitungDelta(k.sebelum, k.sesudah)"
                                            :variant="varianDelta(k.sebelum, k.sesudah)"
                                            size="xs"
                                            appearance="subtle"
                                        />
                                    </div>
                                    <div v-else class="pa-bal-readonly-skor">
                                        <strong>{{ formatNilai(k.sesudah) }}</strong>
                                        <HcisBadge
                                            v-if="hitungDelta(k.sebelum, k.sesudah)"
                                            :label="hitungDelta(k.sebelum, k.sesudah)"
                                            :variant="varianDelta(k.sebelum, k.sesudah)"
                                            size="xs"
                                            appearance="subtle"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Alasan per orang: satu alasan untuk seluruh perubahan orang ini -->
                <div v-if="bisaUbah || peserta.alasan" class="pa-bal-alasan-orang" :class="{ 'is-wajib': butuhAlasanPeserta(peserta) }">
                    <label :for="`pa-bal-alasan-${peserta.participant_id}`">
                        <HcisIcon name="chat-left-text" size="2xs" />
                        Alasan perubahan
                        <small>{{ adaPerubahan(peserta) ? 'wajib, karena ada nilai yang diubah' : 'opsional' }}</small>
                    </label>
                    <textarea
                        v-if="bisaUbah"
                        :id="`pa-bal-alasan-${peserta.participant_id}`"
                        rows="2"
                        maxlength="1000"
                        class="pa-bal-input pa-bal-input--teks"
                        data-uji="alasan-peserta"
                        placeholder="Kenapa nilai orang ini disesuaikan? (kesepakatan forum)"
                        :value="peserta.alasan || ''"
                        @input="ubahAlasan($event.target.value)"
                    />
                    <p v-else class="pa-bal-alasan-readonly">{{ peserta.alasan }}</p>
                </div>

                <!-- Error Alerts -->
                <ul v-if="galat.length" class="pa-bal-kartu__galat" role="alert">
                    <li v-for="g in galat" :key="g">
                        <HcisIcon name="exclamation-circle-fill" size="2xs" />
                        <span>{{ g }}</span>
                    </li>
                </ul>

                <!-- Qualitative Section using HcisCard -->
                <section v-if="peserta.kualitatif?.length" class="pa-bal-kualitatif" aria-label="Uraian Kualitatif">
                    <HcisCard
                        v-for="q in peserta.kualitatif"
                        :key="q.competency_id"
                        variant="flat"
                        size="sm"
                        sentiment="neutral"
                        class="pa-bal-kualitatif__card"
                    >
                        <header class="pa-bal-kualitatif__item-header">
                            <h4>
                                <HcisIcon name="card-text" size="2xs" />
                                {{ q.nama }}
                            </h4>
                            <HcisButton
                                label="Masukan 360°"
                                size="xs"
                                variant="glass"
                                leading-icon="chat-square-quote"
                                @click="bukaModalKualitatif(q.competency_id)"
                            />
                        </header>

                        <div class="pa-bal-kualitatif__grid">
                            <div class="pa-bal-kualitatif__kolom pa-bal-kualitatif__kolom--sebelum">
                                <span class="pa-bal-kualitatif__label">Catatan Awal (Before)</span>
                                <!-- eslint-disable-next-line vue/no-v-html -- disaring server (PaTeksKaya) dan htmlAman. -->
                                <div v-if="q.sebelum" class="pa-bal-kualitatif__konten" v-html="htmlAman(q.sebelum)" />
                                <em v-else class="pa-bal-kualitatif__kosong">Belum ada ringkasan admin.</em>
                            </div>

                            <div class="pa-bal-kualitatif__kolom pa-bal-kualitatif__kolom--sesudah">
                                <span class="pa-bal-kualitatif__label">Catatan Hasil Balancing (After)</span>
                                <HcisRichText
                                    v-if="bisaUbah"
                                    :id="`pa-bal-q-${peserta.participant_id}-${q.competency_id}`"
                                    :label="`After — ${q.nama}`"
                                    :model-value="q.sesudah || ''"
                                    :toolbar="TOOLBAR"
                                    min-height="75px"
                                    max-height="200px"
                                    placeholder="Tuliskan catatan kualitatif hasil keputusan forum..."
                                    @update:model-value="(v) => ubahKualitatif(q, v)"
                                />
                                <!-- eslint-disable-next-line vue/no-v-html -- disaring server (PaTeksKaya) dan htmlAman. -->
                                <div v-else-if="q.sesudah" class="pa-bal-kualitatif__konten" v-html="htmlAman(q.sesudah)" />
                                <em v-else class="pa-bal-kualitatif__kosong">Tidak ada perubahan catatan kualitatif.</em>
                            </div>
                        </div>
                    </HcisCard>
                </section>
            </div>
        </article>

        <!-- Dialog Masukan Kualitatif 360° -->
        <PaBalancingKualitatifDialog
            v-if="modalKualitatifBuka"
            v-model="modalKualitatifBuka"
            :peserta="peserta"
            :competency-id="modalKualitatifCompId"
            :bisa-ubah="bisaUbah"
            @ubah="$emit('ubah')"
        />
    </HcisCard>
</template>

<script setup>
import { computed, ref } from 'vue';
import PaDaftarPerilaku from '../../components/PaDaftarPerilaku.vue';
import {
    HcisAvatar,
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisIcon,
    HcisRichText,
} from '@/components/ui';
import PaBalancingKualitatifDialog from './PaBalancingKualitatifDialog.vue';
import { htmlAman } from '../../utils/htmlAman.js';
import {
    LABEL_TINJAU,
    adaPerubahan,
    berubah,
    butuhAlasanPeserta,
    diLuarRentang,
    formatNilai,
    galatPeserta,
    hitungDelta,
    hitungKompSesudah,
    labelGrade,
    varianDelta,
} from '../utils/balancing.js';

defineOptions({ name: 'PaBalancingPesertaKartu' });

const props = defineProps({
    peserta: { type: Object, required: true },
    bisaUbah: { type: Boolean, default: false },
    terbuka: { type: Boolean, default: false },
});
const emit = defineEmits(['toggle', 'ubah']);

const TOOLBAR = [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']];

const modalKualitatifBuka = ref(false);
const modalKualitatifCompId = ref(null);

function bukaModalKualitatif(compId) {
    modalKualitatifCompId.value = compId;
    modalKualitatifBuka.value = true;
}

const galat = computed(() => galatPeserta(props.peserta));
const statusTampil = computed(() => (adaPerubahan(props.peserta) ? 'DIUBAH' : props.peserta.status_tinjau === 'DIUBAH' ? 'BELUM' : props.peserta.status_tinjau));
const kompSesudah = computed(() => hitungKompSesudah(props.peserta));
const deltaKomp = computed(() => hitungDelta(props.peserta?.sebelum?.nilai_kompetensi, kompSesudah.value));
const varianKomp = computed(() => varianDelta(props.peserta?.sebelum?.nilai_kompetensi, kompSesudah.value));

function persen(b) {
    return `${Math.round(Number(b || 0) * 1000) / 10}%`.replace('.', ',');
}

function ubahNilai(k, v) {
    k.sesudah = v === '' ? '' : Number(v);
    emit('ubah');
}

function ubahAlasan(v) {
    props.peserta.alasan = v;
    emit('ubah');
}

function ubahKualitatif(q, v) {
    q.sesudah = v;
    emit('ubah');
}

function tandaiTanpaPerubahan(ya) {
    props.peserta.status_tinjau = ya ? 'TANPA_PERUBAHAN' : 'BELUM';
    emit('ubah');
}

function resetSemuaKeBefore() {
    for (const k of props.peserta.kompetensi || []) {
        k.sesudah = k.sebelum;
        k.alasan = '';
    }
    props.peserta.status_tinjau = 'BELUM';
    emit('ubah');
}
</script>

<style scoped>
.pa-bal-kartu-wrap {
    padding: 0 !important;
    border-radius: var(--hcis-radius-xl, 16px);
    overflow: hidden;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.pa-bal-kartu-wrap.is-terbuka {
    border-color: rgba(79, 70, 229, 0.35);
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04);
}

.pa-bal-kartu-wrap.is-galat {
    border-color: rgba(239, 68, 68, 0.4);
}

.pa-bal-kartu-wrap.is-berubah {
    border-color: rgba(79, 70, 229, 0.3);
}

.pa-bal-kartu-wrap.is-tanpa-perubahan {
    border-color: rgba(16, 185, 129, 0.3);
}

.pa-bal-kartu {
    width: 100%;
}

/* Card Header (Accordion Button) */
.pa-bal-kartu__kepala {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: none;
    border: 0;
    text-align: left;
    cursor: pointer;
    min-width: 0;
    transition: background-color 150ms ease;
}

.pa-bal-kartu__kepala:hover {
    background-color: rgba(248, 250, 252, 0.6);
}

.pa-bal-kartu__avatar-box {
    flex-shrink: 0;
}

.pa-bal-kartu__nama {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1 1 160px;
    gap: 0.15rem;
}

.pa-bal-kartu__nama strong {
    font-size: 0.9375rem;
    color: var(--hcis-text-strong, #0f172a);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pa-bal-kartu__nama small {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-kartu__angka {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-bal-angka-pill {
    padding: 0.25rem 0.65rem;
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-angka-pill b {
    color: var(--hcis-text-strong, #0f172a);
    margin-left: 0.2rem;
}

.pa-bal-kartu__badge-wrap {
    flex-shrink: 0;
}

.pa-bal-kartu__panah {
    color: #94a3b8;
    transition: transform 200ms ease;
    flex-shrink: 0;
    display: grid;
    place-items: center;
}

.pa-bal-kartu__panah.is-terbuka {
    color: var(--hcis-brand-600, #4f46e5);
}

/* Card Body Content */
.pa-bal-kartu__isi {
    padding: 0 1.25rem 1.25rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.85);
}

.pa-bal-kartu__aksi-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    margin-bottom: 0.5rem;
    border-bottom: 1px dashed rgba(226, 232, 240, 0.85);
}

.pa-bal-kartu__tanpa {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-strong, #0f172a);
    cursor: pointer;
}

.pa-bal-kartu__tanpa input[type="checkbox"] {
    width: 15px;
    height: 15px;
    cursor: pointer;
    accent-color: #10b981;
}

.pa-bal-kartu__info-ubah {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    color: var(--hcis-brand-600, #4f46e5);
    font-weight: 500;
}

/* Matrix Table */
.pa-bal-matriks-wrap {
    overflow-x: auto;
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-md, 10px);
    background: rgba(255, 255, 255, 0.7);
}

.pa-bal-matriks {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}

.pa-bal-matriks th {
    padding: 0.65rem 0.85rem;
    background: rgba(248, 250, 252, 0.9);
    border-bottom: 1px solid rgba(226, 232, 240, 0.85);
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-text-muted, #64748b);
    text-align: left;
}

.pa-bal-matriks td {
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid rgba(241, 245, 249, 0.85);
    vertical-align: top;
}

.pa-bal-matriks tr.is-berubah td {
    background: rgba(254, 243, 199, 0.25);
}

.pa-bal-komp-cell strong {
    color: var(--hcis-text-strong, #0f172a);
    display: block;
}

.pa-bal-komp-cell small {
    display: block;
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.6875rem;
    margin-top: 0.15rem;
}

.pa-bal-skor-sebelum {
    font-weight: 700;
    color: var(--hcis-text-muted, #64748b);
}

.pa-bal-input-group {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.pa-bal-input {
    width: 82px;
    padding: 0.45rem 0.65rem;
    border: 1px solid rgba(203, 213, 225, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    font-size: 0.8125rem;
    font-weight: 700;
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    transition: all 150ms ease;
}

.pa-bal-input:focus {
    outline: none;
    border-color: rgba(79, 70, 229, 0.5);
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.12);
}

.pa-bal-input.is-berubah-input {
    border-color: rgba(79, 70, 229, 0.5);
    background: rgba(238, 242, 255, 0.7);
}

.pa-bal-input.is-galat {
    border-color: #ef4444;
    background: #fef2f2;
}

.pa-bal-input--teks {
    width: 100%;
    min-width: 160px;
    font-weight: 400;
}

.pa-bal-input.is-wajib {
    border-color: #dc2626;
    background: #fff1f2;
}

.pa-bal-readonly-skor {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-bal-alasan-orang {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-top: 0.75rem;
    padding: 0.65rem 0.8rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.pa-bal-alasan-orang.is-wajib {
    border-color: #fdba74;
    background: #fff7ed;
}

.pa-bal-alasan-orang label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0;
    font-size: 0.8rem;
    font-weight: 700;
    color: #0f172a;
}

.pa-bal-alasan-orang label small {
    font-weight: 500;
    color: #64748b;
}

.pa-bal-alasan-orang textarea {
    width: 100%;
    resize: vertical;
}

.pa-bal-alasan-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.pa-bal-alasan-helper {
    color: #dc2626;
    font-size: 0.6875rem;
    font-weight: 600;
}

.pa-bal-alasan-readonly {
    color: var(--hcis-text-muted, #64748b);
    font-size: 0.75rem;
}

/* Errors alert in card */
.pa-bal-kartu__galat {
    margin: 0.85rem 0 0;
    padding: 0.65rem 0.85rem;
    background: rgba(254, 242, 242, 0.85);
    border: 1px solid rgba(254, 202, 202, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    color: #b91c1c;
    font-size: 0.75rem;
}

.pa-bal-kartu__galat li {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

/* Qualitative section */
.pa-bal-kualitatif {
    margin-top: 1rem;
    padding-top: 0.85rem;
    border-top: 1px dashed rgba(226, 232, 240, 0.85);
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.pa-bal-kualitatif__card {
    border-radius: var(--hcis-radius-md, 10px) !important;
    padding: 0.85rem !important;
}

.pa-bal-kualitatif__item-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.65rem;
}

.pa-bal-kualitatif__item-header h4 {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--hcis-text-strong, #0f172a);
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}

.pa-bal-kualitatif__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0.85rem;
}

.pa-bal-kualitatif__kolom {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-bal-kualitatif__label {
    font-size: 0.6875rem;
    font-weight: 700;
    color: var(--hcis-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.pa-bal-kualitatif__konten {
    font-size: 0.75rem;
    line-height: 1.5;
    color: var(--hcis-text-strong, #0f172a);
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: var(--hcis-radius-md, 8px);
    padding: 0.65rem 0.85rem;
}

.pa-bal-kualitatif__kosong {
    font-size: 0.75rem;
    color: var(--hcis-text-subtle, #94a3b8);
    padding: 0.45rem 0;
}

@media (max-width: 768px) {
    .pa-bal-kartu__kepala {
        flex-wrap: wrap;
        padding: 0.85rem 1rem;
    }

    .pa-bal-kartu__angka {
        width: 100%;
        order: 3;
        padding-top: 0.5rem;
        border-top: 1px dashed rgba(226, 232, 240, 0.85);
    }

    .pa-bal-matriks thead {
        display: none;
    }

    .pa-bal-matriks, .pa-bal-matriks tbody, .pa-bal-matriks tr, .pa-bal-matriks td {
        display: block;
        width: 100%;
    }

    .pa-bal-matriks tr {
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: var(--hcis-radius-md, 10px);
        margin-bottom: 0.75rem;
        padding: 0.5rem 0.65rem;
        background: #ffffff;
    }

    .pa-bal-matriks td {
        border: 0;
        display: flex;
        gap: 0.5rem;
        align-items: center;
        justify-content: space-between;
        padding: 0.45rem 0.35rem;
    }

    .pa-bal-matriks td::before {
        content: attr(data-label);
        font-weight: 700;
        font-size: 0.6875rem;
        color: var(--hcis-text-muted, #64748b);
        flex: 0 0 85px;
    }

    .pa-bal-input--teks {
        min-width: 0;
    }
}
.pa-bal-tingkat { align-self: flex-start; padding: 0.05rem 0.45rem; border-radius: 999px; background: #ede9fe; color: #5b21b6; font-size: 0.66rem; font-weight: 700; }
</style>

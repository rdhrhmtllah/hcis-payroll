<template>
    <HcisDualPaneDialog
        :model-value="Boolean(kompetensi)"
        title="Tingkat Kompetensi"
        kicker="MASTER DATA · TINGKAT KOMPETENSI"
        :description="`${kompetensi?.Kode_Competency || ''} · ${kompetensi?.Nama_Competency || ''}`"
        icon="bar-chart-steps"
        sentiment="brand"
        size="xl"
        left-width="330px"
        left-tab-label="Daftar tingkat"
        left-tab-icon="list-ol"
        :left-tab-badge="baris.length ? String(baris.length) : ''"
        right-tab-label="Isi tingkat"
        right-tab-icon="pencil-square"
        :loading="memuat"
        sticky-footer
        @update:model-value="(v) => !v && tutup()"
    >
        <!-- KIRI: daftar tingkat -->
        <template #left>
            <div class="pa-tkt__kiri">
                <HcisAlert
                    variant="info"
                    appearance="soft"
                    size="sm"
                    icon="info-circle"
                    title="Cara kerja tingkat"
                    description="Tingkat 1 = paling dasar. Nomor mengikuti urutan (pakai panah untuk memindah). Tingkat yang dituntut tiap level jabatan dipilih di Matriks Kompetensi."
                />

                <HcisEmptyState
                    v-if="!baris.length"
                    title="Belum ada tingkat"
                    description="Tambah tingkat satu per satu, atau mulai dari 4 tingkat standar lalu ubah isinya."
                    icon="bar-chart-steps"
                    size="sm"
                    variant="dashed"
                    data-uji="tkt-kosong"
                >
                    <div class="pa-tkt__aksi-kosong">
                        <HcisButton label="Tambah tingkat pertama" variant="primary" size="sm" leading-icon="plus-lg" @click="tambah" />
                        <HcisButton label="Pakai 4 tingkat standar" variant="secondary" size="sm" leading-icon="magic" data-uji="tkt-standar" @click="pakaiStandar" />
                    </div>
                </HcisEmptyState>

                <ol v-else class="pa-tkt__daftar" role="listbox" aria-label="Daftar tingkat">
                    <li v-for="(t, i) in baris" :key="t.kunci">
                        <div
                            role="option"
                            tabindex="0"
                            :aria-selected="t.kunci === aktif"
                            :class="['pa-tkt__kartu', { 'is-aktif': t.kunci === aktif, 'is-galat': galatBaris(t) }]"
                            data-uji="tkt-baris"
                            @click="aktif = t.kunci"
                            @keydown.enter.prevent="aktif = t.kunci"
                        >
                            <span class="pa-tkt__nomor">{{ i + 1 }}</span>
                            <div class="pa-tkt__info">
                                <strong>{{ t.nama.trim() || 'Belum diberi nama' }}</strong>
                                <small :class="t.expected_behaviour.trim() ? 'is-ok' : 'is-kosong'">
                                    <HcisIcon :name="t.expected_behaviour.trim() ? 'check-circle-fill' : 'exclamation-circle'" size="2xs" />
                                    {{ t.expected_behaviour.trim() ? 'Expected Behaviour terisi' : 'Expected Behaviour kosong' }}
                                </small>
                                <HcisBadge v-if="t.dipakai_level" :label="`dipakai ${t.dipakai_level} level`" variant="brand" size="sm" />
                            </div>
                            <div class="pa-tkt__urut" @click.stop>
                                <HcisButton icon-only icon="chevron-up" size="xs" variant="ghost" :disabled="i === 0" title="Naikkan" :aria-label="`Naikkan tingkat ${i + 1}`" @click="pindah(i, -1)" />
                                <HcisButton icon-only icon="chevron-down" size="xs" variant="ghost" :disabled="i === baris.length - 1" title="Turunkan" :aria-label="`Turunkan tingkat ${i + 1}`" @click="pindah(i, 1)" />
                            </div>
                        </div>
                    </li>
                </ol>

                <HcisButton v-if="baris.length" label="Tambah tingkat" variant="secondary" size="sm" leading-icon="plus-lg" block data-uji="tkt-tambah" @click="tambah" />
            </div>
        </template>

        <!-- KANAN: isi tingkat terpilih -->
        <template #right>
            <HcisAlert v-if="galat" variant="danger" appearance="soft" size="sm" icon="x-octagon" :title="galat" class="mb-3" data-uji="tkt-galat" />
            <div v-if="terpilih" class="pa-tkt__kanan" data-uji="tkt-editor">
                <div class="pa-tkt__judul">
                    <span class="pa-tkt__nomor pa-tkt__nomor--besar">{{ nomorTerpilih }}</span>
                    <div>
                        <h4>Tingkat {{ nomorTerpilih }}</h4>
                        <small>{{ nomorTerpilih === 1 ? 'Tingkat paling dasar' : nomorTerpilih === baris.length ? 'Tingkat tertinggi saat ini' : `Di antara tingkat ${nomorTerpilih - 1} dan ${nomorTerpilih + 1}` }}</small>
                    </div>
                    <HcisBadge v-if="terpilih.dipakai_level" :label="`Dituntut di ${terpilih.dipakai_level} level`" variant="brand" size="sm" />
                </div>

                <HcisField
                    id="tkt-nama"
                    v-model="terpilih.nama"
                    label="Nama tingkat"
                    required
                    :max-length="150"
                    show-count
                    placeholder="Mis. Basic, Intermediate, Advanced, Expert"
                    :error="cobaSimpan && !terpilih.nama.trim() ? 'Nama tingkat wajib diisi.' : ''"
                    data-uji="tkt-nama"
                />

                <div class="pa-tkt__eb-kepala">
                    <span>Contoh cepat:</span>
                    <button v-for="n in SARAN_NAMA" :key="n" type="button" class="pa-tkt__saran" @click="terpilih.nama = n">{{ n }}</button>
                </div>

                <HcisTextarea
                    id="tkt-eb"
                    v-model="terpilih.expected_behaviour"
                    label="Expected Behaviour"
                    :rows="9"
                    :maxlength="4000"
                    auto-resize
                    placeholder="Tuliskan perilaku yang bisa diamati pada tingkat ini, satu perilaku per baris. Mis.&#10;• Mengurai masalah menjadi bagian-bagian kecil&#10;• Menggunakan data untuk menguji dugaan"
                    hint="Perilaku inilah yang tampil di Penilaian Tim, Balancing, dan laporan peserta untuk level yang menuntut tingkat ini."
                    data-uji="tkt-eb"
                />
                <div class="pa-tkt__eb-alat">
                    <HcisButton
                        v-if="nomorTerpilih > 1 && baris[nomorTerpilih - 2].expected_behaviour.trim()"
                        :label="`Salin dari tingkat ${nomorTerpilih - 1}`"
                        variant="ghost"
                        size="xs"
                        leading-icon="clipboard-plus"
                        data-uji="tkt-salin"
                        @click="salinDariSebelumnya"
                    />
                    <HcisButton
                        label="Sisipkan butir •"
                        variant="ghost"
                        size="xs"
                        leading-icon="list-ul"
                        @click="sisipkanButir"
                    />
                </div>

                <div class="pa-tkt__hapus">
                    <HcisButton
                        label="Hapus tingkat ini"
                        variant="danger-quiet"
                        size="sm"
                        leading-icon="trash3"
                        :disabled="Boolean(terpilih.dipakai_level)"
                        data-uji="tkt-hapus"
                        @click="hapus"
                    />
                    <small v-if="terpilih.dipakai_level">Masih dituntut di Matriks Kompetensi — ubah matriksnya dulu untuk bisa menghapus.</small>
                </div>
            </div>
            <HcisEmptyState
                v-else
                title="Pilih tingkat"
                description="Pilih tingkat di sebelah kiri untuk mengisi nama dan Expected Behaviour-nya."
                icon="hand-index"
                size="sm"
                variant="flat"
            />
        </template>

        <template #footer-start>
            <!-- Ringkas di kiri footer: peringatan lebar di sini mendorong tombol keluar layar (uji browser 10 Okt). -->
            <span v-if="tanyaBuang" class="pa-tkt__status is-tanya" role="alert" data-uji="tkt-status">
                <HcisIcon name="exclamation-triangle-fill" size="xs" />
                Belum disimpan — tekan lagi untuk membuang
            </span>
            <span v-else :class="['pa-tkt__status', { 'is-ubah': berubah }]" data-uji="tkt-status">
                <HcisIcon :name="berubah ? 'pencil-fill' : 'check2-circle'" size="xs" />
                {{ berubah ? 'Ada perubahan yang belum disimpan' : 'Semua perubahan tersimpan' }}
            </span>
        </template>
        <template #footer>
            <HcisButton :label="tanyaBuang ? 'Tutup tanpa simpan' : 'Tutup'" :variant="tanyaBuang ? 'danger-quiet' : 'secondary'" size="sm" :disabled="menyimpan" data-uji="tkt-tutup" @click="tutup" />
            <HcisButton
                label="Simpan tingkat"
                variant="primary"
                size="sm"
                leading-icon="check-lg"
                :loading="menyimpan"
                :disabled="memuat || menyimpan || !berubah"
                data-uji="tkt-simpan"
                @click="simpan"
            />
        </template>
    </HcisDualPaneDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import {
    HcisAlert, HcisBadge, HcisButton, HcisDualPaneDialog, HcisEmptyState, HcisField, HcisIcon, HcisTextarea, hcisMessage,
} from '@/components/ui';
import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaKompetensiTingkatDialog' });

/* Tingkat satu kompetensi (DDL-2026-175). Seluruh daftar disimpan sekaligus: baris ber-id
   diperbarui, baris baru ditambah, yang dihapus dinonaktifkan — kecuali masih dituntut
   Matriks Kompetensi, yang ditolak server. NOMOR TINGKAT = URUTAN di daftar; server
   menomori ulang dengan aman (lewat ruang negatif), jadi tukar urutan tidak menabrak indeks unik. */
const props = defineProps({
    kompetensi: { type: Object, default: null },
});
const emit = defineEmits(['tutup', 'tersimpan']);

const SARAN_NAMA = ['Basic', 'Intermediate', 'Advanced', 'Expert'];

const baris = ref([]);
const aktif = ref(null);
const memuat = ref(false);
const menyimpan = ref(false);
const galat = ref('');
const cobaSimpan = ref(false);
const tanyaBuang = ref(false);
const awal = ref('[]');
let kunci = 0;

const bentuk = (t) => ({ kunci: ++kunci, id: t.id ?? null, nama: t.nama ?? '', expected_behaviour: t.expected_behaviour ?? '', dipakai_level: t.dipakai_level ?? 0 });
const potret = () => JSON.stringify(baris.value.map((t) => [t.id, t.nama.trim(), t.expected_behaviour.trim()]));
const berubah = computed(() => potret() !== awal.value);
const terpilih = computed(() => baris.value.find((t) => t.kunci === aktif.value) || null);
const nomorTerpilih = computed(() => baris.value.findIndex((t) => t.kunci === aktif.value) + 1);
const galatBaris = (t) => cobaSimpan.value && !t.nama.trim();

function muatDari(daftar) {
    baris.value = [...(daftar || [])].sort((a, b) => Number(a.tingkat) - Number(b.tingkat)).map(bentuk);
    awal.value = potret();
    aktif.value = baris.value[0]?.kunci ?? null;
    cobaSimpan.value = false;
    tanyaBuang.value = false;
}

watch(() => props.kompetensi?.Id_PA_Competency, async (id) => {
    galat.value = '';
    muatDari([]);
    if (!id) return;
    memuat.value = true;
    try {
        muatDari(await paApi.tingkatKompetensi(id));
    } catch (e) {
        galat.value = e?.message || 'Gagal memuat tingkat.';
    } finally {
        memuat.value = false;
    }
}, { immediate: true });

watch(berubah, () => { tanyaBuang.value = false; });

function tambah() {
    const t = bentuk({});
    baris.value.push(t);
    aktif.value = t.kunci;
}
function pakaiStandar() {
    SARAN_NAMA.forEach((nama) => baris.value.push(bentuk({ nama })));
    aktif.value = baris.value[0].kunci;
}
function pindah(i, arah) {
    const j = i + arah;
    if (j < 0 || j >= baris.value.length) return;
    const salin = [...baris.value];
    [salin[i], salin[j]] = [salin[j], salin[i]];
    baris.value = salin;
}
function hapus() {
    const t = terpilih.value;
    if (!t || t.dipakai_level) return;
    if (t.expected_behaviour.trim() && !window.confirm(`Hapus tingkat ${nomorTerpilih.value} "${t.nama || 'tanpa nama'}" beserta Expected Behaviour-nya?`)) return;
    const i = baris.value.indexOf(t);
    baris.value.splice(i, 1);
    aktif.value = baris.value[Math.min(i, baris.value.length - 1)]?.kunci ?? null;
}
function salinDariSebelumnya() {
    const sebelum = baris.value[nomorTerpilih.value - 2];
    if (!sebelum || !terpilih.value) return;
    terpilih.value.expected_behaviour = terpilih.value.expected_behaviour.trim()
        ? `${terpilih.value.expected_behaviour.trim()}\n${sebelum.expected_behaviour.trim()}`
        : sebelum.expected_behaviour.trim();
}
function sisipkanButir() {
    if (!terpilih.value) return;
    const isi = terpilih.value.expected_behaviour;
    terpilih.value.expected_behaviour = `${isi}${isi && !isi.endsWith('\n') ? '\n' : ''}• `;
}

async function simpan() {
    galat.value = '';
    cobaSimpan.value = true;
    const kosong = baris.value.find((t) => !t.nama.trim());
    if (kosong) {
        aktif.value = kosong.kunci;
        galat.value = `Nama tingkat ${baris.value.indexOf(kosong) + 1} wajib diisi.`;
        return;
    }
    menyimpan.value = true;
    try {
        const hasil = await paApi.simpanTingkatKompetensi(props.kompetensi.Id_PA_Competency, baris.value.map((t, i) => ({
            id: t.id, tingkat: i + 1, nama: t.nama.trim(), expected_behaviour: t.expected_behaviour.trim() || null,
        })));
        const nomorAktif = nomorTerpilih.value;
        muatDari(hasil);
        aktif.value = baris.value[Math.max(0, nomorAktif - 1)]?.kunci ?? aktif.value;
        hcisMessage.success(`Tingkat ${props.kompetensi.Kode_Competency} tersimpan.`, 'Tingkat Kompetensi');
        emit('tersimpan', hasil);
    } catch (e) {
        galat.value = e?.message || 'Tingkat gagal disimpan.';
    } finally {
        menyimpan.value = false;
    }
}

function tutup() {
    if (menyimpan.value) return;
    if (berubah.value && !tanyaBuang.value) {
        tanyaBuang.value = true;
        return;
    }
    emit('tutup');
}
</script>

<style scoped>
.pa-tkt__kiri, .pa-tkt__kanan { display: flex; flex-direction: column; gap: 0.85rem; min-width: 0; }
.pa-tkt__daftar { display: flex; flex-direction: column; gap: 0.45rem; margin: 0; padding: 0; list-style: none; }
.pa-tkt__kartu { display: flex; align-items: center; gap: 0.65rem; padding: 0.6rem 0.65rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s; }
.pa-tkt__kartu:hover { border-color: #c7d2fe; }
.pa-tkt__kartu.is-aktif { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); background: #f5f7ff; }
.pa-tkt__kartu.is-galat { border-color: #f87171; }
.pa-tkt__kartu:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; }
.pa-tkt__nomor { flex: 0 0 1.9rem; height: 1.9rem; display: grid; place-items: center; border-radius: 999px; background: #eef2ff; color: #4338ca; font-weight: 700; font-size: 0.85rem; }
.pa-tkt__nomor--besar { flex-basis: 2.6rem; height: 2.6rem; font-size: 1.1rem; background: #4338ca; color: #fff; }
.pa-tkt__info { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; flex: 1; }
.pa-tkt__info strong { font-size: 0.86rem; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pa-tkt__info small { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.72rem; }
.pa-tkt__info small.is-ok { color: #15803d; }
.pa-tkt__info small.is-kosong { color: #b45309; }
.pa-tkt__info :deep(.hcis-badge) { align-self: flex-start; }
.pa-tkt__urut { display: flex; flex-direction: column; }
.pa-tkt__aksi-kosong { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; }
.pa-tkt__judul { display: flex; align-items: center; gap: 0.75rem; }
.pa-tkt__judul h4 { margin: 0; font-size: 1rem; font-weight: 700; color: #0f172a; }
.pa-tkt__judul small { color: #64748b; font-size: 0.78rem; }
.pa-tkt__judul :deep(.hcis-badge) { margin-left: auto; }
.pa-tkt__eb-kepala { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; margin-top: -0.4rem; font-size: 0.75rem; color: #64748b; }
.pa-tkt__saran { padding: 0.1rem 0.55rem; border: 1px solid #e2e8f0; border-radius: 999px; background: #fff; color: #4338ca; font-size: 0.74rem; cursor: pointer; }
.pa-tkt__saran:hover { background: #eef2ff; }
.pa-tkt__eb-alat { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: -0.4rem; }
.pa-tkt__hapus { display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; padding-top: 0.75rem; border-top: 1px dashed #e2e8f0; }
.pa-tkt__hapus small { color: #64748b; font-size: 0.75rem; }
.pa-tkt__status { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; color: #15803d; }
.pa-tkt__status.is-ubah { color: #b45309; }
.pa-tkt__status.is-tanya { color: #b91c1c; font-weight: 600; }
</style>

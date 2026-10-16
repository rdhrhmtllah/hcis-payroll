<template>
    <HcisDialog
        :model-value="modelValue"
        :title="data?.peserta?.nama || 'Kesimpulan kualitatif'"
        kicker="KESIMPULAN KUALITATIF"
        :description="subjudul"
        icon="chat-quote-fill"
        sentiment="purple"
        variant="glass"
        size="xl"
        sticky-footer
        data-uji="ruang-kerja-kualitatif"
        @update:model-value="tutup"
    >
        <p v-if="memuat" class="pa-rkk__info">Memuat jawaban penilai…</p>
        <p v-else-if="galat" class="pa-rkk__info is-galat">{{ galat }}</p>
        <template v-else-if="data">
            <div v-if="data.terkunci" class="pa-rkk__kunci" data-uji="rkk-terkunci">
                <HcisIcon name="lock-fill" size="2xs" /> Hasil orang ini sudah TERBIT; kesimpulannya hanya bisa dibaca.
            </div>
            <p v-if="!data.kategori.length" class="pa-rkk__info">Tidak ada jawaban kualitatif untuk peserta ini.</p>

            <section v-for="k in data.kategori" :key="k.kode" class="pa-rkk__kategori" :data-uji="`rkk-${k.kode}`">
                <header class="pa-rkk__kepala">
                    <div>
                        <h4>{{ k.nama }}</h4>
                        <p v-if="k.deskripsi">{{ k.deskripsi }}</p>
                    </div>
                    <span class="pa-rkk__jumlah">{{ k.jawaban.length }} jawaban</span>
                </header>

                <div v-if="k.pertanyaan?.length" class="pa-rkk__soal" data-uji="rkk-soal">
                    <span class="pa-rkk__soal-label">Soal</span>
                    <ol>
                        <li v-for="(q, qi) in k.pertanyaan" :key="qi">{{ q }}</li>
                    </ol>
                </div>

                <div class="pa-rkk__isi">
                    <ul class="pa-rkk__jawaban" role="list">
                        <li v-for="(j, i) in k.jawaban" :key="i">
                            <div class="pa-rkk__penilai">
                                <span class="pa-rkk__chip" :class="`is-${String(j.kategori_penilai || '').toLowerCase()}`">{{ j.nama_kategori || j.kategori_penilai }}</span>
                                <b>{{ j.penilai }}</b>
                                <button
                                    v-if="!data.terkunci"
                                    type="button"
                                    class="pa-rkk__kutip"
                                    title="Tambahkan jawaban ini ke kesimpulan"
                                    @click="kutip(k.kode, j)"
                                >
                                    <HcisIcon name="quote" size="2xs" /> Kutip
                                </button>
                            </div>
                            <!-- Sudah dibersihkan server (PaTeksKaya::bersihkan). -->
                            <div class="pa-rkk__teks" v-html="j.html" />
                        </li>
                        <li v-if="!k.jawaban.length" class="pa-rkk__kosong">Tidak ada jawaban penilai untuk kategori ini.</li>
                    </ul>

                    <div class="pa-rkk__editor">
                        <span class="pa-rkk__label">Kesimpulan admin</span>
                        <HcisRichText
                            :id="`rkk-catatan-${k.kode}`"
                            v-model="catatan[k.kode]"
                            :placeholder="`Rangkum jawaban penilai tentang ${k.nama}…`"
                            :disabled="data.terkunci || menyimpan"
                            min-height="150px"
                            max-height="320px"
                        />
                    </div>
                </div>
            </section>
        </template>

        <template #footer>
            <div class="pa-rkk__kaki">
                <div class="pa-rkk__nav">
                    <HcisButton
                        label="Sebelumnya"
                        variant="ghost"
                        size="sm"
                        leading-icon="chevron-left"
                        :disabled="!bisaSebelum || menyimpan || berubah"
                        :title="berubah ? 'Simpan dulu perubahan di orang ini' : ''"
                        data-uji="rkk-sebelum"
                        @click="emit('navigasi', 'sebelum')"
                    />
                    <span v-if="posisi" class="pa-rkk__posisi">{{ posisi }}</span>
                    <HcisButton
                        label="Berikutnya"
                        variant="ghost"
                        size="sm"
                        trailing-icon="chevron-right"
                        :disabled="!bisaBerikut || menyimpan || berubah"
                        :title="berubah ? 'Simpan dulu perubahan di orang ini' : ''"
                        data-uji="rkk-berikut"
                        @click="emit('navigasi', 'berikut')"
                    />
                </div>
                <div class="pa-rkk__aksi">
                    <span v-if="berubah" class="pa-rkk__belum-simpan">Belum disimpan</span>
                    <HcisButton label="Tutup" variant="secondary" size="sm" :disabled="menyimpan" @click="tutup(false)" />
                    <HcisButton
                        v-if="data && !data.terkunci"
                        label="Simpan"
                        variant="secondary"
                        size="sm"
                        leading-icon="check2"
                        :loading="menyimpan && !lanjutSesudahSimpan"
                        :disabled="!berubah || menyimpan"
                        data-uji="rkk-simpan"
                        @click="simpan(false)"
                    />
                    <HcisButton
                        v-if="data && !data.terkunci && bisaBerikut"
                        label="Simpan & berikutnya"
                        variant="primary"
                        size="sm"
                        trailing-icon="arrow-right"
                        :loading="menyimpan && lanjutSesudahSimpan"
                        :disabled="menyimpan"
                        data-uji="rkk-simpan-berikut"
                        @click="simpan(true)"
                    />
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { HcisButton, HcisDialog, HcisIcon, HcisRichText, hcisMessage } from '@/components/ui';
import paApi from '../../../api/paApi.js';

/* Ruang kerja satu peserta di tahap Kesimpulan Kualitatif: jawaban penilai di kiri,
   kesimpulan admin per kategori di kanan. Disimpan lewat PUT /participants/{id}/catatan
   yang menimpa SELURUH catatan admin orang itu, jadi semua kategori selalu dikirim. */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    participantId: { type: Number, default: null },
    posisi: { type: String, default: '' },
    bisaSebelum: { type: Boolean, default: false },
    bisaBerikut: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'tersimpan', 'navigasi']);

const data = ref(null);
const memuat = ref(false);
const galat = ref('');
const menyimpan = ref(false);
const lanjutSesudahSimpan = ref(false);
const catatan = reactive({});
const awal = ref({});
let urutan = 0;

const subjudul = computed(() => {
    const p = data.value?.peserta;
    return p ? [p.kode_karyawan, p.jabatan, p.divisi, p.level].filter(Boolean).join(' · ') : '';
});
const normal = (v) => String(v || '').replace(/<p><br><\/p>/g, '').trim();
const berubah = computed(() => (data.value?.kategori || []).some((k) => normal(catatan[k.kode]) !== normal(awal.value[k.kode])));

async function muat() {
    const pid = props.participantId;
    if (!pid) return;
    const nomor = ++urutan;
    memuat.value = true;
    galat.value = '';
    try {
        const r = await paApi.ruangKerjaKualitatif(pid);
        if (nomor !== urutan) return;
        data.value = r?.result ?? r;
        Object.keys(catatan).forEach((k) => delete catatan[k]);
        (data.value?.kategori || []).forEach((k) => { catatan[k.kode] = k.catatan || ''; });
        awal.value = { ...catatan };
    } catch (e) {
        if (nomor !== urutan) return;
        data.value = null;
        galat.value = e?.message || 'Gagal memuat jawaban penilai.';
    } finally {
        if (nomor === urutan) memuat.value = false;
    }
}

const escape = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

function teksDari(html) {
    const el = document.createElement('div');
    el.innerHTML = html || '';
    return (el.textContent || '').replace(/\s+/g, ' ').trim();
}

/* Format kutipan sama dengan dialog hasil, supaya catatan dari dua layar terbaca seragam. */
function kutip(kode, j) {
    const kutipan = `<blockquote><strong>Kutipan ${escape(j.penilai || 'Penilai')} (${escape(j.nama_kategori || j.kategori_penilai || 'Evaluator')}):</strong> <em>&ldquo;${escape(teksDari(j.html))}&rdquo;</em></blockquote>`;
    const kini = normal(catatan[kode]);
    catatan[kode] = kini ? `${kini}<p><br></p>${kutipan}` : kutipan;
}

async function simpan(lanjut) {
    if (!data.value || data.value.terkunci || menyimpan.value) return;
    lanjutSesudahSimpan.value = lanjut;

    if (!berubah.value) {
        if (lanjut) emit('navigasi', 'berikut');
        return;
    }

    menyimpan.value = true;
    try {
        const muatan = {};
        data.value.kategori.forEach((k) => {
            const isi = normal(catatan[k.kode]);
            muatan[k.kode] = isi ? [isi] : [''];
        });
        await paApi.simpanCatatanHasil(props.participantId, muatan);
        awal.value = { ...catatan };
        hcisMessage.success(`Kesimpulan ${data.value.peserta.nama} tersimpan.`, 'Tersimpan');
        emit('tersimpan', props.participantId);
        if (lanjut) emit('navigasi', 'berikut');
    } catch (e) {
        hcisMessage.error(e?.message || 'Kesimpulan gagal disimpan.', 'Gagal menyimpan');
    } finally {
        menyimpan.value = false;
        lanjutSesudahSimpan.value = false;
    }
}

function tutup(v) {
    if (v === true) return;
    if (berubah.value && !window.confirm('Ada kesimpulan yang belum disimpan. Tutup tanpa menyimpan?')) return;
    emit('update:modelValue', false);
}

watch(() => [props.modelValue, props.participantId], ([buka]) => {
    if (buka) muat();
}, { immediate: true });
</script>

<style scoped>
.pa-rkk__info { margin: 0; padding: 1rem; font-size: 0.85rem; color: #64748b; }
.pa-rkk__info.is-galat { color: #b91c1c; }
.pa-rkk__kunci { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.85rem; padding: 0.5rem 0.75rem; border-radius: 10px; background: #fef3c7; font-size: 0.8rem; color: #92400e; }
.pa-rkk__kategori { margin-bottom: 1rem; border: 1px solid #ede9fe; border-radius: 14px; background: #fff; overflow: hidden; }
.pa-rkk__kepala { display: flex; justify-content: space-between; gap: 0.75rem; padding: 0.7rem 0.9rem; background: #faf5ff; border-bottom: 1px solid #ede9fe; }
.pa-rkk__soal { display: flex; gap: 0.6rem; align-items: flex-start; padding: 0.55rem 0.9rem; background: #fffbeb; border-bottom: 1px solid #fde68a; font-size: 0.8rem; color: #78350f; }
.pa-rkk__soal-label { flex: 0 0 auto; padding: 0.05rem 0.45rem; border-radius: 999px; background: #f59e0b; color: #fff; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; }
.pa-rkk__soal ol { margin: 0; padding-left: 1.1rem; }
.pa-rkk__soal ol:has(> li:only-child) { padding-left: 0; list-style: none; }
.pa-rkk__kepala h4 { margin: 0; font-size: 0.92rem; color: #4c1d95; }
.pa-rkk__kepala p { margin: 0.15rem 0 0; font-size: 0.74rem; color: #6b7280; }
.pa-rkk__jumlah { flex: none; align-self: center; padding: 0.1rem 0.55rem; border-radius: 999px; background: #ede9fe; font-size: 0.7rem; font-weight: 700; color: #5b21b6; }
.pa-rkk__isi { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 0.9rem; padding: 0.85rem 0.9rem; }
.pa-rkk__jawaban { display: flex; flex-direction: column; gap: 0.55rem; max-height: 380px; margin: 0; padding: 0 0.25rem 0 0; overflow-y: auto; list-style: none; }
.pa-rkk__jawaban > li { padding: 0.55rem 0.65rem; border: 1px solid #f1f5f9; border-radius: 10px; background: #f8fafc; }
.pa-rkk__penilai { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; margin-bottom: 0.3rem; font-size: 0.74rem; color: #334155; }
.pa-rkk__chip { padding: 0 0.45rem; border-radius: 999px; background: #e2e8f0; font-size: 0.66rem; font-weight: 700; color: #334155; }
.pa-rkk__chip.is-atasan { background: #dbeafe; color: #1d4ed8; }
.pa-rkk__chip.is-peer { background: #dcfce7; color: #15803d; }
.pa-rkk__chip.is-bawahan { background: #fef3c7; color: #b45309; }
.pa-rkk__chip.is-self { background: #f3e8ff; color: #7e22ce; }
.pa-rkk__kutip { margin-left: auto; display: inline-flex; align-items: center; gap: 0.2rem; padding: 0.05rem 0.45rem; border: 1px solid #ddd6fe; border-radius: 999px; background: #fff; font-size: 0.68rem; font-weight: 700; color: #6d28d9; cursor: pointer; }
.pa-rkk__kutip:hover { background: #f5f3ff; }
.pa-rkk__teks { font-size: 0.8rem; line-height: 1.5; color: #1e293b; overflow-wrap: anywhere; }
.pa-rkk__teks :deep(p) { margin: 0 0 0.3rem; }
.pa-rkk__kosong { font-size: 0.78rem; font-style: italic; color: #94a3b8; }
.pa-rkk__editor { display: flex; flex-direction: column; gap: 0.35rem; min-width: 0; }
.pa-rkk__label { font-size: 0.68rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #6b21a8; }
.pa-rkk__kaki { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.6rem; width: 100%; }
.pa-rkk__nav, .pa-rkk__aksi { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; }
.pa-rkk__posisi { font-size: 0.76rem; font-weight: 700; color: #475569; }
.pa-rkk__belum-simpan { font-size: 0.72rem; font-weight: 700; color: #b45309; }
@media (max-width: 860px) {
    .pa-rkk__isi { grid-template-columns: minmax(0, 1fr); }
    .pa-rkk__jawaban { max-height: 260px; }
}
</style>

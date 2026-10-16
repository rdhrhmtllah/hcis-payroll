<template>
    <!--
     | PERUBAHAN SEJAK PESERTA / PENUGASAN DIBENTUK.
     |
     | Menjalankan ulang Langkah 4 tidak pernah menyentuh penugasan yang sudah ada. Akibatnya
     | ada keadaan yang hanya bisa dibereskan admin — karyawan baru yang belum diterapkan,
     | peserta tanpa penugasan, Level PA yang tertahan, peserta yang resign — dan tanpa panel
     | ini keempatnya tidak terlihat di mana pun. Tiap baris membawa tindakannya sendiri.
     -->
    <section v-if="tampil" class="pa-perubahan" :class="{ 'is-polos': polos }" data-uji="perubahan-peserta" aria-live="polite">
        <slot name="judul" />
        <p v-if="!baris.length && !polos" class="pa-perubahan__kosong" data-uji="perubahan-kosong">
            <HcisIcon name="check-circle-fill" size="xs" class="pa-perubahan__ikon-ok" />
            Tidak ada perubahan sejak penugasan dibentuk.
        </p>

        <ul v-else-if="baris.length" class="pa-perubahan__daftar">
            <li v-for="b in baris" :key="b.kunci" class="pa-perubahan__baris" :data-uji="`perubahan-${b.kunci}`">
                <HcisIcon v-if="!polos" :name="b.ikon" size="xs" class="pa-perubahan__ikon" />
                <div class="pa-perubahan__isi">
                    <div v-if="polos" class="pa-tugas__tag-line">
                        <span class="pa-tugas-label" :class="b.kunci === 'belum-berpenugasan' ? 'is-wajib' : 'is-saran'">
                            {{ b.kunci === 'belum-berpenugasan' ? 'Wajib' : 'Disarankan' }}
                        </span>
                        <strong class="pa-perubahan__judul">{{ b.judul }}</strong>
                    </div>
                    <strong v-else class="pa-perubahan__judul">{{ b.judul }}</strong>
                    <span class="pa-perubahan__keterangan">{{ b.keterangan }}</span>
                    <details v-if="b.orang?.length" class="pa-perubahan__rincian">
                        <summary>Lihat {{ b.orang.length }} orang</summary>
                        <ul class="pa-perubahan__orang-list">
                            <li v-for="o in b.orang" :key="o.participant_id">
                                <b>{{ o.nama }}</b> ({{ o.kode_karyawan }}) — {{ o.teks }}
                            </li>
                        </ul>
                    </details>
                </div>
                <HcisButton
                    v-if="b.aksi"
                    :label="b.aksi.label"
                    :variant="b.aksi.utama ? 'primary' : 'secondary'"
                    size="sm"
                    :leading-icon="b.aksi.ikon"
                    :loading="sibuk === b.kunci"
                    :disabled="Boolean(sibuk) || Boolean(b.aksi.nonaktif)"
                    :data-uji="`perubahan-aksi-${b.kunci}`"
                    @click="b.aksi.jalan()"
                />
            </li>
        </ul>

        <p v-if="pesan" class="pa-perubahan__pesan" :class="{ 'is-galat': pesanGalat }" role="status">{{ pesan }}</p>

        <HcisDialog v-model="konfirmasi.terbuka" :title="konfirmasi.judul" size="sm">
            <p class="pa-perubahan__konfirmasi" data-uji="perubahan-konfirmasi">{{ konfirmasi.teks }}</p>
            <template #footer>
                <HcisButton label="Batal" variant="secondary" size="sm" @click="konfirmasi.terbuka = false" />
                <HcisButton
                    :label="konfirmasi.label"
                    variant="primary"
                    size="sm"
                    data-uji="perubahan-konfirmasi-ya"
                    @click="setujui"
                />
            </template>
        </HcisDialog>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { HcisButton, HcisDialog, HcisIcon } from '@/components/ui';
import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaPerubahanPeserta' });

const props = defineProps({
    cycleId: { type: [Number, String], required: true },
    /* PESERTA (Langkah 4) atau PENUGASAN (Langkah 5): menentukan baris dan tindakan mana yang tampil. */
    langkah: { type: String, default: 'PENUGASAN' },
    dapatDisunting: { type: Boolean, default: false },
    bolehKecualikan: { type: Boolean, default: false },
    /* Dipasang di dalam daftar tugas Langkah 5: tanpa bingkai, tanpa kalimat "tidak ada
       perubahan", dan tiap baris berlabel Wajib/Disarankan seperti baris lain di daftar itu.
       Hanya peserta tanpa penugasan yang menahan penguncian. */
    polos: { type: Boolean, default: false },
    /* Pembentukan penugasan sedang berjalan atau datanya sedang dibaca ulang. */
    sedangMembentuk: { type: Boolean, default: false },
});

const emit = defineEmits(['berubah', 'lengkapi', 'ke-langkah-4', 'ke-langkah-5', 'jumlah', 'revisi']);

const data = ref(null);
const sibuk = ref('');
const pesan = ref('');
const pesanGalat = ref(false);
const konfirmasi = reactive({ terbuka: false, judul: '', teks: '', label: '', jalan: null });

const angka = (n) => Number(n || 0).toLocaleString('id-ID');

async function muat() {
    try {
        const r = await paApi.perubahanPeserta(props.cycleId);
        data.value = r?.result ?? r?.data ?? r;
    } catch {
        data.value = null;
    }
}

const diPenugasan = computed(() => props.langkah === 'PENUGASAN');

const baris = computed(() => {
    const d = data.value;
    if (!d) return [];
    const hasil = [];

    const belum = d.belum_diterapkan || {};
    if (diPenugasan.value && (belum.karyawan_baru || belum.level_berubah)) {
        const bagian = [];
        if (belum.karyawan_baru) bagian.push(`${angka(belum.karyawan_baru)} karyawan baru`);
        if (belum.level_berubah) bagian.push(`${angka(belum.level_berubah)} peserta berubah Level PA`);
        hasil.push({
            kunci: 'belum-diterapkan',
            ikon: 'arrow-repeat',
            judul: `${bagian.join(' dan ')} belum diterapkan`,
            keterangan: 'Jalankan ulang penyiapan di Langkah 4 supaya masuk ke daftar peserta.',
            aksi: d.dapat_ditindak && props.dapatDisunting
                ? { label: 'Buka Langkah 4', ikon: 'arrow-left', jalan: () => emit('ke-langkah-4') }
                : null,
        });
    }

    if (d.ada_penugasan && d.belum_berpenugasan > 0) {
        hasil.push({
            kunci: 'belum-berpenugasan',
            ikon: 'person-plus',
            judul: `${angka(d.belum_berpenugasan)} peserta belum punya penugasan`,
            keterangan: diPenugasan.value
                ? 'Penugasan yang sudah ada tidak disentuh; yang dibentuk hanya untuk peserta ini.'
                : 'Penugasannya dibentuk di Langkah 5.',
            aksi: !(d.dapat_ditindak && props.dapatDisunting)
                ? null
                : diPenugasan.value
                    ? (props.sedangMembentuk
                        /* Selama pembentukan berjalan (dan sesudahnya, sampai datanya dibaca
                           ulang) tombolnya TIDAK ditawarkan lagi — UAT 13 Okt: admin menekannya
                           dua kali karena barisnya masih tampil beberapa detik sesudah selesai. */
                        ? { label: 'Sedang dibentuk…', ikon: 'arrow-repeat', utama: true, nonaktif: true, jalan: () => {} }
                        : { label: 'Lengkapi penugasan', ikon: 'magic', utama: true, jalan: () => emit('lengkapi') })
                    : { label: 'Ke Langkah 5', ikon: 'arrow-right', jalan: () => emit('ke-langkah-5') },
        });
    }

    const tertahan = d.level_tertahan || [];
    if (tertahan.length) {
        const bisa = tertahan.filter((t) => t.bisa_disegarkan);
        hasil.push({
            kunci: 'level-tertahan',
            ikon: 'person-exclamation',
            judul: `${angka(tertahan.length)} peserta berubah Level PA tetapi tertahan`,
            keterangan: !props.dapatDisunting
                ? 'Levelnya tidak diubah karena siklus sudah dikunci dan sudah berpenugasan. Batalkan penugasannya (yang belum diisi) melalui Revisi Peserta supaya level dan penugasan mengikuti level baru.'
                : 'Levelnya tidak diubah karena sudah berpenugasan. Batalkan penugasannya (yang belum diisi) supaya level dan penugasannya mengikuti level baru.',
            orang: tertahan.map((t) => ({
                ...t,
                teks: `${t.nama_level_sekarang || '–'} → ${t.nama_level_seharusnya || '–'} · ${t.penugasan_aktif} penugasan`
                    + (t.bisa_disegarkan ? '' : ` · ${t.penugasan_diisi ? 'sudah ada yang diisi' : t.sebab}`),
            })),
            aksi: bisa.length && d.dapat_ditindak && props.dapatDisunting
                ? {
                    label: `Batalkan penugasan & segarkan level (${bisa.length})`,
                    ikon: 'arrow-clockwise',
                    jalan: () => tanya({
                        judul: 'Segarkan Level PA?',
                        teks: `${bisa.reduce((n, t) => n + t.penugasan_aktif, 0)} penugasan milik ${bisa.length} peserta dibatalkan (belum ada yang diisi), `
                            + 'lalu levelnya disegarkan. Sesudahnya bentuk penugasan barunya dengan "Lengkapi penugasan".',
                        label: 'Ya, Batalkan & Segarkan',
                        jalan: () => segarkan(bisa.map((t) => t.participant_id)),
                    }),
                }
                : (!props.dapatDisunting && bisa.length
                    ? {
                        label: 'Revisi Peserta',
                        ikon: 'person-gear',
                        jalan: () => emit('revisi', tertahan[0]),
                    }
                    : null),
        });
    }

    const resign = d.resign || [];
    if (resign.length) {
        const diisi = resign.filter((r) => r.penugasan_diisi > 0).length;
        hasil.push({
            kunci: 'resign',
            ikon: 'person-x',
            judul: `${angka(resign.length)} peserta diikutkan sudah resign atau tidak aktif`,
            keterangan: 'Menjalankan ulang penyiapan tidak mengeluarkan mereka. Kecualikan supaya tidak dinilai.',
            orang: resign.map((r) => ({ ...r, teks: `${r.keterangan} · ${r.penugasan_aktif} penugasan${r.penugasan_diisi ? `, ${r.penugasan_diisi} sudah diisi` : ''}` })),
            aksi: props.bolehKecualikan
                ? {
                    label: `Kecualikan yang resign (${resign.length})`,
                    ikon: 'person-dash',
                    jalan: () => tanya({
                        judul: 'Kecualikan peserta yang resign?',
                        teks: `${resign.length} peserta dikecualikan dan penugasannya yang belum diisi dibatalkan.`
                            + (diisi ? ` Penilaian yang sudah diisi (${diisi} orang) tetap tercatat.` : ''),
                        label: 'Ya, Kecualikan',
                        jalan: () => kecualikan(resign),
                    }),
                }
                : null,
        });
    }

    return hasil;
});

/* Langkah 5: selalu tampil begitu ada penugasan (juga untuk menyatakan "tidak ada perubahan").
   Langkah 4: hanya bila ada yang perlu ditindak. */
const tampil = computed(() => {
    if (!data.value) return false;
    return diPenugasan.value ? data.value.ada_penugasan : baris.value.length > 0;
});

/* Dilaporkan ke induk supaya judul daftar tugas menghitung baris ini juga. */
watch(
    () => ({ total: baris.value.length, wajib: baris.value.filter((b) => b.kunci === 'belum-berpenugasan').length }),
    (v) => emit('jumlah', v),
    { immediate: true },
);

function tanya(k) {
    Object.assign(konfirmasi, k, { terbuka: true });
}

async function setujui() {
    konfirmasi.terbuka = false;
    await konfirmasi.jalan?.();
}

async function jalankan(kunci, kerja) {
    sibuk.value = kunci;
    pesan.value = '';
    pesanGalat.value = false;
    try {
        pesan.value = await kerja();
        await muat();
        emit('berubah');
    } catch (e) {
        pesan.value = e?.message || 'Gagal menjalankan tindakan.';
        pesanGalat.value = true;
    } finally {
        sibuk.value = '';
    }
}

function segarkan(ids) {
    return jalankan('level-tertahan', async () => {
        const r = await paApi.segarkanLevelTertahan(props.cycleId, ids);
        const h = r?.result ?? r?.data ?? r;
        return `${h?.disegarkan ?? 0} peserta disegarkan, ${h?.penugasan_dibatalkan ?? 0} penugasan dibatalkan.`
            + (h?.dilewati?.length ? ` Dilewati: ${h.dilewati.join(' ')}` : '');
    });
}

function kecualikan(resign) {
    return jalankan('resign', async () => {
        const orang = resign.map((r) => ({ kode_perusahaan: r.kode_perusahaan, kode_karyawan: r.kode_karyawan }));
        const r = await paApi.tambahPraKecuali(props.cycleId, orang, 'Resign / tidak aktif sesudah peserta dibentuk.');
        const h = r?.result ?? r?.data ?? r;
        return `${h?.peserta_dikecualikan ?? resign.length} peserta dikecualikan.`;
    });
}

onMounted(muat);
defineExpose({ muat });
</script>

<style scoped>
/* Polos: status dibaca dari ikon dan teks, tanpa aksen warna. */
.pa-perubahan {
    border: 1px solid var(--hcis-color-border, #e2e8f0);
    border-radius: var(--hcis-radius-xl, 16px);
    background: var(--hcis-color-surface, #fff);
    padding: 0.9rem 1.1rem;
}

.pa-perubahan.is-polos {
    border: 0;
    border-radius: 0;
    background: transparent;
    padding: 0;
}

.pa-tugas-label {
    display: block;
    font-size: 0.6875rem !important;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.pa-tugas-label.is-wajib {
    color: #b91c1c;
}

.pa-tugas-label.is-saran {
    color: #64748b;
}

.pa-perubahan__kosong {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 0.875rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-perubahan__ikon-ok {
    color: var(--hcis-color-success-600, #16a34a);
}

.pa-perubahan__daftar {
    list-style: none;
    margin: 0;
    padding: 0;
}

.pa-perubahan__baris {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 0.75rem;
    align-items: start;
    padding: 0.75rem 0;
}

.pa-perubahan.is-polos .pa-perubahan__baris {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    padding: 0.85rem 1.1rem;
    background: #fffdfa;
    border: 1px solid #fed7aa;
    border-left: 4px solid #f97316;
    border-radius: 10px;
    margin-top: 0.65rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.pa-perubahan__baris + .pa-perubahan__baris {
    border-top: 1px solid var(--hcis-color-border, #e2e8f0);
}

.pa-perubahan.is-polos .pa-perubahan__baris + .pa-perubahan__baris {
    border-top: 1px solid #fed7aa;
}

.pa-perubahan__ikon {
    margin-top: 0.2rem;
    color: var(--hcis-color-text-muted, #64748b);
}

.pa-perubahan__isi {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.875rem;
    color: var(--hcis-color-text-main, #0f172a);
    flex: 1 1 18rem;
    min-width: 0;
}

.pa-perubahan__judul {
    color: #0f172a;
    font-size: 0.92rem;
    font-weight: 700;
}

.pa-perubahan__keterangan {
    color: #475569;
    font-size: 0.82rem;
    line-height: 1.45;
}

.pa-perubahan__rincian {
    margin-top: 0.25rem;
}

.pa-perubahan__rincian summary {
    cursor: pointer;
    font-size: 0.78rem;
    font-weight: 650;
    color: #4f46e5;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    user-select: none;
    transition: color 0.15s ease;
}

.pa-perubahan__rincian summary:hover {
    color: #3730a3;
    text-decoration: underline;
}

.pa-perubahan__orang-list {
    margin: 0.45rem 0 0;
    padding: 0.5rem 0.85rem;
    list-style: none;
    font-size: 0.8rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.pa-perubahan__orang-list li {
    color: #334155;
    line-height: 1.4;
}

.pa-perubahan__pesan {
    margin: 0.5rem 0 0;
    font-size: 0.8rem;
    color: var(--hcis-color-text-main, #0f172a);
}

.pa-perubahan__pesan.is-galat {
    color: var(--hcis-color-danger-600, #dc2626);
}

.pa-perubahan__konfirmasi {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

@media (max-width: 640px) {
    .pa-perubahan__baris {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .pa-perubahan.is-polos .pa-perubahan__baris {
        flex-direction: column;
        align-items: stretch;
    }

    .pa-perubahan__baris > :deep(.hcis-btn) {
        width: 100%;
    }
}
</style>

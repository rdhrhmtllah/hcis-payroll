<template>
    <HcisCard
        id="pa-lr-section-kompetensi"
        title="Kekuatan & Area Pengembangan Tim"
        description="Rata-rata nilai tim per kompetensi. Klik kompetensi untuk melihat arti, perilaku yang diharapkan, dan nilai tiap anggota."
        icon="stars"
        sentiment="brand"
        variant="glass"
        pattern="grid"
        :size="ringkas ? 'sm' : 'md'"
        class="pa-lkt"
        data-uji="kompetensi-tim"
    >
        <template v-if="kompetensi.length">
            <!-- Ringkasan posisi terhadap KKM + legenda cara membaca -->
            <div class="pa-lkt__atas">
                <div class="pa-lkt__saring" role="tablist" aria-label="Saring kompetensi">
                    <button
                        v-for="f in saringan"
                        :key="f.kunci"
                        type="button"
                        role="tab"
                        class="pa-lkt__chip"
                        :class="[`is-${f.kunci}`, { 'is-aktif': aktif === f.kunci }]"
                        :aria-selected="aktif === f.kunci ? 'true' : 'false'"
                        :data-uji="`saring-${f.kunci}`"
                        @click="pilihSaringan(f.kunci)"
                    >
                        <HcisIcon :name="f.ikon" size="2xs" />
                        {{ f.label }} <b>{{ f.jumlah }}</b>
                    </button>
                </div>
                <div class="pa-lkt__legenda" aria-hidden="true">
                    <span><i class="is-bar" /> Rata-rata tim</span>
                    <span><i class="is-kkm" /> KKM</span>
                </div>
            </div>

            <ul class="pa-lkt__daftar" role="list">
                <li v-for="(k, i) in tampil" :key="k.kode_kompetensi">
                    <button
                        type="button"
                        class="pa-lkt__baris"
                        :class="`is-${status(k)}`"
                        :title="`Lihat detail ${k.nama_kompetensi}`"
                        :data-uji="`komp-${status(k)}`"
                        @click="buka(k)"
                    >
                        <span class="pa-lkt__peringkat">{{ peringkat(k, i) }}</span>
                        <span class="pa-lkt__nama">
                            <b>{{ k.nama_kompetensi }}</b>
                            <small>{{ k.nama_type || 'Kompetensi' }} · {{ k.jumlah_orang }} orang</small>
                        </span>
                        <span class="pa-lkt__bar">
                            <i :style="{ width: `${batas(k.rata_rata)}%` }" />
                            <span v-if="adaKkm(k)" class="pa-lkt__kkm" :style="{ left: `${batas(k.nilai_ambang)}%` }" />
                        </span>
                        <span class="pa-lkt__angka">
                            <strong>{{ desimal(k.rata_rata) }}</strong>
                            <small v-if="adaKkm(k)" class="pa-lkt__selisih" :class="`is-${status(k)}`">{{ tandaSelisih(k) }}</small>
                            <small v-else class="pa-lkt__selisih is-netral">KKM belum ada</small>
                        </span>
                        <HcisIcon name="chevron-right" size="2xs" class="pa-lkt__chevron" />
                    </button>
                </li>
            </ul>

            <p v-if="!terSaring.length" class="pa-lkt__kosong-saring">Tidak ada kompetensi pada saringan ini.</p>
            <button
                v-if="terSaring.length > BATAS"
                type="button"
                class="pa-lkt__lagi"
                data-uji="tampilkan-semua-kompetensi"
                @click="semua = !semua"
            >
                {{ semua ? 'Tampilkan lebih sedikit' : `Tampilkan semua (${terSaring.length})` }}
                <HcisIcon :name="semua ? 'chevron-up' : 'chevron-down'" size="2xs" />
            </button>
        </template>

        <div v-else class="pa-lkt__kosong">
            <HcisIcon name="stars" size="md" />
            <strong>Belum ada nilai kompetensi yang terlihat</strong>
            <p>Muncul setelah hasil anggota dirilis ke Anda atau terbit ke karyawan.</p>
        </div>

        <!-- Detail satu kompetensi -->
        <HcisDialog
            v-model="dialog"
            :title="dipilih?.nama_kompetensi || 'Detail kompetensi'"
            :kicker="(dipilih?.nama_type || 'Kompetensi').toUpperCase()"
            icon="stars"
            sentiment="brand"
            size="md"
        >
            <div v-if="dipilih" class="pa-lkt-detail" data-uji="detail-kompetensi">
                <div class="pa-lkt-detail__stat">
                    <div><small>Rata-rata tim</small><strong>{{ desimal(dipilih.rata_rata) }}</strong></div>
                    <div><small>KKM</small><strong>{{ adaKkm(dipilih) ? desimal(dipilih.nilai_ambang) : '–' }}</strong></div>
                    <div>
                        <small>Selisih</small>
                        <strong :class="`is-${status(dipilih)}`">{{ adaKkm(dipilih) ? tandaSelisih(dipilih) : '–' }}</strong>
                    </div>
                    <div><small>Dinilai</small><strong>{{ dipilih.jumlah_orang }} <em>orang</em></strong></div>
                </div>

                <p class="pa-lkt-detail__kesimpulan" :class="`is-${status(dipilih)}`">
                    <HcisIcon :name="status(dipilih) === 'bawah' ? 'exclamation-triangle-fill' : status(dipilih) === 'atas' ? 'check-circle-fill' : 'info-circle-fill'" size="xs" />
                    {{ kesimpulan(dipilih) }}
                </p>

                <section class="pa-lkt-detail__bagian">
                    <h4><HcisIcon name="book" size="2xs" /> Arti kompetensi</h4>
                    <p v-if="dipilih.deskripsi">{{ dipilih.deskripsi }}</p>
                    <p v-else class="pa-lkt-detail__belum">Deskripsi belum ditulis HC di master kompetensi.</p>
                </section>

                <section class="pa-lkt-detail__bagian">
                    <h4><HcisIcon name="person-check" size="2xs" /> Perilaku yang diharapkan</h4>
                    <!-- Per level: anggota beda level dituntut tingkat berbeda (Matriks Kompetensi). -->
                    <template v-if="(dipilih.perilaku_per_level || []).length">
                        <div v-for="l in dipilih.perilaku_per_level" :key="l.nama_level" class="pa-lkt-detail__level" data-uji="perilaku-per-level">
                            <p class="pa-lkt-detail__level-judul">
                                <b>{{ l.nama_level }}</b> · L{{ l.tingkat }} {{ l.nama_tingkat }}
                                <small>({{ l.jumlah_orang }} orang)</small>
                            </p>
                            <PaDaftarPerilaku v-if="l.expected_behaviour" :teks="l.expected_behaviour" judul="" />
                            <p v-else class="pa-lkt-detail__belum">Expected behaviour tingkat ini belum ditulis.</p>
                        </div>
                    </template>
                    <PaDaftarPerilaku v-else-if="dipilih.expected_behaviour" :teks="dipilih.expected_behaviour" judul="" />
                    <p v-else class="pa-lkt-detail__belum">Expected behaviour belum ditulis HC di master kompetensi.</p>
                </section>

                <section v-if="(dipilih.anggota || []).length" class="pa-lkt-detail__bagian">
                    <h4><HcisIcon name="people" size="2xs" /> Nilai anggota tim</h4>
                    <ul class="pa-lkt-detail__anggota" role="list">
                        <li v-for="a in dipilih.anggota" :key="a.participant_id">
                            <button type="button" class="pa-lkt-detail__orang" :title="`Buka detail ${a.nama}`" @click="bukaAnggota(a)">
                                <b>{{ a.nama }}<small v-if="a.nama_level && (dipilih.perilaku_per_level || []).length > 1" class="pa-lkt-detail__lv"> · {{ a.nama_level }}</small></b>
                                <span class="pa-lkt__bar is-kecil" :class="{ 'is-bawah-kkm': adaKkm(dipilih) && a.nilai < dipilih.nilai_ambang }">
                                    <i :style="{ width: `${batas(a.nilai)}%` }" />
                                    <span v-if="adaKkm(dipilih)" class="pa-lkt__kkm" :style="{ left: `${batas(dipilih.nilai_ambang)}%` }" />
                                </span>
                                <strong>{{ desimal(a.nilai) }}</strong>
                            </button>
                        </li>
                    </ul>
                </section>
            </div>
            <template #footer>
                <HcisButton label="Tutup" variant="secondary" size="sm" @click="dialog = false" />
            </template>
        </HcisDialog>
    </HcisCard>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { HcisButton, HcisCard, HcisDialog, HcisIcon } from '@/components/ui';
import PaDaftarPerilaku from '../../components/PaDaftarPerilaku.vue';

/* Kompetensi tim untuk leader: diurutkan, dibandingkan KKM, dan bisa diklik untuk
   membaca arti, perilaku yang diharapkan, serta nilai tiap anggota. Semua angkanya
   berasal dari hasil yang boleh dilihat leader (lihat PaLeaderService::kompetensiTim). */
const props = defineProps({
    kompetensi: { type: Array, default: () => [] },
    ringkas: { type: Boolean, default: false },
});
const emit = defineEmits(['detail']);

const BATAS = 6;
const aktif = ref('semua');
const semua = ref(false);
const dialog = ref(false);
const dipilih = ref(null);

const desimal = (v) => (v === null || v === undefined ? '–' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
const batas = (v) => Math.min(100, Math.max(0, Number(v) || 0));
const adaKkm = (k) => k?.nilai_ambang !== null && k?.nilai_ambang !== undefined;
const selisih = (k) => Math.round((Number(k.rata_rata) - Number(k.nilai_ambang)) * 100) / 100;
const status = (k) => (!adaKkm(k) ? 'netral' : selisih(k) >= 0 ? 'atas' : 'bawah');
const tandaSelisih = (k) => {
    const d = selisih(k);
    return `${d >= 0 ? '+' : '−'}${desimal(Math.abs(d))} vs KKM`;
};

const kesimpulan = (k) => {
    if (!adaKkm(k)) return 'KKM kompetensi ini belum ditetapkan, jadi belum bisa dinilai cukup atau belum.';
    const d = selisih(k);
    return d >= 0
        ? `Rata-rata tim ${desimal(Math.abs(d))} poin di atas KKM — ini kekuatan tim.`
        : `Rata-rata tim ${desimal(Math.abs(d))} poin di bawah KKM — prioritaskan untuk pengembangan.`;
};

const urut = computed(() => [...props.kompetensi].sort((a, b) => Number(b.rata_rata) - Number(a.rata_rata)));
const saringan = computed(() => [
    { kunci: 'semua', label: 'Semua', ikon: 'list-ul', jumlah: urut.value.length },
    { kunci: 'atas', label: 'Kekuatan', ikon: 'hand-thumbs-up-fill', jumlah: urut.value.filter((k) => status(k) === 'atas').length },
    { kunci: 'bawah', label: 'Perlu dikembangkan', ikon: 'graph-up-arrow', jumlah: urut.value.filter((k) => status(k) === 'bawah').length },
]);
/* "Perlu dikembangkan" diurutkan dari yang paling jauh di bawah KKM. */
const terSaring = computed(() => {
    if (aktif.value === 'atas') return urut.value.filter((k) => status(k) === 'atas');
    if (aktif.value === 'bawah') return urut.value.filter((k) => status(k) === 'bawah').sort((a, b) => selisih(a) - selisih(b));
    return urut.value;
});
const tampil = computed(() => (semua.value ? terSaring.value : terSaring.value.slice(0, BATAS)));
const peringkat = (k, i) => (aktif.value === 'semua' ? i + 1 : urut.value.indexOf(k) + 1);

function pilihSaringan(kunci) {
    aktif.value = kunci;
    semua.value = false;
}

function buka(k) {
    dipilih.value = k;
    dialog.value = true;
}

function bukaAnggota(a) {
    dialog.value = false;
    emit('detail', a);
}

watch(() => props.kompetensi, () => {
    aktif.value = 'semua';
    semua.value = false;
});
</script>

<style scoped>
.pa-lkt__atas { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.6rem; margin-bottom: 0.75rem; }
.pa-lkt__saring { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.pa-lkt__chip { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.7rem; border: 1px solid #e2e8f0; border-radius: 999px; background: #fff; font-size: 0.74rem; font-weight: 700; color: #475569; cursor: pointer; transition: all 0.15s ease; }
.pa-lkt__chip b { padding: 0 0.35rem; border-radius: 999px; background: #f1f5f9; font-variant-numeric: tabular-nums; }
.pa-lkt__chip.is-aktif { border-color: #6366f1; background: #eef2ff; color: #3730a3; }
.pa-lkt__chip.is-atas.is-aktif { border-color: #22c55e; background: #f0fdf4; color: #15803d; }
.pa-lkt__chip.is-bawah.is-aktif { border-color: #f59e0b; background: #fffbeb; color: #b45309; }
.pa-lkt__legenda { display: flex; gap: 0.75rem; font-size: 0.7rem; color: #64748b; }
.pa-lkt__legenda span { display: inline-flex; align-items: center; gap: 0.3rem; }
.pa-lkt__legenda i.is-bar { width: 16px; height: 6px; border-radius: 999px; background: #10b981; }
.pa-lkt__legenda i.is-kkm { width: 2px; height: 12px; border-radius: 2px; background: #0f172a; opacity: 0.6; }

.pa-lkt__daftar { display: flex; flex-direction: column; gap: 0.35rem; margin: 0; padding: 0; list-style: none; }
.pa-lkt__baris { display: grid; grid-template-columns: 28px minmax(0, 1.3fr) minmax(0, 1fr) 118px 14px; align-items: center; gap: 0.75rem; width: 100%; padding: 0.55rem 0.65rem; border: 1px solid #eef2f7; border-left: 3px solid #cbd5e1; border-radius: 12px; background: #fff; text-align: left; cursor: pointer; transition: background 0.15s ease, box-shadow 0.15s ease; }
.pa-lkt__baris:hover, .pa-lkt__baris:focus-visible { background: #f8fafc; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06); outline: none; }
.pa-lkt__baris.is-atas { border-left-color: #22c55e; }
.pa-lkt__baris.is-bawah { border-left-color: #f59e0b; }
.pa-lkt__peringkat { display: grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; background: #f1f5f9; font-size: 0.72rem; font-weight: 800; color: #475569; }
.pa-lkt__nama { display: flex; flex-direction: column; min-width: 0; }
.pa-lkt__nama b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.83rem; color: #0f172a; }
.pa-lkt__nama small { font-size: 0.7rem; color: #64748b; }
.pa-lkt__bar { position: relative; height: 10px; border-radius: 999px; background: #f1f5f9; }
.pa-lkt__bar i { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #34d399, #10b981); transition: width 0.4s ease; }
.is-bawah .pa-lkt__bar i, .pa-lkt__bar.is-bawah-kkm i { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.is-netral .pa-lkt__bar i { background: linear-gradient(90deg, #a5b4fc, #6366f1); }
.pa-lkt__bar.is-kecil { height: 8px; }
.pa-lkt__kkm { position: absolute; top: -3px; width: 2px; height: calc(100% + 6px); border-radius: 2px; background: #0f172a; opacity: 0.6; transform: translateX(-1px); }
.pa-lkt__angka { display: flex; flex-direction: column; align-items: flex-end; }
.pa-lkt__angka strong { font-size: 0.95rem; color: #0f172a; font-variant-numeric: tabular-nums; }
.pa-lkt__selisih { font-size: 0.68rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.pa-lkt__selisih.is-atas { color: #15803d; }
.pa-lkt__selisih.is-bawah { color: #b45309; }
.pa-lkt__selisih.is-netral { color: #94a3b8; font-weight: 600; }
.pa-lkt__chevron { color: #94a3b8; }
.pa-lkt__lagi { display: inline-flex; align-items: center; gap: 0.3rem; margin-top: 0.6rem; padding: 0; border: 0; background: none; font-size: 0.78rem; font-weight: 700; color: #4f46e5; cursor: pointer; }
.pa-lkt__kosong-saring { margin: 0.5rem 0 0; font-size: 0.78rem; color: #64748b; }
.pa-lkt__kosong { display: flex; flex-direction: column; align-items: center; gap: 0.3rem; padding: 1.2rem 0.5rem; text-align: center; color: #64748b; }
.pa-lkt__kosong strong { color: #0f172a; font-size: 0.85rem; }
.pa-lkt__kosong p { margin: 0; max-width: 36ch; font-size: 0.76rem; }

.pa-lkt-detail { display: flex; flex-direction: column; gap: 1rem; }
.pa-lkt-detail__stat { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.5rem; }
.pa-lkt-detail__stat > div { display: flex; flex-direction: column; gap: 0.15rem; padding: 0.6rem 0.7rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; }
.pa-lkt-detail__stat small { font-size: 0.66rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
.pa-lkt-detail__stat strong { font-size: 1.1rem; color: #0f172a; font-variant-numeric: tabular-nums; }
.pa-lkt-detail__stat strong em { font-size: 0.75rem; font-style: normal; font-weight: 600; color: #64748b; }
.pa-lkt-detail__stat strong.is-atas { color: #15803d; }
.pa-lkt-detail__stat strong.is-bawah { color: #b45309; }
.pa-lkt-detail__kesimpulan { display: flex; align-items: center; gap: 0.45rem; margin: 0; padding: 0.6rem 0.75rem; border-radius: 12px; font-size: 0.82rem; font-weight: 600; }
.pa-lkt-detail__kesimpulan.is-atas { background: #f0fdf4; color: #166534; }
.pa-lkt-detail__kesimpulan.is-bawah { background: #fffbeb; color: #92400e; }
.pa-lkt-detail__kesimpulan.is-netral { background: #f1f5f9; color: #475569; }
.pa-lkt-detail__bagian { display: flex; flex-direction: column; gap: 0.4rem; }
.pa-lkt-detail__bagian h4 { display: inline-flex; align-items: center; gap: 0.35rem; margin: 0; font-size: 0.74rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #334155; }
.pa-lkt-detail__bagian > p { margin: 0; font-size: 0.85rem; line-height: 1.55; color: #334155; }
.pa-lkt-detail__belum { font-style: italic; color: #94a3b8 !important; }
.pa-lkt-detail__anggota { display: flex; flex-direction: column; gap: 0.2rem; margin: 0; padding: 0; list-style: none; }
.pa-lkt-detail__orang { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr) 56px; align-items: center; gap: 0.7rem; width: 100%; padding: 0.4rem 0.5rem; border: 0; border-radius: 10px; background: transparent; text-align: left; cursor: pointer; }
.pa-lkt-detail__orang:hover { background: #f8fafc; }
.pa-lkt-detail__orang b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.82rem; color: #0f172a; }
.pa-lkt-detail__orang strong { text-align: right; font-size: 0.82rem; font-variant-numeric: tabular-nums; }

@media (max-width: 640px) {
    .pa-lkt__baris { grid-template-columns: 26px minmax(0, 1fr) 96px; grid-template-areas: 'rank nama angka' 'bar bar bar'; row-gap: 0.45rem; }
    .pa-lkt__peringkat { grid-area: rank; }
    .pa-lkt__nama { grid-area: nama; }
    .pa-lkt__angka { grid-area: angka; }
    .pa-lkt__bar { grid-area: bar; }
    .pa-lkt__chevron { display: none; }
    .pa-lkt-detail__stat { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.pa-lkt-detail__level { margin-bottom: 0.6rem; padding: 0.5rem 0.65rem; border-left: 3px solid #a78bfa; border-radius: 6px; background: #faf5ff; }
.pa-lkt-detail__level-judul { margin: 0 0 0.3rem; font-size: 0.8rem; color: #4c1d95; }
.pa-lkt-detail__level-judul small, .pa-lkt-detail__lv { color: #64748b; font-weight: 500; }
</style>

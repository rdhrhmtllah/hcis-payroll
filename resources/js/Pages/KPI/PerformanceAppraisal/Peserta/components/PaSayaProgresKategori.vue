<template>
    <section class="pa-prog" aria-label="Progres penilaian per kategori penilai">
        <HcisResultState
            v-if="error"
            status="danger"
            variant="glass"
            layout="compact"
            title="Progres gagal dimuat"
            :description="error"
            primary-text="Coba lagi"
            primary-icon="arrow-clockwise"
            @primary-click="$emit('retry')"
        />

        <template v-else-if="loading && !data">
            <HcisSkeleton v-for="n in 3" :key="`prog-skel-${n}`" height="4.5rem" rounded="lg" />
        </template>

        <HcisResultState
            v-else-if="!kategori.length"
            status="info"
            variant="glass"
            layout="compact"
            title="Belum ada tugas penilaian"
            description="Tidak ada orang yang perlu Anda nilai pada periode ini. Kalau menurut Anda seharusnya ada, hubungi HC — penugasannya mungkin belum dibentuk."
        />

        <template v-else>
            <!--
                Total di atas, dan angkanya dari SERVER.

                Ia dihitung sebagai selesai/total seluruh tugas, BUKAN rata-rata persentase
                kategori. Keduanya sama-sama 100% hanya bila semua kategori tuntas, tetapi
                rata-rata salah melaporkan bagian tengahnya: 1 dari 1 di ATASAN ditambah 0 dari
                40 di BAWAHAN akan terbaca 50%, padahal yang selesai 1 dari 41.
            -->
            <div class="pa-prog__total" :class="{ 'is-tuntas': data.tuntas }">
                <div class="pa-prog__total-head">
                    <div>
                        <strong>Progres keseluruhan</strong>
                        <span class="pa-prog__total-sub">
                            {{ angka(data.selesai) }} dari {{ angka(data.total) }} tugas selesai
                            <template v-if="kategori.length > 1">
                                · 100% baru tercapai bila SEMUA {{ kategori.length }} kategori tuntas
                            </template>
                        </span>
                    </div>

                    <span class="pa-prog__total-persen">{{ angka(data.persen) }}%</span>
                </div>

                <div
                    class="pa-prog__track"
                    role="progressbar"
                    aria-label="Progres keseluruhan penilaian"
                    :aria-valuenow="angka(data.persen)"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <span :style="{ width: `${lebar(data.persen)}%` }" />
                </div>

                <!--
                    Yang mendesak disebut di TINGKAT ATAS juga, bukan hanya di dalam kelompok.
                    Orang yang melipat semuanya tetap melihat bahwa ada yang terlambat.
                -->
                <div v-if="angka(data.terlambat) > 0 || angka(data.deadline_dekat) > 0" class="pa-prog__urgensi">
                    <HcisStatusBadge
                        v-if="angka(data.terlambat) > 0"
                        :label="`${angka(data.terlambat)} terlambat`"
                        variant="danger"
                        dot
                    />
                    <HcisStatusBadge
                        v-if="angka(data.deadline_dekat) > 0"
                        :label="`${angka(data.deadline_dekat)} mendekati tenggat`"
                        variant="warning"
                        dot
                    />
                </div>
            </div>

            <!-- Satu kelompok per kategori penilai. -->
            <article
                v-for="k in kategori"
                :key="k.kode_kategori"
                class="pa-prog__grup"
                :class="{ 'is-tuntas': k.tuntas, 'is-terbuka': terbuka(k.kode_kategori) }"
            >
                <!--
                    Kepala kelompok memuat SEMUA yang menuntut tindakan: persentase, jumlah
                    terlambat, dan jumlah mendekati tenggat.

                    Itu keputusan yang disengaja. Kalau urgensinya hanya ada di dalam, melipat
                    kelompok jadi cara kehilangan pekerjaan — dan yang paling mungkin melipat
                    justru orang yang tugasnya paling banyak.
                -->
                <button
                    type="button"
                    class="pa-prog__kepala"
                    :aria-expanded="terbuka(k.kode_kategori) ? 'true' : 'false'"
                    :aria-controls="`pa-prog-isi-${k.kode_kategori}`"
                    @click="toggle(k.kode_kategori)"
                >
                    <HcisIcon :name="terbuka(k.kode_kategori) ? 'chevron-down' : 'chevron-right'" size="xs" />

                    <span class="pa-prog__kepala-nama">
                        {{ k.nama_kategori }}
                        <!-- SELF ditandai, bukan dibiarkan terbaca seperti orang lain yang senama. -->
                        <em v-if="k.kode_kategori === 'SELF'">(penilaian diri)</em>
                    </span>

                    <span class="pa-prog__kepala-cacah">{{ angka(k.selesai) }}/{{ angka(k.total) }}</span>

                    <span class="pa-prog__kepala-bar" aria-hidden="true">
                        <span :style="{ width: `${lebar(k.persen)}%` }" />
                    </span>

                    <span class="pa-prog__kepala-persen">{{ angka(k.persen) }}%</span>

                    <HcisIcon v-if="k.tuntas" name="check-circle-fill" size="xs" class="pa-prog__tuntas-ikon" />

                    <span class="pa-prog__kepala-lencana">
                        <HcisStatusBadge
                            v-if="angka(k.terlambat) > 0"
                            :label="String(angka(k.terlambat))"
                            variant="danger"
                            dot
                            :title="`${angka(k.terlambat)} tugas terlambat`"
                        />
                        <HcisStatusBadge
                            v-if="angka(k.deadline_dekat) > 0"
                            :label="String(angka(k.deadline_dekat))"
                            variant="warning"
                            dot
                            :title="`${angka(k.deadline_dekat)} tugas mendekati tenggat`"
                        />
                    </span>
                </button>

                <div v-show="terbuka(k.kode_kategori)" :id="`pa-prog-isi-${k.kode_kategori}`" class="pa-prog__isi">
                    <dl class="pa-prog__rinci">
                        <div>
                            <dt>Selesai</dt>
                            <dd>{{ angka(k.selesai) }}</dd>
                        </div>
                        <!--
                            BERJALAN berdiri sendiri, TIDAK dilipat ke dalam persentase.

                            Formulirnya diisi di HC Learn: "berjalan" hanya berarti form-nya sudah
                            dibuka, dan HCIS tidak punya cara tahu sudah berapa butir yang dijawab.
                            Memberinya kredit separuh berarti menampilkan angka yang dikarang.
                        -->
                        <div>
                            <dt>Sedang dibuka</dt>
                            <dd>{{ angka(k.berjalan) }}</dd>
                        </div>
                        <div>
                            <dt>Belum dibuka</dt>
                            <dd>{{ angka(k.belum_mulai) }}</dd>
                        </div>
                        <div v-if="angka(k.terlambat) > 0" class="is-bahaya">
                            <dt>Terlambat</dt>
                            <dd>{{ angka(k.terlambat) }}</dd>
                        </div>
                    </dl>

                    <p class="pa-prog__aksi">
                        <button type="button" class="pa-prog__tautan" @click="$emit('lihat-kategori', k.kode_kategori)">
                            Lihat {{ angka(k.total) }} tugas {{ k.nama_kategori }}
                        </button>
                    </p>
                </div>
            </article>

            <!--
                Batas yang jujur: HCIS tidak memiliki formulirnya, jadi halaman ini menampilkan
                KEADAAN, bukan pintu. Tanpa keterangan ini penilai akan mencari tombol yang
                memang belum ada, lalu menyimpulkan layarnya rusak.
            -->
            <p class="pa-prog__catatan">
                <HcisIcon name="info-circle" size="2xs" />
                Pengisiannya berlangsung di HC Learn. Status di sini mengikuti apa yang dikabarkan
                HC Learn kembali, jadi "sedang dibuka" belum berarti sudah terjawab sebagian.
            </p>
        </template>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

import { HcisIcon, HcisResultState, HcisSkeleton, HcisStatusBadge } from '@/components/ui';

const props = defineProps({
    data: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

defineEmits(['retry', 'lihat-kategori']);

const kategori = computed(() => props.data?.kategori ?? []);

/* Yang terbuka disimpan sebagai HIMPUNAN kode yang DILIPAT, bukan yang dibuka.
   Sebabnya: kelompok baru yang muncul sesudahnya (kategori yang tadinya nol tugas lalu terisi)
   harus terbuka secara baku. Kalau yang dicatat adalah "yang dibuka", kelompok baru akan
   muncul terlipat dan penilai tidak punya petunjuk bahwa ada pekerjaan baru di sana. */
const dilipat = ref(new Set());

/* Yang sudah tuntas dilipat secara baku — bakunya menunjuk ke pekerjaan yang masih ada.
   Hanya dijalankan sekali per kedatangan data supaya lipatan yang dibuka penilai tidak
   tertutup lagi setiap kali datanya disegarkan. */
const sudahMenata = ref(false);

watch(
    kategori,
    (nilai) => {
        if (sudahMenata.value || ! nilai.length) {
            return;
        }

        for (const k of nilai) {
            if (k.tuntas) {
                dilipat.value.add(k.kode_kategori);
            }
        }

        sudahMenata.value = true;
    },
    { immediate: true },
);

function terbuka(kode) {
    return ! dilipat.value.has(kode);
}

function toggle(kode) {
    /* Set diganti, bukan disunting di tempat: Vue tidak melacak mutasi Set, jadi menyunting
       isinya membuat layar tidak berubah walau keadaannya sudah berubah. */
    const baru = new Set(dilipat.value);
    baru.has(kode) ? baru.delete(kode) : baru.add(kode);
    dilipat.value = baru;
}

function angka(nilai, baku = 0) {
    const n = Number(nilai);

    return Number.isFinite(n) ? n : baku;
}

function lebar(persen) {
    return Math.max(0, Math.min(100, angka(persen)));
}
</script>

<style scoped>
.pa-prog {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.pa-prog__total {
    padding: 0.85rem 1rem;
    border-radius: 12px;
    border: 1px solid #dbe4f0;
    background: #f8fafc;
}

.pa-prog__total.is-tuntas {
    border-color: #86efac;
    background: #f0fdf4;
}

.pa-prog__total-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.pa-prog__total-sub {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.76rem;
    color: #64748b;
}

.pa-prog__total-persen {
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1;
    color: #1d4ed8;
}

.pa-prog__total.is-tuntas .pa-prog__total-persen {
    color: #15803d;
}

.pa-prog__track {
    height: 0.5rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-prog__track > span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: #1d4ed8;
    transition: width 0.25s ease;
}

.pa-prog__total.is-tuntas .pa-prog__track > span {
    background: #16a34a;
}

.pa-prog__urgensi {
    display: flex;
    gap: 0.35rem;
    margin-top: 0.55rem;
}

.pa-prog__grup {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    overflow: hidden;
}

.pa-prog__grup.is-tuntas {
    border-color: #d1fae5;
    background: #fbfffd;
}

.pa-prog__kepala {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.7rem 0.85rem;
    border: 0;
    background: transparent;
    font: inherit;
    text-align: left;
    cursor: pointer;
}

.pa-prog__kepala:hover {
    background: #f8fafc;
}

.pa-prog__kepala-nama {
    flex: 1 1 auto;
    font-weight: 600;
    font-size: 0.86rem;
}

.pa-prog__kepala-nama em {
    margin-left: 0.3rem;
    font-size: 0.72rem;
    font-style: normal;
    font-weight: 500;
    color: #64748b;
}

.pa-prog__kepala-cacah {
    flex: 0 0 auto;
    font-size: 0.78rem;
    color: #475569;
    font-variant-numeric: tabular-nums;
}

.pa-prog__kepala-bar {
    flex: 0 0 6rem;
    height: 0.35rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}

.pa-prog__kepala-bar > span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: #1d4ed8;
    transition: width 0.25s ease;
}

.pa-prog__grup.is-tuntas .pa-prog__kepala-bar > span {
    background: #16a34a;
}

.pa-prog__kepala-persen {
    flex: 0 0 2.6rem;
    text-align: right;
    font-size: 0.78rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pa-prog__tuntas-ikon {
    flex: 0 0 auto;
    color: #16a34a;
}

.pa-prog__kepala-lencana {
    display: flex;
    flex: 0 0 auto;
    gap: 0.25rem;
}

.pa-prog__isi {
    padding: 0 0.85rem 0.8rem;
    border-top: 1px solid #eef2f7;
}

.pa-prog__rinci {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 1.4rem;
    margin: 0.7rem 0 0.55rem;
}

.pa-prog__rinci > div {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
}

.pa-prog__rinci dt {
    font-size: 0.74rem;
    color: #64748b;
}

.pa-prog__rinci dd {
    margin: 0;
    font-size: 0.86rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.pa-prog__rinci > div.is-bahaya dd {
    color: #b91c1c;
}

.pa-prog__aksi {
    margin: 0;
}

.pa-prog__tautan {
    padding: 0;
    border: 0;
    background: transparent;
    color: #1d4ed8;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: underline;
    cursor: pointer;
}

.pa-prog__catatan {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    margin: 0.2rem 0 0;
    font-size: 0.74rem;
    line-height: 1.4;
    color: #64748b;
}
</style>

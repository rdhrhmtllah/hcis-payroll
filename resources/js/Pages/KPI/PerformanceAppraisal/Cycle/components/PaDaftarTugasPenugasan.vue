<template>
    <!--
     | SATU DAFTAR TUGAS DI ATAS LANGKAH 5 (UAT 13 Okt).
     |
     | Sebelumnya satu masalah disebut di tiga tempat — panel HC Learn, kartu "Sebelum cycle
     | dikunci", dan pesan footer — sementara pertanyaan admin hanya satu: "apa yang harus saya
     | lakukan sekarang?". Di sini tiap masalah disebut SEKALI, satu kalimat, satu tombol.
     |
     |   Wajib       menahan tombol kunci (sumbernya sama dengan `bolehKunci`).
     |   Disarankan  boleh dikunci tanpa membereskannya, tetapi akibatnya disebut.
     |
     | Angka rinci tidak dihapus — dilipat di "Rincian" di bawah.
     -->
    <section class="pa-tugas" :class="kelasStatus" data-uji="daftar-tugas">
        <div class="pa-tugas__kepala">
            <div class="pa-tugas__ikon-box" :class="`is-${warnaJudul}`">
                <HcisIcon :name="ikonJudul" size="sm" :color="warnaJudul" />
            </div>
            <div class="pa-tugas__kepala-teks">
                <div class="pa-tugas__judul-baris">
                    <h3 class="pa-tugas__judul" data-uji="daftar-tugas-judul">{{ judul }}</h3>
                    <span v-if="jumlahWajib > 0 && !terkunci" class="pa-tugas-pill is-wajib">
                        {{ jumlahWajib }} Wajib
                    </span>
                    <span v-else-if="jumlahSaran > 0 && !terkunci" class="pa-tugas-pill is-saran">
                        {{ jumlahSaran }} Perhatian
                    </span>
                    <span v-else-if="!terkunci" class="pa-tugas-pill is-ready">
                        Siap Kunci
                    </span>
                </div>
                <p v-if="subjudul" class="pa-tugas__subjudul">{{ subjudul }}</p>
            </div>
        </div>

        <ul v-if="wajib.length" class="pa-tugas__daftar">
            <li v-for="t in wajib" :key="t.kunci" class="pa-tugas__baris is-wajib" :data-uji="`tugas-${t.kunci}`">
                <div class="pa-tugas__isi">
                    <div class="pa-tugas__tag-line">
                        <span class="pa-tugas-label is-wajib">Wajib</span>
                        <strong class="pa-tugas__teks-judul">{{ t.teks }}</strong>
                    </div>
                    <span v-if="t.rincian" class="pa-tugas__rincian">{{ t.rincian }}</span>
                </div>
                <div v-if="t.aksi?.length" class="pa-tugas__aksi">
                    <HcisButton
                        v-for="(a, i) in t.aksi"
                        :key="i"
                        :label="a.label"
                        :variant="i === 0 ? 'primary' : 'secondary'"
                        size="sm"
                        :leading-icon="a.ikon"
                        :data-uji="a.uji"
                        @click="a.klik()"
                    />
                </div>
            </li>
        </ul>

        <!-- Baris dari panel perubahan peserta (sudah berlabel sendiri). -->
        <slot />

        <ul v-if="saran.length" class="pa-tugas__daftar">
            <li v-for="t in saran" :key="t.kunci" class="pa-tugas__baris is-saran" :data-uji="`tugas-${t.kunci}`">
                <div class="pa-tugas__isi">
                    <div class="pa-tugas__tag-line">
                        <span class="pa-tugas-label is-saran">Disarankan</span>
                        <strong class="pa-tugas__teks-judul">{{ t.teks }}</strong>
                    </div>
                    <span v-if="t.rincian" class="pa-tugas__rincian">{{ t.rincian }}</span>
                </div>
                <div v-if="t.aksi?.length" class="pa-tugas__aksi">
                    <HcisButton
                        v-for="(a, i) in t.aksi"
                        :key="i"
                        :label="a.label"
                        variant="secondary"
                        size="sm"
                        :leading-icon="a.ikon"
                        :data-uji="a.uji"
                        @click="a.klik()"
                    />
                </div>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { HcisButton, HcisIcon } from '@/components/ui';

defineOptions({ name: 'PaDaftarTugasPenugasan' });

const props = defineProps({
    /** @type {Array<{kunci:string, teks:string, rincian?:string, aksi?:Array<{label:string, ikon?:string, uji?:string, klik:Function}>}>} */
    wajib: { type: Array, default: () => [] },
    saran: { type: Array, default: () => [] },
    /* Baris dari panel perubahan peserta yang ditaruh lewat slot: { total, wajib }. */
    tambahan: { type: Object, default: () => ({ total: 0, wajib: 0 }) },
    terkunci: { type: Boolean, default: false },
});

const jumlahWajib = computed(() => props.wajib.length + (props.tambahan?.wajib || 0));
const jumlahSaran = computed(() => props.saran.length + Math.max(0, (props.tambahan?.total || 0) - (props.tambahan?.wajib || 0)));

const judul = computed(() => {
    if (props.terkunci) {
        return jumlahWajib.value + jumlahSaran.value > 0 ? 'Siklus terkunci — masih ada yang perlu dicek' : 'Siklus terkunci';
    }
    if (jumlahWajib.value > 0) return `Belum bisa dikunci — ${jumlahWajib.value} hal wajib dibereskan`;
    if (jumlahSaran.value > 0) return 'Bisa dikunci — ada yang sebaiknya dicek dulu';
    return 'Siap dikunci';
});

const subjudul = computed(() => {
    if (props.terkunci) return '';
    if (jumlahWajib.value === 0 && jumlahSaran.value === 0) return 'Semua penugasan lengkap dan dikenal HC Learn.';
    return '';
});

const ikonJudul = computed(() => {
    if (jumlahWajib.value > 0 && !props.terkunci) return 'exclamation-octagon-fill';
    if (jumlahSaran.value > 0) return 'info-circle-fill';
    return 'check-circle-fill';
});

const warnaJudul = computed(() => {
    if (jumlahWajib.value > 0 && !props.terkunci) return 'danger';
    if (jumlahSaran.value > 0) return 'warning';
    return 'success';
});

const kelasStatus = computed(() => {
    if (props.terkunci) return 'is-terkunci';
    if (jumlahWajib.value > 0) return 'is-has-wajib';
    if (jumlahSaran.value > 0) return 'is-has-saran';
    return 'is-ready';
});
</script>

<style scoped>
.pa-tugas {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.15rem 1.35rem;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #ffffff;
    transition: all 0.2s ease;
}

.pa-tugas.is-has-wajib {
    border-color: #fca5a5;
    border-left: 5px solid #ef4444;
    box-shadow: 0 4px 16px -2px rgba(239, 68, 68, 0.08);
}

.pa-tugas.is-has-saran {
    border-color: #fde68a;
    border-left: 5px solid #f59e0b;
    box-shadow: 0 4px 16px -2px rgba(245, 158, 11, 0.06);
}

.pa-tugas.is-ready {
    border-color: #a7f3d0;
    border-left: 5px solid #10b981;
    box-shadow: 0 4px 16px -2px rgba(16, 185, 129, 0.06);
}

.pa-tugas.is-terkunci {
    border-color: #cbd5e1;
    border-left: 5px solid #64748b;
    background: #f8fafc;
}

.pa-tugas__kepala {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}

.pa-tugas__ikon-box {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pa-tugas__ikon-box.is-danger {
    background: #fee2e2;
    color: #dc2626;
}

.pa-tugas__ikon-box.is-warning {
    background: #fef3c7;
    color: #d97706;
}

.pa-tugas__ikon-box.is-success {
    background: #d1fae5;
    color: #059669;
}

.pa-tugas__kepala-teks {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    flex: 1;
}

.pa-tugas__judul-baris {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}

.pa-tugas__judul {
    margin: 0;
    color: #0f172a;
    font-size: 1.05rem;
    font-weight: 750;
    letter-spacing: -0.01em;
}

.pa-tugas-pill {
    font-size: 0.7rem;
    font-weight: 750;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.pa-tugas-pill.is-wajib {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.pa-tugas-pill.is-saran {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

.pa-tugas-pill.is-ready {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.pa-tugas__subjudul {
    margin: 0;
    color: #64748b;
    font-size: 0.8125rem;
    line-height: 1.4;
}

.pa-tugas__daftar {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pa-tugas__baris {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    padding: 0.85rem 1.1rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.pa-tugas__baris.is-wajib {
    background: #fffafa;
    border-color: #fecaca;
    border-left: 4px solid #ef4444;
}

.pa-tugas__baris.is-saran {
    background: #fffdfa;
    border-color: #fed7aa;
    border-left: 4px solid #f97316;
}

.pa-tugas__isi {
    display: flex;
    flex: 1 1 18rem;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}

.pa-tugas__tag-line {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pa-tugas__teks-judul {
    color: #0f172a;
    font-size: 0.92rem;
    font-weight: 700;
}

.pa-tugas__rincian {
    color: #475569;
    font-size: 0.82rem;
    line-height: 1.45;
}

.pa-tugas__aksi {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.pa-tugas-label {
    font-size: 0.68rem;
    font-weight: 750;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    padding: 0.12rem 0.5rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
}

.pa-tugas-label.is-wajib {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.pa-tugas-label.is-saran {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}

/* Baris panel perubahan di dalam daftar ini mengikuti bentuk baris daftar. */
.pa-tugas :deep(.pa-perubahan__baris) {
    padding: 0.85rem 1.1rem;
    border: 1px solid #fed7aa;
    border-left: 4px solid #f97316;
    background: #fffdfa;
    border-radius: 10px;
    margin-top: 0.65rem;
}

@media (max-width: 640px) {
    .pa-tugas {
        padding: 0.95rem 1rem;
    }

    .pa-tugas__baris {
        flex-direction: column;
        align-items: stretch;
    }

    .pa-tugas__aksi {
        width: 100%;
        justify-content: flex-start;
    }

    .pa-tugas__aksi :deep(button) {
        flex: 1 1 auto;
    }
}
</style>

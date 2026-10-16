<template>
    <div class="versi-stepper">
        <template v-if="memuat && !langkah.length">
            <div v-for="n in 4" :key="n" class="versi-stepper__item is-memuat" aria-hidden="true">
                <span class="versi-stepper__nomor">{{ n }}</span>
                <span class="versi-stepper__isi">
                    <strong>&nbsp;</strong>
                    <small>&nbsp;</small>
                </span>
            </div>
        </template>

        <button
            v-for="l in langkah"
            v-else
            :key="l.Nomor"
            type="button"
            class="versi-stepper__item"
            :class="[`is-${l.Keadaan || 'belum'}`, {
                'is-lengkap': l.Lengkap,
                'is-dibuka': dibuka === l.Nomor,
                'is-terkunci': l.Terkunci,
            }]"
            :disabled="l.Terkunci"
            :aria-current="dibuka === l.Nomor ? 'step' : undefined"
            :title="judul(l)"
            @click="pilih(l)"
        >
            <!-- CENTANG DARI Lengkap, BUKAN DARI Keadaan.
                 Keadaan menjawab "tahapnya di mana" — dan langkah yang sedang
                 DITEMPATI selalu berbunyi 'berjalan'. Pemberkasan hanya bisa
                 ditinggalkan dengan menerbitkan, jadi selama masih draft ia tak
                 pernah bisa berbunyi 'selesai': admin yang sudah melengkapi
                 berkas dan ketiga tanggalnya tetap melihat "sedang dikerjakan",
                 selamanya.

                 Lengkap adalah fakta yang memang dikirim server untuk pertanyaan
                 ini, dan kartu ringkasan di bilah samping sudah membacanya sejak
                 awal — dua rel di halaman yang sama karena itu saling
                 bertentangan tentang langkah yang sama. Pembatalan tetap menang
                 di urutan paling atas: versi batal bukan versi selesai. -->
            <span class="versi-stepper__nomor">
                <i v-if="l.Keadaan === 'cancel'" class="bi bi-x-lg"></i>
                <i v-else-if="l.Lengkap" class="bi bi-check-lg"></i>
                <i v-else-if="l.Terkunci" class="bi bi-lock-fill"></i>
                <span v-else>{{ l.Nomor }}</span>
            </span>
            <span class="versi-stepper__isi">
                <span class="d-flex align-items-center justify-content-between gap-1">
                    <strong>{{ l.Nama }}</strong>
                    <span v-if="dibuka === l.Nomor" class="versi-stepper__pulse-dot" title="Langkah Aktif"></span>
                </span>
                <small>{{ keterangan(l) }}</small>
            </span>
        </button>
    </div>
</template>

<script>
/**
 * Rel langkah wizard versi.
 *
 * Komponen ini TIDAK memutuskan apa pun. Terkunci, keadaan, dan ringkasan
 * semuanya datang dari payload server; yang dikerjakan di sini hanya
 * menggambar dan menolak klik pada yang terkunci.
 */
export default {
    name: 'VersiStepper',
    props: {
        langkah: { type: Array, default: () => [] },
        dibuka: { type: Number, default: 1 },
        memuat: { type: Boolean, default: false },
    },
    emits: ['pilih'],
    methods: {
        keterangan(l) {
            if (l.Terkunci && l.Kurang?.length) return l.Kurang.join(' · ');
            if (l.Terkunci) return 'belum sampai di sini';
            if (l.Ringkasan) return l.Ringkasan;

            /* Sejalan dengan centangnya: saat tak ada ringkasan, yang dibaca
               admin adalah kelengkapannya, bukan posisi tahapnya. */
            return l.Lengkap && l.Keadaan !== 'cancel'
                ? 'selesai'
                : this.labelKeadaan(l.Keadaan);
        },
        labelKeadaan(k) {
            return {
                selesai: 'selesai',
                berjalan: 'sedang dikerjakan',
                cancel: 'dibatalkan',
                belum: 'belum dimulai',
            }[k] || 'belum dimulai';
        },
        judul(l) {
            return l.Terkunci && l.Kurang?.length
                ? `Terkunci — ${l.Kurang.join(', ')}`
                : l.Nama;
        },
        pilih(l) {
            if (l.Terkunci) return;
            this.$emit('pilih', l.Nomor);
        },
    },
};
</script>

<style scoped>
.versi-stepper {
    display: flex;
    gap: 0.65rem;
    flex-wrap: wrap;
    width: 100%;
}

.versi-stepper__item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1 1 12rem;
    min-width: 11rem;
    padding: 0.75rem 1rem;
    text-align: left;
    border: 1px solid var(--hcis-slate-200);
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-white);
    color: var(--hcis-slate-800);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.16s ease;
    position: relative;
    overflow: hidden;
}

.versi-stepper__item:not(:disabled):hover {
    border-color: var(--hcis-slate-300);
    background: var(--hcis-slate-50);
    transform: translateY(-1px);
}

.versi-stepper__item.is-dibuka {
    border-color: var(--hcis-indigo-500);
    background: linear-gradient(135deg, rgba(238, 242, 255, 0.7) 0%, var(--hcis-white) 100%);
    box-shadow: 0 4px 14px -2px rgba(99, 102, 241, 0.14), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
}

.versi-stepper__item.is-dibuka::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-400) 100%);
}

.versi-stepper__pulse-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: var(--hcis-indigo-600);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
    flex-shrink: 0;
}

.versi-stepper__item.is-terkunci {
    opacity: 0.6;
    cursor: not-allowed;
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-100);
}

.versi-stepper__item.is-memuat .versi-stepper__isi strong,
.versi-stepper__item.is-memuat .versi-stepper__isi small {
    display: block;
    min-width: 6rem;
    border-radius: var(--hcis-radius-sm);
    background: var(--hcis-slate-100);
}

.versi-stepper__nomor {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 2rem;
    height: 2rem;
    border-radius: 9999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    font-weight: 800;
    font-size: 0.8125rem;
    transition: all 0.15s ease;
}

/* URUTAN EMPAT ATURAN DI BAWAH INI MENGIKAT. Keempatnya berspesifisitas sama
   (dua kelas), jadi yang menang semata-mata yang belakangan:

       berjalan  <  lengkap  <  dibuka  <  cancel

   lengkap mengalahkan berjalan supaya langkah yang tuntas tapi belum
   ditinggalkan tetap terlihat hijau; dibuka mengalahkan lengkap supaya "Anda
   di sini" tak pernah tersamar; cancel mengalahkan semuanya sebab versi batal
   bukan versi selesai. Menyusunnya ulang akan mematikan salah satunya tanpa
   satu pun galat. */
.versi-stepper__item.is-berjalan .versi-stepper__nomor {
    background: var(--hcis-indigo-500);
    color: var(--hcis-white);
}

.versi-stepper__item.is-selesai .versi-stepper__nomor,
.versi-stepper__item.is-lengkap .versi-stepper__nomor {
    background: var(--hcis-green-100);
    color: var(--hcis-green-700);
    border: 1px solid var(--hcis-green-200);
}

.versi-stepper__item.is-dibuka .versi-stepper__nomor {
    background: var(--hcis-indigo-600);
    color: var(--hcis-white);
}

.versi-stepper__item.is-cancel .versi-stepper__nomor {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

.versi-stepper__isi {
    display: flex;
    flex-direction: column;
    line-height: 1.25;
    min-width: 0;
    flex-grow: 1;
}

.versi-stepper__isi strong {
    font-size: 0.875rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
}

.versi-stepper__isi small {
    font-size: 0.7125rem;
    color: var(--hcis-slate-500);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: 0.15rem;
}
</style>

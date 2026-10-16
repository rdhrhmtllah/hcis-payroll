<template>
    <div
        class="sop-list-row has-accent task-row clickable"
        :class="{ 'is-completed': isCompleted, 'is-overdue': k.Is_Terlambat }"
        :style="{ '--row-accent': tema.color }"
        @click="$emit('open', k)"
    >
        <!-- Thumbnail / Icon Cover -->
        <div class="tr-thumb" :style="{ '--thumb-color': tema.color }">
            <img v-if="hasCover" :src="k.Cover_Url" alt="" loading="lazy" @error="imgFailed = true" class="tr-img" />
            <div v-else class="tr-ico" :style="{ background: tema.color + '18', color: tema.color }">
                <i class="bi" :class="tema.icon"></i>
            </div>
        </div>

        <!-- Main Content -->
        <div class="tr-main min-w-0">
            <!-- Header Row: Title & Badges -->
            <div class="tr-header">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <code v-if="k.Kode_Paket" class="row-code">{{ k.Kode_Paket }}</code>
                    <h4 class="row-title text-truncate" :title="k.Nama_Paket">{{ k.Nama_Paket }}</h4>
                    <span v-if="k.Versi_Paket && k.Versi_Paket > 1" class="row-ver" :title="`Versi Paket v${k.Versi_Paket}.0`">
                        v{{ k.Versi_Paket }}.0
                    </span>
                </div>
                <span v-if="isCompleted" class="badge-done">
                    <i class="bi bi-check-circle-fill"></i> 100% Selesai
                </span>
            </div>

            <!-- Description snippet -->
            <p v-if="k.Deskripsi" class="row-desc text-truncate" :title="k.Deskripsi">
                {{ k.Deskripsi }}
            </p>

            <!-- Chips & Meta Row -->
            <div class="tr-meta">
                <span v-if="k.Kategori" class="meta-chip kat-chip" :style="{ '--kc': tema.color }">
                    <i class="bi" :class="tema.icon"></i> {{ k.Kategori }}
                </span>

                <!--
                    LENCANA KEPEMILIKAN. Departemen melekat pada DOKUMEN, dan
                    satu paket boleh memuat dokumen milik beberapa departemen —
                    karena itu yang tampil daftar, bukan satu nama.
                -->
                <span
                    v-if="pemilik.utama"
                    class="meta-chip dept-chip"
                    :title="pemilik.penuh"
                >
                    <i class="bi bi-diagram-3-fill"></i>
                    {{ pemilik.utama }}
                    <strong v-if="pemilik.sisa">+{{ pemilik.sisa }}</strong>
                </span>

                <span v-if="jenisUtama" class="meta-chip jenis-chip" :title="jenisPenuh">
                    <i class="bi bi-journals"></i> {{ jenisUtama }}
                </span>

                <span v-if="apqcUtama" class="meta-chip apqc-chip" :title="apqcPenuh">
                    <i class="bi bi-diagram-2"></i> {{ apqcUtama }}
                </span>

                <!-- Tenggat adalah sifat PENUGASAN. -->
                <span v-if="!rujukan" class="dl-chip" :class="tenggat.cls">
                    <i class="bi" :class="tenggat.icon"></i> {{ tenggat.text }}
                </span>
                <span v-if="k.Estimasi" class="dl-chip idle"> <i class="bi bi-clock"></i> ~{{ k.Estimasi }} mnt </span>
                <span v-if="rujukan && k.Total_Item" class="dl-chip idle" :title="isiPenuh">
                    <i class="bi bi-collection"></i> {{ isiRingkas }}
                </span>
                <StatusPill v-if="prio" :text="prio.label" :warna="prio.warna" />
                <span v-if="!rujukan && sumberLabel" class="meta-chip src-chip" :title="`Sumber penugasan: ${sumberLabel}`">
                    <i class="bi bi-person-badge"></i> {{ sumberLabel }}
                </span>
                <StatusPill v-if="rujukan && k.Baru" :text="labelPerubahan" variant="upd" />
                <StatusPill v-else-if="rujukan && k.Diperbarui" :text="labelPerubahan" variant="upd" />
                <span v-if="rujukan && k.Wajib" class="dl-chip warn" title="dokumen ini juga ditugaskan kepada Anda">
                    <i class="bi bi-shield-check"></i> Tugas Anda
                </span>
                <StatusPill v-if="k.Is_Diperbarui && !isCompleted" text="Diperbarui" variant="upd" />
                <!-- "Diperbarui" saja tak memberi tahu sisa pekerjaannya. -->
                <span v-if="!rujukan && k.Item_Perlu_Ulang > 0 && !isCompleted" class="dl-chip bt-ulang warn">
                    <i class="bi bi-arrow-repeat"></i> {{ k.Item_Perlu_Ulang }} item perlu dibaca ulang
                </span>
            </div>

            <!-- Progress Bar Section -->
            <div v-if="progres.tampil" class="tr-prog">
                <ProgressBar :value="k.Persen" :done="k.Item_Selesai" :total="progres.total" showLabel />
            </div>
        </div>

        <!-- Right Side CTA Action -->
        <div class="tr-action" @click.stop>
            <a :href="urlDetail" class="stitch-btn sm task-cta" :class="isCompleted ? 'secondary' : 'primary'">
                <span>{{ ctaTeks }}</span>
                <i class="bi" :class="isCompleted ? 'bi-eye-fill' : 'bi-arrow-right'"></i>
            </a>
        </div>
    </div>
</template>

<script>
import ProgressBar from '../ui/ProgressBar.vue';
import StatusPill from '../ui/StatusPill.vue';
import { SOP_URL, temaAksen } from '../../support/constants.js';
import { metaPrioritas } from '../../support/prioritas.js';

/**
 * BarisPaket — satu baris = satu PAKET (bentuk tampilan "Tabel").
 * Menampilkan thumbnail cover/ikon kategori, status kepatuhan, chip metadata,
 * tenggat waktu, progress bar, dan tombol aksi CTA responsif.
 */
export default {
    name: 'BarisPaket',
    components: { ProgressBar, StatusPill },
    props: {
        k: { type: Object, required: true },
        /**
         * Baris RUJUKAN (Public Document): paket yang boleh dibaca siapa saja,
         * bukan pekerjaan yang ditugaskan. Menyembunyikan tenggat dan memakai
         * penyebut progres yang berbeda — lihat computed `progres`.
         */
        rujukan: { type: Boolean, default: false },
    },
    emits: ['open'],
    data() {
        return { imgFailed: false };
    },
    computed: {
        /**
         * PENYEBUT PROGRES BERBEDA ANTARA DUA MODE, dan ini bukan detail kecil.
         *
         * Di Functional Document `Total_Item` berarti "item yang ditugaskan
         * kepada Anda", jadi ia pasangan sah bagi `Item_Selesai`. Di Public
         * Document `Total_Item` berarti "isi paket" — dipakai sebagai penyebut,
         * ia memberitahu penjelajah biasa bahwa ia "0 dari 5 selesai" atas
         * pekerjaan yang tak pernah dibebankan kepadanya. Angka penugasannya
         * ada di medan tersendiri, `Item_Tugas`, dan bar hanya muncul bila
         * angka itu benar-benar ada.
         */
        progres() {
            if (!this.rujukan) {
                return { tampil: !!this.k.Total_Item, total: this.k.Total_Item || 0 };
            }

            const tugas = this.k.Item_Tugas || 0;

            return { tampil: tugas > 0, total: tugas };
        },

        /** Komposisi isi paket: "3 dokumen · 1 kuis". */
        isi() {
            const peta = { DOKUMEN: 'dokumen', KUIS: 'kuis', VIDEO: 'video', TAUTAN: 'tautan' };

            return Object.entries(this.k.Isi || {})
                .filter(([, n]) => n > 0)
                .map(([t, n]) => `${n} ${peta[t] || t.toLowerCase()}`);
        },
        isiRingkas() {
            return this.isi.length ? this.isi.join(' · ') : `${this.k.Total_Item || 0} item`;
        },
        isiPenuh() {
            return `Isi paket: ${this.isiRingkas}`;
        },

        /*
         * "Baru" dan "Diperbarui" saling meniadakan, dan angkanya menyertai
         * lencana alih-alih berdiri sendiri: "Baru" tanpa jumlah menyuruh orang
         * membuka paket hanya untuk tahu seberapa besar perubahannya. Nol
         * sengaja tak ditulis — "Baru 0" membantah dirinya sendiri.
         */
        labelPerubahan() {
            const dasar = this.k.Baru ? 'Baru' : 'Diperbarui';

            return this.k.Item_Baru > 0 ? `${dasar} ${this.k.Item_Baru}` : dasar;
        },

        ctaTeks() {
            if (this.k.Cta) return this.k.Cta;
            if (this.rujukan) {
                if (this.isCompleted) return 'Baca Ulang';

                return (this.k.Persen || 0) > 0 ? 'Lanjutkan' : 'Buka Dokumen';
            }

            return this.isCompleted ? 'Lihat Dokumen' : 'Baca Sekarang';
        },
        tema() {
            return temaAksen(this.k.Nama_Paket);
        },
        hasCover() {
            return !!this.k.Cover_Url && !this.imgFailed;
        },
        prio() {
            return this.k.Prioritas ? metaPrioritas(this.k.Prioritas) : null;
        },

        /**
         * Departemen pemilik dokumen di dalam paket ini.
         *
         * Entri tanpa nama (dokumen yang belum bertuan) TIDAK disembunyikan
         * melainkan disebut "Tanpa pemilik". Membuangnya membuat paket berisi
         * satu dokumen bertuan dan tiga yang tidak tampil seolah seluruhnya
         * sudah rapi.
         */
        pemilik() {
            const daftar = (this.k.Departemen || []).map((d) => d.nama || 'Tanpa pemilik');
            if (!daftar.length) return { utama: '', sisa: 0, penuh: '' };

            return {
                utama: daftar[0],
                sisa: daftar.length - 1,
                penuh: daftar.length > 1 ? `Departemen pemilik: ${daftar.join(', ')}` : `Departemen pemilik: ${daftar[0]}`,
            };
        },

        /*
         * Jenis dokumen menampilkan yang TERTINGGI hirarkinya (level terkecil),
         * bukan yang pertama datang. Paket yang memuat sebuah Kebijakan beserta
         * instruksi kerja turunannya paling tepat dikenali sebagai Kebijakan —
         * dan urutan baris dari server memang sudah menaik menurut level, jadi
         * yang pertama sudah yang tertinggi.
         */
        jenisUtama() {
            const j = (this.k.Jenis_Dokumen || []).filter((x) => x.nama);

            return j.length ? j[0].nama : '';
        },
        jenisPenuh() {
            const j = (this.k.Jenis_Dokumen || []).map((x) => x.nama).filter(Boolean);

            return j.length ? `Jenis dokumen: ${j.join(', ')}` : '';
        },
        apqcUtama() {
            const a = (this.k.Apqc || []).filter((x) => x && x.nama);
            return a.length ? a[0].nama : '';
        },
        apqcPenuh() {
            const a = (this.k.Apqc || []).map((x) => x.nama).filter(Boolean);
            return a.length ? `Proses APQC: ${a.join(', ')}` : '';
        },
        sumberLabel() {
            const s = this.k.Sumber;
            if (!s || s === 'MANUAL') return '';
            const peta = {
                AUTO_RULE: 'Aturan Otomatis',
                JABATAN: 'Jabatan',
                DEPARTEMEN: 'Departemen',
                UNIT_KERJA: 'Unit Kerja',
            };
            return peta[s] || s;
        },
        isCompleted() {
            return (this.k.Persen || 0) >= 100;
        },
        urlDetail() {
            return SOP_URL.detail(this.k.Id_SOP_Paket);
        },
        tenggat() {
            if (this.isCompleted) {
                return { text: 'Tuntas', icon: 'bi-check2-all', cls: 'idle' };
            }
            if (!this.k.Tenggat) return { text: 'Tanpa tenggat', icon: 'bi-infinity', cls: 'idle' };
            const d = new Date(this.k.Tenggat);
            if (isNaN(d.getTime()))
                return { text: 'Tenggat ' + this.k.Tenggat, icon: 'bi-calendar-event', cls: 'idle' };
            const hariIni = new Date();
            hariIni.setHours(0, 0, 0, 0);
            const sisa = Math.ceil((d.getTime() - hariIni.getTime()) / 86400000);
            if (sisa < 0) return { text: `Lewat ${Math.abs(sisa)} hari`, icon: 'bi-exclamation-octagon', cls: 'bad' };
            if (sisa === 0) return { text: 'Jatuh tempo hari ini', icon: 'bi-clock-fill', cls: 'bad' };
            if (sisa <= 3) return { text: `${sisa} hari lagi`, icon: 'bi-clock-history', cls: 'warn' };

            return { text: `${sisa} hari lagi`, icon: 'bi-clock', cls: 'idle' };
        },
    },
};
</script>

<style scoped>
.task-row {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.85rem 1rem;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
}

.task-row.clickable {
    cursor: pointer;
}

.task-row.clickable:hover {
    background: var(--hcis-white);
    border-color: var(--row-accent, var(--hcis-indigo-500));
    transform: translateY(-2px);
    box-shadow:
        0 10px 24px -8px rgba(99, 102, 241, 0.2),
        0 4px 10px rgba(0, 0, 0, 0.03);
}

.task-row.is-completed {
    background: var(--hcis-slate-50);
    border-color: var(--hcis-slate-300);
}

.task-row.is-completed:hover {
    border-color: var(--hcis-emerald-500);
    box-shadow:
        0 10px 24px -8px rgba(16, 185, 129, 0.2),
        0 4px 10px rgba(0, 0, 0, 0.03);
}

.task-row.is-overdue {
    border-color: var(--hcis-red-300);
    background: #fff5f5;
}

.task-row.has-accent::before {
    content: '';
    position: absolute;
    left: 0;
    top: 10px;
    bottom: 10px;
    width: 4px;
    border-radius: 0 4px 4px 0;
    background: var(--row-accent, var(--hcis-indigo-500));
}

/* Thumbnail & Icon */
.tr-thumb {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    overflow: hidden;
    display: grid;
    place-items: center;
    background: var(--hcis-slate-100);
    box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.06);
}

.tr-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tr-ico {
    width: 100%;
    height: 100%;
    display: grid;
    place-items: center;
    font-size: 1.2rem;
    border-radius: 12px;
}

/* Main Content */
.tr-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.tr-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.row-code {
    flex-shrink: 0;
    font-family: var(--font-mono, monospace);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-indigo-700);
    background: rgba(99, 102, 241, 0.09);
    padding: 0.08rem 0.38rem;
    border-radius: 5px;
}

.row-ver {
    flex-shrink: 0;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-indigo-500);
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    padding: 0.05rem 0.35rem;
    border-radius: 4px;
}

.row-desc {
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
    margin: 0;
    line-height: 1.35;
}

.row-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    margin: 0;
    line-height: 1.3;
}

.badge-done {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    white-space: nowrap;
}

/* Meta Chips */
.tr-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
}

.kat-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--kc, var(--hcis-indigo-600));
    background: rgba(99, 102, 241, 0.08);
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    border: 1px solid rgba(99, 102, 241, 0.16);
}

.dept-chip,
.jenis-chip,
.apqc-chip,
.src-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 14rem;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    background: rgba(100, 116, 139, 0.08);
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    border: 1px solid rgba(100, 116, 139, 0.18);
}

.apqc-chip {
    color: #0f766e;
    background: rgba(15, 118, 110, 0.08);
    border-color: rgba(15, 118, 110, 0.18);
}

.src-chip {
    color: var(--hcis-sky-600);
    background: rgba(2, 132, 199, 0.08);
    border-color: rgba(2, 132, 199, 0.18);
}

.dept-chip strong {
    font-weight: 800;
    color: var(--hcis-slate-700);
}

.dl-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.dl-chip.warn {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-700);
}

.dl-chip.bad {
    background: var(--hcis-red-100);
    color: var(--hcis-red-700);
}

/* Progress bar container */
.tr-prog {
    margin-top: 0.1rem;
}

/* Action button */
.tr-action {
    flex-shrink: 0;
    align-self: center;
}

.task-cta {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.85rem;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s ease;
}

/* Responsive mobile adjustments */
@media (max-width: 575px) {
    .task-row {
        flex-wrap: wrap;
        gap: 0.5rem 0.65rem;
        padding: 0.65rem 0.75rem;
        border-radius: 12px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .task-row:active {
        transform: scale(0.99);
    }
    .tr-thumb {
        width: 38px;
        height: 38px;
        border-radius: 8px;
    }
    .tr-ico {
        font-size: 1rem;
    }
    .row-title {
        font-size: 0.86rem;
    }
    .row-code {
        font-size: 0.66rem;
        padding: 0.06rem 0.3rem;
    }
    .tr-meta {
        gap: 0.25rem;
    }
    .kat-chip,
    .dept-chip,
    .jenis-chip,
    .apqc-chip,
    .src-chip,
    .dl-chip {
        font-size: 0.66rem;
        padding: 0.12rem 0.4rem;
    }
    .tr-action {
        width: 100%;
        margin-top: 0.15rem;
    }
    .task-cta {
        width: 100%;
        justify-content: center;
        padding: 0.45rem 0.75rem;
        font-size: 0.78rem;
        border-radius: 8px;
    }
}
</style>

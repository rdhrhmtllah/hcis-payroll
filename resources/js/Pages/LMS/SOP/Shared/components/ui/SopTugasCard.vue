<template>
    <article
        class="tg-card"
        :class="{
            'is-busy': busy,
            'is-done': (tugas.Persen || 0) >= 100,
            'is-late': !!tugas.Is_Terlambat,
            'is-kritis': tugas.Prioritas === 'KRITIS',
        }"
        :style="{ '--accent': theme.color }"
        @click="onCardClick"
    >
        <!-- Cover Banner -->
        <div class="tg-cover" :class="{ 'has-img': hasCover, 'is-fallback': !hasCover }" :style="!hasCover ? fallbackStyle : null">
            <img v-if="hasCover" :src="tugas.Cover_Url" alt="" loading="lazy" @error="imgFailed = true" />
            <div v-else class="tg-cover-ico-wrap">
                <i class="bi tg-cover-ico" :class="theme.icon"></i>
            </div>

            <!-- Badges Kiri Atas -->
            <div class="tg-cover-badges">
                <span v-if="topBadge" class="tg-badge" :class="topBadge.cls">
                    <i class="bi" :class="topBadge.icon"></i> {{ topBadge.text }}
                </span>
                <span v-if="tugas.Kode_Paket" class="tg-badge code-badge" :title="`Kode Paket: ${tugas.Kode_Paket}`">
                    <code>{{ tugas.Kode_Paket }}</code>
                </span>
                <span v-if="tugas.Versi_Paket && tugas.Versi_Paket > 1" class="tg-badge ver-badge" :title="`Versi Paket v${tugas.Versi_Paket}.0`">
                    <i class="bi bi-layers-fill"></i> v{{ tugas.Versi_Paket }}.0
                </span>
                <span v-if="rujukan && tugas.Wajib" class="tg-badge rev" title="dokumen ini juga ditugaskan kepada Anda">
                    <i class="bi bi-shield-check"></i> Tugas Anda
                </span>
                <span v-if="!rujukan && tugas.Item_Perlu_Ulang > 0" class="tg-badge rev" title="Ada item yang perlu dipelajari ulang">
                    <i class="bi bi-arrow-repeat"></i> {{ tugas.Item_Perlu_Ulang }} Revisi
                </span>
            </div>

            <!-- Estimasi / Status Kanan Atas -->
            <div class="tg-cover-right">
                <span v-if="estimasiTeks" class="tg-est" :title="`Estimasi waktu baca: ${estimasiTeks}`">
                    <i class="bi bi-clock"></i> {{ estimasiTeks }}
                </span>
                <!--
                    AKSI MILIK HALAMAN, BUKAN MILIK KARTU. Favorit dan "intip"
                    hanya ada di Public Document; menaruhnya sebagai prop di
                    sini memaksa setiap pemanggil lain (Functional Document,
                    Monitoring) ikut memikirkan tombol yang tak pernah mereka
                    tampilkan. Lewat slot, kartu tetap satu dan halamanlah yang
                    memutuskan isinya.
                -->
                <span v-if="$slots.aksi" class="tg-aksi" @click.stop>
                    <slot name="aksi" :tugas="tugas" />
                </span>
            </div>
        </div>

        <!-- Body Section -->
        <div class="tg-body">
            <!-- Taxonomy Badges Row (Tersusun rapi dalam 1 garis hierarki yang tenang) -->
            <div class="tg-chips">
                <!-- Departemen Pemilik -->
                <span v-if="pemilik.utama" class="tg-chip tg-chip-dept" :title="pemilik.penuh">
                    <i class="bi bi-diagram-3-fill"></i>
                    <span class="tg-chip-text">{{ pemilik.utama }}</span>
                    <strong v-if="pemilik.sisa">+{{ pemilik.sisa }}</strong>
                </span>

                <!-- Jenis Dokumen Utama -->
                <span v-if="jenisUtama" class="tg-chip tg-chip-jenis" :title="jenisPenuh">
                    <i class="bi bi-journals"></i>
                    <span class="tg-chip-text">{{ jenisUtama }}</span>
                </span>

                <!-- Status Prioritas -->
                <StatusPill v-if="prio" :text="prio.label" :warna="prio.warna" />

                <!-- Sumber Penugasan (jika non-manual) -->
                <span v-if="!rujukan && sumberLabel" class="tg-chip tg-chip-src" :title="`Sumber penugasan: ${sumberLabel}`">
                    <i class="bi bi-person-badge"></i>
                    <span class="tg-chip-text">{{ sumberLabel }}</span>
                </span>

                <StatusPill v-if="!rujukan && tugas.Is_Diperbarui" text="Diperbarui" variant="upd" />
                <StatusPill v-if="rujukan && perubahan" :text="perubahan" variant="upd" />
            </div>

            <!-- Judul & Deskripsi -->
            <div class="tg-main-content">
                <h3 class="tg-title" :title="tugas.Nama_Paket">{{ tugas.Nama_Paket }}</h3>
                <p v-if="tugas.Deskripsi" class="tg-desc" :title="tugas.Deskripsi">
                    {{ tugas.Deskripsi }}
                </p>
            </div>

            <!-- Meta Strip (Tenggat, Isi Komposisi, & APQC terpadu) -->
            <div class="tg-meta">
                <!-- Tenggat Waktu -->
                <span v-if="!rujukan" class="tg-meta-item" :class="deadline.cls" :title="`Tenggat: ${deadline.text}`">
                    <i class="bi" :class="deadline.icon"></i>
                    <span class="text-truncate">{{ deadline.text }}</span>
                </span>

                <!-- Pembatas halus jika ada tenggat & isi -->
                <span v-if="!rujukan" class="tg-meta-sep" aria-hidden="true">·</span>

                <!-- Komposisi Isi Paket -->
                <span class="tg-meta-item idle" :title="`Komposisi isi paket: ${isiRingkas}`">
                    <i class="bi bi-collection"></i>
                    <span class="text-truncate">{{ isiRingkas }}</span>
                </span>

                <!-- APQC Proses (Ditata di baris meta agar tidak merusak baris tag atas) -->
                <template v-if="apqcUtama">
                    <span class="tg-meta-sep" aria-hidden="true">·</span>
                    <span class="tg-meta-item tg-meta-apqc" :title="apqcPenuh">
                        <i class="bi bi-diagram-2"></i>
                        <span class="tg-apqc-text">{{ apqcUtama }}</span>
                    </span>
                </template>
            </div>

            <!-- HcisProgress Reusable Component (Tanpa Animasi/Glow) -->
            <div class="tg-progress-wrap mt-auto pt-2">
                <HcisProgress
                    v-if="progres.tampil"
                    :value="tugas.Persen || 0"
                    :label="`${tugas.Item_Selesai || 0}/${progres.total} item selesai`"
                    :sentiment="progressSentiment"
                    size="sm"
                    :glow="false"
                    :animated="false"
                    :striped="false"
                    show-value
                />
                <div v-else class="tg-noitem">{{ rujukan ? 'Belum ditugaskan — bebas dibaca' : 'Tanpa item wajib' }}</div>
            </div>
        </div>

        <!-- Footer Action (HcisButton) -->
        <div class="tg-foot" @click.stop>
            <HcisButton
                :href="detailUrl"
                :label="buttonText"
                :variant="mode === 'admin' ? 'outline' : 'primary'"
                size="sm"
                block
                trailing-icon="arrow-right"
            />
        </div>

        <!-- Busy Overlay -->
        <transition name="ov-fade">
            <div v-if="busy" class="tg-overlay">
                <div class="spinner-border spinner-border-sm"></div>
                <span>Memuat…</span>
            </div>
        </transition>
    </article>
</template>

<script>
import { HcisButton, HcisProgress } from '@/components/ui';
import StatusPill from './StatusPill.vue';
import { temaAksen, SOP_URL } from '../../support/constants.js';
import { metaPrioritas } from '../../support/prioritas.js';

/**
 * SopTugasCard — Kartu paket dokumen reusable standar (Functional Document & Monitoring Dokumen).
 * Menggunakan HcisProgress dan HcisButton dari HCIS Design System.
 */
export default {
    name: 'SopTugasCard',
    components: { StatusPill, HcisButton, HcisProgress },
    props: {
        tugas: { type: Object, required: true },
        busy: { type: Boolean, default: false },
        href: { type: String, default: '' },
        actionLabel: { type: String, default: '' },
        mode: { type: String, default: 'employee' }, // 'employee' | 'admin'
        /**
         * Kartu RUJUKAN (Public Document): paket yang bebas dibaca, bukan
         * pekerjaan yang ditugaskan. Menyembunyikan tenggat & lencana
         * kepatuhan, dan memakai penyebut progres yang berbeda — lihat
         * computed `progres`.
         *
         * Prop tersendiri, bukan nilai ketiga pada `mode`: `mode` sudah dipakai
         * memilih gaya tombol admin, dan menumpuk dua sumbu makna pada satu
         * prop membuat kombinasi "admin + rujukan" mustahil dinyatakan.
         */
        rujukan: { type: Boolean, default: false },
    },
    emits: ['open'],
    data() {
        return { imgFailed: false };
    },
    computed: {
        theme() {
            return temaAksen(this.tugas.Nama_Paket);
        },
        prio() {
            return this.tugas.Prioritas ? metaPrioritas(this.tugas.Prioritas) : null;
        },
        estimasiTeks() {
            const val = this.tugas.Estimasi;
            if (!val) return '';
            if (typeof val === 'string' && /[a-zA-Z]/.test(val)) {
                return val.startsWith('~') ? val : `~${val}`;
            }
            const raw = Number(val);
            if (isNaN(raw) || raw <= 0) return '';
            // Sanitasi jika data sangat besar (mis. detik atau data dummy ekstrem seperti 132132)
            if (raw > 10000) {
                const mnt = Math.round(raw / 60);
                if (mnt < 60) return `~${mnt} mnt`;
                const jam = Math.floor(mnt / 60);
                return jam > 24 ? `~${Math.round(jam / 24)} hari` : `~${jam} jam`;
            }
            if (raw < 60) return `~${raw} mnt`;
            const jam = Math.floor(raw / 60);
            const sisa = raw % 60;
            if (jam >= 24) {
                return `~${Math.round(jam / 24)} hari`;
            }
            return sisa ? `~${jam}j ${sisa}m` : `~${jam} jam`;
        },

        /**
         * Departemen pemilik dokumen di dalam paket ini.
         *
         * Dipendekkan jadi "HSE +2" dengan daftar penuh di tooltip: kartu grid
         * jauh lebih sempit daripada baris daftar, dan tiga nama departemen
         * akan mendorong judul paket turun sehingga tinggi kartu berbeda-beda
         * dalam satu grid.
         *
         * Entri tanpa nama disebut "Tanpa pemilik", bukan dibuang — dokumen
         * yang belum bertuan adalah keadaan yang perlu terlihat.
         */
        pemilik() {
            const daftar = (this.tugas.Departemen || []).map((d) => d.nama || 'Tanpa pemilik');
            if (!daftar.length) return { utama: '', sisa: 0, penuh: '' };

            return {
                utama: daftar[0],
                sisa: daftar.length - 1,
                penuh: `Departemen pemilik: ${daftar.join(', ')}`,
            };
        },
        jenisUtama() {
            const j = (this.tugas.Jenis_Dokumen || []).filter((x) => x && x.nama);
            return j.length ? j[0].nama : '';
        },
        jenisPenuh() {
            const j = (this.tugas.Jenis_Dokumen || []).map((x) => x.nama).filter(Boolean);
            return j.length ? `Jenis dokumen: ${j.join(', ')}` : '';
        },
        apqcUtama() {
            const a = (this.tugas.Apqc || []).filter((x) => x && x.nama);
            return a.length ? a[0].nama : '';
        },
        apqcPenuh() {
            const a = (this.tugas.Apqc || []).map((x) => x.nama).filter(Boolean);
            return a.length ? `Proses APQC: ${a.join(', ')}` : '';
        },
        sumberLabel() {
            const s = this.tugas.Sumber;
            if (!s || s === 'MANUAL') return '';
            const peta = {
                AUTO_RULE: 'Aturan Otomatis',
                JABATAN: 'Jabatan',
                DEPARTEMEN: 'Departemen',
                UNIT_KERJA: 'Unit Kerja',
            };
            return peta[s] || s;
        },
        progressSentiment() {
            if ((this.tugas.Persen || 0) >= 100) return 'success';
            if (this.tugas.Is_Terlambat) return 'danger';
            if (this.tugas.Prioritas === 'KRITIS') return 'warning';
            return 'brand';
        },
        hasCover() {
            return !!this.tugas.Cover_Url && !this.imgFailed;
        },
        fallbackStyle() {
            return { background: `linear-gradient(135deg, ${this.theme.color}, ${this.theme.color}bb)` };
        },
        detailUrl() {
            if (this.href) return this.href;
            return SOP_URL.detail(this.tugas.Id_SOP_Paket);
        },
        buttonText() {
            if (this.actionLabel) return this.actionLabel;
            if (this.mode === 'admin') return 'Detail';

            return (
                this.tugas.Cta ||
                (this.tugas.Persen >= 100 ? 'Baca Ulang' : this.tugas.Persen > 0 ? 'Lanjutkan' : 'Buka Dokumen')
            );
        },
        daysLeft() {
            if (!this.tugas.Tenggat) return 9999;
            const d = new Date(this.tugas.Tenggat);
            if (isNaN(d.getTime())) return 9998;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return Math.ceil((d.getTime() - today.getTime()) / 86400000);
        },
        deadline() {
            const k = this.tugas;
            if (!k.Tenggat) return { text: 'Tanpa tenggat', icon: 'bi-infinity', cls: 'idle' };
            const d = new Date(k.Tenggat);
            if (isNaN(d.getTime())) return { text: 'Tenggat ' + k.Tenggat, icon: 'bi-calendar-event', cls: 'idle' };
            const days = this.daysLeft;
            if (days < 0) return { text: `Lewat ${Math.abs(days)} hari`, icon: 'bi-exclamation-octagon', cls: 'bad' };
            if (days === 0) return { text: 'Jatuh tempo hari ini', icon: 'bi-clock-fill', cls: 'bad' };
            if (days <= 3) return { text: `${days} hari lagi`, icon: 'bi-clock-history', cls: 'warn' };
            return { text: `${days} hari lagi`, icon: 'bi-clock', cls: 'idle' };
        },
        topBadge() {
            /* Di rak rujukan, "Selesai/Terlambat" adalah kabar tentang
               PENUGASAN — dan orang yang sekadar menjelajah tak punya satu pun.
               Yang layak menonjol di sana adalah kebaruan dokumennya. */
            if (this.rujukan) {
                if (this.tugas.Baru) return { text: this.perubahan, icon: 'bi-stars', cls: 'upd' };
                if (this.tugas.Diperbarui) return { text: this.perubahan, icon: 'bi-arrow-repeat', cls: 'upd' };

                return null;
            }

            if ((this.tugas.Persen || 0) >= 100) return { text: 'Selesai', icon: 'bi-check-circle-fill', cls: 'ok' };
            if (this.tugas.Is_Terlambat) return { text: 'Terlambat', icon: 'bi-exclamation-octagon-fill', cls: 'bad' };
            if (this.tugas.Is_Diperbarui) return { text: 'Diperbarui', icon: 'bi-arrow-repeat', cls: 'upd' };

            return null;
        },

        /**
         * PENYEBUT PROGRES BERBEDA ANTARA DUA MODE.
         *
         * `Total_Item` berarti "item yang ditugaskan" di Functional Document,
         * tetapi "isi paket" di Public Document. Memakainya sebagai penyebut di
         * rak rujukan akan memberitahu penjelajah biasa bahwa ia "0 dari 5
         * selesai" atas pekerjaan yang tak pernah dibebankan kepadanya.
         */
        progres() {
            if (!this.rujukan) {
                return { tampil: !!this.tugas.Total_Item, total: this.tugas.Total_Item || 0 };
            }

            const tugas = this.tugas.Item_Tugas || 0;

            return { tampil: tugas > 0, total: tugas };
        },

        /** Komposisi isi paket: "3 dokumen · 1 kuis". */
        isiRingkas() {
            const peta = { DOKUMEN: 'dokumen', KUIS: 'kuis', VIDEO: 'video', TAUTAN: 'tautan' };
            const bagian = Object.entries(this.tugas.Isi || {})
                .filter(([, n]) => n > 0)
                .map(([t, n]) => `${n} ${peta[t] || t.toLowerCase()}`);

            return bagian.length ? bagian.join(' · ') : `${this.tugas.Total_Item || 0} item`;
        },

        /*
         * "Baru" dan "Diperbarui" saling meniadakan, dan angkanya menyertai
         * lencana alih-alih berdiri sendiri: "Baru" tanpa jumlah menyuruh orang
         * membuka paket hanya untuk tahu seberapa besar perubahannya. Nol
         * sengaja tak ditulis — "Baru 0" membantah dirinya sendiri.
         */
        perubahan() {
            if (!this.tugas.Baru && !this.tugas.Diperbarui) return '';
            const dasar = this.tugas.Baru ? 'Baru' : 'Diperbarui';

            return this.tugas.Item_Baru > 0 ? `${dasar} ${this.tugas.Item_Baru}` : dasar;
        },
    },
    watch: {
        'tugas.Cover_Url'() {
            this.imgFailed = false;
        },
    },
    methods: {
        onCardClick() {
            if (this.busy) return;
            this.$emit('open', this.tugas);
            if (this.href) {
                window.location.href = this.href;
            }
        },
    },
};
</script>

<style scoped>
.tg-card {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.tg-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px -8px rgba(99, 102, 241, 0.22);
    border-color: var(--hcis-indigo-300);
}

.tg-card.is-busy {
    opacity: 0.7;
    cursor: progress;
}

.tg-card.is-busy:hover {
    transform: none;
}

.tg-card.is-done {
    border-color: rgba(16, 185, 129, 0.35);
}

.tg-card.is-done::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    box-shadow: inset 0 0 0 1.5px rgba(16, 185, 129, 0.3);
    border-radius: 16px;
    z-index: 2;
}

.tg-card.is-late {
    border-color: rgba(239, 68, 68, 0.35);
}

.tg-cover {
    position: relative;
    height: 98px;
    overflow: hidden;
    display: grid;
    place-items: center;
}

.tg-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.tg-cover.is-fallback::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.25) 1.2px, transparent 1.2px);
    background-size: 12px 12px;
}

.tg-cover-ico-wrap {
    position: relative;
    z-index: 1;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.38);
    display: grid;
    place-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.tg-cover-ico {
    color: rgba(255, 255, 255, 0.95);
    font-size: 1.4rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.15));
}

.tg-cover-badges {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-wrap: wrap;
    max-width: calc(100% - 95px);
}

.tg-cover-right {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-shrink: 0;
}

.tg-aksi {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.tg-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(6px);
    color: var(--hcis-slate-600);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.12);
}

.tg-badge.ok {
    color: var(--hcis-emerald-700);
    background: rgba(236, 253, 245, 0.95);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.tg-badge.bad {
    color: var(--hcis-red-700);
    background: rgba(254, 242, 242, 0.95);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.tg-badge.upd {
    color: var(--hcis-sky-700);
    background: rgba(240, 249, 255, 0.95);
    border: 1px solid rgba(14, 165, 233, 0.3);
}

.tg-badge.rev {
    color: var(--hcis-amber-700);
    background: rgba(254, 243, 199, 0.95);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.tg-badge.code-badge {
    background: rgba(15, 23, 42, 0.85);
    color: var(--hcis-slate-50);
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.tg-badge.code-badge code {
    font-family: var(--font-mono, monospace);
    font-size: 0.64rem;
    font-weight: 700;
    color: inherit;
}

.tg-badge.ver-badge {
    background: rgba(99, 102, 241, 0.92);
    color: var(--hcis-white);
    border: 1px solid rgba(255, 255, 255, 0.25);
}

.tg-est {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    font-size: 0.65rem;
    font-weight: 750;
    padding: 0.2rem 0.52rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #fff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.tg-body {
    padding: 0.75rem 0.9rem 0.5rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

/* Taxonomy Badges Row */
.tg-chips {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.15rem;
    min-height: 22px;
}

.tg-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    font-size: 0.66rem;
    font-weight: 700;
    padding: 0.14rem 0.45rem;
    border-radius: 6px;
    line-height: 1.25;
    max-width: 140px;
}

.tg-chip-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tg-chip-dept {
    color: var(--hcis-slate-600);
    background: rgba(100, 116, 139, 0.08);
    border: 1px solid rgba(100, 116, 139, 0.18);
    max-width: 130px;
}

.tg-chip-jenis {
    color: var(--hcis-indigo-700);
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.18);
    max-width: 110px;
}

.tg-chip-src {
    color: var(--hcis-sky-700);
    background: rgba(2, 132, 199, 0.08);
    border: 1px solid rgba(2, 132, 199, 0.18);
    max-width: 110px;
}

/* Harmonize StatusPill inside tg-chips */
.tg-chips :deep(.sop-pill) {
    font-size: 0.65rem !important;
    font-weight: 700 !important;
    padding: 0.12rem 0.45rem !important;
    border-radius: 6px !important;
    line-height: 1.25 !important;
}
.tg-chips :deep(.sop-pill .dot) {
    width: 5px !important;
    height: 5px !important;
}

/* Title & Description */
.tg-main-content {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.tg-title {
    font-size: 0.94rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    margin: 0;
    line-height: 1.35;
    letter-spacing: -0.01em;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}

.tg-desc {
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
    margin: 0;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}

/* Meta Strip (Tenggat, Isi Komposisi, & APQC terpadu) */
.tg-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
    padding: 0.38rem 0.55rem;
    margin-top: 0.25rem;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    font-size: 0.68rem;
    line-height: 1.25;
}

.tg-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 650;
    color: var(--hcis-slate-600);
    min-width: 0;
}

.tg-meta-item i {
    font-size: 0.74rem;
    flex-shrink: 0;
    color: var(--hcis-slate-400);
}

.tg-meta-item.idle {
    color: var(--hcis-slate-600);
}

.tg-meta-item.bad {
    color: var(--hcis-red-600);
    font-weight: 750;
}
.tg-meta-item.bad i {
    color: var(--hcis-red-600);
}

.tg-meta-item.warn {
    color: var(--hcis-amber-700);
    font-weight: 700;
}
.tg-meta-item.warn i {
    color: var(--hcis-amber-600);
}

.tg-meta-apqc {
    color: #0f766e;
    max-width: 140px;
}
.tg-meta-apqc i {
    color: #0f766e;
}
.tg-apqc-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tg-meta-sep {
    color: var(--hcis-slate-300);
    font-size: 0.75rem;
    flex-shrink: 0;
    user-select: none;
}

.tg-noitem {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    margin-top: 0.4rem;
}

.tg-progress-wrap {
    margin-top: auto;
    padding-top: 0.45rem;
}

.tg-progress-wrap :deep(.hcis-progress__fill) {
    transition: none !important;
    box-shadow: none !important;
}

.tg-progress-wrap :deep(.hcis-progress__shimmer) {
    display: none !important;
    animation: none !important;
}

.tg-foot {
    padding: 0.4rem 0.9rem 0.75rem;
}

.tg-overlay {
    position: absolute;
    inset: 0;
    z-index: 3;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(2px);
    display: grid;
    place-items: center;
    gap: 0.4rem;
    grid-auto-flow: row;
    color: var(--hcis-indigo-600);
    font-weight: 700;
    font-size: 0.82rem;
}

.ov-fade-enter-active,
.ov-fade-leave-active {
    transition: opacity 0.2s ease;
}

.ov-fade-enter-from,
.ov-fade-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .tg-card {
        transition: none;
    }
    .tg-card:hover {
        transform: none;
    }
}

/* ===== Compact Responsive di Mobile ===== */
@media (max-width: 575px) {
    .tg-card {
        border-radius: 14px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .tg-card:active {
        transform: scale(0.985);
    }
    .tg-cover {
        height: 72px;
    }
    .tg-cover-ico-wrap {
        width: 34px;
        height: 34px;
        border-radius: 10px;
    }
    .tg-cover-ico {
        font-size: 1.15rem;
    }
    .tg-cover-badges {
        top: 0.35rem;
        left: 0.35rem;
        max-width: calc(100% - 85px);
        gap: 0.2rem;
    }
    .tg-cover-right {
        top: 0.35rem;
        right: 0.35rem;
        gap: 0.25rem;
    }
    .tg-est {
        font-size: 0.6rem;
        padding: 0.15rem 0.45rem;
    }
    .tg-body {
        padding: 0.6rem 0.75rem 0.45rem;
        gap: 0.25rem;
    }
    .tg-title {
        font-size: 0.88rem;
        line-height: 1.28;
    }
    .tg-desc {
        font-size: 0.72rem;
        -webkit-line-clamp: 1;
    }
    .tg-chips {
        gap: 0.25rem;
        margin-bottom: 0.2rem;
    }
    .tg-chip {
        font-size: 0.62rem;
        padding: 0.1rem 0.38rem;
    }
    .tg-meta {
        padding: 0.3rem 0.45rem;
        gap: 0.35rem;
        font-size: 0.64rem;
    }
    .tg-meta-apqc {
        max-width: 100px;
    }
    .tg-foot {
        padding: 0.35rem 0.75rem 0.6rem;
    }
    .tg-foot :deep(.hcis-btn) {
        min-height: 34px !important;
        font-size: 0.78rem !important;
    }
}
</style>

<template>
    <section v-if="wajib && tercatat" class="ab" :class="{ buka }">
        <!-- Kepala = tombol accordion. Ringkasannya memuat waktu + akurasi,
             karena accordion yang tertutup tetap harus MENJAWAB — kalau isinya
             cuma judul, orang wajib membukanya untuk tahu apa pun. -->
        <button class="ab-kepala" :aria-expanded="String(buka)" aria-controls="ab-isi" @click="buka = !buka">
            <span class="ab-ikon"><i class="bi bi-shield-check"></i></span>

            <span class="ab-teks">
                <span class="ab-judul">Presensi Kehadiran</span>
                <span class="ab-ringkas">
                    <span class="ab-ringkas-i"><i class="bi bi-clock-history"></i>{{ waktuSingkat }}</span>
                    <span v-if="absensi.Akurasi_Meter" class="ab-ringkas-i tnum">
                        <i class="bi bi-geo-alt-fill"></i>±{{ Math.round(absensi.Akurasi_Meter) }} m
                    </span>
                </span>
            </span>

            <span class="ab-pil"><i class="bi bi-patch-check-fill"></i> <span class="ab-pil-t">Valid</span></span>
            <span class="ab-chevron"><i class="bi bi-chevron-down"></i></span>
        </button>

        <transition name="ab-lipat" @enter="bukaIsi" @after-enter="bebaskan" @leave="tutupIsi">
            <div v-show="buka" id="ab-isi" class="ab-isi">
                <div class="ab-badan">
                    <a
                        v-if="absensi.Foto_Url"
                        :href="absensi.Foto_Url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="ab-foto"
                        title="Buka foto ukuran penuh"
                    >
                        <img :src="absensi.Foto_Url" alt="Foto bukti presensi" />
                        <span class="ab-foto-lencana"><i class="bi bi-arrows-fullscreen"></i> Lihat foto</span>
                    </a>
                    <div v-else class="ab-foto-kosong">
                        <i class="bi bi-image"></i>
                        <span>Foto tidak tersedia</span>
                    </div>

                    <div class="ab-grid">
                        <div class="ab-fakta">
                            <span class="ab-fakta-ico"><i class="bi bi-clock-history"></i></span>
                            <span class="min-w-0">
                                <span class="ab-fakta-label">Waktu presensi</span>
                                <strong class="ab-fakta-nilai">{{ waktu }}</strong>
                            </span>
                        </div>

                        <div class="ab-fakta">
                            <span class="ab-fakta-ico gps"><i class="bi bi-geo-alt-fill"></i></span>
                            <span class="min-w-0">
                                <span class="ab-fakta-label">Akurasi GPS</span>
                                <span class="ab-fakta-baris">
                                    <strong class="ab-fakta-nilai tnum">{{ akurasiTeks }}</strong>
                                    <span
                                        v-if="absensi.Akurasi_Meter"
                                        class="ab-tag"
                                        :class="absensi.Lokasi_Meragukan ? 'ragu' : 'ok'"
                                    >
                                        {{ absensi.Lokasi_Meragukan ? 'Sinyal rendah' : 'Presisi tinggi' }}
                                    </span>
                                </span>
                            </span>
                        </div>

                        <div class="ab-fakta luas">
                            <span class="ab-fakta-ico kompas"><i class="bi bi-compass"></i></span>
                            <span class="min-w-0">
                                <span class="ab-fakta-label">Koordinat</span>
                                <strong class="ab-fakta-nilai tnum">{{ koordinat }}</strong>
                            </span>
                            <a
                                v-if="adaKoordinat"
                                :href="mapsUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ab-maps"
                            >
                                <i class="bi bi-geo-alt-fill"></i> Google Maps
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </section>
</template>

<script>
import axios from 'axios';

/**
 * Kartu BUKTI presensi di halaman dokumen — accordion, tertutup secara bawaan.
 *
 * Kenapa tertutup: pembaca datang untuk membaca dokumen, bukan memeriksa buktinya
 * sendiri. Bukti perlu ADA (audit, sengketa, rasa tenang) tapi tak perlu
 * menempati ruang di atas isi setiap kali halaman dibuka.
 *
 * Tapi accordion yang tertutup HARUS tetap menjawab: kepalanya membawa waktu
 * presensi dan akurasi. Accordion yang isinya cuma judul memaksa orang membuka
 * demi tahu apa pun — itu menambah klik, bukan mengurangi kebisingan.
 *
 * Pilihan buka/tutup SENGAJA tidak disimpan: yang diminta adalah bawaan
 * tertutup, dan mengingat pilihan berarti kunjungan kedua tak lagi tertutup.
 *
 * Animasinya digerakkan JS (tinggi diukur saat transisi), bukan max-height
 * tebakan atau grid-template-rows 0fr→1fr — keduanya masing-masing memotong isi
 * yang lebih tinggi dari dugaan atau tak didukung peramban lama.
 */
export default {
    name: 'SopAbsensiGate',
    props: {
        idPaket: { type: String, required: true },
    },
    data() {
        return { wajib: false, absensi: {}, buka: false };
    },
    computed: {
        tercatat() {
            return !!this.absensi.Checktime;
        },
        adaKoordinat() {
            return (
                this.absensi.Latitude !== null &&
                this.absensi.Latitude !== undefined &&
                this.absensi.Longitude !== null &&
                this.absensi.Longitude !== undefined
            );
        },
        koordinat() {
            if (!this.adaKoordinat) return '—';

            return `${Number(this.absensi.Latitude).toFixed(6)}, ${Number(this.absensi.Longitude).toFixed(6)}`;
        },
        mapsUrl() {
            return `https://www.google.com/maps?q=${encodeURIComponent(this.absensi.Latitude)},${encodeURIComponent(this.absensi.Longitude)}`;
        },
        akurasiTeks() {
            return this.absensi.Akurasi_Meter ? `±${Math.round(this.absensi.Akurasi_Meter)} m` : '—';
        },
        tanggal() {
            const t = this.absensi.Checktime;
            if (!t) return null;
            const d = new Date(t);

            return isNaN(d.getTime()) ? null : d;
        },
        waktu() {
            if (!this.tanggal) return this.absensi.Checktime || '—';

            return this.tanggal.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        /** Versi pendek untuk kepala accordion — tahun dibuang, sudah tersirat. */
        waktuSingkat() {
            if (!this.tanggal) return '—';

            return this.tanggal.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
    },
    mounted() {
        this.muatStatus();
    },
    methods: {
        async muatStatus() {
            try {
                const r = await axios.get(`/api/v1/sop/${this.idPaket}/absensi`);
                if (!r.data.success) return;
                this.wajib = r.data.result.wajib;
                this.absensi = r.data.result.absensi || {};
            } catch (e) {
                /* kartu tak tampil bila status tak terbaca */
            }
        },

        /* ── Animasi lipat: tinggi diukur, tidak ditebak ── */
        bukaIsi(el) {
            el.style.height = '0px';
            void el.offsetHeight; // paksa reflow agar transisi punya titik awal
            el.style.height = `${el.scrollHeight}px`;
        },
        bebaskan(el) {
            // Kembali ke auto setelah transisi: nilai tetap akan memotong isi
            // bila nanti tingginya berubah (mis. teks membungkus saat layar diputar).
            el.style.height = 'auto';
        },
        tutupIsi(el) {
            el.style.height = `${el.scrollHeight}px`;
            void el.offsetHeight;
            el.style.height = '0px';
        },
    },
};
</script>

<style scoped>
.min-w-0 {
    min-width: 0;
}

.ab {
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    background: #fff;
    /* margin-bottom: 1rem; */
    overflow: hidden;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}
.ab.buka {
    border-color: rgba(16, 185, 129, 0.34);
    box-shadow: 0 10px 28px -18px rgba(16, 185, 129, 0.4);
}

/* ══ Kepala (tombol) ══ */
.ab-kepala {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    width: 100%;
    text-align: left;
    padding: 0.75rem 0.9rem;
    border: 0;
    background: transparent;
    cursor: pointer;
    transition: background 0.16s ease;
}
.ab-kepala:hover {
    background: var(--hcis-slate-50);
}
.ab-kepala:focus-visible {
    outline: 2px solid var(--hcis-indigo-300);
    outline-offset: -2px;
}

.ab-ikon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    font-size: 1rem;
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
}
.ab-teks {
    flex: 1;
    min-width: 0;
}
.ab-judul {
    display: block;
    font-size: 0.87rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
    line-height: 1.25;
}

.ab-ringkas {
    display: flex;
    flex-wrap: wrap;
    gap: 0.15rem 0.6rem;
    margin-top: 0.1rem;
}
.ab-ringkas-i {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--hcis-slate-400);
}
.ab-ringkas-i i {
    font-size: 0.72rem;
    color: var(--hcis-slate-300);
}

.ab-pil {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    white-space: nowrap;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--hcis-emerald-700);
    background: var(--hcis-emerald-50);
    border: 1px solid var(--hcis-emerald-200);
    border-radius: 999px;
    padding: 0.22rem 0.6rem;
}
.ab-chevron {
    flex-shrink: 0;
    color: var(--hcis-slate-400);
    font-size: 0.85rem;
    transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
.ab.buka .ab-chevron {
    transform: rotate(180deg);
    color: var(--hcis-emerald-700);
}

/* ══ Isi ══ */
.ab-isi {
    overflow: hidden;
}
.ab-badan {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem;
    align-items: flex-start;
    padding: 0.15rem 0.9rem 0.9rem;
    border-top: 1px solid var(--hcis-slate-100);
    padding-top: 0.85rem;
}

.ab-foto {
    position: relative;
    flex: 0 0 104px;
    height: 104px;
    border-radius: 13px;
    overflow: hidden;
    display: block;
    text-decoration: none;
    border: 2px solid var(--hcis-slate-200);
    background: var(--hcis-slate-900);
    transition:
        border-color 0.2s ease,
        transform 0.2s ease;
}
.ab-foto:hover {
    border-color: var(--hcis-emerald-500);
    transform: scale(1.02);
}
.ab-foto img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.ab-foto-lencana {
    position: absolute;
    inset: auto 0 0 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    font-size: 0.6rem;
    font-weight: 700;
    color: #fff;
    padding: 0.24rem;
    background: rgba(15, 23, 42, 0.74);
    opacity: 0;
    transition: opacity 0.16s ease;
}
.ab-foto:hover .ab-foto-lencana,
.ab-foto:focus-visible .ab-foto-lencana {
    opacity: 1;
}

.ab-foto-kosong {
    flex: 0 0 104px;
    height: 104px;
    border-radius: 13px;
    display: grid;
    place-content: center;
    justify-items: center;
    gap: 0.2rem;
    text-align: center;
    background: var(--surface-muted, var(--hcis-slate-50));
    border: 1px dashed var(--border, #dbe1ea);
    color: var(--hcis-slate-400);
    font-size: 0.62rem;
    padding: 0.4rem;
}
.ab-foto-kosong i {
    font-size: 1.15rem;
}

/* auto-fit + minmax: dua kartu berdampingan bila ada ruang, satu kolom bila
   tidak — tanpa satu pun ambang media query untuk mengaturnya. */
.ab-grid {
    flex: 1 1 260px;
    min-width: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
}

.ab-fakta {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    min-width: 0;
    padding: 0.6rem 0.7rem;
    border-radius: 11px;
    background: var(--surface-muted, var(--hcis-slate-50));
    border: 1px solid #eef2f7;
}
/* Koordinat + tombol Maps butuh ruang lebih lega daripada dua fakta lainnya. */
.ab-fakta.luas {
    grid-column: 1 / -1;
    flex-wrap: wrap;
    align-items: center;
}

.ab-fakta-ico {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    font-size: 0.74rem;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
}
.ab-fakta-ico.gps {
    color: #0f766e;
    background: rgba(13, 148, 136, 0.11);
}
.ab-fakta-ico.kompas {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.13);
}

.ab-fakta-label {
    display: block;
    font-size: 0.63rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--hcis-slate-400);
}
.ab-fakta-nilai {
    display: block;
    font-size: 0.79rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    line-height: 1.35;
    word-break: break-word;
}
.ab-fakta-baris {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}

.ab-tag {
    font-size: 0.6rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.1rem 0.42rem;
    white-space: nowrap;
}
.ab-tag.ok {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.14);
}
.ab-tag.ragu {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.16);
}

.ab-maps {
    margin-left: auto;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.73rem;
    font-weight: 700;
    color: var(--hcis-indigo-600);
    text-decoration: none;
    border: 1px solid var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    border-radius: 9px;
    padding: 0.34rem 0.65rem;
}
.ab-maps:hover {
    background: var(--hcis-indigo-100);
    border-color: var(--hcis-indigo-300);
}

/* ══ Transisi lipat ══ */
.ab-lipat-enter-active,
.ab-lipat-leave-active {
    transition: height 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

/* ══ Responsif ══ */
@media (max-width: 560px) {
    .ab-kepala {
        padding: 0.7rem 0.75rem;
        gap: 0.6rem;
    }
    .ab-ikon {
        width: 30px;
        height: 30px;
        font-size: 0.9rem;
    }
    .ab-judul {
        font-size: 0.82rem;
    }
    .ab-ringkas-i {
        font-size: 0.67rem;
    }
    /* Teks pil dibuang, ikon centangnya tetap: itu sudah cukup menandakan sah,
       dan menahan kepala tetap satu baris bersama chevron. */
    .ab-pil-t {
        display: none;
    }
    .ab-pil {
        padding: 0.22rem 0.4rem;
    }

    .ab-badan {
        padding: 0.75rem 0.75rem 0.8rem;
        gap: 0.7rem;
    }
    .ab-foto,
    .ab-foto-kosong {
        flex: 0 0 84px;
        height: 84px;
        border-radius: 11px;
    }
    .ab-grid {
        flex-basis: 100%;
        grid-template-columns: 1fr;
    }
    .ab-maps {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }
}
@media (max-width: 380px) {
    /* Ruang tinggal sedikit: ringkasan akurasi turun, waktu tetap — itu yang
       paling sering dicari saat kartu masih tertutup. */
    .ab-ringkas-i:last-child {
        display: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .ab-lipat-enter-active,
    .ab-lipat-leave-active {
        transition: none;
    }
    .ab-chevron,
    .ab-foto {
        transition: none;
    }
}
</style>

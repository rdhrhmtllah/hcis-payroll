<template>
    <transition name="petunjuk-pop">
        <div
            v-if="tampil"
            ref="el"
            class="panduan-petunjuk"
            :style="{ '--panah-kanan': panahRight }"
            role="status"
            aria-live="polite"
            data-testid="panduan-petunjuk"
            @mousedown.stop
            @click.stop
        >
            <span class="panduan-petunjuk__panah" aria-hidden="true"></span>

            <div class="panduan-petunjuk__header">
                <span class="panduan-petunjuk__badge" :class="{ 'is-update': adaPembaruan }">
                    <i :class="['bi', adaPembaruan ? 'bi-stars' : 'bi-question-circle-fill']"></i>
                    <span>{{ badgeLabel }}</span>
                </span>
                <button type="button" class="panduan-petunjuk__tutup" aria-label="Tutup petunjuk" @click="mengerti">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="panduan-petunjuk__isi">
                <span class="panduan-petunjuk__ikon" :class="{ 'is-update': adaPembaruan }" aria-hidden="true">
                    <i :class="['bi', adaPembaruan ? 'bi-patch-check-fill' : 'bi-journal-bookmark-fill']"></i>
                </span>
                <div class="panduan-petunjuk__teks">
                    <strong class="panduan-petunjuk__headline">{{ judulPetunjuk }}</strong>
                    <p class="panduan-petunjuk__subline">
                        Ada panduan untuk halaman ini serta kontak bantuan PIC jika Anda membutuhkan asistensi.
                    </p>
                    <div class="panduan-petunjuk__resources">
                        <div
                            class="panduan-petunjuk__resource-item is-interactive"
                            role="button"
                            tabindex="0"
                            :title="panduan.length > 1 ? 'Lihat daftar panduan' : 'Buka dokumen panduan'"
                            @click="buka"
                            @keydown.enter.prevent="buka"
                        >
                            <span class="panduan-petunjuk__resource-badge is-panduan">
                                <i class="bi bi-file-earmark-text-fill"></i>
                                <span>Panduan</span>
                            </span>
                            <span class="panduan-petunjuk__resource-name" :title="keterangan">{{ keterangan }}</span>
                            <i class="bi bi-chevron-right panduan-petunjuk__resource-arrow" aria-hidden="true"></i>
                        </div>
                        <div
                            class="panduan-petunjuk__resource-item is-interactive"
                            role="button"
                            tabindex="0"
                            title="Buka Pusat Bantuan untuk melihat kontak PIC"
                            @click="bukaBantuan"
                            @keydown.enter.prevent="bukaBantuan"
                        >
                            <span class="panduan-petunjuk__resource-badge is-bantuan">
                                <i class="bi bi-headset"></i>
                                <span>Bantuan</span>
                            </span>
                            <span class="panduan-petunjuk__resource-name">Kontak PIC (WA, Telp, Email)</span>
                            <i class="bi bi-chevron-right panduan-petunjuk__resource-arrow" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div v-if="catatan" class="panduan-petunjuk__catatan">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>{{ catatan }}</span>
                    </div>
                </div>
            </div>

            <div class="panduan-petunjuk__footer">
                <div class="panduan-petunjuk__aksi">
                    <button type="button" class="panduan-petunjuk__btn is-utama" data-testid="panduan-petunjuk-buka" @click="buka">
                        <i class="bi bi-book"></i>
                        <span>{{ panduan.length > 1 ? 'Lihat panduan' : 'Buka panduan' }}</span>
                    </button>
                    <button type="button" class="panduan-petunjuk__btn" data-testid="panduan-petunjuk-mengerti" @click="mengerti">
                        Mengerti
                    </button>
                </div>
                <button type="button" class="panduan-petunjuk__diam" data-testid="panduan-petunjuk-jangan" @click="janganIngatkan">
                    <i class="bi bi-bell-slash"></i>
                    <span>Jangan ingatkan lagi</span>
                </button>
            </div>
        </div>
    </transition>
</template>

<script setup>
/**
 * Petunjuk kecil di bawah tombol Bantuan (?) saat halaman punya panduan —
 * supaya pengguna tahu (dan ingat) ada manual cara mengisi halaman ini.
 *
 * Kapan muncul: lihat panduanPengingat.js ("sampai dibuka, lalu 7 hari",
 * muncul lagi saat panduan diperbarui). Ditahan selama Pusat Bantuan terbuka,
 * tour berjalan, atau modal global menahan tour otomatis. Diabaikan: hilang
 * sendiri tanpa dianggap ditanggapi.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { tungguTourBolehOtomatis } from '../../composables/tourRegistry.js';
import { catatTanggapan, perluDiingatkan, tandaTangan } from './panduanPengingat.js';

const props = defineProps({
    panduan: { type: Array, default: () => [] }, // [{ id, judul, baru, diperbarui, catatan }]
    jenisPage: { type: String, default: '' },
    kunciPengguna: { type: String, default: '' },
    /** Jangan tampil (Pusat Bantuan terbuka, tour berjalan, ...). */
    ditahan: { type: Boolean, default: false },
    jedaMs: { type: Number, default: 1200 },
    tampilMs: { type: Number, default: 15000 },
});
const emit = defineEmits(['buka-panduan', 'buka-bantuan']);

const el = ref(null);
const tampil = ref(false);
const pengingatUlang = ref(false);
const panahRight = ref('18px');
let pewaktuMuncul = null;
let pewaktuHilang = null;
let giliran = 0;

function perbaruiPosisiPanah() {
    if (typeof window === 'undefined' || !el.value) return;
    if (window.innerWidth > 767.98) {
        panahRight.value = '18px';
        return;
    }
    const parent = el.value.parentElement;
    const tombol = parent?.querySelector('button');
    if (tombol) {
        const rTombol = tombol.getBoundingClientRect();
        const rEl = el.value.getBoundingClientRect();
        if (rTombol.width > 0 && rEl.width > 0) {
            const centerTombol = rTombol.left + rTombol.width / 2;
            const offsetKanan = rEl.right - centerTombol;
            const panahClamped = Math.max(16, Math.min(offsetKanan, rEl.width - 24));
            panahRight.value = `${Math.round(panahClamped)}px`;
            return;
        }
    }
    panahRight.value = '4.5rem';
}

const adaPembaruan = computed(() => props.panduan.some((p) => p.baru));
const utama = computed(() => props.panduan.find((p) => p.baru) || props.panduan[0]);
const keterangan = computed(() => (props.panduan.length > 1 ? `${props.panduan.length} panduan tersedia` : utama.value?.judul || ''));
const catatan = computed(() => (adaPembaruan.value ? utama.value?.catatan || '' : ''));
const tanda = computed(() => tandaTangan(props.panduan));
const judulPetunjuk = computed(() => {
    if (adaPembaruan.value) return 'Panduan halaman ini diperbarui';
    if (pengingatUlang.value) return 'Masih ingat caranya? Panduan & bantuan di sini';
    return 'Ada panduan & bantuan untuk halaman ini';
});
const badgeLabel = computed(() => {
    if (adaPembaruan.value) return 'Pembaruan Panduan';
    if (pengingatUlang.value) return 'Pengingat';
    return 'Panduan & Bantuan';
});

function tanggapi(jenis) {
    catatTanggapan({ pengguna: props.kunciPengguna, halaman: props.jenisPage, tanda: tanda.value, jenis });
}

function bersihkanPewaktu() {
    clearTimeout(pewaktuMuncul);
    clearTimeout(pewaktuHilang);
    pewaktuMuncul = pewaktuHilang = null;
}

function sembunyikan() {
    bersihkanPewaktu();
    tampil.value = false;
}

async function jadwalkan() {
    sembunyikan();
    const nomor = ++giliran;
    if (!props.panduan.length || props.ditahan) return;

    const hasil = perluDiingatkan({ pengguna: props.kunciPengguna, halaman: props.jenisPage, tanda: tanda.value });
    if (!hasil.muncul) return;

    await tungguTourBolehOtomatis();
    if (nomor !== giliran) return;

    pewaktuMuncul = setTimeout(() => {
        if (nomor !== giliran || props.ditahan || !props.panduan.length) return;
        pengingatUlang.value = hasil.pengingatUlang;
        tampil.value = true;
        pewaktuHilang = setTimeout(() => (tampil.value = false), props.tampilMs);
    }, props.jedaMs);
}

function buka() {
    tanggapi('dibuka');
    sembunyikan();
    if (props.panduan.length === 1) emit('buka-panduan', props.panduan[0]);
    else emit('buka-bantuan');
}

function bukaBantuan() {
    // Hanya melihat kontak, belum membaca manualnya: setara "Mengerti"
    // (muncul lagi di login berikutnya), bukan "dibuka" (diam 7 hari).
    tanggapi('mengerti');
    sembunyikan();
    emit('buka-bantuan', 'contacts');
}

function mengerti() {
    tanggapi('mengerti');
    sembunyikan();
}

function janganIngatkan() {
    tanggapi('jangan');
    sembunyikan();
}

// Halaman/isi berubah: jadwalkan ulang. Pusat Bantuan dibuka sendiri saat
// petunjuk tampil: ia sudah menemukan panduannya, jadi anggap ditanggapi.
watch(() => [props.jenisPage, tanda.value, props.kunciPengguna], jadwalkan, { immediate: true });
watch(
    () => props.ditahan,
    (tahan) => {
        if (!tahan) return jadwalkan();
        if (tampil.value) tanggapi('mengerti');
        giliran += 1;
        sembunyikan();
    },
);

watch(
    () => tampil.value,
    (val) => {
        if (val) {
            nextTick(perbaruiPosisiPanah);
        }
    },
);

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', perbaruiPosisiPanah, { passive: true });
        window.addEventListener('orientationchange', perbaruiPosisiPanah, { passive: true });
    }
});

onBeforeUnmount(() => {
    giliran += 1;
    bersihkanPewaktu();
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', perbaruiPosisiPanah);
        window.removeEventListener('orientationchange', perbaruiPosisiPanah);
    }
});

defineExpose({ tampil });
</script>

<style scoped>
.panduan-petunjuk {
    position: absolute;
    top: calc(100% + 14px);
    right: -6px;
    z-index: 1060;
    width: min(23.5rem, calc(100vw - 28px));
    padding: 0.95rem 1rem 0.85rem;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(226, 232, 240, 0.95);
    color: var(--hcis-slate-900, #0f172a);
    box-shadow:
        0 4px 6px -1px rgba(15, 23, 42, 0.05),
        0 16px 36px -8px rgba(15, 23, 42, 0.16),
        0 0 0 1px rgba(99, 102, 241, 0.06);
    text-align: left;
    transform-origin: top right;
}

.panduan-petunjuk__panah {
    position: absolute;
    top: -7px;
    right: var(--panah-kanan, 18px);
    width: 12px;
    height: 12px;
    background: #ffffff;
    border-top: 1px solid rgba(226, 232, 240, 0.95);
    border-left: 1px solid rgba(226, 232, 240, 0.95);
    transform: rotate(45deg);
    border-radius: 2px 0 0 0;
    z-index: 2;
    transition: right 0.18s ease;
}

.panduan-petunjuk__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.65rem;
}

.panduan-petunjuk__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    background: rgba(79, 70, 229, 0.08);
    color: #4f46e5;
    border: 1px solid rgba(79, 70, 229, 0.18);
}

.panduan-petunjuk__badge.is-update {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.25);
}

.panduan-petunjuk__tutup {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.55rem;
    height: 1.55rem;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: #94a3b8;
    font-size: 0.72rem;
    cursor: pointer;
    transition: all 180ms ease;
}

.panduan-petunjuk__tutup:hover {
    color: #0f172a;
    background: #f1f5f9;
}

.panduan-petunjuk__isi {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.panduan-petunjuk__ikon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 12px;
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
    color: #4f46e5;
    border: 1px solid rgba(79, 70, 229, 0.18);
    font-size: 1.1rem;
    box-shadow: 0 2px 8px -2px rgba(79, 70, 229, 0.2);
}

.panduan-petunjuk__ikon.is-update {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.25);
    box-shadow: 0 2px 8px -2px rgba(16, 185, 129, 0.2);
}

.panduan-petunjuk__teks {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    flex: 1;
}

.panduan-petunjuk__headline {
    color: #0f172a;
    font-size: 0.84rem;
    font-weight: 750;
    line-height: 1.35;
    letter-spacing: -0.01em;
}

.panduan-petunjuk__subline {
    margin: 0 0 0.1rem;
    font-size: 0.72rem;
    color: #64748b;
    line-height: 1.35;
}

.panduan-petunjuk__resources {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-top: 0.15rem;
}

.panduan-petunjuk__resource-item {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.32rem 0.55rem;
    border-radius: 9px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #334155;
    min-width: 0;
    transition: all 180ms cubic-bezier(0.16, 1, 0.3, 1);
}

.panduan-petunjuk__resource-item.is-interactive {
    cursor: pointer;
}

.panduan-petunjuk__resource-item.is-interactive:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateX(1px);
}

.panduan-petunjuk__resource-item.is-interactive:focus-visible {
    outline: 2px solid #4f46e5;
    outline-offset: 1px;
}

.panduan-petunjuk__resource-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.12rem 0.42rem;
    border-radius: 6px;
    font-size: 0.62rem;
    font-weight: 750;
    letter-spacing: 0.02em;
    flex-shrink: 0;
}

.panduan-petunjuk__resource-badge.is-panduan {
    background: rgba(79, 70, 229, 0.08);
    color: #4f46e5;
    border: 1px solid rgba(79, 70, 229, 0.2);
}

.panduan-petunjuk__resource-badge.is-bantuan {
    background: rgba(14, 165, 233, 0.09);
    color: #0284c7;
    border: 1px solid rgba(14, 165, 233, 0.22);
}

.panduan-petunjuk__resource-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.72rem;
    font-weight: 600;
    color: #1e293b;
}

.panduan-petunjuk__resource-arrow {
    color: #94a3b8;
    font-size: 0.72rem;
    flex-shrink: 0;
    margin-left: auto;
    transition: transform 160ms ease, color 160ms ease;
}

.panduan-petunjuk__resource-item.is-interactive:hover .panduan-petunjuk__resource-arrow {
    color: #4f46e5;
    transform: translateX(2px);
}

.panduan-petunjuk__catatan {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fef3c7;
    border-radius: 8px;
    padding: 0.35rem 0.55rem;
    font-size: 0.72rem;
    line-height: 1.35;
    margin-top: 0.15rem;
}

.panduan-petunjuk__catatan i {
    flex-shrink: 0;
    font-size: 0.78rem;
    margin-top: 0.05rem;
}

.panduan-petunjuk__footer {
    margin-top: 0.85rem;
    padding-top: 0.75rem;
    border-top: 1px solid #f1f5f9;
}

.panduan-petunjuk__aksi {
    display: flex;
    justify-content: flex-end;
    gap: 0.45rem;
}

.panduan-petunjuk__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    height: 2rem;
    padding: 0 0.8rem;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    background: #ffffff;
    color: #475569;
    font: inherit;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 180ms cubic-bezier(0.16, 1, 0.3, 1);
}

.panduan-petunjuk__btn:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #cbd5e1;
}

.panduan-petunjuk__btn.is-utama {
    border-color: transparent;
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px -2px rgba(79, 70, 229, 0.45);
}

.panduan-petunjuk__btn.is-utama:hover {
    background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgba(79, 70, 229, 0.55);
}

.panduan-petunjuk__btn:focus-visible,
.panduan-petunjuk__tutup:focus-visible,
.panduan-petunjuk__diam:focus-visible {
    outline: 2px solid #4f46e5;
    outline-offset: 2px;
}

.panduan-petunjuk__diam {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0.55rem 0 0 auto;
    padding: 0.15rem 0.25rem;
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: #94a3b8;
    font: inherit;
    font-size: 0.68rem;
    font-weight: 500;
    cursor: pointer;
    transition: color 150ms ease;
}

.panduan-petunjuk__diam:hover {
    color: #64748b;
    text-decoration: underline;
}

.panduan-petunjuk i.bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    vertical-align: middle;
}

.panduan-petunjuk i.bi::before {
    display: block;
    line-height: 1;
    vertical-align: 0 !important;
}

.petunjuk-pop-enter-active,
.petunjuk-pop-leave-active {
    transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.petunjuk-pop-enter-from,
.petunjuk-pop-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
}

@media (max-width: 767.98px) {
    .panduan-petunjuk {
        position: fixed;
        top: 4.25rem;
        right: 0.75rem;
        left: 0.75rem;
        width: auto;
        max-width: 24rem;
        margin: 0 0 0 auto;
        padding: 0.95rem 1rem 0.85rem;
        border-radius: 18px;
        box-shadow:
            0 12px 36px -4px rgba(15, 23, 42, 0.22),
            0 4px 12px -2px rgba(15, 23, 42, 0.08),
            0 0 0 1px rgba(99, 102, 241, 0.12);
    }

    .panduan-petunjuk__tutup {
        width: 1.85rem;
        height: 1.85rem;
        font-size: 0.8rem;
    }

    .panduan-petunjuk__aksi {
        display: flex;
        gap: 0.5rem;
        width: 100%;
    }

    .panduan-petunjuk__btn {
        flex: 1;
        height: 2.35rem;
        min-height: 38px;
        font-size: 0.78rem;
        padding: 0 0.5rem;
        white-space: nowrap;
    }

    .panduan-petunjuk__btn.is-utama {
        flex: 1.4;
    }

    .panduan-petunjuk__diam {
        margin: 0.65rem auto 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 1.75rem;
        font-size: 0.7rem;
    }
}

@media (max-width: 420px) {
    .panduan-petunjuk {
        left: 0.65rem;
        right: 0.65rem;
        max-width: none;
        margin: 0;
        padding: 0.85rem 0.85rem 0.8rem;
    }

    .panduan-petunjuk__isi {
        gap: 0.65rem;
    }

    .panduan-petunjuk__ikon {
        width: 2.2rem;
        height: 2.2rem;
        font-size: 1rem;
    }

    .panduan-petunjuk__resource-item {
        font-size: 0.71rem;
        padding: 0.28rem 0.45rem;
    }
}

@media (max-width: 360px) {
    .panduan-petunjuk__aksi {
        flex-direction: column-reverse;
        gap: 0.4rem;
    }

    .panduan-petunjuk__btn {
        width: 100%;
        height: 2.3rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .panduan-petunjuk__btn.is-utama:hover {
        transform: none;
    }
    .petunjuk-pop-enter-active,
    .petunjuk-pop-leave-active {
        transition: none;
    }
}
</style>

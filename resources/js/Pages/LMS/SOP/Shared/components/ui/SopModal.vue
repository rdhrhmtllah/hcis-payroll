<template>
    <!--
        DITELEPORTASI KE <body> — bukan kenyamanan, melainkan syarat.

        `position: fixed` berhenti mengacu ke viewport begitu SALAH SATU
        leluhurnya punya transform, filter, atau backdrop-filter yang bukan
        `none`. Modul ini penuh pemicunya, dan yang paling licin adalah
        `.sop-fade-up`: animasinya memakai `both`, jadi `transform: translateY(0)`
        tetap menempel SELAMANYA setelah animasinya selesai. Akibatnya latar
        gelap modal hanya menutupi kotak leluhur itu — sidebar dan topbar tetap
        terang, dan pinggirannya menyisakan celah yang terlihat seperti padding.

        Tanpa teleportasi, cacat ini kembali setiap kali ada yang menambahkan
        animasi atau kaca buram di mana pun di atas modal — dan tak seorang pun
        akan menghubungkan kedua hal itu.

        `.sop-scope` DIBAWA SERTA: seluruh tema modul ini bersandar pada leluhur
        itu (`.sop-scope .stitch-btn`, `.sop-field`, dst). Tanpa kelas ini,
        setiap tombol di dalam modal kehilangan gayanya begitu dipindahkan.
    -->
    <Teleport to="body">
        <transition name="sop-modal">
            <div
                v-if="show"
                class="sop-scope sop-modal-backdrop"
                @click.self="close"
                @keydown.esc="close"
                tabindex="-1"
                ref="root"
            >
                <div class="sop-modal" :class="sizeClass" role="dialog" aria-modal="true">
                    <div class="sop-modal-head">
                        <div class="d-flex align-items-center gap-2 flex-grow-1">
                            <div v-if="icon" class="sop-modal-icon"><i class="bi" :class="icon"></i></div>
                            <div>
                                <h5 class="sop-modal-title">{{ title }}</h5>
                                <p v-if="subtitle" class="sop-modal-sub">{{ subtitle }}</p>
                            </div>
                        </div>
                        <button class="sop-modal-close" @click="close" aria-label="Tutup">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="sop-modal-body">
                        <slot />
                    </div>

                    <div v-if="$slots.footer" class="sop-modal-foot">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script>
/**
 * SopModal — modal premium terpusat. Menggantikan modal hand-rolled.
 * Pakai:
 *   <SopModal :show="open" title="Tambah" icon="bi-plus" @close="open=false">
 *     ...body...
 *     <template #footer><button>...</button></template>
 *   </SopModal>
 */
export default {
    name: 'SopModal',
    /* Dideklarasikan supaya listener-nya TIDAK ikut jatuh ke atribut
       elemen. Komponen ini berakar fragment, sehingga Vue tidak punya
       satu elemen tujuan dan memperingatkan setiap kali listener ini
       dipasang. */
    emits: ['close'],
    props: {
        show: { type: Boolean, default: false },
        title: { type: String, default: '' },
        subtitle: { type: String, default: '' },
        icon: { type: String, default: '' },
        size: { type: String, default: 'md' }, // sm | md | lg | xl
    },
    computed: {
        sizeClass() {
            return `is-${this.size}`;
        },
    },
    data() {
        // Apakah INSTANSI ini sedang memegang kunci. Tanpa penanda per-instansi,
        // pemanggilan berulang bisa menaikkan penghitung lebih dari sekali.
        return { terkunciLokal: false };
    },
    mounted() {
        // Modal yang lahir dalam keadaan terbuka tak pernah memicu watcher.
        if (this.show) this.kunciBody(true);
    },
    watch: {
        show(v) {
            if (typeof document === 'undefined') return;
            this.kunciBody(v);
            if (v) this.$nextTick(() => this.$refs.root && this.$refs.root.focus());
        },
    },
    beforeUnmount() {
        this.kunciBody(false);
    },
    methods: {
        /**
         * Kunci scroll body DIHITUNG, bukan disetel-lepas.
         *
         * Modal bisa bertumpuk — pratinjau terbit dibuka dari dalam modal versi.
         * Dengan setel-lepas polos, menutup modal DALAM melepaskan kunci padahal
         * modal luar masih terbuka, dan halaman di belakangnya ikut menggulir
         * saat digulirkan. Penghitung membuat kunci baru lepas ketika modal
         * terakhir benar-benar tertutup.
         *
         * Penghitungnya menempel di document, bukan di modul, supaya tetap satu
         * angka meski komponen ini termuat dari lebih dari satu chunk.
         */
        kunciBody(aktif) {
            if (typeof document === 'undefined') return;
            if (aktif === this.terkunciLokal) return;

            this.terkunciLokal = aktif;
            const n = Math.max(0, (document.__sopModalKunci || 0) + (aktif ? 1 : -1));
            document.__sopModalKunci = n;
            document.body.style.overflow = n > 0 ? 'hidden' : '';
        },
        close() {
            this.$emit('close');
        },
    },
};
</script>

<style scoped>
.sop-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    z-index: 1060;
    padding: 1rem;
    outline: none;
}
.sop-modal {
    width: 100%;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 32px 70px -20px rgba(15, 23, 42, 0.5);
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.sop-modal.is-sm {
    max-width: 440px;
}
.sop-modal.is-md {
    max-width: 560px;
}
.sop-modal.is-lg {
    max-width: 760px;
}
.sop-modal.is-xl {
    max-width: 1000px;
}
/* Layar kerja, bukan dialog: dipakai permukaan yang isinya menelusuri data
   (studio export) dan butuh dua panel berdampingan. */
.sop-modal.is-full {
    max-width: 1560px;
    height: 94vh;
    max-height: 94vh;
}
.sop-modal.is-full .sop-modal-body {
    padding: 0;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.sop-modal-head {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 1.25rem 1.4rem 1rem;
    border-bottom: 1px solid #edf2f7;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(248, 250, 252, 0.95) 100%);
    position: relative;
    overflow: hidden;
}
.sop-modal-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
    color: var(--hcis-white);
    font-size: 1.15rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px -2px rgba(79, 70, 229, 0.35);
}
.sop-modal-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
    margin: 0;
}
.sop-modal-sub {
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
    margin: 0.1rem 0 0;
}
.sop-modal-close {
    border: 0;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    width: 34px;
    height: 34px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.sop-modal-close:hover {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-800);
}
.sop-modal-body {
    position: relative;
    padding: 1.25rem 1.4rem;
    overflow-y: auto;
}
.sop-modal-foot {
    padding: 0.9rem 1.4rem;
    border-top: 1px solid #eef2f7;
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    background: var(--hcis-slate-50);
}

/* transition */
.sop-modal-enter-active,
.sop-modal-leave-active {
    transition: opacity 0.22s ease;
}
.sop-modal-enter-active .sop-modal,
.sop-modal-leave-active .sop-modal {
    transition: transform 0.26s cubic-bezier(0.22, 1, 0.36, 1);
}
.sop-modal-enter-from,
.sop-modal-leave-to {
    opacity: 0;
}
.sop-modal-enter-from .sop-modal,
.sop-modal-leave-to .sop-modal {
    transform: translateY(18px) scale(0.97);
}
</style>

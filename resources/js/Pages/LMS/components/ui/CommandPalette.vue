<template>
    <teleport to="body">
        <transition name="cp">
            <div v-if="show" class="cp-overlay" @mousedown.self="tutup">
                <div class="cp-panel" role="dialog" aria-modal="true" :aria-label="placeholder">
                    <div class="cp-inp">
                        <i class="bi bi-search"></i>
                        <input ref="inp" v-model="q" type="text" :placeholder="placeholder"
                            @keydown.down.prevent="gerak(1)" @keydown.up.prevent="gerak(-1)"
                            @keydown.enter.prevent="jalankan(aktif)" @keydown.esc="tutup" @input="onKetik" />
                        <kbd class="cp-esc">esc</kbd>
                    </div>

                    <div v-if="memuat" class="cp-state"><span class="spinner-border spinner-border-sm"></span> Mencari…</div>
                    <div v-else-if="!hasil.length" class="cp-state">
                        <i class="bi bi-search"></i>
                        <span v-if="q">Tak ada yang cocok dengan “{{ q }}”.</span>
                        <span v-else>Belum ada yang bisa ditampilkan.</span>
                    </div>

                    <div v-else ref="list" class="cp-list">
                        <template v-for="(g, gi) in kelompok" :key="gi">
                            <div class="cp-grup">{{ g.judul }}</div>
                            <button v-for="item in g.items" :key="item.id" class="cp-item"
                                :class="{ on: item.idx === indeks }" @mousemove="indeks = item.idx" @click="jalankan(item)">
                                <span class="cp-ico" :class="item.tone"><i class="bi" :class="item.icon"></i></span>
                                <span class="cp-teks">
                                    <span class="cp-judul">{{ item.judul }}</span>
                                    <span v-if="item.sub" class="cp-sub">{{ item.sub }}</span>
                                </span>
                                <span v-if="item.tag" class="cp-tag" :class="item.tone">{{ item.tag }}</span>
                                <i class="bi bi-arrow-return-left cp-enter"></i>
                            </button>
                        </template>
                    </div>

                    <div class="cp-foot">
                        <span><kbd>↑</kbd><kbd>↓</kbd> pilih</span>
                        <span><kbd>↵</kbd> jalankan</span>
                        <span class="cp-foot-kanan"><kbd>{{ tombolMod }}</kbd>+<kbd>K</kbd> panggil dari mana saja</span>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<script>
/**
 * Palet perintah generik (Ctrl/Cmd+K) — CANGKANG saja.
 *
 * Kelas ini memegang yang selalu sama di mana pun: overlay, kolom isian,
 * pengelompokan, navigasi keyboard, riwayat "baru dibuka", dan status kosong.
 * Yang berbeda tiap halaman — APA yang bisa dicari — disuplai lewat `sources`.
 *
 * Kenapa diekstrak: sebelumnya ada dua palet ~240 baris yang 80% identik
 * (Monitoring SOP dan SOP Saya). Duplikasi itu bukan teori — satu bug nyata
 * (Bootstrap menyetel `kbd { color: var(--bs-body-bg) }` alias putih, sehingga
 * tombol pintasan jadi putih di atas putih dan tak terlihat) tersalin ke
 * keduanya. Sekarang perbaikan seperti itu cukup sekali.
 *
 * Bentuk `sources`:
 *   [{ jenis: 'tugas', label: 'Tugas Anda', cari(q) => item[] | Promise<item[]> }]
 * Bentuk item:
 *   { id, judul, sub?, tag?, tone?: ''|'ok'|'bad', icon, aksi: any }
 *
 * `cari` boleh sinkron maupun asinkron. Sumber yang datanya sudah ada di klien
 * mengembalikan array langsung (hasil muncul pada ketikan yang sama); sumber
 * yang memanggil API mengembalikan Promise dan halaman memasang `debounce`.
 */
export default {
    name: "CommandPalette",
    props: {
        show: { type: Boolean, default: false },
        /** @type {Array<{jenis:string,label:string,cari:Function}>} */
        sources: { type: Array, default: () => [] },
        placeholder: { type: String, default: "Cari atau ketik perintah…" },
        /** Kunci localStorage riwayat. Kosong = riwayat dimatikan. */
        recentKey: { type: String, default: "" },
        /** Ms sebelum `cari` dipanggil. 0 = seketika (untuk sumber lokal). */
        debounce: { type: Number, default: 0 },
        /**
         * Dipanggil saat riwayat dibaca; kembalikan false untuk membuang entri.
         * Dipakai membuang entri yang targetnya sudah tak ada lagi — tautan mati
         * di palet berujung 404 dan itu lebih buruk daripada riwayat kosong.
         */
        recentValid: { type: Function, default: null },
    },
    emits: ["close", "jalankan"],
    data() {
        return { q: "", indeks: 0, memuat: false, timer: null, terakhir: [], kumpulan: [] };
    },
    computed: {
        tombolMod() { return /mac|iphone|ipad/i.test(navigator.userAgent) ? "⌘" : "Ctrl"; },
        hasil() {
            const baru = this.q.trim() ? [] : this.terakhir;

            return [...baru, ...this.kumpulan].map((x, i) => ({ ...x, idx: i }));
        },
        kelompok() {
            const label = { terakhir: "Baru dibuka" };
            this.sources.forEach((s) => { label[s.jenis] = s.label; });

            const out = [];
            for (const item of this.hasil) {
                const j = label[item.jenis] || "Lainnya";
                const g = out.find((x) => x.judul === j);
                if (g) g.items.push(item);
                else out.push({ judul: j, items: [item] });
            }

            return out;
        },
        aktif() { return this.hasil[this.indeks] || null; },
    },
    watch: {
        show(v) {
            if (!v) { clearTimeout(this.timer); return; }
            this.q = "";
            this.indeks = 0;
            this.bacaTerakhir();
            this.kumpulkan();
            this.$nextTick(() => this.$refs.inp?.focus());
        },
    },
    beforeUnmount() { clearTimeout(this.timer); },
    methods: {
        tutup() { this.$emit("close"); },
        onKetik() {
            this.indeks = 0;
            clearTimeout(this.timer);
            if (!this.debounce) { this.kumpulkan(); return; }
            this.timer = setTimeout(this.kumpulkan, this.debounce);
        },
        async kumpulkan() {
            const q = this.q.trim();
            // Penanda giliran: hasil balasan yang datang terlambat (ketikan lama
            // selesai setelah ketikan baru) harus dibuang, bukan menimpa.
            const giliran = (this.giliran = (this.giliran || 0) + 1);

            const perluTunggu = this.sources.some((s) => s.async);
            if (perluTunggu) this.memuat = true;

            try {
                const per = await Promise.all(this.sources.map(async (s) => {
                    const r = await s.cari(q);

                    return (r || []).map((x) => ({ ...x, jenis: s.jenis }));
                }));
                if (giliran !== this.giliran) return;
                this.kumpulan = per.flat();
            } catch (e) {
                if (giliran === this.giliran) this.kumpulan = [];
            } finally {
                if (giliran === this.giliran) this.memuat = false;
            }
        },
        gerak(d) {
            const n = this.hasil.length;
            if (!n) return;
            this.indeks = (this.indeks + d + n) % n;
            this.$nextTick(() => {
                this.$refs.list?.querySelector(".cp-item.on")?.scrollIntoView({ block: "nearest" });
            });
        },
        jalankan(item) {
            if (!item) return;
            this.simpanTerakhir(item);
            this.$emit("jalankan", item);
        },
        bacaTerakhir() {
            if (!this.recentKey) { this.terakhir = []; return; }
            try {
                const raw = JSON.parse(localStorage.getItem(this.recentKey) || "[]");
                let arr = Array.isArray(raw) ? raw : [];
                if (this.recentValid) arr = arr.filter((x) => this.recentValid(x));
                this.terakhir = arr.slice(0, 4).map((x) => ({ ...x, jenis: "terakhir" }));
            } catch (e) {
                this.terakhir = [];
            }
        },
        simpanTerakhir(item) {
            if (!this.recentKey) return;
            try {
                const bersih = { id: item.id, judul: item.judul, sub: item.sub, icon: item.icon, tone: item.tone, aksi: item.aksi };
                const raw = JSON.parse(localStorage.getItem(this.recentKey) || "[]");
                const baru = [bersih, ...(Array.isArray(raw) ? raw : []).filter((x) => x.id !== item.id)].slice(0, 4);
                localStorage.setItem(this.recentKey, JSON.stringify(baru));
            } catch (e) { /* localStorage penuh/diblokir — bukan alasan menggagalkan navigasi */ }
        },
    },
};
</script>

<style scoped>
.cp-overlay {
    position: fixed; inset: 0; z-index: 3000; background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(3px); display: flex; align-items: flex-start; justify-content: center;
    padding: 12vh 1rem 1rem;
}
.cp-panel {
    width: min(620px, 100%); background: #fff; border-radius: 18px; overflow: hidden;
    box-shadow: 0 30px 70px -20px rgba(15, 23, 42, 0.5); display: flex; flex-direction: column; max-height: 70vh;
}
.cp-inp { display: flex; align-items: center; gap: 0.6rem; padding: 0.9rem 1.1rem; border-bottom: 1px solid #eef2f7; }
.cp-inp > i { color: var(--hcis-slate-400); }
.cp-inp input { flex: 1; border: 0; outline: 0; font-size: 0.95rem; color: var(--hcis-slate-800); background: transparent; }

.cp-list { overflow-y: auto; padding: 0.35rem; }
.cp-grup { font-size: 0.62rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--hcis-slate-400); padding: 0.5rem 0.75rem 0.25rem; }
.cp-item {
    display: flex; align-items: center; gap: 0.7rem; width: 100%; text-align: left;
    padding: 0.5rem 0.75rem; border: 0; background: transparent; border-radius: 10px; cursor: pointer;
}
.cp-item.on { background: rgba(99, 102, 241, 0.09); }
.cp-ico { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: var(--hcis-slate-100); color: var(--hcis-slate-500); flex-shrink: 0; }
.cp-ico.bad { background: rgba(239, 68, 68, 0.12); color: var(--hcis-red-700); }
.cp-ico.ok { background: rgba(16, 185, 129, 0.12); color: var(--hcis-emerald-700); }
.cp-teks { min-width: 0; flex: 1; }
.cp-judul { display: block; font-size: 0.85rem; font-weight: 700; color: var(--hcis-slate-800); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cp-sub { display: block; font-size: 0.7rem; color: var(--hcis-slate-400); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cp-tag { font-size: 0.62rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 999px; background: var(--hcis-slate-100); color: var(--hcis-slate-500); flex-shrink: 0; }
.cp-tag.bad { background: rgba(239, 68, 68, 0.12); color: var(--hcis-red-700); }
.cp-tag.ok { background: rgba(16, 185, 129, 0.12); color: var(--hcis-emerald-700); }
.cp-enter { color: transparent; font-size: 0.8rem; }
.cp-item.on .cp-enter { color: var(--hcis-indigo-300); }

.cp-state { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 2rem; color: var(--hcis-slate-400); font-size: 0.85rem; }
.cp-foot { display: flex; align-items: center; gap: 0.9rem; padding: 0.5rem 1rem; border-top: 1px solid #eef2f7; background: var(--hcis-slate-50); font-size: 0.68rem; color: var(--hcis-slate-400); }
.cp-foot-kanan { margin-left: auto; }

/* `color` WAJIB ditulis eksplisit. Bootstrap punya aturan global
   `kbd { color: var(--bs-body-bg) }` — putih — sehingga tanpa ini isi tombol
   pintasan jadi putih di atas latar putih dan yang tampak hanya kotak kosong. */
.cp-inp kbd,
.cp-foot kbd {
    display: inline-block; background: #fff; color: var(--hcis-slate-500);
    border: 1px solid #dbe1ea; border-radius: 4px;
    padding: 0 0.3rem; font-size: 0.66rem; line-height: 1.5; margin: 0 1px;
    font-family: inherit; font-weight: 700;
}
.cp-esc { color: var(--hcis-slate-400) !important; }

.cp-enter-active, .cp-leave-active { transition: opacity 0.15s ease; }
.cp-enter-from, .cp-leave-to { opacity: 0; }
.cp-enter-active .cp-panel { animation: cp-in 0.18s ease; }
@keyframes cp-in { from { transform: translateY(-8px) scale(0.985); opacity: 0; } }

@media (max-width: 575.98px) { .cp-overlay { padding-top: 6vh; } }
</style>

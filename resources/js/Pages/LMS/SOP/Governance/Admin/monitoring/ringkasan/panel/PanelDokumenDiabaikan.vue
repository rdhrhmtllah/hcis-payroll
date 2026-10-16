<template>
    <div class="pdd">
        <!-- Terpopuler dan terabaikan berdampingan, bukan di dua kartu.
             Keduanya sisi dari satu pertanyaan — apa yang dipedulikan orang —
             dan memisahkannya membuat perbandingannya hilang. -->
        <section v-if="terpopuler.length" class="pdd-blok">
            <h4 class="pdd-judul">
                <i class="bi bi-fire text-danger"></i> Paling dibaca sukarela
            </h4>
            <ol class="pdd-daftar">
                <li v-for="d in terpopuler" :key="d.Id_SOP_Dokumen">
                    <span class="pdd-nama" :title="d.Judul">{{ d.Judul }}</span>
                    <span class="pdd-bar" aria-hidden="true">
                        <span class="pdd-isi" :style="{ width: `${lebar(d)}%` }"></span>
                    </span>
                    <span class="pdd-nilai">{{ d.Pembuka }}</span>
                </li>
            </ol>
        </section>

        <section class="pdd-blok">
            <h4 class="pdd-judul">
                <i class="bi bi-eye-slash text-warning"></i>
                Tak disentuh siapa pun
                <span v-if="diabaikan.length" class="pdd-jml">{{ diabaikan.length }}</span>
            </h4>

            <p v-if="!diabaikan.length" class="pdd-kosong">
                <i class="bi bi-check-circle-fill text-success"></i>
                Setiap dokumen publik pernah dibuka setidaknya satu orang.
            </p>

            <ul v-else class="pdd-daftar pdd-daftar--polos">
                <li v-for="d in diabaikan" :key="d.Id_SOP_Dokumen">
                    <span class="pdd-nama" :title="d.Judul">{{ d.Judul }}</span>
                    <span v-if="d.Nomor" class="pdd-nomor">{{ d.Nomor }}</span>
                </li>
            </ul>

            <p v-if="diabaikan.length" class="pdd-nota">
                Dibuka untuk semua orang, lalu tak seorang pun membacanya. Tak ada
                mekanisme lain di sistem yang memunculkan ini.
            </p>
        </section>
    </div>
</template>

<script>
/**
 * Dokumen publik: yang paling dibaca, dan yang tak disentuh sama sekali.
 *
 * Daftar "tak disentuh" dihitung TANPA saringan layar, dan itu disengaja —
 * "tak dibaca siapa pun" adalah pernyataan tentang seluruh orang. Menyaringnya
 * ke satu departemen mengubah artinya menjadi "tak dibaca orang departemen
 * itu", kalimat yang jauh lebih lemah dan mudah disalahbaca.
 */
export default {
    name: 'PanelDokumenDiabaikan',
    props: {
        terpopuler: { type: Array, default: () => [] },
        diabaikan: { type: Array, default: () => [] },
    },
    computed: {
        puncak() {
            return Math.max(1, ...this.terpopuler.map((d) => d.Pembuka || 0));
        },
    },
    methods: {
        lebar(d) {
            return ((d.Pembuka || 0) * 100) / this.puncak;
        },
    },
};
</script>

<style scoped>
.pdd {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.pdd-blok {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.pdd-judul {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-text-strong);
}

.pdd-jml {
    padding: 0.05rem 0.4rem;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-amber-500);
    color: #fff;
    font-size: 0.68rem;
}

.pdd-daftar {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    counter-reset: pdd;
}

.pdd-daftar li {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 6rem 2.25rem;
    align-items: center;
    gap: 0.5rem;
}

.pdd-daftar--polos li { grid-template-columns: minmax(0, 1fr) auto; }

.pdd-nama {
    font-size: 0.78rem;
    color: var(--hcis-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pdd-nomor {
    font-size: 0.68rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    color: var(--hcis-text-muted);
}

.pdd-bar {
    height: 0.5rem;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-surface-soft);
    overflow: hidden;
}

.pdd-isi {
    display: block;
    height: 100%;
    background: var(--hcis-brand);
}

.pdd-nilai {
    text-align: right;
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-text-strong);
}

.pdd-kosong,
.pdd-nota {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin: 0;
    font-size: 0.72rem;
    line-height: 1.45;
    color: var(--hcis-text-muted);
}
</style>

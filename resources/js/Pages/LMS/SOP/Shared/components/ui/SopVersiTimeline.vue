<template>
    <div class="vt">
        <div v-if="loading" class="vt-load py-2">
            <div class="d-flex align-items-center gap-2 text-primary font-weight-bold small">
                <span class="spinner-border spinner-border-sm"></span>
                <span>Memuat riwayat versi…</span>
            </div>
        </div>

        <div v-else-if="!versions.length" class="vt-empty">
            <i class="bi bi-inbox text-muted fs-5"></i>
            <span>Belum ada riwayat versi terdahulu.</span>
        </div>

        <div v-else class="vt-list">
            <div v-for="v in versions" :key="v.id" class="vt-node" :class="{ aktif: v.aktif, obsolete: !v.aktif }">
                <div class="vt-rail">
                    <div class="vt-dot">v{{ v.versi }}</div>
                </div>
                <div class="vt-body">
                    <div class="vt-top">
                        <span class="vt-badge" :class="v.aktif ? 'now' : 'old'">
                            <i class="bi" :class="v.aktif ? 'bi-patch-check-fill' : 'bi-clock-history'"></i>
                            {{ v.aktif ? 'Versi berlaku' : 'Versi lama' }}
                        </span>
                        <span v-if="showBaca && v.baca" class="vt-read" :class="bacaClass(v.baca)">
                            <i class="bi" :class="bacaIcon(v.baca)"></i> {{ bacaLabel(v.baca) }}
                        </span>
                        <span v-if="v.tanggal" class="vt-date tnum">
                            <i class="bi bi-calendar3 me-1"></i>{{ fmt(v.tanggal) }}
                        </span>
                    </div>

                    <div v-if="v.changelog" class="vt-change">
                        <i class="bi bi-chat-left-text me-1 text-muted"></i>{{ v.changelog }}
                    </div>

                    <!-- Pengesahan versi lama tetap disebut: itulah yang membuat
                         baris ini berguna sebagai catatan, bukan sekadar nomor. -->
                    <div v-if="v.pengesah || v.nomorSah" class="vt-sah">
                        <span v-if="v.pengesah"><i class="bi bi-patch-check me-1"></i>Disahkan {{ v.pengesah }}</span>
                        <span v-if="v.tanggalSah" class="tnum">· {{ fmt(v.tanggalSah) }}</span>
                        <span v-if="v.nomorSah">· No. {{ v.nomorSah }}</span>
                    </div>

                    <div class="vt-foot">
                        <span v-if="v.kontenNama" class="vt-konten" :title="v.kontenNama">
                            <i class="bi" :class="kontenIcon(v.kontenTipe)"></i>
                            <span class="text-truncate">{{ v.kontenNama }}</span>
                        </span>
                        <!-- Sebagian versi (formulir non-PDF) tak bisa ditampilkan,
                             hanya diunduh. Tombolnya harus mengatakan yang sebenarnya
                             akan terjadi, bukan "Lihat" lalu berkas terunduh. -->
                        <button v-if="v.canView" class="vt-btn-view ms-auto" @click="$emit('view', v)">
                            <i class="bi" :class="v.unduh ? 'bi-download' : 'bi-eye-fill'"></i>
                            {{ v.unduh ? 'Unduh versi ini' : 'Lihat versi ini' }}
                        </button>

                        <!-- Bukan tombol mati, dan bukan diam. Baris ini menjawab
                             pertanyaan yang pasti muncul — "kenapa yang ini tak
                             bisa saya buka?" — di tempat pertanyaannya lahir.
                             Tanpa itu, hilangnya tombol terbaca sebagai kerusakan. -->
                        <span v-else class="vt-tutup ms-auto">
                            <i class="bi bi-archive"></i> Isi tak lagi dibagikan
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * SopVersiTimeline — timeline riwayat versi (on-demand). Data ternormalisasi dari parent:
 * { id, versi, aktif, baca?, changelog?, tanggal?, kontenNama?, kontenTipe?, canView, unduh? }.
 * unduh=true menandai versi yang hanya bisa diunduh (formulir non-PDF).
 * Emit 'view'(version) untuk membuka konten versi tsb.
 */
export default {
    name: 'SopVersiTimeline',
    props: {
        versions: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
        showBaca: { type: Boolean, default: true },
    },
    emits: ['view'],
    methods: {
        fmt(d) {
            if (!d) return '';
            const x = new Date(d);
            return isNaN(x) ? d : x.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        bacaClass(s) {
            return ['SELESAI', 'DIKONFIRMASI'].includes(s) ? 'ok' : s === 'DIBUKA' ? 'warn' : 'idle';
        },
        bacaIcon(s) {
            return ['SELESAI', 'DIKONFIRMASI'].includes(s)
                ? 'bi-check-circle-fill'
                : s === 'DIBUKA'
                  ? 'bi-eye-fill'
                  : 'bi-circle';
        },
        bacaLabel(s) {
            return (
                {
                    SELESAI: 'Sudah dibaca',
                    DIKONFIRMASI: 'Sudah dibaca',
                    DIBUKA: 'Dibuka',
                    BELUM: 'Belum dibaca',
                    DIGANTIKAN: 'Digantikan',
                }[s] || s
            );
        },
        kontenIcon(t) {
            const s = String(t || '').toLowerCase();
            if (s.includes('video')) return 'bi-play-btn-fill text-danger';
            if (s.includes('pdf')) return 'bi-file-earmark-pdf-fill text-danger';
            return 'bi-file-earmark-text-fill text-primary';
        },
    },
};
</script>

<style scoped>
.vt-load,
.vt-empty {
    font-size: 0.82rem;
    color: var(--hcis-slate-400);
    padding: 0.75rem 0.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.vt-list {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.vt-node {
    display: flex;
    gap: 0.85rem;
    position: relative;
}

.vt-rail {
    position: relative;
    width: 36px;
    flex-shrink: 0;
    display: flex;
    justify-content: center;
}
.vt-rail::before {
    content: '';
    position: absolute;
    top: 36px;
    bottom: -8px;
    width: 2px;
    background: var(--hcis-slate-300);
    z-index: 0;
}
.vt-node:last-child .vt-rail::before {
    display: none;
}

.vt-dot {
    position: relative;
    z-index: 2;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 0.72rem;
    flex-shrink: 0;
    transition: all 0.25s ease;
}

.vt-node.aktif .vt-dot {
    background: linear-gradient(135deg, var(--hcis-emerald-500), var(--hcis-emerald-600));
    color: var(--hcis-white);
    border: 2px solid var(--hcis-emerald-200);
    box-shadow: 0 4px 14px -3px rgba(16, 185, 129, 0.5);
}

.vt-node.obsolete .vt-dot {
    background: var(--hcis-white);
    color: var(--hcis-slate-500);
    border: 2px solid var(--hcis-slate-300);
}

.vt-body {
    flex-grow: 1;
    min-width: 0;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    padding: 0.75rem 0.95rem;
    margin-bottom: 0.75rem;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.vt-node.aktif .vt-body {
    border-color: rgba(16, 185, 129, 0.4);
    background: rgba(16, 185, 129, 0.03);
    box-shadow: 0 6px 18px -4px rgba(16, 185, 129, 0.12);
}

.vt-top {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
}

.vt-badge {
    font-size: 0.64rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.vt-badge.now {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
    border: 1px solid var(--hcis-emerald-200);
}
.vt-badge.old {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-300);
}

.vt-read {
    font-size: 0.64rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    text-transform: uppercase;
}
.vt-read.ok {
    background: var(--hcis-emerald-50);
    color: var(--hcis-emerald-700);
    border: 1px solid var(--hcis-emerald-200);
}
.vt-read.warn {
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-700);
    border: 1px solid var(--hcis-amber-200);
}
.vt-read.idle {
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
    border: 1px solid var(--hcis-slate-200);
}

.vt-date {
    margin-left: auto;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
}
.vt-change {
    font-size: 0.8rem;
    color: var(--hcis-slate-700);
    margin-top: 0.45rem;
    line-height: 1.45;
    font-weight: 500;
}
.vt-sah {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    font-weight: 600;
    margin-top: 0.4rem;
}

/* Sengaja tenang: ini keterangan, bukan tombol yang dimatikan. Menatanya
   seperti tombol nonaktif akan mengundang orang menekannya berulang. */
.vt-tutup {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
}

.vt-foot {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin-top: 0.6rem;
    padding-top: 0.5rem;
    border-top: 1px dashed var(--hcis-slate-100);
}
.vt-konten {
    font-size: 0.75rem;
    color: var(--hcis-slate-600);
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
    max-width: 70%;
}

.vt-btn-view {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: var(--hcis-indigo-50);
    border: 1px solid var(--hcis-indigo-200);
    border-radius: 999px;
    padding: 0.32rem 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.vt-btn-view:hover {
    color: var(--hcis-white);
    background: var(--hcis-indigo-600);
    border-color: var(--hcis-indigo-600);
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
}

@media (max-width: 520px) {
    .vt-top {
        gap: 0.3rem;
    }
    .vt-date {
        width: 100%;
        margin-left: 0;
        margin-top: 0.2rem;
    }
    .vt-foot {
        flex-direction: column;
        align-items: stretch;
        gap: 0.45rem;
    }
    .vt-konten {
        max-width: 100%;
    }
    .vt-btn-view {
        width: 100%;
        justify-content: center;
    }
}
</style>

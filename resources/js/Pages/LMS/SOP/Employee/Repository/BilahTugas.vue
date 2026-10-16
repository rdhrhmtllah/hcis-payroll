<template>
    <div>
        <span ref="jangkar" class="bl-jangkar" aria-hidden="true"></span>

        <a
            href="/sop/saya"
            class="bl"
            :class="[nada, { lekat: lengket, ciut: lengket && terjepit }]"
            :title="judul"
        >
            <span class="bl-ico" aria-hidden="true">
                <HcisIcon :name="ikon" :animation="nada === 'bad' ? 'pulse' : ''" size="sm" />
            </span>

            <div class="bl-teks">
                <div class="bl-judul-row">
                    <strong class="bl-judul">{{ judul }}</strong>
                    <span v-if="nada === 'bad'" class="bl-urgent-pill">Perlu Tindakan</span>
                </div>
                <span v-if="sub" class="bl-sub">{{ sub }}</span>
            </div>

            <span class="bl-cta">
                <span>{{ cta }}</span>
                <HcisIcon name="arrow-right" size="xs" />
            </span>
        </a>
    </div>
</template>

<script>
import HcisIcon from '@/components/ui/HcisIcon.vue';

/**
 * Bilah ajakan ke Functional Document — MENGINGATKAN, bukan menampilkan.
 *
 * Repository memuat dokumen publik rujukan; tugas kepatuhan seluruhnya tinggal di Functional
 * Document. Bilah ini menempel (sticky) secara otomatis HANYA bila ada yang lewat tenggat.
 */
export default {
    name: 'BilahTugas',
    components: { HcisIcon },
    props: {
        t: { type: Object, required: true },
    },
    data() {
        return {
            terjepit: false,
            pengamat: null,
        };
    },
    computed: {
        lengket() {
            return this.t.terlambat > 0;
        },
        nada() {
            if (!this.t.total) return 'bersih';
            if (this.t.terlambat > 0) return 'bad';
            return this.t.segera > 0 ? 'warn' : 'netral';
        },
        ikon() {
            return {
                bad: 'exclamation-triangle-fill',
                warn: 'alarm-fill',
                netral: 'list-check',
                bersih: 'check-circle-fill',
            }[this.nada];
        },
        judul() {
            const { total, terlambat } = this.t;
            if (!total) return 'Semua tugas dokumen Anda telah tuntas';
            if (terlambat > 0) return `${terlambat} dokumen wajib telah melewati tenggat`;
            return `${total} dokumen menunggu Anda kerjakan`;
        },
        sub() {
            if (!this.t.total) return 'Seluruh rujukan operasional di bawah bebas Anda akses dan pelajari kapan saja.';

            const bagian = [];
            if (this.t.terlambat > 0 && this.t.total > this.t.terlambat) {
                bagian.push(`${this.t.total} tugas kepatuhan`);
            }
            const d = this.t.terdekat;
            if (d) {
                const s = d.sisa_hari;
                const kapan =
                    s < 0
                        ? `lewat ${Math.abs(s)} hari`
                        : s === 0
                          ? 'jatuh tempo hari ini'
                          : s === 1
                            ? 'besok jatuh tempo'
                            : `sisa ${s} hari`;
                bagian.push(`Paling mendesak: ${d.nama} (${kapan})`);
            }

            return bagian.join(' · ');
        },
        cta() {
            if (!this.t.total) return 'Lihat Riwayat';
            return this.t.terlambat > 0 ? 'Kerjakan Sekarang' : 'Buka Functional Document';
        },
    },
    mounted() {
        if (!this.lengket || !('IntersectionObserver' in window)) return;
        this.pengamat = new IntersectionObserver(
            ([e]) => {
                this.terjepit = !e.isIntersecting;
            },
            { threshold: 0 },
        );
        this.pengamat.observe(this.$refs.jangkar);
    },
    beforeUnmount() {
        this.pengamat?.disconnect();
    },
};
</script>

<style scoped>
.bl-jangkar {
    display: block;
    height: 1px;
    margin-bottom: -1px;
}

.bl {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.85rem 1.15rem;
    border-radius: 16px;
    margin-bottom: 1.25rem;
    text-decoration: none;
    border: 1px solid var(--bl-br);
    background: var(--bl-bg);
    box-shadow: 0 4px 14px -6px var(--bl-sh, rgba(0, 0, 0, 0.05));
    transition:
        transform 0.18s cubic-bezier(0.16, 1, 0.3, 1),
        border-color 0.18s ease,
        box-shadow 0.18s ease;
    backdrop-filter: blur(10px);
}
.bl:hover {
    transform: translateY(-2px);
    border-color: var(--bl-ac);
    box-shadow: 0 10px 24px -10px var(--bl-sh, rgba(0, 0, 0, 0.12));
}

.bl.netral {
    --bl-ac: var(--hcis-indigo-500);
    --bl-br: rgba(99, 102, 241, 0.25);
    --bl-fg: var(--hcis-indigo-700);
    --bl-bg: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(99, 102, 241, 0.02) 100%);
    --bl-sh: rgba(99, 102, 241, 0.18);
}
.bl.warn {
    --bl-ac: var(--hcis-amber-500);
    --bl-br: rgba(245, 158, 11, 0.35);
    --bl-fg: var(--hcis-amber-700);
    --bl-bg: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.03) 100%);
    --bl-sh: rgba(245, 158, 11, 0.2);
}
.bl.bad {
    --bl-ac: var(--hcis-red-500);
    --bl-br: rgba(239, 68, 68, 0.35);
    --bl-fg: var(--hcis-red-700);
    --bl-bg: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.03) 100%);
    --bl-sh: rgba(239, 68, 68, 0.22);
}
.bl.bersih {
    --bl-ac: var(--hcis-emerald-500);
    --bl-br: rgba(16, 185, 129, 0.28);
    --bl-fg: var(--hcis-emerald-700);
    --bl-bg: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(16, 185, 129, 0.02) 100%);
    --bl-sh: rgba(16, 185, 129, 0.15);
}

.bl.lekat {
    position: sticky;
    top: 12px;
    z-index: 25;
}
.bl.ciut {
    padding: 0.55rem 0.95rem;
    border-radius: 0 0 16px 16px;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);
    box-shadow: 0 12px 30px -15px rgba(15, 23, 42, 0.25);
}
.bl.ciut .bl-sub {
    display: none;
}
.bl.ciut .bl-ico {
    width: 30px;
    height: 30px;
    font-size: 0.85rem;
}
.bl.ciut .bl-judul {
    font-size: 0.82rem;
}

.bl-ico {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    background: var(--hcis-white);
    color: var(--bl-ac);
    font-size: 1.05rem;
    box-shadow:
        inset 0 0 0 1px rgba(0, 0, 0, 0.06),
        0 2px 6px rgba(0, 0, 0, 0.05);
    transition:
        width 0.18s ease,
        height 0.18s ease;
}
.bl-teks {
    min-width: 0;
    line-height: 1.35;
    flex: 1;
}
.bl-judul-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.bl-judul {
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--bl-fg);
    letter-spacing: -0.01em;
}
.bl-urgent-pill {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
    border-radius: 6px;
    padding: 0.08rem 0.42rem;
}
.bl-sub {
    display: block;
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
    margin-top: 0.12rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bl-cta {
    margin-left: auto;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--hcis-white);
    background: var(--bl-ac);
    border-radius: 10px;
    padding: 0.45rem 0.9rem;
    box-shadow: 0 4px 12px -3px var(--bl-sh);
    transition:
        transform 0.15s ease,
        filter 0.15s ease;
}
.bl:hover .bl-cta {
    transform: translateX(2px);
    filter: brightness(1.06);
}
.bl.bersih .bl-cta {
    color: var(--bl-fg);
    background: var(--hcis-white);
    border: 1px solid var(--bl-br);
    box-shadow: none;
}

@media (max-width: 560px) {
    .bl {
        flex-wrap: wrap;
        gap: 0.65rem;
        padding: 0.65rem 0.85rem;
        border-radius: 13px;
        margin-bottom: 0.85rem;
    }
    .bl-ico {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        font-size: 0.95rem;
    }
    .bl-judul {
        font-size: 0.82rem;
    }
    .bl-sub {
        font-size: 0.72rem;
    }
    .bl-cta {
        margin-left: 0;
        width: 100%;
        justify-content: center;
        padding: 0.42rem 0.75rem;
        font-size: 0.76rem;
        border-radius: 8px;
    }
    .bl.ciut .bl-cta {
        width: auto;
        margin-left: auto;
    }
}
</style>

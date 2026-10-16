<template>
    <a class="bd" :class="{ tuntas: isTuntas, ulang: perluUlang }" :href="url" @click="$emit('open', d)">
        <span class="bd-ikon" :style="{ '--dc': tema.color }">
            <i class="bi" :class="ikon"></i>
        </span>

        <span class="bd-isi min-w-0">
            <span class="bd-atas">
                <!-- Nomor resmi lebih dulu: orang lapangan mencari "SOP-HSE-014" -->
                <code v-if="d.Nomor_SOP" class="bd-nomor">{{ d.Nomor_SOP }}</code>
                <span class="bd-judul text-truncate" :title="d.Judul">{{ d.Judul }}</span>

                <span v-if="versiDokumen" class="bd-versi-chip" :title="`Versi Dokumen: ${versiDokumen}`">
                    {{ versiDokumen }}
                </span>

                <span v-if="d.Tipe_Item === 'KUIS'" class="bd-kuis-chip">
                    <i class="bi bi-patch-question-fill"></i> Kuis
                    <strong v-if="d.Skor_Quiz !== null"> ({{ d.Skor_Quiz }} pt{{ d.Status_Lulus ? ' · ' + d.Status_Lulus : '' }})</strong>
                </span>

                <span v-if="!d.Wajib" class="bd-opsional">opsional</span>
            </span>

            <span class="bd-meta">
                <span v-if="jenis" class="bd-chip bd-chip-jenis" :title="`Jenis Dokumen: ${jenis}`">
                    <i class="bi bi-journals"></i> {{ jenis }}
                    <span v-if="levelHirarki" class="bd-level-badge">L{{ levelHirarki }}</span>
                </span>
                <span v-if="departemen" class="bd-chip bd-chip-dept" :title="`Departemen: ${departemen}`">
                    <i class="bi bi-diagram-3-fill"></i> {{ departemen }}
                </span>
                <span v-if="apqc" class="bd-chip bd-chip-apqc" :title="`Proses APQC: ${apqc}`">
                    <i class="bi bi-diagram-2"></i> {{ apqc }}
                </span>
                <span v-if="d.Tanggal_Berlaku" class="bd-chip bd-chip-date" :title="`Tanggal Berlaku: ${d.Tanggal_Berlaku}`">
                    <i class="bi bi-calendar-check"></i> {{ d.Tanggal_Berlaku }}
                </span>
                <span class="bd-paket text-truncate" :title="d.Nama_Paket">
                    <i class="bi bi-box-seam"></i> {{ d.Nama_Paket }}
                </span>
                <span v-if="d.Tanpa_Versi" class="bd-chip warn" title="Item ini belum berdiri di atas versi yang sah terbit">
                    <i class="bi bi-exclamation-triangle-fill"></i> Tanpa versi aktif
                </span>
                <span v-if="perluUlang" class="bd-chip warn-glow" title="Versi dokumen diperbarui — wajib dibaca ulang">
                    <i class="bi bi-arrow-repeat"></i> Wajib Baca Ulang (Revisi)
                </span>
            </span>
        </span>

        <span class="bd-kanan">
            <span v-if="!rujukan" class="bd-status" :class="status.cls">
                <i class="bi" :class="status.icon"></i> {{ status.text }}
            </span>
            <i class="bi bi-arrow-right bd-panah"></i>
        </span>
    </a>
</template>

<script>
import { SOP_URL, temaAksen } from '../../support/constants.js';

/**
 * Satu baris = satu DOKUMEN yang ditugaskan.
 * Mendukung tampilan nomor SOP resmi, versi dokumen, level hirarki, proses APQC,
 * tanggal berlaku, dan peringatan revisi baca ulang.
 */
export default {
    name: 'BarisDokumen',
    props: {
        d: { type: Object, required: true },
        /** Baris rujukan (Perpustakaan): tanpa status kerja apa pun. */
        rujukan: { type: Boolean, default: false },
    },
    emits: ['open'],
    computed: {
        jenis() {
            return (this.d.Jenis_Dokumen || [])[0]?.nama || '';
        },
        levelHirarki() {
            return (this.d.Jenis_Dokumen || [])[0]?.level || this.d.Level_Hirarki || null;
        },
        departemen() {
            const dep = (this.d.Departemen || [])[0];
            if (!dep) return '';

            return dep.nama || 'Tanpa pemilik';
        },
        apqc() {
            return (this.d.Apqc || [])[0]?.nama || '';
        },
        versiDokumen() {
            if (this.d.Versi) return this.d.Versi;
            if (this.d.Versi_Ke !== null && this.d.Versi_Ke !== undefined) {
                return `v${this.d.Versi_Ke}.0`;
            }
            return '';
        },
        tema() {
            return temaAksen(this.jenis || this.d.Tipe_Item || '');
        },
        ikon() {
            const peta = {
                DOKUMEN: 'bi-file-earmark-text-fill',
                KUIS: 'bi-patch-question-fill',
                TAUTAN: 'bi-link-45deg',
                VIDEO: 'bi-play-btn-fill',
            };

            return peta[this.d.Tipe_Item] || 'bi-file-earmark-fill';
        },
        isTuntas() {
            return !this.rujukan && ['SELESAI', 'DIKONFIRMASI'].includes(this.d.Status_Item);
        },
        perluUlang() {
            return !this.rujukan && (this.d.Item_Perlu_Ulang || 0) > 0;
        },
        status() {
            if (this.perluUlang) return { text: 'Perlu dibaca ulang', icon: 'bi-arrow-repeat', cls: 'warn' };
            if (this.isTuntas) return { text: 'Tuntas', icon: 'bi-check-circle-fill', cls: 'ok' };
            if (this.d.Status_Item === 'DIBUKA') return { text: 'Sedang dibaca', icon: 'bi-hourglass-split', cls: 'run' };

            return { text: 'Belum dibaca', icon: 'bi-circle', cls: '' };
        },
        url() {
            return `${SOP_URL.detail(this.d.Id_SOP_Paket)}?item=${this.d.Id_SOP_Paket_Item}`;
        },
    },
};
</script>

<style scoped>
.bd {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 12px;
    background: #fff;
    text-decoration: none;
    color: inherit;
    transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
}
.bd + .bd {
    margin-top: 0.45rem;
}
.bd:hover {
    border-color: var(--hcis-indigo-300);
    box-shadow: 0 4px 14px -6px rgba(15, 23, 42, 0.18);
    transform: translateY(-1px);
}
.bd.tuntas {
    background: var(--hcis-slate-50);
}
.bd.ulang {
    border-color: var(--hcis-amber-300);
}

.bd-ikon {
    flex: 0 0 auto;
    width: 2rem;
    height: 2rem;
    display: grid;
    place-items: center;
    border-radius: 9px;
    color: var(--dc, var(--hcis-indigo-600));
    background: color-mix(in srgb, var(--dc, var(--hcis-indigo-600)) 10%, #fff);
    font-size: 0.95rem;
}

.bd-isi {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.bd-atas {
    display: flex;
    align-items: baseline;
    gap: 0.45rem;
    min-width: 0;
}
.bd-nomor {
    flex: 0 0 auto;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-indigo-700);
    background: rgba(99, 102, 241, 0.09);
    border-radius: 5px;
    padding: 0.06rem 0.35rem;
}
.bd-judul {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
}
.bd.tuntas .bd-judul {
    color: var(--hcis-slate-500);
}

.bd-versi-chip {
    flex: 0 0 auto;
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 4px;
    padding: 0.04rem 0.32rem;
}

.bd-kuis-chip {
    flex: 0 0 auto;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--hcis-violet-600);
    background: rgba(124, 58, 237, 0.08);
    border: 1px solid rgba(124, 58, 237, 0.2);
    border-radius: 4px;
    padding: 0.04rem 0.35rem;
}

.bd-level-badge {
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--hcis-indigo-700);
    background: rgba(99, 102, 241, 0.14);
    padding: 0 0.25rem;
    border-radius: 3px;
    margin-left: 0.15rem;
}

.bd-opsional {
    flex: 0 0 auto;
    font-size: 0.64rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-400);
}

.bd-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
}
.bd-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--hcis-slate-600);
    background: rgba(100, 116, 139, 0.08);
    border-radius: 999px;
    padding: 0.1rem 0.42rem;
}
.bd-chip-jenis {
    color: var(--hcis-indigo-700);
    background: rgba(99, 102, 241, 0.08);
}
.bd-chip-dept {
    color: var(--hcis-slate-700);
    background: rgba(51, 65, 85, 0.08);
}
.bd-chip-apqc {
    color: #0f766e;
    background: rgba(15, 118, 110, 0.08);
}
.bd-chip-date {
    color: var(--hcis-slate-500);
    background: rgba(100, 116, 139, 0.06);
}
.bd-chip.warn {
    color: var(--hcis-amber-800);
    background: rgba(245, 158, 11, 0.12);
}
.bd-chip.warn-glow {
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.18);
    border: 1px solid rgba(245, 158, 11, 0.35);
    font-weight: 700;
}
.bd-paket {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    max-width: 16rem;
    font-size: 0.68rem;
    color: var(--hcis-slate-400);
}

.bd-kanan {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.bd-status {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
}
.bd-status.ok {
    color: var(--hcis-emerald-600);
}
.bd-status.run {
    color: var(--hcis-blue-600);
}
.bd-status.warn {
    color: var(--hcis-amber-700);
}
.bd-panah {
    color: var(--hcis-slate-300);
    font-size: 0.85rem;
}
.bd:hover .bd-panah {
    color: var(--hcis-indigo-500);
}

@media (max-width: 767.98px) {
    .bd {
        padding: 0.55rem 0.75rem;
        gap: 0.6rem;
        border-radius: 10px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .bd:active {
        transform: scale(0.99);
    }
    .bd-ikon {
        width: 32px;
        height: 32px;
        font-size: 0.95rem;
        border-radius: 8px;
    }
    .bd-nomor {
        font-size: 0.66rem;
    }
    .bd-judul {
        font-size: 0.84rem;
    }
    .bd-chip {
        font-size: 0.64rem;
        padding: 0.08rem 0.35rem;
    }
    .bd-kanan {
        flex-direction: column;
        align-items: flex-end;
        gap: 0.15rem;
    }
    .bd-status {
        font-size: 0.65rem;
    }
    .bd-paket {
        max-width: 100%;
    }
}
</style>

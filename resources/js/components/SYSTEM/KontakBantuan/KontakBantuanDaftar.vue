<template>
    <ul class="kontak-bantuan__daftar" data-testid="kontak-bantuan-daftar">
        <li
            v-for="(k, i) in daftar"
            :key="`${k.nama}-${i}`"
            class="kontak-bantuan__item"
            :class="{ 'is-open': isTerbuka(i) }"
        >
            <!-- Accordion Header: Compact Row with Available Channel Icons -->
            <div
                class="kontak-bantuan__item-header"
                role="button"
                tabindex="0"
                :aria-expanded="isTerbuka(i)"
                @click="toggleKontak(i)"
                @keydown.enter.space.prevent="toggleKontak(i)"
            >
                <!-- Avatar & Status Dot -->
                <div class="kontak-bantuan__avatar-wrap">
                    <div class="kontak-bantuan__avatar" aria-hidden="true">
                        {{ inisial(k.nama) }}
                    </div>
                    <span class="kontak-bantuan__status-badge" title="PIC Resmi Aktif" aria-label="PIC Resmi Aktif"></span>
                </div>

                <!-- Name & Role Stack -->
                <div class="kontak-bantuan__identitas">
                    <div class="kontak-bantuan__nama-row">
                        <strong class="kontak-bantuan__nama">{{ k.nama }}</strong>
                        <span v-if="k.keterangan" class="kontak-bantuan__keterangan">
                            <i class="bi bi-person-badge"></i>
                            <span>{{ k.keterangan }}</span>
                        </span>
                    </div>
                </div>

                <!-- Available Channel Badges + Chevron -->
                <div class="kontak-bantuan__channels">
                    <!-- WhatsApp Quick Badge (Clickable directly) -->
                    <a
                        v-if="k.tautan_wa"
                        :href="k.tautan_wa"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="kontak-bantuan__chan-badge is-wa"
                        :title="`Buka WhatsApp ${k.no_hp}`"
                        aria-label="Buka WhatsApp"
                        @click.stop
                    >
                        <HcisIcon name="whatsapp" size="2xs" />
                    </a>

                    <!-- Telepon Channel Icon -->
                    <span
                        v-if="k.tautan_telepon"
                        class="kontak-bantuan__chan-badge is-tel"
                        :title="`Tersedia Telepon: ${k.no_hp}`"
                    >
                        <HcisIcon name="telephone-fill" size="2xs" />
                    </span>

                    <!-- Email Channel Icon -->
                    <span
                        v-if="k.tautan_email"
                        class="kontak-bantuan__chan-badge is-email"
                        :title="`Tersedia Email: ${k.email}`"
                    >
                        <HcisIcon name="envelope-fill" size="2xs" />
                    </span>

                    <!-- Accordion Chevron Indicator -->
                    <span class="kontak-bantuan__chevron-wrap" aria-hidden="true">
                        <i class="bi bi-chevron-down kontak-bantuan__chevron" :class="{ 'is-rotated': isTerbuka(i) }"></i>
                    </span>
                </div>
            </div>

            <!-- Accordion Body: Expanded Details & Full Action Cards -->
            <div v-show="isTerbuka(i)" class="kontak-bantuan__item-body">
                <!-- Operational Hours -->
                <div v-if="k.jam_layanan" class="kontak-bantuan__jam-badge">
                    <HcisIcon name="clock" size="2xs" />
                    <span class="kontak-bantuan__jam">{{ k.jam_layanan }}</span>
                </div>

                <!-- Action Cards -->
                <div class="kontak-bantuan__aksi">
                    <!-- WhatsApp Primary Action -->
                    <a
                        v-if="k.tautan_wa"
                        :href="k.tautan_wa"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="kontak-bantuan__action-card kontak-bantuan__tautan is-wa"
                        :title="`Chat WhatsApp ${k.no_hp}`"
                    >
                        <span class="kontak-bantuan__action-icon">
                            <HcisIcon name="whatsapp" size="sm" />
                        </span>
                        <div class="kontak-bantuan__action-info">
                            <span class="kontak-bantuan__action-label">Chat WhatsApp Resmi</span>
                            <span class="kontak-bantuan__action-val">{{ k.no_hp }}</span>
                        </div>
                        <i class="bi bi-arrow-up-right kontak-bantuan__action-arrow"></i>
                    </a>

                    <!-- Telepon Action Card with Copy Button -->
                    <div v-if="k.tautan_telepon" class="kontak-bantuan__action-card is-tel">
                        <a
                            :href="k.tautan_telepon"
                            class="kontak-bantuan__action-link kontak-bantuan__tautan"
                            :title="`Panggil ${k.no_hp}`"
                        >
                            <span class="kontak-bantuan__action-icon">
                                <HcisIcon name="telephone-fill" size="sm" />
                            </span>
                            <div class="kontak-bantuan__action-info">
                                <span class="kontak-bantuan__action-label">Panggilan Suara</span>
                                <span class="kontak-bantuan__action-val">{{ k.no_hp }}</span>
                            </div>
                        </a>
                        <button
                            type="button"
                            class="kontak-bantuan__action-copy"
                            :class="{ 'is-copied': disalin === `tel-${i}` }"
                            :title="disalin === `tel-${i}` ? 'Nomor tersalin!' : 'Salin nomor telepon'"
                            aria-label="Salin nomor telepon"
                            @click.stop="salin(k.no_hp, `tel-${i}`)"
                        >
                            <i :class="['bi', disalin === `tel-${i}` ? 'bi-check-lg' : 'bi-copy']"></i>
                            <span v-if="disalin === `tel-${i}`" class="kontak-bantuan__copy-tip">Tersalin!</span>
                        </button>
                    </div>

                    <!-- Email Action Card with Copy Button -->
                    <div v-if="k.tautan_email" class="kontak-bantuan__action-card is-email">
                        <a
                            :href="k.tautan_email"
                            class="kontak-bantuan__action-link kontak-bantuan__tautan"
                            :title="`Kirim email ke ${k.email}`"
                        >
                            <span class="kontak-bantuan__action-icon">
                                <HcisIcon name="envelope-fill" size="sm" />
                            </span>
                            <div class="kontak-bantuan__action-info">
                                <span class="kontak-bantuan__action-label">Kirim Pesan Email</span>
                                <span class="kontak-bantuan__action-val text-truncate">{{ k.email }}</span>
                            </div>
                        </a>
                        <button
                            type="button"
                            class="kontak-bantuan__action-copy"
                            :class="{ 'is-copied': disalin === `mail-${i}` }"
                            :title="disalin === `mail-${i}` ? 'Email tersalin!' : 'Salin email'"
                            aria-label="Salin alamat email"
                            @click.stop="salin(k.email, `mail-${i}`)"
                        >
                            <i :class="['bi', disalin === `mail-${i}` ? 'bi-check-lg' : 'bi-copy']"></i>
                            <span v-if="disalin === `mail-${i}`" class="kontak-bantuan__copy-tip">Tersalin!</span>
                        </button>
                    </div>
                </div>
            </div>
        </li>
    </ul>
</template>

<script>
import { HcisIcon } from '@/components/ui';

/**
 * Daftar kontak bantuan resmi dengan arsitektur accordion per kontak.
 *
 * Di header setiap kontak, ditampilkan badge icon saluran yang tersedia (WA, Tel, Email).
 * Ketika diklik/dibuka, tampil detail jam layanan serta kartu aksi lengkap dengan 1-click copy.
 */
export default {
    name: 'KontakBantuanDaftar',
    components: { HcisIcon },
    props: {
        kontak: { type: Array, default: () => [] },
    },
    data() {
        return {
            terbukaSet: new Set(),
            disalin: '',
            salinTimer: null,
        };
    },
    computed: {
        daftar() {
            return (this.kontak || []).filter((k) => k && k.nama && (k.tautan_wa || k.tautan_email));
        },
    },
    watch: {
        daftar() {
            this.terbukaSet = new Set();
        },
    },
    beforeUnmount() {
        if (this.salinTimer) clearTimeout(this.salinTimer);
    },
    methods: {
        inisial(nama) {
            if (!nama) return 'PIC';
            const kata = nama.trim().split(/\s+/).filter(Boolean);
            if (kata.length === 1) return kata[0].substring(0, 2).toUpperCase();
            return (kata[0][0] + (kata[1] ? kata[1][0] : '')).toUpperCase();
        },
        toggleKontak(idx) {
            const next = new Set(this.terbukaSet);
            if (next.has(idx)) {
                next.delete(idx);
            } else {
                next.add(idx);
            }
            this.terbukaSet = next;
        },
        isTerbuka(idx) {
            return this.terbukaSet.has(idx);
        },
        async salin(teks, kunci) {
            if (!teks) return;
            try {
                if (navigator?.clipboard?.writeText) {
                    await navigator.clipboard.writeText(teks);
                } else {
                    const el = document.createElement('textarea');
                    el.value = teks;
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                }
                this.disalin = kunci;
                if (this.salinTimer) clearTimeout(this.salinTimer);
                this.salinTimer = setTimeout(() => {
                    if (this.disalin === kunci) this.disalin = '';
                }, 2000);
            } catch {
                // Ignore copy error
            }
        },
    },
};
</script>

<style scoped>
.kontak-bantuan__daftar {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.kontak-bantuan__item {
    display: flex;
    flex-direction: column;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 200ms cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}

.kontak-bantuan__item:hover {
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 4px 14px -2px rgba(79, 70, 229, 0.08);
}

.kontak-bantuan__item.is-open {
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 4px 16px -2px rgba(79, 70, 229, 0.09);
}

/* Accordion Header */
.kontak-bantuan__item-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.95rem;
    cursor: pointer;
    user-select: none;
    transition: background-color 160ms ease;
}

.kontak-bantuan__item-header:hover {
    background-color: rgba(248, 250, 252, 0.85);
}

.kontak-bantuan__item-header:focus-visible {
    outline: 2px solid #4f46e5;
    outline-offset: -2px;
}

/* Avatar with duty badge */
.kontak-bantuan__avatar-wrap {
    position: relative;
    flex-shrink: 0;
}

.kontak-bantuan__avatar {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 11px;
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
    color: #4f46e5;
    font-size: 0.825rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(199, 210, 254, 0.8);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.kontak-bantuan__status-badge {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #22c55e;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.2);
}

.kontak-bantuan__identitas {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    flex: 1;
}

.kontak-bantuan__nama-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 0.5rem;
}

.kontak-bantuan__nama {
    font-size: 0.875rem;
    font-weight: 750;
    color: #0f172a;
    line-height: 1.25;
}

.kontak-bantuan__keterangan {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 650;
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.08);
    border: 1px solid rgba(79, 70, 229, 0.14);
    line-height: 1.2;
}

.kontak-bantuan__keterangan i {
    font-size: 0.7rem;
}

/* Available Channel Indicators */
.kontak-bantuan__channels {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-left: auto;
    flex-shrink: 0;
}

.kontak-bantuan__chan-badge {
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
    text-decoration: none;
}

.kontak-bantuan__chan-badge.is-wa {
    background: #dcfce7;
    color: #16a34a;
    border: 1px solid #bbf7d0;
}

.kontak-bantuan__chan-badge.is-wa:hover {
    background: #16a34a;
    color: #ffffff;
    border-color: #16a34a;
    transform: scale(1.08);
}

.kontak-bantuan__chan-badge.is-tel {
    background: #f0f9ff;
    color: #0284c7;
    border: 1px solid #bae6fd;
}

.kontak-bantuan__chan-badge.is-email {
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
}

.kontak-bantuan__chevron-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    color: #94a3b8;
    margin-left: 0.2rem;
}

.kontak-bantuan__chevron {
    font-size: 0.75rem;
    transition: transform 220ms cubic-bezier(0.16, 1, 0.3, 1);
}

.kontak-bantuan__chevron.is-rotated {
    transform: rotate(180deg);
    color: #4f46e5;
}

/* Accordion Body */
.kontak-bantuan__item-body {
    padding: 0.75rem 0.95rem 0.95rem;
    border-top: 1px solid #f1f5f9;
    background: rgba(250, 252, 255, 0.6);
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

/* Jam Layanan */
.kontak-bantuan__jam-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    align-self: flex-start;
    padding: 0.22rem 0.6rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #475569;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.kontak-bantuan__jam {
    color: #475569;
}

/* Action Cards */
.kontak-bantuan__aksi {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.kontak-bantuan__action-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.55rem 0.75rem;
    border-radius: 11px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    transition: all 180ms cubic-bezier(0.16, 1, 0.3, 1);
    text-decoration: none;
}

.kontak-bantuan__action-card:hover {
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 3px 8px -1px rgba(15, 23, 42, 0.08);
}

/* WhatsApp Card */
.kontak-bantuan__action-card.is-wa {
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.8) 0%, rgba(220, 252, 231, 0.4) 100%);
    border-color: rgba(187, 247, 208, 0.9);
}

.kontak-bantuan__action-card.is-wa:hover {
    background: linear-gradient(135deg, rgba(220, 252, 231, 0.95) 0%, rgba(187, 247, 208, 0.6) 100%);
    border-color: #86efac;
    transform: translateY(-1px);
}

.kontak-bantuan__action-card.is-wa .kontak-bantuan__action-icon {
    background: #16a34a;
    color: #ffffff;
}

.kontak-bantuan__action-card.is-wa .kontak-bantuan__action-label {
    color: #15803d;
}

.kontak-bantuan__action-card.is-wa .kontak-bantuan__action-val {
    color: #166534;
}

.kontak-bantuan__action-card.is-wa .kontak-bantuan__action-arrow {
    color: #15803d;
    font-size: 1.1rem;
    margin-right: 0.25rem;
    transition: transform 160ms ease;
}

.kontak-bantuan__action-card.is-wa:hover .kontak-bantuan__action-arrow {
    transform: translate(2px, -2px);
}

/* Action Icon Box */
.kontak-bantuan__action-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
    margin-right: 0.65rem;
}

.kontak-bantuan__action-card.is-tel .kontak-bantuan__action-icon {
    background: #e0f2fe;
    color: #0284c7;
    border: 1px solid #bae6fd;
}

.kontak-bantuan__action-card.is-email .kontak-bantuan__action-icon {
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
}

/* Action Link */
.kontak-bantuan__action-link {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    text-decoration: none;
    color: inherit;
}

.kontak-bantuan__action-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
    line-height: 1.25;
}

.kontak-bantuan__action-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.kontak-bantuan__action-val {
    font-size: 0.825rem;
    font-weight: 700;
    color: #0f172a;
}

/* Action Copy Button */
.kontak-bantuan__action-copy {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    cursor: pointer;
    font-size: 0.8rem;
    flex-shrink: 0;
    margin-left: 0.5rem;
    position: relative;
    transition: all 150ms ease;
}

.kontak-bantuan__action-copy:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
}

.kontak-bantuan__action-copy.is-copied {
    background: #dcfce7;
    color: #16a34a;
    border-color: #86efac;
}

.kontak-bantuan__copy-tip {
    position: absolute;
    bottom: calc(100% + 5px);
    right: 0;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    white-space: nowrap;
    pointer-events: none;
    animation: tipFade 180ms ease-out;
    z-index: 10;
}

@keyframes tipFade {
    from {
        opacity: 0;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Universal icon centering reset */
.kontak-bantuan__item :deep(i.bi::before),
.kontak-bantuan__item i.bi::before {
    vertical-align: 0 !important;
    line-height: 1;
    display: inline-block;
}

@media (max-width: 640px) {
    .kontak-bantuan__item-header {
        padding: 0.7rem 0.8rem;
    }

    .kontak-bantuan__avatar {
        width: 2.15rem;
        height: 2.15rem;
        font-size: 0.78rem;
    }

    .kontak-bantuan__channels {
        gap: 0.25rem;
    }

    .kontak-bantuan__chan-badge {
        width: 1.5rem;
        height: 1.5rem;
        font-size: 0.7rem;
    }

    .kontak-bantuan__item-body {
        padding: 0.65rem 0.8rem 0.85rem;
    }

    .kontak-bantuan__action-val {
        font-size: 0.78rem;
    }
}
</style>

<template>
    <div class="live-preview sop-scope" :class="{ 'is-compact': compact, full: !compact }">
        <!-- Scroll progress top line matching SopDetail.vue -->
        <div class="rd-scrollbar" aria-hidden="true" style="width: 0%"></div>

        <div class="reader-shell" ref="scrollContainer">
            <div class="sop-workspace" :class="compact ? 'split-stacked' : 'split-left'">
                <!-- ============ RAIL / KOKPIT BACA (Desktop Column or Mobile Bottom Sheet) ============ -->
                <div
                    class="rd-side"
                    :class="{
                        'is-sheet': compact,
                        open: compact && mobileSheetOpen,
                        'compact-hide': compact && !mobileSheetOpen,
                    }"
                    @click.self="mobileSheetOpen = false"
                >
                    <aside class="sop-panel is-sticky rd-rail sop-fade-up">
                        <!-- Mobile Sheet Header (Only visible in compact/mobile bottom sheet mode) -->
                        <div v-if="compact" class="rd-sheet-head">
                            <span class="rd-sheet-grip"></span>
                            <button
                                class="rd-sheet-close"
                                type="button"
                                @click="mobileSheetOpen = false"
                                title="Tutup Panel"
                            >
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        <!-- Pengalih tab (segmented control) -->
                        <div
                            class="rail-seg"
                            role="tablist"
                            aria-label="Panel samping dokumen"
                            :style="{ '--seg-i': railTabIndex, '--seg-n': railTabs.length }"
                        >
                            <span class="rail-seg-thumb" aria-hidden="true"></span>
                            <button
                                v-for="(t, idx) in railTabs"
                                :key="t.key"
                                class="rail-seg-btn"
                                :class="{ on: railTab === t.key }"
                                type="button"
                                @click="railTab = t.key"
                            >
                                <i class="bi" :class="t.icon"></i>
                                <span>{{ t.label }}</span>
                            </button>
                        </div>

                        <transition name="sop-swap" mode="out-in">
                            <div :key="railTab" class="rail-panel">
                                <!-- TAB 1: DAFTAR ISI -->
                                <template v-if="railTab === 'toc'">
                                    <div class="rail-card rail-gauge">
                                        <SopRing
                                            :value="0"
                                            :size="110"
                                            :stroke="10"
                                            color="#6366f1"
                                            color2="#818cf8"
                                            label="Progres"
                                        />
                                        <div class="rail-gauge-sub">
                                            <span class="tnum">0</span> dari
                                            <span class="tnum">{{ wajibCount }}</span> item wajib
                                        </div>
                                        <div v-if="paket && paket.Estimasi_Waktu_Baca_Menit" class="rail-pill soft">
                                            <i class="bi bi-hourglass-split"></i> ~{{ paket.Estimasi_Waktu_Baca_Menit }}
                                            mnt tersisa
                                        </div>
                                    </div>

                                    <button class="stitch-btn primary block rd-lanjut pulse-glow" disabled>
                                        <i class="bi bi-play-fill fs-5"></i> Mulai Membaca
                                    </button>

                                    <nav class="rd-toc" aria-label="Daftar isi &amp; Minimap Dokumen">
                                        <div class="rd-toc-head">
                                            <span>Struktur Bagian &amp; Minimap</span>
                                            <span class="rail-pill soft tnum">{{ sections.length }} bagian</span>
                                        </div>
                                        <div
                                            v-for="(sec, si) in sections"
                                            :key="sec.Id_SOP_Section"
                                            class="rd-toc-sec-group"
                                        >
                                            <button
                                                class="rd-toc-item"
                                                :class="{ active: activeTocSec === sec.Id_SOP_Section }"
                                                type="button"
                                                @click="scrollToSection(sec.Id_SOP_Section)"
                                            >
                                                <span class="rd-toc-no">{{ si + 1 }}</span>
                                                <span class="rd-toc-body">
                                                    <span class="rd-toc-name">{{ sec.Nama_Section }}</span>
                                                    <span class="rd-toc-bar"><i style="width: 0%"></i></span>
                                                </span>
                                                <span class="rd-toc-prog tnum"
                                                    >0/{{ itemsInSection(sec.Id_SOP_Section).length }}</span
                                                >
                                            </button>

                                            <!-- Sub-items List (Minimap Accordion) -->
                                            <div class="rd-toc-sublist">
                                                <button
                                                    v-for="it in itemsInSection(sec.Id_SOP_Section)"
                                                    :key="it._id"
                                                    class="rd-toc-subitem"
                                                    :class="{
                                                        current: editingId && it.Id_SOP_Paket_Item === editingId,
                                                    }"
                                                    type="button"
                                                    @click="scrollToItem(it._id)"
                                                >
                                                    <span class="rd-toc-subico">
                                                        <i class="bi" :class="itemIcon(it.Tipe_Item)"></i>
                                                    </span>
                                                    <span class="rd-toc-subttl text-truncate" :title="it.Judul_Item">{{
                                                        it.Judul_Item || 'Item Baru'
                                                    }}</span>
                                                    <span class="rd-toc-subbadge">{{ tipeLabel(it.Tipe_Item) }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </nav>
                                </template>

                                <!-- TAB 2: LEGALITAS -->
                                <template v-else-if="railTab === 'info'">
                                    <div class="legal-hero complete">
                                        <div class="legal-hero-icon"><i class="bi bi-patch-check-fill"></i></div>
                                        <div class="min-w-0">
                                            <div class="legal-eyebrow">Legalitas Dokumen</div>
                                            <div class="legal-title">
                                                {{
                                                    paket && paket.Status === 'PUBLISHED'
                                                        ? 'Dokumen Terkendali Resmi'
                                                        : 'Draft Dokumen'
                                                }}
                                            </div>
                                            <div class="legal-sub">Identitas &amp; versi resmi sistem HRIS</div>
                                        </div>
                                    </div>

                                    <div class="legal-grid">
                                        <div class="legal-metric">
                                            <span>Status Dokumen</span>
                                            <strong>{{
                                                paket && paket.Status === 'PUBLISHED' ? 'Terbit Resmi' : 'Draft Aktif'
                                            }}</strong>
                                        </div>
                                        <div class="legal-metric">
                                            <span>Versi Paket</span>
                                            <strong class="tnum"
                                                >v{{ (paket && paket.Versi_Paket_Aktif) || '1.0' }}</strong
                                            >
                                        </div>
                                    </div>

                                    <div class="rail-card legal-package-card">
                                        <div class="rail-cap-row">
                                            <span class="rail-cap">Identitas Paket Resmi</span>
                                            <span class="rail-pill ok"><i class="bi bi-shield-check"></i> Dokumen</span>
                                        </div>
                                        <dl class="legal-kv">
                                            <dt>Nama Paket</dt>
                                            <dd>{{ (paket && paket.Nama_Paket) || '—' }}</dd>
                                            <dt>Prioritas</dt>
                                            <dd>{{ prio.label }}</dd>
                                            <dt>Absensi Gate</dt>
                                            <dd>
                                                {{
                                                    paket && paket.Flag_Absensi === 'Y'
                                                        ? 'Wajib Check-in'
                                                        : 'Tanpa Check-in'
                                                }}
                                            </dd>
                                        </dl>
                                    </div>

                                    <div class="rail-card legal-doc-table">
                                        <div class="rail-cap-row">
                                            <span class="rail-cap">Daftar Item &amp; Versi</span>
                                            <span class="rail-pill soft">{{ totalItems }} item</span>
                                        </div>
                                        <div class="legal-item-list">
                                            <div v-for="doc in allItems" :key="doc._id" class="legal-item-row">
                                                <i class="bi" :class="itemIcon(doc.Tipe_Item)"></i>
                                                <div class="min-w-0 flex-grow-1">
                                                    <strong class="text-truncate d-block">{{
                                                        doc.Judul_Item || 'Item Baru'
                                                    }}</strong>
                                                    <small class="text-truncate d-block"
                                                        >{{ tipeLabel(doc.Tipe_Item) }}
                                                        <template v-if="doc.wajib">· wajib</template></small
                                                    >
                                                </div>
                                                <span class="legal-item-status idle flex-shrink-0">Draf</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- TAB 3: PROGRES -->
                                <template v-else>
                                    <div class="rail-card rail-stat">
                                        <div class="rail-cap">Kepatuhan Kamu</div>
                                        <div class="rail-stat-big tnum">0<span>%</span></div>
                                        <div class="rail-sub">Simulasi tampilan progres pembacaan karyawan</div>

                                        <div class="rail-split">
                                            <i class="s-no" style="width: 100%"></i>
                                        </div>
                                        <ul class="rail-legend">
                                            <li>
                                                <span class="dot no"></span> Belum dibuka
                                                <b class="tnum">{{ totalItems }}</b>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="rail-kv">
                                        <div>
                                            <span><i class="bi bi-list-check"></i> Total Item</span
                                            ><b class="tnum">{{ totalItems }}</b>
                                        </div>
                                        <div>
                                            <span><i class="bi bi-asterisk"></i> Langkah Wajib</span
                                            ><b class="tnum">{{ wajibCount }}</b>
                                        </div>
                                        <div>
                                            <span><i class="bi bi-clock"></i> Estimasi Baca</span
                                            ><b>~{{ (paket && paket.Estimasi_Waktu_Baca_Menit) || 10 }} mnt</b>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </transition>
                    </aside>
                </div>

                <!-- ============ MAIN READER CONTENT ============ -->
                <main class="rd-main sop-fade-up">
                    <!-- Ultra-Premium Hero Banner matching SopDetail.vue -->
                    <div class="rd-hero" :style="heroStyle">
                        <img
                            v-if="paket && paket.Cover_Url"
                            :src="paket.Cover_Url"
                            class="rd-hero-img rd-kenburns"
                            :style="{ objectPosition: (paket && paket.Cover_Position) || 'center' }"
                            alt=""
                        />
                        <div class="rd-hero-mesh"></div>
                        <div class="rd-hero-shade"></div>

                        <!-- Hero Integrated Top Navigation & Quick Actions -->
                        <div
                            class="rd-hero-top d-flex align-items-center justify-content-between p-3 position-relative"
                            style="z-index: 3"
                        >
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <a class="rd-hero-btn" href="#" title="Kembali" @click.prevent>
                                    <i class="bi bi-arrow-left"></i>
                                    <span>Kembali</span>
                                </a>
                                <nav class="rd-hero-bc d-none d-md-flex align-items-center gap-2 text-truncate ms-1">
                                    <span>Functional Document</span>
                                    <i class="bi bi-chevron-right small opacity-75"></i>
                                    <span class="fw-bold text-truncate">{{
                                        (paket && paket.Nama_Paket) || 'Pratinjau Dokumen'
                                    }}</span>
                                </nav>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button class="rd-hero-btn" type="button" title="Mode Fokus Membaca">
                                    <i class="bi bi-arrows-angle-expand"></i>
                                    <span>Mode Fokus</span>
                                </button>
                            </div>
                        </div>

                        <div class="rd-hero-body">
                            <div class="rd-hero-eyebrow">
                                <span v-if="paket && paket.Kode_Paket" class="rd-kode">
                                    <i class="bi bi-hash me-1"></i>{{ paket.Kode_Paket }}
                                </span>
                                <span class="rd-kat">
                                    <i class="bi bi-folder2-open me-1"></i>
                                    {{ (paket && paket.Nama_Paket) || 'Dokumen' }}
                                </span>
                            </div>

                            <h1 class="rd-title">{{ (paket && paket.Nama_Paket) || 'Nama Paket Dokumen' }}</h1>

                            <div class="rd-chips">
                                <span
                                    class="rd-chip"
                                    :style="
                                        prio.warna
                                            ? {
                                                  color: prio.warna,
                                                  borderColor: prio.warna + '4d',
                                                  background: prio.warna + '1f',
                                              }
                                            : null
                                    "
                                >
                                    <i class="bi bi-flag-fill"></i> Prioritas {{ prio.label }}
                                </span>
                                <span v-if="paket && paket.Estimasi_Waktu_Baca_Menit" class="rd-chip">
                                    <i class="bi bi-clock-history"></i> ~{{ paket.Estimasi_Waktu_Baca_Menit }} Menit
                                    Baca
                                </span>
                                <span v-if="paket && paket.Mode_Urutan_Item === 'SEKUENSIAL'" class="rd-chip">
                                    <i class="bi bi-signpost-2-fill"></i> Urutan Sekuensial
                                </span>
                                <span class="rd-chip rd-chip-seal">
                                    <i class="bi bi-patch-check-fill"></i> Dokumen Terkendali Resmi
                                </span>
                            </div>
                        </div>

                        <!-- Glassmorphism Spec Bar -->
                        <div class="rd-spec">
                            <div class="rd-spec-cell">
                                <span class="rd-spec-lbl"
                                    ><i class="bi bi-calendar-check-fill"></i> Tanggal Terbit</span
                                >
                                <span class="rd-spec-val">{{ formattedTanggalPublish }}</span>
                            </div>
                            <div class="rd-spec-cell">
                                <span class="rd-spec-lbl"><i class="bi bi-arrow-repeat"></i> Terakhir Diperbarui</span>
                                <span class="rd-spec-val">Baru Saja</span>
                            </div>
                            <div class="rd-spec-cell">
                                <span class="rd-spec-lbl"><i class="bi bi-list-check"></i> Total Langkah</span>
                                <span class="rd-spec-val">{{ wajibCount }} Wajib</span>
                            </div>
                        </div>
                    </div>

                    <!-- Banner Penugasan Karyawan Preview -->
                    <div class="rd-assign mb-3">
                        <div class="rd-assign-main">
                            <div class="rd-assign-icon">
                                <i class="bi bi-calendar2-week-fill"></i>
                            </div>
                            <div>
                                <div class="rd-assign-title">Simulasi Tampilan Penugasan Karyawan</div>
                                <div class="rd-assign-sub">
                                    <span>Penugasan Aktif · Tenggat fleksibel</span>
                                </div>
                            </div>
                        </div>
                        <StatusPill text="BELUM DIBUKA" variant="idle" />
                    </div>

                    <!-- Gerbang Absensi Card (Check-in Kehadiran) -->
                    <div v-if="paket && paket.Flag_Absensi === 'Y'" class="rd-gate mb-3">
                        <span class="rd-gate-badge"
                            ><i class="bi bi-shield-lock-fill"></i> Wajib Check-in Sebelum Membaca</span
                        >
                        <div class="rd-gate-row">
                            <div class="rd-gate-ico"><i class="bi bi-camera-fill"></i></div>
                            <div>
                                <h5>Gerbang Absensi Kehadiran</h5>
                                <p class="rd-gate-note">
                                    Karyawan wajib melakukan verifikasi foto &amp; lokasi sebelum membuka isi dokumen.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Deskripsi & Ruang Lingkup Dokumen -->
                    <div v-if="hasAbout" class="rd-about mb-3">
                        <button class="rd-about-toggle" type="button" @click="aboutOpen = !aboutOpen">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill text-primary"></i>
                                <span>Deskripsi &amp; Ruang Lingkup Dokumen</span>
                            </div>
                            <i class="bi" :class="aboutOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                        </button>
                        <transition name="sop-swap">
                            <div v-if="aboutOpen" class="rd-about-body">
                                <p v-if="paket && paket.Deskripsi" class="rd-desc">{{ paket.Deskripsi }}</p>
                                <div v-if="paket && paket.Tujuan_Ruang_Lingkup" class="rd-scope mt-2">
                                    <div class="rd-scope-lbl">
                                        <i class="bi bi-bullseye me-1"></i> Tujuan &amp; Ruang Lingkup Operasional
                                    </div>
                                    <p>{{ paket.Tujuan_Ruang_Lingkup }}</p>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <!-- Quick Saring Item (Filter Bar) -->
                    <div v-if="totalItems > 2" class="rd-filter mb-3" role="tablist">
                        <button
                            v-for="f in itemFilters"
                            :key="f.key"
                            class="rd-fchip"
                            :class="{ on: activeFilter === f.key }"
                            type="button"
                            @click="activeFilter = f.key"
                        >
                            <i class="bi" :class="f.icon"></i> {{ f.label }}
                            <span class="rd-fcount tnum">{{ f.count }}</span>
                        </button>
                    </div>

                    <!-- Reading Journey Spine Timeline matching SopDetail.vue -->
                    <div class="rd-flow">
                        <div v-if="!filteredSections.length" class="lp-empty p-4 text-center text-muted">
                            <i class="bi bi-inboxes fs-2"></i>
                            <div class="mt-2">
                                Belum ada section. Tambahkan section &amp; item di panel struktur untuk mulai menyusun
                                paket dokumen ini.
                            </div>
                        </div>

                        <template v-for="(sec, si) in filteredSections" :key="sec.Id_SOP_Section">
                            <!-- Section Header Container & Interactive Card -->
                            <div :id="'sec-' + sec.Id_SOP_Section" class="flow-sec">
                                <div class="flow-node sec">
                                    {{ si + 1 }}
                                </div>

                                <div class="flow-sec-card d-flex align-items-center justify-content-between">
                                    <div class="flow-sec-body min-w-0 me-2">
                                        <div class="rd-sec-name">{{ sec.Nama_Section }}</div>
                                        <div v-if="sec.Deskripsi" class="rd-sec-desc">{{ sec.Deskripsi }}</div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                        <span class="rd-sec-prog tnum">
                                            0/{{ itemsInSection(sec.Id_SOP_Section).length }} Wajib
                                        </span>
                                        <span class="flow-sec-chevron open">
                                            <i class="bi bi-chevron-down"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Step Item Cards List -->
                            <div class="flow-sec-content">
                                <div
                                    v-for="(it, i) in filteredItemsInSection(sec.Id_SOP_Section)"
                                    :key="it._id"
                                    :id="'item-' + it._id"
                                    :ref="it._editing ? 'editing' : undefined"
                                >
                                    <SopPreviewItem :item="it" :n="i + 1" :editing="it._editing" />
                                </div>
                                <p
                                    v-if="!itemsInSection(sec.Id_SOP_Section).length"
                                    class="lp-sec-empty text-muted fst-italic ms-4"
                                >
                                    Belum ada item di section ini.
                                </p>
                            </div>
                        </template>

                        <!-- Terminal End-Cap Node on Spine Line -->
                        <div v-if="sections.length" class="rd-flow-terminal my-4">
                            <div class="rd-terminal-node">
                                <div class="rd-terminal-ripple"></div>
                                <i class="bi bi-flag-fill"></i>
                            </div>
                            <div class="rd-terminal-card">
                                <div class="rd-terminal-title">
                                    <i class="bi bi-check2-circle text-success me-1.5 fs-5"></i> Akhir Dokumen
                                </div>
                                <div class="small text-muted">Seluruh langkah dalam paket dokumen ini telah terdaftar.</div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<script>
import SopPreviewItem from './SopPreviewItem.vue';
import SopRing from './SopRing.vue';
import StatusPill from './StatusPill.vue';
import { temaAksen, isFileTipe, TIPE_ITEM } from '../../support/constants.js';
import { metaPrioritas } from '../../support/prioritas.js';

/**
 * SopLivePreview — Pratinjau Dokumen 100% Akurat Ala Tampilan Karyawan (SopDetail.vue).
 * Mendukung Tampilan Desktop dan Mobile Bottom Bar + Sheet Kokpit Baca.
 */
export default {
    name: 'SopLivePreview',
    components: { SopPreviewItem, SopRing, StatusPill },
    props: {
        paket: { type: Object, default: null },
        sections: { type: Array, default: () => [] },
        items: { type: Array, default: () => [] },
        editingId: { type: [String, Number], default: null },
        editingItem: { type: Object, default: null },
        adding: { type: Boolean, default: false },
        compact: { type: Boolean, default: false },
    },
    data() {
        return {
            railTab: 'toc',
            railTabs: [
                { key: 'toc', label: 'Daftar Isi', icon: 'bi-list-task' },
                { key: 'info', label: 'Legalitas', icon: 'bi-shield-check' },
                { key: 'progress', label: 'Progres', icon: 'bi-pie-chart' },
            ],
            aboutOpen: true,
            activeFilter: 'ALL',
            activeTocSec: null,
            mobileSheetOpen: false,
        };
    },
    computed: {
        formattedTanggalPublish() {
            if (!this.paket || !this.paket.Tanggal_Publish) return 'Draf (Belum Terbit)';
            return this.formatTanggalIndo(this.paket.Tanggal_Publish);
        },
        firstSectionName() {
            return (this.sections && this.sections[0] && this.sections[0].Nama_Section) || 'Daftar Isi dokumen';
        },
        railTabIndex() {
            return this.railTabs.findIndex((t) => t.key === this.railTab);
        },
        prio() {
            return metaPrioritas(this.paket && this.paket.Prioritas);
        },
        heroStyle() {
            const t = temaAksen((this.paket && this.paket.Nama_Paket) || '');
            return { background: `linear-gradient(135deg, ${t.color}, ${t.color}cc)` };
        },
        hasAbout() {
            return !!(this.paket && (this.paket.Deskripsi || this.paket.Tujuan_Ruang_Lingkup));
        },
        allItems() {
            const out = (this.items || []).map((it) => {
                const isEd = this.editingId && it.Id_SOP_Paket_Item === this.editingId;
                const src = isEd && this.editingItem ? { ...it, ...this.editingItem } : it;
                return this.normalize(src, !!isEd);
            });
            if (this.adding && this.editingItem && this.editingItem.Tipe_Item) {
                out.push(this.normalize({ ...this.editingItem, Id_SOP_Paket_Item: '__new__' }, true));
            }
            return out;
        },
        totalItems() {
            return this.allItems.length;
        },
        wajibCount() {
            return this.allItems.filter((it) => it.wajib).length;
        },

        itemFilters() {
            return [
                { key: 'ALL', label: 'Semua', icon: 'bi-grid', count: this.totalItems },
                { key: 'WAJIB', label: 'Wajib', icon: 'bi-asterisk', count: this.wajibCount },
                {
                    key: 'BERKAS',
                    label: 'Dokumen/Berkas',
                    icon: 'bi-file-earmark',
                    count: this.allItems.filter((it) => isFileTipe(it.Tipe_Item)).length,
                },
                {
                    key: 'PARAGRAF',
                    label: 'Teks',
                    icon: 'bi-text-paragraph',
                    count: this.allItems.filter((it) => it.Tipe_Item === 'PARAGRAF').length,
                },
                {
                    key: 'TAUTAN',
                    label: 'Tautan',
                    icon: 'bi-link-45deg',
                    count: this.allItems.filter((it) => it.Tipe_Item === 'TAUTAN').length,
                },
                {
                    key: 'KUIS',
                    label: 'Kuis',
                    icon: 'bi-patch-question',
                    count: this.allItems.filter((it) => it.Tipe_Item === 'KUIS').length,
                },
            ];
        },
        filteredSections() {
            if (this.activeFilter === 'ALL') return this.sections;
            return this.sections.filter((sec) => this.filteredItemsInSection(sec.Id_SOP_Section).length > 0);
        },
    },
    watch: {
        editingId() {
            this.scrollToEditing();
        },
        adding() {
            this.scrollToEditing();
        },
        'editingItem.Tipe_Item'() {
            this.scrollToEditing();
        },
    },
    methods: {
        formatTanggalIndo(val) {
            if (!val || val === 'null' || val === 'undefined') return 'Draf (Belum Terbit)';
            try {
                const str = String(val).trim().replace(' ', 'T').split('.')[0];
                const d = new Date(str);
                if (isNaN(d.getTime())) return val;
                const bulan = [
                    'Januari',
                    'Februari',
                    'Maret',
                    'April',
                    'Mei',
                    'Juni',
                    'Juli',
                    'Agustus',
                    'September',
                    'Oktober',
                    'November',
                    'Desember',
                ];
                return `${d.getDate()} ${bulan[d.getMonth()]} ${d.getFullYear()}`;
            } catch (e) {
                return val;
            }
        },
        itemsInSection(sectionId) {
            return this.allItems.filter((x) => x.Id_SOP_Section === sectionId);
        },
        filteredItemsInSection(sectionId) {
            const list = this.itemsInSection(sectionId);
            if (this.activeFilter === 'ALL') return list;
            if (this.activeFilter === 'WAJIB') return list.filter((it) => it.wajib);
            if (this.activeFilter === 'BERKAS') return list.filter((it) => isFileTipe(it.Tipe_Item));
            return list.filter((it) => it.Tipe_Item === this.activeFilter);
        },
        normalize(src, editing) {
            const rawVer = src.Versi ?? src.Versi_Ke ?? src.Versi_String ?? src.Dokumen_Versi_Aktif ?? src.Versi_Item ?? null;
            let parsedVer = null;
            if (rawVer !== null && rawVer !== undefined && rawVer !== '') {
                const num = Number(rawVer);
                parsedVer = !isNaN(num) ? num : rawVer;
            }

            return {
                _id: src.Id_SOP_Paket_Item || '__new__',
                _editing: editing,
                Id_SOP_Section: src.Id_SOP_Section,
                Tipe_Item: src.Tipe_Item,
                Judul_Item: src.Judul_Item,
                Deskripsi_Item: src.Deskripsi_Item,
                Konten_Teks: src.Konten_Teks,
                Konten_Nama: src.Konten_Nama,
                URL_Tautan: src.URL_Tautan,
                Label_Tautan: src.Label_Tautan,
                Mode_Akses: src.Mode_Akses,
                Jenis_Quiz: src.Jenis_Quiz,
                Nilai_Minimum: src.Nilai_Minimum,
                Nomor_SOP_Resmi: src.Nomor_SOP_Resmi,
                /* DUA BENTUK MASUK KE SINI, dan keduanya harus diterima.
                   Baris dari server memakai 'Y'/'T'; formulir yang sedang
                   disunting memakai boolean, karena kotak centang Vue memang
                   menghasilkan boolean. Sebelumnya nilainya diteruskan mentah
                   lalu dibandingkan `=== 'Y'` di kartu pratinjau — jadi mencentang
                   "Sorot di detail" tak pernah mengubah apa pun di pratinjau,
                   padahal di sisi karyawan sorotannya muncul.
                   Baris `wajib` di bawah sudah menormalkan hal yang sama sejak
                   dulu; yang ini tertinggal — satu dari dua bendera kembar. */
                highlight: typeof src.Flag_Highlight === 'boolean' ? src.Flag_Highlight : src.Flag_Highlight === 'Y',
                Versi: parsedVer !== null ? parsedVer : (src.Versi !== undefined ? src.Versi : 1),
                wajib: typeof src.Flag_Wajib === 'boolean' ? src.Flag_Wajib : src.Flag_Wajib === 'Y',
            };
        },
        tipeLabel(t) {
            return TIPE_ITEM[t]?.label || t || 'Item';
        },
        itemIcon(t) {
            return TIPE_ITEM[t]?.icon || 'bi-file-earmark';
        },
        scrollToEditing() {
            this.$nextTick(() => {
                const el = this.$refs.editing;
                const node = Array.isArray(el) ? el[0] && el[0].$el : el && el.$el;
                const container = this.$refs.scrollContainer;
                if (node && container) {
                    const targetTop = node.offsetTop - container.offsetTop;
                    container.scrollTo({ top: Math.max(0, targetTop - 20), behavior: 'smooth' });
                }
            });
        },
        scrollToSection(secId) {
            this.activeTocSec = secId;
            const el = document.getElementById('sec-' + secId);
            const container = this.$refs.scrollContainer;
            if (el && container) {
                const targetTop = el.offsetTop - container.offsetTop;
                container.scrollTo({ top: Math.max(0, targetTop - 10), behavior: 'smooth' });
            }
            if (this.compact) this.mobileSheetOpen = false;
        },
        scrollToItem(itemId) {
            const el = document.getElementById('item-' + itemId);
            const container = this.$refs.scrollContainer;
            if (el && container) {
                const targetTop = el.offsetTop - container.offsetTop;
                container.scrollTo({ top: Math.max(0, targetTop - 10), behavior: 'smooth' });
            }
            if (this.compact) this.mobileSheetOpen = false;
        },
    },
};
</script>

<style scoped>
.live-preview {
    height: 100%;
    width: 100%;
    position: relative;
    display: flex;
    flex-direction: column;
}
.reader-shell {
    flex: 1;
    min-height: 0;
    position: relative;
    padding: 0.85rem;
}

/* Scroll progress top line */
.rd-scrollbar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    z-index: 70;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6, var(--hcis-pink-500));
}

.sop-workspace {
    display: flex;
    gap: 1.2rem;
    align-items: flex-start;
}

/* ===== RAIL (LEFT SIDEBAR / MOBILE BOTTOM SHEET) ===== */
.rd-side {
    width: 310px;
    flex-shrink: 0;
}
.rd-rail {
    position: sticky;
    top: 10px !important;
    max-height: calc(100vh - 20px) !important;
    padding: 1.15rem 1.05rem;
    gap: 0.95rem;
    overflow-y: auto;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 24px;
    box-shadow: 0 8px 30px -10px rgba(15, 23, 42, 0.08);
}
.rd-rail::-webkit-scrollbar {
    width: 5px;
}
.rd-rail::-webkit-scrollbar-thumb {
    background: rgba(15, 23, 42, 0.16);
    border-radius: 6px;
}

/* In compact / live preview mode: hide side panel & drawers completely for a clean layout */
.is-compact .rd-side {
    display: none !important;
}
.is-compact .rd-side .rd-rail {
    position: relative !important;
    top: auto !important;
    width: 100%;
    max-width: 100%;
    margin: 0;
    max-height: 85% !important;
    overflow-y: auto;
    padding: 0.7rem 1.15rem 1.4rem;
    border-radius: 20px 20px 0 0;
    border: 1px solid var(--border, #dbe1ea);
    border-bottom: 0;
    box-shadow: 0 -14px 40px rgba(15, 23, 42, 0.35);
    animation: sheetUp 0.32s cubic-bezier(0.22, 1, 0.36, 1);
    background: var(--hcis-white);
}
@keyframes sheetUp {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}
@keyframes sideFade {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.rd-sheet-head {
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 0.2rem 0 0.65rem;
    margin-bottom: 0.25rem;
}
.rd-sheet-grip {
    display: block;
    width: 38px;
    height: 4px;
    border-radius: 999px;
    background: var(--hcis-slate-300);
    margin: 0 auto;
}
.rd-sheet-close {
    position: absolute;
    right: 0.25rem;
    top: 50%;
    transform: translateY(-50%);
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    background: rgba(148, 163, 184, 0.15);
    border-radius: 50%;
    color: var(--hcis-slate-500);
    font-size: 0.85rem;
    cursor: pointer;
}

.rail-seg {
    position: sticky;
    top: 0;
    z-index: 3;
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: 1fr;
    padding: 4px;
    background: var(--surface-soft, var(--hcis-slate-100));
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 14px;
    flex-shrink: 0;
}
.rail-seg-thumb {
    position: absolute;
    top: 4px;
    bottom: 4px;
    left: 4px;
    width: calc((100% - 8px) / var(--seg-n, 3));
    transform: translateX(calc(var(--seg-i, 0) * 100%));
    background: var(--hcis-white);
    border-radius: 10px;
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.07),
        0 4px 12px -6px rgba(15, 23, 42, 0.28);
    transition: transform 0.34s cubic-bezier(0.22, 1, 0.36, 1);
}
.rail-seg-btn {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    padding: 0.42rem 0.2rem;
    border: 0;
    background: transparent;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--hcis-slate-500);
    cursor: pointer;
    transition: color 0.2s ease;
}
.rail-seg-btn.on {
    color: var(--hcis-indigo-600);
}

.rail-panel {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.rail-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    padding: 0.95rem 1rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.rail-cap {
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--hcis-slate-400);
}
.rail-cap-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}
.rail-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.68rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
}
.rail-pill.soft {
    color: var(--hcis-slate-600);
    background: var(--surface-soft, var(--hcis-slate-100));
    border: 1px solid var(--border, #dbe1ea);
}
.rail-pill.ok {
    color: var(--hcis-emerald-700);
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.28);
}

.rail-gauge {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.45rem;
}
.rail-gauge-sub {
    font-size: 0.78rem;
    color: var(--hcis-slate-600);
    font-weight: 600;
}
.rail-gauge-sub .tnum {
    font-weight: 800;
    color: var(--hcis-slate-800);
}

.rail-stat {
    text-align: center;
}
.rail-stat-big {
    font-size: 2.6rem;
    font-weight: 800;
    line-height: 1;
    color: var(--hcis-indigo-600);
    letter-spacing: -0.02em;
    margin-top: 0.3rem;
}
.rail-stat-big span {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--hcis-indigo-300);
    margin-left: 2px;
}
.rail-split {
    display: flex;
    height: 8px;
    border-radius: 999px;
    overflow: hidden;
    background: var(--surface-soft, var(--hcis-slate-100));
    margin-top: 0.85rem;
}
.rail-split .s-no {
    background: var(--hcis-slate-200);
    width: 100%;
}
.rail-legend {
    list-style: none;
    margin: 0.7rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.rail-legend li {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.76rem;
    color: var(--hcis-slate-500);
}
.rail-legend b {
    margin-left: auto;
    color: var(--hcis-slate-800);
    font-weight: 800;
}
.rail-legend .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.rail-legend .dot.no {
    background: var(--hcis-slate-300);
}

.rail-kv {
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 16px;
    overflow: hidden;
}
.rail-kv > div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.6rem 0.85rem;
    font-size: 0.78rem;
}
.rail-kv > div + div {
    border-top: 1px solid var(--border-soft, #e7ecf3);
}
.rail-kv span {
    color: var(--hcis-slate-500);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.rail-kv b {
    color: var(--hcis-slate-800);
    font-weight: 800;
}

.legal-hero {
    display: flex;
    gap: 0.8rem;
    align-items: flex-start;
    padding: 0.95rem 1rem;
    border-radius: 16px;
    color: var(--hcis-emerald-700);
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.16), rgba(34, 197, 94, 0.08));
    border: 1px solid rgba(16, 185, 129, 0.28);
}
.legal-hero-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.72);
    font-size: 1.25rem;
}
.legal-eyebrow {
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    opacity: 0.78;
}
.legal-title {
    margin-top: 0.12rem;
    color: var(--hcis-slate-900);
    font-size: 0.94rem;
    font-weight: 850;
    line-height: 1.28;
}
.legal-sub {
    margin-top: 0.22rem;
    color: var(--hcis-slate-600);
    font-size: 0.75rem;
    line-height: 1.45;
}
.legal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}
.legal-metric {
    padding: 0.65rem 0.75rem;
    border-radius: 14px;
    background: var(--hcis-white);
    border: 1px solid var(--border, #dbe1ea);
}
.legal-metric span {
    display: block;
    color: var(--hcis-slate-400);
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
}
.legal-metric strong {
    display: block;
    margin-top: 0.18rem;
    color: var(--hcis-slate-800);
    font-size: 0.82rem;
    font-weight: 850;
}
.legal-kv {
    display: grid;
    grid-template-columns: minmax(92px, auto) 1fr;
    gap: 0.42rem 0.7rem;
    margin: 0.55rem 0 0;
}
.legal-kv dt {
    color: var(--hcis-slate-400);
    font-size: 0.72rem;
    font-weight: 700;
}
.legal-kv dd {
    margin: 0;
    color: var(--hcis-slate-800);
    font-size: 0.74rem;
    font-weight: 800;
    text-align: right;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 170px;
    margin-left: auto;
}
.legal-item-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-top: 0.65rem;
}
.legal-item-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 0;
    border-top: 1px solid var(--border-soft, #e7ecf3);
    min-width: 0;
}
.legal-item-row:first-child {
    border-top: 0;
}
.legal-item-status.idle {
    color: var(--hcis-slate-500);
    background: var(--hcis-slate-100);
    border-radius: 999px;
    padding: 0.18rem 0.5rem;
    font-size: 0.62rem;
    font-weight: 850;
    margin-left: auto;
    flex-shrink: 0;
}

.rd-toc {
    border-top: 1px solid var(--border-soft, #eef2f7);
    padding-top: 0.8rem;
}
.rd-toc-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--hcis-slate-400);
    padding: 0 0.3rem 0.5rem;
}
.rd-toc-sec-group {
    border-radius: 14px;
    margin-bottom: 0.35rem;
}
.rd-toc-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    padding: 0.6rem 0.75rem;
    border-radius: 12px;
    text-align: left;
    transition: all 0.2s ease;
}
.rd-toc-item:hover {
    background: var(--hcis-slate-50);
    border-color: var(--border, #dbe1ea);
}
.rd-toc-item.active {
    background: rgba(99, 102, 241, 0.08) !important;
    border-color: rgba(99, 102, 241, 0.25) !important;
    border-left: 3px solid var(--hcis-indigo-500) !important;
}
.rd-toc-no {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 0.75rem;
    font-weight: 800;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    flex-shrink: 0;
}
.rd-toc-item.active .rd-toc-no {
    background: var(--hcis-indigo-500);
    color: var(--hcis-white);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.35);
}
.rd-toc-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.rd-toc-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--hcis-slate-700);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-toc-item.active .rd-toc-name {
    color: var(--hcis-indigo-600);
}
.rd-toc-prog {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--hcis-slate-400);
    flex-shrink: 0;
}
.rd-toc-bar {
    display: block;
    height: 4px;
    border-radius: 999px;
    background: var(--hcis-slate-100);
    overflow: hidden;
}
.rd-toc-bar i {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6);
}

.rd-toc-sublist {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.4rem 0.2rem 0.35rem 0.65rem;
    border-left: 2px dashed rgba(99, 102, 241, 0.25);
    margin-left: 1.15rem;
    margin-top: 0.25rem;
}
.rd-toc-subitem {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    border: 0;
    background: transparent;
    border-radius: 8px;
    padding: 0.38rem 0.6rem;
    font-size: 0.78rem;
    color: var(--hcis-slate-600);
    text-align: left;
    cursor: pointer;
    min-width: 0;
}
.rd-toc-subitem:hover {
    background: rgba(99, 102, 241, 0.08);
    color: var(--hcis-indigo-600);
}
.rd-toc-subitem.current {
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
    font-weight: 700;
}
.rd-toc-subico {
    font-size: 0.85rem;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}
.rd-toc-subttl {
    flex: 1;
    min-width: 0;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-toc-subbadge {
    font-size: 0.58rem;
    font-weight: 800;
    color: var(--hcis-slate-500);
    background: var(--surface-soft, var(--hcis-slate-100));
    padding: 0.12rem 0.4rem;
    border-radius: 4px;
    flex-shrink: 0;
}

/* ===== MAIN READER CONTENT ===== */
.rd-main {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    flex-grow: 1;
}
.rd-hero {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    min-height: 230px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 16px 36px -16px rgba(15, 23, 42, 0.35);
}
.rd-hero-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.rd-kenburns {
    animation: kenburns 24s ease-in-out infinite alternate;
}
@keyframes kenburns {
    0% {
        transform: scale(1.04);
    }
    100% {
        transform: scale(1.15) translate(-1%, -1%);
    }
}
.rd-hero-mesh {
    position: absolute;
    inset: 0;
    mix-blend-mode: overlay;
    pointer-events: none;
    background:
        radial-gradient(450px 240px at 88% 8%, rgba(139, 92, 246, 0.45), transparent 60%),
        radial-gradient(350px 240px at 8% 92%, rgba(99, 102, 241, 0.4), transparent 60%);
}
.rd-hero-shade {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        180deg,
        rgba(15, 23, 42, 0.35) 0%,
        rgba(15, 23, 42, 0.45) 45%,
        rgba(15, 23, 42, 0.88) 100%
    );
}

.rd-hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--hcis-white);
    font-size: 0.78rem;
    font-weight: 700;
    text-decoration: none;
    padding: 0.32rem 0.75rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    flex-shrink: 0;
}
.rd-hero-bc {
    font-size: 0.76rem;
    color: rgba(255, 255, 255, 0.95);
    background: rgba(15, 23, 42, 0.35);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 999px;
    padding: 0.28rem 0.75rem;
    max-width: 220px;
}
.rd-hero-body {
    position: relative;
    width: 100%;
    padding: 1.35rem 1.35rem 0.95rem;
    z-index: 2;
}
.rd-hero-eyebrow {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.35rem;
    max-width: 100%;
}
.rd-kode {
    font-size: 0.65rem;
    font-weight: 800;
    color: #fff;
    background: rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 7px;
    padding: 0.16rem 0.5rem;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-kat {
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-title {
    color: #fff;
    font-weight: 800;
    font-size: 1.45rem;
    margin: 0 0 0.2rem;
    line-height: 1.25;
    text-shadow: 0 2px 14px rgba(0, 0, 0, 0.45);
    letter-spacing: -0.01em;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
}
.rd-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.6rem;
    max-width: 100%;
}
.rd-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: #fff;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 999px;
    padding: 0.24rem 0.65rem;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-chip-seal {
    background: rgba(16, 185, 129, 0.7);
    border-color: rgba(255, 255, 255, 0.4);
}

.rd-spec {
    position: relative;
    z-index: 2;
    display: flex;
    flex-wrap: wrap;
    background: rgba(15, 23, 42, 0.45) !important;
    backdrop-filter: blur(14px) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.18) !important;
}
.rd-spec-cell {
    flex: 1;
    min-width: 100px;
    padding: 0.55rem 0.8rem;
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow: hidden;
}
.rd-spec-cell + .rd-spec-cell {
    border-left: 1px solid rgba(255, 255, 255, 0.15);
}
.rd-spec-lbl {
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.8);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-spec-val {
    font-size: 0.82rem;
    font-weight: 800;
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Banners */
.rd-assign {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(99, 102, 241, 0.02));
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 14px;
    padding: 0.8rem 1rem;
    box-shadow: 0 4px 14px -4px rgba(99, 102, 241, 0.1);
}
.rd-assign-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}
.rd-assign-icon {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-500);
    font-size: 1.2rem;
    flex-shrink: 0;
}
.rd-assign-title {
    font-weight: 800;
    color: var(--hcis-slate-800);
    font-size: 0.88rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-assign-sub {
    font-size: 0.74rem;
    color: var(--hcis-slate-500);
    margin-top: 1px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rd-gate {
    padding: 0.95rem 1.05rem;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.09), rgba(245, 158, 11, 0.03));
    border: 1px solid rgba(245, 158, 11, 0.28);
}
.rd-gate-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--hcis-amber-700);
    background: rgba(245, 158, 11, 0.16);
    border-radius: 999px;
    padding: 0.22rem 0.6rem;
}
.rd-gate-row {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-top: 0.75rem;
}
.rd-gate-ico {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 1.3rem;
    color: var(--hcis-amber-500);
    background: rgba(245, 158, 11, 0.14);
    flex-shrink: 0;
}
.rd-gate h5 {
    font-weight: 800;
    color: var(--hcis-slate-800);
    margin: 0;
    font-size: 0.96rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-gate-note {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    margin: 0.5rem 0 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rd-about {
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
}
.rd-about-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    border: 0;
    background: transparent;
    cursor: pointer;
    padding: 0.75rem 0.95rem;
    font-weight: 800;
    color: var(--hcis-slate-700);
    font-size: 0.86rem;
}
.rd-about-body {
    padding: 0 0.95rem 0.95rem;
}
.rd-desc {
    font-size: 0.88rem;
    color: var(--hcis-slate-600);
    line-height: 1.6;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rd-scope {
    background: var(--surface-muted, var(--hcis-slate-50));
    border: 1px solid var(--border-soft, #eef2f7);
    border-radius: 12px;
    padding: 0.75rem 0.85rem;
    margin-top: 0.7rem;
}
.rd-scope-lbl {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-indigo-500);
    margin-bottom: 0.3rem;
}
.rd-scope p {
    margin: 0;
    font-size: 0.84rem;
    color: var(--hcis-slate-600);
    line-height: 1.55;
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rd-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.rd-fchip {
    border: 1px solid var(--hcis-slate-300);
    background: var(--hcis-white);
    color: var(--hcis-slate-600);
    font-weight: 700;
    font-size: 0.75rem;
    padding: 0.34rem 0.75rem;
    border-radius: 999px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.rd-fchip.on {
    background: var(--hcis-indigo-500) !important;
    color: var(--hcis-white) !important;
    border-color: var(--hcis-indigo-500) !important;
    box-shadow: 0 4px 14px -2px rgba(99, 102, 241, 0.45) !important;
}
.rd-fcount {
    font-size: 0.65rem;
    font-weight: 800;
    padding: 0.08rem 0.42rem;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
}
.rd-fchip.on .rd-fcount {
    background: rgba(255, 255, 255, 0.25) !important;
    color: var(--hcis-white) !important;
}

/* ===== READING JOURNEY SPINE LINE ===== */
.rd-flow {
    position: relative;
    margin-top: 0.3rem;
}
.rd-flow::before {
    content: '';
    position: absolute;
    left: 18px;
    top: 20px;
    bottom: 24px;
    width: 3px;
    background: linear-gradient(180deg, var(--hcis-indigo-500) 0%, var(--hcis-slate-300) 100%);
    border-radius: 3px;
    z-index: 0;
}

.flow-sec {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding-left: 48px;
    margin: 1.1rem 0 0.85rem;
}
.flow-sec:first-child {
    margin-top: 0.4rem;
}
.flow-sec-body {
    flex: 1;
    min-width: 0;
}

.flow-sec-card {
    width: 100%;
    background: var(--hcis-white);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 14px;
    padding: 0.75rem 0.95rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    min-width: 0;
}
.flow-sec-card:hover {
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 4px 14px -4px rgba(99, 102, 241, 0.14);
}
.flow-sec-chevron {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--surface-soft, var(--hcis-slate-100));
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-500);
    font-size: 0.8rem;
    flex-shrink: 0;
}
.flow-sec-chevron.open {
    transform: rotate(180deg);
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-600);
}

.flow-node.sec {
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--hcis-indigo-500), #8b5cf6);
    color: #fff;
    font-weight: 800;
    font-size: 0.9rem;
    box-shadow: 0 6px 16px -5px rgba(99, 102, 241, 0.65);
    position: absolute;
    z-index: 1;
    display: grid;
    place-items: center;
}
.rd-sec-name {
    font-weight: 800;
    color: var(--hcis-slate-900);
    font-size: 1.02rem;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-sec-desc {
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
    margin-top: 0.1rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-sec-prog {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 999px;
    padding: 0.18rem 0.55rem;
    flex-shrink: 0;
}

/* Terminal End Node */
.rd-flow-terminal {
    position: relative;
    padding-left: 48px;
    display: flex;
    align-items: center;
}
.rd-terminal-node {
    position: absolute;
    left: 4px;
    top: 50%;
    transform: translateY(-50%);
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--hcis-indigo-500), var(--hcis-indigo-600));
    color: var(--hcis-white);
    display: grid;
    place-items: center;
    font-size: 0.85rem;
    z-index: 2;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.45);
}
.rd-terminal-ripple {
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid rgba(99, 102, 241, 0.35);
    animation: rdRipple 2s cubic-bezier(0, 0.2, 0.8, 1) infinite;
}
@keyframes rdRipple {
    0% {
        transform: scale(0.9);
        opacity: 0.8;
    }
    100% {
        transform: scale(1.45);
        opacity: 0;
    }
}
.rd-terminal-card {
    background: var(--hcis-white);
    border: 1px solid var(--border, #dbe1ea);
    border-radius: 14px;
    padding: 0.7rem 1rem;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
}
.rd-terminal-title {
    font-weight: 800;
    font-size: 0.88rem;
    color: var(--hcis-slate-900);
    display: flex;
    align-items: center;
}

/* ===== MOBILE STICKY BOTTOM BAR (rd-mbar 100% matched to SopDetail.vue) ===== */
.rd-mbar {
    position: sticky;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 55;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-top: 1px solid var(--border, #dbe1ea);
    box-shadow: 0 -8px 28px -12px rgba(15, 23, 42, 0.25);
    margin: 1rem -0.85rem -0.85rem;
}
.rd-mbar-prog {
    height: 3px;
    background: var(--surface-soft, var(--hcis-slate-100));
}
.rd-mbar-prog span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, var(--hcis-indigo-500), #8b5cf6);
    transition: width 0.4s ease;
}
.rd-mbar-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.8rem;
}
.rd-mbar-nav {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 42px;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.25rem 0.55rem 0.25rem 0.3rem;
    border: 1px solid var(--border, #dbe1ea);
    background: var(--hcis-white);
    border-radius: 12px;
    cursor: pointer;
    text-align: left;
    transition: border-color 0.2s ease;
}
.rd-mbar-pct {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 28px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--hcis-indigo-600);
    background: rgba(99, 102, 241, 0.1);
}
.rd-mbar-ctx {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
    flex-grow: 1;
}
.rd-mbar-lbl {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--hcis-slate-800);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rd-mbar-sub {
    font-size: 0.64rem;
    color: var(--hcis-slate-400);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rd-mbar-caret {
    flex-shrink: 0;
    font-size: 0.78rem;
    color: var(--hcis-slate-400);
}
.rd-mbar-cta {
    flex-shrink: 0;
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0 0.85rem;
    border: 0;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, var(--hcis-indigo-500), #8b5cf6);
    box-shadow: 0 6px 18px -6px rgba(99, 102, 241, 0.75);
    cursor: pointer;
}
</style>

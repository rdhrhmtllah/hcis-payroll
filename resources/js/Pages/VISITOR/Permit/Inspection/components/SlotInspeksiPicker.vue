<template>
    <div class="slot-inspeksi" :class="{ 'is-disabled': disabled, 'is-hanya-baca': hanyaBaca }">
        <!-- ══ LOADING STATE ══ -->
        <div v-if="loading" class="slot-inspeksi__loading" aria-busy="true">
            <div class="slot-inspeksi__loading-header">
                <HcisSkeleton width="8rem" height="1.25rem" rounded="sm" />
                <HcisSkeleton width="12rem" height="0.875rem" rounded="sm" class="mt-1" />
            </div>
            <div class="slot-inspeksi__loading-grid">
                <HcisSkeleton v-for="n in 5" :key="`skel-date-${n}`" height="74px" rounded="md" />
            </div>
            <div class="slot-inspeksi__loading-slots mt-3">
                <HcisSkeleton v-for="n in 6" :key="`skel-slot-${n}`" height="58px" rounded="md" />
            </div>
        </div>

        <!-- ══ EMPTY STATE JIKA BELUM ADA SLOT ══ -->
        <div v-else-if="!hari.length" class="slot-inspeksi__kosong">
            <div class="slot-inspeksi__kosong-icon">
                <HcisIcon name="calendar-x" size="lg" />
            </div>
            <div class="slot-inspeksi__kosong-text">
                <strong class="slot-inspeksi__kosong-title">Belum ada slot inspeksi yang dibuka SHE</strong>
                <p class="slot-inspeksi__kosong-desc mb-0">
                    Silakan hubungi tim SHE atau periksa kembali secara berkala untuk jadwal slot pemeriksaan kendaraan terbaru.
                </p>
            </div>
        </div>

        <!-- ══ LIVE SLOT PICKER ══ -->
        <template v-else>
            <!-- 1. Interactive Date Selection Carousel Card -->
            <div class="slot-inspeksi__date-deck">
                <div class="slot-inspeksi__section-header">
                    <div class="slot-inspeksi__header-left">
                        <div class="slot-inspeksi__icon-badge">
                            <HcisIcon name="calendar2-week-fill" size="xs" />
                        </div>
                        <div class="slot-inspeksi__header-meta">
                            <span class="slot-inspeksi__section-title">Pilih Tanggal Inspeksi</span>
                            <span class="slot-inspeksi__section-subtitle">Tersedia {{ hari.length }} hari operasional SHE</span>
                        </div>
                    </div>
                    <div class="slot-inspeksi__header-right">
                        <span class="slot-inspeksi__section-badge font-tabular">
                            <span class="slot-inspeksi__badge-dot" />
                            {{ hari.length }} Hari
                        </span>
                        <div class="slot-inspeksi__nav-controls" role="group" aria-label="Navigasi geser tanggal">
                            <button
                                type="button"
                                class="slot-inspeksi__nav-btn"
                                :class="{ 'is-disabled': !canScrollLeft }"
                                :disabled="!canScrollLeft"
                                title="Geser tanggal ke kiri"
                                aria-label="Geser tanggal ke kiri"
                                @click="scrollDays('left')"
                            >
                                <HcisIcon name="chevron-left" size="xs" />
                            </button>
                            <button
                                type="button"
                                class="slot-inspeksi__nav-btn"
                                :class="{ 'is-disabled': !canScrollRight }"
                                :disabled="!canScrollRight"
                                title="Geser tanggal ke kanan"
                                aria-label="Geser tanggal ke kanan"
                                @click="scrollDays('right')"
                            >
                                <HcisIcon name="chevron-right" size="xs" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="slot-inspeksi__hari-wrap">
                    <div
                        ref="hariScroll"
                        class="slot-inspeksi__hari"
                        role="tablist"
                        aria-label="Tanggal inspeksi"
                        @scroll.passive="checkScrollBounds"
                    >
                        <button
                            v-for="h in hari"
                            :key="h.tanggal"
                            type="button"
                            role="tab"
                            class="slot-inspeksi__tanggal"
                            :class="{
                                'is-aktif': h.tanggal === tanggalAktif,
                                'is-terpilih': modelValue && modelValue.mulai && modelValue.mulai.startsWith(h.tanggal)
                            }"
                            :aria-selected="h.tanggal === tanggalAktif"
                            :disabled="disabled"
                            @click="selectDate(h.tanggal)"
                        >
                            <!-- Selected Badge Indicator -->
                            <span
                                v-if="modelValue && modelValue.mulai && modelValue.mulai.startsWith(h.tanggal)"
                                class="slot-inspeksi__tanggal-terpilih-dot"
                                title="Slot dipilih pada tanggal ini"
                            >
                                <HcisIcon name="check-lg" size="2xs" />
                            </span>

                            <!-- Day Name -->
                            <span class="slot-inspeksi__nama-hari">{{ h.namaHari }}</span>

                            <!-- Date Number & Month -->
                            <strong class="slot-inspeksi__angka font-tabular">{{ h.tanggalPendek }}</strong>

                            <!-- Slot Count Pill -->
                            <span class="slot-inspeksi__jumlah font-tabular">
                                <span class="slot-inspeksi__dot-indicator" />
                                {{ h.jumlah }} slot
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 2. Shift Groups & Pos Container for Active Date -->
            <div class="slot-inspeksi__shift-container">
                <div
                    v-for="shiftGrup in shiftAktif"
                    :key="shiftGrup.nama"
                    class="slot-inspeksi__shift-section"
                    :class="{
                        'is-collapsed': !isShiftExpanded(shiftGrup.nama),
                        'is-expanded': isShiftExpanded(shiftGrup.nama),
                        'has-selected': hasSelectedSlot(shiftGrup)
                    }"
                >
                    <!-- Shift Section Header (Interactive Accordion Trigger) -->
                    <button
                        type="button"
                        class="slot-inspeksi__shift-header"
                        :class="{ 'is-active': isShiftExpanded(shiftGrup.nama) }"
                        :aria-expanded="isShiftExpanded(shiftGrup.nama)"
                        @click="toggleShift(shiftGrup.nama)"
                    >
                        <div class="slot-inspeksi__shift-header-main">
                            <div class="slot-inspeksi__shift-icon-box" :class="`is-${shiftSentiment(shiftGrup.nama)}`">
                                <HcisIcon :name="shiftIcon(shiftGrup.nama)" size="xs" />
                            </div>
                            <div class="slot-inspeksi__shift-header-text">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h3 class="slot-inspeksi__shift-title">{{ shiftGrup.nama }}</h3>
                                    <span class="slot-inspeksi__shift-pill">
                                        {{ shiftGrup.posList.length }} Pos
                                    </span>
                                    <span
                                        v-if="hasSelectedSlot(shiftGrup)"
                                        class="slot-inspeksi__selected-shift-tag"
                                    >
                                        <HcisIcon name="check-circle-fill" size="2xs" />
                                        <span>Dipilih</span>
                                    </span>
                                </div>
                                <span class="slot-inspeksi__shift-subtitle font-tabular">
                                    {{ shiftGrup.totalTersedia }} dari {{ shiftGrup.totalSlot }} slot waktu tersedia
                                </span>
                            </div>
                        </div>

                        <div class="slot-inspeksi__shift-header-right">
                            <HcisBadge
                                :label="`${shiftGrup.totalTersedia} Tersedia`"
                                size="xs"
                                :variant="shiftGrup.totalTersedia > 0 ? 'success' : 'neutral'"
                                appearance="subtle"
                                class="d-none d-sm-inline-flex"
                            />
                            <span
                                class="slot-inspeksi__shift-chevron"
                                :class="{ 'is-rotated': isShiftExpanded(shiftGrup.nama) }"
                                aria-hidden="true"
                            >
                                <HcisIcon name="chevron-down" size="xs" />
                            </span>
                        </div>
                    </button>

                    <!-- Pos Cards within this Shift (Smooth Accordion Body) -->
                    <div
                        class="slot-inspeksi__shift-body-wrap"
                        :class="{ 'is-expanded': isShiftExpanded(shiftGrup.nama) }"
                    >
                        <div class="slot-inspeksi__shift-body-inner">
                            <div class="slot-inspeksi__pos-container">
                        <div
                            v-for="grup in shiftGrup.posList"
                            :key="grup.kunci"
                            class="slot-inspeksi__pos-card"
                        >
                            <!-- Pos Header -->
                            <div class="slot-inspeksi__pos-header">
                                <div class="slot-inspeksi__pos-info">
                                    <div class="slot-inspeksi__pos-icon">
                                        <HcisIcon name="geo-alt-fill" size="xs" />
                                    </div>
                                    <div>
                                        <strong class="slot-inspeksi__pos-nama">{{ grup.pos }}</strong>
                                        <span class="slot-inspeksi__pos-subtext">
                                            {{ grup.lokasi || 'Pos Pemeriksaan Fisik SHE & Verifikasi Kendaraan' }}
                                        </span>
                                    </div>
                                </div>
                                <span class="slot-inspeksi__pos-slot-count font-tabular">
                                    {{ grup.bisaDipesan }} dari {{ grup.slot.length }} Waktu
                                </span>
                            </div>

                            <!-- Time Slots Grid -->
                            <div class="slot-inspeksi__jam">
                                <button
                                    v-for="slot in grup.slot"
                                    :key="slot.id_pos + '|' + slot.mulai"
                                    type="button"
                                    class="slot-inspeksi__slot"
                                    :class="{
                                        'is-terpilih': dipilih(slot),
                                        'is-sisa-sedikit': keadaan(slot) === 'tersedia' && slot.sisa <= 1 && !dipilih(slot),
                                        'is-penuh': keadaan(slot) === 'penuh',
                                        'is-lewat': keadaan(slot) === 'lewat'
                                    }"
                                    :aria-pressed="dipilih(slot)"
                                    :title="keadaan(slot) === 'lewat' ? 'Jam ini sudah lewat' : (keadaan(slot) === 'penuh' ? 'Kuota slot ini sudah habis' : '')"
                                    :disabled="disabled || hanyaBaca || keadaan(slot) !== 'tersedia'"
                                    @click="pilih(slot)"
                                >
                                    <!-- Top-right corner floating checkmark badge when selected -->
                                    <span v-if="dipilih(slot)" class="slot-inspeksi__slot-check-badge" title="Slot ini sedang dipilih">
                                        <HcisIcon name="check-lg" size="2xs" />
                                    </span>

                                    <!-- Top Row: Time & Clock -->
                                    <div class="slot-inspeksi__slot-top">
                                        <div class="slot-inspeksi__slot-time-box">
                                            <div class="slot-inspeksi__slot-icon-wrap">
                                                <HcisIcon
                                                    :name="dipilih(slot) ? 'check-circle-fill' : 'clock-fill'"
                                                    size="2xs"
                                                />
                                            </div>
                                            <span class="slot-inspeksi__waktu font-tabular">
                                                {{ jam(slot.mulai) }} – {{ jam(slot.selesai) }}
                                            </span>
                                        </div>
                                        <span class="slot-inspeksi__wib-badge">WIB</span>
                                    </div>

                                    <!-- Bottom Row: Capacity & Pill -->
                                    <div class="slot-inspeksi__slot-bottom">
                                        <span class="slot-inspeksi__sisa font-tabular">
                                            <template v-if="keadaan(slot) === 'lewat'">
                                                <span class="slot-inspeksi__capacity-dot is-lewat" />
                                                Sudah lewat
                                            </template>
                                            <template v-else-if="keadaan(slot) === 'penuh'">
                                                <span class="slot-inspeksi__capacity-dot is-danger" />
                                                Penuh ({{ slot.terisi ?? slot.kuota }}/{{ slot.kuota }})
                                            </template>
                                            <template v-else>
                                                <span class="slot-inspeksi__capacity-dot" :class="slot.sisa <= 1 ? 'is-warning' : 'is-success'" />
                                                {{ slot.sisa }} slot tersedia
                                            </template>
                                        </span>
                                        <span v-if="dipilih(slot)" class="slot-inspeksi__terpilih-pill">
                                            Terpilih
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
        </template>
    </div>
</template>

<script>
import { HcisBadge, HcisIcon, HcisSkeleton } from '@/components/ui';

const NAMA_HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const NAMA_HARI_PANJANG = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const NAMA_BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const NAMA_BULAN_PANJANG = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

/**
 * Pemilih slot inspeksi kendaraan (DDL-2026-123). Slot datang dari server
 * dalam bentuk { id_pos, pos, lokasi, shift, mulai 'Y-m-d H:i', selesai, sisa };
 * komponen ini mengelompokkannya per tanggal -> per shift -> per pos untuk UX yang rapi.
 */
export default {
    name: 'SlotInspeksiPicker',
    components: { HcisBadge, HcisIcon, HcisSkeleton },
    props: {
        slots: { type: Array, default: () => [] },
        modelValue: { type: Object, default: null },
        loading: { type: Boolean, default: false },
        disabled: { type: Boolean, default: false },
        /**
         * Peta slot (Agenda Inspeksi SHE): slotnya tidak bisa dipesan dari sini,
         * tetapi tanggalnya HARUS tetap bisa digeser — memakai `disabled` untuk
         * itu ikut mematikan strip tanggalnya, sehingga peta hanya pernah
         * menampilkan satu hari dan terbaca seperti layar yang rusak.
         */
        hanyaBaca: { type: Boolean, default: false },
        /**
         * Kendali accordion: default tertutup (false) agar tampilan ringkas dan compact.
         * Bila true (misal peta agenda SHE), seluruh shift terbuka sejak awal.
         */
        defaultOpen: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            tanggalAktif: null,
            expandedShifts: [],
            canScrollLeft: false,
            canScrollRight: true,
        };
    },
    computed: {
        hari() {
            const peta = new Map();
            for (const slot of this.slots) {
                const tanggal = String(slot.mulai).slice(0, 10);
                if (!peta.has(tanggal)) peta.set(tanggal, []);
                peta.get(tanggal).push(slot);
            }
            return [...peta.entries()]
                .sort(([a], [b]) => a.localeCompare(b))
                .map(([tanggal, isi]) => {
                    const [y, m, d] = tanggal.split('-').map(Number);
                    const tgl = new Date(y, m - 1, d);
                    return {
                        tanggal,
                        namaHari: NAMA_HARI[tgl.getDay()],
                        namaHariPanjang: NAMA_HARI_PANJANG[tgl.getDay()],
                        tanggalPendek: `${d} ${NAMA_BULAN[m - 1]}`,
                        jumlah: isi.length,
                        isi,
                    };
                });
        },
        /**
         * Backward-compatible: daftar pos aktif per kombinasi pos + shift.
         */
        posAktif() {
            const h = this.hari.find((x) => x.tanggal === this.tanggalAktif);
            if (!h) return [];
            const peta = new Map();
            for (const slot of h.isi) {
                const kunci = `${slot.id_pos}|${slot.shift || ''}`;
                if (!peta.has(kunci)) peta.set(kunci, []);
                peta.get(kunci).push(slot);
            }
            return [...peta.entries()]
                .map(([kunci, slot]) => ({
                    kunci,
                    idPos: slot[0].id_pos,
                    pos: slot[0].pos,
                    lokasi: slot[0].lokasi || '',
                    shift: slot[0].shift || '',
                    bisaDipesan: slot.filter((s) => this.keadaan(s) === 'tersedia').length,
                    slot: slot.sort((a, b) => a.mulai.localeCompare(b.mulai)),
                }))
                .sort((a, b) => a.slot[0].mulai.localeCompare(b.slot[0].mulai));
        },
        /**
         * Pengelompokan hierarkis per SHIFT -> POS.
         * Memberikan struktur visual yang rapi per shift operasional.
         */
        shiftAktif() {
            const h = this.hari.find((x) => x.tanggal === this.tanggalAktif);
            if (!h) return [];

            const petaShift = new Map();
            for (const slot of h.isi) {
                const namaShift = slot.shift || 'Shift Terjadwal';
                if (!petaShift.has(namaShift)) {
                    petaShift.set(namaShift, {
                        nama: namaShift,
                        idShift: slot.id_shift ?? null,
                        posMap: new Map(),
                        slotList: [],
                    });
                }
                const shiftEntry = petaShift.get(namaShift);
                shiftEntry.slotList.push(slot);

                const idPos = slot.id_pos;
                if (!shiftEntry.posMap.has(idPos)) {
                    shiftEntry.posMap.set(idPos, []);
                }
                shiftEntry.posMap.get(idPos).push(slot);
            }

            return [...petaShift.values()]
                .map((sh) => {
                    const posList = [...sh.posMap.entries()].map(([idPos, slotArr]) => {
                        const sortedSlot = slotArr.slice().sort((a, b) => a.mulai.localeCompare(b.mulai));
                        return {
                            kunci: `${idPos}|${sh.nama}`,
                            idPos: slotArr[0].id_pos,
                            pos: slotArr[0].pos,
                            lokasi: slotArr[0].lokasi || '',
                            shift: sh.nama,
                            bisaDipesan: slotArr.filter((s) => this.keadaan(s) === 'tersedia').length,
                            totalSlot: slotArr.length,
                            slot: sortedSlot,
                        };
                    }).sort((a, b) => a.slot[0].mulai.localeCompare(b.slot[0].mulai));

                    const totalTersedia = sh.slotList.filter((s) => this.keadaan(s) === 'tersedia').length;
                    const earliestSlot = sh.slotList.slice().sort((a, b) => a.mulai.localeCompare(b.mulai))[0];

                    const rankMatch = String(sh.nama).match(/\d+/);
                    const rank = sh.idShift ?? (rankMatch ? Number(rankMatch[0]) : 999);

                    return {
                        nama: sh.nama,
                        idShift: sh.idShift,
                        rank,
                        totalSlot: sh.slotList.length,
                        totalTersedia,
                        posList,
                        earliestTime: earliestSlot?.mulai || '',
                    };
                })
                .sort((a, b) => {
                    if (a.rank !== b.rank) return a.rank - b.rank;
                    return a.earliestTime.localeCompare(b.earliestTime);
                });
        },
    },
    watch: {
        hari: {
            immediate: true,
            handler(daftar) {
                if (daftar.some((h) => h.tanggal === this.tanggalAktif)) {
                    this.$nextTick(() => this.checkScrollBounds());
                    return;
                }
                const terpilih = this.modelValue?.mulai ? this.modelValue.mulai.slice(0, 10) : null;
                this.tanggalAktif = daftar.find((h) => h.tanggal === terpilih)?.tanggal || daftar[0]?.tanggal || null;
                this.$nextTick(() => {
                    this.syncExpandedShifts();
                    this.checkScrollBounds();
                });
            },
        },
        tanggalAktif() {
            this.$nextTick(() => {
                this.syncExpandedShifts();
                this.checkScrollBounds();
            });
        },
        'modelValue.mulai'() {
            if (this.modelValue?.mulai) {
                const shiftWithSelection = this.shiftAktif.find((sh) => this.hasSelectedSlot(sh));
                if (shiftWithSelection && !this.expandedShifts.includes(shiftWithSelection.nama)) {
                    const isMobile = typeof window !== 'undefined' && window.innerWidth < 768;
                    if (isMobile) {
                        this.expandedShifts = [shiftWithSelection.nama];
                    } else {
                        this.expandedShifts.push(shiftWithSelection.nama);
                    }
                }
            }
        },
    },
    mounted() {
        this.$nextTick(() => {
            this.syncExpandedShifts();
            this.checkScrollBounds();
        });
        if (typeof window !== 'undefined') {
            window.addEventListener('resize', this.checkScrollBounds);
        }
    },
    beforeUnmount() {
        if (typeof window !== 'undefined') {
            window.removeEventListener('resize', this.checkScrollBounds);
        }
    },
    methods: {
        isShiftExpanded(nama) {
            return this.expandedShifts.includes(nama);
        },
        hasSelectedSlot(shiftGrup) {
            if (!this.modelValue?.mulai) return false;
            return shiftGrup.posList.some((p) => p.slot.some((s) => this.dipilih(s)));
        },
        toggleShift(nama) {
            const isMobile = typeof window !== 'undefined' && window.innerWidth <= 768;
            if (isMobile) {
                if (this.expandedShifts.includes(nama)) {
                    this.expandedShifts = [];
                } else {
                    this.expandedShifts = [nama];
                }
            } else {
                if (this.expandedShifts.includes(nama)) {
                    this.expandedShifts = this.expandedShifts.filter((n) => n !== nama);
                } else {
                    this.expandedShifts.push(nama);
                }
            }
        },
        syncExpandedShifts() {
            if (!this.shiftAktif || !this.shiftAktif.length) {
                this.expandedShifts = [];
                return;
            }

            // Peta slot SHE (hanyaBaca) atau jika di-override defaultOpen: buka seluruh shift
            if (this.hanyaBaca || this.defaultOpen) {
                this.expandedShifts = this.shiftAktif.map((s) => s.nama);
                return;
            }

            // Default untuk form pemesanan: tertutup semua agar tampilan compact.
            // Bila ada slot yang sudah dipilih di tanggal ini, buka shift tersebut agar terlihat.
            const shiftWithSelection = this.shiftAktif.find((sh) => this.hasSelectedSlot(sh));
            this.expandedShifts = shiftWithSelection ? [shiftWithSelection.nama] : [];
        },
        shiftIcon(namaShift) {
            const s = String(namaShift || '').toLowerCase();
            if (s.includes('1') || s.includes('pagi')) return 'sun-fill';
            if (s.includes('2') || s.includes('siang') || s.includes('sore')) return 'sunset-fill';
            if (s.includes('3') || s.includes('malam')) return 'moon-stars-fill';
            return 'clock-fill';
        },
        shiftSentiment(namaShift) {
            const s = String(namaShift || '').toLowerCase();
            if (s.includes('1') || s.includes('pagi')) return 'amber';
            if (s.includes('2') || s.includes('siang') || s.includes('sore')) return 'indigo';
            if (s.includes('3') || s.includes('malam')) return 'purple';
            return 'brand';
        },
        /**
         * Keadaan satu slot: 'tersedia' | 'penuh' | 'lewat'.
         *
         * Server mengirimkannya lewat `keadaan` bila diminta dengan `semua=1`.
         * Muatan lama tak punya kolom itu — di sana seluruh slot memang sudah
         * disaring server, jadi yang tersisa hanya dua kemungkinan dan sisa
         * kuota cukup untuk membedakannya. Menebak 'lewat' dari jam browser
         * sengaja TIDAK dilakukan: jam klien bisa menyimpang dari jam server,
         * dan slot yang sebenarnya masih bisa dipesan akan tampil mati.
         */
        keadaan(slot) {
            if (slot?.keadaan) return slot.keadaan;

            return (slot?.sisa ?? 0) > 0 ? 'tersedia' : 'penuh';
        },
        jam(waktu) {
            return String(waktu).slice(11, 16);
        },
        dipilih(slot) {
            return Boolean(this.modelValue && this.modelValue.id_pos === slot.id_pos && this.modelValue.mulai === slot.mulai);
        },
        pilih(slot) {
            if (this.disabled || this.hanyaBaca) return;
            this.$emit('update:modelValue', {
                id_pos: slot.id_pos,
                pos: slot.pos,
                lokasi: slot.lokasi || '',
                mulai: slot.mulai,
                selesai: slot.selesai,
            });
        },
        formatTanggalPenuh(waktu) {
            if (!waktu) return '-';
            const ymd = String(waktu).slice(0, 10);
            const [y, m, d] = ymd.split('-').map(Number);
            const tgl = new Date(y, m - 1, d);
            return `${NAMA_HARI_PANJANG[tgl.getDay()]}, ${d} ${NAMA_BULAN_PANJANG[m - 1]} ${y}`;
        },
        checkScrollBounds() {
            const el = this.$refs.hariScroll;
            if (!el) return;
            this.canScrollLeft = el.scrollLeft > 6;
            this.canScrollRight = el.scrollLeft < (el.scrollWidth - el.clientWidth - 6);
        },
        scrollDays(direction) {
            const el = this.$refs.hariScroll;
            if (!el) return;
            const scrollAmount = Math.max(el.clientWidth * 0.7, 200);
            el.scrollBy({
                left: direction === 'left' ? -scrollAmount : scrollAmount,
                behavior: 'smooth',
            });
            setTimeout(() => {
                this.checkScrollBounds();
            }, 350);
        },
        selectDate(tanggal) {
            this.tanggalAktif = tanggal;
            this.$nextTick(() => {
                const el = this.$refs.hariScroll;
                if (!el) return;
                const activeBtn = el.querySelector('.slot-inspeksi__tanggal.is-aktif');
                if (activeBtn) {
                    activeBtn.scrollIntoView({
                        behavior: 'smooth',
                        inline: 'nearest',
                        block: 'nearest',
                    });
                }
                setTimeout(() => this.checkScrollBounds(), 350);
            });
        },
    },
};
</script>

<style scoped>
.slot-inspeksi {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
    width: 100%;
}

.slot-inspeksi.is-disabled {
    opacity: 0.6;
    pointer-events: none;
}

/* ══ LOADING & SKELETON ══ */
.slot-inspeksi__loading {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-2, 0.5rem);
}

.slot-inspeksi__loading-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
    gap: 0.5rem;
}

.slot-inspeksi__loading-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 0.65rem;
}

/* ══ EMPTY STATE ══ */
.slot-inspeksi__kosong {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3, 0.75rem);
    padding: 1.5rem;
    border: 1.5px dashed var(--hcis-border-subtle, rgba(15, 23, 42, 0.15));
    border-radius: var(--hcis-radius-lg, 12px);
    background: var(--hcis-surface-sunken, #f8fafc);
}

.slot-inspeksi__kosong-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background: var(--hcis-surface-raised, #ffffff);
    color: var(--hcis-text-muted, #64748b);
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.slot-inspeksi__kosong-title {
    display: block;
    font-size: 0.875rem;
    color: var(--hcis-text-strong, #0f172a);
}

.slot-inspeksi__kosong-desc {
    font-size: 0.75rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.4;
}

/* ══ DATE DECK & CAROUSEL ══ */
.slot-inspeksi__date-deck {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1rem 1.15rem;
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    border: 1.5px solid var(--hcis-border, #e2e8f0);
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
}

.slot-inspeksi__section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.slot-inspeksi__header-left {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
}

.slot-inspeksi__header-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.slot-inspeksi__icon-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 8px;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    color: #4f46e5;
    flex-shrink: 0;
}

.slot-inspeksi__section-title {
    display: block;
    font-size: 0.875rem;
    font-weight: 800;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.25;
}

.slot-inspeksi__section-subtitle {
    display: block;
    font-size: 0.71875rem;
    color: var(--hcis-text-muted, #64748b);
    line-height: 1.25;
    margin-top: 0.1rem;
}

.slot-inspeksi__header-right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-shrink: 0;
}

.slot-inspeksi__section-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.71875rem;
    font-weight: 700;
    color: var(--hcis-brand-700, #4338ca);
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    white-space: nowrap;
    flex-shrink: 0;
}

.slot-inspeksi__badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hcis-brand-600, #4f46e5);
    flex-shrink: 0;
}

.slot-inspeksi__nav-controls {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    flex-shrink: 0;
}

.slot-inspeksi__nav-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    background: #ffffff;
    border: 1.5px solid var(--hcis-border, #cbd5e1);
    color: var(--hcis-slate-700, #334155);
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}

.slot-inspeksi__nav-btn:hover:not(:disabled) {
    background: var(--hcis-brand-50, #eef2ff);
    border-color: var(--hcis-brand-300, #a5b4fc);
    color: var(--hcis-brand-700, #4338ca);
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.15);
    transform: translateY(-1px);
}

.slot-inspeksi__nav-btn:active:not(:disabled) {
    transform: scale(0.92);
}

.slot-inspeksi__nav-btn:disabled,
.slot-inspeksi__nav-btn.is-disabled {
    opacity: 0.35;
    background: #f8fafc;
    border-color: var(--hcis-slate-200, #e2e8f0);
    color: var(--hcis-slate-400, #94a3b8);
    cursor: not-allowed;
    pointer-events: none;
    box-shadow: none;
    transform: none;
}

/* ══ DATE CAROUSEL STRIP ══ */
.slot-inspeksi__hari-wrap {
    width: 100%;
    overflow: hidden;
    position: relative;
}

.slot-inspeksi__hari {
    display: flex;
    gap: 0.6rem;
    overflow-x: auto;
    padding: 6px 4px 10px 4px;
    scrollbar-width: thin;
    scrollbar-color: var(--hcis-slate-300, #cbd5e1) transparent;
    scroll-snap-type: x mandatory;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

.slot-inspeksi__hari::-webkit-scrollbar {
    height: 5px;
}

.slot-inspeksi__hari::-webkit-scrollbar-thumb {
    background: var(--hcis-slate-300, #cbd5e1);
    border-radius: 999px;
}

.slot-inspeksi__tanggal {
    flex: 0 0 auto;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 86px;
    padding: 0.7rem 0.8rem;
    border: 1.5px solid var(--hcis-border, #e2e8f0);
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    scroll-snap-align: start;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.slot-inspeksi__tanggal:hover:not(:disabled) {
    border-color: var(--hcis-brand-400, #818cf8);
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(79, 70, 229, 0.08);
}

.slot-inspeksi__tanggal.is-aktif {
    border-color: var(--hcis-brand-600, #4f46e5);
    background: linear-gradient(180deg, #ffffff 0%, #eef2ff 100%);
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.18), 0 0 0 1px var(--hcis-brand-600, #4f46e5);
    transform: translateY(-1px);
}

.slot-inspeksi__tanggal.is-terpilih {
    border-color: var(--hcis-brand-600, #4f46e5);
}

.slot-inspeksi__tanggal-terpilih-dot {
    position: absolute;
    top: -6px;
    right: -6px;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    background: var(--hcis-emerald-600, #059669);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.slot-inspeksi__nama-hari {
    font-size: 0.6875rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--hcis-slate-400, #94a3b8);
}

.slot-inspeksi__tanggal.is-aktif .slot-inspeksi__nama-hari {
    color: var(--hcis-brand-600, #4f46e5);
}

.slot-inspeksi__angka {
    font-weight: 850;
    font-size: 0.9375rem;
    color: var(--hcis-text-strong, #0f172a);
    margin: 0.2rem 0;
    white-space: nowrap;
}

.slot-inspeksi__jumlah {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.65625rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 9999px;
    background: #f1f5f9;
    color: var(--hcis-slate-600, #475569);
}

.slot-inspeksi__dot-indicator {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--hcis-emerald-500, #10b981);
}

.slot-inspeksi__tanggal.is-aktif .slot-inspeksi__jumlah {
    background: #e0e7ff;
    color: #3730a3;
}

.slot-inspeksi__tanggal.is-aktif .slot-inspeksi__dot-indicator {
    background: #4f46e5;
}

/* ══ SHIFT GROUPS CONTAINER (ACCORDION) ══ */
.slot-inspeksi__shift-container {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.slot-inspeksi__shift-section {
    display: flex;
    flex-direction: column;
    background: rgba(248, 250, 252, 0.85);
    border: 1.5px solid var(--hcis-slate-200, #e2e8f0);
    border-radius: var(--hcis-radius-xl, 14px);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.02);
    overflow: hidden;
    transition: all 220ms cubic-bezier(0.16, 1, 0.3, 1);
}

.slot-inspeksi__shift-section.is-collapsed:hover {
    background: #ffffff;
    border-color: var(--hcis-brand-300, #a5b4fc);
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.05);
}

.slot-inspeksi__shift-section.is-expanded {
    background: #ffffff;
    border-color: var(--hcis-brand-200, #c7d2fe);
    box-shadow: 0 4px 16px -2px rgba(79, 70, 229, 0.06);
}

.slot-inspeksi__shift-section.has-selected {
    border-color: var(--hcis-brand-500, #6366f1);
    box-shadow: 0 0 0 1px var(--hcis-brand-500, #6366f1), 0 4px 14px rgba(79, 70, 229, 0.08);
}

.slot-inspeksi__shift-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    padding: 0.95rem 1.2rem;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background-color 180ms ease;
    -webkit-tap-highlight-color: transparent;
}

.slot-inspeksi__shift-header:hover {
    background: rgba(241, 245, 249, 0.7);
}

.slot-inspeksi__shift-header:focus-visible {
    outline: 2px solid var(--hcis-brand-500, #6366f1);
    outline-offset: -2px;
}

.slot-inspeksi__shift-header-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.slot-inspeksi__shift-header-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.slot-inspeksi__shift-header-right {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-shrink: 0;
}

.slot-inspeksi__shift-chevron {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 8px;
    color: var(--hcis-slate-400, #94a3b8);
    background: rgba(241, 245, 249, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.8);
    transition: transform 260ms cubic-bezier(0.16, 1, 0.3, 1),
                color 200ms ease,
                background-color 200ms ease,
                border-color 200ms ease;
    flex-shrink: 0;
}

.slot-inspeksi__shift-chevron.is-rotated {
    transform: rotate(180deg);
    color: var(--hcis-brand-600, #4f46e5);
    background: var(--hcis-brand-50, #eef2ff);
    border-color: var(--hcis-brand-200, #c7d2fe);
}

.slot-inspeksi__selected-shift-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.625rem;
    font-weight: 800;
    padding: 0.1rem 0.5rem;
    border-radius: 9999px;
    background: #e0e7ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
}

/* Accordion Smooth Height Transition via CSS Grid */
.slot-inspeksi__shift-body-wrap {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 280ms cubic-bezier(0.16, 1, 0.3, 1),
                opacity 220ms ease;
    opacity: 0;
    overflow: hidden;
    border-top: 1px solid transparent;
}

.slot-inspeksi__shift-body-wrap.is-expanded {
    grid-template-rows: 1fr;
    opacity: 1;
    border-top-color: rgba(226, 232, 240, 0.9);
}

.slot-inspeksi__shift-body-inner {
    min-height: 0;
    overflow: hidden;
    padding: 0.85rem 1.2rem 1.15rem;
}

.slot-inspeksi__shift-icon-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    flex-shrink: 0;
}

.slot-inspeksi__shift-icon-box.is-amber {
    background: #fef3c7;
    color: #d97706;
    border: 1px solid #fde68a;
}

.slot-inspeksi__shift-icon-box.is-indigo {
    background: #e0e7ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
}

.slot-inspeksi__shift-icon-box.is-purple {
    background: #f3e8ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
}

.slot-inspeksi__shift-icon-box.is-brand {
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
}

.slot-inspeksi__shift-title {
    font-size: 0.9375rem;
    font-weight: 850;
    color: var(--hcis-slate-900, #0f172a);
    margin: 0;
    line-height: 1.2;
}

.slot-inspeksi__shift-pill {
    font-size: 0.65625rem;
    font-weight: 750;
    background: #e2e8f0;
    color: #334155;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
}

.slot-inspeksi__shift-subtitle {
    display: block;
    font-size: 0.71875rem;
    color: var(--hcis-slate-500, #64748b);
    margin-top: 0.1rem;
}

/* ══ POS LOCATION CARDS ══ */
.slot-inspeksi__pos-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.slot-inspeksi__pos-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1.1rem 1.2rem;
    border: 1.5px solid var(--hcis-border, #e2e8f0);
    border-radius: var(--hcis-radius-lg, 12px);
    background: #ffffff;
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04);
}

.slot-inspeksi__pos-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1.5px dashed var(--hcis-border, #e2e8f0);
}

.slot-inspeksi__pos-info {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.slot-inspeksi__pos-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
    border: 1px solid #c7d2fe;
    color: #4f46e5;
    flex-shrink: 0;
}

.slot-inspeksi__pos-nama {
    display: block;
    font-size: 0.9375rem;
    font-weight: 850;
    color: var(--hcis-text-strong, #0f172a);
    line-height: 1.2;
}

.slot-inspeksi__pos-subtext {
    font-size: 0.71875rem;
    color: var(--hcis-text-muted, #64748b);
}

.slot-inspeksi__pos-slot-count {
    font-size: 0.71875rem;
    font-weight: 750;
    color: var(--hcis-slate-700, #334155);
    background: #f1f5f9;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    border: 1px solid #e2e8f0;
}

/* ══ TIME SLOTS GRID ══ */
.slot-inspeksi__jam {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(195px, 1fr));
    gap: 0.75rem;
}

.slot-inspeksi__slot {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.75rem 0.9rem;
    border: 1.5px solid var(--hcis-border, #e2e8f0);
    border-radius: var(--hcis-radius-md, 10px);
    background: #ffffff;
    color: var(--hcis-text-strong, #0f172a);
    cursor: pointer;
    text-align: left;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    min-height: 4.15rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.slot-inspeksi__slot:hover:not(:disabled):not(.is-terpilih) {
    border-color: var(--hcis-brand-400, #818cf8);
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(79, 70, 229, 0.09);
}

/* Harmonious Luminous Active Selected State */
.slot-inspeksi__slot.is-terpilih {
    border-color: var(--hcis-brand-600, #4f46e5);
    background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 100%);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.16), 0 8px 18px -2px rgba(79, 70, 229, 0.18);
    transform: translateY(-1px);
}

.slot-inspeksi__slot-check-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 50%;
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35);
    z-index: 2;
}

.slot-inspeksi__slot.is-lewat {
    opacity: 0.45;
    cursor: not-allowed;
    filter: grayscale(1);
}

.slot-inspeksi__capacity-dot.is-lewat {
    background: var(--hcis-slate-400, #94a3b8);
}

.slot-inspeksi__slot.is-penuh {
    background: #f8fafc;
    border-style: dashed;
    cursor: not-allowed;
    opacity: 0.6;
}

.slot-inspeksi__slot-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.slot-inspeksi__slot-time-box {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.slot-inspeksi__slot-icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 6px;
    background: #eef2ff;
    color: #4f46e5;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.slot-inspeksi__slot.is-terpilih .slot-inspeksi__slot-icon-wrap {
    background: #4f46e5;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35);
}

.slot-inspeksi__waktu {
    font-weight: 800;
    font-size: 0.875rem;
    letter-spacing: -0.01em;
    color: var(--hcis-slate-900, #0f172a);
    white-space: nowrap;
}

.slot-inspeksi__slot.is-terpilih .slot-inspeksi__waktu {
    color: var(--hcis-brand-950, #1e1b4b);
    font-weight: 850;
}

.slot-inspeksi__wib-badge {
    font-size: 0.625rem;
    font-weight: 800;
    color: var(--hcis-slate-500, #64748b);
    background: #f1f5f9;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    letter-spacing: 0.04em;
    flex-shrink: 0;
}

.slot-inspeksi__slot.is-terpilih .slot-inspeksi__wib-badge {
    background: #c7d2fe;
    color: #3730a3;
}

.slot-inspeksi__slot-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    margin-top: 0.45rem;
}

.slot-inspeksi__sisa {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.6875rem;
    font-weight: 650;
    color: var(--hcis-slate-600, #475569);
    white-space: nowrap;
}

.slot-inspeksi__capacity-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.slot-inspeksi__capacity-dot.is-success {
    background: var(--hcis-emerald-500, #10b981);
}

.slot-inspeksi__capacity-dot.is-warning {
    background: var(--hcis-amber-500, #f59e0b);
}

.slot-inspeksi__capacity-dot.is-danger {
    background: var(--hcis-danger-500, #ef4444);
}

.slot-inspeksi__slot.is-sisa-sedikit .slot-inspeksi__sisa {
    color: var(--hcis-warning-700, #b45309);
    font-weight: 750;
}

.slot-inspeksi__slot.is-terpilih .slot-inspeksi__sisa {
    color: var(--hcis-brand-800, #3730a3);
    font-weight: 750;
}

.slot-inspeksi__terpilih-pill {
    font-size: 0.625rem;
    font-weight: 850;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.125rem 0.5rem;
    border-radius: 999px;
    background: var(--hcis-brand-600, #4f46e5);
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.25);
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .slot-inspeksi__jam {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575.98px) {
    /* Date Deck & Header Mobile Layout */
    .slot-inspeksi__date-deck {
        padding: 0.75rem 0.75rem 0.85rem;
        gap: 0.65rem;
        border-radius: var(--hcis-radius-md, 10px);
    }

    .slot-inspeksi__section-header {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0.55rem;
    }

    .slot-inspeksi__header-left {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        width: 100%;
    }

    .slot-inspeksi__icon-badge {
        width: 2rem;
        height: 2rem;
        border-radius: 7px;
    }

    .slot-inspeksi__section-title {
        font-size: 0.84375rem;
    }

    .slot-inspeksi__section-subtitle {
        font-size: 0.6875rem;
    }

    .slot-inspeksi__header-right {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding-top: 0.45rem;
        border-top: 1px solid var(--hcis-border-light, #f1f5f9);
    }

    .slot-inspeksi__section-badge {
        font-size: 0.6875rem;
        padding: 0.2rem 0.55rem;
    }

    .slot-inspeksi__nav-controls {
        gap: 0.35rem;
    }

    .slot-inspeksi__nav-btn {
        width: 2.15rem;
        height: 2.15rem;
        border-radius: 7px;
    }

    .slot-inspeksi__hari {
        gap: 0.5rem;
        padding: 6px 2px 8px 2px;
    }

    .slot-inspeksi__tanggal {
        min-width: 78px;
        padding: 0.55rem 0.55rem;
        border-radius: 8px;
    }

    .slot-inspeksi__nama-hari {
        font-size: 0.625rem;
    }

    .slot-inspeksi__angka {
        font-size: 0.85rem;
        margin: 0.125rem 0 0.2rem;
    }

    .slot-inspeksi__jumlah {
        font-size: 0.6rem;
        padding: 0.1rem 0.38rem;
    }

    .slot-inspeksi__shift-header {
        padding: 0.75rem 0.85rem;
        gap: 0.5rem;
    }

    .slot-inspeksi__shift-header-main {
        gap: 0.55rem;
    }

    .slot-inspeksi__shift-icon-box {
        width: 2rem;
        height: 2rem;
    }

    .slot-inspeksi__shift-title {
        font-size: 0.85rem;
    }

    .slot-inspeksi__shift-subtitle {
        font-size: 0.68rem;
    }

    .slot-inspeksi__shift-chevron {
        width: 1.65rem;
        height: 1.65rem;
    }

    .slot-inspeksi__shift-body-inner {
        padding: 0.65rem 0.75rem 0.85rem;
    }

    .slot-inspeksi__pos-card {
        padding: 0.75rem 0.75rem;
    }
}

@media (max-width: 480px) {
    .slot-inspeksi__jam {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
    }

    .slot-inspeksi__slot {
        padding: 0.5rem 0.55rem;
        min-height: 3.5rem;
    }

    .slot-inspeksi__slot-icon-wrap {
        width: 1.25rem;
        height: 1.25rem;
    }

    .slot-inspeksi__waktu {
        font-size: 0.8125rem;
    }

    .slot-inspeksi__wib-badge {
        font-size: 0.5625rem;
        padding: 0.05rem 0.25rem;
    }

    .slot-inspeksi__slot-bottom {
        margin-top: 0.35rem;
    }

    .slot-inspeksi__sisa {
        font-size: 0.625rem;
    }

    .slot-inspeksi__terpilih-pill {
        font-size: 0.5625rem;
        padding: 0.1rem 0.35rem;
    }
}
</style>


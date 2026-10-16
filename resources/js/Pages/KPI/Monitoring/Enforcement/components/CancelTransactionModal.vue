<template>
    <Modal
        :show="show"
        :title="modalTitle"
        :subtitle="modalSubtitle"
        size="large"
        :closeOnOverlay="false"
        :loading="executing"
        loadingText="Membatalkan transaksi…"
        @close="$emit('close')"
    >
        <template #body>
            <!-- ============ HASIL ============ -->
            <div v-if="result" class="cx-result">
                <div class="cx-result-icon"><i class="bi bi-check-circle-fill"></i></div>
                <h4>Pembatalan berhasil</h4>
                <p class="cx-result-sum">
                    <strong>{{ result.cancelled.parameter }}</strong> pengajuan parameter ·
                    <strong>{{ result.cancelled.performance }}</strong> update performance ·
                    <strong>{{ result.cancelled.draft }}</strong> draft
                </p>
                <div class="cx-batch">
                    <span>Batch ID</span>
                    <code>{{ result.batch_id }}</code>
                    <button class="cx-copy" @click="copyBatch">
                        <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-clipboard'"></i>
                        {{ copied ? 'Tersalin' : 'Salin' }}
                    </button>
                </div>
                <p class="cx-hint">Telusuri kapan pun di sub-tab <b>Riwayat Pembatalan</b>.</p>
            </div>

            <!-- ============ KONFLIK (409) ============ -->
            <div v-else-if="conflicts && conflicts.length" class="cx-conflict">
                <div class="cx-banner danger">
                    <i class="bi bi-shield-exclamation"></i>
                    <div>
                        <p class="cx-banner-title">Tidak ada satu pun transaksi yang dibatalkan.</p>
                        <small
                            >Status sebagian transaksi berubah sejak Anda membuka preview. Demi keamanan, seluruh aksi
                            dibatalkan.</small
                        >
                    </div>
                </div>
                <div v-for="(c, i) in conflicts" :key="i" class="cx-conflict-item">
                    <div class="cx-ci-head">
                        <span class="cx-ci-name">{{ c.nama || '—' }}</span>
                        <span v-if="c.no_transaksi" class="cx-mono">{{ c.no_transaksi }}</span>
                    </div>
                    <p>{{ c.reason }}</p>
                </div>
            </div>

            <!-- ============ MEMUAT PREVIEW ============ -->
            <div v-else-if="!preview" class="cx-loading">
                <i class="bi bi-arrow-repeat spin"></i> Menghitung dampak…
            </div>

            <!-- ============ WIZARD ============ -->
            <template v-else>
                <!-- Stepper -->
                <div class="cx-steps">
                    <div v-for="s in steps" :key="s.n" class="cx-step" :class="{ on: step === s.n, done: step > s.n }">
                        <span class="cx-step-dot">
                            <i v-if="step > s.n" class="bi bi-check-lg"></i>
                            <template v-else>{{ s.n }}</template>
                        </span>
                        <span class="cx-step-label">{{ s.label }}</span>
                    </div>
                </div>

                <!-- ---------- LANGKAH 1: TINJAU DAMPAK ---------- -->
                <div v-show="step === 1" class="cx-pane">
                    <div class="cx-banner danger">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div>
                            <p class="cx-banner-title">
                                {{ activeItems.length }}
                                {{ domainNoun }} akan
                                dibatalkan.
                            </p>
                            <small v-if="domain === 'draft'">
                                Isian yang belum dikirim dibuang dan karyawan kembali tercatat <b>belum mengisi</b>;
                                selama jadwalnya masih berjalan ia bisa mengisi ulang dari awal. Jejak pembatalan tetap
                                tersimpan.
                            </small>
                            <small v-else>
                                Karyawan kembali berstatus <b>Belum Submit</b> dan approval yang menggantung dibatalkan.
                                Jejak pembatalan tetap tersimpan.
                            </small>
                        </div>
                    </div>

                    <div class="cx-stats">
                        <div class="cx-stat">
                            <span>Transaksi</span><strong>{{ activeItems.length }}</strong>
                        </div>
                        <div class="cx-stat danger" v-if="approvedCount">
                            <span>Sudah disetujui</span><strong>{{ approvedCount }}</strong>
                        </div>
                        <div class="cx-stat warn" v-if="cascadePerfCount">
                            <span>Performance ikut batal</span><strong>{{ cascadePerfCount }}</strong>
                        </div>
                        <div class="cx-stat warn" v-if="cascadeDraftCount">
                            <span>Draft ikut batal</span><strong>{{ cascadeDraftCount }}</strong>
                        </div>
                        <div class="cx-stat muted" v-if="blockedItems.length">
                            <span>Tidak bisa dibatalkan</span><strong>{{ blockedItems.length }}</strong>
                        </div>
                    </div>

                    <!-- Daftar item aktif -->
                    <div class="cx-list">
                        <div
                            v-for="item in activeItems"
                            :key="item.id"
                            class="cx-item"
                            :class="{ approved: item.is_approved, open: expanded === item.id }"
                        >
                            <div class="cx-item-head">
                                <button class="cx-item-main" :disabled="!hasCascade(item)" @click="toggleExpand(item)">
                                    <i
                                        v-if="hasCascade(item)"
                                        class="bi cx-caret"
                                        :class="expanded === item.id ? 'bi-chevron-down' : 'bi-chevron-right'"
                                    ></i>
                                    <span v-else class="cx-caret-spacer"></span>
                                    <span class="cx-avatar">{{ initials(item.nama) }}</span>
                                    <span class="cx-who">
                                        <span class="cx-name">{{ item.nama }}</span>
                                        <span class="cx-meta">
                                            {{ item.kode_karyawan }}
                                            <template v-if="item.divisi"> · {{ item.divisi }}</template>
                                        </span>
                                        <span class="cx-mono cx-trx">{{ item.no_transaksi || '—' }}</span>
                                    </span>
                                </button>
                                <div class="cx-item-right">
                                    <span class="cx-pill" :class="pillClass(item.status)">{{ item.status_label }}</span>
                                    <span v-if="item.is_approved" class="cx-pill hard-danger">
                                        <i class="bi bi-exclamation-triangle-fill"></i> Nilai KPI hilang
                                    </span>
                                    <button class="cx-remove" title="Keluarkan dari daftar" @click="exclude(item.id)">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Cascade -->
                            <div v-if="expanded === item.id && hasCascade(item)" class="cx-cascade">
                                <p class="cx-cascade-title">
                                    <i class="bi bi-diagram-3"></i> Ikut dibatalkan karena bergantung pada pengajuan ini
                                </p>
                                <div v-for="p in item.cascade_performance" :key="'p' + p.id" class="cx-cascade-row">
                                    <span class="cx-cascade-tag perf">Performance</span>
                                    <span class="cx-mono">{{ p.no_transaksi }}</span>
                                    <span class="cx-cascade-per">{{ p.periode }}</span>
                                    <span class="cx-pill sm" :class="pillClass(p.status)">{{ p.status_label }}</span>
                                    <span class="cx-cascade-score">Score {{ p.total_score }}</span>
                                </div>
                                <div v-for="d in item.cascade_draft" :key="'d' + d.id" class="cx-cascade-row">
                                    <span class="cx-cascade-tag draft">Draft</span>
                                    <span class="cx-cascade-per">{{ d.periode || '—' }}</span>
                                    <span class="cx-pill sm is-draft">
                                        {{ d.is_promoted ? 'Sudah difinalisasi' : 'Belum difinalisasi' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p v-if="!activeItems.length" class="cx-empty">
                            Tidak ada transaksi tersisa. Tutup modal atau pilih ulang.
                        </p>
                    </div>

                    <!-- Item terblokir -->
                    <div v-if="blockedItems.length" class="cx-blocked">
                        <p class="cx-blocked-title">
                            <i class="bi bi-slash-circle"></i> Dilewati ({{ blockedItems.length }})
                        </p>
                        <div v-for="b in blockedItems" :key="'b' + b.id" class="cx-blocked-row">
                            <span class="cx-name">{{ b.nama }}</span>
                            <span class="cx-mono" v-if="b.no_transaksi">{{ b.no_transaksi }}</span>
                            <small>{{ b.block_reason }}</small>
                        </div>
                    </div>
                </div>

                <!-- ---------- LANGKAH 2: ALASAN ---------- -->
                <div v-show="step === 2" class="cx-pane">
                    <label class="cx-label" for="cx-reason">
                        Alasan pembatalan <span class="cx-req">wajib</span>
                    </label>
                    <p class="cx-sub">
                        Tersimpan permanen di riwayat audit dan terlihat pada detail transaksi yang dibatalkan.
                    </p>
                    <textarea
                        id="cx-reason"
                        v-model="reason"
                        class="cx-textarea"
                        :class="{ invalid: reasonTouched && !reasonValid }"
                        rows="4"
                        maxlength="500"
                        placeholder="Jelaskan kenapa transaksi ini dibatalkan…"
                        @blur="reasonTouched = true"
                    ></textarea>
                    <div class="cx-counter">
                        <small :class="{ err: reasonTouched && !reasonValid }">
                            <template v-if="reasonTouched && !reasonValid">
                                Minimal {{ MIN_REASON }} karakter ({{ reason.trim().length }}/{{ MIN_REASON }})
                            </template>
                        </small>
                        <small>{{ reason.length }}/500</small>
                    </div>

                    <div class="cx-chips">
                        <button v-for="c in quickReasons" :key="c" class="cx-chip" @click="appendReason(c)">
                            + {{ c }}
                        </button>
                    </div>

                    <div class="cx-acks">
                        <label v-if="cascadeTotal" class="cx-ack">
                            <input type="checkbox" v-model="ackCascade" />
                            <span>
                                Saya paham <b>{{ cascadePerfCount }} update performance</b>
                                <template v-if="cascadeDraftCount">
                                    dan <b>{{ cascadeDraftCount }} draft</b>
                                </template>
                                ikut dibatalkan.
                            </span>
                        </label>
                        <label v-if="approvedCount" class="cx-ack danger">
                            <input type="checkbox" v-model="ackApproved" />
                            <span>
                                Saya paham <b>{{ approvedCount }} transaksi sudah disetujui</b> dan nilai KPI-nya akan
                                hilang.
                            </span>
                        </label>
                    </div>
                </div>

                <!-- ---------- LANGKAH 3: KONFIRMASI ---------- -->
                <div v-show="step === 3" class="cx-pane">
                    <div class="cx-banner danger">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div>
                            <p class="cx-banner-title">Periksa sekali lagi. Aksi ini tidak bisa dibatalkan sendiri.</p>
                            <small>Pastikan nama & nomor transaksi di bawah benar-benar yang Anda maksud.</small>
                        </div>
                    </div>

                    <div class="cx-recap">
                        <div v-for="item in activeItems" :key="'r' + item.id" class="cx-recap-row">
                            <span class="cx-avatar sm">{{ initials(item.nama) }}</span>
                            <span class="cx-who">
                                <span class="cx-name">{{ item.nama }}</span>
                                <span class="cx-mono cx-trx">{{ item.no_transaksi }}</span>
                            </span>
                            <span class="cx-pill" :class="pillClass(item.status)">{{ item.status_label }}</span>
                            <span v-if="hasCascade(item)" class="cx-recap-cascade">
                                +{{ item.cascade_performance.length + item.cascade_draft.length }} turunan
                            </span>
                        </div>
                    </div>

                    <div class="cx-reason-recap">
                        <span>Alasan</span>
                        <p>{{ reason.trim() }}</p>
                    </div>

                    <label class="cx-label" for="cx-confirm">
                        Ketik <code class="cx-target">{{ requiredConfirm }}</code> untuk mengaktifkan tombol
                    </label>
                    <input
                        id="cx-confirm"
                        v-model="confirmInput"
                        type="text"
                        class="cx-input"
                        :class="{ ok: confirmMatches, invalid: confirmInput && !confirmMatches }"
                        autocomplete="off"
                        spellcheck="false"
                        :placeholder="requiredConfirm"
                    />
                    <small v-if="confirmInput && !confirmMatches" class="cx-err">
                        Teks belum cocok persis (huruf besar/kecil diperhatikan).
                    </small>
                </div>
            </template>
        </template>

        <!-- ============ FOOTER ============ -->
        <template #footer>
            <div class="cx-footer">
                <template v-if="result">
                    <button class="cx-btn primary w-100" @click="$emit('close')">Selesai</button>
                </template>

                <template v-else-if="conflicts && conflicts.length">
                    <button class="cx-btn ghost" @click="$emit('close')">Tutup</button>
                    <button class="cx-btn primary" @click="$emit('reload')">
                        <i class="bi bi-arrow-clockwise"></i> Muat ulang dampak
                    </button>
                </template>

                <template v-else-if="preview">
                    <button v-if="step === 1" class="cx-btn ghost" @click="$emit('close')">Batal</button>
                    <button v-else class="cx-btn ghost" @click="step--">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </button>

                    <button v-if="step < 3" class="cx-btn primary" :disabled="!canAdvance" @click="step++">
                        Lanjut <i class="bi bi-arrow-right"></i>
                    </button>
                    <button v-else class="cx-btn danger" :disabled="!canConfirm || executing" @click="submit">
                        <i class="bi bi-x-octagon-fill"></i>
                        Batalkan {{ activeItems.length }} Transaksi
                    </button>
                </template>
            </div>
        </template>
    </Modal>
</template>

<script>
import Modal from '@/components/legacy/modalV2-noir.vue';

const MIN_REASON = 10;

export default {
    name: 'CancelTransactionModal',
    components: { Modal },
    props: {
        show: { type: Boolean, default: false },
        domain: { type: String, default: 'parameter' }, // parameter | performance | draft
        preview: { type: Object, default: null }, // null = masih memuat
        executing: { type: Boolean, default: false },
        result: { type: Object, default: null },
        conflicts: { type: Array, default: null },
    },
    emits: ['close', 'confirm', 'reload'],
    data() {
        return {
            MIN_REASON,
            step: 1,
            reason: '',
            reasonTouched: false,
            ackCascade: false,
            ackApproved: false,
            confirmInput: '',
            excludedIds: [],
            expanded: null,
            copied: false,
            steps: [
                { n: 1, label: 'Tinjau Dampak' },
                { n: 2, label: 'Alasan' },
                { n: 3, label: 'Konfirmasi' },
            ],
            quickReasons: [
                'Salah input parameter',
                'Duplikat transaksi',
                'Salah periode/penjadwalan',
                'Salah karyawan',
                'Permintaan atasan',
                'Perubahan target',
            ],
        };
    },
    computed: {
        modalTitle() {
            return {
                parameter: 'Batalkan Pengajuan KPI Parameter',
                performance: 'Batalkan Update Performance',
                draft: 'Batalkan Draft Update Performance',
            }[this.domain];
        },
        domainNoun() {
            return {
                parameter: 'pengajuan parameter',
                performance: 'update performance',
                draft: 'draft update performance',
            }[this.domain];
        },
        modalSubtitle() {
            if (this.result) return 'Transaksi telah dibatalkan dan tercatat di riwayat audit.';
            if (this.conflicts && this.conflicts.length) return 'Aksi dihentikan demi keamanan data.';
            if (this.domain === 'draft') return 'Draft dinonaktifkan sebelum sempat dikirim, jejaknya tetap tersimpan.';
            return 'Transaksi dinonaktifkan, approval yang menggantung ikut dibatalkan, jejaknya tetap tersimpan.';
        },
        allItems() {
            return this.preview?.items || [];
        },
        blockedItems() {
            return this.allItems.filter((i) => i.blocked);
        },
        activeItems() {
            return this.allItems.filter((i) => !i.blocked && !this.excludedIds.includes(i.id));
        },
        approvedCount() {
            return this.activeItems.filter((i) => i.is_approved).length;
        },
        cascadePerfCount() {
            return this.activeItems.reduce((n, i) => n + (i.cascade_performance?.length || 0), 0);
        },
        cascadeDraftCount() {
            return this.activeItems.reduce((n, i) => n + (i.cascade_draft?.length || 0), 0);
        },
        cascadeTotal() {
            return this.cascadePerfCount + this.cascadeDraftCount;
        },
        reasonValid() {
            return this.reason.trim().length >= MIN_REASON;
        },
        acksOk() {
            return (!this.cascadeTotal || this.ackCascade) && (!this.approvedCount || this.ackApproved);
        },
        /**
         * Frasa konfirmasi tetap: admin harus mengetik persis "YAKIN HAPUS" untuk
         * mengaktifkan tombol pembatalan (baik satu maupun banyak item).
         */
        requiredConfirm() {
            return 'YAKIN HAPUS';
        },
        confirmMatches() {
            return this.confirmInput === this.requiredConfirm;
        },
        canAdvance() {
            if (this.step === 1) return this.activeItems.length > 0;
            if (this.step === 2) return this.reasonValid && this.acksOk;
            return false;
        },
        canConfirm() {
            return this.activeItems.length > 0 && this.reasonValid && this.acksOk && this.confirmMatches;
        },
    },
    watch: {
        show(open) {
            if (open) this.reset();
        },
        // Membuang item mengubah target konfirmasi -> paksa admin mengetik ulang.
        requiredConfirm() {
            this.confirmInput = '';
        },
    },
    methods: {
        reset() {
            this.step = 1;
            this.reason = '';
            this.reasonTouched = false;
            this.ackCascade = false;
            this.ackApproved = false;
            this.confirmInput = '';
            this.excludedIds = [];
            this.expanded = null;
            this.copied = false;
        },
        initials(name) {
            if (!name) return '—';
            const p = name.trim().split(/\s+/);
            return (p.length >= 2 ? p[0][0] + p[1][0] : p[0][0]).toUpperCase();
        },
        pillClass(status) {
            const map = {
                approved: 'is-approved',
                waiting: 'is-waiting',
                rejected: 'is-rejected',
                draft: 'is-draft',
                cancelled: 'is-cancelled',
            };
            return map[status] || 'is-waiting';
        },
        hasCascade(item) {
            return (item.cascade_performance?.length || 0) + (item.cascade_draft?.length || 0) > 0;
        },
        toggleExpand(item) {
            if (!this.hasCascade(item)) return;
            this.expanded = this.expanded === item.id ? null : item.id;
        },
        exclude(id) {
            this.excludedIds.push(id);
            if (this.expanded === id) this.expanded = null;
        },
        appendReason(text) {
            const cur = this.reason.trim();
            this.reason = cur ? `${cur}; ${text}` : text;
            this.reasonTouched = true;
        },
        submit() {
            if (!this.canConfirm) return;
            const fingerprints = {};
            this.activeItems.forEach((i) => {
                fingerprints[i.id] = i.fingerprint;
            });
            this.$emit('confirm', {
                domain: this.domain,
                ids: this.activeItems.map((i) => i.id),
                reason: this.reason.trim(),
                fingerprints,
            });
        },
        async copyBatch() {
            try {
                await navigator.clipboard.writeText(this.result.batch_id);
                this.copied = true;
                setTimeout(() => (this.copied = false), 2000);
            } catch (e) {
                /* clipboard tidak tersedia */
            }
        },
    },
};
</script>

<style scoped>
/* Bootstrap Icons merender glyph lewat `::before` yang inline-block + shifted
   (`vertical-align: -.125em`), sehingga ikon selalu meleset ~2px di dalam tombol/pill
   yang di-center. Menjadikan <i> sebagai flex container membuat ::before jadi flex
   item -- vertical-align tidak lagi berlaku sama sekali, jadi ikon benar-benar center
   pada kedua sumbu. */
.bi {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.bi::before {
    display: block;
    line-height: 1;
}

.cx-pane {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

/* ---------- Stepper ---------- */
.cx-steps {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.9rem;
    margin-bottom: 0.9rem;
    border-bottom: 1px solid var(--hcis-slate-200);
}
.cx-step {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex: 1;
    min-width: 0;
    color: var(--hcis-slate-400);
    font-size: 0.75rem;
    font-weight: 700;
}
.cx-step-dot {
    display: grid;
    place-items: center;
    flex: none;
    width: 24px;
    height: 24px;
    line-height: 1;
    border-radius: 50%;
    background: var(--hcis-slate-100);
    border: 1px solid var(--hcis-slate-200);
    font-size: 0.7rem;
}
.cx-step.on {
    color: var(--hcis-red-600);
}
.cx-step.on .cx-step-dot {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-300);
    color: var(--hcis-red-600);
}
.cx-step.done {
    color: var(--hcis-emerald-600);
}
.cx-step.done .cx-step-dot {
    background: var(--hcis-emerald-100);
    border-color: var(--hcis-emerald-300);
    color: var(--hcis-emerald-600);
}
.cx-step-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ---------- Banner & stats ---------- */
.cx-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    padding: 0.8rem 0.9rem;
    border-radius: 12px;
    border: 1px solid;
}
.cx-banner.danger {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-200);
    color: var(--hcis-red-800);
}
/* Tinggi disamakan dgn tinggi baris judul di sebelahnya supaya ikon sejajar optis
   dengan judul, bukan menempel ke tepi atas kotak. */
.cx-banner > i {
    height: 1.25rem;
    font-size: 1.15rem;
    flex: none;
}
.cx-banner-title {
    margin: 0 0 0.15rem;
    font-weight: 700;
    font-size: 0.85rem;
}
.cx-banner small {
    font-size: 0.73rem;
    line-height: 1.45;
    opacity: 0.92;
}

.cx-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.cx-stat {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    flex: 1 1 120px;
    padding: 0.5rem 0.7rem;
    border-radius: 10px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}
.cx-stat span {
    font-size: 0.66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500);
}
.cx-stat strong {
    font-size: 1.05rem;
    color: var(--hcis-slate-900);
}
.cx-stat.danger {
    background: var(--hcis-red-50);
    border-color: var(--hcis-red-200);
}
.cx-stat.danger strong {
    color: var(--hcis-red-600);
}
.cx-stat.warn {
    background: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
}
.cx-stat.warn strong {
    color: var(--hcis-amber-700);
}
.cx-stat.muted strong {
    color: var(--hcis-slate-400);
}

/* ---------- Daftar item ---------- */
.cx-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 320px;
    overflow-y: auto;
}
.cx-item {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    background: #fff;
    overflow: hidden;
}
.cx-item.approved {
    border-color: var(--hcis-red-300);
    background: #fffbfb;
}
.cx-item-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.65rem;
}
.cx-item-main {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex: 1;
    min-width: 0;
    padding: 0;
    border: 0;
    background: none;
    text-align: left;
    cursor: pointer;
}
.cx-item-main:disabled {
    cursor: default;
}
.cx-caret {
    color: var(--hcis-slate-500);
    font-size: 0.8rem;
    flex: none;
}
.cx-caret-spacer {
    width: 0.8rem;
    flex: none;
}
.cx-avatar {
    display: grid;
    place-items: center;
    flex: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
    font-size: 0.68rem;
    font-weight: 800;
}
.cx-avatar.sm {
    width: 26px;
    height: 26px;
    font-size: 0.62rem;
}
.cx-who {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.cx-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.cx-meta {
    font-size: 0.68rem;
    color: var(--hcis-slate-500);
}
.cx-mono {
    font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace;
    font-size: 0.68rem;
    color: var(--hcis-slate-600);
}
.cx-trx {
    margin-top: 0.05rem;
}
.cx-item-right {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex: none;
}
.cx-remove {
    display: grid;
    place-items: center;
    width: 26px;
    height: 26px;
    padding: 0;
    line-height: 1;
    flex: none;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
    color: var(--hcis-slate-400);
    font-size: 0.7rem;
    cursor: pointer;
    transition: 0.15s;
}
.cx-remove:hover {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-300);
    color: var(--hcis-red-600);
}

/* ---------- Pills ---------- */
.cx-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    border: 1px solid;
    font-size: 0.66rem;
    font-weight: 700;
    white-space: nowrap;
}
.cx-pill.sm {
    font-size: 0.62rem;
}
.cx-pill.is-approved {
    background: var(--hcis-emerald-100);
    border-color: var(--hcis-emerald-300);
    color: var(--hcis-emerald-700);
}
.cx-pill.is-waiting {
    background: var(--hcis-amber-100);
    border-color: var(--hcis-amber-300);
    color: var(--hcis-amber-700);
}
.cx-pill.is-rejected {
    background: var(--hcis-red-100);
    border-color: var(--hcis-red-300);
    color: var(--hcis-red-700);
}
.cx-pill.is-draft {
    background: var(--hcis-indigo-100);
    border-color: var(--hcis-indigo-300);
    color: var(--hcis-indigo-700);
}
.cx-pill.is-cancelled {
    background: var(--hcis-slate-100);
    border-color: var(--hcis-slate-300);
    color: var(--hcis-slate-500);
}
.cx-pill.hard-danger {
    background: var(--hcis-red-600);
    border-color: var(--hcis-red-600);
    color: #fff;
}

/* ---------- Cascade ---------- */
.cx-cascade {
    padding: 0.6rem 0.7rem 0.7rem 2.4rem;
    background: var(--hcis-amber-50);
    border-top: 1px dashed var(--hcis-amber-200);
}
.cx-cascade-title {
    margin: 0 0 0.45rem;
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--hcis-amber-700);
}
.cx-cascade-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.35rem 0;
    border-top: 1px solid var(--hcis-amber-100);
}
.cx-cascade-tag {
    padding: 0.1rem 0.4rem;
    border-radius: 6px;
    font-size: 0.6rem;
    font-weight: 800;
    text-transform: uppercase;
}
.cx-cascade-tag.perf {
    background: #fed7aa;
    color: #9a3412;
}
.cx-cascade-tag.draft {
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-700);
}
.cx-cascade-per,
.cx-cascade-score {
    font-size: 0.68rem;
    color: #78716c;
}
.cx-cascade-score {
    margin-left: auto;
    font-weight: 700;
}

/* ---------- Blocked ---------- */
.cx-blocked {
    padding: 0.6rem 0.7rem;
    border-radius: 10px;
    background: var(--hcis-slate-50);
    border: 1px dashed var(--hcis-slate-300);
}
.cx-blocked-title {
    margin: 0 0 0.35rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--hcis-slate-500);
}
.cx-blocked-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0;
    color: var(--hcis-slate-400);
}
.cx-blocked-row small {
    font-size: 0.68rem;
}
.cx-empty {
    padding: 1.2rem;
    text-align: center;
    color: var(--hcis-slate-400);
    font-size: 0.8rem;
}

/* ---------- Alasan ---------- */
.cx-label {
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}
.cx-req {
    margin-left: 0.25rem;
    padding: 0.08rem 0.35rem;
    border-radius: 6px;
    background: var(--hcis-red-100);
    color: var(--hcis-red-600);
    font-size: 0.62rem;
    text-transform: uppercase;
}
.cx-sub {
    margin: -0.5rem 0 0;
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
}
.cx-textarea,
.cx-input {
    width: 100%;
    padding: 0.6rem 0.7rem;
    border: 1px solid var(--hcis-slate-300);
    border-radius: 10px;
    font-size: 0.82rem;
    color: var(--hcis-slate-900);
    resize: vertical;
}
.cx-textarea:focus,
.cx-input:focus {
    outline: none;
    border-color: var(--hcis-indigo-500);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
}
.cx-textarea.invalid,
.cx-input.invalid {
    border-color: var(--hcis-red-400);
    box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.12);
}
.cx-input.ok {
    border-color: var(--hcis-emerald-400);
    box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.14);
}
.cx-counter {
    display: flex;
    justify-content: space-between;
    margin-top: -0.6rem;
    color: var(--hcis-slate-400);
    font-size: 0.68rem;
}
.cx-counter .err,
.cx-err {
    color: var(--hcis-red-600);
    font-size: 0.7rem;
}
.cx-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.cx-chip {
    padding: 0.28rem 0.55rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 999px;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-600);
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    transition: 0.15s;
}
.cx-chip:hover {
    background: var(--hcis-indigo-50);
    border-color: var(--hcis-indigo-200);
    color: var(--hcis-indigo-700);
}
.cx-acks {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.cx-ack {
    display: flex;
    gap: 0.55rem;
    padding: 0.6rem 0.7rem;
    border: 1px solid var(--hcis-amber-200);
    border-radius: 10px;
    background: var(--hcis-amber-50);
    font-size: 0.76rem;
    line-height: 1.45;
    color: var(--hcis-amber-900);
    cursor: pointer;
}
.cx-ack.danger {
    border-color: var(--hcis-red-300);
    background: var(--hcis-red-50);
    color: var(--hcis-red-800);
}
.cx-ack input {
    margin-top: 0.15rem;
    flex: none;
}

/* ---------- Rekap ---------- */
.cx-recap {
    display: flex;
    flex-direction: column;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 10px;
}
.cx-recap-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.65rem;
    border-bottom: 1px solid var(--hcis-slate-100);
}
.cx-recap-row:last-child {
    border-bottom: 0;
}
.cx-recap-row .cx-pill {
    margin-left: auto;
}
.cx-recap-cascade {
    padding: 0.15rem 0.4rem;
    border-radius: 6px;
    background: var(--hcis-amber-50);
    border: 1px solid var(--hcis-amber-200);
    color: var(--hcis-amber-700);
    font-size: 0.65rem;
    font-weight: 700;
    white-space: nowrap;
}
.cx-reason-recap {
    padding: 0.6rem 0.7rem;
    border-radius: 10px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}
.cx-reason-recap span {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--hcis-slate-500);
}
.cx-reason-recap p {
    margin: 0.2rem 0 0;
    font-size: 0.8rem;
    color: var(--hcis-slate-900);
    white-space: pre-wrap;
}
.cx-target {
    padding: 0.1rem 0.4rem;
    border-radius: 6px;
    background: var(--hcis-slate-900);
    color: #fff;
    font-size: 0.75rem;
    user-select: none;
}

/* ---------- Hasil & konflik ---------- */
.cx-result {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.5rem 1rem;
    text-align: center;
}
.cx-result-icon {
    font-size: 2.4rem;
    color: var(--hcis-emerald-600);
}
.cx-result h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}
.cx-result-sum {
    margin: 0;
    font-size: 0.8rem;
    color: var(--hcis-slate-600);
}
.cx-batch {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.4rem;
    padding: 0.45rem 0.6rem;
    border-radius: 10px;
    background: var(--hcis-slate-50);
    border: 1px solid var(--hcis-slate-200);
}
.cx-batch span {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
}
.cx-batch code {
    font-size: 0.76rem;
    color: var(--hcis-slate-900);
}
.cx-copy {
    padding: 0.2rem 0.45rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 7px;
    background: #fff;
    color: var(--hcis-slate-600);
    font-size: 0.68rem;
    cursor: pointer;
}
.cx-hint {
    margin: 0;
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
}
.cx-conflict {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.cx-conflict-item {
    padding: 0.55rem 0.7rem;
    border: 1px solid var(--hcis-red-200);
    border-radius: 10px;
    background: #fff;
}
.cx-ci-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.cx-ci-name {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--hcis-slate-900);
}
.cx-conflict-item p {
    margin: 0.2rem 0 0;
    font-size: 0.73rem;
    color: var(--hcis-red-700);
}
.cx-loading {
    padding: 2rem;
    text-align: center;
    color: var(--hcis-slate-500);
    font-size: 0.85rem;
}
.spin {
    display: inline-block;
    animation: cx-spin 1s linear infinite;
}
@keyframes cx-spin {
    to {
        transform: rotate(360deg);
    }
}

/* ---------- Footer ---------- */
.cx-footer {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    width: 100%;
}
.cx-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.5rem 0.95rem;
    border-radius: 10px;
    border: 1px solid transparent;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: 0.15s;
}
.cx-btn.w-100 {
    width: 100%;
}
.cx-btn.ghost {
    background: #fff;
    border-color: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
}
.cx-btn.ghost:hover {
    background: var(--hcis-slate-50);
}
.cx-btn.primary {
    background: var(--hcis-indigo-700);
    color: #fff;
}
.cx-btn.primary:hover:not(:disabled) {
    background: var(--hcis-indigo-800);
}
.cx-btn.danger {
    background: var(--hcis-red-600);
    color: #fff;
}
.cx-btn.danger:hover:not(:disabled) {
    background: var(--hcis-red-700);
}
.cx-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ---------- Responsif ---------- */
@media (max-width: 767.98px) {
    .cx-step-label {
        display: none;
    }
    .cx-steps {
        justify-content: center;
        gap: 0.75rem;
    }
    .cx-step {
        flex: none;
    }
    .cx-stat {
        flex: 1 1 calc(50% - 0.25rem);
    }
    .cx-item-head {
        flex-wrap: wrap;
    }
    .cx-item-right {
        width: 100%;
        justify-content: flex-end;
    }
    .cx-cascade {
        padding-left: 0.7rem;
    }
    .cx-cascade-score {
        margin-left: 0;
    }
    .cx-list {
        max-height: none;
    }
    .cx-footer {
        flex-direction: column-reverse;
    }
    .cx-btn {
        width: 100%;
        padding: 0.65rem 1rem;
    }
    .cx-recap-row {
        flex-wrap: wrap;
    }
    .cx-recap-row .cx-pill {
        margin-left: 0;
    }
}
</style>

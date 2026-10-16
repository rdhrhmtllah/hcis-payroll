<template>
    <section class="transaction-history">
        <header class="history-hero">
            <div>
                <span class="history-kicker">{{ kicker }}</span>
                <h5>{{ title }}</h5>
                <p>
                    Seluruh perubahan oleh admin yang memiliki akses pada
                    <strong>{{ featureName }}</strong>.
                </p>
            </div>
            <button class="refresh-button" :disabled="loading" @click="$emit('refresh')">
                <i :class="['bi bi-arrow-clockwise', { spinning: loading }]"></i>
                Refresh
            </button>
        </header>

        <div class="history-toolbar">
            <label class="history-search">
                <i class="bi bi-search"></i>
                <input v-model.trim="search" placeholder="Cari transaksi, admin, atau karyawan..." />
            </label>
            <span class="history-count">{{ filteredItems.length }} transaksi</span>
        </div>

        <div v-if="loading" class="history-skeleton">
            <span v-for="index in 4" :key="index"></span>
        </div>
        <div v-else-if="notice" class="history-state warning">
            <i class="bi bi-info-circle"></i>
            <span>{{ notice }}</span>
        </div>
        <div v-else-if="!filteredItems.length" class="history-state">
            <i class="bi bi-clock-history"></i>
            <strong>Belum ada riwayat pada grup ini</strong>
            <span>Perubahan dari seluruh admin akan tampil di sini.</span>
        </div>
        <div v-else class="transaction-list">
            <button
                v-for="item in filteredItems"
                :key="item.id || item.transaction_id"
                class="transaction-row"
                @click="openDetail(item)"
            >
                <span :class="['transaction-icon', tone]"><i :class="icon"></i></span>
                <span class="transaction-main">
                    <span class="transaction-heading">
                        <strong>{{ item.type }}</strong>
                        <small>{{ formatDateTime(item.occurred_at) }}</small>
                    </span>
                    <span class="transaction-summary">{{ item.summary }}</span>
                    <span class="transaction-actor">
                        <i class="bi bi-person-circle"></i>
                        Oleh <b>{{ item.actor_name || item.actor_code }}</b>
                        <code>{{ item.transaction_id }}</code>
                    </span>
                </span>
                <span class="transaction-impact">
                    <b>{{ item.target_count || 0 }}</b>
                    <small>karyawan</small>
                </span>
                <i class="bi bi-chevron-right row-arrow"></i>
            </button>
        </div>

        <el-dialog v-model="detailVisible" width="min(760px, 94vw)" class="history-detail-dialog">
            <template #header>
                <div class="detail-title">
                    <span :class="['transaction-icon', tone]"><i :class="icon"></i></span>
                    <div>
                        <span class="history-kicker">Detail transaksi</span>
                        <h5>{{ selectedItem?.type }}</h5>
                    </div>
                </div>
            </template>

            <div v-if="selectedItem" class="detail-content">
                <div class="detail-banner">
                    <div>
                        <strong>{{ selectedItem.actor_name || selectedItem.actor_code }}</strong>
                        <span>melakukan perubahan pada {{ formatDateTime(selectedItem.occurred_at) }}</span>
                    </div>
                    <code>{{ selectedItem.transaction_id }}</code>
                </div>

                <label class="history-search detail-search">
                    <i class="bi bi-search"></i>
                    <input v-model.trim="detailSearch" placeholder="Cari karyawan dalam transaksi..." />
                </label>

                <div class="target-list">
                    <article v-for="target in filteredTargets" :key="target.kode" class="target-card">
                        <div class="target-heading">
                            <span class="target-avatar">{{ initials(target.nama) }}</span>
                            <div>
                                <strong>{{ target.nama || target.kode }}</strong>
                                <code>{{ target.kode }}</code>
                            </div>
                        </div>
                        <div v-for="(change, index) in target.changes" :key="index" class="change-row">
                            <div class="action-list">
                                <div
                                    v-for="(action, actionIndex) in describeChange(change)"
                                    :key="actionIndex"
                                    :class="['action-item', action.tone]"
                                >
                                    <span class="action-icon"><i :class="action.icon"></i></span>
                                    <div>
                                        <strong>{{ action.title }}</strong>
                                        <span>{{ action.description }}</span>
                                    </div>
                                </div>
                            </div>
                            <details class="technical-detail">
                                <summary>Data teknis perubahan</summary>
                                <span>{{ change.field || 'Assignment' }}</span>
                                <div class="change-values">
                                    <pre>{{ displayValue(change.before) }}</pre>
                                    <i class="bi bi-arrow-right"></i>
                                    <pre>{{ displayValue(change.after) }}</pre>
                                </div>
                            </details>
                        </div>
                    </article>
                </div>
            </div>
        </el-dialog>
    </section>
</template>

<script>
export default {
    name: 'TransactionHistory',
    emits: ['refresh'],
    props: {
        items: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
        notice: { type: String, default: null },
        featureName: { type: String, default: 'grup fitur terpilih' },
        mode: { type: String, required: true },
    },
    data() {
        return { search: '', detailSearch: '', detailVisible: false, selectedItem: null };
    },
    computed: {
        isMenu() {
            return this.mode === 'menu';
        },
        title() {
            return this.isMenu ? 'Riwayat Akses Menu' : 'Riwayat Assignment Approval';
        },
        kicker() {
            return this.isMenu ? 'Audit Menu' : 'Audit Approval';
        },
        icon() {
            return this.isMenu ? 'bi bi-grid-fill' : 'bi bi-diagram-3-fill';
        },
        tone() {
            return this.isMenu ? 'menu-tone' : 'approval-tone';
        },
        filteredItems() {
            const keyword = this.search.toLowerCase();
            if (!keyword) return this.items;

            return this.items.filter((item) =>
                [
                    item.transaction_id,
                    item.actor_name,
                    item.actor_code,
                    item.type,
                    item.summary,
                    ...(item.targets || []).flatMap((target) => [target.nama, target.kode]),
                ].some((value) => String(value || '').toLowerCase().includes(keyword)),
            );
        },
        filteredTargets() {
            const targets = this.selectedItem?.targets || [];
            const keyword = this.detailSearch.toLowerCase();
            if (!keyword) return targets;
            return targets.filter((target) =>
                [target.nama, target.kode].some((value) => String(value || '').toLowerCase().includes(keyword)),
            );
        },
    },
    methods: {
        openDetail(item) {
            this.selectedItem = item;
            this.detailSearch = '';
            this.detailVisible = true;
        },
        formatDateTime(value) {
            if (!value) return '-';
            return new Date(String(value).replace(' ', 'T')).toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        initials(name) {
            return String(name || '?')
                .split(' ')
                .filter(Boolean)
                .slice(0, 2)
                .map((part) => part[0])
                .join('')
                .toUpperCase();
        },
        displayValue(value) {
            if (value === null || value === undefined || value === '') return '(kosong)';
            if (typeof value === 'string') {
                try {
                    return JSON.stringify(JSON.parse(value), null, 2);
                } catch {
                    return value;
                }
            }
            return JSON.stringify(value, null, 2);
        },
        parseValue(value) {
            if (value === null || value === undefined || value === '') return null;
            if (typeof value !== 'string') return value;
            try {
                return JSON.parse(value);
            } catch {
                return value;
            }
        },
        describeChange(change) {
            return this.isMenu ? this.describeMenuChange(change) : this.describeApprovalChange(change);
        },
        describeMenuChange(change) {
            const value = this.parseValue(change.after);
            if (String(change.field || '').includes('menu_access_change') && value && typeof value === 'object') {
                if (value.detail_unavailable) {
                    return [
                        this.action(
                            'warning',
                            'Rincian hasil perubahan tidak tersedia',
                            'Permintaan tercatat, tetapi hasil menu yang benar-benar berubah tidak dapat dipastikan.',
                        ),
                    ];
                }
                const actions = [];
                (value.added || []).forEach((menu) =>
                    actions.push(this.action('added', 'Akses menu ditambahkan', this.menuDescription(menu))),
                );
                (value.removed || []).forEach((menu) =>
                    actions.push(this.action('removed', 'Akses menu dihapus', this.menuDescription(menu))),
                );

                if (actions.length) return actions;
                return [this.action('neutral', 'Tidak ada perubahan akses', 'Akses karyawan tetap sama.')];
            }

            return [
                this.action(
                    'warning',
                    'Rincian perubahan tidak tersedia',
                    'Log lama hanya mencatat identitas target dan belum menyimpan daftar menu yang ditambahkan atau dihapus.',
                ),
            ];
        },
        describeApprovalChange(change) {
            const before = this.normalizeApprovalRows(this.parseValue(change.before), change.approver_names);
            const after = this.normalizeApprovalRows(this.parseValue(change.after), change.approver_names);
            const beforeByOrder = new Map(before.map((row) => [row.order, row.approver]));
            const afterByOrder = new Map(after.map((row) => [row.order, row.approver]));
            if (String(change.transaction_type || '').toUpperCase() === 'BULK_ASSIGN') {
                const bulkActions = after
                    .filter((row) => beforeByOrder.get(row.order) !== row.approver)
                    .map((row) => {
                        const oldApprover = beforeByOrder.get(row.order);
                        return oldApprover
                            ? this.action('replaced', `Approver diganti pada posisi ${row.order}`, `${oldApprover} menjadi ${row.approver}`)
                            : this.action('added', `Approver ditambahkan pada posisi ${row.order}`, row.approver);
                    });
                return bulkActions.length
                    ? bulkActions
                    : [this.action('neutral', 'Tidak ada perubahan approver', 'Susunan approver tetap sama.')];
            }
            const orders = [...new Set([...beforeByOrder.keys(), ...afterByOrder.keys()])].sort((a, b) => a - b);
            const actions = [];

            orders.forEach((order) => {
                const oldApprover = beforeByOrder.get(order);
                const newApprover = afterByOrder.get(order);
                if (!oldApprover && newApprover) {
                    actions.push(this.action('added', `Approver ditambahkan pada posisi ${order}`, newApprover));
                } else if (oldApprover && !newApprover) {
                    actions.push(this.action('removed', `Approver dihapus dari posisi ${order}`, oldApprover));
                } else if (oldApprover !== newApprover) {
                    actions.push(this.action('replaced', `Approver diganti pada posisi ${order}`, `${oldApprover} menjadi ${newApprover}`));
                }
            });

            return actions.length
                ? actions
                : [this.action('neutral', 'Tidak ada perubahan approver', 'Susunan approver tetap sama.')];
        },
        normalizeApprovalRows(value, names = {}) {
            if (!Array.isArray(value)) return [];
            return value
                .map((row) => {
                    const code = String(row.Kode_Karyawan_Approver || row.Kode_Karyawan || row.approver || '').trim();
                    const name = names?.[code];
                    return {
                        order: Number(row.order_flow || row.Approval_Order || 0),
                        approver: name ? `${name} (${code})` : code,
                    };
                })
                .filter((row) => row.order > 0 && row.approver);
        },
        menuDescription(menu) {
            if (!menu || typeof menu !== 'object') return String(menu || '-');
            return menu.name === menu.code ? menu.name : `${menu.name} (${menu.code})`;
        },
        action(tone, title, description) {
            const icons = {
                added: 'bi bi-plus-lg',
                removed: 'bi bi-dash-lg',
                replaced: 'bi bi-arrow-left-right',
                warning: 'bi bi-exclamation-lg',
                neutral: 'bi bi-check2',
            };
            return { tone, title, description, icon: icons[tone] || icons.neutral };
        },
    },
};
</script>

<style scoped>
.transaction-history { display: grid; gap: 1rem; padding: .5rem; color: #172033; }
.history-hero { display: flex; justify-content: space-between; gap: 1rem; padding: 1.15rem; border: 1px solid #dfe4ff; border-radius: 16px; background: linear-gradient(130deg, #f8f9ff, var(--hcis-indigo-50) 58%, #f0fdfa); }
.history-kicker { color: var(--hcis-indigo-600); font-size: .65rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
.history-hero h5, .detail-title h5 { margin: .15rem 0; font-weight: 800; }
.history-hero p { margin: 0; color: #68758c; font-size: .76rem; }
.refresh-button { min-height: 40px; align-self: flex-start; display: inline-flex; align-items: center; gap: .4rem; padding: .55rem .8rem; border: 1px solid #d9deea; border-radius: 10px; background: #fff; color: #344054; font-size: .75rem; font-weight: 750; }
.history-toolbar { display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
.history-search { min-height: 40px; flex: 1; display: flex; align-items: center; gap: .55rem; padding: 0 .75rem; border: 1px solid var(--hcis-slate-200); border-radius: 11px; background: #fff; color: var(--hcis-slate-400); }
.history-search input { width: 100%; border: 0; outline: 0; background: transparent; font-size: .78rem; }
.history-count { color: #68758c; font-size: .72rem; font-weight: 700; white-space: nowrap; }
.transaction-list, .history-skeleton, .target-list { display: grid; gap: .65rem; }
.transaction-row { width: 100%; display: flex; align-items: center; gap: .8rem; padding: .9rem; border: 1px solid #e6eaf1; border-radius: 14px; background: #fff; color: inherit; text-align: left; transition: .18s ease; }
.transaction-row:hover { border-color: var(--hcis-indigo-200); transform: translateY(-1px); box-shadow: 0 7px 18px rgba(15,23,42,.06); }
.transaction-icon { flex: 0 0 38px; height: 38px; display: grid; place-items: center; border-radius: 11px; }
.menu-tone { color: var(--hcis-indigo-600); background: var(--hcis-indigo-50); }.approval-tone { color: #0f766e; background: #f0fdfa; }
.transaction-main { min-width: 0; flex: 1; display: grid; gap: .15rem; }
.transaction-heading { display: flex; justify-content: space-between; gap: .75rem; }.transaction-heading strong { font-size: .8rem; }.transaction-heading small { color: #8993a5; font-size: .65rem; white-space: nowrap; }
.transaction-summary { color: #68758c; font-size: .7rem; overflow-wrap: anywhere; }.transaction-actor { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; color: #68758c; font-size: .66rem; }.transaction-actor code { color: var(--hcis-indigo-600); }
.transaction-impact { min-width: 60px; display: grid; text-align: center; }.transaction-impact b { font-size: 1rem; }.transaction-impact small { color: #8993a5; font-size: .6rem; }.row-arrow { color: #a3aec0; }
.history-state { min-height: 180px; display: grid; place-items: center; align-content: center; gap: .35rem; padding: 1rem; border: 1px dashed #d9deea; border-radius: 14px; color: #8993a5; text-align: center; font-size: .75rem; }.history-state i { font-size: 1.6rem; }.history-state.warning { color: var(--hcis-amber-700); background: var(--hcis-amber-50); }
.history-skeleton span { height: 78px; border-radius: 14px; background: linear-gradient(90deg, #f1f3f7 25%, #fafbfc 50%, #f1f3f7 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
.detail-title, .detail-banner, .target-heading { display: flex; align-items: center; gap: .7rem; }.detail-content { display: grid; gap: .9rem; }.detail-banner { justify-content: space-between; padding: .8rem; border-radius: 12px; background: var(--hcis-slate-50); }.detail-banner div { display: grid; }.detail-banner span { color: #68758c; font-size: .68rem; }
.target-card { padding: .85rem; border: 1px solid #e6eaf1; border-radius: 13px; }.target-avatar { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 10px; color: var(--hcis-indigo-600); background: var(--hcis-indigo-50); font-size: .7rem; font-weight: 800; }.target-heading div { display: grid; }.target-heading strong { font-size: .76rem; }.target-heading code { color: #68758c; font-size: .63rem; }
.change-row { margin-top: .7rem; padding-top: .7rem; border-top: 1px solid #edf0f4; }.action-list { display: grid; gap: .45rem; }.action-item { display: flex; align-items: flex-start; gap: .55rem; padding: .65rem; border: 1px solid transparent; border-radius: 10px; }.action-icon { flex: 0 0 28px; height: 28px; display: grid; place-items: center; border-radius: 8px; font-size: .7rem; }.action-item div { display: grid; gap: .1rem; }.action-item strong { font-size: .7rem; }.action-item div span { color: #68758c; font-size: .67rem; overflow-wrap: anywhere; }.action-item.added { border-color: var(--hcis-green-200); background: var(--hcis-green-50); }.action-item.added .action-icon { color: var(--hcis-green-700); background: var(--hcis-green-100); }.action-item.removed { border-color: var(--hcis-red-200); background: var(--hcis-red-50); }.action-item.removed .action-icon { color: var(--hcis-red-700); background: var(--hcis-red-100); }.action-item.replaced { border-color: #bfdbfe; background: #eff6ff; }.action-item.replaced .action-icon { color: #1d4ed8; background: #dbeafe; }.action-item.warning { border-color: var(--hcis-amber-200); background: var(--hcis-amber-50); }.action-item.warning .action-icon { color: var(--hcis-amber-700); background: var(--hcis-amber-100); }.action-item.neutral { border-color: var(--hcis-slate-200); background: var(--hcis-slate-50); }.action-item.neutral .action-icon { color: var(--hcis-slate-500); background: var(--hcis-slate-200); }.technical-detail { margin-top: .55rem; color: #68758c; font-size: .63rem; }.technical-detail summary { cursor: pointer; font-weight: 700; }.technical-detail > span { display: block; margin-top: .45rem; }.change-values { display: grid; grid-template-columns: minmax(0,1fr) auto minmax(0,1fr); align-items: center; gap: .5rem; margin-top: .35rem; }.change-values pre { max-height: 150px; overflow: auto; margin: 0; padding: .55rem; border-radius: 8px; background: var(--hcis-slate-50); color: var(--hcis-slate-600); font-size: .62rem; white-space: pre-wrap; overflow-wrap: anywhere; }
.spinning { animation: spin .8s linear infinite; }@keyframes spin { to { transform: rotate(360deg); } }@keyframes shimmer { to { background-position: -200% 0; } }
@media (max-width: 767px) { .transaction-history { padding: 0; }.history-hero, .history-toolbar { flex-direction: column; align-items: stretch; }.refresh-button { width: 100%; justify-content: center; }.transaction-row { align-items: flex-start; flex-wrap: wrap; }.transaction-heading { display: grid; }.transaction-impact { margin-left: 46px; text-align: left; }.row-arrow { margin-left: auto; }.detail-banner { align-items: flex-start; flex-wrap: wrap; }.change-values { grid-template-columns: 1fr; }.change-values > i { transform: rotate(90deg); justify-self: center; } }
</style>

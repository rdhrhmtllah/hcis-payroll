<script>
import { normalizeApiError } from '../../../utils/apiError'
import {
    fetchDatabaseChanges,
    fetchDatabaseChangeSql,
    fetchDatabaseChangesBulkSql,
    updateDatabaseChangeStatus,
    updateDatabaseChangesBatch,
} from './api/databaseChangeApi'

const emptySummary = () => ({
    total: 0,
    development_done: 0,
    tracker_pending: 0,
    production_pending: 0,
    completed: 0,
})

export default {
    name: 'DatabaseChangeDashboardPage',

    data() {
        return {
            items: [],
            summary: emptySummary(),
            selected: [],
            copyingBulk: false,
            filter: 'action_required',
            type: '',
            loading: false,
            saving: false,
            message: '',
            errorState: null,
            trackerReference: '',
            requestSequence: 0,
        }
    },

    computed: {
        filteredItems() {
            return this.items
        },

        allSelected() {
            return this.filteredItems.length > 0
                && this.filteredItems.every((item) => this.selected.includes(item.id))
        },

        isEmpty() {
            return !this.loading && !this.errorState && this.filteredItems.length === 0
        },

        canMutate() {
            return this.selected.length > 0 && !this.saving
        },
    },

    mounted() {
        this.load()
    },

    methods: {
        async load() {
            const requestId = ++this.requestSequence
            this.loading = true
            this.errorState = null

            try {
                const response = await fetchDatabaseChanges({
                    stage: this.filter,
                    type: this.type || undefined,
                })

                if (requestId !== this.requestSequence) return

                this.items = response.data.items || []
                this.summary = { ...emptySummary(), ...(response.data.summary || {}) }
                this.selected = this.selected.filter((id) => this.items.some((item) => item.id === id))
            } catch (error) {
                if (requestId === this.requestSequence) {
                    this.errorState = normalizeApiError(error)
                }
            } finally {
                if (requestId === this.requestSequence) this.loading = false
            }
        },

        retry() {
            this.load()
        },

        toggleAll() {
            this.selected = this.allSelected
                ? []
                : this.filteredItems.map((item) => item.id)
        },

        toggle(id) {
            this.selected = this.selected.includes(id)
                ? this.selected.filter((item) => item !== id)
                : [...this.selected, id]
        },

        async copyToClipboard(text) {
            if (!text) return false
            try {
                if (navigator?.clipboard?.writeText) {
                    await navigator.clipboard.writeText(text)
                    return true
                }
            } catch {
                // Lanjut ke fallback document.execCommand
            }

            try {
                const textarea = document.createElement('textarea')
                textarea.value = text
                textarea.style.position = 'fixed'
                textarea.style.left = '-9999px'
                textarea.style.top = '-9999px'
                textarea.style.opacity = '0'
                document.body.appendChild(textarea)
                textarea.focus()
                textarea.select()
                const ok = document.execCommand('copy')
                document.body.removeChild(textarea)
                return ok
            } catch {
                return false
            }
        },

        async copySql(item) {
            this.errorState = null
            try {
                const response = await fetchDatabaseChangeSql(item.id)
                const sqlText = response.data?.content || ''
                const copied = await this.copyToClipboard(sqlText)
                if (copied) {
                    this.showMessage(`SQL ${item.change_id} sudah disalin.`)
                } else {
                    this.showMessage(`SQL ${item.change_id} gagal disalin ke clipboard otomatis.`)
                }
            } catch (error) {
                this.errorState = normalizeApiError(error)
            }
        },

        async copySelectedSql() {
            if (!this.selected.length || this.copyingBulk) return

            this.errorState = null
            this.copyingBulk = true
            try {
                const response = await fetchDatabaseChangesBulkSql(this.selected)
                const sqlText = response.data?.content || ''
                await this.copyToClipboard(sqlText)

                const dilewati = response.data?.skipped || []
                const total = response.data?.total || 0
                const pesan = `${total} changeset disalin urut sesuai urutan pendaftaran.`

                this.showMessage(dilewati.length
                    ? `${pesan} ${dilewati.length} dilewati karena berkasnya tidak terbaca: ${dilewati.map((d) => d.change_id).join(', ')}.`
                    : pesan)
            } catch (error) {
                this.errorState = normalizeApiError(error)
            } finally {
                this.copyingBulk = false
            }
        },

        async updateStatus(ids, stage, status = 'DONE', reference = null) {
            if (!ids.length || this.saving) return

            this.saving = true
            this.errorState = null
            try {
                const payload = { stage, status, reference }
                if (ids.length === 1) {
                    await updateDatabaseChangeStatus(ids[0], payload)
                } else {
                    await updateDatabaseChangesBatch({ ids, ...payload })
                }

                this.selected = this.selected.filter((id) => !ids.includes(id))
                this.trackerReference = ''
                this.showMessage(`${ids.length} change berhasil diperbarui.`)
                await this.load()
            } catch (error) {
                this.errorState = normalizeApiError(error)
            } finally {
                this.saving = false
            }
        },

        markTracker() {
            this.updateStatus(this.selected, 'sql_tracker', 'RECORDED', this.trackerReference || null)
        },

        markProduction() {
            this.updateStatus(this.selected, 'production', 'DONE')
        },

        markVerification() {
            this.updateStatus(this.selected, 'verification', 'DONE')
        },

        showMessage(message) {
            this.message = message
            window.clearTimeout(this.messageTimer)
            this.messageTimer = window.setTimeout(() => { this.message = '' }, 2500)
        },

        statusClass(status) {
            return `status status--${String(status || '').toLowerCase().replaceAll('_', '-')}`
        },
    },

    beforeUnmount() {
        window.clearTimeout(this.messageTimer)
    },
}
</script>

<template>
    <div class="database-change-page">
        <div class="page-heading">
            <div>
                <p class="eyebrow">SYSTEM / Database Changes</p>
                <h1>Database Change Dashboard</h1>
                <p class="subtitle">Pantau DDL dan DML dari development sampai SQL Tracker dan production.</p>
            </div>
            <button class="button button--secondary" type="button" :disabled="loading" @click="load">Refresh</button>
        </div>

        <div class="summary-grid">
            <div class="summary-card"><span>Total change</span><strong>{{ summary.total }}</strong></div>
            <div class="summary-card"><span>Development selesai</span><strong>{{ summary.development_done }}</strong></div>
            <div class="summary-card summary-card--warning"><span>Tracker pending</span><strong>{{ summary.tracker_pending }}</strong></div>
            <div class="summary-card"><span>Production pending</span><strong>{{ summary.production_pending }}</strong></div>
            <div class="summary-card summary-card--success"><span>Completed</span><strong>{{ summary.completed }}</strong></div>
        </div>

        <div class="toolbar">
            <label class="select-all"><input type="checkbox" :checked="allSelected" :disabled="loading || !filteredItems.length" @change="toggleAll"> Pilih semua</label>
            <select v-model="filter" class="control" :disabled="loading" @change="load"><option value="action_required">Action required</option><option value="tracker_pending">SQL Tracker pending</option><option value="production_pending">Production pending</option><option value="completed">Completed</option><option value="">Semua</option></select>
            <select v-model="type" class="control" :disabled="loading" @change="load"><option value="">Semua jenis</option><option value="DDL">DDL</option><option value="DML">DML</option></select>
            <span class="selection-count">{{ items.length }} changeset</span>
        </div>

        <!--
            Aksi terhadap pilihan MELAYANG di bawah-tengah supaya tetap
            terjangkau saat daftarnya panjang.

            Di-Teleport ke elemen body dengan sengaja: leluhur ber-transform,
            filter, atau animation membuat position:fixed berhenti di kotak
            leluhur itu alih-alih di viewport — dan gejalanya bukan galat,
            melainkan bar yang mendarat di tempat yang salah. Teleport
            menghilangkan pertanyaan itu sama sekali.
        -->
        <Teleport to="body">
            <div v-if="selected.length" class="action-dock">
                <span class="action-dock__count">{{ selected.length }} dipilih</span>
                <button class="button button--copy" :disabled="copyingBulk" type="button" @click="copySelectedSql">{{ copyingBulk ? 'Menyalin…' : 'Salin SQL terpilih' }}</button>
                <input v-model="trackerReference" class="control tracker-reference" placeholder="SQL Tracker reference" :disabled="!canMutate">
                <button class="button button--primary" :disabled="!canMutate" type="button" @click="markTracker">Tandai Tracker Recorded</button>
                <button class="button button--secondary" :disabled="!canMutate" type="button" @click="markProduction">Tandai Production Done</button>
                <button class="button button--secondary" :disabled="!canMutate" type="button" @click="markVerification">Tandai Verification Done</button>
                <button class="button button--ghost" type="button" @click="selected = []">Batal pilih</button>
            </div>
        </Teleport>

        <p v-if="message" class="notice notice--success" role="status">{{ message }}</p>
        <div v-if="loading" class="state-panel" role="status" aria-live="polite">Memuat perubahan database...</div>
        <div v-else-if="errorState" class="state-panel state-panel--error" role="alert">
            <strong>{{ errorState.code === 'FORBIDDEN' ? 'Akses tidak tersedia' : 'Terjadi kendala' }}</strong>
            <p>{{ errorState.message }}</p>
            <button v-if="errorState.action === 'retry'" class="button button--secondary" type="button" @click="retry">Coba lagi</button>
            <button v-else-if="errorState.action === 'refresh'" class="button button--secondary" type="button" @click="retry">Muat ulang</button>
            <small v-if="errorState.requestId">ID laporan: {{ errorState.requestId }}</small>
        </div>
        <div v-else-if="isEmpty" class="state-panel">Tidak ada database change pada filter ini.</div>

        <div v-else class="change-list">
            <article v-for="item in filteredItems" :key="item.id" class="change-card">
                <div class="change-card__topline"><label class="checkbox-label"><input type="checkbox" :checked="selected.includes(item.id)" @change="toggle(item.id)"> Pilih</label><span class="change-type">{{ item.type }}</span></div>
                <div class="change-card__header"><div><h2><span class="change-seq" title="Urutan pendaftaran — inilah urutan aman untuk dieksekusi, bukan nomor pada kodenya">#{{ item.sequence }}</span> {{ item.change_id }}</h2><p>{{ item.title }}</p><small class="change-created">Dibuat {{ item.created_at || '-' }}</small></div><button class="button button--copy" type="button" @click="copySql(item)">Copy SQL</button></div>
                <p v-if="item.description" class="description">{{ item.description }}</p>
                <div class="metadata"><span>Target: {{ item.target_development || 'Development' }}</span><span>{{ item.artifact_path }}</span></div>
                <div class="status-grid"><div><span>Development</span><strong :class="statusClass(item.development)">{{ item.development }}</strong></div><div><span>SQL Tracker</span><strong :class="statusClass(item.sql_tracker)">{{ item.sql_tracker }}</strong><small v-if="item.sql_tracker_reference">{{ item.sql_tracker_reference }}</small></div><div><span>Production</span><strong :class="statusClass(item.production)">{{ item.production }}</strong></div><div><span>Verification</span><strong :class="statusClass(item.verification)">{{ item.verification }}</strong></div></div>
            </article>
        </div>
    </div>
</template>

<style scoped>
.database-change-page { max-width: 1500px; margin: 0 auto; padding: 2rem; color: #1f2937; }
.page-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; }.eyebrow { margin: 0 0 .35rem; color: var(--hcis-slate-500); font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }h1 { margin: 0; font-size: clamp(1.6rem, 3vw, 2.4rem); }.subtitle { margin: .5rem 0 0; color: var(--hcis-slate-500); }
.summary-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }.summary-card { border: 1px solid var(--hcis-slate-200); border-radius: .8rem; padding: 1rem; background: #fff; box-shadow: 0 4px 16px rgb(15 23 42 / 5%); }.summary-card span, .status-grid span { display: block; color: var(--hcis-slate-500); font-size: .78rem; }.summary-card strong { display: block; margin-top: .4rem; font-size: 1.7rem; }.summary-card--warning { border-color: #fed7aa; background: #fffaf3; }.summary-card--success { border-color: var(--hcis-green-200); background: #f4fff7; }
.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .65rem; margin-bottom: 1rem; }.control, .button { min-height: 2.5rem; border: 1px solid var(--hcis-slate-300); border-radius: .5rem; padding: .55rem .8rem; font: inherit; background: #fff; }.tracker-reference { min-width: 190px; }.button { cursor: pointer; font-weight: 650; }.button:disabled { cursor: not-allowed; opacity: .5; }.button--primary { border-color: var(--hcis-blue-600); background: var(--hcis-blue-600); color: #fff; }.button--secondary { background: var(--hcis-slate-50); }.button--copy { border-color: var(--hcis-indigo-200); background: var(--hcis-indigo-50); color: var(--hcis-indigo-800); white-space: nowrap; }.selection-count { margin-left: auto; color: var(--hcis-slate-500); font-size: .9rem; }.select-all { display: inline-flex; align-items: center; gap: .35rem; white-space: nowrap; }
.notice, .state-panel { border-radius: .5rem; padding: .85rem 1rem; }.notice--success { background: var(--hcis-green-50); color: #166534; }.state-panel { margin-bottom: 1rem; background: var(--hcis-slate-50); color: var(--hcis-slate-600); }.state-panel--error { background: var(--hcis-red-50); color: var(--hcis-red-800); }.state-panel p { margin: .35rem 0 .75rem; }.state-panel small { display: block; margin-top: .75rem; }.change-list { display: grid; gap: 1rem; }.change-card { border: 1px solid var(--hcis-slate-200); border-radius: .8rem; padding: 1.1rem; background: #fff; box-shadow: 0 4px 16px rgb(15 23 42 / 4%); }.change-card__topline, .change-card__header, .metadata { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }.change-card__topline { margin-bottom: .8rem; }.change-card h2 { margin: 0; font-size: 1rem; color: var(--hcis-slate-900); }.change-card__header p { margin: .3rem 0 0; color: var(--hcis-slate-600); }.change-type { border-radius: 999px; padding: .25rem .55rem; background: var(--hcis-slate-100); color: var(--hcis-slate-600); font-size: .75rem; font-weight: 700; }.description { color: var(--hcis-slate-600); }.metadata { flex-wrap: wrap; margin: 1rem 0; color: var(--hcis-slate-500); font-size: .78rem; }.status-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }.status-grid > div { border-top: 1px solid var(--hcis-slate-200); padding-top: .7rem; }.status { display: inline-block; margin-top: .3rem; border-radius: 999px; padding: .2rem .5rem; font-size: .72rem; letter-spacing: .02em; }.status--done, .status--recorded, .status--dev-done { background: var(--hcis-green-100); color: #166534; }.status--pending { background: var(--hcis-orange-100); color: #9a3412; }.status--failed { background: var(--hcis-red-100); color: var(--hcis-red-800); }.status-grid small { display: block; margin-top: .25rem; color: var(--hcis-slate-500); }
@media (max-width: 992px) { .summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }.status-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }@media (max-width: 640px) { .database-change-page { padding: 1rem; }.page-heading { flex-direction: column; }.summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.selection-count { margin-left: 0; width: 100%; }.change-card__header, .metadata { align-items: flex-start; flex-direction: column; }.status-grid { grid-template-columns: 1fr; }.toolbar > * { width: 100%; } }

.change-seq {
    display: inline-block;
    padding: 0.05rem 0.35rem;
    margin-right: 0.35rem;
    border-radius: 6px;
    background: var(--hcis-slate-900);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    vertical-align: middle;
    cursor: help;
}

.change-created {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.7rem;
    color: var(--hcis-slate-500);
    font-variant-numeric: tabular-nums;
}

.action-dock {
    position: fixed;
    left: 50%;
    bottom: 1.25rem;
    transform: translateX(-50%);
    z-index: 1200;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    max-width: min(1100px, calc(100vw - 2rem));
    padding: 0.7rem 0.9rem;
    border: 1px solid var(--hcis-slate-300);
    border-radius: 0.9rem;
    background: var(--hcis-white);
    box-shadow: 0 12px 32px rgb(15 23 42 / 18%);
}

.action-dock__count {
    font-weight: 700;
    color: var(--hcis-slate-900);
    white-space: nowrap;
}

.action-dock .button--ghost {
    border-color: transparent;
    background: transparent;
    color: var(--hcis-slate-500);
}

/*
 * Ruang bagi dock supaya kartu terakhir tidak tertutup. Tanpa ini, baris
 * paling bawah daftar tidak pernah bisa dibaca utuh - dan itu justru yang
 * paling sering perlu ditindak.
 */
.database-change-page {
    padding-bottom: 7rem;
}

@media (max-width: 640px) {
    .action-dock {
        left: 0.5rem;
        right: 0.5rem;
        bottom: 0.5rem;
        transform: none;
        max-width: none;
    }
}
</style>

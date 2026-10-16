<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import Modal from '@/components/legacy/modalV2-noir.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    detail: {
        type: Object,
        default: null,
    },
    grupId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const error = ref('');
const role = ref(null);
const step = ref(null);
const members = ref([]);
const responseCache = new Map();

const subtitle = computed(() => role.value
    ? `${role.value.code} • ${role.value.member_count} anggota aktif`
    : 'Memuat anggota role approver...');

const initials = (name) => String(name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase();

const formatDate = (value) => {
    if (!value) return 'Tanggal penugasan tidak tersedia';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

const loadMembers = async () => {
    if (!props.detail?.Id) return;
    loading.value = true;
    error.value = '';
    try {
        const detailId = props.detail.Id;
        let data = responseCache.get(detailId);
        if (!data) {
            const response = await axios.get(`/api/v1/menu-approval-matrix/config-details/${detailId}/role-members`, {
                params: { grup_id: props.grupId },
            });
            data = response.data;
            responseCache.set(detailId, data);
        }
        role.value = data.role;
        step.value = data.step;
        members.value = data.members || [];
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Anggota role tidak dapat dimuat.';
        role.value = null;
        step.value = null;
        members.value = [];
    } finally {
        loading.value = false;
    }
};

watch(() => [props.show, props.detail?.Id], loadMembers, { immediate: true });
</script>

<template>
    <Modal
        :show="show"
        :title="role?.name || detail?.Nama_Approval || 'Anggota Role Approver'"
        :subtitle="subtitle"
        size="large"
        :showFooter="false"
        :skeleton="false"
        @close="emit('close')"
    >
        <template #body>
            <div class="role-members-body">
                <div v-if="loading" class="modal-state">
                    <span class="loading-ring" />
                    <strong>Memuat anggota role...</strong>
                    <small>Mengecek seluruh admin aktif yang dapat melakukan approval.</small>
                </div>

                <div v-else-if="error" class="modal-state modal-state-error">
                    <span class="state-icon">!</span>
                    <strong>Data role tidak dapat dimuat</strong>
                    <small>{{ error }}</small>
                </div>

                <template v-else>
                    <div class="role-hero">
                        <div class="role-symbol">R</div>
                        <div class="role-copy">
                            <span>Pool approver berbasis role</span>
                            <h4>{{ role?.name }}</h4>
                            <code>{{ role?.code }}</code>
                        </div>
                        <div class="member-count">
                            <strong>{{ role?.member_count || 0 }}</strong>
                            <span>Admin aktif</span>
                        </div>
                    </div>

                    <div class="role-notice">
                        Pada <strong>posisi {{ step?.order }}: {{ step?.name }}</strong>, salah satu anggota aktif
                        di bawah dapat memproses approval.
                    </div>

                    <div v-if="members.length" class="member-grid">
                        <article v-for="member in members" :key="member.user_id" class="member-card">
                            <div class="member-avatar">{{ initials(member.nama) }}</div>
                            <div class="member-info">
                                <strong>{{ member.nama }}</strong>
                                <span>{{ member.kode }}<template v-if="member.nik"> • NIK {{ member.nik }}</template></span>
                                <small>Ditugaskan {{ formatDate(member.assigned_at) }}</small>
                            </div>
                            <span class="active-badge">Aktif</span>
                        </article>
                    </div>

                    <div v-else class="modal-state">
                        <span class="state-icon">0</span>
                        <strong>Belum ada anggota aktif</strong>
                        <small>Role ini belum memiliki admin aktif yang dapat memproses approval.</small>
                    </div>
                </template>
            </div>
        </template>
    </Modal>
</template>

<style scoped>
.role-members-body {
    min-height: 260px;
}

.role-hero {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border: 1px solid #dbeafe;
    border-radius: 14px;
    background: linear-gradient(135deg, #eff6ff, var(--hcis-slate-50));
}

.role-symbol {
    display: grid;
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    place-items: center;
    border-radius: 13px;
    background: var(--hcis-blue-600);
    color: #fff;
    font-size: 18px;
    font-weight: 900;
    box-shadow: 0 8px 18px rgba(37, 99, 235, .22);
}

.role-copy {
    min-width: 0;
    flex: 1;
}

.role-copy span {
    color: var(--hcis-slate-500);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.role-copy h4 {
    margin: 2px 0 4px;
    color: var(--hcis-slate-900);
    font-size: 16px;
}

.role-copy code {
    color: var(--hcis-blue-600);
    font-size: 11px;
}

.member-count {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    padding-left: 16px;
    border-left: 1px solid #bfdbfe;
}

.member-count strong {
    color: #1d4ed8;
    font-size: 24px;
    line-height: 1;
}

.member-count span {
    margin-top: 4px;
    color: var(--hcis-slate-500);
    font-size: 10px;
    font-weight: 700;
}

.role-notice {
    margin: 12px 0;
    padding: 10px 12px;
    border-left: 3px solid var(--hcis-amber-500);
    border-radius: 8px;
    background: var(--hcis-amber-50);
    color: var(--hcis-amber-800);
    font-size: 11px;
    line-height: 1.5;
}

.member-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 9px;
    max-height: 430px;
    overflow: auto;
    padding: 2px;
}

.member-card {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 10px;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 11px;
    background: #fff;
    transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}

.member-card:hover {
    border-color: #93c5fd;
    box-shadow: 0 7px 18px rgba(37, 99, 235, .1);
    transform: translateY(-1px);
}

.member-avatar {
    display: grid;
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    place-items: center;
    border-radius: 10px;
    background: var(--hcis-indigo-100);
    color: var(--hcis-indigo-800);
    font-size: 11px;
    font-weight: 900;
}

.member-info {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 2px;
}

.member-info strong {
    overflow: hidden;
    color: var(--hcis-slate-900);
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.member-info span,
.member-info small {
    overflow: hidden;
    color: var(--hcis-slate-500);
    font-size: 9px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.active-badge {
    padding: 3px 6px;
    border-radius: 999px;
    background: var(--hcis-green-100);
    color: #166534;
    font-size: 8px;
    font-weight: 800;
}

.modal-state {
    display: flex;
    min-height: 230px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 7px;
    color: var(--hcis-slate-500);
    text-align: center;
}

.modal-state strong {
    color: var(--hcis-slate-700);
    font-size: 13px;
}

.modal-state small {
    max-width: 360px;
    font-size: 10px;
}

.modal-state-error strong,
.modal-state-error small {
    color: var(--hcis-red-700);
}

.state-icon,
.loading-ring {
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border: 2px solid var(--hcis-slate-300);
    border-radius: 50%;
    font-size: 12px;
    font-weight: 900;
}

.loading-ring {
    border-color: #bfdbfe;
    border-top-color: var(--hcis-blue-600);
    animation: spin .8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>

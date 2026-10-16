<template>
    <Modal
        :show="modelValue"
        @update:show="(val) => emit('update:modelValue', val)"
        title="Pusat Notifikasi"
        size="medium"
        :loading="isLoading"
        :skeleton="false"
        @close="closeModal"
        bodyClass="padding-zero"
    >
        <template #body>
            <div class="notification-modal-container">
                <!-- Toolbar Section -->
                <div class="notification-toolbar">
                    <div class="search-box">
                        <i class="bi bi-search search-icon"></i>
                        <input
                            type="text"
                            v-model="searchQuery"
                            @input="onSearchInput"
                            placeholder="Cari pesan atau judul notifikasi..."
                            class="search-input"
                        />
                    </div>

                    <div class="filter-group">
                        <div class="filter-item">
                            <select v-model="filterStatus" @change="fetchNotifications(1)" class="custom-select">
                                <option value="all">Semua Status</option>
                                <option value="unread">Belum Dibaca</option>
                                <option value="read">Sudah Dibaca</option>
                            </select>
                            <i class="bi bi-chevron-down select-icon"></i>
                        </div>

                        <div class="filter-item">
                            <select v-model="filterModule" @change="fetchNotifications(1)" class="custom-select">
                                <option value="">Semua Modul</option>
                                <option value="HCIS">HCIS</option>
                                <option value="KPI">KPI</option>
                                <option value="LMS">LMS</option>
                                <option value="VISITOR">VISITOR</option>
                            </select>
                            <i class="bi bi-chevron-down select-icon"></i>
                        </div>
                    </div>
                </div>

                <!-- Content Section -->
                <div class="notification-content">
                    <!-- Empty State -->
                    <div v-if="!notifications.length && !isLoading" class="empty-state-container">
                        <div class="empty-state-circle">
                            <i class="bi bi-bell-slash"></i>
                        </div>
                        <h4>Belum ada notifikasi</h4>
                        <p>Tidak ada data yang sesuai dengan pencarian atau filter Anda.</p>
                    </div>

                    <!-- Notification List -->
                    <div v-else class="notification-list">
                        <div
                            v-for="item in notifications"
                            :key="item.id"
                            class="notification-card"
                            :class="{
                                'is-read': item.is_read || filterStatus === 'read',
                                'is-clickable': item.action_url,
                            }"
                            @click="handleNotificationClick(item)"
                        >
                            <div class="notification-icon" :class="getModuleTone(item.module)">
                                <i :class="getModuleIcon(item.module)"></i>
                            </div>

                            <div class="notification-body">
                                <div class="notification-header">
                                    <h5 class="notification-title">{{ item.title }}</h5>
                                    <span class="notification-time">
                                        <i class="bi bi-clock me-1"></i>
                                        {{ item.created_at_human }}
                                    </span>
                                </div>
                                <p class="notification-message">{{ item.message }}</p>
                                <div class="notification-meta">
                                    <span class="module-badge" :class="getModuleTone(item.module)">
                                        {{ item.module || 'Sistem' }}
                                    </span>
                                </div>
                            </div>

                            <div class="notification-actions" v-if="!item.is_read && filterStatus !== 'read'">
                                <button class="btn-mark-read" title="Tandai dibaca" @click.stop="markAsRead(item)">
                                    <i class="bi bi-check2-all"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template #footer>
            <div class="notification-footer">
                <div class="pagination-info">
                    <i class="bi bi-list-task me-2"></i>
                    <span
                        >Menampilkan <strong>{{ fromItem }} - {{ toItem }}</strong> dari
                        <strong>{{ totalItems }}</strong></span
                    >
                </div>

                <div class="pagination-controls">
                    <button class="btn-page" :disabled="currentPage === 1" @click="fetchNotifications(currentPage - 1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span class="page-current">Hal {{ currentPage }} / {{ lastPage || 1 }}</span>
                    <button
                        class="btn-page"
                        :disabled="currentPage === lastPage || !lastPage"
                        @click="fetchNotifications(currentPage + 1)"
                    >
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import Modal from '@/components/legacy/modalV2-noir.vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'notification-read', 'close-modal', 'navigate']);

// State
const notifications = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const filterStatus = ref('all');
const filterModule = ref('');

// Pagination
const currentPage = ref(1);
const lastPage = ref(1);
const totalItems = ref(0);
const fromItem = ref(0);
const toItem = ref(0);

// Debounce timer
let searchTimer = null;

watch(
    () => props.modelValue,
    (newVal) => {
        if (newVal) {
            if (notifications.value.length === 0) {
                fetchNotifications(1);
            }
        }
    },
);

function closeModal() {
    emit('update:modelValue', false);
    emit('close-modal');
}

function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        fetchNotifications(1);
    }, 400);
}

async function fetchNotifications(page = 1) {
    if (page < 1 || (lastPage.value && page > lastPage.value && page !== 1)) return;

    isLoading.value = true;
    try {
        const params = {
            page: page,
            limit: 15,
            source: 'MAIN_NOTIF',
        };

        if (searchQuery.value.trim() !== '') {
            params.search = searchQuery.value.trim();
        }

        if (filterStatus.value === 'read') {
            params.is_read = 1;
        } else if (filterStatus.value === 'unread') {
            params.is_read = 0;
        }

        if (filterModule.value !== '') {
            params.module = filterModule.value;
        }

        const response = await axios.get('/homepage/notifications/paginate', { params });

        const data = response.data;
        notifications.value = Array.isArray(data.data) ? data.data : [];
        currentPage.value = data.current_page || 1;
        lastPage.value = data.last_page || 1;
        totalItems.value = data.total || 0;
        fromItem.value = data.from || 0;
        toItem.value = data.to || 0;
    } catch (error) {
        console.error('Failed to fetch paginated notifications:', error);
    } finally {
        isLoading.value = false;
    }
}

async function markAsRead(item) {
    if (item.is_read) return;
    try {
        await axios.post(`/homepage/notifications/${item.id}/read`, null, {
            headers: { 'X-Silent-419': '1' },
        });
        item.is_read = true;
        emit('notification-read', item);
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
}

function handleNotificationClick(item) {
    if (item.action_url) {
        // Picu navigasi dulu (parent yang melakukan router.visit), baru tandai
        // dibaca & tutup modal. Menutup modal lebih dulu meng-unmount baris dan
        // mengganggu visit pertama (gejala: klik pertama terpental, klik kedua jalan).
        emit('navigate', item.action_url);
        if (!item.is_read) {
            markAsRead(item);
        }
        closeModal();
        return;
    }

    if (!item.is_read) {
        markAsRead(item);
    }
}

function getModuleIcon(moduleName) {
    const mod = String(moduleName || '').toUpperCase();
    if (mod === 'HCIS') return 'bi bi-person-workspace';
    if (mod === 'KPI') return 'bi bi-graph-up-arrow';
    if (mod === 'LMS') return 'bi bi-journal-bookmark';
    if (mod === 'VISITOR') return 'bi bi-person-badge';
    return 'bi bi-bell-fill';
}

function getModuleTone(moduleName) {
    const mod = String(moduleName || '').toUpperCase();
    if (mod === 'HCIS') return 'tone-blue';
    if (mod === 'KPI') return 'tone-purple';
    if (mod === 'LMS') return 'tone-orange';
    if (mod === 'VISITOR') return 'tone-teal';
    return 'tone-gray';
}
</script>

<style scoped>
/* Container Variables & Setup */
.notification-modal-container {
    --border: #e2e8f0;
    --surface-soft: #f8fafc;
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #94a3b8;
    --primary: #6366f1;
    --primary-light: #e0e7ff;

    display: flex;
    flex-direction: column;
    height: 100%;
}

/* Toolbar (Search & Filter) */
.notification-toolbar {
    display: flex;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: #ffffff;
    border-bottom: 1px solid var(--border);
    align-items: center;
    flex-wrap: wrap;
    position: sticky;
    top: 0;
    z-index: 5;
}

.search-box {
    position: relative;
    flex: 1;
    min-width: 200px;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1rem;
}

.search-input {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 0.85rem;
    color: var(--text-primary);
    background: var(--surface-soft);
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

.filter-group {
    display: flex;
    gap: 0.75rem;
}

.filter-item {
    position: relative;
}

.custom-select {
    appearance: none;
    padding: 0.6rem 2rem 0.6rem 1rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-secondary);
    background: var(--surface-soft);
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 130px;
}

.custom-select:focus {
    outline: none;
    border-color: var(--primary);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.select-icon {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
    font-size: 0.8rem;
}

/* Content Area */
.notification-content {
    background: var(--surface-soft);
    flex: 1;
    min-height: 350px;
    padding: 1.25rem;
}

/* Empty State */
.empty-state-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
    color: var(--text-muted);
}

.empty-state-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin-bottom: 1.5rem;
    color: #cbd5e1;
}

.empty-state-container h4 {
    color: var(--text-primary);
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.empty-state-container p {
    font-size: 0.95rem;
    max-width: 300px;
}

/* List & Cards */
.notification-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.notification-card {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    gap: 1rem;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
    align-items: flex-start;
}

.notification-card:not(.is-read) {
    border-left: 3px solid var(--primary);
    background: #fcfcfd;
}

.notification-card.is-clickable {
    cursor: pointer;
}

.notification-card.is-clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border-color: #cbd5e1;
}

/* Icons & Tones */
.notification-icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.tone-blue {
    background: #eff6ff;
    color: #3b82f6;
}
.tone-purple {
    background: #faf5ff;
    color: #a855f7;
}
.tone-orange {
    background: #fff7ed;
    color: #f97316;
}
.tone-teal {
    background: #f0fdfa;
    color: #14b8a6;
}
.tone-gray {
    background: #f8fafc;
    color: #64748b;
}

/* Body */
.notification-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
}

.notification-title {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.3;
}

.notification-time {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    flex-shrink: 0;
}

.notification-message {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.4;
}

.notification-meta {
    display: flex;
    align-items: center;
    margin-top: 0.25rem;
}

.module-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    letter-spacing: 0.5px;
}

/* Actions */
.notification-actions {
    display: flex;
    align-items: flex-start;
    padding-left: 0.5rem;
}

.btn-mark-read {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: #ffffff;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-mark-read:hover {
    background: #10b981;
    border-color: #10b981;
    color: #ffffff;
    transform: scale(1.1);
}

/* Footer Pagination */
.notification-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.5rem 0;
}

.pagination-info {
    font-size: 0.85rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
}

.pagination-info strong {
    color: var(--text-primary);
    font-weight: 700;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-current {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-primary);
    min-width: 80px;
    text-align: center;
}

.btn-page {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: #ffffff;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-page:not(:disabled):hover {
    background: var(--surface-soft);
    border-color: #cbd5e1;
    color: var(--text-primary);
}

.btn-page:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: var(--surface-soft);
}

/* Responsiveness */
@media (max-width: 768px) {
    .notification-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .notification-card {
        padding: 1rem;
        flex-direction: column;
        gap: 0.75rem;
    }

    .notification-icon {
        width: 2.5rem;
        height: 2.5rem;
        font-size: 1.25rem;
    }

    .notification-header {
        flex-direction: column;
        gap: 0.25rem;
    }

    .notification-actions {
        position: absolute;
        top: 1rem;
        right: 1rem;
        padding: 0;
    }

    .btn-mark-read {
        width: 2rem;
        height: 2rem;
        font-size: 1rem;
    }

    .notification-footer {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

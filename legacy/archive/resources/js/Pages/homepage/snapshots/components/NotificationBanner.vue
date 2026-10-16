<template>
    <div v-if="visibleNotifications.length > 0" class="notification-banner mb-4">
        <TransitionGroup name="notif" tag="div" class="d-flex flex-column gap-2">
            <div
                v-for="notif in visibleNotifications"
                :key="notif.id"
                class="notif-item stitch-card border-0 shadow-sm rounded-4 p-3 d-flex align-items-center gap-3 overflow-hidden position-relative"
                :class="`notif-${notif.severity}`"
            >
                <!-- Severity Icon -->
                <div
                    class="notif-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0"
                    :class="`notif-icon-${notif.severity}`"
                >
                    <i :class="severityIcon(notif.severity)"></i>
                </div>

                <!-- Content -->
                <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-bold small text-truncate">{{ notif.title }}</div>
                    <div v-if="notif.message" class="text-muted small text-truncate">{{ notif.message }}</div>
                </div>

                <!-- Actions -->
                <div class="d-flex gap-2 flex-shrink-0">
                    <button
                        v-if="notif.action_url"
                        class="btn btn-sm rounded-pill px-3 fw-semibold"
                        :class="`btn-${severityBtnClass(notif.severity)}`"
                        @click="handleAction(notif)"
                    >
                        Lihat
                    </button>
                    <button
                        class="btn btn-sm btn-link text-muted p-0"
                        style="width: 28px; height: 28px;"
                        @click="dismiss(notif.id)"
                        title="Tutup"
                    >
                        <i class="bi bi-x-lg small"></i>
                    </button>
                </div>

                <!-- Left border accent -->
                <div class="notif-accent position-absolute start-0 top-0 bottom-0" :class="`accent-${notif.severity}`"></div>
            </div>
        </TransitionGroup>

        <!-- "View All" link if more notifications exist -->
        <div v-if="notifications.length > maxVisible" class="text-center mt-2">
            <button class="btn btn-sm btn-link text-muted fw-semibold text-decoration-none" @click="$emit('view-all')">
                <i class="bi bi-bell me-1"></i>
                Lihat {{ notifications.length - maxVisible }} notifikasi lainnya
            </button>
        </div>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import axios from 'axios';

export default {
    name: 'NotificationBanner',
    props: {
        notifications: {
            type: Array,
            default: () => [],
        },
        maxVisible: {
            type: Number,
            default: 3,
        },
    },
    emits: ['dismiss', 'action', 'view-all'],
    data() {
        return {
            dismissedIds: [],
            localNotifications: [...this.notifications],
            pollingInterval: null,
        };
    },
    computed: {
        visibleNotifications() {
            return this.localNotifications
                .filter(n => !this.dismissedIds.includes(n.id))
                .slice(0, this.maxVisible);
        },
    },
    mounted() {
        // Start polling every 60 seconds
        this.pollingInterval = setInterval(this.fetchNotifications, 60000);
    },
    beforeUnmount() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
    },
    watch: {
        notifications(newVal) {
            // Update if props change externally
            this.localNotifications = [...newVal];
        }
    },
    methods: {
        async fetchNotifications() {
            try {
                const response = await axios.get(route('homepage.notifications.unread'));
                if (response.data && response.data.data) {
                    this.localNotifications = response.data.data;
                }
            } catch (error) {
                console.error('Failed to fetch notifications', error);
            }
        },
        async dismiss(id) {
            this.dismissedIds.push(id);
            this.$emit('dismiss', id);
            
            try {
                await axios.post(route('homepage.notifications.read', id));
            } catch (error) {
                console.error('Failed to mark notification as read', error);
            }
        },
        handleAction(notif) {
            this.$emit('action', notif);
            if (notif.action_url) {
                router.visit(notif.action_url);
            }
        },
        severityIcon(severity) {
            const icons = {
                info: 'bi bi-info-circle-fill',
                success: 'bi bi-check-circle-fill',
                warning: 'bi bi-exclamation-triangle-fill',
                danger: 'bi bi-exclamation-octagon-fill',
            };
            return icons[severity] || icons.info;
        },
        severityBtnClass(severity) {
            const map = {
                info: 'primary',
                success: 'success',
                warning: 'warning',
                danger: 'danger',
            };
            return map[severity] || 'primary';
        },
    },
};
</script>

<style scoped>
.notif-item {
    transition: all 0.3s ease;
    padding-left: 1.25rem !important;
}

.notif-accent {
    width: 4px;
    border-radius: 4px 0 0 4px;
}

/* Severity Backgrounds */
.notif-info { background: #EFF6FF; }
.notif-success { background: #ECFDF5; }
.notif-warning { background: #FFFBEB; }
.notif-danger { background: #FEF2F2; }

/* Icon Backgrounds */
.notif-icon {
    width: 36px;
    height: 36px;
    font-size: 1rem;
}
.notif-icon-info { background: #BFDBFE; color: #1E40AF; }
.notif-icon-success { background: #A7F3D0; color: #065F46; }
.notif-icon-warning { background: #FDE68A; color: #92400E; }
.notif-icon-danger { background: #FECACA; color: #991B1B; }

/* Accent Colors */
.accent-info { background: #3B82F6; }
.accent-success { background: #10B981; }
.accent-warning { background: #F59E0B; }
.accent-danger { background: #EF4444; }

/* Transition */
.notif-enter-active {
    animation: slideDown 0.3s ease-out;
}
.notif-leave-active {
    animation: slideUp 0.25s ease-in forwards;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); max-height: 0; }
    to { opacity: 1; transform: translateY(0); max-height: 200px; }
}

@keyframes slideUp {
    from { opacity: 1; transform: translateY(0); }
    to { opacity: 0; transform: translateY(-10px); max-height: 0; margin: 0; padding: 0; }
}
</style>

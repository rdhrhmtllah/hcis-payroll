<template>
    <!-- Revision Notes Section -->
    <div class="row" v-if="showRevisionNotes">
        <div class="col-12">
            <div class="revision-notes-wrapper">
                <div @click="toggleRevisionNotes" class="revision-notes-header">
                    <i
                        class="bi bi-exclamation-triangle-fill d-flex justify-content-center align-items-center me-2"
                    ></i>
                    <span class="revision-notes-title"
                        >Revisi Diperlukan
                        {{
                            approverRejected
                                ? `| Approver : ${approverRejected}`
                                : ""
                        }}</span
                    >
                    <button
                        class="revision-notes-toggle"
                        :aria-expanded="isExpanded ? 'true' : 'false'"
                    >
                        <i
                            class="bi d-flex justify-content-center align-items-center"
                            :class="
                                isExpanded ? 'bi-chevron-up' : 'bi-chevron-down'
                            "
                        ></i>
                    </button>
                </div>

                <div
                    class="revision-notes-content"
                    :class="{ expanded: isExpanded }"
                >
                    <div class="revision-notes-body">
                        <p class="revision-message">
                            <i
                                class="bi bi-info-circle d-flex justify-content-center align-items-center"
                            ></i>
                            {{ revisionMessage }}
                        </p>

                        <div
                            class="revision-details"
                            v-if="revisionDetails.length > 0"
                        >
                            <div
                                v-for="(detail, index) in revisionDetails"
                                :key="index"
                                class="revision-detail-item"
                            >
                                <i
                                    class="bi d-flex justify-content-center align-items-center bi-dot"
                                ></i>
                                <span>{{ detail }}</span>
                            </div>
                        </div>

                        <div
                            class="revision-actions"
                            v-if="showRevisionActions"
                        >
                            <button class="btn-revision btn-mark-resolved">
                                <i
                                    class="bi d-flex justify-content-center align-items-center bi-check-lg"
                                ></i>
                                Tandai Selesai
                            </button>
                            <button class="btn-revision btn-view-details">
                                <i
                                    class="bi d-flex justify-content-center align-items-center bi-eye"
                                ></i>
                                Lihat Detail
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "RevisionNotes",
    props: {
        revisionMessage: {
            type: String,
            default: "Ini Peringatan! Perlu revisi pada beberapa bagian form.",
        },
        revisionDetails: {
            type: Array,
            default: () => [],
        },
        showRevisionActions: {
            type: Boolean,
            default: true,
        },
        showRevisionNotes: {
            type: Boolean,
            default: true,
        },
        approverRejected: {
            type: String,
            default: "",
        },
    },
    data() {
        return {
            isExpanded: true,
        };
    },
    methods: {
        toggleRevisionNotes() {
            this.isExpanded = !this.isExpanded;
        },
    },
};
</script>

<style scoped>
/* Revision Notes Styles */
.revision-notes-wrapper {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--shadow);
    margin-bottom: 1.5rem;
    border: 1px solid var(--danger);
    background-color: var(--surface);
}

.revision-notes-header {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    background-color: var(--danger);
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.revision-notes-header:hover {
    background-color: var(--hcis-red-600); /* Slightly darker red */
}

.revision-notes-header i:first-child {
    /* margin-right: 0.5rem; */
    font-size: 1.1rem;
}

.revision-notes-title {
    flex: 1;
    font-size: 0.95rem;
}

.revision-notes-toggle {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
}

.revision-notes-toggle:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

.revision-notes-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.revision-notes-content.expanded {
    max-height: 300px;
}

.revision-notes-body {
    padding: 1rem;
    background-color: var(--hcis-red-50); /* Light red background */
    border-top: 1px solid var(--hcis-red-200); /* Light red border */
}

.revision-message {
    margin: 0 0 0.75rem 0;
    color: var(--text);
    display: flex;
    align-items: flex-start;
    font-size: 0.9rem;
}

.revision-message i {
    color: var(--danger);
    margin-right: 0.5rem;
    margin-top: 0.1rem;
}

.revision-details {
    margin-bottom: 1rem;
}

.revision-detail-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}

.revision-detail-item i {
    color: var(--danger);
    margin-right: 0.5rem;
    margin-top: 0.1rem;
}

.revision-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.btn-revision {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-revision i {
    margin-right: 0.4rem;
    font-size: 0.9rem;
}

.btn-mark-resolved {
    background-color: var(--success);
    color: white;
}

.btn-mark-resolved:hover {
    background-color: #0da271; /* Slightly darker green */
}

.btn-view-details {
    background-color: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
}

.btn-view-details:hover {
    background-color: var(--surface-soft);
}

/* Responsive adjustments */
@media (max-width: 576px) {
    .revision-notes-header {
        padding: 0.6rem 0.75rem;
    }

    .revision-notes-body {
        padding: 0.75rem;
    }

    .revision-actions {
        flex-direction: column;
        gap: 0.5rem;
    }

    .btn-revision {
        justify-content: center;
        width: 100%;
    }
}

/* Animation for smooth appearance */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.revision-notes-wrapper {
    animation: slideDown 0.3s ease;
}
</style>

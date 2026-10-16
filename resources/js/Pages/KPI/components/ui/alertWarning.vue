<template>
    <!-- Kartu 1: notifikasi perlu revisi -->
    <div class="rejection-alert mb-3" role="alert">
        <div class="rejection-alert-icon">
            <i :class="icon"></i>
        </div>
        <div class="rejection-alert-content">
            <h5 v-if="title" class="rejection-alert-title">
                {{ title }}
            </h5>
            <p v-if="description" class="rejection-alert-text">
                {{ description }}
            </p>
        </div>
    </div>

    <!-- Kartu 2: alasan penolakan global (terpisah, hanya bila ada) -->
    <div v-if="reason" class="rejection-reason-card mb-4">
        <div class="rejection-reason-head">
            <i class="bi bi-chat-left-quote-fill"></i>
            <span>Alasan Penolakan</span>
        </div>
        <p class="rejection-reason-body">{{ reason }}</p>
    </div>

    <slot></slot>
</template>

<script>
export default {
    name: "alertWarning",
    props: {
        icon: {
            type: String,
            default: "bi bi-exclamation-triangle-fill",
        },
        title: {
            type: String,
            required: true,
        },
        description: {
            type: String,
            required: true,
        },
        reason: {
            type: String,
            default: null,
        },
    },
};
</script>

<style scoped>
/* ===== Kartu "Perlu Revisi" ===== */
.rejection-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    background-color: var(--hcis-white);
    color: var(--hcis-amber-800);
    padding: 1rem 1.15rem;
    border-radius: var(--border-radius, 0.75rem);
    border: 1px solid var(--hcis-amber-200);
    border-left: 4px solid var(--hcis-amber-500);
}

.rejection-alert-icon {
    flex: 0 0 auto;
    margin-top: 0.1rem;
    font-size: 1.35rem;
    line-height: 1;
    color: var(--hcis-amber-500);
}

.rejection-alert-content {
    min-width: 0;
    flex: 1 1 auto;
}

.rejection-alert-title {
    margin: 0 0 0.2rem 0;
    font-weight: 700;
    font-size: 1rem;
    color: var(--hcis-amber-800);
}

.rejection-alert-text {
    margin: 0;
    font-size: 0.88rem;
    line-height: 1.55;
}

/* ===== Kartu "Alasan Penolakan" (terpisah) ===== */
.rejection-reason-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-amber-200);
    border-left: 4px solid var(--hcis-amber-500);
    border-radius: var(--border-radius, 0.75rem);
    padding: 0.85rem 1.1rem;
}

.rejection-reason-head {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--hcis-amber-700);
    margin-bottom: 0.35rem;
}

.rejection-reason-head i {
    font-size: 0.85rem;
    color: var(--hcis-amber-500);
}

.rejection-reason-body {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.6;
    color: var(--hcis-amber-900);
    /* Cegah teks panjang tanpa spasi merusak layout */
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: pre-line;
}
</style>

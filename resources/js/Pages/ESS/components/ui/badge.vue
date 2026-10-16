<template>
    <span class="badge" :class="badgeClasses" :style="badgeStyles">
        <i v-if="icon" :class="icon" class="badge-icon"></i>
        <slot>{{ text }}</slot>
    </span>
</template>

<script>
export default {
    name: "Badge",
    props: {
        type: {
            type: String,
            default: "default",
            validator: (value) =>
                [
                    "default",
                    "primary",
                    "secondary",
                    "success",
                    "warning",
                    "danger",
                    "info",
                    "light",
                    "dark",
                ].includes(value),
        },
        size: {
            type: String,
            default: "md",
            validator: (value) => ["sm", "md", "lg"].includes(value),
        },
        outline: {
            type: Boolean,
            default: false,
        },
        rounded: {
            type: Boolean,
            default: false,
        },
        icon: {
            type: String,
            default: "",
        },
        text: {
            type: String,
            default: "",
        },
    },
    computed: {
        badgeClasses() {
            return [
                `badge-${this.type}`,
                `badge-${this.size}`,
                {
                    "badge-outline": this.outline,
                    "badge-rounded": this.rounded,
                    "badge-with-icon": this.icon,
                },
            ];
        },
        badgeStyles() {
            const styles = {};
            return styles;
        },
    },
};
</script>

<style scoped>
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    transition: all 0.2s ease;
}

/* Size Variants */
.badge-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.7rem;
    border-radius: 6px;
}

.badge-md {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 8px;
}

.badge-lg {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    border-radius: 10px;
}

/* Type Variants - Solid */
.badge-default {
    background: var(--surface-soft);
    color: var(--text);
    border: 1px solid var(--border);
}

.badge-primary {
    background: var(--primary);
    color: white;
    border: 1px solid var(--primary);
}

.badge-secondary {
    background: var(--secondary);
    color: white;
    border: 1px solid var(--secondary);
}

.badge-success {
    background: var(--success);
    color: white;
    border: 1px solid var(--success);
}

.badge-warning {
    background: var(--warning);
    color: var(--dark);
    border: 1px solid var(--warning);
}

.badge-danger {
    background: var(--danger);
    color: white;
    border: 1px solid var(--danger);
}

.badge-info {
    background: var(--info);
    color: white;
    border: 1px solid var(--info);
}

.badge-light {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--border);
}

.badge-dark {
    background: var(--dark);
    color: white;
    border: 1px solid var(--dark);
}

/* Outline Variants */
.badge-outline.badge-default {
    background: transparent;
    color: var(--text);
    border: 1px solid var(--border);
}

.badge-outline.badge-primary {
    background: transparent;
    color: var(--primary);
    border: 1px solid var(--primary);
}

.badge-outline.badge-secondary {
    background: transparent;
    color: var(--secondary);
    border: 1px solid var(--secondary);
}

.badge-outline.badge-success {
    background: transparent;
    color: var(--success);
    border: 1px solid var(--success);
}

.badge-outline.badge-warning {
    background: transparent;
    color: var(--warning);
    border: 1px solid var(--warning);
}

.badge-outline.badge-danger {
    background: transparent;
    color: var(--danger);
    border: 1px solid var(--danger);
}

.badge-outline.badge-info {
    background: transparent;
    color: var(--info);
    border: 1px solid var(--info);
}

.badge-outline.badge-light {
    background: transparent;
    color: var(--text-muted);
    border: 1px solid var(--border);
}

.badge-outline.badge-dark {
    background: transparent;
    color: var(--dark);
    border: 1px solid var(--dark);
}

/* Rounded Variant */
.badge-rounded {
    border-radius: 50px;
}

/* Icon */
.badge-icon {
    font-size: 0.8em;
}

.badge-with-icon.badge-sm .badge-icon {
    font-size: 0.7em;
}

.badge-with-icon.badge-lg .badge-icon {
    font-size: 0.9em;
}

/* Hover Effects */
.badge:not(.badge-outline):hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.badge-outline:hover {
    background: currentColor;
    color: white;
}

.badge-outline.badge-light:hover {
    background: var(--text-muted);
    color: white;
}

/* Animation for status changes */
.badge {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.8);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Pulse animation for important badges */
.badge-pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(99, 102, 241, 0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .badge-lg {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
    }

    .badge-with-icon {
        gap: 0.25rem;
    }
}
</style>

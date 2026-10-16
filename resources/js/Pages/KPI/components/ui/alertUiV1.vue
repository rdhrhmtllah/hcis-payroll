<template>
    <div :class="alertClasses" class="mb-4">
        <div class="alert-icon">
            <i :class="dynamicIcon"></i>
        </div>
        <div class="alert-content">
            <h5 v-if="title" class="alert-title">
                {{ title }}
            </h5>
            <p v-if="description" class="alert-text">
                {{ description }}
            </p>
        </div>
    </div>
</template>

<script>
export default {
    name: "DynamicAlert",
    props: {
        variant: {
            type: String,
            default: "info",
            validator: (value) =>
                ["info", "success", "warning", "danger"].includes(value),
        },
        icon: {
            type: String,
            default: "",
        },
        title: {
            type: String,
            required: true,
        },
        description: {
            type: String,
            required: true,
        },
    },
    computed: {
        alertClasses() {
            return ["alert", `alert-${this.variant}`];
        },
        dynamicIcon() {
            if (this.icon) {
                return this.icon;
            }

            const icons = {
                info: "bi bi-info-circle-fill",
                success: "bi bi-check-circle-fill",
                warning: "bi bi-exclamation-triangle-fill",
                danger: "bi bi-x-circle-fill",
            };
            return icons[this.variant];
        },
    },
};
</script>

<style scoped>
.alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.25rem;
    border-radius: var(--border-radius, 8px);
    border: 1px solid;
}
.alert-icon {
    font-size: 1.5rem;
    margin-top: 0.125rem;
}
.alert-title {
    margin: 0 0 0.25rem 0;
    font-weight: 700;
    font-size: 1.1rem;
}
.alert-text {
    margin: 0;
    line-height: 1.6;
}

.alert-info {
    background-color: #eff6ff;
    border-color: #bfdbfe;
    color: #1e40af;
}
.alert-info .alert-icon {
    color: var(--hcis-blue-500);
}

.alert-warning {
    background-color: var(--hcis-amber-50);
    border-color: var(--hcis-amber-200);
    color: var(--hcis-amber-800);
}
.alert-warning .alert-icon {
    color: var(--hcis-amber-500);
}

.alert-success {
    background-color: var(--hcis-green-50);
    border-color: var(--hcis-green-200);
    color: #166534;
}
.alert-success .alert-icon {
    color: #22c55e;
}

.alert-danger {
    background-color: var(--hcis-red-50);
    border-color: var(--hcis-red-200);
    color: var(--hcis-red-800);
}
.alert-danger .alert-icon {
    color: var(--hcis-red-500);
}
</style>

<template>
    <div class="checkbox-filter">
        <div class="checkbox-options">
            <label
                v-for="option in filter.options"
                :key="option.id || option.value"
                class="checkbox-option"
                :class="{ 'checkbox-disabled': option.disabled }"
            >
                <input
                    type="checkbox"
                    :value="option.id || option.value"
                    :checked="isChecked(option)"
                    @change="toggleOption(option)"
                    :disabled="option.disabled"
                    class="checkbox-input"
                />
                <span class="checkbox-custom"></span>
                <span class="checkbox-label">
                    {{ option.name || option.label }}
                    <span
                        v-if="option.count !== undefined"
                        class="option-count"
                    >
                        ({{ option.count }})
                    </span>
                </span>
            </label>
        </div>

        <!-- Selected options summary (optional) -->
        <div
            v-if="selectedOptions.length > 0 && showSelectedSummary"
            class="selected-summary"
        >
            <div class="summary-label">Selected:</div>
            <div class="selected-tags">
                <span
                    v-for="option in selectedOptions"
                    :key="option.id || option.value"
                    class="selected-tag"
                    @click="removeOption(option)"
                >
                    {{ option.name || option.label }}
                    <i class="bi bi-x tag-close"></i>
                </span>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "CheckboxFilter",
    props: {
        filter: {
            type: Object,
            required: true,
        },
        value: {
            type: Array,
            default: () => [],
        },
        showSelectedSummary: {
            type: Boolean,
            default: true,
        },
    },
    computed: {
        selectedOptions() {
            return this.filter.options.filter((option) =>
                this.value.includes(option.id || option.value)
            );
        },
    },
    methods: {
        isChecked(option) {
            return this.value.includes(option.id || option.value);
        },

        toggleOption(option) {
            const optionValue = option.id || option.value;
            let newValue = [...this.value];

            if (this.isChecked(option)) {
                // Remove option
                newValue = newValue.filter((item) => item !== optionValue);
            } else {
                // Add option
                newValue.push(optionValue);
            }

            this.$emit("update:value", newValue);
        },

        removeOption(option) {
            const optionValue = option.id || option.value;
            const newValue = this.value.filter((item) => item !== optionValue);
            this.$emit("update:value", newValue);
        },
    },
};
</script>

<style scoped>
.checkbox-filter {
    width: 100%;
}

.checkbox-options {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-height: 200px;
    overflow-y: auto;
    padding: 0.5rem 0;
}

.checkbox-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
}

.checkbox-option:hover {
    background: var(--surface-soft);
}

.checkbox-option.checkbox-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.checkbox-input {
    display: none;
}

.checkbox-custom {
    width: 18px;
    height: 18px;
    border: 2px solid var(--border);
    border-radius: 4px;
    background: var(--surface);
    position: relative;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.checkbox-input:checked + .checkbox-custom {
    background: var(--primary);
    border-color: var(--primary);
}

.checkbox-input:checked + .checkbox-custom::after {
    content: "";
    position: absolute;
    top: 2px;
    left: 5px;
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.checkbox-input:focus + .checkbox-custom {
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.checkbox-label {
    font-size: 0.9rem;
    color: var(--text);
    flex: 1;
}

.option-count {
    color: var(--text-muted);
    font-size: 0.8rem;
    margin-left: 0.25rem;
}

/* Selected Summary */
.selected-summary {
    margin-top: 1rem;
    padding: 0.75rem;
    background: var(--surface-soft);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.summary-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

.selected-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.selected-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.3rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid var(--primary-light);
}

.selected-tag:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-1px);
}

.tag-close {
    font-size: 0.7rem;
    margin-left: 0.25rem;
}

/* Scrollbar Styling */
.checkbox-options::-webkit-scrollbar {
    width: 6px;
}

.checkbox-options::-webkit-scrollbar-track {
    background: var(--surface-soft);
    border-radius: 3px;
}

.checkbox-options::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

.checkbox-options::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

/* Responsive Design */
@media (max-width: 768px) {
    .checkbox-options {
        max-height: 150px;
    }

    .checkbox-option {
        padding: 0.75rem;
    }

    .selected-tags {
        gap: 0.375rem;
    }

    .selected-tag {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }
}
</style>

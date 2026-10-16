<template>
    <div class="date-select-filter">
        <el-date-picker
            v-model="localValue"
            :type="filter.pickerType || 'date'"
            :placeholder="filter.placeholder || 'Select date'"
            :format="filter.format || 'DD/MM/YYYY'"
            :value-format="filter.valueFormat || 'YYYY-MM-DD'"
            :shortcuts="dateShortcuts"
            :disabled-date="filter.disabledDate"
            :clearable="filter.clearable !== false"
            :editable="filter.editable !== false"
            @change="handleChange"
            class="el-date-select-custom"
        />
    </div>
</template>

<script>
import { ElDatePicker } from "element-plus";
import "element-plus/dist/index.css";
export default {
    name: "DateSelectFilter",
    props: {
        filter: {
            type: Object,
            required: true,
        },
        value: {
            type: [String, Date],
            default: "",
        },
    },
    components: {
        ElDatePicker,
    },
    data() {
        return {
            localValue: this.value,
            dateShortcuts: [
                {
                    text: "Today",
                    value: new Date(),
                },
                {
                    text: "Yesterday",
                    value: () => {
                        const date = new Date();
                        date.setTime(date.getTime() - 3600 * 1000 * 24);
                        return date;
                    },
                },
                {
                    text: "A week ago",
                    value: () => {
                        const date = new Date();
                        date.setTime(date.getTime() - 3600 * 1000 * 24 * 7);
                        return date;
                    },
                },
                {
                    text: "A month ago",
                    value: () => {
                        const date = new Date();
                        date.setTime(date.getTime() - 3600 * 1000 * 24 * 30);
                        return date;
                    },
                },
                {
                    text: "Start of month",
                    value: () => {
                        return new Date(
                            new Date().getFullYear(),
                            new Date().getMonth(),
                            1
                        );
                    },
                },
                {
                    text: "Start of year",
                    value: () => {
                        return new Date(new Date().getFullYear(), 0, 1);
                    },
                },
            ],
        };
    },
    watch: {
        value(newVal) {
            this.localValue = newVal;
        },
    },
    methods: {
        handleChange(value) {
            this.$emit("update:value", value || "");
        },
    },
};
</script>

<style scoped>
.date-select-filter {
    width: 100%;
}

:deep(.el-date-select-custom) {
    width: 100% !important;
}

/* Custom styling untuk Element Plus Date Picker */
:deep(.el-date-select-custom .el-input__inner) {
    height: 44px !important;
    border: 2px solid var(--border) !important;
    border-radius: 10px !important;
    background: var(--surface) !important;
    color: var(--text) !important;
    font-size: 0.9rem !important;
    padding-left: 12px !important;
    padding-right: 12px !important;
}

:deep(.el-date-select-custom .el-input__inner:focus) {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
}

:deep(.el-date-select-custom .el-input__prefix) {
    left: auto !important;
    right: 8px !important;
    color: var(--text-muted) !important;
}

:deep(.el-date-select-custom .el-input__suffix) {
    display: none !important;
}

:deep(.el-date-select-custom .el-input__icon) {
    display: none !important;
}

/* Month/Year Picker Styles */
:deep(.el-date-select-custom.el-date-picker--month .el-picker-panel__content) {
    width: 100% !important;
}

:deep(.el-date-select-custom.el-date-picker--year .el-picker-panel__content) {
    width: 100% !important;
}

:deep(.el-month-table td .cell) {
    color: var(--text) !important;
}

:deep(.el-month-table td.current:not(.disabled) .cell) {
    color: var(--primary) !important;
}

:deep(.el-year-table td .cell) {
    color: var(--text) !important;
}

:deep(.el-year-table td.current:not(.disabled) .cell) {
    color: var(--primary) !important;
}

/* Responsive */
@media (max-width: 768px) {
    :deep(.el-picker-panel) {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        width: 90% !important;
        max-width: 300px !important;
    }
}
</style>

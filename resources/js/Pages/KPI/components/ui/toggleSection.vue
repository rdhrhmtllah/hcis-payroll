<template>
    <div class="approval-tabs-container">
        <!-- Desktop Tabs -->
        <div v-if="!isMobile" class="desktop-tabs">
            <div class="tabs-header">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    class="tab-button"
                    :class="{ active: activeTab === tab.id }"
                    @click="setActiveTab(tab.id)"
                >
                    <i :class="tab.icon"></i>
                    <span>{{ tab.label }}</span>
                    <span class="tab-badge" v-if="tab.count !== null">
                        {{ tab.count }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Mobile Tabs -->
        <div v-else class="mobile-tabs">
            <select
                class="mobile-tab-select"
                :value="activeTab"
                @change="setActiveTab($event.target.value)"
            >
                <option v-for="tab in tabs" :key="tab.id" :value="tab.id">
                    {{ tab.label }} ({{ tab.count !== null ? tab.count : 0 }})
                </option>
            </select>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <slot :name="activeTab"></slot>
        </div>
    </div>
</template>

<script>
export default {
    name: "ApprovalTabs",
    props: {
        tabs: {
            type: Array,
            required: true,
            validator: (tabs) => {
                return tabs.every((tab) => tab.id && tab.label && tab.icon);
            },
        },
        initialTab: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            activeTab:
                this.initialTab ||
                (this.tabs.length > 0 ? this.tabs[0].id : ""),
            isMobile: window.innerWidth < 768,
        };
    },
    methods: {
        setActiveTab(tabId) {
            this.activeTab = tabId;
            this.$emit("tab-change", tabId);
        },
        handleResize() {
            this.isMobile = window.innerWidth < 768;
        },
    },
    mounted() {
        window.addEventListener("resize", this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.handleResize);
    },
    watch: {
        tabs: {
            immediate: true,
            handler(newTabs) {
                if (newTabs.length > 0 && !this.activeTab) {
                    this.activeTab = newTabs[0].id;
                }
            },
        },
    },
};
</script>

<style scoped>
 *,
*::before,
*::after{
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-cyan-500);
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --radius-lg: 16px;
    --transition: all 0.3s ease;
}
.approval-tabs-container {
    background: var(--surface);
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

/* Desktop Tabs */
.desktop-tabs {
    border-bottom: 1px solid var(--border);
}

.tabs-header {
    display: flex;
    background: var(--surface-soft);
    padding: 0.5rem;
    gap: 0.25rem;
}

.tab-button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border: none;
    background: transparent;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    color: var(--text-muted);
    position: relative;
    white-space: nowrap;
}

.tab-button:hover {
    background: rgba(99, 102, 241, 0.05);
    color: var(--primary);
}

.tab-button.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}

.tab-button i {
    font-size: 1rem;
    transition: transform 0.3s ease;
}

.tab-button.active i {
    transform: scale(1.1);
}

.tab-badge {
    background: rgba(255, 255, 255, 0.2);
    color: inherit;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 24px;
    text-align: center;
}

.tab-button:not(.active) .tab-badge {
    background: var(--surface);
    color: var(--text-muted);
    border: 1px solid var(--border);
}

/* Mobile Tabs */
.mobile-tabs {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface-soft);
}

.mobile-tab-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text);
    font-weight: 500;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236466f1'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1rem;
}

.mobile-tab-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Tab Content */
.tab-content {
    padding: 1.5rem;
    background: var(--surface);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .tab-button {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }

    .tab-button i {
        font-size: 0.9rem;
    }
}

@media (max-width: 768px) {
    .approval-tabs-container {
        border-radius: 12px;
        margin-bottom: 1rem;
    }

    .tab-content {
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    .tab-content {
        padding: 0.75rem;
    }
}

/* Animation */
.tab-button {
    animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

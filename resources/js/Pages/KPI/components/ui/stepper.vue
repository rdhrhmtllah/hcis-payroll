<template>
    <div class="modern-stepper-container" :style="componentStyles">
        <div class="modern-stepper">
            <div class="stepper-line" :style="lineStyle">
                <div
                    class="stepper-line-progress"
                    :style="{ width: progressWidth }"
                ></div>
            </div>
            <div
                v-for="step in steps"
                :key="step.id"
                class="step"
                :class="getStepClass(step.id)"
                @click="handleStepClick(step.id)"
            >
                <div class="step-circle">
                    <i
                        v-if="isStepCompleted(step.id)"
                        class="bi bi-check-lg mt-fransdev"
                    ></i>
                    <span v-else>{{ step.id }}</span>
                </div>
                <div class="step-label">
                    <div class="step-title">{{ step.title }}</div>
                    <div class="step-description">{{ step.description }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "stepper",
    props: {
        steps: {
            type: Array,
            required: true,
        },
        lineMargin: {
            type: String,
            default: null,
        },
        currentStep: {
            type: Number,
            required: true,
        },
        activeColor: {
            type: String,
            default: null,
        },
        completedColor: {
            type: String,
            default: null,
        },
        inactiveColor: {
            type: String,
            default: null,
        },
        lineGradientFrom: {
            type: String,
            default: null,
        },
        lineGradientTo: {
            type: String,
            default: null,
        },
        isCompleted: {
            type: Boolean,
            default: false,
        },
    },
    emits: ["update:currentStep"],
    computed: {
        totalSteps() {
            return this.steps.length;
        },
        progressWidth() {
            if (this.isCompleted) {
                return "100%";
            }
            if (this.totalSteps <= 1) return "0%";
            const progress =
                ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
            return `${progress}%`;
        },
        lineStyle() {
            if (this.lineMargin) {
                return { margin: `0 ${this.lineMargin}` };
            }

            if (this.totalSteps <= 1) return { display: "none" };
            const marginPercentage = (1 / this.totalSteps) * 50;
            return { margin: `0 ${marginPercentage}%` };
        },
        componentStyles() {
            return {
                "--active-color": this.activeColor,
                "--completed-color": this.completedColor,
                "--inactive-color": this.inactiveColor,
                "--line-gradient-from": this.lineGradientFrom,
                "--line-gradient-to": this.lineGradientTo,
            };
        },
    },
    methods: {
        getStepClass(stepId) {
            if (this.isCompleted) {
                return "is-completed";
            }
            if (stepId === this.currentStep) return "is-active";
            if (stepId < this.currentStep) return "is-completed";
            return "is-upcoming";
        },

        isStepCompleted(stepId) {
            return this.getStepClass(stepId) === "is-completed";
        },

        handleStepClick(stepId) {
            if (this.isCompleted) {
                return;
            }
            if (stepId < this.currentStep) {
                this.$emit("update:currentStep", stepId);
            }
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary-color: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-100);
    --success-color: var(--hcis-emerald-500);
    --gray-200: #e5e7eb;
    --gray-600: #4b5563;
    --text-color: #374151;
    --white-color: var(--hcis-white);
    --border-radius: 0.75rem;
    --transition-speed: 0.3s;
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}

.mt-fransdev {
    margin-top: -10px;
}

.modern-stepper-container {
    background-color: var(--white-color);
    padding: 1.5rem 2rem;
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
    margin-bottom: 2rem;
}
.modern-stepper {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    position: relative;
}
.stepper-line {
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 4px;
    background-color: var(--gray-200);
    z-index: 0;
}
.stepper-line-progress {
    height: 100%;
    width: 0;
    background: linear-gradient(
        135deg,
        var(--line-gradient-from, var(--hcis-legacy-violet-400)) 0%,
        var(--line-gradient-to, var(--hcis-legacy-violet-700)) 100%
    );
    transition: width var(--transition-speed) ease-in-out;
}
.step {
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-align: left;
    flex: 1;
    justify-content: center;
}
.step-circle {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 700;
    transition: all var(--transition-speed) ease;
    border: 3px solid var(--inactive-color, #e5e7eb);
    background-color: var(--white-color);
}
.step-title {
    margin-top: -6px;
    font-weight: 700;
    color: var(--text-color);
    line-height: 30px;
}
.step-description {
    font-size: 0.875rem;
    color: var(--gray-600);
}
.step.is-upcoming {
    opacity: 0.6;
}
.step.is-active .step-circle {
    border-color: var(--active-color, var(--hcis-indigo-500));
    background-color: color-mix(
        in srgb,
        var(--active-color, var(--hcis-indigo-500)) 20%,
        var(--hcis-white)
    );
    color: var(--active-color, var(--hcis-indigo-600));
}
.step.is-active .step-title {
    color: var(--active-color, var(--hcis-indigo-600));
}
.step.is-completed {
    cursor: pointer;
}

.mt-customByFransIcon {
    margin-top: -10px;
}
.step.is-completed .step-circle {
    border-color: var(--completed-color, var(--hcis-emerald-500));
    background-color: var(--completed-color, var(--hcis-emerald-500));
    color: var(--hcis-white);
}
.content-card {
    background: var(--white-color);
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
}
.step-content-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
    padding-bottom: 1.5rem;
    gap: 1rem;
}
.step-content-header h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-color);
    margin: 0;
}
.step-content-header p {
    color: var(--gray-600);
    margin: 0.25rem 0 0 0;
}
.step-badge {
    background-color: var(--primary-light);
    color: var(--primary-dark);
    padding: 0.4rem 0.8rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}
.action-bar {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--gray-200);
}
.action-bar .spacer {
    flex-grow: 1;
}
/* ======================================================= */
/* RESPONSIVE DESIGN                                       */
/* ======================================================= */
@media (max-width: 991.98px) {
    .modern-stepper {
        flex-direction: column;
        align-items: stretch;
        gap: 1.5rem;
    }

    .stepper-line {
        display: none;
    }

    .step {
        justify-content: flex-start;
        gap: 1rem;
    }

    .step-label {
        padding-top: 0;
        text-align: left;
    }
}

@media (max-width: 576px) {
    .step-description {
        display: none; /* Sembunyikan deskripsi di layar sangat kecil */
    }
    .modern-stepper-container {
        padding: 1.5rem 1rem;
    }
}
</style>

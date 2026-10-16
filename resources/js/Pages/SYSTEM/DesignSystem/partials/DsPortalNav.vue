<template>
    <header class="ds-portal-nav">
        <div class="ds-portal-nav__inner">
            <!-- Brand & Version -->
            <div class="ds-portal-nav__brand">
                <Link href="/design-system" class="ds-portal-nav__logo">
                    <div class="ds-portal-nav__icon-wrap">
                        <HcisIcon name="grid-3x3-gap-fill" size="sm" />
                    </div>
                    <div class="ds-portal-nav__titles">
                        <span class="ds-portal-nav__title">HCIS Design System</span>
                        <span class="ds-portal-nav__version">v2.0 Native Enterprise</span>
                    </div>
                </Link>
            </div>

            <!-- Primary Navigation Links -->
            <nav class="ds-portal-nav__links" aria-label="Navigasi Portal Design System">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="ds-portal-nav__link"
                    :class="{ 'is-active': isCurrentPath(item.href) }"
                >
                    <HcisIcon :name="item.icon" size="xs" />
                    <span>{{ item.label }}</span>
                    <span v-if="item.badge" class="ds-portal-nav__badge">{{ item.badge }}</span>
                </Link>
            </nav>

            <!-- Quick Actions -->
            <div class="ds-portal-nav__actions">
                <div class="ds-portal-nav__search">
                    <HcisIcon name="search" size="xs" class="ds-portal-nav__search-icon" />
                    <select
                        id="portal-component-jump"
                        class="ds-portal-nav__select"
                        :value="activeSlug || ''"
                        @change="onJump"
                        aria-label="Pindah langsung ke komponen"
                    >
                        <option value="" disabled>Lompat ke komponen…</option>
                        <option v-for="c in sortedRegistry" :key="c.slug" :value="c.slug">
                            {{ c.name }} — {{ c.group || 'Umum' }}
                        </option>
                    </select>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { HcisIcon } from '@/components/ui';
import { registry } from '@/components/ui/registry';

const props = defineProps({
    activeSlug: {
        type: String,
        default: '',
    },
});

const page = usePage();

const navItems = [
    { href: '/design-system', label: 'Katalog Komponen', icon: 'puzzle' },
    { href: '/design-system/fondasi', label: 'Fondasi & Token', icon: 'palette' },
    { href: '/design-system/pola', label: 'Pola Halaman', icon: 'layout-wtf', badge: '5 Pola' },
    { href: '/design-system/panduan', label: 'Panduan UX & Feedback', icon: 'journal-check' },
    { href: '/design-system/migrasi', label: 'Peta Migrasi Legacy', icon: 'arrow-repeat' },
];

const sortedRegistry = computed(() => {
    return [...registry].sort((a, b) => a.name.localeCompare(b.name));
});

function isCurrentPath(targetHref) {
    const currentUrl = page.url || '';
    if (targetHref === '/design-system') {
        return currentUrl === '/design-system' || currentUrl.startsWith('/design-system?');
    }
    return currentUrl.startsWith(targetHref);
}

function onJump(e) {
    const slug = e.target.value;
    if (slug) {
        router.visit(`/design-system/${slug}`);
    }
}
</script>

<style scoped>
.ds-portal-nav {
    position: sticky;
    top: 0;
    z-index: 40;
    margin-bottom: var(--hcis-space-6);
    background: rgb(var(--hcis-white-rgb) / 0.85);
    backdrop-filter: blur(var(--hcis-glass-blur));
    -webkit-backdrop-filter: blur(var(--hcis-glass-blur));
    border-bottom: 1px solid var(--hcis-border);
    box-shadow: 0 4px 20px -2px rgb(var(--hcis-shadow-rgb) / 0.05);
}

.ds-portal-nav__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--hcis-space-4);
    padding: var(--hcis-space-3) var(--hcis-space-6);
    max-width: 100%;
}

.ds-portal-nav__brand {
    display: flex;
    align-items: center;
}

.ds-portal-nav__logo {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3);
    text-decoration: none;
    color: var(--hcis-text-strong);
}

.ds-portal-nav__icon-wrap {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: var(--hcis-radius-md);
    background: linear-gradient(135deg, var(--hcis-brand-500) 0%, var(--hcis-brand-700) 100%);
    color: var(--hcis-white);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgb(var(--hcis-brand-500-rgb) / 0.3);
}

.ds-portal-nav__titles {
    display: flex;
    flex-direction: column;
}

.ds-portal-nav__title {
    font-size: var(--hcis-font-size-sm);
    font-weight: var(--hcis-weight-black);
    color: var(--hcis-text-strong);
    line-height: 1.2;
}

.ds-portal-nav__version {
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-bold);
    color: var(--hcis-brand-600);
    text-transform: uppercase;
    letter-spacing: var(--hcis-tracking-wide);
}

.ds-portal-nav__links {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-1);
    background: var(--hcis-surface-soft);
    padding: 3px;
    border-radius: var(--hcis-radius-lg);
    border: 1px solid var(--hcis-border);
}

.ds-portal-nav__link {
    display: inline-flex;
    align-items: center;
    gap: var(--hcis-space-2);
    padding: var(--hcis-space-2) var(--hcis-space-3-5);
    border-radius: var(--hcis-radius-md);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-bold);
    color: var(--hcis-text-muted);
    text-decoration: none;
    transition: var(--hcis-transition-props) var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-portal-nav__link:hover {
    color: var(--hcis-brand-600);
    background: rgb(var(--hcis-white-rgb) / 0.8);
}

.ds-portal-nav__link.is-active {
    color: var(--hcis-brand-700);
    background: var(--hcis-surface);
    box-shadow: 0 1px 3px rgb(var(--hcis-black-rgb) / 0.08);
}

.ds-portal-nav__badge {
    padding: 1px 6px;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-brand-100);
    color: var(--hcis-brand-700);
    font-size: var(--hcis-font-size-2xs);
    font-weight: var(--hcis-weight-black);
}

.ds-portal-nav__actions {
    display: flex;
    align-items: center;
    gap: var(--hcis-space-3);
}

.ds-portal-nav__search {
    position: relative;
    display: flex;
    align-items: center;
}

.ds-portal-nav__search-icon {
    position: absolute;
    left: var(--hcis-space-3);
    color: var(--hcis-text-muted);
    pointer-events: none;
}

.ds-portal-nav__select {
    height: 2.15rem;
    padding-left: 2rem;
    padding-right: var(--hcis-space-3);
    border-radius: var(--hcis-radius-md);
    border: 1px solid var(--hcis-border-strong);
    background: var(--hcis-surface);
    color: var(--hcis-text);
    font-size: var(--hcis-font-size-xs);
    font-weight: var(--hcis-weight-bold);
    cursor: pointer;
    outline: none;
    transition: var(--hcis-transition-props) var(--hcis-duration-fast) var(--hcis-ease);
}

.ds-portal-nav__select:focus {
    border-color: var(--hcis-brand-500);
    box-shadow: 0 0 0 3px rgb(var(--hcis-brand-500-rgb) / 0.15);
}

@media (max-width: 960px) {
    .ds-portal-nav__links {
        display: none;
    }
}
</style>

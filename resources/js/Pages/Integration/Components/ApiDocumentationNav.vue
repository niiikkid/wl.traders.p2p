<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    sections: {
        type: Array,
        required: true,
    },
});

const activeSectionId = ref('');

const resolveActiveSection = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const hash = window.location.hash.replace(/^#/, '');
    activeSectionId.value = props.sections.some((section) => section.id === hash)
        ? hash
        : (props.sections[0]?.id ?? '');
};

const handleHashChange = () => {
    resolveActiveSection();
};

let observer = null;

onMounted(() => {
    resolveActiveSection();
    window.addEventListener('hashchange', handleHashChange);

    if (typeof IntersectionObserver === 'undefined') {
        return;
    }

    const sectionElements = props.sections
        .map((section) => document.getElementById(section.id))
        .filter(Boolean);

    if (!sectionElements.length) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            const visibleEntries = entries
                .filter((entry) => entry.isIntersecting)
                .sort((left, right) => right.intersectionRatio - left.intersectionRatio);

            if (!visibleEntries.length) {
                return;
            }

            activeSectionId.value = visibleEntries[0].target.id;
        },
        {
            rootMargin: '-20% 0px -65% 0px',
            threshold: [0, 0.25, 0.5, 0.75, 1],
        },
    );

    sectionElements.forEach((element) => observer.observe(element));
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('hashchange', handleHashChange);
    }

    observer?.disconnect();
    observer = null;
});

const navItems = computed(() => props.sections.map((section) => ({
    ...section,
    active: activeSectionId.value === section.id,
})));
</script>

<template>
    <aside class="w-full shrink-0 xl:w-48">
        <div class="card sticky top-6 bg-base-100 shadow">
            <div class="p-2">
                <p class="px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-base-content/50">
                    Содержание
                </p>

                <ul class="menu menu-sm w-full space-y-0">
                    <li
                        v-for="section in navItems"
                        :key="section.id"
                        :class="{ 'rounded-md bg-base-content/10': section.active }"
                    >
                        <a
                            :href="`#${section.id}`"
                            class="gap-2 px-2 py-1.5 text-sm"
                            :class="{ 'menu-active font-medium': section.active }"
                        >
                            <svg
                                class="size-4 shrink-0 opacity-30"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                v-html="section.icon"
                            />
                            <span class="truncate">{{ section.title }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </aside>
</template>

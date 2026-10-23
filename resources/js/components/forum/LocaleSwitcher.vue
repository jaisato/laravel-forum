<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { update } from '@/actions/App/Http/Controllers/LocaleController';

const page = usePage();

const current = computed(() => page.props.locale);

// Each language names itself.
const names: Record<string, string> = { es: 'Español', en: 'English' };

function choose(locale: string): void {
    if (locale === current.value) {
        return;
    }

    router.post(
        update.url(),
        { locale },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <div
        role="group"
        :aria-label="$t('Language')"
        class="inline-flex items-center rounded-md border p-0.5 text-xs font-medium"
    >
        <button
            v-for="locale in page.props.locales"
            :key="locale"
            type="button"
            :data-locale="locale"
            :lang="locale"
            :title="names[locale] ?? locale"
            :aria-pressed="locale === current"
            class="rounded px-2 py-1 uppercase transition-colors"
            :class="
                locale === current
                    ? 'bg-accent text-accent-foreground'
                    : 'text-muted-foreground hover:text-foreground'
            "
            @click="choose(locale)"
        >
            {{ locale }}
        </button>
    </div>
</template>

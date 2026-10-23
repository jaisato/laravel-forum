<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

const props = defineProps<{
    /** 403, 404, 429, 500 or 503 (bootstrap/app.php). */
    status: number;
}>();

// Titles and descriptions are translation keys.
const messages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'You may not see this page',
        description:
            'It is not open to your account. If you think it should be, ask the staff.',
    },
    404: {
        title: 'Page not found',
        description:
            'The address is wrong, or what was here has been moved or deleted.',
    },
    429: {
        title: 'Too many requests',
        description: 'Take a breath and try again in a moment.',
    },
    500: {
        title: 'Something went wrong',
        description:
            'The forum ran into a problem on its side. Try again in a while.',
    },
    503: {
        title: 'Back very soon',
        description: 'The forum is being updated. Try again in a few minutes.',
    },
};

const message = computed(() => messages[props.status] ?? messages[500]);
</script>

<template>
    <Head :title="$t(message.title)" />

    <section
        class="mx-auto flex max-w-lg flex-col items-center gap-4 py-16 text-center"
    >
        <p
            data-test="error-status"
            class="text-6xl font-bold tracking-tight text-muted-foreground/60"
        >
            {{ status }}
        </p>
        <h1 class="text-2xl font-semibold tracking-tight">
            {{ $t(message.title) }}
        </h1>
        <p data-test="error-description" class="text-muted-foreground">
            {{ $t(message.description) }}
        </p>
        <Button as-child>
            <Link :href="home.url()" data-test="error-home">{{
                $t('Back to the forum')
            }}</Link>
        </Button>
    </section>
</template>

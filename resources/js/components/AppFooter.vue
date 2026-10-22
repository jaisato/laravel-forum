<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ui as apiDocs } from '@/routes/scramble/docs';

const page = usePage();
const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-sidebar-border/80">
        <div
            class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-xs text-muted-foreground sm:flex-row"
        >
            <p>&copy; {{ year }} {{ page.props.name }}</p>
            <nav :aria-label="$t('Footer')" class="flex items-center gap-4">
                <a :href="apiDocs.url()" class="hover:text-foreground">{{
                    $t('API documentation')
                }}</a>
            </nav>
        </div>
    </footer>
</template>

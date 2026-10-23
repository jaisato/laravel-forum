<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PageMeta } from '@/types/forum';

const page = usePage();

// Pages about one thing (a thread, a profile) describe it in their `meta`.
const meta = computed(() => page.props.meta as PageMeta | undefined);
</script>

<template>
    <Head>
        <link
            head-key="canonical"
            rel="canonical"
            :href="page.props.canonical"
        />
        <meta
            head-key="og:url"
            property="og:url"
            :content="page.props.canonical"
        />
        <meta
            head-key="og:site_name"
            property="og:site_name"
            :content="page.props.name"
        />
        <meta head-key="og:type" property="og:type" content="website" />
        <template v-if="meta">
            <meta
                head-key="og:title"
                property="og:title"
                :content="meta.title"
            />
            <meta
                head-key="og:description"
                property="og:description"
                :content="meta.description"
            />
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content="summary"
            />
        </template>
    </Head>
</template>

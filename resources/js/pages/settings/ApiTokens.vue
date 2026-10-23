<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, KeyRound, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import RelativeTime from '@/components/forum/RelativeTime.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/api-tokens';

type ApiToken = {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string;
};

const props = defineProps<{
    tokens: ApiToken[];
    /** The abilities the member may give a token. */
    abilities: string[];
}>();

const page = usePage();

const labels: Record<string, string> = {
    read: 'Read',
    write: 'Write',
    messages: 'Private messages',
    moderate: 'Moderate',
    '*': 'Everything',
};

const descriptions: Record<string, string> = {
    read: 'Read the forum and your notifications.',
    write: 'Start threads, reply, react and report.',
    messages: 'Read and send your private messages.',
    moderate: 'Use your moderation powers.',
};

const form = useForm({ name: '', abilities: ['read'] as string[] });

// Shown once, right after it is created: the server keeps only its hash.
const newToken = computed(() => page.flash.token ?? null);
const copied = ref(false);

async function copy(token: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(token);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // No clipboard (an insecure origin): the token stays on screen.
    }
}

function submit(): void {
    if (form.processing) {
        return;
    }

    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function revoke(token: ApiToken, question: string): void {
    if (!window.confirm(question)) {
        return;
    }

    router.delete(destroy.url(token.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('API tokens')" />

    <h1 class="sr-only">{{ $t('API tokens') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="API tokens"
            description="Tokens let your scripts and apps use the forum API on your behalf."
        />

        <div
            v-if="newToken"
            data-test="new-token"
            class="space-y-2 rounded-lg border border-emerald-500/40 bg-emerald-500/10 p-4"
        >
            <p class="text-sm font-medium">
                {{ $t('Copy your new token now: it will not be shown again.') }}
            </p>
            <div class="flex items-center gap-2">
                <code
                    class="min-w-0 flex-1 truncate rounded bg-background px-2 py-1.5 font-mono text-sm"
                    >{{ newToken }}</code
                >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test="copy-token"
                    @click="copy(newToken)"
                >
                    <Check v-if="copied" aria-hidden="true" />
                    <Copy v-else aria-hidden="true" />
                    {{ copied ? $t('Copied') : $t('Copy') }}
                </Button>
            </div>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="token-name">{{ $t('Token name') }}</Label>
                <Input
                    id="token-name"
                    v-model="form.name"
                    required
                    maxlength="100"
                    :placeholder="$t('e.g. My backup script')"
                />
                <InputError :message="form.errors.name" />
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">
                    {{ $t('What it may do') }}
                </legend>
                <div
                    v-for="ability in props.abilities"
                    :key="ability"
                    class="flex items-start gap-3"
                >
                    <input
                        :id="`ability-${ability}`"
                        v-model="form.abilities"
                        type="checkbox"
                        :value="ability"
                        class="mt-0.5 size-4 rounded border-input accent-primary"
                    />
                    <label :for="`ability-${ability}`" class="text-sm">
                        <span class="font-medium">{{
                            $t(labels[ability] ?? ability)
                        }}</span>
                        <span
                            v-if="descriptions[ability]"
                            class="block text-muted-foreground"
                            >{{ $t(descriptions[ability]) }}</span
                        >
                    </label>
                </div>
                <InputError :message="form.errors.abilities" />
            </fieldset>

            <Button type="submit" :disabled="form.processing">
                {{ $t('Create token') }}
            </Button>
        </form>
    </div>

    <div class="space-y-4">
        <Heading variant="small" title="Your tokens" />

        <ul
            v-if="tokens.length > 0"
            class="divide-y overflow-hidden rounded-lg border"
        >
            <li
                v-for="token in tokens"
                :key="token.id"
                data-test="api-token"
                class="flex items-center gap-3 p-4"
            >
                <KeyRound
                    class="size-5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1 space-y-1">
                    <p class="font-medium break-words">{{ token.name }}</p>
                    <p class="flex flex-wrap gap-1">
                        <span
                            v-for="ability in token.abilities"
                            :key="ability"
                            class="rounded-md bg-muted px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground"
                            >{{ $t(labels[ability] ?? ability) }}</span
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="token.last_used_at">
                            {{ $t('Last used') }}
                            <RelativeTime :datetime="token.last_used_at" />
                        </template>
                        <template v-else>{{ $t('Never used') }}</template>
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    data-test="revoke-token"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                    @click="
                        revoke(
                            token,
                            $t(
                                'Revoke the token “:name”? Whatever uses it will stop working.',
                                { name: token.name },
                            ),
                        )
                    "
                >
                    <Trash2 aria-hidden="true" />
                    {{ $t('Revoke') }}
                </Button>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
            {{ $t('You have no tokens yet.') }}
        </p>
    </div>
</template>

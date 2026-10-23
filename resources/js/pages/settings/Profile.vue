<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import { update } from '@/routes/profile';
import { send } from '@/routes/verification';

type EditableProfile = {
    username: string;
    bio: string | null;
    location: string | null;
    website: string | null;
    /** Null: the language of the browser or the session. */
    locale: string | null;
    avatar_url: string | null;
};

const props = defineProps<{
    status?: string | null;
    profile: EditableProfile;
}>();

const page = usePage();
// The settings pages require a signed in member.
const user = computed(() => page.props.auth.user!);

// Each language names itself.
const languages: Record<string, string> = { es: 'Español', en: 'English' };

const form = useForm({
    name: user.value.name,
    username: props.profile.username,
    email: user.value.email,
    bio: props.profile.bio ?? '',
    location: props.profile.location ?? '',
    website: props.profile.website ?? '',
    locale: props.profile.locale ?? '',
    avatar: null as File | null,
    remove_avatar: false,
});

// The picture as it will be: the one chosen, none, or the current one.
const preview = ref<string | null>(props.profile.avatar_url);
let chosenUrl: string | null = null;

function forgetChosen(): void {
    if (chosenUrl !== null) {
        URL.revokeObjectURL(chosenUrl);
        chosenUrl = null;
    }
}

function chooseAvatar(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    forgetChosen();
    form.avatar = file;
    form.remove_avatar = false;

    if (file) {
        chosenUrl = URL.createObjectURL(file);
    }

    preview.value = chosenUrl ?? props.profile.avatar_url;
}

function removeAvatar(): void {
    forgetChosen();
    form.avatar = null;
    form.remove_avatar = true;
    preview.value = null;
}

onBeforeUnmount(forgetChosen);

function submit(): void {
    if (form.processing) {
        return;
    }

    // A picture travels as multipart form data, which PHP only reads on
    // POST requests: the method goes in _method, as Wayfinder's forms do.
    form.post(update.url({ query: { _method: 'PATCH' } }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.avatar = null;
            form.remove_avatar = false;
        },
    });
}
</script>

<template>
    <Head :title="$t('Profile settings')" />

    <h1 class="sr-only">{{ $t('Profile settings') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="What other members see of you, and how to reach you"
        />

        <form
            data-test="profile-form"
            class="space-y-6"
            @submit.prevent="submit"
        >
            <div class="flex items-center gap-4">
                <div
                    data-test="avatar-preview"
                    class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted text-xl font-semibold text-muted-foreground"
                >
                    <img
                        v-if="preview"
                        :src="preview"
                        :alt="$t('Your picture')"
                        class="size-full object-cover"
                    />
                    <span v-else aria-hidden="true">{{
                        getInitials(form.name)
                    }}</span>
                </div>
                <div class="grid gap-2">
                    <Label for="avatar">{{ $t('Picture') }}</Label>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            id="avatar"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            class="text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm file:font-medium"
                            @change="chooseAvatar"
                        />
                        <Button
                            v-if="preview"
                            type="button"
                            variant="ghost"
                            size="sm"
                            data-test="remove-avatar"
                            @click="removeAvatar"
                        >
                            {{ $t('Remove picture') }}
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(
                                'JPG, PNG, WebP or GIF up to 2 MB. It is cropped to a square.',
                            )
                        }}
                    </p>
                    <InputError :message="form.errors.avatar" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="name">{{ $t('Name') }}</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    required
                    autocomplete="name"
                    :placeholder="$t('Full name')"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="username">{{ $t('Username') }}</Label>
                <Input
                    id="username"
                    v-model="form.username"
                    required
                    autocomplete="nickname"
                    maxlength="30"
                />
                <p class="text-xs text-muted-foreground">
                    {{
                        $t(
                            'Lower case letters, digits, "-" and "_". Your profile address and @mentions use it.',
                        )
                    }}
                </p>
                <InputError :message="form.errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ $t('Email address') }}</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    :placeholder="$t('Email address')"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div v-if="!user.email_verified">
                <p class="-mt-4 text-sm text-muted-foreground">
                    {{ $t('Your email address is unverified.') }}
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        {{
                            $t('Click here to re-send the verification email.')
                        }}
                    </Link>
                </p>

                <div
                    v-if="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    {{
                        $t(
                            'A new verification link has been sent to your email address.',
                        )
                    }}
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="bio">{{ $t('About you') }}</Label>
                <textarea
                    id="bio"
                    v-model="form.bio"
                    rows="4"
                    maxlength="500"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                />
                <InputError :message="form.errors.bio" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="location">{{ $t('Location') }}</Label>
                    <Input
                        id="location"
                        v-model="form.location"
                        maxlength="80"
                    />
                    <InputError :message="form.errors.location" />
                </div>

                <div class="grid gap-2">
                    <Label for="website">{{ $t('Website') }}</Label>
                    <Input
                        id="website"
                        v-model="form.website"
                        type="url"
                        placeholder="https://"
                    />
                    <InputError :message="form.errors.website" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="locale">{{ $t('Language') }}</Label>
                <select
                    id="locale"
                    v-model="form.locale"
                    class="h-9 rounded-md border border-input bg-background px-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option value="">{{ $t('The browser’s language') }}</option>
                    <option
                        v-for="locale in page.props.locales"
                        :key="locale"
                        :value="locale"
                        :lang="locale"
                    >
                        {{ languages[locale] ?? locale }}
                    </option>
                </select>
                <InputError :message="form.errors.locale" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="update-profile-button"
                    >{{ $t('Save') }}</Button
                >
            </div>
        </form>
    </div>

    <DeleteUser />
</template>

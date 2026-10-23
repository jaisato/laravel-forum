<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <div v-if="canManageTwoFactor" class="space-y-6">
        <Heading
            variant="small"
            title="Two-factor authentication"
            description="Manage your two-factor authentication settings"
        />

        <div
            v-if="!twoFactorEnabled"
            class="flex flex-col items-start justify-start space-y-4"
        >
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'When you enable two-factor authentication, you will be asked for a secure code when you log in. Your phone’s authenticator app (TOTP) gives it to you.',
                    )
                }}
            </p>

            <div>
                <Button v-if="hasSetupData" @click="showSetupModal = true">
                    <ShieldCheck />{{ $t('Continue setup') }}
                </Button>
                <Form
                    v-else
                    #default="{ processing }"
                    v-bind="enable.form()"
                    @success="showSetupModal = true"
                >
                    <Button type="submit" :disabled="processing">
                        {{ $t('Enable 2FA') }}
                    </Button>
                </Form>
            </div>
        </div>

        <div v-else class="flex flex-col items-start justify-start space-y-4">
            <p class="text-sm text-muted-foreground">
                {{
                    $t(
                        'You are asked for a secure code when you log in, which your phone’s authenticator app (TOTP) gives you.',
                    )
                }}
            </p>

            <div class="relative inline">
                <Form #default="{ processing }" v-bind="disable.form()">
                    <Button
                        variant="destructive"
                        type="submit"
                        :disabled="processing"
                    >
                        {{ $t('Disable 2FA') }}
                    </Button>
                </Form>
            </div>

            <TwoFactorRecoveryCodes />
        </div>

        <TwoFactorSetupModal
            v-model:isOpen="showSetupModal"
            :requiresConfirmation="requiresConfirmation"
            :twoFactorEnabled="twoFactorEnabled"
        />
    </div>
</template>

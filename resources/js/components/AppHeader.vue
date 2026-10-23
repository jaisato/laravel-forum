<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { House, Menu, MessagesSquare } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import LocaleSwitcher from '@/components/forum/LocaleSwitcher.vue';
import UserAvatar from '@/components/forum/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { home, login, register } from '@/routes';
import { index as threadsIndex } from '@/routes/threads';
import type { BreadcrumbItem, NavItem } from '@/types';

const props = withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isCurrentUrl } = useCurrentUrl();

const mainNavItems: NavItem[] = [
    { title: 'Home', href: home(), icon: House },
    { title: 'Threads', href: threadsIndex(), icon: MessagesSquare },
];
</script>

<template>
    <header class="border-b border-sidebar-border/80 bg-background">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-2 px-4">
            <!-- Mobile menu -->
            <div class="lg:hidden">
                <Sheet>
                    <SheetTrigger :as-child="true">
                        <Button variant="ghost" size="icon" class="h-9 w-9">
                            <Menu class="h-5 w-5" />
                            <span class="sr-only">{{
                                $t('Open the menu')
                            }}</span>
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" class="w-[300px] p-6">
                        <SheetTitle class="sr-only">{{
                            $t('Navigation menu')
                        }}</SheetTitle>
                        <SheetHeader class="flex justify-start text-left">
                            <AppLogo />
                        </SheetHeader>
                        <nav class="-mx-3 mt-6 space-y-1">
                            <Link
                                v-for="item in mainNavItems"
                                :key="item.title"
                                :href="item.href"
                                class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                :class="{
                                    'bg-accent': isCurrentUrl(item.href),
                                }"
                            >
                                <component
                                    :is="item.icon"
                                    v-if="item.icon"
                                    class="h-5 w-5"
                                />
                                {{ $t(item.title) }}
                            </Link>
                        </nav>
                        <div class="mt-6">
                            <LocaleSwitcher />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>

            <Link :href="home()" class="flex items-center gap-x-2">
                <AppLogo />
            </Link>

            <!-- Desktop menu -->
            <nav
                :aria-label="$t('Main navigation')"
                class="ml-8 hidden h-full items-center gap-1 lg:flex"
            >
                <Link
                    v-for="item in mainNavItems"
                    :key="item.title"
                    :href="item.href"
                    class="relative flex h-full items-center gap-2 px-3 text-sm font-medium text-muted-foreground hover:text-foreground"
                    :class="{
                        'text-foreground after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-foreground':
                            isCurrentUrl(item.href),
                    }"
                    :aria-current="isCurrentUrl(item.href) ? 'page' : undefined"
                >
                    <component
                        :is="item.icon"
                        v-if="item.icon"
                        class="h-4 w-4"
                    />
                    {{ $t(item.title) }}
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <LocaleSwitcher class="hidden sm:inline-flex" />

                <DropdownMenu v-if="user">
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                        >
                            <UserAvatar :user="user" size="md" />
                            <span class="sr-only">{{
                                $t('Your account')
                            }}</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>

                <template v-else>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="login()">{{ $t('Log in') }}</Link>
                    </Button>
                    <Button size="sm" as-child>
                        <Link :href="register()">{{ $t('Sign up') }}</Link>
                    </Button>
                </template>
            </div>
        </div>

        <div
            v-if="props.breadcrumbs.length > 1"
            class="flex w-full border-t border-sidebar-border/70"
        >
            <div
                class="mx-auto flex h-12 w-full max-w-6xl items-center justify-start px-4 text-neutral-500"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </header>
</template>

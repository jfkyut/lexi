<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MessageCircle, SquarePen } from '@lucide/vue';
import { show } from '@/actions/App/Http/Controllers/ThreadController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import ThreadMenu from '@/components/ThreadMenu.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const mainNavItems: NavItem[] = [
    {
        title: 'New Chat',
        href: dashboard(),
        icon: SquarePen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />

            <SidebarGroup
                class="px-2 py-0 group-data-[collapsible=icon]:hidden"
            >
                <SidebarGroupLabel>Your chats</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="thread in page.props.threads"
                        :key="thread.id"
                        class="group/thread"
                    >
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl(show.url(thread.id))"
                            :tooltip="thread.title"
                            tooltip-when-expanded
                        >
                            <Link class="pr-9" :href="show.url(thread.id)">
                                <!-- <MessageCircle /> -->
                                <span>{{ thread.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                        <ThreadMenu :thread="thread" />
                    </SidebarMenuItem>
                    <SidebarMenuItem v-if="page.props.threads.length === 0">
                        <span class="px-2 py-1 text-xs text-muted-foreground"
                            >No chats yet</span
                        >
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

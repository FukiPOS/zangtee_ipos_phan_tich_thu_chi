<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import UserMenuContent from './UserMenuContent.vue';

const page = usePage();
const { isMobile, state } = useSidebar();

// Get Fabi user data
const user = ref(null);
const loading = ref(true);

const fetchUser = async () => {
    try {
        const response = await axios.get('/api/me');
        user.value = response.data.user;
    } catch (error) {
        console.error('Failed to fetch user:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchUser();
});
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <div v-if="loading" class="flex items-center gap-2">
                            <div class="h-8 w-8 rounded-lg bg-gray-200 animate-pulse"></div>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                                <div class="h-3 bg-gray-200 rounded animate-pulse mt-1"></div>
                            </div>
                        </div>
                        <UserInfo v-else-if="user" :user="user" />
                        <div v-else class="text-sm text-gray-500">Không có thông tin user</div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    v-if="user && !loading"
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>

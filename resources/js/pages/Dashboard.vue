<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import axios from 'axios';
import AuthController from '@/actions/App/Http/Controllers/AuthController';
import { Button } from '@/components/ui/button';
import { LogOut } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

// User data from Fabi
const userData = ref<any>(null);
const loading = ref(true);

// Fetch user data
const fetchUserData = async () => {
    try {
        const response = await axios.get('/api/me');
        userData.value = response.data;
    } catch (error) {
        console.error('Error fetching user data:', error);
    } finally {
        loading.value = false;
    }
};

// Logout function
const handleLogout = () => {
    router.post(AuthController.logout.url(), {}, {
        onFinish: () => {
            router.flushAll();
        }
    });
};

onMounted(() => {
    fetchUserData();
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-hidden rounded-xl p-4 md:p-6"
        >
            <!-- Header with logout button -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2">
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Dashboard</h1>
                <Button 
                    @click="handleLogout"
                    variant="outline" 
                    class="flex items-center gap-2 w-full sm:w-auto dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700 dark:hover:bg-zinc-700"
                >
                    <LogOut class="h-4 w-4" />
                    Đăng xuất
                </Button>
            </div>

            <!-- Loading state -->
            <div v-if="loading" class="flex items-center justify-center h-64">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-gray-900 dark:border-white"></div>
            </div>

            <!-- User Info and Company Stats -->
            <div v-if="userData" class="grid auto-rows-min gap-4 md:grid-cols-3">
                <!-- User Info Card -->
                <div class="p-6 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm transition-colors duration-200">
                    <h3 class="text-lg font-semibold mb-4 text-zinc-900 dark:text-zinc-100">Thông tin người dùng</h3>
                    <div class="space-y-2 text-zinc-700 dark:text-zinc-300">
                        <p><strong>Tên:</strong> {{ userData.user?.full_name }}</p>
                        <p><strong>Email:</strong> {{ userData.user?.email }}</p>
                        <p><strong>Vai trò:</strong> {{ userData.userRole?.role_name }}</p>
                    </div>
                </div>

                <!-- Company Info Card -->
                <div class="p-6 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm transition-colors duration-200">
                    <h3 class="text-lg font-semibold mb-4 text-zinc-900 dark:text-zinc-100">Thương hiệu</h3>
                    <div class="space-y-2">
                        <h4 class="font-medium text-zinc-900 dark:text-zinc-200">{{ userData.brands[0].brand_name }}</h4>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">ID: {{ userData.brands[0].brand_id }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Tiền tệ: {{ userData.brands[0].currency }}</p>
                    </div>
                </div>

                <!-- Stats Card -->
                <div class="p-6 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm transition-colors duration-200">
                    <h3 class="text-lg font-semibold mb-4 text-zinc-900 dark:text-zinc-100">Thống kê</h3>
                    <div class="space-y-2 text-zinc-700 dark:text-zinc-300">
                        <p><strong>Số thương hiệu:</strong> {{ userData.brands?.length || 0 }}</p>
                        <p><strong>Số cửa hàng:</strong> {{ userData.stores?.length || 0 }}</p>
                        <p><strong>Cửa hàng hoạt động:</strong> {{ userData.stores?.filter((s: any) => s.active === 1).length || 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- Stores Section -->
            <div v-if="userData?.stores?.length" class="rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 shadow-sm transition-colors duration-200">
                <h3 class="text-lg font-semibold mb-4 text-zinc-900 dark:text-zinc-100">Cửa hàng</h3>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="store in userData.stores" :key="store.id" 
                         class="p-4 border rounded-lg transition-colors duration-200 border-zinc-200 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <h4 class="font-medium text-zinc-900 dark:text-zinc-200">{{ store.short_name || store.store_name }}</h4>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">ID: {{ store.store_id }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Địa chỉ: {{ store.address }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Điện thoại: {{ store.phone }}</p>
                        <p class="text-sm mt-2 font-medium" :class="store.active ? 'text-green-600 dark:text-green-400' : 'text-zinc-500 dark:text-zinc-400'">
                            {{ store.active == true ? '● Hoạt động' : '○ Không hoạt động' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

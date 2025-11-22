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
const userData = ref(null);
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
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <!-- Header with logout button -->
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-2xl font-bold">Dashboard</h1>
                <Button 
                    @click="handleLogout"
                    variant="outline" 
                    class="flex items-center gap-2"
                >
                    <LogOut class="h-4 w-4" />
                    Đăng xuất
                </Button>
            </div>

            <!-- Loading state -->
            <div v-if="loading" class="flex items-center justify-center h-64">
                <div class="animate-spin rounded-full h-32 w-32 border-b-2 border-gray-900"></div>
            </div>

            <!-- User Info and Company Stats -->
            <div v-else-if="userData" class="grid auto-rows-min gap-4 md:grid-cols-3">
                <!-- User Info Card -->
                <div class="p-6 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-gray-800">
                    <h3 class="text-lg font-semibold mb-4">Thông tin người dùng</h3>
                    <div class="space-y-2">
                        <p><strong>Tên:</strong> {{ userData.user?.full_name }}</p>
                        <p><strong>Email:</strong> {{ userData.user?.email }}</p>
                        <p><strong>Vai trò:</strong> {{ userData.userRole?.role_name }}</p>
                    </div>
                </div>

                <!-- Company Info Card -->
                <div class="p-6 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-gray-800">
                    <h3 class="text-lg font-semibold mb-4">Thương hiệu</h3>
                    <div class="space-y-2">
                        <h4 class="font-medium">{{ userData.brands[0].brand_name }}</h4>
                        <p class="text-sm text-gray-600">ID: {{ userData.brands[0].brand_id }}</p>
                        <p class="text-sm text-gray-600">Tiền tệ: {{ userData.brands[0].currency }}</p>
                    </div>
                </div>

                <!-- Stats Card -->
                <div class="p-6 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-gray-800">
                    <h3 class="text-lg font-semibold mb-4">Thống kê</h3>
                    <div class="space-y-2">
                        <p><strong>Số thương hiệu:</strong> {{ userData.brands?.length || 0 }}</p>
                        <p><strong>Số cửa hàng:</strong> {{ userData.stores?.length || 0 }}</p>
                        <p><strong>Cửa hàng hoạt động:</strong> {{ userData.stores?.filter(s => s.active === 1).length || 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- Stores Section -->
            <div v-if="userData?.stores?.length" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-gray-800 p-6">
                <h3 class="text-lg font-semibold mb-4">Cửa hàng</h3>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="store in userData.stores" :key="store.id" 
                         class="p-4 border rounded-lg" 
                         :class="store.active ? 'border-green-300 bg-green-50 dark:bg-green-900/20' : 'border-gray-300 bg-gray-50 dark:bg-gray-900/20'">
                        <h4 class="font-medium">{{ store.store_name }}</h4>
                        <p class="text-sm text-gray-600">ID: {{ store.store_id }}</p>
                        <p class="text-sm text-gray-600">Địa chỉ: {{ store.address }}</p>
                        <p class="text-sm text-gray-600">Điện thoại: {{ store.phone }}</p>
                        <p class="text-sm" :class="store.active ? 'text-green-600' : 'text-red-600'">
                            Trạng thái: {{ store.active ? 'Hoạt động' : 'Không hoạt động' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

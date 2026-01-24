<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as routeRevenueIndex } from '@/routes/revenue';
import { index as routeTransactionsIndex } from '@/routes/transactions';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,

} from '@/components/ui/dialog';
import GroupedBarChart from '@/components/GroupedBarChart.vue';
import axios from 'axios';

const props = defineProps<{
    totalRevenue: number;
    totalExpense: number;
    expenseByProfession: Array<{
        id: number;
        name: string;
        amount: number;
    }>;
    comparisonData: Array<{
        id: number;
        name: string;
        stores: Array<{
            uid: string;
            name: string;
            amount: number;
        }>;
    }>;
    stores: Array<any>;
    filters: {
        store_uid: string;
        from_date: string;
        to_date: string;
    };
}>();

const form = ref({
    store_uid: props.filters.store_uid || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
});

const submitFilter = () => {
    router.get(routeRevenueIndex.url({ query: form.value }), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(value);
};

const formatDate = (dateString: string) => {
    if (!dateString) return '';
    const date = new Date(Number(dateString));
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleString('vi-VN');
};

const profit = computed(() => props.totalRevenue - props.totalExpense);

// --- Chart Logic ---
const colors = [
    '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
    '#ec4899', '#6366f1', '#14b8a6', '#f97316', '#06b6d4'
];

// Assign consistent colors to stores based on their order in props.stores
const getStoreColor = (storeUid: string) => {
    const validStores = props.stores.map(s => s.ipos_id);
    // Or maybe props.comparisonData has all stores? No, better relying on stores list for consistent ordering.
    // If props.stores is available, use it index.
    const index = validStores.indexOf(storeUid);
    if (index >= 0) return colors[index % colors.length];

    // Fallback hash
    let hash = 0;
    for (let i = 0; i < storeUid.length; i++) {
        hash = storeUid.charCodeAt(i) + ((hash << 5) - hash);
    }
    const c = (hash & 0x00FFFFFF).toString(16).toUpperCase();
    return '#' + '00000'.substring(0, 6 - c.length) + c;
};

const chartData = computed(() => {
    const total = props.totalExpense || 1; // avoid div by 0
    let startAngle = 0;

    return props.expenseByProfession
        .sort((a, b) => b.amount - a.amount) // Sort desc
        .map((item, index) => {
            const percentage = item.amount / total;
            const angle = percentage * 360;
            const endAngle = startAngle + angle;

            // Calculate SVG path
            const x1 = 50 + 50 * Math.cos(Math.PI * startAngle / 180);
            const y1 = 50 + 50 * Math.sin(Math.PI * startAngle / 180);
            const x2 = 50 + 50 * Math.cos(Math.PI * endAngle / 180);
            const y2 = 50 + 50 * Math.sin(Math.PI * endAngle / 180);

            const largeArcFlag = angle > 180 ? 1 : 0;

            const pathData = [
                `M 50 50`,
                `L ${x1} ${y1}`,
                `A 50 50 0 ${largeArcFlag} 1 ${x2} ${y2}`,
                `Z`
            ].join(' ');

            const segment = {
                name: item.name,
                id: item.id,
                value: item.amount,
                percentage,
                color: colors[index % colors.length],
                path: pathData,
                startAngle,
                endAngle
            };

            startAngle = endAngle;
            return segment;
        });
});

const comparisonChartGroups = computed(() => {
    if (!props.comparisonData) return [];

    // Sort groups ?
    // Map to internal format
    // Map to internal format
    const formattedGroups = props.comparisonData.map(profession => {
        // Ensure we iterate over ALL stores to fill gaps with 0
        const values = props.stores.map(store => {
            const storeData = profession.stores.find(s => s.uid === store.ipos_id);
            return {
                label: store.short_name || store.name,
                value: storeData ? storeData.amount : 0,
                color: getStoreColor(store.ipos_id)
            };
        });

        return {
            label: profession.name,
            values: values
        };
    });

    // Chunk into 3
    const chunks = [];
    for (let i = 0; i < formattedGroups.length; i += 3) {
        chunks.push(formattedGroups.slice(i, i + 3));
    }
    return chunks;
});

// --- Details Dialog Logic ---
const isDetailsOpen = ref(false);
const detailsLoading = ref(false);
const detailsData = ref<any[]>([]);
const detailsTitle = ref('');
const detailsPage = ref(1);
const detailsLastPage = ref(1);
const detailsPerPage = 100;

const detailsProfessionId = ref<number | null>(null);

const fetchDetails = async (profession: any, page = 1) => {
    if (page === 1) {
        detailsTitle.value = profession.name;
        detailsProfessionId.value = profession.id;
        isDetailsOpen.value = true;
        detailsData.value = [];
        detailsPage.value = 1;
    }

    detailsLoading.value = true;

    try {
        const query = {
            ...form.value,
            profession_id: detailsProfessionId.value,
            page: page,
            per_page: detailsPerPage
        };

        const response = await axios.get(routeTransactionsIndex.url({ query }), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });

        if (page === 1) {
            detailsData.value = response.data.data;
        } else {
            detailsData.value = [...detailsData.value, ...response.data.data];
        }

        detailsPage.value = response.data.current_page;
        detailsLastPage.value = response.data.last_page;

    } catch (e) {
        console.error("Failed to fetch details", e);
    } finally {
        detailsLoading.value = false;
    }
};

const handleScroll = (e: Event) => {
    const target = e.target as HTMLElement;
    if (target.scrollHeight - target.scrollTop <= target.clientHeight + 50) {
        // Scrolled near bottom
        if (!detailsLoading.value && detailsPage.value < detailsLastPage.value) {
            fetchDetails({ name: detailsTitle.value, id: detailsProfessionId.value }, detailsPage.value + 1);
        }
    }
};

const getStoreName = (storeUid: string) => {
    const store = props.stores.find(s => s.store_uid === storeUid || s.ipos_id === storeUid);
    return store ? (store.short_name || store.name) : storeUid;
};

</script>

<template>
    <Head title="Báo cáo Doanh thu & Chi phí" />

    <AppLayout>
        <template #header>
            <h2 class="font-bold text-xl text-zinc-800 dark:text-zinc-100 leading-tight">
                Báo cáo Thu Chi / Lợi Nhuận
            </h2>
        </template>

        <div class="py-6 lg:py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Filters -->
                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-sm sm:rounded-lg p-4 sm:p-6 border border-zinc-200 dark:border-zinc-800 transition-colors duration-200">
                    <form @submit.prevent="submitFilter" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                        <div class="sm:col-span-2 lg:col-span-1">
                            <Label class="block mb-2 font-medium dark:text-zinc-300">Cửa hàng</Label>
                            <select v-model="form.store_uid" class="w-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 rounded-md shadow-sm border p-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm h-10">
                                <option value="">Tất cả</option>
                                <option v-for="store in stores" :key="store.id" :value="store.ipos_id">
                                    {{ store.short_name || store.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label class="block mb-2 font-medium dark:text-zinc-300">Từ ngày</Label>
                            <Input type="date" v-model="form.from_date" class="w-full dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700 block" />
                        </div>
                        <div>
                            <Label class="block mb-2 font-medium dark:text-zinc-300">Đến ngày</Label>
                            <Input type="date" v-model="form.to_date" class="w-full dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700 block" />
                        </div>
                        <div class="sm:col-span-2 lg:col-span-1">
                            <Button type="submit" class="w-full">Xem báo cáo</Button>
                        </div>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                    <!-- Revenue -->
                    <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500 dark:border-green-600 border border-t-zinc-200 border-r-zinc-200 border-b-zinc-200 dark:border-t-zinc-800 dark:border-r-zinc-800 dark:border-b-zinc-800">
                        <div class="text-gray-500 dark:text-zinc-400 text-sm font-medium uppercase">Tổng Doanh Thu (Ước tính)</div>
                        <div class="mt-2 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-zinc-100">{{ formatCurrency(totalRevenue) }}</div>
                    </div>

                    <!-- Expense -->
                    <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-red-500 dark:border-red-600 border border-t-zinc-200 border-r-zinc-200 border-b-zinc-200 dark:border-t-zinc-800 dark:border-r-zinc-800 dark:border-b-zinc-800">
                        <div class="text-gray-500 dark:text-zinc-400 text-sm font-medium uppercase">Chi tiền két</div>
                        <div class="mt-2 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-zinc-100">{{ formatCurrency(totalExpense) }}</div>
                    </div>

                    <!-- Profit -->
                    <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border border-t-zinc-200 border-r-zinc-200 border-b-zinc-200 dark:border-t-zinc-800 dark:border-r-zinc-800 dark:border-b-zinc-800"
                        :class="profit >= 0 ? 'border-l-blue-500 dark:border-l-blue-600' : 'border-l-gray-500 dark:border-l-zinc-500'">
                        <div class="text-gray-500 dark:text-zinc-400 text-sm font-medium uppercase">Doanh thu còn lại</div>
                        <div class="mt-2 text-2xl sm:text-3xl font-bold" :class="profit >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400'">
                            {{ formatCurrency(profit) }}
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="space-y-6">
                    <!-- Pie Chart: Expense Breakdown -->
                    <div class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-6 border border-zinc-200 dark:border-zinc-800">
                        <h3 class="text-lg font-bold mb-4 dark:text-zinc-100">Cơ cấu Chi phí</h3>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                            <!-- SVG Pie -->
                            <div class="lg:col-span-1 flex flex-col items-center">
                                <div class="relative w-64 h-64 flex-shrink-0">
                                    <svg viewBox="0 0 100 100" class="w-full h-full transform -rotate-90">
                                        <path v-for="(segment, i) in chartData" :key="i"
                                            :d="segment.path"
                                            :fill="segment.color"
                                            @click="fetchDetails(segment)"
                                            class="stroke-white dark:stroke-zinc-900 hover:opacity-80 transition-opacity cursor-pointer"
                                            stroke-width="0.5"
                                        >
                                            <title>{{ segment.name }}: {{ formatCurrency(segment.value) }}</title>
                                        </path>
                                        <!-- Fallback circle if empty -->
                                        <circle v-if="chartData.length === 0" cx="50" cy="50" r="50" class="fill-gray-200 dark:fill-zinc-800" />
                                    </svg>

                                    <!-- Center Hole for Donut -->
                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <div class="w-32 h-32 bg-white dark:bg-zinc-900 rounded-full transition-colors duration-200"></div>
                                    </div>
                                </div>

                                <!-- Thông tin nhận xét-->
                                <div class="space-y-6 mt-8 w-full max-w-xs">
                                    <!-- Revenue Bar -->
                                    <div>
                                        <div class="flex justify-between mb-1 text-sm font-medium dark:text-zinc-300">
                                            <span>Doanh thu</span>
                                            <span>{{ formatCurrency(totalRevenue) }}</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-6 dark:bg-zinc-700 overflow-hidden">
                                            <div class="bg-green-500 h-6 rounded-full" style="width: 100%"></div>
                                        </div>
                                    </div>

                                    <!-- Expense Bar -->
                                    <div>
                                        <div class="flex justify-between mb-1 text-sm font-medium dark:text-zinc-300">
                                            <span>Chi phí</span>
                                            <span>{{ formatCurrency(totalExpense) }}</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-6 dark:bg-zinc-700 overflow-hidden relative">
                                            <!-- Percentage of Revenue -->
                                            <div class="bg-red-500 h-6 rounded-full absolute top-0 left-0"
                                                 :style="{ width: Math.min((totalExpense / (totalRevenue || 1)) * 100, 100) + '%' }">
                                            </div>
                                            <div class="absolute inset-0 flex items-center justify-center text-xs font-bold text-white shadow-sm z-10">
                                                {{ ((totalExpense / (totalRevenue || 1)) * 100).toFixed(1) }}% Doanh thu
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-8 p-4 bg-gray-50 dark:bg-zinc-800 rounded text-sm text-gray-600 dark:text-zinc-400">
                                        <p><strong>Nhận xét:</strong> Chi phí chiếm <span class="text-red-600 dark:text-red-400 font-bold">{{ ((totalExpense / (totalRevenue || 1)) * 100).toFixed(1) }}%</span> doanh thu trong kỳ được chọn.</p>
                                    </div>
                                    <!-- End: Thông tin nhận xét-->
                                </div>
                            </div>

                            <!-- Legend -->
                            <div class="lg:col-span-2 w-full">
                                <div class="space-y-2">
                                     <div v-for="(segment, i) in chartData" :key="i"
                                        class="flex items-center justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-zinc-800 p-1 rounded transition-colors"
                                        @click="fetchDetails(segment)"
                                     >
                                        <div class="flex items-center gap-2">
                                            <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: segment.color }"></span>
                                            <span class="font-medium truncate max-w-[300px] text-gray-700 dark:text-zinc-300" :title="segment.name">{{ segment.name }}</span>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-gray-900 dark:text-zinc-100">{{ formatCurrency(segment.value) }}</div>
                                            <div class="text-xs text-gray-500 dark:text-zinc-500">{{ (segment.percentage * 100).toFixed(1) }}%</div>
                                        </div>
                                     </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comparison Charts Section -->
                <div v-if="comparisonChartGroups.length > 0" class="space-y-6">
                    <h3 class="text-lg font-bold dark:text-zinc-100 text-center">So sánh Chi phí theo Cửa hàng</h3>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <GroupedBarChart
                            v-for="(group, index) in comparisonChartGroups"
                            :key="index"
                            :groups="group"
                            :title="group.length > 0 ? '' : ''"
                        />
                    </div>
                </div>
            </div>
        </div>
        <!-- Details Dialog -->
        <Dialog :open="isDetailsOpen" @update:open="isDetailsOpen = false">
            <DialogContent class="sm:max-w-[700px] dark:bg-zinc-900 dark:border-zinc-800 max-h-[80vh] overflow-hidden flex flex-col">
                <DialogHeader>
                    <DialogTitle class="dark:text-zinc-100">Chi tiết: {{ detailsTitle }}</DialogTitle>
                    <DialogDescription class="dark:text-zinc-400">
                        Danh sách các giao dịch thuộc mục chi này.
                    </DialogDescription>
                </DialogHeader>

                <div class="flex-1 overflow-auto mt-4" @scroll="handleScroll">
                    <div v-if="detailsLoading && detailsData.length === 0" class="flex justify-center items-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>

                    <div v-else-if="detailsData.length === 0" class="text-center py-8 text-gray-500 dark:text-zinc-400">
                        Không có dữ liệu
                    </div>

                    <table v-else class="w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-zinc-800 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-2 font-medium text-gray-500 dark:text-zinc-400">Thời gian</th>
                                <th class="px-4 py-2 font-medium text-gray-500 dark:text-zinc-400">Cửa hàng</th>
                                <th class="px-4 py-2 font-medium text-gray-500 dark:text-zinc-400">Nội dung</th>
                                <th class="px-4 py-2 font-medium text-gray-500 dark:text-zinc-400 text-right">Số tiền</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-zinc-700">
                            <tr v-for="item in detailsData" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-zinc-800/30">
                                <td class="px-4 py-2 text-gray-500 dark:text-zinc-400 whitespace-nowrap">{{ formatDate(item.time) }}</td>
                                <td class="px-4 py-2 text-gray-900 dark:text-zinc-200">{{ getStoreName(item.store_uid) }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-zinc-300 max-w-[200px] truncate" :title="item.note">{{ item.note }}</td>
                                <td class="px-4 py-2 font-bold text-gray-900 dark:text-zinc-100 text-right whitespace-nowrap">{{ formatCurrency(item.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="detailsLoading && detailsData.length > 0" class="flex justify-center items-center py-4">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

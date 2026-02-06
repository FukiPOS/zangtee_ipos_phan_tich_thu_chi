<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, Link, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { index as routeTransactionsIndex, update as routeTransactionsUpdate, bulkUpdate as routeTransactionsBulkUpdate } from '@/routes/transactions';
import { User, AlertCircle } from 'lucide-vue-next';

const props = defineProps<{
    transactions: {
        data: Array<any>;
        links: Array<any>;
        current_page: number;
        last_page: number;
        from: number;
        to: number;
        total: number;
    };
    stores: Array<any>;

    professions: Array<{ id: number; label: string }>;
    stats: {
        total: number;
        valid_count: number;
        review_count: number;
        invalid_count: number;
    };
    filters: {
        store_uid: string;
        from_date: string;
        to_date: string;
        search: string;
        profession_id: string;
        flag: string;
        system_flag: string;
    };
}>();

const form = ref({
    store_uid: props.filters.store_uid || '',
    from_date: props.filters.from_date || '',
    to_date: props.filters.to_date || '',
    search: props.filters.search || '',
    profession_id: props.filters.profession_id || '',
    flag: props.filters.flag || '',
    system_flag: props.filters.system_flag || '',
});

const submitFilter = () => {
    router.get(routeTransactionsIndex.url({ query: form.value }), {}, {
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

const getStoreName = (storeUid: string) => {
    const store = props.stores.find(s => s.store_uid === storeUid || s.ipos_id === storeUid);
    return store ? (store.short_name || store.name) : storeUid;
};

// --- Selection Logic ---
const selectedIds = ref<number[]>([]);

const allSelected = computed(() => {
    return props.transactions.data.length > 0 && props.transactions.data.every(t => selectedIds.value.includes(t.id));
});

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedIds.value = [...new Set([...selectedIds.value, ...props.transactions.data.map(t => t.id)])];
    } else {
        // Deselect current page items
        const pageIds = props.transactions.data.map(t => t.id);
        selectedIds.value = selectedIds.value.filter(id => !pageIds.includes(id));
    }
};

const toggleSelection = (id: number) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(i => i !== id);
    } else {
        selectedIds.value.push(id);
    }
};

const bulkForm = useForm({
    ids: [] as number[],
    flag: '',
});

const submitBulkUpdate = (flag: string) => {
    if (selectedIds.value.length === 0) return;
    if (!confirm(`Bạn có chắc chắn muốn cập nhật ${selectedIds.value.length} giao dịch sang trạng thái ${flag}?`)) return;

    bulkForm.ids = selectedIds.value;
    bulkForm.flag = flag;

    bulkForm.post(routeTransactionsBulkUpdate.url(), {
        onSuccess: () => {
             selectedIds.value = []; // Clear selection
             bulkForm.reset();
        }
    });
};

// Edit Dialog Logic
const isEditOpen = ref(false);
const editingTransaction = ref<any>(null);
const editForm = useForm({
    profession_id: '',
    flag: '',
    ql_note: '',
});

const openEdit = (transaction: any) => {
    editingTransaction.value = transaction;
    editForm.profession_id = transaction.profession_id || '';

    editForm.flag = transaction.flag || 'review';
    editForm.ql_note = transaction.ql_note || '';
    isEditOpen.value = true;
};

const submitEdit = () => {
    if (!editingTransaction.value) return;
    const url = routeTransactionsUpdate.url({ id: editingTransaction.value.id || editingTransaction.value.cash_id });

    editForm.put(url, {
        onSuccess: () => {
            isEditOpen.value = false;
            editingTransaction.value = null;
        }
    });
};



const statusLabels: Record<string, string> = {
    valid: 'Đồng ý',
    review: 'Chờ duyệt',
    invalid: 'Từ chối'
};

const systemStatusLabels: Record<string, string> = {
    valid: 'Hợp lý',
    review: 'Ko rõ',
    invalid: 'Bất thường'
};

</script>

<template>
    <Head title="Quản lý Thu Chi" />

    <AppLayout>
        <template #header>
            <h2 class="font-bold text-xl text-zinc-800 dark:text-zinc-100 leading-tight">
                Quản lý Thu Chi
            </h2>
        </template>

        <div class="py-6 lg:py-12">
            <div class="max-w-9xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-zinc-900 overflow-hidden shadow-xl sm:rounded-lg p-4 sm:p-6 border border-zinc-200 dark:border-zinc-800 transition-colors duration-200">

                    <!-- Filters -->
                    <form @submit.prevent="submitFilter" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-6 items-end">
                        <div>
                            <Label class="block mb-2 font-medium dark:text-zinc-300">Cửa hàng</Label>
                            <select v-model="form.store_uid" class="w-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm h-10 border p-2">
                                <option value="">Tất cả</option>
                                <option v-for="store in stores" :key="store.id" :value="store.ipos_id">
                                    {{ store.short_name || store.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label class="block mb-2 font-medium dark:text-zinc-300">Mục chi</Label>
                            <select v-model="form.profession_id" class="w-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm h-10 border p-2">
                                <option value="">Tất cả</option>
                                <option v-for="prof in professions" :key="prof.id" :value="prof.id">
                                    {{ prof.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label class="block mb-2 font-medium dark:text-zinc-300">Từ ngày</Label>
                            <Input type="date" v-model="form.from_date" class="w-full h-10 dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700 block" />
                        </div>
                        <div>
                            <Label class="block mb-2 font-medium dark:text-zinc-300">Đến ngày</Label>
                            <Input type="date" v-model="form.to_date" class="w-full h-10 dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700 block" />
                        </div>
                        <div>
                            <Label class="block mb-2 font-medium dark:text-zinc-300">QL duyệt</Label>
                            <select v-model="form.flag" class="w-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm h-10 border p-2">
                                <option value="">Tất cả</option>
                                <option value="valid">Đồng ý</option>
                                <option value="review">Chưa rõ</option>
                                <option value="invalid">Từ chối</option>
                            </select>
                        </div>
                        <div>
                            <Label class="block mb-2 font-medium dark:text-zinc-300">Hệ thống duyệt</Label>
                            <select v-model="form.system_flag" class="w-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm h-10 border p-2">
                                <option value="">Tất cả</option>
                                <option value="valid">Hợp lý</option>
                                <option value="review">Ko rõ</option>
                                <option value="invalid">Bất thường</option>
                            </select>
                        </div>
                        <div>
                            <Label class="block mb-2 font-medium dark:text-zinc-300">Tìm kiếm (Note)</Label>
                            <Input type="text" v-model="form.search" placeholder="Nội dung..." class="w-full h-10 dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700 block" />
                        </div>
                        <div class="flex gap-2">
                            <Button type="submit" class="w-full md:w-auto h-10">Tìm kiếm</Button>
                        </div>
                    </form>

                    <!-- Stats Widget -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-gray-200 dark:border-zinc-800 flex flex-col">
                            <span class="text-xs font-medium text-gray-500 dark:text-zinc-500 uppercase tracking-wider mb-1">Tổng cộng</span>
                            <span class="text-2xl font-bold text-gray-900 dark:text-zinc-100">{{ stats.total }}</span>
                        </div>
                        <div class="bg-green-50/50 dark:bg-green-900/10 p-4 rounded-xl border border-green-100 dark:border-green-900/30 flex flex-col">
                            <span class="text-xs font-medium text-green-600 dark:text-green-500 uppercase tracking-wider mb-1">Đồng ý (Valid)</span>
                            <span class="text-2xl font-bold text-green-700 dark:text-green-400">{{ stats.valid_count }}</span>
                        </div>
                        <div class="bg-yellow-50/50 dark:bg-yellow-900/10 p-4 rounded-xl border border-yellow-100 dark:border-yellow-900/30 flex flex-col">
                            <span class="text-xs font-medium text-yellow-600 dark:text-yellow-500 uppercase tracking-wider mb-1">Chờ duyệt (Review)</span>
                            <span class="text-2xl font-bold text-yellow-700 dark:text-yellow-400">{{ stats.review_count }}</span>
                        </div>
                        <div class="bg-red-50/50 dark:bg-red-900/10 p-4 rounded-xl border border-red-100 dark:border-red-900/30 flex flex-col">
                            <span class="text-xs font-medium text-red-600 dark:text-red-500 uppercase tracking-wider mb-1">Từ chối (Invalid)</span>
                            <span class="text-2xl font-bold text-red-700 dark:text-red-400">{{ stats.invalid_count }}</span>
                        </div>
                    </div>

                    <!-- Bulk Actions Toolbar -->
                    <div v-if="selectedIds.length > 0" class="mb-4 p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg flex items-center justify-between animate-in fade-in slide-in-from-top-2">
                        <div class="text-indigo-700 dark:text-indigo-300 font-medium">
                            Đã chọn {{ selectedIds.length }} giao dịch
                        </div>
                        <div class="flex gap-2">
                            <Button size="sm" class="bg-green-600 hover:bg-green-700" @click="submitBulkUpdate('valid')">Đồng ý</Button>
                            <Button size="sm" class="bg-yellow-600 hover:bg-yellow-700" @click="submitBulkUpdate('review')">Chờ duyệt</Button>
                            <Button size="sm" class="bg-red-600 hover:bg-red-700" @click="submitBulkUpdate('invalid')">Từ chối</Button>
                        </div>
                    </div>

                    <!-- Desktop View: Table -->
                    <div class="hidden md:block overflow-x-auto border dark:border-zinc-700 rounded-lg">
                        <table class="w-full divide-y divide-gray-200 dark:divide-zinc-700">
                            <thead class="bg-gray-50 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <input type="checkbox" :checked="allSelected" @change="toggleSelectAll(($event.target as HTMLInputElement).checked)" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-zinc-800 dark:border-zinc-700">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Thời gian</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Cửa hàng</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Nội dung</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Số tiền</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Mục đích chi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">QL duyệt</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Hệ thống</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-700 text-sm">
                                <tr v-for="tran in transactions.data" :key="tran.id" class="hover:bg-gray-50 dark:hover:bg-zinc-800/50 transition-colors" :class="{'bg-indigo-50 dark:bg-indigo-900/10': selectedIds.includes(tran.id)}">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" :checked="selectedIds.includes(tran.id)" @change="toggleSelection(tran.id)" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-zinc-800 dark:border-zinc-700">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-zinc-400">{{ formatDate(tran.time) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-zinc-200">{{ getStoreName(tran.store_uid) }}</td>
                                    <td class="px-6 py-4 text-gray-900 dark:text-zinc-300 max-w-xs" :title="tran.note">
                                        <div>
                                            {{ tran.note }}
                                        </div>
                                        <div v-if="tran.system_flag === 'invalid' && tran.system_note" class="flex items-center gap-1 text-xs text-red-600 dark:text-red-500 mt-1 font-medium">
                                            <AlertCircle class="w-3 h-3" />
                                            {{ tran.system_note }}
                                        </div>
                                        <div v-if="tran.ql_note" class="flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-500 mt-1 font-medium">
                                            <User class="w-3 h-3" />
                                            {{ tran.ql_note }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-bold text-gray-900 dark:text-zinc-100">{{ formatCurrency(tran.amount) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                            {{ tran.profession ? tran.profession.name : 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="{
                                            'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full': true,
                                            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300': tran.flag === 'valid',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300': tran.flag === 'review',
                                            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300': tran.flag === 'invalid',
                                        }">
                                            {{ statusLabels[tran.flag] || tran.flag }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="{
                                            'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border border-zinc-200 dark:border-zinc-700': true,
                                            'text-green-600 dark:text-green-400': tran.system_flag === 'valid',
                                            'text-yellow-600 dark:text-yellow-400': tran.system_flag === 'review',
                                            'text-red-600 dark:text-red-400': tran.system_flag === 'invalid',
                                        }">
                                            {{ systemStatusLabels[tran.system_flag] || tran.system_flag || 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="openEdit(tran)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Sửa</button>
                                    </td>
                                </tr>
                                <tr v-if="transactions.data.length === 0">
                                    <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-zinc-500">Không có dữ liệu</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile View: Cards -->
                    <div class="md:hidden space-y-4">
                        <div v-if="transactions.data.length === 0" class="text-center text-gray-500 dark:text-zinc-500 py-8">
                            Không có dữ liệu
                        </div>
                        <div v-for="tran in transactions.data" :key="tran.id"
                             class="bg-white dark:bg-zinc-800/50 border border-gray-200 dark:border-zinc-700 rounded-lg p-4 space-y-3 shadow-sm transition-all"
                             :class="{'ring-2 ring-indigo-500 bg-indigo-50 dark:bg-indigo-900/10 border-indigo-200 dark:border-indigo-800': selectedIds.includes(tran.id)}">
                            <div class="flex justify-between items-start">
                                <div class="flex gap-3 items-start">
                                    <input type="checkbox" :checked="selectedIds.includes(tran.id)" @change="toggleSelection(tran.id)" class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-zinc-800 dark:border-zinc-700">
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-zinc-400">{{ formatDate(tran.time) }}</p>
                                        <h3 class="font-semibold text-gray-900 dark:text-zinc-100 mt-1">{{ formatCurrency(tran.amount) }}</h3>
                                    </div>
                                </div>
                                <span :class="{
                                    'px-2 py-1 text-xs font-semibold rounded-full': true,
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300': tran.flag === 'valid',
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300': tran.flag === 'review',
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300': tran.flag === 'invalid',
                                }">
                                    {{ statusLabels[tran.flag] || tran.flag }}
                                </span>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500 dark:text-zinc-500">Cửa hàng</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-zinc-200">{{ getStoreName(tran.store_uid) }}</p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500 dark:text-zinc-500">Nội dung</p>
                                <p class="text-sm text-gray-700 dark:text-zinc-300 line-clamp-2">
                                    {{ tran.note }}
                                </p>
                                <p v-if="tran.system_flag === 'invalid' && tran.system_note" class="flex items-center gap-1 text-xs text-red-600 dark:text-red-500 mt-1 font-medium">
                                    <AlertCircle class="w-3 h-3" />
                                    {{ tran.system_note }}
                                </p>
                                <p v-if="tran.ql_note" class="flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-500 mt-1 font-medium">
                                    <User class="w-3 h-3" />
                                    {{ tran.ql_note }}
                                </p>
                            </div>

                            <div class="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-zinc-700/50">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
                                    {{ tran.profession ? tran.profession.name : 'Chưa phân loại' }}
                                </span>
                                <button @click="openEdit(tran)" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                    Chỉnh sửa
                                </button>
                            </div>
                        </div>
                    </div>

                     <!-- Pagination -->
                    <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4" v-if="transactions.total > 0">
                        <div class="text-sm text-gray-500 dark:text-zinc-400 order-2 sm:order-1">
                            {{ transactions.from }}-{{ transactions.to }} / {{ transactions.total }}
                        </div>
                        <div class="flex flex-wrap gap-1 justify-center order-1 sm:order-2">
                             <Link v-for="(link, k) in transactions.links" :key="k"
                                :href="link.url || '#'"
                                v-html="link.label"
                                class="px-3 py-1.5 border rounded-md text-sm transition-colors"
                                :class="{
                                    'bg-indigo-600 text-white border-indigo-600': link.active,
                                    'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-600 dark:hover:bg-zinc-700': !link.active,
                                    'opacity-50 cursor-not-allowed': !link.url
                                }"
                             />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <Dialog :open="isEditOpen" @update:open="isEditOpen = false">
            <DialogContent class="sm:max-w-[425px] dark:bg-zinc-900 dark:border-zinc-800">
                <DialogHeader>
                    <DialogTitle class="dark:text-zinc-100">Cập nhật giao dịch</DialogTitle>
                    <DialogDescription class="dark:text-zinc-400">
                        Chỉnh sửa thông tin mục đích chi và trạng thái.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-4 items-start gap-4">
                        <div class="col-span-4 text-sm text-gray-700 dark:text-zinc-300 space-y-2">
                             <div class="bg-gray-50 dark:bg-zinc-800 p-3 rounded-md border dark:border-zinc-700">
                                <p><span class="font-semibold">Nhân viên note:</span> {{ editingTransaction?.note }}</p>
                                <p class="mt-1">
                                    <span class="font-semibold">Hệ thống nhận xét:</span>
                                    <span :class="{
                                        'font-bold ml-1': true,
                                        'text-green-600': editingTransaction?.system_flag === 'valid',
                                        'text-yellow-600': editingTransaction?.system_flag === 'review',
                                        'text-red-600': editingTransaction?.system_flag === 'invalid',
                                    }">{{ systemStatusLabels[editingTransaction.system_flag] || editingTransaction.system_flag || 'N/A' }}</span>
                                    <span> - {{ editingTransaction?.system_note }}</span>
                                </p>
                                <p class="mt-2 text-xs text-gray-500 dark:text-zinc-400">
                                    Bạn có thể tham khảo để đưa ra nhận định cuối cùng bên dưới
                                </p>
                             </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label class="text-right dark:text-zinc-300">Mục chi</Label>
                        <div class="col-span-3">
                             <select v-model="editForm.profession_id" class="w-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 rounded-md shadow-sm border p-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm h-10">
                                <option value="">-- Chọn tên mục chi --</option>
                                <option v-for="prof in professions" :key="prof.id" :value="prof.id">
                                    {{ prof.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label class="text-right dark:text-zinc-300">Trạng thái</Label>
                        <div class="col-span-3">
                            <select v-model="editForm.flag" class="w-full border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 rounded-md shadow-sm border p-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm h-10">
                                <option value="valid">Đồng ý</option>
                                <option value="review">Chờ duyệt</option>
                                <option value="invalid">Từ chối</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-4 items-center gap-4">
                         <Label class="text-right dark:text-zinc-300">Note thêm (nếu cần)</Label>
                         <Input v-model="editForm.ql_note" class="col-span-3 dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700" placeholder="Ghi chú của quản lý..." />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="secondary" @click="isEditOpen = false" class="dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">Hủy</Button>
                    <Button type="submit" @click="submitEdit" :disabled="editForm.processing">Lưu thay đổi</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

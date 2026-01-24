<script setup lang="ts">
import { computed } from 'vue';

interface DataPoint {
    label: string; // Store name
    value: number;
    color: string;
}

interface Group {
    label: string; // Category/Profession name 
    values: DataPoint[];
}

const props = defineProps<{
    title?: string;
    groups: Group[]; // Array of categories to display in this chart section
}>();

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value);
};

const numberToString = (value: number) => {
    if (value >= 1000000) {
        // e.g. 1,100,000 -> 1.1 -> 1tr1
        // e.g. 1,000,000 -> 1 -> 1tr
        const millions = value / 1000000;
        // Format to max 1 decimal place
        const formatted = millions.toFixed(1); 
        // Remove .0 if present
        const clean = formatted.replace(/\.0$/, '');
        // Replace dot with 'tr'
        return clean.replace('.', 'tr') + (clean.includes('.') ? '' : 'tr');
    } else {
        // < 1 million -> display k
        // e.g. 235000 -> 235k
        return Math.round(value / 1000) + 'k';
    }
};

// Calculate max value across all groups to determine bar height scale
const maxValue = computed(() => {
    let max = 0;
    props.groups.forEach(group => {
        group.values.forEach(point => {
            const val = point.value || 1000; // Treat 0 as at least 1000
            if (val > max) max = val;
        });
    });
    return max || 1;
});

</script>

<template>
    <div class="bg-white dark:bg-zinc-900 shadow-sm sm:rounded-lg p-4 sm:p-6 border border-zinc-200 dark:border-zinc-800">
        <h3 v-if="title" class="text-md sm:text-lg font-bold mb-4 dark:text-zinc-100 text-center">{{ title }}</h3>
        
        <div class="space-y-6">
            <div v-for="(group, gIndex) in groups" :key="gIndex" class="space-y-2">
                <div class="text-sm font-semibold text-gray-700 dark:text-zinc-300 border-b dark:border-zinc-700 pb-1 text-center">{{ group.label }}</div>
                
                <!-- Bars Container -->
                <div class="flex items-end justify-between gap-1 sm:gap-4 h-32 sm:h-40 pt-4 pb-2 relative bg-gray-50 dark:bg-zinc-800/50 rounded-md px-2">
                    
                   <div v-for="(point, pIndex) in group.values" :key="pIndex" class="flex-1 flex flex-col items-center justify-end h-full group relative">
                        <!-- Tooltip/Value on hover -->
                        <div class="absolute -top-12 left-1/2 transform -translate-x-1/2 bg-black text-white text-[10px] sm:text-xs rounded px-1 sm:px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-20 pointer-events-none">
                            {{ point.label }}: {{ formatCurrency(point.value) }}
                        </div>

                        <!-- Value Label -->
                        <div class="mb-1 text-[10px] sm:text-xs text-gray-600 dark:text-zinc-400 font-medium">
                            {{ numberToString(point.value || 1000) }}
                        </div>

                        <!-- Bar -->
                        <div 
                            class="w-full max-w-[20px] sm:max-w-[40px] rounded-t-sm transition-all duration-500 ease-out hover:opacity-80 border-t border-x border-white/10"
                            :style="{ 
                                height: `${((point.value || 1000) / maxValue) * 100}%`,
                                backgroundColor: point.color
                            }"
                        ></div>
                   </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-4 flex flex-wrap justify-center gap-4 text-xs">
             <!-- Assuming all groups have same stores in same order, use the first group to generate legend -->
             <template v-if="groups.length > 0 && groups[0].values.length > 0">
                 <div v-for="(point, i) in groups[0].values" :key="i" class="flex items-center gap-1.5">
                     <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: point.color }"></span>
                     <span class="text-gray-600 dark:text-zinc-400">{{ point.label }}</span>
                 </div>
             </template>
        </div>
    </div>
</template>

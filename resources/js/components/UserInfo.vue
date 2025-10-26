<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User | any; // Allow both Laravel User and Fabi user format
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

// Map Fabi user format to expected format
const userName = computed(() => {
    return props.user.full_name || props.user.name || 'Unknown User';
});

const userEmail = computed(() => {
    return props.user.email || '';
});

const userAvatar = computed(() => {
    return props.user.profile_image_path || props.user.avatar || '';
});

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => userAvatar.value && userAvatar.value !== '',
);
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="userAvatar" :alt="userName" />
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(userName) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ userName }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            userEmail
        }}</span>
    </div>
</template>

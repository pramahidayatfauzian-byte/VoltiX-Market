<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);

const displayName = computed(
    () =>
        props.user.nama_lengkap ||
        props.user.name ||
        props.user.username ||
        'User',
);
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-full">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="displayName" />
        <AvatarFallback class="rounded-full bg-teal-500 font-semibold text-white">
            {{ getInitials(displayName) }}
        </AvatarFallback>
    </Avatar>

    <div v-if="showEmail" class="grid flex-1 text-left text-sm leading-tight">
        <span class="text-muted-foreground truncate text-xs">{{
            user.email ?? user.username
        }}</span>
    </div>
</template>

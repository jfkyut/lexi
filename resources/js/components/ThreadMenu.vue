<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Ellipsis, Pin, PinOff, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroy as destroyThread,
    pin as pinThread,
    update as updateThread,
} from '@/actions/App/Http/Controllers/ThreadController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    thread: {
        id: string;
        title: string;
        is_pinned: boolean;
    };
}>();

const renameDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const renameForm = useForm({
    title: '',
});

function beginRename(): void {
    renameForm.title = props.thread.title;
    renameForm.clearErrors();
    renameDialogOpen.value = true;
}

function renameThread(): void {
    renameForm.patch(updateThread.url(props.thread.id), {
        preserveScroll: true,
        onSuccess: () => {
            renameDialogOpen.value = false;
        },
    });
}

function togglePinned(): void {
    router.patch(
        pinThread.url(props.thread.id),
        { is_pinned: !props.thread.is_pinned },
        { preserveScroll: true },
    );
}

function deleteThread(): void {
    router.delete(destroyThread.url(props.thread.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                class="absolute top-1/2 right-1 size-7 -translate-y-1/2 opacity-0 transition-opacity group-focus-within/thread:opacity-100 group-hover/thread:opacity-100 focus-visible:opacity-100"
                :aria-label="`Options for ${props.thread.title}`"
                :title="`Options for ${props.thread.title}`"
            >
                <Ellipsis class="size-4" aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>
        <span
            v-if="props.thread.is_pinned"
            class="pointer-events-none absolute top-1/2 right-1 flex size-7 -translate-y-1/2 items-center justify-center text-muted-foreground group-focus-within/thread:opacity-0 group-hover/thread:opacity-0"
            role="img"
            aria-label="Pinned"
            title="Pinned"
        >
            <Pin class="size-3.5" aria-hidden="true" />
        </span>
        <DropdownMenuContent align="start" side="bottom" class="w-44">
            <DropdownMenuItem @select="beginRename">
                <Pencil aria-hidden="true" />
                Rename
            </DropdownMenuItem>
            <DropdownMenuItem @select="togglePinned">
                <PinOff v-if="props.thread.is_pinned" aria-hidden="true" />
                <Pin v-else aria-hidden="true" />
                {{ props.thread.is_pinned ? 'Unpin' : 'Pin' }}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                variant="destructive"
                @select="deleteDialogOpen = true"
            >
                <Trash2 aria-hidden="true" />
                Delete
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <Dialog v-model:open="renameDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Rename chat</DialogTitle>
                <DialogDescription>
                    Choose a new name for this conversation.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-2" @submit.prevent="renameThread">
                <Input
                    v-model="renameForm.title"
                    maxlength="255"
                    aria-label="Chat name"
                    autofocus
                />
                <p
                    v-if="renameForm.errors.title"
                    class="text-sm text-destructive"
                    role="alert"
                >
                    {{ renameForm.errors.title }}
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="renameForm.processing"
                        @click="renameDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            renameForm.processing || !renameForm.title.trim()
                        "
                    >
                        Save
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete chat?</DialogTitle>
                <DialogDescription>
                    “{{ props.thread.title }}” and all its messages and
                    attachments will be permanently deleted. This action cannot
                    be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="deleteDialogOpen = false"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    @click="deleteThread"
                >
                    Delete chat
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

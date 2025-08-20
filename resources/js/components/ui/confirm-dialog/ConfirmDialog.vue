<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface Props {
  open: boolean;
  title: string;
  description?: string;
  confirmText?: string;
  cancelText?: string;
  confirmVariant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  cancelVariant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
}

const props = withDefaults(defineProps<Props>(), {
  description: '',
  confirmText: 'Confirm',
  cancelText: 'Cancel',
  confirmVariant: 'destructive',
  cancelVariant: 'outline',
});

const emit = defineEmits(['update:open', 'confirm', 'cancel']);

// Prevent body scrolling when dialog is open
const preventBodyScroll = (isOpen: boolean) => {
  if (isOpen) {
    document.body.classList.add('overflow-hidden');
  } else {
    document.body.classList.remove('overflow-hidden');
  }
};

// Watch for open prop changes
watch(() => props.open, (newVal) => {
  preventBodyScroll(newVal);
}, { immediate: true });

// Clean up on component unmount
onUnmounted(() => {
  document.body.classList.remove('overflow-hidden');
});

const handleConfirm = () => {
  emit('confirm');
  emit('update:open', false);
};

const handleCancel = () => {
  emit('cancel');
  emit('update:open', false);
};
</script>

<template>
  <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
    <DialogContent class="sm:max-w-md fixed-dialog">
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription v-if="description">
          {{ description }}
        </DialogDescription>
      </DialogHeader>
      
      <slot></slot>
      
      <DialogFooter class="flex gap-2 justify-end">
        <Button :variant="cancelVariant" @click="handleCancel">
          {{ cancelText }}
        </Button>
        <Button :variant="confirmVariant" @click="handleConfirm">
          {{ confirmText }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { X, CheckCircle, XCircle, AlertTriangle, Info } from 'lucide-vue-next';
import type { Flash } from '@/types';

interface FlashMessage {
  type: 'success' | 'error' | 'warning' | 'info';
  message: string;
}

const page = usePage();
const flashMessages = ref<FlashMessage[]>([]);

// Add a flash message
const addFlashMessage = (type: 'success' | 'error' | 'warning' | 'info', message: string) => {
  // Don't add empty messages
  if (!message) return;
  
  // Add the message
  const newMessage = { type, message };
  flashMessages.value.push(newMessage);
  
  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    dismissFlashMessage(newMessage);
  }, 5000);
};

// Dismiss a flash message
const dismissFlashMessage = (message: FlashMessage) => {
  const index = flashMessages.value.indexOf(message);
  if (index !== -1) {
    flashMessages.value.splice(index, 1);
  }
};

// Get alert variant based on message type
const getAlertVariant = (type: string) => {
  switch (type) {
    case 'error':
      return 'destructive';
    case 'success':
    case 'warning':
    case 'info':
    default:
      return 'default';
  }
};

// Get icon component based on message type
const getIconComponent = (type: string) => {
  switch (type) {
    case 'success':
      return CheckCircle;
    case 'error':
      return XCircle;
    case 'warning':
      return AlertTriangle;
    case 'info':
      return Info;
    default:
      return Info;
  }
};

// Get custom styling for different message types
const getAlertStyling = (type: string) => {
  switch (type) {
    case 'success':
      return 'border-green-200 bg-green-50 text-green-800 [&>svg]:text-green-600';
    case 'error':
      return 'border-red-200 bg-red-50 text-red-800 [&>svg]:text-red-600';
    case 'warning':
      return 'border-yellow-200 bg-yellow-50 text-yellow-800 [&>svg]:text-yellow-600';
    case 'info':
      return 'border-blue-200 bg-blue-50 text-blue-800 [&>svg]:text-blue-600';
    default:
      return '';
  }
};

// Watch for flash messages from the backend
watch(() => page.props.flash, (newFlash: Flash) => {
  
  if (newFlash?.success) {
    addFlashMessage('success', newFlash.success);
  }
  
  if (newFlash?.error) {
    addFlashMessage('error', newFlash.error);
  }
  
  if (newFlash?.warning) {
    addFlashMessage('warning', newFlash.warning);
  }
  
  if (newFlash?.info) {
    addFlashMessage('info', newFlash.info);
  }
}, { deep: true, immediate: true });
</script>

<template>
  <div class="fixed top-4 right-4 z-50 w-80 space-y-2">
    <Alert 
      v-for="(flash, index) in flashMessages" 
      :key="index"
      :variant="getAlertVariant(flash.type)"
      :class="[
        'shadow-lg transition-all duration-300 ease-in-out relative animate-in slide-in-from-right-full',
        getAlertStyling(flash.type)
      ]"
    >
      <!-- Icon positioned in the grid -->
      <component :is="getIconComponent(flash.type)" class="h-4 w-4" />
      
      <!-- AlertDescription will automatically position itself in col-start-2 -->
      <AlertDescription class="pr-8">
        {{ flash.message }}
      </AlertDescription>
      
      <!-- Close button -->
      <Button
        variant="ghost"
        size="sm"
        class="absolute top-2 right-2 h-6 w-6 p-0 hover:bg-black/10 rounded-full opacity-70 hover:opacity-100 transition-opacity"
        @click="dismissFlashMessage(flash)"
      >
        <X class="h-3 w-3" />
        <span class="sr-only">Dismiss</span>
      </Button>
    </Alert>
  </div>
</template>
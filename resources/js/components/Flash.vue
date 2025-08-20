<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Alert } from '@/components/ui/alert';

interface FlashMessage {
  type: 'success' | 'error' | 'warning' | 'info';
  message: string;
}

const page = usePage();
const flashMessages = ref<FlashMessage[]>([]);

// Watch for flash messages from the backend
watch(() => page.props.flash, (newFlash) => {
  if (newFlash.success) {
    addFlashMessage('success', newFlash.success);
  }
  
  if (newFlash.error) {
    addFlashMessage('error', newFlash.error);
  }
  
  if (newFlash.warning) {
    addFlashMessage('warning', newFlash.warning);
  }
  
  if (newFlash.info) {
    addFlashMessage('info', newFlash.info);
  }
}, { deep: true, immediate: true });

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
</script>

<template>
  <div class="fixed top-4 right-4 z-50 w-80 space-y-2">
    <Alert 
      v-for="(flash, index) in flashMessages" 
      :key="index"
      :type="flash.type"
      :message="flash.message"
      class="shadow-md transition-all duration-300 ease-in-out"
      @dismiss="dismissFlashMessage(flash)"
    />
  </div>
</template>

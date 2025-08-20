<script setup lang="ts">
import { XCircle, CheckCircle, AlertCircle, Info } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
  type?: 'success' | 'error' | 'warning' | 'info';
  title?: string;
  message: string;
  dismissable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'info',
  title: '',
  dismissable: true,
});

const emit = defineEmits(['dismiss']);

const alertClasses = computed(() => {
  const classes = {
    success: 'bg-green-50 text-green-800 border-green-200',
    error: 'bg-red-50 text-red-800 border-red-200',
    warning: 'bg-amber-50 text-amber-800 border-amber-200',
    info: 'bg-blue-50 text-blue-800 border-blue-200',
  };
  
  return classes[props.type];
});

const alertIcon = computed(() => {
  const icons = {
    success: CheckCircle,
    error: XCircle,
    warning: AlertCircle,
    info: Info,
  };
  
  return icons[props.type];
});

const iconClasses = computed(() => {
  const classes = {
    success: 'text-green-500',
    error: 'text-red-500',
    warning: 'text-amber-500',
    info: 'text-blue-500',
  };
  
  return classes[props.type];
});
</script>

<template>
  <div :class="['flex items-start gap-3 rounded-lg border p-4 mb-4', alertClasses]">
    <component :is="alertIcon" :class="['h-5 w-5 mt-0.5', iconClasses]" />
    <div class="flex-1">
      <h3 v-if="title" class="font-medium mb-1">{{ title }}</h3>
      <div class="text-sm">{{ message }}</div>
    </div>
    <button v-if="dismissable" @click="emit('dismiss')" class="text-muted-foreground hover:text-foreground">
      <span class="sr-only">Dismiss</span>
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </button>
  </div>
</template>

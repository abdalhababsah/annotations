<template>
  <span
    :class="[
      'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 gap-1 transition-colors overflow-hidden',
      variantClasses,
    ]"
  >
    <slot />
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  variant?: 'default' | 'secondary' | 'destructive' | 'outline'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default'
})

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'default':
      return 'border-transparent bg-primary text-primary-foreground hover:bg-primary/90'
    case 'secondary':
      return 'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/90'
    case 'destructive':
      return 'border-transparent bg-destructive text-white hover:bg-destructive/90'
    case 'outline':
      return 'text-foreground hover:bg-accent hover:text-accent-foreground'
    default:
      return 'border-transparent bg-primary text-primary-foreground hover:bg-primary/90'
  }
})
</script>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'

const props = defineProps<{ projectId: number; open: boolean }>()
const emit = defineEmits<{ (e: 'update:open', v: boolean): void; (e: 'batch-created'): void }>()

const name = ref('')
const description = ref('')
const loading = ref(false)
const errors = ref<{ name?: string; description?: string }>({})

watch(() => props.open, (v) => {
  if (v) {
    name.value = ''
    description.value = ''
    errors.value = {}
  }
})

const submit = () => {
  if (!name.value.trim()) {
    errors.value = { name: 'Batch name is required.' }
    return
  }
  if (name.value.length > 255) {
    errors.value = { name: 'Batch name cannot exceed 255 characters.' }
    return
  }
  if (description.value && description.value.length > 1000) {
    errors.value = { description: 'Description cannot exceed 1000 characters.' }
    return
  }

  loading.value = true
  errors.value = {}

  router.post(
    route('admin.projects.batches.store', props.projectId),
    {
      name: name.value.trim(),
      description: description.value.trim() || null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => { loading.value = false },
      onSuccess: () => {
        emit('batch-created')
        emit('update:open', false)
        name.value = ''
        description.value = ''
      },
      onError: (serverErrors) => { errors.value = serverErrors }
    }
  )
}
</script>

<template>
  <Dialog :open="open" @update:open="v => emit('update:open', v)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Create New Batch</DialogTitle>
        <!-- 👇 Add a description for accessibility -->
        <DialogDescription>
          Provide a batch name and an optional description, then click “Create”.
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-4">
        <div class="space-y-2">
          <Label for="batch-name">Batch Name</Label>
          <Input
            id="batch-name"
            v-model="name"
            placeholder="Enter batch name..."
            :aria-invalid="!!errors.name"
            :class="{ 'border-destructive': errors.name }"
            @keydown.enter.prevent="submit"
          />
          <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name }}</p>
        </div>

        <div class="space-y-2">
          <Label for="batch-description">Description (Optional)</Label>
          <Textarea
            id="batch-description"
            v-model="description"
            placeholder="Enter batch description..."
            rows="3"
            :aria-invalid="!!errors.description"
            :class="{ 'border-destructive': errors.description }"
          />
          <p v-if="errors.description" class="text-sm text-destructive">{{ errors.description }}</p>
          <p class="text-xs text-muted-foreground">{{ description.length }}/1000 characters</p>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <Button variant="outline" @click="emit('update:open', false)" :disabled="loading">Cancel</Button>
          <Button @click="submit" :disabled="loading || !name.trim()">
            <span v-if="loading" class="flex items-center gap-2">
              <div class="w-4 h-4 border-2 border-background border-t-transparent rounded-full animate-spin"></div>
              Creating...
            </span>
            <span v-else>Create Batch</span>
          </Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

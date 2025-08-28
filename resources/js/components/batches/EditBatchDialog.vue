<!-- resources/js/components/batches/EditBatchDialog.vue -->
<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { AlertTriangle } from 'lucide-vue-next'

const props = defineProps<{
  projectId: number
  open: boolean
  batch: {
    id: number
    name: string
    description: string | null
    status: string
    total_tasks: number
  } | null
}>()

const emit = defineEmits<{ (e: 'update:open', v: boolean): void; (e: 'batch-updated'): void }>()

const name = ref<string>('')
const description = ref<string>('')
const loading = ref(false)
const errors = ref<{ name?: string; description?: string }>({})

// Hydrate form when dialog opens or batch changes
function hydrateFromProps() {
  if (!props.batch) return
  name.value = props.batch.name || ''
  description.value = props.batch.description || ''
  errors.value = {}
}

watch(() => props.open, (v) => { 
  if (v) hydrateFromProps() 
})

watch(() => props.batch?.id, () => { 
  if (props.open) hydrateFromProps() 
})

// Check if batch can be edited (only draft batches)
const canEdit = computed(() => {
  return props.batch?.status === 'draft'
})

// Check if form has changes
const hasChanges = computed(() => {
  if (!props.batch) return false
  return name.value !== props.batch.name || 
         description.value !== (props.batch.description || '')
})

const submit = () => {
  if (!props.batch || !canEdit.value) return
  
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
  
  router.put(
    route('admin.projects.batches.update', [props.projectId, props.batch.id]),
    {
      name: name.value.trim(),
      description: description.value.trim() || null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        loading.value = false
      },
      onSuccess: () => {
        emit('batch-updated')
        emit('update:open', false)
      },
      onError: (serverErrors) => {
        errors.value = serverErrors
      }
    }
  )
}
</script>

<template>
  <Dialog :open="open" @update:open="v => emit('update:open', v)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Edit Batch</DialogTitle>
      </DialogHeader>

      <div v-if="batch" class="space-y-4">
        <!-- Status warning for non-draft batches -->
        <Alert v-if="!canEdit" class="border-amber-200 bg-amber-50">
          <AlertTriangle class="h-4 w-4 text-amber-600" />
          <AlertDescription class="text-amber-800">
            Only draft batches can be edited. This batch is currently <strong>{{ batch.status }}</strong>.
          </AlertDescription>
        </Alert>

        <!-- Batch info -->
        <div class="text-sm space-y-1">
          <div class="font-medium">{{ batch.name }}</div>
          <div class="text-muted-foreground">
            {{ batch.total_tasks }} task{{ batch.total_tasks !== 1 ? 's' : '' }} • {{ batch.status }}
          </div>
        </div>

        <div class="space-y-2">
          <Label for="batch-name">Batch Name</Label>
          <Input
            id="batch-name"
            v-model="name"
            placeholder="Enter batch name..."
            :class="{ 'border-destructive': errors.name }"
            :disabled="!canEdit"
            @keydown.enter.prevent="submit"
          />
          <p v-if="errors.name" class="text-sm text-destructive">
            {{ errors.name }}
          </p>
        </div>

        <div class="space-y-2">
          <Label for="batch-description">Description (Optional)</Label>
          <Textarea
            id="batch-description"
            v-model="description"
            placeholder="Enter batch description..."
            rows="3"
            :class="{ 'border-destructive': errors.description }"
            :disabled="!canEdit"
          />
          <p v-if="errors.description" class="text-sm text-destructive">
            {{ errors.description }}
          </p>
          <p class="text-xs text-muted-foreground">
            {{ description.length }}/1000 characters
          </p>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <Button 
            variant="outline" 
            @click="emit('update:open', false)"
            :disabled="loading"
          >
            Cancel
          </Button>
          <Button 
            @click="submit"
            :disabled="loading || !name.trim() || !canEdit || !hasChanges"
          >
            <span v-if="loading" class="flex items-center gap-2">
              <div class="w-4 h-4 border-2 border-background border-t-transparent rounded-full animate-spin"></div>
              Updating...
            </span>
            <span v-else>Save Changes</span>
          </Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
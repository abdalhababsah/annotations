<!-- CreateLabelModal.vue -->
<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'

type Emits = {
  (e: 'close'): void
  (e: 'create', payload: { name: string; color: string }): void
}

const props = defineProps<{
  open: boolean
}>()
const emit = defineEmits<Emits>()

const localOpen = ref(props.open)
watch(() => props.open, v => (localOpen.value = v))
watch(localOpen, v => { if (!v) emit('close') })

const name = ref('')
const color = ref('#6B7280')

const canSubmit = computed(() => name.value.trim().length > 0)

const onSubmit = () => {
  if (!canSubmit.value) return
  emit('create', { name: name.value.trim(), color: color.value })
  name.value = ''
  color.value = '#6B7280'
  localOpen.value = false
}
</script>

<template>
  <Dialog v-model:open="localOpen">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Create a custom label</DialogTitle>
        <DialogDescription>
          Use this for segments that don’t match existing labels.
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-4">
        <div>
          <Label for="clm-name">Label Name</Label>
          <Input id="clm-name" v-model="name" placeholder="e.g., Applause, Background music" />
        </div>
        <div>
          <Label for="clm-color">Label Color</Label>
          <Input id="clm-color" v-model="color" type="color" class="w-full h-10" />
        </div>
      </div>

      <DialogFooter class="mt-4">
        <Button variant="outline" @click="localOpen = false">Cancel</Button>
        <Button :disabled="!canSubmit" @click="onSubmit">Add Label</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'

const props = defineProps<{ projectId: number; open: boolean }>()
const emit = defineEmits<{ (e:'update:open', v:boolean): void; (e:'member-added'): void }>()
const users = ref<Array<{id:number; name:string; email:string; role:string}>>([])
const userId = ref<number | null>(null)
const role = ref<string>('annotator')
const workload = ref<number | null>(null)
const loading = ref(false)

const loadUsers = async () => {
  try {
    const res = await fetch(route('admin.projects.members.available-users', props.projectId))
    const json = await res.json()
    users.value = json.users || []
  } catch {}
}

watch(() => props.open, v => { if (v) loadUsers() })

// ⇅ bridge: number|null <-> string for the input
const workloadInput = computed<string>({
  get: () => (workload.value ?? '').toString(),
  set: (val: string) => {
    const s = (val ?? '').trim()
    if (s === '') { workload.value = null; return }
    const n = Number(s)
    workload.value = Number.isFinite(n) ? n : workload.value
  }
})

// simple validation: optional, but if provided must be 1..50
const workloadError = computed(() => {
  if (workload.value === null) return ''
  if (workload.value < 1) return 'Workload must be at least 1, or leave empty.'
  if (workload.value > 50) return 'Workload cannot exceed 50.'
  return ''
})

const submit = () => {
  if (!userId.value || workloadError.value) return
  loading.value = true
  router.post(route('admin.projects.members.store', props.projectId), {
    user_id: userId.value,
    role: role.value,
    workload_limit: workload.value === null ? null : Math.floor(workload.value),
  }, {
    preserveScroll: true, preserveState: true,
    onFinish: () => loading.value = false,
    onSuccess: () => {
      emit('member-added')
      emit('update:open', false)
      userId.value = null
      workload.value = null
      role.value = 'annotator'
    }
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="v => emit('update:open', v)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Assign Member</DialogTitle>
      </DialogHeader>

      <div class="space-y-3">
        <Select v-model="userId">
          <SelectTrigger class="w-full"><SelectValue placeholder="Select user" /></SelectTrigger>
          <SelectContent class="w-[--radix-select-trigger-width] max-w-[--radix-select-content-available-width]">
            <SelectGroup>
              <SelectItem v-for="u in users" :key="u.id" :value="u.id">
                <div class="flex items-center justify-between gap-2">
                  <span>{{ u.name }}</span>
                  <span class="text-xs text-muted-foreground">{{ u.email }}</span>
                </div>
              </SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>

        <Select v-model="role">
          <SelectTrigger class="w-full"><SelectValue placeholder="Select role" /></SelectTrigger>
          <SelectContent class="w-[--radix-select-trigger-width] max-w-[--radix-select-content-available-width]">
            <SelectGroup>
              <SelectItem value="annotator">Annotator</SelectItem>
              <SelectItem value="reviewer">Reviewer</SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>

        <div>
          <Input
            type="number"
            min="1"
            max="50"
            v-model="workloadInput"
            placeholder="Workload limit (optional)"
          />
          <p v-if="workloadError" class="mt-1 text-xs text-destructive">{{ workloadError }}</p>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
          <Button :disabled="loading || !userId || !!workloadError" @click="submit">Assign</Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'

type Role = 'annotator' | 'reviewer' | 'project_admin'

const props = defineProps<{ projectId: number; open: boolean }>()
const emit  = defineEmits<{ (e:'update:open', v:boolean): void; (e:'member-added'): void }>()

const users = ref<Array<{ id:number; name:string; email:string; role:string }>>([])
const loading = ref(false)
const errors  = ref<Record<string, string>>({})

/** Select values are strings — keep as string, parse on submit */
const userIdStr = ref<string>('')
const role      = ref<Role>('annotator')

/** Text buffer; '' => null on submit */
const workloadText = ref<string>('')

/** fetch available users when opened */
async function loadUsers() {
  try {
    const res = await fetch(route('admin.projects.members.available-users', props.projectId))
    const json = await res.json()
    users.value = Array.isArray(json?.users) ? json.users : []
  } catch {
    users.value = []
  }
}

function resetForm() {
  userIdStr.value  = ''
  role.value       = 'annotator'
  workloadText.value = ''
  errors.value     = {}
}

watch(() => props.open, (v) => {
  if (v) { resetForm(); loadUsers() }
})

/** parsed & validated workload */
const parsedWorkload = computed<number | null>(() => {
  const s = workloadText.value.trim()
  if (s === '') return null
  const n = Math.floor(Number(s))
  return Number.isFinite(n) ? n : null
})

const workloadError = computed<string>(() => {
  const w = parsedWorkload.value
  if (w === null) return ''
  if (w < 1)  return 'Workload must be at least 1, or leave it empty.'
  if (w > 50) return 'Workload cannot exceed 50.'
  return ''
})

const canSubmit = computed<boolean>(() => {
  return !!userIdStr.value && !workloadError.value && !loading.value
})

function submit() {
  errors.value = {}
  if (!userIdStr.value) { errors.value.user_id = 'Select a user.'; return }
  if (workloadError.value) return

  const uid = Number(userIdStr.value)
  const w   = parsedWorkload.value
  const clamped = w === null ? null : Math.max(1, Math.min(50, w))

  loading.value = true
  router.post(
    route('admin.projects.members.store', props.projectId),
    {
      user_id: uid,
      role: role.value,
      workload_limit: clamped, // ← NUMBER or NULL (never an empty string)
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish:   () => { loading.value = false },
      onSuccess:  () => {
        emit('member-added')
        emit('update:open', false)
        resetForm()
      },
      onError: (serverErrors: Record<string, string>) => {
        errors.value = serverErrors || {}
      },
    }
  )
}
</script>

<template>
  <Dialog :open="open" @update:open="v => emit('update:open', v)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Assign Member</DialogTitle>
      </DialogHeader>

      <div class="space-y-3">
        <!-- User -->
        <div>
          <Select v-model="userIdStr">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select user" />
            </SelectTrigger>
            <SelectContent class="w-[--radix-select-trigger-width] max-w-[--radix-select-content-available-width]">
              <SelectGroup>
                <SelectItem
                  v-for="u in users"
                  :key="u.id"
                  :value="String(u.id)"
                >
                  <div class="flex items-center justify-between gap-2">
                    <span>{{ u.name }}</span>
                    <span class="text-xs text-muted-foreground">{{ u.email }}</span>
                  </div>
                </SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>
          <p v-if="errors.user_id" class="mt-1 text-xs text-destructive">{{ errors.user_id }}</p>
        </div>

        <!-- Role -->
        <div>
          <Select v-model="role">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select role" />
            </SelectTrigger>
            <SelectContent class="w-[--radix-select-trigger-width] max-w-[--radix-select-content-available-width]">
              <SelectGroup>
                <SelectItem value="annotator">Annotator</SelectItem>
                <SelectItem value="reviewer">Reviewer</SelectItem>
                <SelectItem value="project_admin">Project Admin</SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>
          <p v-if="errors.role" class="mt-1 text-xs text-destructive">{{ errors.role }}</p>
        </div>

        <!-- Workload (optional) -->
        <div>
          <Input
            type="number"
            inputmode="numeric"
            min="1"
            max="50"
            step="1"
            v-model="workloadText"
            @update:modelValue="(v:any) => { workloadText = (v ?? '') + '' }"
            placeholder="Workload limit (optional)"
            @keydown.enter.prevent="submit"
          />
          <p v-if="workloadError" class="mt-1 text-xs text-destructive">{{ workloadError }}</p>
          <p v-else-if="errors.workload_limit" class="mt-1 text-xs text-destructive">{{ errors.workload_limit }}</p>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-2 pt-2">
          <Button variant="outline" :disabled="loading" @click="emit('update:open', false)">Cancel</Button>
          <Button :disabled="!canSubmit" @click="submit">
            <span v-if="loading" class="flex items-center gap-2">
              <div class="w-4 h-4 border-2 border-background border-t-transparent rounded-full animate-spin" />
              Assigning…
            </span>
            <span v-else>Assign</span>
          </Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

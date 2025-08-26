<!-- resources/js/components/projects/EditMemberDialog.vue -->
<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'

const props = defineProps<{
  projectId: number
  open: boolean
  member: {
    id: number
    role: 'annotator' | 'reviewer' | 'project_admin' | string
    is_active: boolean
    workload_limit: number | null
    user: { id:number; name:string; email:string }
  } | null
}>()

const emit = defineEmits<{ (e:'update:open', v:boolean): void; (e:'member-updated'): void }>()

const role = ref<'annotator' | 'reviewer' | 'project_admin'>('annotator')
const isActive = ref<boolean>(true)

/** Text buffer shown in the input; '' => null (no cap) */
const workloadText = ref<string>('')

/** hydrate defaults whenever dialog opens or member changes */
function hydrateFromProps() {
  if (!props.member) return
  role.value = (props.member.role as any) ?? 'annotator'
  isActive.value = !!props.member.is_active
  workloadText.value = props.member.workload_limit == null ? '' : String(props.member.workload_limit)
}
watch(() => props.open, (o) => { if (o) hydrateFromProps() }, { immediate: true })
watch(() => props.member?.id, () => { if (props.open) hydrateFromProps() })

/** parse & validate (keep your number-or-null semantics) */
const parsedWorkload = computed<number | null>(() => {
  const s = workloadText.value.trim()
  if (s === '') return null
  const n = Math.floor(Number(s))
  return Number.isFinite(n) ? n : null
})
const workloadError = computed<string>(() => {
  const w = parsedWorkload.value
  if (w === null) return ''
  if (w < 1) return 'Workload must be at least 1, or leave empty.'
  if (w > 50) return 'Workload cannot exceed 50.'
  return ''
})

const loading = ref(false)

const submit = () => {
  if (!props.member) return
  if (workloadError.value) return

  const w = parsedWorkload.value
  const clamped = w === null ? null : Math.max(1, Math.min(50, w))

  loading.value = true
  router.patch(
    route('admin.projects.members.update', [props.projectId, props.member.id]),
    {
      role: role.value,
      workload_limit: clamped,   // number or null (unchanged logic)
      is_active: !!isActive.value,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => { loading.value = false },
      onSuccess: () => {
        emit('member-updated')
        emit('update:open', false)
      },
    }
  )
}
</script>

<template>
  <Dialog :open="open" @update:open="v => emit('update:open', v)">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader><DialogTitle>Edit Member</DialogTitle></DialogHeader>

      <div v-if="member" class="space-y-3">
        <div class="text-sm">
          <div class="font-medium">{{ member.user.name }}</div>
          <div class="text-muted-foreground">{{ member.user.email }}</div>
        </div>

        <Select v-model="role">
          <SelectTrigger class="w-full"><SelectValue placeholder="Role" /></SelectTrigger>
          <SelectContent class="w-[--radix-select-trigger-width] max-w-[--radix-select-content-available-width]">
            <SelectGroup>
              <SelectItem value="annotator">Annotator</SelectItem>
              <SelectItem value="reviewer">Reviewer</SelectItem>
              <SelectItem value="project_admin">Project Admin</SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>

        <div>
          <!-- Use v-model (Input emits update:modelValue). Also handle the event explicitly for safety. -->
          <Input
            v-model="workloadText"
            @update:modelValue="(v:any) => { workloadText = (v ?? '') + '' }"
            type="number"
            inputmode="numeric"
            min="1"
            max="50"
            step="1"
            placeholder="Workload limit (leave empty for no cap)"
            @keydown.enter.prevent="submit"
          />
          <p v-if="workloadError" class="mt-1 text-xs text-destructive">{{ workloadError }}</p>
        </div>

        <div class="flex items-center gap-2">
          <input id="active" type="checkbox" v-model="isActive" class="h-4 w-4 rounded border" />
          <label for="active" class="text-sm">Active</label>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
          <Button :disabled="loading || !!workloadError" @click="submit">Save</Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

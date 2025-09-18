<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Progress } from '@/components/ui/progress'
import { Separator } from '@/components/ui/separator'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { 
  Edit3,
  Timer,
  AlertTriangle,
  Music,
  Save,
  CheckCircle2,
  SkipForward,
  FileAudio,
  CheckSquare,
  AlertCircle,
  Star
} from 'lucide-vue-next'

type Dim = {
  id: number
  name: string
  description: string | null
  dimension_type: 'categorical' | 'numeric_scale'
  scale_min: number | null
  scale_max: number | null
  is_required: boolean
  values: { value: string; label: string }[]
}

const props = defineProps<{
  project: { id: number; name: string; task_time_minutes: number | null }
  task: {
    id: number
    status: string
    expires_at: string | null
    audio: { id: number | null; filename: string | null; url: string | null; duration: number | null }
  }
  dimensions: Dim[]
  draft: {
    annotation_id: number | null
    values: Array<{ dimension_id: number; selected_value: string | null; numeric_value: number | null; notes: string | null }>
  }
}>()

/* ---------- Skip Reasons (same as review) ---------- */
const SKIP_REASONS = [
  { value: 'technical_issue', label: 'Technical issue' },
  { value: 'unclear_audio', label: 'Unclear audio' },
  { value: 'unclear_annotation', label: 'Unclear instruction' },
  { value: 'personal_reason', label: 'Personal reason' },
  { value: 'other', label: 'Other' },
]

/* ---------- Draft state (reactive) ---------- */
type Val = { selected_value: string | null; numeric_value: number | null; notes: string | null }
const values = reactive<Record<number, Val>>({})
// Categorical radio selection by OPTION INDEX to avoid duplicate-value collisions
const choiceIndex = reactive<Record<number, number | null>>({})

/* Seed from server draft + dimensions */
props.dimensions.forEach(d => {
  const v = props.draft.values?.find(x => x.dimension_id === d.id)
  const selected = v?.selected_value ?? null
  const numeric = (v?.numeric_value ?? null) as number | null
  const notes   = v?.notes ?? null
  values[d.id] = { selected_value: selected, numeric_value: numeric, notes }
  choiceIndex[d.id] = d.dimension_type === 'categorical'
    ? (selected != null ? d.values.findIndex(o => o.value === selected) : null)
    : null
})

/* ---------- LocalStorage autosave ---------- */
const draftKey = computed(() => `draft:project:${props.project.id}:task:${props.task.id}:user:self`)

const loadLocal = () => {
  const raw = localStorage.getItem(draftKey.value)
  if (!raw) return
  try {
    const obj = JSON.parse(raw) as Record<string, Val>
    Object.entries(obj).forEach(([k, v]) => {
      const id = Number(k)
      values[id] = {
        selected_value: v?.selected_value ?? null,
        numeric_value: (v?.numeric_value ?? null) as number | null,
        notes: v?.notes ?? null,
      }
      const dim = props.dimensions.find(d => d.id === id)
      if (dim?.dimension_type === 'categorical') {
        choiceIndex[id] = values[id].selected_value != null
          ? dim.values.findIndex(o => o.value === values[id].selected_value)
          : null
      }
    })
  } catch {}
}
const saveLocal = () => {
  localStorage.setItem(draftKey.value, JSON.stringify(values))
}
onMounted(loadLocal)
watch(values, saveLocal, { deep: true })

/* ---------- Countdown ---------- */
const expiresAt = props.task.expires_at ? new Date(props.task.expires_at) : null
const remaining = ref<number>(expiresAt ? Math.max(0, Math.floor((expiresAt.getTime() - Date.now()) / 1000)) : 0)
let t: any = null
const tick = () => { if (remaining.value > 0) remaining.value -= 1 }
onMounted(() => { if (expiresAt) t = setInterval(tick, 1000) })
onBeforeUnmount(() => { if (t) clearInterval(t) })

const minutes = computed(() => Math.floor(remaining.value / 60))
const seconds = computed(() => remaining.value % 60)
const timePct = computed(() => {
  const total = (props.project.task_time_minutes ?? 0) * 60
  if (!total) return 0
  return Math.min(100, Math.max(0, (remaining.value / total) * 100))
})

/* ---------- Helpers ---------- */
const getScaleArray = (min: number | null, max: number | null): number[] => {
  const lo = typeof min === 'number' ? min : 1
  const hi = typeof max === 'number' ? max : 5
  const arr: number[] = []
  for (let i = lo; i <= hi; i++) arr.push(i)
  return arr
}

/* ---------- Categorical / Numeric change handlers ---------- */
const onCategoricalChanged = (dim: Dim, idx: number | null) => {
  choiceIndex[dim.id] = idx
  values[dim.id].selected_value = (idx === null || idx < 0) ? null : (dim.values[idx]?.value ?? null)
}
const onNumericChanged = (dimId: number, val: number | null) => {
  values[dimId].numeric_value = val
}

/* ---------- Required check ---------- */
const isRequiredMissing = computed(() => {
  const missing: number[] = []
  props.dimensions.forEach(d => {
    if (!d.is_required) return
    const v = values[d.id]
    if (!v) { missing.push(d.id); return }
    if (d.dimension_type === 'categorical') {
      if (!v.selected_value) missing.push(d.id)
    } else {
      if (v.numeric_value == null) missing.push(d.id)
    }
  })
  return missing
})
const isMissing = (dimId: number) => isRequiredMissing.value.includes(dimId)

/* ---------- Skip Dialog ---------- */
const skipOpen = ref(false)
const skipReason = ref<string>('')
const skipDescription = ref<string>('')

const openSkipDialog = () => {
  skipReason.value = ''
  skipDescription.value = ''
  skipOpen.value = true
}

/* ---------- Actions ---------- */
const assemblingPayload = () => ({
  values: props.dimensions.map(d => ({
    dimension_id: d.id,
    selected_value: values[d.id]?.selected_value ?? null,
    numeric_value: values[d.id]?.numeric_value ?? null,
    notes: values[d.id]?.notes ?? null,
  })),
  spent_seconds: (props.project.task_time_minutes ?? 0) * 60 - remaining.value,
})

const saving = ref(false)
const submitting = ref(false)
const skipping = ref(false)

const saveDraft = async () => {
  saving.value = true
  try {
    await router.post(
      route('staff.attempt.save', [props.project.id, props.task.id]),
      assemblingPayload(),
      { preserveScroll: true }
    )
  } finally {
    saving.value = false
  }
}

const submit = async () => {
  if (isRequiredMissing.value.length) {
    alert('Please fill all required dimensions before submitting.')
    return
  }
  submitting.value = true
  try {
    await router.post(
      route('staff.attempt.submit', [props.project.id, props.task.id]),
      assemblingPayload()
    )
    localStorage.removeItem(draftKey.value)
  } finally {
    submitting.value = false
  }
}

const skip = async () => {
  if (!skipReason.value) { alert('Please choose a reason to skip.'); return }
  skipping.value = true
  try {
    await router.post(
      route('staff.attempt.skip', [props.project.id, props.task.id]),
      { reason: skipReason.value, description: skipDescription.value }
    )
    localStorage.removeItem(draftKey.value)
  } finally {
    skipping.value = false
    skipOpen.value = false
  }
}
</script>

<template>
  <AppLayout :breadcrumbs="[{ title: 'Attempt Task', href: '#' }]">
    <Head :title="`Attempt - ${project.name}`" />

    <div class="container mx-auto p-6 space-y-6">
      <!-- Header Card with Timer (style parity with Review) -->
      <Card class="border-2 border-primary/10 bg-gradient-to-r from-primary/5 to-blue-50">
        <CardHeader>
          <div class="flex items-center justify-between">
            <div class="space-y-1">
              <CardTitle class="text-2xl flex items-center gap-3">
                <div class="p-2 bg-primary/10 rounded-lg">
                  <Edit3 class="h-6 w-6 text-primary" />
                </div>
                Annotate
              </CardTitle>
              <p class="text-muted-foreground font-medium">{{ project.name }}</p>
            </div>

            <!-- Timer Section -->
            <div class="flex items-center gap-4">
              <div class="text-right">
                <div class="text-2xl font-mono font-bold">
                  {{ minutes.toString().padStart(2,'0') }}:{{ seconds.toString().padStart(2,'0') }}
                </div>
                <div class="text-sm text-muted-foreground">Time Remaining</div>
              </div>
              <div class="space-y-2">
                <Progress :value="timePct" class="h-3 w-32" />
                <Badge variant="secondary" class="gap-1 justify-center w-full">
                  <Timer class="h-3 w-3" />
                  <span class="text-xs">{{ Math.round(timePct) }}%</span>
                </Badge>
              </div>
            </div>
          </div>
        </CardHeader>
      </Card>

      <!-- Audio Player Card -->
      <Card>
        <CardHeader>
          <CardTitle class="text-lg flex items-center gap-3">
            <FileAudio class="h-5 w-5 text-blue-600" />
            Audio Content
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="flex items-center gap-4 p-4 bg-muted/30 rounded-lg">
            <div class="p-3 bg-blue-100 rounded-full">
              <Music class="h-6 w-6 text-blue-600" />
            </div>
            <div>
              <div class="font-semibold">{{ task.audio.filename || 'Audio File' }}</div>
              <div class="text-sm text-muted-foreground">Task ID: #{{ task.id }}</div>
            </div>
          </div>

          <div class="rounded-lg border-2 border-dashed border-muted-foreground/20 p-6">
            <audio v-if="task.audio.url" :src="task.audio.url" controls class="w-full">
              Your browser does not support the audio element.
            </audio>
            <Alert v-else class="border-amber-200 bg-amber-50">
              <AlertTriangle class="h-4 w-4 text-amber-600" />
              <AlertDescription class="text-amber-800">
                Audio file is currently unavailable. Please contact support if this issue persists.
              </AlertDescription>
            </Alert>
          </div>
        </CardContent>
      </Card>

      <!-- Dimensions Card -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle class="text-lg flex items-center gap-3">
              <CheckSquare class="h-5 w-5 text-green-600" />
              Fill Dimensions
            </CardTitle>
            <Badge v-if="isRequiredMissing.length" variant="secondary" class="gap-1">
              <AlertCircle class="h-3 w-3" />
              {{ isRequiredMissing.length }} Required Missing
            </Badge>
          </div>
        </CardHeader>

        <CardContent class="space-y-8">
          <div v-for="d in dimensions" :key="d.id" class="space-y-6">
            <Card
              class="border-l-4"
              :class="isMissing(d.id) ? 'border-l-red-400 bg-red-50/30' : 'border-l-muted'"
            >
              <CardHeader class="pb-4">
                <div class="flex items-start justify-between">
                  <div class="space-y-2 flex-1">
                    <div class="flex items-center gap-3">
                      <h3 class="text-lg font-semibold">{{ d.name }}</h3>
                      <Badge v-if="d.is_required" variant="destructive" class="text-xs">Required</Badge>
                    </div>
                    <p v-if="d.description" class="text-sm text-muted-foreground leading-relaxed">
                      {{ d.description }}
                    </p>
                  </div>
                </div>
              </CardHeader>

              <CardContent class="space-y-6">
                <!-- Categorical (INDEX-BASED RADIO) -->
                <div v-if="d.dimension_type === 'categorical'" class="space-y-4">
                  <Label class="text-sm font-medium text-muted-foreground">Select one option:</Label>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <label
                      v-for="(opt, idx) in d.values"
                      :key="`${d.id}-${idx}`"
                      class="group relative p-4 rounded-lg border-2 transition-all duration-200 cursor-pointer hover:shadow-md"
                      :class="{
                        'border-primary bg-primary/5 shadow-sm': choiceIndex[d.id] === idx,
                        'border-muted hover:border-muted-foreground/40': choiceIndex[d.id] !== idx
                      }"
                    >
                      <input
                        type="radio"
                        :name="`dimension-${d.id}`"
                        :value="idx"
                        v-model.number="choiceIndex[d.id]"
                        @change="onCategoricalChanged(d, choiceIndex[d.id])"
                        class="sr-only"
                      />
                      <div class="flex items-center justify-between">
                        <span class="font-medium" :class="choiceIndex[d.id] === idx ? 'text-primary' : ''">
                          {{ opt.label }}
                        </span>
                        <div
                          class="h-5 w-5 rounded-full border-2 flex items-center justify-center transition-colors"
                          :class="{
                            'border-primary bg-primary': choiceIndex[d.id] === idx,
                            'border-muted-foreground group-hover:border-primary/40': choiceIndex[d.id] !== idx
                          }"
                        >
                          <div v-if="choiceIndex[d.id] === idx" class="h-2 w-2 bg-white rounded-full" />
                        </div>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- Numeric Scale (RADIO GROUP) -->
                <div v-else class="space-y-4">
                  <Label class="text-sm font-medium text-muted-foreground">
                    Rate on scale ({{ d.scale_min }}–{{ d.scale_max }}):
                  </Label>

                  <div class="flex items-center justify-center gap-4 p-6 bg-muted/20 rounded-xl">
                    <div class="text-sm font-medium text-muted-foreground">{{ d.scale_min }}</div>
                    <div class="flex items-center gap-3">
                      <template v-for="num in getScaleArray(d.scale_min, d.scale_max)" :key="`scale-${d.id}-${num}`">
                        <label
                          class="group relative p-3 rounded-full transition-all duration-200 hover:scale-110 cursor-pointer"
                          :class="{
                            'bg-primary text-white shadow-lg scale-105': values[d.id].numeric_value === num,
                            'bg-white border-2 border-muted hover:border-primary/40 hover:shadow-md': values[d.id].numeric_value !== num
                          }"
                        >
                          <input
                            type="radio"
                            :name="`scale-${d.id}`"
                            :value="num"
                            v-model.number="values[d.id].numeric_value"
                            @change="onNumericChanged(d.id, values[d.id].numeric_value)"
                            class="sr-only"
                          />
                          <Star class="h-6 w-6" :class="{ 'fill-current': values[d.id].numeric_value === num }" />
                          <span class="absolute -bottom-8 left-1/2 transform -translate-x-1/2 text-sm font-semibold">
                            {{ num }}
                          </span>
                        </label>
                      </template>
                    </div>
                    <div class="text-sm font-medium text-muted-foreground">{{ d.scale_max }}</div>
                  </div>

                  <div class="text-center">
                    <div class="text-sm text-muted-foreground">
                      Selected: <strong class="text-foreground">{{ values[d.id].numeric_value ?? 'None' }}</strong>
                    </div>
                  </div>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                  <Label :for="`notes-${d.id}`" class="text-sm font-medium">Notes (optional)</Label>
                  <Textarea
                    :id="`notes-${d.id}`"
                    v-model="values[d.id].notes"
                    rows="3"
                    class="resize-none"
                    placeholder="Add any additional notes or observations…"
                  />
                </div>
              </CardContent>
            </Card>
          </div>
        </CardContent>
      </Card>

      <Separator class="my-8" />

      <!-- Actions -->
      <Card>
        <CardContent class="pt-6">
          <div class="flex items-center justify-between gap-4">
            <Dialog v-model:open="skipOpen">
              <DialogTrigger as-child>
                <Button variant="outline" class="gap-2" @click="openSkipDialog">
                  <SkipForward class="h-4 w-4" />
                  Skip Task
                </Button>
              </DialogTrigger>

              <DialogContent class="sm:max-w-md">
                <DialogHeader>
                  <DialogTitle>Skip this task</DialogTitle>
                  <DialogDescription>
                    Skipping returns this task to the queue and prevents it from being re-assigned to you.
                  </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                  <div>
                    <Label for="skip-reason">Reason</Label>
                    <Select v-model="skipReason">
                      <SelectTrigger class="w-full">
                        <SelectValue placeholder="Select a reason..." />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="r in SKIP_REASONS" :key="r.value" :value="r.value">
                          {{ r.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div>
                    <Label for="skip-description">Additional details (optional)</Label>
                    <Textarea
                      id="skip-description"
                      v-model="skipDescription"
                      rows="3"
                      placeholder="Add a brief note to help the team understand the skip."
                    />
                  </div>
                </div>

                <DialogFooter class="flex gap-2">
                  <Button variant="outline" @click="skipOpen = false">Cancel</Button>
                  <Button :disabled="skipping" class="gap-2" @click="skip">
                    <SkipForward class="h-4 w-4" />
                    Skip Task
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>

            <div class="flex items-center gap-3">
              <!-- <Button variant="outline" size="lg" class="gap-2" :disabled="saving" @click="saveDraft">
                <Save class="h-4 w-4" />
                Save Draft
              </Button>
              <Button size="lg" class="gap-2 px-8" :disabled="submitting || isRequiredMissing.length > 0" @click="submit">
                <CheckCircle2 class="h-4 w-4" />
                Submit
              </Button> -->
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

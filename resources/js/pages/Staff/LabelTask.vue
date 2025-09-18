<template>
  <AppLayout :breadcrumbs="[{ title: 'Label Task', href: '#' }]">
    <Head :title="`Labeling - ${project.name}`" />

    <div class="container mx-auto p-6 space-y-6">
      <!-- Header + Timer -->
      <Card class="border-2 border-primary/10 bg-gradient-to-r from-primary/5 to-blue-50">
        <CardHeader>
          <div class="flex items-center justify-between">
            <div class="space-y-1">
              <CardTitle class="text-2xl flex items-center gap-3">
                <div class="p-2 bg-primary/10 rounded-lg">
                  <Scissors class="h-6 w-6 text-primary" />
                </div>
                Audio Segmentation
              </CardTitle>
              <p class="text-muted-foreground font-medium">{{ project.name }}</p>
            </div>

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

      <!-- Main Audio Interface -->
      <Card class="overflow-hidden">
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle class="text-lg flex items-center gap-3">
              <FileAudio class="h-5 w-5 text-blue-600" />
              Audio Waveform
            </CardTitle>
            <div class="flex items-center gap-2">
              <Badge variant="outline">{{ segments.length }} segments</Badge>
              
              <!-- Clear All Dialog -->
              <Dialog v-model:open="showClearDialog">
                <DialogTrigger as-child>
                  <Button size="sm" variant="outline" :disabled="!segments.length">
                    <Trash2 class="h-4 w-4 mr-1" />
                    Clear All
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                      <Trash2 class="h-5 w-5 text-red-500" />
                      Clear All Segments
                    </DialogTitle>
                    <DialogDescription>
                      Are you sure you want to remove all <strong>{{ segments.length }}</strong> segments? This action cannot be undone and you will lose all your segmentation work.
                    </DialogDescription>
                  </DialogHeader>
                  <DialogFooter>
                    <Button variant="outline" @click="showClearDialog = false">
                      Cancel
                    </Button>
                    <Button variant="destructive" @click="handleClearAll">
                      <Trash2 class="h-4 w-4 mr-1" />
                      Clear All Segments
                    </Button>
                  </DialogFooter>
                </DialogContent>
              </Dialog>
            </div>
          </div>
        </CardHeader>
        <CardContent class="p-0">
          <!-- Labels Palette (Above Waveform) -->
          <div class="p-4 bg-gradient-to-r from-gray-50 to-slate-50 border-b">
            <div class="flex items-center gap-2 mb-3">
              <h4 class="font-semibold text-sm text-gray-700">Click a label to create segment:</h4>
              <Badge variant="secondary" class="text-xs">
                Selected: {{ selectedLabel?.name || 'None' }}
              </Badge>
            </div>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="label in allAvailableLabels"
                :key="label.key"
                @click="selectLabel(label)"
                class="px-4 py-2 rounded-full font-medium text-sm transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-1"
                :class="selectedLabel?.key === label.key 
                  ? 'ring-2 ring-blue-500 ring-offset-2 shadow-lg transform scale-105' 
                  : 'hover:shadow-md'"
                :style="{
                  backgroundColor: label.color + '20',
                  borderColor: label.color,
                  color: label.color,
                  border: label.color ? `2px solid ${label.color}` : undefined
                }"
                :title="label.description ?? undefined"
              >
                <div class="flex items-center gap-2">
                  <div 
                    class="w-3 h-3 rounded-full"
                    :style="{ backgroundColor: label.color }"
                  ></div>
                  {{ label.name }}
                  <span v-if="label.isCustom" class="text-xs opacity-70">(Custom)</span>
                </div>
              </button>
              
              <!-- Add Custom Label Button -->
              <Button v-if="project.allow_custom_labels" variant="outline" class="gap-2"
                @click="showCreateLabel = true" :disabled="creatingLabel">
                <Plus class="w-4 h-4" />
                {{ creatingLabel ? 'Creating...' : 'Add Custom Label' }}
              </Button>
            </div>
          </div>

          <!-- Audio Controls -->
          <div class="p-4 bg-white border-b">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-3">
                <Button 
                  variant="outline" 
                  size="sm" 
                  @click="playPause"
                  class="flex items-center gap-2"
                >
                  <component :is="isPlaying ? Pause : Play" class="h-4 w-4" />
                  {{ isPlaying ? 'Pause' : 'Play' }}
                </Button>
                <div class="text-sm text-gray-600">
                  {{ formatTime(currentTime) }} / {{ formatTime(duration) }}
                </div>
              </div>
              <div class="flex items-center gap-2">
                <Button size="sm" variant="ghost" @click="zoomOut">
                  <ZoomOut class="h-4 w-4" />
                </Button>
                <Button size="sm" variant="ghost" @click="zoomIn">
                  <ZoomIn class="h-4 w-4" />
                </Button>
                <Badge variant="outline" class="text-xs">
                  {{ Math.round(pixelsPerSecond) }}px/s
                </Badge>
              </div>
            </div>

            <!-- Waveform Container with Overlaid Segments -->
            <div class="relative">
              <div 
                ref="waveformContainer" 
                class="w-full bg-gray-100 rounded-lg overflow-hidden relative" 
                style="height: 120px;"
                @mousedown="handleMouseDown"
                @mousemove="handleMouseMove" 
                @mouseup="handleMouseUp"
              >
                <div ref="waveformEl" class="w-full h-full"></div>
                
                <!-- Segments Timeline Overlay -->
                <div class="absolute inset-0 pointer-events-none z-10">
                  <template v-for="(segment, index) in segments" :key="`segment-${index}`">
                    <div
                      v-if="getSegmentPosition(segment).visible"
                      class="absolute rounded hover:shadow-lg transition-all duration-200 group pointer-events-auto cursor-pointer"
                      :style="{
                        left: `${getSegmentPosition(segment).left}px`,
                        width: `${getSegmentPosition(segment).width}px`,
                        backgroundColor: segment.label.color + '30',
                        border: `2px solid ${segment.label.color}`,
                        top: '10px',
                        height: '100px',
                        borderRadius: '6px'
                      }"
                      @click.stop="playSegment(segment)"
                    >
                      <!-- Resize handles -->
                      <div 
                        class="absolute left-0 top-0 w-3 h-full bg-gray-800 opacity-0 group-hover:opacity-80 cursor-ew-resize rounded-l flex items-center justify-center z-20"
                        @mousedown.stop="startResize(segment, 'start', $event)"
                      >
                        <div class="w-0.5 h-8 bg-white rounded"></div>
                      </div>
                      <div 
                        class="absolute right-0 top-0 w-3 h-full bg-gray-800 opacity-0 group-hover:opacity-80 cursor-ew-resize rounded-r flex items-center justify-center z-20"
                        @mousedown.stop="startResize(segment, 'end', $event)"
                      >
                        <div class="w-0.5 h-8 bg-white rounded"></div>
                      </div>
                      
                      <!-- Label at top -->
                      <div class="absolute top-1 left-1 right-1 px-2 py-1 text-xs font-bold text-white bg-black bg-opacity-60 rounded truncate pointer-events-none">
                        {{ segment.label.name }}
                        <span v-if="segment.custom_label" class="opacity-75">(C)</span>
                      </div>
                      
                      <!-- Duration at bottom -->
                      <div class="absolute bottom-1 left-1 px-1 py-0.5 text-xs font-medium text-white bg-black bg-opacity-50 rounded pointer-events-none">
                        {{ (segment.end_time - segment.start_time).toFixed(1) }}s
                      </div>
                      
                      <!-- Delete button -->
                      <button
                        class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600 flex items-center justify-center shadow-lg z-20"
                        @click.stop="removeSegment(index)"
                      >
                        <X class="h-4 w-4" />
                      </button>
                      
                      <!-- Play button (center) -->
                      <button
                        class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-8 h-8 bg-white bg-opacity-80 text-gray-800 rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-opacity-100 flex items-center justify-center shadow-md z-20"
                        @click.stop="playSegment(segment)"
                      >
                        <Play class="h-4 w-4 ml-0.5" />
                      </button>
                    </div>
                  </template>
                  
                  <!-- Click instruction when no segments -->
                  <div v-if="!segments.length" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="bg-white bg-opacity-90 px-4 py-2 rounded-lg shadow-sm text-gray-500 text-sm text-center">
                      <div class="font-medium mb-1">No segments created</div>
                      <div class="text-xs">Select a label above, then click and drag on the waveform</div>
                    </div>
                  </div>
                </div>
                
                <!-- Selection overlay -->
                <div
                  v-if="isSelecting"
                  class="absolute bg-blue-500 bg-opacity-30 border-2 border-blue-500 pointer-events-none rounded z-15"
                  :style="{
                    left: `${Math.min(selectionStart, selectionEnd)}px`,
                    width: `${Math.abs(selectionEnd - selectionStart)}px`,
                    top: '10px',
                    height: '100px'
                  }"
                ></div>
              </div>
              
              <p class="text-xs text-gray-500 mt-2">
                Instructions: Select a label above, then click and drag on the waveform to create labeled segments. Hover over segments to resize or delete them.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Segments List -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <ListChecks class="h-5 w-5" />
            Segments ({{ segments.length }})
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="!segments.length" class="text-center py-8 text-gray-500">
            <AudioWaveform class="h-12 w-12 mx-auto mb-3 opacity-50" />
            <p>No segments created yet</p>
            <p class="text-sm">Select a label and click on the waveform to create segments</p>
          </div>
          
          <div v-else class="space-y-3">
            <div
              v-for="(segment, index) in segments"
              :key="`list-segment-${index}`"
              class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
            >
              <div class="flex items-center gap-3 flex-1">
                <div 
                  class="w-4 h-4 rounded-full flex-shrink-0"
                  :style="{ backgroundColor: segment.label.color }"
                ></div>
                <div class="flex-1">
                  <div class="font-medium">
                    {{ segment.label.name }}
                    <span v-if="segment.custom_label" class="text-xs text-gray-500">(Custom)</span>
                  </div>
                  <div class="text-sm text-gray-500">
                    {{ formatTime(segment.start_time) }} - {{ formatTime(segment.end_time) }}
                    ({{ (segment.end_time - segment.start_time).toFixed(2) }}s)
                  </div>
                </div>
                <input
                  v-model="segment.notes"
                  placeholder="Add notes..."
                  class="flex-1 px-3 py-1 text-sm border rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
              <div class="flex items-center gap-2">
                <Button size="sm" variant="ghost" @click="playSegment(segment)">
                  <Play class="h-4 w-4" />
                </Button>
                <Button size="sm" variant="ghost" @click="removeSegment(index)">
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <Card>
        <CardContent class="pt-6">
          <div class="flex items-center justify-between">
            <Dialog v-model:open="skipOpen">
              <DialogTrigger as-child>
                <Button variant="outline" class="gap-2">
                  <SkipForward class="h-4 w-4" />
                  Skip Task
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Skip this task</DialogTitle>
                  <DialogDescription>
                    Skipping returns this task to the queue for someone else to complete.
                  </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                  <div>
                    <Label>Reason</Label>
                    <Select v-model="skipReason">
                      <SelectTrigger>
                        <SelectValue placeholder="Select a reason..." />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="technical_issue">Technical issue</SelectItem>
                        <SelectItem value="unclear_audio">Unclear audio</SelectItem>
                        <SelectItem value="unclear_annotation">Unclear instruction</SelectItem>
                        <SelectItem value="personal_reason">Personal reason</SelectItem>
                        <SelectItem value="other">Other</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <Label>Details (optional)</Label>
                    <Textarea v-model="skipDescription" rows="3" placeholder="Additional details..." />
                  </div>
                </div>
                <DialogFooter>
                  <Button variant="outline" @click="skipOpen = false">Cancel</Button>
                  <Button @click="skip" :disabled="skipping">Skip Task</Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>

            <div class="flex items-center gap-3">
              <!-- <Button 
                variant="outline" 
                size="lg" 
                @click="saveDraft" 
                :disabled="saving"
                class="gap-2"
              >
                <Save class="h-4 w-4" />
                {{ saving ? 'Saving...' : 'Save Draft' }}
              </Button> -->
              <Button 
                size="lg" 
                @click="submit" 
                :disabled="submitting || !segments.length"
                class="gap-2 px-8"
              >
                <CheckCircle2 class="h-4 w-4" />
                {{ submitting ? 'Submitting...' : 'Submit' }}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Custom Label Modal -->
    <CreateLabelModal :open="showCreateLabel" @close="showCreateLabel = false" @create="handleCreateLabel" />
  </AppLayout>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import CreateLabelModal from '@/components/Tasks/staff/CreateLabelModal.vue'
import {
  Scissors, Timer, FileAudio, Save, CheckCircle2, SkipForward, Play, Pause, 
  Trash2, X, ListChecks, AudioWaveform, ZoomIn, ZoomOut, Plus
} from 'lucide-vue-next'

// Types
type ProjectLabel = {
  id: number
  name: string
  color: string
  description: string | null
}

type IncomingCustomLabel = {
  id: number
  name: string
  color: string
  description?: string | null
  uuid?: string | null
  isCustom: true
}

type BaseLabel = {
  key: string
  id?: number | null
  uuid?: string | null
  name: string
  color: string
  description: string | null
  isCustom: boolean
}

type Segment = {
  id?: number
  start_time: number
  end_time: number
  notes?: string
  label: { name: string; color: string }
  project_label_id?: number | null
  custom_label?: { uuid?: string | null; name: string; color: string } | null
}

const props = defineProps<{
  mode: 'attempt'
  project: {
    id: number
    name: string
    task_time_minutes: number | null
    allow_custom_labels: boolean
  }
  task: {
    id: number
    status: string
    expires_at: string | null
    audio: { 
      id: number | null
      filename: string | null
      url: string | null
      duration: number | null 
    }
  }
  labels: ProjectLabel[]
  customLabels: IncomingCustomLabel[]
  draft: {
    annotation_id: number | null
    segments: Array<{
      id: number
      start_time: number
      end_time: number
      notes: string | null
      project_label: { id: number; name: string; color: string } | null
      custom_label: { id: number; uuid?: string | null; name: string; color: string } | null
    }>
  }
  csrf_token: string 
}>()

// WaveSurfer
import WaveSurfer from 'wavesurfer.js'
const waveformEl = ref<HTMLDivElement | null>(null)
const waveformContainer = ref<HTMLDivElement | null>(null)
let ws: WaveSurfer | null = null

// Timer
const expiresAt = props.task.expires_at ? new Date(props.task.expires_at) : null
const remaining = ref<number>(expiresAt ? Math.max(0, Math.floor((expiresAt.getTime() - Date.now()) / 1000)) : 0)
let timerInterval: any = null

const minutes = computed(() => Math.floor(remaining.value / 60))
const seconds = computed(() => remaining.value % 60)
const timePct = computed(() => {
  const total = (props.project.task_time_minutes ?? 0) * 60
  if (!total) return 0
  return Math.min(100, Math.max(0, (remaining.value / total) * 100))
})

// Audio state with tracking
const isPlaying = ref(false)
const currentTime = ref(0)
const duration = ref(0)
const pixelsPerSecond = ref(50)
const scrollLeft = ref(0)
const containerWidth = ref(800)

// Watch for container resize
const updateContainerWidth = () => {
  if (waveformContainer.value) {
    containerWidth.value = waveformContainer.value.clientWidth
  }
}

// Label selection and custom labels
const selectedLabel = ref<BaseLabel | null>(null)
const showCreateLabel = ref(false)
const creatingLabel = ref(false)

const mapCustomLabel = (l: IncomingCustomLabel, fallbackSuffix = ''): BaseLabel => ({
  key: l.uuid ? `custom:${l.uuid}` : `custom:task:${props.task.id}:label:${l.id}${fallbackSuffix}`,
  id: l.id,
  uuid: l.uuid ?? null,
  name: l.name,
  color: l.color,
  description: l.description ?? null,
  isCustom: true,
})

const mapProjectLabel = (l: ProjectLabel): BaseLabel => ({
  key: `project:${l.id}`,
  id: l.id,
  uuid: null,
  name: l.name,
  color: l.color,
  description: l.description ?? null,
  isCustom: false,
})

// Then initialize customLabels
const customLabels = ref<BaseLabel[]>(props.customLabels.map(mapCustomLabel))

const projectLabels = computed<BaseLabel[]>(() => props.labels.map(mapProjectLabel))

const allAvailableLabels = computed<BaseLabel[]>(() => {
  const merged = [...projectLabels.value, ...customLabels.value]
  const byKey = new Map<string, BaseLabel>()
  merged.forEach(l => { if (!byKey.has(l.key)) byKey.set(l.key, l) })
  return Array.from(byKey.values())
})

const selectLabel = (label: BaseLabel) => {
  selectedLabel.value = label
}

const handleCreateLabel = async (labelData: { name: string; color: string }) => {
  if (!props.project.allow_custom_labels) {
    alert('Custom labels are not allowed in this project.')
    return
  }

  creatingLabel.value = true
  try {
    const res = await fetch(
      route('staff.custom-labels.create.json', [props.project.id, props.task.id]),
      {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': props.csrf_token,
        },
        body: JSON.stringify({ name: labelData.name, color: labelData.color }),
      }
    )
    if (!res.ok) {
      const text = await res.text()
      throw new Error(`Create label failed (${res.status}): ${text}`)
    }
    const result = await res.json()
    if (!result?.success || !result?.label) throw new Error(result?.message || 'Failed to create custom label.')

    const newLabel = mapCustomLabel(result.label)
    customLabels.value.push(newLabel)
    selectedLabel.value = newLabel
    showCreateLabel.value = false
  } catch (e) {
    console.error(e)
    alert('Failed to create custom label. Please try again.')
  } finally {
    creatingLabel.value = false
  }
}

// Segments
const segments = reactive<Segment[]>([])

// Selection state
const isSelecting = ref(false)
const selectionStart = ref(0)
const selectionEnd = ref(0)
let isDragging = false

// Resize state
let isResizing = false
let resizeSegment: Segment | null = null
let resizeMode: 'start' | 'end' = 'start'

// Actions state
const saving = ref(false)
const submitting = ref(false)
const skipOpen = ref(false)
const skipReason = ref('')
const skipDescription = ref('')
const skipping = ref(false)
const showClearDialog = ref(false)

// Initialize segments from draft
props.draft.segments?.forEach(s => {
  if (s.project_label) {
    segments.push({
      id: s.id,
      start_time: s.start_time,
      end_time: s.end_time,
      notes: s.notes || '',
      label: { name: s.project_label.name, color: s.project_label.color },
      project_label_id: s.project_label.id,
      custom_label: null,
    })
  } else if (s.custom_label) {
    segments.push({
      id: s.id,
      start_time: s.start_time,
      end_time: s.end_time,
      notes: s.notes || '',
      label: { name: s.custom_label.name, color: s.custom_label.color },
      project_label_id: null,
      custom_label: { uuid: s.custom_label.uuid ?? null, name: s.custom_label.name, color: s.custom_label.color },
    })
  }
})

// Default selected label
if (!selectedLabel.value && allAvailableLabels.value.length > 0) {
  selectedLabel.value = allAvailableLabels.value[0]
}

// Helper functions
const formatTime = (time: number): string => {
  const mins = Math.floor(time / 60)
  const secs = Math.floor(time % 60)
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

// Calculate segment positions based on current waveform state
const getSegmentPosition = (segment: Segment) => {
  if (!duration.value || !containerWidth.value) {
    return { left: 0, width: 0, visible: false }
  }

  // Get visible time range
  const visibleStartTime = scrollLeft.value / pixelsPerSecond.value
  const visibleEndTime = visibleStartTime + (containerWidth.value / pixelsPerSecond.value)
  
  // Check if segment overlaps with visible area
  const segmentVisible = segment.end_time > visibleStartTime && segment.start_time < visibleEndTime
  
  if (!segmentVisible) {
    return { left: 0, width: 0, visible: false }
  }
  
  // Calculate pixel positions relative to visible viewport
  const segmentStartInViewport = Math.max(0, segment.start_time - visibleStartTime)
  const segmentEndInViewport = Math.min(segment.end_time - visibleStartTime, containerWidth.value / pixelsPerSecond.value)
  
  const leftPixels = segmentStartInViewport * pixelsPerSecond.value
  const widthPixels = Math.max(2, (segmentEndInViewport - segmentStartInViewport) * pixelsPerSecond.value)
  
  return {
    left: leftPixels,
    width: widthPixels,
    visible: true
  }
}

const getTimeFromX = (clientX: number): number => {
  if (!waveformContainer.value) return 0
  const rect = waveformContainer.value.getBoundingClientRect()
  const relativeX = Math.max(0, Math.min(clientX - rect.left, rect.width))
  const visibleStartTime = scrollLeft.value / pixelsPerSecond.value
  return visibleStartTime + (relativeX / pixelsPerSecond.value)
}

const playPause = () => {
  if (!ws) return
  if (isPlaying.value) {
    ws.pause()
  } else {
    ws.play()
  }
}

const playSegment = (segment: Segment) => {
  if (!ws) return
  ws.setTime(segment.start_time)
  ws.play()
  
  // Stop at segment end
  setTimeout(() => {
    if (ws && currentTime.value >= segment.end_time) {
      ws.pause()
    }
  }, (segment.end_time - segment.start_time) * 1000)
}

const zoomIn = () => {
  if (ws) {
    const newZoom = pixelsPerSecond.value * 1.5
    ws.zoom(newZoom)
    pixelsPerSecond.value = newZoom
  }
}

const zoomOut = () => {
  if (ws) {
    const newZoom = Math.max(25, pixelsPerSecond.value * 0.75)
    ws.zoom(newZoom)
    pixelsPerSecond.value = newZoom
  }
}

const createSegment = (startTime: number, endTime: number) => {
  if (!selectedLabel.value) return
  
  const newSegment: Segment = {
    start_time: Math.min(startTime, endTime),
    end_time: Math.max(startTime, endTime),
    notes: '',
    label: { name: selectedLabel.value.name, color: selectedLabel.value.color },
    project_label_id: selectedLabel.value.isCustom ? null : (selectedLabel.value.id ?? null),
    custom_label: selectedLabel.value.isCustom 
      ? { uuid: selectedLabel.value.uuid ?? null, name: selectedLabel.value.name, color: selectedLabel.value.color }
      : null,
  }
  
  segments.push(newSegment)
  segments.sort((a, b) => a.start_time - b.start_time)
}

const removeSegment = (index: number) => {
  segments.splice(index, 1)
}

const handleClearAll = () => {
  segments.splice(0, segments.length)
  showClearDialog.value = false
}

// Mouse event handlers for waveform selection
const handleMouseDown = (e: MouseEvent) => {
  if (!selectedLabel.value || isResizing) return
  
  isDragging = true
  const rect = waveformContainer.value?.getBoundingClientRect()
  if (rect) {
    selectionStart.value = e.clientX - rect.left
    selectionEnd.value = e.clientX - rect.left
  }
  isSelecting.value = true
}

const handleMouseMove = (e: MouseEvent) => {
  if (!isDragging || !isSelecting.value) return
  
  const rect = waveformContainer.value?.getBoundingClientRect()
  if (rect) {
    selectionEnd.value = e.clientX - rect.left
  }
}

const handleMouseUp = (e: MouseEvent) => {
  if (!isDragging || !isSelecting.value) return
  
  isDragging = false
  isSelecting.value = false
  
  const rect = waveformContainer.value?.getBoundingClientRect()
  if (!rect) return
  
  const startX = Math.min(selectionStart.value, selectionEnd.value)
  const endX = Math.max(selectionStart.value, selectionEnd.value)
  
  const startTime = getTimeFromX(rect.left + startX)
  const endTime = getTimeFromX(rect.left + endX)
  
  if (Math.abs(endTime - startTime) > 0.1) { // Minimum 0.1s segment
    createSegment(startTime, endTime)
  }
}

// Resize handlers
const startResize = (segment: Segment, mode: 'start' | 'end', e: MouseEvent) => {
  e.preventDefault()
  isResizing = true
  resizeSegment = segment
  resizeMode = mode
  
  const handleMouseMove = (e: MouseEvent) => {
    if (!isResizing || !resizeSegment) return
    
    const time = getTimeFromX(e.clientX)
    
    if (resizeMode === 'start') {
      resizeSegment.start_time = Math.max(0, Math.min(time, resizeSegment.end_time - 0.1))
    } else {
      resizeSegment.end_time = Math.max(resizeSegment.start_time + 0.1, Math.min(time, duration.value))
    }
  }
  
  const handleMouseUp = () => {
    isResizing = false
    resizeSegment = null
    document.removeEventListener('mousemove', handleMouseMove)
    document.removeEventListener('mouseup', handleMouseUp)
  }
  
  document.addEventListener('mousemove', handleMouseMove)
  document.addEventListener('mouseup', handleMouseUp)
}

// Initialize WaveSurfer with event tracking
const initWaveSurfer = async () => {
  if (!waveformEl.value || !props.task.audio.url) return

  if (ws) {
    ws.destroy()
  }

  ws = WaveSurfer.create({
    container: waveformEl.value,
    url: props.task.audio.url,
    height: 120,
    waveColor: '#e2e8f0',
    progressColor: '#3b82f6',
    cursorColor: '#1f2937',
    normalize: true,
    interact: true,
    minPxPerSec: 50,
  })

  ws.on('ready', () => {
    duration.value = ws?.getDuration() || 0
    pixelsPerSecond.value = ws?.options.minPxPerSec || 50
    updateContainerWidth()
    
  })

  ws.on('play', () => {
    isPlaying.value = true
  })

  ws.on('pause', () => {
    isPlaying.value = false
  })

  ws.on('timeupdate', (time) => {
    currentTime.value = time
  })

  // Track zoom changes
  ws.on('zoom', (minPxPerSec) => {
    pixelsPerSecond.value = minPxPerSec
  })

  // Track scroll changes
  ws.on('scroll', (startTime) => {
    scrollLeft.value = startTime * pixelsPerSecond.value
  })

  // Listen for container scroll events
  const waveformWrapper = waveformEl.value?.parentElement
  if (waveformWrapper) {
    waveformWrapper.addEventListener('scroll', (e) => {
      const target = e.target as HTMLElement
      scrollLeft.value = target.scrollLeft || 0
    })
  }
}

// Actions
const saveDraft = async () => {
  saving.value = true
  try {
    await router.post(
      route('staff.attempt.save', [props.project.id, props.task.id]),
      {
        segments: segments.map(s => ({
          start_time: Number(s.start_time.toFixed(3)),
          end_time: Number(s.end_time.toFixed(3)),
          notes: s.notes || null,
          project_label_id: s.project_label_id,
          custom_label: s.custom_label
        })),
        spent_seconds: (props.project.task_time_minutes ?? 0) * 60 - remaining.value
      },
      { preserveScroll: true }
    )
  } finally {
    saving.value = false
  }
}

const submit = async () => {
  if (!segments.length) {
    alert('Please create at least one segment before submitting.')
    return
  }
  
  submitting.value = true
  try {
    await router.post(
      route('staff.attempt.submit', [props.project.id, props.task.id]),
      {
        segments: segments.map(s => ({
          start_time: Number(s.start_time.toFixed(3)),
          end_time: Number(s.end_time.toFixed(3)),
          notes: s.notes || null,
          project_label_id: s.project_label_id,
          custom_label: s.custom_label
        })),
        spent_seconds: (props.project.task_time_minutes ?? 0) * 60 - remaining.value
      }
    )
  } finally {
    submitting.value = false
  }
}

const skip = async () => {
  if (!skipReason.value) {
    alert('Please select a reason to skip.')
    return
  }
  
  skipping.value = true
  try {
    await router.post(
      route('staff.attempt.skip', [props.project.id, props.task.id]),
      {
        reason: skipReason.value,
        description: skipDescription.value
      }
    )
  } finally {
    skipping.value = false
    skipOpen.value = false
  }
}

// Lifecycle
onMounted(async () => {
  if (expiresAt) {
    timerInterval = setInterval(() => {
      if (remaining.value > 0) {
        remaining.value -= 1
      }
    }, 1000)
  }
  
  await nextTick()
  await initWaveSurfer()
  
  // Listen for window resize to update container width
  window.addEventListener('resize', updateContainerWidth)
})

onBeforeUnmount(() => {
  if (timerInterval) {
    clearInterval(timerInterval)
  }
  if (ws) {
    ws.destroy()
  }
  window.removeEventListener('resize', updateContainerWidth)
})

// Watch for segments changes and refresh positions
watch([pixelsPerSecond, scrollLeft, containerWidth], () => {
  // Force reactivity update for segment positions
}, { flush: 'post' })
</script>

<style scoped>
.cursor-crosshair {
  cursor: crosshair;
}

.cursor-ew-resize {
  cursor: ew-resize;
}
</style>
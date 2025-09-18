<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label as UILabel } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { Layers, Download, Filter } from 'lucide-vue-next'

type Batch = { id:number; name:string; status:string }
type SegmentationLabel = { id:number; name:string; color:string; description?:string|null }

const props = defineProps<{
  projectId: number
  batches: Batch[]
  open?: boolean
  projectType: 'annotation' | 'segmentation'
  segmentationLabels?: SegmentationLabel[] // required for segmentation, ignored for annotation
}>()

const emit = defineEmits(['update:open'])

/* dialog open */
const open = ref(!!props.open)
watch(() => props.open, (v: boolean) => (open.value = !!v))
watch(open, (v: boolean) => emit('update:open', v))

/* common export options */
const type   = ref<'json'|'csv'|'excel'>('excel')
const status = ref<'all'|'accepted'|'rejected'|'under_review'|'pending'|'assigned'|'in_progress'|'approved'>('all')

/* scope/batches */
const scope = ref<'all'|'selected'>('all')
const selectedBatches = ref<number[]>([])
const canExport = computed(() => scope.value === 'all' || selectedBatches.value.length > 0)

/* segmentation-only */
const isSeg = computed(() => props.projectType === 'segmentation')
const allLabels = computed<SegmentationLabel[]>(() => props.segmentationLabels ?? [])

/** Mutual-exclusion sets */
const includeIds = ref<number[]>([])
const excludeIds = ref<number[]>([])

/** Display pools (hide labels picked on the opposite side) */
const includeOptions = computed(() => allLabels.value.filter(l => !excludeIds.value.includes(l.id)))
const excludeOptions = computed(() => allLabels.value.filter(l => !includeIds.value.includes(l.id)))

/** Toggle helpers */
function toggleInclude(id: number) {
  if (includeIds.value.includes(id)) {
    includeIds.value = includeIds.value.filter(x => x !== id)
  } else {
    // if present in exclude, remove first (shouldn't happen because it's hidden, but safe)
    excludeIds.value = excludeIds.value.filter(x => x !== id)
    includeIds.value.push(id)
  }
}
function toggleExclude(id: number) {
  if (excludeIds.value.includes(id)) {
    excludeIds.value = excludeIds.value.filter(x => x !== id)
  } else {
    includeIds.value = includeIds.value.filter(x => x !== id)
    excludeIds.value.push(id)
  }
}

/** Clear buttons */
const clearIncluded = () => (includeIds.value = [])
const clearExcluded = () => (excludeIds.value = [])

/** Build & fire request */
function runExport() {
  const url = route('admin.projects.tasks.export', props.projectId)
  const params = new URLSearchParams()
  params.set('type', type.value)
  params.set('status', status.value)

  if (scope.value === 'selected' && selectedBatches.value.length) {
    selectedBatches.value.forEach(b => params.append('batches[]', String(b)))
  }

  if (isSeg.value) {
    includeIds.value.forEach(id => params.append('include_label_ids[]', String(id)))
    excludeIds.value.forEach(id => params.append('exclude_label_ids[]', String(id)))
  }

  window.location.href = `${url}?${params.toString()}`
  open.value = false
}

/** Chip component classes */
function chipClass(active: boolean) {
  return [
    'px-3 py-1 rounded-full border text-sm transition',
    active ? 'bg-primary text-primary-foreground border-primary' : 'bg-white hover:bg-muted border-muted-foreground/20'
  ].join(' ')
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="sm:max-w-3xl">
      <DialogHeader>
        <DialogTitle>Export Tasks</DialogTitle>
        <DialogDescription>
          <span v-if="!isSeg">
            Choose format, status, and batch scope. Exports include final dimension values (reviewer fixes applied) and audio links.
          </span>
          <span v-else>
            Choose format, status, batches, and (optional) label filters. Labels are split into two rows; a label can be in
            <strong>Included</strong> or <strong>Excluded</strong> (or neither), never both.
            Removing a label from one row makes it appear in the other again.
          </span>
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-6 py-2">
        <!-- ========== Format ========== -->
        <div class="space-y-2">
          <UILabel>Format</UILabel>
          <RadioGroup v-model="type" class="grid grid-cols-3 gap-3">
            <div class="border rounded-lg p-3 hover:bg-muted cursor-pointer">
              <RadioGroupItem id="t-excel" value="excel" class="mr-2" />
              <UILabel for="t-excel">Excel (.xlsx)</UILabel>
            </div>
            <div class="border rounded-lg p-3 hover:bg-muted cursor-pointer">
              <RadioGroupItem id="t-csv" value="csv" class="mr-2" />
              <UILabel for="t-csv">CSV</UILabel>
            </div>
            <div class="border rounded-lg p-3 hover:bg-muted cursor-pointer">
              <RadioGroupItem id="t-json" value="json" class="mr-2" />
              <UILabel for="t-json">JSON</UILabel>
            </div>
          </RadioGroup>
        </div>

        <!-- ========== Status ========== -->
        <div class="space-y-2">
          <UILabel>Status</UILabel>
          <Select v-model="status">
            <SelectTrigger>
              <SelectValue placeholder="All" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All</SelectItem>
              <SelectItem value="accepted">Accepted (Approved)</SelectItem>
              <SelectItem value="rejected">Rejected</SelectItem>
              <SelectItem value="under_review">Under Review</SelectItem>
              <SelectItem value="pending">Pending</SelectItem>
              <SelectItem value="assigned">Assigned</SelectItem>
              <SelectItem value="in_progress">In Progress</SelectItem>
              <SelectItem value="approved">Approved</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- ========== Scope ========== -->
        <div class="space-y-2">
          <UILabel>Scope</UILabel>
          <RadioGroup v-model="scope" class="grid grid-cols-2 gap-3">
            <div class="border rounded-lg p-3 hover:bg-muted cursor-pointer">
              <RadioGroupItem id="s-all" value="all" class="mr-2" />
              <UILabel for="s-all">All batches</UILabel>
            </div>
            <div class="border rounded-lg p-3 hover:bg-muted cursor-pointer">
              <RadioGroupItem id="s-selected" value="selected" class="mr-2" />
              <UILabel for="s-selected">Selected batches</UILabel>
            </div>
          </RadioGroup>
        </div>

        <!-- ========== Batches (multi) ========== -->
        <div v-if="scope==='selected'" class="space-y-2">
          <UILabel>Select batches</UILabel>
          <div class="flex flex-wrap items-center gap-2">
            <Select :modelValue="null" @update:modelValue="(v:any)=>{ if (typeof v === 'number' && !selectedBatches.includes(v)) selectedBatches.push(v) }">
              <SelectTrigger class="min-w-[220px]">
                <SelectValue placeholder="Add a batch..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="b in batches" :key="b.id" :value="b.id">
                  <span class="flex items-center gap-2">
                    <Layers class="h-3 w-3" /> {{ b.name }}
                    <Badge variant="secondary">{{ b.status }}</Badge>
                  </span>
                </SelectItem>
              </SelectContent>
            </Select>

            <Badge v-for="id in selectedBatches" :key="id" class="gap-1">
              {{ batches.find(b=>b.id===id)?.name || id }}
              <button class="ml-1" @click="selectedBatches = selectedBatches.filter(x=>x!==id)">×</button>
            </Badge>

            <Button v-if="selectedBatches.length" variant="ghost" size="sm" @click="selectedBatches=[]">Clear</Button>
          </div>
        </div>

        <!-- ========== Segmentation: chip rows ========== -->
        <div v-if="isSeg" class="space-y-6">
          <!-- Included row -->
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Filter class="h-4 w-4" />
              <UILabel>Included labels</UILabel>
            </div>
            <p class="text-xs text-muted-foreground">
              Tasks must contain <strong>at least one</strong> of these. Leave empty to include all labels by default.
            </p>

            <div class="flex flex-wrap gap-2">
              <!-- show only labels not on exclude side -->
              <button
                v-for="l in includeOptions"
                :key="`inc-opt-${l.id}`"
                :class="chipClass(includeIds.includes(l.id))"
                type="button"
                @click="toggleInclude(l.id)"
                :aria-pressed="includeIds.includes(l.id)"
                :title="l.description || l.name"
              >
                <span class="inline-flex items-center gap-2">
                  <span class="inline-block h-3 w-3 rounded" :style="{ backgroundColor: l.color }"></span>
                  {{ l.name }}
                </span>
              </button>

              <Button v-if="includeIds.length" variant="ghost" size="sm" @click="clearIncluded">Clear</Button>
            </div>
          </div>

          <!-- Excluded row -->
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Filter class="h-4 w-4" />
              <UILabel>Excluded labels</UILabel>
            </div>
            <p class="text-xs text-muted-foreground">
              Tasks must <strong>not contain any</strong> of these. Leave empty to block nothing.
            </p>

            <div class="flex flex-wrap gap-2">
              <!-- show only labels not on include side -->
              <button
                v-for="l in excludeOptions"
                :key="`exc-opt-${l.id}`"
                :class="chipClass(excludeIds.includes(l.id))"
                type="button"
                @click="toggleExclude(l.id)"
                :aria-pressed="excludeIds.includes(l.id)"
                :title="l.description || l.name"
              >
                <span class="inline-flex items-center gap-2">
                  <span class="inline-block h-3 w-3 rounded" :style="{ backgroundColor: l.color }"></span>
                  {{ l.name }}
                </span>
              </button>

              <Button v-if="excludeIds.length" variant="ghost" size="sm" @click="clearExcluded">Clear</Button>
            </div>
          </div>
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="open = false">Cancel</Button>
        <Button class="gap-2" :disabled="!canExport" @click="runExport">
          <Download class="h-4 w-4" />
          Export
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

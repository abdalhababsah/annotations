<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import { Layers, Download } from 'lucide-vue-next'

type Batch = { id:number; name:string; status:string }

const props = defineProps<{
  projectId: number
  batches: Batch[]
  open?: boolean
}>()
const emit = defineEmits(['update:open'])

const open = ref(!!props.open)
watch(() => props.open, (v: boolean) => open.value = !!v)
watch(open, (v: boolean) => emit('update:open', v))

const type = ref<'json'|'csv'|'excel'>('excel')
const status = ref<'all'|'accepted'|'rejected'|'under_review'|'pending'|'assigned'|'in_progress'|'approved'>('all')
const scope = ref<'all'|'selected'>('all')
const selectedBatches = ref<number[]>([])

const canExport = computed(() => scope.value === 'all' || selectedBatches.value.length > 0)

const runExport = () => {
  const url = route('admin.projects.tasks.export', props.projectId)
  const params = new URLSearchParams()
  params.set('type', type.value)
  params.set('status', status.value)
  if (scope.value === 'selected' && selectedBatches.value.length) {
    selectedBatches.value.forEach(b => params.append('batches[]', String(b)))
  }
  // Force a file download (don’t go through Inertia)
  window.location.href = `${url}?${params.toString()}`
  open.value = false
}
import { watch as vueWatch } from 'vue'

function watch(source: any, cb: any) {
    return vueWatch(source, cb)
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Export Tasks</DialogTitle>
        <DialogDescription>Select the format and scope for export. Final dimension values (including reviewer fixes) and audio links are included.</DialogDescription>
      </DialogHeader>

      <div class="space-y-6 py-2">
        <!-- Format -->
        <div class="space-y-2">
          <Label>Format</Label>
          <RadioGroup v-model="type" class="grid grid-cols-3 gap-3">
            <div class="border rounded-lg p-3 hover:bg-muted cursor-pointer">
              <RadioGroupItem id="t-excel" value="excel" class="mr-2" />
              <Label for="t-excel">Excel (.xlsx)</Label>
            </div>
            <div class="border rounded-lg p-3 hover:bg-muted cursor-pointer">
              <RadioGroupItem id="t-csv" value="csv" class="mr-2" />
              <Label for="t-csv">CSV</Label>
            </div>
            <div class="border rounded-lg p-3 hover:bg-muted cursor-pointer">
              <RadioGroupItem id="t-json" value="json" class="mr-2" />
              <Label for="t-json">JSON</Label>
            </div>
          </RadioGroup>
        </div>

        <!-- Status -->
        <div class="space-y-2">
          <Label>Status</Label>
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

        <!-- Scope -->
        <div class="space-y-2">
          <Label>Scope</Label>
          <RadioGroup v-model="scope" class="grid grid-cols-2 gap-3">
            <div class="border rounded-lg p-3 hover:bg-muted cursor-pointer">
              <RadioGroupItem id="s-all" value="all" class="mr-2" />
              <Label for="s-all">All batches</Label>
            </div>
            <div class="border rounded-lg p-3 hover:bg-muted cursor-pointer">
              <RadioGroupItem id="s-selected" value="selected" class="mr-2" />
              <Label for="s-selected">Selected batches</Label>
            </div>
          </RadioGroup>
        </div>

        <!-- Batch Multi-pick -->
        <div v-if="scope==='selected'" class="space-y-2">
          <Label>Select batches</Label>
          <div class="flex flex-wrap items-center gap-2">
            <Select :modelValue="null" @update:modelValue="(v:any)=>{ if(v !== null && !selectedBatches.includes(v)) selectedBatches.push(v) }">
              <SelectTrigger class="min-w-[220px]">
                <SelectValue placeholder="Add a batch..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="b in batches" :key="b.id" :value="b.id">
                  <span class="flex items-center gap-2">
                    <Layers class="h-3 w-3" /> {{ b.name }} <Badge variant="secondary">{{ b.status }}</Badge>
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

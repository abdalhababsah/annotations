<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select'
import { Table, TableHeader, TableHead, TableBody, TableRow, TableCell } from '@/components/ui/table'
import {
  Pagination, PaginationContent, PaginationItem,
  PaginationPrevious, PaginationNext, PaginationFirst, PaginationLast, PaginationEllipsis
} from '@/components/ui/pagination'
import { Filter, Download, Search, Headphones, CheckCircle, XCircle, Layers } from 'lucide-vue-next'
import ExportDialog from '@/components/Tasks/ExportDialog.vue'

type Batch = { id:number; name:string; status:string }
type Row = {
  id:number; status:string; batch?:string|null;
  audio: { filename?:string|null; url?:string|null; duration?:number|null };
  submitted_at?:string|null; approved_at?:string|null;
}
type Dimension = { id:number; name:string; dimension_type:string }

const props = defineProps<{
  project: { id:number; name:string }
  tasks: {
    data: Row[]
    links: Array<{ url:string|null; label:string; active:boolean }>
    meta: { current_page:number; last_page:number; per_page:number; total:number; from:number|null; to:number|null }
  }
  filters: { q:string; status:string; batches:number[] }
  batches: Batch[]
  dimensions: Dimension[]
}>()

const pg = computed(() => props.tasks.meta)
const list = computed(() => props.tasks.data)

const q = ref(props.filters.q || '')
const status = ref(props.filters.status || 'all')
const selectedBatches = ref<number[]>(props.filters.batches || [])

let debounceTimer:number|undefined
const reload = (page?:number) => {
  router.get(route('admin.projects.tasks.index', props.project.id), {
    q: q.value || undefined,
    status: status.value !== 'all' ? status.value : undefined,
    batches: selectedBatches.value.length ? selectedBatches.value : undefined,
    page: page || undefined,
  }, { preserveScroll:true, preserveState:true, replace:true })
}
watch([status, selectedBatches], () => reload(1))
watch(q, () => { clearTimeout(debounceTimer); debounceTimer = window.setTimeout(() => reload(1), 400) })

const openExport = ref(false)
</script>

<template>
  <Head :title="`Tasks • ${project.name}`" />
  <AppLayout :breadcrumbs="[{ title: 'Projects', href: '/admin/projects' }, { title: project.name, href: `/admin/projects/${project.id}` }, { title:'Tasks', href:'#' }]">
    <div class="flex-1 p-6 space-y-6">

      <div class="flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Tasks</h1>
          <p class="text-muted-foreground">Browse and export tasks for <strong>{{ project.name }}</strong></p>
        </div>
        <Button class="gap-2" @click="openExport = true">
          <Download class="h-4 w-4" />
          Export
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Filter class="h-5 w-5" />
            Filters
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input v-model="q" placeholder="Search by Task ID or audio filename..." class="pl-10" />
            </div>

            <Select v-model="status">
              <SelectTrigger>
                <SelectValue placeholder="All statuses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All statuses</SelectItem>
                <SelectItem value="accepted">Accepted (Approved)</SelectItem>
                <SelectItem value="rejected">Rejected</SelectItem>
                <SelectItem value="under_review">Under Review</SelectItem>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="assigned">Assigned</SelectItem>
                <SelectItem value="in_progress">In Progress</SelectItem>
                <SelectItem value="approved">Approved</SelectItem>
              </SelectContent>
            </Select>

            <!-- simple multi-select for batches -->
            <div class="flex flex-wrap gap-2">
              <Select :modelValue="null" @update:modelValue="(value) => { if (typeof value === 'number' && !selectedBatches.includes(value)) selectedBatches.push(value) }">
                <SelectTrigger>
                  <SelectValue placeholder="Add Batch Filter" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="b in batches" :key="b.id" :value="b.id">
                    <span class="flex items-center gap-2">
                      <Layers class="h-3 w-3" /> {{ b.name }} <Badge variant="secondary">{{ b.status }}</Badge>
                    </span>
                  </SelectItem>
                </SelectContent>
              </Select>
              <div v-if="selectedBatches.length" class="flex items-center gap-2 flex-wrap">
                <Badge v-for="id in selectedBatches" :key="id" variant="secondary" class="gap-1">
                  {{ batches.find(b => b.id === id)?.name || id }}
                  <button class="ml-1" @click="selectedBatches = selectedBatches.filter(x => x !== id)">×</button>
                </Badge>
                <Button variant="ghost" size="sm" @click="selectedBatches = []">Clear</Button>
              </div>
            </div>
          </div>

          <div class="text-sm text-muted-foreground">
            <template v-if="pg.total > 0">
              Showing <strong>{{ pg.from }}</strong>–<strong>{{ pg.to }}</strong> of <strong>{{ pg.total }}</strong> tasks
            </template>
            <template v-else>No tasks</template>
          </div>
        </CardContent>
      </Card>

      <Card class="hidden md:block">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="w-20">ID</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Batch</TableHead>
              <TableHead>Audio</TableHead>
              <TableHead>Submitted</TableHead>
              <TableHead>Approved</TableHead>
              <TableHead class="text-right">Open</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="r in list" :key="r.id">
              <TableCell>#{{ r.id }}</TableCell>
              <TableCell>
                <Badge :variant="r.status === 'approved' ? 'default' : r.status === 'rejected' ? 'destructive' : 'secondary'">
                  <component :is="r.status === 'approved' ? CheckCircle : (r.status === 'rejected' ? XCircle : Filter)" class="h-3 w-3 mr-1" />
                  {{ r.status }}
                </Badge>
              </TableCell>
              <TableCell>{{ r.batch || '—' }}</TableCell>
              <TableCell>
                <div class="flex items-center gap-2">
                  <Headphones class="h-4 w-4 text-muted-foreground" />
                  <span class="truncate max-w-[220px]">{{ r.audio?.filename || '—' }}</span>
                </div>
              </TableCell>
              <TableCell>{{ r.submitted_at || '—' }}</TableCell>
              <TableCell>{{ r.approved_at || '—' }}</TableCell>
              <TableCell class="text-right">
                <a v-if="r.audio?.url" :href="r.audio.url" target="_blank" class="text-primary hover:underline">Open audio</a>
                <span v-else class="text-muted-foreground">—</span>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </Card>

      <!-- Mobile list -->
      <div class="grid grid-cols-1 gap-3 md:hidden">
        <Card v-for="r in list" :key="r.id">
          <CardContent class="p-4 space-y-2">
            <div class="flex items-center justify-between">
              <div class="font-semibold">#{{ r.id }}</div>
              <Badge :variant="r.status === 'approved' ? 'default' : r.status === 'rejected' ? 'destructive' : 'secondary'">
                {{ r.status }}
              </Badge>
            </div>
            <div class="text-sm text-muted-foreground">Batch: {{ r.batch || '—' }}</div>
            <div class="flex items-center gap-2">
              <Headphones class="h-4 w-4 text-muted-foreground" />
              <div class="text-sm truncate">{{ r.audio?.filename || '—' }}</div>
            </div>
            <div class="text-xs text-muted-foreground">
              Submitted: {{ r.submitted_at || '—' }} • Approved: {{ r.approved_at || '—' }}
            </div>
            <div>
              <a v-if="r.audio?.url" :href="r.audio.url" class="text-primary hover:underline" target="_blank">Open audio</a>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between">
        <div class="text-sm text-muted-foreground">Page <strong>{{ pg.current_page }}</strong> of <strong>{{ pg.last_page }}</strong></div>
        <Pagination v-if="pg.last_page > 1" :page="pg.current_page" :items-per-page="pg.per_page" :total="pg.total" @update:page="(p:number)=>reload(p)">
          <PaginationContent v-slot="{ items }">
            <PaginationFirst @click="reload(1)" />
            <PaginationPrevious @click="reload(Math.max(1, pg.current_page - 1))" />
            <template v-for="(item,i) in items" :key="i">
              <PaginationItem v-if="item.type==='page'" :value="item.value" :is-active="item.value===pg.current_page" @click.prevent="reload(item.value)">{{ item.value }}</PaginationItem>
              <PaginationEllipsis v-else />
            </template>
            <PaginationNext @click="reload(Math.min(pg.last_page, pg.current_page + 1))" />
            <PaginationLast @click="reload(pg.last_page)" />
          </PaginationContent>
        </Pagination>
      </div>
    </div>

    <!-- Export dialog -->
    <ExportDialog
      v-model:open="openExport"
      :project-id="project.id"
      :batches="batches"
    />
  </AppLayout>
</template>

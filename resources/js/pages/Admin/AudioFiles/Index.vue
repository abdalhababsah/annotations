<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
  Card, CardHeader, CardContent, CardTitle, CardFooter
} from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Table, TableHeader, TableRow, TableHead, TableBody, TableCell } from '@/components/ui/table'
import { Select, SelectTrigger, SelectItem, SelectValue, SelectContent } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import { Progress } from '@/components/ui/progress'
import { Filter, Search, Trash } from 'lucide-vue-next'
import AudioFilesDialog from '@/components/Audios/AudioFilesDialog.vue'

/* ========= Types ========= */
interface Uploader { id: number; name: string; email: string }
interface AudioFile {
  id: number
  original_filename: string
  stored_filename: string
  file_path: string
  file_size: number
  mime_type: string
  duration: number | null
  url: string | null
  created_at: string
  uploader?: { id: number; name?: string; email?: string } | null
}
interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}
interface Props {
  project: { id: number; name: string }
  audioFiles: {
    data: AudioFile[]
    meta: PaginationMeta
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  filters: Record<string, any>
  uploaders: Uploader[]
}
const props = defineProps<Props>()

/* ========= Filters (no size filters) ========= */
const q = ref(props.filters?.q ?? '')
const uploader = ref(props.filters?.uploader ?? 'all')
const mime = ref(props.filters?.mime ?? '')
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')
const durMin = ref(props.filters?.dur_min ?? '')
const durMax = ref(props.filters?.dur_max ?? '')
const sort = ref(props.filters?.sort ?? 'created_at')
const direction = ref(props.filters?.direction ?? 'desc')
const perPage = ref(String(props.audioFiles.meta?.per_page ?? 10))

/* ========= Data & meta ========= */
const pg = computed(() => props.audioFiles.meta)
const rows = computed(() => props.audioFiles.data)

/* ========= Reload (same pattern as Projects) ========= */
const reload = (page?: number) => {
  router.get(
    route('admin.projects.audio-files.index', props.project.id),
    {
      q: (q.value || undefined),
      uploader: uploader.value !== 'all' ? uploader.value : undefined,
      mime: (mime.value || undefined),
      date_from: (dateFrom.value || undefined),
      date_to: (dateTo.value || undefined),
      dur_min: (durMin.value || undefined),
      dur_max: (durMax.value || undefined),
      sort: sort.value,
      direction: direction.value,
      per_page: Number(perPage.value) || undefined,
      page: page ?? undefined,
    },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

/* debounce filter changes */
let timer: number | undefined
watch([q, uploader, mime, dateFrom, dateTo, durMin, durMax, sort, direction, perPage], () => {
  window.clearTimeout(timer)
  timer = window.setTimeout(() => reload(1), 350)
})


/* ========= Helpers ========= */
const fmtBytes = (n?: number) => (typeof n === 'number' ? n.toLocaleString() : '—')
</script>

<template>
  <Head :title="`Audio Files • ${project.name}`" />

  <AppLayout
    :breadcrumbs="[
      { title: 'Projects', href: '/admin/projects' },
      { title: project.name, href: route('admin.projects.show', project.id) },
      { title: 'Audio Files', href: '#' }
    ]"
  >
    <div class="flex flex-col gap-6 p-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Audio Files</h1>
          <p class="text-muted-foreground">Manage audio media for this project</p>
        </div>
        <AudioFilesDialog :project-id="project.id" />
      </div>

      <!-- Filters -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Filter class="h-4 w-4" /> Filters
          </CardTitle>
        </CardHeader>
        <CardContent class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
          <div class="relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input v-model="q" placeholder="Search filename or mime..." class="pl-10" />
          </div>

          <div>
            <Select v-model="uploader">
              <SelectTrigger>
                <SelectValue placeholder="Uploader" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Uploaders</SelectItem>
                <SelectItem v-for="u in uploaders" :key="u.id" :value="String(u.id)">
                  {{ u.name }} ({{ u.email }})
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <Input v-model="mime" placeholder="MIME (e.g. audio/mpeg)" />

          <div class="grid grid-cols-2 gap-2">
            <Input type="date" v-model="dateFrom" />
            <Input type="date" v-model="dateTo" />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <Input v-model="durMin" type="number" step="0.01" placeholder="Min duration (s)" />
            <Input v-model="durMax" type="number" step="0.01" placeholder="Max duration (s)" />
          </div>

          <div class="grid grid-cols-3 gap-2">
            <Select v-model="sort">
              <SelectTrigger><SelectValue placeholder="Sort" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="created_at">Created</SelectItem>
                <SelectItem value="original_filename">Name</SelectItem>
                <SelectItem value="file_size">Size</SelectItem>
                <SelectItem value="duration">Duration</SelectItem>
              </SelectContent>
            </Select>

            <Select v-model="direction">
              <SelectTrigger><SelectValue placeholder="Order" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="desc">Desc</SelectItem>
                <SelectItem value="asc">Asc</SelectItem>
              </SelectContent>
            </Select>

            <Select v-model="perPage">
              <SelectTrigger><SelectValue placeholder="Per page" /></SelectTrigger>
              <SelectContent>
                <SelectItem v-for="n in [10,20,50,100]" :key="n" :value="String(n)">{{ n }}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <!-- Table -->
      <Card>
        <CardContent class="p-0">
          <Table>
            <TableHeader>
              <TableRow>
           
                <TableHead>Filename</TableHead>
                <TableHead>MIME</TableHead>
                <TableHead>Duration</TableHead>
                <TableHead>Size</TableHead>
                <TableHead>Uploader</TableHead>
                <TableHead>Created</TableHead>
                <TableHead class="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="row in rows" :key="row.id">
      
                <TableCell class="font-medium">
                  <a :href="row.url || '#'" target="_blank" class="underline" :class="!row.url && 'pointer-events-none opacity-50'">
                    {{ row.original_filename }}
                  </a>
                  <div class="text-xs text-muted-foreground break-all">{{ row.file_path }}</div>
                </TableCell>

                <TableCell>{{ row.mime_type || '—' }}</TableCell>
                <TableCell>{{ row.duration ?? '—' }}</TableCell>
                <TableCell>{{ fmtBytes(row.file_size) }} B</TableCell>
                <TableCell>{{ row.uploader?.name || row.uploader?.email || '—' }}</TableCell>
                <TableCell>{{ new Date(row.created_at).toLocaleString() }}</TableCell>

                <TableCell class="text-right">
                  <Button variant="outline" size="sm" as-child :disabled="!row.url">
                    <a :href="row.url || '#'" target="_blank">Open</a>
                  </Button>
                  <Button
                    variant="destructive"
                    size="sm"
                    class="ml-2"
                    @click="router.delete(route('admin.projects.audio-files.destroy', [project.id, row.id]), { preserveScroll: true })"
                  >
                    <Trash class="h-4 w-4" />
                  </Button>
                </TableCell>
              </TableRow>

              <TableRow v-if="rows.length === 0">
                <TableCell colspan="8" class="text-center py-10 text-muted-foreground">
                  No audio files found.
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>

        <!-- Footer: summary + first/prev/next/last (same vibe as Projects) -->
        <CardFooter class="flex items-center justify-between">
          <div class="text-sm text-muted-foreground">
            <template v-if="pg.total > 0">
              Showing <b>{{ pg.from }}</b>–<b>{{ pg.to }}</b> of <b>{{ pg.total }}</b>
            </template>
            <template v-else>No items</template>
          </div>

          <div class="flex items-center gap-2">
     

            <div class="flex items-center gap-1">
              <Button size="sm" variant="outline" :disabled="pg.current_page <= 1" @click="reload(1)">First</Button>
              <Button size="sm" variant="outline" :disabled="pg.current_page <= 1" @click="reload(pg.current_page - 1)">Prev</Button>
              <Button size="sm" variant="outline" :disabled="pg.current_page >= pg.last_page" @click="reload(pg.current_page + 1)">Next</Button>
              <Button size="sm" variant="outline" :disabled="pg.current_page >= pg.last_page" @click="reload(pg.last_page)">Last</Button>
            </div>
          </div>
        </CardFooter>
      </Card>
    </div>
  </AppLayout>
</template>

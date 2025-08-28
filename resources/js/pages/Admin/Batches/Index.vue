<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, reactive, watch, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import CreateBatchDialog from '@/components/batches/CreateBatchDialog.vue'
import EditBatchDialog from '@/components/batches/EditBatchDialog.vue'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogCancel, AlertDialogAction } from '@/components/ui/alert-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationPrevious,
  PaginationNext,
  PaginationFirst,
  PaginationLast,
  PaginationEllipsis
} from '@/components/ui/pagination'
import {
  Plus,
  FolderOpen,
  Play,
  Pause,
  MoreVertical,
  Search,
  Filter,
  AlertTriangle,
  CheckCircle2,
  Clock,
  Users,
  BarChart3,
  Trash,
  Copy,
  Edit,
  Activity
} from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

/* ===================== Types ===================== */
interface Owner {
  id: number
  name: string
  email: string
}

interface Batch {
  id: number
  name: string
  description: string | null
  status: 'draft' | 'published' | 'in_progress' | 'completed' | 'paused'
  total_tasks: number
  completed_tasks: number
  approved_tasks: number
  rejected_tasks: number
  completion_percentage: number
  created_at: string
  published_at: string | null
  completed_at: string | null
  creator: Owner
  can_be_published: boolean
  can_be_paused: boolean
  can_be_resumed: boolean
  can_be_deleted: boolean
}

interface BatchesPayload {
  data: Batch[]
  links: Array<{ url: string | null; label: string; active: boolean; page?: number | null }>
  meta: PaginationMeta
}

interface Props {
  project: {
    id: number
    name: string
    status: string
  }
  batches: BatchesPayload
  statistics: {
    total_batches: number
    published_batches: number
    in_progress_batches: number
    completed_batches: number
    draft_batches: number
    paused_batches: number
    total_tasks: number
    total_completed_tasks: number
    average_completion: number
  }
  filters: {
    status?: string
    search?: string
    sort?: string
    direction?: 'asc' | 'desc'
    perPage?: number
  }
  can: {
    create: boolean
    manage: boolean
  }
}
/* ===================== Types ===================== */
interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

/* ===================== Props ===================== */
const props = defineProps<Props>()

/* ===================== State ===================== */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Projects', href: '/admin/projects' },
  { title: props.project.name, href: `/admin/projects/${props.project.id}` },
  { title: 'Batches', href: `/admin/projects/${props.project.id}/batches` }
]

const showCreateDialog = ref(false)
const showEditDialog = ref(false)
const showDeleteDialog = ref(false)
const batchToEdit = ref<Batch | null>(null)
const batchToDelete = ref<Batch | null>(null)
const activeActionsMenu = ref<number | null>(null)

// Local filters for real-time updates
const localFilters = reactive({
  search: props.filters.search || '',
  status: props.filters.status || '',
  sort: props.filters.sort || 'created_at',
  direction: props.filters.direction || 'desc' as 'asc' | 'desc',
  perPage: props.filters.perPage || 15,
})

/* ===================== Computed ===================== */
const pg = computed(() => props.batches.meta)
const listedBatches = computed<Batch[]>(() => props.batches.data)


/* ===================== Methods ===================== */
let searchTimer: number | undefined
const reload = (pageNum?: number) => {
  router.get(
    route('admin.projects.batches.index', props.project.id),
    {
      search: (localFilters.search || '').trim() || undefined,
      status: localFilters.status !== 'all' ? localFilters.status : undefined,   // <-- only send if not 'all'
      sort: localFilters.sort,
      direction: localFilters.direction,
      perPage: localFilters.perPage,
      page: pageNum ?? undefined
    },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

// Watch for filter changes and update URL
watch(localFilters, () => {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => reload(1), 400)
}, { deep: true })

const clearFilters = () => {
  localFilters.search = ''
  localFilters.status = ''
  localFilters.sort = 'created_at'
  localFilters.direction = 'desc'
  localFilters.perPage = 15
  reload(1)
}

const onBatchCreated = () => {
  reload()
}

const onBatchUpdated = () => {
  reload()
}

const publishBatch = (batch: Batch) => {
  router.post(route('admin.projects.batches.publish', [props.project.id, batch.id]), {}, {
    preserveScroll: true,
  })
}

const pauseBatch = (batch: Batch) => {
  router.post(route('admin.projects.batches.pause', [props.project.id, batch.id]), {}, {
    preserveScroll: true,
  })
}

const resumeBatch = (batch: Batch) => {
  router.post(route('admin.projects.batches.resume', [props.project.id, batch.id]), {}, {
    preserveScroll: true,
  })
}

const duplicateBatch = (batch: Batch) => {
  router.post(route('admin.projects.batches.duplicate', [props.project.id, batch.id]), {}, {
    preserveScroll: true,
    onSuccess: () => {
      activeActionsMenu.value = null
    },
  })
}

const editBatch = (batch: Batch) => {
  batchToEdit.value = batch
  showEditDialog.value = true
  activeActionsMenu.value = null
}

const showDeleteConfirmation = (batch: Batch) => {
  batchToDelete.value = batch
  showDeleteDialog.value = true
  activeActionsMenu.value = null
}

const confirmDelete = () => {
  if (!batchToDelete.value) return

  router.delete(route('admin.projects.batches.destroy', [props.project.id, batchToDelete.value.id]), {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteDialog.value = false
      batchToDelete.value = null
    },
  })
}

const toggleActionsMenu = (batchId: number) => {
  activeActionsMenu.value = activeActionsMenu.value === batchId ? null : batchId
}

const getStatusVariant = (status: string) => {
  switch (status) {
    case 'published': return 'default'
    case 'in_progress': return 'secondary'
    case 'completed': return 'default'
    case 'paused': return 'outline'
    case 'draft': return 'secondary'
    default: return 'secondary'
  }
}

const getStatusIcon = (status: string) => {
  switch (status) {
    case 'published': return Play
    case 'in_progress': return Activity
    case 'completed': return CheckCircle2
    case 'paused': return Pause
    case 'draft': return Edit
    default: return FolderOpen
  }
}

const formatDate = (dateString: string) => new Date(dateString).toLocaleDateString()

// Close actions menu when clicking outside
const handleClickOutside = (event: any) => {
  if (!event.target.closest('.actions-menu-container')) {
    activeActionsMenu.value = null
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>

  <Head title="Batches" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Batches</h1>
          <p class="text-muted-foreground">Manage and organize tasks into batches for {{ project.name }}</p>
        </div>

        <Button v-if="can.create" @click="showCreateDialog = true" size="lg" class="gap-2">
          <Plus class="h-4 w-4" />
          Create Batch
        </Button>
      </div>

      <!-- Statistics -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-2">
        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <div class="p-2 bg-primary/10 rounded-lg">
                <FolderOpen class="h-4 w-4 text-primary" />
              </div>
              <div>
                <p class="text-2xl font-bold">{{ statistics.total_batches }}</p>
                <p class="text-xs text-muted-foreground">Total Batches</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <div class="p-2 bg-green-500/10 rounded-lg">
                <Activity class="h-4 w-4 text-green-600" />
              </div>
              <div>
                <p class="text-2xl font-bold">{{ statistics.published_batches + statistics.in_progress_batches }}</p>
                <p class="text-xs text-muted-foreground">Active Batches</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <div class="p-2 bg-blue-500/10 rounded-lg">
                <CheckCircle2 class="h-4 w-4 text-blue-600" />
              </div>
              <div>
                <p class="text-2xl font-bold">{{ statistics.total_completed_tasks }}</p>
                <p class="text-xs text-muted-foreground">Completed Tasks</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <div class="p-2 bg-amber-500/10 rounded-lg">
                <BarChart3 class="h-4 w-4 text-amber-600" />
              </div>
              <div>
                <p class="text-2xl font-bold">{{ Math.round(statistics.average_completion) }}%</p>
                <p class="text-xs text-muted-foreground">Avg. Completion</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Filters -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Filter class="h-5 w-5" />
            Filters & Search
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <!-- Search -->
            <div class="relative w-full lg:max-w-xs">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input v-model="localFilters.search" placeholder="Search batches..." class="pl-10" />
            </div>

            <!-- Right controls -->
            <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto lg:ml-auto">
              <Select v-model="localFilters.status">
                <SelectTrigger class="w-[180px]">
                  <SelectValue placeholder="All Statuses" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Statuses</SelectItem> <!-- <-- not "" -->
                  <SelectItem value="draft">Draft</SelectItem>
                  <SelectItem value="published">Published</SelectItem>
                  <SelectItem value="in_progress">In Progress</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                  <SelectItem value="paused">Paused</SelectItem>
                </SelectContent>
              </Select>

              <Select v-model="localFilters.sort">
                <SelectTrigger class="w-[160px]">
                  <SelectValue placeholder="Sort by" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="created_at">Created Date</SelectItem>
                  <SelectItem value="name">Name</SelectItem>
                  <SelectItem value="status">Status</SelectItem>
                  <SelectItem value="completion_percentage">Progress</SelectItem>
                </SelectContent>
              </Select>

              <Select v-model="localFilters.direction">
                <SelectTrigger class="w-[120px]">
                  <SelectValue placeholder="Order" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="desc">Descending</SelectItem>
                  <SelectItem value="asc">Ascending</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
              <template v-if="pg.total > 0">
                Showing <strong>{{ pg.from }}</strong>–<strong>{{ pg.to }}</strong> of <strong>{{ pg.total }}</strong>
                batches
              </template>
              <template v-else>No batches found</template>
            </p>
            <Button
              v-if="localFilters.search || localFilters.status !== 'all' || localFilters.sort !== 'created_at' || localFilters.direction !== 'desc'"
              variant="outline" size="sm" @click="clearFilters">
              Clear Filters
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Batch Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <Card v-for="batch in listedBatches" :key="batch.id"
          class="hover:shadow-lg transition-all duration-200 border-border hover:border-primary/20">
          <CardHeader>
            <div class="flex items-start justify-between">
              <div class="flex-1 min-w-0">
                <CardTitle class="text-lg truncate flex items-center gap-2">
                  <component :is="getStatusIcon(batch.status)" class="h-5 w-5 text-muted-foreground" />
                  {{ batch.name }}
                </CardTitle>
                <p class="text-sm text-muted-foreground mt-1 line-clamp-2">
                  {{ batch.description || 'No description provided' }}
                </p>
              </div>
              <div class="flex flex-col items-end gap-2">
                <Badge :variant="getStatusVariant(batch.status)">{{ batch.status }}</Badge>
                <div class="relative actions-menu-container" v-if="can.manage">
                  <Button variant="ghost" size="sm" @click="toggleActionsMenu(batch.id)" class="h-8 w-8 p-0">
                    <MoreVertical class="h-4 w-4" />
                  </Button>

                  <div v-show="activeActionsMenu === batch.id"
                    class="absolute right-0 mt-2 w-48 bg-background border rounded-md shadow-lg z-10">
                    <div class="py-1">
                      <button v-if="batch.status === 'draft'" @click="editBatch(batch)"
                        class="flex items-center w-full px-4 py-2 text-sm text-foreground hover:bg-muted">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit
                      </button>

                      <button @click="duplicateBatch(batch)"
                        class="flex items-center w-full px-4 py-2 text-sm text-foreground hover:bg-muted">
                        <Copy class="mr-2 h-4 w-4" />
                        Duplicate
                      </button>

                      <button v-if="batch.can_be_deleted" @click="showDeleteConfirmation(batch)"
                        class="flex items-center w-full px-4 py-2 text-sm text-destructive hover:bg-muted">
                        <Trash class="mr-2 h-4 w-4" />
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </CardHeader>

          <CardContent>
            <div class="space-y-4">
              <!-- Warning if no tasks -->
              <Alert v-if="batch.total_tasks === 0" class="border-amber-200 bg-amber-50">
                <AlertTriangle class="h-4 w-4 text-amber-600" />
                <AlertDescription class="text-amber-800">
                  <strong>No tasks added yet.</strong> Add tasks before publishing this batch.
                </AlertDescription>
              </Alert>

              <!-- Progress -->
              <div v-if="batch.total_tasks > 0">
                <div class="flex justify-between text-sm mb-2">
                  <span class="text-muted-foreground">Progress</span>
                  <span class="font-medium">{{ batch.completion_percentage }}%</span>
                </div>
                <Progress :value="batch.completion_percentage" class="h-2" />
              </div>

              <!-- Statistics -->
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="flex items-center gap-2">
                  <BarChart3 class="h-4 w-4 text-muted-foreground" />
                  <span>{{ batch.total_tasks }} tasks</span>
                </div>
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="h-4 w-4 text-green-600" />
                  <span>{{ batch.completed_tasks }} done</span>
                </div>
                <div class="flex items-center gap-2">
                  <Users class="h-4 w-4 text-blue-600" />
                  <span>{{ batch.approved_tasks }} approved</span>
                </div>
                <div class="flex items-center gap-2">
                  <AlertTriangle class="h-4 w-4 text-red-600" />
                  <span>{{ batch.rejected_tasks }} rejected</span>
                </div>
              </div>

              <!-- Meta info -->
              <div class="pt-2 border-t space-y-1 text-xs text-muted-foreground">
                <div>Created by {{ batch.creator.name }}</div>
                <div>{{ formatDate(batch.created_at) }}</div>
                <div v-if="batch.published_at">Published {{ formatDate(batch.published_at) }}</div>
              </div>
            </div>
          </CardContent>

          <CardFooter class="flex gap-2">
            <!-- Publish Button -->
            <Button v-if="batch.can_be_published && can.manage" @click="publishBatch(batch)" class="flex-1"
              :disabled="batch.total_tasks === 0">
              <Play class="mr-2 h-4 w-4" />
              Publish
            </Button>

            <!-- Pause Button -->
            <Button v-else-if="batch.can_be_paused && can.manage" @click="pauseBatch(batch)" variant="secondary"
              class="flex-1">
              <Pause class="mr-2 h-4 w-4" />
              Pause
            </Button>

            <!-- Resume Button -->
            <Button v-else-if="batch.can_be_resumed && can.manage" @click="resumeBatch(batch)" class="flex-1">
              <Play class="mr-2 h-4 w-4" />
              Resume
            </Button>

            <!-- View Button -->
            <Link v-else :href="route('admin.projects.batches.show', [project.id, batch.id])" class="flex-1">
            <Button variant="outline" class="w-full">
              View Batch
            </Button>
            </Link>
          </CardFooter>
        </Card>
      </div>

      <!-- Empty State -->
      <Card v-if="listedBatches.length === 0" class="text-center py-16">
        <CardContent>
          <div class="max-w-md mx-auto">
            <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mx-auto mb-4">
              <FolderOpen class="h-8 w-8 text-muted-foreground" />
            </div>
            <h3 class="text-lg font-semibold mb-2">
              {{ pg.total === 0 ? 'No batches yet' : 'No batches found' }}
            </h3>
            <p class="text-muted-foreground mb-6">
              {{
                pg.total === 0
                  ? "You haven't created any batches yet. Start by creating your first batch to organize tasks."
                  : 'No batches match your current filters. Try adjusting your search criteria.'
              }}
            </p>
            <div class="flex flex-col sm:flex-row gap-2 justify-center">
              <Button v-if="can.create && pg.total === 0" @click="showCreateDialog = true" class="gap-2">
                <Plus class="h-4 w-4" />
                Create Your First Batch
              </Button>
              <Button v-if="pg.total > 0" variant="outline" @click="clearFilters" class="gap-2">
                <Filter class="h-4 w-4" />
                Clear All Filters
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Pagination -->
      <div class="flex items-center justify-between" v-if="pg.last_page > 1">
        <div class="text-sm text-muted-foreground">
          Page <strong>{{ pg.current_page }}</strong> of <strong>{{ pg.last_page }}</strong>
        </div>

        <Pagination class="ml-auto" :page="pg.current_page || 1" :items-per-page="pg.per_page || 15"
          :total="pg.total || 0" @update:page="(p: number) => reload(p)">
          <PaginationContent v-slot="{ items }">
            <PaginationFirst @click="reload(1)" />
            <PaginationPrevious @click="reload(Math.max(1, (pg.current_page || 1) - 1))" />

            <template v-for="(item, i) in items" :key="i">
              <PaginationItem v-if="item.type === 'page'" :value="item.value"
                :is-active="item.value === pg.current_page" @click.prevent="reload(item.value)">
                {{ item.value }}
              </PaginationItem>
              <PaginationEllipsis v-else />
            </template>

            <PaginationNext @click="reload(Math.min(pg.last_page || 1, (pg.current_page || 1) + 1))" />
            <PaginationLast @click="reload(pg.last_page || 1)" />
          </PaginationContent>
        </Pagination>
      </div>
    </div>

    <!-- Create Batch Dialog -->
    <CreateBatchDialog :project-id="project.id" :open="showCreateDialog" @update:open="showCreateDialog = $event"
      @batch-created="onBatchCreated" />

    <!-- Edit Batch Dialog -->
    <EditBatchDialog :project-id="project.id" :open="showEditDialog" :batch="batchToEdit"
      @update:open="showEditDialog = $event" @batch-updated="onBatchUpdated" />

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete Batch</AlertDialogTitle>
          <AlertDialogDescription>
            Are you sure you want to delete "{{ batchToDelete?.name }}"? This action cannot be undone and will delete
            all
            associated tasks.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="showDeleteDialog = false">Cancel</AlertDialogCancel>
          <AlertDialogAction @click="confirmDelete"
            class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AppLayout>
</template>
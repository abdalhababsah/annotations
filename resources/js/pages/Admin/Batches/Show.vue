<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogCancel, AlertDialogAction } from '@/components/ui/alert-dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
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
  AlertTriangle,
  CheckCircle2,
  Clock,
  Users,
  BarChart3,
  Trash,
  Copy,
  Edit,
  Activity,
  ChevronRight,
  Music
} from 'lucide-vue-next'
import type { BreadcrumbItem } from '@/types'

/* ===================== Types ===================== */
interface AudioFile {
  id: number
  original_filename: string
  formatted_duration: string
  formatted_file_size: string
}

interface Task {
  id: number
  status: 'draft' | 'pending' | 'assigned' | 'in_progress' | 'completed' | 'approved' | 'rejected'
  audioFile: AudioFile | null
  assignee: { id: number; name: string } | null
  assigned_at: string | null
  started_at: string | null
  completed_at: string | null
  expires_at: string | null
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
  paused_at: string | null
  completed_at: string | null
  creator: { id: number; name: string }
  progress: any
}

interface Project {
  id: number
  name: string
  status: string
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface TasksPayload {
  data: Task[]
  links: Array<{ url: string | null; label: string; active: boolean }>
  meta: PaginationMeta
}

interface Props {
  project: Project
  batch: Batch
  tasks: TasksPayload
  availableAudioFiles: Array<{
    id: number
    original_filename: string
    duration: string
    file_size: string
  }>
  can: {
    manage: boolean
    publish: boolean
    pause: boolean
    resume: boolean
    delete: boolean
  }
}

/* ===================== Props ===================== */
const props = defineProps<Props>()

/* ===================== State ===================== */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Projects', href: '/admin/projects' },
  { title: props.project.name, href: `/admin/projects/${props.project.id}` },
  { title: 'Batches', href: `/admin/projects/${props.project.id}/batches` },
  { title: props.batch.name, href: `/admin/projects/${props.project.id}/batches/${props.batch.id}` }
]

const showAddTasksDialog = ref(false)
const showDeleteDialog = ref(false)
const showActionsMenu = ref(false)
const selectedAudioFiles = ref<number[]>([])
const addingTasks = ref(false)
const deletingBatch = ref(false)

/* ===================== Computed ===================== */
const pg = computed(() => props.tasks.meta)
const listedTasks = computed<Task[]>(() => props.tasks.data)

/* ===================== Methods ===================== */
const publishBatch = () => {
  router.post(route('admin.projects.batches.publish', [props.project.id, props.batch.id]), {}, {
    preserveScroll: true,
  })
}

const pauseBatch = () => {
  router.post(route('admin.projects.batches.pause', [props.project.id, props.batch.id]), {}, {
    preserveScroll: true,
  })
}

const resumeBatch = () => {
  router.post(route('admin.projects.batches.resume', [props.project.id, props.batch.id]), {}, {
    preserveScroll: true,
  })
}

const duplicateBatch = () => {
  router.post(route('admin.projects.batches.duplicate', [props.project.id, props.batch.id]), {}, {
    preserveScroll: true,
    onSuccess: () => {
      showActionsMenu.value = false
    },
  })
}

const editBatch = () => {
  router.visit(route('admin.projects.batches.edit', [props.project.id, props.batch.id]))
}

const showDeleteConfirmation = () => {
  showDeleteDialog.value = true
  showActionsMenu.value = false
}

const confirmDelete = () => {
  deletingBatch.value = true
  router.delete(route('admin.projects.batches.destroy', [props.project.id, props.batch.id]), {
    preserveScroll: true,
    onFinish: () => {
      deletingBatch.value = false
      showDeleteDialog.value = false
    },
  })
}

const addTasksToBatch = () => {
  addingTasks.value = true
  router.post(route('admin.projects.batches.add-tasks', [props.project.id, props.batch.id]), {
    audio_file_ids: selectedAudioFiles.value,
  }, {
    preserveScroll: true,
    onFinish: () => {
      addingTasks.value = false
    },
    onSuccess: () => {
      showAddTasksDialog.value = false
      selectedAudioFiles.value = []
    },
  })
}

const removeTask = (task: Task) => {
  if (confirm('Are you sure you want to remove this task from the batch?')) {
    router.delete(route('admin.projects.batches.remove-task', [props.project.id, props.batch.id, task.id]), {
      preserveScroll: true,
    })
  }
}

const toggleSelectAll = () => {
  if (selectedAudioFiles.value.length === props.availableAudioFiles.length) {
    selectedAudioFiles.value = []
  } else {
    selectedAudioFiles.value = props.availableAudioFiles.map(file => file.id)
  }
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

const getTaskStatusVariant = (status: string) => {
  switch (status) {
    case 'completed': return 'default'
    case 'approved': return 'default'
    case 'in_progress': return 'secondary'
    case 'assigned': return 'outline'
    case 'pending': return 'outline'
    case 'rejected': return 'destructive'
    case 'draft': return 'secondary'
    default: return 'secondary'
  }
}

const formatDate = (dateString: string) => new Date(dateString).toLocaleDateString()

const reload = (pageNum?: number) => {
  router.get(
    route('admin.projects.batches.show', [props.project.id, props.batch.id]),
    { page: pageNum ?? undefined },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

// Close actions menu when clicking outside
const handleClickOutside = (event: any) => {
  if (!event.target.closest('.actions-menu-container')) {
    showActionsMenu.value = false
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

  <Head :title="`${batch.name} - Batches`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <!-- Header -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div class="p-3 bg-primary/10 rounded-lg">
                <FolderOpen class="h-8 w-8 text-primary" />
              </div>
              <div>
                <CardTitle class="text-2xl">{{ batch.name }}</CardTitle>
                <p v-if="batch.description" class="text-muted-foreground mt-1">{{ batch.description }}</p>
                <div class="flex items-center gap-4 mt-2">
                  <Badge :variant="getStatusVariant(batch.status)">{{ batch.status }}</Badge>
                  <span class="text-sm text-muted-foreground">Created {{ formatDate(batch.created_at) }}</span>
                  <span v-if="batch.published_at" class="text-sm text-muted-foreground">
                    Published {{ formatDate(batch.published_at) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3" v-if="can.manage">
              <!-- Edit Button (only for draft) -->
              <Button v-if="batch.status === 'draft'" @click="editBatch" variant="outline" class="gap-2">
                <Edit class="h-4 w-4" />
                Edit
              </Button>

              <!-- Publish Button -->
              <Button v-if="can.publish" @click="publishBatch" :disabled="batch.total_tasks === 0" class="gap-2">
                <Play class="h-4 w-4" />
                Publish Batch
              </Button>

              <!-- Pause Button -->
              <Button v-if="can.pause" @click="pauseBatch" variant="secondary" class="gap-2">
                <Pause class="h-4 w-4" />
                Pause Batch
              </Button>

              <!-- Resume Button -->
              <Button v-if="can.resume" @click="resumeBatch" class="gap-2">
                <Play class="h-4 w-4" />
                Resume Batch
              </Button>

              <!-- More Actions -->
              <div class="relative actions-menu-container">
                <Button @click="showActionsMenu = !showActionsMenu" variant="outline" size="icon">
                  <MoreVertical class="h-4 w-4" />
                </Button>

                <div v-show="showActionsMenu"
                  class="absolute right-0 mt-2 w-48 bg-background border rounded-md shadow-lg z-10">
                  <div class="py-1">
                    <button @click="duplicateBatch"
                      class="flex items-center w-full px-4 py-2 text-sm text-foreground hover:bg-muted">
                      <Copy class="mr-2 h-4 w-4" />
                      Duplicate Batch
                    </button>
                    <button v-if="can.delete" @click="showDeleteConfirmation"
                      class="flex items-center w-full px-4 py-2 text-sm text-destructive hover:bg-muted">
                      <Trash class="mr-2 h-4 w-4" />
                      Delete Batch
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </CardHeader>
      </Card>

      <!-- Progress Overview -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <BarChart3 class="h-5 w-5" />
            Progress Overview
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div class="text-center p-4 bg-muted rounded-lg">
              <div class="text-2xl font-bold">{{ batch.total_tasks }}</div>
              <div class="text-sm text-muted-foreground">Total Tasks</div>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-lg">
              <div class="text-2xl font-bold text-blue-900">{{ batch.total_tasks - batch.completed_tasks }}</div>
              <div class="text-sm text-blue-700">Pending Tasks</div>
            </div>
            <div class="text-center p-4 bg-amber-50 rounded-lg">
              <div class="text-2xl font-bold text-amber-900">{{ batch.completed_tasks }}</div>
              <div class="text-sm text-amber-700">Completed Tasks</div>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
              <div class="text-2xl font-bold text-green-900">{{ batch.approved_tasks }}</div>
              <div class="text-sm text-green-700">Approved Tasks</div>
            </div>
          </div>

          <!-- Progress Bar -->
          <div>
            <div class="flex justify-between text-sm mb-2">
              <span>Progress</span>
              <span class="font-medium">{{ batch.completion_percentage }}%</span>
            </div>
            <Progress :value="batch.completion_percentage" class="h-2" />
          </div>

          <!-- Warning if no tasks -->
          <Alert v-if="batch.total_tasks === 0" class="mt-4 border-amber-200 bg-amber-50">
            <AlertTriangle class="h-4 w-4 text-amber-600" />
            <AlertDescription class="text-amber-800">
              <strong>No Tasks Added</strong>
              <p class="mt-1">This batch doesn't have any tasks yet. Add some audio files to create tasks before
                publishing.</p>
              <Button v-if="batch.status === 'draft'" @click="showAddTasksDialog = true" size="sm" variant="outline"
                class="mt-2 bg-amber-100 text-amber-800 hover:bg-amber-200 border-amber-300">
                Add Tasks Now
              </Button>
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>

      <!-- Tasks Section -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle class="flex items-center gap-2">
              <Music class="h-5 w-5" />
              Tasks ({{ pg.total }})
            </CardTitle>

            <!-- Add Tasks Button -->
            <Button v-if="batch.status === 'draft' && can.manage" @click="showAddTasksDialog = true" class="gap-2">
              <Plus class="h-4 w-4" />
              Add Tasks
            </Button>
          </div>
        </CardHeader>

        <CardContent>
          <!-- Tasks List -->
          <div v-if="listedTasks.length > 0" class="space-y-4">
            <div v-for="task in listedTasks" :key="task.id"
              class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50">
              <div class="flex items-center gap-4">
                <div class="p-2 bg-muted rounded-md">
                  <Music class="h-4 w-4 text-muted-foreground" />
                </div>

                <div class="min-w-0 flex-1">
                  <p class="font-medium">
                    {{ task.audioFile?.original_filename || 'Audio File' }}
                  </p>
                  <div class="flex items-center gap-4 mt-1">
                    <!-- Status -->
                    <Badge :variant="getTaskStatusVariant(task.status)">{{ task.status }}</Badge>

                    <!-- Duration -->
                    <span v-if="task.audioFile?.formatted_duration" class="text-xs text-muted-foreground">
                      {{ task.audioFile.formatted_duration }}
                    </span>

                    <!-- Assignee -->
                    <span v-if="task.assignee" class="text-xs text-muted-foreground">
                      Assigned to {{ task.assignee.name }}
                    </span>

                    <!-- Assigned Date -->
                    <span v-if="task.assigned_at" class="text-xs text-muted-foreground">
                      {{ formatDate(task.assigned_at) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Task Actions -->
              <div v-if="batch.status === 'draft' && can.manage" class="flex items-center">
                <Button @click="removeTask(task)" variant="ghost" size="sm"
                  class="text-destructive hover:text-destructive hover:bg-destructive/10">
                  <Trash class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="text-center py-16">
            <div class="max-w-md mx-auto">
              <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mx-auto mb-4">
                <Music class="h-8 w-8 text-muted-foreground" />
              </div>
              <h3 class="text-lg font-semibold mb-2">No tasks in this batch</h3>
              <p class="text-muted-foreground mb-6">
                Add some audio files to create tasks for annotation.
              </p>
              <Button v-if="batch.status === 'draft' && can.manage" @click="showAddTasksDialog = true" class="gap-2">
                <Plus class="h-4 w-4" />
                Add Tasks
              </Button>
            </div>
          </div>
        </CardContent>

        <!-- Pagination -->
        <div v-if="pg.last_page > 1" class="flex items-center justify-between border-t px-6 py-4">
          <div class="text-sm text-muted-foreground">
            Page <strong>{{ pg.current_page }}</strong> of <strong>{{ pg.last_page }}</strong>
          </div>

          <Pagination class="ml-auto" :page="pg.current_page || 1" :items-per-page="pg.per_page || 25"
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
      </Card>
    </div>

    <!-- Add Tasks Dialog -->
    <Dialog :open="showAddTasksDialog" @update:open="showAddTasksDialog = $event">
      <DialogContent class="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Plus class="h-5 w-5" />
            Add Tasks to Batch
          </DialogTitle>
        </DialogHeader>

        <div class="space-y-4">
          <p class="text-sm text-muted-foreground">
            Select audio files to create tasks in this batch.
          </p>

          <div v-if="availableAudioFiles.length === 0" class="text-center py-8">
            <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mx-auto mb-4">
              <Music class="h-8 w-8 text-muted-foreground" />
            </div>
            <h3 class="font-semibold mb-2">No audio files available</h3>
            <p class="text-sm text-muted-foreground">
              All audio files in this project already have tasks in this batch.
            </p>
          </div>

          <div v-else class="space-y-4">
            <div class="flex items-center space-x-2">
              <Checkbox id="select-all" :checked="selectedAudioFiles.length === availableAudioFiles.length"
                @update:checked="toggleSelectAll" />
              <Label for="select-all" class="font-medium">
                Select All ({{ availableAudioFiles.length }} files)
              </Label>
            </div>

            <div class="max-h-96 overflow-y-auto space-y-2 border rounded-md p-2">
              <div v-for="file in availableAudioFiles" :key="file.id"
                class="flex items-center space-x-3 p-3 rounded-md hover:bg-muted">
                <Checkbox :id="`file-${file.id}`" :value="file.id" v-model="selectedAudioFiles" />
                <div class="flex-1">
                  <div class="flex items-center justify-between">
                    <Label :for="`file-${file.id}`" class="font-medium cursor-pointer">
                      {{ file.original_filename }}
                    </Label>
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                      <span>{{ file.duration }}</span>
                      <span>•</span>
                      <span>{{ file.file_size }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-4" v-if="availableAudioFiles.length > 0">
            <Button variant="outline" @click="showAddTasksDialog = false" :disabled="addingTasks">
              Cancel
            </Button>
            <Button @click="addTasksToBatch" :disabled="addingTasks || selectedAudioFiles.length === 0">
              <span v-if="addingTasks" class="flex items-center gap-2">
                <div class="w-4 h-4 border-2 border-background border-t-transparent rounded-full animate-spin"></div>
                Adding Tasks...
              </span>
              <span v-else>
                Add {{ selectedAudioFiles.length }} Task{{ selectedAudioFiles.length !== 1 ? 's' : '' }}
              </span>
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle class="flex items-center gap-2">
            <AlertTriangle class="h-5 w-5 text-destructive" />
            Delete Batch
          </AlertDialogTitle>
          <AlertDialogDescription>
            Are you sure you want to delete "{{ batch.name }}"? This action cannot be undone and will delete all
            associated
            tasks.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="showDeleteDialog = false">Cancel</AlertDialogCancel>
          <AlertDialogAction @click="confirmDelete" :disabled="deletingBatch"
            class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
            <span v-if="deletingBatch" class="flex items-center gap-2">
              <div class="w-4 h-4 border-2 border-background border-t-transparent rounded-full animate-spin"></div>
              Deleting...
            </span>
            <span v-else>Delete</span>
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AppLayout>
</template>
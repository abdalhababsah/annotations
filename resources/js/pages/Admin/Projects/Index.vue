<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
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
import { type BreadcrumbItem } from '@/types'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import {
  Plus,
  Search,
  Filter,
  Calendar,
  Users,
  BarChart3,
  FileAudio,
  Clock,
  ListTodo,
  Settings,
  TrendingUp,
  Activity,
  FolderOpen,
  AlertCircle,
  CheckCircle2,
  Play,
  Wrench
} from 'lucide-vue-next'

/* ===================== Types ===================== */
interface Owner {
  id: number
  name: string
  email: string
}
interface Project {
  id: number
  name: string
  description: string | null
  status: 'draft' | 'active' | 'paused' | 'completed' | 'archived'
  project_type: 'audio'
  completion_percentage: number
  team_size: number
  task_time_minutes: number
  review_time_minutes: number
  audio_files_count: number
  tasks_count: number
  dimensions_count: number
  owner: Owner
  deadline: string | null
  created_at: string
  updated_at: string
  has_dimensions: boolean
  is_setup_incomplete: boolean
  can_be_activated: boolean
  setup_step: number
}
interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}
interface ProjectsPayload {
  data: Project[]
  links: Array<{ url: string | null; label: string; active: boolean }>
  meta: PaginationMeta
}
interface Props {
  projects: ProjectsPayload
  userRole: 'system_admin' | 'project_owner' | 'user'
  canCreateProject: boolean
  statistics: {
    total_projects: number
    active_projects: number
    completed_projects: number
    draft_projects: number
    incomplete_projects: number
  }
  filters?: {
    q?: string
    status?: string
    sort?: string
    direction?: 'asc' | 'desc'
  }
}

/* ===================== Props / Page ===================== */
const props = defineProps<Props>()


/* ===================== UI State (server-driven) ===================== */
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Projects', href: '/admin/projects' }]

// server-driven filters with fallbacks
const searchQuery = ref(props.filters?.q ?? '')
const statusFilter = ref(props.filters?.status ?? 'all')
const sortBy = ref(props.filters?.sort ?? 'created_at')
const sortOrder = ref<'asc' | 'desc'>((props.filters?.direction as 'asc' | 'desc') ?? 'desc')

const pg = computed(() => props.projects.meta)
const listedProjects = computed<Project[]>(() => props.projects.data)

/* ===================== Helpers ===================== */
const getStatusVariant = (status: string) => {
  switch (status) {
    case 'active': return 'default'
    case 'completed': return 'default'
    case 'paused': return 'outline'
    case 'draft': return 'secondary'
    case 'archived': return 'secondary'
    default: return 'secondary'
  }
}
const getStatusIcon = (status: string) => {
  switch (status) {
    case 'active': return Activity
    case 'completed': return BarChart3
    case 'paused': return Clock
    case 'draft': return Settings
    case 'archived': return FolderOpen
    default: return Settings
  }
}
const getSetupStepText = (step: number) =>
  step === 2 ? 'Configure Dimensions' : step === 3 ? 'Ready to Activate' : 'Setup Complete'

const getSetupStepIcon = (step: number) => (step === 2 ? Settings : step === 3 ? Play : CheckCircle2)
const formatDate = (dateString: string) => new Date(dateString).toLocaleDateString()

/* ===================== Server Reload ===================== */
let searchTimer: number | undefined
const reload = (pageNum?: number) => {
  router.get(
    route('admin.projects.index'),
    {
      q: (searchQuery.value || '').trim() || undefined,
      status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
      sort: sortBy.value,
      direction: sortOrder.value,
      page: pageNum ?? undefined
    },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

// debounce search
watch(searchQuery, () => {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => reload(1), 400)
})
watch([statusFilter, sortBy, sortOrder], () => reload(1))

const clearFilters = () => {
  searchQuery.value = ''
  statusFilter.value = 'all'
  sortBy.value = 'created_at'
  sortOrder.value = 'desc'
  reload(1)
}

/* ===================== Row Actions ===================== */
const quickActivate = (projectId: number) => {
  router.post(route('admin.projects.quick-activate', projectId), {}, { preserveScroll: true })
}
const continueSetup = (project: Project) => {
  if (project.setup_step === 2) {
    window.location.href = route('admin.projects.create.step-two', project.id)
  } else if (project.setup_step === 3) {
    window.location.href = route('admin.projects.create.step-three', project.id)
  }
}
</script>

<template>
  <Head title="Projects" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Projects</h1>
          <p class="text-muted-foreground">Manage and monitor your audio annotation projects</p>
        </div>
        <Link v-if="canCreateProject" :href="route('admin.projects.create')">
          <Button size="lg" class="gap-2">
            <Plus class="h-4 w-4" />
            Create Project
          </Button>
        </Link>
      </div>

      <!-- Incomplete Projects Alert -->
      <Alert v-if="statistics.incomplete_projects > 0" class="border-amber-200 bg-amber-50">
        <AlertCircle class="h-4 w-4 text-amber-600" />
        <AlertDescription class="text-amber-800">
          <strong>{{ statistics.incomplete_projects }} project{{ statistics.incomplete_projects > 1 ? 's' : '' }}</strong>
          need{{ statistics.incomplete_projects === 1 ? 's' : '' }} setup completion. Projects without dimensions cannot be activated.
        </AlertDescription>
      </Alert>

      <!-- Statistics -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-2">
        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <div class="p-2 bg-primary/10 rounded-lg">
                <BarChart3 class="h-4 w-4 text-primary" />
              </div>
              <div>
                <p class="text-2xl font-bold">{{ statistics.total_projects }}</p>
                <p class="text-xs text-muted-foreground">Total Projects</p>
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
                <p class="text-2xl font-bold">{{ statistics.active_projects }}</p>
                <p class="text-xs text-muted-foreground">Active</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <div class="p-2 bg-blue-500/10 rounded-lg">
                <TrendingUp class="h-4 w-4 text-blue-600" />
              </div>
              <div>
                <p class="text-2xl font-bold">{{ statistics.completed_projects }}</p>
                <p class="text-xs text-muted-foreground">Completed</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <div class="p-2 bg-amber-500/10 rounded-lg">
                <Settings class="h-4 w-4 text-amber-600" />
              </div>
              <div>
                <p class="text-2xl font-bold">{{ statistics.draft_projects }}</p>
                <p class="text-xs text-muted-foreground">Drafts</p>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="p-6">
            <div class="flex items-center gap-2">
              <div class="p-2 bg-red-500/10 rounded-lg">
                <Wrench class="h-4 w-4 text-red-600" />
              </div>
              <div>
                <p class="text-2xl font-bold">{{ statistics.incomplete_projects }}</p>
                <p class="text-xs text-muted-foreground">Need Setup</p>
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
              <Input
                v-model="searchQuery"
                placeholder="Search projects..."
                class="pl-10"
                @keydown.enter.prevent="reload(1)"
              />
            </div>

            <!-- Right controls (no per-page dropdown) -->
            <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto lg:ml-auto">
              <Select v-model="statusFilter" class="flex-1 min-w-[150px]">
                <SelectTrigger>
                  <SelectValue placeholder="All Statuses" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Statuses</SelectItem>
                  <SelectItem value="draft">Draft</SelectItem>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="paused">Paused</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                  <SelectItem value="archived">Archived</SelectItem>
                </SelectContent>
              </Select>

              <Select v-model="sortBy" class="flex-1 min-w-[130px]">
                <SelectTrigger>
                  <SelectValue placeholder="Sort by" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="created_at">Created Date</SelectItem>
                  <SelectItem value="name">Name</SelectItem>
                  <SelectItem value="completion_percentage">Progress</SelectItem>
                  <SelectItem value="team_size">Team Size</SelectItem>
                  <SelectItem value="tasks_count">Tasks</SelectItem>
                </SelectContent>
              </Select>

              <Select v-model="sortOrder" class="flex-1 min-w-[110px]">
                <SelectTrigger>
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
                Showing <strong>{{ pg.from }}</strong>–<strong>{{ pg.to }}</strong> of <strong>{{ pg.total }}</strong> projects
              </template>
              <template v-else>No projects</template>
            </p>
            <Button
              variant="outline"
              size="sm"
              @click="clearFilters"
              v-if="searchQuery || statusFilter !== 'all' || sortBy !== 'created_at' || sortOrder !== 'desc'"
            >
              Clear Filters
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Mobile Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:hidden">
        <Card
          v-for="project in listedProjects"
          :key="project.id"
          class="hover:shadow-lg transition-all duration-200 border-border hover:border-primary/20"
          :class="{ 'border-amber-200 bg-amber-50/30': project.is_setup_incomplete }"
        >
          <CardHeader>
            <div class="flex items-start justify-between">
              <div class="flex-1 min-w-0">
                <CardTitle class="text-lg truncate flex items-center gap-2">
                  {{ project.name }}
                  <AlertCircle v-if="!project.has_dimensions" class="h-4 w-4 text-amber-500 flex-shrink-0" />
                </CardTitle>
                <p class="text-sm text-muted-foreground mt-1 line-clamp-2">
                  {{ project.description || 'No description provided' }}
                </p>
              </div>
              <div class="flex flex-col items-end gap-2">
                <Badge :variant="getStatusVariant(project.status)">{{ project.status }}</Badge>
                <Badge
                  v-if="project.is_setup_incomplete"
                  variant="outline"
                  class="text-xs text-amber-700 border-amber-300"
                >
                  Setup Needed
                </Badge>
              </div>
            </div>
          </CardHeader>

          <CardContent>
            <div class="space-y-4">
              <Alert v-if="project.is_setup_incomplete" class="border-amber-200 bg-amber-50">
                <component :is="getSetupStepIcon(project.setup_step)" class="h-4 w-4 text-amber-600" />
                <AlertDescription class="text-amber-800 text-sm">
                  <strong>{{ getSetupStepText(project.setup_step) }}</strong>
                  <br v-if="project.setup_step === 2" />
                  <span v-if="project.setup_step === 2" class="text-xs">
                    Configure annotation dimensions to activate this project
                  </span>
                  <span v-else-if="project.setup_step === 3" class="text-xs">
                    Review your configuration and activate the project
                  </span>
                </AlertDescription>
              </Alert>

              <div v-if="project.status === 'active'">
                <div class="flex justify-between text-sm mb-2">
                  <span class="text-muted-foreground">Progress</span>
                  <span class="font-medium">{{ project.completion_percentage }}%</span>
                </div>
                <Progress :value="project.completion_percentage" class="h-2" />
              </div>

              <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="flex items-center gap-2">
                  <Users class="h-4 w-4 text-muted-foreground" />
                  <span>{{ project.team_size }} members</span>
                </div>
                <div class="flex items-center gap-2">
                  <ListTodo class="h-4 w-4 text-muted-foreground" />
                  <span>{{ project.tasks_count }} tasks</span>
                </div>
                <div class="flex items-center gap-2">
                  <FileAudio class="h-4 w-4 text-muted-foreground" />
                  <span>{{ project.audio_files_count }} files</span>
                </div>
                <div class="flex items-center gap-2">
                  <Settings class="h-4 w-4" :class="project.has_dimensions ? 'text-green-600' : 'text-red-500'" />
                  <span :class="project.has_dimensions ? 'text-green-600' : 'text-red-500'">
                    {{ project.dimensions_count }} dims
                  </span>
                </div>
              </div>

              <div class="pt-2 border-t space-y-1 text-xs text-muted-foreground">
                <div>Owner: {{ project.owner.name }}</div>
                <div class="flex items-center gap-1">
                  <Calendar class="h-3 w-3" />
                  Created {{ formatDate(project.created_at) }}
                </div>
                <div v-if="project.deadline" class="flex items-center gap-1">
                  <Clock class="h-3 w-3" />
                  Due {{ formatDate(project.deadline) }}
                </div>
              </div>
            </div>
          </CardContent>

          <CardFooter class="flex gap-2">
            <template v-if="project.is_setup_incomplete">
              <Button @click="continueSetup(project)" class="flex-1" variant="default">
                <component :is="getSetupStepIcon(project.setup_step)" class="mr-2 h-4 w-4" />
                {{ getSetupStepText(project.setup_step) }}
              </Button>
            </template>
            <template v-else-if="project.can_be_activated">
              <Button @click="quickActivate(project.id)" variant="default" class="flex-1">
                <Play class="mr-2 h-4 w-4" />
                Activate Project
              </Button>
            </template>
            <template v-else>
              <Link :href="route('admin.projects.show', project.id)" class="flex-1">
                <Button variant="outline" class="w-full">View Project</Button>
              </Link>
            </template>
          </CardFooter>
        </Card>
      </div>

      <!-- Desktop Table -->
      <Card class="hidden lg:block">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="w-[320px]">Project</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Setup</TableHead>
              <TableHead>Progress</TableHead>
              <TableHead>Team</TableHead>
              <TableHead>Tasks</TableHead>
              <TableHead>Files</TableHead>
              <TableHead>Owner</TableHead>
              <TableHead class="text-center">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="project in listedProjects" :key="project.id">
              <TableCell>
                <div class="flex items-start gap-3">
                  <div class="mt-0.5">
                    <component :is="getStatusIcon(project.status)" class="h-4 w-4 text-muted-foreground" />
                  </div>
                  <div class="space-y-1">
                    <div class="font-medium flex items-center gap-2">
                      {{ project.name }}
                      <AlertCircle v-if="!project.has_dimensions" class="h-4 w-4 text-amber-500" />
                    </div>
                    <div class="text-sm text-muted-foreground line-clamp-2">
                      {{ project.description || 'No description provided' }}
                    </div>
                    <div class="text-xs text-muted-foreground flex items-center gap-2">
                      <Calendar class="h-3 w-3" />
                      Created {{ formatDate(project.created_at) }}
                      <template v-if="project.deadline">
                        • <Clock class="h-3 w-3" /> Due {{ formatDate(project.deadline) }}
                      </template>
                    </div>
                  </div>
                </div>
              </TableCell>

              <TableCell class="capitalize">
                <Badge :variant="getStatusVariant(project.status)">{{ project.status }}</Badge>
              </TableCell>

              <TableCell>
                <div class="flex items-center gap-2 text-sm">
                  <Settings class="h-4 w-4" :class="project.has_dimensions ? 'text-green-600' : 'text-red-500'" />
                  <span :class="project.has_dimensions ? 'text-green-600' : 'text-red-500'">
                    {{ project.dimensions_count }} dims
                  </span>
                  <Badge v-if="project.is_setup_incomplete" variant="outline" class="ml-2">Needs Setup</Badge>
                </div>
              </TableCell>

              <TableCell class="min-w-[160px]">
                <div class="flex items-center gap-2">
                  <Progress :value="project.completion_percentage" class="h-2 w-28" />
                  <span class="text-sm font-medium w-10 text-right">{{ project.completion_percentage }}%</span>
                </div>
              </TableCell>

              <TableCell class="text-sm">
                <div class="flex items-center gap-2">
                  <Users class="h-4 w-4 text-muted-foreground" />
                  {{ project.team_size }}
                </div>
              </TableCell>

              <TableCell class="text-sm">
                <div class="flex items-center gap-2">
                  <ListTodo class="h-4 w-4 text-muted-foreground" />
                  {{ project.tasks_count }}
                </div>
              </TableCell>

              <TableCell class="text-sm">
                <div class="flex items-center gap-2">
                  <FileAudio class="h-4 w-4 text-muted-foreground" />
                  {{ project.audio_files_count }}
                </div>
              </TableCell>

              <TableCell class="text-sm text-muted-foreground">
                {{ project.owner.name }}
              </TableCell>

              <TableCell class="space-x-2 text-center">
                <template v-if="project.is_setup_incomplete">
                  <Button size="sm" @click="continueSetup(project)">
                    <component :is="getSetupStepIcon(project.setup_step)" class="mr-2 h-4 w-4" />
                    {{ getSetupStepText(project.setup_step) }}
                  </Button>
                </template>
                <template v-else-if="project.can_be_activated">
                  <Button size="sm" @click="quickActivate(project.id)">
                    <Play class="mr-2 h-4 w-4" />
                    Activate
                  </Button>
                </template>
                <template v-else>
                  <Link :href="route('admin.projects.show', project.id)">
                    <Button variant="outline" size="sm">View</Button>
                  </Link>
                </template>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </Card>

      <!-- Pagination -->
      <div class="flex items-center justify-between">
        <div class="text-sm text-muted-foreground">
          Page <strong>{{ pg.current_page }}</strong> of <strong>{{ pg.last_page }}</strong>
        </div>

        <Pagination
          v-if="pg.last_page > 1"
          class="ml-auto"
          :page="pg.current_page || 1"
          :items-per-page="pg.per_page || 12"
          :total="pg.total || 0"
          @update:page="(p:number) => reload(p)"
        >
          <!-- Using the Reka slot from our Shadcn wrapper -->
          <PaginationContent v-slot="{ items }">
            <PaginationFirst @click="reload(1)" />
            <PaginationPrevious @click="reload(Math.max(1, (pg.current_page || 1) - 1))" />

            <template v-for="(item, i) in items" :key="i">
              <PaginationItem
                v-if="item.type === 'page'"
                :value="item.value"
                :is-active="item.value === pg.current_page"
                @click.prevent="reload(item.value)"
              >
                {{ item.value }}
              </PaginationItem>
              <PaginationEllipsis v-else />
            </template>

            <PaginationNext @click="reload(Math.min(pg.last_page || 1, (pg.current_page || 1) + 1))" />
            <PaginationLast @click="reload(pg.last_page || 1)" />
          </PaginationContent>
        </Pagination>
      </div>

      <!-- Empty State -->
      <Card v-if="listedProjects.length === 0" class="text-center py-16">
        <CardContent>
          <div class="max-w-md mx-auto">
            <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mx-auto mb-4">
              <BarChart3 class="h-8 w-8 text-muted-foreground" />
            </div>
            <h3 class="text-lg font-semibold mb-2">{{ pg.total === 0 ? 'No projects yet' : 'No projects found' }}</h3>
            <p class="text-muted-foreground mb-6">
              {{
                pg.total === 0
                  ? "You haven't created any projects yet. Start by creating your first audio annotation project."
                  : 'No projects match your current filters. Try adjusting your search criteria or clearing the filters.'
              }}
            </p>
            <div class="flex flex-col sm:flex-row gap-2 justify-center">
              <Link v-if="canCreateProject && pg.total === 0" :href="route('admin.projects.create')">
                <Button class="gap-2">
                  <Plus class="h-4 w-4" />
                  Create Your First Project
                </Button>
              </Link>
              <Button v-if="pg.total > 0" variant="outline" @click="clearFilters" class="gap-2">
                <Filter class="h-4 w-4" />
                Clear All Filters
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

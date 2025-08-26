<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableHead, TableHeader, TableRow, TableCell } from '@/components/ui/table'
import { Pagination, PaginationContent, PaginationItem, PaginationNext, PaginationPrevious } from '@/components/ui/pagination'
import { Users, ArrowLeft, UserPlus, Search, Filter } from 'lucide-vue-next'
import AddMemberDialog from '@/components/projects/AddMemberDialog.vue'
import EditMemberDialog from '@/components/projects/EditMemberDialog.vue'
import { ConfirmDialog } from '@/components/ui/confirm-dialog'

type PageLink = { url: string | null; label: string; active: boolean }

interface Member {
  id: number
  user: { id: number; name: string; email: string } | null
  role: 'annotator' | 'reviewer' | 'project_admin' | string
  is_active: boolean
  workload_limit: number | null
  assigned_at: string
}

const props = defineProps<{
  project: { id: number; name: string; status: string; owner: { id: number; name: string; email: string } | null }
  members: any
  filters: { q: string; role: string | null; is_active: boolean | null; perPage: number }
  can: { manageTeam: boolean }
}>()

/** ---------- normalize members shape ---------- */
const pg = computed(() => {
  const m = props.members as any

  // shape C: { data: [...], links: [], meta: {...} }
  if (Array.isArray(m?.data) && m?.meta) {
    const cp = m.meta.current_page ?? 1
    const lp = m.meta.last_page ?? 1
    const pp = m.meta.per_page ?? 10
    const tot = m.meta.total ?? m.data.length
    const from = m.meta.from ?? (tot ? (cp - 1) * pp + 1 : 0)
    const to = m.meta.to ?? Math.min(tot, cp * pp)
    return { data: m.data as Member[], current_page: cp, last_page: lp, per_page: pp, total: tot, from, to, links: m.links ?? [] as PageLink[] }
  }

  // shape B: wrapped paginator under .data
  if (m?.data && typeof m.data === 'object' && Array.isArray(m.data.data)) {
    const p = m.data
    return {
      data: p.data as Member[],
      current_page: p.current_page ?? 1,
      last_page: p.last_page ?? 1,
      per_page: p.per_page ?? 10,
      total: p.total ?? (p.data?.length ?? 0),
      from: p.from ?? 0,
      to: p.to ?? 0,
      links: p.links ?? [] as PageLink[],
    }
  }

  // shape A: raw paginator
  if (Array.isArray(m?.data) && typeof m?.current_page === 'number') {
    const tot = m.total ?? m.data.length
    const cp = m.current_page ?? 1
    const pp = m.per_page ?? 10
    const from = m.from ?? (tot ? (cp - 1) * pp + 1 : 0)
    const to = m.to ?? Math.min(tot, cp * pp)
    return {
      data: m.data as Member[],
      current_page: cp,
      last_page: m.last_page ?? 1,
      per_page: pp,
      total: tot,
      from,
      to,
      links: m.links ?? [] as PageLink[],
    }
  }

  // fallback
  return { data: [] as Member[], current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0, links: [] as PageLink[] }
})

/* ---------- Filters (map "all" -> undefined for the request) ---------- */
const q = ref(props.filters.q || '')

// use "all" sentinel to satisfy Reka-UI's non-empty <SelectItem value>
const role = ref<string>(props.filters.role ?? 'all')
const isActive = ref<string>(
  props.filters.is_active === null || props.filters.is_active === undefined
    ? 'all'
    : props.filters.is_active ? '1' : '0'
)
const perPage = ref(String(props.filters.perPage || 10))

const clearFilters = () => {
  q.value = ''
  role.value = 'all'
  isActive.value = 'all'
  perPage.value = '10'
  reload(1)
}

/* ---------- Dialogs ---------- */
const showAddDialog = ref(false)
const showEditDialog = ref(false)
const selectedMember = ref<Member | null>(null)
const showRemoveDialog = ref(false)
const removeId = ref<number | null>(null)

/* ---------- Derived ---------- */
const safeMembers = computed<Member[]>(() => pg.value.data)
const curPage = computed(() => pg.value.current_page)
const lastPage = computed(() => pg.value.last_page)
const itemsPerPage = computed(() => pg.value.per_page)
const totalItems = computed(() => pg.value.total)
const rangeFrom = computed(() => pg.value.from ?? 0)
const rangeTo = computed(() => pg.value.to ?? 0)

// pagination numbers (from links if present)
const numberedLinks = computed<number[]>(() => {
  const numeric = (pg.value.links || [])
    .map(l => l.label)
    .filter((lbl: any) => typeof lbl === 'string' && /^\d+$/.test(lbl))
    .map((lbl: string) => Number(lbl))
  return numeric.length ? numeric : Array.from({ length: lastPage.value }, (_, i) => i + 1)
})

/* ---------- Guards ---------- */
const isOwnerMember = (m: Member) => {
  const ownerId = props.project?.owner?.id ?? null
  return !!ownerId && m.user?.id === ownerId
}
const canRemove = (m: Member) => props.can.manageTeam && !isOwnerMember(m)

/* ---------- Fetching ---------- */
const reload = (page?: number) => {
  const params: Record<string, any> = {
    q: q.value || undefined,
    role: role.value !== 'all' ? role.value : undefined,
    is_active: isActive.value !== 'all' ? isActive.value : undefined,
    perPage: perPage.value || undefined,
    page: page || undefined,
  }
  router.get(route('admin.projects.members.index', props.project.id), params, {
    preserveState: true,
    preserveScroll: true,
  })
}

// Debounce filter changes
let t: number | undefined
watch([q, role, isActive, perPage], () => {
  if (t) window.clearTimeout(t)
  t = window.setTimeout(() => reload(1), 300)
})
</script>

<template>
  <Head :title="`Team • ${project.name}`" />

  <AppLayout :breadcrumbs="[
    { title: 'Projects', href: '/admin/projects' },
    { title: project.name, href: `/admin/projects/${project.id}` },
    { title: 'Team', href: route('admin.projects.members.index', project.id) },
  ]">
    <div class="flex-1 space-y-6 p-4 md:p-6 lg:p-8">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Link :href="route('admin.projects.show', project.id)">
            <Button variant="ghost" class="gap-2">
              <ArrowLeft class="h-4 w-4" />
              Back to Project
            </Button>
          </Link>
          <h1 class="text-2xl font-bold tracking-tight">Team Members</h1>
          <Badge variant="secondary">{{ totalItems }}</Badge>
        </div>

        <Button v-if="can.manageTeam" size="sm" class="gap-2" @click="showAddDialog = true">
          <UserPlus class="h-4 w-4" />
          Assign Member
        </Button>
      </div>

      <!-- Filters (Projects index style) -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Filter class="h-5 w-5" />
            Filters & Search
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <!-- Search -->
            <div class="relative w-full lg:max-w-xs">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input v-model="q" placeholder="Search name or email..." class="pl-10" />
            </div>

            <!-- Selects -->
            <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto lg:ml-auto">
              <Select v-model="role" class="flex-1 min-w-[160px]">
                <SelectTrigger><SelectValue placeholder="All Roles" /></SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="all">All Roles</SelectItem>
                    <SelectItem value="annotator">Annotator</SelectItem>
                    <SelectItem value="reviewer">Reviewer</SelectItem>
                    <SelectItem value="project_admin">Project Admin</SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>

              <Select v-model="isActive" class="flex-1 min-w-[150px]">
                <SelectTrigger><SelectValue placeholder="All Statuses" /></SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="all">All Statuses</SelectItem>
                    <SelectItem value="1">Active</SelectItem>
                    <SelectItem value="0">Inactive</SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>

              <Select v-model="perPage" class="flex-1 min-w-[120px]">
                <SelectTrigger><SelectValue placeholder="Per page" /></SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="10">10</SelectItem>
                    <SelectItem value="25">25</SelectItem>
                    <SelectItem value="50">50</SelectItem>
                    <SelectItem value="100">100</SelectItem>
                  </SelectGroup>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div class="mt-4 flex items-center justify-between">
            <p class="text-sm text-muted-foreground">
              Showing {{ rangeFrom }}–{{ rangeTo }} of {{ totalItems }} members
            </p>
            <Button
              variant="outline"
              size="sm"
              @click="clearFilters"
              v-if="q || role !== 'all' || isActive !== 'all' || perPage !== '10'"
            >
              Clear Filters
            </Button>
          </div>
        </CardContent>
      </Card>

      <!-- Table -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Users class="h-5 w-5" />
            Manage Team
          </CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
          <div class="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Role</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Workload</TableHead>
                  <TableHead>Assigned</TableHead>
                  <TableHead class="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>

              <TableBody>
                <TableRow v-if="!safeMembers.length">
                  <TableCell colspan="7" class="py-6 text-center text-sm text-muted-foreground">
                    No members found with current filters.
                  </TableCell>
                </TableRow>

                <TableRow v-for="(m, i) in safeMembers" :key="m?.id ?? `row-${i}`">
                  <TableCell class="font-medium">{{ m?.user?.name ?? '—' }}</TableCell>
                  <TableCell class="text-muted-foreground">{{ m?.user?.email ?? '—' }}</TableCell>
                  <TableCell class="capitalize">
                    <Badge variant="outline">{{ m?.role ?? '—' }}</Badge>
                  </TableCell>
                  <TableCell>
                    <Badge :variant="m?.is_active ? 'default' : 'secondary'">
                      {{ m?.is_active ? 'Active' : 'Inactive' }}
                    </Badge>
                  </TableCell>
                  <TableCell>{{ m?.workload_limit ?? '—' }}</TableCell>
                  <TableCell>{{ m?.assigned_at ?? '—' }}</TableCell>
                  <TableCell class="space-x-1 text-right">
                    <!-- Hide Edit for the project owner -->
                    <Button
                      v-if="!isOwnerMember(m) && can.manageTeam"
                      size="sm"
                      variant="ghost"
                      @click="selectedMember = m; showEditDialog = true"
                    >
                      Edit
                    </Button>

                    <Button
                      v-if="canRemove(m)"
                      size="sm"
                      variant="ghost"
                      class="text-destructive hover:text-destructive"
                      @click="removeId = m?.id ?? null; showRemoveDialog = true"
                    >
                      Remove
                    </Button>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <!-- Pagination -->
          <Pagination
            v-if="lastPage > 1"
            class="mt-4"
            :items-per-page="itemsPerPage"
            :total="totalItems"
            :page="curPage"
            @update:page="(p:number) => reload(p)"
          >
            <PaginationContent>
              <PaginationItem :value="Math.max(1, curPage - 1)">
                <PaginationPrevious
                  :href="curPage === 1 ? '#' : ''"
                  :disabled="curPage === 1"
                  @click.prevent="reload(curPage - 1)"
                />
              </PaginationItem>

              <PaginationItem
                v-for="n in numberedLinks"
                :key="`p-${n}`"
                :value="n"
                :is-active="n === curPage"
              >
                <button
                  type="button"
                  class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm transition-colors"
                  :class="n === curPage ? 'bg-primary text-primary-foreground' : 'bg-background hover:bg-muted'"
                  @click.prevent="reload(n)"
                  :aria-current="n === curPage ? 'page' : undefined"
                >
                  {{ n }}
                </button>
              </PaginationItem>

              <PaginationItem :value="Math.min(lastPage, curPage + 1)">
                <PaginationNext
                  :href="curPage === lastPage ? '#' : ''"
                  :disabled="curPage === lastPage"
                  @click.prevent="reload(curPage + 1)"
                />
              </PaginationItem>
            </PaginationContent>
          </Pagination>
        </CardContent>
      </Card>
    </div>

    <!-- Dialogs -->
    <AddMemberDialog
      :project-id="project.id"
      :open="showAddDialog"
      @update:open="showAddDialog = $event"
      @member-added="() => reload()"
    />

    <EditMemberDialog
      :project-id="project.id"
      :member="selectedMember && selectedMember.user ? selectedMember : null"
      :open="showEditDialog"
      @update:open="showEditDialog = $event"
      @member-updated="() => { selectedMember = null; reload(); }"
    />

    <ConfirmDialog
      :open="showRemoveDialog"
      @update:open="(v) => { showRemoveDialog = v; if (!v) removeId = null; }"
      title="Remove Team Member"
      description="Are you sure you want to remove this team member from the project?"
      confirm-text="Remove"
      confirm-variant="destructive"
      @confirm="() => {
        if (!removeId) return;
        router.delete(route('admin.projects.members.destroy', [project.id, removeId]), {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => { removeId = null; reload(); },
          onError: () => { removeId = null; }
        });
      }"
    />
  </AppLayout>
</template>

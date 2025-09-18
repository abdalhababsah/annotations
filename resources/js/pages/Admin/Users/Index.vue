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
import {
    Filter, Search, Trash, UserPlus, User, UserCog, Users, Shield,
    Mail, Power, CheckCircle, AlertCircle, Lock, Unlock, RefreshCw
} from 'lucide-vue-next'
import CreateUserDialog from '@/components/Users/CreateUserDialog.vue'
import UpdateUserDialog from '@/components/Users/UpdateUserDialog.vue'
import { Badge } from '@/components/ui/badge'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'

/* ========= Types ========= */
interface UserData {
    id: number
    first_name: string
    last_name: string
    name: string
    email: string
    role: string
    is_active: boolean
    email_verified_at: string | null
    created_at: string
    updated_at: string
}

interface PaginationMeta {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
}

interface Statistics {
    total_users: number
    active_users: number
    inactive_users: number
    admin_users: number
    project_owners: number
    regular_users: number
    verified_users: number
    unverified_users: number
    recent_users: number
}

interface Props {
    users: {
        data: UserData[]
        meta: PaginationMeta
        links: Array<{ url: string | null; label: string; active: boolean }>
    }
    statistics: Statistics
    filters: Record<string, any>
}

const props = defineProps<Props>()

/* ========= State ========= */
const showCreateDialog = ref(false)
const showUpdateDialog = ref(false)
const selectedUser = ref<UserData | null>(null)

/* ========= Filters ========= */
const q = ref(props.filters?.q ?? '')
const role = ref(props.filters?.role ?? 'all')
const status = ref(props.filters?.status ?? 'all')
const verified = ref(props.filters?.verified ?? 'all')
const sort = ref(props.filters?.sort ?? 'created_at')
const direction = ref(props.filters?.direction ?? 'desc')
const perPage = ref(String(props.users.meta?.per_page ?? 10))

/* ========= Data & meta ========= */
const pg = computed(() => props.users.meta)
const rows = computed(() => props.users.data)
const stats = computed(() => props.statistics)

/* ========= Methods ========= */
const openUpdateDialog = (user: UserData) => {
    selectedUser.value = user
    showUpdateDialog.value = true
}

const toggleUserActive = (user: UserData) => {
    if (user.id === 1) {
        alert('Cannot change status of the primary admin account.')
        return
    }

    router.post(route('admin.users.toggle-active', user.id), {}, {
        preserveScroll: true,
        preserveState: true
    })
}

const toggleUserVerified = (user: UserData) => {
    router.post(route('admin.users.toggle-verified', user.id), {}, {
        preserveScroll: true,
        preserveState: true
    })
}

const sendPasswordReset = (user: UserData) => {
    router.post(route('admin.users.send-password-reset', user.id), {}, {
        preserveScroll: true,
        preserveState: true
    })
}

/* ========= Reload (same pattern as Projects) ========= */
const reload = (page?: number) => {
    router.get(
        route('admin.users.index'),
        {
            q: (q.value || undefined),
            role: role.value !== 'all' ? role.value : undefined,
            status: status.value !== 'all' ? status.value : undefined,
            verified: verified.value !== 'all' ? verified.value : undefined,
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
watch([q, role, status, verified, sort, direction, perPage], () => {
    window.clearTimeout(timer)
    timer = window.setTimeout(() => reload(1), 350)
})

const clearFilters = () => {
    q.value = ''
    role.value = 'all'
    status.value = 'all'
    verified.value = 'all'
    sort.value = 'created_at'
    direction.value = 'desc'
    perPage.value = '10'
    reload(1)
}

/* ========= Helpers ========= */
const formatDate = (dateString: string | null) => {
    if (!dateString) return '—'
    return new Date(dateString).toLocaleString()
}

const getRoleBadgeVariant = (user: UserData) => {
    if (user.role === 'system_admin') return 'default'
    if (user.role === 'project_owner') return 'outline'
    return 'secondary'
}

const getRoleLabel = (user: UserData) => {
    if (user.role === 'system_admin') return 'System Admin'
    if (user.role === 'project_owner') return 'Project Owner'
    return 'User'
}
</script>

<template>

    <Head title="User Management" />

    <AppLayout :breadcrumbs="[
        { title: 'Dashboard', href: route('admin.dashboard') },
        { title: 'User Management', href: route('admin.users.index') }
    ]">
        <div class="flex flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">User Management</h1>
                    <p class="text-muted-foreground">Manage users, permissions, and access</p>
                </div>
                <Button size="lg" class="gap-2" @click="showCreateDialog = true">
                    <UserPlus class="h-4 w-4" />
                    Create User
                </Button>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-2">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <div class="p-2 bg-primary/10 rounded-lg">
                                <Users class="h-4 w-4 text-primary" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ stats.total_users }}</p>
                                <p class="text-xs text-muted-foreground">Total Users</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <div class="p-2 bg-green-500/10 rounded-lg">
                                <Power class="h-4 w-4 text-green-600" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ stats.active_users }}</p>
                                <p class="text-xs text-muted-foreground">Active Users</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <div class="p-2 bg-blue-500/10 rounded-lg">
                                <Shield class="h-4 w-4 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ stats.admin_users }}</p>
                                <p class="text-xs text-muted-foreground">Admins</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center gap-2">
                            <div class="p-2 bg-amber-500/10 rounded-lg">
                                <CheckCircle class="h-4 w-4 text-amber-600" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ stats.verified_users }}</p>
                                <p class="text-xs text-muted-foreground">Verified</p>
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
                            <Input v-model="q" placeholder="Search by name, email, or ID..." class="pl-10"
                                @keydown.enter.prevent="reload(1)" />
                        </div>

                        <!-- Right controls -->
                        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto lg:ml-auto">
                            <Select v-model="role" class="flex-1 min-w-[150px]">
                                <SelectTrigger>
                                    <SelectValue placeholder="All Roles" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Roles</SelectItem>
                                    <SelectItem value="system_admin">System Admin</SelectItem>
                                    <SelectItem value="project_owner">Project Owner</SelectItem>
                                    <SelectItem value="user">Regular User</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select v-model="status" class="flex-1 min-w-[150px]">
                                <SelectTrigger>
                                    <SelectValue placeholder="All Statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Statuses</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select v-model="verified" class="flex-1 min-w-[150px]">
                                <SelectTrigger>
                                    <SelectValue placeholder="Verification" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Verification</SelectItem>
                                    <SelectItem value="verified">Verified</SelectItem>
                                    <SelectItem value="unverified">Unverified</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <Select v-model="sort" class="flex-1 min-w-[150px]">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sort by" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="created_at">Created Date</SelectItem>
                                    <SelectItem value="first_name">First Name</SelectItem>
                                    <SelectItem value="last_name">Last Name</SelectItem>
                                    <SelectItem value="email">Email</SelectItem>
                                    <SelectItem value="id">ID</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select v-model="direction" class="flex-1 min-w-[110px]">
                                <SelectTrigger>
                                    <SelectValue placeholder="Order" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="desc">Descending</SelectItem>
                                    <SelectItem value="asc">Ascending</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select v-model="perPage" class="flex-1 min-w-[110px]">
                                <SelectTrigger>
                                    <SelectValue placeholder="Per page" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="n in [10, 20, 50, 100]" :key="n" :value="String(n)">{{ n }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Button variant="outline" size="sm" @click="clearFilters"
                                v-if="q || role !== 'all' || status !== 'all' || verified !== 'all' || sort !== 'created_at' || direction !== 'desc' || perPage !== '10'">
                                Clear Filters
                            </Button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            <template v-if="pg.total > 0">
                                Showing <strong>{{ pg.from }}</strong>–<strong>{{ pg.to }}</strong> of <strong>{{
                                    pg.total }}</strong> users
                            </template>
                            <template v-else>No users</template>
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- User Table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>ID</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Verified</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="user in rows" :key="user.id">
                                <TableCell>{{ user.id }}</TableCell>
                                <TableCell class="font-medium">
                                    {{ user.first_name }} {{ user.last_name }}
                                </TableCell>
                                <TableCell>{{ user.email }}</TableCell>
                                <TableCell>
                                    <Badge :variant="getRoleBadgeVariant(user)">
                                        {{ getRoleLabel(user) }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="user.is_active ? 'outline' : 'destructive'"
                                        :class="user.is_active ? 'border-green-500 text-green-700' : ''">
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center">
                                        <Badge :variant="user.email_verified_at ? 'outline' : 'secondary'"
                                            :class="user.email_verified_at ? 'border-green-500 text-green-700' : ''">
                                            <CheckCircle v-if="user.email_verified_at"
                                                class="h-3 w-3 mr-1 text-green-600" />
                                            <AlertCircle v-else class="h-3 w-3 mr-1" />
                                            {{ user.email_verified_at ? 'Verified' : 'Unverified' }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell>{{ formatDate(user.created_at) }}</TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button size="icon" variant="outline"
                                                        @click="openUpdateDialog(user)">
                                                        <UserCog class="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Edit User</TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>

                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button size="icon"
                                                        :variant="user.is_active ? 'outline' : 'default'"
                                                        @click="toggleUserActive(user)" :disabled="user.id === 1">
                                                        <Power v-if="user.is_active" class="h-4 w-4 text-red-500" />
                                                        <Power v-else class="h-4 w-4 text-green-500" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{{ user.is_active ? 'Deactivate' : 'Activate' }}
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>

                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button size="icon"
                                                        :variant="user.email_verified_at ? 'outline' : 'default'"
                                                        @click="toggleUserVerified(user)">
                                                        <CheckCircle v-if="user.email_verified_at"
                                                            class="h-4 w-4 text-green-500" />
                                                        <AlertCircle v-else class="h-4 w-4 text-yellow-500" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{{ user.email_verified_at ? 'Remove Verification' :
                                                    'Verify Email'
                                                    }}</TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>

                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button size="icon" variant="outline"
                                                        @click="sendPasswordReset(user)">
                                                        <RefreshCw class="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Send Password Reset</TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                </TableCell>
                            </TableRow>

                            <TableRow v-if="rows.length === 0">
                                <TableCell colspan="8" class="text-center py-10 text-muted-foreground">
                                    No users found.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>

                <!-- Footer: Pagination -->
                <CardFooter class="flex items-center justify-between">
                    <div class="text-sm text-muted-foreground">
                        <template v-if="pg.total > 0">
                            Showing <b>{{ pg.from }}</b>–<b>{{ pg.to }}</b> of <b>{{ pg.total }}</b>
                        </template>
                        <template v-else>No items</template>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1">
                            <Button size="sm" variant="outline" :disabled="pg.current_page <= 1"
                                @click="reload(1)">First</Button>
                            <Button size="sm" variant="outline" :disabled="pg.current_page <= 1"
                                @click="reload(pg.current_page - 1)">Prev</Button>
                            <Button size="sm" variant="outline" :disabled="pg.current_page >= pg.last_page"
                                @click="reload(pg.current_page + 1)">Next</Button>
                            <Button size="sm" variant="outline" :disabled="pg.current_page >= pg.last_page"
                                @click="reload(pg.last_page)">Last</Button>
                        </div>
                    </div>
                </CardFooter>
            </Card>

            <!-- Empty State -->
            <Card v-if="rows.length === 0" class="text-center py-16">
                <CardContent>
                    <div class="max-w-md mx-auto">
                        <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mx-auto mb-4">
                            <Users class="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h3 class="text-lg font-semibold mb-2">{{ pg.total === 0 ? 'No users yet' : 'No users found' }}
                        </h3>
                        <p class="text-muted-foreground mb-6">
                            {{ pg.total === 0 ? 'There are no users in the system yet. Start by creating your first user.' : 'No users match your current filters. Try adjusting your search criteria or clearing the filters.' }}
                        </p>
                        <div class="flex flex-col sm:flex-row gap-2 justify-center">
                            <Button v-if="pg.total === 0" @click="showCreateDialog = true" class="gap-2">
                                <UserPlus class="h-4 w-4" />
                                Create Your First User
                            </Button>
                            <Button v-if="pg.total > 0" variant="outline" @click="clearFilters" class="gap-2">
                                <Filter class="h-4 w-4" />
                                Clear All Filters
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Dialogs -->
        <CreateUserDialog :open="showCreateDialog" @update:open="showCreateDialog = $event" />

        <UpdateUserDialog :open="showUpdateDialog" @update:open="showUpdateDialog = $event" :user="selectedUser" />
    </AppLayout>
</template>
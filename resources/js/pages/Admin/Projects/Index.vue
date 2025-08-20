<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Plus, Search, Filter, Calendar, Users, BarChart3 } from 'lucide-vue-next';

interface Project {
  id: number;
  name: string;
  description: string;
  status: 'draft' | 'active' | 'paused' | 'completed' | 'archived';
  project_type: 'audio' | 'image';
  ownership_type: 'self_created' | 'admin_assigned';
  completion_percentage: number;
  team_size: number;
  owner: {
    id: number;
    name: string;
    email: string;
  };
  deadline: string | null;
  created_at: string;
  updated_at: string;
}

interface Props {
  projects: Project[];
  userRole: 'system_admin' | 'project_owner' | 'user';
  canCreateProject: boolean;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Projects', href: '/admin/projects' },
];

// Filters and search
const searchQuery = ref('');
const statusFilter = ref('all');
const typeFilter = ref('all');

const filteredProjects = computed(() => {
  return props.projects.filter(project => {
    const matchesSearch = project.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                         project.description?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                         project.owner.name.toLowerCase().includes(searchQuery.value.toLowerCase());
    
    const matchesStatus = statusFilter.value === 'all' || project.status === statusFilter.value;
    const matchesType = typeFilter.value === 'all' || project.project_type === typeFilter.value;
    
    return matchesSearch && matchesStatus && matchesType;
  });
});

const getStatusColor = (status: string) => {
  const colors = {
    draft: 'bg-gray-100 text-gray-800',
    active: 'bg-green-100 text-green-800',
    paused: 'bg-yellow-100 text-yellow-800',
    completed: 'bg-blue-100 text-blue-800',
    archived: 'bg-purple-100 text-purple-800',
  };
  return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
};

</script>

<template>
  <Head title="Projects" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 overflow-x-auto">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Projects</h1>
          <p class="text-muted-foreground">
            Manage your annotation projects
          </p>
        </div>
        <Link
          v-if="canCreateProject"
          :href="route('admin.projects.create')"
          class="inline-flex items-center"
        >
          <Button>
            <Plus class="mr-2 h-4 w-4" />
            Create Project
          </Button>
        </Link>
      </div>

      <!-- Filters -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Filter class="h-5 w-5" />
            Filters
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="relative">
              <Search class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
              <Input
                v-model="searchQuery"
                placeholder="Search projects..."
                class="pl-10"
              />
            </div>
            <Select v-model="statusFilter">
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
            <Select v-model="typeFilter">
              <SelectTrigger>
                <SelectValue placeholder="All Types" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Types</SelectItem>
                <SelectItem value="audio">Audio</SelectItem>
                <SelectItem value="image">Image</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      <!-- Projects Grid/Table -->
      <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 md:hidden">
        <!-- Mobile Card View -->
        <Card v-for="project in filteredProjects" :key="project.id" class="hover:shadow-md transition-shadow">
          <CardHeader>
            <div class="flex items-start justify-between">
              <div class="flex items-center gap-2">
                <CardTitle class="text-lg">{{ project.name }}</CardTitle>
              </div>    
              <Badge :class="getStatusColor(project.status)">
                {{ project.status }}
              </Badge>
            </div>
            <p class="text-sm text-muted-foreground line-clamp-2">
              {{ project.description || 'No description provided' }}
            </p>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <div>
                <div class="flex justify-between text-sm mb-1">
                  <span>Progress</span>
                  <span>{{ project.completion_percentage }}%</span>
                </div>
                <Progress :value="project.completion_percentage" class="h-2" />
              </div>
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="flex items-center gap-1">
                  <Users class="h-4 w-4" />
                  <span>{{ project.team_size }} members</span>
                </div>
                <div class="flex items-center gap-1" v-if="project.deadline">
                  <Calendar class="h-4 w-4" />
                  <span>{{ new Date(project.deadline).toLocaleDateString() }}</span>
                </div>
              </div>
              <div class="text-xs text-muted-foreground">
                Owner: {{ project.owner.name }}
              </div>
            </div>
          </CardContent>
          <CardFooter>
            <Link :href="route('admin.projects.show', project.id)" class="w-full">
              <Button variant="outline" class="w-full">
                View Project
              </Button>
            </Link>
          </CardFooter>
        </Card>
      </div>

      <!-- Desktop Table View -->
      <Card class="hidden md:block">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Project</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Progress</TableHead>
              <TableHead>Team</TableHead>
              <TableHead>Owner</TableHead>
              <TableHead>Deadline</TableHead>
              <TableHead>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="project in filteredProjects" :key="project.id">
              <TableCell>
                <div>
                  <div class="font-medium">{{ project.name }}</div>
                  <div class="text-sm text-muted-foreground line-clamp-1">
                    {{ project.description || 'No description' }}
                  </div>
                </div>
              </TableCell>
              <TableCell>
                <div class="flex items-center gap-2">
                  <span class="capitalize">{{ project.project_type }}</span>
                </div>
              </TableCell>
              <TableCell>
                <Badge :class="getStatusColor(project.status)">
                  {{ project.status }}
                </Badge>
              </TableCell>
              <TableCell>
                <div class="w-full max-w-[100px]">
                  <div class="flex justify-between text-sm mb-1">
                    <span>{{ project.completion_percentage }}%</span>
                  </div>
                  <Progress :value="project.completion_percentage" class="h-2" />
                </div>
              </TableCell>
              <TableCell>
                <div class="flex items-center gap-1">
                  <Users class="h-4 w-4" />
                  <span>{{ project.team_size }}</span>
                </div>
              </TableCell>
              <TableCell>
                <div class="text-sm">
                  <div>{{ project.owner.name }}</div>
                  <div class="text-muted-foreground text-xs">{{ project.owner.email }}</div>
                </div>
              </TableCell>
              <TableCell>
                <span v-if="project.deadline" class="text-sm">
                  {{ new Date(project.deadline).toLocaleDateString() }}
                </span>
                <span v-else class="text-muted-foreground text-sm">No deadline</span>
              </TableCell>
              <TableCell>
                <Link :href="route('admin.projects.show', project.id)">
                  <Button variant="outline" size="sm">
                    View
                  </Button>
                </Link>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </Card>

      <!-- Empty State -->
      <Card v-if="filteredProjects.length === 0" class="text-center py-12">
        <CardContent>
          <BarChart3 class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
          <h3 class="text-lg font-medium mb-2">No projects found</h3>
          <p class="text-muted-foreground mb-4">
            {{ projects.length === 0 
              ? "You don't have any projects yet. Create your first project to get started."
              : "No projects match your current filters. Try adjusting your search criteria."
            }}
          </p>
          <Link v-if="canCreateProject && projects.length === 0" :href="route('admin.projects.create')">
            <Button>
              <Plus class="mr-2 h-4 w-4" />
              Create Your First Project
            </Button>
          </Link>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>


<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
  ArrowLeft, 
  Save,
  Clock, 
  Users, 
  FileAudio, 
  Settings,
  AlertCircle,
  Calendar,
  User,
  Shield
} from 'lucide-vue-next';

interface ProjectOwner {
  id: number;
  name: string;
  email: string;
}

interface Project {
  id: number;
  name: string;
  description: string;
  status: 'draft' | 'active' | 'paused' | 'completed' | 'archived';
  task_time_minutes: number;
  review_time_minutes: number;
  annotation_guidelines: string;
  deadline: string | null;
  owner_id: number;
}

interface Props {
  project: Project;
  projectOwners: ProjectOwner[];
  userRole: 'system_admin' | 'project_owner' | 'user';
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Projects', href: '/admin/projects' },
  { title: props.project.name, href: `/admin/projects/${props.project.id}` },
  { title: 'Edit', href: `/admin/projects/${props.project.id}/edit` },
];

// Form for updating project
const form = useForm({
  name: props.project.name,
  description: props.project.description || '',
  status: props.project.status,
  annotation_guidelines: props.project.annotation_guidelines || '',
  deadline: props.project.deadline || '',
  task_time_minutes: props.project.task_time_minutes,
  review_time_minutes: props.project.review_time_minutes,
  owner_id: props.project.owner_id,
});

// Validation
const isFormValid = computed(() => {
  return form.name.trim().length > 0 && 
         form.task_time_minutes >= 5 && 
         form.review_time_minutes >= 5;
});

// Status options based on current status
const getAvailableStatuses = computed(() => {
  const baseStatuses = [
    { value: 'draft', label: 'Draft', description: 'Project is being configured' },
    { value: 'active', label: 'Active', description: 'Project is accepting annotations' },
    { value: 'paused', label: 'Paused', description: 'Temporarily stopped' },
    { value: 'completed', label: 'Completed', description: 'All work finished' },
    { value: 'archived', label: 'Archived', description: 'Project archived' }
  ];

  // Return all statuses for admin/owner
  return baseStatuses;
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

// Submit form
const updateProject = () => {
  if (isFormValid.value) {
    form.patch(route('admin.projects.update', props.project.id));
  }
};

// Format time helper
const formatTime = (minutes: number) => {
  if (minutes < 60) return `${minutes} minutes`;
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  return mins > 0 ? `${hours}h ${mins}m` : `${hours} hour${hours > 1 ? 's' : ''}`;
};

const getStatusDescription = (status: string) => {
  const descriptions = {
    draft: 'Project is still being configured and is not yet available for annotation work.',
    active: 'Project is live and team members can work on assigned tasks.',
    paused: 'Project work has been temporarily stopped. Tasks cannot be completed.',
    completed: 'All project work has been finished and approved.',
    archived: 'Project has been archived and is read-only.',
  };
  return descriptions[status as keyof typeof descriptions] || '';
};
</script>

<template>
  <Head title="Edit Project" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 overflow-x-auto">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Edit Project</h1>
          <p class="text-muted-foreground">
            Update project settings and configuration
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Link :href="`/admin/projects/${project.id}`">
            <Button variant="outline" size="sm">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Back to Project
            </Button>
          </Link>
          <Badge :class="getStatusColor(project.status)" class="uppercase">
            {{ project.status }}
          </Badge>
        </div>
      </div>

      <!-- Form Content -->
      <form @submit.prevent="updateProject" class="space-y-6">
        <!-- Basic Information -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Settings class="h-5 w-5" />
              Basic Information
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-6">
            <!-- Project Name & Owner -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-2">
                <Label for="name">Project Name *</Label>
                <Input
                  id="name"
                  v-model="form.name"
                  placeholder="Enter project name"
                  class="w-full"
                  :class="{ 'border-red-500': form.errors.name }"
                  required
                />
                <p v-if="form.errors.name" class="text-sm text-red-600">
                  {{ form.errors.name }}
                </p>
              </div>

              <!-- Owner Assignment (Admin Only) -->
              <div v-if="userRole === 'system_admin' && projectOwners.length > 0" class="space-y-2">
                <Label for="owner_id">Project Owner</Label>
                <Select v-model="form.owner_id">
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="Select project owner" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem 
                      v-for="owner in projectOwners" 
                      :key="owner.id" 
                      :value="owner.id"
                    >
                      <div class="flex items-center gap-2">
                        <User class="h-4 w-4" />
                        <div>
                          <div class="font-medium">{{ owner.name }}</div>
                          <div class="text-xs text-muted-foreground">{{ owner.email }}</div>
                        </div>
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-sm text-muted-foreground">
                  Change project ownership (admin only)
                </p>
              </div>
            </div>

            <!-- Description -->
            <div class="space-y-2">
              <Label for="description">Description</Label>
              <Textarea
                id="description"
                v-model="form.description"
                placeholder="Describe your audio annotation project..."
                rows="3"
                class="w-full"
              />
              <p class="text-sm text-muted-foreground">
                Brief overview of what this project is about
              </p>
            </div>

            <!-- Project Status -->
            <div class="space-y-2">
              <Label for="status">Project Status *</Label>
              <Select v-model="form.status">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Select status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem 
                    v-for="statusOption in getAvailableStatuses" 
                    :key="statusOption.value" 
                    :value="statusOption.value"
                  >
                    <div class="flex items-center gap-2">
                      <Badge :class="getStatusColor(statusOption.value)" class="text-xs">
                        {{ statusOption.label }}
                      </Badge>
                      <span class="text-sm">{{ statusOption.description }}</span>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-sm text-muted-foreground">
                {{ getStatusDescription(form.status) }}
              </p>
              <p v-if="form.errors.status" class="text-sm text-red-600">
                {{ form.errors.status }}
              </p>
            </div>

            <!-- Deadline -->
            <div class="space-y-2">
              <Label for="deadline">Project Deadline (Optional)</Label>
              <div class="flex items-center gap-2">
                <Calendar class="h-4 w-4 text-muted-foreground" />
                <Input
                  id="deadline"
                  v-model="form.deadline"
                  type="date"
                  class="w-full max-w-xs"
                  :min="new Date().toISOString().split('T')[0]"
                />
              </div>
              <p class="text-sm text-muted-foreground">
                Set an overall deadline for project completion
              </p>
            </div>
          </CardContent>
        </Card>

        <!-- Time Configuration -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Clock class="h-5 w-5" />
              Time Limits
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-6">
            <Alert>
              <AlertCircle class="h-4 w-4" />
              <AlertDescription>
                These time limits control how long users have to complete annotation tasks and reviews. 
                Users will be automatically logged out when time expires.
              </AlertDescription>
            </Alert>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-2">
                <Label for="task_time_minutes" class="flex items-center gap-2">
                  <FileAudio class="h-4 w-4" />
                  Task Time Limit (minutes) *
                </Label>
                <Input
                  id="task_time_minutes"
                  v-model.number="form.task_time_minutes"
                  type="number"
                  min="5"
                  max="180"
                  class="w-full"
                  :class="{ 'border-red-500': form.errors.task_time_minutes }"
                />
                <p class="text-sm text-muted-foreground">
                  ⏱️ {{ formatTime(form.task_time_minutes) }} per annotation task
                </p>
                <p v-if="form.errors.task_time_minutes" class="text-sm text-red-600">
                  {{ form.errors.task_time_minutes }}
                </p>
              </div>

              <div class="space-y-2">
                <Label for="review_time_minutes" class="flex items-center gap-2">
                  <Users class="h-4 w-4" />
                  Review Time Limit (minutes) *
                </Label>
                <Input
                  id="review_time_minutes"
                  v-model.number="form.review_time_minutes"
                  type="number"
                  min="5"
                  max="60"
                  class="w-full"
                  :class="{ 'border-red-500': form.errors.review_time_minutes }"
                />
                <p class="text-sm text-muted-foreground">
                  ⏱️ {{ formatTime(form.review_time_minutes) }} per review
                </p>
                <p v-if="form.errors.review_time_minutes" class="text-sm text-red-600">
                  {{ form.errors.review_time_minutes }}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Annotation Guidelines -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <FileAudio class="h-5 w-5" />
              Annotation Guidelines
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="space-y-2">
              <Label for="annotation_guidelines">Guidelines</Label>
              <Textarea
                id="annotation_guidelines"
                v-model="form.annotation_guidelines"
                placeholder="Provide detailed instructions for annotators..."
                rows="6"
                class="w-full"
              />
              <p class="text-sm text-muted-foreground">
                Detailed instructions that will help annotators understand what to evaluate in the audio files.
                These guidelines will be shown to team members during the annotation process.
              </p>
            </div>
          </CardContent>
        </Card>

        <!-- Status Warnings -->
        <Alert v-if="form.status === 'archived'" variant="destructive">
          <AlertCircle class="h-4 w-4" />
          <AlertDescription>
            <strong>Warning:</strong> Archiving this project will make it read-only. 
            Team members will not be able to work on tasks or make any changes.
          </AlertDescription>
        </Alert>

        <Alert v-if="form.status === 'paused'">
          <AlertCircle class="h-4 w-4" />
          <AlertDescription>
            <strong>Note:</strong> Pausing this project will prevent team members from 
            completing tasks, but they can still view existing work.
          </AlertDescription>
        </Alert>

        <Alert v-if="form.status === 'completed'" class="border-green-200 bg-green-50">
          <AlertCircle class="h-4 w-4 text-green-600" />
          <AlertDescription class="text-green-800">
            <strong>Completing Project:</strong> This will mark the project as finished. 
            You can still make changes but no new annotation work can be started.
          </AlertDescription>
        </Alert>

        <!-- Validation Summary -->
        <Alert v-if="!isFormValid" variant="destructive">
          <AlertCircle class="h-4 w-4" />
          <AlertDescription>
            Please fix the following issues:
            <ul class="list-disc list-inside mt-2">
              <li v-if="!form.name.trim()">Project name is required</li>
              <li v-if="form.task_time_minutes < 5">Task time must be at least 5 minutes</li>
              <li v-if="form.review_time_minutes < 5">Review time must be at least 5 minutes</li>
            </ul>
          </AlertDescription>
        </Alert>

        <!-- Form Errors -->
        <div v-if="Object.keys(form.errors).length > 0" class="p-4 bg-red-50 border border-red-200 rounded-md">
          <p class="text-sm text-red-600 font-medium mb-2">Please fix the following errors:</p>
          <ul class="text-sm text-red-600 space-y-1">
            <li v-for="(error, field) in form.errors" :key="field">
              • {{ error }}
            </li>
          </ul>
        </div>

        <!-- Submit Actions -->
        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center justify-between">
              <div class="text-sm text-muted-foreground">
                <Shield class="inline h-4 w-4 mr-1" />
                Changes will be saved immediately and team members will be notified if status changes.
              </div>
              
              <div class="flex items-center gap-3">
                <Link :href="`/admin/projects/${project.id}`">
                  <Button variant="outline" type="button">
                    Cancel
                  </Button>
                </Link>
                
                <Button 
                  type="submit" 
                  :disabled="!isFormValid || form.processing"
                  class="flex items-center gap-2"
                >
                  <Save class="h-4 w-4" />
                  <span>{{ form.processing ? 'Saving...' : 'Save Changes' }}</span>
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </form>
    </div>
  </AppLayout>
</template>
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
  ArrowLeft, 
  ArrowRight, 
  Clock, 
  Users, 
  FileAudio, 
  Settings,
  AlertCircle,
  CheckCircle2
} from 'lucide-vue-next';

interface ProjectOwner {
  id: number;
  name: string;
  email: string;
}

interface Props {
  projectOwners: ProjectOwner[];
  userRole: 'system_admin' | 'project_owner' | 'user';
  currentStep: number;
  totalSteps: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Projects', href: '/admin/projects' },
  { title: 'Create Project', href: '/admin/projects/create' },
];

// Form for step 1 - Basic project information
const form = useForm({
  name: '',
  description: '',
  annotation_guidelines: '',
  deadline: '',
  task_time_minutes: 30,
  review_time_minutes: 15,
  owner_id: null as number | null,
});

// Validation
const isFormValid = computed(() => {
  return form.name.trim().length > 0 && 
         form.task_time_minutes >= 5 && 
         form.review_time_minutes >= 5;
});

// Submit step 1
const submitStepOne = () => {
  if (isFormValid.value) {
    form.post(route('admin.projects.store-step-one'));
  }
};

// Format time helper
const formatTime = (minutes: number) => {
  if (minutes < 60) return `${minutes} minutes`;
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  return mins > 0 ? `${hours}h ${mins}m` : `${hours} hour${hours > 1 ? 's' : ''}`;
};
</script>

<template>
  <Head title="Create Project" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 overflow-x-auto">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Create Audio Annotation Project</h1>
          <p class="text-muted-foreground">
            Step {{ currentStep }} of {{ totalSteps }}: Set up basic project information
          </p>
        </div>
        <Link :href="route('admin.projects.index')">
          <Button variant="outline" size="sm">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back to Projects
          </Button>
        </Link>
      </div>

      <!-- Progress Indicator -->
      <div class="flex items-center justify-between mb-8">
        <div class="flex items-center space-x-4 w-full">
          <!-- Step 1 - Active -->
          <div class="flex items-center">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-medium">
              <span v-if="!isFormValid">1</span>
              <CheckCircle2 v-else class="w-4 h-4" />
            </div>
            <span class="ml-2 text-sm font-medium text-blue-600">Basic Info</span>
          </div>
          
          <Separator class="flex-1" />
          
          <!-- Step 2 - Inactive -->
          <div class="flex items-center">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-600 text-sm font-medium">
              2
            </div>
            <span class="ml-2 text-sm font-medium text-gray-600">Dimensions</span>
          </div>
          
          <Separator class="flex-1" />
          
          <!-- Step 3 - Inactive -->
          <div class="flex items-center">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-600 text-sm font-medium">
              3
            </div>
            <span class="ml-2 text-sm font-medium text-gray-600">Review</span>
          </div>
        </div>
      </div>

      <!-- Form Content -->
      <form @submit.prevent="submitStepOne">
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Settings class="h-5 w-5" />
              Project Details
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
                <Label for="owner_id">Assign to Project Owner</Label>
                <Select v-model="form.owner_id">
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="Assign to yourself or select owner" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem :value="null">Assign to myself</SelectItem>
                    <SelectItem 
                      v-for="owner in projectOwners" 
                      :key="owner.id" 
                      :value="owner.id"
                    >
                      {{ owner.name }} ({{ owner.email }})
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-sm text-muted-foreground">
                  Leave empty to assign to yourself, or select a project owner
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

            <!-- Time Limits -->
            <div class="space-y-4">
              <div class="flex items-center gap-2">
                <Clock class="h-5 w-5 text-blue-500" />
                <h3 class="text-lg font-medium">Time Limits</h3>
              </div>
              
              <Alert>
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                  Set time limits for annotation tasks and reviews. Users will be automatically logged out when time expires.
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
            </div>

            <!-- Deadline -->
            <div class="space-y-2">
              <Label for="deadline">Project Deadline (Optional)</Label>
              <Input
                id="deadline"
                v-model="form.deadline"
                type="date"
                class="w-full"
                :min="new Date().toISOString().split('T')[0]"
              />
              <p class="text-sm text-muted-foreground">
                Set an overall deadline for project completion
              </p>
            </div>

            <!-- Annotation Guidelines -->
            <div class="space-y-2">
              <Label for="annotation_guidelines">Annotation Guidelines</Label>
              <Textarea
                id="annotation_guidelines"
                v-model="form.annotation_guidelines"
                placeholder="Provide detailed instructions for annotators..."
                rows="4"
                class="w-full"
              />
              <p class="text-sm text-muted-foreground">
                Detailed instructions that will help annotators understand what to evaluate in the audio files
              </p>
            </div>

            <!-- Validation Summary -->
            <Alert v-if="!isFormValid" variant="destructive">
              <AlertCircle class="h-4 w-4" />
              <AlertDescription>
                Please fill in all required fields: project name, task time limit (5-180 min), and review time limit (5-60 min).
              </AlertDescription>
            </Alert>

            <!-- Error Messages -->
            <div v-if="Object.keys(form.errors).length > 0" class="p-4 bg-red-50 border border-red-200 rounded-md">
              <p class="text-sm text-red-600 font-medium mb-2">Please fix the following errors:</p>
              <ul class="text-sm text-red-600 space-y-1">
                <li v-for="(error, field) in form.errors" :key="field">
                  • {{ error }}
                </li>
              </ul>
            </div>

            <!-- Navigation -->
            <div class="flex items-center justify-between pt-6 border-t">
              <Link :href="route('admin.projects.index')">
                <Button variant="outline" type="button">
                  Cancel
                </Button>
              </Link>
              
              <Button 
                type="submit" 
                :disabled="!isFormValid || form.processing"
                class="flex items-center gap-2"
              >
                <span>{{ form.processing ? 'Saving...' : 'Next: Configure Dimensions' }}</span>
                <ArrowRight class="h-4 w-4" />
              </Button>
            </div>
          </CardContent>
        </Card>
      </form>
    </div>
  </AppLayout>
</template>

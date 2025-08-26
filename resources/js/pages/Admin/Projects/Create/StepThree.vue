<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
  ArrowLeft, 
  ArrowRight, 
  CheckCircle2, 
  Clock, 
  Users, 
  FileAudio, 
  Settings,
  AlertCircle,
  Play,
  Calendar,
  User,
  Hash
} from 'lucide-vue-next';

interface DimensionValue {
  id: number;
  value: string;
  label: string;
}

interface AnnotationDimension {
  id: number;
  name: string;
  description: string;
  dimension_type: 'categorical' | 'numeric_scale';
  scale_min?: number;
  scale_max?: number;
  is_required: boolean;
  values: DimensionValue[];
}

interface Project {
  id: number;
  name: string;
  description: string;
  status: string;
  task_time_minutes: number;
  review_time_minutes: number;
  annotation_guidelines: string;
  deadline: string | null;
  owner: {
    id: number;
    name: string;
    email: string;
  };
  created_at: string;
}

interface Statistics {
  total_tasks: number;
  completed_tasks: number;
  pending_tasks: number;
  approved_tasks: number;
  total_audio_files: number;
  completion_percentage: number;
  team_size: number;
  annotators_count: number;
  reviewers_count: number;
}

interface Props {
  project: Project;
  dimensions: AnnotationDimension[];
  statistics: Statistics;
  currentStep: number;
  totalSteps: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Projects', href: '/admin/projects' },
  { title: 'Create Project', href: '/admin/projects/create' },
  { title: 'Review & Finalize', href: '#' },
];

// Form for finalizing the project
const form = useForm({});

// Validation - project is ready if it has dimensions
const isProjectReady = computed(() => {
  return props.dimensions.length > 0;
});

// Format time helper
const formatTime = (minutes: number) => {
  if (minutes < 60) return `${minutes} minutes`;
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  return mins > 0 ? `${hours}h ${mins}m` : `${hours} hour${hours > 1 ? 's' : ''}`;
};

// Finalize project
const finalizeProject = () => {
  if (isProjectReady.value) {
    form.post(route('admin.projects.finalize', props.project.id));
  }
};

// Go back to step 2
const goToPreviousStep = () => {
  window.location.href = route('admin.projects.create.step-two', props.project.id);
};
</script>

<template>
  <Head title="Review & Finalize Project" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 overflow-x-auto">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Review & Finalize Project</h1>
          <p class="text-muted-foreground">
            Step {{ currentStep }} of {{ totalSteps }}: Review your project configuration and activate
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
          <!-- Step 1 - Completed -->
          <div class="flex items-center">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium">
              <CheckCircle2 class="w-4 h-4" />
            </div>
            <span class="ml-2 text-sm font-medium text-green-600">Basic Info</span>
          </div>
          
          <Separator class="flex-1" />
          
          <!-- Step 2 - Completed -->
          <div class="flex items-center">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium">
              <CheckCircle2 class="w-4 h-4" />
            </div>
            <span class="ml-2 text-sm font-medium text-green-600">Dimensions</span>
          </div>
          
          <Separator class="flex-1" />
          
          <!-- Step 3 - Active -->
          <div class="flex items-center">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-medium">
              <span v-if="!isProjectReady">3</span>
              <CheckCircle2 v-else class="w-4 h-4" />
            </div>
            <span class="ml-2 text-sm font-medium text-blue-600">Review</span>
          </div>
        </div>
      </div>

      <!-- Ready to Launch Alert -->
      <Alert v-if="isProjectReady" class="border-green-200 bg-green-50">
        <CheckCircle2 class="h-4 w-4 text-green-600" />
        <AlertDescription class="text-green-800">
          Your project is ready to launch! Review the configuration below and click "Activate Project" to begin.
        </AlertDescription>
      </Alert>

      <!-- Project Summary -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Settings class="h-5 w-5" />
            Project Summary
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-6">
          <!-- Basic Information -->
          <div>
            <h3 class="font-semibold mb-3 flex items-center gap-2">
              <FileAudio class="h-4 w-4" />
              Basic Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pl-6">
              <div>
                <p class="text-sm text-muted-foreground">Project Name</p>
                <p class="font-medium">{{ project.name }}</p>
              </div>
              <div>
                <p class="text-sm text-muted-foreground">Status</p>
                <Badge variant="outline" class="capitalize">{{ project.status }}</Badge>
              </div>
              <div v-if="project.description" class="md:col-span-2">
                <p class="text-sm text-muted-foreground">Description</p>
                <p class="text-sm">{{ project.description }}</p>
              </div>
              <div v-if="project.deadline">
                <p class="text-sm text-muted-foreground flex items-center gap-1">
                  <Calendar class="h-3 w-3" />
                  Deadline
                </p>
                <p class="font-medium">{{ new Date(project.deadline).toLocaleDateString() }}</p>
              </div>
              <div>
                <p class="text-sm text-muted-foreground flex items-center gap-1">
                  <User class="h-3 w-3" />
                  Owner
                </p>
                <p class="font-medium">{{ project.owner.name }}</p>
                <p class="text-xs text-muted-foreground">{{ project.owner.email }}</p>
              </div>
            </div>
          </div>

          <Separator />

          <!-- Time Configuration -->
          <div>
            <h3 class="font-semibold mb-3 flex items-center gap-2">
              <Clock class="h-4 w-4" />
              Time Limits
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pl-6">
              <div class="flex items-center justify-between p-3 bg-muted/50 rounded-lg">
                <div>
                  <p class="text-sm font-medium">Task Time Limit</p>
                  <p class="text-xs text-muted-foreground">Per annotation task</p>
                </div>
                <Badge variant="secondary">{{ formatTime(project.task_time_minutes) }}</Badge>
              </div>
              <div class="flex items-center justify-between p-3 bg-muted/50 rounded-lg">
                <div>
                  <p class="text-sm font-medium">Review Time Limit</p>
                  <p class="text-xs text-muted-foreground">Per review task</p>
                </div>
                <Badge variant="secondary">{{ formatTime(project.review_time_minutes) }}</Badge>
              </div>
            </div>
          </div>

          <Separator />

          <!-- Annotation Guidelines -->
          <div v-if="project.annotation_guidelines">
            <h3 class="font-semibold mb-3 flex items-center gap-2">
              <FileAudio class="h-4 w-4" />
              Annotation Guidelines
            </h3>
            <div class="pl-6">
              <div class="p-3 bg-muted/50 rounded-lg">
                <p class="text-sm whitespace-pre-line">{{ project.annotation_guidelines }}</p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Annotation Dimensions Summary -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Hash class="h-5 w-5" />
            Annotation Dimensions ({{ dimensions.length }})
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Alert v-if="dimensions.length === 0" variant="destructive" class="mb-4">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
              No annotation dimensions configured. You need at least one dimension to activate the project.
            </AlertDescription>
          </Alert>

          <div class="space-y-4">
            <div 
              v-for="(dimension, index) in dimensions" 
              :key="dimension.id"
              class="border rounded-lg p-4 hover:bg-muted/50 transition-colors"
            >
              <div class="flex items-start justify-between mb-2">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-muted-foreground">{{ index + 1 }}.</span>
                  <h3 class="font-medium">{{ dimension.name }}</h3>
                  <Badge 
                    :variant="dimension.dimension_type === 'categorical' ? 'default' : 'secondary'"
                    class="text-xs"
                  >
                    {{ dimension.dimension_type === 'categorical' ? 'Categorical' : 'Numeric Scale' }}
                  </Badge>
                  <Badge v-if="dimension.is_required" variant="outline" class="text-xs">Required</Badge>
                </div>
              </div>
              
              <p v-if="dimension.description" class="text-sm text-muted-foreground mb-3">
                {{ dimension.description }}
              </p>
              
              <!-- Categorical Values Preview -->
              <div v-if="dimension.dimension_type === 'categorical'" class="space-y-2">
                <p class="text-xs text-muted-foreground">Available Options:</p>
                <div class="flex flex-wrap gap-1">
                  <Badge 
                    v-for="value in dimension.values" 
                    :key="value.id" 
                    variant="outline"
                    class="text-xs"
                  >
                    {{ value.label || value.value }}
                  </Badge>
                </div>
              </div>
              
              <!-- Numeric Scale Preview -->
              <div v-else class="space-y-2">
                <p class="text-xs text-muted-foreground">Scale Range:</p>
                <div class="flex items-center gap-2">
                  <Badge variant="outline" class="text-xs">{{ dimension.scale_min }}</Badge>
                  <span class="text-xs text-muted-foreground">to</span>
                  <Badge variant="outline" class="text-xs">{{ dimension.scale_max }}</Badge>
                  <span class="text-xs text-muted-foreground">
                    ({{ (dimension.scale_max || 0) - (dimension.scale_min || 0) + 1 }} point scale)
                  </span>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Current Project Statistics -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Users class="h-5 w-5" />
            Current Project Status
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <div class="bg-muted/50 p-4 rounded-lg text-center">
              <div class="text-lg font-bold">{{ statistics.total_audio_files }}</div>
              <div class="text-sm text-muted-foreground">Audio Files</div>
            </div>
            
            <div class="bg-muted/50 p-4 rounded-lg text-center">
              <div class="text-lg font-bold">{{ statistics.total_tasks }}</div>
              <div class="text-sm text-muted-foreground">Total Tasks</div>
            </div>
            
            <div class="bg-muted/50 p-4 rounded-lg text-center">
              <div class="text-lg font-bold">{{ statistics.team_size }}</div>
              <div class="text-sm text-muted-foreground">Team Members</div>
            </div>
          </div>
          
          <p class="text-sm text-muted-foreground mt-4 text-center">
            Once activated, you can add team members, upload audio files, and begin annotation tasks.
          </p>
        </CardContent>
      </Card>

      <!-- Validation Errors -->
      <div v-if="Object.keys(form.errors).length > 0" class="p-4 bg-red-50 border border-red-200 rounded-md">
        <p class="text-sm text-red-600 font-medium mb-2">Unable to activate project:</p>
        <ul class="text-sm text-red-600 space-y-1">
          <li v-for="(error, field) in form.errors" :key="field">
            • {{ error }}
          </li>
        </ul>
      </div>

      <!-- Navigation & Final Actions -->
      <Card>
        <CardContent class="pt-6">
          <div class="flex items-center justify-between">
            <Button @click="goToPreviousStep" variant="outline">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Previous: Edit Dimensions
            </Button>
            
            <div class="flex items-center gap-3">
              <!-- Save as Draft (already saved) -->
              <p class="text-sm text-muted-foreground">
                Project saved as draft
              </p>
              
              <!-- Activate Project -->
              <Button 
                @click="finalizeProject" 
                :disabled="!isProjectReady || form.processing"
                class="flex items-center gap-2"
                size="lg"
              >
                <Play class="h-4 w-4" />
                <span>{{ form.processing ? 'Activating...' : 'Activate Project' }}</span>
              </Button>
            </div>
          </div>

          <!-- Activation Info -->
          <Alert class="mt-4">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
              <strong>What happens when you activate:</strong>
              <ul class="mt-2 text-sm list-disc list-inside space-y-1">
                <li>Project status will change to "Active"</li>
                <li>You can add team members and assign roles</li>
                <li>Audio files can be uploaded and converted to tasks</li>
                <li>Annotators can begin working on assigned tasks</li>
                <li>You can modify project settings anytime after activation</li>
              </ul>
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
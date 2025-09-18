<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { type BreadcrumbItemType } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import {
  ArrowLeft, Calendar, Tag, File, Users, Archive, Trash, Settings2, Clock,
  CheckCircle, AlertCircle, Play, Eye, TrendingUp, FileAudio, Settings,
  FolderOpen, CheckSquare, Layers, Tags, BarChart3, PieChart, Activity,
  Target, Award, Clock4, Users2, Zap, Star, AlertTriangle, TrendingDown
} from 'lucide-vue-next';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

// Enhanced interfaces
interface Member {
  id: number;
  user: { id: number; name: string; email: string; };
  role: string;
  is_active: boolean;
  workload_limit: number | null;
  assigned_at: string;
}

interface DimensionValue {
  id: number;
  value: string;
  label: string;
  display_order: number;
}

interface AnnotationDimension {
  id: number;
  name: string;
  description: string | null;
  dimension_type: 'categorical' | 'numeric_scale';
  scale_min: number | null;
  scale_max: number | null;
  is_required: boolean;
  display_order: number;
  values: DimensionValue[];
}

interface SegmentationLabel {
  id: number;
  name: string;
  color: string;
  description?: string | null;
}

interface EnhancedStats {
  taskStatusDistribution: Record<string, number>;
  dailyCompletions: Array<{ date: string; count: number }>;
  memberPerformance: Array<{
    id: number;
    name: string;
    email: string;
    role: string;
    completed_tasks: number;
    approved_tasks: number;
    active_tasks: number;
    avg_time_spent: number;
  }>;
  reviewStats: Array<{
    action: string;
    count: number;
    avg_rating: number;
    avg_review_time: number;
  }>;
  audioStats: {
    total_files: number;
    total_size: number;
    total_duration: number;
    avg_duration: number;
    min_duration: number;
    max_duration: number;
  };
  batchProgress: Array<{
    id: number;
    name: string;
    batch_status: string;
    total_tasks: number;
    completed_tasks: number;
    approved_tasks: number;
    active_tasks: number;
    completion_percentage: number;
  }>;
  qualityMetrics: {
    avg_review_rating: number;
    approval_rate: number;
    revision_rate: number;
    skip_rate: number;
  };
  summary: {
    total_tasks: number;
    completion_rate: number;
    avg_tasks_per_day: number;
    most_active_day: { date: string; count: number };
    team_efficiency: number;
  };
}

type ProjectStatus = 'draft' | 'active' | 'paused' | 'completed' | 'archived';
type ProjectType = 'annotation' | 'segmentation';

interface ProjectStatistics {
  total_tasks: number;
  completed_tasks: number;
  pending_tasks: number;
  approved_tasks: number;
  assigned_tasks: number;
  in_progress_tasks: number;
  under_review_tasks: number;
  rejected_tasks: number;
  total_media_files: number;
  completion_percentage: number;
  team_size: number;
  annotators_count: number;
  reviewers_count: number;
  task_skips: number;
  review_skips: number;
  total_audio_duration: number;
}

interface Project {
  id: number;
  name: string;
  description: string | null;
  status: ProjectStatus;
  project_type: ProjectType;
  allow_custom_labels?: boolean;
  ownership_type?: 'self_created' | 'admin_assigned';
  quality_threshold?: number;
  total_batches?: number;
  task_time_minutes: number;
  review_time_minutes: number;
  annotation_guidelines: string | null;
  deadline: string | null;
  owner: { id: number; name: string; email: string; };
  creator?: { id: number; name: string; email: string; };
  assigner?: { id: number; name: string; email: string; } | null;
  members?: Member[];
  statistics: ProjectStatistics;
  created_at: string;
  updated_at?: string;
}

interface Props {
  project: Project;
  dimensions?: AnnotationDimension[];
  segmentationLabels?: SegmentationLabel[];
  enhancedStats: EnhancedStats;
}

const props = defineProps<Props>();

// Chart colors
const STATUS_COLORS: Record<string, string> = {
  completed: '#10B981',
  approved: '#059669',
  pending: '#6B7280',
  assigned: '#3B82F6',
  in_progress: '#F59E0B',
  under_review: '#8B5CF6',
  rejected: '#EF4444',
  skipped: '#9CA3AF'
};

// Redirect back if draft & missing required config
onMounted(() => {
  if (props.project.status === 'draft') {
    const needsDimensions = props.project.project_type === 'annotation' && (!props.dimensions || props.dimensions.length === 0);
    const needsLabels = props.project.project_type === 'segmentation' && (!props.segmentationLabels || props.segmentationLabels.length === 0);
    if (needsDimensions || needsLabels) {
      router.visit(route('admin.projects.index'));
    }
  }
});

const breadcrumbs: BreadcrumbItemType[] = [
  { title: 'Projects', href: '/admin/projects' },
  { title: props.project.name, href: `/admin/projects/${props.project.id}` },
];

const showDeleteProjectDialog = ref(false);
const projectStatus = ref<ProjectStatus>(props.project.status);

const isSetupIncomplete = computed(() => {
  if (props.project.status !== 'draft') return false;
  return props.project.project_type === 'annotation'
    ? (props.dimensions?.length ?? 0) === 0
    : (props.segmentationLabels?.length ?? 0) === 0;
});

// Enhanced computed properties for charts
const taskStatusChartData = computed(() => {
  const totalTasks = Object.values(props.enhancedStats.taskStatusDistribution).reduce((sum, count) => sum + count, 0);
  return Object.entries(props.enhancedStats.taskStatusDistribution).map(([status, count]) => ({
    name: status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' '),
    value: count,
    percentage: totalTasks > 0 ? Math.round((count / totalTasks) * 100) : 0,
    color: STATUS_COLORS[status] || '#6B7280'
  }));
});

const completionTrendData = computed(() => {
  return props.enhancedStats.dailyCompletions.map(item => ({
    ...item,
    formattedDate: new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }));
});

const topPerformers = computed(() => {
  return [...props.enhancedStats.memberPerformance]
    .sort((a, b) => (b.completed_tasks + b.approved_tasks) - (a.completed_tasks + a.approved_tasks))
    .slice(0, 5);
});

const batchProgressData = computed(() => {
  return props.enhancedStats.batchProgress.map(batch => ({
    name: batch.name.length > 15 ? batch.name.substring(0, 15) + '...' : batch.name,
    completed: batch.completion_percentage,
    remaining: 100 - batch.completion_percentage,
  }));
});

const qualityScore = computed(() => {
  const metrics = props.enhancedStats.qualityMetrics;
  const score = (
    (metrics.approval_rate / 100) * 0.4 +
    (metrics.avg_review_rating / 5) * 0.3 +
    ((100 - metrics.skip_rate) / 100) * 0.2 +
    ((100 - metrics.revision_rate) / 100) * 0.1
  ) * 100;
  return Math.round(score);
});

const taskMetrics = computed(() => {
  const s = props.project.statistics;
  return [
    { 
      label: 'Total Tasks', 
      value: s.total_tasks, 
      icon: TrendingUp, 
      description: 'All tasks',
      color: 'bg-blue-100 text-blue-600'
    },
    { 
      label: 'Completed', 
      value: s.completed_tasks + s.approved_tasks, 
      icon: CheckCircle, 
      description: 'Finished',
      color: 'bg-green-100 text-green-600'
    },
    { 
      label: 'In Progress', 
      value: s.assigned_tasks + s.in_progress_tasks, 
      icon: Play, 
      description: 'Active',
      color: 'bg-yellow-100 text-yellow-600'
    },
    { 
      label: 'Media Files', 
      value: s.total_media_files, 
      icon: File, 
      description: formatDuration(s.total_audio_duration),
      color: 'bg-purple-100 text-purple-600'
    },
  ];
});

const statusConfig = computed(() => {
  const map: Record<ProjectStatus, { color: string; icon: any }> = {
    draft: { color: 'bg-muted text-muted-foreground', icon: Settings2 },
    active: { color: 'bg-green-500/10 text-green-700 border-green-200', icon: Play },
    paused: { color: 'bg-yellow-500/10 text-yellow-700 border-yellow-200', icon: Clock },
    completed: { color: 'bg-blue-500/10 text-blue-700 border-blue-200', icon: CheckCircle },
    archived: { color: 'bg-purple-500/10 text-purple-700 border-purple-200', icon: Archive },
  };
  return map[projectStatus.value];
});

// Helper functions
function formatDuration(seconds: number) {
  if (!seconds) return '0m';
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function formatBytes(bytes: number) {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

const archiveProject = () => {
  router.patch(route('admin.projects.archive', props.project.id), {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => (projectStatus.value = 'archived'),
  });
};

const restoreProject = () => {
  router.patch(route('admin.projects.restore', props.project.id), {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => (projectStatus.value = 'active'),
  });
};

const openDeleteProjectDialog = () => (showDeleteProjectDialog.value = true);
const deleteProject = () => router.delete(route('admin.projects.destroy', props.project.id));

const goToStepTwo = () => {
  router.visit(route('admin.projects.create.step-two', props.project.id));
};

const projectTypeLabel = computed(() =>
  props.project.project_type === 'annotation' ? 'Audio Annotation' : 'Audio Segmentation'
);
</script>

<template>
  <Head :title="project.name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">

      <!-- Setup warning -->
      <Alert v-if="isSetupIncomplete" variant="destructive" class="border-amber-200 bg-amber-50">
        <AlertCircle class="h-4 w-4 text-amber-600" />
        <AlertDescription class="text-amber-800">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-medium mb-1">Project Setup Incomplete</p>
              <p class="text-sm">
                This project cannot be used until
                <strong v-if="project.project_type === 'annotation'">annotation dimensions</strong>
                <strong v-else>segmentation labels</strong>
                are configured.
              </p>
            </div>
            <Button @click="goToStepTwo" size="sm" class="ml-4">
              <Settings class="h-3 w-3 mr-1" />
              Configure
              <span v-if="project.project_type === 'annotation'">Dimensions</span>
              <span v-else>Labels</span>
            </Button>
          </div>
        </AlertDescription>
      </Alert>

      <!-- Header -->
      <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
          <Link :href="route('admin.projects.index')">
            <Button variant="ghost" class="gap-2">
              <ArrowLeft class="h-4 w-4" />
              Back to Projects
            </Button>
          </Link>

          <div class="flex items-center gap-2">
            <Badge :class="statusConfig.color" class="gap-1.5 px-3 py-1">
              <component :is="statusConfig.icon" class="h-3 w-3" />
              {{ projectStatus.charAt(0).toUpperCase() + projectStatus.slice(1) }}
            </Badge>
            <Badge class="bg-blue-100 text-blue-800 gap-1">
              <Award class="h-3 w-3" />
              {{ qualityScore }}% Quality
            </Badge>
            <AlertCircle v-if="isSetupIncomplete" class="h-5 w-5 text-amber-500" />
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-3xl font-bold tracking-tight md:text-4xl">{{ project.name }}</h1>
            </div>
            <p v-if="project.description" class="mt-2 text-lg text-muted-foreground max-w-3xl">
              {{ project.description }}
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
            <div class="flex items-center gap-1.5">
              <File class="h-4 w-4" />
              <span>{{ projectTypeLabel }}</span>
            </div>

            <div class="flex items-center gap-1.5">
              <Users class="h-4 w-4" />
              <span>{{ project.statistics.team_size }} members</span>
            </div>

            <div class="flex items-center gap-1.5">
              <Target class="h-4 w-4" />
              <span>{{ enhancedStats.summary.completion_rate }}% complete</span>
            </div>

            <Separator orientation="vertical" class="h-4" />

            <div class="flex items-center gap-1.5">
              <Calendar class="h-4 w-4" />
              <span>Created {{ new Date(project.created_at).toLocaleDateString() }}</span>
            </div>

            <div v-if="project.deadline" class="flex items-center gap-1.5">
              <AlertCircle class="h-4 w-4" />
              <span>Due {{ new Date(project.deadline).toLocaleDateString() }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Enhanced Metrics Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        <div
          v-for="metric in taskMetrics"
          :key="metric.label"
          class="group relative overflow-hidden rounded-lg border bg-card p-6 shadow-sm transition-all hover:shadow-md hover:scale-[1.02]"
        >
          <div class="flex items-center justify-between mb-4">
            <div class="rounded-md p-2 transition-colors" :class="metric.color">
              <component :is="metric.icon" class="h-4 w-4" />
            </div>
          </div>
          <div class="space-y-1">
            <p class="text-sm font-medium text-muted-foreground">{{ metric.label }}</p>
            <p class="text-2xl font-bold">{{ metric.value.toLocaleString() }}</p>
            <p class="text-xs text-muted-foreground">{{ metric.description }}</p>
          </div>
        </div>
      </div>

      <!-- Quality Score Card -->
      <Card class="border-l-4 border-l-blue-500">
        <CardHeader class="pb-3">
          <div class="flex items-center justify-between">
            <CardTitle class="flex items-center gap-2">
              <Star class="h-5 w-5 text-yellow-500" />
              Project Quality Score
            </CardTitle>
            <div class="flex items-center gap-2">
              <div class="w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-lg"
                   :style="{ 
                     backgroundColor: qualityScore >= 80 ? '#10B981' : qualityScore >= 60 ? '#F59E0B' : '#EF4444' 
                   }">
                {{ qualityScore }}%
              </div>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 rounded-lg bg-green-50 border border-green-200">
              <p class="text-2xl font-bold text-green-600">{{ enhancedStats.qualityMetrics.approval_rate }}%</p>
              <p class="text-sm text-muted-foreground">Approval Rate</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-blue-50 border border-blue-200">
              <p class="text-2xl font-bold text-blue-600">{{ enhancedStats.qualityMetrics.avg_review_rating.toFixed(1) }}/5</p>
              <p class="text-sm text-muted-foreground">Avg Rating</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-orange-50 border border-orange-200">
              <p class="text-2xl font-bold text-orange-600">{{ enhancedStats.qualityMetrics.skip_rate }}%</p>
              <p class="text-sm text-muted-foreground">Skip Rate</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-purple-50 border border-purple-200">
              <p class="text-2xl font-bold text-purple-600">{{ enhancedStats.qualityMetrics.revision_rate }}%</p>
              <p class="text-sm text-muted-foreground">Revision Rate</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Charts and Analytics Tabs -->
      <Tabs default-value="overview" class="space-y-6">
        <TabsList class="grid w-full grid-cols-3">
          <TabsTrigger value="overview" class="gap-2">
            <BarChart3 class="h-4 w-4" />
            Overview
          </TabsTrigger>
          <TabsTrigger value="team" class="gap-2">
            <Users2 class="h-4 w-4" />
            Team
          </TabsTrigger>
          <TabsTrigger value="config" class="gap-2">
            <Settings2 class="h-4 w-4" />
            Configuration
          </TabsTrigger>
        </TabsList>

        <!-- Overview Tab -->
        <TabsContent value="overview" class="space-y-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Task Status Distribution - CSS-based pie chart -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center gap-2">
                  <PieChart class="h-5 w-5" />
                  Task Status Distribution
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="flex items-center justify-center mb-6">
                  <div class="relative w-48 h-48">
                    <!-- CSS Pie Chart -->
                    <div class="w-full h-full rounded-full" 
                         :style="{
                           background: `conic-gradient(
                             ${taskStatusChartData.map((item, index) => {
                               const startAngle = taskStatusChartData.slice(0, index).reduce((sum, prev) => sum + prev.percentage, 0);
                               const endAngle = startAngle + item.percentage;
                               return `${item.color} ${startAngle * 3.6}deg ${endAngle * 3.6}deg`;
                             }).join(', ')}
                           )`
                         }">
                      <!-- Center hole for donut effect -->
                      <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-24 h-24 bg-white rounded-full flex items-center justify-center">
                        <div class="text-center">
                          <div class="text-lg font-bold">{{ enhancedStats.summary.total_tasks }}</div>
                          <div class="text-xs text-muted-foreground">Total</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <div v-for="item in taskStatusChartData" :key="item.name" class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: item.color }"></div>
                    <span class="text-sm">{{ item.name }} ({{ item.value }})</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Daily Completion Trend - CSS-based line chart -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center gap-2">
                  <Activity class="h-5 w-5" />
                  Daily Completion Trend (30 days)
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="h-64 w-full relative">
                  <!-- CSS Line Chart -->
                  <div class="absolute inset-0 flex items-end justify-between px-2">
                    <div v-for="(item, index) in completionTrendData.slice(-7)" :key="index" 
                         class="flex flex-col items-center flex-1">
                      <div class="w-full flex items-end justify-center mb-2">
                        <div class="bg-blue-500 rounded-t-sm transition-all hover:bg-blue-600" 
                             :style="{ 
                               height: `${Math.max(item.count * 20, 2)}px`,
                               width: '20px'
                             }"></div>
                      </div>
                      <div class="text-xs text-muted-foreground text-center">
                        {{ item.formattedDate }}
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mt-4 text-sm text-muted-foreground">
                  Average: {{ enhancedStats.summary.avg_tasks_per_day.toFixed(1) }} tasks/day
                  • Peak: {{ enhancedStats.summary.most_active_day.count }} tasks on 
                  {{ new Date(enhancedStats.summary.most_active_day.date).toLocaleDateString() }}
                </div>
              </CardContent>
            </Card>
          </div>

          <!-- Batch Progress -->
          <Card v-if="enhancedStats.batchProgress.length > 0">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <FolderOpen class="h-5 w-5" />
                Batch Progress
                <Badge variant="secondary">{{ enhancedStats.batchProgress.length }}</Badge>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-4">
                <div v-for="batch in enhancedStats.batchProgress" :key="batch.id" 
                     class="flex items-center justify-between p-4 rounded-lg border bg-muted/20">
                  <div class="flex items-center gap-4">
                    <div class="flex-shrink-0">
                      <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-sm"
                           :style="{ backgroundColor: batch.completion_percentage > 50 ? '#10B981' : '#F59E0B' }">
                        {{ batch.completion_percentage }}%
                      </div>
                    </div>
                    <div>
                      <p class="font-medium">{{ batch.name }}</p>
                      <p class="text-sm text-muted-foreground">
                        {{ batch.completed_tasks + batch.approved_tasks }} of {{ batch.total_tasks }} tasks completed
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <Badge :variant="batch.batch_status === 'completed' ? 'default' : 'secondary'">
                      {{ batch.batch_status }}
                    </Badge>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Audio Statistics -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <FileAudio class="h-5 w-5" />
                Audio File Statistics
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="text-center">
                  <p class="text-2xl font-bold">{{ enhancedStats.audioStats.total_files }}</p>
                  <p class="text-sm text-muted-foreground">Total Files</p>
                </div>
                <div class="text-center">
                  <p class="text-2xl font-bold">{{ formatBytes(enhancedStats.audioStats.total_size) }}</p>
                  <p class="text-sm text-muted-foreground">Total Size</p>
                </div>
                <div class="text-center">
                  <p class="text-2xl font-bold">{{ formatDuration(enhancedStats.audioStats.total_duration) }}</p>
                  <p class="text-sm text-muted-foreground">Total Duration</p>
                </div>
                <div class="text-center">
                  <p class="text-2xl font-bold">{{ formatDuration(enhancedStats.audioStats.avg_duration) }}</p>
                  <p class="text-sm text-muted-foreground">Avg Duration</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Performance Tab -->
        <TabsContent value="performance" class="space-y-6">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Review Statistics -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center gap-2">
                  <Eye class="h-5 w-5" />
                  Review Performance
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="space-y-4">
                  <div v-for="review in enhancedStats.reviewStats.filter(r => r.action)" :key="review.action" 
                       class="flex items-center justify-between p-3 rounded-lg bg-muted/30">
                    <div class="flex items-center gap-3">
                      <Badge :variant="review.action === 'approved' ? 'default' : 'destructive'">
                        {{ review.action.charAt(0).toUpperCase() + review.action.slice(1) }}
                      </Badge>
                      <span class="font-medium">{{ review.count }} reviews</span>
                    </div>
                    <div class="text-right text-sm text-muted-foreground">
                      <template v-if="review.avg_rating">
                        {{ review.avg_rating.toFixed(1) }}/5 rating<br>
                      </template>
                      <template v-if="review.avg_review_time">
                        {{ formatDuration(review.avg_review_time) }} avg time
                      </template>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <!-- Summary Stats -->
            <Card>
              <CardHeader>
                <CardTitle class="flex items-center gap-2">
                  <Zap class="h-5 w-5" />
                  Performance Summary
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="space-y-4">
                  <div class="flex items-center justify-between p-3 rounded-lg bg-green-50 border border-green-200">
                    <div class="flex items-center gap-3">
                      <CheckCircle class="h-5 w-5 text-green-600" />
                      <span class="font-medium">Completion Rate</span>
                    </div>
                    <span class="text-xl font-bold text-green-600">{{ enhancedStats.summary.completion_rate }}%</span>
                  </div>
                  
                  <div class="flex items-center justify-between p-3 rounded-lg bg-blue-50 border border-blue-200">
                    <div class="flex items-center gap-3">
                      <Users2 class="h-5 w-5 text-blue-600" />
                      <span class="font-medium">Team Efficiency</span>
                    </div>
                    <span class="text-xl font-bold text-blue-600">{{ enhancedStats.summary.team_efficiency }}</span>
                  </div>
                  
                  <div class="flex items-center justify-between p-3 rounded-lg bg-purple-50 border border-purple-200">
                    <div class="flex items-center gap-3">
                      <Clock4 class="h-5 w-5 text-purple-600" />
                      <span class="font-medium">Avg Tasks/Day</span>
                    </div>
                    <span class="text-xl font-bold text-purple-600">{{ enhancedStats.summary.avg_tasks_per_day.toFixed(1) }}</span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <!-- Team Tab -->
        <TabsContent value="team" class="space-y-6">
          <!-- Top Performers -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Award class="h-5 w-5" />
                Top Performers
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-4">
                <div v-for="(member, index) in topPerformers" :key="member.id" 
                     class="flex items-center justify-between p-4 rounded-lg border">
                  <div class="flex items-center gap-3">
                    <Badge :variant="index < 3 ? 'default' : 'secondary'" class="w-8 h-8 rounded-full flex items-center justify-center">
                      {{ index + 1 }}
                    </Badge>
                    <div>
                      <p class="font-medium">{{ member.name }}</p>
                      <p class="text-sm text-muted-foreground">{{ member.role }}</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="font-medium">{{ member.completed_tasks + member.approved_tasks }} tasks</p>
                    <p class="text-sm text-muted-foreground">
                      {{ member.avg_time_spent ? formatDuration(member.avg_time_spent) : 'No data' }} avg
                    </p>
                  </div>
                </div>
              </div>
            </CardContent>
            <CardFooter>
              <Link :href="route('admin.projects.members.index', project.id)" class="w-full">
                <Button class="w-full gap-2">
                  <Users class="h-4 w-4" />
                  View All Team Members
                </Button>
              </Link>
            </CardFooter>
          </Card>
        </TabsContent>

        <!-- Configuration Tab -->
        <TabsContent value="config" class="space-y-6">
          <!-- Configuration block (type-aware) -->
          <Card>
            <CardHeader>
              <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                  <template v-if="project.project_type === 'annotation'">
                    <Settings2 class="h-5 w-5" />
                    Annotation Dimensions
                    <Badge variant="secondary">{{ (dimensions?.length ?? 0) }}</Badge>
                  </template>
                  <template v-else>
                    <Layers class="h-5 w-5" />
                    Segmentation Labels
                    <Badge variant="secondary">{{ (segmentationLabels?.length ?? 0) }}</Badge>
                  </template>
                </CardTitle>

                <Button v-if="isSetupIncomplete" @click="goToStepTwo" size="sm" class="gap-2">
                  <Settings class="h-4 w-4" />
                  <span v-if="project.project_type === 'annotation'">Configure Dimensions</span>
                  <span v-else>Configure Labels</span>
                </Button>
              </div>
            </CardHeader>

            <CardContent>
              <!-- Annotation: Dimensions -->
              <div v-if="project.project_type === 'annotation'">
                <div v-if="!dimensions || dimensions.length === 0"
                     class="text-center py-8 border-2 border-dashed border-amber-300 rounded-lg bg-amber-50">
                  <Settings2 class="mx-auto h-8 w-8 text-amber-500 mb-4" />
                  <h3 class="text-lg font-medium text-amber-900 mb-2">No Dimensions Configured</h3>
                  <p class="text-amber-700 mb-4">Configure annotation dimensions to use this project.</p>
                  <Button @click="goToStepTwo" class="gap-2">
                    <Settings class="h-4 w-4" /> Configure Dimensions Now
                  </Button>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2">
                  <div v-for="dimension in dimensions" :key="dimension.id" class="space-y-3 rounded-lg border bg-muted/20 p-4">
                    <div class="flex items-start justify-between">
                      <div class="space-y-1">
                        <div class="flex items-center gap-2">
                          <p class="font-medium">{{ dimension.name }}</p>
                          <Badge v-if="dimension.is_required" variant="outline" class="text-xs">Required</Badge>
                        </div>
                        <p v-if="dimension.description" class="text-sm text-muted-foreground">{{ dimension.description }}</p>
                      </div>
                      <Badge :variant="dimension.dimension_type === 'categorical' ? 'default' : 'secondary'">
                        {{ dimension.dimension_type === 'categorical' ? 'Categorical' : 'Scale' }}
                      </Badge>
                    </div>

                    <div v-if="dimension.dimension_type === 'categorical'" class="flex flex-wrap gap-1">
                      <Badge v-for="value in dimension.values.slice(0, 3)" :key="value.id" variant="outline" class="text-xs">
                        {{ value.label || value.value }}
                      </Badge>
                      <Badge v-if="dimension.values.length > 3" variant="outline" class="text-xs">
                        +{{ dimension.values.length - 3 }} more
                      </Badge>
                    </div>

                    <div v-else class="text-sm text-muted-foreground">
                      Range: {{ dimension.scale_min }} - {{ dimension.scale_max }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Segmentation: Labels -->
              <div v-else>
                <div v-if="!segmentationLabels || segmentationLabels.length === 0"
                     class="text-center py-8 border-2 border-dashed border-amber-300 rounded-lg bg-amber-50">
                  <Layers class="mx-auto h-8 w-8 text-amber-500 mb-4" />
                  <h3 class="text-lg font-medium text-amber-900 mb-2">No Labels Configured</h3>
                  <p class="text-amber-700 mb-4">Add segmentation labels to use this project.</p>
                  <Button @click="goToStepTwo" class="gap-2">
                    <Settings class="h-4 w-4" /> Configure Labels Now
                  </Button>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  <div v-for="label in segmentationLabels" :key="label.id"
                       class="flex items-center gap-3 p-4 rounded-lg border bg-muted/20">
                    <div class="w-4 h-4 rounded-full border shadow-sm flex-shrink-0" :style="{ backgroundColor: label.color }" />
                    <div class="min-w-0">
                      <p class="font-medium text-sm">{{ label.name }}</p>
                      <p v-if="label.description" class="text-xs text-muted-foreground">{{ label.description }}</p>
                    </div>
                  </div>
                </div>

                <Alert v-if="project.allow_custom_labels" class="mt-4 border-blue-200 bg-blue-50">
                  <Tags class="h-4 w-4 text-blue-600" />
                  <AlertDescription class="text-blue-800">
                    <strong>Custom Labels Enabled:</strong> Annotators can create additional labels during segmentation tasks.
                  </AlertDescription>
                </Alert>
              </div>
            </CardContent>
          </Card>

          <!-- Guidelines -->
          <Card v-if="project.annotation_guidelines">
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Tag class="h-5 w-5" />
                {{ project.project_type === 'annotation' ? 'Annotation Guidelines' : 'Segmentation Guidelines' }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="rounded-md bg-muted/30 p-4">
                <p class="whitespace-pre-wrap text-sm leading-relaxed">{{ project.annotation_guidelines }}</p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <!-- Quick Actions Grid -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
              <Users class="h-5 w-5" />
              Team Management
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground mb-3">{{ project.statistics?.team_size ?? 0 }} active members</p>
            <Link :href="route('admin.projects.members.index', project.id)">
              <Button class="w-full gap-2">
                <Users class="h-4 w-4" />
                Manage Team
              </Button>
            </Link>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
              <FolderOpen class="h-5 w-5" />
              Batch Control
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground mb-3">{{ project?.total_batches ?? 0 }} batches created</p>
            <Link :href="route('admin.projects.batches.index', project.id)">
              <Button class="w-full gap-2">
                <FolderOpen class="h-4 w-4" />
                Manage Batches
              </Button>
            </Link>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
              <FileAudio class="h-5 w-5" />
              Audio Files
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground mb-3">{{ project?.statistics?.total_media_files ?? 0 }} files uploaded</p>
            <Link :href="route('admin.projects.audio-files.index', project.id)">
              <Button class="w-full gap-2">
                <FileAudio class="h-4 w-4" />
                Manage Files
              </Button>
            </Link>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
              <CheckSquare class="h-5 w-5" />
              Tasks & Export
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground mb-3">{{ project?.statistics?.total_tasks ?? 0 }} total tasks</p>
            <Link :href="route('admin.projects.tasks.manage', project.id)">
              <Button class="w-full gap-2">
                <CheckSquare class="h-4 w-4" />
                View Tasks
              </Button>
            </Link>
          </CardContent>
        </Card>
      </div>

      <!-- Project Settings -->
      <Card>
        <CardHeader>
          <CardTitle>Project Settings</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <p class="text-sm font-medium text-muted-foreground">Owner</p>
              <p class="text-sm">{{ project.owner.name }}</p>
              <p class="text-xs text-muted-foreground">{{ project.owner.email }}</p>
            </div>

            <div>
              <p class="text-sm font-medium text-muted-foreground">Time Limits</p>
              <div class="space-y-1">
                <p class="text-sm">Task: {{ project.task_time_minutes }}min</p>
                <p class="text-sm">Review: {{ project.review_time_minutes }}min</p>
              </div>
            </div>

            <div>
              <p class="text-sm font-medium text-muted-foreground">Activity Summary</p>
              <div class="space-y-1">
                <p class="text-sm">Task Skips: {{ project.statistics.task_skips }}</p>
                <p class="text-sm">Review Skips: {{ project.statistics.review_skips }}</p>
              </div>
            </div>

            <div>
              <p class="text-sm font-medium text-muted-foreground">Created</p>
              <p class="text-sm">{{ new Date(project.created_at).toLocaleDateString() }}</p>
              <p class="text-xs text-muted-foreground">{{ new Date(project.created_at).toLocaleTimeString() }}</p>
            </div>
          </div>
        </CardContent>
        <CardFooter class="flex-col gap-2">
          <Button
            v-if="projectStatus !== 'archived'"
            variant="outline"
            class="w-full gap-2 border-amber-200 text-amber-700 hover:bg-amber-50"
            @click="archiveProject"
            :disabled="isSetupIncomplete"
          >
            <Archive class="h-4 w-4" />
            Archive Project
          </Button>

          <Button v-else variant="outline" class="w-full gap-2" @click="restoreProject">
            <Archive class="h-4 w-4" />
            Restore Project
          </Button>

          <Button
            variant="outline"
            class="w-full gap-2 border-destructive text-destructive hover:bg-destructive hover:text-destructive-foreground"
            @click="openDeleteProjectDialog"
          >
            <Trash class="h-4 w-4" />
            Delete Project
          </Button>
        </CardFooter>
      </Card>
    </div>

    <ConfirmDialog
      :open="showDeleteProjectDialog"
      @update:open="showDeleteProjectDialog = $event"
      title="Delete Project"
      description="Are you sure you want to delete this project? This action cannot be undone."
      confirm-text="Delete Project"
      cancel-text="Cancel"
      confirm-variant="destructive"
      @confirm="deleteProject"
    />
  </AppLayout>
</template>
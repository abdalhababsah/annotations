<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItemType } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import {
    ArrowLeft,
    Calendar,
    Tag,
    File,
    Users,
    Archive,
    Trash,
    ListTodo,
    Settings2,
    Clock,
    CheckCircle,
    AlertCircle,
    Play,
    Eye,
    Target,
    TrendingUp,
    FileAudio,
    Settings,
    FolderOpen,
    CheckSquare
} from 'lucide-vue-next';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

interface Member {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
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
    description: string;
    dimension_type: string;
    scale_min: number | null;
    scale_max: number | null;
    is_required: boolean;
    display_order: number;
    values: DimensionValue[];
}

interface Project {
    id: number;
    name: string;
    description: string;
    status: 'draft' | 'active' | 'paused' | 'completed' | 'archived';
    project_type: 'audio';
    ownership_type: 'self_created' | 'admin_assigned';
    quality_threshold: number;
    total_batches: number;
    task_time_minutes: number;
    review_time_minutes: number;
    annotation_guidelines: string | null;
    deadline: string | null;
    owner: {
        id: number;
        name: string;
        email: string;
    };
    creator: {
        id: number;
        name: string;
        email: string;
    };
    assigner: {
        id: number;
        name: string;
        email: string;
    } | null;
    members: Member[];
    annotation_dimensions: AnnotationDimension[];
    statistics: {
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
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    project: Project;
    dimensions: AnnotationDimension[];
}

const props = defineProps<Props>();

// Check for dimensions on mount and redirect if missing
onMounted(() => {
    if (props.project.status === 'draft' && props.dimensions.length === 0) {
        router.visit(route('admin.projects.index'), {
            method: 'get',
            data: {},
            onSuccess: () => {
                // Flash message will be handled by the controller redirect
            }
        });
        return;
    }
});

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Projects', href: '/admin/projects' },
    { title: props.project.name, href: `/admin/projects/${props.project.id}` },
];

const showDeleteProjectDialog = ref(false);
const projectStatus = ref<string>(props.project.status);

const isDimensionsIncomplete = computed(() => {
    return props.project.status === 'draft' && props.dimensions.length === 0;
});

const taskMetrics = computed(() => {
    const stats = props.project.statistics;
    return [
        {
            label: 'Total Tasks',
            value: stats.total_tasks,
            icon: Target,
            trend: null,
            description: 'All annotation tasks'
        },
        {
            label: 'Completed',
            value: stats.completed_tasks + stats.approved_tasks,
            icon: CheckCircle,
            trend: stats.total_tasks > 0 ? `${Math.round(((stats.completed_tasks + stats.approved_tasks) / stats.total_tasks) * 100)}%` : '0%',
            description: 'Finished tasks'
        },
        {
            label: 'In Progress',
            value: stats.assigned_tasks + stats.in_progress_tasks,
            icon: Play,
            trend: stats.total_tasks > 0 ? `${Math.round(((stats.assigned_tasks + stats.in_progress_tasks) / stats.total_tasks) * 100)}%` : '0%',
            description: 'Active work'
        },
        {
            label: 'Audio Files',
            value: stats.total_media_files,
            icon: File,
            trend: formatDuration(stats.total_audio_duration),
            description: 'Media content'
        }
    ];
});

const statusConfig = computed(() => {
    const configs = {
        draft: { color: 'bg-muted text-muted-foreground', icon: Settings2 },
        active: { color: 'bg-green-500/10 text-green-700 border-green-200', icon: Play },
        paused: { color: 'bg-yellow-500/10 text-yellow-700 border-yellow-200', icon: Clock },
        completed: { color: 'bg-blue-500/10 text-blue-700 border-blue-200', icon: CheckCircle },
        archived: { color: 'bg-purple-500/10 text-purple-700 border-purple-200', icon: Archive },
    };
    return configs[projectStatus.value as keyof typeof configs] || configs.draft;
});

const formatDuration = (seconds: number) => {
    if (!seconds) return '0m';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
};

const archiveProject = () => {
    router.patch(route('admin.projects.archive', props.project.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            projectStatus.value = 'archived';
        }
    });
};

const restoreProject = () => {
    router.patch(route('admin.projects.restore', props.project.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            projectStatus.value = 'active';
        }
    });
};

const openDeleteProjectDialog = () => {
    showDeleteProjectDialog.value = true;
};

const deleteProject = () => {
    router.delete(route('admin.projects.destroy', props.project.id));
};

const continueDimensionSetup = () => {
    router.visit(route('admin.projects.create.step-two', props.project.id));
};
</script>

<template>

    <Head :title="project.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">

            <!-- Dimensions Incomplete Warning -->
            <Alert v-if="isDimensionsIncomplete" variant="destructive" class="border-amber-200 bg-amber-50">
                <AlertCircle class="h-4 w-4 text-amber-600" />
                <AlertDescription class="text-amber-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium mb-1">Project Setup Incomplete</p>
                            <p class="text-sm">
                                This project cannot be used until annotation dimensions are configured.
                            </p>
                        </div>
                        <Button @click="continueDimensionSetup" size="sm" class="ml-4">
                            <Settings class="h-3 w-3 mr-1" />
                            Configure Dimensions
                        </Button>
                    </div>
                </AlertDescription>
            </Alert>

            <!-- Header -->
            <div class="flex flex-col gap-4">
                <!-- Nav + Status Row -->
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
                        <AlertCircle v-if="isDimensionsIncomplete" class="h-5 w-5 text-amber-500"
                            title="Setup incomplete - no annotation dimensions configured" />
                    </div>
                </div>

                <!-- Title + Meta -->
                <div class="space-y-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                                {{ project.name }}
                            </h1>
                        </div>
                        <p v-if="project.description" class="mt-2 text-lg text-muted-foreground max-w-3xl">
                            {{ project.description }}
                        </p>
                        <p v-if="isDimensionsIncomplete" class="mt-2 text-sm text-amber-600 font-medium">
                            ⚠️ Setup incomplete - Configure annotation dimensions to activate this project
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                        <div class="flex items-center gap-1.5">
                            <File class="h-4 w-4" />
                            <span>
                                {{ project.project_type.charAt(0).toUpperCase() + project.project_type.slice(1) }}
                                Annotation
                            </span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <Users class="h-4 w-4" />
                            <span>{{ project.statistics.team_size }} members</span>
                        </div>

                        <Separator orientation="vertical" class="h-4" />

                        <div class="flex items-center gap-1.5">
                            <Calendar class="h-4 w-4" />
                            <span>Created {{ new Date(project.created_at).toLocaleDateString() }}</span>
                        </div>

                        <div v-if="project.deadline" class="flex items-center gap-1.5">
                            <Separator orientation="vertical" class="h-4" />
                            <AlertCircle class="h-4 w-4" />
                            <span>Due {{ new Date(project.deadline).toLocaleDateString() }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                <div v-for="metric in taskMetrics" :key="metric.label"
                    class="group relative overflow-hidden rounded-lg border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-muted-foreground">{{ metric.label }}</p>
                            <p class="text-2xl font-bold">{{ metric.value.toLocaleString() }}</p>
                            <p v-if="metric.trend" class="text-xs text-muted-foreground">
                                {{ metric.trend }}
                            </p>
                        </div>
                        <div class="rounded-md bg-muted/50 p-2">
                            <component :is="metric.icon" class="h-4 w-4 text-muted-foreground" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Grid (12 cols: 8 main / 4 sidebar) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Left: Main content -->
                <div class="space-y-6 lg:col-span-8">
                    <!-- Project Progress -->
                    <Card>
                        <CardHeader class="pb-4">
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2">
                                    <TrendingUp class="h-5 w-5" />
                                    Project Progress
                                </CardTitle>
                                <Badge variant="secondary" class="gap-1">
                                    {{ project.statistics.completion_percentage }}% complete
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Overall Completion</span>
                                    <span class="font-medium">{{ project.statistics.completion_percentage }}%</span>
                                </div>
                                <Progress :value="project.statistics.completion_percentage" class="h-2" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="flex items-center gap-3 rounded-md bg-muted/30 p-3">
                                    <div class="rounded-sm bg-blue-500/10 p-1.5">
                                        <Clock class="h-3 w-3 text-blue-600" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-sm font-medium">{{ project.statistics.pending_tasks }}</p>
                                        <p class="text-xs text-muted-foreground">Pending</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 rounded-md bg-muted/30 p-3">
                                    <div class="rounded-sm bg-yellow-500/10 p-1.5">
                                        <Play class="h-3 w-3 text-yellow-600" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-sm font-medium">
                                            {{ project.statistics.assigned_tasks + project.statistics.in_progress_tasks
                                            }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">In Progress</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 rounded-md bg-muted/30 p-3">
                                    <div class="rounded-sm bg-purple-500/10 p-1.5">
                                        <Eye class="h-3 w-3 text-purple-600" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-sm font-medium">{{ project.statistics.under_review_tasks }}</p>
                                        <p class="text-xs text-muted-foreground">Under Review</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 rounded-md bg-muted/30 p-3">
                                    <div class="rounded-sm bg-green-500/10 p-1.5">
                                        <CheckCircle class="h-3 w-3 text-green-600" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-sm font-medium">{{ project.statistics.approved_tasks }}</p>
                                        <p class="text-xs text-muted-foreground">Approved</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                        <CardFooter>
                            <div class="w-full">
                                <Link v-if="!isDimensionsIncomplete" :href="`/admin/projects/${project.id}/tasks`"
                                    class="w-full">

                                </Link>
                                <Button v-else @click="continueDimensionSetup" class="w-full gap-2" variant="outline">
                                    <Settings class="h-4 w-4" />
                                    Complete Project Setup First
                                </Button>
                            </div>
                        </CardFooter>
                    </Card>

                    <!-- Team + Batches (two-up) -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Team card -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Users class="h-5 w-5" />
                                    Team
                                    <Badge variant="secondary">{{ project.statistics?.team_size ?? 0 }}</Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-2">
                                <p class="text-sm text-muted-foreground">
                                    Manage assignments, and roles on the team members page.
                                </p>
                                <Link :href="route('admin.projects.members.index', project.id)">
                                <Button class="gap-2">
                                    <Users class="h-4 w-4" />
                                    Open Team Page
                                </Button>
                                </Link>
                            </CardContent>
                        </Card>

                        <!-- Batches card -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <FolderOpen class="h-5 w-5" />
                                    Batches
                                    <Badge variant="secondary">{{ project?.total_batches ?? 0 }}</Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-2">
                                <p class="text-sm text-muted-foreground">
                                    Create, publish, and monitor batches for this project.
                                </p>
                                <Link :href="route('admin.projects.batches.index', project.id)">
                                <Button class="gap-2">
                                    <FolderOpen class="h-4 w-4" />
                                    Open Batches
                                </Button>
                                </Link>
                            </CardContent>
                        </Card>

                        <!-- Audio Files card -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <FileAudio class="h-5 w-5" />
                                    Audio Files
                                    <Badge variant="secondary">{{ project?.statistics?.total_media_files ?? 0 }}</Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-2">
                                <p class="text-sm text-muted-foreground">
                                    Upload, import, and manage audio files for this project.
                                </p>
                                <Link :href="route('admin.projects.audio-files.index', project.id)">
                                <Button class="gap-2">
                                    <FileAudio class="h-4 w-4" />
                                    Manage Audio Files
                                </Button>
                                </Link>
                            </CardContent>
                        </Card>

                        <!-- 🔥 Tasks card -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <CheckSquare class="h-5 w-5" />
                                    Tasks
                                    <Badge variant="secondary">{{ project?.statistics?.total_tasks ?? 0 }}</Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-2">
                                <p class="text-sm text-muted-foreground">
                                    View, filter, and export tasks with their annotations and review results.
                                </p>
                                <Link :href="route('admin.projects.tasks.manage', project.id)">
                                <Button class="gap-2">
                                    <CheckSquare class="h-4 w-4" />
                                    View Tasks
                                </Button>
                                </Link>
                            </CardContent>
                        </Card>
                    </div>



                    <!-- Annotation Dimensions -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2">
                                    <Settings2 class="h-5 w-5" />
                                    Annotation Dimensions
                                    <Badge variant="secondary">{{ dimensions.length }}</Badge>
                                </CardTitle>
                                <Button v-if="isDimensionsIncomplete" @click="continueDimensionSetup" size="sm"
                                    class="gap-2">
                                    <Settings class="h-4 w-4" />
                                    Configure Dimensions
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <!-- No Dimensions -->
                            <div v-if="dimensions.length === 0"
                                class="text-center py-8 border-2 border-dashed border-amber-300 rounded-lg bg-amber-50">
                                <Settings2 class="mx-auto h-8 w-8 text-amber-500 mb-4" />
                                <h3 class="text-lg font-medium text-amber-900 mb-2">No Dimensions Configured</h3>
                                <p class="text-amber-700 mb-4">
                                    You need to configure annotation dimensions before this project can be used.
                                </p>
                                <Button @click="continueDimensionSetup" class="gap-2">
                                    <Settings class="h-4 w-4" />
                                    Configure Dimensions Now
                                </Button>
                            </div>

                            <!-- Dimensions Grid -->
                            <div v-else class="grid gap-4 sm:grid-cols-2">
                                <div v-for="dimension in dimensions" :key="dimension.id"
                                    class="space-y-3 rounded-lg border bg-muted/20 p-4">
                                    <div class="flex items-start justify-between">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <p class="font-medium">{{ dimension.name }}</p>
                                                <Badge v-if="dimension.is_required" variant="outline" class="text-xs">
                                                    Required
                                                </Badge>
                                            </div>
                                            <p v-if="dimension.description" class="text-sm text-muted-foreground">
                                                {{ dimension.description }}
                                            </p>
                                        </div>

                                        <Badge
                                            :variant="dimension.dimension_type === 'categorical' ? 'default' : 'secondary'">
                                            {{ dimension.dimension_type === 'categorical' ? 'Categorical' : 'Scale' }}
                                        </Badge>
                                    </div>

                                    <div v-if="dimension.dimension_type === 'categorical'" class="flex flex-wrap gap-1">
                                        <Badge v-for="value in dimension.values.slice(0, 3)" :key="value.id"
                                            variant="outline" class="text-xs">
                                            {{ value.label }}
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
                        </CardContent>
                    </Card>

                    <!-- Guidelines -->
                    <Card v-if="project.annotation_guidelines">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Tag class="h-5 w-5" />
                                Annotation Guidelines
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="rounded-md bg-muted/30 p-4">
                                <p class="whitespace-pre-wrap text-sm leading-relaxed">
                                    {{ project.annotation_guidelines }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right: Sidebar -->
                <div class="space-y-6 lg:col-span-4 lg:sticky lg:top-6 self-start">
                    <!-- Setup Reminder (only if incomplete) -->
                    <Card v-if="isDimensionsIncomplete" class="border-amber-200 bg-amber-50">
                        <CardHeader>
                            <CardTitle class="text-amber-800">Complete Setup</CardTitle>
                        </CardHeader>
                        <CardContent class="text-amber-700">
                            <p class="text-sm mb-4">
                                Your project needs annotation dimensions configured before you can:
                            </p>
                            <ul class="text-sm space-y-1 list-disc list-inside mb-4">
                                <li>Add team members</li>
                                <li>Upload audio files</li>
                                <li>Create annotation tasks</li>
                                <li>Activate the project</li>
                            </ul>
                        </CardContent>
                        <CardFooter>
                            <Button @click="continueDimensionSetup" class="w-full gap-2">
                                <Settings class="h-4 w-4" />
                                Configure Dimensions
                            </Button>
                        </CardFooter>
                    </Card>

                    <!-- Project Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Project Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Owner</p>
                                <p class="text-sm">{{ project.owner.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ project.owner.email }}</p>
                            </div>

                            <Separator />

                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Time Limits</p>
                                <div class="space-y-1">
                                    <p class="text-sm">Task: {{ project.task_time_minutes }}min</p>
                                    <p class="text-sm">Review: {{ project.review_time_minutes }}min</p>
                                </div>
                            </div>

                            <Separator />

                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Performance</p>
                                <div class="space-y-1">
                                    <p class="text-sm">Task Skips: {{ project.statistics.task_skips }}</p>
                                    <p class="text-sm">Review Skips: {{ project.statistics.review_skips }}</p>
                                </div>
                            </div>
                        </CardContent>
                        <CardFooter class="flex-col gap-2">
                            <Button v-if="projectStatus !== 'archived'" variant="outline"
                                class="w-full gap-2 border-amber-200 text-amber-700 hover:bg-amber-50"
                                @click="archiveProject" :disabled="isDimensionsIncomplete">
                                <Archive class="h-4 w-4" />
                                Archive Project
                            </Button>

                            <Button v-else variant="outline" class="w-full gap-2" @click="restoreProject">
                                <Archive class="h-4 w-4" />
                                Restore Project
                            </Button>

                            <Button variant="outline"
                                class="w-full gap-2 border-destructive text-destructive hover:bg-destructive hover:text-destructive-foreground"
                                @click="openDeleteProjectDialog">
                                <Trash class="h-4 w-4" />
                                Delete Project
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </div>


        <ConfirmDialog :open="showDeleteProjectDialog" @update:open="showDeleteProjectDialog = $event"
            title="Delete Project"
            description="Are you sure you want to delete this project? This action cannot be undone."
            confirm-text="Delete Project" cancel-text="Cancel" confirm-variant="destructive" @confirm="deleteProject" />
    </AppLayout>
</template>

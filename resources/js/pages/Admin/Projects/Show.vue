<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ArrowLeft, Calendar, Tag, File, Users, BarChart, Edit, Archive, Trash } from 'lucide-vue-next';
import AddMemberDialog from '@/components/projects/AddMemberDialog.vue';
import EditMemberDialog from '@/components/projects/EditMemberDialog.vue';
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

interface AnnotationDimension {
    id: number;
    name: string;
    description: string;
    dimension_type: string;
    scale_min: number | null;
    scale_max: number | null;
    scale_labels: any;
    form_template: any;
    is_required: boolean;
    display_order: number;
}

interface FormLabel {
    id: number;
    label_name: string;
    label_value: string;
    description: string;
    suggested_values: any;
    display_order: number;
}

interface Project {
    id: number;
    name: string;
    description: string;
    status: 'draft' | 'active' | 'paused' | 'completed' | 'archived';
    project_type: 'audio' | 'image' | 'video';
    ownership_type: 'self_created' | 'admin_assigned';
    quality_threshold: number;
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
    form_labels: FormLabel[];
    statistics: {
        total_tasks: number;
        completed_tasks: number;
        pending_tasks: number;
        approved_tasks: number;
        total_media_files: number;
        media_breakdown: Record<string, number>;
        completion_percentage: number;
        team_size: number;
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    project: Project;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/admin/projects' },
    { title: props.project.name, href: `/admin/projects/${props.project.id}` },
];

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

// Member management
const showAddMemberDialog = ref(false);
const showEditMemberDialog = ref(false);
const selectedMember = ref<Member | null>(null);

// Confirmation dialogs
const showDeleteProjectDialog = ref(false);
const showDeleteMemberDialog = ref(false);
const memberToDelete = ref<number | null>(null);

// Local state management
const localMembers = ref<Member[]>(props.project.members);
const projectStatus = ref<string>(props.project.status);

// Computed property for members to display
const displayMembers = computed(() => {
    return localMembers.value;
});

const editMember = (member: Member) => {
    selectedMember.value = member;
    showEditMemberDialog.value = true;
};

// Handle member update
const onMemberUpdated = (updatedMember: { id: number, role: string, workload_limit: number | null, is_active: boolean }) => {
    // Find and update the member in our local state
    const index = localMembers.value.findIndex(m => m.id === updatedMember.id);
    if (index !== -1) {
        localMembers.value[index] = {
            ...localMembers.value[index],
            role: updatedMember.role,
            workload_limit: updatedMember.workload_limit,
            is_active: updatedMember.is_active
        };
    }
};

// Handle member addition
const onMemberAdded = (newMember: Member) => {
    if (newMember) {
        localMembers.value.push(newMember);
    }
};

// Handle member removal
const removeMember = (memberId: number) => {
    const index = localMembers.value.findIndex(m => m.id === memberId);
    if (index !== -1) {
        localMembers.value.splice(index, 1);
    }
};

// Handle project status changes (archive/restore)
const archiveProject = () => {
    router.patch(route('admin.projects.archive', props.project.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            // Update local state on successful archive
            projectStatus.value = 'archived';
        },
        onError: (errors) => {
            console.error('Failed to archive project:', errors);
        }
    });
};

const restoreProject = () => {
    router.patch(route('admin.projects.restore', props.project.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            // Update local state on successful restore
            projectStatus.value = 'active';
        },
        onError: (errors) => {
            console.error('Failed to restore project:', errors);
        }
    });
};

const deleteProject = () => {
    if (confirm('Are you sure you want to delete this project? This action cannot be undone. All project data will be permanently deleted.')) {
        router.delete(route('admin.projects.destroy', props.project.id), {
            onError: (errors) => {
                console.error('Failed to delete project:', errors);
            }
        });
    }
};

// Handle member removal with router
const handleRemoveMember = (memberId: number) => {
    if (confirm('Remove this team member?')) {
        router.delete(route('admin.projects.members.destroy', [props.project.id, memberId]), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                removeMember(memberId);
            },
            onError: (errors) => {
                console.error('Failed to remove member:', errors);
            }
        });
    }
};
</script>

<template>
    <Head :title="project.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 overflow-x-auto">
            <!-- Header with Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('admin.projects.index')">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Projects
                        </Button>
                    </Link>

                    <Badge :class="getStatusColor(projectStatus)" class="uppercase">
                        {{ projectStatus }}
                    </Badge>
                </div>

                <div class="flex items-center gap-2 self-end sm:self-auto">
                    <Link :href="route('admin.projects.edit', project.id)">
                        <Button variant="outline" size="sm" class="gap-1">
                            <Edit class="h-4 w-4" />
                            Edit
                        </Button>
                    </Link>

                    <Button 
                        v-if="projectStatus !== 'archived'" 
                        variant="outline" 
                        size="sm" 
                        class="gap-1 border-amber-500 text-amber-700"
                        @click="archiveProject"
                    >
                        <Archive class="h-4 w-4" />
                        Archive
                    </Button>

                    <Button 
                        v-else 
                        variant="outline" 
                        size="sm" 
                        class="gap-1 border-blue-500 text-blue-700"
                        @click="restoreProject"
                    >
                        <Archive class="h-4 w-4" />
                        Restore
                    </Button>

                    <Button 
                        variant="outline" 
                        size="sm" 
                        class="gap-1 border-red-500 text-red-700"
                        @click="deleteProject"
                    >
                        <Trash class="h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <!-- Project Title & Info -->
            <div>
                <h1 class="text-3xl font-bold mb-2">{{ project.name }}</h1>
                <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    <div class="flex items-center gap-1">
                        <span class="capitalize">{{ project.project_type }}</span>
                    </div>

                    <div v-if="project.deadline" class="flex items-center gap-1">
                        <Calendar class="h-4 w-4" />
                        <span>Deadline: {{ new Date(project.deadline).toLocaleDateString() }}</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <Users class="h-4 w-4" />
                        <span>{{ project.statistics.team_size }} team members</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <File class="h-4 w-4" />
                        <span>Created: {{ new Date(project.created_at).toLocaleDateString() }}</span>
                    </div>
                </div>
            </div>

            <!-- Project Description -->
            <Card v-if="project.description">
                <CardHeader>
                    <CardTitle>Description</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="whitespace-pre-line">{{ project.description }}</p>
                </CardContent>
            </Card>

            <!-- Project Statistics -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BarChart class="h-5 w-5" />
                        Project Statistics
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-muted/50 p-4 rounded-lg">
                            <div class="text-2xl font-bold">{{ project.statistics.total_tasks }}</div>
                            <div class="text-sm text-muted-foreground">Total Tasks</div>
                        </div>

                        <div class="bg-muted/50 p-4 rounded-lg">
                            <div class="text-2xl font-bold">{{ project.statistics.completed_tasks }}</div>
                            <div class="text-sm text-muted-foreground">Completed Tasks</div>
                        </div>

                        <div class="bg-muted/50 p-4 rounded-lg">
                            <div class="text-2xl font-bold">{{ project.statistics.total_media_files }}</div>
                            <div class="text-sm text-muted-foreground">Media Files</div>
                        </div>

                        <div class="bg-muted/50 p-4 rounded-lg">
                            <div class="text-2xl font-bold">{{ project.statistics.completion_percentage }}%</div>
                            <div class="text-sm text-muted-foreground">Completion</div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Project Team -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span>Team Members</span>
                        <Button size="sm" variant="outline" @click="showAddMemberDialog = true">Add Member</Button>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Assigned</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="member in displayMembers" :key="member.id">
                                <TableCell>{{ member.user.name }}</TableCell>
                                <TableCell>{{ member.user.email }}</TableCell>
                                <TableCell class="capitalize">{{ member.role }}</TableCell>
                                <TableCell>
                                    <Badge :variant="member.is_active ? 'default' : 'secondary'">
                                        {{ member.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ new Date(member.assigned_at).toLocaleDateString() }}</TableCell>
                                <TableCell>
                                    <div class="flex gap-2">
                                        <Button variant="ghost" size="sm"
                                            @click="() => editMember(member)">Edit</Button>
                                        <Button 
                                            v-if="member.user.id !== project.owner.id"
                                            variant="ghost" 
                                            size="sm" 
                                            class="text-red-600"
                                            @click="() => handleRemoveMember(member.id)"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Annotation Guidelines -->
            <Card v-if="project.annotation_guidelines">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Tag class="h-5 w-5" />
                        Annotation Guidelines
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="prose prose-sm max-w-none dark:prose-invert">
                        <div class="whitespace-pre-line">{{ project.annotation_guidelines }}</div>
                    </div>
                </CardContent>
            </Card>

            <!-- Project Owner Info -->
            <Card>
                <CardHeader>
                    <CardTitle>Project Management</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <h3 class="font-medium mb-2">Owner</h3>
                            <div class="text-sm mb-1">{{ project.owner.name }}</div>
                            <div class="text-sm text-muted-foreground">{{ project.owner.email }}</div>
                        </div>

                        <div>
                            <h3 class="font-medium mb-2">Created By</h3>
                            <div class="text-sm mb-1">{{ project.creator.name }}</div>
                            <div class="text-sm text-muted-foreground">{{ project.creator.email }}</div>
                        </div>

                        <div v-if="project.assigner">
                            <h3 class="font-medium mb-2">Assigned By</h3>
                            <div class="text-sm mb-1">{{ project.assigner.name }}</div>
                            <div class="text-sm text-muted-foreground">{{ project.assigner.email }}</div>
                        </div>

                        <div>
                            <h3 class="font-medium mb-2">Quality Threshold</h3>
                            <div class="text-sm">{{ (project.quality_threshold * 100).toFixed(0) }}%</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Use separate dialog components -->
        <AddMemberDialog 
          :project-id="project.id"
          :open="showAddMemberDialog"
          @update:open="showAddMemberDialog = $event"
          @member-added="onMemberAdded"
        />
        
        <EditMemberDialog
          :project-id="project.id"
          :member="selectedMember"
          :open="showEditMemberDialog"
          @update:open="showEditMemberDialog = $event"
          @member-updated="onMemberUpdated"
        />
    </AppLayout>
</template>
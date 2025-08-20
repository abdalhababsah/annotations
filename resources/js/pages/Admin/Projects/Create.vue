<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';

interface ProjectOwner {
  id: number;
  name: string;
  email: string;
}

interface Props {
  projectOwners: ProjectOwner[];
  userRole: 'system_admin' | 'project_owner' | 'user';
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Projects', href: '/projects' },
  { title: 'Create Project', href: '/projects/create' },
];

const form = useForm({
  name: '',
  description: '',
  project_type: '',
  annotation_guidelines: '',
  deadline: '',
  owner_id: null as number | null,
});

const submit = () => {
  form.post(route('admin.projects.store'));
};
</script>

<template>
    <Head title="Create Project" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 overflow-x-auto">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Create New Project</h1>
                    <p class="text-muted-foreground">
                        Set up a new annotation project
                    </p>
                </div>
                <Link :href="route('admin.projects.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="w-full">
                <Card>
                    <CardHeader>
                        <CardTitle>Project Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Project Name -->
                            <div class="space-y-2">
                                <Label for="name">Project Name *</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="Enter project name"
                                    class="w-full"
                                    :class="{ 'border-red-500': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <!-- Project Type -->
                            <div class="space-y-2">
                                <Label for="project_type">Project Type *</Label>
                                <Select v-model="form.project_type">
                                    <SelectTrigger class="w-full" :class="{ 'border-red-500': form.errors.project_type }">
                                        <SelectValue placeholder="Select project type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="audio">Audio - Speech, music, sound annotation</SelectItem>
                                        <SelectItem value="image">Image - Object detection, classification</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.project_type" class="text-sm text-red-600">
                                    {{ form.errors.project_type }}
                                </p>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Describe your project..."
                                rows="3"
                                class="w-full"
                                :class="{ 'border-red-500': form.errors.description }"
                            />
                            <p v-if="form.errors.description" class="text-sm text-red-600">
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

                            <!-- Deadline -->
                            <div class="space-y-2">
                                <Label for="deadline">Deadline</Label>
                                <Input
                                    id="deadline"
                                    v-model="form.deadline"
                                    type="date"
                                    class="w-full"
                                    :min="new Date().toISOString().split('T')[0]"
                                    :class="{ 'border-red-500': form.errors.deadline }"
                                />
                                <p v-if="form.errors.deadline" class="text-sm text-red-600">
                                    {{ form.errors.deadline }}
                                </p>
                            </div>
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
                                :class="{ 'border-red-500': form.errors.annotation_guidelines }"
                            />
                            <p class="text-sm text-muted-foreground">
                                Detailed instructions that will help annotators understand what to look for
                            </p>
                            <p v-if="form.errors.annotation_guidelines" class="text-sm text-red-600">
                                {{ form.errors.annotation_guidelines }}
                            </p>
                        </div>

                        <!-- Error Message -->
                        <div v-if="Object.keys(form.errors).length > 0" class="p-4 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-sm text-red-600">There are errors in the form. Please check the fields above.</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-3 pt-4">
                            <Button type="submit" :disabled="form.processing" class="flex items-center gap-2">
                                <Save class="h-4 w-4" />
                                {{ form.processing ? 'Creating...' : 'Create Project' }}
                            </Button>
                            <Link :href="route('admin.projects.index')">
                                <Button variant="outline" type="button">
                                    Cancel
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
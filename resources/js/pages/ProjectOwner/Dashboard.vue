<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '../../components/PlaceholderPattern.vue';

const props = defineProps({
  projects: Array
});

const showNewProjectDialog = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Project Owner Dashboard',
    href: '/projects/dashboard',
  },
];
</script>

<template>
  <Head title="Project Owner Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="mb-4 flex justify-between items-center">
        <h2 class="text-xl font-bold">Your Projects</h2>
        <Button @click="showNewProjectDialog = true">Create New Project</Button>
      </div>
      
      <div v-if="projects && projects.length > 0" class="grid auto-rows-min gap-4 md:grid-cols-3">
        <Card v-for="project in projects" :key="project.id" class="transition-shadow hover:shadow-md">
          <CardHeader>
            <CardTitle>{{ project.name }}</CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-muted-foreground mb-2">{{ project.description }}</p>
            <p class="text-sm">Team members: {{ project.project_memberships?.length || 0 }}</p>
          </CardContent>
          <CardFooter class="flex justify-between">
            <Button variant="outline" size="sm">View Details</Button>
            <Button variant="secondary" size="sm">Manage Team</Button>
          </CardFooter>
        </Card>
      </div>
      
      <div v-else class="relative flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6 text-center">
        <p class="text-muted-foreground mb-6">You don't have any projects yet.</p>
        <Button @click="showNewProjectDialog = true">Create Your First Project</Button>
      </div>

      <div class="relative min-h-[30vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border p-6">
        <h2 class="text-xl font-bold mb-4">Project Analytics</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="border border-sidebar-border/70 rounded-lg p-4">
            <h3 class="text-lg font-medium mb-2">Team Activity</h3>
            <p class="text-muted-foreground">No recent activities to display</p>
          </div>
          <div class="border border-sidebar-border/70 rounded-lg p-4">
            <h3 class="text-lg font-medium mb-2">Project Progress</h3>
            <p class="text-muted-foreground">No active projects to show progress</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

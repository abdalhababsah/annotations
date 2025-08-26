<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Admin Dashboard',
    href: '/admin/dashboard',
  },
];
</script>

<template>
  <Head title="Admin Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>Users</CardTitle>
          </CardHeader>
          <CardContent>
            <p>Manage system users</p>
          </CardContent>
          <CardFooter>
            <Button>
              <Link href="#">
                Manage Users
              </Link>
            </Button>
          </CardFooter>
        </Card>
        
        <Card>
          <CardHeader>
            <CardTitle>Projects</CardTitle>
          </CardHeader>
          <CardContent>
            <p>Manage all projects</p>
          </CardContent>
          <CardFooter>
            <Button>
              <Link :href="route('admin.projects.index')">
                View Projects
              </Link>
            </Button>
          </CardFooter>
        </Card>
        
        <Card>
          <CardHeader>
            <CardTitle>System Settings</CardTitle>
          </CardHeader>
          <CardContent>
            <p>Configure system settings</p>
          </CardContent>
          <CardFooter>
            <Button>
              <Link href="#">
                Settings
              </Link>
            </Button>
          </CardFooter>
        </Card>
      </div>

      <div class="relative min-h-[50vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border p-6">
        <h2 class="text-xl font-bold mb-4">System Overview</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="border border-sidebar-border/70 rounded-lg p-4">
            <h3 class="text-lg font-medium mb-2">Recent Activity</h3>
            <p class="text-muted-foreground">No recent activities to display</p>
          </div>
          <div class="border border-sidebar-border/70 rounded-lg p-4">
            <h3 class="text-lg font-medium mb-2">System Health</h3>
            <p class="text-muted-foreground">All systems operational</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

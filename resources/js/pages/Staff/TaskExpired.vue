<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { 
  Clock,
  AlertTriangle,
  LayoutDashboard,
  ArrowRight
} from 'lucide-vue-next'

const props = defineProps<{
  project: { id: number; name: string }
  task?: { id: number }
}>()

const backToDashboard = () => router.get(route('staff.dashboard'))
const tryNextTask = () => router.get(route('staff.attempt.next', props.project.id))
</script>

<template>
  <AppLayout :breadcrumbs="[{ title: 'Task Expired', href: '#' }]">
    <Head title="Task Expired" />

    <div class="container mx-auto p-6 flex items-center justify-center min-h-[60vh]">
      <Card class="w-full max-w-2xl overflow-hidden">
        <CardHeader class="pb-2">
          <div class="flex items-center gap-4">
            <div class="rounded-full p-3 bg-amber-100 ring-2 ring-amber-200">
              <Clock class="h-8 w-8 text-amber-600" />
            </div>
            <div class="flex flex-col">
              <CardTitle class="text-2xl text-amber-800">Task Expired</CardTitle>
              <div class="text-sm text-muted-foreground">
                Project • <span class="font-medium">{{ project.name }}</span>
              </div>
            </div>
          </div>
        </CardHeader>

        <CardContent class="space-y-6">
          <!-- Alert explaining what happened -->
          <Alert class="border-amber-200 bg-amber-50">
            <AlertTriangle class="h-5 w-5 text-amber-600" />
            <AlertDescription class="text-amber-800">
              The time limit for this task has expired. The task has been automatically returned to the queue 
              and may be assigned to another team member.
            </AlertDescription>
          </Alert>

          <!-- Task info if available -->
          <div v-if="task" class="flex items-center gap-3 p-4 bg-muted/30 rounded-lg">
            <Badge variant="outline" class="gap-1">
              <span class="font-medium">Task ID:</span>
              <span class="ml-1">#{{ task.id }}</span>
            </Badge>
          </div>

          <!-- Next steps info -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold">What happens next?</h3>
            <ul class="space-y-2 text-sm text-muted-foreground">
              <li class="flex items-start gap-2">
                <span class="w-1.5 h-1.5 bg-muted-foreground rounded-full mt-2 flex-shrink-0"></span>
                <span>The task has been returned to the available task queue</span>
              </li>
              <li class="flex items-start gap-2">
                <span class="w-1.5 h-1.5 bg-muted-foreground rounded-full mt-2 flex-shrink-0"></span>
                <span>You can continue working on other available tasks</span>
              </li>
              <li class="flex items-start gap-2">
                <span class="w-1.5 h-1.5 bg-muted-foreground rounded-full mt-2 flex-shrink-0"></span>
                <span>Any draft work on this task was not saved due to expiration</span>
              </li>
            </ul>
          </div>

          <!-- Action buttons -->
          <div class="flex flex-col gap-3 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm text-muted-foreground">
              You can try to get another task or return to your dashboard.
            </div>

            <div class="flex gap-2">
              <Button variant="outline" class="gap-2" @click="tryNextTask">
                <ArrowRight class="h-4 w-4" />
                <span>Try Next Task</span>
              </Button>
              <Button class="gap-2" @click="backToDashboard">
                <LayoutDashboard class="h-4 w-4" />
                <span>Return to Dashboard</span>
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
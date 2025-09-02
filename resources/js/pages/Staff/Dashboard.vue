<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { FolderOpen, Play, CheckCircle2, AlertTriangle } from 'lucide-vue-next'

type ProjectCard = {
  id: number
  name: string
  status: string
  roles: { annotator: boolean; reviewer: boolean }
  has_active_batches: boolean
  /** Provided by controller to reflect true queue availability */
  can_attempt: boolean
  can_review: boolean
}

type AnnotatorStats = {
  today_attempted: number
  today_submitted: number
  today_time_seconds: number
  today_avg_seconds: number
  week_attempted: number
  month_attempted: number
  all_time_submitted: number
}

type ReviewerStats = {
  today_started: number
  today_approved: number
  today_time_seconds: number
  today_avg_seconds: number
  week_approved: number
  month_approved: number
  all_time_approved: number
}

const props = defineProps<{
  projects: ProjectCard[]
  stats: {
    has_annotator: boolean
    has_reviewer: boolean
    annotator: AnnotatorStats | null
    reviewer: ReviewerStats | null
  }
}>()

const hasAnyProjects = computed(() => props.projects.length > 0)

// Simple mm:ss
const fmt = (secs?: number | null) => {
  if (!secs || secs <= 0) return '00:00'
  const m = Math.floor(secs / 60)
  const s = secs % 60
  return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
}

// Jump to next task/review
const goAttemptNext = (projectId: number) => {
  router.get(route('staff.attempt.next', projectId))
}
const goReviewNext = (projectId: number) => {
  router.get(route('staff.review.next', projectId))
}
</script>

<template>
  <AppLayout :breadcrumbs="[{ title: 'Staff', href: '/staff/dashboard' }]">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">

      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Dashboard</h1>
      </div>

      <!-- ====================== STATS (FULL-WIDTH CARDS) ====================== -->
      <div v-if="stats.has_annotator" class="w-full">
        <Card class="w-full overflow-hidden border-none shadow-lg bg-gradient-to-r from-indigo-50 to-white">
          <CardHeader class="pb-2">
            <CardTitle class="text-xl">Today's Attempts</CardTitle>
          </CardHeader>
          <CardContent class="pt-0">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
              <div>
                <div class="text-5xl font-bold leading-none">
                  {{ stats.annotator?.today_attempted ?? 0 }}
                </div>
                <div class="text-muted-foreground mt-2">tasks started today</div>
              </div>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 w-full md:w-auto">
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-muted-foreground">Submitted today</div>
                  <div class="text-2xl font-semibold">{{ stats.annotator?.today_submitted ?? 0 }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-muted-foreground">Avg. time today</div>
                  <div class="text-2xl font-semibold">{{ fmt(stats.annotator?.today_avg_seconds ?? 0) }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-muted-foreground">Week to date</div>
                  <div class="text-2xl font-semibold">{{ stats.annotator?.week_attempted ?? 0 }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-muted-foreground">Month to date</div>
                  <div class="text-2xl font-semibold">{{ stats.annotator?.month_attempted ?? 0 }}</div>
                </div>
              </div>
            </div>
            <div class="mt-4 text-sm text-muted-foreground">
              All-time submissions: <strong>{{ stats.annotator?.all_time_submitted ?? 0 }}</strong> •
              Time spent today: <strong>{{ fmt(stats.annotator?.today_time_seconds ?? 0) }}</strong>
            </div>
          </CardContent>
        </Card>
      </div>

      <div v-if="stats.has_reviewer" class="w-full">
        <Card class="w-full overflow-hidden border-none shadow-lg bg-gradient-to-r from-emerald-50 to-white">
          <CardHeader class="pb-2">
            <CardTitle class="text-xl">Today's Reviews</CardTitle>
          </CardHeader>
          <CardContent class="pt-0">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
              <div>
                <div class="text-5xl font-bold leading-none">
                  {{ stats.reviewer?.today_approved ?? 0 }}
                </div>
                <div class="text-muted-foreground mt-2">approved today</div>
              </div>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 w-full md:w-auto">
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-muted-foreground">Started today</div>
                  <div class="text-2xl font-semibold">{{ stats.reviewer?.today_started ?? 0 }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-muted-foreground">Avg. time today</div>
                  <div class="text-2xl font-semibold">{{ fmt(stats.reviewer?.today_avg_seconds ?? 0) }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-muted-foreground">Week to date</div>
                  <div class="text-2xl font-semibold">{{ stats.reviewer?.week_approved ?? 0 }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-muted-foreground">Month to date</div>
                  <div class="text-2xl font-semibold">{{ stats.reviewer?.month_approved ?? 0 }}</div>
                </div>
              </div>
            </div>
            <div class="mt-4 text-sm text-muted-foreground">
              All-time approved: <strong>{{ stats.reviewer?.all_time_approved ?? 0 }}</strong> •
              Time spent today: <strong>{{ fmt(stats.reviewer?.today_time_seconds ?? 0) }}</strong>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- ====================== PROJECT CARDS (FULL-WIDTH) ====================== -->
      <div class="mt-2">
        <h2 class="text-lg font-semibold mb-3">My Projects</h2>

        <Alert v-if="!hasAnyProjects" class="border-amber-200 bg-amber-50">
          <AlertTriangle class="h-4 w-4 text-amber-600" />
          <AlertDescription class="text-amber-800">
            You are not assigned to any active projects yet.
          </AlertDescription>
        </Alert>

        <div v-else class="space-y-4">
          <Card v-for="p in projects" :key="p.id" class="w-full overflow-hidden">
            <CardHeader>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="p-3 bg-primary/10 rounded-lg">
                    <FolderOpen class="h-6 w-6 text-primary" />
                  </div>
                  <div>
                    <CardTitle class="text-lg">{{ p.name }}</CardTitle>
                    <div class="mt-1 flex items-center gap-2">
                      <Badge v-if="p.roles.annotator" variant="secondary">Annotator</Badge>
                      <Badge v-if="p.roles.reviewer"  variant="secondary">Reviewer</Badge>
                    </div>
                    <div class="mt-2 text-sm text-muted-foreground">
                      <div v-if="p.roles.annotator && p.roles.reviewer">
                        Listen to audio clips, transcribe speech, and review other annotations for quality assurance
                      </div>
                      <div v-else-if="p.roles.annotator">
                        Listen to audio clips and transcribe speech content accurately
                      </div>
                      <div v-else-if="p.roles.reviewer">
                        Review and validate transcriptions created by annotators
                      </div>
                    </div>
                  </div>
                </div>
                <Badge :variant="p.has_active_batches ? 'default' : 'outline'">
                  {{ p.has_active_batches ? 'Active batches' : 'No active batches' }}
                </Badge>
              </div>
            </CardHeader>

            <CardContent class="flex items-center justify-end gap-2">
              <Button
                v-if="p.roles.annotator"
                class="gap-2"
                :disabled="!p.can_attempt"
                @click="goAttemptNext(p.id)"
                title="Get next task"
              >
                <Play class="h-4 w-4" /> Start Attempt
              </Button>

              <Button
                v-if="p.roles.reviewer"
                variant="outline"
                class="gap-2"
                :disabled="!p.can_review"
                @click="goReviewNext(p.id)"
                title="Get next review"
              >
                <CheckCircle2 class="h-4 w-4" /> Start Review
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
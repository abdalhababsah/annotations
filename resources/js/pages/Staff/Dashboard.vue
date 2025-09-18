<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { FolderOpen, Play, CheckCircle2, AlertTriangle, RotateCw, Clock, Info, FileText } from 'lucide-vue-next'

type RoleFlags = { annotator: boolean; reviewer: boolean }

type ContinueAttempt = { task_id: number; project_id: number; route: string } | null
type ContinueReview  = { review_id: number; project_id: number; route: string } | null

type ProjectCard = {
  id: number
  name: string
  description: string | null
  status: string
  project_type: 'annotation' | 'segmentation'
  task_time_minutes: number | null
  review_time_minutes: number | null
  annotation_guidelines: string | null
  roles: RoleFlags
  has_active_batches: boolean
  can_attempt: boolean
  can_review: boolean
  continue: {
    attempt: ContinueAttempt
    review: ContinueReview
  }
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

// If there is any "continue" (attempt or review) anywhere, disable all other "Start" buttons
const hasAnyGlobalContinue = computed(
  () => props.projects.some(p => !!p.continue?.attempt || !!p.continue?.review)
)

// Sort projects - those with continue tasks first
const sortedProjects = computed(() => {
  return [...props.projects].sort((a, b) => {
    const aHasContinue = !!(a.continue?.attempt || a.continue?.review)
    const bHasContinue = !!(b.continue?.attempt || b.continue?.review)
    
    if (aHasContinue && !bHasContinue) return -1
    if (!aHasContinue && bHasContinue) return 1
    return 0
  })
})

// Format minutes to readable time
const formatMinutes = (minutes?: number | null) => {
  if (!minutes || minutes <= 0) return 'Not set'
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`
}

// Format seconds to hh:mm:ss for large values, mm:ss for smaller values
const formatSeconds = (secs?: number | null) => {
  if (!secs || secs <= 0) return '00:00'
  
  const hours = Math.floor(secs / 3600)
  const minutes = Math.floor((secs % 3600) / 60)
  const seconds = secs % 60
  
  if (hours > 0) {
    return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
  }
  
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
}

// Get project type label
const getProjectTypeLabel = (type: string) => {
  return type === 'annotation' ? 'Audio Annotation' : 'Audio Segmentation'
}

// Get role badge text
const getRoleBadgeText = (p: ProjectCard) => {
  const badges = []
  if (p.roles.annotator) {
    badges.push(p.project_type === 'segmentation' ? 'Labeler' : 'Annotator')
  }
  if (p.roles.reviewer) {
    badges.push('Reviewer')
  }
  return badges
}

// Get project description with fallback
const getProjectDescription = (p: ProjectCard) => {
  if (p.description?.trim()) {
    return p.description.trim()
  }
  
  const isAnn = p.project_type === 'annotation'
  if (p.roles.annotator && p.roles.reviewer) {
    return isAnn 
      ? 'Annotate audio using configured dimensions, then review for quality.'
      : 'Label time-based segments in audio, then review boundaries and labels.'
  }
  if (p.roles.annotator) {
    return isAnn 
      ? 'Listen and assess audio clips against configured dimensions.'
      : 'Mark time ranges and assign correct labels to each segment.'
  }
  if (p.roles.reviewer) {
    return isAnn 
      ? 'Review submitted annotations for accuracy and consistency.'
      : 'Review labeled segments to verify boundaries and labels.'
  }
  return 'Audio processing project'
}

// Get color theme for project type
const getProjectTheme = (type: string) => {
  return type === 'annotation' 
    ? {
        gradient: 'from-blue-50 to-indigo-50',
        border: 'border-blue-200',
        icon: 'bg-blue-100 text-blue-600',
        badge: 'bg-blue-100 text-blue-700'
      }
    : {
        gradient: 'from-purple-50 to-pink-50', 
        border: 'border-purple-200',
        icon: 'bg-purple-100 text-purple-600',
        badge: 'bg-purple-100 text-purple-700'
      }
}

// Action handlers
const goAttemptNext = (projectId: number) => {
  router.get(route('staff.attempt.next', projectId))
}
const goReviewNext = (projectId: number) => {
  router.get(route('staff.review.next', projectId))
}
const goContinue = (href: string) => {
  router.get(href)
}
</script>

<template>
  <AppLayout :breadcrumbs="[{ title: 'Staff', href: '/staff/dashboard' }]">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
      </div>

      <!-- ====================== STATS ====================== -->
      <div v-if="stats.has_annotator" class="w-full">
        <Card class="w-full overflow-hidden border-none shadow-lg bg-gradient-to-r from-indigo-50 to-white">
          <CardHeader class="pb-2">
            <CardTitle class="text-xl text-gray-900">Today's Attempts</CardTitle>
          </CardHeader>
          <CardContent class="pt-0">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
              <div>
                <div class="text-5xl font-bold leading-none text-indigo-600">
                  {{ stats.annotator?.today_attempted ?? 0 }}
                </div>
                <div class="text-gray-600 mt-2">tasks started today</div>
              </div>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 w-full md:w-auto">
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-gray-600">Submitted today</div>
                  <div class="text-2xl font-semibold text-gray-900">{{ stats.annotator?.today_submitted ?? 0 }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-gray-600">Avg. time today</div>
                  <div class="text-2xl font-semibold text-gray-900">{{ formatSeconds(stats.annotator?.today_avg_seconds ?? 0) }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-gray-600">Week to date</div>
                  <div class="text-2xl font-semibold text-gray-900">{{ stats.annotator?.week_attempted ?? 0 }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-gray-600">Month to date</div>
                  <div class="text-2xl font-semibold text-gray-900">{{ stats.annotator?.month_attempted ?? 0 }}</div>
                </div>
              </div>
            </div>
            <div class="mt-4 text-sm text-gray-600">
              All-time submissions: <strong class="text-gray-900">{{ stats.annotator?.all_time_submitted ?? 0 }}</strong> •
              Time spent today: <strong class="text-gray-900">{{ formatSeconds(stats.annotator?.today_time_seconds ?? 0) }}</strong>
            </div>
          </CardContent>
        </Card>
      </div>

      <div v-if="stats.has_reviewer" class="w-full">
        <Card class="w-full overflow-hidden border-none shadow-lg bg-gradient-to-r from-emerald-50 to-white">
          <CardHeader class="pb-2">
            <CardTitle class="text-xl text-gray-900">Today's Reviews</CardTitle>
          </CardHeader>
          <CardContent class="pt-0">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
              <div>
                <div class="text-5xl font-bold leading-none text-emerald-600">
                  {{ stats.reviewer?.today_approved ?? 0 }}
                </div>
                <div class="text-gray-600 mt-2">approved today</div>
              </div>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 w-full md:w-auto">
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-gray-600">Started today</div>
                  <div class="text-2xl font-semibold text-gray-900">{{ stats.reviewer?.today_started ?? 0 }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-gray-600">Avg. time today</div>
                  <div class="text-2xl font-semibold text-gray-900">{{ formatSeconds(stats.reviewer?.today_avg_seconds ?? 0) }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-gray-600">Week to date</div>
                  <div class="text-2xl font-semibold text-gray-900">{{ stats.reviewer?.week_approved ?? 0 }}</div>
                </div>
                <div class="rounded-xl border bg-white/70 p-4">
                  <div class="text-sm text-gray-600">Month to date</div>
                  <div class="text-2xl font-semibold text-gray-900">{{ stats.reviewer?.month_approved ?? 0 }}</div>
                </div>
              </div>
            </div>
            <div class="mt-4 text-sm text-gray-600">
              All-time approved: <strong class="text-gray-900">{{ stats.reviewer?.all_time_approved ?? 0 }}</strong> •
              Time spent today: <strong class="text-gray-900">{{ formatSeconds(stats.reviewer?.today_time_seconds ?? 0) }}</strong>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- ====================== PROJECT CARDS ====================== -->
      <div class="mt-2">
        <h2 class="text-lg font-semibold mb-3 text-gray-900">My Projects</h2>

        <Alert v-if="!hasAnyProjects" class="border-amber-200 bg-amber-50">
          <AlertTriangle class="h-4 w-4 text-amber-600" />
          <AlertDescription class="text-amber-800">
            You are not assigned to any active projects yet.
          </AlertDescription>
        </Alert>

        <div v-else class="space-y-4">
          <Card 
            v-for="p in sortedProjects" 
            :key="p.id" 
            class="w-full overflow-hidden transition-all duration-200 hover:shadow-lg"
            :class="[
              // Only add color background and border for projects with continue tasks
              (p.continue?.attempt || p.continue?.review) 
                ? [
                    getProjectTheme(p.project_type).border,
                    `bg-gradient-to-r ${getProjectTheme(p.project_type).gradient}`,
                    'ring-2 ring-green-200 shadow-lg'
                  ]
                : 'bg-white border-gray-200'
            ]"
          >
            <CardHeader class="pb-4">
              <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-4 flex-1 min-w-0">
                  <div 
                    class="p-3 rounded-lg flex-shrink-0"
                    :class="getProjectTheme(p.project_type).icon"
                  >
                    <FolderOpen class="h-6 w-6" />
                  </div>
                  
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2">
                      <CardTitle class="text-lg text-gray-900">{{ p.name }}</CardTitle>
                      <!-- No tasks available message next to project name -->
                      <Badge
                        v-if="!p.continue.attempt && !p.continue.review && !p.can_attempt && !p.can_review"
                        variant="secondary"
                        class="text-xs bg-gray-100 text-gray-600 border-gray-300"
                      >
                        <Info class="h-3 w-3 mr-1" />
                        No tasks available
                      </Badge>
                    </div>
                    
                    <!-- Project Type & Role Badges -->
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                      <Badge 
                        variant="outline" 
                        class="capitalize border-0 font-medium"
                        :class="getProjectTheme(p.project_type).badge"
                      >
                        {{ getProjectTypeLabel(p.project_type) }}
                      </Badge>
                      <Badge 
                        v-for="roleBadge in getRoleBadgeText(p)" 
                        :key="roleBadge"
                        variant="secondary"
                        class="bg-gray-100 text-gray-700"
                      >
                        {{ roleBadge }}
                      </Badge>
                    </div>

                    <!-- Description -->
                    <div class="text-sm text-gray-700 mb-3 leading-relaxed">
                      {{ getProjectDescription(p) }}
                    </div>

                    <!-- Time Information -->
                    <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600">
                      <div v-if="p.roles.annotator && p.task_time_minutes" class="flex items-center gap-1">
                        <Clock class="h-3 w-3" />
                        <span>Task time: {{ formatMinutes(p.task_time_minutes) }}</span>
                      </div>
                      <div v-if="p.roles.reviewer && p.review_time_minutes" class="flex items-center gap-1">
                        <Clock class="h-3 w-3" />
                        <span>Review time: {{ formatMinutes(p.review_time_minutes) }}</span>
                      </div>
                      <div v-if="p.annotation_guidelines" class="flex items-center gap-1">
                        <FileText class="h-3 w-3" />
                        <span>Guidelines available</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="flex flex-col items-end gap-2 flex-shrink-0">
                  <Badge 
                    :variant="p.has_active_batches ? 'default' : 'outline'"
                    class="text-xs"
                  >
                    {{ p.has_active_batches ? 'Active batches' : 'No active batches' }}
                  </Badge>
                </div>
              </div>
            </CardHeader>

            <CardContent class="pt-0 pb-4">
              <div class="flex flex-wrap items-center justify-end gap-2">
                <!-- ===== CONTINUE / START ATTEMPT ===== -->
                <template v-if="p.roles.annotator">
                  <!-- Continue Attempt Button -->
                  <Button
                    v-if="p.continue.attempt"
                    class="gap-2 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white border-0 shadow-md"
                    @click="goContinue(p.continue.attempt.route)"
                    :title="p.project_type === 'segmentation' ? 'Resume labeling task' : 'Resume annotation task'"
                  >
                    <RotateCw class="h-4 w-4" />
                    Continue {{ p.project_type === 'segmentation' ? 'Labeling' : 'Attempt' }}
                  </Button>

                  <!-- Start Attempt Button (always show but disable if there are any continue tasks globally) -->
                  <Button
                    v-else
                    class="gap-2"
                    :disabled="!p.can_attempt || hasAnyGlobalContinue"
                    @click="goAttemptNext(p.id)"
                    :title="hasAnyGlobalContinue 
                      ? 'Complete active tasks first before starting new ones'
                      : (p.project_type === 'segmentation' ? 'Get next labeling task' : 'Get next annotation task')"
                  >
                    <Play class="h-4 w-4" />
                    {{ p.project_type === 'segmentation' ? 'Start Labeling' : 'Start Attempt' }}
                  </Button>
                </template>

                <!-- ===== CONTINUE / START REVIEW ===== -->
                <template v-if="p.roles.reviewer">
                  <!-- Continue Review Button -->
                  <Button
                    v-if="p.continue.review"
                    variant="outline"
                    class="gap-2 border-green-200 bg-green-50 text-green-700 hover:bg-green-100"
                    @click="goContinue(p.continue.review.route)"
                    :title="p.project_type === 'segmentation' ? 'Resume label review' : 'Resume annotation review'"
                  >
                    <RotateCw class="h-4 w-4" />
                    Continue {{ p.project_type === 'segmentation' ? 'Label Review' : 'Review' }}
                  </Button>

                  <!-- Start Review Button (always show but disable if there are any continue tasks globally) -->
                  <Button
                    v-else
                    variant="outline"
                    class="gap-2"
                    :disabled="!p.can_review || hasAnyGlobalContinue"
                    @click="goReviewNext(p.id)"
                    :title="hasAnyGlobalContinue 
                      ? 'Complete active tasks first before starting new ones'
                      : (p.project_type === 'segmentation' ? 'Get next labeling review' : 'Get next annotation review')"
                  >
                    <CheckCircle2 class="h-4 w-4" />
                    {{ p.project_type === 'segmentation' ? 'Start Label Review' : 'Start Review' }}
                  </Button>
                </template>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
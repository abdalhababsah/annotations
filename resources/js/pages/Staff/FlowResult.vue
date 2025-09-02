<!-- resources/js/pages/Staff/FlowResult.vue -->
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import {
    CheckCircle2,
    SkipForward,
    ShieldCheck,
    XCircle,
    Timer,
    ArrowRight,
    LayoutDashboard,
} from 'lucide-vue-next'

const props = defineProps<{
    project: { id: number; name: string }
    taskCount: number
    timeSpent: number // seconds
    action: 'submitted' | 'skipped' | 'approved' | 'rejected'
    nextTaskAvailable: boolean
}>()

/* ------------ Helpers ------------ */
const isAttempt = computed(() => props.action === 'submitted' || props.action === 'skipped')
const isReview = computed(() => !isAttempt.value)

const formatDuration = (totalSeconds: number) => {
    if (!totalSeconds || totalSeconds < 0) return '—'
    const h = Math.floor(totalSeconds / 3600)
    const m = Math.floor((totalSeconds % 3600) / 60)
    const s = Math.floor(totalSeconds % 60)
    const mm = String(m).padStart(2, '0')
    const ss = String(s).padStart(2, '0')
    return h > 0 ? `${h}:${mm}:${ss}` : `${m}:${ss}`
}

const title = computed(() => {
    switch (props.action) {
        case 'submitted': return 'Task submitted successfully'
        case 'skipped': return 'Task skipped'
        case 'approved': return 'Review approved'
        case 'rejected': return 'Review rejected'
        default: return 'Done'
    }
})

const tone = computed(() => {
    switch (props.action) {
        case 'submitted':
        case 'approved':
            return { ring: 'ring-green-200', bg: 'bg-green-50', text: 'text-green-700' }
        case 'skipped':
            return { ring: 'ring-amber-200', bg: 'bg-amber-50', text: 'text-amber-700' }
        case 'rejected':
            return { ring: 'ring-red-200', bg: 'bg-red-50', text: 'text-red-700' }
        default:
            return { ring: 'ring-muted', bg: 'bg-muted', text: 'text-foreground' }
    }
})

const Icon = computed(() => {
    switch (props.action) {
        case 'submitted': return CheckCircle2
        case 'approved': return ShieldCheck
        case 'skipped': return SkipForward
        case 'rejected': return XCircle
        default: return CheckCircle2
    }
})

/* ------------ Actions ------------ */
const continueFlow = () => {
    if (isAttempt.value) {
        router.get(route('staff.attempt.next', props.project.id))
    } else {
        router.get(route('staff.review.next', props.project.id))
    }
}
const backToDashboard = () => router.get(route('staff.dashboard'))
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Result', href: '#' }]">

        <Head :title="title" />
        <div class="p-6">
            <Card class="w-full overflow-hidden">
                <CardHeader class="pb-2">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full p-2 ring-2" :class="[tone.ring, tone.bg]">
                            <component :is="Icon" class="h-6 w-6" :class="tone.text" />
                        </div>
                        <div class="flex flex-col">
                            <CardTitle class="text-xl">{{ title }}</CardTitle>
                            <div class="text-sm text-muted-foreground">
                                Project • <span class="font-medium">{{ project.name }}</span>
                            </div>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="space-y-6">
                    <!-- Summary chips -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Badge variant="secondary" class="gap-1">
                            <Timer class="h-4 w-4" />
                            <span>Time spent: {{ formatDuration(timeSpent) }}</span>
                        </Badge>

                        <Badge v-if="isAttempt" variant="outline" class="gap-1">
                            <span class="font-medium">Tasks today:</span>
                            <span class="ml-1">{{ taskCount }}</span>
                        </Badge>
                        <Badge v-else variant="outline" class="gap-1">
                            <span class="font-medium">Reviews today:</span>
                            <span class="ml-1">{{ taskCount }}</span>
                        </Badge>

                        <Badge variant="outline" class="gap-1">
                            <span class="font-medium">Mode:</span>
                            <span class="ml-1">{{ isAttempt ? 'Attempt' : 'Review' }}</span>
                        </Badge>
                    </div>

                    <Separator />

                    <!-- Next steps -->
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="text-sm text-muted-foreground">
                            <template v-if="nextTaskAvailable">
                                {{ isAttempt ? 'You can continue to the next task.' : 'You can continue to the next review.' }}
                            </template>
                            <template v-else>
                                No more items right now. You can return to your dashboard.
                            </template>
                        </div>

                        <div class="flex gap-2">
                            <Button v-if="nextTaskAvailable" class="gap-2" @click="continueFlow">
                                <ArrowRight class="h-4 w-4" />
                                <span>{{ isAttempt ? 'Continue tasking' : 'Continue reviewing' }}</span>
                            </Button>
                            <Button variant="outline" class="gap-2" @click="backToDashboard">
                                <LayoutDashboard class="h-4 w-4" />
                                <span>Return to dashboard</span>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Review Task', href: '#' }]">

        <Head :title="`Reviewing - ${project.name}`" />

        <div class="container mx-auto p-6 space-y-6">
            <!-- Header + Timer -->
            <Card class="border-2 border-amber-10 bg-gradient-to-r from-amber-5 to-orange-50">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <CardTitle class="text-2xl flex items-center gap-3">
                                <div class="p-2 bg-amber-10 rounded-lg">
                                    <Eye class="h-6 w-6 text-amber-600" />
                                </div>
                                Reviewing Segmentation
                            </CardTitle>
                            <p class="text-muted-foreground font-medium">{{ project.name }}</p>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <div class="text-2xl font-mono font-bold">
                                    {{ minutes.toString().padStart(2, '0') }}:{{ seconds.toString().padStart(2, '0') }}
                                </div>
                                <div class="text-sm text-muted-foreground">Review Time Remaining</div>
                            </div>
                            <div class="space-y-2">
                                <Progress :value="timePct" class="h-3 w-32" />
                                <Badge variant="secondary" class="gap-1 justify-center w-full">
                                    <Timer class="h-3 w-3" />
                                    <span class="text-xs">{{ Math.round(timePct) }}%</span>
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            <!-- Original vs Reviewed Segments -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Original -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg flex items-center gap-2">
                            <FileText class="h-5 w-5 text-blue-600" />
                            Original Annotation
                            <Badge variant="outline">{{ originalSegments.length }} segments</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2 max-h-60 overflow-y-auto">
                            <div v-for="(segment, index) in originalSegments"
                                :key="`original-${segment.id ?? 'new'}-${index}`"
                                class="p-3 bg-gray-50 rounded-lg border-l-4"
                                :style="{ borderLeftColor: segment.label.color }">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full"
                                            :style="{ backgroundColor: segment.label.color }"></div>
                                        <span class="font-medium">{{ segment.label.name }}</span>
                                        <span v-if="segment.custom_label" class="text-xs text-gray-500">(Custom)</span>
                                    </div>
                                    <Badge variant="outline">{{ (segment.end_time - segment.start_time).toFixed(1) }}s
                                    </Badge>
                                </div>
                                <div class="text-sm text-gray-600 mt-1">
                                    {{ formatTime(segment.start_time) }} - {{ formatTime(segment.end_time) }}
                                </div>
                                <div v-if="segment.notes" class="text-sm text-gray-500 mt-1">{{ segment.notes }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Reviewed -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Edit class="h-5 w-5 text-green-600" />
                            Your Review
                            <Badge variant="outline">{{ reviewedSegments.length }} segments</Badge>
                            <Badge v-if="hasChanges" variant="destructive" class="ml-auto">{{ changesCount }} changes
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2 max-h-60 overflow-y-auto">
                            <div v-for="(segment, index) in reviewedSegments"
                                :key="`reviewed-list-${segment.id ?? 'new'}-${index}`" class="p-3 rounded-lg border-l-4"
                                :class="isSegmentChanged(segment) ? 'bg-yellow-50 border-yellow-400' : 'bg-gray-50'"
                                :style="{ borderLeftColor: segment.label.color }">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full"
                                            :style="{ backgroundColor: segment.label.color }"></div>
                                        <span class="font-medium">{{ segment.label.name }}</span>
                                        <span v-if="segment.custom_label" class="text-xs text-gray-500">(Custom)</span>
                                        <AlertCircle v-if="isSegmentChanged(segment)" class="h-4 w-4 text-yellow-600" />
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        {{ formatTime(segment.start_time) }} - {{ formatTime(segment.end_time) }}
                                        <Badge variant="outline" class="ml-2">{{ (segment.end_time -
                                            segment.start_time).toFixed(1) }}s</Badge>
                                    </div>

                                    <!-- Revert (per segment) -->
                                    <Button v-if="canRevert(segment)" size="sm" variant="outline" class="gap-2"
                                        @click="revertSegment(segment)">
                                        <RotateCcw class="h-4 w-4" />
                                        Revert
                                    </Button>
                                </div>

                                <div v-if="segment.notes" class="text-sm text-gray-500 mt-1">{{ segment.notes }}</div>
                            </div>
                        </div>

                        <!-- Revert all -->
                        <div class="mt-4 flex items-center justify-end" v-if="hasChanges">
                            <Button variant="ghost" class="gap-2" @click="revertAllChanges">
                                <RotateCcw class="h-4 w-4" />
                                Revert All Changes
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Main Audio Interface -->
            <Card class="overflow-hidden">
                <CardHeader>
                    <CardTitle class="text-lg flex items-center gap-3">
                        <FileAudio class="h-5 w-5 text-blue-600" />
                        Audio Waveform - Review & Edit
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <!-- Labels Palette -->
                    <div class="p-4 bg-gradient-to-r from-amber-50 to-orange-50 border-b">
                        <div class="flex items-center gap-2 mb-3">
                            <h4 class="font-semibold text-sm text-gray-700">Edit segments with labels:</h4>
                            <Badge variant="secondary" class="text-xs">
                                Selected: {{ selectedLabel?.name || 'None' }}
                            </Badge>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="label in allAvailableLabels" :key="label.key" @click="selectLabel(label)"
                                class="px-4 py-2 rounded-full font-medium text-sm transition-all duration-200 hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-1"
                                :class="selectedLabel?.key === label.key
                                    ? 'ring-2 ring-amber-500 ring-offset-2 shadow-lg transform scale-105'
                                    : 'hover:shadow-md'" :style="{
                        backgroundColor: label.color + '20',
                        borderColor: label.color,
                        color: label.color,
                        border: `2px solid ${label.color}`
                    }" :title="label.description || undefined">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: label.color }"></div>
                                    {{ label.name }}
                                    <span v-if="label.isCustom" class="text-xs opacity-70">(Custom)</span>
                                </div>
                            </button>

                            <Button v-if="project.allow_custom_labels" variant="outline" class="gap-2"
                                @click="showCreateLabel = true" :disabled="creatingLabel">
                                <Plus class="w-4 h-4" />
                                {{ creatingLabel ? 'Creating...' : 'Add Custom Label' }}
                            </Button>
                        </div>
                    </div>

                    <!-- Controls -->
                    <div class="p-4 bg-white border-b">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <Button variant="outline" size="sm" @click="playPause" class="flex items-center gap-2">
                                    <component :is="isPlaying ? Pause : Play" class="h-4 w-4" />
                                    {{ isPlaying ? 'Pause' : 'Play' }}
                                </Button>
                                <div class="text-sm text-gray-600">
                                    {{ formatTime(currentTime) }} / {{ formatTime(duration) }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button size="sm" variant="ghost" @click="zoomOut">
                                    <ZoomOut class="h-4 w-4" />
                                </Button>
                                <Button size="sm" variant="ghost" @click="zoomIn">
                                    <ZoomIn class="h-4 w-4" />
                                </Button>
                                <Badge variant="outline" class="text-xs">
                                    {{ Math.round(pixelsPerSecond) }}px/s
                                </Badge>
                            </div>
                        </div>

                        <!-- Waveform + overlay -->
                        <div class="relative">
                            <div ref="waveformContainer" class="w-full bg-gray-100 rounded-lg overflow-hidden relative"
                                style="height: 120px;" @mousedown="handleMouseDown" @mousemove="handleMouseMove"
                                @mouseup="handleMouseUp">
                                <div ref="waveformEl" class="w-full h-full"></div>

                                <!-- Segments overlay -->
                                <div class="absolute inset-0 pointer-events-none z-10">
                                    <template v-for="(segment, index) in reviewedSegments"
                                        :key="`seg-${segment.id ?? 'new'}-${index}`">
                                        <div v-if="getSegmentPosition(segment).visible"
                                            class="absolute rounded hover:shadow-lg transition-all duration-100 group pointer-events-auto cursor-pointer"
                                            :class="isSegmentChanged(segment) ? 'ring-2 ring-yellow-400' : ''" :style="{
                                                left: `${getSegmentPosition(segment).left}px`,
                                                width: `${getSegmentPosition(segment).width}px`,
                                                backgroundColor: segment.label.color + (isSegmentChanged(segment) ? '50' : '30'),
                                                border: `2px solid ${segment.label.color}`,
                                                top: '10px',
                                                height: '100px',
                                                borderRadius: '6px'
                                            }" @click.stop="playSegment(segment)">
                                            <!-- Resize handles -->
                                            <div class="absolute left-0 top-0 w-3 h-full bg-gray-800 opacity-0 group-hover:opacity-80 cursor-ew-resize rounded-l flex items-center justify-center z-20"
                                                @mousedown.stop="startResize(segment, 'start', $event)">
                                                <div class="w-0.5 h-8 bg-white rounded"></div>
                                            </div>
                                            <div class="absolute right-0 top-0 w-3 h-full bg-gray-800 opacity-0 group-hover:opacity-80 cursor-ew-resize rounded-r flex items-center justify-center z-20"
                                                @mousedown.stop="startResize(segment, 'end', $event)">
                                                <div class="w-0.5 h-8 bg-white rounded"></div>
                                            </div>

                                            <!-- Label -->
                                            <div
                                                class="absolute top-1 left-1 right-1 px-2 py-1 text-xs font-bold text-white bg-black bg-opacity-60 rounded truncate pointer-events-none">
                                                {{ segment.label.name }}
                                                <span v-if="segment.custom_label" class="opacity-75">(C)</span>
                                                <AlertCircle v-if="isSegmentChanged(segment)"
                                                    class="inline h-3 w-3 ml-1 text-yellow-300" />
                                            </div>

                                            <!-- Duration -->
                                            <div
                                                class="absolute bottom-1 left-1 px-1 py-0.5 text-xs font-medium text-white bg-black bg-opacity-50 rounded pointer-events-none">
                                                {{ (segment.end_time - segment.start_time).toFixed(1) }}s
                                            </div>

                                            <!-- Delete -->
                                            <button
                                                class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600 flex items-center justify-center shadow-lg z-20"
                                                @click.stop="removeSegment(index)">
                                                <X class="h-4 w-4" />
                                            </button>

                                            <!-- Play -->
                                            <button
                                                class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-8 h-8 bg-white bg-opacity-80 text-gray-800 rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-opacity-100 flex items-center justify-center shadow-md z-20"
                                                @click.stop="playSegment(segment)">
                                                <Play class="h-4 w-4 ml-0.5" />
                                            </button>

                                            <!-- Revert -->
                                            <button v-if="canRevert(segment)"
                                                class="absolute bottom-1 right-1 px-2 py-0.5 text-xs rounded bg-white bg-opacity-90 hover:bg-opacity-100 text-gray-800 shadow pointer-events-auto"
                                                @click.stop="revertSegment(segment)" title="Revert this segment">
                                                <span class="inline-flex items-center gap-1">
                                                    <RotateCcw class="h-3 w-3" /> Revert
                                                </span>
                                            </button>
                                        </div>
                                    </template>

                                    <!-- Empty hint -->
                                    <div v-if="!reviewedSegments.length"
                                        class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <div
                                            class="bg-white bg-opacity-90 px-4 py-2 rounded-lg shadow-sm text-gray-500 text-sm text-center">
                                            <div class="font-medium mb-1">No segments to review</div>
                                            <div class="text-xs">Original annotation had no segments</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Selection overlay -->
                                <div v-if="isSelecting"
                                    class="absolute bg-amber-500 bg-opacity-30 border-2 border-amber-500 pointer-events-none rounded z-15"
                                    :style="{
                                        left: `${Math.min(selectionStart, selectionEnd)}px`,
                                        width: `${Math.abs(selectionEnd - selectionStart)}px`,
                                        top: '10px',
                                        height: '100px'
                                    }" />
                            </div>

                            <p class="text-xs text-gray-500 mt-2">
                                Tip: Drag the segment edges to resize, click a segment to play it, or drag on the
                                waveform (with a label selected)
                                to add a new segment.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Review Form -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        Review Feedback
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-8">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Quality Rating -->
                        <div class="space-y-4">
                            <Label class="text-sm font-medium text-muted-foreground">Quality Rating (1-5):</Label>
                            <div class="flex items-center justify-center gap-4 p-6 bg-muted/20 rounded-xl">
                                <div class="text-sm font-medium text-muted-foreground">1</div>
                                <div class="flex items-center gap-3">
                                    <template v-for="num in [1, 2, 3, 4, 5]" :key="`rating-${num}`">
                                        <label
                                            class="group relative p-3 rounded-full transition-all duration-200 hover:scale-110 cursor-pointer"
                                            :class="{
                                                'bg-primary text-white shadow-lg scale-105': feedbackRating === num,
                                                'bg-white border-2 border-muted hover:border-primary/40 hover:shadow-md': feedbackRating !== num
                                            }">
                                            <input type="radio" name="quality-rating" :value="num"
                                                v-model.number="feedbackRating" class="sr-only" />
                                            <Star class="h-6 w-6" :class="{ 'fill-current': feedbackRating === num }" />
                                            <span
                                                class="absolute -bottom-8 left-1/2 transform -translate-x-1/2 text-sm font-semibold">{{
                                                num }}</span>
                                        </label>
                                    </template>
                                </div>
                                <div class="text-sm font-medium text-muted-foreground">5</div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm text-muted-foreground">
                                    Selected: <strong class="text-foreground">{{ feedbackRating ?? 'None' }}</strong>
                                </div>
                                <div class="text-xs text-muted-foreground mt-1">{{ getRatingLabel(feedbackRating) }}
                                </div>
                            </div>
                        </div>

                        <!-- Changes Summary -->
                        <div class="space-y-4">
                            <Label class="text-sm font-medium text-muted-foreground">Changes Summary</Label>
                            <div class="p-4 bg-gray-50 rounded-lg space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-muted-foreground">Original segments:</span>
                                    <span class="font-medium">{{ originalSegments.length }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-muted-foreground">Reviewed segments:</span>
                                    <span class="font-medium">{{ reviewedSegments.length }}</span>
                                </div>
                                <div class="border-t pt-3 space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-amber-600">Added:</span>
                                        <span class="font-medium text-amber-600">{{ additionsCount }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-amber-600">Deleted:</span>
                                        <span class="font-medium text-amber-600">{{ deletionsCount }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-amber-600">Modified:</span>
                                        <span class="font-medium text-amber-600">{{ modifiedCount }}</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center border-t pt-3">
                                    <span class="text-sm font-semibold text-amber-700">Total changes:</span>
                                    <span class="font-bold text-amber-700">{{ changesCount }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Review Comments -->
                    <div class="space-y-4">
                        <Label for="comment" class="text-sm font-medium text-muted-foreground">Review Comments</Label>
                        <Textarea id="comment" v-model="feedbackComment" rows="4"
                            placeholder="Provide detailed feedback about the annotation quality, accuracy, and any changes made..."
                            class="resize-none" />
                    </div>
                </CardContent>
            </Card>

            <!-- Actions -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <Dialog v-model:open="skipOpen">
                            <DialogTrigger as-child>
                                <Button variant="outline" class="gap-2">
                                    <SkipForward class="h-4 w-4" />
                                    Skip Review
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Skip this review</DialogTitle>
                                    <DialogDescription>
                                        Skipping returns this annotation to the review queue for another reviewer.
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="space-y-4">
                                    <div>
                                        <Label>Reason</Label>
                                        <Select v-model="skipReason">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a reason..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="technical_issue">Technical issue</SelectItem>
                                                <SelectItem value="unclear_audio">Unclear audio</SelectItem>
                                                <SelectItem value="unclear_annotation">Unclear annotation</SelectItem>
                                                <SelectItem value="personal_reason">Personal reason</SelectItem>
                                                <SelectItem value="other">Other</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label>Details (optional)</Label>
                                        <Textarea v-model="skipDescription" rows="3"
                                            placeholder="Additional details..." />
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button variant="outline" @click="skipOpen = false">Cancel</Button>
                                    <Button @click="skip" :disabled="skipping">Skip Review</Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>

                        <div class="flex items-center gap-3">
                            <!-- <Button variant="outline" size="lg" @click="saveDraft" :disabled="saving" class="gap-2">
                                <Save class="h-4 w-4" />
                                {{ saving ? 'Saving...' : 'Save Draft' }}
                            </Button> -->
                            <Button size="lg" @click="approve" :disabled="!canSubmit" class="gap-2 px-8">
                                <CheckCircle2 class="h-4 w-4" />
                                {{ submitting ? 'Approving...' : 'Approve Review' }}
                            </Button>

                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Custom Label Modal -->
        <CreateLabelModal :open="showCreateLabel" @close="showCreateLabel = false" @create="handleCreateLabel" />
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import CreateLabelModal from '@/components/Tasks/staff/CreateLabelModal.vue'

import {
    Eye, Timer, FileAudio, Save, CheckCircle2, SkipForward, Play, Pause,
    X, ZoomIn, ZoomOut, FileText, Edit, MessageSquare, AlertCircle, Plus, RotateCcw, Star
} from 'lucide-vue-next'

/** Types */
type IncomingLabel = {
    id: number
    name: string
    color: string
    description: string | null
    isCustom?: boolean
    uuid?: string | null
}

type BaseLabel = {
    key: string            // project:<id> or custom:<uuid or fallback>
    id?: number | null     // project id (for API)
    uuid?: string | null   // custom uuid (for identity)
    name: string
    color: string
    description: string | null
    isCustom: boolean
}

type Segment = {
    id?: number
    start_time: number
    end_time: number
    notes?: string
    label: { name: string; color: string }
    project_label_id?: number | null
    custom_label?: { uuid?: string | null; name: string; color: string } | null
}

const props = defineProps<{
    project: {
        id: number
        name: string
        review_time_minutes: number | null
        allow_custom_labels: boolean
    }
    review: { id: number; expires_at: string | null; feedback_rating: number | null; feedback_comment: string | null }
    annotation: {
        id: number
        task_id: number
        segments: Array<{
            id: number
            start_time: number
            end_time: number
            notes: string | null
            project_label: { id: number; name: string; color: string } | null
            custom_label: { id: number; uuid?: string | null; name: string; color: string } | null
        }>
        audio: { id: number | null; filename: string | null; url: string | null; duration: number | null }
    }
    csrf_token: string 
    labels: IncomingLabel[]
}>()

/* TIMER */
const expiresAt = props.review.expires_at ? new Date(props.review.expires_at) : null
const remaining = ref<number>(expiresAt ? Math.max(0, Math.floor((expiresAt.getTime() - Date.now()) / 1000)) : 0)
let timerInterval: any = null
const minutes = computed(() => Math.floor(remaining.value / 60))
const seconds = computed(() => remaining.value % 60)
const timePct = computed(() => {
    const total = (props.project.review_time_minutes ?? 0) * 60
    if (!total) return 0
    return Math.min(100, Math.max(0, (remaining.value / total) * 100))
})

/* WAVESURFER */
import WaveSurfer from 'wavesurfer.js'
const waveformEl = ref<HTMLDivElement | null>(null)
const waveformContainer = ref<HTMLDivElement | null>(null)
let ws: WaveSurfer | null = null

const isPlaying = ref(false)
const currentTime = ref(0)
const duration = ref(0)
const pixelsPerSecond = ref(50)
const scrollLeft = ref(0)
const containerWidth = ref(800)
const activeSegmentEnd = ref<number | null>(null)

/* LABELS (stable key identity) */
const selectedLabel = ref<BaseLabel | null>(null)
const showCreateLabel = ref(false)
const creatingLabel = ref(false)
const customLabels = reactive<BaseLabel[]>([])

const mapProjectLabel = (l: IncomingLabel): BaseLabel => ({
    key: `project:${l.id}`,
    id: l.id,
    uuid: null,
    name: l.name,
    color: l.color,
    description: l.description ?? null,
    isCustom: false,
})

const mapCustomLabel = (
    l: { id: number; uuid?: string | null; name: string; color: string; description?: string | null },
    fallbackSuffix = ''
): BaseLabel => ({
    key: l.uuid ? `custom:${l.uuid}` : `custom:task:${props.annotation.id}:label:${l.id}${fallbackSuffix}`,
    id: null,
    uuid: l.uuid ?? null,
    name: l.name,
    color: l.color,
    description: l.description ?? null,
    isCustom: true,
})

/** build labels from props (may contain both project + custom) */
const labelsFromProps = computed<BaseLabel[]>(() => {
    return props.labels.map(l => (l.isCustom ? mapCustomLabel(l) : mapProjectLabel(l)))
})

/** project-only set (for defaulting) */
const projectLabels = computed<BaseLabel[]>(() => labelsFromProps.value.filter(l => !l.isCustom))

/** custom labels referenced in segments (ensures we capture any SSR payloads) */
const customLabelsFromSegments = computed<BaseLabel[]>(() => {
    const seen = new Set<string>()
    const arr: BaseLabel[] = []
    props.annotation.segments.forEach((s, i) => {
        if (s.custom_label) {
            const mapped = mapCustomLabel(
                { id: s.custom_label.id, uuid: s.custom_label.uuid ?? null, name: s.custom_label.name, color: s.custom_label.color },
                `:${i}`
            )
            if (!seen.has(mapped.key)) { seen.add(mapped.key); arr.push(mapped) }
        }
    })
    return arr
})

/** allAvailableLabels = project + (custom from props/segments) + session-created; de-duped by key */
const allAvailableLabels = computed<BaseLabel[]>(() => {
    const merged = [
        ...labelsFromProps.value,                       // includes project + custom from controller
        ...customLabelsFromSegments.value,              // custom labels that appear only in segments
        ...customLabels,                                // session-created (just now)
    ]
    const byKey = new Map<string, BaseLabel>()
    merged.forEach(l => { if (!byKey.has(l.key)) byKey.set(l.key, l) })
    return Array.from(byKey.values())
})

const selectLabel = (label: BaseLabel) => { selectedLabel.value = label }

/* Create custom label via JSON route */
const handleCreateLabel = async (labelData: { name: string; color: string }) => {
    if (!props.project.allow_custom_labels) {
        alert('Custom labels are not allowed in this project.')
        return
    }

    creatingLabel.value = true
    try {
        const res = await fetch(
            route('staff.custom-labels.create.json', [props.project.id, props.annotation.task_id]),
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': props.csrf_token,
                },
                body: JSON.stringify({ name: labelData.name, color: labelData.color }),
            }
        )
        if (!res.ok) {
            const text = await res.text()
            throw new Error(`Create label failed (${res.status}): ${text}`)
        }
        const result = await res.json()
        if (!result?.success || !result?.label) throw new Error(result?.message || 'Failed to create custom label.')

        const newLabel = mapCustomLabel(result.label)
        customLabels.push(newLabel)
        selectedLabel.value = newLabel
        showCreateLabel.value = false
    } catch (e) {
        console.error(e)
        alert('Failed to create custom label. Please try again.')
    } finally {
        creatingLabel.value = false
    }
}

/* SEGMENTS (original vs reviewed) */
type S = Segment
const originalSegments = reactive<S[]>([])
const reviewedSegments = reactive<S[]>([])

props.annotation.segments?.forEach(s => {
    const base: S = {
        id: s.id,
        start_time: s.start_time,
        end_time: s.end_time,
        notes: s.notes || '',
        label: s.project_label
            ? { name: s.project_label.name, color: s.project_label.color }
            : { name: s.custom_label!.name, color: s.custom_label!.color },
        project_label_id: s.project_label?.id ?? null,
        custom_label: s.custom_label ? { uuid: s.custom_label.uuid ?? null, name: s.custom_label.name, color: s.custom_label.color } : null,
    }
    originalSegments.push({ ...base })
    reviewedSegments.push({ ...base })
})

/** stable default select: first available label (project/custom) */
if (!selectedLabel.value) {
    selectedLabel.value = allAvailableLabels.value[0] ?? null
}

/* HELPERS */
const formatTime = (t: number) => {
    const m = Math.floor(t / 60)
    const s = Math.floor(t % 60)
    return `${m}:${s.toString().padStart(2, '0')}`
}
const getRatingLabel = (rating: number | null) => {
    const labels: Record<number, string> = { 1: 'Very Poor', 2: 'Poor', 3: 'Average', 4: 'Good', 5: 'Excellent' }
    return rating ? labels[rating] : ''
}

/* Overlay math */
const getSegmentPosition = (segment: S) => {
    if (!duration.value || !containerWidth.value) return { left: 0, width: 0, visible: false }
    const visibleStartTime = scrollLeft.value / pixelsPerSecond.value
    const visibleEndTime = visibleStartTime + containerWidth.value / pixelsPerSecond.value
    const visible = segment.end_time > visibleStartTime && segment.start_time < visibleEndTime
    if (!visible) return { left: 0, width: 0, visible: false }
    const startIn = Math.max(0, segment.start_time - visibleStartTime)
    const endIn = Math.min(segment.end_time - visibleStartTime, containerWidth.value / pixelsPerSecond.value)
    const left = startIn * pixelsPerSecond.value
    const width = Math.max(2, (endIn - startIn) * pixelsPerSecond.value)
    return { left, width, visible: true }
}

const getTimeFromX = (clientX: number) => {
    if (!waveformContainer.value) return 0
    const rect = waveformContainer.value.getBoundingClientRect()
    const relX = Math.max(0, Math.min(clientX - rect.left, rect.width))
    const visibleStartTime = scrollLeft.value / pixelsPerSecond.value
    return visibleStartTime + relX / pixelsPerSecond.value
}

/* Playback + Zoom */
const playPause = () => { if (!ws) return; isPlaying.value ? ws.pause() : ws.play() }
const playSegment = (segment: S) => {
    if (!ws) return
    ws.setTime(segment.start_time)
    activeSegmentEnd.value = segment.end_time
    ws.play()
}
const zoomIn = () => { if (!ws) return; const v = pixelsPerSecond.value * 1.5; ws.zoom(v); pixelsPerSecond.value = v }
const zoomOut = () => { if (!ws) return; const v = Math.max(25, pixelsPerSecond.value * 0.75); ws.zoom(v); pixelsPerSecond.value = v }

/* Create / Delete / Resize */
const createSegment = (start: number, end: number) => {
    if (!selectedLabel.value) return
    const seg: S = {
        start_time: Math.min(start, end),
        end_time: Math.max(start, end),
        notes: '',
        label: { name: selectedLabel.value.name, color: selectedLabel.value.color },
        project_label_id: selectedLabel.value.isCustom ? null : (selectedLabel.value.id ?? null),
        custom_label: selectedLabel.value.isCustom
            ? { uuid: selectedLabel.value.uuid ?? null, name: selectedLabel.value.name, color: selectedLabel.value.color }
            : null,
    }
    reviewedSegments.push(seg)
    reviewedSegments.sort((a, b) => a.start_time - b.start_time)
}
const removeSegment = (idx: number) => { reviewedSegments.splice(idx, 1) }

/* Drag-select create */
const isSelecting = ref(false)
const selectionStart = ref(0)
const selectionEnd = ref(0)
let isDragging = false
const handleMouseDown = (e: MouseEvent) => {
    if (!selectedLabel.value || isResizing) return
    isDragging = true
    const rect = waveformContainer.value?.getBoundingClientRect()
    if (rect) {
        selectionStart.value = e.clientX - rect.left
        selectionEnd.value = e.clientX - rect.left
    }
    isSelecting.value = true
}
const handleMouseMove = (e: MouseEvent) => {
    if (!isDragging || !isSelecting.value) return
    const rect = waveformContainer.value?.getBoundingClientRect()
    if (rect) selectionEnd.value = e.clientX - rect.left
}
const handleMouseUp = (_e: MouseEvent) => {
    if (!isDragging || !isSelecting.value) return
    isDragging = false
    isSelecting.value = false
    const rect = waveformContainer.value?.getBoundingClientRect()
    if (!rect) return
    const startX = Math.min(selectionStart.value, selectionEnd.value)
    const endX = Math.max(selectionStart.value, selectionEnd.value)
    const start = getTimeFromX(rect.left + startX)
    const end = getTimeFromX(rect.left + endX)
    if (Math.abs(end - start) > 0.1) createSegment(start, end)
}

/* Resize */
let isResizing = false
let resizeSegmentRef: S | null = null
let resizeMode: 'start' | 'end' = 'start'
const startResize = (segment: S, mode: 'start' | 'end', e: MouseEvent) => {
    e.preventDefault()
    isResizing = true
    resizeSegmentRef = segment
    resizeMode = mode
    const onMove = (e: MouseEvent) => {
        if (!isResizing || !resizeSegmentRef) return
        const t = getTimeFromX(e.clientX)
        if (resizeMode === 'start') {
            resizeSegmentRef.start_time = Math.max(0, Math.min(t, resizeSegmentRef.end_time - 0.1))
        } else {
            resizeSegmentRef.end_time = Math.max(resizeSegmentRef.start_time + 0.1, Math.min(t, duration.value))
        }
    }
    const onUp = () => {
        isResizing = false
        resizeSegmentRef = null
        document.removeEventListener('mousemove', onMove)
        document.removeEventListener('mouseup', onUp)
    }
    document.addEventListener('mousemove', onMove)
    document.addEventListener('mouseup', onUp)
}

/* Changes / Revert */
const originalById = computed(() => {
    const m = new Map<number, S>()
    originalSegments.forEach(s => { if (s.id) m.set(s.id, s) })
    return m
})
const reviewedById = computed(() => {
    const m = new Map<number, S>()
    reviewedSegments.forEach(s => { if (s.id) m.set(s.id, s) })
    return m
})
const isSameContent = (a: S, b: S) =>
    a.start_time === b.start_time &&
    a.end_time === b.end_time &&
    a.label.name === b.label.name &&
    (a.notes || '') === (b.notes || '')

const modifiedCount = computed(() => {
    let n = 0
    reviewedSegments.forEach(s => {
        if (!s.id) return
        const orig = originalById.value.get(s.id)
        if (orig && !isSameContent(orig, s)) n++
    })
    return n
})
const additionsCount = computed(() => reviewedSegments.filter(s => !s.id).length)
const deletionsCount = computed(() => {
    let n = 0
    originalSegments.forEach(os => { if (os.id && !reviewedById.value.get(os.id)) n++ })
    return n
})
const changesCount = computed(() => modifiedCount.value + additionsCount.value + deletionsCount.value)
const hasChanges = computed(() => changesCount.value > 0)
const isSegmentChanged = (segment: S) => {
    if (!segment.id) return true
    const orig = originalById.value.get(segment.id)
    if (!orig) return true
    return !isSameContent(orig, segment)
}

const canRevert = (segment: S) => !segment.id || isSegmentChanged(segment)
const revertSegment = (segment: S) => {
    if (!segment.id) {
        const idx = reviewedSegments.indexOf(segment)
        if (idx !== -1) reviewedSegments.splice(idx, 1)
        return
    }
    const orig = originalById.value.get(segment.id)
    if (!orig) return
    segment.start_time = orig.start_time
    segment.end_time = orig.end_time
    segment.notes = orig.notes
    segment.label = { ...orig.label }
    segment.project_label_id = orig.project_label_id ?? null
    segment.custom_label = orig.custom_label ? { ...orig.custom_label } : null
}
const revertAllChanges = () => {
    reviewedSegments.splice(
        0,
        reviewedSegments.length,
        ...originalSegments.map(s => ({
            id: s.id,
            start_time: s.start_time,
            end_time: s.end_time,
            notes: s.notes,
            label: { ...s.label },
            project_label_id: s.project_label_id ?? null,
            custom_label: s.custom_label ? { ...s.custom_label } : null
        }))
    )
}

/* Feedback + Actions */
const feedbackRating = ref<number | null>(props.review.feedback_rating ?? null)
const feedbackComment = ref<string | null>(props.review.feedback_comment ?? null)
const saving = ref(false)
const submitting = ref(false)
const skipOpen = ref(false)
const skipReason = ref('')
const skipDescription = ref('')
const skipping = ref(false)

const isValidRating = computed(() =>
    typeof feedbackRating.value === 'number' && feedbackRating.value >= 1 && feedbackRating.value <= 5
)
const isValidComment = computed(() => (feedbackComment.value?.trim().length ?? 0) > 0)
const canSubmit = computed(() => isValidRating.value && isValidComment.value && !submitting.value)

const baseSpent = () => (props.project.review_time_minutes ?? 0) * 60 - remaining.value

const generateSegmentChanges = () => {
    const changes: Array<
        | { action: 'create'; start_time: number; end_time: number; project_label_id: number | null; custom_label: { uuid?: string | null; name: string; color: string } | null; notes: string | null }
        | { action: 'update'; segment_id: number; start_time: number; end_time: number; project_label_id: number | null; custom_label: { uuid?: string | null; name: string; color: string } | null; notes: string | null }
        | { action: 'delete'; segment_id: number }
    > = []

    reviewedSegments.forEach(s => {
        if (!s.id) {
            changes.push({
                action: 'create',
                start_time: Number(s.start_time.toFixed(3)),
                end_time: Number(s.end_time.toFixed(3)),
                project_label_id: s.project_label_id ?? null,
                custom_label: s.custom_label
                    ? { uuid: s.custom_label.uuid ?? null, name: s.custom_label.name, color: s.custom_label.color }
                    : null,
                notes: s.notes || null
            })
        } else {
            const orig = originalById.value.get(s.id)
            if (orig && !isSameContent(orig, s)) {
                changes.push({
                    action: 'update',
                    segment_id: s.id,
                    start_time: Number(s.start_time.toFixed(3)),
                    end_time: Number(s.end_time.toFixed(3)),
                    project_label_id: s.project_label_id ?? null,
                    custom_label: s.custom_label
                        ? { uuid: s.custom_label.uuid ?? null, name: s.custom_label.name, color: s.custom_label.color }
                        : null,
                    notes: s.notes || null
                })
            }
        }
    })

    originalSegments.forEach(os => {
        if (os.id && !reviewedById.value.get(os.id)) {
            changes.push({ action: 'delete', segment_id: os.id })
        }
    })

    return changes
}

const saveDraft = async () => {
    saving.value = true
    try {
        await router.post(
            route('staff.review.draft', [props.project.id, props.review.id]),
            {
                feedback_rating: feedbackRating.value,
                feedback_comment: feedbackComment.value,
                spent_seconds: baseSpent()
            },
            { preserveScroll: true }
        )
    } finally {
        saving.value = false
    }
}

const approve = async () => {
    submitting.value = true
    try {
        const segment_changes = generateSegmentChanges()
        await router.post(
            route('staff.review.approve', [props.project.id, props.review.id]),
            {
                feedback_rating: feedbackRating.value,
                feedback_comment: feedbackComment.value,
                spent_seconds: baseSpent(),
                segment_changes
            }
        )
    } finally {
        submitting.value = false
    }
}

const skip = async () => {
    if (!skipReason.value) { alert('Please select a reason.'); return }
    skipping.value = true
    try {
        await router.post(
            route('staff.review.skip', [props.project.id, props.review.id]),
            { reason: skipReason.value, description: skipDescription.value }
        )
    } finally {
        skipping.value = false
        skipOpen.value = false
    }
}

/* INIT */
const updateContainerWidth = () => {
    if (waveformContainer.value) containerWidth.value = waveformContainer.value.clientWidth
}

const initWaveSurfer = async () => {
    if (!waveformEl.value || !props.annotation.audio.url) return
    if (ws) ws.destroy()

    ws = WaveSurfer.create({
        container: waveformEl.value,
        url: props.annotation.audio.url,
        height: 120,
        waveColor: '#e2e8f0',
        progressColor: '#f59e0b',
        cursorColor: '#1f2937',
        normalize: true,
        interact: true,
        minPxPerSec: pixelsPerSecond.value,
    })

    ws.on('ready', () => {
        duration.value = ws?.getDuration() || 0
        updateContainerWidth()
    })

    ws.on('play', () => { isPlaying.value = true })
    ws.on('pause', () => { isPlaying.value = false })

    ws.on('audioprocess', (t: number) => {
        currentTime.value = t
        if (activeSegmentEnd.value !== null && t >= activeSegmentEnd.value - 0.01) {
            ws?.pause()
            activeSegmentEnd.value = null
        }
    })

    ws.on('zoom', (minPxPerSec: number) => { pixelsPerSecond.value = minPxPerSec })
    ws.on('scroll', (startTime: number) => { scrollLeft.value = startTime * pixelsPerSecond.value })

    const wrap = waveformEl.value?.parentElement
    if (wrap) {
        wrap.addEventListener('scroll', (e) => {
            const el = e.target as HTMLElement
            scrollLeft.value = el.scrollLeft || 0
        }, { passive: true })
    }
}

onMounted(async () => {
    if (expiresAt) {
        timerInterval = setInterval(() => { if (remaining.value > 0) remaining.value -= 1 }, 1000)
    }
    await nextTick()
    await initWaveSurfer()
    window.addEventListener('resize', updateContainerWidth)
})

onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval)
    if (ws) ws.destroy()
    window.removeEventListener('resize', updateContainerWidth)
})

watch([pixelsPerSecond, scrollLeft, containerWidth], () => {
    /* keep overlay reactive */
})
</script>

<style scoped>
.cursor-ew-resize {
    cursor: ew-resize;
}
</style>
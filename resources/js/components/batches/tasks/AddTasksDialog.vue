<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref, computed, watch, reactive, nextTick } from 'vue'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Plus, Music, Search } from 'lucide-vue-next'

type AudioFileLite = {
    id: number
    original_filename: string
    duration: string
    file_size: string
}

const props = defineProps<{
    open: boolean
    projectId: number
    batchId: number
    availableAudioFiles: AudioFileLite[]
}>()

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void
    (e: 'added'): void
}>()

const query = ref('')
const adding = ref(false)

// Use a reactive Set to track selected items
const selected = reactive(new Set<number>())

// Debug function to log state
const debugLog = (action: string, fileId?: number, checked?: any) => {
    console.log(`[DEBUG] ${action}`, {
        fileId,
        checked,
        selectedSize: selected.size,
        selectedItems: Array.from(selected),
        selectedTotal: selectedTotal.value
    })
}

// Derived lists & counts
const filteredFiles = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return props.availableAudioFiles
    return props.availableAudioFiles.filter(f =>
        f.original_filename.toLowerCase().includes(q),
    )
})

const totalCount = computed(() => props.availableAudioFiles.length)
const filteredCount = computed(() => filteredFiles.value.length)
const selectedTotal = computed(() => selected.size)
const selectedInFiltered = computed(() => {
    let c = 0
    for (const f of filteredFiles.value) if (selected.has(f.id)) c++
    return c
})
const allFilteredSelected = computed(
    () => filteredCount.value > 0 && selectedInFiltered.value === filteredCount.value,
)
const someFilteredSelected = computed(
    () => selectedInFiltered.value > 0 && selectedInFiltered.value < filteredCount.value,
)

// Individual checkbox handler with multiple approaches
const handleFileClick = async (fileId: number, event: any) => {
    debugLog('handleFileClick called', fileId, event)

    // Toggle the selection manually
    if (selected.has(fileId)) {
        selected.delete(fileId)
        debugLog('Removed from selection', fileId)
    } else {
        selected.add(fileId)
        debugLog('Added to selection', fileId)
    }

    await nextTick()
    debugLog('After nextTick', fileId)
}

// Alternative handler for @update:checked
const handleFileToggle = (fileId: number) => {
    debugLog('handleFileToggle factory called', fileId)

    return (checked: boolean | 'indeterminate') => {
        debugLog('handleFileToggle handler called', fileId, checked)

        if (checked === 'indeterminate') return

        if (checked && !selected.has(fileId)) {
            selected.add(fileId)
            debugLog('Added via toggle', fileId)
        } else if (!checked && selected.has(fileId)) {
            selected.delete(fileId)
            debugLog('Removed via toggle', fileId)
        }
    }
}

// Direct v-model approach
const getFileModel = (fileId: number) => {
    return computed({
        get: () => {
            const result = selected.has(fileId)
            debugLog('getFileModel get', fileId, result)
            return result
        },
        set: (value: boolean) => {
            debugLog('getFileModel set', fileId, value)
            if (value) {
                selected.add(fileId)
            } else {
                selected.delete(fileId)
            }
        }
    })
}

// Check if individual file is selected
const isFileSelected = (fileId: number) => {
    return selected.has(fileId)
}

// Select all handler
const handleSelectAll = () => {
    debugLog('handleSelectAll called')

    if (allFilteredSelected.value) {
        // Deselect all filtered items
        for (const f of filteredFiles.value) {
            selected.delete(f.id)
        }
        debugLog('Deselected all')
    } else {
        // Select all filtered items
        for (const f of filteredFiles.value) {
            selected.add(f.id)
        }
        debugLog('Selected all')
    }
}

// Submit
const submit = () => {
    if (selected.size === 0) return
    adding.value = true
    router.post(
        route('admin.projects.batches.add-tasks', [props.projectId, props.batchId]),
        { audio_file_ids: Array.from(selected) },
        {
            preserveScroll: true,
            onFinish: () => (adding.value = false),
            onSuccess: () => {
                selected.clear()
                emit('update:open', false)
                emit('added')
            },
        },
    )
}

// Clear on close
watch(
    () => props.open,
    (open) => {
        if (!open) {
            query.value = ''
            selected.clear()
            debugLog('Dialog closed, cleared selection')
        }
    },
)

// Watch selected changes
watch(
    () => selected.size,
    (newSize) => {
        debugLog('Selected size changed', undefined, newSize)
    }
)
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Plus class="h-5 w-5" />
                    Add Tasks to Batch
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Info / Counts -->
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm text-muted-foreground">
                        Available: <strong>{{ totalCount }}</strong>
                        <span v-if="query"> • Filtered: <strong>{{ filteredCount }}</strong></span>
                        • Selected: <strong>{{ selectedTotal }}</strong>
                    </p>

                    <div class="relative w-full md:w-72">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input v-model="query" placeholder="Search by filename..." class="pl-10" />
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="totalCount === 0" class="rounded-md border p-8 text-center">
                    <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mx-auto mb-3">
                        <Music class="h-8 w-8 text-muted-foreground" />
                    </div>
                    <h3 class="font-semibold mb-1">No audio files available</h3>
                    <p class="text-sm text-muted-foreground">All project audios are already in this batch.</p>
                </div>

                <!-- List -->
                <template v-else>
                    <!-- Select all (filtered) -->
                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="select-all"
                            :checked="allFilteredSelected ? true : (someFilteredSelected ? 'indeterminate' : false)"
                            @click="handleSelectAll"
                        />
                        <Label for="select-all" class="font-medium cursor-pointer">
                            Select All ({{ selectedInFiltered }} / {{ filteredCount }}{{ query ? ' filtered' : '' }})
                        </Label>
                    </div>

                    <Alert v-if="filteredCount === 0" class="border-amber-200 bg-amber-50">
                        <AlertDescription class="text-amber-800">
                            No files match your search.
                        </AlertDescription>
                    </Alert>

                    <div v-else class="max-h-[28rem] overflow-y-auto space-y-2 border rounded-md p-2">
                        <div
                            v-for="f in filteredFiles"
                            :key="f.id"
                            class="flex items-center space-x-3 p-3 rounded-md hover:bg-muted"
                        >
                            <!-- Try multiple approaches -->
                            <!-- Approach 1: Direct click handler -->
                            <Checkbox
                                :id="`file-${f.id}`"
                                :checked="isFileSelected(f.id)"
                                @click="handleFileClick(f.id, $event)"
                            />

                            <!-- Debug info for this file -->
                            <div class="text-xs text-gray-500 mr-2">
                                ID: {{ f.id }}, Selected: {{ isFileSelected(f.id) ? 'YES' : 'NO' }}
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <Label :for="`file-${f.id}`" class="font-medium cursor-pointer truncate">
                                        {{ f.original_filename }}
                                    </Label>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground shrink-0">
                                        <span>{{ f.duration }}</span>
                                        <span>•</span>
                                        <span>{{ f.file_size }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-2 pt-2">
                        <Button variant="outline" @click="emit('update:open', false)" :disabled="adding">
                            Cancel
                        </Button>
                        <Button @click="submit" :disabled="adding || selectedTotal === 0">
                            <span v-if="adding" class="flex items-center gap-2">
                                <div class="w-4 h-4 border-2 border-background border-t-transparent rounded-full animate-spin"></div>
                                Adding…
                            </span>
                            <span v-else>
                                Add {{ selectedTotal }} Task{{ selectedTotal !== 1 ? 's' : '' }}
                            </span>
                        </Button>
                    </div>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>

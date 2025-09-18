<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea' // if you don’t have this, swap for a <textarea>
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Progress } from '@/components/ui/progress'
import { Separator } from '@/components/ui/separator'
import { Badge } from '@/components/ui/badge'

import {
  ArrowLeft,
  Upload,
  Import as ImportIcon,
  FileAudio,
  Link as LinkIcon,
  Trash,
  X
} from 'lucide-vue-next'

interface Props {
  project: { id: number; name: string }
  // optional: pass accepted mimes / max size via server if you want
  acceptedMimes?: string[] // e.g. ["audio/mpeg","audio/wav","audio/aac","audio/ogg","audio/flac","audio/webm"]
  maxFileMb?: number // e.g. 200
}
const props = defineProps<Props>()

/* ===================== Tabs ===================== */
type Tab = 'upload' | 'import'
const activeTab = ref<Tab>('upload')

/* ======== Upload (drag & drop) ===================== */
const dropZone = ref<HTMLElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const queue = ref<File[]>([])
const uploading = ref(false)
const progress = ref(0)

const acceptAttr = computed(() =>
  (props.acceptedMimes?.length ? props.acceptedMimes.join(',') : 'audio/*')
)

const readableSize = (bytes: number) => {
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes, i = 0
  while (size >= 1024 && i < units.length - 1) { size /= 1024; i++ }
  return `${size.toFixed(i === 0 ? 0 : 2)} ${units[i]}`
}

const onDrop = (e: DragEvent) => {
  e.preventDefault()
  if (!e.dataTransfer) return
  const files = Array.from(e.dataTransfer.files || []).filter(f => f.type.startsWith('audio/'))
  if (files.length) queue.value.push(...files)
}

const onBrowse = () => fileInput.value?.click()
const onPicked = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (!target.files) return
  const files = Array.from(target.files).filter(f => f.type.startsWith('audio/'))
  if (files.length) queue.value.push(...files)
  // reset input so picking same files again re-triggers change
  target.value = ''
}

const removeFromQueue = (idx: number) => {
  queue.value.splice(idx, 1)
}

const clearQueue = () => { queue.value = [] }

const canUpload = computed(() => queue.value.length > 0 && !uploading.value)

const startUpload = () => {
  if (queue.value.length === 0 || uploading.value) return

  const fd = new FormData()
  queue.value.forEach(f => fd.append('files[]', f))

  uploading.value = true
  progress.value = 0

  router.post(route('admin.projects.audio-files.store', props.project.id), fd, {
    forceFormData: true,
    onProgress: (e) => {
      if (e && e.total) progress.value = Math.round((e.loaded / e.total) * 100)
    },
    onFinish: () => {
      uploading.value = false
      progress.value = 100
      clearQueue()
    },
    onError: () => {
      uploading.value = false
    }
  })
}

/* ===================== Import (Excel/CSV or pasted links) ===================== */
const importFile = ref<HTMLInputElement | null>(null)
const importLinks = ref('')
const importing = ref(false)

const canImport = computed(() => {
  const hasFile = !!importFile.value?.files?.[0]
  const hasLinks = importLinks.value.trim().length > 0
  return (hasFile || hasLinks) && !importing.value
})

const submitImport = () => {
  if (!canImport.value) return
  const fd = new FormData()
  if (importFile.value?.files?.[0]) {
    fd.append('file', importFile.value.files[0])
  }
  if (importLinks.value.trim()) {
    fd.append('links', importLinks.value.trim())
  }

  importing.value = true
  router.post(route('admin.projects.audio-files.import', props.project.id), fd, {
    forceFormData: true,
    onFinish: () => {
      importing.value = false
      importLinks.value = ''
      if (importFile.value) (importFile.value as any).value = ''
    },
    onError: () => { importing.value = false }
  })
}
</script>

<template>

  <Head :title="`Add Audio Files • ${project.name}`" />

  <AppLayout :breadcrumbs="[
    { title: 'Projects', href: '/admin/projects' },
    { title: project.name, href: route('admin.projects.show', project.id) },
    { title: 'Audio Files', href: route('admin.projects.audio-files.index', project.id) },
    { title: 'Add', href: '#' }
  ]">

    <div class="flex flex-col gap-6 p-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="space-y-1">
          <h1 class="text-2xl font-bold">Add Audio Files</h1>
          <p class="text-muted-foreground">Upload new files or import from S3 links</p>
        </div>
        <Link :href="route('admin.projects.audio-files.index', project.id)">
        <Button variant="ghost" class="gap-2">
          <ArrowLeft class="h-4 w-4" />
          Back
        </Button>
        </Link>
      </div>

      <!-- Tabs -->
      <div class="inline-flex rounded-lg border bg-card p-1 w-full md:w-auto">
        <Button :variant="activeTab === 'upload' ? 'default' : 'ghost'" class="gap-2" @click="activeTab = 'upload'">
          <Upload class="h-4 w-4" />
          Upload
        </Button>
        <Button :variant="activeTab === 'import' ? 'default' : 'ghost'" class="gap-2" @click="activeTab = 'import'">
          <ImportIcon class="h-4 w-4" />
          Import Links
        </Button>
      </div>

      <!-- Upload tab -->
      <Card v-if="activeTab === 'upload'">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <FileAudio class="h-5 w-5" /> Upload audio files
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
          <Alert>
            <AlertDescription class="text-sm">
              Accepted: <b>{{ acceptAttr }}</b>
              <template v-if="maxFileMb"> • Max per file: <b>{{ maxFileMb }}MB</b></template>
            </AlertDescription>
          </Alert>

          <!-- Drop area -->
          <div ref="dropZone"
            class="rounded-lg border-2 border-dashed p-8 text-center transition-colors hover:bg-muted/40"
            @dragover.prevent @drop="onDrop">
            <p class="text-sm text-muted-foreground">
              Drag & drop audio files here
            </p>
            <div class="my-3 text-xs text-muted-foreground">or</div>
            <Button type="button" class="gap-2" @click="onBrowse">
              <Upload class="h-4 w-4" /> Browse files
            </Button>
            <input ref="fileInput" type="file" :accept="acceptAttr" multiple class="hidden" @change="onPicked" />
          </div>

          <!-- Queue list -->
          <div v-if="queue.length" class="space-y-3">
            <div class="flex items-center justify-between">
              <div class="text-sm">
                Selected <b>{{ queue.length }}</b> file{{ queue.length > 1 ? 's' : '' }}
              </div>
              <Button variant="outline" size="sm" @click="clearQueue" class="gap-2">
                <Trash class="h-4 w-4" />
                Clear
              </Button>
            </div>

            <div class="rounded-md border divide-y">
              <div v-for="(f, idx) in queue" :key="`${f.name}-${idx}`" class="flex items-center justify-between p-3">
                <div class="min-w-0">
                  <div class="font-medium truncate">{{ f.name }}</div>
                  <div class="text-xs text-muted-foreground">
                    {{ f.type || '—' }} • {{ readableSize(f.size) }}
                  </div>
                </div>
                <Button variant="ghost" size="icon" @click="removeFromQueue(idx)" :disabled="uploading">
                  <X class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>

          <!-- Progress -->
          <div v-if="uploading" class="space-y-2">
            <Progress :value="progress" />
            <div class="text-xs text-muted-foreground">{{ progress }}%</div>
          </div>
        </CardContent>
        <CardFooter class="flex items-center justify-between">
          <div class="text-xs text-muted-foreground">
            Files will be stored under <code>projects/{{ project.id }}/audio/</code> in your S3 bucket.
          </div>
          <div class="flex gap-2">
            <Link :href="route('admin.projects.audio-files.index', project.id)">
            <Button variant="outline">Cancel</Button>
            </Link>
            <Button :disabled="!canUpload" @click="startUpload">
              {{ uploading ? 'Uploading…' : 'Start Upload' }}
            </Button>
          </div>
        </CardFooter>
      </Card>

      <!-- Import tab -->
      <Card v-else>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <LinkIcon class="h-5 w-5" /> Import from S3 links
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-6">
          <Alert>
            <AlertDescription class="text-sm">
              You can upload an Excel/CSV file (first column or a header named <b>url</b>) or paste links (one per
              line).
              If a link already points to your bucket, we’ll register it without re-uploading. Otherwise we copy it into
              your
              bucket.
            </AlertDescription>
          </Alert>

          <div class="grid gap-4 md:grid-cols-2">
            <!-- Excel/CSV -->
            <div class="space-y-2">
              <div class="text-sm font-medium">Excel/CSV file</div>
              <Input ref="importFile" type="file"
                accept=".csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
              <p class="text-xs text-muted-foreground">
                First sheet only. Either a first column with links or a column named <b>url</b>.
              </p>
            </div>

            <!-- Paste links -->
            <div class="space-y-2">
              <div class="text-sm font-medium">Paste links</div>
              <!-- If you don’t have a Textarea component, swap for native <textarea> -->
              <Textarea v-model="importLinks" rows="8" placeholder="https://mahalatna.s3.eu-north-1.amazonaws.com/projects/123/audio/file1.mp3
https://other-bucket.s3.amazonaws.com/path/file2.wav" />
              <p class="text-xs text-muted-foreground">
                One URL per line. Both public S3 and your bucket URLs are accepted.
              </p>
            </div>
          </div>

          <Separator />

          <div class="rounded-md border p-3 text-sm">
            <div class="flex items-center gap-2">
              <Badge variant="secondary">Tip</Badge>
              You can mix both: choose a file and paste extra links. We’ll de-duplicate.
            </div>
          </div>

          <div v-if="importing" class="space-y-2">
            <Progress :value="100" />
            <div class="text-xs text-muted-foreground">Importing…</div>
          </div>
        </CardContent>
        <CardFooter class="flex items-center justify-between">
          <Link :href="route('admin.projects.audio-files.index', project.id)">
          <Button variant="outline">Cancel</Button>
          </Link>
          <Button :disabled="!canImport" @click="submitImport">
            {{ importing ? 'Importing…' : 'Import' }}
          </Button>
        </CardFooter>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogTrigger,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { Import, Upload } from 'lucide-vue-next'

/* ========= Props ========= */
const props = defineProps<{ projectId: number }>()

/* ========= Upload state ========= */
const openUpload = ref(false)
const uploading = ref(false)
const progress = ref(0)
const uploadFiles = ref<FileList | null>(null)

const onUploadChange = (e: Event) => {
  const t = e.target as HTMLInputElement
  uploadFiles.value = t.files
}

const submitUpload = async () => {
  const files = uploadFiles.value
  if (!files || files.length === 0) return

  const fd = new FormData()
  Array.from(files).forEach(f => {
    fd.append('files[]', f, f.name)
  })

  uploading.value = true
  progress.value = 0

  router.post(route('admin.projects.audio-files.store', props.projectId), fd, {
    forceFormData: true,
    onProgress: e => {
      if (e && e.total) progress.value = Math.round((e.loaded / e.total) * 100)
    },
    onFinish: () => {
      uploading.value = false
      openUpload.value = false
      // reset
      const el = document.getElementById('upload-input') as HTMLInputElement | null
      if (el) el.value = ''
      uploadFiles.value = null
    },
  })
}

/* ========= Import state ========= */
const openImport = ref(false)
const importing = ref(false)
const importFileList = ref<FileList | null>(null)
const importTextarea = ref('')

const onImportChange = (e: Event) => {
  const t = e.target as HTMLInputElement
  importFileList.value = t.files
}

const submitImport = () => {
  const fd = new FormData()
  const f = importFileList.value?.[0]
  if (f) fd.append('file', f)
  if (importTextarea.value.trim()) fd.append('links', importTextarea.value.trim())

  if (!f && !importTextarea.value.trim()) return

  importing.value = true
  router.post(route('admin.projects.audio-files.import', props.projectId), fd, {
    forceFormData: true,
    onFinish: () => {
      importing.value = false
      openImport.value = false
      // reset
      const el = document.getElementById('import-input') as HTMLInputElement | null
      if (el) el.value = ''
      importFileList.value = null
      importTextarea.value = ''
    },
  })
}
</script>

<template>
  <div class="flex gap-2">
    <!-- Import -->
    <Dialog v-model:open="openImport">
      <DialogTrigger as-child>
        <Button variant="outline" class="gap-2">
          <Import class="h-4 w-4" /> Import Links
        </Button>
      </DialogTrigger>

      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Import from S3 links</DialogTitle>
        </DialogHeader>

        <div id="import-desc" class="space-y-4">
          <div>
            <label class="text-sm font-medium">Excel/CSV (first column or “url” header)</label>
            <!-- native input so FileList is accessible -->
            <input id="import-input" type="file" class="mt-2 block w-full rounded-md border px-3 py-2 text-sm"
              accept=".csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
              @change="onImportChange" />
          </div>
          <div>
            <label class="text-sm font-medium">Or paste links (one per line)</label>
            <textarea v-model="importTextarea" rows="6"
              class="mt-2 w-full rounded-md border px-3 py-2 text-sm"></textarea>
          </div>
        </div>

        <DialogFooter>
          <Button :disabled="importing" @click="submitImport">
            {{ importing ? 'Importing…' : 'Import' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Upload -->
    <Dialog v-model:open="openUpload">
      <DialogTrigger as-child>
        <Button class="gap-2">
          <Upload class="h-4 w-4" /> Upload
        </Button>
      </DialogTrigger>

      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Upload audio files</DialogTitle>
        </DialogHeader>

        <div id="upload-desc" class="space-y-4">
          <div class="border-2 border-dashed rounded-md p-6 text-center">
            <p class="text-sm mb-3">Drag & drop files here or click to select</p>
            <!-- native input so FileList is accessible -->
            <input id="upload-input" type="file" multiple accept="audio/*"
              class="block w-full rounded-md border px-3 py-2 text-sm" @change="onUploadChange" />
          </div>

          <div v-if="uploading" class="space-y-2">
            <Progress :value="progress" />
            <p class="text-xs text-muted-foreground">{{ progress }}%</p>
          </div>
        </div>

        <DialogFooter>
          <Button :disabled="uploading" @click="submitUpload">
            {{ uploading ? 'Uploading…' : 'Upload' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

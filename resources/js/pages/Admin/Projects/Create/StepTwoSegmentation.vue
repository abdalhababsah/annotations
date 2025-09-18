<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
  ArrowLeft, 
  ArrowRight, 
  Plus, 
  Trash2, 
  Tags, 
  CheckCircle2, 
  AlertCircle,
  Palette,
  Save,
  Search,
  Layers
} from 'lucide-vue-next';

interface SegmentationLabel {
  id: number;
  name: string;
  color: string;
  description?: string;
}

interface Project {
  id: number;
  name: string;
  status: string;
  project_type: string;
  allow_custom_labels: boolean;
}

interface Props {
  project: Project;
  availableLabels: SegmentationLabel[];
  selectedLabels: SegmentationLabel[];
  currentStep: number;
  totalSteps: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Projects', href: '/admin/projects' },
  { title: 'Create Project', href: '/admin/projects/create' },
  { title: 'Configure Labels', href: `#` },
];

// Form for labels
const form = useForm({
  selectedLabels: [...props.selectedLabels],
  newLabels: [] as { name: string; color: string; description: string; tempId: string }[]
});

// State
const showCreateLabelModal = ref(false);
const searchQuery = ref('');
const newLabel = ref({
  name: '',
  color: '#3B82F6',
  description: ''
});

// Available color options
const colorOptions = [
  '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
  '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1',
  '#F43F5E', '#14B8A6', '#A855F7', '#EAB308', '#22C55E'
];

// Filtered available labels based on search
const filteredAvailableLabels = computed(() => {
  const query = searchQuery.value.toLowerCase();
  const selectedIds = form.selectedLabels.map(l => l.id);
  
  return props.availableLabels
    .filter(label => !selectedIds.includes(label.id))
    .filter(label => 
      query === '' || 
      label.name.toLowerCase().includes(query) ||
      (label.description && label.description.toLowerCase().includes(query))
    );
});

// Validation
const isFormValid = computed(() => {
  return (form.selectedLabels.length + form.newLabels.length) >= 1;
});

const isNewLabelValid = computed(() => {
  const nameValid = newLabel.value.name.trim().length > 0;
  const colorValid = newLabel.value.color.match(/^#[0-9A-Fa-f]{6}$/);
  const nameNotDuplicate = !isNameDuplicate(newLabel.value.name.trim());
  
  return !!(nameValid && colorValid && nameNotDuplicate);
});

// Check if label name is duplicate
const isNameDuplicate = (name: string) => {
  const trimmedName = name.toLowerCase().trim();
  
  // Check against selected labels
  const selectedNames = form.selectedLabels.map(l => l.name.toLowerCase());
  if (selectedNames.includes(trimmedName)) return true;
  
  // Check against new labels
  const newLabelNames = form.newLabels.map(l => l.name.toLowerCase());
  if (newLabelNames.includes(trimmedName)) return true;
  
  // Check against available labels
  const availableNames = props.availableLabels.map(l => l.name.toLowerCase());
  if (availableNames.includes(trimmedName)) return true;
  
  return false;
};

// Selection helpers
const isLabelSelected = (labelId: number) => {
  return form.selectedLabels.some(l => l.id === labelId);
};

const toggleLabel = (label: SegmentationLabel, checked: boolean) => {
  if (checked) {
    if (!isLabelSelected(label.id)) {
      form.selectedLabels.push(label);
    }
  } else {
    const index = form.selectedLabels.findIndex(l => l.id === label.id);
    if (index > -1) {
      form.selectedLabels.splice(index, 1);
    }
  }
};

// click on row toggles too (fallback if checkbox event is quirky)
const onRowToggle = (label: SegmentationLabel) => {
  const next = !isLabelSelected(label.id);
  toggleLabel(label, next);
};

const removeSelectedLabel = (labelId: number) => {
  const index = form.selectedLabels.findIndex(l => l.id === labelId);
  if (index > -1) {
    form.selectedLabels.splice(index, 1);
  }
};

const addNewLabel = () => {
  if (isNewLabelValid.value) {
    const tempId = Date.now().toString() + Math.random().toString(36).substring(2);
    form.newLabels.push({
      name: newLabel.value.name.trim(),
      color: newLabel.value.color,
      description: newLabel.value.description.trim(),
      tempId
    });
    
    // Reset form
    newLabel.value = {
      name: '',
      color: getRandomColor(),
      description: ''
    };
    
    showCreateLabelModal.value = false;
  }
};

const removeNewLabel = (tempId: string) => {
  const index = form.newLabels.findIndex(l => l.tempId === tempId);
  if (index > -1) {
    form.newLabels.splice(index, 1);
  }
};

const openCreateLabelModal = () => {
  newLabel.value = {
    name: '',
    color: getRandomColor(),
    description: ''
  };
  showCreateLabelModal.value = true;
};

const closeCreateLabelModal = () => {
  showCreateLabelModal.value = false;
  newLabel.value = {
    name: '',
    color: '#3B82F6',
    description: ''
  };
};

// Submit step 2 (transform payload to match backend validation)
const submitStepTwo = () => {
  if (!isFormValid.value || form.processing) return;

  form
    .transform(data => ({
      selectedLabels: data.selectedLabels.map((l: SegmentationLabel) => ({ id: l.id })),
      newLabels: data.newLabels.map((l: any) => ({
        name: l.name,
        color: l.color,
        description: l.description
      }))
    }))
    .post(route('admin.projects.store-step-two', props.project.id));
};

// Go back to step 1
const goToPreviousStep = () => {
  window.location.href = route('admin.projects.edit', props.project.id);
};

// Generate random color
const getRandomColor = () => {
  return colorOptions[Math.floor(Math.random() * colorOptions.length)];
};

const generateRandomColor = () => {
  newLabel.value.color = getRandomColor();
};

// Watch for label name changes to check duplicates
const labelNameError = computed(() => {
  const name = newLabel.value.name.trim();
  if (!name) return '';
  if (isNameDuplicate(name)) {
    return 'This label name already exists';
  }
  return '';
});

// Quick add functionality
const quickAddLabel = (name: string) => {
  if (!isNameDuplicate(name)) {
    form.newLabels.push({
      name: name.trim(),
      color: getRandomColor(),
      description: '',
      tempId: Date.now().toString() + Math.random().toString(36).substring(2)
    });
  }
};

// Common label suggestions
const commonLabelSuggestions = [
  'Speaker A', 'Speaker B', 'Speaker C', 
  'Music', 'Background Noise', 'Silence',
  'Narrator', 'Interview', 'Discussion'
];

const filteredSuggestions = computed(() => {
  if (!searchQuery.value.trim()) {
    // Show all available suggestions when no search
    return commonLabelSuggestions.filter(suggestion => !isNameDuplicate(suggestion));
  }
  // Show matching suggestions when searching
  return commonLabelSuggestions.filter(suggestion => 
    !isNameDuplicate(suggestion) && 
    suggestion.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});
</script>

<template>
  <Head title="Configure Segmentation Labels" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 overflow-x-auto">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Configure Segmentation Labels</h1>
          <p class="text-muted-foreground">
            Step {{ currentStep }} of {{ totalSteps }}: Select and create labels for audio segmentation
          </p>
        </div>
        <Link :href="route('admin.projects.index')">
          <Button variant="outline" size="sm">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back to Projects
          </Button>
        </Link>
      </div>

      <!-- Progress Indicator -->
      <div class="flex items-center justify-between mb-8">
        <div class="flex items-center space-x-4 w-full">
          <!-- Step 1 - Completed -->
          <div class="flex items-center">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-green-600 text-white text-sm font-medium">
              <CheckCircle2 class="w-4 h-4" />
            </div>
            <span class="ml-2 text-sm font-medium text-green-600">Basic Info</span>
          </div>
          
          <Separator class="flex-1" />
          
          <!-- Step 2 - Active -->
          <div class="flex items-center">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-medium">
              <span v-if="!isFormValid">2</span>
              <CheckCircle2 v-else class="w-4 h-4" />
            </div>
            <span class="ml-2 text-sm font-medium text-blue-600">Labels</span>
          </div>
          
          <Separator class="flex-1" />
          
          <!-- Step 3 - Inactive -->
          <div class="flex items-center">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-200 text-gray-600 text-sm font-medium">
              3
            </div>
            <span class="ml-2 text-sm font-medium text-gray-600">Review</span>
          </div>
        </div>
      </div>

      <!-- Project Info -->
      <Card class="mb-6">
        <CardContent class="pt-6">
          <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
              <Layers class="h-5 w-5 text-blue-600" />
              <div>
                <h3 class="font-medium">{{ project.name }}</h3>
                <p class="text-sm text-muted-foreground">Audio Segmentation Project</p>
              </div>
            </div>
            <Badge variant="outline">{{ project.status }}</Badge>
            <Badge v-if="project.allow_custom_labels" variant="secondary" class="gap-1">
              <Tags class="h-3 w-3" />
              Custom Labels Enabled
            </Badge>
          </div>
        </CardContent>
      </Card>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Available Labels -->
        <Card>
          <CardHeader>
            <div class="flex items-center justify-between">
              <CardTitle class="flex items-center gap-2">
                <Tags class="h-5 w-5" />
                Available Labels
                <Badge variant="outline">{{ filteredAvailableLabels.length }}</Badge>
              </CardTitle>
              <Button @click="openCreateLabelModal" size="sm">
                <Plus class="mr-2 h-4 w-4" />
                Create Label
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <!-- Search -->
            <div class="relative mb-4">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                v-model="searchQuery"
                placeholder="Search labels..."
                class="pl-10"
              />
            </div>

            <!-- Quick Add Suggestions -->
            <div v-if="filteredSuggestions.length > 0" class="mb-4">
              <p class="text-sm font-medium text-muted-foreground mb-2">
                {{ searchQuery ? 'Matching suggestions:' : 'Quick Add:' }}
              </p>
              <div class="flex flex-wrap gap-2">
                <Button 
                  v-for="suggestion in filteredSuggestions.slice(0, 3)" 
                  :key="suggestion"
                  @click="quickAddLabel(suggestion)"
                  variant="outline" 
                  size="sm"
                  class="h-7 text-xs"
                >
                  <Plus class="h-3 w-3 mr-1" />
                  {{ suggestion }}
                </Button>
              </div>
              <Separator class="my-4" />
            </div>

            <!-- Labels List -->
            <div class="space-y-2 max-h-96 overflow-y-auto">
              <div 
                v-for="label in filteredAvailableLabels" 
                :key="label.id"
                class="flex items-center space-x-3 p-3 border rounded-lg hover:bg-muted/50 transition-colors cursor-pointer"
                @click="onRowToggle(label)"
              >
                <!-- Controlled checkbox: support both modelValue and checked APIs -->
                <Checkbox
                  :modelValue="isLabelSelected(label.id)"
                  :checked="isLabelSelected(label.id)"
                  @update:modelValue="(val:any) => toggleLabel(label, !!val)"
                  @update:checked="(val:any) => toggleLabel(label, !!val)"
                  @change.stop="(e:any) => toggleLabel(label, !!e?.target?.checked)"
                  @click.stop
                />
                <div 
                  class="w-4 h-4 rounded-full border-2 border-white shadow-sm flex-shrink-0"
                  :style="{ backgroundColor: label.color }"
                />
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-sm">{{ label.name }}</p>
                  <p v-if="label.description" class="text-xs text-muted-foreground truncate">
                    {{ label.description }}
                  </p>
                </div>
              </div>

              <!-- Empty State -->
              <div v-if="filteredAvailableLabels.length === 0" class="text-center py-8 text-muted-foreground">
                <Tags class="mx-auto h-8 w-8 mb-2" />
                <p class="text-sm">{{ searchQuery ? 'No labels found' : 'No available labels' }}</p>
                <p class="text-xs">{{ searchQuery ? 'Try a different search term' : 'Create a new label to get started' }}</p>
              </div>
            </div>

            <!-- Info Alert -->
            <Alert class="mt-4">
              <AlertCircle class="h-4 w-4" />
              <AlertDescription>
                Select labels that annotators will use to segment audio files. You can also create new labels specific to your project.
              </AlertDescription>
            </Alert>
          </CardContent>
        </Card>

        <!-- Selected Labels -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <CheckCircle2 class="h-5 w-5" />
              Selected Labels
              <Badge variant="secondary">{{ form.selectedLabels.length + form.newLabels.length }}</Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <!-- Selected Existing Labels -->
            <div v-if="form.selectedLabels.length > 0" class="space-y-2 mb-4">
              <div class="text-xs font-medium text-muted-foreground mb-2">From Library</div>
              <div 
                v-for="label in form.selectedLabels" 
                :key="label.id"
                class="flex items-center justify-between p-3 border rounded-lg bg-muted/30"
              >
                <div class="flex items-center gap-3">
                  <div 
                    class="w-4 h-4 rounded-full border-2 border-white shadow-sm flex-shrink-0"
                    :style="{ backgroundColor: label.color }"
                  />
                  <div>
                    <p class="font-medium text-sm">{{ label.name }}</p>
                    <p v-if="label.description" class="text-xs text-muted-foreground">
                      {{ label.description }}
                    </p>
                  </div>
                </div>
                <Button 
                  @click="removeSelectedLabel(label.id)"
                  variant="ghost"
                  size="sm"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- New Labels -->
            <div v-if="form.newLabels.length > 0" class="space-y-2 mb-4">
              <div class="text-xs font-medium text-muted-foreground mb-2">New Labels</div>
              <div 
                v-for="label in form.newLabels" 
                :key="label.tempId"
                class="flex items-center justify-between p-3 border rounded-lg bg-blue-50 border-blue-200"
              >
                <div class="flex items-center gap-3">
                  <div 
                    class="w-4 h-4 rounded-full border-2 border-white shadow-sm flex-shrink-0"
                    :style="{ backgroundColor: label.color }"
                  />
                  <div>
                    <p class="font-medium text-sm">{{ label.name }}</p>
                    <p v-if="label.description" class="text-xs text-muted-foreground">
                      {{ label.description }}
                    </p>
                    <Badge variant="outline" class="text-xs mt-1">New</Badge>
                  </div>
                </div>
                <Button 
                  @click="removeNewLabel(label.tempId)"
                  variant="ghost"
                  size="sm"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>

            <!-- Empty State -->
            <div v-if="form.selectedLabels.length === 0 && form.newLabels.length === 0" 
                 class="text-center py-8 border-2 border-dashed border-gray-300 rounded-lg">
              <Tags class="mx-auto h-8 w-8 text-gray-400 mb-4" />
              <h3 class="text-lg font-medium text-gray-900 mb-2">No labels selected</h3>
              <p class="text-gray-600 mb-4">
                Select labels from the available list or create new ones
              </p>
              <Button @click="openCreateLabelModal" size="sm">
                <Plus class="mr-2 h-4 w-4" />
                Create Your First Label
              </Button>
            </div>

            <!-- Custom Labels Info -->
            <Alert v-if="project.allow_custom_labels" class="mt-4 border-blue-200 bg-blue-50">
              <AlertCircle class="h-4 w-4 text-blue-600" />
              <AlertDescription class="text-blue-800">
                <strong>Custom Labels Enabled:</strong> Annotators can create additional labels during segmentation tasks if needed.
              </AlertDescription>
            </Alert>

            <!-- Validation Error -->
            <Alert v-if="!isFormValid && (form.selectedLabels.length > 0 || form.newLabels.length > 0)" variant="destructive" class="mt-4">
              <AlertCircle class="h-4 w-4" />
              <AlertDescription>
                Please select at least one label or create a new label for your segmentation project.
              </AlertDescription>
            </Alert>

            <!-- Form Errors -->
            <div v-if="Object.keys(form.errors).length > 0" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
              <p class="text-sm text-red-600 font-medium mb-2">Please fix the following errors:</p>
              <ul class="text-sm text-red-600 space-y-1">
                <li v-for="(error, field) in form.errors" :key="field">
                  • {{ error }}
                </li>
              </ul>
            </div>

            <!-- Navigation -->
            <div class="flex items-center justify-between pt-6 border-t mt-6">
              <Button @click="goToPreviousStep" variant="outline">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Previous
              </Button>
              
              <Button 
                @click="submitStepTwo" 
                :disabled="!isFormValid || form.processing"
                class="flex items-center gap-2"
              >
                <span>{{ form.processing ? 'Saving...' : 'Next: Review Project' }}</span>
                <ArrowRight class="h-4 w-4" />
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- Create Label Modal -->
    <Dialog :open="showCreateLabelModal" @update:open="showCreateLabelModal = $event">
      <DialogContent class="max-w-md">
        <DialogHeader>
          <DialogTitle>Create New Segmentation Label</DialogTitle>
        </DialogHeader>
        <div class="space-y-4">
          <!-- Label Name -->
          <div class="space-y-2">
            <Label>Label Name *</Label>
            <Input
              v-model="newLabel.name"
              placeholder="e.g., Speaker A, Music, Background Noise"
              @keydown.enter.prevent="addNewLabel"
              :class="{ 'border-red-500': labelNameError }"
            />
            <p v-if="labelNameError" class="text-sm text-red-600">
              {{ labelNameError }}
            </p>
          </div>

          <!-- Label Color -->
          <div class="space-y-2">
            <Label>Color</Label>
            <div class="flex items-center gap-3">
              <Input
                v-model="newLabel.color"
                type="color"
                class="w-16 h-10 p-1 rounded border cursor-pointer"
              />
              <Input
                v-model="newLabel.color"
                placeholder="#3B82F6"
                class="flex-1 font-mono text-sm"
                pattern="^#[0-9A-Fa-f]{6}$"
              />
              <Button @click="generateRandomColor" variant="outline" size="sm" type="button">
                <Palette class="h-4 w-4" />
              </Button>
            </div>
            
            <!-- Color Presets -->
            <div class="flex gap-2 flex-wrap">
              <button
                v-for="color in colorOptions"
                :key="color"
                type="button"
                class="w-8 h-8 rounded border-2 border-white shadow-sm hover:scale-110 transition-transform cursor-pointer"
                :style="{ backgroundColor: color }"
                :class="newLabel.color === color ? 'ring-2 ring-primary' : ''"
                @click="newLabel.color = color"
              />
            </div>
          </div>

          <!-- Description -->
          <div class="space-y-2">
            <Label>Description (Optional)</Label>
            <Textarea
              v-model="newLabel.description"
              placeholder="Describe when this label should be used..."
              rows="2"
            />
          </div>

          <!-- Preview -->
          <div class="p-3 bg-muted/50 rounded-lg">
            <p class="text-sm font-medium mb-2">Preview:</p>
            <div class="flex items-center gap-3 p-2 border rounded">
              <div 
                class="w-4 h-4 rounded-full border-2 border-white shadow-sm"
                :style="{ backgroundColor: newLabel.color }"
              />
              <div>
                <p class="font-medium text-sm">{{ newLabel.name || 'Label Name' }}</p>
                <p v-if="newLabel.description" class="text-xs text-muted-foreground">
                  {{ newLabel.description }}
                </p>
              </div>
            </div>
          </div>

          <!-- Modal Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t">
            <Button @click="closeCreateLabelModal" variant="outline" type="button">
              Cancel
            </Button>
            <Button @click="addNewLabel" :disabled="!isNewLabelValid" type="button">
              <Save class="mr-2 h-4 w-4" />
              Create Label
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

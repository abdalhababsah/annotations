<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { 
  ArrowLeft, 
  ArrowRight, 
  Plus, 
  Trash2, 
  Settings, 
  CheckCircle2, 
  AlertCircle,
  GripVertical,
  Save
} from 'lucide-vue-next';

interface DimensionValue {
  id?: number;
  value: string;
  label: string;
  display_order: number;
}

interface AnnotationDimension {
  id?: number;
  name: string;
  description: string;
  dimension_type: 'categorical' | 'numeric_scale';
  scale_min?: number;
  scale_max?: number;
  is_required: boolean;
  display_order: number;
  values: DimensionValue[];
}

interface Project {
  id: number;
  name: string;
  status: string;
}

interface Props {
  project: Project;
  dimensions: AnnotationDimension[];
  currentStep: number;
  totalSteps: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Projects', href: '/admin/projects' },
  { title: 'Create Project', href: '/admin/projects/create' },
  { title: 'Configure Dimensions', href: `#` },
];

// Form for dimensions
const form = useForm({
  dimensions: props.dimensions.length > 0 ? [...props.dimensions] : [
    {
      name: 'Gender',
      description: 'Identify the speaker\'s gender',
      dimension_type: 'categorical' as const,
      is_required: true,
      display_order: 0,
      values: [
        { value: 'male', label: 'Male', display_order: 0 },
        { value: 'female', label: 'Female', display_order: 1 },
        { value: 'other', label: 'Other', display_order: 2 },
      ]
    }
  ] as AnnotationDimension[]
});

// Modal state for editing dimensions
const showDimensionModal = ref(false);
const editingDimension = ref<AnnotationDimension | null>(null);
const editingIndex = ref(-1);

// Validation
const isFormValid = computed(() => {
  return form.dimensions.length > 0 && 
         form.dimensions.every(dim => 
           dim.name.trim().length > 0 && 
           (dim.dimension_type === 'numeric_scale' || 
            (dim.dimension_type === 'categorical' && dim.values.length > 0))
         );
});

// Add new dimension
const addNewDimension = () => {
  editingDimension.value = {
    name: '',
    description: '',
    dimension_type: 'categorical',
    is_required: true,
    display_order: form.dimensions.length,
    values: [
      { value: '', label: '', display_order: 0 }
    ]
  };
  editingIndex.value = -1;
  showDimensionModal.value = true;
};

// Edit existing dimension
const editDimension = (dimension: AnnotationDimension, index: number) => {
  editingDimension.value = JSON.parse(JSON.stringify(dimension)); // Deep copy
  editingIndex.value = index;
  showDimensionModal.value = true;
};

// Delete dimension
const deleteDimension = (index: number) => {
  form.dimensions.splice(index, 1);
  // Reorder display_order
  form.dimensions.forEach((dim, idx) => {
    dim.display_order = idx;
  });
};

// Save dimension (add or update)
const saveDimension = () => {
  if (!editingDimension.value) return;

  // Validate dimension
  if (!editingDimension.value.name.trim()) {
    alert('Please enter a dimension name');
    return;
  }

  if (editingDimension.value.dimension_type === 'categorical') {
    if (editingDimension.value.values.length === 0 || 
        !editingDimension.value.values.some(v => v.value.trim())) {
      alert('Please add at least one value for categorical dimension');
      return;
    }
  }

  if (editingDimension.value.dimension_type === 'numeric_scale') {
    if (!editingDimension.value.scale_min || !editingDimension.value.scale_max ||
        editingDimension.value.scale_min >= editingDimension.value.scale_max) {
      alert('Please set valid scale range (min < max)');
      return;
    }
  }

  if (editingIndex.value >= 0) {
    // Update existing dimension
    form.dimensions[editingIndex.value] = { ...editingDimension.value };
  } else {
    // Add new dimension
    editingDimension.value.display_order = form.dimensions.length;
    form.dimensions.push({ ...editingDimension.value });
  }

  closeDimensionModal();
};

// Close modal
const closeDimensionModal = () => {
  editingDimension.value = null;
  editingIndex.value = -1;
  showDimensionModal.value = false;
};

// Dimension value management
const addDimensionValue = () => {
  if (editingDimension.value && editingDimension.value.dimension_type === 'categorical') {
    editingDimension.value.values.push({
      value: '',
      label: '',
      display_order: editingDimension.value.values.length
    });
  }
};

const removeDimensionValue = (index: number) => {
  if (editingDimension.value && editingDimension.value.values.length > 1) {
    editingDimension.value.values.splice(index, 1);
    // Reorder display_order
    editingDimension.value.values.forEach((val, idx) => {
      val.display_order = idx;
    });
  }
};

// Watch for dimension type changes
watch(() => editingDimension.value?.dimension_type, (newType) => {
  if (editingDimension.value) {
    if (newType === 'categorical' && editingDimension.value.values.length === 0) {
      editingDimension.value.values = [
        { value: '', label: '', display_order: 0 }
      ];
    } else if (newType === 'numeric_scale') {
      editingDimension.value.scale_min = 1;
      editingDimension.value.scale_max = 5;
      editingDimension.value.values = [];
    }
  }
});

// Submit step 2
const submitStepTwo = () => {
  if (isFormValid.value) {
    form.post(route('admin.projects.store-step-two', props.project.id));
  }
};

// Go back to step 1
const goToPreviousStep = () => {
  window.location.href = route('admin.projects.edit', props.project.id);
};

</script>

<template>
  <Head title="Configure Dimensions" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 overflow-x-auto">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold tracking-tight">Configure Annotation Dimensions</h1>
          <p class="text-muted-foreground">
            Step {{ currentStep }} of {{ totalSteps }}: Define what annotators will evaluate
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
            <span class="ml-2 text-sm font-medium text-blue-600">Dimensions</span>
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
            <div>
              <h3 class="font-medium">{{ project.name }}</h3>
              <p class="text-sm text-muted-foreground">Audio Annotation Project</p>
            </div>
            <Badge variant="outline">{{ project.status }}</Badge>
          </div>
        </CardContent>
      </Card>

      <!-- Dimensions Configuration -->
      <Card>
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle class="flex items-center gap-2">
              <Settings class="h-5 w-5" />
              Annotation Dimensions
            </CardTitle>
            <Button @click="addNewDimension" size="sm">
              <Plus class="mr-2 h-4 w-4" />
              Add Dimension
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <Alert class="mb-6">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
              Define the aspects that annotators will evaluate for each audio file. 
              You can create categorical dimensions (e.g., Gender: Male/Female/Other) 
              or numeric scales (e.g., Quality: 1-5 rating).
            </AlertDescription>
          </Alert>

          <!-- Existing Dimensions -->
          <div v-if="form.dimensions.length > 0" class="space-y-4">
            <div 
              v-for="(dimension, index) in form.dimensions" 
              :key="index"
              class="border rounded-lg p-4 hover:bg-gray-50 transition-colors"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <GripVertical class="h-4 w-4 text-gray-400 cursor-move" />
                    <h3 class="font-medium">{{ dimension.name }}</h3>
                    <Badge variant="outline">
                      {{ dimension.dimension_type === 'categorical' ? 'Categorical' : 'Numeric Scale' }}
                    </Badge>
                    <Badge v-if="dimension.is_required" variant="secondary">Required</Badge>
                  </div>
                  
                  <p v-if="dimension.description" class="text-sm text-muted-foreground mb-2">
                    {{ dimension.description }}
                  </p>
                  
                  <!-- Categorical Values -->
                  <div v-if="dimension.dimension_type === 'categorical'" class="flex flex-wrap gap-2">
                    <Badge 
                      v-for="value in dimension.values" 
                      :key="value.display_order" 
                      variant="outline"
                      class="text-xs"
                    >
                      {{ value.label || value.value }}
                    </Badge>
                  </div>
                  
                  <!-- Numeric Scale -->
                  <div v-else class="text-sm text-muted-foreground">
                    Scale: {{ dimension.scale_min }} to {{ dimension.scale_max }}
                  </div>
                </div>
                
                <div class="flex items-center gap-2">
                  <Button @click="editDimension(dimension, index)" variant="outline" size="sm">
                    Edit
                  </Button>
                  <Button @click="deleteDimension(index)" variant="destructive" size="sm">
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="text-center py-8 border-2 border-dashed border-gray-300 rounded-lg">
            <Settings class="mx-auto h-8 w-8 text-gray-400 mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">No dimensions defined</h3>
            <p class="text-gray-600 mb-4">
              Add annotation dimensions to define what annotators will evaluate
            </p>
            <Button @click="addNewDimension">
              <Plus class="mr-2 h-4 w-4" />
              Add Your First Dimension
            </Button>
          </div>

          <!-- Validation Error -->
          <Alert v-if="!isFormValid && form.dimensions.length > 0" variant="destructive" class="mt-4">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>
              Please ensure all dimensions have names and categorical dimensions have at least one value.
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

    <!-- Dimension Modal -->
    <Dialog :open="showDimensionModal" @update:open="showDimensionModal = $event">
      <DialogContent v-if="editingDimension">
        <DialogHeader>
          <DialogTitle>
            {{ editingIndex >= 0 ? 'Edit' : 'Add' }} Annotation Dimension
          </DialogTitle>
        </DialogHeader>
        <div class="space-y-4">
          <!-- Dimension Name -->
          <div class="space-y-2">
            <Label>Dimension Name *</Label>
            <Input
              v-model="editingDimension!.name"
              placeholder="e.g., Gender, Emotion, Audio Quality"
            />
          </div>

          <!-- Description -->
          <div class="space-y-2">
            <Label>Description</Label>
            <Textarea
              v-model="editingDimension!.description"
              placeholder="Describe what annotators should evaluate..."
              rows="2"
            />
          </div>

          <!-- Dimension Type -->
          <div class="space-y-2">
            <Label>Type</Label>
            <Select v-model="editingDimension!.dimension_type">
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="categorical">Categorical (predefined options)</SelectItem>
                <SelectItem value="numeric_scale">Numeric Scale (rating)</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <!-- Categorical Values -->
          <div v-if="editingDimension?.dimension_type === 'categorical'" class="space-y-4">
            <div class="flex items-center justify-between">
              <Label>Values</Label>
              <Button @click="addDimensionValue" variant="outline" size="sm">
                <Plus class="mr-2 h-4 w-4" />
                Add Value
              </Button>
            </div>
            
            <div class="space-y-2">
              <div 
                v-for="(value, index) in editingDimension.values" 
                :key="index"
                class="flex items-center gap-2"
              >
                <GripVertical class="h-4 w-4 text-gray-400" />
                <Input
                  v-model="value.value"
                  placeholder="Value (e.g., male)"
                  class="flex-1"
                />
                <Input
                  v-model="value.label"
                  placeholder="Label (e.g., Male)"
                  class="flex-1"
                />
                <Button
                  @click="removeDimensionValue(index)"
                  variant="destructive"
                  size="sm"
                  :disabled="editingDimension.values.length <= 1"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>

          <!-- Numeric Scale -->
          <div v-if="editingDimension?.dimension_type === 'numeric_scale'" class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Minimum Value</Label>
              <Input
                v-model.number="editingDimension.scale_min"
                type="number"
                min="1"
                max="10"
              />
            </div>
            <div class="space-y-2">
              <Label>Maximum Value</Label>
              <Input
                v-model.number="editingDimension.scale_max"
                type="number"
                :min="(editingDimension.scale_min || 1) + 1"
                max="10"
              />
            </div>
          </div>

          <!-- Required Toggle -->
          <div class="flex items-center space-x-2">
            <input
              type="checkbox"
              v-model="editingDimension!.is_required"
              class="rounded"
              id="required"
            />
            <Label for="required">Required dimension</Label>
          </div>

          <!-- Modal Actions -->
          <div class="flex items-center justify-end gap-2 pt-4 border-t">
            <Button @click="closeDimensionModal" variant="outline">
              Cancel
            </Button>
            <Button @click="saveDimension">
              <Save class="mr-2 h-4 w-4" />
              Save Dimension
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

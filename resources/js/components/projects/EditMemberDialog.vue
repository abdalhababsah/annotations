<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';

interface Member {
  id: number;
  user: {
    id: number;
    name: string;
    email: string;
  };
  role: string;
  is_active: boolean;
  workload_limit: number | null;
}

const props = defineProps<{
  projectId: number;
  member: Member | null;
  open: boolean;
}>();

const emit = defineEmits(['update:open', 'memberUpdated']);

// Prevent body scrolling when dialog is open
const preventBodyScroll = (isOpen: boolean) => {
  if (isOpen) {
    document.body.classList.add('overflow-hidden');
  } else {
    document.body.classList.remove('overflow-hidden');
  }
};

// Watch for open prop changes
watch(() => props.open, (newVal) => {
  preventBodyScroll(newVal);
}, { immediate: true });

// Clean up on component unmount
onUnmounted(() => {
  document.body.classList.remove('overflow-hidden');
});

const form = useForm({
  role: props.member?.role ?? '',
  workload_limit: props.member?.workload_limit ?? null,
  is_active: props.member?.is_active ?? true,
});

const submit = () => {
  if (!props.member) return;
  
  form.patch(route('admin.projects.members.update', [props.projectId, props.member.id]), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      emit('memberUpdated', { 
        id: props.member!.id, 
        role: form.role, 
        workload_limit: form.workload_limit,
        is_active: form.is_active 
      });
      emit('update:open', false);
      
      // Reset the form for next use
      form.reset();
      form.clearErrors();
    },
  });
};
</script>

<template>
  <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
    <DialogContent class="sm:max-w-md fixed-dialog">
      <DialogHeader>
        <DialogTitle>Edit Team Member</DialogTitle>
        <DialogDescription v-if="member">
          Editing {{ member.user.name }}
        </DialogDescription>
      </DialogHeader>
      
      <form v-if="member" @submit.prevent="submit" class="space-y-4 py-4">
        <div class="space-y-2">
          <Label for="role">Role</Label>
          <Select v-model="form.role">
            <SelectTrigger>
              <SelectValue placeholder="Select a role" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="annotator">Annotator</SelectItem>
              <SelectItem value="reviewer">Reviewer</SelectItem>
              <SelectItem value="project_admin">Project Admin</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="form.errors.role" class="text-sm text-red-600">{{ form.errors.role }}</p>
        </div>
        
        <div class="space-y-2">
          <Label for="workload_limit">Workload Limit</Label>
          <Input 
            type="number" 
            id="workload_limit"
            v-model="form.workload_limit as any" 
            min="1" 
            max="50" 
            placeholder="Maximum tasks (optional)" 
          />
          <p v-if="form.errors.workload_limit" class="text-sm text-red-600">{{ form.errors.workload_limit }}</p>
        </div>
        
        <div class="space-y-2">
          <Label for="is_active">Status</Label>
          <Select v-model="form.is_active as any">
            <SelectTrigger>
              <SelectValue placeholder="Select status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="true as any">Active</SelectItem>
              <SelectItem :value="false as any">Inactive</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="form.errors.is_active" class="text-sm text-red-600">{{ form.errors.is_active }}</p>
        </div>
      
        <DialogFooter class="pt-4">
          <Button type="button" variant="outline" @click="emit('update:open', false)">Cancel</Button>
          <Button type="submit" :disabled="form.processing">Save Changes</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>

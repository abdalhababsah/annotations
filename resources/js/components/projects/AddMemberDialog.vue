<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
  projectId: number;
  open: boolean;
}>();

const emit = defineEmits(['update:open', 'memberAdded']);

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
  user_id: '',
  role: '',
  workload_limit: null as number | null,
});

const submit = () => {
  form.post(route('admin.projects.members.store', props.projectId), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: (response) => {
      // Get the newly created member from response if available
      const newMember = response?.props?.member;
      emit('memberAdded', newMember);
      emit('update:open', false);
      
      // Reset the form
      form.reset();
    },
  });
};
</script>

<template>
  <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
    <DialogContent class="sm:max-w-md fixed-dialog">
      <DialogHeader>
        <DialogTitle>Add Team Member</DialogTitle>
        <DialogDescription>
          Add a user to this project team
        </DialogDescription>
      </DialogHeader>
      
      <form @submit.prevent="submit" class="space-y-4 py-4">
        <div class="space-y-2">
          <Label for="user_id">Select User</Label>
          <Select v-model="form.user_id">
            <SelectTrigger>
              <SelectValue placeholder="Select a user" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="1">User 1</SelectItem>
              <SelectItem value="2">User 2</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="form.errors.user_id" class="text-sm text-red-600">{{ form.errors.user_id }}</p>
        </div>
        
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
      
        <DialogFooter class="pt-4">
          <Button type="button" variant="outline" @click="emit('update:open', false)">Cancel</Button>
          <Button type="submit" :disabled="form.processing">Add Member</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>

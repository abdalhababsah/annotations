<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { 
  Dialog, DialogContent, DialogDescription, 
  DialogFooter, DialogHeader, DialogTitle 
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { UserPlus, Mail, User } from 'lucide-vue-next'
import { useForm } from '@inertiajs/vue3'

interface Props {
  open: boolean
}

const props = defineProps<Props>()
const emit = defineEmits(['update:open'])

// Form state
const form = useForm({
  first_name: '',
  last_name: '',
  email: '',
  role: 'user',
  is_active: true,
  email_verified: false,
})

// Error handling
const formErrors = ref<Record<string, string>>({})

const closeDialog = () => {
  emit('update:open', false)
  form.reset()
  form.clearErrors()
  formErrors.value = {}
}

const submit = () => {
  form.post(route('admin.users.store'), {
    onSuccess: () => {
      closeDialog()
    },
    onError: (errors) => {
      formErrors.value = errors
    },
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <UserPlus class="h-5 w-5" />
          Create New User
        </DialogTitle>
        <DialogDescription>
          Create a new user account. A password reset link will be sent to the user's email.
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="submit" class="space-y-6">
        <div class="space-y-4">
          <!-- First Name -->
          <div class="space-y-2">
            <Label for="first_name">First Name</Label>
            <div class="relative">
              <User class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                id="first_name"
                v-model="form.first_name"
                placeholder="First name"
                class="pl-10"
                :class="{ 'border-red-500': form.errors.first_name }"
              />
            </div>
            <p v-if="form.errors.first_name" class="text-sm text-red-500">{{ form.errors.first_name }}</p>
          </div>

          <!-- Last Name -->
          <div class="space-y-2">
            <Label for="last_name">Last Name</Label>
            <div class="relative">
              <User class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                id="last_name"
                v-model="form.last_name"
                placeholder="Last name"
                class="pl-10"
                :class="{ 'border-red-500': form.errors.last_name }"
              />
            </div>
            <p v-if="form.errors.last_name" class="text-sm text-red-500">{{ form.errors.last_name }}</p>
          </div>

          <!-- Email -->
          <div class="space-y-2">
            <Label for="email">Email</Label>
            <div class="relative">
              <Mail class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="user@example.com"
                class="pl-10"
                :class="{ 'border-red-500': form.errors.email }"
              />
            </div>
            <p v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</p>
          </div>

          <!-- Role -->
          <div class="space-y-2">
            <Label for="role">Role</Label>
            <Select v-model="form.role">
              <SelectTrigger id="role" :class="{ 'border-red-500': form.errors.role }">
                <SelectValue placeholder="Select a role" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="user">Regular User</SelectItem>
                <SelectItem value="project_owner">Project Owner</SelectItem>
                <SelectItem value="system_admin">System Admin</SelectItem>
              </SelectContent>
            </Select>
            <p v-if="form.errors.role" class="text-sm text-red-500">{{ form.errors.role }}</p>
          </div>

          <!-- Active Status -->
          <div class="flex items-center justify-between space-y-0">
            <Label for="is_active" class="flex-1">Active Account</Label>
            <Switch id="is_active" v-model="form.is_active" />
          </div>

          <!-- Email Verified -->
          <div class="flex items-center justify-between space-y-0">
            <Label for="email_verified" class="flex-1">Email Verified</Label>
            <Switch id="email_verified" v-model="form.email_verified" />
          </div>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" @click="closeDialog" :disabled="form.processing">
            Cancel
          </Button>
          <Button type="submit" :disabled="form.processing">
            <span v-if="form.processing">Creating...</span>
            <span v-else>Create User</span>
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
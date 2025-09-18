<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import type { PropType } from 'vue'
import Dialog from '../ui/dialog/Dialog.vue'
import DialogContent from '../ui/dialog/DialogContent.vue'
import DialogHeader from '../ui/dialog/DialogHeader.vue'
import DialogTitle from '../ui/dialog/DialogTitle.vue'
import { Mail, User, UserPlus } from 'lucide-vue-next'
import DialogDescription from '../ui/dialog/DialogDescription.vue'
import Select from '../ui/select/Select.vue'
import SelectTrigger from '../ui/select/SelectTrigger.vue'
import SelectValue from '../ui/select/SelectValue.vue'
import SelectContent from '../ui/select/SelectContent.vue'
import SelectItem from '../ui/select/SelectItem.vue'
import Switch from '../ui/switch/Switch.vue'
import Label from '../ui/label/Label.vue'
import DialogFooter from '../ui/dialog/DialogFooter.vue'
import Button from '../ui/button/Button.vue'
import Input from '../ui/input/Input.vue'

interface UserData {
  id: number
  first_name: string
  last_name: string
  email: string
  role: string
  is_active: boolean
  email_verified_at: string | null
}

const props = defineProps({
  open: { type: Boolean, required: true },
  user: { type: Object as PropType<UserData | null>, default: null }
})

const emit = defineEmits(['update:open'])

// local form state
const form = ref({
  id: null as number | null,
  first_name: '',
  last_name: '',
  email: '',
  role: 'user',
  is_active: true,
  email_verified: false, // Added email_verified
})

watch(() => props.user, (newUser) => {
  if (!newUser) {
    form.value = {
      id: null,
      first_name: '',
      last_name: '',
      email: '',
      role: 'user',
      is_active: true,
      email_verified: false
    }
    return
  }

  form.value = {
    id: newUser.id,
    first_name: newUser.first_name,
    last_name: newUser.last_name,
    email: newUser.email,
    role: newUser.role || 'user',
    is_active: !!newUser.is_active,
    email_verified: !!newUser.email_verified_at, // Convert to boolean
  }
}, { immediate: true })

const close = () => emit('update:open', false)

const submit = () => {
  if (!form.value.id) return
  router.put(route('admin.users.update', form.value.id), {
    first_name: form.value.first_name,
    last_name: form.value.last_name,
    email: form.value.email,
    role: form.value.role,
    is_active: form.value.is_active,
    email_verified: form.value.email_verified, // Include email_verified in payload
  }, {
    preserveState: true,
    onFinish: () => {
      close()
    }
  })
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <UserPlus class="h-5 w-5" />
          Edit User
        </DialogTitle>
        <DialogDescription>
          Update the user's details and status.
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
              />
            </div>
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
              />
            </div>
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
              />
            </div>
          </div>

          <!-- Role -->
          <div class="space-y-2">
            <Label for="role">Role</Label>
            <Select v-model="form.role">
              <SelectTrigger id="role">
                <SelectValue placeholder="Select a role" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="user">Regular User</SelectItem>
                <SelectItem value="project_owner">Project Owner</SelectItem>
                <SelectItem value="system_admin">System Admin</SelectItem>
              </SelectContent>
            </Select>
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
          <Button type="button" variant="outline" @click="close">
            Cancel
          </Button>
          <Button type="submit">
            Update User
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
</script>

<template>
    <AuthBase title="Create an account" description="Enter your details below to create your account">
        <Head title="Register" />

        <Form
            method="post"
            :action="route('register')"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-3">
                <div class="grid gap-2">
                    <Label for="first_name">First Name</Label>
                    <Input 
                        id="first_name" 
                        type="text" 
                        required 
                        autofocus 
                        :tabindex="1" 
                        autocomplete="given-name" 
                        name="first_name" 
                        placeholder="First name" 
                    />
                    <InputError :message="errors.first_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="last_name">Last Name</Label>
                    <Input 
                        id="last_name" 
                        type="text" 
                        required 
                        :tabindex="2" 
                        autocomplete="family-name" 
                        name="last_name" 
                        placeholder="Last name" 
                    />
                    <InputError :message="errors.last_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email_prefix">Email address</Label>
                    <div class="flex items-center border rounded-md overflow-hidden focus-within:ring-2 focus-within:ring-ring">
                        <Input 
                            id="email_prefix" 
                            type="text" 
                            required 
                            :tabindex="3" 
                            autocomplete="username" 
                            name="email_prefix" 
                            placeholder="user" 
                            class="border-0 focus-visible:ring-0 rounded-none pl-3"
                        />
                        <span class="px-3 py-2 bg-muted text-muted-foreground whitespace-nowrap">@menadevs.io</span>
                    </div>
                    <InputError :message="errors.email_prefix" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input 
                        id="password" 
                        type="password" 
                        required 
                        :tabindex="4" 
                        autocomplete="new-password" 
                        name="password" 
                        placeholder="Password" 
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button type="submit" class="w-full mt-2" tabindex="6" :disabled="processing">
                    <LoaderCircle v-if="processing" class="w-4 h-4 animate-spin" />
                    Create account
                </Button>
            </div>

            <div class="text-sm text-center text-muted-foreground">
                Already have an account?
                <TextLink :href="route('login')" class="underline underline-offset-4" :tabindex="7">Log in</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>

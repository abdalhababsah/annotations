<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
  Sidebar, SidebarContent, SidebarFooter, SidebarHeader,
  SidebarMenu, SidebarMenuButton, SidebarMenuItem
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { BookOpen, Folder, FolderPlus, LayoutGrid } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

// Read role from Inertia shared props
const page = usePage<{ auth: { user: { role?: string } } }>();
const userRole = computed(() => page.props.auth?.user?.role ?? null);

// Build main nav based on role
const mainNavItems = computed<NavItem[]>(() => {
  const items: NavItem[] = [
    { title: 'Dashboard', href: route('dashboard'), icon: LayoutGrid },
  ];

  if (userRole.value === 'system_admin') {
    items.push(
      { title: 'Projects', href: route('admin.projects.index'), icon: Folder },
      { title: 'Create Project', href: route('admin.projects.create'), icon: FolderPlus },
    );
  }

  return items;
});

// (Optional) Footer links, unchanged
const footerNavItems: NavItem[] = [
  { title: 'Github Repo', href: 'https://github.com/laravel/vue-starter-kit', icon: Folder },
  { title: 'Documentation', href: 'https://laravel.com/docs/starter-kits#vue', icon: BookOpen },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="floating">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" as-child>
              <Link :href="route('dashboard')">
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
  
      <SidebarContent>
        <NavMain :items="mainNavItems" />
      </SidebarContent>
  
      <SidebarFooter>
        <!-- <NavFooter :items="footerNavItems" /> -->
        <NavUser />
      </SidebarFooter>
    </Sidebar>
    <slot />
  </template>
  
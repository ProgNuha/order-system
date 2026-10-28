<script setup lang="ts">
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { logout as logoutApi } from '@/api/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const links = [
  { to: '/', label: 'Orders' },
  { to: '/products', label: 'Produk' },
  { to: '/customers', label: 'Pelanggan' },
]

async function onLogout() {
  try {
    await logoutApi()
  } catch {
    // token mungkin udah expired di server, tetep lanjut clear di client
  }
  auth.clear()
  router.push('/login')
}
</script>

<template>
  <nav class="bg-white border-b px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-6">
      <span class="font-semibold">Order System</span>
      <router-link
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        class="text-sm"
        :class="route.path === link.to ? 'text-blue-600 font-medium' : 'text-gray-600 hover:text-gray-900'"
      >
        {{ link.label }}
      </router-link>
    </div>
    <div class="flex items-center gap-3 text-sm">
      <span class="text-gray-500">
        {{ auth.user?.name }}
        <span class="px-1.5 py-0.5 rounded bg-gray-100 text-xs ml-1">{{ auth.user?.role }}</span>
      </span>
      <button @click="onLogout" class="text-red-600 hover:underline">Keluar</button>
    </div>
  </nav>
</template>
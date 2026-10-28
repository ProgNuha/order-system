<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import NavBar from '@/components/NavBar.vue'

const route = useRoute()
const auth = useAuthStore()
</script>

<template>
  <NavBar v-if="auth.token && route.name !== 'login'" />
  <router-view />
</template>
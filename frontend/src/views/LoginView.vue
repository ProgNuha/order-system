<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation } from '@tanstack/vue-query'
import { login } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'

const email = ref('')
const password = ref('')
const router = useRouter()
const auth = useAuthStore()

const { mutate, isPending, error } = useMutation({
  mutationFn: login,
  onSuccess: (res) => {
    auth.setAuth(res.data.data.token, res.data.data.user)
    router.push('/')
  },
})

function onSubmit() {
  mutate({ email: email.value, password: password.value })
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <form @submit.prevent="onSubmit" class="bg-white p-8 rounded-lg shadow w-full max-w-sm space-y-4">
      <h1 class="text-xl font-semibold">Masuk</h1>

      <div>
        <label for="email" class="block text-sm font-medium">Email</label>
        <input
          id="email"
          v-model="email"
          type="email"
          required
          class="mt-1 w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <div>
        <label for="password" class="block text-sm font-medium">Password</label>
        <input
          id="password"
          v-model="password"
          type="password"
          required
          class="mt-1 w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <p v-if="error" class="text-red-600 text-sm" role="alert">
        Email atau password salah.
      </p>

      <button
        type="submit"
        :disabled="isPending"
        class="w-full bg-blue-600 text-white py-2 rounded disabled:opacity-50"
      >
        {{ isPending ? 'Memproses...' : 'Masuk' }}
      </button>
    </form>
  </div>
</template>
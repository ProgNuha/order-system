<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { getOrders } from '@/api/orders'
import type { OrderStatus } from '@/types/api'

const route = useRoute()
const router = useRouter()

const status = computed(() => (route.query.status as OrderStatus) || undefined)
const page = computed(() => Number(route.query.page) || 1)

const { data, isLoading, isError } = useQuery({
  queryKey: ['orders', { status: status.value, page: page.value }],
  queryFn: () => getOrders({ status: status.value, page: page.value, per_page: 10 }),
})

const orders = computed(() => data.value?.data.data ?? [])
const meta = computed(() => data.value?.data.meta)

function setStatus(newStatus: string) {
  router.push({ query: { ...route.query, status: newStatus || undefined, page: undefined } })
}

function goToPage(p: number) {
  router.push({ query: { ...route.query, page: p } })
}

const statusColors: Record<OrderStatus, string> = {
  PENDING: 'bg-yellow-100 text-yellow-800',
  PAID: 'bg-blue-100 text-blue-800',
  SHIPPED: 'bg-purple-100 text-purple-800',
  COMPLETED: 'bg-green-100 text-green-800',
  CANCELLED: 'bg-red-100 text-red-800',
}
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">Orders</h1>
      <router-link
        to="/orders/new"
        class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium"
      >
        + Buat Order
      </router-link>
    </div>

    <select
      :value="status || ''"
      @change="setStatus(($event.target as HTMLSelectElement).value)"
      class="border rounded px-3 py-1.5 text-sm mb-4"
      aria-label="Filter status order"
    >
      <option value="">Semua status</option>
      <option value="PENDING">PENDING</option>
      <option value="PAID">PAID</option>
      <option value="SHIPPED">SHIPPED</option>
      <option value="COMPLETED">COMPLETED</option>
      <option value="CANCELLED">CANCELLED</option>
    </select>

    <div v-if="isLoading" class="text-gray-500 text-sm py-8 text-center">Memuat...</div>
    <div v-else-if="isError" class="text-red-600 text-sm py-8 text-center" role="alert">
      Gagal memuat order.
    </div>
    <div v-else-if="orders.length === 0" class="text-gray-500 text-sm py-8 text-center">
      Belum ada order.
    </div>
    <table v-else class="w-full text-sm border-collapse">
      <thead>
        <tr class="text-left border-b">
          <th class="py-2 font-medium">Order #</th>
          <th class="py-2 font-medium">Status</th>
          <th class="py-2 font-medium">Total</th>
          <th class="py-2 font-medium">Dibuat</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="order in orders"
          :key="order.id"
          class="border-b hover:bg-gray-50 cursor-pointer"
          @click="router.push(`/orders/${order.id}`)"
        >
          <td class="py-2">{{ order.order_number }}</td>
          <td class="py-2">
            <span class="px-2 py-0.5 rounded text-xs font-medium" :class="statusColors[order.status]">
              {{ order.status }}
            </span>
          </td>
          <td class="py-2">Rp {{ Number(order.total_amount).toLocaleString('id-ID') }}</td>
          <td class="py-2">{{ new Date(order.created_at).toLocaleDateString('id-ID') }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="meta && meta.total_pages > 1" class="flex gap-2 mt-4 justify-center">
      <button
        v-for="p in meta.total_pages"
        :key="p"
        @click="goToPage(p)"
        class="px-3 py-1 rounded text-sm"
        :class="p === meta.page ? 'bg-blue-600 text-white' : 'bg-gray-100'"
      >
        {{ p }}
      </button>
    </div>
  </div>
</template>
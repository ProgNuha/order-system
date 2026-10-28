<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { getOrder, payOrder, shipOrder, completeOrder, cancelOrder } from '@/api/orders'
import { useAuthStore } from '@/stores/auth'
import type { OrderStatus } from '@/types/api'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const auth = useAuthStore()

const orderId = computed(() => Number(route.params.id))

const { data, isLoading, isError } = useQuery({
  queryKey: ['orders', orderId],
  queryFn: () => getOrder(orderId.value),
})

const order = computed(() => data.value?.data.data)

const statusColors: Record<OrderStatus, string> = {
  PENDING: 'bg-yellow-100 text-yellow-800',
  PAID: 'bg-blue-100 text-blue-800',
  SHIPPED: 'bg-purple-100 text-purple-800',
  COMPLETED: 'bg-green-100 text-green-800',
  CANCELLED: 'bg-red-100 text-red-800',
}

function invalidateAfterAction() {
  queryClient.invalidateQueries({ queryKey: ['orders'] })
}

const { mutate: pay, isPending: isPaying } = useMutation({
  mutationFn: () => payOrder(orderId.value),
  onSuccess: invalidateAfterAction,
})
const { mutate: ship, isPending: isShipping } = useMutation({
  mutationFn: () => shipOrder(orderId.value),
  onSuccess: invalidateAfterAction,
})
const { mutate: complete, isPending: isCompleting } = useMutation({
  mutationFn: () => completeOrder(orderId.value),
  onSuccess: invalidateAfterAction,
})

const showCancelForm = ref(false)
const cancelReason = ref('')
const { mutate: cancel, isPending: isCancelling } = useMutation({
  mutationFn: () => cancelOrder(orderId.value, cancelReason.value),
  onSuccess: () => {
    invalidateAfterAction()
    showCancelForm.value = false
    cancelReason.value = ''
  },
})

const role = computed(() => auth.user?.role)
const canPay = computed(() => order.value?.status === 'PENDING' && (role.value === 'admin' || role.value === 'cashier'))
const canShip = computed(() => order.value?.status === 'PAID' && role.value === 'admin')
const canComplete = computed(() => order.value?.status === 'SHIPPED' && role.value === 'admin')
const canCancel = computed(
  () => ['PENDING', 'PAID'].includes(order.value?.status ?? '') && role.value === 'admin'
)
</script>

<template>
  <div class="p-6 max-w-2xl mx-auto">
    <button @click="router.push('/')" class="text-sm text-blue-600 mb-4">&larr; Kembali</button>

    <div v-if="isLoading" class="text-gray-500 text-sm py-8 text-center">Memuat...</div>
    <div v-else-if="isError || !order" class="text-red-600 text-sm py-8 text-center" role="alert">
      Order tidak ditemukan.
    </div>

    <template v-else>
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ order.order_number }}</h1>
        <span class="px-3 py-1 rounded text-sm font-medium" :class="statusColors[order.status]">
          {{ order.status }}
        </span>
      </div>

      <div class="bg-white border rounded-lg p-4 mb-4">
        <h2 class="text-sm font-medium mb-2">Item</h2>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left border-b text-gray-500">
              <th class="py-1 font-medium">Produk</th>
              <th class="py-1 font-medium">Qty</th>
              <th class="py-1 font-medium">Harga</th>
              <th class="py-1 font-medium">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.items" :key="item.id" class="border-b last:border-0">
              <td class="py-1.5">{{ item.product_name }}</td>
              <td class="py-1.5">{{ item.quantity }}</td>
              <td class="py-1.5">Rp {{ Number(item.unit_price).toLocaleString('id-ID') }}</td>
              <td class="py-1.5">Rp {{ Number(item.subtotal).toLocaleString('id-ID') }}</td>
            </tr>
          </tbody>
        </table>
        <div class="text-right mt-2 font-semibold text-sm">
          Total: Rp {{ Number(order.total_amount).toLocaleString('id-ID') }}
        </div>
      </div>

      <div class="bg-white border rounded-lg p-4 mb-4">
        <h2 class="text-sm font-medium mb-2">Riwayat status</h2>
        <ul class="text-sm space-y-1">
          <li v-for="(h, i) in order.status_history" :key="i" class="flex justify-between text-gray-600">
            <span>{{ h.status }}</span>
            <span>{{ new Date(h.at).toLocaleString('id-ID') }}</span>
          </li>
        </ul>
        <p v-if="order.cancel_reason" class="text-sm text-red-600 mt-2">
          Alasan cancel: {{ order.cancel_reason }}
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          v-if="canPay"
          @click="pay()"
          :disabled="isPaying"
          class="bg-blue-600 text-white px-4 py-2 rounded text-sm disabled:opacity-50"
        >
          {{ isPaying ? 'Memproses...' : 'Tandai Dibayar' }}
        </button>
        <button
          v-if="canShip"
          @click="ship()"
          :disabled="isShipping"
          class="bg-purple-600 text-white px-4 py-2 rounded text-sm disabled:opacity-50"
        >
          {{ isShipping ? 'Memproses...' : 'Kirim' }}
        </button>
        <button
          v-if="canComplete"
          @click="complete()"
          :disabled="isCompleting"
          class="bg-green-600 text-white px-4 py-2 rounded text-sm disabled:opacity-50"
        >
          {{ isCompleting ? 'Memproses...' : 'Selesaikan' }}
        </button>
        <button
          v-if="canCancel && !showCancelForm"
          @click="showCancelForm = true"
          class="bg-red-50 text-red-600 border border-red-200 px-4 py-2 rounded text-sm"
        >
          Batalkan
        </button>
        <button
          disabled
          title="Belum tersedia"
          class="bg-gray-100 text-gray-400 px-4 py-2 rounded text-sm cursor-not-allowed"
        >
          Unduh Invoice
        </button>
      </div>

      <div v-if="showCancelForm" class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
        <label for="cancel_reason" class="block text-sm font-medium mb-1">Alasan pembatalan</label>
        <textarea
          id="cancel_reason"
          v-model="cancelReason"
          required
          class="w-full border rounded px-3 py-2 text-sm"
          rows="2"
        />
        <div class="flex gap-2 mt-2">
          <button
            @click="cancel()"
            :disabled="isCancelling || !cancelReason.trim()"
            class="bg-red-600 text-white px-4 py-2 rounded text-sm disabled:opacity-50"
          >
            {{ isCancelling ? 'Memproses...' : 'Konfirmasi Batalkan' }}
          </button>
          <button @click="showCancelForm = false" class="px-4 py-2 rounded text-sm border">Batal</button>
        </div>
      </div>
    </template>
  </div>
</template>
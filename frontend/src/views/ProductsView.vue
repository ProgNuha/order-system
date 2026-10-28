<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { getProducts } from '@/api/products'

const route = useRoute()
const router = useRouter()

const search = ref((route.query.search as string) || '')
const sort = computed(() => (route.query.sort as string) || 'product_name')
const direction = computed(() => (route.query.direction as string) || 'asc')
const page = computed(() => Number(route.query.page) || 1)

let debounceTimer: ReturnType<typeof setTimeout>
watch(search, (val) => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.push({ query: { ...route.query, search: val || undefined, page: undefined } })
  }, 400)
})

const { data, isLoading, isError } = useQuery({
  queryKey: ['products', { search: route.query.search, sort: sort.value, direction: direction.value, page: page.value }],
  queryFn: () =>
    getProducts({
      search: (route.query.search as string) || undefined,
      sort: sort.value as any,
      direction: direction.value as any,
      page: page.value,
      per_page: 10,
    }),
})

const products = computed(() => data.value?.data.data ?? [])
const meta = computed(() => data.value?.data.meta)

function toggleSort(col: string) {
  const newDirection = sort.value === col && direction.value === 'asc' ? 'desc' : 'asc'
  router.push({ query: { ...route.query, sort: col, direction: newDirection, page: undefined } })
}

function goToPage(p: number) {
  router.push({ query: { ...route.query, page: p } })
}

const columns = [
  { key: 'product_code', label: 'Kode' },
  { key: 'product_name', label: 'Nama' },
  { key: 'stock', label: 'Stok' },
  { key: 'price', label: 'Harga' },
]
</script>

<template>
  <div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-xl font-semibold mb-4">Produk</h1>

    <input
      v-model="search"
      type="search"
      placeholder="Cari produk..."
      aria-label="Cari produk"
      class="border rounded px-3 py-2 text-sm mb-4 w-full max-w-xs"
    />

    <div v-if="isLoading" class="text-gray-500 text-sm py-8 text-center">Memuat...</div>
    <div v-else-if="isError" class="text-red-600 text-sm py-8 text-center" role="alert">Gagal memuat produk.</div>
    <div v-else-if="products.length === 0" class="text-gray-500 text-sm py-8 text-center">
      Produk tidak ditemukan.
    </div>
    <table v-else class="w-full text-sm border-collapse">
      <thead>
        <tr class="text-left border-b">
          <th
            v-for="col in columns"
            :key="col.key"
            class="py-2 font-medium cursor-pointer select-none"
            @click="toggleSort(col.key)"
          >
            {{ col.label }}
            <span v-if="sort === col.key">{{ direction === 'asc' ? '↑' : '↓' }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in products" :key="p.id" class="border-b">
          <td class="py-2">{{ p.product_code }}</td>
          <td class="py-2">{{ p.product_name }}</td>
          <td class="py-2">{{ p.stock }}</td>
          <td class="py-2">Rp {{ Number(p.price).toLocaleString('id-ID') }}</td>
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
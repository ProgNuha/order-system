<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Field, ErrorMessage, useForm, useFieldArray } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { getProducts } from '@/api/products'
import { getCustomers } from '@/api/customers'
import { createOrder } from '@/api/orders'


const router = useRouter()
const queryClient = useQueryClient()

const { data: productsData } = useQuery({
  queryKey: ['products', { per_page: 100 }],
  queryFn: () => getProducts({ per_page: 100, sort: 'product_name', direction: 'asc' }),
})
const products = computed(() => productsData.value?.data.data ?? [])
const productMap = computed(() => new Map(products.value.map((p) => [p.id, p])))

const { data: customersData } = useQuery({
  queryKey: ['customers', { per_page: 100 }],
  queryFn: () => getCustomers({ per_page: 100, sort: 'customer_name', direction: 'asc' }),
})
const customers = computed(() => customersData.value?.data.data ?? [])

const schema = toTypedSchema(
  z.object({
    customer_id: z.string().min(1, 'Pilih pelanggan'),
    items: z
      .array(
        z.object({
          product_id: z.coerce.number().int().positive('Pilih produk'),
          quantity: z.coerce.number().int().min(1, 'Minimal 1'),
        })
      )
      .min(1, 'Minimal 1 item produk'),
  })
)

const { handleSubmit, setFieldError, errors } = useForm({
  validationSchema: schema,
  initialValues: { customer_id: '', items: [{ product_id: 0, quantity: 1 }] },
})

const { fields, push, remove } = useFieldArray<{ product_id: number; quantity: number }>('items')

const submitError = ref('')

const totalPreview = computed(() =>
  fields.value.reduce((sum, f) => {
    const p = productMap.value.get(f.value.product_id)
    return sum + (p ? Number(p.price) * f.value.quantity : 0)
  }, 0)
)

const { mutate, isPending } = useMutation({
  mutationFn: (payload: { customer_id: string; items: { product_id: number; quantity: number }[] }) =>
    createOrder(payload, crypto.randomUUID()),
  onSuccess: (res) => {
    queryClient.invalidateQueries({ queryKey: ['orders'] })
    queryClient.invalidateQueries({ queryKey: ['products'] })
    router.push(`/orders/${res.data.data.id}`)
  },
  onError: (err: any) => {
    const respErrors = err.response?.data?.errors
    if (respErrors) {
      submitError.value = ''
      for (const [key, messages] of Object.entries(respErrors)) {
        const veePath = key.replace(/\.(\d+)\./g, '[$1].').replace(/\.(\d+)$/, '[$1]')
        setFieldError(veePath, (messages as string[])[0])
      }
    } else {
      submitError.value = err.response?.data?.message || 'Gagal membuat order.'
    }
  },
})

const onSubmit = handleSubmit((values) => {
  submitError.value = ''
  mutate(values)
})
</script>

<template>
  <div class="p-6 max-w-2xl mx-auto">
    <h1 class="text-xl font-semibold mb-4">Buat Order</h1>

    <form @submit="onSubmit" class="space-y-6">
      <div>
        <label for="customer_id" class="block text-sm font-medium mb-1">Pelanggan</label>
        <Field as="select" id="customer_id" name="customer_id" class="w-full border rounded px-3 py-2">
          <option value="" disabled>Pilih pelanggan</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.customer_name }}</option>
        </Field>
        <p v-if="errors.customer_id" class="text-red-600 text-sm mt-1" role="alert">{{ errors.customer_id }}</p>
      </div>

      <div>
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium">Item produk</span>
          <button type="button" @click="push({ product_id: 0, quantity: 1 })" class="text-sm text-blue-600">
            + Tambah baris
          </button>
        </div>

        <div v-for="(field, idx) in fields" :key="field.key" class="flex gap-2 items-start mb-2">
          <div class="flex-1">
            <Field as="select" :name="`items[${idx}].product_id`" class="w-full border rounded px-3 py-2 text-sm">
              <option :value="0" disabled>Pilih produk</option>
              <option v-for="p in products" :key="p.id" :value="p.id">
                {{ p.product_name }} — stok {{ p.stock }} — Rp{{ Number(p.price).toLocaleString('id-ID') }}
              </option>
            </Field>
            <ErrorMessage :name="`items[${idx}].product_id`" class="text-red-600 text-xs block mt-1" />
          </div>
          <div class="w-24">
            <Field
              as="input"
              type="number"
              min="1"
              :name="`items[${idx}].quantity`"
              class="w-full border rounded px-3 py-2 text-sm"
            />
            <ErrorMessage :name="`items[${idx}].quantity`" class="text-red-600 text-xs block mt-1" />
          </div>
          <button
            type="button"
            @click="remove(idx)"
            :disabled="fields.length === 1"
            class="text-red-600 text-sm px-2 py-2 disabled:opacity-30"
            aria-label="Hapus baris"
          >
            Hapus
          </button>
        </div>
        <p v-if="errors.items" class="text-red-600 text-sm mt-1" role="alert">{{ errors.items }}</p>
      </div>

      <div class="text-right text-sm text-gray-600">
        Perkiraan total: <span class="font-semibold text-gray-900">Rp {{ totalPreview.toLocaleString('id-ID') }}</span>
        <span class="block text-xs">(total final dihitung ulang di server)</span>
      </div>

      <p v-if="submitError" class="text-red-600 text-sm" role="alert">{{ submitError }}</p>

      <button
        type="submit"
        :disabled="isPending"
        class="w-full bg-blue-600 text-white py-2 rounded disabled:opacity-50"
      >
        {{ isPending ? 'Menyimpan...' : 'Buat Order' }}
      </button>
    </form>
  </div>
</template>
import client from './client'
import type { ApiResponse, Product } from '@/types/api'

interface ListParams {
  search?: string
  sort?: 'product_name' | 'product_code' | 'price' | 'stock' | 'created_at'
  direction?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export function getProducts(params: ListParams) {
  return client.get<ApiResponse<Product[]>>('/products', { params })
}
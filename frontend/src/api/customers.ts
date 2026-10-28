import client from './client'
import type { ApiResponse, Customer } from '@/types/api'

interface ListParams {
  search?: string
  sort?: 'customer_name' | 'birth_date' | 'created_at'
  direction?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export function getCustomers(params: ListParams) {
  return client.get<ApiResponse<Customer[]>>('/customers', { params })
}
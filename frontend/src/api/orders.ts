import client from './client'
import type { ApiResponse, Order, OrderStatus } from '@/types/api'

interface OrderListParams {
  status?: OrderStatus
  customer_id?: string
  date_from?: string
  date_to?: string
  per_page?: number
  page?: number
}

export function getOrders(params: OrderListParams) {
  return client.get<ApiResponse<Order[]>>('/orders', { params })
}

export function getOrder(id: number) {
  return client.get<ApiResponse<Order>>(`/orders/${id}`)
}

interface CreateOrderPayload {
  customer_id: string
  items: { product_id: number; quantity: number }[]
}

export function createOrder(payload: CreateOrderPayload, idempotencyKey: string) {
  return client.post<ApiResponse<Order>>('/orders', payload, {
    headers: { 'Idempotency-Key': idempotencyKey },
  })
}

export function payOrder(id: number) {
  return client.post<ApiResponse<Order>>(`/orders/${id}/pay`)
}

export function shipOrder(id: number) {
  return client.post<ApiResponse<Order>>(`/orders/${id}/ship`)
}

export function completeOrder(id: number) {
  return client.post<ApiResponse<Order>>(`/orders/${id}/complete`)
}

export function cancelOrder(id: number, cancel_reason: string) {
  return client.post<ApiResponse<Order>>(`/orders/${id}/cancel`, { cancel_reason })
}
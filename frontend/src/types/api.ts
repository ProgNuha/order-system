export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
  meta?: { page: number; per_page: number; total: number; total_pages: number }
}

export interface ApiError {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export type Role = 'admin' | 'cashier'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

export interface Product {
  id: number
  product_code: string
  product_name: string
  stock: number
  price: string
  created_at: string
  updated_at: string
}

export interface Customer {
  id: string
  customer_name: string
  customer_address: string
  gender: 'Pria' | 'Wanita'
  birth_date: string
  phone: string
  email: string
  created_at: string
  updated_at: string
}

export type OrderStatus = 'PENDING' | 'PAID' | 'SHIPPED' | 'COMPLETED' | 'CANCELLED'

export interface OrderItem {
  id: number
  product_id: number
  product_name: string
  quantity: number
  unit_price: string
  subtotal: string
}

export interface Order {
  id: number
  order_number: string
  customer_id: string
  status: OrderStatus
  total_amount: string
  paid_at: string | null
  shipped_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
  created_at: string
  updated_at: string
  items?: OrderItem[]
  status_history?: { status: OrderStatus; at: string }[]
}
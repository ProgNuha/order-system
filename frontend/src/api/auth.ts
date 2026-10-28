import client from './client'
import type { ApiResponse, User } from '@/types/api'

interface LoginPayload {
  email: string
  password: string
}

interface LoginData {
  user: User
  token: string
  expires_at: string
}

export function login(payload: LoginPayload) {
  return client.post<ApiResponse<LoginData>>('/auth/login', payload)
}

export function logout() {
  return client.post('/auth/logout')
}

export function me() {
  return client.get<ApiResponse<User>>('/auth/me')
}
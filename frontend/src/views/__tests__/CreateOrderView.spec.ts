import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/vue'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import CreateOrderView from '../CreateOrderView.vue'

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
}))

vi.mock('@/api/products', () => ({
  getProducts: vi.fn(() =>
    Promise.resolve({
      data: {
        success: true,
        message: 'OK',
        data: [{ id: 1, product_code: 'P0001', product_name: 'Produk A', stock: 10, price: '10000.00', created_at: '', updated_at: '' }],
        meta: { page: 1, per_page: 100, total: 1, total_pages: 1 },
      },
    })
  ),
}))

vi.mock('@/api/customers', () => ({
  getCustomers: vi.fn(() =>
    Promise.resolve({
      data: {
        success: true,
        message: 'OK',
        data: [{ id: 'uuid-1', customer_name: 'Budi', customer_address: '', gender: 'Pria', birth_date: '', phone: '', email: '', created_at: '', updated_at: '' }],
        meta: { page: 1, per_page: 100, total: 1, total_pages: 1 },
      },
    })
  ),
}))

const createOrderMock = vi.fn(() =>
  Promise.resolve({
    data: {
      success: true,
      message: 'OK',
      data: { id: 1, order_number: 'INV-1', customer_id: 'uuid-1', status: 'PENDING', total_amount: '10000.00', paid_at: null, shipped_at: null, completed_at: null, cancelled_at: null, cancel_reason: null, created_at: '', updated_at: '', items: [], status_history: [] },
    },
  })
)
vi.mock('@/api/orders', () => ({
  createOrder: (...args: unknown[]) => createOrderMock(...args),
}))

function renderWithPlugins() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(CreateOrderView, {
    global: { plugins: [[VueQueryPlugin, { queryClient }]] },
  })
}

beforeEach(() => {
  createOrderMock.mockClear()
})

describe('CreateOrderView', () => {
  it('shows validation error when submitting without selecting a customer', async () => {
    renderWithPlugins()
    await waitFor(() => screen.getByText('Budi'))

    await fireEvent.click(screen.getByRole('button', { name: /buat order/i }))

    expect(await screen.findByText('Pilih pelanggan')).toBeInTheDocument()
    expect(createOrderMock).not.toHaveBeenCalled()
  })

  it('submits payload without price fields', async () => {
    renderWithPlugins()
    await waitFor(() => screen.getByText('Budi'))

    await fireEvent.update(screen.getByLabelText('Pelanggan'), 'uuid-1')
    await fireEvent.update(screen.getAllByRole('combobox')[1], '1')
    await fireEvent.click(screen.getByRole('button', { name: /buat order/i }))

    await waitFor(() => expect(createOrderMock).toHaveBeenCalledTimes(1))

    const [payload] = createOrderMock.mock.calls[0] as [any, string]
    expect(payload).toEqual({ customer_id: 'uuid-1', items: [{ product_id: 1, quantity: 1 }] })
  })
})
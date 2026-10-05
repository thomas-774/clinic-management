import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { getReportPayments, getReportSummary } from '../api/reports'

export const reportsKey = ['doctor', 'reports']

/** Patients seen, revenue and outstanding for today / this week / this month (FR-H.1, H.2). */
export function useReportSummary(period) {
  return useQuery({
    queryKey: [...reportsKey, 'summary', period],
    queryFn: () => getReportSummary(period),
  })
}

/** One page of the payments table with the whole range's totals (FR-H.3); keeps the old page on screen while the next loads. */
export function useReportPayments({ from, to, page }, { enabled = true } = {}) {
  return useQuery({
    queryKey: [...reportsKey, 'payments', { from, to, page }],
    queryFn: () => getReportPayments({ from, to, page }),
    placeholderData: keepPreviousData,
    enabled,
  })
}

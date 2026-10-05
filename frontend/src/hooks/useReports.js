import { useQuery } from '@tanstack/react-query'
import { getReportSummary } from '../api/reports'

export const reportsKey = ['doctor', 'reports']

/** Patients seen, revenue and outstanding for today / this week / this month (FR-H.1, H.2). */
export function useReportSummary(period) {
  return useQuery({
    queryKey: [...reportsKey, 'summary', period],
    queryFn: () => getReportSummary(period),
  })
}

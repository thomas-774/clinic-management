import { useMutation, useQueryClient } from '@tanstack/react-query'
import { addPayment, createVisit, updateVisit } from '../api/visits'
import { patientsKey } from './usePatients'
import { reportsKey } from './useReports'
import { scheduleKey } from './useSchedule'

/** Visits change balances on the patient pages, appointment statuses on the schedule and the report numbers. */
function useRefreshAfterVisit() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: patientsKey }),
      queryClient.invalidateQueries({ queryKey: scheduleKey }),
      queryClient.invalidateQueries({ queryKey: reportsKey }),
    ])
}

export function useCreateVisit() {
  return useMutation({ mutationFn: (fields) => createVisit(fields), onSuccess: useRefreshAfterVisit() })
}

export function useUpdateVisit() {
  return useMutation({ mutationFn: ({ id, ...fields }) => updateVisit(id, fields), onSuccess: useRefreshAfterVisit() })
}

export function useAddPayment() {
  return useMutation({ mutationFn: ({ visitId, ...fields }) => addPayment(visitId, fields), onSuccess: useRefreshAfterVisit() })
}

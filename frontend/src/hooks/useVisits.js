import { useMutation, useQueryClient } from '@tanstack/react-query'
import { addPayment, createVisit, updateVisit } from '../api/visits'
import { patientsKey } from './usePatients'
import { scheduleKey } from './useSchedule'

/** Visits change balances on the patient pages and appointment statuses on the schedule. */
function useRefreshAfterVisit() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: patientsKey }),
      queryClient.invalidateQueries({ queryKey: scheduleKey }),
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

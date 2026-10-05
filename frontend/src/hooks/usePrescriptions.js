import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  createPrescription,
  deletePrescription,
  getPrescription,
  listPrescriptions,
  updatePrescription,
} from '../api/prescriptions'
import { patientsKey } from './usePatients'

export const prescriptionsKey = ['doctor', 'prescriptions']

export const prescriptionKey = (id) => [...prescriptionsKey, 'one', String(id)]

/** The patient's prescriptions list (FR-J.7). */
export function usePatientPrescriptions(patientId, { enabled = true } = {}) {
  return useQuery({
    queryKey: [...prescriptionsKey, 'patient', String(patientId)],
    queryFn: () => listPrescriptions(patientId),
    enabled: enabled && Boolean(patientId),
  })
}

/** One full prescription, for the form and the print page. */
export function usePrescription(id, { enabled = true } = {}) {
  return useQuery({ queryKey: prescriptionKey(id), queryFn: () => getPrescription(id), enabled: enabled && Boolean(id) })
}

/** Prescriptions show on the patient page (list and count), so both are refreshed after a change. */
function useRefreshAfterPrescription() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: prescriptionsKey }),
      queryClient.invalidateQueries({ queryKey: patientsKey }),
    ])
}

/** Writes a new prescription (`patientId`) or edits one (`id`); resolves to the full prescription. */
export function useSavePrescription() {
  const queryClient = useQueryClient()
  const refresh = useRefreshAfterPrescription()
  return useMutation({
    mutationFn: ({ id, patientId, fields }) => (id ? updatePrescription(id, fields) : createPrescription(patientId, fields)),
    onSuccess: (prescription) => {
      queryClient.setQueryData(prescriptionKey(prescription.id), prescription)
      return refresh()
    },
  })
}

export function useDeletePrescription() {
  return useMutation({ mutationFn: (id) => deletePrescription(id), onSuccess: useRefreshAfterPrescription() })
}

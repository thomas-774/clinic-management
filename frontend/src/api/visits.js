import client from './client'

const data = (res) => res.data.data

/**
 * { patient_id, appointment_id?, work_done, total_amount, paid_now, method }
 * → the visit with paid, remaining, payment_status and payments.
 */
export const createVisit = (fields) => client.post('/doctor/visits', fields).then(data)

/** { work_done, total_amount } */
export const updateVisit = (id, fields) => client.put(`/doctor/visits/${id}`, fields).then(data)

/** An installment: { amount, method, paid_at? } → the visit with its new balance. */
export const addPayment = (visitId, fields) => client.post(`/doctor/visits/${visitId}/payments`, fields).then(data)

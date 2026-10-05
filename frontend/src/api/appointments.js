import client from './client'

const data = (res) => res.data.data

/** Patient: book { start_at } → the new appointment. */
export const bookAppointment = (startAt) => client.post('/patient/appointments', { start_at: startAt }).then(data)

/** Patient: { upcoming: [...], past: [...] }; each has can_cancel. */
export const getMyAppointments = () => client.get('/patient/appointments').then(data)

export const cancelMyAppointment = (id) => client.patch(`/patient/appointments/${id}/cancel`).then(data)

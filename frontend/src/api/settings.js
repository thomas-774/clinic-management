import client from './client'

const data = (res) => res.data.data

/** { slot_duration_minutes, booking_window_days, cancel_cutoff_hours } */
export const getSettings = () => client.get('/doctor/settings').then(data)
export const updateSettings = (fields) => client.put('/doctor/settings', fields).then(data)

/** 7 days: [{ day_of_week: 0..6, ranges: [{ start_time: 'HH:MM', end_time: 'HH:MM' }] }] */
export const getWorkingHours = () => client.get('/doctor/working-hours').then(data)
export const updateWorkingHours = (days) => client.put('/doctor/working-hours', { days }).then(data)

/** Upcoming blocks: [{ id, date, whole_day, start_time, end_time, reason }] */
export const getBlockedTimes = (params = {}) => client.get('/doctor/blocked-times', { params }).then(data)
export const addBlockedTime = (fields) => client.post('/doctor/blocked-times', fields).then(data)
export const deleteBlockedTime = (id) => client.delete(`/doctor/blocked-times/${id}`).then((res) => res.data)

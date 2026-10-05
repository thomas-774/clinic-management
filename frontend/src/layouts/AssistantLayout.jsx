import SidebarLayout from './SidebarLayout'

const LINKS = [
  { to: '/assistant', label: 'nav.today', end: true },
  { to: '/assistant/patients', label: 'nav.patients' },
  { to: '/assistant/schedule', label: 'nav.schedule' },
]

/** Assistant (front desk) area: same sidebar as the doctor's, fewer links (Module I). */
export default function AssistantLayout() {
  return <SidebarLayout id="assistant-sidebar" links={LINKS} />
}

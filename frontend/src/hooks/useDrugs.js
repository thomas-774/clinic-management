import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { getDrug, searchDrugs } from '../api/drugs'
import { useDebouncedValue } from './useDebouncedValue'

export const drugsKey = ['doctor', 'drugs']

export const SEARCH_DELAY_MS = 250
export const MIN_SEARCH_LENGTH = 2

/**
 * Drug suggestions for `q` (FR-J.2): asks the server 250 ms after the last
 * keystroke, from 2 characters, and keeps the previous results on screen
 * while the next ones load. `query` is the text the results belong to.
 */
export function useDrugSearch(q) {
  const query = useDebouncedValue(q.trim(), SEARCH_DELAY_MS)
  const enabled = query.length >= MIN_SEARCH_LENGTH

  const result = useQuery({
    queryKey: [...drugsKey, 'search', query],
    queryFn: () => searchDrugs(query),
    enabled,
    placeholderData: keepPreviousData,
    staleTime: 60_000,
  })

  return { ...result, query, enabled, drugs: enabled ? (result.data ?? []) : [] }
}

/** One full drug for the side note; catalogue entries change rarely, so it is cached for a while. */
export function useDrug(id) {
  return useQuery({
    queryKey: [...drugsKey, 'one', id],
    queryFn: () => getDrug(id),
    enabled: Boolean(id),
    staleTime: 5 * 60_000,
  })
}

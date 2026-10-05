import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { getProfile, updateProfile } from '../api/patient'

export const profileKey = ['patient', 'profile']

export function useProfile() {
  return useQuery({ queryKey: profileKey, queryFn: getProfile })
}

export function useUpdateProfile() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: updateProfile,
    onSuccess: (profile) => queryClient.setQueryData(profileKey, profile),
  })
}

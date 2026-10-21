import type { Invitation } from "#shared/types/Invitation";

const invitations = '/admin/invitations'

// Newest first, unpaginated. A non-admin gets a 404 here, never a 403.
export function listInvitations() {
    return apiGet<Invitation[]>(invitations)
}

// 201 = newly invited, 200 = it was already on the list and nothing changed.
export async function inviteEmail(email: string) {
    const { data, status } = await apiPostWithStatus<Invitation>(invitations, { email })

    return { invitation: data, created: status === 201 }
}

export function removeInvitation(id: number) {
    return apiDelete(`${invitations}/${id}`)
}
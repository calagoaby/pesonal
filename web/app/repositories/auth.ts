import type { LoginPayload, RegisterPayload, ResetPasswordPayload, User } from "#shared/types/User";

export async function fetchUser(): Promise<User | null> {
    try {
        return await apiGet<User>('/user')
    } catch (error) {
        if (error instanceof ApiRequestError && error.status === 401) {
            return null
        }

        throw error
    }
}

export function login(payload: LoginPayload) {
    return apiPost<User>('/auth/login', { ...payload, remember: true })
}

export function register(payload: RegisterPayload) {
    return apiPost<User>('/auth/register', payload)
}

export function logout() {
    return apiPostRaw<unknown>('/auth/logout', {}).then(() => undefined)
}

export async function forgotPassword(email: string): Promise<string> {
    const response = await apiPostRaw<{ message: string }>('/auth/forgot-password', { email })

    return response.message
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<string> {
    const response = await apiPostRaw<{ message: string }>('/auth/reset-password', payload)

    return response.message
}

// Answers 202 with an empty or `{ message }` body; either way there is nothing to return.
export async function resendVerification(): Promise<void> {
    await apiPostRaw<unknown>('/auth/email/verification-notification', {})
}

export function deleteAccount() {
    return apiDelete('/user')
}
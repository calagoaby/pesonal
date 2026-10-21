export interface User {
    id: number
    name: string
    email: string
    avatar_url: string | null
    email_verified: boolean
    is_admin: boolean
}

/** Types, not interfaces, so they satisfy the fetch layer's body index signature. */
export type LoginPayload = {
    email: string
    password: string
}

export type RegisterPayload = {
    name: string
    email: string
    password: string
    password_confirmation: string
}

export type ResetPasswordPayload = {
    token: string
    email: string
    password: string
    password_confirmation: string
}
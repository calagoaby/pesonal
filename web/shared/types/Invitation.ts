export interface Invitation {
    id: number
    email: string
    /** ISO 8601 in UTC — a real moment, so format it in the app timezone. */
    invited_at: string
    has_account: boolean
}
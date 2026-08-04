import type { ApiError, ListMeta } from "#shared/types/Api";

/**
 * The one place that knows the base URL, the `{ data }` envelope and the error
 * shape. Swapping Laravel in should touch this file and nothing else.
 */

export class ApiRequestError extends Error {
    status: number
    /** Per-field messages. Only a 422 has these; every other failure still lands here. */
    errors?: Record<string, string[]>

    constructor(message: string, status: number, errors?: Record<string, string[]>) {
        super(message)
        this.name = 'ApiRequestError'
        this.status = status
        this.errors = errors
    }
}

type Query = Record<string, string | number | undefined>

export async function apiGet<T>(path: string, query?: Query): Promise<T> {
    const response = await request<{ data: T }>(path, query)

    return response.data
}

export async function apiGetList<T>(path: string, query?: Query): Promise<{ items: T[], meta: ListMeta }> {
    const response = await request<{ data: T[], meta: ListMeta }>(path, query)

    return { items: response.data, meta: response.meta }
}

async function request<T>(path: string, query?: Query): Promise<T> {
    const { public: config } = useRuntimeConfig()

    try {
        return await $fetch<T>(`${config.apiBase}/api${path}`, { query })
    } catch (error) {
        throw toRequestError(error)
    }
}

function toRequestError(error: unknown): ApiRequestError {
    const failure = error as { status?: number, statusCode?: number, message?: string, data?: ApiError }
    const status = failure.status ?? failure.statusCode ?? 0

    return new ApiRequestError(
        failure.data?.message ?? failure.message ?? 'Could not reach the server.',
        status,
        failure.data?.errors,
    )
}
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

type RequestOptions = {
    method?: 'GET' | 'POST' | 'PATCH' | 'DELETE'
    query?: Query
    /**
     * `Record<string, any>` mirrors what ofetch itself declares. `unknown` is too
     * wide for it, and `Record<string, unknown>` is too narrow to accept a plain
     * payload interface — interfaces have no implicit index signature.
     */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any -- ofetch declares Record<string, any>
    body?: Record<string, any>
}

export async function apiGet<T>(path: string, query?: Query): Promise<T> {
    const response = await request<{ data: T }>(path, { query })

    return response.data
}

export async function apiGetList<T>(path: string, query?: Query): Promise<{ items: T[], meta: ListMeta }> {
    const response = await request<{ data: T[], meta: ListMeta }>(path, { query })

    return { items: response.data, meta: response.meta }
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- ofetch declares Record<string, any>
export async function apiPost<T>(path: string, body: Record<string, any>): Promise<T> {
    const response = await request<{ data: T }>(path, { method: 'POST', body })

    return response.data
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- ofetch declares Record<string, any>
export async function apiPatch<T>(path: string, body: Record<string, any>): Promise<T> {
    const response = await request<{ data: T }>(path, { method: 'PATCH', body })

    return response.data
}

// DELETE answers 204 with no body, so there's nothing to unwrap or return.
export async function apiDelete(path: string): Promise<void> {
    await request<unknown>(path, { method: 'DELETE' })
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
    const { public: config } = useRuntimeConfig()

    try {
        return await $fetch<T>(`${config.apiBase}/api${path}`, options) as T
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
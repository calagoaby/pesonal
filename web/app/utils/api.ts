import type { ApiError, ListMeta } from "#shared/types/Api";
import { proxyHeaders } from "#shared/utils/proxyHeaders";
import { useAuth } from "~/stores/auth";

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
    onResponse?: (context: { response: { status: number } }) => void
}

export async function apiGet<T>(path: string, query?: Query): Promise<T> {
    const response = await request<{ data: T }>(path, { query })

    return response.data
}

export async function apiGetList<T, M extends ListMeta = ListMeta>(path: string, query?: Query): Promise<{ items: T[], meta: M }> {
    const response = await request<{ data: T[], meta: M }>(path, { query })

    return { items: response.data, meta: response.meta }
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- ofetch declares Record<string, any>
export async function apiPost<T>(path: string, body: Record<string, any>): Promise<T> {
    const response = await request<{ data: T }>(path, { method: 'POST', body })

    return response.data
}

// For a POST whose status code carries meaning, e.g. 201 created vs 200 already there.
// eslint-disable-next-line @typescript-eslint/no-explicit-any -- ofetch declares Record<string, any>
export async function apiPostWithStatus<T>(path: string, body: Record<string, any>): Promise<{ data: T, status: number }> {
    let status = 0
    const response = await request<{ data: T }>(path, {
        method: 'POST',
        body,
        onResponse: ({ response }) => {
            status = response.status
        },
    })

    return { data: response.data, status }
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

// For the auth endpoints that answer `{ message }` instead of `{ data }`.
// eslint-disable-next-line @typescript-eslint/no-explicit-any -- ofetch declares Record<string, any>
export async function apiPostRaw<T>(path: string, body: Record<string, any>): Promise<T> {
    return await request<T>(path, { method: 'POST', body })
}

async function request<T>(path: string, options: RequestOptions = {}, isRetry = false): Promise<T> {
    // Captured before any await: on the server the Nuxt context is gone by the time a catch runs.
    const nuxtApp = useNuxtApp()
    const runtimeConfig = useRuntimeConfig()
    const { public: config } = runtimeConfig
    const method = options.method ?? 'GET'
    const headers: Record<string, string> = {}
    let base = config.apiBase

    if (import.meta.server) {
        // A server render has no browser cookie jar: forward the visitor's own cookie, and send
        // an origin so Sanctum treats the call as first-party stateful.
        Object.assign(headers, useRequestHeaders(['cookie']), { origin: useRequestURL().origin })

        // In production apiBase is this frontend's own address. A server render skips that hop and
        // calls the API directly, with the same headers the /api proxy route would add.
        if (runtimeConfig.apiProxyTarget) {
            // Origin only, as in the proxy route: a trailing slash in the env value would otherwise
            // give //api/user, a 404, and every server render would look signed out.
            base = new URL(runtimeConfig.apiProxyTarget).origin
            Object.assign(headers, proxyHeaders({
                headers: useRequestHeaders(),
                publicUrl: config.apiBase,
                socketIp: useRequestEvent()?.node.req.socket.remoteAddress,
                onVercel: Boolean(process.env.VERCEL),
            }, runtimeConfig.apiProxySecret))
        }
    } else if (method !== 'GET') {
        const token = await readXsrfToken(config.apiBase)

        if (token) {
            headers['X-XSRF-TOKEN'] = token
        }
    }

    try {
        return await $fetch<T>(`${base}/api${path}`, { ...options, headers, credentials: 'include' }) as T
    } catch (error) {
        const failure = toRequestError(error)

        // 419 = the CSRF token was stale. Mint a fresh cookie and retry once.
        if (failure.status === 419 && !isRetry && import.meta.client) {
            await refreshXsrfCookie(config.apiBase)

            return await request<T>(path, options, true)
        }

        // A 401 is a transport-level fact: the session is gone, whichever call noticed.
        if (failure.status === 401 && !(method === 'GET' && path === '/user')) {
            await nuxtApp.runWithContext(() => {
                useAuth().clear()
                return navigateTo('/login')
            })
        }

        throw failure
    }
}

async function readXsrfToken(apiBase: string): Promise<string | null | undefined> {
    if (!useCookie('XSRF-TOKEN').value) {
        await refreshXsrfCookie(apiBase)
    }

    return useCookie('XSRF-TOKEN').value
}

async function refreshXsrfCookie(apiBase: string): Promise<void> {
    await $fetch(`${apiBase}/sanctum/csrf-cookie`, { credentials: 'include' })
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
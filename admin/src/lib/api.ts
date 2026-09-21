declare global {
  interface Window {
    shopify: {
      idToken: () => Promise<string>;
    };
  }
}

const API_BASE = import.meta.env.VITE_API_BASE ?? '';

async function authorizedFetch(path: string, init: RequestInit = {}): Promise<Response> {
  const token = await window.shopify.idToken();

  return fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      ...(init.body ? { 'Content-Type': 'application/json' } : {}),
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
      ...init.headers,
    },
  });
}

async function json<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await authorizedFetch(path, init);

  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw Object.assign(new Error(body.message ?? `Request to ${path} failed (${res.status})`), {
      status: res.status,
      body,
    });
  }

  if (res.status === 204) {
    return undefined as T;
  }

  return res.json();
}

export const api = {
  get: <T>(path: string) => json<T>(path),
  post: <T>(path: string, body?: unknown) =>
    json<T>(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined }),
  put: <T>(path: string, body?: unknown) =>
    json<T>(path, { method: 'PUT', body: body ? JSON.stringify(body) : undefined }),
  del: <T>(path: string) => json<T>(path, { method: 'DELETE' }),
};

export type Shop = {
  domain: string;
  email: string | null;
  shopify_plan: string | null;
  is_plus: boolean;
  plan: 'starter' | 'pro';
  on_trial: boolean;
  subscription: { status: string; plan: string; trial_ends_at: string | null } | null;
};

export type AuditIssue = {
  id: number;
  code: string;
  severity: 'high' | 'medium' | 'low';
  area: string;
  title: string;
  explanation: string | null;
  status: string;
  fix_type: string | null;
};

export type AuditScore = { id: number; area: string; score: number };

export type DashboardData = {
  health_score: number | null;
  sub_scores: AuditScore[];
  top_actions: AuditIssue[];
  last_audit_at: string | null;
  monitoring: unknown;
};

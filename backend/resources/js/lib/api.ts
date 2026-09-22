export type PickedProduct = {
  id: string; // gid://shopify/Product/...
  title: string;
  images?: Array<{ originalSrc: string }>;
  variants: Array<{ id: string; title: string; price?: string }>;
};

declare global {
  interface Window {
    shopify: {
      idToken: () => Promise<string>;
      resourcePicker: (options: {
        type: 'product' | 'collection' | 'variant';
        action?: 'add' | 'select';
        multiple?: boolean | number;
        filter?: { variants?: boolean; draft?: boolean; archived?: boolean };
      }) => Promise<PickedProduct[] | undefined>;
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
};

export type BillingStatus = {
  active: boolean;
  plan: 'starter' | 'pro' | null;
  manage_plan_url: string;
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

export type FeatureConfig = {
  id: number;
  type: string;
  name: string | null;
  status: 'draft' | 'active' | 'paused';
  settings: Record<string, unknown>;
  targeting: Record<string, unknown> | null;
  created_at: string;
};

export type Recipe = {
  id: number;
  key: string;
  goal: string;
  items: Array<{ type: string; name: string; settings: Record<string, unknown> }>;
};

export type RecipePreview = {
  recipe: Recipe;
  items: Array<{ type: string; name: string; settings: Record<string, unknown>; allowed: boolean }>;
  blocked_count: number;
};

export type AnalyticsData = {
  range_days: number;
  totals: { impressions: number; clicks: number; orders: number; revenue: number };
  by_feature?: Array<{
    type: string;
    name: string | null;
    impressions: number;
    clicks: number;
    orders: number;
    revenue: number;
  }>;
};

export type ScoreHistoryPoint = { id: number; score_total: number; finished_at: string };

export type AiJob = {
  id: number;
  product_id: string;
  status: 'queued' | 'running' | 'completed' | 'failed' | 'approved' | 'discarded';
  input: { title?: string; description?: string; productType?: string } | null;
  output: { title?: string; description?: string; bullets?: string[]; faq?: Array<{ question: string; answer: string }>; error?: string } | null;
  created_at: string;
};

export type AiUsage = { plan: string; remaining: number; cap: number };

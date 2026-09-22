import { useEffect, useState } from 'react';
import { Frame, Spinner, Box, Navigation } from '@shopify/polaris';
import {
  HomeIcon,
  StoreIcon,
  DiscountIcon,
  ChartFunnelIcon,
  BookOpenIcon,
  ChartHistogramGrowthIcon,
  MagicIcon,
  SettingsIcon,
} from '@shopify/polaris-icons';
import { api, type BillingStatus, type Shop } from './lib/api';
import { FEATURE_TYPES } from './lib/featureTypes';
import Plans from './pages/Plans';
import Dashboard from './pages/Dashboard';
import Storefront from './pages/Storefront';
import Offers from './pages/Offers';
import Funnels from './pages/Funnels';
import Recipes from './pages/Recipes';
import Analytics from './pages/Analytics';
import AiOptimizer from './pages/AiOptimizer';
import Settings from './pages/Settings';

// Shopify Admin's own left sidebar is for switching between apps, not for
// an app's internal sections -- a merchant reported it as clutter when
// Vantora's 8 sections sat there permanently. So navigation lives inside
// the app's own canvas instead, using Polaris's Navigation component --
// the same building block (icon + label, nested sub-items, active-state
// highlighting) Shopify Admin's real sidebar is built from, just scoped to
// this iframe. Storefront/Offers/Funnels each hold several tools, so they
// expand into a sub-item tree rather than being flat links, matching how
// Admin nests e.g. Settings' own categories.
const SECTIONS: Array<{ path: string; label: string; icon: typeof HomeIcon; category?: 'storefront' | 'offers' | 'funnels' }> = [
  { path: '/', label: 'Home', icon: HomeIcon },
  { path: '/storefront', label: 'Storefront', icon: StoreIcon, category: 'storefront' },
  { path: '/offers', label: 'Offers', icon: DiscountIcon, category: 'offers' },
  { path: '/funnels', label: 'Funnels', icon: ChartFunnelIcon, category: 'funnels' },
  { path: '/recipes', label: 'Recipes', icon: BookOpenIcon },
  { path: '/analytics', label: 'Analytics', icon: ChartHistogramGrowthIcon },
  { path: '/ai', label: 'AI', icon: MagicIcon },
  { path: '/settings', label: 'Settings', icon: SettingsIcon },
];

const PAGE_FOR_PATH: Record<string, number> = { '/': 0, '/storefront': 1, '/offers': 2, '/funnels': 3, '/recipes': 4, '/analytics': 5, '/ai': 6, '/settings': 7 };

export default function App() {
  const [shop, setShop] = useState<Shop | null>(null);
  const [billing, setBilling] = useState<BillingStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const pathname = window.location.pathname;
  const tab = PAGE_FOR_PATH[pathname] ?? 0;
  const openType = new URLSearchParams(window.location.search).get('type');

  const load = () => {
    setLoading(true);
    Promise.all([api.get<Shop>('/api/shop'), api.get<BillingStatus>('/api/billing/status')])
      .then(([shopData, billingData]) => {
        setShop(shopData);
        setBilling(billingData);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
  }, []);

  const nav = (
    <Navigation location={pathname}>
      <Navigation.Section
        items={SECTIONS.map((section) => ({
          url: section.path,
          label: section.label,
          icon: section.icon,
          exactMatch: section.path === '/',
          subNavigationItems: section.category
            ? FEATURE_TYPES.filter((f) => f.category === section.category).map((f) => ({
                url: `${section.path}?type=${f.type}`,
                label: f.label,
              }))
            : undefined,
        }))}
      />
    </Navigation>
  );

  if (loading) {
    return (
      <Frame navigation={nav}>
        <Box padding="800">
          <Spinner accessibilityLabel="Loading Vantora" size="large" />
        </Box>
      </Frame>
    );
  }

  if (error || !shop || !billing) {
    return (
      <Frame navigation={nav}>
        <Box padding="800">Failed to load your store: {error ?? 'unknown error'}</Box>
      </Frame>
    );
  }

  if (!billing.active) {
    return (
      <Frame navigation={nav}>
        <Plans managePlanUrl={billing.manage_plan_url} onRecheck={load} />
      </Frame>
    );
  }

  // Each page keeps its own internal navigation (tool list -> create/edit)
  // via local state; moving between top-level sections is a real route
  // (matching routes/web.php) so the sidebar and the page agree on what's
  // current, and a sub-item's ?type= deep-links straight into that tool.
  const page = [
    <Dashboard key="home" shop={shop} />,
    <Storefront key="storefront" shop={shop} initialType={openType} />,
    <Offers key="offers" shop={shop} initialType={openType} />,
    <Funnels key="funnels" shop={shop} initialType={openType} />,
    <Recipes key="recipes" />,
    <Analytics key="analytics" shop={shop} />,
    <AiOptimizer key="ai" />,
    <Settings key="settings" shop={shop} billing={billing} />,
  ][tab];

  return <Frame navigation={nav}>{page}</Frame>;
}

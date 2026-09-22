import { useEffect, useState } from 'react';
import { Frame, Spinner, Box } from '@shopify/polaris';
import { api, type BillingStatus, type Shop } from './lib/api';
import Plans from './pages/Plans';
import Dashboard from './pages/Dashboard';
import Storefront from './pages/Storefront';
import Offers from './pages/Offers';
import Funnels from './pages/Funnels';
import Recipes from './pages/Recipes';
import Analytics from './pages/Analytics';
import AiOptimizer from './pages/AiOptimizer';
import Settings from './pages/Settings';

// Matches routes/web.php: every destination is a real route, not in-memory
// tab state, so <s-app-nav>'s <s-link href> entries -- which Shopify Admin
// renders in its own left sidebar, outside this iframe -- resolve to an
// actual page instead of always reloading back to Home.
const PAGES: Record<string, number> = {
  '/': 0,
  '/storefront': 1,
  '/offers': 2,
  '/funnels': 3,
  '/recipes': 4,
  '/analytics': 5,
  '/ai': 6,
  '/settings': 7,
};

function pageIndexForPath(pathname: string): number {
  return PAGES[pathname] ?? 0;
}

export default function App() {
  const [shop, setShop] = useState<Shop | null>(null);
  const [billing, setBilling] = useState<BillingStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [tab] = useState(() => pageIndexForPath(window.location.pathname));

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

  // Renders in Shopify Admin's own left sidebar, outside this iframe --
  // this is what makes navigation look like a native Admin section instead
  // of an in-page tab strip. rel="home" marks Home as the default page and
  // is hidden from the list itself, matching Admin's own convention.
  const nav = (
    <s-app-nav>
      <s-link href="/" rel="home">Home</s-link>
      <s-link href="/storefront">Storefront</s-link>
      <s-link href="/offers">Offers</s-link>
      <s-link href="/funnels">Funnels</s-link>
      <s-link href="/recipes">Recipes</s-link>
      <s-link href="/analytics">Analytics</s-link>
      <s-link href="/ai">AI</s-link>
      <s-link href="/settings">Settings</s-link>
    </s-app-nav>
  );

  if (loading) {
    return (
      <Frame>
        {nav}
        <Box padding="800">
          <Spinner accessibilityLabel="Loading Vantora" size="large" />
        </Box>
      </Frame>
    );
  }

  if (error || !shop || !billing) {
    return (
      <Frame>
        {nav}
        <Box padding="800">Failed to load your store: {error ?? 'unknown error'}</Box>
      </Frame>
    );
  }

  if (!billing.active) {
    return (
      <Frame>
        {nav}
        <Plans managePlanUrl={billing.manage_plan_url} onRecheck={load} />
      </Frame>
    );
  }

  // Each page keeps its own internal navigation (category -> tool type ->
  // create/edit) rather than a router -- within-page drill-down is handled
  // with local state, while moving between top-level sections is a real
  // route (see PAGES above) so the sidebar link and the page agree on
  // what's current.
  const page = [
    <Dashboard key="home" shop={shop} />,
    <Storefront key="storefront" shop={shop} />,
    <Offers key="offers" shop={shop} />,
    <Funnels key="funnels" shop={shop} />,
    <Recipes key="recipes" />,
    <Analytics key="analytics" shop={shop} />,
    <AiOptimizer key="ai" />,
    <Settings key="settings" shop={shop} billing={billing} />,
  ][tab];

  return (
    <Frame>
      {nav}
      {page}
    </Frame>
  );
}

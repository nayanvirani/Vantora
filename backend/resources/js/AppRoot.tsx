import { useEffect, useState } from 'react';
import { Frame, Spinner, Box, Tabs } from '@shopify/polaris';
import { api, type Shop } from './lib/api';
import Plans from './pages/Plans';
import Dashboard from './pages/Dashboard';
import Tools from './pages/Tools';
import Recipes from './pages/Recipes';
import Analytics from './pages/Analytics';
import AiOptimizer from './pages/AiOptimizer';
import Settings from './pages/Settings';

const TABS = [
  { id: 'home', content: 'Home' },
  { id: 'tools', content: 'Tools' },
  { id: 'recipes', content: 'Recipes' },
  { id: 'analytics', content: 'Analytics' },
  { id: 'ai', content: 'AI' },
  { id: 'settings', content: 'Settings' },
];

export default function App() {
  const [shop, setShop] = useState<Shop | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [tab, setTab] = useState(0);

  const loadShop = () => {
    setLoading(true);
    api
      .get<Shop>('/api/shop')
      .then(setShop)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadShop();
  }, []);

  if (loading) {
    return (
      <Frame>
        <Box padding="800">
          <Spinner accessibilityLabel="Loading Vantora" size="large" />
        </Box>
      </Frame>
    );
  }

  if (error || !shop) {
    return (
      <Frame>
        <Box padding="800">Failed to load your store: {error ?? 'unknown error'}</Box>
      </Frame>
    );
  }

  const isActive = shop.subscription?.status === 'active' || shop.on_trial;

  if (!isActive) {
    return (
      <Frame>
        <Plans onSubscribed={loadShop} />
      </Frame>
    );
  }

  const page = [
    <Dashboard key="home" shop={shop} />,
    <Tools key="tools" shop={shop} />,
    <Recipes key="recipes" />,
    <Analytics key="analytics" shop={shop} />,
    <AiOptimizer key="ai" />,
    <Settings key="settings" shop={shop} />,
  ][tab];

  return (
    <Frame>
      <Box paddingInlineStart="400" paddingInlineEnd="400" paddingBlockStart="400">
        <Tabs tabs={TABS} selected={tab} onSelect={setTab} />
      </Box>
      {page}
    </Frame>
  );
}

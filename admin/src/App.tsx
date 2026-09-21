import { useEffect, useState } from 'react';
import { Frame, Spinner, Box } from '@shopify/polaris';
import { api, type Shop } from './lib/api';
import Plans from './pages/Plans';
import Dashboard from './pages/Dashboard';

export default function App() {
  const [shop, setShop] = useState<Shop | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

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

  return <Frame>{isActive ? <Dashboard shop={shop} /> : <Plans onSubscribed={loadShop} />}</Frame>;
}

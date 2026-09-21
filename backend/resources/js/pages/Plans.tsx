import { useState } from 'react';
import { Page, Layout, Card, Text, Button, BlockStack, Badge, InlineGrid } from '@shopify/polaris';
import { api } from '../lib/api';

const PLANS = [
  {
    key: 'starter' as const,
    name: 'Starter',
    price: '$19.99/mo',
    bullets: [
      'Audit, Health Score, recommendations (30-day history)',
      'One-Click Fix and 3 CRO Recipes',
      '1 configuration per storefront tool',
      'Basic analytics & weekly monitoring',
    ],
  },
  {
    key: 'pro' as const,
    name: 'Pro',
    price: '$49.99/mo',
    highlight: true,
    bullets: [
      'Everything in Starter, unlimited tool configurations',
      'Pre-purchase & post-purchase funnels',
      'Checkout extensions (Plus stores)',
      'Advanced + funnel analytics, priority support',
    ],
  },
];

export default function Plans({ onSubscribed }: { onSubscribed: () => void }) {
  const [loadingPlan, setLoadingPlan] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const subscribe = async (plan: string) => {
    setLoadingPlan(plan);
    setError(null);
    try {
      const { confirmation_url } = await api.post<{ confirmation_url: string | null }>(
        '/api/billing/subscribe',
        { plan },
      );
      if (confirmation_url) {
        window.open(confirmation_url, '_top');
      } else {
        onSubscribed();
      }
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setLoadingPlan(null);
    }
  };

  return (
    <Page title="Choose your plan" subtitle="Find what's hurting your sales. Fix it in one click.">
      <Layout>
        <Layout.Section>
          {error && (
            <Card>
              <Text as="p" tone="critical">
                {error}
              </Text>
            </Card>
          )}
          <InlineGrid columns={2} gap="400">
            {PLANS.map((plan) => (
              <Card key={plan.key}>
                <BlockStack gap="300">
                  <BlockStack gap="100">
                    <Text as="h2" variant="headingLg">
                      {plan.name} {plan.highlight && <Badge tone="success">Recommended</Badge>}
                    </Text>
                    <Text as="p" variant="headingMd">
                      {plan.price}
                    </Text>
                  </BlockStack>
                  <BlockStack gap="150">
                    {plan.bullets.map((b) => (
                      <Text as="p" key={b}>
                        • {b}
                      </Text>
                    ))}
                  </BlockStack>
                  <Button
                    variant={plan.highlight ? 'primary' : 'secondary'}
                    loading={loadingPlan === plan.key}
                    onClick={() => subscribe(plan.key)}
                  >
                    Start free trial
                  </Button>
                </BlockStack>
              </Card>
            ))}
          </InlineGrid>
        </Layout.Section>
      </Layout>
    </Page>
  );
}

import { useState } from 'react';
import { Page, Layout, Card, Text, Button, BlockStack, InlineStack, Badge } from '@shopify/polaris';
import { api, type Shop } from '../lib/api';

export default function Settings({ shop }: { shop: Shop }) {
  const [switching, setSwitching] = useState(false);

  const switchPlan = async (plan: 'starter' | 'pro') => {
    setSwitching(true);
    try {
      const { confirmation_url } = await api.post<{ confirmation_url: string | null }>(
        '/api/billing/subscribe',
        { plan },
      );
      if (confirmation_url) {
        window.open(confirmation_url, '_top');
      }
    } catch (e) {
      alert((e as Error).message);
    } finally {
      setSwitching(false);
    }
  };

  return (
    <Page title="Settings">
      <Layout>
        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Plan and billing
              </Text>
              <InlineStack gap="200" blockAlign="center">
                <Badge tone={shop.plan === 'pro' ? 'success' : undefined}>
                  {shop.plan === 'pro' ? 'Pro' : 'Starter'}
                </Badge>
                {shop.on_trial && <Badge tone="info">Trial</Badge>}
              </InlineStack>
              {shop.subscription?.trial_ends_at && (
                <Text as="p" tone="subdued">
                  Trial ends {new Date(shop.subscription.trial_ends_at).toLocaleDateString()}
                </Text>
              )}
              <InlineStack gap="200">
                <Button
                  variant="primary"
                  loading={switching}
                  disabled={shop.plan === 'pro'}
                  onClick={() => switchPlan('pro')}
                >
                  Upgrade to Pro
                </Button>
                <Button loading={switching} disabled={shop.plan === 'starter'} onClick={() => switchPlan('starter')}>
                  Switch to Starter
                </Button>
              </InlineStack>
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Store
              </Text>
              <Text as="p">{shop.domain}</Text>
              <Text as="p" tone="subdued">
                {shop.shopify_plan}
                {shop.is_plus ? ' · Shopify Plus' : ''}
              </Text>
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="200">
              <Text as="h2" variant="headingMd">
                Support
              </Text>
              <Text as="p" tone="subdued">
                {shop.plan === 'pro' ? 'Priority support' : 'Standard support'} —{' '}
                <a href="mailto:support@vantora.app">support@vantora.app</a>
              </Text>
            </BlockStack>
          </Card>
        </Layout.Section>
      </Layout>
    </Page>
  );
}

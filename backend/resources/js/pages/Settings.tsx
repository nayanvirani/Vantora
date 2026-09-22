import { Page, Layout, Card, Text, Button, BlockStack, InlineStack, Badge } from '@shopify/polaris';
import type { BillingStatus, Shop } from '../lib/api';

export default function Settings({ shop, billing }: { shop: Shop; billing: BillingStatus }) {
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
              </InlineStack>
              <Text as="p" tone="subdued">
                Managed by Shopify — change or cancel your plan on Shopify's own billing page.
              </Text>
              <InlineStack>
                <Button variant="primary" onClick={() => window.open(billing.manage_plan_url, '_top')}>
                  Manage plan
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

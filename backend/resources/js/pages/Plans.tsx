import { Page, Layout, Card, Text, Button, BlockStack, Badge, InlineGrid, InlineStack } from '@shopify/polaris';

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

/**
 * Billing is Shopify Managed Pricing: the actual plan picker lives on
 * Shopify's own hosted page (managePlanUrl) -- these cards are a
 * consistent in-app preview only, every button opens the same hosted page
 * where the merchant picks between them.
 */
export default function Plans({ managePlanUrl, onRecheck }: { managePlanUrl: string; onRecheck: () => void }) {
  const openPricingPage = () => window.open(managePlanUrl, '_top');

  return (
    <Page title="Choose your plan" subtitle="Find what's hurting your sales. Fix it in one click.">
      <Layout>
        <Layout.Section>
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
                  <Button variant={plan.highlight ? 'primary' : 'secondary'} onClick={openPricingPage}>
                    Choose {plan.name}
                  </Button>
                </BlockStack>
              </Card>
            ))}
          </InlineGrid>
          <InlineStack align="center">
            <Button variant="plain" onClick={onRecheck}>
              I've already picked a plan — refresh
            </Button>
          </InlineStack>
        </Layout.Section>
      </Layout>
    </Page>
  );
}

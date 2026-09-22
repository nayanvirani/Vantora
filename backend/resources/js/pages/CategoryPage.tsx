import { useState } from 'react';
import { Page, Layout, Card, Text, BlockStack, InlineStack, Badge, Icon } from '@shopify/polaris';
import { ChevronRightIcon } from '@shopify/polaris-icons';
import type { Shop } from '../lib/api';
import { FEATURE_TYPES } from '../lib/featureTypes';
import ToolTypeScreen from './ToolTypeScreen';

const TITLES: Record<string, { title: string; subtitle: string }> = {
  storefront: { title: 'Storefront', subtitle: 'Sticky Add to Cart, Free Shipping Bar, Trust Badges, Product FAQ' },
  offers: { title: 'Offers', subtitle: 'Quantity discounts, BOGO, free gifts and bundles' },
  funnels: { title: 'Funnels', subtitle: 'Cart Upsell, Frequently Bought Together, Goal Tracker' },
};

export default function CategoryPage({ category, shop }: { category: 'storefront' | 'offers' | 'funnels'; shop: Shop }) {
  const [openType, setOpenType] = useState<string | null>(null);
  const items = FEATURE_TYPES.filter((f) => f.category === category);
  const { title, subtitle } = TITLES[category];

  if (openType) {
    const meta = items.find((i) => i.type === openType)!;
    return <ToolTypeScreen type={openType} locked={!!meta.proOnly && shop.plan !== 'pro'} onBack={() => setOpenType(null)} />;
  }

  return (
    <Page title={title} subtitle={subtitle}>
      <Layout>
        <Layout.Section>
          <BlockStack gap="300">
            {items.map((meta) => {
              const locked = meta.proOnly && shop.plan !== 'pro';

              return (
                <Card key={meta.type} padding="0">
                  <div
                    role="button"
                    onClick={() => setOpenType(meta.type)}
                    style={{ padding: 16, cursor: 'pointer' }}
                  >
                    <InlineStack align="space-between" blockAlign="center">
                      <InlineStack gap="200" blockAlign="center">
                        <Text as="h3" variant="headingSm">
                          {meta.label}
                        </Text>
                        {meta.proOnly && <Badge tone={locked ? 'critical' : 'success'}>Pro</Badge>}
                      </InlineStack>
                      <Icon source={ChevronRightIcon} tone="subdued" />
                    </InlineStack>
                  </div>
                </Card>
              );
            })}
          </BlockStack>
        </Layout.Section>
      </Layout>
    </Page>
  );
}

import { useEffect, useState } from 'react';
import { Page, Layout, Card, Text, BlockStack, Badge, ResourceList, ResourceItem, Spinner, Box } from '@shopify/polaris';
import { api, type FeatureConfig } from '../lib/api';
import type { Shop } from '../lib/api';
import { FEATURE_TYPES } from '../lib/featureTypes';
import IconTile from '../components/IconTile';
import ToolTypeScreen from './ToolTypeScreen';

const TITLES: Record<string, { title: string; subtitle: string }> = {
  storefront: { title: 'Storefront', subtitle: 'Widgets that show directly on your product and cart pages' },
  offers: { title: 'Offers', subtitle: 'Discounts and promotions applied automatically at checkout' },
  funnels: { title: 'Funnels', subtitle: 'Cross-sell, upsell and cart engagement tools' },
};

function statusFor(configs: FeatureConfig[] | undefined): { label: string; tone: 'success' | undefined } {
  if (!configs || configs.length === 0) return { label: 'Not set up', tone: undefined };

  const activeCount = configs.filter((c) => c.status === 'active').length;
  if (activeCount === 0) return { label: 'Off', tone: undefined };
  if (configs.length > 1 && activeCount > 1) return { label: `${activeCount} active`, tone: 'success' };

  return { label: 'Active', tone: 'success' };
}

export default function CategoryPage({
  category,
  shop,
  initialType,
}: {
  category: 'storefront' | 'offers' | 'funnels';
  shop: Shop;
  initialType?: string | null;
}) {
  const items = FEATURE_TYPES.filter((f) => f.category === category);
  // ?type= deep-links straight into a tool from the sidebar's sub-items --
  // only honored when it's actually one of this category's tools.
  const [openType, setOpenType] = useState<string | null>(
    initialType && items.some((i) => i.type === initialType) ? initialType : null
  );
  const [byType, setByType] = useState<Record<string, FeatureConfig[]>>({});
  const [loading, setLoading] = useState(true);
  const { title, subtitle } = TITLES[category];

  useEffect(() => {
    setLoading(true);
    Promise.all(items.map((meta) => api.get<FeatureConfig[]>(`/api/feature-configs?type=${meta.type}`)))
      .then((results) => {
        const grouped: Record<string, FeatureConfig[]> = {};
        items.forEach((meta, i) => {
          grouped[meta.type] = results[i];
        });
        setByType(grouped);
      })
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [category]);

  if (openType) {
    const meta = items.find((i) => i.type === openType)!;
    return <ToolTypeScreen type={openType} locked={!!meta.proOnly && shop.plan !== 'pro'} onBack={() => setOpenType(null)} />;
  }

  return (
    <Page title={title} subtitle={subtitle}>
      <Layout>
        <Layout.Section>
          <Card padding="0">
            {loading ? (
              <Box padding="800">
                <BlockStack inlineAlign="center">
                  <Spinner accessibilityLabel={`Loading ${title.toLowerCase()}`} />
                </BlockStack>
              </Box>
            ) : (
              <ResourceList
                items={items}
                resourceName={{ singular: 'tool', plural: 'tools' }}
                renderItem={(meta) => {
                  const locked = meta.proOnly && shop.plan !== 'pro';
                  const status = statusFor(byType[meta.type]);

                  return (
                    <ResourceItem
                      id={meta.type}
                      accessibilityLabel={`Open ${meta.label}`}
                      onClick={() => setOpenType(meta.type)}
                      media={
                        <IconTile
                          icon={meta.icon}
                          tone={status.tone === 'success' ? 'success' : 'default'}
                        />
                      }
                    >
                      <BlockStack gap="050">
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                          <Text as="h3" variant="bodyMd" fontWeight="semibold">
                            {meta.label}
                          </Text>
                          {meta.proOnly && <Badge tone={locked ? 'critical' : 'info'}>{locked ? 'Pro only' : 'Pro'}</Badge>}
                          <Badge tone={status.tone}>{status.label}</Badge>
                        </div>
                        <Text as="p" variant="bodySm" tone="subdued">
                          {meta.description}
                        </Text>
                      </BlockStack>
                    </ResourceItem>
                  );
                }}
              />
            )}
          </Card>
        </Layout.Section>
      </Layout>
    </Page>
  );
}

import { useEffect, useState } from 'react';
import { Page, Layout, Card, Text, BlockStack, InlineGrid, InlineStack, Banner } from '@shopify/polaris';
import { api, type AnalyticsData, type ScoreHistoryPoint, type Shop, type TrafficData } from '../lib/api';
import ScoreChart from '../components/ScoreChart';
import TrendChart from '../components/TrendChart';
import FunnelChart from '../components/FunnelChart';
import DeviceSplit from '../components/DeviceSplit';

function StatTile({ label, value }: { label: string; value: string | number }) {
  return (
    <Card>
      <BlockStack gap="100">
        <Text as="p" tone="subdued">
          {label}
        </Text>
        <Text as="p" variant="headingLg">
          {value}
        </Text>
      </BlockStack>
    </Card>
  );
}

export default function Analytics({ shop }: { shop: Shop }) {
  const [data, setData] = useState<AnalyticsData | null>(null);
  const [traffic, setTraffic] = useState<TrafficData | null>(null);
  const [scoreHistory, setScoreHistory] = useState<ScoreHistoryPoint[]>([]);

  useEffect(() => {
    api.get<AnalyticsData>('/api/analytics?days=30').then(setData);
    api.get<TrafficData>('/api/analytics/traffic?days=30').then(setTraffic);
    api.get<ScoreHistoryPoint[]>('/api/analytics/score-history').then(setScoreHistory);
  }, []);

  return (
    <Page title="Analytics" subtitle="Traffic, funnel and feature performance, in one view">
      <Layout>
        <Layout.Section>
          <InlineGrid columns={4} gap="300">
            <StatTile label="Sessions (30d)" value={traffic?.sessions ?? '—'} />
            <StatTile label="Page views (30d)" value={traffic?.page_views ?? '—'} />
            <StatTile label="Clicks (30d)" value={data?.totals.clicks ?? '—'} />
            <StatTile label="Revenue (30d)" value={data ? `$${data.totals.revenue.toFixed(2)}` : '—'} />
          </InlineGrid>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Impressions over time
              </Text>
              <TrendChart
                label="impressions"
                points={(data?.daily ?? []).map((d) => ({ date: d.date, value: d.impressions }))}
              />
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section variant="oneHalf">
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Funnel
              </Text>
              <FunnelChart
                stages={[
                  { label: 'Product viewed', value: traffic?.funnel.product_viewed ?? 0 },
                  { label: 'Added to cart', value: traffic?.funnel.product_added_to_cart ?? 0 },
                  { label: 'Checkout started', value: traffic?.funnel.checkout_started ?? 0 },
                  { label: 'Checkout completed', value: traffic?.funnel.checkout_completed ?? 0 },
                ]}
              />
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section variant="oneHalf">
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Devices
              </Text>
              {traffic?.device_split ? (
                <DeviceSplit mobile={traffic.device_split.mobile} desktop={traffic.device_split.desktop} />
              ) : (
                <Text as="p" tone="subdued">
                  Not enough data yet.
                </Text>
              )}
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section variant="oneHalf">
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Top pages
              </Text>
              {traffic?.top_pages.length ? (
                traffic.top_pages.map((p) => (
                  <InlineStack align="space-between" key={p.path}>
                    <Text as="span" truncate>
                      {p.path}
                    </Text>
                    <Text as="span" tone="subdued">
                      {p.views}
                    </Text>
                  </InlineStack>
                ))
              ) : (
                <Text as="p" tone="subdued">
                  No page views yet.
                </Text>
              )}
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section variant="oneHalf">
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Top products viewed
              </Text>
              {traffic?.top_products.length ? (
                traffic.top_products.map((p) => (
                  <InlineStack align="space-between" key={p.product_id}>
                    <Text as="span" truncate>
                      {p.title}
                    </Text>
                    <Text as="span" tone="subdued">
                      {p.views}
                    </Text>
                  </InlineStack>
                ))
              ) : (
                <Text as="p" tone="subdued">
                  No product views yet.
                </Text>
              )}
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Health Score
              </Text>
              <ScoreChart points={scoreHistory} />
            </BlockStack>
          </Card>
        </Layout.Section>

        {shop.plan === 'pro' ? (
          <Layout.Section>
            <Card>
              <BlockStack gap="300">
                <Text as="h2" variant="headingMd">
                  By feature
                </Text>
                {data?.by_feature?.length ? (
                  data.by_feature.map((row) => (
                    <BlockStack gap="050" key={row.type}>
                      <Text as="span" fontWeight="semibold">
                        {row.name || row.type}
                      </Text>
                      <Text as="span" tone="subdued">
                        {row.impressions} impressions · {row.clicks} clicks · {row.orders} orders · $
                        {row.revenue.toFixed(2)} revenue
                      </Text>
                    </BlockStack>
                  ))
                ) : (
                  <Text as="p" tone="subdued">
                    No feature activity in the last 30 days yet.
                  </Text>
                )}
              </BlockStack>
            </Card>
          </Layout.Section>
        ) : (
          <Layout.Section>
            <Banner tone="info">Upgrade to Pro for the per-feature breakdown and full-history charts.</Banner>
          </Layout.Section>
        )}
      </Layout>
    </Page>
  );
}

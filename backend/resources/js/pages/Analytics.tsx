import { useEffect, useState } from 'react';
import { Page, Layout, Card, Text, BlockStack, InlineGrid, Banner } from '@shopify/polaris';
import { api, type AnalyticsData, type ScoreHistoryPoint, type Shop } from '../lib/api';
import ScoreChart from '../components/ScoreChart';

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
  const [scoreHistory, setScoreHistory] = useState<ScoreHistoryPoint[]>([]);

  useEffect(() => {
    api.get<AnalyticsData>('/api/analytics?days=30').then(setData);
    api.get<ScoreHistoryPoint[]>('/api/analytics/score-history').then(setScoreHistory);
  }, []);

  return (
    <Page title="Analytics" subtitle="Feature performance and Health Score trend">
      <Layout>
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

        <Layout.Section>
          <InlineGrid columns={4} gap="300">
            <StatTile label="Impressions (30d)" value={data?.totals.impressions ?? '—'} />
            <StatTile label="Clicks (30d)" value={data?.totals.clicks ?? '—'} />
            <StatTile label="Orders (30d)" value={data?.totals.orders ?? '—'} />
            <StatTile label="Revenue (30d)" value={data ? `$${data.totals.revenue.toFixed(2)}` : '—'} />
          </InlineGrid>
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

import { useEffect, useState, useCallback } from 'react';
import {
  Page,
  Layout,
  Card,
  Text,
  Button,
  BlockStack,
  InlineStack,
  Badge,
  Banner,
  SkeletonBodyText,
  ProgressBar,
} from '@shopify/polaris';
import { api, type DashboardData, type Shop } from '../lib/api';

const SEVERITY_TONE: Record<string, 'critical' | 'warning' | 'info'> = {
  high: 'critical',
  medium: 'warning',
  low: 'info',
};

function scoreTone(score: number): 'success' | 'caution' | 'critical' {
  if (score >= 80) return 'success';
  if (score >= 50) return 'caution';
  return 'critical';
}

function scoreBadgeTone(score: number): 'success' | 'warning' | 'critical' {
  if (score >= 80) return 'success';
  if (score >= 50) return 'warning';
  return 'critical';
}

function scoreLabel(score: number): string {
  if (score >= 80) return 'Healthy';
  if (score >= 50) return 'Needs attention';
  return 'At risk';
}

export default function Dashboard({ shop }: { shop: Shop }) {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [runningAudit, setRunningAudit] = useState(false);
  const [fixingCode, setFixingCode] = useState<string | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    api
      .get<DashboardData>('/api/dashboard')
      .then(setData)
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const runAudit = async () => {
    setRunningAudit(true);
    await api.post('/api/audits');
    // The audit runs as a queued job; poll briefly for a result.
    setTimeout(() => {
      load();
      setRunningAudit(false);
    }, 4000);
  };

  const fixIssue = async (fixType: string, code: string) => {
    setFixingCode(code);
    try {
      const config = await api.post<{ id: number }>('/api/feature-configs', { type: fixType });
      await api.post(`/api/feature-configs/${config.id}/activate`);
      load();
    } catch (e) {
      alert((e as Error).message);
    } finally {
      setFixingCode(null);
    }
  };

  return (
    <Page
      title={`Welcome back, ${shop.domain}`}
      subtitle={`${shop.plan === 'pro' ? 'Pro' : 'Starter'} plan`}
      primaryAction={{ content: 'Run new audit', loading: runningAudit, onAction: runAudit }}
    >
      <Layout>
        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Store Health Score
              </Text>
              {loading ? (
                <SkeletonBodyText lines={2} />
              ) : data?.health_score != null ? (
                <>
                  <InlineStack gap="300" blockAlign="center">
                    <Text as="p" variant="heading2xl" tone={scoreTone(data.health_score)}>
                      {data.health_score}/100
                    </Text>
                    <Badge tone={scoreBadgeTone(data.health_score)}>{scoreLabel(data.health_score)}</Badge>
                  </InlineStack>
                  <BlockStack gap="200">
                    {data.sub_scores.map((s) => (
                      <BlockStack gap="100" key={s.id}>
                        <InlineStack align="space-between">
                          <Text as="span" tone="subdued">
                            {s.area
                              .replace(/_/g, ' ')
                              .replace(/\b\w/g, (c) => c.toUpperCase())}
                          </Text>
                          <Text as="span">{s.score}</Text>
                        </InlineStack>
                        <ProgressBar progress={s.score} size="small" />
                      </BlockStack>
                    ))}
                  </BlockStack>
                </>
              ) : (
                <Banner tone="info">Run your first audit to see your Health Score.</Banner>
              )}
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                Today's actions
              </Text>
              {loading ? (
                <SkeletonBodyText lines={4} />
              ) : data?.top_actions.length ? (
                data.top_actions.map((issue) => (
                  <Card key={issue.id} background="bg-surface-secondary">
                    <BlockStack gap="200">
                      <InlineStack align="space-between" blockAlign="center">
                        <InlineStack gap="200" blockAlign="center">
                          <Badge tone={SEVERITY_TONE[issue.severity]}>{issue.severity}</Badge>
                          <Text as="h3" variant="headingSm">
                            {issue.title}
                          </Text>
                        </InlineStack>
                        {issue.fix_type && (
                          <Button
                            variant="primary"
                            loading={fixingCode === issue.code}
                            onClick={() => fixIssue(issue.fix_type!, issue.code)}
                          >
                            Fix this
                          </Button>
                        )}
                      </InlineStack>
                      {issue.explanation && (
                        <Text as="p" tone="subdued">
                          {issue.explanation}
                        </Text>
                      )}
                    </BlockStack>
                  </Card>
                ))
              ) : (
                <Text as="p" tone="subdued">
                  No open issues — nice work.
                </Text>
              )}
            </BlockStack>
          </Card>
        </Layout.Section>
      </Layout>
    </Page>
  );
}

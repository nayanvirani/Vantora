import { useEffect, useState } from 'react';
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
  ProgressBar,
  Thumbnail,
} from '@shopify/polaris';
import { api, type AiJob, type AiUsage, type PickedProduct } from '../lib/api';
import { pickProduct } from '../lib/resourcePicker';

const STATUS_TONE: Record<string, 'success' | 'critical' | 'info' | undefined> = {
  completed: 'info',
  approved: 'success',
  failed: 'critical',
  discarded: undefined,
};

export default function AiOptimizer() {
  const [usage, setUsage] = useState<AiUsage | null>(null);
  const [jobs, setJobs] = useState<AiJob[]>([]);
  const [selected, setSelected] = useState<PickedProduct | null>(null);
  const [picking, setPicking] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = () => {
    api.get<AiUsage>('/api/ai/usage').then(setUsage);
    api.get<{ data: AiJob[] }>('/api/ai/jobs').then((res) => setJobs(res.data));
  };

  useEffect(load, []);

  const choose = async () => {
    setPicking(true);
    try {
      const result = await pickProduct();
      if (result) setSelected(result[0]);
    } finally {
      setPicking(false);
    }
  };

  const submit = async () => {
    if (!selected) return;
    setSubmitting(true);
    setError(null);
    try {
      await api.post('/api/ai/jobs', { product_id: selected.id });
      setSelected(null);
      setTimeout(load, 3000);
      load();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setSubmitting(false);
    }
  };

  const approve = async (job: AiJob) => {
    setBusyId(job.id);
    try {
      await api.post(`/api/ai/jobs/${job.id}/approve`);
      load();
    } catch (e) {
      alert((e as Error).message);
    } finally {
      setBusyId(null);
    }
  };

  const discard = async (job: AiJob) => {
    setBusyId(job.id);
    try {
      await api.post(`/api/ai/jobs/${job.id}/discard`);
      load();
    } finally {
      setBusyId(null);
    }
  };

  return (
    <Page title="AI Product Optimizer" subtitle="Suggests title, description, bullets and FAQ — nothing publishes without your approval">
      <Layout>
        <Layout.Section>
          <Card>
            <BlockStack gap="300">
              {usage && (
                <BlockStack gap="150">
                  <Text as="span">
                    {usage.remaining} of {usage.cap} remaining
                    {usage.plan === 'starter' ? ' (lifetime)' : ' this month'}
                  </Text>
                  <ProgressBar progress={usage.cap ? (usage.remaining / usage.cap) * 100 : 0} size="small" />
                </BlockStack>
              )}

              {error && (
                <Banner tone="critical" onDismiss={() => setError(null)}>
                  {error}
                </Banner>
              )}

              {selected ? (
                <InlineStack gap="200" blockAlign="center">
                  {selected.images?.[0]?.originalSrc && (
                    <Thumbnail source={selected.images[0].originalSrc} alt={selected.title} size="small" />
                  )}
                  <Text as="span" variant="bodyMd" fontWeight="semibold">
                    {selected.title}
                  </Text>
                  <Button variant="plain" onClick={choose}>
                    Change
                  </Button>
                </InlineStack>
              ) : (
                <InlineStack>
                  <Button loading={picking} onClick={choose}>
                    Choose a product
                  </Button>
                </InlineStack>
              )}

              <InlineStack>
                <Button variant="primary" disabled={!selected} loading={submitting} onClick={submit}>
                  Generate
                </Button>
              </InlineStack>
            </BlockStack>
          </Card>
        </Layout.Section>

        <Layout.Section>
          <BlockStack gap="300">
            {jobs.map((job) => (
              <Card key={job.id}>
                <BlockStack gap="200">
                  <InlineStack align="space-between" blockAlign="center">
                    <Text as="h3" variant="headingSm">
                      {job.input?.title || job.product_id}
                    </Text>
                    <Badge tone={STATUS_TONE[job.status]}>{job.status}</Badge>
                  </InlineStack>

                  {job.status === 'failed' && (
                    <Banner tone="critical">{job.output?.error ?? 'Generation failed.'}</Banner>
                  )}

                  {job.output?.title && job.status === 'completed' && (
                    <BlockStack gap="150">
                      <Text as="p">
                        <strong>Suggested title:</strong> {job.output.title}
                      </Text>
                      {job.output.description && (
                        <Text as="p">
                          <strong>Description:</strong> {job.output.description}
                        </Text>
                      )}
                      {job.output.bullets && (
                        <ul>
                          {job.output.bullets.map((b, i) => (
                            <li key={i}>
                              <Text as="span">{b}</Text>
                            </li>
                          ))}
                        </ul>
                      )}
                      <InlineStack gap="200">
                        <Button variant="primary" loading={busyId === job.id} onClick={() => approve(job)}>
                          Approve and publish
                        </Button>
                        <Button loading={busyId === job.id} onClick={() => discard(job)}>
                          Discard
                        </Button>
                      </InlineStack>
                    </BlockStack>
                  )}
                </BlockStack>
              </Card>
            ))}
          </BlockStack>
        </Layout.Section>
      </Layout>
    </Page>
  );
}

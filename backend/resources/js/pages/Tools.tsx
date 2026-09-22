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
  Modal,
  TextField,
  Banner,
  EmptyState,
} from '@shopify/polaris';
import { api, type FeatureConfig, type Shop } from '../lib/api';
import { FEATURE_TYPES, featureLabel } from '../lib/featureTypes';

const CATEGORY_LABELS: Record<string, string> = {
  storefront: 'Storefront tools',
  offers: 'Offers and bundles',
  funnels: 'Funnels',
};

const STATUS_TONE: Record<string, 'success' | 'info' | undefined> = {
  active: 'success',
  draft: undefined,
  paused: 'info',
};

export default function Tools({ shop }: { shop: Shop }) {
  const [configs, setConfigs] = useState<FeatureConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [creatingType, setCreatingType] = useState<string | null>(null);
  const [settingsDraft, setSettingsDraft] = useState('{}');
  const [nameDraft, setNameDraft] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    api
      .get<FeatureConfig[]>('/api/feature-configs')
      .then(setConfigs)
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const openCreate = (type: string) => {
    setCreatingType(type);
    setNameDraft(featureLabel(type));
    setSettingsDraft('{}');
    setError(null);
  };

  const submitCreate = async () => {
    if (!creatingType) return;
    let settings: unknown;
    try {
      settings = JSON.parse(settingsDraft || '{}');
    } catch {
      setError('Settings must be valid JSON.');
      return;
    }

    try {
      const config = await api.post<FeatureConfig>('/api/feature-configs', {
        type: creatingType,
        name: nameDraft,
        settings,
      });
      await api.post(`/api/feature-configs/${config.id}/activate`);
      setCreatingType(null);
      load();
    } catch (e) {
      setError((e as Error).message);
    }
  };

  const activate = async (config: FeatureConfig) => {
    setBusyId(config.id);
    try {
      await api.post(`/api/feature-configs/${config.id}/activate`);
      load();
    } catch (e) {
      alert((e as Error).message);
    } finally {
      setBusyId(null);
    }
  };

  const deactivate = async (config: FeatureConfig) => {
    setBusyId(config.id);
    try {
      await api.post(`/api/feature-configs/${config.id}/deactivate`);
      load();
    } catch (e) {
      alert((e as Error).message);
    } finally {
      setBusyId(null);
    }
  };

  const remove = async (config: FeatureConfig) => {
    if (!confirm(`Delete "${config.name ?? config.type}"? This can't be undone.`)) return;
    setBusyId(config.id);
    try {
      await api.del(`/api/feature-configs/${config.id}`);
      load();
    } finally {
      setBusyId(null);
    }
  };

  const configsByType = (type: string) => configs.filter((c) => c.type === type);
  const creatingMeta = FEATURE_TYPES.find((f) => f.type === creatingType);

  return (
    <Page title="Tools" subtitle="Sticky ATC, Shipping Bar, Trust Badges, FAQ, offers, bundles and funnels">
      <Layout>
        {(['storefront', 'offers', 'funnels'] as const).map((category) => (
          <Layout.Section key={category}>
            <BlockStack gap="300">
              <Text as="h2" variant="headingMd">
                {CATEGORY_LABELS[category]}
              </Text>
              {FEATURE_TYPES.filter((f) => f.category === category).map((meta) => {
                const items = configsByType(meta.type);
                const locked = meta.proOnly && shop.plan !== 'pro';

                return (
                  <Card key={meta.type}>
                    <BlockStack gap="300">
                      <InlineStack align="space-between" blockAlign="center">
                        <InlineStack gap="200" blockAlign="center">
                          <Text as="h3" variant="headingSm">
                            {meta.label}
                          </Text>
                          {meta.proOnly && <Badge tone={locked ? 'critical' : 'success'}>Pro</Badge>}
                        </InlineStack>
                        <Button disabled={locked} onClick={() => openCreate(meta.type)}>
                          New
                        </Button>
                      </InlineStack>

                      {locked && (
                        <Banner tone="warning">
                          Upgrade to Pro to unlock {meta.label.toLowerCase()}.
                        </Banner>
                      )}

                      {!loading && items.length === 0 && !locked && (
                        <Text as="p" tone="subdued">
                          No configurations yet.
                        </Text>
                      )}

                      {items.map((config) => (
                        <InlineStack key={config.id} align="space-between" blockAlign="center">
                          <InlineStack gap="200" blockAlign="center">
                            <Badge tone={STATUS_TONE[config.status]}>{config.status}</Badge>
                            <Text as="span">{config.name || meta.label}</Text>
                          </InlineStack>
                          <InlineStack gap="150">
                            {config.status === 'active' ? (
                              <Button
                                size="slim"
                                loading={busyId === config.id}
                                onClick={() => deactivate(config)}
                              >
                                Pause
                              </Button>
                            ) : (
                              <Button
                                size="slim"
                                variant="primary"
                                loading={busyId === config.id}
                                onClick={() => activate(config)}
                              >
                                Activate
                              </Button>
                            )}
                            <Button
                              size="slim"
                              tone="critical"
                              variant="plain"
                              loading={busyId === config.id}
                              onClick={() => remove(config)}
                            >
                              Delete
                            </Button>
                          </InlineStack>
                        </InlineStack>
                      ))}
                    </BlockStack>
                  </Card>
                );
              })}
            </BlockStack>
          </Layout.Section>
        ))}

        {!loading && configs.length === 0 && (
          <Layout.Section>
            <Card>
              <EmptyState
                heading="Nothing configured yet"
                image="https://cdn.shopify.com/s/files/1/0757/9955/files/empty-state.svg"
              >
                <p>Pick a tool above to create your first configuration, or run a recipe from the Recipes tab.</p>
              </EmptyState>
            </Card>
          </Layout.Section>
        )}
      </Layout>

      <Modal
        open={creatingType !== null}
        onClose={() => setCreatingType(null)}
        title={`New ${creatingMeta?.label ?? ''}`}
        primaryAction={{ content: 'Create and activate', onAction: submitCreate }}
        secondaryActions={[{ content: 'Cancel', onAction: () => setCreatingType(null) }]}
      >
        <Modal.Section>
          <BlockStack gap="300">
            {error && (
              <Banner tone="critical" onDismiss={() => setError(null)}>
                {error}
              </Banner>
            )}
            <TextField label="Name" autoComplete="off" value={nameDraft} onChange={setNameDraft} />
            <TextField
              label="Settings (JSON)"
              autoComplete="off"
              multiline={4}
              value={settingsDraft}
              onChange={setSettingsDraft}
              helpText={creatingMeta?.settingsHint ?? 'Leave as {} to use defaults.'}
            />
          </BlockStack>
        </Modal.Section>
      </Modal>
    </Page>
  );
}

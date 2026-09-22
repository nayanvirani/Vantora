import { useEffect, useState, useCallback } from 'react';
import { Page, Layout, Card, Text, Button, BlockStack, InlineStack, Badge, Banner, TextField, ResourceList, ResourceItem, Box } from '@shopify/polaris';
import { api, type FeatureConfig, type ProductLookupEntry } from '../lib/api';
import { TYPE_SCHEMAS, collectProductIds } from '../lib/settingsSchema';
import { featureLabel, featureIcon } from '../lib/featureTypes';
import SettingsForm from '../components/SettingsForm';
import ToolPreview from '../components/ToolPreview';
import IconTile from '../components/IconTile';

const STATUS_TONE: Record<string, 'success' | 'info' | undefined> = {
  active: 'success',
  draft: undefined,
  paused: 'info',
};

// Kept in sync with FeatureConfigController::SINGLETON_TYPES: these render
// as one shop-wide widget (one shop metafield slot), so a second config has
// nowhere to apply -- skip the list screen and edit the one config directly.
// trust_badges/faq support per-product targeting instead, so they keep the
// list screen (multiple configs: one sitewide default plus targeted ones).
const SINGLETON_TYPES = ['sticky_atc', 'shipping_bar', 'goal_tracker', 'cart_upsell'];

function defaultsFor(type: string): Record<string, unknown> {
  const values: Record<string, unknown> = {};
  TYPE_SCHEMAS[type]?.sections.forEach((section) =>
    section.fields.forEach((field) => {
      if ('default' in field && field.default !== undefined) values[field.key] = field.default;
    })
  );
  return values;
}

export default function ToolTypeScreen({ type, locked, onBack }: { type: string; locked: boolean; onBack: () => void }) {
  const [configs, setConfigs] = useState<FeatureConfig[]>([]);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [editing, setEditing] = useState<FeatureConfig | 'new' | null>(null);
  const [name, setName] = useState('');
  const [values, setValues] = useState<Record<string, unknown>>({});
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [lookup, setLookup] = useState<Record<string, ProductLookupEntry>>({});

  const schema = TYPE_SCHEMAS[type];
  const label = featureLabel(type);
  const isSingleton = SINGLETON_TYPES.includes(type);

  const load = useCallback(() => {
    setLoading(true);
    api
      .get<FeatureConfig[]>(`/api/feature-configs?type=${type}`)
      .then((result) => {
        setConfigs(result);
        return result;
      })
      .finally(() => setLoading(false));
  }, [type]);

  useEffect(() => {
    load();
  }, [load]);

  // Singleton tools (one shop-wide widget) skip the list screen entirely --
  // jump straight into editing the one config, creating its draft on the fly.
  useEffect(() => {
    if (!isSingleton || loading || editing) return;
    if (configs.length > 0) {
      openEdit(configs[0]);
    } else {
      openNew();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isSingleton, loading, configs]);

  const openNew = () => {
    setEditing('new');
    setName(label);
    setValues(defaultsFor(type));
    setError(null);
  };

  const openEdit = (config: FeatureConfig) => {
    setEditing(config);
    setName(config.name ?? label);
    const raw = { ...defaultsFor(type), ...config.settings };
    setValues(schema?.fromSettings ? schema.fromSettings(raw) : raw);
    setError(null);
    setLookup({});

    const ids = collectProductIds(type, config.settings);
    if (ids.length) {
      api
        .get<Record<string, ProductLookupEntry>>(`/api/products/lookup?ids=${ids.join(',')}`)
        .then(setLookup)
        .catch(() => {});
    }
  };

  const save = async () => {
    setSaving(true);
    setError(null);
    const settings = schema?.toSettings ? schema.toSettings(values) : values;

    try {
      let config: FeatureConfig;
      if (editing === 'new') {
        config = await api.post<FeatureConfig>('/api/feature-configs', { type, name, settings });
      } else {
        config = await api.put<FeatureConfig>(`/api/feature-configs/${editing!.id}`, { name, settings });
      }
      await api.post(`/api/feature-configs/${config.id}/activate`);
      setEditing(null);
      load();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setSaving(false);
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
    if (!confirm(`Delete "${config.name ?? label}"? This can't be undone.`)) return;
    setBusyId(config.id);
    try {
      await api.del(`/api/feature-configs/${config.id}`);
      load();
    } finally {
      setBusyId(null);
    }
  };

  if (editing) {
    const editingConfig = editing === 'new' ? null : editing;
    return (
      <Page
        title={isSingleton ? label : editing === 'new' ? `New ${label}` : `Edit ${label}`}
        backAction={{ content: isSingleton ? 'Back' : label, onAction: () => (isSingleton ? onBack() : setEditing(null)) }}
        primaryAction={{ content: 'Save and activate', loading: saving, onAction: save }}
        secondaryActions={
          isSingleton && editingConfig?.status === 'active'
            ? [{ content: 'Turn off', loading: busyId === editingConfig.id, onAction: () => deactivate(editingConfig) }]
            : undefined
        }
      >
        <Layout>
          <Layout.Section>
            <BlockStack gap="400">
              {error && (
                <Banner tone="critical" onDismiss={() => setError(null)}>
                  {error}
                </Banner>
              )}
              {isSingleton && editingConfig && (
                <Banner tone={editingConfig.status === 'active' ? 'success' : 'info'}>
                  {editingConfig.status === 'active' ? 'Live on your storefront.' : 'Not live yet -- save to turn it on.'}
                </Banner>
              )}
              <Card>
                <TextField label="Name" autoComplete="off" value={name} onChange={setName} />
              </Card>
              <Card>
                <SettingsForm
                  sections={schema?.sections ?? []}
                  values={values}
                  onChange={(key, v) => setValues((prev) => ({ ...prev, [key]: v }))}
                  lookup={lookup}
                />
              </Card>
            </BlockStack>
          </Layout.Section>
          <Layout.Section variant="oneThird">
            <div style={{ position: 'sticky', top: 16 }}>
              <Card>
                <ToolPreview type={type} values={values} />
              </Card>
            </div>
          </Layout.Section>
        </Layout>
      </Page>
    );
  }

  if (isSingleton) {
    return <Page title={label} backAction={{ content: 'Back', onAction: onBack }} />;
  }

  const icon = featureIcon(type);

  return (
    <Page title={label} backAction={{ content: 'Back', onAction: onBack }} primaryAction={{ content: 'New', disabled: locked, onAction: openNew }}>
      <Layout>
        <Layout.Section>
          {locked && <Banner tone="warning">Upgrade to Pro to unlock {label.toLowerCase()}.</Banner>}

          {!loading && configs.length === 0 && !locked && (
            <Card>
              <Box padding="800">
                <BlockStack gap="300" inlineAlign="center">
                  {icon && <IconTile icon={icon} />}
                  <Text as="p" alignment="center" tone="subdued">
                    No {label.toLowerCase()} set up yet.
                  </Text>
                  <Button variant="primary" onClick={openNew}>
                    Create your first one
                  </Button>
                </BlockStack>
              </Box>
            </Card>
          )}

          {configs.length > 0 && (
            <Card padding="0">
              <ResourceList
                items={configs}
                resourceName={{ singular: label.toLowerCase(), plural: label.toLowerCase() }}
                renderItem={(config) => (
                  <ResourceItem
                    id={String(config.id)}
                    accessibilityLabel={`Edit ${config.name || label}`}
                    onClick={() => openEdit(config)}
                    media={icon ? <IconTile icon={icon} tone={config.status === 'active' ? 'success' : 'default'} /> : undefined}
                    shortcutActions={[
                      config.status === 'active'
                        ? { content: 'Pause', onAction: () => deactivate(config), disabled: busyId === config.id }
                        : { content: 'Activate', onAction: () => activate(config), disabled: busyId === config.id },
                      { content: 'Delete', onAction: () => remove(config), disabled: busyId === config.id },
                    ]}
                    persistActions
                  >
                    <InlineStack gap="200" blockAlign="center">
                      <Text as="span" fontWeight="semibold">
                        {config.name || label}
                      </Text>
                      <Badge tone={STATUS_TONE[config.status]}>{config.status}</Badge>
                    </InlineStack>
                  </ResourceItem>
                )}
              />
            </Card>
          )}
        </Layout.Section>
      </Layout>
    </Page>
  );
}

import { useEffect, useState, useCallback } from 'react';
import { Page, Layout, Card, Text, Button, BlockStack, InlineStack, Badge, Banner, TextField } from '@shopify/polaris';
import { api, type FeatureConfig } from '../lib/api';
import { TYPE_SCHEMAS } from '../lib/settingsSchema';
import { featureLabel } from '../lib/featureTypes';
import SettingsForm from '../components/SettingsForm';
import ToolPreview from '../components/ToolPreview';

const STATUS_TONE: Record<string, 'success' | 'info' | undefined> = {
  active: 'success',
  draft: undefined,
  paused: 'info',
};

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

  const schema = TYPE_SCHEMAS[type];
  const label = featureLabel(type);

  const load = useCallback(() => {
    setLoading(true);
    api
      .get<FeatureConfig[]>(`/api/feature-configs?type=${type}`)
      .then(setConfigs)
      .finally(() => setLoading(false));
  }, [type]);

  useEffect(() => {
    load();
  }, [load]);

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
    return (
      <Page
        title={editing === 'new' ? `New ${label}` : `Edit ${label}`}
        backAction={{ content: label, onAction: () => setEditing(null) }}
        primaryAction={{ content: 'Save and activate', loading: saving, onAction: save }}
      >
        <Layout>
          <Layout.Section>
            <BlockStack gap="400">
              {error && (
                <Banner tone="critical" onDismiss={() => setError(null)}>
                  {error}
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

  return (
    <Page title={label} backAction={{ content: 'Back', onAction: onBack }} primaryAction={{ content: 'New', disabled: locked, onAction: openNew }}>
      <Layout>
        <Layout.Section>
          {locked && <Banner tone="warning">Upgrade to Pro to unlock {label.toLowerCase()}.</Banner>}

          {!loading && configs.length === 0 && !locked && (
            <Card>
              <Text as="p" tone="subdued">
                No configurations yet. Click "New" to create one.
              </Text>
            </Card>
          )}

          <BlockStack gap="300">
            {configs.map((config) => (
              <Card key={config.id}>
                <InlineStack align="space-between" blockAlign="center">
                  <InlineStack gap="200" blockAlign="center">
                    <Badge tone={STATUS_TONE[config.status]}>{config.status}</Badge>
                    <Text as="span">{config.name || label}</Text>
                  </InlineStack>
                  <InlineStack gap="150">
                    <Button size="slim" onClick={() => openEdit(config)}>
                      Edit
                    </Button>
                    {config.status === 'active' ? (
                      <Button size="slim" loading={busyId === config.id} onClick={() => deactivate(config)}>
                        Pause
                      </Button>
                    ) : (
                      <Button size="slim" variant="primary" loading={busyId === config.id} onClick={() => activate(config)}>
                        Activate
                      </Button>
                    )}
                    <Button size="slim" tone="critical" variant="plain" loading={busyId === config.id} onClick={() => remove(config)}>
                      Delete
                    </Button>
                  </InlineStack>
                </InlineStack>
              </Card>
            ))}
          </BlockStack>
        </Layout.Section>
      </Layout>
    </Page>
  );
}

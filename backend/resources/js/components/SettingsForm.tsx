import { useState } from 'react';
import { BlockStack, Text, TextField, Select, Checkbox, RangeSlider, InlineStack, Button, Tag, Thumbnail } from '@shopify/polaris';
import type { FieldDef, FieldSection } from '../lib/settingsSchema';
import { pickProduct, pickProducts, pickVariant, numericId } from '../lib/resourcePicker';
import type { PickedProduct, PickedVariant, ProductLookupEntry } from '../lib/api';

type Values = Record<string, unknown>;
type Lookup = Record<string, ProductLookupEntry>;

function ColorField({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  return (
    <BlockStack gap="100">
      <Text as="span" variant="bodyMd">
        {label}
      </Text>
      <InlineStack gap="200" blockAlign="center">
        <input
          type="color"
          value={/^#[0-9a-fA-F]{6}$/.test(value) ? value : '#000000'}
          onChange={(e) => onChange(e.target.value)}
          style={{ width: 36, height: 36, padding: 0, border: '1px solid var(--p-color-border)', borderRadius: 6, cursor: 'pointer' }}
        />
        <div style={{ width: 120 }}>
          <TextField label="" labelHidden value={value ?? ''} onChange={onChange} autoComplete="off" placeholder="#111111" />
        </div>
      </InlineStack>
    </BlockStack>
  );
}

function ProductField({
  label,
  helpText,
  value,
  lookup,
  onChange,
}: {
  label: string;
  helpText?: string;
  value: string | undefined;
  lookup?: Lookup;
  onChange: (id: string, product: PickedProduct | null) => void;
}) {
  const [picked, setPicked] = useState<PickedProduct | null>(null);
  const resolved = value ? lookup?.[value] : undefined;

  return (
    <BlockStack gap="150">
      <Text as="span" variant="bodyMd">
        {label}
      </Text>
      {value ? (
        <InlineStack gap="200" blockAlign="center">
          {(picked?.images?.[0]?.originalSrc || resolved?.image) && (
            <Thumbnail source={picked?.images?.[0]?.originalSrc ?? resolved!.image!} alt={picked?.title ?? resolved?.title ?? ''} size="small" />
          )}
          <Text as="span">{picked?.title ?? resolved?.title ?? numericId(value)}</Text>
          <Button
            variant="plain"
            onClick={async () => {
              const result = await pickProduct();
              if (result) {
                setPicked(result);
                onChange(result.id, result);
              }
            }}
          >
            Change
          </Button>
        </InlineStack>
      ) : (
        <Button
          onClick={async () => {
            const result = await pickProduct();
            if (result) {
              setPicked(result);
              onChange(result.id, result);
            }
          }}
        >
          Choose a product
        </Button>
      )}
      {helpText && (
        <Text as="span" tone="subdued">
          {helpText}
        </Text>
      )}
    </BlockStack>
  );
}

function VariantField({
  label,
  helpText,
  value,
  lookup,
  onChange,
}: {
  label: string;
  helpText?: string;
  value: string | undefined;
  lookup?: Lookup;
  onChange: (id: string, variant: PickedVariant | null) => void;
}) {
  const [picked, setPicked] = useState<PickedVariant | null>(null);
  const resolved = value ? lookup?.[value] : undefined;

  const choose = async () => {
    const result = await pickVariant();
    if (result) {
      setPicked(result);
      onChange(result.id, result);
    }
  };

  return (
    <BlockStack gap="150">
      <Text as="span" variant="bodyMd">
        {label}
      </Text>
      {value ? (
        <InlineStack gap="200" blockAlign="center">
          {(picked?.image?.originalSrc || resolved?.image) && (
            <Thumbnail source={picked?.image?.originalSrc ?? resolved!.image!} alt={picked?.displayName ?? resolved?.title ?? ''} size="small" />
          )}
          <Text as="span">{picked?.displayName ?? resolved?.title ?? numericId(value)}</Text>
          <Button variant="plain" onClick={choose}>
            Change
          </Button>
        </InlineStack>
      ) : (
        <Button onClick={choose}>Choose a product variant</Button>
      )}
      {helpText && (
        <Text as="span" tone="subdued">
          {helpText}
        </Text>
      )}
    </BlockStack>
  );
}

function ProductsField({
  label,
  helpText,
  value,
  lookup,
  onChange,
}: {
  label: string;
  helpText?: string;
  value: string[] | undefined;
  lookup?: Lookup;
  onChange: (ids: string[], titles: Record<string, string>) => void;
}) {
  const [titles, setTitles] = useState<Record<string, string>>({});
  const ids = value ?? [];

  const add = async () => {
    const results = await pickProducts({ multiple: true });
    if (!results) return;
    const nextTitles = { ...titles };
    const nextIds = [...ids];
    results.forEach((p) => {
      if (!nextIds.includes(p.id)) nextIds.push(p.id);
      nextTitles[p.id] = p.title;
    });
    setTitles(nextTitles);
    onChange(nextIds, nextTitles);
  };

  const remove = (id: string) => onChange(ids.filter((i) => i !== id), titles);

  return (
    <BlockStack gap="150">
      <Text as="span" variant="bodyMd">
        {label}
      </Text>
      <InlineStack gap="150">
        {ids.map((id) => (
          <Tag key={id} onRemove={() => remove(id)}>
            {titles[id] ?? lookup?.[id]?.title ?? numericId(id)}
          </Tag>
        ))}
      </InlineStack>
      <InlineStack>
        <Button onClick={add}>Add products</Button>
      </InlineStack>
      {helpText && (
        <Text as="span" tone="subdued">
          {helpText}
        </Text>
      )}
    </BlockStack>
  );
}

type Pick = { product_id: string; variant_id: string; title: string; price: string; image: string | null };

function PicksField({ label, helpText, value, onChange }: { label: string; helpText?: string; value: Pick[] | undefined; onChange: (picks: Pick[]) => void }) {
  const picks = value ?? [];

  const add = async () => {
    const results = await pickProducts({ multiple: true });
    if (!results) return;
    const additions: Pick[] = results
      .filter((p) => !picks.some((existing) => existing.product_id === p.id))
      .map((p) => ({
        product_id: p.id,
        variant_id: p.variants[0]?.id ?? '',
        title: p.title,
        price: p.variants[0]?.price ?? '',
        image: p.images?.[0]?.originalSrc ?? null,
      }));
    onChange([...picks, ...additions]);
  };

  const remove = (productId: string) => onChange(picks.filter((p) => p.product_id !== productId));

  return (
    <BlockStack gap="150">
      <Text as="span" variant="bodyMd">
        {label}
      </Text>
      <BlockStack gap="200">
        {picks.map((pick) => (
          <InlineStack key={pick.product_id} gap="200" blockAlign="center">
            {pick.image && <Thumbnail source={pick.image} alt={pick.title} size="small" />}
            <Text as="span">{pick.title}</Text>
            {pick.price && (
              <Text as="span" tone="subdued">
                {pick.price}
              </Text>
            )}
            <Button variant="plain" tone="critical" onClick={() => remove(pick.product_id)}>
              Remove
            </Button>
          </InlineStack>
        ))}
      </BlockStack>
      <InlineStack>
        <Button onClick={add}>Add products</Button>
      </InlineStack>
      {helpText && (
        <Text as="span" tone="subdued">
          {helpText}
        </Text>
      )}
    </BlockStack>
  );
}

type QuantityTier = { minQuantity: number; percentage: number };

function QuantityTiersField({ label, helpText, value, onChange }: { label: string; helpText?: string; value: QuantityTier[] | undefined; onChange: (tiers: QuantityTier[]) => void }) {
  const tiers = value && value.length > 0 ? value : [{ minQuantity: 2, percentage: 10 }];

  const update = (i: number, patch: Partial<QuantityTier>) => {
    const next = tiers.map((t, idx) => (idx === i ? { ...t, ...patch } : t));
    onChange(next);
  };

  return (
    <BlockStack gap="200">
      <Text as="span" variant="bodyMd">
        {label}
      </Text>
      {tiers.map((tier, i) => (
        <InlineStack key={i} gap="200" blockAlign="end">
          <div style={{ width: 140 }}>
            <TextField
              label="Buy at least"
              type="number"
              autoComplete="off"
              value={String(tier.minQuantity)}
              onChange={(v) => update(i, { minQuantity: Number(v) || 0 })}
            />
          </div>
          <div style={{ width: 140 }}>
            <TextField
              label="Save %"
              type="number"
              autoComplete="off"
              value={String(tier.percentage)}
              onChange={(v) => update(i, { percentage: Number(v) || 0 })}
            />
          </div>
          <Button variant="plain" tone="critical" onClick={() => onChange(tiers.filter((_, idx) => idx !== i))}>
            Remove
          </Button>
        </InlineStack>
      ))}
      <InlineStack>
        <Button onClick={() => onChange([...tiers, { minQuantity: tiers.length + 2, percentage: 10 }])}>Add tier</Button>
      </InlineStack>
      {helpText && (
        <Text as="span" tone="subdued">
          {helpText}
        </Text>
      )}
    </BlockStack>
  );
}

type BundleComponent = { productId: string; title?: string; quantity: number };

function BundleComponentsField({
  label,
  value,
  lookup,
  onChange,
}: {
  label: string;
  value: BundleComponent[] | undefined;
  lookup?: Lookup;
  onChange: (components: BundleComponent[]) => void;
}) {
  const components = value ?? [];

  const add = async () => {
    const result = await pickProduct();
    if (!result) return;
    if (components.some((c) => c.productId === result.id)) return;
    onChange([...components, { productId: result.id, title: result.title, quantity: 1 }]);
  };

  const setQuantity = (productId: string, quantity: number) =>
    onChange(components.map((c) => (c.productId === productId ? { ...c, quantity } : c)));

  const remove = (productId: string) => onChange(components.filter((c) => c.productId !== productId));

  return (
    <BlockStack gap="200">
      <Text as="span" variant="bodyMd">
        {label}
      </Text>
      {components.map((c) => (
        <InlineStack key={c.productId} gap="200" blockAlign="center">
          <Text as="span">{c.title ?? lookup?.[c.productId]?.title ?? numericId(c.productId)}</Text>
          <div style={{ width: 100 }}>
            <TextField
              label="Qty"
              labelHidden
              type="number"
              autoComplete="off"
              value={String(c.quantity)}
              onChange={(v) => setQuantity(c.productId, Number(v) || 1)}
            />
          </div>
          <Button variant="plain" tone="critical" onClick={() => remove(c.productId)}>
            Remove
          </Button>
        </InlineStack>
      ))}
      <InlineStack>
        <Button onClick={add}>Add component</Button>
      </InlineStack>
    </BlockStack>
  );
}

function Field({
  field,
  values,
  lookup,
  onChange,
}: {
  field: FieldDef;
  values: Values;
  lookup: Lookup;
  onChange: (key: string, value: unknown) => void;
}) {
  const value = values[field.key];

  switch (field.type) {
    case 'text':
      return (
        <TextField
          label={field.label}
          autoComplete="off"
          value={(value as string) ?? field.default ?? ''}
          onChange={(v) => onChange(field.key, v)}
          helpText={field.helpText}
        />
      );
    case 'textarea':
      return (
        <TextField
          label={field.label}
          autoComplete="off"
          multiline={3}
          value={(value as string) ?? field.default ?? ''}
          onChange={(v) => onChange(field.key, v)}
          helpText={field.helpText}
        />
      );
    case 'number':
      return (
        <TextField
          label={field.label}
          type="number"
          autoComplete="off"
          prefix={field.prefix}
          value={String(value ?? field.default ?? '')}
          onChange={(v) => onChange(field.key, v === '' ? '' : Number(v))}
        />
      );
    case 'color':
      return <ColorField label={field.label} value={(value as string) ?? field.default ?? '#111111'} onChange={(v) => onChange(field.key, v)} />;
    case 'select':
      return (
        <Select
          label={field.label}
          options={field.options}
          value={(value as string) ?? field.default}
          onChange={(v) => onChange(field.key, v)}
          helpText={field.helpText}
        />
      );
    case 'checkbox':
      return (
        <Checkbox
          label={field.label}
          checked={(value as boolean) ?? field.default ?? false}
          onChange={(v) => onChange(field.key, v)}
        />
      );
    case 'range':
      return (
        <RangeSlider
          label={`${field.label}: ${value ?? field.default ?? field.min}`}
          min={field.min}
          max={field.max}
          value={Number(value ?? field.default ?? field.min)}
          onChange={(v) => onChange(field.key, v)}
        />
      );
    case 'product':
      return (
        <ProductField
          label={field.label}
          helpText={field.helpText}
          value={value as string | undefined}
          lookup={lookup}
          onChange={(id) => onChange(field.key, id)}
        />
      );
    case 'variant':
      return (
        <VariantField
          label={field.label}
          helpText={field.helpText}
          value={value as string | undefined}
          lookup={lookup}
          onChange={(id) => onChange(field.key, id)}
        />
      );
    case 'products':
      return (
        <ProductsField
          label={field.label}
          helpText={field.helpText}
          value={value as string[] | undefined}
          lookup={lookup}
          onChange={(ids) => onChange(field.key, ids)}
        />
      );
    case 'picks':
      return (
        <PicksField
          label={field.label}
          helpText={field.helpText}
          value={value as Pick[] | undefined}
          onChange={(picks) => onChange(field.key, picks)}
        />
      );
    case 'quantity_tiers':
      return (
        <QuantityTiersField
          label={field.label}
          helpText={field.helpText}
          value={value as QuantityTier[] | undefined}
          onChange={(tiers) => onChange(field.key, tiers)}
        />
      );
    case 'bundle_components':
      return (
        <BundleComponentsField
          label={field.label}
          value={value as BundleComponent[] | undefined}
          lookup={lookup}
          onChange={(c) => onChange(field.key, c)}
        />
      );
  }
}

export default function SettingsForm({
  sections,
  values,
  lookup,
  onChange,
}: {
  sections: FieldSection[];
  values: Values;
  lookup?: Lookup;
  onChange: (key: string, value: unknown) => void;
}) {
  return (
    <BlockStack gap="500">
      {sections.map((section) => (
        <BlockStack gap="300" key={section.title}>
          <Text as="h3" variant="headingSm">
            {section.title}
          </Text>
          <BlockStack gap="300">
            {section.fields.map((field) => (
              <Field key={field.key} field={field} values={values} lookup={lookup ?? {}} onChange={onChange} />
            ))}
          </BlockStack>
        </BlockStack>
      ))}
    </BlockStack>
  );
}

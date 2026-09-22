import { BlockStack, Text, Card } from '@shopify/polaris';
import { TYPE_SCHEMAS } from '../lib/settingsSchema';

type V = Record<string, unknown>;
const str = (v: unknown, fallback = '') => (typeof v === 'string' && v ? v : fallback);
const num = (v: unknown, fallback = 0) => (typeof v === 'number' ? v : Number(v) || fallback);

function Frame({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <BlockStack gap="200">
      <Text as="span" tone="subdued">
        {label}
      </Text>
      <div
        style={{
          background: '#f6f6f7',
          borderRadius: 8,
          padding: 16,
          minHeight: 120,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        {children}
      </div>
    </BlockStack>
  );
}

function StickyBarPreview({ v }: { v: V }) {
  const bg = str(v.background_color, '#111111');
  const text = str(v.text_color, '#ffffff');
  const position = str(v.position, 'bottom');

  return (
    <Frame label={`Sticky Add to Cart — ${position === 'top' ? 'top of screen' : 'bottom of screen'}`}>
      <div
        style={{
          width: '100%',
          background: bg,
          color: text,
          borderRadius: 8,
          padding: '12px 16px',
          display: 'flex',
          alignItems: 'center',
          gap: 12,
        }}
      >
        {v.show_image !== false && <div style={{ width: 32, height: 32, background: 'rgba(255,255,255,.25)', borderRadius: 4 }} />}
        <div style={{ flex: 1, fontSize: 13 }}>Sample Product</div>
        {v.show_price !== false && <div style={{ fontSize: 13 }}>$29.99</div>}
        <div style={{ background: text, color: bg, borderRadius: 6, padding: '6px 14px', fontSize: 13, fontWeight: 600 }}>
          {str(v.button_text, 'Add to cart')}
        </div>
      </div>
    </Frame>
  );
}

function ProgressBar({ bg, fill, text, pct, message }: { bg: string; fill: string; text: string; pct: number; message: string }) {
  return (
    <div style={{ width: '100%', background: bg, color: text, borderRadius: 8, padding: 14, textAlign: 'center' as const }}>
      <div style={{ fontSize: 13, marginBottom: 8 }}>{message}</div>
      <div style={{ height: 6, borderRadius: 3, background: 'rgba(0,0,0,.1)' }}>
        <div style={{ height: '100%', width: `${pct}%`, borderRadius: 3, background: fill, transition: 'width .3s' }} />
      </div>
    </div>
  );
}

function ShippingBarPreview({ v }: { v: V }) {
  const threshold = num(v.threshold, 50);
  const sample = Math.min(threshold * 0.6, threshold - 1);
  const message = str(v.progress_message, 'Add %%AMOUNT%% more for free shipping!').replace(
    '%%AMOUNT%%',
    `$${(threshold - sample).toFixed(2)}`
  );

  return (
    <Frame label="Free Shipping Bar — 60% of the way there">
      <ProgressBar
        bg={str(v.background_color, '#f4f4f4')}
        fill={str(v.progress_color, '#1a7f37')}
        text={str(v.text_color, '#111111')}
        pct={60}
        message={message}
      />
    </Frame>
  );
}

function GoalTrackerPreview({ v }: { v: V }) {
  const tiers = [1, 2, 3]
    .map((i) => ({ threshold: num(v[`tier_${i}_threshold`]), label: str(v[`tier_${i}_label`]) }))
    .filter((t) => t.threshold > 0);
  const max = tiers.length ? Math.max(...tiers.map((t) => t.threshold)) : 100;
  const sample = max * 0.55;
  const next = tiers.find((t) => t.threshold > sample);

  return (
    <Frame label="Cart Goal Tracker">
      <ProgressBar
        bg={str(v.background_color, '#f4f4f4')}
        fill={str(v.progress_color, '#1a7f37')}
        text={str(v.text_color, '#111111')}
        pct={(sample / max) * 100}
        message={next ? `Add more to unlock: ${next.label}` : 'All tiers unlocked!'}
      />
    </Frame>
  );
}

function BadgesPreview({ v }: { v: V }) {
  const labels = [1, 2, 3, 4, 5, 6].map((i) => str(v[`badge_${i}_label`])).filter(Boolean);
  const stacked = str(v.layout) === 'stacked';

  return (
    <Frame label="Trust Badges">
      <div style={{ display: 'flex', flexDirection: stacked ? 'column' : 'row', gap: 12, flexWrap: 'wrap' }}>
        {(labels.length ? labels : ['Secure checkout', 'Money-back guarantee']).map((label) => (
          <div key={label} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
            <div style={{ width: 16, height: 16, borderRadius: '50%', background: '#111' }} />
            {label}
          </div>
        ))}
      </div>
    </Frame>
  );
}

function FaqPreview({ v }: { v: V }) {
  const items = [1, 2, 3].map((i) => ({ q: str(v[`question_${i}`]), a: str(v[`answer_${i}`]) })).filter((i) => i.q);

  return (
    <Frame label="Product FAQ">
      <div style={{ width: '100%', textAlign: 'left' as const }}>
        <div style={{ fontWeight: 600, marginBottom: 10 }}>{str(v.heading, 'Frequently asked questions')}</div>
        {(items.length ? items : [{ q: 'How long does shipping take?', a: '' }]).map((item) => (
          <div key={item.q} style={{ borderBottom: '1px solid rgba(0,0,0,.08)', padding: '8px 0', fontSize: 13 }}>
            {item.q}
          </div>
        ))}
      </div>
    </Frame>
  );
}

function UpsellListPreview({ v }: { v: V }) {
  const picks = Array.isArray(v.picks) ? (v.picks as Array<{ title: string; price?: string }>) : [];

  return (
    <Frame label={str(v.heading, 'Recommended products')}>
      <div style={{ width: '100%', textAlign: 'left' as const }}>
        {(picks.length ? picks : [{ title: 'Sample product', price: '$19.99' }]).slice(0, 3).map((p, i) => (
          <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '6px 0', fontSize: 13 }}>
            <div style={{ width: 28, height: 28, background: '#e3e3e3', borderRadius: 4 }} />
            <div style={{ flex: 1 }}>{p.title}</div>
            {p.price && <div>{p.price}</div>}
            <div
              style={{
                background: str(v.accent_color, '#111111'),
                color: '#fff',
                borderRadius: 4,
                padding: '2px 8px',
                fontSize: 11,
              }}
            >
              Add
            </div>
          </div>
        ))}
      </div>
    </Frame>
  );
}

function DiscountTagPreview({ type, v }: { type: string; v: V }) {
  let summary = str(v.message, 'Discount applied');

  if (type === 'quantity_discount' && Array.isArray(v.tiers) && v.tiers.length) {
    const tiers = v.tiers as Array<{ minQuantity: number; percentage: number }>;
    summary = tiers.map((t) => `Buy ${t.minQuantity}+, save ${t.percentage}%`).join(' · ');
  } else if (type === 'bogo' && v.buy_quantity) {
    summary = `Buy ${v.buy_quantity}, get ${num(v.get_discount_percentage, 100) === 100 ? 'one free' : `${v.get_discount_percentage}% off`}`;
  } else if (type === 'free_gift') {
    summary =
      v.trigger_type === 'product'
        ? 'Free gift when a trigger product is in the cart'
        : `Free gift when cart reaches $${num(v.trigger_amount, 100)}`;
  } else if (type === 'bundle' && v.discount_value) {
    summary = `Bundle: ${v.discount_value}${v.discount_type === 'fixed_amount' ? ' off' : '% off'}`;
  }

  return (
    <Frame label="How this shows up at checkout">
      <Card>
        <div style={{ padding: 12, fontSize: 13, fontWeight: 600, textAlign: 'center' as const }}>{summary}</div>
      </Card>
    </Frame>
  );
}

export default function ToolPreview({ type, values }: { type: string; values: V }) {
  const preview = TYPE_SCHEMAS[type]?.preview;

  switch (preview) {
    case 'sticky_bar':
      return <StickyBarPreview v={values} />;
    case 'shipping_bar':
      return <ShippingBarPreview v={values} />;
    case 'goal_tracker':
      return <GoalTrackerPreview v={values} />;
    case 'badges':
      return <BadgesPreview v={values} />;
    case 'faq':
      return <FaqPreview v={values} />;
    case 'upsell_list':
      return <UpsellListPreview v={values} />;
    case 'discount_tag':
      return <DiscountTagPreview type={type} v={values} />;
    default:
      return null;
  }
}

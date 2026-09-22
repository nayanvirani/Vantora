import { useState } from 'react';
import { Text } from '@shopify/polaris';

const WIDTH = 640;
const HEIGHT = 160;
const PAD = 24;

/**
 * Single-series trend line with a dynamic y-scale (unlike ScoreChart's
 * fixed 0-100) -- for arbitrary counts like daily impressions. Same
 * anatomy as ScoreChart per dataviz skill guidance: no legend needed for
 * one series (the section title names it), 2px line, rounded data-ends,
 * hover crosshair/tooltip, recessive grid.
 */
export default function TrendChart({
  points,
  label,
}: {
  points: Array<{ date: string; value: number }>;
  label: string;
}) {
  const [hoverIndex, setHoverIndex] = useState<number | null>(null);

  if (points.length < 2) {
    return (
      <Text as="p" tone="subdued">
        Not enough data yet to show a trend.
      </Text>
    );
  }

  const maxValue = Math.max(1, ...points.map((p) => p.value));
  const xFor = (i: number) => PAD + (i / (points.length - 1)) * (WIDTH - PAD * 2);
  const yFor = (value: number) => HEIGHT - PAD - (value / maxValue) * (HEIGHT - PAD * 2);

  const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${xFor(i)} ${yFor(p.value)}`).join(' ');
  const areaPath = `${linePath} L ${xFor(points.length - 1)} ${HEIGHT - PAD} L ${xFor(0)} ${HEIGHT - PAD} Z`;

  const hovered = hoverIndex !== null ? points[hoverIndex] : null;

  return (
    <div style={{ position: 'relative' }}>
      <svg
        viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
        style={{ width: '100%', height: 'auto', display: 'block' }}
        onMouseLeave={() => setHoverIndex(null)}
        onMouseMove={(e) => {
          const rect = e.currentTarget.getBoundingClientRect();
          const relX = ((e.clientX - rect.left) / rect.width) * WIDTH;
          const i = Math.round(((relX - PAD) / (WIDTH - PAD * 2)) * (points.length - 1));
          setHoverIndex(Math.max(0, Math.min(points.length - 1, i)));
        }}
      >
        {[0, 0.5, 1].map((frac) => (
          <line
            key={frac}
            x1={PAD}
            x2={WIDTH - PAD}
            y1={yFor(maxValue * frac)}
            y2={yFor(maxValue * frac)}
            stroke="var(--p-color-border-secondary, #e3e3e3)"
            strokeWidth={1}
          />
        ))}

        <path d={areaPath} fill="var(--p-color-bg-fill-info-secondary, #d5e7fb)" opacity={0.5} />
        <path
          d={linePath}
          fill="none"
          stroke="var(--p-color-icon-info, #1f6feb)"
          strokeWidth={2}
          strokeLinecap="round"
          strokeLinejoin="round"
        />

        <circle cx={xFor(0)} cy={yFor(points[0].value)} r={4} fill="var(--p-color-icon-info, #1f6feb)" />
        <circle
          cx={xFor(points.length - 1)}
          cy={yFor(points[points.length - 1].value)}
          r={4}
          fill="var(--p-color-icon-info, #1f6feb)"
        />

        {hoverIndex !== null && (
          <>
            <line
              x1={xFor(hoverIndex)}
              x2={xFor(hoverIndex)}
              y1={PAD}
              y2={HEIGHT - PAD}
              stroke="var(--p-color-border, #8a8a8a)"
              strokeWidth={1}
              strokeDasharray="3 3"
            />
            <circle
              cx={xFor(hoverIndex)}
              cy={yFor(points[hoverIndex].value)}
              r={5}
              fill="var(--p-color-bg-surface, #fff)"
              stroke="var(--p-color-icon-info, #1f6feb)"
              strokeWidth={2}
            />
          </>
        )}
      </svg>

      {hovered && (
        <div
          style={{
            position: 'absolute',
            left: `${(xFor(hoverIndex!) / WIDTH) * 100}%`,
            top: 0,
            transform: 'translate(-50%, -100%)',
            background: 'var(--p-color-bg-surface-inverse, #1a1a1a)',
            color: 'var(--p-color-text-inverse, #fff)',
            padding: '4px 8px',
            borderRadius: 6,
            fontSize: 12,
            whiteSpace: 'nowrap',
            pointerEvents: 'none',
          }}
        >
          {hovered.value} {label} · {new Date(hovered.date).toLocaleDateString()}
        </div>
      )}
    </div>
  );
}

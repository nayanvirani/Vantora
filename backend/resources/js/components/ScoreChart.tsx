import { useState } from 'react';
import { Text } from '@shopify/polaris';
import type { ScoreHistoryPoint } from '../lib/api';

const WIDTH = 640;
const HEIGHT = 180;
const PAD = 24;

/**
 * F-07 Score History. Single series (Health Score over time) -- per
 * dataviz skill guidance, a single series needs no legend (the section
 * title already names it), just a clean 2px line with rounded data-ends
 * and a hover crosshair/tooltip.
 */
export default function ScoreChart({ points }: { points: ScoreHistoryPoint[] }) {
  const [hoverIndex, setHoverIndex] = useState<number | null>(null);

  if (points.length < 2) {
    return (
      <Text as="p" tone="subdued">
        Run a few more audits to see your score trend.
      </Text>
    );
  }

  const xFor = (i: number) => PAD + (i / (points.length - 1)) * (WIDTH - PAD * 2);
  const yFor = (score: number) => HEIGHT - PAD - (score / 100) * (HEIGHT - PAD * 2);

  const linePath = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${xFor(i)} ${yFor(p.score_total)}`).join(' ');
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
        {/* recessive baseline grid */}
        {[0, 50, 100].map((tick) => (
          <line
            key={tick}
            x1={PAD}
            x2={WIDTH - PAD}
            y1={yFor(tick)}
            y2={yFor(tick)}
            stroke="var(--p-color-border-secondary, #e3e3e3)"
            strokeWidth={1}
          />
        ))}

        <path d={areaPath} fill="var(--p-color-bg-fill-success-secondary, #d3f2dd)" opacity={0.5} />
        <path
          d={linePath}
          fill="none"
          stroke="var(--p-color-icon-success, #1a7f37)"
          strokeWidth={2}
          strokeLinecap="round"
          strokeLinejoin="round"
        />

        {/* rounded data-ends at first/last point */}
        <circle cx={xFor(0)} cy={yFor(points[0].score_total)} r={4} fill="var(--p-color-icon-success, #1a7f37)" />
        <circle
          cx={xFor(points.length - 1)}
          cy={yFor(points[points.length - 1].score_total)}
          r={4}
          fill="var(--p-color-icon-success, #1a7f37)"
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
              cy={yFor(points[hoverIndex].score_total)}
              r={5}
              fill="var(--p-color-bg-surface, #fff)"
              stroke="var(--p-color-icon-success, #1a7f37)"
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
          {hovered.score_total} · {new Date(hovered.finished_at).toLocaleDateString()}
        </div>
      )}
    </div>
  );
}

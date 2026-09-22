import type { Shop } from '../lib/api';
import CategoryPage from './CategoryPage';

export default function Funnels({ shop }: { shop: Shop }) {
  return <CategoryPage category="funnels" shop={shop} />;
}

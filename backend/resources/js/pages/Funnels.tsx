import type { Shop } from '../lib/api';
import CategoryPage from './CategoryPage';

export default function Funnels({ shop, initialType }: { shop: Shop; initialType?: string | null }) {
  return <CategoryPage category="funnels" shop={shop} initialType={initialType} />;
}

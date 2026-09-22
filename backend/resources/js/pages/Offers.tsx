import type { Shop } from '../lib/api';
import CategoryPage from './CategoryPage';

export default function Offers({ shop, initialType }: { shop: Shop; initialType?: string | null }) {
  return <CategoryPage category="offers" shop={shop} initialType={initialType} />;
}

import { useEffect, useState } from 'react';
import { Page, Layout, Card, Text, Button, BlockStack, InlineStack, Badge, Banner } from '@shopify/polaris';
import { api, type Recipe, type RecipePreview } from '../lib/api';

export default function Recipes() {
  const [recipes, setRecipes] = useState<Recipe[]>([]);
  const [previews, setPreviews] = useState<Record<string, RecipePreview>>({});
  const [applying, setApplying] = useState<string | null>(null);
  const [applied, setApplied] = useState<Record<string, boolean>>({});

  useEffect(() => {
    api.get<Recipe[]>('/api/recipes').then((list) => {
      setRecipes(list);
      list.forEach((recipe) => {
        api.post<RecipePreview>(`/api/recipes/${recipe.key}/preview`).then((preview) => {
          setPreviews((prev) => ({ ...prev, [recipe.key]: preview }));
        });
      });
    });
  }, []);

  const apply = async (key: string) => {
    setApplying(key);
    try {
      await api.post(`/api/recipes/${key}/apply`);
      setApplied((prev) => ({ ...prev, [key]: true }));
    } catch (e) {
      alert((e as Error).message);
    } finally {
      setApplying(null);
    }
  };

  return (
    <Page title="Recipes" subtitle="Goal-based bundles of presets — approve once, activate everything together">
      <Layout>
        {recipes.map((recipe) => {
          const preview = previews[recipe.key];

          return (
            <Layout.Section key={recipe.key}>
              <Card>
                <BlockStack gap="300">
                  <Text as="h2" variant="headingMd">
                    {recipe.goal}
                  </Text>

                  {preview?.blocked_count ? (
                    <Banner tone="warning">
                      {preview.blocked_count} item{preview.blocked_count > 1 ? 's' : ''} will stay as drafts —
                      you're at your Starter plan limit for those tools.
                    </Banner>
                  ) : null}

                  <BlockStack gap="150">
                    {(preview?.items ?? recipe.items).map((item, i) => (
                      <InlineStack key={i} gap="200" blockAlign="center">
                        <Text as="span">{item.name}</Text>
                        {'allowed' in item && !item.allowed && <Badge tone="warning">Limit reached</Badge>}
                      </InlineStack>
                    ))}
                  </BlockStack>

                  <InlineStack>
                    <Button
                      variant="primary"
                      loading={applying === recipe.key}
                      disabled={applied[recipe.key]}
                      onClick={() => apply(recipe.key)}
                    >
                      {applied[recipe.key] ? 'Applied' : 'Approve and activate'}
                    </Button>
                  </InlineStack>
                </BlockStack>
              </Card>
            </Layout.Section>
          );
        })}
      </Layout>
    </Page>
  );
}

import { expect, test } from '@playwright/test';
import {
  createApiFixturePrefix,
  withApiFixture,
  withAnonymousApiTestAccess,
} from '../../fixtures/api-coverage';

interface JsonApiDocument {
  data: unknown[];
}

const acceptJsonApi = { Accept: 'application/vnd.api+json' };

test.describe('Explore API contracts', () => {
  test.describe.configure({ mode: 'serial' });

  test('returns the requested number of published content items', async ({ request }) => {
    await withAnonymousApiTestAccess(async () => {
      const prefix = createApiFixturePrefix('Explore');
      await withApiFixture({
        categories: { category: `${prefix} category` },
        nodes: Array.from({ length: 4 }, (_, index) => ({
          key: `node${index}`,
          title: `${prefix} content ${index}`,
          categoryKey: 'category',
          publishedAt: Math.floor(Date.now() / 1000) + index,
        })),
      }, async (fixture) => {
        const response = await request.get('/jsonapi/explore/node?page[limit]=4', { headers: acceptJsonApi });
        expect(response.status()).toBe(200);
        const document = await response.json() as JsonApiDocument;
        expect(document.data).toHaveLength(4);
      });
    });
  });
});
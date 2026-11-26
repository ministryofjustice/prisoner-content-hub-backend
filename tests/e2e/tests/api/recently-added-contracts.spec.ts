import { expect, test } from '@playwright/test';
import {
  createApiFixturePrefix,
  withApiFixture,
  withAnonymousApiTestAccess,
} from '../../fixtures/api-coverage';

interface JsonApiDocument {
  data: Array<{ id: string }>;
}

const acceptJsonApi = { Accept: 'application/vnd.api+json' };

test.describe('Recently Added API contracts', () => {
  test.describe.configure({ mode: 'serial' });

  test('sorts newest content and series first', async ({ request }) => {
    await withAnonymousApiTestAccess(async () => {
      const prefix = createApiFixturePrefix('Recently Added');
      const now = Math.floor(Date.now() / 1000);
      await withApiFixture({
        categories: { category: `${prefix} category` },
        series: [
          { key: 'secondSeries', name: `${prefix} second series` },
          { key: 'thirdSeries', name: `${prefix} third series` },
        ],
        nodes: [
          { key: 'first', title: `${prefix} first`, categoryKey: 'category', publishedAt: now, prioritised: true },
          { key: 'second', title: `${prefix} second`, seriesKey: 'secondSeries', publishedAt: now - 1, prioritised: true },
          { key: 'third', title: `${prefix} third`, seriesKey: 'thirdSeries', publishedAt: now - 5 },
          { key: 'fourth', title: `${prefix} fourth`, categoryKey: 'category', publishedAt: now - 7 },
        ],
      }, async (fixture) => {
        const response = await request.get('/jsonapi/recently-added?page[limit]=4', { headers: acceptJsonApi });
        expect(response.status()).toBe(200);
        const document = await response.json() as JsonApiDocument;
        const ids = document.data.map((item) => item.id);
        expect(document.data).toHaveLength(4);
        expect(ids).toContain(fixture.nodes.first.uuid);
        expect(ids).toContain(fixture.series.secondSeries.uuid);
        expect(ids.indexOf(fixture.nodes.first.uuid)).toBeLessThan(ids.indexOf(fixture.series.secondSeries.uuid));
      });
    });
  });

  test('invalidates cache when new content is published', async ({ request }) => {
    await withAnonymousApiTestAccess(async () => {
      const endpoint = '/jsonapi/recently-added?page[limit]=4';
      const firstResponse = await request.get(endpoint, { headers: acceptJsonApi });
      expect(firstResponse.status()).toBe(200);
      const cachedResponse = await request.get(endpoint, { headers: acceptJsonApi });
      expect(cachedResponse.headers()['x-drupal-cache']).toBe('HIT');

      const prefix = createApiFixturePrefix('Recently Added cache clear');
      await withApiFixture({
        categories: { category: `${prefix} category` },
        nodes: [{
          key: 'newNode',
          title: `${prefix} new content`,
          categoryKey: 'category',
          publishedAt: Math.floor(Date.now() / 1000) + 2,
        }],
      }, async (fixture) => {
        const updatedResponse = await request.get(endpoint, { headers: acceptJsonApi });
        expect(updatedResponse.status()).toBe(200);
        const document = await updatedResponse.json() as JsonApiDocument;
        expect(document.data.map((item) => item.id)).toContain(fixture.nodes.newNode.uuid);
      });
    });
  });
});
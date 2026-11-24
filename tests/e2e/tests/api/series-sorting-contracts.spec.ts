import { APIRequestContext, expect, test } from '@playwright/test';
import {
  ApiFixtureSpec,
  createApiFixturePrefix,
  withApiFixture,
  withAnonymousApiTestAccess,
} from '../../fixtures/api-coverage';

interface JsonApiDocument {
  data: Array<{ attributes: { title?: string } }>;
}

const acceptJsonApi = { Accept: 'application/vnd.api+json' };
const seriesNodes = [
  { key: 'episode1', title: 'Episode 1', season: 1, episode: 2, releaseDate: '2018-06-10' },
  { key: 'episode2', title: 'Episode 2', season: 1, episode: 5, releaseDate: '2018-09-10' },
  { key: 'episode3', title: 'Episode 3', season: 4, episode: 12, releaseDate: '2019-01-02' },
  { key: 'episode4', title: 'Episode 4', season: 11, episode: 22, releaseDate: '2020-02-12' },
  { key: 'episode5', title: 'Episode 5', season: 40, episode: 202, releaseDate: '2029-06-10' },
] as const;

function makeSeriesSortingFixture(sortBy: string, prefix: string): ApiFixtureSpec {
  return {
    series: [{ key: 'series', name: `${prefix} series`, sortBy }],
    nodes: seriesNodes.map((node) => ({
      ...node,
      title: `${prefix} ${node.title}`,
      seriesKey: 'series',
      publishedAt: Math.floor(Date.now() / 1000),
    })),
  };
}

async function expectSeriesOrder(
  request: APIRequestContext,
  sortBy: string,
  orderedKeys: readonly (typeof seriesNodes)[number]['key'][],
): Promise<void> {
  await withAnonymousApiTestAccess(async () => {
    const prefix = createApiFixturePrefix(sortBy);
    await withApiFixture(makeSeriesSortingFixture(sortBy, prefix), async (fixture) => {
      const query = new URLSearchParams({
        'filter[field_moj_series.meta.drupal_internal__tid]': String(fixture.series.series.id),
        sort: 'series_sort_value',
      });
      const response = await request.get(`/jsonapi/node/page?${query}`, { headers: acceptJsonApi });
      expect(response.status()).toBe(200);
      const document = await response.json() as JsonApiDocument;
      expect(document.data.map((item) => item.attributes.title)).toEqual(
        orderedKeys.map((key) => `${prefix} ${seriesNodes.find((node) => node.key === key)?.title}`),
      );
    });
  });
}

test.describe('Series sorting API contracts', () => {
  test.describe.configure({ mode: 'serial' });

  test('sorts by season and episode ascending', async ({ request }) => {
    await expectSeriesOrder(request, 'season_and_episode_asc', ['episode1', 'episode2', 'episode3', 'episode4', 'episode5']);
  });

  test('sorts by season and episode descending', async ({ request }) => {
    await expectSeriesOrder(request, 'season_and_episode_desc', ['episode5', 'episode4', 'episode3', 'episode2', 'episode1']);
  });

  test('sorts by release date ascending', async ({ request }) => {
    await expectSeriesOrder(request, 'release_date_asc', ['episode1', 'episode2', 'episode3', 'episode4', 'episode5']);
  });

  test('sorts by release date descending', async ({ request }) => {
    await expectSeriesOrder(request, 'release_date_desc', ['episode5', 'episode4', 'episode3', 'episode2', 'episode1']);
  });
});
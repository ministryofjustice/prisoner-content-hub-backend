import { randomUUID } from 'crypto';
import {
  createTemporaryDrupalUser,
  deleteTemporaryDrupalUser,
  DrupalRoleInput,
  ensureE2ETaxonomyTerms,
  executeDrushEval,
  TemporaryUser,
} from '../helpers/drupalUser';

export interface TaxonomySortingFixture {
  episodeSeriesId: number;
  releaseDateSeriesId: number;
}

function decodeMarkedJson<T>(output: string, marker: string): T {
  const match = output.match(new RegExp(`${marker}([A-Za-z0-9+/=]+)`));
  if (!match) {
    throw new Error(`Drush did not return ${marker} fixture data.`);
  }
  return JSON.parse(Buffer.from(match[1], 'base64').toString('utf8')) as T;
}

function encode(value: unknown): string {
  return Buffer.from(JSON.stringify(value)).toString('base64');
}

function createTaxonomySortingFixture(): TaxonomySortingFixture {
  const prefix = `Playwright series states ${randomUUID().slice(0, 8)}`;
  const encodedPrefix = encode(prefix);
  const marker = '__TAXONOMY_SORTING_FIXTURE__';
  const output = executeDrushEval(String.raw`
    $prefix = json_decode(base64_decode('${encodedPrefix}'), TRUE, 512, JSON_THROW_ON_ERROR);
    $episodeSeries = \Drupal\taxonomy\Entity\Term::create([
      'vid' => 'series',
      'name' => $prefix . ' Episode sorting',
      'field_sort_by' => 'season_and_episode_asc',
    ]);
    $episodeSeries->save();
    $releaseDateSeries = \Drupal\taxonomy\Entity\Term::create([
      'vid' => 'series',
      'name' => $prefix . ' Release date sorting',
      'field_sort_by' => 'release_date_desc',
    ]);
    $releaseDateSeries->save();
    $fixture = [
      'episodeSeriesId' => (int) $episodeSeries->id(),
      'releaseDateSeriesId' => (int) $releaseDateSeries->id(),
    ];
    print('${marker}' . base64_encode(json_encode($fixture, JSON_THROW_ON_ERROR)));
  `);
  return decodeMarkedJson<TaxonomySortingFixture>(output, marker);
}

function cleanupTaxonomySortingFixture(fixture: TaxonomySortingFixture): void {
  const encodedIds = encode([fixture.episodeSeriesId, fixture.releaseDateSeriesId]);
  executeDrushEval(String.raw`
    $termIds = json_decode(base64_decode('${encodedIds}'), TRUE, 512, JSON_THROW_ON_ERROR);
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach ($storage->loadMultiple(array_map('intval', $termIds)) as $term) {
      $term->delete();
    }
  `);
}

export async function withTaxonomySortingFixture<T>(
  role: DrupalRoleInput,
  action: (user: TemporaryUser, fixture: TaxonomySortingFixture) => Promise<T>,
): Promise<T> {
  ensureE2ETaxonomyTerms();
  const user = createTemporaryDrupalUser(role);
  let fixture: TaxonomySortingFixture | undefined;
  try {
    fixture = createTaxonomySortingFixture();
    return await action(user, fixture);
  } finally {
    try {
      if (fixture) {
        cleanupTaxonomySortingFixture(fixture);
      }
    } finally {
      deleteTemporaryDrupalUser(user.username);
    }
  }
}

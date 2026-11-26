/// <reference types="node" />

import { executeDrushEval } from '../helpers/drupalUser';
import { randomUUID } from 'crypto';

export interface ApiFixtureSpec {
  categories?: Record<string, string>;
  series?: Array<{
    key: string;
    name: string;
    sortBy?: string;
    categoryKey?: string;
  }>;
  nodes?: Array<{
    key: string;
    title: string;
    contentType?: string;
    categoryKey?: string;
    seriesKey?: string;
    publishedAt?: number;
    prioritised?: boolean;
    season?: number;
    episode?: number;
    releaseDate?: string;
  }>;
}

export interface ApiEntityReference {
  id: number;
  uuid: string;
}

export interface ApiFixture {
  nodeIds: number[];
  termIds: number[];
  seriesIds: number[];
  categories: Record<string, ApiEntityReference>;
  series: Record<string, ApiEntityReference>;
  nodes: Record<string, ApiEntityReference>;
}

const anonymousPermission = 'view entity without prison context';

function encode(value: unknown): string {
  return Buffer.from(JSON.stringify(value)).toString('base64');
}

export function createApiFixturePrefix(label: string): string {
  return `Playwright ${label} ${randomUUID().slice(0, 8)}`;
}

function decodeMarkedJson<T>(output: string, marker: string): T {
  const match = output.match(new RegExp(`${marker}([A-Za-z0-9+/=]+)`));
  if (!match) {
    throw new Error(`Drush did not return ${marker} fixture data.`);
  }
  return JSON.parse(Buffer.from(match[1], 'base64').toString('utf8')) as T;
}

function runDrushEvalWithResult<T>(marker: string, phpCode: string): T {
  return decodeMarkedJson<T>(executeDrushEval(phpCode), marker);
}

export function createApiFixture(spec: ApiFixtureSpec): ApiFixture {
  const encodedSpec = encode(spec);
  const marker = '__PLAYWRIGHT_API_FIXTURE__';

  return runDrushEvalWithResult<ApiFixture>(marker, String.raw`
    $data = json_decode(base64_decode('${encodedSpec}'), TRUE, 512, JSON_THROW_ON_ERROR);
    $result = [
      'nodeIds' => [],
      'termIds' => [],
      'seriesIds' => [],
      'categories' => [],
      'series' => [],
      'nodes' => [],
    ];
    $categories = [];
    foreach ($data['categories'] ?? [] as $key => $name) {
      $term = \Drupal\taxonomy\Entity\Term::create([
        'vid' => 'moj_categories',
        'name' => $name,
      ]);
      $term->save();
      $categories[$key] = $term;
      $result['termIds'][] = (int) $term->id();
      $result['categories'][$key] = ['id' => (int) $term->id(), 'uuid' => $term->uuid()];
    }
    $seriesTerms = [];
    foreach ($data['series'] ?? [] as $seriesData) {
      $values = [
        'vid' => 'series',
        'name' => $seriesData['name'],
      ];
      if (!empty($seriesData['sortBy'])) {
        $values['field_sort_by'] = $seriesData['sortBy'];
      }
      if (!empty($seriesData['categoryKey'])) {
        $values['field_category'] = [['target_id' => (int) $categories[$seriesData['categoryKey']]->id()]];
      }
      $term = \Drupal\taxonomy\Entity\Term::create($values);
      $term->save();
      $seriesTerms[$seriesData['key']] = $term;
      $result['termIds'][] = (int) $term->id();
      $result['seriesIds'][] = (int) $term->id();
      $result['series'][$seriesData['key']] = ['id' => (int) $term->id(), 'uuid' => $term->uuid()];
    }
    foreach ($data['nodes'] ?? [] as $nodeData) {
      $node = \Drupal\node\Entity\Node::create([
        'type' => $nodeData['contentType'] ?? 'page',
        'title' => $nodeData['title'],
        'uid' => 1,
        'status' => 1,
      ]);
      if ($node->hasField('moderation_state')) {
        $node->set('moderation_state', 'published');
      }
      if (isset($nodeData['publishedAt']) && $node->hasField('published_at')) {
        $node->set('published_at', (int) $nodeData['publishedAt']);
      }
      if (isset($nodeData['categoryKey']) && $node->hasField('field_moj_top_level_categories')) {
        $node->set('field_moj_top_level_categories', [['target_id' => (int) $categories[$nodeData['categoryKey']]->id()]]);
      }
      if (isset($nodeData['seriesKey']) && $node->hasField('field_moj_series')) {
        $node->set('field_moj_series', [['target_id' => (int) $seriesTerms[$nodeData['seriesKey']]->id()]]);
      }
      if (isset($nodeData['season']) && $node->hasField('field_moj_season')) {
        $node->set('field_moj_season', (int) $nodeData['season']);
      }
      if (isset($nodeData['episode']) && $node->hasField('field_moj_episode')) {
        $node->set('field_moj_episode', (int) $nodeData['episode']);
      }
      if (isset($nodeData['releaseDate']) && $node->hasField('field_release_date')) {
        $node->set('field_release_date', $nodeData['releaseDate']);
      }
      if (isset($nodeData['prioritised']) && $node->hasField('field_prioritise_on_recently_add')) {
        $node->set('field_prioritise_on_recently_add', (bool) $nodeData['prioritised']);
      }
      if ($node->hasField('field_summary')) {
        $node->set('field_summary', [['value' => 'Playwright API fixture']]);
      }
      if ($node->hasField('body')) {
        $node->set('body', [['value' => 'Playwright API fixture body', 'format' => 'basic_html']]);
      }
      $node->save();
      $result['nodeIds'][] = (int) $node->id();
      $result['nodes'][$nodeData['key']] = ['id' => (int) $node->id(), 'uuid' => $node->uuid()];
    }
    print('${marker}' . base64_encode(json_encode($result, JSON_THROW_ON_ERROR)));
  `);
}

export function cleanupApiFixture(fixture: ApiFixture): void {
  const encodedIds = encode({
    nodeIds: fixture.nodeIds,
    termIds: fixture.termIds,
    seriesIds: fixture.seriesIds,
  });
  executeDrushEval(String.raw`
    $data = json_decode(base64_decode('${encodedIds}'), TRUE, 512, JSON_THROW_ON_ERROR);
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    foreach ($nodeStorage->loadMultiple(array_map('intval', $data['nodeIds'])) as $node) {
      $node->delete();
    }
    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach ($termStorage->loadMultiple(array_map('intval', $data['seriesIds'])) as $term) {
      $term->delete();
    }
    $categoryIds = array_values(array_diff(array_map('intval', $data['termIds']), array_map('intval', $data['seriesIds'])));
    foreach ($termStorage->loadMultiple($categoryIds) as $term) {
      $term->delete();
    }
  `);
}

export async function withApiFixture<T>(
  spec: ApiFixtureSpec,
  action: (fixture: ApiFixture) => Promise<T>,
): Promise<T> {
  const fixture = createApiFixture(spec);
  try {
    return await action(fixture);
  } finally {
    cleanupApiFixture(fixture);
  }
}

export async function withAnonymousApiTestAccess<T>(action: () => Promise<T>): Promise<T> {
  const marker = '__PLAYWRIGHT_ANONYMOUS_PERMISSION__';
  const hadPermission = runDrushEvalWithResult<boolean>(marker, String.raw`
    $role = \Drupal\user\Entity\Role::load('anonymous');
    $hadPermission = $role->hasPermission('${anonymousPermission}');
    if (!$hadPermission) {
      $role->grantPermission('${anonymousPermission}');
      $role->save();
    }
    print('${marker}' . base64_encode(json_encode($hadPermission, JSON_THROW_ON_ERROR)));
  `);

  try {
    return await action();
  } finally {
    if (!hadPermission) {
      executeDrushEval(String.raw`
        $role = \Drupal\user\Entity\Role::load('anonymous');
        $role->revokePermission('${anonymousPermission}');
        $role->save();
      `);
    }
  }
}
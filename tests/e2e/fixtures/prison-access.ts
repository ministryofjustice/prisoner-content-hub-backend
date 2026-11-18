import { randomUUID } from 'crypto';
import {
  createTemporaryDrupalUser,
  deleteTemporaryDrupalUser,
  DrupalRoleInput,
  executeDrushEval,
  TemporaryUser,
} from '../helpers/drupalUser';

export interface PrisonAccessFixture {
  prefix: string;
  userId: number;
  contentTypes: string[];
  terms: {
    currentCategoryId: number;
    currentPrisonId: number;
    currentPrisonName: string;
    siblingPrisonId: number;
    otherPrisonId: number;
    otherPrisonName: string;
    extraPrisonId: number;
    contentCategoryId: number;
  };
  createdNodeIds: number[];
}

export interface PrisonAccessNodeOptions {
  title: string;
  contentType?: string;
  ownerPrisonIds?: number[];
  includedPrisonIds?: number[];
  excludedPrisonIds?: number[];
  authorUserId?: number;
}

function encodeValue(value: unknown): string {
  return Buffer.from(JSON.stringify(value)).toString('base64');
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

export function createPrisonAccessFixture(username: string): PrisonAccessFixture {
  const prefix = `Playwright ${randomUUID().slice(0, 8)}`;
  const phpUsername = encodeValue(username);
  const marker = '__PRISON_ACCESS_FIXTURE__';
  const fixture = runDrushEvalWithResult<Omit<PrisonAccessFixture, 'prefix' | 'createdNodeIds'>>(marker, String.raw`
    $username = json_decode(base64_decode('${phpUsername}'), TRUE, 512, JSON_THROW_ON_ERROR);
    $user = user_load_by_name($username);
    if (!$user) {
      throw new \RuntimeException('Temporary prison access user was not found.');
    }

    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $loadTerm = static function (string $vocabularyId, string $name) use ($termStorage) {
      $matches = $termStorage->loadByProperties(['vid' => $vocabularyId, 'name' => $name]);
      return reset($matches) ?: NULL;
    };
    $currentCategory = $loadTerm('prisons', 'Female');
    $currentPrison = $loadTerm('prisons', 'New Hall');
    $siblingPrison = $loadTerm('prisons', 'Styal');
    $otherPrison = $loadTerm('prisons', 'Feltham A');
    $extraPrison = $loadTerm('prisons', 'Werrington');
    if (!$currentCategory || !$currentPrison || !$siblingPrison || !$otherPrison || !$extraPrison) {
      throw new \RuntimeException('Required prison taxonomy terms are missing from the database.');
    }
    $findUnscopedContentTerm = static function (string $vocabularyId) use ($termStorage) {
      foreach ($termStorage->loadByProperties(['vid' => $vocabularyId]) as $term) {
        if (!$term->hasField('field_prisons') || $term->get('field_prisons')->isEmpty()) {
          return $term;
        }
      }
      return NULL;
    };
    $contentCategory = $findUnscopedContentTerm('moj_categories');
    if (!$contentCategory) {
      throw new \RuntimeException('No existing unscoped content Category is available for test content.');
    }

    $userPrisonField = \Drupal::getContainer()->getParameter('prisoner_hub_prison_access_cms.user_prison_field_name');
    $user->set($userPrisonField, [['target_id' => $currentPrison->id()]]);
    $user->save();

    $ownerField = \Drupal::getContainer()->getParameter('prisoner_hub_prison_access_cms.prison_owner_field_name');
    $fieldMap = \Drupal::service('entity_field.manager')->getFieldMap();
    $contentTypes = array_values($fieldMap['node'][$ownerField]['bundles'] ?? []);
    $fixture = [
      'userId' => (int) $user->id(),
      'contentTypes' => $contentTypes,
      'terms' => [
        'currentCategoryId' => (int) $currentCategory->id(),
        'currentPrisonId' => (int) $currentPrison->id(),
        'currentPrisonName' => $currentPrison->label(),
        'siblingPrisonId' => (int) $siblingPrison->id(),
        'otherPrisonId' => (int) $otherPrison->id(),
        'otherPrisonName' => $otherPrison->label(),
        'extraPrisonId' => (int) $extraPrison->id(),
        'contentCategoryId' => (int) $contentCategory->id(),
      ],
    ];
    print('${marker}' . base64_encode(json_encode($fixture, JSON_THROW_ON_ERROR)));
  `);

  return {
    prefix,
    ...fixture,
    createdNodeIds: [],
  };
}

export function setUserPrisons(username: string, prisonIds: number[]): void {
  const phpUsername = encodeValue(username);
  const phpPrisonIds = encodeValue(prisonIds);
  executeDrushEval(String.raw`
    $username = json_decode(base64_decode('${phpUsername}'), TRUE, 512, JSON_THROW_ON_ERROR);
    $user = user_load_by_name($username);
    if (!$user) {
      throw new \RuntimeException('Temporary prison access user was not found.');
    }
    $fieldName = \Drupal::getContainer()->getParameter('prisoner_hub_prison_access_cms.user_prison_field_name');
    $ids = json_decode(base64_decode('${phpPrisonIds}'), TRUE);
    $user->set($fieldName, array_map(static fn ($id) => ['target_id' => (int) $id], $ids));
    $user->save();
  `);
}

export function createPrisonAccessNode(
  fixture: PrisonAccessFixture,
  options: PrisonAccessNodeOptions,
): number {
  const phpData = encodeValue({
    title: options.title,
    contentType: options.contentType ?? 'page',
    ownerPrisonIds: options.ownerPrisonIds ?? [],
    includedPrisonIds: options.includedPrisonIds ?? [],
    excludedPrisonIds: options.excludedPrisonIds ?? [],
    authorUserId: options.authorUserId ?? 1,
    contentCategoryId: fixture.terms.contentCategoryId,
  });
  const marker = '__PRISON_ACCESS_NODE__';
  const nodeId = Number(runDrushEvalWithResult<string>(marker, String.raw`
    $data = json_decode(base64_decode('${phpData}'), TRUE, 512, JSON_THROW_ON_ERROR);
    $ownerField = \Drupal::getContainer()->getParameter('prisoner_hub_prison_access_cms.prison_owner_field_name');
    $prisonField = \Drupal::getContainer()->getParameter('prisoner_hub_prison_access.prison_field_name');
    $excludeField = \Drupal::getContainer()->getParameter('prisoner_hub_prison_access.exclude_from_prison_field_name');
    $references = static fn (array $ids) => array_map(static fn ($id) => ['target_id' => (int) $id], $ids);
    $node = \Drupal\node\Entity\Node::create([
      'type' => $data['contentType'],
      'title' => $data['title'],
      'uid' => (int) $data['authorUserId'],
      'status' => 1,
    ]);
    $node->set($ownerField, $references($data['ownerPrisonIds']));
    $node->set($prisonField, $references($data['includedPrisonIds']));
    $node->set($excludeField, $references($data['excludedPrisonIds']));
    if ($node->hasField('moderation_state')) {
      $node->set('moderation_state', 'published');
    }
    if ($node->hasField('field_moj_top_level_categories')) {
      $node->set('field_moj_top_level_categories', [['target_id' => (int) $data['contentCategoryId']]]);
    }
    if ($node->hasField('field_summary')) {
      $node->set('field_summary', [['value' => 'Prison access test summary']]);
    }
    if ($node->hasField('field_main_body_content')) {
      $node->set('field_main_body_content', [['value' => 'Prison access test content', 'format' => 'full_html']]);
    }
    $node->save();
    print('${marker}' . base64_encode((string) $node->id()));
  `));
  fixture.createdNodeIds.push(nodeId);
  return nodeId;
}

export function cleanupPrisonAccessFixture(fixture: PrisonAccessFixture): void {
  const phpData = encodeValue({ nodeIds: fixture.createdNodeIds });
  executeDrushEval(String.raw`
    $data = json_decode(base64_decode('${phpData}'), TRUE, 512, JSON_THROW_ON_ERROR);
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    foreach ($nodeStorage->loadMultiple(array_map('intval', $data['nodeIds'])) as $node) {
      $node->delete();
    }
  `);
}

export async function runWithPrisonAccessUser<T>(
  action: (user: TemporaryUser, fixture: PrisonAccessFixture) => Promise<T>,
  roles: DrupalRoleInput = 'moj_local_content_manager',
): Promise<T> {
  const user = createTemporaryDrupalUser(roles);
  let fixture: PrisonAccessFixture | undefined;
  try {
    fixture = createPrisonAccessFixture(user.username);
    return await action(user, fixture);
  } finally {
    try {
      deleteTemporaryDrupalUser(user.username);
    } finally {
      if (fixture) {
        cleanupPrisonAccessFixture(fixture);
      }
    }
  }
}

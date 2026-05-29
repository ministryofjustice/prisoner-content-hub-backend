/// <reference types="node" />

import { execSync } from 'child_process';
import { randomUUID } from 'crypto';

export interface TemporaryUser {
  username: string;
  password: string;
}

let taxonomySeededForSession = false;
const defaultCategoryTerm = process.env.PLAYWRIGHT_E2E_CATEGORY_TERM ?? 'Animated shorts';
const defaultSeriesTerm = process.env.PLAYWRIGHT_E2E_SERIES_TERM ?? defaultCategoryTerm;
const defaultSeedNodeTitle = process.env.PLAYWRIGHT_E2E_SEED_NODE_TITLE ?? defaultCategoryTerm;
const defaultPrisonTerm = process.env.PLAYWRIGHT_E2E_PRISON_TERM ?? '';

const drushCommand = process.env.PLAYWRIGHT_DRUSH_COMMAND ?? 'docker-compose exec -T drupal drush';

const roleLabelByRoleId: Record<string, string> = {
  moj_local_content_manager: 'Local-Content-Manager',
  local_administrator: 'Local-Administrator',
  administrator: 'Administrator',
  comms_live_service_hq: 'Comms-Live-Service-HQ',
  translator: 'Translator',
  approved_publisher: 'Approved-Publisher',
};

function quote(value: string): string {
  return `'${value.replace(/'/g, `'"'"'`)}'`;
}

function phpSingleQuoted(value: string): string {
  return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function runDrush(args: string[]): string {
  const command = `${drushCommand} ${args.map(quote).join(' ')}`;
  return execSync(command, {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });
}

function roleLabel(role: string): string {
  const mapped = roleLabelByRoleId[role];
  if (mapped) {
    return mapped;
  }

  const roleSlug = role
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return roleSlug.slice(0, 16) || 'user';
}

function isDeadlockError(error: unknown): boolean {
  if (!(error instanceof Error)) {
    return false;
  }
  return /SQLSTATE\[40001\]|Deadlock found when trying to get lock|Serialization failure/i.test(error.message);
}

function runDrushWithRetry(args: string[], retries = 3): string {
  let attempts = 0;
  while (attempts < retries) {
    try {
      return runDrush(args);
    } catch (error) {
      attempts += 1;
      if (!isDeadlockError(error) || attempts >= retries) {
        throw error;
      }
    }
  }

  throw new Error('Unreachable retry state while running drush command.');
}

export function canManageDrupalUsersFromTests(): boolean {
  try {
    runDrush(['--version']);
    return true;
  } catch {
    return false;
  }
}

export function createTemporaryDrupalUser(role = 'moj_local_content_manager'): TemporaryUser {
  const suffix = randomUUID().slice(0, 8);
  const username = `${roleLabel(role)}-${suffix}`;
  const password = `${suffix}-A1!`;
  const email = `${username}@example.test`;

  runDrushWithRetry(['user:create', username, `--mail=${email}`, `--password=${password}`]);
  runDrushWithRetry(['user:role:add', role, username]);

  return { username, password };
}

export function deleteTemporaryDrupalUser(username: string): void {
  runDrush(['user:cancel', username, '--delete-content', '--yes']);
}

export function ensureE2ETaxonomyTerms(): void {
  if (taxonomySeededForSession) {
    return;
  }

  const phpEval = [
    `$map = ["moj_categories" => ${phpSingleQuoted(defaultCategoryTerm)}, "series" => ${phpSingleQuoted(defaultSeriesTerm)}];`,
    '$storage = \\Drupal::entityTypeManager()->getStorage("taxonomy_term");',
    `$prisonName = ${phpSingleQuoted(defaultPrisonTerm)};`,
    '$prisonTid = NULL;',
    'if ($prisonName !== "") {',
    '  $matchingPrison = $storage->loadByProperties(["vid" => "prisons", "name" => $prisonName]);',
    '  if (!empty($matchingPrison)) {',
    '    $prisonTerm = reset($matchingPrison);',
    '    $prisonTid = (int) $prisonTerm->id();',
    '  }',
    '}',
    'if (empty($prisonTid)) {',
    '  $prisonTree = $storage->loadTree("prisons", 0, 1);',
    '  if (!empty($prisonTree)) {',
    '    $prisonTid = (int) $prisonTree[0]->tid;',
    '  }',
    '}',
    '$termIds = [];',
    'foreach ($map as $vid => $name) {',
    '  $existing = $storage->loadByProperties(["vid" => $vid, "name" => $name]);',
    '  if (empty($existing)) {',
    '    $term = \\Drupal\\taxonomy\\Entity\\Term::create(["vid" => $vid, "name" => $name]);',
    '    if (!empty($prisonTid) && $term->hasField("field_prisons")) {',
    '      $term->set("field_prisons", [["target_id" => $prisonTid]]);',
    '    }',
    '    $term->save();',
    '    $termIds[$vid] = (int) $term->id();',
    '  }',
    '  else {',
    '    $existingTerm = reset($existing);',
    '    if (!empty($prisonTid) && $existingTerm->hasField("field_prisons") && $existingTerm->get("field_prisons")->isEmpty()) {',
    '      $existingTerm->set("field_prisons", [["target_id" => $prisonTid]]);',
    '      $existingTerm->save();',
    '    }',
    '    $termIds[$vid] = (int) $existingTerm->id();',
    '  }',
    '}',
    `$seedTitle = ${phpSingleQuoted(defaultSeedNodeTitle)};`,
    '$nodeStorage = \\Drupal::entityTypeManager()->getStorage("node");',
    '$existingNodes = $nodeStorage->loadByProperties(["type" => "page", "title" => $seedTitle]);',
    'if (!empty($termIds["moj_categories"]) && !empty($termIds["series"])) {',
    '  if (empty($existingNodes)) {',
    '    $node = \\Drupal\\node\\Entity\\Node::create([',
    '      "type" => "page",',
    '      "title" => $seedTitle,',
    '      "status" => 1,',
    '      "uid" => 1,',
    '      "field_summary" => [["value" => "Playwright seed summary"]],',
    '      "body" => [["value" => "Playwright seed body", "format" => "basic_html"]],',
    '      "field_moj_top_level_categories" => [["target_id" => $termIds["moj_categories"]]],',
    '      "field_moj_series" => [["target_id" => $termIds["series"]]],',
    '    ]);',
    '    if (!empty($prisonTid) && $node->hasField("field_prisons")) {',
    '      $node->set("field_prisons", [["target_id" => $prisonTid]]);',
    '    }',
    '    $node->save();',
    '  }',
    '  else {',
    '    $existingNode = reset($existingNodes);',
    '    $existingNode->set("status", 1);',
    '    $existingNode->set("field_moj_top_level_categories", [["target_id" => $termIds["moj_categories"]]]);',
    '    $existingNode->set("field_moj_series", [["target_id" => $termIds["series"]]]);',
    '    if (!empty($prisonTid) && $existingNode->hasField("field_prisons") && $existingNode->get("field_prisons")->isEmpty()) {',
    '      $existingNode->set("field_prisons", [["target_id" => $prisonTid]]);',
    '    }',
    '    $existingNode->save();',
    '  }',
    '}',
    'print("ok");',
  ].join(' ');

  runDrushWithRetry(['php:eval', phpEval]);
  taxonomySeededForSession = true;
}

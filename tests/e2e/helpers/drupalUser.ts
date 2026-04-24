/// <reference types="node" />

import { execSync } from 'child_process';
import { randomUUID } from 'crypto';

export interface TemporaryUser {
  username: string;
  password: string;
}

const drushCommand = process.env.PLAYWRIGHT_DRUSH_COMMAND ?? 'docker-compose exec -T drupal drush';

function quote(value: string): string {
  return `'${value.replace(/'/g, `'"'"'`)}'`;
}

function runDrush(args: string[]): string {
  const command = `${drushCommand} ${args.map(quote).join(' ')}`;
  return execSync(command, {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });
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
  const username = `pw-e2e-${suffix}`;
  const password = `PwE2e-${suffix}-A1!`;
  const email = `${username}@example.test`;

  runDrushWithRetry(['user:create', username, `--mail=${email}`, `--password=${password}`]);
  runDrushWithRetry(['user:role:add', role, username]);

  return { username, password };
}

export function deleteTemporaryDrupalUser(username: string): void {
  runDrush(['user:cancel', username, '--delete-content', '--yes']);
}

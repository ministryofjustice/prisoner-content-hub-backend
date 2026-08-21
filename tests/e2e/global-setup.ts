import { execSync } from 'child_process';

function run(command: string, options?: { optional?: boolean }): void {
  try {
    execSync(command, {
      encoding: 'utf8',
      stdio: 'inherit',
    });
  } catch (error) {
    if (!options?.optional) {
      throw error;
    }
  }
}

export default async function globalSetup(): Promise<void> {
  const drushCommand = process.env.PLAYWRIGHT_DRUSH_COMMAND ?? 'docker compose exec -T drupal vendor/bin/drush';

  // Rebuild caches so any settings/config changes are reflected before tests.
  run(`${drushCommand} cr -y`);
}
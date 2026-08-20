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

  // Drupal 11 can stream/attach frontend assets later in the request lifecycle.
  // Keep E2E deterministic by disabling aggregation/compression and BigPipe.
  const requiredCommands = [
    `${drushCommand} cset system.performance css.preprocess 0 -y`,
    `${drushCommand} cset system.performance js.preprocess 0 -y`,
    `${drushCommand} cset system.performance css.gzip 0 -y`,
    `${drushCommand} cset system.performance js.gzip 0 -y`,
    `${drushCommand} cset system.performance cache.page.max_age 0 -y`,
  ];

  for (const command of requiredCommands) {
    run(command);
  }

  run(`${drushCommand} pm:uninstall big_pipe -y`, { optional: true });
  run(`${drushCommand} cr -y`);
}
import { execSync } from 'child_process';

function run(command: string): void {
  execSync(command, {
    encoding: 'utf8',
    stdio: 'inherit',
  });
}

export default async function globalSetup(): Promise<void> {
  const drushCommand = process.env.PLAYWRIGHT_DRUSH_COMMAND ?? 'docker compose exec -T drupal vendor/bin/drush';
  const command = [
    `${drushCommand} cset system.performance css.preprocess 0 -y`,
    `${drushCommand} cset system.performance js.preprocess 0 -y`,
    `${drushCommand} cr -y`,
  ].join(' && ');

  run(command);
}
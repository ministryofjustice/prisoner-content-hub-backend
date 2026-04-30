
const allRoleIds = [
  'administrator',
  'local_administrator',
  'moj_local_content_manager',
  'comms_live_service_hq',
  'approved_publisher',
  'translator',
] as const;

export const appSettings = {
  commands: {
    drush: process.env.PLAYWRIGHT_DRUSH_COMMAND ?? 'docker-compose exec -T drupal drush',
  },
  roles: {
    all: allRoleIds,
    accessTest: 'moj_local_content_manager',
    lcmTest: 'moj_local_content_manager',
    studioAdminTest: 'local_administrator',
    administrator: 'administrator',
    commsLiveServiceHq: 'comms_live_service_hq',
    translator: 'translator',
    approvedPublisher: 'approved_publisher',
  },
};

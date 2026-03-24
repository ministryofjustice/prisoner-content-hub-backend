<?php

declare(strict_types=1);

namespace Drupal\prisoner_hub_audit_logs;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;

/**
 * Generates logs for events that require auditing.
 */
final readonly class LogGenerator {

  /**
   * Constructs a LogGenerator object.
   */
  public function __construct(
    private AccountProxyInterface $currentUser,
    private LoggerChannelInterface $loggerChannel,
  ) {}

  /**
   * Log that a user has been granted the administrator role.
   */
  public function logUserGrantedAdministratorRole(UserInterface $adminUser, array $previousRoles = []): void {
    $context = [
      '%granting_user_email' => $this->currentUser->getEmail(),
      '%granting_user_name' => $this->currentUser->getDisplayName(),
      '%granting_user_id' => $this->currentUser->id(),
      '%granted_user_email' => $adminUser->getEmail(),
      '%granted_user_name' => $adminUser->getDisplayName(),
      '%granted_user_id' => $adminUser->id(),
      '%admin_user_roles' => implode(', ', $adminUser->getRoles()),
      '%admin_user_previous_roles' => implode(', ', $previousRoles),
    ];
    $this->loggerChannel->log('INFO', 'User %granted_user_name with email address %granted_user_email and ID %granted_user_id has been granted the administrator role. They were granted that role by %granting_user_name with email address %granting_user_email and ID %granting_user_id. Their full set of roles is now %admin_user_roles. Their previous full set of roles was %admin_user_previous_roles.', $context);
  }

}

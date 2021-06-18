<?php

namespace Drupal\prisoner_hub_cms_help\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use League\Container\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'AuthorInformationBlock' block.
 *
 * @Block(
 *  id = "author_information_block",
 *  admin_label = @Translation("Author information block"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node")
 *  }
 * )
 */
class AuthorInformationBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs an AggregatorFeedBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param DateFormatterInterface $date_formatter
   *   The entity storage for feeds.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DateFormatterInterface $date_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');

    $build = [];
    $build['#theme'] = 'author_information_block';
    $build['#author_name'] = $node->getOwner()->getDisplayName();
    $build['#date'] = $this->dateFormatter->format($node->getCreatedTime(), 'medium');

    return $build;
  }

}

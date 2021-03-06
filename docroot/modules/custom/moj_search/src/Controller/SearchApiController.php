<?php

namespace Drupal\moj_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\Language\LanguageInterface;
use Drupal\node\NodeInterface;

class SearchApiController extends ControllerBase
{
    /**
     * Node serializer.
     *
     * @var \Symfony\Component\Serializer\Serializer
     */

    protected $serializer;

    /**
     * http requestStack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */

    protected $requestStack;

    /**
     * Entity Manager.
     *
     * @var \Drupal\Core\Entity\EntityManagerInterface
     */

    protected $entityManager;

    /**
     * Var to hold array of nodes.
     *
     * @var array()
     */

    protected $nodeStorage;


    /**
     * A variable to hold parse mode object.
     *
     * @var object
     */

    protected $SearchApiParseMode;

    /**
     * A variable to hold the pase mode conjunction 'AND/OR'
     *
     * @var object
     */

    protected $setConjunction;

    /**
     * SearchApiController constructor.
     *
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
     * @param $SearchApiParseMode
     * @param \Symfony\Component\Serializer\Serializer $serializer
     */

    public function __construct(
      RequestStack $request_stack,
      EntityManagerInterface $entityManager,
      $SearchApiParseMode,
      Serializer $serializer
    ) {
        $this->requestStack = $request_stack;
        $this->entityManager = $entityManager;
        $this->nodeStorage = $this->entityManager->getStorage('node');
        $this->SearchApiParseMode = $SearchApiParseMode->createInstance('direct'); // plugin.manager.search_api.parse_mode
        $this->setConjunction = $this->SearchApiParseMode->setConjunction('OR');
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */

    public static function create(ContainerInterface $container)
    {
        return new static(
          $container->get('request_stack'),
          $container->get('entity.manager'),
          $container->get('plugin.manager.search_api.parse_mode'),
          $container->get('moj_search.serializer.default')
        );
    }

    /**
     * Gets the language of the current request.
     * @return
     *   The language of the current request.
     */

    protected static function getCurrentLanguage() {
        return \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    }

    /**
     * Translates the given node into the current language of the request.
     * @param \Drupal\node\NodeInterface $node
     *   The node to translate.
     *
     * @return
     *   The node translated into the language of the current request.
     */

    protected static function translateNode(NodeInterface $node) {
        $lang = self::getCurrentLanguage();
        $langcode = $lang->getId();

        if ($node->hasTranslation($langcode)) {
            return $node->getTranslation($langcode);
        } else {
            return $node;
        }
    }

    /**
     * searchApiEndpoint()
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */

    public function searchApiEndpoint()
    {
        $keywords = $this->requestStack->getCurrentRequest()->query->get('q');
        $results = $this->seachContent($keywords);
        $nids = $this->parseResults($results);
        $nodes = $this->loadNodes($nids);
        $items = $this->formatResults($nodes);
        return new JsonResponse($items);
    }

    /**
     * seachContent()
     *
     * @return \Drupal\search_api\Item\ItemInterface[]
     */

    private function seachContent($keywords)
    {
        $index = \Drupal\search_api\Entity\Index::load('default_index');
        $query = $index->query()
        ->setParseMode($this->SearchApiParseMode)
        ->keys($keywords)
        ->setFulltextFields(['title', 'name', 'body'])
        ->addCondition('status', 1);
        return $query->execute();
    }

    /**
     * @return array
     */

    private function parseResults($results)
    {
        $list = [];
        foreach ($results->getResultItems() AS $item) {
            $data = explode(':', $item->getId());
            $data = explode('/', $data[1]);
            $list[] = $data[1];
        }
        return $list;
    }

    /**
     * @param array $nids
     *
     * @return array
     */

    private function loadNodes(array $nids)
    {
        $node_storage = \Drupal::entityTypeManager()->getStorage('node'); // TODO: Inject dependency
        $items = array_filter(
          $node_storage->loadMultiple($nids),
          function ($item) {
              return $item->access();
          }
        );
        $items = array_map(function ($i) {
            return self::translateNode($i);
        }, $items);

        return $items;
    }

    /**
     * @param array $nodes
     *
     * @return array|bool
     */

    private function formatResults(array $nodes)
    {
        $items = [];
        if (!empty($nodes)) {
            foreach ($nodes as $node) {
                if ($node->getType() !== 'moj_hub_item') {
                    $items[] = $this->serializer->normalize(
                      $node,
                      'json',
                      ['plugin_id' => 'entity']
                    );
                }
            }
            return $items;
        } else {
            return false;
        }
    }
}
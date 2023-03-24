<?php

namespace Drupal\prisoner_hub_linkit_matcher\Plugin\Linkit\Matcher;

use Drupal\linkit\MatcherBase;
use Drupal\linkit\MatcherInterface;
use Drupal\linkit\Suggestion\DescriptionSuggestion;
use Drupal\linkit\Suggestion\SuggestionCollection;

/**
 * Matcher for front end urls.
 *
 * @Matcher(
 *   id = "prisoner_hub:front_end",
 *   label = @Translation("Profile links")
 * )
 */
class FrontEndMatcher extends MatcherBase implements MatcherInterface {

  /**
   * Gets all suggestions potentially relevant to this matcher.
   *
   * @return \Drupal\linkit\Suggestion\SuggestionCollection
   *   Collection of all suggestions against which this matcher could match.
   */
  protected function searchSuggestions(): SuggestionCollection {
    $suggestionCollection = new SuggestionCollection();

    $suggestion = new DescriptionSuggestion();
    $suggestion->setLabel($this->t('Profile'));
    $suggestion->setPath('/profile');
    $suggestion->setDescription('Prisoner profile');
    $suggestion->setGroup('Profile links');
    $suggestionCollection->addSuggestion($suggestion);

    $suggestion = new DescriptionSuggestion();
    $suggestion->setLabel($this->t('Timetable'));
    $suggestion->setPath('/timetable');
    $suggestion->setDescription('Prisoner timetable');
    $suggestion->setGroup('Profile links');
    $suggestionCollection->addSuggestion($suggestion);

    $suggestion = new DescriptionSuggestion();
    $suggestion->setLabel($this->t('Transactions'));
    $suggestion->setPath('/money/transactions');
    $suggestion->setDescription('Prisoner transactions within profile');
    $suggestion->setGroup('Profile links');
    $suggestionCollection->addSuggestion($suggestion);

    $suggestion = new DescriptionSuggestion();
    $suggestion->setLabel($this->t('Approved Visitors'));
    $suggestion->setPath('/approved-visitors');
    $suggestion->setDescription('Prisoner approved visitors within profile');
    $suggestion->setGroup('Profile links');
    $suggestionCollection->addSuggestion($suggestion);

    return $suggestionCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($string): SuggestionCollection {
    // Get all possible suggestions.
    $suggestions = $this->searchSuggestions()->getSuggestions();
    $matchedSuggestions = new SuggestionCollection();

    // Add any suggestions whose label or path match the user input.
    foreach ($suggestions as $suggestion) {
      if (str_contains(strtoupper($suggestion->getLabel()), strtoupper($string))
        || str_contains(strtoupper($suggestion->getPath()), strtoupper($string))) {
        $matchedSuggestions->addSuggestion($suggestion);
      }
    }

    return $matchedSuggestions;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): array {
    return [$this->t('Matches known prisoner profile links')];
  }

}

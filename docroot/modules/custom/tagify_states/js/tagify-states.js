/**
 * @file
 * Tagify States behaviors.
 */
(function (Drupal) {

  'use strict';

  Drupal.behaviors.tagifyStatesTagifyStates = {
    attach(context, settings) {
      console.log('It works!');
    }
  };

  Drupal.behaviors.tagifyStatesCompatibility = {
    attach(context) {

      // Prevent multiple patch applications.
      if (Drupal.tagifyStatesCompatibilityApplied) {
        return;
      }

      Drupal.tagifyStatesCompatibilityApplied = true;

      const originalCompare = Drupal.states.Dependent.prototype.compare;

      Drupal.states.Dependent.prototype.compare = function (
        reference,
        selector,
        state,
      ) {

        const value = this.values?.[selector]?.[state.name];

        // Handle Tagify JSON payloads.
        if (
          typeof reference === 'string' &&
          typeof value === 'string'
        ) {
          try {
            const parsed = JSON.parse(value);

            if (
              Array.isArray(parsed) &&
              parsed.length &&
              typeof parsed[0] === 'object' &&
              Object.prototype.hasOwnProperty.call(
                parsed[0],
                'value'
              )
            ) {

              return parsed.some(
                (tag) =>
                  String(tag.value) === String(reference)
              );
            }
          } catch (e) {
            // Not JSON, continue to core behaviour.
          }
        }

        return originalCompare.call(
          this,
          reference,
          selector,
          state
        );
      };
    }
  };

}(Drupal));

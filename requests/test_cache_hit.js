client.test("Cache hit", function() {
  client.assert(response.headers.valueOf("X-Drupal-Cache") === "HIT");
});

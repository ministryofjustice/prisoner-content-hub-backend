client.test("Cache hit", function() {
  client.assert(response.headers.valueOf("X-Drupal-Cache") === "HIT");
});

client.test("200 response", function() {
  client.assert(response.status === 200);
});

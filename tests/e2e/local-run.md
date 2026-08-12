# Playwright Local Run Guide

This guide explains how to build and run the backend app and WireMock, then execute Playwright tests locally.

## Prerequisites

- Docker Desktop is installed and running
- Node.js and npm are installed
- You are in the repository root:
  /**/prisoner-content-hub-backend

## 1. Build and start the app

Run from the repo root:

docker compose up -d --build

What this does:
- Builds the Drupal app image
- Starts core services in the background (database, drupal, redis, localstack, opensearch, chrome)

## 2. Ensure WireMock is running

If you already have a shared local WireMock container (like I did), start it with:

docker start wiremock

If WireMock is not created yet, create and run it once:

docker run -d --name wiremock -p 8089:8080 wiremock/wiremock

## 3. Verify containers are up

Check app services:

docker compose ps

Optional: check WireMock directly:

docker ps --filter name=wiremock

## 4. Install Playwright dependencies (first time only)

npm install
npx playwright install chromium

## 5. Run Playwright tests

Run the default E2E suite:

npm run test:e2e

Optional stable run (single worker):

npm run test:e2e:all

## 6. View test report

npx playwright show-report

## 7. Useful troubleshooting

If tests fail with service "drupal" is not running:
- Start services again: docker compose up -d
- Confirm status: docker compose ps

If tests fail with net::ERR_CONNECTION_REFUSED on localhost:11001:
- Drupal is not reachable yet
- Wait 20 to 60 seconds and retry
- Re-check container status with docker compose ps

If auth tests are flaky due to timeout:
- Re-run once; these tests can pass on retry
- Use single-worker mode: npm run test:e2e:all

## Quick command sequence

Use this exact order from repo root:

1. docker compose up -d --build
2. docker start wiremock
3. npm run test:e2e

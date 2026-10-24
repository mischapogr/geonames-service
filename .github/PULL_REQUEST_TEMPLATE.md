## What this changes

<!-- One paragraph. Why, not just what. -->

Refs: #

## Verification

<!-- State what you actually ran, and paste the outcome. -->

- [ ] PHP syntax (`php -l`)
- [ ] `shellcheck -x -S warning` on changed scripts
- [ ] End-to-end suite (`vendor/bin/phpunit`) against a stack with `cities15000` imported
- [ ] Browser cache tests (`npm run test:browser`), if caching or headers changed

**Not verified:** <!-- Be explicit, for example the full allCountries dataset,
alternate names, or a shared-hosting deployment. -->

## Compatibility

- [ ] Requests without the new behaviour's parameters return byte-identical responses
- [ ] New migrations are numbered, no shipped migration is edited, and a re-run is harmless
- [ ] README and AGENTS.md updated for behaviour changes
- [ ] Commit messages follow Conventional Commits

<!-- If any box is unchecked, explain why here. -->

## Breaking changes

<!-- Response fields, query parameters, schema, environment variables, script
     arguments. Write "none" if there are none. -->

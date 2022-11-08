# [2.0.0](https://github.com/idetik/coretik/compare/v1.0.0...v2.0.0) (2022-11-08)


### Features

* App initialization autorun ([35e0455](https://github.com/idetik/coretik/commit/35e0455b0449591ffbeadf489bc80eb62063df1f))


### BREAKING CHANGES

* App autorun init() where run() called. Some WP Hooks will be trigger to regsiter common config. Be carefull to not call twice in your theme or plugin, or disabled them with wp filters if needed.

# 1.0.0 (2022-11-07)

# [1.1.0](https://github.com/idetik/coretik/compare/v1.0.1...v1.1.0) (2022-11-17)


### Bug Fixes

* Add App settings in DI ([1743f3c](https://github.com/idetik/coretik/commit/1743f3ce41edc0afdeb37785a13870e7d1e6ad33))
* Add NoticeInfo ([af6e1da](https://github.com/idetik/coretik/commit/af6e1da884f72a7681349ef478e65f26f861fec9))
* Add notices service in app autorun ([e7eee33](https://github.com/idetik/coretik/commit/e7eee339b170d7136412938a5d8f51bf3aee4259))
* Add wp action hook on app::init ([49506b0](https://github.com/idetik/coretik/commit/49506b02ef0a815b74239477c681c0bd3c5c6f0a))
* Add wp hook action coretik/container/construct ([a095279](https://github.com/idetik/coretik/commit/a095279605e8ef4360bb872400629b26301d65f4))
* Fix return value from Model handler AcfProtectFieldsHandler ([ffe5eac](https://github.com/idetik/coretik/commit/ffe5eac3d04577275d66c0da46f99f712dcf440f))
* Listen notices container in app init ([de3bc3b](https://github.com/idetik/coretik/commit/de3bc3bf7cbbb513f8468eddaf323eca9cb558da))
* Notices container, CLI initialization ([c5bde15](https://github.com/idetik/coretik/commit/c5bde15290b7f04120f84c5d94d7fc6e81f8e253))
* notices service ([4c532d4](https://github.com/idetik/coretik/commit/4c532d4a51b835f17c0300f383d01baf0bcccf09))
* Service notices: add warning type ([6c82111](https://github.com/idetik/coretik/commit/6c8211121cf3fc9d9e2cee2843f0f9ba471a8b59))
* Set WP_CLI notices observer ([494a7f7](https://github.com/idetik/coretik/commit/494a7f7cf5d3f8e8bcee01f8c87160892e0d3dca))


### Features

* move to Illuminate/collection ([55c240e](https://github.com/idetik/coretik/commit/55c240e0dc5151bd84a5fb44e80ea53347165784))

# [1.0.1](https://github.com/idetik/coretik/compare/v1.0.0...v2.0.0) (2022-11-08)


### Features

* App initialization autorun ([35e0455](https://github.com/idetik/coretik/commit/35e0455b0449591ffbeadf489bc80eb62063df1f))
* App autorun init() where run() called. Some WP Hooks will be trigger to regsiter common config. Be carefull to not call twice in your theme or plugin, or disabled them with wp filters if needed.

# 1.0.0 (2022-11-07)

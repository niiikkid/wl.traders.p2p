# ТЗ: генератор тем daisyUI 5 для Laravel/Inertia/Vue проекта

Дата анализа: 2026-07-02. Цель: реализовать в боевом монолите функциональный аналог daisyUI Theme Generator, встроенный как открывающееся окно/панель на половину страницы, без копирования Svelte-бандла daisyUI и без превращения проекта в отдельную SPA.

## 1. Источники и выводы по оригиналу daisyUI

Изучены:

- [daisyUI Theme Generator](https://daisyui.com/theme-generator/)
- [daisyUI Themes docs](https://daisyui.com/docs/themes/)
- [daisyUI Config docs](https://daisyui.com/docs/config/)
- [daisyUI Theme Controller docs](https://daisyui.com/components/theme-controller/)
- JS-чанк страницы генератора `https://daisyui.com/_app/immutable/nodes/205.DHYKkHUh.js` на дату анализа.

Ключевые факты:

- В daisyUI v5 темы управляются через CSS-переменные и `data-theme`.
- Для статического подключения тема экспортируется как:

```css
@plugin "daisyui/theme" {
  name: "mytheme";
  default: true;
  prefersdark: false;
  color-scheme: "light";
  --color-base-100: oklch(...);
  /* ... */
}
```

- Для runtime-применения можно генерировать обычный CSS на `[data-theme="mytheme"]` с теми же переменными. Это важно для боевого проекта: не нужно пересобирать Vite/Tailwind при каждом изменении темы.
- Генератор daisyUI хранит пользовательские темы в `localStorage`, ключ `gen-themes-0.2`; для вашего проекта это должно быть заменено на БД + кеш, а `localStorage` оставить только для черновика и выбранной темы.
- Share URL daisyUI: `#theme=...`, где данные темы идут как `JSON.stringify -> deflate -> base64url`.
- Оригинальный UI: список тем, кнопка удержания для добавления random-темы, редактор имени/цветов/radius/effects/sizes/options, live-preview компонентов, modal с CSS-экспортом.

## 2. Область фичи

Реализовать модуль `Theme Generator` для админской/настроечной части продукта:

- Открывается поверх текущей Inertia-страницы как правая панель.
- На desktop: ширина `50vw`, минимум `760px`, максимум `1120px`; затемнение остальной страницы.
- На экранах меньше `1024px`: полноэкранный режим с нижними/верхними tabs `Themes / Editor / Preview`.
- Изменения мгновенно применяются к preview-области.
- Сохранение/публикация темы идет через Laravel backend.
- Экспорт совместим с daisyUI v5.
- Поддерживаются built-in темы daisyUI и пользовательские темы.

Не делаем:

- Не вставляем Svelte-код daisyUI в проект.
- Не пересобираем Tailwind/Vite из браузера.
- Не разрешаем произвольный CSS от пользователя.

## 3. Роли и права

Использовать Spatie Permission + Laravel Policies.

Роли:

- `super-admin`: управление системными темами, публикация default/prefersdark для всей платформы.
- `merchant-admin`: темы своего merchant scope.
- `trader-admin` или аналогичная роль, если у трейдеров есть отдельный кабинет.
- `support/viewer`: просмотр и экспорт без публикации.

Permissions:

- `theme.view`
- `theme.create`
- `theme.update`
- `theme.delete`
- `theme.publish`
- `theme.export`
- `theme.manage-system`

Правила:

- Только одна опубликованная тема в scope может иметь `is_default = true`.
- Только одна опубликованная тема в scope может иметь `is_prefers_dark = true`.
- Built-in темы можно дублировать и кастомизировать, но не удалять как системную запись.

## 4. Данные темы

Обязательная структура темы:

```ts
type DaisyTheme = {
  id: string
  scope_type: 'system' | 'merchant' | 'trader' | 'user'
  scope_id: string | null
  type: 'builtin' | 'custom' | 'builtin_override'
  name: string
  slug: string
  color_scheme: 'light' | 'dark'
  is_default: boolean
  is_prefers_dark: boolean
  status: 'draft' | 'published' | 'archived'
  tokens: DaisyThemeTokens
  version: number
  checksum: string
}
```

Токены daisyUI:

```ts
type DaisyThemeTokens = {
  '--color-base-100': string
  '--color-base-200': string
  '--color-base-300': string
  '--color-base-content': string
  '--color-primary': string
  '--color-primary-content': string
  '--color-secondary': string
  '--color-secondary-content': string
  '--color-accent': string
  '--color-accent-content': string
  '--color-neutral': string
  '--color-neutral-content': string
  '--color-info': string
  '--color-info-content': string
  '--color-success': string
  '--color-success-content': string
  '--color-warning': string
  '--color-warning-content': string
  '--color-error': string
  '--color-error-content': string
  '--radius-selector': string
  '--radius-field': string
  '--radius-box': string
  '--size-selector': string
  '--size-field': string
  '--border': string
  '--depth': '0' | '1'
  '--noise': '0' | '1'
}
```

Порядок цветов в UI и экспорте должен совпадать с daisyUI:

`base-100`, `base-200`, `base-300`, `base-content`, `primary`, `primary-content`, `secondary`, `secondary-content`, `accent`, `accent-content`, `neutral`, `neutral-content`, `info`, `info-content`, `success`, `success-content`, `warning`, `warning-content`, `error`, `error-content`.

## 5. Валидация

Backend является источником истины.

Имя темы:

- Только lowercase.
- Начинается с `a-z`.
- Заканчивается на `a-z0-9`.
- Разрешены `a-z`, `0-9`, пробел, дефис.
- Длина 3-20 символов.
- Рекомендованный slug: `Str::slug($name)`.

Значения:

- `color_scheme`: только `light` или `dark`.
- Colors: разрешить только безопасные CSS colors:
  - `oklch(...)`
  - `hsl(...)`
  - `rgb(...)`
  - `#rgb`
  - `#rrggbb`
  - ограниченный whitelist CSS named colors, если нужен импорт built-in.
- Запрещены `url(`, `var(`, `calc(`, `;`, `{`, `}`, `<`, `>`.
- Radius UI set: `0rem`, `0.25rem`, `0.5rem`, `1rem`, `2rem`.
- Допустимый backend superset: `0`, `0rem`, `0.125rem`, `0.25rem`, `0.5rem`, `0.75rem`, `1rem`, `2rem`.
- Sizes: `0.1875rem`, `0.21875rem`, `0.25rem`, `0.28125rem`, `0.3125rem`.
- Border: `0.5px`, `1px`, `1.5px`, `2px`.
- `--depth`, `--noise`: `0` или `1`.

Контраст:

- На frontend считать WCAG contrast для каждой пары:
  - `base-*` vs `base-content`
  - `primary` vs `primary-content`
  - `secondary` vs `secondary-content`
  - `accent` vs `accent-content`
  - `neutral` vs `neutral-content`
  - `info/success/warning/error` vs corresponding content.
- Показывать badge: `Low`, `AA`, `AAA`.
- Не блокировать сохранение при низком контрасте, но блокировать публикацию, если любая критическая пара ниже 3.0, кроме случаев override permission `theme.publish-low-contrast`.

## 6. UI/UX спецификация

### 6.1 Панель

Компонент: `ThemeGeneratorDrawer.vue`.

Поведение:

- Открывается кнопкой в настройках интерфейса или профиля.
- Закрытие по `Esc`, overlay click, кнопке close.
- Перед закрытием с dirty state: confirm `Сохранить черновик?`.
- Внутри не должно быть маркетингового текста. Только рабочий интерфейс.

Desktop layout:

- Header: название активной темы, статус `draft/published`, actions `Save`, `Publish`, `Export`, `Close`.
- Body:
  - слева `ThemeListPanel`, ширина около `220px`;
  - центр `ThemeEditorPanel`, ширина `320-360px`;
  - справа `ThemePreviewPanel`, остальное пространство.

Half-page fallback:

- Если фактическая ширина панели меньше `920px`, показывать tabs:
  - `Themes`
  - `Editor`
  - `Preview`

### 6.2 Список тем

Компонент: `ThemeListPanel.vue`.

Секции:

- Options dropdown:
  - Remove my themes
  - Reset built-in overrides
- Hold to add theme:
  - удержание 3 секунды;
  - по завершении создается custom draft с random tokens;
  - на `mouseup`, `mouseleave`, `blur`, `touchend` отменять.
- My themes:
  - custom draft/published.
- daisyUI themes:
  - 35 built-in тем daisyUI v5.6.x: `light`, `dark`, `cupcake`, `bumblebee`, `emerald`, `corporate`, `synthwave`, `retro`, `cyberpunk`, `valentine`, `halloween`, `garden`, `forest`, `aqua`, `lofi`, `pastel`, `fantasy`, `wireframe`, `black`, `luxury`, `dracula`, `cmyk`, `autumn`, `business`, `acid`, `lemonade`, `night`, `coffee`, `winter`, `dim`, `nord`, `sunset`, `caramellatte`, `abyss`, `silk`.

Theme item:

- swatches: `primary`, `secondary`, `accent`, `neutral`.
- name.
- badges: `default`, `dark`, `draft`, `published`.

### 6.3 Редактор

Компонент: `ThemeEditorPanel.vue`.

Controls:

- Name input.
- Random button: генерирует новую палитру для активной темы.
- CSS button: открывает `ThemeCssExportModal`.

Sections:

- Change Colors:
  - groups: `base`, `primary`, `secondary`, `accent`, `neutral`, `info`, `success`, `warning`, `error`.
  - `base` занимает 4 swatches: `base-100`, `base-200`, `base-300`, `base-content`.
  - остальные группы: color + content.
- Radius:
  - Boxes: card, modal, alert -> `--radius-box`.
  - Fields: button, input, select, tab -> `--radius-field`.
  - Selectors: checkbox, toggle, badge -> `--radius-selector`.
- Effects:
  - Depth Effect -> `--depth`.
  - Noise Effect -> `--noise`.
- Sizes:
  - Fields base size -> `--size-field`.
  - Selectors base size -> `--size-selector`.
  - Border Width -> `--border`.
- Options:
  - Default theme.
  - Default dark theme.
  - Dark color scheme.
  - Reset theme или Remove theme.

### 6.4 Color picker

Компонент: `ThemeColorPicker.vue`.

Поведение:

- Открывается по swatch.
- Поддерживает режимы:
  - Palette: Tailwind v4 palette в OKLCH.
  - OKLCH input.
  - HSL input.
  - RGB/HEX input.
- Показывает:
  - текущую пару contrast.
  - короткий alias токена: `b1`, `b2`, `b3`, `bc`, `p`, `pc`, `s`, `sc`, `a`, `ac`, `n`, `nc`, `in`, `inc`, `su`, `suc`, `wa`, `wac`, `er`, `erc`.
- При выборе цвета сразу обновляет preview.

### 6.5 Preview

Компонент: `ThemePreviewPanel.vue`.

Требование: preview должен показывать реальные daisyUI компоненты и доменные состояния P2P-платформы.

Блоки:

- Buttons: primary, secondary, accent, neutral, ghost, disabled.
- Form: input, select, textarea, checkbox, toggle, radio, file input.
- Statuses: `info`, `success`, `warning`, `error` alerts.
- Table: заявки/ордера с badges `pending`, `paid`, `dispute`, `completed`.
- Money/USDT card: balance, frozen, available.
- Modal/card preview.
- Tabs/menu/dropdown.
- Toast/notification preview.
- Chat/message bubble, если используется в поддержке.

Изоляция:

- Preview обернуть в контейнер:

```html
<section data-theme="preview-theme" class="theme-preview-root">
  ...
</section>
```

- Live CSS-переменные инжектить на этот контейнер через `style` или `<style id="theme-preview-style">`.
- Не менять тему всего приложения до явного `Apply`.

### 6.6 CSS export modal

Компонент: `ThemeCssExportModal.vue`.

Должен генерировать 2 формата:

1. daisyUI plugin format:

```css
@plugin "daisyui/theme" {
  name: "merchant-dark";
  default: false;
  prefersdark: true;
  color-scheme: "dark";
  --color-base-100: oklch(...);
}
```

2. Runtime CSS format:

```css
[data-theme="merchant-dark"] {
  color-scheme: dark;
  --color-base-100: oklch(...);
}
```

Actions:

- Copy.
- Download `.css`.
- Import from CSS/hash.

## 7. Random theme algorithm

Нужно реализовать совместимо по духу с daisyUI:

- 50/50 выбирать `light` или `dark`.
- Для base-палитры выбирать семейство Tailwind:
  - чаще neutral: `slate`, `gray`, `zinc`, `neutral`, `stone`;
  - иногда chromatic: `red`, `orange`, `amber`, `yellow`, `lime`, `green`, `emerald`, `teal`, `cyan`, `sky`, `blue`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`, `rose`.
- Light base:
  - `base-100`: shade 50
  - `base-200`: shade 100
  - `base-300`: shade 200
  - `base-content`: shade 900
- Dark base:
  - `base-100`: shade 950
  - `base-200`: shade 900
  - `base-300`: shade 800
  - `base-content`: shade 100
- `neutral`: base family shades `600-950`; `neutral-content`: opposite readable shade.
- Semantic colors:
  - info: `cyan/sky/blue`
  - success: `lime/green/emerald/teal`
  - warning: `yellow/amber/orange`
  - error: `red/pink/rose`
  - shade randomly from `400/500/600`.
- `primary`, `secondary`, `accent` from weighted Tailwind families; content color is opposite shade.
- Radius: random from `0rem`, `0.25rem`, `0.5rem`, `1rem`, `2rem`.
- Border: mostly `1px`, иногда `1.5px` или `2px`.
- Sizes: default `0.25rem`.
- Depth/noise: `0` или `1`.

Frontend library recommendation:

- Use `culori` for OKLCH/HSL/RGB parsing/conversion and contrast helpers, or implement a small local color utility if dependency budget is strict.
- Use `pako` for share hash compatibility with daisyUI style.

## 8. URL import/export

Share hash format:

```txt
#theme=<base64url(deflate(JSON.stringify(themeWithoutId)))>
```

Import flow:

- On drawer open, check `window.location.hash`.
- If hash starts with `#theme=`, decode.
- Validate on frontend.
- Send to backend `POST /api/theme-generator/import` for server validation.
- Create draft theme if valid.
- Replace URL hash after successful import or leave until user closes drawer.

Для совместимости можно поддержать query import:

```txt
?name=foo&color-scheme=light&--color-primary=oklch(...)
```

Но основной формат - hash.

## 9. Backend архитектура

Слои в стиле проекта:

- Thin controllers.
- `services()` для команд.
- `queries()` для чтения.
- API Resources для Inertia/API.

Классы:

- `App\Http\Controllers\Settings\ThemeGeneratorController`
- `App\Http\Controllers\Api\ThemeController`
- `App\Services\Themes\ThemeService`
- `App\Services\Themes\ThemeCssRenderer`
- `App\Services\Themes\ThemeValidator`
- `App\Services\Themes\ThemeImporter`
- `App\Services\Themes\ThemeRandomizer` или только frontend randomizer + backend validate.
- `App\Queries\Themes\ThemeQuery`
- `App\Http\Resources\ThemeResource`
- `App\Policies\ThemePolicy`

Роуты:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/settings/appearance', [ThemeGeneratorController::class, 'index'])
        ->name('settings.appearance');

    Route::get('/themes.css', [ThemeCssController::class, 'published'])
        ->name('themes.css');
});

Route::middleware(['auth:sanctum'])->prefix('api')->group(function () {
    Route::get('/themes', [ThemeController::class, 'index']);
    Route::post('/themes', [ThemeController::class, 'store']);
    Route::get('/themes/{theme}', [ThemeController::class, 'show']);
    Route::patch('/themes/{theme}', [ThemeController::class, 'update']);
    Route::delete('/themes/{theme}', [ThemeController::class, 'destroy']);
    Route::post('/themes/{theme}/duplicate', [ThemeController::class, 'duplicate']);
    Route::post('/themes/{theme}/publish', [ThemeController::class, 'publish']);
    Route::post('/themes/import', [ThemeImportController::class, 'store']);
    Route::get('/themes/{theme}/export.css', [ThemeExportController::class, 'show']);
});
```

Idempotency:

- Для `store`, `duplicate`, `publish`, `import` принимать `Idempotency-Key`.
- На повтор возвращать тот же результат.

## 10. База данных

Таблица `ui_themes`:

- `id` uuid primary.
- `scope_type` string indexed.
- `scope_id` uuid nullable indexed.
- `type` string.
- `name` string.
- `slug` string.
- `color_scheme` string.
- `is_default` boolean.
- `is_prefers_dark` boolean.
- `status` string.
- `tokens` json.
- `version` unsigned integer default 1.
- `checksum` string.
- `created_by` foreign id nullable.
- `published_at` timestamp nullable.
- timestamps, soft deletes.

Indexes:

- unique active slug per scope: `(scope_type, scope_id, slug, deleted_at)`.
- `(scope_type, scope_id, status)`.
- `(scope_type, scope_id, is_default)`.
- `(scope_type, scope_id, is_prefers_dark)`.

Опционально `ui_theme_versions`:

- `theme_id`
- `version`
- `tokens`
- `css`
- `created_by`
- `created_at`

## 11. CSS delivery в production

В `resources/css/app.css` оставить базовый daisyUI:

```css
@import "tailwindcss";
@plugin "daisyui" {
  themes: light --default, dark --prefersdark;
}
```

Динамические опубликованные темы отдавать отдельно:

```html
<link rel="stylesheet" href="{{ route('themes.css') }}?v={{ $themeChecksum }}">
```

Renderer должен генерировать:

```css
[data-theme="merchant-dark"] {
  color-scheme: dark;
  --color-base-100: oklch(...);
  --radius-box: 1rem;
}
```

Для theme-controller совместимости:

```css
:root:has(input.theme-controller[value="merchant-dark"]:checked),
[data-theme="merchant-dark"] {
  color-scheme: dark;
  /* variables */
}
```

Кеш:

- Redis key: `themes:css:{scopeType}:{scopeId}:{checksum}`.
- HTTP headers: `ETag`, `Cache-Control: private, max-age=300`.
- После publish/delete сбрасывать кеш scope.

## 12. Frontend архитектура

Файлы:

- `resources/js/Features/Themes/components/ThemeGeneratorDrawer.vue`
- `ThemeListPanel.vue`
- `ThemeEditorPanel.vue`
- `ThemeColorPicker.vue`
- `ThemePreviewPanel.vue`
- `ThemeCssExportModal.vue`
- `ThemeTokenControl.vue`
- `ThemeSizeControl.vue`
- `ThemeRadiusControl.vue`
- `resources/js/Features/Themes/stores/themeGenerator.ts`
- `resources/js/Features/Themes/lib/theme-schema.ts`
- `theme-css-renderer.ts`
- `theme-share-codec.ts`
- `theme-randomizer.ts`
- `theme-contrast.ts`

Pinia state:

```ts
type ThemeGeneratorState = {
  isOpen: boolean
  activeTab: 'themes' | 'editor' | 'preview'
  builtinThemes: DaisyTheme[]
  customThemes: DaisyTheme[]
  activeThemeId: string | null
  draft: DaisyTheme | null
  dirty: boolean
  cssModalOpen: boolean
  validationErrors: Record<string, string>
}
```

Store actions:

- `open()`
- `close()`
- `loadThemes()`
- `selectTheme(id)`
- `createRandomTheme()`
- `duplicateTheme(id)`
- `updateToken(key, value)`
- `saveDraft()`
- `publish()`
- `removeTheme()`
- `resetBuiltinOverride()`
- `exportPluginCss()`
- `exportRuntimeCss()`
- `importHash(hash)`

Live preview:

- Use computed `previewStyle` object.
- Debounce expensive contrast calculations to `100ms`.
- Do not send API requests on every slider/color change.

## 13. API responses

`ThemeResource`:

```json
{
  "id": "uuid",
  "scopeType": "merchant",
  "scopeId": "uuid",
  "type": "custom",
  "name": "merchant dark",
  "slug": "merchant-dark",
  "colorScheme": "dark",
  "isDefault": false,
  "isPrefersDark": true,
  "status": "draft",
  "tokens": {},
  "version": 3,
  "checksum": "sha256...",
  "can": {
    "update": true,
    "delete": true,
    "publish": true,
    "export": true
  }
}
```

Validation error format должен быть совместим с Laravel/Inertia.

## 14. Observability

Events:

- `ThemeCreated`
- `ThemeUpdated`
- `ThemePublished`
- `ThemeDeleted`
- `ThemeImportFailed`

Sentry:

- capture backend validation exceptions only as warning, not fatal.
- capture frontend decode/import errors with hash length, not full token payload.

Pulse metrics:

- count themes created.
- count publish events.
- theme CSS route latency.
- validation failure rate.

Telescope:

- useful in non-production for import/export debugging.

## 15. Security

Главная опасность - CSS injection через пользовательские токены.

Правила:

- Backend не доверяет frontend.
- Backend renderer выводит только известные token keys.
- Любой неизвестный key игнорируется или вызывает validation error.
- Значения проходят strict allowlist.
- `name` всегда slugify для selectors.
- CSS export escaped.
- Для API включить rate limit: например `30/min` на save/import.
- Audit log для publish/delete.

## 16. Тестирование

PHPUnit/Pest:

- `ThemeValidatorTest`: все allowed/disallowed значения.
- `ThemeCssRendererTest`: snapshot plugin CSS и runtime CSS.
- `ThemeImportTest`: валидный hash импортируется, битый hash отклоняется.
- `ThemePolicyTest`: роли и scope.
- `ThemePublishTest`: только одна default/prefersdark тема в scope.
- `ThemeCssControllerTest`: ETag/cache headers.

Frontend unit:

- `theme-share-codec.spec.ts`: encode/decode roundtrip.
- `theme-randomizer.spec.ts`: все сгенерированные темы проходят schema validation.
- `theme-css-renderer.spec.ts`: порядок токенов стабилен.
- `theme-contrast.spec.ts`: badges Low/AA/AAA.

Browser/E2E:

- Открытие drawer.
- Выбор built-in темы.
- Создание random темы удержанием.
- Изменение primary color сразу меняет preview.
- Save создает draft.
- Publish применяет тему на странице.
- Export CSS копируется.
- Mobile tabs работают.
- Нельзя опубликовать тему с unsafe CSS value.

## 17. Acceptance criteria

Фича считается готовой, если:

- Панель открывается поверх боевого интерфейса и не ломает текущую Inertia-страницу.
- Есть список built-in и custom тем.
- Можно создать random тему удержанием кнопки.
- Можно редактировать все daisyUI v5 theme tokens.
- Preview обновляется мгновенно без запроса на backend.
- Можно сохранить draft.
- Можно опубликовать тему в нужный scope.
- Опубликованная тема применяется через `data-theme` без Vite rebuild.
- Экспорт `@plugin "daisyui/theme"` валиден для app.css.
- Runtime CSS валиден для динамической загрузки.
- Import hash работает.
- Backend блокирует небезопасные CSS values.
- Есть тесты на renderer, validator, policies, import/export.

## 18. Рекомендуемый план реализации

Этап 1: Foundation

- Создать schema, migration, model, policy.
- Добавить `ThemeValidator`, `ThemeCssRenderer`.
- Засидить built-in темы daisyUI v5.6.x в JSON/config.

Этап 2: Frontend drawer

- Собрать drawer/tabs layout.
- Реализовать editor controls и live preview.
- Реализовать randomizer и local draft state.

Этап 3: Persistence

- CRUD API.
- Save draft.
- Publish.
- Dynamic `/themes.css`.
- Cache invalidation.

Этап 4: Import/export

- `pako` codec.
- CSS export modal.
- Import from hash/CSS.

Этап 5: Hardening

- Policies, idempotency, rate limit.
- E2E tests.
- Sentry/Pulse events.

## 19. Особые рекомендации для P2P-платформы

- В preview обязательно показать реальные финансовые состояния: USDT balance, frozen funds, order status, dispute, success payout, failed payout.
- Для trader/merchant кабинетов лучше поддерживать scope hierarchy:
  - user selected theme overrides merchant theme;
  - merchant published theme overrides system default;
  - system default fallback.
- Не давать merchant/trader менять системные built-in темы напрямую; только duplicate -> custom.
- Тему применять на layout root:

```vue
<main :data-theme="currentThemeSlug">
  <slot />
</main>
```

- Выбранную тему пользователя хранить отдельно от theme definitions, например `user_preferences.theme_slug`.


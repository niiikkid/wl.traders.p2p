# Merchant Traffic Categories Requirements

> Source: User conversation in Cursor
> Collected: 2026-05-22
> Published: Unknown

Business feature: allow traders to choose which categories of merchant traffic they want to receive.

The initial business example is merchant categorization by the percentage a trader receives from traffic:

- High-percent merchants: trader receives 4%, traffic volume is lower. Suggested label: "More percent, less traffic".
- Lower-percent merchants: trader receives 3.8%, traffic volume is higher. Suggested label: "Less percent, more traffic".

The percentage values are examples only. The category itself does not calculate commission and does not change payout math. It only filters access to traffic.

Admin requirements:

- On the admin merchants page, add a compact button in the upper-right area named "Categories".
- Clicking it opens a modal where an administrator can create, edit, and delete categories.
- Category fields:
  - Name.
  - Required description. The trader sees this description in a tooltip when hovering over the category.
  - Default enabled flag. When enabled, this category is enabled for existing traders on category creation and for future traders by default.
- The category UI must be compact, minimal, clear, and include plain-language explanations for fields.
- The category modal must include a global system toggle for the traffic category logic.
- The global toggle is off by default.
- When the global toggle is off:
  - Trader-facing category UI is hidden completely.
  - Category filtering does not affect traffic distribution.
  - The system behaves as if categories do not exist.
  - Existing category settings and trader choices are preserved.
  - Admin can still create, edit, delete, and assign categories.
  - Admin should clearly see that categories are currently disabled.
- Use the project SettingService for the global toggle.
- On the admin merchants table, add a "Categories" column showing compact badges for assigned categories.
- In the existing merchant settings dropdown, add an action named "Category" or "Categories".
- The action opens a modal where admin can assign one or more categories to a merchant.
- If a merchant has no categories, it is available to all traders without category filtering. This rule must be shown compactly in the admin UI.

Trader requirements:

- On the trader order/deal table page, show a compact full-width row above the table when the global feature is enabled.
- The row displays all categories as clear toggle buttons or badges.
- It must be visually obvious which categories are enabled and which are disabled.
- The row must explain in simple words what the buttons do. Example meaning: "Enable the categories you want to work with. If you turn a category off, orders from those merchants will not come to you."
- Category descriptions are shown in delayed tooltips on hover.
- It should be visually clear that extra information is available on hover.
- If the global feature is disabled, the trader sees nothing related to categories.

Filtering requirements:

- When a new order/deal is created from a merchant:
  - If the global feature is disabled, ignore categories entirely.
  - If the merchant has no categories, the merchant's traffic can go to all traders.
  - If the merchant has one or more categories, payment details are searched only among traders who have at least one of those categories enabled.
- If a trader disables all categories, they still can receive traffic from merchants without categories.
- If a merchant has multiple categories, one matching enabled trader category is enough.
- If category configuration breaks unexpectedly, traffic should not silently become unrestricted. It is better that traffic does not go and an error is logged.

Default behavior requirements:

- A category can be marked as enabled by default.
- On creation, if enabled by default is true, it should be enabled for all existing traders.
- New traders should receive categories enabled according to the category default flag.
- Editing the default flag affects only future traders by default.
- Admin must be clearly informed that changing the default flag does not change existing traders.
- Add a separate explicit action "Apply to all traders".
- "Apply to all traders" behavior:
  - If the category default flag is enabled, enable this category for all traders.
  - If the category default flag is disabled, disable this category for all traders.
- Audit logging is not required.

Deletion requirements:

- Deleting a category removes merchant assignments and trader settings for that category.
- If a merchant loses its last category, it becomes available to all traders because it has no categories.

UX language:

- Use very simple explanatory wording, as if explaining to a fourteen-year-old.
- Admins should always understand what a field or action does before clicking.
- Traders should clearly understand why they enable or disable a category.

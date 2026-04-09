# Smart Programme Finder

## Technical Architecture

Document version: 1.0  
Status: Technical planning baseline  
Audience: Engineers, solution architects, technical leads  
Date: April 7, 2026

Companion documents:

- [Smart-Programme-Finder-Product-Documentation.md](Smart-Programme-Finder-Product-Documentation.md)
- [Smart-Programme-Finder-Client-Overview.md](Smart-Programme-Finder-Client-Overview.md)
- [Smart-Programme-Finder-Investor-Brief.md](Smart-Programme-Finder-Investor-Brief.md)

---

## 1. Technical Objective

Design a production-ready WordPress plugin architecture for Smart Programme Finder that supports:

- No-code form authoring.
- Deterministic rule-based recommendations.
- AJAX-powered public submission.
- Shared rendering across shortcode and Elementor.
- Secure handling of public and admin input.
- Forward compatibility for advanced rule logic and premium features.

---

## 2. Architecture Principles

- Use WordPress-native patterns for hooks, capabilities, nonces, enqueueing, and localization.
- Keep domain logic independent from admin UI implementation.
- Treat forms, fields, and rules as structured domain entities rather than ad hoc option blobs.
- Separate persistence, evaluation, rendering, and integration concerns.
- Optimize for multi-form scale from the start.
- Design the MVP so future AND or OR rules and scoring can be added without changing core persistence concepts.
- Fail safely on invalid configuration, invalid payloads, and missing dependencies.

---

## 3. Recommended Plugin Topology

```text
smart-programme-finder/
  smart-programme-finder.php
  readme.txt
  uninstall.php
  languages/
  assets/
    admin/
      css/
      js/
      images/
    frontend/
      css/
      js/
  includes/
    Plugin.php
    Installer.php
    Upgrade_Manager.php
    Autoloader.php
    Support/
      Capabilities.php
      Nonce_Service.php
      Validation_Service.php
      Sanitization_Service.php
      Cache_Service.php
      Logger.php
    Domain/
      Entities/
        Form.php
        Field.php
        Rule.php
        Result.php
      Repositories/
        Form_Repository.php
        Field_Repository.php
        Rule_Repository.php
    Admin/
      Menu.php
      Dashboard_Page.php
      Forms_List_Page.php
      Form_Editor_Page.php
      Rule_Editor_Page.php
      Assets.php
    Frontend/
      Renderer.php
      Shortcode_Controller.php
      Ajax_Controller.php
      Assets.php
      Modal_Controller.php
    Engine/
      Rule_Engine.php
      Condition_Evaluator.php
      Result_Resolver.php
      Rule_Sorter.php
    Integrations/
      Elementor/
        Widget.php
        Service_Provider.php
  templates/
    frontend-form.php
    result-modal.php
    admin-form-preview.php
  tests/
    Unit/
    Integration/
    E2E/
  docs/
```

---

## 4. Core Component Model

### 4.1 Bootstrap Layer

Responsibilities:

- Register hooks for activation, deactivation, uninstall, admin init, frontend init, and integrations.
- Load service classes and wire dependencies.
- Register plugin version and schema version.

Key requirement:

- Initialization must be deterministic and should not depend on Elementor or any optional plugin being active.

### 4.2 Admin Module

Responsibilities:

- Register admin menus and pages.
- Load forms list and editor screens.
- Manage field and rule authoring flows.
- Surface preview, publish, duplicate, delete, and embed guidance.

Implementation guidance:

- Use WordPress-native screen structure.
- Keep the MVP builder panel-based rather than highly abstract drag-and-drop.
- Validate configuration both client-side and server-side.

### 4.3 Domain Layer

Responsibilities:

- Represent forms, fields, rules, and results with stable internal schemas.
- Enforce domain validation before persistence or evaluation.
- Expose repository methods used by admin and frontend controllers.

Design goal:

- Domain objects and normalized arrays should be portable enough to support future REST endpoints or SaaS migration.

### 4.4 Rule Engine

Responsibilities:

- Load active rules for a form.
- Sort them deterministically.
- Evaluate conditions.
- Resolve fallback behavior.
- Return a normalized frontend-safe result contract.

The rule engine must not know about shortcode, Elementor, or admin presentation.

### 4.5 Frontend Module

Responsibilities:

- Render public forms.
- Bind client-side validation and AJAX interactions.
- Manage modal lifecycle.
- Handle multiple form instances per page.

### 4.6 Integration Layer

Responsibilities:

- Register Elementor widget when available.
- Reuse shared rendering and asset contracts.
- Prevent optional dependencies from leaking into core execution paths.

---

## 5. Data Storage Strategy

### 5.1 Recommended Storage Approach

Use a hybrid model:

- WordPress Options API for plugin-wide settings and schema versioning.
- Custom database tables for forms, fields, and rules.

### 5.2 Why Custom Tables

Forms, fields, and rules are:

- Structured and relational.
- Order-sensitive.
- Frequently edited.
- Likely to grow over time.
- Better suited to indexed querying than nested post meta.

Custom tables provide cleaner persistence, better query control, easier duplication logic, and a more stable foundation for premium analytics later.

### 5.3 JSON Storage Recommendation

For maximum WordPress hosting compatibility, structured payloads such as field options, conditions, settings, and results should be stored as serialized JSON strings in `LONGTEXT` columns rather than depending on native MySQL JSON column support.

This keeps the schema broadly portable across common WordPress environments while preserving internal structure.

---

## 6. Proposed Database Model

### 6.1 Table: `wp_spf_forms`

Purpose:

- Master record for each recommendation form.

Recommended columns:

- `id` BIGINT unsigned primary key.
- `name` VARCHAR.
- `slug` VARCHAR unique within plugin context.
- `description` TEXT nullable.
- `status` VARCHAR for draft or published states.
- `settings_json` LONGTEXT nullable.
- `fallback_result_json` LONGTEXT nullable.
- `created_at` DATETIME.
- `updated_at` DATETIME.

Recommended indexes:

- Primary key on `id`.
- Index on `status`.
- Unique or constrained index on `slug` if product rules require it.

### 6.2 Table: `wp_spf_fields`

Purpose:

- Store field definitions for a form.

Recommended columns:

- `id` BIGINT unsigned primary key.
- `form_id` BIGINT unsigned foreign-key-like relation.
- `field_key` VARCHAR stable machine key.
- `label` VARCHAR.
- `field_type` VARCHAR.
- `help_text` TEXT nullable.
- `is_required` TINYINT.
- `sort_order` INT.
- `options_json` LONGTEXT nullable.
- `settings_json` LONGTEXT nullable.
- `created_at` DATETIME.
- `updated_at` DATETIME.

Recommended indexes:

- Index on `form_id`.
- Composite index on `form_id, sort_order`.
- Unique constraint candidate on `form_id, field_key`.

### 6.3 Table: `wp_spf_rules`

Purpose:

- Store rule definitions and result payloads per form.

Recommended columns:

- `id` BIGINT unsigned primary key.
- `form_id` BIGINT unsigned relation.
- `name` VARCHAR.
- `status` VARCHAR.
- `priority` INT.
- `match_strategy` VARCHAR.
- `conditions_json` LONGTEXT.
- `result_json` LONGTEXT.
- `created_at` DATETIME.
- `updated_at` DATETIME.

Recommended indexes:

- Index on `form_id`.
- Composite index on `form_id, status, priority`.

### 6.4 Options API Scope

Use options for:

- Plugin version.
- Schema version.
- Global feature flags.
- Global UI defaults.
- Future license or premium configuration.

Do not use options for per-form authoring data.

---

## 7. Domain Schemas

### 7.1 Form Schema

Minimum normalized form contract:

```json
{
  "id": 3,
  "name": "Course Finder",
  "slug": "course-finder",
  "status": "published",
  "description": "Help visitors choose a programme",
  "fields": [],
  "fallback_result": {},
  "settings": {}
}
```

### 7.2 Field Schema

```json
{
  "id": 14,
  "field_key": "interest_area",
  "field_type": "select",
  "label": "What are you interested in?",
  "help_text": "Choose the area that best matches your goal.",
  "required": true,
  "sort_order": 10,
  "options": [
    { "label": "Business", "value": "business" },
    { "label": "Technology", "value": "technology" }
  ],
  "settings": {}
}
```

### 7.3 Rule Schema

```json
{
  "id": 21,
  "name": "Recommend Business Management",
  "status": "active",
  "priority": 10,
  "match_strategy": "first_match",
  "conditions": [
    {
      "field_key": "interest_area",
      "operator": "equals",
      "value": "business"
    }
  ],
  "result": {
    "headline": "Recommended Programme: Business Management",
    "message": "Based on your answer, this programme is the best fit.",
    "cta_label": "View Programme",
    "cta_url": "/programmes/business-management"
  }
}
```

### 7.4 Fallback Result Schema

```json
{
  "headline": "Let us help you choose",
  "message": "We could not find an exact match. Contact our admissions team.",
  "cta_label": "Contact Us",
  "cta_url": "/contact"
}
```

---

## 8. Request and Rendering Flows

### 8.1 Admin Save Flow

1. Administrator edits form or rule configuration.
2. Client-side admin validation highlights obvious issues.
3. Save request submits with nonce and capability context.
4. Server sanitizes and validates payload.
5. Repositories persist normalized records.
6. Cache for the affected form is invalidated.
7. Updated editor state is returned or reloaded.

### 8.2 Frontend Submission Flow

1. Frontend renderer outputs form markup, nonce, and instance ID.
2. Visitor completes required inputs.
3. Client-side script validates required fields and disables repeat submission.
4. Browser posts AJAX request with form ID, nonce, and normalized values.
5. AJAX controller validates request and loads form definition from cache or repository.
6. Rule engine evaluates active rules in deterministic order.
7. Result resolver returns matched result or fallback result.
8. JSON response is returned to the browser.
9. Modal controller renders result content and manages reset or close behavior.

### 8.3 Shortcode Rendering Flow

1. Content parser encounters `[spf_form id="X"]`.
2. Shortcode controller validates the ID.
3. Published form definition is loaded.
4. Shared renderer outputs form markup and enqueues assets.

### 8.4 Elementor Rendering Flow

1. Elementor widget loads available published forms.
2. Editor selects a form.
3. Widget delegates render to the same shared renderer used by shortcode.
4. Frontend behavior remains identical to shortcode output.

---

## 9. Rule Engine Design

### 9.1 MVP Evaluation Model

The MVP uses a first-match-wins engine.

Processing order:

1. Load active rules for the current form.
2. Sort by ascending priority.
3. Evaluate each rule condition.
4. Return the first matching rule result.
5. If no rule matches, return the fallback result.

### 9.2 MVP Operators

- `equals`
- `not_equals`
- `in`

Only `equals` needs to be exposed in the MVP UI. The engine may support additional operators internally if kept stable and tested.

### 9.3 Determinism Requirement

The engine must produce the same output for the same saved configuration and input payload. If two rules share the same priority, a secondary tie-break rule such as ascending rule ID must be defined and documented.

### 9.4 Forward Compatibility

The condition schema should support future evolution toward:

- `AND` and `OR` condition groups.
- Nested groups.
- Weighted scoring.
- Top-N recommendation output.

The persistence model should not assume only one condition forever, even if the MVP UI exposes a single-condition workflow.

---

## 10. Frontend Rendering Model

### 10.1 Rendering Strategy

Use a shared renderer service that receives:

- Form definition.
- Instance configuration.
- Rendering context such as shortcode or Elementor.

The renderer is responsible for:

- Outputting field markup.
- Emitting unique instance identifiers.
- Providing nonce values.
- Rendering the modal container.
- Enqueueing frontend assets once.

### 10.2 Instance Isolation

Multiple forms may appear on one page. The frontend implementation must isolate:

- DOM identifiers.
- Modal instances.
- Validation state.
- Request lifecycle state.

Global singletons that assume only one form per page should be avoided.

### 10.3 Accessibility Baseline

- Semantic labels for inputs.
- Focusable and keyboard-operable controls.
- Visible focus states.
- Error messages associated with fields.
- Modal focus trap and Escape close.
- Focus restoration after modal close.

---

## 11. Security Architecture

### 11.1 Threat Surfaces

- Admin configuration screens.
- Public AJAX submission endpoint.
- Stored result content rendered in the frontend.
- Embed flows through shortcode and Elementor.

### 11.2 Security Controls

Admin side:

- Capability checks on every CRUD path.
- Nonces on save, duplicate, delete, and publish actions.
- Sanitization before persistence.

Frontend side:

- Nonce validation on AJAX submission.
- Validation of field keys and allowed values.
- Safe error responses.
- Optional rate limiting for abuse control.

Output side:

- Escaping by HTML, attribute, and URL context.
- No raw rule definitions exposed to visitors.

Persistence side:

- Prepared statements only.
- No raw answer logging by default.
- No public access to unpublished forms.

### 11.3 Logging and Privacy

- MVP should minimize stored user data.
- Debug logging should be opt-in.
- Any logs should avoid raw answers or personally identifying data unless a later feature explicitly requires retention.

---

## 12. Performance and Reliability

### 12.1 Caching

Recommended cache targets:

- Published form definitions.
- Field collections by form.
- Active rules by form.

Cache invalidation triggers:

- Form save.
- Field save.
- Rule save.
- Publish or unpublish.
- Duplicate or delete.

### 12.2 Query Efficiency

- Use indexed reads for form, status, and rule priority lookups.
- Load full form definition in predictable batches rather than repeated per-field queries.
- Avoid loading assets globally across the site.

### 12.3 Failure Behavior

- No-match returns fallback result, not a technical error.
- Invalid payload returns a structured safe response.
- Slow request returns a recoverable UI state.
- Missing Elementor does not affect core shortcode rendering.

---

## 13. Testing Strategy

### 13.1 Unit Tests

Target:

- Rule sorting.
- Rule evaluation.
- Result resolution.
- Validation and sanitization helpers.

### 13.2 Integration Tests

Target:

- Repository persistence.
- Migration behavior.
- AJAX controller request validation.
- Publish state enforcement.

### 13.3 End-to-End Tests

Target:

- Admin creates form.
- Admin adds rules and publishes form.
- Visitor submits form and receives result.
- Modal opens and resets correctly.
- Shortcode and Elementor render parity smoke checks.

---

## 14. Upgrade and Extensibility Plan

### 14.1 Upgrade Safety

- Separate schema version from plugin version.
- Keep migrations repeatable and additive where possible.
- Avoid breaking changes to stored JSON contracts without explicit migration logic.

### 14.2 Extensibility Hooks

Future extension points should support:

- Additional field types.
- Additional rule operators.
- Alternative result templates.
- Event hooks after recommendation resolution.
- Premium analytics and lead capture modules.

### 14.3 SaaS Readiness

If the product evolves into a hosted service, the most portable assets will be:

- Normalized form schema.
- Rule schema.
- Result contract.
- Engine evaluation logic.

That is why domain logic should remain decoupled from shortcode and admin-specific view concerns.

---

## 15. Technical Decisions to Confirm Early

- Final field-type list for MVP.
- Capability model for MVP and post-MVP.
- Whether fallback result is mandatory before publish.
- Uninstall data retention policy.
- Exact rate-limiting mechanism for public AJAX.
- Preferred JS stack for the admin builder and frontend behavior.

---

## 16. Summary

The recommended architecture gives Smart Programme Finder a stable WordPress-native foundation while preserving room for product expansion. It balances immediate delivery needs with longer-term commercial goals by isolating the core domain model, using custom tables for structured data, and enforcing a single rendering and evaluation contract across all user-facing channels.
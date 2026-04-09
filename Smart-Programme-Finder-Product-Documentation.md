# Smart Programme Finder

## Implementation Specification

Document version: 1.1  
Status: Delivery planning ready  
Audience: Product, engineering, QA, delivery  
Date: April 7, 2026

Companion documents:

- [Smart-Programme-Finder-Client-Overview.md](Smart-Programme-Finder-Client-Overview.md)
- [Smart-Programme-Finder-Investor-Brief.md](Smart-Programme-Finder-Investor-Brief.md)
- [Smart-Programme-Finder-Technical-Architecture.md](Smart-Programme-Finder-Technical-Architecture.md)

---

## 1. Purpose

This document translates the Smart Programme Finder product plan into an implementation-ready specification for engineering and delivery teams.

It defines:

- MVP scope and constraints.
- Release milestones.
- Delivery epics.
- Ticket-level work items.
- Acceptance criteria.
- Cross-cutting quality gates.
- Dependencies and open decisions.

The target outcome is a production-ready WordPress plugin MVP that enables administrators to build recommendation forms, define single-condition rules, return AJAX-based results, and embed those forms by shortcode or Elementor widget.

---

## 2. Delivery Objective

Deliver a WordPress plugin that allows a non-technical administrator to:

1. Create a form in the WordPress admin area.
2. Add structured fields with no coding.
3. Create rules that map answers to recommendations.
4. Publish the form in under five minutes.
5. Embed it by shortcode or Elementor widget.
6. Return a recommendation in a modal without page reload.

Release success is achieved when:

- At least one published form can be created end to end on a clean WordPress installation.
- Public visitors can submit a form and receive a deterministic recommendation.
- No-match, invalid input, and missing form scenarios fail safely.
- The plugin works with and without Elementor installed.

---

## 3. MVP Scope

### 3.1 In Scope

- Plugin bootstrap, activation, and database installer.
- Custom tables for forms, fields, and rules.
- Admin list and editor views for forms.
- MVP field types: select, radio, checkbox group, instructional content block.
- Rule builder with one condition per rule.
- Fallback result per form.
- AJAX form submission using WordPress AJAX.
- Modal result display.
- Shortcode rendering using `[spf_form id="X"]`.
- Elementor widget with form selector.
- Core security hardening, validation, and test coverage.

### 3.2 Out of Scope

- Multi-step conversational flows.
- Saved leads or applicant profiles.
- CRM and email integrations.
- Recommendation scoring engine.
- Conditional field visibility on the frontend.
- Visual theme builder.
- Detailed analytics dashboards.
- Public REST API for third-party integrations.

### 3.3 Delivery Constraints

- Recommended platform baseline: WordPress 6.5+.
- Recommended runtime baseline: PHP 8.1+.
- Elementor integration must be optional, not required.
- The MVP must not depend on external SaaS services.
- User responses must not be stored by default.

---

## 4. Working Assumptions

- Administrators are beginner to intermediate WordPress users.
- Recommendation logic remains deterministic and rule-based in the MVP.
- Frontend requests must support unauthenticated public traffic.
- Database schema should support multiple forms from day one.
- The product will likely expand into premium analytics, lead capture, and integrations later.

---

## 5. Delivery Milestones

### Milestone 1: Platform and Persistence

Outcome:

- The plugin installs cleanly, activates cleanly, and stores forms, fields, and rules in custom tables.

### Milestone 2: Admin Authoring Experience

Outcome:

- An administrator can create, edit, preview, publish, duplicate, and delete forms and rules.

### Milestone 3: Frontend Recommendation Flow

Outcome:

- Visitors can submit a form via AJAX and receive a recommendation in a modal.

### Milestone 4: Embedding and Release Hardening

Outcome:

- Shortcode and Elementor rendering both work, the system is hardened, and release packaging is complete.

---

## 6. Cross-Cutting Acceptance Criteria

These criteria apply to the full MVP regardless of epic ownership.

- The plugin activates, deactivates, and uninstalls without fatal errors.
- All admin write actions are protected by nonces and capability checks.
- All public form submissions are validated server-side even if client validation is bypassed.
- Frontend and admin output is escaped by context.
- Asset loading is conditional and does not add site-wide overhead when the plugin is unused.
- Core user journeys function on current major desktop and mobile browsers.
- Primary form and modal interactions are keyboard accessible.
- Public failures return user-safe messages and never expose stack traces or raw SQL errors.
- Schema changes are versioned and repeatable.
- The plugin is structured to support future multiple-condition rules without rewriting the persistence layer.

---

## 7. Epic Overview

| Epic ID | Epic Name | Primary Outcome | MVP Phase |
| --- | --- | --- | --- |
| SPF-E1 | Foundation and Persistence | Stable plugin bootstrap and data layer | M1 |
| SPF-E2 | Admin Form Authoring | Working no-code builder for forms and fields | M2 |
| SPF-E3 | Rule Authoring and Evaluation | Deterministic recommendation logic | M2 |
| SPF-E4 | Frontend Submission Experience | AJAX recommendation flow and modal results | M3 |
| SPF-E5 | Embedding and Elementor | Shared rendering via shortcode and widget | M4 |
| SPF-E6 | Security, QA, and Release | Production hardening and launch readiness | M4 |

---

## 8. Epic Specifications

### SPF-E1: Foundation and Persistence

Objective:

- Establish the plugin skeleton, storage model, repositories, migration path, and capability baseline required by all later work.

Dependencies:

- None.

#### Ticket SPF-001: Plugin Bootstrap and Lifecycle

Description:

- Create the plugin entry point, plugin loader, autoloading strategy, activation hook, deactivation hook, and initialization flow.

Acceptance criteria:

- The plugin can be activated on a clean WordPress installation without fatal errors.
- The plugin can be deactivated without deleting data.
- The main plugin file registers version and path constants used by assets and migrations.
- Core services load once per request and do not initialize duplicate instances.
- If activation prerequisites are not met, the failure is surfaced as a safe admin message.

#### Ticket SPF-002: Database Schema and Migration Layer

Description:

- Create installation and upgrade routines for forms, fields, and rules tables and store the schema version in plugin settings.

Acceptance criteria:

- Activation creates tables for forms, fields, and rules if they do not exist.
- Schema migrations are idempotent and can run repeatedly without corrupting data.
- The plugin stores a schema version separately from the plugin version.
- Indices exist for the expected hot paths, including form relationships and rule priority lookups.
- A version upgrade path is documented and testable from an older schema revision.

#### Ticket SPF-003: Repository and Validation Services

Description:

- Implement domain repositories and validation services for forms, fields, and rules.

Acceptance criteria:

- Repository methods exist for create, read, update, delete, duplicate, and list operations.
- All write operations validate required fields before persistence.
- Database access uses prepared statements and consistent return shapes.
- Repository read methods can hydrate a full form definition including fields and rules.
- Invalid records are rejected with structured errors rather than silent failure.

#### Ticket SPF-004: Capability Model and Admin Shell

Description:

- Register the admin menu structure, basic screens, and capability gatekeeping.

Acceptance criteria:

- Only users with the configured capability can access Smart Programme Finder admin screens.
- A top-level admin menu and required child screens are registered.
- Unauthorized direct access to admin actions is blocked.
- Plugin admin assets load only on Smart Programme Finder screens.
- The admin shell exposes placeholders for Dashboard, Forms, Embed, and Settings or equivalent MVP sections.

### SPF-E2: Admin Form Authoring

Objective:

- Deliver a beginner-friendly form builder that supports the MVP field types, preview flow, and publish workflow.

Dependencies:

- SPF-E1 complete.

#### Ticket SPF-101: Forms List View and Empty State

Description:

- Build the forms index page with creation entry point, status visibility, and empty-state guidance.

Acceptance criteria:

- Administrators can view all existing forms in a list screen.
- The list screen shows form name, status, last updated date, and identifier needed for embedding.
- An empty-state screen explains the product value and how to create a first form.
- The list screen includes actions for edit, preview, duplicate, and delete.
- Pagination or scalable list handling is in place for multi-form growth.

#### Ticket SPF-102: Form Metadata Editor

Description:

- Build the form editor for title, description, status, slug, and basic settings.

Acceptance criteria:

- An administrator can create and save a draft form.
- A form requires a title before save succeeds.
- Slug generation is deterministic and editable.
- Save draft and publish actions are clearly separated.
- Validation errors are presented inline and in plain language.

#### Ticket SPF-103: Field Builder and Ordering

Description:

- Build the UI and persistence flow for adding and ordering fields.

Acceptance criteria:

- An administrator can add select, radio, checkbox group, and instructional content fields.
- Choice-based fields support label and value pairs.
- Each field supports label, helper text, required flag, and display order where applicable.
- Field order can be changed and persists across saves.
- Invalid field configurations, including empty option values on selectable fields, are blocked before save.

#### Ticket SPF-104: Admin Preview and Publish Workflow

Description:

- Provide preview and publish behavior so administrators can verify a form before embedding it.

Acceptance criteria:

- An administrator can preview the current form configuration in an admin preview state.
- Preview output reflects field order, labels, helper text, and CTA behavior.
- Published forms expose a usable ID and shortcode snippet.
- Unpublished forms are not publicly rendered by shortcode or Elementor.
- Publish state changes are persisted and visible in the list view.

#### Ticket SPF-105: Duplicate and Delete Lifecycle

Description:

- Implement duplication and deletion flows for forms and their related data.

Acceptance criteria:

- Duplicating a form creates a new draft with copied metadata, fields, rules, and fallback result.
- Deleting a form removes or archives associated fields and rules according to the chosen storage policy.
- Destructive actions require confirmation and nonce validation.
- Duplicate and delete operations return the administrator to a predictable post-action screen.
- Duplicate names and slugs are normalized safely.

### SPF-E3: Rule Authoring and Evaluation

Objective:

- Deliver the core recommendation engine and the authoring interface needed to create and prioritize rules.

Dependencies:

- SPF-E1 complete.
- SPF-E2 form and field persistence available.

#### Ticket SPF-201: Rule Schema and Fallback Result Model

Description:

- Define the persisted rule structure and fallback result structure for each form.

Acceptance criteria:

- A rule stores status, priority, one supported condition, and a structured result payload.
- A result payload supports headline, message, CTA label, and CTA URL.
- Each form supports one fallback result configuration.
- A rule cannot reference a missing field.
- Choice-based rule values are validated against the allowed options for that field.

#### Ticket SPF-202: Rule Builder Interface

Description:

- Build the admin UI for creating, editing, ordering, and disabling rules.

Acceptance criteria:

- An administrator can create a rule from the form editor context.
- The UI exposes field selector, operator selector, value selector, priority, and result fields.
- Inactive rules can be disabled without being deleted.
- The UI prevents save of incomplete rules.
- The fallback result is configurable in the same workflow or an adjacent clearly labeled panel.

#### Ticket SPF-203: Evaluation Engine

Description:

- Implement the server-side rule engine for first-match-wins evaluation.

Acceptance criteria:

- The engine loads only active rules for the selected form.
- The engine evaluates rules in deterministic priority order.
- The `equals` operator is supported for MVP and returns the first valid match.
- If multiple rules are otherwise equal, tie-breaking is deterministic and documented.
- The engine returns a normalized result contract independent of admin UI state.

#### Ticket SPF-204: No-Match and Conflict Handling

Description:

- Define behavior for no-match outcomes and predictable handling of overlapping rules.

Acceptance criteria:

- If no rule matches, the fallback result is returned when configured.
- If a fallback result is missing, the system returns a safe generic recommendation message.
- Conflicting rules do not produce nondeterministic output.
- Priority order is visible to administrators and reflected in saved data.
- Error cases do not expose internal rule definitions to the frontend.

### SPF-E4: Frontend Submission Experience

Objective:

- Deliver a lightweight frontend experience that validates input, submits by AJAX, and shows modal results without reloading the page.

Dependencies:

- SPF-E2 and SPF-E3 complete.

#### Ticket SPF-301: Shared Frontend Renderer and Shortcode Output

Description:

- Build the primary frontend rendering pipeline and expose it through shortcode.

Acceptance criteria:

- The shortcode `[spf_form id="X"]` renders a published form by ID.
- Each rendered form instance has an isolated DOM identifier and request nonce.
- Frontend assets are loaded only when a form is rendered.
- Missing or unpublished forms fail gracefully for public users.
- Logged-in administrators receive a contextual explanation when render fails in admin-like contexts.

#### Ticket SPF-302: Client-Side Validation and Submission UX

Description:

- Build client-side behavior for required fields, submission states, and retry handling.

Acceptance criteria:

- Required fields are validated before the request is sent.
- Validation messages are displayed adjacent to the relevant field.
- The submit button shows a loading state and is disabled while a request is in flight.
- Failed submissions re-enable the form without requiring page refresh.
- The experience works on mobile and desktop viewport sizes.

#### Ticket SPF-303: Public AJAX Endpoint and Response Contract

Description:

- Implement the AJAX controller, payload validation, rule invocation, and JSON response format.

Acceptance criteria:

- Both authenticated and unauthenticated visitors can submit published forms.
- The endpoint validates nonce, form existence, publish state, field keys, and required values.
- The response includes success status, headline, message, CTA data, fallback flag, and validation errors when relevant.
- Invalid or malformed payloads return a safe structured error response.
- The controller never exposes raw SQL, PHP warnings, or rule internals.

#### Ticket SPF-304: Result Modal and Reset Behavior

Description:

- Implement the result modal and the reset flow after successful submission.

Acceptance criteria:

- Successful submission opens a modal containing the resolved recommendation.
- The modal supports keyboard focus management and Escape-to-close.
- Closing or resetting the interaction clears field state and validation errors.
- CTA label and destination are driven by the matched rule or fallback result.
- The user can re-run the form without a page reload.

#### Ticket SPF-305: Multi-Instance Form Isolation

Description:

- Ensure multiple forms on the same page behave independently.

Acceptance criteria:

- Two or more form instances can render on the same page without ID collisions.
- Submitting one form does not modify the state of another form instance.
- Modal targeting is scoped to the originating form instance.
- Event listeners are not double-bound when multiple instances exist.
- Shortcode and Elementor-rendered instances can coexist on the same page.

### SPF-E5: Embedding and Elementor

Objective:

- Support both builder and non-builder placement methods without maintaining two rendering systems.

Dependencies:

- SPF-E4 shared frontend rendering complete.

#### Ticket SPF-401: Unified Rendering Service

Description:

- Formalize the shared renderer used by shortcode and Elementor.

Acceptance criteria:

- Shortcode and Elementor output call the same render service and template layer.
- Presentation differences are controlled by instance settings, not duplicated logic.
- Rendering changes can be tested once and trusted across both embedding methods.
- Shared renderer supports multiple form instances per page.

#### Ticket SPF-402: Elementor Widget Registration and Controls

Description:

- Register an Elementor widget that allows selection of a published form.

Acceptance criteria:

- The widget registers only when Elementor is active.
- Widget controls expose a form selector populated by published forms.
- Selecting a form renders the same output contract used by shortcode.
- Sites without Elementor continue to function normally.
- Elementor-specific failures do not break the base plugin.

#### Ticket SPF-403: Embed Guidance and Admin Handover

Description:

- Provide embed instructions and clear handoff paths for administrators and content teams.

Acceptance criteria:

- Published forms surface a copyable shortcode in the admin UI.
- The admin experience clearly distinguishes shortcode embedding from Elementor embedding.
- Missing render targets are explained in plain language.
- Embed instructions do not require technical documentation outside the plugin UI for MVP usage.

### SPF-E6: Security, QA, and Release

Objective:

- Harden the plugin for production use, validate core flows, and prepare a release candidate.

Dependencies:

- All prior epics substantially complete.

#### Ticket SPF-501: Sanitization, Escaping, and URL Safety

Description:

- Apply sanitization and escaping standards across admin persistence and frontend output.

Acceptance criteria:

- Text, textarea, URL, boolean, and numeric inputs are sanitized according to expected type.
- Stored values are escaped by context on output.
- CTA destinations are validated as safe URLs before render.
- Attempts to inject HTML or scripts through labels, messages, or CTA text are neutralized.
- Sanitization and escaping behavior is covered by automated tests where practical.

#### Ticket SPF-502: Nonces, Capability Checks, and Abuse Protection

Description:

- Finalize the request protection model for admin and public interactions.

Acceptance criteria:

- All admin save, duplicate, and delete actions require valid nonces.
- Public AJAX submissions require a valid frontend nonce.
- CRUD actions enforce the configured capability consistently.
- Repeated invalid public submissions can be throttled using a lightweight rate-limit strategy.
- Invalid requests return generic safe responses without exposing implementation details.

#### Ticket SPF-503: Automated Test Coverage

Description:

- Add automated coverage for domain logic, persistence, and the main user journey.

Acceptance criteria:

- Unit tests cover rule evaluation and result resolution behavior.
- Integration tests cover repository operations and AJAX request handling.
- End-to-end smoke coverage exists for form creation, publish, embed, submit, and result display.
- Test fixtures or factories are available for forms, fields, and rules.
- Test execution instructions are documented for local development.

#### Ticket SPF-504: Error Handling and Safe Failure UX

Description:

- Standardize error handling, internal logging, and user-safe fallback messages.

Acceptance criteria:

- Missing form, invalid submission, and no-match scenarios return predictable UX states.
- Public users never see stack traces, SQL warnings, or raw PHP errors.
- Internal logging is opt-in and avoids storing raw answers by default.
- Slow or failed requests restore the form to a usable state.
- Elementor absence or failure does not break shortcode rendering.

#### Ticket SPF-505: Release Packaging and UAT

Description:

- Prepare the MVP release candidate, documentation, and manual QA checklist.

Acceptance criteria:

- Installation, upgrade, and uninstall behavior is documented.
- A release checklist exists for clean install, upgrade test, shortcode render, Elementor render, and AJAX submission.
- The plugin package includes readme, versioning, and changelog preparation.
- The MVP is validated on a clean WordPress environment before sign-off.
- Outstanding known issues are documented explicitly before release.

---

## 9. Suggested Delivery Sequence

Recommended build order:

1. SPF-E1 Foundation and Persistence.
2. SPF-E2 Admin Form Authoring.
3. SPF-E3 Rule Authoring and Evaluation.
4. SPF-E4 Frontend Submission Experience.
5. SPF-E5 Embedding and Elementor.
6. SPF-E6 Security, QA, and Release.

Parallelization guidance:

- After SPF-002, repository and admin shell work can proceed in parallel.
- After SPF-103 and SPF-201, rule UI and evaluation engine can run concurrently.
- After SPF-301, Elementor integration can start once shared rendering contracts are stable.
- Security and test coverage should begin before the final milestone rather than being deferred entirely to the end.

---

## 10. Definition of Done

A ticket is considered complete when:

- Acceptance criteria are met.
- Relevant automated tests are added or updated.
- Manual QA steps for the ticket are documented.
- No known high-severity regressions are introduced.
- Documentation and user-facing help text affected by the change are updated.
- The implementation follows the agreed architecture and naming conventions.

An epic is considered complete when:

- All in-scope tickets are complete.
- Dependencies for downstream epics are stable.
- Cross-cutting acceptance criteria remain satisfied.
- Product and QA sign-off is recorded.

---

## 11. Open Decisions Requiring Product Approval

These items should be locked before development starts or early in Milestone 1.

- Final MVP field type list and whether checkboxes are single-select or multi-select in the first release.
- Whether the MVP capability model remains `manage_options` only or introduces custom capabilities immediately.
- Whether admin preview should be live and reactive or save-based for MVP.
- Whether the fallback result is mandatory at publish time.
- Whether form duplication should clone all rules by default or offer a choice.
- Whether uninstall removes all plugin data or preserves it unless explicitly opted out.
- Whether rate limiting is required for MVP launch or release-candidate hardening.

---

## 12. Post-MVP Backlog Seeds

These are not part of MVP delivery but should shape architectural choices now.

- Multiple-condition rules with AND and OR logic.
- Weighted scoring and ranked recommendations.
- Lead capture and submission storage.
- CRM, email, and webhook integrations.
- Recommendation analytics dashboard.
- Template library for common education use cases.
- Styling presets and UI customization controls.
- Hosted or SaaS orchestration layer.

---

## 13. Release Readiness Summary

The implementation should not move to production release until the following are true:

- MVP core flow works end to end on a clean install.
- Rule evaluation is deterministic and tested.
- Public AJAX requests are nonce-protected and server-validated.
- Shortcode and Elementor output use the same rendering contract.
- No-match, invalid form, and malformed payload scenarios fail safely.
- Release notes, support notes, and UAT evidence are complete.

At that point, Smart Programme Finder is ready to move from planning into execution with a clear delivery backlog and an auditable acceptance standard.
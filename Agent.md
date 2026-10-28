# BLITTO PATENT DATABASE — Agent Architecture Contract

> Machine-readable companion to `Rules.md`. The bindings below are the real
> Laravel namespaces; the production and test adapters are the ones that exist
> in `backend/` today.

project:
  name: "BLITTO PATENT DATABASE"
  domain: "patent-management"
  stack: "Laravel 13 / PHP 8.3 / Blade + Tailwind / SQLite + Eloquent / Pest 4"
  entry_point: "backend/"
  vocabulary_file: "CONTEXT.md"
  decision_records: "docs/adr/"
  roadmap: "BIGGER_PICTURE.md"

modules:
  - name: "ApplicationIntake"
    namespace: "App\\Domain\\ApplicationIntake"
    seam_category: "in-process"
    priority: 3

  - name: "Vault"
    namespace: "App\\Domain\\Vault"
    seam_category: "ports-and-adapters"
    priority: 1

  - name: "Lifecycle"
    namespace: "App\\Domain\\Lifecycle"
    seam_category: "in-process"
    priority: 2

  - name: "Docketing"
    namespace: "App\\Domain\\Docketing"
    seam_category: "in-process"
    priority: 4

  - name: "Prosecution"
    namespace: "App\\Domain\\Prosecution"
    seam_category: "in-process"
    priority: 5
    depends_on: ["Vault", "Docketing"]

  - name: "Filing"
    namespace: "App\\Domain\\Filing"
    seam_category: "in-process"
    priority: 6
    depends_on: ["Lifecycle", "Vault"]

  - name: "Authorization"
    namespace: "App\\Domain\\Authorization"
    seam_category: "local-substitutable"
    priority: 4

  - name: "Notification"
    namespace: "App\\Domain\\Notification"
    seam_category: "true-external"
    priority: 7

  - name: "Audit"
    namespace: "App\\Domain\\Audit"
    seam_category: "in-process"
    priority: 8

  - name: "Analytics"
    namespace: "App\\Domain\\Analytics"
    seam_category: "in-process"
    priority: 9

seams:
  persistence:
    category: "local-substitutable"
    interface: "Illuminate\\Database\\Eloquent\\Model (no custom port)"
    production: "SQLite via Eloquent; models are persistence-only"
    test: "SQLite in-memory via RefreshDatabase"

  documents:
    category: "ports-and-adapters"
    interface: "App\\Domain\\Vault\\DocumentStore"
    production: "App\\Domain\\Vault\\LocalDocumentStore on the private `vault` disk (plaintext, ADR-0004)"
    test: "Storage::fake('vault')"

  mail:
    category: "true-external"
    interface: "App\\Domain\\Notification\\MailChannel"
    production: "App\\Domain\\Notification\\LaravelMailChannel (delegates to MAIL_MAILER)"
    test: "App\\Domain\\Notification\\ArrayMailChannel (BLITTODB_MAIL_CHANNEL=array)"

vocabularies:
  domain: "CONTEXT.md"
  architecture_rules: "Rules.md"
  adrs: "docs/adr/"
  ui_tokens: "DESIGN.md"

task_patterns:
  add_feature:
    - "Write a failing Pest test at the public interface (HTTP route or domain service)"
    - "Add the domain behaviour under app/Domain/<Module>"
    - "Keep the controller thin: validate, delegate, respond"
    - "Run the full suite and Pint"
  add_seam:
    - "Write the port interface with production + test adapters"
    - "Record the decision in docs/adr/"
    - "Test through the interface, never the adapter internals"

review_checklist:
  - "Deletion test passes (would deleting this module remove real complexity)?"
  - "Two adapters exist for every seam?"
  - "Tests hit public interfaces only (no private internals)?"
  - "Domain vocabulary from CONTEXT.md used?"
  - "No business logic in controllers or Eloquent models?"
  - "Suite green and Pint clean?"

priority_order:
  - "Vault"
  - "ApplicationIntake"
  - "Lifecycle"
  - "Authorization"
  - "Docketing"
  - "Prosecution"
  - "Filing"
  - "Notification"
  - "Audit"
  - "Analytics"

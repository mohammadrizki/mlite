# Web-Based Migration System (Safe)

This package replaces legacy upgrade.php with a safe, stateful,
web-based migration architecture.

Flow:
- UI triggers /migration/start
- SQL executed ONLY via /migration/run-next
- One migration version per request
- Fully transactional + logged
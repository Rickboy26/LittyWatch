# LittyWatch V5.2 — Phase 4A: AI Validation Foundation

Phase 4A adds an optional OpenAI-based quality-control layer on top of the deterministic GW1 parser. The parser remains the source of fast extraction; AI is an independent verifier and never blocks collection.

## Modes

- `off`: no AI queue.
- `risky` (default): queue only ambiguous, low-confidence, multi-item, complex-price or market-outlier offers.
- `all`: queue every structured offer.

Environment variables:

```bash
export OPENAI_API_KEY="..."
export LITTYWATCH_AI_MODE="risky"   # off | risky | all
export LITTYWATCH_AI_MODEL="gpt-5-mini"
export LITTYWATCH_AI_TIMEOUT="35"
```

Do not commit the API key to Git or place it in the public web root.

## Run

After a deploy/reparse:

```bash
php tools/maintenance/ai-validate.php --sync --limit=25
```

Subsequent batches:

```bash
php tools/maintenance/ai-validate.php --limit=25
```

To have AI inspect every stored offer:

```bash
LITTYWATCH_AI_MODE=all php tools/maintenance/ai-validate.php --sync --limit=25
```

The queue and verdicts are stored in `ai_offer_validations`. Parser Review shows AI status, risk score, verdict, confidence and reason.

## Safety model

Phase 4A is **advisory**: AI verdicts are stored but do not overwrite parser prices automatically. `correct` and `reject` are surfaced as disagreements for review. This prevents a model mistake from silently corrupting market statistics. Auto-apply is intentionally deferred to a later phase after enough real-world validation data exists.

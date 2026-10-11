# Aevov Senton Field — What This Plugin Is, and What It Deliberately Is Not

**Status:** shipped component of the Aevov Web3 plugin set.
**Licence:** Aevov Sovereign Source-Available License v1.0 (`LICENSE`), **not** the
AGPL-3.0 that covers the rest of Aevov-Web3 — see the carve-out in the repository's root
`LICENSE` and in `README.md`. The retention duty in every file header requires this file
and `LICENSE` to accompany each copy.
**Question:** how does the public architecture let someone *experience* the Senton
paradigm without shipping the Senton organism?
**Answer:** it does not ship a reduced copy of the organism. It ships a different
system — public-mathematics phase-coupled oscillators — that reproduces the
*observable behaviour* the paradigm claims, and exposes exactly the same four numbers
the real substrate would report: coherence `R`, the coherence an event produced
`Δ𝕄`, coherence reputation, and a shareable phase signature.
**Related:** `README.md` (Plugin Ecosystem), `aevov-unified-dashboard/aevov-unified-dashboard.php`,
`aevov-meshcore/includes/discovery/class-dht-service.php`, `aevov-ai-core/includes/providers/class-provider-manager.php`

---

## 1. The distinction the whole plugin rests on

| | Senton organism (not here) | Senton Field (here) |
|---|---|---|
| Substrate | trained/cognitive state, seed-based reconstruction | 64 Kuramoto phase oscillators |
| Coupling | organism-specific | all-to-all, `N²` `sin(θⱼ − θᵢ)` terms per cycle |
| State kept | full internal state | rounded phases, `tick`, `R`, `Δ𝕄`, reputation |
| Input handling | semantics | phase attractor from `sha256(text)` + a salience scalar |
| Shareable over mesh | no | yes — mean phase, `R`, reputation, signature only |
| Can be read by the public | no | yes (`GET /aevov-senton/v1/field`) |

The field is honest about being a field: it responds to **how much** a statement says
(character entropy, length, urgency markers) or to a salience score from `aevov-ai-core`
when one is configured — never to meaning. `Senton_Organism::salience_of()`.

## 2. Absent by design

No weights or trained state. No seed, fold or reconstruct path — persisted phases are
rounded to 3 decimals, which is display resolution, not reconstruction. No codec
internals or encoding parameters. No kernel, bare-metal or lattice-runtime code. No
device, cost or capacity figures. Nothing is called into the organism, and no endpoint
in this plugin can reach it if it is present on the same host.

This list is not just documentation. It is returned by `GET /aevov-senton/v1/boundary`
and rendered on the admin page, so the limit is part of what a visitor sees.

## 3. Present

- **Lattice** — `includes/class-senton-lattice.php`. Kuramoto step with synchronous
  update (all deltas computed against the pre-step phase set before any phase moves),
  which is what keeps the integration stable at the configured step size and coupling.
- **Tuning** — `includes/class-senton-tuning.php`. The model constants (step size,
  coupling, frequency spread, perception strength, peer weight, the reputation gain /
  decay / pull terms, and every salience weight) live in one table that is stored
  XOR-encoded and split across literals, and is read through `Senton_Tuning::of( $slot )`.
  Reason: a drive-by copy should not inherit a *calibrated* model, only its shape. The
  encoding is deterrence, not protection — `hex2bin` + `base64_decode` + an XOR loop is
  reversible in a minute, and the LICENSE application note states that decoding it is a
  permitted SSAL §2(a) audit activity. What actually restricts copying is SSAL §3. If the
  table cannot be recovered the field provably cannot move (zero step size, zero coupling),
  so `Senton_Tuning::recovered()` is reported in the snapshot and the admin page shows an
  error rather than looking merely idle.
- **Order parameter** — `R·e^{iψ} = (1/N)·Σ e^{iθⱼ}`. `R` is coherence, `ψ` is the phase
  the field settled on and the only phase quantity peers need.
- **Reputation** — signed in `Δ𝕄` (decoherence costs), decays each cycle, and pulls
  toward the field's present `R`. Accumulating only positive `Δ𝕄` would score a field
  that jitters symmetrically around zero as reputable; measured, such a field held
  `cr ≈ 0.016` against `cr ≈ 0.947` for a locked one under the current rule. (Those two
  are measurement outputs, not tuning inputs; the tuning inputs behind them are in the
  encoded table.)
- **Clock** — wp-cron `aevov_senton_minute`, 4 cycles per run, so the field is alive
  between requests rather than a request-scoped toy.

## 4. Integration with the core three

The spine is the pattern system — `AevovPatternSyncProtocol`, `bloom-pattern-recognition`,
`aps-tools` — because that is what the dashboard labels "Core 3 Plugins"
(`aevov-unified-dashboard/aevov-unified-dashboard.php:51`) and what the ledger is built on.

| Core plugin | Seam | If absent |
|---|---|---|
| `AevovPatternSyncProtocol` | `do_action('aps_distribute_pattern', $pattern)` with `pattern_hash` = field signature and `confidence` = coherence `R`; `aps_pattern_analysis` filter answers field-typed patterns; `APS_Comparator::find_analogous_patterns()` supplies novelty | publish is skipped and `distributed` reports `false`; novelty falls back to this site's own signature history |
| `bloom-pattern-recognition` | shares the chunk ledger (`AevovChunkRegistry\ChunkRegistry::register_chunk()`) that APS comparison and bloom both read | ledger registration is skipped — guarded on **both** `ChunkRegistry` and `EmbeddingManager`, because `register_chunk()` calls the embedding engine unconditionally and would fatal on a partial install |
| `aps-tools` | `aps_tools_pattern_analysis` and `aps_tools_pattern_comparison` filters add coherence, reputation and signature dimensions — only for `senton-field` patterns | filters simply have no listener |

Two facts worth recording, because they are the reason the seams look the way they do:

- **`aps_distribute_pattern` has no emitter anywhere in this repo.** `PatternDistributor::init_hooks()`
  registers it (`AevovPatternSyncProtocol/Includes/Network/PatternDistributor.php:38`) and the
  distributor is only constructed inside APS endpoints and the queue. The field is the first
  thing that emits into it, which is why publish checks `has_action()` rather than assuming.
- **Using APS's own 0.75 confidence threshold as the publish gate means the field does not
  invent a second quality bar.** A scattered field is not worth propagating, and the ledger
  already says so.

Optional engines, each behind `class_exists`/`method_exists` (the repo has no capability-flag
convention, so the existence check *is* the flag):

| Engine | Seam | If absent |
|---|---|---|
| `aevov-core` | `aevov_get_api_key()` / `aevov_get_rate_limiter()`; `aevov_error` bus | field runs, errors go to the WP error log |
| `aevov-ai-core` | `$GLOBALS['aevov_ai_core']->get_provider_manager()->complete(null, $params)` for salience | deterministic local salience heuristic |
| `aevov-meshcore` | `DHTService::put('senton:field:'.$node, …, 900)` / `get()` for peer signatures | local peer board option; behaviour identical from the UI |
| `aevov-memory-core` | `MemoryManager::write_to_memory()` per field event | event still logged to the ring buffer |
| `aevov-workflow-engine` | `senton` capability via `aevov_workflow_capabilities` | node simply unavailable in workflows |

Availability is surfaced as badges on the admin page rather than assumed anywhere in the code.

## 5. Testing note

`testing/workflow-test-runner.php:93` discovers sister plugins by glob on
`<dir>/<dir>.php`, so this plugin needs no manifest edit to enter the test run. It does
need a dashboard entry (`aevov-unified-dashboard/aevov-unified-dashboard.php`) to appear
in the unified dashboard, and that entry is part of this change.

## 6. Known limits, stated plainly

- **It is a simulation of coherence, not a measurement of it.** The field's `R` is real
  for the field. Nothing here demonstrates that an organism's coherence behaves this way.
- `WebSocketServer::broadcast()` in meshcore is a hook-firing placeholder and no engine in
  this repo actually calls `ProviderManager::complete()`, so the mesh and AI paths are wired
  to real APIs that are themselves not yet exercised in production. Expect the local
  fallbacks to be what runs on most installs.
- Single-node salience heuristics are crude. They are deterministic and documented, not smart.
- The lattice re-reads options on every construct; at `N = 64` the cost is trivial, at
  `N = 256` a request-scoped tick is 65k `sin()` calls. `N` is capped at 256 for that reason.

# Aevov Web4 Architecture Paper

## From Distributed AI to Decentralized Infrastructure

**Version**: 0.1 (Draft)
**Status**: Planning Document
**Target**: Web4 Native Implementation

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current Architecture (Web3)](#2-current-architecture-web3)
3. [Web4 Vision](#3-web4-vision)
4. [Native Storage Engine: AevStore](#4-native-storage-engine-aevstore)
5. [AevCoin Tokenomics](#5-aevcoin-tokenomics)
6. [Developer Incentive Program](#6-developer-incentive-program)
7. [Migration Strategy](#7-migration-strategy)
8. [Technical Specifications](#8-technical-specifications)
9. [Roadmap](#9-roadmap)

---

## 1. Executive Summary

Aevov is transitioning from a Web3 architecture (distributed AI with external storage dependencies) to a fully decentralized Web4 ecosystem with:

- **Native storage engine** replacing Cubbit DS3 dependency
- **AevCoin cryptocurrency** incentivizing network participation
- **Mesh-first networking** via AevIP protocol
- **Self-sovereign AI** running on user-controlled infrastructure

This paper outlines the architectural changes required for this transition.

---

## 2. Current Architecture (Web3)

### 2.1 Component Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     AEVOV WEB3 ARCHITECTURE                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐ │
│  │   AI Core   │  │  Meshcore   │  │   Pattern Sync (APS)    │ │
│  │  Providers  │  │    P2P      │  │   Distributed Ledger    │ │
│  └──────┬──────┘  └──────┬──────┘  └───────────┬─────────────┘ │
│         │                │                     │               │
│         └────────────────┼─────────────────────┘               │
│                          │                                     │
│                    ┌─────▼─────┐                               │
│                    │  Memory   │                               │
│                    │   Core    │                               │
│                    └─────┬─────┘                               │
│                          │                                     │
│         ┌────────────────┼────────────────┐                    │
│         │                │                │                    │
│         ▼                ▼                ▼                    │
│  ┌────────────┐  ┌────────────┐  ┌────────────────┐           │
│  │ WordPress  │  │  Chunk     │  │   EXTERNAL     │           │
│  │    DB      │  │  Registry  │  │   CUBBIT DS3   │ ◄── DEPENDENCY
│  └────────────┘  └────────────┘  └────────────────┘           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Current Storage Dependencies

| Component | Storage Backend | Limitation |
|-----------|-----------------|------------|
| Memory Core | WordPress DB + Cubbit | External dependency |
| Chunk Registry | WordPress DB | Centralized |
| Pattern Storage | Cubbit DS3 | Third-party infrastructure |
| SLAM Maps (AROS) | Cubbit DS3 | Latency for robotics |
| Model Weights | Cubbit DS3 | Bandwidth costs |

### 2.3 Current Token Implementation

```php
// aevov-vision-depth: Placeholder rewards
'avd_reward_per_scrape' => 0.001,  // AevCoin per scrape
'avd_reward_per_pattern' => 0.01,  // AevCoin per pattern

// aevov-meshcore: Generic tokens (not AevCoin)
$this->token_rate = 100.0;  // tokens per MB relayed
```

**Status**: Token accounting exists but no blockchain, no wallets, no consensus.

---

## 3. Web4 Vision

### 3.1 Core Principles

1. **Self-Sovereignty**: Users own their data, compute, and identity
2. **Mesh-Native**: No reliance on traditional ISPs or cloud providers
3. **Incentive-Aligned**: All network participation is economically rewarded
4. **AI-First**: Storage and networking optimized for AI workloads

### 3.2 Target Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     AEVOV WEB4 ARCHITECTURE                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    AEVIP MESH LAYER                      │   │
│  │  (Decentralized networking - replaces traditional ISPs)  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                  │
│         ┌────────────────────┼────────────────────┐            │
│         │                    │                    │            │
│         ▼                    ▼                    ▼            │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐      │
│  │  AevStore   │     │   AevCoin   │     │   AI Core   │      │
│  │   Native    │◄───►│  Blockchain │◄───►│  Federated  │      │
│  │   Storage   │     │   + Wallet  │     │   Learning  │      │
│  └─────────────┘     └─────────────┘     └─────────────┘      │
│         │                    │                    │            │
│         └────────────────────┼────────────────────┘            │
│                              │                                  │
│                    ┌─────────▼─────────┐                       │
│                    │  Proof-of-Work    │                       │
│                    │  Proof-of-Storage │                       │
│                    │  Proof-of-Compute │                       │
│                    └───────────────────┘                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 4. Native Storage Engine: AevStore

### 4.1 Design Goals

- **No external dependencies** (replaces Cubbit)
- **Content-addressed** (like IPFS)
- **Incentivized** (storage providers earn AevCoin)
- **AI-optimized** (tensor sharding, embedding indexes)

### 4.2 Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        AEVSTORE                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌────────────────┐  ┌────────────────┐  ┌──────────────┐  │
│  │  Content       │  │  Erasure       │  │  Replication │  │
│  │  Addressing    │  │  Coding        │  │  Manager     │  │
│  │  (CID/Hash)    │  │  (Reed-Solomon)│  │  (N copies)  │  │
│  └───────┬────────┘  └───────┬────────┘  └──────┬───────┘  │
│          │                   │                   │          │
│          └───────────────────┼───────────────────┘          │
│                              │                              │
│                    ┌─────────▼─────────┐                    │
│                    │   Shard Manager   │                    │
│                    │  (splits files    │                    │
│                    │   across nodes)   │                    │
│                    └─────────┬─────────┘                    │
│                              │                              │
│         ┌────────────────────┼────────────────────┐         │
│         │                    │                    │         │
│         ▼                    ▼                    ▼         │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐   │
│  │  Storage    │     │  Storage    │     │  Storage    │   │
│  │  Node A     │     │  Node B     │     │  Node C     │   │
│  │  (AevCoin   │     │  (AevCoin   │     │  (AevCoin   │   │
│  │   rewards)  │     │   rewards)  │     │   rewards)  │   │
│  └─────────────┘     └─────────────┘     └─────────────┘   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 4.3 Key Features

| Feature | Description |
|---------|-------------|
| **Content Addressing** | Files identified by hash (CID), not location |
| **Erasure Coding** | Reed-Solomon encoding for fault tolerance |
| **Tensor Sharding** | AI model weights split optimally across nodes |
| **Hot/Cold Tiers** | Frequently accessed data replicated more |
| **Proof-of-Storage** | Nodes must prove they store data to earn rewards |

### 4.4 API Design

```php
// AevStore Native API (replacing Cubbit)
interface AevStoreInterface {
    // Store data, returns content ID (CID)
    public function store(string $data, array $options = []): string;

    // Retrieve by CID
    public function retrieve(string $cid): string;

    // Pin data (ensure replication)
    public function pin(string $cid, int $replication_factor = 3): bool;

    // Unpin (allow garbage collection)
    public function unpin(string $cid): bool;

    // Get storage stats
    public function stats(): array;

    // AI-specific: Store tensor with optimal sharding
    public function store_tensor(array $tensor, array $metadata = []): string;

    // AI-specific: Retrieve tensor, reassemble shards
    public function retrieve_tensor(string $cid): array;
}
```

### 4.5 Storage Node Requirements

```yaml
# Minimum requirements to run AevStore node
aevstore_node:
  hardware:
    storage: 100GB minimum (SSD recommended)
    ram: 2GB minimum
    bandwidth: 10 Mbps symmetric

  software:
    php: ">=8.0"
    extensions: [sodium, gmp, curl]

  rewards:
    base_rate: "0.001 AevCoin per GB/month stored"
    retrieval_bonus: "0.0001 AevCoin per GB retrieved"
    uptime_multiplier: "1.0x - 2.0x based on availability"
```

---

## 5. AevCoin Tokenomics

### 5.1 Token Overview

| Property | Value |
|----------|-------|
| **Name** | AevCoin |
| **Symbol** | AEV |
| **Total Supply** | 1,000,000,000 AEV (fixed) |
| **Decimals** | 18 |
| **Consensus** | Proof-of-Contribution (PoC) |

### 5.2 Distribution Model

```
┌─────────────────────────────────────────────────────────────┐
│                  AEVCOIN DISTRIBUTION                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │                                                     │    │
│  │   Storage Rewards     ████████████████  40%        │    │
│  │   Compute Rewards     ████████████      30%        │    │
│  │   Bandwidth Rewards   ██████            15%        │    │
│  │   Development Fund    ████              10%        │    │
│  │   Early Adopters      ██                 5%        │    │
│  │                                                     │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 5.3 Earning Mechanisms

| Activity | Reward Rate | Notes |
|----------|-------------|-------|
| **Storage** | 0.001 AEV/GB/month | Storing data for network |
| **Retrieval** | 0.0001 AEV/GB | Serving data requests |
| **Relay** | 0.0005 AEV/GB | Bandwidth relay via meshcore |
| **Compute** | 0.01 AEV/GPU-hour | AI inference/training |
| **Pattern Discovery** | 0.01 AEV/pattern | Vision Depth scraping |
| **Consensus** | 0.1 AEV/block | Block validation |

### 5.4 Spending Mechanisms

| Service | Cost | Notes |
|---------|------|-------|
| **Storage** | 0.002 AEV/GB/month | 2x reward rate |
| **Retrieval** | 0.0002 AEV/GB | 2x reward rate |
| **Priority Routing** | 0.001 AEV/request | Faster mesh routing |
| **AI Inference** | Variable | Based on model size |
| **Premium Features** | Subscription | Dashboard, analytics |

### 5.5 Proof-of-Contribution Consensus

```
┌─────────────────────────────────────────────────────────────┐
│              PROOF-OF-CONTRIBUTION (PoC)                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Contribution Score = weighted sum of:                       │
│                                                              │
│    Storage Score     × 0.40  (GB stored × uptime)           │
│  + Compute Score     × 0.30  (GPU-hours × quality)          │
│  + Bandwidth Score   × 0.20  (GB relayed × latency)         │
│  + Reputation Score  × 0.10  (historical reliability)       │
│                                                              │
│  Block Producer Selection:                                   │
│    - Top N contributors by score                             │
│    - Weighted random selection                               │
│    - Rotation every epoch (1000 blocks)                      │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 5.6 Wallet Integration

```php
// AevCoin Wallet API
interface AevWalletInterface {
    // Generate new wallet
    public function create(): array; // Returns [address, private_key, mnemonic]

    // Import from mnemonic
    public function import(string $mnemonic): array;

    // Get balance
    public function balance(string $address): float;

    // Send transaction
    public function send(string $to, float $amount, string $private_key): string;

    // Get transaction history
    public function history(string $address, int $limit = 50): array;

    // Stake for consensus participation
    public function stake(float $amount, string $private_key): string;

    // Unstake
    public function unstake(string $stake_id, string $private_key): string;
}
```

---

## 6. Developer Incentive Program

### 6.1 Why Developers Should Build on Aevov

```
┌─────────────────────────────────────────────────────────────────┐
│                  DEVELOPER VALUE PROPOSITION                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │   Earn       │  │   Own        │  │   Build      │          │
│  │   AevCoin    │  │   Your Code  │  │   Once,      │          │
│  │   Passively  │  │   Forever    │  │   Deploy     │          │
│  │              │  │              │  │   Everywhere │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│                                                                  │
│  Unlike Web2 platforms that extract value from developers,      │
│  Aevov PAYS developers for ecosystem contributions.             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 6.2 Revenue Sharing Model

Developers earn AevCoin whenever their code is used:

| Contribution Type | Revenue Share | Example |
|-------------------|---------------|---------|
| **Plugin/Extension** | 70% of usage fees | Build an AI model plugin, earn 70% of inference fees |
| **Core Contributions** | Bounty + 5% perpetual | Fix a core bug, earn bounty + 5% of related tx fees |
| **API Integrations** | 50% of API calls | Build a third-party integration, earn per-call |
| **Templates/Patterns** | 80% of template sales | Create workflow templates, keep 80% |

### 6.3 Direct Earning Mechanisms

```
┌─────────────────────────────────────────────────────────────────┐
│                  HOW DEVELOPERS EARN AEVCOIN                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. CODE CONTRIBUTIONS                                          │
│     ├── Merged PR to core repos      →  50-500 AEV per PR       │
│     ├── Bug fixes (by severity)      →  10-1000 AEV             │
│     ├── Security vulnerabilities     →  500-10000 AEV           │
│     └── Documentation improvements   →  5-50 AEV per page       │
│                                                                  │
│  2. PLUGIN DEVELOPMENT                                          │
│     ├── Published plugin             →  100 AEV welcome bonus   │
│     ├── Per installation             →  1 AEV per install       │
│     ├── Per active user/month        →  0.1 AEV per user        │
│     └── Usage-based fees             →  70% of all fees         │
│                                                                  │
│  3. ECOSYSTEM GROWTH                                            │
│     ├── Onboard new developer        →  25 AEV referral         │
│     ├── Tutorial/course creation     →  100-500 AEV             │
│     ├── Conference talks             →  200 AEV per talk        │
│     └── Open source tools            →  Varies by impact        │
│                                                                  │
│  4. INFRASTRUCTURE OPERATION                                    │
│     ├── Run storage node             →  0.001 AEV/GB/month      │
│     ├── Run compute node             →  0.01 AEV/GPU-hour       │
│     ├── Run relay node               →  0.0005 AEV/GB relayed   │
│     └── Run validator node           →  Block rewards           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 6.4 Grants Program

| Grant Tier | Amount | Requirements |
|------------|--------|--------------|
| **Micro Grant** | 100-500 AEV | Proof of concept, 1-week delivery |
| **Builder Grant** | 500-5,000 AEV | Working prototype, 1-month delivery |
| **Ecosystem Grant** | 5,000-50,000 AEV | Production-ready, strategic value |
| **Strategic Partnership** | 50,000+ AEV | Major integration, long-term commitment |

**Priority Areas for Grants:**
- AI model hosting and inference optimization
- Cross-chain bridges and Web3 integrations
- Mobile/desktop native applications
- Developer tooling (SDKs, CLIs, IDEs)
- Security auditing and formal verification

### 6.5 Early Adopter Advantages

```
┌─────────────────────────────────────────────────────────────────┐
│                  EARLY ADOPTER MULTIPLIERS                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Phase 1 (Now - Q1 2025):     5x reward multiplier              │
│  Phase 2 (Q2 2025):           3x reward multiplier              │
│  Phase 3 (Q3 2025):           2x reward multiplier              │
│  Phase 4 (Q4 2025+):          1x (standard rates)               │
│                                                                  │
│  Example:                                                        │
│    - Publish plugin now      →  100 AEV × 5 = 500 AEV           │
│    - Same plugin in Q4 2025  →  100 AEV × 1 = 100 AEV           │
│                                                                  │
│  PLUS: Early plugins get "Founding Developer" badge             │
│        and priority placement in marketplace                    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 6.6 Staking for Developers

Developers can stake AevCoin to unlock additional benefits:

| Stake Amount | Tier | Benefits |
|--------------|------|----------|
| 100 AEV | **Bronze** | Access to beta APIs, developer Discord |
| 1,000 AEV | **Silver** | Priority support, early feature access |
| 10,000 AEV | **Gold** | Revenue share boost (+10%), governance votes |
| 100,000 AEV | **Platinum** | Direct team access, co-marketing, advisory role |

### 6.7 Developer Onboarding Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                  GETTING STARTED AS A DEVELOPER                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Step 1: Clone & Setup (5 min)                                  │
│  ────────────────────────────                                   │
│  $ git clone https://github.com/aevov/aevov-starter             │
│  $ docker-compose up -d                                         │
│  $ aevov wallet create    # Creates your AEV wallet             │
│                                                                  │
│  Step 2: Build Something (1 hour - 1 week)                      │
│  ─────────────────────────────────────────                      │
│  - Use any existing Aevov plugin as template                    │
│  - Full WordPress environment included                          │
│  - Hot reload for rapid development                             │
│                                                                  │
│  Step 3: Publish & Earn (5 min)                                 │
│  ─────────────────────────────                                  │
│  $ aevov plugin publish ./my-plugin                             │
│  > Plugin published! Earned 500 AEV (5x early adopter bonus)    │
│  > Your plugin is now available in the Aevov marketplace        │
│                                                                  │
│  Step 4: Passive Income (Ongoing)                               │
│  ────────────────────────────────                               │
│  - Earn per installation                                        │
│  - Earn per active user                                         │
│  - Earn percentage of usage fees                                │
│  - All payments in AevCoin, direct to your wallet               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 6.8 Comparison: Aevov vs Traditional Platforms

| Aspect | Web2 (WordPress.org) | Web3 (Typical) | Aevov (Web4) |
|--------|----------------------|----------------|--------------|
| **Upfront Cost** | Free | Gas fees | Free |
| **Revenue Share** | 0% (donations only) | 0-30% | **70-80%** |
| **Payment** | PayPal (fees) | Volatile tokens | AevCoin (stable utility) |
| **Ownership** | Platform controls | You own | **You own + earn** |
| **Discoverability** | Competing with 60K plugins | Fragmented | **Curated + incentivized** |
| **Support** | Community only | Discord maybe | **Paid priority support** |

### 6.9 Example: Developer Earnings Projection

```
Scenario: Developer builds an AI image enhancement plugin

Month 1 (Launch):
  - Publish bonus:                    500 AEV (5x early adopter)
  - 50 installations × 1 AEV:          50 AEV
  - Total:                            550 AEV

Month 6 (Growth):
  - 500 active users × 0.1 AEV:        50 AEV/month
  - Usage fees (1000 images × $0.01):  70 AEV (70% share)
  - Total:                            120 AEV/month

Month 12 (Maturity):
  - 2000 active users × 0.1 AEV:      200 AEV/month
  - Usage fees (10K images × $0.01):  700 AEV/month
  - Total:                            900 AEV/month

Year 1 Total Earnings:              ~6,000 AEV
  (At $1 AEV = $6,000 passive income from one plugin)
```

---

## 7. Migration Strategy

### 7.1 Phase 1: Parallel Operation (Months 1-3)

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 1: Run AevStore alongside Cubbit                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────┐         ┌─────────────┐                    │
│  │   Cubbit    │◄───────►│  AevStore   │                    │
│  │   (Primary) │  sync   │  (Shadow)   │                    │
│  └─────────────┘         └─────────────┘                    │
│                                                              │
│  - All writes go to both systems                            │
│  - Reads from Cubbit (proven reliability)                   │
│  - Validate AevStore data integrity                         │
│  - Build node network                                        │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 7.2 Phase 2: Gradual Shift (Months 4-6)

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 2: Shift reads to AevStore                           │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  - 25% reads from AevStore (Month 4)                        │
│  - 50% reads from AevStore (Month 5)                        │
│  - 75% reads from AevStore (Month 6)                        │
│  - Cubbit as fallback only                                  │
│  - Launch AevCoin testnet                                   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 7.3 Phase 3: Full Independence (Months 7-9)

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 3: Cubbit deprecation                                │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  - 100% reads/writes to AevStore                            │
│  - Migrate all existing Cubbit data                         │
│  - Terminate Cubbit contracts                               │
│  - AevCoin mainnet launch                                   │
│  - Full Web4 operation                                      │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 7.4 Code Migration Path

```php
// Current: Cubbit dependency
$cubbit = new AevovCubbitCDN();
$url = $cubbit->generate_presigned_url($key, 'GET', 3600);

// Migration: Abstraction layer
$storage = AevovStorage::getInstance(); // Returns Cubbit or AevStore
$url = $storage->get_url($key, 3600);

// Web4: Pure AevStore
$aevstore = AevStore::getInstance();
$cid = $aevstore->store($data);
$data = $aevstore->retrieve($cid);
```

---

## 8. Technical Specifications

### 8.1 AevStore Protocol

```
Message Format:
┌────────────────────────────────────────────────────────────┐
│  Version (1 byte) │ Type (1 byte) │ Length (4 bytes)       │
├────────────────────────────────────────────────────────────┤
│  CID (32 bytes)                                            │
├────────────────────────────────────────────────────────────┤
│  Payload (variable)                                        │
├────────────────────────────────────────────────────────────┤
│  Signature (64 bytes)                                      │
└────────────────────────────────────────────────────────────┘

Message Types:
  0x01 - STORE_REQUEST
  0x02 - STORE_RESPONSE
  0x03 - RETRIEVE_REQUEST
  0x04 - RETRIEVE_RESPONSE
  0x05 - PROOF_CHALLENGE
  0x06 - PROOF_RESPONSE
  0x07 - PIN_REQUEST
  0x08 - UNPIN_REQUEST
```

### 8.2 AevCoin Blockchain

```
Block Structure:
┌────────────────────────────────────────────────────────────┐
│  Block Header                                              │
│  ├── Version: uint32                                       │
│  ├── Previous Hash: bytes32                                │
│  ├── Merkle Root: bytes32                                  │
│  ├── Timestamp: uint64                                     │
│  ├── Contribution Score: uint64                            │
│  └── Producer Address: bytes20                             │
├────────────────────────────────────────────────────────────┤
│  Transactions[]                                            │
│  ├── From: bytes20                                         │
│  ├── To: bytes20                                           │
│  ├── Amount: uint256                                       │
│  ├── Nonce: uint64                                         │
│  ├── Data: bytes (optional)                                │
│  └── Signature: bytes64                                    │
├────────────────────────────────────────────────────────────┤
│  Contribution Proofs[]                                     │
│  ├── Node: bytes20                                         │
│  ├── Type: enum {Storage, Compute, Bandwidth}              │
│  ├── Amount: uint64                                        │
│  └── Proof: bytes                                          │
└────────────────────────────────────────────────────────────┘

Parameters:
  Block Time: 10 seconds
  Max Block Size: 2 MB
  Max Transactions: 1000/block
  Finality: 6 blocks (~1 minute)
```

### 8.3 Integration Points

```php
// WordPress hooks for AevStore integration
add_action('aevov_storage_store', function($data, $options) {
    $aevstore = AevStore::getInstance();
    return $aevstore->store($data, $options);
}, 10, 2);

add_action('aevov_storage_retrieve', function($cid) {
    $aevstore = AevStore::getInstance();
    return $aevstore->retrieve($cid);
});

// AevCoin integration hooks
add_action('aevov_aevcoin_reward', function($address, $amount, $reason) {
    $wallet = AevWallet::getInstance();
    return $wallet->reward($address, $amount, $reason);
}, 10, 3);

add_filter('aevov_aevcoin_balance', function($address) {
    $wallet = AevWallet::getInstance();
    return $wallet->balance($address);
});
```

---

## 9. Roadmap

### 9.1 Timeline

```
2024 Q4 - Current (Web3)
├── Cubbit DS3 integration ✓
├── Meshcore P2P networking ✓
├── Token placeholder system ✓
└── Pattern Sync Protocol ✓

2025 Q1 - AevStore Alpha
├── Content-addressed storage design
├── Single-node implementation
├── Basic erasure coding
└── Integration with Memory Core

2025 Q2 - AevStore Beta
├── Multi-node distribution
├── Proof-of-Storage implementation
├── Tensor sharding for AI
└── Migration tooling

2025 Q3 - AevCoin Testnet
├── Blockchain implementation
├── Wallet integration
├── Proof-of-Contribution consensus
└── Testnet rewards

2025 Q4 - Web4 Launch
├── AevStore production
├── AevCoin mainnet
├── Cubbit deprecation
└── Full decentralization
```

### 9.2 Milestones

| Milestone | Target | Criteria |
|-----------|--------|----------|
| **AevStore Alpha** | Q1 2025 | Single-node storage working |
| **AevStore Beta** | Q2 2025 | 10+ nodes, 1TB+ stored |
| **AevCoin Testnet** | Q3 2025 | 100+ validators, 10K+ transactions |
| **Web4 Launch** | Q4 2025 | Zero Cubbit dependency |

---

## Appendix A: Glossary

| Term | Definition |
|------|------------|
| **AevStore** | Native decentralized storage engine |
| **AevCoin (AEV)** | Native cryptocurrency for incentives |
| **AevIP** | Mesh networking protocol |
| **CID** | Content Identifier (hash-based) |
| **PoC** | Proof-of-Contribution consensus |
| **Web4** | Fully decentralized infrastructure |

---

## Appendix B: References

1. IPFS Whitepaper - Content-addressed storage
2. Filecoin - Proof-of-Storage mechanisms
3. Ethereum 2.0 - Proof-of-Stake consensus
4. libp2p - Peer-to-peer networking
5. Reed-Solomon - Erasure coding algorithms

---

*This document is a living specification. Updates will be made as the Web4 architecture evolves.*

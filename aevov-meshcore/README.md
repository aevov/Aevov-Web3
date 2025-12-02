# Aevov Meshcore - Decentralized Mesh Networking for WordPress

**Version:** 1.0.0
**Author:** Aevov Team
**License:** MIT

## Overview

Aevov Meshcore implements **deep mesh networking support** that enables the Aevov system to function as a **decentralized alternative to traditional ISPs**. Through peer-to-peer WebRTC connections, distributed hash table (DHT) discovery, multi-hop routing, and bandwidth sharing incentives, Meshcore creates a resilient, censorship-resistant mesh network.

## Why Meshcore?

Traditional internet infrastructure relies on centralized ISPs that can:
- Monitor and control user traffic
- Impose restrictions and censorship
- Fail as single points of failure
- Charge for bandwidth access

**Meshcore solves this** by enabling:
- ✅ **Direct peer-to-peer connections** (WebRTC)
- ✅ **Decentralized node discovery** (Kademlia DHT)
- ✅ **Multi-hop mesh routing** (AODV-inspired)
- ✅ **Bandwidth sharing economy** (token-based incentives)
- ✅ **Offline-first capability** (local mesh when internet unavailable)
- ✅ **Integration with AevIP** (distributed computing over mesh)

## Architecture

### Core Components

#### 1. **Node Manager** (`includes/core/class-node-manager.php`)
- Manages node identity (public/private key pairs)
- Detects and advertises node capabilities
- Handles reputation scoring
- Signs and verifies packets

#### 2. **WebRTC P2P Layer** (`includes/p2p/`)
- **Connection Manager**: Establishes and maintains peer connections
- **WebRTC Signaling**: SDP offer/answer exchange
- **NAT Traversal**: STUN/TURN server integration
- Browser-based peer-to-peer data channels

#### 3. **DHT Discovery** (`includes/discovery/`)
- **DHTService**: Kademlia-based distributed hash table
- **Peer Discovery**: Multiple discovery strategies (bootstrap, DHT, PEX, local)
- **Service Registry**: Mesh DNS for service discovery
- Decentralized, no central servers required

#### 4. **Mesh Routing** (`includes/routing/`)
- **MeshRouter**: Multi-hop packet routing
- **Routing Table**: Maintains routes to destinations
- **Path Optimizer**: Finds optimal paths using Dijkstra
- Reactive (on-demand) and proactive routing
- Loop prevention and quality-aware path selection

#### 5. **Relay & Bandwidth Sharing** (`includes/relay/`)
- **RelayManager**: Routes traffic through intermediary nodes
- **BandwidthManager**: Allocates bandwidth for operations
- **IncentiveSystem**: Token economics for bandwidth sharing
- Nodes earn tokens by relaying, spend tokens to use network

#### 6. **Security** (`includes/security/`)
- **MeshSecurity**: Packet signing and verification
- **EncryptionManager**: End-to-end AES-256-GCM encryption
- Rate limiting and anti-spam
- Blacklist management

#### 7. **AevIP Integration** (`includes/core/class-aevip-integration.php`)
- Enables AevIP distributed computing over mesh
- Mesh transport for workload distribution
- Seamless integration with existing Aevov ecosystem

### Database Schema

#### Mesh Nodes
```sql
meshcore_nodes (
    id, node_id, peer_id, public_key, capabilities,
    network_info, last_seen, status, reputation_score
)
```

#### Active Connections
```sql
meshcore_connections (
    id, connection_id, local_node_id, remote_node_id,
    connection_type, status, quality_score, bandwidth_up,
    bandwidth_down, latency, packet_loss, bytes_sent, bytes_received
)
```

#### Routing Table
```sql
meshcore_routes (
    id, destination_id, next_hop_id, hop_count,
    path_quality, path_cost, path_nodes, expires_at
)
```

#### DHT Storage
```sql
meshcore_dht (
    id, key_hash, value_data, node_id, ttl, expires_at
)
```

#### Bandwidth Tokens
```sql
meshcore_bandwidth_tokens (
    id, node_id, tokens_earned, tokens_spent,
    bytes_relayed, bytes_consumed, reputation_modifier
)
```

## How It Works

### 1. Node Discovery

When a node joins the mesh:

```
┌─────────────┐       DHT        ┌─────────────┐
│   Node A    │ ◄──────────────► │   Node B    │
└─────────────┘                   └─────────────┘
       │                                 │
       └────────► Bootstrap Nodes ◄──────┘
```

1. Node generates cryptographic identity (Ed25519 keypair)
2. Announces presence to DHT
3. Discovers peers via bootstrap nodes
4. Exchanges capabilities with peers

### 2. Connection Establishment

WebRTC signaling flow:

```
Node A                              Node B
  │                                   │
  ├──► Create Offer (SDP) ──────────►│
  │                                   ├──► Create Answer
  │◄──────────── Answer (SDP) ────────┤
  │                                   │
  ├──► ICE Candidates ──────────────►│
  │◄────────── ICE Candidates ────────┤
  │                                   │
  └──── Peer Connection Established ─┘
```

### 3. Multi-Hop Routing

Packet forwarding through mesh:

```
Source                                       Destination
  │                                                │
  ├──► Find Route ──────────────────────────────► │
  │                                                │
  │     Route: Source → Relay1 → Relay2 → Dest    │
  │                                                │
  ├──► Packet ──► Relay1 ──► Relay2 ──► Dest    │
  │                  ↓           ↓                 │
  │           [Earn Tokens] [Earn Tokens]         │
```

### 4. Bandwidth Incentives

Token economy:

- **Earn**: Relay 1 MB = 100 tokens (configurable)
- **Spend**: Transfer data through others' nodes
- **Reputation**: Quality relaying increases reputation → higher token multiplier
- **Balance**: Tokens earned - tokens spent

## Installation

### Requirements

- WordPress 6.3+
- PHP 7.4+
- MySQL 8.0+
- Redis 7+ (for rate limiting)
- Modern browser with WebRTC support

### Setup

1. **Upload Plugin**
   ```bash
   cp -r aevov-meshcore /path/to/wordpress/wp-content/plugins/
   ```

2. **Activate Plugin**
   - Navigate to WordPress Admin → Plugins
   - Activate "Aevov Meshcore"

3. **Configure Settings**
   - Go to Meshcore → Settings
   - Set bandwidth limits
   - Configure STUN/TURN servers (optional)
   - Enable relay (to share bandwidth)

4. **Bootstrap Nodes** (Optional)
   - Add known bootstrap nodes for faster discovery
   - Settings → Bootstrap URLs

## Configuration

### Bandwidth Settings

```php
// Maximum bandwidth to share for relay (bytes/sec)
update_option('aevov_meshcore_relay_bandwidth', 5 * 1024 * 1024); // 5 MB/s

// Maximum connections
update_option('aevov_meshcore_max_connections', 50);

// Minimum connections to maintain
update_option('aevov_meshcore_min_connections', 3);
```

### Token Economics

```php
// Tokens earned per MB relayed
update_option('aevov_meshcore_tokens_per_mb', 100);

// Token exchange rate
update_option('aevov_meshcore_token_rate', 100.0);
```

### Routing

```php
// Maximum hops for packet routing
update_option('aevov_meshcore_max_hops', 10);

// Route timeout (seconds)
update_option('aevov_meshcore_route_timeout', 300);
```

### STUN/TURN Servers

```php
// Custom STUN servers
update_option('aevov_meshcore_stun_servers', [
    'stun:stun.example.com:3478'
]);

// Custom TURN servers (for NAT traversal)
update_option('aevov_meshcore_turn_servers', [
    [
        'urls' => 'turn:turn.example.com:3478',
        'username' => 'user',
        'credential' => 'pass'
    ]
]);
```

## API Usage

### REST API

#### Get Node Info
```bash
GET /wp-json/aevov-meshcore/v1/node/info
```

#### Get Active Connections
```bash
GET /wp-json/aevov-meshcore/v1/connections
```

#### Connect to Peer
```bash
POST /wp-json/aevov-meshcore/v1/connect
{
    "peer_id": "abc123...",
    "peer_info": {...}
}
```

#### DHT Operations
```bash
# Store data
POST /wp-json/aevov-meshcore/v1/dht/put
{
    "key": "mykey",
    "value": {...},
    "ttl": 3600
}

# Retrieve data
GET /wp-json/aevov-meshcore/v1/dht/get?key=mykey
```

#### Find Route
```bash
POST /wp-json/aevov-meshcore/v1/route/find
{
    "destination": "node_id_here"
}
```

#### Network Stats
```bash
GET /wp-json/aevov-meshcore/v1/stats
```

### JavaScript API

```javascript
// Initialize mesh client
const mesh = new MeshcoreP2P({
    apiUrl: '/wp-json/aevov-meshcore/v1',
    onPeerConnected: (peerId) => {
        console.log('Connected to:', peerId);
    },
    onMessage: (peerId, data) => {
        console.log('Message from', peerId, ':', data);
    }
});

// Connect to a peer
await mesh.connectToPeer(peerId, peerInfo);

// Send data to peer
mesh.sendToPeer(peerId, { type: 'chat', message: 'Hello!' });

// Broadcast to all peers
mesh.broadcastToPeers({ type: 'announcement', data: '...' });

// Get network stats
const stats = await mesh.getNetworkStats();
```

## Use Cases

### 1. Community Mesh Networks

Deploy Meshcore across a community to create a local mesh network independent of traditional ISPs:

- **Offline-first**: Works without internet connectivity
- **Local services**: Share files, chat, collaborate locally
- **Fallback**: Automatic fallback when internet is down

### 2. Distributed Application Backend

Use Meshcore as transport for distributed applications:

```php
// Store application state in DHT
$dht_service->put('app:user:123', $user_data, 3600);

// Retrieve from any node
$user_data = $dht_service->get('app:user:123');
```

### 3. Content Distribution Network (CDN)

Distribute content across mesh nodes:

- Cache popular content on relay nodes
- Earn tokens by serving cached content
- Reduced bandwidth costs

### 4. Censorship Resistance

Route traffic through mesh when direct access is blocked:

- Multi-hop routing bypasses single point of control
- Encrypted end-to-end
- Decentralized - no central authority

### 5. AevIP Distributed Computing

Run distributed AevIP workloads over the mesh:

```php
// AevIP automatically uses mesh transport when available
$aevip->distribute_workload($tasks, $nodes);
```

## Performance

### Benchmarks (Typical)

- **Peer Discovery**: < 2 seconds (with bootstrap)
- **Connection Establishment**: 1-3 seconds (WebRTC handshake)
- **Routing Overhead**: 10-50ms per hop
- **DHT Lookup**: < 500ms (log N hops)
- **Throughput**: Up to WebRTC limits (~1-10 Mbps typical)

### Scalability

- **Nodes**: Designed for 10,000+ nodes per mesh
- **Routes**: Can maintain 1000s of routes per node
- **DHT Storage**: Distributed across nodes (100MB default per node)
- **Connections**: 3-50 connections per node (configurable)

## Security

### Cryptography

- **Node Identity**: Ed25519 (ECC) keypairs
- **Packet Signing**: HMAC-SHA256
- **End-to-End Encryption**: AES-256-GCM
- **Key Exchange**: Handled by WebRTC (DTLS-SRTP)

### Threat Model

Protections against:
- ✅ **Impersonation**: Public key cryptography
- ✅ **Man-in-the-middle**: End-to-end encryption
- ✅ **Spam/DoS**: Rate limiting, reputation scoring
- ✅ **Sybil attacks**: Reputation system, proof-of-relay
- ✅ **Route poisoning**: Signed route advertisements

### Best Practices

1. **Enable encryption**: Always encrypt sensitive data
2. **Verify signatures**: Check packet signatures
3. **Limit exposure**: Don't relay for untrusted nodes
4. **Monitor reputation**: Blacklist misbehaving nodes
5. **Update regularly**: Keep plugin updated

## Troubleshooting

### No Peers Discovered

```bash
# Check bootstrap nodes
wp option get aevov_meshcore_bootstrap_urls

# Manually announce
wp option update aevov_meshcore_announce_interval 60
```

### Poor Connection Quality

```bash
# Check NAT traversal
# Enable TURN servers for strict NAT

# Check firewall
# Allow WebRTC ports (UDP/TCP random high ports)
```

### High Token Spend

```bash
# Check relay usage
GET /wp-json/aevov-meshcore/v1/relay/stats

# Reduce outbound traffic or earn more by relaying
```

## Roadmap

- [ ] **Mobile support**: React Native/Cordova integration
- [ ] **Bluetooth mesh**: Local mesh over Bluetooth
- [ ] **LoRa integration**: Long-range mesh networking
- [ ] **IPFS integration**: Decentralized storage backend
- [ ] **WebTorrent support**: Torrent-based file sharing
- [ ] **Onion routing**: Tor-like privacy layer
- [ ] **Governance**: Token-based mesh governance

## Contributing

Contributions welcome! Please submit pull requests to the main Aevov repository.

## License

MIT License - See LICENSE file for details

## Support

- **Documentation**: https://aevov.com/docs/meshcore
- **Issues**: https://github.com/aevov/aevov-meshcore/issues
- **Community**: https://discord.gg/aevov

---

**Aevov Meshcore** - Building the decentralized internet, one mesh at a time. 🌐

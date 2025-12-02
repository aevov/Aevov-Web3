/**
 * Meshcore P2P Client
 *
 * Browser-side WebRTC peer-to-peer connection handler.
 */

(function() {
    'use strict';

    class MeshcoreP2P {
        constructor(config) {
            this.config = config || {};
            this.apiUrl = this.config.apiUrl || '/wp-json/aevov-meshcore/v1';
            this.nodeId = this.config.nodeId || null;
            this.peers = new Map();
            this.iceServers = [];

            this.init();
        }

        async init() {
            // Get node info
            await this.fetchNodeInfo();

            // Get ICE servers
            await this.fetchIceServers();

            // Start peer discovery
            this.startPeerDiscovery();
        }

        async fetchNodeInfo() {
            try {
                const response = await fetch(`${this.apiUrl}/node/info`);
                const data = await response.json();

                if (data.success) {
                    this.nodeId = data.node.node_id;
                    console.log('Meshcore: Node ID:', this.nodeId);
                }
            } catch (error) {
                console.error('Meshcore: Failed to fetch node info:', error);
            }
        }

        async fetchIceServers() {
            this.iceServers = [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ];
        }

        async connectToPeer(peerId, peerInfo) {
            if (this.peers.has(peerId)) {
                console.log('Meshcore: Already connected to peer:', peerId);
                return this.peers.get(peerId);
            }

            console.log('Meshcore: Connecting to peer:', peerId);

            const peerConnection = new RTCPeerConnection({
                iceServers: this.iceServers
            });

            // Create data channel
            const dataChannel = peerConnection.createDataChannel('meshcore', {
                ordered: true
            });

            this.setupDataChannel(dataChannel, peerId);
            this.setupPeerConnection(peerConnection, peerId);

            // Create and send offer
            const offer = await peerConnection.createOffer();
            await peerConnection.setLocalDescription(offer);

            // Send offer via signaling server
            await this.sendSignal('offer', {
                peerId: peerId,
                offer: offer
            });

            this.peers.set(peerId, {
                connection: peerConnection,
                dataChannel: dataChannel,
                info: peerInfo
            });

            return peerConnection;
        }

        setupDataChannel(dataChannel, peerId) {
            dataChannel.onopen = () => {
                console.log('Meshcore: Data channel opened with', peerId);
                this.onPeerConnected(peerId);
            };

            dataChannel.onclose = () => {
                console.log('Meshcore: Data channel closed with', peerId);
                this.onPeerDisconnected(peerId);
            };

            dataChannel.onmessage = (event) => {
                this.onPeerMessage(peerId, event.data);
            };

            dataChannel.onerror = (error) => {
                console.error('Meshcore: Data channel error:', error);
            };
        }

        setupPeerConnection(peerConnection, peerId) {
            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.sendSignal('ice', {
                        peerId: peerId,
                        candidate: event.candidate
                    });
                }
            };

            peerConnection.onconnectionstatechange = () => {
                console.log('Meshcore: Connection state:', peerConnection.connectionState);

                if (peerConnection.connectionState === 'failed' ||
                    peerConnection.connectionState === 'closed') {
                    this.peers.delete(peerId);
                }
            };
        }

        async sendSignal(type, data) {
            try {
                const response = await fetch(`${this.apiUrl}/signal/${type}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                return await response.json();
            } catch (error) {
                console.error('Meshcore: Signaling error:', error);
            }
        }

        sendToPeer(peerId, data) {
            const peer = this.peers.get(peerId);

            if (!peer || !peer.dataChannel || peer.dataChannel.readyState !== 'open') {
                console.warn('Meshcore: Cannot send to peer, channel not ready:', peerId);
                return false;
            }

            try {
                const message = typeof data === 'string' ? data : JSON.stringify(data);
                peer.dataChannel.send(message);
                return true;
            } catch (error) {
                console.error('Meshcore: Send error:', error);
                return false;
            }
        }

        broadcastToPeers(data) {
            let sent = 0;

            this.peers.forEach((peer, peerId) => {
                if (this.sendToPeer(peerId, data)) {
                    sent++;
                }
            });

            return sent;
        }

        async discoverPeers(count = 10) {
            try {
                const response = await fetch(`${this.apiUrl}/peers?limit=${count}`);
                const data = await response.json();

                if (data.success && data.peers) {
                    return data.peers;
                }
            } catch (error) {
                console.error('Meshcore: Peer discovery error:', error);
            }

            return [];
        }

        async startPeerDiscovery() {
            setInterval(async () => {
                const peers = await this.discoverPeers(5);

                for (const peer of peers) {
                    if (peer.node_id !== this.nodeId && !this.peers.has(peer.node_id)) {
                        // Attempt to connect
                        this.connectToPeer(peer.node_id, peer);
                    }
                }
            }, 30000); // Every 30 seconds
        }

        onPeerConnected(peerId) {
            console.log('Meshcore: Peer connected:', peerId);

            if (this.config.onPeerConnected) {
                this.config.onPeerConnected(peerId);
            }
        }

        onPeerDisconnected(peerId) {
            console.log('Meshcore: Peer disconnected:', peerId);
            this.peers.delete(peerId);

            if (this.config.onPeerDisconnected) {
                this.config.onPeerDisconnected(peerId);
            }
        }

        onPeerMessage(peerId, message) {
            console.log('Meshcore: Message from', peerId, ':', message);

            try {
                const data = JSON.parse(message);

                if (this.config.onMessage) {
                    this.config.onMessage(peerId, data);
                }
            } catch (error) {
                // Not JSON, pass as string
                if (this.config.onMessage) {
                    this.config.onMessage(peerId, message);
                }
            }
        }

        getConnectedPeers() {
            return Array.from(this.peers.keys());
        }

        getPeerCount() {
            return this.peers.size;
        }

        async getNetworkStats() {
            try {
                const response = await fetch(`${this.apiUrl}/stats`);
                const data = await response.json();

                if (data.success) {
                    return data.stats;
                }
            } catch (error) {
                console.error('Meshcore: Failed to fetch stats:', error);
            }

            return null;
        }
    }

    // Expose globally
    window.MeshcoreP2P = MeshcoreP2P;

    // Auto-initialize if config exists
    if (window.aevovMeshcore) {
        window.meshcoreClient = new MeshcoreP2P(window.aevovMeshcore);
    }
})();

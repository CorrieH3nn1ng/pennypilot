# Security Module

> **Module Path:** `backend/app/Security/`
> **Architectural Directive:** #2 - Zero-Knowledge Security (POPIA Compliant)

## Purpose

This module implements PennyPilot's cryptographic security layer, ensuring user financial data remains private through encryption, hashing, and blockchain-anchored integrity proofs.

## Intended Components

```
Security/
├── Encryption/
│   ├── EncryptionService.php      # AES-256-GCM encryption/decryption
│   ├── KeyDerivationService.php   # PBKDF2 key generation
│   └── RecoveryPhraseService.php  # BIP39 mnemonic handling
├── Hashing/
│   ├── MerkleTreeService.php      # Transaction integrity tree
│   ├── TransactionHasher.php      # Individual record hashing
│   └── AnchorService.php          # Blockchain hash anchoring
├── Verification/
│   ├── ProofGenerator.php         # Generate integrity proofs
│   ├── ProofVerifier.php          # Verify data hasn't been tampered
│   └── AuditTrailService.php      # Access logging for POPIA
└── Contracts/
    ├── EncryptableInterface.php   # Models that support encryption
    └── HashableInterface.php      # Models that support hashing
```

## Key Concepts

### Zero-Knowledge Architecture
The server should never have access to plaintext financial data:

```
Client                          Server
──────                          ──────
User Password
      ↓
Key Derivation (PBKDF2)
      ↓
Encryption Key (never sent)
      ↓
Encrypt Data (AES-256-GCM)
      ↓
─────────────────────────────→  Store Ciphertext
                                (Cannot decrypt)
```

### Merkle Tree Integrity

Each transaction is hashed, and hashes are combined into a Merkle Tree:

```
                    Root Hash (Anchor to Blockchain)
                   /                              \
            Hash(1+2)                         Hash(3+4)
           /        \                        /        \
      Hash(Tx1)  Hash(Tx2)              Hash(Tx3)  Hash(Tx4)
```

Benefits:
- Prove specific transaction existed at a point in time
- Detect any tampering in historical records
- Efficient verification (log n complexity)

### Blockchain Anchoring

Periodic root hash publication to a public blockchain:
- **Layer 2 preferred** (lower cost, faster confirmation)
- **Timestamp proof**: Proves data existed before anchor time
- **Tamper evidence**: Any change invalidates the chain
- **Optional**: Users can verify without trusting PennyPilot

## POPIA Compliance Features

### Data Subject Rights
- **Right to Access**: Export all encrypted data + decryption guide
- **Right to Erasure**: Complete deletion including backups
- **Right to Portability**: Standard format export (JSON)
- **Right to Rectification**: Audit trail of corrections

### Audit Trail
Every data access is logged:
```php
AuditTrailService::log(
    userId: $user->id,
    action: 'view',
    resource: 'transactions',
    ipAddress: $request->ip(),
    timestamp: now()
);
```

### Consent Management
- Granular consent tracking per feature
- Consent withdrawal triggers data handling review
- Third-party sharing requires explicit opt-in

## Security Standards

| Standard | Implementation |
|----------|----------------|
| Encryption | AES-256-GCM (authenticated) |
| Key Derivation | PBKDF2-SHA256 (100k iterations) |
| Hashing | SHA-256 (transactions), SHA-384 (Merkle) |
| Recovery Phrase | BIP39 (12/24 word mnemonic) |
| TLS | 1.3 minimum for all API calls |

## Database Considerations

Encrypted fields use `BLOB` or `TEXT` storage:
- Never index encrypted columns
- Search requires client-side decryption
- Metadata (timestamps, IDs) may remain unencrypted for queries

---

*For technical auditors: This module handles cryptographic operations. All encryption keys are derived client-side; the server stores only ciphertext. Merkle proofs can be independently verified against public blockchain anchors.*

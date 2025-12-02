# Aevov Deployment Checklist

A comprehensive checklist for deploying Aevov to production environments.

---

## Pre-Deployment Phase

### 1. Infrastructure Requirements

- [ ] **Server Specifications**
  - [ ] Minimum 4 CPU cores (8 recommended)
  - [ ] Minimum 8GB RAM (16GB recommended)
  - [ ] Minimum 100GB SSD storage
  - [ ] Ubuntu 22.04 LTS or similar Linux distribution

- [ ] **Software Dependencies**
  - [ ] Docker 24.0+ installed
  - [ ] Docker Compose 2.20+ installed
  - [ ] Git installed
  - [ ] SSL certificates obtained (Let's Encrypt or commercial)

- [ ] **Network Configuration**
  - [ ] Domain name configured with DNS
  - [ ] Ports 80, 443 open for web traffic
  - [ ] Firewall configured (UFW/iptables)
  - [ ] Reverse proxy ready (nginx/Traefik) if using custom domain

### 2. Security Preparation

- [ ] **Secrets Management**
  - [ ] Generate strong MySQL root password (32+ characters)
  - [ ] Generate strong MySQL user password (32+ characters)
  - [ ] Generate JWT secret (64+ random characters)
  - [ ] Store secrets in secure vault (recommended: HashiCorp Vault, AWS Secrets Manager)

- [ ] **API Keys Obtained**
  - [ ] OpenAI API key (required for Language Engine)
  - [ ] Stability AI API key (optional, for Image Engine)
  - [ ] DALL-E API key (optional, for Image Engine)
  - [ ] Cubbit credentials (optional, for decentralized storage)
  - [ ] Pinecone API key (optional, for vector memory)
  - [ ] Whisper API key (optional, for transcription)

- [ ] **Security Hardening**
  - [ ] SSH key-based authentication only
  - [ ] Fail2ban or similar intrusion prevention
  - [ ] Automatic security updates enabled
  - [ ] Non-root user for Docker operations

### 3. Environment Configuration

- [ ] **Create Production Environment File**
  - [ ] Copy `.env.production.example` to `.env.production`
  - [ ] Update all `CHANGE_THIS_*` placeholders
  - [ ] Set `APP_URL` to production domain
  - [ ] Configure email settings for notifications
  - [ ] Set `WORDPRESS_DEBUG=false`

- [ ] **Verify Configuration**
  - [ ] All passwords are unique and strong
  - [ ] No default/example values remain
  - [ ] API keys are valid and have appropriate rate limits
  - [ ] Email configuration tested

### 4. Backup Strategy

- [ ] **Database Backups**
  - [ ] Automated daily backup schedule configured
  - [ ] Backup retention policy defined (30 days recommended)
  - [ ] Off-site backup storage configured (S3, GCS, etc.)
  - [ ] Backup encryption enabled

- [ ] **Volume Backups**
  - [ ] WordPress uploads backup scheduled
  - [ ] Redis persistence configured
  - [ ] Test backup restoration procedure

---

## Deployment Phase

### 5. Initial Deployment

- [ ] **Clone Repository**
  ```bash
  git clone https://github.com/Jesse-wakandaisland/Aevov1.git
  cd Aevov1
  ```

- [ ] **Environment Setup**
  - [ ] Copy and configure `.env.production`
  - [ ] Verify file permissions (600 for .env files)
  - [ ] Create required directories (`reports/`, `docker/mysql-init/`)

- [ ] **Docker Deployment**
  ```bash
  docker-compose -f docker-compose.production.yml --env-file .env.production up -d
  ```
  - [ ] All containers start successfully
  - [ ] Health checks pass for all services
  - [ ] No error logs in container output

### 6. WordPress Setup

- [ ] **Initial WordPress Installation**
  ```bash
  docker-compose -f docker-compose.production.yml --profile tools run wpcli
  ```
  - [ ] WordPress core installed
  - [ ] Admin account created with strong password
  - [ ] Default admin password changed from 'admin'

- [ ] **Plugin Activation**
  - [ ] Core plugins activated in order:
    1. bloom-pattern-recognition
    2. AevovPatternSyncProtocol
    3. aps-tools
    4. aevov-core
    5. All remaining aevov-* plugins
  - [ ] No activation errors
  - [ ] Plugin dependencies satisfied

### 7. SSL/TLS Configuration

- [ ] **SSL Setup**
  - [ ] SSL certificates installed
  - [ ] Set `SSL_MODE=on` in environment
  - [ ] Redirect HTTP to HTTPS configured
  - [ ] HSTS header enabled

- [ ] **Verify SSL**
  - [ ] SSL Labs test grade A or higher
  - [ ] No mixed content warnings
  - [ ] Certificate auto-renewal configured

### 8. Post-Deployment Verification

- [ ] **Service Health**
  ```bash
  docker-compose -f docker-compose.production.yml ps
  ```
  - [ ] All containers showing "healthy" status
  - [ ] WordPress responds on configured port
  - [ ] Workflow Engine responds on port 3000

- [ ] **Functional Testing**
  - [ ] WordPress admin login works
  - [ ] All Aevov plugins show as active
  - [ ] API endpoints respond correctly
  - [ ] Test AI engine connectivity (if API keys configured)

- [ ] **Run Test Suite**
  ```bash
  docker-compose -f docker-compose.production.yml --profile testing run tests
  ```
  - [ ] All 414 tests pass
  - [ ] No critical errors in test output
  - [ ] Coverage report generated

---

## Post-Deployment Phase

### 9. Monitoring Setup

- [ ] **Application Monitoring**
  - [ ] Sentry DSN configured for error tracking
  - [ ] Health check endpoint verified
  - [ ] Uptime monitoring configured (UptimeRobot, Pingdom, etc.)

- [ ] **Infrastructure Monitoring**
  - [ ] Container metrics collection (Prometheus/cAdvisor)
  - [ ] Database performance monitoring
  - [ ] Redis metrics monitoring
  - [ ] Disk space alerts configured

- [ ] **Log Aggregation**
  - [ ] Docker logs accessible
  - [ ] Log rotation configured
  - [ ] Centralized logging (optional: ELK, Loki)

### 10. Performance Optimization

- [ ] **WordPress Optimization**
  - [ ] Redis object cache verified
  - [ ] OPcache hit rate > 95%
  - [ ] Page caching enabled (if applicable)

- [ ] **Database Optimization**
  - [ ] InnoDB buffer pool properly sized
  - [ ] Slow query log enabled
  - [ ] Index optimization completed

- [ ] **Asset Optimization**
  - [ ] Static assets cached
  - [ ] Gzip/Brotli compression enabled
  - [ ] CDN configured (optional)

### 11. Security Verification

- [ ] **Security Scan**
  ```bash
  docker-compose -f docker-compose.production.yml --profile quality run quality
  ```
  - [ ] No critical vulnerabilities found
  - [ ] WordPress security headers present
  - [ ] File permissions correct

- [ ] **Access Control**
  - [ ] phpMyAdmin disabled in production
  - [ ] Debug endpoints disabled
  - [ ] Rate limiting active
  - [ ] Admin area secured (IP whitelist or 2FA)

### 12. Documentation & Handoff

- [ ] **Operational Documentation**
  - [ ] Deployment procedure documented
  - [ ] Backup/restore procedures documented
  - [ ] Troubleshooting guide available
  - [ ] Contact information for support

- [ ] **Credentials Management**
  - [ ] All credentials stored securely
  - [ ] Access granted to appropriate team members
  - [ ] Emergency access procedures defined

---

## Rollback Plan

### In Case of Critical Issues

1. **Stop affected containers**
   ```bash
   docker-compose -f docker-compose.production.yml stop wordpress
   ```

2. **Restore from backup**
   ```bash
   # Restore database
   docker exec aevov-prod-mysql mysql -u root -p < backup.sql

   # Restore volumes
   docker volume restore wordpress_data < wordpress_backup.tar
   ```

3. **Restart services**
   ```bash
   docker-compose -f docker-compose.production.yml up -d
   ```

4. **Verify restoration**
   - [ ] All services healthy
   - [ ] Data integrity verified
   - [ ] Functionality tested

---

## Maintenance Schedule

| Task | Frequency | Responsible |
|------|-----------|-------------|
| Security updates | Weekly | DevOps |
| Database backup verification | Weekly | DevOps |
| Log review | Daily | DevOps |
| SSL certificate renewal | Before expiry | Automated |
| Performance review | Monthly | DevOps |
| Full disaster recovery test | Quarterly | DevOps |

---

## Emergency Contacts

| Role | Contact | Escalation |
|------|---------|------------|
| DevOps Lead | [Configure] | Primary |
| Backend Lead | [Configure] | Secondary |
| Security | [Configure] | Critical issues |

---

*Last Updated: 2025-11-30*
*Version: 1.0.0*

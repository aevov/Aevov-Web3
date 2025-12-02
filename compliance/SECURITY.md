# Security Policy

## Supported Versions

The following versions of the Aevov ecosystem are currently being supported with security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

The Aevov team takes security vulnerabilities seriously. We appreciate your efforts to responsibly disclose your findings.

### How to Report

**Please do NOT report security vulnerabilities through public GitHub issues.**

Instead, please report security vulnerabilities by emailing:

**security@aevov.dev** (or your designated security email)

You should receive a response within 48 hours. If for some reason you do not, please follow up via email to ensure we received your original message.

### What to Include

Please include the following information in your report:

- Type of issue (e.g., SQL injection, XSS, authentication bypass, etc.)
- Full paths of source file(s) related to the manifestation of the issue
- The location of the affected source code (tag/branch/commit or direct URL)
- Any special configuration required to reproduce the issue
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

### Response Process

1. **Acknowledgment**: We'll acknowledge receipt of your vulnerability report within 48 hours
2. **Assessment**: Our security team will investigate and assess the severity
3. **Resolution**: We'll work on a fix and keep you informed of progress
4. **Release**: Once fixed, we'll release a security update
5. **Disclosure**: We'll credit you in the security advisory (unless you prefer to remain anonymous)

### Security Update Timeline

- **Critical**: Patch within 48 hours
- **High**: Patch within 1 week
- **Medium**: Patch within 2 weeks
- **Low**: Patch in next regular release

## Security Best Practices

### For Users

1. **Keep Updated**: Always use the latest version of all Aevov plugins
2. **Strong Authentication**: Use strong passwords and enable two-factor authentication
3. **Principle of Least Privilege**: Only give users the permissions they need
4. **Regular Backups**: Maintain regular backups of your WordPress site
5. **Monitoring**: Use the monitoring tools provided to detect unusual activity
6. **HTTPS**: Always use HTTPS in production
7. **Security Headers**: Implement security headers (CSP, HSTS, etc.)

### For Developers

1. **Input Validation**: Always validate and sanitize user inputs
2. **Output Escaping**: Escape all output to prevent XSS attacks
3. **Prepared Statements**: Use prepared statements for database queries
4. **Authentication**: Verify nonces and capabilities for all actions
5. **Secure by Default**: Default configurations should be secure
6. **Dependency Management**: Keep all dependencies up to date
7. **Code Review**: Have security-critical code reviewed by multiple people

## Known Security Features

### Authentication & Authorization

- WordPress nonce verification for all state-changing operations
- Capability checks for all admin functions
- REST API authentication via WordPress cookies or application passwords
- Support for two-factor authentication plugins

### Data Protection

- All database queries use prepared statements (via `$wpdb->prepare()`)
- Input sanitization using WordPress functions (`sanitize_text_field()`, etc.)
- Output escaping using WordPress functions (`esc_html()`, `esc_attr()`, etc.)
- Password fields are never logged or exposed in error messages

### Network Security

- HTTPS enforcement available for all API endpoints
- Rate limiting support for API endpoints
- CORS configuration available
- Security headers can be configured

### File Uploads

- File type validation
- File size limits
- Secure file storage outside web root (when possible)
- Malware scanning hooks available

### Logging & Monitoring

- Security events are logged (login attempts, permission changes, etc.)
- Integration with error tracking services (Sentry, Rollbar, etc.)
- Audit trail available for critical operations

## Security Checklist for Production

Before deploying to production, ensure you've completed this security checklist:

### WordPress Core

- [ ] WordPress is updated to the latest version
- [ ] Debug mode is disabled (`WP_DEBUG = false`)
- [ ] Database table prefix is not the default `wp_`
- [ ] File permissions are correct (644 for files, 755 for directories)
- [ ] wp-config.php is protected from direct access
- [ ] .htaccess or nginx rules are in place

### Aevov Plugins

- [ ] All Aevov plugins are updated to the latest versions
- [ ] Production credentials are used (not development/test credentials)
- [ ] API keys are stored in environment variables or wp-config.php
- [ ] Error logging is enabled but error display is disabled
- [ ] Monitoring and alerting are configured
- [ ] Rate limiting is enabled for public APIs
- [ ] File upload directories have proper permissions

### Server & Infrastructure

- [ ] HTTPS is enforced (SSL/TLS certificate is valid)
- [ ] PHP is updated to a supported version (7.4+)
- [ ] Database is secured (strong password, restricted access)
- [ ] Firewall rules are configured
- [ ] Regular backups are automated
- [ ] Security monitoring is in place

### Access Control

- [ ] Admin accounts use strong, unique passwords
- [ ] Two-factor authentication is enabled for admin accounts
- [ ] Unused admin accounts are removed
- [ ] User roles follow principle of least privilege
- [ ] API keys are rotated regularly

## Vulnerability Disclosure Policy

### Safe Harbor

Aevov supports safe harbor for security researchers who:

- Make a good faith effort to avoid privacy violations, data destruction, and service disruption
- Only interact with accounts you own or with explicit permission
- Do not exploit a security issue beyond what is necessary to demonstrate it
- Provide us a reasonable time to resolve the issue before public disclosure

We will not pursue legal action against researchers who follow these guidelines.

### Public Disclosure Timeline

We aim to disclose security vulnerabilities publicly after they are fixed:

1. Vulnerability is reported to us
2. We acknowledge and assess the vulnerability
3. We develop and test a fix
4. We release the security update
5. We publish a security advisory 30 days after the fix is released (or sooner if already public)

## Security Audit History

| Date | Auditor | Scope | Findings |
|------|---------|-------|----------|
| TBD  | TBD     | TBD   | TBD      |

## Security Contacts

- **Security Team**: security@aevov.dev
- **Security Advisories**: https://github.com/aevov/security/advisories
- **Bug Bounty**: TBD (if applicable)

## Additional Resources

- [WordPress Security White Paper](https://wordpress.org/about/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress Plugin Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [WordPress REST API Security](https://developer.wordpress.org/rest-api/frequently-asked-questions/#security)

## Credits

We would like to thank the following security researchers for responsibly disclosing vulnerabilities:

- (List will be updated as vulnerabilities are reported and fixed)

---

**Last Updated**: 2025-01-19

For questions about this security policy, contact: security@aevov.dev

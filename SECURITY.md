# Security policy

## Supported versions

Each release of Checkout Fácil Brasil for WooCommerce receives security fixes for 12 months after it is published. The latest version is always supported.

## Reporting a vulnerability

Email security@facilo.es with the plugin version, the steps to reproduce and the impact. Please do not open a public forum thread for security issues.

We will reply by email, and credit the reporter in the changelog if they wish.

## Design notes

- The free plugin makes no outside HTTP requests and loads no remote scripts, fonts or images.
- All input is sanitized and validated on the server, all output is escaped, and every admin action checks a nonce and the user's capability.
- Customer documents (CPF, CNPJ, RG, IE) are stored only in the shop's own database, and can be exported or erased with WordPress's personal data tools.

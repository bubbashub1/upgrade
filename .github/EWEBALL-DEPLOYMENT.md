# BubbaHub eWeball GitHub Actions deployment

This repository uses GitHub Actions to deploy the BubbaHub plugin to eWeball over SSH.

## Branches

- `bubbahub-google-editor-fix` -> staging (`https://staging.bubbahub.co.uk`)
- `main` -> production (`https://bubbahub.co.uk`)

Production uses the GitHub `production` environment, so environment protection/required reviewers can be enabled before live deployment.

## Required GitHub secrets

Add these repository or environment secrets under GitHub Settings -> Secrets and variables -> Actions.

### Shared secrets

- `EWEBALL_SSH_KEY` - private Ed25519 SSH key. Never commit this key.
- `EWEBALL_KNOWN_HOSTS` - the SSH host key line(s) for the eWeball SSH host.
- `EWEBALL_SSH_USER` - eWeball SSH username.
- `EWEBALL_SSH_HOST` - eWeball SSH hostname.

### Staging environment secret

- `EWEBALL_STAGING_PATH` - absolute filesystem path to the staging WordPress root, normally the directory containing `wp-content`.

### Production environment secret

- `EWEBALL_PRODUCTION_PATH` - absolute filesystem path to the live WordPress root, normally the directory containing `wp-content`.

## Recommended GitHub environments

Create two environments:

1. `staging`
2. `production`

For `production`, enable required reviewers so merging to `main` does not immediately deploy to the live website without approval.

## eWeball SSH setup

In eWeball, use Manage Hosting -> SSH Access to add the public SSH key. eWeball documents SSH access and the SSH hostname/username in the hosting control panel.

The deployment only writes to:

`wp-content/plugins/bubba-hub-production/`

It does not replace the complete WordPress installation.

## Deployment flow

1. Push changes to `bubbahub-google-editor-fix`.
2. GitHub runs PHP syntax and package checks.
3. The plugin is deployed to staging.
4. Test the staging site.
5. Merge the tested branch into `main`.
6. GitHub runs the checks again.
7. GitHub waits for the `production` environment approval if protection is enabled.
8. The plugin is deployed to live.

## Safety

Do not put WordPress, eWeball, database, Stripe, PayPal, Google or SSH passwords in source files. Store deployment credentials only as GitHub Actions secrets.

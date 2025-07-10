# BlazeCommerce Vercel Direct Deployment Setup

## Overview

This document explains how to configure the BlazeCommerce plugin to use direct Vercel API authentication instead of the BlazeCommerce middleware for deployments.

## ⚠️ Important Considerations

**Before enabling direct Vercel deployment, please understand:**

1. **Middleware Benefits Lost**: The BlazeCommerce middleware may handle custom build processes, environment variables, and store-specific configurations that direct Vercel API calls won't replicate.

2. **Limited Functionality**: Direct Vercel API deployment is more basic and may not include all the features provided by the BlazeCommerce deployment service.

3. **Testing Required**: This is a significant architectural change that should be thoroughly tested before production use.

## Configuration Steps

### 1. Obtain Vercel API Token

1. Go to your [Vercel Dashboard](https://vercel.com/dashboard)
2. Navigate to **Settings** → **Tokens**
3. Click **Create Token**
4. Give it a descriptive name (e.g., "BlazeCommerce Plugin")
5. Set appropriate scope (usually account-wide)
6. Copy the generated token (you won't see it again)

### 2. Get Vercel Project Information

1. Go to your Vercel project dashboard
2. Navigate to **Settings** → **General**
3. Copy your **Project ID** (found in the project settings)
4. If using a team account, copy your **Team ID** from team settings

### 3. Configure BlazeCommerce Plugin

1. Go to **WordPress Admin** → **BlazeCommerce** → **General Settings**
2. Scroll to the **Vercel Deployment Settings** section
3. Configure the following fields:

   - **Enable Direct Vercel Deployment**: ✅ Check this box
   - **Vercel Deployment Token**: Paste your Vercel API token
   - **Vercel Project ID**: Enter your Vercel project ID
   - **Vercel Team ID**: Enter your team ID (optional, leave empty for personal projects)

4. Click **Save Settings**

### 4. Test the Configuration

1. After saving settings, click the **Redeploy Store Front** button
2. Monitor the deployment progress in the admin interface
3. Check the browser console for detailed logs
4. Verify the deployment appears in your Vercel dashboard

## Security Features

### Token Encryption
- Vercel tokens are encrypted using WordPress salts before storage
- Tokens are never exposed in frontend JavaScript or API responses
- Automatic decryption occurs only when needed for API calls

### Input Validation
- Project IDs and Team IDs are validated for proper format
- Only alphanumeric characters, hyphens, and underscores are allowed
- Empty values are permitted for optional fields

## API Endpoints Used

### Vercel API Endpoints
- **Create Deployment**: `POST https://api.vercel.com/v13/deployments`
- **Check Deployment**: `GET https://api.vercel.com/v13/deployments/{deployment_id}`

### Authentication Headers
```
Authorization: Bearer {vercel_token}
Content-Type: application/json
X-Vercel-Team-Id: {team_id} (if specified)
```

## Backward Compatibility

The plugin maintains full backward compatibility:

- **Existing Typesense functionality** remains unchanged
- **BlazeCommerce middleware** is still available when direct Vercel is disabled
- **Settings migration** is automatic - no data loss occurs

## Troubleshooting

### Common Issues

1. **HTTP 401 Unauthorized**
   - Verify your Vercel token is correct and hasn't expired
   - Check that the token has appropriate permissions

2. **HTTP 404 Not Found**
   - Verify your Project ID is correct
   - Ensure the project exists and you have access

3. **HTTP 403 Forbidden**
   - Check Team ID if using team projects
   - Verify token permissions for the specific project

4. **Deployment Timeout**
   - Large projects may take longer to deploy
   - Check Vercel dashboard for deployment status

### Debug Information

Enable WordPress debug logging to see detailed API responses:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Limitations

### Direct Vercel API Limitations
- No custom build environment variables from BlazeCommerce
- No store-specific deployment configurations
- No integration with other BlazeCommerce services
- Basic deployment metadata only

### Recommended Use Cases
- Simple static site deployments
- Projects not requiring complex build processes
- Development and testing environments
- Sites not heavily integrated with BlazeCommerce services

## Reverting to BlazeCommerce Middleware

To revert to the original BlazeCommerce middleware:

1. Go to **BlazeCommerce** → **General Settings**
2. **Uncheck** "Enable Direct Vercel Deployment"
3. Click **Save Settings**

The plugin will immediately switch back to using the BlazeCommerce middleware for all deployment operations.

## Support

For issues related to:
- **Direct Vercel API**: Check Vercel documentation and support
- **BlazeCommerce Plugin**: Contact BlazeCommerce support
- **WordPress Integration**: Check WordPress and plugin logs

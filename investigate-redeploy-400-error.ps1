# Investigation Script for Byron Bay Candles Redeploy HTTP 400 Error
# This script tests the external API and analyzes potential causes

Write-Host "=== Byron Bay Candles Redeploy Investigation ===" -ForegroundColor Green
Write-Host "Investigating HTTP 400 error when clicking 'Redeploy Store Front' button"
Write-Host ""

# Known configuration from previous investigations
$typesenseHost = "d5qgrfvxs1ouw48lp.a1.typesense.net"
$readonlyApiKey = "KSZseBDzRYq476OgkcYDxTbZomC8SrWb"
$storeId = "74"

# External API endpoint that's failing
$redeployApiUrl = "https://my-wooless-admin-portal.vercel.app/api/deployments"
$checkDeploymentUrl = "https://my-wooless-admin-portal.vercel.app/api/deployments?checkDeployment=1"

Write-Host "1. Testing External API Connectivity..." -ForegroundColor Yellow

# Test basic connectivity to the deployment API
try {
    Write-Host "   Testing basic connectivity to deployment API..."
    $response = Invoke-WebRequest -Uri $redeployApiUrl -Method Get -TimeoutSec 10 -ErrorAction Stop
    Write-Host "   ✓ Basic connectivity successful - HTTP $($response.StatusCode)" -ForegroundColor Green
} catch {
    Write-Host "   ✗ Basic connectivity failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "   This could indicate network/firewall issues from the server" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "2. Testing Authentication Header Format..." -ForegroundColor Yellow

# Test different authentication header formats that might be expected
$authString1 = "$readonlyApiKey" + ":" + "$storeId"
$authString2 = "$readonlyApiKey" + ":" + "$storeId"

$testHeaders = @(
    @{ "x-wooless-secret-token" = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($authString1)) },
    @{ "x-wooless-secret-token" = $authString2 },
    @{ "Authorization" = "Bearer $readonlyApiKey" },
    @{ "Authorization" = "Basic " + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($authString1)) },
    @{ "x-api-key" = $readonlyApiKey; "x-store-id" = $storeId }
)

foreach ($i in 0..($testHeaders.Count - 1)) {
    $headers = $testHeaders[$i]
    $headerDesc = ($headers.Keys | ForEach-Object { "$_`: $($headers[$_])" }) -join ", "
    
    Write-Host "   Testing header format $($i + 1): $headerDesc"
    
    try {
        # Test GET request (check deployment)
        $response = Invoke-WebRequest -Uri $checkDeploymentUrl -Method Get -Headers $headers -TimeoutSec 10 -ErrorAction Stop
        Write-Host "   ✓ GET request successful - HTTP $($response.StatusCode)" -ForegroundColor Green
        Write-Host "   Response: $($response.Content.Substring(0, [Math]::Min(100, $response.Content.Length)))..."
        
        # Test POST request (trigger deployment)
        try {
            $postResponse = Invoke-WebRequest -Uri $redeployApiUrl -Method Post -Headers $headers -TimeoutSec 10 -ErrorAction Stop
            Write-Host "   ✓ POST request successful - HTTP $($postResponse.StatusCode)" -ForegroundColor Green
            Write-Host "   Response: $($postResponse.Content.Substring(0, [Math]::Min(100, $postResponse.Content.Length)))..."
        } catch {
            $statusCode = $_.Exception.Response.StatusCode.value__
            Write-Host "   ✗ POST request failed - HTTP $statusCode" -ForegroundColor Red
            if ($statusCode -eq 400) {
                Write-Host "   → This matches the reported error!" -ForegroundColor Cyan
            }
            Write-Host "   Error: $($_.Exception.Message)"
        }
        
    } catch {
        $statusCode = $_.Exception.Response.StatusCode.value__
        Write-Host "   ✗ GET request failed - HTTP $statusCode" -ForegroundColor Red
        Write-Host "   Error: $($_.Exception.Message)"
    }
    
    Write-Host ""
}

Write-Host "3. Testing with Different API Keys..." -ForegroundColor Yellow

# The readonly API key might not have deployment permissions
# Test with different potential API key formats
Write-Host "   Note: The readonly API key might not have deployment permissions"
Write-Host "   HTTP 400 could indicate insufficient permissions or wrong API key"
Write-Host ""

Write-Host "4. Analyzing Potential Causes of HTTP 400..." -ForegroundColor Yellow

$potentialCauses = @(
    "Invalid or expired API key for deployment operations",
    "Readonly API key doesn't have deployment permissions", 
    "Missing required request body or parameters",
    "Incorrect authentication header format",
    "Store ID mismatch or invalid store ID",
    "API endpoint expects different content-type",
    "Rate limiting or API quota exceeded",
    "Server-side validation failing on request format"
)

foreach ($cause in $potentialCauses) {
    Write-Host "   • $cause" -ForegroundColor White
}

Write-Host ""
Write-Host "5. Recommendations for Resolution..." -ForegroundColor Yellow

$recommendations = @(
    "Verify the API key in WordPress admin has deployment permissions (not just readonly)",
    "Check if a different API key is needed for deployment vs. search operations",
    "Ensure the Store ID (74) is correct and matches the deployment service",
    "Contact the deployment service provider to verify API requirements",
    "Check WordPress error logs for more detailed error information",
    "Test with a fresh API key from the deployment service dashboard"
)

foreach ($rec in $recommendations) {
    Write-Host "   -> $rec" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "6. Testing Typesense Connection (for comparison)..." -ForegroundColor Yellow

# Test the Typesense connection to verify the API key works for search operations
try {
    $typesenseUrl = "https://$typesenseHost/collections"
    $typesenseHeaders = @{ "X-TYPESENSE-API-KEY" = $readonlyApiKey }
    
    $response = Invoke-WebRequest -Uri $typesenseUrl -Method Get -Headers $typesenseHeaders -TimeoutSec 10 -ErrorAction Stop
    Write-Host "   ✓ Typesense API connection successful - HTTP $($response.StatusCode)" -ForegroundColor Green
    Write-Host "   This confirms the API key works for Typesense operations"
} catch {
    Write-Host "   ✗ Typesense API connection failed: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "   This suggests the API key itself might be invalid"
}

Write-Host ""
Write-Host "=== Investigation Complete ===" -ForegroundColor Green
Write-Host "The HTTP 400 error is likely due to:" -ForegroundColor Yellow
Write-Host "1. Using a readonly API key for deployment operations" -ForegroundColor White
Write-Host "2. Missing deployment permissions on the current API key" -ForegroundColor White
Write-Host "3. Incorrect authentication format expected by the deployment API" -ForegroundColor White
Write-Host ""
Write-Host "Next steps: Check WordPress admin for the correct deployment API key" -ForegroundColor Cyan

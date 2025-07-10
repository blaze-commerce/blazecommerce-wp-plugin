# Test the redeploy API to understand the HTTP 400 error
Write-Host "=== Testing Byron Bay Candles Redeploy API ===" -ForegroundColor Green
Write-Host "Investigating HTTP 400 error from deployment API"
Write-Host ""

# Known configuration
$readonlyApiKey = "KSZseBDzRYq476OgkcYDxTbZomC8SrWb"
$storeId = "74"
$redeployApiUrl = "https://my-wooless-admin-portal.vercel.app/api/deployments"
$checkDeploymentUrl = "https://my-wooless-admin-portal.vercel.app/api/deployments?checkDeployment=1"

Write-Host "1. Testing Basic Connectivity..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri $redeployApiUrl -Method Get -TimeoutSec 10 -ErrorAction Stop
    Write-Host "   ✓ Basic GET connectivity successful - HTTP $($response.StatusCode)" -ForegroundColor Green
} catch {
    Write-Host "   ✗ Basic connectivity failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "2. Testing Authentication Headers..." -ForegroundColor Yellow

# Test the exact header format used by the plugin
$authString = $readonlyApiKey + ":" + $storeId
$base64Auth = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($authString))

$headers = @{
    "x-wooless-secret-token" = $base64Auth
}

Write-Host "   Testing header: x-wooless-secret-token = $base64Auth"

# Test GET request (check deployment)
Write-Host "   Testing GET request (check deployment)..."
try {
    $response = Invoke-WebRequest -Uri $checkDeploymentUrl -Method Get -Headers $headers -TimeoutSec 10 -ErrorAction Stop
    Write-Host "   ✓ GET request successful - HTTP $($response.StatusCode)" -ForegroundColor Green
    Write-Host "   Response: $($response.Content)"
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    Write-Host "   ✗ GET request failed - HTTP $statusCode" -ForegroundColor Red
    Write-Host "   Error: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "   Testing POST request (trigger deployment)..."
try {
    $response = Invoke-WebRequest -Uri $redeployApiUrl -Method Post -Headers $headers -TimeoutSec 10 -ErrorAction Stop
    Write-Host "   ✓ POST request successful - HTTP $($response.StatusCode)" -ForegroundColor Green
    Write-Host "   Response: $($response.Content)"
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    Write-Host "   ✗ POST request failed - HTTP $statusCode" -ForegroundColor Red
    if ($statusCode -eq 400) {
        Write-Host "   → This matches the reported error!" -ForegroundColor Cyan
    }
    Write-Host "   Error: $($_.Exception.Message)"
    
    # Try to get more details from the response
    try {
        $errorResponse = $_.Exception.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($errorResponse)
        $errorBody = $reader.ReadToEnd()
        Write-Host "   Error Response Body: $errorBody" -ForegroundColor Yellow
    } catch {
        Write-Host "   Could not read error response body" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "3. Testing Alternative Authentication Methods..." -ForegroundColor Yellow

# Test different auth methods
$altHeaders = @(
    @{ "Authorization" = "Bearer $readonlyApiKey" },
    @{ "x-api-key" = $readonlyApiKey; "x-store-id" = $storeId },
    @{ "x-wooless-secret-token" = $authString }
)

$authMethods = @("Bearer Token", "Separate Headers", "Plain Auth String")

for ($i = 0; $i -lt $altHeaders.Count; $i++) {
    Write-Host "   Testing $($authMethods[$i])..."
    try {
        $response = Invoke-WebRequest -Uri $redeployApiUrl -Method Post -Headers $altHeaders[$i] -TimeoutSec 10 -ErrorAction Stop
        Write-Host "   ✓ $($authMethods[$i]) successful - HTTP $($response.StatusCode)" -ForegroundColor Green
    } catch {
        $statusCode = $_.Exception.Response.StatusCode.value__
        Write-Host "   ✗ $($authMethods[$i]) failed - HTTP $statusCode" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "4. Analysis and Recommendations..." -ForegroundColor Yellow

Write-Host "   Potential causes of HTTP 400 error:" -ForegroundColor White
Write-Host "   • Readonly API key doesn't have deployment permissions" -ForegroundColor White
Write-Host "   • Wrong API key - deployment might need different key than search" -ForegroundColor White
Write-Host "   • Missing required request body or parameters" -ForegroundColor White
Write-Host "   • Incorrect authentication header format" -ForegroundColor White
Write-Host "   • Store ID mismatch or invalid" -ForegroundColor White

Write-Host ""
Write-Host "   Recommendations:" -ForegroundColor Cyan
Write-Host "   → Check if there's a separate deployment API key in the admin" -ForegroundColor Cyan
Write-Host "   → Verify the Store ID (74) is correct for deployment" -ForegroundColor Cyan
Write-Host "   → Contact deployment service provider for API requirements" -ForegroundColor Cyan
Write-Host "   → Check if the API key has expired or been revoked" -ForegroundColor Cyan

Write-Host ""
Write-Host "=== Test Complete ===" -ForegroundColor Green
